# ADR-014 — Observabilité technique et traçabilité transversale

- **Statut :** ACCEPTÉ
- **Date :** 19 novembre 2026
- **Décision :** Option C — observabilité hybride avec traçabilité transversale
- **Portée :** architecture cible MAHLINE V7.0
- **Dépendances :** ADR-009 (dépendances inter-domaines), ADR-012 (événements métier), ADR-013 (traitements asynchrones)

## Contexte

Les workflows MAHLINE peuvent traverser plusieurs domaines et combiner des requêtes HTTP, des appels synchrones, des événements et des jobs asynchrones. Une journalisation isolée par composant ne suffit pas à reconstituer le parcours d'une opération. À l'inverse, centraliser indistinctement toutes les données métier, de sécurité et techniques créerait des risques de confidentialité et brouillerait les responsabilités.

## Décision

Adopter une observabilité hybride : infrastructure technique commune et corrélation transversale, tandis que chaque domaine conserve la responsabilité de ses événements métier et de ses données de traçabilité métier.

### 1. Séparation des catégories de traces

Distinguer explicitement :

- **Logs techniques :** diagnostics d'exécution, erreurs, performances, disponibilité, infrastructure et traitements.
- **Audit métier :** historique des actions et changements métier, avec auteur, ressource, contexte et résultat selon les règles du domaine propriétaire et du domaine Audit.
- **Événements de sécurité :** authentifications, refus d'accès, changements sensibles, incidents et opérations de sécurité, avec accès et conservation strictement contrôlés.
- **Métriques et traces distribuées :** mesures agrégées et parcours techniques entre composants, sans devenir un historique métier alternatif.

Ces catégories peuvent être corrélées, mais ne doivent pas être fusionnées dans un journal unique ni se substituer les unes aux autres.

### 2. Infrastructure et propriété

- Les logs techniques et métriques peuvent être collectés dans une infrastructure commune.
- Chaque domaine reste propriétaire de la définition, de l'émission et de l'interprétation de ses événements métier.
- Le domaine Audit conserve son périmètre de traçabilité métier selon ses règles d'accès ; la centralisation technique ne donne pas automatiquement accès aux journaux d'audit.
- Les événements de sécurité restent soumis aux règles spécifiques de confidentialité, d'accès et de conservation d'Identity et des domaines concernés.
- Les contrats et frontières inter-domaines de l'ADR-009 restent applicables.

### 3. Corrélation et propagation

- Un `correlation_id` stable identifie un workflow métier cohérent à travers ses étapes.
- Un `trace_id` identifie une trace technique distribuée ; des identifiants de span permettent de distinguer les appels et traitements.
- Les identifiants de corrélation et de trace sont propagés dans les appels synchrones, événements et jobs asynchrones lorsque le contexte est disponible.
- Les liens entre un événement métier, un job et une trace technique utilisent des identifiants sûrs et minimaux ; les payloads métier complets ne sont pas recopiés dans les logs techniques.
- Les traitements planifiés ou déclenchés sans requête initiale créent un contexte de corrélation explicite.
- L'absence d'un identifiant propagé ne doit pas empêcher le traitement métier ; une nouvelle trace peut être créée et reliée au contexte disponible.

### 4. Sécurité et minimisation des données

- Les mots de passe, tokens d'accès, secrets, codes OTP, clés privées et données d'authentification ne sont jamais écrits dans les logs.
- Les données personnelles et financières ne sont enregistrées que si elles sont strictement nécessaires au diagnostic, avec minimisation, masquage ou pseudonymisation.
- Les corps complets de requêtes/réponses, en-têtes d'authentification et payloads d'événements ne sont pas journalisés par défaut.
- Les exceptions et traces de pile doivent être filtrées afin de ne pas exposer des secrets ou données sensibles.
- Les accès aux outils d'observabilité, exports et recherches sont authentifiés, autorisés et journalisés selon leur sensibilité.
- Les environnements et niveaux de journalisation ne doivent pas affaiblir les protections en production.

### 5. Métriques et supervision

La stratégie de supervision doit couvrir, selon la pertinence de chaque composant :

- disponibilité et santé des applications et workers ;
- latence, volumes, taux d'erreur et temps de réponse ;
- profondeur et ancienneté des files, durées des jobs, retries et échecs définitifs (ADR-013) ;
- consommation de ressources et saturation ;
- délais de traitement des événements et état de l'Outbox lorsque présent (ADR-012) ;
- indicateurs de sécurité et alertes opérationnelles sans divulguer les données protégées.

Les métriques doivent privilégier les agrégats et éviter les labels à forte cardinalité contenant des identifiants personnels ou des données sensibles.

### 6. Conservation et exploitation

- Les durées de conservation sont définies par catégorie de données, finalité, criticité, exigences légales et sécurité ; aucune durée universelle n'est fixée par cet ADR.
- Les règles d'archivage, purge, accès, export et investigation sont documentées.
- Les erreurs doivent pouvoir être reliées à leur contexte technique sans conserver indéfiniment des payloads sensibles.
- Les alertes et procédures d'incident identifient un responsable et une action attendue ; la journalisation seule ne constitue pas une réponse à incident.

### 7. Évolutivité

- Les conventions de logs, métriques, identifiants de corrélation et traces restent indépendantes d'un fournisseur d'observabilité particulier.
- L'architecture prépare le tracing distribué si des workers ou domaines sont extraits, sans imposer immédiatement une plateforme ou un protocole précis.
- Les domaines peuvent enrichir leurs traces avec des attributs métier non sensibles et documentés, sans exposer leurs modèles internes.

## Conséquences

### Positives

- Permet de reconstituer les workflows transversaux et diagnostiquer les traitements asynchrones.
- Préserve la distinction entre diagnostic technique, audit métier et sécurité.
- Réduit le risque de fuite de secrets ou de données personnelles dans les journaux.
- Prépare l'extraction future de workers et domaines.

### Contraintes

- Nécessite des conventions partagées, une propagation fiable des identifiants et des règles de masquage.
- Exige de définir les propriétaires, accès, rétentions et alertes par catégorie.
- La centralisation technique doit être dimensionnée et sécurisée ; elle ne doit pas devenir un accès transversal illimité aux données métier.

## Hors périmètre de cette décision

Cet ADR ne choisit aucun fournisseur, produit, protocole ou backend précis. Il ne fixe pas de durées de conservation, de seuils d'alerte ou de tableaux de bord définitifs. Il ne crée ni infrastructure, ni configuration, ni table, ni migration, ni code applicatif. Il ne modifie pas les règles métier d'audit ou de sécurité déjà validées.

## Vérifications et travaux restant à réaliser

1. Inventorier les logs, événements d'audit, événements de sécurité, métriques et outils existants.
2. Définir les conventions de structure, niveaux, champs autorisés et règles de masquage.
3. Définir la propagation de `correlation_id`, `trace_id` et des spans entre HTTP, contrats synchrones, événements et jobs.
4. Cartographier les accès, responsables et durées de conservation par catégorie.
5. Définir les métriques, seuils, alertes, procédures d'investigation et responsabilités.
6. Tester l'absence de secrets dans les logs et la corrélation d'un workflow traversant plusieurs domaines.
7. Vérifier que la centralisation technique ne contourne pas les permissions du domaine Audit ou des événements de sécurité.

## Références

- [ADR-009 — Contrôle des dépendances entre domaines](ADR-009-domain-dependencies.md)
- [ADR-012 — Fiabilité et cycle de vie des événements métier](ADR-012-domain-events-reliability.md)
- [ADR-013 — Traitements asynchrones et architecture des files d'attente](ADR-013-async-processing-and-queues.md)
- [Feuille de route centrale des domaines](../DOMAIN_ROADMAP.md)
