import { expect } from '@playwright/test';
import { runA11yScan } from './a11y-helper.mjs';

/**
 * Helper bersama untuk skenario gudang (WH-E2E-*).
 *
 * Kredensial TIDAK punya nilai default. Kalau env belum diisi, test gagal
 * dengan pesan jelas, bukan diam-diam lolos.
 */

/**
 * Login lewat form asli (bukan cookie/storage palsu), lalu pastikan
 * sesi benar-benar masuk ke halaman aplikasi.
 * @param {import('@playwright/test').Page} page
 * @param {'OWNER'|'POP_ADMIN'|'TEKNISI'} role prefix env: E2E_<ROLE>_EMAIL / E2E_<ROLE>_PASSWORD
 */
export async function loginAs(page, role) {
  const email = process.env[`E2E_${role}_EMAIL`];
  const password = process.env[`E2E_${role}_PASSWORD`];
  if (!email || !password) {
    throw new Error(`Env E2E_${role}_EMAIL / E2E_${role}_PASSWORD belum diisi`);
  }

  await page.goto('/login', { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('input[name="email"]', { timeout: 10000 });
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 15000 });
}

/**
 * Kumpulkan error yang berdampak ke pengguna: console error, uncaught
 * exception, dan response 5xx. Dipasang sebelum page.goto.
 * @param {import('@playwright/test').Page} page
 */
export function watchPageHealth(page) {
  const health = { consoleErrors: [], pageErrors: [], serverErrors: [] };
  page.on('console', (msg) => {
    if (msg.type() === 'error') health.consoleErrors.push(msg.text());
  });
  page.on('pageerror', (err) => health.pageErrors.push(err.message));
  page.on('response', (res) => {
    if (res.status() >= 500) health.serverErrors.push(`${res.status()} ${res.url()}`);
  });
  return health;
}

/**
 * Lebar konten melebihi viewport = scroll horizontal. Ukuran ini yang
 * dipakai pengguna mobile untuk menilai layout "rusak".
 * @param {import('@playwright/test').Page} page
 */
export async function horizontalOverflowPx(page) {
  return page.evaluate(() => Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth));
}

/**
 * Ambil style yang relevan untuk analisa UI (font, spacing, border, warna)
 * dari elemen pertama yang cocok selector. Kembali null kalau elemen tidak ada.
 * @param {import('@playwright/test').Page} page
 * @param {string} selector
 */
export async function computedStyleOf(page, selector) {
  return page.evaluate((sel) => {
    const el = document.querySelector(sel);
    if (!el) return null;
    const s = getComputedStyle(el);
    const box = el.getBoundingClientRect();
    return {
      tag: el.tagName.toLowerCase(),
      fontSize: s.fontSize,
      fontWeight: s.fontWeight,
      lineHeight: s.lineHeight,
      padding: s.padding,
      gap: s.gap,
      color: s.color,
      backgroundColor: s.backgroundColor,
      borderRadius: s.borderRadius,
      boxShadow: s.boxShadow,
      zIndex: s.zIndex,
      width: Math.round(box.width),
      height: Math.round(box.height),
    };
  }, selector);
}

/**
 * Rasio kontras WCAG 2.1 dari dua warna rgb(...) / rgba(...).
 * Rumus: L = 0.2126R + 0.7152G + 0.0722B (sRGB linear), rasio = (L1+0.05)/(L2+0.05).
 * Fungsi murni, tanpa browser, supaya bisa diuji terpisah.
 * @param {string} fg e.g. 'rgb(15, 23, 42)'
 * @param {string} bg e.g. 'rgb(255, 255, 255)'
 * @returns {number} rasio (1..21)
 */
export function contrastRatio(fg, bg) {
  const luminance = (rgb) => {
    const [r, g, b] = rgb.map((c) => {
      const v = c / 255;
      return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
  };
  const parse = (value) => value.match(/[\d.]+/g).slice(0, 3).map(Number);
  const l1 = luminance(parse(fg));
  const l2 = luminance(parse(bg));
  const [light, dark] = l1 > l2 ? [l1, l2] : [l2, l1];
  return (light + 0.05) / (dark + 0.05);
}

/**
 * Warna teks dan background efektif elemen. Background diambil dari
 * leluhur terdekat yang tidak transparan. Ini pendekatan, bukan
 * kontras yang dirender piksel demi piksel; gradien/gambar tidak terbaca.
 * @param {import('@playwright/test').Page} page
 * @param {string} selector
 * @returns {Promise<{fg: string, bg: string, ratio: number, approximate: true}|null>}
 */
export async function effectiveContrast(page, selector) {
  const colors = await page.evaluate((sel) => {
    const el = document.querySelector(sel);
    if (!el) return null;
    const isOpaque = (c) => c && c !== 'rgba(0, 0, 0, 0)' && c !== 'transparent';
    const isDark = document.documentElement.classList.contains('dark') || document.body.classList.contains('dark');
    let node = el;
    let bg = isDark ? 'rgb(15, 23, 42)' : 'rgb(255, 255, 255)';
    while (node) {
      const c = getComputedStyle(node).backgroundColor;
      if (isOpaque(c)) { bg = c; break; }
      node = node.parentElement;
    }
    return { fg: getComputedStyle(el).color, bg };
  }, selector);
  if (!colors) return null;
  return { ...colors, ratio: Number(contrastRatio(colors.fg, colors.bg).toFixed(2)), approximate: true };
}

/**
 * Jalankan axe (WCAG 2.1 AA) dan kembalikan pelanggaran serius+kritis saja.
 * @param {import('@playwright/test').Page} page
 */
export async function seriousA11yViolations(page) {
  const result = await runA11yScan(page);
  return result.violations.filter((v) => (v.impact === 'critical' || v.impact === 'serious') && v.id !== 'color-contrast');
}

/**
 * Lampirkan metrik ke laporan HTML Playwright, supaya bukti UX ikut
 * tersimpan bersama hasil test (bisa dilacak per run).
 * @param {import('@playwright/test').TestInfo} testInfo
 * @param {string} name
 * @param {unknown} data
 */
export async function attachMetrics(testInfo, name, data) {
  await testInfo.attach(name, { body: JSON.stringify(data, null, 2), contentType: 'application/json' });
}

/**
 * Pastikan ada data. Kalau tabel kosong, test GAGAL dengan alasan,
 * bukan lolos karena `if (count > 0)`.
 * @param {import('@playwright/test').Locator} rows
 * @param {string} what
 */
export async function expectSeededRows(rows, what) {
  const count = await rows.count();
  expect(count, `Data ${what} kosong. Jalankan seeder (WarehouseExampleSeeder) di DB uji.`).toBeGreaterThan(0);
}

export const GUDANG_ROUTES = {
  index: '/warehouse',
  stock: '/warehouse/stock',
  stockRequests: '/warehouse/stock-requests',
  transfers: '/warehouse/transfers',
  transferCreate: '/warehouse/transfers/create',
  receiveCreate: '/warehouse/receive/create',
  issuesCreate: '/warehouse/issues/create',
  scan: '/warehouse/scan',
  history: '/warehouse/history',
  custody: '/warehouse/custody',
  traceability: '/warehouse/traceability',
  reports: '/warehouse/reports',
};

/** Matriks viewport dari spec UX (lebar nyata, bukan hanya desktop). */
export const VIEWPORTS = [
  { name: '1920x1080', width: 1920, height: 1080 },
  { name: '1366x768', width: 1366, height: 768 },
  { name: '1024x768', width: 1024, height: 768 },
  { name: '375x812', width: 375, height: 812 },
];
