import { compactDigest } from '../crypto/digest';
import type { Collector, CollectorContext, Signal } from '../types/index';
import { createSignal, throwIfAborted } from './collector';

const WIDTH = 280;
const HEIGHT = 80;

/**
 * Deterministic 2D canvas scene. Pixels are digested locally; the image
 * is never sent to the server.
 */
export class CanvasCollector implements Collector {
  readonly name = 'canvas';

  async collect(ctx: CollectorContext): Promise<Signal> {
    throwIfAborted(ctx);

    if (typeof document === 'undefined') {
      throw new Error('document is not available');
    }

    const canvas = document.createElement('canvas');
    canvas.width = WIDTH;
    canvas.height = HEIGHT;
    canvas.style.display = 'none';

    const context = canvas.getContext('2d', { willReadFrequently: true });
    if (!context) {
      throw new Error('CanvasRenderingContext2D is not available');
    }

    paintScene(context);

    const image = context.getImageData(0, 0, WIDTH, HEIGHT);
    const digest = await compactDigest(image.data);

    return createSignal({
      name: this.name,
      value: digest,
      normalizedValue: digest,
      quality: 0.92,
      stability: 0.95,
      entropyCategory: 'high',
    });
  }
}

/**
 * Fixed geometry: rectangles, the label "DeviceIntelligence", and arcs.
 */
function paintScene(ctx: CanvasRenderingContext2D): void {
  ctx.fillStyle = '#f60';
  ctx.fillRect(8, 8, 90, 48);

  ctx.fillStyle = '#069';
  ctx.fillRect(108, 12, 70, 40);

  ctx.strokeStyle = '#111827';
  ctx.lineWidth = 2;
  ctx.beginPath();
  ctx.arc(220, 36, 26, 0, Math.PI * 2);
  ctx.stroke();

  ctx.beginPath();
  ctx.arc(248, 52, 14, 0.25 * Math.PI, 1.6 * Math.PI);
  ctx.stroke();

  ctx.fillStyle = '#111827';
  ctx.font = '16px Arial, sans-serif';
  ctx.textBaseline = 'top';
  ctx.fillText('DeviceIntelligence', 12, 58);

  ctx.fillStyle = 'rgba(16, 185, 129, 0.45)';
  ctx.fillRect(40, 20, 36, 18);
}
