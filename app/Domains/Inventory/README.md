# MAHLINE V1 — Domaine Inventory

- **Statut :** cadrage métier consolidé ; socle Inventory et intégrations en cours de vérification.
- **Suivi d'implémentation :** [INVENTORY_V1_IMPLEMENTATION_TRACKER.md](../../../docs/domains/marketplace/INVENTORY_V1_IMPLEMENTATION_TRACKER.md).
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
- **Orders :** pré-évaluation de disponibilité au checkout sans mutation ; réservation atomique uniquement à la confirmation coopérative ; réservation confirmée conservée sans expiration automatique jusqu'à consommation lors de l'exécution ou libération autorisée.
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
- Aucune réservation au checkout ni pendant l'attente de réponse coopérative.
- Réservation intégrale à la confirmation de la sous-commande, après revalidation et verrouillage des soldes ; aucune réservation partielle.
- Une réservation confirmée reste active sans expiration automatique jusqu'à consommation lors de l'exécution ou libération explicite.
- Le décrément physique intervient lors de la consommation de la réservation, pas au checkout ni à la simple confirmation.
- Quarantaine, autorisation de vente conditionnelle et inventaires physiques avec blocage sélectif.
- Rapports PDF uniquement en V1.

## État technique

Le domaine comporte maintenant les modèles et migrations de stock, mouvements, réservations, quarantaines, inventaires physiques, seuils/alertes et signalements de sécurité. Les services correspondants sont enregistrés dans `InventoryServiceProvider`.

Les intégrations présentes comprennent :
- checkout / Orders : preview sans réservation ; réservation persistante à la confirmation coopérative, consommation lors de l'exécution, libération sur annulation de sécurité ; expiration des seules réservations temporaires ;
- Identity : contexte d'acteur, permissions et notifications in-app ;
- Media : preuves privées des signalements ;
- Orders : blocages de sécurité et résolution humaine ;
- PDF : rapports complets ou simplifiés selon le rôle.

Les routes JSON/PDF sont en place ; les écrans dédiés restent à construire. Les tests ciblés et la suite complète doivent encore être exécutés dans l'environnement MySQL du projet. Voir le [suivi d'implémentation](../../../docs/domains/marketplace/INVENTORY_V1_IMPLEMENTATION_TRACKER.md).

Une fonction ne sera déclarée terminée qu'après inspection du code, tests réellement exécutés, vérification des frontières inter-domaines et documentation synchronisée.
