# MAHLINE — BROWSER TRACKING STANDARD

**Version:** 1.0
**Status:** GELÉ
**Domain:** Identity / Authentication

## 1. Purpose

Browser tracking provides security context for Web authentication.

It records client information needed for authentication security and unusual-activity detection.

It is not intended as unrestricted surveillance.

## 2. Client information

The authentication system may capture:

* browser;
* device;
* user agent;
* IP address.

## 3. Known devices

Known devices are associated with a user.

Rules:

* the same normalized user/device combination is unique;
* remembering an existing device is idempotent;
* a user may have multiple known devices;
* the same device value may exist for different users;
* deleted device records can be detected as new again.

## 4. Device normalization

Device comparison is case-insensitive and whitespace-insensitive.

Empty device values are rejected.

Device values longer than 100 characters are rejected.

A device of exactly 100 characters is accepted.

## 5. New device detection

A device is new when it is not already known for that user.

Checking whether a device is new must not automatically remember it.

A successful authentication may:

1. detect the device;
2. create the authentication session;
3. remember the device.

## 6. Multi-device authentication

A user may authenticate from multiple devices simultaneously.

Example:

```text
ONE USER
│
├── Computer → Device A → Session A ACTIVE
└── Phone    → Device B → Session B ACTIVE
```

Detecting a new device must not revoke the previous active Web session.

## 7. Relationship with login history

Browser and device information may be recorded in login history and security events.

## 8. Transactional behavior

Device remembering and authentication session creation must respect the authentication transaction boundary.

If session creation fails, a newly detected device must not remain incorrectly persisted.

## 9. Tests

Covered by:

* `KnownDeviceTest`
* `UnusualActivityDetectionServiceTest`
* `LoginHistoryServiceTest`
* `AuthenticationWorkflowTransactionTest`
* authentication workflows.

## 10. Freeze rule

Browser tracking changes must preserve:

**ONE USER → MULTIPLE ACTIVE WEB SESSIONS**

unless an explicit architecture decision changes that rule.
