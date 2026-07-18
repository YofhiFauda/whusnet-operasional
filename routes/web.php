<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerDeviceController;
use App\Http\Controllers\CustomerDocumentController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Master\RegionController;
use App\Http\Controllers\Master\DistributionController;
use App\Http\Controllers\Master\InternetPackageController;
use App\Http\Controllers\Master\SlaTimelineController;
use App\Http\Controllers\Master\SubscriptionStatusController;
use App\Http\Controllers\Master\PopController;
use App\Http\Controllers\CustomerReportController;
use App\Http\Controllers\InvoiceReportController;
use App\Http\Controllers\PaymentReportController;
use App\Http\Controllers\CustomerVerificationController;
use App\Http\Controllers\CustomerInstallationController;
use App\Http\Controllers\CustomerTestReportController;
use App\Http\Controllers\CustomerSurveyController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskStatusController;
use App\Http\Controllers\TaskEvidenceController;
use App\Http\Controllers\FopDashboardController;
use App\Http\Controllers\FopTaskController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Carbon;

// Guest Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// Authenticated Admin Routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Notifications
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/mark-all-read', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.markAllRead');
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.markRead');
    Route::post('/notifications/{id}/unread', [\App\Http\Controllers\NotificationController::class, 'markAsUnread'])->name('notifications.markUnread');

    // Role & Permission Management
    Route::middleware('permission:roles.view|roles.update')->group(function () {
        Route::get('/roles', [\App\Http\Controllers\RolePermissionController::class, 'index'])->name('roles.index');
        Route::post('/roles', [\App\Http\Controllers\RolePermissionController::class, 'store'])->name('roles.store')->middleware('permission:roles.create');
        Route::put('/roles/{role}', [\App\Http\Controllers\RolePermissionController::class, 'updateRole'])->name('roles.update_role')->middleware('permission:roles.update');
        Route::delete('/roles/{role}', [\App\Http\Controllers\RolePermissionController::class, 'destroy'])->name('roles.destroy')->middleware('permission:roles.delete');
        Route::get('/roles/{role}/matrix', [\App\Http\Controllers\RolePermissionController::class, 'matrix'])->name('roles.matrix');
        Route::put('/roles/{role}/matrix', [\App\Http\Controllers\RolePermissionController::class, 'update'])->name('roles.update');
    });

    // User Management
    Route::middleware('permission:users.view')->group(function () {
        Route::get('/users', [\App\Http\Controllers\UserController::class, 'index'])->name('users.index');
    });

    Route::middleware('permission:users.create|users.update')->group(function () {
        Route::get('/users/create', [\App\Http\Controllers\UserController::class, 'create'])->name('users.create');
        Route::post('/users', [\App\Http\Controllers\UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [\App\Http\Controllers\UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [\App\Http\Controllers\UserController::class, 'update'])->name('users.update');
        Route::get('/users/{user}/pops', [\App\Http\Controllers\UserController::class, 'editPops'])->name('users.pops.edit');
        Route::put('/users/{user}/pops', [\App\Http\Controllers\UserController::class, 'updatePops'])->name('users.pops.update');
        Route::post('/users/preview-access', [\App\Http\Controllers\UserController::class, 'previewAccess'])->name('users.preview-access');
    });

    // Customers Management - Static Routes First
    Route::middleware('permission:customers.view')->group(function () {
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
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
        Route::post('/customers/{customer}/terminate', [\App\Http\Controllers\CustomerTerminationController::class, '__invoke'])->name('customers.terminate');
    });

    Route::middleware('permission:customers.detail.installation.activate')->group(function () {
        Route::post('/customers/{customer}/activate', [CustomerController::class, 'activate'])->name('customers.activate');
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
        Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    });

    Route::middleware('permission:audit_logs.view')->group(function () {
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });

    Route::middleware('permission:payments.create')->group(function () {
        Route::get('/invoices/{invoice}/payments/create', [PaymentController::class, 'create'])->name('invoices.payments.create');
        Route::post('/invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('invoices.payments.store');
        Route::post('/invoices/bulk-pay', [PaymentController::class, 'bulkStore'])->name('invoices.payments.bulk-store');
    });

    Route::middleware('permission:customers.view')->group(function () {
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
        Route::get('/customers/{customer}/installation/report', [CustomerInstallationController::class, 'report'])->name('customers.installation.report');
        Route::post('/customers/{customer}/installation/start', [CustomerInstallationController::class, 'start'])->name('customers.installation.start');
        Route::post('/customers/{customer}/installation', [CustomerInstallationController::class, 'store'])->name('customers.installation.store');
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
        Route::get('/customers/{customer}/network-assignment', [\App\Http\Controllers\CustomerNetworkAssignmentController::class, 'data'])->name('customers.network-assignment.data');
        Route::put('/customers/{customer}/network-assignment', [\App\Http\Controllers\CustomerNetworkAssignmentController::class, 'update'])->name('customers.network-assignment.update');
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
        Route::get('/reports/imports', [\App\Http\Controllers\ImportReportController::class, 'index'])->name('reports.imports.index');
        Route::get('/reports/imports/{batch}', [\App\Http\Controllers\ImportReportController::class, 'show'])->name('reports.imports.show');
        Route::get('/reports/imports/{batch}/export', [\App\Http\Controllers\ImportReportController::class, 'export'])->name('reports.imports.export');
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
        Route::patch('/tasks/{task}/team', [\App\Http\Controllers\TaskTeamController::class, 'update'])->name('tasks.team.update');
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
    });

    // Teknisi: Transisi status (Authorisasi ditangani di Controller menggunakan TaskPolicy)
    Route::post('/tasks/{task}/start', [TaskStatusController::class, 'start'])->name('tasks.start');

    Route::post('/tasks/{task}/complete', [TaskStatusController::class, 'complete'])->name('tasks.complete');
    
    // Maintenance Report
    Route::get('/tasks/{task}/maintenance-report', [\App\Http\Controllers\TaskMaintenanceController::class, 'report'])->name('tasks.maintenance.report');
    Route::post('/tasks/{task}/maintenance-report', [\App\Http\Controllers\TaskMaintenanceController::class, 'store'])->name('tasks.maintenance.store');

    Route::middleware('permission:task.execute')->group(function () {
        Route::post('/tasks/{task}/pending', [TaskStatusController::class, 'pending'])->name('tasks.pending');
        // Pending top-level (reschedule penuh) — beda dari tasks.pending (Lapor Nanti) & tasks.fop-pending (FOP-side).
        Route::post('/tasks/{task}/reschedule', [TaskController::class, 'reschedule'])->name('tasks.reschedule');
    });

    // Teknisi: Upload bukti
    Route::middleware('permission:task.execute')->group(function () {
        Route::post('/tasks/{task}/evidences', [TaskEvidenceController::class, 'store'])->name('tasks.evidences.store');
    });

    Route::middleware('permission:task.manage')->group(function () {
        Route::delete('/tasks/{task}/evidences/{evidence}', [TaskEvidenceController::class, 'destroy'])->name('tasks.evidences.destroy');
    });

    // FOP: Task FOP (Custom)
    Route::middleware('permission:fop_tasks.view')->group(function () {
        Route::get('/fop-tasks', [FopTaskController::class, 'index'])->name('fop-tasks.index');
        Route::get('/fop-tasks/history', [FopTaskController::class, 'history'])->name('fop-tasks.history');
        Route::get('/fop-tasks/history/{fop_task}', [FopTaskController::class, 'showHistory'])->name('fop-tasks.history.show');
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
    });
    Route::middleware('permission:tickets.view')->group(function () {
        Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
        Route::get('/ticket-attachments/{attachment}', [TicketController::class, 'download'])->name('tickets.attachments.download');

        // Submenu bucket (masuk|diproses|selesai|dibatalkan). Dibatasi whereIn
        // biar gak bentrok sama route detail di bawahnya yang cuma nerima angka.
        Route::get('/tickets/{bucket}', [TicketController::class, 'index'])
            ->whereIn('bucket', \App\Enums\TicketBucket::values())
            ->name('tickets.bucket');

        Route::get('/tickets/{ticket}', [TicketController::class, 'show'])
            ->whereNumber('ticket')
            ->name('tickets.show');
    });

    // Location APIs (used in forms)
    Route::get('/api/districts/{district}/villages', function (\App\Models\District $district) {
        return response()->json($district->villages()->orderBy('name')->get());
    });
    Route::get('/api/cities/{city}/districts', function (\App\Models\City $city) {
        return response()->json($city->districts()->orderBy('name')->get());
    });
});

