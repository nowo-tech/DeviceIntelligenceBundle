import { describe, expect, it, afterEach } from 'vitest';
import { AutomationCollector } from '../src/collectors/automation';
import type { AutomationValue } from '../src/collectors/automation';
import type { CollectorContext } from '../src/types/index';

function ctx(): CollectorContext {
  return {
    timeout: 1500,
    abortSignal: new AbortController().signal,
    consent: { highEntropy: true },
  };
}

describe('AutomationCollector', () => {
  afterEach(() => {
    // Restore webdriver if we overwrote it.
    try {
      Object.defineProperty(navigator, 'webdriver', {
        configurable: true,
        value: false,
      });
    } catch {
      // jsdom may freeze the property; ignore.
    }
  });

  it('returns indicators and confidence, not a boolean, when webdriver is true', async () => {
    Object.defineProperty(navigator, 'webdriver', {
      configurable: true,
      value: true,
    });

    const signal = await new AutomationCollector().collect(ctx());
    const value = signal.value as AutomationValue;

    expect(Array.isArray(value.indicators)).toBe(true);
    expect(value.indicators).toContain('webdriver');
    expect(typeof value.confidence).toBe('number');
    expect(value.confidence).toBeGreaterThan(0);
    expect(typeof signal.value).not.toBe('boolean');
    expect(signal.normalizedValue).toEqual(value);
  });

  it('covers remaining automation heuristics', async () => {
    Object.defineProperty(navigator, 'language', { configurable: true, value: 'es' });
    Object.defineProperty(navigator, 'languages', { configurable: true, value: ['en'] });
    Object.defineProperty(navigator, 'hardwareConcurrency', { configurable: true, value: 200 });
    Object.defineProperty(navigator, 'userAgent', {
      configurable: true,
      value: 'Mozilla/5.0 HeadlessChrome/120',
    });
    Object.defineProperty(window, 'outerWidth', { configurable: true, value: 0 });
    Object.defineProperty(window, 'outerHeight', { configurable: true, value: 0 });

    const signal = await new AutomationCollector().collect(ctx());
    const value = signal.value as { indicators: string[] };
    expect(value.indicators).toEqual(
      expect.arrayContaining([
        'language-mismatch',
        'implausible-hardware-concurrency',
        'headless-ua',
        'headless-chrome',
        'zero-outer-size',
      ]),
    );
  });
});
