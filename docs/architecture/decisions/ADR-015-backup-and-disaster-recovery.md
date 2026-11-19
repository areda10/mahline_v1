# ADR-015 — Sauvegardes et reprise après incident

- **Statut :** ACCEPTÉ
- **Date :** 19 novembre 2026
- **Décision :** Option C — stratégie centralisée avec reprise cohérente
- **Portée :** architecture cible MAHLINE V7.0
- **Dépendances :** ADR-012 (événements métier), ADR-013 (traitements asynchrones), ADR-014 (observabilité)

## Contexte

MAHLINE doit pouvoir récupérer ses données et ses services après une panne, une corruption, une erreur humaine, une compromission ou une indisponibilité de l'hébergement. Une sauvegarde de la base seule ne suffit pas si des fichiers Media référencés par les données sont absents ; inversement, restaurer des fichiers sans les enregistrements correspondants peut produire des ressources orphelines. Les événements, jobs et intégrations externes peuvent également être dans un état différent au moment de la reprise.

Des sauvegardes indépendantes et non coordonnées par domaine risqueraient de restaurer des parties de la plateforme à des instants incompatibles.

## Décision

Adopter une stratégie commune de sauvegarde et de reprise à l'échelle de la plateforme, couvrant les données, fichiers et éléments techniques nécessaires, avec des procédures de restauration cohérente et des objectifs de reprise définis par criticité.

### 1. Périmètre des sauvegardes

La politique doit inventorier et couvrir, selon leur nature et leur nécessité :

- la base de données et ses journaux permettant une restauration à un instant précis lorsque le moteur et l'infrastructure le permettent (PITR) ;
- les fichiers et objets Media, leurs versions et métadonnées nécessaires ;
- les documents générés qui doivent être conservés (par exemple factures PDF), selon leur statut de source ou de document reproductible ;
- les configurations techniques non secrètes, définitions d'infrastructure et procédures de déploiement nécessaires à la reconstruction ;
- les secrets et clés indispensables à la restauration, dans un mécanisme séparé, chiffré et à accès fortement restreint ;
- les données d'Outbox, états de jobs ou informations de reprise nécessaires à la cohérence des traitements, selon leur caractère durable et leur source de vérité.

Les caches, fichiers temporaires et artefacts reproductibles ne sont pas nécessairement sauvegardés ; leur reconstruction doit être documentée. Les données de fournisseurs externes ne sont pas considérées comme sauvegardées par MAHLINE du seul fait qu'un identifiant local les référence.

### 2. Fréquence, rétention et stockage

- Les sauvegardes sont automatisées et leur fréquence est déterminée à partir des objectifs RPO/RTO, de la criticité et du volume de données.
- Les copies de sauvegarde sont stockées hors de l'environnement d'hébergement principal afin qu'une panne ou compromission de cet environnement ne détruise pas toutes les copies.
- Une protection contre l'altération et la suppression accidentelle ou malveillante est exigée : immutabilité, verrouillage de rétention, copie isolée ou mécanisme équivalent selon les capacités de l'infrastructure.
- Les sauvegardes et transferts sont chiffrés ; les clés sont protégées et leur récupération est testée.
- Les durées de rétention, rotations, obligations légales et procédures de purge sont définies par catégorie de données.
- Les accès sont limités au strict nécessaire, authentifiés, journalisés et séparés autant que possible des identifiants d'administration de production.
- Les échecs de sauvegarde, retards, tailles anormales et indisponibilités de destination déclenchent une alerte exploitable.

Aucun fournisseur, calendrier, nombre de copies ou durée de rétention universelle n'est imposé par cet ADR.

### 3. Cohérence entre base et fichiers

- La restauration doit rétablir un ensemble cohérent entre les enregistrements de base et les objets/fichiers référencés.
- La stratégie de sauvegarde doit prévoir un point de cohérence commun ou une méthode documentée pour rapprocher les versions de la base et des fichiers.
- Selon l'infrastructure, cela peut s'appuyer sur des snapshots coordonnés, versionnement des objets, manifestes, fenêtres de gel contrôlées ou journal de changements permettant de retrouver les objets à l'instant restauré.
- Les contrôles post-restauration identifient les références manquantes, objets orphelins, sommes de contrôle incorrectes et métadonnées incohérentes.
- Les suppressions logiques, politiques de conservation et exigences de preuve ne doivent pas être contournées par une restauration partielle non contrôlée.

### 4. Transactions, événements et traitements asynchrones

- Les données durables nécessaires à la reprise des événements et traitements suivent les règles de propriété des domaines et de l'ADR-012.
- Après restauration, les événements et jobs peuvent être rejoués uniquement avec des mécanismes d'idempotence, de déduplication et de contrôle des états métier.
- Les tâches en cours au moment de l'incident sont considérées comme potentiellement exécutées partiellement ; leur état doit être réconcilié avant toute reprise.
- Les files de transport et caches reconstruisibles peuvent être recréés, mais les événements durables ou intentions métier non rejouables ne doivent pas être perdus.
- Les intégrations externes (paiement, transport, e-mail, stockage tiers) doivent être rapprochées avec leur état réel avant de répéter une opération susceptible d'avoir déjà produit un effet externe.
- Une restauration ne doit pas provoquer automatiquement un double paiement, un double remboursement, un double envoi irréversible ou une seconde expédition.

### 5. Objectifs de reprise : RPO et RTO

- Un **RPO** (Recovery Point Objective) définit la perte maximale de données temporelle acceptable.
- Un **RTO** (Recovery Time Objective) définit la durée maximale cible pour restaurer le service.
- Les objectifs sont définis par service, catégorie de données et criticité métier, puis validés en fonction des capacités et coûts de l'hébergement.
- Les objectifs peuvent différer entre parcours critiques (identité, commandes, inventaire, finance) et fonctions moins critiques (statistiques, contenus non essentiels).
- Les valeurs chiffrées ne sont pas fixées dans cet ADR : elles nécessitent une analyse d'impact métier, des contraintes d'hébergement et des mesures réelles.

### 6. Procédures de restauration et reprise

- Documenter les scénarios : panne d'hébergement, corruption de base, suppression accidentelle, perte de fichiers, compromission et indisponibilité prolongée.
- Définir les responsables, autorisations, étapes, critères de décision, communications et conditions de retour en production.
- Restaurer dans un environnement isolé lorsque cela est possible, vérifier l'intégrité, puis basculer selon une procédure contrôlée.
- Après restauration, vérifier les comptes et permissions, les références entre données et fichiers, les événements en attente, les jobs, les intégrations externes et les fonctions métier critiques.
- Toute restauration en production est autorisée, documentée et journalisée ; les actions de reprise sont traçables.
- Prévoir une procédure de rotation des secrets et clés si l'incident peut les avoir compromis.

### 7. Tests et preuves

- La réussite d'une sauvegarde ne prouve pas qu'elle est restaurable.
- Des tests périodiques de restauration complète et partielle sont obligatoires, avec preuves de durée, intégrité et contrôles métier.
- Les tests couvrent notamment la restauration de base, les fichiers Media, leur cohérence croisée, les documents financiers conservés et les mécanismes de reprise des événements/jobs.
- Les procédures sont révisées après changement majeur d'infrastructure, de schéma, de stockage ou après incident.
- Les résultats, anomalies, temps mesurés et actions correctives sont conservés dans le suivi opérationnel.

## Conséquences

### Positives

- Réduit le risque de perte irréversible de données et de fichiers.
- Permet de définir une reprise globale cohérente au lieu de restaurations isolées par domaine.
- Protège mieux contre les erreurs humaines, pannes et attaques visant les sauvegardes.
- Rend la capacité réelle de restauration vérifiable par des exercices.

### Contraintes

- Nécessite une capacité de stockage distincte, du chiffrement, des contrôles d'accès et une surveillance des sauvegardes.
- La cohérence base/fichiers et les rapprochements avec les systèmes externes demandent une conception explicite.
- Les objectifs RPO/RTO peuvent augmenter les coûts et doivent être arbitrés avec les contraintes de l'hébergeur.
- Les tests de restauration consomment des ressources et doivent être planifiés.

## Hors périmètre de cette décision

Cet ADR ne fixe aucun fournisseur, calendrier précis, nombre de copies, durée de rétention, valeur RPO/RTO ou procédure opérationnelle finale. Il ne crée aucun système de sauvegarde, stockage, compte, script, table, migration ou configuration. Il ne modifie aucun code applicatif ni déploiement. Ces éléments seront conçus après inventaire de l'infrastructure et validation des contraintes d'hébergement.

## Vérifications et travaux restant à réaliser

1. Inventorier l'hébergement, la base, le stockage Media, les fichiers générés, secrets, clés et intégrations externes.
2. Classifier les données et services par criticité et exigences de conservation.
3. Définir et faire approuver les RPO/RTO par parcours et domaine.
4. Choisir les mécanismes de sauvegarde, chiffrement, stockage hors site, immutabilité et rétention compatibles avec l'hébergeur.
5. Concevoir la cohérence temporelle base/fichiers et les contrôles d'intégrité.
6. Définir la réconciliation des événements, jobs et opérations externes après restauration.
7. Rédiger les runbooks de reprise, responsabilités et communications d'incident.
8. Réaliser des exercices de restauration et conserver les preuves mesurées.

## Références

- [ADR-012 — Fiabilité et cycle de vie des événements métier](ADR-012-domain-events-reliability.md)
- [ADR-013 — Traitements asynchrones et architecture des files d'attente](ADR-013-async-processing-and-queues.md)
- [ADR-014 — Observabilité technique et traçabilité transversale](ADR-014-observability-and-tracing.md)
- [Feuille de route centrale des domaines](../DOMAIN_ROADMAP.md)
