# MAHLINE V1 — Audit et implémentation des notifications Customer

> Branche : `feature/Marketplace`
> Audit de référence : 26 novembre 2026
> Périmètre : Orders, Sales, Delivery, Finance, Identity, Inventory et Notifications.

## 1. Résultat de l'implémentation

### Lot A — Cycle de vie Orders

Les notifications Customer sont désormais émises pour les transitions réellement présentes :

| Événement | Déclencheur | Destinataire | Canaux | Destination |
|---|---|---|---|---|
| Commande créée | Création effective dans `OrderCreationService`, après commit | `orders.customer_id` | Database + Mail | `customer.orders.show` |
| Commande validée | Agrégation globale vers `OrderStatus::CONFIRMED` | `orders.customer_id` | Database + Mail | `customer.orders.show` |
| Commande en préparation | Agrégation globale vers `OrderStatus::PREPARING` | `orders.customer_id` | Database + Mail | `customer.orders.show` |
| Sous-commande annulée | Annulation client d'une sous-commande encore en attente | Client propriétaire de la commande | Database + Mail | `customer.orders.show` |

Les trois événements de cycle de vie utilisent `CustomerOrderLifecycleNotification`. La notification est mise en file d'attente après commit et expose le lien interne vers `customer.orders.show`. La cloche et la liste des notifications affichent maintenant le message et proposent ce lien de destination. La création ne notifie pas lors de la relecture d'une commande existante par clé d'idempotence. Les transitions CONFIRMED/PREPARING ne notifient que lorsque le statut agrégé change.

L'annulation conserve `CustomerOrderCancelledNotification` et transmet maintenant le motif obligatoire dans le contenu Database et Mail. Le destinataire est vérifié par le service contre `orders.customer_id`.

**Attention au vocabulaire :** `READY_FOR_SHIPMENT` signifie « prête pour expédition », pas « expédiée ». L'événement technique existant `order.ready_for_shipment` reste une notification Database distincte et ne remplace pas l'événement métier `order.shipped`.

### Lot B — Delivery / commande expédiée

Non implémenté : le domaine Delivery contient actuellement le contrat de devis et le fournisseur de tarif initial, mais pas encore de mission transporteur, d'affectation, d'expédition ni de transition métier SHIPPED. Aucun faux déclencheur n'a été ajouté.

Le futur déclencheur doit être l'enregistrement atomique de l'expédition réelle par Delivery, puis publier un événement métier consommé par Orders/Notifications. Il devra notifier le propriétaire de la commande, en Database + Mail, avec le lien `customer.orders.show`. Il ne doit jamais être déclenché par `markReadyForShipment`.

### Lot C — Inventory / Identity : destinataires et isolation

Audit des notifications existantes :

- Les alertes de stock Inventory sont adressées aux rôles opérationnels `cooperative_admin` et `user` de la coopérative, ainsi qu'aux `super_admin` affectés. Elles utilisent le canal Database.
- Les rapports de sécurité Inventory sont adressés au `super_admin_root`, au `cooperative_admin` de la coopérative et aux `super_admin` affectés. Ils utilisent le canal Database.
- Les événements Identity de vérification/changement d'adresse e-mail, connexion depuis un nouvel appareil et réinitialisation du mot de passe utilisent le canal Mail. Ils ne doivent pas être transformés en notifications de cloche sans décision fonctionnelle, notamment pour ne jamais exposer un lien de vérification ou de réinitialisation dans le centre Database.
- La révocation d'un appareil de confiance utilise déjà Database + Mail.
- Les notifications Inventory ne ciblent pas le rôle `customer`. Aucun envoi global à tous les utilisateurs n'a été ajouté.

Conclusion du lot C : aucun changement de routage n'est nécessaire pour les événements Inventory audités ; leur périmètre est opérationnel et l'isolation par coopérative/affectation est conservée. Les notifications de sécurité Identity restent distinctes des notifications commerciales Customer.

### Lot D — Finance

Non implémenté : Finance est actuellement un domaine documentaire, sans modèles, migrations, services, routes ou événements fonctionnels de facturation/paiement. Aucun événement financier réel ne peut donc déclencher une notification.

À l'implémentation de Finance, les événements factuels (facture émise, paiement encaissé, remboursement, reversement) devront être définis avec leur propriétaire, destinataire, canal, idempotence et destination Customer avant d'ajouter les notifications. Ne pas déduire un paiement encaissé de la simple création ou validation d'une commande.

## 2. Inventaire des autres émetteurs et tâches planifiées

- Sales ne possède pas de notification métier distincte dans le flux checkout ; il délègue la création à Orders. La notification `order.created` est donc centralisée dans `OrderCreationService`, sans double envoi depuis Sales.
- `CooperativeOrderWorkflowNotification` sert aux acteurs opérationnels (coopérative / administrateurs) et n'est pas une notification Customer.
- `OrderReadyForShipmentNotification` est déclenchée à la transition `READY_FOR_SHIPMENT`, et utilise Database seulement.
- Les tâches planifiées identifiées comprennent `orders:escalate-overdue-cooperative-orders` (chaque minute), `inventory:publish-outbox` (chaque minute), `inventory:reconcile-alerts` (toutes les cinq minutes) et `inventory:escalate-security-reports` (toutes les cinq minutes). Elles alimentent les workflows d'escalade/outbox opérationnels ; elles ne constituent pas un événement d'expédition ni un événement Finance.

## 3. Règles transversales

1. Toute notification commerciale Customer vise exclusivement le propriétaire `orders.customer_id`.
2. Les notifications de commande contiennent la référence et l'identifiant de commande et pointent vers `customer.orders.show`.
3. Les notifications doivent être émises après commit.
4. Les opérations rejouées ou les transitions déjà appliquées ne doivent pas produire de doublon.
5. Les canaux V1 validés pour les cinq événements de commande sont Database + Mail ; les SMS restent hors périmètre.
6. Une notification « prête pour expédition » n'est jamais assimilée à « expédiée ».

## 4. Tests Feature à exécuter localement

- `php artisan test tests/Feature/Domains/Orders/Services/OrderCreationServiceTest.php`
- `php artisan test tests/Feature/Domains/Orders/CustomerOrderHistoryTest.php`
- `php artisan test tests/Feature/Domains/Orders/Services/CooperativeOrderResponseServiceTest.php`
- `php artisan test tests/Feature/Domains/Inventory/InventoryNotificationRoutingTest.php`
- `php artisan test tests/Feature/Domains/Inventory`
- `php artisan test tests/Feature/Domains/Identity`
- `php vendor/bin/pint --test`
- `php artisan test`

Les lots B et D restent ouverts jusqu'à l'existence des transitions Delivery et des services Finance correspondants, puis jusqu'à l'ajout et à la validation de leurs tests Feature.
