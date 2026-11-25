# MAHLINE V1 — Localization / Carnet d'adresses

- **Version :** V1
- **Mise à jour :** 25 novembre 2026
- **Dépôt :** `areda10/mahline_v1`
- **Branche :** `feature/Marketplace`
- **Statut :** implémentation écrite ; tests d'exécution à réaliser dans l'environnement local

## 1. Modèle de propriété

Une adresse appartient exclusivement à un utilisateur OU à une coopérative.

- `addresses.user_id` référence `users.id`.
- `addresses.cooperative_id` référence `cooperatives.id`.
- Une contrainte SQL impose exactement un propriétaire non nul.
- `cooperatives.address_id` est supprimé : la relation est portée uniquement par `addresses.cooperative_id`.
- Les relations applicatives sont `User::addresses()`, `User::primaryAddress()` et `Cooperative::address()`.

## 2. Données V1

Champs d'adresse : `number`, `street`, `floor`, `postal_code`, `city`, `region`, `country_id`, `latitude`, `longitude`.

- Le Maroc (`MA`) est le seul pays autorisé par les services d'écriture V1.
- Les douze régions sont centralisées dans `MoroccanRegion`.
- Les anciens champs `address_line_1`, `address_line_2` et `state` sont supprimés.
- Le pays est défini par le service ; le client ne peut pas choisir un autre pays.

## 3. Limites et adresse principale

| Propriétaire | Adresses actives maximales |
|---|---:|
| Client (`customer`) | 5 |
| `user`, `cooperative_admin`, `super_admin`, `super_admin_root` | 1 |
| Coopérative | 1 |

- La première adresse active est principale.
- Les suivantes ne remplacent pas automatiquement l'adresse principale.
- Le changement d'adresse principale est transactionnel.
- Une seule adresse principale active est garantie en base par propriétaire grâce aux index uniques conditionnels matérialisés.
- La limite de volume est vérifiée dans `AddressService` sous verrou de ligne du propriétaire.

## 4. Suppression et restauration

### Suppression volontaire

- La suppression est logique (`deleted_at`), jamais physique.
- L'adresse supprimée est marquée `deletion_source=voluntary`.
- Elle disparaît du carnet et libère immédiatement une place.
- Si elle était principale, la plus ancienne adresse active restante devient principale.
- Une adresse volontairement supprimée n'est jamais restaurée automatiquement.

### Suppression du propriétaire

- La suppression logique d'un utilisateur ou d'une coopérative supprime logiquement toutes ses adresses actives dans la même transaction.
- Les adresses sont marquées `deletion_source=owner` et reçoivent un `owner_deletion_batch_id`.
- La restauration du propriétaire ne restaure que les adresses du dernier lot de suppression de ce propriétaire.
- La restauration est atomique et vérifie à nouveau la limite d'adresses actives.
- Les adresses supprimées volontairement restent supprimées.
- La restauration d'un compte utilisateur le remet en attente ; les sessions et tokens restent révoqués selon les règles Identity.

## 5. Intégrité SQL

La migration d'initialisation crée le modèle V1 directement, car le dépôt n'a jamais été déployé.

- Clés étrangères vers `users`, `cooperatives` et `countries`, avec suppression physique restrictive.
- Contrôle SQL `addresses_exactly_one_owner_check` (MySQL).
- Index unique d'une adresse principale active par utilisateur.
- Index unique d'une adresse principale active par coopérative.
- Index unique d'une adresse active par coopérative.
- Aucun index unique global sur `user_id` : un client peut posséder cinq adresses.

## 6. Services et interfaces

- `AddressService` : création, mise à jour de l'adresse principale ou d'une adresse ciblée par identifiant, changement de principale, suppression volontaire, suppression/restauration avec le propriétaire.
- `UserManagementService` : déclenche la suppression/restauration du carnet avec le cycle de vie du compte.
- `CooperativeService` : déclenche la suppression/restauration de l'adresse avec le cycle de vie de la coopérative.
- `CustomerProfileController` : carnet client, ajout, édition individuelle, choix de principale et suppression. La route `PATCH customer/profile/addresses/{address}` délègue la modification ciblée à Localization.
- `PersonalAddressController` : adresse personnelle des comptes internes.
- Les contrôleurs de coopérative ne reçoivent plus d'`address_id` et passent par `AddressService`.

## 7. Intégration Checkout / Orders constatée au 25 novembre 2026

Le flux Checkout / Orders est désormais présent dans le code :

- `CheckoutAddressSnapshotService::snapshotForCustomer()` vérifie que l'adresse est active, appartient au client et possède les champs minimaux de livraison.
- Le contrat `CheckoutAddressReader` et le DTO `CheckoutAddressSnapshot` exposent le snapshot à Sales sans transmettre le modèle Eloquent Address.
- Sales liste les adresses actives du client au checkout et exige un `delivery_address_id` à la confirmation.
- `OrderCheckoutData` transmet le snapshot à Orders ; `OrderCreationService` le persiste dans `orders.delivery_address_snapshot`.
- Le snapshot est une copie des données d'adresse de la commande. Une modification ou suppression ultérieure du carnet ne doit pas réécrire l'historique de commande.

### Ajout d'adresse depuis le checkout

Le formulaire de checkout utilise la route Customer d'ajout d'adresse. L'adresse est bien enregistrée dans le carnet.

**Écart restant :** après l'ajout, l'interface recharge l'aperçu et présélectionne l'adresse principale. Si une adresse principale existait déjà, la nouvelle adresse (secondaire) n'est pas automatiquement sélectionnée. La décision fonctionnelle validée exige que l'adresse nouvellement ajoutée soit utilisée pour la commande en cours. Le contrôleur doit retourner l'identifiant créé et l'interface doit le sélectionner explicitement ; il ne faut pas changer l'adresse principale du carnet automatiquement.

### Limites d'édition du carnet

L'interface Customer permet d'ajouter, de définir comme principale et de supprimer une adresse. Le formulaire de profil met à jour l'adresse principale / la plus ancienne. Aucun flux d'édition individuelle d'une adresse secondaire n'a été identifié au 25 novembre 2026 ; confirmer si cette capacité fait partie du périmètre V1.

## 8. Validation

Les tests de framework couvrent notamment les limites d'adresses Customer (cinq), la suppression logique, la libération d'une place, la promotion de l'adresse la plus ancienne, la restauration et les règles d'autorisation.

Les tests Checkout / Orders cités dans le compte rendu du 25 novembre 2026 ont été exécutés localement par l'utilisateur et déclarés verts. En revanche, aucun résultat local n'a été communiqué pour `CustomerProfileTest`, `CustomerEmailChangeTest` ou les tests de framework Localization. Ces tests restent donc **à exécuter / confirmer localement** avant de déclarer le carnet d'adresses validé V1.


## 8. Correctif carnet Customer — 25 novembre 2026

L'édition individuelle des adresses Customer est désormais exposée dans l'interface et la couche applicative :

- Chaque adresse active du carnet affiche son propre formulaire de modification.
- La route `customer.profile.addresses.update` accepte une requête PATCH et valide les champs d'adresse.
- Le contrat `UserAddressLifecycleManager` expose `updateAddressForUser(actorId, ownerId, addressId, attributes)`.
- `AddressService` verrouille le propriétaire puis l'adresse, exige que l'adresse appartienne à ce propriétaire, délègue à la validation centralisée et écrit l'événement d'audit `localization.address.updated`.
- Une adresse secondaire peut être modifiée sans devenir principale. Une adresse d'un autre client est introuvable dans le périmètre du propriétaire et ne peut pas être modifiée.

Les tests Feature ajoutés sont à exécuter localement ; leur présence dans le dépôt ne vaut pas résultat vert.
