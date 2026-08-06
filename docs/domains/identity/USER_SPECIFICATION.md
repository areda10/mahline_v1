# USER_SPECIFICATION

## MAHLINE Framework

### Domaine

Identity

### Composant

User

### Version

1.0

### Statut

OFFICIEL — GELÉ

---

# Objectif

Ce document définit les spécifications fonctionnelles de l'entité **User**.

L'utilisateur constitue l'identité numérique de toute personne autorisée à accéder au système MAHLINE.

Toutes les fonctionnalités d'authentification, d'autorisation, d'audit et de sécurité reposent sur cette entité.

---

# Responsabilités

L'entité User est responsable de :

* l'authentification ;
* l'identification ;
* l'autorisation (via rôles et permissions) ;
* la gestion des profils ;
* la sécurité des accès ;
* la traçabilité des connexions ;
* l'utilisation des API.

---

# Types d'utilisateurs

Le framework MAHLINE supporte les types suivants :

* Super Administrateur
* Administrateur
* Gestionnaire
* Employé
* Client
* Partenaire
* API User (compte technique)

Chaque type est défini par les rôles et permissions qui lui sont attribués.

---

# Informations principales

Chaque utilisateur possède au minimum :

* identifiant ULID ;
* prénom ;
* nom ;
* nom d'affichage ;
* adresse e-mail ;
* téléphone ;
* mot de passe ;
* statut ;
* langue ;
* fuseau horaire ;
* photo de profil (optionnelle).

---

# Statuts

Les statuts sont gérés par un Enum PHP.

Valeurs initiales :

* active
* inactive
* suspended
* pending
* archived

---

# Authentification

Le domaine Identity prend en charge :

* authentification par e-mail et mot de passe ;
* authentification via API Token ;
* gestion des sessions ;
* authentification "Remember Me" ;
* vérification de l'adresse e-mail ;
* réinitialisation du mot de passe.

Le framework pourra être étendu ultérieurement pour supporter des méthodes supplémentaires (SSO, OAuth, MFA).

---

# API Tokens

Les API utilisent des jetons d'authentification.

Chaque jeton est associé à :

* un utilisateur ;
* un nom ;
* des permissions ;
* une date de création ;
* une date d'expiration (optionnelle) ;
* une date de dernière utilisation.

Les jetons sont révoquables à tout moment.

---

# Sessions

Chaque connexion crée une session.

Une session enregistre notamment :

* identifiant de session ;
* utilisateur ;
* adresse IP ;
* navigateur ;
* système d'exploitation ;
* appareil ;
* date de connexion ;
* date de dernière activité.

Une session peut être révoquée par l'utilisateur ou par un administrateur.

---

# Gestion des navigateurs

Chaque session est associée à un navigateur identifié par son User-Agent.

Conformément aux décisions d'architecture de MAHLINE :

* le navigateur utilisé est enregistré ;
* plusieurs sessions simultanées sont autorisées sur des navigateurs différents ;
* si deux connexions concurrentes sont détectées avec le **même navigateur** pour un même utilisateur, la politique de sécurité définie par MAHLINE peut invalider la session précédente.

Cette politique est implémentée dans le domaine Identity.

---

# Journal des connexions

Chaque connexion est historisée.

Les informations suivantes sont enregistrées :

* utilisateur ;
* date et heure ;
* adresse IP ;
* navigateur ;
* système d'exploitation ;
* appareil ;
* identifiant de session ;
* résultat de la connexion (succès ou échec) ;
* jeton API utilisé (si applicable).

Aucune suppression automatique de cet historique n'est effectuée sans politique d'archivage définie.

---

# Sécurité

Les mots de passe :

* sont hachés ;
* ne sont jamais stockés en clair ;
* respectent la politique de sécurité du framework.

Le système doit permettre :

* le verrouillage temporaire d'un compte ;
* la limitation des tentatives de connexion ;
* la révocation des sessions ;
* la révocation des jetons d'API.

---

# Audit

Toutes les opérations sur un utilisateur sont auditables.

Les colonnes suivantes sont obligatoires :

* created_by
* updated_by
* deleted_by
* created_at
* updated_at
* deleted_at

Les événements sensibles peuvent également être enregistrés dans le domaine Audit.

---

# Relations

L'entité User est liée à :

* Roles
* Permissions
* Sessions
* API Tokens
* Login History
* Devices
* Browser Sessions
* Media (avatar)
* Audit Logs

---

# Règles métier

* Une adresse e-mail est unique.
* Un utilisateur ne peut pas être supprimé physiquement dans les traitements courants.
* Les comptes suspendus ne peuvent pas s'authentifier.
* Les comptes archivés sont inactifs.
* Les utilisateurs doivent être authentifiés pour accéder aux ressources protégées.
* Toutes les connexions sont journalisées.
* Les changements de mot de passe invalident les sessions actives (hors politique explicitement définie).
* Les jetons révoqués ne peuvent plus être utilisés.

---

# Notifications

Le domaine User pourra envoyer :

* e-mail de bienvenue ;
* vérification d'adresse e-mail ;
* réinitialisation du mot de passe ;
* changement de mot de passe ;
* nouvelle connexion ;
* connexion depuis un nouvel appareil ;
* révocation de session.

---

# Évolutivité

Le composant User est conçu pour permettre l'ajout futur de :

* authentification multifacteur (MFA) ;
* OAuth2 ;
* OpenID Connect ;
* SSO ;
* WebAuthn / Passkeys ;
* authentification sociale.

Ces fonctionnalités devront être ajoutées sans remettre en cause l'architecture existante.

---

# Validation

Le composant User est conforme lorsque :

* les informations obligatoires sont présentes ;
* les règles métier sont respectées ;
* les politiques de sécurité sont appliquées ;
* les journaux de connexion fonctionnent ;
* les sessions sont gérées ;
* les API Tokens sont opérationnels ;
* l'audit est actif.

---

# Documents associés

* ARCHITECTURE.md
* ID-001-ARCHITECTURE.md
* DATABASE_ARCHITECTURE.md
* USER_MODEL_SPECIFICATION.md
* USER_MIGRATION_SPECIFICATION.md
* AUTHENTICATION_SPECIFICATION.md
* SESSION_SPECIFICATION.md
* API_TOKEN_SPECIFICATION.md

---

# Conclusion

L'entité **User** constitue le cœur du domaine **Identity** et la base de tous les mécanismes d'authentification, d'autorisation et de sécurité du framework MAHLINE.

Cette spécification définit le contrat fonctionnel que devront respecter la migration, le modèle, les services, les actions, les politiques, les tests et les futures évolutions du système.

---

# Historique

## Version 1.0

* Création de la spécification fonctionnelle du composant User.
* Définition des responsabilités, des règles métier et des exigences de sécurité.
* Intégration de la gestion des sessions, des navigateurs, des API Tokens et du journal des connexions.
* Publication de la première version officielle.
