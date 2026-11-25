# MAHLINE V1 — Domaine Orders

- **Branche :** `feature/Marketplace`
- **Commit audité :** `d23907b2f033d23ea2023a5eb52b5a751c94eed7`
- **Date de documentation :** 19 novembre 2026
- **État :** fondation métier et premiers services implémentés ; domaine incomplet, non validé par exécution locale des tests.

## 1. Responsabilité et frontières

Orders possède la commande globale, les sous-commandes affectées aux coopératives, les lignes et snapshots de commande, les statuts commerciaux et l'historique des décisions.

Orders ne possède pas :
- les définitions produits, variantes, prix courants ou unités (Products) ;
- le stock physique, les mouvements, les réservations ou la quarantaine (Inventory) ;
- le panier et l'orchestration précommande (Sales) ;
- les expéditions et transporteurs (Delivery) ;
- les paiements, commissions, transferts et reversements (Finance).

Les références vers les identifiants des autres domaines ne donnent pas à Orders le droit d'écrire dans leurs tables.

## 2. Modèle persistant actuellement présent

Migration additive : `database/migrations/2026_11_18_000003_create_marketplace_orders_tables.php`.

| Table | Fonction |
|---|---|
| `orders` | Commande globale client, référence, statut, devise, totaux, adresse de livraison snapshotée, dates de cycle de vie |
| `cooperative_orders` | Segment de commande affecté à une coopérative, statut, totaux, échéance et décision |
| `order_items` | Lignes rattachées à une sous-commande, références produit/variante, snapshot, quantité, unité et montants |
| `order_change_proposals` | Proposition de réduction ou remplacement liée à une ligne et réponse du client |
| `order_status_histories` | Historique des transitions et décisions, acteur, rôle, motif, métadonnées et date |

Les modèles Eloquent présents sont `Order`, `CooperativeOrder`, `OrderItem`, `OrderChangeProposal` et `OrderStatusHistory`. Les statuts métier sont représentés par les Enums du domaine. Les modèles utilisent la convention `BaseModel` (ULID, clé string, timestamps) ; les modèles concernés utilisent également la suppression logique.

Les clés étrangères des produits, variantes, coopératives et utilisateurs préservent les références historiques selon les règles restrictives de la migration. Les snapshots sont indépendants des libellés et données courantes du catalogue.

## 3. Fonctions effectivement implémentées

### 3.1 Création depuis le checkout — `OrderCreationService::createFromCheckout()`

- Vérifie la présence d'une adresse de livraison snapshotée et d'au moins une ligne.
- Recharge chaque produit depuis la base et verrouille les enregistrements pendant la transaction.
- Refuse un produit absent ou inactif.
- Recharge et vérifie la variante sélectionnée, son rattachement au produit et son état actif.
- Exige une variante lorsqu'un produit possède des variantes.
- Empêche l'utilisation simultanée de l'unité parent et d'une variante.
- Lit l'unité exclusivement depuis Products : unité du produit simple ou unité de la variante. Une unité envoyée par le client est ignorée.
- Refuse un article sans unité enregistrée et une devise autre que MAD.
- Recharge les prix depuis Products ; aucun prix fourni par le client n'est utilisé.
- Calcule les quantités à trois décimales et les montants en unités monétaires mineures entières, sans calcul flottant de prix.
- Crée une commande globale et regroupe les lignes par coopérative pour créer une sous-commande par coopérative.
- Persiste les lignes, les snapshots commerciaux et l'historique initial dans une transaction avec tentatives de reprise configurées.
- Ne crée aucune réservation Inventory au checkout ; la sous-commande démarre en attente avec une échéance de réponse de 12 heures (plafond absolu de 24 heures).

### 3.2 Réponse d'une coopérative — `CooperativeOrderResponseService`

Fonctions actuellement présentes :
- `confirmByCooperative()` : demande de confirmation d'une sous-commande par un acteur autorisé.
- `refuseByCooperative()` : refus avec motif.
- `decideOnBehalf()` : décision de substitution motivée, avec deux fenêtres strictes : `super_admin` affecté à partir de H+12 tant qu'aucune décision n'est enregistrée ; `super_admin_root` à partir de H+24.
- `extendResponseDeadline()` : prolongation motivée, réservée à root ou au super_admin affecté, avec échéance future et plafond de 24 heures.
- `refreshGlobalOrderStatus()` : agrégation du statut de la commande globale à partir des décisions des sous-commandes.

La réponse verrouille la sous-commande dans une transaction, vérifie son statut en attente et le périmètre de l'acteur. En cas de confirmation, Orders transmet les lignes actives à Inventory, qui verrouille les soldes, revérifie toutes les quantités et réserve la sous-commande intégralement. La confirmation et la réservation sont exécutées dans la même transaction SQL ; si une ligne manque de stock, l'opération échoue, la sous-commande reste en attente et aucune réservation partielle n'est conservée. Cette réservation confirmée n'a pas d'expiration automatique : elle reste active jusqu'à sa consommation lors de l'exécution ou sa libération explicite. En cas de refus, aucune réservation n'est créée. L'historique est ensuite écrit et le statut global recalculé. Une réponse concurrente ne peut donc pas décider deux fois la même sous-commande.

Une annulation de sécurité avant expédition libère cette réservation ; elle ne consomme ni ne recrée du stock.

## 4. Règles métier validées à préserver

- Une commande peut regrouper plusieurs coopératives ; chaque sous-commande appartient à une seule coopérative.
- Une commande mono-coopérative possède une sous-commande ; une commande multi-coopératives possède un segment par coopérative.
- Les snapshots de ligne conservent notamment produit, variante, unité, libellé d'unité, quantité, prix et devise.
- V1 : MAD, paiement à la livraison ; aucune réservation au checkout.
- Le stock est réservé uniquement au moment où la coopérative confirme sa sous-commande ; la disponibilité affichée avant confirmation reste informative.
- Une confirmation doit porter sur la quantité intégrale demandée pour chaque ligne active. Aucune confirmation partielle de quantité n'est permise.
- Si le stock disponible est inférieur à une quantité demandée, la coopérative ne confirme pas. Le `cooperative_admin` actualise/corrige le stock au moyen des opérations Inventory autorisées ; la disponibilité est ensuite recalculée.
- Pour une commande multi-coopératives, le voyant de disponibilité est porté par chaque sous-commande, indépendamment des autres coopératives.
- Pour une commande mono-coopérative, il y a un voyant global de commande et un voyant par ligne. Le global est vert uniquement si toutes les lignes actives sont entièrement disponibles.
- Vert signifie disponible intégralement ; rouge signifie stock insuffisant. Le voyant est informatif et ne réserve rien.
- La disponibilité est contrôlée à nouveau au moment de confirmer. Seule une confirmation validée déclenche la réservation, dans une transaction avec verrouillage.
- Une proposition de réduction ou remplacement doit être acceptée explicitement par le client avant modification de la ligne. Une confirmation ne peut pas réduire silencieusement la quantité.
- Le refus d'une proposition ou son expiration concerne la ligne concernée selon les règles fonctionnelles Orders ; les autres lignes continuent.
- Le délai de réponse coopérative de référence est de 12 heures, avec plafond absolu de 24 heures. Une échéance dépassée ne provoque ni refus ni expiration automatique ; la sous-commande reste `AWAITING_COOPERATIVE`.
- À l'échéance de réponse coopérative (H+12 par défaut, prolongation motivée possible dans la limite de H+24), MAHLINE notifie le `super_admin` affecté, qui peut confirmer ou refuser à la place de la coopérative tant qu'aucune décision n'est enregistrée. Le motif de substitution est obligatoire.
- À H+24 sans décision, MAHLINE notifie le `super_admin_root`, qui peut confirmer ou refuser à son tour avec un motif obligatoire. Le `super_admin_root` acquiert également le droit d'intervenir ; le `super_admin` affecté conserve son droit jusqu'à ce qu'une décision concurrente soit enregistrée. Le verrouillage transactionnel garantit qu'une seule décision gagne.
- Les deux escalades sont idempotentes, historisées dans `order_status_histories` et exécutées par `orders:escalate-overdue-cooperative-orders` chaque minute. Elles ne modifient jamais le statut commercial de la sous-commande.
- Une intervention administrative après échéance est attribuée et motivée : `super_admin` affecté à partir de H+12, puis `super_admin_root` à partir de H+24 ; les deux peuvent intervenir après H+24 tant qu'aucune décision n'est enregistrée.
- L'annulation client COD est permise jusqu'à confirmation de la sous-commande, selon le périmètre prévu ; après remise au transporteur, le parcours passe par le retour.
- Le retour ne remet jamais automatiquement le produit en stock : la coopérative contrôle et décide via Inventory.

## 5. Contrat de disponibilité et d'intégration Inventory

Inventory est la source de vérité pour le disponible. Pour chaque ligne active, la disponibilité doit être évaluée avec le produit ou la variante concernée, la coopérative propriétaire, l'unité et la quantité demandée.

Contrat attendu :
1. Orders fournit à Inventory l'identifiant de sous-commande et les lignes actives avec produit/variante, quantité et unité.
2. Inventory calcule le disponible sans mutation et retourne un résultat par ligne ainsi qu'un résultat agrégé de sous-commande.
3. Orders présente les voyants selon le mode mono/multi-coopérative défini ci-dessus.
4. Lors de la confirmation, Orders appelle l'opération de réservation d'Inventory dans la même frontière transactionnelle convenue ; Inventory verrouille les soldes, revalide chaque quantité et réserve toutes les lignes de la sous-commande ou échoue intégralement.
5. En cas d'échec, aucun statut `confirmed` n'est enregistré et aucune réservation partielle ne subsiste.
6. Les changements de stock recalculent les indicateurs affichés ; le voyant ne constitue jamais une garantie de réservation.

La stratégie d'atomicité inter-domaines (transaction DB commune ou orchestration/outbox si les domaines ne partagent pas la même connexion transactionnelle) devra être confirmée à l'audit technique Inventory avant implémentation.

## 6. Fonctions non encore livrées / limites constatées

- Pas encore de contrôle ni de réservation Inventory à la confirmation.
- Pas de calcul des voyants de disponibilité ni de présentation UI associée.
- Pas de contrôleurs, routes ou vues de gestion des commandes repérés dans le périmètre audité.
- Pas de parcours client complet pour consulter les commandes, annuler, accepter/refuser une proposition ou suivre les sous-commandes.
- Les propositions de modification sont modélisées, mais leur cycle complet (création, réponse client, expiration, notifications et concurrence) reste à implémenter.
- Le mécanisme d'escalade H+12/H+24 est implémenté avec notifications in-app, horodatages idempotents et historique ; les notifications email et le tableau de bord global de supervision restent à compléter.
- Pas de gestion complète des annulations, retours et événements de livraison.
- Les frais de livraison restent à intégrer avec Delivery.
- Pas de facture ou de logique financière dans Orders, conformément aux frontières de domaine.
- Des tests Feature couvrent les décisions H+12/H+24, l'idempotence des escalades et l'absence d'expiration automatique ; ils doivent encore être exécutés localement par l'utilisateur. Aucune validation PHPUnit, migration ou Pint n'est revendiquée dans cette session.

## 7. Fichiers principaux vérifiés

| Fichier | Responsabilité |
|---|---|
| `database/migrations/2026_11_18_000003_create_marketplace_orders_tables.php` | Schéma Orders |
| `app/Domains/Orders/Services/OrderCreationService.php` | Création transactionnelle depuis checkout |
| `app/Domains/Orders/Services/CooperativeOrderResponseService.php` | Réponse, décision administrative, échéance, agrégation |
| `app/Domains/Orders/Models/Order.php` | Commande globale |
| `app/Domains/Orders/Models/CooperativeOrder.php` | Sous-commande coopérative |
| `app/Domains/Orders/Models/OrderItem.php` | Ligne snapshotée |
| `app/Domains/Orders/Models/OrderChangeProposal.php` | Proposition de changement |
| `app/Domains/Orders/Models/OrderStatusHistory.php` | Historique |
| `tests/Feature/Domains/Orders/Services/OrderCreationServiceTest.php` | Tests de création et contrat Products |
| `tests/Feature/Domains/Orders/Services/CooperativeOrderResponseServiceTest.php` | Tests de réponse coopérative et intervention admin |

## 8. Prochaine étape du projet

Le cadrage Orders documente désormais les fonctions effectivement présentes et les fonctions restant à livrer. La prochaine étape est le domaine Inventory : audit des conventions de données et autorisations, validation du modèle de stock, puis migrations, modèles, services, policies, tests et raccordement contrôlé à Orders.

**Orders n'est pas déclaré terminé.** Le domaine ne sera clos qu'après livraison des parcours requis, intégration Inventory, exécution des tests ciblés et de la suite de non-régression.
