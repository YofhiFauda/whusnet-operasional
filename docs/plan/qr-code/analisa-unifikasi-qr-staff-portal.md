# Analisa: Unifikasi QR Staff ↔ Portal Pelanggan

**Status:** **Fase 1 (Operasional) SUDAH dikerjakan** — lihat ADHOC-52 di `docs/TASKS.md`. Fase 2 (API kolektor + Portal `portal-pelanggan` Next.js) BELUM.

**Tanggal:** 2026-08-29
**Revisi:** 2026-08-29 — §1.4 (permission RBAC terpisah per channel) & constraint token-scoped di §2 ditambah dari diskusi lanjutan user.
**Revisi:** 2026-08-29 (eksekusi) — user approve ("kurang lebih setuju, kerjakan"), 4 keputusan §6 dikunci lewat `AskUserQuestion`, Fase 1 diimplementasikan: migrasi `staff_portal_tokens`, `StaffPortalToken`+`StaffPortalTokenService`, middleware `portal_staff_token`, permission `tickets.qr.create`/`kolektor.qr.pay`, `QrScanController` staf/kolektor branch redirect ke Portal, endpoint `POST /api/customer-portal/tickets` + dedup guard `TicketService::create()`. Detail di §7 (baru).
**Konteks:** diskusi lisan user soal miskomunikasi arah QR staff (ticketing, kolektor) — saat ini scan QR oleh staff/kolektor tetap **redirect ke sistem Operasional internal**, bukan ke Portal, padahal maksud awal satu QR = satu pintu (Operasional utk fungsi fisik, Portal utk fungsi non-fisik pelanggan+staff).
**Rujukan:** `docs/plan/qr-code/rancangan-qr-pelanggan-final.md` (rancangan awal QR), `docs/api/api-portal-pelanggan/business-logic.md`.

---

## 0. Ringkasan masalah

Maksud user: pelanggan & staff pakai **1 QR yang sama**. Staff scan → bisa kirim ticketing, cek tagihan pelanggan, bantu pembayaran via kolektor — semua **lewat Portal**, bukan nyelonong ke Operasional. Alasan: Portal jadi satu pintu konsisten, Operasional tetap satu-satunya otak business logic (gak boleh dobel logic, lihat `CLAUDE.md` § Service layer).

**Bukti kondisi kode saat ini** (`app/Http/Controllers/QrScanController.php::dispatch()`):

| Siapa scan | Redirect ke | Sesuai maksud? |
|---|---|---|
| Guest (belum login) | Portal (`{portal}/klaim?code=...`) | ✅ sesuai |
| Kolektor (`kolektor.pay` + ada di worklist) | `collector-worklist.index` — **internal Operasional** | ❌ harusnya Portal |
| Staff (`tickets.create`) | `qr.ticket.create` — **internal Operasional** | ❌ harusnya Portal |
| Staff tanpa `tickets.create` | `customers.show` — internal | (fallback, di luar scope diskusi ini) |

Jadi cabang guest sudah benar arahnya (Portal), tapi cabang staff/kolektor masih lompat balik ke Operasional — ini akar "misskomunikasi" yang dimaksud user.

Portal (`portal-pelanggan`) sendiri baru punya:
- `(portal)/tiket` — **baca** riwayat tiket pelanggan (`GET /api/customer-portal/me/tickets*`). Belum ada endpoint **create** tiket.
- Tidak ada apa pun terkait kolektor sama sekali (tidak ada folder `(portal)/kolektor`, tidak ada `/api/customer-portal/kolektor*`).

Jadi dua API yang didiskusikan di bawah (`customer-portal/tickets` create, `customer-portal/kolektor`) memang **belum ada** — ini rancangan baru, bukan koreksi endpoint yang salah pasang.

---

## 1. Tiga poin analisa

### 1.1 Staff ticketing pindah ke Portal, logic tetap di Operasional

Ubah cabang `tickets.create` di `QrScanController::dispatch()`: staff scan → redirect Portal (pola sama seperti cabang guest), bukan `qr.ticket.create` internal.

Portal render form submit tiket → panggil endpoint baru `POST /api/customer-portal/tickets` di Operasional. Endpoint ini **tipis**, delegasi penuh ke `TicketService::create()` yang sudah ada — **jangan** tulis ulang logic tiket di Next.js. Ini konsisten dengan larangan CLAUDE.md: business logic cuma boleh satu tempat.

Konsekuensi: dua jalur create tiket akan hidup berdampingan —
- `/tickets/create` (web, session Laravel, dipakai staf yang kerja dari dashboard Operasional langsung, **bukan** dari scan QR)
- `POST /api/customer-portal/tickets` (API, dipanggil Portal, dipakai staf yang masuk lewat scan QR)

Keduanya **wajib** berujung ke `TicketService::create()` yang sama. Kalau nanti butuh field yang beda antara dua jalur ini, tambahkan parameter opsional di service — jangan fork logic-nya.

### 1.2 Validasi/dedup — biar antrian Helpdesk gak membanjir

Akar masalah yang disebut user: submit tiket dari QR langsung tembus `TicketService::create()` tanpa gate, jadi staf yang scan berulang (atau lupa udah pernah lapor) numpuk tiket duplikat di Helpdesk.

**Bukan** solusi berupa "butuh approval sebelum masuk antrian Helpdesk" — itu nambah role manual approve baru, malah bikin proses lebih lambat, dan gak ada di struktur RBAC sekarang (lihat larangan bikin role baru sembarangan).

**Solusi yang diusulkan — dedup guard di titik masuk, bukan approval layer baru:**

1. Sebelum form submit ditampilkan, Portal panggil endpoint baca dulu (bisa pakai `GET /api/customer-portal/me/tickets` yang sudah ada, atau varian scoped by customer utk staff) → cek ada tiket **open** (`handler != fop` atau `status = open`, sama definisi `holderRoles()`/`TicketHandlingStatus`).
2. Kalau ada tiket open: tampilkan status tiket itu dulu (nomor, handler saat ini, ringkas catatan) — **bukan** langsung blokir total (kadang memang butuh tiket kedua utk masalah beda).
3. User pilih eksplisit: "lihat tiket yang sudah ada" ATAU centang konfirmasi + isi alasan singkat → baru `POST /api/customer-portal/tickets` jalan.
4. Server-side, endpoint create tetap boleh terima permintaan tanpa gate ini (Portal cuma UI gate, bukan satu-satunya pertahanan) — tapi kalau mau extra aman, `TicketService::create()` bisa terima flag `confirmed_duplicate: bool` dan menolak 409 kalau ada tiket open + flag ini `false`. Ini pola sama seperti unique-index guard di invoice (`add_duplicate_guard_indexes_to_invoices_and_payments`), cuma di level aplikasi karena "tiket duplikat" gak sesederhana constraint DB (masih boleh legit dalam kasus tertentu).

Ini murah, langsung nutup akar masalah (orang submit baru krn gak tau udah ada), dan gak nambah birokrasi approval.

### 1.3 Kolektor — pola sama, sisi pembayaran

Cabang kolektor **sudah benar secara logic** (worklist check, POP scope, 403 tegas di luar worklist) — cuma **arahnya** yang salah, sama kayak ticketing: harusnya ke Portal, bukan `collector-worklist.index` internal.

Bedanya dengan staff ticketing: kolektor **pegang uang fisik**, jadi walau UI-nya pindah ke Portal, validasi "kolektor ini boleh nyentuh pelanggan ini" **tetap** harus dicek ulang di endpoint Operasional (jangan percaya keputusan yang sudah dibuat `QrScanController`, itu cuma nentuin arah redirect — bukan otorisasi final). Ini sama prinsipnya dengan §6.3 rancangan awal QR soal absen teknisi: QR sendirian gak membuktikan apa-apa, keputusan tetap harus dicek ulang di titik tulis.

### 1.4 Permission RBAC terpisah per channel (dashboard vs QR-Portal)

Pertanyaan user (2026-08-29): bukankah create tiket dari dashboard Operasional dan create tiket dari QR-Portal butuh **permission RBAC berbeda**, biar staf lain gak bisa "mengacak" salah satu jalur cuma karena punya akses ke yang lain?

**Jawaban: ya, dan pola ini sudah ada preseden di repo** — `QrFeatureSeeder` bikin root feature `qr_scan` **terpisah** dari `tickets`/`customers`, bukan numpang ke permission generik. Ini konsisten dengan aturan yang sudah tertulis di `CLAUDE.md`: *"Tiap halaman Ticketing punya permission sendiri — jangan tambah halaman baru yang numpang `tickets.view` generik."* Alasan yang sama berlaku di sini, malah lebih kuat: channel QR-Portal pakai **token one-shot** (§4), risikonya beda dari session penuh dashboard — token QR lebih gampang nyasar/kebobol saat pemindaian di lapangan dibanding sesi login biasa.

Usulan konkret, generate lewat `PermissionGeneratorService` (bukan hardcode string):

| Channel | Feature | Permission |
|---|---|---|
| Dashboard Operasional (existing) | `tickets` | `tickets.create` |
| QR → Portal (baru) | sub-feature baru `tickets.qr` (pararel sama `customers.qr` yang sudah ada) | `tickets.qr.create` |
| Kolektor dashboard (existing) | `kolektor` | `kolektor.pay` |
| QR → Portal (baru) | sub-feature baru `kolektor.qr` | `kolektor.qr.pay` |

Endpoint `POST /api/customer-portal/tickets` cek `tickets.qr.create`, **bukan** `tickets.create` — staf yang cuma punya akses dashboard biasa (tanpa `tickets.qr.create`) tetap 403 walau dia "boleh bikin tiket" secara umum. Admin RBAC bisa kombinasikan bebas: teknisi lapangan cuma dikasih `tickets.qr.create` (gak punya akses dashboard sama sekali), staf Helpdesk kantor cuma dikasih `tickets.create` (gak pernah pegang QR scanner). Ini **bukan** role per cabang (tetap dilarang) — cuma permission granular per channel, assignment tetap lewat matrix role seperti biasa.

---

## 2. Arsitektur API `customer-portal/tickets` (create)

Pertanyaan user (2026-08-29): Ticketing & Kolektor dari Portal harus dibuatkan API tersendiri, biar gak bersinggungan langsung dengan Operasional. **Jawaban: ya, dan itu memang bentuk §2/§3 di bawah ini** — keduanya murni REST endpoint di bawah prefix `/api/customer-portal/` yang sudah ada, **bukan** Portal memanggil route web/session Blade Operasional. Portal tidak pernah punya akses ke `web.php`, cookie session, atau CSRF token Operasional — satu-satunya permukaan yang Portal sentuh adalah endpoint API ini, sama seperti pola `me/tickets`, `me/invoices` yang sudah jalan.

Constraint tambahan yang wajib eksplisit (bukan cuma "asal API"): token staf (`portal_staff_token`, §4) **wajib scoped**, bukan setara cookie sesi Operasional —
- hanya valid dipanggil ke path `/api/customer-portal/*`, ditolak kalau dicoba ke route lain;
- TTL pendek (mengikuti pola `CustomerPortalToken` access token, 15 menit — lihat `PortalAuthService::ACCESS_TTL_MINUTES`), bukan token panjang umur;
- **setiap request tetap divalidasi ulang** lewat `EffectiveAccessService` (permission + POP scope) — token yang berhasil ditukar TIDAK berarti "sudah dipercaya", cuma bukti identitas staf itu siapa. Otorisasi aslinya tetap dicek per-panggilan, sama seperti keputusan redirect `QrScanController` bukan otorisasi final (§1.3).

Kalau token ini bocor (disadap dari URL redirect, device kolektor hilang), blast radius-nya dibatasi ke: 1 endpoint API tertentu, 1 staf, ≤15 menit — bukan seluruh akses dashboard Operasional staf itu.

```
POST /api/customer-portal/tickets
Middleware: portal_client + portal_staff_token (baru, lihat §4) + throttle:customer-portal-api
Body: { customer_qr_code: string, kategori, deskripsi, lampiran? }
```

Alur:
1. Resolve `customer_qr_code` → `CustomerQrTokenService::resolve()` (sama seperti `QrScanController`), dapat `customer_id`.
2. Cek permission `tickets.qr.create` (§1.4) + POP scope staf pemanggil (`EffectiveAccessService::getAllowedPopIds()`), sama seperti guard di `QrScanController::dispatch()` baris 82 — **jangan** asumsikan Portal sudah filter ini di sisi client.
3. Dedup check §1.2 (kalau diputuskan server-side jadi hard guard, bukan cuma UI gate).
4. `TicketService::create()` — path yang sama persis dengan create tiket dari dashboard Operasional. **Tidak** bikin `FopTask` (sesuai aturan sync Ticket↔FopTask yang sudah ada — FopTask cuma lahir di `escalateToFop()`).
5. Response: nomor tiket (`TKT-YYYY-NNNN`) + status awal.

Field respons **whitelist eksplisit** (pola sama seperti Fase 3 billing di `business-logic.md` portal) — jangan `TicketResource::make($ticket)` polos yang bisa kebocor kolom internal.

---

## 3. Arsitektur API `customer-portal/kolektor`

```
GET  /api/customer-portal/kolektor/worklist/{customer_qr_code}
POST /api/customer-portal/kolektor/payments
Middleware: portal_client + portal_staff_token + throttle:customer-portal-api
```

Alur `GET .../worklist/{code}`:
1. Resolve QR → `customer_id` (sama seperti di atas).
2. Cek permission `kolektor.qr.pay` (§1.4). **Cek ulang** kolektor ini punya pelanggan itu di worklist-nya — query **identik** dengan `CollectorWorklistController::index()` / `CollectorWorklistService::dueInvoices()`, **bukan** query baru yang mirip-mirip. Di luar worklist → 403 tegas, **jangan** fallback ke path lain (sama invarian yang sudah ditulis di komentar `QrScanController` baris 96–100).
3. Response: daftar invoice due milik pelanggan itu (nominal, periode) — data yang sama yang kolektor lihat di halaman worklist Operasional, cuma di-scope ke 1 pelanggan.

Alur `POST .../payments`:
1. Body: `{ customer_qr_code, invoice_number, nominal, metode, ... }` — nominal & metode tetap **input manual kolektor**, bukan auto-isi dari QR (prinsip yang sama sudah ditulis di komentar `QrScanController`: "kolektor yang megang uang fisiknya, scan cuma bantu navigasi").
2. Validasi ulang: worklist membership (poin 2 di atas) + invoice memang milik `customer_id` hasil resolve QR — cegah kolektor kirim `invoice_number` pelanggan lain lewat body manipulasi.
3. Delegasi ke service pencatatan pembayaran kolektor yang sudah ada (**bukan** logic baru) — cek dulu nama service persisnya di `docs/kolektor/` sebelum implementasi (kemungkinan bagian dari alur `CollectorWorklistService` atau service pembayaran kolektor terpisah, perlu dikonfirmasi saat masuk sprint).
4. Semua aturan existing tetap berlaku: `PaymentObserver::creating()` tolak nominal ≤ 0, penuh→lunas/kurang→sebagian, audit log.

---

## 4. Masalah yang belum terjawab — auth staff di Portal

Ini **gap arsitektur** yang harus diputuskan sebelum §2–3 bisa diimplementasi:

`CustomerPortalToken` (dipakai `portal_token` middleware) **terikat ke `customer_id`**, bukan `user_id` Operasional (lihat `PortalAuthService::issueTokenPair()`). Token pelanggan gak bisa dipakai staf/kolektor — beda subjek otorisasi sama sekali (pelanggan gak punya role/permission/POP scope Operasional).

Perlu salah satu dari:

| Opsi | Cara kerja | Risiko/biaya |
|---|---|---|
| **A. SSO token staf** | Operasional terbitkan token pendek (mirip `CustomerPortalToken` tapi `user_id`-based) saat staf klik redirect dari `QrScanController`, Portal terima token ini via query param/fragment sekali pakai, tukar ke session Portal | Butuh model/tabel baru (`StaffPortalToken`?), endpoint tukar token, TTL pendek biar gak jadi bearer token bocor |
| **B. Staf tetap login native ke Portal pakai akun Operasional** | Portal punya form login staf terpisah dari form login pelanggan, verifikasi credential ke Operasional (endpoint auth baru) | Portal jadi punya 2 sistem auth (pelanggan vs staf) — kompleksitas UI/UX nambah, tapi tokennya lebih standar (tidak one-shot) |
| **C. Portal cuma tampilan, submit balik ke Operasional (bukan API portal)** | Redirect ke halaman Operasional yang di-embed/di-style mirip Portal | Bertentangan langsung sama maksud "1 pintu Portal" user — **tidak direkomendasikan**, cuma dicatat sebagai baseline pembanding |

Rekomendasi awal: **Opsi A** — pola one-shot exchange token paling konsisten sama filosofi existing (`CustomerQrTokenService` juga one-shot-verify per scan), TTL pendek meminimalkan risiko token nyasar, dan gak nambah UI login kedua di Portal. Tapi ini keputusan yang **wajib dikonfirmasi ke pemilik produk** sebelum jalan — menyentuh model auth baru di dua repo sekaligus.

---

## 5. Perubahan di `QrScanController::dispatch()` (ringkas, belum kode)

```
Kolektor (worklist match)  → SEKARANG: redirect(collector-worklist.index)
                              USUL:     redirect Portal + token one-shot (§4)

Staff (tickets.create)     → SEKARANG: redirect(qr.ticket.create)
                              USUL:     redirect Portal + token one-shot (§4)
```

Guest branch **tidak berubah** — sudah sesuai maksud dari awal.

`qr.ticket.create` (route + view Blade internal) — kalau staff ticketing pindah total ke Portal, route ini jadi kandidat pencabutan (mirip pencabutan `QrBillingController` sebelumnya) — **atau** dipertahankan sebagai fallback manual dari dashboard Operasional (bukan dari scan QR). Perlu keputusan eksplisit juga, jangan dihapus diam-diam.

---

## 6. Pertanyaan terbuka — DIKUNCI 2026-08-29

1. ~~Opsi auth staf di Portal — A/B/C di §4?~~ → **Opsi A**, dengan klarifikasi: entry HARUS lewat `/scan-qr` in-app scanner (`QrInAppScanController`, sudah ada sejak ADHOC-50) — bukan scan lewat app kamera luar. Titik itu sudah membuktikan cookie sesi staf sah SEBELUM `StaffPortalTokenService` menerbitkan token, jadi tidak perlu mekanisme login Portal kedua (Opsi B tidak dipakai).
2. ~~`qr.ticket.create` internal: dicabut atau dipertahankan?~~ → **Dipertahankan** buat jalur non-QR (dashboard). Sudah tidak lagi jadi tujuan dispatch QR.
3. ~~Dedup tiket: UI gate atau hard guard 409?~~ → **Hard guard 409** — tapi diimplementasikan **opt-in** (`enforceDuplicateGuard` param di `TicketService::create()`, default `false`) supaya dashboard `/tickets/create` existing TIDAK berubah perilakunya (banyak test sengaja bikin >1 tiket per pelanggan). Cuma endpoint QR-Portal yang mengaktifkannya. Lihat §7.
4. ~~Kolektor: service existing yang di-reuse?~~ → `CollectorPaymentService::record()` (dipakai `CollectorPaymentController::store()` di jalur dashboard `/collector-worklist/pay`) — **belum diimplementasikan** ke endpoint API, lihat §7 Fase 2.
5. ~~Sprint mana?~~ → Ad-hoc **ADHOC-52** di `docs/TASKS.md`, dikerjakan langsung (di luar Sprint 8.10 yang sedang jalan, atas persetujuan eksplisit user).

---

## 7. Implementasi Fase 1 (Operasional) — 2026-08-29

**Selesai:**

- Migrasi `staff_portal_tokens` + model `StaffPortalToken` (pola sama `CustomerPortalToken`: plaintext sekali, hash disimpan, one-shot lewat `consumed_at`).
- `StaffPortalTokenService` — `issue()`/`resolve()`, TTL 15 menit, purpose `tickets`/`kolektor`.
- Middleware `portal_staff_token:{purpose}` (alias di `bootstrap/app.php`) — lapis kredensial staf, terpisah dari `portal_token` (pelanggan).
- Permission baru `tickets.qr.create` (sub-feature `tickets.qr`) & `kolektor.qr.pay` (sub-feature `kolektor.qr`) — `config/rbac.php` + `QrFeatureSeeder`. Helpdesk/NOC/FOP otomatis lolos `tickets.qr.create` lewat wildcard `tickets.*` yang sudah mereka punya; `kolektor.qr.pay` ditambah eksplisit ke role `kolektor` di `RolePermissionSeeder` (role itu tidak punya wildcard `kolektor.*`).
- `QrScanController::dispatch()` — cabang staf-ticketing & kolektor sekarang `redirectToPortal()` (helper baru, satu tempat guard `PORTAL_BASE_URL` buat tamu/staf/kolektor sekaligus), bawa `?code=...&staff_token=...`.
- `POST /api/customer-portal/tickets` (`PortalStaffTicketController`) — delegasi ke `TicketService::create()`, dedup guard 409 (`DuplicateTicketException`), token dikonsumsi HANYA setelah sukses simpan.
- Test: `PortalStaffTicketStoreTest` (8 kasus), `QrScanDispatchTest`/`QrScanCollectorPaymentTest` diupdate ke redirect Portal. 253 test modul terkait hijau (1 gagal pre-existing tidak terkait, dikonfirmasi juga gagal di kode sebelum sesi ini).

**Fase 2a selesai (2026-08-29, sesi lanjutan):**

- `PortalStaffKolektorController::worklist()` — `GET /kolektor/worklist/{code}`, resolve QR → cocokkan `customer_id` dengan token (cegah tukar `code` manual biar "pinjam" token pelanggan lain), reuse `CollectorWorklistService::dueInvoices()` tersaring ke 1 pelanggan, 403 kalau `collector_id` tidak cocok.
- `PortalStaffKolektorController::payments()` — `POST /kolektor/payments`, pakai trait `RecordsCollectorBatch` APA ADANYA (sama persis `CollectorPaymentController`), token dikonsumsi HANYA saat batch beneran diproses sukses (bukan idempotency replay, bukan 422 gagal validasi — staf masih bisa perbaiki & submit ulang pakai token yang sama).
- Test: `PortalStaffKolektorTest` (8 kasus — worklist sukses/403/404 tukar-code/401 purpose salah, payments sukses+konsumsi/422 di luar worklist+token tidak terkonsumsi/403 bukan role kolektor/401 tanpa token).

**Fase 2b selesai (2026-08-29, sesi lanjutan, repo `portal-pelanggan`):**

- Route group `(staff)` + layout kartu tunggal (pola sama `(auth)`).
- `/staff/tickets` (Server Component baca `code`+`staff_token` dari query) → `StaffTicketForm` (client): submit, tangani 409 (tampilkan `existing_ticket_number` + tombol "Tetap Buat Baru" yang resubmit `confirmed_duplicate:true`), 401 (token expired/dipakai).
- `/staff/kolektor` (Server Component resolve worklist LANGSUNG via `callLaravel`, pola sama `/klaim`) → `StaffKolektorPaymentForm` (client): checklist invoice + nominal manual per baris (default = sisa tagihan, bisa diubah), `idempotency_key` di-generate sekali per render (`crypto.randomUUID()`).
- Route Handler proxy `api/staff/tickets`, `api/staff/kolektor/payments` — `staff_token` diteruskan lewat header `Authorization` MANUAL (bukan `auth:true` di `callLaravel`, yang baca `iron-session` cookie pelanggan — subjek beda total).
- `ApiResult` (`laravel-client.ts`) ditambah field opsional `raw` (body error mentah) — dibutuhkan buat baca `existing_ticket_number` dari 409, field yang gak masuk union generik `{message,errors}`.
- Tipe TS baru: `StaffTicketCreateRequest/Response`, `StaffKolektorWorklistResponse`, `StaffKolektorPaymentRequest/Response`, dst di `portal-api.ts`.
- `docs/api/api-portal-pelanggan/business-logic.md` §5 "Staf/Kolektor (QR)" — kontrak 3 endpoint. Larangan #9 `frontend-nextjs-rancangan.md` dikoreksi (sudah gak berlaku, ditandai bukan dihapus diam-diam).
- **Verifikasi:** `tsc --noEmit` + `eslint` bersih (dijalankan manual lewat `node .../tsc`/`eslint.js` — `npm run build`/`npx` gagal di sesi ini gara-gara UNC path Windows + EPERM `next-swc-fallback`, batasan environment lokal, BUKAN error kode). **Belum** diverifikasi lewat `next build` penuh atau browser asli — perlu dicoba lagi di environment yang gak kena masalah UNC/WSL ini, atau dari WSL langsung.
- `bootstrap/app.php`/`config/qr.php` di sisi Portal — TIDAK disentuh, tidak ada perubahan konfigurasi baru yang dibutuhkan di sisi Portal untuk fitur ini (Laravel base URL & client secret sudah ada dari sebelumnya).

## 8. Bug ditemukan & ditambal pasca-Fase 2 (2026-08-29, uji coba user)

**Bug 1 — `proxy.ts` Portal nolak halaman staf.** Portal punya gerbang global (`src/proxy.ts`, Next.js middleware) yang minta cookie sesi pelanggan buat SEMUA route kecuali whitelist `/login`/`/aktivasi`/`/klaim`. `/staff/tickets`/`/staff/kolektor` gak masuk whitelist itu (kelewat pas Fase 2b) — staf yang scan QR selalu kelempar ke `/login` walau `staff_token` udah bener di URL. **Fix:** tambah pengecualian `pathname.startsWith('/staff/')` — halaman staf punya otentikasi sendiri (`staff_token` per-request), gak butuh cookie sesi pelanggan.

**Bug 2 — Ambiguitas dua permission sekaligus.** Akun full-access (owner/superadmin, atau siapa pun yang kebetulan eligible `tickets.qr.create` DAN `kolektor.qr.pay` buat pelanggan yang sama) selalu diarahkan ke kolektor walau maksudnya kirim tiket (atau sebaliknya) — `QrScanController::dispatch()` motong kompas lewat urutan `if` (kolektor dicek duluan), staf gak pernah dikasih tau ada pilihan lain. **Fix:** `dispatch()` sekarang hitung eligibility DUA-DUANYA dulu (`resolveEligibility()`); kalau dua-duanya true → redirect ke halaman baru **`qr.scan.choose`** (internal, di app Operasional, BUKAN Portal) — staf pilih eksplisit "Kirim Tiket" atau "Catat Pembayaran", baru dari situ `chooseConfirm()` MENGULANG semua pengecekan (permission, POP scope, worklist) dan menerbitkan token sesuai pilihan. Kalau cuma satu yang eligible, behavior LAMA (auto-redirect) tetap jalan — chooser cuma nongol pas beneran ambigu.

File baru: `resources/views/qr/scan-choose.blade.php`, route `GET/POST /scan-qr/choose/{code}`. Test: `QrScanAmbiguousPermissionTest` (5 kasus). Regresi: 237 test QR/RBAC/ticketing terkait tetap hijau (1 gagal pre-existing tak terkait, sama seperti sebelumnya).

**Bug 3 — `hasRole('kolektor')` bikin owner gak pernah eligible kolektor walau permission `*` (2026-08-29, ditemukan pas user retest chooser).** Setelah bug 1/2 ditambal, user retest pakai akun owner tetap SELALU ke ticketing, gak pernah ke chooser. Investigasi via `qr_scan_logs` + tinker: role owner gak pernah lolos cabang kolektor karena syaratnya `hasRole('kolektor') && hasPermission('kolektor.qr.pay')` — bagian `hasRole()` itu BUKAN permission check, jadi `*` (full access) gak ngaruh ke situ sama sekali. Sempat kelihatan kayak "RBAC gak jalan buat owner", tapi setelah ditelusuri: RBAC-nya (`hasPermission()`) beneran jalan normal, cuma nyelip syarat identitas role TAMBAHAN di atas permission — inkonsisten sama cabang ticketing yang cuma cek permission doang.

**Fix awal (KELIRU, dibalikin — lihat sub-bagian di bawah):** sempat diubah jadi permission-only (`hasPermission('kolektor.qr.pay')` doang, drop `hasRole('kolektor')`), sama pola `tickets.qr.create`.

**Kenapa dibalikin (2026-08-29, user nanya "kalau RBAC kenapa masih hardcode role?"):** pertanyaan itu valid dan nemuin masalah nyata — tapi bukan di tempat yang saya kira. Grep `hasRole('kolektor')` di seluruh `app/` nemu **8+ titik lain**: `CollectorPaymentController`, `CollectorDepositController`, `CollectorVisitController`, `PaymentBatchController`, `PaymentReceiptController`, `CollectorWorksheetController`, **termasuk `PortalStaffKolektorController::payments()` yang ditulis Fase 2a** — SEMUA nolak walau permission `*`. Kalau cuma `resolveEligibility()` di `QrScanController` yang dilonggarin, owner ke-ROUTE ke halaman kolektor (eligibility lolos) tapi begitu submit bayar → 403 di endpoint tulisnya. Hasil akhirnya lebih buruk: nyasar ke halaman yang pasti gagal.

`hasRole('kolektor')` di modul ini BUKAN pengganti RBAC — itu lapis BEDA. RBAC (`hasPermission()`) jawab "boleh ngapain". `hasRole('kolektor')` jawab "identitas ini struktural kolektor apa bukan" — dibutuhkan karena `collector_id`/`collected_by` adalah kunci laporan keuangan kolektor (saldo, setoran, worklist, cross-check, docs/kolektor/). Kalau owner "jadi kolektor" cuma modal `*`, transaksinya nempel ke pembukuan kolektor buat akun yang bukan penagih lapangan beneran — ngerusak laporan itu. Ticketing gak punya lapis ini karena `created_by` ticket bukan kunci pembukuan siapa pun — makanya permission-only DI SANA tetap benar, gak perlu dibalikin.

**Fix final:** `resolveEligibility()` balik ke `hasRole('kolektor') && hasPermission('kolektor.qr.pay')`, konsisten sama seluruh sisa modul Kolektor 2.0. Owner TETAP gak pernah eligible kolektor lewat QR — itu benar & disengaja, bukan kekurangan.

Test: `owner_full_access_tetap_gak_eligible_kolektor_walau_collector_id_cocok` (buktiin owner tetap cuma ticketing walau `collector_id` kebetulan cocok — `hasRole()` yang jadi gerbang penentu, bukan kepemilikan data doang), `kolektor_asli_tetap_eligible_normal` (buktiin staf kolektor sah tetap gak kehalang). Total `QrScanAmbiguousPermissionTest` 7 kasus. 64 test modul QR tetap hijau (1 pre-existing gak terkait).

## 9. Dokumentasi Scramble dibetulkan (2026-08-29)

Dicek lewat `php artisan scramble:export` — endpoint staf (`POST /tickets`, `GET /kolektor/worklist/{code}`, `POST /kolektor/payments`) ada 2 cacat di dokumen OpenAPI yang ke-generate:

1. **Summary kosong/berantakan** — `store()`/`worklist()`/`payments()` gak punya docblock method dengan baris pertama pendek (Scramble ambil baris pertama docblock sebagai `summary`). Hasilnya: `POST /tickets` summary kosong, `worklist()`/`payments()` summary-nya jadi SELURUH paragraf komentar kepanjangan. Fix: tambah judul pendek satu baris di awal tiap docblock method (pola sama `PortalTicketController::index()` — "Riwayat ticketing").
2. **Security scheme salah subjek** — `ScrambleServiceProvider::boot()` nge-assign `PortalClientSecret + PortalAccessToken` (token PELANGGAN) ke SEMUA path `/customer-portal/*` kecuali `/ping` & 3 endpoint auth — gak tau ada endpoint staf yang baru. Dokumen bilang endpoint staf butuh `access_token` pelanggan, PADAHAL itu `StaffPortalToken` yang beda total (one-shot, dari `QrScanController`, bukan `/auth/login`). Fix: scheme baru `StaffPortalToken` didaftarkan, loop assignment dicek DULUAN buat path `/tickets`/`/kolektor/*` sebelum fallback ke scheme pelanggan.

Response code yang kepakai (401/403/404/409/422) juga dilengkapi per endpoint (sebelumnya sebagian cuma nyantumin 200/201 + 1-2 error code, padahal controller-nya beneran bisa mental banyak status lain). Verifikasi: `scramble:analyze` bersih, `scramble:export` nunjukin `summary` pendek + `security: StaffPortalToken` (bukan lagi `PortalAccessToken`) di ketiga endpoint. 106 test `tests/Feature/Api/CustomerPortal` tetap hijau (murni perubahan docblock/attribute, gak nyentuh logic).

## 10. Kolektor dikasih `tickets.qr.create` (2026-08-29, keputusan eksplisit user)

Kasus nyata: kolektor lagi nagih pelanggan, dapet komplain di tempat. Sebelum ini, kolektor gak bisa lapor tiket lewat QR sama sekali (default seeder cuma kasih permission `kolektor.*`). User minta "sesimple itu": staf scan → Portal → pilihan "Tagih Pembayaran" atau "Lapor Komplain".

**Fix:** `tickets.qr.create` ditambah ke role `kolektor` (`RolePermissionSeeder`). Gak butuh kode baru — mekanisme chooser (§8) udah nangkep ini otomatis begitu permission-nya ada:

- Pelanggan **ada** di worklist kolektor (tagihan due, `collector_id` cocok) → dual-eligible → **chooser** ("Tagih Pembayaran"/"Lapor Komplain", label diganti dari "Catat Pembayaran"/"Kirim Tiket" biar sesuai istilah lapangan).
- Pelanggan **di luar** worklist kolektor itu (bukan tanggung jawabnya buat DITAGIH) → `kolektor.qr.pay` gak eligible, tapi `tickets.qr.create` TETAP eligible → langsung ke ticketing, TANPA chooser. Ini konsisten sama prinsip ticketing yang gak pernah dibatasi kepemilikan pelanggan (cuma POP scope) — 403 "bukan tanggung jawab Anda" cuma berlaku buat cabang PEMBAYARAN.

Efek samping yang diperiksa: 3 test lama (`QrScanCollectorPaymentTest`) yang dulu assert kolektor-di-luar-worklist → 403 SEKARANG assert redirect ke ticketing (behavior baru, disengaja — diupdate namanya + assersinya). `KolektorLoginRedirectsToWorklistTest` punya assertion daftar permission kolektor yang hardcode (udah basi duluan sebelum sesi ini, gak ngikutin `qr_scan.view`/`kolektor.qr.pay` dari ADHOC-51/52) — dilengkapi sekalian. Total 186 test terkait (QR + kolektor + RBAC + portal API) hijau, 1 pre-existing gak terkait. `RolePermissionSeeder` dijalanin ulang ke DB dev live.
