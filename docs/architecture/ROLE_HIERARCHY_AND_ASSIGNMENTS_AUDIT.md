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
- Modèle : `App\\Domains\\Identity\\Users\\Models\\User`.
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

## 7. Release V2 — Spécification différée : autorité globale de super_admin_root

> **Décision validée le 16 novembre 2026 : cette évolution est reportée à Release V2.**
> Ne pas développer ni activer cette évolution dans Release V1. La présente section est une spécification fonctionnelle/technique à conserver pour sa planification V2.

### 7.1 Principe d'autorisation global

- `super_admin_root` est le niveau 1 de la hiérarchie MAHLINE.
- En Release V2, ce rôle disposera automatiquement de toutes les permissions MAHLINE actives, dans tous les domaines et pour toutes les coopératives.
- L'accès Root ne dépendra pas d'une attribution individuelle de permissions en base.
- Toute nouvelle permission active ajoutée dans un domaine sera automatiquement incluse dans l'autorité Root, sans devoir modifier manuellement le compte Root ou une liste de permissions.
- Cette règle devra être centralisée dans le mécanisme d'autorisation Laravel afin d'éviter des traitements différents selon les Policies, Gates, middleware, contrôleurs et services.
- Les rôles `super_admin`, `cooperative_admin` et `user` conserveront strictement leurs permissions et périmètres propres. L'autorité globale Root ne devra pas leur être transmise.

### 7.2 Périmètre fonctionnel global Root

En V2, l'autorité Root couvrira globalement les opérations CRUD et administratives autorisées sur :
- comptes internes `super_admin`, `cooperative_admin` et `user` ;
- coopératives et toutes leurs données administratives ;
- affectations des super_admin, cooperative_admin et user ;
- catalogue, catégories globales et locales, produits et variantes ;
- médias et photos ;
- commandes et opérations de traitement ;
- factures et opérations de facturation ;
- inventaire, stocks et opérations de stock ;
- paiements, reversements et commissions ;
- livraison, transporteurs et transferts ;
- paramètres, configuration et fonctions administratives de tous les domaines ;
- consultation des journaux d'audit.

Les comptes `super_admin_root` eux-mêmes restent exclus de la gestion CRUD/lifecycle ordinaire des comptes : un compte Root ne doit pas pouvoir être modifié, désactivé, suspendu, archivé ou supprimé par le module standard de gestion des utilisateurs.

### 7.3 Invariants métier et limites non contournables

L'autorité globale Root ne constitue pas un droit de contourner l'intégrité métier, financière ou historique :
- Les événements d'audit restent append-only : Root peut les consulter, jamais les modifier ni les supprimer.
- Une facture validée reste immuable. Une annulation conserve la facture et son historique ; toute nouvelle commande produit sa propre facture.
- Les mouvements de stock enregistrés restent immuables pour tous les rôles, y compris Root. Une correction passe par un mouvement compensatoire.
- Les commandes et historiques commerciaux ne doivent pas être effacés ou réécrits au mépris des règles de conservation validées.
- Les opérations financières et de reversement doivent préserver les transactions, rapprochements, traces, montants et références.
- Les contraintes relationnelles, règles de portée des données, verrous transactionnels et contrôles de cohérence restent applicables.
- Les protections du compte Root lui-même et les invariants ci-dessus doivent être vérifiés au niveau Policies/services, et pas uniquement au niveau interface.

### 7.4 Routes, middleware, Gates et Policies à harmoniser en V2

L'audit a relevé que plusieurs Policies et contrôles de permissions individuels peuvent encore bloquer Root, malgré son rôle hiérarchique. En V2, auditer et harmoniser l'ensemble des couches :
1. Routes web et API de chaque rôle et de chaque domaine.
2. Middleware de rôle, d'état de compte, de session et de permissions/abilities.
3. Gates et fournisseur central d'autorisation.
4. Toutes les Policies, y compris les règles d'accès global et les exceptions d'intégrité.
5. Contrôleurs, services et tâches asynchrones qui répètent ou contournent des vérifications d'autorisation.
6. Requêtes de listing, recherche, export et téléchargement : le scope SQL doit être cohérent avec le rôle et le périmètre.
7. Interfaces, menus et tableaux de bord des quatre rôles internes.

Points de contrôle déjà identifiés :
- Des Policies Root vérifient encore des permissions individuelles en base.
- La consultation des événements d'audit Root dépend encore de `audit.view`, alors qu'elle devra découler du rôle Root en V2.
- Certaines autorisations Media traitent Root différemment selon l'action ; elles devront être uniformisées.
- Une route API de gestion de tokens d'autres utilisateurs combine le rôle Root avec un contrôle explicite `users.view` ; ce contrôle devra être réconcilié avec l'autorité globale Root.
- Les middleware de rôle et de permission devront être testés séparément pour éviter qu'un contrôle de rôle correct soit ensuite annulé par un contrôle de permission incompatible.

### 7.5 Séparation impérative : permissions globales Root et capacités des API tokens

- L'autorité globale du rôle Root dans l'application web/admin ne signifie pas que ses API tokens reçoivent automatiquement toutes les abilities.
- Les abilities des tokens restent limitées aux slugs de permissions MAHLINE existants, actifs et effectivement accordés au propriétaire du token selon la règle de sécurité des API tokens.
- Le wildcard `*` reste interdit.
- La création d'un token reste réservée à son propriétaire ; Root peut gérer les tokens d'autres comptes uniquement via les routes d'administration explicitement autorisées.
- Cette séparation doit être couverte par des tests dédiés en V2.

### 7.6 Stratégie de réalisation et critères d'acceptation V2

- Réaliser d'abord un inventaire exhaustif des routes, middleware, Gates, Policies, services et contrôles de permissions par domaine.
- Cartographier chaque autorisation Root et chaque invariant métier qui doit rester prioritaire.
- Centraliser ensuite l'autorité globale Root sans créer de bypass générique des règles d'intégrité.
- Ajouter des tests prouvant que Root, sans permission individuelle attribuée, accède à toutes les actions actives autorisées dans chaque domaine.
- Ajouter des tests prouvant qu'une nouvelle permission active est automatiquement accessible à Root.
- Ajouter des tests négatifs : compte Root protégé contre son propre lifecycle standard ; audit et mouvements de stock immuables ; facture validée non modifiable ; règles de token API inchangées ; rôles inférieurs toujours limités à leurs permissions et périmètres.
- Exécuter les tests unitaires et fonctionnels de chaque domaine touché, puis la suite complète MAHLINE et Pint.
- Ne déclarer cette évolution terminée qu'après validation des tests requis et mise à jour de la documentation de suivi V2.

### 7.7 Statut

- Release cible : **V2**.
- Release V1 : **aucune activation de l'autorité globale automatique Root au titre de cette évolution** ; conserver les règles actuellement implémentées et les protections existantes.
- Développement : **à planifier — non commencé**.
- Tests V2 : **non exécutés**.
- Fusion : **aucune**.
