// Login otomatis sebelum audit Lighthouse CI (puppeteerScript). Selector
// dicocokkan ke resources/views/auth/login.blade.php. Hanya untuk dev lokal —
// kredensial seeder default (UserSeeder), jangan dipakai ke server beneran.
//
// Tunggu 'domcontentloaded', BUKAN 'networkidle*': dashboard punya koneksi
// WebSocket/polling yang tidak pernah idle, jadi networkidle bikin timeout.
module.exports = async (browser) => {
    const page = await browser.newPage();

    await page.goto('http://localhost:8000/login', { waitUntil: 'domcontentloaded' });

    // Skrip ini dipanggil LHCI SEBELUM tiap URL, dan cookie sesi bertahan antar
    // URL. Kalau sudah login, /login dialihkan ke dashboard (tidak ada form).
    if (!(await page.$('input[name="email"]'))) {
        await page.close();
        return;
    }

    await page.type('input[name="email"]', process.env.LH_EMAIL || 'superadmin@whusnet.com');
    await page.type('input[name="password"]', process.env.LH_PASSWORD || 'password');

    await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 60000 }),
        page.click('button[type="submit"]'),
    ]);

    if (new URL(page.url()).pathname.startsWith('/login')) {
        const msg = await page.$eval('body', (b) => b.innerText.slice(0, 300)).catch(() => '');
        throw new Error(`Login gagal (masih di /login). Cek LH_EMAIL/LH_PASSWORD. Isi halaman: ${msg}`);
    }

    await page.close();
};
