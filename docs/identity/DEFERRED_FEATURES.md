# MAHLINE V7.0 — Registre des fonctionnalités reportées
## Domaine Identity

- **Branche de référence :** `feature/Identity`
- **Dernière mise à jour :** 10 novembre 2026
- **Règle :** les éléments ci-dessous restent au backlog, mais sont exclus du périmètre d'audit et d'implémentation courant jusqu'à une reprise explicite. Les décisions déjà validées restent applicables.

## Éléments reportés

| Identifiant | Fonctionnalité reportée | Motif / condition de reprise | État |
|---|---|---|---|
| ID01-03 | Vérification des mots de passe courants/compromis avec approche respectueuse de la confidentialité (k-anonymity ou équivalent). | Reprise ultérieure à la demande de l'utilisateur ; conserver les contrôles de robustesse déjà actifs. | Reporté |
| ID02-12 | Audit de compatibilité inter-algorithmes des hashes historiques (bcrypt ↔ Argon) et stratégie de migration progressive. | Explicitement exclu du périmètre courant par décision utilisateur du 9 novembre 2026. | Reporté |
| SESS-12 | Détection d'un pays inhabituel et challenge OTP e-mail complémentaire. | Audit de conception réalisé ; reprise après choix explicite du fournisseur GeoIP et validation du comportement en cas d'indisponibilité. Aucune implémentation applicative engagée. | Reporté |
| SESS-13 | Moteur OTP : six chiffres, durée cinq minutes, cinq tentatives, renvoi après 60 secondes, nouveau code invalidant l'ancien, stockage hashé, consommation atomique et journalisation sans code en clair. | Dépend du chantier SESS-12 ; reprise ultérieure à la demande de l'utilisateur. | Reporté |
| SESS-14 | Tests des faux positifs et avertissement qu'une IP/localisation n'est pas une preuve d'identité. | Dépend du chantier pays inhabituel/GeoIP SESS-12 ; reprise avec SESS-12. | Reporté |

## Règles de reprise

1. Ne pas démarrer ces tâches automatiquement lors de l'audit d'autres fonctions Identity.
2. À la reprise, relire les décisions et l'audit historique correspondants avant toute modification.
3. Ne pas modifier les règles déjà validées sans nouvelle décision explicite.
4. Après reprise, déplacer l'élément hors de ce registre uniquement lorsque son statut et ses preuves sont mis à jour dans le backlog principal.

## Historique

- **10 novembre 2026 :** création du registre consolidé à la demande de l'utilisateur. Regroupement des éléments ID01-03, ID02-12, SESS-12, SESS-13 et SESS-14 déjà reportés ou explicitement différés.
