/**
 * Demo app entry (Pentatrion Vite + TypeScript).
 * Compiles the bundle Device Intelligence client from mounted sources.
 */
import { DeviceIntelligence } from '@bundle/src/index.ts';
import type { CollectResult } from '@bundle/src/types/index.ts';

const RELOAD_KEY = 'nowo.device-intelligence.demo-reloaded';

function publicResult(result: CollectResult): Record<string, unknown> {
  return {
    ok: result.ok,
    degraded: result.degraded,
    new: result.new,
    deviceId: result.deviceId,
    confidence: result.confidence,
    risk: result.risk,
  };
}

const el = document.getElementById('collect-result');
if (el instanceof HTMLElement) {
  const device = new DeviceIntelligence({
    endpoint: '/_device/collect',
    timeout: 8000,
    cache: { enabled: false },
  });
  device.collect().then((result) => {
    const summary = publicResult(result);
    el.textContent = JSON.stringify(summary, null, 2);
    if (!result.ok) {
      return;
    }
    if (sessionStorage.getItem(RELOAD_KEY) === '1') {
      return;
    }
    sessionStorage.setItem(RELOAD_KEY, '1');
    el.textContent = JSON.stringify({ ...summary, reloading: true }, null, 2);
    globalThis.location.reload();
  });
}
