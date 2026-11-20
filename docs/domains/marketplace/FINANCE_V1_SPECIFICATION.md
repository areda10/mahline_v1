# MAHLINE V1 — Spécification fonctionnelle Finance

- **Domaine :** Finance
- **Version :** 0.1 — consolidation initiale
- **Date :** 20 novembre 2026
- **Branche :** `feature/Marketplace`
- **Statut :** règles fonctionnelles existantes consolidées ; arbitrages de facture et fiscalité requis avant gel ; aucun code Finance implémenté.
- **Référence :** `ORDER_INVOICING_PAYMENTS_DELIVERY_FUNCTIONAL_RULES.md` et `FOUR_DOMAINS_TECHNICAL_DESIGN.md`.

## 1. Responsabilité et frontières

Finance possède :
- Invoicing : factures, lignes, numérotation, snapshots, PDF émis, états et historique d'envoi ;
- Payments : encaissements COD, sommes dues, allocations, remboursements et rapprochements ;
- Commissions : contrats financiers versionnés et calculs ;
- Settlements : transferts des sociétés de livraison vers MAHLINE et reversements MAHLINE vers coopératives ;
- Refunds : remboursements et écritures compensatoires.

Finance ne possède ni commandes/statuts commerciaux (Orders), ni missions et expéditions (Delivery), ni stocks (Inventory), ni comptes/permissions (Identity). Subscriptions/SaaS Billing demeure un domaine autonome.

## 2. Règles fonctionnelles consolidées

### 2.1 Facturation
- Une commande validée génère un document de facture unique selon le principe métier validé.
- La facture est émise après validation de la commande et réservation effective du stock.
- Une facture émise/validée est immuable.
- Une annulation conserve la facture et son statut dans l'historique.
- Une correction après facturation passe par l'annulation motivée de l'ancienne commande puis la création d'une nouvelle commande et d'une nouvelle facture ; aucune modification directe d'un document émis.
- Un numéro attribué n'est jamais réutilisé.
- Numérotation documentée : `FAC-AAAA-NNNNNN`, compteur annuel par coopérative.
- Contenu minimal : coopérative, client, numéro/date, commande, produits/variantes, quantités/unités, prix, fiscalité applicable, livraison, total, devise et conditions de paiement.
- Le PDF est généré à l'émission, conservé dans MAHLINE, non remplaçable et envoyé au client par email.
- La date, le destinataire et le résultat de chaque tentative d'envoi sont conservés ; les échecs sont retentables sans réémettre la facture.

**Arbitrage bloquant :** « une facture unique par commande » doit être concilié avec la numérotation par coopérative et la visibilité coopérative. Décider si la facture est rattachée à la commande globale et contient des allocations/sections coopératives, ou si le document unique est en réalité par sous-commande. Ne pas créer le schéma avant décision.

### 2.2 Encaissement COD et flux financiers
- Le client remet le total de commande à la société de livraison lors de la remise.
- La société de livraison conserve la part de frais de livraison due selon son contrat.
- La société transfère à MAHLINE le solde dû.
- MAHLINE applique ensuite la commission contractuelle et reverse le solde à la coopérative.
- Le modèle V1 est COD ; aucun paiement carte/en ligne n'est présumé.

### 2.3 Contrats et commissions
- Contrat MAHLINE–coopérative et contrat MAHLINE–transporteur indépendants, datés et historisés.
- Formules possibles : pourcentage du total payé par le client, pourcentage des produits hors livraison, ou montant fixe par tranche.
- Le taux de 5 % est un exemple de scénario et n'est pas un taux universel.
- Chaque calcul conserve la version du contrat, l'assiette, la formule, le montant, les arrondis et la date d'effet.

### 2.4 Transferts et reversements
- Transfert transporteur → MAHLINE tous les 7 jours sur calendrier ancré au 1er du mois (exemples : 7, 14, 21, 28).
- Reversement MAHLINE → coopérative chaque semaine selon le contrat applicable.
- Chaque lot est rapproché des commandes, encaissements, contrats, transporteurs et coopératives.
- Les sommes non transférées restent identifiées comme créances/sommes dues.

### 2.5 Accès
- Client : ses propres factures et états de paiement.
- cooperative_admin : documents et mouvements de sa coopérative.
- super_admin : coopératives affectées.
- super_admin_root : portée globale.
- Les permissions de consultation ne donnent pas les droits de créer un contrat, valider un rapprochement, émettre un remboursement ou exécuter un reversement.

## 3. Modèle cible à concevoir

Candidats à valider au schéma :
- Invoice, InvoiceLine, InvoiceNumberSequence, InvoiceDeliveryAttempt ;
- PaymentCollection, PaymentAllocation ;
- PartnerContract, PartnerContractVersion ;
- CommissionCalculation ;
- CarrierRemittance, RemittanceAllocation ;
- CooperativePayout, PayoutAllocation ;
- Refund, FinancialLedgerEntry.

Les références vers Orders, Delivery et Cooperatives sont logiques par défaut. Les snapshots financiers sont conservés ; les modèles Finance ne font pas de relations Eloquent vers les modèles privés des domaines voisins.

## 4. Contrats et événements attendus

Finance consomme les faits publics validés :
- commande confirmée et réservation effective ;
- commande préparée, expédiée, livrée/remise et COD encaissé ;
- annulation, retour accepté, remboursement ou litige.

Finance expose des contrats de consultation financière contrôlée et publie ses propres événements : facture émise/annulée, encaissement rapproché, commission calculée, reversement exécuté, remboursement enregistré.

Les événements critiques utilisent l'Outbox et l'idempotence. Finance ne change jamais le statut d'une commande et ne déclenche pas une transition Orders par accès direct.

## 5. Sécurité, intégrité et audit

- Les factures émises et écritures financières sont append-only/immuables ; corrections par annulation ou écriture compensatoire.
- Numérotation atomique, non réutilisable, protégée contre les émissions concurrentes.
- Idempotence des événements COD, transferts, allocations et reversements.
- Transactions et verrouillage pour éviter doubles allocations et doubles paiements.
- Audit obligatoire des actions sensibles, avec acteur, périmètre, motif et références non secrètes.
- Aucune donnée bancaire ou personnelle superflue dans les événements/statistiques/logs.
- PDF privé servi via contrat Media, autorisé par Policy Finance.

## 6. Routes et policies à construire

Routes candidates :
- client : `finance.invoices.*` ;
- back-office : `backoffice.finance.invoices.*`, `backoffice.finance.remittances.*`, `backoffice.finance.payouts.*`, `backoffice.finance.contracts.*` ;
- root : `root.backoffice.finance.*`.

Policies Finance séparées pour Invoice, PartnerContract, PaymentCollection, Remittance, Payout et Refund. Les middlewares authentifient la session et vérifient rôle/permission générale ; les Policies vérifient possession, coopérative, affectation et action financière spécifique. Les slugs exacts seront alignés sur le registre de permissions avant ajout.

## 7. Tests requis

- Immutabilité facture/PDF et annulation sans réutilisation de numéro.
- Numérotation concurrente multi-coopérative et changement d'année.
- Idempotence encaissement, transfert, allocation et reversement.
- Calcul des commissions selon version du contrat, assiette, seuils et dates d'effet.
- Rapprochement exact, partiel, excédentaire, incomplet et en écart.
- Droits root / super_admin affecté / non affecté / cooperative_admin / client propriétaire.
- Fuites inter-coopératives, autorisation PDF, Audit et reprise d'envoi.
- Tests MySQL transactionnels et suite complète.

## 8. Décisions restant à arbitrer

1. Facture unique par commande globale ou facture par sous-commande coopérative.
2. Régime fiscal, taux, HT/TTC, arrondis et règles d'application.
3. Règles précises de traitement des transferts incomplets, écarts, contestations et preuves.
4. Cycle de remboursement et lien avec retours physiques Delivery/Inventory.
5. Définition des contrats en vigueur lorsqu'une version change avec des commandes déjà engagées.

Finance reste **à concevoir / non implémenté** tant que ces arbitrages et les contrats Orders/Delivery ne sont pas validés.
