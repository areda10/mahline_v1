# MAHLINE V1 — Domaine Finance

- **Statut :** domaine créé — règles métier consolidées — arbitrages de facturation/fiscalité et audit requis — implémentation non démarrée.
- **Spécification :** [FINANCE_V1_SPECIFICATION.md](../../../docs/domains/marketplace/FINANCE_V1_SPECIFICATION.md)
- **Règles de référence :** [ORDER_INVOICING_PAYMENTS_DELIVERY_FUNCTIONAL_RULES.md](../../../docs/domains/marketplace/ORDER_INVOICING_PAYMENTS_DELIVERY_FUNCTIONAL_RULES.md)
- **Architecture :** ADR-009, ADR-011, ADR-012, ADR-017 et ADR-021.

## Responsabilité

Finance possède la facturation, les paiements/encaissements, remboursements, commissions, rapprochements, allocations et reversements transporteurs/coopératives.

Finance ne possède ni les commandes et leurs statuts (Orders), ni les missions et expéditions (Delivery), ni le stock (Inventory), ni les comptes et autorisations générales (Identity). Subscriptions/SaaS Billing reste autonome.

## Flux COD

Le transporteur collecte le paiement auprès du client puis reverse les fonds à MAHLINE. Finance rapproche les sommes reçues, les affecte aux commandes et calcule les commissions et reversements dus aux coopératives. Delivery publie les faits logistiques ; Finance reste propriétaire des écritures et soldes.

## État technique

Aucun modèle, migration, service, route ou provider vide n'est créé artificiellement. Les règles fiscales, la numérotation et les invariants comptables doivent être arbitrés avant toute migration financière.
