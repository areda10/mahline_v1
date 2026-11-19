# Audit des dépendances et relations inter-domaines — MAHLINE

- **Date :** 19 novembre 2026
- **Branche :** `feature/Marketplace`
- **HEAD de départ :** `23cc0ebaa4f1099e77282bbdf050ec6448a896ea`
- **HEAD code après intégration des contrats AuditWriter et CheckoutCatalog :** `3d1c91db28362a38919d7fa1e5084dc862001d69`
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

Référence du code après intégration : `3d1c91db28362a38919d7fa1e5084dc862001d69` sur `feature/Marketplace`.

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


### Cooperatives / Localization — contrat d'adresse intégré
- Création de `Localization\\Contracts\\CooperativeAddressManager`.
- `AddressService` implémente le contrat avec des méthodes d'entrée par identifiant de coopérative.
- `CooperativeService` dépend désormais du contrat public, plus directement de `AddressService`.
- Le binding est réalisé dans `AppServiceProvider`. Les opérations restent dans la même transaction SQL du cas d'usage Cooperatives ; la création, suppression logique et restauration d'adresse conservent leurs comportements.
- Localization reste propriétaire des adresses et de leur cycle de vie ; Cooperatives orchestre son propre cycle de vie sans appeler un service concret d'un autre domaine.
- La validation locale devra couvrir création coopérative + adresse, suppression/restauration et toutes les règles de permissions.


### Référence finale du lot intégré
- HEAD du code après contrats AuditWriter, CheckoutCatalog, CooperativeAddressManager et providers propriétaires : `3d1c91db28362a38919d7fa1e5084dc862001d69`.
- `CooperativeService` n'injecte plus `AddressService` directement ; il consomme `CooperativeAddressManager`.
- Le scan de l'arborescence confirme que les anciens fichiers AddressPolicy/CooperativePolicy sous Identity et les anciens dossiers Countries n'existent plus.

## 10. Ré-audit global de l'arborescence et des points d'entrée — 19 novembre 2026

- **HEAD contrôlé :** `ad3de3395a794e965973493716fbae1e0ca358bb`
- **Méthode :** arbre Git récursif complet, inventaire des fichiers par domaine, lecture des migrations, routes, providers, contrats et services d'orchestration.
- **Limite :** audit statique du dépôt Git ; aucune connexion à la base MySQL, aucun test PHP/PHPUnit/Pint et aucune exécution des triggers dans cette passe.

### 10.1 Inventaire réel du code

| Domaine | Fichiers sous app/Domains | État structurel observé |
|---|---:|---|
| Identity | 99 | Domaine le plus développé ; plusieurs sous-domaines (Authentication, Customer, Password, UserManagement, Users, SuperAdminRoot, etc.). Audit fonctionnel et architectural encore ouvert. |
| Media | 21 | Services d'upload, validation, traitement, incidents, nettoyage et policies présents ; contrats de propriétaires manquants. |
| Orders | 13 | Modèles, enums et deux services de création/réponse présents ; aucun contrôleur HTTP Orders dans le domaine et aucun provider Orders dans bootstrap/providers.php. |
| Products | 12 | Produits, variantes, unités, service et contrat checkout présents ; dépendances de catalogue/catégories et Media à découpler. |
| Audit | 11 | Journal, repository, service, writer public et policy présents. |
| Catalog | 8 | Modèles, services, contrôleurs et policy présents ; PDF et pivots à contrôler. |
| Cooperatives | 7 | Modèles, service, policy et contrôleur présents ; contrat d'affectation inter-domaines à finaliser. |
| Localization | 7 | Address, Country, service, policy et contrat d'adresse coopérative présents. |
| Categories | 6 | Modèle, service, contrôleur et policy présents ; pivot à encapsuler. |
| Dashboard | 2 | Deux contrôleurs seulement ; pas de couche de projection/lecture autonome constatée. |

Les dossiers Countries ont disparu ; Country appartient à Localization. Aucun code applicatif Inventory, Delivery, Finance, Communication, Statistics, CMS ou Sales n'existe dans `app/Domains` au HEAD contrôlé.

### 10.2 Points d'entrée et composition

- Les routes web déclarent des points d'entrée Identity, Cooperatives, Catalog, Categories, Products, Media et Dashboard. Aucun contrôleur de commande Orders n'est importé dans `routes/web.php`.
- Les routes API exposent Identity et Audit ; aucune API métier Orders/Inventory/Delivery/Finance n'est actuellement déclarée.
- `Orders` possède des services et modèles, mais n'est pas encore un parcours HTTP complet.
- `bootstrap/providers.php` ne déclare aucun Orders provider ; aucun provider n'existe pour les domaines manquants.
- `AppServiceProvider` lie déjà AuditWriter, CheckoutCatalog, CooperativeAddressManager et SuperAdminCooperativeAssignmentService. Il enregistre aussi encore MediaOwnerObserver globalement sur Product, Cooperative et User : ce point reste un couplage à retirer après mise en place des contrats et adaptateurs propriétaires.
- Aucun fichier de workflow GitHub Actions ni test d'architecture/boundary n'a été trouvé dans l'arbre inspecté.

### 10.3 Migrations et propriété des données

Les migrations applicatives historiques sont toutes regroupées sous `database/migrations/` au HEAD contrôlé. Elles couvrent Identity, Localization/Countries, Cooperatives, Catalog/Categories/Products, Audit, Media et Orders. Les cinq migrations les plus récentes introduisent notamment les tables Orders, les prix et les unités Products.

Constats :
- Aucune migration Inventory, Delivery, Finance, Communication, Statistics ou CMS n'existe.
- Le placement historique des migrations ne respecte pas encore la cible ADR-021 qui prévoit des migrations métier nouvelles dans le domaine propriétaire. Ne pas déplacer ni réécrire les migrations existantes ; appliquer la convention de domaine aux migrations nouvelles, après vérification de la découverte Laravel.
- Les tables `category_product`, `catalog_product` et `super_admin_cooperative` restent des pivots d'intégration dont les contrats/ownership applicatifs doivent être explicités.
- Les FK et triggers recensés en section 9 sont uniquement ceux relus ; le registre complet des FKs Identity, Media, sessions, sécurité, notifications et toutes les contraintes de suppression reste à compléter.
- La table Laravel `notifications` existe déjà, mais cela ne constitue pas un domaine Communication : propriétaire des notifications métier, préférences, modèles, canaux et journal des envois restent à décider.

### 10.4 Dépendances directes encore visibles après les premiers contrats

| Intégration | État constaté | Travail restant |
|---|---|---|
| Audit → consommateurs | `AuditWriter` est public et utilisé par les services inspectés | Finaliser ownership du DTO, vérifier couverture et atomicité dans chaque service. |
| Products → Orders | `CheckoutCatalog` / `CheckoutProductData` sont intégrés dans `OrderCreationService` | Tests MySQL de transaction/lock ; contrats de disponibilité et changements de prix à stabiliser. |
| Localization → Cooperatives | `CooperativeAddressManager` est consommé par `CooperativeService` | Vérifier toutes les opérations et les imports directs restants dans AddressService/Policies. |
| Identity ↔ Cooperatives | `SuperAdminCooperativeAssignmentService` est déclaré dans Identity et lié dans le composition root | Définir la frontière d'affectation et les opérations de lecture coopérative sans faire importer à Cooperatives les modèles privés Identity. |
| Media ↔ propriétaires | Media importe encore Product, Cooperative et User dans catégorie, service, observer et policy | Contrats publics de propriétaires, contexte d'acteur et adaptateurs dans les domaines fournisseurs. |
| Catalog / Categories / Products | Relations Eloquent et services se réfèrent encore directement aux modèles/pivots des domaines voisins | Encapsuler `category_product` dans Categories et `catalog_product` dans Catalog ; exposer des contrats/DTO à Products. |
| Orders ↔ Identity / Cooperatives | Les modèles Orders référencent directement User/Cooperative et les services typent l'acteur User | Contrat d'identité/acteur et références logiques de propriétaire ; conserver snapshots et invariants historiques. |
| Dashboard ↔ domaines métier | Les contrôleurs consomment directement plusieurs domaines | Définir des contrats de lecture/projections, appliquer les filtres coopératifs à la source et éviter toute écriture métier. |
| Localization ↔ Identity / Cooperatives | AddressService et les relations d'adresse gardent des références aux propriétaires | Contrats d'identité et de cycle de vie du propriétaire ; garder l'invariant XOR user/cooperative. |

### 10.5 Domaines absents et arbitrages de périmètre

| Domaine cible | Statut | Décision / condition préalable |
|---|---|---|
| Inventory | Absent du code ; spécification V1 INV-001 à INV-016 existante | Audit global effectué ; audit technique détaillé des modèles, unités, migrations, commandes, transactions, autorisations et événements à terminer avant schéma/code. |
| Delivery | Absent | Règles connues : offres transporteurs, acceptation 12 h, première acceptation exclusive, attribution automatique au tarif le moins cher, collecte/livraison et transferts. Départage en cas d'égalité et plusieurs cas d'échec restent ouverts. |
| Finance | Absent | Propriétaire cible des contrats financiers, encaissements reçus, commissions, transferts et reversements. Paramètres contractuels, remboursements, litiges et rapprochements restent incomplets. |
| Facturation | Absente comme domaine autonome | Doit rester dans Finance ; pas de domaine Billing/Invoicing séparé sans ADR explicite. Facture unique par commande validée après réservation, immuable, numérotation par coopérative et année, PDF conservé et envoyé. |
| Communication | Absent | Notification in-app et email validées pour événements de commande ; SMS hors V1. Le domaine doit posséder modèles, préférences éventuelles, orchestration d'envoi et journal de livraison, sans prendre possession des événements sources. |
| Statistics | Absent | Autonomie du domaine validée ; définir indicateurs, projections, fraîcheur, fuseau, devise et contrats de lecture. |
| CMS | Absent | Domaine prévu dans la roadmap mais aucune spécification fonctionnelle V1 détaillée n'a été trouvée ; ne pas inventer pages, workflow ou permissions. |
| Sales | Absent du code ; spécification fonctionnelle SALES V1 validée (SALES-001 à SALES-037) | Périmètre désormais confirmé : parcours commercial pré-commande, paniers invité/authentifié, fusion, validation commerciale et orchestration du checkout. Orders reste propriétaire des commandes ; Sales n'écrit pas dans ses tables. Créer le domaine Sales après stabilisation des contrats Products, Cooperatives, Localization et Identity ; le checkout complet dépend aussi des contrats publics Orders, Inventory et Delivery. |
| Incidents | Aucun domaine autonome | Les incidents Media sont déjà possédés par Media. Aucun périmètre général d'incidents plateforme n'est spécifié ; ne pas créer un domaine Incidents sur la seule base des anciennes roadmaps. |

### 10.6 Séquence autorisée après l'audit global

1. Terminer les corrections de frontières sûres et caractérisées dans les domaines présents : Media, Catalog/Categories/Products, Identity/Cooperatives, Localization, Orders et Dashboard.
2. Effectuer maintenant l'audit technique détaillé Inventory : migrations et invariants Products, contrats de confirmation Orders, verrouillage transactionnel, mouvements, réservation, événements, permissions et scénarios d'échec.
3. Implémenter Inventory par lots verticaux après validation du schéma et des contrats ; ne jamais réserver au checkout ni pendant le délai de réponse de 12 heures.
4. Compléter le parcours HTTP et les événements Orders, en gardant Orders propriétaire des statuts commerciaux et snapshots.
5. Implémenter Delivery et Finance uniquement après fermeture des points fonctionnels ouverts qui affectent les transitions, calculs ou rapprochements.
6. Créer Communication et Statistics avec contrats/événements sources définis ; cadrer CMS séparément.
7. Résoudre formellement Sales et le périmètre Incidents avant toute création de code.

### 10.7 Conditions de clôture du présent audit

- Inventaire structurel complet des domaines et points d'entrée : réalisé au HEAD indiqué.
- Cartographie des principaux couplages et pivots : réalisée statiquement, mais certains appels internes et FK restent à recenser exhaustivement.
- Vérification réelle MySQL des FKs, contraintes et triggers : à exécuter.
- Tests de frontières automatisés : absents.
- Audit détaillé Inventory : prochaine étape, non réalisé par ce rapport.
- Tests PHPUnit/Pint : non exécutés ; aucun domaine n'est déclaré globalement validé.



### 10.8 Mise à jour — périmètre Sales confirmé (19 novembre 2026)

Les décisions SALES-001 à SALES-037 ont été validées par l'utilisateur. Le doute historique sur l'existence d'un domaine Sales distinct est donc levé : Sales est le propriétaire du parcours commercial pré-commande, des paniers, de la fusion, des validations de panier et de l'orchestration checkout. Orders reste propriétaire des commandes, sous-commandes, statuts et snapshots après création ; Inventory reste propriétaire de la disponibilité/réservation ; Delivery reste propriétaire des tarifs et opérations de livraison ; Finance reste propriétaire des factures et opérations financières.

Le code Sales n'est pas encore créé. Les dépendances à traiter en priorité sont consignées dans le rapport dédié `docs/domains/marketplace/SALES_DEPENDENCY_AUDIT.md`. Cette mise à jour remplace la conclusion de la ligne Sales de la section 10.5, sans modifier les autres conclusions de l'audit global.

Limites : cet audit de dépendances est statique. Les suites PHP/PHPUnit/Pint, les contrôles MySQL et les tests de contrats n'ont pas été exécutés dans cette session.


## 11. Ré-audit transversal actualisé — HEAD `befe78ab89cf7b2ab8dc4433c5a4c1ee6ff46930`

- **Date :** 19 novembre 2026
- **Branche :** `feature/Marketplace`
- **Méthode :** arbre Git récursif complet, inventaire de tous les fichiers sous `app/Domains`, lecture ciblée des contrats, services, modèles, providers, routes, tests et migrations concernés.
- **Limites :** audit statique GitHub. Aucun accès à la base locale, aucune exécution PHP/PHPUnit/Pint, aucune vérification runtime des transactions, providers ou triggers. Les éléments non inspectés ne sont pas déclarés conformes.

### 11.1 Photographie exacte de l'arborescence

| Domaine présent | Fichiers sous `app/Domains/<domaine>` |
|---|---:|
| Identity | 99 |
| Media | 21 |
| Orders | 13 |
| Products | 12 |
| Audit | 11 |
| Catalog | 8 |
| Cooperatives | 7 |
| Localization | 7 |
| Categories | 6 |
| Dashboard | 2 |
| **Total** | **186** |

Domaines prévus mais absents de `app/Domains` : Sales, Inventory, Delivery, Finance/Facturation, Communication, Statistics et CMS. La présence d'une spécification ne vaut pas implémentation.

Le dépôt contient des tests Feature et Framework pour les domaines existants, mais leur présence ne prouve ni la couverture exhaustive des règles ni leur réussite sur ce HEAD. Aucun répertoire `tests/Architecture` n'a été trouvé dans l'arbre recensé.

### 11.2 Défauts et blocages P0 confirmés par lecture du code

| ID | Domaine | Constat actuel | Conséquence / correction obligatoire |
|---|---|---|---|
| XDOM-001 | Products | `ProductService` importe directement `Catalog`, `Categories` et `Identity\Users\Models\User`. Il lit et synchronise directement les pivots `catalog_product` et `category_product`. | Introduire les contrats propriétaires pour les associations Catalog/Categories ; remplacer le modèle User par un contexte d'acteur/ID logique, sans perdre l'autorisation ni l'audit. |
| XDOM-002 | Localization | `AddressService` importe et verrouille directement les modèles User et Cooperative ; ses opérations publiques sont centrées sur des modèles Eloquent. | Garder Address et ses règles dans Localization, fournir un contrat public d'adresse client avec vérification d'ownership et DTO snapshot ; découpler l'acteur et le cycle de vie des propriétaires. |
| XDOM-003 | Cooperatives | `CooperativeService` reçoit directement le modèle User. Le domaine ne fournit pas encore de contrat public de lecture du statut d'une coopérative pour les consommateurs métier. | Ajouter un contrat de lecture par identifiant et un DTO de statut ; garder les règles d'organisation dans Cooperatives. |
| XDOM-004 | Orders | `OrderCreationService::createFromCheckout(User, array): Order` expose User et un tableau libre à la frontière ; aucune clé d'idempotence n'est présente dans l'entrée observée. | Créer un contrat public Orders versionné, DTO d'entrée/sortie, ID client logique et clé d'idempotence ; couvrir retries, doublons, concurrence et rollback. |
| XDOM-005 | Orders / Inventory | `CooperativeOrderResponseService` confirme une sous-commande et change les statuts Orders, mais aucun appel de réservation Inventory n'existe. | Bloquer la confirmation de production tant que la réservation atomique Inventory n'est pas intégrée. La règle Inventory validée exige la réservation complète à la confirmation, sans réservation partielle. |
| XDOM-006 | Orders / Delivery | La création initialise `delivery_total` et `delivery_fee` à `0.00`. Aucun contrat Delivery n'existe dans le code. | Ne jamais présenter ce montant comme frais de livraison réels ; intégrer un devis Delivery avec validité et périmètre avant total final. |
| XDOM-007 | Media / composition | `AppServiceProvider` attache `MediaOwnerObserver` directement à Product, Cooperative et User. | Déplacer la composition vers des adaptateurs/contrats de propriétaires enregistrés par les domaines, sans casser les événements de suppression/restauration. |
| XDOM-008 | Orders / exposition | Orders possède modèles et services mais pas de provider de domaine ni de contrôleurs/routes propres identifiés dans l'arbre. | Définir la surface applicative et HTTP d'Orders, ses Policies et son provider avant de considérer le domaine utilisable/complet. |

### 11.3 État des providers

`bootstrap/providers.php` enregistre DatabaseMacro, Audit, Catalog, Categories, Media, Products, Cooperatives, Localization et les providers Authorization d'Identity. Il n'enregistre pas de provider Orders ou Dashboard. Aucun provider n'existe encore pour les domaines absents.

`AppServiceProvider` ne contient plus les bindings AuditWriter, CheckoutCatalog et CooperativeAddressManager, mais conserve :
- le binding `SuperAdminCooperativeAssignmentService` vers `UserManagementService` ;
- le binding `AuthenticationWorkflowHook` ;
- l'enregistrement transversal de `MediaOwnerObserver` sur Product, Cooperative et User ;
- le rate limiter Media public et la configuration Sanctum.

Les deux bindings Identity doivent être classés explicitement comme services de composition Identity ou déplacés vers un provider Identity si la résolution et l'ordre de boot le permettent. Le rate limiter peut rester transversal s'il est documenté comme configuration HTTP globale.

### 11.4 Contrats publics actuellement présents et manquants

Présents et confirmés :
- Audit : `AuditWriter`.
- Products : `CheckoutCatalog` / `CheckoutProductData`.
- Localization : `CooperativeAddressManager`.

À créer ou compléter avant clôture :
- Products : lecteur d'état commercial complet pour panier, avec produit/variante absents, supprimés, désactivés, prix/configuration modifiés et fingerprint/version stable.
- Catalog et Categories : contrats d'association dont chacun possède son pivot.
- Cooperatives : lecteur de statut public par ID.
- Localization : lecteur autorisé d'adresse client et snapshot immuable.
- Identity : contexte d'acteur minimal, sans modèle User consommé par les domaines métier.
- Orders : création depuis DTO versionné, idempotence, résultat explicite et intégration Inventory.
- Media : contrat de résolution/autorisation des propriétaires, sans importer les modèles Eloquent des domaines consommateurs.
- Inventory, Delivery, Finance et Communication : contrats inter-domaines définis dans leurs spécifications avant implémentation.

### 11.5 Ordre de correction et clôture

1. **Frontières fondatrices :** Identity / Localization / Cooperatives (acteur, adresse, statut et cycle de vie).
2. **Catalogue :** Catalog / Categories / Products (ownership des pivots, lecture commerciale, variantes, unités et états).
3. **Transversal :** Media / Audit (propriétaires médias, accès, intégrité, événements et rétention).
4. **Commerce :** Orders / Inventory (contrat idempotent, confirmation et réservation atomiques).
5. **Exécution logistique et financière :** Delivery / Finance / Communication.
6. **Projection et contenu :** Statistics / Dashboard / CMS.
7. **Sales :** implémenter le panier et le checkout seulement lorsque les contrats Products, Cooperatives, Localization, Identity, Orders, Inventory et Delivery sont stables.

### 11.6 Critère de clôture obligatoire

Un domaine ne peut passer à `FROZEN` que si toutes les conditions suivantes sont prouvées sur le même HEAD :
1. spécification V1 complète et décisions ouvertes identifiées ;
2. ownership des modèles, tables, pivots, migrations et contraintes SQL attribué sans ambiguïté ;
3. contrats publics et dépendances entrantes/sortantes documentés, sans import privé non autorisé ;
4. services, transactions, verrouillages, idempotence, événements et erreurs métier implémentés ;
5. routes, contrôleurs, Policies, permissions et scopes coopératifs couverts lorsqu'applicables ;
6. tests Unit, Feature/HTTP, contrats, intégration, sécurité, concurrence et migrations adaptés au domaine ;
7. tests ciblés, tests de frontières, Pint et suite complète exécutés avec résultats verts ; tests MySQL réels pour triggers, verrous et contraintes ;
8. audit final des fichiers et du diff, documentation et roadmap mises à jour avec SHA/commit de preuve.

Un domaine absent du code reste `À implémenter`, pas `FROZEN`. Un domaine partiellement présent reste `Écarts à corriger` ou `À tester`. Aucun résultat de test non exécuté n'est réputé vert.


### 11.7 Lot A1 — Cooperatives : politique de suppression corrigée (19 novembre 2026)

**Fichiers audités :**
- `app/Domains/Cooperatives/Policies/CooperativePolicy.php`
- `app/Domains/Cooperatives/Services/CooperativeService.php`
- `app/Domains/Cooperatives/Models/Cooperative.php`
- `app/Domains/Cooperatives/Http/Controllers/CooperativeController.php`
- `app/Domains/Cooperatives/Providers/CooperativesServiceProvider.php`
- `tests/Framework/Domains/Cooperatives/Policies/CooperativePolicyTest.php`

**Écart corrigé :** la méthode `delete` refusait auparavant toute suppression par un `super_admin`, même affecté à la coopérative. Elle autorise maintenant `super_admin_root` globalement et `super_admin` uniquement si la coopérative lui est affectée, avec la permission `cooperatives.delete`. Les rôles `cooperative_admin` et `user` restent exclus.

**Test ajouté/modifié :** un `super_admin` peut supprimer une coopérative affectée et ne peut pas supprimer une coopérative non affectée.

**Écarts encore ouverts dans Cooperatives :**
- `CooperativeService` dépend directement du modèle Eloquent Identity `User` pour l'acteur et les affectations.
- `CooperativeController` dépend directement de `Localization\\Services\\AddressService` pour modifier l'adresse, alors qu'il doit dépendre d'un contrat public Localization ; le contrat actuel `CooperativeAddressManager` ne couvre pas encore l'opération de mise à jour.
- `Cooperative` expose des relations Eloquent directes vers Identity, Localization, Media et Products ; à classifier et remplacer/encapsuler selon les contrats de lecture et de composition retenus.
- Aucun contrat public `CooperativeStatusReader`/DTO de statut n'est encore présent pour les consommateurs métier.
- Les règles de restauration restent root-only dans le service ; à couvrir explicitement par tests et à confirmer contre la spécification V1 consolidée.

**Commits du lot :**
- Policy : `048f47736203c2b1c5d08208bd68b1fc195c411c`
- Test : `6cc88ab802b18c947668ca4f9149710f040526ae`

**Validation :** modifications poussées sur `feature/Marketplace`. PHPUnit, Pint et tests MySQL non exécutés ici. Le lot A1 n'est donc pas déclaré validé par exécution.


### 11.8 Lot A2 — Identity : suppression de l'activation par mot de passe partagé (19 novembre 2026)

**Fichiers audités :**
- `app/Domains/Identity/UserManagement/Services/UserManagementService.php`
- `app/Domains/Identity/SuperAdminRoot/Http/Controllers/UserManagementController.php`
- `routes/web.php`
- `resources/views/super-admin-root/users/show.blade.php`
- `tests/Framework/Domains/Identity/UserManagement/Services/UserManagementServiceTest.php`
- `app/Domains/Identity/Authentication/Services/AuthenticationService.php`
- `app/Domains/Identity/Authentication/Services/SessionService.php`
- `app/Domains/Identity/Authentication/Services/ApiTokenService.php`
- `app/Domains/Identity/Authentication/Http/Middleware/EnsureActiveAuthenticationSession.php`
- `app/Domains/Identity/Authorization/Policies/UserPolicy.php`

**Écart P0 corrigé :** l'action root `activate-and-verify` validait directement l'adresse e-mail et attribuait le mot de passe partagé `MahlineMahline`, lequel était ensuite affiché dans l'interface. Cette voie contournait la vérification par invitation et exposait un secret commun. La route, l'action du contrôleur, la méthode de service et le formulaire correspondant ont été supprimés. Le test de service vérifie maintenant que l'activation root conserve l'adresse non vérifiée et le mot de passe existant ; le parcours restant envoie une invitation de vérification.

La dépendance `PasswordHistoryService` devenue inutilisée dans `UserManagementService` a également été retirée.

**Écarts Identity encore ouverts :**
- `User` expose des relations Eloquent directes vers Cooperatives, Localization et Media ; plusieurs services Identity importent également Cooperative ou AddressService. La frontière de domaine n'est pas encore propre.
- Les bindings `SuperAdminCooperativeAssignmentService` et `AuthenticationWorkflowHook` sont encore enregistrés dans `AppServiceProvider` au lieu d'un provider de composition Identity clairement identifié.
- La gestion de token reste centrée sur le modèle Eloquent User à travers son service ; vérifier toutes les routes/middlewares API pour prouver que les abilities Sanctum ne remplacent jamais les Policies/Gates et que l'expiration 90 jours est appliquée à l'authentification.
- Les sessions multiples sont bien explicitées dans le service et couvertes par des tests existants ; vérifier l'ensemble des parcours de révocation, les courses login/révocation et les tests MySQL.
- Vérifier les flows d'invitation, reset 30 minutes, changement d'e-mail, verrouillage d'urgence, historique de mots de passe et absence de secrets dans les logs/audits.

**Commits du lot :**
- Service : `acc951883cd65f5d9f79f3997668ae88e34397d1`
- Contrôleur : `1b6041b9616c6747db632a89ac3e43c7a4ffc313`
- Route : `9730d4c0ee4463f18cafb2fe389051766f94abbe`
- Vue : `2d2f761243b55789c03f5fdf4fc250245e9302ba`
- Test : `cfc950e5c8f2ba3509a13b2dd2e7b809ad93aae5`
- Nettoyage dépendance : `4789744e9ef9b8663202e1ce776a50da982a2c76`

**Validation :** code et test modifiés sur `feature/Marketplace`. PHPUnit, Pint, tests de routes et parcours invitation non exécutés ici. Le lot A2 reste en attente de validation runtime.


### 11.9 Lot A3 — Localization : contrats d'intégration pour le cycle de vie des adresses (19 novembre 2026)

**Fichiers audités :**
- `app/Domains/Localization/Services/AddressService.php`
- `app/Domains/Localization/Models/Address.php`
- `app/Domains/Localization/Models/Country.php`
- `app/Domains/Localization/Policies/AddressPolicy.php`
- `app/Domains/Localization/Contracts/CooperativeAddressManager.php`
- `app/Domains/Localization/Providers/LocalizationServiceProvider.php`
- `app/Domains/Identity/UserManagement/Services/UserManagementService.php`
- `app/Domains/Identity/Users/Http/Controllers/PersonalAddressController.php`
- `app/Domains/Cooperatives/Http/Controllers/CooperativeController.php`
- migrations d'ownership d'adresses et tests de relations/intégrité.

**Corrections du lot :**
- Ajout de `UserAddressLifecycleManager`, contrat public à identifiants, lié à `AddressService` par Localization.
- `UserManagementService` ne dépend plus directement de `AddressService` pour supprimer/restaurer les adresses lors du cycle de vie d'un compte.
- `CooperativeAddressManager` expose maintenant une opération de mise à jour d'adresse par identifiant de coopérative.
- `CooperativeController` dépend du contrat `CooperativeAddressManager`, et non plus de la classe concrète `AddressService`.
- Le provider Localization lie les deux contrats à `AddressService`.
- Test ajouté : suppression logique puis restauration d'une adresse utilisateur via le contrat public.

**Invariants confirmés par lecture du code/migrations :**
- Un enregistrement Address doit appartenir exactement à un User ou à une Cooperative.
- Une coopérative possède au plus une adresse active et une seule adresse principale.
- Un utilisateur possède au plus une adresse principale active ; les limites métier (5 clients, 1 compte interne) sont contrôlées dans le service.
- Le pays est imposé au Maroc (`MA`) pour la V1.
- Les adresses supprimées volontairement sont en soft delete ; les suppressions liées au propriétaire sont groupées par batch pour restauration.
- Les contraintes d'ownership, les colonnes générées et les index uniques de l'adresse sont spécifiques à MySQL dans les migrations actuelles ; ils exigent des tests réels sur MySQL.

**Écarts Localization encore ouverts :**
- `PersonalAddressController` dépend toujours directement de `AddressService`.
- `AddressService`, `Address` et `AddressPolicy` importent directement les modèles Eloquent User/Cooperative ; la séparation de l'autorisation et du verrouillage des propriétaires nécessite des contrats fournisseurs et DTO.
- `CooperativeAddressManager` expose encore le modèle Eloquent User comme acteur.
- Aucun contrat public de lecture d'adresse client et de snapshot immuable pour Orders/Sales n'est présent.
- Vérifier les courses concurrence sur les limites d'adresses, restauration/suppression, l'unicité des principales et l'historique d'adresse des commandes.
- Le sous-module Countries est bien situé dans Localization ; aucun domaine Countries autonome ne doit être créé.

**Commits du lot :**
- Contrat UserAddressLifecycleManager : `f5caab7ffbd6352744454443257f3a184fdeab58`
- Extension CooperativeAddressManager : `40a28946027d77babb4e433f25fdee8f3f49a9cf`
- Implémentation AddressService : `f4776b66673d86426c52425e167f38b5914ff6cc`
- Bindings Localization : `da11f75bb4315f0951e5a80b7e6a24cab3e27af7`
- UserManagementService : `2377fcd4d942725d89cdc80391d2c5fe4ca7fe48`
- CooperativeController : `73b41c2cdc01be34a2dcf8332d1557dd732c007c`
- Test contrat : `3b769200afd2c8b96f18d4c98b50c04f3fb4fe04`

**Validation :** fichiers et test ajoutés/modifiés sur `feature/Marketplace`. PHPUnit, Pint et scénarios MySQL non exécutés ici. Le lot A3 reste en attente de validation runtime.


#### Complément de vérification A2 — références de tests nettoyées

Après suppression de l'ancienne méthode, deux références de tests ont également été corrigées :
- `tests/Framework/Domains/Identity/UserManagement/Services/UserLifecycleServiceTest.php` : le contrôle d'immutabilité root ne tente plus d'appeler la méthode supprimée.
- `tests/Feature/Domains/Identity/SuperAdminRoot/Http/Controllers/UserManagementControllerTest.php` : le test de l'ancienne voie est remplacé par une assertion que la route `root.backoffice.users.activate-and-verify` n'est plus enregistrée ; le test d'activation d'un compte archivé vérifie désormais uniquement la voie d'invitation conservée.

Commits complémentaires : `5a879c85b604afac68c22c82d15f2e92dcd6a76a`, `5cbf41359197e547e6094fa17977c4e45c513e79`.

Le contrôle statique ciblé des fichiers de route et de vue ne retrouve plus l'ancien nom de route ni le secret partagé. Cela ne remplace pas l'exécution de la suite Feature/Framework.

#### Complément A3 — carnet d'adresse personnel

- `PersonalAddressController` ne dépend plus directement de `AddressService` ; il utilise `UserAddressLifecycleManager`.
- Ajout de `savePrimaryAddressForUser` au contrat et à son implémentation. La recherche/création/mise à jour de l'adresse principale est maintenant réalisée sous verrou du propriétaire dans une transaction, au lieu de décider dans le contrôleur via une requête non verrouillée.
- Test ajouté : création puis mise à jour via le contrat, avec vérification qu'une seule adresse est conservée.
- Commits complémentaires : `bf01e8496a7420a07696a9d0a21bde0afa4929c8`, `4266eda1b56a0cc3339368ed1d9ee1526baf350a`, `9b328e793ba8eef894e48a7e6c98f1dd7dc9319a`, `6d8c7edec8e184936780ee6fdb20bb59d4304463`, `a18166ce5f207f6c6f0c1efe9552217e287e3330`.
- PHPUnit/Pint/MySQL non exécutés ; lot A3 toujours en attente de validation runtime.
