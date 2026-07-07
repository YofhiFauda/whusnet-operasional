> **Arsip.** Dokumen desain/rencana historis RBAC — sebagian besar sudah diimplementasi (lihat [../README.md](../README.md), [../business-logic.md](../business-logic.md) untuk kondisi kode terkini).

﻿# Analisa: RBAC Dinamis (Feature–Action–Scope) untuk WHUSNET Admin Payment

**Project:** WHUSNET Admin Payment
**Topik:** Desain Role & Permission dinamis agar perubahan role/akses tidak memerlukan perubahan kode
**Tanggal:** 20 Juni 2026
**Berdasarkan:** Diskusi RBAC + `spesifikasi-pop-distribusi-cid.md` + `implementation-plan-registrasi-survey-verifikasi.md` + roadmap `Sprint 11 — Advanced Hierarchical RBAC Planning & Documentation.md` (Sprint 11–13 Advanced RBAC)

---

## 1. Masalah yang Ingin Diselesaikan

Pertanyaan awal: apakah alur sistem (siapa boleh melakukan apa) bisa diatur lewat halaman Pengguna berbasis RBAC, bukan di-hardcode di kode?

Kekhawatiran yang muncul selama diskusi:
1. Ada alur proses yang terasa "pasti hardcode" (contoh: registrasi → survey → FOP menjadwalkan teknisi).
2. Role di project ini **belum final** — ada kemungkinan tambah/kurang role di kemudian hari (lihat daftar role di `Sprint 11 — Advanced Hierarchical RBAC Planning & Documentation.md`: Owner, Atasan, Admin, NOC, Helpdesk, FOP, Teknisi, Sales, POP Admin).
3. Kalau role belum pasti, apakah RBAC dinamis tetap mungkin diterapkan?

**Kesimpulan inti:** Bisa, dan justru kondisi "role belum pasti" adalah alasan **utama** untuk pakai RBAC dinamis, bukan alasan untuk hardcode.

---

## 2. Prinsip Dasar: Pisahkan 3 Hal yang Sering Tertukar

| Konsep | Pertanyaan yang dijawab | Sifat | Tempat hidup |
|---|---|---|---|
| **Workflow / State Machine** | "Apa yang terjadi dan urutannya?" | Tetap (fixed business logic) | Kode (`CustomerWorkflowService`, enum status) |
| **RBAC (Role + Permission)** | "Siapa boleh memicu aksi ini?" | Dinamis (berubah sesuai kebutuhan organisasi) | Database |
| **Scope** | "Data wilayah/cabang mana yang boleh dilihat?" | Dinamis, terpisah dari Role | Database |

Kesalahan paling umum: menulis kode seperti ini —

```php
// ❌ Role hardcode menyatu dengan business logic
if ($user->role === 'fop') {
    $survey->assignTeam($teknisiIds);
}
```

Yang membuat **role** (bukan urutan prosesnya) ikut hardcode. Solusinya: kode hanya bicara *permission*, bukan *nama role*.

```php
// ✅ Business logic (workflow) tetap eksplisit di kode — ini wajar
$survey->status = 'survey_scheduled';
$survey->assignTeam($teknisiIds);
$survey->save();
event(new SurveyScheduled($survey));
```

```php
// ✅ Permission check terpisah, sumbernya dari database
if (!$user->hasPermission('customer.survey.validate')) {
    abort(403);
}
```

---

## 3. Kenapa "Role per Cabang" adalah Jebakan

Pola yang **harus dihindari**: membuat role baru setiap kali ada unit organisasi baru.

```
❌ NOC Siman, NOC Jetis, Teknisi Siman, Teknisi Jetis, ...
```

Ini meledak jumlahnya seiring penambahan cabang/POP, dan tetap butuh sentuh kode/seeder tiap kali cabang baru dibuka.

**Solusi: Role + Scope dipisah.**

```
Role  = kemampuan apa yang dimiliki        → FOP boleh assign teknisi
Scope = wilayah data mana yang terlihat    → FOP ini hanya untuk Cabang Jetis
```

Satu user = kombinasi (1 atau lebih) Role + 1 Scope assignment.

Contoh dari `Sprint 11 — Advanced Hierarchical RBAC Planning & Documentation.md`:
```
User: NOC Pusat   | Role: NOC       | Scope: all_pop
User: Admin Siman | Role: POP Admin | Scope: selected_pop (POP Siman)
```

Role `NOC` dan `POP Admin` cukup didefinisikan **sekali**. Penambahan cabang baru = data baru di tabel scope, **bukan** role baru.

---

## 4. Skema Database: Feature Tree + Action + Permission + Scope

### 4.1 Feature Tree
Representasi modul/sub-modul sistem secara hierarkis.

```sql
CREATE TABLE features (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    parent_id BIGINT NULL REFERENCES features(id),
    code VARCHAR(50) UNIQUE NOT NULL,   -- contoh: 'customer', 'customer.survey', 'invoice'
    name VARCHAR(100) NOT NULL,
    sort_order INT DEFAULT 0
);
```

### 4.2 Actions (daftar tetap, lintas-feature)
```sql
CREATE TABLE actions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(30) UNIQUE NOT NULL
    -- view, create, update, delete, import, export, print,
    -- validate, activate, cancel, upload, download,
    -- view_sensitive, update_sensitive
);
```

### 4.3 Permissions (kombinasi Feature × Action)
```sql
CREATE TABLE permissions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    feature_id BIGINT REFERENCES features(id),
    action_id BIGINT REFERENCES actions(id),
    code VARCHAR(150) UNIQUE NOT NULL,  -- generated: '{feature_code}.{action_code}'
    UNIQUE (feature_id, action_id)
);
```

### 4.4 Roles (statis strukturnya, dinamis isinya)
```sql
CREATE TABLE roles (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,  -- owner, admin, noc, helpdesk, fop, teknisi, sales, pop_admin
    name VARCHAR(100) NOT NULL,
    is_system BOOLEAN DEFAULT FALSE     -- true = tidak bisa dihapus, contoh: Owner
);

CREATE TABLE role_permissions (
    role_id BIGINT REFERENCES roles(id),
    permission_id BIGINT REFERENCES permissions(id),
    PRIMARY KEY (role_id, permission_id)
);
```

### 4.5 User Role + Scope (inti dari "siapa di mana")
```sql
CREATE TABLE user_role_scopes (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT REFERENCES users(id),
    role_id BIGINT REFERENCES roles(id),
    scope_type ENUM('all_pop','selected_pop','pop_tree','assigned_only','own_created') NOT NULL,
    UNIQUE (user_id, role_id)
);

CREATE TABLE user_role_scope_targets (
    user_role_scope_id BIGINT REFERENCES user_role_scopes(id),
    pop_id BIGINT REFERENCES pops(id),   -- Cabang POP atau Mini POP
    PRIMARY KEY (user_role_scope_id, pop_id)
);
```

### 4.6 Optional: Override per-user
Untuk pengecualian individual tanpa membuat role baru.
```sql
CREATE TABLE user_permission_overrides (
    user_id BIGINT REFERENCES users(id),
    permission_id BIGINT REFERENCES permissions(id),
    effect ENUM('allow','deny') NOT NULL
);
```

---

## 5. Definisi Scope Type

| scope_type | Arti | Contoh pemakaian |
|---|---|---|
| `all_pop` | Lihat semua Cabang/POP/Mini POP | NOC Pusat |
| `selected_pop` | Hanya POP tertentu yang dipilih manual | POP Admin Siman, FOP Cabang Jetis |
| `pop_tree` | Satu Cabang POP beserta seluruh Mini POP di bawahnya | NOC Cabang |
| `assigned_only` | Hanya data yang ditugaskan ke user tsb | Teknisi (hanya survey/instalasi yang di-assign ke dia) |
| `own_created` | Hanya data yang dibuat sendiri oleh user | Sales (hanya pelanggan yang dia daftarkan) |

---

## 6. Pemetaan ke Alur Registrasi → Survey → Verifikasi → Aktivasi

Ini adalah jawaban langsung untuk **Open Question #2** pada `implementation-plan-registrasi-survey-verifikasi.md`: *"siapa yang boleh tekan Proses ke Tim vs Verifikasi vs Survey Data?"*

| Feature code | Action | Permission code | Dipicu di Controller (sesuai implementation plan) | Role yang biasanya diberi izin |
|---|---|---|---|---|
| `customer.registration` | `create` | `customer.registration.create` | `CustomerRegistrationController@store` | Sales, Admin, CS |
| `customer.survey` | `validate` (action "Survey Data" / start) | `customer.survey.validate` | `SurveyController@start` | FOP, Teknisi |
| `customer.survey` | `update` (action "Lapor Data") | `customer.survey.update` | `SurveyController@complete` | Teknisi |
| `customer.installation` | `validate` (action "Proses ke Tim") | `customer.installation.validate` | `VerificationController@processToTeam` | Admin, NOC |
| `customer.installation` | `activate` (action "Start Proses") | `customer.installation.activate` | `InstallationController@start` | FOP, Teknisi |
| `customer.installation` | `update` (action "Lapor Pemasangan") | `customer.installation.update` | `InstallationController@complete` | Teknisi |
| `customer.verification` | `activate` (action "Verifikasi" final) | `customer.verification.activate` | `VerificationController@finalVerify` | Admin, NOC |
| `invoice` | `create` (Modal Buat Tagihan Manual) | `invoice.create` | bagian dari `finalVerify` flow | Admin, Helpdesk |
| `customer.technical_detail` | `view_sensitive` | `customer.technical_detail.view_sensitive` | tampilan Detail Pelanggan | NOC, Teknisi (bukan Sales) |

> Catatan: kolom *Role yang biasanya diberi izin* di atas **bukan hardcode** — itu hanya isi awal seeder `role_permissions`. Bisa diubah kapan pun lewat halaman Pengguna tanpa redeploy.

---

## 7. Implementasi di Kode (Stabil, Tidak Berubah Walau Role Berubah)

### 7.1 Middleware permission
```php
// app/Http/Middleware/CheckFeaturePermission.php
class CheckFeaturePermission
{
    public function handle($request, Closure $next, string $permissionCode)
    {
        if (!auth()->user()->hasPermission($permissionCode)) {
            abort(403);
        }
        return $next($request);
    }
}
```

### 7.2 Route
```php
Route::post('/survey/{survey}/start', [SurveyController::class, 'start'])
    ->middleware('permission:customer.survey.validate');

Route::post('/survey/{survey}/complete', [SurveyController::class, 'complete'])
    ->middleware('permission:customer.survey.update');

Route::post('/verification/{customer}/process-to-team', [VerificationController::class, 'processToTeam'])
    ->middleware('permission:customer.installation.validate');
```

### 7.3 Controller — tidak ada satu pun nama role di sini
```php
public function start(Survey $survey)
{
    $this->workflowService->transition($survey->customer, 'survey_in_progress');
    $survey->update(['started_at' => now(), 'survey_status' => 'in_progress']);
    broadcast(new SurveyStarted($survey));
}
```

### 7.4 Scope Enforcement via Eloquent Global Scope
Permission menjawab "boleh ngapain", scope menjawab "data mana yang kelihatan". Ini wajib jadi Global Scope, bukan filter manual yang ditulis ulang di tiap query (rawan lupa/bocor data antar-cabang).

```php
// app/Models/Scopes/PopScope.php
class PopScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        $user = auth()->user();
        if (!$user) return;

        $scope = $user->roleScopes()->first(); // simplifikasi; bisa dikembangkan utk multi-role

        match ($scope->scope_type) {
            'all_pop'       => null, // tidak difilter
            'selected_pop'  => $builder->whereIn($model->getTable().'.pop_id', $scope->target_pop_ids),
            'pop_tree'      => $builder->whereIn($model->getTable().'.pop_id', $scope->getDescendantPopIds()),
            'assigned_only' => $builder->where('assigned_user_id', $user->id),
            'own_created'   => $builder->where('created_by', $user->id),
        };
    }
}

// Customer.php
protected static function booted()
{
    static::addGlobalScope(new PopScope);
}
```

---

## 8. Matriks: Apa yang Disentuh Saat Ada Perubahan Organisasi

| Perubahan | Yang disentuh | Yang TIDAK disentuh |
|---|---|---|
| Tambah role baru (mis. "Koordinator Wilayah") | Insert ke `roles` + `role_permissions` lewat UI | Controller, Middleware, Route |
| FOP Cabang Jetis dipindah ke Cabang Siman | Update `user_role_scope_targets` | Kode, Migration |
| Teknisi tidak lagi boleh lihat data sensitif | Hapus 1 baris di `role_permissions` | Tidak ada |
| Role lama dihapus (mis. "Teknisi Lepas" dibubarkan) | Soft-delete/hapus row di `roles` | Tidak ada referensi nama role di kode yang perlu dicari |
| Fitur benar-benar baru (mis. "Export Laporan BUMN") | Insert row baru di `features` + `permissions`, assign ke role; **plus** 1 controller baru ditulis | Wajar disentuh — ini logic baru, bukan masalah RBAC |

Prinsip: kode hanya berubah saat ada **kapabilitas/logic baru**, bukan saat ada perubahan struktur organisasi/role.

---

## 9. Kaitan dengan Aturan dari `Sprint 11 — Advanced Hierarchical RBAC Planning & Documentation.md` (Sprint 11–13)

Dokumen roadmap Advanced RBAC sudah menetapkan aturan yang selaras dengan analisa ini, di antaranya:

- Role tidak boleh dibuat per cabang (lihat Notes Sprint 11–13, poin 2).
- Format permission wajib `{feature_code}.{action_code}` (poin 7).
- Query data wajib mengikuti user scope (poin 8) — dipenuhi lewat Global Scope di §7.4.
- Route wajib dilindungi middleware permission (poin 9) — dipenuhi di §7.2.
- Menu yang disembunyikan **bukan pengganti** middleware (poin 10) — UI hanya menyembunyikan, validasi tetap di server.
- Field sensitif wajib dibatasi permission (poin 11) — direpresentasikan lewat action `view_sensitive` / `update_sensitive`.
- Semua perubahan RBAC wajib masuk audit log (poin 12).

Advanced RBAC ini dijadwalkan dikerjakan **setelah** S8-T006 (Import Data Legacy) selesai, sesuai urutan di roadmap tersebut.

---

## 10. Langkah Lanjutan yang Direkomendasikan

1. Selesaikan dulu **Sprint 1** di `implementation-plan-registrasi-survey-verifikasi.md` (state machine + `CustomerWorkflowService`) — ini fondasi tempat permission akan menempel.
2. Saat menulis Controller di Sprint 2–4, langsung pasang middleware `permission:{code}` di setiap route sejak awal — boleh sementara pakai daftar permission hardcode sebagai placeholder, lalu di Sprint 11+ tinggal diarahkan ke sumber database tanpa mengubah signature middleware maupun route.
3. Jawab eksplisit **Open Question #2** (siapa boleh tekan "Proses ke Tim" vs "Verifikasi" vs "Survey Data") — jawaban ini langsung menjadi isi awal seeder `role_permissions` (lihat tabel di §6).
4. Lanjutkan ke Sprint 11–13 sesuai roadmap `Sprint 11 — Advanced Hierarchical RBAC Planning & Documentation.md` untuk implementasi penuh Feature Tree, Action Permission, dan User Scope di database + UI.

---

## 11. Ringkasan Satu Kalimat

**Workflow (urutan proses) boleh dan memang wajar tetap eksplisit di kode sebagai state machine; yang harus dinamis dari database adalah *siapa* yang berhak memicu tiap transisi (Role + Permission) dan *data wilayah mana* yang boleh mereka lihat (Scope) — keduanya dipisah agar penambahan/pengurangan role atau cabang tidak pernah memerlukan deploy ulang kode.**
