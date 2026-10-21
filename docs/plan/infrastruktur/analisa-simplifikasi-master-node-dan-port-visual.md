# Catatan Keluhan & Analisa: Simplifikasi Master Node Jaringan & Port Visual

- **Tanggal:** 17 Oktober 2026
- **Status:** Analisa & Rumusan Arah Perbaikan Operasional (Menindaklanjuti audit `analisa-tumpang-tindih-konsep-network-node.md`)
- **Tujuan Dokumen:** Mencatat keluhan riil penggunaan sistem di lapangan, membongkar akar masalah friksi UI/UX, dan menetapkan blueprint simplifikasi agar Master Node & Peta Infrastruktur mudah dioperasikan sehari-hari.

---

## 1. Intisari Filosofi & Batasan Sistem

> **Pernyataan Kunci User:**
> *"Sebenarnya konsep Master Node + Peta Infrastruktur ini hanyalah untuk tracking barang dari gudang digunakan pada infra mana dan juga untuk infrastruktur tipis-tipis."*

Sistem ini **BUKAN** Network Management System (NMS) kelas enterprise (seperti NetBox, UNMS, atau GIS fiber optik telco) yang memodelkan redaman optik dBm, core per core, dan simulasi kartu OLT chassis.  
Fokus sebenarnya adalah:
1. **Aset Gudang Trackability:** Barang gudang (dengan SN / roll) terpasang di mana? (Di rumah Pelanggan A atau di Node ODP X).
2. **Infrastruktur Tipis-tipis:** Mengetahui posisi ODP/ODC di peta, kapasitas port-nya, mana yang kosong, dan siapa pelanggan yang colok di sana.

---

## 2. Rincian Keluhan Pengguna (Pain Points Operasional)

| Area | Keluhan Nyata Lapangan | Dampak bagi Pengguna |
|---|---|---|
| **Pembuatan Node Baru** | Bingung saat mengisi form. Muncul istilah rumit: *Anchor POP*, *Placement Child/Parent*, *Kapasitas Ringkasan vs Port Splitter*, *Aturan Penempatan Tipe*. | Admin/Teknisi takut salah pilih; proses pendaftaran ODP baru terasa berbelit-belit. |
| **Penamaan & Istilah Port** | Istilah dan label port terasa asing dan tidak mencerminkan istilah lapangan ISP. | Pengguna merasa berjarak dengan sistem karena bahasanya adalah bahasa kodingan programmer. |
| **Koneksi Perangkat Membingungkan** | Muncul konsep OLT Chassis, Slot Kartu, dan sambungan node yang terkesan "putus-putus" atau tidak terhubung dengan benar. | Memunculkan status janggal seperti *"Node kartu belum memiliki aset Gudang"* atau link melayang. |
| **Pengecekan Status Port** | Sulit mengetahui: *"Port ini milik device/pelanggan siapa?"* dan *"Port mana yang masih kosong?"*. | Harus membaca tabel vertikal panjang; tidak ada gambaran cepat saat tim sales/teknisi butuh info slot ODP untuk pasang baru. |
| **Eksekusi Port Terbatas** | Di halaman detail ODP, tombol port kosong hanya menawarkan *"Pilih anak langsung"*. Tidak bisa langsung memilih/menghubungkan pelanggan dari layar tersebut. | Alur buntu; teknisi/admin tidak tahu cara memasukkan pelanggan ke port ODP secara langsung. |
| **Birokrasi Pelepasan Port** | Mengosongkan port membutuhkan centang konfirmasi fisik dan input teks catatan wajib. | Terlalu kaku untuk sekadar update data operasional lapangan. |

---

## 3. Komparasi: Kenapa Tab "Inventaris" Lebih Mudah Dipahami?

Pengguna secara eksplisit menyatakan bahwa **Tab Inventaris di Peta Infrastruktur jauh lebih mudah dimengerti** daripada Master Node Jaringan.

### Mengapa Tab Inventaris Berhasil:
- **Konkret & Ada Wujudnya (1:1 Dunia Nyata):**
  - Barang: *Switch D-Link 8 Port* / *Kabel Dropcore 1 Roll* / *Modem ONT ZTE*.
  - Nomor Seri: *SN: ZTEGC123456*.
  - Pelaku: *Dibawa Teknisi Rian*.
  - Titik Pasang: *Terpasang di ODP-SMN-01* atau *Pelanggan Pak Budi*.
- Otak manusia sangat mudah memahami alur fisik: **Barang keluar gudang → dibawa teknisi → nempel di tiang / rumah pelanggan.**

### Mengapa Master Node Saat Ini Gagal Berkomunikasi:
- Menggantikan wujud barang fisik dengan **abstraksi relasi pohon kaku**:
  - `olt_slots` menuntut input angka slot 1-64.
  - Kartu OLT diperlakukan sebagai "Node" yang punya koordinat GPS dan alamat jalan (padahal hanya kartu elektronik di rak).
  - Port ODP memperlakukan pelanggan dan uplink node di kolom yang sama tanpa pembeda visual yang jelas.

---

## 4. Blueprint Solusi Simplifikasi (Cara Memperbaiki)

### A. Simplifikasi Form "Tambah ODP / Node Baru"
Ganti form bertingkat dengan alur cepat to-the-point:

1. **Nama & Kode:** misal `ODP-SMN-04` / `ODC Pasar Pon`.
2. **Wilayah / Cabang:** Dropdown Cabang/POP (misal `Cabang Siman`).
3. **Kategori Node:** Pilihan tombol chip:
   - `[ ODP (Kotak Bagi Pelanggan) ]` *(Default)*
   - `[ ODC (Kabinet Distribusi) ]`
   - `[ POP / Server Site ]`
4. **Kapasitas Port:** Tombol cepat `[ 8 Port ]` `[ 16 Port ]` `[ 24 Port ]` `[ Custom ]`.
5. **Titik Lokasi:** Koordinat Lat/Long (bisa klik di peta) & patokan alamat jalan.

*Catatan: Sembunyikan istilah "Anchor", "Parent Node", "Slot OLT" dari form utama ODP standar.*

---

### B. Visualisasi "Port Visual Grid" (Wujud Asli ODP)
Ganti tabel baris vertikal kaku dengan **Panel Visual Port Grid** (menyerupai penampang depan ODP):

```text
┌──────────────────────────────────────────────────────────────┐
│ ODP-SMN-04 (8 Port Splitter) — Cabang Siman                  │
│ Terpakai: 5 / 8 Port  ·  Sisa Kosong: 3 Port                 │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  [Port 1]        [Port 2]        [Port 3]        [Port 4]    │
│  ● Terisi        ● Terisi        ○ KOSONG        ● Terisi    │
│  Budi Santoso    Siti Aminah     (Klik Pasang)   Joko Tarub  │
│  ZTE-8812        Huawei-442                      ZTE-9910    │
│                                                              │
│  [Port 5]        [Port 6]        [Port 7]        [Port 8]    │
│  ○ KOSONG        ▲ UPLINK        ● Terisi        ✕ RUSAK     │
│  (Klik Pasang)   Ke ODC-01 (P2)  Ahmad Dhani     (Redaman)   │
│                                  ZTE-3311                    │
└──────────────────────────────────────────────────────────────┘
```

#### Aturan Warna & Status Port Visual:
- 🟢 **Hijau (Kosong / Free):** Klik langsung membuka dialog cepat: *"Pasang Pelanggan di Port Ini"* (cari nama/ID pelanggan, langsung sambung).
- 🔵 **Biru (Terpasang Pelanggan):** Menampilkan nama pelanggan, ID pelanggan, dan SN modem yang terpasang. Klik untuk melihat detail pelanggan atau opsi *"Lepas / Pindah Port"*.
- 🟡 **Kuning/Oranye (Uplink/Feeder):** Menandakan port dipakai untuk jalur sambungan antar node (misal ODC → ODP).
- 🔴 **Merah / Abu-abu (Rusak / Reserved):** Port tidak dapat digunakan karena kendala fisik/redaman.

---

### C. Alur Langsung Assign Pelanggan (Tanpa Jalan Memutar)
- Pada port yang kosong, sediakan aksi langsung:
  1. Input / Search Pelanggan (autocomplete nama / CID).
  2. Sistem otomatis mengaitkan pelanggan ke port tersebut.
  3. Sistem langsung mendeteksi modem ONT pelanggan yang aktif (`customer_devices` / `inventory_serials`).
- Tidak perlu memaksa membuat task maintenance rumit jika admin hanya ingin merapikan pencatatan data port eksisting.

---

### D. Penyatuan Nyata Node dengan Tab "Aset & Inventaris"
Pada halaman detail Node/ODP, sediakan tab **"Barang Terpasang di Node Ini"**:
- Tampilkan daftar barang gudang yang statusnya `DEPLOYED` di node tersebut:
  - 1x Pasif Splitter 1:8
  - 1x Box ODP 8 Core
  - 1x SFP PON (jika di OLT/POP)
- Dengan begini, admin gudang dan tim lapangan melihat data yang konsisten antara fisik tiang dengan laporan inventaris gudang.

---

## 5. Kesimpulan & Rekomendasi Eksekusi

1. **Jangan buang database yang ada**, tetapi **bungkus dengan antarmuka yang ramah manusia**.
2. Fokuskan perbaikan pada:
   - Modal Tambah Node yang bersih dan menggunakan istilah umum ISP.
   - Tampilan Port ODP diubah menjadi Grid Visual dengan aksi klik langsung (Pasang Pelanggan / Kosongkan).
   - Menghubungkan ringkasan aset terpasang dari ledger Gudang langsung ke dalam detail node.
