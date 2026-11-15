# MAHLINE — AUTHENTICATION STANDARD

**Version:** 1.3
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


## 6.1 HTTP response contract

The Web authentication endpoints negotiate HTML and JSON according to the request:

| Operation | Browser response | JSON response |
|---|---|---|
| Login success | Redirect to the role dashboard or forced-password-change page | `200` with authenticated user and session metadata |
| Login authentication failure | Redirect back with the generic `Authentication failed.` form error | `401` with the generic `Authentication failed.` message |
| Login validation failure | Redirect back with field errors | `422` with field errors |
| Logout success | Redirect to the login page | `200` with `Logout successful.` |
| Guest logout | Redirect to the login page | `401` with `Unauthenticated.` |

API routes always return JSON. Protected Sanctum routes return `401` when authentication is missing or invalid. A user with `must_change_password=true` receives `403` until the mandatory password change is completed. Password-reset API validation and invalid reset requests return `422`; a forgot-password request returns the same generic `200` response whether or not the address belongs to an active account.

Browser form validation must not be forced into JSON solely because the path starts with `/authentication/`; JSON is returned when the client explicitly expects it. This preserves normal browser redirects and session errors.


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
* `AuthenticationControllerTest`
* `ApiAuthenticationResponseTest`

## 12. Freeze rule

The rule:

**ONE USER → MULTIPLE ACTIVE WEB SESSIONS**

must not be changed implicitly.

Changing this rule requires an explicit architecture decision and corresponding test/documentation changes.


## 6.2 Dedicated authentication failure handling

Expected authentication denials are represented by `AuthenticationFailedException` (IP/account/pair lock, inactive account, unverified email and invalid credentials). The Web/JSON login controller catches only this business exception and returns the same generic public response. Unknown accounts are also represented by this dedicated exception, so unexpected Eloquent or infrastructure exceptions cannot be mistaken for invalid credentials.

Unexpected runtime failures must not be translated into an authentication `401`. Infrastructure, persistence, programming and other unexpected failures must reach Laravel's exception handler so they can be treated as server-side incidents instead of being disguised as invalid credentials.

The diagnostic `GET /api/test-auth` endpoint is not part of the production API. Authentication middleware and forced-password-change behavior are covered through a test-only route registered by the API feature test.

Unexpected workflow failures are covered by a regression test: a simulated runtime/persistence failure must return `500`, not `401`, and must not leave an active Identity authentication session.


## 13. Emergency account lock — root-only

An emergency account lock is a persistent security state, separate from the business user status and temporary rate-limit lockouts.

Only super_admin_root may apply or release it. While users.emergency_locked_at is non-null, AuthenticationService rejects new authentication and User::isAuthorized() denies authorization checks. Applying the lock revokes every active Web session with reason emergency_lock and revokes every active Sanctum token by setting revoked_at; historical session, token and audit records are retained.

The release action clears the emergency-lock fields and only the account-specific failed-attempt counter. It does not clear shared IP counters and never restores previously revoked sessions or API tokens. The account must authenticate again and create new API tokens.

The root-only controller writes identity.authentication.emergency_lock.applied and identity.authentication.emergency_lock.released. Session and token revocations additionally write their own per-resource audit events. See ID-I-003-SESSION_STANDARD.md, ID-I-008-API_TOKEN_SPECIFICATION.md and DB-008-AUDIT_STANDARD.md.
