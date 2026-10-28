import { test, expect } from '@playwright/test';
import { collectDevice, openCase, signIn } from './helpers';

/** REQ-DEMO-013 — Device Intelligence demo happy path (collect → device context → checkout risk → trust). */
test.describe('Device Intelligence demo', () => {
  test('browser collect() resolves a device with risk and confidence', async ({ page }) => {
    const response = await page.goto('/en');
    expect(response?.ok()).toBeTruthy();
    await collectDevice(page);
    for (const label of ['Risk', 'Confidence', 'Trusted']) {
      await expect(page.getByText(label, { exact: true }).first()).toBeVisible();
    }
    await expect(page.locator('#collect-result')).not.toContainText('Waiting for collect()');
  });

  test('checkout applies the risk decision', async ({ page }) => {
    await collectDevice(page);
    await openCase(page, 'Checkout');
    await page.getByRole('button', { name: 'Pay €42' }).click();
    await expect(page.locator('.alert-success, .alert-warning, .alert-danger').first()).toBeVisible();
  });

  test('signed-in user can trust the current browser', async ({ page }) => {
    await collectDevice(page);
    await signIn(page);
    await openCase(page, 'Trust');
    await page.getByRole('button', { name: 'Trust this browser' }).click();
    await expect(page.locator('.alert-success')).toContainText('now trusted for 90 days');
  });
});
