# ADR-021 — Architecture hybride du monolithe modulaire MAHLINE

- **Statut :** ACCEPTÉ — décision confirmée par l'utilisateur le 19 novembre 2026
- **Périmètre :** MAHLINE V1 / architecture modulaire Laravel
- **Branche de référence :** `feature/Marketplace`
- **Décision :** architecture hybride, monolithe modulaire à contrats explicites

## 1. Décision

MAHLINE conserve un déploiement Laravel unique et une base SQL partagée, tout en appliquant des frontières de responsabilité strictes entre domaines. L'architecture combine :
- des cas d'usage synchrones via contrats publics lorsque le résultat est nécessaire immédiatement ;
- des événements métier versionnés, publiés après commit, pour annoncer les faits métier ;
- un Transactional Outbox réservé aux événements critiques dont la perte est inacceptable ;
- des transactions SQL locales au monolithe lorsqu'une invariance métier l'exige, orchestrées par un cas d'usage clairement propriétaire ;
- des références logiques entre domaines par défaut, avec clés étrangères physiques conservées uniquement lorsqu'une justification d'intégrité et une exception documentée l'exigent.

Cette architecture ne constitue ni un microservice prématuré ni un partage libre des modèles Eloquent.

## 2. Règles de dépendance

1. Un domaine possède ses modèles, tables, règles métier, services applicatifs, policies et migrations métier.
2. Un domaine consommateur ne doit pas importer les Models, Repositories, Services privés, Policies ou composants Infrastructure d'un autre domaine.
3. Les échanges synchrones utilisent des interfaces/DTO publics placés dans une surface `Contracts` appartenant au domaine fournisseur ; l'implémentation reste interne au fournisseur.
4. Les événements publics expriment des faits déjà validés. Ils sont versionnés, minimisés, publiés après commit et consommés de manière idempotente.
5. Les événements critiques utilisent une Outbox transactionnelle. Les événements simples et recalculables peuvent utiliser une publication après commit sans Outbox.
6. Les dépendances inter-domaines doivent être orientées et acycliques. Les cycles sont des violations à éliminer, pas des exceptions permanentes.
7. Les appels inter-domaines ne doivent pas contourner les policies du domaine propriétaire de la ressource.
8. Les contrôleurs HTTP et le point de composition Laravel peuvent assembler plusieurs domaines ; cette orchestration ne transfère pas la propriété métier.

## 3. Données et transactions

- Chaque table métier a un domaine propriétaire unique.
- Les migrations métier nouvelles sont placées dans `app/Domains/{Domain}/Database/Migrations/`; les migrations framework et réellement transversales restent dans `database/migrations/`.
- Les migrations déjà exécutées ne sont jamais réécrites.
- Une FK physique inter-domaines n'est autorisée que si elle protège un invariant nécessaire au monolithe, sans cascade destructive inter-domaines, et si son exception est inscrite au registre des relations.
- À défaut, le domaine consommateur stocke un identifiant logique et valide l'existence/état via le contrat du domaine propriétaire.
- Une transaction SQL partagée est permise dans le monolithe si l'opération doit être atomique. Le cas d'usage propriétaire coordonne les écritures et les effets externes sont compensés ou déclenchés après commit.
- Les snapshots historiques (notamment commandes et lignes de commande) préservent les données nécessaires même si la ressource source évolue ou disparaît.

## 4. Propriété des domaines et cas particuliers

- `Localization` absorbe le référentiel `Countries`. Pays, adresses, régions, langues et paramètres géographiques sont sous sa responsabilité.
- `Identity` possède comptes, authentification, sessions, rôles et permissions générales ; chaque domaine métier possède les autorisations de ses ressources.
- `Audit` expose un contrat public d'écriture/consultation contrôlée ; les domaines ne dépendent pas de son repository interne.
- `Media` possède les fichiers, métadonnées, validation et intégrité des médias. Les domaines fournisseurs exposent un contrat d'identification/propriété de média ; Media ne doit pas importer leurs modèles internes.
- `Orders` possède les commandes, sous-commandes et snapshots de commande. `Products` possède produits, variantes et prix de référence ; Orders consulte ces informations via un contrat de checkout/quote stable.
- `Catalog`, `Categories` et `Products` restent des domaines distincts. Les pivots qui les relient sont traités comme des relations d'intégration explicites, avec propriétaire unique et contrat de commande/lecture ; ils ne justifient pas des imports réciproques de modèles.
- `Cooperatives` possède les coopératives et leurs règles métier. Identity possède les comptes et les affectations d'identité ; le lien d'affectation est une intégration explicite et non une propriété croisée des modèles.

## 5. Providers et composition root

- `bootstrap/providers.php` enregistre les providers de domaines et les providers framework.
- Chaque provider de domaine enregistre ses bindings, policies, observers, listeners et routes propres au domaine.
- `AppServiceProvider` reste limité aux préoccupations réellement globales et aux bindings d'assemblage qui ne peuvent pas appartenir à un domaine.
- Aucun provider de domaine ne doit importer les détails internes d'un domaine consommateur.
- Les providers inactifs ne sont supprimés qu'après vérification de leur chargement et de leurs usages.

## 6. Exceptions et gouvernance

Toute exception inter-domaines doit préciser : tables/classes concernées, propriétaire, invariant protégé, sens de dépendance, absence de cascade destructive, justification, tests et stratégie de retrait éventuelle. Aucune exception globale n'est permise.

Les tests d'architecture vérifient les imports, cycles, ownership des migrations/tables, surfaces publiques, namespaces PSR-4 et exceptions. Ils sont d'abord exécutés en baseline, puis deviennent bloquants pour toute nouvelle violation.

## 7. Déploiement progressif

1. Cartographier toutes les relations et dépendances existantes.
2. Intégrer Countries dans Localization (première consolidation réalisée sur la branche).
3. Introduire les contrats publics sans changer le comportement métier.
4. Découpler les services et policies par lots verticaux, avec tests de caractérisation.
5. Traiter les FK/pivots par nouvelles migrations additives uniquement, après preuve d'intégrité et plan de déploiement.
6. Ajouter les tests d'architecture et la CI.
7. Exécuter la suite complète et vérifier Pint/analyse statique avant de déclarer chaque lot validé.

## 8. Conséquences

La solution conserve la simplicité opérationnelle du monolithe et la cohérence transactionnelle SQL, tout en réduisant le couplage et en préparant une extraction future de domaines si elle devient nécessaire. Elle exige des contrats versionnés, des owners de données explicites et des tests de frontières.

## 9. Références

- ADR-009 — Dépendances entre domaines
- ADR-010 — Propriété des configurations
- ADR-011 — Propriété des autorisations
- ADR-012 — Fiabilité des événements métier
- ADR-017 — Versionnement des contrats
- ADR-018 — Gouvernance automatisée
