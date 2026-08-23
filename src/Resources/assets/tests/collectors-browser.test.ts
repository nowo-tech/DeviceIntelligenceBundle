import { afterEach, describe, expect, it, vi } from 'vitest';
import { AudioCollector } from '../src/collectors/audio';
import { CanvasCollector } from '../src/collectors/canvas';
import { CapabilitiesCollector } from '../src/collectors/capabilities';
import { ClientHintsCollector } from '../src/collectors/client-hints';
import { FontsCollector } from '../src/collectors/fonts';
import { NavigatorCollector } from '../src/collectors/navigator';
import { ScreenCollector } from '../src/collectors/screen';
import { TimezoneCollector } from '../src/collectors/timezone';
import { WebGlCollector } from '../src/collectors/webgl';
import { DeviceIntelligence } from '../src/client';
import type { CollectorContext } from '../src/types/index';

function ctx(highEntropy = true): CollectorContext {
  return {
    timeout: 1500,
    abortSignal: new AbortController().signal,
    consent: { highEntropy },
  };
}

function stubSubtle(): void {
  if (!globalThis.crypto?.subtle?.digest) {
    vi.stubGlobal('crypto', {
      ...globalThis.crypto,
      subtle: {
        digest: async () => new Uint8Array(32).buffer,
      },
      getRandomValues: (arr: Uint8Array) => {
        arr.fill(1);
        return arr;
      },
    });
  }
}

describe('built-in collectors', () => {
  afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
  });

  it('collects timezone, screen, navigator, and capabilities', async () => {
    const tz = await new TimezoneCollector().collect(ctx());
    expect(tz.name).toBe('timezone');
    expect(typeof tz.normalizedValue).toBe('string');

    const screenSignal = await new ScreenCollector().collect(ctx());
    expect(screenSignal.name).toBe('screen');
    expect(screenSignal.normalizedValue).toEqual(
      expect.objectContaining({ screenClass: expect.any(String) }),
    );

    const nav = await new NavigatorCollector().collect(ctx());
    expect(nav.name).toBe('navigator');
    expect(nav.normalizedValue).toEqual(expect.objectContaining({ browser: expect.any(String) }));

    const caps = await new CapabilitiesCollector().collect(ctx());
    expect(caps.name).toBe('capabilities');
    expect(Array.isArray(caps.normalizedValue)).toBe(true);
  });

  it('collects client hints without UA-CH and with high-entropy brands', async () => {
    const low = await new ClientHintsCollector().collect(ctx());
    expect(low.quality).toBe(0.35);

    Object.defineProperty(navigator, 'userAgentData', {
      configurable: true,
      value: {
        brands: [
          { brand: 'Not:A-Brand', version: '99' },
          { brand: 'Chromium', version: '143' },
        ],
        mobile: false,
        platform: 'macOS',
        getHighEntropyValues: async () => ({ uaFullVersion: '143.0.0.0' }),
      },
    });
    const high = await new ClientHintsCollector().collect(ctx(true));
    expect(high.quality).toBe(0.95);

    Object.defineProperty(navigator, 'userAgentData', {
      configurable: true,
      value: {
        brands: [{ brand: 'Chromium', version: '1' }],
        mobile: true,
        platform: 'Android',
        getHighEntropyValues: async () => {
          throw new Error('denied');
        },
      },
    });
    const denied = await new ClientHintsCollector().collect(ctx(true));
    expect(denied.quality).toBe(0.7);
  });

  it('digests a mocked 2d canvas scene', async () => {
    stubSubtle();
    const ctx2d = {
      fillStyle: '',
      strokeStyle: '',
      lineWidth: 0,
      font: '',
      textBaseline: '',
      fillRect: vi.fn(),
      beginPath: vi.fn(),
      arc: vi.fn(),
      stroke: vi.fn(),
      fillText: vi.fn(),
      getImageData: () => ({ data: new Uint8ClampedArray([1, 2, 3, 4]) }),
    };
    const orig = document.createElement.bind(document);
    vi.spyOn(document, 'createElement').mockImplementation(((tag: string, options?: string) => {
      if (tag === 'canvas') {
        return {
          width: 0,
          height: 0,
          style: {},
          getContext: (type: string) => (type === '2d' ? ctx2d : null),
        } as unknown as HTMLCanvasElement;
      }
      return orig(tag, options as never);
    }) as typeof document.createElement);

    const signal = await new CanvasCollector().collect(ctx());
    expect(signal.name).toBe('canvas');
    expect(typeof signal.value).toBe('string');
    expect(ctx2d.fillRect).toHaveBeenCalled();
  });

  it('reads a mocked WebGL context including debug info and Int32 viewport', async () => {
    stubSubtle();
    const lose = { loseContext: vi.fn() };
    const debug = { UNMASKED_VENDOR_WEBGL: 0x9245, UNMASKED_RENDERER_WEBGL: 0x9246 };
    const gl = {
      VENDOR: 0x1f00,
      RENDERER: 0x1f01,
      MAX_TEXTURE_SIZE: 0x0d33,
      MAX_VIEWPORT_DIMS: 0x0d3a,
      MAX_RENDERBUFFER_SIZE: 0x84e8,
      MAX_VERTEX_ATTRIBS: 0x8869,
      MAX_FRAGMENT_UNIFORM_VECTORS: 0x8dfd,
      getExtension: (name: string) => {
        if (name === 'WEBGL_debug_renderer_info') {
          return debug;
        }
        if (name === 'WEBGL_lose_context') {
          return lose;
        }
        return null;
      },
      getParameter: (pname: number) => {
        if (pname === debug.UNMASKED_VENDOR_WEBGL) {
          return 'Apple';
        }
        if (pname === debug.UNMASKED_RENDERER_WEBGL) {
          return 'Apple GPU';
        }
        if (pname === gl.MAX_VIEWPORT_DIMS) {
          return new Int32Array([1024, 768]);
        }
        return 16;
      },
      getSupportedExtensions: () => ['EXT_a', 'EXT_b'],
    };
    const orig = document.createElement.bind(document);
    vi.spyOn(document, 'createElement').mockImplementation(((tag: string, options?: string) => {
      if (tag === 'canvas') {
        return {
          width: 0,
          height: 0,
          getContext: (type: string) => (type === 'webgl' || type === 'experimental-webgl' ? gl : null),
        } as unknown as HTMLCanvasElement;
      }
      return orig(tag, options as never);
    }) as typeof document.createElement);

    const signal = await new WebGlCollector().collect(ctx());
    expect(signal.name).toBe('webgl');
    expect(signal.normalizedValue).toEqual(expect.objectContaining({ gpuFamily: 'apple' }));
    expect(lose.loseContext).toHaveBeenCalled();
  });

  it('renders audio through a stub OfflineAudioContext', async () => {
    stubSubtle();
    class FakeOffline {
      destination = {};
      createOscillator() {
        return { type: '', frequency: { value: 0 }, connect: vi.fn(), start: vi.fn() };
      }
      createDynamicsCompressor() {
        return {
          threshold: { value: 0 },
          knee: { value: 0 },
          ratio: { value: 0 },
          attack: { value: 0 },
          release: { value: 0 },
          connect: vi.fn(),
        };
      }
      startRendering() {
        return Promise.resolve({ getChannelData: () => new Float32Array(512) });
      }
    }
    vi.stubGlobal('OfflineAudioContext', FakeOffline);
    const signal = await new AudioCollector().collect(ctx());
    expect(signal.name).toBe('audio');
    expect(typeof signal.value).toBe('string');
  });

  it('throws when 2d context is missing', async () => {
    const orig = document.createElement.bind(document);
    vi.spyOn(document, 'createElement').mockImplementation(((tag: string, options?: string) => {
      if (tag === 'canvas') {
        return { width: 0, height: 0, style: {}, getContext: () => null } as unknown as HTMLCanvasElement;
      }
      return orig(tag, options as never);
    }) as typeof document.createElement);
    await expect(new CanvasCollector().collect(ctx())).rejects.toThrow(/CanvasRenderingContext2D/);
  });

  it('falls back to experimental-webgl and swallows getParameter errors', async () => {
    stubSubtle();
    const gl = {
      VENDOR: 1,
      RENDERER: 2,
      MAX_TEXTURE_SIZE: 3,
      MAX_VIEWPORT_DIMS: 4,
      MAX_RENDERBUFFER_SIZE: 5,
      MAX_VERTEX_ATTRIBS: 6,
      MAX_FRAGMENT_UNIFORM_VECTORS: 7,
      getExtension: () => null,
      getParameter: (pname: number) => {
        if (pname === 4) {
          return [800, 600];
        }
        return null;
      },
      getSupportedExtensions: () => null,
    };
    const orig = document.createElement.bind(document);
    vi.spyOn(document, 'createElement').mockImplementation(((tag: string, options?: string) => {
      if (tag === 'canvas') {
        return {
          width: 0,
          height: 0,
          getContext: (type: string) => (type === 'experimental-webgl' ? gl : null),
        } as unknown as HTMLCanvasElement;
      }
      return orig(tag, options as never);
    }) as typeof document.createElement);
    const signal = await new WebGlCollector().collect(ctx());
    expect(signal.quality).toBe(0.45);
  });

  it('falls back timezone when Intl throws', async () => {
    vi.spyOn(Intl, 'DateTimeFormat').mockImplementation(() => {
      throw new Error('intl');
    });
    const signal = await new TimezoneCollector().collect(ctx());
    expect(signal.normalizedValue).toBe('UTC');
  });

  it('probes fonts via span widths', async () => {
    const orig = document.createElement.bind(document);
    vi.spyOn(document, 'createElement').mockImplementation(((tag: string, options?: string) => {
      if (tag === 'span') {
        const el = orig('span');
        Object.defineProperty(el, 'offsetWidth', {
          get() {
            return String(el.style.font).includes('Arial') ? 80 : 40;
          },
        });
        return el;
      }
      return orig(tag, options as never);
    }) as typeof document.createElement);

    const signal = await new FontsCollector().collect(ctx());
    expect(signal.name).toBe('fonts');
    expect(Array.isArray(signal.value)).toBe(true);
  });
});

describe('DeviceIntelligence built-in path', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('maps server fields and defaults timeout', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        text: async () =>
          JSON.stringify({
            ok: true,
            new: true,
            token: 'tok',
            expiresAt: 1,
            degraded: false,
            deviceId: 'd1',
            confidence: 0.9,
            risk: { score: 1, level: 'low' },
          }),
      }),
    );
    const client = new DeviceIntelligence({
      timeout: 0,
      cache: { enabled: false },
      collectors: {
        audio: false,
        canvas: false,
        webgl: false,
        screen: true,
        timezone: true,
        navigator: true,
        clientHints: true,
        capabilities: true,
        automation: true,
        fonts: true,
      },
    });
    const result = await client.collect();
    expect(result.token).toBe('tok');
    expect(result.deviceId).toBe('d1');
    expect(result.new).toBe(true);
    expect(result.risk).toEqual({ score: 1, level: 'low' });
    client.clear();
  });
});
