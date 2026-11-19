# Audit des dépendances et relations inter-domaines — MAHLINE

- **Date :** 19 novembre 2026
- **Branche :** `feature/Marketplace`
- **HEAD de départ :** `23cc0ebaa4f1099e77282bbdf050ec6448a896ea`
- **HEAD code après intégration des contrats AuditWriter et CheckoutCatalog :** `f8f7535b1ae98879738c6aad0b0eef614b97a29f`
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
- `MediaOwnerObserver` dépend encore des modèles Product/Cooperative/User ; sa dépendance Audit a été remplacée par `AuditWriter`.
- `MediaService` manipule des propriétaires Eloquent de plusieurs domaines.
- **Solution :** contrat de propriétaire média fourni par chaque domaine consommateur ; contrat public `AuditWriter` fourni par Audit ; Media conserve le stockage, les catégories, la validation et l'intégrité. Le point de composition branche les adaptateurs sans imports privés dans Media.

### Orders / Products / Identity / Cooperatives
- `OrderCreationService` chargeait initialement directement Product et ProductVariant ; cette dépendance est désormais remplacée par `CheckoutCatalog` et `CheckoutProductData`.
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
| P0 | Contrat public AuditWriter | `Audit/**`, `Shared/**`, `Media/**`, autres services | **Intégré**
| P1 | Contrat Products checkout/quote pour Orders | `Products/**`, `Orders/Services/OrderCreationService.php` | **Intégré**
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


## 8. Corrections de frontières intégrées — 19 novembre 2026

Référence du code après intégration : `f8f7535b1ae98879738c6aad0b0eef614b97a29f` sur `feature/Marketplace`.

### Audit — contrat public effectif
- Création de `App\\Domains\\Audit\\Contracts\\AuditWriter`, surface publique pour `record(AuditEntryDTO)` et `verifyIntegrity()`.
- `AuditWriterService` adapte ce contrat vers `AuditService` ; le contrat ne retourne pas le modèle AuditEvent. Le repository et la chaîne SHA-256 restent internes à Audit.
- Binding du contrat vers `AuditWriterService` dans le composition root Laravel (`AppServiceProvider`).
- Les services consommateurs de Catalog, Categories, Cooperatives, Identity, Localization, Products et Media injectent désormais `AuditWriter`, pas `AuditService`.
- Media ne référence plus `AuditEventRepository` pour vérifier l'intégrité ; les contrôles passent par le contrat public.
- Le DTO partagé reste une dépendance explicite. Son ownership et son emplacement seront consolidés dans une étape dédiée ; aucune duplication de DTO n'a été introduite.

### Orders / Products — contrat checkout effectif
- Création de `Products\\Contracts\\CheckoutCatalog` et du DTO immuable `Products\\DTOs\\CheckoutProductData`.
- `ProductService` implémente le contrat et conserve la responsabilité de recharger/verrouiller le produit et la variante, de vérifier leur état et de fournir prix, unité et identité de coopérative.
- `OrderCreationService` ne charge plus directement `Product` ni `ProductVariant`. Il consomme les données autoritatives fournies par Products et conserve la transaction de création, les calculs de commande et les snapshots dans Orders.
- Le binding `CheckoutCatalog → ProductService` est déclaré dans le composition root. Les verrous restent dans la transaction SQL ouverte par le cas d'usage Orders ; cela est intentionnel dans le monolithe hybride et doit être vérifié par tests MySQL.

### Contrôles et limites
- Vérification statique des imports et des points d'injection modifiés ; aucune suite PHP/PHPUnit/Pint n'a été exécutée dans cette session.
- Les tests existants de commande utilisent le conteneur Laravel (`app(OrderCreationService::class)`) ; aucune instanciation directe repérée dans le test ciblé.
- Les relations Eloquent, FK, pivots, policies et providers ne sont pas toutes découplées. Le travail restant est décrit ci-dessous.

### Suite à réaliser
1. Définir et intégrer les contrats de propriétaires médias afin que Media ne dépende plus des modèles Product/Cooperative/User.
2. Attribuer et encapsuler les pivots `category_product` et `catalog_product` ; supprimer les imports réciproques Catalog/Categories/Products par lots.
3. Déplacer les policies d'Address et Cooperative vers Localization et Cooperatives, puis enregistrer les policies via providers locaux sans changer les règles d'autorisation.
4. Définir le contrat d'adresse utilisé par Identity/Cooperatives.
5. Finaliser le registre des FK, cascades et triggers à partir des migrations réelles ; ne supprimer aucune FK sans migration additive et tests d'intégrité.
6. Ajouter tests de frontières, tests de contrat et CI progressive.


## 9. Registre relationnel SQL — vérifications ciblées des migrations

Les règles ci-dessous ont été relues dans les migrations existantes. Elles décrivent les contraintes réellement déclarées, pas une cible théorique. Elles ne doivent pas être modifiées sans migration additive, vérification des données et tests sur MySQL.

| Relation | Règle physique observée | Conséquence / traitement |
|---|---|---|
| `addresses.country_id → countries.id` | `RESTRICT ON DELETE`, `CASCADE ON UPDATE` | La suppression d'un pays référencé est bloquée ; propriété désormais interne à Localization. |
| `addresses.user_id → users.id` | `RESTRICT ON DELETE`, `RESTRICT ON UPDATE` | Un compte référencé ne peut pas être physiquement supprimé tant que l'adresse existe. |
| `addresses.cooperative_id → cooperatives.id` | FK ajoutée avec `RESTRICT ON DELETE/UPDATE` | Même protection pour les adresses de coopérative. |
| `categories.cooperative_id → cooperatives.id` | `RESTRICT ON DELETE` | Une coopérative référencée ne peut pas être physiquement supprimée. |
| `categories.parent_id → categories.id` | `RESTRICT ON DELETE` | Une catégorie parent ne peut pas être physiquement supprimée si des enfants la référencent. |
| `products.cooperative_id → cooperatives.id` | `RESTRICT ON DELETE` | Préserve la référence coopérative ; le soft delete reste distinct. |
| `catalogs.cooperative_id → cooperatives.id` | `RESTRICT ON DELETE` | Même protection pour les catalogues. |
| `category_product.category_id/product_id` | `CASCADE ON DELETE` des deux côtés | La suppression physique d'une catégorie ou d'un produit supprime les lignes de pivot ; un soft delete ne déclenche pas cette cascade. |
| `catalog_product.catalog_id/product_id` | `CASCADE ON DELETE` des deux côtés | Même comportement pour les associations de catalogue. |
| `super_admin_cooperative.super_admin_id/cooperative_id` | `CASCADE ON UPDATE`, pas de cascade delete déclarée | Suppression physique protégée par le comportement restrictif par défaut. |
| `orders.customer_id → users.id` | `RESTRICT ON DELETE` | Préserve l'identité du client liée à la commande. |
| `cooperative_orders.order_id/cooperative_id` | `RESTRICT ON DELETE` | Préserve commande et coopérative référencées. |
| `order_items.cooperative_order_id` | `RESTRICT ON DELETE` | Préserve les lignes de commande historiques. |
| `order_items.product_id/product_variant_id` | nullable + `RESTRICT ON DELETE` | La référence peut être nulle à la création, mais une ressource référencée ne peut pas être physiquement supprimée ; le snapshot JSON conserve les données commerciales. |
| `order_change_proposals` → lignes, utilisateurs, produits/variantes | `RESTRICT ON DELETE` | Les propositions et leur contexte sont protégés. |
| `order_status_histories.actor_id → users.id` | `NULL ON DELETE` | L'historique est conservé si l'acteur est physiquement supprimé. `subject_id` est polymorphe et sans FK. |

### Triggers et invariants vérifiés

- `2026_10_27_033514_enforce_cooperative_relation_ownership` : empêche les associations catalogue/produit de coopératives différentes ; autorise une catégorie globale mais interdit une catégorie locale sur un produit d'une autre coopérative ; bloque les changements de propriétaire qui invalideraient les associations existantes.
- `2026_10_04_224228_add_cooperative_owner_to_addresses` : impose exactement un propriétaire (user OU cooperative), et utilise des colonnes générées/index uniques pour les adresses actives et principales.
- `2026_11_17_000001_replace_address_owner_check_with_triggers` : maintient l'invariant du propriétaire exact via triggers MySQL et documente son caractère irréversible.
- Ces triggers ne sont pas des remplacements de FKs : ils protègent des invariants métier complémentaires.

### Ownership des pivots — décision de travail à appliquer dans les lots suivants

- `category_product` : propriétaire fonctionnel proposé = Categories (gestion de l'affectation d'une catégorie à un produit).
- `catalog_product` : propriétaire fonctionnel proposé = Catalog (gestion de l'appartenance à un catalogue).
- Les pivots restent des tables d'intégration ; aucun des deux domaines ne doit être modifié directement par ProductService à terme. ProductService devra appeler des contrats d'association Categories/Catalog, avec validation des droits dans le domaine propriétaire.
- Cette attribution est une décision d'architecture proposée par l'audit, pas encore une migration de propriété ni une refonte des relations Eloquent.

### Limites restantes de l'audit SQL

- Le registre ci-dessus couvre les migrations explicitement relues pour les relations clés ; les autres FKs des migrations Identity, Media et des tables de sécurité doivent encore être ajoutées au registre exhaustif.
- Aucun schéma MySQL réel n'a été interrogé dans cette session ; les règles ont été lues dans les fichiers de migration.
- Aucun trigger n'a été exécuté ni testé dans une base MySQL locale.


### Authorization — policies déplacées vers les domaines propriétaires
- `AddressPolicy` déplacée de Identity vers `Localization/Policies`.
- `CooperativePolicy` déplacée de Identity vers `Cooperatives/Policies`.
- Création de `LocalizationServiceProvider` et `CooperativesServiceProvider`, enregistrés dans `bootstrap/providers.php`.
- Les Gates `updateUserAddress`, `createCooperativeAddress` et `updateCooperativeAddress` restent disponibles avec leurs mêmes noms et délèguent désormais à Localization.
- Le provider Identity ne référence plus les modèles/policies Address et Cooperative.
- Les tests des deux policies ont été déplacés sous les domaines propriétaires et leurs namespaces/imports mis à jour. Tests non exécutés.


### Suite de l'ownership des autorisations — providers locaux
- Les enregistrements de policies Audit, Catalog, Categories, Media et Products ont également été déplacés dans leurs providers de domaine.
- `Identity/Authorization/AuthorizationServiceProvider` ne conserve que l'enregistrement de `UserPolicy` ; les policies de ressources métier sont enregistrées par leur domaine propriétaire.
- Les providers Audit, Catalog, Categories, Cooperatives, Localization, Media et Products sont tous listés dans `bootstrap/providers.php`.
- Les policies et Gates Address/Cooperative conservent leurs règles et noms d'actions ; les tests de ces deux policies ont été déplacés avec les classes.
- À valider localement : ordre de boot Laravel, résolution des policies, tests d'autorisation existants et absence de références résiduelles aux anciens namespaces.
