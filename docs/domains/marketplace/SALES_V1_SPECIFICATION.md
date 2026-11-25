# MAHLINE V1 — Spécification fonctionnelle Sales

- **Domaine :** Sales
- **Version :** 1.0 — cadrage initial
- **Date :** 19 novembre 2026
- **Branche :** `feature/Marketplace`
- **Statut :** EN COURS — décisions fonctionnelles SALES-001 à SALES-040 consignées ; socle panier et checkout Customer V1 présents ; validation technique intégrée encore requise.
- **Référence d'architecture :** [ADR-021 — Architecture hybride du monolithe modulaire](../../architecture/decisions/ADR-021-hybrid-modular-monolith.md)

> Ce document est le registre des décisions fonctionnelles et techniques du domaine Sales. Une règle non validée reste explicitement ouverte. Aucune migration ou implémentation ne doit être créée sur la base d'une supposition.

## 1. Objectif et responsabilité

Sales possède le parcours commercial précommande :
- panier invité et panier client authentifié ;
- lignes et regroupement des articles dans le panier ;
- calcul et présentation du récapitulatif commercial ;
- promotions et coupons : reportés à V2 ;
- devis : reportés à V2 ;
- orchestration du checkout et transmission de la demande de création à Orders.

Sales ne possède pas :
- produits, variantes, unités et prix catalogue de référence (Products) ;
- catégories et présentation du catalogue (Categories / Catalog) ;
- stock, disponibilité faisant foi et réservations (Inventory) ;
- commande globale, sous-commandes, lignes et statuts après création (Orders) ;
- transporteurs, offres et expéditions (Delivery) ;
- factures, paiements, remboursements, commissions et reversements (Finance) ;
- comptes, sessions et permissions (Identity).

## 2. Architecture et propriété

MAHLINE conserve un monolithe modulaire Laravel avec déploiement unique et base SQL partagée. Sales est un domaine autonome, au même titre que Orders, Inventory, Delivery, Finance et Communication.

Sales possède ses modèles, tables, règles métier, services applicatifs, policies, contrats publics fournis, migrations et tests. Les autres domaines ne doivent pas importer ses modèles ou services privés ni écrire dans ses tables.

Les échanges inter-domaines utilisent les contrats publics et DTO du domaine fournisseur, ainsi que des événements métier versionnés publiés après commit. Les opérations critiques suivent les règles d'atomicité et d'Outbox de l'ADR-021.

## 3. Décisions validées

### SALES-001 — Panier invité et authentifié

**Statut : VALIDÉ — 19 novembre 2026**

Le panier est accessible :
- au visiteur non authentifié, sans obligation de créer un compte avant d'ajouter des articles ;
- au client authentifié, avec conservation de son panier associé à son compte.

Après authentification d'un visiteur possédant un panier invité, Sales fusionne ce panier avec le panier du compte client.

La fusion des lignes est définie par SALES-003. Les quantités maximales, l'indisponibilité, les changements de prix et de caractéristiques commerciales, les conflits concurrents et les cas de réactivation sont précisés par SALES-006 à SALES-037. Les paramètres techniques encore ouverts sont listés à la section 6.

### SALES-002 — Séparation des domaines

**Statut : VALIDÉ — 19 novembre 2026**

Sales, Orders, Inventory, Delivery, Finance et Communication sont des domaines autonomes dans le monolithe modulaire. Chacun est propriétaire de ses modèles, tables, règles métier, contrats et tests.

Orders reste le domaine propriétaire de la commande après sa création. Finance reste propriétaire des documents et opérations financières. Sales ne devient pas un domaine englobant ces responsabilités.

## 4. Frontières fonctionnelles du parcours

1. Le client consulte le catalogue ; Catalog / Products restent les sources de vérité des données catalogue.
2. Sales ajoute et conserve les articles dans le panier.
3. Sales demande aux domaines propriétaires les informations nécessaires au calcul et à la validation.
4. Les promotions, coupons et devis ne s'appliquent pas en V1 : ils sont reportés à V2.
5. Au checkout, Sales prépare une demande complète et validée ; l'aperçu Inventory ne réserve rien.
6. Sales transmet cette demande au contrat public de création d'Orders.
7. Orders crée la commande globale et ses sous-commandes en attente ; Sales ne modifie ensuite ni les lignes ni les statuts de cette commande.
8. Le checkout ne réserve aucun stock. À la confirmation d'une sous-commande par sa coopérative, Orders demande à Inventory de réserver toutes les lignes actives de cette sous-commande, après revalidation et verrouillage du stock. Si une ligne est insuffisante, la confirmation entière échoue sans réservation partielle.

## 5. Règles existantes à préserver

- Panier multi-coopératives.
- Checkout avec paiement à la livraison (COD) uniquement pour la V1.
- Devise V1 : MAD, sans conversion implicite.
- Une commande globale possède une seule adresse de livraison commune, capturée dans un snapshot indépendant.
- Toutes les commandes V1 nécessitent une adresse de livraison ; pas de retrait en coopérative pour le premier release.
- Le carnet client autorise au maximum cinq adresses actives ; la suppression logique d'une adresse ne modifie jamais les snapshots d'une commande existante.
- Le checkout ne réserve aucun stock.
- Orders crée une sous-commande par coopérative et conserve les snapshots commerciaux des lignes.
- Une modification d'une ligne de commande après création relève du workflow Orders et ne peut pas être effectuée silencieusement par Sales.

## 6. Points restant à définir avant le gel technique

Les décisions fonctionnelles SALES-001 à SALES-037 sont validées. Elles ne doivent plus être présentées comme des questions métier ouvertes. Les éléments ci-dessous sont des paramètres techniques ou des périmètres fonctionnels qui nécessitent encore un arbitrage explicite avant le gel du schéma ou du checkout :

### 6.1 Paramètres et invariants techniques
- Valeur minimale technique de quantité par ligne et contrôles de précision/unité ; la limite technique globale maximale est désormais fixée à **100 unités par ligne** (SALES-019). La limite commerciale par produit/variante est configurable par la coopérative et reste plafonnée à 100.
- Format/version du panier, stratégie exacte de verrouillage optimiste, réponse API en cas de version périmée et politique de retry (SALES-016/017).
- Clé d'idempotence, empreinte de requête, conservation du résultat et reprise après timeout/réponse incertaine du contrat Orders (SALES-018).
- Durée de validité d'un prix affiché et mécanisme de preuve d'acceptation d'une hausse ou d'une modification commerciale (SALES-007/008/033/034/037). Les devis sont reportés à V2.
- Champs exacts et version des snapshots de panier et de commande, notamment pour les produits/variantes supprimés ou modifiés (SALES-010/011/012/028/035/037).
- Règles d'arrondi monétaire, précision des quantités, calculs des sous-totaux, taxes et frais. Les paramètres fiscaux ne doivent pas être inventés.
- Durée de conservation et accès à l'historique des lignes retirées/supprimées (SALES-014/015).

### 6.2 Périmètres fonctionnels non encore arbitrés
- Promotions, coupons et devis sont explicitement hors périmètre V1 et reportés à V2. Leurs règles métier seront définies dans le cadrage V2 ; ils ne bloquent pas le checkout V1.
- Droits exacts de consultation et d'administration des paniers par rôle MAHLINE, au-delà de la propriété du panier client.
- Règles tarifaires de livraison, frais, partage éventuel et contrat de calcul avec Delivery.
- Comportement de récupération si Orders confirme la création mais que Sales ne reçoit pas la réponse ; aucun panier ne doit être consommé sans confirmation vérifiable.

### 6.3 Dépendances bloquantes pour le checkout complet
- Contrat Products de lecture commerciale du panier : distinguer produit/variante actifs, désactivés, supprimés, variantes inconnues, changements de prix et changements de caractéristiques.
- Contrat Cooperatives de consultation du statut de la coopérative propriétaire.
- Contrat Localization de lecture autorisée et de snapshot d'une adresse client active.
- Contrat public Orders de création idempotente depuis un DTO de checkout, sans exposer User ou les modèles privés Orders.
- Contrat Inventory de disponibilité au checkout ; aucune réservation ne doit être effectuée à cette étape.
- Contrat Delivery de calcul/confirmation des frais lorsque le tarif de livraison entre dans le total présenté au client.

## 7. Suivi d'implémentation

### Socle panier — 21 novembre 2026

Implémenté sur `feature/Marketplace` :
- tables `sales_carts` et `sales_cart_items`, avec ULID et références inter-domaines logiques ;
- modèle de panier invité (jeton aléatoire, seul son SHA-256 est stocké, expiration après 30 jours d'inactivité) ;
- panier client sans expiration automatique ;
- lignes actives et conservation logique des lignes retirées/vidées ;
- version optimiste obligatoire sur les mutations ;
- fusion transactionnelle panier invité → panier client ;
- limite technique de 100 unités appliquée côté service ;
- lecture du produit et de son prix initial par le contrat public Products `CheckoutCatalog` ;
- provider Sales enregistré dans `bootstrap/providers.php` ;
- tests Feature pour jeton invité, expiration, fusion, retrait historique, limite, conflit de version, réactivation produit, hausses/baisse de prix et acceptation des changements.

### Points non livrés

Le panier n'est pas encore complet au regard de SALES-001 à SALES-037. Restent notamment :
- contrat Products enrichi pour les états supprimé/désactivé/réactivé, les limites commerciales et le versionnement des caractéristiques ;
- rafraîchissement des données descriptives et détection/acceptation des changements de prix et de caractéristiques ;
- statut et activité de la coopérative via Cooperatives ;
- contrat public Localization pour valider et capturer l'adresse ;
- contrat public idempotent Orders et orchestration de consommation des seules lignes commandées ;
- contrat Delivery pour les frais de livraison ;
- contrat public Localization pour lire et capturer une adresse active ;
- contrat public idempotent Orders sans réservation au checkout ; la réservation est déclenchée par la confirmation coopérative dans Orders via Inventory ; contrat Delivery pour calculer les frais ;
- vérification complète des policies et des tests HTTP/intégration du parcours Customer ;
- rétention/purge des paniers invités expirés et historique ;
- promotions, coupons et devis : explicitement reportés à V2 et non implémentés en V1.

### Interfaces Customer V1 — tranche écrans

Les interfaces Blade Customer sont présentes sur `feature/Marketplace` :

- Catalogue (`storefront.products.index`) : recherche, filtres, tri, ajout au panier pour les produits simples, accès à la fiche pour les produits à variantes et commande directe pour un produit simple.
- Fiche produit (`storefront.products.show`) : galerie, choix obligatoire d'une variante lorsqu'elle existe, quantité à trois décimales maximum, ajout au panier et commande directe.
- Panier (`storefront.cart`) : affichage des lignes actives, coopérative, prix et quantités ; modification, retrait, vidage et acceptation explicite des changements de prix/caractéristiques ; accès au checkout du panier.
- Confirmation (`storefront.checkout`) : aperçu recalculé côté serveur, regroupement par coopérative, devis Delivery par coopérative, adresse de livraison, total en MAD, confirmation COD et référence de commande.

#### Deux parcours indépendants

1. **Commande directe depuis catalogue/fiche** : la demande crée un panier technique invité distinct, dont le jeton opaque est conservé dans la session Customer. Ce panier technique contient uniquement le produit/variante et la quantité demandés. Le panier client préexistant n'est ni lu pour composer la commande ni modifié par ce parcours.
2. **Commande depuis le panier** : le checkout examine toutes les lignes actives. Si le stock est insuffisant dans une coopérative, toutes les lignes de cette coopérative sont explicitement exclues et restent actives dans le panier ; les autres coopératives disponibles peuvent être commandées. Une coopérative inactive, un produit/une variante non vendable ou un changement commercial non accepté bloque toujours le checkout global.

Les deux parcours utilisent les contrôles serveur communs (Products, Cooperatives, Inventory, Localization, Delivery et Orders), l'adresse unique de livraison, le paiement COD et la clé d'idempotence. Les actions de commande sont réservées au rôle `customer`. Le panier invité reste accessible aux visiteurs, conformément à SALES-001.

#### Vérification à effectuer

La tranche d'interface a été complétée dans le catalogue pour proposer l'action « Ajouter au panier » directement sur les cartes des produits simples. Les fichiers Blade et le contrôleur existants ont été inspectés avant cette modification. Aucun test Laravel, Pint ou parcours navigateur n'a été exécuté pendant cette intervention ; la présence des vues ne vaut donc pas validation de bout en bout. La revue des policies, les tests HTTP/intégration et la suite complète restent obligatoires avant gel technique.

### Paramètre technique confirmé

Le 21 novembre 2026, l'utilisateur a confirmé la précision des quantités à trois décimales. Sales stocke les quantités en `DECIMAL(15,3)` et rejette toute valeur comportant plus de trois décimales, sans arrondi silencieux.

Les tests ont été ajoutés mais n'ont pas été exécutés dans un environnement Laravel/PHP durant cette intervention. Aucune validation CI, Pint ou suite complète n'est revendiquée.

## 8. Journal des décisions

### 19 novembre 2026
- SALES-001 : panier invité et authentifié avec fusion à la connexion — validé (option B).
- SALES-002 : autonomie des domaines Sales, Orders, Inventory, Delivery, Finance et Communication dans le monolithe modulaire — validé.

### SALES-003 — Fusion des lignes de panier

**Statut : VALIDÉ — 19 novembre 2026 (option C)**

Lors de la fusion d'un panier invité avec le panier du compte authentifié :
- deux lignes ne sont fusionnées que si toutes leurs caractéristiques commerciales sont identiques ;
- l'identité minimale à comparer comprend le produit, la variante éventuelle, la coopérative propriétaire et les caractéristiques commerciales qui influencent le prix ou les conditions de vente ;
- si une caractéristique diffère, les lignes restent distinctes ;
- lorsque les lignes sont strictement identiques, leurs quantités sont additionnées sous réserve des limites de quantité qui restent à définir ;
- la fusion doit être atomique et résistante aux connexions ou requêtes concurrentes ; les détails techniques seront établis à l'audit avant implémentation.

Le panier invité et le panier du compte ne doivent pas être fusionnés sur la seule base de l'identifiant du produit.


### SALES-004 — Expiration du panier invité

**Statut : VALIDÉ — 19 novembre 2026 (option B)**

Un panier invité est conservé pendant 30 jours à compter de sa dernière activité. Toute activité pertinente sur le panier réinitialise cette période de conservation.

Cette règle concerne uniquement le panier invité. La durée de conservation du panier associé à un compte authentifié est indépendante et reste à définir. L'expiration du panier invité ne doit pas entraîner la suppression d'une commande déjà créée ni de ses instantanés commerciaux.


### SALES-005 — Conservation du panier authentifié

**Statut : VALIDÉ — 19 novembre 2026 (option A)**

Le panier associé à un compte client authentifié est conservé sans expiration automatique. Il reste disponible jusqu'à sa suppression volontaire par le client ou sa transformation à la suite d'une création de commande réussie.

Cette conservation est indépendante de la durée de vie du panier invité, fixée à 30 jours sans activité. Les règles de nettoyage des paniers abandonnés ne doivent donc pas supprimer automatiquement les paniers authentifiés.



### SALES-006 — Produit devenu indisponible dans le panier

**Statut : VALIDÉ — 20 novembre 2026 (maximum absolu fixé à 100 unités par ligne)**

Lorsqu'un produit ou une variante présent(e) dans le panier devient inactif(ve), désactivé(e) ou n'est plus vendable :
- Sales conserve la ligne dans le panier ;
- la ligne est signalée clairement comme indisponible ;
- le checkout est bloqué tant qu'une ligne indisponible subsiste ;
- le client peut retirer lui-même la ligne pour poursuivre son checkout ;
- Sales ne supprime pas automatiquement la ligne et ne transmet pas une ligne invalide à Orders.

La vérification de l'état vendable doit être refaite au checkout auprès du domaine propriétaire du produit. Le signalement dans le panier est informatif et ne remplace pas cette validation finale.


### SALES-007 — Modification du prix après ajout au panier

**Statut : VALIDÉ — 19 novembre 2026 (option D)**

Si le prix de référence d'un produit ou d'une variante change après son ajout au panier et avant la validation du checkout :
- Sales détecte l'écart en relisant le prix auprès du domaine propriétaire ;
- le checkout est bloqué pour la ligne concernée tant que le client n'a pas accepté explicitement le nouveau prix ;
- le nouveau prix et son impact sur le total doivent être présentés clairement avant l'acceptation ;
- aucun nouveau prix ne peut être appliqué silencieusement ;
- en cas de refus, le client peut retirer la ligne ou quitter le checkout ;
- la demande transmise à Orders contient le prix accepté et les informations nécessaires à son snapshot commercial.

Le mécanisme d'acceptation doit être lié à une version/valeur de prix vérifiée au checkout afin d'éviter qu'une nouvelle modification concurrente soit considérée comme acceptée par erreur. Les détails de versionnement, de concurrence et de preuve d'acceptation seront définis à l'audit technique.


### SALES-008 — Baisse du prix après ajout au panier

**Statut : VALIDÉ — 19 novembre 2026 (option C)**

Si le prix de référence d'un produit ou d'une variante baisse après son ajout au panier et avant la validation du checkout :
- Sales applique automatiquement le nouveau prix inférieur ;
- le client est informé de la baisse et du nouveau total ;
- aucune acceptation supplémentaire n'est requise pour bénéficier de cette baisse.

La règle SALES-007 reste applicable aux hausses : toute augmentation doit être présentée au client et acceptée explicitement avant de poursuivre le checkout. Une baisse ultérieure doit toujours bénéficier au client. Le prix final transmis à Orders est le prix de référence courant vérifié au moment de la validation, avec les informations nécessaires au snapshot commercial.


### SALES-009 — Quantité du panier et stock disponible

**Statut : VALIDÉ — 19 novembre 2026 (option B)**

Le client peut ajouter au panier une quantité supérieure au stock actuellement disponible.

- Le panier est une intention d'achat et ne constitue jamais une réservation de stock.
- Sales ne bloque pas l'ajout ou la modification d'une quantité au seul motif que le stock courant est inférieur à cette quantité.
- La disponibilité est vérifiée aux étapes métier définies par Inventory, notamment lors du checkout et de la confirmation de la sous-commande selon les règles Inventory applicables.
- Une quantité supérieure au stock ne garantit ni la disponibilité ni la confirmation de la commande.
- Sales doit présenter les informations de disponibilité disponibles sans les transformer en promesse de stock réservé.

Les limites absolues de quantité par ligne, les règles anti-abus et les contrôles de dépassement indépendants du stock restent à définir.


### SALES-010 — Produit supprimé du catalogue alors qu'il figure au panier

**Statut : VALIDÉ — 19 novembre 2026 (option C)**

Lorsqu'un produit est supprimé du catalogue par sa coopérative alors qu'il figure déjà dans un panier :

- Sales conserve la ligne du panier comme ligne historique non achetable.
- La ligne conserve les informations descriptives disponibles au moment de l'ajout, notamment le nom du produit et son prix de référence, afin que le client puisse identifier l'article concerné.
- La ligne est signalée comme indisponible/supprimée et ne peut pas être commandée.
- Le checkout est bloqué tant que cette ligne non achetable demeure dans le panier.
- Le client doit retirer lui-même la ligne pour poursuivre le checkout.
- Aucune suppression automatique de la ligne n'est effectuée.
- La conservation de ces informations dans le panier ne ressuscite pas le produit et ne crée aucun droit à l'achat.

Les champs exacts du snapshot descriptif et la durée de conservation d'une ligne historique dans le panier restent à préciser lors de la conception technique.


### SALES-011 — Modification des informations produit après ajout au panier

**Statut : VALIDÉ — 19 novembre 2026 (option C)**

Lorsqu'une coopérative modifie un produit déjà présent dans un panier :

- Sales actualise automatiquement les informations descriptives non tarifaires à partir des données actuelles du domaine propriétaire, notamment le nom, la description et l'image.
- Sales conserve les informations initiales utiles à l'identification et à la traçabilité de la ligne au moment de son ajout.
- Les caractéristiques qui modifient les conditions commerciales (notamment variante, unité de vente, conditionnement ou toute caractéristique ayant un effet sur le prix ou les modalités de vente) ne sont pas assimilées à de simples mises à jour descriptives.
- Toute modification commerciale doit être détectée et traitée selon les règles de validation explicite déjà définies pour les hausses de prix et selon les règles à définir pour les autres changements commerciaux.
- Une mise à jour descriptive ne doit jamais entraîner silencieusement une modification de la quantité, du prix accepté ou des conditions commerciales de la ligne.
- Au checkout, Sales vérifie à nouveau les données et l'état commercial auprès du domaine propriétaire.

Le détail des champs du snapshot initial, la classification exhaustive des attributs descriptifs/commerciaux et le traitement de chaque type de changement commercial restent à formaliser avant implémentation.


### SALES-012 — Désactivation d'une variante présente au panier

**Statut : VALIDÉ — 19 novembre 2026 (option B)**

Lorsqu'une variante ajoutée au panier est désactivée par la coopérative alors que le produit parent reste actif et que d'autres variantes demeurent disponibles :

- La ligne correspondant à la variante initialement choisie est conservée dans le panier et signalée comme indisponible.
- Cette ligne ne peut plus être commandée ; le checkout est bloqué tant qu'elle demeure dans le panier.
- Le client doit retirer lui-même cette ligne et choisir explicitement une autre variante s'il le souhaite.
- Sales ne remplace jamais automatiquement la variante sélectionnée par une autre variante, même si celle-ci est disponible.
- L'activité du produit parent ne rend pas achetable une variante désactivée.
- La variante et les informations initiales nécessaires à l'identification de la ligne sont conservées pour expliquer au client ce qui est devenu indisponible.

Cette règle complète SALES-006 et SALES-011 : la disponibilité est vérifiée au niveau de la variante lorsque le produit en possède.


### SALES-013 — Réduction du stock disponible après ajout au panier

**Statut : VALIDÉ — 19 novembre 2026 (option B)**

Lorsqu'une coopérative réduit le stock disponible après qu'un client a ajouté une quantité supérieure au nouveau stock :

- Sales conserve la quantité demandée par le client ; aucune réduction automatique n'est effectuée.
- La ligne est signalée comme insuffisamment disponible au regard du stock courant.
- La disponibilité de chaque ligne est affichée et revérifiée au checkout.
- Si une seule coopérative est concernée, aucune commande n'est créée tant que son stock ne permet pas de servir toutes les lignes.
- Dans un panier multi-coopératives, si le stock est insuffisant pour au moins une ligne d'une coopérative, toutes les lignes de cette coopérative sont exclues ensemble de cette tentative de commande ; les lignes des autres coopératives disposant du stock requis peuvent être commandées.
- Les lignes exclues restent actives dans le panier. Le client peut ajuster leurs quantités ou réessayer ultérieurement.
- Une quantité n'est jamais réduite automatiquement et aucune ligne d'une coopérative insuffisamment stockée n'est commandée partiellement.
- Sales ne réserve pas le stock et ne garantit pas qu'il restera disponible entre l'affichage et la validation.
- La disponibilité doit être revérifiée auprès d'Inventory au checkout, puis selon le processus de confirmation défini par Inventory.

Cette règle préserve le choix du client tout en empêchant une validation silencieuse d'une quantité actuellement indisponible.


### SALES-014 — Vidage complet du panier

**Statut : VALIDÉ — 19 novembre 2026 (option B)**

Lorsqu'un client choisit de vider entièrement son panier :

- Sales retire logiquement toutes les lignes actives du panier ; elles ne sont plus présentées comme des articles à acheter.
- Les lignes supprimées sont conservées dans un historique interne à des fins de traçabilité et d'audit.
- Le vidage ne crée aucune commande et n'entraîne aucune réservation ou libération de stock, puisque le panier ne réserve pas le stock.
- Cette opération ne supprime ni les commandes, ni les snapshots de commandes, ni les factures.
- Les règles de rétention du panier invité (30 jours depuis la dernière activité) et de conservation du panier authentifié (sans expiration automatique) restent distinctes de cet historique interne.

La durée de conservation de l'historique, ses droits d'accès et sa purge éventuelle restent à définir dans la conception technique et les règles de gouvernance des données.


### SALES-015 — Restauration d'une ligne supprimée du panier

**Statut : VALIDÉ — 19 novembre 2026 (option A)**

Une ligne supprimée du panier, individuellement ou lors du vidage complet, ne peut pas être restaurée depuis l'historique du panier.

- Le client doit rechercher à nouveau le produit dans le catalogue et l'ajouter comme un nouvel article.
- La nouvelle ligne est créée à partir des informations commerciales actuelles du produit et de sa variante.
- Les contrôles actuels de statut, de prix et de disponibilité s'appliquent comme pour tout nouvel ajout.
- L'historique interne des lignes supprimées reste conservé à des fins de traçabilité, mais ne constitue pas une fonction de restauration.
- Aucune ancienne quantité, aucun ancien prix accepté et aucune ancienne condition commerciale ne sont réactivés.


### SALES-016 — Panier synchronisé entre les appareils

**Statut : VALIDÉ — 19 novembre 2026 (option A)**

Pour un client authentifié, le panier est unique au niveau du compte et synchronisé entre ses appareils (ordinateur, téléphone, tablette).

- Les lignes et quantités du panier du compte sont communes à toutes ses sessions et tous ses appareils.
- Une modification effectuée sur un appareil doit être répercutée sur les autres appareils.
- La synchronisation doit prévenir les pertes de données et les écrasements silencieux en cas de modifications concurrentes.
- Les mécanismes techniques de concurrence, de versionnement et de résolution des conflits seront définis lors de l'audit technique avant implémentation.
- Cette règle concerne le panier authentifié ; elle ne transforme pas le panier invité en panier multi-appareils.



### SALES-017 — Conflits de modifications simultanées du panier

**Statut : VALIDÉ — 19 novembre 2026 (option B)**

Lorsqu'un même panier authentifié est modifié presque simultanément depuis plusieurs appareils :

- Le panier utilise un mécanisme de versionnement permettant d'identifier la version sur laquelle repose chaque modification.
- Toute modification soumise avec une version devenue obsolète est refusée ; elle ne remplace pas automatiquement l'état plus récent du panier.
- L'appareil à l'origine de la modification refusée doit recharger l'état courant du panier avant de permettre une nouvelle tentative.
- Aucune modification concurrente ne doit être perdue ou écrasée silencieusement.
- Les détails techniques du versionnement, de l'atomicité et de la réponse applicative seront définis lors de l'audit avant implémentation.

Cette règle précise SALES-016 : le panier reste unique et synchronisé entre les appareils, mais les conflits sont traités par contrôle optimiste de version plutôt que par écrasement automatique ou fusion arbitraire.

### SALES-018 — Retrait des lignes après création réussie d'une commande

**Statut : VALIDÉ — 19 novembre 2026 (option B)**

Lorsqu'Orders confirme la création réussie d'une commande à partir du checkout :

- Sales retire logiquement du panier uniquement les lignes effectivement incluses dans la commande créée.
- Les autres lignes du panier, qui n'ont pas été incluses dans cette commande, restent actives et disponibles dans le panier.
- Le retrait des lignes consommées ne doit intervenir qu'après confirmation du succès de création par le contrat public d'Orders ; un échec ou un résultat incertain ne doit pas vider le panier.
- Les lignes retirées restent soumises aux règles d'historique et de traçabilité définies par SALES-014 et SALES-015.
- Les snapshots de commande et les données détenues par Orders ne sont ni supprimés ni modifiés par cette opération.

Cette règle est particulièrement importante pour un panier multi-coopératives : seules les lignes réellement consommées par la commande créée sont retirées, sans supprimer les articles restants.

L'atomicité entre la création Orders et la consommation des lignes Sales, ainsi que le traitement des réponses perdues, reprises et idempotence, seront définis lors de l'audit technique des contrats avant implémentation.

### SALES-019 — Limite maximale de quantité par ligne de panier

**Statut : VALIDÉ — 19 novembre 2026 (option D)**

La quantité d'une ligne de panier est encadrée par deux niveaux complémentaires :

1. **Limite technique globale de la plateforme :** la borne maximale absolue est fixée à **100 unités par ligne de panier**. Elle protège MAHLINE contre les quantités aberrantes, les abus et les valeurs hors capacité technique. Elle s'applique à toutes les lignes, quels que soient le produit et la coopérative.
2. **Limite commerciale configurable :** la coopérative propriétaire peut définir une quantité maximale propre à chaque produit ou variante, adaptée à ses conditions de vente.

Règles d'application :

- La quantité demandée ne peut dépasser ni la limite technique globale ni, lorsqu'elle est configurée, la limite commerciale du produit ou de la variante.
- Sales refuse tout ajout, fusion ou modification qui ferait dépasser l'une de ces limites et indique au client la quantité maximale applicable.
- Pour un produit avec variantes, la limite commerciale est déterminée au niveau de la variante ; pour un produit sans variante, elle est déterminée au niveau du produit.
- La limite commerciale ne peut jamais permettre de dépasser la limite technique globale.
- Ces limites sont indépendantes du stock : une quantité autorisée dans le panier ne réserve pas le stock et ne garantit pas sa disponibilité. Les contrôles de disponibilité restent ceux de SALES-009, SALES-013 et du domaine Inventory.
- Les règles s'appliquent également à la fusion des paniers (SALES-003) et aux modifications concurrentes (SALES-017) ; aucune de ces opérations ne peut contourner les limites.

Restent à préciser avant implémentation : la valeur minimale technique, la précision/unité de quantité admise et les contrôles de conversion ; la limite maximale absolue est fixée à 100 unités par ligne. Le comportement sans limite commerciale, les droits de modification et le traitement d'une limite abaissée sont définis par SALES-020 à SALES-025.

### SALES-020 — Réduction d'une limite commerciale avec une quantité déjà au panier

**Statut : VALIDÉ — 19 novembre 2026 (option A)**

Lorsqu'une coopérative abaisse la limite commerciale d'un produit ou d'une variante alors qu'une quantité supérieure figure déjà dans le panier d'un client :

- Sales conserve la quantité déjà demandée ; aucune réduction automatique n'est effectuée.
- La ligne est signalée comme dépassant désormais la limite commerciale applicable.
- Le checkout est bloqué tant que la quantité de la ligne dépasse la nouvelle limite.
- Le client doit réduire lui-même la quantité à une valeur autorisée ou retirer la ligne.
- La modification de la limite par la coopérative ne supprime pas la ligne et ne modifie pas silencieusement le choix du client.
- La nouvelle limite est vérifiée lors des modifications du panier et à nouveau au checkout auprès des données du domaine propriétaire.

Cette règle complète SALES-019 : les limites commerciales courantes s'appliquent aux lignes déjà présentes dans les paniers, sans altération automatique de leur quantité.

### SALES-021 — Absence de limite commerciale configurée

**Statut : VALIDÉ — 19 novembre 2026 (option A)**

Lorsqu'aucune limite commerciale n'est configurée par la coopérative pour un produit ou une variante :

- Aucune limite commerciale spécifique n'est appliquée à cette ligne.
- La limite technique globale de MAHLINE définie par SALES-019 reste obligatoire et constitue la borne maximale absolue.
- L'ajout, la modification et la fusion de lignes restent soumis à cette limite technique ainsi qu'aux autres règles applicables au panier.
- L'absence de configuration commerciale ne bloque donc pas, à elle seule, l'ajout au panier ou le checkout.

Cette règle n'affecte pas les contrôles de disponibilité, de statut vendable ou de prix, qui restent indépendants des limites de quantité.

### SALES-022 — Autorisation de gestion des limites commerciales

**Statut : VALIDÉ — 19 novembre 2026**

La définition et la modification des limites commerciales de quantité sont autorisées selon le périmètre coopératif du rôle :

- **`cooperative_admin`** : peut définir et modifier les limites des produits et variantes de sa propre coopérative.
- **`super_admin`** : peut définir et modifier les limites des produits et variantes des coopératives qui lui sont affectées.
- **`super_admin_root`** : peut définir et modifier les limites pour toutes les coopératives.
- **`user`** : ne peut ni définir ni modifier ces limites.

Toute opération doit vérifier côté serveur le rôle et le périmètre d'accès à la coopérative concernée. Cette autorisation ne permet pas de dépasser la limite technique globale de MAHLINE définie par SALES-019.

### SALES-023 — Application immédiate d'une diminution de limite commerciale

**Statut : VALIDÉ — 19 novembre 2026 (option A)**

Lorsqu'une coopérative diminue la limite commerciale d'un produit ou d'une variante, la nouvelle limite s'applique immédiatement à tous les paniers existants concernés, et pas uniquement aux nouveaux ajouts.

- Les lignes dont la quantité demandée dépasse la nouvelle limite sont signalées comme non conformes.
- Le checkout est bloqué pour toute ligne concernée tant que la quantité n'a pas été ramenée à la limite ou en dessous, ou que la ligne n'a pas été retirée.
- La quantité déjà demandée est conservée : aucune réduction automatique ni suppression de ligne n'est effectuée.
- La règle complète SALES-020 s'applique : le client conserve le choix de réduire ou retirer la ligne.
- La nouvelle limite est vérifiée lors des modifications du panier et à nouveau au checkout auprès du domaine propriétaire.

Cette règle s'applique immédiatement à tous les clients concernés, indépendamment de la date à laquelle ils ont ajouté le produit au panier.

### SALES-024 — Application immédiate d'une augmentation de limite commerciale

**Statut : VALIDÉ — 19 novembre 2026 (option A)**

Lorsqu'une coopérative augmente la limite commerciale d'un produit ou d'une variante, la nouvelle limite s'applique immédiatement à tous les paniers existants concernés.

- Les clients peuvent bénéficier immédiatement de la limite augmentée, y compris lorsque leur quantité était auparavant bloquée par l'ancienne limite.
- Aucune modification artificielle du panier n'est exigée pour bénéficier de cette augmentation.
- Les autres règles du panier restent applicables, notamment la limite technique globale MAHLINE, les contrôles de statut vendable, de prix et de disponibilité.
- La limite applicable est relue auprès du domaine propriétaire lors des opérations concernées et au checkout.

Cette règle complète SALES-020 et SALES-023 : une diminution s'applique immédiatement et peut bloquer une ligne excédentaire sans la réduire ; une augmentation s'applique immédiatement et peut lever ce blocage lié à l'ancienne limite.

### SALES-025 — Suppression d'une limite commerciale configurée

**Statut : VALIDÉ — 19 novembre 2026 (option A)**

Lorsqu'une coopérative supprime la limite commerciale configurée pour un produit ou une variante, cette suppression prend effet immédiatement pour tous les paniers existants concernés.

- La limite commerciale spécifique cesse de s'appliquer immédiatement.
- Seule la limite technique globale de MAHLINE définie par SALES-019 demeure applicable au titre des limites de quantité.
- Les clients peuvent bénéficier immédiatement de la suppression, sans devoir modifier artificiellement leur panier.
- Les autres règles restent applicables : statut vendable, contrôle du prix, disponibilité et validations de checkout.
- La limite effective est relue auprès du domaine propriétaire lors des opérations concernées et au checkout.

Cette règle complète SALES-021, SALES-023 et SALES-024 : en l'absence de limite commerciale configurée, seule la limite technique globale s'applique ; les modifications et suppressions de limite prennent effet immédiatement sur les paniers existants.



### SALES-038 — Frais de livraison multi-coopératives au tarif initial (décision utilisateur du 23 novembre 2026)

**Statut : VALIDÉ — tarif initial de 40,00 MAD par expédition coopérative.**

- Sales demande à Delivery un devis pour chaque expédition correspondant à une sous-commande coopérative ; Sales ne calcule jamais les frais de livraison.
- Pour le tarif initial, chaque expédition d'une coopérative vers le client coûte 40,00 MAD, quel que soit le nombre d'articles regroupés dans cette expédition.
- Le montant de livraison de la commande principale est la somme des frais de toutes ses expéditions coopératives : deux coopératives expédiant au même client entraînent 80,00 MAD ; trois entraînent 120,00 MAD.
- Chaque sous-commande conserve son propre frais de livraison et son snapshot de devis ; la commande principale conserve le total agrégé.
- Le tarif de 40,00 MAD est provisoire et doit être remplacé, sans changement de règle d'agrégation, par le montant renvoyé par Delivery selon les sociétés de livraison et leurs grilles de tarifs configurées.
- Sales affiche au client le détail par expédition coopérative, le total livraison et le total général avant validation finale.
- Au moment de confirmer, le serveur doit recalculer/valider les devis encore valides et les montants ; il ne fait jamais confiance à un total ou un frais envoyé par le navigateur.
- Les devis et tarifs acceptés sont figés dans les snapshots Orders ; une modification tarifaire ultérieure ne modifie pas les commandes existantes.
- Aucun checkout ne doit enregistrer des frais à zéro par défaut ou créer une commande si un devis de livraison requis est absent/expiré.


### Précision de périmètre — transfert de produit entre coopératives

**Décision confirmée — 19 novembre 2026**

Aucun transfert de produit d'une coopérative vers une autre n'est prévu dans MAHLINE V1. Un produit reste rattaché à sa coopérative propriétaire ; il n'existe donc pas de scénario de changement de propriétaire entre coopératives à traiter par Sales. La question SALES-026 relative au transfert de produit est sans objet et n'introduit aucune règle de transfert ou de migration de limite commerciale.


### SALES-027 — Produit supprimé alors qu'il figure dans plusieurs paniers

**Statut : VALIDÉ — 19 novembre 2026 (option A)**

Lorsqu'une coopérative supprime un produit présent dans plusieurs paniers clients :

- Chaque panier conserve sa ligne historique ; aucune suppression automatique n'est effectuée dans les paniers concernés.
- La ligne est signalée comme supprimée/indisponible et ne peut plus être achetée.
- Le checkout de chaque panier concerné est bloqué tant que cette ligne y demeure.
- Chaque client doit retirer lui-même la ligne pour poursuivre son checkout.
- Les informations descriptives conservées selon SALES-010 restent disponibles pour identifier l'article.

Cette règle s'applique indépendamment du nombre de paniers concernés et complète SALES-010. La suppression du produit par la coopérative ne doit jamais modifier silencieusement les paniers des clients.


### SALES-028 — Variante supprimée alors qu'elle figure dans plusieurs paniers

**Statut : VALIDÉ — 19 novembre 2026 (option A)**

Lorsqu'une coopérative supprime une variante présente dans plusieurs paniers clients :

- Chaque panier conserve la ligne correspondant à la variante supprimée comme ligne historique non achetable.
- La ligne est signalée comme supprimée/indisponible ; le checkout du panier concerné est bloqué tant qu'elle y demeure.
- Chaque client doit retirer lui-même la ligne pour poursuivre son checkout.
- Aucune substitution automatique par une autre variante, même active, n'est autorisée.
- Les informations initiales utiles à l'identification de la variante restent conservées conformément à SALES-012.

Cette règle complète SALES-012 et SALES-027. La suppression d'une variante ne doit jamais modifier silencieusement les paniers des clients.


### SALES-029 — Réactivation d'un produit temporairement désactivé

**Statut : VALIDÉ — 19 novembre 2026 (option A)**

Lorsqu'un produit est temporairement désactivé par sa coopérative alors qu'il figure dans des paniers clients :

- Les lignes concernées sont conservées dans chaque panier et signalées comme indisponibles pendant la désactivation.
- Le checkout de chaque panier concerné reste bloqué tant qu'une ligne indisponible y demeure.
- Si le produit est ensuite réactivé, les lignes correspondantes redeviennent automatiquement achetables, sans que le client ait à les supprimer puis à les ajouter de nouveau.
- La réactivation ne garantit pas à elle seule l'achat : les autres contrôles applicables restent obligatoires, notamment variante active, prix, limite commerciale, quantité et disponibilité du stock.
- Aucune ligne n'est supprimée ou modifiée silencieusement du seul fait de la désactivation ou de la réactivation.

Cette règle concerne une désactivation temporaire, et non la suppression définitive du produit traitée par SALES-010 et SALES-027.


### SALES-030 — Coopérative inactive ou fermée avec produits présents dans les paniers

**Statut : VALIDÉ — 19 novembre 2026 (option A)**

Lorsqu'une coopérative devient inactive ou ferme son compte alors que ses produits figurent dans des paniers clients :

- Les lignes de panier concernées sont conservées comme lignes historiques non achetables et signalées comme indisponibles.
- Le checkout de chaque panier concerné est bloqué tant qu'une de ces lignes y demeure.
- Chaque client doit retirer manuellement les lignes concernées pour poursuivre son checkout.
- Aucune suppression automatique des lignes et aucune vente maintenue au motif qu'un stock serait encore disponible.
- Les informations utiles à l'identification et à la traçabilité des produits restent conservées conformément aux règles de conservation des lignes de panier.
- Aucun transfert de propriété vers une autre coopérative n'est effectué, conformément à la règle V1 d'absence de transfert de produits entre coopératives.

Cette règle s'applique à l'indisponibilité de la coopérative propriétaire ; elle ne transforme pas une fermeture en transfert de produits ni en autorisation de vente résiduelle.


### SALES-031 — Panier multi-coopératives lorsqu'une coopérative devient inactive

**Statut : VALIDÉ — 19 novembre 2026 (option A)**

Lorsqu'un panier contient des produits de plusieurs coopératives et que l'une d'elles devient inactive ou ferme son compte :

- Les lignes appartenant à cette coopérative sont conservées et signalées comme indisponibles, conformément à SALES-030.
- Le checkout de l'intégralité du panier est bloqué tant que ces lignes indisponibles y demeurent, même si les produits des autres coopératives restent achetables.
- Le client doit retirer manuellement toutes les lignes indisponibles concernées.
- Après leur retrait, le client peut reprendre le checkout avec les lignes restantes, sous réserve des validations habituelles.
- Sales ne doit ni exclure automatiquement les lignes concernées, ni créer automatiquement un checkout ou une commande partielle.

Cette règle préserve le contenu choisi par le client et évite toute modification implicite d'un panier multi-coopératives.


### SALES-032 — Réactivation d'une coopérative et lignes de panier conservées

**Statut : VALIDÉ — 19 novembre 2026 (option A)**

Lorsqu'une coopérative précédemment inactive redevient active :

- Les lignes de ses produits qui ont été conservées dans les paniers conformément à SALES-030 et SALES-031 redeviennent automatiquement achetables.
- Le client n'a pas à supprimer puis à ajouter de nouveau ces produits.
- Le checkout n'est débloqué que si toutes les autres lignes du panier satisfont également les validations habituelles.
- La réactivation de la coopérative ne contourne aucun contrôle relatif au statut du produit ou de sa variante, au prix, aux limites commerciales, aux quantités ou à la disponibilité du stock.
- Aucune ligne n'est supprimée ni modifiée silencieusement lors de la réactivation.

Cette règle complète SALES-030 et SALES-031 et suit le principe de réactivation défini par SALES-029 pour un produit temporairement désactivé.


### SALES-033 — Changement de prix pendant l'indisponibilité d'une coopérative

**Statut : VALIDÉ — 19 novembre 2026 (option A)**

Lorsqu'un produit présent dans un panier devient indisponible parce que sa coopérative est inactive, puis que la coopérative redevient active avec un prix supérieur au prix de référence enregistré lors de l'ajout au panier :

- La ligne redevient disponible à la suite de la réactivation de la coopérative, sous réserve des autres contrôles applicables.
- L'augmentation de prix est détectée et présentée clairement au client.
- Le checkout de la ligne reste bloqué jusqu'à l'acceptation explicite du nouveau prix par le client.
- Aucune hausse ne peut être appliquée silencieusement.
- En cas de refus, le client peut retirer la ligne ou quitter le checkout ; l'ancien prix ne constitue pas un prix garanti.
- Le prix accepté et les informations nécessaires à la traçabilité doivent être transmis à Orders pour le snapshot de commande, conformément à SALES-007.

Cette règle s'applique même si la hausse est intervenue pendant la période d'inactivité de la coopérative. La réactivation ne remet pas à zéro l'obligation d'acceptation explicite d'une hausse.


### SALES-034 — Baisse de prix pendant l'indisponibilité d'une coopérative

**Statut : VALIDÉ — 19 novembre 2026 (option A)**

Lorsqu'un produit présent dans un panier devient indisponible parce que sa coopérative est inactive, puis que la coopérative redevient active avec un prix inférieur au prix de référence enregistré avant l'inactivité :

- La ligne redevient disponible après la réactivation, sous réserve des autres contrôles applicables.
- Le nouveau prix inférieur est automatiquement appliqué à la ligne et communiqué clairement au client.
- Aucune acceptation supplémentaire du client n'est nécessaire pour bénéficier de cette baisse.
- Le prix courant vérifié est transmis à Orders pour le snapshot de commande.
- Les autres validations restent obligatoires ; cette baisse ne contourne notamment pas les contrôles de statut, de limite commerciale, de quantité ou de disponibilité du stock.

Cette règle complète SALES-008 et s'applique même si la baisse est intervenue pendant la période d'inactivité de la coopérative.


### SALES-035 — Variante supprimée pendant l'indisponibilité de la coopérative

**Statut : VALIDÉ — 19 novembre 2026 (option A)**

Lorsqu'une ligne de panier référence une variante qui est supprimée par sa coopérative pendant une période d'inactivité, puis que la coopérative redevient active :

- La ligne conserve la référence et les informations historiques permettant d'identifier la variante initialement choisie.
- La ligne reste signalée comme variante supprimée/indisponible après la réactivation de la coopérative.
- Le checkout du panier reste bloqué tant que le client n'a pas retiré manuellement cette ligne.
- Aucune substitution automatique par une autre variante active du même produit n'est autorisée, même si elle paraît équivalente.
- Aucune suppression automatique de la ligne n'est effectuée.
- Les autres lignes du panier suivent leurs propres règles de disponibilité et de validation.

Cette règle complète SALES-012 et SALES-028. La réactivation de la coopérative ne réactive pas une variante supprimée et ne modifie pas le choix initial du client.

### SALES-036 — Nouvelle variante créée pendant l'indisponibilité de la coopérative

**Statut : VALIDÉ — 19 novembre 2026 (option A)**

Lorsqu'une coopérative inactive crée une nouvelle variante pour un produit existant dans des paniers clients, puis redevient active :

- La nouvelle variante devient disponible dans le catalogue, sous réserve des contrôles habituels de disponibilité et de commercialisation.
- Elle n'est jamais ajoutée automatiquement aux paniers qui contiennent déjà ce produit.
- Elle ne remplace jamais automatiquement une ancienne variante référencée dans une ligne de panier, même si cette ancienne variante a été supprimée.
- Le client doit choisir volontairement la nouvelle variante et l'ajouter lui-même à son panier.
- Les lignes de panier existantes restent soumises à leurs propres règles de disponibilité ; la création d'une nouvelle variante ne réactive pas une variante supprimée et ne contourne pas SALES-035.

Cette règle garantit que l'ajout d'une variante par la coopérative ne vaut jamais choix ou consentement du client.

### SALES-037 — Modification commerciale d'une variante pendant l'indisponibilité de la coopérative

**Statut : VALIDÉ — 19 novembre 2026 (option A)**

Lorsqu'une coopérative modifie une caractéristique commerciale d'une variante déjà référencée dans des paniers clients pendant sa période d'inactivité, sans supprimer cette variante, puis redevient active :

- La ligne de panier conserve les caractéristiques commerciales initialement choisies ; elles ne sont pas remplacées silencieusement par la nouvelle configuration.
- Le changement commercial est détecté et présenté clairement au client.
- Le checkout de la ligne reste bloqué jusqu'à ce que le client accepte explicitement la nouvelle configuration ou retire la ligne.
- Aucune modification commerciale ne peut être considérée comme acceptée du seul fait de la réactivation de la coopérative.
- Les caractéristiques acceptées et les informations nécessaires à la traçabilité doivent être transmises à Orders pour le snapshot de commande.
- Les autres contrôles de disponibilité, de prix, de limite commerciale, de quantité et de stock restent applicables.

Cette règle couvre les changements tels que la contenance, le poids, le conditionnement ou l'unité. Elle complète SALES-011 et les règles d'acceptation explicite des changements commerciaux.


### Complément décisionnel — SALES-019 (20 novembre 2026)

- **Décision utilisateur :** quantité maximale absolue fixée à **100 unités par ligne de panier**, toutes coopératives, produits et variantes confondus.
- Cette borne s'applique aux ajouts, modifications, fusions de paniers et opérations de checkout. Elle ne constitue pas une disponibilité de stock et ne crée aucune réservation.
- Une limite commerciale définie par la coopérative peut être inférieure ou égale à 100, jamais supérieure.
- Toute tentative de quantité supérieure à 100 est rejetée côté serveur ; l'interface ne constitue pas un contrôle de sécurité.
- Tests obligatoires : quantité 100 acceptée, 101 rejetée, fusion produisant 100 acceptée et fusion produisant 101 rejetée, limite commerciale inférieure, limite commerciale supérieure à 100 refusée.
- **Décision utilisateur (21 novembre 2026) :** la précision de quantité est fixée à exactement trois décimales. Les quantités sont stockées en `DECIMAL(15,3)` et toute entrée comportant plus de trois décimales est rejetée, sans arrondi silencieux.
- « 100 » désigne la valeur numérique de quantité, sans conversion automatique d'unité. La limite maximale reste 100 par ligne, quelle que soit l'unité.

### SALES-039 — Deux parcours distincts de commande client

**Statut : VALIDÉ — décision confirmée dans les questions-réponses**

MAHLINE V1 expose deux parcours d'achat indépendants, réservés au rôle `customer` :

1. **Parcours panier** : le client ajoute plusieurs produits au panier, consulte et modifie les lignes, puis choisit « Commander » depuis la page du panier. Le récapitulatif distingue les coopératives commandables et celles exclues pour stock insuffisant. Si au moins une coopérative dispose du stock nécessaire, le client peut confirmer les seules sous-commandes commandables ; toutes les lignes des coopératives exclues restent actives dans le panier. Les frais de livraison et le total sont calculés uniquement pour les sous-commandes effectivement commandables. Si aucune coopérative ne peut être commandée, la confirmation est bloquée.
2. **Parcours commande directe** : depuis une carte de produit ou sa fiche, le client choisit la quantité et, si nécessaire, la variante, puis choisit « Commander ». Cette commande porte exclusivement sur le produit, la variante et la quantité sélectionnés. Elle ne lit, ne fusionne, ne modifie et ne consomme jamais les lignes du panier client existant.

Les deux parcours utilisent les mêmes validations serveur de produit, variante, coopérative, disponibilité Inventory, adresse client, devis Delivery et création idempotente Orders. Le navigateur ne détermine jamais le prix, les frais de livraison ou le total accepté.

Le parcours direct utilise un contexte temporaire de checkout distinct, lié à la session client et protégé par un jeton dont seul le hash est stocké en base. Il ne crée pas un second panier client et n'est pas accessible aux rôles `user`, `cooperative_admin`, `super_admin` ou `super_admin_root`.

### Suivi d'implémentation SALES-039 — 23 novembre 2026

- Ajout d'un aperçu serveur du panier avec regroupement par coopérative, calcul des sous-totaux, devis Delivery et total général.
- Ajout d'une page de confirmation client avec choix d'une adresse du carnet client et confirmation serveur.
- Activation de l'entrée « Commander le panier » depuis la page panier.
- Ajout d'un contexte temporaire isolé pour la commande directe, avec vérification du jeton côté serveur et clé d'idempotence.
- Ajout de l'action « Commander » sur les cartes de produits simples et de l'action « Commander directement » sur la fiche produit ; les produits avec variantes demandent leur sélection sur la fiche.
- Les fichiers modifiés nécessitent encore une revue technique et l'exécution des tests Laravel, Pint et des tests HTTP/checkout avant déclaration de livraison validée.

### SALES-040 — Checkout partiel en cas de stock insuffisant par coopérative

**Statut : VALIDÉ — décision utilisateur du 22 novembre 2026**

Pour un panier contenant des produits de plusieurs coopératives, l'insuffisance de stock est traitée au niveau de la coopérative :

- Si une ou plusieurs lignes d'une coopérative ne peuvent pas être servies intégralement, toutes les lignes de cette coopérative sont exclues de la tentative de commande. Aucune quantité ni ligne de cette coopérative n'est commandée partiellement.
- Les lignes des autres coopératives disposant du stock requis peuvent être commandées dans la même opération.
- Les lignes exclues restent actives et inchangées dans le panier du client ; elles ne sont ni supprimées ni marquées comme commandées.
- L'aperçu affiche explicitement les coopératives et les lignes exclues pour stock insuffisant. Les frais de livraison et totaux affichés ne portent que sur les coopératives incluses.
- Si toutes les coopératives sont insuffisamment approvisionnées, aucune commande n'est créée et le panier est conservé.
- La disponibilité est recalculée dans la transaction de création Orders. Si le stock devient insuffisant après l'aperçu, Orders exclut atomiquement la coopérative concernée et Sales ne consomme que les lignes des sous-commandes réellement créées.
- Cette exclusion automatique ne s'applique pas à une coopérative inactive/supprimée, à un produit ou une variante non vendable, à une hausse de prix non acceptée ou à une modification commerciale non acceptée : ces situations continuent de bloquer le checkout jusqu'à résolution explicite par le client.
- Cette décision complète et précise SALES-009 et SALES-013. Elle ne remplace pas SALES-031 : une coopérative inactive continue de bloquer l'ensemble du checkout conformément à cette règle.

