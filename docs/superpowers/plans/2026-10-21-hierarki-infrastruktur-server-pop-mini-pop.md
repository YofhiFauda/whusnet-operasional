# Rencana Implementasi Hierarki Infrastruktur Server–POP–Mini POP

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans to implement this plan task-by-task. Checklist memakai - [ ].

**Goal:** Implementasi Server → POP → Mini POP → OLT → Slot → PON → ODP → ONT pelanggan dan Mini POP → Switch → Slot → Port.

**Architecture:** Satu proyeksi UI dari POP, network node, slot/port kanonis, koneksi, pelanggan, dan ledger Gudang. Identitas posisi tetap saat unit fisik diganti. Resolver lokasi dan scope yang sama dipakai semua jalur.

**Tech Stack:** Laravel 13, PHP 8.4, PHPUnit 12, Blade/JavaScript aplikasi, database relasional.

**Spec:** docs/plan/infrastruktur/rancangan-hierarki-infrastruktur-server-pop-mini-pop.md dan docs/plan/infrastruktur/penjelasan-infrastruktur-baru.md. Bagian 16 rancangan mengikat jika bertentangan dengan bagian sebelumnya.

**Status:** Dokumen post-MVP. Sprint aktif tetap S8.10-T003 (FOP Notification Dashboard). Sebelum eksekusi, jadikan infrastruktur task aktif sesuai docs/TASKS.md. Rencana ini tidak mengubah kode, skema, data, atau task aktif.

## Scope Check

| Aspek | Ketetapan |
|---|---|
| Modul | POP, Master Node, Peta, Slot/Port, koneksi, Task FOP, laporan O-REQ/INFR-REQ, Gudang, instalasi pelanggan, POP scope. |
| PRD | Pelanggan tetap pusat sistem. Billing, invoice, payment, harga layanan, status tagihan, dan role matrix tidak diubah. |
| File | File implementasi/test disebut pada tiap task; dokumentasi audit dan task tracker baru diubah ketika task implementasi aktif. |
| Batas | Tanpa integrasi router/OLT, SNMP, monitoring, role per cabang atau fitur billing baru. |
| Acceptance | Hierarki tepat, subtree aman, aset/ledger tepat, kabel hanya sisa roll, kapasitas PON benar, migrasi aman dan rekonsiliasi nol. |

## Global Constraints

- Baca AGENTS.md, semua dokumen wajib, sibling file, dan dokumentasi paket dengan Laravel Boost search-docs sebelum coding. Gunakan Artisan, PHPUnit class, dan Pint untuk PHP.
- POP/Mini POP tetap di pops; OLT/Switch/ODP di network_nodes; Slot/Port punya model posisi; ONT dari assignment dan perangkat pelanggan. Jangan buat node POP/ONT duplikat atau Mini POP fiktif.
- Gunakan FK ID stabil. Parent/lokasi berbeda dari sambungan fisik. Kode/nama/SN hanya label, bukan dasar join. Server di atas POP dapat berupa proyeksi tanpa siklus parent database.
- Semua mutasi atomik, memakai lock serial/roll/port ketika diperlukan; ledger dan audit append-only. Pertahankan action_key dan mutation_hash untuk idempotensi.
- Scope efektif = izin POP aktor ∩ Cabang task ∩ subtree akar scope task. Terapkan pada lookup, baca, tulis, detail, breadcrumb, pencarian, ekspor, sumber/tujuan koneksi, dan koreksi.
- Serial lama cabut/ganti hanya yang terpasang di posisi tepat. Serial baru hanya dari custody tim dan kategori cocok. Aset langsung dan ringkasan turunan ditampilkan terpisah.
- Migrasi data nyata aditif dan reversible, legacy ambigu menunggu keputusan operator. Reset hanya topologi yang terverifikasi dummy, tanpa pelanggan/stok/transaksi/histori nyata.
- Jangan tutup jalur tulis lama pada data nyata sebelum tujuh bukti §16.4 lengkap, termasuk selisih nol satu siklus tutup periode penuh.
- Pada setiap task: buat tes perilaku yang gagal, jalankan gagal, implementasi kecil, jalankan tes sampai lulus, Pint, lalu commit hanya bila task aktif dan review selesai.

## Review Focus

1. Request dimanipulasi: akar null, anak setelah Dikosongkan, ID dari induk lain, ODP di bawah Switch (Task 4, 8).
2. Data POP bocor melalui lokasi, SN, breadcrumb, pencarian, ekspor, atau ujung koneksi (Task 4, 8, 10).
3. Serial di dua lokasi, cabut dari posisi saudara, deploy Mini POP/Slot/Port tanpa FK, company_asset kompatibel tertolak (Task 6, 8).
4. Roll negatif pada aksi ulang/konkuren, harga berubah, saldo kabel terpasang, atau qty non-kabel rusak (Task 7, 8).
5. Legacy ambigu terhubung otomatis, status verifikasi palsu, histori hilang, atau cutover prematur (Task 1, 3, 11).

---

## Fase A — Audit, posisi dan scope

### Task 1 — Audit data read-only

**Files:** Baca app/Models/NetworkNode.php, OltSlot.php, InventorySerial.php, InventoryTransaction.php; database/seeders/NetworkNodeTypeSeeder.php dan WhusnetTopologySeeder.php. Saat implementasi aktif buat docs/plan/infrastruktur/audit-transisi-hierarki-infrastruktur.md.

- [ ] Catat baseline per POP: POP/Mini POP, OLT, aggregation_switch, olt_card, pon_port, ODP, slot, port, assignment aktif, aset DEPLOYED, roll, transaksi, audit. Simpan query, waktu dan lingkungan.
- [ ] Daftar orphan/duplikat, OLT/Switch langsung di POP Cabang, uplink atau slot ambigu; tiap baris nyata punya ID asal, POP, alasan dan keputusan pemetaan operator.
- [ ] Buktikan klasifikasi dummy/nyata beserta referensi pelanggan/stok/ledger; jika nyata, reset ditolak. Simpan baseline jumlah ODP, pelanggan terhubung, port aktif, aset per POP.
- [ ] Review hasil read-only sebelum Task 2–3.

### Task 2 — Model kanonis Slot, PON, Port Switch

**Files:** Buat database/migrations/2026_10_21_000001_create_infrastructure_slots_and_ports.php, app/Models/InfrastructureSlot.php, InfrastructurePort.php, tests/Feature/Network/InfrastructurePositionTest.php. Ubah NetworkNode, NetworkNodeTypeSeeder dan aturan placement.

**Kontrak:** infrastructure_slots ber-FK network_node_id dan nomor unik per perangkat; kind, code, status, capacity dan capacity_unit. Kode infrastruktur POP/Mini POP dan node adalah identitas lokasi stabil; kode Slot/Port ditelusuri ke FK dan nomor parent, dengan keunikan sesuai parent. infrastructure_ports ber-FK slot dan nomor unik per slot; kind pon/switch, code, status, capacity, capacity_unit, red_zone_threshold. PON hanya di Slot OLT, Port Switch hanya di Slot Switch. Slot boleh tanpa kartu. Switch port tetap memakai Slot 1 berlabel internal/logis; Switch modular memakai slot fisik. Tipe switch_mini_pop memiliki has_ports=true dan placement Mini POP eksplisit. aggregation_switch tetap terbaca untuk histori tetapi tak ditawarkan lagi.

- [ ] Tes gagal banyak OLT/Switch per Mini POP, nomor Slot/Port unik pada parent, PON di Switch ditolak, Port Switch di OLT ditolak, Slot kosong sah.
- [ ] Jalankan tes gagal; buat migrasi/model/guard placement. network_node_ports lama tetap untuk port ODP, bukan sumber port OLT/Switch baru.
- [ ] Jalankan tes sampai lulus; Pint PHP.

### Task 3 — Koneksi, assignment dan mapping legacy

**Files:** Buat database/migrations/2026_10_21_000002_create_infrastructure_connections_and_mappings.php, app/Models/InfrastructureConnection.php, tests/Feature/Network/InfrastructureConnectionTest.php dan InfrastructureLegacyMappingTest.php; ubah service port/assignment ODP terkait.

**Kontrak:** Koneksi memakai FK posisi sumber/tujuan dan status verifikasi, terpisah dari parent. Satu PON bisa melayani banyak ODP; satu ODP maksimal satu uplink PON aktif terverifikasi. Sesudah Port Switch, tujuan adalah koneksi fisik tersendiri, bukan anak ODP. Port ODP maksimal satu pelanggan aktif, release menjaga histori. Mapping legacy menyimpan tipe/ID asal, ID baru, bukti dan keputusan. Status belum didata, kosong terverifikasi, terpakai terverifikasi, terpakai belum terverifikasi, rusak/nonaktif berbeda.

- [ ] Tes gagal PON banyak ODP, dua uplink ODP aktif ditolak, koneksi belum diverifikasi tidak dihitung, dua pelanggan pada port ODP ditolak, Switch tidak membuat ODP anak.
- [ ] Jalankan tes gagal; implementasikan FK, unique aktif, transaksi, audit, dan guard move/delete untuk posisi dengan aset/koneksi.
- [ ] Backfill olt_slots, kartu, pon_port, port, assignment hanya jika FK/nomor unik membuktikan hubungan; jangan pindah OLT/Switch POP Cabang otomatis atau buat Mini POP fiktif. Ambigu masuk daftar manual.
- [ ] Uji forward/rollback migrasi pada fixture nyata/ambigu; histori lama tetap terbaca; tes lulus.

### Task 4 — Resolver lokasi dan akar scope Task FOP

**Files:** Buat app/Support/InfrastructureLocation.php, app/Services/InfrastructureLocationResolver.php, database/migrations/2026_10_21_000003_add_infrastructure_scope_to_fop_tasks.php, tests/Feature/FopTaskInfrastructureScopeTest.php. Ubah FopTask, FopTaskController dan service POP scope.

**Kontrak:** Lokasi akhir = jenis posisi + FK ID tepat. fop_tasks.pop_id tetap konteks Cabang; scope root menunjuk POP/Mini POP/node/Slot/Port/ODP. Simpan snapshot dan audit perubahan scope agar laporan lama tak berubah tafsir. Resolver menurunkan ancestor berbasis ID. Scope OLT A mencakup OLT A dan semua Slot/PON/ODP turunannya, menolak saudara/induk. Scope POP dapat berhenti di POP.

- [ ] Tes gagal root kosong, scope POP/Mini POP/OLT/Slot/Switch, dua Cabang, perubahan scope setelah assignment, laporan lama memakai snapshot lama.
- [ ] Jalankan tes; buat resolver, FK, validasi dan audit; semua lokasi dalam aksi harus lolos actor + task + subtree.
- [ ] Tes lulus; guard move lintas Cabang dan delete posisi dengan aset/koneksi aktif.

### Task 5 — Kapasitas PON manual dan Red Zone

**Files:** Ubah InfrastructurePort, controller/form PON, tampilan kapasitas, audit; buat tests/Feature/Network/PonCapacityTest.php.

**Keputusan pengguna 21 Oktober 2026:** Kapasitas PON bersatuan pelanggan aktif. Hitung satu pelanggan dengan assignment port ODP aktif pada koneksi ODP→PON aktif terverifikasi sebagai satu unit. Banyak ONT pada pelanggan sama tetap tampil semua tetapi tidak menggandakan kapasitas; dua assignment aktif pelanggan yang sama adalah konflik data untuk ditolak/direkonsiliasi. Kapasitas ODP bersatuan port pelanggan, terpisah. Red Zone = pelanggan aktif terverifikasi / kapasitas PON manual dibanding ambang PON eksplisit. Kapasitas boleh naik/turun oleh pengguna berizin, tetapi penurunan di bawah pemakaian ditolak dan diaudit. NetworkNode::isInRedZone() dan monitors_capacity node pon_port lama bukan sumber rumus baru.

- [ ] Tes gagal 0 pemakaian, tepat ambang, di atas ambang, penuh, kapasitas belum terdata, pengurangan di bawah pelanggan aktif, banyak ONT satu pelanggan, koneksi belum terverifikasi, actor tanpa izin.
- [ ] Jalankan tes; implementasikan query pemakaian, validasi, audit sebelum/sesudah dan label unit pelanggan aktif. UI tidak mengklaim Red Zone jika kapasitas/ambang belum valid.
- [ ] Jalankan tes sampai lulus.

---

## Fase B — Gudang, kabel, laporan

### Task 6 — Lokasi serial dan ledger dua arah

**Files:** Buat database/migrations/2026_10_21_000004_add_exact_infrastructure_asset_locations.php, tests/Feature/Warehouse/ExactInfrastructureAssetLocationTest.php. Ubah InventorySerial, InventoryTransaction, Pop, NetworkNode, InventoryService, InfrastructureMaterialService, aturan kompatibilitas item.

**Kontrak:** inventory_serials.current_mini_pop_id untuk DEPLOYED langsung di Mini POP; current_network_node_id, current_infrastructure_slot_id, current_infrastructure_port_id untuk posisi lain. Gudang current_pop_id, custody, pelanggan dan tiap lokasi jaringan saling eksklusif. Ledger from/to Mini POP/node/Slot/Port punya FK tepat, termasuk to_mini_pop_id untuk deploy langsung di Mini POP; to_pop_id tetap snapshot Cabang. Tambah Pop::deployedSerials() dan aset langsung di detail Slot/Port. Item company_asset seperti Switch/SFP yang memang cocok lokasi perlu jalur custody→deploy eksplisit, tanpa bypass guard status.

- [ ] Tes gagal Gudang→custody→Mini POP→cabut, deploy langsung OLT, kartu Slot 1 tidak dapat dicabut dari Slot 2, Port Switch FK tepat, SN tak bisa dua tempat, item salah kategori ditolak.
- [ ] Jalankan tes; migrasi aditif, validasi eksklusif, lock, deploy/retrieve/replace/move/correction dan audit. Wrapper legacy hanya selama cutover.
- [ ] Verifikasi ledger from/to, status, Cabang snapshot; tes lulus, harga tidak berubah saat deploy.

### Task 7 — Kabel sebagai saldo sisa roll

**Files:** Ubah InventoryService, InfrastructureMaterialService, InventoryRoll, tipe transaksi, TaskInfrastructureAction; buat tests/Feature/Warehouse/InfrastructureCableConsumptionTest.php.

**Kontrak:** Aksi kabel menyimpan lokasi kerja terdalam, POP/Mini POP area induk, latitude/longitude opsional, serta ujung koneksi sumber/tujuan terpisah. Konsumsi hanya mengurangi length_remaining dengan transaksi append-only. Tidak membuat infrastructure_balances kabel terpasang di node/Slot/PON/Port/ODP. Harga snapshot saat intake roll, tanpa repricing. Qty non-kabel tetap memakai aturan saldo jenisnya. Koordinat bukan pengganti FK/scope.

- [ ] Tes gagal roll 100 m dipakai 35 m → sisa 65 m; pemakaian lanjut, kurang saldo, retry/konkuren, koreksi, harga awal tetap, tidak ada saldo terpasang.
- [ ] Tes qty non-kabel dan kabel bekerja langsung di POP ketika task berscope POP dan Mini POP null.
- [ ] Jalankan tes; potong roll dengan lock, transaksi konsumsi/koreksi terlacak, validasi koordinat/ujung dan scope; tes lulus.

### Task 8 — Backend laporan O-REQ/INFR-REQ

**Files:** Ubah TaskInfrastructureActionService, TaskMaintenanceController, TaskInfrastructureAction; buat database/migrations/2026_10_21_000005_add_exact_work_locations_to_infrastructure_actions.php; perluas tests/Feature/TaskInfrastructureDeployReportTest.php dan TaskFopActionsTest.php.

**Kontrak:** Lokasi kerja per baris. OLT: POP→Mini POP→OLT→Slot→PON→ODP. Switch: POP→Mini POP→Switch→Slot→Port lalu berhenti. Dikosongkan = null eksplisit dan anak null/disabled; Belum dipilih bukan null yang mengizinkan pemasangan. Akar scope minimum wajib. Anak yang disisipkan setelah null/salah ditolak server. Semua ID, SN lama, SN baru, port pelanggan, koreksi dan ujung koneksi diperiksa pada irisan scope. Dua baris boleh menarget turunan berbeda; satu baris invalid me-rollback seluruh laporan. O-REQ inspeksi nonjaringan tetap boleh tanpa lokasi.

- [ ] Tes gagal Mini POP dengan OLT null, OLT dengan Slot null, Slot dengan PON null, kabel POP saja, semua null, anak paksa, ODP di Switch, OLT B dalam task OLT A, dua baris sah berbeda.
- [ ] Tes gagal SN lama saudara, SN baru di luar custody, item tak kompatibel, ujung/koreksi luar scope, rollback jika satu baris gagal.
- [ ] Jalankan tes; FK lokasi kerja dan refactor semua jalur controller termasuk form tunggal legacy agar guard sama. Pertahankan action_key/mutation_hash dan audit.
- [ ] Tes lulus; Pint.

### Task 9 — UI Task FOP dan dropdown laporan

**Files:** Ubah resources/views/tasks/infrastructure-report.blade.php, partials/infra-action-rows.blade.php, partials/infra-action-review.blade.php, resources/views/tasks/maintenance-report.blade.php, form Task FOP, endpoint opsi di routes/web.php; tambah tes feature form/lookup.

- [ ] Tes gagal opsi dua Cabang, Mini POP dalam actor/Cabang scope, perangkat dalam Mini POP, Slot dalam perangkat, PON/Port dalam Slot, ODP dalam PON; tidak ada ODP di Switch.
- [ ] Endpoint opsi memakai resolver server, bukan mengirim semua data lalu menyaring browser. Ganti induk menghapus anak, SN lama, port, koneksi; Dikosongkan membuat semua anak null/disabled; Mini POP akar tidak punya Dikosongkan.
- [ ] Form Task FOP menyimpan scope terdalam dengan breadcrumb/audit; aksi jamak punya target masing-masing. Tampilkan aset langsung terpisah dari turunan; SN cabut/ganti tepat, SN pasang custody.
- [ ] Tes feature lulus; build frontend bila relevan; uji manual jalur OLT dan Switch, termasuk request buatan ditolak server.

---

## Fase C — Proyeksi dan cutover

### Task 10 — Proyeksi tunggal Master Node, Peta, Inventaris

**Files:** Buat app/Services/InfrastructureHierarchyService.php, tests/Feature/Network/InfrastructureHierarchyTest.php. Ubah InfrastructureMapService, NetworkNodeController, InfrastructureMapController, OltSlotController, resources/views/network/nodes/, resources/views/network/map/, tampilan inventaris, routes/web.php; perluas tests/Feature/Network/InfrastructureMapTest.php.

**Kontrak:** Satu proyeksi berscope menampilkan Server, POP Pusat/Cabang, Mini POP, OLT/Switch, Slot, PON/Port, ODP, ONT. ONT dari assignment pelanggan dan perangkat INSTALLED; semua ONT satu pelanggan terlihat, tanpa SN tampil ONT belum tercatat. SN ONT perlu customers.detail.devices.view_sensitive. Peta membedakan garis lokasi dan koneksi, menandai belum terverifikasi; Slot/PON/Port/ONT tak punya pin mandiri. Klik detail tanpa mutasi. Detail: breadcrumb, status, koneksi, kapasitas dan unit (slot perangkat, port Slot, pelanggan PON, port pelanggan ODP), aset langsung/turunan, riwayat task/ledger. Koordinat utama berada di POP/Mini POP dan node fisik; Slot/Port mewarisi titik parent. Tab Inventaris menunjukkan barang, SN/lot/qty, lokasi tepat, teknisi, task, dan transaksi asal. Tambah kontekstual: Mini POP→OLT/Switch, perangkat→Slot, Slot OLT→PON, PON→ODP, port ODP→pelanggan; edit identitas tidak diam-diam memindah parent.

- [ ] Tes gagal dua Cabang, banyak OLT/Switch per Mini POP, Switch berakhir Port, Slot kosong, PON banyak ODP, ODP banyak pelanggan, banyak ONT/tanpa SN, belum terverifikasi, tanpa pin subposisi.
- [ ] Tes actor satu Cabang tak melihat node, aset, SN, breadcrumb, pencarian, ekspor, uplink lain; SN ONT ditutup tanpa permission sensitif.
- [ ] Jalankan tes; buat proyeksi dengan eager loading dan gunakan di Master Node/Peta/inventaris; nama/kode jalur diturunkan dari FK.
- [ ] Tes/build relevan lulus; bandingkan empat metrik Task 1 per POP.

### Task 11 — Seeder, legacy, rekonsiliasi dan rollback

**Files:** Ubah NetworkNodeTypeSeeder, WhusnetTopologySeeder, NetworkInfrastructureExampleSeeder bila ada, semua penulis node/slot/port legacy, tes seeder/migrasi; update audit Task 1 dan docs/TASKS.md hanya saat implementasi aktif.

- [ ] Tes gagal seeder baru membuat switch_mini_pop di Mini POP dengan Slot 1/Port; tak membuat aggregation_switch, kartu OLT node atau PON node baru; tipe lama tetap terbaca.
- [ ] Audit penulis olt_slots, olt_card, pon_port, aggregation_switch, form legacy dan deploy kabel ke balance. Matikan jalur tulis lama setelah pengganti terbukti, tanpa menghapus histori.
- [ ] Dummy terverifikasi: reset hanya baris topologi yang terdaftar, tes pelanggan/stok/transaksi/histori nyata aman. Data nyata: mapping eksplisit, review operator atas ambigu, migrasi aditif, rollback membaca legacy.
- [ ] Catat tujuh bukti §16.4: (1) tidak ada tulis slot/kartu/PON lama; (2) serial/kabel tepat; (3) Master Node/Peta satu proyeksi; (4) Task/report satu jalur; (5) seeder baru; (6) selisih ODP/pelanggan/port aktif/aset per POP nol satu siklus tutup periode penuh; (7) audit/histori lama terbaca.
- [ ] Jalankan tes terarah, Pint, build dan uji manual. Jangan nyatakan cutover nyata selesai sebelum tujuh bukti tertulis. Update task tracker sesuai protokol setelah task selesai.

## Matriks cakupan spesifikasi

| Bagian | Task |
|---|---|
| §1–2 struktur dan sumber data | 2, 3, 10 |
| §3 relasi lama dan koneksi | 1, 3, 11 |
| §4 integritas, stok, keamanan | 2–4, 6–8, 10 |
| §5–6 Master Node, Peta, inventaris | 9–10 |
| §7–9 transisi, acceptance, batas | 1, 11, Global Constraints |
| §10.1–10.2 Dikosongkan dan dropdown | 4, 8, 9 |
| §10.3–10.4 subtree dan filter barang | 4, 6, 8, 9 |
| §11 Mini POP langsung dan kabel | 6–8 |
| §12 identitas, koordinat, kapasitas | 2, 5, 7, 10 |
| §13–15 dampak aplikasi dan acceptance | 4–11 |
| §16.1 FK lokasi dan ledger | 6, 8 |
| §16.2 Switch Mini POP | 2, 3, 11 |
| §16.3 PON manual dan Red Zone | 5 |
| §16.4 tujuh bukti cutover | 11 |

## Acceptance Criteria Akhir

- [ ] Dua cabang hierarki sama di Master Node dan Peta; Switch berhenti pada Port.
- [ ] Setiap aksi memiliki minimal akar scope; Dikosongkan adalah null eksplisit; O-REQ nonjaringan tetap sah.
- [ ] FK, koneksi, status, assignment, ONT, kapasitas dan satuan benar tanpa node POP/ONT duplikat.
- [ ] Scope aktor, Cabang task dan subtree melindungi semua permukaan baca/tulis, termasuk request buatan.
- [ ] Serial DEPLOYED tepat satu posisi dengan ledger dua arah; kabel hanya mengurangi roll pada harga awal.
- [ ] PON dihitung dari pelanggan aktif terverifikasi; penurunan di bawah pemakaian ditolak; Red Zone memakai rumus/ambang baru.
- [ ] Data nyata aditif dan reversible; reset dummy terbatas; histori aman; tujuh bukti cutover lengkap.
- [ ] Tes terarah, Pint, build relevan dan uji manual lulus pada masing-masing tahap.


