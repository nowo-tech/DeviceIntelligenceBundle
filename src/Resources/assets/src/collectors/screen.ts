import { normalizeScreenClass } from '../normalization/normalize';
import type { Collector, CollectorContext, Signal } from '../types/index';
import { createSignal, throwIfAborted } from './collector';

/**
 * Screen geometry and color depth. Window size is omitted (too volatile).
 */
export class ScreenCollector implements Collector {
  readonly name = 'screen';

  async collect(ctx: CollectorContext): Promise<Signal> {
    throwIfAborted(ctx);

    const screenRef = typeof screen === 'undefined' ? null : screen;
    if (!screenRef) {
      throw new Error('screen is not available');
    }

    const width = finiteOrZero(screenRef.width);
    const height = finiteOrZero(screenRef.height);
    const value = {
      width,
      height,
      availWidth: finiteOrZero(screenRef.availWidth),
      availHeight: finiteOrZero(screenRef.availHeight),
      colorDepth: finiteOrZero(screenRef.colorDepth),
      pixelDepth: finiteOrZero(screenRef.pixelDepth),
      pixelRatio: finiteOrZero(typeof devicePixelRatio === 'number' ? devicePixelRatio : 1),
    };

    const screenClass = normalizeScreenClass(width, height);

    return createSignal({
      name: this.name,
      value,
      normalizedValue: {
        screenClass,
        width,
        height,
      },
      quality: width > 0 && height > 0 ? 0.95 : 0.4,
      stability: 0.7,
      entropyCategory: 'low',
    });
  }
}

function finiteOrZero(value: number): number {
  return Number.isFinite(value) ? value : 0;
}
