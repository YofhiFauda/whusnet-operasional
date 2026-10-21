# Rancangan Perbaikan — Hasil Review Implementasi Hierarki Infrastruktur

Tanggal: 21 Oktober 2026
Sumber: `/code-review high` atas branch `feat/hierarki-infrastruktur` (commit `531653b^..HEAD`, ADHOC-202) terhadap
`docs/plan/infrastruktur/rancangan-hierarki-infrastruktur-server-pop-mini-pop.md` (§16 mengikat) dan
`docs/plan/infrastruktur/audit-transisi-hierarki-infrastruktur.md`.
Status: rancangan perbaikan — belum dikerjakan. Temuan belum diverifikasi ulang dengan tes; setiap perbaikan wajib
diawali tes merah yang mereproduksi temuan.

## Ringkasan prioritas

| # | Prioritas | Area | Inti masalah |
|---|---|---|---|
| 1 | Kritis | Bukti cutover §16.4 #6 | "Selisih nol satu siklus" bisa dipenuhi dengan sekali run; periode bebas; saklar legacy tidak terkait bukti |
| 2 | Kritis | Migration 000005 | Diedit setelah berstatus Ran; nama FK lama > 64 karakter MySQL → status FK di DB nyata tidak pasti |
| 3 | Tinggi | Rollback backfill | Rollback menghapus keputusan operator (mapped/rejected + alasan) |
| 4 | Tinggi | Dua sumber slot | Jalur tulis lama (`olt_slots`, kartu) tidak tersinkron ke `infrastructure_slots` setelah migrasi |
| 5 | Tinggi | Reset dummy | Cek "riwayat audit node" selalu menolak — setiap node punya audit `created` |
| 6 | Tinggi | Reset dummy | `orWhereIn('card_node_id')` menghapus slot milik chassis lain (data nyata) |
| 7 | Sedang | Keputusan operator | `mapOdpUplink` tidak dicocokkan dengan PON yang sudah dipetakan dari rantai lama ODP |
| 8 | Sedang | Keputusan operator | `mapAggregationSwitch` tidak memperbarui `pops.uplink_node_id` → uplink Mini POP menunjuk dirinya sendiri |
| 9 | Sedang | Keputusan operator | `mapPonNode` tidak membawa kapasitas & ambang Red Zone node PON lama |
| 10 | Rendah | Performa | `assetsAt()` memuat semua serial DEPLOYED lalu resolve satu per satu tiap ganti dropdown |

## Rincian dan rencana perbaikan

### 1. Bukti cutover #6 tidak bisa dibuktikan (Kritis)

- **Lokasi:** `app/Services/InfrastructureReconciliationService.php` (`run()`, `zeroForPeriod()`), `config/infrastructure.php`.
- **Masalah:** `run()` memotret keadaan saat ini dengan label `period` bebas; `zeroForPeriod()` hanya membaca `run_key`
  terakhir. Dokumen audit §7 mencatat bukti untuk 2026-10 yang belum ditutup. `INFRA_LEGACY_WRITES` bisa di-set
  `false` tanpa bukti apa pun.
- **Skenario gagal:** selisih ≠ 0 di pertengahan bulan, data dirapikan, lalu `infrastructure:reconcile --period=2026-10`
  sekali → periode dianggap bersih.
- **Perbaikan:**
  1. `period` hanya boleh periode yang sudah ditutup (`PeriodClosing`/`BookPeriod::isLocked()`), default periode
     terakhir yang tertutup; tolak periode berjalan/masa depan.
  2. Bukti siklus = run terjadwal pada awal periode berikutnya DAN tidak ada run berselisih ≠ 0 di dalam rentang
     periode itu (semua run periode tersebut dinilai, bukan hanya terakhir). Simpan `trigger` (`schedule`/`manual`).
  3. Saklar legacy ditutup lewat perintah `infrastructure:close-legacy-writes` yang memeriksa ketujuh bukti
     (minimal bukti #6 terpenuhi + tidak ada mapping `ambiguous` tersisa); env hanya fallback darurat yang diaudit.
  4. Koreksi dokumen audit §7: bukti 2026-10 = snapshot sementara, bukan bukti siklus.
- **Tes:** periode berjalan ditolak; satu run nol setelah run ≠ 0 pada periode sama → bukan bukti; penutupan saklar
  ditolak tanpa bukti.

### 2. Migration 000005 diedit setelah Ran (Kritis)

- **Lokasi:** `database/migrations/2026_10_21_000005_add_exact_work_locations_to_infrastructure_actions.php`.
- **Masalah:** nama FK default lama `task_infrastructure_actions_end_from_infrastructure_port_id_foreign` (67 karakter)
  melewati batas MySQL; migration lalu diedit. Status FK di DB aplikasi tidak diketahui; rollback bisa gagal di
  `dropForeign('tia_end_from_port_foreign')`.
- **Perbaikan:**
  1. Cek aktual: `SHOW CREATE TABLE task_infrastructure_actions` di DB aplikasi.
  2. Migration susulan baru (bukan edit lama) yang idempoten: buat FK `tia_end_from_port_foreign`/`tia_end_to_port_foreign`
     bila belum ada; `down()` hanya drop bila ada.
  3. Kembalikan migration 000005 ke bentuk yang benar-benar dijalankan, atau dokumentasikan bentuk final dengan catatan.
- **Tes:** migrate fresh di sqlite + verifikasi skrip pengecekan FK pada MySQL (manual, dicatat di audit).

### 3. Rollback backfill menghapus keputusan operator (Tinggi)

- **Lokasi:** `app/Services/InfrastructureLegacyBackfillService.php` (`rollback()`), migration 000002 `down()`.
- **Masalah:** `InfrastructureLegacyMapping::query()->delete()` menghapus semua mapping termasuk `mapped`/`rejected`
  beserta `decision`/`decided_by`; drop tabel juga menghapus koneksi hasil `mapOdpUplink`.
- **Perbaikan:** rollback menolak bila ada mapping yang sudah diputuskan operator atau koneksi/port hasil keputusan;
  hanya baris backfill otomatis (`status=mapped` dengan `evidence.created` dan tanpa `decided_by`, serta `ambiguous`
  tanpa keputusan) yang boleh dihapus. Ekspor keputusan ke audit log sebelum rollback apa pun.
- **Tes:** keputusan operator ada → rollback ditolak; tanpa keputusan → rollback berhasil.

### 4. Jalur tulis lama tidak tersinkron ke slot kanonis (Tinggi)

- **Lokasi:** `OltSlotController::store/attachCard`, `TaskInfrastructureActionService::attachCardSlot`,
  migration 000002 (backfill satu kali).
- **Masalah:** selama saklar legacy terbuka, slot/kartu baru lewat jalur lama tidak masuk `infrastructure_slots` →
  dua sumber kebenaran (melanggar rancangan §7.5); rekonsiliasi tidak menghitung slot/kartu.
- **Perbaikan:** setiap tulis `olt_slots` (store/attach card) memanggil `InfrastructureLegacyBackfillService` untuk
  slot itu dalam transaksi yang sama (map/link ke `infrastructure_slots`); tambah metrik slot ke rekonsiliasi; tolak
  `storeSlot` kanonis bila nomor slot sudah ada di `olt_slots` chassis yang sama tanpa tautan (sinkronkan, jangan duplikat).
- **Tes:** tambah slot lewat form lama → `infrastructure_slots` + mapping terbentuk; tidak ada slot ganda.

### 5. Reset dummy selalu ditolak oleh audit `created` (Tinggi)

- **Lokasi:** `app/Services/InfrastructureDummyResetService.php`.
- **Masalah:** `NetworkNode` memakai `RecordsAuditLogs` dengan `auditEvents=['created']`; cek "riwayat audit node"
  menolak semua node. Tes lolos karena menghapus audit manual.
- **Perbaikan:** abaikan baris audit aksi `create`/`created` milik node itu sendiri; tetap tolak audit aksi lain
  (move, update, delete, legacy_switch_remap, dll.). Hapus manipulasi audit dari tes.
- **Tes:** node dummy hanya punya audit `created` → reset berhasil; node dengan audit `move` → ditolak.

### 6. Reset dummy menghapus slot chassis lain (Tinggi)

- **Lokasi:** `InfrastructureDummyResetService::reset()` — `->orWhereIn('card_node_id', $nodeIds)->delete()`.
- **Masalah:** kartu dummy di slot chassis nyata → baris `olt_slots` chassis nyata terhapus (atau FK error mentah bila
  sudah dibackfill); mapping `olt_slot`/`olt_card_node` milik chassis yang direset tidak dibersihkan.
- **Perbaikan:** hapus `olt_slots` hanya untuk chassis yang direset; untuk kartu, kosongkan `card_node_id`; bersihkan
  mapping `olt_slot`/`olt_card_node` terkait; tolak bila slot chassis yang direset dirujuk `infrastructure_slots`
  milik data yang tidak ikut direset.
- **Tes:** reset kartu dummy di chassis nyata → slot chassis nyata tetap ada, `card_node_id` null.

### 7. `mapOdpUplink` tidak dicocokkan dengan rantai lama (Sedang)

- **Lokasi:** `app/Services/InfrastructureLegacyDecisionService.php::mapOdpUplink()`.
- **Masalah:** ODP di bawah feeder → node PON #25 bisa disambung ke PON mana pun walau #25 sudah dipetakan ke port
  tertentu (`legacy_network_node_id`).
- **Perbaikan:** telusuri rantai parent lama ODP; bila ada node `pon_port` leluhur yang sudah dipetakan, port tujuan
  wajib sama, kecuali operator memberi flag `override` + alasan eksplisit yang tercatat di audit.
- **Tes:** pilih PON berbeda tanpa override → ditolak; dengan override → tercatat.

### 8. `mapAggregationSwitch` merusak uplink Mini POP (Sedang)

- **Lokasi:** `InfrastructureLegacyDecisionService::mapAggregationSwitch()`.
- **Masalah:** seeder lama mengisi `pops.uplink_node_id` Mini POP = AGG-*; setelah Switch dipindah ke Mini POP itu,
  uplink menunjuk perangkat di dalam dirinya sendiri (self-loop/garis hilang di Peta).
- **Perbaikan:** saat memetakan, bila ada POP yang `uplink_node_id` = Switch itu dan Switch kini berada di POP
  tersebut, kosongkan `uplink_node_id` (atau minta uplink pengganti) dengan audit; tolak bila operator tidak memilih.
- **Tes:** map-switch #23 → C1 → `uplink_node_id` C1 tidak lagi menunjuk #23; audit tercatat.

### 9. `mapPonNode` tidak membawa kapasitas (Sedang)

- **Lokasi:** `InfrastructureLegacyDecisionService::mapPonNode()`.
- **Masalah:** `network_nodes.capacity` dan `red_zone_threshold` node `pon_port` lama tidak dipindah (§16.3 minta
  dipetakan saat transisi) → Red Zone PON hasil migrasi "belum bisa dinilai".
- **Perbaikan:** salin `capacity` (satuan pelanggan aktif, bila operator mengonfirmasi satuannya) dan ambang dari
  tipe/node lama sebagai nilai awal; operator boleh mengoreksi; bila nilai lama tidak bersatuan pelanggan, tampilkan
  peringatan dan biarkan kosong.
- **Tes:** node lama capacity 64 → port baru capacity 64 + audit.

### 10. N+1 pada `assetsAt()` (Rendah)

- **Lokasi:** `app/Services/InfrastructureLocationOptionsService.php::assetsAt()`.
- **Masalah:** memuat semua serial DEPLOYED lalu `currentLocationOf()` per serial di setiap ganti dropdown.
- **Perbaikan:** filter di SQL ke subtree lokasi (kumpulan ID Mini POP/node/slot/port turunan) sebelum resolve;
  hitung turunan dengan `count()`.
- **Tes:** jumlah query terbatas (assert query count) untuk 200 serial lintas Cabang.

## Urutan pengerjaan

1 → 2 → 3 → 4 (integritas data dan bukti cutover) → 5 → 6 (reset dummy) → 7 → 8 → 9 (keputusan operator) → 10.
Setiap perbaikan: tes merah → perbaikan → tes hijau → Pint → commit terpisah; dokumen audit §7 diperbarui.
