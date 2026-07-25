# Analisa Implementasi Real-Time (SPA-Like) pada Aplikasi Whusnet Operasional

Dokumen ini mencatat analisis arsitektur, perbandingan metode implementasi real-time tanpa reload/polling, serta detail panduan penerapan menggunakan pendekatan **Reactive State-Binding (Alpine.js State)** yang dipilih untuk halaman operasional Whusnet.

---

## 1. Fondasi Arsitektur Real-Time Existing
Aplikasi saat ini telah dilengkapi dengan infrastruktur modern yang siap mendukung pembaruan data real-time:
*   **Backend:** [Laravel Reverb](file:///d:/Whusnet/whusnet-operasional/config/broadcasting.php) (WebSocket Server bawaan Laravel 11+) yang dikombinasikan dengan Broadcast Event Laravel (`ShouldBroadcast` / `ShouldBroadcastNow`).
*   **Frontend:** [Laravel Echo](file:///d:/Whusnet/whusnet-operasional/resources/js/echo.js) terkonfigurasi secara dinamis di `echo.js` dan diintegrasikan dengan state reactive [Alpine.js](https://alpinejs.dev/).

---

## 2. Pemetaan Fitur yang Membutuhkan Real-Time (SPA-Like)

Berikut adalah pemetaan seluruh fitur operasional Whusnet yang memerlukan update reaktif secara real-time tanpa refresh halaman:

### 2.1. Kategori Kritis (High Priority / Crucial Operational Realtime)
Fitur pada kategori ini dikerjakan oleh **banyak user sekaligus dalam satu waktu**. Tanpa realtime, berisiko terjadi *data stale* (data basi), *race condition* (duplikasi penugasan), atau keterlambatan penanganan gangguan di lapangan.

| No | Fitur | Role Terkait | Event Reverb | Alasan Membutuhkan SPA-Like Realtime |
|---|---|---|---|---|
| 1 | **FOP / Task Management Teknisi** *(Penugasan Survey, Pemasangan, Maintenance)* | FOP, Teknisi, Helpdesk, NOC | `TaskScheduled`<br>`TaskStatusUpdated`<br>`TaskTeamAssigned` | • Dispatcher/FOP melihat pergerakan status tugas teknisi di lapangan secara instan.<br>• Teknisi di lapangan langsung mendapatkan notifikasi tugas baru / perubahan rute di layar HP tanpa perlu refresh. |
| 2 | **Kasir & Loket Pembayaran (Payment Desk)** | Kasir / Finance, Admin POP | `PaymentReceived`<br>`InvoiceStatusUpdated` | • Saat kasir mengonfirmasi pembayaran tagihan, status invoice langsung berubah jadi `PAID` di seluruh layar cabang/pusat.<br>• Mencegah ganda cetak kwitansi atau klaim bayar ulang oleh kasir lain. |
| 3 | **Antrean & Escalation Tiket Pengaduan (Helpdesk)** | Helpdesk, NOC, Admin POP | `TicketCreated`<br>`TicketAssigned`<br>`TicketStatusChanged` | • Tiket gangguan baru dari pelanggan langsung *pop-up* / masuk ke antrean Helpdesk secara *live*.<br>• Mencegah 2 petugas Helpdesk mengambil tiket penanganan yang sama (*overlapping*). |
| 4 | **Live Progress Import Pelanggan (Bulk Import CSV/Excel)** | Admin Pusat, Admin POP | `ImportProgressUpdated`<br>`ImportBatchCompleted` | • Menampilkan *Progress Bar* secara *live* (misal: *140/500 baris sukses*) beserta log error tanpa membuat halaman *freeze* atau minta di-reload. |
| 10 | **Antrean Verifikasi Admin** (`verifications/queue.blade.php`) | Admin POP, Admin Pusat | `CustomerVerificationStatusChanged` | • Sama kelas risiko dengan antrean tiket (#3): 2 admin bisa verifikasi pelanggan yang sama bersamaan tanpa realtime. Item yang sudah diproses harus hilang/berubah status di layar admin lain secara instan. |
| 11 | **Konfirmasi Pembayaran di List Invoice** (`invoices/index.blade.php:279`) | Kasir, Admin POP | `PaymentReceived`<br>`InvoiceStatusUpdated` | • Saat ini konfirmasi bayar memicu `window.location.reload()` — full page reload. Harus pakai event yang sama dengan Kasir (#2), update baris invoice di state Alpine, cegah klaim bayar ganda tanpa reload. |

---

### 2.2. Kategori Efisiensi Operasional (Medium Priority / Workflow Booster)
Fitur yang mempermudah interaksi harian antar divisi agar alur kerja berjalan cepat tanpa harus sering berpindah halaman atau menekan tombol F5.

| No | Fitur | Role Terkait | Event Reverb | Alasan Membutuhkan SPA-Like Realtime |
|---|---|---|---|---|
| 5 | **Quick Action & Quick View Modal Pelanggan** | Helpdesk, NOC, Finance, CS | `CustomerUpdated`<br>`CustomerDeviceStatusChanged` | • Pengguna dapat mengubah paket, menguji perangkat, atau memperbarui data dari modal samping/pop-up; baris di tabel utama pelanggan langsung ter-update di background. |
| 6 | **Proses Aktivasi & Perubahan Status Layanan** | NOC, Admin POP, CS | `CustomerStatusActivated`<br>`CustomerSuspended` | • Ketika status pelanggan diubah dari *Perlu Dilengkapi* → *Lengkap* → *Siap Billing* / *Aktif*, perubahan badge status langsung terfleksi di seluruh modul billing & FOP. |
| 7 | **Notifikasi Center & Alert Bar (Top Nav Notification)** | Seluruh Role | `UserNotificationSent`<br>`SystemAlertBroadcast` | • Indikator lonceng notifikasi di navbar menyala instan saat ada tugas baru atau alert sistem tanpa mengganggu form yang sedang diisi pengguna. |
| 12 | **Tombol Refresh Manual "Status Teknisi"** (`fop/dashboard.blade.php:481`) | FOP | `TechnicianStatusChanged` | • UI FOP dashboard sekarang punya tombol reload eksplisit untuk lihat status teknisi terbaru — bukti langsung butuh realtime. Ganti tombol dengan listener status teknisi (online/task progress), hapus tombolnya. |
| 13 | **Reassign Tim FOP Task** (`fop_tasks/index.blade.php:673`) | FOP | `TaskTeamAssigned` | • Setelah pindah tim, halaman full reload (`setTimeout(reload, 1000)`). Broadcast event, update baris task di state Alpine langsung tanpa reload. |

---

### 2.3. Kategori Executive & Monitoring (Low Priority / Visual Dashboards)
Fitur yang berfokus pada aspek statistik & transparansi bagi jajaran manajemen / Owner.

| No | Fitur | Role Terkait | Event Reverb | Alasan Membutuhkan SPA-Like Realtime |
|---|---|---|---|---|
| 8 | **Live Feed Audit Log & Ticker Aktivitas System** | Owner, Admin Pusat | `AuditLogCreated` | • Stream log aktivitas internal secara *live* (siapa yang memverifikasi pembayaran, menghapus data, atau login ke sistem). |
| 9 | **Dashboard Stat Cards (KPI Counter)** | Owner, Admin Pusat, Finance | `DashboardMetricsUpdated` | • Counter angka statistik (contoh: *Pemasukan Hari Ini*, *Total Pelanggan Aktif*, *Tiket Open*) naik/turun secara dinamis begitu ada transaksi baru. |

---

## 3. Perbandingan Dua Metode Real-Time Frontend

Untuk merancang UI yang reaktif tanpa refresh manual, auto refresh, atau polling, terdapat dua pilihan metode utama di sisi frontend:

### Metode 1: Direct DOM Target (Manipulasi DOM Spesifik)
Pendekatan ini menyisipkan ID unik di elemen HTML Blade, kemudian memanipulasi properti/teks dari elemen tersebut langsung menggunakan JavaScript Vanilla sewaktu event WebSocket diterima.

*   **Konsep:**
    ```html
    <!-- Blade template -->
    <span id="task-status-{{ $task->id }}" class="badge">
        {{ $task->status }}
    </span>
    ```
    ```javascript
    // Echo listener
    window.Echo.private(`fop.${popId}`)
        .listen('TaskUpdated', (e) => {
            const badge = document.getElementById(`task-status-${e.task_id}`);
            if (badge) {
                badge.innerText = e.status;
                badge.className = `badge ${e.badge_class}`;
            }
        });
    ```
*   **Pro:** Sangat mudah diterapkan pada halaman Laravel Blade tradisional tanpa mengubah loop data (`@foreach`).
*   **Kontra:** Jika UI semakin kompleks (misalnya ada interaksi antar elemen, pergantian list, atau pengurutan ulang), manipulasi DOM manual akan menjadi sangat rumit, rawan error (*fragile*), dan kode menjadi tidak rapi (*spaghetti code*).

---

### Metode 2: Reactive State-Binding (Alpine.js State) — *PILIHAN YANG DIGUNAKAN*
Pendekatan ini menyimpan seluruh data data tabular atau list ke dalam variabel *state* (array JavaScript) di dalam Alpine.js (`x-data`). HTML di-render secara dinamis menggunakan directive `x-for`. Ketika event WebSocket diterima, kita hanya perlu memodifikasi data array di memory, dan Alpine.js secara otomatis melacak serta merender ulang bagian DOM yang berubah secara instan.

*   **Konsep:**
    ```html
    <!-- Alpine.js loop -->
    <div x-data="{ 
        tasks: @json($fopTasks->items()),
        init() {
            window.Echo.private(`fop.${popId}`)
                .listen('TaskUpdated', (e) => {
                    let task = this.tasks.find(t => t.id === e.task_id);
                    if (task) {
                        task.status = e.status;
                        task.badge_class = e.badge_class;
                    }
                });
        }
    }">
        <table>
            <template x-for="task in tasks" :key="task.id">
                <tr>
                    <td x-text="task.tugas"></td>
                    <td>
                        <span :class="task.badge_class" x-text="task.status"></span>
                    </td>
                </tr>
            </template>
        </table>
    </div>
    ```
*   **Pro:**
    *   **SPA-Like sejati:** UI ter-update instan secara deklaratif dan responsif.
    *   **Bebas Manipulasi DOM Manual:** Cukup kelola datanya, tampilan akan otomatis sinkron (*data-binding*).
    *   **Clean Code:** Logika UI terpusat di satu handler JS/Alpine.js.
*   **Kontra:** Membutuhkan perubahan struktur kode loop dari Blade `@foreach` menjadi Alpine `x-for`.

---

## 4. Langkah Implementasi dengan Pendekatan Alpine.js State

Untuk menerapkan **Alpine.js State** pada modul seperti **Task FOP**, ikuti spesifikasi berikut:

### Langkah A: Kirim Payload Lengkap dari Backend Event
Event Laravel harus mengemas seluruh field yang diperlukan frontend untuk me-render UI secara mandiri.
Contoh pada event `app/Events/TaskScheduled.php`:

```php
namespace App\Events;

use App\Models\Task;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class TaskScheduled implements ShouldBroadcast
{
    public Task $task;
    public string $eventType;

    public function __construct(Task $task, string $eventType = 'created')
    {
        $this->task = $task;
        $this->eventType = $eventType;
    }

    public function broadcastOn(): array
    {
        // Distribusikan ke teknisi yang ditugaskan
        return $this->task->teamMembers->map(fn ($member) => new PrivateChannel('teknisi.' . $member->user_id))->all();
    }

    public function broadcastWith(): array
    {
        // Custom payload lengkap untuk di-render client
        return [
            'id' => $this->task->id,
            'task_number' => $this->task->task_number,
            'title' => $this->task->title,
            'description' => $this->task->description,
            'status' => $this->task->status->value,
            'badge_class' => $this->task->status->badgeClasses(),
            'scheduled_at' => $this->task->scheduled_at?->toIso8601String(),
            'customer' => [
                'name' => $this->task->customer?->name,
                'address' => $this->task->customer?->address,
            ]
        ];
    }
}
```

### Langkah B: Integrasikan State ke Handler Alpine.js
Di file Handler JavaScript halaman, buat variabel penampung data (misalnya `tasks`) dan bind listener Laravel Echo untuk memperbarui state tersebut secara realtime.

Contoh integrasi pada `fopTaskPageHandler()`:

```javascript
function fopTaskPageHandler() {
    return {
        // State awal diambil dari server-side render json
        tasks: [], 
        
        init() {
            // Load initial data
            this.tasks = initialTasks;
            
            // Dengarkan perubahan realtime via Reverb
            this.initEchoListeners();
        },

        initEchoListeners() {
            if (typeof window.Echo === 'undefined') return;

            window.Echo.private(`fop.${popId}`)
                .listen('TaskScheduled', (e) => {
                    if (e.event_type === 'created') {
                        // Tambahkan item baru ke baris paling atas
                        this.tasks.unshift(e);
                    }
                })
                .listen('TaskUpdated', (e) => {
                    // Cari item berdasarkan ID dan update statusnya saja
                    let task = this.tasks.find(t => t.id === e.id);
                    if (task) {
                        task.status = e.status;
                        task.badge_class = e.badge_class;
                        task.technician_name = e.technician_name;
                    }
                })
                .listen('TaskCancelled', (e) => {
                    // Hapus task dari layar
                    this.tasks = this.tasks.filter(t => t.id !== e.id);
                });
        }
    }
}
```

### Langkah C: Ganti Rendering Blade Looping dengan `x-for`
Ubah looping tabel di template Blade agar sepenuhnya dibaca dari state Alpine.js:

```html
<tbody class="divide-y divide-slate-100">
    <template x-for="task in tasks" :key="task.id">
        <tr class="hover:bg-slate-50 transition-colors">
            <!-- Kolom Judul Tugas -->
            <td class="px-3 py-2 whitespace-nowrap">
                <span class="font-medium text-slate-800" x-text="task.title"></span>
            </td>
            
            <!-- Kolom Pelanggan -->
            <td class="px-3 py-2">
                <span x-text="task.customer?.name || '—'"></span>
            </td>

            <!-- Kolom Status Realtime -->
            <td class="px-3 py-2">
                <span :class="task.badge_class" class="px-2 py-1 rounded text-xs font-semibold" x-text="task.status"></span>
            </td>
        </tr>
    </template>
</tbody>
```

---

## 5. Keuntungan Penerapan Pola Real-Time
1.  **Penghematan Resource Server & Bandwidth:** Mengurangi overhead database dan jaringan dibandingkan metode *polling* (request AJAX berulang setiap sekian detik).
2.  **Meningkatkan Kepuasan Pengguna (Wow Factor):** Aplikasi terasa responsif, modern, dan bernilai premium.
3.  **Konsistensi Data Multi-User:** Meminimalisir kesalahan operasional akibat data yang basi (stale data) di layar admin yang berbeda.
