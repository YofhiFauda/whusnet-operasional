# Business Logic — Master Timeline SLA

## 1. Apa yang Diukur

Master Timeline SLA = **batas waktu wajib mulai ditangani** tiket, per jenis tiket, per paket internet. **Bukan** durasi pengerjaan teknisi di lapangan (`tasks.sla_minutes`/`Task::isOverSla()` — beda fitur, tidak disentuh modul ini).

## 2. Anchor (Titik Mulai Hitung) per Jenis Tiket

| Jenis Tiket | Anchor | Alasan |
|---|---|---|
| `SURVEY` | `customer.created_at` | Jam pelanggan daftar — sudah jadi pola existing di `FopDashboardController` (Antrean Survey), dipertahankan. |
| `PEMASANGAN` (`PSB`) | `completed_at` survey terakhir customer (fallback `customer.updated_at` kalau belum ada survey selesai) | Pemasangan baru bisa jalan setelah survey kelar — sudah jadi pola existing (Verif Pemasangan). |
| `MTN`, `DEAC`, `RELOKASI`, `C-REQ`, `O-REQ`, `INFR REQ` | `created_at` tiket (`task_date`) | Tidak ada titik acuan lain yang relevan — tiket jenis ini murni permintaan baru, dihitung sejak diajukan. |

Anchor **tidak** configurable per paket — cuma durasinya yang beda per paket. Kalau tiket sudah punya `Task` asli (sudah ditugaskan/`scheduled_at` terisi), deadline pindah pakai `scheduled_at` + SLA pengerjaan (`TaskType::slaMinutes()`) — ini di luar scope Master Timeline, lihat `FopTask::slaDeadline()`.

## 3. Resolusi Durasi (`InternetPackage::getHandlingSla()`)

1. Cari baris `package_sla_settings` milik paket tsb utk `task_type` yg diminta.
2. Kalau ada dan `is_active=true` → pakai `sla_hours` (accessor, normalisasi `sla_duration`+`sla_unit` ke jam).
3. Kalau tidak ada / nonaktif → fallback ke `TaskType::defaultHandlingSlaHours()` (hardcode global per jenis tiket).

Fallback memastikan **tidak pernah ada tiket tanpa SLA**, bahkan utk paket yang belum diatur admin sama sekali.

## 4. Snapshot, Bukan Live Recalculate

`FopTask::booted()` → `creating` hook resolve dan menyimpan `handling_sla_hours` **sekali**, TAPI cuma kalau kolomnya masih `null` pas creating:

```
if ($fopTask->handling_sla_hours !== null) {
    return; // sudah diisi caller — lihat catatan warisan di bawah
}

$package = $fopTask->customer?->internetPackage;
$fopTask->handling_sla_hours = $package
    ? $package->getHandlingSla($fopTask->category)
    : $fopTask->category->defaultHandlingSlaHours();
```

> **Sejak `2026_08_05` (docs/plan/analisa-target-sla-ticketing.md):** `FopTask` yang lahir dari `TicketService::syncToFopTask()` (eskalasi MTN/C-REQ dari Ticketing) TIDAK lewat resolve di atas — `handling_sla_hours` sudah diisi CALLER dari `Ticket::sla_hours` sebelum `save()`, jadi hook ini `return` lebih awal. Ini WARISAN, bukan hitung ulang: jam SLA yang sama yang di-snapshot saat tiket lahir tetap dipakai, gak reset di titik handoff Ticketing → FOP. Resolve mandiri di atas cuma jalan buat `FopTask` yang dibuat LANGSUNG di `/fop-tasks` (SURVEY, PEMASANGAN, atau MTN/C-REQ manual FOP tanpa tiket) — jalur yang gak pernah lewat `Ticket` sama sekali.

**Kenapa snapshot (bukan live):**
- Kalau customer ganti paket internet setelah tiket dibuat, tiket yang sudah berjalan **tidak** ikut berubah SLA-nya — konsisten dengan desain kolom `tasks.sla_minutes` yang juga snapshot.
- Kalau admin ubah angka di Master Timeline, itu cuma berlaku ke tiket-tiket **baru** setelah perubahan — tidak menimpa tiket lama.
- Tiket tanpa `customer_id` (kalau ada) fallback ke default global.

## 5. Kenapa Tidak Ditambahkan ke `Task` (Tiket Teknisi)

Diputuskan skip. SLA di level teknisi ("SLA pengerjaan", ditampilkan sbg progress/badge saat teknisi kerja) secara eksplisit dinyatakan kurang dibutuhkan sekarang. `Task` tetap pakai `sla_minutes`/`isOverSla()` yang sudah ada, tidak berubah. Ini soal beda dari § 5a di bawah — jangan dicampur.

> **Koreksi per `2026_08_05` — `FopTask` BUKAN LAGI satu-satunya *single source of truth* SLA.** Kalimat itu benar waktu ditulis (Ticketing belum punya SLA sendiri), tapi sudah tidak akurat sejak `Ticket` dapat kolom `sla_hours`/`sla_deadline_at` sendiri (§ 5a). Dipertahankan di sini apa adanya sebagai jejak keputusan lama — TIDAK dihapus/ditulis ulang biar histori keputusan tetap terbaca, tapi jangan dijadikan rujukan status TERKINI.

## 5a. Ticketing Sekarang Punya SLA Sendiri (Susulan, `2026_08_05`)

Waktu dokumen ini ditulis pertama kali, Ticketing (`tickets` table) sengaja TIDAK dikasih SLA — keputusannya "cukup di `FopTask`" (§ 5). Belakangan ketauan itu bikin gap: tiket yang masih di tangan Helpdesk/NOC (`handler` ≠ FOP), atau yang ditutup langsung tanpa pernah ke FOP, sama sekali gak punya SLA terukur — karena `FopTask` belum tentu ada.

Perbaikannya (docs/plan/analisa-target-sla-ticketing.md): `tickets.sla_hours` + `tickets.sla_deadline_at`, snapshot di `TicketService::create()`, pakai anchor & resolusi durasi YANG SAMA persis dengan § 2–3 di atas (MTN/C-REQ anchor `created_at`). Saat eskalasi ke FOP, `handling_sla_hours` FopTask **mewarisi** angka ini (§ 4) — bukan dua sumber independen, satu clock yang pindah tangan. Detail lengkap & alasan kenapa gak digabung jadi satu tabel: `docs/ticketing/business-logic.md` § 16.

Modul Master Timeline SLA ini (halaman matrix, `PackageSlaSetting`, RBAC § 7) TIDAK BERUBAH — `Ticket::resolveSlaHours()` di sisi Ticketing tetap manggil `InternetPackage::getHandlingSla()` yang sama, cuma titik SIMPANnya yang nambah satu (dari `Ticket`, bukan cuma `FopTask`).

## 6. Kenapa 8 Jenis Tiket Semua Masuk, Bukan Cuma Survey/Pemasangan

Meski hanya Survey & Pemasangan yang punya anchor "istimewa" (relatif ke tanggal lain, bukan `created_at` sendiri), pola "batas wajib ditangani per paket" tetap relevan buat 6 jenis lain — customer paket premium (mis. Bisnis Dedicated) tetap wajar dapat jaminan respon lebih cepat utk MTN/C-Req/dst dibanding paket Home Broadband biasa.

## 7. RBAC — Permission Dedicated, Bukan Reuse

Modul ini punya permission sendiri, **bukan** numpang di `packages.*` (Master Paket Internet). Root Feature baru `sla_timeline` (`database/seeders/SlaTimelineFeatureSeeder.php`), dua permission digenerate otomatis lewat `config/rbac.php` → `PermissionGeneratorService`:

- `sla_timeline.view` — akses halaman matrix (read-only kalau tidak punya `.update`).
- `sla_timeline.update` — simpan perubahan angka SLA.

Assignment per role (`RolePermissionSeeder.php`), dibuat cermin dari role yang sebelumnya punya `packages.view`:

| Role | Akses |
|---|---|
| `owner` | Semua (`*`, otomatis) |
| `admin` | `sla_timeline.*` (view + update) |
| `atasan`, `noc`, `helpdesk`, `pop_admin` | `sla_timeline.view` saja |
| `fop`, `teknisi`, `sales` | Tidak ada |

**Kenapa dedicated, bukan reuse `packages.*`:** biar akses Master Timeline SLA bisa diatur independen dari akses Master Paket Internet — role yang boleh lihat/edit paket belum tentu harus boleh ubah kebijakan SLA, dan sebaliknya. Sempat dibuat reuse `packages.*` di iterasi pertama, direvisi jadi permission sendiri.

## 8. Yang Sengaja Belum Dibuat

- **Notifikasi/eskalasi otomatis** begitu lewat Master Timeline — di luar scope. Saat ini cuma jadi input `slaDeadline()`/`slaTotalSeconds()` yang sudah dipakai indikator visual existing (progress bar/badge) di FOP Dashboard. Berlaku juga buat SLA sisi `Ticket` (§ 5a) — badge-nya pasif, gak ada notif/eskalasi otomatis di situ juga.
- **Resolve via `customer_services`** — tidak dipakai. Sumber paket customer langsung dari `customer.internetPackage` (`customers.internet_package_id`), sesuai relasi yang sudah jadi *source of truth* di codebase.
- **Auto-naikin `priority` tiket berdasar sisa SLA** — `FopTaskController::autoSyncAndCalculatePriority()` cuma jalan buat SURVEY/PEMASANGAN di sisi `FopTask`. Belum ada versi sisi `Ticket` (MTN/C-REQ yang masih di Helpdesk/NOC) — `priority` tiket statis dari input awal sampai dieskalasi.
