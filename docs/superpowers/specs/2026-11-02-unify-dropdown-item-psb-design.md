# Unify Dropdown Item Dipasang di Laporan PSB

Tanggal: 2026-11-02 · Status: **menunggu review user** · Modul: Task Teknisi / Instalasi (PSB)

## 1. Masalah

Form laporan instalasi PSB (`CustomerInstallationController::report()`) menampilkan **4 section dropdown terpisah** buat item yang dipasang teknisi:

| Section | Sumber data | Mekanisme custody |
|---|---|---|
| Modem / perangkat aktif | `InventorySerial::eligibleSerialsForTeam()` | per-serial (`InventorySerial.current_technician_id`, status `ISSUED`) |
| Perangkat tambahan (AP/STB) | `AdditionalDeviceCustodyService::eligibleSerialsForTask()` | per-serial, filter `item.category.is_customer_extra_device=true` |
| Perangkat pasif (RJ45, kabel pendek, dst) | `TechnicianCustody` milik tim | per-qty/batch, agregat tim |
| Kabel panjang / FO | `InventoryRoll::current_technician_id`, `length_remaining > 0` | per-roll individual |

Keempat query **sudah** custody-scoped ke tim task (bukan pilihan bebas) — ini sudah benar dan konsisten. Masalahnya murni presentasi: tiap jenis barang punya field/section sendiri yang hardcode di Blade, sehingga menambah jenis barang baru (atau sekadar menata ulang) berarti menyentuh 4 tempat berbeda, dan teknisi harus tahu dulu barang yang dia pegang masuk kategori mana sebelum bisa mulai isi laporan.

## 2. Tujuan & keberhasilan

- Teknisi mengisi laporan lewat **satu dropdown "Pilih Item"** per baris, isinya gabungan semua barang yang dia pegang (custody tim), apa pun mekanisme di baliknya.
- Baris bisa **ditambah sesuai kebutuhan** (tombol "+ Tambah Item"), bukan slot tetap per jenis barang.
- Input tambahan (qty / meter / pilih serial) muncul otomatis sesuai mekanisme item yang dipilih — teknisi tidak perlu tahu istilah "serial/batch/roll".
- **Tidak ada perubahan skema tabel** (`InventorySerial`, `TechnicianCustody`, `InventoryRoll` tetap). Tidak ada perubahan logika consumption yang sudah ada — hanya cara item dipilih dan dikirim ke controller.
- Keberhasilan: 1 teknisi bisa lapor instalasi PSB campur modem + kabel roll + pasif + tambahan dari satu komponen input, tanpa tahu 4 section lama.

## 3. Di luar cakupan

- Form lain yang masih pakai pola lama (pendaftaran, verifikasi admin, maintenance non-PSB, edit pelanggan) — **tidak disentuh** di task ini. Unify dilakukan dulu di PSB; perluasan ke form lain adalah task terpisah kalau pola ini terbukti jalan.
- Migrasi data custody existing — tidak perlu, karena sumber data (`InventorySerial`, `TechnicianCustody`, `InventoryRoll`) tidak berubah struktur.
- `ItemCategory` tidak ditambah kolom config baru di task ini (misal flag "butuh serial/qty/meter") — mapping mechanism-per-item disimpulkan dari sumber datanya sendiri (serial vs custody vs roll), bukan dari field baru di `ItemCategory`. Kalau nanti mau generalize lebih jauh (device bawaan paket, bukan cuma PSB), baru dipertimbangkan nambah config di situ.

## 4. Desain

### 4.1 Komponen UI

Di view laporan PSB (`resources/views/installations/report.blade.php` atau partial terkait), 4 section dropdown diganti komponen **"Daftar Item Dipasang"**:

- Tabel baris dinamis (Alpine `x-data`), tiap baris: 1 dropdown "Pilih Item" + area input kondisional + tombol hapus baris.
- Tombol **"+ Tambah Item"** menambah baris baru (index array naik). Tidak ada limit jumlah baris.
- Dropdown "Pilih Item" isinya 1 list gabungan dikirim dari controller (lihat §4.2), tiap opsi bawa data `item_id`, `item_name`, `category`, `mechanism` (`serial|batch|roll`), dan info ketersediaan (`available_qty`/`length_remaining`/daftar serial), lewat `data-*` attribute buat dibaca Alpine.
- Saat teknisi pilih item di satu baris, Alpine menampilkan input tambahan sesuai `mechanism`:
  - `serial` → tampil serial number yang tersedia (auto-pilih kalau cuma 1, atau sub-dropdown kalau lebih dari 1).
  - `batch` → input angka qty, dengan hint "maks tersedia: N" (divalidasi juga di server).
  - `roll` → sub-dropdown pilih roll spesifik (kalau tim pegang lebih dari 1 roll item itu) + input meter terpakai, hint "sisa: N meter".

### 4.2 Query gabungan (server, sisi tampil form)

Service baru `EligibleInstallationItemsService` (atau method baru di `AdditionalDeviceCustodyService` — keputusan final saat implementasi, tergantung mana yang lebih pas dengan tanggung jawab existing) memanggil 3 sumber data yang **sudah ada, tanpa diubah**:

1. `InventorySerial::eligibleSerialsForTeam()` — modem/perangkat aktif + perangkat tambahan (beda hanya filter kategori).
2. `TechnicianCustody` query existing per tim.
3. `InventoryRoll::current_technician_id` + `length_remaining > 0` query existing.

Hasil 3 sumber di-normalize jadi 1 collection seragam `{item_id, item_name, category_name, mechanism, ...}` dikirim ke view sebagai satu variable. Query asal **tidak digabung jadi satu SQL** — tetap 3 query/scope terpisah di model masing-masing, cuma dirapikan di 1 pemanggil servis.

### 4.3 Submit & konsumsi (server)

- Request mengirim array `items[i] = {item_id, mechanism, qty_or_meter?, serial_id?, roll_id?}`.
- `FormRequest` baru validasi per baris sesuai `mechanism`:
  - `serial`: `serial_id` wajib ada & masih berstatus `ISSUED` ke tim task ini.
  - `batch`: `qty` wajib `> 0` dan `≤ qty_remaining` custody tim buat item itu (dihitung ulang di server, bukan percaya nilai dari klien).
  - `roll`: `roll_id` wajib milik tim, `meter` wajib `> 0` dan `≤ length_remaining` roll itu.
  - Baris kosong (tidak ada `item_id`) **diskip**, bukan error — laporan tanpa device line tetap valid kalau alur lama memang mengizinkannya (perlu dicek ulang saat implementasi, bukan diasumsikan berubah).
  - Item yang sama dipilih di lebih dari satu baris dengan mechanism `batch`/`roll`: qty/meter **dijumlah dulu** sebelum dibandingkan ke sisa custody, supaya tidak lolos lewat split baris.
- Service orchestrator (baru) loop tiap baris valid, dispatch ke method consumption **yang sudah ada** sesuai `mechanism` (serial → issue/install existing flow; batch → `consumeFromCustody()`; roll → `consumeFromRoll()`). Tidak menulis ulang logic consume.
- Seluruh baris dibungkus **1 DB transaction** — kalau satu baris gagal (misal stok berubah di antara render form dan submit), semua baris dibatalkan, user diarahkan balik ke form (`back()->withErrors()->withInput()`, sesuai `PRG_REDIRECT_CONVENTION.md`) dengan pesan error menyebut baris mana yang gagal.
- Locking saat consume reuse mekanisme lock yang sudah dipakai `consumeFromCustody()`/`consumeFromRoll()` kalau sudah ada (`lockForUpdate()`) — tidak bikin pola lock baru.

### 4.4 Testing

Test baru (nama sesuai gejala, bukan kelas, ikut konvensi repo):

- Submit laporan PSB dengan baris campur ketiga mechanism sekaligus → semua ter-consume benar, custody berkurang sesuai.
- Submit qty batch lebih dari `qty_remaining` → ditolak, custody tidak berubah.
- Submit serial yang bukan milik tim task → ditolak.
- Submit dengan baris kosong di tengah array → diskip, baris lain tetap diproses.
- Submit item sama di 2 baris roll/batch berbeda yang totalnya melebihi sisa → ditolak (bukan lolos karena dicek per-baris).

Test mechanism lama (`InstallationRollConsumptionTest`, `MaintenanceGantiPerangkatTambahanTest`, dll) **tidak dihapus** — mechanism consume-nya tidak berubah, hanya dipanggil dari jalur input baru.

## 5. Risiko & catatan implementasi

- Scope kecil secara data (tidak ada migrasi skema), tapi Blade + Alpine + FormRequest + 1 service baru cukup untuk disiplinkan lewat TDD per komponen (service dulu, baru UI).
- Pastikan `view:cache`/Alpine tetap kebangun lewat `npm run build` setelah ubah Blade — kalau tidak, `public/build` lama bikin interaksi dropdown dinamis mati tanpa error jelas.
- Perlu baca ulang `docs/fop-task/analisa-sync-execution-task.md` kalau laporan PSB ini juga menyentuh sinkronisasi Task/FopTask (belum dicek detail — task ini fokus ke input device-nya saja, bukan status task).
