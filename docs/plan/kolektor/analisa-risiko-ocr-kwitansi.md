# BELUM DIKERJAKAN (PENDING) 
## Analisa Risiko OCR Kwitansi & Rancangan Antisipasi

**Tanggal:** 2026-08-08
**Status:** dicatat atas permintaan user. **Belum ada kode yang diubah.**
**Pemicu:** pertanyaan user — *"jika QR/OCR error tanpa sebab atau gagal mendeteksi, bukankah itu justru jadi celah baru?"*

> ## ⚠️ MEMBLOKIR PENGISIAN `GEMINI_API_KEY`
>
> Lubang di §3 **dorman** selama OCR mati. Ia hidup **tepat pada hari API key diisi**.
> Kerjakan §5 dulu, baru aktifkan OCR — sesudahnya, tiap kwitansi yang salah tempel harus
> ditelusuri satu per satu.

Konteks modul: [`docs/kolektor/business-logic.md` §12](../../kolektor/business-logic.md#12-kwitansi--sumbu-dokumen)

> **Dokumen ini soal KEBENARAN** (hasil tebakan tak boleh jadi keputusan). Sisi **OPERASIONAL** —
> rate limit, cooldown, klasifikasi galat 429/503/401, pagu harian — dipisah ke
> [`analisa-operasional-ocr-gemini.md`](analisa-operasional-ocr-gemini.md) (ADHOC-31).
> Keduanya wajib selesai sebelum `GEMINI_API_KEY` diisi; yang satu tidak menggantikan yang lain.

---

## 1. Pertanyaan yang diajukan

Apakah ketergantungan pada QR/OCR menciptakan celah baru, karena kegagalan pembacaan bisa dipakai
sebagai alasan ("sistemnya error") atau membuka jalan penyalahgunaan?

**Jawaban singkat: instingnya benar, tapi bahayanya di tempat yang berlawanan.**

---

## 2. Yang BUKAN celah — kegagalan baca

Kegagalan pembacaan di desain sekarang **fail-closed**:

```
QR tak terbaca → status FAILED → berkas terpampang menunggu manusia
                                  tak ada yang otomatis terjadi
```

Sifatnya **berisik**: berkas nongkrong di daftar dengan badge merah, `last_error` tercatat, dan tak
ada pembayaran yang berubah status. Tak ada yang bisa disembunyikan lewat kegagalan.

Ditambah: kwitansi **bukan kontrol**, ia arsip yang dicetak kantor **setelah** setoran diperiksa
(keputusan user 2026-08-08). Kolektor tak pernah menyentuhnya. Jadi tak ada kontrol yang runtuh saat
QR gagal — yang bertambah hanya kerja manual admin.

> Catatan: premis "kegagalan otomatis = celah" **benar secara umum** untuk kontrol yang punya jalan
> keluar manual. Di sini tidak berlaku karena kwitansinya memang bukan kontrol. Kalau kelak
> kwitansi dijadikan kontrol (§6), premis itu langsung berlaku dan harus ditangani.

---

## 3. Celah yang SEBENARNYA — berhasil tapi salah

`PaymentReceiptService::match()`:

```php
$payment = Payment::where('payment_number', $found['number'])->first();

if (! $payment) { → MISMATCH }

return $this->attach($receipt, $payment, $found['method'], ...);  // QR & OCR diperlakukan SAMA
```

Hasil OCR ditempelkan dengan keyakinan yang sama seperti hasil QR. Padahal sifatnya berbeda:

| | QR | OCR |
|---|---|---|
| Salah baca satu digit | **mustahil** — ada checksum & error correction | **mungkin** |
| `PAY-202608-0042` terbaca `PAY-202608-0043` | tidak terjadi | bisa terjadi |
| Kalau nomor salah-baca itu **ada** di DB | — | lolos kedua gerbang, **menempel diam-diam ke pembayaran pelanggan lain** |

**Kedua gerbang yang ada tidak menangkap kasus ini.** Gerbang 1 (pola `PAY-YYYYMM-NNNN`) lolos karena
bentuknya benar. Gerbang 2 (payment ada di DB) lolos karena pembayarannya memang ada — milik orang
lain.

### Kenapa ini kelas bug yang sama dengan dua kejadian sebelumnya

| Kejadian | Gejala |
|---|---|
| Idempotency key dipakai bersama (review Fase 4 #1) | toast **hijau**, uang tak tercatat |
| Notifikasi di dalam `try` (review Fase 1–3 #2) | `422` padahal payment tersimpan |
| **OCR salah tempel (ini)** | status **Cocok** hijau, dokumen nempel ke pelanggan lain |

Yang berbahaya bukan yang gagal — yang berbahaya adalah **yang gejalanya menyerupai keberhasilan**,
karena tak ada yang melaporkannya.

### Dampak konkret

- Pelanggan A menerima/tercatat kwitansi milik pelanggan B.
- Saat sengketa, bukti yang dibuka justru milik orang lain — memperburuk, bukan menyelesaikan.
- Salahnya senyap: statusnya `matched`, tak ada `last_error`, tak masuk daftar "butuh perhatian".

### Kenapa belum meledak

`GEMINI_API_KEY` kosong ⇒ OCR mati ⇒ semua pencocokan otomatis lewat QR yang tak bisa salah baca.

---

## 4. Prinsip yang dilanggar

> **Deterministik boleh memutuskan. Probabilistik hanya boleh mengusulkan.**

QR membaca data yang sistem sendiri cetak, dengan error correction — keluarannya fakta.
OCR menebak dari piksel — keluarannya dugaan. Menyimpan keduanya lewat jalur kode yang sama membuat
dugaan diperlakukan sebagai fakta.

---

## 5. Rancangan antisipasi

### 5.1 OCR jadi USULAN, bukan keputusan (wajib, sebelum API key diisi)

| Jalur | Sekarang | Sesudah |
|---|---|---|
| QR | auto `MATCHED` | **tetap** — memang tak bisa salah baca |
| OCR | auto `MATCHED` | status **butuh perhatian**, `detected_number` di-prefill, admin konfirmasi 1 klik |
| Manual | admin memilih | tetap (sudah masuk audit log) |

Perubahan yang diperlukan:

- `PaymentReceiptService::match()` — cabangkan berdasarkan `$found['method']`:
  - `QR` → `attach()` seperti sekarang;
  - `OCR` → simpan `detected_number` + status yang menuntut konfirmasi, **jangan** isi `payment_id`.
- Status: pakai ulang `MISMATCH`, **atau** tambah `ReceiptStatus::PERLU_KONFIRMASI` supaya bisa
  dibedakan dari "nomor tak dikenal". **Rekomendasi: status baru** — dua keadaan itu butuh tindak
  lanjut berbeda (yang satu "benarkan tebakan mesin", satu lagi "cari tahu ini berkas apa"), dan
  status yang artinya berbeda tidak boleh berbagi nama (pelajaran #6 review Fase 1–3).
- UI tab Kwitansi: baris OCR menampilkan **nama pelanggan hasil tebakan** + tombol "Benar, cocokkan".
  Menampilkan nama pelanggan penting — admin mengoreksi salah-baca digit dengan melihat nama, bukan
  dengan mengeja ulang nomor.
- `match_method` tetap `OCR` setelah dikonfirmasi (jejak: mesin yang menebak, manusia yang setuju).

**Biaya:** satu klik per kwitansi yang QR-nya rusak. Jumlahnya sedikit — QR error-correction High
menoleransi ~30% kerusakan — dan itu tepat porsi manusia: memutuskan hal yang mesin cuma bisa menebak.

### 5.2 Pengaman tambahan bila OCR tetap ingin otomatis (opsional)

Kalau kelak volumenya besar dan konfirmasi manual dirasa berat, auto-attach OCR **boleh** dihidupkan
dengan syarat koroborasi — minimal satu:

| Pengaman | Cara |
|---|---|
| Cocokkan **nominal** | OCR juga membaca angka rupiah di kwitansi; auto hanya bila sama dengan `payment.amount` |
| Cocokkan **nama pelanggan** | OCR membaca nama; auto hanya bila cocok dengan `payment.customer.full_name` |
| Tolak nomor "tetangga" | Bila nomor hasil baca berbeda 1 digit dari nomor lain yang juga ada di DB, paksa konfirmasi |

Yang ketiga paling murah dan menyasar tepat modus salah-bacanya.

### 5.3 Membuat jalan keluar manual TERLIHAT (menjawab kekhawatiran awal user)

Premis "sistemnya error" baru berbahaya kalau tak ada yang menghitungnya. Dua tambahan murah,
datanya sudah ada:

1. **Tingkat kegagalan per pengunggah** — berapa persen berkas dari orang tertentu berakhir
   `FAILED`/`MISMATCH`. Kalau selalu tinggi, itu pola yang layak ditanya (logika sama dengan aging
   kunjungan §12 modul).
2. **Tonjolkan `via Manual`** di daftar kwitansi. `match_method` sudah tersimpan; tinggal ditampilkan
   mencolok supaya jalan keluar itu terlihat, bukan tenggelam di antara yang otomatis.

### 5.4 Yang TIDAK perlu dilakukan

- Mengganti pustaka OCR atau menaikkan "akurasi model" — masalahnya bukan akurasi, tapi **cara hasil
  tebakan diperlakukan**. Model seakurat apa pun tetap probabilistik.
- Mematikan OCR selamanya — fallback-nya berguna; yang salah cuma tingkat kepercayaannya.

---

## 6. Kalau kelak kwitansi dijadikan KONTROL

Baru di skenario ini premis awal user berlaku penuh: begitu kwitansi menentukan sesuatu, "QR gagal"
berubah jadi alasan yang menguntungkan pihak yang diawasi.

| Opsi kontrol | Konsekuensinya pada desain sekarang |
|---|---|
| Kwitansi bertanda tangan pelanggan (OCR baca tulisan tangan) | Kwitansi jadi dokumen **lapangan** — membatalkan keputusan "kwitansi menunggu kantor" |
| Kwitansi bernomor prasetak + registri blok per kolektor | Tak butuh AI sama sekali; kontrol klasik door-to-door |
| Notifikasi pembayaran ke pelanggan | Tak menyentuh kwitansi; menurut analisa §12 modul, **paling efektif per rupiah** |

Kalau salah satu dari dua yang pertama dipilih, §5.3 naik dari "murah dan berguna" jadi **wajib** —
tingkat kegagalan harus dipantau, dan kegagalan berulang harus punya konsekuensi.

---

## 7. Yang perlu diputuskan

- [ ] **D1** — Status baru `PERLU_KONFIRMASI`, atau pakai ulang `MISMATCH`? *(rekomendasi: status baru)*
- [ ] **D2** — Setelah §5.1, apakah §5.2 (auto-attach berkorolasi) diperlukan, atau konfirmasi manual dianggap cukup? *(rekomendasi: cukup, sampai volumenya terbukti berat)*
- [ ] **D3** — §5.3 dikerjakan sekarang atau menyusul? *(rekomendasi: sekarang, murah)*
- [ ] **D4** — Apakah kwitansi kelak dijadikan kontrol (§6)? Jawaban ini mengubah keputusan "kwitansi menunggu kantor"

## 8. Kriteria selesai

- [ ] Hasil OCR **tidak pernah** langsung mengisi `payment_id`
- [ ] Test: OCR yang mengembalikan nomor **milik pembayaran lain** tidak menempel diam-diam
- [ ] Test: hasil QR tetap otomatis (tidak ikut diperlambat)
- [ ] UI menampilkan tebakan OCR beserta **nama pelanggan**, dengan konfirmasi satu klik
- [ ] Jejak `match_method` membedakan QR / OCR-dikonfirmasi / Manual
- [ ] Dokumentasi modul ([business-logic §12](../../kolektor/business-logic.md#12-kwitansi--sumbu-dokumen), flowchart §8, uat-checklist §5) diperbarui
