import { normalizeBrowser, normalizePlatform } from '../normalization/normalize';
import type { Collector, CollectorContext, NavigatorUADataLike, Signal } from '../types/index';
import { createSignal, raceAbort, throwIfAborted } from './collector';

const HIGH_ENTROPY_HINTS = [
  'architecture',
  'bitness',
  'model',
  'platformVersion',
  'uaFullVersion',
  'fullVersionList',
] as const;

/**
 * JS User-Agent Client Hints. Never the sole identity source; quality drops
 * when high-entropy values are denied or unavailable.
 */
export class ClientHintsCollector implements Collector {
  readonly name = 'client_hints';

  async collect(ctx: CollectorContext): Promise<Signal> {
    throwIfAborted(ctx);

    if (typeof navigator === 'undefined') {
      throw new Error('navigator is not available');
    }

    const uaData = (navigator as Navigator & { userAgentData?: NavigatorUADataLike }).userAgentData;

    if (!uaData) {
      return createSignal({
        name: this.name,
        value: {
          brands: [],
          mobile: null,
          platform: '',
          highEntropy: false,
          available: false,
        },
        normalizedValue: {
          browser: normalizeBrowser(navigator.userAgent ?? ''),
          platform: normalizePlatform(navigator.platform ?? navigator.userAgent ?? ''),
          highEntropy: false,
        },
        quality: 0.35,
        stability: 0.7,
        entropyCategory: 'medium',
      });
    }

    const base = {
      brands: Array.isArray(uaData.brands) ? uaData.brands : [],
      mobile: typeof uaData.mobile === 'boolean' ? uaData.mobile : null,
      platform: uaData.platform ?? '',
      highEntropy: false,
      available: true,
    };

    let highEntropy: Record<string, unknown> = {};
    let gotHighEntropy = false;

    if (ctx.consent.highEntropy && typeof uaData.getHighEntropyValues === 'function') {
      try {
        highEntropy = await raceAbort(
          uaData.getHighEntropyValues([...HIGH_ENTROPY_HINTS]),
          ctx.abortSignal,
        );
        gotHighEntropy = true;
      } catch {
        gotHighEntropy = false;
      }
    }

    const value = {
      ...base,
      ...highEntropy,
      highEntropy: gotHighEntropy,
    };

    const brandLabel = firstBrand(base.brands);
    const versionHint =
      typeof highEntropy['uaFullVersion'] === 'string'
        ? String(highEntropy['uaFullVersion'])
        : brandLabel?.version ?? '';
    const browserInput = brandLabel
      ? `${brandLabel.brand} ${versionHint}`
      : navigator.userAgent;

    return createSignal({
      name: this.name,
      value,
      normalizedValue: {
        browser: normalizeBrowser(browserInput),
        platform: normalizePlatform(base.platform || navigator.platform || ''),
        highEntropy: gotHighEntropy,
      },
      quality: gotHighEntropy ? 0.95 : 0.7,
      stability: 0.75,
      entropyCategory: 'medium',
    });
  }
}

function firstBrand(brands: Array<{ brand: string; version: string }>): {
  brand: string;
  version: string;
} | null {
  const preferred = brands.find((item) => {
    const brand = item.brand.toLowerCase();
    return brand !== 'not?a;brand' && brand !== 'not a;brand' && !brand.includes('not:');
  });
  return preferred ?? brands[0] ?? null;
}
