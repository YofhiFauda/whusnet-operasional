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

## Konvensi penyajian data — GLOBAL, berlaku semua halaman

- **Uang**: parse string desimal (`"150000.00"`) → format `Rp 150.000` (locale
  `id-ID`, `Intl.NumberFormat('id-ID', {style:'currency', currency:'IDR',
  maximumFractionDigits:0})`). JANGAN simpen hasil parse sebagai state,
  format on-render doang dari string aslinya.
- **Tanggal**: ISO-8601 dari backend (`"2026-08-20T08:00:00+07:00"`) → format
  lokal Indonesia (`20 Agustus 2026`, atau `20 Agt 2026` di tempat sempit
  kayak tabel) pakai `Intl.DateTimeFormat('id-ID', {...})` atau `dayjs`
  locale `id`. Konsisten satu cara format di seluruh app, jangan campur.
- **Status → badge warna** (dari `{value, label}`, warna berdasarkan `value`,
  teks dari `label`):
  | `value` (invoice) | Warna | `value` (payment) | Warna | `value` (tiket) | Warna |
  |---|---|---|---|---|---|
  | `lunas` | Hijau | `valid` | Hijau | `selesai` | Hijau |
  | `sebagian` | Kuning | `ditolak` | Abu (bukan merah — pesan `label` udah "belum terverifikasi") | `sedang_ditangani` | Biru |
  | `belum_dibayar` | Merah | — | — | `diterima` | Kuning |
  | `batal` | Abu | — | — | `dibatalkan` | Abu |
- **Loading**: skeleton (blok abu-abu placeholder bentuk kartu/baris tabel),
  BUKAN spinner polos di tengah layar — biar layout gak "lompat" pas data
  masuk.
- **Empty state**: ikon + 1 kalimat spesifik per konteks ("Belum ada
  tagihan", bukan "Tidak ada data" generik di semua tempat).
- **Error state** (network gagal / Laravel down, BEDA dari 401/404/422 yang
  emang respons valid): banner "Tidak bisa terhubung ke server, coba lagi" +
  tombol retry — jangan biarin halaman putih kosong.
- **Responsive mobile-first**: pelanggan kemungkinan besar akses dari HP
  (sama kayak asumsi `capture="environment"` di form foto staf, app ini).
  List di HP = kartu bertumpuk, BUKAN tabel di-scroll horizontal.

## Komponen shared — layout, navbar, sidebar

Dua kelompok halaman butuh **layout beda total** — jangan dipaksa satu
`layout.tsx` buat semuanya:

### Layout tanpa nav (`/login`, `/aktivasi`)
- Card tunggal di tengah layar, gak ada sidebar/navbar/menu apa pun.
- Cuma logo/nama usaha di atas card + form. Pelanggan yang belum login gak
  perlu (dan gak boleh) liat menu ke halaman yang butuh auth.
- Route Handler + `middleware.ts` yang jamin dua halaman ini gak numpang
  layout halaman berauth (lihat bagian "Auth & session" — grup route
  terpisah, misal `app/(auth)/login/page.tsx` vs `app/(portal)/dashboard/page.tsx`).

### Layout halaman berauth (`/dashboard`, `/tagihan`, dst)
- **Desktop (≥768px)**: sidebar kiri tetap (fixed), konten di kanan.
- **Mobile (<768px)**: sidebar HILANG, diganti bottom nav bar (ikon+label,
  4-5 item paling sering dipakai) ATAU hamburger drawer dari navbar atas —
  pilih SATU pola, jangan dua-duanya sekaligus (bikin bingung).

**Sidebar** — isi menu (urutan sesuai frekuensi pemakaian, bukan abjad):
```
Dashboard
Tagihan
Pembayaran
Saldo
Tiket
─────────────
Profil
Keluar (logout)
```
Item aktif (halaman yang lagi dibuka) dikasih highlight — pola umum: bandingin
`usePathname()` ke href tiap link, styling beda kalau cocok.

**Navbar atas** (tampil di SEMUA ukuran layar, isi beda dikit):
- Kiri: nama usaha/logo (dan hamburger toggle kalau mobile pilih pola drawer)
- Kanan: nama pelanggan (`full_name` dari `GET /me`, TARUH di layout server
  component sekali, jangan tiap halaman manggil `/me` ulang cuma buat nama)
  + tombol Keluar

**Requirement yang perlu disepakati sebelum bangun** (bukan cuma teknis,
keputusan produk):
- Apakah "Keluar" di navbar = `logout` (sesi ini doang) atau `logout-all`
  (semua perangkat)? Backend nyediain dua-duanya, efeknya SAMA PERSIS
  sekarang (`business-logic.md` — keduanya cabut semua token), jadi gak ada
  bedanya teknis pilih yang mana — tapi kalau nanti dibedain di backend,
  tombol ini harus jelas maksudnya yang mana dari awal.
- Notifikasi (lonceng ikon dsb) — **BELUM ada sumber datanya** (lihat bagian
  "Yang SENGAJA belum ada endpoint-nya" — webhook realtime belum tentu
  kepasang penerimanya). Jangan taruh ikon lonceng di navbar kalau belum
  ada yang ngisi datanya — UI kosong yang keliatan "rusak"/belum jadi lebih
  buruk daripada gak ada sama sekali.

### Komponen reusable lain yang bakal kepakai di banyak halaman
- `<StatusBadge value label />` — satu komponen, terima `{value, label}`
  langsung dari response API, mapping warna internal (tabel warna di bagian
  "Konvensi penyajian data" di atas). Dipakai tagihan/pembayaran/tiket —
  JANGAN bikin 3 badge terpisah yang mirip-mirip.
- `<Pagination meta onPageChange />` — satu komponen buat semua list
  (tagihan/pembayaran/saldo-mutasi), baca `meta` paginasi dari Laravel
  Resource apa adanya, jangan hitung ulang total halaman manual.
- `<EmptyState icon text />`, `<ErrorBanner message onRetry />`,
  `<SkeletonRows count />` — dipakai berulang di tiap halaman list/detail
  (lihat "Konvensi penyajian data" § loading/empty/error).
- `<MoneyDisplay value />`, `<DateDisplay value format="long|short" />` —
  bungkus format uang/tanggal (lihat konvensi global) jadi komponen, BUKAN
  fungsi util yang dipanggil manual tiap tempat — kalau formatnya berubah,
  cukup ubah satu komponen.

## Halaman yang dibutuhkan — spek per halaman

### `/login`
- **Data & sumber**: `POST /auth/login`.
- **Tampilan**: form 2 field (`login_id`, `password`), tombol submit,
  link ke `/aktivasi` ("Belum punya akun? Aktivasi di sini").
- **State**: submit disabled+spinner pas loading; error 401/423/429 tampil
  SATU banner generik di atas form (JANGAN highlight field mana yang
  "salah" — pesannya emang sengaja gak dibedain, ikutin itu di UI juga).
- **Requirement terbuka**: **belum ada endpoint "lupa password"** di backend
  (Fase 0-4 gak mencakup ini) — kalau Portal butuh link "Lupa Password",
  endpoint-nya harus diminta dibangun dulu di repo Laravel, jangan
  diasumsikan ada.

### `/aktivasi`
- **Data & sumber**: `POST /auth/claim`.
- **Tampilan**: form `login_id`, `pin` (input numeric 6 digit,
  `inputMode="numeric"` `maxLength={6}`, idealnya 6 kotak terpisah gaya OTP
  biar jelas ini beda dari password), `new_password` + `confirm_password`
  (konfirmasi CUMA validasi sisi client, backend cuma terima `new_password`
  tunggal).
- **State**: 401 (generik, sama kayak login) · 409 ("Akun ini sudah pernah
  diaktivasi" + tombol ke `/login`, BUKAN retry form yang sama) · 422
  (list error per-field dari `errors.new_password`) · 423 ("PIN terkunci
  sementara, coba lagi nanti" — TANPA hitung mundur presisi, backend gak
  ngirim `retry_after` di endpoint ini).
- **Requirement terbuka**: sama kayak login — kalau salah PIN berkali-kali
  dan pelanggan gak bisa akses "lupa PIN", satu-satunya jalan sekarang
  MINTA STAF reset PIN dari halaman `customers/{id}/qr` (app INI) — Portal
  gak punya cara mandiri buat itu, perlu dicantumin di pesan UI ("hubungi
  admin/CS" kalau lockout).

### `/dashboard`
- **Data & sumber**: `GET /me` + `GET /me/invoices?status=belum_dibayar`
  (ambil buat kartu "tagihan jatuh tempo") + `GET /me/balance` (ambil field
  `balance` doang). Panggil paralel (`Promise.all`) di Server Component,
  bukan berurutan.
- **Tampilan**: kartu profil ringkas (nama, status, paket), kartu tagihan
  terdekat (kalau ada yang belum lunas — highlight due_date), kartu saldo,
  shortcut ke `/tagihan` `/tiket`.
- **Requirement terbuka**: `/me/invoices` gak punya param "urutkan by
  jatuh tempo terdekat" — endpoint cuma difilter `status`/`period`,
  sortnya default backend. Kalau butuh "tagihan PALING deket jatuh tempo"
  presisi, frontend WAJIB sort ulang di sisi client dari hasil yang
  kebaca, jangan asumsi item pertama array udah yang paling dekat.

### `/tagihan` (list)
- **Data & sumber**: `GET /me/invoices?status=..&period=..`, paginasi
  10/halaman (dari Laravel, ikutin `meta`/link paginasi bawaan Resource).
- **Tampilan**: tabel (desktop) — kolom No. Tagihan, Periode, Jatuh Tempo,
  Total, Sisa, Status (badge); kartu bertumpuk (mobile) — No.Tagihan+badge
  di atas, Total+Jatuh Tempo di bawah.
- **Interaksi**: filter dropdown `status` (opsi: semua/lunas/sebagian/
  belum_dibayar/batal), filter `period` (bulan-tahun, `<input type="month">`
  cocok buat format `YYYY-MM` yang backend minta). Kontrol paginasi
  prev/next + nomor halaman.
- **State**: skeleton 5 baris pas loading; empty "Belum ada tagihan".

### `/tagihan/[nomor]` (detail)
- **Data & sumber**: `GET /me/invoices/{nomor}` — includes `payments`
  (array) yang menempel.
- **Tampilan**: header (No.Tagihan, badge status, Total/Dibayar/Sisa 3
  angka besar berdampingan), tabel kecil daftar pembayaran yang udah masuk
  ke tagihan ini (nomor pembayaran, tanggal, jumlah, status).
- **State**: 404 → halaman "Tagihan tidak ditemukan" generik (JANGAN bilang
  "atau ini bukan tagihan Anda" — itu ngebocorin info, ikutin sikap
  anti-enumeration backend).

### `/pembayaran` (list)
- **Data & sumber**: `GET /me/payments?status=..&period=..`.
- **Tampilan**: mirip tagihan — kolom No.Pembayaran, Tanggal, Metode,
  Jumlah, Lebih Bayar (cuma tampil kalau `overpay_amount` > 0), Status.
- **Aturan KHUSUS status `ditolak`**: `label` udah "belum terverifikasi —
  hubungi admin" dari backend (BUKAN "Ditolak" mentah) — pakai `label`
  apa adanya, JANGAN tulis ulang jadi "Gagal"/"Ditolak" sendiri. `has_receipt:
  false` buat baris ini → tombol "Lihat Kwitansi" HARUS disembunyikan,
  bukan cuma disabled.
- **State**: sama pola tagihan (skeleton/empty/pagination).

### `/pembayaran/[nomor]/kwitansi`
- **Data & sumber**: `GET /me/payments/{nomor}/receipt`.
- **Tampilan**: layout TERPISAH dari halaman lain — print-friendly (mirip
  `print.blade.php` app ini): kop nama usaha, info pelanggan (nama, cid,
  hp, alamat), ringkasan invoice terkait (nomor, periode, paket, total,
  sisa), jumlah dibayar besar, lebih-bayar (kalau ada), tombol "Cetak"
  (`window.print()`) yang disembunyikan di `@media print`.
- **Field yang TIDAK PERNAH ada** (jangan bikin slot buat ini): `penerima`,
  `penagih`, `catatan` — backend sengaja buang, jangan render `undefined`.

### `/saldo`
- **Data & sumber**: `GET /me/balance` — `balance` (angka tunggal) +
  `mutations` (array, dipaginasi 10/halaman).
- **Tampilan**: angka saldo besar di atas (format Rp), tabel/list mutasi di
  bawah: tanggal, badge tipe (`credit`→"Masuk" hijau, `debit`→"Keluar"
  merah), jumlah, catatan (`note`, bisa kosong).
- **State**: empty "Belum ada mutasi saldo".

### `/tiket` (list)
- **Data & sumber**: `GET /me/tickets` — **TANPA filter/query param sama
  sekali** (beda dari tagihan/pembayaran, dokumen sengaja gak sebut filter
  buat ini).
- **Tampilan**: list — No.Tiket, Kategori Keluhan (`issue_category`),
  tanggal dibuat, badge status.
- **Requirement terbuka**: kalau nanti dibutuhkan filter/cari tiket, itu
  perlu backend ditambah dulu (query param baru) — jangan bikin filter
  UI yang manggil param yang gak dikenal backend (bakal diabaikan diam-diam
  atau 422, tergantung validasi Laravel).

### `/tiket/[nomor]` (detail)
- **Data & sumber**: `GET /me/tickets/{nomor}`.
- **Tampilan**: kategori, `detail_keluhan` (teks panjang), badge status,
  `resolved_at` (tampilin kalau ada, sembunyikan kalau `null`).
- **Field yang TIDAK PERNAH ada**: riwayat/log tiket mentah, `catatan_teknis`,
  nama pegawai yang nanganin, `handler`/nomor TFOP/TASK internal — jangan
  bikin section "Riwayat" yang nunggu data yang emang gak dikirim.

### `/profil`
- **Data & sumber**: `GET /me` (tampil) + `PUT /me/password` (form ganti).
- **Tampilan**: info read-only (login_id, nama, status, paket, desa,
  kecamatan, tanggal klaim), form terpisah di bawahnya: `current_password`,
  `new_password`, `confirm_password` (client-only match check).
- **Requirement UX penting**: sukses ganti password → tampilkan pesan
  eksplisit **"Sesi Anda di perangkat lain otomatis keluar"** (efek nyata
  `PUT /me/password` — semua token LAIN dicabut, sesi yang manggil ini
  tetap hidup) — jangan biarin pelanggan kaget kenapa HP lain ke-logout
  sendiri tanpa penjelasan.

## Yang SENGAJA belum ada endpoint-nya (jangan dibikin UI-nya dulu)

- Bikin tiket dari Portal (cuma bisa lihat riwayat, belum bisa buat baru)
- Upload bukti bayar
- Gateway pembayaran/QRIS (bayar tetap manual transfer + lapor ke staf)
- "Lupa password" mandiri (harus lewat CS/staf sampai ada endpoint-nya)
- "Lupa PIN" mandiri (sama, lewat staf — reset PIN di app Operasional)
- Filter/pencarian di halaman tiket
- Notifikasi push/realtime (webhook `invoice.updated` ke Portal sudah ada
  di sisi backend — `PORTAL_WEBHOOK_URL`/`_SECRET`, arah OUTBOUND — tapi
  Portal BELUM tentu punya endpoint penerima. Kalau mau notifikasi
  realtime beneran, itu pekerjaan TERPISAH: bikin endpoint penerima webhook
  di Next.js + tentuin cara nyampein ke browser pelanggan (WebSocket/SSE/
  polling), bukan otomatis kepakai cuma dari backend udah nge-`send`)

Semua di atas "ditahan" per keputusan dokumen QR (§0) & rencana API — di
luar Fase 0-4 yang udah selesai. Kalau requirement Portal butuh salah satu
ini, itu **pekerjaan backend baru** dulu (repo ini), bukan sesuatu yang bisa
disiasati murni di sisi Next.js.

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
