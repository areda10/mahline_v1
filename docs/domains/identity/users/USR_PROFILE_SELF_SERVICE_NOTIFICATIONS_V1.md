# MAHLINE — Profil personnel et notifications internes V1

- Domaine propriétaire : Identity / Users
- Domaines associés : Localization, Media, Notifications, Audit
- Rôles concernés : cooperative_admin, user
- Branche d'implémentation : feature/Marketplace
- État : implémentation ajoutée ; validation locale requise
- Date : 26 novembre 2026

## 1. Résultat de l'audit préalable

### Profil personnel

Les éléments suivants existaient déjà :
- User::addresses() et User::primaryAddress() relient le compte à son adresse personnelle dans Localization.
- User::profileImage() relie le compte au média principal de catégorie profile_image.
- MediaCategory::PROFILE_IMAGE, MediaService et MediaPolicy prennent déjà en charge l'upload privé, le remplacement et la suppression de la photo du propriétaire.
- PersonalAddressController et AddressService permettent déjà à l'utilisateur de mettre à jour sa propre adresse.

Écarts identifiés :
- aucun écran de profil interne complet n'existait pour cooperative_admin et user ;
- aucun contrôleur / Form Request / service dédié ne permettait de modifier les informations personnelles de ces rôles ;
- la Policy User ne proposait pas d'abilities explicites de self-service ;
- l'adresse était accessible par un écran distinct, non intégré à un espace « Mon profil ».

Aucune nouvelle migration n'est nécessaire : les relations adresse et photo existent déjà. Les migrations exécutées restent inchangées.

### Notifications

Les composants déjà présents :
- table Laravel notifications et trait Notifiable sur User ;
- routes notifications.index, notifications.read et notifications.read-all, protégées par authentification, session active et rôle ;
- contrôleur de centre qui interroge exclusivement les notifications de l'utilisateur connecté ;
- cloche et aperçu intégrés au layout admin ;
- écran de liste avec action de lecture individuelle et globale.

Corrections de ce lot :
- tests HTTP dédiés à cooperative_admin et user ;
- libellé générique de la cloche au lieu de « Notifications client » ;
- maintien de l'isolation stricte des notifications par propriétaire.

## 2. Architecture et liaisons

| Écran / action | Route | Contrôleur | Domaine/service | Autorisation |
|---|---|---|---|---|
| Consulter Mon profil | backoffice.profile.edit | UserProfileController@edit | Identity / Users | UserPolicy::viewOwnProfile |
| Modifier les informations personnelles | backoffice.profile.update | UserProfileController@update | UserProfileService + Audit | UserPolicy::updateOwnProfile |
| Ajouter/remplacer la photo | backoffice.profile.photo.store | UserProfileController@storePhoto | Media / MediaService | propriétaire + MediaPolicy |
| Supprimer la photo | backoffice.profile.photo.destroy | UserProfileController@deletePhoto | Media / MediaService | propriétaire + MediaPolicy |
| Modifier l'adresse | backoffice.profile.address.update | PersonalAddressController@update | Localization / AddressService | AddressPolicy::updateUserAddress |
| Ouvrir les notifications | notifications.index | NotificationCenterController@index | Notifications | authentification + rôle + notifications du compte |
| Marquer une notification lue | notifications.read | NotificationCenterController@markRead | Notifications | relation notifications du compte |
| Tout marquer comme lu | notifications.read-all | NotificationCenterController@markAllRead | Notifications | relation unreadNotifications du compte |

## 3. Règles de sécurité du profil

- Seuls cooperative_admin et user utilisent les routes de self-service.
- Les autorisations sont strictement limitées à l'utilisateur authentifié lui-même.
- Les informations modifiables sont prénom, nom et téléphone.
- L'e-mail reste en lecture seule et conserve son workflow de changement/vérification dédié.
- Mot de passe, statut, rôles et affectation à la coopérative ne sont jamais modifiables via le formulaire.
- La photo reste privée ; son affichage passe par la route privée Media et sa Policy.
- L'adresse reste la propriété de l'utilisateur et est modifiée via Localization.
- Les modifications des informations personnelles sont journalisées par Audit.
- Une modification ordinaire du profil ne révoque ni les sessions Web ni les tokens API.

## 4. Notifications

- Chaque utilisateur ne consulte et ne marque comme lues que ses propres notifications.
- La cloche affiche le nombre de notifications non lues et un aperçu des notifications récentes.
- L'écran complet permet la lecture individuelle et globale.
- Les notifications restent dans le domaine Notifications ; aucun second système n'est créé dans Identity.

## 5. Producteurs métier des notifications vérifiés

- Création de commande : OrderCreationService adresse une notification à l'administrateur actif et vérifié de la coopérative concernée, avec un lien vers la sous-commande.
- Alertes de stock : InventoryStockAlertNotificationDispatcher adresse les notifications aux rôles cooperative_admin et user de la coopérative active, ainsi qu'aux super_admin qui lui sont affectés, avec un lien vers l'inventaire.
- Signalements de sécurité Inventory : InventorySecurityReportNotificationDispatcher notifie Root, l'administrateur de la coopérative et les super_admin affectés ; le rôle user n'est pas destinataire. Le lien mène au centre des rapports de sécurité.
- Cycle de vie des commandes client : CustomerOrderLifecycleNotification est destiné au customer, pas aux comptes internes.

Les notifications de commandes, de stock et de sécurité contiennent un titre, un message et un lien interne vers l'écran concerné. La cloche affiche le message lorsqu'il existe ; la liste complète affiche les champs message/body/description. Les dispatchers de stock et de sécurité excluent les comptes inactifs, non vérifiés ou verrouillés d'urgence ; les alertes de stock sont limitées à une coopérative active et à des rôles actifs.

## 6. Tests locaux à exécuter

- php artisan test tests/Feature/Domains/Identity/Users/UserProfileControllerTest.php
- php artisan test tests/Feature/Domains/Identity/Users/PersonalAddressControllerTest.php
- php artisan test tests/Feature/Domains/Notifications/InternalNotificationCenterTest.php
- php artisan test tests/Feature/Domains/Notifications/NotificationCenterTest.php
- php vendor/bin/pint --test
- php artisan test

Les tests n'ont pas été exécutés par ce changement distant. La fonctionnalité ne doit pas être déclarée clôturée avant les tests ciblés, Pint et la suite complète verts en local.

## 7. Points restant à confirmer

- Vérifier localement PHP GD/WebP, requis par le traitement d'images Media déjà en place.
- Vérifier le rendu de la cloche et du menu sur desktop et mobile.
- Vérifier que les producteurs de notifications des domaines métier couvrent les événements attendus pour cooperative_admin et user ; ce lot garantit l'accès et la confidentialité du centre, pas l'ajout de notifications métier non spécifiées.
