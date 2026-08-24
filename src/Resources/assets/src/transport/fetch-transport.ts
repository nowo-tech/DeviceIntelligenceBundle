import type { CollectPayload, ServerCollectResponse } from '../types/index';

const PREVIOUS_DEBUG_TOKEN_HEADER = 'X-Previous-Debug-Token';

/**
 * Token of the current HTML request's Web Debug Toolbar, if present.
 * Used so POST /_device/collect is stored as a child of that profiler profile.
 */
export function readProfilerPreviousToken(): string | undefined {
  if (typeof document === 'undefined') {
    return undefined;
  }
  const el = document.querySelector('[id^="sfwdt"]');
  if (!(el instanceof Element) || !el.id.startsWith('sfwdt')) {
    return undefined;
  }
  const token = el.id.slice('sfwdt'.length);
  return token.length > 0 ? token : undefined;
}

/**
 * POST a collect payload. Never throws; returns `null` on any failure.
 *
 * @param endpoint - Same-origin collect URL
 * @param payload - Protocol v1 JSON body
 * @param abortSignal - Optional cancellation
 */
export async function postCollect(
  endpoint: string,
  payload: CollectPayload,
  abortSignal?: AbortSignal,
): Promise<ServerCollectResponse | null> {
  try {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
    };
    const previousToken = readProfilerPreviousToken();
    if (previousToken !== undefined) {
      headers[PREVIOUS_DEBUG_TOKEN_HEADER] = previousToken;
    }

    const response = await fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers,
      body: JSON.stringify(payload),
      signal: abortSignal,
    });

    if (!response.ok) {
      return null;
    }

    const text = await response.text();
    if (text.trim().length === 0) {
      return { ok: true };
    }

    const parsed: unknown = JSON.parse(text);
    if (parsed === null || typeof parsed !== 'object') {
      return { ok: true };
    }

    return parsed as ServerCollectResponse;
  } catch {
    return null;
  }
}
