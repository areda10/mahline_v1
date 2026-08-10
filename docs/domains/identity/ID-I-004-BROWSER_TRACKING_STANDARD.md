# BROWSER_TRACKING_STANDARD.md

## MAHLINE Framework

### Domaine

Identity

### Référence

ID-001 / BROWSER-TRACKING

### Version

1.0

### Statut

**OFFICIEL — GELÉ**

---

# 1. Objectif

Ce document définit le standard de collecte, de stockage et d'utilisation des informations techniques relatives au navigateur utilisé par un User lors de son authentification.

Le Browser Tracking permet notamment de :

* identifier le contexte technique d'une connexion ;
* enrichir Login History ;
* faciliter l'analyse de sécurité ;
* identifier les changements de navigateur ou d'appareil ;
* contribuer à la traçabilité des sessions.

Le Browser Tracking **ne constitue pas un mécanisme d'authentification**.

---

# 2. Principe fondamental

Le Browser Tracking est une information de contexte.

Il ne doit jamais permettre de contourner la règle :

```text
ONE USER → ONE ACTIVE AUTHENTICATED SESSION
```

Un changement de navigateur ou d'appareil entraîne l'application normale de la politique de session unique.

---

# 3. Architecture

Le Browser Tracking appartient au domaine Identity.

Architecture conceptuelle :

```text
User
 │
 ├── Session
 │    └── Browser Context
 │
 ├── Login History
 │    └── Browser Context
 │
 └── Browser Tracking
```

Le Browser Tracking peut être associé à une session et à une entrée Login History.

---

# 4. Informations collectées

Le système peut collecter les informations techniques suivantes :

| Information                | Description                 |
| -------------------------- | --------------------------- |
| `user_agent`               | User-Agent HTTP             |
| `browser_name`             | Nom du navigateur           |
| `browser_version`          | Version du navigateur       |
| `operating_system`         | Système d'exploitation      |
| `operating_system_version` | Version du système          |
| `device_type`              | Type d'appareil             |
| `device_name`              | Nom technique si disponible |
| `ip_address`               | Adresse IP                  |
| `locale`                   | Locale détectée             |
| `timezone`                 | Fuseau horaire détecté      |

La collecte doit respecter le principe de minimisation des données.

---

# 5. User-Agent

Le User-Agent peut être conservé comme donnée technique brute.

Exemple conceptuel :

```text
Mozilla/5.0 (...)
```

Le système peut également extraire :

```text
browser_name
browser_version
operating_system
operating_system_version
device_type
```

Le User-Agent brut ne doit pas être utilisé comme identifiant permanent du User.

---

# 6. Browser Name

Le système peut identifier le navigateur.

Exemples :

```text
Chrome
Firefox
Safari
Edge
Opera
```

La liste n'est pas limitée à ces navigateurs.

---

# 7. Browser Version

La version du navigateur peut être enregistrée.

Exemple :

```text
Firefox 152
```

La version est une information technique et peut changer entre deux connexions.

Elle ne doit pas être utilisée comme identifiant unique.

---

# 8. Operating System

Le système peut identifier le système d'exploitation.

Exemples :

```text
Windows
macOS
Linux
Android
iOS
```

Cette information sert uniquement au contexte technique et à la sécurité.

---

# 9. Device Type

Le système peut classifier le type d'appareil :

```text
desktop
laptop
tablet
mobile
unknown
```

Cette classification n'est pas une preuve d'identité.

---

# 10. Device Identity

MAHLINE ne doit pas considérer les caractéristiques du navigateur comme une identité forte du User.

Il est interdit de considérer :

```text
Browser + OS + IP
```

comme une preuve suffisante d'identité.

Le User reste identifié par son compte et son mécanisme d'authentification.

---

# 11. IP Address

L'adresse IP peut être enregistrée dans le contexte de sécurité.

Elle peut servir à :

* l'analyse de connexion ;
* Login History ;
* l'audit ;
* la détection d'anomalies ;
* l'analyse d'incidents.

Une adresse IP ne doit jamais être utilisée seule pour authentifier un User.

---

# 12. Changement d'IP

Un changement d'adresse IP ne crée pas une nouvelle identité.

Il ne doit pas non plus permettre plusieurs sessions authentifiées.

Exemple :

```text
User A
Session 1
IP = A.B.C.D
```

Puis :

```text
IP = X.Y.Z.W
```

La session reste soumise aux règles normales de validation.

---

# 13. Changement de navigateur

Un User peut se connecter depuis un navigateur différent.

Exemple :

```text
Chrome
    │
    ▼
Session 1
```

Puis :

```text
Firefox
    │
    ▼
New Login
```

La nouvelle authentification doit respecter :

```text
ONE USER → ONE ACTIVE AUTHENTICATED SESSION
```

La session précédente est donc révoquée.

---

# 14. Changement d'appareil

Même principe pour un nouvel appareil.

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

Le Browser Tracking ne doit jamais permettre de conserver les deux sessions actives.

---

# 15. Browser Tracking et Session

Le Browser Tracking est rattaché au contexte de session.

Conceptuellement :

```text
User
 │
 └── Session
      │
      └── Browser Context
```

Lorsque la session est révoquée, le contexte de cette session ne doit pas permettre une nouvelle authentification automatique.

---

# 16. Browser Tracking et Login History

Les données Browser Tracking peuvent être enregistrées dans Login History.

Exemple :

```text
Login History
│
├── User
├── Date
├── Result
├── IP
├── Browser
├── Browser Version
├── OS
└── Device
```

Cela permet de reconstruire le contexte technique d'une connexion.

---

# 17. Browser Tracking et Authentication

Browser Tracking intervient **après ou pendant le contexte de connexion**, mais ne remplace jamais l'authentification.

Flux :

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
Browser Context
    │
    ▼
Session Creation
    │
    ▼
Login History
```

Le navigateur n'est pas un credential.

---

# 18. Browser Tracking et Session Unique

Le Browser Tracking doit respecter la règle :

```text
ONE USER → ONE ACTIVE AUTHENTICATED SESSION
```

Il est interdit d'utiliser le Browser Tracking pour produire :

```text
User A
├── Chrome Session → ACTIVE
├── Firefox Session → ACTIVE
└── Mobile Session → ACTIVE
```

L'état attendu est :

```text
User A
├── Previous Session → REVOKED
└── Current Session → ACTIVE
```

---

# 19. Browser Fingerprinting

Le fingerprinting doit être évité par défaut.

MAHLINE ne doit pas construire un identifiant permanent complexe à partir de :

* résolution écran ;
* polices ;
* canvas ;
* WebGL ;
* timezone ;
* plugins ;
* User-Agent ;
* caractéristiques matérielles.

Ces données peuvent être instables et présenter des risques de confidentialité.

Toute implémentation de fingerprinting devra faire l'objet d'une décision architecturale spécifique.

---

# 20. Cookies

Les cookies utilisés pour le fonctionnement de la session doivent être séparés conceptuellement du Browser Tracking.

Le Browser Tracking ne doit pas créer un cookie d'authentification parallèle.

Les cookies de session doivent respecter les standards de sécurité définis dans :

```text
SESSION_STANDARD.md
```

---

# 21. Données sensibles

Les informations Browser Tracking ne doivent pas contenir :

* mot de passe ;
* token API ;
* token de session en clair ;
* secret cryptographique ;
* cookie complet ;
* credentials.

---

# 22. Logs

Les logs peuvent contenir des informations techniques nécessaires au diagnostic.

Cependant :

* les secrets doivent être exclus ;
* les tokens doivent être masqués ;
* les données doivent être minimisées ;
* les logs doivent respecter la politique de conservation.

---

# 23. Audit

Les événements de sécurité importants liés au Browser Tracking peuvent être audités.

Exemples :

```text
New Browser Context
Browser Change
Device Change
Suspicious Browser Context
Session Replacement
Session Revocation
```

L'audit ne doit jamais enregistrer de secret.

---

# 24. Privacy

Le Browser Tracking doit respecter le principe de minimisation.

Ne collecter que les données nécessaires à :

* la sécurité ;
* l'authentification ;
* la traçabilité ;
* le diagnostic ;
* les exigences réglementaires applicables.

Les données inutiles ne doivent pas être collectées.

---

# 25. Rétention

Les données Browser Tracking doivent respecter la politique de conservation définie par MAHLINE.

La durée exacte dépend :

* de Login History ;
* de l'Audit ;
* des exigences de sécurité ;
* des exigences légales applicables.

Une politique de rétention dédiée pourra être définie ultérieurement.

---

# 26. Accès

Les informations Browser Tracking ne doivent pas être accessibles à tous les utilisateurs.

L'accès doit être contrôlé selon les rôles et permissions.

Exemples :

```text
User
└── accès limité à ses propres informations

Administrator
└── accès selon permissions

Security/Audit
└── accès selon permissions
```

---

# 27. Modification

Les données techniques détectées automatiquement ne doivent pas être modifiables librement par le User.

Par exemple :

```text
browser_name
browser_version
operating_system
device_type
ip_address
```

doivent provenir du contexte technique lorsque ces données sont utilisées comme données de sécurité.

---

# 28. Fiabilité

Les informations Browser Tracking sont des informations techniques et peuvent être inexactes.

Elles peuvent être affectées par :

* proxy ;
* VPN ;
* navigateur ;
* extensions ;
* User-Agent modifié ;
* réseau intermédiaire ;
* détection imparfaite du device.

Elles ne constituent donc pas une preuve absolue d'identité.

---

# 29. Sécurité

Le Browser Tracking peut contribuer à la détection d'anomalies mais ne doit pas remplacer :

* password authentication ;
* session validation ;
* authorization ;
* role/permission checks ;
* token validation.

---

# 30. Connexions simultanées

Le Browser Tracking ne modifie pas la politique de session unique.

Deux navigateurs ne peuvent pas créer deux sessions authentifiées actives pour le même User.

```text
User
├── Browser A → Session revoked
└── Browser B → Session active
```

---

# 31. Tests obligatoires

Les tests doivent couvrir :

### Détection

* User-Agent ;
* navigateur ;
* version ;
* OS ;
* device type.

### Session

* association au User ;
* association à la session ;
* nouveau navigateur ;
* nouvel appareil ;
* nouvelle IP.

### Sécurité

* aucune authentification basée uniquement sur Browser Tracking ;
* aucun secret stocké ;
* aucun token exposé ;
* aucun cookie de session enregistré en clair.

### Session unique

* nouveau navigateur ;
* nouvel appareil ;
* nouvelle IP ;
* révocation de la session précédente ;
* une seule session active.

### Privacy

* minimisation ;
* accès contrôlé ;
* données non modifiables par le User.

---

# 32. Invariants

### BROWSER-R01

Browser Tracking ne constitue pas une authentification.

### BROWSER-R02

Browser Tracking ne constitue pas une preuve d'identité.

### BROWSER-R03

Un User-Agent ne doit pas être utilisé comme identifiant permanent du User.

### BROWSER-R04

L'adresse IP ne doit pas être utilisée seule pour authentifier un User.

### BROWSER-R05

Les informations Browser Tracking ne doivent jamais contenir de secrets.

### BROWSER-R06

Le Browser Tracking doit respecter le principe de minimisation.

### BROWSER-R07

Le Browser Tracking doit être associé au contexte de session et/ou Login History selon le besoin.

### BROWSER-R08

Un changement de navigateur ne permet pas plusieurs sessions authentifiées.

### BROWSER-R09

Un changement d'appareil ne permet pas plusieurs sessions authentifiées.

### BROWSER-R10

## ONE USER → ONE ACTIVE AUTHENTICATED SESSION

Le Browser Tracking doit toujours respecter la politique de session unique.

---

# 33. Architecture recommandée

```text
HTTP Request
     │
     ▼
Browser Context Resolver
     │
     ├── User-Agent
     ├── Browser
     ├── OS
     ├── Device
     ├── IP
     └── Locale/Timezone
     │
     ▼
Authentication
     │
     ▼
Session
     │
     ├── Login History
     └── Audit
```

Le Browser Context Resolver ne doit pas être responsable de l'authentification.

---

# 34. Services conceptuels

Les responsabilités peuvent être réparties entre des composants tels que :

```text
BrowserContextResolver
BrowserTrackingService
BrowserTrackingRepository
```

Les noms définitifs doivent respecter les conventions de nommage MAHLINE.

---

# 35. Documents associés

```text
ID-001-ARCHITECTURE.md

USER_SPECIFICATION.md
USER_MODEL_SPECIFICATION.md

AUTHENTICATION_SPECIFICATION.md
AUTHENTICATION_STANDARD.md

SESSION_SPECIFICATION.md
SESSION_STANDARD.md

LOGIN_HISTORY_STANDARD.md

TOKEN_STANDARD.md
PASSWORD_STANDARD.md

ROLE_PERMISSION_STANDARD.md
AUDIT_STANDARD.md
```

---

# 36. Historique

## Version 1.0

* Création du standard Browser Tracking.
* Définition des informations techniques collectées.
* Définition de la relation avec Session.
* Définition de la relation avec Login History.
* Définition des règles de sécurité.
* Définition des règles de confidentialité.
* Définition des tests.
* Alignement avec la politique **ONE USER → ONE ACTIVE AUTHENTICATED SESSION**.

---

# 37. Statut final

**BROWSER_TRACKING_STANDARD.md — OFFICIEL — GELÉ**

Le Browser Tracking est exclusivement un mécanisme de contexte technique et de traçabilité.

Il ne constitue ni :

* une identité ;
* un credential ;
* une authentification ;
* une session.

Il doit toujours respecter la règle architecturale :

```text
ONE USER
   │
   └── ONE ACTIVE AUTHENTICATED SESSION
```

Toute évolution introduisant un mécanisme de fingerprinting fort, une identité persistante de navigateur ou une utilisation du Browser Tracking comme mécanisme d'authentification devra faire l'objet d'une nouvelle décision d'architecture.
