# MAHLINE — USER MIGRATION SPECIFICATION

**Version:** 1.0
**Status:** GELÉ
**Domain:** Identity / Database

## 1. Purpose

This specification defines the database requirements for the Identity domain.

The database structure must remain compatible with the final User, Authentication, Authorization and Password implementations.

## 2. Users table

The `users` table contains the canonical user identity.

It must support:

* ULID primary key;
* first name;
* last name;
* display name;
* email;
* telephone;
* password hash;
* user status;
* locale;
* timezone;
* email verification timestamp;
* timestamps;
* soft deletion.

## 3. Primary key

Identity users use ULID identifiers.

The identifier is:

* string-compatible;
* non-incrementing;
* unique.

## 4. Password

The password column stores only the password hash.

Plain passwords must never be persisted.

## 5. User status

The database stores the user's lifecycle status.

The application-level `UserStatus` enum interprets this value.

## 6. Authentication sessions

Authentication sessions are stored independently from users.

Each authentication session references one user.

The architecture explicitly supports:

# ONE USER → MULTIPLE ACTIVE WEB SESSIONS

Therefore the database must **not** enforce a unique active-session constraint that would prevent multiple sessions for the same user.

Example:

```text
users
  │
  ├── authentication_session A → ACTIVE
  ├── authentication_session B → ACTIVE
  └── authentication_session C → ACTIVE
```

## 7. Login histories

Login history may reference:

* user;
* authentication session.

Unknown-user authentication attempts may exist without a user relationship.

## 8. Authorization

Authorization uses:

* `user_role`;
* `role_permission`.

The pivot tables use foreign keys and unique associations as defined by their migrations.

## 9. Password reset tokens

Password reset tokens use a dedicated table containing:

* ULID `id`;
* `user_id`;
* `token_hash`;
* `expires_at`;
* nullable `used_at`;
* `created_at`.

The reset token is stored as a hash.

The user foreign key uses cascade deletion.

## 10. API tokens

Sanctum personal access tokens are stored separately from Web authentication sessions.

API tokens must not share the Web session lifecycle.

## 11. Known devices

Known devices are stored separately and associated with users.

The user/device combination is unique after normalization.

## 12. Security events

Security events may exist:

* anonymously;
* for a known user.

Security-event metadata and occurrence timestamps are persisted according to the Identity security model.

## 13. Migration validation

Identity migrations must be validated through:

* migration tests;
* model tests;
* relationship tests;
* service tests;
* the complete Identity test suite.

## 14. Freeze rule

A migration change requires:

1. migration update;
2. affected tests;
3. model/service validation;
4. documentation update;
5. complete Identity suite execution.

The Identity domain is considered valid only when the complete suite remains green.
