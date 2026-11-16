# MAHLINE — Audit hiérarchie des rôles, périmètres et affectations

> Branche auditée : `feature/Marketplace`
> Date : 16 novembre 2026
> Statut : audit en cours, premières corrections appliquées. Ne pas fusionner.
> Tests : non exécutés par l'assistant ; ils doivent être lancés localement avant validation.

## 1. Hiérarchie métier de référence

Les quatre rôles internes sont :
1. `super_admin_root` : administration globale et responsable de toutes les affectations.
2. `super_admin` : responsable d'une ou plusieurs coopératives affectées, avec une seule affectation super_admin par coopérative.
3. `cooperative_admin` : responsable opérationnel d'une coopérative.
4. `user` : utilisateur opérationnel d'une seule coopérative.

Le client est un acteur commercial externe, distinct de l'utilisateur interne. Il n'appartient pas à la hiérarchie interne.

## 2. Relations constatées dans le code

### 2.1 Compte / rôle
- Modèle : `App\Domains\Identity\Users\Models\User`.
- Table : `users`.
- Relation `roles()` : many-to-many via `user_role`.
- La table `user_role` impose l'unicité du couple `user_id, role_id`, mais n'impose pas un rôle unique par compte.
- Le service de mise à jour exige maintenant exactement un rôle opérationnel sur le compte cible. Une contrainte globale d'un seul rôle en base reste à décider/contrôler sur les données existantes.

### 2.2 Affectation super_admin / coopérative
- Relation many-to-many via `super_admin_cooperative`.
- Le service crée automatiquement l'affectation lorsque `super_admin` crée une nouvelle coopérative.
- Seul Root dispose des routes et services d'affectation/désaffectation.
- Une nouvelle migration ajoute une unicité sur `cooperative_id` pour imposer un seul super_admin par coopérative.
- La migration échoue volontairement si des coopératives ont déjà plusieurs super_admin affectés ; ces données doivent être arbitrées avant migration.

### 2.3 Comptes cooperative_admin et user
- Relation via `users.cooperative_id` (belongsTo / hasMany implicite).
- Chaque compte est rattaché à une coopérative.
- Root peut changer l'affectation via la fiche du compte.
- Les rôles inférieurs ne peuvent pas déplacer un compte d'une coopérative à une autre.

### 2.4 Client externe
- Le code actuel utilise encore le modèle `User`, la table `users` et le rôle `customer`.
- Des routes, contrôleurs et middleware Customer dédiés isolent une partie du parcours client.
- Cela distingue déjà les parcours applicatifs, mais pas encore les modèles/tables/authentifications physiques. Ce point doit rester explicite dans la conception : la décision métier confirme un acteur client séparé, mais le choix d'un modèle/table séparé n'a pas encore été validé.

## 3. Matrice des droits métier

| Opération | super_admin_root | super_admin | cooperative_admin | user |
|---|---|---|---|---|
| CRUD comptes super_admin | Oui | Non | Non | Non |
| CRUD comptes cooperative_admin | Oui | Création du premier par coopérative ; lecture/modification dans le périmètre | Non ; ne peut même pas consulter les autres admins de sa coopérative | Non |
| CRUD comptes user | Oui | Création/modification dans les coopératives assignées | Lecture/modification dans sa coopérative | Non |
| Changer rôle d'un compte | Oui, hors root | Non | Non | Non |
| Changer affectation d'un compte | Oui | Non | Non | Non |
| Affecter un super_admin à une coopérative | Oui uniquement | Non | Non | Non |
| Créer une coopérative | Oui | Oui, affectation automatique au créateur | Non | Non |
| Modifier une coopérative | Toutes les données non techniques/audit | Coopératives assignées, données non techniques/audit | Téléphone, email, adresse uniquement | Non |
| Supprimer une coopérative | Oui | Non | Non | Non |
| Activer un compte | Oui | Non | Non | Non |
| Désactiver / suspendre un compte | Oui | Comptes de son périmètre | Non | Non |
| Archiver / restaurer un compte | Oui | Non | Non | Non |
| Suppression logique / restauration supprimée | Oui | Non | Non | Non |
| Consulter le journal d'audit | Oui | Non | Non | Non |

## 4. Écarts détectés et corrections déjà appliquées

### Corrigé dans cette série de commits
- `UserManagementService::updateUserAccount()` n'était accessible qu'à Root : désormais, super_admin peut modifier les fiches cooperative_admin/user de son périmètre ; cooperative_admin peut modifier uniquement les fiches user de sa coopérative.
- Les rôles et `cooperative_id` sont immuables pour super_admin et cooperative_admin lors de la modification d'une fiche.
- Ajout de `UserPolicy::update()` avec le périmètre correspondant.
- Ajout des routes et de la vue d'édition de fiche utilisateur dans l'espace back-office.
- Le listing cooperative_admin est limité aux comptes `user` ; l'accès direct à la fiche d'un autre cooperative_admin est refusé.
- La Policy d'adresse interdit au cooperative_admin de modifier l'adresse d'un autre cooperative_admin.
- Le service de création impose que le super_admin ne crée que le premier cooperative_admin d'une coopérative. La ligne coopérative est verrouillée pendant la vérification pour éviter deux créations concurrentes.
- Suppression du droit `cooperatives.delete` pour super_admin dans la matrice ; la Policy réserve la suppression de coopérative à Root.
- Retrait de `audit.view` des matrices super_admin et cooperative_admin.
- L'écran d'affectation Root ne propose plus les coopératives déjà affectées à un autre super_admin.
- Le service d'affectation verrouille la coopérative et refuse une seconde affectation super_admin.
- Migration ajoutant une contrainte unique sur `super_admin_cooperative.cooperative_id`.

### À vérifier encore dans les tests locaux
- Tests de Policy d'édition des fiches et de non-consultation des cooperative_admin pairs.
- Tests de service de création du premier cooperative_admin, refus du second par super_admin et création multiple par Root.
- Tests de contrainte de super_admin unique par coopérative.
- Tests de routes et rendu de la nouvelle vue `backoffice.users.edit`.
- Tests complets Identity, Cooperatives, puis suite complète MAHLINE et Pint.

## 5. Points techniques restant à auditer avant de déclarer l'alignement complet

1. Vérifier toutes les méthodes du service de cycle de vie pour s'assurer que les protections Root-only sont appliquées aussi au niveau service, pas uniquement dans les Policies/contrôleurs.
2. Contrôler les relations et données existantes avant toute contrainte « un rôle par compte » ou « rôle ↔ cooperative_id ».
3. Le client utilise encore `User/customer` : déterminer si l'acteur client doit rester dans `users` avec un rôle externe ou migrer vers un modèle/table distincts.
4. Vérifier que les écrans Root excluent/traitent correctement les comptes client, sans les confondre avec les comptes internes.
5. Vérifier les droits d'accès aux adresses, médias, catalogues, catégories, produits et paramètres de coopérative pour les quatre rôles.
6. Vérifier que toutes les listes et recherches appliquent un scope SQL cohérent, et pas seulement un contrôle de Policy après chargement.
7. Vérifier l'intégrité des affectations super_admin existantes avant exécution de la migration unique.
8. Vérifier les interfaces dédiées par rôle : Root, super_admin, cooperative_admin et user. L'édition de fiche est maintenant ajoutée dans l'espace back-office, mais l'alignement complet de chaque tableau de bord et menu reste à vérifier.

## 6. Fichiers principaux examinés

- `app/Domains/Identity/UserManagement/Services/UserManagementService.php`
- `app/Domains/Identity/Authorization/Policies/UserPolicy.php`
- `app/Domains/Identity/Authorization/Policies/CooperativePolicy.php`
- `app/Domains/Identity/Authorization/Policies/AddressPolicy.php`
- `app/Domains/Cooperatives/Services/CooperativeService.php`
- `app/Domains/Cooperatives/Models/Cooperative.php`
- `app/Domains/Identity/Users/Models/User.php`
- `app/Domains/Identity/Authorization/Models/SuperAdminCooperative.php`
- `app/Domains/Identity/UserManagement/Http/Controllers/UserManagementController.php`
- `app/Domains/Identity/UserManagement/Http/Controllers/SuperAdminCooperativeAssignmentController.php`
- `app/Domains/Identity/SuperAdminRoot/Http/Controllers/UserManagementController.php`
- `config/authorization.php`
- `database/seeders/PermissionSeeder.php`
- `database/seeders/RolePermissionSeeder.php`
- `database/seeders/RoleSeeder.php`
- `routes/web.php`
- Migrations `create_users`, `add_cooperative_id_to_users`, `create_super_admin_cooperative`, `create_user_role`.

