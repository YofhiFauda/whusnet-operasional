# Flowchart — Modul Kolektor

## 1. Alur Besar — dari teras rumah ke buku kas

```
   PELANGGAN                KOLEKTOR                    ADMIN                  OWNER
       │                       │                          │                      │
       │  ←── didatangi ───────│                          │                      │
       │                       │                          │                      │
   ┌───┴────┐          ┌───────┴────────┐                 │                      │
   │ bayar? │          │                │                 │                      │
   └───┬────┘          ▼                ▼                 │                      │
       │        [catat bayar]    [catat kunjungan]        │                      │
       │         payment +        tidak_ada_orang /       │                      │
       │         visit(bayar)     menolak / janji         │                      │
       │              │                  │                │                      │
       │              ▼                  ▼                │                      │
       │        SALDO NAIK          aging naik            │                      │
       │              │                                   │                      │
       │              ▼                                   │                      │
       │        [Setor seluruh saldo] ───────────────────►│                      │
       │         saldo → 0                          menunggu_verifikasi          │
       │                                                  │                      │
       │                                          [hitung uang fisik]            │
       │                                                  ▼                      │
       │                                    ┌── difference = 0 ──► terverifikasi │
       │                                    ├── difference < 0 ──► selisih ──────┤
       │                                    └── difference > 0 ──► lebih_setor   │
       │                                                              (final)    │
       │                                            selisih │                    │
       │                          ┌─────────────────────────┴──────────┐         │
       │                          ▼                                    ▼         │
       │                 [pelunasan di setoran                  [hapus buku] ◄────┘
       │                  berikutnya]                            + alasan
       │                          │                                    │
       │                          ▼                                    ▼
       │                    selisih_lunas                        dihapus_buku
```

---

## 2. State Machine Setoran

```
                    POST /collector-worklist/deposit
                    (saldo > 0, seluruhnya)
                               │
                               ▼
                 ┌─────────────────────────────┐
                 │   MENUNGGU_VERIFIKASI       │ ← payment di dalamnya
                 │   declared = null           │   MASIH boleh di-reject
                 └─────────────┬───────────────┘
                               │ verify(declared, [settles, settlement], note)
                               │ guard: pending? verifier≠penyetor? lihat semua payment?
                               │        pelunasan valid (re-cek di bawah lock)?
                               ▼
              difference = declared − (Σ payment + settlement)
                               │
        ┌──────────────────────┼──────────────────────┐
        │ = 0                  │ < 0                  │ > 0
        ▼                      ▼                      ▼
┌───────────────┐   ┌──────────────────┐   ┌────────────────────┐
│ TERVERIFIKASI │   │     SELISIH      │   │    LEBIH_SETOR     │
│   (terminal)  │   │  (kurang setor)  │   │     (terminal)     │
└───────────────┘   │  note WAJIB      │   │  note WAJIB        │
                    │  BUKAN terminal  │   │  uang dikembalikan │
                    └────────┬─────────┘   └────────────────────┘
                             │
              ┌──────────────┴───────────────┐
              │ settlement dari setoran      │ writeOff(reason) — OWNER
              │ berikutnya                   │ guard: verifier≠penyetor,
              │ settled_amount += amount     │        lihat semua payment
              ▼                              ▼
     sisa habis? ── ya ──► SELISIH_LUNAS   DIHAPUS_BUKU
          │                 (terminal)       (terminal)
          └── tidak ──► tetap SELISIH
                        (sisa lebih kecil)

Sesudah MENUNGGU_VERIFIKASI: payment di dalamnya TIDAK BISA di-reject
(DepositStatus::isVerified()).
```

### Kabar keluar di tiap transisi (2026-08-11)

Setiap panah di atas memancarkan **dua kabar**, dikirim SESUDAH commit lewat
`safelyNotify()` — gagal mengabari tidak pernah membatalkan uang yang sudah
pindah tangan.

```
  transisi setoran
        │
        ├─► AppNotification (in-app, SINKRON — tak lewat queue)
        │     diajukan      → admin + pop_admin di POP setoran
        │     diverifikasi  → kolektor penyetor
        │     dihapus_buku  → kolektor penyetor
        │
        └─► CollectorDepositUpdated (broadcast, ShouldBroadcastNow)
              ├─ private-collector-activity.{popId}  → Worksheet Admin
              └─ private-App.Models.User.{kolektor}  → Worklist Kolektor

        payload: aksi, deposit_number, status, declared, recorded, collector
        payload TIDAK memuat saldo — saldo angka turunan, klien hitung ulang
        efek di layar (2026-08-21): toast + auto-tambal #live-content (fetch-
        ulang halaman + replaceWith, TANPA syarat skip-kalau-form-kebuka —
        sebelumnya cuma toast + bilah "Muat ulang" manual, dicabut atas
        permintaan eksplisit user, lihat business-logic.md §9)
```

Kenapa dua jalur, bukan satu: notifikasi in-app **bertahan** (masuk lonceng,
bisa dibaca besok), siaran realtime **sekejap** (cuma sampai ke layar yang
sedang terbuka). Menghapus salah satunya meninggalkan lubang — yang pertama
tanpa yang kedua berarti layar diam, yang kedua tanpa yang pertama berarti
kabar hilang begitu tab ditutup.

### Aktivitas kas DI LUAR siklus setoran

`CollectorActivityUpdated` — kanal & aturan main persis sama, cuma pemicunya
berbeda. Tiga kejadian yang sebelumnya mengubah angka orang lain tanpa suara:

```
  kolektor mencatat pembayaran (batch)
        │  saldo NAIK, tunggakan di Worksheet berkurang
        ├─► AppNotification → pop_admin di POP itu          (sudah ada)
        └─► CollectorActivityUpdated 'pembayaran_dicatat'   (BARU)

  admin menolak pembayaran ber-collected_by
        │  saldo TURUN
        ├─► AppNotification → pencatatnya                   (sudah ada)
        └─► CollectorActivityUpdated 'pembayaran_ditolak'   (BARU)
        └─ pembayaran TANPA collected_by → TIDAK menyiarkan apa pun
           (uang kantor, tak ada saldo kolektor yang bergerak)

  admin assign / lepas pelanggan
        │  rute kolektor berubah
        ├─► AppNotification → kolektor                      (BARU — dulu NOL)
        └─► CollectorActivityUpdated 'pelanggan_diassign'   (BARU)
                                     'pelanggan_dilepas'

        efek di layar (2026-08-21): toast + auto-tambal #live-content (sama
        pola di atas — lihat business-logic.md §9).
        'pelanggan_dilepas' & 'pembayaran_ditolak' bertoast WARNING, bukan
        hijau — keduanya berarti ada yang HILANG dari penerima kabar.
```

Yang paling berbahaya justru perubahan rute: pelanggan yang dilepas **setelah**
kolektor berangkat berarti dia menagih orang yang bukan lagi tanggungannya.

### Setoran Kas Admin → Owner/Bank (2026-08-21, baru — ADHOC-45)

Satu tingkat DI ATAS setoran kolektor. Sebelumnya TIDAK broadcast apa pun —
Setoran Kas (`cash-deposits/index.blade.php`) butuh reload manual buat lihat
setoran baru/hasil pemeriksaan.

```
CashDepositService::submit()/verify()/writeOff()
        │
        └─► CashDepositUpdated (broadcast, ShouldBroadcastNow)
              ├─ private-cash-deposits              → Setoran Kas (Owner/atasan)
              └─ private-App.Models.User.{adminId}  → Worksheet Admin (penyetor sendiri)

        aksi: diajukan | diverifikasi | ditutup_selisih
        payload: aksi, deposit_number, status, pop_id, depositor
        payload TIDAK memuat saldo — sama alasan CollectorDepositUpdated di atas
        efek di layar: toast + auto-tambal #live-content (sama pola,
        gak ada bilah "Muat ulang" — lihat business-logic.md §9)
```

Channel `cash-deposits` GLOBAL, bukan per-POP: pemeriksanya (`cash_deposit.view`)
selalu Owner/atasan, yang sudah bypass scope POP (CLAUDE.md § RBAC).

---

## 3. Alur Catat Pembayaran (kolektor & admin)

```
POST /collector-worklist/pay          POST /payment-batches/{collector}
  collector = auth()->user()            collector = route param
  gate: kolektor.pay                    gate: payments.create
        └───────────────┬───────────────┘
                        ▼
          RecordsCollectorBatch::recordBatch()
                        │
        ┌───────────────▼────────────────┐
        │ 1. findProcessedBatch(key)?    │── ada ──► 200 already_processed
        └───────────────┬────────────────┘           (idempotensi DULU,
                        │ tidak ada                   sebelum validasi)
                        ▼
        ┌────────────────────────────────┐
        │ 2. validateRows() — fase cepat │
        │    · invoice dalam POP scope?  │
        │    · customer.collector_id?    │
        │    · status lunas/batal?       │── gagal ──► 422 + alasan per baris
        │    · amount ≤ sisa & > 0?      │             (tak ada yang tersimpan)
        └───────────────┬────────────────┘
                        ▼
        ╔════════════ DB::transaction ════════════╗
        ║ buat PaymentBatch                       ║
        ║ per baris:                              ║
        ║   lockForUpdate(invoice)                ║
        ║   re-cek amount ≤ sisa  ── gagal ──► THROW ⇒ ROLLBACK SEMUA
        ║   Payment::create()                     ║
        ║   invoice->recalculateFromPayments()    ║
        ║   visits->recordPaid()  ← jejak kunjungan
        ╚═════════════════┬═══════════════════════╝
                          │ COMMIT
                          ▼
        notifyPopAdmins()  ← DI LUAR try; gagal ⇒ report(), TIDAK 422
                          ▼
                    200 + results[]
```

> **Kenapa notifikasi di luar `try`.** Batas transaksi dan batas penanganan error harus sejajar. Waktu masih di dalam, satu exception dispatch dijawab `422 "Batch ditolak"` padahal payment sudah tersimpan — kolektor menekan Bayar lagi dan pelanggan terkredit dua kali.

---

## 4. Idempotensi Klien

```
   submit(rows)
        │
        ▼
   signature = sort(rows.map(invoice:amount:method:date)).join('|')
        │
        ▼
   cbPendingKeys.has(signature)?
        │
   ┌────┴─────┐
   │ tidak    │ ya
   ▼          │
 mint key     │  ← pakai key yang sama (retry kiriman yang SAMA)
   └────┬─────┘
        ▼
   POST { idempotency_key, rows }
        │
   ┌────┴──────────────────────────┐
   │ sukses / already_processed    │ gagal
   ▼                               ▼
 cbPendingKeys.delete(signature)   key DIPERTAHANKAN untuk signature ini
 (kiriman identik berikutnya =     (baris lain tetap punya key sendiri)
  kiriman baru, mis. cicilan
  50rb kedua di hari sama)
```

**Kenapa per-tanda-tangan, bukan satu key global.** Dua bentuk sebelumnya masing-masing melahirkan bug dengan arah berlawanan:

| Bentuk | Akibat |
|---|---|
| key baru tiap panggilan | retry pasca-commit ⇒ pelanggan terkredit **dua kali** |
| satu key sampai sukses | bayar baris A lalu B sebelum A dijawab ⇒ B dijawab `already_processed` ⇒ uang baris B **hilang**, dengan toast hijau |

Bentuk sekarang menutup keduanya: identitas kiriman ada pada isinya, bukan pada tab yang mengirim.

---

## 5. Alur Catat Kunjungan

```
POST /collector-worklist/visits   (gate: kolektor.visit)
              │
              ▼
   result ∈ manualValues()? ── tidak ──► 422 "Bayar tidak bisa diinput manual"
              │ ya
              ▼
   customer.collector_id == auth? ── tidak ──► tolak
              │ ya
              ▼
   customer dalam POP scope auth? ── tidak ──► tolak
              │ ya
              ▼
   janji_bayar tanpa promised_date? ── ya ──► tolak
              │ tidak
              ▼
   visited_at > hari ini? ── ya ──► tolak (rencana, bukan laporan)
              │ tidak
              ▼
   baris (kolektor, pelanggan, tanggal) sudah ada?
              │
      ┌───────┴────────┐
      │ ada & = bayar  │──► TOLAK "sudah tercatat sebagai Bayar"
      │                │    (batalkan payment-nya kalau keliru)
      ├────────────────┤
      │ ada & ≠ bayar  │──► timpa; payment_id → null
      ├────────────────┤
      │ belum ada      │──► buat baru
      └────────────────┘

Jalur otomatis (dari pembayaran):
   recordPaid() ──► timpa apa pun hasil hari itu menjadi `bayar`
                    note manual → null, payment_id → payment terakhir
```

Pencarian baris memakai `whereDate()`, **bukan** `where('visited_at', …)` — kolomnya `DATE` tapi atributnya di-cast `date`, jadi perbandingan biasa jadi datetime penuh dan tak pernah ketemu (pernah merontokkan seluruh transaksi pembayaran, lihat database-schema.md).

---

## 6. Pohon Keputusan Guard POP

### Membuka halaman kas kolektor

```
GET /collector-worksheet/{collector}
              │
              ▼
   target ber-role kolektor? ── tidak ──► 404
              │ ya
              ▼
   viewer owner/atasan atau hasAllPopAccess? ── ya ──► IZINKAN
              │ tidak
              ▼
   popFootprint(collector)
     = POP pelanggan ∪ POP payment ∪ POP setoran
              │
      ┌───────┴────────┐
      │ kosong         │──► IZINKAN (kolektor baru, tak ada yang bocor)
      ├────────────────┤
      │ ⊆ allowedPops  │──► IZINKAN
      ├────────────────┤
      │ ada di luar    │──► 403 (all-or-nothing; total tak boleh disaring diam-diam)
      └────────────────┘
```

### Verifikasi & hapus buku setoran

```
verify() / writeOff()
     │
     ├─ status sesuai? (pending untuk verify, selisih untuk writeOff) ── tidak ──► tolak
     ├─ verifier ≠ penyetor? ─────────────────────────────────────────── tidak ──► tolak
     └─ jumlah payment terlihat == jumlah payment setoran? ───────────── tidak ──► tolak
              (applyUserScope(verifier) atas payments — BUKAN deposits.pop_id,
               karena setoran bisa lintas POP untuk kolektor pop_tree)
```

### Menagih / mencatat kunjungan

```
Dua lapis, dua-duanya wajib:
   1. customer.collector_id == kolektor        (pelanggan tanggung jawabnya)
   2. applyUserScope(kolektor) atas customer   (POP scope EFEKTIF sekarang)

Lapis 2 penting sendiri: assign lama tidak otomatis dibersihkan saat kolektor
dipindah cabang. Tanpa itu, pelanggan cabang lama tetap muncul dan bisa ditagih.
```

---

## 7. Resolusi Daftar Tagihan

```
                      ┌──────────────────────────────┐
                      │ siapa yang melihat?          │
                      └───────┬──────────────┬───────┘
                              │ kolektor     │ admin
                              ▼              ▼
                 dueInvoices()          outstandingInvoices()
                              │              │
   status ∈ {belum_dibayar, sebagian}  status ∈ {belum_dibayar, sebagian}
   customer.collector_id = X           customer.collector_id = X
   applyUserScope(kolektor)            applyUserScope(admin)
   DAN pelanggannya punya ≥1 invoice   (tanpa filter jatuh tempo —
   due_date ≤ hari ini + N              admin butuh gambaran penuh
                              │          untuk cross check)
                              ▼
        seluruh invoice tertunggak pelanggan itu ikut tampil,
        termasuk yang belum masuk jendela
```

---

## 8. Kwitansi — sumbu DOKUMEN (tak menyentuh sumbu kas)

```
ADMIN                                          SISTEM
  │
  │ centang pembayaran → Buka Halaman Cetak
  ▼
[halaman cetak]  QR(payment_number) + payment_number teks
  │
  │ print → serahkan/arsip → scan/foto
  ▼
[upload bulk] ────────────────────────────────► simpan disk `local` (privat)
                                                checksum sama? → pakai baris lama
                                                       │
                                                       ▼
                                              queue `kwitansi` (BUKAN `default`):
                                              MatchPaymentReceipt
                                              tries = MAX_ATTEMPTS, timeout 240s
                                                       │
                                    ┌──────────────────▼──────────────────┐
                                    │ 1. LAPISAN TEKS PDF (pdftotext)     │
                                    │    seluruh halaman sekaligus        │
                                    │    → bisa BANYAK nomor (lembar 8)   │
                                    └─────────┬───────────────┬───────────┘
                                     terbaca  │               │ kosong (foto/scan)
                                              │               ▼
                                              │      2. QR: raster per halaman
                                              │         (pdftoppm 200 DPI,
                                              │          eskalasi 400 DPI ≤3 hal)
                                              │       ┌──────┴───────┐
                                              │  terbaca            │ tidak
                                              │       │              ▼
                                              │       │      3. OCR Gemini tersedia?
                                              │       │         (GEMINI_API_KEY)
                                              │       │       ┌──────┴───────┐
                                              │       │    ya │              │ tidak
                                              │       │       ▼              │
                                              │       │  baca via model      │
                                              │       │   (temperature 0)    │
                                              └───────┴───────┴──────────────┘
                                                      │
                              ada kegagalan TEKNIS & jatah percobaan tersisa?
                                     ya → lempar ulang ⇒ queue MENGULANG
                                     tidak ↓
                                        ┌─────────────▼─────────────┐
                                        │ GERBANG 1: pola           │
                                        │ PAY-YYYYMM-NNNN?          │
                                        └──────┬─────────────┬──────┘
                                          lolos│             │tidak
                                               ▼             ▼
                                  ┌────────────────────┐  FAILED
                                  │ GERBANG 2: payment │  (menunggu manusia)
                                  │ dengan nomor itu   │
                                  │ ada di DB?         │
                                  └─────┬────────┬─────┘
                                     ya │        │ tidak
                                        ▼        ▼
                                    MATCHED   MISMATCH
                                  pop_id ←     (menunggu manusia)
                                  payment.pop_id
                                        │
        ADMIN ──── override manual ─────┴───► MATCHED (match_method = manual)
        ADMIN ──── lepas kaitan ────────────► MISMATCH (audit log)
                                              pop_id DIPERTAHANKAN —
                                              melepasnya melebarkan akses

Gerbang 1 & 2 dijalankan PER NOMOR. Satu lembar 8 kwitansi menghasilkan
8 baris payment_receipts; satu nomor MISMATCH tidak menggagalkan tujuh
lainnya. Seluruhnya dalam SATU transaksi (200 kwitansi: 16 dtk → 1,14 dtk).

LAYAR (2026-08-11): panel progres menanyai
GET /payment-receipts/progress/{collector} tiap 2 dtk selama masih ada
yang antre, lalu berhenti sendiri. Selesai → toast + segarkan sekali.

TIDAK ADA satu pun panah dari diagram ini menuju status setoran.
Setoran terverifikasi tanpa menunggu berkas apa pun (§13.2).
```

**Kenapa dua gerbang.** Gerbang pola menyaring teks liar; gerbang keberadaan payment menyaring nomor yang bentuknya benar tapi tak menunjuk apa pun. Tanpa gerbang kedua, QR salah cetak atau halusinasi OCR akan menempelkan kwitansi ke pembayaran yang salah — kesalahan yang baru ketahuan saat pelanggan protes.

**Siapa boleh membuka berkas:**

```
berkas punya pop_id?  ── ya ──► POP scope penuh
        │ tidak (belum pernah tercocokkan)
        ▼
  pengunggahnya sendiri, ATAU pemegang akses seluruh POP
  (bukan semua pemegang permission halaman — daftar berkas yatim
   akan bocor lintas cabang kalau begitu)
```

---

## 9. Efek Reject Payment terhadap Saldo

```
payment ditolak
      │
      ├─ collector_deposit_id NULL ─────────────► saldo turun sendiri
      │                                            (tak ada kompensasi manual)
      │
      ├─ setoran MENUNGGU_VERIFIKASI ───────────► total setoran ikut berubah
      │                                            (yang disimpan relasi, bukan angka)
      │
      └─ setoran sudah terverifikasi ───────────► DITOLAK
                                                   koreksi = payment pembalik,
                                                   setoran lama tak disentuh
```
