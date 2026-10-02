# MAHLINE — TOKEN STANDARD

**Version:** 1.0
**Status:** GELÉ
**Domain:** Identity / Authentication

## 1. Purpose

This standard defines API token behavior and its separation from Web authentication.

## 2. Sanctum

API authentication uses Laravel Sanctum personal access tokens.

Personal access tokens use ULID identifiers in the MAHLINE Identity implementation.

## 3. Token storage

Persisted API tokens must not expose the plain token value.

The token representation stored by the authentication layer is hashed.

## 4. API authentication

Protected API endpoints must reject:

* guest requests;
* invalid tokens;
* revoked tokens.

A valid token authenticates the corresponding user.

## 5. Separation from Web sessions

API tokens and Web authentication sessions have independent lifecycles.

```text
USER
│
├── Web Session A
├── Web Session B
│
└── API Token A
```

Creating API Token A does not create Web Session C.

Revoking API Token A does not revoke Web Session A or B.

## 6. Multiple active Web sessions

The token architecture must preserve:

**ONE USER → MULTIPLE ACTIVE WEB SESSIONS**

API token management must never reintroduce a one-session-only rule.

## 7. Tests

Covered by:

* `PersonalAccessTokenTest`
* `SanctumAuthenticationTest`

## 8. Freeze rule

Changes to Sanctum integration, token persistence or Web/API separation require corresponding test and documentation changes.
