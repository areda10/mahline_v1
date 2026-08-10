# USER_STATUS_SPECIFICATION.md

# MAHLINE Framework

## Domaine : Identity

### Référence

ID-001 / USER-STATUS

### Version

1.0

### Statut

**OFFICIEL — GELÉ**

---

# 1. Objectif

Ce document définit le système de statut d'un User dans MAHLINE.

Il précise :

* les statuts autorisés ;
* leur signification ;
* les transitions ;
* leur impact sur l'authentification ;
* leur impact sur les sessions ;
* leur impact sur les tokens ;
* leur comportement avec le Soft Delete ;
* les règles métier ;
* les tests obligatoires.

---

# 2. Principe

Le statut d'un User détermine son état fonctionnel dans le système Identity.

Le statut ne doit pas être confondu avec :

```text
deleted_at
```

ou :

```text
email_verified_at
```

ou :

```text
session.active
```

Chaque information possède une responsabilité différente.

---

# 3. Statuts officiels

MAHLINE définit les statuts suivants :

```text
active
inactive
blocked
suspended
```

---

# 4. active

## Valeur

```text
active
```

## Signification

Le User est actif et peut utiliser les fonctionnalités auxquelles ses permissions lui donnent accès.

Un User `active` peut être authentifié sous réserve des autres contrôles de sécurité.

Conditions minimales :

```text
status = active
deleted_at IS NULL
```

---

# 5. inactive

## Valeur

```text
inactive
```

## Signification

Le compte est désactivé.

Un User `inactive` ne peut pas ouvrir une nouvelle session authentifiée.

Une session existante doit être révoquée lorsque le User passe de `active` à `inactive`.

---

# 6. blocked

## Valeur

```text
blocked
```

## Signification

Le compte est bloqué pour des raisons de sécurité, d'administration ou de politique interne.

Un User `blocked` ne peut pas être authentifié.

Toute session authentifiée active doit être révoquée lors du passage à `blocked`.

Les tokens actifs doivent également être invalidés selon la politique Token.

---

# 7. suspended

## Valeur

```text
suspended
```

## Signification

Le compte est temporairement suspendu.

Un User `suspended` ne peut pas ouvrir une nouvelle session authentifiée.

La session active doit être révoquée lors de la suspension.

La réactivation peut restaurer le statut :

```text
suspended → active
```

sous réserve des règles métier et de sécurité.

---

# 8. Résumé

| Statut      | Authentification | Session active | Compte supprimé |
| ----------- | ---------------: | -------------: | --------------: |
| `active`    |              Oui | Oui, maximum 1 |             Non |
| `inactive`  |              Non |            Non |             Non |
| `blocked`   |              Non |            Non |             Non |
| `suspended` |              Non |            Non |             Non |

---

# 9. Règle d'authentification

Un User peut être authentifié uniquement si :

```text
status = active
AND
deleted_at IS NULL
```

Les autres contrôles de sécurité restent obligatoires.

Exemple :

```text
status = active
deleted_at = NULL
email_verified_at = valid
password = valid
```

Le User peut alors être authentifié.

---

# 10. Statut ≠ Email Verification

Le statut User et la vérification email sont deux mécanismes indépendants.

Exemple :

```text
status = active
email_verified_at = NULL
```

Le compte peut être actif mais non vérifié.

La possibilité d'authentification dépend alors de la politique définie dans `AUTHENTICATION_SPECIFICATION.md`.

---

# 11. Statut ≠ Soft Delete

Un User supprimé logiquement possède :

```text
deleted_at IS NOT NULL
```

Même si :

```text
status = active
```

il ne doit pas être authentifiable.

La condition effective est :

```text
status = active
AND
deleted_at IS NULL
```

---

# 12. Statut et Session

Le changement de statut doit avoir un impact immédiat sur les sessions.

Lorsqu'un User passe de :

```text
active
```

vers :

```text
inactive
```

ou :

```text
blocked
```

ou :

```text
suspended
```

la session authentifiée active doit être révoquée.

---

# 13. Règle architecturale de session

La règle gelée reste :

```text
ONE USER → ONE ACTIVE AUTHENTICATED SESSION
```

Un User ne peut jamais avoir plusieurs sessions authentifiées actives.

---

# 14. Changement de statut vers un état non actif

Exemple :

```text
User
status = active
session = ACTIVE
```

Action :

```text
status → blocked
```

Résultat :

```text
User
status = blocked

Session
status = REVOKED
```

---

# 15. Réactivation

Un User `inactive` ou `suspended` peut être réactivé selon les autorisations nécessaires.

Exemple :

```text
inactive → active
```

ou :

```text
suspended → active
```

La réactivation ne doit pas recréer automatiquement une session.

Le User doit effectuer une nouvelle authentification.

---

# 16. Réactivation et session

Une réactivation ne doit jamais produire :

```text
status = active
session = ACTIVE
```

automatiquement.

Le résultat doit être :

```text
status = active
session = NONE
```

jusqu'à une nouvelle authentification réussie.

---

# 17. blocked

Le statut `blocked` représente un état de sécurité plus restrictif.

Une réactivation d'un User `blocked` ne doit pas être effectuée automatiquement.

Elle doit être explicitement autorisée par la logique métier et les permissions appropriées.

---

# 18. Transitions autorisées

Transitions standard :

```text
active → inactive
active → blocked
active → suspended

inactive → active
inactive → blocked

suspended → active
suspended → blocked

blocked → active
blocked → inactive
```

Les transitions peuvent être restreintes par les politiques d'autorisation.

---

# 19. Transitions interdites par défaut

Une transition ne doit pas être réalisée implicitement.

Exemple :

```text
blocked → active
```

ne doit jamais être déclenché automatiquement par une simple connexion.

Une action explicite de réactivation est nécessaire.

---

# 20. Diagramme des états

```text
                       ┌───────────┐
                       │  ACTIVE   │
                       └─────┬─────┘
                         │   │   │
              ┌──────────┘   │   └──────────┐
              ▼              ▼              ▼
        ┌──────────┐   ┌──────────┐   ┌───────────┐
        │ INACTIVE │   │ SUSPENDED│   │  BLOCKED  │
        └────┬─────┘   └────┬─────┘   └─────┬─────┘
             │              │               │
             └──────┐   ┌───┘               │
                    ▼   ▼                   │
                   ACTIVE ◄─────────────────┘
```

Les transitions exactes doivent être contrôlées par les Actions / Services Identity.

---

# 21. UserStatus Enum

Le statut doit être représenté par un Enum MAHLINE.

Emplacement recommandé :

```text
app/Domains/Identity/Users/Enums/UserStatus.php
```

Namespace :

```php
App\Domains\Identity\Users\Enums
```

Valeurs :

```php
ACTIVE = 'active';
INACTIVE = 'inactive';
BLOCKED = 'blocked';
SUSPENDED = 'suspended';
```

Le nom exact de l'Enum doit respecter les conventions MAHLINE.

---

# 22. Exemple d'Enum

```php
<?php

declare(strict_types=1);

namespace App\Domains\Identity\Users\Enums;

enum UserStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case BLOCKED = 'blocked';
    case SUSPENDED = 'suspended';
}
```

L'Enum doit rester compatible avec les standards MAHLINE `BaseEnum` lorsque celui-ci est applicable.

---

# 23. Cast du modèle

Le modèle `User` doit caster le statut vers l'Enum.

Exemple :

```php
protected function casts(): array
{
    return [
        'status' => UserStatus::class,
        'email_verified_at' => 'datetime',
    ];
}
```

Ainsi :

```php
$user->status
```

retourne :

```text
UserStatus
```

et non une chaîne arbitraire.

---

# 24. Valeur SQL

La base de données conserve la valeur string :

```text
active
inactive
blocked
suspended
```

Le modèle convertit cette valeur vers :

```text
UserStatus
```

---

# 25. Valeur par défaut

La migration doit définir :

```text
status DEFAULT 'active'
```

Tout nouveau User possède donc par défaut :

```text
status = active
```

sauf indication métier explicite.

---

# 26. Création d'un User

Lorsqu'un User est créé normalement :

```text
status = active
```

Le User peut ensuite être désactivé, suspendu ou bloqué par une opération métier autorisée.

---

# 27. Modification du statut

Le statut ne doit pas être modifié directement depuis une Request non contrôlée.

Flux recommandé :

```text
Request
   │
   ▼
FormRequest
   │
   ▼
DTO
   │
   ▼
ChangeUserStatusAction
   │
   ▼
UserStatus
   │
   ▼
Session / Token / Audit
```

---

# 28. Action dédiée

Une Action dédiée est recommandée :

```text
app/Domains/Identity/Users/Actions/ChangeUserStatusAction.php
```

Elle centralise les effets secondaires du changement de statut.

---

# 29. Service

Si la logique nécessite plusieurs opérations, elle peut être orchestrée par un Service Identity.

Exemple :

```text
UserStatusService
```

Le Service peut coordonner :

```text
User
Session
Token
Audit
Login History
```

---

# 30. Audit

Tout changement de statut doit être auditable.

Exemples :

```text
USER_STATUS_CHANGED
```

avec des informations telles que :

```text
old_status
new_status
actor
timestamp
reason
```

La collecte exacte dépend du système Audit.

---

# 31. Login History

Un changement de statut affectant une session peut également générer un événement Login History.

Exemple :

```text
SESSION_REVOKED
```

lorsqu'une session active est révoquée à cause d'un changement de statut.

---

# 32. Tokens

Lorsqu'un User passe vers :

```text
inactive
blocked
suspended
```

les tokens d'authentification actifs doivent être invalidés lorsque le mécanisme Token MAHLINE les utilise.

---

# 33. Notifications

Une notification peut être déclenchée lors d'un changement de statut lorsque cette fonctionnalité est prévue.

Exemples :

```text
USER_SUSPENDED
USER_BLOCKED
USER_REACTIVATED
```

Les notifications ne constituent cependant pas une responsabilité du modèle User.

---

# 34. Autorisations

La modification du statut doit être protégée par les permissions appropriées.

Un User ne doit pas pouvoir modifier arbitrairement son propre statut si la politique de sécurité ne l'autorise pas.

Les règles d'autorisation sont définies par :

```text
Role / Permission / Policy
```

---

# 35. Soft Delete

Le Soft Delete est indépendant du statut.

Lorsqu'un User est supprimé :

```text
deleted_at != NULL
```

Il doit être considéré comme non authentifiable, quel que soit son statut.

---

# 36. Restore

Lorsqu'un User est restauré :

```text
deleted_at = NULL
```

son statut précédent peut être conservé.

Exemple :

```text
status = suspended
deleted_at = NULL
```

Le User reste suspendu.

La restauration ne doit pas automatiquement transformer le statut en `active`.

---

# 37. Restauration et session

La restauration d'un User ne crée aucune session.

Après restauration :

```text
User = authentifiable seulement si status = active
Session = NONE
```

Une nouvelle authentification est nécessaire.

---

# 38. Sécurité

Un User `blocked` ne doit jamais être considéré comme authentifiable simplement parce que :

```text
email_verified_at IS NOT NULL
```

ou parce qu'un ancien token existe.

Le statut de sécurité est prioritaire dans la décision d'accès.

---

# 39. Invariants

### USER-STATUS-001

Tous les Users possèdent un statut valide.

### USER-STATUS-002

Le statut par défaut est `active`.

### USER-STATUS-003

Les statuts sont représentés par `UserStatus`.

### USER-STATUS-004

Un User non actif ne peut pas ouvrir une nouvelle session authentifiée.

### USER-STATUS-005

Un User passant vers `inactive`, `blocked` ou `suspended` perd sa session active.

### USER-STATUS-006

Une réactivation ne crée jamais automatiquement une session.

### USER-STATUS-007

Un User supprimé ne peut pas être authentifié.

### USER-STATUS-008

Le changement de statut est auditable.

### USER-STATUS-009

Les tokens actifs peuvent être révoqués lors d'un changement vers un statut non actif.

### USER-STATUS-010

Le statut ne remplace pas `deleted_at`.

### USER-STATUS-011

Le statut ne remplace pas `email_verified_at`.

### USER-STATUS-012

Le statut ne remplace pas le système de Session.

### USER-STATUS-013

Le système respecte :

```text
ONE USER → ONE ACTIVE AUTHENTICATED SESSION
```

---

# 40. Tests obligatoires

Fichier recommandé :

```text
tests/Framework/Domains/Identity/Users/Enums/UserStatusTest.php
```

Tests minimum :

```text
✓ active exists
✓ inactive exists
✓ blocked exists
✓ suspended exists
✓ values are correct
✓ enum is string backed
```

---

# 41. Tests du modèle

`UserTest.php` doit vérifier :

```text
✓ status casts to UserStatus
✓ default status is active
✓ invalid status cannot be represented
```

---

# 42. Tests du changement de statut

Fichier recommandé :

```text
tests/Framework/Domains/Identity/Users/Actions/ChangeUserStatusActionTest.php
```

Doit notamment vérifier :

```text
✓ active → inactive
✓ active → blocked
✓ active → suspended
✓ inactive → active
✓ suspended → active
✓ session is revoked
✓ no new session is created
✓ audit event is generated
```

---

# 43. Règle finale

Le statut User est une propriété métier du compte.

Il ne doit jamais être utilisé comme substitut de :

```text
Authentication
Session
Token
Email Verification
Soft Delete
Permission
Role
```

Architecture finale :

```text
                    USER
                      │
             ┌────────┴────────┐
             │                 │
          STATUS          deleted_at
             │                 │
     ┌───────┼───────┐         │
     ▼       ▼       ▼         ▼
  ACTIVE  INACTIVE BLOCKED   DELETED
             │
          SUSPENDED
```

Et pour l'authentification :

```text
USER
 │
 ├── status = active
 ├── deleted_at = NULL
 │
 ▼
AUTHENTICATION
 │
 ▼
ONE ACTIVE SESSION
```

---

# 44. Documents liés

Cette spécification doit rester cohérente avec :

```text
ID-001-ARCHITECTURE.md

USER_SPECIFICATION.md
USER_MODEL_SPECIFICATION.md
USER_MIGRATION_SPECIFICATION.md
USER_FACTORY_SPECIFICATION.md
USER_STATUS_SPECIFICATION.md

AUTHENTICATION_SPECIFICATION.md
AUTHENTICATION_STANDARD.md

SESSION_SPECIFICATION.md
SESSION_STANDARD.md

TOKEN_STANDARD.md
PASSWORD_STANDARD.md

LOGIN_HISTORY_STANDARD.md
BROWSER_TRACKING_STANDARD.md

ROLE_PERMISSION_STANDARD.md

AUDIT_STANDARD.md
```

Toute modification des statuts ou de leurs effets doit entraîner une revue des documents concernés.

---

# 45. Statut du document

**USER_STATUS_SPECIFICATION.md**

```text
VERSION : 1.0
STATUT  : OFFICIEL — GELÉ
DOMAINE : IDENTITY
```

Règle fondamentale :

```text
ACTIVE USER
    │
    ▼
AUTHENTICATED
    │
    ▼
ONE ACTIVE AUTHENTICATED SESSION
```

**ONE USER → ONE ACTIVE AUTHENTICATED SESSION** est une règle architecturale obligatoire et gelée.
