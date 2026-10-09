import { test, expect } from '@playwright/test';
import {
  loginAs, watchPageHealth, horizontalOverflowPx, GUDANG_ROUTES, VIEWPORTS,
  seriousA11yViolations, attachMetrics, expectSeededRows,
} from '../../helpers/gudang-helpers.mjs';

/**
 * WH-E2E-01 — Akses per peran & navigasi gudang.
 * Tujuan: pastikan pengguna sampai ke halaman yang benar dalam jumlah klik
 * wajar, dan peran tanpa izin gudang tidak melihat data gudang.
 */
test.describe('WH-E2E-01 Akses & navigasi gudang', () => {
  test('owner: dashboard gudang tampil dengan judul, tab, dan tanpa error server', async ({ page }, testInfo) => {
    const health = watchPageHealth(page);
    await loginAs(page, 'OWNER');
    await page.goto(GUDANG_ROUTES.index);

    await expect(page.locator('h1').first()).toBeVisible();

    const navLinks = page.locator('nav a, a:has-text("Dashboard"), a[href*="warehouse"]');
    expect(await navLinks.count(), 'Navigasi gudang harus punya link').toBeGreaterThan(0);

    expect(health.serverErrors).toEqual([]);
    expect(health.pageErrors).toEqual([]);
    await attachMetrics(testInfo, 'health', health);
  });

  test('owner: dari dashboard ke Stok langsung lewat link navigasi', async ({ page }) => {
    await loginAs(page, 'OWNER');
    await page.goto(GUDANG_ROUTES.index);

    const stockLink = page.locator('a[href$="/warehouse/stock"], a:has-text("Kelola Stok"), a:has-text("Stok")').first();
    await expect(stockLink, 'Link "Stok" tidak ditemukan di navigasi gudang').toBeVisible();
    await stockLink.click();
    await expect(page).toHaveURL(/\/warehouse\/stock/);
  });

  // Butuh nama cabang lain yang ada di DB uji. Tanpa env ini test gagal, bukan lolos.
  test('pop_admin: baris stok tidak memuat cabang di luar scope', async ({ page }, testInfo) => {
    const otherPop = process.env.E2E_OTHER_POP_NAME;
    expect(otherPop, 'Isi E2E_OTHER_POP_NAME = nama cabang yang BUKAN scope pop_admin').toBeTruthy();

    await loginAs(page, 'POP_ADMIN');
    await page.goto(GUDANG_ROUTES.stock);

    const stockRows = page.locator('tbody tr, .block.lg\\:hidden .rounded-2xl, [data-stock-item]');
    await expectSeededRows(stockRows, 'stok');

    const rowTexts = await stockRows.allInnerTexts();
    const leaked = rowTexts.filter((t) => t.includes(otherPop));
    await attachMetrics(testInfo, 'pop-admin-scope', { totalRows: rowTexts.length, leakedRows: leaked.length });
    expect(leaked, `Baris cabang lain bocor ke pop_admin`).toHaveLength(0);
  });

  test('teknisi: tidak boleh membuka form transfer (izin gudang tidak ada)', async ({ page }) => {
    await loginAs(page, 'TEKNISI');
    const response = await page.goto(GUDANG_ROUTES.transferCreate);

    const blocked = response.status() === 403 || response.status() === 404 || !page.url().includes('/transfers/create');
    expect(blocked, `Teknisi mendapat ${response.status()} dan halaman ${page.url()}`).toBeTruthy();
  });

  for (const vp of VIEWPORTS) {
    test(`dashboard gudang tidak scroll horizontal di ${vp.name}`, async ({ page }, testInfo) => {
      await loginAs(page, 'OWNER');
      await page.setViewportSize({ width: vp.width, height: vp.height });
      await page.goto(GUDANG_ROUTES.index);

      const overflow = await horizontalOverflowPx(page);
      await attachMetrics(testInfo, `overflow-${vp.name}`, { overflowPx: overflow });
      expect(overflow, `Overflow ${overflow}px di ${vp.name}`).toBe(0);
    });
  }
});
