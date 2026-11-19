# ADR-010 — Propriété et gestion des configurations des domaines

- **Statut :** ACCEPTÉ
- **Date :** 19 novembre 2026
- **Décision :** 10
- **Périmètre :** architecture cible MAHLINE V7.0
- **Portée :** décision d'architecture ; ne vaut pas implémentation

## 1. Contexte

Les domaines MAHLINE peuvent nécessiter des paramètres techniques, des valeurs par défaut, des règles métier configurables, des seuils, des délais ou des options activables. Une configuration entièrement centralisée risque de mélanger les responsabilités métier. À l'inverse, une dispersion sans convention rendrait la configuration difficile à découvrir, valider, tracer et exploiter.

La configuration doit respecter la propriété des domaines et rester compatible avec le monolithe modulaire actuel ainsi qu'avec une éventuelle extraction future de domaines.

## 2. Décision

**L'option C — approche hybride avec propriété stricte — est adoptée.**

### 2.1 Configuration technique et environnementale

- Les paramètres d'infrastructure et d'environnement restent dans le mécanisme standard Laravel : fichiers config/ et variables d'environnement.
- Cela couvre notamment les connexions, services externes, mail, cache, files d'attente, stockage, secrets et paramètres d'exécution.
- Les secrets ne doivent pas être stockés comme paramètres métier ordinaires en base de données.
- Un domaine ne doit pas modifier directement la configuration technique d'un autre domaine à l'exécution.

### 2.2 Configuration métier détenue par son domaine

- Chaque paramètre métier a un domaine propriétaire clairement identifié.
- Les valeurs métier modifiables à l'exécution sont stockées et gérées par des tables appartenant à ce domaine.
- Les valeurs par défaut statiques peuvent être définies dans le code ou dans une configuration propre au domaine, si cela apporte une réelle valeur ; elles ne doivent pas être dupliquées dans une configuration globale.
- Un domaine ne lit ni ne modifie directement les tables de configuration métier internes d'un autre domaine.

### 2.3 Accès et modification inter-domaines

- Un domaine consommateur demande une valeur métier à son propriétaire via un contrat public documenté.
- Les modifications passent par les opérations publiques prévues par le domaine propriétaire, avec les autorisations correspondantes.
- Aucun accès direct aux modèles, repositories, tables ou mécanismes internes de configuration d'un autre domaine n'est autorisé.
- Les dépendances et contrats suivent les règles de l'ADR-009.

### 2.4 Validation, traçabilité et cache

Chaque domaine propriétaire définit et documente, selon la nature de ses paramètres :

- les valeurs par défaut et les valeurs effectives ;
- les types, formats, bornes et règles de validation ;
- la portée d'application (globale, coopérative ou autre portée métier autorisée) ;
- les rôles autorisés à consulter ou modifier les paramètres ;
- la traçabilité des changements, avec auteur, date, anciennes/nouvelles valeurs lorsque cela est approprié et sans journaliser de secrets ;
- les règles d'invalidation ou de versionnement du cache ;
- le comportement en cas de valeur absente, invalide ou inaccessible.

Les paramètres ne doivent pas devenir un mécanisme d'exécution arbitraire de code ou de modification libre de la configuration Laravel.

## 3. Règles de conception

1. Une donnée de configuration métier possède un seul domaine propriétaire.
2. Les paramètres techniques/environnementaux et les paramètres métier sont distingués explicitement.
3. Les paramètres métier modifiables sont persistés dans le domaine propriétaire, pas dans une table globale fourre-tout.
4. Les consommateurs utilisent exclusivement les contrats publics du propriétaire.
5. Les portées multi-coopératives doivent respecter les frontières d'accès et d'isolation métier.
6. Les valeurs sensibles et secrets suivent le mécanisme de gestion des secrets de l'infrastructure.
7. Les changements de configuration doivent être validés, autorisés et auditables.
8. Les mécanismes de cache ne doivent pas laisser subsister des valeurs obsolètes après une modification.
9. Les paramètres et contrats doivent être documentés pour permettre une extraction future du domaine.

## 4. Conséquences

### Positives

- La propriété des règles métier reste cohérente avec les frontières des domaines.
- Les paramètres sont gérés au plus près des règles qu'ils gouvernent.
- Les consommateurs ne dépendent pas du schéma de stockage interne d'un autre domaine.
- Les configurations peuvent évoluer avec les domaines et faciliter une extraction ultérieure.

### Contraintes

- Chaque domaine doit fournir des contrats et une validation cohérents.
- La gestion des droits, de la traçabilité et du cache demande une conception explicite.
- Les paramètres transversaux doivent être attribués à un propriétaire plutôt que placés par défaut dans un stockage global.

## 5. Plan d'application

1. Inventorier les configurations Laravel et les paramètres actuellement codés en dur.
2. Classer chaque paramètre comme technique/environnemental, valeur par défaut statique métier ou paramètre métier modifiable.
3. Attribuer chaque paramètre métier à un domaine propriétaire.
4. Définir pour chaque domaine les contrats de lecture et de modification nécessaires.
5. Définir les validations, portées, permissions, journaux et stratégies de cache.
6. Déplacer ou créer les configurations uniquement après audit et approbation des changements.
7. Ajouter des tests architecturaux et fonctionnels pour empêcher les accès directs aux configurations internes d'autres domaines.

## 6. Liens

- ADR-001 — Architecture modulaire évolutive.
- ADR-005 — Communication inter-domaines.
- ADR-007 — Shared Kernel strict.
- ADR-009 — Contrôle des dépendances entre domaines.

## 7. État d'implémentation

Cette décision est acceptée sur le plan architectural. L'inventaire des configurations existantes, leur classification et leur éventuelle migration restent à effectuer. Aucun changement de code, de configuration Laravel, de table ou de migration n'est autorisé implicitement par cet ADR.
