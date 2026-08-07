# USER_MIGRATION_SPECIFICATION.md

## MAHLINE Framework V1

**Version:** 1.0
**Status:** Frozen
**Domain:** Identity

---

# 1. Purpose

This document defines the official database specification for the `users` table.

It is the single source of truth for:

* the migration;
* the User model;
* factories;
* repositories;
* DTOs;
* services;
* requests;
* resources;
* policies;
* automated tests.

---

# 2. Table Information

| Property         | Value              |
| ---------------- | ------------------ |
| Table            | users              |
| Primary Key      | id                 |
| Primary Key Type | ULID               |
| Engine           | InnoDB             |
| Charset          | utf8mb4            |
| Collation        | utf8mb4_unicode_ci |

---

# 3. Primary Key

| Column | Type | Nullable | Default   |
| ------ | ---- | -------- | --------- |
| id     | ULID | No       | Generated |

---

# 4. Identity Columns

| Column       | Type        | Nullable | Rules   |
| ------------ | ----------- | -------- | ------- |
| first_name   | string(100) | No       |         |
| last_name    | string(100) | No       |         |
| display_name | string(200) | Yes      |         |
| email        | string(255) | No       | Unique  |
| username     | string(100) | Yes      | Unique  |
| phone        | string(30)  | Yes      | Indexed |

---

# 5. Authentication

| Column         | Type   | Nullable | Rules   |
| -------------- | ------ | -------- | ------- |
| password       | string | No       | Hashed  |
| remember_token | string | Yes      | Laravel |

---

# 6. Status

| Column | Type       | Nullable | Default |
| ------ | ---------- | -------- | ------- |
| status | string(30) | No       | active  |

Enum:

```text
UserStatus
```

Allowed values:

* active
* pending
* inactive
* suspended
* archived

---

# 7. Email Verification

| Column            | Type      |
| ----------------- | --------- |
| email_verified_at | timestamp |

Nullable.

---

# 8. Preferences

| Column   | Type        | Default    |
| -------- | ----------- | ---------- |
| locale   | string(10)  | app locale |
| timezone | string(100) | UTC        |

---

# 9. Security Metadata

| Column        | Type       | Nullable |
| ------------- | ---------- | -------- |
| last_login_at | timestamp  | Yes      |
| last_login_ip | string(45) | Yes      |

Browser information belongs to Session and Login History, not the User table.

---

# 10. Audit Columns

Required.

| Column     |
| ---------- |
| created_by |
| updated_by |
| deleted_by |
| created_at |
| updated_at |

---

# 11. Soft Delete

Required.

| Column     |
| ---------- |
| deleted_at |

---

# 12. Indexes

Required indexes:

* email (unique)
* username (unique)
* phone
* status
* deleted_at

---

# 13. Foreign Keys

Audit fields reference:

* users.id

Rules:

* created_by → nullable
* updated_by → nullable
* deleted_by → nullable

---

# 14. Default Values

| Column   | Default              |
| -------- | -------------------- |
| status   | active               |
| locale   | config('app.locale') |
| timezone | UTC                  |

Application-level defaults should mirror migration defaults where appropriate.

---

# 15. Eloquent Casts

The User model should define:

```php
'email_verified_at' => 'datetime',
'password'          => 'hashed',
'last_login_at'     => 'datetime',
'status'            => UserStatus::class,
```

---

# 16. Hidden Attributes

Hidden by default:

* password
* remember_token

---

# 17. Fillable Attributes

Typical fillable attributes:

* first_name
* last_name
* display_name
* email
* username
* phone
* password
* status
* locale
* timezone

Audit fields are managed internally.

---

# 18. Relationships

User has many:

* Sessions
* Personal Access Tokens
* Login History
* Audit Logs
* Notifications

User belongs to many:

* Roles
* Permissions

Optional relationships:

* Avatar
* Addresses

---

# 19. Business Rules

* Email is required and unique.
* Username is optional but unique when present.
* Only `UserStatus::Active` users may authenticate.
* Passwords are always hashed.
* Browser metadata is not stored in the `users` table.
* Session information is stored separately.
* Login history is immutable.

---

# 20. Performance

Indexes should support:

* authentication by email;
* user lookup by username;
* filtering by status;
* filtering by phone;
* audit queries.

Avoid unnecessary duplicated data.

---

# 21. Security Notes

The following must never be stored in the `users` table:

* API tokens
* session identifiers
* browser fingerprints
* raw passwords
* password history
* authentication cookies

---

# 22. Migration Checklist

Before implementation, verify:

* ULID primary key
* Email unique
* Username unique (nullable)
* UserStatus enum
* Password hashing
* Email verification
* Audit columns
* Soft deletes
* Required indexes
* Foreign keys
* Automated tests

---

# Status

**This document is part of the official MAHLINE Framework V1 specification and is considered frozen.**
