# SPA Feel via htmx — Design

Status: **belum dieksekusi**, nunggu ADHOC-202 (sprint aktif) selesai. Ini spec hasil brainstorming, bukan izin implementasi.

## Latar Belakang

User minta app Blade terasa seperti SPA: navigasi instant di SEMUA halaman, plus update data realtime tanpa reload penuh di halaman yang sering dipakai/sering berubah/lintas role. Analisa sebelumnya (`analisa-performa-seeder-15k.md` §12-13) menemukan 3 dari 4 halaman terberat (`/noc/dashboard`, `/fop`, `/collector-worksheet`) sudah pakai Echo/Reverb tapi handler-nya `fetch(window.location.href)` — fetch ulang SELURUH controller tiap broadcast event, dikali jumlah tab terbuka. Satu halaman (`/fop-tasks`, lewat `refreshFopTaskRow()` → `/fop-tasks/{id}/row`) sudah punya pola partial-update yang benar — jadi presedennya ada di repo yang sama, cuma belum ditiru ke tempat lain.

## Tujuan

1. Navigasi antar halaman terasa instant di **semua** halaman (tanpa full page reload).
2. Data di halaman yang sering dipakai + sering berubah + dipakai lintas role ikut live-update tanpa reload, mengikuti pola `/fop-tasks` yang sudah terbukti benar.
3. Tidak menambah beban server baru — endpoint partial justru harus LEBIH ringan dari full page load yang digantikannya (motong akar masalah §1/§3/§7/§12 di `analisa-performa-seeder-15k.md`, bukan cuma memindahkan ongkosnya).

## Non-tujuan

- Tidak mengubah 3 pola aksi-baru (modal/halaman-create/inline-toggle) yang sudah ditetapkan CLAUDE.md §Konvensi Kode — htmx cuma lapisan transport, bukan pengganti keputusan pola itu.
- Tidak menambah mekanisme notifikasi cross-role baru di luar broadcast Echo yang sudah ada (keputusan user saat brainstorming: perkuat yang ada, bukan bikin baru).
- Tidak menyentuh `fop-tasks` index/row — sudah benar, dijadikan referensi pola, bukan target perubahan.
- Tidak eager menambah live-refresh ke halaman selain yang disebut di scope — halaman lain cukup dapat nav instant dari `hx-boost` global, tanpa listener Echo baru.

## Scope

### A. Nav instant — global, semua halaman
`hx-boost="true"` dipasang di level `<body>` layout utama (`resources/views/layouts/app.blade.php`, atau layout sejenis yang jadi parent semua halaman ber-auth). Efeknya otomatis ke SEMUA `<a>`/`<form>` turunan tanpa disentuh satu-satu — tidak perlu whitelist per halaman.

### B. Live-data-refresh — scoped ke halaman berikut

| Halaman | Status Echo saat ini | Perubahan |
|---|---|---|
| `/noc/dashboard` | ada, tapi `fetch(window.location.href)` | ganti ke endpoint `GET /noc/dashboard/stats` (JSON agregat SQL, gantikan 4× `->get()` leaderboard di §1) |
| `/fop` | ada, tapi `fetch(window.location.href)` | ganti ke endpoint `GET /fop/stats` (JSON stat-card) + endpoint partial per container yang perlu HTML, pola sama `refreshFopTaskRow()` |
| `/collector-worksheet` | ada, tapi `fetch(window.location.href)` | ganti ke endpoint `GET /collector-worksheet/summary` (JSON `SUM()` SQL, gantikan narik-penuh-ke-PHP di §7/§11) |
| `/tickets/{id}` | **tidak ada listener sekarang** (dicek: 0 hit) | tambah listener baru channel `tickets.{popId}` + endpoint baru `GET /tickets/{id}/status-panel` (partial HTML kecil: badge status+handler) |
| `/tasks/{id}` | **tidak ada listener sekarang** | tambah listener `TaskStarted`/`TaskCompleted` (filter `task_id` cocok) + endpoint baru `GET /tasks/{id}/status-panel` |

Catatan `fop-tasks/{id}`: tidak ada halaman show (cuma board + row), jadi tidak masuk scope — sudah tercover oleh `refreshFopTaskRow()` existing.

## Arsitektur

```
Backend action (controller/service)
  → event broadcast (ShouldBroadcast, channel per-POP — SUDAH ADA utk 3 dashboard, BARU utk ticket/task detail)
  → browser Echo listener terima event KECIL (cuma id, bukan payload besar)
  → fetch endpoint partial spesifik (JSON kecil utk dashboard, HTML fragment kecil utk status-panel)
  → Alpine assign (dashboard, data JSON) / DOM swap langsung (status-panel, HTML fragment) — TANPA DOMParser-scan-seluruh-halaman seperti pola lama
```

Prinsip tegas:
- Event cuma pembawa sinyal ("ada yang berubah, cek ulang"), bukan pembawa data — fetch tetap jalan ke server, tapi ke endpoint sempit, bukan `window.location.href`.
- Endpoint partial baru tetap WAJIB lewat POP scope (`EffectiveAccessService`) dan policy yang sama seperti halaman penuhnya — partial bukan alasan bypass otorisasi.

### htmx — integrasi
- Install via npm (`htmx.org`), import di `resources/js/app.js` sejajar Alpine, **ikut build step Vite** (bukan CDN) — konsisten dengan cara Alpine dibundel sekarang (CLAUDE.md §Tech Stack).
- `window.Alpine` yang sudah di-set manual di `app.js` tidak disentuh.
- `hx-boost` taruh di `<body>`, swap target `<main id="app-main">` (atau elemen setara kalau sudah ada id lain — perlu dicek saat implementasi).
- `htmx:responseError` listener global: kalau fetch boost gagal, fallback ke navigasi native (`window.location = event.detail.requestConfig.path`) — biar tidak nyangkut blank page.

## Data Flow — Error Handling

- **Endpoint partial gagal** (network/500): log console, biarkan DOM lama (stale) sampai event broadcast berikutnya atau user refresh manual. **Dilarang** fallback ke `window.location.reload()` — itu justru pola lama yang mau dihapus (lihat §12 `analisa-performa-seeder-15k.md`).
- **htmx nav gagal**: fallback native navigate (lihat di atas), bukan diam/blank.
- **Form POST (PRG)**: CLAUDE.md wajib PRG tetap berlaku. htmx boost pada form default ikut redirect response dari browser. Kalau ada handler yang butuh redirect "terasa" tanpa full reload (`HX-Redirect` header), ini diputuskan PER CONTROLLER saat implementasi — spec ini tidak mewajibkan semua controller ganti response-nya, cuma menandai kemungkinan itu perlu dicek satu-satu supaya tidak ada form yang diam-diam balik ke behavior lama (gagal validasi nutup konten tanpa pesan, risiko yang sama dengan masalah modal-vs-halaman-create di CLAUDE.md §Konvensi Kode poin "Aksi baru").

## Testing

- Feature test baru per endpoint partial baru (5 endpoint: `/noc/dashboard/stats`, `/fop/stats`, `/collector-worksheet/summary`, `/tickets/{id}/status-panel`, `/tasks/{id}/status-panel`):
  - Assert bentuk response (JSON shape / HTML fragment yang benar).
  - Assert POP-scope ditegakkan — user di luar scope tidak boleh dapat data POP lain lewat endpoint ini juga (celah yang sama berharga dicek seperti endpoint penuh).
- `PostTargetRenderedServerSideTest` (guard existing) harus tetap lolos tanpa modifikasi — htmx boost tidak boleh membuat form/link merakit URL sendiri.
- Manual verification wajib sebelum dianggap selesai (tidak ada test otomatis untuk ini — butuh browser + koneksi Reverb nyata):
  - Buka 2 tab, role berbeda, trigger event di satu tab, lihat tab lain update tanpa reload.
  - Klik-klik navigasi lintas beberapa halaman berbeda, pastikan URL address bar & tombol back/forward browser tetap benar (htmx boost history).
  - Submit form yang gagal validasi di halaman create (pola §Konvensi Kode poin 2) — pastikan error+input lama tetap muncul, bukan nutup/blank (regresi PRG).

## Risiko & Mitigasi

| Risiko | Mitigasi |
|---|---|
| htmx boost nyangkut di link yang dirender dinamis dari script lain (bukan server-rendered awal) | `hx-boost` re-scan DOM otomatis tiap htmx swap; untuk elemen yang disuntik Alpine murni (`x-if`, dsb) perlu dicek manual saat implementasi apakah perlu `htmx.process()` dipanggil ulang |
| Endpoint partial baru jadi beban tambahan kalau tidak hati-hati (nambah query baru di atas yang sudah ada) | endpoint partial WAJIB pakai agregat SQL (`SUM`, `GROUP BY` di DB), bukan narik baris penuh ke PHP — tujuan eksplisit: endpoint ini harus lebih murah dari full page load yang digantikan, bukan sekadar "lebih kecil responsnya" |
| Form POST kehilangan PRG behavior karena boost | test manual wajib (lihat Testing) + tidak ada controller yang diubah response-nya tanpa dicek satu-satu |
| Channel Echo baru (`tickets.{popId}`, task equivalent) tabrakan dengan channel existing / authorization channel | cek `routes/channels.php` existing saat implementasi, ikuti pola otorisasi channel yang sudah ada, jangan buat channel baru tanpa pengecekan pola `holderRoles()`/POP scope |

## Keputusan Open (diselesaikan saat implementasi, bukan blocker spec)

- Nama/struktur id target swap htmx (`#app-main` atau nama lain) — tergantung struktur layout aktual saat implementasi dimulai.
- Perlu `HX-Redirect` header di controller tertentu atau browser-follow default sudah cukup — dicek per controller yang disentuh.
- Apakah perlu `htmx.process()` manual di titik tertentu yang dirender ulang oleh Alpine (bukan oleh htmx sendiri).
