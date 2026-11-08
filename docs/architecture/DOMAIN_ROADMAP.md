# MAHLINE V7.0 — Feuille de route centrale des domaines

- **Version :** 1.0
- **Mise à jour :** 8 novembre 2026
- **Dépôt :** `areda10/mahline_v1`
- **Branche de référence :** `feature/Identity`
- **Statut :** registre de suivi — périmètres à auditer et décisions à consolider

> Ce document est le point d'entrée central du suivi des domaines MAHLINE. Il complète les cahiers de décisions propres à chaque domaine. Il ne remplace pas les spécifications détaillées et ne signifie pas qu'un domaine est déjà implémenté, audité ou validé.

## 1. Règles de suivi

1. Un domaine ne doit être audité qu'une fois par périmètre et version de référence. Toute reprise doit consulter ce registre et le cahier de décisions du domaine.
2. Les décisions validées sont conservées dans le document du domaine concerné, puis référencées ici.
3. Avant toute modification de code : inventorier les fichiers réels, comparer le code aux décisions, présenter les écarts et proposer les changements fichier par fichier.
4. Ne jamais déclarer un test « vert » sans résultat d'exécution vérifiable. Les tests non exécutés restent « À exécuter ».
5. Toute évolution du périmètre, dépendance ou responsabilité doit être documentée avant implémentation.
6. Conserver les commits, preuves, tests et points restant à faire afin d'éviter de recommencer les mêmes tâches.

### États autorisés

- **À cadrer** : périmètre ou décisions métier incomplets.
- **À auditer** : périmètre défini, audit du code à réaliser.
- **En audit** : vérification du code et des tests en cours.
- **Écarts à corriger** : audit effectué, corrections proposées ou attendues.
- **En implémentation** : changements approuvés en cours.
- **À tester** : implémentation présente, tests à exécuter ou compléter.
- **Validé** : décisions, code et tests vérifiés pour le périmètre déclaré.
- **Bloqué** : dépendance ou décision empêchant d'avancer.

## 2. État du dépôt — inventaire observé

Sur la branche `feature/Identity`, l'arborescence `app/Domains/` contient actuellement les dossiers suivants :

- `Audit`
- `Catalog`
- `Categories`
- `Cooperatives`
- `Countries`
- `Dashboard`
- `Identity`
- `Localization`
- `Products`

Cet inventaire décrit uniquement la présence de dossiers dans le dépôt au moment de cette mise à jour. Il ne constitue pas une validation fonctionnelle ou technique de ces domaines.

Les documents d'architecture décrivent également des domaines ou périmètres prévus qui ne figurent pas dans cet inventaire, notamment `Inventory`, `Sales`, `Finance`, `Media`, `Communication` et `CMS`. Cette différence devra être traitée progressivement, sans créer de dossiers ni déplacer du code avant validation de leur périmètre.

## 3. Registre central des domaines

| Domaine | Périmètre de référence | Présence dans app/Domains (inventaire) | Suivi |
|---|---|---:|---|
| Identity | Utilisateurs, rôles, authentification, sessions, mots de passe, API, sécurité et confidentialité | Oui | En audit — écarts initiaux consignés le 8 novembre 2026 ; voir le cahier Identity |
| Cooperatives | Organisations, rattachements, statuts et gestion des coopératives | Oui | Audit fonctionnel en cours / suivi dédié à maintenir |
| Catalog | Catalogue commercial et règles de présentation des produits | Oui | À auditer après consolidation des frontières avec Products et Categories |
| Categories | Arborescence et règles de catégories globales et coopératives | Oui | Décisions et tests déjà travaillés ; conserver le suivi dédié |
| Products | Données et cycle de vie des produits | Oui | À auditer selon les frontières Catalog / Products |
| Countries | Référentiel des pays | Oui | À inventorier et auditer |
| Localization | Adresses, langues, devises, fuseaux horaires et données géographiques selon spécification | Oui | À inventorier et auditer |
| Audit | Journalisation métier, traçabilité et consultation sécurisée | Oui | À auditer indépendamment des simples colonnes techniques |
| Dashboard | Présentation des indicateurs et vues de pilotage | Oui | À cadrer avec Statistics |
| Media | Fichiers et ressources média transversales consommés par plusieurs domaines | Non | À planifier |
| Inventory | Stocks, mouvements, réservations, entrepôts et ajustements | Non | À planifier |
| Orders | Commandes client, lignes, cycle de vie et éventuelle segmentation par coopérative | Non | À cadrer : le document DOMAIN_BOUNDARIES place actuellement Order dans Sales |
| Delivery | Expédition, transport, tarifs, suivi, statuts et documents de livraison | Non | À cadrer : le document DOMAIN_BOUNDARIES place actuellement Delivery dans Sales |
| Finance / Facturation | Factures client et coopérative, paiements, remboursements, commissions, reversements et transactions | Non | À planifier ; Finance est le domaine de référence prévu pour la facturation |
| Statistics | Agrégation et analyse des indicateurs produits et, à terme, commandes, stocks, livraisons et ventes | Non | Domaine autonome approuvé — périmètre détaillé à définir |
| Communication | E-mails, notifications, modèles et suivi des envois | Non | À planifier selon l'architecture |
| CMS | Pages, contenus éditoriaux et contenus administrables | Non | À planifier selon l'architecture |

## 4. Décision validée — Statistics

**Décision :** Statistics est un domaine autonome.

Responsabilités envisagées :
- produire des indicateurs et agrégations à partir des données des domaines propriétaires ;
- proposer des statistiques produits (consultations, ventes, performance, disponibilité selon les données disponibles) ;
- permettre ultérieurement des analyses sur les commandes, coopératives, stocks, livraisons et ventes ;
- alimenter des tableaux de bord sans devenir propriétaire des données sources.

Règles de séparation :
- Catalog / Products restent propriétaires des informations produit.
- Orders reste propriétaire des commandes et de leur cycle de vie.
- Inventory reste propriétaire des stocks et mouvements.
- Delivery reste propriétaire des expéditions et du suivi de livraison.
- Finance reste propriétaire des factures, paiements et calculs financiers.
- Statistics consomme des contrats, événements ou projections de données autorisés ; il ne modifie pas directement les données métier des domaines sources.
- Les indicateurs doivent préciser leur définition, période, fuseau horaire, devise éventuelle, source et règles d'actualisation.

**État :** décision d'autonomie validée ; spécification fonctionnelle et technique à cadrer.

## 5. Points d'architecture à arbitrer avant gel

### 5.1 Orders et Delivery par rapport à Sales

Le document `docs/framework/DOMAIN_BOUNDARIES.md` décrit actuellement `Sales` comme propriétaire de `Order`, `OrderItem` et `Delivery`. La présente feuille de route enregistre le souhait d'étudier `Orders` et `Delivery` comme domaines distincts.

Aucune séparation n'est considérée comme définitive à ce stade. Il faudra décider :
- si `Sales` reste un domaine englobant ou si ses responsabilités sont réparties entre `Orders` et d'autres domaines ;
- si `Delivery` devient autonome, avec ses propres modèles, statuts, tarifs et documents ;
- comment représenter une commande contenant des produits de plusieurs coopératives (commande globale et sous-commandes / segments coopératifs) ;
- quels contrats et événements relient Orders, Inventory, Delivery, Finance et Communication.

### 5.2 Facturation et Finance

La facturation est suivie dans `Finance`, conformément aux frontières d'architecture existantes. Le périmètre devra distinguer clairement :
- facture du client ;
- documents financiers dus aux coopératives ;
- paiements, remboursements et commissions ;
- rapprochement, reversements et traçabilité.

Ne pas créer un domaine séparé `Billing` sans décision d'architecture explicite.

### 5.3 Media transversal

Media doit être le propriétaire des fichiers et ressources média partagés. Les domaines consommateurs conservent le sens métier de leurs pièces jointes (photo produit, document coopérative, image CMS, etc.). Les domaines ne doivent pas recréer chacun leur propre stockage média indépendant.

### 5.4 Catalog, Categories et Products

L'arborescence contient trois dossiers distincts, tandis que les documents d'architecture attribuent à Catalog les concepts Product et Category. Les frontières et responsabilités réelles doivent être vérifiées avant de figer cette organisation.

## 6. Ordre de travail proposé

L'ordre ci-dessous est une proposition de pilotage, pas une validation de dépendances d'implémentation :

1. Terminer l'audit Identity et mettre à jour son cahier de suivi.
2. Terminer et clôturer l'audit Cooperatives, puis auditer la gestion des utilisateurs déjà rattachée à Identity.
3. Consolider les frontières Catalog / Categories / Products.
4. Définir Orders et le scénario marketplace multi-coopératives.
5. Auditer et spécifier Inventory en cohérence avec Orders.
6. Définir Delivery et ses interactions avec Orders et Inventory.
7. Spécifier Finance / Facturation, notamment les documents client et coopérative.
8. Définir Media comme service métier transversal.
9. Spécifier Statistics autonome et ses contrats de lecture avec les domaines sources.
10. Auditer les autres domaines présents ou prévus : Audit, Localization, Countries, Dashboard, Communication et CMS.

L'ordre pourra être ajusté selon les dépendances confirmées pendant les audits.

## 7. Fiche de suivi à maintenir pour chaque domaine

Pour chaque domaine, conserver les informations suivantes dans son propre cahier de suivi :

- Objectif métier et périmètre inclus / exclus.
- Concepts, modèles, tables et propriétaire de chaque donnée.
- Rôles, permissions, règles d'accès et limites coopératives.
- Flux métier, états, transitions et cas limites.
- Dépendances entrantes et sortantes, contrats et événements.
- Fichiers existants inventoriés et écarts confirmés.
- Décisions validées, questions ouvertes et arbitrages.
- Migrations, services, actions, policies, contrôleurs, routes, notifications et interfaces concernés.
- Tests unitaires, intégration, Feature / HTTP, sécurité, multi-coopératives et régression.
- Résultats d'exécution, preuves, commit et date de validation.
- Tâches restantes, blocages et prochaine étape précise.

## 8. Journal des mises à jour

### 8 novembre 2026 — Version 1.0
- Création du registre central des domaines.
- Inventaire des dossiers présents dans `app/Domains/` sur `feature/Identity`.
- Enregistrement de la décision : Statistics est un domaine autonome.
- Signalement des arbitrages nécessaires concernant Sales / Orders / Delivery et Catalog / Categories / Products.
- Confirmation que la facturation relève du périmètre Finance dans l'architecture existante.


### 8 novembre 2026 — Point de contrôle Identity
- Audit en lecture seule poursuivi sur `feature/Identity`.
- Premiers écarts confirmés consignés dans `docs/identity/USER_MANAGEMENT_DECISIONS_AND_TRACKING.md` (section 20).
- Référence : commit d'audit de départ `926cf33b09f93f9e651dafd259fa57b3d6f4c6aa`.
- L'audit Identity reste ouvert ; aucune modification du code applicatif n'a été effectuée.


### 8 novembre 2026 — Complément du contrôle Identity
- Ajout des constats sur le changement d'e-mail personnel, la vérification 24 h, la gestion des rôles existants, les transferts de coopérative, les alertes nouvel appareil, l'OTP, les messages de connexion et la gestion des tokens API.
- Référence détaillée : section 20.5 et 20.6 de `docs/identity/USER_MANAGEMENT_DECISIONS_AND_TRACKING.md`.
- Aucun code applicatif modifié ; audit toujours ouvert.


### 8 novembre 2026 — Audit Identity : politique de mot de passe et sessions
- Vérification des règles de mot de passe, du reset, des migrations utilisateurs, des sessions et des routes API.
- Constats détaillés : section 20.7 de `docs/identity/USER_MANAGEMENT_DECISIONS_AND_TRACKING.md`.
- Aucun changement de code applicatif ; audit en lecture seule.
