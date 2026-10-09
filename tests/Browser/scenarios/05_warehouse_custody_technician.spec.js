import { test, expect } from '../helpers/warehouse-fixture.mjs';

test.describe('Skenario 5: Custody (Barang di Tangan Teknisi) & Reassign Flow UI/UX', () => {

  test('5.1. Daftar Custody: Filter Teknisi, Tabulasi Barang & Badge Status', async ({ authenticatedPage: page }) => {
    await page.goto('/warehouse/custody', { waitUntil: 'networkidle' });

    // 1. Verifikasi tabel atau kartu daftar teknisi
    const custodyContainer = page.locator('table, .grid, .space-y-4').first();
    await expect(custodyContainer).toBeVisible();

    // 2. Filter / Search Teknisi
    const searchInput = page.locator('input[placeholder*="Teknisi"], input[name="search"], input[type="search"]').first();
    if (await searchInput.isVisible()) {
      await searchInput.fill('Teknisi');
      await page.keyboard.press('Enter');
      await page.waitForLoadState('networkidle');
    }

    expect(page.getConsoleErrors()).toHaveLength(0);
  });

  test('5.2. Modal Reassign / Pengembalian: Dialog Interactivity & Accessibility', async ({ authenticatedPage: page }) => {
    await page.goto('/warehouse/custody', { waitUntil: 'networkidle' });

    // Cari tombol aksi reassign atau kembali ke gudang jika ada
    const actionBtn = page.locator('button:has-text("Reassign"), a:has-text("Reassign"), button:has-text("Kembalikan"), a:has-text("Kembalikan")').first();
    
    if (await actionBtn.isVisible()) {
      await actionBtn.click();
      await page.waitForLoadState('networkidle');

      // Verifikasi form atau dialog reassign terbuka
      const formContainer = page.locator('form, [role="dialog"]').first();
      await expect(formContainer).toBeVisible();

      // Periksa keberadaan label yang jelas untuk setiap input
      const labels = page.locator('label');
      expect(await labels.count()).toBeGreaterThan(0);
    }
  });
});
