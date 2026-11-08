# MAHLINE V7.0 — Cahier de décisions et plan de suivi
## Domaine Identity — User Management, Authentication, Security, API & Privacy

- **Version :** 1.1
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

**Note de périmètre :** la règle ci-dessus concerne l'accès aux données personnelles d'autrui. Elle ne retire pas à chaque utilisateur le droit de consulter et modifier ses propres informations.

### Questions de confidentialité restant à clarifier
- [ ] Quelles catégories exactes de données personnelles sont visibles par root et super admin ?
- [ ] Quelles données chaque utilisateur peut-il consulter et modifier lui-même ?
- [ ] Faut-il un export téléchargeable des données personnelles d'un utilisateur ?
- [ ] Quel rôle peut demander ou effectuer une correction des données ?
- [ ] Quel processus pour une demande de suppression ou d'anonymisation ?
- [ ] Quelles données doivent être conservées pour les obligations comptables, commerciales, légales ou de sécurité ?
- [ ] Quelle durée de conservation appliquer aux données de profil après fermeture/suppression du compte ?
- [ ] Faut-il journaliser chaque consultation administrative d'une fiche personnelle ?
- [ ] Faut-il informer l'utilisateur lorsqu'un administrateur consulte ses données personnelles ?

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
- [ ] Définir les catégories de données modifiables par leur propriétaire.
- [ ] Clarifier et implémenter l'export des données personnelles.
- [ ] Clarifier le processus de correction, suppression et anonymisation.
- [ ] Définir les règles de conservation et les exceptions légales/comptables.
- [ ] Journaliser les consultations administratives si cette règle est validée.

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
- La prochaine question à traiter est la question 92 : catégories exactes de données personnelles accessibles à root et super admin.
- Ce document doit être mis à jour dans `feature/Identity` à chaque nouvelle décision validée.
