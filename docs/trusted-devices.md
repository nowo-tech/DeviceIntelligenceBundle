# Trusted devices

```php
$deviceManager->trust($device, $userIdentifier, expiresAt: null, label: 'MacBook · Chrome');
$deviceManager->revoke($device, $userIdentifier);
$deviceManager->isTrusted($device, $userIdentifier);
```

Trust is an explicit grant. Successful login does **not** auto-trust.

`#[RequireTrustedDevice]` on a controller returns 403 when the current device is not trusted for the authenticated user.

Copy-paste “remember this browser” and privileged-action examples: [USE-CASES.md](USE-CASES.md#3-remember-this-device-explicit-trust).
