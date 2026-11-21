# MAHLINE V1 — Domaine Inventory

- **Statut :** cadrage métier V1 consolidé le 21 novembre 2026 ; conception technique et implémentation en cours.
- **Spécification initiale :** [INVENTORY_V1_SPECIFICATION.md](../../../docs/domains/marketplace/INVENTORY_V1_SPECIFICATION.md).
- **Décisions validées faisant foi :** [INVENTORY_V1_DECISIONS_2026-11-21.md](../../../docs/domains/marketplace/INVENTORY_V1_DECISIONS_2026-11-21.md).
- **Architecture :** ADR-009, ADR-011, ADR-012, ADR-017 et ADR-021.

## Responsabilité

Inventory est l'unique propriétaire du stock physique, des soldes, mouvements, réservations, quantités en quarantaine et calcul de disponibilité nette.

La disponibilité V1 doit tenir compte des réservations, de la quarantaine non autorisée à la vente et des quantités en quarantaine explicitement autorisées. Le stock normal est prélevé en priorité. Les quantités de quarantaine autorisées restent identifiées séparément.

## Frontières

Inventory ne possède ni produits/variantes/unités de référence (Products), ni commandes et statuts commerciaux (Orders), ni panier (Sales), ni expéditions/retours physiques (Delivery), ni comptes/rôles (Identity), ni fichiers (Media), ni journal transversal (Audit).

Aucun autre domaine n'écrit directement dans les tables Inventory. Les intégrations utilisent des contrats/DTO publics et des identifiants logiques.

## Intégrations V1

- **Products :** identité produit/variante et unité canonique via contrat public.
- **Orders :** pré-évaluation de disponibilité au checkout, réservation atomique à la finalisation, expiration après 24 heures, consommation de la réservation lors de la confirmation coopérative, libération lors d'un refus/annulation autorisé.
- **Catalog / Sales :** consultation de l'état de disponibilité sans exposer les quantités internes.
- **Identity / Cooperatives :** contexte d'acteur, rôles, permissions et périmètre coopératif via contrats publics.
- **Delivery :** contrat public futur pour inspection des retours ; Inventory reste seul décideur des mouvements de stock.
- **Media :** stockage privé des preuves jointes aux signalements via l'API propriétaire de Media.
- **Audit :** journalisation des opérations sensibles via `AuditWriter`.
- **Notifications :** notifications in-app V1 pour alertes de stock et sécurité, sans dépendance Eloquent directe à Identity.

## Règles V1 essentielles

- Un stock logique par coopérative et par produit simple ou variante ; pas de transfert inter-coopératives ni de multi-emplacements en V1.
- Quantités `DECIMAL(15,3)`, unités `kg`, `g`, `l`, `ml`, `u`, aucune conversion automatique.
- Aucun stock négatif ; transactions et verrous de ligne obligatoires.
- Mouvements append-only ; correction par mouvement compensatoire.
- Réservation au checkout, 24 heures, expiration indépendante par sous-commande.
- Décrément physique à la confirmation de la sous-commande par la coopérative.
- Quarantaine, autorisation de vente conditionnelle et inventaires physiques avec blocage sélectif.
- Rapports PDF uniquement en V1.

## État technique

Le domaine était initialement un dossier documentaire sans modèles, migrations, services, contrats ni tests. La conception et l'implémentation sont en cours sur `feature/Marketplace`.

Les migrations seront ajoutées dans `app/Domains/Inventory/Database/Migrations` et chargées explicitement par le provider Inventory. Les migrations déjà exécutées ne seront jamais modifiées.

Une fonction ne sera déclarée terminée qu'après inspection du code, tests réellement exécutés, vérification des frontières inter-domaines et documentation synchronisée.
