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
- `Sales`
- `Inventory`
- `Delivery`
- `Finance`
- `Statistics`

Cet inventaire décrit la présence des dossiers racines dans le dépôt au moment de cette mise à jour. Pour Sales, Inventory, Delivery, Finance et Statistics, la présence correspond à un README de domaine et à une racine applicative ; elle ne signifie pas que les services métier, modèles, migrations, routes, policies ou providers sont implémentés.

**Référence historique :** le contrôle du 19 novembre 2026 recensait dix domaines avant le scaffold Marketplace. Les cinq dossiers ajoutés ensuite sont documentés dans le journal A21. `Orders` était déjà présent et reste le propriétaire des commandes ; il ne doit pas être absorbé dans Sales.

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
| Inventory | Stocks, mouvements, réservations, quarantaine et disponibilité | Oui (README) | Domaine créé ; cadrage métier présent ; audit technique et conception à poursuivre |
| Orders | Commandes client, lignes, sous-commandes coopératives et cycle de vie | Oui | Présent dans le dépôt ; audit métier/architecture à terminer et frontières avec Inventory, Delivery et Finance à consolider |
| Delivery | Transporteurs, tarifs, devis, missions, expéditions, suivi, preuves et retours | Oui (README) | Domaine créé ; spécification partielle ; arbitrages restants avant schéma et code |
| Finance | Facturation, paiements, remboursements, commissions, rapprochements et reversements | Oui (README) | Domaine créé ; règles consolidées ; arbitrages fiscaux et audit avant implémentation |
| Statistics | Définitions KPI, projections, agrégations et rapports analytiques | Oui (README) | Domaine créé ; spécification initiale existante ; catalogue KPI et accès à valider |
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

## 5. Frontières d'architecture confirmées

### 5.1 Sales, Orders et Delivery

La séparation est désormais explicite : Sales possède le panier et le checkout précommande ; Orders possède la commande globale, les sous-commandes, les lignes et le cycle de vie commercial ; Delivery possède les missions, transporteurs, tarifs, expéditions, preuves et retours physiques. Aucun de ces domaines ne doit importer les modèles ou services privés des deux autres.

Les contrats et événements nécessaires doivent être conçus et inscrits au registre avant intégration. La spécification Delivery fixe désormais le départage des transporteurs ex æquo au tarif minimal après 12 heures par la première acceptation valide, avec attribution atomique.

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
- Référence du code intégré : `3d1c91db28362a38919d7fa1e5084dc862001d69` sur `feature/Marketplace`.
- Tests PHP/Pint non exécutés ; validation locale requise avant clôture.
- Prochain lot : contrats de propriétaires Media, pivots Catalog/Categories/Products, policies/providers propriétaires, contrat Address, puis registre FK/triggers et tests d'architecture.


### 19 novembre 2026 — autorisations par domaine
- `AddressPolicy` est maintenant sous Localization ; `CooperativePolicy` sous Cooperatives.
- Chaque domaine enregistre ses policies et Gates par son provider, chargé depuis `bootstrap/providers.php`.
- Tests de policies déplacés dans les dossiers de leurs domaines. Validation PHPUnit locale encore requise.


### 19 novembre 2026 — providers d'autorisation propriétaires
- Enregistrements des policies Audit, Catalog, Categories, Cooperatives, Localization, Media et Products déplacés dans leurs providers locaux.
- Identity conserve l'enregistrement de UserPolicy et le mécanisme générique d'autorisations.
- Vérification d'exécution du boot et des policies à effectuer localement.


### 19 novembre 2026 — contrat Cooperatives / Localization
- Cooperatives utilise désormais `CooperativeAddressManager` pour créer, supprimer logiquement et restaurer les adresses.
- AddressService reste propriétaire de l'implémentation et est exposé via un contrat public.
- Les transactions existantes et les invariants d'adresse restent inchangés ; tests locaux requis.


### Référence du lot du 19 novembre 2026
- Code intégré sur `feature/Marketplace` : `3d1c91db28362a38919d7fa1e5084dc862001d69`.
- Contrats intégrés : AuditWriter, CheckoutCatalog, CooperativeAddressManager.
- Providers de policies locaux enregistrés pour Audit, Catalog, Categories, Cooperatives, Localization, Media et Products.

## 14. Reprise d'exécution — état réel au 19 novembre 2026

**Référence vérifiée :** HEAD `2945c62a34d928f277cc02a57a0e22d0b6f62a51` sur `feature/Marketplace`.

La feuille de route est maintenue comme registre de pilotage. Les commits récents ont principalement corrigé des attentes de tests et des namespaces ; ils ne constituent pas une validation globale des domaines. Les tests PHPUnit/Pint n'ont pas été exécutés par l'assistant.

### 14.1 Lots d'architecture déjà intégrés

- Countries absorbé par Localization ; aucun domaine Countries autonome.
- Contrat public `AuditWriter` et adaptateur d'implémentation intégrés.
- Contrat Products `CheckoutCatalog` / DTO `CheckoutProductData` utilisé par Orders pour le checkout.
- Policies et providers redistribués vers les domaines propriétaires pour les domaines déjà traités.
- Contrat `CooperativeAddressManager` exposé par Localization et consommé par Cooperatives.
- ADR-021 enregistré comme architecture de référence.

Ces éléments sont **intégrés dans le code**, mais ne valent pas validation de toutes les dépendances, tests ou règles de chaque domaine.

### 14.2 Premier audit de frontière restant — Media

L'inspection du HEAD courant confirme que Media n'est pas encore découplé de ses propriétaires :
- `MediaCategory` importe directement `Product`, `Cooperative` et `User` pour déterminer les propriétaires admis.
- `MediaService` importe directement les trois modèles et utilise le modèle User comme acteur.
- `MediaOwnerObserver` importe les trois modèles pour gérer restauration, suppression logique et suppression définitive.
- `MediaPolicy` connaît directement les types Product, Cooperative et User et leurs règles d'accès.
- `AppServiceProvider` enregistre l'observer Media sur les modèles Product, Cooperative et User, au lieu de laisser chaque provider propriétaire déclarer son adaptateur.
- Les modèles propriétaires exposent des relations Eloquent vers le modèle Media. Ces relations doivent être traitées comme une dépendance d'intégration explicite ; elles ne seront pas retirées sans remplacement compatible avec les écrans et services existants.

**Décision d'exécution :** avant tout refactor, définir les contrats publics d'identification/propriété média dans les domaines fournisseurs, les DTO de contexte d'acteur nécessaires, les adaptateurs d'intégration et le point d'assemblage. Conserver le comportement V1 : catégories, visibilité, quotas, autorisations, verrous concurrents, nettoyage des fichiers, restauration, rétention et audit. Aucun changement métier ou migration ne doit être introduit au seul motif du découplage.

**Statut :** audit statique ciblé réalisé ; contrats et tests de caractérisation à définir ; implémentation non commencée dans ce lot.

### 14.3 Ordre d'exécution restant

1. Media — contrats propriétaires et découplage sans régression, puis tests ciblés et tests de concurrence.
2. Catalog / Categories / Products — ownership des pivots `catalog_product` et `category_product`, contrats de lecture/commande, suppression des imports privés et tests.
3. Identity / Cooperatives — finaliser le contrat d'affectation des super-admins et clarifier l'ownership de `super_admin_cooperative`.
4. Inventory — terminer l'audit des tables, migrations, états, réservations et transactions Orders avant création du domaine et de ses migrations métier.
5. Orders — compléter l'audit du cycle de vie, des snapshots, des sous-commandes multi-coopératives et des contrats Inventory/Delivery/Finance.
6. Delivery — cadrer le périmètre autonome, les expéditions, tarifs, transporteurs, suivi et documents ; aucune table avant spécification validée.
7. Finance / Facturation — cadrer factures immuables, paiements, remboursements, commissions, reversements et rapprochement ; conserver Finance comme propriétaire.
8. Statistics — définir projections et agrégations consommant des contrats/événements sans devenir propriétaire des données sources.
9. Audit, Localization, Dashboard, Communication et CMS — terminer les audits de périmètre, sécurité, dépendances et tests.
10. Architecture transversale — registre exhaustif FK/triggers/pivots, événements versionnés et Outbox critique, tests d'architecture et CI bloquante progressive.

Cet ordre est un plan d'exécution progressif, pas une déclaration que les domaines suivants sont déjà spécifiés ou validés. Les domaines Inventory, Delivery, Finance, Statistics, Communication et CMS ne seront créés qu'après audit et gel de leur périmètre, de leurs contrats et de leurs tables propriétaires.

### 14.4 Règles de clôture

Un lot ne peut être marqué « Validé » qu'après :
- implémentation et revue des contrats/public APIs ;
- tests unitaires, intégration, Feature, sécurité et régression adaptés ;
- exécution locale confirmée par l'utilisateur ou preuve CI consultable ;
- Pint et contrôles statiques applicables ;
- mise à jour du cahier du domaine, de ce registre et du commit de référence.

Aucune fusion vers `main` n'est autorisée sans instruction explicite.

## 15. Inventaire global réconcilié — 19 novembre 2026

**Audit de référence :** `docs/architecture/DOMAIN_DEPENDENCY_AUDIT.md`, section 10.  
**HEAD inventorié :** `ad3de3395a794e965973493716fbae1e0ca358bb`.  
**Commit de mise à jour de l'audit :** `10756fe898e0513c2a409ecf2c575d53a403aee1`.

### Domaines présents

| Domaine | Fichiers | État de suivi |
|---|---:|---|
| Identity | 99 | À compléter : audit fonctionnel, contrats vers les domaines et tests complets |
| Media | 21 | À découpler des modèles Product/Cooperative/User |
| Orders | 13 | À compléter : parcours HTTP, contrats Inventory/Delivery/Finance et événements |
| Products | 12 | À compléter : contrats Catalog/Categories/Media et tests d'intégration |
| Audit | 11 | À compléter : couverture de toutes les opérations et validation MySQL |
| Catalog | 8 | À compléter : ownership du pivot et contrats Products/Categories |
| Cooperatives | 7 | À compléter : frontière d'affectation avec Identity et intégration |
| Localization | 7 | À compléter : contrats de propriétaire d'adresse et validation MySQL |
| Categories | 6 | À compléter : ownership du pivot category_product |
| Dashboard | 2 | À développer : contrats de lecture/projections et contrôle des périmètres |

### Domaines absents à construire

- **Inventory** : spécification V1 existante INV-001 à INV-016 ; audit technique détaillé obligatoire avant schéma et code.
- **Delivery** : périmètre validé en partie ; critères d'égalité des tarifs et cas d'échec à arbitrer.
- **Finance** : inclut Facturation, encaissements, commissions, transferts, remboursements, reversements et rapprochement ; les paramètres et exceptions restent à préciser.
- **Communication** : notifications in-app et email V1 ; SMS exclu. La table Laravel notifications existante ne constitue pas le domaine.
- **Statistics** : autonomie du domaine déjà validée ; spécification et contrats de lecture à construire.
- **CMS** : domaine prévu, mais pas de spécification V1 suffisante ; cadrage préalable requis.

### Domaines non décidés comme domaines autonomes

- **Sales** : ne pas créer à ce stade. Les documents antérieurs le décrivent tantôt comme propriétaire de commandes/livraisons, alors que l'ADR-021 actuel donne la propriété des commandes à Orders et suit Delivery séparément. Son éventuel périmètre panier/checkout doit être arbitré.
- **Invoicing/Billing** : ne pas créer séparément ; Facturation relève de Finance selon la feuille de route et les règles métier.
- **Countries** : ne pas recréer ; absorbé par Localization.
- **Incidents** : les incidents Media restent dans Media. Aucun périmètre d'incidents généraux n'est validé pour un domaine autonome.

### Ordre d'exécution après audit global

1. Corriger les frontières des domaines existants : Media, Catalog/Categories/Products, Identity/Cooperatives, Localization, Orders, Dashboard.
2. Auditer techniquement Inventory puis l'implémenter par lots verticaux.
3. Compléter Orders (API/HTTP, événements, tests et intégration Inventory).
4. Cadrer et implémenter Delivery.
5. Cadrer et implémenter Finance / Facturation.
6. Créer Communication et Statistics.
7. Cadrer CMS.
8. Décider Sales et Incidents avant toute création.

**Statut global :** l'inventaire et l'audit statique des principales relations sont mis à jour ; les domaines ne sont pas déclarés terminés. La validation MySQL, l'audit exhaustif des FK/triggers, les tests d'architecture et les suites PHPUnit/Pint restent à réaliser.

## 16. Audit technique Inventory — complément réalisé le 19 novembre 2026

- **Rapport global préalable :** `docs/architecture/DOMAIN_DEPENDENCY_AUDIT.md`, section 10.
- **Rapport Inventory :** `docs/domains/marketplace/INVENTORY_V1_SPECIFICATION.md`, section 11.
- **Commit de l'audit global :** `10756fe898e0513c2a409ecf2c575d53a403aee1`.
- **Commit du complément Inventory :** `08d5093304fbd4adbe643c344d952e2ee8d367af`.

L'audit technique ciblé a vérifié les modèles et contrats Products, les unités V1, les migrations de prix/unités, les modèles et services Orders, ainsi que la migration marketplace Orders.

Constats structurants :
- Products est propriétaire du produit, de la variante, de l'unité et du prix de référence.
- Orders est propriétaire des commandes, sous-commandes, lignes, snapshots et statuts commerciaux.
- Le checkout n'écrit aucune réservation Inventory.
- Le service de réponse coopérative verrouille la sous-commande et la commande globale ; la confirmation ne déclenche actuellement aucun appel Inventory.
- Le contrat de checkout Products ne fournit pas encore de disponibilité.
- Les nouvelles tables Inventory devront référencer logiquement les IDs des domaines voisins par défaut ; les FK historiques Orders ne sont pas modifiées.
- Le schéma Inventory n'est pas encore gelé ; les règles d'idempotence et quelques transitions doivent être raccordées explicitement avant création des tables.

**Statut :** audit global préalable effectué ; audit technique Products/Orders initial effectué ; conception détaillée du schéma et des contrats Inventory reste à finaliser. Aucun code Inventory, aucune migration Inventory et aucun test n'ont été créés ou exécutés dans ce lot.



## 12. Sales V1 — cadrage commencé le 19 novembre 2026

Spécification de référence : [Sales V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).

Décisions validées :
- panier invité et authentifié ; fusion du panier invité avec le panier du compte à la connexion (SALES-001) ;
- fusion des lignes uniquement lorsque toutes les caractéristiques commerciales sont identiques (SALES-003) ;
- conservation du panier invité pendant 30 jours sans activité (SALES-004) ;
- conservation du panier authentifié sans expiration automatique, jusqu'à suppression volontaire ou création réussie d'une commande (SALES-005) ;
- conservation et signalement des lignes devenues indisponibles ; checkout bloqué jusqu'au retrait volontaire par le client, sans suppression automatique (SALES-006) ;
- si le prix augmente après ajout au panier, acceptation explicite obligatoire ; si le prix baisse, application automatique avec information du client (SALES-007 et SALES-008) ;
- le panier peut contenir une quantité supérieure au stock disponible ; il ne réserve jamais le stock et la disponibilité est contrôlée aux étapes Inventory prévues (SALES-009) ;
- Sales, Orders, Inventory, Delivery, Finance et Communication sont autonomes dans le monolithe modulaire, chacun propriétaire de ses modèles, tables, règles, contrats et tests (SALES-002) ;
- Sales possède le parcours précommande ; Orders possède la commande après création ; Finance possède les opérations financières.

Le cadrage détaillé reste ouvert : limites de quantité lors des fusions, règles de suppression des lignes et nettoyage après commande, prix, promotions, coupons, devis, idempotence et validations du checkout. Aucun code Sales ni migration Sales n'est déclaré réalisé.

Ordre de travail confirmé par l'utilisateur : Sales → Inventory → Orders → Delivery → Finance → Communication. Pour chaque domaine : cadrage métier, audit technique des contrats et fichiers réels, validation, puis implémentation et tests. L'ordre de travail ne modifie pas les frontières de propriété ni les dépendances nécessaires entre domaines.


### Journal Sales — 19 novembre 2026
- SALES-006 : lorsqu'un produit ou une variante du panier devient inactif(ve) ou non vendable, la ligne est conservée et signalée ; le checkout est bloqué jusqu'à son retrait volontaire par le client. Aucune suppression automatique.
- Spécification détaillée : [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).
- Mise à jour documentaire uniquement : aucun code, migration ou test modifié/exécuté.


- SALES-007 : tout changement de prix entre l'ajout au panier et le checkout doit être présenté au client et accepté explicitement ; aucun nouveau prix ne peut être appliqué silencieusement. Détail dans [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).


- SALES-008 : toute baisse de prix entre l'ajout au panier et le checkout est appliquée automatiquement et signalée au client ; aucune acceptation n'est requise. Les hausses restent soumises à acceptation explicite (SALES-007). Détail dans [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).


- SALES-009 : le panier accepte une quantité supérieure au stock actuellement disponible. Le panier ne constitue pas une réservation ; les contrôles de disponibilité relèvent d'Inventory aux étapes métier prévues. Les limites absolues de quantité restent ouvertes. Voir [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).


- SALES-010 : si un produit est supprimé du catalogue alors qu'il figure au panier, Sales conserve une ligne historique non achetable (nom, prix de référence et informations descriptives disponibles à l'ajout), la signale comme indisponible et bloque le checkout jusqu'à son retrait manuel par le client. Aucune suppression automatique ni possibilité d'achat ne subsiste. Voir [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).


- SALES-011 : les informations descriptives non tarifaires d'un produit au panier sont actualisées automatiquement, tout en conservant les données initiales utiles à l'identification et à la traçabilité. Les changements de caractéristiques commerciales sont détectés et traités explicitement ; aucune modification silencieuse du prix accepté ou des conditions de vente n'est permise. Voir [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).


- SALES-012 : si une variante du panier est désactivée alors que le produit parent ou d'autres variantes restent actifs, la ligne de la variante choisie est conservée comme indisponible et bloque le checkout jusqu'à son retrait manuel. Aucune substitution automatique par une autre variante n'est permise. Voir [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).


- SALES-013 : si le stock disponible diminue après l'ajout au panier et devient inférieur à la quantité demandée, Sales conserve cette quantité sans la corriger automatiquement, signale l'insuffisance et bloque le checkout jusqu'à ce que le client réduise ou retire la ligne. La disponibilité reste revérifiée auprès d'Inventory ; le panier ne réserve pas le stock. Voir [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).


- SALES-014 : vider le panier retire logiquement toutes ses lignes actives, tout en conservant les lignes retirées dans un historique interne de traçabilité. L'opération ne crée aucune commande et ne modifie ni commandes, ni factures, ni stock. La rétention du panier invité (30 jours) et celle du panier authentifié (sans expiration automatique) restent inchangées. Voir [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).


- SALES-015 : une ligne supprimée du panier ne peut pas être restaurée depuis l'historique. Le client doit rechercher à nouveau le produit dans le catalogue et l'ajouter comme un nouvel article, soumis aux prix, statuts et contrôles de disponibilité actuels. L'historique reste disponible uniquement pour la traçabilité. Voir [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).


- SALES-016 : le panier d'un client authentifié est unique au niveau du compte et synchronisé entre ses appareils. Les modifications doivent être répercutées sans perte ni écrasement silencieux en cas de concurrence ; les mécanismes techniques restent à définir à l'audit. Voir [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).



- SALES-017 : les modifications simultanées du panier authentifié sont protégées par versionnement. Une opération basée sur une version obsolète est refusée et l'appareil doit recharger le panier avant de réessayer ; aucun écrasement silencieux. Voir [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).

- SALES-018 : après création réussie d'une commande par Orders, Sales retire logiquement du panier uniquement les lignes effectivement incluses dans cette commande ; les lignes non commandées restent dans le panier. En cas d'échec ou de résultat incertain, aucune consommation ne doit être présumée. L'atomicité, les reprises et l'idempotence restent à définir à l'audit technique. Voir [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).
- SALES-019 : les quantités du panier sont encadrées par une limite technique globale absolue de la plateforme et, si configurée, une limite commerciale propre au produit ou à la variante définie par la coopérative. La limite commerciale ne peut dépasser la limite technique ; les contrôles s'appliquent aux ajouts, fusions et modifications. Les valeurs numériques et cas de changement de limite restent à préciser. Voir [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).
- SALES-020 : lorsqu'une limite commerciale est abaissée sous une quantité déjà présente au panier, Sales conserve la quantité sans la réduire automatiquement, signale le dépassement et bloque le checkout jusqu'à ce que le client réduise ou retire la ligne. La limite courante est revérifiée au checkout. Voir [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).
- SALES-021 : en l'absence de limite commerciale configurée pour un produit ou une variante, seule la limite technique globale de MAHLINE s'applique. L'absence de configuration commerciale ne bloque pas l'ajout au panier ni, à elle seule, le checkout. Voir [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).
- SALES-022 : gestion des limites commerciales autorisée à `cooperative_admin` pour sa coopérative, `super_admin` pour ses coopératives affectées et `super_admin_root` pour toutes les coopératives ; `user` n'a aucun droit de modification. Vérification du périmètre obligatoire côté serveur. Voir [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).
- SALES-023 : toute diminution de limite commerciale s'applique immédiatement à tous les paniers existants. Les lignes dépassant la nouvelle limite sont conservées sans réduction automatique, mais bloquent le checkout jusqu'à réduction ou retrait manuel par le client. Voir [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).
- SALES-024 : toute augmentation de limite commerciale s'applique immédiatement à tous les paniers existants concernés et peut lever un blocage causé par l'ancienne limite, sans modification artificielle du panier. Les autres contrôles et la limite technique globale restent applicables. Voir [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).
- SALES-025 : la suppression d'une limite commerciale prend effet immédiatement sur tous les paniers existants ; seule la limite technique globale MAHLINE reste applicable aux quantités. Les autres contrôles commerciaux et de disponibilité demeurent obligatoires. Voir [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).

- Précision Sales V1 — aucun transfert de produit entre coopératives n'est prévu : un produit reste rattaché à sa coopérative propriétaire. La question SALES-026 sur le changement de coopérative propriétaire est sans objet ; aucune fonctionnalité de transfert ni migration de limite commerciale n'est à planifier. Voir [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).
- SALES-027 : lorsqu'un produit supprimé figure dans plusieurs paniers, chaque ligne est conservée comme historique indisponible ; le checkout de chaque panier est bloqué jusqu'au retrait manuel par son client. Aucune suppression automatique des lignes n'est permise. Voir [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).
- SALES-028 : lorsqu'une variante supprimée figure dans plusieurs paniers, chaque ligne est conservée comme historique indisponible ; le checkout de chaque panier est bloqué jusqu'au retrait manuel par son client. Aucune suppression automatique ni substitution par une autre variante n'est permise. Voir [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).
- SALES-029 : les lignes de panier d'un produit temporairement désactivé sont conservées et indisponibles ; après réactivation, elles redeviennent automatiquement achetables, sous réserve des autres contrôles (variante, prix, limite commerciale, quantité et stock). Aucun retrait/réajout manuel n'est requis. Cette règle ne s'applique pas à une suppression définitive. Voir [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).
- SALES-030 : si une coopérative devient inactive ou ferme son compte, les lignes de ses produits présentes dans les paniers sont conservées comme indisponibles ; le checkout est bloqué jusqu'à leur retrait manuel par chaque client. Pas de suppression automatique, de vente résiduelle ni de transfert vers une autre coopérative. Voir [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).
- SALES-031 : dans un panier multi-coopératives, l'inactivité d'une coopérative bloque le checkout de tout le panier tant que ses lignes indisponibles n'ont pas été retirées manuellement. Aucun retrait automatique ni checkout/commande partielle automatique ; après retrait, le client peut poursuivre avec les lignes restantes. Voir [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).
- SALES-032 : lorsqu'une coopérative inactive redevient active, ses lignes conservées dans les paniers redeviennent automatiquement achetables, sans retrait/réajout manuel. Le checkout reste soumis à toutes les autres validations (produit/variante, prix, limites commerciales, quantité et stock). Aucune modification silencieuse. Voir [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).
- SALES-033 : si le prix d'un produit augmente pendant l'inactivité de sa coopérative, sa réactivation rend la ligne disponible mais le checkout reste bloqué jusqu'à acceptation explicite du nouveau prix. Aucune hausse silencieuse ; le prix accepté est transmis à Orders pour le snapshot, conformément à SALES-007. Voir [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).
- SALES-034 : si le prix d'un produit baisse pendant l'inactivité de sa coopérative, sa réactivation rend la ligne disponible et le prix inférieur est automatiquement appliqué, avec information du client et sans acceptation supplémentaire. Le prix courant vérifié est transmis à Orders ; les autres validations restent requises, conformément à SALES-008. Voir [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).
- SALES-035 : une variante supprimée pendant l'inactivité de sa coopérative reste supprimée/indisponible après réactivation. Sa ligne historique est conservée et bloque le checkout jusqu'au retrait manuel ; aucune substitution automatique par une autre variante ni suppression automatique de la ligne. Voir [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).
- SALES-036 : une variante créée par une coopérative pendant son inactivité devient disponible dans le catalogue après réactivation, sous réserve des contrôles habituels, mais n'est jamais ajoutée automatiquement aux paniers existants et ne remplace aucune ancienne variante. Le client doit choisir et ajouter lui-même cette nouvelle variante ; les lignes existantes conservent leurs propres statuts et validations, conformément à SALES-035. Voir [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).
- SALES-037 : lorsqu'une caractéristique commerciale d'une variante existante est modifiée pendant l'inactivité de sa coopérative, la ligne de panier conserve sa configuration initiale et le checkout reste bloqué jusqu'à acceptation explicite de la nouvelle configuration ou retrait manuel. Aucun changement commercial silencieux ; la configuration acceptée doit être transmise à Orders pour le snapshot. Voir [SALES V1](../domains/marketplace/SALES_V1_SPECIFICATION.md).

- Audit Sales V1 du 19 novembre 2026 : périmètre pré-commande confirmé après validation SALES-001 à SALES-037. Le domaine Sales reste à créer ; les contrats Products, Cooperatives, Localization, Identity, Orders, Inventory et Delivery sont à traiter selon le rapport [SALES_DEPENDENCY_AUDIT.md](../domains/marketplace/SALES_DEPENDENCY_AUDIT.md). Aucun checkout complet ne doit être déclaré avant contrats Orders idempotent, Inventory availability et Delivery quote.


## 9. Ré-audit de clôture — 19 novembre 2026

- **HEAD contrôlé :** `befe78ab89cf7b2ab8dc4433c5a4c1ee6ff46930`
- **Branche :** `feature/Marketplace`
- **Méthode :** arbre Git récursif complet ; inventaire des 186 fichiers de domaines, tests, migrations et providers ; lecture ciblée des services Products, Cooperatives, Localization et Orders.
- **Résultat :** aucun domaine ne peut être déclaré `FROZEN` sur la seule base de cet audit statique. Les tests n'ont pas été exécutés dans l'environnement Laravel local.

### 9.1 Inventaire applicatif actuel

| Domaine | Fichiers | Statut de clôture |
|---|---:|---|
| Identity | 99 | Écarts à corriger / audit de sécurité et tests finaux |
| Media | 21 | Écarts V1 documentés ; contrats propriétaires et tests finaux à terminer |
| Orders | 13 | Écarts P0 : contrat public, idempotence, Inventory, Delivery, exposition HTTP |
| Products | 12 | Écarts P0 : dépendances Catalog/Categories/Identity et contrat commercial versionné |
| Audit | 11 | Contrat public présent ; intégrité, accès, tests et dépendances à revalider |
| Catalog | 8 | Écarts de frontière avec Products/Categories et ownership du pivot |
| Cooperatives | 7 | Contrat public de statut absent ; découplage de l'acteur à traiter |
| Localization | 7 | Contrat d'adresse client/snapshot absent ; dépendances directes Identity/Cooperatives |
| Categories | 6 | Ownership du pivot et contrat d'association à finaliser |
| Dashboard | 2 | Projection et dépendance Statistics à cadrer |
| Sales | 0 | Spécification SALES-001 à SALES-037 validée ; domaine à implémenter après stabilisation des contrats |
| Inventory | 0 | Spécification initiale présente ; audit technique, schéma, code et tests à réaliser |
| Delivery | 0 | Domaine à cadrer et implémenter |
| Finance / Facturation | 0 | Domaine à cadrer et implémenter selon les règles financières validées |
| Communication | 0 | Domaine à cadrer et implémenter |
| Statistics | 0 | Autonomie validée ; spécification à produire |
| CMS | 0 | Domaine à cadrer et implémenter |

### 9.2 Bloqueurs de clôture transversaux

1. ProductService dépend directement de Catalog, Categories et du modèle User Identity.
2. AddressService dépend directement des modèles User et Cooperative ; aucun contrat public de lecture d'adresse client avec snapshot n'est présent.
3. CooperativeService reçoit le modèle User et aucun lecteur public de statut de coopérative n'est présent.
4. OrderCreationService accepte User et un tableau libre, sans idempotence visible.
5. La confirmation Orders ne déclenche pas de réservation Inventory.
6. Les frais Delivery sont initialisés à zéro sans contrat de devis.
7. AppServiceProvider enregistre encore MediaOwnerObserver sur Product, Cooperative et User.
8. Orders n'a pas de provider/contrôleurs/routes propres recensés dans l'arbre actuel.
9. Aucun test d'architecture de frontières n'a été recensé.
10. Les tests, Pint, migrations MySQL, triggers et scénarios de concurrence n'ont pas été exécutés sur ce HEAD.

Le détail technique et les corrections attendues sont consignés dans `docs/architecture/DOMAIN_DEPENDENCY_AUDIT.md`, section 11.

### 9.3 Vagues de clôture

- **Vague A — Identité et propriétaires :** Identity, Localization, Cooperatives.
- **Vague B — Catalogue :** Catalog, Categories, Products.
- **Vague C — Transversal :** Media, Audit.
- **Vague D — Commande et stock :** Orders, Inventory.
- **Vague E — Opérations :** Delivery, Finance, Communication.
- **Vague F — Restitution :** Statistics, Dashboard, CMS.
- **Vague G — Parcours client :** Sales et intégration finale.

Chaque vague se termine par audit des dépendances, tests ciblés, tests de frontières, Pint et suite complète. Les domaines dépendants ne sont pas déclarés gelés tant que leurs contrats fournisseurs ne sont pas stabilisés.

### 9.4 Journal de contrôle

- Référence précédente de roadmap remplacée : HEAD `c5981e490f3f15f8f1672b589a1c962a1c51abc2`.
- Nouvelle photographie : HEAD `befe78ab89cf7b2ab8dc4433c5a4c1ee6ff46930`.
- Audit détaillé : commit `629d88fb2049e28f9307fd38410a20e432bebcb0`.
- PHPUnit/Pint/MySQL/CI : non exécutés dans cette session.
- Merge vers `main) : non effectué.


### 10. Lot A1 — Cooperatives (19 novembre 2026)

- Règle de suppression corrigée : root peut supprimer globalement ; super_admin peut supprimer uniquement une coopérative affectée ; cooperative_admin et user ne peuvent pas supprimer.
- Test de Policy mis à jour pour vérifier simultanément l'accès à la coopérative affectée et le refus hors affectation.
- Commits : `048f47736203c2b1c5d08208bd68b1fc195c411c` (Policy), `6cc88ab802b18c947668ca4f9149710f040526ae` (test).
- Restent à traiter : dépendance directe User dans CooperativeService, dépendance directe AddressService dans CooperativeController, relations Eloquent transversales du modèle Cooperative et contrat public de statut.
- Tests non exécutés ; statut du lot : **correction écrite, validation runtime en attente**.


### 11. Lot A2 — Identity (19 novembre 2026)

- Suppression du parcours dangereux `activate-and-verify` qui validait directement l'e-mail et exposait le mot de passe partagé `MahlineMahline`.
- L'activation root conserve désormais le parcours vérifiable : compte activé mais e-mail non vérifié, invitation envoyée, connexion bloquée jusqu'à vérification.
- Test de service modifié pour vérifier la conservation du mot de passe existant et l'absence de validation automatique de l'e-mail.
- Dépendance PasswordHistoryService inutilisée retirée du service.
- Commits : `acc9518`, `1b6041b`, `9730d4c`, `2d2f761`, `cfc950e`, `4789744`.
- Restent à traiter : frontières Eloquent Identity↔Cooperatives/Localization/Media, provider Identity, vérification complète API token/expiration, reset/invitation/email change et scénarios de concurrence.
- Tests non exécutés ; statut du lot : **correction écrite, validation runtime en attente**.


### 12. Lot A3 — Localization (19 novembre 2026)

- Création du contrat `UserAddressLifecycleManager` à identifiants ; Identity l'utilise pour le soft delete/restauration des adresses utilisateur.
- Extension de `CooperativeAddressManager` avec la mise à jour d'adresse par identifiant ; le contrôleur Cooperatives ne dépend plus de la classe concrète AddressService.
- Bindings enregistrés dans Localization ; test ajouté pour le cycle suppression logique/restauration via contrat.
- Invariants relevés : propriétaire exclusif User ou Cooperative, unicité adresse active coopérative, adresse principale unique, Maroc `MA`, soft delete et batch de restauration.
- Commits : `f5caab7`, `40a2894`, `f4776b6`, `da11f75`, `2377fcd`, `73b41c2`, `3b76920`.
- Restent à traiter : PersonalAddressController dépend encore du service concret ; modèles et Policy Localization importent directement User/Cooperative ; contrat snapshot d'adresse pour Orders/Sales absent ; tests MySQL concurrence/trigger obligatoires.
- Tests non exécutés ; statut du lot : **correction écrite, validation runtime en attente**.


#### Complément A2 — nettoyage des tests

- Références à la méthode supprimée retirées du test de cycle de vie root.
- Test Feature ajouté pour confirmer que la route directe `activate-and-verify` n'est plus enregistrée ; le test d'utilisateur archivé ne teste plus que le parcours d'invitation.
- Commits : `5a879c8`, `5cbf413`.
- Validation runtime toujours en attente.

#### Complément A3 — carnet d'adresse personnel

- Le contrôleur de profil Identity utilise désormais le contrat Localization pour enregistrer l'adresse principale ; l'opération est transactionnelle et verrouille l'utilisateur avant de choisir création ou mise à jour.
- Test ajouté pour création/mise à jour via contrat.
- Commits complémentaires : `bf01e84`, `4266eda`, `9b328e7`, `6d8c7ed`, `a18166c`.
- Validation runtime en attente.


#### Complément A1 — test de service

- Test de bout en bout service ajouté : suppression permise sur coopérative affectée, soft delete de son adresse, refus hors affectation et absence d'audit de suppression hors périmètre.
- Commit : `735de59`.
- PHPUnit non exécuté.

#### Complément A3 — restauration des adresses client

- Correction du calcul de limite de restauration : le type de compte client est désormais déterminé par le rôle actif, même si le compte est archivé/inactif.
- Test ajouté pour restaurer cinq adresses après archivage et soft delete, avec une seule adresse principale.
- Commits : `c0ee1be`, `5a2aa1a`.
- PHPUnit non exécuté.

### 13. Lot A4 — Identity (19 novembre 2026)

- Révocation globale des sessions : verrouillage User puis sessions, sérialisé avec la création de session pour éviter une nouvelle session qui échapperait à la révocation.
- Gestion root des tokens : les rôles interdits (root/customer) sont maintenant identifiés même si le compte est suspendu/inactif.
- Tests ajoutés : ordre des verrous et refus d'un customer suspendu dans le workflow root API tokens.
- Commits : `737d3f0`, `cf70857`, `fec7d87`, `8378e8a`.
- Tests non exécutés ; validation runtime et test de concurrence MySQL en attente.

### 14. Lot A5 — Provider Identity (19 novembre 2026)

- Création et enregistrement d'un provider propre à Identity ; bindings de contrats, UserObserver et modèle Sanctum déplacés hors d'AppServiceProvider.
- Test de résolution des contrats ajouté.
- AppServiceProvider conserve la composition MediaOwnerObserver sur trois domaines ; ce couplage reste un écart Media/architecture à traiter.
- Commits : `89862fd`, `aa12e27`, `5247c83`, `8e4b307`.
- Tests non exécutés.

### 15. Lot A6 — Contrat de statut Cooperatives (19 novembre 2026)

- Contrat public `CooperativeStatusReader` + DTO créés ; résultat distingue active, deleted et missing.
- Binding dans CooperativesServiceProvider et test du contrat ajoutés.
- Commits : `6b6086d`, `491e211`, `96f59bd`, `0cbd481`, `25af509`.
- Restent à traiter : contrat d'acteur pour supprimer les imports User Eloquent et intégration des consommateurs Orders/Sales à venir.
- Tests non exécutés.

### 16. Lot A7 — Ownership du pivot Identity / Cooperatives (19 novembre 2026)

- L'affectation du super_admin créateur n'est plus écrite directement par Cooperatives dans le pivot Identity.
- Identity possède désormais l'opération, le verrouillage, la révocation des tokens et l'audit.
- Test étendu pour vérifier affectation, révocation et événement d'audit.
- Commits : `5d9f046`, `6bc5332`, `2394de8`, `1a0fe1d`.
- Tests non exécutés.

- Garde ajoutée : l'affectation initiale ne peut concerner que la coopérative dont le super_admin est le créateur. Commit : `6681226`.

- Test ajouté pour refuser l'affectation initiale lorsque `created_by` ne correspond pas au super_admin. Commit : `1c81c415`.

#### Révision A7 — événement de domaine

- Le couplage direct CooperativeService → contrat Identity a été remplacé par `CooperativeCreatedV1`, consommé par un listener Identity ; la dépendance est désormais unidirectionnelle par événement.
- L'affectation, l'audit et la révocation restent dans la transaction de création.
- Test d'exigence du rôle super_admin adapté.
- Commits : `66b4373`, `6053e56`, `d5f9025`, `41ea201`, `a2a8cbd`, `262b9c7`.
- Tests non exécutés.

- Le contrat de lecture de statut est maintenant fourni par un service dédié, séparé du service d'écriture. Commits : `2be30cb`, `20bfbb7`, `42af72a`.

- Test A4 du contrôleur root aligné sur le middleware désactivé utilisé par les tests du même périmètre. Commit : `9ce03e8`.

### 17. Lot A8 — Contrat Identity / Cooperatives

- Identity ne dépend plus directement du modèle Cooperative dans UserManagementService ; validation et verrouillage passent par `CooperativeIdentityReference`.
- Les méthodes d'affectation reçoivent des IDs coopérative, le contrôleur garde Gate/route binding.
- Verrouillage dans l'ordre User puis Cooperative.
- Test du contrat : coopérative inactive mais non supprimée acceptée ; soft-deleted refusée.
- Commits : `4266034`, `521b8e6`, `692cf78`, `70e7c60`, `599ac39`, `d2f3645`, `50f4f05`, `bdb0d9a`, `75b79f2`, `a5b8820`, `65dd84e`, `6e6fff1`, `d9e75e7`.
- Tests non exécutés.

#### Complément A8 — pivot Identity

- `UserManagementService` ne manipule plus le pivot via la relation `User::cooperatives()` ; il utilise le modèle Identity `SuperAdminCooperative`.
- Aucun import du modèle Cooperative ni appel `cooperatives()` ne subsiste dans ce service.
- Commits : `841b85e`, `31f70f1`.

- Contrôle statique des tests : correction d'un séparateur de namespace doublé dans un test Identity. Commit : `472a8a5`.

- Timestamps du pivot Identity explicitement activés et vérifiés dans le test de création. Commits : `7059760`, `f87bd65`.

### 18. État de la séquence A1–A8

- Lots de corrections et tests écrits : Cooperatives, Identity, Localization, événements versionnés, contrats publics, ownership du pivot, révocation de sessions et adresses.
- Identity / Localization / Cooperatives restent **AUDIT_IN_PROGRESS**, non `FROZEN`.
- Restent : ActorContext pour CooperativeService ; contrats d'owner/DTO pour AddressService et AddressPolicy ; audit des relations Eloquent inter-domaines ; intégration MediaOwnerObserver ; audit Identity complet ; tests PHPUnit/Pint/MySQL.
- Aucun test runtime n'a été exécuté ici ; aucun statut CI disponible sur la branche lors du contrôle.
- Prochain lot : A9 — contrats d'acteur et d'owner, puis audit exhaustif des fichiers restants de ces trois domaines.

### 19. Lot A9 — acteur partagé et affectations Identity

- `CooperativeService` utilise désormais `ActorContextProvider` + `ActorAuthorizer` et ne dépend plus du modèle User.
- Le vrai contrôle d'accès reste exécuté par Laravel Gate/Policies dans l'adaptateur Identity.
- Les Policies Cooperatives et Localization lisent les affectations via `SuperAdminCooperativeAssignmentReader`, contrat détenu par Identity.
- Tests ajoutés pour ActorContext, verrou d'urgence, lecteur de pivot et bindings ; tests non exécutés.
- Localization reste à découpler de User/Cooperative dans `AddressService` : ports owner User et owner Cooperative à définir et implémenter sans dépendance circulaire.
- Les trois domaines restent `AUDIT_IN_PROGRESS`.

### 20. Lot A10 — Localization sans modèles propriétaires

- `AddressService` est désormais indépendant des modèles Eloquent User et Cooperative ; ses APIs échangent des IDs et DTOs.
- Localization définit les ports `UserAddressOwnerGateway` et `CooperativeAddressOwnerGateway`; Identity et Cooperatives fournissent les adaptateurs.
- L'autorisation passe toujours par les Policies Laravel via les adapters, pas par une duplication de règles.
- Les contrôleurs consomment les contrats `UserAddressLifecycleManager` et `CooperativeAddressManager`.
- Tests de service adaptés, test de frontière ajouté, tests de bindings ajoutés ; non exécutés.
- Identity / Localization / Cooperatives restent `AUDIT_IN_PROGRESS` jusqu'à exécution des suites et audit final.

### 21. Lot A11 — contrats partagés et Policies sans modèles croisés

- Contrat de lecture des affectations déplacé vers Shared ; Identity implémente, les domaines consommateurs ne dépendent plus d'une interface privée Identity.
- `IdentityActor` abstrait le principal authentifié ; User l'implémente ; CooperativePolicy et le scope de visibilité utilisent cette abstraction.
- Scope de visibilité coopérative reçoit les IDs d'affectation depuis le lecteur Identity.
- AddressPolicy utilise ActorContext et DTOs d'owner ; les adapters Identity/Cooperatives traduisent leurs modèles en DTOs et délèguent à la Policy.
- LocalizationServiceProvider ne contient plus de Gate closures couplées aux modèles User/Cooperative.
- Tests d'architecture et tests de Policies ajoutés/adaptés, non exécutés.
- Relations Eloquent réciproques conservées uniquement comme mapping relationnel/lecture ; toutes les écritures de pivot restent dans Identity.

### 22. Lot A12 — UserPolicy

- UserPolicy utilise les contrats CooperativeIdentityReference et SuperAdminCooperativeAssignmentReader, pas le modèle Cooperative ni la relation User::cooperatives().
- Contrôleur d'affectation transmet les identifiants pour Gate et les opérations de service.
- Test de frontière UserPolicy ajouté ; tests non exécutés.

### 23. Lot A13 — sécurité Identity

- SessionService bloque désormais la création directe d'une session pour un compte verrouillé d'urgence.
- PasswordResetService valide la séparation client / non-client dès la demande, à l'émission du token et à sa consommation ; classification par rôles actifs, même si l'e-mail n'est pas vérifié.
- Tests de session et de reset ajoutés ; non exécutés.
- Revue statique des tokens, invitations et changement d'e-mail documentée ; aucun gel Identity tant que la suite complète n'a pas été exécutée.

### 24. Lot A14 — schéma Localization

- Audit statique des contraintes d'ownership, index uniques et foreign keys terminé.
- Tests MySQL existants identifiés pour les triggers et index ; exécution runtime encore en attente.
- Countries reste intégré à Localization.

### 25. Lot A15 — périmètre Identity depuis la fiche utilisateur

- CRUD et transitions de statut des comptes internes réservés au super_admin_root, avec classification du rôle fondée sur les rôles actifs en base et indépendante du statut utilisateur.
- Renvoi d'invitation d'activation autorisé au root globalement, au super_admin pour les comptes user des coopératives affectées et au cooperative_admin pour les comptes user de sa coopérative.
- Envoi d'un lien de réinitialisation du mot de passe selon le même périmètre ; routes, contrôleurs, Policy et formulaires ajoutés. Token de reset valable 30 minutes.
- L'affectation d'un super_admin exige un compte actif, vérifié et non verrouillé dans les deux couches Policy et service.
- Tests ciblés ajoutés mais non exécutés. Identity reste AUDIT_IN_PROGRESS jusqu'à validation locale, tests de routes et suite complète.

### 26. Lot A16 — restrictions de profil et frontière des statuts Cooperatives

- `cooperative_admin` peut modifier uniquement `email` et `telephone` des comptes `user` de sa coopérative. Le contrôleur filtre les champs et le service applique une seconde liste blanche.
- `super_admin` conserve la modification des champs de profil prénom/nom/e-mail/téléphone des comptes `user` dans les coopératives affectées.
- Les rôles cibles sont filtrés par association de rôle active en base, sans dépendre du statut de compte Identity.
- Le constat initial de la section 11.23 (absence de cascade) a été remplacé par la décision A17 : le lecteur de statut est consommé par Identity et la révocation des sessions/jetons est intégrée lors des transitions vers un statut non actif et de la suppression logique.
- **Décision métier : validée dans le lot A17.** Les comptes membres conservent leur statut Identity, mais perdent leurs sessions et jetons ; la réactivation ne restaure pas les anciens accès.
- Contrôle statique : les 15 actions root et 15 actions back-office de gestion des comptes correspondent à des méthodes de contrôleur existantes ; les endpoints root sont gardés et les endpoints délégués utilisent les Gates/permissions prévues. Les routes back-office create/store demeurent volontairement inopérantes pour les rôles délégués (`create` root-only).
- Tests ajoutés/adaptés mais non exécutés ; Identity et Cooperatives restent `AUDIT_IN_PROGRESS`.

### 27. Lot A17 — révocation des accès Identity lors de la désactivation Cooperatives

- Décision validée : toute transition vers un statut autre que `ACTIVE` (`PENDING`, `INACTIVE`, `SUSPENDED` ou `ARCHIVED`), ainsi que la suppression logique, révoque toutes les sessions web et tous les jetons API des comptes membres `cooperative_admin` et `user` de cette coopérative.
- Le statut Identity des comptes membres n'est pas modifié. Les comptes `super_admin_root`, `super_admin` et `customer` sont exclus.
- Les sessions et jetons restent historisés et portent une raison de révocation auditable. Une réactivation/restauration ne rétablit aucun ancien accès.
- La Policy d'autorisation Identity consulte le contrat `CooperativeStatusReader` : aucun nouveau login/session ou jeton API n'est possible tant que la coopérative n'est pas active ; le middleware web et la validation Sanctum invalident également les accès existants.
- Implémentation par contrat `CooperativeMemberAccessRevoker` défini dans Cooperatives et adaptateur implémenté par Identity.
- Tests de régression ajoutés pour désactivation, suppression et non-restauration ; exécution non réalisée. Cooperatives/Identity restent `AUDIT_IN_PROGRESS`.


### 28. Lot A18 — conception technique Sales / Inventory / Finance / Statistics (20 novembre 2026)

- Ajout de `docs/domains/marketplace/MARKETPLACE_DOMAINS_TECHNICAL_DESIGN.md` : ownership, modèles candidats, contrats publics, événements, transactions, routes, middleware, policies, matrice de portée, tests et séquence de livraison.
- Audit statique de `app/Domains` sur `feature/Marketplace` : Sales, Inventory, Finance et Statistics absents ; Orders et Dashboard présents.
- Les spécifications Sales V1 et Inventory V1 préexistantes sont conservées comme sources métier. Aucun modèle ni migration n'a été créé dans ce lot.
- Finance est conçu à partir des règles validées du document `ORDER_INVOICING_PAYMENTS_DELIVERY_FUNCTIONAL_RULES.md`, avec un point bloquant à résoudre sur l'unicité de facture par commande versus numérotation par coopérative.
- Statistics est défini comme domaine autonome de projections ; Dashboard reste la couche de présentation.
- **État :** conception initiale documentée ; décisions techniques/métier bloquantes listées dans le document ; aucun domaine n'est déclaré implémenté ou validé.
- **Prochaine étape :** arbitrer les points bloquants, auditer les conventions exactes (BaseModel, migrations, permissions, providers, tests), puis coder par incréments et auditer routes/Policies/middleware/contrats après chaque lot.


### 29. Lot A19 — spécifications initiales Finance et Statistics (20 novembre 2026)

- Création de `docs/domains/marketplace/FINANCE_V1_SPECIFICATION.md` à partir des règles fonctionnelles validées du 16 novembre ; statut non gelé en raison de l'arbitrage facture globale/par sous-commande, de la fiscalité et des écarts de rapprochement.
- Création de `docs/domains/marketplace/STATISTICS_V1_SPECIFICATION.md` ; l'autonomie du domaine est respectée et les KPI sont explicitement marqués comme propositions à valider.
- Les documents ne déclarent aucun modèle, migration, route, Policy ou test implémenté.
- Prochaine étape : résoudre les arbitrages bloquants puis commencer Sales et Inventory par lots, avec tests et mise à jour du registre après chaque incrément.


### 30. Lot A20 — Delivery V1 et décision Sales (20 novembre 2026)

- Décision utilisateur SALES-019 : maximum absolu fixé à **100 unités par ligne de panier**, tous produits/variantes/coopératives confondus. Application obligatoire côté serveur aux ajouts, modifications, fusions et checkout ; indépendant du stock. La valeur minimale et la précision/unité restent à cadrer.
- Création de `docs/domains/marketplace/DELIVERY_V1_SPECIFICATION.md` : périmètre autonome Delivery, transporteurs, tarifs de trajet, devis, missions, affectations, expéditions, colis, suivi, preuves, retours, anomalies et litiges.
- Règles déjà validées consolidées : tarification par trajet ; somme des frais par expédition multi-coopérative ; fenêtre d'acceptation transporteur 12 h ; première acceptation valide exclusive ; à défaut, attribution automatique au tarif le moins cher ; accès transporteur dédié au premier release ; photos aux étapes de départ et retour ; contrôles produit par produit ; responsabilité de frais de retour selon motif ; litiges humains et gouvernance de contestation.
- Les arbitrages encore ouverts (égalité tarifaire, éligibilité, identité transporteur, devis, statuts, échecs, matrice photos, consommation stock, FR-014 et autres) sont inscrits dans la spécification Delivery.
- Audit statique : aucun domaine/provider/migration/modèle/route/Policy/middleware Delivery n'existe au code consulté. Orders initialise encore les frais de livraison à zéro et ne possède pas de contrat Delivery intégré.
- **État :** spécification Delivery initiale et audit des frontières documentés ; pas de code Delivery ni de migration créée ; aucun test runtime exécuté.
- **Prochaine étape :** arbitrer les points bloquants Delivery et stabiliser les contrats Orders/Inventory/Localization/Identity/Media avant les migrations et l'implémentation Delivery.


## 14. Journal A21 — création des cinq racines de domaines Marketplace (21 novembre 2026)

À la suite de la demande de création des domaines, les racines suivantes ont été ajoutées sur `feature/Marketplace` avec un README de responsabilité et de frontières :
- `app/Domains/Sales/README.md`
- `app/Domains/Inventory/README.md`
- `app/Domains/Delivery/README.md`
- `app/Domains/Finance/README.md`
- `app/Domains/Statistics/README.md`

La spécification Delivery a été mise à jour : après 12 heures, si plusieurs transporteurs sont ex æquo au tarif minimal, le premier à enregistrer une acceptation valide obtient la mission. Le mécanisme devra être atomique et testé contre les courses concurrentes.

Cette création est un scaffold documentaire/applicatif de racine, pas une implémentation métier. Aucun modèle, migration, service, contrôleur, route, Policy ou provider vide n'a été ajouté. Les providers seront enregistrés dans `bootstrap/providers.php` lorsque les premières liaisons réelles du domaine seront définies. Les tests PHP/Pint et l'exécution Laravel restent à faire localement.


## 13. Intégration des fonctionnalités dans les écrans — point de contrôle du 23 novembre 2026

**Branche de travail :** `feature/Marketplace`  
**Référence d'inventaire :** HEAD observé avant cette séquence UI : `ae0fcf18cef85bbda90594cd6b326b33749f68d1`.  
**Périmètre :** connecter les fonctions réellement présentes aux interfaces des rôles client, cooperative_admin, super_admin et super_admin_root. Cette section suit l'intégration UI ; elle ne vaut pas validation fonctionnelle des domaines.

### 13.1 Écrans et intégrations déjà présentes

- **Boutique publique :** liste et détail produits, recherche, filtres catégorie/coopérative/prix, variantes et ajout au panier.
- **Panier :** lecture et mise à jour du panier, retrait, acceptation des changements de prix/caractéristiques et détection des conflits de version.
- **Client :** tableau de bord, profil, carnet d'adresses, changement d'e-mail, historique et détail des commandes, sécurité du mot de passe et appareils.
- **Back-office coopérative / super_admin :** coopératives, utilisateurs, catalogues, catégories et produits ; inventaire disponible selon le périmètre autorisé.
- **Root :** gestion globale des coopératives, utilisateurs, catégories globales, catalogues/produits/catégories par coopérative, sessions, appareils, jetons API et verrouillages d'authentification.
- **Inventory :** espace de stock, comptages, seuils, quarantaine, rapports et signalements de sécurité, sous réserve des permissions et de la portée coopérative.

### 13.2 Intégrations UI réalisées pendant la séquence

- Navigation Boutique → espace client, commandes et profil.
- Correctif de robustesse du panier : le script ne manipule le lien de checkout que lorsqu'il est présent (ce lien n'existe pas dans le rendu client connecté). Commit `fad149b` ; tests runtime non exécutés.
- Retrait du bloc de photo de profil qui annonçait une fonctionnalité Media dans le profil client. Aucun développement Media n'est inclus dans ce chantier.
- Navigation back-office enrichie avec notifications, sessions, appareils de confiance et jetons API.
- Navigation Root enrichie avec inventaire, notifications, appareils de confiance et jetons API.
- Fiche coopérative enrichie de raccourcis vers catalogues, catégories et produits, sous contrôle des Policies existantes.
- Tableau de bord du rôle `user` enrichi de raccourcis vers inventaire, adresse personnelle, sessions, appareils, jetons API et mot de passe.

**Fichiers modifiés :** `resources/views/layouts/storefront.blade.php`, `resources/views/customer/profile/edit.blade.php`, `resources/views/layouts/admin.blade.php`, `resources/views/layouts/root.blade.php`, `resources/views/backoffice/cooperatives/show.blade.php`, `resources/views/user/dashboard.blade.php` et tests associés de navigation/profil.

### 13.3 Écarts empêchant une intégration complète à ce stade

| Périmètre | Constat observé dans le dépôt | Conséquence UI |
|---|---|---|
| Sales / Checkout | Les endpoints de prévisualisation et confirmation existent ; l'adresse snapshot, la disponibilité Inventory et l'idempotence sont implémentées. `OrderCreationService` fixe toutefois `delivery_total` et `delivery_fee` à `0.00`. | Le parcours checkout reste désactivé tant que le calcul et l'intégration Delivery ne sont pas disponibles et testés ; ne pas créer de commande avec des frais de livraison fictifs ou nuls. |
| Orders — espace coopérative | Les écrans client d'historique/détail et les contrôles d disponibilité existent ; aucun contrôleur d'interface coopérative de traitement des commandes n'a été recensé dans `app/Domains/Orders/Http/Controllers`. | Ne pas ajouter de faux lien « Commandes à traiter » avant la mise en place et l'audit du parcours coopérative. |
| Delivery | Le dossier de domaine existe, mais aucun contrôleur HTTP, service métier ou route Delivery n'a été recensé dans l'inventaire vérifié. | Pas d'écran de devis, expédition, suivi, preuve de livraison ou retour à relier pour l'instant. |
| Finance | Le dossier de domaine existe, mais aucun contrôleur HTTP ou service Finance n'a été recensé dans l'inventaire vérifié. | Pas d'écran de factures, paiements, commissions, remboursements, rapprochements ou reversements à relier pour l'instant. |
| Statistics | Le domaine est présent dans l'arborescence, mais aucun contrôleur HTTP ou service Statistics n'a été recensé dans l'inventaire vérifié. | Les tableaux de bord ne doivent pas afficher de KPI simulés ; les contrats et agrégats doivent d'abord exister. |
| Audit | Un contrôleur de consultation existe ; son intégration doit rester réservée aux rôles et permissions prévus, notamment Root. | Auditer le lien et les contrôles d'accès avant exposition dans la navigation. |

### 13.4 Prochain ordre d'intégration

1. Auditer les écrans existants et les contrôleurs associés, page par page, pour identifier les actions non reliées, liens morts, formulaires incomplets et vues sans retour d'état.
2. Terminer l'intégration des fonctions déjà disponibles dans Identity, Cooperatives, Catalog, Categories, Products et Inventory.
3. Compléter les écrans Sales/Checkout uniquement après validation du parcours de livraison et de l'adresse de commande.
4. Concevoir les écrans de traitement coopératif des commandes à partir des contrats Orders existants.
5. Ne créer les écrans Delivery, Finance/Facturation et Statistics qu'après implémentation et audit de leurs services et routes propriétaires.
6. Exécuter les tests Feature ciblés, la suite complète et Pint localement ; tant que les résultats ne sont pas fournis, l'état reste **À tester**.

**État global de l'intégration UI : En implémentation / audit à poursuivre.** Aucun test n'est déclaré vert dans le cadre de ce point de contrôle. Aucun merge vers `main`, aucune exécution GitHub Actions et aucun développement Media.
