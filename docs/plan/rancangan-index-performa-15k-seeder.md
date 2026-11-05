# Rancangan dan verifikasi indeks — temuan seeder 15k

Status per 2026-11-05: **diimplementasikan pada kode dan database development lokal** melalui `2026_11_05_113900_add_listing_order_and_transition_indexes.php`. Migration belum diterapkan ke database production. Dokumen ini mencatat keputusan setelah memeriksa query aplikasi, skema MySQL 8.0.46, serta EXPLAIN sebelum dan sesudah migration. Angka `rows` di bawah adalah **estimasi optimizer**, bukan waktu respons atau bukti skor Lighthouse.

## Scope dan keputusan

Perubahan hanya menambah tiga indeks urutan dan satu constraint unik. Tidak ada filter, urutan, aturan billing, POP scope, atau kode controller yang diubah. Indeks lama untuk filter POP tetap dipakai pada query yang memiliki `pop_id`; pengujian di bawah memakai daftar global tanpa filter agar masalah yang ditemukan analisis awal dapat dibandingkan langsung.

| Tabel | Indeks baru | Query sasaran | Alasan |
| --- | --- | --- | --- |
| `invoices` | `invoices_issue_date_idx (issue_date)` | `ORDER BY issue_date DESC, id DESC LIMIT 10/15` tanpa filter status/POP | Indeks lama diawali `pop_id`, `customer_id`, atau `invoice_status`, sehingga tidak memberi urutan tanggal global. InnoDB menyimpan primary key `id` pada secondary index. |
| `payments` | `payments_payment_date_idx (payment_date)` | `ORDER BY payment_date DESC, id DESC LIMIT 15` tanpa filter | Indeks lama untuk tanggal pembayaran diawali `pop_id`, `customer_id`, atau `payment_status`. |
| `customers` | `customers_updated_at_idx (updated_at)` | Widget 10 pelanggan belum lengkap terbaru | `WHERE data_completeness_status IN (...)` perlu urutan `updated_at` global. Pemindaian dari baris terbaru dapat berhenti setelah menemukan 10 baris yang cocok. |
| `workflow_transition_permissions` | `wtp_from_to_unique (from_status, to_status)` | Lookup satu aturan transisi | Tujuan utama integritas data. Tabel berisi 12 pasangan berbeda; manfaat performa diperkirakan kecil. |

**Koreksi rancangan lama:** `(invoice_status, issue_date)` tidak menyelesaikan `ORDER BY issue_date` global tanpa filter `invoice_status`, karena `invoice_status` adalah kolom pertama. Filter `IN` dengan beberapa status juga tidak otomatis menghasilkan urutan tanggal global dari indeks itu. Karena itu indeks tersebut **tidak dibuat**. Untuk widget pelanggan, kandidat lama `(data_completeness_status, updated_at)` juga tidak dipilih: query memakai beberapa status dan meminta urutan terbaru lintas status. Indeks pengganti yang dipilih telah diuji dengan EXPLAIN setelah migration; kandidat yang tidak dibuat belum dibandingkan sebagai indeks terpasang.

## Bukti EXPLAIN pada database development

Query yang diuji memilih `id` dan urutan yang sama dengan daftar. Tabel aplikasi juga memuat relasi serta filter lain; hasil di bawah hanya membuktikan perbaikan jalur pengurutan untuk varian ini. Nilai `rows` adalah estimasi, bukan jumlah baris aktual yang pasti dibaca.

| Query | Sebelum | Sesudah |
| --- | --- | --- |
| Invoice global terbaru, LIMIT 15 | `invoices_pop_issue_idx`, rows 123841, `Using filesort` | `invoices_issue_date_idx`, rows 15, `Backward index scan; Using index` |
| Invoice lunas terbaru, LIMIT 10 | `invoices_status_due_idx`, rows 61920, `Using filesort` | `invoices_issue_date_idx`, rows 20, `Using where; Backward index scan` |
| Payment global terbaru, LIMIT 15 | `payments_pop_date_idx`, rows 103581, `Using filesort` | `payments_payment_date_idx`, rows 15, `Backward index scan; Using index` |
| Pelanggan belum lengkap terbaru, LIMIT 10 | full scan, rows 48495, `Using where; Using filesort` | `customers_updated_at_idx`, rows 10, `Using where; Backward index scan` |

Cek tambahan pada POP 2 (sekitar 125.056 invoice dan 50.107 pelanggan) menunjukkan daftar invoice tetap memilih `invoices_pop_issue_idx`, daftar payment memilih `payments_pop_date_idx`, dan widget pelanggan memilih `customers_updated_at_idx` tanpa filesort. Ini memeriksa rencana query untuk satu POP besar; uji akses POP lintas role tetap menjadi tanggung jawab test RBAC yang sudah ada.

Constraint unik berhasil dibuat pada data development; 12 pasangan status yang ada tidak duplikat. Migration `down()` diuji pada database development: indeks dan constraint baru hilang, lalu `up()` dijalankan kembali. Tidak ada baris data yang diubah oleh migration.

## Laporan kolektor bulanan

`CollectorMonthlyReportService` memakai `billing_period`, `pop_id`, `invoice_id`, `payment_date`, beberapa subquery agregat, dan kondisi status. Pengukuran `perf:pages` setelah migration pada periode default memberi **6.451 ms, 24 query, 92,5 MB** untuk `/reports/collector-monthly`; dua query terberat masing-masing 3.615,2 ms dan 1.421,8 ms. EXPLAIN masih menandai full scan `invoices` sekitar 123.841 baris pada query piutang lama. Query ini meminta seluruh invoice sebelum periode berjalan dan menggabungkan agregat payment; indeks tanggal untuk daftar global memang bukan solusi langsung. Indeks yang sudah ada mencakup `invoices_pop_period_idx`, `payments_invoice_id_idx`, dan `payments_status_date_idx`. Tidak ada indeks tambahan khusus laporan bulanan dalam migration ini. Optimasi laporan memerlukan analisis SQL per blok dengan periode dan POP representatif, lalu pengujian bentuk query serta indeks JOIN/agregat secara terpisah agar angka keuangan dan scope POP tetap sama.

## Kaitan dengan optimasi query lain

ADHOC-268 mengganti tiga pengambilan histori eskalasi/pengembalian NOC dengan satu agregasi `GROUP BY actor_id, action, to_status`. Tes regresi membuktikan histori non-selesai tidak lagi dihidrasi; 17 tes NOC lulus. Setelah ADHOC-270 memadatkan tren 12 bulan dan pelanggan kronis, halaman NOC terukur 6.827 ms/12.512 model pada satu run development; bottleneck halaman penuh masih terbuka.

Setelah ADHOC-269 pada database development, `/warehouse/reports` turun dari 221 menjadi 47 query lewat batch saldo, dan `/collector-worksheet` turun dari 50.108 menjadi 74 model lewat agregasi kas. Keduanya merupakan perubahan query, bukan dampak migration indeks ini. `/fop-tasks` masih 21.782 model; refresh halaman penuh serta laporan kolektor bulanan tetap terbuka. Status dan urutan kerja ada di `rancangan-optimasi-performa-skala-15k.md`.

## Verifikasi dan batasan

- PHPUnit `tests/Feature/PerformanceIndexMigrationTest.php` menguji bentuk tiga indeks urutan dan penolakan pasangan transisi duplikat.
- EXPLAIN sebelum/sesudah di atas menunjukkan perubahan rencana eksekusi untuk varian query yang diuji. Waktu respons halaman, p95, beban tulis, dan Lighthouse **belum diukur ulang**; jangan menyimpulkan persentase percepatan dari estimasi EXPLAIN saja.
- Di production, periksa pasangan transisi duplikat dan indeks yang sudah ada sebelum migration. Jalankan pada jendela operasional yang sesuai, lalu verifikasi EXPLAIN dan waktu query pada data serta POP scope production.
- Indeks menambah biaya INSERT/UPDATE dan ruang penyimpanan. Masalah N+1, pemuatan seluruh model, dan refresh halaman penuh di `analisa-performa-seeder-15k.md` tetap pekerjaan terpisah.
