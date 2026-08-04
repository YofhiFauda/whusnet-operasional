<?php
    $pageTitle = match ($statusGroup) {
        'failed' => 'Pelanggan Gagal',
        'terminated' => 'Pelanggan Putus',
        'survey' => 'Survey Pelanggan',
        'verification' => 'Verifikasi Pelanggan',
        default => 'List Pelanggan',
    };
?>

<?php $__env->startSection('title', $pageTitle . ' - Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', $pageTitle); ?>
<?php $__env->startSection('breadcrumb_parent', 'Pelanggan'); ?>
<?php $__env->startSection('breadcrumb_parent_url', '/customers'); ?>

<?php $__env->startSection('content'); ?>
<style>
    .toggle-checkbox:checked + .toggle-label .check-icon { display: block; }
    .toggle-checkbox:checked + .toggle-label .x-icon { display: none; }
    .toggle-checkbox:not(:checked) + .toggle-label .check-icon { display: none; }
    .toggle-checkbox:not(:checked) + .toggle-label .x-icon { display: block; }

    /* Custom scrollbar & mobile tab scrollbar */
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

    /* Compact table padding override */
    #customerTable tbody td { padding-top: 0.75rem; padding-bottom: 0.75rem; }
    html.density-compact #customerTable tbody td { padding-top: 0.4rem !important; padding-bottom: 0.4rem !important; }

    /* Baris aktif navigasi keyboard */
    #customerTable tbody tr.row-active { outline: 2px solid #0284c7; outline-offset: -2px; }
    html.dark #customerTable tbody tr.row-active { outline-color: #38bdf8; }

    /* Touch target minimum height for mobile accessibility */
    .touch-target { min-height: 40px; }

    /* Keyframe Animations */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(6px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes popIn {
        0% { opacity: 0; transform: scale(0.9); }
        65% { transform: scale(1.03); }
        100% { opacity: 1; transform: scale(1); }
    }
    @keyframes pulseGlow {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.6; transform: scale(1.25); }
    }
    .animate-fade-in { animation: fadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    .animate-pop-in { animation: popIn 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    .animate-pulse-glow { animation: pulseGlow 2s infinite ease-in-out; }

    .btn-interactive { transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1); }
    .btn-interactive:hover { transform: translateY(-1px); }
    .btn-interactive:active { transform: translateY(0) scale(0.98); }

    .card-interactive { transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1); }
    .card-interactive:hover { transform: translateY(-2px); }
</style>


<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-50 tracking-tight"><?php echo e($pageTitle); ?></h1>
        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">Kelola data pelanggan, jaringan distribusi, status layanan internet, dan penagihan.</p>
    </div>
    <div class="flex items-center gap-2.5 flex-wrap shrink-0">
        <?php if(auth()->user()->hasPermission('customers.import.view')): ?>
        <a href="/customers/import"
           class="h-9 px-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900
                  text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800
                  text-xs font-semibold inline-flex items-center gap-2 transition-all shadow-sm">
            <svg class="h-4 w-4 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
            <span>Import Pelanggan</span>
        </a>
        <?php endif; ?>
        <?php if(auth()->user()->hasPermission('customers.create')): ?>
        <a href="/customers/create"
           class="h-9 px-4 rounded-xl bg-sky-600 hover:bg-sky-700 text-white
                  text-xs font-semibold inline-flex items-center gap-2 transition-all shadow-md shadow-sky-600/20 active:scale-95">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            <span>Tambah Pelanggan</span>
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Modern Stats Summary Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
    <!-- Total -->
    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 shadow-sm flex items-center justify-between card-interactive">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Total Pelanggan</p>
            <h3 class="text-2xl font-bold text-slate-900 dark:text-white mt-1"><?php echo e(number_format($totalCustomers)); ?></h3>
            <p class="text-[10px] text-slate-500 mt-0.5">Terdaftar dalam sistem</p>
        </div>
        <div class="w-10 h-10 rounded-xl bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        </div>
    </div>

    <!-- Aktif -->
    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 shadow-sm flex items-center justify-between card-interactive">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Layanan Aktif</p>
            <h3 class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1"><?php echo e(number_format($statusCounts['active'] ?? 0)); ?></h3>
            <p class="text-[10px] text-slate-500 mt-0.5">Berlangganan lancar</p>
        </div>
        <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
    </div>

    <!-- Isolir -->
    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 shadow-sm flex items-center justify-between card-interactive">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Isolir / Suspend</p>
            <h3 class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1"><?php echo e(number_format($statusCounts['suspended'] ?? 0)); ?></h3>
            <p class="text-[10px] text-slate-500 mt-0.5">Penangguhan sementara</p>
        </div>
        <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        </div>
    </div>

    <!-- Lewat Tempo -->
    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 shadow-sm flex items-center justify-between card-interactive">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Lewat Tempo</p>
            <h3 class="text-2xl font-bold text-rose-600 dark:text-rose-400 mt-1"><?php echo e(number_format($overdueCount ?? 0)); ?></h3>
            <p class="text-[10px] text-slate-500 mt-0.5">Menunggu pembayaran</p>
        </div>
        <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
    </div>
</div>


<div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200/80 dark:border-slate-800/80 shadow-sm space-y-3 mb-5">

    <!-- Status Tabs & Search Row -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
        <!-- Status Tabs Filter -->
        <div class="flex items-center p-1 bg-slate-100 dark:bg-slate-800/80 rounded-xl w-fit text-xs font-semibold">
            <a href="<?php echo e(route('customers.index')); ?>"
               class="px-3.5 py-1.5 rounded-lg transition-all flex items-center gap-2 cursor-pointer
                      <?php echo e($status === '' && empty($statusGroup) ? 'bg-white dark:bg-slate-900 text-sky-600 dark:text-sky-400 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100'); ?>">
                <span>Semua</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono
                             <?php echo e($status === '' && empty($statusGroup) ? 'bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400' : 'bg-slate-200/60 dark:bg-slate-700 text-slate-600 dark:text-slate-300'); ?>"><?php echo e($totalCustomers); ?></span>
            </a>
            <a href="<?php echo e(route('customers.index', ['status' => 'active'])); ?>"
               class="px-3.5 py-1.5 rounded-lg transition-all flex items-center gap-2 cursor-pointer
                      <?php echo e($status === 'active' ? 'bg-white dark:bg-slate-900 text-emerald-600 dark:text-emerald-400 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100'); ?>">
                <span>Aktif</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono
                             <?php echo e($status === 'active' ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400' : 'bg-slate-200/60 dark:bg-slate-700 text-slate-600 dark:text-slate-300'); ?>"><?php echo e($statusCounts['active'] ?? 0); ?></span>
            </a>
            <a href="<?php echo e(route('customers.index', ['status' => 'suspended'])); ?>"
               class="px-3.5 py-1.5 rounded-lg transition-all flex items-center gap-2 cursor-pointer
                      <?php echo e($status === 'suspended' ? 'bg-white dark:bg-slate-900 text-amber-600 dark:text-amber-400 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100'); ?>">
                <span>Isolir</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono
                             <?php echo e($status === 'suspended' ? 'bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400' : 'bg-slate-200/60 dark:bg-slate-700 text-slate-600 dark:text-slate-300'); ?>"><?php echo e($statusCounts['suspended'] ?? 0); ?></span>
            </a>
        </div>

        <!-- Search & Density Control -->
        <div class="flex items-center gap-3">
            <form action="<?php echo e(url()->current()); ?>" method="GET" id="searchForm" class="relative flex-1 max-w-md">
                <?php if($statusGroup !== ''): ?>
                    <input type="hidden" name="status_group" value="<?php echo e($statusGroup); ?>">
                <?php endif; ?>
                <?php if($status !== ''): ?>
                    <input type="hidden" name="status" value="<?php echo e($status); ?>">
                <?php endif; ?>
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 dark:text-slate-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="search" id="search" value="<?php echo e($search); ?>"
                       placeholder="Cari Nama, CID, HP, atau Desa..."
                       class="w-full h-9 pl-10 pr-4 rounded-full border border-slate-200 dark:border-slate-700
                              bg-slate-50/50 dark:bg-slate-800/60 text-xs text-slate-800 dark:text-slate-100
                              placeholder-slate-400 dark:placeholder-slate-500
                              focus:outline-none focus:bg-white dark:focus:bg-slate-800 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-all">
            </form>

            <div class="hidden sm:flex items-center gap-2 text-xs text-slate-500">
                <div class="flex items-center p-1 bg-slate-100 dark:bg-slate-800 rounded-xl text-[11px] font-medium">
                    <button type="button" onclick="setDensity('comfortable')" id="density-comfortable" class="px-2.5 py-1 rounded-lg transition-all">Longgar</button>
                    <button type="button" onclick="setDensity('compact')" id="density-compact" class="px-2.5 py-1 rounded-lg transition-all">Rapat</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Multi Dropdown Filters Grid -->
    <form action="<?php echo e(url()->current()); ?>" method="GET" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-2.5 pt-2 border-t border-slate-100 dark:border-slate-800" id="filterForm">
        <?php if($statusGroup !== ''): ?>
            <input type="hidden" name="status_group" value="<?php echo e($statusGroup); ?>">
        <?php endif; ?>
        <?php if($status !== ''): ?>
            <input type="hidden" name="status" value="<?php echo e($status); ?>">
        <?php endif; ?>
        <?php if($search !== ''): ?>
            <input type="hidden" name="search" value="<?php echo e($search); ?>">
        <?php endif; ?>

        
        <?php if (isset($component)) { $__componentOriginalcf81d7d2d405cea4b5093282df7bde09 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalcf81d7d2d405cea4b5093282df7bde09 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.pop-filter','data' => ['selectedCabang' => $selectedCabang,'selectedMini' => $selectedMini]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.pop-filter'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['selected-cabang' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($selectedCabang),'selected-mini' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($selectedMini)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalcf81d7d2d405cea4b5093282df7bde09)): ?>
<?php $attributes = $__attributesOriginalcf81d7d2d405cea4b5093282df7bde09; ?>
<?php unset($__attributesOriginalcf81d7d2d405cea4b5093282df7bde09); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalcf81d7d2d405cea4b5093282df7bde09)): ?>
<?php $component = $__componentOriginalcf81d7d2d405cea4b5093282df7bde09; ?>
<?php unset($__componentOriginalcf81d7d2d405cea4b5093282df7bde09); ?>
<?php endif; ?>

        
        <?php if (isset($component)) { $__componentOriginald0fca8a5f156b3787475ebb0be4a25b4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0fca8a5f156b3787475ebb0be4a25b4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.wilayah-filter','data' => ['selectedDistricts' => $selectedDistricts,'selectedVillages' => $selectedVillages]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.wilayah-filter'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['selected-districts' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($selectedDistricts),'selected-villages' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($selectedVillages)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald0fca8a5f156b3787475ebb0be4a25b4)): ?>
<?php $attributes = $__attributesOriginald0fca8a5f156b3787475ebb0be4a25b4; ?>
<?php unset($__attributesOriginald0fca8a5f156b3787475ebb0be4a25b4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald0fca8a5f156b3787475ebb0be4a25b4)): ?>
<?php $component = $__componentOriginald0fca8a5f156b3787475ebb0be4a25b4; ?>
<?php unset($__componentOriginald0fca8a5f156b3787475ebb0be4a25b4); ?>
<?php endif; ?>

        
        <select name="package_id" id="package_id" onchange="this.form.submit()"
                class="w-full h-9 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs text-slate-700 dark:text-slate-200 focus:outline-none focus:border-sky-500">
            <option value="">Semua Paket Internet</option>
            <?php $__currentLoopData = $packages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $package): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($package->id); ?>" <?php echo e($packageId == $package->id ? 'selected' : ''); ?>><?php echo e($package->package_code); ?> - <?php echo e($package->name); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>

        
        <select name="completeness_status" id="completeness_status" onchange="this.form.submit()"
                class="w-full h-9 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs text-slate-700 dark:text-slate-200 focus:outline-none focus:border-sky-500">
            <option value="">Semua Kelengkapan Berkas</option>
            <option value="draft" <?php echo e($completenessStatus === 'draft' ? 'selected' : ''); ?>>Draft</option>
            <option value="perlu_dilengkapi" <?php echo e($completenessStatus === 'perlu_dilengkapi' ? 'selected' : ''); ?>>Perlu Dilengkapi</option>
            <option value="lengkap" <?php echo e($completenessStatus === 'lengkap' ? 'selected' : ''); ?>>Lengkap</option>
            <option value="siap_billing" <?php echo e($completenessStatus === 'siap_billing' ? 'selected' : ''); ?>>Siap Billing</option>
        </select>

        
        <?php if($collectorOptions->isNotEmpty()): ?>
        <select name="collector_id" id="collector_id" onchange="this.form.submit()"
                class="w-full h-9 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs text-slate-700 dark:text-slate-200 focus:outline-none focus:border-sky-500">
            <option value="">Semua (Kolektor &amp; Tanpa Kolektor)</option>
            <option value="none" <?php echo e($collectorId === 'none' ? 'selected' : ''); ?>>Belum Ada Kolektor</option>
            <?php $__currentLoopData = $collectorOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $collectorOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($collectorOption->id); ?>" <?php echo e((string) $collectorId === (string) $collectorOption->id ? 'selected' : ''); ?>><?php echo e($collectorOption->name); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <?php endif; ?>

        
        <div class="col-span-1 sm:col-span-2 md:col-span-4 lg:col-span-1 flex items-center gap-2">
            <a href="<?php echo e(url()->current()); ?>"
               class="w-full h-9 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold inline-flex items-center justify-center gap-1.5 transition-colors">
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span>Reset Filter</span>
            </a>
        </div>
    </form>
</div>




<div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm overflow-hidden mb-6">

    <?php if(!empty($statusGroup)): ?>
    
    <div class="border-b border-slate-200 dark:border-slate-800 px-6 py-3 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/30">
        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">
            <?php if($statusGroup === 'survey'): ?> Daftar Survey Pelanggan
            <?php elseif($statusGroup === 'verification'): ?> Daftar Verifikasi Pelanggan
            <?php elseif($statusGroup === 'failed'): ?> Daftar Pelanggan Gagal
            <?php elseif($statusGroup === 'terminated'): ?> Daftar Pelanggan Putus
            <?php endif; ?>
        </span>
        <a href="<?php echo e(route('customers.index')); ?>" class="text-xs text-sky-600 dark:text-sky-400 hover:underline">Lihat Semua Pelanggan</a>
    </div>
    <?php endif; ?>

    
    <?php if($statusGroup === 'failed'): ?>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead>
                    <tr class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                        <th class="py-3.5 px-4 w-12 text-center">No</th>
                        <th class="py-3.5 px-4">CID</th>
                        <th class="py-3.5 px-4">Nama Pelanggan</th>
                        <th class="py-3.5 px-4">POP</th>
                        <th class="py-3.5 px-4">Alasan</th>
                        <th class="py-3.5 px-4">Tgl Pemutusan</th>
                        <th class="py-3.5 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 text-xs text-slate-700 dark:text-slate-200">
                    <?php $__empty_1 = true; $__currentLoopData = $customers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $customer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr class="hover:bg-sky-50/40 dark:hover:bg-sky-950/20 transition-colors">
                        <td class="px-4 py-3.5 text-center text-slate-400 font-mono">
                            <?php echo e(($customers->currentPage() - 1) * $customers->perPage() + $loop->iteration); ?>

                        </td>
                        <td class="px-4 py-3.5 font-mono font-semibold text-sky-600 dark:text-sky-400">
                            <?php echo e($customer->display_id); ?>

                        </td>
                        <td class="px-4 py-3.5 font-bold text-slate-900 dark:text-white">
                            <?php echo e($customer->full_name); ?>

                        </td>
                        <td class="px-4 py-3.5 font-medium text-slate-700 dark:text-slate-300">
                            <?php echo e($customer->pop->name ?? '-'); ?>

                        </td>
                        <td class="px-4 py-3.5 max-w-xs text-slate-600 dark:text-slate-400 truncate">
                            <?php echo e($customer->reject_reason ?? '-'); ?>

                        </td>
                        <td class="px-4 py-3.5 font-mono text-slate-500">
                            <?php echo e($customer->rejected_at ? \App\Support\IndonesianDate::date($customer->rejected_at) : '-'); ?>

                        </td>
                        <td class="px-4 py-3.5 text-right">
                            <div class="inline-flex items-center gap-2">
                                <a href="<?php echo e(route('customers.show', $customer->id)); ?>"
                                   class="px-2.5 py-1 text-xs font-medium text-sky-600 hover:text-sky-700 border border-sky-200 dark:border-sky-800 rounded-lg hover:bg-sky-50 transition-colors">
                                    Detail
                                </a>
                                <?php if(auth()->user()->hasPermission('customers.detail.installation.validate') && $customer->status_before_reject): ?>
                                <form action="<?php echo e(route('customers.restore-from-failed', $customer->id)); ?>" method="POST"
                                      onsubmit="event.preventDefault(); window.confirmAction('Kembalikan <?php echo e($customer->full_name); ?> ke proses sebelum ditolak?', this);">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit" class="px-2.5 py-1 text-xs font-medium text-amber-600 border border-amber-200 rounded-lg hover:bg-amber-50 transition-colors">
                                        Kembalikan
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-slate-400">Tidak ada data pelanggan gagal.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    
    <?php elseif($statusGroup === 'terminated'): ?>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left">
                <thead>
                    <tr class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                        <th class="py-3.5 px-4 w-12 text-center">No</th>
                        <th class="py-3.5 px-4">CID</th>
                        <th class="py-3.5 px-4">Nama Pelanggan</th>
                        <th class="py-3.5 px-4">POP</th>
                        <th class="py-3.5 px-4">Kontrak</th>
                        <th class="py-3.5 px-4">Alasan Putus</th>
                        <th class="py-3.5 px-4">Tgl Pemutusan</th>
                        <th class="py-3.5 px-4 text-center">Status Alat</th>
                        <th class="py-3.5 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 text-xs text-slate-700 dark:text-slate-200">
                    <?php $__empty_1 = true; $__currentLoopData = $customers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $customer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php
                        $contractType = match($customer->customerService->contract_type ?? null) {
                            'sewa' => 'Sewa',
                            'beli' => 'Beli',
                            default => '-',
                        };
                        $isDeviceRetrieved = (bool) $customer->device_retrieved_at;
                    ?>
                    <tr class="hover:bg-sky-50/40 dark:hover:bg-sky-950/20 transition-colors">
                        <td class="px-4 py-3.5 text-center text-slate-400 font-mono">
                            <?php echo e(($customers->currentPage() - 1) * $customers->perPage() + $loop->iteration); ?>

                        </td>
                        <td class="px-4 py-3.5 font-mono font-semibold text-sky-600 dark:text-sky-400">
                            <?php echo e($customer->display_id); ?>

                        </td>
                        <td class="px-4 py-3.5 font-bold text-slate-900 dark:text-white">
                            <?php echo e($customer->full_name); ?>

                        </td>
                        <td class="px-4 py-3.5 font-medium text-slate-700 dark:text-slate-300">
                            <?php echo e($customer->pop->name ?? '-'); ?>

                        </td>
                        <td class="px-4 py-3.5 font-medium">
                            <?php echo e($contractType); ?>

                        </td>
                        <td class="px-4 py-3.5 max-w-xs text-slate-600 dark:text-slate-400 truncate">
                            <?php echo e($customer->termination_reason ?? '-'); ?>

                        </td>
                        <td class="px-4 py-3.5 font-mono text-slate-500">
                            <?php echo e($customer->terminated_at ? \App\Support\IndonesianDate::date($customer->terminated_at) : '-'); ?>

                        </td>
                        <td class="px-4 py-3.5 text-center">
                            <span class="px-2.5 py-0.5 text-[10px] font-bold rounded-full border <?php echo e($isDeviceRetrieved ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/50 dark:text-amber-300'); ?>">
                                <?php echo e($isDeviceRetrieved ? 'Sudah Diambil' : 'Belum Diambil'); ?>

                            </span>
                        </td>
                        <td class="px-4 py-3.5 text-right">
                            <div class="inline-flex items-center gap-2">
                                <a href="<?php echo e(route('customers.show', $customer->id)); ?>"
                                   class="px-2.5 py-1 text-xs font-medium text-sky-600 border border-sky-200 dark:border-sky-800 rounded-lg hover:bg-sky-50 transition-colors">
                                    Detail
                                </a>
                                <?php if(!$isDeviceRetrieved && auth()->user()->hasPermission('customers.detail.devices.retrieve')): ?>
                                <form action="<?php echo e(route('customers.retrieve-device', $customer->id)); ?>" method="POST"
                                      onsubmit="event.preventDefault(); window.confirmAction('Buat Task FOP pengambilan alat untuk <?php echo e($customer->full_name); ?>?', this);">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit" class="px-2.5 py-1 text-xs font-medium text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">
                                        Ambil Alat
                                    </button>
                                </form>
                                <?php endif; ?>
                                <?php if(auth()->user()->hasPermission('customers.detail.installation.validate')): ?>
                                <form action="<?php echo e(route('customers.reactivate', $customer->id)); ?>" method="POST"
                                      onsubmit="event.preventDefault(); window.confirmAction('Aktifkan kembali langganan <?php echo e($customer->full_name); ?>?', this);">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit" class="px-2.5 py-1 text-xs font-medium text-emerald-600 border border-emerald-200 rounded-lg hover:bg-emerald-50 transition-colors">
                                        Langganan Lagi
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="9" class="px-6 py-8 text-center text-slate-400">Tidak ada data pelanggan putus.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    
    <?php else: ?>
        <!-- DESKTOP TABLE VIEW (hidden di mobile) -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full min-w-[960px] border-collapse text-left" id="customerTable">
                <thead>
                    <tr class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                        <th class="py-3.5 pl-4 px-3">CID (ID PELANGGAN)</th>
                        <th class="py-3.5 px-3">NAMA LENGKAP</th>
                        <th class="py-3.5 px-3">POP & DESA</th>
                        <th class="py-3.5 px-3">PAKET INTERNET</th>
                        <th class="py-3.5 px-3">NO. TELEPON</th>
                        <th class="py-3.5 px-3">JATUH TEMPO</th>
                        <th class="py-3.5 px-3 text-right">TAGIHAN</th>
                        <th class="py-3.5 px-3 text-center">BERKAS</th>
                        <th class="py-3.5 px-3 text-center">STATUS</th>
                        <th class="py-3.5 px-3 text-center w-16">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 text-xs text-slate-700 dark:text-slate-200">
                    <?php $__empty_1 = true; $__currentLoopData = $customers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $customer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php
                        $displayId = $customer->display_id;
                        $completeness = $customer->dataCompleteness();
                        $cleanPhone = preg_replace('/[^0-9]/', '', $customer->primary_phone ?? '');
                        if (str_starts_with($cleanPhone, '0')) {
                            $cleanPhone = '62' . substr($cleanPhone, 1);
                        }
                    ?>
                    
                    <tr data-customer-row class="hover:bg-sky-50/40 dark:hover:bg-sky-950/20 transition-colors group">
                        <!-- CID (Klik untuk Atur Mini POP / Jaringan) -->
                        <td class="py-3.5 pl-4 px-3 font-mono font-semibold">
                            <?php if(auth()->user()->hasPermission('customers.detail.installation.validate')): ?>
                            <button type="button" onclick="openNetworkAssignmentModal(<?php echo e($customer->id); ?>)"
                                    class="text-sky-600 dark:text-sky-400 hover:text-sky-700 hover:underline flex items-center gap-1 text-left group-hover:scale-[1.01] transition-transform"
                                    title="Klik untuk Atur Mini POP & Distribusi">
                                <span><?php echo e($displayId); ?></span>
                                <svg class="w-3.5 h-3.5 opacity-0 group-hover:opacity-100 text-sky-500 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/></svg>
                            </button>
                            <?php else: ?>
                            <span class="text-sky-600 dark:text-sky-400 font-mono"><?php echo e($displayId); ?></span>
                            <?php endif; ?>
                        </td>

                        <!-- Nama Lengkap -->
                        <td class="py-3.5 px-3">
                            <span class="font-bold text-slate-900 dark:text-white group-hover:text-sky-600 transition-colors">
                                <?php echo e($customer->full_name); ?>

                            </span>
                            <?php if($customer->collector): ?>
                                <div class="mt-0.5">
                                    <span class="inline-flex items-center px-1.5 py-0.5 text-[9px] font-bold rounded border bg-violet-50 dark:bg-violet-500/10 text-violet-700 dark:text-violet-400 border-violet-100 dark:border-violet-500/20">
                                        Kolektor: <?php echo e($customer->collector->name); ?>

                                    </span>
                                </div>
                            <?php endif; ?>
                        </td>

                        <!-- POP & Desa -->
                        <td class="py-3.5 px-3 whitespace-nowrap">
                            <div class="flex items-center gap-1.5">
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-[11px]">
                                    <?php echo e($customer->pop->name ?? '-'); ?>

                                </span>
                                <span class="text-slate-400">·</span>
                                <span class="text-slate-500 dark:text-slate-400">
                                    <?php echo e($customer->village->name ?? ($customer->customerAddress->village ?? '-')); ?>

                                </span>
                            </div>
                        </td>

                        <!-- Paket Internet -->
                        <td class="py-3.5 px-3 font-mono text-[11px] text-slate-600 dark:text-slate-400">
                            <?php echo e($customer->internetPackage ? $customer->internetPackage->package_code . ' - ' . $customer->internetPackage->name : '-'); ?>

                        </td>

                        <!-- No. Telepon WA -->
                        <td class="py-3.5 px-3 font-mono">
                            <?php if($cleanPhone): ?>
                                <div class="inline-flex items-center gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="1.1em" height="1.1em" fill="currentColor" class="text-emerald-500 shrink-0">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347M12.05 21.785h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884a9.82 9.82 0 0 1 6.988 2.896 9.83 9.83 0 0 1 2.893 6.994c-.003 5.45-4.437 9.886-9.885 9.886m8.413-18.297A11.8 11.8 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.9 11.9 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413"/>
                                    </svg>
                                    <span><?php echo e($customer->primary_phone); ?></span>
                                </div>
                            <?php else: ?>
                                <span class="text-slate-400 italic">-</span>
                            <?php endif; ?>
                        </td>

                        <!-- Jatuh Tempo -->
                        <td class="py-3.5 px-3 font-mono text-[11px]">
                            <?php if($customer->latestInvoice): ?>
                                <?php
                                    $isOverdue = $customer->latestInvoice->due_date && $customer->latestInvoice->due_date->isPast() && $customer->latestInvoice->invoice_status !== \App\Enums\InvoiceStatus::LUNAS;
                                ?>
                                <span class="<?php echo e($isOverdue ? 'text-rose-600 dark:text-rose-400 font-bold' : 'text-slate-600 dark:text-slate-400'); ?>">
                                    <?php echo e(\App\Support\IndonesianDate::date($customer->latestInvoice->due_date)); ?>

                                </span>
                            <?php else: ?>
                                <span class="text-slate-400">-</span>
                            <?php endif; ?>
                        </td>

                        <!-- Tagihan -->
                        <td class="py-3.5 px-3 text-right font-mono">
                            <?php if($customer->latestInvoice): ?>
                                <?php
                                    $isPaid = $customer->latestInvoice->invoice_status === \App\Enums\InvoiceStatus::LUNAS;
                                    $isOverdue = !$isPaid && $customer->latestInvoice->due_date && $customer->latestInvoice->due_date->isPast();
                                ?>
                                <span class="font-bold <?php echo e($isPaid ? 'text-emerald-600 dark:text-emerald-400' : ($isOverdue ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-white')); ?>">
                                    Rp <?php echo e(number_format($customer->latestInvoice->total_amount, 0, ',', '.')); ?>

                                </span>
                                <span class="block text-[10px] font-sans font-semibold <?php echo e($isPaid ? 'text-emerald-500' : ($isOverdue ? 'text-rose-500' : 'text-slate-400')); ?>">
                                    <?php echo e($isPaid ? 'Lunas' : ($isOverdue ? 'Lewat Tempo' : 'Belum Bayar')); ?>

                                </span>
                            <?php else: ?>
                                <span class="text-slate-400">-</span>
                            <?php endif; ?>
                        </td>

                        <!-- Berkas -->
                        <td class="py-3.5 px-3 text-center font-mono">
                            <span class="px-2 py-0.5 rounded-full font-bold text-[11px] <?php echo e($completeness['percentage'] >= 80 ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400' : 'bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400'); ?>">
                                <?php echo e($completeness['percentage']); ?>%
                            </span>
                        </td>

                        <!-- Status Badge -->
                        <td class="py-3.5 px-3 text-center">
                            <?php
                                $statusLabel = $customer->subscriptionStatus->name ?? ucfirst($customer->status);
                                $isSuspended = $customer->status === 'suspended';
                                $isActive = $customer->status === 'active';
                            ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold border
                                  <?php echo e($isActive ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800' : ''); ?>

                                  <?php echo e($isSuspended ? 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800' : ''); ?>

                                  <?php echo e(!$isActive && !$isSuspended ? 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800' : ''); ?>">
                                <span class="w-1.5 h-1.5 rounded-full <?php echo e($isActive ? 'bg-emerald-500 animate-pulse-glow' : ($isSuspended ? 'bg-amber-500' : 'bg-rose-500')); ?> mr-1.5"></span>
                                <span><?php echo e($statusLabel); ?></span>
                            </span>
                        </td>

                        <!-- Aksi Button -->
                        <td class="py-3.5 px-3 text-center">
                            <button type="button"
                                    onclick="openActionsModal(this)"
                                    data-id="<?php echo e($customer->id); ?>"
                                    data-code="<?php echo e($displayId); ?>"
                                    data-name="<?php echo e($customer->full_name); ?>"
                                    data-nik="<?php echo e($customer->identity_number ?? '-'); ?>"
                                    data-phone="<?php echo e($customer->primary_phone); ?>"
                                    data-email="<?php echo e($customer->email ?? '-'); ?>"
                                    data-status="<?php echo e($customer->subscriptionStatus->name ?? Str::headline($customer->status)); ?>"
                                    data-raw-status="<?php echo e($customer->status); ?>"
                                    data-pop="<?php echo e($customer->pop->name ?? '-'); ?>"
                                    data-reg="<?php echo e(\App\Support\IndonesianDate::date($customer->registration_date)); ?>"
                                    data-package="<?php echo e($customer->internetPackage ? $customer->internetPackage->package_code . ' - ' . $customer->internetPackage->name : '-'); ?>"
                                    data-bandwidth="<?php echo e($customer->internetPackage?->speed_mbps ? $customer->internetPackage->speed_mbps . ' Mbps' : '-'); ?>"
                                    data-price="<?php echo e($customer->internetPackage ? 'Rp ' . number_format($customer->internetPackage->monthly_price, 0, ',', '.') : '-'); ?>"
                                    data-due-date="<?php echo e($customer->latestInvoice ? \App\Support\IndonesianDate::date($customer->latestInvoice->due_date) : '-'); ?>"
                                    data-address="<?php echo e($customer->address); ?>"
                                    data-landmark="<?php echo e($customer->customerAddress->landmark ?? '-'); ?>"
                                    data-rt-rw="<?php echo e(($customer->customerAddress?->rt ? 'RT ' . $customer->customerAddress->rt : '') . ($customer->customerAddress?->rw ? ' / RW ' . $customer->customerAddress->rw : '') ?: '-'); ?>"
                                    data-village="<?php echo e($customer->village->name ?? ($customer->customerAddress->village ?? '-')); ?>"
                                    data-district="<?php echo e($customer->district->name ?? ($customer->customerAddress->district ?? '-')); ?>"
                                    data-city="<?php echo e($customer->city->name ?? ($customer->customerAddress->city ?? 'Kab. Ponorogo')); ?>"
                                    data-postal-code="<?php echo e($customer->customerAddress->postal_code ?? '-'); ?>"
                                    data-lat="<?php echo e($customer->customerAddress->latitude ?? ''); ?>"
                                    data-lng="<?php echo e($customer->customerAddress->longitude ?? ''); ?>"
                                    data-completeness-pct="<?php echo e($completeness['percentage']); ?>"
                                    data-completeness-status="<?php echo e(Str::headline($customer->data_completeness_status ?? 'draft')); ?>"
                                    data-pppoe="<?php echo e($customer->customerService->pppoe_username ?? '-'); ?>"
                                    data-ip="<?php echo e($customer->customerService->ip_address ?? '-'); ?>"
                                    data-vlan="<?php echo e($customer->customerService->vlan_id ?? '-'); ?>"
                                    data-onu="<?php echo e($customer->customerDevice->onu_sn ?? ($customer->customerDevice->mac_address ?? '-')); ?>"
                                    data-onu-brand="<?php echo e($customer->customerDevice->onu_brand ?? '-'); ?>"
                                    data-router="<?php echo e($customer->customerDevice->router_sn ?? '-'); ?>"
                                    data-router-brand="<?php echo e($customer->customerDevice->router_brand ?? '-'); ?>"
                                    data-contract="<?php echo e(match($customer->customerService->contract_type ?? null) { 'sewa' => 'Sewa', 'beli' => 'Beli', default => '-' }); ?>"
                                    data-distribution="<?php echo e($customer->distribution->name ?? '-'); ?>"
                                    class="w-9 h-9 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-sky-50 dark:hover:bg-slate-700 hover:border-sky-300 text-slate-500 hover:text-sky-600 inline-flex items-center justify-center transition-all shadow-sm cursor-pointer"
                                    title="Buka Modal Hub Aksi Cepat">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/></svg>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="10" class="px-6 py-8 text-center text-slate-400">Tidak ada data pelanggan yang cocok.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- MOBILE & TABLET PORTRAIT CARD VIEW (block di mobile) -->
        <div class="block md:hidden p-3 sm:p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?php $__empty_1 = true; $__currentLoopData = $customers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $customer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <?php
                    $displayId = $customer->display_id;
                    $completeness = $customer->dataCompleteness();
                    $isActive = $customer->status === 'active';
                    $isSuspended = $customer->status === 'suspended';
                    $isPaid = $customer->latestInvoice && $customer->latestInvoice->invoice_status === \App\Enums\InvoiceStatus::LUNAS;
                ?>
                <div class="p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm hover:border-sky-300 dark:hover:border-sky-800 transition-all card-interactive flex flex-col justify-between space-y-3">
                    <div class="space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <?php if(auth()->user()->hasPermission('customers.detail.installation.validate')): ?>
                                <button type="button" onclick="openNetworkAssignmentModal(<?php echo e($customer->id); ?>)"
                                        class="font-mono text-xs font-bold text-sky-600 dark:text-sky-400 flex items-center gap-1 hover:underline text-left">
                                    <span><?php echo e($displayId); ?></span>
                                    <svg class="w-3 h-3 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/></svg>
                                </button>
                                <?php else: ?>
                                <span class="font-mono text-xs font-bold text-sky-600 dark:text-sky-400"><?php echo e($displayId); ?></span>
                                <?php endif; ?>
                                <h4 class="font-bold text-slate-900 dark:text-white text-base mt-0.5"><?php echo e($customer->full_name); ?></h4>
                                <?php if($customer->collector): ?>
                                    <span class="inline-flex items-center mt-1 px-1.5 py-0.5 text-[9px] font-bold rounded border bg-violet-50 dark:bg-violet-500/10 text-violet-700 dark:text-violet-400 border-violet-100 dark:border-violet-500/20">
                                        Kolektor: <?php echo e($customer->collector->name); ?>

                                    </span>
                                <?php endif; ?>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-semibold border shrink-0
                                  <?php echo e($isActive ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800' : ''); ?>

                                  <?php echo e($isSuspended ? 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800' : ''); ?>

                                  <?php echo e(!$isActive && !$isSuspended ? 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800' : ''); ?>">
                                <span class="w-1.5 h-1.5 rounded-full <?php echo e($isActive ? 'bg-emerald-500 animate-pulse-glow' : ($isSuspended ? 'bg-amber-500' : 'bg-rose-500')); ?> mr-1"></span>
                                <span><?php echo e($customer->subscriptionStatus->name ?? ucfirst($customer->status)); ?></span>
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-xs bg-slate-50 dark:bg-slate-800/50 p-2.5 rounded-xl border border-slate-100 dark:border-slate-800">
                            <div>
                                <span class="text-slate-400 text-[10px] block">POP / Desa</span>
                                <span class="font-medium text-slate-700 dark:text-slate-200"><?php echo e($customer->pop->name ?? '-'); ?> · <?php echo e($customer->village->name ?? ($customer->customerAddress->village ?? '-')); ?></span>
                            </div>
                            <div>
                                <span class="text-slate-400 text-[10px] block">Tagihan</span>
                                <span class="font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                                    Rp <?php echo e(number_format($customer->latestInvoice->total_amount ?? 0, 0, ',', '.')); ?>

                                </span>
                                <span class="text-[9px] font-bold block <?php echo e($isPaid ? 'text-emerald-500' : 'text-rose-500'); ?>">
                                    ● <?php echo e($isPaid ? 'LUNAS' : 'BELUM BAYAR'); ?>

                                </span>
                            </div>
                            <div class="col-span-2">
                                <span class="text-slate-400 text-[10px] block">Paket Internet</span>
                                <span class="font-mono text-slate-600 dark:text-slate-300"><?php echo e($customer->internetPackage ? $customer->internetPackage->package_code . ' - ' . $customer->internetPackage->name : '-'); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                        <?php if($customer->latestPayment): ?>
                            <a href="<?php echo e(route('payments.receipt', $customer->latestPayment->id)); ?>"
                               target="_blank"
                               title="Cetak Struk / Kwitansi Pembayaran Terakhir"
                               class="px-3 py-2 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/40 text-rose-700 dark:text-rose-300 text-xs font-semibold inline-flex items-center gap-1.5 hover:bg-rose-100 shrink-0 touch-target btn-interactive">
                                <svg class="w-4 h-4 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                <span>Invoice / Struk</span>
                            </a>
                        <?php else: ?>
                            <button type="button"
                                    onclick="showModalToast('Belum ada pembayaran/kwitansi untuk pelanggan ini.')"
                                    title="Belum ada pembayaran yang bisa dicetak"
                                    class="px-3 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-400 text-xs font-semibold inline-flex items-center gap-1.5 opacity-60 cursor-not-allowed shrink-0">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v4a2 2 0 002 2z"/></svg>
                                <span>Invoice</span>
                            </button>
                        <?php endif; ?>

                        <button type="button"
                                onclick="openActionsModal(this)"
                                data-id="<?php echo e($customer->id); ?>"
                                data-code="<?php echo e($displayId); ?>"
                                data-name="<?php echo e($customer->full_name); ?>"
                                data-nik="<?php echo e($customer->identity_number ?? '-'); ?>"
                                data-phone="<?php echo e($customer->primary_phone); ?>"
                                data-email="<?php echo e($customer->email ?? '-'); ?>"
                                data-status="<?php echo e($customer->subscriptionStatus->name ?? Str::headline($customer->status)); ?>"
                                data-raw-status="<?php echo e($customer->status); ?>"
                                data-pop="<?php echo e($customer->pop->name ?? '-'); ?>"
                                data-reg="<?php echo e(\App\Support\IndonesianDate::date($customer->registration_date)); ?>"
                                data-package="<?php echo e($customer->internetPackage ? $customer->internetPackage->package_code . ' - ' . $customer->internetPackage->name : '-'); ?>"
                                data-bandwidth="<?php echo e($customer->internetPackage?->speed_mbps ? $customer->internetPackage->speed_mbps . ' Mbps' : '-'); ?>"
                                data-price="<?php echo e($customer->internetPackage ? 'Rp ' . number_format($customer->internetPackage->monthly_price, 0, ',', '.') : '-'); ?>"
                                data-due-date="<?php echo e($customer->latestInvoice ? \App\Support\IndonesianDate::date($customer->latestInvoice->due_date) : '-'); ?>"
                                data-address="<?php echo e($customer->address); ?>"
                                data-landmark="<?php echo e($customer->customerAddress->landmark ?? '-'); ?>"
                                data-rt-rw="<?php echo e(($customer->customerAddress?->rt ? 'RT ' . $customer->customerAddress->rt : '') . ($customer->customerAddress?->rw ? ' / RW ' . $customer->customerAddress->rw : '') ?: '-'); ?>"
                                data-village="<?php echo e($customer->village->name ?? ($customer->customerAddress->village ?? '-')); ?>"
                                data-district="<?php echo e($customer->district->name ?? ($customer->customerAddress->district ?? '-')); ?>"
                                data-city="<?php echo e($customer->city->name ?? ($customer->customerAddress->city ?? 'Kab. Ponorogo')); ?>"
                                data-postal-code="<?php echo e($customer->customerAddress->postal_code ?? '-'); ?>"
                                data-lat="<?php echo e($customer->customerAddress->latitude ?? ''); ?>"
                                data-lng="<?php echo e($customer->customerAddress->longitude ?? ''); ?>"
                                data-completeness-pct="<?php echo e($completeness['percentage']); ?>"
                                data-completeness-status="<?php echo e(Str::headline($customer->data_completeness_status ?? 'draft')); ?>"
                                data-pppoe="<?php echo e($customer->customerService->pppoe_username ?? '-'); ?>"
                                data-ip="<?php echo e($customer->customerService->ip_address ?? '-'); ?>"
                                data-vlan="<?php echo e($customer->customerService->vlan_id ?? '-'); ?>"
                                data-onu="<?php echo e($customer->customerDevice->onu_sn ?? ($customer->customerDevice->mac_address ?? '-')); ?>"
                                data-onu-brand="<?php echo e($customer->customerDevice->onu_brand ?? '-'); ?>"
                                data-router="<?php echo e($customer->customerDevice->router_sn ?? '-'); ?>"
                                data-router-brand="<?php echo e($customer->customerDevice->router_brand ?? '-'); ?>"
                                data-contract="<?php echo e(match($customer->customerService->contract_type ?? null) { 'sewa' => 'Sewa', 'beli' => 'Beli', default => '-' }); ?>"
                                data-distribution="<?php echo e($customer->distribution->name ?? '-'); ?>"
                                class="flex-1 py-2 px-3 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold flex items-center justify-center gap-1.5 shadow-md shadow-sky-600/20 transition-all btn-interactive touch-target">
                            <span>Buka Quick Hub</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <div class="p-8 text-center text-slate-400 text-xs">Tidak ada data pelanggan yang cocok.</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    
    <?php
        $cur = $customers->currentPage();
        $last = $customers->lastPage();
        $winStart = max(1, $cur - 2);
        $winEnd = min($last, $cur + 2);
        $btnBase = 'px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/60 text-xs font-semibold transition-colors';
        $btnDisabled = 'px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-400 dark:text-slate-600 text-xs font-semibold opacity-40 cursor-not-allowed';
        $btnActive = 'px-3 py-1.5 rounded-lg bg-sky-600 text-white font-semibold text-xs font-mono';
    ?>
    <div class="p-4 border-t border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4 text-xs text-slate-500">

        <div class="flex items-center gap-4">
            <div>
                <?php if($customers->total() > 0): ?>
                    Menampilkan
                    <strong class="text-slate-800 dark:text-white font-mono"><?php echo e(number_format($customers->firstItem(), 0, ',', '.')); ?>&ndash;<?php echo e(number_format($customers->lastItem(), 0, ',', '.')); ?></strong>
                    dari
                    <strong class="text-slate-800 dark:text-white font-mono"><?php echo e(number_format($customers->total(), 0, ',', '.')); ?></strong>
                    pelanggan
                <?php else: ?>
                    Tidak ada data
                <?php endif; ?>
            </div>

            <form method="GET" action="/customers" class="flex items-center gap-1.5">
                <?php $__currentLoopData = request()->except(['per_page', 'page']); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $qk => $qv): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php if(is_array($qv)): ?>
                        <?php $__currentLoopData = $qv; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $qvItem): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <input type="hidden" name="<?php echo e($qk); ?>[]" value="<?php echo e($qvItem); ?>">
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <?php else: ?>
                        <input type="hidden" name="<?php echo e($qk); ?>" value="<?php echo e($qv); ?>">
                    <?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <span class="hidden sm:inline">Baris</span>
                <select name="per_page" onchange="this.form.submit()"
                        class="h-8 pl-2 pr-7 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-mono focus:outline-none focus:border-sky-600">
                    <?php $__currentLoopData = [10, 25, 50, 100]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ppOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($ppOption); ?>" <?php echo e((int) request('per_page', 10) === $ppOption ? 'selected' : ''); ?>><?php echo e($ppOption); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </form>
        </div>

        <?php if($last > 1): ?>
        <div class="flex items-center gap-1.5">
            <?php if($customers->onFirstPage()): ?>
                <span class="<?php echo e($btnDisabled); ?>">Prev</span>
            <?php else: ?>
                <a href="<?php echo e($customers->previousPageUrl()); ?>" id="paginatePrev" class="<?php echo e($btnBase); ?>">Prev</a>
            <?php endif; ?>

            <?php if($winStart > 1): ?>
                <a href="<?php echo e($customers->url(1)); ?>" class="<?php echo e($btnBase); ?> font-mono">1</a>
                <?php if($winStart > 2): ?>
                    <span class="px-1 text-slate-400">&hellip;</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php for($n = $winStart; $n <= $winEnd; $n++): ?>
                <?php if($n === $cur): ?>
                    <span aria-current="page" class="<?php echo e($btnActive); ?>"><?php echo e($n); ?></span>
                <?php else: ?>
                    <a href="<?php echo e($customers->url($n)); ?>" class="<?php echo e($btnBase); ?> font-mono"><?php echo e($n); ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if($winEnd < $last): ?>
                <?php if($winEnd < $last - 1): ?>
                    <span class="px-1 text-slate-400">&hellip;</span>
                <?php endif; ?>
                <a href="<?php echo e($customers->url($last)); ?>" class="<?php echo e($btnBase); ?> font-mono"><?php echo e($last); ?></a>
            <?php endif; ?>

            <?php if($customers->hasMorePages()): ?>
                <a href="<?php echo e($customers->nextPageUrl()); ?>" id="paginateNext" class="<?php echo e($btnBase); ?>">Next</a>
            <?php else: ?>
                <span class="<?php echo e($btnDisabled); ?>">Next</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>



<div id="network-modal-wrapper" class="fixed inset-0 z-50 overflow-y-auto flex items-end sm:items-center justify-center p-0 sm:p-4 md:p-6 hidden">
    <!-- Backdrop Blur -->
    <div onclick="closeNetworkAssignmentModal()" class="fixed inset-0 bg-slate-900/60 backdrop-blur-md transition-opacity"></div>

    <!-- Modal Dialog Sheet -->
    <div class="relative bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-t-3xl sm:rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden z-10 max-h-[88vh] sm:max-h-[90vh] flex flex-col transform transition-all duration-300">
        <!-- Mobile Pull Drag Handle Indicator -->
        <div class="w-10 h-1 bg-slate-300 dark:bg-slate-700 rounded-full mx-auto mt-2.5 mb-1 sm:hidden shrink-0"></div>

        <!-- Header -->
        <div class="px-4 sm:px-6 py-3 sm:py-3.5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/50 shrink-0">
            <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-2xl bg-sky-100 dark:bg-sky-950 text-sky-600 dark:text-sky-400 flex items-center justify-center font-bold shrink-0 shadow-sm">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </div>
                <div class="min-w-0">
                    <h3 class="font-bold text-slate-900 dark:text-white text-sm sm:text-base leading-tight truncate">Atur Jaringan & Mini POP</h3>
                    <p class="text-[11px] sm:text-xs text-slate-400 truncate">Konfigurasi titik distribusi OLT & pelanggan</p>
                </div>
            </div>
            <button type="button" onclick="closeNetworkAssignmentModal()" class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl btn-interactive shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Body Form -->
        <form id="network-assignment-form" method="POST" class="p-4 sm:p-6 space-y-4 overflow-y-auto flex-1 overscroll-y-contain">
            <?php echo csrf_field(); ?>
            <?php echo method_field('PUT'); ?>

            <!-- Customer Target Summary Card -->
            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <div class="min-w-0 pr-2">
                    <span class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Pelanggan</span>
                    <p id="na-customer-name" class="font-bold text-slate-800 dark:text-white text-xs sm:text-sm truncate">Memuat...</p>
                </div>
                <div class="text-right shrink-0">
                    <span class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">CID / Jaringan</span>
                    <p id="na-customer-cid" class="font-mono font-bold text-sky-600 dark:text-sky-400 text-xs">—</p>
                </div>
            </div>

            <!-- Cabang Context -->
            <div class="text-xs text-slate-500">
                <span>Cabang Utama: <strong id="na-pop-name" class="text-slate-800 dark:text-slate-200 font-semibold">—</strong></span>
            </div>

            <!-- Blocked Warning Banner -->
            <div id="na-blocked-warning" class="hidden text-xs text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/40 p-3 rounded-xl border border-rose-200 dark:border-rose-800">
                Mini POP & Distribusi cuma bisa diatur setelah proses pemasangan dimulai.
            </div>

            <!-- Mini POP Select -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Mini POP (OLT Target)</label>
                <select id="na-mini-pop-select" name="mini_pop_id" class="w-full h-10 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs sm:text-sm focus:ring-2 focus:ring-sky-500 focus:outline-none transition-all">
                    <option value="">Memuat...</option>
                </select>
            </div>

            <!-- Jalur Distribusi (ODP) Select -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Titik Distribusi (ODP / Box)</label>
                <select id="na-distribution-select" name="distribution_id" class="w-full h-10 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs sm:text-sm focus:ring-2 focus:ring-sky-500 focus:outline-none transition-all">
                    <option value="">—</option>
                </select>
                <p class="text-[11px] text-slate-400 mt-1">Daftar Distribusi otomatis menyesuaikan dengan Mini POP yang dipilih.</p>
            </div>

            <!-- Footer Buttons -->
            <div class="pt-4 flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-2 border-t border-slate-100 dark:border-slate-800 shrink-0">
                <button type="button" onclick="closeNetworkAssignmentModal()" class="w-full sm:w-auto px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 text-center btn-interactive">Batal</button>
                <button type="submit" id="na-submit-btn" class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold shadow-md shadow-sky-600/20 text-center btn-interactive">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>



<div id="actions-modal" class="fixed inset-0 z-50 overflow-y-auto flex items-end sm:items-center justify-center p-0 sm:p-4 md:p-6 hidden">
    <!-- Backdrop Blur -->
    <div onclick="closeActionsModal()" class="fixed inset-0 bg-slate-900/60 backdrop-blur-md transition-opacity"></div>

    <!-- Modal Dialog Sheet -->
    <div class="relative bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-t-3xl sm:rounded-3xl shadow-2xl w-full max-w-3xl overflow-hidden z-10 max-h-[88vh] sm:max-h-[90vh] flex flex-col transform transition-all duration-300">
        
        <!-- Mobile Pull Drag Handle Indicator -->
        <div class="w-10 h-1 bg-slate-300 dark:bg-slate-700 rounded-full mx-auto mt-2.5 mb-1 sm:hidden shrink-0"></div>

        <!-- Toast Notification inside modal -->
        <div id="modal-toast" class="hidden absolute top-3 left-1/2 -translate-x-1/2 z-30 px-4 py-2 rounded-xl bg-emerald-600 text-white font-semibold text-xs shadow-lg items-center gap-2 animate-pop-in">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span id="modal-toast-text"></span>
        </div>

        <!-- Header -->
        <div class="px-4 sm:px-6 py-3 sm:py-3.5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/80 dark:bg-slate-900/80 shrink-0 gap-2">
            <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
                <div id="actions-modal-avatar" class="w-9 h-9 sm:w-10 sm:h-10 rounded-2xl bg-gradient-to-tr from-sky-500 to-indigo-600 text-white flex items-center justify-center font-bold text-xs sm:text-sm shadow-md shadow-sky-500/20 shrink-0">
                    CU
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap">
                        <h3 class="font-bold text-slate-900 dark:text-white text-sm sm:text-base leading-tight truncate max-w-[150px] sm:max-w-none" id="actions-modal-title">Nama Pelanggan</h3>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border inline-flex items-center gap-1 shrink-0" id="actions-modal-status-badge">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse-glow"></span>
                            <span>ACTIVE</span>
                        </span>
                    </div>
                    <div class="flex items-center gap-1.5 text-[11px] sm:text-xs mt-0.5">
                        <span class="font-mono text-sky-600 dark:text-sky-400 font-bold shrink-0" id="actions-modal-code">CID-000</span>
                        <span class="text-slate-400">•</span>
                        <span class="text-slate-500 dark:text-slate-400 truncate max-w-[140px] sm:max-w-none" id="actions-modal-location-text">POP Central</span>
                    </div>
                </div>
            </div>
            <button type="button" onclick="closeActionsModal()" class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl btn-interactive shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- MODAL TAB NAVIGATION (SCROLLABLE NO-SCROLLBAR) -->
        <div class="px-2 sm:px-6 border-b border-slate-100 dark:border-slate-800 bg-slate-50/40 dark:bg-slate-900/40 flex items-center gap-1 overflow-x-auto shrink-0 no-scrollbar snap-x snap-mandatory" id="modal-tab-header">
            <button type="button" onclick="switchActionTab('finance')" id="tab-btn-finance" class="py-2.5 px-3 sm:px-4 text-[11px] sm:text-xs font-bold border-b-2 border-sky-600 text-sky-600 dark:text-sky-400 bg-white dark:bg-slate-800 rounded-t-xl transition-all whitespace-nowrap shrink-0 flex items-center gap-1.5 touch-target btn-interactive snap-start">
                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                
                <span class="hidden sm:inline">Ringkasan & Tagihan</span>
                <span class="sm:hidden">Tagihan</span>
            </button>
            <button type="button" onclick="switchActionTab('technical')" id="tab-btn-technical" class="py-2.5 px-3 sm:px-4 text-[11px] sm:text-xs font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 rounded-t-xl transition-all whitespace-nowrap shrink-0 flex items-center gap-1.5 touch-target btn-interactive snap-start">
                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
                <span class="hidden sm:inline">Teknis & Perangkat</span>
                <span class="sm:hidden">Teknis</span>
            </button>
            <button type="button" onclick="switchActionTab('field')" id="tab-btn-field" class="py-2.5 px-3 sm:px-4 text-[11px] sm:text-xs font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 rounded-t-xl transition-all whitespace-nowrap shrink-0 flex items-center gap-1.5 touch-target btn-interactive snap-start">
                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span class="hidden sm:inline">Lokasi & Lapangan</span>
                <span class="sm:hidden">Lokasi</span>
            </button>
            <button type="button" onclick="switchActionTab('profile')" id="tab-btn-profile" class="py-2.5 px-3 sm:px-4 text-[11px] sm:text-xs font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 rounded-t-xl transition-all whitespace-nowrap shrink-0 flex items-center gap-1.5 touch-target btn-interactive snap-start">
                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <span class="hidden sm:inline">Profil & Berkas</span>
                <span class="sm:hidden">Berkas</span>
            </button>
        </div>

        <!-- Body Scrollable Content -->
        <div class="p-3.5 sm:p-6 overflow-y-auto space-y-4 sm:space-y-6 flex-1 overscroll-y-contain">
            
            <!-- Loading State -->
            <div id="modal-hub-loading" class="py-8 text-center hidden">
                <svg class="animate-spin h-6 w-6 text-sky-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="text-xs text-slate-500 font-medium mt-2">Memuat data tagihan & sistem...</p>
            </div>

            <!-- TAB 1: RINGKASAN & KEUANGAN -->
            <div id="tab-content-finance" class="tab-pane space-y-4 sm:space-y-6">
                
                <div class="hidden lg:flex lg:flex-wrap gap-2">
                    <!-- Kirim WA Dropdown -->
                    <div class="relative flex-1 min-w-[140px]" id="wa-dropdown-container">
                        
                        <button type="button" onclick="toggleWaDropdown()" class="w-full h-11 px-2.5 rounded-xl border border-emerald-200 dark:border-emerald-900/40 bg-emerald-50/60 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 text-xs font-semibold flex items-center justify-center gap-1.5 hover:bg-emerald-100 transition-all btn-interactive touch-target">
                            <svg class="w-4 h-4 fill-current shrink-0" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                            <span>Kirim WA</span>
                        </button>
                        <div id="wa-menu-dropdown" class="hidden absolute left-0 right-0 sm:right-auto mt-1 w-full sm:w-60 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-2xl z-30 p-2 space-y-1 text-xs">
                            <span class="text-[10px] font-bold text-slate-400 px-2 uppercase">Pilih Template WA:</span>
                            <a id="btn-wa-reminder" href="#" target="_blank" class="block p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 btn-interactive">
                                <p class="font-bold">Pengingat Tagihan</p>
                                <p class="text-[10px] text-slate-400">Notifikasi invoice & jatuh tempo</p>
                            </a>
                            <a id="btn-wa-confirmation" href="#" target="_blank" class="block p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 btn-interactive">
                                <p class="font-bold">Konfirmasi Pembayaran</p>
                                <p class="text-[10px] text-slate-400">Terima kasih pembayaran lunas</p>
                            </a>
                            <a id="btn-wa-isolir" href="#" target="_blank" class="block p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 btn-interactive">
                                <p class="font-bold">Pemberitahuan Isolir</p>
                                <p class="text-[10px] text-slate-400">Penangguhan sementara</p>
                            </a>
                        </div>
                    </div>

                    <!-- Switch Status Layanan -->
                    <button type="button" onclick="triggerHubToggleConnection()" id="btn-hub-toggle-status" class="flex-1 min-w-[140px] h-11 px-2.5 rounded-xl border border-amber-200 dark:border-amber-900/40 bg-amber-50/60 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 text-xs font-semibold flex items-center justify-center gap-1.5 hover:bg-amber-100 transition-all btn-interactive touch-target">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                        <span id="btn-hub-toggle-status-text">Isolir Layanan</span>
                    </button>

                    <?php if(auth()->user()->hasPermission('payments.view')): ?>
                    
                        <button type="button" onclick="printLatestReceipt()" id="btn-print-receipt" disabled
                                class="btn-print-receipt-action flex-1 min-w-[140px] h-11 px-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold flex items-center justify-center gap-1.5 shadow-sm btn-interactive disabled:opacity-50 disabled:cursor-not-allowed"
                                title="Cetak struk pembayaran terakhir">
                            <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-5a2 2 0 00-2-2H5a2 2 0 00-2 2v5a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4"/></svg>
                            <span>Cetak Struk</span>
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Grid Info Tagihan & Form Pembayaran Cepat -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Left: Tagihan Active Card Summary -->
                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-700/60 pb-2">
                            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Ringkasan Tagihan</h4>
                            <span id="hub-invoice-period-badge" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-100 dark:bg-sky-950 text-sky-700 dark:text-sky-300">Periode</span>
                        </div>
                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Paket Internet</span>
                                <span id="hub-fin-package" class="font-semibold text-slate-900 dark:text-white">-</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Harga Bulanan</span>
                                <span id="hub-fin-price" class="font-mono font-bold text-slate-900 dark:text-white">-</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Jatuh Tempo</span>
                                <span id="hub-fin-due-date" class="font-mono text-slate-800 dark:text-slate-200">-</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Total Piutang</span>
                                <span id="hub-fin-arrears" class="font-mono font-bold text-rose-600">Rp 0</span>
                            </div>
                            <div class="flex justify-between pt-2 border-t border-slate-200/60 dark:border-slate-700/60">
                                <span class="font-bold text-slate-900 dark:text-white">Total Harus Dibayar</span>
                                <span id="hub-fin-total-pay" class="font-mono font-bold text-base text-emerald-600 dark:text-emerald-400">Rp 0</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Form Input Pembayaran Cepat -->
                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 space-y-3" id="payment-form-container">
                        <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-700/60 pb-2">
                            <h4 class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Input Pembayaran Instan</span>
                            </h4>
                        </div>

                        <!-- Notice Badge Saat Belum Ada Tagihan -->
                        <div id="payment-form-notice" class="hidden p-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-900/60 text-amber-700 dark:text-amber-300 text-[11px] font-medium flex items-center gap-2">
                            <svg class="w-4 h-4 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <span id="payment-form-notice-text">Pelanggan ini belum memiliki tagihan aktif.</span>
                        </div>

                        <form id="payment-form" method="POST" action="" class="space-y-3 text-xs">
                            <?php echo csrf_field(); ?>
                            <div>
                                <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Nominal Pembayaran (Rp)</label>
                                <input type="number" name="amount" id="payment_amount" class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 font-mono font-bold text-sm focus:ring-2 focus:ring-sky-500 focus:outline-none transition-all" required>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Metode</label>
                                    <select name="payment_method" class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs focus:ring-2 focus:ring-sky-500 focus:outline-none transition-all" required>
                                        <option value="cash">Tunai / Kasir</option>
                                        <option value="transfer">Transfer Bank</option>
                                        <option value="qris">QRIS</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Tanggal</label>
                                    <input type="date" name="payment_date" id="payment_date" value="<?php echo e(date('Y-m-d')); ?>" class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 font-mono text-xs focus:ring-2 focus:ring-sky-500 focus:outline-none transition-all" required>
                                </div>
                            </div>
                            <div class="flex flex-col sm:flex-row items-stretch gap-2">
                                <button type="submit" class="w-full h-10 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold flex items-center justify-center gap-1.5 shadow-md shadow-emerald-600/20 btn-interactive">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Simpan Pembayaran</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Riwayat Pembayaran Terakhir -->
                <div class="space-y-2">
                    <h5 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Riwayat 3 Pembayaran Terakhir</h5>
                    
                    <div class="border border-slate-200/80 dark:border-slate-800 rounded-2xl overflow-x-auto">
                        <table class="w-full min-w-[420px] text-left text-xs">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-slate-800/60 text-slate-400 font-semibold border-b border-slate-200/80 dark:border-slate-800">
                                    <th class="py-2.5 px-3">TANGGAL</th>
                                    <th class="py-2.5 px-3">INVOICE</th>
                                    <th class="py-2.5 px-3">METODE</th>
                                    <th class="py-2.5 px-3 text-right">NOMINAL</th>
                                    <th class="py-2.5 px-3 text-center">STRUK</th>
                                </tr>
                            </thead>
                            <tbody id="hub-recent-payments-body" class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr>
                                    <td colspan="5" class="py-4 text-center text-slate-400">Belum ada riwayat pembayaran.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 2: TEKNIS & PERANGKAT -->
            <div id="tab-content-technical" class="tab-pane hidden space-y-4 sm:space-y-5">
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 space-y-4 text-xs">
                    <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-700/60 pb-2">
                        <h4 class="font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                            <span>Konfigurasi Teknis Jaringan & Perangkat</span>
                        </h4>
                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button" onclick="copyTechInfo()" class="text-sky-600 dark:text-sky-400 font-semibold hover:underline">Copy Teknis</button>
                            <?php if(auth()->user()->hasPermission('customers.detail.installation.validate')): ?>
                            <button type="button" onclick="triggerNetworkAssignmentFromHub()"
                                    class="px-3 py-1.5 rounded-xl bg-white dark:bg-slate-800 border border-sky-200 dark:border-sky-800 text-sky-600 dark:text-sky-400 font-semibold hover:bg-sky-50 dark:hover:bg-slate-700 transition-colors btn-interactive inline-flex items-center gap-1.5"
                                    title="Atur Mini POP & Distribusi">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
                                <span>Atur Jaringan</span>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="p-2.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700">
                            <span class="text-slate-400 text-[10px] block">Username PPPoE</span>
                            <span id="hub-tech-pppoe" class="font-mono font-bold text-slate-900 dark:text-white">-</span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700">
                            <span class="text-slate-400 text-[10px] block">IP Address</span>
                            <span id="hub-tech-ip" class="font-mono font-bold text-sky-600 dark:text-sky-400">-</span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700">
                            <span class="text-slate-400 text-[10px] block">VLAN ID</span>
                            <span id="hub-tech-vlan" class="font-mono font-semibold text-slate-800 dark:text-slate-200">-</span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700">
                            <span class="text-slate-400 text-[10px] block">Bandwidth</span>
                            <span id="hub-tech-bandwidth" class="font-semibold text-slate-800 dark:text-slate-200">-</span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700">
                            <span class="text-slate-400 text-[10px] block">SN ONU / Modem</span>
                            <span id="hub-tech-onu" class="font-mono font-semibold text-slate-800 dark:text-slate-200">-</span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700">
                            <span class="text-slate-400 text-[10px] block">SN Router WiFi</span>
                            <span id="hub-tech-router" class="font-mono font-semibold text-slate-800 dark:text-slate-200">-</span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700">
                            <span class="text-slate-400 text-[10px] block">ODP / Distribusi</span>
                            <span id="hub-tech-distribution" class="font-semibold text-slate-800 dark:text-slate-200">-</span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700">
                            <span class="text-slate-400 text-[10px] block">Skema Kontrak</span>
                            <span id="hub-tech-contract" class="font-semibold text-slate-800 dark:text-slate-200">-</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: LOKASI & LAPANGAN -->
            <div id="tab-content-field" class="tab-pane hidden space-y-4 sm:space-y-5">
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 space-y-3 text-xs">
                    <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-700/60 pb-2">
                        <h4 class="font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span>Alamat Pemasangan & Navigasi</span>
                        </h4>
                    </div>

                    <div class="space-y-2">
                        <div>
                            <span class="text-slate-400 text-[11px] block">Alamat Pemasangan Lengkap</span>
                            <p id="hub-field-address-full" class="font-semibold text-slate-900 dark:text-white text-sm">-</p>
                        </div>
                        <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-200/60 dark:border-slate-700">
                            <div>
                                <span class="text-slate-400 text-[10px] block">Desa / Kelurahan</span>
                                <span id="hub-field-village" class="font-semibold text-slate-800 dark:text-slate-200">-</span>
                            </div>
                            <div>
                                <span class="text-slate-400 text-[10px] block">Kecamatan</span>
                                <span id="hub-field-district" class="font-semibold text-slate-800 dark:text-slate-200">-</span>
                            </div>
                            <div>
                                <span class="text-slate-400 text-[10px] block">Kota / Kabupaten</span>
                                <span id="hub-field-city" class="font-semibold text-slate-800 dark:text-slate-200">-</span>
                            </div>
                            <div>
                                <span class="text-slate-400 text-[10px] block">Kode Pos</span>
                                <span id="hub-field-postal-code" class="font-mono text-slate-800 dark:text-slate-200">-</span>
                            </div>
                        </div>
                        <div class="pt-2 border-t border-slate-200/60 dark:border-slate-700">
                            <span class="text-slate-400 text-[10px] block">Koordinat GPS</span>
                            <span id="hub-field-coords" class="font-mono font-bold text-sky-600 dark:text-sky-400 block">-</span>
                        </div>
                    </div>

                    <div class="pt-3 flex justify-end">
                        <a id="btn-field-launch-maps" href="#" target="_blank" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-semibold inline-flex items-center justify-center gap-2 shadow-md shadow-sky-600/20 btn-interactive">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span>Buka Google Maps</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- TAB 4: PROFIL & BERKAS -->
            <div id="tab-content-profile" class="tab-pane hidden space-y-4 sm:space-y-5">
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 space-y-3 text-xs">
                    <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-700/60 pb-2">
                        <h4 class="font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            <span>Identitas & Kelengkapan Berkas</span>
                        </h4>
                        <span id="hub-prof-completeness-status" class="px-2.5 py-0.5 rounded-full font-bold text-[10px] bg-amber-100 text-amber-700">-</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <span class="text-slate-400 text-[10px] block">Nama Lengkap</span>
                            <span id="hub-prof-fullname" class="font-bold text-slate-900 dark:text-white">-</span>
                        </div>
                        <div>
                            <span class="text-slate-400 text-[10px] block">NIK / No. KTP</span>
                            <span id="hub-prof-nik" class="font-mono text-slate-800 dark:text-slate-200">-</span>
                        </div>
                        <div>
                            <span class="text-slate-400 text-[10px] block">Kode Pelanggan (CID)</span>
                            <span id="hub-prof-cid" class="font-mono font-bold text-sky-600 dark:text-sky-400">-</span>
                        </div>
                        <div>
                            <span class="text-slate-400 text-[10px] block">No. HP / WA</span>
                            <span id="hub-prof-phone" class="font-mono text-slate-800 dark:text-slate-200">-</span>
                        </div>
                        <div>
                            <span class="text-slate-400 text-[10px] block">Email</span>
                            <span id="hub-prof-email" class="text-slate-800 dark:text-slate-200">-</span>
                        </div>
                        <div>
                            <span class="text-slate-400 text-[10px] block">Tanggal Registrasi</span>
                            <span id="hub-prof-reg" class="font-mono text-slate-800 dark:text-slate-200">-</span>
                        </div>
                    </div>

                    <!-- Progress Bar Kelengkapan -->
                    <div class="pt-3 border-t border-slate-200/60 dark:border-slate-700 space-y-1.5">
                        <div class="flex justify-between text-xs font-semibold">
                            <span class="text-slate-700 dark:text-slate-200">Kemajuan Kelengkapan Berkas</span>
                            <span id="hub-prof-completeness-bar-text" class="font-mono text-sky-600 dark:text-sky-400">0%</span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                            <div id="hub-prof-completeness-bar" class="h-full bg-sky-600 transition-all duration-300" style="width: 0%;"></div>
                        </div>
                    </div>
                </div>

                <!-- Kartu Berkas: KTP & Foto Rumah -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <?php $__currentLoopData = [
                        ['type' => 'ktp', 'title' => 'Foto KTP / Identitas', 'upload_label' => 'Ganti / Upload KTP'],
                        ['type' => 'rumah', 'title' => 'Foto Rumah / Lokasi', 'upload_label' => 'Upload Foto Lokasi'],
                    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $berkas): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="p-3.5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-bold text-slate-800 dark:text-white"><?php echo e($berkas['title']); ?></span>
                            <span id="hub-doc-<?php echo e($berkas['type']); ?>-badge" class="text-[10px] px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 font-semibold shrink-0">-</span>
                        </div>

                        <div class="h-28 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center border border-dashed border-slate-300 dark:border-slate-700 text-slate-400 overflow-hidden">
                            <a id="hub-doc-<?php echo e($berkas['type']); ?>-link" href="#" target="_blank" class="hidden w-full h-full items-center justify-center text-[11px] font-semibold text-sky-600 dark:text-sky-400 hover:underline">
                                Lihat Berkas Tersimpan
                            </a>
                            <span id="hub-doc-<?php echo e($berkas['type']); ?>-empty" class="text-[10px] px-2 text-center">Belum ada berkas diunggah.</span>
                        </div>

                        <?php if(auth()->user()->hasPermission('customers.detail.documents.upload')): ?>
                        
                        <form method="POST" action="" enctype="multipart/form-data" class="space-y-1.5 hub-document-form" data-document-type="<?php echo e($berkas['type']); ?>">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="document_type" value="<?php echo e($berkas['type']); ?>">
                            <input type="file" name="document_file" required accept=".jpg,.jpeg,.png,.webp,.pdf"
                                   class="w-full text-[10px] text-slate-500 dark:text-slate-400 file:mr-2 file:py-1.5 file:px-2.5 file:rounded-lg file:border-0 file:text-[10px] file:font-semibold file:bg-slate-100 dark:file:bg-slate-800 file:text-slate-700 dark:file:text-slate-200">
                            <button type="submit" class="w-full py-2 rounded-lg border border-sky-200 dark:border-sky-900/60 text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-slate-800 text-[11px] font-semibold touch-target btn-interactive">
                                <?php echo e($berkas['upload_label']); ?>

                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>

        </div>

        <!-- Modal Footer -->
        
        <div class="lg:hidden px-2 py-2 border-t border-slate-100 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-900/80 shrink-0">
            <div class="grid grid-cols-6 gap-1">
                <button type="button" onclick="triggerDetail()" title="Detail Full"
                        class="flex flex-col items-center justify-center gap-0.5 py-1.5 rounded-xl text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-slate-800 transition-colors btn-interactive">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    <span class="text-[9px] font-semibold leading-none">Detail</span>
                </button>

                <?php if(auth()->user()->hasPermission('customers.update')): ?>
                <button type="button" onclick="triggerEdit()" title="Edit Data Pelanggan"
                        class="flex flex-col items-center justify-center gap-0.5 py-1.5 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors btn-interactive">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    <span class="text-[9px] font-semibold leading-none">Edit</span>
                </button>
                <?php endif; ?>

                <button type="button" onclick="focusWaTemplates()" data-wa-trigger title="Kirim WhatsApp"
                        class="flex flex-col items-center justify-center gap-0.5 py-1.5 rounded-xl text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-slate-800 transition-colors btn-interactive">
                    <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                    <span class="text-[9px] font-semibold leading-none">WA</span>
                </button>

                <?php if(auth()->user()->hasPermission('payments.view')): ?>
                
                    <button type="button" onclick="printLatestReceipt()" id="btn-hub-footer-print-receipt" disabled
                        class="btn-print-receipt-action flex flex-col items-center justify-center gap-0.5 py-1.5 rounded-xl text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-slate-800 transition-colors btn-interactive disabled:opacity-50 disabled:cursor-not-allowed"
                        title="Cetak struk pembayaran terakhir">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-5a2 2 0 00-2-2H5a2 2 0 00-2 2v5a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4"/></svg>
                        <span class="text-[9px] font-semibold leading-none">PDF</span>
                    </button>
                <?php endif; ?>

                <button type="button" onclick="triggerHubToggleConnection()" title="Isolir / Aktifkan Layanan"
                        class="flex flex-col items-center justify-center gap-0.5 py-1.5 rounded-xl text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-slate-800 transition-colors btn-interactive">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                    <span id="btn-hub-footer-toggle-text" class="text-[9px] font-semibold leading-none">Isolir</span>
                </button>

                <?php if(auth()->user()->hasPermission('customers.deactivate')): ?>
                <button type="button" onclick="triggerTerminate()" title="Putus Langganan"
                        class="flex flex-col items-center justify-center gap-0.5 py-1.5 rounded-xl text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-slate-800 transition-colors btn-interactive">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.1-1.1m-1.756-4.928a4 4 0 005.656 0l4-4a4 4 0 10-5.656-5.656l-1.1 1.1"/></svg>
                    <span class="text-[9px] font-semibold leading-none">Putus</span>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="hidden lg:flex px-6 py-3.5 border-t border-slate-100 dark:border-slate-800 items-center justify-between gap-2 bg-slate-50/80 dark:bg-slate-900/80 shrink-0 text-xs">
            <div class="flex items-center gap-2">
                <button type="button" onclick="triggerDetail()" class="h-10 px-3.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-semibold transition-colors shadow-sm btn-interactive inline-flex items-center justify-center text-center">
                    Detail Full
                </button>
                <?php if(auth()->user()->hasPermission('customers.update')): ?>
                <button type="button" onclick="triggerEdit()" class="h-10 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors btn-interactive inline-flex items-center justify-center text-center">
                    Edit Master Data
                </button>
                <?php endif; ?>
            </div>

            <div class="flex items-center gap-2">
                <?php if(auth()->user()->hasPermission('customers.deactivate')): ?>
                <button type="button" onclick="triggerTerminate()" class="h-10 px-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/40 text-rose-700 dark:text-rose-300 font-semibold hover:bg-rose-100 dark:hover:bg-rose-950/70 transition-colors btn-interactive inline-flex items-center justify-center text-center">
                    Putus Langganan
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
<script>
    /* ── Kerapatan tabel ── */
    function setDensity(mode) {
        document.documentElement.classList.toggle('density-compact', mode === 'compact');
        localStorage.setItem('whusnet-density', mode);
        syncDensityButtons();
    }

    function syncDensityButtons() {
        const compact = document.documentElement.classList.contains('density-compact');
        const on  = 'px-2.5 py-1 rounded-lg transition-all bg-white dark:bg-slate-900 text-sky-600 dark:text-sky-400 font-semibold shadow-sm';
        const off = 'px-2.5 py-1 rounded-lg transition-all text-slate-500 hover:text-slate-700 dark:hover:text-slate-200';
        const c = document.getElementById('density-compact');
        const f = document.getElementById('density-comfortable');
        if (c) c.className = compact ? on : off;
        if (f) f.className = compact ? off : on;
    }

    (function () {
        if (localStorage.getItem('whusnet-density') === 'compact') {
            document.documentElement.classList.add('density-compact');
        }
        syncDensityButtons();
    })();

    /* ── Navigasi keyboard tabel ── */
    (function () {
        const table = document.getElementById('customerTable');
        if (!table) return;

        let activeRow = -1;
        // Baris data ditandai [data-customer-row] — baris "tidak ada data" tidak
        // punya penanda ini, jadi tidak ikut jadi target navigasi.
        const rowEls = () => Array.from(table.querySelectorAll('tbody tr[data-customer-row]'));

        function setActiveRow(i) {
            const rows = rowEls();
            if (!rows.length) return;
            activeRow = Math.min(Math.max(0, i), rows.length - 1);
            rows.forEach(r => r.classList.remove('row-active'));
            const el = rows[activeRow];
            el.classList.add('row-active');
            el.scrollIntoView({ block: 'nearest' });
        }

        function anyModalOpen() {
            return !document.getElementById('actions-modal')?.classList.contains('hidden')
                || !document.getElementById('network-modal-wrapper')?.classList.contains('hidden');
        }

        document.addEventListener('keydown', e => {
            const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)
                        || document.activeElement.isContentEditable;

            if (e.key === 'Escape') {
                const actionsModal = document.getElementById('actions-modal');
                if (actionsModal && !actionsModal.classList.contains('hidden')) {
                    e.preventDefault();
                    closeActionsModal();
                    return;
                }
                const netModal = document.getElementById('network-modal-wrapper');
                if (netModal && !netModal.classList.contains('hidden')) {
                    e.preventDefault();
                    closeNetworkAssignmentModal();
                    return;
                }
            }

            if (e.altKey && e.key.toLowerCase() === 'n') {
                const addLink = document.querySelector('a[href="/customers/create"]');
                if (addLink) { e.preventDefault(); window.location = addLink.href; }
                return;
            }

            if (typing || anyModalOpen()) return;

            const rows = rowEls();
            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault(); setActiveRow(activeRow < 0 ? 0 : activeRow + 1); break;
                case 'ArrowUp':
                    e.preventDefault(); setActiveRow(activeRow < 0 ? 0 : activeRow - 1); break;
                case 'Home':
                    if (!rows.length) return; e.preventDefault(); setActiveRow(0); break;
                case 'End':
                    if (!rows.length) return; e.preventDefault(); setActiveRow(rows.length - 1); break;
                // PageUp/PageDown pindah halaman paginasi. Sudah didokumentasikan
                // di modal Pintasan (layouts/app.blade.php) tapi belum pernah
                // diimplementasikan di halaman ini.
                case 'PageUp': {
                    const prev = document.getElementById('paginatePrev');
                    if (prev && prev.tagName === 'A' && prev.href) { e.preventDefault(); window.location = prev.href; }
                    break;
                }
                case 'PageDown': {
                    const next = document.getElementById('paginateNext');
                    if (next && next.tagName === 'A' && next.href) { e.preventDefault(); window.location = next.href; }
                    break;
                }
                case 'Enter': {
                    if (activeRow < 0) return;
                    e.preventDefault();
                    const actionBtn = rows[activeRow].querySelector('button[onclick^="openActionsModal"]');
                    if (actionBtn) actionBtn.click();
                    break;
                }
            }
        });
    })();

    let selectedCustomerData = {};

    // Cuma kelas STATE yang ditukar, bukan className utuh. Menimpa className
    // penuh (versi lama) ikut menghapus kelas responsif tab (text-[11px]
    // sm:text-xs, sm:px-4, snap-start) — begitu user pindah tab sekali, header
    // tab langsung berantakan di layar kecil.
    const TAB_ACTIVE_CLASSES = ['font-bold', 'border-sky-600', 'text-sky-600', 'dark:text-sky-400', 'bg-white', 'dark:bg-slate-800'];
    const TAB_INACTIVE_CLASSES = ['font-medium', 'border-transparent', 'text-slate-500', 'hover:text-slate-700', 'dark:hover:text-slate-300'];

    function switchActionTab(tabName) {
        const tabs = ['finance', 'technical', 'field', 'profile'];
        tabs.forEach(t => {
            const btn = document.getElementById(`tab-btn-${t}`);
            const content = document.getElementById(`tab-content-${t}`);
            const isActive = t === tabName;

            if (btn) {
                btn.classList.remove(...(isActive ? TAB_INACTIVE_CLASSES : TAB_ACTIVE_CLASSES));
                btn.classList.add(...(isActive ? TAB_ACTIVE_CLASSES : TAB_INACTIVE_CLASSES));
            }
            if (content) content.classList.toggle('hidden', !isActive);
        });
    }

    function showModalToast(msg) {
        const toast = document.getElementById('modal-toast');
        const text = document.getElementById('modal-toast-text');
        if (!toast || !text) return;
        text.innerText = msg;
        toast.classList.remove('hidden');
        toast.classList.add('flex');
        setTimeout(() => {
            toast.classList.add('hidden');
            toast.classList.remove('flex');
        }, 3000);
    }

    function toggleWaDropdown() {
        const dropdown = document.getElementById('wa-menu-dropdown');
        if (dropdown) dropdown.classList.toggle('hidden');
    }

    // Tombol WA di bar aksi footer memakai dropdown template yang SUDAH ADA di
    // tab Ringkasan — bukan salinan kedua. Menduplikasi dropdown berarti dua
    // elemen dengan id btn-wa-* yang sama, dan href template cuma keisi di salah
    // satunya. Jadi: pindah tab, scroll ke atas, lalu buka dropdown aslinya.
    function focusWaTemplates() {
        switchActionTab('finance');

        const dropdown = document.getElementById('wa-menu-dropdown');
        const container = document.getElementById('wa-dropdown-container');
        if (!dropdown || !container) return;

        container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        dropdown.classList.remove('hidden');
    }

    document.addEventListener('click', function(e) {
        const container = document.getElementById('wa-dropdown-container');
        const dropdown = document.getElementById('wa-menu-dropdown');
        // [data-wa-trigger] dikecualikan: klik tombol WA di footer membuka
        // dropdown lalu event-nya bubble ke sini, dan tanpa pengecualian ini
        // dropdown-nya langsung ketutup lagi di klik yang sama.
        if (container && dropdown && !container.contains(e.target) && !e.target.closest('[data-wa-trigger]')) {
            dropdown.classList.add('hidden');
        }
    });

    function getWaLink(type) {
        if (!selectedCustomerData.phone) return '#';
        let cleanPhone = selectedCustomerData.phone.replace(/[^0-9]/g, '');
        if (cleanPhone.startsWith('0')) cleanPhone = '62' + cleanPhone.substring(1);
        const name = selectedCustomerData.name || 'Pelanggan';
        const code = selectedCustomerData.code || '';
        const price = selectedCustomerData.price || '';
        const dueDate = selectedCustomerData.dueDate || '';

        let msg = '';
        if (type === 'reminder') {
            msg = `Halo Kak ${name} (${code}), menginformasikan tagihan internet Whusnet untuk bulan ini sebesar ${price} dengan jatuh tempo ${dueDate}. Pembayaran dapat dilakukan via Kasir POP atau Transfer. Terima kasih!`;
        } else if (type === 'confirmation') {
            msg = `Halo Kak ${name} (${code}), pembayaran tagihan internet Whusnet sebesar ${price} telah kami terima. Terima kasih telah berlangganan Whusnet!`;
        } else if (type === 'isolir') {
            msg = `Halo Kak ${name} (${code}), menginformasikan layanan internet Whusnet saat ini tertangguh (isolir) karena telah melewati jatuh tempo. Mohon lakukan konfirmasi pembayaran untuk aktivasi kembali.`;
        } else {
            msg = `Halo Kak ${name} (${code}), ada yang bisa kami bantu terkait layanan internet Whusnet?`;
        }
        return 'https://wa.me/' + cleanPhone + '?text=' + encodeURIComponent(msg);
    }

    function openActionsModal(button) {
        const modal = document.getElementById('actions-modal');
        if (!modal) return;

        selectedCustomerData = {
            id: button.getAttribute('data-id'),
            code: button.getAttribute('data-code'),
            name: button.getAttribute('data-name'),
            nik: button.getAttribute('data-nik') || '-',
            phone: button.getAttribute('data-phone') || '',
            email: button.getAttribute('data-email') || '-',
            status: button.getAttribute('data-status') || '-',
            rawStatus: button.getAttribute('data-raw-status') || 'active',
            pop: button.getAttribute('data-pop') || '-',
            reg: button.getAttribute('data-reg') || '-',
            package: button.getAttribute('data-package') || '-',
            bandwidth: button.getAttribute('data-bandwidth') || '-',
            price: button.getAttribute('data-price') || '-',
            dueDate: button.getAttribute('data-due-date') || '-',
            address: button.getAttribute('data-address') || '-',
            landmark: button.getAttribute('data-landmark') || '-',
            rtRw: button.getAttribute('data-rt-rw') || '-',
            village: button.getAttribute('data-village') || '-',
            district: button.getAttribute('data-district') || '-',
            city: button.getAttribute('data-city') || '-',
            postalCode: button.getAttribute('data-postal-code') || '-',
            lat: button.getAttribute('data-lat') || '',
            lng: button.getAttribute('data-lng') || '',
            completenessPct: button.getAttribute('data-completeness-pct') || '0',
            completenessStatus: button.getAttribute('data-completeness-status') || 'Draft',
            pppoe: button.getAttribute('data-pppoe') || '-',
            ip: button.getAttribute('data-ip') || '-',
            vlan: button.getAttribute('data-vlan') || '-',
            onu: button.getAttribute('data-onu') || '-',
            onuBrand: button.getAttribute('data-onu-brand') || '-',
            router: button.getAttribute('data-router') || '-',
            routerBrand: button.getAttribute('data-router-brand') || '-',
            contract: button.getAttribute('data-contract') || '-',
            distribution: button.getAttribute('data-distribution') || '-',
        };

        const setElemText = (id, txt) => {
            const el = document.getElementById(id);
            if (el) el.innerText = txt;
        };

        // Header & Bindings
        setElemText('actions-modal-title', selectedCustomerData.name);
        setElemText('actions-modal-code', selectedCustomerData.code);
        
        const avatarEl = document.getElementById('actions-modal-avatar');
        if (avatarEl && selectedCustomerData.name) {
            avatarEl.innerText = selectedCustomerData.name.substring(0, 2).toUpperCase();
        }

        const badgeEl = document.getElementById('actions-modal-status-badge');
        if (badgeEl) {
            const statusLabelSpan = badgeEl.querySelector('span:last-child') || badgeEl;
            statusLabelSpan.innerText = selectedCustomerData.status.toUpperCase();
            if (selectedCustomerData.rawStatus === 'active') {
                badgeEl.className = 'px-2 py-0.5 rounded-full text-[10px] font-semibold border inline-flex items-center gap-1 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800';
            } else if (selectedCustomerData.rawStatus === 'suspended') {
                badgeEl.className = 'px-2 py-0.5 rounded-full text-[10px] font-semibold border inline-flex items-center gap-1 bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800';
            } else {
                badgeEl.className = 'px-2 py-0.5 rounded-full text-[10px] font-semibold border inline-flex items-center gap-1 bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800';
            }
        }

        const fullLoc = `${selectedCustomerData.pop} (${selectedCustomerData.village})`;
        setElemText('actions-modal-location-text', fullLoc);

        // WA Links
        const waReminder = document.getElementById('btn-wa-reminder');
        const waConfirmation = document.getElementById('btn-wa-confirmation');
        const waIsolir = document.getElementById('btn-wa-isolir');
        if (waReminder) waReminder.href = getWaLink('reminder');
        if (waConfirmation) waConfirmation.href = getWaLink('confirmation');
        if (waIsolir) waIsolir.href = getWaLink('isolir');

        // Maps Link
        const fieldMapsBtn = document.getElementById('btn-field-launch-maps');
        let mapsUrl = '#';
        if (selectedCustomerData.lat && selectedCustomerData.lng) {
            mapsUrl = `https://www.google.com/maps/search/?api=1&query=${selectedCustomerData.lat},${selectedCustomerData.lng}`;
        } else {
            const queryAddr = `${selectedCustomerData.address}, ${selectedCustomerData.village}, ${selectedCustomerData.district}`;
            mapsUrl = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(queryAddr)}`;
        }
        if (fieldMapsBtn) fieldMapsBtn.href = mapsUrl;

        // Toggle Status Button Text
        const isActiveService = selectedCustomerData.rawStatus === 'active';
        const toggleBtnText = document.getElementById('btn-hub-toggle-status-text');
        if (toggleBtnText) {
            toggleBtnText.innerText = isActiveService ? 'Isolir Layanan' : 'Aktifkan Layanan';
        }
        // Label kembar di bar aksi footer (mobile/tablet) — versinya dipendekkan
        // karena slotnya cuma selebar ikon.
        const footerToggleText = document.getElementById('btn-hub-footer-toggle-text');
        if (footerToggleText) {
            footerToggleText.innerText = isActiveService ? 'Isolir' : 'Aktifkan';
        }

        // Pre-fill Static Data
        setElemText('hub-fin-package', selectedCustomerData.package);
        setElemText('hub-fin-price', selectedCustomerData.price);
        setElemText('hub-fin-due-date', selectedCustomerData.dueDate);

        setElemText('hub-tech-pppoe', selectedCustomerData.pppoe);
        setElemText('hub-tech-ip', selectedCustomerData.ip);
        setElemText('hub-tech-vlan', selectedCustomerData.vlan);
        setElemText('hub-tech-bandwidth', selectedCustomerData.bandwidth);
        setElemText('hub-tech-onu', selectedCustomerData.onu);
        setElemText('hub-tech-router', selectedCustomerData.router);
        setElemText('hub-tech-distribution', selectedCustomerData.distribution);
        setElemText('hub-tech-contract', selectedCustomerData.contract);

        setElemText('hub-field-address-full', `${selectedCustomerData.address !== '-' ? selectedCustomerData.address + ', ' : ''}Kel. ${selectedCustomerData.village}, Kec. ${selectedCustomerData.district}`);
        setElemText('hub-field-village', selectedCustomerData.village);
        setElemText('hub-field-district', selectedCustomerData.district);
        setElemText('hub-field-city', selectedCustomerData.city);
        setElemText('hub-field-postal-code', selectedCustomerData.postalCode);
        setElemText('hub-field-coords', (selectedCustomerData.lat && selectedCustomerData.lng) ? `${selectedCustomerData.lat}, ${selectedCustomerData.lng}` : 'Belum Diatur');

        setElemText('hub-prof-fullname', selectedCustomerData.name);
        setElemText('hub-prof-nik', selectedCustomerData.nik);
        setElemText('hub-prof-cid', selectedCustomerData.code);
        setElemText('hub-prof-phone', selectedCustomerData.phone || '-');
        setElemText('hub-prof-email', selectedCustomerData.email || '-');
        setElemText('hub-prof-reg', selectedCustomerData.reg);
        setElemText('hub-prof-completeness-status', selectedCustomerData.completenessStatus);
        setElemText('hub-prof-completeness-bar-text', selectedCustomerData.completenessPct + '%');
        const compBar = document.getElementById('hub-prof-completeness-bar');
        if (compBar) compBar.style.width = selectedCustomerData.completenessPct + '%';

        switchActionTab('finance');
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');

        // Set initial state saat data tagihan dimuat
        togglePaymentFormState(false, 'Memuat data tagihan pelanggan...');
        // Struk & berkas ikut direset — kalau tidak, data pelanggan sebelumnya
        // masih nempel selama fetch berjalan.
        setLatestReceipt(null);
        renderHubDocuments(null, null);

        // Fetch Live Payment Info
        const loadingEl = document.getElementById('modal-hub-loading');
        if (loadingEl) loadingEl.classList.remove('hidden');

        fetch(`/customers/${selectedCustomerData.id}/payment-info`)
            .then(res => res.json())
            .then(data => {
                if (loadingEl) loadingEl.classList.add('hidden');
                const formatRp = (num) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(num);

                if (data.invoice_id) {
                    setElemText('hub-invoice-period-badge', `Periode: ${data.billing_period || '-'}`);
                    setElemText('hub-fin-due-date', data.due_date || selectedCustomerData.dueDate);
                    setElemText('hub-fin-arrears', data.total_piutang > 0 ? formatRp(data.total_piutang) : 'Rp 0');
                    setElemText('hub-fin-total-pay', formatRp(data.remaining_amount));

                    const payForm = document.getElementById('payment-form');
                    if (payForm) payForm.action = `/invoices/${data.invoice_id}/payments`;
                    
                    // Enable form jika pelanggan punya tagihan aktif
                    togglePaymentFormState(true);

                    const amountInput = document.getElementById('payment_amount');
                    if (amountInput) {
                        amountInput.value = data.remaining_amount;
                    }
                } else {
                    setElemText('hub-invoice-period-badge', 'Tidak Ada Tagihan Aktif');
                    setElemText('hub-fin-total-pay', 'Rp 0');
                    setElemText('hub-fin-arrears', 'Rp 0');

                    // Disable form jika belum ada tagihan
                    togglePaymentFormState(false, 'Pelanggan ini belum memiliki tagihan aktif untuk dibayar.');
                }

                const tbody = document.getElementById('hub-recent-payments-body');
                if (tbody) {
                    if (data.recent_payments && data.recent_payments.length > 0) {
                        tbody.innerHTML = data.recent_payments.map(p => `
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                <td class="py-2.5 px-3 font-mono text-slate-600 dark:text-slate-300">${p.date}</td>
                                <td class="py-2.5 px-3 font-mono font-semibold text-slate-800 dark:text-white">${p.invoice_number}</td>
                                <td class="py-2.5 px-3"><span class="px-2 py-0.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 rounded text-[10px] font-semibold border border-slate-200 dark:border-slate-700">${p.method}</span></td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">${formatRp(p.amount)}</td>
                                <td class="py-2.5 px-3 text-center">
                                    <a href="${p.receipt_url}" target="_blank" class="inline-flex items-center justify-center p-1 rounded text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-slate-800 transition-colors" title="Cetak Struk Pembayaran Ini">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-5a2 2 0 00-2-2H5a2 2 0 00-2 2v5a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4"/></svg>
                                    </a>
                                </td>
                            </tr>
                        `).join('');
                    } else {
                        tbody.innerHTML = '<tr><td colspan="5" class="py-4 text-center text-slate-400">Belum ada riwayat pembayaran.</td></tr>';
                    }
                }

                // Struk hanya bisa dicetak kalau pelanggan sudah pernah bayar —
                // recent_payments sudah urut terbaru dari server.
                setLatestReceipt(data.recent_payments && data.recent_payments.length > 0
                    ? data.recent_payments[0].receipt_url
                    : null);

                renderHubDocuments(data.documents, data.documents_upload_url);

                if (data.technical) {
                    setElemText('hub-tech-pppoe', data.technical.pppoe_username || selectedCustomerData.pppoe);
                    setElemText('hub-tech-ip', data.technical.ip_address || selectedCustomerData.ip);
                    setElemText('hub-tech-onu', data.technical.onu_sn || selectedCustomerData.onu);
                    setElemText('hub-tech-router', data.technical.router_sn || selectedCustomerData.router);
                    setElemText('hub-tech-distribution', data.technical.distribution || selectedCustomerData.distribution);
                }
            })
            .catch(err => {
                console.error(err);
                if (loadingEl) loadingEl.classList.add('hidden');
                togglePaymentFormState(false, 'Gagal memuat informasi tagihan.');
                setLatestReceipt(null);
            });
    }

    // URL struk pembayaran terakhir pelanggan yang lagi dibuka di Modal Hub.
    // Direset tiap modal dibuka supaya tidak mencetak struk pelanggan sebelumnya.
    let latestReceiptUrl = null;

    function setLatestReceipt(url) {
        latestReceiptUrl = url || null;
        const btns = document.querySelectorAll('.btn-print-receipt-action, #btn-print-receipt, #btn-hub-footer-print-receipt');
        btns.forEach(btn => {
            btn.disabled = !latestReceiptUrl;
            btn.title = latestReceiptUrl
                ? 'Cetak struk pembayaran terakhir'
                : 'Belum ada pembayaran yang bisa dicetak';
        });
    }

    function printLatestReceipt() {
        if (!latestReceiptUrl) {
            showModalToast('Belum ada pembayaran yang bisa dicetak.');

            return;
        }
        window.open(latestReceiptUrl, '_blank');
    }

    function renderHubDocuments(documents, uploadUrl) {
        document.querySelectorAll('.hub-document-form').forEach(form => {
            form.action = uploadUrl || '';
            form.reset();
        });

        ['ktp', 'rumah'].forEach(type => {
            const doc = documents ? documents[type] : null;
            const badge = document.getElementById(`hub-doc-${type}-badge`);
            const link = document.getElementById(`hub-doc-${type}-link`);
            const empty = document.getElementById(`hub-doc-${type}-empty`);

            if (doc && doc.exists) {
                if (badge) {
                    badge.textContent = 'Ada';
                    badge.className = 'text-[10px] px-2 py-0.5 rounded bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 font-semibold shrink-0';
                }
                if (link) {
                    link.href = doc.url;
                    link.classList.remove('hidden');
                    link.classList.add('flex');
                }
                if (empty) empty.classList.add('hidden');
            } else {
                if (badge) {
                    badge.textContent = 'Belum';
                    badge.className = 'text-[10px] px-2 py-0.5 rounded bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 font-semibold shrink-0';
                }
                if (link) {
                    link.href = '#';
                    link.classList.add('hidden');
                    link.classList.remove('flex');
                }
                if (empty) empty.classList.remove('hidden');
            }
        });
    }

    function togglePaymentFormState(enabled, message = '') {
        const payForm = document.getElementById('payment-form');
        if (!payForm) return;

        const amountInput = document.getElementById('payment_amount');
        const methodSelect = payForm.querySelector('select[name="payment_method"]');
        const dateInput = document.getElementById('payment_date');
        const submitBtn = payForm.querySelector('button[type="submit"]');
        const noticeEl = document.getElementById('payment-form-notice');
        const noticeText = document.getElementById('payment-form-notice-text');

        if (enabled) {
            if (amountInput) {
                amountInput.disabled = false;
                amountInput.classList.remove('bg-slate-100', 'dark:bg-slate-900', 'cursor-not-allowed', 'opacity-60');
            }
            if (methodSelect) {
                methodSelect.disabled = false;
                methodSelect.classList.remove('bg-slate-100', 'dark:bg-slate-900', 'cursor-not-allowed', 'opacity-60');
            }
            if (dateInput) {
                dateInput.disabled = false;
                dateInput.classList.remove('bg-slate-100', 'dark:bg-slate-900', 'cursor-not-allowed', 'opacity-60');
            }
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed', 'pointer-events-none');
            }
            if (noticeEl) noticeEl.classList.add('hidden');
        } else {
            if (amountInput) {
                amountInput.disabled = true;
                amountInput.value = '';
                amountInput.classList.add('bg-slate-100', 'dark:bg-slate-900', 'cursor-not-allowed', 'opacity-60');
            }
            if (methodSelect) {
                methodSelect.disabled = true;
                methodSelect.classList.add('bg-slate-100', 'dark:bg-slate-900', 'cursor-not-allowed', 'opacity-60');
            }
            if (dateInput) {
                dateInput.disabled = true;
                dateInput.classList.add('bg-slate-100', 'dark:bg-slate-900', 'cursor-not-allowed', 'opacity-60');
            }
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed', 'pointer-events-none');
            }
            if (noticeEl) {
                if (noticeText && message) noticeText.innerText = message;
                noticeEl.classList.remove('hidden');
            }
        }
    }

    function closeActionsModal() {
        const modal = document.getElementById('actions-modal');
        if (modal) modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
        // Dropdown template WA ikut ditutup — kalau tidak, dia masih terbuka
        // waktu modal dibuka lagi untuk pelanggan lain.
        const waDropdown = document.getElementById('wa-menu-dropdown');
        if (waDropdown) waDropdown.classList.add('hidden');
    }

    function copyTechInfo() {
        const textToCopy = `[DATA TEKNIS PELANGGAN]
Nama: ${selectedCustomerData.name} (${selectedCustomerData.code})
POP: ${selectedCustomerData.pop}
PPPoE: ${selectedCustomerData.pppoe}
IP: ${selectedCustomerData.ip}
ONU SN: ${selectedCustomerData.onu}
Router SN: ${selectedCustomerData.router}
ODP/Distribusi: ${selectedCustomerData.distribution}`;

        navigator.clipboard.writeText(textToCopy).then(() => {
            showModalToast('Kredensial teknis berhasil disalin!');
        });
    }

    function triggerHubToggleConnection() {
        const isCurrentActive = selectedCustomerData.rawStatus === 'active';
        const actionText = isCurrentActive ? 'mengisolir / menonaktifkan' : 'mengaktifkan kembali';

        if (window.confirmAction) {
            if (confirm(`Apakah Anda yakin ingin ${actionText} koneksi internet untuk ${selectedCustomerData.name}?`)) {
                showModalToast(`Status layanan ${selectedCustomerData.name} berhasil diubah.`);
            }
        } else if (confirm(`Apakah Anda yakin ingin ${actionText} koneksi internet untuk ${selectedCustomerData.name}?`)) {
            showModalToast(`Status layanan ${selectedCustomerData.name} berhasil diubah.`);
        }
    }

    function triggerDetail() {
        window.location.href = '/customers/' + selectedCustomerData.id;
    }

    // Jembatan Modal Hub → Modal Atur Jaringan. Dua modal tidak boleh tampil
    // bersamaan (keduanya z-50 + backdrop), jadi hub ditutup dulu baru network dibuka.
    function triggerNetworkAssignmentFromHub() {
        if (!selectedCustomerData || !selectedCustomerData.id) return;
        const customerId = selectedCustomerData.id;
        closeActionsModal();
        openNetworkAssignmentModal(customerId);
    }

    function triggerEdit() {
        window.location.href = '/customers/' + selectedCustomerData.id + '/edit';
    }

    function triggerTerminate() {
        closeActionsModal();
        if (confirm(`Apakah Anda yakin ingin melakukan PEMUTUSAN / TERMINASI untuk ${selectedCustomerData.name}?`)) {
            window.location.href = '/customers/' + selectedCustomerData.id + '#terminate';
        }
    }

    /* ── Modal Atur Mini POP & Jaringan ── */
    function openNetworkAssignmentModal(customerId) {
        const wrapper = document.getElementById('network-modal-wrapper');
        const form = document.getElementById('network-assignment-form');
        const miniPopSelect = document.getElementById('na-mini-pop-select');
        const distSelect = document.getElementById('na-distribution-select');
        const warning = document.getElementById('na-blocked-warning');
        const submitBtn = document.getElementById('na-submit-btn');

        const custNameEl = document.getElementById('na-customer-name');
        const custCidEl = document.getElementById('na-customer-cid');
        const popNameEl = document.getElementById('na-pop-name');

        if (!wrapper || !form) return;

        form.action = `/customers/${customerId}/network-assignment`;
        miniPopSelect.innerHTML = '<option value="">Memuat...</option>';
        distSelect.innerHTML = '<option value="">—</option>';
        custNameEl.textContent = 'Memuat...';
        custCidEl.textContent = '—';
        popNameEl.textContent = '—';
        warning.classList.add('hidden');
        submitBtn.disabled = true;

        fetch(`/customers/${customerId}/network-assignment`, { headers: { 'Accept': 'application/json' } })
            .then(res => res.json())
            .then(data => {
                custNameEl.textContent = data.customer_name;
                custCidEl.textContent = data.customer_cid || 'DRAFT';
                popNameEl.textContent = `${data.pop_name} (${data.pop_code})`;

                miniPopSelect.innerHTML = '<option value="">— Belum di-assign —</option>';
                data.mini_pops.forEach(mp => {
                    const opt = document.createElement('option');
                    opt.value = mp.id;
                    opt.textContent = `[${mp.pop_code}] ${mp.name}`;
                    if (data.current.mini_pop_id === mp.id) opt.selected = true;
                    miniPopSelect.appendChild(opt);
                });

                distSelect.dataset.allOptions = JSON.stringify(data.distributions);
                distSelect.dataset.currentDistributionId = data.current.distribution_id ?? '';
                renderDistributionOptions();

                if (!data.editable) {
                    warning.classList.remove('hidden');
                    submitBtn.disabled = true;
                } else {
                    submitBtn.disabled = false;
                }
            })
            .catch(() => {
                custNameEl.textContent = 'Gagal memuat data. Coba lagi.';
            });

        wrapper.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeNetworkAssignmentModal() {
        const wrapper = document.getElementById('network-modal-wrapper');
        if (wrapper) wrapper.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    function renderDistributionOptions() {
        const miniPopSelect = document.getElementById('na-mini-pop-select');
        const distSelect = document.getElementById('na-distribution-select');
        if (!miniPopSelect || !distSelect) return;
        const selectedMiniPopId = miniPopSelect.value;
        const currentDistributionId = distSelect.dataset.currentDistributionId || '';
        const allDistributions = JSON.parse(distSelect.dataset.allOptions || '[]');

        distSelect.innerHTML = '<option value="">— Belum di-assign —</option>';
        allDistributions
            .filter(d => String(d.pop_id) === String(selectedMiniPopId))
            .forEach(d => {
                const opt = document.createElement('option');
                opt.value = d.id;
                opt.textContent = d.name ? `[${d.code}] ${d.name}` : d.code;
                if (String(currentDistributionId) === String(d.id)) opt.selected = true;
                distSelect.appendChild(opt);
            });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const miniPopSelect = document.getElementById('na-mini-pop-select');
        if (miniPopSelect) {
            miniPopSelect.addEventListener('change', renderDistributionOptions);
        }
    });
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/customers/index.blade.php ENDPATH**/ ?>