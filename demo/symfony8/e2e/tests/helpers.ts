import { expect, type Page } from '@playwright/test';

/**
 * Loads a demo page, waits for the browser collector to POST /_device/collect,
 * then reloads so the server resolves the DeviceContext for this browser.
 */
export async function collectDevice(page: Page, path = '/en'): Promise<void> {
  await page.goto(path);
  await expect(page.locator('#collect-result')).not.toContainText('Waiting for collect()', { timeout: 20_000 });
  // The demo reloads itself once after collect(): reload until the observation cookie reaches the HTML request.
  await expect(async () => {
    await page.reload();
    await expect(page.getByText('Device ID', { exact: true }).first()).toBeVisible({ timeout: 3_000 });
  }).toPass({ timeout: 20_000 });
}

/** Signs in with an in-memory demo user (alice / vip, password "password"). */
export async function signIn(page: Page, user = 'alice'): Promise<void> {
  await page.goto('/en/login');
  await page.getByLabel('Username').fill(user);
  await page.getByLabel('Password').fill('password');
  await page.locator('main, body').getByRole('button', { name: 'Sign in' }).click();
  await expect(page.locator('main').getByText(user, { exact: false }).first()).toBeVisible();
}

/** Opens one of the demo cases from the shell's aside nav (the examples section). */
export async function openCase(page: Page, name: string): Promise<void> {
  const link = page.locator('[data-nowo-ui-aside]').getByRole('link', { name, exact: true });
  await link.click();
  await expect(link).toHaveAttribute('aria-current', 'page');
}

/** README screenshots never show the Symfony debug toolbar (it is not part of the demo platform): hide it. */
export async function waitForToolbar(page: Page): Promise<void> {
  await page.addStyleTag({ content: '.sf-toolbar,.sf-minitoolbar,[id^="sfwdt"]{display:none!important}' });
  await page.waitForTimeout(300);
}
