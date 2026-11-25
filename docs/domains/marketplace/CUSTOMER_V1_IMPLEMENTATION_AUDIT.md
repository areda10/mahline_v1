# MAHLINE V1 — Audit d'implémentation Customer (profil et adresses)

- **Date de revue :** 25 novembre 2026
- **Dépôt :** `areda10/mahline_v1`
- **Branche auditée :** `feature/Marketplace`
- **Référence audit initiale :** `8dfd554c60f17f93cc09904ce9b51b306210ad2d`
- **Correctifs code ajoutés :** `ab849e46d89eca65288320fa4fa691e3d698b5dc`
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
| Sélectionner automatiquement au checkout l'adresse nouvellement ajoutée | **Corrigé dans le code** : le endpoint JSON renvoie l'identifiant créé et le checkout le présélectionne après rechargement, sans changer l'adresse principale |
| Modifier une adresse secondaire depuis le carnet | **Corrigé dans le code** : formulaire individuel, route PATCH et service Localization ciblé par propriétaire/adresse, avec audit |
| Photo de profil Customer | **Non implémentée dans l'interface Customer** ; le modèle User expose un lien Media, mais aucun flux upload Customer n'a été identifié dans ce périmètre |
| Suite de tests Customer exécutée localement | **Non confirmée** par les résultats transmis pour cet audit |

## 2. Revue fichier par fichier

### Identity / Customer

| Fichier | Responsabilité constatée | État / observation |
|---|---|---|
| `app/Domains/Identity/Customer/Http/Controllers/CustomerProfileController.php` | Affiche le profil, met à jour les données, ajoute / modifie / définit / supprime les adresses et initie le changement d'e-mail | Implémenté. Les actions carnet sont déléguées au contrat Localization ; l'ajout JSON renvoie l'identifiant créé. |
| `app/Domains/Identity/Customer/Http/Requests/UpdateCustomerProfileRequest.php` | Valide les champs du profil et l'adresse affichée avec le profil | Écart UX/fonctionnel : rue, ville et région sont obligatoires dans le même PUT que prénom/nom/téléphone. Un client sans adresse ne peut donc pas modifier uniquement ses informations personnelles. |
| `app/Domains/Identity/Customer/Http/Requests/RequestCustomerEmailChange.php` | Autorise uniquement un compte Customer et valide nouvel e-mail / mot de passe | Implémenté. |
| `app/Domains/Identity/Customer/Services/CustomerProfileService.php` | Met à jour prénom, nom, display_name et téléphone sous verrou ; écrit un événement d'audit | Implémenté. Refuse les comptes non Customer, les comptes avec rôle interne, inactifs ou non vérifiés. |
| `app/Domains/Identity/Customer/Services/CustomerEmailChangeService.php` | Demande et confirme le changement d'e-mail | Implémenté : mot de passe actuel, jeton aléatoire stocké sous forme hashée, expiration 30 minutes, usage unique, vérification d'unicité, révocation des sessions et jetons, audit et notification de l'ancienne adresse. |
| `app/Domains/Identity/Customer/Models/CustomerEmailChangeRequest.php` | Persiste la demande de changement d'e-mail | Implémenté. |
| `app/Domains/Identity/Customer/Notifications/CustomerEmailChangeNotification.php` | Envoie le lien de confirmation à la nouvelle adresse | Implémenté. |
| `app/Domains/Identity/Customer/Notifications/CustomerEmailChangedNotification.php` | Informe l'ancienne adresse après validation | Implémenté. |
| `resources/views/customer/profile/edit.blade.php` | Interface profil, adresse principale, carnet, ajout, édition individuelle et changement d'e-mail | Présent. Chaque adresse dispose de son formulaire d'édition. Pas d'upload de photo Customer identifié. Le message de succès peut encore être rendu deux fois par le layout et la vue. |
| `resources/views/layouts/customer.blade.php` | Navigation Customer vers boutique, panier, commandes, profil, sécurité et appareils | Présent. Le lien profil est accessible depuis la navigation Customer. |
| `routes/web.php` | Déclare les routes Customer profile et les protège par auth, session active, rôle Customer et middleware de compte Customer | Présent. Routes : `customer.profile.edit`, `customer.profile.update`, `customer.profile.addresses.store`, `customer.profile.addresses.update`, `customer.profile.addresses.primary`, `customer.profile.addresses.destroy`, `customer.profile.email.change`. |
| `app/Domains/Identity/Customer/Http/Middleware/EnsureCustomerAccount.php` | Empêche les comptes ayant des rôles internes d'utiliser l'espace Customer | Présent et appliqué au groupe de routes du profil. |
| `tests/Feature/Domains/Identity/Customer/CustomerProfileTest.php` | Consultation, mise à jour, protection des champs sensibles, séparation des rôles, ajout / principale / suppression logique | Présent. Ne prouve pas à lui seul la couverture complète des limites et de la concurrence. |
| `tests/Feature/Domains/Identity/Customer/CustomerEmailChangeTest.php` | Mot de passe incorrect, e-mail déjà utilisé, révocation des demandes précédentes, expiration, sessions/tokens et audit | Présent. Exécution locale non communiquée pour cet audit. |
| `tests/Feature/Domains/Identity/Customer/CustomerAuthenticationIsolationTest.php` | Séparation des parcours d'authentification, sécurité et déconnexion Customer | Présent. |

### Localization / carnet d'adresses

| Fichier | Responsabilité constatée | État / observation |
|---|---|---|
| `app/Domains/Localization/Models/Address.php` | Modèle d'adresse avec propriétaire User ou coopérative, pays, adresse principale et SoftDeletes | Implémenté. |
| `app/Domains/Identity/Users/Models/User.php` | Relations `addresses()` et `primaryAddress()` | Implémenté. |
| `app/Domains/Localization/Contracts/UserAddressLifecycleManager.php` | Contrat public de cycle de vie des adresses User | Implémenté ; expose aussi la mise à jour d'une adresse par son identifiant. |
| `app/Domains/Localization/Contracts/UserAddressOwnerGateway.php` | Frontière vers Identity pour verrouiller / autoriser le propriétaire | Implémenté. |
| `app/Domains/Identity/Users/Services/IdentityUserAddressOwnerGateway.php` | Adaptateur Identity et application de AddressPolicy | Implémenté. |
| `app/Domains/Localization/Services/AddressService.php` | Création, mise à jour ciblée, principale, suppression logique, restauration et audit | Implémenté. La mise à jour ciblée verrouille le propriétaire et l'adresse, vérifie l'appartenance et journalise les changements. |
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
| `resources/views/storefront/checkout.blade.php` | Choix d'une adresse existante et formulaire d'ajout d'une adresse | Présent. Le formulaire poste vers `customer.profile.addresses.store`, récupère l'identifiant créé et le transmet au rechargement de l'aperçu pour présélectionner cette adresse. |
| `app/Domains/Sales/Services/SalesCheckoutService.php` | Demande le snapshot Localization avant création de commande | Implémenté. |
| `app/Domains/Localization/Contracts/CheckoutAddressReader.php` | Contrat de lecture / snapshot pour Sales | Implémenté. |
| `app/Domains/Localization/DTOs/CheckoutAddressSnapshot.php` | Transporte les champs du snapshot sans exposer le modèle Address | Implémenté. |
| `app/Domains/Orders/DTOs/OrderCheckoutData.php` | Transporte le snapshot d'adresse à Orders | Implémenté. |
| `app/Domains/Orders/Services/OrderCreationService.php` | Persiste le snapshot dans `orders.delivery_address_snapshot` | Implémenté. La commande conserve une copie des données ; elle ne dépend pas de la lecture ultérieure du carnet courant. |
| `tests/Feature/Domains/Sales/Http/SalesCustomerCheckoutFlowsTest.php` | Parcours checkout panier et commande directe | Présent ; résultat vert communiqué par l'utilisateur. Un test de non-régression a été ajouté pour vérifier le retour de l'identifiant de la nouvelle adresse, son ajout à l'aperçu et la préservation de l'adresse principale ; l'exécution locale reste à confirmer. |

## 3. Écarts et tâches V1 à traiter

### P0 — Corriger avant de considérer le profil V1 terminé

1. **Découpler la mise à jour du profil de l'adresse.** Toujours ouvert : le PUT du profil ne doit pas exiger une rue, une ville et une région pour modifier seulement prénom, nom ou téléphone.
2. **Checkout — sélection de la nouvelle adresse.** Corrigé dans le code : le endpoint retourne `data.id`, puis le frontend sélectionne cette adresse après actualisation de l'aperçu ; le drapeau `is_primary` du carnet n'est pas modifié.
3. **Édition individuelle des adresses.** Corrigée dans le code : formulaire et route PATCH dédiés, méthode `updateAddressForUser()` avec verrouillage, contrôle d'appartenance, validation centralisée et audit.
4. **Tests de non-régression ajoutés, à exécuter localement.** Le test Sales vérifie l'identifiant retourné, la présence de la nouvelle adresse dans l'aperçu et la conservation de l'adresse principale. Le test Identity vérifie la modification d'une adresse secondaire, l'absence de promotion implicite et le rejet d'une adresse appartenant à un autre client.

### P1 — Fonctionnalités à arbitrer / compléter

4. **Photo de profil.** Aucun contrôle d'affichage ou d'upload Customer n'a été identifié dans l'écran. Le modèle User possède une relation `profileImage()` vers Media, mais cela ne prouve pas que le flux Customer soit implémenté. À classer selon le périmètre fonctionnel V1 confirmé.
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


## 6. Révalidation après correctifs — 25 novembre 2026

Les correctifs ci-dessous ont été poussés sur `feature/Marketplace` après l'audit initial :

- **Checkout :** `CustomerProfileController::storeAddress()` retourne désormais `data.id` pour les requêtes JSON. `checkout.blade.php` transmet cet identifiant à `loadPreview()` et sélectionne la nouvelle adresse, même si une autre adresse est principale. La création ne modifie pas `is_primary`.
- **Carnet :** ajout de `PATCH customer/profile/addresses/{address}`, du formulaire d'édition pour chaque adresse et de `UserAddressLifecycleManager::updateAddressForUser()`. `AddressService` verrouille le propriétaire et l'adresse, filtre par `user_id`, applique l'autorisation Identity et réutilise la validation / journalisation existantes.
- **Tests ajoutés :** modification d'une adresse secondaire sans changer l'adresse principale ; refus d'une adresse appartenant à un autre Customer ; identifiant de la nouvelle adresse renvoyé au checkout et présence dans l'aperçu.
- **Résultats :** aucun résultat d'exécution n'est encore communiqué pour ces nouveaux tests. Ils doivent être exécutés localement avant de déclarer les deux corrections validées.
