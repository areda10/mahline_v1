# Registre canonique des décisions d'architecture — MAHLINE V1

- **Portée :** MAHLINE V1
- **Branche de référence :** `feature/Marketplace`
- **Dernière vérification :** 19 novembre 2026
- **Règle :** ce répertoire est la source canonique des ADR applicables à MAHLINE V1.

## 1. Règles de numérotation et d'autorité

1. Les identifiants `ADR-001` à `ADR-020` désignent la série des décisions d'architecture MAHLINE V1.
2. Un identifiant V1 ne peut désigner qu'une seule décision. Les titres, liens et statuts doivent être maintenus dans ce registre.
3. `docs/framework/ADR.md` est conservé comme **registre historique du framework**, distinct de la série V1. Ses ADR-001 à ADR-015 ne doivent pas être interprétés comme les ADR-001 à ADR-015 de MAHLINE V1.
4. Les documents historiques ne sont ni supprimés ni renumérotés dans le cadre de cette correction.
5. Les décisions acceptées sont des exigences cibles ; elles ne prouvent pas que le code est implémenté ou conforme.
6. Toute nouvelle décision ou révision doit préserver l'historique, avoir un identifiant unique et être référencée dans `DOMAIN_ROADMAP.md`.

## 2. État du registre V1

| Identifiant V1 | Document | État documentaire |
|---|---|---|
| ADR-001 à ADR-008 | Sources originales non retrouvées dans l'arbre Git vérifié | **À récupérer / vérifier** — ne pas reconstituer le contenu par supposition |
| [ADR-009 — Dépendances entre domaines](ADR-009-domain-dependencies.md) | Présent | ACCEPTED |
| [ADR-010 — Propriété des configurations](ADR-010-domain-configuration-ownership.md) | Présent | ACCEPTED |
| [ADR-011 — Propriété des autorisations](ADR-011-authorization-ownership.md) | Présent | ACCEPTED |
| [ADR-012 — Fiabilité des événements métier](ADR-012-domain-events-reliability.md) | Présent | ACCEPTED |
| [ADR-013 — Traitement asynchrone et queues](ADR-013-async-processing-and-queues.md) | Présent | ACCEPTED |
| [ADR-014 — Observabilité et traçage](ADR-014-observability-and-tracing.md) | Présent | ACCEPTED |
| [ADR-015 — Sauvegarde et reprise après sinistre](ADR-015-backup-and-disaster-recovery.md) | Présent | ACCEPTED |
| [ADR-016 — Environnements et promotion des versions](ADR-016-environments-and-release-promotion.md) | Présent | ACCEPTED |
| [ADR-017 — Versionnement et compatibilité des contrats](ADR-017-contract-versioning-and-compatibility.md) | Présent | ACCEPTED |
| [ADR-018 — Gouvernance automatisée de l'architecture](ADR-018-automated-architecture-governance.md) | Présent | ACCEPTED |
| [ADR-019 — Applicabilité à MAHLINE V1](ADR-019-v1-architecture-decision-scope.md) | Présent | ACCEPTED |
| [ADR-020 — CI/CD et approbations humaines](ADR-020-ci-cd-human-approvals.md) | Présent | ACCEPTED |

## 3. Traitement des ADR-001 à ADR-008

La feuille de route et l'ADR-019 déclarent que les décisions 1 à 18 s'appliquent à MAHLINE V1. Toutefois, au commit audité, aucun fichier ADR V1 numéroté 001 à 008 n'est présent dans `docs/architecture/decisions/`, et la recherche d'historique Git pour les chemins ADR-001/008 testés n'a retourné aucun commit.

Le fichier historique `docs/framework/ADR.md` contient bien huit décisions initiales, mais il s'agit d'une série de décisions du framework avec son propre périmètre et des identifiants qui entrent en collision avec ADR-009 à ADR-015 de la série V1. Leur équivalence avec les décisions V1 001 à 008 **n'est pas démontrée**.

En conséquence :
- les ADR historiques du framework sont conservés sans modification de leur contenu décisionnel ;
- les ADR V1 001 à 008 sont marqués comme documents sources manquants à retrouver ;
- aucune décision V1 manquante n'est inventée ou automatiquement copiée depuis le registre framework ;
- avant de déclarer le registre V1 documentaire complet, il faut retrouver les sources approuvées ou obtenir une validation explicite de leur reprise, avec traçabilité.

## 4. Références centrales

- [Feuille de route centrale des domaines](../DOMAIN_ROADMAP.md)
- [Plan concret de correction V1](../IMPLEMENTATION_REMEDIATION_PLAN.md)
- [Registre historique du framework](../../framework/ADR.md) — archive distincte, non canonique pour la numérotation V1.

## 5. Journal

### 19 novembre 2026
- Création du registre canonique V1.
- Détection et documentation de la collision entre les identifiants historiques du framework et ceux des décisions V1.
- ADR-001 à ADR-008 laissés en état « source à retrouver » ; aucune reconstruction spéculative.
