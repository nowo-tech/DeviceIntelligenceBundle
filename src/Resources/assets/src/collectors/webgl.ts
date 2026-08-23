import { compactDigest } from '../crypto/digest';
import { normalizeGpuFamily } from '../normalization/normalize';
import type { Collector, CollectorContext, Signal } from '../types/index';
import { createSignal, throwIfAborted } from './collector';

export interface WebGlValue {
  vendor: string | null;
  renderer: string | null;
  extensionsHash: string;
  limits: {
    maxTextureSize: number | null;
    maxViewportDims: number[] | null;
    maxRenderbufferSize: number | null;
    maxVertexAttribs: number | null;
    maxFragmentUniformVectors: number | null;
  };
}

/**
 * WebGL vendor/renderer (when unmasked), hashed extension set, and a few limits.
 * Does not assume `WEBGL_debug_renderer_info` is present.
 */
export class WebGlCollector implements Collector {
  readonly name = 'webgl';

  async collect(ctx: CollectorContext): Promise<Signal> {
    throwIfAborted(ctx);

    if (typeof document === 'undefined') {
      throw new Error('document is not available');
    }

    const canvas = document.createElement('canvas');
    canvas.width = 1;
    canvas.height = 1;

    const gl = getWebGlContext(canvas);
    if (!gl) {
      throw new Error('WebGL is not available');
    }

    try {
      const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
      const vendor = readParameter(
        gl,
        debugInfo ? debugInfo.UNMASKED_VENDOR_WEBGL : gl.VENDOR,
      );
      const renderer = readParameter(
        gl,
        debugInfo ? debugInfo.UNMASKED_RENDERER_WEBGL : gl.RENDERER,
      );

      const extensions = gl.getSupportedExtensions() ?? [];
      const extensionsHash = await compactDigest([...extensions].sort().join(','));

      const maxViewport = gl.getParameter(gl.MAX_VIEWPORT_DIMS);
      const limits = {
        maxTextureSize: readNumeric(gl, gl.MAX_TEXTURE_SIZE),
        maxViewportDims: Array.isArray(maxViewport)
          ? [Number(maxViewport[0]), Number(maxViewport[1])]
          : maxViewport instanceof Int32Array
            ? [maxViewport[0] ?? 0, maxViewport[1] ?? 0]
            : null,
        maxRenderbufferSize: readNumeric(gl, gl.MAX_RENDERBUFFER_SIZE),
        maxVertexAttribs: readNumeric(gl, gl.MAX_VERTEX_ATTRIBS),
        maxFragmentUniformVectors: readNumeric(gl, gl.MAX_FRAGMENT_UNIFORM_VECTORS),
      };

      const value: WebGlValue = {
        vendor,
        renderer,
        extensionsHash,
        limits,
      };

      const hasRenderer = typeof renderer === 'string' && renderer.length > 0;
      const quality = hasRenderer && debugInfo ? 0.88 : hasRenderer ? 0.7 : 0.45;

      return createSignal({
        name: this.name,
        value,
        normalizedValue: {
          gpuFamily: normalizeGpuFamily(vendor ?? '', renderer ?? ''),
          extensionsHash,
          maxTextureSize: limits.maxTextureSize,
        },
        quality,
        stability: 0.95,
        entropyCategory: 'high',
      });
    } finally {
      const lose = gl.getExtension('WEBGL_lose_context');
      lose?.loseContext();
    }
  }
}

function getWebGlContext(canvas: HTMLCanvasElement): WebGLRenderingContext | null {
  const options: WebGLContextAttributes = {
    alpha: true,
    antialias: false,
    depth: false,
    failIfMajorPerformanceCaveat: false,
    preserveDrawingBuffer: false,
  };

  const standard = canvas.getContext('webgl', options);
  if (standard) {
    return standard;
  }

  return canvas.getContext('experimental-webgl', options) as WebGLRenderingContext | null;
}

function readParameter(gl: WebGLRenderingContext, pname: number): string | null {
  try {
    const value = gl.getParameter(pname);
    return typeof value === 'string' && value.length > 0 ? value : null;
  } catch {
    return null;
  }
}

function readNumeric(gl: WebGLRenderingContext, pname: number): number | null {
  try {
    const value = gl.getParameter(pname);
    return typeof value === 'number' && Number.isFinite(value) ? value : null;
  } catch {
    return null;
  }
}
