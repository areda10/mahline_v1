# MAHLINE V1 — Domaine Sales

- **Statut :** implémentation du socle panier en cours ; checkout et fonctionnalités commerciales avancées non gelés techniquement.
- **Spécification :** [SALES_V1_SPECIFICATION.md](../../../docs/domains/marketplace/SALES_V1_SPECIFICATION.md)
- **Architecture :** ADR-021 — monolithe modulaire à contrats explicites.

## Responsabilité

Sales possède le parcours précommande : panier invité/authentifié, lignes de panier, calcul du récapitulatif commercial, promotions/devis uniquement après arbitrage dédié, et orchestration du checkout vers Orders.

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
- Précision validée : `DECIMAL(15,3)` ; validation HTTP et service limitées à trois décimales.

## Limites actuelles

- Le contrat Products actuel ne donne pas encore l'état détaillé des produits supprimés/désactivés, les limites commerciales ni la version des caractéristiques commerciales. La synchronisation complète des lignes historiques reste donc à intégrer.
- Les contrats publics de snapshot d'adresse, de création de commande et de calcul de livraison doivent être stabilisés avant le checkout complet.
- Promotions, coupons et devis ne sont pas implémentés : leurs règles ne sont pas entièrement arbitrées dans la spécification.
- Contrôleur JSON et routes panier déclarés (`sales.cart.show`, `sales.cart.items.store`, `sales.cart.items.update`, `sales.cart.items.destroy`, `sales.cart.clear`). Les écrans Blade/UI restent à réaliser.
- Les limites commerciales par produit/variante et leur administration restent à intégrer dans Products ; les champs de limites ne sont pas encore exposés par son contrat checkout.

## Migrations et tests

Les migrations Sales sont chargées par `SalesServiceProvider`, enregistré dans `bootstrap/providers.php`. Les tests du domaine couvrent le jeton invité, l'expiration, la précision à trois décimales, les limites, la version, la fusion et la conservation logique. La suite complète doit encore être remise au vert.
