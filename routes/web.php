<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CashDepositController;
use App\Http\Controllers\CollectorDepositController;
use App\Http\Controllers\CollectorPaymentController;
use App\Http\Controllers\CollectorVisitController;
use App\Http\Controllers\CollectorWorklistController;
use App\Http\Controllers\CollectorWorksheetController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerDeviceController;
use App\Http\Controllers\CustomerDocumentController;
use App\Http\Controllers\CustomerFailedController;
use App\Http\Controllers\CustomerFieldworkController;
use App\Http\Controllers\CustomerInstallationController;
use App\Http\Controllers\CustomerNetworkAssignmentController;
use App\Http\Controllers\CustomerQrController;
use App\Http\Controllers\CustomerReportController;
use App\Http\Controllers\CustomerSurveyController;
use App\Http\Controllers\CustomerTerminatedController;
use App\Http\Controllers\CustomerTerminationController;
use App\Http\Controllers\CustomerTestReportController;
use App\Http\Controllers\CustomerVerificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FopDashboardController;
use App\Http\Controllers\FopTaskController;
use App\Http\Controllers\ImportReportController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceReportController;
use App\Http\Controllers\Master\DistributionController;
use App\Http\Controllers\Master\InternetPackageController;
use App\Http\Controllers\Master\ItemCategoryController;
use App\Http\Controllers\Master\ItemController;
use App\Http\Controllers\Master\PopController;
use App\Http\Controllers\Master\RegionController;
use App\Http\Controllers\Master\SlaTimelineController;
use App\Http\Controllers\Master\SubscriptionStatusController;
use App\Http\Controllers\Master\TicketIssueCategoryController;
use App\Http\Controllers\Master\WorkToolController;
use App\Http\Controllers\NocDashboardController;
use App\Http\Controllers\NocWorksheetController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentBatchController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\PaymentReportController;
use App\Http\Controllers\QrBillingController;
use App\Http\Controllers\QrScanController;
use App\Http\Controllers\QrTicketController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskMaintenanceController;
use App\Http\Controllers\TaskStatusController;
use App\Http\Controllers\TaskTeamController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketDibatalkanController;
use App\Http\Controllers\TicketHistoryController;
use App\Http\Controllers\TicketSelesaiController;
use App\Http\Controllers\UserController;
use App\Models\City;
use App\Models\District;
use App\Models\Pop;
use App\Models\Village;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Guest Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// QR Pelanggan — dispatcher publik (docs/plan/qr-code/rancangan-qr-
// pelanggan-final.md §5, Fase 1). SENGAJA di luar middleware `guest`
// MAUPUN `auth` — satu URL melayani tamu (belum login) dan staf (login)
// sekaligus, dibedakan lewat auth()->check() di dalam QrScanController.
// Regex format base32 penuh (BUKAN charset ULID) menyaring sampah SEBELUM
// satu query DB pun jalan.
Route::middleware('throttle:qr-public')->group(function () {
    Route::get('/q1/{code}', [QrScanController::class, 'dispatch'])
        ->where('code', '[A-Z2-7]{26}\.[A-Z2-7]{10}')
        ->name('qr.dispatch');

    // Fungsi A — halaman tagihan publik (§6.1, Fase 2). Bisa diakses
    // langsung (bookmark) TANPA lewat qr.dispatch dulu — QrBillingController
    // meresolusi $code mandiri, bukan asumsi sudah tervalidasi.
    Route::get('/q1/{code}/tagihan', [QrBillingController::class, 'show'])
        ->where('code', '[A-Z2-7]{26}\.[A-Z2-7]{10}')
        ->name('qr.billing');
});

// Limiter TERPISAH & lebih ketat dari qr-public baseline — endpoint ini
// menerima kredensial (PIN/4-digit HP), bukan cuma baca (§10 Fase 2).
Route::middleware('throttle:qr-billing-verify')->group(function () {
    Route::post('/q1/{code}/tagihan/verifikasi', [QrBillingController::class, 'verify'])
        ->where('code', '[A-Z2-7]{26}\.[A-Z2-7]{10}')
        ->name('qr.billing.verify');

    // Wajib ganti PIN cetak saat login pertama (§6.5.5b) — dua langkah
    // (GET form + POST submit), keduanya di belakang limiter kredensial
    // yang sama, bukan qr-public.
    Route::get('/q1/{code}/tagihan/ganti-pin', [QrBillingController::class, 'changePinForm'])
        ->where('code', '[A-Z2-7]{26}\.[A-Z2-7]{10}')
        ->name('qr.billing.pin.change-form');
    Route::post('/q1/{code}/tagihan/ganti-pin', [QrBillingController::class, 'changePinSubmit'])
        ->where('code', '[A-Z2-7]{26}\.[A-Z2-7]{10}')
        ->name('qr.billing.pin.change-submit');
});

// Hop KEDUA Fungsi B — di belakang middleware `auth` ASLI (bukan cuma
// pengecekan di controller), defense-in-depth kalau cabang pertama di
// QrScanController somehow ke-bypass (§6.2).
Route::middleware('auth')->group(function () {
    Route::get('/q1/{code}/tiket', [QrTicketController::class, 'create'])
        ->where('code', '[A-Z2-7]{26}\.[A-Z2-7]{10}')
        ->name('qr.ticket.create');
});

// Authenticated Admin Routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.markAllRead');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.markRead');
    Route::post('/notifications/{id}/unread', [NotificationController::class, 'markAsUnread'])->name('notifications.markUnread');

    // Role & Permission Management
    Route::middleware('permission:roles.view|roles.update')->group(function () {
        Route::get('/roles', [RolePermissionController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RolePermissionController::class, 'store'])->name('roles.store')->middleware('permission:roles.create');
        Route::put('/roles/{role}', [RolePermissionController::class, 'updateRole'])->name('roles.update_role')->middleware('permission:roles.update');
        Route::delete('/roles/{role}', [RolePermissionController::class, 'destroy'])->name('roles.destroy')->middleware('permission:roles.delete');
        Route::get('/roles/{role}/matrix', [RolePermissionController::class, 'matrix'])->name('roles.matrix');
        Route::put('/roles/{role}/matrix', [RolePermissionController::class, 'update'])->name('roles.update');
    });

    // User Management
    Route::middleware('permission:users.view')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
    });

    Route::middleware('permission:users.create|users.update')->group(function () {
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/preview-access', [UserController::class, 'previewAccess'])->name('users.preview-access');
    });

    // Customers Management - Static Routes First
    Route::middleware('permission:customers.view')->group(function () {
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    });

    // List Pelanggan Putus & List Pelanggan Gagal — permission SENDIRI,
    // terpisah dari customers.view (List Data Pelanggan biasa). Sebelumnya
    // dua-duanya numpang customers.view lewat query param status_group di
    // customers.index — gak bisa di-toggle independen lewat Role Matrix
    // (mis. cabut akses teknisi ke List tapi Putus/Gagal ikut ke-cabut juga
    // meski gak diminta, atau sebaliknya).
    Route::middleware('permission:customers.terminated.view')->group(function () {
        Route::get('/customers/terminated', [CustomerTerminatedController::class, 'index'])->name('customers.terminated');
    });

    Route::middleware('permission:customers.failed.view')->group(function () {
        Route::get('/customers/failed', [CustomerFailedController::class, 'index'])->name('customers.failed');
    });

    Route::middleware('permission:customers.create')->group(function () {
        Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    });

    Route::middleware('permission:customers.import')->group(function () {
        Route::get('/customers/import', [CustomerController::class, 'importForm'])->name('customers.import');
        Route::get('/customers/import/history', [CustomerController::class, 'importHistory'])->name('customers.import.history');
        Route::get('/customers/import/history/{batch}', [CustomerController::class, 'importBatchDetail'])->name('customers.import.batch-detail');
        Route::get('/customers/import/template', [CustomerController::class, 'downloadImportTemplate'])->name('customers.import.template');
        Route::post('/customers/import/validate', [CustomerController::class, 'validateImport'])->name('customers.import.validate');
        Route::post('/customers/import/confirm', [CustomerController::class, 'confirmImport'])->name('customers.import.confirm');
    });

    // Customers Management - Dynamic Routes Last
    Route::middleware('permission:customers.update')->group(function () {
        Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
    });

    // Terminasi langganan — permission SENDIRI (customers.deactivate), BUKAN
    // numpang customers.update lagi. Sebelumnya Helpdesk/Sales (yang cuma
    // butuh edit field pelanggan biasa) ikut kebawa bisa putus langganan.
    Route::middleware('permission:customers.deactivate')->group(function () {
        Route::post('/customers/{customer}/terminate', [CustomerTerminationController::class, '__invoke'])->name('customers.terminate');
    });

    Route::middleware('permission:customers.detail.devices.retrieve')->group(function () {
        Route::post('/customers/{customer}/retrieve-device', [CustomerController::class, 'retrieveDevice'])->name('customers.retrieve-device');
    });

    Route::middleware('permission:customers.detail.installation.activate')->group(function () {
        Route::post('/customers/{customer}/activate', [CustomerController::class, 'activate'])->name('customers.activate');
    });

    // QR Pelanggan — halaman staf lihat/cetak/terbitkan/cabut token
    // (docs/plan/qr-code/rancangan-qr-pelanggan-final.md §5, §10 Fase 1).
    // Empat permission TERPISAH (bukan numpang customers.update) — cabut
    // lebih destruktif dari sekadar lihat, jadi harus bisa di-toggle
    // independen lewat Role Matrix.
    Route::middleware('permission:customers.qr.view')->group(function () {
        Route::get('/customers/{customer}/qr', [CustomerQrController::class, 'show'])->name('customers.qr.show');
        // JSON ringkas — dipakai modal ringkas di tab Pemasangan
        // (_installation.blade.php), BUKAN pengganti halaman penuh
        // customers.qr.show. Nol PIN di payload ini (modal ringkas gak
        // punya UI Reset PIN sama sekali — itu tetap cuma di halaman penuh).
        Route::get('/customers/{customer}/qr/status', [CustomerQrController::class, 'status'])->name('customers.qr.status');
    });
    Route::middleware('permission:customers.qr.print')->group(function () {
        Route::get('/customers/{customer}/qr/cetak', [CustomerQrController::class, 'print'])->name('customers.qr.print');
    });
    Route::middleware('permission:customers.qr.create')->group(function () {
        // Terbitkan QR + PIN BARENG (koreksi 2026-08-26, §6.5.3) — aman
        // diklik berulang: token idempoten, PIN CUMA terbit kalau belum
        // pernah ada. Lihat CustomerQrController::issue() docblock.
        Route::post('/customers/{customer}/qr/terbitkan', [CustomerQrController::class, 'issue'])->name('customers.qr.issue');
    });
    Route::middleware('permission:customers.qr.cancel')->group(function () {
        Route::post('/customers/{customer}/qr/cabut', [CustomerQrController::class, 'revoke'])->name('customers.qr.revoke');
        // Reset PIN numpang permission `.cancel` (bukan `.create`) — ini
        // aksi DESTRUKTIF ke PIN yang sudah dipegang pelanggan, wajib
        // seketat mencabut token, bukan seringan menerbitkan yang baru.
        // Gerbangnya sisi KLIEN (modal pratinjau, koreksi 2026-08-26 —
        // bukan lagi field `verification_note` wajib) — lihat CustomerQrController.
        Route::post('/customers/{customer}/qr/pin/reset', [CustomerQrController::class, 'reissuePin'])->name('customers.qr.pin.reissue');
        // "Lupa Password" — pulihkan akun portal `active` (PortalAuthService::
        // resetToPendingClaim() docblock kenapa ini TERPISAH dari reissuePin
        // di atas, bukan digabung). Numpang permission `.cancel` yang sama —
        // efeknya lebih berat (matiin password + cabut semua sesi portal).
        Route::post('/customers/{customer}/qr/portal-akun/reset', [CustomerQrController::class, 'resetPortalAccount'])->name('customers.qr.portal-account.reset');
    });

    // Perangkat & Pemasangan — halaman TERPISAH dari Detail Pelanggan
    // (customers.show, digerbangin customers.detail.view). Teknisi sengaja
    // DIBLOK dari customers.detail.view (gak boleh buka Detail Pelanggan
    // umum: identitas/alamat/paket/billing/dokumen), TAPI tetap genuinely
    // butuh liat/isi data Perangkat & Pemasangan buat kerja lapangan — makanya
    // dipecah jadi route sendiri, digerbangin permission tab yang memang udah
    // dipunyai teknisi (customers.detail.devices.view /
    // customers.detail.installation.view), bukan numpang customers.detail.view.
    Route::middleware('permission:customers.detail.devices.view|customers.detail.installation.view')->group(function () {
        Route::get('/customers/{customer}/perangkat-pemasangan', [CustomerFieldworkController::class, 'show'])->name('customers.fieldwork');
    });

    Route::middleware('permission:invoices.create')->group(function () {
        Route::post('/customers/{customer}/invoices/manual', [CustomerController::class, 'storeManualInvoice'])->name('customers.invoices.manual');
    });

    Route::middleware('permission:invoices.view')->group(function () {
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/lunas', [InvoiceController::class, 'lunas'])->name('invoices.lunas');
        Route::get('/invoices/belum-lunas', [InvoiceController::class, 'belumLunas'])->name('invoices.belum-lunas');
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    });

    Route::middleware('permission:payments.view')->group(function () {
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        // Static route lebih-bayar — didaftarkan SEBELUM /payments/{payment}
        // biar segmen 'overpay' tidak ketelan route dynamic di atasnya.
        Route::get('/payments/overpay', [PaymentController::class, 'overpay'])->name('payments.overpay');
        // Struk/kwitansi cetak — didaftarkan SEBELUM /payments/{payment} biar
        // segmen 'kwitansi' tidak ketelan route dynamic di atasnya.
        Route::get('/payments/{payment}/kwitansi', [PaymentController::class, 'receipt'])->name('payments.receipt');
        Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    });

    Route::middleware('permission:audit_logs.view')->group(function () {
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });

    Route::middleware('permission:payments.create')->group(function () {
        Route::get('/invoices/{invoice}/payments/create', [PaymentController::class, 'create'])->name('invoices.payments.create');
        Route::post('/invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('invoices.payments.store');
    });

    Route::middleware('permission:payments.reject')->group(function () {
        Route::post('/payments/{payment}/reject', [PaymentController::class, 'reject'])->name('payments.reject');
    });

    // ===================== MODUL KOLEKTOR =====================
    // Dua halaman, dua audiens, dua permission — JANGAN disatukan
    // (docs/plan/kolektor/analisa-alur-kolektor-2.0.md §9):
    //   /collector-worksheet → ADMIN: daftar kolektor, assign, cross check
    //   /collector-worklist  → KOLEKTOR: pelanggan sendiri + catat pembayaran
    // Konsekuensinya kolektor tak pernah menyentuh halaman admin, jadi role
    // `kolektor` tetap tanpa `payments.create` maupun `customers.update`.

    // Worksheet Admin — permission SENDIRI (`collector_worksheet.view`), bukan
    // numpang customers.update/payments.create seperti dulu: halaman ini harus
    // bisa dimatikan per-role tanpa mencabut hak edit pelanggan atau hak bayar
    // di halaman Tagihan. Static routes dulu, dynamic ({collector}) belakangan.
    Route::middleware('permission:collector_worksheet.view')->group(function () {
        Route::get('/collector-worksheet', [CollectorWorksheetController::class, 'index'])->name('collector-worksheet.index');
        Route::get('/collector-worksheet/{collector}', [CollectorWorksheetController::class, 'show'])->name('collector-worksheet.show');
    });

    Route::middleware('permission:collector_worksheet.assign')->group(function () {
        // DUA rute, SATU method & satu blok guard:
        //   - tanpa parameter → dipakai panel index, kolektor tujuan dikirim
        //     lewat `collector_id` di body (dipilih dari dropdown);
        //   - dengan {collector} → dipakai tab Atur Pelanggan, kolektornya
        //     sudah tetap dari halamannya.
        // Versi tanpa parameter SENGAJA ada: sebelumnya panel index menyusun
        // URL tujuan di klien lewat Alpine (`:action`), dan Alpine dimuat dari
        // CDN. Begitu CDN tak termuat, form-nya mem-POST ke URL halaman
        // sendiri dan assign diam-diam tidak terjadi. Target POST untuk aksi
        // yang mengubah data tidak boleh bergantung pada skrip pihak ketiga.
        Route::post('/collector-worksheet/assign', [CollectorWorksheetController::class, 'assign'])->name('collector-worksheet.assign-selected');
        Route::post('/collector-worksheet/{collector}/assign', [CollectorWorksheetController::class, 'assign'])->name('collector-worksheet.assign');
        Route::post('/collector-worksheet/{collector}/customers/{customer}/release', [CollectorWorksheetController::class, 'release'])->name('collector-worksheet.release');
    });

    // Admin mencatat pembayaran MEWAKILI seorang kolektor. Kolektornya dari
    // route parameter — aman karena digerbang `payments.create` (hak bayar
    // penuh, admin). Bandingkan dengan rute kolektor di bawah.
    Route::middleware('permission:payments.create')->group(function () {
        Route::post('/payment-batches/{collector}', [PaymentBatchController::class, 'store'])->name('payment-batches.store');
    });

    // Worklist Kolektor — halaman kerja kolektor sendiri.
    Route::middleware('permission:kolektor.view')->group(function () {
        Route::get('/collector-worklist', [CollectorWorklistController::class, 'index'])->name('collector-worklist.index');
    });

    // Kolektor mencatat pembayarannya SENDIRI. TANPA parameter {collector} —
    // kolektor diambil dari auth()->user(). Kalau id kolektor boleh dikirim
    // dari klien, kolektor A bisa mencatat pembayaran atas nama kolektor B.
    Route::middleware('permission:kolektor.pay')->group(function () {
        Route::post('/collector-worklist/pay', [CollectorPaymentController::class, 'store'])->name('collector-worklist.pay');
    });

    // Kolektor menyetorkan SELURUH saldonya ke admin. Sama seperti rute bayar:
    // tanpa parameter, kolektor dari auth()->user().
    Route::middleware('permission:kolektor.deposit')->group(function () {
        Route::post('/collector-worklist/deposit', [CollectorDepositController::class, 'store'])->name('collector-worklist.deposit');
    });

    // Kolektor mencatat kunjungan tanpa hasil. Tanpa parameter kolektor —
    // pelakunya auth()->user(), supaya catatan tak bisa ditulis atas nama
    // orang lain (laporan aging jadi tak bisa dipercaya kalau bisa).
    Route::middleware('permission:kolektor.visit')->group(function () {
        Route::post('/collector-worklist/visits', [CollectorVisitController::class, 'store'])->name('collector-worklist.visits.store');
    });

    // Cross check setoran oleh admin. Guard uangnya (verifikator ≠ penyetor,
    // POP scope seluruh payment, selisih wajib beralasan) ada di
    // CollectorDepositService — permission ini cuma gerbang halamannya.
    Route::middleware('permission:collector_worksheet.validate')->group(function () {
        Route::post('/collector-deposits/{deposit}/verify', [CollectorDepositController::class, 'verify'])->name('collector-deposits.verify');
    });

    // Hapus buku selisih — Owner. Titik di mana kerugian diakui, jadi
    // permission-nya terpisah dari verifikasi (§11.4 no. 4).
    Route::middleware('permission:collector_worksheet.approve')->group(function () {
        Route::post('/collector-deposits/{deposit}/write-off', [CollectorDepositController::class, 'writeOff'])->name('collector-deposits.write-off');
    });

    // ===================== SETORAN KAS ADMIN =====================
    // Satu tingkat DI ATAS setoran kolektor: pelanggan → kolektor → admin →
    // owner/bank. Feature permission SENDIRI (`cash_deposit.*`), bukan
    // menumpang `collector_worksheet.*` — halaman ini memeriksa ADMIN, dan
    // kalau menumpang, tiap admin yang berwenang memverifikasi kolektor
    // otomatis berwenang menutup setoran kasnya sendiri.
    // docs/plan/kolektor/analisa-setoran-kas-admin.md §4.5.
    // Static routes dulu, dynamic ({deposit}) belakangan.
    //
    // DUA TINGKAT RINCIAN (§10):
    //   - `cash_deposit.view`   → pandangan PEMERIKSA: posisi kas admin mana pun
    //     dalam scope-nya, antrean pemeriksaan, rincian sampai tingkat
    //     pelanggan. Owner & atasan.
    //   - `cash_deposit.create` → pandangan PENYETOR: kas & riwayat setoran
    //     SENDIRI, tersaji di Worksheet Admin. Admin & pop_admin, TANPA `view`.
    Route::middleware('permission:cash_deposit.view')->group(function () {
        Route::get('/cash-deposits', [CashDepositController::class, 'index'])->name('cash-deposits.index');
    });

    // Unduh bukti setoran — digerbang DUA permission dengan arti berbeda, dan
    // pembedanya diperiksa di controller: pemegang `view` boleh mengambil bukti
    // siapa pun dalam scope-nya, pemegang `create` HANYA buktinya sendiri.
    // Penyetor tetap harus bisa membuka berkas yang dia unggah sendiri.
    Route::middleware('permission:cash_deposit.view|cash_deposit.create')->group(function () {
        Route::get('/cash-deposits/{deposit}/download', [CashDepositController::class, 'download'])->name('cash-deposits.download');
    });

    // Admin menyetorkan SELURUH saldo tunainya. TANPA parameter admin —
    // penyetor diambil dari auth()->user(), sama alasannya dengan rute setor
    // kolektor: kalau id penyetor boleh datang dari klien, admin A bisa
    // menutup saldo admin B.
    Route::middleware('permission:cash_deposit.create')->group(function () {
        Route::post('/cash-deposits', [CashDepositController::class, 'store'])->name('cash-deposits.store');
    });

    // Pemeriksaan setoran kas — Owner & atasan. Guard uangnya (pemeriksa ≠
    // penyetor, POP scope seluruh sumber, selisih wajib beralasan) ada di
    // CashDepositService; permission ini cuma gerbang halamannya.
    Route::middleware('permission:cash_deposit.validate')->group(function () {
        Route::post('/cash-deposits/{deposit}/verify', [CashDepositController::class, 'verify'])->name('cash-deposits.verify');
    });

    // Menutup selisih kas = titik kerugian (atau kelebihan) diakui. Permission
    // terpisah dari pemeriksaan, pola sama dengan hapus buku setoran kolektor.
    Route::middleware('permission:cash_deposit.approve')->group(function () {
        Route::post('/cash-deposits/{deposit}/write-off', [CashDepositController::class, 'writeOff'])->name('cash-deposits.write-off');
    });

    // Kwitansi — sumbu DOKUMEN, terpisah dari sumbu kas (§13.2). Permission
    // sendiri: staf yang mengurus arsip tak otomatis boleh menutup setoran.
    //
    // GET *dan* POST ke rute yang sama: cetak SATUAN (link <a>, 1 payment_id)
    // tetap pakai GET — aman, pendek. Cetak MASSAL (form, N payment_ids) wajib
    // POST — setoran dengan puluhan/ratusan pembayaran per hari membuat query
    // string GET gampang lewat batas panjang URL server (414 Request-URI Too
    // Large, kejadian nyata 2026-08-14 begitu cetak massal per-setoran dipakai
    // untuk setoran besar). Nama rute tetap sama supaya route() di kedua jalur
    // (link satuan & form massal) tidak perlu dibedakan pemanggilnya.
    Route::middleware('permission:collector_worksheet.print')->group(function () {
        Route::match(['get', 'post'], '/collector-worksheet/{collector}/receipts/print', [PaymentReceiptController::class, 'print'])->name('payment-receipts.print');
    });

    Route::middleware('permission:collector_worksheet.upload')->group(function () {
        Route::post('/payment-receipts', [PaymentReceiptController::class, 'store'])->name('payment-receipts.store');
        Route::post('/payment-receipts/{receipt}/match', [PaymentReceiptController::class, 'matchManually'])->name('payment-receipts.match');
        Route::post('/payment-receipts/{receipt}/detach', [PaymentReceiptController::class, 'detach'])->name('payment-receipts.detach');
    });

    // Unduh berkas kwitansi. Digerbang `view` (bukan upload) karena membaca
    // arsip adalah kebutuhan yang lebih luas daripada mengelolanya — dan
    // berkasnya TIDAK PERNAH dilayani lewat URL publik.
    Route::middleware('permission:collector_worksheet.view')->group(function () {
        // Static route DULU — `progress/{collector}` sebelum `{receipt}/...`,
        // supaya "progress" tidak pernah tertangkap sebagai id kwitansi.
        Route::get('/payment-receipts/progress/{collector}', [PaymentReceiptController::class, 'progress'])->name('payment-receipts.progress');
        Route::get('/payment-receipts/{receipt}/download', [PaymentReceiptController::class, 'download'])->name('payment-receipts.download');
    });

    // Detail Pelanggan — permission SENDIRI (customers.detail.view), terpisah
    // dari customers.view (List). Sebelumnya satu permission yang sama
    // ngegerbangin List DAN Detail, jadi gak bisa kasih akses List doang
    // tanpa Detail atau sebaliknya.
    Route::middleware('permission:customers.detail.view')->group(function () {
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::get('/customers/{customer}/payment-info', [CustomerController::class, 'paymentInfo'])->name('customers.payment-info');
    });

    // Master Data
    Route::middleware('permission:master_wilayah.view')->group(function () {
        Route::get('/master/wilayah', [RegionController::class, 'index'])->name('master.wilayah.index');
    });

    // POP Management - Static Routes First
    Route::middleware('permission:pops.view')->group(function () {
        Route::get('/master/pop', [PopController::class, 'index'])->name('master.pop.index');
    });

    Route::middleware('permission:pops.create|pops.update')->group(function () {
        Route::get('/master/pop/create', [PopController::class, 'create'])->name('master.pop.create');
        Route::post('/master/pop', [PopController::class, 'store'])->name('master.pop.store');
    });

    // POP Management - Dynamic Routes Last
    Route::middleware('permission:pops.view')->group(function () {
        Route::get('/master/pop/{pop}', [PopController::class, 'show'])->name('master.pop.show');
    });

    Route::middleware('permission:pops.create|pops.update')->group(function () {
        Route::get('/master/pop/{pop}/edit', [PopController::class, 'edit'])->name('master.pop.edit');
        Route::put('/master/pop/{pop}', [PopController::class, 'update'])->name('master.pop.update');
        Route::post('/master/pop/{pop}/toggle', [PopController::class, 'toggleStatus'])->name('master.pop.toggle');
    });

    // Distribusi
    Route::middleware('permission:master_distribusi.view')->group(function () {
        Route::get('/master/distribusi', [DistributionController::class, 'index'])->name('master.distribusi.index');
    });

    Route::middleware('permission:master_distribusi.create|master_distribusi.update')->group(function () {
        Route::get('/master/distribusi/create', [DistributionController::class, 'create'])->name('master.distribusi.create');
        Route::post('/master/distribusi', [DistributionController::class, 'store'])->name('master.distribusi.store');
        Route::get('/master/distribusi/{distribusi}/edit', [DistributionController::class, 'edit'])->name('master.distribusi.edit');
        Route::put('/master/distribusi/{distribusi}', [DistributionController::class, 'update'])->name('master.distribusi.update');
    });

    Route::middleware('permission:master_distribusi.delete')->group(function () {
        Route::delete('/master/distribusi/{distribusi}', [DistributionController::class, 'destroy'])->name('master.distribusi.destroy');
    });

    Route::middleware('permission:master_status_pelanggan.view')->group(function () {
        Route::get('/master/status-langganan', [SubscriptionStatusController::class, 'index'])->name('master.status-langganan.index');
    });

    // Master Issue/Kategori Keluhan - Static Routes First
    Route::middleware('permission:ticket_issue_categories.create|ticket_issue_categories.update')->group(function () {
        Route::get('/master/issue-categories/create', [TicketIssueCategoryController::class, 'create'])->name('master.ticket-issue-categories.create');
        Route::post('/master/issue-categories', [TicketIssueCategoryController::class, 'store'])->name('master.ticket-issue-categories.store');
    });

    Route::middleware('permission:ticket_issue_categories.view')->group(function () {
        Route::get('/master/issue-categories', [TicketIssueCategoryController::class, 'index'])->name('master.ticket-issue-categories.index');
    });

    // Master Issue/Kategori Keluhan - Dynamic Routes Last
    Route::middleware('permission:ticket_issue_categories.create|ticket_issue_categories.update')->group(function () {
        Route::get('/master/issue-categories/{category}/edit', [TicketIssueCategoryController::class, 'edit'])->name('master.ticket-issue-categories.edit');
        Route::put('/master/issue-categories/{category}', [TicketIssueCategoryController::class, 'update'])->name('master.ticket-issue-categories.update');
        Route::post('/master/issue-categories/{category}/toggle', [TicketIssueCategoryController::class, 'toggleStatus'])->name('master.ticket-issue-categories.toggle');
    });

    // Master Barang/Material - Static Routes First
    Route::middleware('permission:items.create|items.update')->group(function () {
        Route::get('/master/items/create', [ItemController::class, 'create'])->name('master.items.create');
        Route::post('/master/items', [ItemController::class, 'store'])->name('master.items.store');
    });

    Route::middleware('permission:items.view')->group(function () {
        Route::get('/master/items', [ItemController::class, 'index'])->name('master.items.index');
    });

    // Master Barang/Material - Dynamic Routes Last
    Route::middleware('permission:items.create|items.update')->group(function () {
        Route::get('/master/items/{item}/edit', [ItemController::class, 'edit'])->name('master.items.edit');
        Route::put('/master/items/{item}', [ItemController::class, 'update'])->name('master.items.update');
        Route::post('/master/items/{item}/toggle', [ItemController::class, 'toggleStatus'])->name('master.items.toggle');
    });

    // Master Kategori Barang - Static Routes First
    Route::middleware('permission:item_categories.create|item_categories.update')->group(function () {
        Route::get('/master/item-categories/create', [ItemCategoryController::class, 'create'])->name('master.item-categories.create');
        Route::post('/master/item-categories', [ItemCategoryController::class, 'store'])->name('master.item-categories.store');
    });

    Route::middleware('permission:item_categories.view')->group(function () {
        Route::get('/master/item-categories', [ItemCategoryController::class, 'index'])->name('master.item-categories.index');
    });

    // Master Kategori Barang - Dynamic Routes Last
    Route::middleware('permission:item_categories.create|item_categories.update')->group(function () {
        Route::get('/master/item-categories/{itemCategory}/edit', [ItemCategoryController::class, 'edit'])->name('master.item-categories.edit');
        Route::put('/master/item-categories/{itemCategory}', [ItemCategoryController::class, 'update'])->name('master.item-categories.update');
        Route::post('/master/item-categories/{itemCategory}/toggle', [ItemCategoryController::class, 'toggleStatus'])->name('master.item-categories.toggle');
    });

    // Master Alat Kerja - Static Routes First
    Route::middleware('permission:work_tools.create|work_tools.update')->group(function () {
        Route::get('/master/work-tools/create', [WorkToolController::class, 'create'])->name('master.work-tools.create');
        Route::post('/master/work-tools', [WorkToolController::class, 'store'])->name('master.work-tools.store');
    });

    Route::middleware('permission:work_tools.view')->group(function () {
        Route::get('/master/work-tools', [WorkToolController::class, 'index'])->name('master.work-tools.index');
    });

    // Master Alat Kerja - Dynamic Routes Last
    Route::middleware('permission:work_tools.create|work_tools.update')->group(function () {
        Route::get('/master/work-tools/{workTool}/edit', [WorkToolController::class, 'edit'])->name('master.work-tools.edit');
        Route::put('/master/work-tools/{workTool}', [WorkToolController::class, 'update'])->name('master.work-tools.update');
        Route::post('/master/work-tools/{workTool}/toggle', [WorkToolController::class, 'toggleStatus'])->name('master.work-tools.toggle');
    });

    // Paket Internet Management - Static Routes First
    Route::middleware('permission:packages.create|packages.update')->group(function () {
        Route::get('/master/paket/create', [InternetPackageController::class, 'create'])->name('master.paket.create');
        Route::post('/master/paket', [InternetPackageController::class, 'store'])->name('master.paket.store');
    });

    Route::middleware('permission:packages.view')->group(function () {
        Route::get('/master/paket', [InternetPackageController::class, 'index'])->name('master.paket.index');
    });

    // Paket Internet Management - Dynamic Routes Last
    Route::middleware('permission:packages.create|packages.update')->group(function () {
        Route::get('/master/paket/{paket}/edit', [InternetPackageController::class, 'edit'])->name('master.paket.edit');
        Route::put('/master/paket/{paket}', [InternetPackageController::class, 'update'])->name('master.paket.update');
        Route::post('/master/paket/{paket}/toggle', [InternetPackageController::class, 'toggleStatus'])->name('master.paket.toggle');
    });

    Route::middleware('permission:sla_timeline.view')->group(function () {
        Route::get('/master/sla-timeline', [SlaTimelineController::class, 'index'])->name('master.sla-timeline.index');
    });

    Route::middleware('permission:sla_timeline.update')->group(function () {
        Route::put('/master/sla-timeline/{paket}', [SlaTimelineController::class, 'update'])->name('master.sla-timeline.update');
    });

    Route::middleware('permission:customers.detail.survey.view|customers.detail.survey.update')->group(function () {
        Route::get('/surveys/queue', [CustomerSurveyController::class, 'index'])->name('surveys.queue');
        Route::get('/customers/{customer}/survey/report', [CustomerSurveyController::class, 'report'])->name('customers.survey.report');
        Route::post('/customers/{customer}/survey/start', [CustomerSurveyController::class, 'start'])->name('customers.survey.start');
        Route::post('/customers/{customer}/survey', [CustomerSurveyController::class, 'store'])->name('customers.survey.store');
    });

    Route::middleware('permission:customers.detail.survey.reject')->group(function () {
        Route::post('/customers/{customer}/survey/cancel', [CustomerSurveyController::class, 'cancel'])->name('customers.survey.cancel');
    });

    Route::middleware('permission:customers.update')->group(function () {
        Route::post('/customers/{customer}/assign-survey', [CustomerController::class, 'assignSurvey'])->name('customers.assign-survey');
    });

    Route::middleware('permission:customers.detail.installation.view|customers.detail.installation.update')->group(function () {
        Route::get('/verifications/queue', [CustomerVerificationController::class, 'index'])->name('verifications.queue');
        Route::get('/verifications/{customer}/row', [CustomerVerificationController::class, 'row'])->name('verifications.row');
        Route::get('/customers/{customer}/installation/report', [CustomerInstallationController::class, 'report'])->name('customers.installation.report');
        Route::post('/customers/{customer}/installation/start', [CustomerInstallationController::class, 'start'])->name('customers.installation.start');
        Route::post('/customers/{customer}/installation', [CustomerInstallationController::class, 'store'])->name('customers.installation.store');
        Route::post('/customers/{customer}/installation/pemasangan', [CustomerInstallationController::class, 'storePemasangan'])->name('customers.installation.pemasangan');
        Route::post('/customers/{customer}/installation/speedtest', [CustomerInstallationController::class, 'storeSpeedtest'])->name('customers.installation.speedtest');
        Route::post('/customers/{customer}/test-report', [CustomerTestReportController::class, 'store'])->name('customers.test-report.store');
    });

    Route::middleware('permission:customers.detail.installation.reject')->group(function () {
        Route::post('/customers/{customer}/installation/cancel', [CustomerInstallationController::class, 'cancel'])->name('customers.installation.cancel');
    });

    Route::middleware('permission:customers.detail.installation.validate')->group(function () {
        Route::get('/verifications/{customer}/admin', [CustomerVerificationController::class, 'showAdmin'])->name('customers.verification.admin');
        Route::post('/verifications/{customer}/process-to-team', [CustomerVerificationController::class, 'processToTeam'])->name('customers.verification.process-to-team');
        Route::post('/verifications/{customer}/final', [CustomerVerificationController::class, 'finalVerify'])->name('customers.verification.final');
        Route::post('/verifications/{customer}/revisi', [CustomerVerificationController::class, 'revisi'])->name('customers.verification.revisi');
        Route::post('/verifications/{customer}/reject', [CustomerVerificationController::class, 'reject'])->name('customers.verification.reject');
        Route::post('/customers/{customer}/restore-from-failed', [CustomerController::class, 'restoreFromFailed'])->name('customers.restore-from-failed');
        Route::post('/customers/{customer}/reactivate', [CustomerController::class, 'reactivate'])->name('customers.reactivate');
        Route::get('/customers/{customer}/network-assignment', [CustomerNetworkAssignmentController::class, 'data'])->name('customers.network-assignment.data');
        Route::put('/customers/{customer}/network-assignment', [CustomerNetworkAssignmentController::class, 'update'])->name('customers.network-assignment.update');
    });

    Route::middleware('permission:customers.detail.devices.create|customers.detail.devices.update')->group(function () {
        Route::post('/customers/{customer}/device', [CustomerDeviceController::class, 'store'])->name('customers.device.store');
    });

    Route::middleware('permission:customers.detail.documents.upload')->group(function () {
        Route::post('/customers/{customer}/documents', [CustomerDocumentController::class, 'store'])->name('customers.documents.store');
    });

    Route::middleware('permission:customers.detail.documents.view')->group(function () {
        Route::get('/customer-documents/{document}', [CustomerDocumentController::class, 'show'])->name('customers.documents.show');
    });

    // Reports Management
    Route::middleware('permission:reports.view')->group(function () {
        Route::get('/reports/customers', [CustomerReportController::class, 'index'])->name('reports.customers.index');
        Route::get('/reports/customers/export', [CustomerReportController::class, 'export'])->name('reports.customers.export');
        Route::get('/reports/invoices', [InvoiceReportController::class, 'index'])->name('reports.invoices.index');
        Route::get('/reports/invoices/export', [InvoiceReportController::class, 'export'])->name('reports.invoices.export');
        Route::get('/reports/payments', [PaymentReportController::class, 'index'])->name('reports.payments.index');
        Route::get('/reports/payments/export', [PaymentReportController::class, 'export'])->name('reports.payments.export');
        Route::get('/reports/payments/export-xlsx', [PaymentReportController::class, 'exportXlsx'])->name('reports.payments.export-xlsx');
        Route::get('/reports/imports', [ImportReportController::class, 'index'])->name('reports.imports.index');
        Route::get('/reports/imports/{batch}', [ImportReportController::class, 'show'])->name('reports.imports.show');
        Route::get('/reports/imports/{batch}/export', [ImportReportController::class, 'export'])->name('reports.imports.export');
    });

    // ── FOP Dashboard ────────────────────────────────────────────

    Route::middleware('permission:task.view.all')->group(function () {
        Route::get('/fop', [FopDashboardController::class, 'index'])->name('fop.dashboard');
        Route::get('/api/fop/pipeline', [FopDashboardController::class, 'pipeline'])->name('fop.pipeline');
    });

    // ── Task Management ──────────────────────────────────────────

    // FOP: utility API — cek konflik jadwal (form edit task) & cari pelanggan (modal /fop-tasks)
    Route::middleware('permission:task.lookup')->group(function () {
        Route::match(['get', 'post'], '/api/tasks/check-conflict', [TaskController::class, 'checkConflict'])->name('tasks.check-conflict');
        Route::get('/api/tasks/search-customers', [TaskController::class, 'searchCustomers'])->name('tasks.search-customers');
    });

    // FOP: Edit, cancel task
    Route::middleware('permission:task.manage')->group(function () {
        Route::get('/tasks/{task}/edit', [TaskController::class, 'edit'])->name('tasks.edit');
        Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    });

    Route::middleware('permission:task.manage|task.assign.team')->group(function () {
        Route::patch('/tasks/{task}/team', [TaskTeamController::class, 'update'])->name('tasks.team.update');
    });

    Route::middleware('permission:task.cancel')->group(function () {
        Route::post('/tasks/{task}/cancel', [TaskController::class, 'cancel'])->name('tasks.cancel');
    });

    // Task detail — FOP (view.all) atau Teknisi (view.own + member)
    Route::middleware('permission:task.view.all|task.view.own')->group(function () {
        Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    });

    // FOP: Review actions
    Route::middleware('permission:task.view.all')->group(function () {
        Route::post('/tasks/{task}/review', [TaskController::class, 'review'])->name('tasks.review');
        Route::post('/tasks/{task}/fop-reject', [TaskController::class, 'reject'])->name('tasks.fop-reject');
        Route::post('/tasks/{task}/fop-pending', [TaskController::class, 'pending'])->name('tasks.fop-pending');
    });

    // Teknisi: Dashboard task sendiri
    Route::middleware('permission:task.view.own')->group(function () {
        Route::get('/tasks-saya', [TaskController::class, 'indexOwn'])->name('tasks.own');
        // Endpoint partial HTML — digunakan Echo listener untuk inject task card baru tanpa reload
        Route::get('/tasks-saya/partial/{task}', [TaskController::class, 'cardPartial'])->name('tasks.own.card-partial');
        // Arsip task yang sudah diselesaikan teknisi login — static path, taruh
        // sebelum {task} dinamis di route lain biar gak ketelan.
        Route::get('/tasks-saya/riwayat', [TaskController::class, 'historyOwn'])->name('tasks.own.history');
    });

    // Teknisi: Transisi status (Authorisasi ditangani di Controller menggunakan TaskPolicy)
    Route::post('/tasks/{task}/start', [TaskStatusController::class, 'start'])->name('tasks.start');

    Route::post('/tasks/{task}/complete', [TaskStatusController::class, 'complete'])->name('tasks.complete');

    // Maintenance Report
    Route::get('/tasks/{task}/maintenance-report', [TaskMaintenanceController::class, 'report'])->name('tasks.maintenance.report');
    Route::post('/tasks/{task}/maintenance-report', [TaskMaintenanceController::class, 'store'])->name('tasks.maintenance.store');

    Route::middleware('permission:task.execute')->group(function () {
        Route::post('/tasks/{task}/pending', [TaskStatusController::class, 'pending'])->name('tasks.pending');
        // Pending top-level (reschedule penuh) — beda dari tasks.pending (Lapor Nanti) & tasks.fop-pending (FOP-side).
        Route::post('/tasks/{task}/reschedule', [TaskController::class, 'reschedule'])->name('tasks.reschedule');
    });

    // FOP: Task FOP (Custom)
    Route::middleware('permission:fop_tasks.view')->group(function () {
        Route::get('/fop-tasks', [FopTaskController::class, 'index'])->name('fop-tasks.index');
        Route::get('/fop-tasks/history', [FopTaskController::class, 'history'])->name('fop-tasks.history');
        Route::get('/fop-tasks/history/{fop_task}', [FopTaskController::class, 'showHistory'])->name('fop-tasks.history.show');
        Route::get('/fop-tasks/{fop_task}/row', [FopTaskController::class, 'row'])->name('fop-tasks.row');
    });
    Route::middleware('permission:fop_tasks.create')->group(function () {
        Route::post('/fop-tasks', [FopTaskController::class, 'store'])->name('fop-tasks.store');
    });
    Route::middleware('permission:fop_tasks.update')->group(function () {
        Route::put('/fop-tasks/{fop_task}', [FopTaskController::class, 'update'])->name('fop-tasks.update');
        Route::post('/fop-tasks/{fop_task}/assign-to-team', [FopTaskController::class, 'assignToTeam'])->name('fop-tasks.assign-to-team');
        Route::post('/fop-tasks/switch-technician', [FopTaskController::class, 'switchTechnician'])->name('fop-tasks.switch-technician');
        Route::post('/fop-tasks/{fop_task}/switch-team', [FopDashboardController::class, 'switchTeam'])->name('fop-tasks.switch-team');
    });
    Route::middleware('permission:fop_tasks.delete')->group(function () {
        Route::delete('/fop-tasks/{fop_task}', [FopTaskController::class, 'destroy'])->name('fop-tasks.destroy');
    });

    // ── Ticketing (internal perusahaan) ───────────────────────────
    // Beda dari Task FOP (internal FOP): tiket di sini diajukan role mana pun
    // (helpdesk/NOC/sales/admin) dan otomatis memunculkan FopTask baru.
    Route::middleware('permission:tickets.create')->group(function () {
        // Didaftarkan sebelum /tickets/{bucket} & /tickets/{ticket} — kalau di
        // bawah, '/tickets/new' bakal ketangkep route lain duluan.
        Route::get('/tickets/new', [TicketController::class, 'create'])->name('tickets.create');
        Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
        Route::get('/api/tickets/lookup-customer', [TicketController::class, 'lookupCustomer'])->name('tickets.lookup-customer');
        Route::get('/api/tickets/worksheet-tasks', [TicketController::class, 'worksheetJson'])->name('tickets.worksheet-tasks');
        // Gap #5 — dupe-check server-side per customer_id, gak kena cap panel.
        Route::get('/api/tickets/duplicates', [TicketController::class, 'duplicates'])->name('tickets.duplicates');
    });
    // Halaman arsip — masing-masing route + permission SENDIRI (bukan param
    // {bucket} generik lagi) biar bisa di-toggle independen di Role Matrix.
    // Bucket Masuk & Diproses pindah jadi halaman Worksheet NOC di bawah.
    // Didaftarkan SEBELUM /tickets/{ticket} biar gak ketelan route dinamis.
    Route::middleware('permission:tickets.selesai.view')->group(function () {
        Route::get('/tickets/selesai', [TicketSelesaiController::class, 'index'])->name('tickets.selesai');
    });
    Route::middleware('permission:tickets.dibatalkan.view')->group(function () {
        Route::get('/tickets/dibatalkan', [TicketDibatalkanController::class, 'index'])->name('tickets.dibatalkan');
    });
    // History Ticketing — arsip SEMUA tiket (semua handler & status, termasuk
    // yang masih jalan + tiket "Terputus"). Superset dua halaman arsip di atas,
    // tapi permission-nya sendiri: isinya lintas-bucket dan bisa diekspor.
    // Tetap di atas /tickets/{ticket} — route dinamis di bawah bakal menelannya.
    Route::middleware('permission:tickets.history.view')->group(function () {
        Route::get('/tickets/history', [TicketHistoryController::class, 'index'])->name('tickets.history');
    });
    Route::middleware('permission:tickets.history.export')->group(function () {
        Route::get('/tickets/history/export', [TicketHistoryController::class, 'export'])->name('tickets.history.export');
    });

    Route::middleware('permission:tickets.view')->group(function () {
        Route::get('/ticket-attachments/{attachment}', [TicketController::class, 'download'])->name('tickets.attachments.download');

        // Detail buat drawer kanan di Worksheet Helpdesk & Worksheet NOC —
        // prefix /api/ biar gak ketelan /tickets/{ticket} di bawahnya.
        Route::get('/api/tickets/{ticket}/detail', [TicketController::class, 'detailJson'])
            ->whereNumber('ticket')
            ->name('tickets.detail-json');

        Route::get('/tickets/{ticket}', [TicketController::class, 'show'])
            ->whereNumber('ticket')
            ->name('tickets.show');
    });

    // Close/Escalate (docs/plan/RANCANGAN_WORKSHEET_TICKETING.MD) — otorisasi
    // "cuma pihak yang lagi pegang tiket" dicek di TicketService, bukan di sini.
    Route::middleware('permission:tickets.update')->group(function () {
        Route::post('/tickets/{ticket}/close', [TicketController::class, 'close'])
            ->whereNumber('ticket')
            ->name('tickets.close');
        Route::post('/tickets/{ticket}/escalate', [TicketController::class, 'escalate'])
            ->whereNumber('ticket')
            ->name('tickets.escalate');
        // Gap #7 — NOC kembaliin tiket ke Helpdesk (jalur pemulihan salah kirim).
        Route::post('/tickets/{ticket}/return-to-helpdesk', [TicketController::class, 'returnToHelpdesk'])
            ->whereNumber('ticket')
            ->name('tickets.return-to-helpdesk');
        // Route `tickets.oncheck-noc` DIHAPUS (ADHOC-06) — tiket yang dikirim
        // ke NOC langsung diproses, gak ada langkah "terima dulu".
    });

    // Batalkan tiket pra-FOP — permission terpisah dari tickets.update biar
    // bisa diatur independen lewat matrix role.
    Route::middleware('permission:tickets.cancel')->group(function () {
        Route::post('/tickets/{ticket}/cancel', [TicketController::class, 'cancel'])
            ->whereNumber('ticket')
            ->name('tickets.cancel');
    });

    // ── Worksheet NOC & Dashboard NOC — halaman kerja NOC sendiri, terpisah
    // dari Ticketing generik di atas biar RBAC-nya bisa diatur independen.
    // Worksheet NOC sekarang SATU halaman tanpa tab (ADHOC-06) — dua route tab
    // lama (/noc/worksheet/masuk & /diproses) dihapus, link lamanya diarahkan
    // balik ke halaman utama biar bookmark user gak jadi 404.
    Route::middleware('permission:noc_worksheet.view')->group(function () {
        Route::get('/noc/worksheet', [NocWorksheetController::class, 'index'])->name('noc.worksheet');
    });
    Route::redirect('/noc/worksheet/masuk', '/noc/worksheet');
    Route::redirect('/noc/worksheet/diproses', '/noc/worksheet');
    Route::middleware('permission:noc_dashboard.view')->group(function () {
        Route::get('/noc/dashboard', [NocDashboardController::class, 'index'])->name('noc.dashboard');
    });

    // Location APIs (used in forms)
    Route::get('/api/districts/{district}/villages', function (District $district) {
        return response()->json($district->villages()->orderBy('name')->get());
    });
    Route::get('/api/cities/{city}/districts', function (City $city) {
        return response()->json($city->districts()->orderBy('name')->get());
    });

    // Fase 5.4 — endpoint pencarian wilayah (?q= + limit) untuk typeahead,
    // menggantikan pemuatan SELURUH baris ke <select> yang meledak saat wilayah
    // bertambah. Hasil selalu dibatasi 20 baris; opsional disaring per induk
    // (city_id / district_id). LIKE '%q%' aman karena tabel wilayah kecil dan
    // hasil dibatasi limit.
    Route::get('/api/wilayah/cities', function (Request $request) {
        $q = trim((string) $request->query('q', ''));

        return City::query()
            ->when($q !== '', fn ($b) => $b->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name']);
    })->name('wilayah.cities.search');

    Route::get('/api/wilayah/districts', function (Request $request) {
        $q = trim((string) $request->query('q', ''));

        return District::query()
            ->when($request->filled('city_id'), fn ($b) => $b->where('city_id', $request->integer('city_id')))
            ->when($q !== '', fn ($b) => $b->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'city_id']);
    })->name('wilayah.districts.search');

    Route::get('/api/wilayah/villages', function (Request $request) {
        $q = trim((string) $request->query('q', ''));
        // district_id bisa array (multi kecamatan terpilih) atau tunggal.
        $districtIds = array_values(array_filter(
            (array) $request->query('district_id', []),
            fn ($v) => $v !== '' && $v !== null
        ));

        return Village::query()
            ->when($districtIds !== [], fn ($b) => $b->whereIn('district_id', $districtIds))
            ->when($q !== '', fn ($b) => $b->where('name', 'like', "%{$q}%"))
            ->with('district:id,name')
            ->orderBy('name')
            ->limit(30)
            // Sertakan nama kecamatan untuk disambiguasi desa senama lintas kecamatan.
            ->get(['id', 'name', 'district_id'])
            ->map(fn ($v) => ['id' => $v->id, 'name' => $v->name, 'district_id' => $v->district_id, 'district' => $v->district?->name]);
    })->name('wilayah.villages.search');

    // Fase 5.4b — endpoint pencarian POP untuk filter dropdown (Cabang + Mini POP).
    // WAJIB lewat forUser() supaya HANYA POP dalam scope user yang muncul
    // (mencegah kebocoran lintas cabang di dropdown, sejalan Fase 5.5).
    Route::get('/api/pop/cabang', function (Request $request) {
        $q = trim((string) $request->query('q', ''));

        return Pop::forUser()
            ->where('type', 'cabang')
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%")))
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name', 'code']);
    })->name('pop.cabang.search');

    Route::get('/api/pop/mini', function (Request $request) {
        $q = trim((string) $request->query('q', ''));
        // Mini POP = anak dari cabang terpilih (cascade). pop_id[] = cabang terpilih.
        $cabangIds = array_values(array_filter(
            (array) $request->query('pop_id', []),
            fn ($v) => $v !== '' && $v !== null
        ));

        if ($cabangIds === []) {
            return [];
        }

        return Pop::forUser()
            ->whereIn('parent_id', $cabangIds)
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%")))
            ->with('parent:id,name')
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name', 'code', 'parent_id'])
            ->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'code' => $m->code, 'parent_id' => $m->parent_id, 'parent' => $m->parent?->name]);
    })->name('pop.mini.search');
});
