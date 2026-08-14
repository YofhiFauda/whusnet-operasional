# Analisa: Kwitansi Otomatis ke Portal Pelanggan

**Status:** Rancangan murni — belum sprint aktif, belum boleh dieksekusi. Bergantung pada proyek
**Portal Pelanggan** (`docs/plan/qr-code/rancangan-qr-pelanggan-final.md`, Fase 5a/5b) yang juga
masih rancangan. Dokumen ini tidak mengubah kode apa pun.

**Tanggal:** 2026-08-14

---

## 0. Pemicu

Kalau admin verifikasi Setoran kolektor, kwitansi pelanggan otomatis terkirim ke portal
pelanggan — tanpa cetak, tanpa scan, tanpa OCR. Pertanyaan yang muncul dari situ: kalau begitu,
apa gunanya "Verifikasi Nota" (pipeline upload → TEKS/QR/OCR → cocokkan ke payment,
`docs/kolektor/business-logic.md` §12)?

---

## 1. Dua sumbu lama tetap utuh, tambah satu kanal baru — bukan pengganti

Sumbu KAS dan sumbu DOKUMEN (§12) **tidak berubah**. Yang ditambahkan adalah kanal ketiga: kirim
kwitansi digital ke portal, paralel dan independen dari `payment_receipts`/`ReceiptMatchMethod`.

```
Sumbu KAS      : bayar → setor → cross check → terverifikasi/selisih/lebih_setor
Sumbu DOKUMEN  : cetak (grid 8/lembar) → upload → TEKS/QR/OCR → cocokkan → payment_receipts
Kanal PORTAL   : status setoran final → render 1-halaman-digital → kirim ke akun pelanggan   [BARU]
```

**Verifikasi Nota TIDAK dihapus.** Tetap dipakai penuh untuk pelanggan non-portal (§3). Jangan
menyandera atau mematikan sebagian pipeline OCR/QR yang sudah ada — itu melanggar prinsip repo
"kalau perlu balik, balikkan utuh" yang sama berlaku sebaliknya: kalau belum ada perintah eksplisit
menghapus, jangan dihapus sebagian.

---

## 2. Trigger pengiriman

**Disepakati:** cukup status Setoran final di sisi admin, TANPA nunggu scan/OCR sama sekali.

Final = `status != menunggu_verifikasi` — bukan cuma `terverifikasi`. Konsisten dengan aturan
existing di §12: setoran yang berakhir `selisih` atau `lebih_setor` pun sudah selesai diperiksa
kantor, dan pelanggan yang bayar penuh tidak boleh kehilangan kwitansinya cuma karena kolektornya
kurang menyetor.

Render dokumennya **reuse `ReceiptPresenter`** (sudah dipakai struk thermal/A4/kartu kolektor) —
tambah varian output "1 halaman digital", bukan builder baru dari nol.

---

## 3. Siapa dapat kanal apa

### 3.1 Akun portal — digenerate otomatis, bukan opt-in

**Disepakati:** akun/PIN portal tergenerate otomatis dari sistem sejak awal untuk SEMUA pelanggan
— mustahil kosong. PIN awal dari sistem, lalu **bisa diganti sendiri oleh pelanggan** belakangan
(self-service, bukan admin yang reset/lihat ulang).

> **Divergensi dari rancangan QR existing — wajib disinkronkan nanti.** `rancangan-qr-pelanggan-final.md`
> §6.5 mendesain PIN **diterbitkan manual** (dicetak di kantor, dibawa teknisi, wajib-ganti saat
> login pertama) dan sengaja punya `pin_expires_at` (90 hari nganggur → di-null-kan) supaya
> "mayoritas pelanggan yang gak pernah login" tidak menumpuk PIN aktif tanpa guna. Keputusan di
> sini — auto-generate massal buat semua pelanggan — jalan berlawanan arah. Bukan salah, tapi kalau
> proyek portal mulai digarap, dua desain ini **harus disatukan eksplisit**, jangan diam-diam jalan
> dua arah berbeda.

### 3.2 Non-portal ≠ tidak punya akun

**Disepakati:** "non-portal" bukan soal akun ada/tidak (akun selalu ada, lihat §3.1) — melainkan
soal **kemampuan pakai** (pelanggan lansia/gaptek yang gak akan pernah buka HP-nya buat portal).
Ini atribut manusia, bukan atribut sistem, jadi tidak boleh diturunkan dari status token/PIN aktif.

### 3.3 Kanal kwitansi — hidup di detail Setoran, bukan field tersembunyi

**Disepakati:** kontrol kanal ada di **halaman detail Setoran** (tempat admin sudah membuka daftar
pelanggan yang bayar untuk verifikasi) — bukan field terpisah yang cuma hidup diam-diam di profil
pelanggan dan jarang dilihat siapa pun. Admin melihat status kirim & bisa bertindak (kirim ulang,
dsb) tepat di layar yang sedang dia pakai buat verifikasi — konsisten dengan pola yang sudah ada:
"Verifikasi Setoran ditekan → tampilkan pelanggan yang bayar", "Riwayat Setoran (SETOR-2026-0001)
→ tampilkan pelanggan yang bayar + kwitansi tunggal/massal".

---

## 4. Kegagalan kirim

**Disepakati:** retry (queue) 3x → kalau tetap gagal semua → notifikasi admin → admin bertindak
manual. Bentuk aksinya **fleksibel**, dua-duanya diterima: tombol "Kirim Ulang" (retry ke portal)
atau turun ke jalur cetak biasa (jalur lama §12). Tidak dipatok satu bentuk — keputusan detail UI
menyusul saat implementasi.

Pola ini konsisten dengan repo: kegagalan kirim tidak boleh diam-diam (`safelyNotify()`,
`ReceiptReadFailure` dilempar ulang selama jatah percobaan, bukan ditelan).

---

## 5. Yang TIDAK berubah

- Sumbu DOKUMEN lama (§12) tetap apa adanya untuk pelanggan non-portal: cetak grid 8/lembar,
  upload, TEKS→QR→OCR→manual, `payment_receipts`.
- Kanal kwitansi digital baru **tidak** menulis ke `payment_receipts`/`ReceiptMatchMethod` —
  semantiknya beda: satu "dokumen fisik yang perlu dicocokkan balik ke payment", satu lagi "kirim
  langsung karena datanya sudah pasti benar di tangan sistem sendiri, gak pernah discan".
  Menggabungkan keduanya bikin kolom match method kebingungan menampung kasus yang tak pernah
  melalui pemindaian.

---

## 6. Terbuka / belum diputuskan (menyusul saat proyek portal digarap)

1. Nama kolom & skema flag "kemampuan pakai portal" (§3.2) — di `customers` atau tabel terpisah,
   siapa yang mengisi (admin saat registrasi? auto-detect dari histori login?).
2. Skema log pengiriman kwitansi digital (status pending/terkirim/gagal/manual, jumlah percobaan)
   — dibutuhkan supaya §4 (retry+notifikasi admin) punya tempat menyimpan state, bukan cuma job
   sekali tembak tanpa jejak.
3. Bentuk final UI kirim-ulang/cetak-fallback di detail Setoran (§3.3, §4) — placement per baris
   payment atau per Setoran keseluruhan.
4. Sinkronisasi model PIN (§3.1) dengan `rancangan-qr-pelanggan-final.md` §6.5 sebelum proyek
   portal mulai — jangan biarkan dua dokumen rancangan saling bertentangan tanpa keputusan eksplisit.

---

## 7. Referensi

- `docs/kolektor/business-logic.md` §4 (siklus Setoran), §12 (Kwitansi — sumbu Dokumen)
- `docs/kolektor/README.md`
- `docs/plan/qr-code/rancangan-qr-pelanggan-final.md` §6.4–§6.6 (Portal Pelanggan, PIN)
- `docs/plan/kolektor/analisa-risiko-ocr-kwitansi.md`
- `docs/plan/kolektor/analisa-operasional-ocr-gemini.md`
