import { test, expect } from '@playwright/test';
import { loginAs, watchPageHealth, GUDANG_ROUTES, attachMetrics } from '../../helpers/gudang-helpers.mjs';

/**
 * WH-E2E-03 — Form transfer barang: validasi, error state, dan keyboard.
 *
 * Bagian yang MENULIS data (submit valid) diberi gerbang env E2E_MUTATION=1.
 * Hanya jalan di DB sekali pakai (migrate:fresh --seed). Gerbang ini eksplisit
 * dan tercatat di laporan sebagai "skipped", bukan lolos diam-diam.
 */
test.describe('WH-E2E-03 Form transfer', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'OWNER');
  });

  test('submit kosong: tidak ada request POST, error tampil di dekat field', async ({ page }, testInfo) => {
    const health = watchPageHealth(page);
    await page.goto(GUDANG_ROUTES.transferCreate);

    let postCount = 0;
    page.on('request', (req) => {
      if (req.method() === 'POST' && req.url().includes('/warehouse/transfers')) postCount++;
    });

    const submitBtn = page.locator('button[type="submit"][form="transfer-form"], button[type="submit"]:has-text("Kirim"), button[type="submit"]:has-text("Simpan"), button[type="submit"]').first();
    await submitBtn.click();

    // Validasi harus mencegah POST sama sekali, bukan kirim lalu gagal di server
    await page.waitForTimeout(500);
    expect(postCount, 'Form kosong tetap terkirim ke server').toBe(0);

    const invalid = await page.locator('#transfer-form :invalid, #transfer-form [aria-invalid="true"]').count();
    const messages = await page.locator('#transfer-form [role="alert"], #transfer-form .text-rose-600, #transfer-form .text-rose-500').allInnerTexts();
    await attachMetrics(testInfo, 'submit-kosong', { invalidFields: invalid, messages });
    expect(invalid + messages.length, 'Tidak ada indikator error pada form kosong').toBeGreaterThan(0);
    expect(health.pageErrors).toEqual([]);
  });

  test('label POP asal & tujuan terhubung ke select (klik label memfokus select)', async ({ page }) => {
    await page.goto(GUDANG_ROUTES.transferCreate);

    for (const id of ['from_pop_id', 'to_pop_id']) {
      const label = page.locator(`label[for="${id}"]`);
      await expect(label, `Label untuk #${id} tidak ada`).toHaveCount(1);
      await label.click();
      const focused = await page.evaluate(() => document.activeElement?.id);
      expect(focused, `Klik label tidak memfokus #${id}`).toBe(id);
    }
  });

  test('keyboard: Tab dari POP asal menuju POP tujuan dengan focus-visible', async ({ page }) => {
    await page.goto(GUDANG_ROUTES.transferCreate);
    await page.locator('#from_pop_id').focus();
    await page.keyboard.press('Tab');
    const next = await page.evaluate(() => document.activeElement?.id);
    expect(next, 'Urutan Tab POP asal → POP tujuan tidak logis').toBe('to_pop_id');

    const outline = await page.evaluate(() => getComputedStyle(document.activeElement).outlineStyle);
    const ring = await page.evaluate(() => getComputedStyle(document.activeElement).boxShadow);
    expect(outline !== 'none' || ring !== 'none', 'Fokus POP tujuan tidak terlihat').toBeTruthy();
  });

  test('POP asal = POP tujuan ditolak sebelum kirim', async ({ page }) => {
    await page.goto(GUDANG_ROUTES.transferCreate);
    const from = page.locator('#from_pop_id');
    const to = page.locator('#to_pop_id');
    const firstValue = await from.locator('option:not([value=""])').first().getAttribute('value');
    await from.selectOption(firstValue);
    await to.selectOption(firstValue);

    let postCount = 0;
    page.on('request', (req) => {
      if (req.method() === 'POST' && req.url().includes('/warehouse/transfers')) postCount++;
    });
    await page.locator('button[type="submit"][form="transfer-form"]').filter({ visible: true }).first().click();
    await page.waitForTimeout(500);

    expect(postCount, 'POP sama tetap dikirim ke server').toBe(0);
  });

  test('MUTASI: transfer valid tersimpan lalu muncul di daftar dan surat jalan', async ({ page }) => {
    test.skip(process.env.E2E_MUTATION !== '1', 'Set E2E_MUTATION=1 hanya di DB sekali pakai');
    await page.goto(GUDANG_ROUTES.transferCreate);
    // Belum dibuat: perlu tahu dulu data stok tersedia di DB uji (POP, barang, SN).
    // Sengaja gagal supaya tidak terlihat hijau tanpa pernah menulis data.
    throw new Error('Alur mutasi belum dilengkapi: isi langkah pilih barang + SN sesuai DB uji');
  });
});
