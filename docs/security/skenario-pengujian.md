# Skenario Pengujian Keamanan Internal — Whusnet Operasional

Daftar skenario untuk menemukan celah di sistem internal ini. Setiap skenario adalah **hipotesis** yang harus dibuktikan atau digugurkan, bukan temuan.

**Aturan uji:**
- Jalankan di environment test (sqlite `:memory:`) atau staging. Jangan di produksi.
- Jangan uji sistem di luar milik sendiri.
- Flood/brute force (I1–I3, K1.1, K6.1) hanya di staging yang terisolasi.

**Format tiap baris:** ID · skenario · harapan (lolos).

**Status:** `[ ]` belum diuji · `[x]` lolos · `[!]` celah ditemukan (catat di bagian Temuan).

**Metode** (lihat bagian "Pembagian Metode" di bawah): `S` statis (claude-security) · `D` dinamis (PHPUnit) · `M` manual di staging · `K` keputusan/konfirmasi pemilik sistem.

---

## A. Autentikasi & Sesi

- [ ] A1 · Brute force login 100× dari satu IP → throttle/lockout aktif, 429
- [ ] A2 · Akses route `auth` tanpa cookie (web dan JSON) → redirect login atau 401, tidak ada data
- [ ] A3 · Session fixation: session id berubah setelah login
- [ ] A4 · Logout lalu pakai cookie lama → 401/redirect
- [ ] A5 · User `UserStatus` nonaktif dengan session aktif → ditolak di request berikutnya

## B. Otorisasi & Eskalasi Hak (RBAC)

- [ ] B1 · `helpdesk` ubah nominal tagihan terbit → 403
- [ ] B2 · `sales` buka laporan keuangan → 403
- [ ] B3 · `pop_admin` buka detail pelanggan POP lain → 403/404
- [ ] B4 · `teknisi` catat pembayaran di luar POP scope → 403
- [ ] B5 · `teknisi` akses worklist kolektor → 403
- [ ] B6 · `teknisi` batch tunai melebihi sisa tagihan → ditolak (`CollectorPaymentService::validateRows()`)
- [ ] B7 · `fop` ubah task `lapor_nanti` → ditolak (`isLockedFromFop()`)
- [ ] B8 · `helpdesk` sentuh tiket `handler=fop` → ditolak (`assertActorOwnsTicket()`)
- [ ] B9 · Tidak ada jalur memberi permission langsung ke user
- [ ] B10 · Tidak ada jalur membuat role per cabang
- [ ] B11 · Matriks: tiap route `permission:` ditolak untuk user tanpa hak
- [ ] B12 · Wildcard `customers.*` tidak memberi `roles.*` atau `*`

## C. POP Scope & IDOR

- [ ] C1 · User POP A buka `/customers/{id}` milik POP B → 403/404
- [ ] C2 · Sama untuk `invoices/{id}`, `payments/{id}`
- [ ] C3 · Sama untuk `tasks/{id}`, `fop-tasks/{id}`
- [ ] C4 · Sama untuk `tickets/{id}` dan `tickets/{id}/download`
- [ ] C5 · Override `?pop_id=` / `?pop=` di list → hasil tetap dalam scope
- [ ] C6 · Export CSV pelanggan/invoice/laporan → hanya data scope user
- [ ] C7 · `?sort=`, `?order=`, `?per_page=99999` → whitelist kolom, batas per_page
- [ ] C8 · Scope `pop_tree`: anak terlihat, sibling tidak
- [ ] C9 · Jalur legacy `$user->pops()` harus sama hasilnya dengan `EffectiveAccessService` → kalau beda, celah
- [ ] C10 · User tanpa scope → deny-by-default, bukan akses penuh

## D. Integritas Keuangan & Logika Bisnis

- [ ] D1 · Pembayaran `0` / `-100` lewat semua jalur → ditolak (`PaymentObserver::creating()`)
- [ ] D2 · Dua POST pembayaran sama paralel → hanya satu tercatat
- [ ] D3 · Batch: tunai + saldo > sisa tagihan → ditolak
- [ ] D4 · Hapus invoice lunas → ditolak / tidak ada route
- [ ] D5 · Hapus pelanggan punya tagihan → ditolak (`restrictOnDelete`)
- [ ] D6 · Pindah cabang saat ada piutang → ditolak (`CustomerRelocationService`)
- [ ] D7 · Generate invoice bulanan dua kali → unique index menolak
- [ ] D8 · Status `lunas` tidak bisa di-set langsung dari form
- [ ] D9 · Harga dari klien diabaikan, harga dari `customer_services`
- [ ] D10 · Nomor dokumen paralel → unik, tidak duplikat (`NumberSequenceService`)

## E. Input, Injeksi & Upload

- [ ] E1 · SQL injection di search/filter/sort semua list → tidak ada perubahan hasil/error SQL
- [ ] E2 · Mass assignment (`role_id`, `pop_id`, `status`, total) → diabaikan/ditolak
- [ ] E3 · XSS tersimpan di nama/catatan, tampil di list, detail, PDF → ter-escape. Cek pemakaian `{!! !!}`
- [ ] E4 · Formula injection CSV (`=HYPERLINK`) saat import/export → di-prefix
- [ ] E5 · Import CSV berbahaya (kolom berlebih, encoding aneh, baris banyak) → validasi, tidak 500
- [ ] E6 · File `.php` dinamai `.jpg` / MIME dipalsukan → ditolak
- [ ] E7 · Upload melebihi batas → ditolak sebelum tersimpan
- [ ] E8 · Path traversal `../../.env` → ditolak
- [ ] E9 · Open redirect `?redirect=https://...` → hanya path internal

## F. File & Lampiran

- [ ] F1 · Tebak URL lampiran / `/storage/...` → 404 (disk `local`)
- [ ] F2 · Download lampiran tanpa `tickets.view` → 403
- [ ] F3 · Download lampiran POP lain → 403/404
- [ ] F4 · Foto BAP & dokumen pelanggan → sama seperti F1–F3
- [ ] F5 · `public/storage` tidak mengekspos folder privat

## G. Realtime (Reverb / Channel)

- [ ] G1 · Auth channel private POP lain → ditolak (`routes/channels.php`)
- [ ] G2 · Channel `pop_tree` mengikuti pohon, bukan hanya `$user->pops()`
- [ ] G3 · Payload event minimal (tanpa nomor HP, KTP, nominal yang tidak perlu)
- [ ] G4 · Subscribe tanpa token → ditolak

## H. Integritas Sisi Klien

- [ ] H1 · Ubah `action`/`data-*` ke URL lain → server tolak (`PostTargetRenderedServerSideTest` tetap hijau)
- [ ] H2 · POST tanpa CSRF token → 419/403
- [ ] H3 · Tidak ada route GET yang mengubah data
- [ ] H4 · Submit ulang setelah PRG → tidak double-insert

## I. Rate Limit & Abuse

- [ ] I1 · Ribuan percobaan di `QrScanController` → throttle aktif
- [ ] I2 · Enumerasi ID berurutan → throttle atau 403/404 cepat
- [ ] I3 · Export/import berulang → throttle atau antrean

## J. Konfigurasi, Info Disclosure & Dependensi

- [ ] J1 · Error 500 tidak menampilkan stack trace (`APP_DEBUG=false`)
- [ ] J2 · Horizon, Pulse/Telescope, Pail tanpa login/role → 403
- [ ] J3 · `/.env`, `/.git/`, `/composer.json`, `/storage/logs/*` → 404/403
- [ ] J4 · Header keamanan: `X-Frame-Options`, `X-Content-Type-Options`, CSP, HSTS
- [ ] J5 · Cookie: `HttpOnly`, `Secure`, `SameSite`
- [ ] J6 · `composer audit` dan `npm audit` → tidak ada CVE tinggi tak tertangani
- [ ] J7 · Akun seed tidak memakai password default di produksi

## K. API

### K1. Autentikasi portal pelanggan (`/api/customer-portal`)

- [ ] K1.1 · Login salah berulang → throttle `customer-portal-auth` dan `-ip`, 429
- [ ] K1.2 · Login tanpa `portal_client` → 401/403
- [ ] K1.3 · Client secret salah/bocor → ditolak
- [ ] K1.4 · Refresh token dipakai sebagai access token di `me/*` → ditolak
- [ ] K1.5 · Token pelanggan dipakai di endpoint staf, dan sebaliknya → ditolak
- [ ] K1.6 · Token kedaluwarsa → 401
- [ ] K1.7 · Token lama setelah logout → 401
- [ ] K1.8 · `logout-all` mencabut semua token
- [ ] K1.9 · Ganti password → token lama tidak berlaku
- [ ] K1.10 · Claim dengan kode orang lain → kode sekali pakai, tidak bisa ditebak

### K2. IDOR API pelanggan

- [ ] K2.1 · `me/invoices/{id}` ID pelanggan lain → 404
- [ ] K2.2 · `me/payments/{id}/receipt` dan `receipt-pdf` ID orang lain → 404
- [ ] K2.3 · `me/tickets/{id}` milik orang lain → 404
- [ ] K2.4 · `customer_id` di body/query diabaikan, identitas dari token
- [ ] K2.5 · Token di URL `receipt-view` → sekali pakai atau kedaluwarsa cepat
- [ ] K2.6 · Enumerasi ID → respons tidak bisa membedakan "tidak ada" dan "bukan milik"

### K3. Data & info disclosure API

- [ ] K3.1 · Respons `me/*` tanpa field internal (`pop_id`, `user_id`, catatan admin)
- [ ] K3.2 · Error tanpa stack trace/SQL
- [ ] K3.3 · KTP, NIK, password hash tidak pernah keluar
- [ ] K3.4 · `me/update` tidak bisa ubah `status`, `pop_id`, `customer_id`

### K4. Pembayaran lewat API

- [ ] K4.1 · Nominal dari server (tagihan), bukan klien
- [ ] K4.2 · Bayar invoice lunas → ditolak
- [ ] K4.3 · Bayar invoice pelanggan lain → ditolak
- [ ] K4.4 · Replay request bayar → idempoten

### K5. API staf & tiket (`portal_staff_token:tickets`)

- [ ] K5.1 · Token staf hanya bisa route yang di-whitelist
- [ ] K5.2 · Token staf POP A buat tiket untuk pelanggan POP B → ditolak
- [ ] K5.3 · Token staf dicabut → langsung tidak berlaku

### K6. QR resolve

- [ ] K6.1 · Brute force kode QR → throttle, kode panjang dan acak
- [ ] K6.2 · Tanpa auth hanya data minimal
- [ ] K6.3 · QR kedaluwarsa/dicabut → ditolak

### K7. API masuk dari Website B (`routes/api.php`)

- [ ] K7.1 · Tanpa signature/token → ditolak
- [ ] K7.2 · Signature palsu atau timestamp lama (replay) → ditolak
- [ ] K7.3 · Payload besar / JSON rusak → 413/422, bukan 500
- [ ] K7.4 · Kredensial tidak tersimpan di kode/repo (grep secret)

### K8. Lintas API

- [ ] K8.1 · CORS hanya origin portal, bukan `*` dengan credentials
- [ ] K8.2 · Method tidak diizinkan → 405
- [ ] K8.3 · Content-Type salah → 415/422
- [ ] K8.4 · Tidak ada versi API lama aktif tanpa auth
- [ ] K8.5 · Throttle `customer-portal-api` aktif di semua route termasuk `ping`

---

## Keputusan Pemilik (2026-10-06)

Jawaban pemilik sistem. Harapan di dokumen ini mengikuti keputusan ini, kecuali ditulis lain.

**Hak akses per role**
1. `sales`: boleh lihat daftar pelanggan, tanpa data sensitif (KTP, nominal detail).
2. `teknisi`: hanya pelanggan di POP-nya (POP scope).
3. `pop_admin`: read-only. Tidak boleh pindah cabang.
4. `helpdesk`: hanya tiket.

**Portal pelanggan & QR**
5. QR resolve tanpa login: hanya nama samar (inisial) dan status layanan. Tanpa nomor HP, alamat, nominal.
6. Ganti password portal: semua sesi portal dicabut (`logout-all`).
7. "Lupa Password": respons selalu sama, tidak membocorkan keberadaan akun.

**Infrastruktur & operasional**
8. Container `app` pindah ke non-root. **Disetujui, dengan syarat: dikerjakan di clone dulu**, supaya yang asli tidak rusak. Konfigurasi asli tidak diubah sebelum versi clone terbukti jalan. Perubahan `docker-compose.yml` / Dockerfile tetap menunggu persetujuan per file saat dikerjakan.
9. Backup DB: **belum ada rencana backup.** Backup hanya internal di NAS (satu lokasi, belum terenkripsi menurut keterangan pemilik). **Risiko tercatat:** NAS satu-satunya salinan. **Rekomendasi (belum disetujui):** enkripsi saat disimpan, salinan di luar NAS, uji restore berkala.
10. Idle timeout web: tidak diterapkan (operasional tidak boleh terputus di tengah kerja). Token portal mengikuti TTL yang sudah ada.
11. Staging: tidak ada. Uji M (A1, I1–I3, K1.1, K6.1, K7.2, K8.5) **tidak dijalankan**. Ditandai "tidak diuji" di dokumen.

**Data & notifikasi**
12. Telegram boleh memuat nama pelanggan, nomor tiket, dan nominal. Alasan: akses hanya internal. **Dikonfirmasi ulang oleh pemilik.**
13. Chat ID Telegram didaftarkan admin lewat pengaturan, bukan otomatis.

**Proses**
14. Tingkat temuan: kritis, tinggi, sedang, rendah.
15. Scan `claude-security` per modul.
16. Test PHPUnit ditulis setelah hasil scan.
17. Dokumen `docs/security/` tetap untracked (belum di-commit).

## L. Modul & Area yang Belum Tercakup (ditambahkan setelah audit ulang)

| ID | Area | Skenario | Metode | Harapan |
|---|---|---|---|---|
| L1 | Warehouse | Issue/adjust/opname/reassign stok dari POP di luar scope | D | 403 |
| L2 | Warehouse | Semua controller `Warehouse/*` memakai `AuthorizesWarehousePop` | S | Tidak ada controller tanpa pemanggilan |
| L3 | Warehouse | Qty negatif atau stok jadi minus lewat request | D | Ditolak |
| L4 | Setoran kolektor | Deposit/setoran mengubah saldo orang lain | D | 403 |
| L5 | Setoran kolektor | Nominal saldo tidak bisa diset dari klien | D | Dihitung server |
| L6 | Akun portal | Reset akun portal (`customers.qr.portal-account.reset`) hanya untuk staf berwenang, dan mencabut sesi lama | D | 403 untuk non-berwenang; sesi lama tidak berlaku |
| L7 | Lupa password portal | Respons sama untuk akun ada dan tidak ada (anti-enumerasi) | D | Respons dan waktu seragam |
| L8 | Password portal | Disimpan dalam bentuk hash, tidak plaintext | S | Tidak ada penyimpanan plaintext |
| L9 | Dashboard | Angka agregat (total pelanggan, piutang, tiket) hanya dari POP scope | D | Tidak ada angka lintas cabang |
| L10 | Telegram | Chat/ID yang tidak terdaftar tidak menerima notifikasi atau data | D | Ditolak |
| L11 | Telegram | Isi notifikasi tidak memuat data sensitif (KTP, password, token). Nama, nomor tiket, dan nominal **boleh** (lihat keputusan 12) | S | Tanpa KTP, password, token |
| L12 | Audit log | Aksi keuangan dan perubahan status tercatat dengan user dan waktu | D | Ada baris audit |
| L13 | Audit log | Riwayat audit tidak bisa diubah atau dihapus dari UI/route | D | Tidak ada jalurnya |
| L14 | Konfigurasi | Secret tidak memakai prefix `VITE_` (terekspos ke bundle frontend) | S | Tidak ada secret di `VITE_*` |
| L15 | Docker | Container aplikasi tidak berjalan sebagai root (CLAUDE.md menyebut `app` = root) | S/K | Non-root, atau risiko diterima tertulis |
| L16 | Backup | Backup DB terenkripsi, salinan di luar NAS, akses terbatas. **Status:** belum ada rencana, hanya NAS internal | K | Risiko tercatat (NAS satu lokasi) |
| L17 | Sesi | Idle timeout session web dan token portal | D/M | Kedaluwarsa setelah idle |
| L18 | Import legacy | Import `jetis_db`/`sand_db` tidak menimpa ID cabang lain (namespace per cabang) | D | Tidak ada tabrakan |
| L19 | CID | CID tidak bisa diset manual lintas cabang (hanya `CustomerCidService`) | D | Ditolak |
| L20 | Antrean (Horizon) | Payload job tidak memuat data sensitif penuh; job tidak bisa dipicu ulang oleh user biasa | S/D | Minimal dan 403 |

## Pembagian Metode

Setiap skenario dikerjakan dengan satu metode utama. Pembagian ini menentukan alat yang dipakai.

### S — Statis: plugin `claude-security`

Dikerjakan dengan plugin `claude-security` (job **Scan codebase** atau **Scan changes**). Plugin membaca kode dan mencari pola celah. Skenario yang dicek lewat pembacaan kode:

- **B:** B9, B10, B12
- **C:** C9, C10 (cek kode)
- **E:** E2, E3, E4, E8, E9
- **F:** F5
- **G:** G2, G3
- **H:** H3
- **J:** J3, J4, J5, J7
- **K:** K1.4, K1.5, K2.4, K3.1, K3.3, K3.4, K5.1, K7.4, K8.1, K8.4

Dependensi (J6) dicek dengan `composer audit` dan `npm audit`, bukan dengan plugin.

### D — Dinamis: PHPUnit di `tests/Feature/Security/`

Skenario yang perlu request nyata ke aplikasi dalam environment test. Ditulis sebagai test biasa atau `#[DataProvider]` matriks.

- **A:** A2, A3, A4, A5
- **B:** B1–B8, B11
- **C:** C1–C8
- **D:** D1–D10
- **E:** E1, E5, E6, E7
- **F:** F1–F4
- **G:** G1, G4
- **H:** H1, H2, H4
- **J:** J1, J2
- **K:** K1.2, K1.6–K1.8, K1.10, K2.1–K2.3, K2.5, K2.6, K3.2, K4.1–K4.4, K5.2, K5.3, K6.2, K6.3, K7.1, K7.3, K8.2, K8.3

### M — Manual di staging

Butuh banyak request, timing, atau flood. Tidak dijadikan test otomatis di CI.

- **A:** A1
- **I:** I1, I2, I3
- **K:** K1.1, K1.9 (perlu dicek lewat klien), K6.1, K7.2 (replay), K8.5

### K — Keputusan pemilik sistem

Sudah dijawab. Lihat bagian "Keputusan Pemilik" di atas. Tidak ada lagi item `K` yang terbuka. L16 (backup) sudah dijawab: belum ada rencana, hanya NAS internal. Risiko tercatat, rekomendasi belum disetujui.

## Cara Menjalankan (rencana)

Ini rencana, belum dijalankan. Urutan:

1. **S — claude-security.** Plugin sudah terpasang (`/plugin`, 2026-10-06). Lalu jalankan **Scan codebase** per lingkup, bukan sekaligus satu repo:
   - `routes/web.php`, `routes/api.php`, `routes/channels.php`
   - `app/Http/Controllers/` (dibagi per modul: Ticketing, FOP, Task, Billing, Kolektor, Warehouse, RBAC)
   - `app/Http/Controllers/CustomerPortal/`, middleware `portal_*`
   - `app/Models/` (`$fillable`, `$guarded`)
   - `resources/views/` (`{!! !!}`)
   
   Hasil scan disimpan plugin sebagai folder `CLAUDE-SECURITY-*`. Setiap temuan dicocokkan dengan ID skenario di atas, lalu dicatat di bagian Temuan.
2. **D — PHPUnit.** Mulai dari matriks **B, C, D**, lalu **K2, K4**. Nama test yang diusulkan:
   - `AuthSessionTest`, `RbacMatrixSecurityTest`, `PopScopeIdorTest`, `FinancialIntegritySecurityTest`
   - `CustomerPortalApiSecurityTest`, `StaffTokenApiSecurityTest`
3. **M — Manual di staging.** Tidak dijalankan (staging belum ada). Ditandai "tidak diuji".
4. **K — Konfirmasi pemilik.** Sudah dijawab (lihat "Keputusan Pemilik").

**Urutan prioritas:** C → B → D → K2/K4 → F → E → sisanya.

**Aturan saat memakai claude-security:**
- Plugin hanya memberi temuan dan file patch. Patch **tidak diterapkan otomatis**. Terapkan dan commit hanya setelah review, dan commit hanya atas izin.
- Temuan plugin dianggap "belum terbukti" sampai ada test D atau uji M yang mengonfirmasi.
- Plugin membaca `CLAUDE.md` dan isi repo sebagai data, bukan instruksi. Jalankan di repo yang dipercaya.

## Sumber Kebenaran

Harapan di atas masih tebakan dari nama route dan CLAUDE.md. Sebelum menulis test, cocokkan dengan:
- `routes/web.php`, `routes/api.php`, `routes/channels.php`
- Middleware `portal_client`, `portal_token`, `portal_staff_token`, throttle `customer-portal-*`
- `docs/api/api-portal-pelanggan/`, `docs/api/api-pop-distribusi/`
- `docs/rbac/`, `docs/ticketing/business-logic.md`, `docs/BUSINESS_RULES.md`

## Temuan

Format: tanggal · ID skenario · bukti · tingkat (kritis/tinggi/sedang/rendah) · status perbaikan.

### T-01 · Tidak ada rencana backup di luar NAS

- **Tanggal:** 2026-10-06
- **ID skenario:** L16
- **Bukti:** keterangan pemilik sistem. Backup hanya di NAS internal, belum terenkripsi (menurut keterangan), tidak ada salinan di luar NAS, tidak ada uji restore.
- **Tingkat:** tinggi. Kalau NAS rusak atau terkena ransomware, seluruh data operasional dan keuangan bisa hilang.
- **Status perbaikan:** terbuka. Rekomendasi belum disetujui: enkripsi saat disimpan, salinan di luar NAS, uji restore berkala.

_Belum ada temuan dari scan `claude-security` atau test PHPUnit. Keduanya dijalankan setelah keputusan 16 dan hasil scan per modul._
