# Analisa Dashboard Owner — Fase 2 (Cakupan Seluruh Modul)

> Status: disetujui user 2026-11-23 (P1, P2, P3 semua item). P4 (kesehatan
> sistem) & perubahan Operational Pulse **belum** di-acc — jangan dikerjakan.
> Task: ADHOC-313. Kelanjutan `docs/plan/analisa-dashboard-owner-statistik.md`
> (6 Pilar, selesai 2026-09-10).

## 1. Kondisi Sekarang (Diagnosa)

`DashboardController::index()` sudah memuat 6 Pilar: KPI strip, keuangan & kas,
funnel & pertumbuhan, ticketing & SLA, delivery FOP (Survey/PSB saja), stok
kritis gudang. Audit cakupan modul (route `permission:*` × `app/Models`)
menemukan **13 area yang sama sekali tidak terlihat** di dashboard, padahal
modulnya sudah jalan di produksi:

| Area | Kenapa jadi masalah |
|---|---|
| Isolir (`isolation_cases`, ADHOC 2026-11-19) | Kasus isolir/pemulihan gagal = pelanggan bayar tapi masih terisolir (atau sebaliknya) — tak ada yang lihat kecuali buka antrean isolir |
| Putus langganan & DEAC | Alasan putus tak pernah teragregasi; alat belum ditarik = aset hilang diam-diam |
| Verifikasi tertunda (registrasi, BD, C-REQ billing) | Antrean tersebar di 3 halaman — Owner tak tahu ada bottleneck |
| Pembebasan tagihan (`customer_billing_waivers`) | Periode yang sengaja tidak ditagih tak terlihat |
| Rekonsiliasi jual modem | Modem terjual belum ditagih = kebocoran pendapatan |
| MRR / ARPU / churn % | Dashboard cuma punya angka mentah putus, bukan rasio |
| Upgrade/downgrade paket | Arah pergeseran pendapatan bulanan tak terlihat |
| Kinerja kolektor (visit log) | Cuma posisi kas, bukan efektivitas kunjungan |
| Akuisisi per agen | Omset per segmen ada, per agen tidak |
| Okupansi port jaringan | Tak ada sinyal kapan ODP perlu ekspansi |
| Persilangan kabel & pemulihan material | Kabel terpasang di pelanggan putus + material menunggu inspeksi |
| Task MTN / C-REQ / INFR REQ, pending, lapor nanti | Blok FOP cuma Survey/PSB |
| Antrean gudang (stock request, pengajuan barang) | Permintaan menggantung tanpa terlihat |

### Akar masalah
Dashboard dirancang 2026-09 saat modul di atas belum ada / belum stabil. Tiap
modul baru menambah halaman list sendiri tanpa menyumbang ringkasan ke
dashboard. Bukan bug, tapi utang cakupan.

### Temuan sampingan (jangan diandalkan)
- `customer_services.service_status` dipakai dengan nilai `'active'` **dan**
  `'aktif'` di kode → MRR **tidak** boleh difilter dari kolom ini. Pakai
  `customers.status = 'active'` (sama dengan KPI Pelanggan Aktif).
- `customer_billing_waivers` **tidak punya kolom nominal** → yang ditampilkan
  jumlah periode dibebaskan per sumber (`BillingWaiverSource`), bukan rupiah.
- Feature `billing_waivers` cuma punya `.create`/`.delete` (tak ada `.view`)
  → gerbang kartu waiver pakai `invoices.view` (waiver = urusan tagihan).

## 2. Prinsip (tetap dari Fase 1)
1. **Satu kartu ringkas + link drilldown** ke halaman modulnya. Tidak ada tabel
   detail baru — dashboard sudah 1100 baris Blade.
2. **Gerbang = permission halaman modulnya sendiri.** Tanpa permission baru.
3. **Semua query lewat POP scope** (`applyUserScope()` / `EffectiveAccessService`)
   + filter dropdown POP dashboard.
4. **Reuse service yang sudah ada** supaya aturan tidak ditulis dua kali:
   `ModemSaleReconciliationService::rows()/summary()`,
   `DeviceRetrievalReviewService::pendingForUser()`, grup
   `IsolationCaseStatus::*Group()`.
5. Logic dipindah ke **`app/Services/DashboardModuleStatsService.php`** (bukan
   ditambah ke controller yang sudah 1060 baris). Hasil berupa array angka →
   ikut `Cache::remember` 60 detik yang sudah ada.

## 3. Rincian Metrik

| # | Kartu | Sumber & formula | Gerbang | Drilldown |
|---|---|---|---|---|
| 1 | Isolir | `IsolationCase` scoped: hitung per grup waiting / ready (termasuk `ISOLATION_FAILED`) / `SUSPENDED` / restoration; plus `ISOLATION_FAILED` + `RESTORATION_FAILED` sebagai alert | `customers.isolir_queue.view` | `customers.isolir-queue.index` |
| 2 | Putus & DEAC | Top 5 `termination_reason_id` pelanggan `terminated_at` dalam periode; Task `DEAC` status antrean; `pendingForUser()` review penarikan | `customers.terminated.view` (alasan), `device_retrieval_review.view` (review), `task.view.all` (task DEAC) | halaman terminated / review penarikan |
| 3 | Verifikasi tertunda | `Customer` status `REGISTERED`; status `WAITING_BUSINESS_DEVELOPMENT_VERIFICATION`; Task C-REQ/MTN billable dengan detail `verification_status = pending` | `customer_registration_verification.view`, `business_development_verification.view`, `creq_billing_verification.view` | halaman masing-masing |
| 4 | Pembebasan tagihan | `CustomerBillingWaiver` periode dashboard (`billing_period` between from–to), group `source`, scoped via `customer` | `invoices.view` | — |
| 5 | Modem belum ditagih | `ModemSaleReconciliationService::summary(rows($user, ['pop_id'=>…]))` → `anomaly`, `waiting`, `unbilled_amount` | `modem_sale_reconciliation.view` | `modem-sale-reconciliation.index` |
| 6 | MRR / ARPU / Churn | MRR = Σ `customer_services.total_monthly_bill` milik customer `status=active` (scoped). ARPU = MRR / pelanggan aktif. Churn % = putus periode / (aktif sekarang + putus periode − aktif baru periode) | `dashboard.view` | — |
| 7 | Perubahan paket | `CustomerPackageChange` `billing_period` dalam periode, scoped via customer: upgrade (new > old), downgrade (new < old), Σ (new − old) | `dashboard.view` | — |
| 8 | Kinerja kolektor | `CollectorVisit` (HasPopScope) `visited_at` dalam periode, group `result`; konversi = bayar / total; janji lewat = `janji_bayar` + `promised_date` < hari ini + tak ada pembayaran pelanggan sejak `visited_at` | `collector_report.view` | `collector-report.*` |
| 9 | Akuisisi per agen | `CustomerAcquisition` `periode` dalam periode, join `customers.agent_id`, top 5 | `dashboard.view` | — |
| 10 | Okupansi port | `NetworkNodePort` join `network_nodes.anchor_pop_id` (scope via `NetworkNode::scopeInPops()`): % `is_used`; jumlah node ≥ 90% terisi | `network_nodes.view` | `network-nodes.index` |
| 11 | Kabel & material | `CustomerCableCrossing` `TERPASANG` milik pelanggan putus + `TIDAK_BISA_DITARIK`; `InfrastructureRecovery` `pending_inspection` | `customer_cable_crossings.view` / `warehouse_reassign.create` (grup route halaman Pemulihan Material) | halaman masing-masing |
| 12 | Task non-PSB | Task MTN / C-REQ / INFR REQ: selesai periode + masih antrean; total `PENDING` & `LAPOR_NANTI` | `task.view.all` | `fop.dashboard` |
| 13 | Antrean gudang | `StockRequest` `PENDING` (`cabang_pop_id` ∈ warehouse POP); `PengajuanBarang` `MENUNGGU_FOP` (`pop_id`) | `warehouse_stock_request.view` / `warehouse_pengajuan_barang.view` | halaman masing-masing |

Action Center ("Perlu Segera Ditindak") ditambah: isolir/pemulihan gagal,
verifikasi tertunda, review penarikan menunggu, modem anomali.

## 4. Tahapan
1. P1 (#1–5) + test → 2. P2 (#6–9) + test → 3. P3 (#10–13) + test.
Tiap tahap: `npm run build`, pint, test file dashboard terkait saja.

## 5. Status Implementasi (2026-11-23)

✅ P1, P2, P3 selesai sekaligus (bukan 3 tahap terpisah).

| Bagian | Lokasi |
|---|---|
| Hitungan | `app/Services/DashboardModuleStatsService.php` |
| Gerbang + rakitan | `DashboardController::moduleAccess()` / `moduleStats()` → `$stats['modules']` (key gerbang tertutup tidak ada sama sekali) |
| Okupansi port | `FopUnifiedDashboardService::getOdpSummary()` ditambah key `total_ports`/`used_ports` (key lama tetap) |
| View | `dashboard.blade.php` Row 2B (tab Keuangan), Row 3B (tab Pemasangan & Gangguan), antrean gudang di panel Gudang Row 4, +4 alert Action Center; komponen `x-dashboard.module-card` / `module-row` |
| Test | `tests/Feature/DashboardModuleCoverageTest.php` (17 test) |

Temuan saat implementasi: `collector_visits.visited_at` kolom **DATE** —
filter periode wajib pakai batas `toDateString()`, batas datetime membuat
kunjungan tanggal 1 lolos dari filter di sqlite.

## 6. Risiko
- Query count dashboard naik → semua angka masuk cache 60 detik; ada
  `DashboardFopQueryCountTest` sebagai pembanding pola.
- `ModemSaleReconciliationService::rows()` memuat Collection → dipanggil di
  dalam closure cache, yang disimpan cuma array `summary()`.
- Nama route drilldown wajib dicek `route:list` sebelum dipakai.
