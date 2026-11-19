# ADR-013 — Traitements asynchrones et architecture des files d'attente

- **Statut :** ACCEPTÉ
- **Date :** 19 novembre 2026
- **Décision :** Option C — architecture hybride avec isolation logique
- **Portée :** architecture cible MAHLINE V7.0
- **Dépendances :** ADR-009 (dépendances inter-domaines), ADR-012 (fiabilité des événements métier)

## Contexte

MAHLINE doit exécuter des traitements qui peuvent être longs, coûteux ou soumis à des dépendances externes : génération de PDF, envoi d'e-mails, traitement des médias, agrégations statistiques, consommation d'événements et opérations de livraison. Une file unique sans isolation peut permettre à des tâches volumineuses de retarder des opérations critiques. À l'inverse, multiplier dès le départ les infrastructures et workers indépendants par domaine augmenterait inutilement la complexité opérationnelle.

## Décision

Adopter une architecture hybride : infrastructure de queue commune au départ, avec séparation logique des files et isolation progressive des traitements selon le domaine, la catégorie, la criticité et la charge.

### 1. Infrastructure commune et isolation logique

- Le backend de queue et l'infrastructure d'exécution peuvent être communs au démarrage.
- Les tâches sont réparties dans des files nommées et documentées par domaine ou catégorie lorsque cela apporte une isolation utile.
- Les noms, responsabilités et consommateurs des files sont inventoriés ; aucun domaine ne s'approprie la queue d'un autre domaine.
- Les pools de workers, leur concurrence et leurs ressources peuvent être séparés pour protéger les traitements prioritaires des tâches lourdes.
- Le découpage reste proportionné au volume et aux exigences opérationnelles : pas de service ou d'infrastructure indépendante par domaine par principe.

### 2. Frontières entre domaines

- Un job inter-domaines invoque les contrats publics du domaine propriétaire ou consomme un événement public documenté.
- Il ne doit pas importer ni manipuler directement les modèles, repositories, services internes ou détails de persistance d'un autre domaine, conformément à l'ADR-009.
- Les jobs et événements sont des mécanismes de transport/exécution, pas une autorisation de contourner les frontières métier.
- Les planifications récurrentes sont rattachées au domaine propriétaire du traitement et respectent les mêmes contrats.

### 3. Données transportées et validation

- Les jobs transportent de préférence des identifiants stables et des DTO/valeurs sérialisables minimales, plutôt que des modèles Eloquent complets.
- Les secrets, mots de passe, tokens, données sensibles inutiles et payloads volumineux ne doivent pas être sérialisés dans les jobs.
- À l'exécution, le traitement recharge les données nécessaires par les interfaces autorisées et revalide l'état métier pertinent ; il ne suppose pas que l'état observé lors de la mise en file est toujours actuel.
- Les jobs ne transportent pas une autorisation durable implicite : les règles d'accès et le contexte métier doivent être vérifiés selon la nature de l'opération.

### 4. Fiabilité, reprises et idempotence

- Les jobs sont conçus pour être idempotents lorsque leur exécution peut être répétée ; les opérations non répétables utilisent une clé d'idempotence ou une protection métier adaptée.
- Les retries sont bornés et configurés selon le type de traitement, avec délais progressifs (backoff), timeouts, nombre maximal de tentatives et politique d'échec explicites.
- Le système ne promet pas une exécution exactement une fois. Les consommateurs et jobs doivent tolérer les livraisons répétées et viser un effet métier unique.
- L'ordre d'exécution n'est pas garanti par défaut. Les traitements qui exigent un ordre strict doivent le déclarer et mettre en œuvre une stratégie explicite.
- Les tâches définitivement échouées sont conservées avec un contexte opérationnel minimisé, rendues observables et reprises uniquement par une procédure contrôlée, autorisée et traçable.
- Les verrous, jobs uniques et mécanismes de déduplication sont utilisés lorsqu'une exigence métier ou opérationnelle les justifie, avec une stratégie de libération et d'expiration.

### 5. Transactions et événements

- La mise en queue d'un traitement dépendant d'une écriture transactionnelle doit respecter la règle de dispatch après commit.
- Les événements durables et critiques suivent l'ADR-012 : recours au Transactional Outbox lorsque nécessaire, puis consommation idempotente.
- Un job ne doit pas être lancé sur la base d'une transaction qui pourrait ensuite être annulée et dont les données ne seraient donc pas confirmées.
- Les workflows couvrant plusieurs domaines coordonnent les contrats, événements et éventuelles actions compensatoires ; un job asynchrone ne crée pas à lui seul une transaction distribuée.

### 6. Priorités et observabilité

- Les files et workers sont organisés pour éviter qu'un traitement massif (par exemple médias ou statistiques) bloque les tâches critiques (par exemple opérations de commande ou notifications urgentes).
- Les priorités sont fondées sur la criticité métier explicitement définie, pas uniquement sur l'ordre d'arrivée.
- La supervision doit au minimum permettre d'observer le volume en attente, l'ancienneté des tâches, les durées d'exécution, les échecs/reprises et l'état des workers.
- Les seuils, alertes, rétention et accès aux informations d'exploitation seront définis dans la stratégie d'observabilité.

### 7. Évolutivité

- La séparation logique doit permettre d'extraire ultérieurement certains workers, files ou domaines vers des ressources dédiées sans modifier les contrats métier ni autoriser les accès directs interdits.
- Une extraction est décidée sur la base de besoins mesurés (charge, disponibilité, sécurité, isolation ou déploiement), et non présumée nécessaire dès la V1.

## Conséquences

### Positives

- Limite la complexité initiale tout en apportant une isolation utile.
- Réduit le risque de concurrence entre tâches lourdes et traitements critiques.
- Clarifie la propriété des jobs et des files, ainsi que les frontières inter-domaines.
- Prépare une montée en charge et une extraction progressive des workers.

### Contraintes

- Nécessite un catalogue des files, jobs, priorités et propriétaires.
- Exige des jobs idempotents, des politiques de retry/timeout explicites et une procédure de reprise des échecs.
- Nécessite une supervision opérationnelle et des tests portant sur les doublons, retries, délais et transactions.
- Le choix concret du backend, des noms de files, des tailles de workers et des seuils reste à établir lors de l'audit technique et du dimensionnement.

## Hors périmètre de cette décision

Cette décision ne choisit pas de backend de queue, ne fixe pas de nombre de workers, ne crée aucune queue, aucun job, aucune table Outbox et aucune configuration. Elle ne modifie pas le code applicatif, les migrations, les tests ou le déploiement. Elle définit uniquement l'architecture cible.

## Vérifications et travaux restant à réaliser

1. Inventorier les jobs, listeners, notifications, commandes planifiées et tâches asynchrones existants.
2. Identifier le domaine propriétaire, la criticité et la file cible de chaque traitement.
3. Définir les contrats de payload, clés d'idempotence, retries, backoff, timeouts et règles d'échec.
4. Vérifier le dispatch après commit et la cohérence avec les transactions métier et l'Outbox.
5. Définir les besoins d'isolation des workers et la stratégie de supervision.
6. Ajouter des tests de doublons, retries, échec définitif, rollback, ordre requis et frontières inter-domaines.
7. Mesurer les charges avant toute extraction d'infrastructure.

## Références

- [ADR-009 — Contrôle des dépendances entre domaines](ADR-009-domain-dependencies.md)
- [ADR-012 — Fiabilité et cycle de vie des événements métier](ADR-012-domain-events-reliability.md)
- [Feuille de route centrale des domaines](../DOMAIN_ROADMAP.md)
