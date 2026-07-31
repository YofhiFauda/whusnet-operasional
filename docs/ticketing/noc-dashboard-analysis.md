# Analisa Kekurangan Dashboard NOC

Analisa per 2026-07-31 terhadap `NocDashboardController.php` + `resources/views/noc/dashboard.blade.php`. Tujuan: jadi basis pengembangan lanjutan Dashboard NOC agar layak dipakai analisa oleh Tim NOC, bukan cuma monitoring snapshot.

## Kondisi Saat Ini

- Semua "grafik" berupa progress-bar/meter (`div` width %) — bukan chart library. Tidak ada Chart.js atau sejenis di-load di view.
- Data yang ditampilkan: stat card (total, selesai, assign FOP, dibatalkan, avg durasi), statistik per daerah (meter), statistik per issue category (meter), trend matrix daerah x issue (tabel angka polos), list tiket aktif (aging queue), activity feed (log mentah).
- Filter tersedia: date preset (7 hari/30 hari/bulan ini/bulan lalu/custom/semua waktu) + POP scope.

## Kekurangan

1. **No chart library** — semua visual cuma meter linear, gak representatif buat pola/distribusi data kompleks.
2. **No time-series** — angka cuma snapshot total periode terpilih. Gak ada tren harian/mingguan (naik/turun volume tiket, lonjakan issue tertentu di hari/tanggal tertentu).
3. **No SLA visibility** — kolom `handling_sla_hours` ada di `fop_tasks` tapi dashboard NOC gak render breach rate / on-time %. Ini seharusnya jadi metrik inti NOC.
4. **No aging distribution** — list tiket aktif urut aging (`created_at asc`), tapi gak ada ringkasan bucket (0-8 jam / 8-24 jam / >24 jam). Pola aging cuma keliatan kalau scroll manual satu-satu.
5. **Trend matrix cuma tabel angka** — bukan heatmap visual proper (warna intensitas), dan statis per periode terpilih — gak bisa lihat pergeseran pola over time (mis. "issue X di daerah Y naik minggu ini vs minggu lalu").
6. **No period-over-period comparison** — semua angka absolut tanpa indikator delta (↑/↓ % vs periode sebelumnya).
7. **No per-petugas NOC performance** — activity feed cuma log mentah per event, gak ada agregat "siapa handle berapa tiket, rata-rata durasi penanganan per orang".

## Rekomendasi Pengembangan

- Pasang Chart.js (atau library chart yang sudah dipakai di modul lain kalau ada), tambahkan:
  - **Line chart** — volume tiket masuk/selesai/dibatalkan per hari sepanjang periode terpilih.
  - **Donut/gauge chart** — SLA compliance (on-time vs breach), sumber `handling_sla_hours`.
  - **Bar chart** — distribusi aging tiket aktif per bucket (0-8j / 8-24j / >24j).
  - **Heatmap chart proper** — ganti tabel trend matrix jadi heatmap visual (bukan tabel angka + warna badge manual).
  - **Leaderboard** — performa per petugas NOC (jumlah tiket handled, rata-rata durasi penanganan).
- Tambah indikator delta % vs periode sebelumnya di tiap stat card (total tiket, selesai, assign FOP, dibatalkan).

## Catatan Implementasi

- Belum ada keputusan prioritas — perlu konfirmasi Tim NOC/user mana yang paling dibutuhkan duluan sebelum eksekusi.
- Kalau nanti dieksekusi: cek dulu apakah ada chart library lain yang sudah dipakai di project (hindari nambah dependency baru tanpa approval, sesuai Laravel Boost guideline "jangan ubah dependency tanpa approval").
- Semua query tambahan wajib tetap lewat POP scope (`applyUserScope()`) — konsisten sama pola yang sudah ada di controller ini.
