# Analisa & Rancangan: Putus Langganan (Termination)

**Status:** Terbuka — analisa selesai 2026-09-15, implementasi belum mulai. Di luar sprint aktif, dicatat sebagai ADHOC-69 di `docs/TASKS.md`.

**Sumber ide awal:** `docs/plan/billing/skema-putus-langganan.md` (skema dari user). Dokumen ini hasil review + gap analysis terhadap kode nyata + rancangan implementasi + keputusan hasil diskusi.

**Terkait:** `docs/plan/upgrade-downgrade/analisa-upgrade-downgrade-paket.md` — prorate harian pemakaian dipakai bareng di dua skema ini, sebaiknya jadi satu helper/service yang di-share, bukan ditulis dua kali.

---

## 1. Ringkasan Putusan

Skema (invoice + denda terbit otomatis saat putus, kolom "siapa yang registrasi", alasan dari master data ter-filter/sort) **benar secara prinsip**, tapi **implementasinya nol** di kode sekarang — `CustomerTerminationController` cuma ubah status, gak nyentuh invoice/piutang sama sekali. 3 gap besar + keputusan hasil diskusi ada di §2–§3.

---

## 2. Gap Terhadap Kode Nyata

### 2.1 Tidak ada invoice/denda yang terbit sama sekali

`app/Http/Controllers/CustomerTerminationController.php:31-58` — begitu putus langganan, yang berubah cuma `customers.status`, `customer_services.service_status`, `AuditLog`. **Tidak ada invoice yang lahir.** Piutang pemakaian bulan berjalan (tanggal 1 s.d. tanggal putus) hilang gitu aja kalau invoice periode ini belum digenerate; denda gak punya field maupun jalur simpan.

**Solusi:** pakai `InvoiceType::INSIDENTAL` (`app/Enums/InvoiceType.php:11-23`) — tipe ini sudah dirancang khusus untuk "tagihan di luar langganan: jasa perbaikan, biaya instalasi tambahan, denda, dsb", dan sudah dikecualikan dari guard "satu tagihan langganan per periode" (`Invoice::SUBSCRIPTION_TYPES`) sehingga bisa terbit bareng tagihan bulanan biasa di periode yang sama. Generate lewat `ManualInvoiceService` (pola yang sama dipakai `CustomerAcquisition::installationFeeInvoice()` untuk biaya instalasi Busdev) — **otomatis** saat form putus langganan disubmit, bukan admin disuruh bikin tagihan manual terpisah setelahnya (rawan lolos ketagih).

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

### 3.2 Alat belum kembali: dicatat, TIDAK ada denda

Tidak ada perubahan dari mekanisme existing: `device_retrieved_at` + tombol "Ambil Alat" (`terminated.blade.php:78-88`) sudah cukup — itu murni status tracking, bukan billing. Fitur invoice/denda baru **tidak boleh** ikut menyentuh alur ini; pastikan cuma ditest sebagai regresi (badge status alat tetap tampil & tombol tetap berfungsi setelah perubahan form putus langganan).

### 3.3 Tunggakan lama: dipisah, TIDAK digabung ke invoice putus

Invoice putus langganan cuma berisi 2 komponen:
1. Prorate pemakaian bulan berjalan s.d. tanggal putus (pakai basis prorate harian yang sama dengan skema upgrade/downgrade — lihat dokumen terkait §3, `harga_harian = monthly_price / hari_dalam_periode`).
2. Denda (§3.1), kalau nominalnya > 0.

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

**Invoice putus langganan:** tidak perlu tabel baru — pakai `Invoice` existing dengan `invoice_type = InvoiceType::INSIDENTAL`, item-nya dipecah 2 baris (prorate pemakaian + denda, kalau denda > 0) lewat `ManualInvoiceService`/`InvoiceItemBuilder` yang sudah ada.

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

`CustomerTerminationController` (atau service baru `CustomerTerminationService` — sebaiknya dipisah dari controller karena logikanya sudah cukup berat: prorate + invoice + audit, ikuti aturan CLAUDE.md "semua business logic di Service"):

1. Validasi: `termination_reason_id` wajib (dari master, bukan lagi `reason` teks bebas), `termination_note` opsional, `penalty_amount` (prefill dari `default_penalty_amount` alasan terpilih, editable).
2. Hitung prorate pemakaian s.d. hari ini: `hari_terpakai × harga_harian` dari `customer_service` aktif — reuse helper yang sama dengan upgrade/downgrade (§lihat dokumen terkait, jangan duplikasi rumus).
3. `DB::transaction()`:
   - Update `customers.status = terminated`, `terminated_at`, `termination_reason_id`, `termination_note`.
   - Update `customer_services.service_status = berhenti`.
   - Generate invoice `INSIDENTAL` via `ManualInvoiceService` (baris prorate + baris denda kalau > 0) — kalau prorate = 0 dan denda = 0, tetap boleh skip generate invoice (tidak ada yang perlu ditagih).
   - `AuditLog` seperti sekarang (tetap dicatat, tapi bukan lagi satu-satunya sumber alasan).
4. Notifikasi ke `created_by` seperti sekarang, sebut nominal invoice yang terbit di pesannya.

### 4.4 Test yang wajib ada

- Putus langganan tengah bulan → invoice `INSIDENTAL` terbit dengan nominal prorate benar (hari terpakai × harga harian).
- Denda terisi default dari alasan terpilih, tapi bisa di-override manual sebelum submit — override yang tersimpan, bukan default.
- Alasan dengan `default_penalty_amount = 0` (mis. "Meninggal") → invoice tidak ada baris denda, atau baris denda Rp0 (tentukan salah satu konsisten, rekomendasi: skip baris kalau 0, bukan tampilkan baris Rp0).
- Putus langganan TIDAK menyentuh invoice tunggakan lama — invoice periode sebelumnya tetap `belum_dibayar`/`sebagian` apa adanya, nominalnya tidak berubah (regresi §3.3).
- Alat belum dikembalikan saat putus — badge & tombol "Ambil Alat" tetap berfungsi setelah perubahan (regresi §3.2).
- Master alasan: hapus alasan yang masih dipakai ≥1 pelanggan → ditolak dengan pesan jelas, bukan 500 dari FK violation.
- Master alasan: hapus alasan yang tidak dipakai siapa pun → berhasil.
- Master alasan: CRUD dasar (create/update/toggle) — pola sama `RevenueCategoryMasterTest`/pengujian `TicketIssueCategory` yang sudah ada: unique name, toggle aktif/nonaktif menghilangkan dari dropdown form putus tapi data lama tetap utuh.
- Master alasan: gate permission `termination_reasons.view|create|update|delete` terpisah dari `customers.deactivate` — role yang cuma pegang `customers.deactivate` bisa lihat dropdown alasan tapi tidak bisa akses halaman Master-nya.
- List Putus: filter & sort berdasarkan `termination_reason_id` bekerja di level query (bukan di memori setelah fetch) — regresi terhadap performa/pagination.
- Kolom "Didaftarkan Oleh" (`created_by`) tampil benar di List Putus.
- POP scope: `CustomerTerminatedController`/list tetap tunduk scope existing (regresi, bukan test baru).

### 4.5 Migrasi data lama

Data pelanggan `terminated` yang sudah ada sekarang alasannya cuma ada di `AuditLog.new_values.reason` (teks bebas, tidak terstruktur). Perlu diputuskan (belum, saat implementasi nanti): apakah data lama dibiarkan `termination_reason_id = NULL` (tampil "-" di kolom baru), atau di-backfill manual/semi-otomatis (mapping teks bebas → master baru, butuh review manusia karena variasi penulisan tidak seragam — lihat pola masalah serupa di `docs/billing-pembayaran/analisa-duplikasi-tagihan-pembayaran-migrasi-legacy.md` soal data legacy yang tidak seragam).

---

## 5. Dampak ke Modul Lain (perlu dicek saat implementasi)

- `InvoiceObserver::creating()` — pastikan invoice `INSIDENTAL` baru ini tidak konflik dengan invoice `INSIDENTAL` lain yang mungkin sudah ada di periode yang sama untuk pelanggan tsb (mis. biaya instalasi Busdev yang belum lunas) — guard dedup 5 menit `InvoiceObserver` cek `customer+type+billing_period+total_amount`, kombinasi denda+prorate biasanya unik nominalnya jadi kemungkinan aman, tapi wajib diverifikasi test.
- `CustomerWorkflowService`/`WorkflowTransition` — pastikan transisi ke `terminated` tetap konsisten dengan state machine yang ada, invoice generation ini nambah langkah baru di jalur yang sama, bukan jalur baru terpisah.
- `docs/billing-pembayaran/` & dokumentasi modul pelanggan — begitu diimplementasi, update README/business-logic sesuai `docs/DEFINITION_OF_DONE.md`.
