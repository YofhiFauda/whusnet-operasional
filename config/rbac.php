<?php

use App\Enums\ActionCode;

return [
    /*
    |--------------------------------------------------------------------------
    | Allowed Actions Per Feature
    |--------------------------------------------------------------------------
    |
    | Pemetaan ini digunakan oleh PermissionGeneratorService untuk membuat
    | permission secara dinamis berdasarkan kombinasi Feature dan Action
    | yang valid. Format permission nantinya adalah: {feature_code}.{action_code}
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Role Management Scope (RBAC Privilege Rules)
    |--------------------------------------------------------------------------
    |
    | Menentukan role mana yang berhak mengelola role lain.
    |
    | Format:
    |   'role_code' => ['dapat_kelola_role_code_1', 'dapat_kelola_role_code_2']
    |
    | Catatan penting:
    |   - Role 'owner' SELALU dapat mengelola semua role (tidak perlu didaftarkan).
    |   - Role 'owner' hanya dapat diedit/dihapus oleh user ber-role 'owner'.
    |   - Role yang tidak terdaftar di sini tidak memiliki wewenang mengelola role lain.
    |   - Aturan ini berlaku untuk: tambah, edit, hapus, dan atur permission (matrix).
    |
    */
    'role_management_scope' => [
        'admin' => ['teknisi', 'helpdesk'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Protected Roles (Tidak Dapat Dihapus Siapapun)
    |--------------------------------------------------------------------------
    |
    | Role dalam daftar ini tidak dapat dihapus dari sistem, bahkan oleh Owner
    | sekalipun. Ini adalah role inti sistem yang keberadaannya wajib.
    |
    */
    'protected_roles' => ['owner'],

    'allowed_actions' => [
        'dashboard' => [ActionCode::VIEW->value],

        'master_wilayah' => [
            ActionCode::VIEW->value,
            ActionCode::CREATE->value,
            ActionCode::UPDATE->value,
            ActionCode::DELETE->value,
        ],

        'master_distribusi' => [
            ActionCode::VIEW->value,
            ActionCode::CREATE->value,
            ActionCode::UPDATE->value,
            ActionCode::DELETE->value,
        ],

        'master_status_pelanggan' => [
            ActionCode::VIEW->value,
            ActionCode::CREATE->value,
            ActionCode::UPDATE->value,
            ActionCode::DELETE->value,
        ],

        'pops' => [
            ActionCode::VIEW->value,
            ActionCode::CREATE->value,
            ActionCode::UPDATE->value,
            ActionCode::DELETE->value,
        ],

        'users' => [
            ActionCode::VIEW->value,
            ActionCode::CREATE->value,
            ActionCode::UPDATE->value,
            ActionCode::DELETE->value,
        ],

        'roles' => [
            ActionCode::VIEW->value,
            ActionCode::CREATE->value,
            ActionCode::UPDATE->value,
            ActionCode::DELETE->value,
        ],

        'packages' => [
            ActionCode::VIEW->value,
            ActionCode::CREATE->value,
            ActionCode::UPDATE->value,
            ActionCode::DELETE->value,
        ],

        'customers' => [
            ActionCode::VIEW->value,
            ActionCode::CREATE->value,
            ActionCode::UPDATE->value,
            ActionCode::DELETE->value,
            // Terminasi langganan — sebelumnya numpang di customers.update
            // (CustomerTerminationController gak punya guard permission sama
            // sekali), jadi role mana pun yang bisa edit field pelanggan biasa
            // (Helpdesk/Sales) otomatis bisa putus langganan juga. Aksi
            // destruktif/service-impacting, wajib permission sendiri — pola
            // sama kayak customers.detail.devices.retrieve.
            ActionCode::DEACTIVATE->value,
        ],

        'customers.import' => [
            ActionCode::VIEW->value,
            ActionCode::IMPORT->value,
        ],

        'customers.detail' => [
            ActionCode::VIEW->value,
        ],

        // List Pelanggan Putus & List Pelanggan Gagal — sebelumnya numpang
        // customers.view yang sama persis dengan List Data Pelanggan biasa
        // (cuma beda query param status_group), jadi gak bisa di-toggle
        // independen lewat Role Matrix. Sekarang masing-masing halaman
        // (route + controller sendiri) punya permission sendiri.
        'customers.terminated' => [
            ActionCode::VIEW->value,
        ],

        'customers.failed' => [
            ActionCode::VIEW->value,
        ],

        'customers.detail.identity' => [
            ActionCode::VIEW->value,
            ActionCode::UPDATE->value,
        ],

        'customers.detail.address' => [
            ActionCode::VIEW->value,
            ActionCode::UPDATE->value,
        ],

        'customers.detail.packages' => [
            ActionCode::VIEW->value,
            ActionCode::UPDATE->value,
        ],

        'customers.detail.survey' => [
            ActionCode::VIEW->value,
            ActionCode::UPDATE->value,
            ActionCode::VALIDATE->value,
            ActionCode::REJECT->value,
        ],

        'customers.detail.installation' => [
            ActionCode::VIEW->value,
            ActionCode::UPDATE->value,
            ActionCode::VALIDATE->value,
            ActionCode::ACTIVATE->value,
            ActionCode::REJECT->value,
        ],

        'customers.detail.devices' => [
            ActionCode::VIEW->value,
            ActionCode::UPDATE->value,
            ActionCode::VIEW_SENSITIVE->value,
            ActionCode::UPDATE_SENSITIVE->value,
            ActionCode::RETRIEVE->value,
        ],

        'customers.detail.documents' => [
            ActionCode::VIEW->value,
            ActionCode::UPLOAD->value,
            ActionCode::DOWNLOAD->value,
            ActionCode::DELETE->value,
        ],

        'invoices' => [
            ActionCode::VIEW->value,
            ActionCode::CREATE->value,
            ActionCode::UPDATE->value,
            ActionCode::DELETE->value,
            ActionCode::PRINT->value,
        ],

        'payments' => [
            ActionCode::VIEW->value,
            ActionCode::CREATE->value,
            ActionCode::UPDATE->value,
            ActionCode::DELETE->value,
            ActionCode::VALIDATE->value,
            ActionCode::APPROVE->value,
            ActionCode::REJECT->value,
        ],

        'reports' => [
            ActionCode::VIEW->value,
            ActionCode::EXPORT->value,
            ActionCode::PRINT->value,
        ],

        'audit_logs' => [
            ActionCode::VIEW->value,
            ActionCode::EXPORT->value,
        ],

        'fop_tasks' => [
            ActionCode::VIEW->value,
            ActionCode::CREATE->value,
            ActionCode::UPDATE->value,
            ActionCode::DELETE->value,
            ActionCode::UPDATE_SENSITIVE->value,
            ActionCode::CANCEL->value,
        ],

        'sla_timeline' => [
            ActionCode::VIEW->value,
            ActionCode::UPDATE->value,
        ],

        'tickets' => [
            ActionCode::VIEW->value,
            ActionCode::CREATE->value,
            // Close/Escalate/Return (Ticket::close()/escalateToNoc()/escalateToFop()/
            // returnToHelpdesk()) — satu permission generik buat aksi-aksi itu,
            // otorisasi per-handler (siapa lagi pegang tiket) dicek di
            // TicketService, bukan di sini.
            ActionCode::UPDATE->value,
            // Batalkan tiket pra-FOP (Ticket::cancel()) — permission terpisah
            // dari UPDATE biar bisa diatur independen lewat matrix role (mis.
            // NOC boleh close/escalate tapi gak boleh batalkan).
            ActionCode::CANCEL->value,
        ],

        // Arsip Ticket Selesai & Dibatalkan — dulu numpang `tickets.view` lewat
        // route bucket generik `/tickets/{bucket}`, jadi gak bisa di-toggle
        // per-halaman di Role Matrix. Sekarang masing-masing halaman (route +
        // controller sendiri) punya permission sendiri, pola sama persis
        // customers.terminated/customers.failed di atas.
        'tickets.selesai' => [
            ActionCode::VIEW->value,
        ],

        'tickets.dibatalkan' => [
            ActionCode::VIEW->value,
        ],

        // History Ticketing — halaman arsip SEMUA tiket (semua handler & status,
        // termasuk yang masih jalan), pengganti sheet Excel Helpdesk lama.
        // Permission sendiri, BUKAN numpang tickets.view: isinya lintas-bucket
        // dan bisa diekspor, jadi harus bisa dimatikan per-role tanpa mencabut
        // akses tiket sehari-hari (docs/plan/analisa-halaman-history-ticketing.md §6).
        'tickets.history' => [
            ActionCode::VIEW->value,
            ActionCode::EXPORT->value,
        ],

        // Worksheet NOC & Dashboard NOC — feature terpisah dari 'tickets'
        // (bukan cuma aksi atas tiket, tapi AKSES HALAMAN KERJA) biar RBAC-nya
        // bisa diatur independen. Worksheet NOC sekarang SATU halaman tanpa
        // tab (ADHOC-06), jadi `noc_worksheet.view` yang jadi gerbangnya.
        'noc_worksheet' => [
            ActionCode::VIEW->value,
        ],

        // Dua permission tab lama DIPENSIUNKAN (ADHOC-06) — sengaja tetap
        // digenerate biar role yang terlanjur punya gak error waktu resolusi
        // permission, tapi sudah tidak menggerbangi route mana pun.
        'noc_worksheet.masuk' => [
            ActionCode::VIEW->value,
        ],

        'noc_worksheet.diproses' => [
            ActionCode::VIEW->value,
        ],

        'noc_dashboard' => [
            ActionCode::VIEW->value,
        ],

        // DELETE digenerate tapi TIDAK dipasang ke route CRUD — kategori
        // di-toggle is_active, bukan dihapus keras (pola sama dengan packages),
        // biar kategori yang sudah dipakai tiket lama gak kehilangan jejak.
        'ticket_issue_categories' => [
            ActionCode::VIEW->value,
            ActionCode::CREATE->value,
            ActionCode::UPDATE->value,
            ActionCode::DELETE->value,
        ],

        // Master Barang/Material, Kategori Barang, dan Alat Kerja.
        //
        // PermissionGeneratorService melakukan loop atas daftar INI, bukan atas
        // tabel `features` — feature yang punya seeder tapi tidak terdaftar di
        // sini permission-nya tidak pernah lahir, tanpa error apa pun. `items`
        // sempat begitu (ADHOC-11): halamannya cuma bisa diakses Owner lewat
        // wildcard `*` dan tidak bisa diberikan ke role lain lewat Role Matrix.
        // Tiap FeatureSeeder baru WAJIB menambah entri di sini juga.
        //
        // DELETE digenerate tapi TIDAK dipasang ke route CRUD — ketiganya
        // di-toggle is_active, bukan dihapus keras, biar baris yang sudah
        // dipakai laporan lama tidak kehilangan rujukan.
        'items' => [
            ActionCode::VIEW->value,
            ActionCode::CREATE->value,
            ActionCode::UPDATE->value,
            ActionCode::DELETE->value,
        ],

        'item_categories' => [
            ActionCode::VIEW->value,
            ActionCode::CREATE->value,
            ActionCode::UPDATE->value,
            ActionCode::DELETE->value,
        ],

        'work_tools' => [
            ActionCode::VIEW->value,
            ActionCode::CREATE->value,
            ActionCode::UPDATE->value,
            ActionCode::DELETE->value,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | View Permission Overrides (Dependency Chaining)
    |--------------------------------------------------------------------------
    |
    | RoleManagementService::syncPermissions() dependency chaining (S6) butuh
    | tahu kode permission "view" milik tiap Feature buat auto-grant induk
    | saat anak dicentang. Default konvensinya "{feature_code}.view".
    |
    | Sebagian Feature (mis. tasks.fop / tasks.teknisi dari TaskFeatureSeeder)
    | gak ikut konvensi ini karena kode permission view-nya beda nama
    | (task.view.all / task.view.own). Daftarkan pengecualiannya di sini
    | biar RoleManagementService gak perlu hardcode per-fitur di kode PHP.
    |
    | Format: 'feature_code' => 'kode_permission_view'
    |
    */
    'view_permission_overrides' => [
        'tasks.fop' => 'task.view.all',
        'tasks.teknisi' => 'task.view.own',
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Name Overrides (Label Kontekstual)
    |--------------------------------------------------------------------------
    |
    | Sebagian permission yang digenerate PermissionGeneratorService dari
    | allowed_actions di atas butuh label tampilan yang beda dari nama Action
    | generik (mis. Action `update_sensitive` namanya "Update Timer SLA" —
    | itu gak relevan buat fitur di luar SLA timeline). Daftarkan override
    | label per kode permission di sini; PermissionGeneratorService bakal
    | pasang label ini setiap kali permission itu dibuat ATAU ditemukan masih
    | null — jadi gak bergantung ke migration one-off yang timingnya gak
    | reliable (lihat docs/post-mvp/rbac/migrasi-mapping-permission.md bagian 9).
    |
    | Format: 'kode_permission' => 'Label tampilan'
    |
    */
    'permission_name_overrides' => [
        'fop_tasks.update_sensitive' => 'Ubah Kategori & Prioritas Tiket',
        'customers.detail.devices.update_sensitive' => 'Ubah Data Sensitif Perangkat',
        'customers.detail.devices.view_sensitive' => 'Lihat Data Sensitif Perangkat',
        'customers.detail.devices.retrieve' => 'Ambil Alat Pelanggan Putus Langganan',
        'customers.detail.view' => 'Lihat Detail Pelanggan',
        'customers.terminated.view' => 'Lihat List Pelanggan Putus',
        'customers.failed.view' => 'Lihat List Pelanggan Gagal',

        // Modul Ticketing — tiap halaman punya permission sendiri, jadi
        // labelnya harus nyebut NAMA HALAMAN-nya biar di Role Matrix kelihatan
        // jelas mana yang lagi di-toggle (bukan cuma "Lihat"/"Buat" generik).
        'tickets.create' => 'Buka Halaman New Ticket (Worksheet)',
        'tickets.view' => 'Lihat Ticket & Detailnya',
        'tickets.update' => 'Aksi Ticket (Selesai/Assign NOC/Assign FOP/Oncheck/Kembalikan)',
        'tickets.cancel' => 'Batalkan Ticket (pra-FOP)',
        'tickets.selesai.view' => 'Lihat Halaman Ticket Selesai',
        'tickets.dibatalkan.view' => 'Lihat Halaman Ticket Dibatalkan',
        'tickets.history.view' => 'Lihat Halaman History Ticketing (semua tiket)',
        'tickets.history.export' => 'Ekspor History Ticketing ke Excel',
        'noc_worksheet.view' => 'Akses Modul Worksheet NOC',
        'noc_worksheet.masuk.view' => '[Nonaktif] Tab Ticket Masuk — dilebur ke Worksheet NOC',
        'noc_worksheet.diproses.view' => '[Nonaktif] Tab Ticket Diproses — dilebur ke Worksheet NOC',
        'noc_dashboard.view' => 'Lihat Halaman Dashboard NOC',
    ],
];
