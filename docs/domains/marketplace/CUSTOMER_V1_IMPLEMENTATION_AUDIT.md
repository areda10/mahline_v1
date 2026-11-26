# MAHLINE V1 — Audit des écrans et parcours Customer

- **Date de revue :** 26 novembre 2026
- **Dépôt :** `areda10/mahline_v1`
- **Branche auditée :** `feature/Marketplace`
- **Référence audit initiale :** `8dfd554c60f17f93cc09904ce9b51b306210ad2d`
- **Correctifs code ajoutés :** `ab849e46d89eca65288320fa4fa691e3d698b5dc`
- **Périmètre :** inventaire des écrans Customer V1, routes, contrôleurs, authentification, boutique, panier, checkout, commandes, profil, carnet d'adresses, sécurité, notifications et liaison Sales / Orders.
- **Nature :** revue statique des fichiers du dépôt. Les résultats de tests ci-dessous sont ceux communiqués par l'utilisateur et exécutés localement.

## 1. Synthèse

Le profil Customer n'est pas à créer de zéro. Les écrans, routes, contrôleurs, services, modèle d'adresse, politiques, notification de changement d'e-mail et tests existent déjà.

| Fonction | État constaté dans le code |
|---|---|
| Consulter le profil | Implémenté |
| Modifier prénom, nom, téléphone indépendamment des adresses | Implémenté après correctif du 26 novembre 2026 ; tests de non-régression ajoutés, exécution locale à confirmer |
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
| Photo de profil Customer | **Implémentée** : upload/remplacement/suppression via MediaService, catégorie privée `profile_image`, miniature privée et avatar dans la navigation. Tests ciblés déclarés verts par l'utilisateur avant l'ajout des nouveaux tests de découplage. |
| Suite de tests Customer exécutée localement | **Non confirmée** par les résultats transmis pour cet audit |

## 2. Revue fichier par fichier

### Identity / Customer

| Fichier | Responsabilité constatée | État / observation |
|---|---|---|
| `app/Domains/Identity/Customer/Http/Controllers/CustomerProfileController.php` | Affiche le profil, met à jour les données personnelles, gère les photos, adresses et changement d'e-mail | Implémenté. `update()` ne traite désormais que les données personnelles ; les opérations d'adresse restent déléguées à Localization. |
| `app/Domains/Identity/Customer/Http/Requests/UpdateCustomerProfileRequest.php` | Valide les seules données personnelles modifiables | Corrigé le 26 novembre 2026 : prénom, nom et téléphone uniquement. Aucun champ d'adresse n'est requis ni validé par ce PUT. |
| `app/Domains/Identity/Customer/Http/Requests/RequestCustomerEmailChange.php` | Autorise uniquement un compte Customer et valide nouvel e-mail / mot de passe | Implémenté. |
| `app/Domains/Identity/Customer/Services/CustomerProfileService.php` | Met à jour prénom, nom, display_name et téléphone sous verrou ; écrit un événement d'audit | Implémenté. Refuse les comptes non Customer, les comptes avec rôle interne, inactifs ou non vérifiés. |
| `app/Domains/Identity/Customer/Services/CustomerEmailChangeService.php` | Demande et confirme le changement d'e-mail | Implémenté : mot de passe actuel, jeton aléatoire stocké sous forme hashée, expiration 30 minutes, usage unique, vérification d'unicité, révocation des sessions et jetons, audit et notification de l'ancienne adresse. |
| `app/Domains/Identity/Customer/Models/CustomerEmailChangeRequest.php` | Persiste la demande de changement d'e-mail | Implémenté. |
| `app/Domains/Identity/Customer/Notifications/CustomerEmailChangeNotification.php` | Envoie le lien de confirmation à la nouvelle adresse | Implémenté. |
| `app/Domains/Identity/Customer/Notifications/CustomerEmailChangedNotification.php` | Informe l'ancienne adresse après validation | Implémenté. |
| `resources/views/customer/profile/edit.blade.php` | Interface profil, photo, carnet d'adresses, édition individuelle et changement d'e-mail | Présent. Formulaire personnel indépendant ; carnet séparé avec ajout, édition, sélection principale et suppression. Les notifications de succès sont rendues par le layout pour éviter les doublons. |
| `resources/views/layouts/customer.blade.php` | Navigation Customer vers boutique, panier, commandes, profil, sécurité et appareils | Présent. Le lien profil affiche la miniature privée du Customer ou son initiale en l'absence de photo. |
| `routes/web.php` | Déclare les routes Customer profile et les protège par auth, session active, rôle Customer et middleware de compte Customer | Présent. Routes : `customer.profile.edit`, `customer.profile.update`, `customer.profile.photo.store`, `customer.profile.photo.destroy`, `customer.profile.addresses.store`, `customer.profile.addresses.update`, `customer.profile.addresses.primary`, `customer.profile.addresses.destroy`, `customer.profile.email.change`. |
| `app/Domains/Identity/Customer/Http/Middleware/EnsureCustomerAccount.php` | Empêche les comptes ayant des rôles internes d'utiliser l'espace Customer | Présent et appliqué au groupe de routes du profil. |
| `tests/Feature/Domains/Identity/Customer/CustomerProfileTest.php` | Consultation, profil, adresses, photo, protection des champs sensibles et séparation des rôles | Présent. Deux tests ajoutés pour prouver la mise à jour personnelle sans adresse et la préservation d'une adresse existante ; ces nouveaux tests doivent être exécutés localement. |
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

1. **Découpler la mise à jour du profil de l'adresse.** Corrigé le 26 novembre 2026 : validation, contrôleur et formulaire ne traitent plus que les informations personnelles. Les adresses restent gérées dans le carnet dédié. Tests de régression ajoutés ; exécution locale à confirmer.
2. **Checkout — sélection de la nouvelle adresse.** Corrigé dans le code : le endpoint retourne `data.id`, puis le frontend sélectionne cette adresse après actualisation de l'aperçu ; le drapeau `is_primary` du carnet n'est pas modifié.
3. **Édition individuelle des adresses.** Corrigée dans le code : formulaire et route PATCH dédiés, méthode `updateAddressForUser()` avec verrouillage, contrôle d'appartenance, validation centralisée et audit.
4. **Tests de non-régression ajoutés, à exécuter localement.** Le test Sales vérifie l'identifiant retourné, la présence de la nouvelle adresse dans l'aperçu et la conservation de l'adresse principale. Le test Identity vérifie la modification d'une adresse secondaire, l'absence de promotion implicite et le rejet d'une adresse appartenant à un autre client.

### P1 — Fonctionnalités à arbitrer / compléter

4. **Photo de profil.** Implémentée sur `feature/Marketplace` et testée dans `CustomerProfileTest` ; les tests ciblés ont été déclarés verts par l'utilisateur avant l'ajout des tests de découplage.
5. **Nettoyage UI.** Corrigé le 26 novembre 2026 : le message de succès du profil est rendu une seule fois par le layout Customer.
6. **Robustesse et tests d'intégration.** Couvrir l'adresse d'un autre client, l'accès par compte multi-rôle, compte inactif / non vérifié, dépassement des cinq adresses par HTTP, ajout concurrent, suppression de l'adresse principale, restauration et adresse supprimée utilisée au checkout.

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

## 5. Résultats de tests locaux communiqués (historique avant le dernier correctif)

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


## 5. Mise à jour — photo de profil Customer (25 novembre 2026)

### Fichiers applicatifs ajoutés / modifiés

- `CustomerProfileController` : chargement de `profileImage`, upload et suppression délégués à `MediaService`.
- `routes/web.php` : routes `customer.profile.photo.store` et `customer.profile.photo.destroy`, protégées par authentification, session active, rôle Customer et `EnsureCustomerAccount`.
- `resources/views/customer/profile/edit.blade.php` : aperçu, formulaire multipart et suppression.
- `resources/views/layouts/customer.blade.php` : avatar dans la navigation Customer.
- `tests/Feature/Domains/Identity/Customer/CustomerProfileTest.php` : tests upload, remplacement, suppression et rejet d'un format non autorisé.

### Sécurité et cycle de vie

- Catégorie MEDIA `profile_image`, visibilité privée, une seule photo active.
- Limite HTTP de 5 Mo et validation serveur approfondie maintenue par MEDIA.
- Le remplacement utilise le verrouillage et la suppression logique déjà implémentés dans `MediaService`.
- L'affichage passe par `media.private` avec variante `thumbnail` ; aucune URL de stockage n'est exposée.
- Les tests doivent être exécutés sur l'environnement local ; aucune exécution n'est déclarée dans cet audit.


## 7. Audit exhaustif des écrans Customer V1 — 26 novembre 2026

L'inventaire ci-dessous est établi à partir de l'arbre des fichiers de **feature/Marketplace**, des routes déclarées dans **routes/web.php** et des contrôleurs associés. Un écran partagé avec les autres rôles est indiqué comme réutilisé, et non comme une vue Customer dédiée.

### 7.1 Écrans existants

| Parcours / écran | Vue réelle | Route(s) / contrôleur principal | État constaté |
|---|---|---|---|
| Connexion Customer | resources/views/auth/login.blade.php (vue partagée) | customer.login, customer.login.store / AuthenticationController | Implémenté ; formulaire partagé avec parcours d'authentification. |
| Inscription Customer | resources/views/customer/auth/register.blade.php | customer.register, customer.register.store / CustomerRegistrationController | Implémenté. |
| Vérification d'e-mail | resources/views/customer/auth/verification-notice.blade.php | customer.verification.notice, customer.verification.verify, customer.verification.resend / CustomerRegistrationController | Implémenté. |
| Mot de passe oublié / réinitialisation | resources/views/auth/forgot-password.blade.php, resources/views/auth/reset-password.blade.php (vues partagées) | customer.password.request, customer.password.email, customer.password.reset, customer.password.update / PasswordResetController | Implémenté ; vues partagées. |
| Tableau de bord Customer | resources/views/customer/dashboard.blade.php | customer.dashboard / CustomerDashboardController | Implémenté comme page d'accueil et hub de navigation ; pas de tableau de bord analytique. |
| Catalogue boutique | resources/views/storefront/products/index.blade.php | storefront.products.index / ProductStorefrontController@index | Implémenté et partagé aux visiteurs/clients. |
| Fiche produit | resources/views/storefront/products/show.blade.php | storefront.products.show / ProductStorefrontController@show | Implémenté et partagé. |
| Panier | resources/views/storefront/cart.blade.php | sales.cart.page / SalesCartPageController et routes sales.cart.* | Implémenté ; actions ajout, modification, suppression et vidage. |
| Checkout panier / achat direct | resources/views/storefront/checkout.blade.php | sales.cart.checkout.page, sales.cart.checkout.direct, sales.cart.checkout.preview, sales.cart.checkout.confirm / SalesCartController | Implémenté ; sélection/adjonction d'adresse et confirmation. |
| Historique des commandes | resources/views/customer/orders/index.blade.php | customer.orders.index / CustomerOrderHistoryController | Implémenté ; recherche, filtre par statut et pagination. |
| Détail d'une commande | resources/views/customer/orders/show.blade.php | customer.orders.show / CustomerOrderHistoryController | Implémenté ; sous-commandes, articles, statuts, chronologie et annulation dans les conditions autorisées. |
| Profil et photo | resources/views/customer/profile/edit.blade.php | customer.profile.edit, customer.profile.update, customer.profile.photo.store, customer.profile.photo.destroy / CustomerProfileController | Implémenté ; données personnelles séparées des adresses, photo privée. |
| Carnet d'adresses | Même vue profil, section #address-book | customer.profile.addresses.store, addresses.update, addresses.primary, addresses.destroy / CustomerProfileController et UserAddressLifecycleManager | Implémenté ; formulaires indépendants par adresse. |
| Changement d'e-mail | Même vue profil ; vérification par lien | customer.profile.email.change, customer.email.change.verify / CustomerProfileController et CustomerEmailChangeService | Implémenté ; confirmation séparée, expiration 30 minutes et révocation des sessions/jetons. |
| Mot de passe / changement forcé | resources/views/auth/force-password-change.blade.php (vue partagée) | customer.security.password.edit, customer.security.password.update, customer.password.force-change.* / ForcedPasswordChangeController | Implémenté ; parcours de sécurité partagé. |
| Appareils de confiance | resources/views/identity/security/devices/index.blade.php (vue partagée) | customer.security.devices.* / DeviceTrustController | Implémenté ; liste et actions de gestion des appareils. |
| Centre de notifications et cloche en haut à droite | resources/views/notifications/index.blade.php et _bell.blade.php (vues partagées ; cloche incluse dans le layout Customer) | notifications.index, notifications.read, notifications.read-all / NotificationCenterController | Implémenté ; icône cloche en haut à droite, compteur non lu, aperçu des cinq dernières notifications du Customer connecté, accès à la liste complète et marquage comme lu. Le contenu provient exclusivement de la relation notifications de l'utilisateur authentifié. |

### 7.2 Écrans à développer par release (décisions fonctionnelles validées le 26 novembre 2026)

| Élément | Constat statique | Suite recommandée |
|---|---|---|
| Liste des sessions web actives du Customer et révocation individuelle depuis son espace | Aucune route dédiée n'a été identifiée dans le groupe customer ; la page appareils de confiance existe mais n'est pas nécessairement équivalente à une liste de sessions Laravel. | **Release V2** — hors périmètre V1. Prévoir une page dédiée et la révocation individuelle des sessions web. |
| Consultation et téléchargement des factures côté Customer | Aucun écran customer/invoices ni route Customer de consultation/téléchargement de facture n'a été identifié dans cet inventaire. | **Release V1 — obligatoire.** Développer la liste des factures du client, le détail si requis par le parcours et le téléchargement PDF privé, avec contrôle strict de propriété/autorisation. |
| Historique des paiements et moyens de paiement | Aucun écran Customer dédié n'a été identifié dans les vues inventoriées. | **Release V1 — obligatoire.** Développer l'historique des paiements et la consultation/gestion des moyens de paiement conformément aux méthodes autorisées par Finance V1. |
| Préférences de notifications | Le centre de notifications est présent, mais aucun écran de préférences Customer n'a été identifié. | **Release V2** — hors périmètre V1. Prévoir les préférences client sans désactiver les notifications métier/sécurité obligatoires. |
| Tableau de bord avec indicateurs personnels | Le dashboard actuel est un hub de navigation simple. | À considérer comme option UX, pas comme écran manquant obligatoire sans exigence métier. |

### 7.3 Résultat de l'audit d'exhaustivité

- Les écrans structurants du parcours d'achat sont présents : boutique, fiche produit, panier, checkout, historique et détail de commande.
- Les écrans d'identité Customer sont présents : inscription, vérification, profil, changement d'e-mail, mot de passe et appareils.
- Le profil et le carnet d'adresses sont désormais séparés au niveau des formulaires, de la validation et du contrôleur.
- Les écrans Customer V1 obligatoires encore à développer sont la consultation/téléchargement des factures et l'historique des paiements/moyens de paiement.
- La gestion des sessions web et les préférences de notifications sont explicitement affectées à la Release V2 ; leur absence ne bloque donc pas la complétude du périmètre V1.
- Le centre de notifications existant reste distinct de l'écran de préférences V2.

### 7.4 Validation après le correctif du 26 novembre

L'utilisateur a communiqué les résultats verts suivants avant l'ajout des deux nouveaux tests de découplage :

- php artisan test tests/Feature/Domains/Identity/Customer/CustomerProfileTest.php
- php artisan test tests/Framework/Domains/Media
- php artisan test tests/Framework/Database/Migrations/MediaCategoryIndexMigrationTest.php

Comme CustomerProfileTest.php vient d'être enrichi avec les tests de mise à jour sans adresse et de préservation d'adresse, il doit être relancé. Pint --test et la suite complète n'ont pas encore été déclarés verts.


### 7.5 Icône de notifications Customer — 26 novembre 2026

Le layout Customer affiche une icône cloche dans la zone supérieure droite. Le badge indique le nombre de notifications non lues (plafonné visuellement à 99+). L'aperçu au survol ou au focus clavier présente les cinq notifications les plus récentes et un lien vers le centre complet. La requête utilise uniquement les notifications du compte authentifié ; les notifications d'un autre utilisateur ne sont jamais mélangées. Un test Feature dédié vérifie le rendu de la cloche, le compteur et l'isolation entre deux utilisateurs. L'exécution locale de ce nouveau test reste requise.
