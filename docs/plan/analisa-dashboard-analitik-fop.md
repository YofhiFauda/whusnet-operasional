# BELUM DI IMPLEMENTASIKAN

# Analisa Dashboard Analitik FOP — Alat Kerja, Wilayah, Performa Teknisi

Status: **Analisa awal + mockup, belum eksekusi.** Belum masuk sprint aktif — perlu entry task baru di `docs/TASKS.md` sebelum dikerjakan.

Mockup visual (Artifact, data contoh — bukan hasil query): https://claude.ai/code/artifact/41cb4da2-4046-472d-b6b3-db63351e7e02

## Konteks

Owner/FOP minta dashboard analitik terpisah dari `/fop` (landing operasional harian, lihat [fop-dashboard.md](../fop-task/fop-dashboard.md)) — fokusnya bukan "apa yang lagi jalan hari ini", tapi pola & performa lintas periode:

1. Alat kerja apa yang paling sering dipakai teknisi, breakdown harian/mingguan/bulanan/tahunan.
2. Wilayah mana yang pemasangan tinggi, komplain tinggi, task gagal tinggi.
3. Teknisi mana yang beban tugasnya paling banyak vs paling banyak solving.
4. Task yang masih belum dikerjakan (backlog).
5. Task yang paling lama dikerjakan/solving (durasi anomali).

Bentuk: halaman baru (card KPI + chart statistik), bukan section tambahan di `/fop` — beda audiens & beda ritme baca (`/fop` dibuka tiap shift, dashboard ini dibuka mingguan/bulanan buat evaluasi).

## Sumber data per section

Semua query **wajib** lewat `EffectiveAccessService::getAllowedPopIds()` (bukan `$user->pops()` — lihat CLAUDE.md § POP Scope), sama seperti `FopDashboardController`.

### 1. Alat kerja

| Metric | Sumber | Catatan |
|---|---|---|
| Ranking alat terpakai | `task_work_tools` join `fop_tasks` (untuk `pop_id`/tanggal) | Agregasi `GROUP BY tool_name` (bukan `work_tool_id` — FK boleh null kalau master dihapus, `tool_name` snapshot yang aman dipakai historis, lihat migrasi `2026_08_01_000004`) |
| Tren pemakaian per periode | sama, `GROUP BY DATE(created_at)` / `WEEK`/`MONTH`/`YEAR` sesuai filter | Granularitas ikut toggle Harian/Mingguan/Bulanan/Tahunan di UI |
| Jumlah jenis alat aktif dipakai | `COUNT(DISTINCT tool_name)` vs `COUNT(*) FROM work_tools WHERE is_active` | KPI card "N/M jenis" |

`task_work_tools` **tidak** anchor ke task type tertentu — SURVEY/PSB/MTN/C-REQ semua bisa punya baris alat (lihat migrasi `2026_08_01_000004`, alasan anchor ke `fop_task_id`). Query ranking tidak boleh diam-diam filter `task_type` kecuali memang diminta breakdown per tipe pekerjaan.

### 2. Wilayah — pemasangan, komplain, task gagal

| Metric | Sumber | Catatan |
|---|---|---|
| Pemasangan per kecamatan | `tasks` where `task_type = PSB` and `status = selesai`, join `customer_addresses` (`district`/`district_id`) via `customer_id` | Pakai `district_id` (relasi ke `districts`) kalau tersedia, fallback ke kolom teks `district` untuk data lama yang belum ter-normalisasi (lihat migrasi `2026_06_16_170000_fill_missing_customer_regions`) |
| Komplain per kecamatan | `tasks` where `task_type = MTN`, join sama | **Proksi** — bukan tabel komplain terpisah, disepakati bareng user |
| Task gagal per kecamatan | `tasks` where `status = dibatalkan`, join sama | **Proksi** — belum ada status "gagal" di `TaskStatus` enum (draft/terjadwal/in_progress/selesai/dibatalkan/pending), disepakati pakai `dibatalkan` |

Belum diputuskan: agregasi per `district` (kecamatan) atau `village` (desa) — mockup pakai kecamatan karena volume tiap desa kemungkinan terlalu kecil buat ranking yang bermakna. Konfirmasi ke stakeholder saat masuk sprint.

### 3. Teknisi — beban tugas & solving

| Metric | Sumber | Catatan |
|---|---|---|
| Beban tugas terbanyak | `tasks` group by teknisi (kolom penugasan — technician pivot/`task_technicians`, cek model `Task` relasi tim), periode filter by `scheduled_at`/`created_at` | Perlu konfirmasi kolom pasti penugasan di `Task` (single teknisi vs tim) sebelum implementasi — task bisa multi-teknisi lewat `FopTaskTeam` |
| Solving terbanyak | `tasks.completed_by` group by user, `status = selesai` | Kolom `completed_by` baru ditambah (commit `f6a2f77`, tracking completed_by pada task) — pastikan backfill data lama cukup sebelum dipakai statistik jangka panjang, kalau tidak angka historis bakal timpang |

### 4. Task belum dikerjakan (backlog)

| Metric | Sumber | Catatan |
|---|---|---|
| List + umur antrean | `tasks` where `status IN (draft, terjadwal, pending)`, urut `created_at` | Umur = `now() - created_at` |
| Badge SLA lewat/mendekati | bandingkan umur vs `FopTask::slaDeadline()` / `handling_sla_hours` | **Reuse** logic yang sama persis dengan badge SLA breach di `/fop` (CLAUDE.md/[fop-dashboard.md](../fop-task/fop-dashboard.md) § Alert SLA breach) — jangan bikin logic kedua yang bisa menyimpang |
| Catatan status `pending` | pisahkan `report_deferred=true` ("Lapor Nanti", tim tetap nempel) dari `pending` biasa (tim dilepas, balik antrian) | `TaskStatus::displayLabel()` |

### 5. Durasi pengerjaan terlama

| Metric | Sumber | Catatan |
|---|---|---|
| Top N task terlama | `completed_at - started_at` (atau `- scheduled_at` kalau `started_at` null), `status = selesai`, urut durasi desc | Beda konsep dari SLA Pengerjaan (target durasi per `TaskType::slaMinutes()`) — panel ini ranking durasi aktual, bukan pengganti `TaskReport` |
| Baseline rata-rata | rata-rata durasi solving periode yang sama, buat pembanding visual | Reuse pola hitung SLA yang sudah ada di `TaskService`/`TaskReport`, jangan reimplement dari nol |

## Filter & UI

- Toggle periode: Harian / Mingguan / Bulanan / Tahunan — mengubah granularitas agregasi tren & rentang default (bukan cuma label).
- Filter POP: dropdown, ikut `EffectiveAccessService`.
- Layout: KPI strip (card) di atas, lalu grid chart (bar ranking alat, line tren alat, tabel ranked wilayah, dua leaderboard teknisi, tabel backlog, bar ranking durasi terlama).

## Open questions (perlu diputuskan sebelum masuk sprint)

1. **Definisi "task gagal"** — disepakati sementara pakai `status = dibatalkan`. Kalau butuh granularitas lebih (gagal teknis vs batal permintaan pelanggan), perlu reason code terstruktur dari `cancel_reason` (saat ini bebas teks).
2. **Definisi "komplain"** — disepakati pakai `task_type = MTN`. Tidak ada tabel komplain terpisah.
3. **Kolom penugasan teknisi untuk "beban tugas"** — perlu konfirmasi apakah dihitung per task (bisa dobel-hitung kalau tim >1 orang) atau per assignment individual.
4. **Granularitas wilayah** — kecamatan (`district`) vs desa (`village`) untuk ranking pemasangan/komplain/gagal.
5. **Kualitas data `completed_by`** — kolom baru, cek cakupan backfill data lama sebelum dipakai untuk leaderboard "solving terbanyak" jangka panjang (tahunan).

## Related

- [fop-dashboard.md](../fop-task/fop-dashboard.md) — dashboard operasional `/fop` (harian), beda audiens dari dashboard analitik ini
- [database-schema.md](../fop-task/database-schema.md) — skema `fop_tasks`, `tasks`, `task_work_tools`
- CLAUDE.md § POP Scope, § SLA — aturan yang wajib diikuti query baru
