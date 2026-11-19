# MAHLINE V7.0 — Feuille de route centrale des domaines

- **Version :** 1.0
- **Mise à jour :** 19 novembre 2026
- **Dépôt :** `areda10/mahline_v1`
- **Branche de référence :** `feature/Marketplace`
- **Statut :** registre de suivi — périmètres à auditer et décisions à consolider

> Ce document est le point d'entrée central du suivi des domaines MAHLINE. Il complète les cahiers de décisions propres à chaque domaine. Il ne remplace pas les spécifications détaillées et ne signifie pas qu'un domaine est déjà implémenté, audité ou validé.

## 0. Registre canonique des ADR V1

Le registre canonique des décisions d'architecture applicables à MAHLINE V1 est désormais [docs/architecture/decisions/README.md](decisions/README.md).

- La série ADR-001 à ADR-020 de MAHLINE V1 est distincte des anciens ADR du framework.
- `docs/framework/ADR.md` est conservé comme historique du framework ; ses numéros ne doivent pas être confondus avec ceux de V1.
- Les sources des ADR V1 001 à 008 n'ont pas été retrouvées dans l'arbre Git vérifié. Leur contenu ne sera pas reconstitué par supposition.
- Les ADR V1 009 à 020 sont indexés dans le registre canonique.
- La déclaration de portée de l'ADR-019 reste enregistrée ; la complétude documentaire des ADR 001 à 008 demeure à régulariser.

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

Sur la branche `feature/Marketplace`, l'arborescence `app/Domains/` contient actuellement les dossiers suivants :

- `Audit`
- `Catalog`
- `Categories`
- `Cooperatives`
- `Dashboard`
- `Identity`
- `Localization`
- `Media`
- `Orders`
- `Products`

Cet inventaire décrit uniquement la présence de dossiers dans le dépôt au moment de cette mise à jour. Il ne constitue pas une validation fonctionnelle ou technique de ces domaines.

**Référence vérifiée pour cette révision :** branche `feature/Marketplace`, HEAD `c5981e490f3f15f8f1672b589a1c962a1c51abc2`, arbre Git récursif. Dix dossiers sont présents. Le domaine `Orders` est présent et ne doit plus être suivi comme absent. Le rapport `MAHLINE_V1_REPOSITORY_AUDIT.md` du 17 novembre est fondé sur un ancien HEAD (`184d10db20305fa60cdc6b1835f6a6a51bd5aa1d`) ; ses chiffres et conclusions de présence/absence ne sont pas la référence actuelle.

Les documents d'architecture décrivent également des domaines ou périmètres prévus qui ne figurent pas dans cet inventaire, notamment `Inventory`, `Sales`, `Finance`, `Media`, `Communication` et `CMS`. Cette différence devra être traitée progressivement, sans créer de dossiers ni déplacer du code avant validation de leur périmètre.

## 3. Registre central des domaines

| Domaine | Périmètre de référence | Présence dans app/Domains (inventaire) | Suivi |
|---|---|---:|---|
| Identity | Utilisateurs, rôles, authentification, sessions, mots de passe, API, sécurité et confidentialité | Oui | En audit — écarts initiaux consignés le 8 novembre 2026 ; voir le cahier Identity |
| Cooperatives | Organisations, rattachements, statuts et gestion des coopératives | Oui | Audit fonctionnel en cours / suivi dédié à maintenir |
| Catalog | Catalogue commercial et règles de présentation des produits | Oui | À auditer après consolidation des frontières avec Products et Categories |
| Categories | Arborescence et règles de catégories globales et coopératives | Oui | Décisions et tests déjà travaillés ; conserver le suivi dédié |
| Products | Données et cycle de vie des produits | Oui | À auditer selon les frontières Catalog / Products |
| Localization | Adresses, référentiel des pays, régions, langues, devises, fuseaux horaires et données géographiques | Oui | Countries intégré dans Localization ; V1 carnet d'adresses à tester et frontières inter-domaines à finaliser |
| Audit | Journalisation métier, traçabilité et consultation sécurisée | Oui | À auditer indépendamment des simples colonnes techniques |
| Dashboard | Présentation des indicateurs et vues de pilotage | Oui | À cadrer avec Statistics |
| Media | Fichiers et ressources média transversales consommés par plusieurs domaines | Oui | Présent dans le dépôt ; audit V1 et tests à poursuivre |
| Inventory | Stocks, mouvements, réservations, entrepôts et ajustements | Non | À planifier |
| Orders | Commandes client, lignes, sous-commandes coopératives et cycle de vie | Oui | Présent dans le dépôt ; audit métier/architecture à terminer et frontières avec Inventory, Delivery et Finance à consolider |
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
10. Auditer les autres domaines présents ou prévus : Audit, Localization, Dashboard, Communication et CMS.

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


### 8 novembre 2026 — Audit Identity : auditabilité et sécurité des sessions
- Inspection du journal d'audit, des événements de sécurité, des consultations de fiches et de la gestion des sessions.
- Constats détaillés : section 20.8 de `docs/identity/USER_MANAGEMENT_DECISIONS_AND_TRACKING.md`.
- Les composants existants sont distingués des exigences non couvertes ; aucun code applicatif modifié.


## 9. Suivi détaillé — Inventory V1 (19 novembre 2026)

**Spécification de référence :** [`docs/domains/marketplace/INVENTORY_V1_SPECIFICATION.md`](../domains/marketplace/INVENTORY_V1_SPECIFICATION.md).

- **Présence du domaine :** absent de `app/Domains/` au commit de référence `72e27c4b32aefd09c2118e631f2aec0433627e84`.
- **État fonctionnel :** règles métier initiales consolidées à partir des décisions validées ; identifiants INV-001 à INV-016 attribués.
- **État technique :** audit initial ciblé effectué ; aucun code Inventory, aucune migration Inventory, aucun service de réservation et aucune intégration Orders–Inventory à cette référence.
- **Priorité immédiate :** compléter l'audit technique des migrations, modèles Products/ProductVariant, unités, permissions, policies, Audit et transactions Orders avant de figer le schéma.
- **Dépendances :** Products, Orders, Cooperatives/Identity, Catalog, Delivery, Audit et Media.
- **Statut du domaine :** **En audit / spécification initiale créée** — ne pas déclarer implémenté ou validé.

### Règles de pilotage Inventory

1. Aucun codage avant validation du schéma et des contrats transactionnels.
2. La confirmation d'une sous-commande doit réserver toutes ses lignes actives ou échouer intégralement ; aucune réservation partielle.
3. Le checkout et le délai d'attente coopérative de 12 heures ne réservent aucun stock.
4. Les mouvements sont immuables ; toute correction passe par un mouvement compensatoire.
5. Les fonctions sont suivies par identifiants INV-001 à INV-016 dans la spécification dédiée.
6. Après codage : audit complet des fonctions, sécurité, tests, permissions et liaisons inter-domaines ; les tests locaux restent « À exécuter » tant qu'aucun résultat vérifiable n'est fourni.

### Journal

- **19 novembre 2026 :** création de la spécification et du suivi Inventory V1. Aucun fichier applicatif modifié, aucune migration créée, aucun test exécuté.


## 10. Décision architecturale — contrôle des dépendances inter-domaines (19 novembre 2026)

**Décision 9 validée :** les domaines ne peuvent dépendre les uns des autres qu'à travers des contrats publics explicitement exposés. Les imports vers les composants internes d'un domaine fournisseur sont interdits.

- Les dépendances autorisées sont orientées, justifiées et inscrites dans un registre central.
- Les modèles Eloquent, repositories, migrations, Actions/Services internes, contrôleurs, composants Infrastructure et détails de persistance d'un autre domaine ne sont pas accessibles directement.
- Les dépendances circulaires sont interdites.
- Les échanges synchrones passent par des contrats publics ; les notifications de faits métier passent par des événements publics.
- Les frontières doivent être contrôlées par des tests architecturaux automatisés intégrés à la CI.
- Toute nouvelle dépendance ou exception doit être approuvée et documentée avant implémentation.

**Document de décision :** [ADR-009 — Contrôle des dépendances entre domaines](decisions/ADR-009-domain-dependencies.md).

**État :** décision d'architecture acceptée ; audit des dépendances réelles, définition des surfaces publiques, registre détaillé et contrôles automatisés restent à réaliser. Aucun code applicatif ni test n'a été modifié ou exécuté dans le cadre de cette décision.

### Journal — 19 novembre 2026
- Ajout de l'ADR-009 et référencement dans la feuille de route centrale.
- Aucun changement applicatif, aucune migration et aucune modification de configuration CI.

## 11. Décision architecturale — propriété des configurations des domaines (19 novembre 2026)

**Décision 10 validée :** adopter une approche hybride avec propriété stricte des configurations.

- Les paramètres techniques et environnementaux restent dans la configuration Laravel (fichiers config/ et variables d'environnement).
- Chaque paramètre métier appartient à un seul domaine.
- Les paramètres métier modifiables à l'exécution sont stockés dans des tables détenues par leur domaine propriétaire.
- Les valeurs par défaut statiques métier peuvent être définies dans le code ou dans une configuration propre au domaine lorsque cela est utile.
- Les domaines consommateurs accèdent aux paramètres métier par les contrats publics du domaine propriétaire ; aucun accès direct à ses modèles, repositories ou tables internes n'est autorisé.
- Les règles de validation, portées, permissions, traçabilité et invalidation/versionnement du cache doivent être explicites.
- Les secrets restent gérés par les mécanismes techniques adaptés et ne sont pas enregistrés comme paramètres métier ordinaires.

**Document de décision :** [ADR-010 — Propriété et gestion des configurations des domaines](decisions/ADR-010-domain-configuration-ownership.md).

**État :** décision d'architecture acceptée ; inventaire et classification des configurations existantes, définition des contrats et éventuelle migration restent à réaliser. Aucun changement de code, de configuration Laravel, de table ou de migration n'a été effectué dans le cadre de cette décision.

### Journal — 19 novembre 2026
- Ajout de l'ADR-010 et référencement dans la feuille de route centrale.
- Aucun changement applicatif, aucune migration et aucun test exécuté.

## 12. Décision architecturale — répartition de la responsabilité des autorisations (19 novembre 2026)

**Décision 11 validée :** adopter une approche hybride séparant les habilitations générales des autorisations métier.

- Identity reste propriétaire des comptes, de l'authentification, des rôles, de leurs affectations et des permissions générales.
- Chaque domaine métier reste propriétaire des autorisations portant sur ses ressources, leur propriété, leur périmètre, les restrictions coopératives et les opérations ou transitions métier.
- Les contrôles peuvent combiner une permission générale issue d'Identity et une règle métier du domaine propriétaire de la ressource.
- Les Policies, Gates et services d'application coordonnent les vérifications sans transférer à Identity la propriété des règles métier.
- Les refus sont appliqués par défaut lorsque l'autorisation ou le contexte ne peut pas être établi.
- Les règles inter-domaines suivent les contrats publics et les frontières définies dans l'ADR-009.

**Document de décision :** [ADR-011 — Répartition de la responsabilité des autorisations](decisions/ADR-011-authorization-ownership.md).

**État :** décision d'architecture acceptée ; inventaire et classification des rôles, permissions, Gates, Policies, middleware et contrôles existants restent à réaliser. Aucune conformité du code actuel n'est présumée. Aucun code, rôle, permission, table ou migration n'a été modifié dans le cadre de cette décision.

### Journal — 19 novembre 2026
- Ajout de l'ADR-011 et référencement dans la feuille de route centrale.
- Aucun changement applicatif et aucun test exécuté.

## 13. Décision architecturale — fiabilité et cycle de vie des événements métier (19 novembre 2026)

**Décision 12 validée :** adopter une approche hybride, avec un niveau de fiabilité adapté à la criticité de chaque événement.

- Les événements inter-domaines sont des contrats publics documentés et versionnés ; ils représentent des faits métier, pas des commandes.
- Les événements annonçant une écriture métier sont publiés après le commit ; un rollback ne doit jamais annoncer un fait annulé.
- Le Transactional Outbox est utilisé lorsque la perte d'un événement est inacceptable ou qu'une livraison durable et reprenable est nécessaire ; il n'est pas imposé à tous les événements.
- Les consommateurs sont idempotents et reconnaissent les doublons à l'aide d'un identifiant d'événement stable.
- Les tentatives, erreurs, délais, reprises et échecs à traiter sont observables et contrôlés.
- La rétention et les payloads sont minimisés selon les besoins métier, opérationnels, légaux et d'audit.
- Les événements internes restent distincts des contrats publics inter-domaines.

**Document de décision :** [ADR-012 — Fiabilité et cycle de vie des événements métier](decisions/ADR-012-domain-events-reliability.md).

**État :** stratégie acceptée ; inventaire et catalogue des événements, classification de criticité, mécanismes Outbox éventuels, idempotence et procédures de reprise restent à auditer et concevoir. Aucun événement existant n'est déclaré conforme. Aucun code, table, migration, worker ou configuration de queue n'a été modifié.

### Journal — 19 novembre 2026
- Ajout de l'ADR-012 et référencement dans la feuille de route centrale.
- Aucun changement applicatif et aucun test exécuté.


## 14. Décision architecturale — traitements asynchrones et files d'attente (19 novembre 2026)

**Décision 13 validée :** adopter une architecture hybride avec infrastructure de queue commune au départ et isolation logique des traitements selon leur domaine, leur catégorie et leur criticité.

- Les files sont séparées logiquement lorsque cela est utile, sans multiplier prématurément les infrastructures indépendantes.
- Les traitements lourds ne doivent pas bloquer les tâches critiques ; les priorités et pools de workers sont adaptés aux besoins mesurés.
- Chaque job appartient à un domaine et les traitements inter-domaines utilisent exclusivement les contrats publics ou événements autorisés.
- Les jobs transportent des identifiants et des données minimales sérialisables ; les données et états métier nécessaires sont rechargés et revérifiés à l'exécution.
- Les retries sont bornés et définis par traitement : backoff, timeout, tentatives maximales et gestion des échecs définitifs.
- L'idempotence et la déduplication protègent contre les exécutions répétées ; aucune garantie d'exécution exactement une fois n'est présumée.
- L'ordre d'exécution n'est pas garanti par défaut ; tout besoin d'ordre strict doit être explicite.
- Le dispatch respecte les commits transactionnels ; les événements critiques suivent les règles de l'ADR-012 et peuvent nécessiter un Transactional Outbox.
- La supervision doit rendre visibles les files d'attente, l'ancienneté, les durées, les erreurs, les reprises et l'état des workers.
- L'architecture permet d'extraire ultérieurement des workers ou files sur des ressources dédiées lorsque des besoins mesurés le justifient.

**Document de décision :** [ADR-013 — Traitements asynchrones et architecture des files d'attente](decisions/ADR-013-async-processing-and-queues.md).

**État :** architecture cible acceptée ; inventaire des jobs et tâches planifiées, classification de criticité, choix du backend, dimensionnement des workers, politiques de reprise, observabilité et tests restent à définir ou auditer. Aucun job, worker, queue, table, migration, configuration ou code applicatif n'a été créé ou modifié dans le cadre de cette décision.

### Journal — 19 novembre 2026
- Ajout de l'ADR-013 et référencement dans la feuille de route centrale.
- Aucun changement applicatif, aucune migration et aucun test exécuté.


## 15. Décision architecturale — observabilité technique et traçabilité transversale (19 novembre 2026)

**Décision 14 validée :** adopter une observabilité hybride, avec infrastructure technique commune et responsabilité métier conservée par chaque domaine.

- Les logs techniques, l'audit métier, les événements de sécurité, les métriques et les traces distribuées sont des catégories distinctes ; aucune ne remplace les autres.
- Les logs techniques et métriques peuvent être centralisés, mais cette centralisation ne confère pas un accès général aux journaux d'audit ou de sécurité.
- Chaque domaine reste propriétaire de ses événements métier et de leur interprétation.
- Un `correlation_id` suit un workflow métier ; un `trace_id` et des identifiants de span permettent de suivre son exécution technique à travers les appels et jobs.
- Les identifiants sont propagés dans les appels synchrones, événements et traitements asynchrones lorsque le contexte est disponible.
- Les secrets, mots de passe, tokens, codes OTP et données sensibles inutiles sont exclus des logs ; les données personnelles et financières sont minimisées, masquées ou pseudonymisées.
- Les métriques couvrent notamment latence, erreurs, disponibilité, files, jobs, retries, événements et santé des workers.
- Les accès, durées de conservation, exports, alertes et procédures d'investigation sont définis par catégorie de données.
- Les conventions restent indépendantes d'un fournisseur afin de préparer le tracing distribué et l'extraction future de domaines ou workers.

**Document de décision :** [ADR-014 — Observabilité technique et traçabilité transversale](decisions/ADR-014-observability-and-tracing.md).

**État :** architecture cible acceptée ; inventaire des mécanismes existants, conventions de logs, propagation des identifiants, règles de masquage, accès, rétentions, métriques, alertes et tests restent à définir ou auditer. Aucun outil, infrastructure, configuration, table, migration ou code applicatif n'a été créé ou modifié dans le cadre de cette décision.

### Journal — 19 novembre 2026
- Ajout de l'ADR-014 et référencement dans la feuille de route centrale.
- Aucun changement applicatif, aucune migration et aucun test exécuté.


## 16. Décision architecturale — sauvegardes et reprise après incident (19 novembre 2026)

**Décision 15 validée :** adopter une stratégie centralisée de sauvegarde et de reprise cohérente à l'échelle de la plateforme.

- La couverture inclut la base de données, les fichiers et objets Media, les documents à conserver, ainsi que les éléments techniques et secrets indispensables à la reconstruction, avec des mécanismes de protection adaptés.
- Les sauvegardes sont automatisées, chiffrées et stockées hors de l'environnement principal ; elles bénéficient d'une protection contre l'altération ou la suppression malveillante.
- La restauration doit préserver la cohérence entre les données et les fichiers référencés, avec contrôles d'intégrité et détection des objets manquants ou orphelins.
- Les événements durables et traitements asynchrones sont réconciliés selon les règles d'idempotence ; les intégrations externes sont vérifiées avant toute répétition d'une opération à effet externe.
- Les objectifs RPO (perte temporelle maximale acceptable) et RTO (durée cible de reprise) sont définis par criticité métier ; aucune valeur chiffrée n'est fixée à ce stade.
- Des procédures de restauration documentées, autorisées et journalisées couvrent les scénarios de panne, corruption, erreur humaine et compromission.
- Des tests périodiques de restauration complète et partielle sont nécessaires ; l'existence d'une sauvegarde ne constitue pas une preuve de restaurabilité.
- Les fournisseurs, fréquences, rétentions, mécanismes précis et objectifs chiffrés restent à déterminer après inventaire de l'hébergement et analyse d'impact.

**Document de décision :** [ADR-015 — Sauvegardes et reprise après incident](decisions/ADR-015-backup-and-disaster-recovery.md).

**État :** stratégie cible acceptée ; inventaire de l'infrastructure et du stockage, classification des données, RPO/RTO, choix des mécanismes, cohérence base/fichiers, runbooks et exercices de restauration restent à définir. Aucun système de sauvegarde, script, stockage, compte, configuration, table, migration ou code applicatif n'a été créé ou modifié dans le cadre de cette décision.

### Journal — 19 novembre 2026
- Ajout de l'ADR-015 et référencement dans la feuille de route centrale.
- Aucun changement applicatif, aucune migration et aucun test exécuté.


## 17. Décision architecturale — environnements et promotion des versions (19 novembre 2026)

**Décision 16 validée :** adopter quatre environnements de référence — `local`, `testing`, `staging` et `production` — avec séparation stricte à l'échelle de la plateforme.

- Chaque environnement dispose de configurations, secrets, bases de données, stockages et ressources externes séparés selon les besoins ; aucun environnement inférieur ne partage directement les ressources modifiables de production.
- Les secrets et comptes de production ne sont jamais copiés vers les environnements non productifs.
- Testing utilise des données synthétiques ; staging privilégie également des données synthétiques représentatives. Toute exception impliquant des données dérivées de production exige autorisation, minimisation et anonymisation ou pseudonymisation vérifiée.
- Les intégrations non productives utilisent des sandboxes ou doubles de test ; aucun paiement, envoi, expédition ou autre effet réel ne doit être déclenché par défaut.
- La CI exécute des contrôles reproductibles et traçables ; staging valide les parcours transversaux, intégrations, migrations et configurations avant promotion.
- La promotion vers production est contrôlée et associée à un artefact/version, un commit, un environnement et un responsable.
- Les migrations doivent être compatibles avec des déploiements progressifs lorsque nécessaire ; rollback applicatif et restauration des données sont traités comme deux opérations distinctes.
- Les accès production sont limités, journalisés et accordés selon le moindre privilège.
- Les sauvegardes et restaurations suivent l'ADR-015 ; les procédures de déploiement et de reprise sont testées avant d'être considérées fiables.

**Document de décision :** [ADR-016 — Stratégie des environnements et promotion des versions](decisions/ADR-016-environments-and-release-promotion.md).

**État :** architecture cible acceptée ; inventaire des environnements et ressources existants, séparation des secrets et données, données synthétiques, CI/CD, contrôles de promotion, stratégie de migrations et procédures de rollback restent à définir ou auditer. Aucun environnement, pipeline, compte, secret, configuration, migration ou code applicatif n'a été créé ou modifié dans le cadre de cette décision.

### Journal — 19 novembre 2026
- Ajout de l'ADR-016 et référencement dans la feuille de route centrale.
- Aucun changement applicatif, aucune migration et aucun test exécuté.


## 18. Décision architecturale — versionnement et compatibilité des contrats inter-domaines (19 novembre 2026)

**Décision 17 validée :** adopter un versionnement raisonné des contrats publics, en privilégiant la rétrocompatibilité et en créant une nouvelle version lorsqu'une évolution casse le contrat existant.

- Le cadre couvre les contrats synchrones, DTO publics, événements métier, API et messages asynchrones consommés hors du domaine producteur.
- Les composants purement internes ne sont pas soumis à un versionnement public artificiel.
- Les ajouts ne sont rétrocompatibles que si les champs, valeurs, unités, sémantiques et invariants existants restent valides pour les consommateurs.
- Toute rupture (suppression/renommage, changement de type, nullabilité, unité, sémantique ou garantie comportementale) impose une version distincte et une période de coexistence adaptée.
- La dépréciation est annoncée et documentée ; le retrait attend la migration des consommateurs et le traitement des messages historiques encore en circulation.
- Les événements et messages asynchrones identifient explicitement leur version ; les versions inconnues ou non prises en charge suivent une stratégie observable et sûre, sans interprétation silencieuse.
- Les tests de contrats couvrent producteurs, consommateurs, compatibilité, coexistence et déploiements progressifs.
- Chaque contrat public possède un producteur propriétaire, une finalité, une version et des consommateurs identifiés.
- La convention précise de numérotation, les formats et les outils seront définis après inventaire du patrimoine existant.

**Document de décision :** [ADR-017 — Versionnement et compatibilité des contrats inter-domaines](decisions/ADR-017-contract-versioning-and-compatibility.md).

**État :** stratégie acceptée ; inventaire des contrats et consommateurs, convention de versionnement, catalogue, tests de compatibilité et procédures de dépréciation/retrait restent à définir ou auditer. Aucun contrat, événement, job, API, code, configuration ou pipeline n'a été modifié dans le cadre de cette décision.

### Journal — 19 novembre 2026
- Ajout de l'ADR-017 et référencement dans la feuille de route centrale.
- Aucun changement applicatif, aucune migration et aucun test exécuté.


## 19. Décision architecturale — gouvernance automatisée des frontières entre domaines (19 novembre 2026)

**Décision 18 validée :** mettre en place une gouvernance architecturale automatisée, progressive et obligatoire dans la CI, avant toute promotion vers staging puis production.

- Les contrôles vérifient les imports interdits vers les composants internes des autres domaines et l'utilisation exclusive des contrats publics ou événements autorisés.
- Les dépendances inter-domaines doivent être déclarées, orientées et justifiées ; les cycles sont interdits.
- La propriété des migrations et des données est contrôlée afin qu'un domaine ne modifie pas les tables internes d'un autre domaine.
- Les contrats publics, événements et messages sont vérifiés selon les règles de versionnement et de compatibilité de l'ADR-017.
- Les exceptions sont explicites, limitées, motivées, attribuées et documentées ; aucune dérogation implicite n'est admise.
- Les rapports identifient la règle, les domaines concernés et le fichier ou symbole à corriger.
- Les contrôles complètent les tests applicatifs et de contrats ; ils ne les remplacent pas.
- Un audit initial du patrimoine et des violations existantes précède l'activation de tous les contrôles bloquants ; la trajectoire de correction ne doit pas autoriser de nouvelles violations.

**Document de décision :** [ADR-018 — Gouvernance automatisée de l'architecture et des frontières entre domaines](decisions/ADR-018-automated-architecture-governance.md).

**État :** stratégie acceptée ; inventaire des dépendances, définition des règles exécutables, registre des exceptions, choix des outils et intégration CI restent à réaliser. Aucun code applicatif, migration, configuration, pipeline CI/CD ou test n'a été modifié ou exécuté dans le cadre de cette décision.

### Journal — 19 novembre 2026
- Ajout de l'ADR-018 et référencement dans la feuille de route centrale.
- Aucun changement applicatif, aucune migration et aucun test exécuté.


## 20. Décision de gouvernance — applicabilité des décisions d'architecture à V1 (19 novembre 2026)

**Décision 19 validée :** les décisions d'architecture 1 à 18 (ADR-001 à ADR-018) constituent le cadre de référence de **MAHLINE V1**.

- Elles doivent être prises en compte dans les audits, spécifications, contrats inter-domaines, données, autorisations, tests, environnements et choix d'exploitation de V1.
- Toute nouvelle décision, précision ou modification validée doit être consignée dans un ADR et référencée dans cette feuille de route ; une validation en conversation seule ne suffit pas.
- Toute révision doit conserver la traçabilité de la décision précédente et expliquer le motif du changement.
- Les décisions acceptées définissent une cible et ne prouvent pas que le code actuel est conforme ou que la fonctionnalité est implémentée.
- Les choix qui n'ont pas encore été arbitrés restent ouverts. La stratégie CI/CD et d'approbation des promotions est désormais fixée par la décision 20, documentée dans l'ADR-020.
- Les documents restent sur `feature/Marketplace` ; aucun merge vers `main` sans instruction explicite.

**Document de décision :** [ADR-019 — Applicabilité des décisions d'architecture à MAHLINE V1](decisions/ADR-019-v1-architecture-decision-scope.md).

**État :** règle de gouvernance acceptée pour V1 ; les audits, implémentations et validations techniques restent à réaliser domaine par domaine. Aucun code applicatif, migration, configuration, pipeline ou test n'a été modifié ou exécuté dans le cadre de cette décision.

### Journal — 19 novembre 2026
- Ajout de l'ADR-019 et référencement dans la feuille de route centrale.
- Confirmation que les ADR-001 à ADR-018 s'appliquent à MAHLINE V1.
- Aucun changement applicatif, aucune migration et aucun test exécuté.


## 21. Décision architecturale — stratégie CI/CD avec approbations humaines (19 novembre 2026)

**Décision 20 validée — question 19 : option C.** MAHLINE V1 adopte une CI/CD automatisée avec approbations humaines aux étapes critiques.

- Les contrôles reproductibles (qualité, tests, sécurité pertinente, contrats et architecture) sont automatisés.
- Les opérations répétitives de livraison et de déploiement sont automatisées autant que possible.
- Les promotions sensibles, notamment vers la production, exigent une approbation humaine explicite d'une personne autorisée.
- L'approbation est traçable et liée au commit/artefact et à l'environnement cible.
- Un contrôle obligatoire en échec bloque la promotion ; l'approbation humaine ne permet pas de le contourner.
- Les secrets, runners et droits de déploiement suivent le moindre privilège et la séparation des environnements.
- L'outil, les responsables habilités, les étapes exactes d'approbation et les stratégies opérationnelles restent à spécifier.

**Document de décision :** [ADR-020 — Stratégie CI/CD avec approbations humaines aux étapes critiques](decisions/ADR-020-ci-cd-human-approvals.md).

**État :** décision d'architecture V1 acceptée ; pipeline, contrôles, approbations, déploiements, vérifications post-déploiement et procédures de retour arrière restent à concevoir et implémenter. Aucun pipeline, secret, environnement, configuration, code applicatif, migration ou test n'a été modifié ou exécuté dans le cadre de cette décision.

### Journal — 19 novembre 2026
- Ajout de l'ADR-020 et référencement dans la feuille de route centrale.
- Mise à jour de l'ADR-019 pour indiquer que la question 19, ouverte à cette date, a été arbitrée par l'ADR-020.
- Aucun changement applicatif, aucune migration et aucun test exécuté.


## 22. Plan de correction transversal — audit du 19 novembre 2026

Le plan détaillé et ordonné est enregistré dans [IMPLEMENTATION_REMEDIATION_PLAN.md](IMPLEMENTATION_REMEDIATION_PLAN.md). Il constitue le plan d'exécution de référence après l'audit transversal. Les statuts y distinguent les constats prouvés, les contrôles restant à faire et les changements proposés.

### Corrections documentaires immédiates
- Rectification de l'inventaire : `Orders` est présent dans `app/Domains` et les migrations marketplace sont présentes.
- Signalement de l'ancienneté du rapport d'audit du 17 novembre.
- Enregistrement du conflit de numérotation entre `docs/framework/ADR.md` (ADR-001 à ADR-015 historiques) et `docs/architecture/decisions/ADR-009` à `ADR-020`.
- Les ADR-001 à ADR-008 du cadre V1 doivent être localisés et reliés au registre canonique ; aucune renumérotation ni suppression n'est autorisée sans traçabilité.

### Journal — 19 novembre 2026
- Ajout du plan de correction transversal, avec priorités, séquencement, fichiers cibles, dépendances et critères de sortie.
- Correction de la présence du domaine Orders dans l'inventaire courant.
- Aucun code métier, aucune migration, configuration ou test applicatif modifié/exécuté dans cette mise à jour documentaire.
- Aucun merge vers `main`.


### 19 novembre 2026 — Correction du registre ADR
- Création de `docs/architecture/decisions/README.md` comme index canonique de la série V1.
- Clarification du statut historique de `docs/framework/ADR.md`, sans suppression ni renumérotation de ses décisions.
- ADR V1 001 à 008 classés « sources à retrouver » ; aucune équivalence avec les anciens ADR du framework n'est supposée.
- Correction de la date de mise à jour et d'une parenthèse erronée dans la référence de l'ancien commit d'audit.


## 10. Architecture hybride et audit des dépendances (19 novembre 2026)

- Architecture cible confirmée : monolithe modulaire hybride, base SQL partagée, contrats publics synchrones, événements après commit, Outbox réservée aux faits critiques.
- Décision détaillée : [ADR-021](decisions/ADR-021-hybrid-modular-monolith.md).
- Cartographie et corrections proposées : [DOMAIN_DEPENDENCY_AUDIT.md](DOMAIN_DEPENDENCY_AUDIT.md).
- `Countries` a été absorbé dans `Localization` sur `feature/Marketplace` ; il ne constitue plus un domaine autonome.
- La consolidation du modèle Country et de ses références est intégrée ; tests d'exécution encore à réaliser.


### 19 novembre 2026 — contrats inter-domaines intégrés
- Audit expose désormais `AuditWriter` ; les services des domaines consommateurs n'injectent plus son service concret ni son repository.
- Products expose `CheckoutCatalog` ; Orders consomme un DTO checkout et ne lit plus directement les modèles Product/ProductVariant.
- Référence du code intégré : `f8f7535b1ae98879738c6aad0b0eef614b97a29f` sur `feature/Marketplace`.
- Tests PHP/Pint non exécutés ; validation locale requise avant clôture.
- Prochain lot : contrats de propriétaires Media, pivots Catalog/Categories/Products, policies/providers propriétaires, contrat Address, puis registre FK/triggers et tests d'architecture.
