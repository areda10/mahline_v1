# MAHLINE — ROLE & PERMISSION STANDARD

**Version:** 1.0
**Status:** GELÉ
**Domain:** Identity / Authorization

## 1. Purpose

MAHLINE authorization is based on users, roles and permissions.

## 2. Roles

A role:

* uses a ULID primary key;
* has a name;
* has a unique slug;
* may have a description;
* has an active state;
* uses timestamps;
* supports soft deletion.

A role can have multiple permissions and multiple users.

## 3. Permissions

A permission:

* uses a ULID primary key;
* has a name;
* has a unique slug;
* may have a description;
* has an active state;
* uses timestamps;
* supports soft deletion.

A permission can belong to multiple roles.

## 4. Pivot relationships

The authorization model uses:

```text
User
  │
  └── user_role ── Role
                       │
                       └── role_permission ── Permission
```

The pivot relationships support attachment and detachment.

## 5. User authorization

A user may obtain a permission through a role.

Authorization methods include:

* `hasRole()`
* `hasAnyRole()`
* `hasPermission()`
* `hasAnyPermission()`
* `isAuthorized()`

## 6. User status

Only an active user may be authorized.

The following statuses are denied:

* pending;
* inactive;
* suspended;
* archived.

## 7. Gates

Authorization Gates must:

* allow an active user with the required permission;
* deny an active user without permission;
* allow a permission inherited through a role;
* deny unknown abilities;
* throw the appropriate authorization exception when explicit authorization is requested and denied.

## 8. Tests

Covered by:

* `HasAuthorizationTest`
* `AuthorizationGateTest`
* `AuthorizationGateExceptionTest`
* `RoleTest`
* `PermissionTest`
* `RolePermissionRelationshipTest`
* `UserRoleRelationshipTest`

Final validation:

**41 tests / 53 assertions passed**

## 9. Freeze rule

Authorization behavior is frozen.

Any change to roles, permissions, Gates or authorization semantics requires synchronized code, tests and documentation.
