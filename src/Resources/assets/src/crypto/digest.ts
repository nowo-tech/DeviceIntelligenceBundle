/**
 * Compact WebCrypto digests. Never send raw high-entropy buffers.
 */

const HEX = 16;

/**
 * SHA-256 hex digest of a string or binary payload.
 *
 * @param data - UTF-8 string or bytes
 * @returns Full 64-character lowercase hex digest
 */
export async function sha256Hex(data: string | BufferSource): Promise<string> {
  const subtle = globalThis.crypto?.subtle;
  if (!subtle) {
    throw new Error('WebCrypto SubtleCrypto is not available');
  }

  const bytes = typeof data === 'string' ? new TextEncoder().encode(data) : data;
  const hash = await subtle.digest('SHA-256', bytes);
  const view = new Uint8Array(hash);
  let hex = '';
  for (let i = 0; i < view.length; i += 1) {
    hex += (view[i] ?? 0).toString(16).padStart(2, '0');
  }
  return hex;
}

/**
 * Truncated SHA-256 hex digest used as a compact fingerprint token.
 *
 * @param data - UTF-8 string or bytes
 * @param length - Number of hex characters to keep (default 16)
 */
export async function compactDigest(
  data: string | BufferSource,
  length: number = HEX,
): Promise<string> {
  const hex = await sha256Hex(data);
  return hex.slice(0, length);
}
