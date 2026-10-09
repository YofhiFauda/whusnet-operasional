import { test, expect } from '../helpers/warehouse-fixture.mjs';

test.describe('Skenario 1: Dashboard Gudang & Navigasi Terpadu UI/UX', () => {

  test('1.1. Verifikasi Hierarki Visual Header & Tab Navigasi Kategori (Desktop)', async ({ authenticatedPage: page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/warehouse', { waitUntil: 'networkidle' });

    // 1. Header & Title Accessibility
    const heading = page.locator('h1').first();
    await expect(heading).toBeVisible();
    await expect(heading).toContainText(/Gudang|Inventori|Dashboard/i);

    // 2. Tab Navigation Utama
    const tabs = ['Dashboard', 'Riwayat Mutasi', 'Kelola Stok', 'Barang di Tangan Teknisi', 'Lacak Barang / SN', 'Laporan'];
    for (const tabText of tabs) {
      const tabLink = page.locator(`main nav a:has-text("${tabText}"), a:has-text("${tabText}")`).first();
      await expect(tabLink).toBeVisible();
    }

    // 3. Quick Action Menu Dropdown Interactivity
    const quickActionBtn = page.locator('button:has-text("Aksi")').first();
    if (await quickActionBtn.isVisible()) {
      await quickActionBtn.click();
      const dropdownMenu = page.locator('div:has-text("Transfer Barang"), div:has-text("Terima Barang")').first();
      await expect(dropdownMenu).toBeVisible();
      
      // Close dropdown by clicking outside
      await page.mouse.click(10, 10);
      await page.waitForTimeout(200);
    }

    // 4. Zero severe console errors
    expect(page.getConsoleErrors()).toHaveLength(0);
  });

  test('1.2. Responsivitas & Ergonomi Menu Navigasi Mobile (375px)', async ({ authenticatedPage: page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto('/warehouse', { waitUntil: 'networkidle' });

    // Periksa apakah tab desktop disembunyikan dan navigasi mobile tampil
    const desktopHeader = page.locator('.hidden.lg\\:block').first();
    await expect(desktopHeader).toBeHidden();

    // Periksa Tombol Aksi Cepat / Dropdown Navigasi Mobile
    const mobileDropdownTrigger = page.locator('button:has-text("Pilih Menu"), button:has-text("Navigasi"), select[name="mobile_nav"]').first();
    if (await mobileDropdownTrigger.isVisible()) {
      const box = await mobileDropdownTrigger.boundingBox();
      // Verifikasi Minimum Touch Target Size (44x44px atau ergonomis)
      expect(box.height).toBeGreaterThanOrEqual(36);
    }

    // Periksa bahwa tidak ada horizontal viewport break (overflow X)
    const bodyScrollWidth = await page.evaluate(() => document.body.scrollWidth);
    const windowInnerWidth = await page.evaluate(() => window.innerWidth);
    expect(bodyScrollWidth).toBeLessThanOrEqual(windowInnerWidth + 5);
  });

  test('1.3. Aksesibilitas Keyboard (Tab Order & Focus Trap)', async ({ authenticatedPage: page }) => {
    await page.goto('/warehouse', { waitUntil: 'networkidle' });

    // Focus pertama pada halaman
    await page.keyboard.press('Tab');
    const focusedTag = await page.evaluate(() => document.activeElement?.tagName);
    expect(['A', 'BUTTON', 'INPUT', 'SELECT']).toContain(focusedTag);

    // WCAG Accessibility Scan
    const a11y = await page.runA11y();
    const criticalViolations = a11y.violations.filter(v => v.impact === 'critical');
    expect(criticalViolations).toHaveLength(0);
  });
});
