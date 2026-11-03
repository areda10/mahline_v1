# MAHLINE — ROLE & PERMISSION STANDARD

**Version:** 1.1
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


## 8.1 Official user-management role matrix

The following permissions govern user management. Cooperative scope and allowed
status transitions are additionally enforced by `UserPolicy` and
`UserManagementService`.

| Permission | super_admin_root | super_admin | cooperative_admin | user |
|---|---:|---:|---:|---:|
| `users.view` | Yes | Yes | Yes | No |
| `users.create` | Yes | Yes | No | No |
| `users.activate` | Yes | Yes | No | No |
| `users.deactivate` | Yes | Yes | No | No |
| `users.suspend` | Yes | Yes | No | No |
| `users.archive` | Yes | No | No | No |
| `users.restore` | Yes | No | No | No |
| `users.delete` | Yes | No | No | No |
| `users.restore_deleted` | Yes | No | No | No |

### Scope and transition constraints

* `super_admin_root` can consult and manage users globally, including changing
  their own status. It is the only role allowed to archive, restore archived
  users, soft-delete users, and restore soft-deleted users.
* `super_admin` can consult users only in cooperatives assigned through
  `super_admin_cooperative`. It can create users and manage eligible statuses
  only in those assigned cooperatives. It cannot change its own status, lift a
  suspension, archive, or delete users.
* `cooperative_admin` can consult users only in its own cooperative. It cannot
  create users or change any user status, including its own. It cannot archive,
  delete, or restore users.
* `user` has no user-management permission.
* A user's status does not grant or remove role membership. Inactive users are
  denied authorization by `HasAuthorization::isAuthorized()`.

## 9. Tests

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

## 10. Change history

### Version 1.1

* Added the official user-management permission matrix.
* Restricted user creation to `super_admin_root` and `super_admin`.
* Confirmed that `cooperative_admin` may view users in its own cooperative
  but cannot create users or manage their statuses.
* Documented cooperative scope and status-transition constraints.

## 11. Freeze rule

Authorization behavior is frozen.

Any change to roles, permissions, Gates or authorization semantics requires synchronized code, tests and documentation.
