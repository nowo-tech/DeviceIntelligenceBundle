/**
 * Process-local TTL cache. Not shared across tabs or origins.
 */

interface CacheEntry<T> {
  value: T;
  expiresAt: number;
}

/**
 * In-memory cache with a per-entry TTL expressed in seconds.
 */
export class MemoryCache<T> {
  private entry: CacheEntry<T> | null = null;

  /**
   * Return the cached value when present and unexpired; otherwise `null`.
   */
  get(): T | null {
    if (this.entry === null) {
      return null;
    }
    if (Date.now() >= this.entry.expiresAt) {
      this.entry = null;
      return null;
    }
    return this.entry.value;
  }

  /**
   * Store `value` for `ttlSeconds`. A non-positive TTL is treated as already expired.
   */
  set(value: T, ttlSeconds: number): void {
    const ttlMs = ttlSeconds * 1000;
    this.entry = {
      value,
      expiresAt: Date.now() + ttlMs,
    };
    if (ttlMs <= 0) {
      this.entry = null;
    }
  }

  /** Drop the cached value immediately. */
  clear(): void {
    this.entry = null;
  }

  /** Whether a non-expired value is stored. */
  has(): boolean {
    return this.get() !== null;
  }
}
