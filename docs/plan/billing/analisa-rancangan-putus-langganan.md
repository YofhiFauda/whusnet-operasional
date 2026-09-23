# Analisa & Rancangan: Putus Langganan (Termination)

**Status:** Terbuka — analisa selesai 2026-09-15, direvisi 2026-09-22 (aturan denda + kolom siapa-registrasi diklarifikasi ulang user, lihat §2.1/§2.2), implementasi belum mulai. Di luar sprint aktif, dicatat sebagai ADHOC-69 di `docs/TASKS.md`.

**Sumber ide awal:** `docs/plan/billing/skema-putus-langganan.md` (skema dari user). Dokumen ini hasil review + gap analysis terhadap kode nyata + rancangan implementasi + keputusan hasil diskusi.

**Terkait:** [`analisa-rancangan-request-deaktivasi-bebas-tagihan-periode.md`](analisa-rancangan-request-deaktivasi-bebas-tagihan-periode.md) (ADHOC-87) — pilihan bulan yang tagihannya dibatalkan saat Request Deaktivasi (pengganti prorate untuk pemakaian yang tidak terjadi) + Cuti Berlangganan. Form Putus Langganan akan memakainya dalam satu transaksi.

**Terkait:** `docs/plan/billing/upgrade-downgrade/analisa-upgrade-downgrade-paket.md` — **tidak lagi berbagi helper prorate.** Keputusan user 2026-09-19: putus langganan **tanpa prorate** (lihat §2.1), jadi tidak ada rumus harian yang perlu dipakai bersama; prorate hanya milik upgrade/downgrade.

---

## 1. Ringkasan Putusan

Skema (invoice + denda terbit otomatis saat putus, kolom "siapa yang registrasi", alasan dari master data ter-filter/sort) **benar secara prinsip**, tapi **implementasinya nol** di kode sekarang — `CustomerTerminationController` cuma ubah status, gak nyentuh invoice/piutang sama sekali. 3 gap besar + keputusan hasil diskusi ada di §2–§3.

---

## 2. Gap Terhadap Kode Nyata

### 2.1 Tidak ada invoice/denda yang terbit sama sekali

`app/Http/Controllers/CustomerTerminationController.php:31-58` — begitu putus langganan, yang berubah cuma `customers.status`, `customer_services.service_status`, `AuditLog`. **Tidak ada invoice yang lahir.** Denda gak punya field maupun jalur simpan. (Pemakaian bulan berjalan sudah tertagih oleh invoice Bulanan penuh yang terbit tiap tanggal 1 — lihat keputusan tanpa prorate di bawah.)

**Solusi:** invoice **denda** terbit **otomatis** saat form putus langganan disubmit (kalau nominalnya > 0), bukan admin disuruh bikin tagihan manual terpisah setelahnya (rawan lolos ketagih).

> **Revisi 2026-09-19:** rancangan sebelumnya memakai `InvoiceType::INSIDENTAL` + `ManualInvoiceService`/`InvoiceItemBuilder` (kode lama ADHOC-60). **Tidak dipakai** — instruksi user. Taksonomi jenis tagihan sekarang (dikonfirmasi user): Aktivasi, Bulanan, Reaktivasi, dan Tagihan Manual (Perbaikan / Lainnya dengan sub diketik / Pindah Lokasi) — lihat `analisa-rancangan-tagihan-manual.md` §3.2. Putus langganan **tidak punya jenis sendiri** di daftar itu, jadi bentuk invoicenya belum diputuskan. **KEPUTUSAN 2026-09-19 (user): denda = Tagihan Manual, jenis Lainnya, sub "Denda Putus Langganan"** (`InvoiceType::MANUAL` rancangan `analisa-rancangan-tagihan-manual.md` §3.2–§3.3, di luar `SUBSCRIPTION_TYPES` sehingga tidak bentrok dengan `rejectSecondSubscriptionInvoice()` walau terbit di periode yang sama dengan tagihan Bulanan). Konsekuensi:
> - **Sub-nama diisi sistem, bukan diketik.** Di form Tagihan Manual jenis Lainnya, sub diketik bebas oleh admin; untuk denda putus langganan nilainya konstanta "Denda Putus Langganan" yang diisi Service. Pakai satu konstanta bersama, jangan diketik ulang di dua tempat, supaya laporan yang mengelompokkan per sub tidak terpecah oleh salah ketik.
> - **Bergantung pada ADHOC-70 (Tagihan Manual).** `InvoiceType::MANUAL`, enum jenis, dan kolom penyimpanan jenis/sub/deskripsi **belum ada di kode**. Bagian denda ADHOC-69 tidak bisa diimplementasi sebelum itu ada. **Keputusan urutan kerja (user, 2026-09-22): ADHOC-69 dikerjakan utuh SEKALIGUS setelah ADHOC-70 kelar** — bukan dipecah, walau secara teknis bagian master alasan/kolom siapa-registrasi/aturan ≤1 tahun sendiri tidak bergantung ADHOC-70 dan bisa saja jalan lebih dulu. Alasan: satu fitur, satu rilis, hindari List Putus tampil separuh-jadi (alasan & kolom siapa sudah ada tapi denda belum bisa disubmit).
> - **Tidak ada pembayaran saat terbit.** Rancangan Tagihan Manual §3.4 membuat Invoice **dan** Payment dalam satu submit (bayar langsung). Denda putus langganan justru tagihan yang belum dibayar: terbit `belum_dibayar`, ditagih kemudian. Jadi lapisan Service pembuat invoice manual **harus bisa jalan tanpa Payment** (pembayaran opsional di level Service, wajib hanya di form `/invoices/create`), bukan memaksa denda lewat alur bayar-langsung. Ini perlu masuk ke rancangan ADHOC-70 sebelum ia dikodekan, supaya tidak perlu dirombak.
>
> **KEPUTUSAN 2026-09-19 (user): pelanggan putus langganan TIDAK dikenai prorate.** Yang berarti:
> - Invoice Bulanan periode berjalan (terbit penuh tiap tanggal 1 oleh `billing:generate-monthly-invoices`) **dibiarkan apa adanya** — tidak dihitung ulang, tidak dibatalkan, tidak diganti. Pelanggan yang putus tanggal 15 tetap menanggung satu bulan penuh (**"pemakaian bulan berjalan"** — istilah dari `skema-putus-langganan.md` bullet 1, dikonfirmasi user 2026-09-22 maksudnya invoice Bulanan biasa ini, **bukan** invoice prorate baru).
> - Pertanyaan terbuka sebelumnya ("hitung ulang atau terbit invoice baru", risiko tagih dobel) **gugur**: tidak ada perhitungan ulang, jadi tidak ada kelebihan bayar, tidak ada keputusan deposit/refund, dan tidak ada soal siapa berhak mengubah nominal tagihan terbit.
> - Prorate hanya berlaku di upgrade/downgrade paket (ADHOC-68). Tidak ada helper prorate bersama — dua rancangan ini sengaja terpisah (dikonfirmasi ulang user 2026-09-22, sempat disangka tercampur).
>
> **Batasan yang perlu diketahui:** pelanggan aktif yang periode berjalannya *belum* punya invoice (cron tanggal 1 gagal/terlewat, atau `--period` belum ditambal) lalu diputus **tidak akan tertagih pemakaian bulan itu sama sekali** — sebelumnya rancangan prorate menutup celah ini. Diterima sebagai konsekuensi keputusan; penangkalnya operasional (pastikan cron tanggal 1 jalan), bukan kode putus langganan.
>
> **KEPUTUSAN 2026-09-22 (user, menggantikan sebagian §3.1 lama): denda cuma berlaku untuk pelanggan dengan masa langganan ≤ 1 tahun/12 bulan.** Pelanggan masa langganan **> 1 tahun tidak dikenakan denda sama sekali** — apapun alasan putusnya, tidak ada invoice denda yang terbit, cukup invoice pemakaian bulan berjalan (poin di atas) yang tetap jalan seperti biasa. Ini **bukan** cuma soal "default alasan tidak dipakai" seperti keputusan 2026-09-15 sebelumnya (§3.1a lama) — sekarang ambang 1 tahun menentukan **ada/tidaknya** denda, bukan cuma sumber nominalnya. Detail & konsekuensi ke `default_penalty_amount`: §3.1 (direvisi).

### 2.2 Kolom "pelanggan siapa" belum ditampilkan (koreksi 2026-09-22: kolomnya sudah ada, tinggal ditampilkan)

**Revisi total dari analisa 2026-09-15.** Klaim sebelumnya di dokumen ini ("sistem cuma punya `created_by` tunggal, tidak ada `sales_id`/`teknisi_id` terpisah") **salah** — sudah ketinggalan zaman saat ditulis. Dicek ulang 2026-09-22:

- **Sales** — `customers.sales_user_id` (FK ke `users`, nullable) sudah ada sejak migration `2026_09_12_090115_add_sales_agent_referral_fk_to_customers_table.php`. Komentar migration eksplisit bilang kolom ini **memang dirancang untuk hitung komisi** ("ID Sales/Agent/Referral naik level dari varchar bebas jadi FK asli, supaya bisa dipakai hitung komisi & agregasi omset") — persis kebutuhan yang disebut user (2026-09-22): "yang registrasinya perlu ditrace ... karena untuk komisi". Autofill dari user login kalau yang input Sales sendiri.
- **Teknisi Survei** — `customer_surveys.technician_id` (+ `surveyor_2_id`/`surveyor_3_id` untuk tim multi-petugas, `app/Models/CustomerSurvey.php`) sudah ada sejak S9-T002 (`docs/TASKS.md`).

**Klarifikasi user (2026-09-22):** kolom "siapa yang registrasi" **bukan** permintaan tambah field baru — user cuma menunjuk 2 pihak yang **saat ini** perlu ditrace demi komisi (Sales & Teknisi Survei). Kalau nanti nambah pihak lain yang perlu ditrace, itu perubahan terpisah di luar scope ADHOC-69 (bukan didesain sekarang, biar tidak overengineered).

**Konsekuensi ke rancangan:** tidak perlu kolom baru, tidak perlu migrasi skema tambahan untuk kebutuhan ini. Cukup:
1. List Putus (`resources/views/customers/terminated.blade.php` / `RendersCustomerList.php`) menampilkan `customer->salesUser->name` (relasi `Customer::salesUser()`, `Customer.php:134`) dan teknisi survei dari `customer->latestSurvey->technician->name` (relasi `Customer::latestSurvey()` `:465` → `CustomerSurvey::technician()` `:68`, ambil survei terbaru; survei tim 1-3 petugas, tampilkan minimal petugas utama `technician_id`; detail lengkap tim tetap ada di tab Survey Detail Pelanggan, tidak perlu diduplikasi ke List Putus).
2. `created_by` (dipakai sebelumnya di §2.2 lama untuk notif internal, `CustomerTerminationController.php:64`) **tetap dipertahankan apa adanya** untuk keperluan notifikasi — bukan diganti, cuma bukan lagi satu-satunya sumber "siapa yang registrasi" di tampilan List Putus.
3. Query List Putus perlu eager-load relasi sales & survey (`with(['salesUser', 'latestSurvey.technician'])`) supaya tidak N+1 — cek pola eager-load existing di `RendersCustomerList.php`.

### 2.3 Alasan putus = teks bebas di AuditLog, bukan master data

`reason` di controller cuma `required|string|max:500` (`CustomerTerminationController.php:28`), disimpan di `AuditLog.new_values.reason` (JSON) — **bukan** kolom `customers`. List Putus menariknya balik per-batch tiap halaman diload (`RendersCustomerList.php:229-246`), difilter/di-assign di memori PHP setelah fetch. Akibatnya:
- Tidak bisa `WHERE`/`ORDER BY` di database — filter & sort yang diminta user tidak jalan natural, apalagi kalau diterapkan setelah pagination (pagination jadi salah hasil).
- Tidak ada standardisasi teks (dua CS bisa nulis alasan yang sama dengan kata berbeda).

**Solusi:** master baru `CustomerTerminationReason`, pola sama seperti `TicketIssueCategory` (`app/Models/TicketIssueCategory.php`) — `name`, `is_active`, CRUD lewat Master controller. Kolom baru `customers.termination_reason_id` (FK), form putus langganan jadi dropdown dari master (bukan textarea bebas). Catatan tambahan bebas (detail lebih spesifik) tetap boleh ada sebagai kolom teks terpisah & opsional, tapi klasifikasi utama wajib dari master.

---

## 3. Keputusan (2026-09-15)

### 3.1 Denda: cuma untuk masa langganan ≤ 1 tahun, manual dengan prefill opsional (direvisi 2026-09-22)

> **Revisi total 2026-09-22.** Versi 2026-09-15 di bawah ini salah kerangka: menganggap ambang 1 tahun cuma menentukan *sumber nominal* (default vs manual), padahal keputusan user (§2.1) adalah ambang 1 tahun menentukan **ada/tidaknya denda itu sendiri**. Aturan final:

- **Masa langganan** = `customer_services.activation_date` s.d. tanggal putus diajukan (hari form putus disubmit). Tepat 1 tahun **masih** dianggap ≤ 1 tahun (inklusif). `activation_date` NULL (data legacy/belum lengkap) → **diperlakukan ≤ 1 tahun** (jalur paling aman, sistem tidak menagih/membebaskan otomatis dari data yang tidak lengkap).
- **Masa ≤ 1 tahun** → denda **berlaku**. Nominal **wajib diisi manual** oleh admin/CS saat submit (angka 0 sah — artinya sengaja dibebaskan meski eligible — tapi field tidak boleh kosong). Form boleh **prefill** nominal dari `default_penalty_amount` alasan terpilih sebagai titik awal (kenyamanan UI, JS ringan), tapi admin tetap **wajib mengonfirmasi/mengubahnya** sebelum submit — bukan langsung terpakai tanpa disentuh. Field submit tetap satu: nominal denda final, sistem tidak perlu tahu/membedakan "ini dari prefill" vs "ini diketik ulang".
- **Masa > 1 tahun** → **denda tidak berlaku sama sekali**. Form putus langganan **tidak menampilkan field nominal denda** untuk kasus ini (bukan field kosong/nonaktif — dihilangkan, supaya admin tidak mengira masih bisa diisi). Tidak ada invoice denda yang terbit, apapun alasan putusnya.
- Batas 1 tahun dihitung di **server** (Service), bukan cuma di JS form — kalau JS form menyimpang (mis. race, bug tampilan), server yang menang dan menolak nominal denda untuk pelanggan > 1 tahun.

**Konsekuensi ke `default_penalty_amount` di master alasan:** kolom ini **dipertahankan** di skema (§4.1), tapi perannya berubah — sekarang murni **angka prefill/titik awal** untuk form manual pelanggan ≤ 1 tahun (§3.1 di atas), **tidak pernah** dipakai otomatis untuk pelanggan > 1 tahun (karena field dendanya sendiri tidak tampil). Behavior spesifik: alasan "Kompetitor" bisa diisi default X rupiah (denda kontrak) sebagai titik awal buat kasus ≤ 1 tahun; alasan "Meninggal" defaultnya 0. Label field di form Master Alasan perlu jelas soal ini ("nilai awal untuk pelanggan ≤ 1 tahun") supaya admin tidak mengira default itu otomatis terpakai untuk semua pelanggan.

### 3.2 Alat belum kembali: dicatat, TIDAK ada denda

Tidak ada perubahan dari mekanisme existing: `device_retrieved_at` + tombol "Ambil Alat" (`terminated.blade.php:78-88`) sudah cukup — itu murni status tracking, bukan billing. Fitur invoice/denda baru **tidak boleh** ikut menyentuh alur ini; pastikan cuma ditest sebagai regresi (badge status alat tetap tampil & tombol tetap berfungsi setelah perubahan form putus langganan).

> **Update 2026-09-21 (ADHOC-86/88) — semantik alur alat berubah, uji regresi di atas tetap berlaku:** (1) tombol "Ambil Alat" hanya **membuat task DEAC**; `device_retrieved_at` terisi setelah teknisi melapor hasil "diambil" dan **dikosongkan lagi saat Langganan Lagi**; (2) badge status alat kini 3 keadaan (**Sudah Diambil** > **Sedang Diproses** > **Belum Diambil**) dan tombol hilang saat Sedang Diproses/Sudah Diambil; (3) pelanggan tanpa baris `customer_devices` tidak lagi ditolak (dibuatkan placeholder `Data Migrasi Legacy`); (4) `terminated.blade.php` & `RendersCustomerList` sekarang membawa `device_retrieval_in_progress`. Implementasi ADHOC-69 wajib mempertahankan keempat perilaku ini. Kolom alasan putus (`termination_reason_id`) yang dibangun ADHOC-69 bisa dibawa ke `description` task DEAC (sekarang teks generik) — belum dikerjakan. Detail: [`docs/warehouse/business-logic.md §12a`](../../warehouse/business-logic.md#12a-ambil-modem-deac--terima-retur-adhoc-86).

### 3.3 Tunggakan lama: dipisah, TIDAK digabung ke invoice putus

Invoice putus langganan cuma berisi **1 komponen: denda** (§3.1), kalau nominalnya > 0. **Tidak ada prorate** (keputusan 2026-09-19, §2.1) — invoice Bulanan periode berjalan tetap penuh dan berdiri sendiri, sama seperti tunggakan lama di bawah.

Tunggakan periode-periode **sebelumnya** (invoice lama `belum_dibayar`/`sebagian`) **tetap invoice terpisah apa adanya** — tidak digabung, tidak direcompute. Alasan:
- Invoice lama punya riwayat pembayaran/cicilan sendiri (`Payment::installmentContext()`); menggabung bikin ambigu pembayaran lama itu menutup bagian yang mana.
- Laporan penagihan & kolektor bekerja per-invoice — kalau digabung, nominal yang sama berpotensi kelihatan dobel di dua laporan berbeda.
- Ke pelanggan lebih jelas: "ini tunggakan bulan X" vs "ini tagihan akhir + denda karena putus" — dua alasan penagihan yang beda, jangan dicampur satu angka.

List Putus tetap boleh menampilkan **kolom total piutang** (jumlah semua invoice belum lunas milik pelanggan — invoice lama + invoice putus baru) untuk kebutuhan tampilan/CS, tapi secara data tetap baris-baris invoice yang independen.

### 3.4 Master alasan: hapus diblok kalau masih dipakai

**Keputusan:** alasan yang masih dipakai minimal 1 pelanggan **tidak bisa dihapus**; alasan yang sudah tidak dipakai siapa pun **bisa dihapus permanen**. Ini beda dari draf awal (`nullOnDelete`/soft-delete) — sekarang hard-delete tapi **dijaga di level constraint DB**:

- FK `customers.termination_reason_id` → `customer_termination_reasons.id` pakai `restrictOnDelete()` (bukan `nullOnDelete()` / `cascadeOnDelete()`).
- Di level controller/service, sebelum delete cek dulu `CustomerTerminationReason::whereHas('customers')->exists()` (atau `count()` langsung ke FK) — kalau masih dipakai, tolak dengan pesan jelas ("Alasan ini masih dipakai N pelanggan, tidak bisa dihapus") **sebelum** query DELETE dilempar, supaya errornya bukan raw SQL constraint violation yang membingungkan admin.
- RBAC: permission CRUD master ini ikut pola RBAC dinamis existing (generate dari `features` × `actions`, bukan hardcode role) — tidak perlu role baru.

---

## 4. Rancangan Implementasi

### 4.1 Skema DB baru

**Tabel `customer_termination_reasons`** (master, pola `TicketIssueCategory`):

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | | |
| `name` | string | "Pindah", "Kompetitor", "Meninggal", dst — CRUD bebas |
| `default_penalty_amount` | decimal, default 0 | prefill nominal denda (§3.1) |
| `is_active` | boolean, default true | soft-hide dari dropdown form baru (beda dari delete — is_active tetap ada untuk "sembunyikan tanpa hapus", delete tetap dicek pemakaian §3.4) |
| `created_at`/`updated_at` | | |

**Kolom baru di `customers`:**

| Kolom | Tipe | Keterangan |
|---|---|---|
| `termination_reason_id` | FK nullable, `restrictOnDelete()` | menggantikan peran `reason` teks bebas di AuditLog sebagai sumber utama |
| `termination_note` | text nullable | catatan tambahan bebas (opsional, pelengkap alasan master) |

`customers.termination_reason` yang sekarang ada di view (`terminated.blade.php:65`, virtual property dari `RendersCustomerList.php:242`) diganti jadi baca `$customer->terminationReason->name` langsung (relasi Eloquent, bisa di-`with()` — hilangkan query AuditLog terpisah untuk halaman ini).

**Invoice putus langganan:** hanya **denda** (kalau denda > 0) — tanpa prorate (§2.1). Tipe invoice **diputuskan**: Tagihan Manual (`InvoiceType::MANUAL`), jenis Lainnya, sub "Denda Putus Langganan" (§2.1). Bentuk penyimpanan jenis/sub/deskripsi di tabel `invoices` mengikuti ADHOC-70 (belum diputuskan di sana). Kode lama `INSIDENTAL`/`ManualInvoiceService`/`InvoiceItemBuilder` tidak dipakai.

### 4.2 Master Alasan Pelanggan Putus — Halaman & Controller

Pola sama seperti `Master\TicketIssueCategoryController` (`app/Http/Controllers/Master/TicketIssueCategoryController.php`) — satu bedanya: master ini **punya aksi hapus permanen** (§3.4), yang tidak dimiliki `TicketIssueCategory`.

**`Master\CustomerTerminationReasonController`** (baru):

| Method | Route | Permission | Perilaku |
|---|---|---|---|
| `index()` | `GET /master/termination-reasons` | `termination_reasons.view` | List + search nama + filter aktif/nonaktif (pola `TicketIssueCategoryController::index()`), kolom tambahan: jumlah pelanggan yang memakai (`withCount('customers')` — dipakai juga buat nge-disable tombol hapus di UI kalau count > 0, tanpa perlu klik dulu baru ketahuan ditolak). |
| `create()`/`store()` | `GET/POST /master/termination-reasons(/create)` | `termination_reasons.create` | Form: `name` (required, unique), `default_penalty_amount` (nullable, format rupiah masking — ikut konvensi `RupiahInput`, lihat `docs/billing-pembayaran/README.md` §Format Nominal), `is_active` (default true). |
| `edit()`/`update()` | `GET/PUT /master/termination-reasons/{reason}(/edit)` | `termination_reasons.update` | Sama field dengan create. |
| `toggleStatus()` | `POST /master/termination-reasons/{reason}/toggle` | `termination_reasons.update` | Nonaktifkan tanpa hapus — tetap kelihatan di data lama, hilang dari dropdown form putus langganan baru. |
| `destroy()` | `DELETE /master/termination-reasons/{reason}` | `termination_reasons.delete` | **Baru, tidak ada di pola `TicketIssueCategory`.** Cek `$reason->customers()->exists()` dulu — kalau true, redirect back dengan error jelas ("masih dipakai N pelanggan"), **jangan** lempar ke DB dan mengandalkan `restrictOnDelete()` buat nangkepnya (constraint DB tetap dipasang sebagai jaring pengaman jalur lain — tinker, SQL langsung — bukan pengganti pengecekan di controller). |

Permission `termination_reasons.*` — Feature baru di RBAC dinamis (generate dari `features`×`actions`, bukan hardcode), actions minimal `view|create|update|delete`. Assignment role: `owner`/`admin` penuh, role yang berhak proses putus langganan (mana pun yang pegang `customers.deactivate` sekarang) minimal `view` — supaya CS/admin yang lagi ngisi form putus bisa lihat daftar alasan aktif, tanpa perlu bisa CRUD-nya (dua permission berbeda, `customers.deactivate` vs `termination_reasons.*` — jangan digabung satu gate).

**Kegunaan di alur putus langganan** (link balik ke §4.3 di bawah): form putus langganan (`CustomerTerminationController`) narik `CustomerTerminationReason::active()->orderBy('name')->get()` buat dropdown alasan. Begitu admin pilih satu, `default_penalty_amount`-nya dipakai buat prefill field nominal denda di form yang sama (JS ringan, bukan submit terpisah) — mekanismenya persis §3.1.

Menu sidebar: masuk grup Master (sejajar `Master Alat Kerja`, `Kategori Material`, dll — pola existing, cek `resources/views/components/layout/sidebar.blade.php` buat posisi/`sort_order` yang konsisten).

### 4.3 Service

`CustomerTerminationController` (atau service baru `CustomerTerminationService` — sebaiknya dipisah dari controller karena logikanya sudah cukup berat: aturan denda ≤/> 1 tahun + invoice + audit, ikuti aturan CLAUDE.md "semua business logic di Service"):

1. Validasi: `termination_reason_id` wajib (dari master, bukan lagi `reason` teks bebas), `termination_note` opsional. `penalty_amount` **cuma divalidasi/diterima kalau masa langganan ≤ 1 tahun** (§3.1) — wajib diisi (0 sah), boleh diprefill dari `default_penalty_amount` alasan terpilih. **Masa > 1 tahun → `penalty_amount` diabaikan sepenuhnya di server** meski klien mengirim nilai (§3.1, guard anti tamper).
2. Hitung masa langganan & tentukan eligibilitas denda di **server** (§3.1: ≤ 1 tahun → denda wajib manual; > 1 tahun → tidak ada denda sama sekali). **Tidak ada langkah hitung prorate** (§2.1).
3. `DB::transaction()`:
   - Update `customers.status = terminated`, `terminated_at`, `termination_reason_id`, `termination_note`.
   - Update `customer_services.service_status = berhenti`.
   - **Hanya untuk masa ≤ 1 tahun dengan `penalty_amount` > 0:** terbitkan invoice denda — Tagihan Manual / Lainnya / sub "Denda Putus Langganan", status `belum_dibayar`, **tanpa Payment** (§2.1). Periode tagihan = bulan tanggal putus. Bergantung pada ADHOC-70. Masa > 1 tahun, atau ≤ 1 tahun dengan denda 0 → tidak ada invoice yang terbit sama sekali.
   - `AuditLog` seperti sekarang (tetap dicatat, tapi bukan lagi satu-satunya sumber alasan).
4. Notifikasi ke `created_by` seperti sekarang; sebut nominal invoice denda kalau terbit, atau sebut "tidak ada denda (masa langganan > 1 tahun)" kalau tidak.

### 4.4 Test yang wajib ada

- Putus langganan tengah bulan → invoice Bulanan periode berjalan **tidak berubah** (nominal, status, pembayaran utuh) dan **tidak ada invoice pemakaian/prorate baru** — cuma invoice denda (kalau eligible) yang baru (regresi keputusan tanpa prorate, §2.1).
- Masa langganan ≤ 1 tahun → field denda tampil di form, wajib diisi manual (0 sah, tapi tidak boleh kosong); boleh diprefill dari `default_penalty_amount` alasan terpilih sebagai titik awal, tapi nilai yang tersimpan = yang dikonfirmasi/diketik admin, bukan otomatis dari default tanpa disentuh. Batas tepat 1 tahun (inklusif → masih ≤ 1 tahun) dan `activation_date` NULL (→ diperlakukan ≤ 1 tahun) ikut diuji (§3.1).
- Masa langganan **> 1 tahun** → **tidak ada invoice denda yang terbit sama sekali**, apapun alasan putus & apapun `default_penalty_amount` alasan itu; server mengabaikan `penalty_amount` walau klien mengirimnya (guard anti tamper, §3.1).
- Denda (masa ≤ 1 tahun) final = 0 → **tidak ada invoice yang terbit** (bukan invoice Rp0) — sama seperti kasus > 1 tahun, cuma beda alasan.
- Putus langganan TIDAK menyentuh invoice tunggakan lama — invoice periode sebelumnya tetap `belum_dibayar`/`sebagian` apa adanya, nominalnya tidak berubah (regresi §3.3).
- Alat belum dikembalikan saat putus — badge & tombol "Ambil Alat" tetap berfungsi setelah perubahan (regresi §3.2).
- List Putus menampilkan nama Sales (`customer->salesUser->name`) & Teknisi Survei (`customer->latestSurvey->technician->name`) dari relasi existing, termasuk kasus null (pelanggan tanpa sales/survei tercatat → tampil "-", bukan error) — regresi/verifikasi §2.2 (koreksi 2026-09-22, bukan kolom baru).
- Master alasan: hapus alasan yang masih dipakai ≥1 pelanggan → ditolak dengan pesan jelas, bukan 500 dari FK violation.
- Master alasan: hapus alasan yang tidak dipakai siapa pun → berhasil.
- Master alasan: CRUD dasar (create/update/toggle) — pola sama pengujian `TicketIssueCategory` yang sudah ada: unique name, toggle aktif/nonaktif menghilangkan dari dropdown form putus tapi data lama tetap utuh.
- Master alasan: gate permission `termination_reasons.view|create|update|delete` terpisah dari `customers.deactivate` — role yang cuma pegang `customers.deactivate` bisa lihat dropdown alasan tapi tidak bisa akses halaman Master-nya.
- List Putus: filter & sort berdasarkan `termination_reason_id` bekerja di level query (bukan di memori setelah fetch) — regresi terhadap performa/pagination.
- POP scope: `CustomerTerminatedController`/list tetap tunduk scope existing (regresi, bukan test baru).

### 4.5 Migrasi data lama

**Keputusan (user, 2026-09-22): Opsi A — dibiarkan kosong.** Data pelanggan `terminated` yang sudah ada sekarang alasannya cuma ada di `AuditLog.new_values.reason` (teks bebas, tidak terstruktur). `termination_reason_id` data lama **dibiarkan `NULL`** (tampil "-" di kolom Alasan pada List Putus & filter). **Tidak ada backfill/mapping manual** dari teks bebas lama ke master alasan baru — laporan/filter alasan cuma akurat untuk pelanggan yang putus **setelah** fitur ini live; data historis sebelum itu tetap "-" selamanya, tidak dikejar retroaktif.

---

## 5. Dampak ke Modul Lain (perlu dicek saat implementasi)

- `InvoiceObserver::creating()` — dua guard harus lolos untuk invoice putus langganan: (a) `rejectSecondSubscriptionInvoice()` (menolak invoice langganan kedua per pelanggan+periode) dan (b) guard dedup 5 menit `customer+type+billing_period+total_amount`. Tipe invoice sudah dipilih (Tagihan Manual, di luar `SUBSCRIPTION_TYPES`, §2.1), jadi (a) tidak berlaku; yang tinggal (b) dedup 5 menit — dua denda dengan nominal sama untuk pelanggan+periode yang sama dalam 5 menit akan ditolak. Wajib diverifikasi test, termasuk denda terbit di periode yang sama dengan invoice Bulanan pelanggan itu.
- `CustomerWorkflowService`/`WorkflowTransition` — pastikan transisi ke `terminated` tetap konsisten dengan state machine yang ada, invoice generation ini nambah langkah baru di jalur yang sama, bukan jalur baru terpisah. **Update 2026-09-19 (ADHOC-85):** `CustomerTerminationController` sekarang sudah lewat `transition()` + pre-check status; audit `customers`/`terminate` masih ditulis karena dibaca list & import legacy. Service baru di rancangan ini harus mewarisi pre-check itu dan baru boleh menghentikan audit `terminate` setelah `RendersCustomerList` membaca `termination_reason_id`. Lihat [`rancangan-terminate-reactivate-state-machine.md`](rancangan-terminate-reactivate-state-machine.md) §7.
- `docs/billing-pembayaran/` & dokumentasi modul pelanggan — begitu diimplementasi, update README/business-logic sesuai `docs/DEFINITION_OF_DONE.md`.
