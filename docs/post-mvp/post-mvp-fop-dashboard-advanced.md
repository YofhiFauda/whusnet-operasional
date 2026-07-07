> **Arsip.** Dokumen historis, sebagian sudah tidak sesuai kode aktif (lihat [../README.md](../README.md) untuk dokumentasi terkini).

# Spesifikasi UI/UX: Widget Tambahan Dashboard FOP
## WHUSNET Admin Payment · Modern & Comfortable UI

> [!NOTE]
> **Status:** Post-MVP  
> **Target Tipe Halaman:** Type C (Aggregate Dashboard Page)  
> **Sistem Desain Utama:** Sky Blue & Slate (Light & Dark) — Berdasarkan [Design.md](file:///d:/Whusnet/whusnet-operasional/design-system/whusnet-operasional/Design.md)  
> **Prinsip Utama:** Calm Productivity, Data Clarity First, Action Confidence  

---

## 1. Arsitektur Layout Shell (Type C Grid)

Berdasarkan spesifikasi **Type C — Aggregate Dashboard Page** pada [Design.md](file:///d:/Whusnet/whusnet-operasional/design-system/whusnet-operasional/Design.md), halaman dashboard diperbolehkan memiliki struktur multi-card modular (card per chart/recent list) karena berfungsi sebagai aggregasi metrik taktis dan operasional.

### Visual Blueprint Kiri-Kanan (2 Column Layout Grid: 2fr + 1fr)

```
Page Header — naked (tidak dibungkus card)
┌────────────────────────────────────────────────────────────────────────┐
│ Dashboard FOP Tambahan                                                  │
│ Analitik real-time, kualitas kerja, dan monitoring tim lapangan.       │
└────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────┐ ┌────────────────────────────┐
│ 1. KANVAS UTAMA ANALITIK & KERJA (2fr)  │ │ 2. OPERASIONAL ALERTS (1fr)│
│                                         │ │                            │
│ ┌─────────────────────────────────────┐ │ │ ┌────────────────────────┐ │
│ │ ∿ Distribusi Task Hari Ini (Bar)    │ │ │ │ ⚠ SLA At-Risk Alerts   │ │
│ └─────────────────────────────────────┘ │ │ └────────────────────────┘ │
│ ┌─────────────────────────────────────┐ │ │ ┌────────────────────────┐ │
│ │ 📈 Trend Task 7 Hari Terakhir (Area) │ │ │ │ 🕒 Backlog Aging       │ │
│ └─────────────────────────────────────┘ │ │ └────────────────────────┘ │
│ ┌─────────────────────────────────────┐ │ │ ┌────────────────────────┐ │
│ │ 🏢 Breakdown Task per POP (Tabel)   │ │ │ │ 💬 Top Pending Reasons │ │
│ └─────────────────────────────────────┘ │ │ └────────────────────────┘ │
│ ┌─────────────────────────────────────┐ │ │                            │
│ │ 👥 Utilisasi & Beban Kerja Teknisi  │ │ │                            │
│ └─────────────────────────────────────┘ │ │                            │
└─────────────────────────────────────────┘ └────────────────────────────┘
```

---

## 2. Spesifikasi Detail Widget & Komponen UI

Setiap widget dibangun dengan mematuhi batasan visual dan tipografi berikut:
*   **Tipografi:** UI Label/Text menggunakan **Inter**, sedangkan Data/Angka/Uptime menggunakan **JetBrains Mono**.
*   **Ikonografi:** Hanya menggunakan **Lucide SVG Icons** (Bukan emoji).
*   **Warna Status:** Hanya diaplikasikan pada teks dan badge (Background card tetap putih bersih/gelap solid, bukan background berwarna terang/soft secara penuh).

### 2.1 Kategori 1: Analitik & Trend

#### Widget 1.1: Distribusi Task per Kategori Hari Ini
*   **Tipe Visual:** Bar Chart Horizontal (Space-efficient).
*   **Warna Data:** `var(--color-primary)` (#0284C7) untuk survey, `var(--color-success)` (#16A34A) untuk pemasangan, `var(--color-warning)` (#D97706) untuk maintenance/revisit.
*   **Interaktivitas:** Hover tooltip menunjukkan total task dan persentase kontribusi terhadap total beban harian.

#### Widget 1.2: Trend Volume Task 7 Hari Terakhir
*   **Tipe Visual:** Dual-line / Smooth Area Chart.
*   **Data Series:** Task Terbuat (Created) vs Task Selesai (Completed).
*   **Urgensi UX:** Deteksi dini lonjakan beban (workload spikes) yang tidak seimbang dengan kecepatan penyelesaian tim.

#### Widget 1.3: Breakdown per POP Cabang
*   **Tipe Visual:** Compact Data Table (Naked, di dalam chart card).
*   **Prinsip POP Scope:** Hanya menampilkan POP yang didelegasikan ke user yang login (diambil dari `user_role_scopes`).

| Kolom | Lebar | Font | Alignment | Catatan |
|---|---:|---|---|---|
| Nama POP | `flex` | Inter (Medium) | Left | Nama cabang / sub-POP |
| Active Tasks | `100px` | JetBrains Mono | Right | Jumlah task sedang jalan |
| Overdue Tasks | `100px` | JetBrains Mono | Right | Teks merah jika > 0 |
| SLA Compliance | `120px` | JetBrains Mono | Right | Badge indikator compliance |

---

### 2.2 Kategori 2: Metrik Kualitas Kerja

#### Widget 2.1: Recurring Maintenance (MTN) Alert Panel
*   **Urgensi Bisnis:** Mendeteksi pelanggan yang meminta kunjungan perbaikan berulang dalam rentang waktu singkat (misal: 2 kali dalam 30 hari). Ini menandakan adanya masalah infrastruktur/kabel drop, bukan sekadar insiden acak.
*   **Format UI:** Alert Strip dengan warning icon merah, label nama pelanggan (CID), nama POP, dan frekuensi perbaikan.

#### Widget 2.2: SLA Completion vs Target Table
*   **Koneksi Model:** Membandingkan durasi riil `completed_at - started_at` terhadap standard SLA (`TaskType::slaMinutes()`).
*   **Format Nilai:** Jam & menit dalam font **JetBrains Mono**.

#### Widget 2.3: First-Time-Fix Rate (FTFR) untuk MTN
*   **Metrik:** Persentase tiket maintenance yang terselesaikan tanpa memerlukan tiket follow-up/revisit dalam 7 hari.
*   **Visual:** Card Donut Chart / Semi-circular gauge dengan warna hijau jika > 85%, dan merah jika di bawah target.

---

### 2.3 Kategori 3: Utilisasi & Roster Tim

#### Widget 3.1: Workload Distribution Grid
*   **Format UI:** Grid visual dari profil teknisi aktif.
*   **Metrik Visual:** Menampilkan perbandingan jumlah task aktif (sedang berjalan) vs standby (antrean ditugaskan) dalam bentuk bar penunjuk beban (progress bar multi-color).

```
[Profile Avatar] Adi Teknisi
Status: 🟢 AKTIF (Madiun)
Beban Kerja: 3 Task Sedang Berjalan (SLA 45m tersisa)
[▓▓▓▓▓▓░░░░] 30% Kapasitas Terisi
```

#### Widget 3.2: Productivity Leaderboard (Opsional)
> [!WARNING]
> **Constraint UX:** Untuk menghindari gesekan internal atau tekanan psikologis yang tidak sehat di lapangan, metrik produktivitas individu (misal: pengerjaan tercepat) secara default **hanya dapat dilihat oleh Owner, Atasan, dan FOP Manager**. Teknisi reguler tidak boleh melihat ranking ini.

---

### 2.4 Kategori 4: Operasional & Peringatan Proaktif

#### Widget 4.1: SLA At-Risk Alerts
*   **Fungsi:** Mengidentifikasi task aktif yang sisa waktunya mendekati batas SLA (< 2 jam) namun belum ditandai selesai oleh teknisi di lapangan.
*   **Interaktivitas:** Klik alert langsung memicu modal "Hubungi Teknisi" via WhatsApp atau detail koordinat tim.

#### Widget 4.2: Backlog Aging Breakdown
*   **Metrik:** Mengelompokkan task pending berdasarkan lama hari mengantre (0-3 hari, 4-7 hari, > 7 hari) untuk mencegah timbunan backlog operasional.

#### Widget 4.3: Pareto Analysis Pending Reason
*   **Visual:** Bar chart vertical yang diurutkan dari alasan terbanyak (misal: "Hujan Lebat", "Pelanggan Tidak di Rumah", "Material Habis"). Memudahkan FOP mengambil tindakan perbaikan suplai logistik atau kebijakan penjadwalan.

---

## 3. Blueprint Kode HTML/Blade & Tailwind CSS

Berikut adalah blueprint struktur kode visual yang mematuhi **Universal Prohibited Patterns** (Tidak ada double-card, layout naked header, font konsisten, dan visual status tersentralisasi pada teks/badge).

### 3.1 Blueprint Widget SLA At-Risk Alerts (Sidebar Widget / Panel Kanan)

```html
{{-- Widget SLA At-Risk Alerts --}}
<div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg p-5 flex flex-col gap-4">
    <div class="flex items-center justify-between">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 font-sans flex items-center gap-1.5">
            <svg class="w-4 h-4 text-rose-600 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            SLA At-Risk Alerts
        </span>
        <span class="text-xs font-mono bg-rose-50 text-rose-600 dark:bg-rose-950/30 dark:text-rose-400 px-2 py-0.5 rounded-full font-medium">
            3 Task Kritis
        </span>
    </div>

    <div class="flex flex-col gap-3">
        {{-- Row Task 1 --}}
        <div class="flex items-start justify-between py-2.5 border-b border-slate-100 dark:border-slate-800 last:border-none">
            <div class="flex flex-col gap-1">
                <span class="text-sm font-semibold text-slate-800 dark:text-slate-200 font-sans hover:text-sky-600 cursor-pointer">
                    Budi Santoso
                </span>
                <span class="text-xs font-mono text-slate-400 dark:text-slate-500">
                    CID-100042 · Pemasangan Fiber
                </span>
            </div>
            <div class="text-right">
                <span class="text-xs font-mono text-rose-600 font-semibold block">
                    -00:45:12
                </span>
                <span class="text-[10px] text-slate-400 dark:text-slate-500 font-sans">
                    Sisa SLA
                </span>
            </div>
        </div>

        {{-- Row Task 2 --}}
        <div class="flex items-start justify-between py-2.5 border-b border-slate-100 dark:border-slate-800 last:border-none">
            <div class="flex flex-col gap-1">
                <span class="text-sm font-semibold text-slate-800 dark:text-slate-200 font-sans hover:text-sky-600 cursor-pointer">
                    Ahmad Fauzi
                </span>
                <span class="text-xs font-mono text-slate-400 dark:text-slate-500">
                    CID-100044 · Maintenance (LOS)
                </span>
            </div>
            <div class="text-right">
                <span class="text-xs font-mono text-amber-600 font-semibold block">
                    00:15:33
                </span>
                <span class="text-[10px] text-slate-400 dark:text-slate-500 font-sans">
                    Sisa SLA
                </span>
            </div>
        </div>
    </div>
</div>
```

---

### 3.2 Blueprint Tabel Kualitas Kerja (SLA Target vs Realisasi)

```html
{{-- Widget SLA vs Realisasi --}}
<div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg p-5 flex flex-col gap-4">
    <div class="flex items-center justify-between">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 font-sans flex items-center gap-1.5">
            <svg class="w-4 h-4 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            Kualitas Kerja: Target vs Realisasi SLA
        </span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-slate-200 dark:border-slate-700">
                    <th class="py-2 text-[10px] font-semibold text-slate-400 uppercase tracking-wider font-sans">Kategori Task</th>
                    <th class="py-2 text-[10px] font-semibold text-slate-400 uppercase tracking-wider font-sans text-right">Standard SLA</th>
                    <th class="py-2 text-[10px] font-semibold text-slate-400 uppercase tracking-wider font-sans text-right">Rata-rata Durasi</th>
                    <th class="py-2 text-[10px] font-semibold text-slate-400 uppercase tracking-wider font-sans text-right">Status Target</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors duration-150">
                    <td class="py-3 text-sm font-medium text-slate-800 dark:text-slate-200 font-sans">Survey Pelanggan</td>
                    <td class="py-3 text-sm font-mono text-slate-600 dark:text-slate-400 text-right">120m</td>
                    <td class="py-3 text-sm font-mono text-slate-600 dark:text-slate-400 text-right">98m</td>
                    <td class="py-3 text-right">
                        <span class="inline-flex items-center text-xs font-semibold text-emerald-600 font-sans">
                            ● ON TARGET
                        </span>
                    </td>
                </tr>
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors duration-150">
                    <td class="py-3 text-sm font-medium text-slate-800 dark:text-slate-200 font-sans">Pemasangan Fiber</td>
                    <td class="py-3 text-sm font-mono text-slate-600 dark:text-slate-400 text-right">240m</td>
                    <td class="py-3 text-sm font-mono text-slate-600 dark:text-slate-400 text-right">285m</td>
                    <td class="py-3 text-right">
                        <span class="inline-flex items-center text-xs font-semibold text-rose-600 font-sans animate-pulse">
                            ▲ SLA BREACH
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
```

---

## 4. Panduan Transisi & Efek Visual UX (Pro Max)

1.  **Micro-Interactions (Hover States):**
    *   Setiap baris data tabel atau card alert wajib memiliki transisi hover yang halus (`transition-colors duration-150 ease-in-out hover:bg-slate-50`).
    *   Efek pointer cursor (`cursor-pointer`) harus disematkan pada setiap elemen yang mengarah ke aksi detail.
2.  **State Loading Skeletal:**
    *   Saat grafik sedang melakukan fetching data periodik, gunakan animasi shimmer skeleton loading (`animate-pulse`), bukan full-screen spinner buram yang memotong konsentrasi pengguna.
3.  **Real-Time Sync dengan Reverb:**
    *   Gunakan Echo listener pada channel `fop.{pop_id}` untuk memicu animasi flash highlight hijau muda/biru muda tipis sesaat ketika status task ter-update secara dinamis di dashboard.
