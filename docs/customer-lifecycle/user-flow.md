# User Flow — Customer Verifikasi & Onboarding Lifecycle

Aktor: **Sales/Admin** (registrasi, terminasi), **Teknisi** (survey & pemasangan lapangan), **FOP/Admin** (verifikasi & aktivasi).

## 1. Sales/Admin — Registrasi Pelanggan Baru

1. Isi form registrasi: identitas, alamat, paket internet, upload foto KTP/rumah/kontrak.
2. Submit (`POST /customers`) → sistem generate `customer_code` (sequence per-POP), status langsung `waiting_survey`.
3. Pelanggan otomatis masuk antrean survey (`/surveys/queue`) — gak perlu langkah manual tambahan buat "kirim ke survey".

## 2. Teknisi — Proses Survey

1. Buka `/surveys/queue`, cari pelanggan (search by nama/NIK/HP).
2. Klik "Mulai Survey" → sistem cek gak ada Task lain yang lagi `in_progress` di tim teknisi itu (kalau ada, ditolak — harus selesaikan dulu task sebelumnya).
3. Timer survey mulai jalan (`started_at` dicatat).
4. Datang ke lokasi, kerjakan survey, balik ke app → isi form laporan: alat yang dibutuhkan, estimasi kabel, ODP terdekat, tingkat kesulitan, foto ODP + foto rumah (wajib), catatan.
5. Pilih status laporan: `completed` (survey selesai, lanjut ke antrean verifikasi) / `failed` / `pending` (belum kelar, submit ulang nanti).
6. Submit → kalau `completed`, pelanggan otomatis pindah ke antrean Verifikasi FOP.

## 3. FOP — Verifikasi Survey & Proses ke Tim Pemasangan

1. Buka `/verifications/queue` — lihat pelanggan status `waiting_acc`/`surveyed`/dst, plus list teknisi yang lagi standby/aktif.
2. Klik "Proses ke TIM" → pelanggan pindah ke `waiting_installation`, otomatis masuk antrean pemasangan teknisi. Laporan survey teknisi ikut ter-approve otomatis (1 klik, 2 efek).

## 4. Teknisi — Proses Pemasangan

1. Buka antrean pemasangan, klik "Mulai Pemasangan" → sama seperti survey, dicek dulu gak ada task lain yang lagi jalan.
2. Timer pemasangan mulai. Kerjakan instalasi fisik.
3. Isi form laporan lengkap: device (tipe, brand, serial, MAC, WiFi, PPPoE), data teknis (ODP/OLT/VLAN), hasil speedtest (upload/download/jitter/latency/packet loss), foto pemasangan + kontrak + TTD pelanggan + foto speedtest.
4. Pilih status: `completed` (semua foto wajib lengkap, kalau kurang satu aja ditolak dengan pesan spesifik) / `failed` (butuh revisi, balik ke antrean) / progress (belum kelar).
5. Submit `completed` → pelanggan otomatis masuk antrean Verifikasi Admin.

## 5. FOP/Admin — Verifikasi Admin (Aktivasi Final)

1. Buka `/verifications/{customer}/admin` — review semua data: device, technical detail, hasil instalasi, paket, alamat.
2. Pilih salah satu aksi:
   - **Approve/Aktivasi** — isi manual: periode billing, tanggal terbit/jatuh tempo, subtotal, discount, PPN, biaya prorate/kabel/tiang/instalasi tambahan → submit. Sistem generate Invoice AWAL, generate CID, aktivasi pelanggan+service.
   - **Reject** — isi alasan → pelanggan final ditolak, gak bisa diproses lagi.
   - **Revisi** — isi alasan → pelanggan balik ke antrean pemasangan teknisi buat perbaikan, laporan lama gak hilang (catatan revisi ditambahkan).
3. Setelah aktivasi, pelanggan lanjut ke alur normal billing bulanan (lihat [docs/billing-pembayaran](../billing-pembayaran/README.md)).

## 6. Admin — Terminasi Layanan

1. Dari halaman pelanggan aktif, klik "Hentikan Layanan" → isi alasan (wajib) → submit (`POST /customers/{customer}/terminate`).
2. Status pelanggan langsung `terminated`, service `berhenti`. **Aksi ini gak lewat validasi state machine** — bisa dilakukan dari status manapun, gak tercatat di riwayat transisi resmi (`customer_status_logs`), cuma di audit log biasa.

## Guard / Permission per Tahap

| Aksi | Permission |
|------|------------|
| Registrasi pelanggan | `customers.create` |
| Mulai/lapor Survey | `customers.detail.survey.update` |
| Lihat antrean Survey | `customers.detail.survey.view` |
| Proses ke Tim, Aktivasi, Reject, Revisi | `customers.detail.installation.validate` |
| Mulai/lapor Pemasangan | `customers.detail.installation.update` |
| Lihat antrean Verifikasi | `customers.detail.installation.view` |
| Upload/lihat dokumen tambahan | `customers.detail.documents.upload` / `.view` |
| Tambah/update device manual | `customers.detail.devices.create` / `.update` |

## Hal yang Perlu Diperhatikan (Gotcha)

- **Konflik jadwal teknisi** berlaku di 2 tahap (Survey & Pemasangan) — kalau teknisi lagi pegang Task `in_progress` lain, mulai kerjaan baru bakal ditolak. Solusi: selesaikan atau tandai Pending task yang lagi jalan dulu.
- **Foto wajib saat pemasangan `completed`** — 4 foto (pemasangan, kontrak, TTD, speedtest) dicek satu-satu, submit gagal kalau ada yang belum diupload (baik baru maupun yang sudah tersimpan sebelumnya).
- **CID baru muncul saat Aktivasi** — sebelum status `active`, pelanggan belum punya CID sama sekali.
- **Terminasi gak lewat state machine** — beda dari semua transisi lain, jadi gak masuk riwayat `customer_status_logs`, cuma di `AuditLog`.
