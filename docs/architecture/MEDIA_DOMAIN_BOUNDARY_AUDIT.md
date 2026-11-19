# Audit de frontière du domaine Media — MAHLINE V1

- **Date :** 19 novembre 2026
- **Branche :** `feature/Marketplace`
- **HEAD audité :** `2945c62a34d928f277cc02a57a0e22d0b6f62a51`
- **Statut :** audit statique ciblé ; aucune modification applicative dans ce lot
- **Référence :** ADR-021 — monolithe modulaire hybride

## 1. Périmètre vérifié

Fichiers inspectés :
- `app/Domains/Media/Enums/MediaCategory.php`
- `app/Domains/Media/Services/MediaService.php`
- `app/Domains/Media/Observers/MediaOwnerObserver.php`
- `app/Domains/Media/Models/Media.php`
- `app/Domains/Media/Policies/MediaPolicy.php`
- `app/Domains/Media/Providers/MediaServiceProvider.php`
- `app/Domains/Products/Models/Product.php`
- `app/Domains/Cooperatives/Models/Cooperative.php`
- `app/Domains/Identity/Users/Models/User.php`
- `app/Providers/AppServiceProvider.php`
- `bootstrap/providers.php`
- `app/Domains/Media/README.md`

## 2. Responsabilités à préserver

Media reste propriétaire :
- des fichiers originaux et dérivés, de leur stockage et de leurs métadonnées ;
- de la validation de contenu, MIME, extension, taille, dimensions et intégrité ;
- des catégories techniques de médias, quotas, ordre et image principale ;
- de la suppression logique, de la rétention, de la purge et du nettoyage des orphelins ;
- des incidents Media et de leurs pièces jointes privées ;
- de la journalisation de ses opérations via le contrat public AuditWriter.

Les domaines propriétaires conservent le sens métier de leurs ressources :
- Products : image produit et documents associés ;
- Cooperatives : logo, photos et documents de la coopérative ;
- Identity : photo de profil et documents personnels ;
- Media : pièces jointes d'incidents Media.

Les règles V1 de sécurité et de visibilité du README Media restent inchangées pendant le refactor.

## 3. Violations et couplages constatés

| Fichier | Dépendance constatée | Risque |
|---|---|---|
| `MediaCategory.php` | imports directs Product, Cooperative, User | Media connaît les modèles privés des domaines fournisseurs |
| `MediaService.php` | imports Product, Cooperative, User ; acteur typé User | dépendance directe aux modèles et à l'identité concrète |
| `MediaOwnerObserver.php` | imports Product, Cooperative, User et branches `instanceof` | cycle de dépendance et logique d'ownership dispersée |
| `MediaPolicy.php` | imports Product, Cooperative, User et règles d'accès spécifiques | Media porte des détails internes des domaines et de leurs permissions |
| `AppServiceProvider.php` | enregistrement global de MediaOwnerObserver sur Product, Cooperative, User | composition des observers centralisée hors des providers propriétaires |
| modèles Product/Cooperative/User | relations Eloquent vers Media | dépendance de relation inverse à documenter et remplacer uniquement si un contrat compatible existe |

## 4. Cible d'intégration

Ne pas introduire un service Media qui importe les modèles des domaines fournisseurs. Les domaines propriétaires doivent exposer des surfaces publiques minimales permettant à Media de vérifier :
- l'identité stable et le type de propriétaire ;
- l'existence et l'état supprimé/restauré ;
- l'identifiant de coopérative associé lorsqu'il existe ;
- le contexte d'accès nécessaire à la policy ;
- les opérations de cycle de vie qui doivent préserver les médias.

Media doit consommer ces contrats et DTO, sans connaître les modèles Eloquent privés. Le contexte d'acteur doit également être abstrait par une surface Identity stable, au lieu de faire dépendre les services Media du modèle User concret.

Le point de composition Laravel assemble les adaptateurs. Les dépendances doivent rester orientées et acycliques. Les contrats ne doivent pas exposer de repository, relation Eloquent ou table interne.

## 5. Séquence de refactor proposée

1. Ajouter des tests de caractérisation sur chaque catégorie et chaque propriétaire : upload, consultation publique/privée, gestion, restauration, suppression et accès multi-coopératif.
2. Définir les contrats fournisseurs et DTO d'acteur avec leurs propriétaires exacts ; documenter les signatures et les permissions consommées.
3. Implémenter les adaptateurs dans Products, Cooperatives et Identity ; conserver MediaIncident dans Media.
4. Introduire le registre/port de résolution des propriétaires dans Media, avec liaison explicite des morph types autorisés.
5. Remplacer les imports directs dans MediaCategory, MediaService et MediaPolicy par les contrats et le registre.
6. Déplacer l'enregistrement des observers vers les providers propriétaires ou leurs adaptateurs ; retirer les bindings globaux seulement après vérification de tous les usages.
7. Préserver les relations Eloquent existantes tant que leurs consommateurs n'ont pas été remplacés ; toute suppression nécessite une migration de code complète et des tests.
8. Exécuter tests Media, tests propriétaires, tests de concurrence, suite complète et Pint ; conserver les résultats.

## 6. Contraintes non négociables

- Aucune migration déjà exécutée ne sera modifiée.
- Aucune table ou FK nouvelle n'est nécessaire pour le découplage décrit ici.
- Ne pas modifier les statuts, catégories, quotas, règles d'autorisation ou la rétention Media au cours du refactor.
- Ne pas affaiblir les contrôles antivirus, MIME, taille, intégrité, journalisation ou accès privé.
- Les opérations concurrentes de remplacement d'image principale, suppression et réorganisation restent verrouillées et atomiques.
- Toute erreur d'upload doit continuer à annuler la transaction DB et nettoyer les fichiers créés sans masquer l'erreur initiale.
- Les pièces jointes d'incident restent privées et consultables uniquement par `super_admin_root`, avec contrôles et audit associés.

## 7. État de clôture

- Audit statique des dépendances directes : réalisé pour les fichiers listés.
- Contrats publics propriétaires : à concevoir.
- Tests de caractérisation : à inventorier et compléter.
- Refactor applicatif : non commencé.
- Tests PHPUnit/Pint : non exécutés dans ce lot.
