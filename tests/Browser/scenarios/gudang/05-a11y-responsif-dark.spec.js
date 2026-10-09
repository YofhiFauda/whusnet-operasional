import { test, expect } from '@playwright/test';
import {
  loginAs, watchPageHealth, horizontalOverflowPx, seriousA11yViolations,
  effectiveContrast, GUDANG_ROUTES, VIEWPORTS, attachMetrics,
} from '../../helpers/gudang-helpers.mjs';

/**
 * WH-E2E-05 — Aksesibilitas (axe WCAG 2.1 AA), responsivitas, dan kontras.
 * Satu test per halaman; setiap viewport dicatat di lampiran laporan.
 */
const ROUTES = Object.entries(GUDANG_ROUTES);

test.describe('WH-E2E-05 A11y, responsif, dan kontras', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'OWNER');
  });

  for (const [key, route] of ROUTES) {
    test(`${key} (${route}): axe serius+kritis = 0 dan tanpa error JS`, async ({ page }, testInfo) => {
      const health = watchPageHealth(page);
      await page.goto(route);

      const violations = await seriousA11yViolations(page);
      await attachMetrics(testInfo, 'axe', violations.map((v) => ({
        id: v.id, impact: v.impact, help: v.help, nodes: v.nodesCount, targets: v.targets.slice(0, 5),
      })));
      expect(violations.map((v) => v.id), 'Pelanggaran axe serius/kritis').toEqual([]);
      expect(health.pageErrors).toEqual([]);
    });

    test(`${key} (${route}): tidak scroll horizontal di semua viewport`, async ({ page }, testInfo) => {
      await page.goto(route);
      const result = {};
      for (const vp of VIEWPORTS) {
        await page.setViewportSize({ width: vp.width, height: vp.height });
        await page.waitForTimeout(150); // beri waktu transisi CSS responsif
        result[vp.name] = await horizontalOverflowPx(page);
      }
      await attachMetrics(testInfo, 'overflow-px', result);
      const broken = Object.entries(result).filter(([, px]) => px > 0);
      expect(broken, `Overflow horizontal: ${JSON.stringify(broken)}`).toHaveLength(0);
    });
  }

  test('mode gelap: teks utama tetap memenuhi kontras 4.5:1 di halaman stok', async ({ page }, testInfo) => {
    await page.goto(GUDANG_ROUTES.stock);
    await page.evaluate(() => document.documentElement.classList.add('dark'));
    await page.waitForTimeout(150);

    const contrast = {
      heading: await effectiveContrast(page, 'h1'),
      cell: await effectiveContrast(page, 'tbody td'),
      header: await effectiveContrast(page, 'thead th'),
    };
    await attachMetrics(testInfo, 'dark-contrast', contrast);

    for (const [name, c] of Object.entries(contrast)) {
      expect(c, `Elemen ${name} tidak ditemukan`).not.toBeNull();
      expect(c.ratio, `Kontras ${name} mode gelap ${c.ratio}:1 < 4.5:1`).toBeGreaterThanOrEqual(4.5);
    }
  });

  test('mode gelap: class .dark dipakai (variant Tailwind) dan background berubah', async ({ page }) => {
    await page.goto(GUDANG_ROUTES.index);
    const lightBg = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);
    await page.evaluate(() => document.documentElement.classList.add('dark'));
    await page.waitForTimeout(150);
    const darkBg = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);
    expect(darkBg, 'Background body tidak berubah saat mode gelap').not.toBe(lightBg);
  });
});
