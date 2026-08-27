# Rancangan Frontend Portal Pelanggan — Next.js (BFF)

Blueprint buat app Portal Pelanggan (Next.js + React + TypeScript) — **di luar
repo ini** (`docs/api/api-portal-pelanggan/business-logic.md` §"Portal adalah
aplikasi terpisah"), tapi dokumen ini ditaruh di sini karena isinya cara
mengonsumsi API yang SUDAH jadi di repo ini (Fase 0-4, `rencana-implementasi.md`
— semua Done). Ditulis biar pembuatan Portal nanti gak nebak-nebak kontrak API
atau salah pola arsitektur dari awal.

**Backend (repo ini) TIDAK BERUBAH sama sekali gara-gara dokumen ini** — murni
panduan sisi Next.js.

## Prasyarat — siapkan SEBELUM mulai coding

- **Akses ke Laravel dev/staging yang jalan** (bukan cuma baca dokumen) —
  `LARAVEL_API_URL` yang beneran bisa di-`curl`, plus `PORTAL_CLIENT_SECRET`
  yang cocok (minta dari yang pegang `.env` repo ini, `webhooks.php` config
  `portal_client_secret`).
- **Minimal 1 akun pelanggan uji coba nyata**: satu `login_id` + PIN
  (dari kartu `/qr/cetak` staf) buat tes `claim`, dan satu akun `active`
  (udah pernah claim) buat tes `login` — dua skenario beda, dua akun beda.
- **Node.js versi yang didukung Next.js target** (cek `package.json`
  Next.js — biasanya Node 18.18+/20+) + package manager yang disepakati tim
  (`pnpm`/`npm`/`yarn`, konsisten, jangan campur lockfile).
- **Baca `business-logic.md` §"Kontrak endpoint" penuh dulu** SEBELUM nulis
  tipe TypeScript — dokumen itu sumber kebenaran bentuk response, dokumen
  ini (frontend-nextjs-rancangan.md) cuma peta cara makainya.
- **Sepakati dulu**: `iron-session` vs cookie signed manual (`jose`) buat
  nyimpen sesi — pilih SATU sebelum mulai Route Handler auth, jangan
  nyampur dua pendekatan di file berbeda.

## Yang HARUS dihindari — daftar larangan, bukan saran

1. **JANGAN kasih Next.js database sendiri** (Postgres/Prisma/apa pun).
   Semua data pelanggan hidup di Laravel (repo ini). Dua sumber kebenaran
   buat data yang sama = bug sinkronisasi yang gak perlu ada.
2. **JANGAN prefix env rahasia (`PORTAL_CLIENT_SECRET`, dll) dengan
   `NEXT_PUBLIC_`** — itu bikin Next.js nge-bundle nilainya ke JS yang
   dikirim ke browser, sama fatalnya kayak nulis password di HTML.
3. **JANGAN simpen `access_token`/`refresh_token` di `localStorage` atau
   cookie non-`httpOnly`** — itu ngebatalin seluruh alasan pola BFF ini
   dipakai (lihat bagian "Pola arsitektur" di atas).
4. **JANGAN panggil Laravel langsung dari Client Component** (`fetch()` ke
   `LARAVEL_API_URL` dari kode yang jalan di browser). SEMUA panggilan lewat
   Route Handler Next.js sendiri — kalau ini dilanggar, `X-Portal-Client`
   secret otomatis kebocor ke browser (harus ditempel di request, dan
   request dari browser keliatan devtools).
5. **JANGAN `fetch()` manual ke Laravel di banyak tempat** — satu pintu
   `lib/laravel-client.ts` (lihat bagian struktur folder). Nyebar berarti
   logic refresh-on-401 kepaksa dikopipaste, gampang lupa satu tempat.
6. **JANGAN hardcode ulang label status Indonesia** (`"Sedang Ditangani"`,
   `"Lunas"`, dst) di komponen React — pakai `label` yang udah dikirim
   backend (`{value, label}`). Kalau backend ganti teksnya, Next.js ikut
   otomatis, gak perlu PR terpisah buat sinkron.
7. **JANGAN bedain pesan/tampilan buat 404 "gak ada" vs "punya pelanggan
   lain"** — backend sengaja samain (anti-enumeration, `business-logic.md`
   §"Kepemilikan data"). Kalau Next.js bedain di sisi tampilan, itu ngebalik
   proteksi yang udah dibangun.
8. **JANGAN simpen nominal uang sebagai `number`/`float`** di state React —
   backend ngirim string desimal SENGAJA (`"150000.00"`) buat hindari
   floating-point error. Parse ke number CUMA pas mau format tampilan,
   jangan disimpen begitu.
9. **JANGAN bikin UI buat fitur yang endpoint-nya belum ada** — bikin
   tiket dari Portal, upload bukti bayar, gateway pembayaran/QRIS. Itu
   ditahan sengaja (§0 dokumen QR), backend-nya belum ada, UI-nya bakal
   manggil endpoint yang gak exist.
10. **JANGAN expose route/UI publik "lihat tagihan" tanpa `Bearer` token**
    di Portal ini — fitur "tamu lihat tagihan tanpa akun" itu **sudah ada**
    di app satunya (`/q1/.../tagihan`, gerbang QR publik), BUKAN tanggung
    jawab Portal. Portal ini cuma buat pelanggan yang UDAH punya akun.

## Pola arsitektur — BFF (Backend-for-Frontend), bukan SPA murni

```
Browser (client)
   │  cuma pernah ngomong ke domain Next.js sendiri
   ▼
Next.js Route Handlers  (app/api/.../route.ts)   ◄── "backend" versi Next.js
   │  - baca access_token dari cookie httpOnly
   │  - teruskan request ke Laravel + Bearer token
   │  - kalau 401 karena expired → refresh dulu, ulang sekali, baru nyerah
   ▼
Laravel API (repo ini)  /api/customer-portal/*
   │  - satu-satunya yang punya DATABASE
   │  - satu-satunya yang jalanin business logic
   ▼
MySQL (repo ini)
```

**Keputusan sadar, alasannya:**
- Token (`access_token`/`refresh_token`) **gak pernah nyentuh JS sisi client** —
  disimpen cookie `httpOnly` yang di-set Next.js Route Handler, bukan
  `localStorage`. Sekali ada celah XSS di Portal, token gak bisa dicuri lewat
  `document.cookie` atau `localStorage.getItem()` (data yang dilindungi:
  tagihan, saldo, PII pelanggan — proporsional dijaga seketat ini).
- Browser gak pernah manggil Laravel LANGSUNG — jadi **CORS gak relevan** dari
  sisi browser (`config/cors.php`/`PORTAL_ALLOWED_ORIGIN` di repo ini tetap
  ada buat jaga-jaga/panggilan server-to-server lain, tapi Portal versi BFF
  ini gak bergantung ke situ).
- Hop tambahan (Next.js → Laravel) HAMPIR GRATIS kalau dua server **satu
  infra/region yang sama** (rekomendasi: 1-5ms) — beda jauh dari hop
  Client→Next.js yang emang mahal di internet publik dan gak terhindarkan di
  arsitektur apa pun.
- SSR/Server Components bikin halaman nyampe browser UDAH ADA ISINYA (fetch
  jalan di server SEBELUM html dikirim) — bukan nunggu spinner client-side.

## Tech stack

- **Next.js 14/15 App Router** — Route Handlers (`app/api/**/route.ts`) sebagai
  BFF, Server Components buat halaman yang butuh data awal (SSR).
- **React + TypeScript** — tipe response API di-generate/ditulis manual dari
  kontrak `business-logic.md` §"Kontrak endpoint" (persis, bukan tebakan).
- **Tanpa database, tanpa ORM sisi Next.js** — satu-satunya sumber data
  Laravel API di repo ini. Kalau kepikiran nambah Prisma/Postgres di sisi
  Next.js, itu tandanya melenceng dari pola BFF (dua sumber kebenaran buat
  data yang sama — jangan).
- **Cookie session**: `iron-session` atau cookie signed manual (`jose` buat
  JWT-encode payload token ke cookie) — isi cookie: `access_token`,
  `refresh_token`, `expires_at`. `httpOnly`, `secure`, `sameSite: 'lax'`.

## Struktur folder yang disarankan

```
app/
  (portal)/                     ← route group, halaman ber-layout Portal
    login/page.tsx
    aktivasi/page.tsx           ← claim() — login_id + PIN + password baru
    dashboard/page.tsx          ← ringkasan: profil + tagihan jatuh tempo + saldo
    tagihan/page.tsx            ← GET /me/invoices (list, filter status/period)
    tagihan/[invoiceNumber]/page.tsx
    pembayaran/page.tsx         ← GET /me/payments
    pembayaran/[paymentNumber]/kwitansi/page.tsx
    saldo/page.tsx              ← GET /me/balance
    tiket/page.tsx              ← GET /me/tickets
    tiket/[ticketNumber]/page.tsx
    profil/page.tsx             ← GET /me + ganti password (PUT /me/password)
  api/
    auth/
      login/route.ts            → proxy POST /auth/login, set cookie
      claim/route.ts            → proxy POST /auth/claim, set cookie
      logout/route.ts           → proxy POST /auth/logout, hapus cookie
      logout-all/route.ts       → proxy POST /auth/logout-all, hapus cookie
    me/
      route.ts                  → proxy GET /me
      password/route.ts         → proxy PUT /me/password
      invoices/route.ts         → proxy GET /me/invoices
      invoices/[invoiceNumber]/route.ts
      payments/route.ts         → proxy GET /me/payments
      payments/[paymentNumber]/receipt/route.ts
      balance/route.ts          → proxy GET /me/balance
      tickets/route.ts          → proxy GET /me/tickets
      tickets/[ticketNumber]/route.ts
lib/
  laravel-client.ts             ← satu fetch wrapper, nempel X-Portal-Client
                                    + Bearer, urus refresh-on-401 di SATU tempat
  session.ts                    ← baca/tulis cookie sesi (get/set/clear token)
  types/portal-api.ts           ← tipe TypeScript persis kontrak business-logic.md
```

**Satu prinsip penting:** SEMUA panggilan ke Laravel lewat `lib/laravel-client.ts`
SATU pintu — jangan tiap Route Handler `fetch()` manual sendiri-sendiri. Logic
"nempelin `X-Portal-Client`", "nempelin Bearer", dan "kalau 401 refresh dulu,
ulang sekali" WAJIB satu tempat, biar gak nyebar & gampang audit (pola sama
kayak `CustomerQrTokenService` di repo ini — "satu-satunya tempat" buat hal
sensitif, konsisten sama filosofi CLAUDE.md).

### `lib/laravel-client.ts` — bentuk konkret, bukan cuma konsep

```ts
type ApiResult<T> =
  | { ok: true; status: number; data: T }
  | { ok: false; status: number; message: string };

async function callLaravel<T>(
  path: string,
  init: RequestInit & { auth?: boolean } = {},
): Promise<ApiResult<T>> {
  const headers = new Headers(init.headers);
  headers.set('X-Portal-Client', process.env.PORTAL_CLIENT_SECRET!);
  headers.set('Accept', 'application/json');

  if (init.auth) {
    const session = await getSession(); // lib/session.ts
    if (!session) return { ok: false, status: 401, message: 'Sesi tidak ada.' };
    headers.set('Authorization', `Bearer ${session.accessToken}`);
  }

  let res = await fetch(`${process.env.LARAVEL_API_URL}${path}`, { ...init, headers });

  // Refresh-on-401: CUMA dicoba kalau request ini pakai auth DAN gagalnya
  // gara-gara token (bukan gara-gara memang belum ada sesi di atas).
  if (res.status === 401 && init.auth) {
    const refreshed = await tryRefresh(); // panggil /auth/refresh, rotasi cookie
    if (refreshed) {
      headers.set('Authorization', `Bearer ${refreshed.accessToken}`);
      res = await fetch(`${process.env.LARAVEL_API_URL}${path}`, { ...init, headers });
    } else {
      await clearSession();
      return { ok: false, status: 401, message: 'Sesi tidak valid, silakan login ulang.' };
    }
  }

  const body = await res.json().catch(() => ({}));
  if (!res.ok) return { ok: false, status: res.status, message: body.message ?? 'Terjadi kesalahan.' };
  return { ok: true, status: res.status, data: body.data ?? body };
}
```

**Kenapa bentuknya `ApiResult` union, bukan `throw`:** status 401/404/409/422/423
dari Laravel itu SEMUA kondisi normal yang UI emang harus tampilin pesannya
(bukan bug/exception) — pola `throw` maksa tiap caller nulis `try/catch`
buat alur normal, gampang lupa. Route Handler tinggal:

```ts
const result = await callLaravel<Invoice>(`/me/invoices/${id}`, { auth: true });
if (!result.ok) return NextResponse.json({ message: result.message }, { status: result.status });
return NextResponse.json(result.data);
```

### `middleware.ts` — gerbang sebelum halaman ke-render

```ts
export function middleware(request: NextRequest) {
  const hasSession = request.cookies.has('portal_session');
  const isAuthPage = ['/login', '/aktivasi'].includes(request.nextUrl.pathname);

  if (!hasSession && !isAuthPage) {
    return NextResponse.redirect(new URL('/login', request.url));
  }
  if (hasSession && isAuthPage) {
    return NextResponse.redirect(new URL('/dashboard', request.url));
  }
  return NextResponse.next();
}

export const config = { matcher: ['/((?!api|_next/static|_next/image|favicon.ico).*)'] };
```

Middleware ini CUMA cek cookie ADA/GAK ADA (murah, jalan di Edge) — validasi
"token-nya masih valid beneran" tetap kerjaan `laravel-client.ts` pas
halaman/Route Handler beneran manggil Laravel. Jangan taruh logic decode/
verifikasi token di middleware — mahal & duplikasi kerjaan yang udah dijamin
Laravel sisi server.

## Auth & session — alur konkret

**Aktivasi akun (`claim`):**
1. Pelanggan isi form `/aktivasi`: `login_id`, `pin`, `new_password` (dari kartu
   fisik yang staf cetak, `docs/plan/qr-code/`).
2. Client POST ke Route Handler `app/api/auth/claim/route.ts`.
3. Route Handler proxy ke `POST {LARAVEL_API_URL}/api/customer-portal/auth/claim`
   + header `X-Portal-Client: <secret>` (secret ini **cuma di env server
   Next.js**, TIDAK PERNAH dikirim ke client).
4. Sukses (200) → Route Handler `set-cookie` (httpOnly) isi
   `access_token`/`refresh_token`/`expires_at`, redirect ke `/dashboard`.
5. Gagal → teruskan status+message APA ADANYA ke client (401/409/422/423, lihat
   tabel error di `business-logic.md` §"Error umum") — jangan diterjemahkan
   ulang, pesannya udah final dari Laravel.

**Login normal:** sama polanya, `app/api/auth/login/route.ts` →
`POST /auth/login`.

**Tiap panggilan `/me/*`:**
1. Server Component/Route Handler baca `access_token` dari cookie.
2. Panggil Laravel + `Authorization: Bearer <access_token>`.
3. Kalau balik 401 (token expired) → panggil `POST /auth/refresh` pakai
   `refresh_token` dari cookie, **rotasi** (Laravel balikin pasangan token
   BARU, refresh_token lama otomatis mati) → update cookie → ULANG request
   asli SEKALI. Kalau refresh JUGA gagal (401 — reuse/expired) → hapus cookie,
   redirect `/login`.

**Logout:** `POST /auth/logout` (atau `logout-all` — efeknya sama persis,
lihat business-logic.md) lewat Route Handler, lalu hapus cookie sisi Next.js.

## Peta Route Handler ↔ endpoint Laravel

| Route Handler (Next.js) | Method | Endpoint Laravel | Butuh Bearer? |
|---|---|---|---|
| `/api/auth/login` | POST | `/auth/login` | Tidak |
| `/api/auth/claim` | POST | `/auth/claim` | Tidak |
| `/api/auth/logout` | POST | `/auth/logout` | Ya |
| `/api/auth/logout-all` | POST | `/auth/logout-all` | Ya |
| `/api/me` | GET | `/me` | Ya |
| `/api/me/password` | PUT | `/me/password` | Ya |
| `/api/me/invoices` | GET | `/me/invoices` | Ya |
| `/api/me/invoices/[invoiceNumber]` | GET | `/me/invoices/{invoice_number}` | Ya |
| `/api/me/payments` | GET | `/me/payments` | Ya |
| `/api/me/payments/[paymentNumber]/receipt` | GET | `/me/payments/{payment_number}/receipt` | Ya |
| `/api/me/balance` | GET | `/me/balance` | Ya |
| `/api/me/tickets` | GET | `/me/tickets` | Ya |
| `/api/me/tickets/[ticketNumber]` | GET | `/me/tickets/{ticket_number}` | Ya |

Refresh (`/auth/refresh`) **gak butuh Route Handler publik sendiri** — dipanggil
INTERNAL oleh `lib/laravel-client.ts` pas nemu 401, gak pernah diekspos jadi
endpoint yang client React manggil langsung.

## Kontrak tipe data — jangan tebak, kutip dari business-logic.md

Semua bentuk response (nama field, tipe, kapan null) udah final &
terverifikasi dari kode yang jalan (`business-logic.md` §"Kontrak endpoint —
request & response", ditulis "PERSIS dari kode yang jalan ... bukan
rancangan"). Yang perlu diinget pas nulis TypeScript:

- **Semua nominal uang string desimal** (`"150000.00"`), BUKAN number/float —
  `PortalMoneyIsDecimalStringTest` di repo ini yang jagain ini di sisi
  backend. Parse ke number cuma pas mau format tampilan, jangan simpen state
  sebagai float (floating point error).
- **Envelope beda per jenis endpoint**: `/me/*` data-resource →
  `{data, meta}`; `/auth/*` dan `PUT /me/password` → objek flat langsung
  (`{access_token,...}` atau `{message}`). Jangan disamain paksa.
- **Status selalu objek `{value, label}`** (invoice/payment/ticket) — render
  `label` ke UI, pakai `value` buat logic (filter, badge warna), JANGAN
  hardcode ulang label Indonesia di sisi Next.js (kalau backend ubah teksnya,
  Next.js otomatis ikut, gak perlu sinkron manual).
- **404 dipakai buat "gak ada" DAN "punya orang lain"** sengaja disamain
  (anti-enumeration) — Next.js jangan coba bedain, tampilkan pesan generik
  "Data tidak ditemukan".
- Field yang SENGAJA gak pernah dikirim backend (nama staf, `pop_id`,
  `reject_reason` di pembayaran ditolak, dst — daftar lengkap di
  business-logic.md) **jangan diasumsikan ada** di tipe TypeScript.

## Halaman yang dibutuhkan (peta ke endpoint)

| Halaman | Endpoint dipanggil | Catatan |
|---|---|---|
| `/login` | `POST /auth/login` | — |
| `/aktivasi` | `POST /auth/claim` | Form: login_id, pin, new_password |
| `/dashboard` | `GET /me` + ringkasan tagihan/saldo | Bisa gabung 2-3 panggilan paralel di Server Component |
| `/tagihan` | `GET /me/invoices` | Filter `status`, `period`; paginasi 10/hal |
| `/tagihan/[nomor]` | `GET /me/invoices/{nomor}` | Termasuk `payments` yang menempel |
| `/pembayaran` | `GET /me/payments` | Filter `status`, `period` |
| `/pembayaran/[nomor]/kwitansi` | `GET /me/payments/{nomor}/receipt` | Layout cetak/PDF-friendly |
| `/saldo` | `GET /me/balance` | Mutasi dipaginasi |
| `/tiket` | `GET /me/tickets` | Tanpa filter |
| `/tiket/[nomor]` | `GET /me/tickets/{nomor}` | Status `{value,label}` |
| `/profil` | `GET /me`, `PUT /me/password` | Ganti password (`current_password`+`new_password`) |

**Yang SENGAJA belum ada endpoint-nya** (jangan dibikin UI-nya dulu): bikin
tiket dari Portal, upload bukti bayar, gateway pembayaran/QRIS — semua ini
"ditahan" per keputusan dokumen QR (§0) & rencana API, di luar Fase 0-4 yang
udah selesai.

## Environment variables (sisi Next.js, SERVER-ONLY)

```
LARAVEL_API_URL=http://laravel-app:8000/api/customer-portal   # internal, satu infra
PORTAL_CLIENT_SECRET=<sama dengan PORTAL_CLIENT_SECRET Laravel>
SESSION_COOKIE_SECRET=<buat encode/sign cookie sesi, generate sendiri>
```

`PORTAL_CLIENT_SECRET` **JANGAN PERNAH** diprefix `NEXT_PUBLIC_` — itu bikin
Next.js nge-bundle-nya ke JS client, sama fatalnya kayak nulis password di
HTML. Semua env di atas cuma boleh dibaca dari Route Handler/Server
Component, gak pernah dari Client Component.

## Deployment — biar hop Next.js↔Laravel murah

- Taruh container/instance Next.js dan Laravel **satu Docker network / satu
  VPC / satu region** — target latency internal <5ms.
  `docker-compose.yml` repo ini udah punya network `app`/`assets` dst, kalau
  Next.js dijalanin di infra yang sama, tinggal nambah service baru di
  compose yang sama & panggil `http://app:8000` (nama service Laravel),
  BUKAN lewat domain publik/tunnel.
- Kalau infra beda provider — pastiin sama region minimal, dan connection
  pooling/keep-alive aktif di `lib/laravel-client.ts` (`fetch` dengan agent
  yang reuse koneksi, atau `undici` custom dispatcher) biar gak nego
  TCP+TLS tiap request.
- CORS (`PORTAL_ALLOWED_ORIGIN` di `config/cors.php` repo ini) TETAP diisi
  domain Portal produksi buat jaga-jaga (kalau suatu saat ada panggilan
  client-side langsung, misal webhook/health-check tooling) — tapi jalur
  utama Portal (Route Handler) gak lewat CORS sama sekali.

## Checklist mulai bangun

1. `lib/laravel-client.ts` — fetch wrapper + refresh-on-401, SATU tempat.
2. `lib/session.ts` — cookie get/set/clear.
3. Route Handlers `auth/login`, `auth/claim` dulu (jalur masuk).
4. Halaman `/login`, `/aktivasi`.
5. Middleware Next.js (`middleware.ts`) — cek cookie ada, redirect `/login`
   kalau nembak halaman `/dashboard` dst tanpa sesi.
6. Baru lanjut Route Handler+halaman data (`/me/*`) satu-satu ngikutin tabel
   di atas.
7. Test manual tiap endpoint pakai kredensial dev sungguhan (bukan cuma mock)
   — pola yang sama dipakai sesi ini pas verifikasi backend: `cek beneran`,
   bukan asumsi kontrak cocok.
