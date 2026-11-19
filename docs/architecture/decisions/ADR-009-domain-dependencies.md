# ADR-009 — Contrôle des dépendances entre domaines

- **Statut :** ACCEPTÉ
- **Date de décision :** 19 novembre 2026
- **Projet :** MAHLINE V7.0
- **Branche de référence :** `feature/Marketplace`
- **Décision :** option C — dépendances contrôlées par contrats publics
- **Portée :** architecture cible ; aucune modification applicative n'est autorisée par ce document à elle seule.

## 1. Contexte

MAHLINE adopte un monolithe modulaire initial, conçu pour permettre l'extraction ultérieure de domaines vers des applications ou services indépendants. Les domaines doivent donc pouvoir collaborer sans exposer leurs détails internes ni créer de dépendances structurelles incontrôlées.

L'architecture hexagonale pragmatique retenue pour chaque domaine distingue notamment Domain, Application, Infrastructure et Presentation. Cette séparation interne ne suffit pas : les frontières entre domaines doivent également être vérifiables.

## 2. Décision

Un domaine peut dépendre d'un autre domaine uniquement à travers la surface publique explicitement exposée par ce dernier : contrats, interfaces et messages d'intégration documentés.

Les dépendances directes vers les composants internes d'un autre domaine sont interdites.

### 2.1 Dépendances autorisées

Un domaine consommateur peut utiliser :
- les contrats publics explicitement exposés par le domaine propriétaire ;
- les DTO ou objets de message faisant partie de ces contrats publics ;
- les événements métier publics auxquels il est abonné ;
- les identifiants et types techniques transversaux définis dans le Shared Kernel strict.

Un contrat public doit exprimer une capacité métier ou une information nécessaire au consommateur, sans révéler l'implémentation qui la réalise.

### 2.2 Dépendances interdites

Un domaine ne doit pas importer ni manipuler directement les éléments internes d'un autre domaine, notamment :
- ses modèles Eloquent et relations ORM ;
- ses repositories et requêtes de persistance ;
- ses migrations, tables ou détails de schéma ;
- ses Actions, Services ou classes d'orchestration internes ;
- ses contrôleurs, Form Requests, Resources ou autres composants de présentation ;
- ses classes Infrastructure ou implémentations concrètes.

Il est également interdit de contourner un contrat en accédant directement à la base de données d'un autre domaine, même si les domaines partagent initialement la même base et la même connexion.

### 2.3 Dépendances déclarées et sens de dépendance

- Les dépendances inter-domaines autorisées doivent être inscrites dans un registre explicite.
- Chaque dépendance doit indiquer le domaine consommateur, le domaine fournisseur, le contrat utilisé, le motif métier et les flux concernés.
- Les dépendances doivent être orientées et justifiées ; les dépendances circulaires sont interdites.
- Un domaine fournisseur conserve la maîtrise de ses contrats et de leur évolution.
- Toute nouvelle dépendance ou exception doit être examinée et approuvée avant implémentation, puis inscrite au registre.

### 2.4 Appels synchrones et événements

Cette décision complète la décision 5 :
- un besoin de résultat immédiat passe par un contrat public synchrone ;
- la notification d'un fait métier passe par un événement public ;
- un événement ne doit pas exposer les modèles internes ni devenir un moyen détourné d'appeler les méthodes privées d'un autre domaine ;
- les événements publiés dans un contexte transactionnel doivent respecter la décision 6 : publication fiable après commit.

## 3. Contrôle automatisé

Les frontières ne reposent pas uniquement sur la discipline des développeurs.

Avant la validation de l'architecture cible, il faudra mettre en place des contrôles automatisés qui vérifient au minimum :
1. les imports entre domaines et leur conformité au registre des dépendances autorisées ;
2. l'absence d'accès aux namespaces internes des domaines fournisseurs ;
3. l'absence de dépendances circulaires ;
4. l'absence d'accès inter-domaines aux modèles, repositories et détails de persistance ;
5. le respect des surfaces publiques documentées.

Les contrôles devront être exécutés dans la suite de tests/CI et échouer en cas de violation. La technologie et l'outil précis seront choisis après audit de la structure réelle du dépôt ; aucune bibliothèque ou configuration n'est présumée déjà installée.

## 4. Registre des dépendances

Un registre central devra être maintenu dans la documentation d'architecture. Pour chaque relation, il contiendra :
- domaine consommateur ;
- domaine fournisseur ;
- contrat ou événement public utilisé ;
- sens et justification de la dépendance ;
- nature de l'échange (synchrone, événementiel ou les deux) ;
- données échangées, limitées au nécessaire ;
- propriétaire et version du contrat ;
- tests architecturaux couvrant la relation ;
- éventuelle exception approuvée et sa justification.

Une dépendance absente du registre est considérée comme non autorisée jusqu'à son examen.

## 5. Conséquences

### Bénéfices attendus
- réduction du couplage entre domaines ;
- protection de la propriété des données et de la persistance ;
- possibilité de remplacer l'implémentation interne d'un domaine sans casser ses consommateurs, tant que ses contrats restent compatibles ;
- meilleure visibilité sur les dépendances et leurs cycles ;
- préparation concrète à l'extraction et au déploiement indépendant des domaines.

### Coûts et contraintes
- nécessité de concevoir et maintenir des contrats publics ;
- ajout de tests architecturaux et d'un registre de dépendances ;
- évolution des contrats à gérer explicitement ;
- certaines opérations nécessiteront une orchestration plutôt qu'un accès direct aux modèles.

## 6. Règles de mise en œuvre

1. Ne pas déplacer ni réécrire de code sur la seule base de cet ADR.
2. Auditer d'abord les imports, appels, requêtes et accès aux données inter-domaines existants.
3. Produire une cartographie des dépendances réelles et la comparer au registre cible.
4. Proposer les corrections fichier par fichier avant toute modification.
5. Définir les namespaces publics et internes de chaque domaine à partir de l'architecture réellement retenue.
6. Ajouter les tests architecturaux et les intégrer à la CI.
7. Ne déclarer cette décision techniquement appliquée qu'après correction des écarts et exécution réussie des contrôles.

## 7. Décisions connexes

Cet ADR s'applique conjointement avec les décisions architecturales validées pendant le cadrage du 19 novembre 2026 :

1. monolithe modulaire initial, évolutif vers des domaines déployables indépendamment ;
2. relations inter-domaines selon l'approche hybride, avec références par identifiants par défaut et exceptions physiques documentées ;
3. migrations métier détenues par chaque domaine, migrations du framework et réellement transversales dans `database/migrations` ;
4. redéfinition des frontières métier à partir d'une cartographie complète ;
5. communication hybride par contrats synchrones et événements métier ;
6. transactions SQL communes lorsque l'atomicité l'exige, événements après commit et compensations pour les opérations externes ;
7. Shared Kernel strict, limité aux éléments techniques réellement transversaux ;
8. architecture hexagonale pragmatique au sein de chaque domaine ;
9. contrôle des dépendances par contrats publics, registre explicite et tests automatisés.

## 8. État

**Décision d'architecture acceptée ; mise en œuvre non auditée et non déclarée.**

Aucun code applicatif, migration, test ou configuration CI n'est modifié par cet ADR. L'étape suivante est l'inventaire des dépendances réellement présentes dans le dépôt, après la poursuite du cadrage des frontières métier.
