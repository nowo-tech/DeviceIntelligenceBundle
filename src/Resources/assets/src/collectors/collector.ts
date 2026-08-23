import type { CollectorContext, EntropyCategory, Signal } from '../types/index';

/**
 * Clamp a numeric quality/stability score into `[0, 1]`.
 */
export function clamp01(value: number): number {
  if (!Number.isFinite(value)) {
    return 0;
  }
  if (value < 0) {
    return 0;
  }
  if (value > 1) {
    return 1;
  }
  return value;
}

/**
 * Build a {@link Signal} with clamped scores and a default timestamp.
 */
export function createSignal(input: {
  name: string;
  value: unknown;
  normalizedValue: unknown;
  quality: number;
  stability: number;
  entropyCategory: EntropyCategory;
  collectedAt?: number;
}): Signal {
  return {
    name: input.name,
    value: input.value,
    normalizedValue: input.normalizedValue,
    quality: clamp01(input.quality),
    stability: clamp01(input.stability),
    entropyCategory: input.entropyCategory,
    collectedAt: input.collectedAt ?? Date.now(),
  };
}

/**
 * Reject when `signal` is already aborted or becomes aborted.
 */
export function rejectedWhenAborted(signal: AbortSignal): Promise<never> {
  return new Promise((_resolve, reject) => {
    const abort = (): void => {
      reject(new DOMException('The operation was aborted.', 'AbortError'));
    };
    if (signal.aborted) {
      abort();
      return;
    }
    signal.addEventListener('abort', abort, { once: true });
  });
}

/**
 * Race a collector promise against the shared abort signal.
 */
export function raceAbort<T>(promise: Promise<T>, signal: AbortSignal): Promise<T> {
  return Promise.race([promise, rejectedWhenAborted(signal)]);
}

/**
 * Throw when the collector context has already been cancelled.
 */
export function throwIfAborted(ctx: CollectorContext): void {
  if (ctx.abortSignal.aborted) {
    throw new DOMException('The operation was aborted.', 'AbortError');
  }
}
