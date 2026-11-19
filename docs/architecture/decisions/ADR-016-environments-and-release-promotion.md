# ADR-016 — Stratégie des environnements et promotion des versions

- **Statut :** ACCEPTÉ
- **Date :** 19 novembre 2026
- **Décision :** Option C — environnements centralisés avec séparation stricte
- **Portée :** architecture cible MAHLINE V7.0
- **Dépendances :** ADR-009 (frontières inter-domaines), ADR-014 (observabilité), ADR-015 (sauvegarde et reprise)

## Contexte

Le développement, les tests automatisés, la validation métier et l'exploitation réelle n'ont ni les mêmes besoins ni les mêmes niveaux de risque. Partager des bases, fichiers, secrets ou services entre ces usages peut provoquer des corruptions de données, des fuites ou des effets externes involontaires. Des environnements séparés par domaine seraient prématurés pour le monolithe modulaire actuel et rendraient les validations transversales difficiles.

## Décision

Adopter quatre environnements de référence — local, testing, staging et production — gérés selon des règles communes de séparation, de promotion et de contrôle. Les environnements sont séparés à l'échelle de la plateforme, pas créés indépendamment pour chaque domaine.

### 1. Environnements de référence

- **Local :** développement et expérimentation sur poste individuel ; aucune connexion implicite aux données ou services de production.
- **Testing :** exécution automatisée des tests unitaires, d'intégration, Feature, sécurité et architecture ; données synthétiques et services simulés ou isolés.
- **Staging :** préproduction représentative de la production pour valider les versions, migrations, intégrations et parcours transversaux avant promotion.
- **Production :** environnement réel contenant les données et opérations des utilisateurs ; changements strictement contrôlés.

Chaque environnement possède ses propres paramètres, identifiants, bases de données, espaces de stockage et ressources externes lorsque cela est nécessaire. Aucun environnement non productif ne partage directement la base ou le stockage modifiable de production.

### 2. Configuration et secrets

- Les configurations sont explicites et propres à chaque environnement ; aucune valeur sensible n'est codée en dur dans le dépôt.
- Les secrets, clés, comptes techniques et identifiants d'intégration sont distincts par environnement et accessibles selon le moindre privilège.
- Les secrets de production ne sont pas copiés vers local, testing ou staging.
- Les changements de configuration sont traçables et soumis à une procédure de validation.
- Les intégrations externes non productives utilisent des environnements sandbox ou des doubles de test lorsque disponibles ; un environnement non productif ne doit pas déclencher d'opération réelle par défaut.

### 3. Données et confidentialité

- Testing utilise des jeux de données synthétiques ou fabriqués pour les scénarios de test.
- Staging utilise en priorité des données synthétiques représentatives.
- Toute utilisation exceptionnelle de données dérivées de la production exige une base légitime, une autorisation, une minimisation et une anonymisation ou pseudonymisation vérifiée avant transfert.
- Les exports de production ne sont jamais importés tels quels dans un environnement inférieur.
- Les données, fichiers, journaux et sauvegardes restent soumis aux règles de confidentialité, de rétention et d'accès applicables.

### 4. Tests et validation

- La CI exécute les contrôles adaptés au changement : tests, analyse statique, formatage, tests de sécurité et tests architecturaux.
- Les tests doivent être reproductibles et ne pas dépendre de données ou services de production.
- Staging vérifie les parcours transversaux, les intégrations, les migrations et les paramètres de déploiement dans des conditions aussi proches que possible de la production.
- La validation d'une version est fondée sur des résultats observables et conservés ; une compilation ou un déploiement réussi ne remplace pas les tests métier.
- Les tests destructifs ou de charge sont exécutés dans un environnement isolé et explicitement autorisé.

### 5. Promotion et déploiement

- Une version suit un processus contrôlé de validation avant sa promotion vers production.
- L'artefact promu doit être identifié et traçable ; éviter de reconstruire un artefact différent entre la validation et la production lorsque la chaîne technique le permet.
- Les déploiements production exigent des contrôles préalables : état de la version, sauvegarde ou point de restauration adapté, compatibilité des migrations, disponibilité des dépendances et procédure de retour arrière.
- Les migrations suivent des stratégies compatibles avec les déploiements progressifs lorsque nécessaire (par exemple changements additifs, période de compatibilité et suppression différée).
- Les changements incompatibles sont séquencés explicitement ; le rollback applicatif ne doit pas supposer qu'une migration destructive est automatiquement réversible.
- Les déploiements sont journalisés et associés à une version, un commit, un environnement et un responsable.

### 6. Accès et opérations

- Les accès sont distincts par environnement et accordés selon le moindre privilège.
- L'accès production est limité aux personnes et mécanismes autorisés, avec authentification renforcée lorsque disponible et journalisation des opérations sensibles.
- Les comptes de test ne sont pas réutilisés en production.
- Les tâches planifiées, files et notifications non productives sont isolées afin d'éviter les envois, paiements, expéditions ou autres effets réels.
- Les données de production ne sont pas modifiées pour faciliter un test ou une démonstration.

### 7. Cohérence avec la reprise après incident

- Les sauvegardes et restaurations respectent l'ADR-015 ; chaque environnement possède une stratégie adaptée à son niveau de criticité.
- Les procédures de restauration sont d'abord éprouvées dans un environnement isolé lorsque possible.
- Les paramètres et versions nécessaires à la reconstruction sont conservés de manière contrôlée.
- Un retour à une version applicative antérieure et une restauration de données sont deux opérations distinctes, avec des critères et autorisations propres.

## Conséquences

### Positives

- Réduit le risque qu'un test ou un déploiement affecte les données réelles.
- Rend les validations préproduction plus représentatives.
- Protège les secrets et les intégrations de production.
- Clarifie le processus de promotion, de déploiement et de retour arrière.
- Conserve une approche cohérente avec le monolithe modulaire sans multiplier les environnements par domaine.

### Contraintes

- Nécessite des ressources distinctes pour les bases, stockages et services selon l'environnement.
- Demande une gestion rigoureuse des secrets, données synthétiques, intégrations sandbox et droits d'accès.
- La préproduction doit rester suffisamment proche de la production pour que ses résultats soient pertinents.
- Les migrations et déploiements doivent être conçus pour permettre des transitions sûres et contrôlées.

## Hors périmètre de cette décision

Cet ADR ne crée aucun environnement, base, stockage, pipeline CI/CD, compte, secret ou configuration. Il ne choisit pas d'hébergeur, de fournisseur de secrets ni d'outil de déploiement. Il ne fixe pas les approbateurs, fenêtres de déploiement ou procédures détaillées propres à chaque service. Il ne modifie aucun code, migration ou workflow existant.

## Vérifications et travaux restant à réaliser

1. Inventorier les environnements, bases, stockages, services et intégrations réellement disponibles.
2. Comparer les configurations existantes et identifier les secrets ou ressources partagés à séparer.
3. Définir la stratégie de données synthétiques et les règles exceptionnelles d'anonymisation.
4. Définir le pipeline CI/CD, les artefacts, les validations et les approbations de promotion.
5. Définir les contrôles pré-déploiement, la stratégie de migrations compatibles et les procédures de rollback.
6. Isoler les files, planifications et intégrations non productives de tout effet réel.
7. Tester les scénarios de déploiement, échec, retour applicatif et restauration de données.
8. Vérifier les accès et la journalisation des opérations de production.

## Références

- [ADR-009 — Contrôle des dépendances entre domaines](ADR-009-domain-dependencies.md)
- [ADR-014 — Observabilité technique et traçabilité transversale](ADR-014-observability-and-tracing.md)
- [ADR-015 — Sauvegardes et reprise après incident](ADR-015-backup-and-disaster-recovery.md)
- [Feuille de route centrale des domaines](../DOMAIN_ROADMAP.md)
