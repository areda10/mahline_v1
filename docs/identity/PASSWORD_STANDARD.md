# PASSWORD_STANDARD.md

## MAHLINE Framework V1

**Version:** 1.0
**Status:** Frozen
**Domain:** Identity

---

# 1. Purpose

This document defines the official password management standard for the MAHLINE Framework.

Its objectives are to:

* protect user credentials;
* enforce secure password policies;
* standardize password handling;
* support password lifecycle management;
* integrate with the Identity and Audit domains.

---

# 2. Password Strategy

Passwords are managed using Laravel's native hashing system.

Passwords are:

* hashed before storage;
* never reversible;
* never stored in plain text;
* never logged;
* never exposed through APIs.

---

# 3. Hashing Algorithm

The framework uses Laravel's configured password hasher.

Requirements:

* password hashing must use Laravel Hash;
* password verification must use Laravel Hash;
* hashing algorithm upgrades must be transparent.

Application code must never implement custom hashing algorithms.

---

# 4. Storage Rules

Passwords are stored only in the `password` column.

The column must:

* be hidden from serialization;
* use the `hashed` cast;
* never be returned by DTOs;
* never be exposed by Resources.

---

# 5. Password Requirements

The default policy requires:

* minimum length: 12 characters;
* at least one uppercase letter;
* at least one lowercase letter;
* at least one number;
* at least one special character.

The policy must remain configurable.

---

# 6. Password Validation

Passwords must be validated:

* during registration;
* during password change;
* during password reset;
* when updated by an administrator.

Validation rules belong to the Identity domain.

---

# 7. Password Confirmation

Sensitive operations require password confirmation.

Examples:

* change password;
* delete account;
* revoke all sessions;
* revoke all tokens;
* change email address.

---

# 8. Password Change

Changing a password must:

* hash the new password;
* invalidate the previous password;
* update the audit log;
* optionally revoke active sessions;
* optionally revoke active tokens.

Policy enforcement belongs to the Identity domain.

---

# 9. Password Reset

Password reset must use secure, time-limited reset tokens.

Requirements:

* single-use tokens;
* configurable expiration;
* automatic invalidation after use.

The framework relies on Laravel's password reset infrastructure.

---

# 10. Password History

Password history is optional.

When enabled:

* previous passwords are hashed;
* reused passwords may be rejected;
* history length is configurable.

---

# 11. Failed Authentication

The framework should support throttling after repeated failed login attempts.

Examples:

* temporary lockout;
* exponential backoff;
* configurable retry limits.

---

# 12. Password Expiration

Password expiration is optional.

Supported policies:

* never expires;
* fixed lifetime;
* administrator-defined lifetime.

Users must be notified before expiration when applicable.

---

# 13. Security Rules

Passwords must never:

* appear in logs;
* appear in exceptions;
* appear in audit payloads;
* be transmitted in URLs;
* be stored in cookies;
* be cached in plain text.

---

# 14. Audit Integration

The following events are auditable:

* password created;
* password changed;
* password reset requested;
* password reset completed;
* password policy violation.

The password value itself is never recorded.

---

# 15. Session & Token Integration

After a password change, the Identity domain may:

* revoke all sessions;
* revoke all API tokens;
* require fresh authentication.

The active policy is configurable.

---

# 16. Foundation Responsibilities

The Foundation provides:

* common authentication abstractions;
* password helper integration.

Business rules are implemented by the Identity domain.

---

# 17. Identity Responsibilities

The Identity domain manages:

* password validation;
* password changes;
* password reset;
* password history;
* password policies;
* session and token revocation after password changes.

---

# 18. Development Rules

Every password-related component must:

* use strict types;
* follow PSR-12;
* rely on Laravel Hash;
* include automated tests;
* integrate with the Audit domain.

---

# 19. Best Practices

Recommended practices:

* use long passphrases;
* avoid password reuse;
* enable multi-factor authentication when available;
* revoke compromised sessions immediately;
* monitor repeated authentication failures.

---

# 20. Checklist

Password implementation must support:

* secure hashing;
* configurable validation rules;
* password confirmation;
* password reset;
* optional password history;
* optional password expiration;
* audit integration;
* session/token revocation policies;
* automated tests.

---

# Status

**This document is part of the official MAHLINE Framework V1 specification and is considered frozen.**
