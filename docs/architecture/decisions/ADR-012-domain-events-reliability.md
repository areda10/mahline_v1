# ADR-012 — Fiabilité et cycle de vie des événements métier

- **Statut :** ACCEPTÉ
- **Date :** 19 novembre 2026
- **Décision :** 12
- **Périmètre :** architecture cible MAHLINE V7.0
- **Portée :** décision d'architecture ; ne vaut pas implémentation

## 1. Contexte

Les domaines MAHLINE doivent pouvoir notifier les autres domaines de faits métier sans leur donner accès à leurs modèles ou à leur persistance interne. Les événements peuvent déclencher des traitements importants : réservation de stock, préparation de livraison, facturation, notification ou agrégation statistique.

Un événement émis avant la validation de la transaction peut annoncer un fait qui sera finalement annulé. Une livraison répétée ou une reprise peut également provoquer plusieurs exécutions du même traitement. Le mécanisme doit donc assurer la cohérence transactionnelle, la tolérance aux doublons et une reprise maîtrisée, sans imposer une infrastructure lourde à tous les événements.

## 2. Décision

**L'option C — approche hybride avec fiabilité adaptée à la criticité — est adoptée.**

### 2.1 Événements métier publics

- Les événements inter-domaines sont des contrats publics, documentés et versionnés.
- Ils décrivent un fait métier déjà survenu ; ils ne sont pas des commandes demandant à un autre domaine d'effectuer une action.
- Ils exposent uniquement les données nécessaires aux consommateurs, sans révéler les modèles, objets internes ou détails de persistance du domaine émetteur.
- Leur nom, leur sens métier, leur version, leur schéma, leur producteur, leurs consommateurs attendus et leurs règles de compatibilité sont documentés.
- Les changements incompatibles exigent une stratégie de versionnement et de transition explicite.

### 2.2 Moment de publication et cohérence transactionnelle

- Un événement qui annonce une modification persistée ne peut être publié comme fait acquis qu'après le commit de la transaction correspondante.
- Un rollback ne doit jamais conduire à annoncer le fait métier annulé.
- Les événements internes à une transaction peuvent être préparés, mais leur publication externe doit respecter la validation effective de la transaction.
- Les traitements qui nécessitent une atomicité avec l'écriture métier doivent utiliser un mécanisme adapté à la transaction.

### 2.3 Transactional Outbox lorsque nécessaire

Le Transactional Outbox est utilisé lorsque la perte d'un événement aurait un impact métier ou opérationnel significatif, ou lorsqu'une livraison fiable et reprenable est requise.

- L'enregistrement Outbox est écrit dans la même transaction que le changement métier qu'il représente.
- Un processus dédié publie les messages en attente après commit.
- Le statut de livraison, les tentatives, les dates et les erreurs techniques utiles sont suivis.
- Les événements purement internes, non critiques ou facilement recalculables ne nécessitent pas automatiquement une persistance Outbox.
- Le choix entre publication après commit simple et Outbox est explicite, justifié par la criticité et documenté par événement ou famille d'événements.

### 2.4 Consommateurs idempotents

- Tout consommateur d'événement inter-domaines doit être conçu pour tolérer la réception répétée d'un même événement.
- Chaque événement possède un identifiant stable permettant de reconnaître les doublons.
- Le consommateur conserve, lorsque nécessaire, une preuve de traitement associée à l'identifiant et à sa propre opération.
- La déduplication et le traitement métier doivent être atomiques lorsque la persistance le permet.
- Une nouvelle tentative ne doit pas créer une seconde réservation, facture, livraison ou autre effet métier non souhaité.

### 2.5 Reprises et erreurs

- Les erreurs transitoires peuvent déclencher des tentatives automatiques avec temporisation et limites explicites.
- Les erreurs permanentes ou les événements qui dépassent le seuil de tentatives sont isolés pour analyse et reprise contrôlée.
- Les reprises manuelles sont autorisées uniquement aux acteurs habilités et doivent être tracées.
- Les échecs ne doivent pas être silencieusement abandonnés.
- Les journaux techniques ne doivent pas contenir de secrets, jetons ou données personnelles non nécessaires.

### 2.6 Rétention et observabilité

- La durée de conservation des événements et métadonnées Outbox dépend de leur usage métier, opérationnel, légal et d'audit.
- Les payloads sont minimisés ; les données sensibles ou volatiles sont référencées par identifiant lorsque cela est approprié.
- Les métriques doivent permettre de détecter les retards, échecs répétés, files bloquées et événements non traités.
- La rétention et la purge doivent être documentées et ne doivent pas supprimer les preuves nécessaires à une obligation d'audit ou de rapprochement.

## 3. Règles de conception

1. Un événement public représente un fait métier et possède un domaine producteur propriétaire.
2. Aucun consommateur ne lit directement les tables ou modèles du producteur pour interpréter l'événement.
3. Les événements sont publiés après commit ; l'Outbox est privilégiée lorsque la fiabilité l'exige.
4. Les consommateurs sont idempotents.
5. Les contrats sont documentés, versionnés et compatibles selon une politique explicite.
6. Les tentatives, erreurs, délais et reprises sont contrôlés et observables.
7. Les événements techniques internes ne sont pas automatiquement exposés comme contrats inter-domaines.
8. Les échanges de commandes synchrones restent distincts des événements de notification.
9. Le choix de persister un événement est fondé sur sa criticité et non appliqué aveuglément à tous les événements.

## 4. Conséquences

### Positives

- Les domaines communiquent sans couplage à leur stockage interne.
- Les consommateurs peuvent être relancés sans dupliquer les effets métier.
- Les événements critiques peuvent être livrés de manière durable et observable.
- L'architecture peut évoluer vers des traitements asynchrones ou distribués.

### Contraintes

- Les contrats et leur compatibilité doivent être maintenus.
- L'Outbox, les mécanismes de déduplication et les reprises ajoutent de la complexité là où ils sont nécessaires.
- L'exploitation doit surveiller les retards, erreurs et files de reprise.
- La cohérence éventuelle doit être explicitée aux processus métier consommateurs.

## 5. Plan d'application

1. Inventorier les événements Laravel existants et les notifications inter-domaines.
2. Identifier les événements qui représentent des faits publics et les distinguer des événements internes.
3. Définir un catalogue des événements publics avec propriétaire, version, schéma et consommateurs.
4. Classer leur criticité et décider, pour chacun, de la publication après commit simple ou de l'Outbox.
5. Définir l'identifiant stable, les garanties d'idempotence et les stratégies de reprise.
6. Définir rétention, observabilité, alertes et procédure de traitement des échecs.
7. Ajouter des tests couvrant commit/rollback, doublons, reprise, ordre si nécessaire et compatibilité de contrat.
8. Mettre en œuvre progressivement après audit et approbation, sans imposer l'Outbox à tous les événements.

## 6. Liens

- ADR-005 — Communication inter-domaines.
- ADR-006 — Transactions multi-domaines.
- ADR-009 — Contrôle des dépendances entre domaines.
- ADR-011 — Répartition de la responsabilité des autorisations.

## 7. État d'implémentation

La stratégie est acceptée sur le plan architectural. Le catalogue des événements, leur classification de criticité, l'infrastructure Outbox éventuelle, l'idempotence des consommateurs et les mécanismes de reprise restent à auditer et à concevoir. Aucun événement existant n'est déclaré conforme sur la seule base de cet ADR. Aucun code, table, migration, worker ou configuration de queue n'est modifié implicitement par cette décision.
