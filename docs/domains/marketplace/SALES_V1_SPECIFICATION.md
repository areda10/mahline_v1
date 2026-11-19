# MAHLINE V1 — Spécification fonctionnelle Sales

- **Domaine :** Sales
- **Version :** 1.0 — cadrage initial
- **Date :** 19 novembre 2026
- **Branche :** `feature/Marketplace`
- **Statut :** EN COURS — décision d'architecture et mode de panier validés ; règles détaillées à définir.
- **Référence d'architecture :** [ADR-021 — Architecture hybride du monolithe modulaire](../../architecture/decisions/ADR-021-hybrid-modular-monolith.md)

> Ce document est le registre des décisions fonctionnelles et techniques du domaine Sales. Une règle non validée reste explicitement ouverte. Aucune migration ou implémentation ne doit être créée sur la base d'une supposition.

## 1. Objectif et responsabilité

Sales possède le parcours commercial précommande :
- panier invité et panier client authentifié ;
- lignes et regroupement des articles dans le panier ;
- calcul et présentation du récapitulatif commercial ;
- promotions et coupons, après définition de leurs règles ;
- devis, après définition de leur cycle de vie ;
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

La règle de fusion des lignes est définie par SALES-003. Les contrôles de quantités maximales, articles devenus indisponibles ou inactifs, prix modifiés et conflits concurrents restent à spécifier avant implémentation.

### SALES-002 — Séparation des domaines

**Statut : VALIDÉ — 19 novembre 2026**

Sales, Orders, Inventory, Delivery, Finance et Communication sont des domaines autonomes dans le monolithe modulaire. Chacun est propriétaire de ses modèles, tables, règles métier, contrats et tests.

Orders reste le domaine propriétaire de la commande après sa création. Finance reste propriétaire des documents et opérations financières. Sales ne devient pas un domaine englobant ces responsabilités.

## 4. Frontières fonctionnelles du parcours

1. Le client consulte le catalogue ; Catalog / Products restent les sources de vérité des données catalogue.
2. Sales ajoute et conserve les articles dans le panier.
3. Sales demande aux domaines propriétaires les informations nécessaires au calcul et à la validation.
4. Les règles de promotions et de devis s'appliquent uniquement selon les décisions qui seront validées.
5. Au checkout, Sales prépare une demande complète et validée.
6. Sales transmet cette demande au contrat public de création d'Orders.
7. Orders crée la commande globale et ses sous-commandes ; Sales ne modifie ensuite ni les lignes ni les statuts de cette commande.
8. Le checkout ne réserve aucun stock. La réservation intervient uniquement lors de la confirmation de la sous-commande par la coopérative, selon la spécification Inventory.

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

## 6. Décisions encore ouvertes

Les sujets suivants doivent être arbitrés avant le gel fonctionnel et le schéma :
- fusion des paniers invité et client : limites de quantité maximale et arbitrage des conflits concurrents ;
- dépassement du stock disponible dans le panier : autorisé ; contrôle de disponibilité à effectuer au checkout selon SALES-009 ;
- durée de vie du panier invité et du panier authentifié ;
- durée de conservation du panier invité : 30 jours sans activité (SALES-004) ;
- panier authentifié conservé sans expiration automatique, jusqu'à suppression volontaire ou transformation en commande (SALES-005) ;
- traitement d'une ligne dont le produit ou la variante devient inactive ou n'est plus vendable : règle validée par SALES-006 ;
- traitement d'une modification du prix catalogue entre ajout au panier et checkout : hausse soumise à acceptation explicite et baisse appliquée automatiquement (SALES-007 et SALES-008) ;
- moment et durée de validité du prix affiché ;
- règles d'arrondi et de calcul des totaux, taxes et éventuels frais ;
- promotions : périmètre global ou par coopérative, cumul, priorité, plafonds, période, éligibilité et limites d'utilisation ;
- coupons : unicité, attribution, limites d'utilisation et contrôle contre les abus ;
- devis : acteurs, durée de validité, acceptation, expiration et conversion en commande ;
- checkout : validations, idempotence, double soumission, concurrence et gestion des erreurs partielles ;
- suppression du panier après création réussie de la commande et conservation de la preuve de checkout ;
- droits de consultation et de gestion des paniers pour les rôles MAHLINE.

## 7. Suivi d'implémentation

Aucun modèle, migration, route, contrôleur ou service Sales n'est déclaré implémenté par ce document. Le développement commencera après validation des règles métier essentielles et audit des contrats réels de Catalog, Products, Localization et Orders.

Les tests requis devront couvrir au minimum : panier invité, panier connecté, fusion et conflits, multi-coopératives, prix obsolètes, promotions, devis, checkout idempotent, sécurité des accès, et non-régression Orders / Inventory.

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

**Statut : VALIDÉ — 19 novembre 2026 (option D)**

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
- Le checkout est bloqué tant que la quantité demandée dépasse la disponibilité vérifiée.
- Le client doit lui-même réduire la quantité à un niveau disponible ou retirer la ligne.
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

1. **Limite technique globale de la plateforme :** une borne maximale absolue protège MAHLINE contre les quantités aberrantes, les abus et les valeurs hors capacité technique. Elle s'applique à toutes les lignes, quels que soient le produit et la coopérative.
2. **Limite commerciale configurable :** la coopérative propriétaire peut définir une quantité maximale propre à chaque produit ou variante, adaptée à ses conditions de vente.

Règles d'application :

- La quantité demandée ne peut dépasser ni la limite technique globale ni, lorsqu'elle est configurée, la limite commerciale du produit ou de la variante.
- Sales refuse tout ajout, fusion ou modification qui ferait dépasser l'une de ces limites et indique au client la quantité maximale applicable.
- Pour un produit avec variantes, la limite commerciale est déterminée au niveau de la variante ; pour un produit sans variante, elle est déterminée au niveau du produit.
- La limite commerciale ne peut jamais permettre de dépasser la limite technique globale.
- Ces limites sont indépendantes du stock : une quantité autorisée dans le panier ne réserve pas le stock et ne garantit pas sa disponibilité. Les contrôles de disponibilité restent ceux de SALES-009, SALES-013 et du domaine Inventory.
- Les règles s'appliquent également à la fusion des paniers (SALES-003) et aux modifications concurrentes (SALES-017) ; aucune de ces opérations ne peut contourner les limites.

Restent à préciser avant implémentation : les valeurs numériques minimale et maximale de la limite technique, le comportement lorsqu'aucune limite commerciale n'est configurée, les droits de modification de la limite par la coopérative, ainsi que le traitement d'une limite abaissée alors qu'une quantité supérieure se trouve déjà dans un panier.

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


### Précision de périmètre — transfert de produit entre coopératives

**Décision confirmée — 19 novembre 2026**

Aucun transfert de produit d'une coopérative vers une autre n'est prévu dans MAHLINE V1. Un produit reste rattaché à sa coopérative propriétaire ; il n'existe donc pas de scénario de changement de propriétaire entre coopératives à traiter par Sales. La question SALES-026 relative au transfert de produit est sans objet et n'introduit aucune règle de transfert ou de migration de limite commerciale.
