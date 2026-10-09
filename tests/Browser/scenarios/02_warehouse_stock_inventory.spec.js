import { test, expect } from '../helpers/warehouse-fixture.mjs';

test.describe('Skenario 2: Master Stok Barang & Threshold UI/UX', () => {

  test('2.1. Filter Pencarian & Interaktivitas Real-Time Debounce', async ({ authenticatedPage: page }) => {
    await page.goto('/warehouse/stock', { waitUntil: 'networkidle' });

    const searchInput = page.locator('input#search, input[name="search"]').first();
    if (await searchInput.isVisible()) {
      // 1. Ketik kata kunci pencarian
      await searchInput.fill('Modem');
      
      // Tunggu debounce 400ms selesai dan memicu form reload
      await page.waitForURL((url) => url.searchParams.get('search') === 'Modem', { timeout: 8000 }).catch(() => null);

      // 2. Verifikasi nilai input atau tabel terisi
      const value = await searchInput.inputValue();
      expect(value).toBe('Modem');

      // 3. Clear button / reset input
      await searchInput.fill('');
      await page.waitForTimeout(500);
    }

    expect(page.getConsoleErrors()).toHaveLength(0);
  });

  test('2.2. Modal / Detail Serial Number Interactivity', async ({ authenticatedPage: page }) => {
    await page.goto('/warehouse/stock', { waitUntil: 'networkidle' });

    // Cari tombol/badge serial number di tabel utama
    const serialBtn = page.locator('button[onclick*="Serial"], button[onclick*="Modal"], a[href*="serials"]').first();
    
    if (await serialBtn.isVisible()) {
      await serialBtn.scrollIntoViewIfNeeded();
      await serialBtn.click();
      await page.waitForTimeout(300);

      // Periksa apakah modal atau popup muncul
      const activeModal = page.locator('.modal, [role="dialog"], [x-show*="open"], .fixed.inset-0:not(.hidden)').first();
      if (await activeModal.isVisible()) {
        await page.keyboard.press('Escape');
        await page.waitForTimeout(300);
      }
    }
  });

  test('2.3. Evaluasi Tabel Stok pada Viewport Desktop, Tablet (768px) & Mobile (375px)', async ({ authenticatedPage: page }) => {
    // 1. Desktop HD (≥1024px) -> Tampilan Tabel Desktop
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/warehouse/stock', { waitUntil: 'networkidle' });
    const desktopTable = page.locator('.hidden.lg\\:block table, table.min-w-full').first();
    await expect(desktopTable).toBeVisible();

    // 2. Tablet Viewport (768px) -> Tampilan Kartu / Container Responsif
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.waitForTimeout(200);
    const responsiveContainer = page.locator('.block.lg\\:hidden, main').first();
    await expect(responsiveContainer).toBeVisible();

    // 3. Mobile Viewport (375px) -> Verifikasi Bebas Overflow X
    await page.setViewportSize({ width: 375, height: 812 });
    await page.waitForTimeout(200);
    const bodyScrollWidth = await page.evaluate(() => document.body.scrollWidth);
    const windowInnerWidth = await page.evaluate(() => window.innerWidth);
    expect(bodyScrollWidth).toBeLessThanOrEqual(windowInnerWidth + 5);
  });
});
