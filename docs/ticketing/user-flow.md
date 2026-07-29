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
| **Assign NOC** | Sudah dikirim ke NOC — badge menunjukkan **Pending NOC** atau **OnCheck NOC** | Selesai, Ke FOP, Kembalikan (selama masih Pending) |
| **Assign FOP** | Sudah dikirim ke FOP — pantau status Task FOP-nya | — (read-only, kendali ada di modul FOP) |

Panel auto-refresh lewat Reverb saat ada perubahan dari user lain. Tombol **Refresh** manual jadi cadangan kalau Reverb tidak aktif. Kalau tiket aktif lebih dari 30, muncul indikator "+N tiket aktif lainnya".

### Skenario A — Helpdesk selesaikan sendiri

1. Di tab **Ticket**, klik **Selesai** pada baris tiket.
2. Dialog konfirmasi muncul dengan kolom **"Apa yang sudah dikerjakan?"** (opsional) — isi kalau mau tercatat di riwayat.
3. Klik **Ya, Selesaikan** → baris hilang dari panel, tiket pindah ke halaman **Ticket Selesai**.

### Skenario B — Helpdesk kirim ke NOC

1. Klik **Ke NOC** → isi catatan buat NOC (opsional) → konfirmasi.
2. Tiket pindah ke tab **Assign NOC**, statusnya **Pending NOC**.
3. **Helpdesk masih bisa bertindak** di jendela ini — Selesai, Ke FOP, atau Batalkan tetap bisa selama NOC belum klik Oncheck.

### Skenario C — Helpdesk kirim langsung ke FOP

1. Klik **Ke FOP** → isi catatan (opsional) → konfirmasi.
2. Task FOP baru (`TFOP-…`) otomatis dibuat berstatus Draft, tanpa teknisi.
3. Tiket pindah ke tab **Assign FOP**. Sejak titik ini **semua aksi Ticketing tertutup** — kendali sepenuhnya di modul FOP.

---

## 3. NOC — Worksheet NOC

Sidebar **Ticketing** → **Worksheet NOC** (`/noc/worksheet`). Satu halaman, dua tab.

### Tab "Ticket Masuk" — tiket Pending NOC

Tiket yang dikirim Helpdesk tapi **belum** di-Oncheck. Tombol yang tersedia:

| Tombol | Efek |
|---|---|
| **Oncheck NOC** | NOC resmi ambil alih. Tiket pindah ke tab Ticket Diproses; Helpdesk kehilangan akses |
| **Assign FOP** | Lempar ke FOP tanpa perlu Oncheck dulu (mis. jelas butuh teknisi lapangan) |
| **Kembalikan** | Balikin ke Helpdesk (salah kirim/bukan ranah NOC) |
| **Batalkan** | Batalkan tiket (alasan **wajib**) |

**Tombol "Selesai" sengaja TIDAK ada di tab ini** — NOC wajib Oncheck dulu sebelum boleh menyelesaikan. Kalau dipaksa lewat request manual, server menolak dengan pesan *"NOC wajib Oncheck dulu sebelum bisa Selesaikan tiket ini."*

### Tab "Ticket Diproses" — tiket yang sudah di-Oncheck

Tombol berubah jadi: **Selesai**, **Assign FOP**, **Kembalikan**, **Batalkan**.

Alur NOC lengkap:

1. Buka tab **Ticket Masuk** → klik **Oncheck NOC** pada tiket yang mau dikerjakan.
2. Kerjakan perbaikan (konfigurasi/routing/dll).
3. Buka tab **Ticket Diproses** → klik **Selesai** → isi **"Apa yang sudah dikerjakan?"** → konfirmasi.
4. Kalau ternyata butuh lapangan: klik **Assign FOP** → Task FOP dibuat, tiket keluar dari worksheet NOC.

> Tab yang tidak dimiliki izinnya tidak ditampilkan sama sekali. Kalau user cuma punya akses satu tab, membuka `/noc/worksheet` otomatis mengarahkan ke tab itu.

---

## 4. NOC — Dashboard NOC

Sidebar **Ticketing** → **Dashboard NOC** (`/noc/dashboard`). Isinya:

| Bagian | Isi |
|---|---|
| **Stat counter** | Pending NOC, OnCheck NOC, Selesai hari ini, Dibatalkan hari ini |
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

- Header: nomor tiket, tipe, status (mis. *Pending NOC*, *OnCheck NOC*, *Selesai (NOC)*), prioritas, nama pelanggan, POP, **Assigned by**, **Created**.
- Panel **Aksi Tiket** — tombol yang muncul mengikuti state & role (lihat flowchart.md § 7). Semua tombol membuka dialog konfirmasi + kolom alasan.
- Info box link ke Task FOP terkait (kalau ada).
- Panel snapshot data pelanggan (kondisi saat tiket dibuat).
- Detail Keluhan & Catatan Teknis versi utuh.
- Dua kolom riwayat berdampingan: **Riwayat Ticketing** & **Riwayat Task FOP**.
- Lampiran — tombol Unduh per file (disk privat, dicek permission + POP scope).

**Dari sisi Task FOP** (`/fop-tasks/history/{id}`, khusus MTN/C-REQ yang nyambung ke tiket):

- Section "Detail Ticket": CID, data pelanggan, keluhan, catatan teknis, lampiran, Assigned by/Created.
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

**Last updated:** 2026-07-28
