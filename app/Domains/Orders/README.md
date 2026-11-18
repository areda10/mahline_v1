# MAHLINE V1 — Domaine Orders

- **Branche de référence :** `feature/Marketplace`
- **État :** AUDIT INITIAL EFFECTUÉ — MONTAGE ARCHITECTURAL EN COURS
- **Référence de dépôt auditée :** `a24ef6e32bb6e7cc603a413620445bda943a848a`
- **Date :** 18 novembre 2026

## 1. Objectif

Orders porte le cycle de vie métier des commandes acceptées par la marketplace, depuis leur création persistée après le checkout jusqu'à leur clôture ou annulation. Il garantit l'historique, la segmentation par coopérative et la cohérence des transitions.

Ce document ne vaut pas validation des détails de schéma SQL. Les migrations et modèles seront ajoutés après inventaire complet des règles et validation des contrats inter-domaines.

## 2. Frontières de responsabilité

| Domaine | Responsabilité |
|---|---|
| Sales | Panier, lignes de panier, checkout, orchestration commerciale et demande de création de commande |
| Orders | Commande globale, lignes figées, sous-commandes coopératives, statuts et transitions, propositions de modification de lignes, historique des décisions |
| Products / Catalog | Identité et données descriptives des produits/variantes, prix publiés et présentation du catalogue |
| Inventory | Stock physique, disponibilité nette, mouvements, réservations et libérations |
| Delivery | Transporteurs, missions, expéditions, suivi, preuve de remise et événements de livraison/encaissement |
| Finance | Montants financiers, commissions, encaissements, transferts, reversements et rapprochements |
| Invoicing | À rattacher à Finance selon le registre d'architecture ; aucune duplication de factures dans Orders |
| Identity | Identité du client et des acteurs, rôles et authentification |

Orders ne possède ni stock, ni paiement, ni facture, ni expédition, ni définition de produit. Les références vers ces données ne transfèrent pas leur propriété à Orders.

## 3. Agrégat marketplace multi-coopératives

Une commande client peut regrouper des lignes de plusieurs coopératives. Le modèle cible doit distinguer :
- la commande globale du client ;
- les segments / sous-commandes propres à chaque coopérative ;
- les lignes de commande rattachées à leur segment ;
- les instantanés commerciaux nécessaires à l'historique (désignation, variante, unité, quantité, prix et données fiscales applicables).

Le nom technique final du segment coopératif et la structure exacte des instantanés restent à confirmer dans la spécification Orders/Sales avant migration.

## 4. Règles fonctionnelles déjà enregistrées

- Une commande validée et son historique ne doivent pas être réécrits pour refléter ultérieurement un changement du catalogue.
- La coopérative peut proposer une réduction de quantité ou un produit de remplacement ; la ligne ne change qu'après acceptation explicite du client (Q253).
- Une proposition porte sur la sous-commande concernée.
- Inventory revalide la disponibilité et effectue la réservation après confirmation.
- Si le client refuse, seule la ligne concernée est annulée ; les autres lignes continuent (Q254).
- Sans réponse du client à l'échéance configurable d'une proposition de modification, celle-ci expire et seule la ligne concernée est annulée automatiquement (Q255). Cette règle concerne la réponse du client à une proposition de ligne, pas le délai de réponse initial de la coopérative.
- Expiration côté serveur, notifications préalables, idempotence et arbitrage des réponses concurrentes sont requis.
- En paiement à la livraison V1, le stock est réservé à la confirmation de la sous-commande par la coopérative ; la réservation à la validation de commande concerne le futur paiement en ligne (FR-007).
- Une commande multi-coopératives possède une adresse de livraison commune, snapshotée sur la commande globale et immuable après confirmation (Q299–Q302). Toutes les commandes V1 exigent une adresse ; aucun retrait coopérative.
- Une seule expédition/un seul colis par sous-commande en V1 (Q304). Les frais sont calculés par expédition puis additionnés ; ils sont recalculés sur les expéditions restantes (Q303). Si une sous-commande est refusée entièrement avant expédition, ses frais sont retirés (Q305). Si toutes les sous-commandes sont refusées, la commande globale passe à Refusée, frais de livraison nuls et notification client (Q306).
- Le délai de réponse initial d'une coopérative est centralisé, avec référence de 12 h et plafond absolu de 24 h (Q307). À expiration sans réponse, la sous-commande reste en attente ; un administrateur est alerté, sans refus ni annulation automatique (Q308).
- Un administrateur habilité peut relancer avec un nouveau délai (maximum 24 h), ou accepter/refuser au nom de la coopérative ; toute intervention est attribuée, motivée et auditée (Q309). Intervention réservée à `super_admin_root` ou au `super_admin` affecté ; `cooperative_admin` ne peut pas prendre cette décision de substitution (Q310).
- Le client peut annuler une commande COD jusqu'à la confirmation par la coopérative ; après remise au transporteur, l'annulation n'est plus possible et un refus à la livraison ouvre un retour. Une demande d'annulation client sur un panier multi-coopératives vise seulement la sous-commande sélectionnée.
- Au retour, le transporteur photographie le colis à la prise en charge et la coopérative photographie/contrôle à réception. La remise en stock n'est jamais automatique : la coopérative décide après contrôle (FR-009/Q25–Q27).
- La modification d'une commande déjà facturée ne doit pas réécrire la commande ou la facture historique : appliquer la procédure d'annulation motivée et nouvelle commande prévue par les règles de facturation.

Références : `docs/roadmap/FIRST_RELEASE_FUNCTIONAL_DISCOVERY.md`, `docs/roadmap/DOMAIN_DEVELOPMENT_ROADMAP.md`, `docs/domains/marketplace/ORDER_INVOICING_PAYMENTS_DELIVERY_FUNCTIONAL_RULES.md`.

## 5. Audit initial du dépôt

Sur le commit de référence :
- aucun dossier `app/Domains/Orders/` ;
- aucune migration Orders identifiée ;
- aucun test `tests/**/Orders/` identifié ;
- aucun contrôleur ou route de commande identifié dans les domaines existants ;
- Products expose les produits et variantes, et Cooperative expose la relation vers ses produits ;
- les migrations Products utilisent des ULID, colonnes d'audit, timestamps, suppression logique et clés étrangères restrictives/cascadées selon la relation ;
- `BaseModel` fournit les ULID, clé string non auto-incrémentée et `guarded = []` ; `BaseService` reste volontairement vide.

L'audit est limité à l'inventaire Git du commit indiqué. Il ne prétend pas prouver l'absence de tout concept de commande dans les fichiers non encore inspectés.

## 6. Contrats inter-domaines à définir avant implémentation métier

1. Sales → Orders : commande de création, validation du panier et transmission des instantanés.
2. Orders → Inventory : vérification/réservation après confirmation, libération ciblée des quantités annulées.
3. Orders ↔ Sales : proposition coopérative, demande de décision client, acceptation/refus/expiration et recalcul commercial.
4. Orders → Finance : notification des montants définitifs et des changements qui affectent le montant dû.
5. Orders → Delivery : commande prête à expédier et référence des segments à prendre en charge.
6. Orders → Communication : notifications des événements client et coopérative.

Les échanges doivent être transactionnellement sûrs, idempotents, traçables et ne pas permettre à un domaine consommateur d'écrire directement dans les tables propriétaires d'un autre domaine.

## 7. Points métier encore ouverts — bloquants pour les branches concernées

- Délai exact et configuration de réponse du client à une proposition de modification.
- Matrice complète des états et du moment de confirmation définitive de la commande globale et de chaque sous-commande.
- Règles de traitement partiel si une proposition concerne plusieurs unités/lignes.
- Frais de livraison en cas d'annulation partielle de lignes dans une sous-commande (les règles de refus total Q305–Q306 et de calcul par expédition Q303 sont déjà enregistrées).
- Annulation totale, rupture de stock après checkout et échec de réservation.
- Matrice de transitions complète, acteurs autorisés et motifs obligatoires.
- Politique de conservation des snapshots et des références après suppression logique d'un produit ou d'un compte.
- Critères d'alerte et d'escalade opérationnelle après expiration du délai de réponse coopérative, au-delà de l'intervention admin prévue par Q308–Q310.
- Les règles de gestion du profil/suppression du compte client Q311–Q312 sont portées par Identity ; Orders conserve ses snapshots et références historiques.

Aucune valeur par défaut ne doit être inventée pour ces points.

### Écarts techniques de dépendance relevés le 18 novembre 2026
- Écart prix initial traité le 18 novembre 2026 : migration additive `2026_11_18_000004_add_prices_to_products_and_variants.php` ; `products.price` en MAD et `product_variants.price` facultatif comme surcharge. Les nouveaux formulaires produit exigent un prix positif à deux décimales. Les anciennes fiches peuvent rester sans prix en base ; Sales/checkout devra les exclure tant qu'un prix exploitable n'est pas défini. Les promotions Q260–Q269 restent à auditer/implémenter.
- Les migrations actuelles de `addresses` et le modèle `Address` existent ; le snapshot global de livraison doit néanmoins être persisté dans Orders et ne doit jamais dépendre de la ligne d'adresse après checkout.
- Les routes actuelles exposent déjà l'espace client et les adresses, mais aucun flux de panier/commande n'a été repéré dans les fichiers Routes audités.
- La convention `BaseModel` impose ULID, clé string, timestamps et `guarded = []`; les migrations utilisent `ulidPrimary()` et `auditActorColumns()`.

## 8. Prochaines étapes techniques

1. Terminer le relevé des règles Orders dans le registre fonctionnel et vérifier les éventuels concepts existants hors de `app/Domains`.
2. Faire valider la frontière Sales / Orders et le nom du segment coopératif.
3. Établir le diagramme d'états et la matrice des permissions par rôle.
4. Concevoir les migrations additives, contraintes d'intégrité et index.
5. Implémenter modèles/relations/Enums/services puis tests de domaine.
6. Ajouter les contrats d'intégration Inventory, Sales, Finance et Communication.
7. Ajouter routes, policies, contrôleurs et interfaces après validation des cas d'usage.
8. Exécuter les tests ciblés, Pint et la suite complète ; enregistrer les résultats réels.

## 9. Montage technique initial — 18 novembre 2026

Fichiers ajoutés sur `feature/Marketplace` :
- Migration additive `2026_11_18_000003_create_marketplace_orders_tables.php` : `orders`, `cooperative_orders`, `order_items`, `order_change_proposals`, `order_status_histories`.
- Enums : `OrderStatus`, `CooperativeOrderStatus`, `OrderItemStatus`, `OrderChangeProposalStatus`, `OrderChangeProposalType`.
- Modèles : `Order`, `CooperativeOrder`, `OrderItem`, `OrderChangeProposal`, `OrderStatusHistory`.

Ce premier lot établit la persistance des commandes globales, segments coopératifs, snapshots de lignes, propositions de modification et historique des transitions. Il ne constitue pas encore un parcours commandable : services transactionnels, validation métier, politiques d'accès, contrôleurs/routes, interfaces, notifications, intégrations Inventory/Sales/Finance/Delivery et tests restent à construire.

La migration est additive ; aucune migration déjà exécutée n'a été modifiée. Les tests et migrations n'ont pas été exécutés dans un environnement PHP/MySQL par cette opération. Le schéma devra être vérifié contre les conventions DB réelles et les tests de migration avant d'être déclaré validé.

**État : fondation Orders créée — module non terminé, non testé et non gelé.**
