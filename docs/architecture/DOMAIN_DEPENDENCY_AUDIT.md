# Audit des dépendances et relations inter-domaines — MAHLINE

- **Date :** 19 novembre 2026
- **Branche :** `feature/Marketplace`
- **HEAD de départ :** `23cc0ebaa4f1099e77282bbdf050ec6448a896ea`
- **Dernier HEAD après intégration Localization/Country :** à relever après finalisation de cette révision
- **Portée :** inspection statique des imports, modèles, services, policies, providers, routes et migrations.
- **Limite :** aucune suite PHP/PHPUnit/Pint n'a été exécutée dans cet audit ; l'inventaire statique ne prouve pas le comportement à l'exécution.

## 1. Architecture cible

L'architecture cible est celle de l'ADR-021 : monolithe modulaire hybride, base SQL partagée, contrats publics synchrones, événements après commit, Outbox pour les faits critiques, propriété unique des données et exceptions SQL documentées.

## 2. Dépendances de code confirmées

Les imports statiques recensés lors de l'audit font apparaître les dépendances suivantes. Elles ne sont pas toutes de même nature : certaines sont des relations de lecture ou de composition, d'autres des accès directs à des modèles/services privés.

| Domaine source | Dépendances observées |
|---|---|
| Audit | Identity |
| Catalog | Cooperatives, Products, Media, Audit, Identity |
| Categories | Cooperatives, Products, Identity, Audit |
| Cooperatives | Localization, Identity, Media, Products, Audit |
| Dashboard | Catalog, Categories, Cooperatives, Identity, Products |
| Identity | Audit, Cooperatives, Localization, Catalog, Categories, Products, Media |
| Localization | Cooperatives, Identity, Audit ; Countries a été absorbé |
| Media | Cooperatives, Identity, Audit, Products |
| Orders | Cooperatives, Identity, Products |
| Products | Catalog, Categories, Cooperatives, Media, Audit, Identity |

Les dossiers Countries ont été supprimés de l'arborescence applicative après transfert de Country dans Localization. Les références de modèle/factory/seeder/tests connues ont été mises à jour dans le même lot.

## 3. Relations et couplages concrets vérifiés

### Catalog / Categories / Products
- `Product` possède des relations Eloquent vers `Catalog`, `Category`, `Cooperative` et `Media`.
- `CatalogService` charge directement des modèles `Product` pour les rattachements.
- `CategoryService` manipule directement `Product` et la table `category_product`.
- Les pivots `catalog_product` et `category_product` relient des domaines distincts.
- **Solution :** chaque domaine garde ses tables et modèles ; les commandes de rattachement passent par des contrats d'application du domaine propriétaire du pivot, avec une seule responsabilité de validation. Les imports réciproques de modèles sont remplacés progressivement par DTO/contrats.

### Cooperatives / Identity
- `Cooperative` expose des relations vers `User`, `Product`, `Media` et `SuperAdminCooperative`.
- `User` expose des relations vers `Cooperative`, `Address` et `Media`.
- `UserManagementService` manipule directement les modèles Cooperative et User pour la création et les affectations.
- **Solution :** Cooperatives possède ses données et règles d'organisation ; Identity possède les comptes et rôles. Les affectations sont coordonnées par un contrat explicite d'affectation, et les règles de visibilité/ownership sont évaluées par le domaine propriétaire. Les relations Eloquent croisées ne sont pas supprimées avant définition des cas de lecture nécessaires.

### Localization / Identity / Cooperatives
- `AddressService` manipule directement User et Cooperative, verrouille leurs lignes et appelle des Gates définis dans Identity.
- `AddressPolicy` se trouve dans Identity alors que l'adresse appartient à Localization.
- **Solution :** Localization possède Address et ses règles d'accès métier ; Identity fournit les informations d'acteur/permissions via contrat. Les opérations de cycle de vie du propriétaire notifient Localization via contrat/événement. Country est maintenant dans Localization.

### Media / domaines propriétaires / Audit
- `AppServiceProvider` enregistre `MediaOwnerObserver` sur Product, Cooperative et User.
- `MediaOwnerObserver` dépend des modèles Product/Cooperative/User, du `AuditEventRepository` et de `AuditService`.
- `MediaService` manipule des propriétaires Eloquent de plusieurs domaines.
- **Solution :** contrat de propriétaire média fourni par chaque domaine consommateur ; contrat public `AuditWriter` fourni par Audit ; Media conserve le stockage, les catégories, la validation et l'intégrité. Le point de composition branche les adaptateurs sans imports privés dans Media.

### Orders / Products / Identity / Cooperatives
- `OrderCreationService` charge directement Product et ProductVariant, verrouille les modèles et lit variantes/unités.
- Les modèles Orders référencent User et Cooperative.
- **Solution :** Products expose un contrat de validation de panier/devis qui retourne un DTO immuable (produit, variante, unité, prix, statut et coopérative). Orders possède le snapshot et le cycle de commande. Les IDs de client/coopérative sont conservés comme références logiques sauf exception SQL prouvée.

### Authorization / composition
- `Identity/Authorization/AuthorizationServiceProvider` enregistre les policies Audit, Catalog, Categories, Cooperatives, Media et Products, et contient les Gates d'adresse.
- `AddressPolicy` et `CooperativePolicy` sont dans Identity malgré la propriété métier Localization/Cooperatives.
- `AppServiceProvider` enregistre les observers de plusieurs domaines.
- **Solution :** déplacer l'enregistrement des policies/observers vers les providers propriétaires ; Identity garde les mécanismes génériques d'identité et de permissions. Les providers sont assemblés via `bootstrap/providers.php`.

## 4. Relations SQL inter-domaines repérées

| Tables / relation | Propriétaire cible | Traitement |
|---|---|---|
| addresses → users / cooperatives | Localization pour addresses ; Identity et Cooperatives pour propriétaires | Référence logique cible ; FK conservée temporairement si intégrité actuelle requise |
| addresses → countries | Localization | Relation désormais intra-domaine |
| products → cooperatives | Products / Cooperatives | Référence logique cible, exception physique à justifier |
| catalogs → cooperatives | Catalog / Cooperatives | Référence logique cible |
| categories → cooperatives | Categories / Cooperatives | Référence logique cible |
| category_product | À attribuer explicitement à Categories ou Products | Pivot d'intégration ; pas de propriété double |
| catalog_product | À attribuer explicitement à Catalog ou Products | Pivot d'intégration ; pas de propriété double |
| super_admin_cooperative | Identity + Cooperatives | Pivot d'affectation ; contrat d'affectation requis |
| orders → users | Orders / Identity | Référence logique cible ; snapshots historiques |
| cooperative_orders → cooperatives | Orders / Cooperatives | Référence logique cible |
| order_items → products / product_variants | Orders / Products | Snapshot obligatoire ; FK à évaluer avec politique de rétention |

Les triggers MySQL de cohérence coopérative et de propriété des adresses sont des règles d'intégrité existantes. Ils ne doivent pas être supprimés avant qu'un remplacement applicatif ou une nouvelle migration ait été conçu et testé.

## 5. Matrice de correction priorisée

| Priorité | Correction | Fichiers principaux |
|---|---|---|
| P0 | Finaliser le registre des relations SQL, cascades, triggers et ownership | `database/migrations/**`, modèles concernés |
| P0 | Déplacer policies vers les domaines propriétaires et providers locaux | `Identity/Authorization/**`, `Cooperatives/**`, `Localization/**`, providers |
| P0 | Contrat public AuditWriter | `Audit/**`, `Shared/**`, `Media/**`, autres services |
| P1 | Contrat Products checkout/quote pour Orders | `Products/**`, `Orders/Services/OrderCreationService.php` |
| P1 | Contrats de propriétaires médias | `Media/**`, `Products/**`, `Cooperatives/**`, `Identity/**` |
| P1 | Contrat d'adresses pour Identity/Cooperatives | `Localization/**`, `Identity/UserManagement/**`, `Cooperatives/**` |
| P1 | Définir ownership des pivots Catalog/Categories/Products | migrations pivots, services, modèles |
| P2 | Événements versionnés, Outbox critique, idempotence | `app/Domains/*/Application/Events/**`, migrations nouvelles |
| P2 | Tests de frontières et CI progressive | `tests/Architecture/**`, `.github/workflows/**` |

## 6. Changements déjà intégrés pendant ce lot

- `Country` est désormais sous `App\Domains\Localization\Models\Country`.
- `Address` et `AddressService` référencent le modèle Country de Localization.
- `CountryFactory`, `CountrySeeder` et `AddressFactory` référencent le nouveau namespace.
- Les tests Country ont été déplacés dans `tests/Framework/Domains/Localization/**` ; le test de relation Address/Country référence le nouveau modèle.
- Le modèle autonome `app/Domains/Countries/Models/Country.php` et les deux anciens chemins de tests Countries ont été supprimés.
- Aucune migration existante n'a été modifiée.

## 7. Vérifications restantes

- Rechercher toutes les anciennes références `App\Domains\Countries` dans les 460 fichiers PHP suivis, y compris tests et fixtures.
- Vérifier l'autoload PSR-4 et la découverte des tests.
- Exécuter les tests ciblés Country/Address, puis toute la suite et Pint.
- Auditer toutes les clauses `ON DELETE` / `ON UPDATE`, les triggers, les pivots et les accès SQL hors domaine propriétaire.
- Vérifier les dispatchs d'événements, jobs, listeners, notifications et transactions réelles.
- Aucune de ces vérifications d'exécution n'est déclarée verte à ce stade.
