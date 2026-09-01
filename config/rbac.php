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

        // Skip Survey saat Registrasi — Sales input data survey langsung,
        // pelanggan lompat ke antrean ACC Admin. Sempit & terpisah dari
        // customers.create biasa (lihat ActionCode::SKIP_SURVEY).
        'customers.registration' => [
            ActionCode::SKIP_SURVEY->value,
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

        // QR pelanggan (docs/plan/qr-code/rancangan-qr-pelanggan-final.md
        // §5, §10 Fase 1). `cancel` = "cabut token", bukan `manage` — bukan
        // ActionCode yang valid di repo ini.
        'customers.qr' => [
            ActionCode::VIEW->value,
            ActionCode::CREATE->value,
            ActionCode::CANCEL->value,
            ActionCode::PRINT->value,
        ],

        // Absen teknisi via QR (Fase 3) — diseed sekarang biar matrix role
        // sudah siap dipakai begitu Fase 3 mulai, TANPA route/enforcement
        // aktif apa pun di Fase 1 ini. `attendance` bukan action code yang
        // valid — pakai `create` (§5).
        'tasks.qr_attendance' => [
            ActionCode::CREATE->value,
        ],

        // Dashboard anomali scan QR (Fase 2/3) — permission diseed sekarang,
        // halamannya sendiri belum dibangun di Fase 1.
        'qr_scan_logs' => [
            ActionCode::VIEW->value,
        ],

        // Scan QR Internal (staf) — 2026-08-27, resources/js/qr-scan.js.
        // Cuma `.view` (buka halaman + pakai kamera) — bukan aksi CRUD.
        'qr_scan' => [
            ActionCode::VIEW->value,
        ],

        // Create tiket via QR → Portal (2026-08-29, docs/plan/qr-code/
        // analisa-unifikasi-qr-staff-portal.md §1.4). TERPISAH dari
        // `tickets.create` dashboard — channel Portal pakai token one-shot,
        // risikonya beda. Role ber-`tickets.*` (helpdesk/noc/fop) sudah
        // lolos otomatis lewat feature wildcard, tidak perlu baris tambahan.
        'tickets.qr' => [
            ActionCode::CREATE->value,
        ],

        // Catat pembayaran via QR → Portal (2026-08-29). TERPISAH dari
        // `kolektor.pay` dashboard, sama alasan seperti `tickets.qr` di
        // atas. Role `kolektor` TIDAK punya wildcard `kolektor.*` (sengaja),
        // jadi permission ini wajib ditambah eksplisit di RolePermissionSeeder.
        'kolektor.qr' => [
            ActionCode::PAY->value,
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

        // Worklist Kolektor — halaman kerja kolektor sendiri.
        //
        // VIEW: baca pelanggan yang ter-assign ke dirinya (bukan
        // `customers.view` penuh).
        // PAY: mencatat pembayaran dari worklist-nya sendiri. Kolektor TETAP
        // TIDAK diberi `payments.create` — itu kewenangan bayar invoice mana
        // pun; `kolektor.pay` cuma invoice pelanggan miliknya, lewat rute yang
        // memaksa `collector_id = auth()->id()`.
        //
        // Merevisi §B-8 no. 4 dokumen lama ("kolektor tak boleh input
        // pembayaran") — lihat docs/plan/kolektor/analisa-alur-kolektor-2.0.md §8.
        'kolektor' => [
            ActionCode::VIEW->value,
            ActionCode::PAY->value,
            // Menyetorkan hasil tagihan ke admin (Fase 2). Terpisah dari PAY
            // supaya hak memegang kas bisa dicabut tanpa mencabut hak menagih.
            ActionCode::DEPOSIT->value,
            // Mencatat kunjungan tanpa hasil (Fase 3). Diberikan bersama VIEW —
            // mencabutnya berarti mematikan satu-satunya kontrol anti-fraud
            // modul ini, jadi jangan dilepas tanpa alasan kuat.
            ActionCode::VISIT->value,
        ],

        // Worksheet Admin — halaman admin untuk mengelola kolektor: daftar
        // kolektor, assign/lepas pelanggan, dan (Fase 2) cross check setoran.
        // Feature SENDIRI, bukan numpang `customers.update`/`payments.create`:
        // halaman ini punya audiens & kewenangan yang beda dari dua-duanya,
        // dan harus bisa dimatikan per-role tanpa mencabut hak edit pelanggan
        // atau hak bayar di halaman Tagihan.
        //
        // docs/plan/kolektor/analisa-alur-kolektor-2.0.md §9, §14.1.
        'collector_worksheet' => [
            ActionCode::VIEW->value,
            ActionCode::ASSIGN->value,
            // Cross check & tutup setoran. Pakai VALIDATE yang sudah ada —
            // konsisten dengan `payments.validate`; jangan bikin action
            // `verify` baru yang artinya sama persis.
            ActionCode::VALIDATE->value,
            // Hapus buku selisih = titik kerugian diakui. Permission SENDIRI,
            // sengaja TIDAK diberikan ke `admin` di RolePermissionSeeder —
            // admin yang memverifikasi tak boleh sekaligus menutup kerugian
            // yang dia temukan sendiri. Owner lolos lewat wildcard `*`.
            ActionCode::APPROVE->value,
            // Kwitansi (Fase 4). Cetak & upload dipisah dari VALIDATE karena
            // ini sumbu DOKUMEN, bukan sumbu kas: staf yang mengurus arsip
            // kwitansi tak otomatis berwenang menutup setoran, dan sebaliknya.
            ActionCode::PRINT->value,
            ActionCode::UPLOAD->value,
        ],

        // Setoran Kas Admin — uang kolektor yang sudah diverifikasi + tunai
        // yang diterima di loket, diteruskan ke owner/bank.
        // docs/plan/kolektor/analisa-setoran-kas-admin.md §4.5.
        'cash_deposit' => [
            ActionCode::VIEW->value,
            // Menyetorkan saldo kas sendiri. Terpisah dari VIEW supaya hak
            // memegang kas bisa dicabut tanpa mencabut hak membaca posisinya.
            ActionCode::CREATE->value,
            // Memeriksa & menutup setoran kas orang lain — Owner & atasan.
            // Pakai VALIDATE yang sudah ada, konsisten dengan
            // `collector_worksheet.validate`.
            ActionCode::VALIDATE->value,
            // Menutup selisih kas (kerugian ATAU kelebihan diakui). Permission
            // sendiri, sengaja TIDAK diberikan bersama VALIDATE — pemeriksa
            // tak boleh sekaligus menutup selisih yang dia temukan sendiri.
            ActionCode::APPROVE->value,
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
    | Fitur yang DIKECUALIKAN dari auto-grant `view`
    |--------------------------------------------------------------------------
    |
    | RoleManagementService::syncPermissions() otomatis ikut mencentang
    | `{feature}.view` setiap kali ada permission anak yang dicentang —
    | masuk akal untuk hampir semua fitur: percuma memberi hak "ubah" tanpa
    | hak "lihat" halamannya.
    |
    | Tidak berlaku di sini. Pada `cash_deposit`, `view` BUKAN "halaman yang
    | sama tapi baca saja": ia pandangan PEMERIKSA — posisi kas admin mana pun
    | dalam scope, antrean pemeriksaan, dan rincian sumber sampai nama
    | pelanggan. Admin cukup `create` (menyetor + riwayat sendiri di Worksheet
    | Admin). Tanpa pengecualian ini, mencentang "Setor" diam-diam memberi
    | admin pandangan yang justru sengaja dipisahkan darinya.
    |
    | docs/plan/kolektor/analisa-setoran-kas-admin.md §10.
    */
    'view_autogrant_exempt' => [
        'cash_deposit',
    ],

    /*
    |--------------------------------------------------------------------------
    | Batas Rantai Auto-Grant `view` — Channel QR/Portal Independen
    |--------------------------------------------------------------------------
    |
    | Sub-fitur `*.qr` (`customers.qr`, `tickets.qr`, `kolektor.qr`) SECARA
    | SENGAJA cuma menumpang struktur tree Fitur biar rapi di menu Role
    | Matrix — bukan tab navigasi dashboard Operasional. Aksesnya datang
    | dari alur scan QR / Portal Pelanggan (lihat QrScanController,
    | StaffPortalTokenService), 100% terpisah dari halaman `tickets.view`
    | atau `kolektor.view` di Operasional.
    |
    | Tanpa daftar ini, RoleManagementService::syncPermissions() tetap NAIK
    | ke fitur induk (`tickets`, `kolektor`, `customers`) dan diam-diam ikut
    | mencentang `.view` DASHBOARD OPERASIONAL induknya begitu permission
    | `.qr` dicentang — mencampur dua channel akses yang harus independen.
    | Contoh nyata: role Kolektor SEHARUSNYA bisa dapat `tickets.qr.create`
    | (buat bikin tiket lewat QR) tanpa terpaksa ikut dapat `tickets.view`
    | (akses penuh dashboard Ticketing Operasional).
    |
    | Beda dengan `view_autogrant_exempt` di atas — itu cuma melewati fitur
    | ITU SENDIRI lalu tetap naik ke induknya. Daftar ini menghentikan
    | rantai TOTAL begitu ketemu kode fiturnya: tidak menambah `.view`
    | fitur ini MAUPUN fitur induk mana pun di atasnya.
    */
    'view_autogrant_chain_boundary' => [
        'customers.qr',
        'tickets.qr',
        'kolektor.qr',
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
        'customers.registration.skip_survey' => 'Skip Survey saat Registrasi (Input Data Survey Langsung)',

        // QR pelanggan (docs/plan/qr-code/rancangan-qr-pelanggan-final.md)
        'customers.qr.view' => 'Lihat Status Token QR Pelanggan',
        'customers.qr.create' => 'Terbitkan Token QR Pelanggan',
        'customers.qr.cancel' => 'Cabut Token QR Pelanggan',
        'customers.qr.print' => 'Cetak Stiker QR Pelanggan',
        'tasks.qr_attendance.create' => 'Absen Task via Scan QR (Fase 3)',
        'qr_scan_logs.view' => 'Lihat Dashboard Anomali Scan QR',
        // Scan QR internal (2026-08-27) — kamera DI DALAM app ini, bukan
        // app scanner luar (lihat resources/js/qr-scan.js kenapa).
        'qr_scan.view' => 'Buka Halaman Scan QR Internal (Staf)',

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

        // Modul Kolektor — dua halaman, dua audiens (analisa-alur-kolektor-2.0
        // §9). Labelnya nyebut halamannya biar di Role Matrix kelihatan mana
        // yang lagi di-toggle: halaman admin atau halaman kolektor.
        'kolektor.view' => 'Lihat Worklist Kolektor (pelanggan sendiri)',
        'kolektor.pay' => 'Catat Pembayaran dari Worklist Sendiri',
        'kolektor.deposit' => 'Setor Hasil Tagihan ke Admin',
        'kolektor.visit' => 'Catat Kunjungan Tanpa Hasil (tidak ada orang/menolak/janji)',
        'collector_worksheet.view' => 'Akses Halaman Worksheet Admin (Kolektor)',
        'collector_worksheet.assign' => 'Assign / Lepas Pelanggan ke Kolektor',
        'collector_worksheet.validate' => 'Cross Check & Verifikasi Setoran Kolektor',
        'collector_worksheet.approve' => 'Hapus Buku Selisih Setoran (kerugian diakui)',
        'collector_worksheet.print' => 'Cetak Kwitansi Pembayaran (ber-QR)',
        'collector_worksheet.upload' => 'Upload & Cocokkan Kwitansi',
        'cash_deposit.view' => 'Akses Halaman Setoran Kas (Admin)',
        'cash_deposit.create' => 'Menyetorkan Kas ke Owner / Bank',
        'cash_deposit.validate' => 'Periksa & Tutup Setoran Kas Admin',
        'cash_deposit.approve' => 'Tutup Selisih Setoran Kas (kerugian/kelebihan diakui)',
    ],
];
