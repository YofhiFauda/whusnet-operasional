// Smoke test journey penuh Portal Pelanggan — k6 (https://k6.io). JANGAN
// dijalankan ke produksi: jalankan ke STAGING.
//
//   docker run --rm -i -e BASE_URL=https://staging.contoh.id \
//     -e PORTAL_CLIENT_SECRET=... -e USERS_FILE=/data/users.json \
//     -e VUS=20 -e QR_CODE=ABC123 \
//     grafana/k6 run - < tests/load/portal-smoke.k6.js
//
// BEDA dari dua skrip lain di folder ini:
//   - portal-burst.k6.js      → banyak pelanggan SAH, login SEKALI, puncak beban
//   - portal-bruteforce.k6.js → SATU klien jahat, password salah berkali, limiter
//   - portal-smoke.k6.js (ini)→ load MODERAT, tiap VU jalanin JOURNEY LENGKAP
//                                (ping → login → me/* → refresh → me lagi → qr),
//                                buat pastiin tiap endpoint FUNGSIONAL bener di
//                                bawah beban bersamaan — bukan buat nyari batas
//                                kapasitas server.
//
// Env:
//   BASE_URL              wajib.
//   PORTAL_CLIENT_SECRET  wajib.
//   USERS_FILE            wajib. JSON [{"login_id":"...","password":"..."}] —
//                         akun staging SUNGGUHAN, bukan dummy (butuh data asli
//                         buat validasi /me/invoices /me/payments /me/tickets).
//   VUS                   default 20 (moderat, bukan spike).
//   QR_CODE               opsional. Kode QR aktif di staging buat tes
//                         /qr/resolve — tanpa ini, step QR dilewati (bukan
//                         gagal), karena kode QR biasanya sekali pakai/kadaluarsa.
//
// Thresholds longgar dibanding dua skrip lain: tujuan smoke test = SEMUA
// endpoint balas 2xx/404-wajar, bukan ngukur kecepatan di bawah tekanan.

import http from 'k6/http';
import { check, sleep } from 'k6';
import { SharedArray } from 'k6/data';

const BASE = __ENV.BASE_URL;
const SECRET = __ENV.PORTAL_CLIENT_SECRET;
const VUS = Number(__ENV.VUS || 20);
const QR_CODE = __ENV.QR_CODE || '';

if (!BASE || !SECRET || !__ENV.USERS_FILE) {
  throw new Error('BASE_URL, PORTAL_CLIENT_SECRET, dan USERS_FILE wajib diisi.');
}

const users = new SharedArray('users', () => JSON.parse(open(__ENV.USERS_FILE)));
if (users.length === 0) {
  throw new Error('USERS_FILE kosong — butuh minimal 1 akun staging sungguhan.');
}

http.setResponseCallback(http.expectedStatuses({ min: 200, max: 499 }));

export const options = {
  scenarios: {
    smoke: { executor: 'per-vu-iterations', vus: VUS, iterations: 1, maxDuration: '3m' },
  },
  thresholds: {
    http_req_failed: ['rate==0'], // di sini http_req_failed berarti 5xx (lihat setResponseCallback)
    checks: ['rate>0.95'],
  },
};

function headers(token) {
  const h = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-Portal-Client': SECRET,
  };
  if (token) h.Authorization = `Bearer ${token}`;
  return h;
}

export default function () {
  const vu = __VU;
  const account = users[(vu - 1) % users.length];

  // 1. /ping — health check publik, tanpa secret sama sekali.
  const ping = http.get(`${BASE}/api/customer-portal/ping`, { tags: { name: 'ping' } });
  check(ping, { 'ping 200': (r) => r.status === 200 });

  // 2. Login dengan akun SAH.
  const loginRes = http.post(
    `${BASE}/api/customer-portal/auth/login`,
    JSON.stringify({ login_id: account.login_id, password: account.password }),
    { headers: headers(), tags: { name: 'auth/login' } }
  );
  const loginOk = check(loginRes, { 'login 200': (r) => r.status === 200 });
  if (!loginOk) {
    // Akun ini mungkin memang gagal (password berubah, dll) — jangan lanjut
    // journey dengan token kosong, tapi JANGAN gagalkan seluruh VU: akun lain
    // tetap perlu diuji.
    return;
  }

  let accessToken = loginRes.json('access_token');
  const refreshToken = loginRes.json('refresh_token');

  // 3. Endpoint /me/* dengan access token dari login.
  check(http.get(`${BASE}/api/customer-portal/me`, { headers: headers(accessToken), tags: { name: 'me' } }), {
    'me 200': (r) => r.status === 200,
  });
  check(
    http.get(`${BASE}/api/customer-portal/me/invoices`, { headers: headers(accessToken), tags: { name: 'me/invoices' } }),
    { 'me/invoices 200': (r) => r.status === 200 }
  );
  check(
    http.get(`${BASE}/api/customer-portal/me/payments`, { headers: headers(accessToken), tags: { name: 'me/payments' } }),
    { 'me/payments 200': (r) => r.status === 200 }
  );
  check(
    http.get(`${BASE}/api/customer-portal/me/balance`, { headers: headers(accessToken), tags: { name: 'me/balance' } }),
    { 'me/balance 200': (r) => r.status === 200 }
  );
  check(
    http.get(`${BASE}/api/customer-portal/me/tickets`, { headers: headers(accessToken), tags: { name: 'me/tickets' } }),
    { 'me/tickets 200': (r) => r.status === 200 }
  );

  sleep(1);

  // 4. Rotasi token lewat /auth/refresh — refresh_token BEDA mekanisme dari
  // access token (diverifikasi manual di PortalAuthService, bukan portal_token
  // middleware), jadi perlu dites terpisah dari /me/*.
  const refreshRes = http.post(
    `${BASE}/api/customer-portal/auth/refresh`,
    JSON.stringify({ refresh_token: refreshToken }),
    { headers: headers(), tags: { name: 'auth/refresh' } }
  );
  const refreshOk = check(refreshRes, { 'refresh 200': (r) => r.status === 200 });

  // 5. Token baru hasil refresh harus langsung valid (bukan basi/reuse lama).
  if (refreshOk) {
    accessToken = refreshRes.json('access_token');
    check(http.get(`${BASE}/api/customer-portal/me`, { headers: headers(accessToken), tags: { name: 'me-after-refresh' } }), {
      'me setelah refresh 200': (r) => r.status === 200,
    });
  }

  // 6. QR resolve — opsional, kode QR sekali pakai jadi gak dipaksa tiap VU.
  if (QR_CODE) {
    const qrRes = http.get(`${BASE}/api/customer-portal/qr/resolve?code=${encodeURIComponent(QR_CODE)}`, {
      headers: headers(),
      tags: { name: 'qr/resolve' },
    });
    check(qrRes, { 'qr/resolve 200 atau 404': (r) => r.status === 200 || r.status === 404 });
  }

  // 7. Logout — bersihkan sesi biar gak numpuk token aktif di staging tiap run.
  http.post(`${BASE}/api/customer-portal/auth/logout`, null, {
    headers: headers(accessToken),
    tags: { name: 'auth/logout' },
  });
}
