# Business Logic — Modul Business Development

## 1. Customer Acquisition — Monitoring "Pelanggan Aktif < 30 Hari"

**Tujuan:** murni monitoring, BUKAN penghitung komisi otomatis.

- Baris `customer_acquisitions` dibuat **otomatis** oleh `CustomerObserver::updated()`, **tepat sekali** saat `customers.status` pertama kali menyentuh `WorkflowTransition::ACTIVE` (`firstOrCreate` by `customer_id`) — pelanggan yang suspend lalu diaktifkan lagi TIDAK bikin baris baru; modul ini mencatat "akuisisi baru", bukan tiap kali status balik ACTIVE.
- Dikelompokkan per `periode` (`YYYY-MM`, bulan verifikasi). "Reset tanggal 1" **tanpa job/cron** — `CustomerAcquisitionController::index()` default filter periode bulan berjalan (otomatis kosong lagi begitu bulan ganti); bulan lalu tetap bisa dibuka read-only lewat filter.
- `CustomerAcquisition::isEditable()` = `periode === bulan berjalan` — periode lampau ditolak di server juga (bukan cuma disembunyikan di UI).
- Kolom tabel: No, Nama, POP/Cabang, Alamat, Tanggal Aktivasi, Biaya Langganan, **Harga Dikurangi PPN** (`getHargaDikurangiPpnAttribute()` — Biaya Langganan × 89%, dihitung live bukan disimpan), **Diinput Oleh** (`customers.sales_user_id` → nama + role, buat filter Role Penginput/Nama Penginput khusus role ber-`is_package_restricted`).
- Permission: `customer_acquisitions.view` (feature root, TANPA create/delete — baris otomatis), `customer_acquisitions.installation_fee.update` (sub-feature terpisah, lihat §3).

## 2. Master Kategori Paket — Saklar Gate

`package_categories.installation_fee_approval_role_id` (FK `roles`, nullable) adalah **satu-satunya** sumber kebenaran "kategori paket ini butuh gate BD atau tidak".

- **NULL** → `needsInstallationFeeValidation()` = false → kategori ini TIDAK PERNAH kena gate, berapa pun harga instalasinya.
- **Diisi** → kategori ini WAJIB lewat gate §3 di bawah, divalidasi oleh **role** yang dipilih (bukan permission mentah — lihat §2.1).

CRUD penuh di `/master/package-categories` (`PackageCategoryController`, permission `packages.view`/`packages.update`/`packages.create`): index, create, edit, destroy. `destroy()` **hard-delete** hanya kalau kategori belum dipakai paket manapun (`internet_packages.category` masih string, dicek `exists()`); kalau sudah dipakai, ditolak & diarahkan nonaktifkan lewat Edit (`is_active = false`).

### 2.1 Kenapa Role, bukan Permission

Desain awal (ADHOC-67) pakai kolom `installation_fee_approval_permission` (string bebas, dropdown isinya kode permission mentah kayak `warehouse_transfer.receive`). **Diganti** ke FK Role setelah user feedback: dropdown permission "terlalu teknis, cuma developer yang paham". Sekarang admin cukup pilih nama role ("Business Development", "Admin", dst) dari dropdown.

Gerbang tulis (`Customer::canInstallationFeeBeValidatedBy()`, `CustomerAcquisition::canBeValidatedBy()`) tetap **dua lapis**:

1. User punya permission `customer_acquisitions.installation_fee.update` lewat Role Matrix biasa — jalur teknis, dicek **duluan**, selalu lolos apa pun konfigurasi role di kategori (override buat admin/owner).
2. ATAU `user->role_id` **persis sama** dengan `installation_fee_approval_role_id` kategori paket pelanggan itu — jalur utama, gampang dipahami admin non-teknis.

Permission `.installation_fee.update` **sengaja TIDAK** digrant default ke role `business_development` di `RolePermissionSeeder` — kalau digrant, admin gak akan pernah bisa "mencabut" akses `business_development` cuma dengan mindah role di Master Kategori Paket (override permission selalu menang). Akses default BD datang murni dari jalur #2 (role cocok).

## 3. Gate "Menunggu Verifikasi BD" — State Machine

Enum: `WorkflowTransition::WAITING_BUSINESS_DEVELOPMENT_VERIFICATION` (value `waiting_business_development_verification`), disisipkan di antara `VERIFICATION_ADMIN` dan `ACTIVE`. **Satu-satunya** next state dari sini: `ACTIVE` — **TANPA jalur tolak sama sekali** (dikonfirmasi eksplisit user, beda dari tahap lain yang punya reject/revisi).

```
VERIFICATION_ADMIN ──finalVerify()──┬── kategori TIDAK butuh gate ──→ ACTIVE (langsung)
                                     └── kategori BUTUH gate ────────→ WAITING_BUSINESS_DEVELOPMENT_VERIFICATION
                                                                              │
                                                              BD isi Biaya Instalasi
                                                              & "Verifikasi & Aktifkan"
                                                                              ▼
                                                                            ACTIVE
```

Titik keputusan: `Customer::needsBusdevInstallationFeeVerification()`, dipanggil di `CustomerVerificationController::finalVerify()`. **Semua proses CS lain (prorata, CID, `service_status`/`billing_status`/`activation_date`, approve Task Pemasangan) TIDAK BERUBA sama sekali buat kedua kategori** — cuma `customers.status` yang beda tujuan.

### 3.1 Kapan Invoice Awal Terbit — Titik Paling Penting

**Bukan lagi selalu di CS.** Ini koreksi arsitektural (2026-09-14) atas desain awal yang sempat salah: sebelumnya Invoice Awal SELALU terbit di `finalVerify()` (CS) buat semua kategori, gate BD cuma menunda **status**, bukan **tagihan**. User menandai ini sebagai bug — kalau BD punya kuasa buat "menyetujui" Biaya Instalasi, harusnya tagihan pertama pelanggan juga baru sah setelah BD ikut menyetujui, bukan sudah tercetak duluan seakan-akan sudah final.

| | Kategori TIDAK butuh gate | Kategori butuh gate (Bisnis) |
|---|---|---|
| Invoice Awal terbit | **Di CS**, `finalVerify()`, langsung | **Di BD**, `verify()`, setelah BD approve |
| `extra_installation_fee` di Invoice Awal | Sesuai input CS | **Selalu 0** (dipaksa server) — biaya instalasi kategori ini ditagih terpisah oleh BD |
| Status pelanggan setelah CS submit | `ACTIVE` | `WAITING_BUSINESS_DEVELOPMENT_VERIFICATION` |

Mekanisme penundaan:

1. `finalVerify()` tetap **menghitung** tagihan penuh (`InitialInvoiceService::calculate()`) — nominalnya sudah final dan sudah ditunjukkan/dikonfirmasi ke pelanggan di layar CS.
2. Kalau kategori butuh gate, hasil hitungan itu **disimpan sebagai snapshot** (bukan invoice sungguhan) ke `customers.pending_initial_invoice` (JSON: `{billing: {...}, issue_date: "..."}"`). Invoice **tidak dibuat** di titik ini.
3. `BusinessDevelopmentVerificationController::verify()` membaca snapshot itu dan menerbitkan Invoice Awal dengan **angka PERSIS yang sama** (`InitialInvoiceService::issue()`) — bukan dihitung ulang, supaya harga paket/diskon yang mungkin berubah di antara dua titik waktu tidak menggeser nominal yang sudah dikonfirmasi CS ke pelanggan.
4. Snapshot ditimpa `null` setelah invoice terbit.
5. Kalau `verify()` dipanggil tapi snapshot kosong (data cacat/dimanipulasi di luar alur normal) — ditolak `422`.

`InitialInvoiceService::issue()` jadi **satu-satunya** tempat penerbitan Invoice AWAL (rumus baris & nomor invoice), dipanggil dari dua titik (CS langsung, BD tertunda) — mencegah dua rumus menyimpang.

### 3.2 Aksi BD — Satu Tombol, Tiga Efek

`BusinessDevelopmentVerificationController::verify()`, **satu transaksi DB**:

1. Terbitkan Invoice Awal yang ditunda (§3.1) — via `InitialInvoiceService::issue()`.
2. Terbitkan Invoice Biaya Instalasi — via `InstallationFeeInvoiceService::issue()`, **invoice TERPISAH** dari Invoice Awal (lihat §4 kenapa dua invoice, bukan satu).
3. `customers.status` → `ACTIVE` — memicu `CustomerObserver` bikin baris `customer_acquisitions` (§1) seperti biasa; baris itu **langsung** diisi `installation_fee`/`installation_fee_invoice_id` di request yang sama, jadi tidak pernah nongol "Menunggu Validasi" buat pelanggan yang lewat gate ini.

Gerbang akses: `Customer::canInstallationFeeBeValidatedBy()` (dua lapis, sama seperti §2.1) + wajib POP scope (`EffectiveAccessService`). Permission `business_development_verification.view` cuma buka HALAMAN (index/show) — aksi tulis gerbangnya dinamis per pelanggan, bukan permission statis kedua.

### 3.3 Kenapa Dua Invoice, Bukan Satu

`extra_installation_fee` (CS, Invoice Awal) dan `installation_fee` (BD, Invoice Biaya Instalasi) sengaja **TETAP DUA FIELD & DUA INVOICE TERPISAH**, walau dua-duanya sekarang sama-sama menerbitkan tagihan sungguhan:

- Invoice Awal **kadang sudah lunas** (dibayar di tempat saat aktivasi) sebelum BD sempat verifikasi kalau digabung risikonya mengubah nominal invoice yang mungkin sudah direkonsiliasi.
- Permission validasi keduanya memang beda dari awal (Role Matrix): `customers.detail.installation.validate` (CS) vs `customer_acquisitions.installation_fee.update`/gate role (BD) — tanda dua tim beda yang bertanggung jawab.
- Kategori "Jasa Instalasi" tetap sama (`RevenueCategory::CODE_JASA_INSTALASI` / `RevenueSubcategory::CODE_BIAYA_AKTIVASI`) di laporan pendapatan — cuma nomor invoicenya beda, bukan kategorinya.

### 3.4 Tampilan Halaman BD — Reuse View CS

`/business-development-verifications/{customer}` **me-reuse** `resources/views/verifications/admin.blade.php` — view yang sama persis dipakai CS di `/verifications/{id}/admin` (tab Registrasi/Survey/Pemasangan/Pengujian identik, badge status jadi "Verifikasi BD"). Data dimuat lewat service bersama `CustomerVerificationDetailService::load()` supaya CS & BD selalu melihat data yang identik — cuma tab "Verifikasi" yang bercabang isi (`$isWaitingBdStage`):

- **Cabang CS** — form `extra_installation_fee` dkk, posting ke `customers.verification.final`.
- **Cabang BD** — kartu "Hasil Verifikasi CS" (§3.1 datanya) + form satu-field `installation_fee` (prefill dari `internet_packages.installation_fee` bawaan paket, bisa diubah BD kalau nego harga), posting ke `business-development-verifications.verify`.

Kartu "Hasil Verifikasi CS" punya dua bentuk tergantung sudah/belum terbit:

| Kondisi | Sumber data | Badge |
|---|---|---|
| Invoice Awal sudah terbit (`$initialInvoice`, mis. pelanggan yang gate-nya sudah dilewati atau kategori tanpa gate) | `Invoice` sungguhan dari DB | Lunas / Belum Dibayar (warna sesuai `invoice_status`) |
| Invoice Awal masih tertunda (`$pendingInitialInvoice`, §3.1) | Snapshot JSON `customers.pending_initial_invoice` | "Belum Terbit" (amber) |

## 4. Skema 1 — Restriksi Paket per Role

Role dengan `roles.is_package_restricted = true` (Sales & Teknisi bawaan) cuma boleh pilih paket yang ada di `restricted_packages` (daftar **GLOBAL**, satu daftar dipakai bareng semua role restricted). `InternetPackage::scopeAvailableFor(User)` satu-satunya titik query dropdown paket di form Customer (create/edit) — **fail-open** (tampilkan semua paket aktif) selama `restricted_packages` masih kosong sama sekali, biar Sales gak terkunci sebelum BD sempat mengisi daftarnya. Validasi submit (bukan cuma sembunyikan di dropdown) menolak paket di luar daftar buat role restricted, fail-open yang sama.

Dikelola di `/business-development/package-restrictions` (checkbox paket, permission `package_restrictions.*`).

## 5. Skema 2 — Dashboard Omset Sales

`/business-development/sales-omset` (`SalesOmsetDashboardController`) — agregat per `customers.sales_user_id`, wajib POP scope. Metrik `omset_sales` (`CustomerAcquisition::getOmsetSalesAttribute()`, Biaya Langganan × 11%) **BEDA** dari `harga_dikurangi_ppn` (§1, Biaya Langganan × 89%) — dua metrik beda pemilik (Sales vs Busdev), jangan disatukan lagi (pernah salah, dikoreksi 2026-09-12).

Kolom tabel utama: Sales | Role | Jumlah Pelanggan | Total Biaya Langganan | Total Harga Dikurangi PPN | Total Omset. Modal breakdown per Sales: Pelanggan | Tanggal Aktivasi | POP | Biaya Langganan | Harga Dikurangi PPN | Omset. Filter Role/Nama sama pola dengan §1 (khusus role ber-`is_package_restricted`).

## 6. Skema 3 — FK Sales/Agent/Referral

`customers.sales_user_id` (FK `users`, dipaksa `= auth()->id()` di server buat Sales yang login — submission spoof diabaikan), `agent_id` (FK `agents` — master mitra, **bukan** akun login, dikelola BD; field hanya tampil buat actor ber-permission `agents.view`), `referral_customer_id` (FK self `customers`, autocomplete cari CID/nama existing). Kolom lama (`sales_code`/`agent_code`/`referral_customer_code` varchar) **tidak dihapus** — fallback tampilan data legacy.

## 7. Seed Data Demo

`database/seeders/BusinessDevelopmentSeeder.php` — user `busdev@whusnet.com`/`password` (role `business_development`, scope `all_pop`), 8 paket contoh masuk `restricted_packages`, 2 Master Agent, 3 pelanggan demo lengkap `CustomerService`+`CustomerAcquisition`. Idempoten penuh.

---

**Riwayat perubahan kunci:**
- 2026-09-11 — modul Customer Acquisition (§1) & Master Kategori Paket dasar (§2) lahir.
- 2026-09-12 — Skema 1-3 (§4-6), koreksi formula Omset Sales (§5).
- 2026-09-13/14 — gate "Menunggu Verifikasi BD" jadi state machine sungguhan (§3), rename penuh "Busdev"→"Business Development" di kode (UI tetap "BD").
- 2026-09-14 — koreksi Master Kategori Paket dari permission-picker ke role-picker (§2.1), Invoice Biaya Instalasi jadi tagihan sungguhan (§3.3), halaman BD reuse view CS (§3.4), **Invoice Awal kategori Bisnis ditunda sampai BD verifikasi (§3.1)**.
