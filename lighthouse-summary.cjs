// Ringkas skor semua laporan di lighthouse-reports/*/lhr-*.json, urut dari
// skor Performance terendah (= halaman yang paling perlu diperbaiki).
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, 'lighthouse-reports');
const rows = [];

for (const group of fs.existsSync(root) ? fs.readdirSync(root) : []) {
    const dir = path.join(root, group);
    if (!fs.statSync(dir).isDirectory()) continue;
    for (const f of fs.readdirSync(dir).filter((n) => /^lhr-.*\.json$/.test(n))) {
        const j = JSON.parse(fs.readFileSync(path.join(dir, f)));
        const pct = (c) => Math.round((j.categories[c]?.score ?? 0) * 100);
        const ms = (a) => Math.round(j.audits[a]?.numericValue ?? 0);
        rows.push({
            perf: pct('performance'),
            a11y: pct('accessibility'),
            bp: pct('best-practices'),
            seo: pct('seo'),
            ttfb: ms('server-response-time'),
            lcp: ms('largest-contentful-paint'),
            tbt: ms('total-blocking-time'),
            url: new URL(j.finalUrl).pathname,
            group,
        });
    }
}

rows.sort((a, b) => a.perf - b.perf);
console.log(['perf', 'a11y', 'bp', 'seo', 'ttfb', 'lcp', 'tbt', 'grup', 'url'].join('\t'));
for (const r of rows) console.log([r.perf, r.a11y, r.bp, r.seo, r.ttfb, r.lcp, r.tbt, r.group, r.url].join('\t'));
console.log(`\n${rows.length} halaman. Satuan ttfb/lcp/tbt = ms.`);
