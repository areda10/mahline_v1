# ADR-019 — Applicabilité des décisions d'architecture à MAHLINE V1

- **Statut :** ACCEPTED
- **Date :** 19 novembre 2026
- **Branche de référence :** `feature/Marketplace`
- **Portée :** cadrage de MAHLINE V1

## Contexte

Les décisions d'architecture prises au cours de la revue doivent constituer le cadre de référence de la première version de MAHLINE. Il est nécessaire d'éviter qu'elles soient interprétées comme de simples pistes pour une version ultérieure ou qu'elles soient perdues au fil des audits et implémentations.

## Décision

1. Les décisions d'architecture numérotées 1 à 18, enregistrées dans les ADR-001 à ADR-018 et référencées dans la feuille de route centrale, sont retenues comme décisions de référence pour **MAHLINE V1**.
2. Elles doivent être prises en compte lors de la définition des domaines, de leurs contrats, de leurs données, de leurs autorisations, de leurs tests, de leurs environnements et de leur exploitation.
3. Toute décision nouvelle, précision ou modification validée doit être documentée dans un ADR dédié et référencée dans `docs/architecture/DOMAIN_ROADMAP.md`. Une réponse en conversation ne remplace pas son enregistrement dans le dépôt.
4. Avant de modifier un ADR accepté, conserver la traçabilité de la décision précédente, expliquer le motif et enregistrer explicitement la nouvelle décision ou révision.
5. Les décisions architecturales ne constituent pas une preuve d'implémentation. Chaque exigence reste à auditer, concevoir, implémenter et tester selon le suivi du domaine concerné. Aucun élément ne doit être déclaré conforme sans preuve vérifiable.
6. Les choix encore ouverts ne sont pas réputés décidés par cette règle. En particulier, la stratégie détaillée de déploiement et de promotion en production reste à arbitrer séparément ; la présente décision ne sélectionne pas une option pour la question 19.

## Règle de documentation obligatoire

Pour chaque décision validée :
- créer ou mettre à jour le document ADR approprié ;
- enregistrer le statut, la date, le périmètre V1, les règles et conséquences ;
- ajouter un lien et une entrée au journal de `DOMAIN_ROADMAP.md` ;
- relire les fichiers enregistrés et vérifier le commit sur `feature/Marketplace` ;
- ne pas fusionner vers `main` sans instruction explicite.

## Hors périmètre

Cette décision ne signifie pas que les décisions 1 à 18 sont déjà implémentées ou que les domaines sont conformes. Elle ne modifie aucun code applicatif, migration, configuration, pipeline ou test.

## Références

- ADR-001 à ADR-018 — décisions d'architecture de MAHLINE.
- `docs/architecture/DOMAIN_ROADMAP.md` — registre central et journal des décisions.
