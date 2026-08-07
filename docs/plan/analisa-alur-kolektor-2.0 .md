# BELUM DI KERJAKAN

# Analisa Alur Sistem Koleksi / Penagihan

## Diagram Alur

![Alur Kolektor](./alur-kolektor-2.0.svg)

---

## 1. Aktor dalam Sistem

- **Admin** — mengelola Worksheet, meng-assign pelanggan ke kolektor, melakukan cross check & verifikasi setoran, serta bisa membayar tagihan pelanggan secara langsung.
- **Kolektor** — menagih pelanggan sesuai Worklist miliknya, memegang saldo (uang cash hasil tagihan) sampai disetorkan ke Admin.

---

## 2. Pembayaran Langsung oleh Admin

Admin bisa membayar sendiri pada menu **Tagihan** — bagian ini **sudah benar** dan tidak ada perubahan. Ini adalah jalur terpisah, tidak melalui kolektor sama sekali.

---

## 3. Struktur Menu: Kolektor sebagai Submenu Worksheet Admin

**Kolektor adalah submenu** di bawah menu **Worksheet Admin** (bukan relasi data "kolektor = worksheet admin", melainkan struktur navigasi/menu). Strukturnya kurang lebih:

```
Worksheet Admin
 ├─ (Panel utama: List Kolektor + List Pelanggan belum di-assign)
 └─ Kolektor  ← submenu
      └─ Worklist per kolektor (collectors/{id}?tab=worklist)
```

### Layout Worksheet Admin — 2 Panel

| Panel | Isi | Aksi |
|---|---|---|
| Kiri | Daftar Kolektor | — |
| Kanan | Daftar pelanggan yang **belum di-assign** ke kolektor mana pun | Checkbox (single/multi-select) → pilih kolektor tujuan → **Assign** |

---

## 4. Worklist Kolektor

Sudah benar — menampilkan pelanggan yang **sudah jatuh tempo** pembayaran. Kolektor bisa membayarkan tagihan secara:

- **1 by 1** (per pelanggan), atau
- **Massal / bulk**, sama seperti mekanisme di halaman Tagihan.

---

## 5. Mekanisme Saldo Kolektor

Saldo di sini merepresentasikan **uang cash** yang sedang dipegang kolektor, sehingga ada 2 pemicu perubahan:

### a) Saldo Bertambah — Saat Menagih Pelanggan

Trigger: kolektor klik tombol **Selesai/Pembayaran**. Ada 2 kemungkinan hasil:

- **Lunas** → saldo kolektor bertambah sejumlah uang yang diterima; pelanggan pindah dari daftar "belum bayar" ke tab **Selesai** di `collectors/{id}?tab=worklist`.
- **Pending** (pelanggan belum ada uang, sedang di luar kota, dll) → saldo **tidak** berubah; pelanggan tetap berada di Lembar Kerja aktif kolektor untuk ditagih ulang nanti.

### b) Saldo Berkurang — Saat Setor ke Admin

Trigger: kolektor menyetorkan hasil tagihan ke Admin.

- Saldo kolektor berkurang sejumlah uang yang disetorkan.
- Sistem menampilkan **Alert/Modal**: "Menunggu Verifikasi Admin" — karena setoran masih harus di-*cross check*.
- Admin melakukan cross check, lalu melakukan **Verifikasi** untuk menyelesaikan proses setoran.

### c) Proses Invoice & OCR — Setelah Admin Cross Check

Setelah Admin melakukan cross check, alur berlanjut ke proses pembuatan dan validasi invoice:

1. Admin **menandai pelanggan** yang ingin dicetak invoice-nya.
2. Setelah semua invoice yang ditandai **sudah jadi**, Admin **meng-upload invoice tersebut secara bersamaan** (sekaligus/bulk).
3. Saat upload, sistem menjalankan **OCR** (integrasi dengan **Gemini OCR**) yang melakukan:
   - **Scanning** invoice yang diupload.
   - **Validasi** — mencocokkan data pada invoice dengan data pelanggan yang telah dibayarkan (hasil setoran kolektor).
4. Setelah proses **OCR selesai** dan validasi berhasil, maka **Status Verifikasi pada Kolektor** berubah menjadi **Berhasil**.

---

## 6. Ringkasan Alur End-to-End

1. Admin buka menu **Worksheet Admin** → lihat 2 panel (List Kolektor di kiri, List Pelanggan belum di-assign di kanan).
2. Admin check/multi-check pelanggan → assign ke kolektor tertentu.
3. Untuk melihat detail per kolektor, Admin masuk ke submenu **Kolektor** → pilih kolektor → tampil Worklist (`collectors/{id}?tab=worklist`).
4. Kolektor menagih (1 by 1/massal) → klik Selesai/Pembayaran.
   - **Lunas** → saldo kolektor bertambah, pelanggan pindah ke tab `worklist` (selesai).
   - **Pending** → saldo tetap, pelanggan tetap di worksheet aktif kolektor.
5. Kolektor setor uang hasil tagihan ke Admin → saldo kolektor berkurang.
6. Modal "Menunggu Verifikasi Admin" muncul.
7. Admin melakukan cross check.
8. Admin menandai pelanggan yang ingin dicetak invoice-nya.
9. Setelah invoice sudah jadi semua, Admin meng-upload invoice tersebut secara bersamaan.
10. Sistem menjalankan **OCR (integrasi Gemini OCR)** untuk scanning dan validasi invoice terhadap pelanggan yang dibayarkan.
11. Setelah OCR selesai dan validasi berhasil → **Status Verifikasi Kolektor: Berhasil**.
12. *(Terpisah)* Admin bisa langsung bayar tagihan pelanggan sendiri via menu Tagihan, tanpa alur di atas.

---

## 7. Status Konfirmasi

Poin-poin berikut sudah dikonfirmasi sepanjang diskusi:

| No | Poin | Status |
|---|---|---|
| 1 | "Kolektor = Worksheet Admin" dimaksudkan sebagai **submenu**, bukan relasi data yang diturunkan | ✅ Dikonfirmasi |
| 2 | Saat status **Pending**, saldo **tidak bertambah sama sekali** (bukan bertambah lalu dikurangi) | ✅ Dikonfirmasi |
| 3 | "Saldo berkurang" adalah proses **setoran/deposit** kolektor ke Admin (bukan pembayaran baru ke pelanggan) | ✅ Dikonfirmasi |
| 4 | Setelah Admin cross check, ada tahap tandai pelanggan → cetak invoice → upload bersamaan → OCR (Gemini) validasi → Status Verifikasi Kolektor jadi **Berhasil** | ✅ Ditambahkan |
