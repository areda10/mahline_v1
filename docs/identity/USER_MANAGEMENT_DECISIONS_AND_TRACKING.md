# MAHLINE V7.0 — Cahier de décisions et plan de suivi
## Domaine Identity — User Management, Authentication, Security, API & Privacy

- **Version :** 3.1
- **Dernière mise à jour :** 9 novembre 2026
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
- Seul `super_admin_root` peut activer manuellement un compte ; ce parcours ne s'applique pas à l'invitation classique ni aux autres workflows d'activation.
- Mot de passe initial temporaire actuel : `MahlineMahline` ; son remplacement par un mot de passe aléatoire est reporté à une évolution ultérieure.
- Le mot de passe est stocké uniquement sous forme de hash. Il est communiqué manuellement par `super_admin_root` (par exemple lors d'un appel téléphonique) ; aucun appel vocal automatisé ni envoi automatique par e-mail n'est prévu dans ce parcours.
- Le changement de mot de passe est obligatoire à la première connexion, avant tout accès métier, y compris le dashboard.
- Tant que `must_change_password` est actif, l'utilisateur ne peut accéder qu'aux routes strictement nécessaires au changement obligatoire et à la déconnexion ; les accès web métier et les routes API authentifiées protégées sont refusés.
- Le nouveau mot de passe doit respecter la politique et l'historique validés ; le mot de passe initial ne peut pas être réutilisé.
- Après un changement obligatoire réussi, la session web courante est conservée, les autres sessions web actives sont révoquées et les tokens API existants sont supprimés.
- Le changement volontaire de mot de passe et l'acceptation d'une invitation conservent leurs workflows propres ; cette règle de conservation de session est limitée au changement obligatoire issu de l'activation manuelle.

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
- Interdire la réutilisation du mot de passe actuel et des cinq anciens mots de passe.
- L'historique contient au maximum cinq anciens hashes, en plus du hash du mot de passe actuel stocké sur l'utilisateur.
- Les hashes d'historique sont conservés uniquement sous forme de hashes sécurisés ; aucun mot de passe en clair n'est persisté.
- Lors d'un changement réussi, l'ancien hash courant est ajouté à l'historique dans la même transaction que le changement. Une opération échouée ou annulée ne doit jamais ajouter d'entrée.
- Lorsqu'une sixième entrée ancienne doit être conservée, supprimer la plus ancienne afin de maintenir exactement au maximum cinq anciens hashes.
- Minimum 12 caractères et maximum 128 caractères.
- Au moins une majuscule, une minuscule, un chiffre et un caractère spécial.
- Rejeter les mots de passe courants ou compromis.
- Espaces et caractères Unicode acceptés.
- Aucun changement périodique obligatoire.
- Règles applicables à la création, l'activation, la réinitialisation et au changement.

### Politique renforcée des mots de passe — décisions ID-01
- Appliquer la politique à tous les rôles, sans exception : super_admin_root, super_admin, cooperative_admin et user.
- Refuser les mots de passe contenant des informations personnelles reconnaissables de l'utilisateur : nom complet, prénom, identifiant ou partie identifiable de l'adresse e-mail. La détection est insensible à la casse et doit éviter les faux positifs excessifs.
- Vérifier les mots de passe courants/compromis lors de la création, de l'acceptation d'une invitation, de l'activation, du changement volontaire ou forcé et de la réinitialisation.
- Le service externe de vérification ne reçoit jamais le mot de passe en clair. Utiliser une approche préservant la confidentialité, de type k-anonymity.
- Si le service externe est indisponible, appliquer une liste locale de refus, conserver tous les contrôles de robustesse et refuser au minimum les mots de passe localement connus comme compromis/courants. Reprendre le contrôle externe dès que le service redevient disponible.
- La liste locale doit pouvoir être mise à jour sans modification du code applicatif.
- Conserver l'historique des mises à jour de la liste : version, horodatage, source et auteur/processus.
- Autoriser deux modes de mise à jour : processus automatique contrôlé et action manuelle réservée à super_admin_root.
- Fréquence de mise à jour configurable par super_admin_root, quotidienne par défaut.
- Vérifier la provenance de la source et son intégrité (signature ou SHA-256). Rejeter toute liste corrompue ou incomplète, conserver la dernière version valide et permettre un retour arrière.
- Journaliser chaque tentative de mise à jour. Après trois échecs consécutifs, alerter super_admin_root ; remettre le compteur à zéro après une mise à jour réussie.
- Le tableau de bord de sécurité réservé à super_admin_root doit présenter la version active, la dernière mise à jour, la prochaine mise à jour planifiée, les contrôles d'intégrité, les indisponibilités externes, les erreurs et l'historique.
- Pour les comptes existants, vérifier le mot de passe fourni lors de la prochaine authentification réussie. Si ce mot de passe ne respecte plus la politique ou est compromis, imposer un changement avant tout accès au dashboard.
- Pendant ce changement obligatoire, seules les routes de changement de mot de passe et de déconnexion sont accessibles. Le nouveau mot de passe doit respecter la politique et l'historique.
- Envoyer un e-mail informatif à l'utilisateur lorsqu'un changement est requis ; ne jamais inclure le mot de passe ni un lien de réinitialisation non sollicité.
- Afficher à l'utilisateur les motifs utiles du refus (longueur, classe de caractères manquante, mot de passe courant/compromis ou information personnelle), sans révéler les détails internes de détection.

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

Cette absence de notification ne supprime pas l'obligation de journaliser l'accès conformément à la décision 99. L'historique de ces accès est réservé à super_admin_root, conformément à la décision 101.

### Accès à l'historique des consultations administratives des fiches personnelles (décision 101)

Les journaux des consultations administratives de fiches personnelles sont consultables uniquement par `super_admin_root`.
- Les utilisateurs concernés ne peuvent pas consulter cet historique.
- `super_admin` ne peut pas consulter ces journaux, y compris pour les utilisateurs de ses coopératives attribuées.
- `cooperative_admin` et `user` n'y ont aucun accès.
- La restriction concerne la consultation des journaux ; elle ne modifie pas l'obligation de journalisation append-only définie à la décision 99.
- Toute consultation de ces journaux par `super_admin_root` doit elle-même être journalisée.

### Protection de l'accès de super_admin_root aux journaux d'audit (décision 102 — point validé)

L'accès de `super_admin_root` aux journaux d'audit et aux journaux de consultation des fiches personnelles reste protégé par les règles de sécurité déjà validées dans le présent document. Cette validation ne crée aucun contournement des contrôles d'authentification, de session, d'autorisation et de journalisation existants.

Les protections supplémentaires proposées ensuite ont été refusées comme nouvelles règles par la décision 103 ; les règles générales déjà validées restent applicables.

### Protections supplémentaires proposées pour les consultations de journaux par super_admin_root (décision 103)

La proposition groupée de protections supplémentaires (journalisation spécifique de chaque consultation par root, nouvelle confirmation de l'interdiction de modifier/supprimer les journaux, et alerte dédiée en cas de consultation massive ou inhabituelle) n'a pas été acceptée comme nouvel ensemble de règles.

Précisions de portée :
- Aucune nouvelle obligation spécifique de journalisation des consultations de journaux par root n'est ajoutée au titre de cette proposition.
- Aucune nouvelle alerte dédiée liée au volume ou au caractère inhabituel des consultations de journaux n'est ajoutée au titre de cette proposition.
- Le refus de cette proposition ne supprime pas les règles générales déjà validées, notamment le caractère append-only des journaux d'audit et les contrôles d'accès/sécurité existants.

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
- [ ] Journaliser les consultations administratives des fiches personnelles selon la décision 99 — exigence validée, implémentation non repérée dans les contrôleurs examinés ; vérifier les mécanismes transversaux.

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
- La décision 92 fixe les catégories de données consultables par root et super admin. Les décisions 93 et 94 fixent respectivement les données personnelles consultables/modifiables par leur propriétaire et les règles d'export personnel. La décision 95 définit les droits de correction des données personnelles et leur journalisation. La décision 96 définit les demandes d'effacement et les conditions d'anonymisation. La décision 97 définit les catégories de données susceptibles d'être conservées malgré une demande d'effacement. La décision 98 fixe une durée de référence de cinq ans pour les données de profil après archivage ou suppression logique. La décision 99 impose la journalisation des consultations administratives des fiches personnelles. La décision 100 confirme qu'aucune notification n'est envoyée à l'utilisateur lors de ces consultations. La décision 101 réserve la consultation des journaux d'accès aux fiches personnelles à super_admin_root. La décision 102 valide que l'accès de super_admin_root aux journaux d'audit reste protégé par les règles de sécurité déjà approuvées. Les autres protections supplémentaires proposées pour ses consultations restent à confirmer.
- Ce document doit être mis à jour dans `feature/Identity` à chaque nouvelle décision validée.


## 19. Clôture de la clarification et lancement de l'audit technique

### Q104 — Clôture de la phase de questions
- **Décision : OUI, validée le 8 novembre 2026.**
- La phase de clarification fonctionnelle et de sécurité est terminée.
- L'audit technique global du domaine Identity peut commencer sur la branche `feature/Identity`.
- L'audit doit s'appuyer sur les fichiers réellement présents dans le dépôt.
- Il doit distinguer les fonctionnalités implémentées, incomplètes, absentes et celles dont la conformité reste à prouver par des tests.
- Il doit relever les risques, les écarts par rapport aux décisions Q1 à Q103 et les tests manquants.
- Un plan de correction précis, fichier par fichier, doit être présenté avant toute modification.
- **Aucune modification du code n'est autorisée avant validation explicite du plan par l'utilisateur.**

### Premières observations techniques — provisoires, à confirmer pendant l'audit
Ces observations proviennent d'une première lecture de fichiers de la branche `feature/Identity`. Elles ne constituent pas encore le rapport final :

1. `routes/web.php` et `routes/api.php` ne montrent pas, dans leur état examiné, de routes dédiées à la consultation et à la modification du profil personnel. L'existence éventuelle d'autres points d'entrée devra être vérifiée.
2. `PasswordResetService::createToken()` crée actuellement des liens de réinitialisation valables 60 minutes, alors que la décision validée prévoit 30 minutes.
3. `PasswordResetService` vérifie l'existence, l'utilisation et l'expiration du jeton, mais les méthodes examinées ne vérifient pas que le compte est dans un état autorisant la réinitialisation (compte actif uniquement).
4. `EnsureActiveAuthenticationSession` vérifie l'autorisation du compte et l'existence de la session MAHLINE, puis actualise son activité. Aucun contrôle de durée d'inactivité propre au rôle n'apparaît dans ce middleware ; la conformité aux délais validés reste à vérifier dans le reste du projet.
5. Les routes API d'audit et d'authentification doivent être vérifiées avec leurs Form Requests, Policies, capacités Sanctum et services métier afin de confirmer le cumul des deux niveaux d'autorisation.

**État :** audit en cours ; aucune modification du code effectuée.


## 20. Point de contrôle de l'audit technique — 8 novembre 2026

**Branche auditée :** `feature/Identity`  
**Commit de départ du contrôle :** `926cf33b09f93f9e651dafd259fa57b3d6f4c6aa`  
**État :** audit en cours — aucune modification du code applicatif.

### 20.1 Écarts confirmés dans les fichiers examinés

1. **Expiration du lien de réinitialisation :** `PasswordResetService::createToken()` utilise 60 minutes au lieu des 30 minutes validées.
2. **Invalidation des anciens liens :** `createToken()` ajoute un nouveau jeton sans révoquer les jetons précédents non utilisés. Après une réinitialisation, le service marque uniquement le jeton courant comme utilisé.
3. **Statut du compte :** `validateToken()` et `resetPassword()` vérifient l'existence du compte, mais pas que son statut est `active`. La réinitialisation doit être réservée aux comptes actifs.
4. **Révocation des accès après réinitialisation :** `PasswordResetService` révoque les sessions web via `SessionService`, mais ne révoque pas les tokens API. La décision validée exige la révocation des deux.
5. **Politique du mot de passe :** `PasswordPolicy` interdit les espaces, n'exige pas de caractère spécial et ne vérifie pas les mots de passe communs/compromis. La politique validée autorise espaces et Unicode, exige majuscule/minuscule/chiffre/caractère spécial et rejette les mots de passe communs ou compromis.
6. **Historique des mots de passe :** aucun mécanisme ni migration d'historique n'a été repéré dans les fichiers et migrations examinés. La règle des cinq derniers mots de passe n'est donc pas démontrée.
7. **Délai d'inactivité :** `EnsureActiveAuthenticationSession` vérifie l'état du compte et la session MAHLINE, puis actualise l'activité, mais ne vérifie pas les délais d'inactivité différenciés par rôle. La configuration de session Laravel examinée utilise un réglage global `expire_on_close`, pas les règles de persistance par rôle.
8. **Profil personnel :** aucune route ou classe dédiée au profil personnel n'a été repérée dans l'arborescence examinée. Le modèle User ne contient pas, à ce stade, les attributs d'adresse personnelle, de photo ou de préférences de notification attendus ; les migrations supplémentaires devront être vérifiées avant de figer ce constat.
9. **Consultation des fiches personnelles :** les méthodes `show()` des contrôleurs User Management examinés vérifient les Policies, mais n'enregistrent pas elles-mêmes la consultation. Aucun mécanisme transversal de journalisation de cette consultation n'a encore été identifié. L'exigence de journalisation reste donc à démontrer.
10. **Tokens API :** le modèle Sanctum personnalisé et le stockage haché sont présents. Toutefois, `config/sanctum.php` fixe `expiration` à `null`, aucune couche de gestion des tokens (création, liste, révocation individuelle, capacités autorisées et limites par rôle) n'a été repérée dans les routes et contrôleurs examinés, et aucun Scheduler de purge des tokens expirés n'apparaît dans les fichiers de planification examinés.
11. **API d'audit :** `routes/api.php` expose `/audit/events` derrière `auth:sanctum`; le contrôleur applique `viewAny` et filtre les événements par coopérative. La Policy actuelle autorise aussi un `cooperative_admin` ayant `audit.view` à consulter les événements de sa coopérative. Cette règle doit être comparée à la décision applicable au type exact de journaux concerné ; ne pas confondre l'accès général aux événements métier avec les journaux spécifiques de consultation des fiches personnelles, réservés à `super_admin_root`.

### 20.2 Éléments conformes ou partiellement présents

- Plusieurs sessions web simultanées sont prises en charge : `SessionService::create()` crée une nouvelle session sans révoquer les précédentes.
- La révocation individuelle des sessions web est exposée dans les routes back-office.
- Les tokens API sont distincts des sessions web ; un test Framework vérifie cette séparation.
- Le modèle Sanctum personnalisé utilise des ULID et les tests Framework vérifient le stockage haché des secrets de tokens.
- La restauration d'un utilisateur supprimé conserve la révocation de ses anciennes sessions et tokens ; le service restaure le compte en statut `pending`.
- Le flux d'invitation invalide les invitations précédentes non utilisées avant d'en créer une nouvelle.
- Le service de gestion des utilisateurs révoque les sessions et tokens lors de certains changements de rôle/profil ou de statut ; ce mécanisme ne couvre pas encore le flux de réinitialisation de mot de passe examiné.

### 20.3 Tests repérés et tests manquants à confirmer

Douze fichiers Feature sont présents sous `tests/Feature/Domains/Identity/`, ainsi qu'un test Framework dédié au modèle de token Sanctum. Les fichiers repérés ne démontrent pas encore une couverture complète des sujets suivants :

- réinitialisation de mot de passe : expiration 30 minutes, invalidation des anciens liens, statut actif uniquement, usage unique, révocation de toutes les sessions et de tous les tokens ;
- politique complète des mots de passe et historique des cinq derniers ;
- délais d'inactivité par rôle et expiration serveur ;
- gestion API des tokens : capacités, durée maximale de 30 jours, limites par rôle, révocation individuelle et globale, nettoyage planifié ;
- profil personnel et contrôle d'accès à ses propres données ;
- journalisation des consultations autorisées et refusées des fiches personnelles ;
- règles OTP, vérification de connexion depuis un pays inhabituel et notifications de sécurité, à confirmer dans les autres composants du projet.

**Important :** les tests listés dans le dépôt n'ont pas été exécutés pendant cet audit. Leur présence ne constitue pas une preuve qu'ils passent.

### 20.4 Prochaine étape

Poursuivre l'audit des flux Identity (création/activation, changements d'e-mail, statuts, rôles, transferts de coopérative, API et audit/privacy), puis produire une matrice consolidée des écarts et un plan de correction fichier par fichier. Aucun changement de code avant validation explicite.


### 20.5 Compléments — cycle de vie, e-mail et authentification

12. **Changement d'e-mail personnel :** aucune route ni action dédiée au changement d'adresse par son propriétaire n'a été repérée. Le parcours présent est l'action `update()` de `SuperAdminRoot/Http/Controllers/UserManagementController`, protégée pour root. Cela ne couvre pas la règle selon laquelle chaque rôle peut changer sa propre adresse.
13. **Délai de vérification après changement d'e-mail :** `UserInvitationService` applique une expiration fixe de 60 minutes à toutes ses invitations. Le contrôleur root réutilise ce service après un changement d'e-mail. La décision prévoit 24 heures pour le lien de vérification de la nouvelle adresse ; le parcours actuel ne respecte donc pas ce délai.
14. **Rôles des utilisateurs existants :** `UserManagementService::updateUserAccount()` appelle `assertSuperAdminRoot()`. Aucun autre service/action de modification de rôle d'un utilisateur existant n'a été repéré. La création par `super_admin` de comptes `cooperative_admin` et `user` est couverte, mais pas la gestion ultérieure de leurs rôles telle que décidée.
15. **Transfert de coopérative d'un utilisateur :** le changement de `cooperative_id` est effectué dans `updateUserAccount()`, réservé à root. Le service d'affectation existant concerne le pivot des coopératives attribuées aux `super_admin`, et non le transfert de la coopérative principale d'un utilisateur. Le transfert par `super_admin` entre ses coopératives attribuées n'est donc pas couvert par le parcours identifié.
16. **Alerte de connexion depuis un nouvel appareil :** `UnusualActivityDetectionService::isNewDevice()` est disponible, mais le workflow ne l'appelle pas. `AppServiceProvider` lie `AuthenticationWorkflowHook` à `NullAuthenticationWorkflowHook`, dont le hook est vide. Aucun envoi d'alerte e-mail au nouvel appareil n'est donc réalisé par ce flux.
17. **Pays inhabituel et OTP :** aucune implémentation de détection de pays, génération/envoi OTP, validation, consommation atomique ou limite de tentatives n'a été repérée dans les fichiers Identity inventoriés. L'enum `SecurityEventType` contient `UnusualActivityDetected`, mais cela ne constitue pas une implémentation OTP.
18. **Message de connexion et divulgation d'information :** `AuthenticationController::login()` retourne au client le message des exceptions RuntimeException. `AuthenticationService` distingue notamment compte inexistant (404) et compte existant mais inactif / e-mail non vérifié / IP verrouillée (401 avec message distinct). Cela révèle des informations sur l'existence ou l'état d'un compte, contrairement à la règle de message générique. Les réponses doivent être uniformisées sans supprimer la journalisation interne détaillée.
19. **Tentatives échouées et IP :** les compteurs métier par compte et par IP sont incrémentés dans le chemin du mot de passe incorrect d'un utilisateur actif et vérifié. Les comptes inexistants et certains états de compte sont traités avant cette incrémentation. La route web applique aussi `throttle:5,1`, mais la couverture de la limitation métier par IP et la résistance aux tentatives distribuées doivent être renforcées et testées.
20. **Gestion des tokens API :** les routes API visibles exposent un test d'authentification, les endpoints de réinitialisation et la consultation des événements d'audit ; aucune route de création, liste, révocation individuelle ou révocation globale des tokens n'a été repérée. `config/sanctum.php` définit `expiration => null` ; la durée maximale de 30 jours n'est pas démontrée par une couche de création de tokens. Aucun nettoyage planifié des tokens expirés n'a été repéré dans `routes/console.php` ni dans la configuration de planification examinée.
21. **Déconnexion globale :** les routes de session visibles permettent la révocation individuelle et root peut révoquer une session d'un autre utilisateur. Aucun endpoint explicite de déconnexion globale personnelle ni d'administration globale des tokens n'a été repéré dans les routes examinées.

### 20.6 Couverture des tests observée

- Le test Feature `UserManagementControllerTest` couvre de nombreux cas de lecture, permissions, création, activation, statuts, suppression logique et invitations.
- Le test Feature root couvre notamment la modification de profil/rôle par root et le changement d'e-mail par root.
- Le test `UserInvitationServiceTest` couvre l'invalidation d'une ancienne invitation, l'acceptation, les statuts et l'échec d'envoi.
- Aucun test dédié au changement d'e-mail par le propriétaire, au délai de vérification de 24 heures, à la modification de rôle par super_admin, au transfert de coopérative principale par super_admin, aux alertes de nouvel appareil ou à l'OTP par pays inhabituel n'a été repéré dans les fichiers de tests examinés.
- Les tests n'ont pas été exécutés dans cet audit GitHub ; les résultats restent à confirmer sur l'environnement local.

**Statut de cette étape :** constats documentés ; audit Identity toujours ouvert. Aucun code applicatif modifié.


### 20.7 Compléments — politique de mot de passe, profil et sessions

22. **Politique de mot de passe réellement implémentée :** `PasswordPolicy` impose 12 à 128 caractères, majuscule, minuscule et chiffre, mais interdit les espaces et n'exige pas de caractère spécial. Aucun contrôle de mot de passe compromis/courant ni historique des cinq derniers mots de passe n'est implémenté. Le comportement ne correspond donc pas à la politique validée (espaces et Unicode autorisés, caractère spécial obligatoire, rejet des mots de passe compromis/communs, historique de cinq mots de passe).
23. **Changement volontaire et forcé :** `PasswordService::changePassword()` révoque les sessions via `SessionService`, mais ne révoque pas les tokens API. Le contrôleur de changement forcé `ForcedPasswordChangeController::update()` met à jour le mot de passe et `must_change_password`, mais ne révoque ni sessions ni tokens. Le changement volontaire n'a pas de route PUT dédiée repérée ; la route `security.password.edit` ne sert que le formulaire GET, tandis que la route PUT disponible est le parcours `password.force-change`.
24. **Reset de mot de passe — statut et tokens :** `PasswordResetService::validateToken()` et `resetPassword()` ne vérifient pas que le compte est actif. Le service crée le token avec une expiration de 60 minutes et ne révoque pas les anciens tokens non consommés à la création d'un nouveau. Après reset, il révoque les sessions mais pas les tokens API. Le parcours ne vérifie pas l'historique des cinq derniers mots de passe.
25. **Profil personnel — schéma réel :** toutes les migrations présentes sur la branche ont été listées. Aucune migration n'ajoute les champs d'adresse personnelle, photo, ou préférences de notification. La table `users` initiale contient notamment nom/prénom, nom affiché, e-mail, téléphone, mot de passe, statut, locale, fuseau horaire et vérification e-mail ; le modèle `User` ne déclare pas non plus de champs de profil additionnels.
26. **Sessions — expiration et rôles :** `EnsureActiveAuthenticationSession` vérifie l'autorisation et l'existence de la session, puis met à jour `last_activity_at`. Il n'applique aucun délai d'inactivité par rôle (15/30/30/60 minutes) et n'affiche aucun avertissement deux minutes avant expiration. La configuration Laravel `expire_on_close` ne fournit pas le comportement différencié demandé selon le rôle.
27. **Sessions — révocation globale :** `SessionService::revokeForUser()` permet techniquement de révoquer toutes les sessions d'un utilisateur. Cependant, aucune route personnelle dédiée à la déconnexion globale n'a été identifiée ; les routes exposées permettent la révocation d'une session choisie et la révocation par root d'une session d'un autre utilisateur.
28. **Protection contre les tentatives de connexion :** `AuthenticationSecurityService` compte cinq échecs par adresse e-mail et cinq échecs par IP sur 900 secondes. Toutefois, `AuthenticationService` n'incrémente ces compteurs qu'après un mauvais mot de passe pour un compte actif et vérifié. Les e-mails inconnus, les comptes non actifs et les e-mails non vérifiés sont journalisés mais ne contribuent pas aux compteurs de blocage. La limitation de route `throttle:5,1` ne remplace pas le mécanisme métier attendu par compte et IP. Il faut aussi éviter qu'une connexion réussie depuis une IP partagée efface le compteur IP alimenté par d'autres utilisateurs.
29. **Réponses d'authentification :** le contrôleur renvoie le message des exceptions RuntimeException, et le service distingue compte inexistant, compte inactif, e-mail non vérifié, IP verrouillée et mauvais mot de passe. Cela expose des différences observables côté client, contrairement à la réponse générique validée. Conserver le détail dans les journaux internes, mais uniformiser la réponse publique.
30. **Journaux d'audit — distinction nécessaire :** `AuditEventPolicy` autorise les rôles disposant de `audit.view` à lire les événements dans leur périmètre de coopérative ; `AuditEventController` applique ce filtrage. Ce mécanisme concerne les événements d'audit métier généraux. Il ne démontre pas l'existence du journal dédié aux consultations de fiches personnelles, dont la lecture a été réservée à `super_admin_root`. Ne pas confondre ces deux catégories.
31. **Tests :** aucun test de politique de mot de passe, d'historique, de réinitialisation de mot de passe, d'expiration par rôle, de révocation API après changement/reset, de profil personnel ou de réponse générique de connexion n'a été identifié dans les tests Identity présents. Le seul test de changement de mot de passe actuellement visible couvre le parcours forcé et ne prouve pas la révocation des sessions/tokens. Les tests n'ont pas été exécutés pendant l'audit.

**Statut :** les constats de cette section sont issus de l'inspection des fichiers et migrations présents sur `feature/Identity`. Aucune modification du code applicatif ; les tests restent à exécuter localement.


### 20.8 Compléments — audit, journaux de sécurité et sessions

32. **Audit append-only :** `AuditEvent::performUpdate()` et `performDeleteOnModel()` empêchent les modifications/suppressions via Eloquent. `AuditService` filtre récursivement plusieurs clés sensibles. En revanche, la migration `audit_events` ne contient aucun hash d'événement, hash précédent, signature ou champ d'intégrité ; aucun service de chaîne de hachage ni contrôle périodique d'intégrité n'a été repéré. L'append-only applicatif seul ne satisfait donc pas les garanties d'intégrité cryptographique, de détection de falsification et de vérification périodique validées.
33. **Journal des consultations personnelles :** les méthodes `show()` des contrôleurs utilisateurs (back-office et root) chargent la fiche après autorisation, sans écrire d'événement dédié de consultation. Aucun journal spécialisé ne permet de vérifier acteur, utilisateur cible, date/heure, coopérative, origine UI/API et résultat autorisé/refusé. La règle de consultation silencieuse vis-à-vis de l'utilisateur n'est donc pas encore démontrée ; le journal dédié, consultable uniquement par `super_admin_root`, reste à implémenter.
34. **Séparation des journaux :** `AuditEventController` expose les événements d'audit généraux aux utilisateurs disposant de `audit.view`, filtrés par coopérative ; `AuditEventPolicy` autorise aussi les rôles coopératifs selon leurs permissions. Il s'agit du journal d'audit général, pas du journal confidentiel des consultations de fiches personnelles. Une catégorie dédiée et une politique strictement root-only sont nécessaires pour éviter toute exposition croisée.
35. **Événements de sécurité :** `SecurityEventService` sait créer des événements de sécurité avec utilisateur, IP, agent utilisateur, navigateur, appareil, métadonnées et date. Toutefois, aucune politique, route de consultation, filtre d'accès root-only, export ni règle de rétention dédiée n'a été identifiée pour cette table. L'existence du service ne prouve pas le cycle complet de supervision de sécurité validé.
36. **Révocation des sessions :** `SessionService::revokeForUser()` révoque toutes les sessions actives une par une et journalise chaque révocation ; le mécanisme existe côté service. En revanche, les routes exposées ne proposent que la révocation d'une session individuelle par l'utilisateur et par root. L'action globale personnelle, sa confirmation et son test HTTP restent absents.
37. **Sessions multi-appareils :** le contrôleur permet de consulter les sessions actives et de révoquer une session individuelle ; les tests couvrent l'isolation entre utilisateurs et la révocation individuelle. Ils ne couvrent pas les délais d'inactivité par rôle, la persistance différenciée des cookies, l'avertissement deux minutes avant expiration, ni la déconnexion globale.
38. **Notifications de sécurité :** les notifications trouvées concernent l'invitation et la réinitialisation du mot de passe. Aucun flux de notification pour nouvel appareil, pays inhabituel, OTP, révocation d'accès, changement de profil ou événement de sécurité critique n'a été identifié. Aucun centre de préférences/historique de notifications ni canal multilingue FR/EN/AR n'a été repéré dans le domaine Identity.
39. **Export et rétention des journaux :** aucune implémentation d'export PDF/CSV/JSON, de lien temporaire signé de 15 minutes, de suppression du fichier après 24 heures, ni de politique de purge différenciée (2 ans / 5 ans / 90 jours selon catégorie) n'a été repérée dans les composants Audit/Identity inspectés. Les exigences de rétention et de sauvegarde inviolable doivent être intégrées à la conception de correction.
40. **Tests d'audit et sécurité :** les tests présents couvrent la révocation individuelle et les permissions générales de consultation des événements, mais aucun test spécifique n'a été identifié pour le journal de consultation personnelle root-only, la chaîne d'intégrité, le contrôle périodique, les exports sécurisés, la rétention, les notifications de sécurité ou la déconnexion globale.
41. **Portée des preuves :** ces constats décrivent les fichiers présents sur `feature/Identity`. Les migrations n'ont pas été exécutées et les tests n'ont pas été lancés pendant l'audit GitHub ; aucun statut de test local n'est présumé.

**Statut :** contrôle des composants Audit/Identity terminé pour cette passe documentaire. L'audit fonctionnel et technique Identity peut maintenant être consolidé en matrice d'écarts et plan de correction, sans commencer les modifications de code.


### 20.9 Décisions complémentaires — politique et historique des mots de passe

**Décisions confirmées le 8 novembre 2026 :**
1. La politique renforcée ID-01 décrite à la section 8 s'applique à tous les rôles et à tous les parcours de définition ou de remplacement d'un mot de passe.
2. L'historique ID-02 interdit le mot de passe actuel et les cinq anciens mots de passe.
3. Seuls cinq anciens hashes sont conservés, en plus du hash courant.
4. L'ancien hash courant est ajouté à l'historique uniquement dans la transaction d'une opération réussie. Toute opération annulée ou échouée ne laisse aucune entrée.
5. Lorsqu'un sixième ancien hash est ajouté, le plus ancien est supprimé automatiquement.
6. Les mêmes contrôles doivent être appliqués aux changements volontaires/forcés, réinitialisations et acceptations d'invitation/activation.

**État du dépôt au début de la correction :**
- PasswordPolicy impose la longueur, la majuscule, la minuscule et le chiffre, mais rejette les espaces et n'exige pas de caractère spécial.
- Aucun contrôle de données personnelles, de mots de passe courants/compromis ou de liste locale versionnée n'a été identifié dans les fichiers examinés.
- Aucun modèle, service ou migration d'historique des mots de passe n'a été identifié.
- PasswordService, PasswordResetService, ForcedPasswordChangeController et UserInvitationService définissent ou remplacent un mot de passe sans contrôle commun complet de l'historique.
- Le reset reste configuré à 60 minutes, n'invalide pas les anciens liens et ne vérifie pas l'état actif du compte.
- Les tests de politique et d'historique n'ont pas été identifiés dans l'arborescence examinée et aucun test n'a été exécuté à distance.

**Plan de correction initial :**
- [ ] Ajouter une migration append-only pour l'historique des mots de passe.
- [ ] Ajouter un modèle/service d'historique avec transactions, verrouillage et limite stricte de cinq anciens hashes.
- [ ] Centraliser les contrôles de politique et d'historique pour tous les flux de mot de passe.
- [ ] Corriger l'expiration, l'invalidation et l'éligibilité des tokens de réinitialisation.
- [ ] Révoquer les sessions et tokens API dans tous les flux où la décision l'exige.
- [ ] Concevoir et implémenter le contrôle de mots de passe courants/compromis, sa liste locale versionnée et son mécanisme de mise à jour sécurisé.
- [ ] Ajouter les tests unitaires, service, HTTP et de concurrence ; exécuter les tests localement.

**État :** décisions validées ; audit des fichiers ciblés effectué ; implémentation à commencer par lots.

### 20.10 Suivi de la première tranche d'implémentation — 8 novembre 2026

**Branche :** feature/Identity

| Élément | État | Preuve / remarque |
|---|---|---|
| Migration `user_password_histories` | Code ajouté — migration non exécutée | `2026_11_08_000001_create_user_password_histories_table.php` |
| Service d'historique | Code ajouté — tests à exécuter | `PasswordHistoryService`, limite à cinq hashes précédents |
| Politique de complexité Unicode | Code ajouté — tests à exécuter | Majuscule/minuscule/chiffre/spécial ; espaces et séparateurs Unicode autorisés |
| Détection d'informations personnelles | Code ajouté — tests à exécuter | Prénom, nom complet, fragments e-mail suffisamment longs et identifiant exact |
| Changement volontaire | Intégration ajoutée — test à exécuter | Vérification historique, archivage transactionnel, révocation sessions et tokens API dans `PasswordService` |
| Changement forcé | Intégration ajoutée — test à exécuter | Historique, archivage, révocation sessions et tokens API |
| Acceptation d'invitation | Intégration ajoutée — test à exécuter | Contrôle contextualisé et archivage de l'ancien hash dans la transaction |
| Réinitialisation | Corrections ajoutées — tests à exécuter | 30 minutes, invalidation des anciens liens, compte actif uniquement, historique et révocation sessions/tokens |
| Réponse publique reset | Correction ajoutée — test à exécuter | Les comptes non actifs ne déclenchent pas l'envoi ; réponse générique conservée |
| Tests de politique | Fichiers ajoutés — non exécutés | `PasswordPolicyTest` |
| Tests d'historique | Fichiers ajoutés — non exécutés | `PasswordHistoryServiceTest` |
| Vérification CI / suite complète | Non vérifiée | L'environnement GitHub utilisé pour cette modification ne permet pas d'exécuter PHPUnit ou les migrations du projet. |

**Commits de cette tranche :**
- Documentation des décisions : `26b4e14e1174ca8f5870e5973499f13d21aa252e`
- Politique et contrôles : commits `c7fd2af`, `9f4fd35`, `4f331ab`, `e102806`
- Historique et intégrations : commits `c921933`, `b5d25c9`, `c24eb91`, `1c6bdf1`, `044e41e`, `13dd1c9`, `0ec9ae7`, `d9aae27`, `78a9f90`, `a070e2e`
- Tests : commits `68ed2fa`, `4e81517`, `2d8cce0`, `5b77e1a`, `3bdd0b2`

**Limites restantes avant de considérer ID-01/ID-02 terminés :**
- [ ] Vérifier et corriger les flux de création/activation manuelle qui ne passent pas encore par une validation complète du mot de passe final.
- [ ] Implémenter la vérification des mots de passe courants/compromis avec k-anonymity, liste locale versionnée, intégrité, mise à jour planifiée et retour arrière.
- [ ] Ajouter les alertes root et le tableau de bord de gestion de la liste de mots de passe compromis.
- [ ] Vérifier le contrôle des mots de passe des comptes existants à la prochaine authentification réussie et le changement obligatoire avant dashboard.
- [ ] Ajouter la notification e-mail correspondante et vérifier le verrouillage des routes pendant le changement obligatoire.
- [ ] Compléter les tests de réutilisation, de rotation du cinquième/sixième hash, d'échec/rollback, de reset, d'invitation et de révocation des sessions/tokens.
- [ ] Exécuter localement la migration, les tests ciblés, puis toute la suite Identity ; enregistrer les résultats et le commit de validation.

**Important :** « Code ajouté » ne signifie ni migration exécutée, ni test vert, ni validation de production. La mise en œuvre de la politique ID-01 reste partielle jusqu'à réalisation des limites restantes.


### 20.11 Correction après retour des tests locaux — 8 novembre 2026

**Retour utilisateur :** plusieurs cas de `Tests\\Framework\\Domains\\Identity\\Password\\Rules\\PasswordPolicyTest` échouaient car les fixtures utilisaient des mots de passe sans caractère spécial. La nouvelle politique exige bien un caractère spécial ; c'est donc le jeu de données des tests qui était obsolète, et non la règle métier.

**Correction commitée :**
- Fichier : `tests/Framework/Domains/Identity/Password/Rules/PasswordPolicyTest.php`
- Commit : `e613ed5828b0fbe4472f6186188ae7936d474f20`
- Fixtures alignées sur la politique : caractère spécial obligatoire, espaces autorisés, bornes 12 et 128 caractères, et mots de passe distincts dans le test de changement.
- Le test historique `test_special_character_is_optional` a été remplacé par un test vérifiant explicitement que l'absence de caractère spécial est refusée.
- Le test interdisant les espaces a été remplacé par un test qui confirme que les espaces restent autorisés.

**État après correction :** l'utilisateur confirme que `php artisan test --filter=PasswordPolicyTest` est vert. Le test ciblé est donc validé selon le retour d'exécution local.

**Validation utilisateur :**
- [x] Résultat vert confirmé pour `php artisan test --filter=PasswordPolicyTest`.
- [ ] Conserver le résultat détaillé de PHPUnit dans le suivi local/CI si nécessaire.
- [ ] Continuer avec les tests de l'historique des mots de passe ; ne pas extrapoler ce résultat aux autres suites.


### 20.12 Validation locale — PasswordPolicyTest

**Retour utilisateur :** test vert confirmé après récupération de la correction des fixtures.

- [x] `php artisan test --filter=PasswordPolicyTest` — **VERT**, selon confirmation utilisateur.
- [ ] `php artisan test --filter=PasswordHistoryServiceTest`
- [ ] `php artisan test --filter=ForcedPasswordChangeControllerTest`
- [ ] `php artisan test --filter=UserInvitationServiceTest`
- [ ] Tests de réinitialisation du mot de passe.
- [ ] Migration de l'historique puis tests associés.
- [ ] Suite complète Identity.

**Portée de la validation :** seul `PasswordPolicyTest` est déclaré vert. Aucun autre test, migration ou parcours n'est considéré validé par cette confirmation.


### 20.13 Validation locale — PasswordHistoryServiceTest

**Retour utilisateur :** test vert confirmé.

- [x] `php artisan test --filter=PasswordHistoryServiceTest` — **VERT**, selon confirmation utilisateur.
- [ ] `php artisan test --filter=ForcedPasswordChangeControllerTest`
- [ ] `php artisan test --filter=UserInvitationServiceTest`
- [ ] Tests de réinitialisation du mot de passe.
- [ ] Migration de l'historique puis tests associés.
- [ ] Suite complète Identity.

**Portée :** cette confirmation valide uniquement le test ciblé du service d'historique ; elle ne prouve pas encore l'exécution de la migration ni la validation de tous les parcours d'écriture.


### 20.14 Validation locale — ForcedPasswordChangeControllerTest

**Retour utilisateur :** test vert confirmé.

- [x] `php artisan test --filter=ForcedPasswordChangeControllerTest` — **VERT**, selon confirmation utilisateur.
- [ ] `php artisan test --filter=UserInvitationServiceTest`
- [ ] Tests de réinitialisation du mot de passe.
- [ ] Migration de l'historique puis tests associés.
- [ ] Suite complète Identity.

**Portée :** cette confirmation valide uniquement la suite ciblée du contrôleur de changement forcé. Les autres parcours et la migration restent à valider séparément.


### 20.15 Validation locale — UserInvitationServiceTest

**Retour utilisateur :** test vert confirmé.

- [x] `php artisan test --filter=UserInvitationServiceTest` — **VERT**, selon confirmation utilisateur.
- [ ] Tests de réinitialisation du mot de passe.
- [ ] Exécuter la migration de l'historique sur la base locale de test.
- [ ] Vérifier les tests d'intégration/HTTP des parcours de changement, invitation et reset.
- [ ] Exécuter la suite complète Identity.

**Portée :** cette confirmation valide uniquement la suite ciblée du service d'invitation. Elle ne valide pas encore les autres flux ni la migration.


### 20.16 Validation locale — PasswordResetServiceTest

**Retour utilisateur :** test vert confirmé après correction des fixtures.

- [x] `php artisan test --filter=PasswordResetServiceTest` — **VERT**, selon confirmation utilisateur.
- [x] Correction des fixtures du test pour respecter la politique : mots de passe conformes (12 caractères minimum, majuscule, minuscule, chiffre et caractère spécial).
- [x] Correction du message attendu pour la réutilisation : le service applique l'interdiction du mot de passe courant et des cinq précédents.
- [ ] Exécuter la migration de l'historique sur la base locale de test.
- [ ] Vérifier les tests d'intégration/HTTP des parcours de changement, invitation et reset.
- [ ] Exécuter la suite complète Identity.

**Commit de correction des tests :** `c14d145adc550e8073c8770239ad545231779a11`.

**Portée :** le test ciblé de reset est vert selon le retour utilisateur. Cela ne valide pas encore la migration, la suite complète Identity, ni l'ensemble des scénarios d'intégration.


## 20.17 — Migration de l'historique des mots de passe exécutée

Le 8 novembre 2026, l'utilisateur a confirmé l'exécution locale de :

```bash
php artisan migrate
```

Résultat Laravel :

```
2026_11_08_000001_create_user_password_histories_table ...... 229.90ms DONE
```

La migration `2026_11_08_000001_create_user_password_histories_table` est donc **exécutée avec succès sur la base locale**. Cette preuve confirme l'application de la migration, mais ne valide pas à elle seule l'intégrité complète du schéma, les parcours métier, l'atomicité ou la concurrence.

**Prochaine étape :** exécuter les tests d'intégration liés à l'historique et aux changements/réinitialisations de mot de passe, puis la suite complète Identity. Ne marquer ces validations comme vertes qu'après confirmation des résultats.


## 20.18 — Tests de l'historique des mots de passe

Le 8 novembre 2026, l'utilisateur a confirmé que la commande suivante est **verte** :

```bash
php artisan test --filter=PasswordHistory
```

Cette validation confirme que les tests correspondant au filtre `PasswordHistory` passent dans l'environnement local. Elle ne remplace pas les tests HTTP de bout en bout, les contrôles de concurrence ni l'exécution de toute la suite Identity.

**Prochaine étape :** exécuter les tests HTTP/intégration liés aux changements volontaires et forcés de mot de passe ainsi qu'à la réinitialisation, puis lancer la suite complète Identity.


## 20.19 — Échecs révélés par le filtre Password et correction des fixtures

Le 8 novembre 2026, l'exécution élargie `php artisan test --filter=Password` a révélé :

- des fixtures de tests utilisant des mots de passe sans caractère spécial, incompatibles avec la politique ID-01 déjà validée ;
- un échec distinct dans `UserManagementControllerTest` : `Call to a member function all() on array`, signalé au moment de l'assertion de redirection du parcours d'activation root avec changement forcé.

Corrections apportées aux seules fixtures de tests (aucun assouplissement de la politique applicative) :
- `UserManagementControllerTest.php` : `NewSecurePassword2026!` ;
- `PasswordResetControllerTest.php` : `NewPassword123!` ;
- `ResetPasswordRequestTest.php` : `ValidPassword123!` et `DifferentPassword123!`.

Commits des trois corrections :
- `7f67460e0a31c0ff14c42b28060241faf7dd5830`
- `f9fec3607000d1dd56938577aba71a23ec027742`
- `851f842d20315cfafd3b7275c4583064073d69d3`

**État :** corrections poussées, mais non encore confirmées vertes par exécution locale. L'erreur `all() on array` reste à isoler ; ne pas la considérer comme corrigée par les changements de fixtures.


## 20.20 — Validation HTTP du UserManagementController

Le 8 novembre 2026, l'utilisateur a confirmé que le test ciblé du contrôleur de gestion des utilisateurs est **VERT** après récupération des corrections de fixtures :

```bash
php artisan test --filter=UserManagementControllerTest
```

Cette confirmation valide la suite ciblée `UserManagementControllerTest` dans l'environnement local. L'erreur précédemment signalée `Call to a member function all() on array` n'est donc plus bloquante dans cette exécution ciblée.

Les corrections de fixtures des commits suivants sont désormais couvertes par ce retour de test vert :
- `7f67460e0a31c0ff14c42b28060241faf7dd5830` — mot de passe conforme dans `UserManagementControllerTest.php` ;
- `f9fec3607000d1dd56938577aba71a23ec027742` — fixtures de `PasswordResetControllerTest.php` ;
- `851f842d20315cfafd3b7275c4583064073d69d3` — fixtures de `ResetPasswordRequestTest.php`.

**Limite de validation :** ce résultat ne confirme pas encore que le filtre global `Password` est entièrement vert, ni que toute la suite Identity passe. Les tests de reset HTTP, les tests d'intégration/concurrence et la suite complète restent à exécuter séparément.


## 20.21 — Diagnostic du filtre Password : fixtures de PasswordServiceTest

Après la confirmation verte de `UserManagementControllerTest`, l'utilisateur a relancé :

```bash
php artisan test --filter=Password
```

Le filtre échoue encore avec plusieurs exceptions :

`InvalidArgumentException: Password must contain at least one special character.`

L'audit du dépôt `feature/Identity` a identifié une cause concrète dans :

`tests/Framework/Domains/Identity/Password/Services/PasswordServiceTest.php`

Plusieurs tests de changement de mot de passe fournissent comme nouveau mot de passe `MahlinePassword13`, sans caractère spécial, alors que la règle ID-01 exige une majuscule, une minuscule, un chiffre et un caractère spécial. La correction des fixtures de ce fichier doit utiliser un mot de passe conforme, par exemple `MahlinePassword13!`, pour les scénarios qui attendent une réussite.

Un test supplémentaire doit être corrigé sur le plan fonctionnel : `test_change_password_rejects_password_with_spaces` affirme que les espaces sont interdits, alors que la décision ID-01 autorise explicitement les espaces et caractères Unicode. Son scénario doit devenir un test d'acceptation d'un mot de passe contenant des espaces et un caractère spécial, par exemple `Mahline Password13!`.

**Correction appliquée le 9 novembre 2026 :**
- Tous les scénarios de réussite et les scénarios où l'erreur attendue porte sur le mot de passe courant utilisent désormais `MahlinePassword13!`, conforme à ID-01.
- Le scénario qui rejetait les espaces a été remplacé par `test_change_password_accepts_password_with_spaces`, qui vérifie qu'un mot de passe valide contenant un espace (`Mahline Password13!`) est accepté et correctement haché.
- Aucun changement n'a été apporté à `PasswordPolicy.php` ni au comportement applicatif.

**Commit :** `8a31970f8d983df7926f3a13d696903edd0471b3`.

**Vérification du dépôt :** le fichier a été relu après commit ; aucune occurrence de `MahlinePassword13` sans caractère spécial ne subsiste dans ce test, le nouveau test d'acceptation des espaces est présent.

**État des tests :** l'utilisateur a confirmé le 9 novembre 2026 que les deux commandes suivantes sont vertes : `php artisan test --filter=PasswordServiceTest` et `php artisan test --filter=Password`.


## 20.22 — Correction des tests PasswordService

Le 9 novembre 2026, après accord de l'utilisateur, le fichier `PasswordServiceTest.php` a été corrigé sur `feature/Identity` selon la politique ID-01 : fixtures conformes avec caractère spécial et test d'acceptation des espaces. La politique applicative n'a pas été modifiée.

Commit : `8a31970f8d983df7926f3a13d696903edd0471b3`.

La correction est commitée et vérifiée sur GitHub. Le 9 novembre 2026, l'utilisateur a confirmé vertes les deux commandes : `php artisan test --filter=PasswordServiceTest` et `php artisan test --filter=Password`. Le filtre global Password est donc validé dans l'environnement local.


## 20.23 — Validation locale des tests Password

Le 9 novembre 2026, l'utilisateur a confirmé que les deux commandes suivantes sont **VERTES** dans son environnement local :

```bash
php artisan test --filter=PasswordServiceTest
php artisan test --filter=Password
```

Cette validation confirme le test ciblé du service et l'ensemble des tests sélectionnés par le filtre global `Password`. Elle ne vaut pas validation de toute la suite Identity, ni des tests non sélectionnés par ce filtre.

**Prochaine étape :** poursuivre les tests d'intégration/HTTP et exécuter la suite complète du domaine Identity.


## 20.24 — Validation du contrôleur HTTP de réinitialisation

Le 9 novembre 2026, l'utilisateur a confirmé que le test ciblé suivant est **VERT** :

```bash
php artisan test --filter=PasswordResetControllerTest
```

Cette validation couvre uniquement les tests sélectionnés par `PasswordResetControllerTest` dans l'environnement local. Elle ne vaut pas validation de l'ensemble des parcours Identity ni de la suite complète.

**Prochaine étape :** poursuivre les tests HTTP/intégration ciblés restants, puis exécuter la suite complète du domaine Identity.


## 20.25 — Validation des sessions personnelles et des invitations HTTP

Le 9 novembre 2026, l'utilisateur a confirmé **VERTS** les trois tests HTTP suivants après corrections sur `feature/Identity` :

```bash
php artisan test --filter=AuthenticationSessionControllerTest
php artisan test --filter=UserInvitationControllerTest
php artisan test --filter=UserInvitationThrottleTest
```

Corrections associées :

- `routes/web.php` : déplacement des routes de gestion des sessions personnelles dans un groupe dédié autorisant `super_admin`, `cooperative_admin` et `user`, tout en conservant `auth` et `EnsureActiveAuthenticationSession`. Les routes d'administration restent dans le groupe back-office réservé à `super_admin` et `cooperative_admin`. Le contrôleur conserve le contrôle de propriété des sessions.
- `UserInvitationControllerTest.php` : fixtures de mots de passe valides mises en conformité avec ID-01 ; le scénario de confirmation différente conserve deux valeurs distinctes et conformes ; le scénario `weakpassword` reste volontairement invalide.
- `UserInvitationThrottleTest.php` : fixture conforme à ID-01 afin que les requêtes atteignent effectivement la validation du token et le middleware de limitation.

Commits de correction :
- Routes de sessions personnelles : `8ab1be9d1dd5c054e6e0e688fb18e07c37f94e32`.
- Tests d'invitation : `4cc1c74e99aa63dfe0258ab32bf72db72b0dd784`.
- Test de limitation des invitations : `4a7d2028ec03b26224efe70c5afc90c02b07abb7`.

La validation est limitée à ces trois suites ciblées. Elle ne vaut pas validation de toute la suite Identity. La suite complète et les contrôles de style/analyse statique restent à exécuter.

## Validation globale du 9 novembre 2026

- Exécution complète confirmée par l'utilisateur : `php artisan test`.
- Résultat : **1 189 tests réussis, 3 147 assertions réussies**.
- Contrôle de style confirmé par l'utilisateur : `php vendor/bin/pint --test`.
- Résultat Pint : **351 fichiers passés**, aucune correction de formatage restante signalée.
- Les corrections de fixtures/autorisation des tests Catalog, Categories et Products sont couvertes par cette exécution globale.
- Cette validation atteste le résultat de tests et de style dans l'environnement local de l'utilisateur ; elle ne remplace pas l'audit final du diff ni les vérifications CI éventuelles.


## 20.26 — Activation manuelle root et changement obligatoire du mot de passe

Le parcours a été implémenté sur `feature/Identity` selon le périmètre validé : activation manuelle par `super_admin_root` uniquement. Le mot de passe temporaire reste `MahlineMahline` pour le moment et est communiqué manuellement ; aucun envoi automatique par e-mail ni appel vocal automatisé n'est ajouté.

Le garde-fou bloque les accès métier web pendant que `must_change_password` est actif et protège également les routes API authentifiées actuellement déclarées. Seules les routes de changement obligatoire et de déconnexion restent accessibles. Le parcours d'invitation et le changement volontaire ne sont pas modifiés.

Après le changement obligatoire réussi :
- la session web qui réalise l'opération est conservée ;
- les autres sessions Identity actives sont révoquées ;
- les tokens API existants sont supprimés ;
- l'utilisateur peut accéder à son espace selon son rôle et ses permissions.

Fichiers/éléments concernés :
- `EnsureForcedPasswordChangeCompleted` : garde-fou d'accès pendant le changement obligatoire ;
- `bootstrap/app.php` : application du garde-fou au groupe web ;
- `routes/api.php` : protection des routes API authentifiées présentes ;
- `SessionService::revokeOtherSessions()` : révocation des autres sessions sans fermer la session courante ;
- `ForcedPasswordChangeController` : conservation de la session courante uniquement pour le changement forcé ; comportement volontaire conservé ;
- `ForcedPasswordChangeControllerTest` : tests de blocage des routes métier/API et de conservation de la session courante, révocation des autres sessions et suppression des tokens API.

Commits de la séquence :
- `0761f879299ee978816ee293b6bbd4b089a5e952` — middleware de garde-fou ;
- `ed02919e484cfd77d909f1cdaa30fde7296a25d2` — révocation des autres sessions ;
- `c86ff3ace34bfe07aefc8b2eb4698acab1705b80` — application au groupe web ;
- `825235b6847911f398ab7d9ac62562f193d37c39` — protection des routes API existantes ;
- `73454a9086a2aee4127172167a92b9314acf9d10` — tests de blocage web/API ;
- `5cfb7e61305ff1ce4f27fddbb5410165b9037cd3` — correction du contrôleur ;
- `8b2286f1ba6945186aea459d81f39ad9175aa985` — tests de régression de session.

**Validation locale confirmée par l'utilisateur le 9 novembre 2026 :**
```bash
php artisan test --filter=ForcedPasswordChangeControllerTest
php vendor/bin/pint --test
php artisan test
```
Les trois commandes sont confirmées vertes. Aucun nombre de tests/assertions n'est indiqué ici, car le résultat communiqué pour cette exécution complète ne précisait pas de décompte.

**État :** implémentation et validation locale confirmées. L'audit final du diff et les vérifications CI éventuelles restent distincts de ces résultats.


## Validation du verrouillage concurrent — ForcedPasswordChangeController

Le contrôle de concurrence de la modification du mot de passe a été corrigé et validé localement par l'utilisateur le 9 novembre 2026.

- Les validations contextuelles `PasswordPolicy::validate()` et `PasswordPolicy::validateChange()` sont désormais exécutées à l'intérieur de la transaction.
- Elles sont exécutées après `lockForUpdate()` et les contrôles d'état du compte.
- Elles utilisent l'instance fraîchement verrouillée `$lockedUser`, et non l'instance utilisateur chargée avant la transaction.
- Les exceptions `InvalidArgumentException` sont toujours converties en erreurs de validation affichées à l'utilisateur.
- L'historique, la révocation des sessions, la suppression des tokens API, la journalisation et la distinction entre changement forcé et volontaire sont conservés.

**Validation locale confirmée par l'utilisateur :**
- `php artisan test --filter=ForcedPasswordChangeControllerTest` — VERT.
- `php vendor/bin/pint --test` — VERT.

**État :** correction appliquée dans le fichier local et tests ciblés/style confirmés verts par l'utilisateur. Le SHA du commit applicatif n'est pas enregistré dans ce suivi.


## 20.27 — Tests de rejet des informations personnelles dans les mots de passe

Des tests complémentaires ont été ajoutés à `tests/Framework/Domains/Identity/Password/Rules/PasswordPolicyTest.php` afin de couvrir le contrôle des informations personnelles déjà implémenté dans `PasswordPolicy`.

Couverture ajoutée :
- rejet du prénom ;
- rejet du nom complet ;
- rejet de la partie locale de l'adresse e-mail ;
- rejet d'un fragment reconnaissable de l'adresse e-mail ;
- détection insensible à la casse et aux séparateurs ;
- acceptation d'un mot de passe ne contenant pas d'information personnelle reconnaissable.

**Validation locale confirmée par l'utilisateur le 9 novembre 2026 :**
- `php artisan test --filter=PasswordPolicyTest` — VERT ;
- `php vendor/bin/pint --test` — VERT.

Commit des tests : `8814862caca5dae7c7e9e226e6dc766ed9cb74db`.

Aucune modification de la politique applicative n'a été faite dans cette étape. Les tests Unicode détaillés, les limites et les messages de validation restent à compléter dans le backlog.

**Décision de suivi :** le contrôle des mots de passe courants ou compromis (ID01-03) est reporté à une étape ultérieure à la demande de l'utilisateur. Il reste explicitement ouvert dans le backlog et ne doit pas être considéré comme implémenté ou validé.


## 20.28 — Couverture Unicode et messages de la politique de mot de passe

Le 9 novembre 2026, les tests de `tests/Framework/Domains/Identity/Password/Rules/PasswordPolicyTest.php` ont été complétés pour couvrir les comportements Unicode et les messages exacts des erreurs de `PasswordPolicy`.

Couverture ajoutée :
- acceptation des majuscules et minuscules accentuées ;
- acceptation des chiffres arabes Unicode ;
- acceptation d'un caractère spécial Unicode ;
- vérification que les lettres Unicode et les espaces ne satisfont pas l'exigence de caractère spécial ;
- vérification du calcul de longueur en caractères Unicode ;
- vérification exacte des messages pour longueur minimale/maximale, majuscule, minuscule, chiffre, caractère spécial, information personnelle et nouveau mot de passe identique à l'ancien.

Aucun code applicatif n'a été modifié.

**Commit des tests :** `45290bceefc84158f1158ecf7b626537e9682c43`.

**Validation locale confirmée par l'utilisateur le 9 novembre 2026 :**
- `php artisan test --filter=PasswordPolicyTest` — VERT ;
- `php vendor/bin/pint --test` — VERT.

**État :** ID01-17 est validé pour cette couverture. Le contrôle des mots de passe courants/compromis ID01-03 reste reporté à la demande de l'utilisateur.


## 20.29 — Atomicité de l'historique des mots de passe et rollback des parcours

Le 9 novembre 2026, l'utilisateur a confirmé **VERTS** les quatre suites de tests ciblées couvrant une panne de persistance du journal d'audit pendant une opération de changement de mot de passe :

```bash
php artisan test tests/Framework/Domains/Identity/Password/Services/PasswordServiceTest.php tests/Framework/Domains/Identity/Password/Services/PasswordResetServiceTest.php tests/Feature/Domains/Identity/Password/Http/Controllers/ForcedPasswordChangeControllerTest.php tests/Feature/Domains/Identity/UserManagement/Http/Controllers/UserInvitationControllerTest.php
php vendor/bin/pint --test
```

Les tests provoquent une exception lors de la création de l'événement d'audit, puis vérifient que la transaction annule les modifications déjà effectuées :
- changement volontaire : ancien mot de passe conservé, aucun historique ajouté et token API non révoqué ;
- reset : ancien mot de passe conservé, token de reset toujours inutilisé, aucun historique ajouté et token API non révoqué ;
- changement forcé : mot de passe temporaire conservé, obligation de changement maintenue, aucun historique ajouté et token API non révoqué ;
- acceptation d'invitation : compte toujours pending, e-mail non vérifié, ancien mot de passe conservé, invitation non consommée et aucun historique ajouté.

Aucun code applicatif n'a été modifié dans cette étape. Le service `PasswordHistoryService` documente que l'archivage doit être exécuté dans la même transaction que le remplacement du mot de passe ; les quatre tests de régression vérifient désormais le rollback en cas d'échec d'audit.

**Validation locale confirmée par l'utilisateur le 9 novembre 2026 :** les quatre suites ciblées et `php vendor/bin/pint --test` sont VERTS.

**État :** ID02-02 et la couverture rollback d'ID02-06 sont validés pour les quatre parcours testés. Les contrôles de concurrence, de réutilisation des cinq anciens mots de passe, de rotation, de compatibilité et de suppression restent à auditer.

## 20.30 — Vérification du verrouillage concurrent de l'utilisateur

Le test `PasswordServiceTest` a été complété sur `feature/Identity` pour vérifier deux propriétés :
- le service de changement de mot de passe émet une requête `SELECT ... FOR UPDATE` ciblant la ligne de l'utilisateur ;
- une transaction ouverte sur une première connexion MySQL empêche une deuxième connexion d'obtenir le verrou exclusif de cette même ligne avec `NOWAIT`.

**Validation locale confirmée par l'utilisateur le 9 novembre 2026 :**
- `php artisan test --filter=PasswordServiceTest` — VERT ;
- `php vendor/bin/pint --test` — VERT.

Cette couverture confirme l'émission du verrou par le service et le comportement de verrouillage InnoDB entre deux connexions. Elle ne prétend pas simuler deux requêtes applicatives complètes qui changent simultanément le même mot de passe ; ce scénario bout-en-bout reste une couverture complémentaire possible.

**Commit du test :** `ed0261c97ade2bdebc9320e9c3e7f395265a753c` (dernier commit du fichier de test).

**État :** ID02-03 validé pour le verrouillage du service et la contention MySQL. Les tests de réutilisation des cinq anciens mots de passe, rotation, application à tous les parcours, compatibilité et suppression restent à auditer.

## 20.31 — Refus de réutilisation et rotation de l'historique des mots de passe

Deux tests complémentaires ont été ajoutés dans `tests/Framework/Domains/Identity/Password/Services/PasswordServiceTest.php` :

- `test_password_change_rejects_each_of_the_five_previous_passwords` : après cinq changements successifs, chacun des cinq anciens mots de passe est refusé ; le mot de passe courant reste inchangé après les tentatives de réutilisation.
- `test_password_history_retains_only_the_five_most_recent_password_hashes` : après six changements, l'historique conserve exactement les cinq hashes les plus récents et élimine le plus ancien.

Aucun code applicatif n'a été modifié dans cette étape : le service d'historique existant est couvert par ces tests supplémentaires.

**Commit des tests :** `f4595f26502e0e74d4853fb0dbe09a5d70f6d11d`.

**Validation locale confirmée par l'utilisateur le 9 novembre 2026 :**
- `php artisan test --filter=PasswordServiceTest` — VERT ;
- `php vendor/bin/pint --test` — VERT.

**État :** ID02-04 et ID02-05 validés. La vérification de l'application commune de l'historique aux différents parcours, des comptes historiques et de la suppression physique reste à réaliser.


## 20.32 — Historique du mot de passe lors de l'activation manuelle root

L'audit a détecté que `UserManagementService::activateAndVerifyUser()` remplaçait le hash courant par `MahlineMahline` sans l'archiver. Cette opération échappait donc à la règle commune des changements de mot de passe.

Correction sur `feature/Identity` : injection du `PasswordHistoryService` dans `UserManagementService` et archivage du hash courant dans la transaction, avant l'affectation du mot de passe temporaire. Le comportement fonctionnel validé reste inchangé : activation par `super_admin_root`, statut actif, e-mail vérifié, `must_change_password=true`, mot de passe temporaire `MahlineMahline`, révocation des invitations et des anciens moyens d'accès, audit métier.

Test ajouté : `test_root_temporary_activation_archives_the_existing_password`, qui vérifie l'état activé, l'obligation de changement, le mot de passe temporaire et la présence/vérification de l'ancien hash dans `user_password_histories`.

Commits :
- `6b70f2065d6f1ba15f57640787bda0e8324ee30e` — correction du service ;
- `9f5d774293fcb86ac0a05cd52da53cca8fe48d25` — test de régression.

**Validation locale confirmée par l'utilisateur le 9 novembre 2026 :** `php artisan test --filter=UserManagementServiceTest` et `php vendor/bin/pint --test` — VERTS.

**État :** ID02-07 validé pour les cinq parcours de remplacement recensés. Les comptes sans hash courant, la compatibilité des comptes historiques et la suppression physique restent à examiner.


## 20.33 — Cas limites et cycle de vie de l'historique des mots de passe

Le 9 novembre 2026, l'utilisateur a confirmé **VERTS** les commandes locales :

```bash
php artisan test --filter=PasswordServiceTest
php artisan test --filter=UserManagementServiceTest
php vendor/bin/pint --test
```

Deux protections/tests ont été ajoutés sur `feature/Identity` :

- **Hash courant absent :** `PasswordHistoryService::archiveCurrentPassword()` refuse désormais d'archiver un hash nul ou vide et lève une `InvalidArgumentException` explicite. Test : `test_password_history_refuses_to_archive_an_empty_current_hash`.
- **Cycle de vie du compte :** `test_password_history_survives_soft_delete_and_cascades_on_force_delete` confirme que la suppression logique conserve l'historique et que la suppression physique exceptionnelle le supprime par cascade de la clé étrangère.

Commit du test de cycle de vie : `b231483c25ab1fba5dfa5020fd3e8f7fbb840017`.

**Limite de validation :** ces tests ne valident pas encore la compatibilité des anciens formats de hash ni le traitement des comptes historiques dont le hash aurait été créé par une ancienne version de l'application. Cet audit reste ouvert.


## 20.34 — Rehash des mots de passe obsolètes à la connexion

L'audit du parcours personnalisé `AuthenticationService` a montré que le projet utilise `Hash::check()` puis laisse le workflow effectuer la connexion. Dans ce parcours, le rehash automatique du fournisseur Laravel n'est pas déclenché.

Correction sur `feature/Identity` : après vérification réussie du mot de passe, `AuthenticationService` contrôle `hashing.rehash_on_login` et `Hash::needsRehash()`. Si les paramètres du hash sont obsolètes, il enregistre un nouveau hash du même mot de passe. Cette opération ne constitue pas un changement de mot de passe : elle n'ajoute donc aucune ligne à `user_password_histories`.

Tests ajoutés dans `AuthenticationServiceTest` :
- `test_successful_login_rehashes_an_outdated_password_hash` : un hash bcrypt de coût inférieur est recalculé avec la configuration courante ; le mot de passe reste vérifiable et aucun historique de changement n'est créé.
- `test_successful_login_does_not_rehash_when_rehash_on_login_is_disabled` : l'option désactivée préserve le hash existant.

Commits :
- `d8585f716ec2eb97be8705407ce5dfd5c8634af9` — rehash après connexion réussie ;
- `7d01d96f7950d0ea7748c09393cb14186bcaf68e` — tests de régression.

**Validation locale confirmée par l'utilisateur le 9 novembre 2026 :**
- `php artisan test --filter=AuthenticationServiceTest` — VERT ;
- `php vendor/bin/pint --test` — VERT.

**Décision explicite de l'utilisateur :** l'audit de compatibilité entre algorithmes bcrypt et Argon (et la migration inter-algorithmes des anciens hashes) est reporté à une étape ultérieure. Il reste inscrit au backlog sous ID02-12 et ne doit pas être entrepris dans l'audit courant.


## 20.35 — Harmonisation des réponses publiques d'authentification

Le 9 novembre 2026, l'utilisateur a confirmé **VERTS** les commandes locales :

```bash
php artisan test --filter=AuthenticationControllerTest
php artisan test --filter=AuthenticationServiceTest
php vendor/bin/pint --test
```

Le contrôleur de connexion renvoie désormais une réponse publique identique pour les échecs de connexion traités : statut HTTP `401` et message `Authentication failed.` pour les réponses JSON ; le formulaire web utilise le même message. Un compte inexistant ne se distingue plus par un `404`. Les raisons détaillées restent enregistrées dans `login_histories`. Les erreurs de validation des champs restent des réponses `422`.

Les tests HTTP couvrent les comptes inexistants, les mots de passe incorrects, les comptes inactifs, les e-mails non vérifiés et les comptes verrouillés, et vérifient l'uniformité de la réponse publique.

Commits : `ccb0a3d5fc24cf80b62d6c41a2562b8699e6d19e`, `d98ea4f41965c9e726fb73807c0bc1f5c3e7a1e3`, `97836aebf459b7fe182a3f0822407bdef5601aa3`, `ed274a592c8d364f6d91769c815160668e825341`.

**État :** AUTH-01 validé par tests ciblés et Pint. L'audit se poursuit sur AUTH-02, comptabilisation cohérente des tentatives échouées.


## 20.36 — Comptabilisation cohérente des échecs de connexion (AUTH-02)

L'audit du 9 novembre 2026 a révélé que le compteur de tentatives par adresse e-mail n'était incrémenté que pour un mot de passe incorrect sur un compte actif et vérifié. Les adresses inconnues, les comptes inactifs et les comptes dont l'e-mail n'est pas vérifié étaient journalisés sans consommer le compteur.

Correction sur `feature/Identity` :
- contrôle du verrouillage par e-mail avant les contrôles d'existence et d'état du compte ;
- incrément du compteur normalisé pour les adresses inconnues, les comptes inactifs et les comptes non vérifiés ;
- maintien de la règle selon laquelle une adresse déjà verrouillée n'incrémente pas davantage son compteur ;
- conservation des raisons internes détaillées dans `login_histories` et de la réponse publique générique validée par AUTH-01.

Tests ajoutés dans `AuthenticationServiceTest` :
- `test_failed_attempts_are_counted_for_unknown_inactive_and_unverified_emails` ;
- `test_locked_unknown_email_does_not_increment_failed_attempts`.

Commits :
- `9aaff962e0809e5582b3adef6a8baa78a4e182a2` — service ;
- `3627b143beea199c5cae380634c23c288369e5d9` — premiers tests ;
- `3d7f555f01130ffd7699fbc510585bf993871fa6` — simplification des captures d'exceptions dans les tests.

**État :** VALIDÉ le 9 novembre 2026 après confirmation utilisateur : `AuthenticationServiceTest`, `AuthenticationControllerTest` et `php vendor/bin/pint --test` verts.

**Correction après retour de test :** le contrôle du blocage IP a été déplacé avant le contrôle du blocage du compte. La décision existante est conservée : si les deux limites sont atteintes, `login_histories.reason` doit être `ip_locked`. Commit : `bfa963f7c0e47204fa4ede19230a609589bc826a`. Tests ciblés et Pint confirmés verts par l'utilisateur le 9 novembre 2026.


## 20.37 — Audit de la limitation des tentatives par compte et par IP (AUTH-03)

Audit et implémentation sur `feature/Identity`, le 9 novembre 2026. Les seuils ci-dessous ont été explicitement validés par l'utilisateur.

### Règles retenues et implémentées

| Niveau | Seuil | Fenêtre |
|---|---:|---:|
| Compte / e-mail normalisé | 5 échecs | 15 minutes |
| Adresse IP | 30 échecs | 15 minutes |
| Couple IP + e-mail normalisé | 5 échecs | 15 minutes |

### Modifications

- `AuthenticationSecurityService` centralise les trois compteurs via le cache Laravel et applique une durée de 900 secondes.
- Les clés de cache utilisent des empreintes HMAC-SHA-256 basées sur `app.key` : les adresses e-mail et IP brutes ne figurent pas dans les noms de clés.
- `AuthenticationService` vérifie les blocages dans cet ordre : IP, compte, couple IP + e-mail. La priorité historique `ip_locked` sur `account_locked` est conservée.
- Chaque échec d'authentification traité incrémente le compte, l'IP et le couple lorsqu'une IP est disponible : adresse inconnue, compte inactif, e-mail non vérifié et mot de passe incorrect.
- Une tentative déjà bloquée n'incrémente pas de nouveau le compteur correspondant.
- Après une connexion réussie, seuls le compteur du compte et celui du couple IP + e-mail sont effacés. Le compteur IP partagé reste intact.
- Le middleware de route `throttle:5,1` a été retiré de `authentication.login.store`. Ce middleware comptait aussi les connexions réussies et risquait de bloquer les réseaux partagés avant les contrôles métier. La limitation par domaine compte désormais uniquement les échecs traités.
- En production multi-serveurs, toutes les instances doivent partager le même backend de cache et la même valeur `APP_KEY`, condition nécessaire à la cohérence des compteurs.

### Tests ajoutés ou adaptés

Dans `AuthenticationServiceTest` :
- `test_all_processed_failure_types_increment_ip_and_ip_email_counters` ;
- `test_ip_email_counter_is_cleared_on_success_without_clearing_shared_ip_counter` ;
- `test_ip_lockout_threshold_is_thirty_failures` ;
- `test_ip_email_lockout_threshold_is_five_failures` ;
- `test_all_three_lockouts_expire_after_fifteen_minutes` ;
- adaptation des tests de blocage IP et de priorité IP sur le nouveau seuil de 30.

Dans `AuthenticationControllerTest` :
- `test_more_than_five_login_requests_from_one_ip_are_not_blocked_before_domain_failure_limits_apply` confirme que la route ne bloque plus arbitrairement au sixième appel depuis une IP partagée.

### Commits applicatifs et tests

- `c2343856f2bdc69192cee5d10b8d1d8a71eb3856` — trois compteurs, seuils et clés HMAC ;
- `d40d8f70afe01294844ac47849c0029aff8ace15` — intégration des trois contrôles dans le service ;
- `6cded32fe0e839bc5252621d2c2278ed6a1a2028` — comptabilisation de tous les types d'échec ;
- `385a821838f3380fdd1cd93808b18abd773325e8` — suppression du throttle de requêtes sur la route login ;
- `a4ff8c53df7c5a7c41a7f4cdc7535fa2b4345fec` — tests de seuils, compteurs et remise à zéro ;
- `dc60621c3455e3667c8c4762b3e6d766a1c394af` — test HTTP de réseau partagé ;
- `71542986bf582f8d4564feceae6149fc5a66907e` — correction syntaxique du test ;
- `a515185afb9a86b1f63850be5cc315327c821416` — test d'expiration des trois verrouillages.
- `15e472431450c6ab08e65c2573354249746c1e03` — test du rejet d'un couple IP + e-mail déjà verrouillé sans incrément supplémentaire.

Le standard `docs/domains/identity/ID-I-002-AUTHENTICATION_STANDARD.md` est passé en version 1.1 pour formaliser les trois niveaux.

### Corrections après la première exécution locale

Les échecs signalés par l'utilisateur ont été analysés et corrigés dans les tests, sans changement des règles de sécurité :

- Les tests historiques de `AuthenticationSecurityServiceTest` utilisaient encore le seuil IP de 5. Ils vérifient maintenant 30 tentatives par IP et les compteurs restants correspondants (30, 29, 27, etc.).
- Le test de remise à zéro IP a été renommé `test_ip_counter_can_be_cleared_explicitly` afin de ne pas suggérer qu'une connexion réussie efface le compteur IP partagé.
- `AuthenticationWorkflowTransactionTest` vérifie désormais la réponse publique générique exacte `Authentication failed.` après une erreur interne du workflow ; le détail de l'exception n'est pas exposé au client.

Commits correctifs :
- `2087b31c50901411cdaf20b25331a5cdcd644bf9` — alignement des tests IP historiques sur le seuil 30 ;
- `cfe87ae5376cd9d5b35b76a5802353ca3466457b` — vérification du message public générique dans le test transactionnel ;
- `1b4e2a498042a25122cf858c530054bf4a8afae2` — clarification du nom du test de remise à zéro explicite.

**État : VALIDÉ LE 9 NOVEMBRE 2026.** L'utilisateur confirme VERT après correction pour les suites `AuthenticationSecurityServiceTest`, `AuthenticationWorkflowTransactionTest`, `AuthenticationServiceTest`, `AuthenticationControllerTest` et `php vendor/bin/pint --test`. Les seuils approuvés restent inchangés.


## 20.38 — Déverrouillage administratif des trois compteurs (AUTH-06)

**Décision validée le 9 novembre 2026 :** seul `super_admin_root` peut lever manuellement les trois blocages d'authentification.

### Règle et portée

- Une opération doit fournir l'e-mail normalisé du compte, l'adresse IP concernée et un motif administratif obligatoire.
- L'opération efface explicitement le compteur du compte, le compteur partagé de l'IP et le compteur du couple IP + e-mail.
- Le compteur IP partagé est donc remis à zéro pour tous les comptes utilisant cette IP ; cette portée est affichée dans le message de confirmation et conservée dans l'audit.
- L'opération peut viser un e-mail qui ne correspond plus à un utilisateur existant : le compteur de compte et les compteurs IP restent effaçables. L'audit conserve alors l'identité de l'acteur et les empreintes des cibles, sans stocker l'e-mail ni l'IP fournis.
- Les tentatives d'accès sont protégées par le groupe de routes root-only et un contrôle explicite du rôle dans le contrôleur.
- L'événement append-only `identity.authentication.lockouts.unlocked` contient l'acteur, le sujet utilisateur lorsqu'il existe, le motif, les trois portées effacées et les empreintes HMAC des cibles.

### Implémentation — tests ciblés validés localement

- `AuthenticationSecurityService::unlockAllScopes()` centralise l'effacement des trois compteurs.
- `AuthenticationLockoutController::index()` et `unlock()` fournissent la page de gestion et l'action de déverrouillage ; les deux méthodes vérifient explicitement le rôle root.
- Routes : `GET root/back-office/security/authentication-lockouts` (`root.backoffice.security.authentication-lockouts.index`) et `POST root/back-office/security/authentication-lockouts/unlock` (`root.backoffice.security.authentication-lockouts.unlock`).
- Vue `resources/views/identity/security/lockouts/index.blade.php` avec avertissement explicite sur la portée partagée de l'IP, e-mail, IP et motif obligatoire ; lien ajouté à la navigation Root.
- Test HTTP `AuthenticationLockoutControllerTest` couvre l'ouverture root-only de la page, l'interdiction aux autres rôles, la remise à zéro des trois compteurs, la préservation du compteur compte/couple d'un autre e-mail, la confidentialité des empreintes d'audit et la validation des champs.

Commits principaux : `a77a802835b39bfa3a22684119a1147610f71838` (service), `481fdd6e9b9d95ab45b7cae3b97919a1f5ddb959` / `05d1adcd74512e2d6809c24f325a005da70d6312` / `d36bacd01eb2a45a3f8e5c1ed2af39d55aa78dee` (contrôleur), `0be2e6ca9be03a0407b26f3968e70869f704cb21` / `f6f3e448dbb2fcbd8878265c2d32a273a26a196c` / `78b73f7292bcbfa34177a63878a26d9c2edf8b89` (routes), `b33eba9bca1610e0284a7f8ba5e527264f8b51bc` (vue), `df96ca4e211101a97db9207b8c629d3105cba5b6` (navigation), `0c2218a575ab20d243eb9f241b6ff92176ff7991` / `b5793dca418a9340ad4cb64772b2e5a97ed66ae9` / `dc1a88125b802dc607b561a98fc5733f959bc8ca` / `093dbdaad9731fb402e72265f7e84d86825c73f8` (tests).

**État : TESTS CIBLÉS VERTS — 9 NOVEMBRE 2026.** L'utilisateur a confirmé le résultat vert après l'exécution des tests AUTH-06. La validation Pint de ce lot n'a pas été confirmée séparément.


## 20.39 — Contrat des réponses HTTP Web/API (AUTH-07)

### Validation AUTH-06

Le 9 novembre 2026, l'utilisateur a confirmé **VERT** les tests ciblés du déverrouillage administratif AUTH-06. Le code et les tests sont donc validés localement. La commande Pint de ce lot n'a pas été confirmée séparément.

### Audit initial AUTH-07

L'audit des routes et contrôleurs sur `feature/Identity` a établi le périmètre actuel :

- Web : `GET/POST /authentication/login`, `POST /authentication/logout`.
- API : `GET /api/test-auth`, `GET /api/audit/events`, `POST /api/password/forgot`, `POST /api/password/reset`.
- Les ressources API protégées utilisent `auth:sanctum` ; les routes API sont rendues en JSON par le traitement global des exceptions.
- Le changement obligatoire du mot de passe bloque les ressources API par une réponse JSON `403`.
- Aucun endpoint stateless dédié de connexion/déconnexion API n'est ajouté dans AUTH-07 ; le cycle de vie des tokens demeure dans le lot API-01 à API-09.

### Incohérence corrigée

`bootstrap/app.php` forçait auparavant le rendu JSON pour toute exception sur `authentication/*`, y compris les soumissions HTML du formulaire de connexion. Cela contredisait les réponses HTML prévues par `AuthenticationController` (redirection et erreurs de formulaire).

Correction : les routes `api/*` restent toujours JSON ; les routes `authentication/*` ne sont rendues JSON que lorsque la requête annonce qu'elle attend du JSON (`expectsJson()`). Les navigateurs retrouvent donc les redirections et erreurs de session, tandis que les clients JSON conservent les réponses JSON et le statut `422` pour les erreurs de validation.

### Tests ajoutés

Dans `AuthenticationControllerTest` :
- validation HTML de connexion : redirection vers le formulaire et erreurs de session ;
- échec HTML : message générique `Authentication failed.` dans les erreurs du formulaire ;
- déconnexion JSON : `200`, message `Logout successful.`, révocation de la session courante.

Nouveau fichier `tests/Feature/Domains/Identity/Authentication/Http/ApiAuthenticationResponseTest.php` :
- API sans token : `401` JSON ;
- token Sanctum valide : `200` ;
- changement obligatoire non terminé : `403` JSON ;
- validation des demandes API de reset : `422` JSON ;
- demande de reset pour une adresse inconnue : réponse générique `200`, sans révéler l'existence du compte.

Commits :
- `ee47d7ae9d485d2dd91642a45b0fc21248b9e1d3` — négociation JSON/HTML dans le gestionnaire d'exceptions ;
- `db64ebbd816a0fa2959a32bf21df919bb15711f3` — tests de réponses du contrôleur Web ;
- `3f3402761802189e5102c991558aea11f6b77cb3` — premiers tests de réponses API ;
- `36c533746517eff4eddf64c78fadd3511e690e0a` — correction des namespaces du nouveau fichier de tests.

### Points encore ouverts avant clôture d'AUTH-07

1. Confirmer si `GET /api/test-auth` est un endpoint de diagnostic temporaire à retirer avant production ou une route de test intentionnellement conservée.
2. Examiner le fait que `AuthenticationController::login()` transforme toute `RuntimeException` remontée du workflow en `401`. Le message reste générique conformément à la décision de non-divulgation ; il faut toutefois distinguer une authentification refusée d'une panne technique inattendue afin de ne pas masquer un incident serveur. Aucun changement n'est fait ici, car un test transactionnel antérieur attend explicitement une réponse publique générique après un échec du workflow.

**État : AUTH-07 EN COURS — code et tests ajoutés, validation locale en attente.**


## 20.40 — Durcissement final des réponses de connexion AUTH-07

Après la confirmation VERT des premiers tests AUTH-07, les deux points ouverts de l'audit ont été traités sur `feature/Identity`.

### 1. Retrait de la route de diagnostic API

- La route `GET /api/test-auth` a été retirée de `routes/api.php` : elle ne doit pas être exposée dans l'API de production.
- Les tests de contrat Sanctum continuent de vérifier les statuts `401`, `200` et `403), mais enregistrent désormais leur route de diagnostic directement dans le contexte de test.
- Les vraies routes API conservées sont les demandes de reset et la consultation autorisée des événements d'audit.

### 2. Exceptions de refus d'authentification dédiées

- L'exception existante `AuthenticationFailedException` est désormais utilisée pour les refus métier attendus dans `AuthenticationService` : IP verrouillée, compte verrouillé, compte inactif, e-mail non vérifié et identifiants incorrects.
- `AuthenticationController::login()` capture uniquement `AuthenticationFailedException` pour produire le message public générique `Authentication failed.`. Les comptes inconnus utilisent désormais aussi cette exception métier dédiée ; les exceptions techniques ou Eloquent inattendues ne sont pas interceptées.
- Une `RuntimeException` inattendue (panne technique, erreur d'infrastructure ou autre incident) n'est plus convertie artificiellement en `401` ; elle remonte au gestionnaire d'exceptions et peut être signalée comme incident serveur.
- Les tests de service ont été alignés sur le type d'exception métier explicite. Un test workflow injecte une panne technique inattendue et vérifie qu'elle donne `500` (et non `401`), sans créer de session. Les tests de non-divulgation des réponses publiques restent en place.

### Fichiers modifiés

- `routes/api.php`
- `app/Domains/Identity/Authentication/Services/AuthenticationService.php`
- `app/Domains/Identity/Authentication/Http/Controllers/AuthenticationController.php`
- `tests/Feature/Domains/Identity/Authentication/Http/ApiAuthenticationResponseTest.php`
- `tests/Framework/Domains/Identity/Authentication/Services/AuthenticationServiceTest.php`
- `tests/Framework/Domains/Identity/Authentication/Workflows/AuthenticationWorkflowTest.php`

Commits :
- `d5ecc18acb0bf77e4d05643d9c4ed3882e96bd82` — exception métier dédiée dans le service ;
- `18db2a0140d9c6c7846b8a89c518fce549072837` — capture ciblée dans le contrôleur ;
- `759803306490ea30f6cc111105a65856168b9d86` — retrait de la route diagnostic de production ;
- `18d06053c3316f77a2306781662045c6fc1f09ba` — route de test déplacée dans le test API ;
- `28c3f958d2af3a1af44126894ce6d5ceab27e5d8` — tests service alignés sur l'exception métier.

**État : CODE MODIFIÉ — tests ciblés et Pint de cette dernière série à exécuter. AUTH-07 ne sera clôturé qu'après confirmation VERT.**


## 20.41 — Validation du contrat HTTP Web/API (AUTH-07)

Le 9 novembre 2026, l'utilisateur a confirmé **VERT** les tests ciblés de la dernière série AUTH-07 ainsi que Pint. AUTH-07 est donc clôturé.

La validation porte sur :
- négociation correcte HTML/JSON du gestionnaire d'exceptions ;
- réponses publiques génériques et uniformes pour les refus d'authentification ;
- distinction entre refus métier (AuthenticationFailedException, réponse 401) et erreur technique inattendue (remontée au gestionnaire d'exceptions, réponse 500) ;
- retrait de la route de diagnostic /api/test-auth de l'API de production ;
- tests de contrat API exécutés avec une route de test enregistrée uniquement dans le contexte de test.

Les commits applicatifs et tests de la série AUTH-07 sont consignés en sections 20.39 et 20.40.

**État : AUTH-07 VALIDÉ — tests ciblés et Pint confirmés VERTS par l'utilisateur le 9 novembre 2026.**

## 20.42 — Début de l'audit des sessions (SESS-01)

Après clôture d'AUTH-07, l'audit Identity reprend sur la gestion de l'expiration des sessions par rôle. Le premier contrôle porte sur les délais d'inactivité serveur : 15 minutes pour super_admin_root, 30 minutes pour super_admin, 30 minutes pour cooperative_admin et 60 minutes pour user. Aucun changement applicatif n'est déclaré avant inspection des middlewares, de la configuration de session, des routes et des tests existants.

**État : À AUDITER — inspection du code réel de feature/Identity.**
