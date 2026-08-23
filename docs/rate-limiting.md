# Rate limiting

```yaml
nowo_device_intelligence:
    profiles:
        default:
            rate_limit:
                policies:
                    coupon:
                        key: device
                        limit: 3
                        interval: '24 hours'
                    login:
                        key: device_ip
                        limit: 20
                        interval: '15 minutes'
```

Keys: `ip`, `user`, `device`, `device_ip`, `user_device`. IP keys use the hashed IP.

```php
#[DeviceRateLimit(policy: 'coupon')]
public function redeem(): Response {}
```

Missing device falls back to IP and does not fail open for named policies.
