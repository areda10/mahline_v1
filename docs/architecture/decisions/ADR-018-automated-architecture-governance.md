# ADR-018 — Gouvernance automatisée de l'architecture et des frontières entre domaines

- **Statut :** ACCEPTED
- **Date :** 19 novembre 2026
- **Branche de référence :** `feature/Marketplace`
- **Portée :** architecture cible MAHLINE V7.0
- **Décision :** Option C — gouvernance architecturale automatisée

## Contexte

Les ADR-009 et ADR-017 établissent des frontières strictes entre domaines, des contrats publics et des règles de compatibilité. Une revue manuelle seule ne permet pas de prévenir durablement les régressions introduites par de nouveaux imports, dépendances, migrations ou changements de contrats.

## Décision

Mettre en place une gouvernance architecturale automatisée, progressive et obligatoire dans la chaîne d'intégration continue, avant toute promotion vers staging puis production.

Les contrôles devront couvrir au minimum :

1. **Frontières d'importation :** interdire aux domaines consommateurs d'importer les Models, Repositories, Actions/Services internes, contrôleurs, composants Infrastructure, migrations et détails de persistance d'un autre domaine.
2. **Dépendances autorisées :** vérifier que chaque dépendance inter-domaines est déclarée, orientée, justifiée et passe par un contrat public ou un événement autorisé.
3. **Cycles :** détecter les dépendances circulaires entre domaines et les signaler comme violations.
4. **Propriété des données et migrations :** vérifier que les migrations métier sont détenues par leur domaine propriétaire et qu'aucun domaine ne modifie les tables internes d'un autre domaine.
5. **Contrats publics :** contrôler les signatures, schémas, versions et règles de compatibilité définis dans l'ADR-017.
6. **Événements et messages :** contrôler leur version, leur structure publique et les règles de compatibilité applicables.
7. **Exceptions :** toute dérogation doit être explicite, limitée, motivée, attribuée à un responsable et documentée ; aucune exception implicite ou globale n'est admise.
8. **Rapports :** chaque échec doit identifier la règle violée, le domaine source, le domaine cible et le fichier ou symbole concerné afin de faciliter la correction.

## Intégration et gouvernance

- Les contrôles sont exécutés automatiquement dans la CI sur les changements concernés.
- Un échec bloquant empêche la validation de l'intégration et la promotion de la version.
- Les règles sont versionnées avec le dépôt et leurs exceptions sont réévaluées périodiquement.
- Les contrôles doivent distinguer les violations bloquantes des avertissements de migration, sans transformer les avertissements en autorisations implicites.
- L'outillage reste à choisir après inventaire du code, des conventions et des possibilités de la CI existante.
- Les tests architecturaux complètent les tests unitaires, d'intégration, de sécurité et de contrats ; ils ne les remplacent pas.

## Mise en œuvre différée

Avant d'activer le blocage général, réaliser un état initial des dépendances et des violations existantes. Classer les écarts, définir les exceptions temporaires et établir une trajectoire de correction. Le contrôle final devra empêcher l'introduction de nouvelles violations, même pendant la résorption de la dette historique.

Aucun framework, package, outil CI, seuil chiffré ou format de rapport n'est imposé par cette décision.

## Conséquences

- Les frontières architecturales deviennent vérifiables et résistantes aux régressions.
- Les violations sont détectées tôt, avec une localisation exploitable.
- Le dépôt doit maintenir un registre fiable des dépendances et exceptions.
- Un audit initial est nécessaire avant de rendre tous les contrôles bloquants.
- La gouvernance s'applique au monolithe modulaire actuel et prépare une extraction ultérieure de domaines ou workers.

## Hors périmètre

Cette décision ne modifie aucun code applicatif, migration, configuration, pipeline CI/CD ou test existant. Elle ne signifie pas que le dépôt actuel est déjà conforme.

## Références

- [ADR-009 — Contrôle des dépendances entre domaines](ADR-009-domain-dependencies.md)
- [ADR-017 — Versionnement et compatibilité des contrats inter-domaines](ADR-017-contract-versioning-and-compatibility.md)
