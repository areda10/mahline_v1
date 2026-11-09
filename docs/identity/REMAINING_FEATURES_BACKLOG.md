# MAHLINE V7.0 — Backlog des fonctionnalités restantes
## Domaine Identity — plan d'implémentation après les décisions et commits initiaux

- **Version :** 2.4
- **Date de création :** 8 novembre 2026
- **Dernière mise à jour :** 9 novembre 2026
- **Branche de référence :** `feature/Identity`
- **Document de décisions associé :** [USER_MANAGEMENT_DECISIONS_AND_TRACKING.md](./USER_MANAGEMENT_DECISIONS_AND_TRACKING.md)
- **Statut :** backlog initial établi à partir des décisions validées et des constats d'audit ; tâches à revalider au fur et à mesure des tests et des nouveaux audits.

---

## 1. Règles de gestion du backlog

Ce fichier est le registre des fonctionnalités qui restent à développer, compléter ou valider après les commits initiaux du suivi Identity.

Il ne remplace pas le cahier des décisions. En cas de différence, les décisions validées dans `USER_MANAGEMENT_DECISIONS_AND_TRACKING.md` font foi.

### États

- [ ] **À auditer** — confirmer l'état exact dans le code avant de planifier une modification.
- [ ] **À faire** — fonctionnalité absente ou incomplète.
- [ ] **En cours** — modification commencée, pas encore validée.
- [ ] **À tester** — code présent, tests ciblés à exécuter.
- [ ] **Bloqué** — dépendance ou décision manquante.
- [x] **Validé** — uniquement après tests verts et preuve/commit enregistré.

Ne jamais déclarer une fonctionnalité terminée parce qu'un fichier ou une classe existe. Chaque ligne doit avoir une preuve de test et un commit de validation.

### Ordre de travail

1. Stabiliser les commits de politique et d'historique des mots de passe déjà ajoutés.
2. Finaliser ID-01/ID-02 et les contrôles de tous les parcours d'authentification.
3. Finaliser les sessions, tokens et alertes de sécurité.
4. Finaliser le profil, la gestion des utilisateurs et les workflows administratifs.
5. Finaliser les journaux, la confidentialité, les exports et la rétention.
6. Exécuter la suite Identity complète et réaliser un audit final de conformité.

---

## 2. Priorité P0 — Sécurité des mots de passe et authentification

### ID-01 — Politique de mot de passe renforcée

- [ ] **ID01-01** Auditer tous les points d'entrée qui créent ou modifient un mot de passe : création administrative, activation manuelle, invitation, activation, changement volontaire, changement forcé, reset et éventuels endpoints API.
- [ ] **ID01-02** Faire passer chaque parcours par un validateur commun, sans possibilité de contourner la politique.
- [ ] **ID01-03** Implémenter la vérification des mots de passe courants/compromis avec une approche respectueuse de la confidentialité (k-anonymity ou mécanisme équivalent).
- [ ] **ID01-04** Concevoir la liste locale de mots de passe refusés : format documenté, source, version, date, taille, empreinte SHA-256 et statut de validité.
- [ ] **ID01-05** Implémenter l'import sécurisé de la liste : provenance vérifiée, intégrité vérifiée, rejet des listes incomplètes/corrompues et conservation de la dernière version valide.
- [ ] **ID01-06** Permettre la mise à jour sans modification du code applicatif.
- [ ] **ID01-07** Implémenter les mises à jour automatiques contrôlées et le déclenchement manuel réservé à `super_admin_root`.
- [ ] **ID01-08** Rendre la fréquence configurable par `super_admin_root`, quotidienne par défaut.
- [ ] **ID01-09** Journaliser chaque tentative de mise à jour et son résultat ; alerter `super_admin_root` après trois échecs consécutifs ; remettre le compteur à zéro après succès.
- [ ] **ID01-10** Créer le tableau de bord root-only de la liste : version active, dernière/prochaine mise à jour, intégrité, indisponibilités, erreurs et historique.
- [ ] **ID01-11** Définir et tester le comportement de repli lorsque le service externe est indisponible : liste locale obligatoire, politique de complexité maintenue et reprise du contrôle externe.
- [ ] **ID01-12** Ajouter les tests de sécurité et de confidentialité prouvant que le mot de passe en clair n'est jamais envoyé au service externe ni journalisé.
- [ ] **ID01-13** Pour les comptes existants, contrôler le mot de passe fourni à la prochaine authentification réussie et déclencher un changement obligatoire si nécessaire.
- [ ] **ID01-14** Pendant le changement obligatoire, bloquer l'accès au dashboard et aux autres routes métier ; autoriser uniquement changement de mot de passe et déconnexion.
- [ ] **ID01-15** Envoyer l'e-mail informatif prévu lorsqu'un changement devient obligatoire, sans mot de passe ni lien de reset non sollicité.
- [ ] **ID01-16** Présenter à l'utilisateur les motifs de refus utiles sans révéler les détails internes de détection.
- [ ] **ID01-17** Compléter les tests de longueur, classes de caractères, Unicode, espaces, données personnelles, limites et messages de validation.
- [ ] **ID01-18** Exécuter et valider les tests existants de `PasswordPolicyTest` après le commit de correction des fixtures.
- [x] **ID01-19** Corriger les fixtures de `PasswordServiceTest` pour respecter ID-01 et remplacer le rejet erroné des espaces par un test d'acceptation (`Mahline Password13!`). Commit `8a31970f8d983df7926f3a13d696903edd0471b3` ; exécution locale du test ciblé encore à confirmer.

### ID-02 — Historique des mots de passe

- [x] **ID02-01** Exécuter la migration `2026_11_08_000001_create_user_password_histories_table.php` — exécutée localement avec succès le 8 novembre 2026 (Laravel : `229.90ms DONE`). La validation du schéma et des parcours reste couverte par les tests d'intégration.
- [ ] **ID02-02** Vérifier que l'archivage de l'ancien hash et le remplacement du mot de passe sont toujours dans une transaction atomique.
- [ ] **ID02-03** Vérifier le verrouillage concurrent de la ligne utilisateur pour empêcher deux changements simultanés de contourner l'historique.
- [ ] **ID02-04** Tester le refus du mot de passe courant et de chacun des cinq anciens mots de passe.
- [ ] **ID02-05** Tester la rotation : conservation de cinq anciens hashes et suppression du plus ancien lors de l'ajout du sixième.
- [ ] **ID02-06** Tester qu'un échec de validation, une exception ou un rollback ne crée aucune entrée d'historique.
- [ ] **ID02-07** Vérifier l'application commune aux changements volontaires/forcés, reset, invitation et activation.
- [ ] **ID02-08** Vérifier les cas de compte sans hash courant (si techniquement possibles) et la compatibilité avec les comptes historiques.
- [ ] **ID02-09** Vérifier la politique de suppression des historiques lors de la suppression physique exceptionnelle d'un compte, en cohérence avec les obligations de conservation et la FK choisie.
- [ ] **ID02-10** Compléter les tests HTTP/intégration et concurrence ; les tests ciblés `php artisan test --filter=PasswordHistory` sont verts (confirmation utilisateur du 8 novembre 2026).

### Authentification — protection des réponses et des tentatives

- [ ] **AUTH-01** Uniformiser les réponses publiques de connexion pour compte inexistant, mot de passe incorrect, compte inactif/suspendu, e-mail non vérifié et verrouillage ; conserver les détails uniquement dans les journaux internes.
- [ ] **AUTH-02** Compter les tentatives échouées de façon cohérente pour les comptes connus/inconnus et les états de compte, sans créer de fuite d'information.
- [ ] **AUTH-03** Renforcer la limitation par compte et par IP contre les attaques distribuées et les contournements via IP partagée.
- [ ] **AUTH-04** Vérifier la remise à zéro des compteurs après authentification réussie sans effacer abusivement les compteurs IP communs.
- [ ] **AUTH-05** Tester le blocage après cinq échecs pendant quinze minutes, son expiration automatique et l'absence de blocage permanent.
- [ ] **AUTH-06** Vérifier que seul `super_admin_root` peut lever manuellement un blocage lorsque cette action est autorisée.
- [ ] **AUTH-07** Vérifier les réponses, statuts HTTP et messages sur toutes les routes web/API.

---

## 3. Priorité P1 — Sessions, appareils et tokens API

### Sessions multi-appareils et expiration

- [ ] **SESS-01** Implémenter et tester les délais d'inactivité serveur par rôle : root 15 min, super_admin 30 min, cooperative_admin 30 min, user 60 min.
- [ ] **SESS-02** Ajouter l'avertissement côté interface deux minutes avant expiration et l'action de prolongation.
- [ ] **SESS-03** Vérifier que l'expiration d'une session ferme uniquement la session courante et conserve les autres sessions valides.
- [ ] **SESS-04** Implémenter le comportement de cookie demandé : non persistant pour root/super_admin ; persistant pour cooperative_admin/user jusqu'à expiration d'inactivité ou révocation.
- [ ] **SESS-05** Ajouter la déconnexion globale personnelle avec confirmation, révocation de toutes les sessions et tokens propres, et journalisation.
- [ ] **SESS-06** Conserver plusieurs sessions web actives simultanément ; une nouvelle connexion ne doit pas révoquer les anciennes.
- [ ] **SESS-07** Tester la révocation individuelle, globale, par changement de mot de passe, changement de rôle, changement d'e-mail et verrouillage d'urgence.
- [ ] **SESS-08** Vérifier que les anciennes sessions et anciens tokens ne sont pas restaurés après restauration d'un compte archivé/supprimé.

### Détection des appareils et alertes de connexion

- [ ] **SESS-09** Remplacer le hook d'authentification nul par une intégration effective du workflow de détection de nouvel appareil.
- [ ] **SESS-10** Envoyer une alerte e-mail lors d'une connexion depuis un nouvel appareil avec date/heure, navigateur, appareil et localisation approximative.
- [ ] **SESS-11** Permettre à l'utilisateur de consulter et révoquer ses propres sessions ; interdire la révocation des sessions d'un autre utilisateur sauf rôle autorisé.
- [ ] **SESS-12** Implémenter la détection d'un pays inhabituel et le challenge OTP e-mail complémentaire.
- [ ] **SESS-13** OTP : six chiffres, durée cinq minutes, maximum cinq tentatives, renvoi après 60 secondes, nouveau code invalidant l'ancien, stockage hashé, consommation atomique et journalisation sans code en clair.
- [ ] **SESS-14** Tester les faux positifs et rappeler qu'une IP/localisation n'est pas une preuve d'identité.

### Tokens API Sanctum

- [ ] **API-01** Créer les parcours autorisés de création, liste, consultation des métadonnées, révocation individuelle et révocation globale des tokens.
- [ ] **API-02** Appliquer une durée maximale de 30 jours et vérifier l'expiration côté serveur à chaque authentification API.
- [ ] **API-03** Ajouter le nettoyage périodique des tokens expirés via Laravel Scheduler.
- [ ] **API-04** Limiter les tokens actifs : root 10, super_admin 5, cooperative_admin 5, user 3.
- [ ] **API-05** Permettre à l'utilisateur de voir le nom du token, sa date de création et sa dernière utilisation ; le secret complet n'est affiché qu'une seule fois.
- [ ] **API-06** Proposer une sélection explicite de capacités autorisées selon le rôle ; refuser toute capacité excédant les droits métier.
- [ ] **API-07** Vérifier systématiquement que les abilities Sanctum ne contournent jamais Policies/Gates, propriété et appartenance à une coopérative.
- [ ] **API-08** Révoquer les tokens lors des événements déjà décidés : changement mot de passe, changement rôle/e-mail, transfert, suspension/désactivation, suppression logique, verrouillage d'urgence et déconnexion globale.
- [ ] **API-09** Ajouter tests HTTP et sécurité couvrant expiration, limite, capacité, révocation et cloisonnement coopératif.

### Verrouillage d'urgence

- [ ] **EMG-01** Implémenter le verrouillage d'urgence réservé à `super_admin_root`.
- [ ] **EMG-02** À l'activation, révoquer toutes les sessions et tokens et bloquer toute nouvelle connexion sans modifier le statut métier.
- [ ] **EMG-03** Implémenter le déverrouillage après vérification d'identité, reset sécurisé, politique/historique de mot de passe et vérification de l'e-mail.
- [ ] **EMG-04** Journaliser toutes les actions et tester les accès web/API pendant le verrouillage.

---

## 4. Priorité P1 — Gestion des utilisateurs et profils

### Profil personnel et données

- [ ] **USR-01** Auditer les routes, migrations et contrôleurs existants du profil ; confirmer les éléments réellement absents.
- [ ] **USR-02** Ajouter les champs/migrations manquants pour adresse personnelle, photo et préférences de notification, sans modifier les migrations déjà exécutées.
- [ ] **USR-03** Implémenter consultation et modification de ses propres informations par tout utilisateur.
- [ ] **USR-04** Appliquer les droits de consultation/modification d'autres profils selon rôle et périmètre coopératif.
- [ ] **USR-05** Séparer strictement les workflows du profil, de l'adresse e-mail, du mot de passe, du rôle, de la coopérative et du statut.
- [ ] **USR-06** Exclure des réponses de profil les secrets d'authentification, hashes, tokens, codes OTP et données internes.
- [ ] **USR-07** Ajouter tests de validation, autorisation, propriété et cloisonnement entre coopératives.

### Adresse e-mail

- [ ] **USR-08** Implémenter le changement d'e-mail par le propriétaire avec remplacement immédiat, statut pending et vérification dans les 24 heures.
- [ ] **USR-09** Invalider les anciens liens lors d'une nouvelle demande.
- [ ] **USR-10** Révoquer toutes les sessions et tous les tokens après changement d'e-mail.
- [ ] **USR-11** Permettre à root de corriger l'adresse d'un autre utilisateur, avec vérification obligatoire de la nouvelle adresse.
- [ ] **USR-12** Tester les liens expirés, réutilisés, invalidés, les comptes suspendus/inactifs et la non-réactivation automatique.

### Rôles, statuts et coopératives

- [ ] **USR-13** Auditer le workflow de changement de rôle par `super_admin` dans ses coopératives attribuées.
- [ ] **USR-14** Implémenter/compléter le transfert de la coopérative principale d'un utilisateur par `super_admin` entre ses coopératives attribuées, si absent.
- [ ] **USR-15** Vérifier toutes les permissions de création, consultation, modification, suspension, désactivation, archivage, restauration et suppression logique selon la matrice validée.
- [ ] **USR-16** Vérifier qu'un changement de rôle ou transfert révoque sessions et tokens et recalcule les droits après reconnexion.
- [ ] **USR-17** Vérifier les motifs obligatoires de suspension/archivage, les codes stables et traductions FR/EN/AR.
- [ ] **USR-18** Vérifier que les restaurations n'activent pas automatiquement un compte auparavant inactif ou suspendu.
- [ ] **USR-19** Ajouter les tests de matrice complète des rôles, statuts, coopératives assignées/non assignées et accès direct par URL.

### Invitation et activation

- [ ] **USR-20** Vérifier que tous les parcours d'invitation et d'activation appliquent la politique commune de mot de passe et l'historique.
- [ ] **USR-21** Auditer l'activation manuelle root et le mot de passe initial imposant un changement avant dashboard.
- [ ] **USR-22** Vérifier que le mot de passe initial est inutilisable après son premier remplacement.
- [ ] **USR-23** Vérifier les délais, l'usage unique et l'invalidation des invitations précédentes.
- [ ] **USR-24** Vérifier les e-mails, messages et transitions pending → active et ajouter tests HTTP/service.

---

## 5. Priorité P1 — Audit, sécurité et supervision

### Journal d'audit général et intégrité

- [ ] **AUD-01** Concevoir une chaîne d'intégrité cryptographique des événements : hash courant, hash précédent, ordre et métadonnées d'intégrité.
- [ ] **AUD-02** Ajouter les migrations append-only nécessaires sans altérer les migrations exécutées.
- [ ] **AUD-03** Implémenter la vérification périodique de la chaîne et la détection de falsification.
- [ ] **AUD-04** Définir la gestion des incidents d'intégrité et les alertes root-only.
- [ ] **AUD-05** Tester les modifications/suppressions directes, les événements manquants, réordonnés ou altérés et les sauvegardes.

### Journal confidentiel des consultations de fiches personnelles

- [ ] **AUD-06** Créer un journal dédié des consultations de fiches : acteur, cible, date/heure, coopérative, origine UI/API, résultat autorisé/refusé.
- [ ] **AUD-07** Brancher la journalisation sur chaque consultation, y compris refus d'accès.
- [ ] **AUD-08** Ne pas enregistrer le contenu copié de la fiche ni notifier l'utilisateur consulté.
- [ ] **AUD-09** Réserver la consultation de ce journal à `super_admin_root` exclusivement, avec contrôle des règles de sécurité validées.
- [ ] **AUD-10** Tester les consultations autorisées/refusées, l'accès root-only et l'absence de notification utilisateur.

### Événements de sécurité et tableau de bord

- [ ] **AUD-11** Compléter le cycle de vie des événements de sécurité : politiques, routes, filtres, permissions, consultation et suivi.
- [ ] **AUD-12** Construire un tableau de bord de sécurité réservé à `super_admin_root`.
- [ ] **AUD-13** Inclure les événements de connexion, appareils, OTP, verrouillages, tokens, modifications sensibles et incidents d'intégrité.
- [ ] **AUD-14** Distinguer clairement journal d'audit métier, journal confidentiel de consultation des profils et événements de sécurité.
- [ ] **AUD-15** Ajouter tests d'accès, de filtrage et de non-divulgation inter-coopérative.

### Exports, conservation et rétention

- [ ] **AUD-16** Implémenter les exports PDF, CSV et JSON selon les autorisations.
- [ ] **AUD-17** Générer des liens signés temporaires valables 15 minutes, stocker les exports en privé et journaliser génération/téléchargement.
- [ ] **AUD-18** Supprimer automatiquement les fichiers exportés après 24 heures.
- [ ] **AUD-19** Appliquer les durées de conservation : sécurité/authentification 2 ans ; données administratives/commerciales sensibles 5 ans ; technique ordinaire 90 jours ; incidents au moins 5 ans.
- [ ] **AUD-20** Définir les sauvegardes, la protection contre l'altération et la restauration des journaux.
- [ ] **AUD-21** Tester les permissions d'export, les URLs expirées, la purge, les filtres et les traces de téléchargement.

---

## 6. Priorité P2 — Notifications et confidentialité

### Notifications

- [ ] **NOT-01** Mettre en place un service de notification transversal pour Identity.
- [ ] **NOT-02** Ajouter les notifications des événements critiques : nouvel appareil, pays inhabituel, OTP, changement de mot de passe/e-mail, révocation, verrouillage d'urgence et changement obligatoire.
- [ ] **NOT-03** Implémenter les préférences de notification pour les événements ordinaires ; les alertes critiques restent obligatoires.
- [ ] **NOT-04** Ajouter un historique des notifications : type, canal, destinataire, statut d'envoi, date d'envoi et lecture.
- [ ] **NOT-05** Gérer FR/EN/AR avec fallback FR et respecter le fuseau horaire de l'utilisateur.
- [ ] **NOT-06** Tester les canaux MAHLINE/e-mail, les échecs, les relances et les préférences.

### Données personnelles et demandes d'effacement

- [ ] **PRV-01** Auditer la couverture réelle des données personnelles, de leurs accès et de leur durée de conservation.
- [ ] **PRV-02** Ajouter les exports personnels PDF/JSON avec lien temporaire sécurisé et journalisation.
- [ ] **PRV-03** Implémenter le workflow de demande d'effacement adressé à root, sans suppression automatique.
- [ ] **PRV-04** Ajouter les contrôles préalables de conservation légale, comptable, commerciale et sécurité.
- [ ] **PRV-05** Implémenter l'anonymisation uniquement après validation des obligations applicables.
- [ ] **PRV-06** Appliquer la durée de référence de cinq ans après archivage/suppression logique, sous réserve des obligations légales de conservation.
- [ ] **PRV-07** Tester les droits d'accès, l'absence de fuite de données sensibles et la traçabilité.

---

## 7. Priorité P0 — Validation des commits déjà ajoutés

Ces tâches sont prioritaires avant d'élargir le développement. Le code a été poussé sur `feature/Identity`, mais l'exécution locale n'est pas encore confirmée.

- [ ] **VAL-01** Récupérer les commits de la branche `feature/Identity`.
- [x] **VAL-02** Exécuter la migration de l'historique — exécutée localement avec succès le 8 novembre 2026 (`229.90ms DONE`).
- [x] **VAL-03** Exécuter `php artisan test --filter=PasswordPolicyTest` — VERT confirmé par l'utilisateur.
- [x] **VAL-04** Exécuter `php artisan test --filter=PasswordHistoryServiceTest` — VERT confirmé par l'utilisateur.
- [x] **VAL-05** Exécuter `php artisan test --filter=ForcedPasswordChangeControllerTest` — VERT confirmé par l'utilisateur.
- [x] **VAL-06** Exécuter `php artisan test --filter=UserInvitationServiceTest` — VERT confirmé par l'utilisateur.
- [x] **VAL-07** Exécuter les tests de reset de mot de passe — VERT confirmé par l'utilisateur (`PasswordResetServiceTest`).
- [x] **VAL-08** Corriger les régressions révélées par ces tests avant de poursuivre — fixtures alignées sur la politique et message attendu de réutilisation corrigé ; commit `c14d145adc550e8073c8770239ad545231779a11` ; test relancé et vert.
- [x] **VAL-14** Exécuter `php artisan test --filter=PasswordServiceTest` puis `php artisan test --filter=Password` après le commit `8a31970f8d983df7926f3a13d696903edd0471b3` — les deux commandes sont VERTES selon confirmation utilisateur du 9 novembre 2026.
- [x] **VAL-15** Exécuter `php artisan test --filter=PasswordResetControllerTest` — VERT confirmé par l'utilisateur le 9 novembre 2026 ; validation limitée à cette suite HTTP ciblée.
- [x] **VAL-16** Exécuter `php artisan test --filter=AuthenticationSessionControllerTest`, `php artisan test --filter=UserInvitationControllerTest` et `php artisan test --filter=UserInvitationThrottleTest` — les trois suites sont VERTES selon confirmation utilisateur du 9 novembre 2026 ; corrections routes/fixtures commités sur `feature/Identity` (commits `8ab1be9`, `4cc1c74`, `4a7d202`).
- [ ] **VAL-09** Exécuter toute la suite de tests du domaine Identity.
- [ ] **VAL-10** Exécuter les vérifications de style, analyse statique et migrations dans l'environnement local/CI du projet.
- [ ] **VAL-11** Faire un audit final du diff et confirmer que les commits respectent l'architecture V7.0 et les conventions MAHLINE.
- [x] **VAL-12** Mettre à jour les deux documents de suivi avec les résultats, preuves et commits de validation — mise à jour effectuée après confirmation du test HTTP de reset, commits documentaires du 9 novembre 2026.
- [x] **VAL-13** Exécuter `php artisan test --filter=UserManagementControllerTest` — VERT confirmé par l'utilisateur ; l'erreur `all() on array` n'est plus bloquante dans cette exécution ciblée.

---

## 8. Registre de progression

| Domaine | Tâches ouvertes initiales | Validées | Prochaine étape |
|---|---:|---:|---|
| Politique mot de passe ID-01 | À mesurer | 0 | Finaliser le contrôle compromis et tester |
| Historique ID-02 | À mesurer | 0 | Migration et tests locaux |
| Authentification | 7 | 0 | Uniformiser réponses et tentatives |
| Sessions / appareils | 14 | 0 | Délais, alertes, OTP |
| Tokens API | 9 | 0 | Cycle de vie complet Sanctum |
| Verrouillage d'urgence | 4 | 0 | Workflow root-only |
| Utilisateurs / profils | 24 | 0 | Audit puis workflows manquants |
| Audit / sécurité | 21 | 0 | Intégrité et journaux dédiés |
| Notifications | 6 | 0 | Service multicanal |
| Confidentialité | 7 | 0 | Export et effacement contrôlé |
| Validation des commits | 5 | 8 | Tests HTTP/intégration complémentaires et suite complète Identity |

> Les quantités de ce tableau sont des estimations initiales des éléments numérotés ; elles seront recalculées au fur et à mesure. Aucun élément n'est marqué validé tant qu'un test ou une preuve d'exécution n'est pas disponible.

---

## 9. Historique des mises à jour

| Date | Version | Mise à jour |
|---|---|---|
| 8 novembre 2026 | 1.0 | Création du backlog à partir des décisions Identity et des constats d'audit ; séparation des tâches de validation des commits et des développements futurs. |
| 8 novembre 2026 | 1.1 | Validation utilisateur de `PasswordPolicyTest` (vert) ; VAL-03 cochée, les autres validations restent ouvertes. |
| 8 novembre 2026 | 1.2 | Validation utilisateur de `PasswordHistoryServiceTest` (vert) ; VAL-04 cochée, migration et tests de parcours restent à valider. |
| 8 novembre 2026 | 1.3 | Validation utilisateur de `ForcedPasswordChangeControllerTest` (vert) ; VAL-05 cochée, invitation, reset et migration restent ouverts. |
| 8 novembre 2026 | 1.4 | Validation utilisateur de `UserInvitationServiceTest` (vert) ; VAL-06 cochée, reset, migration et suite complète restent ouverts. |


| 8 novembre 2026 | 1.5 | Validation utilisateur de `PasswordResetServiceTest` (vert) ; VAL-07 et VAL-08 cochées après correction des fixtures et du message attendu ; migration, tests d'intégration et suite complète Identity restent ouverts. |

| 8 novembre 2026 | 1.6 | Migration locale de l'historique des mots de passe exécutée avec succès ; ID02-01 et VAL-02 cochées. Tests d'intégration et suite complète Identity restent ouverts. |

| 8 novembre 2026 | 1.7 | Tests `php artisan test --filter=PasswordHistory` confirmés verts par l'utilisateur ; tests HTTP/intégration complémentaires, concurrence et suite complète Identity restent à exécuter. |

| 8 novembre 2026 | 1.8 | Le filtre `Password` a révélé des fixtures non conformes à la politique et une erreur distincte `all() on array` dans le test d'activation root. Trois fichiers de tests ont été corrigés pour utiliser des mots de passe conformes ; résultats locaux à confirmer et erreur distincte à diagnostiquer. |


| 8 novembre 2026 | 1.9 | Test `php artisan test --filter=UserManagementControllerTest` confirmé vert par l'utilisateur ; VAL-13 cochée. L'erreur `all() on array` n'est plus bloquante dans cette suite ciblée ; le filtre global Password et la suite complète Identity restent à confirmer. |


| 8 novembre 2026 | 2.0 | Diagnostic du filtre global `Password` : plusieurs fixtures de `PasswordServiceTest.php` utilisent un nouveau mot de passe sans caractère spécial ; le test qui prétend interdire les espaces contredit la décision validée autorisant les espaces. ID01-19 ajoutée ; correction des tests et nouvelle exécution en attente. |


| 9 novembre 2026 | 2.1 | Correction commitée de `PasswordServiceTest.php` : fixtures conformes à ID-01 et test d'acceptation des espaces ; ID01-19 cochée. VAL-14 ajoutée pour confirmer localement le test ciblé puis le filtre global Password. |
| 9 novembre 2026 | 2.2 | L'utilisateur confirme vertes les commandes `php artisan test --filter=PasswordServiceTest` et `php artisan test --filter=Password` ; VAL-14 cochée. La suite complète Identity et les tests d'intégration complémentaires restent ouverts. |
| 9 novembre 2026 | 2.3 | Test `php artisan test --filter=PasswordResetControllerTest` confirmé vert ; VAL-15 cochée. Les deux documents de suivi ont été mis à jour ; la suite complète Identity reste à exécuter. |
| 9 novembre 2026 | 2.4 | Trois suites HTTP confirmées vertes : `AuthenticationSessionControllerTest`, `UserInvitationControllerTest`, `UserInvitationThrottleTest` ; VAL-16 cochée. Corrections des routes de sessions personnelles et des fixtures d'invitation documentées ; suite complète Identity toujours ouverte. |

## Validation globale — 9 novembre 2026

Les validations suivantes sont confirmées par l'utilisateur sur la branche locale synchronisée avec `feature/Identity` :

- [x] **VAL-09** Exécuter la suite complète : `php artisan test` — **1 189 tests réussis, 3 147 assertions réussies**.
- [x] **VAL-10** Exécuter le contrôle de style : `php vendor/bin/pint --test` — **351 fichiers passés**.
- [ ] **VAL-11** Faire l'audit final du diff et confirmer la conformité à l'architecture V7.0 et aux conventions MAHLINE — reste à réaliser.

Cette validation globale couvre notamment les tests des domaines Identity, Catalog, Categories et Products. Elle ne signifie pas que toutes les fonctionnalités restantes du backlog sont implémentées : les tâches métier non validées restent ouvertes.
