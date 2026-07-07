> **Arsip.** Dokumen historis, sebagian sudah tidak sesuai kode aktif (lihat [../README.md](../README.md) untuk dokumentasi terkini).

# FOP & Teknisi Dashboard — Spesifikasi Desain

**Sistem:** WHUSNET Admin Payment  
**Konteks:** Unified task management untuk Field Operation Planner (FOP) dan Teknisi  
**Tujuan:** Mengganti alur multi-halaman menjadi satu dashboard per role agar lebih efisien

---

## 1. Latar Belakang & Masalah

### Kondisi Lama

| Aktor | Masalah |
|-------|---------|
| FOP | Tidak ada visibility real-time ke status teknisi di lapangan |
| FOP | Harus cek status pelanggan satu per satu di list Pelanggan |
| FOP | Tombol "Proses ke TIM" tersebar di halaman detail pelanggan |
| Teknisi | Harus buka banyak halaman hanya untuk melihat jadwal dan mengisi laporan |
| Teknisi | Alur Survey → Laporan → Pemasangan → Laporan memerlukan navigasi halaman berulang |

### Solusi

Dua dashboard berbasis role dengan **Task Card** sebagai unit kerja utama. Semua aksi dilakukan inline via slide-over/modal tanpa pindah halaman. State machine `customers.status` tetap jadi single source of truth — dashboard hanya men-surface state tersebut dengan lebih baik.

---

## 2. Alur Workflow (State Machine)

```
Registrasi
    ↓  ← ⏱ Countdown Survey mulai: 1×24 jam harus disurvey
Survey Terjadwal      ←── FOP assign teknisi + jadwal
    ↓
Survey Berjalan       ←── Teknisi: "Mulai Survey" → started_at dicatat → countdown SLA eksekusi aktif (120 menit)
    ↓
Survey Selesai        ←── Teknisi: submit Laporan Survey → completed_at dicatat → Waktu Survey terhitung
    ↓  ← ⏱ Countdown Verifikasi mulai: 3×24 jam harus diverifikasi & dipasang
Verifikasi Admin      ←── FOP: review laporan → "Proses ke TIM"
    ↓
Pemasangan Terjadwal  ←── FOP assign teknisi + jadwal
    ↓
Pemasangan Berjalan   ←── Teknisi: "Mulai Pemasangan" → started_at dicatat → countdown SLA eksekusi aktif (240 menit)
    ↓
Pemasangan Selesai    ←── Teknisi: submit Laporan Pemasangan → completed_at dicatat → Waktu Pemasangan terhitung
    ↓
Aktivasi
```

### Ringkasan Aturan Countdown

| Countdown | Mulai Dari | Batas Waktu | Tampil Di |
|-----------|-----------|-------------|-----------|
| **Survey** | Tanggal registrasi pelanggan | **1×24 jam** | FOP Dashboard — antrean survey |
| **Verifikasi/Pemasangan** | Survey selesai (`completed_at`) | **3×24 jam** | FOP Dashboard — kolom Perlu Aksi FOP |
| **SLA Eksekusi Survey** | Teknisi tekan "Mulai Survey" | **120 menit** | Task card Teknisi & FOP Kanban |
| **SLA Eksekusi Pemasangan** | Teknisi tekan "Mulai Pemasangan" | **240 menit** | Task card Teknisi & FOP Kanban |

> **Catatan:** Tidak ada perubahan pada `customers.status` — kolom ini tetap backbone state machine. Dashboard hanya membaca dan memperbarui state yang sudah ada.

---

## 3. FOP Dashboard

### 3.1 Layout Utama

```
┌────────────────────────────────────────────────────────┐
│  Antrian Survey: 8   Menunggu TIM: 3   Selesai: 5      │  ← Stat cards
├────────────────────────────────────────────────────────┤
│  [Semua] [Survey] [Pemasangan] [Verifikasi]  [+ Jadwal]│  ← Filter + aksi
├──────────┬──────────┬──────────┬────────────────────────┤
│ Terjadwal│ Berjalan │ Aksi FOP │ Selesai               │  ← Kanban pipeline
│          │          │          │                        │
└──────────┴──────────┴──────────┴────────────────────────┘
│  Status Teknisi (tabel real-time)                       │
└────────────────────────────────────────────────────────┘
```

### 3.2 Kolom Kanban Pipeline

| Kolom | Kondisi `customers.status` | Aksi yang Tersedia |
|-------|---------------------------|-------------------|
| **Terjadwal** | `survey_scheduled`, `install_scheduled` | Lihat detail, Reschedule, Assign ulang teknisi |
| **Sedang Berjalan** | `survey_in_progress`, `install_in_progress` | Lihat progress real-time (via Reverb) |
| **Perlu Aksi FOP** | `survey_done` (menunggu verifikasi) | **Proses ke TIM** (buka slide-over) |
| **Selesai** | `active` | Lihat ringkasan |

### 3.3 Fitur "Proses ke TIM" (Slide-Over Inline)

Menggantikan tombol yang sebelumnya ada di halaman detail pelanggan terpisah. FOP mengklik task card → slide-over muncul di atas halaman yang sama:

```
┌──────────────────────────────────────┐
│  Proses ke TIM                       │
│  Dewi Lestari · Survey selesai       │
│                                      │
│  ℹ Hasil: Layak — Fiber, 320m       │  ← Ringkasan laporan survey
│                                      │
│  Assign Teknisi: [dropdown]          │
│  Jadwal Pemasangan: [date picker]    │
│  Catatan untuk teknisi: [textarea]   │
│                                      │
│  [Batal]      [Konfirmasi Proses]    │
└──────────────────────────────────────┘
```

### 3.4 Status Teknisi Real-Time

Tabel di bawah kanban menampilkan status semua teknisi yang terhubung ke POP yang dikelola FOP:

| Kolom | Sumber Data |
|-------|-------------|
| Nama teknisi | `users` table |
| Status (Aktif / Standby) | Broadcast via Laravel Reverb event `TechnicianStatusUpdated` |
| Task aktif | Join ke `customer_tasks` atau query `customers` berdasarkan status aktif |
| Lokasi | Field `current_location` yang diupdate teknisi saat mulai task |

### 3.5 Route & Controller

```php
// routes/web.php
Route::middleware(['auth', 'permission:fop.dashboard'])
    ->prefix('fop')
    ->name('fop.')
    ->group(function () {
        Route::get('/', [FopDashboardController::class, 'index'])->name('dashboard');
        Route::get('/tasks', [FopDashboardController::class, 'tasks'])->name('tasks');
        Route::post('/tasks/{customer}/process-to-tim', [FopDashboardController::class, 'processToTim'])->name('process-to-tim');
        Route::post('/tasks/{customer}/schedule', [FopDashboardController::class, 'schedule'])->name('schedule');
    });
```

```php
// app/Http/Controllers/FopDashboardController.php
class FopDashboardController extends Controller
{
    public function tasks(Request $request): JsonResponse
    {
        // Group customers by status untuk kanban
        $pipeline = Customer::query()
            ->whereIn('status', [
                'survey_scheduled',
                'survey_in_progress',
                'survey_done',
                'install_scheduled',
                'install_in_progress',
                'active',
            ])
            ->with(['assignedTechnician', 'pop'])
            ->whereBelongsToPop(auth()->user()->pop_id)
            ->get()
            ->groupBy('status');

        return response()->json($pipeline);
    }

    public function processToTim(Customer $customer, ProcessToTimRequest $request): JsonResponse
    {
        // Validasi state — hanya boleh dari survey_done
        abort_if($customer->status !== 'survey_done', 422, 'Status tidak valid.');

        $customer->update([
            'status' => 'install_scheduled',
            'assigned_technician_id' => $request->technician_id,
            'install_scheduled_at' => $request->scheduled_date,
            'fop_notes' => $request->notes,
        ]);

        // Notify teknisi via Reverb
        broadcast(new InstallationScheduled($customer))->toOthers();

        return response()->json(['message' => 'Berhasil diproses ke TIM.']);
    }
}
```

---

## 4. Teknisi Dashboard

### 4.1 Layout Utama

```
┌────────────────────────────────────────────────────────┐
│  Selamat pagi, Hendra 👋                    Kam, 25 Jun │
│  3 task aktif hari ini                                 │
├────────────────────────────────────────────────────────┤
│  ┌──────────────────────────────────────────────────┐  │
│  │ 🔵 Budi Santoso               [Survey]           │  │  ← Task aktif
│  │    Jl. Diponegoro, Sukorejo · 09:00              │  │
│  │    ████████████░░░░░  3/5 langkah                │  │
│  │    [Lanjutkan Survey]  [Instruksi]               │  │
│  └──────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────┐  │
│  │ 🟢 Dewi Lestari               [Pemasangan]       │  │  ← Siap dimulai
│  │    Jl. Pahlawan, Jenangan · 13:00                │  │
│  │    ✓ TIM sudah diproses FOP — siap dimulai       │  │
│  │    [Mulai Pemasangan]  [Detail Pelanggan]        │  │
│  └──────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────┐  │
│  │ ⬜ Fajar Nugroho              [Survey]            │  │  ← Upcoming
│  │    Jl. Gatot Subroto, Babadan · 15:30            │  │
│  │    Belum dimulai · Tunggu giliran                │  │
│  └──────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────┘
```

### 4.2 Task Card — State & Aksi

| Status Task | Tampilan | Tombol Utama |
|-------------|----------|-------------|
| `survey_scheduled` | Abu-abu, upcoming | "Mulai Survey" (aktif sesuai jadwal) |
| `survey_in_progress` | Biru, progress bar + countdown hitung mundur SLA | "Lanjutkan Survey" → buka form inline |
| `install_scheduled` + TIM diproses | Hijau, banner notifikasi | "Mulai Pemasangan" |
| `install_in_progress` | Biru, progress bar + countdown hitung mundur SLA | "Lanjutkan Pemasangan" |

### 4.2.1 Countdown Hitung Mundur — Aturan Bisnis

Ada **dua jenis countdown** yang berbeda. Keduanya hitung mundur ke bawah, bukan stopwatch ke atas.

---

#### Countdown 1 — Batas Waktu Survey (1×24 jam)

**Kapan mulai:** Saat pelanggan pertama kali didaftarkan / masuk sistem (registrasi).

**Batas waktu:** **1×24 jam** sejak registrasi — pelanggan harus sudah disurvey.

**Sumber waktu:** `customers.created_at` atau `customer_services.registered_at`

**Logika:**
```
Sisa Waktu Survey = (registered_at + 1 hari) - sekarang
```

**Tampil di:** Task card di FOP Dashboard (kolom Terjadwal/Berjalan) dan daftar antrean survey.

---

#### Countdown 2 — Batas Waktu Verifikasi/Pemasangan (3×24 jam)

**Kapan mulai:** Saat laporan survey disimpan / survey selesai.

**Batas waktu:** **3×24 jam** sejak survey selesai — pelanggan harus sudah diverifikasi FOP dan dipasang.

**Sumber waktu:** `tasks.completed_at` dari task tipe `survey` (atau `customer_surveys.completed_at`)

**Logika:**
```
Sisa Waktu Pemasangan = (survey_completed_at + 3 hari) - sekarang
```

**Tampil di:** Task card di FOP Dashboard (kolom Perlu Aksi FOP) dan kanban pemasangan.

---

#### Tabel Threshold Warna (berlaku untuk kedua countdown)

| Kondisi Sisa Waktu | Tampilan | Makna |
|--------------------|--------------------|-------|
| > 50% dari batas | 🟢 Hijau — `22:30:00` | Masih aman |
| 25%–50% dari batas | 🟡 Kuning — `08:15:00` | Perlu diperhatikan |
| < 25% dari batas | 🔴 Merah berkedip — `02:10:00` | Prioritas tinggi, segera eksekusi |
| Sudah melewati batas | 🔴 Merah + label **TERLAMBAT** — `-01:20:00` | FOP wajib intervensi |

---

#### Countdown SLA Eksekusi (saat task sedang berjalan)

Selain dua countdown di atas, ada countdown ketiga yang aktif **saat teknisi sedang mengerjakan task** (status `in_progress`). Ini mengukur durasi eksekusi aktual vs SLA operasional.

**Sumber waktu:** `tasks.started_at`

```
Sisa Waktu Eksekusi = (started_at + sla_minutes) - sekarang
```

| Tipe Task | SLA Eksekusi |
|-----------|-------------|
| Survey | 120 menit (2 jam) |
| Pemasangan | 240 menit (4 jam) |
| Maintenance | 180 menit (3 jam) |

---

#### Kapan countdown berhenti & berubah jadi catatan waktu

| Event | Countdown | Berubah Jadi |
|-------|-----------|-------------|
| Laporan survey disimpan | Countdown SLA eksekusi berhenti | **Waktu Survey:** `09:15 – 10:42 (1 jam 27 menit)` |
| Laporan pemasangan disimpan | Countdown SLA eksekusi berhenti | **Waktu Pemasangan:** `13:00 – 16:45 (3 jam 45 menit)` |

Nilai durasi aktual dihitung dari `tasks.started_at` → `tasks.completed_at` dan ditampilkan di:
- Task card (setelah status `selesai`)
- Halaman detail task
- Laporan FOP untuk monitoring SLA compliance

### 4.3 Form Laporan Survey (Slide-Over Inline)

Teknisi tidak diarahkan ke halaman baru. Slide-over muncul di atas dashboard:

**Step pills navigasi:**
```
[✓ Data diri]  [✓ Foto lokasi]  [✓ Cek sinyal]  [● Teknis jaringan]  [○ Kesimpulan]
```

**Langkah 4 — Teknis Jaringan:**

| Field | Tipe | Keterangan |
|-------|------|------------|
| Jarak dari POP (meter) | number input | Mandatory |
| Signal strength (dBm) | number input | Mandatory |
| Tipe media rekomendasi | select | Fiber / Wireless / UTP |
| Foto kondisi tiang/akses | file upload (kamera) | Min. 1 foto |
| Catatan teknis | textarea | Opsional |

**Langkah 5 — Kesimpulan:**

| Field | Tipe |
|-------|------|
| Hasil survey | select: Layak / Tidak Layak / Perlu Kunjungan Ulang |
| Alasan (jika tidak layak) | textarea |
| Tanda tangan digital teknisi | signature pad |

### 4.4 Form Laporan Pemasangan (Slide-Over Inline)

**Step pills navigasi:**
```
[● Foto pemasangan]  [○ Data teknis]  [○ Kontrak & TTD]  [○ Aktivasi]
```

**Langkah 1 — Foto Pemasangan:**

| Field | Keterangan |
|-------|------------|
| Foto ONU/ONT terpasang | Min. 1 foto |
| Foto kabel routing | Min. 1 foto |
| Foto titik sambungan | Opsional |

**Langkah 2 — Data Teknis:**

| Field | Tipe |
|-------|------|
| MAC Address ONU | text |
| Serial Number | text |
| VLAN ID | number |
| IP Address yang di-assign | text |
| Kecepatan paket | select (dari paket pelanggan) |

**Langkah 3 — Kontrak & TTD:**

| Field | Keterangan |
|-------|------------|
| Foto/scan kontrak fisik | File upload |
| Tanda tangan pelanggan | Signature pad |
| Tanda tangan teknisi | Signature pad |
| Tanggal aktivasi | date (auto-fill hari ini) |

**Langkah 4 — Aktivasi:**

- Tombol "Aktifkan Pelanggan" → trigger endpoint yang mengubah `customers.status` ke `active`
- Konfirmasi berhasil tampil inline, task hilang dari list aktif teknisi

### 4.5 Route & Controller

```php
// routes/web.php
Route::middleware(['auth', 'permission:teknisi.dashboard'])
    ->prefix('teknisi')
    ->name('teknisi.')
    ->group(function () {
        Route::get('/', [TeknisiDashboardController::class, 'index'])->name('dashboard');
        Route::get('/my-tasks', [TeknisiDashboardController::class, 'myTasks'])->name('tasks');
        Route::post('/tasks/{customer}/start-survey', [TeknisiDashboardController::class, 'startSurvey'])->name('start-survey');
        Route::post('/tasks/{customer}/survey-report', [SurveyReportController::class, 'store'])->name('survey-report');
        Route::post('/tasks/{customer}/start-install', [TeknisiDashboardController::class, 'startInstall'])->name('start-install');
        Route::post('/tasks/{customer}/install-report', [InstallReportController::class, 'store'])->name('install-report');
        Route::post('/tasks/{customer}/activate', [TeknisiDashboardController::class, 'activate'])->name('activate');
    });
```

```php
// app/Http/Controllers/TeknisiDashboardController.php
class TeknisiDashboardController extends Controller
{
    public function myTasks(): JsonResponse
    {
        $tasks = Customer::query()
            ->where('assigned_technician_id', auth()->id())
            ->whereIn('status', [
                'survey_scheduled',
                'survey_in_progress',
                'install_scheduled',
                'install_in_progress',
            ])
            ->with(['surveyReport', 'installReport'])
            ->orderBy('scheduled_at')
            ->get();

        return response()->json($tasks);
    }

    public function startSurvey(Customer $customer): JsonResponse
    {
        abort_if($customer->assigned_technician_id !== auth()->id(), 403);
        abort_if($customer->status !== 'survey_scheduled', 422);

        $customer->update([
            'status' => 'survey_in_progress',
            'survey_started_at' => now(),
        ]);

        broadcast(new SurveyStarted($customer));

        return response()->json(['started_at' => now()]);
    }
}
```

---

## 5. Komponen Livewire

Rekomendasi menggunakan Livewire untuk reaktivitas tanpa full SPA, karena stack sudah Laravel.

### 5.1 FOP Pipeline Component

```php
// app/Livewire/FopPipeline.php
class FopPipeline extends Component
{
    public array $pipeline = [];
    public string $filter = 'all';

    protected $listeners = [
        'echo:fop.{popId},SurveyStarted' => 'refreshPipeline',
        'echo:fop.{popId},SurveyCompleted' => 'refreshPipeline',
        'echo:fop.{popId},InstallationStarted' => 'refreshPipeline',
    ];

    public function mount(): void
    {
        $this->refreshPipeline();
    }

    public function refreshPipeline(): void
    {
        $this->pipeline = Customer::query()
            ->whereIn('status', CustomerStatus::activePipelineStatuses())
            ->whereBelongsToPop(auth()->user()->pop_id)
            ->with('assignedTechnician')
            ->get()
            ->groupBy('status')
            ->toArray();
    }

    public function render(): View
    {
        return view('livewire.fop-pipeline');
    }
}
```

### 5.2 Survey Report Modal Component

```php
// app/Livewire/SurveyReportModal.php
class SurveyReportModal extends Component
{
    public bool $open = false;
    public ?int $customerId = null;
    public int $currentStep = 1;

    // Step 4: Teknis Jaringan
    public ?int $distanceFromPop = null;
    public ?int $signalStrength = null;
    public string $mediaType = '';
    public string $technicalNotes = '';

    // Step 5: Kesimpulan
    public string $surveyResult = '';
    public string $rejectionReason = '';

    // Temporary upload
    #[Validate(['locationPhotos.*' => 'image|max:5120'])]
    public array $locationPhotos = [];

    public function nextStep(): void
    {
        $this->validateCurrentStep();
        $this->currentStep++;
    }

    public function submitReport(): void
    {
        $this->validate();

        $customer = Customer::findOrFail($this->customerId);

        DB::transaction(function () use ($customer) {
            $report = $customer->surveyReport()->updateOrCreate([], [
                'distance_from_pop' => $this->distanceFromPop,
                'signal_strength' => $this->signalStrength,
                'media_type' => $this->mediaType,
                'technical_notes' => $this->technicalNotes,
                'result' => $this->surveyResult,
                'rejection_reason' => $this->rejectionReason,
                'surveyed_by' => auth()->id(),
                'surveyed_at' => now(),
            ]);

            // Store uploaded photos
            foreach ($this->locationPhotos as $photo) {
                $report->addMedia($photo->getRealPath())
                    ->toMediaCollection('survey_photos');
            }

            $customer->update(['status' => 'survey_done']);
        });

        broadcast(new SurveyCompleted($customer));
        $this->open = false;
        $this->dispatch('task-updated');
    }

    // ...
}
```

---

## 6. Real-Time via Laravel Reverb

### 6.1 Event yang Di-broadcast

| Event Class | Channel | Trigger | Consumer |
|-------------|---------|---------|---------|
| `SurveyStarted` | `fop.{pop_id}` | Teknisi tekan "Mulai Survey" | FOP Dashboard — update kanban |
| `SurveyCompleted` | `fop.{pop_id}` | Teknisi submit laporan survey | FOP Dashboard — pindah kolom "Perlu Aksi FOP" |
| `InstallationScheduled` | `teknisi.{user_id}` | FOP proses ke TIM | Teknisi Dashboard — munculkan task baru |
| `InstallationStarted` | `fop.{pop_id}` | Teknisi tekan "Mulai Pemasangan" | FOP Dashboard — update progress |
| `InstallationCompleted` | `fop.{pop_id}` | Teknisi submit laporan pemasangan | FOP Dashboard — pindah kolom "Selesai" |
| `TechnicianStatusUpdated` | `fop.{pop_id}` | Teknisi online/offline | FOP — tabel status teknisi |

### 6.2 Contoh Event

```php
// app/Events/SurveyCompleted.php
class SurveyCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Customer $customer) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("fop.{$this->customer->pop_id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'status' => $this->customer->status,
            'survey_result' => $this->customer->surveyReport?->result,
        ];
    }
}
```

### 6.3 Livewire Listener Setup (Blade)

```blade
{{-- resources/views/livewire/fop-pipeline.blade.php --}}
<div
    x-data
    x-on:task-updated.window="$wire.refreshPipeline()"
>
    {{-- Kanban columns --}}
</div>
```

---

## 7. Upload Foto

Gunakan Livewire temporary upload bawaan — tidak perlu library tambahan.

```blade
{{-- Komponen upload foto survey --}}
<div wire:loading.class="opacity-50">
    <input
        type="file"
        wire:model="locationPhotos"
        multiple
        accept="image/*"
        capture="environment"
    >
    <div wire:loading wire:target="locationPhotos">
        <span>Mengupload...</span>
    </div>
</div>

@foreach ($locationPhotos as $photo)
    <img src="{{ $photo->temporaryUrl() }}" class="w-24 h-24 object-cover rounded">
@endforeach
```

`capture="environment"` memastikan kamera belakang langsung terbuka di mobile — teknisi tidak perlu pilih gallery terlebih dahulu.

---

## 8. Rencana Implementasi (Sprint)

### Sprint A — FOP Dashboard Dasar

- [ ] Buat `FopDashboardController` dengan endpoint `/fop/tasks` (grouped by status)
- [ ] Buat Livewire component `FopPipeline`
- [ ] Buat view Blade kanban (4 kolom)
- [ ] Pindahkan tombol "Proses ke TIM" ke slide-over inline
- [ ] Tambah tabel Status Teknisi (static dulu, real-time di Sprint C)

### Sprint B — Teknisi Dashboard Dasar

- [ ] Buat `TeknisiDashboardController` dengan endpoint `/teknisi/my-tasks`
- [ ] Buat Livewire component `TeknisiTaskList`
- [ ] Buat slide-over `SurveyReportModal` (multi-step form)
- [ ] Buat slide-over `InstallReportModal` (multi-step form)
- [ ] Implementasi countdown hitung mundur SLA di task card (bukan stopwatch, melainkan hitung mundur dari batas SLA)
- [ ] Countdown otomatis aktif saat "Mulai Survey" / "Mulai Pemasangan" → `started_at` dicatat
- [ ] Countdown berhenti dan tampilkan Waktu Survey / Waktu Pemasangan saat laporan disimpan → `completed_at` dicatat
- [ ] Warna countdown: hijau (>50% SLA), kuning (25–50%), merah berkedip (<25%), OVER SLA jika melewati batas
- [ ] Foto upload via Livewire temporary upload + `capture="environment"`

### Sprint C — Real-Time & Polish

- [ ] Tambah Reverb event broadcasting untuk semua transisi status
- [ ] Livewire listener di FOP Pipeline untuk auto-refresh kanban
- [ ] Push notification ke Teknisi Dashboard ketika FOP proses ke TIM
- [ ] Tabel status teknisi diupdate real-time via `TechnicianStatusUpdated`

---

## 9. Keputusan Desain Penting

### Yang Tidak Berubah

- Kolom `customers.status` tetap jadi state machine backbone
- Semua business logic (validasi kelayakan, kalkulasi biaya, dsb.) tetap di service layer yang sudah ada
- Endpoint yang sudah ada tidak dihapus — dashboard baru memanggil endpoint yang sama atau endpoint baru yang tipis di atasnya

### Yang Berubah

- Tombol "Proses ke TIM" dipindah dari halaman detail pelanggan ke FOP Dashboard (slide-over)
- Halaman survey dan pemasangan terpisah digantikan slide-over dalam Teknisi Dashboard
- **Countdown diganti menjadi hitung mundur SLA** — bukan stopwatch ke atas, melainkan menghitung sisa waktu dari batas SLA agar teknisi tahu seberapa prioritas task-nya
- Countdown otomatis aktif saat teknisi menekan "Mulai" → `started_at` dicatat di `tasks`
- Saat laporan disimpan → `completed_at` dicatat, countdown beku, tampil ringkasan **Waktu Survey** atau **Waktu Pemasangan** (jam mulai – jam selesai + durasi aktual)

### Prinsip RBAC

Semua route dilindungi permission string, bukan hardcode nama role:

```php
// Benar
Route::middleware(['permission:fop.dashboard'])

// Salah — hardcode nama role
Route::middleware(['role:fop'])
```

Ini sesuai dengan arsitektur RBAC dinamis yang sudah dirancang di `analisa-rbac-dinamis-whusnet.md`.
