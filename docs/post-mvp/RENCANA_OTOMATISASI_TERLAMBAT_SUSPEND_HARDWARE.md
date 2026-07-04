# Rencana Otomatisasi Tagihan Terlambat & Suspend Hardware

Status: **analisa, belum implementasi.** Dicatat sekarang karena baru relevan penuh setelah integrasi hardware (Mikrotik/RADIUS) ada. Ditulis detail supaya siap dieksekusi kapan saja tanpa riset ulang dari nol.

## 1. Kondisi Sekarang (Konfirmasi Gap)

Dicek langsung ke kode: **tidak ada otomatisasi apapun** untuk invoice yang lewat `due_date`.

- `invoice_status` cuma berubah lewat pembayaran manual (`PaymentController::store`/`bulkStore`) — nilainya `belum_dibayar` → `sebagian` → `lunas`. Tidak ada status "terlambat"/"overdue".
- Tidak ada scheduled command yang mengecek `due_date < now()`.
- Tidak ada denda/late fee di skema (`invoices` table tidak punya kolom untuk itu).
- Customer status (`app/Enums/WorkflowTransition.php`) sudah punya transisi `ACTIVE → SUSPENDED → ACTIVE/TERMINATED` yang valid secara state-machine, tapi tidak ada trigger otomatis yang memanggilnya berdasarkan tunggakan.
- Tidak ada integrasi hardware (Mikrotik/RADIUS) sama sekali di codebase — `grep` untuk "mikrotik", "radius", "pppoe", "isolir" tidak ketemu apa-apa.

Jadi ini benar-benar dibangun dari nol, bukan menyambungkan yang sudah setengah jadi.

## 2. Kenapa Nunggu Hardware Dulu

Auto-suspend tanpa hardware itu percuma — status `suspended` di database cuma label, koneksi internet pelanggan tetap nyala. Baru berguna kalau ada jalur teknis buat benar-benar memutus akses (PPPoE disable, RADIUS CoA disconnect, atau Mikrotik API `/ppp/secret disable`). Maka urutan yang benar: integrasi hardware dulu (command untuk enable/disable akses per pelanggan), baru automasi bisnis (kapan harus disable) dipasang di atasnya.

## 3. Rancangan Aturan Bisnis (untuk didiskusikan/dikonfirmasi nanti)

Ini draft aturan, bukan keputusan final — perlu dikonfirmasi ke bisnis sebelum dikode:

### 3.1 Definisi "Terlambat"
- Invoice BULANAN: terlambat kalau `now() > due_date` DAN `invoice_status` masih `belum_dibayar`/`sebagian`.
- Invoice AWAL/REAKTIVASI: perlu dipikir terpisah — biasanya PSB tidak seketat bulanan (bisa given lebih longgar karena belum tentu customer sudah aktif menikmati layanan penuh).
- **Grace period**: usul default 3-5 hari kerja setelah `due_date` sebelum status apapun berubah, biar tidak langsung menghukum keterlambatan setor kolektor/bank transfer yang butuh 1-2 hari settle.

### 3.2 Status Baru yang Dibutuhkan
Tambah field, JANGAN timpa `invoice_status` yang sudah ada (biar tidak merusak logic pembayaran yang sudah jalan):
- `invoices.is_overdue` (boolean, computed via scheduled job) — atau lebih baik: hitung on-the-fly di query (`due_date < now() AND invoice_status IN (belum_dibayar, sebagian)`) tanpa kolom tambahan, supaya tidak ada risiko data basi kalau job telat jalan. **Rekomendasi: computed, bukan kolom fisik.**
- `customer_services.suspended_at` (timestamp, nullable) — kapan pelanggan mulai kena suspend otomatis karena nunggak, beda dari suspend manual oleh admin (butuh kolom `suspension_reason`: `overdue` vs `manual` vs `maintenance`).

### 3.3 Alur Suspend Otomatis (Draft)
```
Hari 0  : due_date lewat, grace period mulai
Hari 3-5: kalau masih belum_dibayar/sebagian → kirim notifikasi (WA/SMS) "tagihan jatuh tempo"
Hari 7  : kalau masih nunggak → suspend otomatis (customer.status = suspended,
          suspension_reason = overdue) + panggil hardware disable
Setelah suspend: pelanggan bayar lunas → auto-reactivate (customer.status = active)
          + panggil hardware enable + (opsional) buat invoice_type REAKTIVASI
          kalau ada biaya buka isolir
```

Titik penting: `InvoiceType::REAKTIVASI` **sudah ada di enum** (`app/Enums/InvoiceType.php`) tapi belum ada consumer otomatis yang memakainya untuk kasus ini — saat ini cuma dipakai manual invoice. Auto-suspend/reaktivasi ini justru use-case aslinya.

### 3.4 Denda (Opsional, Perlu Keputusan Bisnis)
Kalau bisnis mau kenakan denda keterlambatan:
- Butuh kolom baru `invoices.late_fee` (decimal, nullable) — jangan campur ke `other_fee` (itu untuk biaya lain di luar standar, beda konteks).
- Formula: flat (misal Rp5.000/bulan telat) atau persentase (misal 2% dari `total_amount`) — perlu keputusan bisnis eksplisit, jangan ditebak.
- Kalau ada denda: `remaining_amount` bertambah, bukan `total_amount` diubah langsung (biar histori tagihan asli tidak berubah — tambah field `late_fee` terpisah, `remaining_amount = total_amount + late_fee - paid_amount`).

### 3.5 Interaksi dengan Fitur yang Sudah Dibangun
- **Modal Bayar Cepat & Bayar Massal** (sudah ada) — begitu pembayaran masuk dan invoice jadi `lunas`, harus trigger auto-reactivate kalau customer sedang `suspended` karena `overdue`. Ini butuh event/observer baru di `PaymentController` (atau `PaymentObserver` yang sudah dibuat sesi ini) yang cek status suspend & panggil hardware enable.
- **`GenerateMonthlyInvoicesCommand`** (sudah ada) — customer yang statusnya `suspended` karena nunggak TETAP harus dapat invoice bulan berikutnya (supaya tunggakan tidak hilang dari radar), tapi mungkin perlu flag terpisah "jangan auto-unsuspend cuma karena invoice baru dibuat" — auto-reactivate HARUS berbasis pelunasan, bukan berbasis invoice baru.

## 4. Integrasi Hardware — Yang Perlu Diriset Sebelum Kode

Belum ada info di codebase soal merek/jenis perangkat, jadi ini daftar pertanyaan yang HARUS dijawab dulu sebelum implementasi:

1. **Jenis akses**: PPPoE (via Mikrotik RouterOS) atau RADIUS (FreeRADIUS/RADIUS server terpisah) atau keduanya?
2. **API yang tersedia**: Mikrotik punya REST API (RouterOS v7+) atau harus lewat API lama (binary API port 8728)? Ada RouterOS versi berapa yang dipakai di lapangan?
3. **Topologi**: satu Mikrotik pusat, atau tiap POP (`pops` table sudah ada di sistem) punya router sendiri? Kalau per-POP, perlu `pops.mikrotik_host`/`mikrotik_api_credentials` (terenkripsi) sebagai kolom baru.
4. **Isolir vs disable total**: banyak ISP pakai "isolir" (redirect ke halaman "tagihan belum dibayar" dengan bandwidth kecil) bukan putus total — beda implementasi (ganti profile PPPoE ke profile "isolir" vs `disable=yes` di secret).
5. **Rate limit API**: Mikrotik API bisa nge-lag kalau dipanggil ratusan sekaligus (misal generate bulanan bikin 1459 invoice, kalau semua nunggak & disuspend bersamaan, itu 1459 API call sekaligus) — perlu queue/job batching, bukan panggil sinkron di request/command yang sama.

## 5. Urutan Implementasi yang Disarankan (Kalau Sudah Waktunya)

1. **Riset & pilih metode integrasi** (jawab pertanyaan §4) — ini bukan tugas coding, ini keputusan infrastruktur/vendor.
2. **Buat service layer abstrak** `NetworkAccessService` (interface) dengan 2 method: `disable(CustomerService $service)` dan `enable(CustomerService $service)` — implementasinya (Mikrotik/RADIUS) di-inject belakangan, supaya business logic (kapan suspend) tidak nempel ke detail vendor perangkat.
3. **Scheduled command** `billing:check-overdue-invoices` — jalan harian, cari invoice lewat due_date + grace period, kirim notifikasi H-sekian, lalu suspend yang sudah lewat ambang.
4. **Hook di `PaymentObserver`/`PaymentController`** — begitu invoice yang bikin customer suspended jadi lunas, auto panggil `NetworkAccessService::enable()`.
5. **UI**: badge "Terlambat" di invoice list (computed, bukan field DB — lihat §3.2), kolom `suspension_reason` di detail pelanggan biar admin beda mana yang manual vs otomatis.
6. **Audit log**: setiap auto-suspend/auto-reactivate WAJIB tercatat di `audit_logs` (pola `RecordsAuditLogs` sudah dipakai di model lain) — ini uang & akses pelanggan, harus bisa ditelusuri siapa/apa yang motong.

## 6. Yang TIDAK Direkomendasikan

- **Jangan** auto-suspend berdasarkan `invoice_status` doang tanpa grace period — bisa motong pelanggan yang baru telat sehari karena transfer bank belum settle.
- **Jangan** hardcode kredensial Mikrotik di `.env` polos — kalau nanti per-POP, taruh di DB terenkripsi (Laravel `encrypted` cast), bukan file config.
- **Jangan** panggil API hardware secara sinkron di dalam command `GenerateMonthlyInvoicesCommand` atau `PaymentController::store` — pisahkan ke queued job supaya kalau Mikrotik lambat/timeout, tidak ikut menggagalkan pembuatan invoice atau pencatatan pembayaran itu sendiri.

## 7. Ringkasan

Belum dikerjakan karena benar bergantung hardware. Begitu integrasi Mikrotik/RADIUS mulai digarap, urutannya: **riset akses hardware → service layer abstrak → command cek-nunggak → hook auto-reactivate → UI badge**. Aturan bisnis di §3 (grace period, denda) masih draft, wajib dikonfirmasi ke pemilik bisnis sebelum dikode — jangan tebak angka hari/persentase.
