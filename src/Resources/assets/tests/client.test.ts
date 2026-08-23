import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';
import { DeviceIntelligence } from '../src/client';
import type { Collector, CollectorContext, Signal } from '../src/types/index';

function failingCollector(name = 'boom'): Collector {
  return {
    name,
    collect(): Promise<Signal> {
      return Promise.reject(new Error('collector exploded'));
    },
  };
}

function okCollector(name: string, value: unknown = name): Collector {
  return {
    name,
    async collect(_ctx: CollectorContext): Promise<Signal> {
      return {
        name,
        value,
        normalizedValue: value,
        quality: 1,
        stability: 1,
        entropyCategory: 'low',
        collectedAt: Date.now(),
      };
    },
  };
}

describe('DeviceIntelligence.collect', () => {
  beforeEach(() => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        text: async () => JSON.stringify({ ok: true, token: 't1' }),
      }),
    );
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('does not throw when collectors fail and returns a degraded result', async () => {
    const client = new DeviceIntelligence({
      collectors: {
        audio: false,
        canvas: false,
        webgl: false,
        screen: false,
        timezone: false,
        navigator: false,
        clientHints: false,
        capabilities: false,
        automation: false,
        fonts: false,
      },
    });
    client.registerCollector(failingCollector());

    let result: unknown;
    await expect(
      (async () => {
        result = await client.collect();
      })(),
    ).resolves.toBeUndefined();

    expect(result).toEqual(
      expect.objectContaining({
        ok: false,
        degraded: true,
        signals: {},
      }),
    );
  });

  it('does not throw when fetch rejects', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('network down')));

    const client = new DeviceIntelligence({
      collectors: {
        audio: false,
        canvas: false,
        webgl: false,
        screen: false,
        timezone: false,
        navigator: false,
        clientHints: false,
        capabilities: false,
        automation: false,
        fonts: false,
      },
    });
    client.registerCollector(okCollector('custom'));

    const result = await client.collect();
    expect(result.ok).toBe(false);
    expect(result.degraded).toBe(true);
    expect(result.signals.custom?.value).toBe('custom');
  });

  it('omits a failing collector and keeps successful ones', async () => {
    const client = new DeviceIntelligence({
      collectors: {
        audio: false,
        canvas: false,
        webgl: false,
        screen: false,
        timezone: false,
        navigator: false,
        clientHints: false,
        capabilities: false,
        automation: false,
        fonts: false,
      },
    });
    client.registerCollector(okCollector('keep'));
    client.registerCollector(failingCollector('drop'));

    const result = await client.collect();
    expect(result.signals.keep).toBeDefined();
    expect(result.signals.drop).toBeUndefined();
    expect(result.degraded).toBe(true);
  });

  it('returns cached result on a second collect() within TTL', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      text: async () => JSON.stringify({ ok: true }),
    });
    vi.stubGlobal('fetch', fetchMock);

    const client = new DeviceIntelligence({
      cache: { enabled: true, ttl: 3600 },
      collectors: {
        audio: false,
        canvas: false,
        webgl: false,
        screen: false,
        timezone: false,
        navigator: false,
        clientHints: false,
        capabilities: false,
        automation: false,
        fonts: false,
      },
    });
    client.registerCollector(okCollector('keep'));

    const first = await client.collect();
    const second = await client.collect();
    expect(second).toBe(first);
    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(client.getCachedObservation()?.result).toBe(first);
  });

  it('refresh() bypasses the cache', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      text: async () => JSON.stringify({ ok: true }),
    });
    vi.stubGlobal('fetch', fetchMock);

    const client = new DeviceIntelligence({
      collectors: {
        audio: false,
        canvas: false,
        webgl: false,
        screen: false,
        timezone: false,
        navigator: false,
        clientHints: false,
        capabilities: false,
        automation: false,
        fonts: false,
      },
    });
    client.registerCollector(okCollector('keep'));

    await client.collect();
    await client.refresh();
    expect(fetchMock).toHaveBeenCalledTimes(2);
  });

  it('POSTs protocol v1 JSON with same-origin credentials', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      text: async () => JSON.stringify({ ok: true }),
    });
    vi.stubGlobal('fetch', fetchMock);

    const client = new DeviceIntelligence({
      endpoint: '/_device/collect',
      collectors: {
        audio: false,
        canvas: false,
        webgl: false,
        screen: false,
        timezone: false,
        navigator: false,
        clientHints: false,
        capabilities: false,
        automation: false,
        fonts: false,
      },
    });
    client.registerCollector(okCollector('keep', 'v'));

    await client.collect();

    expect(fetchMock).toHaveBeenCalledTimes(1);
    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toBe('/_device/collect');
    expect(init.method).toBe('POST');
    expect(init.credentials).toBe('same-origin');
    expect(init.headers).toEqual({ 'Content-Type': 'application/json' });

    const body = JSON.parse(String(init.body)) as {
      v: number;
      sdkVersion: string;
      signals: Record<string, { value: unknown; quality: number; collectedAt: number }>;
    };
    expect(body.v).toBe(1);
    expect(body.sdkVersion).toBe('1.0.0');
    expect(body.signals.keep?.value).toBe('v');
  });
});
