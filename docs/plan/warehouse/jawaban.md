1. Poin 1 — Penomoran TRF-/ISS- : Global

Keputusan: Global sequence, format TRF-2026-0001.
Alasan utama: Transfer itu transaksi antar dua cabang — Pusat kirim, Cabang terima. Kalau per-cabang, nomor yang sama muncul dua kali di dua sisi (satu dari perspektif pengirim, satu penerima), atau harus diputuskan "nomor milik siapa" — ini ambiguous dan rawan bug display.
ISS- dan REQ- juga global karena perlu dilacak lintas cabang oleh admin/owner tanpa harus tahu prefix cabangnya dulu.

SELECT FOR UPDATE cukup — tidak perlu Redis/queue. Throughput ISP lokal jauh di bawah ambang batas yang bikin ini jadi bottleneck. Daftarkan format ke docs/ID_NUMBERING_RULES.md:

TRF-{YYYY}-{0001}  Transfer gudang       Global sequence
ISS-{YYYY}-{0001}  Issue ke teknisi      Global sequence  
REQ-{YYYY}-{0001}  Stock request         Global sequence (fase 2)


2. Akses FOP di Layar Gudang (Buatkan RBAC agar bisa di modifikasi atau di edit)
Keputusan: FOP dapat akses READ-ONLY terbatas, bukan akses data gudang mentah penuh.

Alasan dari §3.4 yang sudah dikonfirmasi — FOP wajib bisa lihat gabungan Aktif + Pasif per fop_task_id untuk analisa. Tapi FOP tidak perlu lihat seluruh stok gudang, ledger transfer, atau custody teknisi lain di luar wilayahnya.

Matrix yang konkret:

Layar	FOP dapat akses?	Catatan
Dashboard stok (qty per item)	❌	Itu urusan admin gudang
Transfer buat/terima	❌	Bukan wewenang FOP
Issue ke teknisi	❌	Bukan wewenang FOP
Custody — lihat semua teknisi	✅ terbatas	Hanya teknisi dalam wilayah FOP-nya — sama scope FOP Task existing
Custody — lihat punya sendiri	✅	Kalau FOP juga pegang barang
Asset Traceability (cari SN)	✅	Untuk troubleshoot lapangan — ini yang dimaksud "opsional"
Ledger/riwayat transaksi	❌	Terlalu granular, bukan kebutuhan FOP
Analisa material per fop_task_id	✅	Ini yang wajib — extend verifikasi FOP existing (§3.4)


oin 3 — Alur Lot/Drum Kabel Fiber Step-by-Step

Ini yang paling perlu diurai. Ada 3 sub-pertanyaan, jawab satu per satu.

3a. ISSUE ke Teknisi — teknisi ambil dari 2 drum berbeda

Satu Issue bisa multi-lot, tiap lot jadi baris terpisah di inventory_transactions:

ISS-2026-0042
  ├── item: Kabel Dropcore G657A2
  │     lot_no: LOT-2026-001 (drum pertama)  qty: 80m
  │
  └── item: Kabel Dropcore G657A2
        lot_no: LOT-2026-002 (drum kedua)    qty: 50m

Di UI form Issue: setelah pilih item "Kabel Dropcore", muncul daftar drum yang tersedia di gudang cabang (lot_no + sisa meter). Admin gudang pilih drum mana yang diambil dan berapa meter dari tiap drum. Bukan teknisi yang milih — admin gudang yang tahu fisik drum mana yang sudah dibuka.

Di inventory_balances: satu baris per (warehouse_id, item_id, lot_no) — bukan satu baris per item saja.

warehouse_id  item_id  lot_no        qty_available
cabang-PON    kabel-1  LOT-2026-001  45m    ← sisa setelah diambil 80m dari 125m
cabang-PON    kabel-1  LOT-2026-002  170m   ← utuh minus 50m yang diambil
3b. Teknisi submit laporan — lot_no mana yang tercatat

Tidak manual pilih — otomatis FIFO dari drum yang di custody teknisi.

Alasan: teknisi di lapangan tidak peduli dan tidak harus tahu lot nomor drum. Yang dia tahu: "saya pakai 30 meter kabel."

Logikanya di service layer:

php
// InventoryService::consumeFromCustody()
// Dipanggil saat submit laporan (kind=terpakai)

public function consumeFromCustody(int $technicianId, int $itemId, float $qtyUsed): array
{
    // Ambil semua lot yang di custody teknisi ini, urut lot_no ASC (FIFO)
    $lots = TechnicianCustody::where('technician_id', $technicianId)
        ->where('item_id', $itemId)
        ->where('qty_remaining', '>', 0)
        ->orderBy('lot_no', 'asc')  // FIFO: drum lama habis dulu
        ->lockForUpdate()
        ->get();

    $remaining = $qtyUsed;
    $consumed  = [];

    foreach ($lots as $lot) {
        if ($remaining <= 0) break;

        $take = min($lot->qty_remaining, $remaining);
        $lot->decrement('qty_remaining', $take);

        $consumed[] = [
            'lot_no'  => $lot->lot_no,
            'qty'     => $take,
        ];

        $remaining -= $take;
    }

    if ($remaining > 0) {
        // Quantity yang diklaim lebih dari yang dibawa — validasi gagal
        throw new InsufficientCustodyException("Sisa custody tidak cukup: kurang {$remaining}m");
    }

    return $consumed; // [{lot_no: LOT-001, qty: 20}, {lot_no: LOT-002, qty: 10}]
}

Di task_materials, kalau konsumsi memotong 2 drum → 2 baris:

fop_task_id  item_id   lot_no        qty   kind      unit_price_snapshot
TASK-123     kabel-1   LOT-2026-001  20m   terpakai  850
TASK-123     kabel-1   LOT-2026-002  10m   terpakai  850

Teknisi lihat di form: field "Jumlah dipakai" — input angka saja. Lot-nya otomatis resolved di backend, tidak ditampilkan ke teknisi. Kalau mau ditampilkan di summary laporan setelah submit, boleh — tapi bukan input yang harus diisi teknisi.

3c. Urutan pemakaian — FIFO drum lama dulu

Sudah dijawab di 3b: FIFO berdasarkan lot_no ascending.

Alasan praktis:

Drum lama yang sudah terbuka perlu dihabiskan dulu sebelum buka drum baru — ini praktik fisik yang wajar.
Lot number yang berurutan mencerminkan urutan masuk (LOT-2026-001 lebih lama dari LOT-2026-002).
Tidak perlu UI khusus untuk pilih urutan — FIFO otomatis sudah cukup dan tidak membebani teknisi.

Satu-satunya kasus edge yang perlu dicatat: kalau teknisi secara fisik sudah menghabiskan drum LOT-001 tapi sistem masih mencatat ada sisa (karena belum ada laporan submit sebelumnya) — ini diselesaikan lewat stock opname custody (bukan fitur Fase 1, tapi perlu dicatat di backlog).


4. Verifikasi Material Non-Serial
Masalah intinya

Untuk serialized item, verifikasi sudah built-in: SN ada atau tidak ada, terpasang atau belum, custody cocok atau tidak — sistem bisa jawab deterministik.

Untuk non-serial (kabel, RJ45, splice protector, dll), tidak ada "bukti fisik digital" yang bisa dicek sistem. Yang ada hanya angka yang diketik teknisi.

Anomaly detection yang kamu sebut itu valid tapi reactive — baru ketahuan setelah fraud/kesalahan terjadi, dan threshold-nya subjektif (rata-rata dari mana? instalasi rumah vs gedung beda jauh).

Pendekatan saya: Structural Constraint, bukan Detective Work

Alih-alih mendeteksi apakah laporan itu jujur, batasi struktural berapa yang bisa diklaim — sehingga overclaim tidak mungkin secara sistem, bukan sekadar dicurigai setelah kejadian.

Kuncinya ada di satu aturan:

Teknisi hanya bisa klaim sejumlah yang ada di custody-nya. Tidak lebih.

Ini sudah ada fondasinya dari consumeFromCustody() di poin 3 tadi — service itu throw InsufficientCustodyException kalau qty claimed > qty custody. Itu bukan sekadar validasi input, itu ceiling yang tidak bisa ditembus dari sisi sistem.

Konsekuensinya: kalau teknisi mau overclaim 30m padahal dibawa cuma 20m, sistem tolak di submit. Titik.

Tapi ini geser masalahnya, bukan selesaikan

Sekarang pertanyaannya bukan "apakah laporan jujur" tapi "apakah custody-nya jujur" — yaitu: apakah teknisi benar-benar membawa 20m ke lapangan, atau dia minta 50m ke gudang tapi pakai 20m dan sisanya entah ke mana?

Di sinilah return custody jadi mekanisme verifikasi utama:

Teknisi ambil dari gudang: 50m kabel
        ↓
Lapangan: pakai 30m
        ↓
Sisa custody: 20m
        ↓
Wajib dikembalikan ke gudang — RETURN transaction
        ↓
Gudang konfirmasi fisik: terima 20m ✅ atau terima 15m ⚠️

Kalau gudang terima 15m tapi sistem expect 20m → ada selisih 5m yang perlu dijelaskan. Ini bukan anomaly detection — ini rekonsiliasi eksplisit dengan konfirmasi fisik dari pihak kedua (admin gudang), bukan hanya klaim teknisi.

Implementasinya di sistem

Tambah status di custody teknisi:

ISSUED        → di tangan teknisi, belum dipakai/dikembalikan
PARTIALLY_USED → sebagian diklaim di laporan, sisa masih di custody
RETURNED      → fisik sudah balik ke gudang, dikonfirmasi admin
CONSUMED      → habis dipakai (qty_remaining = 0 setelah laporan)

Setelah submit laporan:

custody 50m kabel
laporan klaim 30m
        ↓
sistem otomatis:
  task_materials: 30m (terpakai)
  sisa custody:   20m → status PARTIALLY_USED

Sisa custody PARTIALLY_USED ini "menggantung" — visible di dashboard admin gudang sebagai item yang pending return. Admin gudang tahu teknisi X masih pegang 20m kabel yang belum dikembalikan.

Saat teknisi return fisik:

php
// Admin gudang input: teknisi X kembalikan kabel lot LOT-001, qty: 15m
// (bukan 20m yang expected)

WarehouseReturnService::receive([
    'technician_id' => $technicianId,
    'item_id'       => $itemId,
    'lot_no'        => 'LOT-2026-001',
    'qty_actual'    => 15,  // fisik yang diterima
    'qty_expected'  => 20,  // dari sisa custody sistem
]);

// Kalau qty_actual != qty_expected:
// → selisih 5m dicatat sebagai SHRINKAGE
// → butuh catatan alasan (hilang, rusak, salah hitung)
// → flagged untuk review atasan
Kenapa ini lebih kuat dari anomaly detection
	Anomaly Detection	Structural Constraint + Return
Kapan ketahuan	Setelah banyak data terkumpul	Saat return — per transaksi
Siapa yang verifikasi	Algoritma (bisa salah threshold)	Admin gudang secara fisik
Bisa di-game?	Ya — pakai angka yang "normal"	Harder — harus cocok dengan fisik yang dikembalikan
False positive	Tinggi (instalasi beda-beda)	Tidak ada — selisih adalah selisih
Butuh historical data	Ya	Tidak

Anomaly detection tetap berguna tapi posisinya bergeser: bukan alat verifikasi utama, tapi alat audit periodik — misalnya flagging teknisi yang konsisten punya shrinkage tinggi setiap return, atau kategori item yang shrinkage-nya di atas threshold tertentu secara historis.

Satu celah yang perlu diakui

Kalau teknisi dan admin gudang berkolusi — admin gudang konfirmasi return 20m padahal fisiknya 15m — sistem tidak bisa deteksi ini.

Mitigasi: stock opname periodik. Stok fisik gudang dihitung, dibandingkan dengan inventory_balances. Kalau admin gudang rutin "menerima" lebih dari yang ada, stok gudang akan over di sistem tapi under di fisik — ketahuan saat opname.

Ini sudah di luar scope Fase 1, tapi fondasinya (ledger yang akurat) sudah dibangun dari awal.

Ringkasan mekanismenya
ISSUE (gudang → teknisi)
  └── custody terbentuk, qty terkunci

LAPORAN SUBMIT
  └── qty claimed ≤ qty custody → sistem enforce
  └── sisa → status PARTIALLY_USED, visible ke admin gudang

RETURN (teknisi → gudang)
  └── admin gudang input qty fisik yang diterima
  └── sistem compare: expected vs actual
  └── selisih → SHRINKAGE, wajib ada catatan alasan

STOCK OPNAME (periodik, fase 2)
  └── verifikasi bahwa return yang dikonfirmasi admin benar-benar masuk fisik gudang

Verifikasi material non-serial bukan soal mendeteksi kebohongan — tapi soal membangun loop yang tidak bisa selesai tanpa konfirmasi pihak kedua. Teknisi tidak bisa tutup loop sendiri.