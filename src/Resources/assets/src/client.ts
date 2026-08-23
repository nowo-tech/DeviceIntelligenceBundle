import { MemoryCache } from './cache/memory-cache';
import { AudioCollector } from './collectors/audio';
import { AutomationCollector } from './collectors/automation';
import { CanvasCollector } from './collectors/canvas';
import { CapabilitiesCollector } from './collectors/capabilities';
import { ClientHintsCollector } from './collectors/client-hints';
import { raceAbort } from './collectors/collector';
import { FontsCollector } from './collectors/fonts';
import { NavigatorCollector } from './collectors/navigator';
import { ScreenCollector } from './collectors/screen';
import { TimezoneCollector } from './collectors/timezone';
import { WebGlCollector } from './collectors/webgl';
import { postCollect } from './transport/fetch-transport';
import type {
  CachedObservation,
  CollectPayload,
  CollectResult,
  CollectSignalWire,
  Collector,
  CollectorContext,
  CollectorsConfig,
  DeviceIntelligenceOptions,
  EnhancementLevel,
  Signal,
} from './types/index';
import { SDK_VERSION } from './version';

const DEFAULT_ENDPOINT = '/_device/collect';
const DEFAULT_TIMEOUT_MS = 1500;
const DEFAULT_CACHE_TTL = 3600;

const DEFAULT_COLLECTORS: Required<CollectorsConfig> = {
  audio: true,
  canvas: true,
  webgl: true,
  screen: true,
  timezone: true,
  navigator: true,
  clientHints: true,
  capabilities: true,
  automation: true,
  fonts: false,
};

const COLLECTOR_LEVELS: Record<string, EnhancementLevel> = {
  navigator: 1,
  client_hints: 1,
  screen: 1,
  timezone: 1,
  capabilities: 1,
  automation: 3,
  canvas: 2,
  webgl: 2,
  audio: 3,
  fonts: 3,
};

/**
 * Browser Device Intelligence client.
 *
 * `collect()` never throws into application code. Failures resolve to
 * `{ ok: false, degraded: true }` and omit the failing collector.
 */
export class DeviceIntelligence {
  private readonly endpoint: string;
  private readonly timeout: number;
  private readonly cacheTtl: number;
  private readonly cacheEnabled: boolean;
  private readonly consent: { highEntropy: boolean };
  private readonly cache = new MemoryCache<CachedObservation>();
  private readonly collectors: Collector[] = [];

  /**
   * @param options - Optional endpoint, collector flags, cache, and timeout
   */
  constructor(options: DeviceIntelligenceOptions = {}) {
    this.endpoint = options.endpoint ?? DEFAULT_ENDPOINT;
    this.timeout = positiveTimeout(options.timeout);
    this.cacheEnabled = options.cache?.enabled ?? true;
    this.cacheTtl = options.cache?.ttl ?? DEFAULT_CACHE_TTL;
    this.consent = {
      highEntropy: options.consent?.highEntropy ?? true,
    };
    this.collectors = createBuiltInCollectors({
      ...DEFAULT_COLLECTORS,
      ...options.collectors,
    });
  }

  /**
   * Collect enabled signals and POST them to the configured endpoint.
   * Returns a cached observation when the TTL is still valid.
   *
   * @returns Collect result; never rejects — failures set `ok: false` and `degraded: true`.
   */
  async collect(): Promise<CollectResult> {
    return this.runCollect(false);
  }

  /**
   * Force a new collection, ignoring any cached observation.
   *
   * @returns Collect result; never rejects.
   */
  async refresh(): Promise<CollectResult> {
    return this.runCollect(true);
  }

  /**
   * Last cached observation, or `null` when empty / expired.
   */
  getCachedObservation(): CachedObservation | null {
    return this.cache.get();
  }

  /** Drop the in-memory cache. */
  clear(): void {
    this.cache.clear();
  }

  /**
   * Register an additional collector. It runs on the next `collect()` / `refresh()`.
   *
   * @param collector Collector implementation with `name` and `collect()`.
   */
  registerCollector(collector: Collector): void {
    this.collectors.push(collector);
  }

  private async runCollect(forceRefresh: boolean): Promise<CollectResult> {
    try {
      if (!forceRefresh && this.cacheEnabled) {
        const cached = this.cache.get();
        if (cached) {
          return cached.result;
        }
      }

      const controller = new AbortController();
      const timer = globalThis.setTimeout(() => controller.abort(), this.timeout);
      const ctx: CollectorContext = {
        timeout: this.timeout,
        abortSignal: controller.signal,
        consent: this.consent,
      };

      let signals: Record<string, Signal> = {};
      try {
        signals = await this.collectSignals(ctx);
      } finally {
        globalThis.clearTimeout(timer);
      }

      const enabledCount = this.collectors.length;
      const collectedCount = Object.keys(signals).length;
      const collectorsDegraded = collectedCount < enabledCount;

      const payload = this.toPayload(signals);
      const transportController = new AbortController();
      const transportTimer = globalThis.setTimeout(
        () => transportController.abort(),
        this.timeout,
      );

      let server = null;
      try {
        server = await postCollect(this.endpoint, payload, transportController.signal);
      } finally {
        globalThis.clearTimeout(transportTimer);
      }

      const transportFailed = server === null;
      const hasSignals = collectedCount > 0;
      const result: CollectResult = {
        ok: !transportFailed && hasSignals && server?.ok !== false,
        degraded: Boolean(server?.degraded) || collectorsDegraded || transportFailed || !hasSignals,
        signals,
      };

      if (server?.new !== undefined) {
        result.new = server.new;
      }
      if (server?.token !== undefined) {
        result.token = server.token;
      }
      if (server?.expiresAt !== undefined) {
        result.expiresAt = server.expiresAt;
      }
      if (server?.deviceId !== undefined) {
        result.deviceId = server.deviceId;
      }
      if (server?.confidence !== undefined) {
        result.confidence = server.confidence;
      }
      if (server?.risk !== undefined) {
        result.risk = server.risk;
      }

      if (transportFailed || !hasSignals) {
        result.ok = false;
        result.degraded = true;
      }

      const observation: CachedObservation = {
        timestamp: Date.now(),
        signals,
        result,
      };

      if (this.cacheEnabled) {
        this.cache.set(observation, this.cacheTtl);
      }

      return result;
    } catch {
      return { ok: false, degraded: true, signals: {} };
    }
  }

  /**
   * Run collectors in parallel. Each collector is raced against the shared
   * AbortSignal. Level-1 results are preferred if Level 2/3 still pending
   * when the timeout fires.
   */
  private async collectSignals(ctx: CollectorContext): Promise<Record<string, Signal>> {
    const signals: Record<string, Signal> = {};
    const jobs = this.collectors.map(async (collector) => {
      try {
        const signal = await raceAbort(collector.collect(ctx), ctx.abortSignal);
        if (signal && typeof signal.name === 'string') {
          signals[signal.name] = signal;
        }
      } catch {
        // Omit failed / timed-out collectors.
      }
    });

    const allSettled = Promise.all(jobs);
    const timeoutDone = sleep(this.timeout, ctx.abortSignal);

    await Promise.race([allSettled, timeoutDone]);
    await Promise.allSettled(jobs);

    return signals;
  }

  private toPayload(signals: Record<string, Signal>): CollectPayload {
    const wire: Record<string, CollectSignalWire> = {};
    for (const [name, signal] of Object.entries(signals)) {
      wire[name] = {
        value: signal.value,
        quality: signal.quality,
        collectedAt: signal.collectedAt,
      };
    }

    return {
      v: 1,
      sdkVersion: SDK_VERSION,
      timestamp: Math.floor(Date.now() / 1000),
      nonce: createNonce(),
      consent: this.consent,
      signals: wire,
    };
  }
}

function createBuiltInCollectors(enabled: Required<CollectorsConfig>): Collector[] {
  const list: Collector[] = [];
  if (enabled.navigator) {
    list.push(new NavigatorCollector());
  }
  if (enabled.clientHints) {
    list.push(new ClientHintsCollector());
  }
  if (enabled.screen) {
    list.push(new ScreenCollector());
  }
  if (enabled.timezone) {
    list.push(new TimezoneCollector());
  }
  if (enabled.capabilities) {
    list.push(new CapabilitiesCollector());
  }
  if (enabled.automation) {
    list.push(new AutomationCollector());
  }
  if (enabled.canvas) {
    list.push(new CanvasCollector());
  }
  if (enabled.webgl) {
    list.push(new WebGlCollector());
  }
  if (enabled.audio) {
    list.push(new AudioCollector());
  }
  if (enabled.fonts) {
    list.push(new FontsCollector());
  }
  return list;
}

function positiveTimeout(value: number | undefined): number {
  if (typeof value === 'number' && Number.isFinite(value) && value > 0) {
    return value;
  }
  return DEFAULT_TIMEOUT_MS;
}

function createNonce(): string {
  const bytes = new Uint8Array(16);
  if (globalThis.crypto?.getRandomValues) {
    globalThis.crypto.getRandomValues(bytes);
  } else {
    for (let i = 0; i < bytes.length; i += 1) {
      bytes[i] = Math.floor(Math.random() * 256);
    }
  }
  let hex = '';
  for (let i = 0; i < bytes.length; i += 1) {
    hex += (bytes[i] ?? 0).toString(16).padStart(2, '0');
  }
  return hex;
}

function sleep(ms: number, signal: AbortSignal): Promise<void> {
  return new Promise((resolve) => {
    if (signal.aborted) {
      resolve();
      return;
    }
    const id = globalThis.setTimeout(resolve, ms);
    signal.addEventListener(
      'abort',
      () => {
        globalThis.clearTimeout(id);
        resolve();
      },
      { once: true },
    );
  });
}

/** @internal Exported for tests that need collector level metadata. */
export function collectorLevel(name: string): EnhancementLevel {
  return COLLECTOR_LEVELS[name] ?? 2;
}
