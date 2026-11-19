# MAHLINE V1 — Spécification fonctionnelle Sales

- **Domaine :** Sales
- **Version :** 1.0 — cadrage initial
- **Date :** 19 novembre 2026
- **Branche :** `feature/Marketplace`
- **Statut :** EN COURS — décision d'architecture et mode de panier validés ; règles détaillées à définir.
- **Référence d'architecture :** [ADR-021 — Architecture hybride du monolithe modulaire](../../architecture/decisions/ADR-021-hybrid-modular-monolith.md)

> Ce document est le registre des décisions fonctionnelles et techniques du domaine Sales. Une règle non validée reste explicitement ouverte. Aucune migration ou implémentation ne doit être créée sur la base d'une supposition.

## 1. Objectif et responsabilité

Sales possède le parcours commercial précommande :
- panier invité et panier client authentifié ;
- lignes et regroupement des articles dans le panier ;
- calcul et présentation du récapitulatif commercial ;
- promotions et coupons, après définition de leurs règles ;
- devis, après définition de leur cycle de vie ;
- orchestration du checkout et transmission de la demande de création à Orders.

Sales ne possède pas :
- produits, variantes, unités et prix catalogue de référence (Products) ;
- catégories et présentation du catalogue (Categories / Catalog) ;
- stock, disponibilité faisant foi et réservations (Inventory) ;
- commande globale, sous-commandes, lignes et statuts après création (Orders) ;
- transporteurs, offres et expéditions (Delivery) ;
- factures, paiements, remboursements, commissions et reversements (Finance) ;
- comptes, sessions et permissions (Identity).

## 2. Architecture et propriété

MAHLINE conserve un monolithe modulaire Laravel avec déploiement unique et base SQL partagée. Sales est un domaine autonome, au même titre que Orders, Inventory, Delivery, Finance et Communication.

Sales possède ses modèles, tables, règles métier, services applicatifs, policies, contrats publics fournis, migrations et tests. Les autres domaines ne doivent pas importer ses modèles ou services privés ni écrire dans ses tables.

Les échanges inter-domaines utilisent les contrats publics et DTO du domaine fournisseur, ainsi que des événements métier versionnés publiés après commit. Les opérations critiques suivent les règles d'atomicité et d'Outbox de l'ADR-021.

## 3. Décisions validées

### SALES-001 — Panier invité et authentifié

**Statut : VALIDÉ — 19 novembre 2026**

Le panier est accessible :
- au visiteur non authentifié, sans obligation de créer un compte avant d'ajouter des articles ;
- au client authentifié, avec conservation de son panier associé à son compte.

Après authentification d'un visiteur possédant un panier invité, Sales fusionne ce panier avec le panier du compte client.

Le détail de la stratégie de fusion (doublons, quantités maximales, articles devenus indisponibles ou inactifs, prix modifiés et conflits concurrents) reste à spécifier avant implémentation.

### SALES-002 — Séparation des domaines

**Statut : VALIDÉ — 19 novembre 2026**

Sales, Orders, Inventory, Delivery, Finance et Communication sont des domaines autonomes dans le monolithe modulaire. Chacun est propriétaire de ses modèles, tables, règles métier, contrats et tests.

Orders reste le domaine propriétaire de la commande après sa création. Finance reste propriétaire des documents et opérations financières. Sales ne devient pas un domaine englobant ces responsabilités.

## 4. Frontières fonctionnelles du parcours

1. Le client consulte le catalogue ; Catalog / Products restent les sources de vérité des données catalogue.
2. Sales ajoute et conserve les articles dans le panier.
3. Sales demande aux domaines propriétaires les informations nécessaires au calcul et à la validation.
4. Les règles de promotions et de devis s'appliquent uniquement selon les décisions qui seront validées.
5. Au checkout, Sales prépare une demande complète et validée.
6. Sales transmet cette demande au contrat public de création d'Orders.
7. Orders crée la commande globale et ses sous-commandes ; Sales ne modifie ensuite ni les lignes ni les statuts de cette commande.
8. Le checkout ne réserve aucun stock. La réservation intervient uniquement lors de la confirmation de la sous-commande par la coopérative, selon la spécification Inventory.

## 5. Règles existantes à préserver

- Panier multi-coopératives.
- Checkout avec paiement à la livraison (COD) uniquement pour la V1.
- Devise V1 : MAD, sans conversion implicite.
- Une commande globale possède une seule adresse de livraison commune, capturée dans un snapshot indépendant.
- Toutes les commandes V1 nécessitent une adresse de livraison ; pas de retrait en coopérative pour le premier release.
- Le carnet client autorise au maximum cinq adresses actives ; la suppression logique d'une adresse ne modifie jamais les snapshots d'une commande existante.
- Le checkout ne réserve aucun stock.
- Orders crée une sous-commande par coopérative et conserve les snapshots commerciaux des lignes.
- Une modification d'une ligne de commande après création relève du workflow Orders et ne peut pas être effectuée silencieusement par Sales.

## 6. Décisions encore ouvertes

Les sujets suivants doivent être arbitrés avant le gel fonctionnel et le schéma :
- fusion des paniers invité et client : traitement des lignes identiques, quantités, limites et conflits ;
- durée de vie du panier invité et du panier authentifié ;
- règles de suppression/expiration des paniers et de leurs lignes ;
- comportement lorsque le produit ou la variante devient inactive ou n'est plus vendable ;
- traitement d'une modification du prix catalogue entre ajout au panier et checkout ;
- moment et durée de validité du prix affiché ;
- règles d'arrondi et de calcul des totaux, taxes et éventuels frais ;
- promotions : périmètre global ou par coopérative, cumul, priorité, plafonds, période, éligibilité et limites d'utilisation ;
- coupons : unicité, attribution, limites d'utilisation et contrôle contre les abus ;
- devis : acteurs, durée de validité, acceptation, expiration et conversion en commande ;
- checkout : validations, idempotence, double soumission, concurrence et gestion des erreurs partielles ;
- suppression du panier après création réussie de la commande et conservation de la preuve de checkout ;
- droits de consultation et de gestion des paniers pour les rôles MAHLINE.

## 7. Suivi d'implémentation

Aucun modèle, migration, route, contrôleur ou service Sales n'est déclaré implémenté par ce document. Le développement commencera après validation des règles métier essentielles et audit des contrats réels de Catalog, Products, Localization et Orders.

Les tests requis devront couvrir au minimum : panier invité, panier connecté, fusion et conflits, multi-coopératives, prix obsolètes, promotions, devis, checkout idempotent, sécurité des accès, et non-régression Orders / Inventory.

## 8. Journal des décisions

### 19 novembre 2026
- SALES-001 : panier invité et authentifié avec fusion à la connexion — validé (option B).
- SALES-002 : autonomie des domaines Sales, Orders, Inventory, Delivery, Finance et Communication dans le monolithe modulaire — validé.
