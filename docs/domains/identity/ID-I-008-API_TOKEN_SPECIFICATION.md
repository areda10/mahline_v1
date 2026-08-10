# API_TOKEN_SPECIFICATION.md

# MAHLINE Framework

## API Token Specification

### Référence

IDENTITY / AUTHENTICATION / API TOKEN

### Version

1.0

### Statut

**OFFICIEL — GELÉ**

---

# 1. Objectif

Ce document définit les règles fonctionnelles et architecturales relatives aux tokens d'API dans MAHLINE.

Les API Tokens permettent à une application ou à un client autorisé d'accéder aux ressources API de MAHLINE sans utiliser directement les identifiants de connexion du User.

---

# 2. Principe fondamental

Un API Token est une **credential d'accès**.

Il doit être considéré comme un secret.

```text
API TOKEN = SECRET
```

Un token ne doit jamais être traité comme une simple donnée métier.

---

# 3. Distinction avec la session

Un API Token et une session authentifiée sont deux mécanismes différents.

```text
WEB AUTHENTICATION
        │
        ▼
     SESSION

API AUTHENTICATION
        │
        ▼
    API TOKEN
```

Le token ne doit pas être considéré comme une session navigateur.

---

# 4. Distinction avec le mot de passe

Le mot de passe permet d'établir une authentification.

Le token permet ensuite d'autoriser des appels API selon ses permissions.

```text
PASSWORD
    │
    ▼
AUTHENTICATION
    │
    ▼
API TOKEN
    │
    ▼
API ACCESS
```

Un API Token ne doit jamais remplacer le mot de passe dans l'interface Web.

---

# 5. Propriété du Token

Chaque API Token appartient à un User.

Relation logique :

```text
USER 1 ──────── N API TOKENS
```

Un User peut donc posséder plusieurs tokens.

Cependant, chaque token doit être identifiable individuellement.

---

# 6. Identité du Token

Chaque token possède au minimum :

```text
id
user_id
name
token
created_at
updated_at
expires_at
last_used_at
revoked_at
```

Les colonnes exactes dépendent de la migration finale.

---

# 7. Identifiant

L'identifiant interne du token doit respecter le standard ULID MAHLINE.

```text
CHAR(26)
```

L'identifiant interne ne doit pas être utilisé comme secret d'authentification.

---

# 8. Secret du Token

Le secret du token est la valeur permettant l'authentification API.

Il doit être généré de manière cryptographiquement sûre.

Il doit être suffisamment long et imprévisible.

---

# 9. Stockage du Token

Le token secret ne doit **jamais** être stocké en clair dans la base de données.

Principe :

```text
TOKEN EN CLAIR
      │
      ▼
   HASHING
      │
      ▼
DATABASE
```

La base de données doit conserver uniquement une représentation permettant de vérifier le token.

---

# 10. Token affiché une seule fois

Lors de la création d'un token :

```text
CREATE TOKEN
      │
      ▼
DISPLAY SECRET
      │
      ▼
USER STORES SECRET
```

Le secret complet peut être affiché au User au moment de sa création.

Après cette étape, MAHLINE ne doit pas pouvoir afficher à nouveau le secret complet.

---

# 11. Interdiction de récupération

Il est interdit de proposer une fonction :

```text
SHOW TOKEN SECRET
```

après sa création.

En cas de perte :

```text
OLD TOKEN
    │
    ▼
REVOKE
    │
    ▼
CREATE NEW TOKEN
```

---

# 12. Token Name

Chaque token doit posséder un nom permettant au User de l'identifier.

Exemples :

```text
ERP Integration
Mobile Application
Accounting System
Partner API
Automation
```

Le nom n'est pas secret.

---

# 13. Token Prefix

Lorsque nécessaire, MAHLINE peut conserver un préfixe public permettant d'identifier rapidement un token.

Exemple :

```text
mah_01JXXXXXXXXXXXX
```

Le préfixe ne doit pas permettre de reconstruire le secret.

---

# 14. Expiration

Un token peut avoir une date d'expiration.

```text
expires_at
```

Valeurs possibles :

```text
NULL
```

pour un token sans expiration selon la politique de sécurité applicable,

ou :

```text
DATE/TIME
```

pour un token temporaire.

---

# 15. Token expiré

Un token dont :

```text
expires_at < now()
```

est considéré comme expiré.

Il ne doit plus permettre l'authentification API.

---

# 16. Révocation

Un token peut être révoqué.

La révocation est représentée par :

```text
revoked_at
```

Lorsqu'un token est révoqué :

```text
revoked_at IS NOT NULL
```

Le token ne doit plus être accepté.

---

# 17. Révocation irréversible

La révocation doit être considérée comme définitive.

Un token révoqué ne doit pas être réactivé.

Flux :

```text
ACTIVE
  │
  ▼
REVOKED
```

et non :

```text
ACTIVE
  │
  ▼
REVOKED
  │
  ▼
ACTIVE
```

Pour rétablir l'accès :

```text
CREATE NEW TOKEN
```

---

# 18. Statut logique

Le statut d'un token peut être déterminé à partir de ses attributs.

```text
ACTIVE
EXPIRED
REVOKED
```

Priorité :

```text
REVOKED
   >
EXPIRED
   >
ACTIVE
```

---

# 19. Last Used

Le système peut conserver :

```text
last_used_at
```

afin de connaître la dernière utilisation du token.

Cette information est utile pour :

```text
security review
token cleanup
usage monitoring
inactive token detection
```

---

# 20. Première utilisation

Lorsqu'un token est utilisé pour la première fois :

```text
last_used_at = current timestamp
```

---

# 21. Mise à jour de last_used_at

Chaque authentification réussie avec le token peut mettre à jour :

```text
last_used_at
```

Cette opération ne doit cependant pas provoquer une surcharge inutile de la base de données.

Une stratégie d'optimisation peut être introduite ultérieurement.

---

# 22. Token Authentication

Le flux standard est :

```text
HTTP REQUEST
     │
     ▼
READ API TOKEN
     │
     ▼
VERIFY TOKEN
     │
     ├── INVALID ──► 401
     │
     ▼
CHECK REVOKED
     │
     ├── YES ─────► 401
     │
     ▼
CHECK EXPIRATION
     │
     ├── EXPIRED ─► 401
     │
     ▼
IDENTIFY USER
     │
     ▼
CHECK USER STATUS
     │
     ├── INVALID ─► 401/403
     │
     ▼
AUTHORIZATION
     │
     ▼
API ACTION
```

---

# 23. User Status

Un API Token valide ne doit pas permettre à un User désactivé d'accéder à l'API.

Exemple :

```text
USER = suspended
TOKEN = active
```

Résultat :

```text
ACCESS DENIED
```

Le statut du User reste prioritaire sur le token.

---

# 24. User Deleted

Un User supprimé ou désactivé selon les règles Identity ne doit plus pouvoir utiliser ses API Tokens.

Les tokens associés doivent être considérés comme invalides.

---

# 25. One Active Session Rule

La règle :

```text
ONE USER → ONE ACTIVE AUTHENTICATED SESSION
```

concerne les sessions d'authentification interactive.

Elle ne signifie pas :

```text
ONE USER → ONE API TOKEN
```

Un User peut posséder plusieurs API Tokens.

Exemple :

```text
USER
 ├── Token ERP
 ├── Token Mobile
 └── Token Automation
```

---

# 26. Nouvelle connexion Web

Une nouvelle connexion Web peut révoquer l'ancienne session conformément à :

```text
SESSION_SPECIFICATION.md
SESSION_STANDARD.md
```

Cette règle ne doit pas automatiquement révoquer les API Tokens du User.

---

# 27. API Token indépendant

Les API Tokens sont indépendants des sessions navigateur.

```text
WEB SESSION
    │
    └── browser authentication

API TOKEN
    │
    └── API authentication
```

La révocation d'une session ne révoque donc pas automatiquement un token API.

---

# 28. Révocation globale

MAHLINE peut prévoir une opération de révocation globale des tokens d'un User.

Exemple :

```text
REVOKE ALL USER API TOKENS
```

Cette opération est particulièrement importante en cas de :

```text
security incident
account compromise
user termination
credential rotation
```

---

# 29. Révocation individuelle

Un User disposant des permissions nécessaires peut révoquer un token spécifique.

Exemple :

```text
REVOKE TOKEN
```

---

# 30. Création d'un Token

La création doit suivre :

```text
AUTHENTICATED USER
       │
       ▼
AUTHORIZATION
       │
       ▼
CREATE TOKEN
       │
       ▼
GENERATE SECRET
       │
       ▼
STORE HASH
       │
       ▼
RETURN SECRET ONCE
```

---

# 31. Autorisation

La création, la révocation et la gestion des API Tokens sont des opérations protégées.

Exemples de permissions possibles :

```text
api_tokens.view
api_tokens.create
api_tokens.revoke
api_tokens.manage
```

Les noms définitifs doivent respecter :

```text
ROLE_PERMISSION_STANDARD.md
```

---

# 32. Token Scopes

Les tokens peuvent être associés à des scopes.

Exemples :

```text
products:read
products:write
orders:read
orders:write
users:read
```

Le scope doit limiter les capacités du token.

---

# 33. Principe du moindre privilège

Un token ne doit recevoir que les permissions nécessaires.

```text
TOKEN
   │
   ▼
MINIMUM REQUIRED PERMISSIONS
```

Éviter :

```text
FULL ACCESS
```

lorsqu'un accès limité suffit.

---

# 34. Token Administration

Un User ne doit pas pouvoir administrer les tokens d'un autre User sans permission explicite.

Exemple :

```text
USER A
   │
   └── peut gérer ses propres tokens

ADMIN
   │
   └── peut gérer les tokens selon ses permissions
```

---

# 35. Token API et Audit

Les opérations sensibles sur les tokens doivent être auditées.

Exemples :

```text
API_TOKEN_CREATED
API_TOKEN_REVOKED
API_TOKEN_EXPIRED
API_TOKEN_REVOKED_ALL
```

---

# 36. Audit du Secret

Le secret du token ne doit jamais apparaître dans l'audit.

Interdit :

```text
token = "mah_XXXXXXXXXXXXXXXX"
```

Autorisé :

```text
token_id
token_name
actor_id
created_at
revoked_at
```

---

# 37. Token Creation Audit

Lors de la création :

```text
API_TOKEN_CREATED
```

peut contenir :

```text
actor_id
token_id
token_name
created_at
```

---

# 38. Token Revocation Audit

Lors de la révocation :

```text
API_TOKEN_REVOKED
```

peut contenir :

```text
actor_id
token_id
reason
revoked_at
```

---

# 39. Failed Authentication

Les tentatives d'authentification API échouées peuvent être enregistrées dans le mécanisme de sécurité approprié.

Elles ne doivent pas exposer le token reçu.

Interdit :

```text
invalid_token = "secret..."
```

---

# 40. Rate Limiting

L'authentification API doit être protégée contre les tentatives répétées.

Le système doit pouvoir appliquer :

```text
rate limiting
throttling
abuse detection
```

selon les exigences du domaine API.

---

# 41. Transmission

Le token doit être transmis uniquement via un canal sécurisé.

Production :

```text
HTTPS
```

L'envoi de tokens sur HTTP non sécurisé est interdit.

---

# 42. Headers

Le mécanisme d'authentification API doit utiliser un mécanisme standardisé.

Exemple :

```http
Authorization: Bearer <TOKEN>
```

Le token ne doit pas être transmis dans :

```text
URL
query string
```

---

# 43. Logs

Le token ne doit jamais apparaître dans :

```text
application logs
error logs
debug logs
audit logs
access logs
exception messages
```

Les valeurs sensibles doivent être masquées ou supprimées.

---

# 44. Exceptions

Une erreur d'authentification ne doit pas révéler pourquoi un token particulier a échoué.

Éviter :

```text
Token exists but is expired
```

si cela permet d'aider un attaquant.

Réponse générique recommandée :

```text
Unauthenticated.
```

---

# 45. API Response

Une requête avec un token invalide doit retourner une réponse d'authentification appropriée.

Typiquement :

```http
401 Unauthorized
```

Une requête authentifiée mais non autorisée peut retourner :

```http
403 Forbidden
```

---

# 46. Token Enumeration

Les réponses API ne doivent pas permettre de déterminer facilement :

```text
token exists
token does not exist
token expired
token revoked
```

Le comportement externe doit rester suffisamment uniforme.

---

# 47. Token Rotation

La rotation doit être supportée par le cycle :

```text
CREATE NEW TOKEN
       │
       ▼
TEST NEW TOKEN
       │
       ▼
REVOKE OLD TOKEN
```

La rotation évite les périodes prolongées d'utilisation d'un secret ancien.

---

# 48. Token Compromise

En cas de compromission :

```text
IDENTIFY TOKEN
       │
       ▼
REVOKE TOKEN
       │
       ▼
CREATE NEW TOKEN
       │
       ▼
AUDIT EVENT
```

Si nécessaire :

```text
REVOKE ALL USER TOKENS
```

---

# 49. Token Expiration Policy

Les tokens destinés aux intégrations externes doivent privilégier une durée de vie limitée lorsque cela est compatible avec le besoin métier.

Exemple :

```text
SHORT-LIVED TOKEN
```

pour une intégration temporaire.

---

# 50. Long-Lived Token

Un token sans expiration doit être considéré comme plus sensible.

Il doit être soumis à une politique de sécurité appropriée.

---

# 51. Token Cleanup

Les tokens expirés ou révoqués peuvent être conservés pour l'historique et l'audit.

Une suppression physique ne doit pas être réalisée simplement pour masquer l'existence passée du token.

---

# 52. Soft Delete

Si les API Tokens utilisent Soft Delete, celui-ci ne doit pas être confondu avec la révocation.

```text
REVOKED
```

représente l'état de sécurité.

```text
DELETED
```

représente l'état de conservation de l'enregistrement.

---

# 53. Token Model

Le modèle API Token doit respecter les standards MAHLINE :

```text
BaseModel
ULID
timestamps
audit
relations
```

selon les capacités effectivement présentes sur la table.

---

# 54. Token Factory

Une Factory peut être utilisée pour les tests.

Elle doit pouvoir produire :

```text
active token
expired token
revoked token
token without expiration
```

sans jamais exposer de secrets réels.

---

# 55. Tests obligatoires

Les tests doivent couvrir au minimum :

```text
✓ token can be created
✓ token has ULID
✓ token belongs to user
✓ token secret is not stored in plain text
✓ token authenticates valid request
✓ invalid token is rejected
✓ revoked token is rejected
✓ expired token is rejected
✓ inactive user is rejected
✓ deleted user is rejected
✓ token permissions are enforced
✓ token can be revoked
✓ all user tokens can be revoked
✓ token creation is audited
✓ token revocation is audited
✓ token secret never appears in audit
✓ token secret never appears in logs
✓ token secret is returned only once
```

---

# 56. Security Invariants

### API-TOKEN-001

Un API Token est un secret.

### API-TOKEN-002

Le secret ne doit jamais être stocké en clair.

### API-TOKEN-003

Le secret ne doit jamais apparaître dans les logs.

### API-TOKEN-004

Le secret ne doit jamais apparaître dans l'audit.

### API-TOKEN-005

Un token révoqué ne peut plus être utilisé.

### API-TOKEN-006

Un token expiré ne peut plus être utilisé.

### API-TOKEN-007

Un User invalide ne peut pas utiliser ses tokens.

### API-TOKEN-008

Les tokens sont indépendants des sessions Web.

### API-TOKEN-009

La règle suivante concerne les sessions Web :

```text
ONE USER → ONE ACTIVE AUTHENTICATED SESSION
```

Elle n'impose pas un seul API Token par User.

### API-TOKEN-010

Les permissions d'un token suivent le principe du moindre privilège.

### API-TOKEN-011

La création et la révocation des tokens sont auditables.

### API-TOKEN-012

La révocation est définitive.

### API-TOKEN-013

La transmission des tokens en production exige HTTPS.

### API-TOKEN-014

Un token ne doit pas être placé dans une URL.

---

# 57. Documents liés

Cette spécification doit rester cohérente avec :

```text
AUTHENTICATION_SPECIFICATION.md
SESSION_SPECIFICATION.md
SESSION_STANDARD.md
PASSWORD_STANDARD.md
LOGIN_HISTORY_STANDARD.md
BROWSER_TRACKING_STANDARD.md
ROLE_PERMISSION_STANDARD.md
AUDIT_STANDARD.md
ULID_STANDARD.md
USER_SPECIFICATION.md
USER_MODEL_SPECIFICATION.md
USER_STATUS_SPECIFICATION.md
ID-001-ARCHITECTURE.md
```

---

# 58. Architecture finale

```text
                         USER
                           │
              ┌────────────┴────────────┐
              │                         │
              ▼                         ▼
       WEB AUTHENTICATION        API AUTHENTICATION
              │                         │
              ▼                         ▼
           SESSION                 API TOKEN
              │                         │
              │                    ┌────┴────┐
              │                    │         │
              │                  SCOPE    EXPIRATION
              │                    │         │
              └────────────┬───────┴─────────┘
                           │
                           ▼
                     AUTHORIZATION
                           │
                           ▼
                     BUSINESS ACTION
                           │
                           ▼
                         AUDIT
```

---

# 59. Règles architecturales gelées

MAHLINE retient définitivement :

```text
ONE USER → ONE ACTIVE AUTHENTICATED SESSION
```

pour les sessions Web.

Mais :

```text
ONE USER → MANY API TOKENS
```

reste autorisé.

Chaque API Token doit :

```text
être identifiable
être révocable
pouvoir expirer
respecter les permissions
être protégé comme un secret
être auditable sans exposer son secret
```

---

# 60. Statut

```text
DOCUMENT : API_TOKEN_SPECIFICATION.md
VERSION  : 1.0
DOMAINE  : IDENTITY / AUTHENTICATION
STATUT   : OFFICIEL — GELÉ
```

Toute modification de cette spécification doit entraîner une revue des documents Authentication, Session, Audit et Role/Permission concernés.
