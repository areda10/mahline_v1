# AUTHENTICATION_STANDARD.md

## MAHLINE Framework V1

**Version:** 1.0
**Status:** Frozen
**Domain:** Identity

---

# 1. Purpose

This document defines the official authentication standard for the MAHLINE Framework.

Its objectives are to:

* standardize authentication across the application;
* improve security;
* simplify maintenance;
* provide a consistent API for every authenticated user.

This document is the reference for every authentication-related component.

---

# 2. Authentication Strategy

MAHLINE uses Laravel's authentication system with framework extensions.

Authentication is based on:

* Laravel Authentication
* Laravel Sanctum
* ULID identifiers
* Domain-driven architecture
* BaseAuthenticatable
* Identity domain services

---

# 3. Authentication Identifier

The official login identifier is:

* Email Address

Future versions may optionally support:

* Username
* Phone Number

without changing the authentication architecture.

---

# 4. Password Standard

Passwords are never stored in plain text.

Requirements:

* Hashed using Laravel Hash
* Automatically cast using:

```php
'password' => 'hashed'
```

Passwords must never be:

* logged
* serialized
* returned by APIs
* exposed in DTOs

---

# 5. User Status

Authentication depends on UserStatus.

Allowed statuses:

* Active
* Pending
* Inactive
* Suspended
* Archived

Only:

```text
UserStatus::Active
```

is allowed to authenticate.

---

# 6. Email Verification

Email verification is supported.

Users may be required to verify their email before accessing protected features.

Verification uses Laravel's native email verification system.

---

# 7. Authentication Tokens

API authentication uses:

Laravel Sanctum

Rules:

* personal access tokens
* revocable
* individually identifiable
* expiration configurable
* auditable

---

# 8. Session Management

Every login creates a session record.

Each session stores:

* user
* browser
* operating system
* device type
* IP address
* user agent
* login time
* logout time
* last activity

---

# 9. Browser Tracking

Browser information is mandatory.

Each authentication records:

* browser
* browser version
* operating system
* platform
* device
* user agent

---

# 10. Concurrent Sessions

Multiple sessions are supported.

Authentication policies are configurable.

Supported policies:

* unlimited sessions
* one session only
* one session per browser
* one session per device

The active policy is configured in the Identity domain.

---

# 11. Session Revocation

Users can revoke:

* one session
* multiple sessions
* all sessions

Administrators may revoke sessions.

---

# 12. Token Revocation

Users can revoke:

* one token
* all tokens

Administrators may revoke tokens.

---

# 13. Login History

Every authentication event is recorded.

Events include:

* login success
* login failure
* logout
* password reset
* password change
* token creation
* token revocation
* session revocation

Login history is immutable.

---

# 14. Audit Integration

Authentication integrates with the Audit domain.

Every sensitive action is auditable.

Examples:

* login
* logout
* password update
* email verification
* session revocation
* token revocation

---

# 15. Security Rules

Authentication must reject:

* archived users
* suspended users
* inactive users

Passwords must never be recoverable.

Sensitive information must never be exposed.

---

# 16. Extension Points

Authentication may later support:

* Two-Factor Authentication (2FA)
* Passkeys (WebAuthn)
* OAuth Providers
* Social Login
* Single Sign-On (SSO)
* Multi-Factor Authentication (MFA)

without changing the core architecture.

---

# 17. Foundation Responsibilities

The Foundation provides:

* BaseAuthenticatable
* authentication abstractions
* common authentication helpers

The Foundation must not contain business rules.

---

# 18. Identity Responsibilities

The Identity domain manages:

* User
* UserStatus
* Sessions
* Tokens
* Login History
* Password policies
* Authentication services

Business rules belong exclusively to the Identity domain.

---

# 19. Development Rules

Every authentication component must:

* follow PSR-12
* use strict types
* use ULIDs
* use Enums
* use Repository pattern
* use Services
* use DTOs
* include automated tests

---

# 20. Checklist

Authentication implementation must satisfy:

* ULID identifiers
* Sanctum integration
* Email verification
* Session management
* Browser tracking
* Login history
* Audit integration
* Password hashing
* UserStatus validation
* Automated tests

---

# Status

**This document is part of the official MAHLINE Framework V1 specification and is considered frozen.**
