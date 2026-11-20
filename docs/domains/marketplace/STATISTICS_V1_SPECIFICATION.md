# MAHLINE V1 — Spécification initiale Statistics

- **Domaine :** Statistics
- **Version :** 0.1 — proposition de périmètre V1
- **Date :** 20 novembre 2026
- **Branche :** `feature/Marketplace`
- **Statut :** autonomie du domaine validée ; catalogue KPI et accès détaillés à valider ; aucun code Statistics implémenté.
- **Référence :** `DOMAIN_ROADMAP.md`, ADR-009, ADR-012, ADR-021 et `FOUR_DOMAINS_TECHNICAL_DESIGN.md`.

## 1. Responsabilité et frontières

Statistics est propriétaire des définitions de métriques, projections, agrégats, dimensions, curseurs de consommation et résultats de calcul.

Statistics ne possède aucune donnée transactionnelle source. Il ne modifie jamais Sales, Orders, Inventory, Delivery, Finance, Products ou Cooperatives. Il ne lit pas leurs tables privées et ne construit pas de rapports par jointures SQL inter-domaines.

Dashboard reste responsable de la présentation et consomme les DTO de lecture de Statistics.

## 2. Sources et intégration

Statistics consomme exclusivement :
- événements publics versionnés émis après commit ;
- événements critiques provenant des Outbox des domaines sources ;
- contrats de lecture/export explicitement publiés et minimisés lorsque l'historique d'événements ne suffit pas.

Chaque événement consommé est dédupliqué par identifiant stable et version de schéma. Les consommateurs sont idempotents et reprenables. Les projections doivent pouvoir être recalculées/rejouées selon une procédure contrôlée et auditée.

## 3. Catalogue KPI proposé — à valider

### 3.1 Activité commerciale (Sales / Orders)
- commandes créées, confirmées, refusées, annulées ;
- chiffre d'affaires commandé, confirmé et livré, avec définitions distinctes ;
- nombre de commandes, panier moyen et répartition mono/multi-coopératives ;
- évolution quotidienne/hebdomadaire/mensuelle.

### 3.2 Produits et coopératives
- produits les plus commandés ;
- activité des coopératives ;
- évolution des ventes par coopérative et catégorie lorsque les événements publiés le permettent.

### 3.3 Inventory
- ruptures et alertes de disponibilité ;
- mouvements entrants/sortants et stock immobilisé en réservation/quarantaine ;
- agrégats uniquement dans les portées autorisées ; aucune quantité exacte n'est publiée au public.

### 3.4 Finance
- encaissements COD reçus, sommes dues, transferts rapprochés ;
- commissions calculées, reversements effectués et montants en attente ;
- indicateurs financiers disponibles uniquement aux rôles autorisés par Finance.

Ces KPI sont des propositions, pas des règles métier approuvées. Les définitions exactes (date de rattachement, annulations, retours, taxes, frais de livraison, devise et périmètre) doivent être validées avant création des projections.

## 4. Modèle technique candidat

- MetricDefinition : code stable, version, formule, source, dimensions et niveau de confidentialité.
- MetricConsumptionCursor : source, partition/cursor, dernière version et état.
- MetricEventReceipt : identifiant événement, domaine source, version, date, résultat de consommation et clé de déduplication.
- SalesDailyProjection, OrderDailyProjection, InventoryDailyProjection, FinanceDailyProjection.
- CooperativePerformanceProjection et éventuellement ProductPerformanceProjection.
- ProjectionRebuildRun : audit des reconstructions, période, motif, acteur système et résultat.

Les projections sont des données dérivées et peuvent être reconstruites à partir des faits autorisés. Elles ne doivent pas contenir de données personnelles non nécessaires.

## 5. Confidentialité et périmètres d'accès proposés

- super_admin_root : vue globale.
- super_admin : coopératives qui lui sont affectées.
- cooperative_admin : sa propre coopérative.
- customer : aucune vue analytique globale ; un tableau personnel ne sera créé que si explicitement validé.
- user : aucun accès par défaut.

Les métriques financières respectent les autorisations de Finance. Les métriques de stock n'exposent pas les quantités exactes à un acteur non autorisé. Les agrégats publics sont anonymisés et protégés contre les regroupements révélant une donnée individuelle.

## 6. Routes et policies à construire

Routes de lecture candidates :
- `statistics.overview.*`
- `statistics.sales.*`
- `statistics.orders.*`
- `statistics.inventory.*`
- `statistics.finance.*`

Statistics possède ses Policies de consultation et un contrat de lecture public consommé par Dashboard. Les routes de reconstruction/replay ne sont pas exposées aux rôles métier ; elles sont déclenchées par jobs/commandes internes protégés. Middleware d'authentification/session active/permission générale, puis Policy pour la portée de coopérative et la confidentialité de la métrique.

## 7. Tests requis

- Versionnement et compatibilité des événements.
- Idempotence, déduplication, ordre, reprise et replay.
- Exactitude des projections sur jeux d'événements déterministes.
- Recalcul sans double comptage.
- Isolation par coopérative et filtrage des métriques Finance.
- Absence de données personnelles et de quantités exactes dans les DTO publics.
- Contrat Statistics → Dashboard.
- Tests de routes, middleware, Policies et commandes/jobs internes.

## 8. Décisions restant à valider

1. Catalogue KPI obligatoire pour le premier release et formule exacte de chaque indicateur.
2. Périodes, fuseaux horaires et dates de rattachement (création, confirmation, livraison, encaissement).
3. Accès des clients aux indicateurs personnels (désactivé par défaut).
4. Granularité autorisée des métriques financières et de stock selon chaque rôle.
5. Rétention des événements/projections et politique de replay.

Statistics est **autonome mais non implémenté**. Il ne doit pas être fusionné dans Dashboard.
