import type { Collector, CollectorContext, Signal } from '../types/index';
import { clamp01, createSignal, throwIfAborted } from './collector';

export interface AutomationValue {
  indicators: string[];
  confidence: number;
}

const WEIGHTS: Record<string, number> = {
  webdriver: 0.7,
  'headless-ua': 0.6,
  'missing-languages': 0.25,
  'language-mismatch': 0.2,
  'missing-hardware-concurrency': 0.2,
  'implausible-hardware-concurrency': 0.25,
  'zero-outer-size': 0.2,
  'headless-chrome': 0.55,
};

/**
 * Automation heuristics. Returns indicators + confidence, never a boolean.
 */
export class AutomationCollector implements Collector {
  readonly name = 'automation';

  async collect(ctx: CollectorContext): Promise<Signal> {
    throwIfAborted(ctx);

    const indicators: string[] = [];
    const nav = typeof navigator === 'undefined' ? null : navigator;
    const userAgent = nav?.userAgent ?? '';

    if (nav && nav.webdriver === true) {
      indicators.push('webdriver');
    }

    if (!nav || !Array.isArray(nav.languages) || nav.languages.length === 0) {
      indicators.push('missing-languages');
    } else if (nav.language && nav.languages[0] && nav.language !== nav.languages[0]) {
      indicators.push('language-mismatch');
    }

    if (!nav || typeof nav.hardwareConcurrency !== 'number') {
      indicators.push('missing-hardware-concurrency');
    } else if (nav.hardwareConcurrency <= 0 || nav.hardwareConcurrency > 128) {
      indicators.push('implausible-hardware-concurrency');
    }

    if (/headless/i.test(userAgent)) {
      indicators.push('headless-ua');
    }
    if (/HeadlessChrome/i.test(userAgent)) {
      indicators.push('headless-chrome');
    }

    if (typeof window !== 'undefined' && window.outerWidth === 0 && window.outerHeight === 0) {
      indicators.push('zero-outer-size');
    }

    const confidence = clamp01(
      indicators.reduce((sum, name) => sum + (WEIGHTS[name] ?? 0.15), 0),
    );

    const value: AutomationValue = {
      indicators,
      confidence,
    };

    return createSignal({
      name: this.name,
      value,
      normalizedValue: value,
      quality: 0.8,
      stability: 0.5,
      entropyCategory: 'low',
    });
  }
}
