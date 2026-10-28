import { expect, type Page } from '@playwright/test';

/**
 * Loads a demo page, waits for the browser collector to POST /_device/collect,
 * then reloads so the server resolves the DeviceContext for this browser.
 */
export async function collectDevice(page: Page, path = '/en'): Promise<void> {
  await page.goto(path);
  await expect(page.locator('#collect-result')).not.toContainText('Waiting for collect()', { timeout: 20_000 });
  await page.reload();
  await expect(page.getByText('Device ID', { exact: true }).first()).toBeVisible();
}

/** Signs in with an in-memory demo user (alice / vip, password "password"). */
export async function signIn(page: Page, user = 'alice'): Promise<void> {
  await page.goto('/en/login');
  await page.getByLabel('Username').fill(user);
  await page.getByLabel('Password').fill('password');
  await page.locator('main, body').getByRole('button', { name: 'Sign in' }).click();
  await expect(page.getByText(user, { exact: false }).first()).toBeVisible();
}

/** Opens one of the demo cases from the pills nav. */
export async function openCase(page: Page, name: string): Promise<void> {
  await page.locator('.nav-pills').getByRole('link', { name, exact: true }).click();
  await expect(page.locator('.nav-pills .nav-link.active')).toHaveText(name);
}

/** Waits for the Symfony WebProfiler toolbar so it is part of the capture. */
export async function waitForToolbar(page: Page): Promise<void> {
  await expect(page.locator('.sf-toolbar .sf-toolbarreset').first()).toBeVisible({ timeout: 15_000 });
  await page.waitForTimeout(300);
}
