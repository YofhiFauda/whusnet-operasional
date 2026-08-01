# Analisa Pemisahan Permission Verifikasi Admin vs Detail Laporan (Survey & Pemasangan)

**Tanggal:** 31 Juli 2026  
**Status:** Dicatat & Siap Diimplementasikan  

---

## 1. Latar Belakang & Identifikasi Masalah

Saat ini terdapat ketidaksesuaian antara pemisahan hak akses (RBAC permission) dengan pengarahan tombol detail pada antrean operasional:

1. **Halaman `/verifications/{customer}/admin`** diproteksi dengan permission `customers.detail.installation.validate`. Halaman ini berisi form aksi eksekusi berisiko tinggi (*high privilege*) seperti approval verifikasi akhir, penetapan port ODP/IP, penerbitan invoice awal, revisi, dan reject.
2. **Masalah Link Routing**: Tombol "Detail" (ikon mata) di tabel antrean survey (`surveys/queue.blade.php`) dan antrean verifikasi/pemasangan (`verifications/queue.blade.php`) saat ini mengarahkan ke `route('customers.verification.admin', $customer)`.
3. **Dampak**: User yang hanya memiliki izin membaca/melihat (*read-only*) seperti Teknisi, CS, Helpdesk, atau FOP tanpa hak `customers.detail.installation.validate` terkena **HTTP 403 Forbidden** ketika mengeklik tombol "Detail".

---

## 2. Matriks Pemisahan Route & Permission

Untuk menjaga prinsip keamanan RBAC dan memberikan akses informasi yang tepat sesuai kebutuhan role pengguna:

| Modul / Tahap | Fungsi Halaman | Route Name & URL Pattern | Permission Required | Target Role |
| :--- | :--- | :--- | :--- | :--- |
| **Detail Survey** | Melihat laporan hasil survey teknis (foto, koordinat, kelayakan) | `customers.survey.report`<br>`/customers/{customer}/survey/report` | `customers.detail.survey.view`<br>`customers.detail.survey.update` | Teknisi, Sales, CS, Helpdesk, FOP, Admin |
| **Detail Pemasangan** | Melihat laporan hasil instalasi (foto ODP/modem, signal redaman, test report) | `customers.installation.report`<br>`/customers/{customer}/installation/report` | `customers.detail.installation.view`<br>`customers.detail.installation.update` | Teknisi, FOP, Helpdesk, CS, Admin |
| **Profil Pelanggan** | Melihat data umum registrasi dan kontak pelanggan | `customers.show`<br>`/customers/{customer}` | `customers.view` | Seluruh Role Operational |
| **Verifikasi Admin** *(Approval)* | Mengesahkan instalasi, menetapkan ODP/IP, menerbitkan invoice awal, reject/revisi | `customers.verification.admin`<br>`/verifications/{customer}/admin` | `customers.detail.installation.validate` | Khusus Admin Pusat, Admin POP, NOC, Owner |

---

## 3. Rencana Perbaikan (Blade View Updates)

1. **`resources/views/surveys/queue.blade.php`**:
   * Ubah link ikon "Detail" (Mata) dari `customers.verification.admin` ke `route('customers.survey.report', $customer)`.

2. **`resources/views/verifications/queue.blade.php`**:
   * Ubah link ikon "Detail" (Mata) umum dari `customers.verification.admin` ke `route('customers.installation.report', $customer)`.
   * Pertahankan tombol **"Verifikasi Admin"** atau **"Detail & Review"** mengarah ke `route('customers.verification.admin', $customer)` yang di-wrap dengan `@can('customers.detail.installation.validate')`.

3. **`resources/views/tasks/show.blade.php`**:
   * Pertahankan navigasi ke Verifikasi Admin khusus untuk alur approval/aktivasi pelanggan yang dilakukan FOP/Admin.
