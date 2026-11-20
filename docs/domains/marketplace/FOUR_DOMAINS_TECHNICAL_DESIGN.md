# MAHLINE V1 — Conception technique des domaines Sales, Inventory, Finance et Statistics

- **Version :** 0.1 — conception initiale, non gelée
- **Date :** 20 novembre 2026
- **Branche cible :** `feature/Marketplace`
- **Architecture de référence :** ADR-021, ADR-009, ADR-011, ADR-012 et ADR-017
- **Statut :** conception et audit initial réalisés ; décisions bloquantes listées en section 12
- **Règle :** ce document ne déclare aucun domaine implémenté et n'autorise pas une migration sur la base d'une hypothèse métier non validée.

## 1. Objectif et méthode

Livrer quatre domaines autonomes dans le monolithe modulaire Laravel MAHLINE, sans déplacer les responsabilités déjà détenues par Orders, Products, Cooperatives, Identity, Localization, Audit, Media ou Dashboard.

Ordre d'exécution proposé :
1. Sales — panier et parcours précommande ;
2. Inventory — soldes, mouvements, disponibilité et réservations ;
3. raccordement Inventory–Orders et finalisation des fonctions Orders dépendantes ;
4. Delivery — domaine autonome préalable aux flux d'expédition et de collecte ;
5. Finance — factures, paiements COD, commissions, transferts et reversements ;
6. Statistics — projections et indicateurs, raccordés aux événements publiés par les domaines sources.

Finance peut être conçu et documenté en parallèle, mais son implémentation intégrée ne doit pas anticiper les événements fiables de Orders et Delivery. Statistics peut être conçu en parallèle, mais ses projections ne doivent pas lire les tables métier privées.

## 2. Principes d'architecture obligatoires

- Chaque domaine possède ses modèles, tables, migrations, règles métier, services applicatifs, contrats publics, événements, policies et tests.
- Un domaine consommateur ne référence jamais un modèle, repository, service ou table privée d'un domaine fournisseur.
- Les appels synchrones nécessaires immédiatement passent par des interfaces et DTO publics du domaine fournisseur.
- Les faits métier sont publiés par événements versionnés après commit. Les événements critiques utilisent Transactional Outbox et une consommation idempotente.
- Les dépendances doivent être orientées et acycliques.
- Les migrations métier sont placées dans `app/Domains/{Domain}/Database/Migrations/`, conformément à ADR-021 et aux conventions du dépôt. Aucune migration déjà exécutée ne sera modifiée.
- Les références entre domaines sont des identifiants logiques par défaut. Une FK inter-domaines n'est ajoutée qu'après justification documentée d'un invariant d'intégrité et de son absence d'effet destructif.
- Les providers de domaine enregistrent les bindings, policies, routes, listeners et observers qui leur appartiennent ; `bootstrap/providers.php` reste le composition root.
- L'autorisation générale (authentification, rôle, permission) est séparée de l'autorisation métier (propriété, affectation, statut de la coopérative, possession de la ressource). Le middleware ne remplace jamais la Policy.
- Toute donnée historique financière ou de commande repose sur des snapshots immuables ; une donnée courante de Products ne réécrit jamais un document historique.

## 3. Ownership et responsabilités

| Domaine | Possède | Ne possède pas |
|---|---|---|
| Sales | Paniers invités et authentifiés, lignes, versions, fusion, validation précommande, orchestration checkout | Produits/prix de référence, stock/réservations, commandes après création, livraison, factures/paiements |
| Inventory | Soldes par stockable, mouvements append-only, réservations, quarantaine, disponibilité | Produits/variantes, statuts de commande, expédition, factures et paiements |
| Finance | Factures et PDF émis, paiements/encaissements, commissions contractuelles, créances, transferts transporteurs, reversements coopératives, rapprochements | Commandes et statuts commerciaux, livraison physique, stock, comptes utilisateurs |
| Statistics | Définitions de métriques, projections, agrégats, périodes, matérialisations et indicateurs autorisés | Source transactionnelle métier ; aucune écriture dans les domaines sources |

Dashboard reste la couche de présentation/agrégation d'interface. Il consomme Statistics via contrat public ; il ne devient pas propriétaire des projections statistiques.

## 4. Sales — conception cible

### 4.1 Modèle candidat

- `Cart` : propriétaire client ou visiteur, statut, version entière, devise, dernière activité, expiration éventuelle.
- `CartItem` : référence logique produit/variante/coopérative, quantité DECIMAL, unité snapshotée, caractéristiques commerciales, état achetable, prix observé et version du prix, snapshot descriptif.
- `CartItemHistory` : trace append-only des suppressions et changements commerciaux nécessaires à l'historique défini par SALES-014/015.
- `CheckoutAttempt` : clé d'idempotence, empreinte de requête, état, référence de commande Orders si confirmée, horodatage et résultat minimal permettant la reprise.
- Les tables et noms définitifs restent à confirmer par audit des conventions ULID, BaseModel, traits d'audit et migrations du dépôt.

### 4.2 Contrats fournisseurs requis

- Products : lire un produit/variante vendable, prix courant, unité, caractéristiques tarifaires et limite commerciale ; fournir un DTO versionné. Aucun import de modèle Products.
- Cooperatives : lire le statut et l'identité de la coopérative propriétaire via contrat public.
- Localization : vérifier l'appartenance et l'état actif d'une adresse, puis produire le snapshot d'adresse de livraison.
- Identity : résoudre le contexte client authentifié et les droits sans importer de logique interne Identity dans Sales.
- Inventory : lire la disponibilité indicative au checkout ; cette lecture ne réserve jamais le stock.
- Orders : accepter une commande depuis un DTO immuable et idempotent ; retourner un résultat public confirmant la création.
- Delivery : fournir le devis de livraison lorsque le montant de livraison est présenté avant validation. Cette intégration dépend de la définition du domaine Delivery.

### 4.3 Invariants techniques

- Panier invité : expiration après 30 jours sans activité ; panier authentifié : pas d'expiration automatique.
- Fusion invité/client atomique ; fusion de lignes seulement si produit, variante, coopérative et attributs commerciaux sont identiques.
- Panier authentifié unique par compte, synchronisé entre appareils.
- Contrôle optimiste de version : une écriture sur version obsolète est refusée et l'état courant doit être rechargé.
- Les prix sont toujours relus chez Products. Une hausse nécessite une acceptation explicite liée à la version exacte du prix ; une baisse est appliquée automatiquement.
- Les produits/variantes supprimés ou inactifs restent visibles dans le panier comme lignes non achetables ; aucun checkout si une telle ligne subsiste.
- Les quantités peuvent dépasser le stock ; elles ne peuvent dépasser les limites absolues et commerciales validées.
- Checkout COD V1, devise MAD, une adresse de livraison commune snapshotée, aucune réservation au checkout.
- Les lignes ne sont consommées du panier qu'après résultat Orders confirmé et vérifiable. Une réponse incertaine ne vide jamais le panier.

### 4.4 Routes et autorisations proposées

- Routes client : `sales.cart.show`, `sales.cart.items.store`, `sales.cart.items.update`, `sales.cart.items.destroy`, `sales.checkout.review`, `sales.checkout.store`.
- Middleware de groupe : throttling adapté ; `auth` uniquement pour les opérations exigeant un compte, pas pour le panier invité. Le middleware de session active est exigé sur les routes client authentifiées.
- Policy Sales : propriétaire du panier uniquement ; le visiteur n'accède qu'au panier lié à son jeton/session invité signé et non devinable.
- Aucun rôle back-office ne reçoit automatiquement le droit de consulter le panier privé d'un client.
- Les permissions d'administration ou de consultation transversale des paniers restent hors périmètre tant qu'elles ne sont pas validées.

## 5. Inventory — conception cible

### 5.1 Modèle candidat

- `InventoryStock` : un solde par coopérative et cible stockable (produit simple OU variante), quantité physique, réservée, quarantaine, unité canonique et version.
- `InventoryMovement` : journal append-only des variations ; type, delta, avant/après, motif, acteur, date effective, référence externe et clé d'idempotence.
- `InventoryReservation` : réservation pour une sous-commande, état et horodatages.
- `InventoryReservationItem` : lien logique vers la ligne Orders, stockable, quantité et unité réservées.
- `InventoryQuarantine` : quantité, origine, motif, état et décision de contrôle ; l'entité exacte sera confirmée au schéma.
- `InventoryOperation` ou mécanisme équivalent d'idempotence pour les commandes externes répétées.

### 5.2 Invariants métier déjà documentés

- Disponibilité = quantité physique - quantité réservée - quantité en quarantaine ; jamais négative.
- Stock coopératif logique unique en V1, sans entrepôts/emplacements.
- Produits simples : stock porté par le produit ; produits à variantes : stock par variante exclusivement.
- Quantités DECIMAL(15,3), unités canoniques alignées sur Products (`kg`, `g`, `l`, `ml`, `u`), sans conversion automatique.
- Initialisation par mouvement `opening_balance`; mouvements append-only ; correction par mouvement compensatoire.
- Toute mutation transactionnelle, verrouillage des soldes dans un ordre déterministe et contrôle d'idempotence.
- Stock non négatif ; aucune écriture directe depuis un autre domaine.
- La disponibilité catalogue ne révèle jamais les quantités exactes.

### 5.3 Contrats et flux Orders–Inventory

- Inventory fournit `StockAvailabilityReader` pour une lecture non mutante et `StockReservationManager` pour réserver/libérer/consommer.
- Orders fournit un DTO public de sous-commande et lignes actives ; Inventory ne lit pas les tables Orders.
- Au clic de confirmation, Orders verrouille la sous-commande et ses lignes, appelle la réservation Inventory avant la transition commerciale et ne confirme que si la réservation complète réussit.
- La réservation porte sur toutes les lignes actives et toutes les quantités demandées ; échec d'une ligne = échec intégral.
- Dans le monolithe à connexion SQL commune, l'opération synchrone doit participer à la transaction ouverte par Orders ; cela exige une convention explicite de transaction et un test MySQL d'intégration. Pas de transaction distribuée simulée.
- Une sous-commande multi-coopérative est réservée indépendamment des autres sous-commandes de la commande globale.
- La consommation du stock, la libération après annulation et le traitement des retours attendent les événements/contrats de cycle de vie Orders et Delivery définis en section 12.

### 5.4 Autorisations et routes proposées

- Permissions : `inventory.view`, `inventory.manage`, `inventory.adjust`, `inventory.reservations.view`, `inventory.quarantine.manage` (à aligner sur le registre réel des slugs avant ajout).
- Middleware de base : `auth`, session d'authentification active, rôle interne autorisé ; la Policy vérifie ensuite la coopérative.
- `super_admin_root` : toutes les coopératives ; `super_admin` : coopératives affectées ; `cooperative_admin` : sa propre coopérative ; `user` et `customer` : aucune mutation.
- Routes à nommer sous `root.backoffice.cooperatives.inventory.*` et `backoffice.cooperatives.inventory.*`, avec ressources séparées par action et pas de contrôleur global exposant les données d'une autre coopérative.
- Les réservations créées par Orders ne sont pas des opérations manuelles modifiables par l'interface.

## 6. Finance — conception cible

### 6.1 Sous-domaines possédés par Finance

- Invoicing : documents de facture, numérotation, snapshots, PDF immuable, états et historique d'envoi.
- Payments : encaissements COD, sommes dues, réception des transferts, rapprochements et allocations.
- Commissions : contrats versionnés MAHLINE–coopérative et calcul explicable de la commission.
- Payouts/Settlements : transferts société de livraison → MAHLINE et reversements MAHLINE → coopérative.
- Refunds : remboursements et écritures compensatoires, sans altérer les documents émis.
- SaaS Billing/Subscriptions est un domaine distinct et ne doit pas être absorbé par Finance Marketplace.

### 6.2 Modèle candidat

- `Invoice` + `InvoiceLine` + `InvoiceNumberSequence` + `InvoiceDeliveryAttempt` : document émis immuable, snapshot complet et envoi PDF.
- `PaymentCollection` + `PaymentAllocation` : encaissement déclaré/reçu et affectation aux commandes concernées.
- `PartnerContract` + `PartnerContractVersion` : règles datées et historisées pour coopératives et sociétés de livraison.
- `CommissionCalculation` : base, formule, version de contrat, taux/montant, arrondis et résultat.
- `CarrierRemittance` + `RemittanceAllocation` : rapprochement périodique des fonds société de livraison → MAHLINE.
- `CooperativePayout` + `PayoutAllocation` : reversement hebdomadaire et détail des sommes par commande.
- `Refund` + `FinancialLedgerEntry` : remboursement et écritures compensatoires traçables.
- Références logiques vers Orders, Delivery et Cooperatives ; snapshots financiers obligatoires.

### 6.3 Flux V1 documenté

- Paiement client COD à la société de livraison à la remise de la commande.
- La société conserve la part livraison due selon son contrat puis transfère le solde dû à MAHLINE.
- MAHLINE calcule la commission selon le contrat applicable à la coopérative, puis reverse le solde à la coopérative.
- Les remises transporteurs sont attendues tous les 7 jours sur calendrier ancré au 1er du mois (exemples : 7, 14, 21, 28).
- Les reversements MAHLINE → coopérative sont hebdomadaires selon le contrat.
- Les deux relations contractuelles (MAHLINE–coopérative et MAHLINE–transporteur) sont indépendantes, versionnées et datées.
- Les formules contractuelles possibles comprennent pourcentage sur total client, pourcentage sur produits seuls, ou montant fixe par tranche ; aucun taux unique global n'est imposé.
- Les remboursements, litiges et écarts de collecte doivent générer des écritures compensatoires, jamais modifier silencieusement un paiement ou une facture historique.

### 6.4 Facturation et verrou métier

- Une commande validée et effectivement réservée déclenche l'émission de la facture selon les règles métier documentées.
- Une facture émise/validée est immuable ; le PDF conservé ne peut pas être remplacé.
- Les numéros ne sont jamais réutilisés, même après annulation.
- Le PDF est conservé par Finance via le contrat public Media ; l'envoi email est traçable et reprenable.
- Les paramètres fiscaux (TVA, HT/TTC, régime, arrondis) sont issus de règles validées et versionnées ; aucune taxe ne sera inventée.
- **Blocage de cohérence à arbitrer avant schéma :** le document fonctionnel dit « une facture unique par commande », alors que la numérotation est « indépendante par coopérative » et les droits de lecture sont segmentés par coopérative. Il faut préciser si la facture est unique par commande globale avec allocations/sections coopératives, ou s'il existe une facture par sous-commande. Aucun code de facturation ne doit choisir silencieusement.

### 6.5 Routes et autorisations proposées

- Client : `finance.invoices.index`, `finance.invoices.show`, `finance.invoices.download` — uniquement ses propres factures.
- Back-office : `backoffice.finance.invoices.*`, `backoffice.finance.settlements.*`, `backoffice.finance.payouts.*`.
- Root : `root.backoffice.finance.*` pour accès global et administration.
- `cooperative_admin` : données de sa coopérative ; `super_admin` : coopératives affectées ; `super_admin_root` : global.
- Les mutations financières sont séparées des consultations : aucun rôle ne reçoit un droit de paiement, d'annulation ou de reversement par simple permission de lecture.
- Middleware : authentification active et rôle autorisé ; Policies par ressource et périmètre ; throttling sur opérations sensibles ; journalisation Audit obligatoire.
- Finance ne modifie jamais le statut d'une commande : il consomme les événements Orders/Delivery et publie ses propres faits financiers.

## 7. Statistics — conception cible

### 7.1 Responsabilité

Statistics est autonome et en lecture seule vis-à-vis des domaines sources. Il conserve des projections calculées à partir d'événements publics versionnés ou d'exports contractuels minimisés. Il ne fait aucune jointure sur les tables privées Sales, Orders, Inventory, Delivery, Finance, Products ou Cooperatives.

### 7.2 Projections candidates

- `MetricDefinition` : identifiant, version, formule documentée, dimensions et source autorisée.
- `MetricEvent` / curseur de consommation : événement source, version, identifiant stable et idempotence.
- `SalesDailyProjection` : ventes par période et dimensions autorisées.
- `OrderDailyProjection` : commandes et sous-commandes par statut/période.
- `InventorySnapshotProjection` : disponibilité agrégée, ruptures et mouvements, sans exposer de détails aux rôles non autorisés.
- `FinanceDailyProjection` : montants encaissés, dus, commissions et reversements selon droits financiers.
- `CooperativePerformanceProjection` : indicateurs de performance coopérative.
- Les projections détaillées et leurs dimensions seront figées après validation du catalogue des KPI et des droits d'accès.

### 7.3 Fiabilité et confidentialité

- Consommation idempotente, reprise après échec, déduplication par identifiant d'événement et version.
- Les événements critiques sont issus de l'Outbox du domaine source ; Statistics ne réinterprète pas un état transactionnel incomplet.
- Les projections peuvent être recalculées depuis un historique de faits publiés ou un mécanisme de replay contrôlé.
- Les métriques financières sont accessibles uniquement aux acteurs autorisés dans le périmètre coopératif ; les métriques clients ne doivent pas révéler des données personnelles.
- Les indicateurs publics sont agrégés et ne révèlent ni volumes de stock exacts ni données financières confidentielles.
- Dashboard consomme les DTO de lecture Statistics et reste responsable de la présentation, pas du calcul source.

### 7.4 Routes et autorisations proposées

- `statistics.overview.*`, `statistics.sales.*`, `statistics.inventory.*`, `statistics.finance.*`.
- Middleware : `auth` + session active + rôle autorisé ; Policy Statistics applique portée root/global, super_admin/affectations, cooperative_admin/propre coopérative, customer/données personnelles uniquement si un tableau de bord client est explicitement retenu.
- Les routes Statistics sont en lecture seule. Les tâches de recalcul/replay sont des commandes internes protégées et non des routes accessibles aux rôles métier.
- Dashboard n'accorde aucun droit supplémentaire : il relaie les droits fournis par Statistics.

## 8. Dépendances orientées — registre cible

| Fournisseur | Consommateur | Surface publique attendue | Sens |
|---|---|---|---|
| Products | Sales | `CartProductReader` / DTO de produit commercial | Sales → contrat Products |
| Cooperatives | Sales | `CooperativeStatusReader` / identité de propriétaire | Sales → contrat Cooperatives |
| Localization | Sales | `CheckoutAddressReader` / snapshot | Sales → contrat Localization |
| Identity | Sales | contexte acteur/client et autorisation | Sales → contrat Identity |
| Inventory | Sales | disponibilité indicative | Sales → contrat Inventory |
| Orders | Sales | `OrderCheckoutCreator` + résultat idempotent | Sales → contrat Orders |
| Delivery | Sales | devis de livraison | Sales → contrat Delivery |
| Products | Inventory | résolution stockable/unité/statut | Inventory → contrat Products |
| Orders | Inventory | lecture DTO sous-commande/lignes et faits de cycle de vie | Inventory → contrat/événements Orders |
| Inventory | Orders | disponibilité, réservation, libération, consommation | Orders → contrat Inventory |
| Orders / Delivery | Finance | faits validés de commande, expédition, livraison, encaissement COD | Finance consomme événements publics |
| Cooperatives | Finance | identité et contrats partenaires via surface dédiée | Finance → contrat Cooperatives |
| Media | Finance | stockage et récupération de PDF privé | Finance → contrat Media |
| Audit | Finance | écriture/consultation contrôlée | Finance → contrat Audit |
| Sales / Orders / Inventory / Delivery / Finance / Products | Statistics | événements versionnés minimisés | Statistics consomme |
| Statistics | Dashboard | DTO de lecture agrégés et filtrés | Dashboard → contrat Statistics |

Aucun cycle n'est autorisé. Les contrats doivent être définis dans le domaine fournisseur, les adaptateurs dans le consommateur ou dans le point de composition, selon les conventions ADR-021.

## 9. Conventions transversales de routes, middleware et policies

### 9.1 Règles communes

1. Préfixes et noms de routes possédés par le domaine ; pas de contrôleur métier central dans Dashboard ou Identity.
2. Les routes back-office root utilisent l'espace de nom dédié `root.backoffice.*` ; les routes standards utilisent `backoffice.*`. Les routes client utilisent un espace propre au domaine.
3. Le middleware vérifie l'authentification, la session active, les rôles généraux et les permissions de haut niveau.
4. La Policy du domaine vérifie systématiquement la ressource, le propriétaire, le statut de la coopérative et l'affectation super_admin.
5. Le scope est calculé côté serveur depuis l'acteur authentifié ; un `cooperative_id` fourni par le client n'est jamais une preuve d'autorisation.
6. Les opérations sensibles ont validation stricte, idempotence, throttling adapté, Audit et transaction/lock.
7. Les rôles `user` et `customer` ne sont pas assimilés à `cooperative_admin` ; aucun accès de gestion n'est implicite.

### 9.2 Matrice d'accès cible

| Domaine / action | super_admin_root | super_admin affecté | cooperative_admin propre coop | customer propriétaire | user |
|---|---|---|---|---|---|
| Sales panier client | Accès support uniquement si droit dédié | Aucun accès par défaut | Aucun accès par défaut | Son panier | Aucun accès par défaut |
| Inventory consulter/mutater | Toutes coopératives | Affectées | Propre coop | Aucun | Aucun |
| Finance consulter | Global | Affectées | Propre coop | Ses documents | Aucun |
| Finance mutations contractuelles/settlements | Global selon permission dédiée | Selon permission et affectation explicites | Aucun par défaut | Aucun | Aucun |
| Statistics consulter | Global | Affectées | Propre coop | Tableau personnel uniquement si validé | Aucun |

Les droits ci-dessus sont des limites de portée ; les slugs exacts doivent être alignés sur les fichiers de permissions existants avant toute route.

## 10. Tests obligatoires

- Tests unitaires de règles et calculs purs, sans accès aux modèles d'un domaine voisin.
- Tests de contrats fournisseur/consommateur et de sérialisation/version des DTO.
- Tests de policies : root global, super_admin affecté/non affecté, cooperative_admin propre/autre coop, client propriétaire/autre client, rôle sans droit.
- Tests feature routes : middleware, noms, binding ULID, 401/403/404, CSRF/session web, abilities API si endpoints API.
- Tests DB MySQL : contraintes, transactions, `lockForUpdate`, opérations concurrentes, deadlocks/retry, rollback intégral, idempotence.
- Tests d'événements : publication après commit, Outbox critique, duplication, ordre, reprise et replay.
- Tests d'absence de fuite inter-domaines : imports interdits, accès aux tables privées interdits, données financières et stock non divulgués.
- Tests non-régression Sales/Inventory/Orders/Delivery/Finance et suite complète avant gel.

## 11. Audit initial du dépôt au 20 novembre 2026

| Élément | Constat vérifié |
|---|---|
| `app/Domains` | Présents : Audit, Catalog, Categories, Cooperatives, Dashboard, Identity, Localization, Media, Orders, Products |
| Sales | Aucun dossier applicatif ; spécification fonctionnelle existante |
| Inventory | Aucun dossier applicatif ; spécification fonctionnelle existante |
| Finance | Aucun dossier applicatif ; règles fonctionnelles de facturation/paiement/livraison existantes dans un document commun |
| Statistics | Aucun dossier applicatif autonome ; Dashboard existe et doit rester la couche de présentation |
| Orders | Modèles et services de création/réponse présents ; pas de routes/contrôleurs de cycle de commande recensés dans le README audité ; confirmation sans réservation Inventory actuellement |
| Providers | Providers locaux des domaines existants enregistrés dans `bootstrap/providers.php` ; chaque nouveau domaine devra fournir le sien |
| Routes | `routes/web.php` contient les espaces root et back-office existants ; `routes/api.php` regroupe Identity/Audit ; les nouveaux domaines doivent enregistrer leurs routes depuis leurs providers selon ADR-021 |
| Finance | Aucun schéma, modèle, service, policy ou contrat Finance recensé dans `app/Domains` |
| Tests | Aucune exécution PHP/PHPUnit/Pint possible via le connecteur GitHub ; tout résultat de test devra être produit localement/CI |

Cet audit est statique et limité aux chemins inspectés ; il ne remplace pas l'inventaire complet des imports, migrations, policies, middleware et tests après implémentation.

## 12. Décisions bloquantes avant gel des schémas et code métier

1. **Sales — limite technique globale** : SALES-019 est validée dans son principe, mais la valeur numérique maximale n'est pas renseignée. Les valeurs minimale/maximale et les contrôles anti-abus doivent être fixés.
2. **Sales — version et idempotence** : choisir le type/version de panier, la durée de conservation des clés d'idempotence et la politique de reprise après résultat Orders incertain. Recommandation technique à présenter avant migration.
3. **Inventory — frontière transactionnelle** : confirmer l'utilisation de la transaction SQL commune Orders → Inventory et les tests MySQL qui garantissent rollback intégral.
4. **Inventory — sortie de stock** : le fait exact qui consomme la réservation dépend du workflow Delivery (remise au transporteur ou autre événement).
5. **Inventory — annulation et retour** : préciser les transitions Orders/Delivery qui libèrent une réservation et le fait de réception/contrôle qui réintègre une quantité acceptée ; les quantités refusées vont en quarantaine.
6. **Inventory — transfert produit simple vers variantes** : point d'orchestration Products/Inventory et validation d'égalité des quantités.
7. **Finance — facture** : résoudre la contradiction entre « une facture unique par commande » et « numérotation indépendante par coopérative » / visibilité par coopérative. Aucun modèle de facture ne sera gelé avant arbitrage.
8. **Finance — fiscalité** : taux/régime, HT/TTC, arrondis et règles d'application doivent être fournis par les décisions fiscales applicables ; aucun taux n'est supposé.
9. **Finance — contrats et litiges** : seuils, délais, écarts d'encaissement, remboursement/retour et preuves de rapprochement à compléter.
10. **Statistics — catalogue KPI** : valider les indicateurs V1, les dimensions temporelles et les droits exacts par rôle. Les projections proposées ici ne constituent pas encore un catalogue métier validé.

## 13. Séquence de réalisation et critères de clôture

Pour chaque domaine : audit des conventions réelles → conception et arbitrage → migrations additives → modèles/services/contrats/événements → policies/providers/routes/controllers → tests unitaires/feature/architecture/MySQL → documentation README et roadmap → audit final des références, imports, routes, policies et middleware.

Un domaine n'est déclaré **Validé** que si son périmètre V1 est implémenté, les contrats inter-domaines sont testés, les routes et policies sont auditées, les migrations sont exécutées et la suite ciblée puis complète est verte. Aucun merge vers `main` n'est autorisé sans demande explicite.
