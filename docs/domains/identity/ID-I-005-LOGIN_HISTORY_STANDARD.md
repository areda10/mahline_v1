# MAHLINE — LOGIN HISTORY STANDARD

**Version:** 1.0
**Status:** GELÉ
**Domain:** Identity / Authentication

## 1. Purpose

Login history provides an auditable record of authentication-related activity.

## 2. User relationship

A login history record may reference a known user.

Unknown-user authentication attempts may be recorded without a resolved user.

## 3. Authentication session relationship

A successful Web authentication event references its corresponding authentication session.

This creates a traceable relationship:

```text
User
 ↓
Authentication Session
 ↓
Login History
```

## 4. Recorded client information

Where available, login history can contain:

* IP address;
* user agent;
* browser;
* device;
* authentication result;
* occurred-at timestamp.

## 5. Security

The following must never be recorded:

* plain password;
* authentication secrets.

User-agent information is hidden from normal serialized model output.

## 6. Supported events

The Login History service supports recording:

* successful login;
* failed login;
* unknown-user attempt;
* logout;
* session revocation;
* client information.

## 7. Multiple active sessions

Login history must preserve the relationship between each authentication event and its own Web authentication session.

Because one user may have multiple active Web sessions, authentication history must not assume a single active session.

## 8. Tests

Covered by:

* `LoginHistoryTest`
* `LoginHistoryServiceTest`
* `AuthenticationServiceTest`
* `AuthenticationWorkflowTest`

## 9. Freeze rule

Changes to login-history events or security context require corresponding tests and documentation updates.
