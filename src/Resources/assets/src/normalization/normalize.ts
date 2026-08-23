/**
 * Client-side normalizers. Output is family + major, never a full version string.
 */

export type PlatformFamily = 'windows' | 'macos' | 'linux' | 'ios' | 'android' | 'other';

export type ScreenClass = 'mobile-s' | 'mobile-l' | 'tablet' | 'hd' | 'qhd' | 'uhd' | 'other';

export type GpuFamily = 'apple' | 'intel' | 'nvidia' | 'amd' | 'mali' | 'adreno' | 'other';

/**
 * Normalize a user-agent or "Family x.y.z" label to `Family <major>`.
 *
 * @example
 * normalizeBrowser('Chrome 143.0.7312.58') // 'Chrome 143'
 * normalizeBrowser('... Chrome/143.0.7312.58 Safari/537.36') // 'Chrome 143'
 */
export function normalizeBrowser(input: string): string {
  const text = input.trim();
  if (text.length === 0) {
    return 'other';
  }

  if (/Edg(?:e|A|iOS)?\/|\bEdge\//i.test(text) || /^Edge\s/i.test(text)) {
    return labeledFamily(text, 'Edge', /(?:Edg(?:e|A|iOS)?|Edge)[/\s](\d+)/i);
  }

  if (/\bOPR\/|\bOpera\//i.test(text) || /^Opera\s/i.test(text)) {
    return labeledFamily(text, 'Opera', /(?:OPR|Opera)[/\s](\d+)/i);
  }

  if (/\bFirefox\/|\bFxiOS\//i.test(text) || /^Firefox\s/i.test(text)) {
    return labeledFamily(text, 'Firefox', /(?:Firefox|FxiOS)[/\s](\d+)/i);
  }

  if (/^Chrome\s+\d+/i.test(text)) {
    return labeledFamily(text, 'Chrome', /^Chrome\s+(\d+)/i);
  }

  const isSafari =
    /\bSafari\//i.test(text) &&
    /\bVersion\//i.test(text) &&
    !/\b(Chrome|Chromium|CriOS|Edg|OPR)\b/i.test(text);
  if (isSafari || /^Safari\s/i.test(text)) {
    return labeledFamily(text, 'Safari', /(?:Version|Safari)[/\s](\d+)/i);
  }

  if (/\b(Chrome|Chromium|CriOS)\//i.test(text)) {
    return labeledFamily(text, 'Chrome', /(?:Chrome|Chromium|CriOS)\/(\d+)/i);
  }

  return 'other';
}

function labeledFamily(text: string, family: string, majorPattern: RegExp): string {
  const match = text.match(majorPattern);
  const major = match?.[1];
  return major ? `${family} ${major}` : family;
}

/**
 * Map a platform / oscpu / UA-CH platform string to a lowercase family.
 */
export function normalizePlatform(input: string): PlatformFamily {
  const value = input.toLowerCase();
  if (value.includes('win')) {
    return 'windows';
  }
  if (value.includes('android')) {
    return 'android';
  }
  if (
    value.includes('iphone') ||
    value.includes('ipad') ||
    value.includes('ipod') ||
    value.includes('ios')
  ) {
    return 'ios';
  }
  if (value.includes('mac') || value.includes('darwin')) {
    return 'macos';
  }
  if (value.includes('linux') || value.includes('x11') || value.includes('cros')) {
    return 'linux';
  }
  return 'other';
}

/**
 * Classify a viewport/screen by its largest dimension in CSS pixels.
 *
 * - `mobile-s` ≤ 480
 * - `mobile-l` ≤ 767
 * - `tablet` ≤ 1280
 * - `hd` ≤ 1920
 * - `qhd` ≤ 2560
 * - `uhd` ≤ 3840
 * - `other` otherwise (including non-finite values)
 */
export function normalizeScreenClass(width: number, height: number): ScreenClass {
  const max = Math.max(width, height);
  if (!Number.isFinite(max) || max <= 0) {
    return 'other';
  }
  if (max <= 480) {
    return 'mobile-s';
  }
  if (max <= 767) {
    return 'mobile-l';
  }
  if (max <= 1280) {
    return 'tablet';
  }
  if (max <= 1920) {
    return 'hd';
  }
  if (max <= 2560) {
    return 'qhd';
  }
  if (max <= 3840) {
    return 'uhd';
  }
  return 'other';
}

/**
 * Coarse GPU vendor family from WebGL vendor/renderer strings.
 */
export function normalizeGpuFamily(vendor: string, renderer: string): GpuFamily {
  const text = `${vendor} ${renderer}`.toLowerCase();
  if (text.includes('apple') || text.includes('metal')) {
    return 'apple';
  }
  if (text.includes('nvidia') || text.includes('geforce') || text.includes('quadro')) {
    return 'nvidia';
  }
  if (text.includes('amd') || text.includes('radeon') || text.includes('ati')) {
    return 'amd';
  }
  if (text.includes('intel')) {
    return 'intel';
  }
  if (text.includes('mali')) {
    return 'mali';
  }
  if (text.includes('adreno')) {
    return 'adreno';
  }
  return 'other';
}
