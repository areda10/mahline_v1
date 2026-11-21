# MAHLINE V1 — Domaine Sales

- **Statut :** domaine créé — cadrage fonctionnel en cours — implémentation non démarrée.
- **Spécification :** [SALES_V1_SPECIFICATION.md](../../../docs/domains/marketplace/SALES_V1_SPECIFICATION.md)
- **Architecture :** ADR-009, ADR-011, ADR-012, ADR-017 et ADR-021.

## Responsabilité

Sales possède le parcours précommande : panier invité/authentifié, lignes de panier, calcul du récapitulatif commercial, devis/promotions lorsqu'ils seront cadrés et orchestration du checkout vers Orders.

Sales ne possède ni le catalogue et les prix de référence (Products), ni le stock et les réservations (Inventory), ni les commandes après création (Orders), ni les missions/expéditions (Delivery), ni les factures/paiements/commissions (Finance).

## Règle quantitative validée

La quantité maximale absolue est de **100 unités par ligne de panier**. Le contrôle serveur doit être appliqué à l'ajout, la modification, la fusion de paniers et au checkout. Cette règle est indépendante du stock et ne remplace pas les limites commerciales plus basses éventuellement définies par la coopérative ou le produit.

## Intégrations

- Products : lecture des informations/prix via contrat public.
- Inventory : consultation de disponibilité via contrat public, sans réserver depuis Sales.
- Localization : validation/résolution d'adresse via contrat public.
- Delivery : demande de devis logistique via contrat public lorsque le panier est éligible.
- Orders : transmission du checkout via contrat public ; Orders reste propriétaire des commandes.

## État technique

Ce dossier est la racine du domaine. Aucun modèle, migration, service, route ou provider vide n'est créé artificiellement. Les composants seront ajoutés par lots fonctionnels après audit des contrats et approbation des changements fichier par fichier.
