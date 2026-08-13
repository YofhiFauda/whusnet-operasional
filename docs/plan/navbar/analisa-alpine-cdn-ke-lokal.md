# Analisa: Alpine.js dari CDN ke Bundle Lokal

**Status:** **Dikerjakan 2026-08-13** — Langkah 1 (plugin sudah terpasang), Langkah 2 (wiring
`app.js`), Langkah 3 (cabut CDN), Langkah 4 (dokumentasi) selesai. Ditambah: tahap build aset di
`Dockerfile` (tidak ada di rencana awal, tapi wajib — `public/build` di-gitignore, jadi tanpa itu
image produksi kehilangan Alpine sepenuhnya).
**Verifikasi browser (§5) SELESAI 2026-08-13** — kesepuluh poin dijalankan manual oleh user dan
lolos, termasuk Grup `window.Alpine.initTree()` (poin 4-6) yang sempat tertunda karena papan FOP
tidak menampilkan tim mana pun; baru bisa diuji setelah jendela tanggal papan diperbaiki (ADHOC-34).
**Sisir target POST yang dirakit di klien** (ADHOC-20 langkah 3 di `docs/TASKS.md` — penomorannya
berbeda dari dokumen ini) juga sudah selesai 2026-08-13; penjaganya `PostTargetRenderedServerSideTest`.
**Tambahan di luar rencana dokumen ini:** build aset dijadikan syarat start di `docker-compose.yml`
(service `assets`), karena bind-mount `./:/var/www` menutupi `public/build` bawaan image sehingga
tahap `assets` di `Dockerfile` saja tidak cukup untuk deployment compose.
**Sifat:** ADHOC, di luar sprint aktif
**Tanggal:** 2026-08-12
**Konteks:** Prasyarat Fase 0 dari [`README.md`](README.md) (Rancangan Navigation Bar Responsive)

---

## 1. Ringkasan Temuan

Migrasi Alpine dari CDN ke bundle lokal **sudah pernah dimulai lalu ditinggalkan separuh jalan.** Paketnya ter-install, wiring-nya tidak pernah dipasang.

| Fakta | Bukti |
|---|---|
| `alpinejs` ada di dependencies | `package.json:18` — `"alpinejs": "^3.15.12"` |
| Paket benar-benar ter-install | `node_modules/alpinejs/` ada |
| **Tidak pernah diimpor** | `resources/js/app.js` hanya impor `nprogress` + `./bootstrap`; `resources/js/bootstrap.js` hanya `axios` + `./echo`. Tidak ada `import Alpine` di mana pun |
| CDN masih jadi satu-satunya sumber | `resources/views/layouts/app.blade.php:22` — `<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js">` |
| Dokumentasi mencerminkan keadaan lama | `CLAUDE.md` — "Alpine.js 3 via CDN (`layouts/app.blade.php`) — bukan SPA, tidak ada build step untuk Alpine" |

Konsekuensinya: aplikasi menarik ~44KB Alpine dari jsdelivr setiap kali halaman dimuat, sementara paket yang sama menganggur di `node_modules`.

---

## 2. Kerusakan Yang Sudah Terjadi Akibat Setengah Jalan

### 2.1 `x-collapse` mati — 6 titik pemakaian

Bundle `cdn.min.js` hanya berisi Alpine **core**. Plugin tidak termasuk, dan tidak ada tag `<script>` kedua untuk plugin mana pun. Padahal `x-collapse` dipakai di:

| File | Baris |
|---|---|
| `resources/views/roles/matrix.blade.php` | 613, 696 |
| `resources/views/noc/worksheet.blade.php` | 191 |
| `resources/views/tickets/history.blade.php` | 219 |
| `resources/views/components/ui/pop-tree-picker.blade.php` | 126, 177 |

Dampak: karena semua titik itu memasangkan `x-collapse` dengan `x-show`, panelnya **tetap buka-tutup** — yang hilang hanya animasi tinggi, berganti jadi snap mendadak. Bukan kerusakan fungsional, tapi Alpine juga melempar peringatan directive tak dikenal ke console di setiap halaman tersebut.

Ini kerusakan yang paling langsung tertutup oleh migrasi: plugin `@alpinejs/collapse` cuma bisa dipasang lewat bundle.

### 2.2 Versi tidak terkunci

URL CDN memakai `alpinejs@3.x.x` — rentang mayor, bukan versi pasti. Rilis minor Alpine mana pun masuk ke produksi tanpa satu baris pun berubah di repo, tanpa lewat CI, tanpa bisa di-rollback lewat git. `package.json` mengunci `^3.15.12` tapi kunci itu tidak berpengaruh karena paketnya tidak dipakai.

### 2.3 Ketergantungan jaringan pihak ketiga

Aplikasi operasional internal ISP. Kalau jsdelivr tidak terjangkau — DNS, firewall kantor, gangguan upstream — **seluruh interaksi Alpine mati**: dropdown user, dropdown notifikasi, drawer detail tiket, filter POP/wilayah, form pembuatan tiket. Halaman tetap render tapi tidak bisa dioperasikan.

Ini bukan hipotetis untuk aplikasi yang dipakai dari POP/cabang dengan jalur internet yang justru sedang jadi objek keluhan.

---

## 3. Yang Wajib Diperiksa Sebelum Mencabut CDN

Tiga pola pemakaian di repo ini bergantung pada perilaku spesifik bundle CDN. Ketiganya harus tetap jalan setelah migrasi.

### 3.1 `window.Alpine` dipakai eksplisit — 3 file

CDN **otomatis** memasang `window.Alpine`. Bundle lokal **tidak** — kalau lupa, tiga file ini rusak diam-diam:

| File | Baris | Pemakaian |
|---|---|---|
| `resources/views/fop/dashboard.blade.php` | 777, 779 | `window.Alpine.initTree(...)` setelah board di-swap realtime |
| `resources/views/tasks/own.blade.php` | 520-521 | `window.Alpine.initTree(card)` setelah kartu task diperbarui |
| `resources/views/fop_tasks/index.blade.php` | 1263-1264 | `window.Alpine.initTree(row)` setelah baris tabel diganti |

Semuanya jalur realtime (Reverb/Echo) yang mengganti potongan DOM lalu meminta Alpine mengikat ulang directive di subtree baru. Kalau `window.Alpine` undefined, penjagaan `if (window.Alpine)` membuatnya **gagal tanpa error** — DOM baru muncul tapi mati total. Gejalanya sangat sulit dilacak.

**Mitigasi wajib:** `window.Alpine = Alpine;` sebelum `Alpine.start()`.

### 3.2 Pola `alpine:init` — 2 komponen

`resources/views/components/ui/wilayah-filter.blade.php:157` dan `pop-filter.blade.php:153` mendaftarkan komponen lewat:

```js
document.addEventListener('alpine:init', () => {
    Alpine.data('wilayahFilter', (cfg) => ({ ... }));
});
```

Dua hal yang harus benar:

1. **`Alpine` (tanpa `window.`) harus resolve** — terpenuhi oleh §3.1.
2. **Listener harus terdaftar sebelum `alpine:init` dilempar.** Inline `<script>` klasik di `<body>` dieksekusi saat parsing; `@vite` menghasilkan `<script type="module">` yang selalu ditunda sampai parsing selesai. Jadi urutannya aman — listener terdaftar duluan.

Perilaku ini **sama dengan CDN sekarang** (`defer` juga menunggu parsing selesai), jadi tidak ada perubahan urutan. Tetap perlu diverifikasi di browser, bukan diasumsikan.

### 3.3 Flash of unstyled content (`x-cloak`)

`resources/css/app.css:258` sudah punya `[x-cloak] { display: none !important; }` dan `x-cloak` dipakai di ~30 titik. Karena bundle Vite dan CDN sama-sama ditunda, jendela flash-nya setara. Namun bundle lokal dilayani dari origin yang sama — umumnya **lebih cepat** dari round-trip CDN, jadi flash cenderung mengecil, bukan membesar.

### 3.4 Halaman yang memuat `@vite` tapi tanpa Alpine CDN

| File | Kondisi |
|---|---|
| `resources/views/auth/login.blade.php:16` | `@vite` ada, CDN Alpine tidak → tidak ada Alpine sama sekali hari ini |
| `resources/views/welcome.blade.php:15` | sama |
| `resources/views/components/layout/app-shell.blade.php:7` | sama, **dan file ini mati** (tidak dirujuk view mana pun) |

Setelah Alpine masuk ke `app.js`, ketiga halaman ini **mendapat Alpine secara otomatis**. Untuk login dan welcome itu netral atau menguntungkan (`x-data` di masa depan langsung jalan). Untuk `app-shell` tidak relevan karena dijadwalkan dihapus di Fase 6 rancangan navbar.

Efek samping yang perlu disadari: halaman login jadi mengeksekusi Alpine yang sebelumnya tidak ada. Tidak ada `x-data` di sana sekarang, jadi biayanya hanya parsing bundle.

---

## 4. Rencana Migrasi

### Langkah 1 — Pasang plugin

```bash
npm install --save @alpinejs/focus @alpinejs/collapse
```

Dua plugin, dua alasan berbeda:

- **`@alpinejs/collapse`** — memperbaiki kerusakan yang sudah ada (§2.1). Dibutuhkan walau rancangan navbar dibatalkan.
- **`@alpinejs/focus`** — menyediakan `x-trap` untuk focus trap drawer (§4.3 rancangan navbar). Dibutuhkan Fase 3.

Ukuran tambahan: `collapse` ≈ 2KB, `focus` ≈ 5KB (gzip), keduanya ikut ke dalam bundle yang sudah ada — tidak menambah request HTTP.

> `alpinejs` sendiri **tidak perlu di-install ulang** — sudah ada di `package.json:18` dan `node_modules/`.

Sesuai aturan Boost di `CLAUDE.md`, penambahan dependency perlu persetujuan eksplisit. Dua paket di atas adalah satu-satunya penambahan yang dokumen ini minta.

### Langkah 2 — Wiring di `resources/js/app.js`

```js
import NProgress from 'nprogress';

window.NProgress = NProgress;
NProgress.configure({ showSpinner: false, minimum: 0.1 });
NProgress.done();

window.addEventListener('beforeunload', () => {
    NProgress.start();
});

import './bootstrap';

/* ── Alpine.js — dibundel lokal, bukan CDN ──
   window.Alpine WAJIB di-set: tiga view realtime (fop/dashboard,
   tasks/own, fop_tasks/index) memanggil window.Alpine.initTree()
   untuk mengikat ulang directive di DOM yang baru diganti. Bundle
   CDN memasang global ini otomatis; bundle lokal tidak. Kalau
   dihapus, ketiga halaman itu gagal DIAM-DIAM — penjagaan
   `if (window.Alpine)` di sana membuatnya tidak melempar error. */
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';

Alpine.plugin(collapse);
Alpine.plugin(focus);

window.Alpine = Alpine;
Alpine.start();
```

Plugin didaftarkan **sebelum** `start()` — plugin yang didaftarkan setelahnya diabaikan tanpa peringatan.

### Langkah 3 — Cabut CDN

Hapus `resources/views/layouts/app.blade.php:21-22`:

```blade
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
```

**Jangan dicabut sebelum Langkah 2 terverifikasi di browser.** Kalau keduanya aktif bersamaan sesaat, Alpine akan memperingatkan "detected multiple instances" — itu wajar dalam jendela transisi, tapi jangan ditinggal dalam keadaan itu.

### Langkah 4 — Perbarui dokumentasi

`CLAUDE.md`, bagian Tech Stack:

```diff
- Blade server-rendered + **Alpine.js 3 via CDN** (`layouts/app.blade.php`) — bukan SPA, tidak ada build step untuk Alpine
+ Blade server-rendered + **Alpine.js 3 dibundel lewat Vite** (`resources/js/app.js`, plugin `collapse` + `focus`) — bukan SPA, tapi Alpine ikut build step
```

Ini penting: kalimat lama secara aktif menyesatkan siapa pun yang mengubah JS setelah migrasi.

---

## 5. Verifikasi

Test PHPUnit tidak menjangkau eksekusi JavaScript, jadi verifikasi migrasi ini **manual di browser**. Daftar berikut menyasar tiga pola berisiko di §3, bukan sekadar "buka aplikasi".

| # | Halaman | Yang dicek | Membuktikan |
|---|---|---|---|
| 1 | mana saja | dropdown user & dropdown notifikasi di topbar terbuka | Alpine core hidup |
| 2 | `roles/matrix` | panel `x-collapse` beranimasi mulus, bukan snap | plugin collapse terpasang (§2.1) |
| 3 | `tickets/history`, `noc/worksheet` | panel filter beranimasi | idem |
| 4 | `fop/dashboard` | picu update realtime, pastikan board yang di-swap masih interaktif | `window.Alpine.initTree` (§3.1) |
| 5 | `tasks/own` | idem untuk kartu task | idem |
| 6 | `fop_tasks/index` | idem untuk baris tabel | idem |
| 7 | halaman ber-`pop-filter`/`wilayah-filter` | dropdown filter terbuka, pencarian jalan, "Terapkan" jalan | pola `alpine:init` (§3.2) |
| 8 | `tickets/create` | form pencarian CID, toast, drawer | `x-data` kompleks |
| 9 | `auth/login` | halaman tetap normal | Alpine baru masuk ke halaman ini |
| 10 | Console browser | tidak ada peringatan Alpine, tidak ada "multiple instances" | CDN benar-benar tercabut |

Semuanya dicek dengan `npm run build` (bukan hanya `npm run dev`) minimal sekali — resolusi plugin bisa berbeda antara mode dev dan build.

### Rollback

Kembalikan `app.blade.php:21-22` dan buang blok Alpine dari `app.js`. Satu commit, dua file. Murah — itu sebabnya migrasi ini aman dikerjakan lebih dulu, terpisah dari perubahan navbar.

---

## 6. Rekomendasi

Kerjakan migrasi ini **sebagai commit tersendiri, sebelum fase navbar mana pun.**

Alasannya:

1. Ia berdiri sendiri — memperbaiki `x-collapse` yang sudah rusak (§2.1) dan menghapus ketergantungan CDN (§2.3), terlepas dari apakah rancangan navbar dilanjutkan.
2. Ia prasyarat keras Fase 1 dan Fase 3 rancangan navbar (`Alpine.store`, `x-trap`).
3. Kalau digabung dengan perubahan navbar, kegagalan jadi ambigu: rusaknya karena bundling atau karena refactor state? Terpisah, penyebabnya jelas.

Yang perlu persetujuan sebelum mulai: **`npm install @alpinejs/focus @alpinejs/collapse`** — satu-satunya penambahan dependency.
