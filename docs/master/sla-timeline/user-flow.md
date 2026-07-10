# User Flow: Master Timeline SLA

## Skenario Utama: Admin Mengatur Batas Waktu SLA per Paket

**Aktor**: Admin (permission `packages.create`/`packages.update`)

**Pre-condition**: User sudah login dan berada di area dashboard, minimal 1 paket internet aktif sudah ada di Master Paket Internet.

**Langkah-langkah**:
1. User mengklik menu **Master Data** di Sidebar.
2. User memilih sub-menu **Master Timeline SLA**.
3. Sistem menampilkan grid: baris = daftar paket internet aktif, kolom = 8 jenis tiket (Survey, Pemasangan Baru, Maintenance, Ambil Modem, Relokasi/Pemindahan, Customer Request, Office Request, Infrastruktur Request). Tiap sel sudah terisi nilai default/tersimpan.
4. User mengubah angka durasi pada sel tertentu (mis. Survey utk paket "Dedicated100" dari 24 jam → 12 jam) dan/atau ganti satuan (jam/hari).
5. Begitu user pindah fokus dari input (`onchange`), sistem otomatis menyimpan perubahan lewat AJAX — tidak perlu klik tombol simpan terpisah, tidak reload halaman.
6. Sistem menampilkan notifikasi kecil "Tersimpan." di bawah tabel.

**Post-condition**: Baris `package_sla_settings` utk kombinasi paket+jenis tiket tsb ter-update. Tiket **baru** yang dibuat sesudahnya utk customer paket ybs otomatis pakai angka baru. Tiket yang sudah ada sebelumnya **tidak berubah** (snapshot beku).

## Skenario Turunan: Dampak ke FOP saat Tiket Dibuat

**Aktor**: Sistem (otomatis, tidak ada interaksi user langsung) — dipicu saat FOP/Admin membuat tiket baru (Survey auto-sync registrasi, atau tiket manual MTN/C-Req/dst).

**Langkah-langkah**:
1. User (FOP/Admin) membuat tiket baru — lewat auto-sync registrasi customer (Survey/Pemasangan) atau form manual (MTN/C-Req/O-Req/INFR/Relokasi/DEAC).
2. Sistem mencari paket internet customer terkait tiket.
3. Sistem mengambil angka SLA dari Master Timeline utk kombinasi paket+jenis tiket tsb (atau default global kalau belum diatur).
4. Sistem menyimpan angka itu ke tiket (snapshot), lalu memakainya utk hitung deadline & progress bar di FOP Dashboard / Antrean Survey / Verif Pemasangan.

**Post-condition**: FOP melihat batas waktu wajib tangani yang sesuai paket customer, tanpa perlu tahu detail matrix di baliknya.
