# MAHLINE V7.0 — Cahier de décisions et plan de suivi
## Domaine Identity — User Management, Authentication, Security, API & Privacy

- **Version :** 1.2
- **Dernière mise à jour :** 8 novembre 2026
- **Branche de référence :** `feature/Identity`
- **Dépôt :** `areda10/mahline_v1`
- **Statut :** décisions fonctionnelles validées ; implémentation à auditer
- **Méthode :** clarification question par question avant toute modification du code

> Ce document est le registre vivant des décisions prises pendant l'audit. Il distingue les règles validées des tâches restant à réaliser. Toute nouvelle décision validée doit être ajoutée ici et commitée sur la branche `feature/Identity`.

---

## 1. Objectif et règles de suivi

Ce document sert à :
- conserver une référence unique des règles métier ;
- préparer l'implémentation sans ambiguïté ;
- identifier les migrations, services, policies, contrôleurs, routes, notifications et interfaces à auditer ;
- établir les tests unitaires, fonctionnels et HTTP nécessaires ;
- suivre les tâches jusqu'à leur validation.

**Aucune modification de code ne doit être faite avant l'audit des fichiers réels concernés et la présentation des changements proposés.** Les décisions déjà validées ne doivent pas être modifiées silencieusement.

Pour chaque tâche, conserver : identifiant, composant/fichier, décision de référence, état, tests associés, résultat/preuve et commit.

États de suivi recommandés : `À auditer`, `À faire`, `En cours`, `Bloqué`, `Testé`, `Validé`.

## 2. Rôles et statuts

### Rôles
- `super_admin_root`
- `super_admin`
- `cooperative_admin`
- `user`

Le rôle principal est unique par utilisateur. Les affectations de coopératives d'un `super_admin` sont gérées séparément. Il ne peut exister qu'un seul `super_admin_root` : aucun autre compte de ce rôle ne peut être créé ou attribué.

### Statuts
- `active`
- `pending`
- `inactive`
- `suspended`
- `archived`

Le verrouillage de sécurité est indépendant du statut métier.

## 3. Profil, adresse e-mail et rôles

### Modification du profil
- Tous les utilisateurs peuvent modifier leur propre profil.
- `super_admin` peut modifier le profil d'un utilisateur dans une coopérative qui lui est attribuée.
- `super_admin_root` peut modifier tous les profils.
- `cooperative_admin` ne peut pas modifier le profil des autres utilisateurs.
- Les modifications du profil ne changent pas le statut et ne révoquent pas les sessions.
- Les changements sont journalisés.

### Changement d'adresse e-mail
- L'adresse est remplacée immédiatement et le statut devient `pending` jusqu'à vérification de la nouvelle adresse.
- Toutes les sessions web et tous les tokens API sont révoqués.
- Tous les rôles peuvent modifier leur propre adresse ; seul `super_admin_root` peut modifier l'adresse d'un autre utilisateur.
- Le lien de vérification est valable 24 heures.
- Une nouvelle demande invalide les anciens liens.
- Une adresse incorrecte ou inaccessible doit être corrigée par `super_admin_root`.
- Toute nouvelle adresse doit être vérifiée ; l'ancien e-mail n'est jamais restauré automatiquement.
- Les changements sont audités (acteur, ancienne/nouvelle adresse, date).

### Gestion des rôles
- `super_admin_root` peut attribuer ou retirer les rôles, sauf créer ou attribuer un second rôle root.
- `super_admin` peut gérer uniquement `cooperative_admin` et `user` dans ses coopératives attribuées.
- `cooperative_admin` et `user` ne gèrent aucun rôle.
- Un changement de rôle révoque toutes les sessions et tous les tokens API ; reconnexion obligatoire.

## 4. Affectation à une coopérative

- Un utilisateur possède une seule coopérative principale.
- `super_admin_root` peut effectuer tous les transferts.
- `super_admin` peut transférer un utilisateur uniquement entre ses coopératives attribuées.
- `cooperative_admin` et `user` ne peuvent pas transférer d'utilisateur.
- Le transfert révoque toutes les sessions et tous les tokens API.
- Les droits et le dashboard sont recalculés après reconnexion.
- Les historiques commerciaux ne sont jamais transférés automatiquement.
- Un utilisateur ne peut pas être affecté à une coopérative inactive ou suspendue.
- Si une coopérative devient inactive ou suspendue, ses utilisateurs perdent l'accès à son périmètre, mais leur statut personnel ne change pas automatiquement.

## 5. Suppression logique et restauration

### Suppression logique
- Seul `super_admin_root` peut supprimer logiquement un utilisateur.
- Utilisation de `deleted_at`, sans suppression physique ni cascade sur les commandes, factures ou historiques.
- Révocation de toutes les sessions et de tous les tokens API ; connexion bloquée.

### Restauration
- Seul `super_admin_root` peut restaurer.
- Le statut précédent est conservé : `active`, `inactive` ou `suspended`. Il doit être mémorisé explicitement.
- L'e-mail doit être vérifié s'il était non vérifié ou a changé pendant la suppression.
- Le mot de passe existant est conservé.
- Les anciennes sessions et les anciens tokens restent révoqués.

## 6. Suspension, désactivation et archivage

### Suspension
- `super_admin_root` peut suspendre tous les utilisateurs.
- `super_admin` peut suspendre uniquement `cooperative_admin` et `user` dans ses coopératives attribuées.
- `super_admin` ne peut pas suspendre un autre `super_admin`.
- `cooperative_admin` et `user` ne peuvent pas suspendre d'autres utilisateurs.
- Toutes les sessions et tous les tokens sont révoqués.
- La suspension n'a pas de date de fin automatique.
- Seul `super_admin_root` peut lever une suspension ; cette action est distincte de la réactivation.
- Vérification d'e-mail exigée si l'adresse a changé ou n'a jamais été vérifiée.

Motifs obligatoires (codes stables, traductions FR/EN/AR) :
- `security_incident` — Incident de sécurité
- `suspicious_activity` — Activité suspecte
- `policy_violation` — Violation des règles MAHLINE
- `fraud_suspicion` — Suspicion de fraude
- `unauthorized_access` — Accès non autorisé
- `administrative_decision` — Décision administrative
- `other` — Autre (commentaire obligatoire)

### Désactivation
- `super_admin_root` peut désactiver tous les utilisateurs.
- `super_admin` peut désactiver `cooperative_admin` et `user` dans ses coopératives attribuées.
- `super_admin` ne peut pas désactiver un autre `super_admin`.
- Seul `super_admin_root` peut réactiver un compte `inactive`.
- Vérification d'e-mail exigée uniquement si l'adresse a changé ou n'a jamais été vérifiée.
- Les sessions et tokens sont révoqués lors de la désactivation.

### Archivage
- Seul `super_admin_root` peut archiver.
- Les sessions et tokens sont révoqués ; les données commerciales sont conservées.
- Une restauration explicite est nécessaire avant toute réactivation.
- Motifs obligatoires (codes stables, traductions FR/EN/AR) :
  - `account_inactivity` — Inactivité prolongée
  - `business_closure` — Fermeture de la coopérative
  - `contract_termination` — Fin de collaboration
  - `duplicate_account` — Compte en doublon
  - `administrative_decision` — Décision administrative
  - `other` — Autre (commentaire obligatoire)
- La restauration conserve le statut antérieur et ne restaure jamais les anciennes sessions ou tokens.
- Les motifs de restauration ne sont pas obligatoires.

## 7. Activation et invitations

### Invitation classique
- Un compte `pending` ne peut pas accéder au dashboard.
- L'utilisateur reçoit un lien sécurisé, définit lui-même son mot de passe et vérifie son adresse.
- Le compte passe à `active` après validation.
- Les invitations sont à usage unique ; une nouvelle invitation invalide la précédente.

### Activation manuelle par root
- Seul `super_admin_root` peut activer manuellement un compte.
- Mot de passe initial : `MahlineMahline`.
- Le mot de passe est stocké uniquement sous forme de hash et n'est jamais envoyé par e-mail.
- Le changement de mot de passe est obligatoire à la première connexion, avant l'accès au dashboard.
- Le mot de passe initial ne peut pas être réutilisé après son premier changement.

## 8. Réinitialisation et changement de mot de passe

### Réinitialisation
- `super_admin_root` peut réinitialiser tous les utilisateurs.
- `super_admin` peut réinitialiser `cooperative_admin` et `user` dans ses coopératives attribuées.
- `cooperative_admin` peut réinitialiser uniquement les comptes `user` de sa coopérative.
- `user` ne peut pas réinitialiser le compte d'un autre utilisateur.
- L'opération utilise un lien sécurisé par e-mail, sans mot de passe temporaire.
- Durée de validité : 30 minutes ; lien à usage unique ; nouvelle demande invalide l'ancien lien.
- Après réussite, toutes les sessions web et tous les tokens API sont révoqués.
- Seuls les comptes `active` sont éligibles. Les comptes `pending`, `inactive`, `suspended`, `archived` ou supprimés logiquement suivent leur processus dédié.

### Changement volontaire
- Mot de passe actuel obligatoire, nouveau mot de passe et confirmation.
- Politique de robustesse appliquée.
- Révocation de toutes les sessions web et de tous les tokens API ; reconnexion obligatoire sur tous les appareils.
- Événement audité.

### Historique et robustesse
- Interdire la réutilisation des cinq derniers mots de passe.
- Historique conservé uniquement sous forme de hashes.
- Minimum 12 caractères.
- Au moins une majuscule, une minuscule, un chiffre et un caractère spécial.
- Rejeter les mots de passe courants ou compromis.
- Espaces et caractères Unicode acceptés.
- Aucun changement périodique obligatoire.
- Règles applicables à la création, l'activation, la réinitialisation et au changement.

## 9. Authentification et protection contre les attaques

### Tentatives de connexion
- Blocage temporaire après cinq échecs, pendant 15 minutes.
- Limitation par compte et par IP ; compteur réinitialisé après connexion réussie.
- Message d'erreur générique, sans révéler l'existence d'une adresse.
- Journaliser les tentatives suspectes sans enregistrer de mot de passe.
- Protection contre les attaques distribuées et les changements d'IP.
- Pas de verrouillage permanent automatique par échecs de connexion.
- Le blocage temporaire expire automatiquement ; seul `super_admin_root` peut le lever manuellement.

### Nouvel appareil
- Connexion autorisée si les identifiants et le compte sont valides.
- Alerte e-mail avec date, heure, appareil, navigateur et localisation approximative si disponible.
- Possibilité de consulter et révoquer les sessions.
- Les autres appareils ne sont pas déconnectés automatiquement.
- Empreinte d'appareil raisonnable et non invasive.

### Pays inhabituel et OTP
- Un pays inhabituel déclenche une vérification OTP supplémentaire par e-mail.
- OTP de six chiffres, validité cinq minutes, maximum cinq tentatives.
- Nouveau code invalide le précédent.
- Nouvelle demande possible après 60 secondes, avec limitation de débit.
- Perte d'accès à l'e-mail : contact avec root et vérification d'identité ; aucun contournement manuel.
- Codes stockés sous forme de hash, jamais journalisés en clair.
- Génération cryptographiquement sûre et consommation atomique à usage unique.
- Aucun blocage automatique du compte sur la seule base d'un voyage ou d'un pays inhabituel.

## 10. Sécurité des comptes et incidents

### Journalisation de sécurité
- Journaux append-only ; pas de modification ou suppression non autorisée, y compris par root.
- Accès restreint et accès sensible journalisé.
- Chaînage cryptographique des événements, vérifications périodiques d'intégrité et sauvegardes indépendantes protégées.
- Détection et signalement des ruptures de chaîne ou altérations.

### Verrouillage d'urgence
- État de sécurité indépendant du statut métier.
- Exemple de champ : `security_locked_at`, avec motif et détails d'incident.
- Déclenchement et levée réservés à `super_admin_root`.
- Révocation de toutes les sessions et tokens ; connexion bloquée.
- Déverrouillage après vérification d'identité, réinitialisation du mot de passe et sécurisation/vérification de l'e-mail.
- Le statut métier reste inchangé ; un statut `inactive` ou `suspended` continue de bloquer l'accès après déverrouillage.
- Audit complet des actions.

### Signalement et traitement des incidents
- `user`, `cooperative_admin` et `super_admin` peuvent signaler un compte compromis dans leur périmètre.
- Le signalement crée un incident et notifie `super_admin_root`.
- Le signalement ne verrouille jamais automatiquement un compte ; seul root peut déclencher le verrouillage d'urgence.
- Statuts d'incident : `new`, `in_progress`, `resolved`.
- Root peut prendre en charge, ajouter des observations, verrouiller/déverrouiller et résoudre.
- Les autres rôles ne voient que les informations autorisées.
- Notifications immédiates dans MAHLINE ; e-mail pour les alertes critiques.
- L'utilisateur concerné est informé uniquement si cela ne compromet pas l'enquête.
- Les preuves sont protégées et hachées ; pas de suppression physique des incidents.
- Historique des accès réservé à root.
- Gestion des sessions concurrentes avec transactions et verrous de lignes.
- Journaliser l'identité root, la session, la date, l'action et le résultat.

### Authentification à deux facteurs
- 2FA complète reportée à une phase ultérieure.
- Architecture à garder extensible.

## 11. Sessions web

### Sessions multiples
- Un utilisateur peut avoir plusieurs sessions web actives.
- Une nouvelle connexion ne révoque pas les anciennes.
- Chaque session peut être révoquée individuellement.
- Les tokens API sont distincts des sessions web.
- La déconnexion globale peut révoquer les deux.

### Expiration d'inactivité
- `super_admin_root` : 15 minutes.
- `super_admin` : 30 minutes.
- `cooperative_admin` : 30 minutes.
- `user` : 60 minutes.
- Vérification côté serveur ; JavaScript sert uniquement à afficher le compte à rebours.
- Avertissement deux minutes avant expiration.
- Bouton « Rester connecté » pour prolonger.
- À expiration, seule la session concernée est fermée.

### Fermeture du navigateur
- `super_admin_root` et `super_admin` : cookies de session non persistants ; fermeture du navigateur = fin de la session navigateur.
- `cooperative_admin` et `user` : sessions persistantes jusqu'à expiration d'inactivité ou révocation.
- La fermeture d'un navigateur ne révoque pas les autres appareils.

### Déconnexion de tous les appareils
- Tous les rôles peuvent révoquer leurs propres sessions et tokens.
- Root peut révoquer ceux d'autres utilisateurs.
- Confirmation explicite obligatoire.
- Mot de passe actuel exigé pour root et super admin pour les opérations sensibles.
- Journalisation des révocations distantes avec identifiant du compte cible.

## 12. API et Sanctum

### Tokens
- Durée maximale : 30 jours.
- Expiration contrôlée côté serveur et vérifiée à chaque authentification API.
- Nettoyage périodique des tokens expirés par Laravel Scheduler.
- Révocation individuelle.
- Révocation globale lors d'un changement de mot de passe, de rôle ou d'un verrouillage d'urgence.
- Affichage du nom, de la date de création et de la dernière utilisation.
- Token complet affiché une seule fois.
- Création et révocation journalisées.

### Limites de tokens actifs
| Rôle | Maximum |
|---|---:|
| `super_admin_root` | 10 |
| `super_admin` | 5 |
| `cooperative_admin` | 5 |
| `user` | 3 |

### Gestion
- Tous les rôles gèrent leurs propres tokens.
- Seul root peut gérer les tokens d'autres utilisateurs.
- Mot de passe actuel exigé à chaque création.
- Révocation de son propre token sans mot de passe.
- Révocation du token d'un autre utilisateur par root : mot de passe actuel obligatoire.
- E-mail au propriétaire après création d'un token.
- E-mail au propriétaire après révocation administrative par root.
- Pas d'e-mail après révocation volontaire.

### Capacités et autorisations
- Pas de permissions automatiques globales.
- L'utilisateur choisit parmi les capacités autorisées par son rôle.
- Exemples : `profile:read`, `profile:update`, `products:read`, `products:manage`, `orders:read`, `orders:manage`.
- Une capacité ne dépasse jamais les droits du propriétaire du token.
- Double contrôle obligatoire : capacités Sanctum **et** Policies/Gates Laravel avec règles métier de propriété/appartenance.
- Une capacité API ne contourne jamais les règles de coopérative.
- Après transfert de coopérative : révocation de tous les tokens, aucun rétablissement, reconnexion et création de nouveaux tokens.

## 13. Journalisation et audit API

Éléments à tracer :
- Identifiant de l'utilisateur et identifiant interne du token.
- Route et méthode HTTP.
- Date et heure.
- Résultat HTTP.
- Adresse IP et informations techniques utiles.
- Échecs d'autorisation et tentatives d'accès à une autre coopérative.

Ne jamais enregistrer :
- Mots de passe.
- Tokens complets.
- Codes OTP.
- Secrets.
- Données sensibles des requêtes.

Séparer les journaux de sécurité détaillés des journaux techniques ordinaires.

### Durées de conservation
| Type de journal | Durée |
|---|---:|
| Sécurité et authentification | 2 ans |
| Actions administratives et métier sensibles | 5 ans |
| Journaux techniques ordinaires | 90 jours |
| Incidents de sécurité | 5 ans minimum |

### Consultation et export
- Root : accès et export globaux.
- Super admin : accès et export des coopératives attribuées, hors événements internes sensibles de MAHLINE.
- Cooperative admin : consultation de sa coopérative, sans export des journaux de sécurité globaux.
- User : aucun accès direct.
- Chaque consultation et export est journalisé.

### Formats et protection
- Formats : PDF, CSV et JSON.
- Chaque export comporte période, filtres, auteur, date de génération et empreinte SHA-256.
- URL temporaire sécurisée, valable 15 minutes.
- Stockage privé, hors dossier public.
- Journalisation de la génération et du téléchargement.
- Suppression automatique du fichier après 24 heures.

### Visibilité cooperative_admin
Peut consulter les événements de sa coopérative : création et modification des utilisateurs, invitations et activations, changements de statut autorisés, modifications des profils, connexions/déconnexions, création/révocation des tokens API.
Ne peut pas consulter les secrets, données d'authentification ou enquêtes de sécurité.

## 14. Notifications

### Destinataires
- Root : alertes globales.
- Super admin : alertes des coopératives attribuées.
- Cooperative admin : alertes de sa coopérative.
- User : alertes de son propre compte.

### Canaux et préférences
- Notifications dans MAHLINE.
- E-mail pour alertes importantes.
- Journal d'audit systématique, indépendamment de l'envoi ou de la lecture.
- Notifications ordinaires configurables ; alertes critiques obligatoires.
- Chaque utilisateur peut gérer ses notifications ordinaires et les e-mails non critiques.
- Les alertes critiques de sécurité ne peuvent pas être désactivées.

### Langues et fuseaux horaires
- Français, anglais et arabe.
- Langue des préférences utilisateur ; français par défaut si une traduction manque.
- Dates stockées en UTC et affichées selon le fuseau horaire du profil.
- E-mails et notifications adaptés à l'heure locale.
- Horodatage UTC conservé dans l'audit.

### Modifications administratives
Notification à l'utilisateur lors des actions importantes : changement de rôle, transfert de coopérative, suspension/désactivation, archivage/restauration, verrouillage/déverrouillage d'urgence, réinitialisation de mot de passe, modification d'e-mail.
Exception : différer la communication si elle risque de compromettre une enquête.

### Historique et notifications critiques
- Conserver date d'envoi et de lecture, canal, type, résultat d'envoi, destinataire et statut de lecture.
- L'utilisateur peut gérer l'affichage des notifications ordinaires, sans effacer la trace d'audit.
- Les notifications critiques ne peuvent pas être supprimées définitivement par leur destinataire.
- Root peut les archiver, sans effacer l'audit.
- Celles liées aux incidents sont conservées au moins cinq ans.

## 15. Tableau de bord et alertes de sécurité

### Tableau de bord root
- Tentatives suspectes.
- Nouveaux appareils et pays.
- Comptes verrouillés ou compromis.
- Incidents ouverts, en cours et résolus.
- Tokens API actifs et révoqués.
- Sessions actives par utilisateur.
- Historique des actions de sécurité.
- Intégrité des journaux.

Les autres administrateurs ont une vue limitée à leur périmètre.

### Alertes automatiques root
- Tentatives de connexion échouées répétées.
- Connexion depuis un pays inhabituel.
- Créations/révocations multiples de tokens.
- Tentatives d'accès à une autre coopérative.
- Échecs OTP répétés.
- Anomalies de la chaîne d'intégrité.
- Activité inhabituelle sur plusieurs comptes.

Les seuils sont configurables et les événements similaires regroupés.

### Réaction graduée
- Faible : journalisation.
- Moyenne : alerte root.
- Élevée : alerte immédiate et vérification supplémentaire si nécessaire.
- Critique : alerte immédiate et verrouillage possible, uniquement sur décision root.

Aucun verrouillage automatique sur la seule base d'un score d'anomalie.

## 16. Données personnelles et confidentialité — décisions validées

### Accès aux données personnelles (décision 91)
- `super_admin_root` : accès à toutes les données personnelles de tous les utilisateurs.
- `super_admin` : accès uniquement aux données personnelles des utilisateurs des coopératives qui lui sont attribuées.
- `cooperative_admin` : aucun accès aux données personnelles des autres utilisateurs.
- `user` : accès à ses propres données personnelles uniquement, avec possibilité de les consulter et de les modifier.
- Les secrets d'authentification, tokens, codes de vérification et informations internes de sécurité restent exclus des vues administratives ordinaires.
- L'accès doit être contrôlé côté serveur par Policies/Gates et respecter le périmètre coopératif.

### Catégories de données consultables par root et super admin (décision 92)
Dans le périmètre autorisé ci-dessus, `super_admin_root` et `super_admin` peuvent consulter les catégories suivantes :
1. **Identité :** nom, prénom et date de naissance si elle est collectée.
2. **Coordonnées :** adresse e-mail, téléphone et adresse postale.
3. **Profil :** photo de profil et autres informations personnelles renseignées.
4. **Informations professionnelles :** rôle, coopérative, fonction et statut du compte.
5. **Informations de gestion :** date de création du compte et date de dernière mise à jour.

Les mots de passe, tokens, codes OTP, secrets et données confidentielles d'authentification restent exclus. Le périmètre coopératif du `super_admin` s'applique à toutes ces catégories.

### Données personnelles consultables et modifiables par leur propriétaire (décision 93)

Chaque utilisateur peut consulter et modifier les données suivantes de son propre profil :
- Nom et prénom.
- Téléphone.
- Adresse personnelle.
- Photo de profil.
- Langue d'affichage.
- Fuseau horaire.
- Préférences de notifications.

L'adresse e-mail, le mot de passe, le rôle, la coopérative et le statut restent soumis à leurs procédures spécifiques de sécurité et de gestion déjà définies. L'édition du profil personnel ne permet donc pas de modifier les droits, l'appartenance à une coopérative ou le cycle de vie du compte.

### Export des données personnelles par leur propriétaire (décision 94)

Chaque utilisateur peut télécharger une copie de ses propres données personnelles :
- Formats proposés : PDF (lecture) et JSON (réutilisation informatique).
- L'accès à l'export est limité aux données du propriétaire authentifié.
- Le téléchargement est fourni par un lien sécurisé et temporaire.
- La génération et le téléchargement de chaque export sont journalisés.
- Les mots de passe, tokens, codes OTP, secrets et autres données sensibles d'authentification sont exclus.
- Les exports administratifs concernant d'autres utilisateurs restent soumis aux autorisations et périmètres définis pour le rôle concerné.

### Correction des données personnelles (décision 95)

Les règles de correction sont les suivantes :
- Chaque utilisateur peut corriger lui-même ses propres données personnelles modifiables, dans les limites des règles spécifiques applicables à certains champs.
- `super_admin` peut corriger les données personnelles des utilisateurs des seules coopératives qui lui sont attribuées.
- `super_admin_root` peut corriger les données personnelles de tous les utilisateurs.
- `cooperative_admin` ne peut pas modifier les données personnelles des autres utilisateurs.
- Toute correction effectuée par un administrateur est journalisée avec l'identité de l'auteur, l'ancienne valeur, la nouvelle valeur et la date.
- Les changements d'adresse e-mail restent soumis à la procédure dédiée de changement et de vérification déjà validée.

### Suppression et anonymisation des données personnelles (décision 96)

Trois situations sont distinguées :
1. **Suppression du compte :** la suppression logique d'un compte reste réservée à `super_admin_root`, conformément à la règle de cycle de vie déjà validée. Elle ne déclenche pas de suppression physique automatique des données métier.
2. **Demande d'effacement personnel :** chaque utilisateur peut soumettre une demande à `super_admin_root`. La demande n'entraîne aucune suppression automatique ; elle doit être examinée avant toute décision.
3. **Anonymisation :** les données personnelles qui ne sont plus nécessaires peuvent être anonymisées après vérification des obligations légales, comptables, commerciales et de sécurité applicables.

Les données nécessaires à la traçabilité des commandes, factures et autres obligations de conservation ne doivent pas être supprimées automatiquement. Toute opération d'effacement ou d'anonymisation autorisée devra préserver les historiques et journaux qui doivent légalement ou techniquement être conservés, en limitant les données personnelles conservées au strict nécessaire.

### Données à conserver malgré une demande d'effacement (décision 97)

Les catégories suivantes peuvent nécessiter une conservation malgré une demande d'effacement, dans la limite des obligations applicables et du strict nécessaire :
1. **Facturation :** factures, avoirs, justificatifs et données légalement obligatoires.
2. **Commandes :** historique des commandes, transactions et livraisons.
3. **Comptabilité :** écritures et pièces justificatives.
4. **Sécurité :** journaux d'accès, incidents et traces d'administration.
5. **Litiges :** preuves nécessaires aux réclamations et procédures en cours.

Les données conservées doivent être accessibles uniquement aux rôles autorisés et pendant la durée applicable. Les données qui ne sont plus nécessaires doivent être supprimées ou anonymisées selon les règles validées. Aucun mot de passe, token ou code OTP ne doit être conservé en clair, y compris au titre de ces obligations de conservation.

### Durée de conservation des données de profil après fermeture du compte (décision 98)

Durée de référence définie pour les données de profil :
- **Compte actif :** conservation pendant toute la durée d'activité du compte.
- **Compte suspendu ou inactif :** conservation jusqu'à réactivation ou décision de fermeture.
- **Compte archivé :** conservation pendant 5 ans à compter de l'archivage, puis examen pour anonymisation ou suppression des données qui ne sont plus nécessaires.
- **Compte supprimé logiquement :** conservation pendant 5 ans à compter de la suppression logique, puis examen pour anonymisation ou suppression des données qui ne sont plus nécessaires.

Les données soumises à des obligations légales, comptables, commerciales ou de sécurité suivent leurs propres durées de conservation et peuvent donc être conservées au-delà de cette durée de référence. Les délais définitifs devront être vérifiés au regard des obligations légales et réglementaires applicables avant mise en production ; la durée de cinq ans constitue la règle fonctionnelle validée, non une affirmation de délai légal universel.

### Journalisation des consultations administratives de fiches personnelles (décision 99)

Chaque consultation d'une fiche personnelle par un administrateur est journalisée, qu'elle soit autorisée ou refusée. L'événement conserve :
- L'identité de l'administrateur.
- L'identifiant de l'utilisateur dont la fiche est visée.
- La date et l'heure.
- La coopérative concernée, lorsqu'elle s'applique.
- L'origine de la consultation (interface web ou API).
- Le résultat de l'accès (autorisé ou refusé).

Le journal enregistre la trace de l'accès sans recopier les données personnelles consultées. Cette règle s'applique à `super_admin_root` et `super_admin`, dans les limites de leurs périmètres respectifs. Les journaux restent soumis aux règles append-only, d'accès restreint et de conservation déjà définies.

### Notification lors d'une consultation administrative d'une fiche personnelle (décision 100)

Lorsqu'un `super_admin_root` ou un `super_admin` consulte une fiche personnelle, l'utilisateur concerné ne reçoit aucune notification, ni immédiate ni par e-mail, du seul fait de cette consultation.

Cette absence de notification ne supprime pas l'obligation de journaliser l'accès conformément à la décision 99. La question de savoir si l'utilisateur peut consulter lui-même un historique de ces accès reste à clarifier séparément.

### Questions de confidentialité restant à clarifier
- [x] Catégories exactes de données personnelles visibles par root et super admin — décision 92 validée.
- [x] Données consultables et modifiables par le propriétaire — décision 93 validée.
- [x] Export personnel PDF/JSON, lien temporaire sécurisé, journalisation et exclusion des secrets — décision 94 validée.
- [x] Correction par le propriétaire et corrections administratives selon le périmètre root/super_admin ; cooperative_admin exclu — journalisation des changements administratifs — décision 95 validée.
- [x] Demande d'effacement soumise par l'utilisateur à super_admin_root, sans suppression automatique ; anonymisation après vérification des obligations de conservation — décision 96 validée.
- [x] Catégories de conservation : facturation, commandes, comptabilité, sécurité et litiges ; accès limité et durée applicable — décision 97 validée.
- [x] Durée de référence de 5 ans après archivage ou suppression logique, sous réserve des durées spécifiques et obligations applicables — décision 98 validée.
- [x] Journaliser chaque consultation administrative (autorisée ou refusée) avec acteur, utilisateur visé, date/heure, coopérative, origine et résultat, sans recopier les données consultées — décision 99 validée.
- [x] Aucune notification à l'utilisateur lors de la consultation d'une fiche par super_admin_root ou super_admin — décision 100 validée.

## 17. Plan de tâches à réaliser

Les tâches ci-dessous sont un plan d'audit et d'implémentation. Elles ne signifient pas que les fonctionnalités sont déjà présentes dans le dépôt.

### A. Audit du dépôt
- [ ] Vérifier la synchronisation GitHub/local et la branche active.
- [ ] Inventorier les fichiers réels du domaine Identity.
- [ ] Comparer les implémentations existantes avec les décisions du présent document.
- [ ] Identifier les migrations déjà exécutées.
- [ ] Identifier les services, policies, Gates, contrôleurs, routes, notifications et vues concernés.
- [ ] Produire une matrice écarts / risques / corrections avant modification.

### B. Modèle de données et migrations
- [ ] Vérifier les statuts, rôles et relations coopératives.
- [ ] Vérifier la conservation du statut précédent.
- [ ] Prévoir le verrouillage de sécurité indépendant.
- [ ] Vérifier le stockage de l'historique de mots de passe.
- [ ] Vérifier les invitations et tokens de vérification.
- [ ] Vérifier les OTP et leur consommation atomique.
- [ ] Vérifier les sessions multiples et leur révocation individuelle.
- [ ] Vérifier les tokens API et leur expiration.
- [ ] Vérifier les tables d'audit, incidents et notifications.
- [ ] Créer de nouvelles migrations pour les changements nécessaires lorsque les migrations ont déjà été exécutées.

### C. Services et règles métier
- [ ] Centraliser les règles de cycle de vie des utilisateurs.
- [ ] Vérifier les autorisations de chaque rôle.
- [ ] Vérifier les transferts entre coopératives.
- [ ] Vérifier les changements d'e-mail et de rôle.
- [ ] Vérifier les flux d'activation et d'invitation.
- [ ] Vérifier les réinitialisations et changements de mots de passe.
- [ ] Vérifier les limites et permissions des tokens.
- [ ] Vérifier les opérations de verrouillage et déverrouillage d'urgence.
- [ ] Vérifier l'intégrité des opérations concurrentes avec transactions et verrous adaptés.

### D. Authentification et sécurité
- [ ] Vérifier les limites de tentatives et les délais de blocage.
- [ ] Vérifier les alertes nouveaux appareils et pays inhabituels.
- [ ] Vérifier l'OTP e-mail.
- [ ] Vérifier l'expiration des sessions côté serveur.
- [ ] Vérifier le comportement à la fermeture du navigateur selon le rôle.
- [ ] Vérifier la déconnexion globale et individuelle.
- [ ] Vérifier le nettoyage périodique des tokens expirés via Scheduler.
- [ ] Vérifier que les capacités Sanctum ne contournent jamais Policies/Gates.

### E. Audit, incidents et notifications
- [ ] Vérifier l'append-only des journaux.
- [ ] Implémenter ou vérifier la chaîne d'intégrité et ses contrôles.
- [ ] Vérifier les sauvegardes protégées.
- [ ] Vérifier les règles d'accès aux journaux selon les rôles.
- [ ] Vérifier les durées de conservation et les mécanismes de purge.
- [ ] Vérifier les exports PDF, CSV et JSON.
- [ ] Vérifier le stockage privé, les liens temporaires et la suppression après 24 heures.
- [ ] Vérifier les notifications et les préférences.
- [ ] Vérifier les traductions FR/EN/AR.
- [ ] Vérifier le fuseau horaire par utilisateur.
- [ ] Vérifier le tableau de bord de sécurité et les alertes graduées.

### F. Données personnelles et confidentialité
- [ ] Auditer les champs personnels réellement présents dans le modèle utilisateur et les domaines associés.
- [ ] Implémenter et tester les autorisations root/super_admin/utilisateur selon la décision 91.
- [ ] Vérifier qu'un cooperative_admin ne peut accéder aux données personnelles d'autrui, même par URL directe ou API.
- [x] Définir les catégories de données modifiables par leur propriétaire — décision 93.
- [x] Règles fonctionnelles d'export définies (décision 94) ; implémentation et tests à réaliser.
- [x] Processus fonctionnel de demande d'effacement et d'anonymisation défini (décision 96) ; conformité juridique et implémentation à vérifier.
- [ ] Vérifier les durées légales/réglementaires exactes par catégorie et les documenter ; durée de référence des données de profil fixée à 5 ans après archivage/suppression logique (décision 98).
- [x] Journaliser les consultations administratives des fiches personnelles selon la décision 99.

### G. Tests indispensables
- [ ] Matrice de permissions par rôle et par coopérative.
- [ ] Tests d'accès direct à une coopérative non attribuée.
- [ ] Tests de changement de rôle et de transfert.
- [ ] Tests de changement d'e-mail et expiration du lien.
- [ ] Tests d'invitation à usage unique et invalidation des anciens liens.
- [ ] Tests de politique de mots de passe et des cinq derniers mots de passe.
- [ ] Tests de réinitialisation par rôle et par statut.
- [ ] Tests de révocation des sessions et tokens.
- [ ] Tests de sessions multiples et révocation individuelle.
- [ ] Tests d'expiration d'inactivité côté serveur.
- [ ] Tests de limitation des tentatives de connexion.
- [ ] Tests OTP : expiration, tentatives, invalidation, rejeu et concurrence.
- [ ] Tests des tokens : capacités, expiration, limite active, révocation et Scheduler.
- [ ] Tests d'isolation des données entre coopératives via API.
- [ ] Tests d'intégrité et d'accès aux journaux.
- [ ] Tests d'accès, d'expiration et de purge des exports.
- [ ] Tests des notifications et de leurs permissions.
- [ ] Tests des incidents et du verrouillage d'urgence.
- [ ] Tests de confidentialité, consultation/modification de ses propres données et restrictions d'accès inter-utilisateurs.
- [ ] Tests de non-régression de tous les flux existants.

### H. Validation et livraison
- [ ] Corriger les écarts identifiés.
- [ ] Exécuter les tests ciblés après chaque lot.
- [ ] Exécuter l'ensemble des tests du projet.
- [ ] Vérifier `route:list`, migrations et Scheduler.
- [ ] Effectuer une revue de sécurité.
- [ ] Documenter les commandes d'exploitation et de maintenance.
- [ ] Faire valider les résultats avant clôture du domaine Identity.

## 18. État du périmètre

- Les décisions consignées dans les sections 2 à 16 sont celles validées pendant la phase de questions-réponses.
- L'implémentation effective reste à auditer dans le dépôt.
- Les questions ouvertes de confidentialité sont listées en section 16.
- La décision 92 fixe les catégories de données consultables par root et super admin. Les décisions 93 et 94 fixent respectivement les données personnelles consultables/modifiables par leur propriétaire et les règles d'export personnel. La décision 95 définit les droits de correction des données personnelles et leur journalisation. La décision 96 définit les demandes d'effacement et les conditions d'anonymisation. La décision 97 définit les catégories de données susceptibles d'être conservées malgré une demande d'effacement. La décision 98 fixe une durée de référence de cinq ans pour les données de profil après archivage ou suppression logique. La décision 99 impose la journalisation des consultations administratives des fiches personnelles. La décision 100 confirme qu'aucune notification n'est envoyée à l'utilisateur lors de ces consultations. La prochaine question à traiter est la question 101 : l'utilisateur peut-il consulter un historique des accès administratifs à sa fiche ?
- Ce document doit être mis à jour dans `feature/Identity` à chaque nouvelle décision validée.
