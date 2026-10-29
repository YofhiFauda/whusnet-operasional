// Lighthouse CI — dipecah per kelompok supaya hemat RAM (WSL 4 GB bisa macet
// kalau 44 halaman berat diaudit sekali jalan).
//
//   LH_GROUP=pelanggan npx lhci autorun     # satu kelompok
//   ./run-lighthouse-groups.sh              # semua kelompok berurutan
//
// Target: app dev lokal di http://localhost:8000. Login otomatis lewat
// lighthouse-auth.cjs. Ganti ID contoh (customers/44, invoices/1,
// payments/1, collector-worksheet/107) dengan record nyata di DB lokal.
const BASE = process.env.LH_BASE || 'http://localhost:8000';

const groups = {
    umum: ['/', '/notifications', '/profile', '/roles', '/users'],
    pelanggan: [
        '/customers',
        '/customers/44',
        '/customers/create',
        '/customers/terminated',
        '/customers/failed',
    ],
    keuangan: [
        '/invoices',    
        '/invoices/create',
        '/invoices/lunas',
        '/invoices/belum-lunas',
        '/invoices/1',
        '/payments',
        '/payments/1',
    ],
    worksheet: ['/collector-worksheet', '/collector-worksheet/107', '/noc/worksheet'],
    fop: ['/fop', '/fop-tasks', '/fop-tasks/history', '/fop/analitik'],
    infrastruktur: ['/network/nodes', '/network/peta'],
    ticketing: ['/tickets/new', '/tickets/selesai', '/tickets/dibatalkan', '/tickets/history'],
    // Hanya halaman index laporan; /export & /export-xlsx itu unduhan file.
    laporan: [
        '/reports/customers',
        '/reports/invoices',
        '/reports/payments',
        '/reports/imports',
        '/reports/collector-monthly',
        '/reports/collector-payments',
    ],
    gudang: [
        '/warehouse',
        '/warehouse/stock',
        '/warehouse/history',
        '/warehouse/transfers',
        '/warehouse/usage',
        '/warehouse/traceability',
    ],
};

const group = process.env.LH_GROUP;
if (!group || !groups[group]) {
    throw new Error(
        `Set LH_GROUP salah satu dari: ${Object.keys(groups).join(', ')} (atau pakai ./run-lighthouse-groups.sh)`,
    );
}

module.exports = {
    ci: {
        collect: {
            url: groups[group].map((path) => BASE + path),
            puppeteerScript: './lighthouse-auth.cjs',
            // 1 run = cepat tapi noisy (+-5-15 poin). Naikkan ke 3 untuk angka final.
            numberOfRuns: Number(process.env.LH_RUNS || 1),
            // Dengan puppeteerScript, settings.chromeFlags DIABAIKAN LHCI —
            // wajib lewat puppeteerLaunchOptions.args. --no-sandbox karena root di WSL.
            puppeteerLaunchOptions: {
                headless: 'new',
                args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-gpu', '--disable-dev-shm-usage'],
            },
            settings: {
                formFactor: 'desktop',
                screenEmulation: { disabled: true },
            },
        },
        upload: {
            target: 'filesystem',
            outputDir: `./lighthouse-reports/${group}`,
        },
    },
};
