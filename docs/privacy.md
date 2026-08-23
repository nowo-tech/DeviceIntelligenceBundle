# Privacy

Default: IP hashed, raw IP not stored, canvas/audio stored as compact digests only.

| Mode | Collectors | Retention default |
| --- | --- | --- |
| strict | Level 0–1 (no canvas/webgl/audio/fonts) | configure `privacy.retention` |
| balanced | Level 0–2 | 90 days typical |
| full | profile flags | 90 days typical |

`bin/console device-intelligence:cleanup` deletes old observations.

This bundle provides technical controls. It does not certify GDPR/ePrivacy compliance for your processing.
