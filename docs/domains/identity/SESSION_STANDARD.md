# SESSION_STANDARD.md

## MAHLINE Framework V1

**Version:** 1.0
**Status:** Frozen
**Domain:** Identity

---

# 1. Purpose

This document defines the official session management standard for the MAHLINE Framework.

Its objectives are to:

* manage authenticated user sessions;
* improve account security;
* provide complete session traceability;
* support multiple devices and browsers;
* allow administrators and users to control active sessions.

---

# 2. Session Strategy

Every successful authentication creates a session record.

Sessions are managed independently from Laravel's native session storage to provide additional security, auditing, and management capabilities.

---

# 3. Session Identifier

Each session has:

* ULID primary key
* unique session identifier
* user relationship

Sessions are never identified by incremental IDs.

---

# 4. Session Lifecycle

A session may transition through the following states:

* Created
* Active
* Expired
* Revoked
* Logged Out

Only one state is active at any given time.

---

# 5. Session Information

Every session stores at least:

* user_id
* session_id
* browser
* browser_version
* operating_system
* operating_system_version
* platform
* device_type
* user_agent
* ip_address
* login_at
* last_activity_at
* logout_at
* expires_at
* status

---

# 6. Browser Tracking

Browser tracking is mandatory.

Each session records:

* browser name
* browser version
* rendering engine (optional)
* operating system
* device type
* platform
* complete user agent

---

# 7. Device Identification

The framework distinguishes between:

* Desktop
* Laptop
* Tablet
* Mobile
* Bot
* Unknown

Device detection should remain replaceable.

---

# 8. IP Address

Every session stores:

* IPv4 or IPv6
* latest IP
* login IP

IP history may be stored separately.

---

# 9. Concurrent Sessions

The framework supports configurable policies.

Examples:

* unlimited sessions
* one active session
* one session per browser
* one session per device
* administrator-defined limits

Policy enforcement belongs to the Identity domain.

---

# 10. Duplicate Browser Policy

If two sessions are opened using the same browser according to the configured policy:

* the previous session may be revoked;
* the new session may be rejected;
* or both may coexist,

depending on the active authentication policy.

This behavior is configurable.

---

# 11. Session Revocation

A session may be revoked by:

* the user;
* an administrator;
* a security policy;
* automatic cleanup.

Revoked sessions can no longer authenticate requests.

---

# 12. Session Expiration

Sessions may expire due to:

* inactivity timeout;
* absolute lifetime;
* password change;
* administrator action;
* security events.

---

# 13. Logout

Logout updates:

* logout_at
* session status
* last activity

Logout must be auditable.

---

# 14. Audit Integration

Every important session event is recorded.

Examples:

* login
* logout
* revocation
* expiration
* forced disconnect
* concurrent session handling

---

# 15. Security Rules

The framework must never:

* expose session tokens;
* expose raw cookies;
* expose internal session identifiers through APIs.

Sensitive session data must remain protected.

---

# 16. Relationships

A session belongs to:

* one User

A User may have:

* zero or many Sessions

---

# 17. Foundation Responsibilities

The Foundation provides:

* session abstractions;
* base contracts;
* shared utilities.

The Foundation does not implement business policies.

---

# 18. Identity Responsibilities

The Identity domain manages:

* session creation;
* session validation;
* concurrent session policy;
* browser policy;
* session revocation;
* session expiration.

---

# 19. Development Rules

Every session component must:

* use ULIDs;
* follow PSR-12;
* use strict types;
* support Enums;
* include automated tests;
* integrate with the Audit domain.

---

# 20. Checklist

Session implementation must support:

* ULID identifiers
* Browser tracking
* Device detection
* IP tracking
* Session expiration
* Concurrent session policy
* Session revocation
* Audit integration
* Automated tests

---

# Status

**This document is part of the official MAHLINE Framework V1 specification and is considered frozen.**
