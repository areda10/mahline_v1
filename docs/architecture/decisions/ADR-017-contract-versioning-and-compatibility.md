# ADR-017 — Versionnement et compatibilité des contrats inter-domaines

- **Statut :** ACCEPTÉ
- **Date :** 19 novembre 2026
- **Décision :** Option C — versionnement raisonné avec compatibilité
- **Portée :** contrats publics synchrones, événements métier, API internes et messages de traitements asynchrones
- **Dépendances :** ADR-009 (frontières inter-domaines), ADR-012 (fiabilité des événements), ADR-013 (traitements asynchrones), ADR-016 (environnements et promotion)

## Contexte

Les domaines MAHLINE échangent des informations par des contrats publics. Une évolution d'un contrat fournisseur peut casser les consommateurs, notamment lorsque les versions applicatives ne sont pas déployées simultanément ou lorsque des messages asynchrones restent en attente. Un versionnement systématique de chaque détail ajoute une charge inutile ; une évolution sans règle de compatibilité expose la plateforme aux régressions.

## Décision

Adopter un versionnement raisonné des contrats publics, en privilégiant les évolutions rétrocompatibles et en imposant une nouvelle version lorsqu'une modification casse le contrat existant. Ne pas versionner artificiellement les composants internes qui ne constituent pas une surface publique.

### 1. Contrats concernés

Le cadre s'applique aux surfaces d'échange publiques entre domaines, notamment :
- interfaces et DTO publics utilisés dans les appels synchrones ;
- événements métier publics et leurs payloads ;
- messages et schémas persistés ou transportés par les traitements asynchrones lorsqu'ils sont consommés hors du domaine producteur ;
- API internes ou externes exposées comme contrats stables.

Les classes, méthodes, modèles et détails purement internes à un domaine restent sous le contrôle de ce domaine et ne reçoivent pas de version publique artificielle.

### 2. Évolutions rétrocompatibles

Privilégier les changements qui ne cassent pas les consommateurs existants, sous réserve de règles documentées :
- ajouter un champ optionnel dont l'absence conserve un sens défini ;
- ajouter une nouvelle capacité sans modifier le comportement contractuel existant ;
- élargir un enum ou un ensemble de valeurs uniquement si les consommateurs sont conçus pour traiter les valeurs inconnues de manière sûre ;
- conserver la signification, le type, les unités, la nullabilité et les invariants des champs existants.

Une modification apparemment additive n'est pas automatiquement rétrocompatible : l'ajout d'un champ obligatoire, le changement d'unité, de sémantique, de valeur par défaut ou d'ordre garanti peut constituer une rupture.

### 3. Changements incompatibles

Une modification incompatible exige une nouvelle version majeure ou une nouvelle révision explicitement distincte du contrat, selon la convention de versionnement retenue lors de la conception du catalogue de contrats.

Sont notamment considérés comme incompatibles :
- supprimer ou renommer un champ ou une opération ;
- changer un type, une unité, une nullabilité ou une sémantique ;
- rendre obligatoire un champ auparavant facultatif ;
- modifier les règles d'autorisation, d'idempotence, d'ordre ou de temporalité promises par le contrat ;
- changer la structure ou la signification d'un événement déjà consommé.

La nouvelle version doit pouvoir coexister avec l'ancienne pendant la période nécessaire à la migration des consommateurs et au traitement des messages encore en circulation.

### 4. Dépréciation et retrait

- Toute dépréciation est annoncée, documentée et associée à une version de remplacement.
- Le producteur identifie les consommateurs connus et évalue les impacts avant le retrait.
- Une période de coexistence est prévue selon le risque, la criticité et la durée de vie des traitements/messages.
- Le retrait n'intervient qu'après vérification que les consommateurs concernés ont migré et que les messages persistés de l'ancienne version ont été consommés, convertis ou explicitement traités.
- Les exceptions urgentes de sécurité ou de conformité suivent une procédure accélérée, documentée et contrôlée.

Aucune durée universelle de coexistence n'est fixée par cet ADR.

### 5. Événements et traitements asynchrones

- Les événements publics possèdent une identité stable et un schéma/version explicite.
- Les consommateurs vérifient la version reçue et appliquent une stratégie définie pour les versions non prises en charge : mise en quarantaine, échec observable ou traitement de compatibilité ; jamais d'interprétation silencieuse hasardeuse.
- Les jobs et messages susceptibles de survivre à un déploiement doivent rester lisibles pendant la période de coexistence prévue.
- Les changements de schéma d'un message tiennent compte des retries, doublons, retards, dead-letter et relectures.
- Les conversions entre versions sont explicites, testées et traçables ; elles ne doivent pas altérer silencieusement la signification métier.
- Les règles de publication après commit, d'idempotence et d'Outbox restent celles de l'ADR-012.

### 6. Tests de contrats

- Les contrats publics disposent de tests de conformité côté producteur et de tests de consommation côté consommateurs pertinents.
- La CI vérifie les changements de schéma et détecte les ruptures incompatibles connues.
- Les tests couvrent les champs absents, valeurs inconnues, versions supportées, erreurs de validation et cas de coexistence.
- Les tests de déploiement progressif vérifient qu'une ancienne version de consommateur peut coexister avec le producteur mis à jour, et inversement lorsque le scénario l'exige.
- Les contrats critiques disposent d'exemples de payloads valides et invalides, versionnés avec leur documentation.

### 7. Gouvernance

- Chaque contrat public possède un domaine producteur propriétaire, une finalité, une version, une liste de consommateurs connus et un responsable de son évolution.
- Toute évolution est examinée au regard de la compatibilité, de la sécurité, de la confidentialité et de l'impact sur les consommateurs.
- Les dépendances et contrats restent recensés conformément à l'ADR-009.
- La convention précise de numérotation, le format de schéma et les outils de validation seront choisis lors de l'inventaire et de la conception du catalogue ; cet ADR ne prescrit pas prématurément un outil.

## Conséquences

### Positives

- Réduit les régressions lors des évolutions inter-domaines.
- Permet les déploiements progressifs et la coexistence temporaire de versions.
- Protège les événements et jobs qui peuvent rester en attente après un déploiement.
- Évite de versionner inutilement les détails internes.
- Rend la dépréciation et le retrait des contrats prévisibles.

### Contraintes

- Exige un catalogue de contrats et de consommateurs maintenu à jour.
- Ajoute des tests de compatibilité et, parfois, des adaptateurs temporaires.
- Peut nécessiter de maintenir plusieurs versions pendant une période limitée.
- Demande une gouvernance explicite pour éviter la prolifération des versions.

## Hors périmètre de cette décision

Cet ADR ne définit pas encore la convention exacte (SemVer, numéro de schéma ou autre), le format technique des contrats, le registre automatisé, les outils de génération ou de validation, ni une durée fixe de dépréciation. Il ne modifie aucun contrat, événement, job, API, code, configuration ou pipeline existant.

## Vérifications et travaux restant à réaliser

1. Inventorier les contrats synchrones, événements, API et messages asynchrones réellement présents.
2. Identifier leurs producteurs, consommateurs, versions et dépendances.
3. Définir la convention de versionnement et les formats de schéma adaptés à chaque catégorie.
4. Créer un catalogue de contrats publics et un processus de revue des changements.
5. Ajouter des tests de compatibilité producteur/consommateur dans la CI.
6. Définir la coexistence, la conversion et le retrait des messages asynchrones historiques.
7. Vérifier les scénarios de déploiement progressif avec les environnements de l'ADR-016.
8. Documenter les procédures de dépréciation, d'exception et de retrait.

## Références

- [ADR-009 — Contrôle des dépendances entre domaines](ADR-009-domain-dependencies.md)
- [ADR-012 — Fiabilité et cycle de vie des événements métier](ADR-012-domain-events-reliability.md)
- [ADR-013 — Traitements asynchrones et architecture des files d'attente](ADR-013-async-processing-and-queues.md)
- [ADR-016 — Stratégie des environnements et promotion des versions](ADR-016-environments-and-release-promotion.md)
- [Feuille de route centrale des domaines](../DOMAIN_ROADMAP.md)
