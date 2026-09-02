# Rancangan Navigation Bar Responsive

**Status:** Rancangan — belum dikerjakan
**Sifat:** ADHOC, **di luar sprint aktif `docs/TASKS.md`**
**Tanggal:** 2026-08-12
**Dokumen pendamping:** [`analisa-alpine-cdn-ke-lokal.md`](analisa-alpine-cdn-ke-lokal.md) — prasyarat teknis Fase 1

---

## 1. Kenapa Dokumen Ini Ada

Navigasi aplikasi (sidebar + topbar) ditulis untuk desktop, lalu ditambal satu breakpoint (`md`/768px) supaya "tidak rusak" di HP. Hasilnya bukan responsive, tapi dua layout yang dijahit di satu titik. Peran lapangan — teknisi, kolektor — memakai HP sebagai perangkat utama, dan justru mereka yang paling dirugikan.

Dokumen ini memetakan kondisi sekarang, menyebut masalahnya satu per satu, lalu merancang penggantinya dalam fase yang bisa dihentikan di tengah tanpa meninggalkan sistem setengah jadi.

---

## 2. Kondisi Sekarang

### 2.1 Peta file

| Bagian | Lokasi | Perilaku sekarang |
|---|---|---|
| Sidebar (drawer + rail) | `resources/views/layouts/app.blade.php:36-587` | `fixed inset-y-0 w-64 -translate-x-full`, dibuka dengan `md:static md:translate-x-0` |
| Backdrop mobile | `app.blade.php:590-592` | `hidden md:hidden`, klik memanggil `toggleSidebar()` |
| Topbar (glass header) | `app.blade.php:600-732` | `h-16`, isi: toggle, breadcrumb, search, tema, bantuan, notifikasi, menu user |
| Main content | `app.blade.php:735-737` | `p-4 sm:p-6 lg:p-5 xl:p-8` |
| JS navigasi | `app.blade.php:744-785` | `toggleSidebar()`, `toggleDesktopSidebar()`, `toggleSubmenu(menuId, chevronId)` — class-swap imperatif |
| CSS rail/collapsed | `resources/css/app.css:261-365` | seluruh aturan `.collapsed` di dalam `@media (min-width: 768px)` |
| Style item nav | `resources/css/app.css:790-850` | `.sidebar-light`, `.sidebar-nav-item`, `.sidebar-subitem-active` |
| **Shell paralel (mati)** | `resources/views/components/layout/{app-shell,sidebar,topbar}.blade.php` | tak dirujuk view mana pun; pakai token desain berbeda (`bg-surface`, `text-text-main`) dan state Alpine `sidebarOpen` yang tak pernah didefinisikan |

### 2.2 Struktur menu yang harus dipertahankan

Sidebar sekarang punya 4 grup, semuanya dipagari `hasPermission()`:

1. **Operasional** — Dashboard, Pelanggan (submenu 7 item), Tagihan (submenu 4), Pembayaran, Worksheet Admin, Worklist Kolektor
2. **Jaringan & Lapangan** — Penjadwalan Teknis (submenu 4), Ticketing (submenu 6)
3. **Laporan** — Laporan Keuangan (submenu 4), Audit Log
4. **Master & Pengaturan** — Master Data (submenu 10+), Pengguna, Role

Rancangan ini **tidak mengubah isi menu, urutan, maupun ekspresi permission-nya.** Yang berubah hanya cara menu itu ditampilkan dan di-state-kan. Setiap perubahan pada ekspresi `hasPermission()` di luar scope dokumen ini.

---

## 3. Masalah Yang Ditemukan

Diurutkan dari dampak terbesar.

### M1 — Hanya satu breakpoint, rentang tablet tak tertangani

Semua keputusan responsive bergantung pada `md` (768px). Akibatnya rentang **768–1279px** (tablet landscape, laptop kecil, jendela browser setengah layar) mendapat sidebar penuh 256px + breadcrumb 3 ruas + search — konten utama tersisa sangat sempit, terutama di halaman tabel lebar seperti `customers.index` dan `invoices.index`.

### M2 — Search hilang total di mobile

`app.blade.php:645` — `hidden md:block`. Di bawah 768px tidak ada jalan mencari CID, invoice, atau tiket. Ini persis kebutuhan teknisi dan kolektor di lapangan. Handler `handleGlobalSearch(event)` sudah ada dan berfungsi; yang hilang cuma pintu masuknya.

### M3 — Drawer tanpa aksesibilitas

Drawer mobile tidak punya:
- `aria-expanded` / `aria-controls` di tombol hamburger
- `role="dialog"` + `aria-modal` di `<aside>`
- focus trap — Tab dari dalam drawer lolos ke konten di belakang
- Escape untuk menutup
- body scroll lock — halaman di belakang tetap ikut ter-scroll

### M4 — State navigasi tersebar dan imperatif

Tiga fungsi global memanipulasi `classList` langsung, dan `toggleSubmenu()` mengoper **dua ID string** (`'submenu-pelanggan'`, `'chevron-pelanggan'`) yang harus cocok dengan atribut `id` di markup. Rapuh: salah ketik satu ID = submenu diam tanpa error. Padahal Alpine sudah tersedia di halaman ini.

Efek samping lain: drawer tidak menutup otomatis saat berpindah halaman atau saat viewport melewati `md`.

### M5 — Target sentuh di bawah standar

Tombol ikon di topbar memakai `p-2` (≈36px) dan tombol tutup sidebar `p-1.5` (≈32px). Minimum yang layak untuk jari adalah 44×44px (WCAG 2.5.5 / pedoman platform).

### M6 — `h-screen` bermasalah di browser mobile

`app.blade.php:31` memakai `h-screen` (`100vh`). Di Safari/Chrome mobile, address bar yang muncul-hilang membuat `100vh` lebih tinggi dari area terlihat, sehingga bagian bawah konten terpotong. Ditambah `overflow-hidden` di elemen yang sama, konten yang terpotong tidak bisa dijangkau.

### M7 — Topbar padat di layar sempit

Di bawah 400px, `h-16` harus memuat: hamburger, judul halaman, tombol tema, tombol bantuan, dropdown notifikasi, pemisah, avatar + chevron. Tidak ada satu pun yang runtuh ke menu lain. Judul halaman kena `truncate` sampai tidak terbaca.

### M8 — Padding `<main>` turun di `lg`

`p-4 sm:p-6 lg:p-5 xl:p-8` — nilai `lg` (20px) **lebih kecil** dari `sm` (24px). Urutan skala tidak monoton; hampir pasti tidak disengaja.

### M9 — Shell navigasi duplikat yang mati

`components/layout/app-shell.blade.php` merakit `x-layout.sidebar` + `x-layout.topbar`, tapi `app-shell` sendiri tidak dirujuk view mana pun. Ketiganya memakai kosakata token berbeda dari layout hidup dan bergantung pada `sidebarOpen` yang tidak pernah ada. Dua sumber kebenaran navigasi = perubahan menu berisiko diterapkan di file yang salah.

---

## 4. Rancangan

### 4.1 Tiga mode, bukan dua

| Mode | Lebar | Sidebar | Topbar |
|---|---|---|---|
| **Mobile** | `< 768px` (`< md`) | Off-canvas drawer `w-72`, overlay penuh + backdrop | Hamburger · judul halaman · ikon search · notifikasi · avatar |
| **Tablet** | `768–1279px` (`md`–`xl`) | **Rail default** — 64px, ikon saja + tooltip. Expand → melayang di atas konten, tidak mendorong | Breadcrumb 2 ruas · search `max-w-xs` |
| **Desktop** | `≥ 1280px` (`xl`) | Expanded 256px, mendorong konten. Toggle → rail | Breadcrumb 3 ruas · search `max-w-md` · nama user tampil |

**Perubahan inti: ambang rail digeser dari `md` (768) ke `xl` (1280).** Satu keputusan ini menyelesaikan M1 — rentang tablet tidak lagi mewarisi layout desktop.

Konsekuensi pada `resources/css/app.css`: blok `@media (min-width: 768px)` di baris 261 dipecah jadi dua.

```css
/* Rail: berlaku dari tablet ke atas */
@media (min-width: 768px) {
  #sidebar { position: static; transform: none; }
  /* aturan .collapsed yang sudah ada tetap di sini — tidak diubah */
}

/* Tablet: rail sebagai default, bukan pilihan user.
   Preferensi user (localStorage) baru dihormati mulai xl. */
@media (min-width: 768px) and (max-width: 1279px) {
  #sidebar:not(.rail-forced-open) { /* terapkan gaya .collapsed */ }
}
```

Catatan implementasi: lebih bersih menerapkan ini lewat kelas yang dipasang Alpine berdasarkan lebar viewport daripada menduplikasi selector `.collapsed` di dua media query. Detail di §4.2.

### 4.2 State model — pindah ke Alpine store

Ganti `toggleSidebar()` / `toggleDesktopSidebar()` / `toggleSubmenu()` dengan satu store. **Prasyarat: Alpine dibundel lokal** — lihat dokumen pendamping.

```js
// resources/js/nav.js — diimpor dari app.js
document.addEventListener('alpine:init', () => {
    Alpine.store('nav', {
        // drawer off-canvas (mobile). Tidak pernah persist —
        // membuka aplikasi selalu mulai dari keadaan tertutup.
        drawer: false,

        // rail = sidebar ikon-saja. Persist, tapi hanya dihormati di xl ke atas;
        // di bawah xl rail dipaksa aktif tanpa menimpa preferensi user.
        railPref: localStorage.getItem('nav-rail') === '1',

        // Lebar viewport dipantau di satu tempat supaya tidak ada
        // komponen yang menghitung breakpoint-nya sendiri.
        viewport: 'desktop', // 'mobile' | 'tablet' | 'desktop'

        // Akordeon submenu: satu terbuka pada satu waktu.
        // Menggantikan toggleSubmenu(menuId, chevronId) yang bergantung
        // pada kecocokan dua string id di markup.
        openSubmenu: null,

        get rail() {
            if (this.viewport === 'mobile') return false;
            if (this.viewport === 'tablet') return true;
            return this.railPref;
        },

        toggleDrawer() { this.drawer = !this.drawer; },
        closeDrawer()  { this.drawer = false; },

        toggleRail() {
            this.railPref = !this.railPref;
            localStorage.setItem('nav-rail', this.railPref ? '1' : '0');
        },

        toggleSubmenu(key) {
            this.openSubmenu = this.openSubmenu === key ? null : key;
        },

        syncViewport() {
            const w = window.innerWidth;
            this.viewport = w < 768 ? 'mobile' : (w < 1280 ? 'tablet' : 'desktop');
            // Drawer wajib tertutup begitu keluar dari mode mobile —
            // kalau tidak, sidebar tersangkut sebagai overlay di desktop.
            if (this.viewport !== 'mobile') { this.drawer = false; }
        },
    });

    Alpine.store('nav').syncViewport();
    window.addEventListener('resize', () => Alpine.store('nav').syncViewport());
});
```

Pemakaian di Blade:

```blade
<aside id="sidebar"
       x-data
       :class="{ 'translate-x-0': $store.nav.drawer, '-translate-x-full': !$store.nav.drawer,
                 'collapsed': $store.nav.rail }"
       role="dialog" aria-modal="true" aria-label="Menu navigasi"
       x-trap.noscroll="$store.nav.viewport === 'mobile' && $store.nav.drawer">
```

**Submenu aktif saat load halaman.** Sekarang ditentukan Blade lewat `Request::is()` yang menempelkan kelas `is-open`. Dengan store, nilai awal `openSubmenu` diisi dari Blade sekali:

```blade
<div x-data x-init="$store.nav.openSubmenu ??= @js($submenuAktif)">
```

di mana `$submenuAktif` dihitung dari `Request::is()` yang sama — logika penentuannya tidak berubah, hanya keluarannya yang jadi satu nilai alih-alih kelas yang ditempel per-elemen.

### 4.3 Aksesibilitas (menutup M3)

| Kebutuhan | Cara |
|---|---|
| Focus trap | `x-trap.noscroll` dari `@alpinejs/focus`. Modifier `.noscroll` sekaligus mengunci scroll body |
| Escape menutup | `@keydown.escape.window="$store.nav.closeDrawer()"` |
| Status tombol | `aria-expanded="$store.nav.drawer"` + `aria-controls="sidebar"` di hamburger |
| Label rail | Saat `.sidebar-text` disembunyikan, teks jadi tak terbaca screen reader. Setiap item nav butuh `aria-label` eksplisit — `title` saja tidak cukup |
| Item aktif | `aria-current="page"` sudah dipakai di sebagian item (`app.blade.php:138`); seragamkan ke semua item |
| Reduced motion | `app.css:1125` sudah punya blok `prefers-reduced-motion`; pastikan transisi drawer masuk ke dalamnya |

### 4.4 Target sentuh (menutup M5)

Tombol ikon topbar: `p-2.5 md:p-2` — 44px di mobile, 36px di desktop di mana pointer presisi. Tombol tutup sidebar `p-1.5` → `p-2.5`. Item submenu `py-1.5` → `py-2.5 md:py-1.5`.

### 4.5 Topbar mobile (menutup M2, M7)

```
[≡]  Judul Halaman ………………………         [🔍] [🔔] [ID]
```

- Tombol tema dan bantuan pindah ke dalam dropdown user saat `< sm`; tetap inline mulai `sm`.
- `[🔍]` membuka overlay search fullscreen: `fixed inset-0 z-50`, input `autofocus`, Escape menutup. Reuse `handleGlobalSearch(event)` yang sudah ada — tidak ada logika pencarian baru.
- Breadcrumb ruas induk tetap `hidden sm:flex`. Ruas aktif (`@yield('page_title')`) naik jadi judul topbar mobile.

### 4.6 Bottom navigation untuk peran lapangan

Diputuskan **ikut** dalam rancangan ini (Fase 5).

**Bentuk.** `fixed bottom-0 inset-x-0 md:hidden`, tinggi 56px + `env(safe-area-inset-bottom)`, 4 slot maksimum, ikon + label pendek.

**Isi slot** disusun dari permission — bukan dari role, sesuai larangan keras RBAC repo ini:

| Slot | Tujuan | Syarat permission |
|---|---|---|
| 1 | Task Saya (`tasks.own`) | `task.view.own` |
| 2 | Worklist Kolektor | `kolektor.view` |
| 3 | Worksheet Helpdesk / Tiket | `tickets.create` atau `tickets.view` |
| 4 | Menu (buka drawer) | selalu ada |

Slot yang syaratnya tidak terpenuhi tidak dirender; sisanya merapat. Slot "Menu" selalu di posisi terakhir supaya letaknya konsisten.

**Kapan tampil.** Bottom nav hanya berguna untuk pengguna yang navigasinya sempit (2–3 halaman). Untuk `owner`/`admin` yang butuh 30+ menu, bottom nav justru menyesatkan karena hanya menampilkan sebagian kecil. Aturannya:

> Bottom nav dirender bila user memenuhi **maksimal 2** dari slot 1–3, **dan** tidak punya `*` (full access).

Ini murni turunan permission, tidak menambah kolom, tidak menambah role.

**Dampak layout.** `<main>` perlu `pb-20 md:pb-0`. Karena `@yield('content')` dipakai ~90 view, padding dipasang di `<main>` layout — bukan di tiap halaman.

**Risiko yang diterima.** Bottom nav menambah permukaan UI ketiga (drawer + topbar + bottom). Kalau ternyata tidak dipakai, hapusnya murah karena ia satu partial mandiri dan satu kelas padding.

### 4.7 Perbaikan dasar (M6, M8, M9)

| Item | Perubahan |
|---|---|
| `app.blade.php:31` | `h-screen` → `h-[100dvh]` |
| `app.blade.php:735` | `p-4 sm:p-6 lg:p-5 xl:p-8` → `p-4 md:p-6 xl:p-8` (monoton naik) |
| `components/layout/app-shell.blade.php` | **Hapus** |
| `components/layout/sidebar.blade.php` | **Hapus** |
| `components/layout/topbar.blade.php` | **Hapus** |
| `components/layout/page-header.blade.php` | **Pertahankan** — dipakai terpisah, tidak bagian shell mati |

Penghapusan tiga file di atas perlu konfirmasi eksplisit sebelum dieksekusi, meski `grep` menunjukkan `x-layout.app-shell` tidak dirujuk siapa pun.

---

## 5. Fase Kerja

Setiap fase berdiri sendiri: berhenti setelah fase mana pun tetap meninggalkan aplikasi dalam keadaan konsisten.

### Fase 0 — Alpine lokal (prasyarat)
Bundel Alpine + plugin `focus` dan `collapse` lewat Vite, cabut CDN. Tidak ada perubahan visual yang diharapkan. **Detail lengkap, risiko, dan urutan verifikasi ada di [`analisa-alpine-cdn-ke-lokal.md`](analisa-alpine-cdn-ke-lokal.md).**
→ Menutup: prasyarat M4, sekaligus memperbaiki `x-collapse` yang mati.

### Fase 1 — Fondasi state
Buat `resources/js/nav.js` + store. Ganti tiga fungsi global di `app.blade.php:744-785` dengan binding Alpine. Perilaku dan tampilan **tidak berubah** — ini refactor murni.
→ Menutup: M4.

### Fase 2 — Tiga breakpoint
Terapkan mode mobile/tablet/desktop di `<aside>` dan `app.css:261`. Ini fase yang mengubah tampilan paling terasa.
→ Menutup: M1.

### Fase 3 — Aksesibilitas & target sentuh
Focus trap, Escape, scroll lock, ARIA, ukuran tombol.
→ Menutup: M3, M5.

### Fase 4 — Topbar mobile & overlay search
→ Menutup: M2, M7.

### Fase 5 — Bottom navigation lapangan
→ Menutup: §4.6.

### Fase 6 — Perbaikan dasar & bersih-bersih
`100dvh`, padding `<main>`, hapus shell mati, perbarui `CLAUDE.md` (baris yang menyebut "Alpine.js 3 via CDN … tidak ada build step untuk Alpine" sudah tidak benar setelah Fase 0), jalankan `vendor/bin/pint`.
→ Menutup: M6, M8, M9.

---

## 6. Test

Navigasi ini Blade + JS, dan saat ini **tidak ada satu pun test yang menyentuhnya**. Refactor sebesar ini tanpa jaring pengaman berisiko membocorkan menu lintas permission tanpa ketahuan. Karena itu Fase 1 tidak dimulai sebelum test berikut ada dan hijau.

### `tests/Feature/LayoutNavigationTest.php`

Render halaman ber-layout untuk beberapa peran, lalu pastikan menu yang tampil sesuai permission:

- `owner` — melihat keempat grup
- `teknisi` — melihat "Task Saya"; **tidak** melihat Tagihan, Pembayaran, Laporan Keuangan, Master Data
- `kolektor`/`pop_admin` — melihat Worklist Kolektor; **tidak** melihat Master Data maupun Role
- `helpdesk` — melihat Worksheet Helpdesk; **tidak** melihat Laporan Keuangan

Assertion negatif (`assertDontSee`) yang paling penting di sini — itu yang menangkap kebocoran menu.

### `tests/Feature/LayoutResponsiveMarkupTest.php`

Menjaga atribut responsive/a11y tidak hilang saat markup diedit di kemudian hari:

- `<aside>` punya `role="dialog"` dan `aria-label`
- tombol hamburger punya `aria-controls="sidebar"`
- ada tepat satu elemen `aria-current="page"`
- bottom nav muncul untuk user teknisi dan **tidak** muncul untuk owner

Ikuti konvensi repo: PHPUnit (bukan Pest), `RefreshDatabase`, atribut `#[Test]`, nama test menggambarkan gejala.

### Verifikasi manual

Lebar yang wajib dicek tiap fase: **375, 414, 768, 1024, 1280, 1536**. Plus: buka drawer lalu putar layar, dan buka drawer lalu tekan Escape.

---

## 7. Yang Sengaja Tidak Dikerjakan

- **Isi dan urutan menu** — tidak disentuh sama sekali.
- **Ekspresi `hasPermission()`** — tidak ditambah, tidak dikurangi, tidak digabung.
- **Halaman konten** (tabel, form, kartu) — responsivitas di dalam `<main>` masalah terpisah dan cukup besar untuk dokumen sendiri.
- **Mode SPA / Turbo** — di luar arsitektur repo (server-rendered Blade).
- **Sistem tema** — `toggleTheme()` dan View Transitions API dibiarkan apa adanya.

---

## 8. Catatan Scope

Pekerjaan ini **di luar `docs/TASKS.md`**. Sesuai `CLAUDE.md` §"Cara Kerja Task", perubahan di luar sprint aktif tidak dikerjakan tanpa persetujuan. Dokumen ini adalah rancangan, bukan izin mulai. Bila disetujui, catat sebagai ADHOC di `docs/TASKS.md` sebelum Fase 0 dimulai.
