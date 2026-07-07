# Analisa: Tracking Material/Alat & Biaya per Task FOP

Status: Post-MVP (belum diimplementasi)

## Latar Belakang

Dashboard FOP (`FopDashboardController.php`) saat ini hanya track status & SLA task
(antrean survey, overdue, tim aktif). Belum ada tracking pemakaian material/alat per
kategori task (SRV, PSB, MTN, O-REQ, C-REQ, INFR — lihat `App\Enums\TaskType`),
sehingga belum bisa tahu pengeluaran alat per kategori pekerjaan.

Cek codebase: tidak ada tabel/model inventory sama sekali di `app/Models`. Modul ini
harus dibangun dari nol, bukan modifikasi dashboard yang ada.

## Struktur Data yang Diperlukan

1. **`items`** — master alat/material
   - nama, satuan, harga_satuan, kategori (kabel, ONT/modem, tray, konektor, dll), stok.
2. **`fop_task_materials`** (pivot)
   - `fop_task_id`, `item_id`, `qty_dipakai`, `harga_saat_pakai` (snapshot harga saat
     dipakai — jaga histori biar gak berubah kalau harga item naik belakangan).
3. Input qty material saat teknisi selesaikan task (integrasi ke flow completion
   `Task` / evidence upload) — link ke `FopTask` via `task_id`.

## Penambahan Dashboard (setelah struktur data ada)

- Card total pengeluaran material hari ini/bulan ini, breakdown per kategori task
  (SRV/PSB/MTN/O-REQ/C-REQ/INFR).
- Chart tren pengeluaran per kategori per bulan — deteksi MTN mahal terus-menerus
  jadi sinyal masalah infrastruktur di POP tertentu.
- Rata-rata cost per task per kategori (cost efficiency antar teknisi/tim).
- Stock alert kalau item di bawah ambang minimum.
- Pemakaian material per teknisi (audit, cegah pemakaian gak wajar).

## Rekomendasi Tambahan (di luar cost material)

- SLA compliance rate per kategori — persentase on-time vs overdue. Data mentah
  sudah ada lewat `FopTask::slaDeadline()`, tinggal agregasi.
- Recurring MTN per pelanggan/POP dalam rentang waktu N hari — sinyal instalasi/
  infrastruktur bermasalah, bukan insiden acak.
- Rata-rata waktu penyelesaian per kategori vs SLA target (`TaskType::slaMinutes()`)
  — evaluasi kapasitas tim.
- First-time-fix rate untuk MTN — persentase MTN yang tidak perlu revisit.

## Prioritas Implementasi

1. Bikin modul material (master item + pivot `fop_task_materials`) — dashboard cost
   baru bisa jalan kalau data input sudah ada.
2. Baru lanjut ke dashboard cards/chart pengeluaran.
3. Metrik SLA/recurring-MTN bisa jalan duluan karena data sumbernya (task, customer)
   sudah tersedia sekarang.
