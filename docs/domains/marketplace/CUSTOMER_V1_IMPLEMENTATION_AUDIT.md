# MAHLINE V1 — Audit d'implémentation Customer (profil et adresses)

- **Date de revue :** 25 novembre 2026
- **Dépôt :** `areda10/mahline_v1`
- **Branche auditée :** `feature/Marketplace`
- **Référence auditée :** `8dfd554c60f17f93cc09904ce9b51b306210ad2d`
- **Périmètre :** profil Customer, carnet d'adresses, changement d'e-mail et liaison avec le checkout Sales / snapshot Orders.
- **Nature :** revue statique des fichiers du dépôt. Les résultats de tests ci-dessous sont ceux communiqués par l'utilisateur et exécutés localement.

## 1. Synthèse

Le profil Customer n'est pas à créer de zéro. Les écrans, routes, contrôleurs, services, modèle d'adresse, politiques, notification de changement d'e-mail et tests existent déjà.

| Fonction | État constaté dans le code |
|---|---|
| Consulter le profil | Implémenté |
| Modifier prénom, nom, téléphone | Implémenté |
| Changer l'e-mail avec confirmation par lien | Implémenté |
| Vérifier le mot de passe actuel avant demande de changement d'e-mail | Implémenté |
| Révoquer sessions et jetons après changement d'e-mail | Implémenté |
| Ajouter des adresses au carnet | Implémenté |
| Limiter le carnet à 5 adresses actives | Implémenté dans Localization |
| Définir une adresse principale | Implémenté |
| Suppression logique et promotion d'une adresse restante | Implémenté dans Localization |
| Choisir une adresse existante au checkout | Implémenté |
| Ajouter une adresse depuis le checkout et l'enregistrer | Implémenté |
| Sélectionner automatiquement au checkout l'adresse nouvellement ajoutée | **Écart** : le checkout recharge la liste et présélectionne l'adresse principale ; une nouvelle adresse secondaire n'est pas automatiquement sélectionnée |
| Modifier une adresse secondaire depuis le carnet | **Non implémenté** : seul le formulaire du profil met à jour l'adresse principale / la plus ancienne |
| Photo de profil Customer | **Non implémentée dans l'interface Customer** ; le modèle User expose un lien Media, mais aucun flux upload Customer n'a été identifié dans ce périmètre |
| Suite de tests Customer exécutée localement | **Non confirmée** par les résultats transmis pour cet audit |

## 2. Revue fichier par fichier

### Identity / Customer

| Fichier | Responsabilité constatée | État / observation |
|---|---|---|
| `app/Domains/Identity/Customer/Http/Controllers/CustomerProfileController.php` | Affiche le profil, met à jour les données, ajoute / définit / supprime les adresses et initie le changement d'e-mail | Implémenté. Les actions carnet sont déléguées au contrat Localization. |
| `app/Domains/Identity/Customer/Http/Requests/UpdateCustomerProfileRequest.php` | Valide les champs du profil et l'adresse affichée avec le profil | Écart UX/fonctionnel : rue, ville et région sont obligatoires dans le même PUT que prénom/nom/téléphone. Un client sans adresse ne peut donc pas modifier uniquement ses informations personnelles. |
| `app/Domains/Identity/Customer/Http/Requests/RequestCustomerEmailChange.php` | Autorise uniquement un compte Customer et valide nouvel e-mail / mot de passe | Implémenté. |
| `app/Domains/Identity/Customer/Services/CustomerProfileService.php` | Met à jour prénom, nom, display_name et téléphone sous verrou ; écrit un événement d'audit | Implémenté. Refuse les comptes non Customer, les comptes avec rôle interne, inactifs ou non vérifiés. |
| `app/Domains/Identity/Customer/Services/CustomerEmailChangeService.php` | Demande et confirme le changement d'e-mail | Implémenté : mot de passe actuel, jeton aléatoire stocké sous forme hashée, expiration 30 minutes, usage unique, vérification d'unicité, révocation des sessions et jetons, audit et notification de l'ancienne adresse. |
| `app/Domains/Identity/Customer/Models/CustomerEmailChangeRequest.php` | Persiste la demande de changement d'e-mail | Implémenté. |
| `app/Domains/Identity/Customer/Notifications/CustomerEmailChangeNotification.php` | Envoie le lien de confirmation à la nouvelle adresse | Implémenté. |
| `app/Domains/Identity/Customer/Notifications/CustomerEmailChangedNotification.php` | Informe l'ancienne adresse après validation | Implémenté. |
| `resources/views/customer/profile/edit.blade.php` | Interface profil, adresse principale, carnet, ajout et changement d'e-mail | Présent. Pas d'édition individuelle des adresses secondaires ni d'upload de photo. Le message de succès est aussi rendu par le layout, ce qui peut afficher deux fois le même message. |
| `resources/views/layouts/customer.blade.php` | Navigation Customer vers boutique, panier, commandes, profil, sécurité et appareils | Présent. Le lien profil est accessible depuis la navigation Customer. |
| `routes/web.php` | Déclare les routes Customer profile et les protège par auth, session active, rôle Customer et middleware de compte Customer | Présent. Routes : `customer.profile.edit`, `customer.profile.update`, `customer.profile.addresses.store`, `customer.profile.addresses.primary`, `customer.profile.addresses.destroy`, `customer.profile.email.change`. |
| `app/Domains/Identity/Customer/Http/Middleware/EnsureCustomerAccount.php` | Empêche les comptes ayant des rôles internes d'utiliser l'espace Customer | Présent et appliqué au groupe de routes du profil. |
| `tests/Feature/Domains/Identity/Customer/CustomerProfileTest.php` | Consultation, mise à jour, protection des champs sensibles, séparation des rôles, ajout / principale / suppression logique | Présent. Ne prouve pas à lui seul la couverture complète des limites et de la concurrence. |
| `tests/Feature/Domains/Identity/Customer/CustomerEmailChangeTest.php` | Mot de passe incorrect, e-mail déjà utilisé, révocation des demandes précédentes, expiration, sessions/tokens et audit | Présent. Exécution locale non communiquée pour cet audit. |
| `tests/Feature/Domains/Identity/Customer/CustomerAuthenticationIsolationTest.php` | Séparation des parcours d'authentification, sécurité et déconnexion Customer | Présent. |

### Localization / carnet d'adresses

| Fichier | Responsabilité constatée | État / observation |
|---|---|---|
| `app/Domains/Localization/Models/Address.php` | Modèle d'adresse avec propriétaire User ou coopérative, pays, adresse principale et SoftDeletes | Implémenté. |
| `app/Domains/Identity/Users/Models/User.php` | Relations `addresses()` et `primaryAddress()` | Implémenté. |
| `app/Domains/Localization/Contracts/UserAddressLifecycleManager.php` | Contrat public de cycle de vie des adresses User | Implémenté. |
| `app/Domains/Localization/Contracts/UserAddressOwnerGateway.php` | Frontière vers Identity pour verrouiller / autoriser le propriétaire | Implémenté. |
| `app/Domains/Identity/Users/Services/IdentityUserAddressOwnerGateway.php` | Adaptateur Identity et application de AddressPolicy | Implémenté. |
| `app/Domains/Localization/Services/AddressService.php` | Création, mise à jour, principale, suppression logique, restauration et audit | Implémenté. La création verrouille le propriétaire et applique la limite Customer de cinq adresses actives. |
| `app/Domains/Localization/Policies/AddressPolicy.php` | Autorisation de gestion d'une adresse personnelle | Implémenté ; les tests de policy couvrent les rôles et la propriété. |
| `app/Domains/Localization/Services/CheckoutAddressSnapshotService.php` | Vérifie l'appartenance et l'activité de l'adresse, puis construit le DTO de snapshot | Implémenté. Refuse une adresse qui n'appartient pas au Customer ou qui est incomplète / indisponible. |
| `app/Domains/Localization/Providers/LocalizationServiceProvider.php` | Lie les contrats d'adresse à AddressService et CheckoutAddressSnapshotService | Implémenté. |
| `docs/domains/localization/ADDRESS_BOOK_V1.md` | Règles V1 du carnet et de son cycle de vie | Existe ; doit être actualisé pour refléter le snapshot Checkout/Orders déjà présent et l'ajout depuis le checkout. |
| `tests/Framework/Domains/Localization/Services/AddressServiceTest.php` | Limite 5, suppression logique, libération d'une place, promotion, restauration et audit | Présent. Résultat local non communiqué pour cette exécution. |
| `tests/Framework/Domains/Localization/Policies/AddressPolicyTest.php` | Règles d'autorisation d'adresse | Présent. |
| `tests/Framework/Domains/Localization/Services/AddressServiceBoundaryTest.php` | Contrôle de frontière entre domaines | Présent. |

### Sales / Orders — liaison avec l'adresse de livraison

| Fichier | Responsabilité constatée | État / observation |
|---|---|---|
| `app/Domains/Sales/Http/Controllers/SalesCartController.php` | Fournit au checkout la liste des adresses Customer actives ; exige un `delivery_address_id` à la confirmation | Implémenté. Les adresses sont filtrées par propriétaire et pays actif, avec la principale en premier. |
| `resources/views/storefront/checkout.blade.php` | Choix d'une adresse existante et formulaire d'ajout d'une adresse | Présent. Le formulaire poste vers `customer.profile.addresses.store`, puis recharge l'aperçu. Il ne conserve pas l'identifiant de la nouvelle adresse pour la sélectionner explicitement. |
| `app/Domains/Sales/Services/SalesCheckoutService.php` | Demande le snapshot Localization avant création de commande | Implémenté. |
| `app/Domains/Localization/Contracts/CheckoutAddressReader.php` | Contrat de lecture / snapshot pour Sales | Implémenté. |
| `app/Domains/Localization/DTOs/CheckoutAddressSnapshot.php` | Transporte les champs du snapshot sans exposer le modèle Address | Implémenté. |
| `app/Domains/Orders/DTOs/OrderCheckoutData.php` | Transporte le snapshot d'adresse à Orders | Implémenté. |
| `app/Domains/Orders/Services/OrderCreationService.php` | Persiste le snapshot dans `orders.delivery_address_snapshot` | Implémenté. La commande conserve une copie des données ; elle ne dépend pas de la lecture ultérieure du carnet courant. |
| `tests/Feature/Domains/Sales/Http/SalesCustomerCheckoutFlowsTest.php` | Parcours checkout panier et commande directe | Présent ; résultat vert communiqué par l'utilisateur. Le test transmis ne vérifie pas le cas précis « ajouter une adresse alors qu'une autre est principale, puis confirmer avec la nouvelle ». |

## 3. Écarts et tâches V1 à traiter

### P0 — Corriger avant de considérer le profil V1 terminé

1. **Découpler la mise à jour du profil de l'adresse.** Le PUT du profil ne doit pas exiger une rue, une ville et une région pour modifier seulement prénom, nom ou téléphone. Séparer les données personnelles et la mise à jour d'adresse, ou rendre l'adresse conditionnelle sans perdre la validation stricte lorsqu'elle est réellement soumise.
2. **Respecter la décision Customer validée pour le checkout (option A).** Une adresse ajoutée depuis le checkout doit être enregistrée dans le carnet ET devenir l'adresse sélectionnée pour la commande en cours. Le serveur doit retourner l'identifiant de l'adresse créée et le client doit le sélectionner après le rafraîchissement de l'aperçu. Ne pas changer automatiquement l'adresse principale du carnet si ce n'est pas demandé.
3. **Ajouter les tests HTTP de ces deux comportements.** Inclure un scénario avec adresse principale existante, ajout d'une seconde adresse depuis le checkout, sélection de cette seconde adresse, confirmation et vérification du snapshot Orders.

### P1 — Fonctionnalités à arbitrer / compléter

4. **Édition des adresses secondaires.** Aucun bouton ni endpoint d'édition individuelle n'a été identifié. Le formulaire actuel ne met à jour que l'adresse principale / la plus ancienne. Confirmer si l'édition de chacune des cinq adresses fait partie du V1 ; si oui, ajouter route, validation, autorisation par propriétaire et tests.
5. **Photo de profil.** Aucun contrôle d'affichage ou d'upload Customer n'a été identifié dans l'écran. Le modèle User possède une relation `profileImage()` vers Media, mais cela ne prouve pas que le flux Customer soit implémenté. À classer selon le périmètre fonctionnel V1 confirmé.
6. **Nettoyage UI.** Éviter le double affichage du message de succès par le layout et la vue profil.
7. **Robustesse et tests d'intégration.** Couvrir l'adresse d'un autre client, l'accès par compte multi-rôle, compte inactif / non vérifié, dépassement des cinq adresses par HTTP, ajout concurrent, suppression de l'adresse principale, restauration et adresse supprimée utilisée au checkout.

## 4. Règles fonctionnelles confirmées à appliquer

- Seul le rôle `customer` utilise ce profil et le parcours d'achat Customer.
- Un Customer peut conserver au maximum cinq adresses actives.
- La première adresse active devient principale ; les suivantes ne remplacent pas automatiquement l'adresse principale.
- La suppression d'une adresse est logique et libère une place.
- Si l'adresse principale est supprimée, la plus ancienne adresse active restante est promue.
- Au checkout, le Customer choisit une adresse existante ou en ajoute une nouvelle.
- Décision validée par l'utilisateur : une adresse créée depuis le checkout est enregistrée dans le carnet et doit être utilisée pour la commande en cours.
- L'adresse de livraison est validée par Localization puis copiée dans `orders.delivery_address_snapshot`. La modification ultérieure du carnet ne doit pas réécrire ce snapshot.
- Une commande V1 requiert une adresse de livraison ; le checkout ne doit pas accepter une adresse d'un autre propriétaire ni une adresse supprimée.

## 5. Résultats de tests locaux communiqués

Les commandes ci-dessous ont été exécutées localement par l'utilisateur et déclarées vertes :

- `php artisan test tests/Feature/Domains/Sales/Services/SalesCheckoutServiceTest.php`
- `php artisan test tests/Feature/Domains/Orders/Services/OrderCreationServiceTest.php`
- `php artisan test tests/Feature/Domains/Sales/Http/SalesCustomerCheckoutFlowsTest.php`
- `php artisan test tests/Feature/Domains/Orders/Services/CooperativeOrderResponseServiceTest.php`
- `php artisan test tests/Feature/Domains/Orders/CooperativeOrderControllerTest.php`
- `php artisan test tests/Feature/Domains/Orders/CustomerOrderHistoryTest.php`

Les commandes répétées dans le compte rendu ont été regroupées ici.

**Non confirmés par le compte rendu transmis :**
- `php artisan test tests/Feature/Domains/Identity/Customer/CustomerProfileTest.php`
- `php artisan test tests/Feature/Domains/Identity/Customer/CustomerEmailChangeTest.php`
- `php artisan test tests/Framework/Domains/Localization/Services/AddressServiceTest.php`
- `php artisan test tests/Framework/Domains/Localization/Policies/AddressPolicyTest.php`
- `php vendor/bin/pint --test`
- Suite complète `php artisan test`

Aucun test n'est déclaré exécuté par l'assistant. Le profil Customer et le carnet ne doivent pas être déclarés validés V1 avant exécution locale des tests ciblés et correction des écarts P0.
