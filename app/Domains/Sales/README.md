# MAHLINE V1 — Domaine Sales

- **Statut :** socle panier et interfaces Customer V1 présents sur `feature/Marketplace` ; checkout multi-coopératives et exclusion par coopérative en cas de stock insuffisant implémentés ; tests checkout ciblés déclarés verts localement par l'utilisateur le 25 novembre 2026. Le profil Customer / carnet d'adresses reste en audit, avec deux écarts P0 documentés.
- **Spécification :** [SALES_V1_SPECIFICATION.md](../../../docs/domains/marketplace/SALES_V1_SPECIFICATION.md)
- **Architecture :** ADR-021 — monolithe modulaire à contrats explicites.

## Responsabilité

Sales possède le parcours précommande : panier invité/authentifié, lignes de panier, calcul du récapitulatif commercial et orchestration du checkout vers Orders. Les promotions, coupons et devis sont hors périmètre V1 et reportés à V2.

Sales ne possède ni le catalogue et les prix de référence (Products), ni le stock et les réservations (Inventory), ni les commandes après création (Orders), ni les expéditions (Delivery), ni les factures/paiements/commissions (Finance).

## Socle réalisé

- Persistance distincte des paniers invités et authentifiés.
- Jeton invité aléatoire dont seul le SHA-256 est conservé en base.
- Expiration du panier invité après 30 jours d'inactivité ; aucune expiration automatique du panier authentifié.
- Lignes historiques retirées/vidées conservées logiquement.
- Version optimiste du panier : les écritures exigent la version attendue.
- Fusion transactionnelle du panier invité vers le panier client, avec addition des lignes de même empreinte commerciale.
- Limite technique de 100 unités par ligne appliquée côté service.
- Lecture des données produit via le contrat public `CheckoutCatalog` ; aucun import de modèle Products.
- Contrôle du statut actif/non supprimé de la coopérative via `CooperativeStatusReader` à l'ajout et au rafraîchissement du panier.
- Rafraîchissement des produits/variantes existants : désactivation/suppression bloquante sans suppression de ligne ; réactivation reconnue automatiquement.
- Détection des hausses de prix et changements commerciaux, acceptation explicite via `sales.cart.items.accept-current` ; baisse de prix appliquée automatiquement.
- Checkout panier et commande directe orchestrés vers Orders avec clé d'idempotence, snapshot d'adresse et devis Delivery par coopérative.
- En cas de stock insuffisant dans une coopérative d'un panier multi-coopératives, toutes ses lignes sont exclues ensemble de la tentative ; les autres coopératives disponibles peuvent être commandées et les lignes exclues restent actives dans le panier.
- Les coopératives inactives, produits/variantes non vendables et changements commerciaux non acceptés continuent de bloquer le checkout global ; l'exclusion automatique est réservée à l'insuffisance de stock.
- Précision validée : `DECIMAL(15,3)` ; validation HTTP et service limitées à trois décimales.

## Limites actuelles

- Le contrat Products actuel ne donne pas encore l'état détaillé des produits supprimés/désactivés, les limites commerciales ni la version des caractéristiques commerciales. La synchronisation complète des lignes historiques reste donc à intégrer.
- Les contrats publics de snapshot d'adresse, de création de commande et de calcul de livraison doivent être stabilisés avant le checkout complet.
- Promotions, coupons et devis sont explicitement reportés à V2 ; ils ne bloquent pas le checkout V1.
- Contrôleur JSON et routes panier déclarés (`sales.cart.show`, `sales.cart.items.store`, `sales.cart.items.update`, `sales.cart.items.destroy`, `sales.cart.clear`). Les vues Customer sont présentes : catalogue, fiche produit, panier et confirmation checkout.
- Les limites commerciales par produit/variante et leur administration restent à intégrer dans Products ; les champs de limites ne sont pas encore exposés par son contrat checkout.

## Migrations et tests

Les migrations Sales sont chargées par `SalesServiceProvider`, enregistré dans `bootstrap/providers.php`. Les tests du domaine couvrent le jeton invité, l'expiration, la précision à trois décimales, les limites, la version, la fusion et la conservation logique. La suite complète doit encore être remise au vert.


## Interfaces Customer V1 — tranche écrans

Les vues Customer existantes et complétées sur `feature/Marketplace` sont :

- `storefront.products.index` : catalogue, filtres et tri ; ajout au panier depuis une carte pour les produits sans variantes ; accès à la fiche pour choisir une variante ; commande directe pour les produits simples depuis la carte.
- `storefront.products.show` : fiche produit, galerie, sélection de variante et quantité ; actions distinctes « Ajouter au panier » et « Commander directement ».
- `storefront.cart` : panier actif, regroupement visuel par coopérative, modification des quantités, retrait, acceptation explicite des changements commerciaux, vidage du panier et accès au checkout.
- `storefront.checkout` : récapitulatif serveur, groupes par coopérative, frais Delivery par coopérative, adresse de livraison, total MAD, confirmation COD et référence de commande.

### Séparation des parcours

- Le checkout direct crée un panier technique isolé, lié à un jeton opaque conservé en session ; il ne réutilise ni ne modifie le panier client.
- Le checkout panier utilise le panier authentifié et porte sur toutes ses lignes actives ; aucune ligne indisponible n'est silencieusement écartée.
- Les deux parcours partagent les validations serveur, le contrôle de disponibilité, le devis Delivery, le snapshot d'adresse, le paiement COD et la création idempotente dans Orders.
- Les actions d'achat sont réservées au rôle `customer`. Le panier invité reste disponible conformément à la règle du panier invité.

### État de vérification

Le changement de carte catalogue a été enregistré dans le dépôt. Les tests Laravel, Pint et le parcours navigateur n'ont pas été exécutés pendant cette intervention ; cette tranche ne doit donc pas être considérée comme validée par la suite de tests. Les contrats checkout inter-domaines et les tests HTTP/intégration restent à vérifier avant gel technique.


## Profil Customer et carnet d'adresses — état du code au 25 novembre 2026

Le profil est porté par Identity / Customer et Localization :

- `resources/views/customer/profile/edit.blade.php` : informations personnelles, adresse principale, carnet, changement d'e-mail.
- `CustomerProfileController` et `CustomerProfileService` : lecture / mise à jour du profil et journalisation.
- `CustomerEmailChangeService` : changement d'e-mail avec mot de passe actuel, lien à usage unique valable 30 minutes, puis révocation des sessions et tokens.
- `AddressService` : carnet d'adresses, limite Customer de cinq adresses actives, adresse principale, suppression logique et audit.
- `SalesCartController` + `storefront.checkout` : sélection d'une adresse enregistrée et ajout d'une adresse depuis le checkout.
- `CheckoutAddressSnapshotService` + `OrderCreationService` : validation de l'adresse et copie du snapshot dans la commande.

Écarts P0 connus :
1. Le formulaire PUT du profil exige actuellement rue, ville et région même lorsqu'un client souhaite modifier uniquement son prénom, son nom ou son téléphone.
2. Une adresse ajoutée depuis le checkout est enregistrée, mais n'est pas explicitement sélectionnée si une autre adresse principale existe déjà.

L'édition individuelle des adresses secondaires et l'upload d'une photo de profil ne sont pas présents dans l'interface Customer identifiée ; leur inclusion dans V1 reste à confirmer.

Voir [l'audit d'implémentation Customer V1](../../../docs/domains/marketplace/CUSTOMER_V1_IMPLEMENTATION_AUDIT.md) et [les règles du carnet Localization](../../../docs/domains/localization/ADDRESS_BOOK_V1.md).
