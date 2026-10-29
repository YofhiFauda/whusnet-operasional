// Uji beban API Portal Pelanggan — k6 (https://k6.io). JANGAN dijalankan ke
// produksi yang melayani pelanggan sungguhan: jalankan ke STAGING.
//
//   docker run --rm -i -e BASE_URL=https://staging.contoh.id \
//     -e PORTAL_CLIENT_SECRET=... -e MODE=spike -e VUS=5000 \
//     grafana/k6 run - < tests/load/portal-burst.k6.js
//
// Env:
//   BASE_URL              wajib. Tanpa slash akhir (mis. https://staging.contoh.id)
//   PORTAL_CLIENT_SECRET  wajib. Sama dengan PORTAL_CLIENT_SECRET di Laravel staging.
//   MODE                  spike (default) = semua VUS mulai bersamaan, 1 iterasi
//                         ramp            = naik bertahap ke VUS dalam RAMP_MINUTES menit
//   VUS                   jumlah pelanggan virtual (default 5000)
//   RAMP_MINUTES          untuk MODE=ramp (default 10)
//   USERS_FILE            opsional, JSON [{"login_id":"...","password":"..."}].
//                         Tanpa ini: login_id palsu → 401 yang DIHARAPKAN; tetap
//                         menguji limiter + biaya bcrypt, tapi tidak /me/*.
//
// Tiap pelanggan virtual mengirim X-Portal-Client-IP berbeda (rentang
// 198.18.0.0/15, khusus benchmark) seolah dari 5.000 pelanggan sungguhan lewat
// SATU server portal — persis pola produksi.

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Trend } from 'k6/metrics';
import { SharedArray } from 'k6/data';

const BASE = __ENV.BASE_URL;
const SECRET = __ENV.PORTAL_CLIENT_SECRET;
const VUS = Number(__ENV.VUS || 5000);
const MODE = __ENV.MODE || 'spike';
const RAMP_MINUTES = Number(__ENV.RAMP_MINUTES || 10);

if (!BASE || !SECRET) {
  throw new Error('BASE_URL dan PORTAL_CLIENT_SECRET wajib diisi.');
}

const users = new SharedArray('users', () => (__ENV.USERS_FILE ? JSON.parse(open(__ENV.USERS_FILE)) : []));

// Hanya 5xx yang dihitung gagal. 401 (kredensial palsu) dan 429 (kena limiter)
// adalah jawaban SAH yang justru sedang kita ukur.
http.setResponseCallback(http.expectedStatuses({ min: 200, max: 499 }));

const loginOk = new Counter('login_ok_200');
const loginRejected = new Counter('login_rejected_401');
const loginLimited = new Counter('login_limited_429');
const loginServerError = new Counter('login_server_error_5xx');
const loginDuration = new Trend('login_duration_ms', true);

export const options = {
  scenarios:
    MODE === 'ramp'
      ? {
          portal: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
              { duration: `${RAMP_MINUTES}m`, target: VUS },
              { duration: '1m', target: 0 },
            ],
          },
        }
      : {
          portal: { executor: 'per-vu-iterations', vus: VUS, iterations: 1, maxDuration: '5m' },
        },
  thresholds: {
    // Server TIDAK boleh error: 429 boleh banyak (itu limiter bekerja), 5xx tidak.
    login_server_error_5xx: ['count==0'],
    // Pelanggan yang lolos limiter tidak boleh menunggu lebih dari 10 detik.
    login_duration_ms: ['p(95)<10000'],
  },
};

function clientIpFor(vu) {
  const n = vu % 130000; // 198.18.0.0 – 198.19.255.255
  return `198.${18 + Math.floor(n / 65536)}.${Math.floor((n % 65536) / 256)}.${n % 256}`;
}

function portalHeaders(vu, token) {
  const headers = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-Portal-Client': SECRET,
    'X-Portal-Client-IP': clientIpFor(vu),
  };
  if (token) headers.Authorization = `Bearer ${token}`;
  return headers;
}

export default function () {
  const vu = __VU;
  const account = users.length ? users[(vu - 1) % users.length] : null;
  const payload = JSON.stringify({
    login_id: account ? account.login_id : `LOADTEST-${vu}`,
    password: account ? account.password : 'password-palsu-loadtest',
  });

  const res = http.post(`${BASE}/api/customer-portal/auth/login`, payload, {
    headers: portalHeaders(vu),
    tags: { name: 'auth/login' },
  });
  loginDuration.add(res.timings.duration);

  if (res.status === 200) loginOk.add(1);
  else if (res.status === 401) loginRejected.add(1);
  else if (res.status === 429) loginLimited.add(1);
  else if (res.status >= 500) loginServerError.add(1);

  check(res, { 'bukan 5xx': (r) => r.status < 500 });

  // Pelanggan yang berhasil login membuka dashboard seperti sesi nyata.
  if (res.status === 200) {
    const token = res.json('access_token');
    http.get(`${BASE}/api/customer-portal/me`, { headers: portalHeaders(vu, token), tags: { name: 'me' } });
    sleep(1);
    http.get(`${BASE}/api/customer-portal/me/invoices`, { headers: portalHeaders(vu, token), tags: { name: 'me/invoices' } });
    http.get(`${BASE}/api/customer-portal/me/balance`, { headers: portalHeaders(vu, token), tags: { name: 'me/balance' } });
  }
}
