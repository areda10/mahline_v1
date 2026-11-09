# MAHLINE — AUTHENTICATION STANDARD

**Version:** 1.1
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

Authentication security tracks failed attempts independently at three levels:

| Scope | Threshold | Window |
|---|---:|---:|
| Normalized account email | 5 failures | 15 minutes |
| IP address | 30 failures | 15 minutes |
| IP address + normalized email pair | 5 failures | 15 minutes |

All processed authentication failures consume the applicable counters, including unknown accounts, inactive accounts, unverified email addresses and invalid passwords. Requests rejected because a relevant counter is already locked do not increment that locked counter again.

A successful login clears only the normalized account counter and the matching IP + email pair counter. It never clears the shared IP counter, which expires naturally after its 15-minute window.

Cache keys use HMAC fingerprints; raw email addresses and IP addresses are not exposed in cache key names. All application nodes serving authentication traffic must use the same shared cache backend and the same application key so that the limits are consistent across servers.

The account, IP and IP + email lockouts are handled by `AuthenticationSecurityService`. Only `super_admin_root` may manually clear all three scopes together, by submitting the normalized account email, the affected IP address and a mandatory administrative reason. This explicitly clears the shared IP counter for every account using that IP. The operation is recorded in the append-only audit log; the target email and IP are represented there by HMAC fingerprints. The login route must not apply a second request-count throttle that also counts successful logins; the domain counters are failure-only.

A locked account, IP or IP + email pair must be rejected. When both the IP and account limits are reached, the existing `ip_locked` login-history reason retains priority.

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
