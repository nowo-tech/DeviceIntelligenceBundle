import { afterEach, describe, expect, it, vi } from 'vitest';
import { compactDigest, sha256Hex } from '../src/crypto/digest';
import { clamp01, createSignal, raceAbort, throwIfAborted } from '../src/collectors/collector';
import { postCollect, readProfilerPreviousToken } from '../src/transport/fetch-transport';
import { MemoryCache } from '../src/cache/memory-cache';
import {
  DeviceIntelligence,
  MemoryCache as ExportedCache,
  SDK_VERSION,
  normalizeBrowser,
  normalizeGpuFamily,
  normalizePlatform,
  normalizeScreenClass,
} from '../src/index';
import { collectorLevel } from '../src/client';
import type { CollectorContext } from '../src/types/index';

function ctx(aborted = false): CollectorContext {
  const controller = new AbortController();
  if (aborted) {
    controller.abort();
  }
  return {
    timeout: 1500,
    abortSignal: controller.signal,
    consent: { highEntropy: true },
  };
}

describe('public index exports', () => {
  it('re-exports the client, cache, version, and normalizers', () => {
    expect(DeviceIntelligence).toBeTypeOf('function');
    expect(ExportedCache).toBe(MemoryCache);
    expect(SDK_VERSION).toBe('1.0.0');
    expect(normalizeBrowser('')).toBe('other');
    expect(normalizePlatform('macos')).toBe('macos');
    expect(normalizeScreenClass(Number.NaN, 0)).toBe('other');
    expect(normalizeGpuFamily('Apple', 'Metal')).toBe('apple');
    expect(normalizeGpuFamily('NVIDIA', 'GeForce')).toBe('nvidia');
    expect(normalizeGpuFamily('AMD', 'Radeon')).toBe('amd');
    expect(normalizeGpuFamily('Intel', 'UHD')).toBe('intel');
    expect(normalizeGpuFamily('ARM', 'Mali-G78')).toBe('mali');
    expect(normalizeGpuFamily('Qualcomm', 'Adreno')).toBe('adreno');
    expect(normalizeGpuFamily('xx', 'yy')).toBe('other');
    expect(normalizeBrowser('OPR/90.0')).toBe('Opera 90');
    expect(normalizeBrowser('Mozilla/5.0 Firefox/128.0')).toBe('Firefox 128');
    expect(normalizeBrowser('Version/17.0 Safari/605.1.15')).toBe('Safari 17');
    expect(collectorLevel('audio')).toBe(3);
    expect(collectorLevel('unknown')).toBe(2);
  });
});

describe('digest and collector helpers', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('hashes strings and BufferSource, and truncates compactDigest', async () => {
    const hex = await sha256Hex('abc');
    expect(hex).toHaveLength(64);
    expect(await compactDigest('abc', 8)).toHaveLength(8);
    const bytes = new Uint8Array([1, 2, 3]);
    expect(await sha256Hex(bytes)).toHaveLength(64);
  });

  it('throws when SubtleCrypto is missing', async () => {
    vi.stubGlobal('crypto', {});
    await expect(sha256Hex('x')).rejects.toThrow(/SubtleCrypto/);
  });

  it('clamps quality and races abort', async () => {
    expect(clamp01(Number.NaN)).toBe(0);
    expect(clamp01(-1)).toBe(0);
    expect(clamp01(2)).toBe(1);
    expect(clamp01(0.4)).toBe(0.4);
    const signal = createSignal({
      name: 'n',
      value: 1,
      normalizedValue: 1,
      quality: 4,
      stability: -1,
      entropyCategory: 'low',
    });
    expect(signal.quality).toBe(1);
    expect(signal.stability).toBe(0);

    expect(() => throwIfAborted(ctx(true))).toThrow(/aborted/);
    await expect(raceAbort(new Promise(() => undefined), ctx(true).abortSignal)).rejects.toThrow(
      /aborted/,
    );
  });
});

describe('postCollect', () => {
  afterEach(() => {
    document.getElementById('sfwdt48ea71')?.remove();
    vi.unstubAllGlobals();
  });

  it('reads the Web Debug Toolbar token from the sfwdt node', () => {
    expect(readProfilerPreviousToken()).toBeUndefined();
    const bar = document.createElement('div');
    bar.id = 'sfwdt48ea71';
    document.body.appendChild(bar);
    expect(readProfilerPreviousToken()).toBe('48ea71');
  });

  it('sends X-Previous-Debug-Token when the toolbar is present', async () => {
    const bar = document.createElement('div');
    bar.id = 'sfwdt48ea71';
    document.body.appendChild(bar);
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, text: async () => '  ' });
    vi.stubGlobal('fetch', fetchMock);
    await postCollect('/_device/collect', {
      v: 1,
      sdkVersion: '1',
      timestamp: 1,
      nonce: 'n',
      consent: { highEntropy: true },
      signals: {},
    });
    expect(fetchMock).toHaveBeenCalledWith(
      '/_device/collect',
      expect.objectContaining({
        headers: expect.objectContaining({
          'X-Previous-Debug-Token': '48ea71',
        }),
      }),
    );
  });

  it('returns null on HTTP errors and network failures', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, text: async () => '' }));
    expect(await postCollect('/x', { v: 1, sdkVersion: '1', timestamp: 1, nonce: 'n', consent: { highEntropy: true }, signals: {} })).toBeNull();

    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('down')));
    expect(await postCollect('/x', { v: 1, sdkVersion: '1', timestamp: 1, nonce: 'n', consent: { highEntropy: true }, signals: {} })).toBeNull();
  });

  it('treats empty and non-object bodies as ok', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, text: async () => '  ' }));
    expect(await postCollect('/x', { v: 1, sdkVersion: '1', timestamp: 1, nonce: 'n', consent: { highEntropy: true }, signals: {} })).toEqual({
      ok: true,
    });

    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, text: async () => 'true' }));
    expect(await postCollect('/x', { v: 1, sdkVersion: '1', timestamp: 1, nonce: 'n', consent: { highEntropy: true }, signals: {} })).toEqual({
      ok: true,
    });
  });
});

describe('MemoryCache non-positive TTL', () => {
  it('stores nothing when ttl is zero', () => {
    const cache = new MemoryCache<string>();
    cache.set('x', 0);
    expect(cache.get()).toBeNull();
  });
});
