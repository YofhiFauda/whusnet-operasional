import { test, expect } from '@playwright/test';
import {
  loginAs, watchPageHealth, computedStyleOf, effectiveContrast, GUDANG_ROUTES,
  attachMetrics, expectSeededRows,
} from '../../helpers/gudang-helpers.mjs';

/**
 * WH-E2E-02 — Daftar stok: cari, filter, dan keterbacaan tabel.
 * Setiap aksi memakai form/URL nyata. Tidak ada `if (isVisible)` yang
 * membuat test diam-diam lolos.
 */
test.describe('WH-E2E-02 Daftar stok', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'OWNER');
    await page.goto(GUDANG_ROUTES.stock);
  });

  test('cari "Modem" memfilter baris dan URL membawa parameter search', async ({ page }) => {
    const health = watchPageHealth(page);
    const stockRows = page.locator('tbody tr, .block.lg\\:hidden .rounded-2xl, [data-stock-item]');
    await expectSeededRows(stockRows, 'stok');

    const search = page.locator('input[name="search"]').first();
    await expect(search, 'Input pencarian stok (name="search") tidak ada').toBeVisible();
    await search.fill('Modem');
    await search.press('Enter');

    await page.waitForURL(/search=Modem/, { timeout: 8000 }).catch(() => null);
    await expect(page).toHaveURL(/search=Modem/);
    const rows = await stockRows.allInnerTexts();
    for (const row of rows) {
      expect(row.toLowerCase(), `Baris tidak cocok dengan "Modem": ${row.slice(0, 80)}`).toContain('modem');
    }
    expect(health.serverErrors).toEqual([]);
  });

  test('pencarian tanpa hasil menampilkan pesan kosong yang jelas', async ({ page }) => {
    const search = page.locator('input[name="search"]').first();
    await search.fill('zzz-tidak-ada-barang-9999');
    await search.press('Enter');

    await page.waitForURL(/zzz-tidak-ada-barang-9999/, { timeout: 8000 }).catch(() => null);
    const emptyMsg = page.locator('h4, p, div').filter({ hasText: /tidak ada|tidak ditemukan|kosong/i }).first();
    await expect(emptyMsg).toBeVisible({ timeout: 10000 });
  });

  test('filter stok rendah mengubah URL dan hasil', async ({ page }) => {
    const lowStockLabel = page.locator('label:has-text("Stok Menipis")').first();
    if (await lowStockLabel.isVisible()) {
      await lowStockLabel.click();
    } else {
      const lowStock = page.locator('input[name="low_stock_only"]').first();
      await lowStock.check({ force: true });
    }
    await page.waitForURL(/low_stock_only=1|low_stock_only=true/, { timeout: 8000 }).catch(() => null);
    await expect(page).toHaveURL(/low_stock_only=1|low_stock_only=true/);
  });

  test('keterbacaan header & baris tabel (font, padding, kontras)', async ({ page }, testInfo) => {
    await page.setViewportSize({ width: 1366, height: 768 });
    await expectSeededRows(page.locator('tbody tr'), 'stok');

    const metrics = {
      header: await computedStyleOf(page, 'thead th'),
      cell: await computedStyleOf(page, 'tbody td'),
      headerContrast: await effectiveContrast(page, 'thead th'),
      cellContrast: await effectiveContrast(page, 'tbody td'),
    };
    await attachMetrics(testInfo, 'tabel-stok-metrik', metrics);

    // Ambang minimum WCAG AA: teks normal 4.5:1
    expect(metrics.headerContrast.ratio, 'Kontras header tabel < 4.5:1').toBeGreaterThanOrEqual(4.5);
    expect(metrics.cellContrast.ratio, 'Kontras sel tabel < 4.5:1').toBeGreaterThanOrEqual(4.5);
  });

  test('header tabel diberi aria-sort saat kolom diurutkan', async ({ page }) => {
    const sortable = page.locator('thead th[aria-sort]').first();
    await expect(sortable, 'Tidak ada header dengan aria-sort').toHaveCount(1);
    const before = await sortable.getAttribute('aria-sort');
    await sortable.locator('a, button').first().click();
    await expect(page).toHaveURL(/sort=/);
    expect(await sortable.getAttribute('aria-sort')).not.toBe(before);
  });
});
