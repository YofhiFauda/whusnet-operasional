# Analisis Performa Peta Infrastruktur ISP (whusnet-operasional)

Sumber: `Peta_Infrastruktur_ISP.html` (snapshot halaman, 92 MB) dan dokumen `analisis_dan_solusi_performa_canvas_topologi_isp.md`.

## 1. Ringkasan

Peta ini **bukan Canvas**. Tidak ada satu pun `<canvas>` di file. Peta dirender sebagai DOM/SVG yang dikendalikan Alpine.js, dan semua node digambar sekaligus. Itulah kandidat penyebab utama memori mencapai 4 GB.

> Batasan: pembagian persis 4 GB tidak bisa diukur dari file statis. Angka di bawah adalah hasil hitung/simulasi dari file, bukan pengukuran di browser. Konfirmasi dengan Chrome DevTools (Memory → Heap snapshot, Performance Monitor → DOM Nodes, JS heap).

## 2. Temuan dari file

| Hal | Nilai |
|---|---|
| Elemen `<canvas>` | 0 |
| Total elemen DOM | 277.087 |
| Kartu node (`<svg class="topo-card">`) | 16.244 |
| Komposisi node | 12.750 ONT, 3.072 ODP, 384 PON, 24 slot, 6 chassis, 6 lainnya |
| Garis (`<path>`) dalam satu SVG | 16.243 |
| Ukuran SVG garis | 2560 × 1.232.232 px |
| Elemen per kartu | 16 |
| Directive Alpine per kartu | 23 (≈ 374 ribu total) |
| Blob JSON `peta-data` | 16,9 MB (`nodes` 14,9 MB; `index` 2,9 MB) |
| Port terpakai / total | 12.762 / 49.200 (49.152 = 3.072 ODP × 16 + 48) |

"3.489 node" kemungkinan merujuk ke 3.494 node infrastruktur non-ONT (16.244 − 12.750 = 3.494, sama dengan jumlah `hierarchyItems`).

Daftar port **tidak** ikut payload awal (`ports: []`, dimuat lazy lewat `ports_url`). Tree berhenti di ONT, tidak sampai level port.

Snapshot dimuat dengan `expand=all&ont=1`, sehingga semua kartu dirender.

## 3. Penyebab yang paling mungkin

1. **Rendering tanpa culling.** Semua 16.244 kartu dan 16.243 garis ada di DOM. Pada simulasi viewport 1500×800, kasus terburuk hanya **63 kartu** yang terlihat sekaligus.
2. **Ledakan effect reaktif Alpine.** Sekitar 374 ribu directive, tiap `:class` kartu bergantung pada `selectedNodeId` sehingga memilih satu kartu memicu evaluasi ulang ribuan effect.
3. **Proxy reaktif pada data besar.** `nodes` (14 MB) tidak di-freeze sehingga dibungkus Proxy Alpine. Hanya `index` yang sudah di-freeze.
4. **Dunia setinggi 1,2 juta px.** Setiap ONT mendapat satu baris 82 px (15.026 baris leaf). Layer hasil `transform` dengan transisi jadi sangat besar.
5. **`edgePaths()` lewat `x-html`.** Membangun ulang 16 ribu path setiap node dipilih (jalur aktif bergantung pada `selectedNodeId`).
6. **Payload gemuk.** Per ONT dikirim `path`, `show_url`, `modems_url`, `location_description`, `ports: []`, `assets: []`, dan banyak field `null`.

## 4. Hasil verifikasi

Simulasi `computeLayout()` pada data asli cocok dengan snapshot: 15.026 baris dan tinggi 1.232.232 px. Posisi `y` pada kedalaman yang sama selalu terurut, jadi culling bisa memakai binary search.

Kartu terlihat (viewport 1500×800, margin 200 px, seluruh posisi pan disapu):

| Zoom | Median | p95 | Maks |
|---|---|---|---|
| 0,2 | 58 | 62 | 63 |
| 0,25 | 48 | 51 | 52 |
| 0,5 | 27 | 29 | 31 |
| 1,0 | 16 | 18 | 19 |
| 2,0 | 1 | 12 | 14 |

Potensi pangkas payload: `nodes` 13,9 MB menjadi 1,0 MB (−93%) dengan bentuk ringkas 9 kolom `[id, parent, tipe, code, name, aktif, red_zone, ports, used]`. `index` (2,8 MB) duplikat dan bisa diturunkan dari data itu.

Renderer Canvas (`topology-canvas.js`) diuji di Node memakai Skia pada 16.244 node asli (belum diuji di browser/Alpine):

| Zoom | draw p95 | Kartu digambar (maks) |
|---|---|---|
| 0,2 | 4,2 ms | 59 |
| 0,5 | 6,9 ms | 27 |
| 1,0 | 4,9 ms | 15 |

- Hit-test: 2000/2000 klik acak tepat.
- Pipeline data (parse JSON, layout, indeks): +27 MB heap V8. Bitmap canvas ≈ 19 MB (hitungan). Ini hanya biaya renderer pengganti, bukan total halaman.

## 5. Rencana perbaikan (urutan disarankan)

1. **Keluarkan data besar dari `x-data`.** Simpan `nodes`, `layout`, dan instance renderer di variabel biasa (closure/modul). Biarkan hanya state kecil reaktif (`selectedNodeId`, `query`, `collapsedIds`). Murah dan tidak mengubah UI.
2. **Ringkas payload dari Laravel.** Kirim bentuk ringkas, ambil detail saat node diklik (pola `ports_url` / `modems_url` yang sudah ada).
3. **Ganti SVG per kartu dengan renderer `<canvas>` + viewport culling** (`topology-canvas.js`): binary search per kolom untuk kartu, culling garis berdasarkan rentang anak induk (garis yang menyilang viewport tetap tergambar), render on-demand, LOD ringkas di bawah zoom 0,5.
4. **Ikhtisar semantik.** `zoomFit()` saat ini tak berguna (di zoom minimum 0,2 butuh ±308 layar). Tampilkan PON/ODP sebagai bar utilisasi, munculkan kartu ONT hanya saat zoom-in, dan pakai `centerOn(id)` untuk lompat ke node hasil pencarian.

Catatan pemasangan renderer:

- Jangan jadikan instance renderer properti reaktif Alpine.
- Panggil `requestDraw()` setelah `document.fonts.ready`, karena canvas tidak redraw otomatis saat font selesai dimuat.
- Panggil `setTheme('dark')` saat tema berganti (canvas tidak membaca kelas Tailwind).
- Pertahankan panel pohon `hierarchyItems` dan pencarian di DOM untuk aksesibilitas dan seleksi teks.

## 6. Review dokumen `analisis_dan_solusi_performa_canvas_topologi_isp.md`

Kesimpulan: arah solusi (culling, payload ramping, render on-demand) benar, tetapi diagnosis generik dan tidak cocok dengan kode halaman ini.

| Klaim di dokumen | Penilaian | Alasan |
|---|---|---|
| Canvas 2D menjalankan `arc()`/`fillText()` untuk node di luar layar | Salah | Tidak ada `<canvas>`; yang ada SVG + Alpine. |
| JSON tree utuh sampai level port jadi penyebab | Sebagian | Port tidak di payload awal; JSON 14 MB, dominan lebih mungkin DOM dan Proxy Alpine. |
| Tanpa culling | Benar | Semua kartu dirender. |
| Tanpa LOD sampai level port | Salah | Level port tidak digambar sama sekali. |
| API bounding box + PostGIS | Tidak cocok | Diagram pohon, bukan peta geografis (`latitude`/`longitude` null); `x/y` dihitung klien dan berubah saat collapse/filter. |
| Flat array `[id, parent, type, x, y, status]` | Benar, kecuali `x,y` | Layout dihitung klien; data ringkas cukup dimuat sekaligus. |
| Culling dengan `forEach` + `isInViewport` | Benar tapi kurang | O(N) per frame dan garis menyilang bisa hilang; binary search per kolom lebih baik. |
| Sprite / offscreen canvas | Prematur | Hanya ±60 kartu per frame dengan teks unik. |
| Render on-demand | Benar | Sudah ada di `topology-canvas.js`. |
| WebGL/PixiJS, "RAM < 200MB" | Tidak perlu, tidak terbukti | 16 ribu node dengan ≤63 terlihat tidak butuh WebGL; angka 200 MB tanpa dasar pengukuran. |

Hal penting yang tidak dicakup dokumen itu: Proxy reaktif pada `nodes`, dunia setinggi 1,2 juta px (LOD saja tidak menolong jika layout tetap menyediakan baris untuk semua ONT), dan `edgePaths()` lewat `x-html`.

## 7. Langkah verifikasi

1. Ukur baseline di Chrome: Heap snapshot, jumlah "DOM Nodes", dan JS heap.
2. Terapkan langkah 1 (keluarkan data dari `x-data`), ukur ulang.
3. Terapkan langkah 2 dan 3, ukur ulang, lalu uji interaksi (pan, zoom, pilih, lipat, cari) di browser nyata.
