# LOGIN_HISTORY_STANDARD.md

## MAHLINE Framework V1

**Version:** 1.0
**Status:** Frozen
**Domain:** Identity

---

# 1. Purpose

This document defines the official Login History standard for the MAHLINE Framework.

Its objectives are to:

* provide complete authentication traceability;
* improve security monitoring;
* support auditing and investigations;
* detect suspicious authentication activity;
* maintain an immutable history of authentication events.

---

# 2. Scope

Login History records authentication events.

It is **not** responsible for:

* managing active sessions;
* managing API tokens;
* storing audit logs.

Those responsibilities belong to dedicated components.

---

# 3. Event Strategy

A history record is created for every authentication-related event.

Events must never modify previous records.

Login History is append-only.

---

# 4. Recorded Events

The framework records:

* Login Success
* Login Failure
* Logout
* Password Changed
* Password Reset
* Email Verified
* Account Locked
* Account Unlocked
* Session Revoked
* Token Revoked

Additional events may be added without changing the architecture.

---

# 5. Stored Information

Each record stores at least:

* ULID
* user_id (nullable for failed logins)
* event type
* event result
* occurred_at
* ip_address
* browser
* browser_version
* operating_system
* operating_system_version
* platform
* device_type
* user_agent

---

# 6. Event Result

Each event records its outcome.

Supported values:

* Success
* Failure

Future values may be added if needed.

---

# 7. Browser Tracking

Browser information is mandatory.

Stored information includes:

* browser name
* browser version
* rendering engine (optional)
* operating system
* platform
* device type
* user agent

---

# 8. IP Tracking

Every authentication event stores:

* login IP address;
* IPv4 or IPv6 support.

Optional:

* geolocation;
* ASN;
* country.

These features remain configurable.

---

# 9. User Association

Successful events are linked to one User.

Failed login attempts may have a nullable `user_id` when the account cannot be identified.

---

# 10. Immutability

Login history records must never be updated.

Allowed operations:

* Insert
* Read

Forbidden operations:

* Update
* Delete

This ensures historical integrity.

---

# 11. Security Monitoring

Login History supports detection of:

* repeated failures;
* unusual login times;
* unusual locations;
* multiple browsers;
* multiple devices;
* suspicious IP addresses.

Detection policies belong to the Identity domain.

---

# 12. Audit Integration

Login History complements the Audit domain.

Authentication events may generate audit events, but Login History remains a dedicated authentication log.

---

# 13. Relationships

User

* Has Many LoginHistory records

LoginHistory

* Belongs To User (nullable)

---

# 14. Retention Policy

Retention is configurable.

Supported policies:

* unlimited;
* fixed duration;
* administrator-defined.

Archived records remain read-only.

---

# 15. Privacy

Sensitive information must never be stored.

Forbidden:

* passwords;
* tokens;
* cookies;
* session secrets.

Only metadata is recorded.

---

# 16. Foundation Responsibilities

The Foundation provides:

* base models;
* common abstractions;
* reusable utilities.

Business rules belong to the Identity domain.

---

# 17. Identity Responsibilities

The Identity domain manages:

* event recording;
* history queries;
* security analysis;
* suspicious activity detection;
* retention policy.

---

# 18. Development Rules

Every Login History component must:

* use ULIDs;
* use strict types;
* follow PSR-12;
* use Enums where appropriate;
* include automated tests;
* integrate with the Audit domain.

---

# 19. Best Practices

Recommended practices:

* never modify history records;
* record every authentication event;
* index searchable fields;
* monitor repeated failures;
* review suspicious activity regularly.

---

# 20. Checklist

Login History implementation must support:

* ULID identifiers
* Immutable records
* Browser tracking
* Device tracking
* IP tracking
* Event types
* Event results
* User association
* Audit integration
* Automated tests

---

# Status

**This document is part of the official MAHLINE Framework V1 specification and is considered frozen.**
