# Analisa & Rancangan: Verifikasi Registrasi oleh Admin/CS

**Status:** Terbuka — analisa selesai 2026-09-15, implementasi belum mulai. Di luar sprint aktif (Sprint 8.10), dicatat sebagai ADHOC-73 di `docs/TASKS.md`.

**Sumber ide awal:** permintaan user (chat 2026-09-15) — pelanggan yang baru diregistrasi TIDAK BOLEH langsung nongol di Task FOP. Harus melalui "Verifikasi Registrasi" oleh Admin/CS dulu; begitu disetujui, baru pelanggan masuk antrean survey dan muncul di Task FOP.

**Keputusan yang sudah dikonfirmasi user (chat 2026-09-15):**
1. **Skip Survey DIKECUALIKAN** dari gerbang ini — jalur Skip Survey (Sales input data survey langsung saat registrasi) tetap langsung ke `waiting_installation` + Task/FopTask Pemasangan seperti sekarang, TIDAK berhenti di Verifikasi Registrasi.
2. **Tolak → `WorkflowTransition::REJECTED`** — pakai status yang sudah ada (sama seperti penolakan di tahap lain), pelanggan yang ditolak muncul di halaman Pelanggan Gagal (`customers.failed`).
3. Dokumen ini (analisa & rancangan) ditulis dulu sebelum coding — direview user sebelum implementasi.

---

## 1. Kondisi Saat Ini (bukti dari kode)

`CustomerController::store()` (`app/Http/Controllers/CustomerController.php`), jalur **non-Skip Survey** (baris ~604-625):

```php
} else {
    // 5. Sentralisasi Tiket: Auto-create Task antrean (Survey) + FopTask
    //    anchor-nya. FopTask dibuat di sini, bukan menunggu papan
    //    /fop-tasks dibuka: dia anchor wajib task_materials &
    //    task_work_tools, dan tanpa itu isian estimasi material serta
    //    checklist alat di laporan survey hilang tanpa pesan error.
    $year = date('Y');
    $count = Task::whereYear('created_at', $year)->count() + 1;
    Task::create([
        'task_number' => sprintf('TASK-%s-%04d', $year, $count),
        'task_type' => TaskType::SURVEY->value,
        'title' => 'Survey Calon Pelanggan: '.$customer->full_name,
        ...
    ]);

    app(FopTaskProvisioningService::class)->ensureForCustomer($customer, TaskType::SURVEY);
}
```

Ini jalan **di dalam transaksi `store()` yang sama**, seketika submit form Registrasi berhasil — **TIDAK ADA gerbang apa pun** sebelum ini. Efeknya:

- `Task` (SURVEY) langsung ada, status `pending`.
- `FopTask` (kategori SURVEY, status `draft`) langsung ada lewat `FopTaskProvisioningService::ensureForCustomer()` — dan FopTask inilah yang nongol di Papan FOP / Task FOP (`/fop-tasks`).
- Tapi `customers.status` (`WorkflowTransition`) **TETAP `registered`** — belum `waiting_survey`. Status itu baru pindah ke `waiting_survey` lewat aksi terpisah, `CustomerController::assignSurvey()` (assign teknisi + jadwal), yang dipanggil manual belakangan.

Jadi ada jeda ganjil: pelanggan status `registered` (belum "resmi" masuk antrean survey secara workflow) tapi **Task FOP-nya sudah ada dan bisa dikerjakan FOP/teknisi**. Ini persis yang dikeluhkan user — "tidak langsung masuk ke dalam FOP" berarti Task/FopTask itu semestinya BELUM ada sampai Admin/CS menyetujui.

`WorkflowTransition::allowedNextTransitions()` (`app/Enums/WorkflowTransition.php`):

```php
self::REGISTERED => [self::WAITING_SURVEY, self::WAITING_INSTALLATION, self::REJECTED],
```

`self::WAITING_INSTALLATION` di sini KHUSUS Skip Survey (lompat survey & ACC sepenuhnya, lihat komentar baris 44-46 file yang sama).

`CustomerSurveyController::index()` (Antrean Survey, `/surveys/queue`) sudah memfilter `status IN (waiting_survey, survey_in_progress)` — jadi begitu pelanggan pindah ke `waiting_survey`, otomatis nongol di sana tanpa perlu ubah controller ini.

**Precedent pola serupa yang sudah ada di codebase** — `BusinessDevelopmentVerificationController` (gerbang `WAITING_BUSINESS_DEVELOPMENT_VERIFICATION` sebelum pelanggan kategori Bisnis resmi `ACTIVE`): index (queue) → show (detail + form) → verify (satu aksi PUT, transisi status + efek samping tercatat). Rancangan di bawah **mengikuti bentuk yang sama** biar konsisten dengan pola yang sudah dipahami tim.

---

## 2. Masalah yang Diperbaiki

1. Task/FopTask Survey lahir tanpa gerbang manusia — begitu Sales/CS submit form, FOP langsung kebanjiran task walau datanya belum ditinjau siapa pun.
2. Tidak ada titik tanggung jawab "saya (Admin/CS) sudah cek data registrasi ini dan menyetujui" — jejak audit yang hilang.
3. Tidak ada jalur tolak registrasi di titik paling awal — sekarang penolakan cuma ada di tahap survey/instalasi (`CustomerSurveyController`, dll), bukan tepat setelah registrasi.

---

## 3. Desain

### 3.1 Tidak perlu state `WorkflowTransition` baru

Reuse `WorkflowTransition::REGISTERED` sebagai status "menunggu Verifikasi Registrasi" — itu memang makna aslinya (baru diregistrasi, belum apa-apa). Tidak ada migrasi kolom/enum baru untuk state. Transisi keluar dari `REGISTERED` (`WAITING_SURVEY` via approve, `REJECTED` via tolak) **sudah** valid di `allowedNextTransitions()` — tidak perlu ubah rules engine sama sekali.

**Skip Survey TETAP tidak tersentuh** — jalur itu transisi langsung `REGISTERED → WAITING_INSTALLATION` di `store()`, di luar cakupan dokumen ini (dikonfirmasi user, §Keputusan #1).

### 3.2 Pindahkan pembuatan Task+FopTask dari `store()` ke aksi "Setujui"

`CustomerController::store()` jalur non-Skip Survey (baris ~604-625 saat ini) **DIHAPUS bagian `Task::create()` + `ensureForCustomer()`-nya**. Setelah `store()`, pelanggan non-Skip-Survey cuma:
- Tersimpan sebagai `Customer` dengan `status = registered`.
- **Tidak** punya `Task`/`FopTask` sama sekali sampai diverifikasi.

Logic `Task::create(TaskType::SURVEY)` + `FopTaskProvisioningService::ensureForCustomer($customer, TaskType::SURVEY)` **dipindah utuh** (bukan diduplikasi) ke aksi approve controller baru (§3.4).

### 3.3 Antrean baru: "Verifikasi Registrasi"

Controller baru `CustomerRegistrationVerificationController`, pola identik `BusinessDevelopmentVerificationController`:

- `index()` — daftar `Customer::where('status', WorkflowTransition::REGISTERED->value)`, POP-scoped (`EffectiveAccessService`), **kecuali** yang sudah kadung Skip Survey (tidak masalah — Skip Survey sama sekali tidak transit lewat `registered` lama-lama, langsung `waiting_installation` dalam transaksi `store()` yang sama, jadi otomatis tidak pernah muncul di query ini).
- `show()` — detail satu pelanggan: identitas, alamat, paket+harga, dokumen (Foto Rumah kalau diupload, FAB kalau paket Bisnis — lihat ADHOC-72), buat Admin/CS menilai kelengkapan/kewajaran data sebelum approve.
- `approve()` (PUT) — dalam transaksi:
  1. Buat `Task` (SURVEY) — logic dipindah dari `store()` (§3.2).
  2. `FopTaskProvisioningService::ensureForCustomer($customer, TaskType::SURVEY)`.
  3. `CustomerWorkflowService::transition($customer, WorkflowTransition::WAITING_SURVEY, 'Registrasi diverifikasi Admin/CS')`.
  4. `AuditLog` (module "Data Pelanggan", action `verify_registration`).
  5. Notifikasi ke pembuat registrasi (Sales/CS yang input) — pola sama `BusinessDevelopmentVerificationController::verify()`.
- `reject()` (PUT) — dalam transaksi:
  1. Validasi `reason` wajib diisi (reuse `App\Support\ReasonValidationRule`, pola dipakai penolakan lain di repo ini — cek dulu pemakaiannya di `TicketService`/`CustomerVerificationController` biar konsisten format alasannya).
  2. `CustomerWorkflowService::transition($customer, WorkflowTransition::REJECTED, $reason)`.
  3. `AuditLog` (action `reject_registration`).
  4. Notifikasi ke pembuat registrasi.
  5. **Tidak** ada Task/FopTask untuk dibersihkan — belum pernah dibuat (§3.2), jadi tolak di sini tidak butuh cascade cancel apa pun. Lebih sederhana dari pembatalan pasca-FOP.

Pelanggan yang ditolak otomatis nongol di halaman **Pelanggan Gagal** (`customers.failed`, sudah ada, filter `status = rejected`) — tidak perlu halaman baru untuk itu.

### 3.4 Routes & Permission

Ikuti pola persis `business-development-verifications` (`routes/web.php` baris ~972-976):

```php
Route::middleware('permission:customer_registration_verification.view')->group(function () {
    Route::get('/customer-registration-verifications', [CustomerRegistrationVerificationController::class, 'index'])->name('customer-registration-verifications.index');
    Route::get('/customer-registration-verifications/{customer}', [CustomerRegistrationVerificationController::class, 'show'])->name('customer-registration-verifications.show');
    Route::put('/customer-registration-verifications/{customer}/approve', [CustomerRegistrationVerificationController::class, 'approve'])->name('customer-registration-verifications.approve');
    Route::put('/customer-registration-verifications/{customer}/reject', [CustomerRegistrationVerificationController::class, 'reject'])->name('customer-registration-verifications.reject');
});
```

**Diputuskan user (chat 2026-09-15):**

- **Murni soal RBAC** — jangan hardcode role di controller (controller cuma cek permission, bukan cek nama role). `RoleSeeder` **tidak punya** role bernama "CS" — role yang paling dekat perannya adalah `helpdesk` (customer-facing, sudah pegang Worksheet Helpdesk/Ticketing). Permission baru di-*grant default* ke `helpdesk` lewat `RolePermissionSeeder`, tapi tetap bisa dipindah/ditambah ke role lain kapan saja lewat Role Matrix (`roles.matrix`) — **tidak ada logic yang mengunci ke role tertentu di kode**, konsisten dengan RBAC generik yang sudah dipakai seluruh repo (`EffectiveAccessService`).
- **Approve & Reject permission TERPISAH** dari `view` — 3 permission di feature `customer_registration_verification`: `view` (lihat antrean+detail), `approve` (`ActionCode::APPROVE`, sudah ada di enum), `reject` (`ActionCode::REJECT`, sudah ada di enum). Beda dari `business_development_verification` (yang cuma `view` + gerbang dinamis per-pelanggan) — di sini approve/reject dua aksi independen yang wajar dipisah PIC-nya (mis. yang boleh lihat antrean lebih luas dari yang boleh eksekusi approve/reject).
- **Alasan tolak = textarea bebas**, divalidasi `App\Support\ReasonValidationRule` (dicek dulu signature-nya saat implementasi, pola sama dipakai penolakan lain di repo ini).

```php
// config/rbac.php
'customer_registration_verification' => [
    ActionCode::VIEW->value,
    ActionCode::APPROVE->value,
    ActionCode::REJECT->value,
],
```

```php
// routes/web.php
Route::middleware('permission:customer_registration_verification.view')->group(function () {
    Route::get('/customer-registration-verifications', [...]::class, 'index'])->name('customer-registration-verifications.index');
    Route::get('/customer-registration-verifications/{customer}', [...]::class, 'show'])->name('customer-registration-verifications.show');
});
Route::middleware('permission:customer_registration_verification.approve')->group(function () {
    Route::put('/customer-registration-verifications/{customer}/approve', [...]::class, 'approve'])->name('customer-registration-verifications.approve');
});
Route::middleware('permission:customer_registration_verification.reject')->group(function () {
    Route::put('/customer-registration-verifications/{customer}/reject', [...]::class, 'reject'])->name('customer-registration-verifications.reject');
});
```

`RolePermissionSeeder.php` — role `helpdesk` dapat ketiganya (`view`, `approve`, `reject`) secara default; `admin`/`owner` biasanya sudah `hasFullAccess()` (`*`) jadi otomatis lolos tanpa entry eksplisit.

### 3.5 Sidebar & UI

Tambah menu "Verifikasi Registrasi" (badge counter, pola sama menu FOP/Ticketing lain) di `components/layout/sidebar.blade.php`, gerbang `@can('customer_registration_verification.view')`.

View: `resources/views/customer-registration-verifications/index.blade.php` (list + counter) dan reuse sebanyak mungkin partial dari `verifications/admin.blade.php` untuk tab detail data pelanggan (identitas/alamat/paket/dokumen) — TAPI perlu view baru, bukan reuse langsung `verifications.admin`, karena view itu dirancang untuk tahap verifikasi PASCA-instalasi (ada tab Survey/Pemasangan/Pengujian yang datanya belum ada sama sekali di tahap registrasi). Cukup ambil pola kartu tombol Setujui/Tolak dari situ.

---

## 4. Dampak ke Kode & Dokumen Lain

| Area | Dampak |
|---|---|
| `CustomerController::store()` | Hapus `Task::create(SURVEY)` + `ensureForCustomer(SURVEY)` di jalur non-Skip-Survey. Jalur Skip Survey (Task PEMASANGAN) **tidak disentuh**. |
| `app/Enums/WorkflowTransition.php` | **Tidak berubah** — state & transisi yang dipakai sudah ada. |
| `config/rbac.php`, seeder fitur/permission | Tambah feature `customer_registration_verification` + action `view` (dan aksi approve/reject kalau diputuskan perlu permission terpisah, §3.4). |
| `database/seeders/RolePermissionSeeder.php` | Grant permission baru ke role yang tepat — perlu konfirmasi role mana ("Admin/CS" di kalimat user bisa berarti role `admin`, atau `helpdesk`, atau keduanya). |
| `routes/web.php`, `resources/views/components/layout/sidebar.blade.php` | Route group + menu baru (§3.4-3.5). |
| `resources/views/customer-registration-verifications/*.blade.php` (baru) | Halaman index + show. |
| `app/Http/Controllers/CustomerRegistrationVerificationController.php` (baru) | Index/show/approve/reject. |
| `docs/pendaftaran-pelanggan/README.md`, `docs/customer-lifecycle/business-logic.md` | Update alur: Registrasi → **Verifikasi Registrasi (Admin/CS)** → Antrean Survey → ... Tandai eksplisit Skip Survey sebagai jalur yang MELOMPATI gerbang ini. |
| `docs/fop-task/analisa-sync-execution-task.md` | Update titik provisioning FopTask SURVEY — sekarang di `CustomerRegistrationVerificationController::approve()`, bukan lagi `CustomerController::store()`. |
| Test | Tidak ada test existing yang menaruh assertion "FopTask/Task langsung ada setelah `customers.store`" (sudah dicek — nihil), jadi risiko regresi test lama rendah. Test baru wajib: (1) submit Registrasi non-Skip-Survey → **tidak** ada Task/FopTask, status tetap `registered`; (2) approve → Task+FopTask+`waiting_survey` muncul; (3) reject → status `rejected`, tidak ada Task/FopTask; (4) Skip Survey **tidak terpengaruh** (Task PEMASANGAN tetap langsung ada seperti sekarang); (5) RBAC — actor tanpa permission ditolak 403; (6) POP scope — actor cuma lihat pelanggan di POP-nya. |

---

## 5. Keputusan Final (2026-09-15) — semua terjawab, siap implementasi

1. **RBAC generik, bukan hardcode role** — controller cuma cek permission. Default digrant ke role `helpdesk` (role paling dekat "CS" di `RoleSeeder`), tapi Admin bisa pindah/tambah ke role lain kapan saja lewat Role Matrix tanpa ubah kode.
2. **Approve & Reject permission terpisah** dari `view` (§3.4 di atas, sudah difinalkan).
3. **Alasan tolak = textarea bebas**, `ReasonValidationRule`.
4. Notifikasi: ikut pola `AppNotification` in-app yang sudah ada (sama seperti `BusinessDevelopmentVerificationController`) — cukup, tidak perlu Telegram untuk gerbang ini.
5. Nama menu/halaman: "Verifikasi Registrasi" dipakai apa adanya, sesuai istilah user.

---

## 6. Rencana Implementasi (setelah rancangan ini disetujui)

1. Migrasi RBAC: feature + permission baru, seed ke role yang dikonfirmasi (§5.1-2).
2. `CustomerRegistrationVerificationController` (index/show/approve/reject) + routes.
3. Pindahkan blok `Task::create(SURVEY)` + `ensureForCustomer()` dari `CustomerController::store()` ke `approve()`.
4. View index + show (baru, bukan reuse `verifications.admin` mentah-mentah — lihat §3.5).
5. Menu sidebar + badge counter.
6. Test Feature baru (§4, baris "Test").
7. Update dokumen (§4, baris dokumen) + `docs/TASKS.md` (pindah ADHOC-73 dari Terbuka ke Done).
8. `vendor/bin/pint` + jalankan test terkait + full suite.
