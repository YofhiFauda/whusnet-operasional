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
            ActionCode::DELETE->value
        ],

        'master_distribusi' => [
            ActionCode::VIEW->value, 
            ActionCode::CREATE->value, 
            ActionCode::UPDATE->value, 
            ActionCode::DELETE->value
        ],

        'master_status_pelanggan' => [
            ActionCode::VIEW->value, 
            ActionCode::CREATE->value, 
            ActionCode::UPDATE->value, 
            ActionCode::DELETE->value
        ],

        'pops' => [
            ActionCode::VIEW->value, 
            ActionCode::CREATE->value, 
            ActionCode::UPDATE->value, 
            ActionCode::DELETE->value
        ],
        
        'users' => [
            ActionCode::VIEW->value, 
            ActionCode::CREATE->value, 
            ActionCode::UPDATE->value, 
            ActionCode::DELETE->value
        ],
        
        'roles' => [
            ActionCode::VIEW->value, 
            ActionCode::CREATE->value, 
            ActionCode::UPDATE->value, 
            ActionCode::DELETE->value
        ],
        
        'packages' => [
            ActionCode::VIEW->value, 
            ActionCode::CREATE->value, 
            ActionCode::UPDATE->value, 
            ActionCode::DELETE->value
        ],
        
        'customers' => [
            ActionCode::VIEW->value, 
            ActionCode::CREATE->value, 
            ActionCode::UPDATE->value, 
            ActionCode::DELETE->value
        ],
        
        'customers.import' => [
            ActionCode::VIEW->value, 
            ActionCode::IMPORT->value
        ],
        
        'customers.detail' => [
            ActionCode::VIEW->value
        ],
        
        'customers.detail.identity' => [
            ActionCode::VIEW->value, 
            ActionCode::UPDATE->value
        ],
        
        'customers.detail.address' => [
            ActionCode::VIEW->value, 
            ActionCode::UPDATE->value
        ],
        
        'customers.detail.packages' => [
            ActionCode::VIEW->value, 
            ActionCode::UPDATE->value
        ],
        
        'customers.detail.survey' => [
            ActionCode::VIEW->value,
            ActionCode::UPDATE->value,
            ActionCode::VALIDATE->value,
            ActionCode::REJECT->value
        ],
        
        'customers.detail.installation' => [
            ActionCode::VIEW->value, 
            ActionCode::UPDATE->value, 
            ActionCode::VALIDATE->value, 
            ActionCode::ACTIVATE->value
        ],
        
        'customers.detail.devices' => [
            ActionCode::VIEW->value, 
            ActionCode::UPDATE->value, 
            ActionCode::VIEW_SENSITIVE->value, 
            ActionCode::UPDATE_SENSITIVE->value
        ],
        
        'customers.detail.documents' => [
            ActionCode::VIEW->value, 
            ActionCode::UPLOAD->value, 
            ActionCode::DOWNLOAD->value, 
            ActionCode::DELETE->value
        ],
        
        'invoices' => [
            ActionCode::VIEW->value, 
            ActionCode::CREATE->value, 
            ActionCode::UPDATE->value, 
            ActionCode::DELETE->value, 
            ActionCode::PRINT->value
        ],
        
        'payments' => [
            ActionCode::VIEW->value, 
            ActionCode::CREATE->value, 
            ActionCode::UPDATE->value, 
            ActionCode::DELETE->value, 
            ActionCode::VALIDATE->value, 
            ActionCode::APPROVE->value, 
            ActionCode::REJECT->value
        ],
        
        'reports' => [
            ActionCode::VIEW->value, 
            ActionCode::EXPORT->value, 
            ActionCode::PRINT->value
        ],
        
        'audit_logs' => [
            ActionCode::VIEW->value, 
            ActionCode::EXPORT->value
        ],

        'fop_tasks' => [
            ActionCode::VIEW->value,
            ActionCode::CREATE->value,
            ActionCode::UPDATE->value,
            ActionCode::DELETE->value,
            ActionCode::UPDATE_SENSITIVE->value
        ],

        'sla_timeline' => [
            ActionCode::VIEW->value,
            ActionCode::UPDATE->value,
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
    ],
];
