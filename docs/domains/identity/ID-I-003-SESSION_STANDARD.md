# MAHLINE — SESSION STANDARD

**Version:** 1.0
**Status:** GELÉ
**Domain:** Identity / Authentication

## 1. Fundamental rule

MAHLINE does not use a one-user/one-session model.

The official rule is:

**ONE USER → MULTIPLE ACTIVE WEB SESSIONS**

A user can remain authenticated simultaneously on multiple browsers and devices.

## 2. Authentication session

Each Web authentication session is an independent security object.

A session contains:

* user identifier;
* ULID identifier;
* Laravel session identifier;
* authentication timestamp;
* activity information;
* revocation state;
* revocation reason;
* client/device context where applicable.

The Laravel session identifier is hidden from serialized model output.

## 3. Session states

A session can be:

* active;
* revoked;
* soft deleted.

Revoked and deleted sessions are not considered active.

## 4. New login

When a user logs in from a new browser or device:

```text
Existing Session A → ACTIVE
New Session B      → ACTIVE
```

Session A remains active.

The new login must not automatically revoke Session A.

## 5. Logout

Normal logout revokes only the current Web session.

Example:

```text
Session A → ACTIVE
Session B → ACTIVE
Logout B
↓
Session A → ACTIVE
Session B → REVOKED
```

## 6. Individual revocation

A specific session may be revoked independently.

Revoking one session must not revoke another active session.

An already revoked session cannot be revoked again as an active session.

## 7. Global revocation

The session service may revoke all active sessions belonging to a user.

Global revocation:

* affects only the specified user;
* ignores already revoked sessions;
* ignores deleted sessions;
* preserves the global revocation reason;
* does not create individual logout history entries for every session.

## 8. Current session

The current-session operation returns an active authentication session when one exists.

If no active session exists, it returns `null`.

## 9. Activity

An active session may update its last activity.

A revoked session cannot update its activity.

## 10. Tests

Covered by:

* `AuthenticationSessionTest`
* `SessionServiceTest`
* `AuthenticationWorkflowTest`
* `AuthenticationWorkflowTransactionTest`

## 11. Freeze rule

The multiple-active-Web-session rule is a core security decision.

It cannot be replaced by a one-session-only rule without an explicit architecture change.
