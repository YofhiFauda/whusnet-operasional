# Analisa Gap: Desain Peta Infrastruktur ISP vs Implementasi `network_nodes`

Dibuat: 2026-10-13. Status: **analisa, belum ada keputusan implementasi.**

Membandingkan desain `peta-infrastruktur-isp.md` / `.html` (prototipe mandiri,
data di `localStorage`) dengan yang sudah dibangun di repo: spec
`docs/superpowers/specs/2026-10-09-aset-infrastruktur-lokasi-design.md` (v3)
plus kerja yang belum di-commit per 2026-10-13 (`olt_slots`,
`network_port_assignments`, `OltSlotController`, `NetworkPortAssignmentService`).

Fokus tambahan: **setiap level struktur harus bisa menampilkan barang Gudang
yang terpasang di sana, baik perangkat aktif maupun pasif.**

Visualisasi rancangan (data fiktif): `visualisasi-rancangan-infrastruktur.html`
di folder ini, juga di https://claude.ai/artifact/Kw9gDEK488zu5pX9yZeGzw.

---

## 1. Ringkasan

Bagian akses (Cabang → Mini POP/OLT → kartu → port → ODP → pelanggan) sudah
punya fondasinya. Bagian di atas POP, rack, feeder, dan aturan susunan belum
ada. Integrasi Gudang sudah punya jalurnya (`DEPLOYED` +
`infrastructure_balances`), tetapi ada enam lubang yang akan bikin data kacau
kalau implementasi visual dikejar duluan (lihat §4).

Prototipe HTML **tidak boleh** dijadikan acuan skema. §7 di dokumen desain
memakai satu tabel `nodes` dengan `cascadeOnDelete`, tanpa POP scope, dan
menyimpan identitas perangkat (`model`, `serial`) di node. Ketiganya bertentangan
dengan aturan repo. Prototipe dipakai sebagai acuan **perilaku dan tampilan**
saja.

### 1.1 Koreksi 2026-10-13: hierarki POP tiga tingkat, bug resolver cabang

Data produksi: `WHUSNET (pusat) > Jetis, Sandya (cabang) > C1, D1 (mini_pop)`.
Cabang memang punya `parent_id` ke Pusat (dipasang `app:import-legacy-sql
--pusat-code`, dijaga `ImportLegacySqlPusatParentTest`). `MasterPopSeeder` dan
factory test masih membuat Cabang tanpa induk, sehingga test tidak pernah
melihat hierarki ini.

Akibatnya `Pop::resolveCabangId()` yang naik sampai akar mengembalikan WHUSNET
untuk semua cabang (terbukti di database: C1, Jetis, Sandya → id 1). Semua
guard lintas cabang di `NetworkPortAssignmentService`, `InventoryService`, dan
form instalasi diam-diam lolos.

**Sudah diperbaiki:** `resolveCabangId()` berhenti di POP bertipe `cabang`;
`NetworkNode::resolvePop()` memakai resolver yang sama. Test regresi:
`tests/Feature/CabangResolutionUnderPusatTest.php`.

**Belum diperbaiki, perlu keputusan user (data):**
`CustomerController::resolveCabangPopId()` adalah salinan resolver lama, dipakai
untuk namespace `persons.legacy_key` saat import legacy. Di database, seluruh
1.915 `legacy_key` ber-namespace `1` (WHUSNET), dan **42 dari 42 pelanggan
Sandya terhubung ke person yang juga dipakai pelanggan Jetis**, persis tabrakan
yang ingin dicegah komentar di method itu. Memperbaiki kodenya saja tidak
memisahkan person yang sudah tergabung. Butuh rencana perbaikan data
tersendiri.

Untuk peta infrastruktur: susunan pohon mengikuti `pops.parent_id`
(Pusat → Cabang → Mini POP). Port uplink antar-POP hanya mencatat jalur fisik,
tidak menentukan induk.

---

## 2. Pemetaan Desain ke Kode

| Desain | Di kode | Status |
|---|---|---|
| Satu pohon `parentId` | `network_nodes.parent_id` + `anchor_pop_id` (XOR, `NetworkNodeService::assertPlacement`) | Ada |
| Data Center / Pusat | `pops.type = pusat`, induk Cabang lewat `pops.parent_id` | Ada sebagai POP, bukan node |
| Core, Backbone, Aggregation | Tipe node `core_router`, `backbone`, `aggregation_switch` | Tipe ada, tapi hanya bisa di **bawah** POP |
| Layanan (BNG, Radius, Billing) | — | Tidak ada |
| POP Cabang | `pops.type = cabang` | Ada |
| OLT (ke-2 dst = Mini POP) | `pops.type = mini_pop` = OLT (keputusan v3 §2.3) | Ada, model berbeda |
| Slot chassis OLT + kartu PON | `olt_slots(pop_id, number, card_node_id)` + node tipe `olt_card` | Ada (uncommitted) |
| Port PON | Port milik node `olt_card` (`network_node_ports`) | Ada, semantik ambigu (§3.3) |
| Feeder (jumlah core, warna serat) | — | Tidak ada |
| ODC | Tipe `odc` | Ada (tidak ada di desain HTML) |
| ODP + port splitter | Tipe `odp` + `network_node_ports` | Ada |
| Drop + ONT | `network_port_assignments.customer_id` + `inventory_serials` `INSTALLED` (`customer_id`) | Ada secara implisit, bukan node |
| Port → anak | `network_port_assignments` (customer / target_node / manual_label, riwayat pasang-lepas) | Ada (uncommitted) |
| Warna utilisasi | `red_zone_threshold` per tipe, `isInRedZone()` | Sebagian (satu ambang, desain dua: 70/90) |
| Rack U | — | Tidak ada |
| Backbone Gbps + jenis link | Hanya `capacity` generik | Tidak ada |
| Status `aktif/rencana/gangguan/nonaktif` | `active` / `inactive` | Sebagian |
| Aturan tipe induk → anak | Ditunda (spec v3 §2.11) | Tidak ada |
| Hapus node + turunan | Sengaja tidak ada (`restrictOnDelete`) | Berbeda, dan memang benar |
| Kanvas topologi, zoom, kedalaman | Tree list di `index.blade.php` | Sebagian |
| Inventaris + urut utilisasi + cari serial/alamat | — | Tidak ada |

---

## 3. Gap Struktur (di luar Gudang)

### 3.1 Lapisan di atas POP

Hierarki organisasinya sudah ada: Cabang adalah anak Pusat di `pops` (§1.1).
Yang belum ada adalah jalur fisiknya: backbone mana yang menyuplai cabang
mana. POP bukan node, jadi POP tidak bisa jadi anak node Backbone, dan memang
tidak perlu.

Node pusat (Core, Layanan, Backbone) ditambatkan ke POP `pusat`. Hubungan
Backbone ke Cabang dicatat sebagai **link port**: port node Backbone di-assign
ke node `aggregation_switch` milik Cabang lewat `network_port_assignments`.
Link ini tidak mengubah induk siapa pun.

Hambatan: `NetworkPortAssignmentService::assign()` menolak target beda cabang
("Node tujuan berada di cabang berbeda."). Guard itu benar untuk ODC → ODP,
tetapi link uplink memang melintasi cabang. Perlu pengecualian yang eksplisit,
misalnya flag `is_uplink` pada tipe node, bukan sekadar menghapus guard.

### 3.2 Aturan susunan tipe

Spec v3 menunda aturan ini "sampai salah input jadi masalah nyata". Desain
HTML justru menjadikannya inti validasi (tabel §1). Begitu Feeder, kartu, dan
lapisan pusat masuk, kombinasi salah jadi mudah terjadi (ODP di bawah
`core_router`, kartu OLT di bawah ODP). Kombinasi salah juga merusak
perhitungan utilisasi berantai (§3.4). Rekomendasi: aktifkan sekarang, dalam
bentuk tabel `network_node_type_rules (parent_type_id, child_type_id)` yang
dikelola admin, sejalan dengan prinsip "tipe dikelola admin, bukan enum".

### 3.3 Semantik kartu, port, dan PON

- Seeder: `olt_card` punya `has_ports=true, max_ports=128`. Tipe `pon_port`
  punya `has_ports=false` dan tidak dipakai di kode mana pun.
- Desain: kartu punya ±16 port PON, dan **tiap port PON** menampung maksimal
  128 ONT.

Angka 128 di `olt_card` sepertinya tertukar: angka itu batas ONT per PON,
bukan jumlah port per kartu. Harus diputuskan satu arti:

- **Opsi A (disarankan):** port milik `olt_card` = port PON (`max_ports` ~16).
  Kapasitas ONT per PON dihitung dari jumlah pelanggan di subtree port itu,
  dengan batas 128 disimpan di tipe (kolom baru `max_downstream`, atau
  dipakai ulang dari `capacity`). Tipe `pon_port` dihapus dari seed.
- **Opsi B:** tiap PON jadi node `pon_port` anak kartu. Lebih berat (16 node
  per kartu) tanpa manfaat nyata.

### 3.4 Utilisasi berantai

Desain menghitung "PON 23/128 ONT", yaitu jumlah pelanggan di seluruh
turunannya. Sekarang utilisasi hanya per node (`ports_count` /
`used_ports_count`). Menghitung pelanggan per PON butuh jalan turun
PON port → assignment → node → port → … → customer. Pakai pola recursive CTE
yang sama dengan `scopeInPops()`, dan ukur ulang performanya setelah data riil
masuk (open item spec v3 §7.8).

### 3.5 OLT di Cabang ("OLT utama")

`OltSlotController::store()` menolak selain `mini_pop`. Desain menganggap OLT
pertama ada di POP itu sendiri. Kalau di lapangan Cabang memang memegang OLT
sendiri (bukan lewat Mini POP), model sekarang tidak bisa mencatatnya.
**Perlu dikonfirmasi user** (§6 Q2).

### 3.6 Lepas kartu, nonaktif, bongkar

- Tidak ada route untuk melepas kartu dari slot. Aturan desain: kartu hanya
  boleh dilepas kalau tidak ada port aktif.
- `NetworkNodeController::update()` mengizinkan `status=inactive` tanpa
  memeriksa apakah node masih punya port terpakai, aset `DEPLOYED`, atau
  saldo `infrastructure_balances`. Node nonaktif yang masih "berisi" barang
  adalah sumber data kacau.
- Status `rencana` dan `gangguan` dari desain belum ada. `rencana` penting:
  node yang direncanakan belum boleh menerima pelanggan atau deploy.

---

## 4. Integrasi Gudang: Analisa Lebih Dalam

### 4.1 Prinsip yang harus dikunci dulu: node = posisi, barang = isi

Ini keputusan yang paling menentukan, dan prototipe HTML mengambil arah yang
berlawanan.

- **Prototipe:** node *adalah* perangkatnya (`model`, `serial` disimpan di node).
- **Repo (spec v3 §2.9):** node adalah **posisi/fungsi** di jaringan. Barang
  fisik menempel ke posisi itu lewat ledger Gudang.

Model repo harus dipertahankan. Alasannya:

1. Ganti perangkat (OLT rusak diganti unit baru) tidak mengubah topologi. Node
   tetap, SN lama `retrieve_infra`, SN baru `deploy`. Riwayat tetap utuh di
   ledger.
2. Satu sumber kebenaran untuk SN, yaitu `inventory_serials`. Kalau node juga
   menyimpan `serial`/`model`, keduanya pasti menyimpang.
3. Laporan aset, opname, dan traceability Gudang langsung mencakup
   infrastruktur tanpa jalur kedua.

Konsekuensi yang wajib ditaati implementasi:

- **Jangan tambahkan kolom `model` atau `serial` ke `network_nodes`.** Label
  model di topologi diturunkan dari aset yang ter-deploy di node itu.
- Atribut fisik yang melekat pada jenis barang (tinggi rack `ru`, jumlah port
  bawaan switch, jumlah core kabel) tempatnya di **master Item**, bukan di
  node. Node hanya menyimpan atribut posisi (kapasitas logis, lokasi, status).

### 4.2 Peta barang per level struktur

| Level | Barang aktif (SERIALIZED, `INFRASTRUCTURE_ASSET`) | Barang pasif | Sumber tampilan |
|---|---|---|---|
| Pusat / Data Center | Server, UPS | Rack, PDU, patch panel | `deployedSerials` + `infrastructureBalances` node pusat |
| Core | Core router/switch | SFP (QUANTITY/SERIALIZED), patch cord | idem |
| Layanan | Server BNG/Radius/Billing | — | idem |
| Backbone | Radio, media converter | Kabel fiber backbone (ROLL), joint closure | idem; ROLL di `infrastructure_balances.lot_no` |
| POP Cabang | Aggregation switch, UPS | Rack, OTB | idem |
| Mini POP / OLT | **Chassis OLT — belum bisa** (spec v3 §7.1) | — | — |
| Kartu OLT | Kartu PON (SN) | Modul SFP PON | `olt_slots.card_node` → `deployedSerials` (sudah dipakai di `master/pop/show`) |
| Feeder | — | Kabel feeder (ROLL, meter) | Belum ada node Feeder |
| ODC | — | Kabinet ODC, splitter | `deployedSerials` / `infrastructureBalances` |
| ODP | — | Box ODP (SN atau QUANTITY), splitter | idem |
| Drop | — | Kabel drop (ROLL, dipakai saat instalasi) | `consumeFromRoll()` di instalasi, terkait pelanggan, bukan node |
| ONT | ONT/modem (`INSTALLABLE`, `INSTALLED`, `customer_id`) | — | Pelanggan dari `network_port_assignments` → `inventory_serials.customer_id` |

Klasifikasi aktif/pasif sudah ada di Item (`effective_equipment_class`:
`equipment_class_override` ?? kategori ?? `PASIF`). Tampilan per node cukup
mengelompokkan berdasarkan atribut itu, tanpa kolom baru.

Catatan level ONT: ONT **tidak** di-deploy ke node, dan memang tidak boleh
(`OwnershipMode::INSTALLABLE` vs `INFRASTRUCTURE_ASSET`, guard di
`deploySerial()`). Jadi "barang di ODP port X" = pelanggan yang menempati
port itu → SN yang `INSTALLED` ke pelanggan itu. Ini join baca, bukan relasi
baru.

### 4.3 Enam lubang yang harus ditutup sebelum UI dikejar

**L1. Chassis OLT tidak punya tempat.** Open item spec v3 §7.1. Kerja
uncommitted menambah `olt_slots` yang menempel ke `pop_id`, tetapi chassis
itu sendiri (SN, model, jumlah slot fisik) masih tidak bisa di-deploy.
Akibatnya level OLT di topologi tidak bisa menampilkan perangkat aktif
utamanya. Arah solusi:

- Node tipe `olt_chassis` ditambatkan ke Mini POP (satu per Mini POP, karena
  OLT = Mini POP).
- Chassis SN di-deploy ke node itu.
- Node `olt_card` jadi **anak** chassis (`parent_id`), bukan ditambatkan
  langsung ke Mini POP seperti yang diwajibkan `attachCard()` sekarang
  (`card->anchor_pop_id === pop->id && parent_id === null`).

**Ini harus diputuskan sebelum kerja `olt_slots` di-commit.** Kalau tidak,
semua kartu yang sudah tercatat harus dipindah belakangan. Ada juga pilihan
apakah `olt_slots.pop_id` sebaiknya jadi `olt_slots.chassis_node_id`.

**L2. Tidak ada jalur mencatat aset yang sudah terpasang di lapangan.**
`deploySerial()`, `deployQuantity()`, dan `deployRoll()` semuanya mewajibkan
`FopTask` dan SN berstatus `ISSUED` di custody teknisi. Jalur itu benar untuk
pemasangan baru. Tetapi seluruh infrastruktur yang **sudah** berdiri (OLT,
switch, ODP yang terpasang bertahun-tahun) tidak punya jalan masuk selain
membuat task palsu. Perlu jalur "Registrasi Aset Terpasang" (opname awal
infrastruktur):

- SN baru dibuat langsung berstatus `DEPLOYED` di node tertentu.
- Ledger memakai tipe tersendiri (misalnya `ADJUSTMENT` dengan alasan wajib,
  atau tipe baru `REGISTER_INFRA`), bukan menyamar sebagai `DEPLOY`.
- Permission terpisah, karena ini bisa menciptakan aset tanpa jejak
  penerimaan.

Tanpa ini, halaman topologi akan kosong dari barang untuk semua infrastruktur
lama, dan user akan tergoda mengisinya lewat cara memutar.

**L3. Barang mana boleh di node mana (open item spec v3 §7.3).**
`deploySerial()` hanya memeriksa `ownership_mode`. Kartu PON bisa di-deploy ke
ODP, kabinet ODC ke `core_router`. Untuk tampilan per level yang dapat
dipercaya, perlu aturan kompatibilitas. Bentuk paling ringan:
`network_node_type_item_categories (node_type_id, item_category_id)`, kosong =
bebas. Ini sejalan dengan aturan susunan tipe di §3.2: keduanya master data
admin.

**L4. Status node tidak memengaruhi deploy dan assignment.**
`deploySerial()` dan `NetworkPortAssignmentService::assign()` tidak memeriksa
`network_nodes.status`. Perlu aturan tunggal, misalnya
`NetworkNode::acceptsNewAttachments()`, yang ditanyai semua jalur (deploy,
assign pelanggan, assign node, pasang kartu). Pola ini sama dengan
`TaskStatus::acceptsReport()`. Sebaliknya, `inactive` hanya boleh kalau node
sudah kosong (tidak ada port terpakai, SN `DEPLOYED`, atau saldo > 0).

**L5. Kapasitas logis vs kapasitas fisik barang.** Switch 24 port yang
di-deploy ke node `aggregation_switch` tidak otomatis memberi node itu 24
port. Ada dua sumber "jumlah port":

- Node: `network_node_ports`, dibuat manual saat create / `addPorts()`.
- Item: spesifikasi barang (belum ada kolomnya).

Rekomendasi: port node tetap manual dan menjadi sumber kebenaran. Atribut Item
hanya dipakai sebagai **saran isian** saat membuat node atau saat deploy, bukan
sinkronisasi otomatis. Sinkronisasi dua arah antara item dan node adalah jenis
"dua sumber kebenaran" yang selama ini dihindari repo.

**L6. `parent_id` dan sambungan port bisa saling bertentangan.** Ditemukan
saat menyusun visualisasi. Ada dua relasi hulu-hilir: `parent_id` (posisi di
pohon) dan `network_port_assignments.target_node_id` (port mana yang
menunjuk node itu). `assignNode()` tidak mengecek keduanya cocok, sehingga
ODP bisa berada di bawah ODC-1 di pohon, sementara port ODC-2 yang
menunjuknya. Aturan yang diusulkan: `parent_id` = hulu fisik, dan port hanya
boleh menunjuk anak langsung node pemilik port (`target.parent_id =
port.network_node_id`). Pengecualiannya uplink antar-POP (switch agregasi →
chassis OLT di Mini POP, backbone → switch cabang), yang ditandai lewat flag
di tipe node. Masuk Fase 1.

### 4.4 Hal yang sudah benar dan jangan diubah

- `current_network_node_id` satu kolom untuk semua level (spec v3 §2.8).
- Snapshot cabang di ledger (`to_pop_id` / `from_pop_id`) saat deploy dan
  retrieve. Memindah node tidak mengubah laporan lama.
- `infrastructure_balances` terpisah dari `inventory_balances` (ADHOC-54).
- Adjustment yang menarik SN `DEPLOYED` sudah mengisi `from_network_node_id`
  (`InventoryAdjustmentService` ±baris 255).
- Hapus node `restrictOnDelete`. Bongkar = retrieve semua aset → lepas semua
  assignment → `inactive`. Bukan delete berantai seperti prototipe.

### 4.5 Celah kecil yang ditemukan

- `NetworkNodeController::show()` sudah me-load `deployedSerials.item` dan
  `infrastructureBalances.item`, tetapi `show.blade.php` tidak merendernya.
  Aset baru tampil di panel `index.blade.php`.
- Kartu di `master/pop/show` hanya menampilkan "Kartu terpasang / belum
  memiliki aset Gudang" (jumlah SN), bukan SN/model-nya.

---

## 5. Urutan Implementasi yang Disarankan

Urutan dipilih supaya data yang masuk lebih awal tidak perlu dimigrasi ulang.

| Fase | Isi | Alasan urutan |
|---|---|---|
| **0. Kunci keputusan** | Jawab §6 Q1–Q4. Putuskan chassis OLT (L1) sebelum `olt_slots` di-commit | Kerja uncommitted masih murah diubah |
| **1. Fondasi aturan** | Aturan susunan tipe (§3.2), arti port kartu = PON (§3.3), status `planned/active/fault/inactive` + `acceptsNewAttachments()` (L4), konsistensi parent ↔ port (L6), detach kartu (§3.6) | Semua input data sesudahnya tervalidasi |
| **2. Aset Gudang di tiap level** | Node `olt_chassis` (L1), Registrasi Aset Terpasang (L2), kompatibilitas item ↔ tipe node (L3), render aset aktif/pasif di `show.blade.php` (§4.5) | Halaman topologi butuh data barang yang lengkap dan benar |
| **3. Akses lanjut** | Tipe Feeder (port = core, warna serat dari nomor port), utilisasi berantai PON (§3.4) | Butuh fase 1 supaya rantai PON → Feeder → ODC/ODP valid |
| **4. Lapisan pusat (opsional)** | Node pusat di POP `pusat`, link uplink lintas cabang (§3.1), Gbps, rack U dari atribut Item | Paling jarang berubah, paling sedikit nilai operasional harian |
| **5. Tampilan** | Kanvas topologi ala prototipe, inventaris + urut utilisasi, pencarian global | Terakhir: tampilan hanya membaca data yang sudah benar |

Setiap fase punya spec dan plan sendiri. Fase 0–2 menyentuh Gudang dan
Network sekaligus, jadi perlu dicek terhadap sprint aktif di `docs/TASKS.md`.

---

## 6. Pertanyaan untuk User

**Keputusan 2026-10-14 (Q1-Q4, detail revisi di
`docs/superpowers/specs/2026-10-09-aset-infrastruktur-lokasi-design.md` §11):**

1. ~~Lapisan pusat dibutuhkan?~~ **Ya, tapi tanpa layer "Metro" terpisah** —
   node Core/Backbone/Server langsung ditambatkan ke POP `pusat` (WHUSNET).
2. ~~OLT utama di Cabang vs selalu Mini POP?~~ **Diganti jadi aturan tunggal:**
   Mini POP = lokasi fisik (alamat/koordinat beda dari Cabang), **bukan**
   identitas 1 OLT. OLT di gedung Cabang → chassis anak POP Cabang langsung.
   OLT di lokasi lapangan lain → chassis anak Mini POP lokasi itu. Satu
   lokasi (Cabang atau Mini POP) boleh menaungi **berapa pun** chassis —
   nambah unit OLT di titik yang sama = tambah node, bukan Mini POP baru.
3. ~~Chassis OLT di bawah Mini POP?~~ **Setuju**, dengan catatan poin 2: bisa
   juga anak POP Cabang langsung. Kartu jadi anak chassis (`attachCard()`
   perlu diubah). Tidak perlu seeder — tipe `olt_chassis` didaftarkan sekali
   di Master Tipe Node, instance dibuat manual per lokasi nyata.
4. ~~Registrasi aset lama: UI atau Excel?~~ **Manual satu-satu lewat UI**,
   pola sama dengan `odp_port` di `CustomerInstallationController`: teks
   bebas kalau node/slot belum terdaftar, jadi pilihan resmi begitu terdaftar.
5. ~~Feeder: cukup node, atau ROLL asal juga wajib?~~ **Keputusan 2026-10-14:
   Opsi B — ikut lacak stok fisik.** Feeder jadi node (`feeder`, parent OLT,
   `capacity` = jumlah core, port assignment ke ODC tujuan) **dan** dipasang
   lewat jalur Gudang yang sama dengan kabel Drop: `consumeFromRoll()` saat
   pemasangan (laporan O-REQ/INFR), mengurangi `infrastructure_balances` di
   lot ROLL asal + mencatat ledger `DEPLOY` (siapa/kapan/lot mana). Pencabutan
   kabel ikut jalur retrieve yang sama (meter sisa balik ke ROLL atau jadi
   scrap). Feeder diperlakukan sebagai barang terpasang, bukan sekadar jalur
   topologi.

---

## 7. Keputusan user (2026-10-14): urutan — akar dulu, sinkronisasi API nanti

Gap sinkronisasi API (`olt_number`/`olt_slot`/`olt_port` dari
`NetworkDeviceController` belum otomatis jadi `network_port_assignments`,
lihat `analisa-inventaris-infrastruktur.md` §HIPOTESIS AWAL — "Bagaimana slot
ODP dan OLT sebaiknya terisi?") **dicatat sebagai backlog, bukan dibatalkan.**
Implementasinya **ditunda** sampai akar masalahnya beres dulu:

- **L1** (chassis OLT belum punya tempat, §4.3) — `olt_slots.pop_id` vs
  `chassis_node_id` harus diputuskan sebelum ada data slot/kartu yang bisa
  disinkronkan dengan benar.
- **§3.3** (semantik port kartu = PON vs batas ONT per PON tertukar) —
  tanpa ini, port yang mau disamakan dari API bisa salah arti kapasitasnya.
- **§3.2** (aturan susunan tipe) — supaya hasil sinkronisasi API tidak bisa
  nyelip ke kombinasi node yang salah (ODP jadi anak OLT langsung, dst).

Ini sejalan dengan Fase 0–1 di §5 (Urutan Implementasi): kunci keputusan +
fondasi aturan jalan duluan, baru fase yang menyentuh integrasi API
(disebut di §6 Q-API — belum ada nomor resmi, tambahkan saat Fase 1 kelar).
Sampai root cause-nya tuntas, jalur isi slot/port OLT **tetap manual**
(§HIPOTESIS AWAL "Bagaimana slot ODP dan OLT sebaiknya terisi?" poin 1–3);
API cuma jadi pembanding/selisih, bukan penulis otomatis.
