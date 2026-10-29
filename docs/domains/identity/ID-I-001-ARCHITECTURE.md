# ID-001-ARCHITECTURE

## MAHLINE Framework

### Domaine

Identity

### Référence

ID-001

### Version

1.1

### Statut

**OFFICIEL — GELÉ**

---

# 1. Objectif

Ce document définit l'architecture officielle du domaine **Identity** du framework MAHLINE.

Le domaine Identity regroupe les composants responsables de :

* l'identité des utilisateurs ;
* l'authentification ;
* la gestion des sessions ;
* les API Tokens ;
* l'historique des connexions ;
* le suivi des navigateurs et appareils ;
* les rôles et permissions ;
* la sécurité des comptes ;
* les mécanismes associés à l'accès au système.

Le domaine Identity constitue une infrastructure fondamentale du framework MAHLINE.

---

# 2. Principes architecturaux

Le domaine Identity respecte les principes suivants :

1. séparation entre identité, authentification et autorisation ;
2. sécurité par défaut ;
3. traçabilité des événements sensibles ;
4. absence de dépendance directe du domaine métier à HTTP ;
5. utilisation des contrats et services pour les mécanismes transversaux ;
6. toutes les opérations sensibles doivent être testables ;
7. plusieurs sessions Web authentifiées actives sont autorisées par User ;
8. les API Tokens sont séparés des sessions web ;
9. les informations sensibles ne doivent jamais être stockées inutilement ;
10. les règles de sécurité doivent être centralisées et non dispersées dans les contrôleurs.

---

# 3. Structure du domaine

Le domaine Identity est organisé conceptuellement comme suit :

```text
Identity
│
├── Users
│   ├── Models
│   ├── Actions
│   ├── DTOs
│   ├── Services
│   ├── Policies
│   ├── Repositories
│   └── Factories
│
├── Authentication
│   ├── Actions
│   ├── Services
│   ├── Contracts
│   └── Exceptions
│
├── Sessions
│   ├── Models
│   ├── Actions
│   ├── Services
│   └── Contracts
│
├── Tokens
│   ├── Models
│   ├── Actions
│   ├── Services
│   └── Contracts
│
├── LoginHistory
│   ├── Models
│   ├── Actions
│   └── Services
│
├── BrowserTracking
│   ├── Models
│   ├── Actions
│   └── Services
│
└── Authorization
    ├── Roles
    ├── Permissions
    ├── Policies
    └── Services
```

L'organisation physique exacte des fichiers doit respecter les conventions générales de l'architecture MAHLINE.

---

# 4. User

Le User représente l'identité numérique d'une personne ou d'un compte technique autorisé à utiliser le système.

Le User est responsable de l'identification et constitue le point central des mécanismes Identity.

Un User possède notamment :

* ULID ;
* prénom ;
* nom ;
* nom d'affichage ;
* adresse e-mail ;
* téléphone ;
* mot de passe ;
* statut ;
* locale ;
* fuseau horaire ;
* date de vérification de l'e-mail ;
* informations d'audit ;
* informations de suppression logique.

Les détails fonctionnels sont définis dans :

```text
USER_SPECIFICATION.md
```

Les détails du modèle sont définis dans :

```text
USER_MODEL_SPECIFICATION.md
```

---

# 5. User Status

Le statut d'un User contrôle son éligibilité à l'authentification.

Les statuts sont définis par l'Enum :

```text
UserStatus
```

Valeurs initiales :

```text
active
inactive
suspended
pending
archived
```

Un User qui n'est pas autorisé à s'authentifier ne doit pas pouvoir établir une nouvelle session authentifiée.

Les règles détaillées sont définies dans :

```text
USER_STATUS_SPECIFICATION.md
```

---

# 6. Authentication

Authentication est responsable de la vérification de l'identité du User.

Le mécanisme initial supporte notamment :

* e-mail + mot de passe ;
* Remember Me ;
* vérification de l'adresse e-mail ;
* réinitialisation du mot de passe ;
* API Token lorsque le contexte est API.

Authentication ne doit pas être confondue avec Authorization.

```text
Authentication
      │
      ▼
Who are you?
```

Alors que :

```text
Authorization
      │
      ▼
What are you allowed to do?
```

Les détails sont définis dans :

```text
AUTHENTICATION_SPECIFICATION.md
AUTHENTICATION_STANDARD.md
```

---

# 7. Session

Une Session représente le contexte d'authentification actif d'un User.

Une session contient notamment :

* identifiant de session ;
* User ;
* adresse IP ;
* User-Agent ;
* navigateur ;
* système d'exploitation ;
* appareil ;
* date de création ;
* dernière activité ;
* expiration éventuelle ;
* état.

Les détails sont définis dans :

```text
SESSION_SPECIFICATION.md
SESSION_STANDARD.md
```

---

# 8. Règle architecturale fondamentale

## ONE USER → MULTIPLE ACTIVE WEB SESSIONS

MAHLINE autorise plusieurs sessions Web authentifiées actives simultanément pour un même User. Chaque session est indépendante, peut correspondre à un navigateur ou appareil différent et possède son propre cycle de vie.

```text
ONE USER
   ├── Session A → ACTIVE
   ├── Session B → ACTIVE
   └── Session C → ACTIVE
```

Cette règle est **OFFICIELLE — GELÉE**.

---

# 9. Nouvelle authentification

Lorsqu'un User se connecte avec succès, le système doit :

1. authentifier le User ;
2. vérifier son statut ;
3. renouveler l'identifiant de session Laravel ;
4. créer une nouvelle session d'authentification indépendante ;
5. enregistrer le contexte de connexion ;
6. enregistrer l'événement dans Login History.

Une nouvelle connexion ne révoque pas les sessions Web déjà actives.

Flux officiel :

```text
User
 │
 ▼
Authentication
 │
 ▼
UserStatus validation
 │
 ▼
Credentials valid
 │
 ▼
Create new independent session
 │
 ▼
Record Login History
 │
 ▼
Authenticated
```

---

# 10. Sessions précédentes

Lorsqu'une nouvelle session est créée pour un User :

```text
Existing Session A → ACTIVE
New Session B      → ACTIVE
```

Chaque session peut être révoquée individuellement. La déconnexion normale révoque uniquement la session courante. Une révocation globale est une action distincte qui cible toutes les sessions actives du User concerné.

Exemple :

```text
User A
├── Session 1 → Chrome / Windows → ACTIVE
└── Session 2 → Firefox / Android → ACTIVE

Logout Session 2
├── Session 1 → ACTIVE
└── Session 2 → REVOKED
```

---

# 11. Sessions simultanées et concurrence

La création et la révocation doivent rester transactionnelles et préserver l'intégrité de chaque session. La concurrence entre connexions ne doit pas entraîner la révocation implicite d'une session indépendante.

Le résultat attendu est :

```text
User A
├── Session A → ACTIVE
├── Session B → ACTIVE
└── Session C → ACTIVE
```

---

# 12. Révocation globale

Une révocation globale peut être déclenchée par une action de sécurité dédiée. Elle révoque les sessions Web actives du User spécifié, sans affecter les sessions d'autres utilisateurs. Les API Tokens restent un mécanisme distinct et leur révocation suit la politique dédiée aux tokens.

---

# 13. Session Fixation

Après authentification, l'identifiant de session doit être renouvelé.

L'ancien identifiant ne doit plus permettre d'accéder à la session authentifiée.

Cette règle protège contre les attaques de Session Fixation.

---

# 14. Déconnexion

Lorsqu'un User se déconnecte :

```text
Active Session
      │
      ▼
Logout
      │
      ▼
Session → REVOKED
```

La déconnexion ne supprime pas :

* le User ;
* l'historique de connexion ;
* les données d'audit.

---

# 15. Révocation

Une session peut être révoquée :

* par le User ;
* par un administrateur autorisé ;
* automatiquement ;
* après une nouvelle authentification ;
* après un changement de mot de passe ;
* à la suite d'un événement de sécurité.

Une session révoquée ne doit plus permettre l'accès aux ressources protégées.

---

# 16. User Status et Session

Le statut du User doit toujours être pris en compte lors de l'utilisation d'une session.

Si le User devient :

```text
inactive
suspended
archived
```

sa session active doit être invalidée conformément à la politique de sécurité.

Un User suspendu ou archivé ne doit pas pouvoir continuer à utiliser une session authentifiée.

---

# 17. Password Change

Un changement de mot de passe doit invalider la session active conformément à la politique de sécurité MAHLINE.

Une nouvelle authentification est alors nécessaire pour établir une nouvelle session.

Cette règle permet de limiter l'utilisation d'une session potentiellement compromise.

---

# 18. Login History

Chaque authentification doit être traçable.

Login History doit permettre d'enregistrer notamment :

* User ;
* date et heure ;
* résultat ;
* adresse IP ;
* navigateur ;
* système d'exploitation ;
* appareil ;
* session ;
* contexte API si applicable.

La création d'une nouvelle session est enregistrée dans Login History sans révoquer les sessions actives existantes.

Les détails sont définis dans :

```text
LOGIN_HISTORY_STANDARD.md
```

---

# 19. Browser Tracking

Browser Tracking permet de conserver le contexte technique du navigateur.

Il peut inclure :

* navigateur ;
* version ;
* système d'exploitation ;
* appareil ;
* User-Agent ;
* informations techniques nécessaires à la sécurité.

Chaque navigateur ou appareil peut posséder sa propre session Web active :

```text
ONE USER → MULTIPLE ACTIVE WEB SESSIONS
```

Un nouveau navigateur crée une session indépendante et ne révoque pas les sessions existantes.

Les détails sont définis dans :

```text
BROWSER_TRACKING_STANDARD.md
```

---

# 20. API Tokens

Les API Tokens constituent un mécanisme d'authentification distinct.

```text
Web
User → Session

API
User → API Token
```

La règle :

```text
ONE USER → MULTIPLE ACTIVE WEB SESSIONS
```

concerne les sessions Web authentifiées. Les sessions Web et les API Tokens ont des cycles de vie distincts.

Les API Tokens sont régis par :

```text
TOKEN_STANDARD.md
API_TOKEN_SPECIFICATION.md
```

---

# 21. Authorization

L'authentification identifie le User.

L'autorisation détermine ce que le User peut faire.

Architecture :

```text
Session
   │
   ▼
User
   │
   ▼
Roles
   │
   ▼
Permissions
   │
   ▼
Authorization
```

Les sessions ne doivent pas contenir directement la logique métier des permissions.

---

# 22. Sécurité

Le domaine Identity applique les principes suivants :

* mots de passe hachés ;
* sessions imprévisibles ;
* régénération après authentification ;
* cookies sécurisés ;
* révocation des sessions ;
* sessions Web multiples et indépendantes par User ;
* journalisation des connexions ;
* limitation des tentatives ;
* protection contre la Session Fixation ;
* séparation entre sessions et API Tokens.

---

# 23. Audit

Les opérations sensibles du domaine Identity doivent être auditables.

Événements concernés :

* création de User ;
* modification de User ;
* changement de statut ;
* authentification ;
* échec d'authentification ;
* création de session ;
* révocation de session ;
* expiration ;
* nouvelle connexion ;
* remplacement de session ;
* changement de mot de passe ;
* révocation globale ;
* événements de sécurité.

Les secrets et données sensibles ne doivent jamais être enregistrés en clair dans les journaux.

---

# 24. Architecture des dépendances

Les composants Identity doivent respecter une séparation claire des responsabilités.

```text
User
 │
 ├── Authentication
 │
 ├── Session
 │
 ├── Login History
 │
 ├── Browser Tracking
 │
 ├── API Tokens
 │
 └── Authorization
```

Les composants doivent communiquer au moyen :

* de Services ;
* d'Actions ;
* de DTOs ;
* de Contracts ;
* d'Events lorsque nécessaire.

Les contrôleurs HTTP ne doivent pas contenir la logique métier centrale.

---

# 25. Indépendance de HTTP

Les Services et Actions du domaine Identity ne doivent pas dépendre directement de :

```php
Illuminate\Http\Request
```

Les données provenant du contexte HTTP doivent être transformées en DTOs ou objets structurés avant d'entrer dans la logique métier.

Cette règle permet d'utiliser Identity depuis :

* Web ;
* API ;
* CLI ;
* Jobs ;
* Tests.

---

# 26. Modèles

Les modèles Identity doivent respecter les standards généraux de MAHLINE.

Les identifiants utilisent des ULID lorsque le modèle possède une clé primaire générée par MAHLINE.

Les modèles doivent utiliser :

```text
BaseModel
```

lorsque cela est applicable.

Les responsabilités des modèles doivent rester limitées à la représentation et aux comportements propres aux données.

---

# 27. Audit Actor

Les entités Identity doivent respecter les règles d'audit définies par MAHLINE.

Les colonnes d'acteur d'audit peuvent notamment comprendre :

```text
created_by
updated_by
deleted_by
```

Ces valeurs identifient l'acteur responsable de l'opération lorsque celui-ci est disponible.

Les détails sont définis dans :

```text
AUDIT_STANDARD.md
```

---

# 28. Soft Delete

Les entités concernées doivent utiliser la suppression logique lorsque la conservation historique est nécessaire.

La suppression physique ne doit pas être utilisée dans les traitements courants lorsqu'elle compromet :

* l'audit ;
* la traçabilité ;
* l'historique ;
* l'intégrité référentielle.

---

# 29. Tests

Le domaine Identity doit être entièrement couvert par des tests automatisés.

Les tests doivent notamment vérifier :

### User

* création ;
* validation ;
* statut ;
* ULID ;
* audit ;
* soft delete.

### Authentication

* authentification valide ;
* authentification invalide ;
* User suspendu ;
* User archivé ;
* limitation des tentatives ;
* changement de mot de passe.

### Session

* création ;
* authentification ;
* session active ;
* expiration ;
* révocation ;
* déconnexion ;
* régénération ;
* sessions simultanées autorisées ;
* nouvelle connexion ;
* révocation automatique de l'ancienne session ;
* changement d'appareil ;
* changement de navigateur ;
* connexions concurrentes.

### Login History

* succès ;
* échec ;
* contexte technique ;
* association à la session.

### API Tokens

* création ;
* utilisation ;
* expiration ;
* révocation ;
* permissions.

### Authorization

* rôles ;
* permissions ;
* policies ;
* accès autorisé ;
* accès refusé.

---

# 30. Règles architecturales gelées

Les règles suivantes sont officiellement gelées :

### ID-001-R01

Un User est identifié par un ULID.

### ID-001-R02

Authentication et Authorization sont deux responsabilités distinctes.

### ID-001-R03

Les sessions authentifiées doivent être imprévisibles et protégées contre la Session Fixation.

### ID-001-R04

Les connexions importantes sont journalisées.

### ID-001-R05

Les API Tokens sont séparés des sessions web.

### ID-001-R06

Les Services et Actions Identity ne dépendent pas directement de `Illuminate\Http\Request`.

### ID-001-R07

Les événements sensibles sont auditables.

### ID-001-R08

Les statuts User contrôlent l'éligibilité à l'authentification.

### ID-001-R09

## ONE USER → MULTIPLE ACTIVE WEB SESSIONS

Un User peut disposer de plusieurs sessions Web authentifiées actives simultanément.

### ID-001-R10

Toute nouvelle authentification valide crée une nouvelle session indépendante et ne révoque pas les sessions actives existantes.

### ID-001-R11

Chaque session est indépendante du navigateur, de l'appareil, du système d'exploitation, de l'adresse IP et du réseau.

### ID-001-R12

Les API Tokens ne sont pas automatiquement soumis à la règle de session unique.

---

# 31. Validation architecturale

Le domaine Identity est conforme lorsque :

* User est correctement identifié ;
* Authentication fonctionne ;
* Authorization est séparée ;
* les statuts User sont respectés ;
* les sessions sont sécurisées ;
* plusieurs sessions Web authentifiées actives peuvent coexister pour un User ;
* une nouvelle connexion conserve les sessions actives existantes ;
* les connexions sont journalisées ;
* les événements sensibles sont auditables ;
* les API Tokens sont correctement séparés ;
* les tests automatisés passent.

---

# 32. Documents associés

## User

* `USER_SPECIFICATION.md`
* `USER_STATUS_SPECIFICATION.md`
* `USER_MODEL_SPECIFICATION.md`
* `USER_MIGRATION_SPECIFICATION.md`
* `USER_FACTORY_SPECIFICATION.md`

## Authentication

* `AUTHENTICATION_SPECIFICATION.md`
* `AUTHENTICATION_STANDARD.md`
* `PASSWORD_STANDARD.md`

## Sessions

* `SESSION_SPECIFICATION.md`
* `SESSION_STANDARD.md`

## Tokens

* `TOKEN_STANDARD.md`
* `API_TOKEN_SPECIFICATION.md`

## Login

* `LOGIN_HISTORY_STANDARD.md`

## Browser

* `BROWSER_TRACKING_STANDARD.md`

## Authorization

* `ROLE_PERMISSION_STANDARD.md`

## Audit

* `AUDIT_STANDARD.md`

## Architecture générale

* `ARCHITECTURE.md`
* `DATABASE_ARCHITECTURE.md`

---

# 33. Historique

## Version 1.1

* Mise à jour de l'architecture Identity.
* Confirmation de la politique de sessions Web simultanées.
* Gel de la règle **ONE USER → MULTIPLE ACTIVE WEB SESSIONS**.
* Interdiction de révoquer les sessions actives existantes lors d'une nouvelle authentification.
* Intégration de la règle dans le flux Authentication → Session.
* Ajout des exigences concernant les connexions concurrentes.
* Clarification de la séparation entre Sessions et API Tokens.
* Mise à jour des règles architecturales gelées.
* Mise à jour des exigences de tests.
* Publication de la version 1.1 officielle.

## Version 1.0

* Création de l'architecture du domaine Identity.
* Définition des composants User, Authentication, Session, Tokens, Login History, Browser Tracking et Authorization.
* Définition des responsabilités et dépendances.
* Définition des principes de sécurité.
* Publication de la première version officielle.

---

# Conclusion

Le domaine **Identity** constitue l'infrastructure de gestion de l'identité, de l'authentification, des sessions, des tokens et de l'autorisation dans MAHLINE.

La règle de sécurité fondamentale concernant les sessions Web est :

```text
ONE USER → MULTIPLE ACTIVE WEB SESSIONS
```

Un User peut rester authentifié simultanément dans plusieurs navigateurs ou appareils. Une nouvelle authentification ne révoque pas les sessions existantes. La déconnexion normale révoque uniquement la session courante ; la révocation globale est une action distincte.

Cette règle est **OFFICIELLE — GELÉE**. Toute modification future nécessite une décision explicite d'architecture.

