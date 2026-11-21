# MAHLINE — Conception technique Inventory V1

- **Date :** 21 novembre 2026
- **Branche :** `feature/Marketplace`
- **Référence métier :** `INVENTORY_V1_DECISIONS_2026-11-21.md`
- **Statut :** conception de référence pour l'implémentation ; à ajuster uniquement si un invariant découvert dans le code ou MySQL l'exige.
- **Règle :** aucune migration déjà exécutée n'est modifiée.

## 1. Découpage du domaine

Arborescence cible :

```text
app/Domains/Inventory/
├── Contracts/
├── DTOs/
├── Enums/
├── Exceptions/
├── Models/
├── Policies/
├── Providers/
├── Services/
│   ├── Stock/
│   ├── Reservations/
│   ├── Quarantine/
│   ├── Counts/
│   ├── Alerts/
│   ├── Reports/
│   └── Security/
├── Database/Migrations/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Resources/
└── Console/Commands/
```

Les services restent indépendants de HTTP. Les contrôleurs valident les entrées, construisent des DTO et appellent les services. Les modèles héritent de `BaseModel`. Les services héritent de `BaseService`.

## 2. Tables et propriété

Toutes les tables métier Inventory utilisent ULID, timestamps, acteurs logiques et index explicites. Les références vers les autres domaines sont des ULID/identifiants logiques sans FK croisées, conformément à ADR-021. Les FK physiques sont réservées aux relations internes Inventory.

| Table | Responsabilité |
|---|---|
| `inventory_stocks` | Solde unique coopérative + stockable (produit simple ou variante), unité canonique, verrou d'inventaire actif |
| `inventory_movements` | Grand livre append-only des changements de quantités et soldes avant/après |
| `inventory_reservations` | Une réservation par sous-commande, état, échéance 24 h, clé d'idempotence |
| `inventory_reservation_items` | Quantités réservées par ligne de commande et source (stock normal / quarantaine autorisée) |
| `inventory_quarantines` | Lots de quarantaine, quantité physique restante, inspection, état et motif |
| `inventory_quarantine_authorizations` | Autorisations quantitatives de vente, auteur, motif, durée jusqu'à épuisement/révocation, révocations |
| `inventory_count_sessions` | Session de comptage par coopérative, état, créateur, validation/cancellation |
| `inventory_count_items` | Produit/variante compté, attendu, constaté, écart, motif, validation |
| `inventory_count_participants` | Participants et rôle dans la session |
| `inventory_stock_thresholds` | Seuil configurable par stock |
| `inventory_threshold_histories` | Historique append-only des modifications de seuil |
| `inventory_alert_episodes` | Épisodes low-stock/out-of-stock, réarmement et anti-doublon |
| `inventory_security_reports` | Signalements de sécurité, déclarant, périmètre, urgence, échéance et statut |
| `inventory_security_report_events` | Historique append-only des créations, alertes, escalades, décisions, clôtures et réouvertures |
| `inventory_security_report_attachments` | Références logiques aux médias privés appartenant à Media |
| `inventory_idempotency_records` | Empreinte de requête et résultat immuable par sujet/opération/clé |
| `inventory_outbox_messages` | Événements fiables à publier après commit |

## 3. Comptabilité des quantités

### 3.1 Colonnes de solde

`inventory_stocks` porte les compteurs suivants en `DECIMAL(15,3)` :

- `quantity_on_hand` : quantité physique totale.
- `quantity_reserved` : réservations totales.
- `quantity_reserved_normal` : réservations prélevées sur le stock normal.
- `quantity_reserved_quarantine` : réservations prélevées sur les quantités de quarantaine autorisées.
- `quantity_quarantined` : quantité totale en quarantaine, autorisée ou non.
- `quantity_quarantine_authorized` : sous-ensemble de la quarantaine explicitement autorisé à la vente.
- `active_count_session_id` : session qui verrouille sélectivement le stock, nullable.

Invariants :
- Toutes les quantités sont positives ou nulles.
- `quantity_quarantine_authorized <= quantity_quarantined <= quantity_on_hand`.
- `quantity_reserved = quantity_reserved_normal + quantity_reserved_quarantine`.
- `quantity_reserved_normal <= quantity_on_hand - quantity_quarantined`.
- `quantity_reserved_quarantine <= quantity_quarantine_authorized`.
- `quantity_reserved <= quantity_on_hand - quantity_quarantined + quantity_quarantine_authorized`.

### 3.2 Disponibilité et priorité

- `normal_available = quantity_on_hand - quantity_quarantined - quantity_reserved_normal`.
- `authorized_quarantine_available = quantity_quarantine_authorized - quantity_reserved_quarantine`.
- `available = normal_available + authorized_quarantine_available`.
- Équivalent : `quantity_on_hand - quantity_quarantined + quantity_quarantine_authorized - quantity_reserved`.

Les réservations allouent d'abord `normal_available`, puis `authorized_quarantine_available`. Une réservation conserve ces deux allocations par ligne. Une quantité en quarantaine non autorisée n'est jamais vendable.

L'agrégat est mis à jour sous `SELECT ... FOR UPDATE` dans une transaction. Les contrôles de cohérence sont répétés dans le service avant écriture ; des contraintes SQL sont ajoutées lorsque le moteur MySQL les applique réellement.

## 4. Mouvements

Un mouvement contient :
- stock et coopérative ;
- type métier ;
- deltas séparés : physique, réservé, quarantaine, quarantaine autorisée ;
- valeurs avant/après utiles à l'audit ;
- unité figée ;
- acteur logique (type + ID) ;
- motif obligatoire pour les opérations manuelles/sensibles ;
- références logiques vers réservation, sous-commande, ligne, retour ou inventaire ;
- opération/idempotency key et date effective.

Aucune route UPDATE/DELETE n'existe pour les mouvements. Le modèle refuse les modifications/suppressions hors création. Les corrections sont de nouveaux mouvements compensatoires.

## 5. Réservation et Orders

### 5.1 Pré-évaluation du checkout

Le contrat Inventory expose une évaluation par groupe coopératif et par ligne, sans révéler de quantités au client. Si une seule ligne d'une coopérative est insuffisante, le groupe entier de cette coopérative est exclu ; les autres groupes suffisants restent éligibles.

Le parcours checkout doit afficher les groupes exclus et obtenir l'acceptation du client avant la finalisation. À la finalisation, Inventory revalide sous verrou. Si le résultat diffère du preview accepté, la finalisation échoue sans commande ni réservation ; le client doit revoir le résultat.

### 5.2 Frontière atomique

La création Orders et la réservation Inventory doivent utiliser la même connexion MySQL et la même transaction appelante. L'adaptateur Inventory appelé par Orders ne démarre pas de transaction distribuée indépendante ; il participe à la transaction active. Les deux domaines ne partagent aucun modèle Eloquent.

Ordre d'opérations :
1. résoudre les données produits via `CheckoutCatalog` ;
2. revalider le preview accepté ;
3. créer Order, CooperativeOrders éligibles et OrderItems ;
4. réserver chaque sous-commande par le contrat Inventory ;
5. écrire les historiques/outbox requis ;
6. commit commun.

Toute erreur de réservation annule la création entière. Une indisponibilité détectée au preview est présentée avant finalisation ; aucun sous-ensemble de réservation ne subsiste.

### 5.3 Cycle

- Checkout : réservation active, échéance `reserved_at + 24h`.
- Confirmation coopérative : consommer intégralement la réservation et décrémenter `quantity_on_hand` dans la transaction Orders existante, avant commit du statut confirmé.
- Refus/annulation autorisée avant confirmation : libérer la réservation, écrire les mouvements de libération et clôturer définitivement la sous-commande selon le statut Orders approprié.
- Expiration : commande idempotente qui verrouille la réservation et la sous-commande, libère le stock et demande à Orders de clôturer la sous-commande ; une réservation déjà consommée/libérée n'est pas rejouée.
- Chaque sous-commande expire indépendamment.
- Une sous-commande expirée/refusée/annulée ne peut jamais être réouverte ni réservée une seconde fois.

Orders reste propriétaire des statuts commerciaux. Inventory ne met jamais directement à jour les statuts Orders.

## 6. Quarantaine

Chaque lot est identifié et quantifié. Les inspections sont unitaires par produit retourné. Une autorisation de vente est une entité distincte, limitée à une quantité et à un lot, avec auteur/motif/date. La quantité autorisée est un sous-ensemble de la quantité en quarantaine.

La consommation de stock autorisé décrémente simultanément la quantité physique, la quantité quarantinée, la quantité autorisée, les compteurs de réservation correspondants et écrit le mouvement de vente.

La révocation empêche les nouvelles réservations sur le reliquat mais ne supprime ni ne libère les réservations existantes. Si une commande confirmée doit être arrêtée avant expédition, Inventory publie/appelle le contrat de blocage de sécurité d'Orders ; Orders possède le verrou de fulfillment et doit empêcher toute transition d'expédition tant que le blocage est actif.

Les permissions de mise au rebut restent non exposées tant que le rôle autorisé n'est pas décidé (voir registre des décisions).

## 7. Inventaires physiques

Le verrou sélectif est porté par `inventory_stocks.active_count_session_id`. Une contrainte/transaction empêche deux sessions actives de verrouiller le même stock. Les opérations d'entrée, sortie, réservation et mutation de quarantaine refusent un stock verrouillé.

Une session contient plusieurs éléments. Validation partielle : seuls les éléments validés produisent les mouvements correctifs et libèrent leur verrou. Les autres éléments restent verrouillés tant que la session est active. L'annulation logique libère tous les verrous restants et conserve les données, comptages et participants.

Le comptage porte sur la quantité physique totale. La validation est refusée si le résultat rendrait impossibles les réservations ou les quantités de quarantaine déjà engagées.

## 8. Alertes et notifications

Les seuils sont historisés. Les épisodes low-stock et out-of-stock sont distincts. Un épisode ne génère pas de doublon ; il se réarme lorsque le disponible repasse au-dessus du seuil (low stock) ou redevient positif (rupture).

Inventory émet des messages Outbox. Un adaptateur de notification in-app résout les destinataires via un contrat Identity public ; aucun modèle User n'est importé par Inventory. Les messages V1 sont internes à MAHLINE, sans email/SMS.

## 9. Signalements de sécurité

Inventory conserve le dossier et son historique. Le déclarant `user` peut créer le signalement et consulter son propre suivi ; les détails sont accessibles aux responsables de la coopérative et au root dans leur périmètre. Les preuves privées sont référencées via Media, qui reste propriétaire des fichiers et de leur pipeline antivirus.

Un signalement urgent déclenche une demande immédiate de hold auprès d'Orders via un contrat public. Orders conserve un enregistrement de blocage de fulfillment indépendant du statut commercial. Le blocage ne peut être levé que par un responsable autorisé après décision explicite. Le délai de deux heures déclenche les escalades ; le délai dépassé ne lève jamais le blocage.

Les événements de sécurité sont append-only. Les détails de reprise de commande après expédition et les ajouts du déclarant sont hors V1.

## 10. Contrats publics

### Inventory fournit

- `InventoryCheckoutAvailability` : évaluer les groupes coopératifs et lignes au checkout.
- `InventoryReservationManager` : réserver, consommer, libérer et expirer une réservation.
- `InventoryAvailabilityReader` : retourner uniquement l'état de disponibilité autorisé.
- `InventoryReturnIntake` : recevoir un résultat d'inspection de retour.
- `InventoryStockQuery` : lecture administrative dans le périmètre.
- `InventorySecurityReporter` : créer/consulter/traiter les signalements via DTO.

### Inventory consomme

- `CheckoutCatalog` (Products) pour identité, variante, unité et données produit autoritatives.
- `ActorContextProvider`, `ActorAuthorizer`, `SuperAdminCooperativeAssignmentReader` (Shared/Identity) pour acteur, permissions et périmètre.
- `AuditWriter` pour l'audit transversal.
- Un contrat public Media pour attacher des fichiers privés sans importer les modèles Media.
- Un contrat public Orders pour les holds de fulfillment et la clôture/expiration commerciale des sous-commandes.
- Un contrat public Identity pour résoudre les destinataires des notifications in-app.

Les contrats doivent utiliser des DTO immuables et des identifiants logiques, jamais des modèles Eloquent d'un autre domaine.

## 11. Composition et exécution

- `InventoryServiceProvider` lie les contrats aux services/adaptateurs.
- Le provider charge explicitement `Database/Migrations` via `loadMigrationsFrom`.
- Le provider est enregistré dans `bootstrap/providers.php`.
- Commandes planifiées : expiration des réservations, publication Outbox, alertes/escalades de sécurité et détection d'épisodes.
- Les tâches doivent être idempotentes, batchées et protégées contre l'exécution concurrente.
- Les routes web/API utilisent Form Requests, Policies, Resources et noms `inventory.*`.
- Les exports utilisent Dompdf déjà présent dans `composer.json`, PDF uniquement en V1.

## 12. Stratégie de tests

- Unit : calculs décimaux, allocation normal/quarantaine, invariants de quantité et machines d'état.
- Feature : droits et périmètres des cinq rôles, routes, rapports, signalements, comptage.
- Integration : Orders checkout → Inventory reservation ; confirmation → consommation ; refus/annulation/expiration → libération ; Delivery return → quarantaine.
- Concurrence MySQL : deux checkouts sur le même stock, double confirmation, double expiration, opérations répétées, deux sessions d'inventaire concurrentes.
- Architecture : absence d'import Eloquent inter-domaines et interdiction d'écrire directement dans les tables Inventory.
- Pint et suite complète `php artisan test`.

Aucun test ne sera déclaré vert sans exécution réelle. Le domaine ne sera pas marqué terminé avant l'exécution locale/CI de la suite complète et la correction des régressions.
