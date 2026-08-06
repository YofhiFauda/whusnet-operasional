# User Flow — Modul Ticketing

Lima halaman, tiga aktor. Peta cepat:

| Halaman | Route | Siapa yang kerja di sini |
|---|---|---|
| New Ticket | `/tickets/new` | Helpdesk & NOC (worksheet bersama) |
| Worksheet NOC | `/noc/worksheet` | NOC |
| Dashboard NOC | `/noc/dashboard` | NOC (+ atasan, monitoring) |
| Ticket Selesai | `/tickets/selesai` | semua pemegang `tickets.*` |
| Ticket Dibatalkan | `/tickets/dibatalkan` | semua pemegang `tickets.*` |

---

## 1. Helpdesk/NOC — Submit Tiket Baru

1. Sidebar **Ticketing** → **New Ticket** (`/tickets/new`).
2. Pilih **Tipe Ticket** (MTN atau C-REQ) dan **Prioritas**.
3. Pilih **Kategori Issue** dari Master Issue (opsional — boleh "Lainnya (isi manual)"). Prioritas otomatis terisi dari `default_priority` kategori itu.
4. Ketik CID atau nama pelanggan di kolom **CID / Pelanggan** → hasil pencarian muncul (debounce 300ms, lewat `/api/tickets/lookup-customer`).
5. Klik salah satu hasil → panel **Data Pelanggan** otomatis terisi: Nama, Alamat, No. HP, POP/Cabang, ODP, Paket, Perangkat, Koordinat (link Google Maps kalau ada).
   - Kalau pelanggan itu masih punya tiket aktif, muncul **peringatan kuning duplikat** (dicek server-side lewat `/api/tickets/duplicates`, jadi tiket lama yang sudah tergeser dari cap 30 panel tetap terdeteksi).
6. Isi **Detail Keluhan** (wajib) dan **Catatan Teknis** (opsional).
7. Opsional: lampirkan bukti foto/PDF (maks 5 file, 5 MB/file).
8. Klik **CREATE TICKET** (atau `Ctrl+Enter`) → **tetap di halaman ini**, form auto-reset, tiket langsung muncul di panel kanan.
9. Tiket masuk ke tab **Ticket** di panel List Task Ticketing — **belum ada Task FOP**, masih di tangan Helpdesk.

> **Beda dari perilaku lama:** dulu setiap submit langsung membuat Task FOP. Sekarang tidak — Task FOP baru dibuat kalau tiketnya benar-benar dikirim ke FOP.

## 2. Panel "List Task Ticketing" — 3 Tab

Panel di kanan halaman New Ticket, memantau tiket yang sedang berjalan. Filternya per **siapa yang lagi pegang**, bukan per tahap pengerjaan:

| Tab | Isi | Tombol aksi yang muncul |
|---|---|---|
| **Ticket** | Tiket yang masih di tangan pembuat, belum dikirim ke mana pun | Selesai, Ke NOC, Ke FOP |
| **Assign NOC** | Sudah dikirim ke NOC — badge **Diproses NOC** | Selesai, Ke FOP, Kembalikan, Batalkan |
| **Assign FOP** | Sudah dikirim ke FOP — pantau status Task FOP-nya | — (read-only, kendali ada di modul FOP) |

Panel auto-refresh lewat Reverb saat ada perubahan dari user lain. Tombol **Refresh** manual jadi cadangan kalau Reverb tidak aktif. Kalau tiket aktif lebih dari 30, muncul indikator "+N tiket aktif lainnya".

**Detail tiket = drawer kanan** (ADHOC-10). Klik nomor tiket (mode tabel) atau kartunya (mode kartu) → drawer kanan terbuka berisi Status & atribusi, Aksi Ticket, Snapshot Pelanggan, Keluhan & Catatan Teknis, Lampiran, dan Riwayat Ticket & Audit. Halaman kerja **tidak** melempar user ke `/tickets/{id}` — keluar halaman berarti kehilangan form yang sedang diisi, tab, dan posisi scroll. Navigasi halaman penuh cuma dipakai halaman arsip (**Ticket Selesai**, **Ticket Dibatalkan**, **History Ticketing**).

Tombol **Batalkan** sengaja **hanya ada di drawer**, bukan di baris tabel/kartu — aksi destruktif jangan sampai kepencet sambil scroll.

Tiap baris nampilin badge **Target SLA** (mis. "Sisa 3j 12m" / "TERLAMBAT 1j 05m") kalau tiketnya punya snapshot SLA — lihat business-logic.md § 16. Badge ini statis (ngikut refresh/broadcast), bukan countdown detik-per-detik.

### Skenario A — Helpdesk selesaikan sendiri

1. Di tab **Ticket**, klik **Selesai** pada baris tiket.
2. Dialog konfirmasi muncul dengan kolom **"Apa yang sudah dikerjakan?"** (opsional) — isi kalau mau tercatat di riwayat.
3. Klik **Ya, Selesaikan** → baris hilang dari panel, tiket pindah ke halaman **Ticket Selesai**.

### Skenario B — Helpdesk kirim ke NOC

1. Klik **Ke NOC** → isi catatan buat NOC (opsional) → konfirmasi.
2. Tiket pindah ke tab **Assign NOC**, statusnya langsung **Diproses NOC**.
3. **Helpdesk tetap bisa bertindak** — Selesai, Ke FOP, atau Batalkan tetap tersedia sampai tiket ditutup atau dikirim ke FOP.

### Skenario C — Helpdesk kirim langsung ke FOP

1. Klik **Ke FOP** → isi catatan (opsional) → konfirmasi.
2. Task FOP baru (`TFOP-…`) otomatis dibuat berstatus Draft, tanpa teknisi.
3. Tiket pindah ke tab **Assign FOP**. Sejak titik ini **semua aksi Ticketing tertutup** — kendali sepenuhnya di modul FOP.

### Sort Kolom & Navigasi Keyboard (mode tabel & kartu)

Klik header **Ticket ID & Time**, **Status / Issue**, atau **Lokasi / POP / ODP** buat sort ASC/DESC (klik lagi = toggle arah, klik header lain = pindah kolom). Kolom **Pelanggan** sengaja gak sortable.

Navigasi tanpa mouse (nonaktif kalau lagi ngetik di field manapun, atau drawer detail kebuka):

| Tombol | Aksi |
|---|---|
| `↑` / `↓` | Pindah baris yang "fokus" (ring biru di baris) |
| `←` / `→` | Pindah tab Ticket / Assign NOC / Assign FOP — fokus reset ke baris pertama |
| `Enter` | Buka drawer detail baris yang fokus |
| `C` / `V` / `B` | Selesai / Ke NOC / Ke FOP baris yang fokus — dialog konfirmasi (Skenario A/B/C di atas) **tetap muncul**, cuma langkah pilih tombolnya yang dipercepat |
| `N` | Buka/lipat panel form New Ticket |

Detail lengkap: business-logic.md § 17.

---

## 3. NOC — Worksheet NOC

Sidebar **Ticketing** → **Worksheet NOC** (`/noc/worksheet`). Satu halaman berisi **tabel padat** (satu baris = satu tiket) dengan dua tab bercounter, pencarian, dan filter.

### Tab

| Tab | Isi | Bisa diaksi? |
|---|---|---|
| **Tiket Masuk** (default, `?tab=masuk`) | Tiket yang diassign ke NOC oleh Helpdesk — `handler=noc`, `status=open` | Ya |
| **Assign FOP** (`?tab=assign_fop`) | Tiket yang sudah NOC teruskan ke FOP (`handler=fop` + jejak eskalasi lewat NOC) | **Tidak** — tindak lanjut di `/fop-tasks` |

### Kolom & filter

Kolom: Masuk · Tiket · Nama/CID · HP · Desa · POP · Aduan · Kategori · Prioritas, ditutup **Umur** (lama menunggu di meja NOC; ≥8 jam kuning, ≥24 jam merah) di tab Tiket Masuk, atau **Status / Diserahkan / Dikirim Oleh** di tab Assign FOP.

Filter (GET, ikut kebawa di URL & paginasi): **Cari** (nomor tiket, nama, CID, desa, keluhan), rentang tanggal, POP, Kategori Issue, Prioritas, Tipe Tiket, Dikirim Oleh. Angka di badge tab ikut filter yang aktif.

### Aksi — klik baris

Klik baris mana pun (atau nomor tiketnya) → **drawer kanan** berisi detail lengkap: Status & atribusi, Aksi Ticket, Snapshot Pelanggan, Keluhan & Catatan Teknis, Lampiran, Riwayat Ticket & Audit. Drawer-nya partial yang sama dengan Worksheet Helpdesk (`tickets/partials/detail-drawer.blade.php`), isinya di-fetch dari `tickets.detail-json`. Baris tabel **tidak** menaut ke `/tickets/{id}` — halaman penuh cuma buat halaman arsip. Tombolnya:

| Tombol | Efek |
|---|---|
| **Selesai** | Tutup tiket, isi "Apa yang sudah dikerjakan?" (opsional) |
| **Assign FOP** | Lempar ke FOP (mis. jelas butuh teknisi lapangan) — Task FOP dibuat, tiket keluar dari tab Tiket Masuk |
| **Kembalikan** | Balikin ke Helpdesk (salah kirim/bukan ranah NOC) |
| **Batalkan** | Batalkan tiket (alasan **wajib**) |

Nomor tiket di kolom **Tiket** membuka drawer yang sama, bukan halaman baru.

Alur NOC lengkap:

1. Buka **Worksheet NOC** — tiket yang diassign Helpdesk sudah langsung ada di tab **Tiket Masuk** (gak ada langkah "terima").
2. Cari/filter tiket yang mau dikerjakan, klik barisnya untuk baca detail.
3. Kerjakan perbaikan (konfigurasi/routing/dll) → **Selesai** → isi apa yang dikerjakan → konfirmasi.
4. Kalau ternyata butuh lapangan: **Assign FOP** → Task FOP dibuat, tiket pindah ke tab **Assign FOP** (read-only).

> **Berubah (ADHOC-09, 2026-07-30):** daftar kartu jadi tabel padat + cari + filter + dua tab. **Ini bukan pengembalian window Pending NOC** yang dihapus ADHOC-06 — tab **Assign FOP** murni turunan data (tiket yang sudah lepas ke FOP), gak ada aksi "Oncheck"/"ambil tiket", dan tiket yang diassign ke NOC tetap langsung berstatus diproses. Satu permission (`noc_worksheet.view`) menggerbangi kedua tab.

---

## 4. NOC — Dashboard NOC

Sidebar **Ticketing** → **Dashboard NOC** (`/noc/dashboard`). Isinya:

| Bagian | Isi |
|---|---|
| **Stat counter** | Diproses NOC, Selesai hari ini, Dibatalkan hari ini |
| **Tiket Aktif NOC** | Tiket `handler=NOC` yang masih berjalan, diurut **paling lama menunggu di atas** + indikator umur — untuk melihat mana yang keteteran |
| **Aktivitas Terbaru** | 20 kejadian terakhir dari `ticket_histories` (siapa mengerjakan apa) |
| **Statistik per Issue** | 10 kategori keluhan terbanyak |
| **Statistik per Daerah** | 10 kecamatan dengan komplain terbanyak |

Semua di-scope POP user. Auto-refresh lewat Reverb; ada tombol **Refresh** manual.

---

## 5. Membatalkan Tiket

Dua pintu, tergantung tiket sudah sampai FOP atau belum.

### Pra-FOP (masih di Helpdesk/NOC)

1. Klik **Batalkan** dari panel worksheet, Worksheet NOC, atau halaman detail tiket.
2. Isi **Alasan pembatalan** — **wajib**, dialog menolak submit kalau kosong.
3. Konfirmasi → tiket pindah ke halaman **Ticket Dibatalkan**, satu baris riwayat tercatat.

Butuh permission `tickets.cancel` dan harus jadi pemegang tiket saat itu.

### Pasca-FOP (sudah `handler=FOP`)

1. Pembatalan **hanya dari** halaman Task FOP (`/fop-tasks`) — tombol Batalkan di sisi Ticketing sudah tidak muncul, dan endpoint-nya menolak.
2. Butuh permission `fop_tasks.cancel` (owner/admin/fop).
3. Isi alasan (wajib) → konfirmasi.
4. `FopTask.status` jadi `dibatalkan`, `Task` eksekusi ikut dibatalkan kalau ada, dan **dua riwayat** ditulis sekaligus (Histori Status Task FOP + Riwayat Ticketing).

---

## 6. Melihat Detail Tiket

**Dari sisi Ticketing** (`/tickets/{id}`):

- Header: nomor tiket, tipe, **Kategori Issue**, status (mis. *Diproses NOC*, *Selesai (NOC)*), prioritas, nama pelanggan, POP, **Assigned by**, **Created**.
- **Target SLA** — countdown live (jam:menit:detik) selama tiket masih jalan & belum di FOP; badge statis on-time/lewat-SLA begitu tiket resolved atau sudah diserahkan ke FOP (business-logic.md § 16).
- Panel **Aksi Tiket** — tombol yang muncul mengikuti state & role (lihat flowchart.md § 7). Semua tombol membuka dialog konfirmasi + kolom alasan.
- Info box link ke Task FOP terkait (kalau ada).
- Panel snapshot data pelanggan (kondisi saat tiket dibuat).
- Detail Keluhan & Catatan Teknis versi utuh.
- Dua kolom riwayat berdampingan: **Riwayat Ticketing** & **Riwayat Task FOP**.
- Lampiran — tombol Unduh per file (disk privat, dicek permission + POP scope).

**Dari sisi Task FOP** (`/fop-tasks/history/{id}`, khusus MTN/C-REQ yang nyambung ke tiket):

- Section "Detail Ticket": CID, **Kategori Issue**, data pelanggan, keluhan, catatan teknis, lampiran, Assigned by/Created.
- Section "Riwayat Ticketing" berdampingan dengan "Histori Status".
- Link "Buka di Ticketing →" balik ke `/tickets/{id}`.
- Kategori lain (SURVEY, PSB, O-REQ, dll) atau MTN/C-REQ yang dibuat manual **tidak** menampilkan section ini.

---

## 7. Filter & Pencarian (Halaman Arsip)

Di `/tickets/selesai` dan `/tickets/dibatalkan`:

- **Cari**: nomor tiket, CID, nama pelanggan, atau isi keluhan.
- **Filter Tipe**: MTN atau C-REQ.
- **Ticket Saya**: hanya tiket yang dikirim user login sendiri.
- Tab navigasi antar dua halaman arsip di atas list (hanya yang user punya izinnya).

---

## 8. FOP — Bikin Tiket MTN/C-REQ Langsung dari Task FOP

1. Buka `/fop-tasks` → klik **Tambah Task FOP**.
2. Pilih **Tipe Task** = MTN atau C-REQ → form berubah ke mode Ticketing.
3. Cari & pilih pelanggan lewat CID — panel data pelanggan terisi otomatis.
4. Isi Detail Keluhan, Catatan Teknis, Lampiran (opsional).
5. Pilih **Tanggal & Waktu** dan **Prioritas**.
6. **Pilih Teknisi** — opsional:
   - **Dikosongkan** → Task FOP dibuat sebagai Draft.
   - **Diisi** → Task FOP langsung Terjadwal, `Task` eksekusi langsung dibuat.
7. Simpan → redirect balik ke `/fop-tasks`.

Tiket dari jalur ini langsung `handler=FOP` — tidak pernah mampir ke antrean Helpdesk/NOC.

---

**Last updated:** 2026-08-05
