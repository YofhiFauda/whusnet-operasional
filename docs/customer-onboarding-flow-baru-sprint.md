# Customer Onboarding — Flow Baru: Sprint Planning

> **Dokumen ini** menjabarkan implementasi flow baru onboarding pelanggan ISP (Registrasi → Survey → Verifikasi → Pemasangan → Aktif) dengan sistem penjadwalan FOP, task teknisi, dan SLA real-time.
>
> **Stack:** Laravel · Livewire · Alpine.js · Laravel Reverb (WebSocket) · Blade · Tailwind CSS · MySQL

---

## Ringkasan Perubahan Flow

| Aspek | Flow Lama | Flow Baru |
|---|---|---|
| Aktor | 1 (Admin/Helpdesk) | 3 (FOP, Teknisi, Admin) |
| Penjadwalan | Manual, tidak terstruktur | FOP buat tim 1–3 teknisi per fase |
| Task Teknisi | Tidak ada | Ada — muncul di Kalender & Task Teknisi |
| SLA | Tidak ada | Ada — countdown real-time via Reverb |
| Fase survey & pasang | 1 flow linier | 2 fase paralel dengan pola yang sama |

---

## State Machine `customers.status`

```
registrasi
  └─→ waiting-survey
        └─→ process-survey
              └─→ waiting-acc
                    └─→ waiting-installation
                          └─→ process-installation
                                └─→ pending-verification
                                      └─→ active
```

### Transisi & Trigger

| Dari | Ke | Trigger | Aktor |
|---|---|---|---|
| `registrasi` | `waiting-survey` | Pelanggan berhasil ditambah | System/Admin |
| `waiting-survey` | `process-survey` | Teknisi tekan "Mulai Survey" | Teknisi |
| `process-survey` | `waiting-acc` | Teknisi simpan Laporan Survey | Teknisi |
| `waiting-acc` | `waiting-installation` | FOP/Helpdesk tekan "Proses ke Tim" | FOP/Helpdesk |
| `waiting-installation` | `process-installation` | Teknisi tekan "Mulai Pemasangan" | Teknisi |
| `process-installation` | `pending-verification` | Teknisi simpan Laporan Pemasangan | Teknisi |
| `pending-verification` | `active` | Admin simpan Form Verifikasi Admin | Admin |

---

## Pola Berulang (Survey & Pemasangan)

Kedua fase mengikuti pola yang sama:

```
Waiting (SLA aktif)
  → FOP buat jadwal tim (1–3 teknisi)
    → Task masuk Kalender FOP + Task Teknisi
      → Teknisi buka Detail Task
        → Teknisi tekan [Mulai ...]   ← status berubah
          → Teknisi isi Form Laporan
            → Simpan                 ← status lanjut ke fase berikutnya
```

---

## Sprint Breakdown

---

### Sprint 1 — Fondasi: State Machine & Struktur DB

**Tujuan:** Semua state `customers.status` terdefinisi, migrasi DB siap, permission seed tersedia.

**Story & Tasks:**

#### 1.1 Migrasi DB

- [ ] Tambah kolom `status` enum pada tabel `customers` dengan semua state di atas
- [ ] Buat tabel `customer_tasks` untuk task survey & pemasangan:
  ```
  id, customer_id, type (survey|installation), status (scheduled|in-progress|done),
  scheduled_at, started_at, completed_at, sla_deadline, notes, created_by
  ```
- [ ] Buat tabel `customer_task_technicians` (pivot):
  ```
  id, task_id, user_id (teknisi), role (lead|support), assigned_by, assigned_at
  ```
- [ ] Tambah kolom SLA pada `customer_surveys`:
  ```
  sla_deadline, started_at, completed_at
  ```
- [ ] Tambah kolom SLA pada `customer_installations`:
  ```
  sla_deadline, started_at, completed_at
  ```

#### 1.2 Model & Relasi

- [ ] Update model `Customer` — tambah state machine helper (`canTransitionTo`, `transitionTo`)
- [ ] Buat model `CustomerTask` dengan relasi ke `Customer` dan `User` (teknisi)
- [ ] Buat model `CustomerTaskTechnician`
- [ ] Definisikan konstanta status di `App\Enums\CustomerStatus`

#### 1.3 Permission & RBAC

- [ ] Seed permission codes:
  - `customer.survey.start`
  - `customer.survey.report.submit`
  - `customer.installation.schedule`
  - `customer.installation.start`
  - `customer.installation.report.submit`
  - `customer.acc.process`
  - `customer.verification.submit`
- [ ] Assign permission ke role FOP, Teknisi, Helpdesk, Admin

**Acceptance Criteria:**
- Semua migrasi berjalan tanpa error
- `Customer::transitionTo('waiting-survey')` berfungsi dan log perubahan status
- Permission terseed dan bisa dicek via `$user->can('customer.survey.start')`

---

### Sprint 2 — Fase Survey: FOP Penjadwalan & Task Teknisi

**Tujuan:** FOP bisa menjadwal tim teknisi untuk survey, task muncul di kalender dan halaman teknisi.

**Story & Tasks:**

#### 2.1 Halaman List Task FOP — Survey

- [ ] Buat Livewire component `Fop\SurveyTaskList`
- [ ] Tampilkan daftar pelanggan dengan status `waiting-survey` + SLA countdown (via Reverb)
- [ ] Filter: tanggal, area, teknisi tersedia
- [ ] Tombol "Buat Jadwal Survey" per pelanggan

#### 2.2 Form Penjadwalan Tim Teknisi

- [ ] Slide-over / modal form: pilih tanggal, pilih 1–3 teknisi (multi-select dari daftar user role Teknisi)
- [ ] Validasi: teknisi tidak bentrok jadwal di hari yang sama
- [ ] Simpan ke `customer_tasks` + `customer_task_technicians`
- [ ] Broadcast event `TaskAssigned` via Reverb ke teknisi yang ditunjuk

#### 2.3 Kalender Scheduler FOP

- [ ] Buat halaman kalender (FullCalendar.js atau grid manual Blade)
- [ ] Tampilkan semua task survey & pemasangan yang sudah dijadwal
- [ ] Color-code: biru = survey, amber = pemasangan
- [ ] Klik event → tampilkan detail task (slide-over)

#### 2.4 Halaman Task Teknisi

- [ ] Buat Livewire component `Teknisi\MyTaskList`
- [ ] Tampilkan task yang di-assign ke teknisi yang sedang login
- [ ] Notifikasi real-time saat task baru di-assign (Reverb)
- [ ] Klik task → Detail Task

#### 2.5 Detail Task — Survey

- [ ] Tampilkan: nama pelanggan, alamat, jadwal, anggota tim, SLA countdown
- [ ] Tombol **"Mulai Survey"** — hanya aktif jika:
  - Status customer = `waiting-survey`
  - User = salah satu teknisi di tim task ini
  - Permission `customer.survey.start`
- [ ] Tekan → status customer berubah ke `process-survey`, `task.started_at` diisi, SLA mulai

**Acceptance Criteria:**
- FOP bisa assign 1–3 teknisi ke task survey
- Task muncul di kalender FOP dan list task teknisi
- Tombol "Mulai Survey" mengubah status customer ke `process-survey`
- SLA countdown tampil real-time via Reverb

---

### Sprint 3 — Fase Survey: Laporan & Transisi ke Verifikasi ACC

**Tujuan:** Teknisi bisa mengisi dan menyimpan laporan survey; FOP/Helpdesk bisa proses ACC.

**Story & Tasks:**

#### 3.1 Form Laporan Survey (inline di Detail Task)

- [ ] Setelah status `process-survey`, tombol berubah menjadi **"Laporan Survey"**
- [ ] Klik → buka slide-over / section inline dengan form laporan:
  - Kondisi lokasi (dropdown: Layak / Tidak Layak / Perlu Tindak Lanjut)
  - Catatan survei (textarea)
  - Jarak ODP terdekat (meter)
  - Foto lokasi (upload, `capture="environment"` untuk mobile)
  - Rekomendasi paket (optional)
- [ ] Validasi semua field wajib sebelum simpan
- [ ] Simpan ke tabel `customer_surveys`, set `completed_at`
- [ ] Status customer → `waiting-acc`
- [ ] Broadcast event `SurveyCompleted` ke halaman FOP/Helpdesk

#### 3.2 Halaman Verifikasi & Pemasangan — Panel ACC

- [ ] Tampilkan data pelanggan + ringkasan laporan survey
- [ ] Status badge: **"Menunggu ACC"**
- [ ] Tombol **"Proses ke Tim"** — hanya untuk role FOP/Helpdesk dengan permission `customer.acc.process`
- [ ] Tekan → tampilkan konfirmasi (sudah konfirmasi ke pelanggan?)
- [ ] Konfirmasi → status customer berubah ke `waiting-installation`
- [ ] Otomatis trigger pembuatan task pemasangan (bisa langsung redirect ke form penjadwalan pemasangan)

**Acceptance Criteria:**
- Laporan survey tersimpan dengan foto
- Status berubah ke `waiting-acc` setelah laporan disimpan
- Tombol "Proses ke Tim" mengubah status ke `waiting-installation`
- FOP mendapat notifikasi real-time saat laporan survey masuk

---

### Sprint 4 — Fase Pemasangan: Penjadwalan, Task, & Laporan

**Tujuan:** Replikasi pola sprint 2–3 untuk fase pemasangan.

**Story & Tasks:**

#### 4.1 List Task FOP — Pemasangan

- [ ] Filter pelanggan status `waiting-installation` + SLA countdown
- [ ] Tombol "Buat Jadwal Pemasangan" per pelanggan
- [ ] Form penjadwalan tim teknisi (sama dengan sprint 2.2, type = `installation`)

#### 4.2 Task Teknisi — Pemasangan

- [ ] Task muncul di Kalender FOP dan List Task Teknisi (type badge: "Pemasangan")
- [ ] Notifikasi real-time saat task pemasangan di-assign

#### 4.3 Detail Task — Pemasangan

- [ ] Tampilkan ringkasan hasil survey sebagai referensi teknisi
- [ ] Tombol **"Mulai Pemasangan"** — validasi permission `customer.installation.start`
- [ ] Tekan → status customer → `process-installation`, `started_at` diisi

#### 4.4 Form Laporan Pemasangan

- [ ] Setelah `process-installation`, tombol berubah ke **"Laporan Pemasangan"**
- [ ] Form slide-over:
  - Serial number ONT/ONU
  - Serial number kabel/drop (meter yang dipakai)
  - Port ODP yang digunakan
  - Sinyal (dBm)
  - Foto instalasi perangkat (upload, `capture="environment"`)
  - Foto hasil speedtest
  - Catatan teknisi
- [ ] Simpan ke `customer_installations` + update `customer_technical_details`
- [ ] Status customer → `pending-verification`
- [ ] Broadcast event `InstallationCompleted` ke Admin

**Acceptance Criteria:**
- Task pemasangan bisa dijadwal dan dikerjakan teknisi
- Laporan pemasangan tersimpan dengan data device lengkap
- Status berubah ke `pending-verification` setelah laporan disimpan

---

### Sprint 5 — Verifikasi Admin & Aktivasi Pelanggan

**Tujuan:** Admin memverifikasi seluruh data dari registrasi s/d pemasangan, lalu mengaktifkan pelanggan.

**Story & Tasks:**

#### 5.1 Halaman Verifikasi Admin

- [ ] Tampilkan timeline lengkap:
  - Data registrasi (nama, alamat, paket)
  - Hasil laporan survey (kondisi, foto)
  - Hasil laporan pemasangan (device, sinyal, foto)
- [ ] Form verifikasi admin:
  - Paket yang diaktifkan (konfirmasi/ubah)
  - Tanggal mulai billing
  - Catatan admin (optional)
  - Checkbox konfirmasi data valid
- [ ] Tombol **"Aktivasi Pelanggan"** — permission `customer.verification.submit`

#### 5.2 Proses Aktivasi

- [ ] Simpan form verifikasi
- [ ] Generate CID (format: `{POP_CODE}{KATEGORI}{DISTRIBUSI}{REQ_ID}`)
- [ ] Status customer → `active`
- [ ] Buat record billing pertama (invoice pertama)
- [ ] Kirim notifikasi ke pelanggan (via Telegram bot / WhatsApp)
- [ ] Pindahkan ke Halaman Pelanggan Aktif

#### 5.3 Halaman Pelanggan Aktif

- [ ] Pastikan pelanggan dengan status `active` muncul di list pelanggan aktif
- [ ] Tampilkan CID, paket, tanggal aktif, data device

**Acceptance Criteria:**
- Admin bisa melihat seluruh data dari registrasi hingga pemasangan dalam satu halaman
- CID ter-generate otomatis sesuai spesifikasi
- Status berubah ke `active`, pelanggan muncul di halaman pelanggan aktif
- Notifikasi terkirim ke pelanggan

---

### Sprint 6 — SLA, Notifikasi Real-time & Polish

**Tujuan:** SLA countdown berjalan di semua fase, notifikasi Reverb terpasang merata, edge case ditangani.

**Story & Tasks:**

#### 6.1 SLA Engine

- [ ] Definisikan SLA default per fase (contoh: survey = 2 hari, pemasangan = 3 hari) — bisa dikonfigurasi via `.env` atau tabel `settings`
- [ ] Hitung `sla_deadline` saat task dibuat
- [ ] Livewire countdown komponen (reusable) yang subscribe ke Reverb channel
- [ ] Warning badge jika SLA < 24 jam
- [ ] Alert merah jika SLA sudah terlewat (overdue)
- [ ] Notifikasi Telegram ke FOP jika ada task overdue

#### 6.2 Reverb Broadcast Events

| Event | Channel | Subscriber |
|---|---|---|
| `TaskAssigned` | `private-user.{id}` | Teknisi |
| `SurveyCompleted` | `private-fop` | FOP/Helpdesk |
| `InstallationCompleted` | `private-admin` | Admin |
| `SlaWarning` | `private-fop` | FOP |
| `CustomerActivated` | `private-admin` | Admin |

- [ ] Buat semua event class dan listener
- [ ] Daftarkan di `BroadcastServiceProvider`
- [ ] Test koneksi Reverb di staging

#### 6.3 Edge Cases & Validasi

- [ ] Jika teknisi yang di-assign tidak bisa hadir → FOP bisa reassign teknisi lain tanpa reset status
- [ ] Jika laporan survey menyatakan "Tidak Layak" → FOP bisa menutup/membatalkan pelanggan dari halaman ACC
- [ ] Teknisi tidak bisa mengerjakan task yang bukan miliknya (validasi di controller + gate)
- [ ] Semua transisi status di-log ke tabel `customer_status_logs`

#### 6.4 UI Polish

- [ ] Responsive mobile untuk halaman task teknisi (prioritas utama — teknisi pakai HP)
- [ ] `capture="environment"` pada semua input foto
- [ ] Loading state di semua tombol aksi
- [ ] Empty state di list task FOP dan teknisi

**Acceptance Criteria:**
- SLA countdown tampil akurat di semua fase
- Semua broadcast event terkirim dan diterima subscriber yang tepat
- Teknisi tidak bisa akses/ubah task milik orang lain
- Halaman task teknisi mobile-friendly

---

## Ringkasan Sprint

| Sprint | Fokus | Estimasi |
|---|---|---|
| Sprint 1 | State machine, DB, permission seed | 3–4 hari |
| Sprint 2 | FOP penjadwalan survey, task teknisi, kalender | 4–5 hari |
| Sprint 3 | Laporan survey, verifikasi ACC | 3–4 hari |
| Sprint 4 | Penjadwalan pemasangan, task, laporan pasang | 4–5 hari |
| Sprint 5 | Verifikasi admin, aktivasi, generate CID | 3–4 hari |
| Sprint 6 | SLA engine, Reverb broadcast, edge cases, polish | 4–5 hari |
| **Total** | | **~21–27 hari** |

---

## Catatan Implementasi

### Permission Check (contoh controller)

```php
// CustomerSurveyController.php
public function start(Customer $customer)
{
    abort_unless(auth()->user()->can('customer.survey.start'), 403);
    abort_unless($customer->status === CustomerStatus::WaitingSurvey, 422);

    $customer->transitionTo(CustomerStatus::ProcessSurvey);

    CustomerTask::where('customer_id', $customer->id)
        ->where('type', 'survey')
        ->active()
        ->update(['started_at' => now()]);

    broadcast(new SurveyStarted($customer))->toOthers();

    return response()->json(['status' => 'ok']);
}
```

### State Machine Helper (Customer model)

```php
// App\Models\Customer.php
public function transitionTo(string $newStatus): void
{
    $allowed = CustomerStatus::allowedTransitions($this->status);

    abort_unless(in_array($newStatus, $allowed), 422, "Transisi tidak diizinkan: {$this->status} → {$newStatus}");

    $old = $this->status;
    $this->update(['status' => $newStatus]);

    CustomerStatusLog::create([
        'customer_id' => $this->id,
        'from_status' => $old,
        'to_status'   => $newStatus,
        'changed_by'  => auth()->id(),
        'changed_at'  => now(),
    ]);
}
```

### SLA Countdown (Livewire + Reverb)

```php
// App\Livewire\SlaCountdown.php
class SlaCountdown extends Component
{
    public string $deadline;
    public bool   $isOverdue = false;

    protected $listeners = ['echo-private:sla,SlaWarning' => 'refresh'];

    public function render()
    {
        $this->isOverdue = now()->gt($this->deadline);
        return view('livewire.sla-countdown');
    }
}
```

---

## Pertanyaan Terbuka (perlu konfirmasi sebelum sprint)

1. **Apakah FOP bisa langsung membuat jadwal pemasangan saat menekan "Proses ke Tim"**, atau tetap manual dari list task FOP?
2. **Berapa default SLA** untuk masing-masing fase (survey & pemasangan)?
3. **Jika laporan survey = "Tidak Layak"**, apakah pelanggan langsung dibatalkan atau masuk status khusus (misalnya `rejected-survey`)?
4. **Apakah satu teknisi bisa di-assign ke dua task berbeda di hari yang sama** (benturan jadwal)?
5. **Notifikasi ke pelanggan saat aktif** — via Telegram bot, WhatsApp, SMS, atau semua?


## Jawaban:
1. yang menekan "Proses ke Tim" adalah admin/helpdesk karena ketika survey harus di verifikasi oleh admin/helpdesk tersebut terkait data yang di berikan dan paket yanng akan di ambil, jadi ini harus di verifikasi manual oleh admin/helpdesk setelah admin/helpdesk yakin maka button "Proses ke Tim" akan di tekan oleh mereka bukan FOP
2. Default SLA
 - Countdown Survey (hitung Mundur)
   1x24 jam harus di survey

 - Countdown Verifikasi (Hitung Mundur)
   3x24 jam harus di verifikasi atau di pasang
3. Bisa, asalkan berbeda jam dan Jika tidak layak maka akan langsung di tolak/dibatalkan oleh admin/helpdesk
4. bisa di batasi per hari