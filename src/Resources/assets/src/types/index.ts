/**
 * Shared public types for the Device Intelligence browser SDK.
 */

/** Expected uniqueness / identifying power of a signal type. */
export type EntropyCategory = 'low' | 'medium' | 'high';

/**
 * One compact derived measurement. Never contains raw pixels, PCM, or
 * unhashed extension dumps.
 */
export interface Signal {
  name: string;
  value: unknown;
  normalizedValue: unknown;
  /** Measurement confidence in `[0, 1]`. */
  quality: number;
  /** Expected temporal stability of this signal *type* in `[0, 1]`. */
  stability: number;
  entropyCategory: EntropyCategory;
  /** Epoch milliseconds. */
  collectedAt: number;
}

/** Runtime context passed to every collector. */
export interface CollectorContext {
  timeout: number;
  abortSignal: AbortSignal;
  consent: { highEntropy: boolean };
}

/**
 * Pluggable signal source. Implementations may throw; the client omits
 * failed collectors and never surfaces the exception to application code.
 */
export interface Collector {
  readonly name: string;
  collect(ctx: CollectorContext): Promise<Signal>;
}

/** Enable/disable built-in collectors. */
export interface CollectorsConfig {
  audio?: boolean;
  canvas?: boolean;
  webgl?: boolean;
  screen?: boolean;
  timezone?: boolean;
  navigator?: boolean;
  clientHints?: boolean;
  capabilities?: boolean;
  automation?: boolean;
  fonts?: boolean;
}

/** In-memory observation cache. `ttl` is seconds. */
export interface CacheConfig {
  enabled?: boolean;
  ttl?: number;
}

/** Constructor options. All fields are optional. */
export interface DeviceIntelligenceOptions {
  endpoint?: string;
  collectors?: CollectorsConfig;
  cache?: CacheConfig;
  timeout?: number;
  consent?: { highEntropy?: boolean };
}

/** Wire-format collect body (`POST` JSON). */
export interface CollectPayload {
  v: 1;
  sdkVersion: string;
  timestamp: number;
  nonce: string;
  consent: { highEntropy: boolean };
  signals: Record<string, CollectSignalWire>;
}

/** Compact signal as sent to the server. */
export interface CollectSignalWire {
  value: unknown;
  quality: number;
  collectedAt: number;
}

/** Optional fields the server may return. */
export interface ServerCollectResponse {
  ok?: boolean;
  new?: boolean;
  token?: string;
  expiresAt?: number;
  degraded?: boolean;
  deviceId?: string;
  confidence?: number;
  risk?: { score: number; level: string };
}

/**
 * Result of {@link DeviceIntelligence.collect}. Always resolved; never thrown.
 */
export interface CollectResult {
  ok: boolean;
  degraded: boolean;
  new?: boolean;
  token?: string;
  expiresAt?: number;
  deviceId?: string;
  confidence?: number;
  risk?: { score: number; level: string };
  signals: Record<string, Signal>;
}

/** Last local observation stored by the memory cache. */
export interface CachedObservation {
  timestamp: number;
  signals: Record<string, Signal>;
  result: CollectResult;
}

/** Built-in collector keys. */
export type BuiltInCollectorName =
  | 'audio'
  | 'canvas'
  | 'webgl'
  | 'screen'
  | 'timezone'
  | 'navigator'
  | 'clientHints'
  | 'capabilities'
  | 'automation'
  | 'fonts';

/** Progressive-enhancement level (see architecture §2.3). */
export type EnhancementLevel = 1 | 2 | 3;

/** User-Agent Client Hints subset used by the SDK. */
export interface NavigatorUABrand {
  brand: string;
  version: string;
}

export interface NavigatorUADataLike {
  brands: NavigatorUABrand[];
  mobile: boolean;
  platform: string;
  getHighEntropyValues?: (hints: string[]) => Promise<Record<string, unknown>>;
}
