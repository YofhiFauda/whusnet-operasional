import { test, expect } from '@playwright/test';
import { loginAs } from '../helpers/gudang-helpers.mjs';

test.use({ ignoreHTTPSErrors: true });

test('tab filter POP dan Wilayah tetap membuka dropdown sampai klik di luar', async ({ page }) => {
  await loginAs(page, 'OWNER');
  await page.goto('/customers', { waitUntil: 'networkidle' });

  for (const filter of [
    { trigger: 'Filter POP atau Cabang', tabs: ['Mini POP', 'POP / Cabang'] },
    { trigger: 'Filter Wilayah Kecamatan dan Desa', tabs: ['Desa', 'Kecamatan'] },
  ]) {
    const trigger = page.getByRole('button', { name: filter.trigger });
    await trigger.click();
    await expect(trigger).toHaveAttribute('aria-expanded', 'true');

    for (const tab of filter.tabs) {
      await page.getByRole('button', { name: tab, exact: true }).first().click();
      await expect(trigger).toHaveAttribute('aria-expanded', 'true');
    }

    await page.locator('h1').first().click();
    await expect(trigger).toHaveAttribute('aria-expanded', 'false');
  }
});
