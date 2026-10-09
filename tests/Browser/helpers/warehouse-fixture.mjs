import { test as base, expect } from '@playwright/test';
import { runA11yScan } from './a11y-helper.mjs';

/**
 * Extended Playwright Test Fixture with auto-login, console error monitor,
 * and UI/UX performance telemetry.
 */
export const test = base.extend({
  authenticatedPage: async ({ page }, use) => {
    const consoleErrors = [];
    const pageErrors = [];

    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });

    page.on('pageerror', (err) => {
      pageErrors.push(err.message);
    });

    // 1. Authenticate with Superadmin / Owner credentials
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    if (page.url().includes('/login')) {
      await page.fill('input[name="email"]', 'owner1@whusnet.com');
      await page.fill('input[name="password"]', 'password');
      await page.click('button[type="submit"]');
      await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 15000 });
    }

    // Attach telemetry collectors to page object
    page.getConsoleErrors = () => consoleErrors;
    page.getPageErrors = () => pageErrors;
    page.runA11y = () => runA11yScan(page);

    await use(page);
  },
});

export { expect };
