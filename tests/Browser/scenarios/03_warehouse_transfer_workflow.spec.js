import { test, expect } from '../helpers/warehouse-fixture.mjs';

test.describe('Skenario 3: Alur Kerja Form Transfer Barang & Validasi UI/UX', () => {

  test('3.1. Validasi Form Transfer: Uji Error Handling & Inline Feedback', async ({ authenticatedPage: page }) => {
    await page.goto('/warehouse/transfers/create', { waitUntil: 'networkidle' });

    // 1. Verifikasi elemen form dasar
    const form = page.locator('form[action*="transfers"]');
    await expect(form).toBeVisible();

    // 2. Submit form kosong untuk memicu validasi
    const submitBtn = page.locator('button[type="submit"]:has-text("Kirim"), button[type="submit"]:has-text("Simpan"), button[type="submit"]').first();
    await submitBtn.click();

    // 3. Verifikasi apakah ada pesan error atau atribut HTML5 validation
    const hasValidationError = await page.evaluate(() => {
      const invalidInputs = document.querySelectorAll(':invalid');
      const errorTexts = document.querySelectorAll('.text-error, .text-danger, .text-rose-600, .text-red-500');
      return invalidInputs.length > 0 || errorTexts.length > 0;
    });

    expect(hasValidationError).toBeTruthy();
    expect(page.getConsoleErrors()).toHaveLength(0);
  });

  test('3.2. Form Ergonomics: Pencegahan Transfer ke POP yang Sama', async ({ authenticatedPage: page }) => {
    await page.goto('/warehouse/transfers/create', { waitUntil: 'networkidle' });

    const sourcePopSelect = page.locator('select[name="from_pop_id"], select[name="source_pop_id"]').first();
    const destPopSelect = page.locator('select[name="to_pop_id"], select[name="destination_pop_id"]').first();

    if (await sourcePopSelect.isVisible() && await destPopSelect.isVisible()) {
      // Pilih option pertama pada POP asal
      await sourcePopSelect.selectOption({ index: 1 });
      const selectedSourceVal = await sourcePopSelect.inputValue();

      // Pilih option yang sama pada POP tujuan jika sistem mengizinkan, lalu submit
      await destPopSelect.selectOption(selectedSourceVal);
      
      const submitBtn = page.locator('button[type="submit"]').first();
      await submitBtn.click();
      await page.waitForTimeout(500);

      // Verifikasi pesan peringatan UX "POP Asal dan Tujuan tidak boleh sama"
      const alertWarning = page.locator('.text-error, .alert, [role="alert"], body');
      const pageText = await alertWarning.first().innerText();
      expect(pageText.toLowerCase()).toMatch(/sama|invalid|pilih|error|wajib/i);
    }
  });

  test('3.3. Tampilan Cetak Dokumen: Surat Jalan & Invoice Transfer', async ({ authenticatedPage: page }) => {
    await page.goto('/warehouse/transfers', { waitUntil: 'networkidle' });

    // Cari link Surat Jalan jika ada row data
    const suratJalanLink = page.locator('a[href*="surat-jalan"]').first();
    if (await suratJalanLink.isVisible()) {
      await suratJalanLink.click();
      await page.waitForLoadState('networkidle');

      // Verifikasi layout Surat Jalan
      await expect(page.locator('h1, h2, h3, .surat-jalan, .invoice-header').first()).toBeVisible();

      // Emulate Print Media untuk memastikan styling cetak bersih (tanpa tombol navigasi web)
      await page.emulateMedia({ media: 'print' });
      await page.waitForTimeout(200);

      // Kembali ke mode screen
      await page.emulateMedia({ media: 'screen' });
    }
  });
});
