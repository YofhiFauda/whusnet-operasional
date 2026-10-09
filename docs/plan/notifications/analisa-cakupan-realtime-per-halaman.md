# Analisa Cakupan Realtime per Halaman

**Tanggal:** 2026-10-06
**Sifat:** Audit kode aktual (grep `Echo.private`, `location.reload`, `window.Toast`). Belum dibaca baris per baris dan belum diuji di browser.
**Pelengkap:** `analisa-status-implementasi-notifikasi.md` (notifikasi personal/lonceng) dan `../analisa-realtime-spa-operasional.md` (rencana SPA-like; sebagian sudah usang, lihat §4).

---

## 1. Lonceng Notifikasi (Personal)

Global. `components/notification-dropdown.blade.php` subscribe `App.Models.User.{id}` lewat Echo dan tampil di navbar, jadi tersedia di halaman mana pun. Toast muncul saat notifikasi masuk (§8.9 status doc). Hanya event yang dikirim lewat `AppNotification` yang memicu toast ini.

---

## 2. Halaman Sudah Realtime (Listener Echo di Halaman)

| Halaman | Channel | Catatan |
|---|---|---|
| `verifications/queue` | `customers.{popId}` | `CustomerVerificationStatusChanged` |
| `invoices/index` | `invoices.{popId}` | `InvoiceStatusUpdated`. Tidak ada `reload()` lagi |
| `fop/dashboard` | `fop.{popId}` | |
| `fop_tasks/index` | `fop-tasks.{popId}` | `FopTaskUpdated` |
| `tasks/own` (teknisi) | `teknisi.{userId}` | |
| `noc/dashboard` | `tickets.{popId}` | `TicketQueueUpdated` |
| `tickets/create` | `tickets.{popId}` | |
| `tickets/partials/archive` | `tickets.{popId}` | |
| `customers/show` | `invoices.{popId}` | Hanya update tagihan |
| `partials/collector-realtime` | Dinamis (`window.Echo.private(nama)`) | Dipakai di halaman kolektor |

---

## 3. Halaman Masih Reload / Refresh Manual

| Lokasi | Pemicu | Rencana terkait |
|---|---|---|
| `fop/dashboard.blade.php:995, 999` | Reload setelah aksi | Doc realtime #12 (tombol "Status Teknisi") |
| `fop_tasks/index.blade.php:1446` | `setTimeout(reload, 800)` setelah reassign tim | Doc realtime #13. Halaman sudah punya Echo, tinggal update state Alpine |
| `collector-worksheet/show.blade.php:1199` | `setTimeout(reload, 1200)` | |
| `partials/collector-pay-script.blade.php:366` | `setTimeout(reload, 1500)` setelah bayar | |
| `technician-payments/index.blade.php:265` | Reload | |
| `customers/qr/show.blade.php:346, 380` | Reload | |
| `payments/partials/quick-payment-modal.blade.php:878` | Reload setelah bayar | |
| `tickets/history.blade.php:75` | Tombol reload manual | |

---

## 4. Halaman Belum Realtime (Tanpa Echo)

- `dashboard.blade.php`: KPI counter (doc realtime #9). Punya Toast tapi tanpa subscription.
- `customers` (list/index) dan `customers/show` selain tagihan.
- `payments/show`, `invoices/show`: punya Toast, tidak ada listener data.
- `master/sla-timeline`, `installations/report`, `roles/*`, `warehouse/*` (create, scan, transfer, issue, receive).
- Audit log live feed (doc realtime #8): view belum ada.

---

## 5. Gap Toast

Halaman yang sudah subscribe Echo tapi event data-nya tidak memicu toast kecuali ada `AppNotification` yang menyertainya:
`verifications/queue`, `tasks/own`, `invoices/index`, `fop_tasks/index`, `noc/dashboard`, `tickets/*`.

Keputusan belum diambil: apakah perubahan data di halaman cukup dengan update baris (tanpa toast), atau perlu toast tambahan.

---

## 6. Koreksi Dokumen Lama

- `analisa-realtime-spa-operasional.md` #11 (konfirmasi bayar invoice pakai `reload`): sudah tidak ada di `invoices/index`. Status: sudah realtime.
- `analisa-realtime-spa-operasional.md` #13 (reassign tim FOP): masih reload di `fop_tasks/index.blade.php:1446`. Status: belum.
- `analisa-realtime-spa-operasional.md` #12 (tombol refresh "Status Teknisi"): masih ada di `fop/dashboard`. Status: belum.

---

## 7. Langkah Lanjut (Usulan)

1. Verifikasi di browser per halaman §2 dan §3 (grep belum cukup).
2. Ganti reload di §3 dengan update state Alpine, dimulai dari `fop_tasks/index` (#13) dan `fop/dashboard` (#12).
3. Putuskan kebijakan toast untuk halaman §2 (lihat §5).
4. Tambah listener untuk halaman §4 sesuai prioritas di `analisa-realtime-spa-operasional.md`.
