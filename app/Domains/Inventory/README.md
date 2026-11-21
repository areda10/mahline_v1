# MAHLINE V1 — Domaine Inventory

- **Statut :** domaine créé — cadrage métier initial validé — audit technique et conception détaillée à poursuivre — implémentation non démarrée.
- **Spécification :** [INVENTORY_V1_SPECIFICATION.md](../../../docs/domains/marketplace/INVENTORY_V1_SPECIFICATION.md)
- **Architecture :** ADR-009, ADR-011, ADR-012, ADR-017 et ADR-021.

## Responsabilité

Inventory est l'unique propriétaire du stock physique, des soldes, mouvements, réservations, quantités en quarantaine et calcul de disponibilité nette.

Formule de référence :

`quantity_available = quantity_on_hand - quantity_reserved - quantity_quarantined`

La disponibilité ne peut jamais être négative. La précision V1 définie dans la spécification est DECIMAL(15,3).

## Frontières

Inventory ne possède ni produits/variantes/unités de référence (Products), ni commandes et statuts commerciaux (Orders), ni panier (Sales), ni expéditions/retours physiques (Delivery), ni factures et flux financiers (Finance).

Delivery fournit les résultats d'inspection des retours ; Inventory décide seul du mouvement de stock, de la remise en stock ou de la quarantaine. Aucun autre domaine n'écrit directement dans les tables Inventory.

## Intégrations

- Products : identité produit/variante et unité via contrat public.
- Orders : lecture de disponibilité et réservation atomique à la confirmation.
- Delivery : résultat d'inspection de retour via contrat/événement public.
- Sales : consultation de disponibilité, sans mutation.
- Audit : journalisation via contrat public.

## État technique

Aucun modèle, migration, service, route ou provider vide n'est créé artificiellement. Les migrations seront ajoutées dans `app/Domains/Inventory/Database/Migrations` après résolution des décisions de conception et audit des invariants de concurrence.
