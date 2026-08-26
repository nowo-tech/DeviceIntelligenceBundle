/**
 * Browser Device Intelligence client (shipped as Composer package assets).
 *
 * Collects compact, privacy-aware signals and POSTs them to a same-origin
 * collect endpoint. `collect()` never throws.
 */

import { DeviceIntelligence } from './client';
import { createBundleLogger, setBundleLogger } from './logger';

declare const __DEVICE_INTELLIGENCE_BUILD_TIME__: string;

const log = createBundleLogger('device-intelligence', {
  buildTime:
    typeof __DEVICE_INTELLIGENCE_BUILD_TIME__ !== 'undefined'
      ? __DEVICE_INTELLIGENCE_BUILD_TIME__
      : undefined,
});
log.scriptLoaded();
setBundleLogger(log);

export { DeviceIntelligence };
export { MemoryCache } from './cache/memory-cache';
export { SDK_VERSION } from './version';
export {
  normalizeBrowser,
  normalizeGpuFamily,
  normalizePlatform,
  normalizeScreenClass,
} from './normalization/normalize';

export type { Collector } from './types/index';
export type { CollectorContext } from './types/index';
export type { Signal } from './types/index';
export type { DeviceIntelligenceOptions } from './types/index';
export type { CollectorsConfig } from './types/index';
export type { CacheConfig } from './types/index';
export type { CollectResult } from './types/index';
export type { CachedObservation } from './types/index';
export type { CollectPayload } from './types/index';
export type { EntropyCategory } from './types/index';
