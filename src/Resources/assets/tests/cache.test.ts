import { afterEach, describe, expect, it, vi } from 'vitest';
import { MemoryCache } from '../src/cache/memory-cache';
import { DeviceIntelligence } from '../src/client';
import type { Collector, Signal } from '../src/types/index';

describe('MemoryCache TTL', () => {
  afterEach(() => {
    vi.useRealTimers();
  });

  it('returns the value before TTL and null after expiry', () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-01-01T00:00:00.000Z'));

    const cache = new MemoryCache<string>();
    cache.set('observation', 3600);
    expect(cache.get()).toBe('observation');
    expect(cache.has()).toBe(true);

    vi.setSystemTime(new Date('2026-01-01T00:59:59.000Z'));
    expect(cache.get()).toBe('observation');

    vi.setSystemTime(new Date('2026-01-01T01:00:00.000Z'));
    expect(cache.get()).toBeNull();
    expect(cache.has()).toBe(false);
  });

  it('clear() drops the entry immediately', () => {
    const cache = new MemoryCache<number>();
    cache.set(1, 3600);
    cache.clear();
    expect(cache.get()).toBeNull();
  });
});

describe('DeviceIntelligence cache TTL', () => {
  afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('expires the client cache after ttl seconds', async () => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-01-01T00:00:00.000Z'));

    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      text: async () => JSON.stringify({ ok: true }),
    });
    vi.stubGlobal('fetch', fetchMock);

    const collector: Collector = {
      name: 'keep',
      async collect(): Promise<Signal> {
        return {
          name: 'keep',
          value: 1,
          normalizedValue: 1,
          quality: 1,
          stability: 1,
          entropyCategory: 'low',
          collectedAt: Date.now(),
        };
      },
    };

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
    client.registerCollector(collector);

    await client.collect();
    expect(client.getCachedObservation()).not.toBeNull();

    vi.setSystemTime(new Date('2026-01-01T01:00:01.000Z'));
    expect(client.getCachedObservation()).toBeNull();

    await client.collect();
    expect(fetchMock).toHaveBeenCalledTimes(2);
  });
});
