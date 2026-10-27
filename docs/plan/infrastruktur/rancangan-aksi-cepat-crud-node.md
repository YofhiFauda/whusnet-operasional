# Rancangan: Aksi Cepat Tambah/Edit/Hapus Node dari List (tanpa masuk Detail)

2026-10-24 · Diminta user: tambah/edit/hapus antar node harus secepat ODP nambah kapasitas — saat ini cuma Hapus yang masih wajib masuk halaman Detail.

## Masalah

Di `resources/views/network/nodes/index.blade.php`, inspector panel kanan (Action Buttons, baris 344-390) sudah punya jalur cepat tanpa pindah halaman:

| Aksi | Lokasi sekarang | Cepat? |
|---|---|---|
| Tambah Sub-Node | Modal `openAddChildModal()` di index | ✅ |
| Tambah Kapasitas Port (ODP) | Inline form `network.nodes.ports.add` di index | ✅ |
| Edit Detail | Modal `openEditModal()` di index | ✅ |
| **Hapus Node** | **Form `network.nodes.destroy` cuma ada di `show.blade.php`** | ❌ — wajib klik "Buka Halaman Detail Penuh" dulu |

Satu-satunya aksi yang masih maksa pindah halaman adalah Hapus. Padahal route (`network.nodes.destroy`) dan permission (`network_nodes.delete`) sudah ada — kurang entry point di index.

## Scope Keputusan

- Berlaku **semua tipe node** (ODC, OLT Chassis, ODP, dst) — konsisten dengan Edit & Tambah Kapasitas yang sudah generic, bukan khusus ODP.
- **Tidak ada perubahan controller/service.** `NetworkNodeController::destroy()` tetap: soft-deactivate kalau node punya relasi, hard delete kalau node kosong (lihat `app/Http/Controllers/Network/NetworkNodeController.php:353-369`). Semua guard (lock row, audit log via `AuditLog::log()`) ikut terbawa otomatis karena cuma nambah entry point UI ke route yang sama.

## Perubahan

**File:** `resources/views/network/nodes/index.blade.php` saja.

Sisipkan tombol "Hapus Node" di Action Buttons row (setelah "Edit Detail", sebelum link "Registrasi Aset"), dibungkus `@can('network_nodes.delete')`:

```blade
@can('network_nodes.delete')
<form method="POST" action="{{ route('network.nodes.destroy', $node) }}"
      onsubmit="return confirm('Hapus node kosong? Node yang punya relasi akan dinonaktifkan.');"
      class="flex-1 sm:flex-initial">
    @csrf
    @method('DELETE')
    <button type="submit"
            class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-red-50 text-red-700 border border-red-200 hover:bg-red-100 dark:bg-red-950/40 dark:text-red-300 dark:border-red-800 dark:hover:bg-red-950/70 transition shadow-2xs cursor-pointer">
        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16" />
        </svg>
        <span>Hapus Node</span>
    </button>
</form>
@endcan
```

Teks confirm sengaja disamakan persis dengan yang ada di `show.blade.php:53` — satu pesan, satu pemahaman konsekuensi, di mana pun tombolnya diklik.

## Testing

- Tambah assertion ke `tests/Feature/OdpNetworkManagementTest.php`: index menampilkan tombol "Hapus Node" untuk user ber-permission `network_nodes.delete`, dan tidak menampilkan untuk user tanpa permission itu.
- Tidak perlu test baru untuk logic `destroy()` — sudah dicakup test existing lewat jalur `show.blade.php` (route & controller method sama, cuma beda asal klik).

## Status

**DIKERJAKAN (2026-10-26, ADHOC-218).** Tombol Hapus Node ditambah di Action Buttons row `index.blade.php`, reuse route/permission existing. 3 test baru lulus bareng 29 test existing (32 total) di `OdpNetworkManagementTest.php`.

---

# Bagian 2: Efisiensi Form/Modal Tambah & Edit Node

2026-10-24 · Lanjutan diskusi: user minta form/modal CRUD dibuat lebih cepat & efisien, bukan cuma aksi Hapus.

## Temuan Penting Lebih Dulu — Koreksi Asumsi

Cek `database/seeders/NetworkNodeTypeSeeder.php`: tipe legacy (`core_router`, `aggregation_switch`, `pon_port`, `olt_card`, `odc`) **sudah dihapus total permanen** (ADHOC-211/212, migration `2026_10_23_000003`/`000004`). Tipe resmi yang tersisa cuma **4**: `server`, `switch_mini_pop`, `olt_chassis`, `odp`. Badge warna `feeder`/`backbone`/`aggregation`/`odc` di `index.blade.php` (baris ~398-404) adalah sisa kelas CSS, bukan tipe yang hilang dari modal — jadi dugaan awal "tipe lain gak bisa ditambah lewat modal" **gugur**, bukan itu akar masalahnya.

Akar masalah sebenarnya ada di dua tempat:

### A. Modal Tambah — type-selector hardcode, tidak scale

`resources/views/network/nodes/_create-point-modal.blade.php` baris 41 hardcode array:
```blade
@foreach(['server'=>'Server','switch'=>'Switch','olt'=>'OLT','slot'=>'SLOT','pon'=>'PON','odp'=>'ODP'] as $code=>$label)
```
lalu `node_type_id` di Form A (baris 67) dipetakan manual lewat objek translasi `{server:'server',switch:'switch_mini_pop',olt:'olt_chassis',odp:'odp'}` — key singkat yang beda dari `code` asli di DB. Field "Kapasitas" cuma required/berlabel khusus kalau `createType === 'odp'` (baris 96-99), hardcode, bukan baca flag `has_ports` tipe tersebut.

Akibat: kalau admin nambah tipe node baru lewat halaman Master Tipe Node, tipe itu **tidak otomatis muncul** di modal Tambah — perlu sentuh kode ini lagi.

### B. Modal Edit — field kecantol di state tapi tidak ada input-nya

`index.blade.php` baris 1225-1238, state Alpine `editModal` nyimpen `village`, `district`, `city` — dan `openEditModal()` (baris 1323-1338) nerima ketiganya dari pemanggilan di baris 363. Tapi form modal Edit (baris 922-979) cuma me-render `code`, `name`, `status`, `address`, `latitude`, `longitude`. `village`/`district`/`city` tidak bisa diedit dari modal sama sekali — data nyangkut di state JS tanpa jalan keluar ke form.

## Rancangan Perbaikan

**Tidak ada perubahan controller.** `$nodeTypes` (tipe aktif, sudah di-order by `sort_order`) sudah dikirim oleh `NetworkNodeController::index()` ke view — cukup dipakai di modal, belum perlu ditambah variabel baru.

### A. Generalisasi type-selector Modal Tambah

- Loop tombol tipe langsung dari `$nodeTypes` (bukan array hardcode) — `createType` Alpine diisi `code` asli DB (`server`, `switch_mini_pop`, `olt_chassis`, `odp`), bukan alias singkat.
- Hidden input `node_type_id` ambil dari map `{code: id}` yang dibangun sekali di `x-data` (`nodeTypeIds` sudah ada sebagai konsep, cuma direct-mapping tanpa objek translasi lagi).
- Field "Kapasitas Port" required & teks labelnya mengikuti `has_ports` tipe yang dipilih (map tambahan `{code: has_ports}` dari `$nodeTypes`), bukan hardcode `createType === 'odp'`.
- Tombol **Slot** & **PON** tetap statis terpisah — keduanya bukan baris `network_node_types`, melainkan konsep struktural `InfrastructureSlot`/`InfrastructurePort` yang beda model sepenuhnya.
- Field **Uplink PON** tetap hardcode khusus `code === 'odp'` — itu aturan bisnis spesifik ODP (lihat validasi `uplink_pon_id` di `NetworkNodeController::store()`), bukan properti generic tipe node, sengaja tidak digeneralisasi.

Hasil: tipe node baru yang dibuat admin lewat Master Tipe Node otomatis muncul di modal Tambah tanpa sentuh Blade/JS lagi.

### B. Lengkapi field hilang di Modal Edit

Tambah 3 input baru (`village`, `district`, `city`) di form Edit, mengikuti grid style yang sudah ada (`sm:grid-cols-2`), binding ke `editModal.village/district/city` yang sudah tersedia di state. Tidak menambah kemampuan ganti tipe node (`node_type_id`) lewat modal ini — di luar scope yang diminta, dan berisiko karena ada guard "tipe tidak bisa diubah kalau node sudah punya relasi" yang butuh penanganan error terpisah (YAGNI, tidak diminta).

## File Disentuh

- `resources/views/network/nodes/_create-point-modal.blade.php` — loop type-selector, hidden `node_type_id`, kondisi field Kapasitas.
- `resources/views/network/nodes/index.blade.php` — script data (`nodeTypeIds`/`has_ports` map) + 3 input baru di form Edit.
- Controller & service: **tidak berubah.**

## Testing

Tambah assertion ke `tests/Feature/OdpNetworkManagementTest.php`:
- Submit Tambah node pakai `node_type_id` tipe non-ODP (misal `olt_chassis`) lewat form generic hasil loop — pastikan tersimpan dengan tipe benar.
- Submit Edit dengan `village`/`district`/`city` terisi — pastikan ketiganya tersimpan ke `NetworkNode` (kolom-kolom ini sudah divalidasi `locationRules()` di controller, cuma belum ada jalur UI-nya).

## Status

**DIKERJAKAN (2026-10-26, ADHOC-218).** Type-selector modal Tambah digeneralisasi dari `$nodeTypes` (+ map `nodeTypeIds`/`nodeTypeHasPorts`), field village/district/city ditambah ke modal Edit, bug stale `allowedCreationPops` (masih pakai alias `'switch'`/`'olt'` lama) ikut dibenahi. Catatan: struktur Form A modal Tambah ternyata sudah berubah dari analisa awal (ODP wajib anak PON lewat field pencarian, bukan `uplink_pon_id` select) — rancangan di atas disesuaikan ke bentuk aktual sebelum eksekusi, bukan bentuk yang dianalisa 2026-10-24. 3 test baru lulus bareng 29 test existing (32 total, 197 assertion).
