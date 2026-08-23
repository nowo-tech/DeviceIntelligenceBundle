import type { Collector, CollectorContext, Signal } from '../types/index';
import { createSignal, throwIfAborted } from './collector';

const SAMPLE = 'mmmmmmmmmmlli';
const BASELINE = 'monospace';
const CANDIDATES = ['Arial', 'Times New Roman', 'Courier New', 'Georgia', 'Verdana', 'Roboto'];

/**
 * Cheap width-based font probe. Disabled by default (Level 3 / high entropy).
 */
export class FontsCollector implements Collector {
  readonly name = 'fonts';

  async collect(ctx: CollectorContext): Promise<Signal> {
    throwIfAborted(ctx);

    if (typeof document === 'undefined' || !document.body) {
      throw new Error('document.body is not available');
    }

    const baseline = measureWidth(SAMPLE, BASELINE);
    const detected = CANDIDATES.filter((font) => {
      const width = measureWidth(SAMPLE, `${font}, ${BASELINE}`);
      return width !== baseline;
    });

    return createSignal({
      name: this.name,
      value: detected,
      normalizedValue: [...detected].sort(),
      quality: 0.7,
      stability: 0.8,
      entropyCategory: 'high',
    });
  }
}

function measureWidth(text: string, fontFamily: string): number {
  const span = document.createElement('span');
  span.style.cssText = [
    'position:absolute',
    'left:-9999px',
    'top:0',
    'visibility:hidden',
    'white-space:nowrap',
    `font:72px ${fontFamily}`,
  ].join(';');
  span.textContent = text;
  document.body.appendChild(span);
  const width = span.offsetWidth;
  document.body.removeChild(span);
  return width;
}
