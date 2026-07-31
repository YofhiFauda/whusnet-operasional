<?php $__env->startSection('title', 'Import Pelanggan - Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', 'Import Pelanggan'); ?>
<?php $__env->startSection('breadcrumb_parent', 'Pelanggan'); ?>
<?php $__env->startSection('breadcrumb_parent_url', '/customers'); ?>

<?php $__env->startSection('content'); ?>
<!-- Header Area -->
<div class="mb-6">
    <h1 class="text-2xl font-bold text-text-main tracking-tight">Import Pelanggan & Billing Lama</h1>
    <p class="text-xs text-text-secondary mt-1">Unggah file Excel multi-sheet untuk memigrasikan data pelanggan, paket, layanan, detail teknis, tagihan, dan pembayaran</p>
    <div class="mt-4 flex gap-3">
        <a href="<?php echo e(route('customers.import.template')); ?>" class="inline-flex items-center justify-center gap-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold py-2.5 px-4 rounded transition-all shadow-sm">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            Download Template Excel (Multi-Sheet)
        </a>
        <a href="<?php echo e(route('customers.import.history')); ?>" class="inline-flex items-center justify-center gap-2 bg-surface border border-border text-text-secondary hover:bg-surface-muted text-xs font-semibold py-2 px-4 rounded transition-colors">
            Lihat Riwayat Import
        </a>
    </div>
</div>

<!-- Main Grid -->
<div class="grid grid-cols-1 gap-6">
    
    <!-- Upload Area Card -->
    <div class="bg-surface border border-border rounded-lg shadow-sm overflow-hidden p-6">
        <div class="border-2 border-dashed border-border hover:border-primary-border transition-colors rounded-lg p-8 text-center bg-surface-muted/30 relative">
            <input type="file" id="file-input" accept=".xlsx, .xls" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" onchange="handleFileSelect(event)">
            <div class="flex flex-col items-center">
                <svg class="h-12 w-12 text-text-disabled mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
                <span class="block text-sm font-bold text-text-main">Tarik & Letakkan File Excel Migrasi di Sini</span>
                <span class="block text-xs text-text-muted mt-1">Harus berupa file Excel (.XLSX / .XLS) yang memiliki 6 sheet: customers, packages, services, technical_details, invoices, payments</span>
                <span class="inline-block mt-4 bg-primary-soft border border-primary-border text-primary hover:bg-primary-soft/80 text-xs font-bold px-4 py-2 rounded transition-colors">
                    Pilih File Excel
                </span>
            </div>
        </div>
        <div id="file-info-container" class="hidden mt-4 flex items-center justify-between text-xs p-3 bg-primary-soft border border-primary-border rounded-md text-primary">
            <span class="font-medium font-mono" id="file-name-text">filename.xlsx</span>
            <button type="button" onclick="resetFileSelection()" class="text-primary hover:text-primary-hover font-bold focus:outline-none">Hapus</button>
        </div>
    </div>

    <!-- Preview & Validation Results -->
    <div id="preview-section" class="hidden space-y-6">
        
        <!-- Loading Indicator -->
        <div id="loading-indicator" class="hidden bg-surface border border-border rounded-lg p-8 text-center shadow-sm">
            <svg class="animate-spin h-8 w-8 text-primary mx-auto mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="block text-xs font-semibold text-text-secondary">Membaca & Memvalidasi Relasi Seluruh Sheet...</span>
        </div>

        <!-- Tab Selector for Sheets -->
        <div id="sheets-tab-container" class="bg-surface-muted border border-border rounded-lg flex p-1.5 overflow-x-auto gap-2">
            <button type="button" onclick="switchSheetTab('customers')" id="btn-tab-customers" class="flex-1 min-w-[120px] px-3 py-2 text-xs font-bold rounded-md transition-all text-center focus:outline-none cursor-pointer bg-surface text-text-main shadow-sm border border-border">
                Customers <span id="badge-cust-count" class="ml-1 px-1.5 py-0.5 text-[10px] bg-surface-muted rounded text-text-secondary">0</span>
            </button>
            <button type="button" onclick="switchSheetTab('packages')" id="btn-tab-packages" class="flex-1 min-w-[120px] px-3 py-2 text-xs font-bold rounded-md transition-all text-center focus:outline-none cursor-pointer text-text-secondary hover:bg-surface/50">
                Packages <span id="badge-pkg-count" class="ml-1 px-1.5 py-0.5 text-[10px] bg-surface-muted rounded text-text-secondary">0</span>
            </button>
            <button type="button" onclick="switchSheetTab('services')" id="btn-tab-services" class="flex-1 min-w-[120px] px-3 py-2 text-xs font-bold rounded-md transition-all text-center focus:outline-none cursor-pointer text-text-secondary hover:bg-surface/50">
                Services <span id="badge-serv-count" class="ml-1 px-1.5 py-0.5 text-[10px] bg-surface-muted rounded text-text-secondary">0</span>
            </button>
            <button type="button" onclick="switchSheetTab('technical_details')" id="btn-tab-technical_details" class="flex-1 min-w-[120px] px-3 py-2 text-xs font-bold rounded-md transition-all text-center focus:outline-none cursor-pointer text-text-secondary hover:bg-surface/50">
                Technical Details <span id="badge-tech-count" class="ml-1 px-1.5 py-0.5 text-[10px] bg-surface-muted rounded text-text-secondary">0</span>
            </button>
            <button type="button" onclick="switchSheetTab('invoices')" id="btn-tab-invoices" class="flex-1 min-w-[120px] px-3 py-2 text-xs font-bold rounded-md transition-all text-center focus:outline-none cursor-pointer text-text-secondary hover:bg-surface/50">
                Invoices <span id="badge-inv-count" class="ml-1 px-1.5 py-0.5 text-[10px] bg-surface-muted rounded text-text-secondary">0</span>
            </button>
            <button type="button" onclick="switchSheetTab('payments')" id="btn-tab-payments" class="flex-1 min-w-[120px] px-3 py-2 text-xs font-bold rounded-md transition-all text-center focus:outline-none cursor-pointer text-text-secondary hover:bg-surface/50">
                Payments <span id="badge-pay-count" class="ml-1 px-1.5 py-0.5 text-[10px] bg-surface-muted rounded text-text-secondary">0</span>
            </button>
        </div>

        <!-- Metrics per Sheet -->
        <div id="sheet-metrics-container" class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-surface border border-border p-4 rounded-lg shadow-sm">
                <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">Total Baris</span>
                <span class="block text-xl font-extrabold text-text-main mt-1 font-mono" id="metric-total">0</span>
            </div>
            <div class="bg-surface border border-border p-4 rounded-lg shadow-sm">
                <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">Valid / Siap</span>
                <span class="block text-xl font-extrabold text-green-600 mt-1 font-mono" id="metric-ready">0</span>
            </div>
            <div class="bg-surface border border-border p-4 rounded-lg shadow-sm">
                <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">Peringatan</span>
                <span class="block text-xl font-extrabold text-amber-500 mt-1 font-mono" id="metric-warning">0</span>
            </div>
            <div class="bg-surface border border-border p-4 rounded-lg shadow-sm">
                <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider">Error Format / Wajib</span>
                <span class="block text-xl font-extrabold text-red-600 mt-1 font-mono" id="metric-error">0</span>
            </div>
        </div>

        <!-- Sheet Preview Tables -->
        <div class="bg-surface border border-border rounded-lg shadow-sm overflow-hidden flex flex-col">
            <div class="px-6 py-4 bg-surface-muted border-b border-border flex justify-between items-center">
                <h3 class="text-xs font-bold text-text-main uppercase tracking-wider" id="preview-title">Preview Data: Customers</h3>
                <span class="text-[10px] text-text-secondary font-medium">Pastikan seluruh tab bebas dari error sebelum mengonfirmasi import</span>
            </div>

            <!-- Table wrappers for each sheet -->
            <div class="overflow-x-auto min-h-[300px]">
                <table class="w-full text-left text-xs border-collapse text-text-secondary">
                    <thead id="preview-table-header" class="bg-surface-muted/50 border-b border-border text-text-muted font-semibold text-[10px] uppercase">
                        <!-- Header cells will be injected -->
                    </thead>
                    <tbody id="preview-table-body" class="divide-y divide-border">
                        <!-- Rows will be injected -->
                    </tbody>
                </table>
            </div>

            <!-- Action Form Footer -->
            <div class="px-6 py-4 bg-surface-muted border-t border-border flex justify-between items-center">
                <span class="text-xs text-slate-500" id="submit-summary-text">0 baris siap di-import.</span>
                
                <form action="<?php echo e(route('customers.import.confirm')); ?>" method="POST" id="confirm-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="sheets" id="confirm-sheets-json">
                    <input type="hidden" name="file_name" id="confirm-file-name">
                    <button type="submit" id="btn-submit-import" class="inline-flex items-center justify-center gap-2 bg-sky-600 hover:bg-sky-700 disabled:bg-slate-200 disabled:text-slate-400 text-white text-xs font-semibold py-2.5 px-6 rounded transition-all cursor-pointer focus:outline-none shadow-sm disabled:cursor-not-allowed">
                        <svg id="btn-submit-import-spinner" class="hidden animate-spin h-4 w-4 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span id="btn-submit-import-text">Simpan Hasil Migrasi ke Database</span>
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
<script>
    let validatedSheetsData = {};
    let currentSheetTab = 'customers';

    function handleFileSelect(e) {
        const file = e.target.files[0];
        if (!file) return;

        document.getElementById('confirm-file-name').value = file.name;
        document.getElementById('file-name-text').textContent = `${file.name} (${formatBytes(file.size)})`;
        document.getElementById('file-info-container').classList.remove('hidden');

        document.getElementById('preview-section').classList.remove('hidden');
        document.getElementById('loading-indicator').classList.remove('hidden');
        document.getElementById('sheets-tab-container').classList.add('hidden');
        document.getElementById('sheet-metrics-container').classList.add('hidden');
        document.getElementById('preview-table-body').innerHTML = '';

        validateSheets(file);
    }

    function validateSheets(file) {
        const formData = new FormData();
        formData.append('file', file);

        fetch('/customers/import/validate', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            document.getElementById('loading-indicator').classList.add('hidden');
            if (data.success) {
                validatedSheetsData = data.sheets;
                
                // Show containers
                document.getElementById('sheets-tab-container').classList.remove('hidden');
                document.getElementById('sheet-metrics-container').classList.remove('hidden');
                
                updateTabBadges();
                switchSheetTab(currentSheetTab);
            } else {
                window.Toast.error("Gagal Validasi", data.message || "Gagal memvalidasi data Excel. Pastikan format file sesuai.");
                resetFileSelection();
            }
        })
        .catch(err => {
            console.error("API error:", err);
            window.Toast.error("Error Jaringan", "Kesalahan jaringan saat memvalidasi.");
            document.getElementById('loading-indicator').classList.add('hidden');
            resetFileSelection();
        });
    }

    function switchSheetTab(sheetName) {
        currentSheetTab = sheetName;
        
        // Update tab buttons style
        const tabs = ['customers', 'packages', 'services', 'technical_details', 'invoices', 'payments'];
        tabs.forEach(t => {
            const btn = document.getElementById(`btn-tab-${t}`);
            if (btn) {
                if (t === sheetName) {
                    btn.className = "flex-1 min-w-[120px] px-3 py-2 text-xs font-bold rounded-md transition-all text-center focus:outline-none cursor-pointer bg-surface text-text-main shadow-sm border border-border";
                } else {
                    btn.className = "flex-1 min-w-[120px] px-3 py-2 text-xs font-bold rounded-md transition-all text-center focus:outline-none cursor-pointer text-text-secondary hover:bg-surface/50";
                }
            }
        });

        // Update preview title
        const titleMap = {
            customers: 'Preview Data: Customers',
            packages: 'Preview Data: Packages (Master Paket)',
            services: 'Preview Data: Services (Layanan)',
            technical_details: 'Preview Data: Technical Details',
            invoices: 'Preview Data: Invoices (Tagihan)',
            payments: 'Preview Data: Payments (Pembayaran)'
        };
        document.getElementById('preview-title').textContent = titleMap[sheetName];

        renderPreviewSheet(sheetName);
    }

    function renderPreviewSheet(sheetName) {
        const sheet = validatedSheetsData[sheetName] || { rows: [] };
        const rows = sheet.rows;

        // Render Metrics
        let total = rows.length;
        let ready = 0, warning = 0, error = 0;
        rows.forEach(r => {
            if (r.status_row === 'error') error++;
            else if (r.status_row === 'warning') warning++;
            else ready++;
        });

        document.getElementById('metric-total').textContent = total;
        document.getElementById('metric-ready').textContent = ready;
        document.getElementById('metric-warning').textContent = warning;
        document.getElementById('metric-error').textContent = error;

        // Render Table Headers & Body
        const thead = document.getElementById('preview-table-header');
        const tbody = document.getElementById('preview-table-body');
        thead.innerHTML = '';
        tbody.innerHTML = '';

        if (total === 0) {
            tbody.innerHTML = `<tr><td colspan="10" class="px-4 py-8 text-center text-slate-400 italic">Sheet ini kosong atau tidak ditemukan.</td></tr>`;
            return;
        }

        const headersMap = {
            customers: ['NO', 'ID LAMA', 'NAMA LENGKAP', 'POP / CABANG', 'DESA', 'NOMOR HP', 'CATATAN'],
            packages: ['NO', 'ID PAKET LAMA', 'NAMA PAKET', 'HARGA BULANAN', 'SPEED', 'CATATAN'],
            services: ['NO', 'ID REQUEST LAMA', 'ID CUSTOMER LAMA', 'ID PAKET LAMA', 'STATUS LAYANAN', 'CATATAN'],
            technical_details: ['NO', 'ID REPORT LAMA', 'ID CUSTOMER LAMA', 'ONT SN', 'IP ADDRESS', 'ODP', 'CATATAN'],
            invoices: ['NO', 'ID INVOICE LAMA', 'ID CUSTOMER LAMA', 'PERIODE', 'NOMINAL TOTAL', 'STATUS', 'CATATAN'],
            payments: ['NO', 'ID PEMBAYARAN LAMA', 'ID INVOICE LAMA', 'NOMINAL BAYAR', 'TGL BAYAR', 'CATATAN']
        };

        const currentHeaders = headersMap[sheetName];
        const trHeader = document.createElement('tr');
        currentHeaders.forEach(h => {
            const th = document.createElement('th');
            th.className = "px-4 py-3";
            th.textContent = h;
            trHeader.appendChild(th);
        });
        thead.appendChild(trHeader);

        rows.forEach((row, index) => {
            const tr = document.createElement('tr');
            if (row.status_row === 'error') tr.className = "bg-error-bg/40 text-error";
            else if (row.status_row === 'warning') tr.className = "bg-warning-bg/40 text-warning";
            else tr.className = "hover:bg-surface-muted transition-colors";

            // Render columns based on sheet structure
            let cells = [];
            cells.push(index + 1);

            if (sheetName === 'customers') {
                cells.push(row.old_customer_id || '-');
                cells.push(row.full_name || '-');
                cells.push(row.pop_name || '-');
                cells.push(row.village_name || row.village || '-');
                cells.push(row.phone || '-');
            } else if (sheetName === 'packages') {
                cells.push(row.old_package_id || '-');
                cells.push(row.name || '-');
                cells.push(row.monthly_price !== null ? `Rp ${Number(row.monthly_price).toLocaleString('id-ID')}` : '-');
                cells.push((row.download_speed || '10') + ' Mbps');
            } else if (sheetName === 'services') {
                cells.push(row.old_request_id || '-');
                cells.push(row.old_customer_id || '-');
                cells.push(row.old_package_id || '-');
                cells.push(row.service_status || '-');
            } else if (sheetName === 'technical_details') {
                cells.push(row.old_report_id || '-');
                cells.push(row.old_customer_id || '-');
                cells.push(row.ont_sn || '-');
                cells.push(row.ip_address || '-');
                cells.push(row.odp_code || '-');
            } else if (sheetName === 'invoices') {
                cells.push(row.old_invoice_id || '-');
                cells.push(row.old_customer_id || '-');
                cells.push(row.billing_period || '-');
                cells.push(`Rp ${Number(row.total_amount).toLocaleString('id-ID')}`);
                cells.push(row.status || '-');
            } else if (sheetName === 'payments') {
                cells.push(row.old_payment_id || '-');
                cells.push(row.old_invoice_id || '-');
                cells.push(`Rp ${Number(row.amount).toLocaleString('id-ID')}`);
                cells.push(row.payment_date || '-');
            }

            // Append standard columns
            cells.forEach(c => {
                const td = document.createElement('td');
                td.className = "px-4 py-3 font-medium";
                td.textContent = c;
                tr.appendChild(td);
            });

            // Log / Status column
            const tdLog = document.createElement('td');
            tdLog.className = "px-4 py-3";
            if (row.status_row === 'error') {
                const span = document.createElement('span');
                span.className = "inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-error-bg text-error border border-error-border";
                span.textContent = row.errors.join(' | ');
                tdLog.appendChild(span);
            } else if (row.status_row === 'warning') {
                const span = document.createElement('span');
                span.className = "inline-flex px-2 py-0.5 rounded text-[10px] font-semibold bg-warning-bg text-warning border border-warning-border";
                span.textContent = row.warnings.join(' | ');
                tdLog.appendChild(span);
            } else {
                const span = document.createElement('span');
                span.className = "inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-success-bg text-success border border-success-border";
                span.textContent = "VALID";
                tdLog.appendChild(span);
            }
            tr.appendChild(tdLog);
            tbody.appendChild(tr);
        });

        updateSubmitSection();
    }

    function updateTabBadges() {
        const tabs = ['customers', 'packages', 'services', 'technical_details', 'invoices', 'payments'];
        const badgeIds = {
            customers: 'badge-cust-count',
            packages: 'badge-pkg-count',
            services: 'badge-serv-count',
            technical_details: 'badge-tech-count',
            invoices: 'badge-inv-count',
            payments: 'badge-pay-count'
        };

        tabs.forEach(t => {
            const count = (validatedSheetsData[t] && validatedSheetsData[t].rows) ? validatedSheetsData[t].rows.length : 0;
            const badge = document.getElementById(badgeIds[t]);
            if (badge) badge.textContent = count;
        });
    }

    function updateSubmitSection() {
        let hasErrors = false;
        let totalCount = 0;
        let validCount = 0;

        const tabs = ['customers', 'packages', 'services', 'technical_details', 'invoices', 'payments'];
        tabs.forEach(t => {
            const rows = (validatedSheetsData[t] && validatedSheetsData[t].rows) ? validatedSheetsData[t].rows : [];
            rows.forEach(r => {
                totalCount++;
                if (r.status_row === 'error') {
                    hasErrors = true;
                } else {
                    validCount++;
                }
            });
        });

        const btnSubmit = document.getElementById('btn-submit-import');
        const summaryText = document.getElementById('submit-summary-text');

        if (totalCount === 0) {
            btnSubmit.disabled = true;
            summaryText.innerHTML = `<span class="text-text-muted">Tidak ada data terdeteksi untuk diimport.</span>`;
            return;
        }

        if (hasErrors) {
            btnSubmit.disabled = true;
            summaryText.innerHTML = `<span class="text-error font-bold">Terdeteksi baris error. Hapus baris bermasalah di file Excel lalu unggah ulang.</span>`;
        } else {
            btnSubmit.disabled = false;
            summaryText.innerHTML = `<span class="text-success font-bold">Semua data valid! ${validCount} baris siap dimasukkan ke database.</span>`;
            
            // Populate hidden inputs
            document.getElementById('confirm-sheets-json').value = JSON.stringify(validatedSheetsData);
        }
    }

    function resetFileSelection() {
        document.getElementById('file-input').value = '';
        document.getElementById('file-info-container').classList.add('hidden');
        document.getElementById('preview-section').classList.add('hidden');
        validatedSheetsData = {};
    }

    function formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    document.getElementById('confirm-form').addEventListener('submit', function() {
        const btnSubmit = document.getElementById('btn-submit-import');
        const spinner = document.getElementById('btn-submit-import-spinner');
        const buttonText = document.getElementById('btn-submit-import-text');

        btnSubmit.disabled = true;
        spinner.classList.remove('hidden');
        buttonText.textContent = 'Menyimpan...';
    });
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/customers/import.blade.php ENDPATH**/ ?>