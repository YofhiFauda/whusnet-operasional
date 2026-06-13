<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Master\RegionController;
use App\Http\Controllers\Master\InternetPackageController;
use App\Http\Controllers\Master\SubscriptionStatusController;
use App\Http\Controllers\Master\PopController;
use App\Http\Controllers\CustomerReportController;
use App\Http\Controllers\InvoiceReportController;
use App\Http\Controllers\PaymentReportController;
use Illuminate\Support\Facades\Route;

// Guest Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// Authenticated Admin Routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // User Management
    Route::middleware('permission:view_users')->group(function () {
        Route::get('/users', [\App\Http\Controllers\UserController::class, 'index'])->name('users.index');
    });

    Route::middleware('permission:manage_users')->group(function () {
        Route::get('/users/{user}/pops', [\App\Http\Controllers\UserController::class, 'editPops'])->name('users.pops.edit');
        Route::put('/users/{user}/pops', [\App\Http\Controllers\UserController::class, 'updatePops'])->name('users.pops.update');
    });

    // Customers Management - Static Routes First
    Route::middleware('permission:view_customers')->group(function () {
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    });

    Route::middleware('permission:create_customers')->group(function () {
        Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    });

    Route::middleware('permission:import_customers')->group(function () {
        Route::get('/customers/import', [CustomerController::class, 'importForm'])->name('customers.import');
        Route::get('/customers/import/history', [CustomerController::class, 'importHistory'])->name('customers.import.history');
        Route::get('/customers/import/history/{batch}', [CustomerController::class, 'importBatchDetail'])->name('customers.import.batch-detail');
        Route::get('/customers/import/template', [CustomerController::class, 'downloadImportTemplate'])->name('customers.import.template');
        Route::post('/customers/import/validate', [CustomerController::class, 'validateImport'])->name('customers.import.validate');
        Route::post('/customers/import/confirm', [CustomerController::class, 'confirmImport'])->name('customers.import.confirm');
    });

    // Customers Management - Dynamic Routes Last
    Route::middleware('permission:edit_customers')->group(function () {
        Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    });

    Route::middleware('permission:validate_customer_data')->group(function () {
        Route::post('/customers/{customer}/activate', [CustomerController::class, 'activate'])->name('customers.activate');
    });

    Route::middleware('permission:create_invoices')->group(function () {
        Route::post('/customers/{customer}/invoices/manual', [CustomerController::class, 'storeManualInvoice'])->name('customers.invoices.manual');
    });

    Route::middleware('permission:view_invoices')->group(function () {
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    });

    Route::middleware('permission:view_payments')->group(function () {
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    });

    Route::middleware('permission:create_payments')->group(function () {
        Route::get('/invoices/{invoice}/payments/create', [PaymentController::class, 'create'])->name('invoices.payments.create');
        Route::post('/invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('invoices.payments.store');
    });

    Route::middleware('permission:view_customers')->group(function () {
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    });

    // Master Data
    Route::middleware('permission:view_pop')->group(function () {
        Route::get('/master/wilayah', [RegionController::class, 'index'])->name('master.wilayah.index');
    });

    // POP Management - Static Routes First
    Route::middleware('permission:manage_pop')->group(function () {
        Route::get('/master/pop/create', [PopController::class, 'create'])->name('master.pop.create');
        Route::post('/master/pop', [PopController::class, 'store'])->name('master.pop.store');
    });

    Route::middleware('permission:view_pop')->group(function () {
        Route::get('/master/pop', [PopController::class, 'index'])->name('master.pop.index');
    });

    // POP Management - Dynamic Routes Last
    Route::middleware('permission:manage_pop')->group(function () {
        Route::get('/master/pop/{pop}/edit', [PopController::class, 'edit'])->name('master.pop.edit');
        Route::put('/master/pop/{pop}', [PopController::class, 'update'])->name('master.pop.update');
        Route::post('/master/pop/{pop}/toggle', [PopController::class, 'toggleStatus'])->name('master.pop.toggle');
    });

    Route::middleware('permission:view_pop')->group(function () {
        Route::get('/master/pop/{pop}', [PopController::class, 'show'])->name('master.pop.show');
    });

    Route::middleware('permission:view_packages')->group(function () {
        Route::get('/master/status-langganan', [SubscriptionStatusController::class, 'index'])->name('master.status-langganan.index');
    });

    // Paket Internet Management - Static Routes First
    Route::middleware('permission:manage_packages')->group(function () {
        Route::get('/master/paket/create', [InternetPackageController::class, 'create'])->name('master.paket.create');
        Route::post('/master/paket', [InternetPackageController::class, 'store'])->name('master.paket.store');
    });

    Route::middleware('permission:view_packages')->group(function () {
        Route::get('/master/paket', [InternetPackageController::class, 'index'])->name('master.paket.index');
    });

    // Paket Internet Management - Dynamic Routes Last
    Route::middleware('permission:manage_packages')->group(function () {
        Route::get('/master/paket/{paket}/edit', [InternetPackageController::class, 'edit'])->name('master.paket.edit');
        Route::put('/master/paket/{paket}', [InternetPackageController::class, 'update'])->name('master.paket.update');
        Route::post('/master/paket/{paket}/toggle', [InternetPackageController::class, 'toggleStatus'])->name('master.paket.toggle');
    });

    Route::middleware('permission:fill_survey')->group(function () {
        Route::post('/customers/{customer}/survey', [\App\Http\Controllers\CustomerSurveyController::class, 'store'])->name('customers.survey.store');
    });

    // Reports Management
    Route::get('/reports/customers', [CustomerReportController::class, 'index'])->name('reports.customers.index');
    Route::get('/reports/customers/export', [CustomerReportController::class, 'export'])->name('reports.customers.export');
    Route::get('/reports/invoices', [InvoiceReportController::class, 'index'])->name('reports.invoices.index');
    Route::get('/reports/invoices/export', [InvoiceReportController::class, 'export'])->name('reports.invoices.export');
    Route::get('/reports/payments', [PaymentReportController::class, 'index'])->name('reports.payments.index');
    Route::get('/reports/payments/export', [PaymentReportController::class, 'export'])->name('reports.payments.export');
    Route::get('/reports/imports', [\App\Http\Controllers\ImportReportController::class, 'index'])->name('reports.imports.index');
    Route::get('/reports/imports/{batch}', [\App\Http\Controllers\ImportReportController::class, 'show'])->name('reports.imports.show');
    Route::get('/reports/imports/{batch}/export', [\App\Http\Controllers\ImportReportController::class, 'export'])->name('reports.imports.export');

    // Location APIs (used in forms)
    Route::get('/api/districts/{district}/villages', function (\App\Models\District $district) {
        return response()->json($district->villages()->orderBy('name')->get());
    });
    Route::get('/api/cities/{city}/districts', function (\App\Models\City $city) {
        return response()->json($city->districts()->orderBy('name')->get());
    });
});
