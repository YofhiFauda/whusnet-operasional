# Plan Implementasi Migrasi Pelanggan dan Billing

## Tujuan

Dokumen ini menjadi rencana implementasi teknis berdasarkan:

- `docs/ANALISIS_SCOPE_MIGRASI_PELANGGAN_BILLING.md`
- `docs/SCOPE_MIGRASI_PELANGGAN_BILLING.md`
- `sand_db_sandya.sql`

Fokus tahap ini adalah menyesuaikan website baru agar dapat menampung, menampilkan, dan memakai data lama untuk:

1. Data pelanggan.
2. Data layanan pelanggan.
3. Master paket internet.
4. Tagihan/billing.
5. Pembayaran.
6. Detail teknis lama sebagai informasi pelanggan.

Tahap ini tidak membuat sistem ISP lengkap. Target utamanya adalah migrasi data lama dan billing manual.

---

## Scope Implementasi

### Masuk Scope

- Import Excel multi-sheet dari hasil ekstrak `sand_db_sandya.sql`.
- Mapping data pelanggan lama ke `customers` dan `customer_addresses`.
- Mapping paket lama ke `internet_packages`.
- Mapping request/langganan lama ke `customer_services`.
- Mapping data teknis lama ke `customer_technical_details`.
- Mapping biaya/tagihan lama ke `invoices`.
- Mapping transaksi pembayaran lama ke `payments`.
- Tampilan detail pelanggan menampilkan data pelanggan, layanan, tagihan, pembayaran, dan teknis lama.
- Data lama yang belum lengkap tetap bisa masuk sebagai `perlu_dilengkapi`.
- Billing tetap manual setelah data masuk.

### Tidak Masuk Scope

- Integrasi MikroTik.
- Payment gateway.
- Auto suspend pelanggan.
- Auto generate invoice bulanan kompleks.
- WhatsApp notification.
- Ticketing gangguan.
- Monitoring OLT/SNMP.
- Inventory perangkat kompleks.
- Workflow teknisi lapangan lengkap.
- Modul keuangan/jurnal lengkap.
- Multi-company.

---

## Fase Implementasi

### Fase 1 - Kunci Scope dan Dokumen Kontrol

Tujuan:

Menjadikan scope migrasi pelanggan dan billing sebagai acuan kerja tahap sekarang.

Langkah:

1. Jadikan `docs/SCOPE_MIGRASI_PELANGGAN_BILLING.md` sebagai acuan utama implementasi migrasi.
2. Jadikan `docs/Website_Billing_ISP_PRD.md` sebagai roadmap besar, bukan acuan harian untuk tahap ini.
3. Tambahkan task baru di `docs/TASKS.md` hanya setelah pekerjaan implementasi akan dimulai.
4. Jangan menambah modul post-MVP selama fase migrasi belum stabil.

Output:

- Scope implementasi jelas.
- Tidak ada fitur baru di luar pelanggan dan billing.

---

### Fase 2 - Lengkapi Field Legacy Minimal

Tujuan:

Memastikan database baru dapat menyimpan data lama yang penting tanpa meniru seluruh struktur database lama.

Field yang perlu dipastikan tersedia:

| Data | Field Baru |
| --- | --- |
| Pelanggan | `old_customer_id`, `customer_type`, `company_name`, `npwp`, `old_account_status` |
| Alamat | `old_region_id`, `old_branch_id` |
| Paket | `old_package_id` |
| Layanan | `old_request_id`, `old_cost_id`, `request_status`, `installation_status`, `network_type`, `member_type`, `reason` |
| Invoice | `old_invoice_id`, `old_cost_id`, `old_request_id` |
| Payment | `old_payment_id`, `old_transaction_id`, `old_request_id`, `billing_period`, `received_by_old`, `deposited_by_old` |
| Teknis | `old_report_id`, `old_customer_id`, `old_request_id` |

Catatan:

- Jangan membuat tabel OLT, router, inventory, ticketing, atau payment gateway.
- Data teknis lama cukup masuk sebagai informasi detail pelanggan.

Output:

- Struktur data siap menerima hasil ekstrak dari `sand_db_sandya.sql`.

---

### Fase 3 - Perbaiki Template Import Multi-Sheet

Tujuan:

Membuat template import yang konsisten dengan kebutuhan migrasi lama.

Sheet yang digunakan:

1. `customers`
2. `packages`
3. `services`
4. `technical_details`
5. `invoices`
6. `payments`

Aturan:

- Download template harus mengikuti format multi-sheet, bukan CSV pelanggan sederhana.
- Jika export Excel multi-sheet belum praktis, sediakan minimal contoh CSV per sheet.
- Header harus sama dengan `docs/SCOPE_MIGRASI_PELANGGAN_BILLING.md`.

Output:

- Admin memiliki template yang sesuai untuk migrasi data lama.
- Template frontend dan backend konsisten.

---

### Fase 4 - Longgarkan Validasi Import Pelanggan Lama

Tujuan:

Data pelanggan lama tidak gagal import hanya karena data belum lengkap.

Aturan validasi pelanggan:

- Wajib:
  - `old_customer_id`
  - minimal salah satu dari `full_name`, `phone`, `identity_number`, atau `full_address`
- Tidak boleh langsung ditolak hanya karena:
  - `phone` kosong
  - `phone` bernilai string `null`
  - `city` kosong
  - `district` kosong
  - `village` kosong
  - wilayah tidak ditemukan di master region
  - alamat tidak terstruktur

Aturan status:

- Jika field billing wajib belum lengkap, set `data_completeness_status = perlu_dilengkapi`.
- Jika data cukup lengkap untuk billing, set sesuai hasil validasi existing.
- Data yang benar-benar tidak bisa diidentifikasi tetap masuk `import_errors`.

Output:

- Pelanggan lama dari `pengguna` bisa masuk walaupun datanya belum rapi.

---

### Fase 5 - Mapping Status Legacy

Tujuan:

Status dari database lama dapat diterima dan dipetakan ke status baru.

Mapping utama:

| Status Lama | Status Baru | Catatan |
| --- | --- | --- |
| `ACTIVE` | `aktif` / `active` | Pelanggan aktif |
| `PUTUS` | `berhenti` / `terminated` | Layanan berhenti |
| `GAGAL` | `gagal` atau `nonaktif` / `rejected` | Tidak lanjut pasang |
| `DISURVEI` | `survey` / `waiting_survey` | Sudah/sedang survey |
| `PENGAJUAN` | `calon_pelanggan` / `registered` | Baru pengajuan |
| kosong/null | `belum_diketahui` atau `registered` | Perlu review |

Aturan:

- Status lama tidak boleh membuat import gagal selama relasi pelanggan dan layanan masih bisa dikenali.
- Status asli tetap disimpan sebagai data legacy jika field tersedia.

Output:

- Layanan lama dari `prosedure_permintaan_wifi` bisa dipakai tanpa manual cleanup besar.

---

### Fase 6 - Mapping Paket dan Layanan

Tujuan:

Paket dan layanan lama menjadi dasar pelanggan dan invoice baru.

Mapping paket dari `paket`:

| Sumber Lama | Target Baru |
| --- | --- |
| `KODEPAKET` | `internet_packages.old_package_id` |
| `NAMA_PAKET` | `internet_packages.name` |
| `JENIS_PAKET` | `package_group` atau field legacy |
| `KATEGORI_PAKET` | `category` |
| `HARGA` | `monthly_price` |
| `SPEEDUP` | `upload_speed_mbps` |
| `SPEEDDOWN` | `download_speed_mbps` |
| `PROFILOLT` | field legacy/technical profile |
| `PROFILPPP` | field legacy/technical profile |
| `KETERANGAN` | `description` |

Mapping layanan dari `prosedure_permintaan_wifi`:

| Sumber Lama | Target Baru |
| --- | --- |
| `IDPERMINTAAN` | `customer_services.old_request_id` |
| `IDPENGGUNA` | relasi ke `customers.old_customer_id` |
| `IDPAKET` | relasi ke `internet_packages.old_package_id` |
| `IDBIAYA` | `customer_services.old_cost_id` |
| `STATUS` | `request_status` dan status hasil mapping |
| `STATUSPASANG` | `installation_status` |
| `TGL_AKTIFPUTUS` | tanggal aktivasi/putus jika valid |
| `TGLSURVEY`, `TGLDIACC`, `TGLDIPROSES`, `TGLSELESAI` | histori tanggal layanan |
| `JENISJARINGAN` | `network_type` |
| `ALASAN` | `reason` |

Output:

- Setiap pelanggan lama dapat memiliki layanan lama yang terhubung ke paket.
- Harga layanan disimpan sebagai snapshot.

---

### Fase 7 - Mapping Invoice Historis

Tujuan:

Biaya dan tagihan lama bisa tampil sebagai invoice historis.

Sumber data:

- `biaya_tagihan`
- `penagihan`
- `apikeuangan_buktitransaksitagihan`

Aturan pembentukan invoice:

1. Jika ada `penagihan.IDTAGIHAN`, gunakan sebagai `old_invoice_id`.
2. Jika tidak ada `penagihan`, gunakan `biaya_tagihan.IDBIAYA` sebagai `old_cost_id`.
3. Jika hanya ada bukti transaksi, bentuk invoice historis sederhana dari:
   - `IDTRANSAKSI`
   - `IDPERMINTAAN`
   - `BULANTAGIHAN`
4. Invoice harus tetap terhubung ke:
   - customer
   - customer service jika ada
   - POP/cabang jika customer punya POP
5. Jika relasi tidak ditemukan, simpan ke import error/review.

Status invoice:

| Kondisi | Status Baru |
| --- | --- |
| total belum dibayar | `belum_dibayar` |
| pembayaran sebagian | `sebagian` |
| pembayaran penuh | `lunas` |
| data batal/tidak valid | `batal` atau import error |

Output:

- Tagihan lama bisa dilihat di detail pelanggan dan daftar invoice.

---

### Fase 8 - Mapping Payment Historis

Tujuan:

Pembayaran lama dapat masuk walaupun tidak selalu punya invoice ID yang eksplisit.

Sumber data:

- `apikeuangan_buktitransaksitagihan`
- `apikeuangan_buktitransaksilunas`
- `apikeuangan_buktitransaksipemasangan`

Aturan matching payment:

1. Cocokkan dengan invoice lewat `old_invoice_id` jika tersedia.
2. Jika tidak tersedia, cocokkan `old_transaction_id` ke `invoices.old_cost_id`.
3. Jika masih belum ketemu, cocokkan `old_request_id` dan `billing_period`.
4. Jika tetap tidak bisa dicocokkan, simpan sebagai import error/review.

Mapping utama:

| Sumber Lama | Target Baru |
| --- | --- |
| `IDUNIQ` | `payments.old_payment_id` |
| `IDTRANSAKSI` | `payments.old_transaction_id` |
| `IDPERMINTAAN` | `payments.old_request_id` |
| `TGLBAYAR` / `INSERTED_AT` | `payment_date` |
| `BULANTAGIHAN` | `billing_period` |
| `JENISPEMBAYARAN` | `payment_method` |
| `BAYAR` | `amount` |
| `IDPENERIMA` | `received_by_old` |
| `IDPENYETOR` | `deposited_by_old` |
| `KET` | `note` |

Aturan update invoice:

- Jika total pembayaran >= total invoice, invoice menjadi `lunas`.
- Jika total pembayaran > 0 tetapi kurang dari total invoice, invoice menjadi `sebagian`.
- Jika payment tidak valid/ditolak, invoice tidak berubah menjadi lunas.

Output:

- Histori pembayaran lama bisa dilihat dan status invoice terupdate.

---

### Fase 9 - Tampilkan Data Hasil Migrasi

Tujuan:

Data hasil migrasi mudah dicek oleh admin.

Tampilan minimal:

- Daftar pelanggan:
  - cari nama
  - cari ID lama
  - cari ID baru
  - cari nomor HP
  - filter status pelanggan
  - filter paket
  - filter POP/cabang
- Detail pelanggan:
  - identitas
  - alamat
  - layanan lama
  - tagihan
  - pembayaran
  - teknis lama
  - status kelengkapan data
- Tagihan:
  - daftar invoice
  - filter periode
  - filter status
  - filter pelanggan
- Pembayaran:
  - daftar pembayaran
  - filter tanggal
  - filter metode
  - filter pelanggan
- Import:
  - riwayat batch
  - detail error
  - jumlah valid/invalid/imported

Catatan:

- Workflow survey/pemasangan/perangkat tidak perlu ditonjolkan untuk tahap ini.
- Data teknis lama cukup tampil sebagai informasi.

---

### Fase 10 - Test dan Verifikasi

Tujuan:

Memastikan import legacy sesuai data lama dan tidak merusak flow billing manual.

Test wajib:

1. Import pelanggan dengan wilayah kosong tetap masuk sebagai `perlu_dilengkapi`.
2. Import pelanggan dengan `HP = null` string tidak gagal total.
3. Import status `ACTIVE` menjadi aktif.
4. Import status `PUTUS` menjadi berhenti.
5. Import status `GAGAL` menjadi gagal/nonaktif.
6. Import status `DISURVEI` menjadi survey.
7. Import status `PENGAJUAN` menjadi calon pelanggan.
8. Import paket lama tersimpan dengan `old_package_id`.
9. Import layanan lama terhubung ke customer dan paket.
10. Import detail teknis lama tersimpan di `customer_technical_details`.
11. Import invoice dari `old_cost_id` berhasil.
12. Import payment dengan `old_transaction_id` berhasil cocok ke invoice.
13. Payment historis mengubah status invoice menjadi `sebagian` atau `lunas`.
14. Data yang tidak bisa dicocokkan masuk ke `import_errors`.
15. Import ulang tidak membuat duplikasi berdasarkan key legacy.

Test regresi:

- `CustomerImportTest`
- `CustomerImportLoggingTest`
- `InvoiceModelTest`
- `InvoiceListTest`
- `PaymentModelTest`
- `PaymentInputTest`
- `PaymentListTest`
- `ReportImportTest`

Build:

- Jalankan `npm run build` setelah perubahan frontend.

---

## Acceptance Criteria

- Data pelanggan lama dari `pengguna` dapat masuk ke sistem baru.
- Pelanggan lama tidak lengkap tetap tersimpan dan ditandai `perlu_dilengkapi`.
- Paket lama dari `paket` tersimpan sebagai master paket.
- Layanan lama dari `prosedure_permintaan_wifi` terhubung ke pelanggan.
- Data teknis lama dari `laporan_pemasangan_wifi` tampil di detail pelanggan.
- Biaya/tagihan lama dapat tampil sebagai invoice.
- Pembayaran lama dapat terhubung ke invoice jika relasinya ditemukan.
- Data yang tidak bisa dicocokkan tidak hilang dan masuk import error/review.
- Billing manual tetap berjalan setelah data migrasi masuk.
- Tidak ada fitur post-MVP yang ditambahkan.

---

## Risiko dan Mitigasi

| Risiko | Dampak | Mitigasi |
| --- | --- | --- |
| Data lama tidak lengkap | Banyak baris gagal import | Longgarkan validasi dan gunakan status `perlu_dilengkapi` |
| Relasi invoice-payment tidak selalu jelas | Payment tidak terhubung | Matching bertahap via `old_invoice_id`, `old_transaction_id`, `old_request_id`, dan periode |
| Wilayah lama tidak cocok dengan master region | Pelanggan gagal masuk | Simpan teks wilayah apa adanya, mapping region dibuat opsional |
| Status lama tidak sesuai enum baru | Layanan gagal import | Buat mapping status legacy eksplisit |
| Import ulang membuat duplikasi | Data ganda | Gunakan key legacy unik |
| Fitur teknis melebar | Scope creep | Data teknis lama hanya sebagai informasi pelanggan |

---

## Urutan Task Yang Disarankan

1. Update `docs/TASKS.md` dengan task aktif khusus migrasi legacy.
2. Tambahkan field legacy yang masih kurang.
3. Perbaiki template import multi-sheet.
4. Refactor validasi import pelanggan legacy.
5. Tambahkan mapper status legacy.
6. Refactor import paket dan layanan legacy.
7. Refactor import invoice historis.
8. Refactor import payment historis.
9. Perbaiki tampilan detail pelanggan hasil migrasi.
10. Tambahkan test import legacy.

---

## Catatan Keputusan

- `docs/SCOPE_MIGRASI_PELANGGAN_BILLING.md` menjadi acuan utama tahap ini.
- `docs/Website_Billing_ISP_PRD.md` tetap menjadi roadmap besar.
- `docs/ANALISIS_SCOPE_MIGRASI_PELANGGAN_BILLING.md` menjadi dasar alasan teknis perubahan arah.
- `sand_db_sandya.sql` menjadi sumber referensi struktur data lama.
- Fitur post-MVP tetap ditunda sampai data pelanggan dan billing migrasi stabil.
