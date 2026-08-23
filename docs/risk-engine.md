# Risk engine

Rules implement `RiskRuleInterface` and return an explainable `RiskResult`.

Built-in: new_device, multiple_accounts, rapid_account_creation, device_velocity, fingerprint_mutation, automation, suspicious_login, impossible_travel, session_change, ip_change, country_change, trusted_device (negative).

Impossible travel and IP reputation **skip** when no provider is registered. This bundle does not invent TLS/JA3 fingerprints.

Register extras with `#[AsDeviceRiskRule]` or by implementing the interface (autoconfigured).

`RiskDecisionInterface` maps scores to allow / observe / step_up / block. The application implements MFA.
