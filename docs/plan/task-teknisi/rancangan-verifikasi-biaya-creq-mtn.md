# Rancangan: Breakdown Per-Item Verifikasi Biaya + Buka Jalur Billing MTN

Status: **SELESAI dikerjakan — 2026-10-10.**
Terkait: `docs/plan/task-teknisi/analisa-gap-ownership-modem-dan-rincian-billing-creq.md` (Gap 2), `docs/plan/task-teknisi/rancangan-biaya-creq-verifikasi-cs.md`, `docs/plan/task-teknisi/rancangan-ownership-modem-sewa-beli.md` (Task 6 — breakdown Harga Modem+Jasa yang DIGANTI rancangan ini).

## 1. Latar Belakang

Dua gap yang disambung jadi satu pekerjaan, hasil diskusi 2026-10-10:

**Gap 2 (sisa, dari `analisa-gap-ownership-modem-dan-rincian-billing-creq.md`):** `TaskCreqBillingController::approve()` cuma punya 1 field `amount` polos buat C-REQ yang BUKAN jual modem (pasang kabel ekstra, ongkos jasa macem-macem, dll) — gak ada struktur "nama item + nominal" per baris. Plan `ownership-modem-sewa-beli` kemarin (Task 6) cuma nambah breakdown 2-field KHUSUS kasus jual modem (Harga Modem + Biaya Jasa), gak menjawab Gap 2 secara umum.

**Gap baru (ditemukan pas brainstorming sesi ini):** `TaskCreqDetail` (antrean `is_billable`/`verification_status`/dll) **cuma dibuat `if ($isCreq)`** — `TaskMaintenanceController::store()` baris komentar eksplisit: "cuma dibuat untuk task_type CREQ, gak nyentuh MTN/O-REQ/INFR REQ." Task MTN polos **TIDAK PUNYA jalur ke billing sama sekali**. User konfirmasi: MTN kadang (jarang) perlu jadi berbayar juga.

Keputusan yang disambung: breakdown per-item (jawaban Gap 2) dibikin GENERIK sejak awal, dipakai bareng buat C-REQ & MTN — sekalian nutup gap billing MTN, daripada bikin 2 mekanisme beda yang bakal divergen.

## 2. Keputusan Desain (hasil brainstorming, disetujui user)

1. **Breakdown per-item (bukan 1 field `amount`)** — CS isi N baris (nama+nominal) pas Verifikasi, bukan teknisi yang nentuin harga (precedent lama: harga/tarif resmi tetap hak CS, bukan "diambil dari catatan teknisi").
2. **Tersimpan terstruktur di DB** — tabel baru `task_billing_items`, bukan cuma di-compose jadi teks description doang. Bisa di-query/audit per baris.
3. **Unify, bukan 2 mekanisme beda** — breakdown ini GANTI 2-field Harga Modem+Jasa dari Task 6 (plan ownership-modem) DAN 1-field `amount`+`description` lama. Satu mekanisme buat semua task berbayar, C-REQ maupun MTN.
4. **Baris modem auto-seed** — kalau task punya `InventorySerialSale` (jual modem dari Gudang, lihat plan ownership-modem), 1 baris "Jual Modem" otomatis muncul di form (prefilled dari `InventorySerialSale::price`), CS tetap bisa koreksi nominalnya. Task yang gak ada modemnya ya gak ada baris ini.
5. **MTN dapat jalur billing baru** — tabel kecil terpisah `task_maintenance_billing_details` (mirror subset generik `task_creq_details`), checkbox "Task ini berbayar" muncul juga di laporan MTN polos (sebelumnya cuma muncul kalau C-REQ).
6. **Nama controller/route/permission TIDAK di-rename** — `creq_billing_verification`/`tasks.creq-billing.*` tetap, cuma query `index()` dilebarin baca 2 sumber (C-REQ + MTN). Alasan: hindari blast radius regresi ke RBAC + test yang udah mature, buat kebutuhan MTN yang user sendiri bilang "sangat jarang". Opsi lain yang dipertimbangkan & ditolak: (a) MTN rare-case tetap lewat Tagihan Manual biasa tanpa tautan task — ditolak, user mau tetap satu antrean/satu alur; (c) generalize total `task_creq_details` jadi tabel billing-queue lintas task_type — ditolak, migrasi kolom existing terlalu berisiko buat kebutuhan yang jarang.
7. **Kategori invoice MTN berbayar = `ManualInvoiceCategory::PERBAIKAN`** (hardcode, gak ada pilihan CS) — paling pas secara makna ("Tagihan Perbaikan"), gak perlu nilai enum baru. C-REQ tetap pakai `creqDetail->category->toManualInvoiceCategory()` seperti sekarang.
8. **Minimal 1 baris breakdown wajib** kalau task ditandai berbayar — gak ada submit kosong.

## 3. Skema Database

### 3.1 `task_maintenance_billing_details` (baru)

Mirror subset generik `task_creq_details` — TANPA field spesifik C-REQ (kategori, tikor, target POP).

```php
$table->id();
$table->foreignId('task_id')->unique()->constrained('tasks')->onDelete('cascade');
$table->boolean('is_billable')->default(false);
$table->text('billing_note')->nullable();
$table->string('verification_status')->default('pending'); // reuse CReqVerificationStatus
$table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
$table->timestamp('verified_at')->nullable();
$table->text('rejection_reason')->nullable();
$table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
$table->timestamps();
```

`verification_status` reuse cast `App\Enums\CReqVerificationStatus` — isinya cuma pending/verified/rejected, gak ada semantik C-REQ spesifik di value-nya, aman dipakai lintas task_type walau nama class-nya "CReq" (gak perlu rename/duplikasi enum).

### 3.2 `task_billing_items` (baru)

Baris breakdown — SATU-SATUNYA record rinci biaya (Invoice sendiri tetap flat `amount`+`description`, skema `Invoice` TIDAK diubah, konsisten Global Constraint plan ownership-modem).

```php
$table->id();
$table->foreignId('task_id')->constrained('tasks')->restrictOnDelete();
$table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
$table->string('name', 150);
$table->decimal('amount', 12, 2);
$table->foreignId('inventory_serial_sale_id')->nullable()->constrained('inventory_serial_sales')->restrictOnDelete();
$table->foreignId('created_by')->constrained('users')->restrictOnDelete();
$table->timestamps();
```

- `task_id`/`invoice_id`/`inventory_serial_sale_id` → `restrictOnDelete()` (bukan `cascade`/`nullOnDelete`) — ini tabel finansial, konsisten aturan proyek ("FK ke tabel keuangan/inventory baru pakai `restrictOnDelete()`").
- `invoice_id` NOT NULL — baris cuma ditulis DI DALAM transaksi yang sama persis setelah `Invoice` berhasil terbit (urutan: issue invoice → insert items → update detail jadi VERIFIED), jadi invoice selalu sudah ada duluan.
- `inventory_serial_sale_id` nullable — NULL buat baris biasa (jasa, kabel ekstra, dll), terisi CUMA buat baris "Jual Modem" yang auto-seed — dipakai buat tulis balik `InventorySerialSale::price` pas submit (perilaku sama Task 6, generalized).

## 4. Alur `TaskCreqBillingController::approve()` (generalized)

Ganti total logic branching `if ($sale) {...} else {...}` dari Task 6 jadi:

1. Resolve `$sale = InventorySerialSale` buat task ini (cara sama — `TaskWorkToolService::resolveTaskFor()` → `FopTask` → `InventorySerialSale.fop_task_id`), kalau ada → 1 baris default "Jual Modem" (nominal dari `$sale->price`).
2. Terima `items` array dari request: `items.*.name` (string, required, max 150), `items.*.amount` (numeric, min:0.01 — TIDAK ada baris Rp0, beda dari dulu yang modem_price saja min:0.01 tapi service_fee boleh 0 — di desain baru TIDAK ada baris "jasa gratis", kalau mau gratis ya gak usah ditambah baris itu). Minimal 1 baris (`items` wajib `array|min:1`).
3. Kalau `$sale` ada DAN baris pertama bukan hasil edit CS buat baris modemnya (lihat §5 soal UI auto-seed) — validasi tambahan: salah satu baris WAJIB punya `inventory_serial_sale_id` mengarah ke `$sale->id` dengan nominal > 0 (CS gak boleh menghapus baris modem seenaknya kalau modemnya beneran terjual — breakdown harus tetap merepresentasikan transaksi yang nyata).
4. `$amount = SUM(items.*.amount)`, `$description = implode(' + ', items.map(fn ($i) => "{$i->name}: Rp" . FormatHelper::rupiahInput($i->amount)))`.
5. `$manualCategory` — kalau `$task->task_type === TaskType::CREQ`: `$task->creqDetail->category->toManualInvoiceCategory()` (gak ubah). Kalau MTN: `ManualInvoiceCategory::PERBAIKAN` (hardcode).
6. `DB::transaction()`: lock detail row yang sesuai (`creqDetail` ATAU `maintenanceBillingDetail`, branch tipis by `task_type`), cek `verification_status===PENDING`, kalau `$sale` ada → `$sale->update(['price' => <nominal baris modem>])`, `ManualCategoryInvoiceService::issue(...)` (signature TIDAK berubah), insert tiap baris ke `task_billing_items` (`invoice_id` dari invoice yang baru terbit), update detail row jadi VERIFIED + `invoice_id`, `AuditLog`.

`reject()` — gak berubah secara struktur, cuma branch baca/tulis ke detail row yang sesuai (`creqDetail` vs `maintenanceBillingDetail`) sama kayak `approve()`.

## 5. UI

### 5.1 Laporan MTN (`maintenance-report.blade.php`)

Blok "Task Berbayar" (checkbox + catatan) yang sekarang ada DI DALAM `@if($isCreq)` — **dipindah/diduplikasi ke LUAR** blok itu, muncul juga buat task MTN polos. Field beda nama biar gak collision 1 form:
- C-REQ: `creq_is_billable` / `creq_billing_note` (TIDAK diubah).
- MTN: `mtn_is_billable` / `mtn_billing_note` (baru).

`TaskMaintenanceController::store()`: tambah blok `if (! $isCreq) { TaskMaintenanceBillingDetail::updateOrCreate(['task_id' => $task->id], [...]) }` paralel blok `if ($isCreq) {...}` yang sudah ada.

### 5.2 Verifikasi (`tasks/creq-billing/show.blade.php` + `approve()`)

Ganti section breakdown Task 6 (2 field fix) jadi tabel baris dinamis (Alpine, add/remove row):
- Kalau `$sale` ada: baris pertama ter-prefill "Jual Modem" + nominal dari `$sale->price`, field `name` readonly/locked (biar gak kehapus/ganti nama sembarangan — poin §4.3), field `amount` tetap editable.
- CS klik "+ Tambah Baris" buat nambah baris nama+nominal bebas.
- Total (SUM) ditampilkan live di bawah tabel sebagai preview, bukan input terpisah.
- Field `amount`/`description` tunggal yang lama **dihapus dari DOM** sepenuhnya (gak ada 2 sumber nilai) — sama prinsip kayak Task 6 kemarin pas hilangkan field `amount` buat kasus modem.

### 5.3 `index()` (antrean)

Query diOR, bukan cuma C-REQ:
```php
Task::applyUserScope()->where(function ($q) {
    $q->where(fn ($q) => $q->where('task_type', TaskType::CREQ->value)->whereHas('creqDetail', fn ($sq) => $sq->where('is_billable', true)))
      ->orWhere(fn ($q) => $q->whereHas('maintenanceBillingDetail', fn ($sq) => $sq->where('is_billable', true)));
});
```
Kolom status/filter summary ikut baca dari relasi yang sesuai per baris (branch tipis di view/accessor, bukan join ganda yang rumit — cek pola `statusCounts` existing dulu sebelum tulis ulang).

Label UI boleh digenerik-in ("Verifikasi Biaya Task" bukan "Verifikasi Biaya C-REQ") di halaman index/show — permission/route/controller code TETAP nama lama (lihat §2.6).

## 6. Dampak ke Task 6 (plan `ownership-modem-sewa-beli`, sudah di-merge)

Task 6 kemarin nambah field `modem_price`/`service_fee` di `approve()` + UI 2-field kondisional. Rancangan ini **menggantikan** mekanisme itu total (bukan nambah di sebelahnya — sudah dikonfirmasi user). Saat implementasi: hapus/ubah kode Task 6 yang relevan (bukan ditumpuk), test `TaskCreqBillingModemSaleBreakdownTest` perlu diperbarui/diganti test baru yang reflect struktur `items[]`.

## 7. Data Lama

- Task C-REQ yang udah `VERIFIED` sebelum perubahan ini — gak ada `task_billing_items` buat mereka (backfill TIDAK dilakukan, konsisten prinsip proyek "data lama dibiarkan"). Laporan/audit yang butuh breakdown cuma berlaku ke depan.
- `task_maintenance_billing_details` — tabel baru, kosong by default, gak ada data MTN lama yang perlu dipetakan (MTN emang belum pernah punya kapasitas ini).

## 8. Testing (rencana, detail test case disusun di plan eksekusi)

- Regresi: C-REQ yang gak ada modemnya tetap bisa breakdown N-baris manual (ganti test lama yang expect `amount` tunggal).
- Baris modem auto-seed + CS boleh koreksi nominal, tapi gak boleh hilangin baris itu kalau `$sale` ada.
- MTN: checkbox berbayar → masuk antrean, approve dengan breakdown N-baris → invoice `ManualInvoiceCategory::PERBAIKAN` terbit, `task_maintenance_billing_details` jadi VERIFIED.
- MTN: task biasa (gak ditandai berbayar) — gak masuk antrean sama sekali, perilaku existing gak keganggu.
- `index()` nampilin task dari KEDUA sumber (C-REQ + MTN) dalam 1 daftar, status filter jalan buat dua-duanya.
- POP scope tetap ketat di semua query baru (ikut `applyUserScope()` yang sudah ada di `index()`).

## 9. Pertanyaan Terbuka (kalau ada, isi sebelum eksekusi)

Tidak ada — semua poin desain sudah dikonfirmasi user pas brainstorming 2026-10-10 (§2 poin 1-8).
