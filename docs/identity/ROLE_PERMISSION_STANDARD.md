# ROLE_PERMISSION_STANDARD.md

## MAHLINE Framework V1

**Version:** 1.0
**Status:** Frozen
**Domain:** Identity

---

# 1. Purpose

This document defines the official Role and Permission standard for the MAHLINE Framework.

Its objectives are to:

* implement Role-Based Access Control (RBAC);
* centralize authorization;
* simplify permission management;
* provide a scalable authorization architecture;
* integrate with Laravel Policies and Gates.

---

# 2. Authorization Strategy

Authentication determines **who the user is**.

Authorization determines **what the user can do**.

MAHLINE uses Role-Based Access Control (RBAC) with direct permissions when necessary.

---

# 3. Core Concepts

The authorization system is composed of:

* Users
* Roles
* Permissions

Relationships:

* User ↔ Role (Many-to-Many)
* Role ↔ Permission (Many-to-Many)
* User ↔ Permission (optional Many-to-Many)

---

# 4. Roles

A Role represents a business responsibility.

Examples:

* Super Administrator
* Administrator
* Manager
* Cooperative Manager
* Seller
* Customer
* Moderator
* Auditor

Roles are business concepts and belong to the Identity domain.

---

# 5. Permissions

A Permission represents a single action.

Naming convention:

```text
resource.action
```

Examples:

```text
users.view
users.create
users.update
users.delete

roles.view
roles.assign

catalog.products.view
catalog.products.create
catalog.products.update
catalog.products.delete

orders.view
orders.create
orders.cancel

finance.invoices.view
finance.invoices.create
```

Permissions must be granular and descriptive.

---

# 6. Authorization Rules

Authorization checks should be based on permissions, not role names.

Preferred:

```php
$user->can('users.create');
```

Avoid:

```php
$user->role === 'Administrator';
```

Business logic must remain independent from specific role names.

---

# 7. Multiple Roles

A user may have:

* zero roles;
* one role;
* multiple roles.

Authorization evaluates the union of granted permissions.

---

# 8. Direct Permissions

Users may receive permissions directly.

Direct permissions complement role permissions and do not replace them.

---

# 9. Permission Inheritance

Roles aggregate permissions.

Permissions themselves do not inherit from other permissions.

The framework does not support recursive permission inheritance.

---

# 10. Super Administrator

A configurable Super Administrator role may bypass normal authorization checks.

This behavior belongs to the Identity domain and must be explicitly configured.

---

# 11. Laravel Integration

Authorization integrates with:

* Policies
* Gates
* Middleware
* Blade directives

Authorization decisions should use Laravel's native authorization mechanisms.

---

# 12. Audit Integration

The following authorization events may be audited:

* role assigned;
* role removed;
* permission granted;
* permission revoked;
* authorization failure.

---

# 13. Relationships

User

* Belongs To Many Roles
* Belongs To Many Permissions (optional)

Role

* Belongs To Many Users
* Belongs To Many Permissions

Permission

* Belongs To Many Roles
* Belongs To Many Users (optional)

---

# 14. Naming Standards

Roles use descriptive business names.

Permissions follow the pattern:

```text
domain.resource.action
```

Examples:

```text
identity.users.view
identity.users.update

catalog.products.create

finance.invoices.export

orders.cancel
```

---

# 15. Foundation Responsibilities

The Foundation provides:

* authorization abstractions;
* shared interfaces;
* reusable base classes.

Business authorization rules belong exclusively to the Identity domain.

---

# 16. Identity Responsibilities

The Identity domain manages:

* roles;
* permissions;
* assignments;
* authorization services;
* permission resolution;
* role management.

---

# 17. Development Rules

Every authorization component must:

* use ULIDs;
* use strict types;
* follow PSR-12;
* use Policies;
* use Enums where appropriate;
* include automated tests.

---

# 18. Best Practices

Recommended practices:

* authorize by permission, not by role name;
* keep permissions granular;
* minimize privilege assignments;
* review permissions regularly;
* avoid hard-coded authorization rules.

---

# 19. Future Extensions

The architecture supports future additions without breaking compatibility:

* Permission groups
* Teams
* Organizations
* Multi-tenancy
* Context-aware permissions
* Time-limited permissions
* Delegated administration

---

# 20. Checklist

Role & Permission implementation must support:

* RBAC
* Multiple roles
* Direct permissions
* Laravel Policies
* Laravel Gates
* Audit integration
* ULID identifiers
* Configurable Super Administrator
* Automated tests

---

# Status

**This document is part of the official MAHLINE Framework V1 specification and is considered frozen.**
