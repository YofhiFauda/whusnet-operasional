# Rencana Kerja: Simplifikasi Operasional Master Node & Port Visual

- **Tanggal:** 17 Oktober 2026
- **Status:** **Selesai (Completed & Verified)**
- **Dasar:** Keluhan operasional & analisa di `docs/plan/infrastruktur/analisa-simplifikasi-master-node-dan-port-visual.md`
- **Tujuan:** Mengubah interaksi Master Node & ODP yang kaku menjadi visual, mudah dikelola, dan langsung terhubung dengan alur pelanggan serta inventaris gudang.

---

## Tahapan Pekerjaan (Phases)

### Tahap 1: Humanisasi Modal "Tambah ODP / Node Baru"
- [x] Ubah modal `showCreateModal` di `resources/views/network/nodes/index.blade.php`.
- [x] Ganti istilah teknis asing (ubah bahasa programmer menjadi bahasa lapangan: "Tipe Perangkat", "Koneksi Jalur", "Cabang / Wilayah POP", "Node Induk (Sumber Kabel)", "Kode Titik", "Nama Titik/Lokasi").
- [x] Tombol kapasitas instan: `[8 Port]`, `[16 Port]`, `[24 Port]`, `[32 Port]` yang otomatis mengisi input form.
- [x] Petunjuk bantuan kontekstual di bawah tiap input agar teknisi/admin tidak ragu saat mendaftar titik baru.

### Tahap 2: Transformasi Port ODP Menjadi "Port Visual Grid"
- [x] Ganti tabel vertikal kaku di `resources/views/network/nodes/show.blade.php` dengan komponen Visual Grid Port (layout soket ODP).
- [x] Format kotak port dengan indikator status warna yang jelas:
  - 🟢 **Hijau (Kosong / Free):** Tersedia untuk pelanggan baru dengan tombol aksi langsung.
  - 🔵 **Biru (Terisi Pelanggan):** Menampilkan nama pelanggan, ID pelanggan, serta modem/ONT (SN & model).
  - 🟡 **Kuning (Uplink):** Port distribusi ke ODC / POP.
  - 🔴 **Merah / Abu-abu (Terpakai Manual / Belum Terverifikasi).**
- [x] Ringkasan statistik di atas grid: Total Port, Jumlah Terpakai (+ persentase), dan Sisa Port Kosong.

### Tahap 3: Alur Cepat Sambung Pelanggan (Assign Customer Direct Action)
- [x] Sediakan controller endpoint `NetworkNodeController::assignCustomerPort()` dan route `network.node-ports.assign-customer`.
- [x] Di setiap port kosong pada ODP, tampilkan select box cepat berisi daftar pelanggan di cabang tersebut yang belum punya port.
- [x] Satu klik submit langsung menempelkan pelanggan ke port ODP.
- [x] Sediakan opsi lepas port yang cepat dan terintegrasi pada dropdown card port.
- [x] Buat automated feature test `test_admin_dapat_menyambungkan_pelanggan_langsung_ke_port_odp_lewat_form()` di `tests/Feature/NetworkPortAssignmentTest.php` (Lulus hijau).

### Tahap 4: Hubungkan Nyata dengan Aset Inventaris Gudang
- [x] Tampilkan panel "Aset Gudang Terpasang" di detail node dengan data serial number dan saldo infrastruktur fisik (roll/qty).
- [x] Nomor seri (SN) pada aset terpasang dibuat interaktif (dapat diklik langsung membuka riwayat penelusuran gudang di `warehouse.traceability.index`).
- [x] Setiap modem ONT pelanggan yang menancap di port ODP terhubung langsung dengan status perangkat dan sumber data serial gudang.
