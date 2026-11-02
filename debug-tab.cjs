const puppeteer = require('puppeteer-core');

(async () => {
    const browser = await puppeteer.launch({
        headless: 'new',
        executablePath: process.env.CHROME_PATH || '/usr/bin/google-chrome',
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-gpu', '--disable-dev-shm-usage'],
    });
    const page = await browser.newPage();

    page.on('console', (msg) => console.log('[console.' + msg.type() + ']', msg.text()));
    page.on('pageerror', (err) => console.log('[pageerror]', err.message));

    await page.goto('http://localhost:8000/login', { waitUntil: 'domcontentloaded' });
    await page.type('input[name="email"]', 'superadmin@whusnet.com');
    await page.type('input[name="password"]', 'password');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 60000 }),
        page.click('button[type="submit"]'),
    ]);

    await page.goto('http://localhost:8000/customers/2', { waitUntil: 'domcontentloaded' });

    const hasAlpine = await page.evaluate(() => !!window.Alpine);
    console.log('window.Alpine exists:', hasAlpine);

    const storeExists = await page.evaluate(() => {
        try {
            return !!(window.Alpine && window.Alpine.store('tabState'));
        } catch (e) {
            return 'ERROR: ' + e.message;
        }
    });
    console.log('store exists:', storeExists);

    // click Identitas tab
    await page.click('#tab-btn-identitas');
    await new Promise((r) => setTimeout(r, 500));

    const result = await page.evaluate(() => {
        const el = document.getElementById('tab-content-identitas');
        return {
            hiddenClass: el ? el.classList.contains('hidden') : 'NO ELEMENT',
            childCount: el ? el.children.length : -1,
            html: el ? el.innerHTML.slice(0, 300) : 'NONE',
        };
    });
    console.log('tab-content-identitas after click:', JSON.stringify(result, null, 2));

    await browser.close();
})();
