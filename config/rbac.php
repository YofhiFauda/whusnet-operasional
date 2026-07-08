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
];
