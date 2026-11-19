# ADR-020 — Stratégie CI/CD avec approbations humaines aux étapes critiques

- **Statut :** ACCEPTED
- **Date :** 19 novembre 2026
- **Branche de référence :** `feature/Marketplace`
- **Portée :** MAHLINE V1

## Contexte

La décision 16 (ADR-016) a défini les environnements `local`, `testing`, `staging` et `production`, ainsi que le principe d'une promotion contrôlée. La décision 18 (ADR-018) impose des contrôles architecturaux automatisés dans la CI avant les promotions. Le niveau d'automatisation et le rôle des validations humaines restaient à arbitrer.

## Décision

**Option C — adopter une CI/CD automatisée avec approbations humaines aux étapes critiques.**

- La CI automatise les contrôles reproductibles : qualité du code, tests, contrôles de sécurité pertinents, contrats et règles d'architecture.
- Les étapes de livraison et de déploiement répétitives sont automatisées autant que possible afin de produire des artefacts traçables et reproductibles.
- Les promotions sensibles, en particulier vers la production, nécessitent une approbation humaine explicite avant exécution.
- Les approbations doivent être attribuées à des personnes autorisées, associées à la version/au commit et à l'environnement cible, et conservées dans une trace consultable.
- Un échec d'un contrôle obligatoire bloque la promotion ; une approbation humaine ne contourne pas les contrôles techniques obligatoires.
- Les secrets, permissions des runners et accès de déploiement suivent le moindre privilège et restent séparés par environnement.
- Les procédures de déploiement, de migration, de vérification après déploiement et de retour arrière doivent être documentées et testées.
- Les étapes exactes soumises à approbation, les responsables habilités, l'outil CI/CD, les stratégies de déploiement et les conditions de rollback seront précisés lors de la conception opérationnelle, sans remettre en cause le principe validé ici.

## Conséquences

- Les déploiements ne sont ni entièrement manuels ni entièrement autonomes.
- L'automatisation réduit les opérations répétitives et garantit la reproductibilité ; les approbations humaines conservent le contrôle des décisions de promotion critiques.
- Les pipelines doivent fournir une preuve claire des contrôles, de l'artefact, du commit, de l'environnement et de l'approbateur.

## Hors périmètre et état d'implémentation

Cette ADR définit la cible d'architecture V1. Elle ne signifie pas qu'un pipeline CI/CD existe ou qu'il est configuré. Aucun pipeline, secret, environnement, configuration, code applicatif, migration ou test n'est créé ou modifié par cette décision.

## Références

- ADR-016 — Stratégie des environnements et promotion des versions.
- ADR-018 — Gouvernance automatisée de l'architecture et des frontières entre domaines.
- ADR-019 — Applicabilité des décisions d'architecture à MAHLINE V1.
- `docs/architecture/DOMAIN_ROADMAP.md`.
