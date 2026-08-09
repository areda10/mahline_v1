# USER_STATUS_SPECIFICATION

## MAHLINE Framework

### Domaine

Identity

### Composant

User

### Sous-composant

User Status

### Document parent

USER_SPECIFICATION.md

### Document ID

ID-001.2

### Version

1.0

### Statut

OFFICIEL — GELÉ

---

# Objectif

Ce document définit les spécifications fonctionnelles du statut de l'entité **User**.

Le statut permet de déterminer l'état fonctionnel et opérationnel d'un compte utilisateur.

Le statut est indépendant :

* des rôles ;
* des permissions ;
* du type d'utilisateur ;
* des sessions ;
* des API Tokens.

---

# Responsabilité

`UserStatus` est exclusivement responsable de représenter l'état du compte utilisateur.

Il ne détermine pas les droits d'accès de l'utilisateur.

Les droits d'accès sont déterminés par :

* les rôles ;
* les permissions ;
* les politiques d'autorisation.

---

# Valeurs officielles

MAHLINE définit les cinq statuts suivants :

| Nom Enum | Valeur | Signification |
|---|---|---|
| `ACTIVE` | `active` | Compte actif |
| `INACTIVE` | `inactive` | Compte désactivé |
| `SUSPENDED` | `suspended` | Compte temporairement suspendu |
| `PENDING` | `pending` | Compte en attente d'activation ou de validation |
| `ARCHIVED` | `archived` | Compte archivé |

Aucune autre valeur ne peut être ajoutée sans modification de cette spécification.

---

# ACTIVE

## Valeur

```text
active
Description

Le compte est actif.

Un compte ACTIVE peut être utilisé normalement sous réserve :

d'une authentification valide ;
des règles de sécurité ;
des rôles et permissions applicables.
Authentification
AUTORISÉE

Sous réserve des autres contrôles de sécurité.

INACTIVE
Valeur
inactive
Description

Le compte est désactivé.

Un compte INACTIVE reste présent dans le système mais ne peut pas être utilisé pour une authentification normale.

Authentification
REFUSÉE
SUSPENDED
Valeur
suspended
Description

Le compte est temporairement suspendu.

La suspension peut être déclenchée par :

une action administrative ;
une règle de sécurité ;
une procédure métier ;
une détection de comportement anormal.

Un compte suspendu reste conservé dans le système.

Authentification
REFUSÉE
PENDING
Valeur
pending
Description

Le compte est en attente d'une activation ou d'une validation.

Ce statut peut notamment être utilisé lorsqu'une étape préalable est nécessaire avant l'activation complète du compte.

Exemples :

vérification de l'adresse e-mail ;
validation administrative ;
activation initiale du compte ;
validation d'une invitation.
Authentification

Par défaut :

REFUSÉE

Une politique d'authentification spécifique pourra autoriser certains traitements limités pour les comptes PENDING.

Cette politique devra être définie dans la documentation Authentication.

ARCHIVED
Valeur
archived
Description

Le compte est archivé.

L'archivage indique que le compte n'est plus actif dans le fonctionnement courant du système.

Les données historiques et les relations nécessaires à la traçabilité doivent être conservées conformément aux politiques d'archivage de MAHLINE.

Authentification
REFUSÉE
Résumé des statuts
Statut	Compte utilisable	Authentification
active	Oui	Autorisée
inactive	Non	Refusée
suspended	Non	Refusée
pending	Non	Refusée par défaut
archived	Non	Refusée
Status ≠ Role

Le statut du compte ne définit pas le rôle de l'utilisateur.

Exemple :

User
├── status = active
└── role = admin

Le même utilisateur pourrait avoir :

User
├── status = suspended
└── role = admin

Le rôle admin existe toujours, mais le compte est suspendu.

Admin et Super Admin

ADMIN et SUPER_ADMIN ne sont pas des valeurs de UserStatus.

Ils appartiennent au système :

Roles
Permissions
RolePermissions
UserRoles

Les types d'utilisateurs définis dans USER_SPECIFICATION.md :

Super Administrateur ;
Administrateur ;
Gestionnaire ;
Employé ;
Client ;
Partenaire ;
API User ;

sont représentés par les rôles et permissions appropriés.

Ils ne doivent donc pas être ajoutés à UserStatus.

Status ≠ Permission

Le statut ne définit aucune permission.

Il ne contient pas de valeurs telles que :

users.create
users.update
users.delete
products.manage
orders.manage

Les permissions appartiennent au système Authorization.

Enum PHP

L'implémentation sera :

app/Core/Foundation/Enums/Identity/UserStatus.php

L'Enum sera un Backed Enum de type string.

Valeurs obligatoires :

ACTIVE = 'active';
INACTIVE = 'inactive';
SUSPENDED = 'suspended';
PENDING = 'pending';
ARCHIVED = 'archived';

L'Enum doit respecter les conventions définies par :

BaseEnum
BaseEnumContract
InteractsWithEnum
InvalidEnumValueException
BaseEnum

UserStatus doit utiliser l'infrastructure commune des Enums MAHLINE.

Les fonctionnalités communes suivantes doivent être disponibles :

all()
values()
names()
options()
labels()
has()
hasName()
tryFromName()
fromName()
random()
label()
value()
toArray()
toArrayList()

Aucune logique métier d'authentification ne doit être placée dans UserStatus.

Database

La colonne correspondante dans la table users est :

status

Caractéristiques :

Propriété	Valeur
Type	string
Nullable	NON
Default	active

Le statut enregistré dans la base de données doit toujours correspondre à une valeur officielle de UserStatus.

Valeur par défaut

Lors de la création normale d'un utilisateur, le statut par défaut est :

active

Une création avec un autre statut doit être explicitement justifiée par une règle métier.

Authentification

Les règles générales d'authentification sont définies dans :

AUTHENTICATION_SPECIFICATION.md

Règle initiale :

ACTIVE     → autorisée
INACTIVE   → refusée
SUSPENDED  → refusée
PENDING    → refusée par défaut
ARCHIVED   → refusée

UserStatus ne doit pas lui-même effectuer les contrôles d'authentification.

Sessions

Le statut d'un utilisateur peut entraîner la révocation ou l'invalidation de ses sessions selon les règles définies dans :

SESSION_SPECIFICATION.md

En particulier, les changements vers :

inactive
suspended
archived

doivent être compatibles avec la politique de révocation des sessions de MAHLINE.

API Tokens

Les règles relatives aux API Tokens sont définies dans :

API_TOKEN_SPECIFICATION.md

Un compte qui n'est plus autorisé à s'authentifier ne doit pas pouvoir utiliser ses API Tokens actifs.

Les mécanismes précis de révocation ou de blocage sont définis par la spécification API Token.

Transitions

Les transitions entre statuts appartiennent à la logique métier.

L'Enum ne doit pas contenir les règles de transition.

Exemples de transitions possibles :

PENDING
   │
   ▼
ACTIVE
ACTIVE
   │
   ├──→ INACTIVE
   │
   ├──→ SUSPENDED
   │
   └──→ ARCHIVED
SUSPENDED
   │
   └──→ ACTIVE
INACTIVE
   │
   └──→ ACTIVE

Les transitions autorisées seront définies dans les services/actions Identity appropriés.

Archivage

ARCHIVED ne signifie pas nécessairement suppression physique.

Un utilisateur archivé doit conserver les informations nécessaires à :

l'audit ;
la traçabilité ;
l'historique ;
les relations métier nécessaires.

L'archivage est différent du Soft Delete.

Soft Delete

Le statut :

archived

ne remplace pas :

deleted_at

Les deux mécanismes ont des responsabilités différentes.

Status

Représente l'état fonctionnel du compte.

Soft Delete

Représente la suppression logique de l'enregistrement.

Audit

Les changements de statut d'un utilisateur doivent être auditables conformément aux règles du domaine Audit.

Un changement de :

active
inactive
suspended
pending
archived

doit pouvoir être retracé.

Sécurité

Les modifications de statut doivent être protégées par les permissions appropriées.

Un utilisateur ne doit pas pouvoir modifier librement son propre statut.

Les actions administratives de changement de statut doivent être journalisées conformément aux standards d'audit et de sécurité.

Tests obligatoires

Le test principal sera :

tests/Framework/Foundation/Enums/Identity/UserStatusTest.php

Les tests doivent vérifier au minimum :

enum contains ACTIVE
enum contains INACTIVE
enum contains SUSPENDED
enum contains PENDING
enum contains ARCHIVED

values are correct
names are correct
options are correct
labels are correct

has works
hasName works

tryFrom works
tryFromName works

from works
fromName works

invalid values are rejected
Tests d'intégration futurs

Des tests supplémentaires devront couvrir ultérieurement :

authentification avec compte ACTIVE ;
authentification avec compte INACTIVE ;
authentification avec compte SUSPENDED ;
authentification avec compte PENDING ;
authentification avec compte ARCHIVED ;
révocation des sessions ;
comportement des API Tokens ;
audit des changements de statut.

Ces tests ne font pas partie du test de l'Enum lui-même.

Relations conceptuelles
User
│
├── status
│     └── UserStatus
│
├── roles
│     └── Role
│           └── Permissions
│
├── sessions
├── api_tokens
├── login_history
├── devices
├── browser_sessions
├── media
└── audit_logs
Règles d'architecture
UserStatus est un Backed Enum.
Les valeurs sont des chaînes.
Cinq statuts sont officiellement définis.
active est le statut par défaut.
UserStatus ne contient aucun rôle.
UserStatus ne contient aucune permission.
ADMIN n'est pas un statut.
SUPER_ADMIN n'est pas un statut.
Les types d'utilisateurs sont définis par les rôles et permissions.
Les transitions appartiennent à la logique métier.
Les règles d'authentification appartiennent au domaine Authentication.
Les règles de session appartiennent au domaine Session.
Les règles API Token appartiennent au composant API Token.
archived ne remplace pas deleted_at.
Les changements de statut doivent être auditables.
Toute nouvelle valeur nécessite une modification de cette spécification.
Toute modification du comportement doit être couverte par des tests.
Documents associés
USER_SPECIFICATION.md
USER_MODEL_SPECIFICATION.md
USER_MIGRATION_SPECIFICATION.md
AUTHENTICATION_SPECIFICATION.md
SESSION_SPECIFICATION.md
API_TOKEN_SPECIFICATION.md
ROLE_PERMISSION_STANDARD.md
AUDIT_STANDARD.md
SOFT_DELETE_STANDARD.md
Validation

Le composant UserStatus est conforme lorsque :

les cinq valeurs sont implémentées ;
les valeurs sont conformes à cette spécification ;
l'Enum respecte BaseEnum ;
les tests de l'Enum sont passants ;
la colonne users.status respecte la spécification ;
la valeur par défaut est active ;
les règles d'authentification respectent le statut ;
les changements de statut sont auditables.
Historique
Version 1.0
Création de la spécification User Status.
Alignement avec USER_SPECIFICATION.md.
Définition des cinq statuts officiels.
Clarification de la séparation entre statut, rôle et permission.
Clarification des règles Admin et Super Admin.
Définition des responsabilités Authentication, Session, API Token et Audit.
Définition des règles d'archivage et de Soft Delete.
Statut du document

OFFICIEL — GELÉ


### Point important

Avec ton `USER_SPECIFICATION.md`, je corrige donc notre décision précédente :

**UserStatus aura bien 5 valeurs**, pas 3 :

```text
ACTIVE
INACTIVE
SUSPENDED
PENDING
ARCHIVED