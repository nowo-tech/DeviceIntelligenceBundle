import { test, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { collectDevice, openCase, signIn, waitForToolbar } from './helpers';

/** REQ-DEMO-013 — README screenshots (captured as PNG, converted to WebP by scripts/to-webp.mjs). */
const outDir = process.env.SCREENSHOT_DIR
  ? resolve(process.env.SCREENSHOT_DIR)
  : resolve(__dirname, '../../../../docs/images/demo');

test.use({ viewport: { width: 1280, height: 920 } });

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

test.describe('Device Intelligence screenshots', () => {
  test('device-overview — collected device with risk, confidence and collect() payload', async ({ page }) => {
    await collectDevice(page);
    await expect(page.locator('#collect-result')).not.toContainText('Waiting for collect()', { timeout: 20_000 });
    await waitForToolbar(page);
    await page.screenshot({ path: resolve(outDir, 'device-overview.png') });
  });

  test('trusted-device — signed-in user trusts the current browser', async ({ page }) => {
    await collectDevice(page);
    await signIn(page);
    await openCase(page, 'Trust');
    await page.getByRole('button', { name: 'Trust this browser' }).click();
    await expect(page.locator('.alert-success')).toBeVisible();
    await expect(page.locator('#collect-result')).not.toContainText('Waiting for collect()', { timeout: 20_000 });
    await waitForToolbar(page);
    await page.screenshot({ path: resolve(outDir, 'trusted-device.png') });
  });
});
