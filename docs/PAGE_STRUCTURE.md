# PAGE_STRUCTURE.md - UI/UX Halaman Advanced RBAC

# Website Billing ISP Berbasis Master Data Pelanggan

## 1. Tujuan Dokumen

Dokumen ini mendefinisikan rancangan struktur halaman (UI/UX wireframe conceptual) untuk pengelolaan **Advanced Hierarchical RBAC** pada Website Billing ISP. 

Dokumen ini menjadi acuan mutlak bagi frontend developer / AI saat membangun layout, blade views, komponen JavaScript, navigasi sidebar, tombol aksi, serta sensor field sensitif.

---

## 2. Struktur Layout Global (Sidebar)

Sistem menggunakan layout berbasis **Sidebar Kiri** untuk navigasi utama (bukan navigasi atas).

### 2.1 Aturan Rendering Menu Berbasis Otorisasi
- **Menu Utama:** Tampil jika user memiliki salah satu permission `view` di bawah rumpun menu tersebut.
- **Sub-Menu:** Tampil hanya jika user memiliki permission `view` khusus pada sub-fitur bersangkutan.
- **Empty State:** Jika user tidak memiliki hak akses sama sekali ke suatu rumpun fitur, modul navigasi tersebut disembunyikan sepenuhnya dari sidebar (tidak boleh dirender sebagai menu disable).

---

## 3. Rancangan Halaman Konfigurasi RBAC

Halaman-halaman berikut hanya boleh diakses oleh user dengan role `Owner` dan `Admin` (melalui verifikasi route middleware `permission:role.view` dan `permission:permission.view`).

### 3.1 Halaman Feature Management (`/admin/rbac/features`)
Digunakan untuk mengelola Feature Tree (hierarki modul).
*   **Elemen UI:**
    *   **Expand/Collapse All Button:** Untuk membuka atau menutup semua tingkatan hierarki.
    *   **Hierarchical Tree View:** Menampilkan struktur utama, cabang fitur, dan mini fitur dalam format berjenjang (indented list).
    *   **Empty State:** Jika data fitur kosong, tampilkan ilustrasi "Fitur belum ditambahkan" dengan tombol "PHP Artisan Seed Features" (khusus Mode Developer).
    *   **Form Popup (Tambah/Edit Node):**
        *   Dropdown: Parent Feature (nullable).
        *   Input Text: Feature Code (unique, e.g., `customers.detail.devices`).
        *   Input Text: Feature Name (e.g., "Data Perangkat").
        *   Input Number: Sort Order.

### 3.2 Halaman Action Management (`/admin/rbac/actions`)
Digunakan untuk mengelola daftar Action (aksi umum).
*   **Elemen UI:**
    *   **Tabel Daftar Action:** Menampilkan kolom ID, Action Code (e.g., `view_sensitive`), dan Deskripsi.
    *   **Form Popup (Tambah/Edit Action):**
        *   Input Text: Action Code (lowercase, e.g., `download`).
        *   Input Text: Nama/Deskripsi Aksi (e.g., "Mengunduh Lampiran").

### 3.3 Halaman Role & Permission Matrix (`/admin/rbac/matrix`)
Pusat pengaturan izin akses untuk setiap role.
*   **Elemen UI:**
    *   **Role Selector Dropdown:** Memilih role target yang ingin diatur (e.g., `NOC`, `Teknisi`).
    *   **Permission Matrix Tree Table:**
        *   Kolom Kiri: Feature Tree dengan visual berjenjang.
        *   Kolom Kanan: Checkbox aksi yang relevan (CRUD + custom actions).
        *   *Contoh Tampilan Matrix:*
            ```
            [ ] customers
                ├── [ ] customers.detail
                │    ├── customers.detail.devices
                │    │    └── view_sensitive:    [ ] Checkbox
                │    │    └── update_sensitive:  [ ] Checkbox
            ```
    *   **Interactive Behavior:**
        *   Mencentang node parent otomatis mencentang seluruh node child di bawahnya (*Cascade Check*).
        *   Menghilangkan centang parent otomatis menghapus centang seluruh child (*Cascade Uncheck*).
    *   **Tombol Simpan:** Mengirim array `permission_id` yang dicentang ke backend (`role_permissions`). Aktivitas ini wajib dicatat ke Audit Log.

---

## 4. Struktur Form Tambah/Edit User & Penugasan Scope

Halaman pengelolaan user `/admin/users/create` dan `/admin/users/{id}/edit` dilengkapi dengan pengaturan Role dan wilayah cakupan data (Scope) yang aman.

### 4.1 Form Elemen Otorisasi User
1.  **Dropdown Role:** Memilih satu atau lebih Role (Owner, Admin, Admin POP, FOP, Teknisi, Sales, Helpdesk, dsb).
2.  **Dropdown Scope Type:**
    *   Pilihan: `all_pop`, `selected_pop`, `pop_tree`, `assigned_only`, `own_created`.
3.  **POP Target Selector (Conditional):**
    *   Hanya muncul jika Scope Type yang dipilih adalah `selected_pop` atau `pop_tree`.
    *   Menggunakan multi-select checkbox atau tag-input untuk memilih satu atau banyak POP dari database.
    *   *Validasi Client-Side:* Jika scope `selected_pop` dipilih, user tidak boleh disubmit sebelum minimal memilih satu POP target.

### 4.2 Panel Preview Effective Permission (Izin Efektif)
Panel interaktif di sisi kanan form yang menampilkan ringkasan hak akses nyata yang akan diperoleh user secara real-time sebelum tombol Simpan ditekan:
*   **Header:** "Preview Hak Akses Efektif Pengguna"
*   **Data Scope:** Menampilkan ringkasan wilayah data (e.g., "Hanya dapat melihat data di POP Siman & POP Jetis").
*   **Data Action:** Menampilkan daftar modul yang bisa diakses beserta level aksinya dalam format badges (e.g., `[Pelanggan: View, Create, Update]`, `[Pembayaran: No Access]`).
*   **Warning Box (Auto-Evaluation):**
    *   Jika Role `Teknisi` dipilih tetapi user mencoba memberi permission `payments.create`, tampilkan peringatan: `"Warning: Role Teknisi umumnya tidak diperbolehkan mencatat pembayaran."`
    *   Jika Scope `all_pop` dipilih pada role lokal, tampilkan warning: `"Peringatan: User ini akan dapat melihat seluruh data POP secara nasional."`

---

## 5. Aturan Rendering Elemen di Halaman Detail Pelanggan

Pengamanan field sensitif dan tombol aksi di halaman detail pelanggan `/admin/customers/{id}`:

### 5.1 Tombol Aksi Berbasis Action Permission
*   **Tombol Edit Identitas:** Hanya dirender jika memiliki `customers.detail.identity.update`.
*   **Tombol Proses ke Tim:** Hanya dirender jika memiliki `customer.installation.validate`.
*   **Tombol Verifikasi Layanan:** Hanya dirender jika memiliki `customer.verification.activate`.

### 5.2 Sensor Field Sensitif
Field sensitif (Username/Password PPPoE, Password WiFi, VLAN, IP) yang dirender di tab data perangkat pelanggan dilindungi dengan sensor masking:
*   **Visual Default:** Password disensor menggunakan karakter bintang (`••••••••`).
*   **Tombol Reveal (Show/Hide Toggle Eye Icon):**
    *   Hanya tampil jika user memiliki permission `customers.detail.devices.view_sensitive`.
    *   Jika tombol diklik, password di-reveal di layar.
*   **Tombol Salin (Copy to Clipboard):**
    *   Hanya aktif jika user memiliki permission `view_sensitive`.
*   **Popup Form Edit Parameter Teknis:**
    *   Input edit hanya aktif (tidak read-only) jika user memiliki permission `customers.detail.devices.update_sensitive`.
