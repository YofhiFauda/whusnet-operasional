# Analisa & Rancangan: Putus Langganan (Termination)

**Status:** Terbuka — analisa selesai 2026-09-15, implementasi belum mulai. Di luar sprint aktif, dicatat sebagai ADHOC-69 di `docs/TASKS.md`.

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
> - **Bergantung pada ADHOC-70 (Tagihan Manual).** `InvoiceType::MANUAL`, enum jenis, dan kolom penyimpanan jenis/sub/deskripsi **belum ada di kode**. Bagian denda ADHOC-69 tidak bisa diimplementasi sebelum itu ada; bagian lain (master alasan, kolom "didaftarkan oleh", aturan ≤/> 1 tahun) tidak bergantung dan boleh jalan duluan.
> - **Tidak ada pembayaran saat terbit.** Rancangan Tagihan Manual §3.4 membuat Invoice **dan** Payment dalam satu submit (bayar langsung). Denda putus langganan justru tagihan yang belum dibayar: terbit `belum_dibayar`, ditagih kemudian. Jadi lapisan Service pembuat invoice manual **harus bisa jalan tanpa Payment** (pembayaran opsional di level Service, wajib hanya di form `/invoices/create`), bukan memaksa denda lewat alur bayar-langsung. Ini perlu masuk ke rancangan ADHOC-70 sebelum ia dikodekan, supaya tidak perlu dirombak.
>
> **KEPUTUSAN 2026-09-19 (user): pelanggan putus langganan TIDAK dikenai prorate.** Yang berarti:
> - Invoice Bulanan periode berjalan (terbit penuh tiap tanggal 1 oleh `billing:generate-monthly-invoices`) **dibiarkan apa adanya** — tidak dihitung ulang, tidak dibatalkan, tidak diganti. Pelanggan yang putus tanggal 15 tetap menanggung satu bulan penuh.
> - Tidak ada invoice pemakaian baru saat putus. **Satu-satunya invoice yang lahir dari putus langganan adalah denda** (§3.1), dan hanya kalau nominalnya > 0.
> - Pertanyaan terbuka sebelumnya ("hitung ulang atau terbit invoice baru", risiko tagih dobel) **gugur**: tidak ada perhitungan ulang, jadi tidak ada kelebihan bayar, tidak ada keputusan deposit/refund, dan tidak ada soal siapa berhak mengubah nominal tagihan terbit.
> - Prorate hanya berlaku di upgrade/downgrade paket. Tidak ada helper prorate bersama.
>
> **Batasan yang perlu diketahui:** pelanggan aktif yang periode berjalannya *belum* punya invoice (cron tanggal 1 gagal/terlewat, atau `--period` belum ditambal) lalu diputus **tidak akan tertagih pemakaian bulan itu sama sekali** — sebelumnya rancangan prorate menutup celah ini. Diterima sebagai konsekuensi keputusan; penangkalnya operasional (pastikan cron tanggal 1 jalan), bukan kode putus langganan.

### 2.2 Kolom "pelanggan siapa" belum ditampilkan

`customers.created_by` sudah ada (dipakai `CustomerTerminationController.php:64` untuk notif internal), tapi tidak pernah muncul sebagai kolom di List Putus (`resources/views/customers/terminated.blade.php`). Perlu ditambahkan.

**Catatan:** sistem cuma punya `created_by` tunggal — tidak ada kolom `sales_id`/`teknisi_id` terpisah di `customers` maupun `CustomerAcquisition`. Kalau "siapa yang registrasi" cukup diwakili satu orang (siapa yang input data awal pelanggan), `created_by` sudah cukup. Kalau perlu breakdown Sales vs Teknisi Survei sebagai dua peran berbeda, itu perubahan skema data lebih besar di luar scope putus-langganan — **belum diputuskan, dianggap `created_by` cukup untuk sekarang** kecuali user bilang lain.

### 2.3 Alasan putus = teks bebas di AuditLog, bukan master data

`reason` di controller cuma `required|string|max:500` (`CustomerTerminationController.php:28`), disimpan di `AuditLog.new_values.reason` (JSON) — **bukan** kolom `customers`. List Putus menariknya balik per-batch tiap halaman diload (`RendersCustomerList.php:229-246`), difilter/di-assign di memori PHP setelah fetch. Akibatnya:
- Tidak bisa `WHERE`/`ORDER BY` di database — filter & sort yang diminta user tidak jalan natural, apalagi kalau diterapkan setelah pagination (pagination jadi salah hasil).
- Tidak ada standardisasi teks (dua CS bisa nulis alasan yang sama dengan kata berbeda).

**Solusi:** master baru `CustomerTerminationReason`, pola sama seperti `TicketIssueCategory` (`app/Models/TicketIssueCategory.php`) — `name`, `is_active`, CRUD lewat Master controller. Kolom baru `customers.termination_reason_id` (FK), form putus langganan jadi dropdown dari master (bukan textarea bebas). Catatan tambahan bebas (detail lebih spesifik) tetap boleh ada sebagai kolom teks terpisah & opsional, tapi klasifikasi utama wajib dari master.

---

## 3. Keputusan (2026-09-15)

### 3.1 Denda: manual bebas + default per alasan

Setiap `CustomerTerminationReason` punya kolom `default_penalty_amount` (nullable/0 = tidak ada default). Saat form putus langganan dibuka, nominal denda **di-prefill** dari default alasan yang dipilih, tapi **admin tetap bisa mengubahnya manual** sebelum submit (bukan dikunci). Field submit tetap satu: nominal denda final (apapun asalnya, default terpakai atau override manual) — sistem tidak perlu tahu/membedakan "ini default" vs "ini override" di baris invoice, cukup catat nominal final yang terbit.

Behavior spesifik: alasan "Kompetitor" defaultnya bisa diisi X rupiah (denda kontrak), alasan "Meninggal" defaultnya 0 (gratis) — keduanya cuma beda nilai default di master, mekanismenya sama.

#### 3.1a Masa langganan ≤ 1 tahun: default denda alasan TIDAK dipakai (aturan tambahan, 2026-09-19)

Sumber: bullet ke-4 `docs/plan/billing/skema-putus-langganan.md`. Aturan ini **menggantikan** prefill §3.1 untuk pelanggan yang putus dini — bukan tambahan di atas denda alasan.

- **Masa langganan** = `customer_services.activation_date` s.d. tanggal putus diajukan (hari form putus disubmit).
- **Masa ≤ 1 tahun** → `default_penalty_amount` alasan **tidak** di-prefill dan **tidak** dipakai sebagai nilai otomatis; admin/CS mengisi nominal denda manual per kasus saat submit.
- **Masa > 1 tahun** → perilaku §3.1 tetap (prefill dari default alasan, boleh di-override).
- Batas dihitung di **server** (Service), bukan cuma di JS form — prefill di form hanya kenyamanan UI. Kalau dua sisi menyimpang, server yang menang.
- Konsekuensi ke master: `default_penalty_amount` sekarang efektif cuma berlaku untuk pelanggan > 1 tahun. Label field di form Master Alasan perlu jelas soal ini supaya admin tidak mengira default itu selalu terpakai.

**Keputusan (user, 2026-09-19):**
1. Masa langganan dihitung `activation_date` → tanggal putus diajukan; tepat 1 tahun **masih** dianggap ≤ 1 tahun (inklusif, sesuai teks "≤ 1 tahun").
2. Masa ≤ 1 tahun → nominal denda **wajib diisi manual** (angka 0 sah, tapi tidak boleh kosong). Masa > 1 tahun → tidak wajib manual, default alasan dipakai (§3.1).
3. `activation_date` NULL (data legacy/belum lengkap) → **diperlakukan ≤ 1 tahun** (manual). Alasan: jalur paling aman, sistem tidak menagih otomatis dari data yang tidak lengkap.

### 3.2 Alat belum kembali: dicatat, TIDAK ada denda

Tidak ada perubahan dari mekanisme existing: `device_retrieved_at` + tombol "Ambil Alat" (`terminated.blade.php:78-88`) sudah cukup — itu murni status tracking, bukan billing. Fitur invoice/denda baru **tidak boleh** ikut menyentuh alur ini; pastikan cuma ditest sebagai regresi (badge status alat tetap tampil & tombol tetap berfungsi setelah perubahan form putus langganan).

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

1. Validasi: `termination_reason_id` wajib (dari master, bukan lagi `reason` teks bebas), `termination_note` opsional, `penalty_amount` (prefill dari `default_penalty_amount` alasan terpilih, editable — **kecuali masa langganan ≤ 1 tahun**, lihat §3.1a: tanpa prefill, diisi manual).
2. Hitung nominal denda final di **server** (§3.1/§3.1a: masa langganan ≤ 1 tahun → wajib input manual, > 1 tahun → boleh default alasan). **Tidak ada langkah hitung prorate** (§2.1).
3. `DB::transaction()`:
   - Update `customers.status = terminated`, `terminated_at`, `termination_reason_id`, `termination_note`.
   - Update `customer_services.service_status = berhenti`.
   - Terbitkan invoice denda: Tagihan Manual / Lainnya / sub "Denda Putus Langganan", status `belum_dibayar`, **tanpa Payment** (§2.1) — hanya kalau denda > 0; denda 0 → tidak ada invoice yang terbit. Periode tagihan = bulan tanggal putus. Bergantung pada ADHOC-70.
   - `AuditLog` seperti sekarang (tetap dicatat, tapi bukan lagi satu-satunya sumber alasan).
4. Notifikasi ke `created_by` seperti sekarang, sebut nominal invoice yang terbit di pesannya.

### 4.4 Test yang wajib ada

- Putus langganan tengah bulan → invoice Bulanan periode berjalan **tidak berubah** (nominal, status, pembayaran utuh) dan **tidak ada invoice pemakaian/prorate baru**; satu-satunya invoice baru adalah denda (regresi keputusan tanpa prorate, §2.1).
- Denda terisi default dari alasan terpilih, tapi bisa di-override manual sebelum submit — override yang tersimpan, bukan default.
- Masa langganan ≤ 1 tahun (§3.1a) → default denda alasan **tidak** dipakai; nominal yang tersimpan = input manual. Batas tepat 1 tahun (inklusif → masih manual) dan `activation_date` NULL (→ manual) ikut diuji sesuai keputusan §3.1a. Submit ≤ 1 tahun dengan nominal denda kosong → ditolak validasi.
- Masa langganan > 1 tahun → default denda alasan tetap dipakai (prefill, boleh override) — regresi §3.1.
- Server menolak/mengabaikan nominal default yang dikirim klien untuk pelanggan ≤ 1 tahun (batas dihitung di Service, bukan JS).
- Denda final = 0 (mis. alasan "Meninggal", atau input manual 0) → **tidak ada invoice yang terbit sama sekali** (bukan invoice Rp0), karena tanpa prorate denda adalah satu-satunya isi invoice putus langganan.
- Putus langganan TIDAK menyentuh invoice tunggakan lama — invoice periode sebelumnya tetap `belum_dibayar`/`sebagian` apa adanya, nominalnya tidak berubah (regresi §3.3).
- Alat belum dikembalikan saat putus — badge & tombol "Ambil Alat" tetap berfungsi setelah perubahan (regresi §3.2).
- Master alasan: hapus alasan yang masih dipakai ≥1 pelanggan → ditolak dengan pesan jelas, bukan 500 dari FK violation.
- Master alasan: hapus alasan yang tidak dipakai siapa pun → berhasil.
- Master alasan: CRUD dasar (create/update/toggle) — pola sama pengujian `TicketIssueCategory` yang sudah ada: unique name, toggle aktif/nonaktif menghilangkan dari dropdown form putus tapi data lama tetap utuh.
- Master alasan: gate permission `termination_reasons.view|create|update|delete` terpisah dari `customers.deactivate` — role yang cuma pegang `customers.deactivate` bisa lihat dropdown alasan tapi tidak bisa akses halaman Master-nya.
- List Putus: filter & sort berdasarkan `termination_reason_id` bekerja di level query (bukan di memori setelah fetch) — regresi terhadap performa/pagination.
- Kolom "Didaftarkan Oleh" (`created_by`) tampil benar di List Putus.
- POP scope: `CustomerTerminatedController`/list tetap tunduk scope existing (regresi, bukan test baru).

### 4.5 Migrasi data lama

Data pelanggan `terminated` yang sudah ada sekarang alasannya cuma ada di `AuditLog.new_values.reason` (teks bebas, tidak terstruktur). Perlu diputuskan (belum, saat implementasi nanti): apakah data lama dibiarkan `termination_reason_id = NULL` (tampil "-" di kolom baru), atau di-backfill manual/semi-otomatis (mapping teks bebas → master baru, butuh review manusia karena variasi penulisan tidak seragam — lihat pola masalah serupa di `docs/billing-pembayaran/analisa-duplikasi-tagihan-pembayaran-migrasi-legacy.md` soal data legacy yang tidak seragam).

---

## 5. Dampak ke Modul Lain (perlu dicek saat implementasi)

- `InvoiceObserver::creating()` — dua guard harus lolos untuk invoice putus langganan: (a) `rejectSecondSubscriptionInvoice()` (menolak invoice langganan kedua per pelanggan+periode) dan (b) guard dedup 5 menit `customer+type+billing_period+total_amount`. Tipe invoice sudah dipilih (Tagihan Manual, di luar `SUBSCRIPTION_TYPES`, §2.1), jadi (a) tidak berlaku; yang tinggal (b) dedup 5 menit — dua denda dengan nominal sama untuk pelanggan+periode yang sama dalam 5 menit akan ditolak. Wajib diverifikasi test, termasuk denda terbit di periode yang sama dengan invoice Bulanan pelanggan itu.
- `CustomerWorkflowService`/`WorkflowTransition` — pastikan transisi ke `terminated` tetap konsisten dengan state machine yang ada, invoice generation ini nambah langkah baru di jalur yang sama, bukan jalur baru terpisah. **Update 2026-09-19 (ADHOC-85):** `CustomerTerminationController` sekarang sudah lewat `transition()` + pre-check status; audit `customers`/`terminate` masih ditulis karena dibaca list & import legacy. Service baru di rancangan ini harus mewarisi pre-check itu dan baru boleh menghentikan audit `terminate` setelah `RendersCustomerList` membaca `termination_reason_id`. Lihat [`rancangan-terminate-reactivate-state-machine.md`](rancangan-terminate-reactivate-state-machine.md) §7.
- `docs/billing-pembayaran/` & dokumentasi modul pelanggan — begitu diimplementasi, update README/business-logic sesuai `docs/DEFINITION_OF_DONE.md`.
