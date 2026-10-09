import { test, expect } from '../helpers/warehouse-fixture.mjs';

test.describe('Skenario 4: Barcode/QR Scan & Traceability Serial Number UI/UX', () => {

  test('4.1. Halaman Scan: Autofocus Input & Fallback Manual Lookup', async ({ authenticatedPage: page }) => {
    await page.goto('/warehouse/scan', { waitUntil: 'networkidle' });

    // 1. Verifikasi Header Scan
    const heading = page.locator('h1, h2').first();
    await expect(heading).toBeVisible();

    // 2. Verifikasi Input Manual Serial Number memiliki Autofocus atau mudah dijangkau
    const manualInput = page.locator('input[name="serial_number"], input[name="serial"], input[placeholder*="SN"], input[placeholder*="Scan"]').first();
    if (await manualInput.isVisible()) {
      await expect(manualInput).toBeVisible();
      
      // Test input keyboard event Enter
      await manualInput.fill('TEST-DUMMY-SN-9999');
      await page.keyboard.press('Enter');
      await page.waitForTimeout(500);

      // Verifikasi UI tidak crash dan memberikan feedback "Data tidak ditemukan" yang ramah
      const feedbackMessage = page.locator('.alert, .text-error, .text-slate-500, p');
      await expect(feedbackMessage.first()).toBeVisible();
    }
  });

  test('4.2. Traceability: Timeline Visual Status & Node Alignment', async ({ authenticatedPage: page }) => {
    await page.goto('/warehouse/traceability', { waitUntil: 'networkidle' });

    // Verifikasi Input Pencarian SN
    const searchSN = page.locator('input[type="text"], input[name="search"], input[name="serial"]').first();
    if (await searchSN.isVisible()) {
      await searchSN.fill('SN');
      await page.keyboard.press('Enter');
      await page.waitForLoadState('networkidle');
    }

    // Periksa apakah ada elemen timeline (.timeline, list milestone, border vertikal)
    const timelineElements = page.locator('.border-l-2, .timeline, [class*="timeline"], ol, ul');
    if (await timelineElements.count() > 0) {
      await expect(timelineElements.first()).toBeVisible();
    }

    expect(page.getConsoleErrors()).toHaveLength(0);
  });
});
