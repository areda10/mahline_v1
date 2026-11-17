# MAHLINE V1 — Localization / Carnet d'adresses

- **Version :** V1
- **Mise à jour :** 17 novembre 2026
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

- `AddressService` : création, mise à jour, changement de principale, suppression volontaire, suppression/restauration avec le propriétaire.
- `UserManagementService` : déclenche la suppression/restauration du carnet avec le cycle de vie du compte.
- `CooperativeService` : déclenche la suppression/restauration de l'adresse avec le cycle de vie de la coopérative.
- `CustomerProfileController` : carnet client, ajout, choix de principale et suppression.
- `PersonalAddressController` : adresse personnelle des comptes internes.
- Les contrôleurs de coopérative ne reçoivent plus d'`address_id` et passent par `AddressService`.

## 7. Historique commercial futur

Les domaines Orders, Finance/Facturation et Delivery ne sont pas encore présents. Lors de leur implémentation, chaque commande, facture ou livraison devra conserver un snapshot d'adresse immuable, indépendant du carnet courant du client ou de la coopérative.

## 8. Validation

Les tests ont été adaptés pour couvrir les relations, les limites, l'adresse principale, la suppression logique, les lots de restauration et les contraintes SQL. Ils restent **à exécuter localement** ; aucun résultat vert n'est déclaré dans ce document.
