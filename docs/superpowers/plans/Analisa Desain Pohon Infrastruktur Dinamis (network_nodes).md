# Analisa Desain Pohon Infrastruktur Dinamis (network\_nodes)

12 Oktober 2026 · Dasar analisa: plan dan spec aset-infrastruktur-lokasi (2026-10-09)

## Ringkasan

Usulan untuk tidak menyimpan cabang di tiap node, lalu menurunkannya dengan menelusuri induk ke atas, benar arahnya. Memindahkan folder induk cukup mengubah satu baris, dan semua anaknya ikut pindah. Setelah membaca spec dan plan, ada satu celah yang lebih dalam dari `root_pop_id`, beberapa efek samping yang perlu diputuskan, dan beberapa bagian spec yang masih hardcode.

## 1. Celah utama: OLT punya dua representasi

Spec §1 dan §2.3 menyatakan OLT sama dengan Mini POP di tabel `pops`, tetapi spec juga mendefinisikan `olt` sebagai `network_node_type`. Karena `parent_id` hanya menunjuk ke `network_nodes`, memindahkan ODC-1 dari OLT A ke OLT B berarti:

- Kalau A dan B sama-sama node bertipe `olt`: yang berubah `parent_id`. Aman.
- Kalau A dan B adalah Mini POP: yang berubah `root_pop_id`, yaitu kolom yang ingin dihapus.

Jadi analogi folder bocor di titik yang paling sering dipakai. Klaim spec §3.2 bahwa `root_pop_id` tidak berubah sepanjang hidup node juga bertabrakan langsung dengan tujuan ini.

**Rekomendasi: aturan folder-di-drive.**

- Rename `root_pop_id` menjadi `anchor_pop_id`, nullable.
- Setiap node berada di tepat satu tempat: di dalam node lain (`parent_id` terisi) atau langsung di sebuah POP (`anchor_pop_id` terisi, `parent_id` null).
- Hanya node puncak yang punya anchor. Semua turunan tidak menyimpan cabang sama sekali.

Hasilnya, memindahkan subtree selalu satu baris, baik antar-node maupun antar-Mini POP.

```php
// NetworkNodeService::move(NetworkNode $node, ?NetworkNode $newParent, ?Pop $newPop)
// tepat satu dari $newParent / $newPop; tolak jika $newParent adalah descendant dari $node
```

## 2. Cara menurunkan cabang

Cabang sebuah node didapat dengan naik sampai node puncak, mengambil `anchor_pop_id`, lalu kalau itu Mini POP, mengambil `parent` Pop-nya lewat relasi `pops` yang sudah ada.

```sql
WITH RECURSIVE up AS (
  SELECT id, parent_id, anchor_pop_id FROM network_nodes WHERE id = ?
  UNION ALL
  SELECT n.id, n.parent_id, n.anchor_pop_id
  FROM network_nodes n JOIN up ON n.id = up.parent_id
)
SELECT anchor_pop_id FROM up WHERE anchor_pop_id IS NOT NULL;
```

SQLite (untuk test) dan MySQL 8+ mendukung recursive CTE.

**Performa.** Pengecekan satu node aman. Yang perlu hati-hati adalah query daftar dan scope:

- Halaman `/network/nodes`, `getOdpSummary()` (Red Zone), dan tree picker perlu menjawab "node mana saja di cabang yang boleh saya akses". Itu arah sebaliknya, turun dari anchor, dan harus berupa satu CTE, bukan resolver per baris (N+1).
- Bungkus semuanya di satu API, misalnya `NetworkNode::scopeInPops()` dan `->resolvePop()`. Kalau suatu hari lambat, isinya bisa diganti ke materialized path atau closure table tanpa mengubah pemanggil.
- Jangan tambah kolom cache cabang sekarang. Itu mengembalikan masalah yang ingin dihilangkan.

## 3. Efek samping yang perlu diputuskan

1. **Ledger append-only.** `inventory_transactions` hanya punya `to/from_network_node_id`. Kalau cabang diturunkan dari posisi node saat ini, laporan "apa yang dipasang di cabang X bulan lalu" berubah begitu node dipindah. Solusi: isi snapshot `to_pop_id` dan `from_pop_id` (kolom sudah ada) saat `deploy` dan `retrieve_infra`. Ledger mencatat fakta saat kejadian, bukan hasil turunan.
2. **Log pemindahan.** Tanpa kolom cabang, jejak perpindahan hilang. Tambahkan tabel kecil `network_node_moves` (node, dari, ke, oleh siapa, kapan).
3. **Guard scope saat memindah.** Aktor harus punya scope di asal dan di tujuan. Untuk lintas cabang, pertimbangkan permission terpisah.
4. **Efek ke hal lain yang ikut cabang.** Cek apakah ada yang menurunkan identitas dari cabang node: task O-REQ/INFR yang masih terbuka di cabang lama, nomor dokumen, atau CID.

## 4. Frekuensi pindah: dalam satu cabang atau lintas cabang

Desainnya tidak bergantung pada jawaban pertanyaan ini. Skemanya sama untuk kedua kasus, hanya guard-nya yang berbeda.

- **Jarang lintas cabang:** cukup larang lintas cabang di `move()`. Itu satu aturan di service, bukan skema, dan bisa dilonggarkan nanti.
- **Sering lintas cabang:** butuh permission khusus, log pemindahan, dan penanganan task yang masih terbuka (poin 2 sampai 4 di bagian 3).

## 5. Bagian lain di spec yang masih hardcode

- **Filter `node_type.code = 'odp'`** di `getOdpSummary()` dan migrasi data. Ganti dengan flag di `network_node_types`, misalnya `has_ports` dan `monitors_capacity`, supaya Red Zone bisa berlaku untuk ODC atau tipe baru tanpa mengubah kode.
- **Batas 128 port dan ambang Red Zone** (`addPorts()`). Pindahkan ke kolom per tipe (`max_ports`, `red_zone_threshold`).
- **Aturan tipe mana boleh di bawah tipe mana.** Spec sekarang membolehkan ODP di bawah Core Router. Tambahkan tabel `network_node_type_rules` (`parent_type_id` nullable berarti boleh di puncak, plus `child_type_id`) yang diedit admin. Tree picker dan validasi `move()` memakainya. Kalau ingin bebas total, tabel ini boleh kosong yang berarti tanpa batasan.
- **Item mana boleh dipasang di tipe node mana.** Sekarang hanya dijaga `OwnershipMode`. Pivot kategori item ke tipe node bisa ditunda.

## 6. Dampak ke plan

- **Spec §2.3, §3.2, §7:** ganti `root_pop_id` dengan `anchor_pop_id` dan aturan XOR.
- **Task 4:** migration, factory, dan `breadcrumbLabel()` memakai resolver.
- **Task 6:** migrasi data `odps.pop_id` menjadi `anchor_pop_id`.
- **Task 8:** `assertNetworkNodeInScope()` tidak lagi "1 lookup", jadi memakai resolver (cache per request).
- **Task 11:** query `$nodeOptions` yang memakai `root_pop_id` diganti `scopeInPops()`.
- **Task baru:** `NetworkNodeService::move()`, log pemindahan, dan test (siklus, XOR, scope asal dan tujuan).
