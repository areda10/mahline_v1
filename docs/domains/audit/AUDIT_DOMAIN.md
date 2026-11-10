# MAHLINE — Domaine Audit

## Statut

Première implémentation technique sur la branche `feature/audit-domain-v7`. À valider avant intégration dans `main`.

## Responsabilité

Le domaine Audit conserve les événements métier et administratifs importants sous forme d'historique append-only. Il ne remplace ni les logs techniques ni les journaux d'authentification, de sessions ou de sécurité du domaine Identity.

### Séparation des responsabilités

- **Identity** conserve les événements de connexion, de session et de sécurité.
- **Audit** conserve les opérations métier et administratives : changements de statut, création/modification/suppression/restauration d'entités, changements de rôles et permissions, actions de gestion.
- Les journaux techniques servent au diagnostic d'exploitation.

## Stockage

La table `audit_events` contient l'acteur, la coopérative de contexte, le sujet, l'action, les valeurs avant/après, les métadonnées minimales et l'heure de l'événement.

Les identifiants d'acteur et de sujet sont conservés sans clés étrangères vers les domaines concernés afin que la suppression ou l'archivage d'une entité ne fasse pas disparaître l'historique. Le type et l'identifiant du sujet sont des références descriptives, pas une relation Eloquent garantie.

## Écriture

Les domaines appellent `AuditService::record(AuditEntryDTO $entry)` après avoir autorisé l'action métier. Lorsque l'opération métier et son événement doivent être atomiques, les deux écritures doivent être exécutées dans la même transaction de base de données.

Exemple :

```php
app(\App\Domains\Audit\Services\AuditService::class)->record(
    new \App\Shared\DTOs\AuditEntryDTO(
        eventKey: 'PRODUCT_STATUS_CHANGED',
        action: 'STATUS_CHANGE',
        actorType: \App\Domains\Identity\Users\Models\User::class,
        actorId: (string) $user->getKey(),
        cooperativeId: (string) $product->cooperative_id,
        subjectType: $product::class,
        subjectId: (string) $product->getKey(),
        oldValues: ['is_active' => false],
        newValues: ['is_active' => true],
    ),
);
```

Ne transmettez que les champs nécessaires. Le service filtre plusieurs clés de secrets connues, mais ce filtre n'est pas un substitut à la minimisation des données à la source.

## Immutabilité et accès

Les événements sont append-only au niveau des opérations Eloquent du modèle. Cette protection n'empêche pas un accès SQL direct ou une requête de masse via le Query Builder ; les droits de base de données et les procédures de maintenance doivent également protéger l'historique.

La policy refuse les créations, modifications, suppressions et restaurations via les capacités d'autorisation. La consultation exige la permission `audit.view`; l'application doit ajouter cette permission à la matrice d'autorisation et aux rôles voulus avant d'exposer une interface ou une route.

## Événements des autres domaines

Cette première phase fournit le contrat d'écriture commun. Elle ne branche pas automatiquement chaque action des domaines Identity, Cooperatives, Catalog, Categories, Products, Finance ou Inventory. Chaque intégration doit être ajoutée explicitement et couverte par des tests de régression, sans dupliquer les journaux propres à Identity.

## Limites à traiter avant mise en production

- Ajouter `audit.view` à la matrice de permissions et décider précisément quels rôles la reçoivent.
- Définir la politique de rétention, archivage et purge technique contrôlée.
- Ajouter les tests d'intégration MySQL et les tests de politique selon la configuration réelle du projet.
- Décider si l'IP et le user-agent sont nécessaires pour chaque type d'événement, compte tenu de la confidentialité.
- Ajouter des intégrations explicites dans chaque domaine après validation de son service métier.


## Intégration Identity — verrouillage d'urgence root-only

L'implémentation Identity du verrouillage d'urgence utilise le contrat commun AuditService::record(AuditEntryDTO $entry) et les événements suivants :

- identity.authentication.emergency_lock.applied : opération administrative de verrouillage du User ;
- identity.authentication.session_revoked : révocation unitaire de chaque session avec le motif emergency_lock ;
- identity.authentication.token_revoked : révocation unitaire de chaque token API avec le motif emergency_lock ;
- identity.authentication.emergency_lock.released : levée administrative du verrouillage.

Le contrôleur exécute l'état d'urgence, les révocations et les événements dans la même transaction. Les événements Identity spécialisés (Login History, session et sécurité) restent complémentaires et ne sont pas remplacés par l'Audit. Aucun secret de session, mot de passe ou token en clair ne doit être enregistré.

Fichiers d'intégration : app/Domains/Identity/Authentication/Http/Controllers/AuthenticationLockoutController.php et app/Domains/Identity/Authentication/Services/SessionService.php.


## Intégration Identity — détection d'un nouvel appareil

Lorsqu'un utilisateur s'authentifie depuis un appareil jamais enregistré pour son compte, le hook applicatif `RecordNewDeviceAuthenticationWorkflowHook` écrit l'événement append-only `identity.authentication.new_device_detected`, dont le sujet est la nouvelle `AuthenticationSession`. L'événement spécialisé `unusual_activity_detected` / `new_device_detected` est également persisté dans `security_events`. Les deux journaux sont complémentaires ; Login History conserve séparément le succès de connexion normal. Un appareil déjà connu ne génère aucun doublon.

Fichiers d'intégration : `app/Domains/Identity/Authentication/Workflows/AuthenticationWorkflow.php`, `app/Domains/Identity/Authentication/Workflows/RecordNewDeviceAuthenticationWorkflowHook.php` et `app/Providers/AppServiceProvider.php`.
