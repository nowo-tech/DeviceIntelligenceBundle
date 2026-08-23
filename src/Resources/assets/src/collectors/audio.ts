import { compactDigest } from '../crypto/digest';
import type { Collector, CollectorContext, Signal } from '../types/index';
import { createSignal, raceAbort, throwIfAborted } from './collector';

type OfflineAudioContextCtor = new (
  numberOfChannels: number,
  length: number,
  sampleRate: number,
) => OfflineAudioContext;

const SAMPLE_RATE = 44100;
const DURATION_SAMPLES = 44100;
const SNAPSHOT_BINS = 256;

/**
 * WebAudio fingerprint using an offline graph only.
 *
 * Uses OfflineAudioContext + OscillatorNode + DynamicsCompressorNode.
 * Never calls `getUserMedia` and never touches the microphone.
 */
export class AudioCollector implements Collector {
  readonly name = 'audio';

  async collect(ctx: CollectorContext): Promise<Signal> {
    throwIfAborted(ctx);

    const Ctor = resolveOfflineAudioContext();
    if (!Ctor) {
      throw new Error('OfflineAudioContext is not available');
    }

    const offline = new Ctor(1, DURATION_SAMPLES, SAMPLE_RATE);
    const oscillator = offline.createOscillator();
    oscillator.type = 'triangle';
    oscillator.frequency.value = 10000;

    const compressor = offline.createDynamicsCompressor();
    compressor.threshold.value = -50;
    compressor.knee.value = 40;
    compressor.ratio.value = 12;
    compressor.attack.value = 0;
    compressor.release.value = 0.25;

    oscillator.connect(compressor);
    compressor.connect(offline.destination);
    oscillator.start(0);

    const buffer = await raceAbort(offline.startRendering(), ctx.abortSignal);
    const channel = buffer.getChannelData(0);
    const snapshot = downsample(channel, SNAPSHOT_BINS);
    const digest = await compactDigest(snapshot.join(','));

    return createSignal({
      name: this.name,
      value: digest,
      normalizedValue: digest,
      quality: 0.85,
      stability: 0.9,
      entropyCategory: 'high',
    });
  }
}

function resolveOfflineAudioContext(): OfflineAudioContextCtor | null {
  const root = globalThis as typeof globalThis & {
    OfflineAudioContext?: OfflineAudioContextCtor;
    webkitOfflineAudioContext?: OfflineAudioContextCtor;
  };
  return root.OfflineAudioContext ?? root.webkitOfflineAudioContext ?? null;
}

function downsample(channel: Float32Array, bins: number): number[] {
  const step = Math.max(1, Math.floor(channel.length / bins));
  const values: number[] = [];
  for (let i = 0; i < channel.length && values.length < bins; i += step) {
    const sample = channel[i] ?? 0;
    values.push(Math.round(sample * 1_000_000));
  }
  return values;
}
