# TOKEN_STANDARD.md

## MAHLINE Framework V1

**Version:** 1.0
**Status:** Frozen
**Domain:** Identity

---

# 1. Purpose

This document defines the official Personal Access Token standard for the MAHLINE Framework.

Its objectives are to:

* secure API authentication;
* manage user access tokens;
* provide complete token traceability;
* support token revocation;
* integrate with the Identity and Audit domains.

This document is the reference for every token-related component.

---

# 2. Authentication Strategy

MAHLINE uses **Laravel Sanctum** for Personal Access Tokens.

Sanctum is the official token provider for:

* REST APIs
* Mobile applications
* Desktop applications
* Internal services

---

# 3. Token Identifier

Every token has:

* ULID primary key (framework-managed entities)
* Sanctum token identifier
* unique token name
* user relationship

Tokens are never identified by incremental IDs in MAHLINE-managed entities.

---

# 4. Token Ownership

A token always belongs to one authenticated user.

Relationship:

* User → Has Many Tokens
* Token → Belongs To User

Tokens are personal and must never be shared.

---

# 5. Token Information

Every token stores:

* user_id
* token name
* abilities (permissions)
* creation date
* last usage date
* expiration date (optional)
* revocation date (optional)
* status

Additional metadata may be stored by the Identity domain if required.

---

# 6. Token Status

A token may have one of the following states:

* Active
* Expired
* Revoked

Only **Active** tokens may authenticate requests.

---

# 7. Token Abilities

Tokens may define one or more abilities.

Examples:

* *
* users.read
* users.write
* catalog.read
* catalog.write
* orders.read
* orders.write

Abilities follow the principle of least privilege.

---

# 8. Token Expiration

Expiration is configurable.

Supported policies:

* Never expires
* Fixed lifetime
* Sliding expiration
* Custom expiration

Expired tokens must no longer authenticate requests.

---

# 9. Token Revocation

Tokens may be revoked by:

* the owner;
* an administrator;
* a security policy;
* automatic expiration.

Revocation is permanent.

---

# 10. Token Rotation

The framework supports token rotation.

Typical scenarios:

* password change;
* security incident;
* administrator action;
* scheduled rotation policy.

Old tokens must be revoked after successful rotation.

---

# 11. Token Usage Tracking

Every token stores:

* last_used_at
* creation date
* expiration date
* revocation date (if applicable)

Additional tracking may include:

* IP address
* browser
* device
* application name

---

# 12. Security Rules

Tokens must:

* never be stored in plain text;
* never be returned after creation;
* never appear in logs;
* never be exposed by APIs.

Only the token hash is persisted.

---

# 13. Audit Integration

Every important token event is auditable.

Examples:

* token created;
* token used;
* token expired;
* token revoked;
* token rotated.

---

# 14. Relationships

User

* Has Many Tokens

Token

* Belongs To User

---

# 15. Foundation Responsibilities

The Foundation provides:

* authentication abstractions;
* common token helpers;
* shared interfaces.

The Foundation contains no business rules.

---

# 16. Identity Responsibilities

The Identity domain manages:

* token creation;
* token validation;
* token revocation;
* token expiration;
* token rotation;
* token policies.

---

# 17. Development Rules

Every token component must:

* use strict types;
* follow PSR-12;
* support Enums;
* integrate with Sanctum;
* include automated tests;
* integrate with the Audit domain.

---

# 18. API Security

Tokens are transmitted only through secure HTTPS connections.

Authorization header:

```text
Authorization: Bearer {token}
```

Tokens must never be passed through query parameters.

---

# 19. Best Practices

Recommended practices:

* Use descriptive token names.
* Grant only the required abilities.
* Rotate long-lived tokens.
* Revoke unused tokens.
* Monitor suspicious activity.
* Audit every sensitive token operation.

---

# 20. Checklist

Token implementation must support:

* Laravel Sanctum
* Token abilities
* Token expiration
* Token revocation
* Token rotation
* Audit integration
* HTTPS-only transmission
* Secure storage
* Automated tests

---

# Status

**This document is part of the official MAHLINE Framework V1 specification and is considered frozen.**
