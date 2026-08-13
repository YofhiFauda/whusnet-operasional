# Checklist Pengujian Manual — Modul Kolektor

Dipakai untuk memverifikasi Fase 1–4 beserta perbaikan hasil review. Dirancang untuk dicentang
dan **dipakai ulang tiap rilis** yang menyentuh modul ini.

> Bagian [§6 Uji Temuan Review](#6-uji-temuan-review--prioritas-tertinggi) adalah yang **paling
> penting**. Isinya perilaku yang tak tercakup test otomatis — semua bug di sana pernah benar-benar
> terjadi. Kalau waktumu terbatas, kerjakan bagian itu dulu.

Acuan perilaku: [business-logic.md](business-logic.md) · [user-flow.md](user-flow.md) · [flowchart.md](flowchart.md)

---

## 0. Fakta lingkungan yang mengubah cara menguji

Periksa dulu, karena hasilnya menentukan apa yang wajar terjadi:

```bash
docker exec whusnet-app php artisan tinker --execute='
  echo "queue="   .config("queue.default")
     ." | disk="  .config("filesystems.default")
     ." | window=".config("billing.collector_due_window_days")
     ." | gemini=".(config("services.gemini.key") ? "aktif" : "kosong");
'
```

| Nilai | Artinya saat menguji |
|---|---|
| `queue=sync` | Pembacaan kwitansi jalan **seketika** saat upload — tak perlu menunggu worker |
| `queue=redis` | Butuh Horizon hidup (`docker ps` → `whusnet-horizon`), status kwitansi berubah beberapa detik setelah upload |
| `gemini=kosong` | **Benar & disengaja.** OCR mati; QR tetap jalan, yang gagal jatuh ke pencocokan manual |
| `window=7` | Tagihan muncul di Worklist bila `due_date ≤ hari ini + 7` |


---

## 1. Persiapan data & akun

- [x] **P1 — Kolektor A**: role `kolektor`, scope POP **X**
- [x] **P2 — Kolektor B**: role `kolektor`, scope POP **Y** (beda cabang)
- [x] **P3 — Admin X**: `pop_admin` scope POP X · **Admin X2**: `pop_admin` scope POP X (untuk uji berkas yatim) · **Admin Y**: scope POP Y
- [x] **P4 — Owner** (untuk hapus buku)
- [x] **P5** — 3–4 pelanggan di POP X, punya tagihan `belum_dibayar` dengan `due_date` **dalam 7 hari**
- [x] **P6** — 1 pelanggan di POP X dengan tagihan `due_date` **> 30 hari** (untuk uji jendela)
- [x] **P7** — 1 pelanggan yang punya **2 tagihan**: satu jatuh tempo, satu masih jauh

> Kolektor tanpa POP scope **tidak melihat apa pun** — itu deny-by-default, bukan bug. Pastikan
> scope-nya terisi lewat User Management.

Login sebagai kolektor otomatis diarahkan ke `/collector-worklist`.

---

## 2. Fase 1 — Pemisahan halaman & kolektor menagih

| ✔ | # | Langkah | Hasil yang diharapkan |
|---|---|---|---|
| [x] | 1.1 | Login **kolektor** → buka `/collector-worksheet` | **403** |
| [x] | 1.2 | Login **Admin X** → `/collector-worksheet` | 2 panel: daftar kolektor + pelanggan belum ber-kolektor |
| [x] | 1.3 | Centang 2 pelanggan, **tanpa** memilih kolektor → Assign | Ditolak: "Pilih kolektor tujuan dulu" |
| [x] | 1.4 | Ulangi, pilih **Kolektor A** | Dialog konfirmasi aplikasi (bukan `confirm()` browser) → setelah "Ya", pelanggan hilang dari panel kanan |
| [x] | 1.5 | Assign pelanggan POP X ke **Kolektor B** (POP Y) | **Seluruh batch** ditolak, pesan menyebut nama pelanggannya |
| [x] | 1.6 | Login **Kolektor A** → Worklist | Pelanggan P5 muncul; P6 (jauh dari jatuh tempo) **tidak** |
| [x] | 1.7 | Lihat pelanggan P7 | **Kedua** tagihannya tampil, termasuk yang belum jatuh tempo |
| [x] | 1.8 | **Bayar** satu baris, nominal penuh | Toast hijau, baris hilang, Saldo bertambah |
| [x] | 1.9 | Bayar **sebagian** (nominal < sisa) | Invoice `sebagian`, baris tetap ada dengan sisa baru, saldo bertambah sebesar yang dibayar |
| [x] | 1.10 | Centang 3 baris → **Bayar Massal** | Semua tercatat sekaligus |
| [x] | 1.11 | Nominal **melebihi** sisa → Bayar | Ditolak, alasan per baris, tak ada yang tersimpan |
| [x] | 1.12 | **Tgl Ditagih = besok** → Bayar | Ditolak |

---

## 3. Fase 2 — Saldo & Setoran

| ✔ | # | Langkah | Hasil yang diharapkan |
|---|---|---|---|
| [x] | 2.1 | Cek panel Saldo di Worklist | = jumlah pembayaran yang belum disetor |
| [x] | 2.2 | **Setor ke Admin** | Konfirmasi menyebut nominal & jumlah pembayaran → saldo **0**, status "Menunggu Verifikasi Admin" |
| [x] | 2.3 | Tagih lagi **sesudah** setor | Masuk saldo **baru**; nilai setoran yang menunggu tak berubah |
| [x] | 2.4 | Saldo 0 → Setor | Ditolak: "Saldo Anda sedang kosong" |
| [x] | 2.5 | Admin X → tab **Setoran** → Uang Fisik = angka sistem → Verifikasi | **Terverifikasi** |
| [x] | 2.6 | Setoran lain: Uang Fisik **kurang**, catatan **kosong** | Ditolak — catatan wajib |
| [x] | 2.7 | Ulangi dengan catatan | **Kurang Setor**; Saldo 0 tapi Kurang Setor > 0 (dua angka terpisah) |
| [x] | 2.8 | Cek Worklist kolektor | Badge merah "Kurang setor Rp…" |
| [x] | 2.9 | Hari berikutnya: tagih & setor. Admin isi Uang Fisik = total **+** pelunasan, pilih setoran yang dilunasi, isi nominal pelunasan | Setoran baru **Terverifikasi**, setoran lama **Selisih Lunas**, Kurang Setor → **Rp0** |
| [x] | 2.10 | Pelunasan **sebagian** | Setoran lama tetap **Kurang Setor** dengan sisa lebih kecil |
| [x] | 2.11 | Nominal pelunasan **melebihi** sisa kewajiban | Ditolak |
| [x] | 2.12 | Uang Fisik **lebih** dari angka sistem + catatan | **Lebih Setor (dikembalikan)** — badge biru, **bukan** "Kurang setor Rp0", dan tak muncul di daftar pending kolektor |
| [x] | 2.13 | Owner → hapus buku setoran **Lebih Setor** | Ditolak |
| [x] | 2.14 | **Admin** → hapus buku setoran Kurang Setor | **403** — hanya Owner |
| [x] | 2.15 | **Owner** → hapus buku + alasan | **Dihapus Buku**, Kurang Setor → 0 |
| [x] | 2.16 | `/payments/{id}` pembayaran di setoran **terverifikasi** → Tolak | Ditolak, pesan menyebut nomor setoran |
| [x] | 2.17 | Tolak pembayaran yang setorannya **masih menunggu verifikasi** | Berhasil; total setoran ikut turun |
| [x] | 2.18 | User Management → nonaktifkan kolektor yang masih pegang saldo | Ditolak, pesan menyebut nominal |
| [x] | 2.19 | Nonaktifkan kolektor yang punya **kurang setor** terbuka | Ditolak |

---

## 4. Fase 3 — Visit Log

| ✔ | # | Langkah | Hasil yang diharapkan |
|---|---|---|---|
| [x] | 3.1 | Panel **Catat Kunjungan** → **Tidak Ada Orang** | Tersimpan, muncul di "Kunjungan Hari Ini" |
| [x] | 3.2 | **Janji Bayar** tanpa tanggal | Ditolak |
| [x] | 3.3 | **Janji Bayar** + tanggal besok | Tersimpan dengan tanggal janji |
| [x] | 3.4 | **Menolak** + isi tanggal janji | Tersimpan, tanggal janji **diabaikan** |
| [x] | 3.5 | Cek isi dropdown hasil | Pilihan **"Bayar" tidak ada** |
| [x] | 3.6 | Tanggal Kunjungan = besok | Ditolak |
| [x] | 3.7 | Catat "Tidak Ada Orang" untuk Z → lalu **Bayar** Z hari itu juga | Kunjungan Z **berubah jadi Bayar**, tetap **satu** baris |
| [x] | 3.8 | Sesudah 3.7, catat "Tidak Ada Orang" lagi untuk Z hari ini | **Ditolak** — "sudah tercatat sebagai Bayar" |
| [x] | 3.9 | Bayar pelanggan dengan **3 tagihan** sekaligus | Riwayat kunjungan bertambah **1**, bukan 3 |
| [x] | 3.10 | Catat kunjungan untuk **tanggal kemarin** pada pelanggan yang hari ini sudah Bayar | Boleh — yang dikunci hanya hari yang berakhir dengan pembayaran |
| [x] | 3.11 | Admin → tab **Kunjungan** | Aging terurut dari kunjungan gagal terbanyak; baris ≥3 gagal disorot |

---

## 5. Fase 4 — Kwitansi (QR & OCR)

### 5a. Mendapatkan QR untuk diuji

**Lewat UI:**

```
/collector-worksheet → pilih kolektor → tab "Kwitansi"
   → panel kiri "Cetak Kwitansi" → centang pembayaran → "Buka Halaman Cetak"
```

URL langsung: `/collector-worksheet/{id_kolektor}/receipts/print?payment_ids[]=12&payment_ids[]=13`

Panel hanya menampilkan pembayaran yang **setorannya sudah diperiksa kantor** dan **belum punya
kwitansi**. Kalau kosong, urutan yang benar: kolektor menagih → **Setor ke Admin** → admin
**Verifikasi** di tab Setoran → baru pembayarannya muncul di kandidat cetak.

| Cara ambil gambar | Bisa diunggah? |
|---|---|
| **Screenshot area QR → PNG** | ✅ paling cepat untuk uji |
| Klik kanan QR → Save as (`.svg`) | ❌ SVG tidak diterima validasi |
| Ctrl+P → Save as PDF | ⚠️ diterima, tapi decoder hanya membaca **gambar** → berakhir "Gagal Dibaca" (berguna untuk menguji jalur manual) |

**Generate PNG langsung (tanpa UI):**

```bash
# ambil nomor pembayaran yang ada
docker exec whusnet-app php artisan tinker --execute='
  echo App\Models\Payment::latest()->take(5)->pluck("payment_number")->implode("\n");
'

# buat QR-nya
docker exec whusnet-app php artisan tinker --execute='
  $png = Endroid\QrCode\Builder\Builder::create()
      ->writer(new Endroid\QrCode\Writer\PngWriter)
      ->data("PAY-202608-0001")
      ->size(500)->margin(20)->build()->getString();
  file_put_contents("/tmp/qr.png", $png);
  echo "ok";
'
docker cp whusnet-app:/tmp/qr.png ./qr.png
```

### 5b. Skenario

| ✔ | # | Langkah | Hasil yang diharapkan |
|---|---|---|---|
| [x] | 4.0a | Bayar seorang pelanggan, **jangan** disetor → cek panel Cetak | Pembayaran itu **tidak muncul** — kwitansi dokumen kantor, uang masih di tas kolektor |
| [x] | 4.0b | Setor, tapi **jangan** diverifikasi → cek panel Cetak | Masih **tidak muncul** |
| [x] | 4.0c | Verifikasi setorannya → cek panel Cetak | Baru **muncul** sebagai kandidat |
| [x] | 4.0d | Setoran yang berakhir **Kurang Setor** | Kandidatnya **tetap muncul** — pelanggan tak boleh kehilangan bukti karena kolektor kurang setor |
| [x] | 4.0e | Tolak sebuah pembayaran → cek panel Cetak | **Tidak muncul**; paksa lewat URL `?payment_ids[]=` → **404** |
| [x] | 4.1 | Buka Halaman Cetak | Kwitansi 2 kolom; tiap kwitansi punya **QR** + nomor `PAY-…` sebagai **teks** |
| [x] | 4.2 | Scan QR pakai HP | Isinya **hanya** `PAY-YYYYMM-NNNN`, bukan URL |
| [x] | 4.3 | Upload PNG QR tersebut | **Cocok**, "via QR", nama pelanggan muncul |
| [x] | 4.4 | Upload **file identik** lagi | Tidak ada baris kedua |
| [x] | 4.5 | Upload PDF/gambar acak tanpa QR | **Gagal Dibaca**, berkas tetap ada, muncul dropdown pencocokan manual |
| [x] | 4.6 | Generate QR berisi `PAY-209912-9999` (tak ada di DB) → upload | **Nomor Tidak Dikenali** — bukan tercocokkan asal |
| [x] | 4.7 | Cocokkan manual ke sebuah pembayaran | **Cocok**, "via Manual" |
| [x] | 4.8 | **Lepas kaitan** | Kembali **Nomor Tidak Dikenali**, bisa dicocokkan ulang |
| [x] | 4.9 | **Unduh** kwitansi | Berkas terunduh |
| [x] | 4.10 | Salin URL unduh → buka sebagai **Admin Y** | **403** |
| [x] | 4.11 | Upload berkas gagal-baca sebagai **Admin X** → login **Admin X2** → coba unduh berkas yatim itu | **403** ("diunggah orang lain") |
| [x] | 4.12 | Login **kolektor** → coba upload kwitansi | **403** |
| [x] | 4.13 | Verifikasi sebuah setoran **tanpa** mengunggah kwitansi apa pun | Berhasil — dokumen tidak menyandera kas |
| [x] | 4.14 | Upload > 8 MB / > 100 berkas | Ditolak dengan pesan yang jelas |

---

## 6. Uji temuan review — PRIORITAS TERTINGGI

Semua di bawah ini **pernah benar-benar terjadi** dan tak tercakup test otomatis.

| ✔ | # | Langkah | Hasil yang diharapkan | Bug yang dicegah |
|---|---|---|---|---|
| [x] | R.1 | DevTools → Network → throttle **Slow 3G**. Di Worklist klik **Bayar baris A**, lalu **segera** klik **Bayar baris B** sebelum toast A muncul | **Dua-duanya tercatat.** `payments` bertambah 2, saldo naik A+B | Uang baris B **hilang** dengan gejala toast hijau *(HIGH)* |
| [x] | R.2 | Bayar invoice X sebesar 50rb → sukses → bayar invoice X sebesar 50rb lagi di hari yang sama | Keduanya tercatat | Cicilan kedua ditolak diam-diam sebagai "pengulangan" |
| [x] | R.3 | Kolektor buka Worklist. Admin **batalkan** invoice itu. Kolektor tekan Bayar | Ditolak: "sudah Batal (berubah sejak form dibuka)"; invoice **tidak** punya payment | Uang mendarat di tagihan mati |
| [x] | R.4 | **Admin Y** buka `/collector-worksheet/{Kolektor A}` | **403** | Saldo & riwayat setoran cabang lain bocor |
| [x] | R.5 | Kolektor yang jejak uangnya di **2 POP** dibuka admin yang cuma membawahi 1 POP | **403** | Angka total berbohong karena disaring diam-diam |
| [x] | R.6 | Buka **2 tab** Worklist → Setor di tab A → Setor di tab B | Tab B: **"saldo kosong"**, bukan "Setoran terkirim" | Sukses palsu |
| [x] | R.7 | Tab **Atur Pelanggan** → klik halaman 2 paginasi | Tetap di tab Atur Pelanggan | Terlempar ke tab lain |
| [x] | R.8 | Admin cabang lain coba mengisi **pelunasan** ke setoran kolektor cabang berbeda | Ditolak | Menulis ke catatan yang tak boleh dibaca |
| [x] | R.9 | Cek `audit_logs` module `kolektor` setelah setor / verifikasi / hapus buku / lepas kwitansi | Semua transisi tercatat lengkap dengan pelakunya | Jejak uang hilang |

---

## 7. Verifikasi angka lewat tinker

```bash
docker exec whusnet-app php artisan tinker
```

```php
$k = App\Models\User::find(<id_kolektor>);

// Dua angka uang — TIDAK boleh dijumlahkan
app(App\Services\CollectorBalanceService::class)->balance($k);              // saldo belum disetor
app(App\Services\CollectorBalanceService::class)->outstandingShortfall($k); // kurang setor

// Setoran terakhir
App\Models\CollectorDeposit::latest()->first()
    ->only(['deposit_number','status','declared_amount','difference','settlement_amount','settled_amount']);

// Kwitansi terakhir
App\Models\PaymentReceipt::latest()->take(5)
    ->get(['original_filename','status','match_method','detected_number','last_error']);

// Kunjungan hari ini
App\Models\CollectorVisit::whereDate('visited_at', today())
    ->get(['collector_id','customer_id','result','payment_id']);

// Jejak audit
App\Models\AuditLog::where('module','kolektor')->latest('created_at')->take(10)
    ->get(['action','auditable_type','auditable_id','new_values']);
```

---

## 8. Yang SUDAH BENAR walau terasa seperti error

Jangan dilaporkan sebagai bug:

| Gejala | Kenapa benar |
|---|---|
| Kolektor tanpa POP scope tak melihat apa pun | Deny-by-default — setup scope dulu |
| "Tidak ada pembayaran yang belum disetorkan" | Saldo memang kosong |
| "Anda tidak boleh memverifikasi setoran Anda sendiri" | Cross check butuh dua orang, berlaku juga untuk Owner |
| "Setoran ini memuat pembayaran di luar scope POP Anda" | Yang menutup harus membawahi seluruh POP-nya |
| "Kunjungan hari itu sudah tercatat sebagai Bayar" | Batalkan pembayarannya kalau memang keliru |
| Upload PDF berakhir "Gagal Dibaca" | Decoder QR hanya membaca gambar; lanjutkan dengan pencocokan manual |
| Kwitansi tidak sampai ke pelanggan saat penagihan | Disengaja — nomor baru ada setelah pembayaran tersimpan, jadi ini arsip internal ([business-logic §12](business-logic.md#12-kwitansi--sumbu-dokumen)) |
| OCR tidak pernah jalan | `GEMINI_API_KEY` kosong = mati by design |

---

## 9. Catatan hasil pengujian

| Tanggal | Penguji | Versi/commit | Temuan |
|---|---|---|---|
|  |  |  |  |
