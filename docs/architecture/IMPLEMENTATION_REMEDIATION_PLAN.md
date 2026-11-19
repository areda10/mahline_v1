# MAHLINE V1 — Plan concret de correction et de mise en conformité

- **Date :** 19 novembre 2026
- **Dépôt :** `areda10/mahline_v1`
- **Branche cible :** `feature/Marketplace`
- **Commit de référence de l'audit :** `8eaf654cb315b032ea7c12f370ba14a86993ed50`
- **Statut :** plan établi à partir de constats partiels vérifiés ; exécution applicative non commencée
- **Portée :** architecture, domaines, données, dépendances, documentation, tests et CI

> Ce plan transforme les constats d'audit en tâches ordonnées. Il ne certifie pas la conformité du code. Les contrôles marqués « à vérifier » doivent être exécutés avant toute correction correspondante. Les migrations déjà exécutées ne seront jamais réécrites : toute évolution de schéma passera par une nouvelle migration compatible.

## 1. Règles d'exécution

1. Travailler exclusivement sur `feature/Marketplace`. Aucun merge vers `main` sans autorisation explicite.
2. Auditer le fichier réel et son SHA avant chaque modification ; ne pas remplacer un fichier sur la base d'un extrait ancien.
3. Ne pas déplacer ou réécrire en masse les domaines pour les faire ressembler à une structure cible. Appliquer les frontières progressivement, cas par cas.
4. Ne pas créer de domaines ou tables avant validation de leur périmètre et de leurs contrats.
5. Ne pas modifier les migrations déjà exécutées. Ajouter une migration corrective, avec stratégie de déploiement et de retour arrière, si nécessaire.
6. Aucun test n'est réputé vert sans exécution réelle et résultat conservé. Une suite non exécutée est « À exécuter ».
7. Chaque correction doit inclure tests ciblés, régression, mise à jour du suivi et preuve de commit.
8. Toute exception à une règle d'architecture doit être justifiée, limitée et inscrite dans un registre.

## 2. Priorités

| Priorité | Sujet | Pourquoi | Résultat attendu |
|---|---|---|---|
| P0 | Référentiel ADR et documentation contradictoire | Les identifiants ADR se chevauchent et les documents de suivi sont obsolètes | Une source canonique et un historique traçable |
| P0 | Inventaire réel des dépendances et des données | Impossible d'appliquer les frontières sans savoir qui possède quoi | Matrice vérifiée fichiers, tables, FK, relations et appels |
| P0 | Sécurité des frontières et autorisations | Risque de fuite inter-coopérative ou de contournement des Policies | Contrôles centralisés et tests négatifs par rôle/périmètre |
| P1 | Contrats inter-domaines et événements | Les appels directs créent du couplage | Contrats publics, événements après commit et consommateurs idempotents |
| P1 | Orders / Inventory / Delivery / Finance | Cœur marketplace et flux multi-coopératifs | Modèle métier cohérent et frontières explicites |
| P1 | Qualité des migrations et propriété des tables | Des FK inter-domaines peuvent rigidifier les domaines | Cartographie et stratégie d'évolution non destructive |
| P1 | Tests d'architecture et CI | Les règles doivent empêcher les régressions | Contrôles bloquants automatisés |
| P2 | Observabilité, sauvegardes et exploitation | Nécessaire avant livraison et production | Corrélation, runbooks, restauration testée |
| P2 | Refactor structurel ciblé | Réduire le couplage sans réécriture risquée | Déplacements limités et justifiés par domaine |

## 3. Plan d'exécution par lots

### Lot 0 — Stabiliser le référentiel documentaire (P0)

**Constats vérifiés**
- `docs/framework/ADR.md` porte les anciens ADR-001 à ADR-015.
- `docs/architecture/decisions/` contient les ADR-009 à ADR-020 avec des sujets différents pour ADR-009 à ADR-015.
- ADR-019 affirme que les ADR-001 à ADR-018 sont applicables à V1.
- Les ADR-001 à ADR-008 ne figurent pas dans le répertoire `docs/architecture/decisions/` sur l'arbre audité.
- Le registre central omettait le domaine Orders alors que `app/Domains/Orders` et les migrations marketplace sont présents.
- Le rapport de dépôt du 17 novembre cible un HEAD antérieur et ne doit pas servir d'inventaire courant.

**Fichiers cibles**
1. `docs/framework/ADR.md` — conserver comme archive historique du framework, retirer seulement après arbitrage son statut de registre V1 actif ; ne pas renuméroter son contenu sans plan de migration documentaire.
2. `docs/architecture/decisions/ADR-001` à `ADR-008` — retrouver les décisions V1 originales dans l'historique Git ou les conversations/documents de référence, puis créer les fiches manquantes sans reconstituer de mémoire incertaine.
3. `docs/architecture/decisions/ADR-009` à `ADR-020` — conserver les identifiants et liens actuels.
4. Nouveau `docs/architecture/decisions/README.md` ou registre équivalent — définir la source canonique, l'index, les statuts, la portée et la politique de révision.
5. `docs/architecture/DOMAIN_ROADMAP.md` — tenir à jour l'inventaire et le journal.
6. `docs/architecture/MAHLINE_V1_REPOSITORY_AUDIT.md` — marquer explicitement comme photographie historique du 17 novembre, ou le remplacer par un rapport daté après audit complet.

**Actions**
- Rechercher dans l'historique Git les ADR-001 à ADR-008 réellement approuvés.
- Établir une table de correspondance entre ADR historiques du framework et ADR applicables à V1.
- Ajouter un contrôle documentaire qui vérifie que chaque ADR référencé existe et que les numéros ne sont pas ambigus dans le registre V1.
- Ne pas modifier le contenu métier d'une décision acceptée sans nouvel ADR.

**Sortie / critère de clôture**
- Un index unique des décisions V1, liens valides, historique conservé, aucune collision non expliquée.
- Roadmap et rapport d'audit datés avec commit exact et limites explicites.

### Lot 1 — Inventaire exhaustif du code et des frontières (P0)

**Éléments déjà identifiés**
- 10 dossiers présents dans `app/Domains` : Audit, Catalog, Categories, Cooperatives, Dashboard, Identity, Localization, Media, Orders, Products. Countries est désormais intégré à Localization.
- `app/Core` contient les fondations et composants transverses ; `app/Shared` contient actuellement `DTOs/AuditEntryDTO.php`.
- Les dépendances directes observées sur l'échantillon incluent Products → Catalog/Categories/Audit, Catalog → Products, Orders → Product/ProductVariant/User, Media → Audit et modèles Product/Cooperative/User, Cooperatives → Localization.
- Cet échantillon n'est pas encore un inventaire exhaustif.

**Fichiers et zones à inspecter**
- `app/Domains/**/**/*.php` — imports, appels de services, accès modèles, requêtes SQL, relations Eloquent.
- `app/Core/**` et `app/Shared/**` — vérifier que le code commun est réellement transversal et ne contient pas de métier.
- `app/Providers/**`, `app/Providers/AppServiceProvider.php` (si présent), `bootstrap/providers.php` — bindings, observers et composition root.
- `routes/web.php`, `routes/api.php`, `bootstrap/app.php` — chargement, middleware, frontières HTTP.
- `composer.json`, `composer.lock` — PSR-4, dépendances et autoload.
- `docs/framework/ARCHITECTURE.md`, `docs/framework/DOMAIN_BOUNDARIES.md` — alignement avec ADR-001 à 018.

**Actions**
1. Générer une matrice de toutes les références PHP inter-domaines (FQCN, fichier source, symbole cible, type d'usage).
2. Recenser les relations Eloquent inter-domaines, observers, policies, Gates, événements, listeners, jobs, notifications et appels de repository.
3. Détecter les cycles de dépendances et distinguer dépendance de lecture, commande synchrone et événement.
4. Vérifier le PSR-4 classe/fichier/casse, les namespaces et les chemins réels sous Windows/Linux.
5. Examiner les providers non enregistrés et prouver s'ils sont inutilisés, chargés autrement ou obsolètes avant toute suppression.
6. Classer chaque dépendance : autorisée via contrat public, exception documentée, ou violation à corriger.

**Sortie / critère de clôture**
- Matrice exhaustive et reproductible, aucune dépendance inter-domaines non classée, aucun cycle non accepté.
- Pas de refactor tant que le propriétaire et le contrat cible ne sont pas définis.

### Lot 2 — Propriété des données et migrations (P0)

**État observé**
- 44 migrations dans `database/migrations/` au commit de référence.
- Aucune migration sous `app/Domains/*/Database/Migrations/`.
- La migration `2026_11_18_000003_create_marketplace_orders_tables.php` crée les tables de commande et référence notamment users, cooperatives, products et product_variants.
- Le choix de FK physiques inter-domaines doit être évalué au regard d'ADR-002 ; une FK n'est pas automatiquement interdite si une exception V1 est explicitement motivée.

**Fichiers cibles**
- Les 44 fichiers sous `database/migrations/` listés dans l'inventaire Git du commit de référence.
- Modèles Eloquent et relations des domaines concernés.
- `database/seeders/**`, factories et tests de contraintes.
- `docs/architecture/DOMAIN_ROADMAP.md` et registre des exceptions.

**Actions**
1. Établir une ligne par table : propriétaire métier, migration de création, colonnes, indexes, uniques, FK sortantes/entrantes, triggers et consommateurs.
2. Distinguer FK intra-domaine, FK inter-domaines, pivot, table de framework et relation logique.
3. Justifier chaque FK inter-domaines ou proposer un identifiant logique avec intégrité applicative.
4. Définir la destination des futures migrations métier dans `app/Domains/{Domain}/Database/Migrations/` et conserver les migrations framework/transversales au niveau global.
5. Vérifier contraintes MySQL, triggers, index générés, ordre de déploiement et restauration.
6. Pour toute évolution d'une table existante, ajouter une nouvelle migration additive/compatible ; aucune réécriture d'une migration appliquée.

**Sortie / critère de clôture**
- Registre complet de propriété et dépendances de données, exceptions acceptées, plan de migration non destructif testé sur base isolée.

### Lot 3 — Autorisations, identité et périmètres coopératifs (P0)

**Principes à faire respecter**
- Identity possède les comptes, l'authentification, les rôles et permissions générales.
- Chaque domaine métier reste propriétaire de l'autorisation d'accès à ses ressources, de l'ownership et des scopes coopératifs.
- Les Policies/Gates ne doivent pas être contournées par une autorisation globale trop large.
- Les quatre espaces Root, super_admin, cooperative_admin et user doivent rester distincts ; les règles de visibilité et de modification dépendent du rôle et des coopératives affectées.
- Les accès aux journaux d'audit et pièces jointes d'incident privés restent réservés à `super_admin_root` selon les décisions V1 applicables.

**Fichiers cibles**
- `app/Domains/Identity/**` — rôles, permissions, sessions, tokens, lifecycle et sécurité.
- `app/Domains/Cooperatives/**/Policies/**` et services — ownership et affectations.
- Policies, services et contrôleurs de Catalog, Categories, Products, Media et Orders.
- `app/Providers/AuthorizationServiceProvider.php`, `PermissionGateServiceProvider.php` et providers réellement enregistrés.
- Tests Feature/HTTP de chaque espace et tests d'accès inter-coopératives.

**Actions**
1. Construire la matrice rôle × action × ressource × périmètre.
2. Vérifier que chaque requête filtre les données par coopérative avant sérialisation.
3. Vérifier les règles de création, lecture, modification, changement de statut, suppression, restauration et export.
4. Vérifier les tokens API : capacités limitées aux permissions réellement accordées et politiques métier toujours appliquées.
5. Tester les refus explicites, les IDs d'une autre coopérative, les utilisateurs désactivés/suspendus/archivés et les sessions révoquées.
6. Ajouter des tests de non-régression pour toute correction.

**Sortie / critère de clôture**
- Matrice signée, tests positifs et négatifs par rôle et coopérative, aucun accès transversal non autorisé.

### Lot 4 — Contrats, événements et transactions inter-domaines (P1)

**Fichiers/zones**
- Services et repositories inter-domaines identifiés au Lot 1.
- `app/Domains/*/Application/Contracts` (à créer seulement si nécessaire).
- `app/Domains/*/Application/Events`, listeners, jobs et providers.
- `app/Providers/AppServiceProvider.php` et configuration queue.
- Outbox à concevoir uniquement pour les événements critiques.

**Actions**
1. Définir les contrats publics minimaux pour les opérations synchrones qui doivent retourner immédiatement un résultat.
2. Remplacer progressivement les accès aux modèles/repositories internes d'un autre domaine par ces contrats.
3. Publier les événements métier après commit, versionnés et documentés.
4. Rendre les consommateurs idempotents ; définir retries bornés, échecs observables et dead-letter/reprise.
5. Définir une outbox transactionnelle pour les faits critiques dont la perte est inacceptable.
6. Garder les transactions SQL partagées possibles dans le monolithe, mais orchestrées par un cas d'usage propriétaire ; prévoir compensations pour les effets externes.

**Sortie / critère de clôture**
- Contrats versionnés, tests de contrat et d'idempotence, aucun événement critique perdu silencieusement.

### Lot 5 — Cœur marketplace : Orders, Inventory, Delivery, Finance (P1)

**Ordre métier**
1. Orders : commande globale, lignes et sous-commandes coopératives, cycle de vie.
2. Inventory : disponibilité, réservations, mouvements, ajustements et concurrence.
3. Delivery : expéditions, transporteurs, tarifs, suivi, preuve et documents.
4. Finance : facture unique par commande, paiements, remboursements, commissions et reversements.
5. Communication : notifications et e-mails déclenchés à partir de faits métier confirmés.

**Exigences déjà validées à préserver**
- Une commande multi-coopératives possède un voyant de disponibilité par sous-commande coopérative ; aucun voyant global imposé au niveau de la commande principale dans ce cas.
- Une commande mono-coopérative possède un voyant global et un voyant par ligne de commande. Vert = disponible, ambre = disponibilité limitée, rouge = indisponible.
- Chaque commande génère une facture unique liée à cette commande ; une facture validée est immuable. Une annulation conserve la facture dans l'historique avec son statut. Une nouvelle commande génère une nouvelle facture.
- Les reversements du transporteur vers MAHLINE suivent des périodes de 7 jours ancrées au premier jour du mois (7, 14, 21 et 28), selon les encaissements depuis le transfert précédent.
- Les règles de transport national et international doivent être spécifiées avant automatisation ; conserver les exigences documentaires et réglementaires par destination.

**Fichiers/zones**
- `app/Domains/Orders/**` et migration marketplace `2026_11_18_000003_create_marketplace_orders_tables.php`.
- Spécification Inventory `docs/domains/marketplace/INVENTORY_V1_SPECIFICATION.md` (si toujours présente et à jour).
- Nouveaux domaines Inventory, Delivery et Finance uniquement après audit et validation des contrats.
- Tests de concurrence, multi-coopératives, annulation, stock insuffisant, factures immuables et reversements.

**Actions**
1. Auditer le code Orders déjà présent et comparer les tables, services, routes, policies et tests à la spécification validée.
2. Décider si l'inventaire et les réservations sont atomiques avec la création/confirmation de commande ; définir le comportement en cas d'échec partiel.
3. Écrire les contrats Orders ↔ Inventory, Orders ↔ Delivery, Orders ↔ Finance et événements versionnés.
4. Spécifier statuts, transitions, idempotence, verrouillage et compensations.
5. Construire les domaines dans cet ordre, avec tests de bout en bout avant communication client.

**Sortie / critère de clôture**
- Scénarios mono/multi-coopératives couverts, intégrité de stock, cycle de commande traçable, facture immuable et calculs financiers vérifiés.

### Lot 6 — Tests d'architecture, CI/CD et qualité (P1)

**Constats**
- Aucun fichier sous `.github/workflows/` n'était présent dans l'arbre de référence.
- Aucun test explicitement identifié comme test d'architecture par son nom n'a été repéré lors de l'inventaire initial.
- La présence d'une CI externe n'a pas été vérifiée ; ne pas conclure qu'aucune CI n'existe.

**Fichiers cibles**
- `composer.json`, `phpunit.xml`, configuration Pint/PHPStan/Psalm si présente.
- `tests/Architecture/**` (à créer) et suites Unit/Feature/Framework existantes.
- `.github/workflows/**` (à créer après choix des runners et secrets).
- `docs/architecture/decisions/ADR-018-automated-architecture-governance.md` et ADR-020.

**Actions**
1. Ajouter des contrôles automatiques pour imports inter-domaines, cycles, ownership de migrations/tables, dépendances Core/Shared, contrats et namespaces PSR-4.
2. Démarrer en mode rapport sur les violations historiques, sans permettre de nouvelles violations.
3. Définir une liste d'exceptions avec propriétaire et date d'expiration.
4. Rendre les contrôles bloquants après traitement des faux positifs et validation de la baseline.
5. Pipeline : dépendances verrouillées, Pint, analyse statique retenue, tests Unit/Feature/Architecture, tests MySQL, contrôles sécurité pertinents, construction artefact.
6. Environnements et promotions selon ADR-016/020 : approbation humaine explicite aux étapes critiques, aucun contournement d'un contrôle obligatoire.

**Sortie / critère de clôture**
- Pipeline reproductible, résultats attachés au commit, échec bloquant sur violation nouvelle ou test obligatoire en échec.

### Lot 7 — Exploitation et résilience (P2)

**Fichiers/zones**
- configuration logging, queue, cache, stockage, sauvegardes et environnements.
- runbooks dans `docs/operations/**`.
- ADR-014 (observabilité), ADR-015 (backup/DR), ADR-016 (environnements).

**Actions**
1. Propager `correlation_id` et `trace_id` ; séparer logs techniques, audit métier, événements sécurité et métriques.
2. Vérifier que les logs ne contiennent ni mots de passe, tokens, OTP, données sensibles ou payloads inutiles.
3. Définir sauvegarde DB + fichiers cohérente, RPO/RTO, chiffrement, rétention et contrôle d'accès.
4. Tester une restauration complète et documenter les procédures d'incident et rollback.
5. Vérifier la séparation des environnements, secrets, permissions et accès de production.

**Sortie / critère de clôture**
- Runbooks testés, restauration prouvée, objectifs RPO/RTO validés, traçabilité opérationnelle.

### Lot 8 — Refactor structurel progressif (P2)

**Constat**
Les domaines existants utilisent majoritairement une structure historique `Http/Models/Policies/Repositories/Services`, tandis que la cible est une organisation hexagonale pragmatique. Ce décalage ne justifie pas un déplacement massif.

**Actions**
- À chaque évolution, déplacer uniquement les composants concernés vers `Domain`, `Application`, `Infrastructure`, `Presentation`, `Database` et `Tests` si le bénéfice est réel.
- Garder les contrôleurs minces, les cas d'usage explicites, les contrats publics séparés des implémentations.
- Garder `BaseService` vide et éviter de créer des classes/dossiers sans responsabilité concrète.
- Évaluer `Shared/DTOs/AuditEntryDTO.php) : déterminer si le contrat appartient au domaine Audit et exposer un contrat public ; ne pas déplacer avant analyse des consommateurs.
- Vérifier `CoreServiceProvider` et `DomainServiceProvider` non enregistrés dans `bootstrap/providers.php` ; ne pas les supprimer sans prouver leur inutilité.

**Sortie / critère de clôture**
- Refactor par petits lots, mêmes comportements, tests de régression, aucun cycle ajouté.

## 4. Matrice fichier par fichier — première passe

| Fichier / motif | Action prévue | Priorité | Condition préalable |
|---|---|---:|---|
| `docs/framework/ADR.md` | Conserver comme archive historique et clarifier son statut | P0 | Retrouver les décisions V1 001–008 |
| `docs/architecture/decisions/ADR-001..008` | Reconstituer depuis sources approuvées et ajouter les fiches absentes | P0 | Historique/source probante |
| `docs/architecture/decisions/README.md` | Créer l'index canonique et règles de numérotation | P0 | Correspondance ADR validée |
| `docs/architecture/DOMAIN_ROADMAP.md` | Maintenir inventaire, états, liens et journal | P0 | Mise à jour à chaque lot |
| `docs/architecture/MAHLINE_V1_REPOSITORY_AUDIT.md` | Marquer photographie historique puis remplacer après audit complet | P0 | Nouvel inventaire vérifié |
| `docs/framework/ARCHITECTURE.md` | Aligner le modèle de couches et les flux sur ADR V1 | P1 | Lots 0–1 |
| `docs/framework/DOMAIN_BOUNDARIES.md` | Remplacer l'ancienne carte Sales/Catalog par la carte validée | P1 | Responsabilités Orders/Inventory/Delivery/Finance confirmées |
| `composer.json` / `composer.lock` | Vérifier autoload PSR-4 et cohérence des dépendances | P0 | Inventaire composer complet |
| `bootstrap/providers.php` + providers | Vérifier l'assemblage et les bindings | P1 | Dépendances et chargement prouvés |
| `routes/web.php` / `routes/api.php` / `bootstrap/app.php` | Vérifier middleware, exposition et attribution domaine | P1 | Inventaire routes et contrôleurs |
| `app/Core/**` / `app/Shared/**` | Classer les composants transverses et réduire le métier partagé | P1 | Matrice des usages |
| `app/Domains/**` | Corriger dépendances, ownership et frontières fichier par fichier | P0/P1 | Matrice exhaustive et contrat cible |
| `database/migrations/**` | Cartographier tables/FK/triggers ; corrections par nouvelles migrations seulement | P0 | Registre de propriété |
| `tests/**` | Ajouter tests architecture, sécurité, contrat et intégration | P0/P1 | Règles testables définies |
| `.github/workflows/**` | Ajouter CI conforme ADR-018/020 | P1 | Choix runners, secrets et commandes validées |
| `docs/operations/**` | Créer runbooks et procédures de restauration/déploiement | P2 | RPO/RTO et environnements arbitrés |

## 5. Ordre d'exécution recommandé

1. Lot 0 — référentiel documentaire et ADR.
2. Lot 1 — inventaire exhaustif du code et dépendances.
3. Lot 2 — propriété des données et migrations.
4. Lot 3 — autorisations et frontières de sécurité.
5. Lot 4 — contrats, événements et transactions.
6. Lot 5 — terminer Orders puis spécifier/implémenter Inventory, Delivery et Finance.
7. Lot 6 — tests d'architecture et CI/CD, avec baseline puis blocage.
8. Lot 7 — exploitation et résilience.
9. Lot 8 — refactor structurel ciblé au fil des lots précédents.

Les lots peuvent se chevaucher pour la documentation et les tests, mais aucun changement structurel ne doit précéder l'inventaire et l'accord sur le contrat cible.

## 6. Statut initial et limites

- **Fait :** plan rédigé ; inventaire Git de référence et principaux documents consultés.
- **Fait :** correction documentaire de l'inventaire Orders dans la feuille de route.
- **À faire :** audit exhaustif des imports, relations, événements, jobs, migrations et autorisations.
- **À faire :** retrouver les ADR-001 à ADR-008 d'origine et régler la collision documentaire.
- **Non exécuté :** aucun test PHP/PHPUnit/Pint, aucun test MySQL, aucun pipeline.
- **Non modifié :** code métier, migrations, configuration, routes et tests applicatifs.
- **Non fait :** merge vers `main`.

## 7. Journal

### 19 novembre 2026
- Création du plan concret de correction transversal.
- Correction du registre de domaines : Orders est présent.
- Référence de travail : `feature/Marketplace` à `c5981e490f3f15f8f1672b589a1c962a1c51abc2`.


### Mise à jour du 19 novembre 2026 — architecture hybride
- ADR-021 accepté : monolithe modulaire hybride, contrats publics synchrones, événements après commit et Outbox pour événements critiques.
- `Countries` intégré dans `Localization` ; aucune migration historique modifiée.
- Voir `DOMAIN_DEPENDENCY_AUDIT.md` pour les relations, les écarts et la matrice de correction.
- Les changements applicatifs réalisés sont limités à l'intégration de Country ; le découplage des autres domaines reste progressif et nécessite tests ciblés puis suite complète.


### Mise à jour — 19 novembre 2026 : premier lot de contrats appliqué
- **Intégré :** contrat public `AuditWriter` avec vérification d'intégrité ; AuditService reste l'implémentation interne.
- **Intégré :** contrat `CheckoutCatalog` et DTO immuable ; Orders ne dépend plus des modèles Eloquent Product/ProductVariant.
- **Intégré :** bindings dans AppServiceProvider ; aucune migration existante modifiée.
- **À faire :** contrats MediaOwner, contrats Address, ownership des pivots, policies/providers locaux, audit exhaustif FK/cascades/triggers, tests d'architecture et CI.
- **Non exécuté :** PHPUnit/Pint/tests MySQL. Le lot est intégré statiquement mais reste à valider dans l'environnement local.
- Référence de code après intégration : `4c2d4fecc288b37b164f3b9b941fe5caadc278d9`.


### Mise à jour — policies propriétaires intégrées
- **Intégré :** AddressPolicy → Localization ; CooperativePolicy → Cooperatives.
- **Intégré :** providers de domaine et enregistrement dans `bootstrap/providers.php`, sans changement de noms de Gates ni des règles métier.
- **Intégré :** tests de policies déplacés et namespaces mis à jour.
- **À faire :** déplacer les autres enregistrements de policies vers leurs domaines (Audit, Catalog, Categories, Media, Products) après audit de tous les usages ; ajouter les tests d'architecture qui empêchent une régression.
- **Non exécuté :** PHPUnit/Pint.


### Mise à jour — enregistrement des policies par domaine
- **Intégré :** providers propriétaires pour Audit, Catalog, Categories, Cooperatives, Localization, Media et Products.
- **Intégré :** Identity ne centralise plus l'enregistrement des policies des ressources des autres domaines.
- **À valider :** tests d'autorisation, boot providers et tests d'architecture en local ; aucun test n'a été exécuté dans cette session.


### Mise à jour — contrat d'adresse Cooperatives
- **Intégré :** contrat public Localization `CooperativeAddressManager`, implémenté par AddressService.
- **Intégré :** CooperativeService ne dépend plus directement du service concret AddressService.
- **À valider :** scénarios transactionnels et permissions des adresses en tests locaux.


### Référence technique actualisée
- Dernier HEAD du code intégré : `4c2d4fecc288b37b164f3b9b941fe5caadc278d9` sur `feature/Marketplace`.
- Contrats intégrés : AuditWriter, CheckoutCatalog, CooperativeAddressManager ; providers d'autorisation locaux actifs par déclaration bootstrap.
- Tests non exécutés : valider ce snapshot dans l'environnement local avant de poursuivre les lots restants.
