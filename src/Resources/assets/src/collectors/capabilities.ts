import type { Collector, CollectorContext, Signal } from '../types/index';
import { createSignal, throwIfAborted } from './collector';

/**
 * Boolean capability set used for Jaccard matching. Avoids expensive probes.
 */
export class CapabilitiesCollector implements Collector {
  readonly name = 'capabilities';

  async collect(ctx: CollectorContext): Promise<Signal> {
    throwIfAborted(ctx);

    const nav = typeof navigator === 'undefined' ? null : navigator;
    const win = typeof window === 'undefined' ? null : window;

    const value = {
      cookieEnabled: nav?.cookieEnabled ?? false,
      pdfViewerEnabled: Boolean((nav as Navigator & { pdfViewerEnabled?: boolean } | null)?.pdfViewerEnabled),
      serviceWorker: Boolean(nav && 'serviceWorker' in nav),
      webgl: hasWebGl(),
      webgpu: Boolean(nav && 'gpu' in nav),
      wasm: typeof WebAssembly !== 'undefined',
      sharedArrayBuffer: typeof SharedArrayBuffer !== 'undefined',
      touch: (nav?.maxTouchPoints ?? 0) > 0,
      matchMedia: typeof win?.matchMedia === 'function',
      localStorage: hasStorage('localStorage'),
      sessionStorage: hasStorage('sessionStorage'),
      indexedDB: typeof indexedDB !== 'undefined',
    };

    const flags = Object.entries(value)
      .filter(([, enabled]) => enabled)
      .map(([key]) => key)
      .sort();

    return createSignal({
      name: this.name,
      value,
      normalizedValue: flags,
      quality: 0.9,
      stability: 0.9,
      entropyCategory: 'medium',
    });
  }
}

function hasWebGl(): boolean {
  if (typeof document === 'undefined') {
    return false;
  }
  // jsdom implements getContext but cannot create a GPU context and logs loudly.
  if (typeof navigator !== 'undefined' && /jsdom/i.test(navigator.userAgent)) {
    return false;
  }
  try {
    const canvas = document.createElement('canvas');
    return Boolean(canvas.getContext('webgl') || canvas.getContext('experimental-webgl'));
  } catch {
    return false;
  }
}

function hasStorage(name: 'localStorage' | 'sessionStorage'): boolean {
  try {
    const storage = globalThis[name];
    const key = '__di_cap__';
    storage.setItem(key, '1');
    storage.removeItem(key);
    return true;
  } catch {
    return false;
  }
}
