# Slot, PON, dan Port Switch Menjadi Node — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Slot OLT/Switch, PON, dan Port Switch menjadi baris `network_nodes` (dengan `parent_id`), menggantikan tabel `infrastructure_slots` dan `infrastructure_ports`.

**Architecture:** Satu pohon `network_nodes`. Empat tipe node baru (`olt_slot`, `olt_pon`, `switch_slot`, `switch_port`) dengan aturan induk lewat `network_node_type_rules.parent_type_id` yang sudah ada. Kolom khusus ditambah nullable ke `network_nodes`. Semua kolom FK lokasi (`*_infrastructure_slot_id` / `*_infrastructure_port_id`) dipertahankan namanya tapi FK-nya diarahkan ke `network_nodes.id`. ODP→PON memakai `parent_id`; `infrastructure_connections` tinggal untuk sambungan Port Switch.

**Tech Stack:** Laravel 13, PHP 8.3, PHPUnit 12, sqlite `:memory:` (tes) / MySQL 8 (produksi), Blade + Alpine.

**Spec:** `docs/superpowers/specs/2026-10-24-slot-pon-sebagai-node-design.md`. Dokumen yang wajib dibaca sebelum menyentuh area ini: `docs/plan/infrastruktur/rancangan-hierarki-infrastruktur-server-pop-mini-pop.md` (§2, §3, §12, §16, §17), `docs/plan/infrastruktur/audit-transisi-hierarki-infrastruktur.md`, `docs/master/` (node/POP), `docs/rbac/`.

## Global Constraints

- Bahasa kerja Indonesia: komentar menjelaskan *kenapa*, bukan *apa*; pesan error Indonesia.
- Kerja di branch `dev`. Jalankan `vendor/bin/pint --dirty --format agent` sebelum tiap commit.
- Tes: **jangan jalankan full suite**. Hanya file/filter terdampak: `php artisan test --compact tests/Feature/...`.
- Tes memakai PHPUnit 12, atribut `#[Test]`/`#[DataProvider]`, `RefreshDatabase`, `loginAsAdmin()` dari `Tests\TestCase`. Nama tes regresi sesuai gejala.
- Tidak ada tes yang dihapus tanpa persetujuan user. Tes lama diubah, bukan dihapus.
- Setiap query node/pelanggan wajib lewat POP scope (`EffectiveAccessService`, bukan `$user->pops()`).
- Target aksi POST/PUT/DELETE dirender server-side lewat `route()`.
- Handler POST/PUT/DELETE selalu redirect (PRG). Mutasi data = halaman create, bukan modal.
- Kolom FK lokasi **tidak di-rename** (spec §4); beri komentar bahwa nama `*_slot_id`/`*_port_id` kini menunjuk node.
- Tipe legacy `odc`, `pon_port`, `olt_card`, `aggregation_switch`, `core_router` tidak boleh dihidupkan lagi.
- Kode Slot/PON/Port diturunkan otomatis dari kode induk; tidak ada input bebas.
- Nomor unik per induk: `unique(parent_id, number)`; `network_nodes.code` tetap unik global.
- Migrasi destruktif hanya jalan bila `app()->environment('testing')` atau `config('infrastructure.confirm_dummy_reset') === true`.
- Commit diakhiri baris `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.

## Keputusan Terbuka — penyimpangan dari spec yang ditemukan saat membaca kode

Plan ini memakai nilai default di bawah. Tiap butir perlu persetujuan user sebelum Task terkait dimulai.

| # | Temuan | Default di plan | Task |
|---|---|---|---|
| D1 | `network_nodes.status` sudah berarti `active`/`inactive` (siklus hidup node). Spec §2.2 menyuruh memakai nilai `InfrastructureRecordStatus` (`belum_didata`/`terverifikasi`/`rusak_nonaktif`) di kolom yang sama: dua makna dalam satu kolom. | Kolom baru `record_status` (nullable string 30). `status` tetap `active`/`inactive`. | 1 |
| D2 | `InfrastructureDummyResetService` mencatat database saat ini **bukan dummy murni** (ODP-JETIS-01 memegang pelanggan). Spec §2.4 menyuruh membuang ODP lama. Menghapus ODP berpelanggan melanggar aturan `restrictOnDelete`. | Migrasi **tidak menghapus node ODP**. Yang dibersihkan hanya baris slot/port/koneksi/mapping. ODP lama (anchor POP) dibiarkan dan baru wajib berinduk PON saat dibuat atau dipindah. Operator memasangnya ke PON lewat `move()`. | 5, 10 |
| D3 | `code` `string(50)` tidak muat kode turunan (`{kode OLT}-S3-P5`). | Lebarkan `network_nodes.code` jadi `string(120)`. | 1 |
| D4 | Spec diam soal `olt_slots`, `OltSlot`, `OltSlotController` (`attachCard`, kartu sudah tidak ada), dan alat legacy (`InfrastructureLegacyBackfillService`, `InfrastructureLegacyDecisionService`, `InfrastructureLegacyMappingsCommand`, `InfrastructureReconciliationService`, `InfrastructureDummyResetService`, `infrastructure_legacy_mappings`). Semuanya bergantung pada tabel yang dibuang. | Task 14 memensiunkan semuanya. **Butuh persetujuan eksplisit** karena menghapus kode dan mengubah tes. | 14 |
| D5 | Slot/PON/Port yang dipindah antar-induk merusak identitas (nomor, kode). | `NetworkNodeService::move()` menolak tipe berposisi (`olt_slot`, `olt_pon`, `switch_slot`, `switch_port`). ODP boleh dipindah antar-PON dalam Cabang yang sama. | 10 |

## Review Focus

1. **Kode turunan melebihi panjang kolom:** OLT berkode panjang → kode PON ditolak/terpotong. Harapan: aman sampai 120 karakter, error ramah di atasnya (Task 3).
2. **Generate massal diulang atau sebagian:** OLT sudah punya Slot 1–4, generate 16 slot. Harapan: hanya Slot 5–16 dibuat, Slot 1–4 dan PON-nya tidak ditimpa (Task 4).
3. **Hapus/pindahkan Slot atau PON yang berisi aset atau ODP:** harapan: ditolak dengan pesan, bukan FK error 500 (Task 10).
4. **User cabang A membuka PON cabang B lewat URL atau endpoint lazy-load:** harapan: 403/404 (Task 12).
5. **ODP lama (anchor POP) dan pelanggannya setelah migrasi:** harapan: tetap terbaca dan tetap bisa menampung pelanggan; tidak hilang dari pohon (Task 5, 11).

---

## File Structure

| File | Tanggung jawab |
|---|---|
| `database/migrations/2026_10_24_000001_add_position_columns_and_types_to_network_nodes.php` | Kolom baru, indeks, tipe node, aturan induk |
| `database/migrations/2026_10_24_000002_retarget_location_foreign_keys_to_network_nodes.php` | Bersihkan baris dummy, arahkan FK ke `network_nodes`, drop tabel lama |
| `config/infrastructure.php` | `confirm_dummy_reset` |
| `app/Models/NetworkNode.php` | Helper posisi, relasi `slots()`/`ports()`/`uplinkPon()` |
| `app/Enums/InfrastructureSlotKind.php`, `InfrastructurePortKind.php` | Pemetaan ke kode tipe node |
| `app/Services/InfrastructurePositionService.php` | Penulis tunggal Slot/PON/Port sebagai node + generate massal |
| `app/Services/InfrastructureConnectionService.php` | Sambungan Port Switch saja |
| `app/Services/PonCapacityService.php` | Kapasitas PON dari ODP anak |
| `app/Services/InfrastructureLocationResolver.php` | Leluhur berbasis `parent_id` |
| `app/Services/NetworkNodeService.php` | Aturan penempatan + guard pindah |
| `app/Services/InfrastructureHierarchyService.php`, `InfrastructureMapService.php` | Proyeksi pohon |
| `app/Http/Controllers/Network/*` | Controller posisi, lazy-load, generate |
| `database/factories/NetworkNodeFactory.php` | State `slot()`, `pon()`, `switchPort()` |

---

### Task 1: Skema — kolom, indeks, tipe node, aturan induk

**Files:**
- Create: `database/migrations/2026_10_24_000001_add_position_columns_and_types_to_network_nodes.php`
- Modify: `database/seeders/NetworkNodeTypeSeeder.php`, `app/Models/NetworkNode.php` (`#[Fillable]` + casts)
- Test: `tests/Feature/Network/NodePositionSchemaTest.php`

**Interfaces:**
- Produces: kolom `network_nodes.number` (unsigned smallint null), `capacity_unit` (string 30 null), `is_logical` (bool default false), `record_status` (string 30 null), `red_zone_threshold` (unsigned tinyint null); `code` jadi `string(120)`; `unique(parent_id, number)` bernama `network_nodes_parent_number_unique`; tipe `olt_slot`, `olt_pon`, `switch_slot`, `switch_port`; rule `parent_type_id`: `olt_slot→olt_chassis`, `olt_pon→olt_slot`, `switch_slot→switch_mini_pop`, `switch_port→switch_slot`, `odp→olt_pon`.

- [ ] **Step 1: Tulis tes gagal** `NodePositionSchemaTest`: `#[Test] tipe_posisi_dan_aturan_induk_ada` (assert 4 kode tipe aktif ada, rule `child→parent` persis tabel di atas, rule anchor `odp` untuk `cabang`/`mini_pop` sudah tidak ada); `#[Test] nomor_unik_per_induk_bukan_global` (dua OLT masing-masing punya `olt_slot` nomor 1 lolos; dua slot nomor 1 pada OLT yang sama melempar `UniqueConstraintViolationException`); `#[Test] kode_panjang_120_karakter_tersimpan`.
- [ ] **Step 2: Jalankan** `php artisan test --compact tests/Feature/Network/NodePositionSchemaTest.php` → FAIL.
- [ ] **Step 3: Migrasi.** Tambah kolom, `change()` `code` ke 120, unique index, `insertOrIgnore` tipe + rule (pola `2026_10_21_000001`). Hapus rule anchor ODP. Nilai tipe: `has_ports=false`, `monitors_capacity=false`, `max_ports=null`, `is_active=true`, `sort_order` lanjut. `down()` membalik (hapus rule/tipe bila belum dipakai).
- [ ] **Step 4: Seeder.** Tambah 4 tipe ke `$types` dan 5 rule induk ke `$defaultRules` (struktur `'nodes'` yang sudah ada). Hapus rule anchor `odp`. Seeder tetap `firstOrCreate`.
- [ ] **Step 5: Model.** Tambah 5 atribut ke `#[Fillable]`; casts `record_status => InfrastructureRecordStatus::class`, `capacity_unit => InfrastructureCapacityUnit::class`, `is_logical => 'boolean'`, `number`/`red_zone_threshold => 'integer'`.
- [ ] **Step 6: Jalankan tes** → PASS. Jalankan juga `tests/Feature/Network/NetworkNodePlacementRuleTest.php` (harus tetap lulus).
- [ ] **Step 7: Commit** `feat(network): kolom posisi dan 4 tipe node Slot/PON/Port (ADHOC-213)`.

### Task 2: Model dan enum — helper posisi

**Files:**
- Modify: `app/Models/NetworkNode.php`, `app/Enums/InfrastructureSlotKind.php`, `app/Enums/InfrastructurePortKind.php`, `database/factories/NetworkNodeFactory.php`
- Test: `tests/Feature/Network/NodePositionModelTest.php`

**Interfaces:**
- Produces:
  - `InfrastructureSlotKind::typeCode(): string` (`olt_slot`/`switch_slot`); `::fromSlotTypeCode(?string): ?self`.
  - `InfrastructurePortKind::typeCode(): string` (`olt_pon`/`switch_port`); `::fromPortTypeCode(?string): ?self`.
  - `NetworkNode::isSlot(): bool`, `isPort(): bool`, `isPon(): bool`, `isPosition(): bool`, `slotKind(): ?InfrastructureSlotKind`, `portKind(): ?InfrastructurePortKind`, `slots(): HasMany` (anak bertipe slot, urut `number`), `ports(): HasMany`, `uplinkPon(): ?NetworkNode` (parent bila `olt_pon`), `displayName(): string` (`Slot 3`, `PON 5`, `Port 7`, selain itu `code`).
  - Factory: `NetworkNodeFactory::slot(NetworkNode $device, int $number)`, `pon(NetworkNode $slot, int $number)`, `switchPort(NetworkNode $slot, int $number)` — otomatis `firstOrCreate` tipe, mengisi `parent_id`, `anchor_pop_id=null`, `number`, `code` turunan.

- [ ] **Step 1: Tulis tes gagal**: `slot_pon_dan_odp_membentuk_rantai_parent`, `ports_pada_slot_olt_hanya_mengembalikan_pon`, `uplinkPon_odp_adalah_parent_bila_pon_dan_null_bila_anchor_pop`, `displayName_per_jenis`.
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implementasi** sesuai signature di atas. `slots()`/`ports()` memakai `whereHas('nodeType', code in [...])`.
- [ ] **Step 4: Jalankan** → PASS.
- [ ] **Step 5: Commit** `feat(network): helper posisi pada NetworkNode dan factory`.

### Task 3: InfrastructurePositionService — Slot/PON/Port sebagai node

**Files:**
- Modify: `app/Services/InfrastructurePositionService.php`
- Test: `tests/Feature/Network/InfrastructurePositionTest.php` (diubah dari model lama)

**Interfaces:**
- Consumes: Task 1–2.
- Produces (semua mengembalikan `NetworkNode`):
  - `createSlot(NetworkNode $device, int $number, array $attributes = []): NetworkNode`
  - `ensureLogicalSlot(NetworkNode $switch): NetworkNode` (idempoten, Slot 1, `is_logical=true`, label `LOGICAL_SLOT_LABEL`)
  - `createPort(NetworkNode $slot, int $number, array $attributes = []): NetworkNode` — jenis diturunkan dari tipe slot; tidak ada parameter `$kind` lagi.
  - `updateDeviceCapacity(NetworkNode $device, int $capacity, User $actor): NetworkNode`
  - `updateSlotCapacity(NetworkNode $slot, int $capacity, User $actor): NetworkNode`
  - `private derivedCode(NetworkNode $parent, string $letter, int $number): string` → `{parent.code}-{S|P}{n}`; melempar `InvalidArgumentException('Kode turunan melebihi 120 karakter.')`.

- [ ] **Step 1: Ubah tes lama + tambah tes** (tetap satu file): `slot_hanya_di_olt_atau_switch`, `pon_hanya_di_slot_olt_dan_port_switch_hanya_di_slot_switch`, `nomor_ganda_per_induk_ditolak_tapi_nomor_sama_di_induk_lain_lolos`, `kode_slot_dan_pon_diturunkan_dari_kode_induk`, `kode_turunan_lebih_dari_120_karakter_ditolak_ramah`, `slot_melebihi_kapasitas_perangkat_ditolak`, `slot_logis_hanya_untuk_switch_dan_idempoten`, `red_zone_dan_kapasitas_pelanggan_hanya_untuk_pon`.
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Tulis ulang service.** Tulis ke `network_nodes`: `parent_id=$parent->id`, `anchor_pop_id=null`, `node_type_id` dari `typeCode()`, `name=displayName`, `record_status` default `BELUM_DIDATA`, `capacity_unit` (`PON`/`PORT` untuk slot, `PELANGGAN_AKTIF` untuk PON, null untuk Port Switch), `status='active'`. Validasi tipe induk lewat `NetworkNodeService::assertTypePlacement` (jangan duplikasi aturan). `lockForUpdate` pada induk. Bungkus `UniqueConstraintViolationException` seperti `guardUnique` lama. Hapus logika `legacy_olt_slot_id`/`OltSlot`.
- [ ] **Step 4: Jalankan** → PASS.
- [ ] **Step 5: Commit** `refactor(network): InfrastructurePositionService menulis Slot/PON sebagai node`.

### Task 4: Generate massal Slot & PON/Port

**Files:**
- Modify: `app/Services/InfrastructurePositionService.php`, `routes/web.php` (static sebelum dynamic), `app/Http/Controllers/Network/InfrastructurePositionController.php`
- Create: `resources/views/network/positions/generate-structure.blade.php`
- Test: `tests/Feature/Network/GenerateInfrastructureStructureTest.php`

**Interfaces:**
- Produces: `InfrastructurePositionService::generateStructure(NetworkNode $device, int $slotCount, int $portsPerSlot, ?int $ponCapacity, User $actor): array{slots_created:int, ports_created:int}`; rute `GET network.nodes.generate-structure.create` dan `POST network.nodes.generate-structure.store` (`/network/nodes/{node}/generate-structure`), permission mengikuti rute `network.nodes.infrastructure-slots.store`. Setelah simpan redirect ke halaman detail node (`network.nodes.show`).

- [ ] **Step 1: Tes gagal**: `olt_16_slot_8_pon_membuat_16_slot_dan_128_pon`, `generate_ulang_hanya_menambah_nomor_yang_belum_ada` (Slot 1–4 sudah ada → hanya 12 slot baru; PON Slot 1–4 tidak berubah, kapasitasnya tidak tertimpa), `satu_entri_audit_per_aksi` (satu baris `AuditLog` module `Struktur Infrastruktur`), `switch_membuat_satu_slot_logis_dan_n_port`, `gagal_di_tengah_membatalkan_semua` (paksa error → tak ada node tersisa), `user_cabang_lain_ditolak_403`, `jumlah_di_atas_batas_ditolak` (batas 64 slot dan 128 port per slot; `ValidationException`).
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implementasi service**: satu `DB::transaction`; kunci perangkat; hitung nomor yang belum ada; buat lewat `createSlot`/`createPort` dengan audit per-node dimatikan (`saveQuietly`/`withoutEvents`) dan satu `AuditLog::create` di akhir berisi ringkasan; set `device.capacity` bila lebih kecil dari `slotCount`.
- [ ] **Step 4: Controller + view + rute.** `FormRequest`-style `validate()` di controller (`slot_count` 1–64, `ports_per_slot` 1–128, `pon_capacity` nullable ≥1). Cek POP scope lewat `InfrastructureLocationResolver` seperti aksi posisi lain.
- [ ] **Step 5: Jalankan** → PASS.
- [ ] **Step 6: Commit** `feat(network): generate massal Slot dan PON/Port`.

### Task 5: Migrasi retarget FK + bersihkan data posisi lama

**Files:**
- Create: `database/migrations/2026_10_24_000002_retarget_location_foreign_keys_to_network_nodes.php`, `config/infrastructure.php`
- Test: `tests/Feature/Network/RetargetLocationForeignKeysTest.php`

**Interfaces:**
- Produces: kolom-kolom berikut menunjuk `network_nodes.id` (nama tidak berubah): `inventory_serials.current_infrastructure_{slot,port}_id`; `inventory_transactions.{from,to}_infrastructure_{slot,port}_id`; `infrastructure_balances.infrastructure_{slot,port}_id`; `task_infrastructure_actions.work_infrastructure_{slot,port}_id`, `end_from_infrastructure_port_id`, `end_to_infrastructure_port_id` (nama FK `tia_end_from_port_foreign`, `tia_end_to_port_foreign`); `fop_tasks.scope_infrastructure_{slot,port}_id`; `infrastructure_connections.{source,target}_infrastructure_port_id`. Tabel `infrastructure_slots`, `infrastructure_ports`, `infrastructure_legacy_mappings` dihapus. `config('infrastructure.confirm_dummy_reset')` dari `env('INFRA_CONFIRM_DUMMY_RESET', false)`.

- [ ] **Step 1: Tes gagal**: `migrasi_menolak_tanpa_konfirmasi_di_luar_testing` (uji lewat memanggil kelas migrasi dengan env `production` + config false → `RuntimeException` berisi jumlah baris yang akan dibersihkan); `fk_lokasi_menunjuk_network_nodes` (`Schema::getForeignKeys` tiap tabel: referensi tabel `network_nodes`); `tabel_lama_hilang`; `node_odp_berpelanggan_tidak_dihapus` (D2: ODP + `network_port_assignments` aktif tetap ada setelah migrasi).
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implementasi `up()`**: (a) guard konfirmasi + hitung baris slot/port/koneksi/mapping; (b) set `NULL` seluruh kolom di atas; (c) `delete` isi `infrastructure_connections`, `infrastructure_legacy_mappings`, `infrastructure_ports`, `infrastructure_slots`; (d) per kolom `dropForeign` (identitas array di sqlite, nama di MySQL — pola `2026_10_21_150332`) lalu `foreign(...)->references('id')->on('network_nodes')->restrictOnDelete()`; (e) drop tabel. `down()` melempar `RuntimeException('Tidak dapat dibalik: data posisi lama sudah dibersihkan.')`. Cek kompatibilitas ganda: jalankan juga di MySQL bila tersedia; minimal sqlite.
- [ ] **Step 4: Jalankan** → PASS.
- [ ] **Step 5: Commit** `feat(db): arahkan FK lokasi ke network_nodes dan drop tabel slot/port lama`.

> Setelah Task 5, tes yang masih memakai `InfrastructureSlot`/`InfrastructurePort` gagal sampai Task 6–13 selesai. Itu diharapkan; jalankan hanya tes task berjalan.

### Task 6: Model terkait — relasi ke NetworkNode

**Files:**
- Modify: `app/Models/InventorySerial.php`, `InventoryTransaction.php`, `InfrastructureBalance.php`, `TaskInfrastructureAction.php`, `FopTask.php`, `InfrastructureConnection.php`
- Delete (setelah Task 14 disetujui bila perlu): `app/Models/InfrastructureSlot.php`, `InfrastructurePort.php`
- Test: `tests/Feature/Warehouse/ExactInfrastructureAssetLocationTest.php` (diubah)

**Interfaces:**
- Produces: relasi `infrastructureSlot(): BelongsTo` dan `infrastructurePort(): BelongsTo` (nama tetap) ke `NetworkNode` dengan FK kolom lama; `InfrastructureConnection::sourcePort()`/`targetPort()` ke `NetworkNode`.

- [ ] **Step 1: Ubah tes** `ExactInfrastructureAssetLocationTest` memakai `NetworkNode` Slot/PON; tambah `serial_dipasang_di_slot_node_membawa_lokasi_tepat` dan `fk_menolak_slot_yang_bukan_node` (nilai id tidak ada → `QueryException`).
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Ganti `belongsTo(InfrastructureSlot::class, ...)` → `belongsTo(NetworkNode::class, ...)`** di keenam model; hapus import lama.
- [ ] **Step 4: Jalankan** → PASS.
- [ ] **Step 5: Commit**.

### Task 7: InfrastructureLocationResolver

**Files:**
- Modify: `app/Services/InfrastructureLocationResolver.php`
- Test: `tests/Feature/TaskInfrastructureHierarchicalLocationTest.php`, `tests/Feature/Network/InfrastructureEndpointScopeTest.php` (diubah)

**Interfaces:**
- Produces: `resolve(string $kind, int $id): InfrastructureLocation` tetap; `InfrastructureLocation::SLOT/PORT` dipertahankan sebagai penanda `kind`. `forSlot(NetworkNode)`, `forPort(NetworkNode)` jadi alias `forNode` yang memverifikasi tipe (melempar `InvalidArgumentException("Node {code} bukan Slot")`). `forNode` hanya naik lewat `parent_id`; cabang uplink-via-koneksi dihapus. Kind `type` pada rantai = kode tipe node; `kind` Slot/Port bila `isSlot()`/`isPort()`.

- [ ] **Step 1: Ubah/tambah tes**: `ancestors_odp_adalah_pon_slot_olt_lalu_pop` (rantai berurut), `resolve_slot_dengan_id_node_non_slot_ditolak`, `scope_cabang_dihitung_dari_rantai_parent`.
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implementasi**; `MAX_DEPTH=32` dipertahankan.
- [ ] **Step 4: Jalankan** → PASS.
- [ ] **Step 5: Commit**.

### Task 8: InfrastructureConnectionService — Port Switch saja

**Files:**
- Modify: `app/Services/InfrastructureConnectionService.php`, `app/Models/InfrastructureConnection.php`
- Test: `tests/Feature/Network/InfrastructureConnectionTest.php` (diubah)

**Interfaces:**
- Produces: `connect(NetworkNode $source, User $actor, ?NetworkNode $targetNode = null, ?NetworkNode $targetPort = null, ?string $targetLabel = null, bool $verified = false, ?string $notes = null): InfrastructureConnection` — `$source` wajib `switch_port`, `$targetPort` wajib `switch_port`, `$targetNode` tidak boleh `odp`. `verify`, `release` tetap. `occupancyOf(NetworkNode $port): InfrastructureOccupancy`. `activeUplinkFor` **dihapus** (gantinya `NetworkNode::uplinkPon()`).

- [ ] **Step 1: Ubah tes**: `pon_tidak_bisa_jadi_sumber_sambungan` (pesan: "ODP tersambung ke PON lewat induknya, bukan sambungan"), `port_switch_satu_sambungan_aktif`, `tujuan_odp_ditolak`, `lintas_cabang_ditolak`, `release_menyimpan_histori`, `occupancy_*`.
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implementasi**: buang cabang PON; `cabangOfPort` memakai `resolvePop()` node; tampilkan `displayName()` pada pesan.
- [ ] **Step 4: Jalankan** → PASS.
- [ ] **Step 5: Commit**.

### Task 9: PonCapacityService

**Files:**
- Modify: `app/Services/PonCapacityService.php`
- Test: `tests/Feature/Network/PonCapacityTest.php` (diubah)

**Interfaces:**
- Produces: `used(NetworkNode $pon): int`; `summary(NetworkNode $pon): array{used:int, capacity:?int, threshold:?int, unit:string, unit_label:string, red_zone:?bool, is_full:bool, remaining:?int}` (**tanpa `unverified`**); `updateCapacity(NetworkNode $pon, int $capacity, int $threshold, User $actor): NetworkNode`. Pemakaian = `distinct customer_id` pada `NetworkPortAssignment` aktif di `NetworkNodePort` milik ODP dengan `parent_id = $pon->id`.

- [ ] **Step 1: Ubah tes**: `pemakaian_dihitung_dari_odp_anak_langsung`, `satu_pelanggan_dengan_banyak_ont_dihitung_satu`, `odp_di_pon_lain_tidak_dihitung`, `red_zone_null_bila_kapasitas_atau_ambang_kosong`, `kapasitas_di_bawah_pemakaian_ditolak`, `bukan_pon_ditolak`.
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implementasi**; audit `module='Port Infrastruktur'` dipertahankan agar riwayat konsisten, `auditable_type = NetworkNode::class`.
- [ ] **Step 4: Jalankan** → PASS.
- [ ] **Step 5: Commit**.

### Task 10: NetworkNodeService — penempatan dan guard pindah

**Files:**
- Modify: `app/Services/NetworkNodeService.php`
- Test: `tests/Feature/Network/NetworkNodeMoveTest.php`, `tests/Feature/Network/NetworkNodePlacementRuleTest.php` (diubah)

**Interfaces:**
- Produces: `move()` menolak node `isPosition()` (D5) dengan pesan "Slot/PON/Port tidak dapat dipindah — identitasnya mengikuti induk"; `assertCrossBranchSubtreeIsEmpty` hanya memakai `subtree` node (aset/saldo/koneksi/assignment) tanpa tabel slot/port; ODP boleh dipindah dari anchor POP ke PON, dan antar-PON dalam Cabang yang sama.

- [ ] **Step 1: Ubah/tambah tes**: `slot_pon_port_tidak_bisa_dipindah`, `pindah_olt_ikut_memindahkan_seluruh_subtree` (cek `resolvePop` PON setelah pindah Mini POP dalam Cabang sama), `pindah_lintas_cabang_ditolak_bila_ada_aset_di_slot`, `odp_lama_anchor_pop_bisa_dipasang_ke_pon` (D2), `odp_baru_tanpa_pon_ditolak` (rule `odp→olt_pon`), `odp_antar_pon_lintas_cabang_ditolak`.
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implementasi**; komentar menjelaskan kenapa tipe berposisi tidak boleh dipindah.
- [ ] **Step 4: Jalankan** → PASS.
- [ ] **Step 5: Commit**.

### Task 11: Hierarki, Peta, dan lazy-load

**Files:**
- Modify: `app/Services/InfrastructureHierarchyService.php`, `InfrastructureMapService.php`, `app/Http/Controllers/Network/InfrastructureMapController.php`, `NetworkNodeController.php`, `routes/web.php`, `resources/views/network/nodes/{_hierarchy-item,_tree-node,_pop-tree,index}.blade.php`, `resources/views/network/map/{_hierarchy-item,_tree-node,_pop-tree,index}.blade.php`
- Test: `tests/Feature/Network/InfrastructureHierarchyTest.php`, `InfrastructureMapTest.php` (diubah), `tests/Feature/Network/NodeChildrenLazyLoadTest.php` (baru)

**Interfaces:**
- Produces: `InfrastructureHierarchyService::build(?User $user, ?int $cabangId = null): array` — hanya item `pop:` dan `node:`; Slot/PON/Port bertipe `node:` dengan `type` = kode tipe, `parent` = `node:{parent_id}`; node di bawah tingkat OLT/Switch **tidak** dimuat (ditandai `has_children=true`). Rute `GET network.nodes.children` (`/network/nodes/{node}/children`) → JSON `{children: [{id, key, type, label, has_children, capacity, used, record_status, url}]}`, scope POP dicek (`EffectiveAccessService`), 404/403 bila node di luar scope.

- [ ] **Step 1: Tes gagal**: `pohon_tidak_memuat_slot_ke_bawah_sampai_dibuka`, `children_olt_mengembalikan_slot_urut_nomor`, `children_pon_mengembalikan_odp_dengan_pemakaian`, `children_di_luar_scope_403`, `odp_lama_anchor_pop_tetap_muncul_di_bawah_pop` (Review Focus 5), `item_tidak_lagi_berkunci_slot_atau_port`.
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implementasi service dan controller.** Bobot query: `network_nodes` dimuat per tingkat, `withCount('children')`; tidak ada `N+1` (uji dengan `DB::getQueryLog()` batas jumlah query).
- [ ] **Step 4: View.** Alpine memanggil URL dari atribut `data-children-url` (dirender server-side lewat `route()`), tidak merakit path.
- [ ] **Step 5: `npm run build`** lalu jalankan tes → PASS.
- [ ] **Step 6: Commit**.

### Task 12: Controller dan view posisi

**Files:**
- Modify: `app/Http/Controllers/Network/InfrastructurePositionController.php`, `InfrastructurePortController.php`, `NetworkNodeController.php`, `routes/web.php`, `resources/views/network/nodes/{show,_device-slots,_slot-inspector,_pon-inspector,_create-point-modal}.blade.php`, `resources/views/network/positions/{slot,port}.blade.php`
- Test: `tests/Feature/Network/InfrastructurePositionTest.php`, `OdpNetworkManagementTest.php`, `OltSlotManagementTest.php` (diubah)

**Interfaces:**
- Produces: rute `network.infrastructure-slots.*` dan `network.infrastructure-ports.*` tetap bernama sama tetapi route-model-binding ke `NetworkNode` (`{slot}`/`{port}` diverifikasi tipenya, 404 bila bukan Slot/Port). Pembuatan ODP lewat `NetworkNodeController::store` wajib `parent_id` PON (validasi + `assertTypePlacement`).

- [ ] **Step 1: Tes gagal**: `slot_route_binding_404_bila_node_bukan_slot`, `user_cabang_lain_tidak_bisa_membuka_pon_cabang_lain` (Review Focus 4), `buat_odp_tanpa_pon_ditolak`, `buat_odp_di_pon_membuat_induk_pon`, `ubah_kapasitas_pon_menulis_audit`, `halaman_slot_menampilkan_aset_terpasang_tepat`.
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implementasi** controller + view.
- [ ] **Step 4: `npm run build`**, jalankan tes → PASS.
- [ ] **Step 5: Commit**.

### Task 13: Gudang, Task FOP, laporan

**Files:**
- Modify: `app/Services/InventoryService.php`, `FopTaskInfrastructureScopeService.php`, `InfrastructureLocationOptionsService.php`, `InfrastructureReportLocationParser.php`, `TaskInfrastructureActionService.php`, `app/Http/Controllers/TaskMaintenanceController.php`, `TaskController.php`, `Warehouse/WarehouseUsageController.php`, `Master/PopController.php`, `resources/views/tasks/**`, `resources/views/warehouse/**`
- Test: `tests/Feature/Warehouse/InfrastructureCableConsumptionTest.php`, `ExactInfrastructureAssetLocationTest.php`, `tests/Feature/InfrastructureLocationOptionsTest.php`, `FopTaskInfrastructureScopeTest.php`, `TaskInfrastructureDeployReportTest.php` (diubah)

**Interfaces:**
- Produces: `InfrastructureLocationOptionsService` mengembalikan opsi Slot/PON dari anak node (`ports()`/`slots()`), bukan tabel lama; `InventoryService::deploySerial()` menyimpan `current_infrastructure_slot_id` = id node Slot dan `current_infrastructure_port_id` = id node PON/Port, dengan validasi tipe.

- [ ] **Step 1: Ubah tes**: deploy serial ke Slot node; deploy ke PON node; serial tak kompatibel ditolak; opsi lokasi memuat PON dari Slot yang benar; scope FOP menolak Slot di cabang lain; laporan membawa lokasi tepat.
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implementasi** (ganti kueri `InfrastructureSlot::`/`InfrastructurePort::` menjadi kueri `NetworkNode` dengan filter tipe; jangan ubah alur Gudang).
- [ ] **Step 4: Jalankan** → PASS.
- [ ] **Step 5: Commit**.

### Task 14: Pensiun alat legacy dan tabel `olt_slots` — BUTUH PERSETUJUAN

**Files:**
- Delete (setelah persetujuan): `app/Services/InfrastructureLegacyBackfillService.php`, `InfrastructureLegacyDecisionService.php`, `InfrastructureReconciliationService.php`, `InfrastructureDummyResetService.php`, `app/Console/Commands/InfrastructureLegacyMappingsCommand.php`, `InfrastructureResetDummyTopologyCommand.php`, `app/Models/InfrastructureSlot.php`, `InfrastructurePort.php`, `OltSlot.php`, `app/Http/Controllers/Network/OltSlotController.php`, `database/factories/OltSlotFactory.php`
- Modify: `routes/web.php` (hapus `network.nodes.olt-slots.*`), `database/seeders/WhusnetTopologySeeder.php`, `ItemSeeder.php`
- Create: migrasi drop `olt_slots`
- Test: tes legacy (`InfrastructureLegacyDecisionTest`, `InfrastructureLegacyMappingTest`, `InfrastructureCutoverEvidenceTest`, `LegacyInfrastructureWriteCutoverTest`, `InfrastructureReconciliationTest`, `OltSlotManagementTest`) — **tanya user** dihapus atau dialihkan. `WhusnetTopologySeederTest` diubah: seeder membangun OLT → Slot → PON → ODP sebagai node.

- [ ] **Step 1: Berhenti dan minta persetujuan user** untuk D4: daftar file di atas dan nasib tiap tes legacy.
- [ ] **Step 2: Setelah disetujui**, tulis tes yang tersisa (seeder topologi) → FAIL.
- [ ] **Step 3: Hapus/alihkan sesuai persetujuan**, ubah seeder.
- [ ] **Step 4: Jalankan** hanya `WhusnetTopologySeederTest` dan tes yang diubah → PASS.
- [ ] **Step 5: Commit**.

### Task 15: Dokumentasi dan penutup

**Files:**
- Modify: `docs/plan/infrastruktur/rancangan-hierarki-infrastruktur-server-pop-mini-pop.md` (§2, §3, §17 + catatan pembalikan), `docs/database-schema.md`, `docs/TASKS.md` (ADHOC-213: apa yang dikerjakan, risiko, sisa), `docs/superpowers/specs/2026-10-24-slot-pon-sebagai-node-design.md` (koreksi D1–D5), `docs/plan/infrastruktur/manual-book-penataan-infrastruktur.md`

- [ ] **Step 1: Perbarui dokumen** sesuai hasil implementasi (bukan rencana).
- [ ] **Step 2: Jalankan semua tes file yang disentuh plan** (daftar per Task, bukan full suite) → PASS.
- [ ] **Step 3: `vendor/bin/pint --dirty --format agent`**, `npm run build`.
- [ ] **Step 4: Commit** `docs: ADHOC-213 Slot/PON sebagai node`.

---

## Self-Review

- **Spec coverage:** §2.1–2.4 → T1, T2, T10; §3 → T5, T14; §4 → T5, T6; §5 → T9; §6 → T4; §7 → T11, T12; §8 → T11 (query log), T1 (indeks); §9 → tes tiap task; §10 → T5 (guard), T14 (persetujuan), T15 (docs). Tidak ada requirement tanpa task.
- **Penyimpangan dari spec** dikumpulkan di tabel D1–D5 untuk disetujui.
- **Konsistensi tipe:** `createPort(NetworkNode $slot, int $number, array)` dipakai T3, T4; `PonCapacityService::summary` tanpa `unverified` dipakai T9, T11; `uplinkPon()` menggantikan `activeUplinkFor` (T2, T8).
- **Proporsi:** tiap task memuat tanda tangan, nama tes, dan nilai spec; tidak ada badan kode kecuali pola migrasi yang menunjuk file contoh.
