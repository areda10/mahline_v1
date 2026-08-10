# LOGIN_HISTORY_STANDARD.md

# MAHLINE Framework

## Domaine : Identity

### Référence

ID-001 / LOGIN-HISTORY

### Version

1.0

### Statut

**OFFICIEL — GELÉ**

---

# 1. Objectif

Ce document définit le standard MAHLINE relatif à l'historique des tentatives et connexions d'authentification d'un User.

Le Login History permet de conserver une trace structurée des événements d'authentification afin de permettre :

* la traçabilité des connexions ;
* l'analyse de sécurité ;
* l'identification des connexions réussies ;
* l'identification des échecs ;
* l'analyse des changements de navigateur ou d'appareil ;
* l'audit des sessions ;
* l'analyse des révocations de sessions.

---

# 2. Principe fondamental

Login History est un mécanisme de **traçabilité**.

Il ne constitue pas :

* un mécanisme d'authentification ;
* un mécanisme d'autorisation ;
* une session ;
* un credential ;
* un token.

---

# 3. Règle architecturale fondamentale

Le Login History doit respecter la règle gelée :

```text
ONE USER → ONE ACTIVE AUTHENTICATED SESSION
```

L'historique peut contenir plusieurs connexions pour un même User.

Cependant, un User ne peut avoir qu'une seule session authentifiée active.

Exemple :

```text
User A
│
├── Login #1 → SUCCESS → Session REVOKED
├── Login #2 → SUCCESS → Session REVOKED
└── Login #3 → SUCCESS → Session ACTIVE
```

---

# 4. Différence entre Login History et Session

Ces deux concepts sont différents.

### Login History

Représente un **événement d'authentification**.

### Session

Représente une **session authentifiée active ou révoquée**.

Exemple :

```text
Login History
│
├── Login event #1
├── Login event #2
└── Login event #3
          │
          ▼
       Session
          │
          └── ACTIVE
```

Le Login History est historique.

La Session représente l'état courant de l'authentification.

---

# 5. Événements enregistrés

Le système doit pouvoir enregistrer au minimum :

```text
LOGIN_SUCCESS
LOGIN_FAILED
LOGOUT
SESSION_REVOKED
SESSION_EXPIRED
```

D'autres événements pourront être ajoutés ultérieurement par décision d'architecture.

---

# 6. Login réussi

Lorsqu'un User est authentifié avec succès, un événement Login History doit être créé.

Exemple :

```text
LOGIN_SUCCESS
```

Le système doit conserver le contexte technique disponible au moment de la connexion.

---

# 7. Login échoué

Une tentative d'authentification échouée peut être enregistrée.

Exemples :

```text
Invalid credentials
Unknown account
Account inactive
Account blocked
```

Le système doit éviter d'enregistrer des informations sensibles telles que :

```text
password
password hash
authentication token
session token
```

---

# 8. Logout

Lorsqu'un User se déconnecte volontairement, l'événement peut être enregistré :

```text
LOGOUT
```

Le Login History doit permettre de différencier :

```text
USER_LOGOUT
SESSION_REVOKED
SESSION_EXPIRED
```

---

# 9. Révocation automatique

Lorsqu'un User se connecte depuis un nouvel appareil ou navigateur, la session authentifiée précédente doit être révoquée.

Exemple :

```text
Session A
ACTIVE
```

Nouvelle connexion :

```text
Login SUCCESS
     │
     ▼
Session A → REVOKED
     │
     ▼
Session B → ACTIVE
```

Le Login History doit conserver cette information.

---

# 10. Session unique

Le système ne doit jamais produire :

```text
User A
├── Session A → ACTIVE
├── Session B → ACTIVE
└── Session C → ACTIVE
```

Le résultat obligatoire est :

```text
User A
├── Session A → REVOKED
├── Session B → REVOKED
└── Session C → ACTIVE
```

---

# 11. Données minimales

Une entrée Login History doit pouvoir contenir au minimum :

| Champ                      | Description                             |
| -------------------------- | --------------------------------------- |
| `id`                       | Identifiant ULID                        |
| `user_id`                  | User concerné                           |
| `event`                    | Type d'événement                        |
| `occurred_at`              | Date/heure de l'événement               |
| `ip_address`               | Adresse IP                              |
| `user_agent`               | User-Agent                              |
| `browser_name`             | Navigateur                              |
| `browser_version`          | Version                                 |
| `operating_system`         | OS                                      |
| `operating_system_version` | Version OS                              |
| `device_type`              | Type d'appareil                         |
| `session_id`               | Session concernée                       |
| `metadata`                 | Informations complémentaires contrôlées |

La structure définitive de la table devra être validée par la migration et la spécification du domaine Identity.

---

# 12. Identifiant

Toutes les nouvelles entités MAHLINE utilisent un ULID.

Le Login History doit donc utiliser :

```text
CHAR(26)
```

comme identifiant primaire.

---

# 13. User ID

Chaque événement associé à un User doit référencer :

```text
user_id
```

La relation conceptuelle est :

```text
User 1
  │
  └── N Login History
```

Un User peut donc avoir un nombre illimité d'entrées historiques.

---

# 14. Session ID

Lorsqu'un événement est associé à une session, le Login History doit pouvoir référencer la session concernée.

Exemple :

```text
LOGIN_SUCCESS
      │
      └── session_id = Session A
```

Pour un échec d'authentification sans session créée :

```text
LOGIN_FAILED
      │
      └── session_id = NULL
```

---

# 15. Browser Tracking

Le Login History doit intégrer les informations Browser Tracking disponibles.

Cela permet de conserver le contexte au moment exact de l'événement.

Exemple :

```text
Login History
│
├── IP
├── User-Agent
├── Browser
├── Browser Version
├── OS
├── OS Version
└── Device Type
```

Voir :

```text
BROWSER_TRACKING_STANDARD.md
```

---

# 16. Adresse IP

L'adresse IP peut être enregistrée pour les événements d'authentification.

Elle peut être utilisée pour :

* sécurité ;
* diagnostic ;
* audit ;
* analyse d'anomalies.

Elle ne doit jamais être utilisée seule comme mécanisme d'authentification.

---

# 17. User-Agent

Le User-Agent peut être conservé comme donnée technique.

Il doit être considéré comme une information de contexte et non comme une identité.

---

# 18. Mot de passe

Le Login History ne doit **jamais** enregistrer :

```text
password
```

ou :

```text
password_hash
```

ou toute donnée permettant de reconstruire le mot de passe.

---

# 19. Tokens

Le Login History ne doit jamais enregistrer en clair :

```text
session_token
remember_token
api_token
access_token
refresh_token
```

Si une référence à un token est nécessaire, elle doit être représentée par un identifiant ou une empreinte adaptée, conformément au standard Token.

---

# 20. Metadata

Le champ `metadata`, lorsqu'il existe, doit contenir uniquement des données complémentaires nécessaires à la traçabilité.

Exemple acceptable :

```json
{
    "authentication_method": "password"
}
```

Exemple interdit :

```json
{
    "password": "secret"
}
```

---

# 21. Chronologie

Chaque événement doit avoir une date d'occurrence.

Conceptuellement :

```text
occurred_at
```

doit représenter le moment réel où l'événement s'est produit.

Exemple :

```text
10:00 LOGIN_SUCCESS
10:05 SESSION_REVOKED
10:05 LOGIN_SUCCESS
```

---

# 22. Ordre des événements

Le système doit conserver la chronologie des événements.

Exemple :

```text
LOGIN_SUCCESS
      ↓
SESSION_REVOKED
      ↓
LOGIN_SUCCESS
```

Cela permet de comprendre pourquoi une session précédente n'est plus active.

---

# 23. Nouveau navigateur

Exemple :

```text
Chrome
   │
   └── Login SUCCESS
```

Puis :

```text
Firefox
   │
   └── Login SUCCESS
```

Le second login entraîne :

```text
Chrome Session → REVOKED
Firefox Session → ACTIVE
```

Les deux événements restent présents dans Login History.

---

# 24. Nouvel appareil

Même comportement.

```text
Desktop
   │
   └── Session A
```

Puis :

```text
Mobile
   │
   └── Session B
```

Résultat :

```text
Session A → REVOKED
Session B → ACTIVE
```

---

# 25. Même navigateur

Une nouvelle authentification dans le même navigateur doit également respecter :

```text
ONE USER → ONE ACTIVE AUTHENTICATED SESSION
```

Le Browser Tracking ne doit jamais permettre de contourner cette règle.

---

# 26. Sessions simultanées

Le Login History peut montrer plusieurs événements rapprochés :

```text
10:00 LOGIN_SUCCESS
10:01 LOGIN_SUCCESS
10:02 LOGIN_SUCCESS
```

Cela ne signifie pas que trois sessions sont actives.

Après chaque nouvelle authentification :

```text
Previous Session → REVOKED
New Session → ACTIVE
```

---

# 27. État de session

Le Login History peut référencer les changements d'état d'une session.

États conceptuels :

```text
ACTIVE
REVOKED
EXPIRED
LOGGED_OUT
```

Le Login History ne doit toutefois pas devenir la source principale de vérité de l'état actuel de la session.

La Session reste la source de vérité de son propre état.

---

# 28. Source de vérité

### User

Source de vérité :

```text
users
```

### Session

Source de vérité :

```text
sessions
```

### Login History

Source historique :

```text
login_history
```

### Browser Tracking

Source du contexte technique :

```text
browser context
```

---

# 29. Architecture

```text
                    ┌─────────────────┐
                    │      User       │
                    └────────┬────────┘
                             │
                             │
                 ┌───────────▼───────────┐
                 │   Authentication      │
                 └───────────┬───────────┘
                             │
             ┌───────────────┴───────────────┐
             │                               │
             ▼                               ▼
      Browser Context                    Session
             │                               │
             │                               │
             └───────────────┬───────────────┘
                             │
                             ▼
                      Login History
```

---

# 30. Flux d'authentification

```text
Request
   │
   ▼
Authentication
   │
   ├── FAILED
   │     │
   │     └── LOGIN_FAILED
   │
   └── SUCCESS
         │
         ▼
   Revoke previous sessions
         │
         ▼
   Create new session
         │
         ▼
   LOGIN_SUCCESS
```

---

# 31. Transaction

La création de la nouvelle session et la révocation de l'ancienne session doivent être conçues de manière atomique lorsque cela est nécessaire afin d'éviter un état intermédiaire incohérent.

Objectif :

```text
Previous Sessions → REVOKED
New Session       → ACTIVE
```

et jamais :

```text
Previous Session → ACTIVE
New Session      → ACTIVE
```

---

# 32. Concurrent Login

Les connexions concurrentes doivent également respecter :

```text
ONE USER → ONE ACTIVE AUTHENTICATED SESSION
```

Le système doit prévoir une protection contre les conditions de concurrence lors de deux authentifications simultanées du même User.

La stratégie exacte de verrouillage/transaction doit être définie dans l'implémentation Identity.

---

# 33. Conservation historique

Une session révoquée ne doit pas entraîner automatiquement la suppression du Login History.

Exemple :

```text
Login #1 → SUCCESS
Login #2 → SUCCESS
Login #3 → SUCCESS
```

Les trois événements doivent pouvoir rester consultables selon la politique de rétention.

---

# 34. Suppression

Le Login History est une donnée historique.

La suppression physique ne doit pas être effectuée automatiquement lors d'un logout.

Toute suppression doit respecter :

* la politique de rétention ;
* les obligations légales ;
* les règles d'audit ;
* les exigences de sécurité.

---

# 35. Soft Delete

Si la table Login History utilise le soft delete, son comportement doit être documenté explicitement.

Le soft delete ne doit pas être utilisé pour masquer les événements de sécurité actifs sans raison architecturale.

---

# 36. Audit

Les événements Login History eux-mêmes peuvent être intégrés aux mécanismes d'audit selon la stratégie Audit MAHLINE.

Il faut cependant éviter de créer une duplication inutile entre :

```text
Audit
```

et :

```text
Login History
```

### Distinction

Audit :

```text
Qui a effectué quelle action ?
```

Login History :

```text
Quel événement d'authentification s'est produit ?
```

---

# 37. Sécurité

Login History peut être considéré comme une donnée de sécurité.

Son accès doit donc être contrôlé.

Un User doit pouvoir consulter uniquement les informations qui lui sont autorisées.

Les administrateurs et fonctions de sécurité peuvent disposer d'un accès plus large selon les permissions.

---

# 38. Tests obligatoires

Les tests doivent couvrir :

### Login réussi

* création de Login History ;
* association au User ;
* association à la Session ;
* date d'événement ;
* Browser Tracking.

### Login échoué

* création de l'événement ;
* User lorsqu'il est identifiable ;
* absence de mot de passe ;
* absence de token.

### Logout

* événement `LOGOUT` ;
* association à la session.

### Session replacement

* ancienne session révoquée ;
* nouvelle session active ;
* historique conservé.

### Session unique

* même navigateur ;
* nouveau navigateur ;
* nouvel appareil ;
* nouvelle IP ;
* connexions concurrentes.

### Sécurité

* aucun password ;
* aucun password hash ;
* aucun token en clair ;
* aucune donnée sensible inutile.

---

# 39. Invariants

### LOGIN-R01

Chaque événement Login History possède un identifiant ULID.

### LOGIN-R02

Un User peut avoir plusieurs entrées Login History.

### LOGIN-R03

Login History est historique et non la source de vérité de la session courante.

### LOGIN-R04

Une tentative échouée ne doit jamais créer une session authentifiée active.

### LOGIN-R05

Un Login History ne doit jamais stocker un mot de passe.

### LOGIN-R06

Un Login History ne doit jamais stocker un token secret en clair.

### LOGIN-R07

Le Browser Tracking ne constitue pas une authentification.

### LOGIN-R08

Une nouvelle authentification révoque les sessions authentifiées précédentes du même User.

### LOGIN-R09

Une seule session authentifiée peut être active pour un User.

### LOGIN-R10

Les événements historiques restent conservables après révocation d'une session.

### LOGIN-R11

Les connexions concurrentes doivent respecter la règle de session unique.

### LOGIN-R12

## ONE USER → ONE ACTIVE AUTHENTICATED SESSION

Cette règle est obligatoire et gelée.

---

# 40. Exemple complet

Avant nouvelle connexion :

```text
USER: U01

LOGIN HISTORY
├── Login #1
│   └── SUCCESS
│
SESSION
└── Session A
    └── ACTIVE
```

Nouvelle connexion depuis Firefox :

```text
LOGIN
   │
   ▼
SUCCESS
   │
   ▼
Revoke Session A
   │
   ▼
Create Session B
```

Résultat :

```text
USER: U01

LOGIN HISTORY
├── Login #1
│   └── SUCCESS
│
└── Login #2
    └── SUCCESS

SESSIONS
├── Session A
│   └── REVOKED
│
└── Session B
    └── ACTIVE
```

---

# 41. Architecture finale

```text
                         USER
                          │
                          │
                    AUTHENTICATION
                          │
             ┌────────────┴────────────┐
             │                         │
             ▼                         ▼
     BROWSER CONTEXT                SESSION
             │                         │
             │                  ┌──────┴──────┐
             │                  │             │
             │               ACTIVE        REVOKED
             │
             └────────────┬──────────────┘
                          │
                          ▼
                    LOGIN HISTORY
                          │
                          ▼
                       AUDIT
```

La règle centrale reste :

```text
ONE USER
   │
   └── ONE ACTIVE AUTHENTICATED SESSION
```

---

# 42. Documents associés

```text
ID-001-ARCHITECTURE.md

USER_SPECIFICATION.md
USER_MODEL_SPECIFICATION.md
USER_MIGRATION_SPECIFICATION.md
USER_FACTORY_SPECIFICATION.md

AUTHENTICATION_SPECIFICATION.md
AUTHENTICATION_STANDARD.md

SESSION_SPECIFICATION.md
SESSION_STANDARD.md

BROWSER_TRACKING_STANDARD.md

TOKEN_STANDARD.md
PASSWORD_STANDARD.md

ROLE_PERMISSION_STANDARD.md

AUDIT_STANDARD.md
```

---

# 43. Historique

## Version 1.0

* Création du standard Login History.
* Définition des événements d'authentification.
* Définition de la relation User / Session / Login History.
* Intégration du Browser Tracking.
* Définition des règles de sécurité.
* Définition de la révocation des sessions précédentes.
* Définition des tests.
* Intégration de la règle **ONE USER → ONE ACTIVE AUTHENTICATED SESSION**.

---

# 44. Statut final

**LOGIN_HISTORY_STANDARD.md — OFFICIEL — GELÉ**

Le Login History constitue la trace historique des événements d'authentification.

Il ne remplace ni :

```text
User
Session
Authentication
Browser Tracking
Audit
```

La règle architecturale obligatoire est :

```text
ONE USER
   │
   └── ONE ACTIVE AUTHENTICATED SESSION
```

Toute modification de cette règle nécessite une nouvelle décision d'architecture et une mise à jour des documents Identity concernés.
