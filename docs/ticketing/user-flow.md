# User Flow — Modul Ticketing

## 1. Helpdesk/NOC/Sales — Submit Tiket Baru

1. Buka sidebar **TICKETING** → **New Ticket** (`/tickets/new`).
2. Pilih **Tipe Ticket** (MTN atau C-REQ) dan **Prioritas**.
3. Ketik CID atau nama pelanggan di kolom **CID / Pelanggan** → hasil pencarian muncul (debounce 300ms, lewat `/api/tickets/lookup-customer`).
4. Klik salah satu hasil → panel **Data Pelanggan** otomatis terisi 8 field: Nama, Alamat, No. HP, POP/Cabang, ODP, Paket, Perangkat Pelanggan, Koordinat (link Google Maps kalau ada).
5. Isi **Detail Keluhan** (wajib) dan **Catatan Teknis** (opsional).
6. Opsional: lampirkan bukti foto/PDF (maks 5 file, 5 MB/file).
7. Klik **CREATE TICKET** → redirect ke halaman detail tiket (`/tickets/{id}`), pesan sukses menyebutkan nomor tiket dan nomor Task FOP yang otomatis dibuat.
8. Tiket masuk ke bucket **Ticket Masuk** (status `draft`, belum ada teknisi) — menunggu FOP assign.

## 2. FOP — Assign Teknisi ke Tiket yang Masuk

1. Buka `/fop-tasks` — tiket dari Ticketing muncul di tabel dengan kategori MTN/C-REQ, status Draft.
2. Klik tombol **Edit** pada baris tiket itu.
3. Modal terbuka dalam **mode Ticketing** (otomatis terdeteksi karena tiket ini punya `ticket` terkait): panel CID/data pelanggan tampil **read-only**, Detail Keluhan/Catatan Teknis tampil sebagai teks (gak bisa diedit di sini — edit beneran lewat Ticketing).
4. Pilih **Tanggal & Waktu** dan **Pilih Teknisi**.
5. Simpan → `FopTask.status` otomatis naik dari `draft` ke `terjadwal`, `Task` eksekusi teknisi otomatis dibuat, tiket pindah dari bucket **Ticket Masuk** ke **Ticket di Proses**.

## 3. FOP — Bikin Tiket MTN/C-REQ Langsung dari Task FOP

1. Buka `/fop-tasks` → klik **Tambah Task FOP**.
2. Pilih **Tipe Task** = MTN atau C-REQ → form otomatis berubah ke mode Ticketing (field POP/Desa/Tugas/Issue generik disembunyikan).
3. Cari & pilih pelanggan lewat CID — panel data pelanggan otomatis terisi, sama persis `/tickets/new`.
4. Isi Detail Keluhan, Catatan Teknis (opsional), Lampiran (opsional).
5. Pilih **Tanggal & Waktu** dan **Prioritas** (selalu wajib).
6. **Pilih Teknisi** — opsional di sini:
   - **Dikosongin** → tiket dibuat sebagai Draft, masuk bucket "Ticket Masuk", sama kayak submit helpdesk.
   - **Diisi** → `FopTask` langsung `Terjadwal`, `Task` eksekusi langsung dibuat, gak mampir Draft.
7. Simpan → redirect balik ke `/fop-tasks` (bukan ke halaman detail tiket), pesan sukses menyesuaikan (disebut "langsung dijadwalkan" atau "masuk ke Ticket Masuk").

## 4. Membatalkan Tiket

1. Pembatalan **cuma bisa dari halaman Task FOP** (`/fop-tasks`) — modul Ticketing sendiri sengaja gak punya tombol/endpoint cancel.
2. Butuh permission `fop_tasks.cancel` (role owner/admin/fop secara default).
3. Klik tombol **Cancel** pada baris tiket → isi alasan pembatalan (wajib) → konfirmasi.
4. `FopTask.status` jadi `dibatalkan`, `Task` eksekusi ikut dibatalkan (kalau udah ada & masih aktif), dan **dua riwayat** ditulis sekaligus: satu di "Histori Status" Task FOP, satu di "Riwayat Ticketing".
5. Tiket pindah ke bucket **Ticket Dibatalkan**.

## 5. Melihat Detail Tiket

**Dari sisi Ticketing** (`/tickets/{id}`):
- Header: nomor tiket, tipe, status, prioritas, nama pelanggan, POP, **Assigned by** (pengirim), **Created** (waktu submit).
- Info box link ke Task FOP terkait (kalau masih ada).
- Panel snapshot data pelanggan (8 field, versi saat tiket dibuat).
- Detail Keluhan & Catatan Teknis (versi utuh).
- Dua kolom riwayat berdampingan: Riwayat Ticketing & Riwayat Task FOP.
- Lampiran (kalau ada) — tombol Unduh per file.

**Dari sisi Task FOP** (`/fop-tasks/history/{id}`, cuma buat category MTN/C-REQ yang nyambung ke tiket):
- Section "Detail Ticket" di atas "Durasi & SLA Pengerjaan" — isinya sama persis panel di atas (CID, data pelanggan, keluhan, catatan teknis, lampiran, Assigned by/Created).
- Section "Riwayat Ticketing" berdampingan dengan "Histori Status" (riwayat FOP) yang udah ada.
- Link "Buka di Ticketing →" balik ke `/tickets/{id}`.
- FopTask kategori lain (SURVEY, PSB, O-REQ, dll) atau MTN/C-REQ yang dibuat manual (bukan dari Ticketing) **gak** menampilkan section ini sama sekali.

## 6. Filter & Pencarian Daftar Tiket

Di `/tickets/{bucket}`:
- **Cari**: nomor tiket, CID, nama pelanggan, atau isi keluhan.
- **Filter Tipe**: MTN atau C-REQ.
- **Ticket Saya**: cuma tampilkan tiket yang dikirim user login sendiri.
- Tab bucket (Masuk/Diproses/Selesai/Dibatalkan) di atas list, masing-masing dengan badge jumlah.
