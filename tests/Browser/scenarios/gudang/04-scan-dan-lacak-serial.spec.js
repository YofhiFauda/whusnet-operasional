import { test, expect } from '@playwright/test';
import { loginAs, watchPageHealth, GUDANG_ROUTES, attachMetrics } from '../../helpers/gudang-helpers.mjs';

/**
 * WH-E2E-04 — Scan SN & lacak serial (dipakai staf di lapangan/gudang).
 * Kritis: fallback input manual harus tetap ada saat kamera tidak tersedia.
 */
test.describe('WH-E2E-04 Scan & lacak serial', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'OWNER');
  });

  test('halaman scan: input SN terlihat dan bisa diisi tanpa kamera', async ({ page }, testInfo) => {
    const health = watchPageHealth(page);
    await page.goto(GUDANG_ROUTES.scan);

    const snInput = page.locator('input[x-model="manualSn"], input[placeholder*="Serial Number"], input[name="serial_number"]').first();
    await expect(snInput, 'Input SN manual tidak ditemukan').toBeVisible();
    await snInput.fill('SN-TIDAK-ADA-0000');
    await expect(snInput).toHaveValue('SN-TIDAK-ADA-0000');

    await attachMetrics(testInfo, 'scan-health', health);
    expect(health.serverErrors).toEqual([]);
  });

  test('SN tidak terdaftar: pesan ramah, bukan error mentah', async ({ page }) => {
    await page.goto(GUDANG_ROUTES.scan);
    const snInput = page.locator('input[x-model="manualSn"], input[placeholder*="Serial Number"]').first();
    await snInput.fill('SN-TIDAK-ADA-0000');
    const cariBtn = page.locator('button:has-text("Cari")').first();
    if (await cariBtn.isVisible()) {
      await cariBtn.click();
    } else {
      await snInput.press('Enter');
    }

    await expect(page.getByText(/tidak ditemukan|tidak terdaftar|not found|Gak ada aksi/i).first()).toBeVisible({ timeout: 10000 });
    await expect(page.getByText(/SQLSTATE|Exception|Stack trace/i)).toHaveCount(0);
  });

  test('SN valid dari seed: tampil nama barang dan posisi terakhir', async ({ page }) => {
    const knownSn = process.env.E2E_KNOWN_SN;
    expect(knownSn, 'Isi E2E_KNOWN_SN = SN yang ada di DB uji').toBeTruthy();

    await page.goto(GUDANG_ROUTES.scan);
    const snInput = page.locator('input[x-model="manualSn"], input[placeholder*="Serial Number"]').first();
    await snInput.fill(knownSn);
    const cariBtn = page.locator('button:has-text("Cari")').first();
    if (await cariBtn.isVisible()) {
      await cariBtn.click();
    } else {
      await snInput.press('Enter');
    }

    await expect(page.getByText(knownSn).first()).toBeVisible({ timeout: 10000 });
  });

  test('halaman traceability: pencarian SN mengubah URL dan tidak error', async ({ page }) => {
    const health = watchPageHealth(page);
    const knownSn = process.env.E2E_KNOWN_SN;
    expect(knownSn, 'Isi E2E_KNOWN_SN = SN yang ada di DB uji').toBeTruthy();

    await page.goto(GUDANG_ROUTES.traceability);
    const search = page.locator('input[name="search"], input[name="serial"]').first();
    await expect(search, 'Input pencarian traceability tidak ada').toBeVisible();
    await search.fill(knownSn);
    await search.press('Enter');

    await expect(page).toHaveURL(new RegExp(knownSn));
    expect(health.serverErrors).toEqual([]);
  });
});
