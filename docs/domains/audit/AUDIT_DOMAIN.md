# MAHLINE V1 — Domaine Audit

- **Branche de travail :** `feature/Marketplace`
- **Statut technique :** implémentation présente ; validation d'exécution MySQL à effectuer localement
- **Périmètre :** événements métier et administratifs append-only

## 1. Responsabilités

Audit conserve les opérations métier et administratives importantes : création, modification, suppression logique, restauration, changement de statut, changement de rôle et opérations sensibles. Il ne remplace pas les journaux Identity :

- Login History conserve les tentatives et résultats d'authentification.
- Les journaux de session et de sécurité conservent leurs événements spécialisés.
- Audit conserve la trace métier et administrative correspondante lorsqu'elle est requise.

## 2. Schéma et intégrité

La migration `2026_10_29_000001_create_audit_events_table.php` crée :

- `audit_events`, sans clés étrangères vers les entités métier afin de préserver l'historique ;
- `audit_chain_heads`, tête unique de la chaîne SHA-256 ;
- `previous_hash` et `event_hash` sur chaque événement ;
- les index de consultation par événement, acteur, coopérative, sujet et corrélation.

L'écriture est sérialisée par verrouillage de la ligne `audit_chain_heads`. Le hash porte sur un payload canonique incluant les valeurs avant/après, métadonnées, acteur, sujet, contexte HTTP et date. `AuditEventRepository::verifyIntegrity()` vérifie la chaîne complète et détecte les événements manquants, altérés ou orphelins.

L'immutabilité est appliquée à plusieurs niveaux :

1. `AuditEvent` refuse les mises à jour et suppressions Eloquent ;
2. les triggers MySQL refusent les UPDATE/DELETE SQL directs sur `audit_events` ;
3. un trigger empêche de remplacer ou supprimer la tête de chaîne sans avancer vers l'événement suivant.

## 3. Données sensibles

`AuditService` filtre récursivement les clés sensibles avant écriture : mots de passe, tokens, secrets, cookies, sessions, OTP, codes de vérification et clés privées. Ce filtrage est une défense complémentaire : chaque domaine doit minimiser les données envoyées à l'audit et ne jamais transmettre de secret en clair.

## 4. Autorisations

La consultation du journal d'audit est réservée à `super_admin_root` avec la permission `audit.view`. Les autres rôles ne disposent pas d'un accès au journal. La Policy refuse les opérations de création, modification, suppression et restauration par les utilisateurs.

Les identifiants acteur/sujet restent des références descriptives sans FK afin que la suppression logique ou physique d'une entité ne supprime pas son historique.

## 5. Intégration dans les domaines

Les services métier doivent appeler `AuditService::record(AuditEntryDTO $entry)` dans la même transaction que l'opération lorsque l'atomicité est requise. Les événements Identity spécialisés restent séparés et complémentaires.

L'implémentation comprend notamment des événements d'audit pour les changements d'identité, les sessions/tokens sensibles, les coopératives, les adresses, les médias, les catalogues, les catégories et les produits. Les créations, modifications, suppressions et changements d'associations des catalogues sont attribués à l'acteur HTTP ; les suppressions de branches de catégories tracent aussi le détachement des catégories locales et la désactivation des produits devenus sans catégorie active. Cette liste n'est pas une preuve que chaque opération de chaque domaine est déjà couverte : chaque service doit encore être vérifié avec ses tests dédiés.

## 6. Tests et validation

`tests/Framework/Domains/Audit/AuditServiceTest.php` couvre notamment :

- l'écriture et l'ordre de la chaîne ;
- la vérification d'intégrité ;
- le filtrage récursif des clés sensibles ;
- l'immutabilité Eloquent ;
- les triggers MySQL d'immutabilité et de protection de la tête ;
- la capture du contexte HTTP et de l'identifiant de corrélation.

Les tests existent dans le dépôt, mais leur exécution sur MySQL 8.4 et la suite complète MAHLINE restent à effectuer localement. Aucun statut « tests verts » n'est déclaré ici.
