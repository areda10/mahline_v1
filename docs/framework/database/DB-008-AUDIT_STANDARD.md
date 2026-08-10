# AUDIT_STANDARD.md

# MAHLINE Framework

## Standard transversal — Audit

### Référence

CORE / DATABASE / AUDIT

### Version

1.0

### Statut

**OFFICIEL — GELÉ**

---

# 1. Objectif

Ce document définit le standard officiel MAHLINE concernant l'audit des données et des opérations métier.

L'audit permet de répondre aux questions :

```text
QUI ?
QUOI ?
QUAND ?
SUR QUOI ?
AVANT ?
APRÈS ?
DEPUIS QUEL CONTEXTE ?
```

L'audit doit permettre de reconstituer les changements importants effectués dans le système.

---

# 2. Principe fondamental

Toute opération métier importante doit pouvoir être tracée.

Le système distingue :

```text
AUDIT
```

de :

```text
LOG APPLICATION
```

et de :

```text
LOGIN HISTORY
```

Ces mécanismes ont des responsabilités différentes.

---

# 3. Audit ≠ Application Log

Les logs techniques servent principalement au diagnostic :

```text
exceptions
errors
warnings
debug
performance
```

L'audit sert à conserver une trace métier :

```text
User X
a effectué
Action Y
sur
Resource Z
à
Date/Heure T
```

---

# 4. Audit ≠ Login History

`LOGIN_HISTORY_STANDARD.md` concerne spécifiquement :

```text
login
logout
failed login
session
authentication events
```

L'Audit concerne les changements et actions métier significatifs.

Exemple :

```text
LOGIN_SUCCESS
```

relève du Login History.

Alors que :

```text
USER_STATUS_CHANGED
```

relève de l'Audit.

---

# 5. Audit ≠ Session

La session représente le contexte d'authentification actif.

L'audit conserve la trace d'une opération.

Exemple :

```text
SESSION
    │
    └── active session

AUDIT
    │
    └── SESSION_REVOKED
```

---

# 6. Audit Actor

Les opérations auditées doivent identifier l'acteur lorsqu'un acteur existe.

Colonnes standard :

```text
created_by
updated_by
deleted_by
```

Ces colonnes utilisent des identifiants ULID de longueur 26.

---

# 7. Audit Actor Columns

Les colonnes d'acteur sont fournies par le macro :

```php
$table->auditActorColumns();
```

Le macro est défini dans :

```text
app/Core/Database/Macros/AuditActorMacros.php
```

et enregistré via :

```text
App\Core\Database\Providers\DatabaseMacroServiceProvider
```

---

# 8. Colonnes d'acteur

Le macro crée :

```text
created_by
updated_by
deleted_by
```

Caractéristiques :

```text
Type logique : CHAR(26)
Nullable     : YES
```

Ces colonnes peuvent être `NULL` lorsqu'aucun acteur identifiable n'existe.

---

# 9. Exemple SQL

Une table auditable peut contenir :

```sql
created_by CHAR(26) NULL,
updated_by CHAR(26) NULL,
deleted_by CHAR(26) NULL
```

---

# 10. Pourquoi nullable ?

Certaines opérations peuvent être effectuées :

```text
par le système
par une tâche planifiée
par une commande CLI
pendant une migration
pendant une initialisation
```

Dans ces cas :

```text
created_by = NULL
```

peut être valide.

---

# 11. Audit système

Lorsqu'une opération est effectuée automatiquement :

```text
actor = SYSTEM
```

Le système doit utiliser le mécanisme d'identification d'acteur système défini par l'architecture.

Il ne faut pas inventer un ULID User lorsqu'aucun User réel n'est responsable.

---

# 12. Actions auditées

Les opérations suivantes doivent généralement être auditées :

```text
CREATE
UPDATE
DELETE
RESTORE
STATUS_CHANGE
ROLE_ASSIGN
ROLE_REMOVE
PERMISSION_CHANGE
LOGIN_SECURITY_CHANGE
SESSION_REVOCATION
```

La liste exacte dépend du domaine.

---

# 13. Événements d'audit

Les événements doivent utiliser des noms stables.

Exemples :

```text
USER_CREATED
USER_UPDATED
USER_DELETED
USER_RESTORED
USER_STATUS_CHANGED

ROLE_CREATED
ROLE_UPDATED
ROLE_DELETED
ROLE_ASSIGNED
ROLE_REMOVED

PERMISSION_CREATED
PERMISSION_UPDATED
PERMISSION_DELETED
PERMISSION_GRANTED
PERMISSION_REVOKED
```

---

# 14. User Status

Tout changement de statut User doit être audité.

Exemple :

```text
USER_STATUS_CHANGED
```

avec :

```text
old_status = active
new_status = suspended
```

---

# 15. Session

La révocation d'une session pour raison de sécurité peut générer un événement d'audit.

Exemple :

```text
SESSION_REVOKED
```

Le mécanisme Session conserve cependant sa propre responsabilité fonctionnelle.

---

# 16. Nouvelle connexion

MAHLINE impose :

```text
ONE USER → ONE ACTIVE AUTHENTICATED SESSION
```

Lorsqu'un User se connecte depuis un nouvel appareil :

```text
NEW LOGIN
    │
    ▼
REVOKE PREVIOUS SESSION
    │
    ▼
CREATE NEW SESSION
```

La révocation de l'ancienne session peut être auditée :

```text
SESSION_REVOKED
reason = new_login
```

---

# 17. Browser Tracking

Les informations du navigateur peuvent être associées au contexte d'audit lorsque nécessaire.

Référence :

```text
BROWSER_TRACKING_STANDARD.md
```

Le navigateur ne constitue cependant pas l'identité de l'acteur.

---

# 18. Login History

Les événements d'authentification sont conservés conformément à :

```text
LOGIN_HISTORY_STANDARD.md
```

L'Audit peut référencer ou compléter ces événements, mais ne doit pas devenir un duplicata complet du Login History.

---

# 19. Actor

Un acteur peut être :

```text
AUTHENTICATED USER
SYSTEM
CONSOLE COMMAND
SCHEDULED JOB
```

L'identification exacte dépend du contexte.

---

# 20. Actor User

Lorsqu'un User authentifié réalise une opération :

```text
actor_id = User.id
```

L'identifiant doit respecter le standard ULID.

---

# 21. Actor absent

Lorsqu'aucun User n'est disponible :

```text
actor_id = NULL
```

L'événement doit néanmoins conserver suffisamment d'informations pour indiquer qu'il s'agit d'une opération système si cela est nécessaire.

---

# 22. Before / After

Pour les modifications importantes, l'audit doit pouvoir conserver :

```text
old_value
new_value
```

Exemple :

```text
old_status = active
new_status = blocked
```

---

# 23. Données sensibles

Les données sensibles ne doivent jamais être enregistrées inutilement dans l'audit.

En particulier, ne jamais journaliser :

```text
password
password confirmation
plain-text tokens
session secrets
authentication secrets
API secrets
```

---

# 24. Password

Une modification de mot de passe peut être auditée :

```text
PASSWORD_CHANGED
```

mais jamais avec le mot de passe lui-même.

Interdit :

```text
old_password = "..."
new_password = "..."
```

---

# 25. Token

Une opération concernant un token peut être auditée :

```text
TOKEN_CREATED
TOKEN_REVOKED
TOKEN_EXPIRED
```

mais le token secret en clair ne doit jamais être stocké dans l'audit.

---

# 26. PII

Les données personnelles doivent être limitées au strict nécessaire.

Exemples de données à traiter avec prudence :

```text
email
telephone
IP address
browser information
address
```

Le standard d'audit doit respecter les exigences de confidentialité applicables.

---

# 27. Immutabilité

Les événements d'audit doivent être considérés comme historiques.

Un événement d'audit ne doit pas être modifié pour masquer une opération passée.

Principe :

```text
AUDIT HISTORY
    ↓
APPEND ONLY
```

---

# 28. Suppression d'un Audit

La suppression d'un enregistrement d'audit doit être extrêmement restrictive.

Un utilisateur métier ordinaire ne doit jamais pouvoir supprimer son historique d'audit.

Toute suppression exceptionnelle doit elle-même être contrôlée et auditée si l'architecture le permet.

---

# 29. Soft Delete

L'utilisation du Soft Delete sur les entités métier ne doit pas effacer l'historique d'audit.

Exemple :

```text
Product deleted
```

doit conserver l'information historique correspondante.

---

# 30. Audit des suppressions

Lors d'une suppression logique :

```text
deleted_at
deleted_by
```

peuvent être renseignés.

Exemple :

```text
deleted_at = 2026-08-10 10:30:00
deleted_by = <ULID>
```

---

# 31. Audit des restaurations

Une restauration doit également être traçable.

Exemple :

```text
USER_RESTORED
PRODUCT_RESTORED
ORDER_RESTORED
```

---

# 32. Audit des rôles

Les opérations Role doivent être auditées.

Exemples :

```text
ROLE_CREATED
ROLE_UPDATED
ROLE_DELETED
ROLE_ASSIGNED
ROLE_REMOVED
```

---

# 33. Audit des permissions

Les opérations Permission doivent être auditées.

Exemples :

```text
PERMISSION_CREATED
PERMISSION_UPDATED
PERMISSION_DELETED
PERMISSION_GRANTED
PERMISSION_REVOKED
```

---

# 34. Privilege Escalation

Toute modification sensible des privilèges doit être traçable.

Exemple :

```text
USER
  │
  ▼
ROLE_ASSIGNED
  │
  ▼
super_admin
```

L'audit doit permettre de déterminer :

```text
qui a attribué le rôle
à quel User
quand
quel rôle
depuis quel contexte
```

---

# 35. Audit et Authorization

Une opération doit être autorisée avant d'être exécutée.

Flux :

```text
AUTHENTICATION
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

L'audit ne remplace jamais l'autorisation.

---

# 36. Audit et Transaction

Lorsqu'une opération métier et son audit doivent être atomiques, ils doivent utiliser la même transaction.

Principe :

```text
BEGIN TRANSACTION

business operation
audit operation

COMMIT
```

En cas d'échec :

```text
ROLLBACK
```

---

# 37. Audit et Events

Les événements métier peuvent déclencher le mécanisme d'audit.

Exemple :

```text
UserStatusChanged
       │
       ▼
Audit Listener
       │
       ▼
USER_STATUS_CHANGED
```

La conception exacte dépend de l'architecture Event / Listener de MAHLINE.

---

# 38. Audit et Observers

Les Eloquent Observers peuvent être utilisés lorsque l'audit est directement lié au cycle de vie d'un modèle.

Ils ne doivent cependant pas contenir de logique métier complexe.

---

# 39. Audit et Services

Pour une opération métier complexe, l'audit doit être orchestré au niveau approprié :

```text
Action
  │
  ├── Business operation
  │
  └── Audit
```

Le `BaseService` ne doit pas dépendre directement de `Request`.

---

# 40. Audit et DTO

Les données nécessaires à l'audit peuvent être transportées par DTO.

Exemple :

```text
ChangeUserStatusDTO
    │
    ├── userId
    ├── newStatus
    └── reason
```

L'audit peut ensuite conserver :

```text
oldStatus
newStatus
reason
actor
```

---

# 41. Audit Actor Macros

Le macro :

```php
$table->auditActorColumns();
```

est destiné aux migrations des tables nécessitant les colonnes d'acteur.

Il ne doit pas être utilisé automatiquement sur les tables qui n'ont aucune responsabilité d'audit.

---

# 42. Enregistrement du macro

Le macro est enregistré par :

```php
App\Core\Database\Providers\DatabaseMacroServiceProvider
```

via :

```php
AuditActorMacros::register();
```

La chaîne de responsabilité est :

```text
DatabaseMacroServiceProvider
        │
        ▼
BlueprintMacros
        │
        ▼
AuditActorMacros
```

---

# 43. BlueprintMacros

Le fichier :

```text
app/Core/Database/Macros/BlueprintMacros.php
```

centralise les macros Blueprint MAHLINE.

Il doit enregistrer :

```php
ColumnMacros::register();
AuditMacros::register();
ForeignKeyMacros::register();
IndexMacros::register();
SoftDeleteMacros::register();
AuditActorMacros::register();
```

si `AuditActorMacros` fait partie des macros Blueprint utilisées par les migrations.

---

# 44. Migration

Exemple :

```php
Schema::create('products', function (Blueprint $table) {
    $table->ulid('id')->primary();

    // colonnes métier

    $table->auditActorColumns();

    $table->timestamps();
});
```

L'ordre exact des colonnes doit respecter les standards de migration MAHLINE.

---

# 45. Toutes les tables ?

La règle MAHLINE concernant l'audit doit être appliquée selon la classification de la table.

Une table métier nécessitant la traçabilité doit intégrer les colonnes d'acteur.

Les tables purement techniques ne doivent pas recevoir automatiquement des colonnes métier inutiles.

---

# 46. BaseModel

Le `BaseModel` constitue la base des modèles métier.

Il doit rester compatible avec :

```text
ULID
timestamps
Soft Delete
Audit
Audit Actor
```

lorsque ces fonctionnalités sont nécessaires au modèle concerné.

---

# 47. Audit Actor et BaseModel

Le BaseModel ne doit pas obliger tous les modèles à avoir des colonnes :

```text
created_by
updated_by
deleted_by
```

si la table correspondante ne les possède pas.

Le comportement doit être cohérent avec la capacité d'audit du modèle.

---

# 48. Audit automatique

L'audit automatique peut être implémenté à travers :

```text
Traits
Observers
Events
Listeners
Actions
Services
```

Le choix doit respecter la responsabilité de chaque couche.

---

# 49. Interdiction

Il est interdit de mettre toute la logique d'audit directement dans :

```text
Controller
Request
Blade
```

Le Controller doit uniquement orchestrer la requête HTTP.

---

# 50. Audit des actions système

Les opérations automatiques importantes doivent être traçables.

Exemples :

```text
scheduled status change
automatic session revocation
automatic token revocation
system synchronization
```

L'acteur doit être identifiable comme système lorsqu'aucun User n'est responsable.

---

# 51. Audit et confidentialité

L'audit est soumis aux mêmes exigences de sécurité que les autres données sensibles de MAHLINE.

Accès recommandé :

```text
AUDIT VIEW
AUDIT EXPORT
AUDIT ADMINISTRATION
```

doivent être protégés par Role / Permission.

---

# 52. Permission d'accès à l'audit

Exemples :

```text
audit.view
audit.export
audit.manage
```

Les noms définitifs doivent suivre le standard Role / Permission.

---

# 53. Audit Export

Tout export d'audit doit être considéré comme une opération sensible.

Un export peut lui-même être audité :

```text
AUDIT_EXPORTED
```

avec :

```text
actor
timestamp
scope
filters
```

sans exposer inutilement de données sensibles.

---

# 54. Audit et Performance

L'audit ne doit pas dégrader inutilement les performances des opérations critiques.

Lorsque nécessaire, une architecture asynchrone peut être utilisée.

Cependant, les événements de sécurité critiques doivent être traités selon le niveau de garantie requis.

---

# 55. Ordre de priorité

Pour les opérations critiques :

```text
SECURITY
   >
DATA INTEGRITY
   >
AUDIT
   >
PERFORMANCE
```

L'optimisation ne doit pas supprimer une garantie d'audit obligatoire.

---

# 56. Invariants

### AUDIT-001

Les opérations métier importantes doivent être traçables.

### AUDIT-002

L'acteur doit être identifié lorsqu'il existe.

### AUDIT-003

Les colonnes d'acteur utilisent des ULID de longueur 26.

### AUDIT-004

`created_by`, `updated_by` et `deleted_by` sont nullable.

### AUDIT-005

Les mots de passe ne doivent jamais être enregistrés en clair.

### AUDIT-006

Les secrets de tokens ne doivent jamais être enregistrés en clair.

### AUDIT-007

L'audit ne remplace pas le Login History.

### AUDIT-008

L'audit ne remplace pas la Session.

### AUDIT-009

L'audit ne remplace pas l'Authorization.

### AUDIT-010

Les opérations d'autorisation sensibles doivent être auditables.

### AUDIT-011

Les suppressions ne doivent pas effacer silencieusement l'historique.

### AUDIT-012

Les opérations atomiques doivent conserver leur cohérence avec l'audit.

### AUDIT-013

Un User supprimé ne doit pas devenir authentifiable.

### AUDIT-014

Un User non actif ne doit pas utiliser ses permissions pour contourner son statut.

### AUDIT-015

Le système respecte :

```text
ONE USER → ONE ACTIVE AUTHENTICATED SESSION
```

---

# 57. Tests obligatoires

Les tests du système Audit doivent vérifier au minimum :

```text
✓ audit actor columns exist
✓ created_by is nullable
✓ updated_by is nullable
✓ deleted_by is nullable
✓ actor IDs use ULID
✓ audit event is created
✓ audit event identifies actor
✓ audit event stores relevant changes
✓ password is never stored
✓ token secret is never stored
✓ deleted entity remains auditable
✓ restored entity creates audit event
✓ role assignment is audited
✓ role removal is audited
✓ permission changes are audited
✓ user status changes are audited
✓ session revocation can be audited
```

---

# 58. Tests du macro

Fichier :

```text
tests/Framework/Database/Macros/AuditActorMacrosTest.php
```

Il doit vérifier :

```text
✓ macro is registered
✓ created_by exists
✓ updated_by exists
✓ deleted_by exists
✓ columns are nullable
✓ columns have length 26
✓ register is idempotent
```

---

# 59. Documentation du macro

Fichier :

```text
docs/framework/database/AuditActorMacros.md
```

Il documente spécifiquement :

```text
$table->auditActorColumns();
```

et son implémentation technique.

---

# 60. Architecture finale

```text
                    BUSINESS ACTION
                          │
                          ▼
                    AUTHORIZATION
                          │
                          ▼
                    DOMAIN ACTION
                          │
             ┌────────────┴────────────┐
             │                         │
             ▼                         ▼
       DATABASE CHANGE              AUDIT
             │                         │
             └────────────┬────────────┘
                          ▼
                       HISTORY
```

Pour Identity :

```text
USER
 │
 ├── STATUS
 │
 ▼
AUTHENTICATION
 │
 ▼
ONE ACTIVE SESSION
 │
 ▼
AUTHORIZATION
 │
 ├── ROLE
 │     └── PERMISSION
 │
 ▼
BUSINESS ACTION
 │
 ▼
AUDIT
```

---

# 61. Documents liés

Ce standard doit rester cohérent avec :

```text
ID-001-ARCHITECTURE.md

USER_SPECIFICATION.md
USER_MODEL_SPECIFICATION.md
USER_STATUS_SPECIFICATION.md
USER_MIGRATION_SPECIFICATION.md
USER_FACTORY_SPECIFICATION.md

AUTHENTICATION_SPECIFICATION.md
AUTHENTICATION_STANDARD.md

SESSION_SPECIFICATION.md
SESSION_STANDARD.md

TOKEN_STANDARD.md
PASSWORD_STANDARD.md

LOGIN_HISTORY_STANDARD.md
BROWSER_TRACKING_STANDARD.md

ROLE_PERMISSION_STANDARD.md

AUDIT_ACTOR_COLUMNS.md
AuditActorMacros.md

ULID_STANDARD.md
SOFT_DELETE_STANDARD.md
FOREIGN_KEY_STANDARD.md
INDEX_STANDARD.md
TABLE_STANDARD.md
COLUMN_STANDARD.md
```

---

# 62. Règles architecturales gelées

MAHLINE retient les règles suivantes :

```text
AUDIT ≠ LOG
AUDIT ≠ LOGIN HISTORY
AUDIT ≠ SESSION
AUDIT ≠ AUTHORIZATION
```

et :

```text
ONE USER → ONE ACTIVE AUTHENTICATED SESSION
```

ainsi que :

```text
DENY BY DEFAULT
```

et :

```text
NO SECRETS IN AUDIT
```

---

# 63. Statut du document

```text
DOCUMENT : AUDIT_STANDARD.md
VERSION  : 1.0
DOMAINE  : CORE / DATABASE
STATUT   : OFFICIEL — GELÉ
```

Toute modification du modèle d'audit doit entraîner une revue des documents Identity, Database et Security concernés.
