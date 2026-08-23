import { afterEach, describe, expect, it, vi } from 'vitest';
import { AudioCollector } from '../src/collectors/audio';
import type { CollectorContext } from '../src/types/index';

function ctx(): CollectorContext {
  return {
    timeout: 1500,
    abortSignal: new AbortController().signal,
    consent: { highEntropy: true },
  };
}

describe('AudioCollector', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('never calls getUserMedia', async () => {
    const getUserMedia = vi.fn();
    Object.defineProperty(navigator, 'mediaDevices', {
      configurable: true,
      value: { getUserMedia },
    });

    const collector = new AudioCollector();
    try {
      await collector.collect(ctx());
    } catch {
      // OfflineAudioContext is often missing in jsdom; failure is fine.
    }

    expect(getUserMedia).not.toHaveBeenCalled();
  });

  it('does not reference navigator.mediaDevices during collect', async () => {
    const getUserMedia = vi.fn();
    const mediaDevices = { getUserMedia };
    Object.defineProperty(navigator, 'mediaDevices', {
      configurable: true,
      get() {
        return mediaDevices;
      },
    });
    const readSpy = vi.spyOn(navigator, 'mediaDevices', 'get');

    try {
      await new AudioCollector().collect(ctx());
    } catch {
      // Expected when WebAudio is unavailable.
    }

    expect(readSpy).not.toHaveBeenCalled();
    expect(getUserMedia).not.toHaveBeenCalled();
  });
});
