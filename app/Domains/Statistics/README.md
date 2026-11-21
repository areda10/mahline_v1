# MAHLINE V1 — Domaine Statistics

- **Statut :** domaine créé — spécification initiale à cadrer — implémentation non démarrée.
- **Spécification :** [STATISTICS_V1_SPECIFICATION.md](../../../docs/domains/marketplace/STATISTICS_V1_SPECIFICATION.md)
- **Architecture :** ADR-009, ADR-010, ADR-011, ADR-012, ADR-017 et ADR-021.

## Responsabilité cible

Statistics possède les indicateurs, agrégations, projections de lecture et rapports analytiques transversaux autorisés. Il ne devient propriétaire d'aucune donnée transactionnelle des domaines sources.

Les domaines métier restent propriétaires de leurs faits et publient les événements/contrats nécessaires. Statistics consomme ces surfaces publiques et construit ses propres projections, avec traçabilité de la fraîcheur et possibilité de reconstruction.

## Frontières

Statistics ne modifie jamais les commandes, stocks, expéditions, factures, paiements, commissions, comptes ou données catalogue. Il n'accède pas directement aux modèles, repositories ou tables des domaines sources. Les accès sont filtrés selon les autorisations du domaine propriétaire et les périmètres coopératifs de l'acteur.

## État technique

Aucun tableau de bord, modèle analytique, migration ou endpoint n'est créé avant validation des indicateurs, règles d'agrégation, granularité, confidentialité et stratégie de rétention.
