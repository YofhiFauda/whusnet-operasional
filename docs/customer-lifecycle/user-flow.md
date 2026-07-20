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

### 2b. FOP/Admin — Batalkan Survey (baru 2026-07-21)

Buat pelanggan yang **belum ditugaskan** (belum ada teknisi jalan) ATAU **udah ditugaskan tapi gak jadi/dibatalkan** (teknisi udah di-assign tapi belum/gak akan dikerjakan):

1. Klik tombol **"Batalkan"** — tersedia di 2 tempat: tabel `/surveys/queue` (Antrean Survey Lapangan, kolom Action) DAN tab Survey halaman detail Customer.
2. Isi alasan (wajib, mis. "Alamat tidak ditemukan", "Pelanggan menolak").
3. Submit → Task Survey terkait dibatalkan, pelanggan pindah ke `rejected` (masuk List **Pelanggan Gagal**).
4. Tombol ini muncul selama status masih `waiting_survey` atau `survey_in_progress` — begitu laporan udah disubmit (`completed`), pakai jalur reject di Verifikasi (§5), bukan cancel ini.
5. **Cancel Task Survey langsung dari halaman Task/tabel FOP Task gak bisa lagi** — tombol Cancel disembunyikan buat kategori Survey, harus lewat sini biar status pelanggan konsisten ke-update.

## 3. FOP — Verifikasi Survey & Proses ke Tim Pemasangan

1. Buka `/verifications/queue` — lihat pelanggan status `waiting_acc`/`surveyed`/dst, plus list teknisi yang lagi standby/aktif.
2. Klik "Proses ke TIM" → pelanggan pindah ke `waiting_installation`, otomatis masuk antrean pemasangan teknisi. Laporan survey teknisi ikut ter-approve otomatis (1 klik, 2 efek).

## 4. Teknisi — Proses Pemasangan

1. Buka antrean pemasangan, klik "Mulai Pemasangan" → sama seperti survey, dicek dulu gak ada task lain yang lagi jalan.
2. Timer pemasangan mulai. Kerjakan instalasi fisik.
3. Isi form laporan lengkap: device (tipe, brand, serial, MAC, WiFi, PPPoE), data teknis (ODP/OLT/VLAN), hasil speedtest (upload/download/jitter/latency/packet loss), foto pemasangan + kontrak + TTD pelanggan + foto speedtest.
4. Pilih status: `completed` (semua foto wajib lengkap, kalau kurang satu aja ditolak dengan pesan spesifik) / `failed` (butuh revisi, balik ke antrean) / progress (belum kelar).
5. Submit `completed` → pelanggan otomatis masuk antrean Verifikasi Admin.

### 4b. Admin/FOP — Batalkan Pemasangan (baru 2026-07-21)

Setara Batalkan Survey (§2b), buat tahap Pemasangan:

1. Buka tab Pemasangan halaman detail Customer → klik **"Batalkan Pemasangan"**.
2. Isi alasan → submit. Muncul selama status `waiting_installation`, `installation_in_progress`, atau `revision_installation` (belum submit laporan `completed`).
3. Task Pemasangan terkait dibatalkan, pelanggan pindah ke `rejected` (List Pelanggan Gagal).
4. Permission baru: `customers.detail.installation.reject`. Sebelumnya **gak ada jalur ini sama sekali** — satu-satunya cara batalin Task Pemasangan yang lagi jalan cuma lewat tombol Cancel di halaman Task, yang gak nyentuh status pelanggan (gap, sekarang ditutup).

## 5. FOP/Admin — Verifikasi Admin (Aktivasi Final)

1. Buka `/verifications/{customer}/admin` — review semua data: device, technical detail, hasil instalasi, paket, alamat.
2. Pilih salah satu aksi:
   - **Approve/Aktivasi** — isi manual: periode billing, tanggal terbit/jatuh tempo, subtotal, discount, PPN, biaya prorate/kabel/tiang/instalasi tambahan → submit. Sistem generate Invoice AWAL, generate CID, aktivasi pelanggan+service.
   - **Tolak** *(tombol ini baru ditambahkan di halaman ini — fix reject-sync gap, 2026-07-14; sebelumnya cuma ada Approve/Revisi di sini, reject cuma bisa dari halaman queue tahap survey)* — isi alasan, modal kasih peringatan tegas **"final, gak bisa dibuka lagi, harus registrasi ulang dari awal"**. Pelanggan masuk list **Pelanggan Gagal**, Task Pemasangan terkait ke-mark `fop_review_status=rejected` (bukan Task Survey — target-nya sekarang sesuai tahap). Tiketnya sendiri tetap `Selesai` di Riwayat FOP (kerjaan lapangan teknisi bener, cuma keputusan bisnis customer-nya yang ditolak) — dapet badge KEDUA "Verifikasi: Ditolak" terpisah dari status utama. *(Lihat §5b — sekarang ada jalur "Kembalikan" buat reverse aksi ini, warning "final" di modal jadi kurang akurat tapi dibiarkan sebagai peringatan default.)*
   - **Revisi** — isi alasan → pelanggan balik ke antrean pemasangan teknisi buat perbaikan, laporan lama gak hilang (catatan revisi ditambahkan).
3. Setelah aktivasi, pelanggan lanjut ke alur normal billing bulanan (lihat [docs/billing-pembayaran](../billing-pembayaran/README.md)).
4. **Sebelum admin mutusin** (approve/tolak/revisi): tiket teknisi TETAP tampil `Selesai` di Riwayat FOP (bukan nangkring di antrian aktif, karena kerjaan lapangan emang udah kelar) — badge kedua "Verifikasi: Menunggu" nunjukin masih nunggu keputusan, dengan link balik ke halaman ini. Lihat `docs/project_verifikasi_reject_gap.md` (§ DESAIN FINAL).

### 5b. FOP/Admin — List Pelanggan Gagal & Kembalikan (baru 2026-07-20)

1. Buka `/customers?status_group=failed` — tabel ringkas: CID, Nama, Alasan, Tanggal Ditolak, Action. **Diurut DESC berdasarkan Tanggal Ditolak** (baru 2026-07-20 — sebelumnya diurut `customer_code`).
2. **Detail** → buka halaman detail pelanggan seperti biasa.
3. **Kembalikan** *(cuma muncul kalau status sebelum ditolak berhasil ditemukan dari audit log)* → konfirmasi → pelanggan balik ke status TEPAT SEBELUM ditolak (mis. ditolak dari `installation_in_progress` → balik ke situ lagi, BUKAN ke `waiting_survey`/awal alur). Teknisi/FOP lanjut kerjain dari titik itu, gak perlu registrasi ulang dari nol. Permission sama dengan Tolak/Approve/Revisi (`customers.detail.installation.validate`). Buat pelanggan hasil migrasi legacy, "status sebelum ditolak" di-default `registered` (data lama gak selalu jelas tahap persisnya).

### 5c. Admin — Aktivasi Manual pelanggan migrasi (baru 2026-07-20)

Khusus pelanggan hasil **import legacy** yang di sistem lama udah aktif (bayar, terpasang), tapi di sistem baru nyangkut belum `active` karena gak pernah lewat alur Survey/Pemasangan di sini:

1. Buka detail Customer pelanggan itu — tombol **"Aktivasi Manual"** muncul di header (bukan di halaman queue manapun).
2. Tombol cuma muncul kalau pelanggan itu: hasil import legacy, TERBUKTI udah `ACTIVE` di sistem lama, dan belum pernah kesentuh Task Survey/Pemasangan di sistem baru. Kalau data wajib (paket internet, POP, dll) belum lengkap, tombol tetap kelihatan tapi disabled.
3. Klik → konfirmasi → sistem generate CID + aktifkan pelanggan+service langsung (gak lewat Invoice awal manual seperti §5, karena ini bukan verifikasi baru — cuma "menyelesaikan" migrasi data lama).
4. **Bukan buat pelanggan yang lagi jalan di SRV/PSB** (baik di sistem baru maupun yang di data lama-nya sendiri masih stuck survey/pemasangan) — pelanggan begitu harus tetap lewat alur normal §2-§5.

## 6. Admin — Terminasi Layanan

1. Dari halaman pelanggan aktif, klik "Hentikan Layanan" → isi alasan (wajib) → submit (`POST /customers/{customer}/terminate`).
2. Status pelanggan langsung `terminated`, service `berhenti`. **Aksi ini gak lewat validasi state machine** — bisa dilakukan dari status manapun, gak tercatat di riwayat transisi resmi (`customer_status_logs`), cuma di audit log biasa.

### 6b. Admin/FOP — List Putus Langganan, Ambil Alat & Langganan Lagi (baru 2026-07-20)

1. Buka `/customers?status_group=terminated` — tabel: ID, Nama, Kontrak (Sewa/Beli), Alasan Putus, Tanggal Pemutusan, **Status Alat** (badge "Sudah di Ambil"/"Belum di Ambil"), Action. **Diurut DESC berdasarkan Tanggal Pemutusan** (baru 2026-07-20 — sebelumnya diurut `customer_code`).
2. **Detail** — selalu ada, buka halaman detail pelanggan.
3. **Ambil Alat** — cuma muncul kalau status alat masih "Belum di Ambil". Klik → konfirmasi → tandai alat pelanggan (`customer_devices.device_retrieved_at`) sudah diambil. Permission `customers.detail.devices.retrieve` *(dipisah dari `customers.update` 2026-07-20 — biar granular, gak numpang di permission edit-data-pelanggan generik; lihat [docs/rbac/business-logic.md § 3.1](../rbac/business-logic.md#31-langkah-nambah-permission-baru-fitur-existing--contoh-nyata-customersdetaildevicesretrieve))*.
4. **Langganan Lagi** — selalu muncul (gak peduli status alat). Klik → konfirmasi → pelanggan **langsung aktif lagi** (`status=active`), TANPA lewat survey/verifikasi ulang (asumsi infrastruktur masih terpasang). Permission `customers.detail.installation.validate`.

## Guard / Permission per Tahap

| Aksi | Permission |
|------|------------|
| Registrasi pelanggan | `customers.create` |
| Mulai/lapor Survey | `customers.detail.survey.update` |
| Lihat antrean Survey | `customers.detail.survey.view` |
| **Batalkan Survey** | `customers.detail.survey.reject` |
| Proses ke Tim, Aktivasi, Reject, Revisi | `customers.detail.installation.validate` |
| Mulai/lapor Pemasangan | `customers.detail.installation.update` |
| **Batalkan Pemasangan** *(baru 2026-07-21)* | `customers.detail.installation.reject` |
| Lihat antrean Verifikasi | `customers.detail.installation.view` |
| Upload/lihat dokumen tambahan | `customers.detail.documents.upload` / `.view` |
| Tambah/update device manual | `customers.detail.devices.create` / `.update` |
| **Aktivasi Manual** (pelanggan migrasi) | `customers.detail.installation.activate` |

## Hal yang Perlu Diperhatikan (Gotcha)

- **Konflik jadwal teknisi** berlaku di 2 tahap (Survey & Pemasangan) — kalau teknisi lagi pegang Task `in_progress` lain, mulai kerjaan baru bakal ditolak. Solusi: selesaikan atau tandai Pending task yang lagi jalan dulu.
- **Foto wajib saat pemasangan `completed`** — 4 foto (pemasangan, kontrak, TTD, speedtest) dicek satu-satu, submit gagal kalau ada yang belum diupload (baik baru maupun yang sudah tersimpan sebelumnya).
- **Cancel Task Survey/Pemasangan cuma bisa dari halaman Customer (2026-07-21)** — tombol Cancel di halaman Task/tabel FOP Task disembunyikan/diblokir buat kategori Survey & PSB, biar `Customer.status` selalu konsisten ikut ke-update pas dibatalkan (masuk List Pelanggan Gagal). Task_type lain (MTN/DEAC/RELOKASI/dst) tetap bisa dibatalkan langsung dari Task seperti biasa.
- **CID baru muncul saat Aktivasi** — sebelum status `active`, pelanggan belum punya CID sama sekali.
- **Terminasi gak lewat state machine** — beda dari semua transisi lain, jadi gak masuk riwayat `customer_status_logs`, cuma di `AuditLog`.
- **Tombol Delete pelanggan dihapus dari `/verifications/queue` (2026-07-20)** — diganti icon "Batal" (modal reject yang sama), berlaku di semua status antrean, bukan cuma `surveyed`. Pelanggan pada dasarnya emang gak boleh dihapus permanen selama masih SRV/PSB — pakai Batal/Tolak, bukan Delete.
- **Migrasi data lama** — status Customer hasil import (`app:import-legacy-sql`) sekarang di-mapping akurat dari data legacy (`PENGAJUAN`→`waiting_survey`, `DISURVEI`→`waiting_installation`, `ACTIVE`→`active`, `GAGAL`→`rejected`, `PUTUS`→`terminated`), termasuk Kontrak (Sewa/Beli) dan alasan+tanggal buat pelanggan `rejected`/`terminated` migrasi. Detail lengkap: `docs/customer-lifecycle/business-logic.md` §9.
