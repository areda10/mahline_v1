# Audit technique — cycle de vie Orders V1

- **Branche :** `feature/Marketplace`
- **Révision auditée :** `2e086704cd18db635a8c9cafb09ad86cac4cdf28` (base de l'audit statique)
- **Périmètre :** Sales → Orders → Inventory → Delivery → Finance
- **Statut :** audit statique et première implémentation de l'annulation client ; tests locaux de cette nouvelle implémentation à exécuter.

## 1. Décisions fonctionnelles appliquées

- Le client peut annuler une sous-commande uniquement tant que son statut est `awaiting_cooperative`.
- Dès qu'une coopérative, un `super_admin` affecté ou le `super_admin_root` a confirmé/refusé, l'annulation client est interdite.
- Le motif client est obligatoire.
- En multi-coopératives, l'annulation cible une sous-commande ; les autres sous-commandes continuent leur propre cycle.
- Les frais de livraison et les totaux de la commande globale sont recalculés à partir des sous-commandes restantes ; les snapshots des sous-commandes annulées ne sont pas réécrits.
- La sous-commande annulée et ses lignes actives sont conservées et historisées ; aucune suppression physique.
- L'annulation avant décision ne libère pas de réservation Inventory : aucune réservation ne doit exister avant confirmation.

## 2. Audit fichier par fichier

### Orders

| Fichier | Responsabilité vérifiée | Résultat / écart |
|---|---|---|
| `app/Domains/Orders/Models/Order.php` | Commande globale, totaux, adresse et snapshots, statut, annulation | Modèle présent ; champs globaux d'annulation déjà disponibles. |
| `app/Domains/Orders/Models/CooperativeOrder.php` | Sous-commande par coopérative, échéances, décisions | Modèle présent ; statuts et horodatages d'escalade présents. |
| `app/Domains/Orders/Models/OrderItem.php` | Lignes et snapshots historiques | Modèle présent ; annulation logique des lignes prise en charge. |
| `app/Domains/Orders/Models/OrderStatusHistory.php` | Historique append-only des transitions | Supporte motif, acteur, rôle, métadonnées et date. |
| `app/Domains/Orders/Services/OrderCreationService.php` | Création multi-coopératives | Crée la commande globale et les sous-commandes ; pas de réservation au checkout ; stock revalidé dans la transaction. |
| `app/Domains/Orders/Services/OrderCheckoutService.php` | Adaptateur du contrat checkout | Implémente `OrderCheckoutCreator` et délègue à OrderCreationService. |
| `app/Domains/Orders/Services/CooperativeOrderResponseService.php` | Confirmation/refus, substitution, réservation | Réserve toutes les lignes avant confirmation ; refus et décisions administratives motivés ; verrouillage transactionnel. |
| `app/Domains/Orders/Services/CooperativeOrderEscalationService.php` | Alertes H+12/H+24 | Notifications idempotentes et journalisées ; ne modifie pas le statut en attente. |
| `app/Domains/Orders/Services/CooperativeOrderFulfillmentService.php` | Préparation et prêt à expédier | Transitions sous-commande confirmée → préparation → prête ; appelle désormais l'agrégateur global. Aucune transition transport/livraison V1 n'est encore raccordée. |
| `app/Domains/Orders/Services/OrderSafetyHoldService.php` | Blocage de sécurité et annulation administrative | Distinct de l'annulation client ; libération/restauration Inventory selon décision de sécurité. |\n| `app/Domains/Orders/Services/OrderStatusAggregationService.php` | Agrégation commande globale | Centralise les statuts préparation, expédition partielle/totale et livraison complète ; appelé après décision, annulation client et transition de préparation. |
| `app/Domains/Orders/Http/Controllers/CooperativeOrderController.php` | Inbox et actions coopératives/admin | Scopes par rôle et coopérative ; routes distinctes pour root. |
| `app/Domains/Orders/Http/Controllers/CustomerOrderHistoryController.php` | Historique client | Liste et détail limités au propriétaire ; endpoint d'annulation client ajouté. |
| `app/Domains/Orders/Notifications/CooperativeOrderWorkflowNotification.php` | Notifications workflow | Canal base de données pour notifications coopératives. |
| `app/Domains/Orders/Notifications/CustomerOrderCancelledNotification.php` | Confirmation d'annulation au client | Notification base de données + email en file d'attente. |
| `app/Domains/Orders/Providers/OrdersServiceProvider.php` | Binding contrats et événements | Enregistre le contrat checkout et écoute l'événement Inventory de sécurité. |
| `routes/web.php` | Frontières HTTP | Routes customer protégées par auth, session active, rôle customer et contrôle de compte ; route POST d'annulation ajoutée. |
| `resources/views/customer/orders/show.blade.php` | Présentation client | Formulaire de motif affiché uniquement pour une sous-commande en attente. |
| `tests/Feature/Domains/Orders/CustomerOrderHistoryTest.php` | Isolation client et annulation | Tests d'annulation ajoutés ; exécution locale encore requise. |

### Sales → Orders

| Fichier | Liaison | Résultat |
|---|---|---|
| `app/Domains/Sales/Services/SalesCheckoutService.php` | Consomme `OrderCheckoutCreator`, `OrderCheckoutData`, Inventory, Delivery, Products, Cooperatives et Localization via contrats | Dépendances explicites par interfaces/DTO ; ne crée pas directement de modèles Orders. |
| `app/Domains/Orders/Contracts/OrderCheckoutCreator.php` | Contrat consommé par Sales | Frontière publique présente. |
| `app/Domains/Orders/DTOs/OrderCheckoutData.php` | DTO du checkout | Données transmises au domaine Orders sans exposer ses modèles internes. |
| `app/Domains/Orders/Providers/OrdersServiceProvider.php` | Binding `OrderCheckoutCreator → OrderCheckoutService` | Liaison présente. |
| `app/Domains/Orders/Services/OrderCheckoutService.php` | Adaptateur vers OrderCreationService | Liaison présente. |

### Orders ↔ Inventory

| Fichier | Liaison | Résultat |
|---|---|---|
| `app/Domains/Inventory/Contracts/InventoryReservationManager.php` | API publique de réservation/consommation/libération | Contrat présent ; références Orders sous forme d'identifiants logiques. |
| `app/Domains/Inventory/Services/Reservations/InventoryReservationService.php` | Implémentation de réservation | Ne dépend pas des modèles privés Orders ; verrouille les stocks, contrôle l'idempotence et réserve toutes les lignes ou échoue. |
| `app/Domains/Inventory/Providers/InventoryServiceProvider.php` | Binding du contrat Inventory | Binding présent. |
| `app/Domains/Orders/Services/CooperativeOrderResponseService.php` | Appel Inventory à la confirmation | Orders appelle le contrat Inventory dans sa transaction de confirmation. |
| `app/Domains/Orders/Listeners/HandleInventoryDomainEvent.php` | Consommation d'événement Inventory | Écoute les révocations d'autorisation de quarantaine et demande un blocage de sécurité Orders via contrat partagé. |

### Sales → Delivery

| Fichier | Liaison | Résultat |
|---|---|---|
| `app/Domains/Delivery/Contracts/DeliveryQuoteProvider.php` | Contrat public de devis | Présent. |
| `app/Domains/Delivery/Providers/DeliveryServiceProvider.php` | Binding du devis | Présent. |
| `app/Domains/Sales/Services/SalesCheckoutService.php` | Devis au checkout | Intégration présente ; le devis n'est pas une expédition ni une mission de transport. |

### Finance

| Fichier | Responsabilité | Résultat |
|---|---|---|
| `app/Domains/Finance/README.md` | État du domaine | Domaine documentaire uniquement ; pas de modèle, migration, service, route ou test Finance V1 identifié dans l'arbre audité. |
| `docs/domains/marketplace/FINANCE_V1_SPECIFICATION.md` | Spécification | COD, facturation, commissions, transferts et reversements décrits ; implémentation absente. |
| `docs/domains/marketplace/ORDER_INVOICING_PAYMENTS_DELIVERY_FUNCTIONAL_RULES.md` | Règles financières de référence | Une facture unique par commande globale est en tension avec la numérotation et les droits par coopérative. Arbitrage nécessaire avant schéma. |

## 3. Implémentation ajoutée pour l'annulation client

Fichiers ajoutés/modifiés :
- `app/Domains/Orders/Services/CustomerOrderCancellationService.php`
- `app/Domains/Orders/Notifications/CustomerOrderCancelledNotification.php`
- `app/Domains/Orders/Http/Controllers/CustomerOrderHistoryController.php`
- `routes/web.php`
- `resources/views/customer/orders/show.blade.php`
- `tests/Feature/Domains/Orders/CustomerOrderHistoryTest.php`

Le service verrouille la sous-commande puis la commande globale, dans le même ordre que le flux de décision coopératif, afin de sérialiser une confirmation et une annulation concurrentes. Il vérifie le propriétaire client et le statut encore en attente, annule logiquement les lignes, écrit l'historique, recalcule les montants restants en centimes entiers et notifie le client ainsi que les administrateurs de la coopérative.

## 4. Écarts bloquants avant clôture Orders V1

1. **Transport/livraison :** le domaine Delivery ne pilote pas encore les transitions `SHIPPED` / `DELIVERED`. L'agrégateur est prêt à recalculer ces états dès que les événements de livraison V1 seront raccordés.
2. **Finance V1 absent :** factures et encaissements COD non implémentés.
3. **Facture :** choisir entre facture unique globale avec allocations/sections coopératives, ou facture par sous-commande. Le choix impacte numérotation, permissions, PDF et annulation.
4. **Delivery :** mission transporteur, acceptation, collecte, preuve de remise et événement de livraison non implémentés ; ces transitions ne doivent pas être simulées dans Orders.
5. **Tests :** les tests ajoutés à CustomerOrderHistoryTest doivent être exécutés localement ; ensuite tests Orders ciblés, suite complète et Pint.

## 5. Hors V1 confirmé

- Propositions de réduction/remplacement et réponse client : V2.
- Refus à la livraison et retours : V2.
- Remboursements : V2.


## 6. Règle d'agrégation validée et implémentée

Règle validée par l'utilisateur le 25 novembre 2026 :

- `PREPARING` : au moins une sous-commande est en préparation ou prête à expédier, tant qu'aucune sous-commande active n'est expédiée.
- `PARTIALLY_SHIPPED` : au moins une sous-commande active est expédiée/livrée, mais pas toutes.
- `SHIPPED` : toutes les sous-commandes exécutables sont expédiées ou livrées.
- `COMPLETED` : toutes les sous-commandes exécutables sont livrées.
- Les sous-commandes refusées et annulées sont exclues du calcul de progression logistique.
- L'agrégateur est centralisé dans `OrderStatusAggregationService` et appelé après les décisions coopératives/administratives, l'annulation client et les transitions de préparation.
- Les transitions réelles `SHIPPED` et `DELIVERED` restent à fournir par le domaine Delivery ; aucun faux événement de livraison n'est créé par Orders.

Un test Feature couvre les étapes préparation, expédition partielle, expédition complète et livraison complète. Son exécution locale est requise avant validation finale.
