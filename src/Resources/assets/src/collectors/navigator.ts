import { normalizeBrowser, normalizePlatform } from '../normalization/normalize';
import type { Collector, CollectorContext, Signal } from '../types/index';
import { createSignal, throwIfAborted } from './collector';

/**
 * Compact navigator snapshot (Level 1). No high-entropy Client Hints here.
 */
export class NavigatorCollector implements Collector {
  readonly name = 'navigator';

  async collect(ctx: CollectorContext): Promise<Signal> {
    throwIfAborted(ctx);

    if (typeof navigator === 'undefined') {
      throw new Error('navigator is not available');
    }

    const userAgent = navigator.userAgent ?? '';
    const platform = navigator.platform ?? '';
    const languages = Array.isArray(navigator.languages) ? [...navigator.languages] : [];
    const nav = navigator as Navigator & { deviceMemory?: number };

    const value = {
      userAgent,
      platform,
      language: navigator.language ?? '',
      languages,
      hardwareConcurrency: navigator.hardwareConcurrency ?? null,
      deviceMemory: typeof nav.deviceMemory === 'number' ? nav.deviceMemory : null,
      maxTouchPoints: navigator.maxTouchPoints ?? 0,
      vendor: navigator.vendor ?? '',
    };

    const browser = normalizeBrowser(userAgent);
    const platformFamily = normalizePlatform(platform || userAgent);

    return createSignal({
      name: this.name,
      value,
      normalizedValue: {
        browser,
        platform: platformFamily,
        hardwareConcurrency: value.hardwareConcurrency,
      },
      quality: userAgent.length > 0 ? 0.9 : 0.4,
      stability: 0.8,
      entropyCategory: 'medium',
    });
  }
}
