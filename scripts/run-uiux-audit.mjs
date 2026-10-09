import { chromium } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { runA11yScan } from '../tests/Browser/helpers/a11y-helper.mjs';
import { VIEWPORT_MATRIX } from '../tests/Browser/helpers/viewport-matrix.mjs';

const BASE_URL = process.env.APP_URL || 'http://127.0.0.1:8000';
const ARTIFACTS_DIR = path.resolve('docs/audits/reports/artifacts');
const REPORT_FILE = path.resolve('docs/audits/reports/LATEST_UI_UX_AUDIT.md');

const ROUTES_TO_AUDIT = [
  { name: 'Dashboard Utama', path: '/dashboard' },
  { name: 'Gudang - Transfer Barang', path: '/warehouse/transfers' },
  { name: 'Gudang - Form Buat Transfer', path: '/warehouse/transfers/create' },
  { name: 'Gudang - Stok Barang', path: '/warehouse/stock' },
  { name: 'Gudang - Scan QR/Barcode', path: '/warehouse/scan' },
  { name: 'Gudang - Histori & Log', path: '/warehouse/history' },
];

async function runFullAudit() {
  if (!fs.existsSync(ARTIFACTS_DIR)) {
    fs.mkdirSync(ARTIFACTS_DIR, { recursive: true });
  }

  console.log(`🚀 Memulai Audit UI/UX Otomatis pada ${BASE_URL}...\n`);
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();

  // 1. Authenticate as Superadmin
  console.log('🔑 Melakukan Login sebagai Superadmin...');
  await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
  await page.fill('input[name="email"]', 'owner1@whusnet.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.includes('/login'));

  const auditReportItems = [];

  // 2. Audit Each Route
  for (const route of ROUTES_TO_AUDIT) {
    console.log(`🔍 Mengaudit: ${route.name} (${route.path})`);
    const routeErrors = [];
    page.on('console', (msg) => {
      if (msg.type() === 'error') routeErrors.push(msg.text());
    });
    page.on('pageerror', (err) => routeErrors.push(err.message));

    const startTime = Date.now();
    await page.goto(`${BASE_URL}${route.path}`, { waitUntil: 'networkidle' });
    const loadTimeMs = Date.now() - startTime;

    // A11y scan
    const a11yResult = await runA11yScan(page);

    // Capture Desktop & Mobile screenshots
    const safeName = route.name.toLowerCase().replace(/[^a-z0-9]/g, '_');
    
    // Desktop HD
    await page.setViewportSize({ width: 1440, height: 900 });
    const desktopImg = path.join(ARTIFACTS_DIR, `${safeName}_desktop.png`);
    await page.screenshot({ path: desktopImg, fullPage: true });

    // Mobile
    await page.setViewportSize({ width: 375, height: 812 });
    const mobileImg = path.join(ARTIFACTS_DIR, `${safeName}_mobile.png`);
    await page.screenshot({ path: mobileImg, fullPage: true });

    auditReportItems.push({
      ...route,
      loadTimeMs,
      consoleErrors: routeErrors,
      a11yViolations: a11yResult.violations,
      desktopImg: path.relative(process.cwd(), desktopImg),
      mobileImg: path.relative(process.cwd(), mobileImg),
    });
  }

  await browser.close();

  // 3. Compile Markdown Report
  console.log(`\n📝 Menyusun Laporan Markdown ke: ${REPORT_FILE}`);
  let mdContent = `# 📋 Hasil Audit UI/UX & Browser Testing Otomatis\n\n`;
  mdContent += `**Tanggal & Waktu**: ${new Date().toLocaleString('id-ID')}\n`;
  mdContent += `**Target Base URL**: \`${BASE_URL}\`\n`;
  mdContent += `**Total Halaman Diaudit**: ${auditReportItems.length}\n\n`;

  mdContent += `## 1. Ringkasan Status Halaman\n\n`;
  mdContent += `| Halaman | Load Time | A11y Violations | JS Console Errors | Status |\n`;
  mdContent += `| :--- | :---: | :---: | :---: | :---: |\n`;

  for (const item of auditReportItems) {
    const statusIcon = item.consoleErrors.length === 0 && item.a11yViolations.length === 0 ? '🟢 Pass' : '🟡 Review';
    mdContent += `| **${item.name}** (\`${item.path}\`) | ${item.loadTimeMs}ms | ${item.a11yViolations.length} issue | ${item.consoleErrors.length} error | ${statusIcon} |\n`;
  }

  mdContent += `\n## 2. Detail Analisa & Accessibility (A11y)\n\n`;
  for (const item of auditReportItems) {
    mdContent += `### 📄 ${item.name} (\`${item.path}\`)\n`;
    mdContent += `- **Load Time**: \`${item.loadTimeMs} ms\`\n`;
    mdContent += `- **Console Errors**: ${item.consoleErrors.length === 0 ? '✅ Bersih' : `⚠️ ${item.consoleErrors.join(', ')}`}\n`;
    
    if (item.a11yViolations.length > 0) {
      mdContent += `- **Accessibility Warnings (${item.a11yViolations.length})**:\n`;
      for (const v of item.a11yViolations) {
        mdContent += `  - **[${v.impact?.toUpperCase()}]** ${v.help} (\`${v.id}\`): ${v.description}\n`;
      }
    } else {
      mdContent += `- **Accessibility**: ✅ Lolos standar WCAG 2.1 AA\n`;
    }

    mdContent += `- **Snapshot Desktop**: \`${item.desktopImg}\`\n`;
    mdContent += `- **Snapshot Mobile**: \`${item.mobileImg}\`\n\n`;
  }

  fs.writeFileSync(REPORT_FILE, mdContent, 'utf-8');
  console.log(`🎉 Audit selesai! Buka dokumen laporan: docs/audits/reports/LATEST_UI_UX_AUDIT.md`);
}

runFullAudit().catch(console.error);
