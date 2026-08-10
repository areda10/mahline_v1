# SESSION_STANDARD.md

## MAHLINE Framework

### Domaine

Identity

### Référence

ID-001 / SESSION

### Version

1.1

### Statut

**OFFICIEL — GELÉ**

---

# 1. Objectif

Ce document définit les standards techniques applicables aux sessions authentifiées dans MAHLINE.

Il couvre :

* la création des sessions ;
* l'authentification ;
* l'identification du User ;
* la session unique ;
* la révocation ;
* l'expiration ;
* la déconnexion ;
* la régénération de session ;
* le suivi technique ;
* la sécurité ;
* la concurrence ;
* la traçabilité.

---

# 2. Principe fondamental

MAHLINE applique obligatoirement la règle :

```text
ONE USER → ONE ACTIVE AUTHENTICATED SESSION
```

Un User ne peut disposer que d'une seule session authentifiée active à un instant donné.

Cette règle est :

**OFFICIELLE — GELÉE**

---

# 3. Définition d'une session

Une session authentifiée représente le contexte de sécurité permettant à un User authentifié d'accéder aux ressources protégées.

Conceptuellement :

```text
User
  │
  ▼
Authentication
  │
  ▼
Authenticated Session
  │
  ▼
Protected Resources
```

Une session non authentifiée ne doit pas être considérée comme une session Identity active.

---

# 4. Cycle de vie

Le cycle de vie standard est :

```text
CREATED
   │
   ▼
AUTHENTICATED
   │
   ├──────────────┐
   │              │
   ▼              ▼
LOGGED OUT      REVOKED
   │              │
   └──────┬───────┘
          ▼
       CLOSED
```

Une session peut également atteindre l'état `EXPIRED`.

```text
AUTHENTICATED
      │
      ▼
   EXPIRED
      │
      ▼
    CLOSED
```

---

# 5. Création

Une session authentifiée ne doit être créée qu'après une authentification réussie.

Flux obligatoire :

```text
Credentials
    │
    ▼
Authentication
    │
    ▼
User validation
    │
    ▼
Revoke previous session
    │
    ▼
Create session
    │
    ▼
Regenerate session ID
    │
    ▼
Record login
```

---

# 6. Règle de session unique

## ONE USER → ONE ACTIVE AUTHENTICATED SESSION

Lorsqu'une nouvelle session authentifiée est créée pour un User, toutes les sessions authentifiées précédemment actives de ce User doivent être révoquées.

Exemple :

```text
User A

Session 1 → ACTIVE
```

Nouvelle connexion :

```text
Session 1 → REVOKED
Session 2 → ACTIVE
```

État final :

```text
User A
└── Session 2 → ACTIVE
```

---

# 7. Indépendance du navigateur

Le navigateur utilisé ne change pas la règle de session unique.

```text
Chrome
└── Session 1 → ACTIVE

Firefox
└── Login
```

Résultat :

```text
Chrome
└── Session 1 → REVOKED

Firefox
└── Session 2 → ACTIVE
```

---

# 8. Indépendance de l'appareil

La connexion depuis un nouvel appareil révoque la session active précédente.

Exemple :

```text
PC
└── Session 1 → ACTIVE

Mobile
└── Login
```

Résultat :

```text
PC
└── Session 1 → REVOKED

Mobile
└── Session 2 → ACTIVE
```

---

# 9. Indépendance de l'adresse IP

Un changement d'adresse IP ne permet pas de conserver plusieurs sessions actives.

La règle reste :

```text
ONE USER → ONE ACTIVE AUTHENTICATED SESSION
```

L'adresse IP est une donnée de contexte et de sécurité, pas un mécanisme permettant de créer une session supplémentaire.

---

# 10. Indépendance du réseau

Le changement de :

* Wi-Fi ;
* réseau mobile ;
* VPN ;
* réseau d'entreprise ;

ne modifie pas la politique de session unique.

---

# 11. Session Fixation

Après authentification réussie, l'identifiant de session doit être régénéré.

Objectif :

```text
Unauthenticated Session ID
          │
          ▼
Authentication
          │
          ▼
New Session ID
```

L'ancien identifiant ne doit plus permettre d'accéder au contexte authentifié.

---

# 12. Session ID

L'identifiant de session doit être :

* imprévisible ;
* suffisamment entropique ;
* unique ;
* protégé ;
* non exposé dans les logs ;
* non construit à partir du User ID ;
* non construit à partir de données personnelles.

Le User ID ne doit jamais être utilisé comme identifiant de session.

---

# 13. Cookie de session

Le cookie de session doit respecter les standards de sécurité applicables.

Lorsque l'environnement le permet :

```text
Secure
HttpOnly
SameSite
```

Les paramètres exacts dépendent de l'environnement d'exécution et doivent être configurés de manière sécurisée.

---

# 14. Session Authentifiée

Une session est considérée comme authentifiée lorsque :

* le User a été authentifié ;
* le User est autorisé à se connecter ;
* la session est active ;
* aucune révocation n'a été appliquée ;
* la session n'est pas expirée.

---

# 15. Vérification de session

Toute requête nécessitant une authentification doit vérifier que la session est toujours valide.

Conceptuellement :

```text
Request
   │
   ▼
Session exists?
   │
   ├── NO → Unauthorized
   │
   ▼
Session active?
   │
   ├── NO → Unauthorized
   │
   ▼
User active?
   │
   ├── NO → Unauthorized
   │
   ▼
Authorized Session
```

---

# 16. Révocation

Une session peut être révoquée par :

* une nouvelle connexion ;
* une déconnexion ;
* un changement de mot de passe ;
* une suspension du User ;
* une désactivation du User ;
* une suppression logique ;
* une action administrative ;
* un événement de sécurité ;
* une expiration.

Une session révoquée ne doit plus permettre l'accès aux ressources protégées.

---

# 17. Nouvelle connexion

La nouvelle connexion est le principal mécanisme de remplacement de session.

```text
Existing Active Session
          │
          ▼
      New Login
          │
          ▼
       REVOKED
          │
          ▼
New Authenticated Session
```

Le système ne doit pas simplement créer une deuxième session.

Il doit remplacer la session active existante.

---

# 18. Connexions concurrentes

Le système doit garantir la règle de session unique même lorsque plusieurs connexions sont exécutées simultanément.

Situation :

```text
Request A → Login
Request B → Login
```

Le résultat ne doit jamais être :

```text
Session A → ACTIVE
Session B → ACTIVE
```

Le système doit aboutir à :

```text
Session A → REVOKED
Session B → ACTIVE
```

ou l'inverse selon l'ordre final validé.

Une seule session doit rester active.

---

# 19. Transaction

La création et la révocation des sessions doivent être protégées contre les conditions de concurrence.

Lorsque nécessaire, l'implémentation doit utiliser :

* transactions de base de données ;
* verrouillage approprié ;
* contraintes d'intégrité ;
* mécanismes de synchronisation.

L'objectif est de garantir :

```text
COUNT(active authenticated sessions for User) <= 1
```

---

# 20. Déconnexion

Lors d'une déconnexion :

```text
ACTIVE
  │
  ▼
LOGOUT
  │
  ▼
REVOKED
  │
  ▼
CLOSED
```

La session ne doit plus être utilisable.

La déconnexion ne doit pas supprimer les données historiques nécessaires à l'audit.

---

# 21. Expiration

Une session peut expirer après une période définie par la politique de sécurité.

Deux mécanismes peuvent être utilisés :

### Idle timeout

Expiration après une période d'inactivité.

### Absolute timeout

Expiration après une durée maximale depuis la création.

Les valeurs exactes doivent être définies par la configuration de sécurité de l'application.

---

# 22. Remember Me

Remember Me permet au User de conserver un mécanisme de reconnexion selon la configuration Laravel.

Remember Me ne constitue pas une seconde session active permanente.

Lorsqu'une nouvelle authentification est effectivement établie, la règle :

```text
ONE USER → ONE ACTIVE AUTHENTICATED SESSION
```

reste obligatoire.

---

# 23. Changement de mot de passe

Après un changement de mot de passe, les sessions authentifiées doivent être invalidées conformément à la politique de sécurité Identity.

Une nouvelle authentification est nécessaire.

Objectif :

```text
Password Changed
      │
      ▼
Sessions Revoked
      │
      ▼
New Login Required
```

---

# 24. Changement de statut User

Si le User devient non authentifiable :

```text
active
   │
   ▼
suspended
```

la session active doit être révoquée.

Exemples :

```text
inactive
suspended
archived
deleted
```

Le comportement exact dépend du `UserStatus`, mais aucun User non autorisé ne doit conserver un accès authentifié actif.

---

# 25. Session et API Tokens

Les sessions et API Tokens sont deux mécanismes distincts.

```text
WEB
User
 └── Session

API
User
 └── Token
```

La règle de session unique concerne les sessions authentifiées.

Elle ne signifie pas automatiquement :

```text
ONE USER → ONE API TOKEN
```

Les API Tokens sont régis par leurs propres standards.

---

# 26. Login History

Chaque authentification doit être enregistrée.

Une entrée Login History doit pouvoir associer :

* User ;
* session ;
* date ;
* résultat ;
* adresse IP ;
* User-Agent ;
* navigateur ;
* système d'exploitation ;
* appareil.

Lorsqu'une nouvelle connexion révoque une ancienne session, cette opération doit être traçable.

---

# 27. Browser Tracking

Le système peut associer des informations techniques au contexte de session :

```text
Browser
OS
Device
User-Agent
IP
```

Ces données ne doivent pas être utilisées pour contourner la politique de session unique.

---

# 28. Audit

Les événements de session importants doivent être auditables.

Événements minimum :

* session créée ;
* session authentifiée ;
* session révoquée ;
* session expirée ;
* logout ;
* remplacement de session ;
* révocation globale ;
* événement de sécurité.

Les secrets ne doivent jamais être enregistrés.

---

# 29. Données sensibles

Ne jamais enregistrer dans les logs :

* mot de passe ;
* token en clair ;
* cookie de session complet ;
* secret d'authentification ;
* données sensibles inutiles.

Les données techniques doivent être minimisées.

---

# 30. Architecture

La gestion des sessions doit être séparée de la logique métier.

Architecture recommandée :

```text
HTTP / API
    │
    ▼
Authentication
    │
    ▼
Session Service
    │
    ├── Session Repository
    │
    ├── Session Policy
    │
    ├── Login History
    │
    └── Audit
```

Les contrôleurs ne doivent pas implémenter directement la logique complexe de session.

---

# 31. Services

La logique de session doit être centralisée dans les Services et/ou Actions appropriés.

Exemples conceptuels :

```text
CreateSession
RevokeSession
RevokeUserSessions
ReplaceUserSession
ValidateSession
ExpireSession
LogoutUser
```

Les noms exacts doivent respecter les conventions de nommage MAHLINE.

---

# 32. Base de données

Lorsque les sessions sont persistées en base, elles doivent respecter les standards database MAHLINE.

Les informations peuvent notamment comprendre :

```text
id
user_id
ip_address
user_agent
created_at
last_activity
expires_at
revoked_at
```

Les champs exacts dépendent de la migration et de l'implémentation officielle.

---

# 33. Intégrité

Toute session authentifiée doit être associée à un User valide.

Une session ne doit pas rester utilisable lorsque :

* le User est supprimé ;
* le User est désactivé ;
* le User est suspendu ;
* la session est révoquée ;
* la session est expirée.

---

# 34. Tests obligatoires

Les tests doivent couvrir au minimum :

### Création

* création de session ;
* association au User ;
* session authentifiée.

### Sécurité

* session ID régénéré ;
* session ID imprévisible ;
* cookie sécurisé ;
* session fixation impossible.

### Session unique

* une seule session active ;
* nouvelle connexion ;
* révocation de l'ancienne session ;
* nouvel appareil ;
* nouveau navigateur ;
* nouvelle IP ;
* nouveau réseau.

### Révocation

* logout ;
* changement de mot de passe ;
* User suspendu ;
* User désactivé ;
* User supprimé ;
* révocation administrative.

### Expiration

* idle timeout ;
* absolute timeout.

### Concurrence

* deux connexions simultanées ;
* plusieurs requêtes concurrentes ;
* vérification finale d'une seule session active.

---

# 35. Invariants

Les invariants suivants sont obligatoires.

### SESSION-R01

Une session authentifiée doit appartenir à un User valide.

### SESSION-R02

Un identifiant de session doit être imprévisible.

### SESSION-R03

L'identifiant de session doit être régénéré après authentification.

### SESSION-R04

Une session révoquée ne peut plus être utilisée.

### SESSION-R05

Une session expirée ne peut plus être utilisée.

### SESSION-R06

Un User non authentifiable ne peut pas disposer d'une session authentifiée utilisable.

### SESSION-R07

Les sessions doivent être traçables.

### SESSION-R08

Les secrets d'authentification ne doivent jamais être journalisés.

### SESSION-R09

## ONE USER → ONE ACTIVE AUTHENTICATED SESSION

Un User ne peut avoir qu'une seule session authentifiée active.

### SESSION-R10

Toute nouvelle authentification valide révoque la session authentifiée active précédente.

### SESSION-R11

La règle de session unique est indépendante du navigateur, de l'appareil, du système d'exploitation, de l'adresse IP et du réseau.

### SESSION-R12

Les API Tokens sont indépendants des sessions.

### SESSION-R13

Les conditions de concurrence ne doivent jamais permettre durablement plusieurs sessions authentifiées actives pour un même User.

---

# 36. Critères de conformité

Le système est conforme à ce standard lorsque :

* l'authentification crée une session valide ;
* l'identifiant est régénéré ;
* les sessions sont sécurisées ;
* une seule session authentifiée active existe par User ;
* une nouvelle connexion révoque l'ancienne ;
* la règle fonctionne sur différents appareils ;
* la règle fonctionne sur différents navigateurs ;
* la règle fonctionne indépendamment de l'IP ;
* la concurrence est maîtrisée ;
* les sessions révoquées sont refusées ;
* les sessions expirées sont refusées ;
* les événements sont auditables ;
* les tests passent.

---

# 37. Documents associés

```text
ID-001-ARCHITECTURE.md

USER_SPECIFICATION.md
USER_MODEL_SPECIFICATION.md
USER_MIGRATION_SPECIFICATION.md

AUTHENTICATION_SPECIFICATION.md
AUTHENTICATION_STANDARD.md

TOKEN_STANDARD.md
API_TOKEN_SPECIFICATION.md

LOGIN_HISTORY_STANDARD.md
BROWSER_TRACKING_STANDARD.md

PASSWORD_STANDARD.md
ROLE_PERMISSION_STANDARD.md

AUDIT_STANDARD.md
```

---

# 38. Historique

## Version 1.1

* Ajout de la règle officielle de session unique.
* Adoption de **ONE USER → ONE ACTIVE AUTHENTICATED SESSION**.
* Ajout de la révocation automatique de la session précédente.
* Ajout des règles concernant les nouveaux appareils.
* Ajout des règles concernant les nouveaux navigateurs.
* Ajout des règles concernant IP et réseau.
* Ajout des exigences de concurrence.
* Ajout des exigences de transaction et d'intégrité.
* Clarification de la séparation entre Sessions et API Tokens.
* Ajout des invariants SESSION-R09 à SESSION-R13.
* Alignement avec `ID-001-ARCHITECTURE.md` et `USER_SPECIFICATION.md`.

## Version 1.0

* Création du standard Session.
* Définition du cycle de vie.
* Définition des mécanismes de sécurité.
* Définition de la révocation et de l'expiration.
* Définition des tests et invariants initiaux.

---

# 39. Statut final

**SESSION_STANDARD.md — OFFICIEL — GELÉ**

La règle fondamentale est :

```text
ONE USER
   │
   └── ONE ACTIVE AUTHENTICATED SESSION
```

Toute nouvelle authentification valide remplace la session authentifiée active précédente.

Cette règle est obligatoire pour toute implémentation MAHLINE.

Toute modification permettant plusieurs sessions authentifiées simultanément devra faire l'objet d'une nouvelle décision d'architecture et d'une nouvelle version de ce standard.
