# Rancangan: Kolom List Pelanggan Dapat Diatur (Pilih, Urut Drag-Drop, Sort ASC/DESC)

**Status:** ✅ DIIMPLEMENTASI 2026-10-28 (ADHOC-226). Penyimpangan dari rancangan saat implementasi dicatat di §15. Di luar sprint aktif; dibuat atas permintaan eksplisit user.

**Alur kerja:** analisa → dokumentasi (dokumen ini) → implementasi **hanya setelah diinstruksikan user**.

**Riwayat dokumen:**

| Tanggal | Perubahan |
|---|---|
| 2026-10-27 | Dibuat dari brainstorming. Keputusan: "filter" = pemilih kolom; preferensi disimpan di DB per user. |
| 2026-10-27 | Pertanyaan terbuka §14 dijawab user (K1–K3). Katalog final; NIK & email tanpa pembatasan permission; Survey & Verifikasi Pelanggan **tidak diubah**. |

**Terkait:**
- `app/Http/Controllers/Concerns/RendersCustomerList.php` — query + render daftar pelanggan
- `resources/views/customers/partials/_list_table.blade.php`, `_list_filters.blade.php`, `_list_scripts.blade.php`
- [`../data-pelanggan/`](../data-pelanggan/) — modul List Pelanggan
- `docs/TASKS.md` — daftarkan sebagai ADHOC baru saat mulai implementasi

---

## 1. Permintaan User

> "Tambahkan Filter yang banyak yang bisa dipilih. Ketika dipilih maka akan masuk ke List Pelanggan, dan kolomnya bisa di drag and drop kanan atau kiri agar bisa diatur urutan kolomnya. Pada kolom tersebut ketika ditekan akan ada ASC atau DESC."

Klarifikasi user:

- "Filter" **bukan** penyaring baris, melainkan **daftar fitur/kolom** yang ingin ditampilkan di List Pelanggan (contoh: nama, nomor HP, CID, paket, dll). Dipilih → kolomnya **masuk** ke tabel.
- Preferensi (kolom terpilih + urutan) disimpan **di database per user**, bukan localStorage.

## 2. Kondisi Saat Ini

- Kolom tabel **hardcode** di `_list_table.blade.php` (10 kolom: CID, Nama, POP & Desa, Paket, No. Telepon, Jatuh Tempo, Tagihan, Berkas, Status, Aksi). Sebagian `hidden 2xl:table-cell`.
- Urutan baris **hardcode** `orderBy('customer_code')` (grup biasa); `failed`/`terminated` punya urutan sendiri. Satu-satunya sort lewat query string adalah `sort=alasan` (List Putus).
- `_list_table` dipakai List Pelanggan, Survey Pelanggan, Verifikasi Pelanggan. **List Putus & Gagal tidak pakai** (kolom berbeda) → di luar cakupan.
- Query memakai `select([...])` terbatas (Fase 5.6, anti row-bloat) — kolom baru harus masuk daftar select + eager load seperlunya.
- Tidak ada tabel preferensi user di DB.

## 3. Tujuan & Non-Tujuan

**Tujuan**
1. User memilih kolom yang tampil dari katalog kolom.
2. Urutan kolom diatur dengan drag-drop (kiri/kanan).
3. Klik header kolom → sort ASC → DESC → default.
4. Preferensi persisten per user di DB; ada tombol Reset ke default.

**Non-tujuan (YAGNI)**
- Tidak mengubah filter penyaring baris yang sudah ada (POP, wilayah, paket, kelengkapan, kolektor).
- Tidak menyentuh List Putus & List Gagal.
- Tidak mengubah Survey Pelanggan & Verifikasi Pelanggan (K3): keduanya tetap kolom tetap & urutan default seperti sekarang. Fitur ini **hanya aktif di List Pelanggan utama** (`statusGroup` kosong).
- Tidak mengubah kartu mobile.
- Tidak membuat sistem preferensi generik untuk semua tabel sekarang (tabel DB dibuat generik by `table_key`, tapi hanya satu key dipakai).
- Tidak menambah dependensi JS (drag-drop pakai HTML5 native + Alpine yang sudah ada).

## 4. Keputusan Desain

| # | Keputusan | Alasan |
|---|---|---|
| D1 | Katalog kolom tunggal di `app/Support/CustomerListColumns.php` | Satu sumber kebenaran untuk label, sortable, kolom sort, default. Whitelist validasi simpan & sort. |
| D2 | Preferensi di tabel `user_table_preferences` (JSON) | Permintaan user: per user di DB, ikut akun di semua perangkat. Satu baris per `user_id + table_key`, bukan kolom per preferensi. |
| D3 | Sort **server-side** via query string `sort` + `dir` | Sort client-side salah begitu paginasi (hanya sort halaman aktif). |
| D4 | Kolom **Aksi** fixed paling kanan, tak bisa disembunyikan/dipindah | Tanpa Aksi, baris tak bisa dioperasikan; Quick Hub & navigasi keyboard bergantung padanya. |
| D5 | Logic di `CustomerListColumnService`, controller tipis | Sesuai pembagian layer repo (logic di Service). |
| D6 | Simpan lewat endpoint JSON dengan URL **dirender server** | Aturan repo: klien tidak merakit path. URL disisipkan lewat `data-*`/`route()`. |
| D7 | Drag-drop HTML5 native + Alpine | Tanpa dependensi baru (Boost: perubahan dependensi butuh persetujuan). |

## 5. Katalog Kolom

Kolom awal (sama dengan tampilan sekarang, jadi **default tidak berubah**):

| Key | Label | Sortable | Default tampil | Catatan sort |
|---|---|---|---|---|
| `cid` | CID (ID Pelanggan) | ✅ | ✅ | `customers.customer_code` |
| `nama` | Nama Lengkap | ✅ | ✅ | `customers.full_name` |
| `pop_desa` | POP & Desa | ✅ | ✅ | join `pops.name` lalu `villages.name` |
| `paket` | Paket Internet | ✅ | ✅ | join `internet_packages.name` |
| `telepon` | No. Telepon | ✅ | ✅ | `customers.primary_phone` |
| `jatuh_tempo` | Jatuh Tempo | ✅ | ✅ | subquery `customer_services` |
| `tagihan` | Tagihan | ✅ | ✅ | subquery invoice terakhir |
| `berkas` | Berkas | ✅ | ✅ | `customers.data_completeness_status` |
| `status` | Status | ✅ | ✅ | `customers.status` |

Kolom tambahan (default **tidak tampil**, bisa dicentang user):

`email`, `nik` (`identity_number`), `gender`, `alamat`, `kecamatan`, `kota`, `mini_pop`, `kolektor`, `tgl_daftar` (`registration_date`), `sales`.

Kolom `aksi` bukan bagian katalog yang bisa diatur (D4), selalu dirender terakhir.

> Katalog final dikonfirmasi user (K1): 9 kolom awal + email, NIK, gender, alamat, kecamatan, kota, Mini POP, kolektor, tanggal daftar, sales. Tidak ada tambahan lain.

## 6. Skema Database

Migration baru `create_user_table_preferences_table`:

```
user_table_preferences
  id            bigint PK
  user_id       FK users, cascadeOnDelete
  table_key     string(64)        -- 'customers.list'
  columns       json              -- [{"key":"cid","visible":true}, ...] (urutan = urutan tampil)
  timestamps
  UNIQUE (user_id, table_key)
```

- Cascade delete aman: ini data preferensi, bukan riwayat keuangan/audit.
- Kunci kolom di JSON yang tak lagi ada di katalog **diabaikan saat baca** (tahan terhadap perubahan katalog); kunci katalog baru yang belum ada di JSON ditambahkan di akhir dengan `visible = default`.

## 7. Backend

**`CustomerListColumns`** (Support): `all()`, `defaults()`, `sortable()`, `keys()`.

**`CustomerListColumnService`**
- `forUser(User): array` — kolom efektif (urut + visible) setelah merge preferensi dengan katalog.
- `save(User, array $columns): void` — validasi: tiap key ∈ katalog, tanpa duplikat, minimal 1 kolom visible; `updateOrCreate` per `user_id + table_key`.
- `reset(User): void` — hapus baris preferensi → kembali ke default.
- `applySort(Builder, ?string $sort, ?string $dir): Builder` — whitelist dari katalog; `dir` hanya `asc|desc`; join hanya ditambahkan bila kolom sort butuh, dengan `select('customers.*')` agar kolom master tak nyampur (pola yang sama dengan sort `alasan`).

**`RendersCustomerList`**
- Tambah kolom opsional ke `select([...])` dan `with([...])` hanya bila kolomnya terpilih (hindari bloat untuk user default).
- Preferensi kolom & sort kustom **hanya diterapkan saat `statusGroup` kosong**; grup `survey`/`verification` tetap memakai kolom tetap, tanpa panel "Kolom" dan tanpa header sortable (K3).
- Ganti `orderBy('customer_code')` default dengan `applySort()` **hanya bila** `sort` valid ada di katalog; `sort=alasan` (List Putus) dan urutan `failed`/`terminated` tidak berubah.
- Kirim `$columns` (efektif) dan `$sort`/`$dir` ke view.

**Route** (static dulu, dynamic belakangan — letakkan di blok "Customers Management - Static Routes First"):
- `PUT customers/list-columns` → simpan
- `DELETE customers/list-columns` → reset

Permission: `customers.view` (preferensi tampilan pribadi, tidak membuka data baru). Tidak perlu feature/action baru.

Respons JSON; tidak ada redirect karena dipanggil via fetch dari halaman yang sama (pengecualian PRG yang wajar untuk aksi AJAX tampilan).

## 8. Frontend

- **Tombol "Kolom"** di filter bar → panel Alpine berisi checklist semua kolom katalog; centang/lepas langsung menampilkan/menyembunyikan kolom; tombol "Reset ke Default".
- **Header tabel** dirender dari `$columns`:
  - `draggable="true"`; event `dragstart/dragover/drop` memindahkan elemen di array, lalu simpan.
  - Klik header sortable → tautan ke URL dengan `sort=<key>&dir=asc`; klik lagi `dir=desc`; klik lagi hapus `sort`. Panah ▲/▼ menandai kolom aktif. URL tautan dirender server (D6), query string lain (filter, search) dipertahankan.
  - Ikon pegangan drag di header; kolom Aksi tidak draggable.
- **Body tabel**: `@foreach($columns as $col) @include("customers.partials.columns._{$col['key']}")`; satu partial kecil per kolom memuat isi sel yang sekarang inline di `_list_table`. Perilaku sel yang ada (Quick Hub di CID, badge kolektor di Nama, dsb.) dipindahkan apa adanya.
- Penyimpanan: setelah drop/centang → `fetch` PUT ke URL dari `data-save-url`; gagal → toast error dan state UI dikembalikan.
- Perubahan Blade/JS → wajib `npm run build`.

## 9. Keamanan & Scope

- Semua query tetap lewat `Customer::applyUserScope()` (POP scope tidak disentuh). Kolom tambahan (email, NIK, alamat) hanya menampilkan data yang **sudah** boleh dilihat user di List Pelanggan lewat scope yang sama.
- **Keputusan K2:** `nik` dan `email` boleh tampil untuk semua pemegang `customers.view` (tanpa permission sensitif tambahan). Kolom `identity_number` & `email` sudah ada di `select` list dan sudah bisa dicari lewat kolom Cari, jadi tidak membuka data baru.
- Endpoint simpan memvalidasi key terhadap katalog (tidak ada injeksi nama kolom ke SQL); `sort` juga whitelist, tidak pernah dirangkai mentah ke `orderBy`.
- Preferensi hanya milik `auth()->id()`; tidak ada parameter `user_id` dari klien.

## 10. Performa

- Eager load & select kolom tambahan hanya bila terpilih.
- Sort kolom turunan (Jatuh Tempo, Tagihan) lewat subquery berkorelasi; pantau pada data besar (±puluhan ribu pelanggan). Jika lambat, batasi ke kolom yang terindeks atau tandai non-sortable (kolom `sortable=false` di katalog cukup untuk mematikan).
- Preferensi dibaca 1 query per request halaman; cache tidak diperlukan.

## 11. Rencana Test

Fitur baru wajib test (PHPUnit; hanya jalankan file terdampak):

`tests/Feature/CustomerListColumnPreferenceTest.php`
- default (tanpa preferensi) = kolom sekarang, urutan sekarang
- simpan preferensi → render mengikuti urutan & visible
- preferensi user A tidak memengaruhi user B
- key tidak dikenal / duplikat / nol kolom visible → 422
- kunci usang di JSON diabaikan; kunci katalog baru ditambahkan di akhir
- reset menghapus baris, kembali default
- kolom Aksi selalu ada & terakhir

`tests/Feature/CustomerListSortTest.php` (#[DataProvider] per kolom sortable)
- ASC dan DESC menghasilkan urutan benar
- `sort` tidak valid → fallback urutan default, tanpa error SQL
- sort + paginasi + filter tetap konsisten (`withQueryString`)
- `sort=alasan` di List Putus tidak berubah
- POP scope tetap berlaku saat sort/join aktif (user scope terbatas tak melihat pelanggan cabang lain)

Regresi: `CustomerListFilterKeepsStatusGroupTest` dan test List Pelanggan yang ada harus tetap lulus.

## 12. Langkah Implementasi (urutan)

1. Migration + model `UserTablePreference`.
2. `CustomerListColumns` + `CustomerListColumnService` (+ test unit/feature).
3. Integrasi `RendersCustomerList` (select/with dinamis, `applySort`).
4. Route + controller tipis (simpan/reset).
5. Pecah `_list_table` jadi partial per kolom; header dinamis.
6. Panel "Kolom" + drag-drop + sort header (Alpine).
7. `npm run build`, `vendor/bin/pint`, test terdampak.
8. Update `docs/TASKS.md` (ADHOC baru) dan dokumen modul `docs/data-pelanggan/`.

## 13. Risiko

| Risiko | Mitigasi |
|---|---|
| Sort via join mengubah hasil `select` (kolom ambigu) | `select('customers.*')` wajib saat join, seperti pola `alasan`. |
| Sort subquery lambat di data besar | Flag `sortable` per kolom; uji di data dev (±1.957 pelanggan) lalu perkirakan. |
| Memindah isi sel ke partial merusak Quick Hub / navigasi keyboard | Pertahankan `data-customer-row` dan handler CID apa adanya; uji manual keyboard ↑/↓/Enter. |
| Kolom `hidden 2xl:table-cell` lama bertentangan dengan pilihan user | Hapus pembatasan responsif per kolom; tabel sudah `overflow-x-auto`. |
| Data sensitif (NIK/email) bocor lewat kolom opsional | Lihat §9, butuh keputusan permission. |

## 14. Keputusan Pertanyaan Terbuka (dijawab user 2026-10-27)

| # | Pertanyaan | Jawaban |
|---|---|---|
| K1 | Daftar kolom | Final: CID, Nama Lengkap, POP & Desa, Paket Internet, No. Telepon, Jatuh Tempo, Tagihan, Berkas, Status, Email, NIK, Gender, Alamat, Kecamatan, Kota, Mini POP, Kolektor, Tanggal Daftar, Sales. |
| K2 | NIK & email dibatasi permission? | Tidak. Boleh tampil untuk semua pemegang `customers.view`. |
| K3 | Survey & Verifikasi Pelanggan ikut preferensi? | Tidak. Dibiarkan seperti sekarang; fitur hanya di List Pelanggan utama. |

## 15. Penyimpangan Saat Implementasi (2026-10-28)

| Rancangan | Implementasi | Alasan |
|---|---|---|
| JSON `[{"key","visible"}]` | JSON daftar key kolom **tampil**, berurutan (`["cid","nama",...]`) | Kolom tak terdaftar otomatis tersembunyi; tidak perlu merge flag `visible`. Kunci usang dibuang saat baca, kosong → default. |
| Kunci katalog baru ditambahkan di akhir | Tidak otomatis; muncul sebagai kolom tersembunyi yang bisa dicentang | Sederhana, tidak mengubah tampilan user yang sudah punya preferensi. |
| Sort kolom relasi via join | Subquery korelasi di `ORDER BY` | Tanpa `select customers.*`, tanpa risiko menggandakan baris. |
| Tombol Reset di panel | Ada (tombol "Reset" di panel Kolom) | — |
| Pembatasan `hidden 2xl:table-cell` | Dihapus | Pilihan user menggantikan pembatasan responsif per kolom. |
| Panel centang | Terapkan = simpan lalu muat ulang halaman | Kolom baru butuh data & eager load dari server. |

Berkas: `app/Support/CustomerListColumns.php`, `app/Services/CustomerListColumnService.php`, `app/Http/Controllers/CustomerListColumnController.php`, `app/Models/UserTablePreference.php`, migrasi `2026_10_28_083128_create_user_table_preferences_table.php`, partial `resources/views/customers/partials/columns/_*.blade.php` dan `_list_columns_script.blade.php`.
