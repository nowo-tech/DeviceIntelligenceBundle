import type { CollectPayload, ServerCollectResponse } from '../types/index';

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
    const response = await fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
      },
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
