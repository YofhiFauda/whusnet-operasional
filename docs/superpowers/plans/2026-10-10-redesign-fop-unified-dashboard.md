# Redesign FOP Unified Dashboard (Command Center) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengubah FOP Dashboard (`/fop`) dari tampilan angka statis polos menjadi Unified Operational Command Center satu halaman yang memonitor seluruh modul operasional lapangan (Pipeline Pelanggan, Tiket Gangguan, Task & Tim FOP, Beban Teknisi, Material & Gudang Transit, serta Kapasitas Jaringan ODP) dengan visualisasi modern, kaya data, dan interaktif.

**Architecture:** Membangun `FopUnifiedDashboardService` sebagai single-source-of-truth agregator data dashboard berkinerja tinggi (dengan scalar caching 30–60 detik ber-scope POP via `EffectiveAccessService`). View `resources/views/fop/dashboard.blade.php` dipecah modular ke dalam partials reusable (`_hero_metrics.blade.php`, `_pipeline_funnel.blade.php`, `_teams_board.blade.php`, `_action_queues.blade.php`, `_tickets_telemetry.blade.php`, `_technician_radar.blade.php`, `_inventory_telemetry.blade.php`, `_distribution_glance.blade.php`), dipadukan dengan Alpine.js untuk tab switching instan, visual progress indicators, and slide-over preview drawers tanpa reload.

**Tech Stack:** Laravel 13, PHP 8.4, Tailwind CSS v4, Alpine.js, Blade Components, PHPUnit 12.

**Spec:** `docs/plan/fop/rancangan-fop-unified-dashboard.md` (dan dokumen kontekstual `docs/fop-task/fop-dashboard.md`, `docs/fop-task/database-schema.md`).

## Global Constraints

- Scope POP wajib dihormati 100%: seluruh query data wajib dibatasi oleh `EffectiveAccessService::getAllowedPopIds($user)` / `hasAllPopAccess($user)`. Tidak boleh ada kebocoran data lintas cabang.
- Role & Permission Invariant: FOP guard utama tetap `$this->authorize('viewAll', Task::class)` pada route `GET /fop`. Sub-komponen tiket menghormati `tickets.view`, gudang menghormati `warehouse_custody.view` / `warehouse.view`.
- Larangan Caching Koleksi Eloquent: Hanya nilai skalar (count, sum, array ter-serialisasi aman) yang boleh disimpan di `Cache::remember`. Jangan menyimpan instance `Collection` atau `Model` ke cache karena dapat menyebabkan bug un-serialization `__PHP_Incomplete_Class`.
- SQLite Compatibility: Test berjalan di SQLite in-memory. Hindari fungsi SQL raw MySQL-only seperti `DATE_ADD(...)` atau `NOW()`; gunakan helper query Carbon/Eloquent standar.
- Jangan menambahkan library eksternal JS berat (hindari Chart.js / npm bundle baru jika SVG native + Tailwind CSS bar/gauge dapat menghasilkan performa 10x lebih cepat dan zero build overhead).
- Jalankan `vendor/bin/pint --dirty --format agent` sebelum setiap commit yang memodifikasi file PHP.

## Review Focus

- **Scope POP Bocor pada Modul Tiket / Gudang**: User dengan `selected_pop` (misal Cabang Jetis) tidak boleh melihat tiket, material, atau teknisi dari Cabang Siman di widget mana pun.
- **Performa Query N+1 & Timeout**: Query agregasi lintas modul tidak boleh mengeksekusi puluhan query terpisah per baris. Agregasi harus dilakukan via subquery SQL / `withCount` / eager loading terpilih.
- **Kondisi Empty State (Nol Data)**: Saat POP baru dibuat dan belum memiliki antrean/tiket/task, seluruh widget (funnel, radar teknisi, material) harus menampilkan empty state elegan tanpa error division by zero (`0/0`) atau null pointer exception.
- **Interaktivitas Drag & Drop & Mobile Parity**: Pemindahan task antar tim (drag & drop di desktop, modal picker di mobile) yang sudah ada tidak boleh regresi atau rusak akibat restrukturisasi blade.
- **Responsif di Resolusi Rendah & Mobile**: Layout 2-kolom harus otomatis collapse menjadi single column yang rapi di layar tablet (< 1024px) dan mobile (< 768px).

---

## File Structure

**Baru:**
- `app/Services/FopUnifiedDashboardService.php`: Service agregator untuk seluruh modul dashboard FOP (Pipeline Pelanggan, Tiket Gangguan, Beban Teknisi, Material Lapangan, dan Kapasitas ODP).
- `docs/plan/fop/rancangan-fop-unified-dashboard.md`: Dokumen spesifikasi dan analisa arsitektur komprehensif.
- `resources/views/fop/partials/_hero_metrics.blade.php`: Komponen 6 Hero Metric Tiles dengan visual ring progress & status micro-trend.
- `resources/views/fop/partials/_pipeline_funnel.blade.php`: Komponen visual Stepper Funnel alur pelanggan dari Registrasi s/d Siap Billing.
- `resources/views/fop/partials/_tickets_telemetry.blade.php`: Komponen pantauan tiket gangguan (MTN) & C-REQ darurat terbuka.
- `resources/views/fop/partials/_technician_radar.blade.php`: Komponen radar teknisi dengan progress bar beban tugas, alat kerja (OPM/Splicer), dan status pulse.
- `resources/views/fop/partials/_inventory_telemetry.blade.php`: Komponen stok kritis ONT di cabang & modem retur transit di teknisi.
- `resources/views/fop/partials/_distribution_glance.blade.php`: Komponen ringkasan ODP/ODC dan port kritis di POP.
- `tests/Feature/FopUnifiedDashboardTest.php`: Feature test yang menguji otorisasi, scope POP, seluruh komponen data lintas modul, dan penanganan empty state.

**Modifikasi:**
- `app/Http/Controllers/FopDashboardController.php`: Integrasi `FopUnifiedDashboardService` ke method `index()`, merampingkan controller, dan menyiapkan payload modular.
- `resources/views/fop/dashboard.blade.php`: Refactor total layout menjadi Command Center berbasis modular partials dengan tab interactive Alpine.js.

---

### Task 1: Spesifikasi & Dokumentasi Arsitektur Unified FOP Dashboard

**Files:**
- Create: `docs/plan/fop/rancangan-fop-unified-dashboard.md`

**Interfaces:**
- Produces: Dokumen spesifikasi teknis lengkap yang menjabarkan rancangan UI/UX, arsitektur data, formula metrik, RBAC scope, dan mockup layout 1 halaman.

- [ ] **Step 1: Tulis dokumen spesifikasi rancangan FOP Unified Dashboard**

Tulis dokumen komprehensif di `docs/plan/fop/rancangan-fop-unified-dashboard.md` mencakup:
1. Analisa gap dashboard eksisting vs kebutuhan operasional modern.
2. Wireframe konseptual Command Center 1 halaman (Zona 1 s/d Zona 5).
3. Mapping data per modul (Pelanggan, Tiket, Task/Tim, Teknisi, Material/Gudang, Distribusi ODP).
4. Aturan SLA & Emergency alerting (Pulsing badge, countdown).
5. Aturan scope POP dan kompatibilitas sistem.

- [ ] **Step 2: Review kelengkapan dokumen spesifikasi**

Pastikan seluruh aspek teknis dan formula query tercantum secara jelas tanpa asumsi ambigu.

---

### Task 2: Service Agregator `FopUnifiedDashboardService` (Backend Engine)

**Files:**
- Create: `app/Services/FopUnifiedDashboardService.php`
- Test: `tests/Unit/FopUnifiedDashboardServiceTest.php`

**Interfaces:**
- Produces: `FopUnifiedDashboardService` dengan method:
  - `getCustomerPipelineSummary(bool $hasAllPopAccess, array $allowedPopIds): array`
  - `getHeroMetrics(User $user, bool $hasAllPopAccess, array $allowedPopIds, Carbon $today): array`
  - `getActiveFieldTickets(bool $hasAllPopAccess, array $allowedPopIds): array`
  - `getWarehouseTelemetry(bool $hasAllPopAccess, array $allowedPopIds, Carbon $today): array`
  - `getDistributionSummary(bool $hasAllPopAccess, array $allowedPopIds): array`
  - `getEmergencyAlerts(bool $hasAllPopAccess, array $allowedPopIds): array`

- [ ] **Step 1: Write the failing test**

```php
// tests/Unit/FopUnifiedDashboardServiceTest.php
namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Pop;
use App\Models\Customer;
use App\Models\Ticket;
use App\Services\FopUnifiedDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class FopUnifiedDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function pipeline_summary_menghitung_jumlah_pelanggan_per_stage_sesuai_scope_pop(): void
    {
        $service = app(FopUnifiedDashboardService::class);
        $pop1 = Pop::factory()->create();
        $pop2 = Pop::factory()->create();

        Customer::factory()->create(['pop_id' => $pop1->id, 'status' => 'waiting_survey']);
        Customer::factory()->create(['pop_id' => $pop1->id, 'status' => 'waiting_installation']);
        Customer::factory()->create(['pop_id' => $pop2->id, 'status' => 'waiting_survey']);

        $summary = $service->getCustomerPipelineSummary(false, [$pop1->id]);

        $this->assertEquals(1, $summary['waiting_survey']);
        $this->assertEquals(1, $summary['waiting_installation']);
        $this->assertEquals(0, $summary['calon_pelanggan']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=FopUnifiedDashboardServiceTest`
Expected: FAIL (Class `FopUnifiedDashboardService` not found).

- [ ] **Step 3: Implement `FopUnifiedDashboardService`**

Implementasikan `app/Services/FopUnifiedDashboardService.php` dengan query Eloquent aman (kompatibel SQLite & MySQL), eager-loading yang efisien, dan perhitungan metrik pipeline, tiket, gudang transit, dan ODP.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=FopUnifiedDashboardServiceTest`
Expected: PASS.

- [ ] **Step 5: Format code dengan Laravel Pint**

Run: `vendor/bin/pint --dirty --format agent`

---

### Task 3: Integrasi Controller & View Payload di `FopDashboardController`

**Files:**
- Modify: `app/Http/Controllers/FopDashboardController.php`
- Test: `tests/Feature/FopUnifiedDashboardTest.php`

**Interfaces:**
- Consumes: `FopUnifiedDashboardService`
- Produces: Updated `FopDashboardController@index` yang menyuntikkan variabel dashboard komprehensif (`pipelineSummary`, `heroMetrics`, `fieldTickets`, `warehouseTelemetry`, `distributionSummary`, `emergencyAlerts`) ke view `fop.dashboard`.

- [ ] **Step 1: Write failing feature test**

```php
// tests/Feature/FopUnifiedDashboardTest.php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Pop;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class FopUnifiedDashboardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function fop_user_dapat_mengakses_dashboard_dengan_payload_modul_lengkap(): void
    {
        $roleFop = Role::firstOrCreate(['code' => 'fop'], ['name' => 'FOP']);
        $user = User::factory()->create(['role_id' => $roleFop->id]);
        $pop = Pop::factory()->create();

        $response = $this->actingAs($user)->get(route('fop.dashboard'));

        $response->assertOk();
        $response->assertViewHasAll([
            'pipelineSummary',
            'heroMetrics',
            'fieldTickets',
            'warehouseTelemetry',
            'distributionSummary',
            'emergencyAlerts',
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=FopUnifiedDashboardTest::fop_user_dapat_mengakses_dashboard_dengan_payload_modul_lengkap`
Expected: FAIL (view missing required variables).

- [ ] **Step 3: Update `FopDashboardController.php`**

Inject `FopUnifiedDashboardService` di constructor dan update method `index()` untuk memuat data modular dengan caching skalar terukur.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=FopUnifiedDashboardTest::fop_user_dapat_mengakses_dashboard_dengan_payload_modul_lengkap`
Expected: PASS.

- [ ] **Step 5: Format code dengan Laravel Pint**

Run: `vendor/bin/pint --dirty --format agent`

---

### Task 4: Visualisasi Zona 1 & Zona 2: Emergency Ticker & Hero Metric Tiles

**Files:**
- Create: `resources/views/fop/partials/_hero_metrics.blade.php`
- Modify: `resources/views/fop/dashboard.blade.php`
- Test: `tests/Feature/FopUnifiedDashboardVisualTest.php`

**Interfaces:**
- Consumes: `$emergencyAlerts`, `$heroMetrics`, `$stats`
- Produces: Header bar dengan alert ticker berdenyut dan 6 kartu metrik dengan visual ring/progress bar, status badge, dan micro-indikator.

- [ ] **Step 1: Write test for hero metrics and alert ticker rendering**

```php
// tests/Feature/FopUnifiedDashboardVisualTest.php
#[Test]
public function hero_metrics_dan_emergency_ticker_terender_dengan_benar(): void
{
    $roleFop = Role::firstOrCreate(['code' => 'fop'], ['name' => 'FOP']);
    $user = User::factory()->create(['role_id' => $roleFop->id]);

    $response = $this->actingAs($user)->get(route('fop.dashboard'));

    $response->assertOk();
    $response->assertSee('hero-metrics-grid', false);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=FopUnifiedDashboardVisualTest`
Expected: FAIL.

- [ ] **Step 3: Implement `resources/views/fop/partials/_hero_metrics.blade.php`**

Bangun komponen kartu hero metrics berdesain modern (Tailwind v4, CSS vars, progress ring SVG / bars, micro-sparkline indicators, dark mode support).

- [ ] **Step 4: Pasang di `resources/views/fop/dashboard.blade.php` dan tes**

Run: `php artisan test --filter=FopUnifiedDashboardVisualTest`
Expected: PASS.

---

### Task 5: Visualisasi Zona 3: Customer Delivery Pipeline (Stepper Funnel)

**Files:**
- Create: `resources/views/fop/partials/_pipeline_funnel.blade.php`
- Modify: `resources/views/fop/dashboard.blade.php`
- Test: `tests/Feature/FopUnifiedDashboardVisualTest.php`

**Interfaces:**
- Consumes: `$pipelineSummary`
- Produces: Stepper funnel visual horizontal interaktif (Registrasi -> Menunggu Survey -> Survey Selesai/ACC -> Menunggu Pasang -> Sedang Dipasang -> Verifikasi Admin -> Aktif) dengan counter badge, dwell alert, dan direct link ke filter status.

- [ ] **Step 1: Write test for pipeline funnel rendering**

```php
#[Test]
public function pipeline_funnel_menampilkan_alur_onboarding_pelanggan(): void
{
    $roleFop = Role::firstOrCreate(['code' => 'fop'], ['name' => 'FOP']);
    $user = User::factory()->create(['role_id' => $roleFop->id]);

    $response = $this->actingAs($user)->get(route('fop.dashboard'));

    $response->assertSee('customer-pipeline-funnel', false);
    $response->assertSee('Antrean Survey');
    $response->assertSee('Pemasangan');
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=FopUnifiedDashboardVisualTest::pipeline_funnel_menampilkan_alur_onboarding_pelanggan`
Expected: FAIL.

- [ ] **Step 3: Implement `resources/views/fop/partials/_pipeline_funnel.blade.php`**

Bangun visualisasi stepper pipeline horizontal yang responsive (scrollable di mobile, flex-grid di desktop) dengan konektor garis panah halus, indikator kuantitas, dan warna status dinamis.

- [ ] **Step 4: Integrasikan ke `resources/views/fop/dashboard.blade.php` dan tes**

Run: `php artisan test --filter=FopUnifiedDashboardVisualTest::pipeline_funnel_menampilkan_alur_onboarding_pelanggan`
Expected: PASS.

---

### Task 6: Visualisasi Zona 4 (Kolom Kiri): Tactical Execution Board (Tabbed Live Center)

**Files:**
- Create: `resources/views/fop/partials/_action_queues.blade.php`
- Create: `resources/views/fop/partials/_tickets_telemetry.blade.php`
- Modify: `resources/views/fop/dashboard.blade.php`
- Test: `tests/Feature/FopUnifiedDashboardVisualTest.php`

**Interfaces:**
- Consumes: `$activeFopTeams`, `$surveyQueue`, `$fieldTickets`
- Produces: Multi-tab layout interaktif di kolom kiri (Tab 1: Tim FOP & Drag-Drop Dispatch, Tab 2: Antrean Perlu Aksi FOP dengan countdown timer, Tab 3: Tiket Gangguan/C-REQ Lapangan) dengan perpindahan tab instan via Alpine.js.

- [ ] **Step 1: Write test for tabbed execution board**

```php
#[Test]
public function tabbed_execution_board_memiliki_tab_tim_antrean_dan_tiket(): void
{
    $roleFop = Role::firstOrCreate(['code' => 'fop'], ['name' => 'FOP']);
    $user = User::factory()->create(['role_id' => $roleFop->id]);

    $response = $this->actingAs($user)->get(route('fop.dashboard'));

    $response->assertSee('tactical-execution-tabs', false);
    $response->assertSee('Tiket Gangguan & C-REQ');
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=FopUnifiedDashboardVisualTest::tabbed_execution_board_memiliki_tab_tim_antrean_dan_tiket`
Expected: FAIL.

- [ ] **Step 3: Implement partials dan perbaiki styling board tim eksisting**

Buat `_action_queues.blade.php` dan `_tickets_telemetry.blade.php`. Integrasikan ke dalam layout tabbed di `resources/views/fop/dashboard.blade.php`. Pastikan fungsi drag & drop dan mobile switch modal tetap berfungsi tanpa kendala.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=FopUnifiedDashboardVisualTest::tabbed_execution_board_memiliki_tab_tim_antrean_dan_tiket`
Expected: PASS.

---

### Task 7: Visualisasi Zona 4 (Kolom Kanan): Resource & Asset Telemetry (Teknisi, Material, ODP)

**Files:**
- Create: `resources/views/fop/partials/_technician_radar.blade.php`
- Create: `resources/views/fop/partials/_inventory_telemetry.blade.php`
- Create: `resources/views/fop/partials/_distribution_glance.blade.php`
- Modify: `resources/views/fop/dashboard.blade.php`
- Test: `tests/Feature/FopUnifiedDashboardVisualTest.php`

**Interfaces:**
- Consumes: `$teknisiList`, `$warehouseTelemetry`, `$distributionSummary`
- Produces: Tiga widget telemetry vertikal di kolom kanan:
  1. Live Radar Teknisi (Beban kerja, status pulse, alat kerja OPM/Splicer).
  2. Telemetry Material & Gudang (Pemakaian hari ini, stok kritis ONT, modem retur transit di teknisi).
  3. Quick Glance Jaringan Distribusi (ODP/ODC port hampir penuh).

- [ ] **Step 1: Write test for right column telemetry widgets**

```php
#[Test]
public function telemetry_kanan_menampilkan_radar_teknisi_stok_dan_odp(): void
{
    $roleFop = Role::firstOrCreate(['code' => 'fop'], ['name' => 'FOP']);
    $user = User::factory()->create(['role_id' => $roleFop->id]);

    $response = $this->actingAs($user)->get(route('fop.dashboard'));

    $response->assertSee('technician-radar-widget', false);
    $response->assertSee('inventory-telemetry-widget', false);
    $response->assertSee('distribution-glance-widget', false);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=FopUnifiedDashboardVisualTest::telemetry_kanan_menampilkan_radar_teknisi_stok_dan_odp`
Expected: FAIL.

- [ ] **Step 3: Implement ketiga partials telemetry kolom kanan**

Desain antarmuka visual premium dengan badge status berdenyut, progress bar rasio kapasitas, chip alat kerja, dan peringatan level stok kritis.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=FopUnifiedDashboardVisualTest::telemetry_kanan_menampilkan_radar_teknisi_stok_dan_odp`
Expected: PASS.

---

### Task 8: Regresi, Polish Interaktivitas & Verifikasi Akhir

**Files:**
- Modify: `resources/views/fop/dashboard.blade.php`
- Run: Seluruh test suite FOP (`FopDashboardTest`, `FopDashboardPastTeamsTest`, `FopUnifiedDashboardTest`, `FopUnifiedDashboardVisualTest`)

- [ ] **Step 1: Jalankan seluruh test FOP terkait**

Run: `php artisan test --filter=Fop`
Expected: Seluruh test hijau (PASS) tanpa regresi pada drag & drop, switch team, filter POP, atau query lampau.

- [ ] **Step 2: Lakukan audit format kode dengan Laravel Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 3: Uji konsistensi styling Dark Mode dan Responsivitas Mobile**

Pastikan seluruh komponen menggunakan token warna standar proyek (`var(--color-primary)`, `var(--color-surface)`, `var(--color-text-main)`, dll) dan tersusun fleksibel di layar smartphone.
