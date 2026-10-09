import { chromium } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const AUTH_DIR = path.resolve('tests/Browser/fixtures/auth');
const BASE_URL = process.env.APP_URL || 'http://127.0.0.1:8000';

const USER_ACCOUNTS = {
  superadmin: { email: 'owner1@whusnet.com', password: 'password' },
  admin_pusat: { email: 'admin1@whusnet.com', password: 'password' },
  admin_pop: { email: 'popadmin1@whusnet.com', password: 'password' },
  teknisi: { email: 'teknisi1@whusnet.com', password: 'password' },
  helpdesk: { email: 'helpdesk1@whusnet.com', password: 'password' },
  kolektor: { email: 'kolektor1@whusnet.com', password: 'password' },
  sales: { email: 'sales1@whusnet.com', password: 'password' },
};

export async function bootstrapAuthStates() {
  if (!fs.existsSync(AUTH_DIR)) {
    fs.mkdirSync(AUTH_DIR, { recursive: true });
  }

  console.log(`🔑 Generating Playwright Auth Sessions against ${BASE_URL}...`);
  const browser = await chromium.launch({ headless: true });

  for (const [roleName, credentials] of Object.entries(USER_ACCOUNTS)) {
    const storageStatePath = path.join(AUTH_DIR, `${roleName}.json`);
    const context = await browser.newContext();
    const page = await context.newPage();

    try {
      console.log(`  -> Logging in as [${roleName}] (${credentials.email})...`);
      await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
      await page.fill('input[name="email"]', credentials.email);
      await page.fill('input[name="password"]', credentials.password);
      await page.click('button[type="submit"]');

      // Wait until redirection happens away from login
      await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 10000 });
      
      await context.storageState({ path: storageStatePath });
      console.log(`  ✅ Session saved: ${roleName}.json`);
    } catch (err) {
      console.warn(`  ⚠️ Failed to save session for [${roleName}]: ${err.message}`);
    } finally {
      await context.close();
    }
  }

  await browser.close();
  console.log('✨ All Auth States bootstrap completed.');
}

if (process.argv[1]?.endsWith('auth-bootstrap.mjs')) {
  bootstrapAuthStates().catch(console.error);
}
