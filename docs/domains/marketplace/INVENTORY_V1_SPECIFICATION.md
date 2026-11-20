# Inventory V1 — Spécification fonctionnelle et suivi d'implémentation

- **Domaine :** Inventory
- **Version :** 1.0 — cadrage initial
- **Date :** 19 novembre 2026
- **Branche de référence :** `feature/Marketplace`
- **Commit de référence :** `72e27c4b32aefd09c2118e631f2aec0433627e84`
- **Statut :** CADRAGE MÉTIER INITIAL VALIDÉ — audit technique complémentaire et conception du schéma à poursuivre ; aucun code Inventory implémenté à cette référence.

> Ce document est le registre opérationnel du domaine Inventory. Il distingue les règles métier validées, les contrats inter-domaines, les fonctions à implémenter, les contrôles d'audit et les éléments restant à décider. Une fonction ne sera déclarée réalisée qu'après vérification du code et exécution des tests requis.

## 1. Objectif et frontières

Inventory est l'unique propriétaire du stock physique, des mouvements, des quantités réservées, des quantités en quarantaine et de la disponibilité nette.

Inventory ne possède pas :
- les produits, variantes, unités commerciales et prix (Products) ;
- les commandes, sous-commandes, lignes et transitions commerciales (Orders) ;
- les fichiers et pièces jointes (Media) ;
- les affectations et identités des utilisateurs (Identity / Cooperatives) ;
- les journaux transversaux d'audit (Audit).

### Contrat de disponibilité

`quantity_available = quantity_on_hand - quantity_reserved - quantity_quarantined`

La disponibilité ne peut jamais être négative. La quantité physique ne doit pas être confondue avec la quantité disponible.

## 2. Règles métier validées

### 2.1 Périmètre du stock

- Une coopérative possède un stock logique unique en V1 ; les entrepôts et emplacements physiques sont reportés à V2.
- Pour un produit simple, le stock est porté par le produit parent.
- Pour un produit à variantes, le stock est porté individuellement par chaque variante.
- Le produit parent ne peut pas porter simultanément un stock actif avec des stocks de variantes.
- La précision des quantités est `DECIMAL(15,3)`.
- Unités admises : `kg`, `g`, `l`, `ml`, `u`. Les codes canoniques doivent être alignés sur Products.
- Aucune unité libre et aucune conversion automatique entre unités.
- Aucun stock négatif.

### 2.2 Initialisation et mouvements

- Toute initialisation crée un mouvement immuable `opening_balance`.
- L'initialisation exige un responsable, une date, une quantité strictement positive et un motif obligatoire.
- Types de mouvements V1 : `opening_balance`, `purchase_receipt`, `sale`, `customer_return`, `supplier_return`, `manual_adjustment_in`, `manual_adjustment_out`, `damaged`, `lost`, `expired`.
- Toute entrée/sortie manuelle et toute correction d'inventaire exigent un motif.
- Un comptage physique ne modifie jamais directement le solde : Inventory calcule l'écart et enregistre un mouvement correctif.
- Chaque mouvement conserve l'acteur, la date effective, le motif et la référence de l'opération.
- Les mouvements sont append-only, immuables et non supprimables. Une correction se fait par mouvement compensatoire.
- Toutes les mutations de stock passent par des services Inventory transactionnels ; aucune écriture directe depuis l'interface ou un autre domaine.
- Les opérations concurrentes utilisent transactions et verrouillage des lignes de solde.

### 2.3 Autorisations et isolation

- Les rôles autorisés à initialiser et effectuer les mouvements manuels sont `super_admin_root`, `super_admin` et `cooperative_admin`.
- `super_admin_root` intervient sur toutes les coopératives.
- `super_admin` intervient uniquement sur les coopératives qui lui sont affectées.
- `cooperative_admin` intervient uniquement sur sa propre coopérative.
- Le rôle `user` n'a aucun droit de mutation Inventory.
- Toute autorisation est contrôlée côté serveur et ne peut pas être contournée par un identifiant transmis par le client.

### 2.4 Réservations et confirmation Orders

- Le checkout ne réserve aucun stock.
- Aucune réservation n'est créée pendant le délai d'attente de 12 heures de réponse de la coopérative.
- La réservation est créée uniquement lors de la confirmation de la sous-commande par la coopérative.
- La confirmation porte sur la totalité des quantités demandées pour toutes les lignes actives ; aucune confirmation partielle de quantité n'est permise.
- Si une seule ligne ne dispose pas de la quantité complète, la sous-commande ne peut pas être confirmée.
- L'indicateur vert/rouge est informatif et ne constitue jamais une réservation.
- À la confirmation, Inventory relit et verrouille les soldes, vérifie toutes les lignes, puis réserve l'ensemble dans une transaction atomique.
- En cas d'échec d'une ligne, aucune réservation partielle ne subsiste et Orders ne change pas le statut de la sous-commande.
- Une sous-commande d'une coopérative peut être confirmée même si une autre sous-commande de la commande globale est indisponible ; chaque sous-commande a son propre résultat.
- Orders reste propriétaire du statut commercial ; Inventory reste propriétaire de la réservation et des soldes.

### 2.5 Indicateurs de disponibilité

- Pour une commande multi-coopératives : un indicateur par sous-commande, calculé uniquement sur les lignes de cette sous-commande. Aucun indicateur global n'est requis.
- Pour une commande mono-coopérative : un indicateur global et un indicateur par ligne. Le global est vert uniquement si toutes les lignes actives sont vertes.
- Vert si la disponibilité nette couvre la quantité entière demandée ; rouge sinon.
- Les indicateurs sont recalculés après tout mouvement ou changement de réservation qui affecte la disponibilité.
- Aucun indicateur ne déclenche de réservation.
- L'indicateur public du catalogue ne doit jamais exposer les quantités exactes ; Catalog présente uniquement le statut commercial prévu par les décisions Q281/Q282/Q285 du registre fonctionnel.

### 2.6 Quarantaine et retours

- Les quantités en quarantaine sont déduites de la disponibilité.
- Seul `cooperative_admin` décide de la sortie de quarantaine :
  1. libération vers le stock disponible ;
  2. destruction/rebut avec sortie du stock physique ;
  3. maintien en quarantaine.
- Chaque décision exige un motif, un acteur et une date.
- Les mouvements historiques restent immuables.
- Un retour client accepté crée un mouvement `customer_return`.
- Une quantité refusée lors du contrôle de retour est placée en quarantaine et ne redevient pas disponible automatiquement.

### 2.7 Passage d'un produit simple aux variantes

Si un produit simple possède un stock, la coopérative doit répartir la totalité de ce stock entre les variantes avant de valider leur activation.
- Le transfert est orchestré par Inventory dans une transaction atomique.
- Les quantités affectées aux variantes doivent totaliser exactement le stock parent.
- Des mouvements immuables documentent la sortie du stock parent et les entrées des stocks variantes.
- Le stock parent est ramené à zéro ; aucun double comptage n'est permis.

## 3. Fonctions Inventory V1 — identifiants de suivi

Les identifiants ci-dessous sont attribués pour la spécification Inventory. Leur statut initial est **À développer** sauf mention contraire. Ils ne constituent pas une preuve d'implémentation.

| ID | Fonction | Acteurs | Statut |
|---|---|---|---|
| INV-001 | Initialiser le stock d'un produit simple ou d'une variante | root, super_admin affecté, cooperative_admin propriétaire | À développer |
| INV-002 | Consulter le solde et la disponibilité nette dans le périmètre autorisé | rôles habilités | À développer |
| INV-003 | Enregistrer une entrée de stock | rôles habilités | À développer |
| INV-004 | Enregistrer une sortie manuelle motivée | rôles habilités | À développer |
| INV-005 | Enregistrer une correction issue d'un comptage physique | rôles habilités | À développer |
| INV-006 | Consulter l'historique immuable des mouvements | rôles habilités selon périmètre | À développer |
| INV-007 | Calculer les indicateurs de disponibilité par ligne et sous-commande | Orders / rôles coopératifs autorisés | À développer |
| INV-008 | Réserver atomiquement toutes les lignes d'une sous-commande à sa confirmation | Orders via contrat Inventory | À développer |
| INV-009 | Libérer une réservation à la suite d'une annulation ou d'un changement commercial autorisé | Orders via contrat Inventory | À développer |
| INV-010 | Consommer la réservation lors de la sortie commerciale effective | Orders / Delivery via contrat à préciser | À développer |
| INV-011 | Placer une quantité en quarantaine | cooperative_admin selon workflow de retour/contrôle | À développer |
| INV-012 | Décider de la sortie de quarantaine (libérer, détruire, maintenir) | cooperative_admin | À développer |
| INV-013 | Accepter et enregistrer un retour client | cooperative_admin selon contrat Orders/Delivery | À développer |
| INV-014 | Transférer atomiquement le stock parent vers les variantes | cooperative_admin via Products + Inventory | À développer |
| INV-015 | Protéger les opérations contre concurrence, répétition et double traitement | services internes / domaines appelants | À développer |
| INV-016 | Publier un changement fiable de disponibilité pour Catalog | Inventory vers Catalog | À développer |

## 4. Contrats inter-domaines

| Domaine | Responsabilité du domaine externe | Contrat attendu avec Inventory |
|---|---|---|
| Products | Produits, variantes, unités, statut simple/variantes | Références stables produit/variante et unité canonique ; notification/orchestration du transfert parent-vers-variantes |
| Orders | Commandes, sous-commandes, lignes, états commerciaux | Demande de disponibilité ; réservation atomique complète à la confirmation ; libération et consommation selon événements commerciaux validés |
| Cooperatives / Identity | Identité, rôles, affectations et appartenance coopérative | Fournir l'acteur authentifié et permettre de résoudre son périmètre d'autorisation |
| Catalog | Présentation publique des produits | Consommer un état de disponibilité sans quantité interne exacte |
| Delivery | Expédition, remise transporteur et retours physiques | Contrat de sortie/consommation du stock et réception des retours à préciser avant implémentation |
| Audit | Journal transversal immuable et consultation protégée | Enregistrer les opérations sensibles via l'API réelle d'Audit, sans dupliquer son stockage |
| Media | Stockage des fichiers | Aucune propriété de fichiers par Inventory ; éventuelles preuves photo de contrôle via Media |

### Flux de confirmation de sous-commande

1. Orders demande une évaluation des quantités complètes de la sous-commande.
2. Inventory renvoie les résultats par ligne (vert/rouge) sans créer de réservation.
3. La coopérative demande la confirmation de la sous-commande.
4. Orders appelle le contrat de réservation Inventory dans le contexte de la transaction métier défini par l'intégration.
5. Inventory verrouille tous les soldes concernés, vérifie toutes les quantités, crée toutes les réservations et met à jour les quantités réservées atomiquement.
6. Si une ligne échoue, Inventory annule toute la réservation ; Orders conserve le statut antérieur.
7. Si la réservation réussit, Orders peut effectuer la transition commerciale dans la même frontière transactionnelle ou via un mécanisme fiable équivalent à définir à l'audit technique.

**Point d'architecture à traiter avant codage :** garantir l'atomicité entre la réservation Inventory et la transition Orders sans créer de transaction distribuée fragile. L'implémentation doit suivre le moteur DB et les conventions de transaction réels du dépôt.

## 5. Modèle de données cible — à valider après audit technique

Entités conceptuelles attendues :
- **InventoryStock** : solde par coopérative et cible stockable (produit simple ou variante), avec quantités physiques, réservées et en quarantaine.
- **InventoryMovement** : registre append-only des variations, avec type, delta, solde avant/après, acteur, motif, date et référence d'opération.
- **InventoryReservation** : réservation liée à une sous-commande et à ses lignes, quantité réservée, état et dates de cycle de vie.
- **InventoryQuarantine** (ou entité/état équivalent validé à la conception) : suivi des quantités et décisions de quarantaine.

La stratégie de référence produit/variante, les clés étrangères, contraintes d'unicité, règles de suppression, index et noms définitifs des tables restent à arrêter après inspection complète des migrations et conventions SQL du dépôt. Ne pas introduire de relation polymorphique sans avoir évalué l'intégrité référentielle.

## 6. Sécurité, intégrité et exigences techniques

- Validation serveur stricte des unités, précisions, quantités positives et motifs.
- Transactions et verrous de lignes pour chaque opération de solde.
- Idempotence pour les appels de réservation, libération, consommation, retours et événements.
- Empêcher les doubles réservations et les sorties dépassant la disponibilité.
- Immutabilité des mouvements au niveau des services, modèles et permissions ; aucun endpoint de modification/suppression.
- Isolation stricte par coopérative, y compris lors des consultations, exports éventuels et appels inter-domaines.
- Journalisation des opérations sensibles via Audit ; ne jamais journaliser de secrets ou données inutiles.
- Les erreurs d'Inventory ne doivent pas laisser Orders dans un état confirmé sans réservation complète.
- Les erreurs de transaction doivent annuler toutes les écritures associées.

## 7. Audit technique initial — état constaté au commit de référence

### Présent
- Domaine Orders présent et documenté.
- `OrderCreationService::createFromCheckout()` crée la commande globale et les sous-commandes en transaction, sans réserver de stock au checkout.
- `CooperativeOrderResponseService` gère les décisions de confirmation/refus et verrouille la sous-commande ; son chemin de confirmation passe actuellement au statut confirmé sans vérification ni réservation Inventory.
- Products fournit les modèles Product et ProductVariant ainsi que les unités de produits.
- Audit est un domaine existant et doit être appelé selon son API réelle.

### Absent ou non intégré
- Aucun dossier `app/Domains/Inventory` à la référence auditée.
- Aucune table de solde, mouvement, réservation ou quarantaine Inventory.
- Aucun service Inventory.
- Aucun contrat de réservation dans le flux de confirmation Orders.
- Aucun indicateur Inventory branché dans Orders/Catalog.
- Aucune permission Inventory dédiée identifiée dans la matrice examinée.
- Aucun test Inventory ni test d'intégration Orders–Inventory.

### Limites de cet audit
Il s'agit d'un audit initial ciblé avant création du domaine, pas de l'audit final après codage. Le schéma SQL définitif, les conventions de tests, la découverte des policies, l'API Audit complète et la stratégie transactionnelle inter-domaines doivent encore être vérifiés avant l'implémentation.

## 8. Plan de réalisation et suivi

| Étape | Travail | État |
|---|---|---|
| INV-AUD-01 | Vérifier les conventions BaseModel, migrations, ULID, FK et précision décimale | En cours |
| INV-AUD-02 | Vérifier Products/ProductVariant, unités et cycle de passage aux variantes | À faire |
| INV-AUD-03 | Vérifier Orders, transaction de confirmation, lignes et annulations | À faire |
| INV-AUD-04 | Vérifier permissions, rôles, affectations coopératives et policies | À faire |
| INV-AUD-05 | Vérifier API Audit et mécanisme append-only existant | À faire |
| INV-DES-01 | Valider le schéma Inventory et les contraintes SQL | À faire |
| INV-DES-02 | Valider les contrats transactionnels Orders–Inventory | À faire |
| INV-DEV-01 | Créer migrations et modèles Inventory | À faire |
| INV-DEV-02 | Implémenter services de mouvements et soldes | À faire |
| INV-DEV-03 | Implémenter réservations atomiques et intégration Orders | À faire |
| INV-DEV-04 | Implémenter disponibilité et contrats Catalog | À faire |
| INV-DEV-05 | Implémenter quarantaine, retours et transfert parent/variantes | À faire |
| INV-DEV-06 | Ajouter permissions, policies, routes/contrôleurs/interfaces si inclus au périmètre validé | À faire |
| INV-TEST-01 | Tests unitaires, Feature, HTTP et intégration multi-domaines | À faire |
| INV-AUD-POST | Audit complet après codage et correction des écarts | À faire |
| INV-DOC-01 | Synchroniser README, roadmap, registre fonctionnel et matrice de traçabilité | En cours |

## 9. Matrice de tests et critères de clôture

La clôture V1 exige au minimum des tests vérifiant :
- stock simple et stock par variante, précision 3 décimales et unités autorisées ;
- absence de stock négatif et rejet des unités/conversions non autorisées ;
- rôles, affectations, isolation inter-coopératives et refus du rôle user ;
- immutabilité des mouvements et corrections compensatoires ;
- initialisation, entrées, sorties, comptage et motifs obligatoires ;
- disponibilité nette et indicateurs vert/rouge exacts ;
- réservation seulement à la confirmation, jamais au checkout ni pendant les 12 heures d'attente ;
- réservation intégrale ou échec total, sans état ni réservation partielle ;
- concurrence, verrouillage, idempotence et absence de double réservation ;
- annulation/libération/consommation sans double traitement ;
- quarantaine, décision cooperative_admin, retour client accepté/refusé ;
- transfert parent-vers-variantes atomique et sans double comptage ;
- cohérence des transitions Orders et de la disponibilité Catalog ;
- journalisation Audit et absence de données sensibles superflues.

Un test est marqué réussi uniquement après exécution réelle sur l'environnement local/CI. Le domaine ne peut être déclaré complet tant que chaque fonction V1 n'est pas implémentée et vérifiée ou explicitement bloquée avec justification.

## 10. Historique

### 19 novembre 2026 — création du suivi Inventory V1
- Attribution des identifiants fonctionnels INV-001 à INV-016.
- Consolidation des décisions déjà validées sur stock, mouvements, réservations, disponibilité, quarantaine et transfert parent/variantes.
- Enregistrement de l'audit initial et des écarts d'intégration Orders.
- Aucun code Inventory n'est déclaré réalisé à cette date.

## 11. Audit technique complémentaire après audit global — 19 novembre 2026

**HEAD audité :** `ad3de3395a794e965973493716fbae1e0ca358bb`.  
**Rapport relationnel global :** `docs/architecture/DOMAIN_DEPENDENCY_AUDIT.md`, sections 9 et 10.  
**Statut :** audit statique Orders/Products effectué sur les fichiers et migrations indiqués ; schéma Inventory non encore gelé.

### 11.1 Products — source autoritative du stockable

Fichiers contrôlés :
- `app/Domains/Products/Models/Product.php`
- `app/Domains/Products/Models/ProductVariant.php`
- `app/Domains/Products/Models/ProductUnit.php`
- `app/Domains/Products/Contracts/CheckoutCatalog.php`
- `app/Domains/Products/DTOs/CheckoutProductData.php`
- `app/Domains/Products/Services/ProductService.php`
- `database/migrations/2026_11_18_000004_add_prices_to_products_and_variants.php`
- `database/migrations/2026_11_18_000005_create_product_units_and_attach_to_products.php`

Constats confirmés :
- Product possède `cooperative_id`, prix, devise, unité parent et relation vers ses variantes.
- ProductVariant appartient à un Product et possède prix/unité propres.
- Les cinq codes V1 sont `kg`, `g`, `l`, `ml`, `u`; aucune conversion automatique n'est prévue.
- Product avec variantes : stock à porter sur chaque variante ; le parent ne doit pas simultanément porter un stock.
- Le contrat CheckoutCatalog retourne actuellement produit, coopérative, prix, unité, état variantes et identifiants stables ; il ne fournit ni solde, ni quantité disponible, ni réservation.
- `resolveForCheckout()` verrouille le produit et la variante dans la transaction appelante. Ce verrou protège les données Products pendant la lecture checkout, pas le stock.

**Conséquence :** Inventory ne doit importer ni Product, ProductVariant, ProductUnit ni ProductService. Il stocke des références logiques au stockable et reçoit les informations nécessaires par un contrat Products public. Le code unité doit être conservé avec les mouvements/réservations pour interpréter les quantités historiques.

### 11.2 Orders — orchestration et point de réservation

Fichiers contrôlés :
- `app/Domains/Orders/Models/Order.php`
- `app/Domains/Orders/Models/CooperativeOrder.php`
- `app/Domains/Orders/Models/OrderItem.php`
- `app/Domains/Orders/Services/OrderCreationService.php`
- `app/Domains/Orders/Services/CooperativeOrderResponseService.php`
- `app/Domains/Orders/Enums/CooperativeOrderStatus.php`
- `database/migrations/2026_11_18_000003_create_marketplace_orders_tables.php`

Constats confirmés :
- OrderCreationService crée la commande, les sous-commandes et les lignes dans une transaction ; il ne réserve pas le stock au checkout.
- Les lignes conservent un snapshot JSON, la quantité à trois décimales, l'unité, le prix, la TVA et les totaux.
- Chaque sous-commande porte une coopérative, un statut et une échéance de réponse.
- CooperativeOrderResponseService verrouille la sous-commande puis la commande globale dans sa transaction ; il met actuellement la sous-commande en CONFIRMED sans appeler Inventory.
- Les statuts de commande sont possédés par Orders ; Inventory ne doit pas modifier directement les statuts commerciaux.
- Les lignes actives d'une sous-commande sont la portée de la réservation, conformément aux décisions V1.

**Point d'intégration obligatoire :** la confirmation doit appeler le contrat public Inventory dans la transaction de confirmation existante, après verrouillage/relecture des lignes actives et avant que la décision ne puisse être commitée. Si une seule ligne échoue, la transaction entière doit être annulée et le statut rester inchangé. Une confirmation d'une autre sous-commande de la même commande globale demeure indépendante.

### 11.3 Contraintes SQL et historique

- Les migrations Orders actuelles déclarent des FK physiques vers Users, Cooperatives, Products et ProductVariants, avec RESTRICT pour préserver les commandes historiques.
- Ces FK existantes ne seront ni réécrites ni supprimées dans ce lot.
- Pour les nouvelles tables Inventory, les références vers Orders, Products et Cooperatives seront logiques par défaut, conformément à ADR-021 ; toute exception physique doit être justifiée par un invariant, documentée et non destructive.
- Les mouvements de stock doivent être append-only. L'état courant ne peut être corrigé qu'au moyen d'un mouvement compensatoire.
- Le solde net reste `on_hand - reserved - quarantined`; aucune mutation ne peut produire une quantité physique, réservée ou en quarantaine négative, ni une disponibilité négative.
- Les lignes de réservation doivent permettre de prouver la correspondance avec la sous-commande et chaque ligne de commande, sans prendre possession des statuts Orders.
- Les opérations répétées, réessayées ou rejouées doivent être idempotentes ; la clé d'idempotence et la stratégie de re-confirmation après libération doivent être définies avant le gel du schéma.

### 11.4 Autorisations et intégration

- Identity demeure propriétaire des comptes, rôles, permissions générales et affectations de super-admin.
- Cooperatives demeure propriétaire des coopératives et de leurs règles d'organisation.
- Inventory doit posséder ses Policies et vérifier l'acteur, la permission générale et le périmètre coopératif à chaque opération.
- Root agit sur toutes les coopératives ; super_admin uniquement sur ses coopératives affectées ; cooperative_admin uniquement sur sa propre coopérative ; user sans droit de mutation.
- Le contrat d'acteur/périmètre ne doit pas exposer le modèle Eloquent User à Inventory.
- Les écritures critiques doivent utiliser AuditWriter dans la transaction lorsque l'atomicité est exigée ; les mouvements eux-mêmes constituent l'historique métier immuable.

### 11.5 Points à régler avant schéma final

1. Stratégie de ré-confirmation : une sous-commande refusée/annulée peut-elle être réouverte et réservée une seconde fois ? Si oui, règles exactes de libération puis nouvelle réservation.
2. Moment exact de consommation du stock : le statut `SHIPPED`, la remise au transporteur ou un autre fait de sortie ; le domaine Delivery n'existe pas encore.
3. Annulation après confirmation : conditions et acteur autorisé selon chaque statut Orders, notamment après préparation ou expédition.
4. Retours : contrat Orders/Delivery qui distingue retour demandé, retour reçu, contrôle qualité et acceptation du retour ; seule la quantité acceptée réintègre le stock, la quantité refusée va en quarantaine.
5. Passage produit simple vers variantes : point d'orchestration exact entre Products et Inventory, validation d'égalité des quantités et activation des variantes.
6. Garantie d'idempotence des réservations et opérations de mouvements, y compris les tentatives concurrentes et les reprises après incident.
7. Définition du contrat d'indicateur pour Orders et Catalog, y compris recalcul/invalidation après mouvement ou changement de réservation.

Ces points ne changent pas les règles déjà validées ; ils précisent les transitions et identifiants techniques qui ne sont pas encore complètement définis dans les documents disponibles. Aucun choix silencieux ne sera introduit.

### 11.6 Conclusion de l'audit technique

- Audit global des dépendances : réalisé statiquement au HEAD indiqué.
- Audit Products/unités/prix/variantes : réalisé sur les fichiers listés.
- Audit Orders/confirmation/snapshots : réalisé sur les fichiers listés.
- Audit complet des permissions et des migrations MySQL : à compléter avant gel du schéma.
- Schéma et contrats Inventory : non gelés.
- Implémentation Inventory : non commencée.
- Tests PHPUnit/Pint et tests de concurrence MySQL : non exécutés.



## Complément A20 — contrat Delivery (20 novembre 2026)

Référence : `DELIVERY_V1_SPECIFICATION.md`.

- Delivery est propriétaire des expéditions, transporteurs, missions, preuves, retours physiques, anomalies et litiges ; Inventory reste propriétaire des soldes, mouvements, réservations et quarantaines.
- Inventory ne consomme pas le stock sur simple création d'une mission ou acceptation transporteur. Le fait exact de sortie/consommation reste à arbitrer entre confirmation, remise physique au transporteur et départ effectif.
- À réception d'un retour, Delivery fournit un résultat d'inspection par produit ; Inventory seul décide/enregistre la réintégration des produits acceptés ou la mise en quarantaine des produits refusés, selon ses règles métier.
- Les contrats doivent être orientés et publics. Delivery ne peut pas écrire dans les tables Inventory ; Inventory ne peut pas écrire dans les tables Delivery.
- Aucun contrat de ce type n'est actuellement intégré au code. L'implémentation est bloquée sur le choix du fait de consommation et les transitions de retour, qui doivent être validés avant les migrations/handlers correspondants.
