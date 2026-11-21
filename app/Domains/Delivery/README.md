# MAHLINE V1 — Domaine Delivery

- **Statut :** domaine créé — spécification partielle non gelée — implémentation non démarrée.
- **Spécification :** [DELIVERY_V1_SPECIFICATION.md](../../../docs/domains/marketplace/DELIVERY_V1_SPECIFICATION.md)
- **Architecture :** ADR-009, ADR-011, ADR-012, ADR-017 et ADR-021.

## Responsabilité

Delivery possède les transporteurs dans leur représentation opérationnelle, contrats de service et tarifs, devis logistiques, missions, affectations, expéditions/colis, suivi, preuves logistiques, incidents, retours physiques et litiges de transport.

Delivery ne possède pas le panier (Sales), les commandes/statuts commerciaux (Orders), les stocks/réservations (Inventory), les factures/paiements/commissions/reversements (Finance), les comptes généraux (Identity), ni les fichiers (Media).

## Attribution après 12 heures — règle validée

À l'expiration des 12 heures, si aucun transporteur n'a déjà obtenu la mission :
- si un seul transporteur éligible propose le tarif minimal, la mission lui est attribuée ;
- si plusieurs transporteurs sont exactement ex æquo au tarif minimal, seuls ces transporteurs restent candidats et **le premier à enregistrer une acceptation valide obtient la mission** ;
- l'attribution doit être exclusive et atomique ; toute acceptation concurrente perdante est rejetée et historisée.

## Intégrations

- Orders : lecture des sous-commandes éligibles et publication des faits logistiques.
- Inventory : transmission des résultats d'inspection de retour ; Inventory décide du stock.
- Finance : publication des faits de collecte COD, livraison et retour.
- Media : preuves photo par contrat public.
- Localization : villes/adresses et règles géographiques par contrat public.
- Communication : notifications par contrat public.

## État technique

Aucun modèle, migration, service, route ou provider vide n'est créé artificiellement. Les décisions encore ouvertes de la spécification doivent être résolues avant la conception définitive des tables et transitions.
