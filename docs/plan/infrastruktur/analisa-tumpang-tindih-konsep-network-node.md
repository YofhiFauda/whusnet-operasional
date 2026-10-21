# Analisa: Tumpang Tindih 3 Konsep di `network_nodes`

2026-10-17 · Analisa diminta user setelah diskusi soal makna "port" di modul Infrastruktur. Bukan rencana perubahan — ini pencatatan temuan supaya gak ilang pas sprint pindah.

## Ringkasan

`network_nodes` satu tabel dipakai buat menampung 3 konsep yang secara sifat beda:

1. **Topologi Fiber/Kabel** — jalur fisik dari sumber ke pelanggan (Core Router → Backbone → ODC → ODP → rumah pelanggan).
2. **Slot Hardware OLT** — posisi fisik kartu/port di dalam satu chassis OLT (chassis → card → pon_port).
3. **Penempatan Logis Pelanggan** — siapa nempel di port mana (assignment customer ↔ port, atau sambungan antar node).

Ketiganya numpuk di tabel `network_nodes` + `network_node_ports` + `network_port_assignments`, dibedakan cuma lewat `node_type_id` dan flag-flag opsional (`has_ports`, `monitors_capacity`), bukan lewat struktur tabel yang eksplisit per konsep. Diagram & tabel rekonsiliasi: lihat artifact (link dikirim ke user di chat).

## 1. Bukti: timeline migration = jejak desain belum selesai saat dibangun

Modul lahir 2026-10-12, berubah bentuk 5x dalam 5 hari:

| Tanggal | Migration | Apa yang berubah |
|---|---|---|
| 10-12 | `create_network_nodes_table`, `create_network_node_ports_table` | Skema dasar |
| 10-12 | `reshape_odps_into_network_nodes` | Data ODP lama dipaksa masuk model baru |
| 10-13 | `create_network_port_assignments_table` | `target_node_id` doang buat sambungan antar node |
| 10-13 | `create_olt_slots_table` | Slot OLT masih pakai `pop_id` sebagai owner |
| 10-13 | `create_network_node_type_rules_table` + `backfill_default_network_node_type_rules` | Aturan "tipe X boleh anak tipe Y" — hardcode array di migration |
| 10-14 | `reconcile_olt_slots_chassis_node_id` | `olt_slots` pindah owner dari `pop_id` → `chassis_node_id` (karena Mini POP bisa punya >1 chassis) |
| 10-17 | `add_target_port_to_network_port_assignments_table` | Ternyata `target_node_id` gak cukup, butuh `target_port_id` juga |

Tiap baris bukan fitur baru — itu "ternyata model sebelumnya kurang". `reconcile_olt_slots_chassis_node_id` bahkan bisa `throw` di tengah migration produksi kalau data ambigu (lihat `app/.../2026_10_14_120000_reconcile_olt_slots_chassis_node_id.php` baris 34-38) — tanda model lama gak cukup informasi buat ditebak otomatis.

## 2. Temuan per konsep

### Konsep A — Topologi Fiber
- Representasi: `network_nodes.parent_id` (tree) + `anchor_pop_id` (titik tambat ke POP).
- Aturan valid: `network_node_type_rules` — **opt-in**. Kalau tipe baru gak punya row rule, otomatis bebas ditempatkan di mana saja (`NetworkNodeService::assertTypePlacement()`, gak ada rule = `return` tanpa cek). Sengaja didesain begitu (lihat `docs/superpowers/plans/Analisa Desain Pohon Infrastruktur Dinamis (network_nodes).md` §5: "tabel ini boleh kosong yang berarti tanpa batasan") — tapi berarti juga gak ada guard default-deny.
- Aturan XOR (`parent_id` xor `anchor_pop_id`) ditegakin di service, bukan di DB. Model `NetworkNode` sengaja gak reject kombinasi ilegal (komentar di model, baris 17-22) — jalur tinker/import/seeder bisa nulis data invalid tanpa ketahuan.

### Konsep B — Slot Hardware OLT
- Representasi: `olt_slots` (`chassis_node_id`, `card_node_id`, `number`) — TAPI `card_node_id` juga node di tree yang sama (tipe `olt_card`), jadi hubungan chassis↔card ada DUA jalur: lewat `parent_id` (tree) ATAU lewat `olt_slots`. Gak ada yang menjamin keduanya konsisten.
- Awalnya di-anchor ke POP langsung (`pop_id`), direconcile ke `chassis_node_id` tgl 10-14 karena satu Mini POP bisa punya banyak chassis — bukti model awal gak modelin relasi 1-POP-ke-banyak-chassis dari awal.

### Konsep C — Penempatan Logis Pelanggan
- Representasi: `network_port_assignments` — satu tabel, SATU set kolom (`network_node_port_id`, `customer_id`, `target_node_id`, `target_port_id`) dipakai buat DUA makna beda: "pelanggan nempel di port" (customer_id terisi) ATAU "port ini nyambung ke port node lain" (target_node_id/target_port_id terisi). Gak ada kolom tipe eksplisit — baca null/isi kolom buat nebak maksud baris.
- 4 unique constraint aktif numpuk tanpa penjelasan kenapa butuh semua:
  - `(network_node_port_id, active_key)` — 1 port, 1 assignment aktif.
  - `(customer_id, active_key)` — 1 pelanggan, 1 port aktif.
  - `(target_node_id, active_key)` — 1 node tujuan cuma boleh jadi tujuan 1 link aktif.
  - `(target_port_id, active_key)` — ditambah 10-17, port tujuan juga cuma 1.
  Constraint ke-3 & ke-4 overlap granularitas (node vs port) tanpa catatan kapan masing-masing berlaku; kalau suatu hari ada node yang legitimately butuh >1 link masuk (feeder redundan), constraint ke-3 nolak duluan.

## 3. Sumber kebenaran ganda

Aturan "tipe node X boleh jadi anak tipe Y" ada di DUA tempat:
- `backfill_default_network_node_type_rules.php` — hardcode array PHP, permanen di migration (gak rerun kalau diubah).
- `NetworkNodeTypeSeeder` — komentar migration sendiri ngaku: "Lihat catatan di NetworkNodeTypeSeeder".

Dua sumber kebenaran soal topologi valid. Migration yang sudah jalan gak ikut berubah kalau seeder diedit belakangan → berisiko drift.

## 4. Dampak

- **Implementasi:** developer baru harus baca migration + seeder + komentar model + service buat ngerti "apa yang valid" — gak ada satu tempat.
- **Manajemen data:** integritas XOR dan tipe-placement gak ditegakin DB, bergantung disiplin kode di semua jalur masuk (controller, artisan, import, tinker).
- **Transparansi:** `NetworkPortAssignment` overload makna bikin query/report harus selalu cek customer_id vs target_node_id buat nebak jenis baris, bukan filter eksplisit.

## 5. Status

Ini catatan analisa, BUKAN keputusan refactor. Belum ada rencana perubahan — nunggu arahan user mau ditindaklanjuti atau dibiarkan (modul masih dalam sprint aktif, lihat `docs/TASKS.md`).
