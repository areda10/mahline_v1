# USER_STANDARD.md

## MAHLINE Framework V1

**Version:** 1.0
**Status:** Frozen
**Domain:** Identity

---

# 1. Purpose

This document defines the official User standard for the MAHLINE Framework.

The User is the central identity of the application.

This specification defines:

* business rules;
* lifecycle;
* relationships;
* security requirements;
* responsibilities.

---

# 2. User Identity

Each user has a unique identity.

The primary identifier is:

* ULID

Business identifiers:

* Email (required)
* Username (optional)
* Full Name

---

# 3. User Lifecycle

A user progresses through the following lifecycle:

```text
Pending
    │
    ▼
Active
    │
    ├────────────► Suspended
    │                   │
    │                   ▼
    │                Active
    │
    ├────────────► Inactive
    │                   │
    │                   ▼
    │                Active
    │
    └────────────► Archived
```

Archived users cannot return to an active state.

---

# 4. User Status

Official statuses:

* Pending
* Active
* Inactive
* Suspended
* Archived

Status is represented by:

```php
UserStatus
```

---

# 5. User Profile

Every user may contain:

* First Name
* Last Name
* Display Name
* Avatar
* Phone Number
* Preferred Language
* Timezone

Additional profile information belongs to dedicated domains if needed.

---

# 6. Authentication

A user authenticates using:

* Email
* Password

Future versions may support:

* Username
* Phone Number
* Passkeys
* OAuth
* SSO

---

# 7. Email Verification

Email verification is supported.

Business rules may require verification before granting access to protected features.

---

# 8. Password

Passwords:

* are hashed;
* are never readable;
* follow PASSWORD_STANDARD.md.

---

# 9. Sessions

A user may own multiple active sessions depending on the configured policy.

Session management follows:

SESSION_STANDARD.md

---

# 10. API Tokens

A user may own multiple Personal Access Tokens.

Token management follows:

TOKEN_STANDARD.md

---

# 11. Roles

A user may have:

* zero roles;
* one role;
* multiple roles.

Role management follows:

ROLE_PERMISSION_STANDARD.md

---

# 12. Permissions

Permissions are resolved through:

* assigned roles;
* direct permissions.

Authorization decisions must be permission-based.

---

# 13. Login History

Every authentication event creates a Login History record.

History is immutable.

---

# 14. Browser Tracking

Authentication records browser metadata according to:

BROWSER_TRACKING_STANDARD.md

---

# 15. Audit

Important user actions are audited.

Examples:

* account creation;
* profile update;
* password change;
* email verification;
* role assignment;
* suspension;
* archival.

---

# 16. Relationships

The User has relationships with:

* Sessions
* Personal Access Tokens
* Login History
* Roles
* Permissions
* Audit Logs
* Avatar (optional)
* Addresses (optional)
* Notifications

Other domain relationships may be added without modifying the User standard.

---

# 17. Security Rules

Only users with the **Active** status may authenticate.

Suspended, Inactive, and Archived users must not authenticate.

Sensitive attributes must remain hidden from serialization.

---

# 18. Foundation Responsibilities

The Foundation provides:

* BaseAuthenticatable;
* shared authentication behavior;
* reusable abstractions.

Business rules belong exclusively to the Identity domain.

---

# 19. Identity Responsibilities

The Identity domain manages:

* user lifecycle;
* profile;
* authentication;
* authorization;
* sessions;
* tokens;
* login history;
* browser tracking;
* password management.

---

# 20. Development Rules

Every User component must:

* use ULIDs;
* use strict types;
* follow PSR-12;
* use Enums;
* use Repositories;
* use Services;
* use DTOs;
* use Policies;
* include automated tests.

---

# 21. Future Extensions

The architecture supports future features without breaking compatibility:

* Multi-factor authentication (MFA)
* Two-factor authentication (2FA)
* Passkeys (WebAuthn)
* OAuth providers
* SSO
* Multi-tenancy
* User impersonation
* Account recovery workflows

---

# 22. Checklist

User implementation must support:

* ULID identifier
* Email authentication
* UserStatus enum
* Password hashing
* Email verification
* Sessions
* Personal Access Tokens
* Login History
* Browser Tracking
* Roles & Permissions
* Audit integration
* Automated tests

---

# Status

**This document is part of the official MAHLINE Framework V1 specification and is considered frozen.**
