# Device Intelligence Bundle — baseline code inventory

**Package**: `nowo-tech/device-intelligence-bundle`  
**Last audited**: 2026-11-27 (1.7 private EM, 1.9 GDPR ports, 1.9.2 exact ids, 1.10 transaction runner)  
**Production units**: **228/228**  
**Audit command**: count `lib/**/*.php` + `src/**/*.php` + `src/**/*.twig` + `src/**/*.svg` + `src/Resources/translations/*.yaml` + `src/Resources/assets/src/**/*.ts` (exclude `src/Resources/assets/tests/**`).

## Scope notes

- Vitest files under `src/Resources/assets/tests/` are **excluded** (`FR-TEST-TS-001` in spec.md; not inventory rows).
- Vite IIFE `src/Resources/public/js/device-intelligence.min.js` (+ `.map`) is a **build output** of `src/Resources/assets/src/**` (`FR-BUILD-001`), not a separate production unit.
- `src/Resources/assets/LICENSE` and `src/Resources/assets/README.md` are documentation shipped next to the SDK, not runtime units.
- Unlisted tracked files under `src/` are exactly these exclusions: the 9 Vitest files in `src/Resources/assets/tests/`, `src/Resources/assets/LICENSE`, `src/Resources/assets/README.md`, and the build output `device-intelligence.min.js` + `device-intelligence.min.js.map` (2026-10-20 audit: 208 rows = 115 `lib` PHP + 64 `src` PHP + 1 Twig + 1 SVG + 7 translations + 20 TS sources).
- `demo/` is out of inventory scope.
- This package co-locates the matcher engine in `lib/` (PSR-4 `Nowo\DeviceIntelligence\`) and the Symfony adapter in `src/`; both are production scope.

## Coverage summary

| Category | Mapped |
| --- | ---: |
| Matching & risk | 68 |
| Core domain | 17 |
| Ports & infra | 28 |
| Privacy | 9 |
| Personal data (GDPR) | 11 |
| Guards | 4 |
| CLI | 6 |
| Bundle & DI | 9 |
| HTTP collect | 8 |
| Doctrine | 15 |
| Events | 18 |
| Request context | 6 |
| Messenger | 4 |
| Profiler | 3 |
| i18n | 7 |
| Browser SDK | 20 |
| **Total** | **233** |

## Matching & risk

| Source path | Spec section | Requirement ID(s) |
| --- | --- | --- |
| `lib/Analysis.php` | Matching & risk | FR-MATCH-001 |
| `lib/AnalysisInput.php` | Matching & risk | FR-MATCH-001 |
| `lib/DeviceIntelligence.php` | Matching & risk | FR-MATCH-001 |
| `lib/DeviceIntelligenceInterface.php` | Matching & risk | FR-MATCH-001 |
| `lib/Matching/Candidate/DeviceCandidateProviderInterface.php` | Matching & risk | FR-MATCH-001 |
| `lib/Matching/Candidate/RepositoryCandidateProvider.php` | Matching & risk | FR-MATCH-001 |
| `lib/Matching/CandidateIndexKey.php` | Matching & risk | FR-MATCH-001 |
| `lib/Matching/Comparator/DefaultSignalComparator.php` | Matching & risk | FR-MATCH-001 |
| `lib/Matching/Comparator/SignalComparatorInterface.php` | Matching & risk | FR-MATCH-001 |
| `lib/Matching/Confidence.php` | Matching & risk | FR-MATCH-001 |
| `lib/Matching/DeviceMatch.php` | Matching & risk | FR-MATCH-001 |
| `lib/Matching/DeviceMatcherInterface.php` | Matching & risk | FR-MATCH-001 |
| `lib/Matching/IndexKeyFactory.php` | Matching & risk | FR-MATCH-001 |
| `lib/Matching/MatchingConfig.php` | Matching & risk | FR-MATCH-001 |
| `lib/Matching/MatchingWeights.php` | Matching & risk | FR-MATCH-001 |
| `lib/Matching/Similarity.php` | Matching & risk | FR-MATCH-001 |
| `lib/Matching/WeightedDeviceMatcher.php` | Matching & risk | FR-MATCH-001 |
| `lib/Risk/RiskAssessment.php` | Matching & risk | FR-RISK-001 |
| `lib/Risk/RiskContext.php` | Matching & risk | FR-RISK-001 |
| `lib/Risk/RiskDecision.php` | Matching & risk | FR-RISK-001 |
| `lib/Risk/RiskDecisionAction.php` | Matching & risk | FR-RISK-001 |
| `lib/Risk/RiskDecisionInterface.php` | Matching & risk | FR-RISK-001 |
| `lib/Risk/RiskEngine.php` | Matching & risk | FR-RISK-001 |
| `lib/Risk/RiskEngineInterface.php` | Matching & risk | FR-RISK-001 |
| `lib/Risk/RiskLevel.php` | Matching & risk | FR-RISK-001 |
| `lib/Risk/RiskLevels.php` | Matching & risk | FR-RISK-001 |
| `lib/Risk/RiskReason.php` | Matching & risk | FR-RISK-001 |
| `lib/Risk/RiskResult.php` | Matching & risk | FR-RISK-001 |
| `lib/Risk/RiskRuleInterface.php` | Matching & risk | FR-RISK-001 |
| `lib/Risk/RiskScore.php` | Matching & risk | FR-RISK-001 |
| `lib/Risk/RiskSeverity.php` | Matching & risk | FR-RISK-001 |
| `lib/Risk/Rule/AutomationRule.php` | Matching & risk | FR-RISK-001 |
| `lib/Risk/Rule/CountryChangeRule.php` | Matching & risk | FR-RISK-001 |
| `lib/Risk/Rule/DeviceVelocityRule.php` | Matching & risk | FR-RISK-001 |
| `lib/Risk/Rule/FingerprintMutationRule.php` | Matching & risk | FR-RISK-001 |
| `lib/Risk/Rule/ImpossibleTravelRule.php` | Matching & risk | FR-RISK-001 |
| `lib/Risk/Rule/IpChangeRule.php` | Matching & risk | FR-RISK-001 |
| `lib/Risk/Rule/IpReputationRule.php` | Matching & risk: scores the client IP reputation (Tor 30, proxy 20, VPN 10, hosting 10, or the provider risk / 3, capped at 40; 0 without a provider) | FR-RISK-001, FR-RISK-002 |
| `lib/Risk/Rule/MultipleAccountsRule.php` | Matching & risk | FR-RISK-001 |
| `lib/Risk/Rule/NewDeviceRule.php` | Matching & risk | FR-RISK-001 |
| `lib/Risk/Rule/RapidAccountCreationRule.php` | Matching & risk | FR-RISK-001 |
| `lib/Risk/Rule/SessionChangeRule.php` | Matching & risk | FR-RISK-001 |
| `lib/Risk/Rule/SuspiciousLoginRule.php` | Matching & risk | FR-RISK-001 |
| `lib/Risk/Rule/TrustedDeviceRule.php` | Matching & risk | FR-RISK-001 |
| `lib/Risk/ThresholdRiskDecision.php` | Matching & risk | FR-RISK-001 |
| `lib/Signal/ClientHintPlatformBridge.php` | Matching & risk | FR-MATCH-001 |
| `lib/Signal/EnhancementLevel.php` | Matching & risk | FR-MATCH-001 |
| `lib/Signal/EntropyCategory.php` | Matching & risk | FR-MATCH-001 |
| `lib/Signal/Normalizer/BrowserVersionNormalizer.php` | Matching & risk | FR-MATCH-001 |
| `lib/Signal/Normalizer/CompactDigestNormalizer.php` | Matching & risk | FR-MATCH-001 |
| `lib/Signal/Normalizer/IdentityNormalizer.php` | Matching & risk | FR-MATCH-001 |
| `lib/Signal/Normalizer/PlatformNormalizer.php` | Matching & risk | FR-MATCH-001 |
| `lib/Signal/Normalizer/ScreenNormalizer.php` | Matching & risk | FR-MATCH-001 |
| `lib/Signal/Normalizer/SignalNormalizerInterface.php` | Matching & risk | FR-MATCH-001 |
| `lib/Signal/Normalizer/SignalNormalizerRegistry.php` | Matching & risk | FR-MATCH-001 |
| `lib/Signal/Normalizer/TimezoneNormalizer.php` | Matching & risk | FR-MATCH-001 |
| `lib/Signal/Normalizer/WebGlNormalizer.php` | Matching & risk | FR-MATCH-001 |
| `lib/Signal/Quality.php` | Matching & risk | FR-MATCH-001 |
| `lib/Signal/Server/DefaultServerSignalProvider.php` | Matching & risk | FR-MATCH-001 |
| `lib/Signal/Server/NetworkSignalProviderInterface.php` | Matching & risk | FR-MATCH-001 |
| `lib/Signal/Server/NullNetworkSignalProvider.php` | Matching & risk | FR-MATCH-001 |
| `lib/Signal/Server/ServerSignalProviderInterface.php` | Matching & risk | FR-MATCH-001 |
| `lib/Signal/Signal.php` | Matching & risk | FR-MATCH-001 |
| `lib/Signal/SignalBag.php` | Matching & risk | FR-MATCH-001 |
| `lib/Signal/SignalFactory.php` | Matching & risk | FR-MATCH-001 |
| `lib/Signal/SignalName.php` | Matching & risk | FR-MATCH-001 |
| `lib/Signal/SignalSource.php` | Matching & risk | FR-MATCH-001 |
| `src/Risk/RiskEngineFactory.php` | Matching & risk | FR-RISK-001 |

## Core domain

| Source path | Spec section | Requirement ID(s) |
| --- | --- | --- |
| `lib/Device/DefaultDeviceLabeler.php` | Core domain | FR-CORE-001 |
| `lib/Device/Device.php` | Core domain | FR-CORE-001 |
| `lib/Device/DeviceId.php` | Core domain | FR-CORE-001 |
| `lib/Device/DeviceLabelerInterface.php` | Core domain | FR-CORE-001 |
| `lib/Device/DeviceManager.php` | Core domain | FR-CORE-001 |
| `lib/Device/DeviceManagerInterface.php` | Core domain | FR-CORE-001 |
| `lib/Device/DeviceStatus.php` | Core domain | FR-CORE-001 |
| `lib/Device/MutationReport.php` | Core domain | FR-CORE-001 |
| `lib/Device/Stability.php` | Core domain | FR-CORE-001 |
| `lib/Device/Ulid.php` | Core domain | FR-CORE-001 |
| `lib/Observation/DeviceObservation.php` | Core domain | FR-CORE-001 |
| `lib/Observation/ObservationId.php` | Core domain | FR-CORE-001 |
| `lib/Trust/TrustedDevice.php` | Core domain | FR-CORE-001 |
| `lib/Trust/TrustedDeviceManagerInterface.php` | Core domain | FR-CORE-001 |
| `lib/User/DeviceUserRelation.php` | Core domain | FR-CORE-001 |
| `lib/User/UserIdentifier.php` | Core domain | FR-CORE-001 |
| `lib/User/UserIdentifierResolverInterface.php` | Core domain | FR-CORE-001 |

## Ports & infra

| Source path | Spec section | Requirement ID(s) |
| --- | --- | --- |
| `lib/Exception/DeviceIntelligenceException.php` | Ports & infra | FR-CORE-001 |
| `lib/Exception/InvalidValueException.php` | Ports & infra | FR-CORE-001 |
| `lib/Exception/LookAlikeIdentifierException.php` | Ports & infra: a write refused because a look-alike identifier's row blocks it on a folding unique key (REQ-SEC-006, 1.9.2); fails closed | FR-CORE-003 |
| `lib/Infrastructure/FrozenClock.php` | Ports & infra | FR-CORE-001 |
| `lib/Infrastructure/InMemoryDeviceRepository.php` | Ports & infra | FR-CORE-001 |
| `lib/Infrastructure/InMemoryDeviceUserRepository.php` | Ports & infra | FR-CORE-001 |
| `lib/Infrastructure/InMemoryObservationRepository.php` | Ports & infra | FR-CORE-001 |
| `lib/Infrastructure/InMemoryTrustedDeviceRepository.php` | Ports & infra | FR-CORE-001 |
| `src/Trust/DeviceTrustServiceInterface.php` | Ports & infra | FR-TRUST-001 |
| `lib/Port/DeviceRepositoryInterface.php` | Ports & infra | FR-CORE-001 |
| `lib/Port/DeviceUserRepositoryInterface.php` | Ports & infra | FR-CORE-001 |
| `lib/Port/AtomicDeviceRepositoryInterface.php` | Ports & infra: optional atomic writes for a device repository adapter (`saveObserved()` increments the count in storage, `saveStability()` writes only the stability; never reverts a concurrent status change; 1.10) | FR-CORE-002 |
| `lib/Port/AtomicDeviceUserRepositoryInterface.php` | Ports & infra: optional atomic `recordLogin()` for the device-user link (two simultaneous logins both count; 1.10) | FR-CORE-002, FR-CORE-003 |
| `lib/Port/AtomicTrustedDeviceRepositoryInterface.php` | Ports & infra: optional one-write `revoke()` of a trust (only the revocation time is written; 1.10) | FR-CORE-002, FR-CORE-003 |
| `lib/Port/GeoIpProviderInterface.php` | Ports & infra | FR-CORE-001 |
| `lib/Port/GeoIpResult.php` | Ports & infra | FR-CORE-001 |
| `lib/Port/IpReputation.php` | Ports & infra | FR-CORE-001 |
| `lib/Port/IpReputationProviderInterface.php` | Ports & infra | FR-CORE-001 |
| `lib/Port/MetricsRecorderInterface.php` | Ports & infra | FR-CORE-001 |
| `lib/Port/NullGeoIpProvider.php` | Ports & infra | FR-CORE-001 |
| `lib/Port/NullIpReputationProvider.php` | Ports & infra | FR-CORE-001 |
| `lib/Port/NullMetricsRecorder.php` | Ports & infra | FR-CORE-001 |
| `lib/Port/ObservationRepositoryInterface.php` | Ports & infra | FR-CORE-001 |
| `lib/Port/TrustedDeviceRepositoryInterface.php` | Ports & infra | FR-CORE-001 |
| `lib/Velocity/CacheVelocityEngine.php` | Ports & infra | FR-CORE-001 |
| `lib/Velocity/InMemoryVelocityEngine.php` | Ports & infra | FR-CORE-001 |
| `lib/Velocity/TimeWindow.php` | Ports & infra | FR-CORE-001 |
| `lib/Velocity/VelocityEngineInterface.php` | Ports & infra | FR-CORE-001 |

## Privacy

| Source path | Spec section | Requirement ID(s) |
| --- | --- | --- |
| `lib/Privacy/AllowAllConsentGate.php` | Privacy | FR-PRIV-001 |
| `lib/Privacy/ConsentContext.php` | Privacy | FR-PRIV-001 |
| `lib/Privacy/ConsentGateInterface.php` | Privacy | FR-PRIV-001 |
| `lib/Privacy/IpHash.php` | Privacy | FR-PRIV-001 |
| `lib/Privacy/IpHasher.php` | Privacy | FR-PRIV-001 |
| `lib/Privacy/PrivacyContext.php` | Privacy | FR-PRIV-001 |
| `lib/Privacy/PrivacyMode.php` | Privacy | FR-PRIV-001 |
| `lib/Privacy/PrivacyProcessor.php` | Privacy | FR-PRIV-001 |
| `lib/Privacy/PrivacyProcessorInterface.php` | Privacy | FR-PRIV-001 |

## Personal data (GDPR)

| Source path | Spec section | Requirement ID(s) |
| --- | --- | --- |
| `src/PersonalData/DeviceIntelligenceErasureResult.php` | Personal data | FR-GDPR-001 |
| `src/PersonalData/DeviceIntelligencePersonalDataEraser.php` | Personal data | FR-GDPR-001 |
| `src/PersonalData/DeviceIntelligencePersonalDataEraserInterface.php` | Personal data | FR-GDPR-001 |
| `src/PersonalData/DeviceIntelligencePersonalDataExporter.php` | Personal data | FR-GDPR-001 |
| `src/PersonalData/DeviceIntelligencePersonalDataExporterInterface.php` | Personal data | FR-GDPR-001 |
| `src/PersonalData/DeviceIntelligencePersonalDataSubject.php` | Personal data | FR-GDPR-001 |
| `src/PersonalData/DeviceIntelligencePersonalDataSubjectResolverInterface.php` | Personal data | FR-GDPR-001 |
| `src/PersonalData/PersonalDataRows.php` | Personal data | FR-GDPR-001, FR-ORM-003 |
| `src/PersonalData/UserIdentifierSubjectResolver.php` | Personal data | FR-GDPR-001 |
| `src/PersonalData/UserKit/DeviceIntelligenceUserKitEraser.php` | Personal data | FR-GDPR-002 |
| `src/PersonalData/UserKit/DeviceIntelligenceUserKitExportProvider.php` | Personal data | FR-GDPR-002 |

## Guards

| Source path | Spec section | Requirement ID(s) |
| --- | --- | --- |
| `src/Attribute/AsDeviceRiskRule.php` | Guards | FR-GUARD-001 |
| `src/Attribute/DeviceRateLimit.php` | Guards | FR-GUARD-001 |
| `src/Attribute/DeviceRisk.php` | Guards | FR-GUARD-001 |
| `src/Attribute/RequireTrustedDevice.php` | Guards | FR-GUARD-001 |

## CLI

| Source path | Spec section | Requirement ID(s) |
| --- | --- | --- |
| `src/Command/CleanupCommand.php` | CLI | FR-CLI-001 |
| `src/Command/DeviceShowCommand.php` | CLI | FR-CLI-001 |
| `src/Command/RecalculateCommand.php` | CLI | FR-CLI-001 |
| `src/Command/RiskTestCommand.php` | CLI | FR-CLI-001 |
| `src/Command/StatsCommand.php` | CLI | FR-CLI-001 |
| `src/Command/UserDevicesCommand.php` | CLI | FR-CLI-001 |

## Bundle & DI

| Source path | Spec section | Requirement ID(s) |
| --- | --- | --- |
| `src/Config/DeviceIntelligenceConfig.php` | Bundle & DI | FR-BUNDLE-001, FR-CFG-001, FR-DI-001 |
| `src/DependencyInjection/Configuration.php` | Bundle & DI | FR-BUNDLE-001, FR-CFG-001, FR-DI-001 |
| `src/DependencyInjection/NowoDeviceIntelligenceExtension.php` | Bundle & DI | FR-BUNDLE-001, FR-CFG-001, FR-DI-001 |
| `src/NowoDeviceIntelligenceBundle.php` | Bundle & DI | FR-BUNDLE-001, FR-CFG-001, FR-DI-001 |
| `src/Resources/config/routes.php` | Bundle & DI | FR-BUNDLE-001, FR-CFG-001, FR-DI-001 |
| `src/Resources/config/services.php` | Bundle & DI | FR-BUNDLE-001, FR-CFG-001, FR-DI-001 |
| `src/DependencyInjection/Compiler/TwigPathsPass.php` | Bundle & DI | FR-TWIG-001 |
| `src/DependencyInjection/Compiler/DeviceIntelligenceEntityManagerPass.php` | Bundle & DI | FR-ORM-002 |
| `src/DependencyInjection/Compiler/PersonalDataBridgePass.php` | Bundle & DI | FR-GDPR-002 |

## HTTP collect

| Source path | Spec section | Requirement ID(s) |
| --- | --- | --- |
| `src/Controller/CollectController.php` | HTTP collect | FR-CTRL-001, FR-HTTP-001 |
| `src/Http/AnalysisInputFactory.php` | HTTP collect | FR-CTRL-001, FR-HTTP-001 |
| `src/Http/CollectRequestValidator.php` | HTTP collect | FR-CTRL-001, FR-HTTP-001 |
| `src/Http/Exception/CollectValidationException.php` | HTTP collect | FR-CTRL-001, FR-HTTP-001 |
| `src/Http/ObservationTokenIssuer.php` | HTTP collect | FR-CTRL-001, FR-HTTP-001 |
| `src/Http/OriginValidator.php` | HTTP collect | FR-CTRL-001, FR-HTTP-001 |
| `src/RateLimiter/DeviceRateLimiterInterface.php` | HTTP collect | FR-HTTP-001 |
| `src/RateLimiter/SymfonyDeviceRateLimiter.php` | HTTP collect | FR-HTTP-001 |

## Doctrine

| Source path | Spec section | Requirement ID(s) |
| --- | --- | --- |
| `src/Doctrine/DeviceMapper.php` | Doctrine | FR-ORM-001 |
| `src/Doctrine/DoctrineDeviceRepository.php` | Doctrine | FR-ORM-001, FR-WORKER-001 |
| `src/Doctrine/DoctrineDeviceUserRepository.php` | Doctrine | FR-ORM-001, FR-WORKER-001 |
| `src/Doctrine/DoctrineObservationRepository.php` | Doctrine | FR-ORM-001, FR-WORKER-001 |
| `src/Doctrine/DoctrineTrustedDeviceRepository.php` | Doctrine | FR-ORM-001, FR-WORKER-001 |
| `src/Doctrine/ResolvesEntityManagerTrait.php` | Doctrine | FR-ORM-001, FR-WORKER-001 |
| `src/Doctrine/TablePrefixSubscriber.php` | Doctrine | FR-ORM-001 |
| `src/Entity/DeviceEntity.php` | Doctrine | FR-ORM-001 |
| `src/Entity/DeviceObservationEntity.php` | Doctrine | FR-ORM-001 |
| `src/Entity/DeviceTrustEntity.php` | Doctrine | FR-ORM-001 |
| `src/Entity/DeviceUserEntity.php` | Doctrine | FR-ORM-001 |
| `src/Doctrine/DbalEntityWriter.php` | Doctrine | FR-ORM-002, FR-WORKER-001 |
| `src/Doctrine/ExcludedEntityNamespaceDriver.php` | Doctrine | FR-ORM-002 |
| `src/Doctrine/ExactCollationListener.php` | Doctrine | FR-ORM-003 |
| `src/Doctrine/DeviceIntelligenceTransactionRunner.php` | Doctrine | FR-ORM-004, FR-WORKER-001 |

## Events

| Source path | Spec section | Requirement ID(s) |
| --- | --- | --- |
| `src/Event/AnalyzeService.php` | Events | FR-EVT-001 |
| `src/Event/BeforeRiskAssessmentEvent.php` | Events | FR-EVT-001 |
| `src/Event/DeviceCreatedEvent.php` | Events | FR-EVT-001 |
| `src/Event/DeviceMatchedEvent.php` | Events | FR-EVT-001 |
| `src/Event/DeviceObservedEvent.php` | Events | FR-EVT-001 |
| `src/Event/DeviceRevokedEvent.php` | Events | FR-EVT-001 |
| `src/Event/DeviceRiskCalculatedEvent.php` | Events | FR-EVT-001 |
| `src/Event/DeviceTrustedEvent.php` | Events | FR-EVT-001 |
| `src/Event/NewDeviceDetectedEvent.php` | Events | FR-EVT-001 |
| `src/Event/RiskAssessmentCompletedEvent.php` | Events | FR-EVT-001 |
| `src/Event/SuspiciousDeviceEvent.php` | Events | FR-EVT-001 |
| `src/Event/DeviceIntelligencePersonalDataErasedEvent.php` | Events | FR-GDPR-001 |
| `src/EventSubscriber/AnalyzeSubscriber.php` | Events | FR-EVT-001 |
| `src/EventSubscriber/ControllerAttributeSubscriber.php` | Events | FR-EVT-001 |
| `src/EventSubscriber/DeviceRequestSubscriber.php` | Events | FR-EVT-001 |
| `src/EventSubscriber/ProfilerAjaxBridgeSubscriber.php` | Events | FR-PROF-001, FR-EVT-001 |
| `src/EventSubscriber/RequestStateResetSubscriber.php` | Events | FR-WORKER-001 |
| `src/EventSubscriber/SecurityDeviceSubscriber.php` | Events | FR-EVT-001 |

## Request context

| Source path | Spec section | Requirement ID(s) |
| --- | --- | --- |
| `src/Infrastructure/SystemClock.php` | Request context | FR-CTX-001, FR-DI-002 |
| `src/Request/DeviceContext.php` | Request context | FR-CTX-001, FR-DI-002 |
| `src/Request/DeviceContextValueResolver.php` | Request context | FR-CTX-001, FR-DI-002 |
| `src/Request/TokenDeviceContextFactory.php` | Request context | FR-CTX-001, FR-DI-002 |
| `src/Trust/DeviceTrustService.php` | Request context | FR-CTX-001, FR-DI-002 |
| `src/User/SecurityUserIdentifierResolver.php` | Request context | FR-CTX-001, FR-DI-002 |

## Messenger

| Source path | Spec section | Requirement ID(s) |
| --- | --- | --- |
| `src/Messenger/CleanupHandler.php` | Messenger | FR-MSG-001 |
| `src/Messenger/CleanupMessage.php` | Messenger | FR-MSG-001 |
| `src/Messenger/RecalculateStabilityHandler.php` | Messenger | FR-MSG-001 |
| `src/Messenger/RecalculateStabilityMessage.php` | Messenger | FR-MSG-001 |

## Profiler

| Source path | Spec section | Requirement ID(s) |
| --- | --- | --- |
| `src/Profiler/DeviceIntelligenceDataCollector.php` | Profiler | FR-PROF-001, FR-TWIG-001 |
| `src/Resources/views/Collector/device_intelligence.html.twig` | Profiler | FR-PROF-001, FR-TWIG-001 |
| `src/Resources/views/Icon/device-intelligence.svg` | Profiler | FR-PROF-001, FR-TWIG-001 |

## i18n

| Source path | Spec section | Requirement ID(s) |
| --- | --- | --- |
| `src/Resources/translations/NowoDeviceIntelligenceBundle.de.yaml` | i18n | FR-I18N-001 |
| `src/Resources/translations/NowoDeviceIntelligenceBundle.en.yaml` | i18n | FR-I18N-001 |
| `src/Resources/translations/NowoDeviceIntelligenceBundle.es.yaml` | i18n | FR-I18N-001 |
| `src/Resources/translations/NowoDeviceIntelligenceBundle.fr.yaml` | i18n | FR-I18N-001 |
| `src/Resources/translations/NowoDeviceIntelligenceBundle.it.yaml` | i18n | FR-I18N-001 |
| `src/Resources/translations/NowoDeviceIntelligenceBundle.nl.yaml` | i18n | FR-I18N-001 |
| `src/Resources/translations/NowoDeviceIntelligenceBundle.pt.yaml` | i18n | FR-I18N-001 |

## Browser SDK

| Source path | Spec section | Requirement ID(s) |
| --- | --- | --- |
| `src/Resources/assets/src/cache/memory-cache.ts` | Browser SDK | FR-TS-001 |
| `src/Resources/assets/src/client.ts` | Browser SDK | FR-TS-001, FR-BUILD-001 |
| `src/Resources/assets/src/collectors/audio.ts` | Browser SDK | FR-TS-001 |
| `src/Resources/assets/src/collectors/automation.ts` | Browser SDK | FR-TS-001 |
| `src/Resources/assets/src/collectors/canvas.ts` | Browser SDK | FR-TS-001 |
| `src/Resources/assets/src/collectors/capabilities.ts` | Browser SDK | FR-TS-001 |
| `src/Resources/assets/src/collectors/client-hints.ts` | Browser SDK | FR-TS-001 |
| `src/Resources/assets/src/collectors/collector.ts` | Browser SDK | FR-TS-001 |
| `src/Resources/assets/src/collectors/fonts.ts` | Browser SDK | FR-TS-001 |
| `src/Resources/assets/src/collectors/navigator.ts` | Browser SDK | FR-TS-001 |
| `src/Resources/assets/src/collectors/screen.ts` | Browser SDK | FR-TS-001 |
| `src/Resources/assets/src/collectors/timezone.ts` | Browser SDK | FR-TS-001 |
| `src/Resources/assets/src/collectors/webgl.ts` | Browser SDK | FR-TS-001 |
| `src/Resources/assets/src/crypto/digest.ts` | Browser SDK | FR-TS-001 |
| `src/Resources/assets/src/index.ts` | Browser SDK | FR-TS-001, FR-BUILD-001 |
| `src/Resources/assets/src/logger.ts` | Browser SDK: bundle logger, `scriptLoaded()` banner with build label and quiet switch | FR-TS-001, FR-TS-002, FR-BUILD-001 |
| `src/Resources/assets/src/normalization/normalize.ts` | Browser SDK | FR-TS-001 |
| `src/Resources/assets/src/transport/fetch-transport.ts` | Browser SDK | FR-TS-001 |
| `src/Resources/assets/src/types/index.ts` | Browser SDK | FR-TS-001, FR-BUILD-001 |
| `src/Resources/assets/src/version.ts` | Browser SDK | FR-TS-001, FR-BUILD-001 |

