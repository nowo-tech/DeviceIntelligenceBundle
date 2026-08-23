import { describe, expect, it } from 'vitest';
import {
  normalizeBrowser,
  normalizePlatform,
  normalizeScreenClass,
} from '../src/normalization/normalize';

describe('normalizeBrowser', () => {
  it('maps Chrome 143.0.7312.58 to Chrome 143', () => {
    expect(normalizeBrowser('Chrome 143.0.7312.58')).toBe('Chrome 143');
  });

  it('maps a full Chrome user-agent to family + major', () => {
    const ua =
      'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.7312.58 Safari/537.36';
    expect(normalizeBrowser(ua)).toBe('Chrome 143');
  });

  it('does not classify Edge as Chrome', () => {
    const ua =
      'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0';
    expect(normalizeBrowser(ua)).toBe('Edge 143');
  });
});

describe('normalizePlatform', () => {
  it('maps common platform strings to lowercase families', () => {
    expect(normalizePlatform('Win32')).toBe('windows');
    expect(normalizePlatform('MacIntel')).toBe('macos');
    expect(normalizePlatform('Linux x86_64')).toBe('linux');
    expect(normalizePlatform('iPhone')).toBe('ios');
    expect(normalizePlatform('Android')).toBe('android');
    expect(normalizePlatform('Haiku')).toBe('other');
  });
});

describe('normalizeScreenClass', () => {
  it('classifies by max dimension', () => {
    expect(normalizeScreenClass(320, 480)).toBe('mobile-s');
    expect(normalizeScreenClass(375, 667)).toBe('mobile-l');
    expect(normalizeScreenClass(768, 1024)).toBe('tablet');
    expect(normalizeScreenClass(1920, 1080)).toBe('hd');
    expect(normalizeScreenClass(2560, 1440)).toBe('qhd');
    expect(normalizeScreenClass(3840, 2160)).toBe('uhd');
    expect(normalizeScreenClass(7680, 4320)).toBe('other');
  });
});
