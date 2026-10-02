# MAHLINE — AUTHENTICATION STANDARD

**Version:** 1.0
**Status:** GELÉ
**Domain:** Identity / Authentication

## 1. Fundamental authentication rule

MAHLINE authentication follows:

# ONE USER → MULTIPLE ACTIVE WEB SESSIONS

This is a fundamental architecture rule.

A user may have several active Web authentication sessions simultaneously.

Example:

```text
ONE USER
│
├── Computer / Chrome  → Session A → ACTIVE
├── Computer / Firefox → Session B → ACTIVE
└── Phone              → Session C → ACTIVE
```

A new login does **not** revoke previous active sessions.

## 2. Session independence

Each Web authentication session has its own lifecycle.

Therefore:

* Session A can be active;
* Session B can be active;
* Session C can be revoked;
* revoking Session C does not revoke A or B.

## 3. Authentication mechanisms

MAHLINE separates:

```text
WEB
└── AuthenticationSession

API
└── Sanctum PersonalAccessToken
```

The two mechanisms must not be confused.

## 4. Authentication context

Authentication services operate from an explicit `AuthenticationContext`.

The domain service must not depend directly on HTTP `Request`.

The context may contain:

* email;
* transient plain password;
* Laravel session identifier;
* IP address;
* user agent;
* browser;
* device.

The plain password must never be persisted or recorded in history.

## 5. Login process

A successful login must:

1. normalize the email;
2. evaluate authentication security restrictions;
3. verify credentials;
4. create a new authentication session;
5. retain previous active sessions;
6. record login history;
7. detect the device;
8. remember a new device when appropriate.

## 6. Failed authentication

Authentication security tracks failed attempts independently for:

* normalized email;
* IP address.

Account and IP lockouts are handled by `AuthenticationSecurityService`.

A locked account or locked IP must be rejected.

## 7. Client tracking

Authentication may record:

* IP address;
* user agent;
* browser;
* device.

New-device detection is part of the authentication security workflow.

## 8. Logout

Normal Web logout revokes only the current authentication session.

It must not revoke other active sessions belonging to the same user.

Global logout is a separate operation and may revoke all active sessions.

## 9. API authentication

API tokens are handled separately through Sanctum.

Creating an API token:

* does not create a Web session.

Revoking an API token:

* does not revoke a Web session.

## 10. Transactions

Device and session creation must be atomic.

If session creation fails, a newly detected device must not be incorrectly persisted.

## 11. Tests

Authentication is covered by:

* `AuthenticationContextTest`
* `AuthenticationServiceTest`
* `AuthenticationSecurityServiceTest`
* `SessionServiceTest`
* `LoginHistoryServiceTest`
* `SecurityEventServiceTest`
* `KnownDeviceTest`
* `UnusualActivityDetectionServiceTest`
* `AuthenticationWorkflowTest`
* `AuthenticationWorkflowTransactionTest`
* `SanctumAuthenticationTest`

## 12. Freeze rule

The rule:

**ONE USER → MULTIPLE ACTIVE WEB SESSIONS**

must not be changed implicitly.

Changing this rule requires an explicit architecture decision and corresponding test/documentation changes.
