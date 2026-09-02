<?php $__env->startSection('title', 'Detail Pelanggan: ' . $customer->full_name . ' — Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', 'Detail Pelanggan'); ?>

<?php $__env->startSection('content'); ?>
<?php
    $completeness = $customer->dataCompleteness();
    $missingRequiredLabels = array_values($completeness['missing_required']);
    $missingOptionalLabels = array_values($completeness['missing_optional']);
    $regDate = \App\Support\IndonesianDate::date($customer->registration_date);
    $latestInvoice = $customer->invoices->first();

    $monthlyPrice  = (float)($customer->customerService?->monthly_price ?? ($customer->internetPackage?->monthly_price ?? 0));
    $discount      = (float)($customer->customerService?->discount ?? ($customer->discount_amount ?? 0));
    $otherFee      = (float)($customer->customerService?->other_fee ?? 0);
    $ppnPercent    = $customer->customerService ? (float)$customer->customerService->ppn : (float)($customer->tax_percent ?? 0);

    $discountedPrice = max(0, $monthlyPrice - $discount);
    $ppnAmount       = round($discountedPrice * ($ppnPercent / 100), 2);
    $totalBill       = $customer->customerService
        ? (float)$customer->customerService->total_monthly_bill
        : ($discountedPrice + $ppnAmount + $otherFee);

    $isActive = in_array($customer->status, ['active', 'suspended']) || $customer->data_completeness_status === 'siap_billing';
?>

<!-- LAYER 1: NAKED PAGE HEADER (Strict Design System Rule: No card wrapper) -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-xl font-bold text-slate-900 dark:text-slate-50 tracking-tight"><?php echo e($customer->full_name); ?></h1>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-semibold <?php echo e($customer->subscriptionStatus?->badgeClasses() ?? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800'); ?>">
                ● Status: <?php echo e($customer->subscriptionStatus->name ?? Str::headline($customer->status)); ?>

            </span>
            <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-700 px-2 py-0.5 rounded text-xs font-mono font-medium text-slate-700 dark:text-slate-300">
                <span><?php echo e($displayIdLabel ?? 'CID'); ?>: <?php echo e($displayId); ?></span>
                <button type="button" onclick="copyText('<?php echo e($displayId); ?>', 'CID')" class="text-slate-400 hover:text-sky-600 ml-1 cursor-pointer" title="Salin CID">
                    <i class="fa-regular fa-copy"></i>
                </button>
            </div>
            <?php if($customer->collector): ?>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-semibold bg-violet-50 dark:bg-violet-500/10 text-violet-700 dark:text-violet-400 border border-violet-200 dark:border-violet-500/20" title="Kolektor yang rutin menagih pelanggan ini">
                    Kolektor: <?php echo e($customer->collector->name); ?>

                </span>
            <?php else: ?>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-semibold bg-slate-50 dark:bg-slate-800 text-slate-400 dark:text-slate-500 border border-slate-200 dark:border-slate-700">
                    Belum Ada Kolektor
                </span>
            <?php endif; ?>

            <?php
                // Total uang lebih yang pernah diserahkan pelanggan ini.
                // Pembayaran DITOLAK tak ikut dijumlah — kalau pembayarannya
                // dibatalkan, lebih bayarnya ikut batal.
                $totalOverpay = $customer->payments
                    ->filter(fn ($p) => $p->payment_status === \App\Enums\PaymentStatus::VALID)
                    ->sum(fn ($p) => (float) $p->overpay_amount);
            ?>
            <?php if($totalOverpay > 0): ?>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-semibold bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-500/20" title="Total uang lebih yang pernah diserahkan. Catatan saja — bukan saldo, tidak otomatis dipakai untuk tagihan berikutnya.">
                    Lebih Bayar: Rp <?php echo e(number_format($totalOverpay, 0, ',', '.')); ?>

                </span>
            <?php endif; ?>
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Paket: <strong><?php echo e($customer->internetPackage ? ($customer->internetPackage->package_code . ' - ' . $customer->internetPackage->name) : 'Belum Ada Paket'); ?></strong> (Rp <?php echo e(number_format($totalBill, 0, ',', '.')); ?>/bln) — <?php echo e($customer->pop->name ?? 'POP Belum Set'); ?> (<?php echo e($customer->miniPop->name ?? 'Mini POP Belum Set'); ?>) — Terdaftar sejak <?php echo e($regDate); ?>

        </p>
    </div>
    <div class="flex items-center gap-2 shrink-0 flex-wrap">
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('customers.detail.installation.activate')): ?>
            <?php
                $hasWorkflowTask = isset($customerTasks) && $customerTasks->whereIn('task_type', [\App\Enums\TaskType::SURVEY->value, \App\Enums\TaskType::PEMASANGAN->value])->isNotEmpty();
                $isProvenLegacyActive = $customer->old_customer_id && $customer->customerService?->request_status === 'ACTIVE';
            ?>
            <?php if($customer->data_completeness_status !== 'siap_billing' && $customer->status !== 'active' && !$hasWorkflowTask && $isProvenLegacyActive): ?>
                <form action="<?php echo e(route('customers.activate', $customer->id)); ?>" method="POST" class="inline" onsubmit="event.preventDefault(); window.confirmAction('Pelanggan ini belum aktif lewat proses verifikasi normal. Aktifkan manual sekarang? CID akan dibuat dan tagihan pertama akan diterbitkan.', this);">
                    <?php echo csrf_field(); ?>
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-300 disabled:cursor-not-allowed text-white rounded-lg transition-colors text-xs font-semibold shadow-sm cursor-pointer"
                            title="Khusus pelanggan migrasi lama."
                            <?php if(!$completeness['is_ready_billing']): ?> disabled title="Data profil belum lengkap untuk diaktifkan" <?php endif; ?>>
                        <i class="fa-solid fa-circle-check"></i>
                        Aktivasi Manual
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <a href="<?php echo e(route('customers.edit', $customer->id)); ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg transition-colors text-xs font-semibold shadow-sm">
            <i class="fa-solid fa-pen-to-square"></i>
            Edit Profil
        </a>

        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('customers.qr.view')): ?>
            <a href="<?php echo e(route('customers.qr.show', $customer->id)); ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/50 text-slate-700 dark:text-slate-300 rounded-lg transition-colors text-xs font-semibold shadow-sm">
                <i class="fa-solid fa-qrcode"></i>
                QR Pelanggan
            </a>
        <?php endif; ?>

        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('invoices.create')): ?>
            <?php if($isActive && $customer->customerService): ?>
                <button type="button" onclick="openInvoiceModal()" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors text-xs font-semibold shadow-sm cursor-pointer">
                    <i class="fa-solid fa-plus"></i>
                    Buat Tagihan
                </button>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('customers.detail.installation.validate')): ?>
            <button type="button" x-data @click="$dispatch('open-modal', 'network-assignment')" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors text-xs font-semibold shadow-sm cursor-pointer">
                <i class="fa-solid fa-diagram-project"></i>
                Atur Mini POP
            </button>
        <?php endif; ?>

        <a href="<?php echo e(route('customers.index')); ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/50 text-slate-700 dark:text-slate-300 rounded-lg transition-colors text-xs font-semibold shadow-sm">
            Kembali
        </a>
    </div>
</div>

<!-- LAYER 3: SINGLE UNIFIED DETAIL PANEL (Card Budget = 1) -->
<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-sm overflow-hidden">

    <!-- SECTION A: QUICK METRIC STRIP (Flat summary bar with dividers) -->
    <div class="grid grid-cols-2 md:grid-cols-5 divide-x divide-y md:divide-y-0 divide-slate-200 dark:divide-slate-700 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/30">
        <div class="p-4 flex flex-col justify-center">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">LANGGANAN & BIAYA</span>
            <span class="text-xs font-bold text-slate-900 dark:text-slate-100 mt-1 truncate"><?php echo e($customer->internetPackage->name ?? 'Belum Ada Paket'); ?></span>
            <span class="text-xs font-mono font-semibold text-sky-600 dark:text-sky-400">Rp <?php echo e(number_format($totalBill, 0, ',', '.')); ?>/bln (Nett)</span>
        </div>
        <div class="p-4 flex flex-col justify-center">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">PPPOE</span>
            <div class="flex items-center gap-1.5 mt-1 font-mono text-xs font-semibold text-slate-900 dark:text-slate-100">
                <span><?php echo e($customer->customerTechnicalDetail->pppoe_username ?? ($customer->pppoe_username ?? '-')); ?></span>
            </div>
        </div>
        <div class="p-4 flex flex-col justify-center">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">MODEM ONT & SIGNAL</span>
            <span class="text-xs font-mono font-semibold text-slate-900 dark:text-slate-100 mt-1 truncate"><?php echo e($customer->ont_sn ?? ($customer->customerDevice->ont_sn ?? 'Belum Terpasang')); ?></span>
            <span class="text-[11px] font-mono text-emerald-600 dark:text-emerald-400 font-semibold">Redaman: <?php echo e($customer->customerTechnicalDetail->fiber_signal ?? ($customer->customerDevice->signal_power ?? '-')); ?></span>
        </div>
        <div class="p-4 flex flex-col justify-center">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">BILLING & TAGIHAN</span>
            <?php if($latestInvoice): ?>
                <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 mt-1 truncate"><?php echo e($latestInvoice->invoice_number); ?> (<?php echo e($latestInvoice->invoice_status->label()); ?>)</span>
            <?php else: ?>
                <span class="text-xs font-bold text-slate-400 mt-1">Belum Ada Tagihan</span>
            <?php endif; ?>
            <span class="text-[11px] text-slate-500">Jatuh Tempo: <?php echo e($customer->customerService?->due_date ? 'Tgl ' . \Carbon\Carbon::parse($customer->customerService->due_date)->day . ' Per Bulan' : '-'); ?></span>
        </div>
        <div class="p-4 flex flex-col justify-center col-span-2 md:col-span-1">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">KELENGKAPAN PROFIL</span>
            <div class="flex items-center justify-between mt-1">
                <span class="text-xs font-bold <?php echo e(count($completeness['missing_required']) > 0 ? 'text-rose-600' : (count($completeness['missing_optional']) > 0 ? 'text-amber-600' : 'text-emerald-600')); ?>"><?php echo e($completeness['percentage']); ?>% Lengkap</span>
                <span class="text-[10px] font-mono text-slate-400"><?php echo e(28 - count($completeness['missing_required']) - count($completeness['missing_optional'])); ?>/28 Parameter</span>
            </div>
            <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-1.5 mt-1.5 overflow-hidden">
                <div class="<?php echo e(count($completeness['missing_required']) > 0 ? 'bg-rose-500' : (count($completeness['missing_optional']) > 0 ? 'bg-amber-500' : 'bg-emerald-500')); ?> h-full rounded-full transition-all duration-300" style="width: <?php echo e($completeness['percentage']); ?>%"></div>
            </div>
        </div>
    </div>

    <!-- SECTION C: SEARCH & EXACT 15-TAB NAV BAR -->
    <div class="border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
        <!-- Omni-Search Bar & Mode Toggle -->
        <div class="p-4 border-b border-slate-200 dark:border-slate-700 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
            <div class="relative flex-1">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" id="omni-search" onkeyup="filterContent()" placeholder="⚡ Cari apapun di seluruh tab (contoh: IP, ZTE, PPPoE, Speedtest, NIK, Prorate, Tiang, Kontrak)..."
                       class="w-full pl-9 pr-10 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                <button type="button" onclick="clearSearch()" id="clear-search-btn" class="hidden absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs cursor-pointer">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-900 p-1 rounded-lg border border-slate-200 dark:border-slate-700 text-xs shrink-0">
                <button type="button" onclick="setViewMode('tabs')" id="view-mode-tabs" class="px-3 py-1.5 rounded-md font-semibold bg-white dark:bg-slate-800 text-sky-600 shadow-sm transition-all cursor-pointer">📑 Mode Tab (15 Tab)</button>
                <button type="button" onclick="setViewMode('all')" id="view-mode-all" class="px-3 py-1.5 rounded-md font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 transition-all cursor-pointer">⚡ All-In-One (Scroll Semua)</button>
            </div>
        </div>

        <!-- 15 Tab Buttons Nav -->
        <div id="tab-nav-wrapper" class="overflow-x-auto flex border-b border-slate-200 dark:border-slate-700 scrollbar-none px-2 bg-slate-50/50 dark:bg-slate-900/30">
            <button type="button" onclick="switchTab('ringkasan')" id="tab-btn-ringkasan" class="tab-button px-3.5 py-3 text-xs font-bold border-b-2 border-sky-600 text-sky-600 whitespace-nowrap cursor-pointer">Ringkasan (Overview)</button>
            <?php if(auth()->user()->hasPermission('customers.detail.identity.view')): ?>
            <button type="button" onclick="switchTab('identitas')" id="tab-btn-identitas" class="tab-button px-3.5 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 whitespace-nowrap cursor-pointer">Identitas</button>
            <?php endif; ?>
            <?php if(auth()->user()->hasPermission('customers.detail.address.view')): ?>
            <button type="button" onclick="switchTab('alamat')" id="tab-btn-alamat" class="tab-button px-3.5 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 whitespace-nowrap cursor-pointer">Alamat</button>
            <?php endif; ?>
            <button type="button" onclick="switchTab('pop')" id="tab-btn-pop" class="tab-button px-3.5 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 whitespace-nowrap cursor-pointer">POP/Cabang</button>
            <?php if(auth()->user()->hasPermission('customers.detail.survey.view')): ?>
            <button type="button" onclick="switchTab('survey')" id="tab-btn-survey" class="tab-button px-3.5 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 whitespace-nowrap cursor-pointer">Survey</button>
            <?php endif; ?>
            <?php if(auth()->user()->hasPermission('customers.detail.installation.view')): ?>
            <button type="button" onclick="switchTab('pemasangan')" id="tab-btn-pemasangan" class="tab-button px-3.5 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 whitespace-nowrap cursor-pointer">Pemasangan</button>
            <?php endif; ?>
            <?php if(auth()->user()->hasPermission('customers.detail.devices.view')): ?>
            <button type="button" onclick="switchTab('perangkat')" id="tab-btn-perangkat" class="tab-button px-3.5 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 whitespace-nowrap cursor-pointer">Perangkat</button>
            <?php endif; ?>
            <?php if(auth()->user()->hasPermission('customers.detail.packages.view')): ?>
            <button type="button" onclick="switchTab('paket-layanan')" id="tab-btn-paket-layanan" class="tab-button px-3.5 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 whitespace-nowrap cursor-pointer">Paket & Layanan</button>
            <?php endif; ?>
            <button type="button" onclick="switchTab('billing')" id="tab-btn-billing" class="tab-button px-3.5 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 whitespace-nowrap cursor-pointer">Billing</button>
            <button type="button" onclick="switchTab('tagihan')" id="tab-btn-tagihan" class="tab-button px-3.5 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 whitespace-nowrap cursor-pointer">Tagihan</button>
            <button type="button" onclick="switchTab('pembayaran')" id="tab-btn-pembayaran" class="tab-button px-3.5 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 whitespace-nowrap cursor-pointer">Pembayaran</button>
            <?php if(auth()->user()->hasPermission('customers.detail.documents.view')): ?>
            <button type="button" onclick="switchTab('dokumen')" id="tab-btn-dokumen" class="tab-button px-3.5 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 whitespace-nowrap cursor-pointer">Dokumen & Berkas</button>
            <?php endif; ?>
            <button type="button" onclick="switchTab('riwayat-ticketing')" id="tab-btn-riwayat-ticketing" class="tab-button px-3.5 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 whitespace-nowrap cursor-pointer">Riwayat Ticketing</button>
            <button type="button" onclick="switchTab('riwayat-perubahan')" id="tab-btn-riwayat-perubahan" class="tab-button px-3.5 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 whitespace-nowrap cursor-pointer">Riwayat Perubahan</button>
            <?php if($customer->customerTechnicalDetail): ?>
            <button type="button" onclick="switchTab('teknis-lama')" id="tab-btn-teknis-lama" class="tab-button px-3.5 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 whitespace-nowrap cursor-pointer">Detail Teknis Lama</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- SECTION D: UNIFIED DETAILS BODY (All Data Tabs) -->
    <div class="p-6 text-xs" id="details-container">

        <!-- TAB 1: RINGKASAN (OVERVIEW) -->
        <div id="tab-content-ringkasan" class="tab-content space-y-6 searchable-section">
            <!-- Completeness Overview Banner -->
            <div class="border <?php echo e(count($completeness['missing_required']) > 0 ? 'border-rose-200 dark:border-rose-800 bg-rose-50/40 dark:bg-rose-950/20' : (count($completeness['missing_optional']) > 0 ? 'border-amber-200 dark:border-amber-800 bg-amber-50/40 dark:bg-amber-950/20' : 'border-emerald-200 dark:border-emerald-800 bg-emerald-50/40 dark:bg-emerald-950/20')); ?> rounded-lg p-5">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">Kelengkapan Data Profil Pelanggan</h3>
                    <span class="text-xs font-semibold text-slate-500">Persentase Terisi: <strong class="text-sky-600 font-bold"><?php echo e($completeness['percentage']); ?>%</strong></span>
                </div>
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold <?php echo e(count($completeness['missing_required']) > 0 ? 'bg-rose-100 text-rose-700 border border-rose-300' : (count($completeness['missing_optional']) > 0 ? 'bg-amber-100 text-amber-700 border border-amber-300' : 'bg-emerald-100 text-emerald-700 border border-emerald-300')); ?>">
                        ● <?php echo e(Str::headline($customer->data_completeness_status)); ?>

                    </span>
                    <span class="text-xs text-slate-600 dark:text-slate-300">
                        Profil data terisi <strong class="<?php echo e(count($completeness['missing_required']) > 0 ? 'text-rose-600' : 'text-emerald-600'); ?> font-bold"><?php echo e($completeness['percentage']); ?>%</strong> dari total 28 parameter evaluasi sistem.
                    </span>
                </div>
            </div>

            <!-- CARD TIMELINE PROSES ALUR PENGERJAAN -->
            <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-5 bg-white dark:bg-slate-800">
                <div class="flex flex-wrap gap-2 items-center justify-between mb-3">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">CARD TIMELINE PROSES ALUR PENGERJAAN</span>
                    <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded border <?php echo e($customer->status === 'active' ? 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/50 border-emerald-200 dark:border-emerald-800' : 'text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-700 border-slate-200 dark:border-slate-600'); ?>">
                        <?php echo e(Str::headline($customer->status)); ?>

                    </span>
                </div>

                <?php if(! empty($timelineAnomaly)): ?>
                    <p class="text-[10px] text-amber-600 dark:text-amber-400 mb-3 italic">
                        Catatan: tanggal registrasi tercatat lebih akhir dari tanggal survey/pemasangan — anomali data hasil migrasi legacy, bukan kesalahan tampilan.
                    </p>
                <?php endif; ?>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                    <?php $__currentLoopData = ($timeline ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $step): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php
                            $stepTone = match($step['status']) {
                                'completed' => 'bg-emerald-50/60 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-800',
                                'current' => 'bg-sky-50/60 dark:bg-sky-950/30 border-sky-200 dark:border-sky-800',
                                'warning' => 'bg-amber-50/60 dark:bg-amber-950/30 border-amber-200 dark:border-amber-800',
                                'danger' => 'bg-rose-50/60 dark:bg-rose-950/30 border-rose-200 dark:border-rose-800',
                                default => 'bg-slate-50 dark:bg-slate-900/40 border-slate-200 dark:border-slate-700',
                            };
                            $stepIcon = match($step['status']) {
                                'completed' => 'fa-circle-check text-emerald-500',
                                'current' => 'fa-circle-half-stroke text-sky-500',
                                'warning' => 'fa-triangle-exclamation text-amber-500',
                                'danger' => 'fa-circle-xmark text-rose-500',
                                default => 'fa-circle text-slate-300 dark:text-slate-600',
                            };
                        ?>
                        <div class="p-3 rounded-lg border <?php echo e($stepTone); ?>">
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <span class="text-[10px] font-bold uppercase <?php echo e($step['status'] === 'completed' ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-600 dark:text-slate-300'); ?>">
                                    <?php echo e($index + 1); ?>. <?php echo e($step['step']); ?>

                                </span>
                                <i class="fa-solid <?php echo e($stepIcon); ?> text-xs"></i>
                            </div>
                            <span class="block text-xs font-bold text-slate-900 dark:text-slate-100"><?php echo e($step['date']); ?></span>
                            <span class="block text-[10px] font-semibold text-slate-600 dark:text-slate-300"><?php echo e($step['title']); ?></span>
                            <span class="block text-[10px] text-slate-500 font-mono truncate" title="<?php echo e($step['notes']); ?>"><?php echo e($step['notes']); ?></span>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>

            <!-- CARD RINGKASAN WAKTU & PENANGGUNG JAWAB PER TAHAP -->
            <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden bg-white dark:bg-slate-800 shadow-sm">
                <div class="px-5 py-3.5 bg-slate-50/70 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">
                        <i class="fa-solid fa-clock-rotate-left text-sky-600 mr-1.5"></i> Ringkasan Waktu &amp; Penanggung Jawab Per Tahap Workflow
                    </h3>
                    <p class="text-[11px] text-slate-500 mt-0.5">Rekapitulasi timestamp eksekusi dan PIC penanggung jawab dari tahap pendaftaran hingga verifikasi aktivasi.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50/40 dark:bg-slate-900/30 border-b border-slate-200 dark:border-slate-700 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                <th class="px-5 py-3">Tahap Alur Workflow</th>
                                <th class="px-5 py-3">Waktu &amp; Tanggal Eksekusi</th>
                                <th class="px-5 py-3">Penanggung Jawab (PIC)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                            <?php
                                // Class ditulis literal, BUKAN "bg-{$accent}-100": Tailwind 4
                                // memindai teks sumber, jadi class yang dirakit lewat
                                // interpolasi tidak akan pernah ikut ter-generate.
                                $accentClasses = [
                                    'sky' => 'bg-sky-100 dark:bg-sky-950 text-sky-600 dark:text-sky-400',
                                    'indigo' => 'bg-indigo-100 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400',
                                    'amber' => 'bg-amber-100 dark:bg-amber-950 text-amber-600 dark:text-amber-400',
                                    'purple' => 'bg-purple-100 dark:bg-purple-950 text-purple-600 dark:text-purple-400',
                                    'emerald' => 'bg-emerald-100 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400',
                                ];
                            ?>
                            <?php $__currentLoopData = ($workflowStages ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stage): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php
                                    $accent = $accentClasses[$stage['accent']] ?? 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300';
                                    $waktu = $stage['at']
                                        ? \App\Support\IndonesianDate::dateTimeWithSeconds($stage['at'])
                                        : ($stage['date_fallback'] ?: null);
                                    $initials = $stage['pic']
                                        ? strtoupper(mb_substr(preg_replace('/[^\p{L}]/u', '', $stage['pic']), 0, 2))
                                        : '—';
                                ?>
                                <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-900/30 transition-colors">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-2.5">
                                            <span class="w-6 h-6 shrink-0 rounded-full <?php echo e($accent); ?> flex items-center justify-center font-bold text-[10px]"><?php echo e($stage['no']); ?></span>
                                            <div>
                                                <span class="block text-xs font-bold text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($stage['title']); ?></span>
                                                <span class="block text-[10px] text-slate-400 font-mono"><?php echo e($stage['subtitle']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 font-mono searchable-text <?php echo e($waktu ? 'text-slate-700 dark:text-slate-300' : 'text-slate-400'); ?>">
                                        <?php echo e($waktu ?: 'Belum tercatat'); ?>

                                    </td>
                                    <td class="px-5 py-3">
                                        <?php if($stage['pic']): ?>
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 shrink-0 rounded-full <?php echo e($accent); ?> flex items-center justify-center font-bold text-[10px]"><?php echo e($initials); ?></div>
                                                <div>
                                                    <span class="block text-xs font-semibold text-slate-800 dark:text-slate-200 searchable-text"><?php echo e($stage['pic']); ?></span>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-slate-400">Belum ada PIC</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Active Subscription card -->
                <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-4 bg-slate-50/50 dark:bg-slate-900/30">
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">LANGGANAN AKTIF</span>
                    <h4 class="text-sm font-semibold text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($customer->internetPackage ? ($customer->internetPackage->package_code . ' - ' . $customer->internetPackage->name) : 'Belum Ada Paket'); ?></h4>
                    <p class="text-xs text-slate-500 mt-1">Kategori: <?php echo e($customer->internetPackage->category ?? '-'); ?></p>
                    <div class="flex items-baseline mt-3">
                        <span class="text-lg font-bold text-slate-900 dark:text-slate-100 font-mono searchable-text">Rp <?php echo e(number_format($totalBill, 0, ',', '.')); ?></span>
                        <span class="text-[10px] text-slate-400 ml-1">/bulan (Nett)</span>
                    </div>
                </div>
                
                <!-- Address Card -->
                <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-4 bg-slate-50/50 dark:bg-slate-900/30">
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">ALAMAT INSTALASI</span>
                    <p class="text-xs text-slate-800 dark:text-slate-200 font-semibold leading-relaxed searchable-text"><?php echo e($customer->address ?? 'Belum diisi'); ?></p>
                    <p class="text-[10px] text-slate-500 mt-1.5 searchable-text">
                        Kel. <?php echo e($customer->village->name ?? ($customer->customerAddress->village ?? '-')); ?>, 
                        Kec. <?php echo e($customer->district->name ?? ($customer->customerAddress->district ?? '-')); ?>, 
                        Kota/Kab. <?php echo e($customer->city->name ?? ($customer->customerAddress->city ?? '-')); ?>

                    </p>
                    <div class="mt-2.5 flex items-center gap-1.5 font-mono text-[10px] text-slate-500 searchable-text">
                        <i class="fa-solid fa-location-dot text-sky-600"></i>
                        <span>Lat/Long: <?php echo e($customer->latitude ?? ($customer->customerAddress->latitude ?? '-')); ?>, <?php echo e($customer->longitude ?? ($customer->customerAddress->longitude ?? '-')); ?></span>
                    </div>
                </div>
            </div>

            <!-- Technical summary card -->
            <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-5">
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-3">RINGKASAN TEKNIS JARINGAN</span>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-4 text-xs">
                    <div>
                        <span class="block text-[9px] font-bold text-slate-400 uppercase">NOMOR CID / REQ ID</span>
                        <span class="font-mono font-bold text-sky-600 dark:text-sky-400 searchable-text"><?php echo e($displayId); ?></span>
                    </div>
                    <div>
                        <span class="block text-[9px] font-bold text-slate-400 uppercase">POP CABANG</span>
                        <span class="font-semibold text-slate-800 dark:text-slate-200 searchable-text"><?php echo e($customer->pop->name ?? '-'); ?></span>
                    </div>
                    <div>
                        <span class="block text-[9px] font-bold text-slate-400 uppercase">MINI POP (OLT)</span>
                        <span class="font-semibold text-slate-800 dark:text-slate-200 searchable-text"><?php echo e($customer->miniPop->name ?? 'Belum di-assign'); ?></span>
                    </div>
                    <div>
                        <span class="block text-[9px] font-bold text-slate-400 uppercase">DISTRIBUSI ODP</span>
                        <span class="font-semibold text-slate-800 dark:text-slate-200 searchable-text"><?php echo e($customer->distribution->code ?? 'Belum di-assign'); ?></span>
                    </div>
                    <div>
                        <span class="block text-[9px] font-bold text-slate-400 uppercase">ONT SERIAL NUMBER</span>
                        <span class="font-mono font-semibold text-slate-800 dark:text-slate-200 searchable-text"><?php echo e($customer->ont_sn ?? '-'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Missing Fields Alert if applicable -->
            <?php if(count($completeness['missing_required']) > 0 || count($completeness['missing_optional']) > 0): ?>
            <div class="border border-amber-200 dark:border-amber-800 rounded-lg p-5 bg-amber-50/40 dark:bg-amber-950/20">
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">PARAMETER YANG BELUM LENGKAP</span>
                <div class="space-y-3 text-xs">
                    <?php if(count($completeness['missing_required']) > 0): ?>
                    <div>
                        <span class="font-semibold text-rose-600 block mb-1">Field Wajib (Mencegah Layanan Aktif/Billing):</span>
                        <div class="flex flex-wrap gap-1.5">
                            <?php $__currentLoopData = $missingRequiredLabels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <span class="px-2.5 py-0.5 bg-rose-50 text-rose-600 border border-rose-200 rounded text-[10px] font-mono"><?php echo e($label); ?></span>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if(count($completeness['missing_optional']) > 0): ?>
                    <div>
                        <span class="font-semibold text-amber-600 block mb-1">Field Opsional/Teknis:</span>
                        <div class="flex flex-wrap gap-1.5">
                            <?php $__currentLoopData = $missingOptionalLabels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <span class="px-2.5 py-0.5 bg-amber-50 text-amber-600 border border-amber-200 rounded text-[10px] font-mono dark:bg-red-950 dark:border-red-200 dark:text-white"><?php echo e($label); ?></span>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- TAB 2: IDENTITAS -->
        <?php if(auth()->user()->hasPermission('customers.detail.identity.view')): ?>
        <div id="tab-content-identitas" class="tab-content hidden space-y-6 searchable-section">
            <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
                <div class="px-5 py-3.5 bg-slate-50/50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700">
                    <span class="text-xs font-bold text-slate-900 dark:text-slate-100">Formulir Identitas Pelanggan</span>
                </div>
                <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-xs">
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">ID Registrasi (Permanen)</span>
                        <span class="font-mono font-bold text-sky-600 dark:text-sky-400 searchable-text"><?php echo e($customer->customer_code); ?></span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Nama Lengkap</span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($customer->full_name); ?></span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Nomor Identitas (NIK)</span>
                        <span class="font-mono font-medium text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($customer->identity_number ?? 'Belum diisi'); ?></span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Jenis Kelamin</span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($customer->gender?->label() ?? 'Belum diisi'); ?></span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Nomor HP Utama</span>
                        <span class="font-mono font-medium text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($customer->primary_phone ?? 'Belum diisi'); ?></span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Nomor HP Alternatif</span>
                        <span class="font-mono font-medium text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($customer->alternative_phone ?? 'Belum diisi'); ?></span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Alamat Email</span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($customer->email ?? 'Belum diisi'); ?></span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between md:col-span-2">
                        <span class="text-slate-400">Tanggal Registrasi</span>
                        <span class="font-mono font-medium text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($regDate); ?></span>
                    </div>
                </div>
            </div>

            <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
                <div class="px-5 py-3.5 bg-slate-50/50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700">
                    <span class="text-xs font-bold text-slate-900 dark:text-slate-100">Informasi Referral & Sales</span>
                </div>
                <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-6 text-xs">
                    <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-4 bg-slate-50/50 dark:bg-slate-900/30">
                        <span class="block text-[9px] font-bold text-slate-400 uppercase">ID SALES</span>
                        <span class="font-mono font-medium text-slate-900 dark:text-slate-100 mt-1 block searchable-text"><?php echo e($customer->sales_code ?? '-'); ?></span>
                    </div>
                    <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-4 bg-slate-50/50 dark:bg-slate-900/30">
                        <span class="block text-[9px] font-bold text-slate-400 uppercase">KODE AGENT</span>
                        <span class="font-mono font-medium text-slate-900 dark:text-slate-100 mt-1 block searchable-text"><?php echo e($customer->agent_code ?? '-'); ?></span>
                    </div>
                    <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-4 bg-slate-50/50 dark:bg-slate-900/30">
                        <span class="block text-[9px] font-bold text-slate-400 uppercase">REFERRAL PELANGGAN</span>
                        <span class="font-mono font-medium text-slate-900 dark:text-slate-100 mt-1 block searchable-text"><?php echo e($customer->referral_customer_code ?? '-'); ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- TAB 3: ALAMAT -->
        <?php if(auth()->user()->hasPermission('customers.detail.address.view')): ?>
        <div id="tab-content-alamat" class="tab-content hidden space-y-6 searchable-section">
            <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
                <?php
                    $lat = $customer->latitude ?? ($customer->customerAddress->latitude ?? null);
                    $lng = $customer->longitude ?? ($customer->customerAddress->longitude ?? null);
                ?>
                <div class="px-5 py-3.5 bg-slate-50/50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700 flex flex-wrap gap-2 justify-between items-center">
                    <span class="text-xs font-bold text-slate-900 dark:text-slate-100">Alamat Instalasi Detail</span>
                    <?php if($lat && $lng): ?>
                        <a href="https://maps.google.com/?q=<?php echo e($lat); ?>,<?php echo e($lng); ?>" target="_blank" rel="noopener" class="text-xs text-sky-600 font-semibold hover:underline">Buka di Google Maps ↗</a>
                    <?php endif; ?>
                </div>
                <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-xs">
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between md:col-span-2">
                        <span class="text-slate-400">Alamat Lengkap</span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100 text-right searchable-text"><?php echo e($customer->address ?? 'Belum diisi'); ?></span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Desa / Kelurahan</span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($customer->village->name ?? ($customer->customerAddress->village ?? 'Belum diisi')); ?></span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Kecamatan</span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($customer->district->name ?? ($customer->customerAddress->district ?? 'Belum diisi')); ?></span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Kota / Kabupaten</span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($customer->city->name ?? ($customer->customerAddress->city ?? 'Belum diisi')); ?></span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Provinsi</span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($customer->customerAddress->province ?? 'Jawa Timur'); ?></span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Garis Lintang (Latitude)</span>
                        <span class="font-mono font-medium text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($customer->latitude ?? ($customer->customerAddress->latitude ?? 'Belum diisi')); ?></span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Garis Bujur (Longitude)</span>
                        <span class="font-mono font-medium text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($customer->longitude ?? ($customer->customerAddress->longitude ?? 'Belum diisi')); ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- TAB 4: POP/CABANG -->
        <div id="tab-content-pop" class="tab-content hidden space-y-6 searchable-section">
            <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
                <div class="px-5 py-3.5 bg-slate-50/50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center">
                    <span class="text-xs font-bold text-slate-900 dark:text-slate-100">Data POP / Cabang Terkoneksi</span>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('customers.detail.installation.validate')): ?>
                    <button type="button" x-data @click="$dispatch('open-modal', 'network-assignment')" class="text-xs text-sky-600 font-semibold hover:underline cursor-pointer">Ubah Penugasan POP →</button>
                    <?php endif; ?>
                </div>
                <?php if($customer->pop): ?>
                <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-xs">
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Nama POP / Cabang</span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($customer->pop->name); ?></span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Kode Cabang</span>
                        <span class="font-mono font-semibold text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($customer->pop->code); ?></span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Tipe Cabang</span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100 uppercase text-[10px] tracking-wider searchable-text"><?php echo e($customer->pop->type); ?></span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Status POP</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-200 uppercase">
                            <?php echo e($customer->pop->status ? 'Aktif' : 'Nonaktif'); ?>

                        </span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between md:col-span-2">
                        <span class="text-slate-400">Alamat Kantor POP</span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100 text-right searchable-text">
                            <?php echo e($customer->pop->address); ?>, Kel. <?php echo e($customer->pop->village); ?>, Kec. <?php echo e($customer->pop->district); ?>, <?php echo e($customer->pop->city); ?>

                        </span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Penanggung Jawab (PIC)</span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($customer->pop->pic_name ?? 'Belum ditentukan'); ?></span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Nomor HP PIC</span>
                        <span class="font-mono font-medium text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($customer->pop->pic_phone ?? 'Belum ditentukan'); ?></span>
                    </div>
                </div>
                <?php else: ?>
                <div class="p-8 text-center text-slate-400 text-xs">
                    Belum ada POP/Cabang yang di-assign untuk pelanggan ini.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- TAB 5: SURVEY -->
        <?php if(auth()->user()->hasPermission('customers.detail.survey.view')): ?>
        <div id="tab-content-survey" class="tab-content hidden space-y-6 searchable-section">
            <?php echo $__env->make('customers.tabs._survey', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
        <?php endif; ?>

        <!-- TAB 6: PEMASANGAN -->
        <?php if(auth()->user()->hasPermission('customers.detail.installation.view')): ?>
        <div id="tab-content-pemasangan" class="tab-content hidden space-y-6 searchable-section">
            <?php echo $__env->make('customers.tabs._installation', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
        <?php endif; ?>

        <!-- TAB 7: PERANGKAT -->
        <?php if(auth()->user()->hasPermission('customers.detail.devices.view')): ?>
        <div id="tab-content-perangkat" class="tab-content hidden space-y-6 searchable-section">
            <?php echo $__env->make('customers.tabs._device', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
        <?php endif; ?>

        <!-- TAB 8: PAKET & LAYANAN -->
        <?php if(auth()->user()->hasPermission('customers.detail.packages.view')): ?>
        <div id="tab-content-paket-layanan" class="tab-content hidden space-y-6 searchable-section">
            <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
                <div class="px-5 py-3.5 bg-slate-50/50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700">
                    <span class="text-xs font-bold text-slate-900 dark:text-slate-100">Paket Internet & Status Layanan Jaringan</span>
                </div>
                <div class="p-5 space-y-5">
                    <?php if($customer->internetPackage): ?>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-3 bg-slate-50/50 dark:bg-slate-900/30">
                            <span class="block text-[9px] font-bold text-slate-400 uppercase">KODE LAYANAN</span>
                            <span class="font-mono font-semibold text-slate-900 dark:text-slate-100 mt-1 block searchable-text"><?php echo e($customer->internetPackage->package_code); ?> - <?php echo e($customer->internetPackage->name); ?></span>
                        </div>
                        <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-3 bg-slate-50/50 dark:bg-slate-900/30">
                            <span class="block text-[9px] font-bold text-slate-400 uppercase">KATEGORI LAYANAN</span>
                            <span class="font-semibold text-slate-900 dark:text-slate-100 mt-1 block searchable-text"><?php echo e($customer->internetPackage->category); ?></span>
                        </div>
                        <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-3 bg-slate-50/50 dark:bg-slate-900/30">
                            <span class="block text-[9px] font-bold text-slate-400 uppercase">KECEPATAN (BANDWIDTH)</span>
                            <span class="font-mono font-semibold text-sky-600 dark:text-sky-400 mt-1 block searchable-text">
                                <?php echo e($customer->internetPackage->download_speed_mbps); ?> Mbps Down / <?php echo e($customer->internetPackage->upload_speed_mbps); ?> Mbps Up
                            </span>
                        </div>
                        <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-3 bg-slate-50/50 dark:bg-slate-900/30">
                            <span class="block text-[9px] font-bold text-slate-400 uppercase">JENIS KONTRAK</span>
                            <span class="font-semibold text-slate-900 dark:text-slate-100 mt-1 block searchable-text">Rutin Bulanan</span>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="p-4 text-center text-slate-400">
                        Belum ada paket internet yang terpilih.
                    </div>
                    <?php endif; ?>

                    <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-4 bg-slate-50/50 dark:bg-slate-900/30">
                        <span class="block text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-2">INTEGRASI TEKNIS JARINGAN</span>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3 font-mono text-xs">
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">ONT Serial Number</span>
                                <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($customer->ont_sn ?? 'Belum terpasang'); ?></span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Nama Perangkat OLT</span>
                                <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($customer->olt_code ?? 'Belum diisi'); ?></span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Nomor OLT [CID Generator]</span>
                                <span class="font-bold text-sky-600 searchable-text"><?php echo e($displayId); ?></span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Kode Kotak ODP</span>
                                <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($customer->odp_code ?? 'Belum terhubung'); ?></span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">VLAN ID Jaringan</span>
                                <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($customer->vlan_id ?? 'Belum ditentukan'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- TAB 9: BILLING -->
        <div id="tab-content-billing" class="tab-content hidden space-y-6 searchable-section">
            <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
                <div class="px-5 py-3.5 bg-slate-50/50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700">
                    <span class="text-xs font-bold text-slate-900 dark:text-slate-100">Rincian Biaya Bulanan, Biaya Pemasangan Awal & Billing Cycle</span>
                </div>
                <div class="p-5 space-y-6 text-xs">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-4">BREAKDOWN BIAYA NETT BULANAN</span>
                        <div class="space-y-3 font-mono text-xs">
                            <div class="flex justify-between text-slate-500">
                                <span>Harga Paket Internet</span>
                                <span>Rp <?php echo e(number_format($monthlyPrice, 2, ',', '.')); ?></span>
                            </div>
                            <div class="flex justify-between text-emerald-600 font-semibold">
                                <span>Potongan Diskon</span>
                                <span>- Rp <?php echo e(number_format($discount, 2, ',', '.')); ?></span>
                            </div>
                            <div class="flex justify-between text-slate-500">
                                <span>PPN (<?php echo e(number_format($ppnPercent, 0)); ?>%)</span>
                                <span>Rp <?php echo e(number_format($ppnAmount, 2, ',', '.')); ?></span>
                            </div>
                            <?php if($otherFee > 0): ?>
                            <div class="flex justify-between text-slate-500">
                                <span>Biaya Lain</span>
                                <span>Rp <?php echo e(number_format($otherFee, 2, ',', '.')); ?></span>
                            </div>
                            <?php endif; ?>
                            <hr class="border-dashed border-slate-200 dark:border-slate-700">
                            <div class="flex justify-between text-xs font-bold text-slate-900 dark:text-slate-100">
                                <span>Total Biaya Per Bulan</span>
                                <span class="text-sky-600">Rp <?php echo e(number_format($totalBill, 2, ',', '.')); ?></span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-4">MASA AKTIF & PERIODE</span>
                        <div class="space-y-3 text-xs">
                            <div class="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Tanggal Aktivasi Layanan</span>
                                <span class="font-mono font-semibold text-slate-900 dark:text-slate-100 searchable-text">
                                    <?php echo e($customer->customerService?->activation_date ? \App\Support\IndonesianDate::date($customer->customerService->activation_date) : 'Belum diaktivasi'); ?>

                                </span>
                            </div>
                            <?php if($customer->customerService?->activation_time): ?>
                            <div class="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Waktu Aktivasi</span>
                                <span class="font-mono font-semibold text-slate-900 dark:text-slate-100 searchable-text">
                                    <?php echo e(substr($customer->customerService->activation_time, 0, 5)); ?> WIB
                                </span>
                            </div>
                            <?php endif; ?>
                            <?php if($customer->customerService?->activated_by_name || $customer->customerService?->activatedBy): ?>
                            <div class="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Petugas Aktivasi</span>
                                <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text">
                                    <?php echo e($customer->customerService->activatedBy->name ?? $customer->customerService->activated_by_name ?? '-'); ?>

                                </span>
                            </div>
                            <?php endif; ?>
                            <div class="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Tanggal Jatuh Tempo Bulanan</span>
                                <span class="font-mono font-semibold text-slate-900 dark:text-slate-100 searchable-text">
                                    <?php echo e($customer->customerService?->due_date ? \App\Support\IndonesianDate::date($customer->customerService->due_date) : 'Belum ditentukan'); ?>

                                </span>
                            </div>
                            <div class="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Siklus Billing</span>
                                <span class="font-semibold text-slate-900 dark:text-slate-100 capitalize searchable-text">
                                    <?php echo e($customer->customerService?->billing_cycle ?? 'Monthly'); ?>

                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                
                <?php
                    $initialInvoice = $customer->invoices->first(fn ($inv) => in_array($inv->invoice_type?->value, ['awal', 'reaktivasi'], true));
                ?>
                <?php if($initialInvoice): ?>
                    <?php
                        $initialRows = [
                            ['label' => 'Tagihan Prorate Bulan Pertama', 'column' => 'prorate_amount — proporsional dari tanggal aktivasi sampai akhir periode', 'value' => (float) $initialInvoice->prorate_amount],
                            ['label' => 'Jasa Instalasi & Pemasangan', 'column' => 'extra_installation_fee — default Master Paket + tambahan setting perangkat', 'value' => (float) $initialInvoice->extra_installation_fee],
                            ['label' => 'Biaya Kabel Tambahan', 'column' => 'extra_cable_fee — kabel FO di luar jarak standar', 'value' => (float) $initialInvoice->extra_cable_fee],
                            ['label' => 'Tambahan Tiang', 'column' => 'extra_pole_fee — tiang galvanis penambat udara', 'value' => (float) $initialInvoice->extra_pole_fee],
                            ['label' => 'Biaya Lain-lain', 'column' => 'other_fee', 'value' => (float) $initialInvoice->other_fee],
                        ];
                    ?>
                    <div class="pt-4 border-t border-slate-200 dark:border-slate-700">
                        <div class="flex flex-wrap gap-2 items-center justify-between mb-3">
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                RINCIAN TAGIHAN AWAL / REGISTRASI (<?php echo e($initialInvoice->invoice_number); ?>)
                            </span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wide <?php echo e($initialInvoice->invoice_status->value === 'lunas' ? 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800' : 'bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 border-amber-200 dark:border-amber-800'); ?>">
                                <?php echo e($initialInvoice->invoice_status->label()); ?>

                            </span>
                        </div>
                        <div class="overflow-x-auto border border-slate-200 dark:border-slate-700 rounded-lg">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="bg-slate-50/60 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-700 text-[10px] font-bold text-slate-400 uppercase">
                                        <th class="px-4 py-2">Komponen Biaya Awal</th>
                                        <th class="px-4 py-2">Kolom di Sistem</th>
                                        <th class="px-4 py-2 text-right">Nominal Biaya</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-slate-700 font-mono">
                                    <?php $__currentLoopData = $initialRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <?php if($row['value'] > 0): ?>
                                            <tr>
                                                <td class="px-4 py-2 font-sans font-semibold text-slate-900 dark:text-slate-100"><?php echo e($row['label']); ?></td>
                                                <td class="px-4 py-2 font-sans text-slate-500"><?php echo e($row['column']); ?></td>
                                                <td class="px-4 py-2 text-right font-bold text-slate-900 dark:text-slate-100 searchable-text">Rp <?php echo e(number_format($row['value'], 2, ',', '.')); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    <tr class="bg-slate-50/40 dark:bg-slate-900/30">
                                        <td class="px-4 py-2 font-sans font-semibold text-slate-900 dark:text-slate-100">Subtotal</td>
                                        <td class="px-4 py-2 font-sans text-slate-500">subtotal</td>
                                        <td class="px-4 py-2 text-right font-bold text-slate-900 dark:text-slate-100 searchable-text">Rp <?php echo e(number_format((float) $initialInvoice->subtotal, 2, ',', '.')); ?></td>
                                    </tr>
                                    <tr class="text-emerald-600 dark:text-emerald-400">
                                        <td class="px-4 py-2 font-sans font-semibold">Potongan Diskon</td>
                                        <td class="px-4 py-2 font-sans text-slate-500">discount</td>
                                        <td class="px-4 py-2 text-right font-bold searchable-text">- Rp <?php echo e(number_format((float) $initialInvoice->discount, 2, ',', '.')); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-2 font-sans font-semibold text-slate-900 dark:text-slate-100">PPN</td>
                                        <td class="px-4 py-2 font-sans text-slate-500">ppn</td>
                                        <td class="px-4 py-2 text-right <?php echo e((float) $initialInvoice->ppn > 0 ? 'font-bold text-slate-900 dark:text-slate-100' : 'font-sans text-slate-400'); ?> searchable-text">
                                            <?php echo e((float) $initialInvoice->ppn > 0 ? 'Rp '.number_format((float) $initialInvoice->ppn, 2, ',', '.') : 'Tidak dikenakan'); ?>

                                        </td>
                                    </tr>
                                    <tr class="bg-slate-100/60 dark:bg-slate-700/60 font-bold">
                                        <td class="px-4 py-2 font-sans text-slate-900 dark:text-slate-100" colspan="2">TOTAL PEMBAYARAN REGISTRASI AWAL (total_amount)</td>
                                        <td class="px-4 py-2 text-right text-sky-600 dark:text-sky-400 text-sm searchable-text">Rp <?php echo e(number_format((float) $initialInvoice->total_amount, 2, ',', '.')); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- TAB 10: TAGIHAN -->
        <div id="tab-content-tagihan" class="tab-content hidden space-y-6 searchable-section">
            <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-700">
                <div>
                    <h3 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">Riwayat Tagihan Pelanggan</h3>
                    <p class="text-[11px] text-slate-500 mt-0.5">Daftar invoice tagihan bulanan yang diterbitkan secara manual maupun sistem.</p>
                </div>
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('invoices.create')): ?>
                    <?php if($isActive && $customer->customerService): ?>
                        <button type="button" onclick="openInvoiceModal()" class="px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white rounded text-xs font-semibold shadow-sm cursor-pointer">
                            + Buat Tagihan Manual
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if($customer->invoices && $customer->invoices->count() > 0): ?>
                <?php
                    $invoicesAwal = $customer->invoices->filter(fn($inv) => in_array($inv->invoice_type?->value, ['awal', 'reaktivasi'], true));
                    $invoicesBulanan = $customer->invoices->filter(fn($inv) => $inv->invoice_type?->value === 'bulanan');
                ?>

                <?php $__currentLoopData = [
                    ['title' => 'Tagihan Awal / Registrasi', 'rows' => $invoicesAwal, 'badge' => 'bg-amber-50 text-amber-600 border-amber-200'],
                    ['title' => 'Tagihan Bulanan', 'rows' => $invoicesBulanan, 'badge' => 'bg-sky-50 text-sky-600 border-sky-200'],
                ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="<?php echo e(!$loop->first ? 'mt-6' : ''); ?>">
                        <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2"><?php echo e($group['title']); ?> (<?php echo e($group['rows']->count()); ?>)</h4>
                        <?php if($group['rows']->count() > 0): ?>
                            <div class="overflow-x-auto border border-slate-200 dark:border-slate-700 rounded-lg shadow-sm">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="bg-slate-50/60 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-700 font-semibold text-slate-400 uppercase text-[10px]">
                                            <th class="px-4 py-3">No. Tagihan</th>
                                            <th class="px-4 py-3">Jenis</th>
                                            <th class="px-4 py-3">Periode</th>
                                            <th class="px-4 py-3">Tanggal Terbit</th>
                                            <th class="px-4 py-3">Jatuh Tempo</th>
                                            <th class="px-4 py-3 text-right">Total Tagihan</th>
                                            <th class="px-4 py-3 text-center">Status</th>
                                            <th class="px-4 py-3">Dibuat Oleh</th>
                                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('payments.create')): ?>
                                                <th class="px-4 py-3 text-center">Aksi</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700 text-slate-700 dark:text-slate-300 font-mono">
                                        <?php $__currentLoopData = $group['rows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors" id="invoice-row-<?php echo e($invoice->id); ?>">
                                                <td class="px-4 py-3 font-bold text-slate-900 dark:text-slate-100 searchable-text">
                                                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('invoices.view')): ?>
                                                        <a href="<?php echo e(route('invoices.show', $invoice->id)); ?>" class="text-sky-600 hover:underline"><?php echo e($invoice->invoice_number); ?></a>
                                                    <?php else: ?>
                                                        <?php echo e($invoice->invoice_number); ?>

                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-4 py-3 font-sans">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wide <?php echo e($group['badge']); ?>">
                                                        <?php echo e($invoice->invoice_type?->label() ?? ucfirst((string) $invoice->invoice_type)); ?>

                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 searchable-text"><?php echo e($invoice->billing_period); ?></td>
                                                <td class="px-4 py-3 font-sans searchable-text"><?php echo e(\App\Support\IndonesianDate::date($invoice->issue_date)); ?></td>
                                                <td class="px-4 py-3 font-sans searchable-text"><?php echo e(\App\Support\IndonesianDate::date($invoice->due_date)); ?></td>
                                                <td class="px-4 py-3 text-right font-semibold searchable-text">Rp <?php echo e(number_format($invoice->total_amount, 2, ',', '.')); ?></td>
                                                <td class="px-4 py-3 text-center font-sans">
                                                    <?php
                                                        $statusClass = match($invoice->invoice_status->value) {
                                                            'lunas' => 'bg-emerald-50 text-emerald-600 border-emerald-200',
                                                            'sebagian' => 'bg-sky-50 text-sky-600 border-sky-200',
                                                            'belum_dibayar' => 'bg-amber-50 text-amber-600 border-amber-200',
                                                            'batal' => 'bg-rose-50 text-rose-600 border-rose-200',
                                                            default => 'bg-slate-100 text-slate-600 border-slate-200',
                                                        };
                                                        $statusLabel = $invoice->invoice_status->label();
                                                    ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wide <?php echo e($statusClass); ?>" id="invoice-status-badge-<?php echo e($invoice->id); ?>">
                                                        <?php echo e($statusLabel); ?>

                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-slate-400 font-sans searchable-text"><?php echo e($invoice->creator->name ?? 'System'); ?></td>
                                                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('payments.create')): ?>
                                                    <?php $settled = in_array($invoice->invoice_status->value, ['lunas', 'batal'], true); ?>
                                                    <td class="px-4 py-3 text-center font-sans" id="invoice-pay-btn-<?php echo e($invoice->id); ?>">
                                                        <button type="button"
                                                                data-invoice-id="<?php echo e($invoice->id); ?>"
                                                                data-invoice-number="<?php echo e($invoice->invoice_number); ?>"
                                                                data-remaining="<?php echo e((float) $invoice->remaining_amount); ?>"
                                                                
                                                                data-payment-store-url="<?php echo e(route('invoices.payments.store', $invoice->id)); ?>"
                                                                onclick="openQuickPaymentModal(parseInt(this.dataset.invoiceId, 10), this.dataset.invoiceNumber, parseFloat(this.dataset.remaining), this.dataset.paymentStoreUrl)"
                                                                class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-[10px] font-bold uppercase tracking-wide shadow-sm cursor-pointer <?php echo e($settled ? 'hidden' : ''); ?>">
                                                            Bayar
                                                        </button>
                                                        <span class="text-slate-400 text-[10px] <?php echo e($settled ? '' : 'hidden'); ?>">—</span>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="py-6 text-center text-slate-400 bg-slate-50/50 dark:bg-slate-900/30 border border-dashed border-slate-200 dark:border-slate-700 rounded-lg text-xs">
                                Belum ada <?php echo e(strtolower($group['title'])); ?>.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php else: ?>
                <div class="py-12 text-center text-slate-400 bg-slate-50/50 dark:bg-slate-900/30 border border-dashed border-slate-200 dark:border-slate-700 rounded-lg">
                    <i class="fa-solid fa-file-invoice text-3xl mb-2 text-slate-300"></i>
                    <h4 class="text-xs font-bold text-slate-900 dark:text-slate-100">Belum Ada Tagihan Terbit</h4>
                    <p class="text-[11px] text-slate-500 mt-1">Gunakan tombol "Buat Tagihan Manual" untuk membuat invoice pertama pelanggan.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB 11: PEMBAYARAN -->
        <div id="tab-content-pembayaran" class="tab-content hidden space-y-6 searchable-section">
            <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-700">
                <div>
                    <h3 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">Riwayat Pembayaran Pelanggan</h3>
                    <p class="text-[11px] text-slate-500 mt-0.5">Pembayaran yang terhubung ke invoice pelanggan ini.</p>
                </div>
            </div>

            <?php if($customer->payments && $customer->payments->count() > 0): ?>
                <div class="overflow-x-auto border border-slate-200 dark:border-slate-700 rounded-lg shadow-sm">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50/60 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-700 font-semibold text-slate-400 uppercase text-[10px]">
                                <th class="px-4 py-3">No. Pembayaran</th>
                                <th class="px-4 py-3">No. Tagihan</th>
                                <th class="px-4 py-3">Tanggal</th>
                                <th class="px-4 py-3">Metode</th>
                                <th class="px-4 py-3 text-right">Nominal</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Bukti</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700 font-mono">
                            <?php $__currentLoopData = $customer->payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors">
                                    <td class="px-4 py-3 font-bold text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($payment->payment_number); ?></td>
                                    <td class="px-4 py-3 searchable-text">
                                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('invoices.view')): ?>
                                            <a href="<?php echo e(route('invoices.show', $payment->invoice_id)); ?>" class="text-sky-600 hover:underline"><?php echo e($payment->invoice->invoice_number ?? '-'); ?></a>
                                        <?php else: ?>
                                            <?php echo e($payment->invoice->invoice_number ?? '-'); ?>

                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 font-sans searchable-text"><?php echo e(\App\Support\IndonesianDate::date($payment->payment_date)); ?></td>
                                    <td class="px-4 py-3 font-sans uppercase searchable-text"><?php echo e(strtoupper($payment->payment_method->value ?? (string)$payment->payment_method)); ?></td>
                                    <td class="px-4 py-3 text-right font-semibold text-slate-900 dark:text-slate-100 searchable-text">
                                        Rp <?php echo e(number_format((float) $payment->amount, 2, ',', '.')); ?>

                                        <?php if((float) $payment->overpay_amount > 0): ?>
                                            <span class="block text-[10px] font-semibold text-sky-600 dark:text-sky-400" title="Uang lebih yang diserahkan pelanggan — catatan saja, tidak menambah pembayaran tagihan">
                                                +<?php echo e(number_format((float) $payment->overpay_amount, 0, ',', '.')); ?> lebih
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 font-sans">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wide bg-emerald-50 text-emerald-600 border-emerald-200">
                                            <?php echo e($payment->payment_status->label()); ?>

                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-sans">
                                        <?php if($payment->proof_file): ?>
                                            <a href="<?php echo e(asset('storage/' . $payment->proof_file)); ?>" target="_blank" class="text-sky-600 hover:underline font-semibold">Lihat bukti ↗</a>
                                        <?php else: ?>
                                            <span class="text-slate-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="py-12 text-center text-slate-400 bg-slate-50/50 dark:bg-slate-900/30 border border-dashed border-slate-200 dark:border-slate-700 rounded-lg">
                    <i class="fa-solid fa-receipt text-3xl mb-2 text-slate-300"></i>
                    <h4 class="text-xs font-bold text-slate-900 dark:text-slate-100">Belum Ada Riwayat Pembayaran</h4>
                    <p class="text-[11px] text-slate-500 mt-1">Pembayaran akan tampil setelah kasir/finance mencatat pembayaran invoice.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB 12: DOKUMEN & BERKAS -->
        <div id="tab-content-dokumen" class="tab-content hidden space-y-6 searchable-section">
            <?php if(auth()->user()->hasPermission('customers.detail.documents.view')): ?>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">LAMPIRAN DOKUMEN PENDUKUNG</h3>
                        <p class="text-[11px] text-slate-500 mt-0.5">Dokumen disimpan aman & private sesuai hak akses permission sistem.</p>
                    </div>
                    <?php if(auth()->user()->hasPermission('upload_customer_documents')): ?>
                    <button type="button" onclick="openModal('document-upload-modal')" class="px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white rounded text-xs font-semibold cursor-pointer">
                        + Upload Dokumen Baru
                    </button>
                    <?php endif; ?>
                </div>

                <?php if($customer->documents->isNotEmpty()): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php $__currentLoopData = $customer->documents->sortByDesc('created_at'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $document): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden flex flex-col justify-between hover:border-sky-500 transition-colors shadow-sm bg-white dark:bg-slate-800">
                                <div class="p-4 bg-slate-50/50 dark:bg-slate-900/50 flex items-center justify-center h-36 border-b border-slate-200 dark:border-slate-700">
                                    <?php if($document->isImage()): ?>
                                        <img src="<?php echo e(route('customers.documents.show', $document->id)); ?>" alt="<?php echo e($document->typeLabel()); ?>" class="max-h-28 max-w-full rounded object-contain shadow-sm">
                                    <?php else: ?>
                                        <div class="h-24 w-24 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 rounded-lg flex flex-col items-center justify-center text-rose-600 shadow-sm">
                                            <i class="fa-solid fa-file-pdf text-3xl mb-1"></i>
                                            <span class="text-[10px] font-bold">PDF FILE</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="p-3 flex items-center justify-between gap-3 text-xs">
                                    <div class="min-w-0">
                                        <span class="block text-xs font-semibold text-slate-900 dark:text-slate-100 truncate searchable-text"><?php echo e($document->typeLabel()); ?></span>
                                        <span class="block text-[10px] text-slate-400 font-mono mt-0.5"><?php echo e($document->created_at?->format('d/m/Y H:i')); ?></span>
                                        <span class="block text-[10px] text-slate-400 truncate">Upload: <?php echo e($document->uploader?->name ?? '-'); ?></span>
                                    </div>
                                    <a href="<?php echo e(route('customers.documents.show', $document->id)); ?>" target="_blank" class="shrink-0 p-1.5 text-sky-600 hover:bg-slate-100 dark:hover:bg-slate-700 rounded" title="Buka Dokumen">
                                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                <?php else: ?>
                    <div class="py-12 text-center text-slate-400 bg-slate-50/50 dark:bg-slate-900/30 border border-dashed border-slate-200 dark:border-slate-700 rounded-lg">
                        <i class="fa-solid fa-folder-open text-3xl mb-2 text-slate-300"></i>
                        <h4 class="text-xs font-bold text-slate-900 dark:text-slate-100">Belum Ada Dokumen</h4>
                        <p class="text-[11px] text-slate-500 mt-1">Dokumen rumah, kontrak, survey, dan foto pemasangan akan tampil di sini setelah diupload.</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="py-12 text-center text-slate-400 bg-slate-50/50 dark:bg-slate-900/30 border border-dashed border-slate-200 dark:border-slate-700 rounded-lg">
                    <h4 class="text-xs font-bold text-slate-900 dark:text-slate-100">Akses dokumen dibatasi</h4>
                    <p class="text-[11px] text-slate-500 mt-1">User Anda tidak memiliki permission untuk melihat dokumen pelanggan.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB 13: RIWAYAT TICKETING -->
        <div id="tab-content-riwayat-ticketing" class="tab-content hidden space-y-6 searchable-section">
            <?php echo $__env->make('customers.tabs._riwayat_ticketing', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>

        <!-- TAB 14: RIWAYAT PERUBAHAN -->
        <div id="tab-content-riwayat-perubahan" class="tab-content hidden space-y-6 searchable-section">
            <?php echo $__env->make('customers.tabs._riwayat_perubahan', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>

        <!-- TAB 15: DETAIL TEKNIS LAMA -->
        <?php if($customer->customerTechnicalDetail): ?>
        <div id="tab-content-teknis-lama" class="tab-content hidden space-y-6 searchable-section">
            <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
                <div class="px-5 py-3.5 bg-slate-50/50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700">
                    <span class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">Detail Teknis Jaringan Lama (Hasil Migrasi Database)</span>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 text-xs">
                    <div class="py-2 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">ID Report Lama</span>
                        <span class="font-mono font-bold text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($customer->customerTechnicalDetail->old_report_id ?? '-'); ?></span>
                    </div>
                    <div class="py-2 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">ID Request/Layanan Lama</span>
                        <span class="font-mono font-semibold text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($customer->customerTechnicalDetail->old_request_id ?? '-'); ?></span>
                    </div>
                    <div class="py-2 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Tipe Koneksi Jaringan</span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($customer->customerTechnicalDetail->connection_type ?? '-'); ?></span>
                    </div>
                    <div class="py-2 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">ONT Serial Number</span>
                        <span class="font-mono font-semibold text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($customer->customerTechnicalDetail->router_or_ont_serial ?? '-'); ?></span>
                    </div>
                    <div class="py-2 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Nomor ODP / Port</span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($customer->customerTechnicalDetail->odp_number ?? '-'); ?> / <?php echo e($customer->customerTechnicalDetail->odp_port ?? '-'); ?></span>
                    </div>
                    <div class="py-2 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Redaman Optik Kabel</span>
                        <span class="font-mono font-bold text-sky-600 searchable-text"><?php echo e($customer->customerTechnicalDetail->fiber_signal ?? '-'); ?></span>
                    </div>
                    <div class="py-2 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Hasil Test Speed</span>
                        <span class="font-mono font-bold text-emerald-600 searchable-text"><?php echo e($customer->customerTechnicalDetail->test_download ?? '-'); ?> Mbps Down / <?php echo e($customer->customerTechnicalDetail->test_upload ?? '-'); ?> Mbps Up</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- TOAST NOTIFICATION -->
<div id="toast" class="fixed bottom-5 right-5 z-50 bg-slate-900 text-white text-xs px-4 py-2.5 rounded-lg shadow-lg flex items-center gap-2 transition-all duration-300 translate-y-10 opacity-0 pointer-events-none">
    <i class="fa-solid fa-circle-check text-emerald-400"></i>
    <span id="toast-message">Disalin!</span>
</div>

<!-- MODALS SECTION -->
<!-- MODAL: Manual Invoice -->
<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('invoices.create')): ?>
    <?php if($isActive && $customer->customerService): ?>
        <?php
            $defaultPeriod = now()->format('Y-m');
            $defaultIssueDate = now()->format('Y-m-d');
            $defaultDueDate = now()->addDays(14)->format('Y-m-d');
            if ($customer->customerService->due_date) {
                $dueDay = \Carbon\Carbon::parse($customer->customerService->due_date)->day;
                try {
                    $defaultDueDate = now()->day($dueDay)->format('Y-m-d');
                } catch (\Exception $e) {
                    $defaultDueDate = now()->addDays(14)->format('Y-m-d');
                }
            }
        ?>
        <div id="manual-invoice-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700 w-full max-w-md overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <h3 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">Buat Tagihan Manual</h3>
                    <button type="button" onclick="closeInvoiceModal()" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                        <i class="fa-solid fa-xmark text-base"></i>
                    </button>
                </div>
                <form action="<?php echo e(route('customers.invoices.manual', $customer->id)); ?>" method="POST">
                    <?php echo csrf_field(); ?>
                    <div class="p-6 space-y-4 text-xs">
                        <div class="p-3 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg">
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Pelanggan: <?php echo e($customer->full_name); ?></span>
                            <span class="text-xs font-bold text-slate-900 dark:text-slate-100"><?php echo e($customer->customerService->package_name_snapshot); ?> (Rp <?php echo e(number_format($totalBill, 0, ',', '.')); ?>)</span>
                        </div>
                        <div>
                            <label for="billing_period" class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Periode Tagihan</label>
                            <input type="month" name="billing_period" id="billing_period" value="<?php echo e($defaultPeriod); ?>" required class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg font-mono text-xs text-slate-800 dark:text-slate-200">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Tanggal Terbit & Jatuh Tempo</label>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="date" name="issue_date" id="issue_date" value="<?php echo e($defaultIssueDate); ?>" required class="px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg font-mono text-xs text-slate-800 dark:text-slate-200">
                                <input type="date" name="due_date" id="due_date" value="<?php echo e($defaultDueDate); ?>" required class="px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg font-mono text-xs text-slate-800 dark:text-slate-200">
                            </div>
                        </div>
                        <div>
                            <label for="invoice_type" class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Jenis Tagihan</label>
                            <select name="invoice_type" id="invoice_type" required class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg font-semibold text-xs text-slate-800 dark:text-slate-200">
                                <option value="bulanan" <?php echo e($customer->invoices->count() > 0 ? 'selected' : ''); ?>>Tagihan Bulanan Rutin</option>
                                <option value="awal" <?php echo e($customer->invoices->count() === 0 ? 'selected' : ''); ?>>Tagihan Awal (PSB)</option>
                                <option value="reaktivasi">Tagihan Reaktivasi</option>
                            </select>
                        </div>
                        <div>
                            <label for="prorate_amount" class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Tagihan Prorate (Opsional)</label>
                            
                            <input type="text" inputmode="decimal" data-rupiah name="prorate_amount" id="prorate_amount" value="0" oninput="recalcInvoiceTotal()" class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg font-mono text-xs text-slate-800 dark:text-slate-200">
                        </div>
                        <div class="flex justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-700">
                            <button type="button" onclick="closeInvoiceModal()" class="px-4 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-700 dark:text-slate-300 cursor-pointer">Batal</button>
                            <button type="submit" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white font-semibold rounded-lg shadow-sm cursor-pointer">Proses Tagihan</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- MODAL: Document Upload Modal -->
<?php if(auth()->user()->hasPermission('upload_customer_documents')): ?>
<div id="document-upload-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700 w-full max-w-md overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
            <h3 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">Upload Dokumen Pelanggan Baru</h3>
            <button type="button" onclick="closeModal('document-upload-modal')" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>
        <form method="POST" action="<?php echo e(route('customers.documents.store', ['customer' => $customer->id])); ?>" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <div class="p-5 space-y-4 text-xs">
                <div>
                    <label for="document_type" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Jenis Dokumen</label>
                    <select name="document_type" id="document_type" class="w-full text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200" required>
                        <?php $__currentLoopData = \App\Enums\DocumentType::cases(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($type->value); ?>"><?php echo e($type->label()); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div>
                    <label for="document_file" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">File Gambar / PDF</label>
                    <input type="file" name="document_file" id="document_file" accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf" class="w-full text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200" required>
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-700">
                    <button type="button" onclick="closeModal('document-upload-modal')" class="px-4 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-700 dark:text-slate-300 cursor-pointer">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white font-semibold rounded-lg shadow-sm cursor-pointer">Upload File</button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php echo $__env->make('payments.partials.quick-payment-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>


<?php $__env->startPush('scripts'); ?>
    <script>
        const CUSTOMER_INVOICE_STATUS_LABELS = {
            belum_dibayar: 'Belum Dibayar',
            sebagian: 'Sebagian',
            lunas: 'Lunas',
            batal: 'Batal',
        };

        const CUSTOMER_INVOICE_STATUS_BADGE_CLASSES = {
            lunas: 'bg-emerald-50 text-emerald-600 border-emerald-200',
            sebagian: 'bg-sky-50 text-sky-600 border-sky-200',
            belum_dibayar: 'bg-amber-50 text-amber-600 border-amber-200',
            batal: 'bg-rose-50 text-rose-600 border-rose-200',
        };

        function applyCustomerInvoiceUpdate(data) {
            const row = document.getElementById('invoice-row-' + data.invoice_id);
            if (!row) {
                return false;
            }

            const badge = document.getElementById('invoice-status-badge-' + data.invoice_id);
            if (badge) {
                badge.textContent = data.invoice_status_label || CUSTOMER_INVOICE_STATUS_LABELS[data.invoice_status] || data.invoice_status;
                badge.className = 'inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wide ' +
                    (CUSTOMER_INVOICE_STATUS_BADGE_CLASSES[data.invoice_status] || CUSTOMER_INVOICE_STATUS_BADGE_CLASSES.belum_dibayar);
            }

            const payCell = document.getElementById('invoice-pay-btn-' + data.invoice_id);
            if (payCell) {
                const settled = data.invoice_status === 'lunas' || data.invoice_status === 'batal';
                const btn = payCell.querySelector('button');
                const dash = payCell.querySelector('span');
                if (btn) {
                    btn.classList.toggle('hidden', settled);
                    if (!settled) {
                        btn.dataset.remaining = data.remaining_amount;
                    }
                }
                if (dash) {
                    dash.classList.toggle('hidden', !settled);
                }
            }

            return true;
        }

        // Aksi sendiri lewat modal Bayar Cepat.
        document.addEventListener('payment-recorded', function (e) {
            if (applyCustomerInvoiceUpdate(e.detail)) {
                e.preventDefault();
            }
        });

        // Aksi user lain di POP yang sama, lewat Reverb.
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof window.Echo === 'undefined' || !window.Echo) {
                return;
            }

            window.Echo.private('invoices.<?php echo e($customer->pop_id); ?>')
                .listen('.InvoiceStatusUpdated', applyCustomerInvoiceUpdate);
        });
    </script>
<?php $__env->stopPush(); ?>

<!-- MODAL: Network Assignment -->
<?php if(auth()->user()->hasPermission('customers.detail.installation.validate')): ?>
<?php if (isset($component)) { $__componentOriginal7762953202be6518eecd1cfbd075bf2f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7762953202be6518eecd1cfbd075bf2f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.modal','data' => ['name' => 'network-assignment','title' => 'Atur Mini POP & Distribusi','maxWidth' => 'sm']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'network-assignment','title' => 'Atur Mini POP & Distribusi','maxWidth' => 'sm']); ?>
    <div x-data="{ miniPopId: '<?php echo e(old('mini_pop_id', $customer->mini_pop_id)); ?>' }">
        <div class="pb-3 mb-4 border-b border-slate-200 dark:border-slate-700 space-y-1">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Pelanggan</span>
                    <span class="text-sm font-bold text-slate-900 dark:text-slate-100"><?php echo e($customer->full_name); ?></span>
                </div>
                <div class="text-right">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">ID Jaringan</span>
                    <span class="font-mono text-xs text-sky-600 font-bold bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded"><?php echo e($displayId); ?></span>
                </div>
            </div>
            <div class="text-xs text-slate-500 pt-1 flex items-center gap-1">
                <span>Cabang: <strong class="text-slate-700 dark:text-slate-300 font-semibold"><?php echo e($customer->pop->name ?? '-'); ?> (<?php echo e($customer->pop->pop_code ?? ''); ?>)</strong></span>
            </div>
        </div>

        <form action="<?php echo e(route('customers.network-assignment.update', $customer)); ?>" method="POST" class="space-y-4">
            <?php echo csrf_field(); ?>
            <?php echo method_field('PUT'); ?>

            <div class="space-y-1.5">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Mini POP (OLT)</label>
                <select name="mini_pop_id" x-model="miniPopId" class="w-full text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 outline-none cursor-pointer">
                    <option value="">— Belum di-assign —</option>
                    <?php $__currentLoopData = $availableMiniPops; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $miniPop): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($miniPop->id); ?>">[<?php echo e($miniPop->pop_code); ?>] <?php echo e($miniPop->name); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
                <?php if($availableMiniPops->isEmpty()): ?>
                    <div class="text-[11px] text-amber-600 mt-1">
                        Belum ada Mini POP terdaftar di bawah Cabang POP ini.
                    </div>
                <?php endif; ?>
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Distribusi</label>
                <select name="distribution_id" class="w-full text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 outline-none cursor-pointer">
                    <option value="">— Belum di-assign —</option>
                    <?php $__currentLoopData = $availableDistributions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dist): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($dist->id); ?>"
                                x-show="miniPopId == <?php echo e($dist->pop_id); ?>"
                                <?php echo e(old('distribution_id', $customer->distribution_id) == $dist->id ? 'selected' : ''); ?>>
                            [<?php echo e($dist->code); ?>] <?php echo e($dist->name); ?>

                        </option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
                <p class="text-[11px] text-slate-400">Daftar Distribusi otomatis mengikuti Mini POP yang dipilih di atas.</p>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-slate-200 dark:border-slate-700 mt-4">
                <button type="button" @click="$dispatch('close-modal', 'network-assignment')"
                        class="text-xs font-semibold px-4 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 cursor-pointer">Batal</button>
                <button type="submit" class="text-xs font-semibold px-5 py-2 rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm cursor-pointer">Simpan</button>
            </div>
        </form>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7762953202be6518eecd1cfbd075bf2f)): ?>
<?php $attributes = $__attributesOriginal7762953202be6518eecd1cfbd075bf2f; ?>
<?php unset($__attributesOriginal7762953202be6518eecd1cfbd075bf2f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7762953202be6518eecd1cfbd075bf2f)): ?>
<?php $component = $__componentOriginal7762953202be6518eecd1cfbd075bf2f; ?>
<?php unset($__componentOriginal7762953202be6518eecd1cfbd075bf2f); ?>
<?php endif; ?>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
<script>
    let currentViewMode = 'tabs'; // 'tabs' or 'all'

    function switchTab(tabId) {
        if (currentViewMode === 'all') {
            setViewMode('tabs');
        }

        // Hide all tab panels
        document.querySelectorAll('.tab-content').forEach(el => {
            el.classList.add('hidden');
        });
        
        // Show active tab panel
        const activeTab = document.getElementById('tab-content-' + tabId);
        if (activeTab) {
            activeTab.classList.remove('hidden');
        }

        // Reset active state for tab buttons
        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.classList.remove('border-sky-600', 'text-sky-600');
            btn.classList.add('border-transparent', 'text-slate-500');
        });

        // Add active state to clicked tab button
        const activeBtn = document.getElementById('tab-btn-' + tabId);
        if (activeBtn) {
            activeBtn.classList.add('border-sky-600', 'text-sky-600');
            activeBtn.classList.remove('border-transparent', 'text-slate-500');
            activeBtn.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }
    }

    function setViewMode(mode) {
        currentViewMode = mode;
        const btnTabs = document.getElementById('view-mode-tabs');
        const btnAll = document.getElementById('view-mode-all');
        const navWrapper = document.getElementById('tab-nav-wrapper');

        if (mode === 'all') {
            document.querySelectorAll('.tab-content').forEach(el => {
                el.classList.remove('hidden');
            });
            if (navWrapper) navWrapper.classList.add('hidden');

            if (btnAll) {
                btnAll.classList.add('bg-white', 'dark:bg-slate-800', 'text-sky-600', 'shadow-sm');
                btnAll.classList.remove('text-slate-600', 'dark:text-slate-400');
            }
            if (btnTabs) {
                btnTabs.classList.remove('bg-white', 'dark:bg-slate-800', 'text-sky-600', 'shadow-sm');
                btnTabs.classList.add('text-slate-600', 'dark:text-slate-400');
            }
        } else {
            if (navWrapper) navWrapper.classList.remove('hidden');
            if (btnTabs) {
                btnTabs.classList.add('bg-white', 'dark:bg-slate-800', 'text-sky-600', 'shadow-sm');
                btnTabs.classList.remove('text-slate-600', 'dark:text-slate-400');
            }
            if (btnAll) {
                btnAll.classList.remove('bg-white', 'dark:bg-slate-800', 'text-sky-600', 'shadow-sm');
                btnAll.classList.add('text-slate-600', 'dark:text-slate-400');
            }
            switchTab('ringkasan');
        }
    }

    function filterContent() {
        const query = document.getElementById('omni-search')?.value.toLowerCase().trim() || '';
        const clearBtn = document.getElementById('clear-search-btn');
        const sections = document.querySelectorAll('.searchable-section');

        if (query.length > 0) {
            if (clearBtn) clearBtn.classList.remove('hidden');
            if (currentViewMode === 'tabs') {
                document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('hidden'));
            }
        } else {
            if (clearBtn) clearBtn.classList.add('hidden');
            if (currentViewMode === 'tabs') {
                switchTab('ringkasan');
            }
        }

        sections.forEach(sec => {
            if (query === '') {
                if (currentViewMode === 'all') sec.classList.remove('hidden');
            } else {
                const text = sec.innerText.toLowerCase();
                if (text.includes(query)) {
                    sec.classList.remove('hidden');
                } else {
                    sec.classList.add('hidden');
                }
            }
        });
    }

    function clearSearch() {
        const input = document.getElementById('omni-search');
        if (input) {
            input.value = '';
            filterContent();
            input.focus();
        }
    }

    function copyText(text, label) {
        if (!text) return;
        navigator.clipboard.writeText(text).then(() => {
            const toast = document.getElementById('toast');
            const msg = document.getElementById('toast-message');
            if (msg) msg.innerText = label + ' disalin: ' + text;
            if (toast) {
                toast.classList.remove('translate-y-10', 'opacity-0', 'pointer-events-none');
                setTimeout(() => {
                    toast.classList.add('translate-y-10', 'opacity-0', 'pointer-events-none');
                }, 2500);
            }
        });
    }

    function openModal(id) { document.getElementById(id)?.classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id)?.classList.add('hidden'); }

    function openInvoiceModal() { openModal('manual-invoice-modal'); }
    function closeInvoiceModal() { closeModal('manual-invoice-modal'); }

    // Modal milik partial tab Pemasangan & Perangkat. Sebelumnya fungsi ini HANYA
    // ada di customers/fieldwork.blade.php, jadi tombol "Isi Data Pemasangan" /
    // "Isi Laporan Uji" / "Isi Ubah Data Perangkat" di halaman Detail Pelanggan
    // memanggil fungsi yang tidak pernah terdefinisi — klik tidak melakukan apa pun.
    function openInstallationModal() { openModal('installation-modal'); }
    function closeInstallationModal() { closeModal('installation-modal'); }
    function openTestReportModal() { openModal('test-report-modal'); }
    function closeTestReportModal() { closeModal('test-report-modal'); }
    function openDeviceModal() { openModal('device-modal'); }
    function closeDeviceModal() { closeModal('device-modal'); }

    const BASE_NETT = <?php echo e((float)$totalBill); ?>;
    function recalcInvoiceTotal() {
        // Kolom prorata bermasking ribuan — parseFloat('50.000') = 50, dan
        // pratinjau total tagihan akan berbohong tanpa parser ini.
        const prorateEl = document.getElementById('prorate_amount');
        const prorate = (prorateEl && window.Rupiah ? window.Rupiah.angka(prorateEl.value) : parseFloat(prorateEl?.value || 0)) || 0;
        const total   = BASE_NETT + prorate;
        const fmt = v => 'Rp ' + Math.round(v).toLocaleString('id-ID');
        const totalEl = document.getElementById('preview-total');
        if (totalEl) totalEl.textContent = fmt(total);
    }
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/customers/show.blade.php ENDPATH**/ ?>