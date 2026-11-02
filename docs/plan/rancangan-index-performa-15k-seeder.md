# Rancangan — Index Pendukung dari Temuan `analisa-performa-seeder-15k.md`

Status: **rancangan, belum diimplementasi.** Turunan dari kategori 3 ("butuh index biasa") di `analisa-performa-seeder-15k.md` §10. Lima titik ini dipisah ke dokumen sendiri karena solusinya murni tambah index — low-effort, low-risk, tapi tetap butuh migration asli + verifikasi EXPLAIN, bukan cuma dicatat sebagai temuan.

Index yang SUDAH ada di `invoices`/`payments` dicek langsung ke MySQL (`show index from ...`), bukan dari migration saja — supaya rancangan di bawah tidak mengusulkan index yang sudah ada dengan nama/kolom lain.

## 1. `workflow_transition_permissions.(from_status, to_status)`

**Bukti:** `/tasks/1` → EXPLAIN flag full scan (~12 baris). Tabel ini cuma punya `PRIMARY(id)` — tidak ada index lain sama sekali.

**Kenapa worth dibenahi walau tabelnya kecil:** query `where from_status = ? and to_status = ? limit 1` ini dipanggil di SETIAP transisi status task (dicek via `TaskPolicy`/`TaskService`, bukan cuma di halaman detail), jadi frekuensi panggilnya tinggi walau tabelnya kecil. Index murah, dampak kumulatif lebih besar dari ukuran tabelnya.

```php
Schema::table('workflow_transition_permissions', function (Blueprint $table) {
    $table->unique(['from_status', 'to_status'], 'wtp_from_to_unique');
});
```
Pakai `unique()` bukan `index()` biasa — satu pasangan `(from_status, to_status)` semestinya cuma punya satu baris aturan; kalau ternyata ada duplikat di data sekarang, migration akan gagal dan itu sendiri sinyal bug data yang harus dibersihkan dulu (jangan diam-diam diganti ke `index()` biasa untuk meloloskan migration).

## 2. `invoices` — ORDER BY `issue_date` tanpa WHERE yang cocok

**Bukti:** `/invoices`, `/reports/invoices` → full scan `invoices` (~111.438 baris) + filesort untuk `ORDER BY issue_date DESC, id DESC LIMIT 10/15` TANPA klausa WHERE.

**Index yang SUDAH ada (dicek langsung ke DB, bukan tebakan):** `invoices_pop_issue_idx (pop_id, issue_date)`, `invoices_customer_issue_date_index (customer_id, issue_date)`, `invoices_status_due_idx (invoice_status, due_date)`, `invoices_status_period_due_idx (invoice_status, billing_period, due_date)`. **Tidak ada satu pun yang punya `issue_date` sebagai kolom terdepan** — makanya sort tanpa filter `pop_id`/`customer_id` tidak bisa pakai index manapun di atas.

**Untuk `/invoices/belum-lunas`** (`where invoice_status in (...) order by issue_date`): index status yang ada (`invoices_status_due_idx`, `invoices_status_period_due_idx`) urutan kedua kolomnya `due_date`/`billing_period`, bukan `issue_date` — tetap filesort.

```php
Schema::table('invoices', function (Blueprint $table) {
    $table->index(['invoice_status', 'issue_date'], 'invoices_status_issue_idx');
});
```

Satu composite ini menolong KEDUA kasus: `/invoices` & `/reports/invoices` (tanpa filter — MySQL tetap bisa pakai index ini untuk ORDER BY walau tanpa WHERE, karena optimizer boleh pilih full index scan terurut alih-alih filesort) dan `/invoices/belum-lunas` (`WHERE invoice_status IN (...) ORDER BY issue_date`, prefix match langsung).

## 3. `payments` — ORDER BY `payment_date` tanpa WHERE yang cocok

**Bukti:** `/reports/payments` → full scan `payments` (~101.990 baris) + filesort, `ORDER BY payment_date DESC, id DESC LIMIT 15` tanpa WHERE.

**Index yang sudah ada:** `payments_pop_date_idx (pop_id, payment_date)`, `payments_status_date_idx (payment_status, payment_date)`, `payments_customer_date_idx (customer_id, payment_date)`, `payments_status_date_amount_idx (payment_status, payment_date, amount)` — semua butuh WHERE di kolom pertama buat kepakai; tidak ada yang menutupi sort tanpa filter.

`payments_status_date_idx` sebenarnya SUDAH cukup untuk kasus berfilter status (dipakai `/reports/collector-payments` dkk). Yang belum ketolong cuma listing tanpa filter apa pun. Daripada nambah index kelima yang isinya tumpang-tindih sama `payments_status_date_idx`, opsi yang lebih murah: ubah default `/reports/payments` supaya SELALU punya filter minimal (mis. default ke bulan berjalan) — itu keputusan produk, bukan keputusan index, jadi dicatat di sini sebagai alternatif, bukan dieksekusi sepihak.

Kalau listing tanpa filter tetap mau dipertahankan sebagai default:
```php
Schema::table('payments', function (Blueprint $table) {
    $table->index('payment_date', 'payments_payment_date_idx');
});
```

## 4. `reports/collector-monthly` — JOIN besar di `invoices`/`payments`

**Bukti:** full scan `invoices as i` (~111.438), full scan `payments as pay` (~101.990), temporary table — EXPLAIN dari `CollectorMonthlyReportController`.

Berbeda dari #2/#3: ini bukan sekadar ORDER BY, tapi JOIN + agregat per periode. Index #2 dan #3 di atas kemungkinan membantu sebagian (JOIN condition-nya perlu dicek ulang query aslinya — EXPLAIN di `analisa-performa-seeder-15k.md` belum menunjukkan kolom JOIN persis). **Butuh EXPLAIN ulang SETELAH #2 & #3 diterapkan** sebelum menyimpulkan index tambahan apa yang masih kurang di sini — jangan tembak index baru tanpa ukur ulang, berisiko nambah index yang ternyata tidak terpakai (kelas masalah E di `ANALISA_INDEX_DATABASE.md`: index redundan = write amplification tanpa manfaat baca).

## 5. `customers.data_completeness_status` (widget "Aktivitas Terbaru" di Dashboard `/`)

**Bukti:** `/` → full scan `customers` (~49.463 baris) + filesort untuk `WHERE data_completeness_status IN (...) ORDER BY updated_at DESC LIMIT 10`.

```php
Schema::table('customers', function (Blueprint $table) {
    $table->index(['data_completeness_status', 'updated_at'], 'customers_completeness_updated_idx');
});
```

Catatan: ini index TERPISAH dari masalah utama Dashboard (§5 di `analisa-performa-seeder-15k.md` — JOIN 5 tabel ber-`LOWER()` buat omset per segmen, lebih tepat dibenahi lewat cache, bukan index). Index ini cuma menyembuhkan satu dari beberapa query berat di halaman `/`, bukan solusi tunggal buat halaman itu.

## Urutan eksekusi yang disarankan

1. Migration gabungan untuk #1, #2, #3, #5 — empat-empatnya independen satu sama lain, aman dalam satu migration, tidak menyentuh kode PHP.
2. Jalankan ulang `php artisan perf:pages --only=invoices --only=payments --only=tasks` (atau satu-satu) buat konfirmasi EXPLAIN sudah hilang full scan/filesort-nya.
3. Baru investigasi #4 (`collector-monthly`) dengan EXPLAIN ulang, karena bentuk JOIN-nya belum cukup jelas dari data yang ada sekarang.

Belum ada migration yang dibuat — nunggu konfirmasi sebelum generate file migration asli (`php artisan make:migration`), sesuai CLAUDE.md §Cara Kerja Task.
