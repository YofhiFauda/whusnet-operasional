// Uji limiter anti-brute-force Portal Pelanggan — k6 (https://k6.io). JANGAN
// dijalankan ke produksi: jalankan ke STAGING.
//
//   docker run --rm -i -e BASE_URL=https://staging.contoh.id \
//     -e PORTAL_CLIENT_SECRET=... -e LOGIN_ID=PNG-RQ000631 \
//     grafana/k6 run - < tests/load/portal-bruteforce.k6.js
//
// Beda dengan portal-burst.k6.js (banyak pelanggan SAH, banyak IP, 1x login):
// skrip ini SATU klien jahat (1 IP, 1 akun) ngulang password salah berkali —
// ngetest DUA mekanisme beda yang kadang disalahartikan satu hal:
//
//   1. customer-portal-auth limiter (5/15menit per IP+login_id) → 429
//   2. Lockout akun (5x gagal berturut-turut → kunci 15 menit) → 423
//      (app/Http/Controllers/CustomerPortal/PortalAuthController.php)
//
// Keduanya sama-sama ambang 5, jadi lomba siapa kena duluan — skrip ini
// cuma memastikan SALAH SATU jalan (bukan 200 lolos brute-force) dan server
// gak 5xx waktu kena spam.
//
// Env:
//   BASE_URL              wajib.
//   PORTAL_CLIENT_SECRET  wajib.
//   LOGIN_ID              wajib. Akun pelanggan SUNGGUHAN di staging (akun ini
//                         akan KETERKUNCI 15 menit tiap kali skrip ini jalan —
//                         jangan pakai akun yang lagi dipakai tes lain).
//   ATTEMPTS              default 10 (lebih dari ambang 5 — buat lihat yang
//                         terjadi SETELAH limiter/lockout trigger, bukan cuma
//                         pas kena pertama kali).

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';

const BASE = __ENV.BASE_URL;
const SECRET = __ENV.PORTAL_CLIENT_SECRET;
const LOGIN_ID = __ENV.LOGIN_ID;
const ATTEMPTS = Number(__ENV.ATTEMPTS || 10);

if (!BASE || !SECRET || !LOGIN_ID) {
  throw new Error('BASE_URL, PORTAL_CLIENT_SECRET, dan LOGIN_ID wajib diisi.');
}

// Satu IP tetap (klien jahat beneran dari satu tempat) — BUKAN rentang
// benchmark seperti portal-burst, sengaja supaya kena limiter per-IP.
const ATTACKER_IP = '203.0.113.7'; // TEST-NET-3 (RFC 5737), aman dipakai simulasi

http.setResponseCallback(http.expectedStatuses({ min: 200, max: 499 }));

const rejected401 = new Counter('login_rejected_401');
const locked423 = new Counter('login_locked_423');
const limited429 = new Counter('login_limited_429');
const serverError5xx = new Counter('login_server_error_5xx');
const unexpectedOk200 = new Counter('login_unexpected_ok_200'); // password salah TAPI lolos — bug kalau > 0

export const options = {
  scenarios: {
    bruteforce: { executor: 'shared-iterations', vus: 1, iterations: ATTEMPTS, maxDuration: '5m' },
  },
  thresholds: {
    login_server_error_5xx: ['count==0'],
    // Password salah gak boleh pernah lolos — ini satu-satunya kegagalan fatal.
    login_unexpected_ok_200: ['count==0'],
  },
};

function headers() {
  return {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-Portal-Client': SECRET,
    'X-Portal-Client-IP': ATTACKER_IP,
  };
}

export default function () {
  const payload = JSON.stringify({ login_id: LOGIN_ID, password: 'password-salah-sengaja' });

  const res = http.post(`${BASE}/api/customer-portal/auth/login`, payload, {
    headers: headers(),
    tags: { name: 'auth/login-bruteforce' },
  });

  if (res.status === 200) unexpectedOk200.add(1);
  else if (res.status === 401) rejected401.add(1);
  else if (res.status === 423) locked423.add(1);
  else if (res.status === 429) limited429.add(1);
  else if (res.status >= 500) serverError5xx.add(1);

  check(res, {
    'password salah gak pernah lolos': (r) => r.status !== 200,
    'bukan 5xx': (r) => r.status < 500,
  });

  sleep(0.5);
}
