# ROLE_PERMISSION_STANDARD.md

# MAHLINE Framework

## Domaine : Identity

### Référence

ID-001 / ROLE-PERMISSION

### Version

1.0

### Statut

**OFFICIEL — GELÉ**

---

# 1. Objectif

Ce document définit le standard MAHLINE relatif à :

* l'utilisateur ;
* aux rôles ;
* aux permissions ;
* à l'attribution des rôles ;
* à l'autorisation des actions ;
* à la séparation entre authentification et autorisation ;
* à l'audit des changements de rôles et permissions.

Ce document constitue la référence technique pour l'implémentation du système Role / Permission.

---

# 2. Principe fondamental

MAHLINE sépare strictement :

```text
AUTHENTICATION
        │
        ▼
"Qui est connecté ?"
        │
        ▼
USER
```

et :

```text
AUTHORIZATION
        │
        ▼
"Que peut-il faire ?"
        │
        ▼
ROLE / PERMISSION
```

L'authentification ne donne aucune permission métier par elle-même.

---

# 3. Architecture

Le modèle d'autorisation est :

```text
USER
  │
  ├──────────────┐
  │              │
  ▼              ▼
ROLE          PERMISSION
  │
  ▼
PERMISSIONS
```

Un User peut posséder un ou plusieurs rôles.

Un Role possède une ou plusieurs permissions.

Une Permission représente une capacité précise.

---

# 4. Définitions

## 4.1 User

Le User représente l'identité authentifiée.

Exemple :

```text
User
├── id
├── first_name
├── last_name
├── email
├── status
└── ...
```

Le User ne doit pas contenir directement la liste complète des permissions métier.

---

# 5. Role

Un Role représente un ensemble logique de permissions.

Exemples :

```text
super_admin
admin
manager
cooperative_manager
accountant
customer
```

Les noms définitifs des rôles métier sont définis par les besoins fonctionnels de MAHLINE.

---

# 6. Permission

Une Permission représente une capacité atomique.

Exemples :

```text
users.view
users.create
users.update
users.delete

products.view
products.create
products.update
products.delete

orders.view
orders.create
orders.update
orders.cancel

invoices.view
invoices.create
invoices.send
```

Une permission doit représenter une action identifiable.

---

# 7. Convention de nommage

Format officiel :

```text
resource.action
```

Exemples :

```text
users.view
users.create
users.update
users.delete
```

Pour une action spécifique :

```text
invoices.send
orders.cancel
payments.refund
```

Le nom doit être :

* en minuscules ;
* stable ;
* explicite ;
* sans espace ;
* sans accent ;
* sans dépendance à l'interface utilisateur.

---

# 8. Principe de granularité

Une Permission doit rester suffisamment atomique pour permettre un contrôle précis.

À éviter :

```text
admin.all
```

pour les permissions métier ordinaires.

Préférer :

```text
products.view
products.create
products.update
products.delete
```

Un accès global peut toutefois être réservé à un mécanisme explicitement défini pour les administrateurs système.

---

# 9. Role → Permission

Un Role regroupe des Permissions.

Exemple :

```text
ROLE: product_manager

PERMISSIONS:
- products.view
- products.create
- products.update
- products.delete
```

Le Role n'est donc pas une permission.

---

# 10. User → Role

L'attribution normale des droits se fait via les rôles.

Exemple :

```text
USER
  │
  ▼
ROLE: accountant
  │
  ├── invoices.view
  ├── invoices.create
  └── invoices.send
```

---

# 11. User → Permission directe

Par défaut, MAHLINE privilégie :

```text
User → Role → Permission
```

Les permissions directement attribuées à un User sont interdites par défaut.

Elles ne peuvent être introduites que par une décision architecturale explicite.

Cela évite une architecture difficile à maintenir :

```text
User
 ├── Role A
 ├── Role B
 ├── Permission X
 ├── Permission Y
 └── Permission Z
```

---

# 12. Authentification ≠ Autorisation

Un User authentifié n'est pas automatiquement autorisé à effectuer toutes les actions.

Exemple :

```text
User
status = active
authenticated = true
```

ne signifie pas :

```text
can('users.delete') = true
```

La permission doit être évaluée séparément.

---

# 13. Condition préalable

Avant toute vérification de permission, le système doit disposer d'un User authentifié valide.

Règle :

```text
NOT AUTHENTICATED
        │
        ▼
NO AUTHORIZATION
```

---

# 14. User Status

Le système Role / Permission doit respecter le statut User.

Un User avec :

```text
status != active
```

ne doit pas être considéré comme autorisé à effectuer une opération authentifiée.

La condition générale est :

```text
authenticated
AND
status = active
AND
deleted_at IS NULL
AND
permission granted
```

---

# 15. Session

MAHLINE impose :

```text
ONE USER → ONE ACTIVE AUTHENTICATED SESSION
```

La session identifie le contexte d'authentification.

Elle ne doit pas être confondue avec les permissions.

Architecture :

```text
USER
 │
 ▼
AUTHENTICATION
 │
 ▼
ACTIVE SESSION
 │
 ▼
AUTHORIZATION
 │
 ├── ROLE
 │     └── PERMISSION
 │
 └── POLICY
```

---

# 16. Session révoquée

Si la session est révoquée :

```text
session = revoked
```

le User ne peut plus effectuer d'opérations authentifiées via cette session.

Même si le User possède encore :

```text
Role
Permission
```

cela ne permet pas de contourner l'authentification.

---

# 17. User Status et Permissions

Les permissions ne doivent jamais être utilisées pour contourner le statut du User.

Exemple :

```text
status = blocked
role = super_admin
permission = users.delete
```

Le User reste non authentifiable.

Le rôle ne remplace pas l'état du compte.

---

# 18. Role Status

Les Roles doivent eux-mêmes posséder un état si le domaine fonctionnel l'exige.

Lorsque le mécanisme de statut Role est introduit, un Role désactivé ne doit plus fournir ses permissions.

Exemple :

```text
role.status = inactive
```

Résultat :

```text
role permissions = unavailable
```

---

# 19. Permission Status

Les Permissions peuvent également être activées ou désactivées selon les besoins du système.

Une permission inactive ne doit jamais autoriser une action.

---

# 20. Priorité de sécurité

La décision d'autorisation doit suivre l'ordre logique :

```text
1. User existe
2. User non supprimé
3. User status = active
4. Session authentifiée
5. Session active
6. Role valide
7. Permission valide
8. Policy métier valide
```

Une condition échouée arrête l'autorisation.

---

# 21. Policies

Les Permissions déterminent la capacité générale.

Les Policies déterminent les règles contextuelles.

Exemple :

```text
permission:
products.update
```

ne signifie pas nécessairement que le User peut modifier **n'importe quel** produit.

Une Policy peut vérifier :

```text
User
Product
Cooperative
Ownership
Status
Business rules
```

---

# 22. Exemple

```text
User
  │
  └── Role: cooperative_manager
          │
          ├── products.view
          ├── products.create
          └── products.update
```

Lors d'une modification :

```text
User
 │
 ▼
authenticated?
 │
 ▼
status active?
 │
 ▼
permission products.update?
 │
 ▼
ProductPolicy
 │
 ▼
ALLOW / DENY
```

---

# 23. Deny by Default

MAHLINE applique le principe :

```text
DENY BY DEFAULT
```

Si aucune permission explicite n'autorise l'action :

```text
DENY
```

Une absence de permission ne doit jamais être interprétée comme une autorisation.

---

# 24. Permissions système

Certaines permissions peuvent être considérées comme critiques.

Exemples :

```text
users.delete
roles.update
permissions.update
audit.delete
```

Ces permissions doivent être protégées par des Policies supplémentaires si nécessaire.

---

# 25. Administration des rôles

La gestion des rôles doit elle-même être protégée.

Exemples :

```text
roles.view
roles.create
roles.update
roles.delete
roles.assign
```

---

# 26. Administration des permissions

La gestion des permissions doit être réservée aux utilisateurs autorisés.

Exemples :

```text
permissions.view
permissions.create
permissions.update
permissions.delete
```

Dans la majorité des cas, les permissions devraient être définies par le système et non créées librement depuis l'interface utilisateur.

---

# 27. Attribution d'un Role

L'attribution d'un rôle à un User est une opération sensible.

Elle doit être :

```text
AUTHENTICATED
+
AUTHORIZED
+
AUDITED
```

---

# 28. Suppression d'un Role

La suppression d'un rôle attribué doit être contrôlée.

Une suppression ne doit pas créer un état incohérent dans les relations User / Role.

Les contraintes de base de données doivent empêcher les références invalides.

---

# 29. Audit

Les opérations suivantes doivent être auditables :

```text
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

Les informations minimales peuvent inclure :

```text
actor
target
action
old_value
new_value
timestamp
```

---

# 30. Actor

Les changements d'autorisation doivent identifier l'acteur.

Les colonnes d'acteur d'audit utilisent le standard :

```text
created_by
updated_by
deleted_by
```

avec des identifiants ULID de longueur 26.

---

# 31. Security Principle

Un User ne doit pas pouvoir augmenter ses propres privilèges.

Exemple interdit :

```text
customer
    │
    └── s'attribue lui-même
            │
            ▼
        super_admin
```

Une opération d'attribution doit être autorisée par une Policy appropriée.

---

# 32. Privilege Escalation

Le système doit empêcher :

```text
privilege escalation
```

notamment :

* attribution d'un rôle supérieur ;
* modification de ses propres rôles ;
* modification de ses propres permissions ;
* création d'une permission donnant plus de privilèges ;
* modification d'un rôle système.

---

# 33. Rôles système

Les rôles critiques peuvent être déclarés comme rôles système.

Exemple :

```text
super_admin
```

Un rôle système ne doit pas être modifiable arbitrairement depuis l'interface métier.

Les opérations sensibles doivent être explicitement contrôlées.

---

# 34. Permissions système

Même principe pour les permissions critiques.

Une permission système doit posséder une définition stable.

Exemple :

```text
users.delete
audit.view
roles.assign
permissions.update
```

---

# 35. Cache des permissions

Si MAHLINE utilise un cache d'autorisation, celui-ci doit être invalidé après toute modification de :

```text
User Role
Role Permission
Permission
```

Aucune ancienne permission ne doit rester active après révocation.

---

# 36. Révocation d'un Role

Lorsqu'un rôle est retiré d'un User :

```text
USER
 │
 └── ROLE REMOVED
```

les permissions héritées de ce rôle ne doivent plus être considérées comme accordées.

Si nécessaire, les sessions doivent être réévaluées selon la politique de sécurité MAHLINE.

---

# 37. Modification des permissions d'un Role

Lorsqu'une permission est ajoutée ou retirée d'un rôle :

```text
ROLE
 │
 └── PERMISSIONS CHANGED
```

les autorisations effectives doivent refléter immédiatement la nouvelle configuration.

Un cache éventuel doit être invalidé.

---

# 38. Session et modification des privilèges

La modification des privilèges ne doit jamais permettre à une session révoquée de continuer à accéder aux ressources.

La politique de révocation des sessions lors d'une modification de privilèges doit être centralisée dans le domaine Identity / Security.

---

# 39. Une session par User

Règle architecturale gelée :

```text
ONE USER
     │
     ▼
ONE ACTIVE AUTHENTICATED SESSION
```

Lorsqu'un User se connecte depuis un nouvel appareil :

```text
NEW LOGIN
   │
   ▼
REVOKE PREVIOUS SESSION
   │
   ▼
CREATE NEW ACTIVE SESSION
```

Cette règle est indépendante du Role et des Permissions.

---

# 40. Browser Tracking

Le navigateur utilisé pour l'authentification peut être enregistré conformément à :

```text
BROWSER_TRACKING_STANDARD.md
```

Le navigateur ne constitue pas une permission.

---

# 41. Login History

Les événements liés à l'autorisation peuvent être corrélés aux événements d'authentification conformément à :

```text
LOGIN_HISTORY_STANDARD.md
```

Exemples :

```text
LOGIN_SUCCESS
LOGIN_FAILED
SESSION_REVOKED
```

---

# 42. Modèle relationnel recommandé

Architecture conceptuelle :

```text
users
  │
  │
  ▼
user_role
  │
  ▼
roles
  │
  │
  ▼
role_permission
  │
  ▼
permissions
```

Les tables de liaison doivent posséder les clés nécessaires et respecter les standards ULID / Foreign Key / Index de MAHLINE.

---

# 43. Relations

Relation User → Role :

```text
User belongsToMany Role
```

Relation Role → User :

```text
Role belongsToMany User
```

Relation Role → Permission :

```text
Role belongsToMany Permission
```

Relation Permission → Role :

```text
Permission belongsToMany Role
```

---

# 44. ULID

Tous les identifiants métier MAHLINE utilisent le standard ULID.

Les IDs des entités :

```text
users
roles
permissions
```

doivent être compatibles avec le standard ULID MAHLINE.

---

# 45. Soft Delete

Si les entités Role et Permission utilisent Soft Delete, celui-ci doit être traité indépendamment de leur état fonctionnel.

Une entité supprimée ne doit plus être utilisée pour accorder une autorisation.

---

# 46. Validation

Une attribution doit vérifier :

```text
User exists
Role exists
Role is usable
Permission exists
Permission is usable
Actor authorized
```

---

# 47. DTO

Les opérations complexes doivent utiliser des DTO plutôt que de transmettre directement des Request HTTP au domaine.

Architecture :

```text
Request
   │
   ▼
DTO
   │
   ▼
Action
   │
   ▼
Domain Service
```

---

# 48. Actions recommandées

Exemples :

```text
AssignRoleAction
RemoveRoleAction

CreateRoleAction
UpdateRoleAction
DeleteRoleAction

CreatePermissionAction
UpdatePermissionAction
DeletePermissionAction
```

Les noms définitifs doivent respecter les conventions MAHLINE.

---

# 49. Interdiction de dépendance au Request

Les Services et Actions du domaine ne doivent pas dépendre directement de :

```php
Illuminate\Http\Request
```

Les données nécessaires doivent être transportées via DTO / Value Object / paramètres typés.

---

# 50. Tests obligatoires

Les tests doivent couvrir :

```text
✓ User can have a role
✓ Role can have permissions
✓ User inherits role permissions
✓ missing permission is denied
✓ inactive User is denied
✓ blocked User is denied
✓ suspended User is denied
✓ deleted User is denied
✓ revoked session is denied
✓ role removal revokes effective permission
✓ permission removal revokes effective permission
✓ role assignment is audited
✓ role removal is audited
```

---

# 51. Tests de sécurité

Les tests doivent explicitement vérifier l'absence d'escalade de privilèges :

```text
✓ User cannot assign himself a privileged role
✓ User cannot grant himself a privileged permission
✓ User cannot modify protected roles
✓ User cannot modify protected permissions
```

---

# 52. Invariants

### ROLE-PERMISSION-001

Toute autorisation doit être explicitement accordée.

### ROLE-PERMISSION-002

Le système applique `DENY BY DEFAULT`.

### ROLE-PERMISSION-003

L'authentification est distincte de l'autorisation.

### ROLE-PERMISSION-004

Un User non actif ne peut pas utiliser ses permissions pour contourner son statut.

### ROLE-PERMISSION-005

Un User supprimé ne peut pas être autorisé.

### ROLE-PERMISSION-006

Une session révoquée ne peut pas accéder aux ressources protégées.

### ROLE-PERMISSION-007

Les changements de rôles sont auditables.

### ROLE-PERMISSION-008

Les changements de permissions sont auditables.

### ROLE-PERMISSION-009

Les privilèges ne peuvent pas être augmentés sans autorisation.

### ROLE-PERMISSION-010

Les permissions héritées d'un rôle supprimé ne sont plus effectives.

### ROLE-PERMISSION-011

Les permissions doivent être évaluées au moment de l'autorisation.

### ROLE-PERMISSION-012

Le système respecte :

```text
ONE USER → ONE ACTIVE AUTHENTICATED SESSION
```

---

# 53. Documents liés

Cette spécification doit rester cohérente avec :

```text
ID-001-ARCHITECTURE.md

USER_SPECIFICATION.md
USER_MODEL_SPECIFICATION.md
USER_STATUS_SPECIFICATION.md
USER_MIGRATION_SPECIFICATION.md

AUTHENTICATION_SPECIFICATION.md
AUTHENTICATION_STANDARD.md

SESSION_SPECIFICATION.md
SESSION_STANDARD.md

TOKEN_STANDARD.md
PASSWORD_STANDARD.md

LOGIN_HISTORY_STANDARD.md
BROWSER_TRACKING_STANDARD.md

AUDIT_STANDARD.md
SOFT_DELETE_STANDARD.md
ULID_STANDARD.md
FOREIGN_KEY_STANDARD.md
INDEX_STANDARD.md
```

Toute modification du modèle Role / Permission doit déclencher une revue des documents dépendants.

---

# 54. Règle architecturale finale

Le système Identity MAHLINE suit cette chaîne :

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
ROLE
 │
 ▼
PERMISSION
 │
 ▼
POLICY
 │
 ▼
AUTHORIZED ACTION
```

Aucun élément de cette chaîne ne doit être utilisé comme substitut d'un autre.

---

# 55. Statut du document

```text
DOCUMENT : ROLE_PERMISSION_STANDARD.md
VERSION  : 1.0
DOMAINE  : IDENTITY
STATUT   : OFFICIEL — GELÉ
```

Règles architecturales fondamentales :

```text
DENY BY DEFAULT

AUTHENTICATION ≠ AUTHORIZATION

ONE USER → ONE ACTIVE AUTHENTICATED SESSION
```
