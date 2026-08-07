<?php $__env->startSection('title', 'Lapor Data Survey Pelanggan — Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', 'Lapor Data Survey'); ?>
<?php $__env->startSection('breadcrumb_parent', 'Antrean Survey'); ?>
<?php $__env->startSection('breadcrumb_parent_url', route('surveys.queue')); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-6xl mx-auto space-y-6">

    <!-- LAYER 1: NAKED PAGE HEADER (Strict Design System Rule: No card wrapper) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5 flex-wrap">
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-slate-50 tracking-tight">Laporan Survey Lapangan</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-sky-100 dark:bg-sky-900/50 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-700/60">
                    <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'user-check','class' => 'w-2.5 h-2.5 mr-1.5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'user-check','class' => 'w-2.5 h-2.5 mr-1.5']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?> <?php echo e($customer->full_name); ?>

                </span>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                Lapor data teknis pantauan lapangan, ketersediaan ODP, foto lokasi, serta perkiraan alat dan material instalasi.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="<?php echo e($returnTo); ?>" class="px-3.5 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors inline-flex items-center gap-2">
                <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'arrow-left','class' => 'w-3 h-3']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'arrow-left','class' => 'w-3 h-3']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
                <span>Kembali</span>
            </a>
        </div>
    </div>

    <!-- MAIN FORM CONTAINER -->
    <form action="<?php echo e(route('customers.survey.store', $customer->id)); ?>" method="POST" enctype="multipart/form-data" id="wizard-form" class="space-y-6">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="return_to" value="<?php echo e($returnTo); ?>">
        <input type="hidden" name="survey_status" id="survey_status_input" value="completed">

        <!-- TOP PANEL: Dynamic Completeness Progress Bar -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-xl p-4 sm:p-5 shadow-sm space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-sky-50 dark:bg-sky-900/40 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0 border border-sky-200 dark:border-sky-800/50">
                        <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'clipboard-check','class' => 'w-3 h-3']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'clipboard-check','class' => 'w-3 h-3']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">Kelengkapan Laporan Survey</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Semua data wajib akan divalidasi sebelum laporan disimpan</p>
                    </div>
                </div>
                <div class="text-right">
                    <span id="progress-percentage" class="text-sm sm:text-base font-extrabold text-sky-600 dark:text-sky-400 data-text">0%</span>
                    <span class="text-[11px] text-slate-500 dark:text-slate-400 block"><span id="filled-fields-count" class="data-text font-semibold">0</span> dari <span id="total-fields-count" class="data-text font-semibold">5</span> field terisi</span>
                </div>
            </div>

            <!-- Progress Bar Fill Strip -->
            <div class="w-full bg-slate-100 dark:bg-slate-700/60 rounded-full h-2.5 overflow-hidden border border-slate-200/60 dark:border-slate-700">
                <div id="progress-bar-fill" class="bg-gradient-to-r from-sky-500 to-sky-600 h-full w-0 transition-all duration-500 ease-out" style="width: 0%;"></div>
            </div>
        </div>

        <!-- MOBILE RESPONSIVE STEPPER (Visible on < lg screens: 2 rows x 2 items) -->
        <div class="lg:hidden bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-xl p-3 shadow-sm">
            <div class="grid grid-cols-2 gap-2 text-center">
                <button type="button" onclick="goToStep(1)" id="mobile-step-btn-1" class="py-2.5 px-2 rounded-lg text-xs font-bold bg-sky-50 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-700 transition-all flex items-center justify-center gap-1.5 shadow-sm">
                    <span class="w-4 h-4 rounded-full bg-sky-600 text-white text-[9px] font-bold flex items-center justify-center shrink-0">1</span>
                    <span class="truncate text-[11px] font-semibold">1. Data Diri</span>
                </button>
                <button type="button" onclick="goToStep(2)" id="mobile-step-btn-2" class="py-2.5 px-2 rounded-lg text-xs font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-all flex items-center justify-center gap-1.5">
                    <span class="w-4 h-4 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-[9px] font-bold flex items-center justify-center shrink-0">2</span>
                    <span class="truncate text-[11px] font-semibold">2. Dokumen</span>
                </button>
                <button type="button" onclick="goToStep(3)" id="mobile-step-btn-3" class="py-2.5 px-2 rounded-lg text-xs font-medium text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 transition-all flex items-center justify-center gap-1.5">
                    <span class="w-4 h-4 rounded-full bg-emerald-500 text-white text-[9px] font-bold flex items-center justify-center shrink-0">✓</span>
                    <span class="truncate text-[11px] font-semibold">3. Layanan</span>
                </button>
                <button type="button" onclick="goToStep(4)" id="mobile-step-btn-4" class="py-2.5 px-2 rounded-lg text-xs font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-all flex items-center justify-center gap-1.5">
                    <span class="w-4 h-4 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-[9px] font-bold flex items-center justify-center shrink-0">4</span>
                    <span class="truncate text-[11px] font-semibold">4. Laporan</span>
                </button>
            </div>
        </div>

        <!-- MAIN GRID LAYOUT -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            <!-- LEFT COLUMN: Desktop Stepper Checklist (Visible on >= lg screens) -->
            <div class="hidden lg:block lg:col-span-4 space-y-4">
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-xl p-5 shadow-sm space-y-4">
                    <h4 class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Tahapan Laporan</h4>

                    <div class="space-y-3">
                        <!-- Step 1 Navigation Card (Read-only) -->
                        <button type="button" onclick="goToStep(1)" id="step-nav-1" class="w-full text-left p-3.5 rounded-xl border-2 border-sky-500 bg-sky-50/50 dark:bg-sky-900/20 transition-all group focus:outline-none">
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 shrink-0" id="step-nav-icon-1">
                                    <span class="w-6 h-6 rounded-full bg-emerald-500 text-white flex items-center justify-center">
                                        <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'check','class' => 'w-3 h-3']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'check','class' => 'w-3 h-3']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
                                    </span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <span class="block text-xs font-bold text-slate-900 dark:text-slate-100">1. Data Diri Pelanggan</span>
                                    <span class="text-[9px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 block mt-0.5">Lengkap</span>
                                    <span class="text-[10px] text-slate-400 dark:text-slate-500 block mt-1">Data Read-only dari registrasi</span>
                                </div>
                            </div>
                        </button>

                        <!-- Step 2 Navigation Card -->
                        <button type="button" onclick="goToStep(2)" id="step-nav-2" class="w-full text-left p-3.5 rounded-xl border border-rose-200 dark:border-rose-900/50 bg-rose-50/40 dark:bg-rose-950/20 hover:bg-rose-50 dark:hover:bg-rose-900/30 transition-all group focus:outline-none">
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 shrink-0" id="step-nav-icon-2">
                                    <span class="w-6 h-6 rounded-full bg-rose-100 dark:bg-rose-900/40 border border-rose-200 dark:border-rose-700 flex items-center justify-center text-rose-600 dark:text-rose-400">
                                        <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'x','class' => 'w-3 h-3']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'x','class' => 'w-3 h-3']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
                                    </span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <span class="block text-xs font-bold text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-slate-100">2. Dokumen Lampiran</span>
                                    <span id="step-nav-status-2" class="text-[9px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400 block mt-0.5">Belum Lengkap</span>
                                    <span id="step-nav-missing-2" class="text-[10px] text-slate-500 dark:text-slate-400 block mt-1 leading-relaxed">Wajib diisi: Foto Rumah, Foto ODP Terdekat</span>
                                </div>
                            </div>
                        </button>

                        <!-- Step 3 Navigation Card (Read-only) -->
                        <button type="button" onclick="goToStep(3)" id="step-nav-3" class="w-full text-left p-3.5 rounded-xl border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/40 dark:bg-emerald-950/20 transition-all group focus:outline-none">
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 shrink-0" id="step-nav-icon-3">
                                    <span class="w-6 h-6 rounded-full bg-emerald-500 text-white flex items-center justify-center">
                                        <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'check','class' => 'w-3 h-3']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'check','class' => 'w-3 h-3']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
                                    </span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <span class="block text-xs font-bold text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-slate-100">3. Layanan &amp; Paket</span>
                                    <span class="text-[9px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 block mt-0.5">Lengkap</span>
                                    <span class="text-[10px] text-slate-400 dark:text-slate-500 block mt-1">Data Read-only</span>
                                </div>
                            </div>
                        </button>

                        <!-- Step 4 Navigation Card -->
                        <button type="button" onclick="goToStep(4)" id="step-nav-4" class="w-full text-left p-3.5 rounded-xl border border-rose-200 dark:border-rose-900/50 bg-rose-50/40 dark:bg-rose-950/20 hover:bg-rose-50 dark:hover:bg-rose-900/30 transition-all group focus:outline-none">
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 shrink-0" id="step-nav-icon-4">
                                    <span class="w-6 h-6 rounded-full bg-rose-100 dark:bg-rose-900/40 border border-rose-200 dark:border-rose-700 flex items-center justify-center text-rose-600 dark:text-rose-400">
                                        <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'x','class' => 'w-3 h-3']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'x','class' => 'w-3 h-3']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
                                    </span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <span class="block text-xs font-bold text-slate-700 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-slate-100">4. Laporan Survey</span>
                                    <span id="step-nav-status-4" class="text-[9px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400 block mt-0.5">Belum Lengkap</span>
                                    <span id="step-nav-missing-4" class="text-[10px] text-slate-500 dark:text-slate-400 block mt-1 leading-relaxed">Wajib diisi: ODP Terdekat, Estimasi Kabel, Tingkat Kesulitan</span>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Wizard Form Content Panels -->
            <div class="lg:col-span-8 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-xl shadow-sm overflow-hidden flex flex-col justify-between min-h-[520px]">

                <!-- FORM BODY -->
                <div class="p-5 sm:p-7 flex-1">

                    <!-- STEP 1 PANEL: Data Diri Pelanggan (Read-Only) -->
                    <div id="step-panel-1" class="step-panel space-y-6">
                        <div class="border-b border-slate-100 dark:border-slate-700/60 pb-3">
                            <h4 class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">1. IDENTITAS PELANGGAN &amp; ALAMAT</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Data identitas calon pelanggan yang diisi pada saat registrasi.</p>
                        </div>

                        <div class="bg-slate-50/70 dark:bg-slate-900/50 rounded-xl p-5 border border-slate-200/60 dark:border-slate-700/60 grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-4 text-xs">
                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Nama Lengkap</span>
                                <span class="block text-sm font-bold text-slate-800 dark:text-slate-100"><?php echo e($customer->full_name); ?></span>
                            </div>

                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Nomor Identitas (NIK)</span>
                                <span class="block text-sm data-text font-semibold text-slate-800 dark:text-slate-100"><?php echo e($customer->identity_number ?? '-'); ?></span>
                            </div>

                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Jenis Kelamin</span>
                                <span class="block text-xs font-semibold text-slate-800 dark:text-slate-200"><?php echo e($customer->gender?->label() ?? '-'); ?></span>
                            </div>

                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Nomor HP Utama</span>
                                <span class="block text-xs data-text font-semibold text-slate-800 dark:text-slate-200"><?php echo e($customer->primary_phone ?? $customer->phone ?? '-'); ?></span>
                            </div>

                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Nomor HP Alternatif</span>
                                <span class="block text-xs <?php echo e($customer->alternative_phone ? 'data-text font-semibold text-slate-800 dark:text-slate-200' : 'text-slate-400 dark:text-slate-500'); ?>"><?php echo e($customer->alternative_phone ?? '-'); ?></span>
                            </div>

                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Alamat Email</span>
                                <span class="block text-xs <?php echo e($customer->email ? 'font-semibold text-slate-800 dark:text-slate-200' : 'text-slate-400 dark:text-slate-500'); ?>"><?php echo e($customer->email ?? '-'); ?></span>
                            </div>

                            <div class="md:col-span-2 pt-3 border-t border-slate-200/60 dark:border-slate-700/60">
                                <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Alamat Instalasi Lengkap</span>
                                <span class="block text-xs font-bold text-slate-900 dark:text-slate-100"><?php echo e($customer->address); ?></span>
                                <span class="block text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                                    Kel. <?php echo e($customer->village->name ?? '-'); ?>,
                                    Kec. <?php echo e($customer->district->name ?? '-'); ?>,
                                    <?php echo e($customer->city->name ?? '-'); ?>

                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-3 md:col-span-1 pt-2">
                                <div>
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Latitude</span>
                                    <span class="block text-xs data-text font-semibold text-slate-800 dark:text-slate-200"><?php echo e($customer->latitude ?? '-'); ?></span>
                                </div>
                                <div>
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Longitude</span>
                                    <span class="block text-xs data-text font-semibold text-slate-800 dark:text-slate-200"><?php echo e($customer->longitude ?? '-'); ?></span>
                                </div>
                            </div>

                            <div class="pt-2 md:col-span-1">
                                <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">POP Cabang</span>
                                <span class="block text-xs font-bold text-sky-600 dark:text-sky-400"><?php echo e($customer->pop->name ?? '-'); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 2 PANEL: Dokumen Lampiran -->
                    <div id="step-panel-2" class="step-panel space-y-6 hidden">
                        <div class="border-b border-slate-100 dark:border-slate-700/60 pb-3">
                            <h4 class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">2. UPLOAD DOKUMEN LAMPIRAN</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Tinjau foto KTP dari registrasi, lalu unggah Foto Rumah dan Foto ODP / Jalur terdekat.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                            <!-- Foto KTP (Read Only dari Registrasi) -->
                            <div class="border border-slate-200 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/60 rounded-xl p-4 flex flex-col justify-between shadow-sm text-center">
                                <?php if($customer->foto_ktp): ?>
                                    <div>
                                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">Foto KTP (Registrasi)</span>
                                        <div class="relative rounded-lg overflow-hidden border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                                            <img class="max-h-36 max-w-full rounded-lg object-contain mx-auto hover:scale-105 transition-transform cursor-pointer"
                                                 src="<?php echo e(asset('storage/' . $customer->foto_ktp)); ?>"
                                                 alt="Preview Foto KTP"
                                                 onclick="window.open('<?php echo e(asset('storage/' . $customer->foto_ktp)); ?>', '_blank')">
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center justify-center gap-1 text-[11px] font-bold text-emerald-600 dark:text-emerald-400 mt-3 bg-emerald-50 dark:bg-emerald-950/40 py-1 px-2.5 rounded-full border border-emerald-200 dark:border-emerald-800">
                                        <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'circle-check','class' => 'w-3 h-3']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'circle-check','class' => 'w-3 h-3']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?> Terlampir di System
                                    </span>
                                <?php else: ?>
                                    <div class="py-4">
                                        <div class="w-10 h-10 mx-auto rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 flex items-center justify-center text-lg border border-slate-200 dark:border-slate-700">
                                            <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'id-card','class' => 'w-4 h-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'id-card','class' => 'w-4 h-4']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
                                        </div>
                                        <span class="block text-xs font-bold text-slate-700 dark:text-slate-300 mt-3 uppercase">Foto KTP Kosong</span>
                                    </div>
                                    <span class="inline-flex items-center justify-center gap-1 text-[11px] font-bold text-amber-600 dark:text-amber-400 mt-3 bg-amber-50 dark:bg-amber-950/40 py-1 px-2.5 rounded-full border border-amber-200 dark:border-amber-800">
                                        <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'triangle-alert','class' => 'w-3 h-3']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'triangle-alert','class' => 'w-3 h-3']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?> Belum diunggah
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Foto Rumah (Input Upload Baru) -->
                            <div class="border-2 border-dashed <?php $__errorArgs = ['house_photo'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-400 bg-rose-50/20 <?php else: ?> border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> hover:border-sky-500 dark:hover:border-sky-400 rounded-xl p-4 text-center transition-all shadow-sm flex flex-col justify-between relative group">
                                <div id="default-placeholder-house_photo" class="py-4 space-y-2">
                                    <div class="w-10 h-10 mx-auto rounded-full bg-sky-50 dark:bg-sky-900/40 text-sky-600 dark:text-sky-400 flex items-center justify-center text-lg border border-sky-200 dark:border-sky-800">
                                        <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'house','class' => 'w-4 h-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'house','class' => 'w-4 h-4']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
                                    </div>
                                    <div>
                                        <span class="block text-xs font-bold text-slate-800 dark:text-slate-200">FOTO RUMAH <span class="text-rose-500">*</span></span>
                                        <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">Wajib Diisi (JPG, PNG)</span>
                                    </div>
                                </div>

                                <div id="preview-container-house_photo" style="display: none;" class="py-2 flex flex-col items-center justify-center">
                                    <div class="relative inline-block w-full">
                                        <img id="preview-img-house_photo" class="max-h-32 max-w-full rounded-lg object-contain border border-slate-200 dark:border-slate-700 shadow-sm mx-auto" src="" alt="Preview Foto Rumah">
                                        <button type="button" onclick="clearFile('house_photo')" class="absolute -top-2.5 -right-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-full w-6 h-6 flex items-center justify-center shadow-md hover:scale-110 transition-transform focus:outline-none cursor-pointer" title="Hapus File">
                                            <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'x','class' => 'w-3 h-3']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'x','class' => 'w-3 h-3']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
                                        </button>
                                    </div>
                                    <span class="block text-[10px] font-bold text-emerald-600 dark:text-emerald-400 mt-2">✓ Foto Rumah Terpilih</span>
                                </div>

                                <div class="mt-2">
                                    <input type="file" name="house_photo" id="house_photo" accept="image/*" capture="environment" class="hidden" onchange="onFileChange('house_photo')">
                                    <label for="house_photo" class="block w-full text-center bg-sky-600 hover:bg-sky-700 text-white text-[11px] font-semibold py-2 px-3 rounded-lg cursor-pointer transition-colors shadow-sm focus:outline-none">
                                        Pilih Foto Rumah
                                    </label>
                                    <span id="file-label-house_photo" class="block text-[10px] text-slate-400 dark:text-slate-500 text-center mt-1.5 font-mono truncate">Belum ada file</span>
                                </div>

                                <?php $__errorArgs = ['house_photo'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <p class="text-[10px] text-rose-600 dark:text-rose-400 mt-2"><?php echo e($message); ?></p>
                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                            </div>

                            <!-- Foto ODP (Input Upload Baru) -->
                            <div class="border-2 border-dashed <?php $__errorArgs = ['survey_photo'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-400 bg-rose-50/20 <?php else: ?> border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> hover:border-sky-500 dark:hover:border-sky-400 rounded-xl p-4 text-center transition-all shadow-sm flex flex-col justify-between relative group">
                                <div id="default-placeholder-survey_photo" class="py-4 space-y-2">
                                    <div class="w-10 h-10 mx-auto rounded-full bg-sky-50 dark:bg-sky-900/40 text-sky-600 dark:text-sky-400 flex items-center justify-center text-lg border border-sky-200 dark:border-sky-800">
                                        <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'network','class' => 'w-4 h-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'network','class' => 'w-4 h-4']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
                                    </div>
                                    <div>
                                        <span class="block text-xs font-bold text-slate-800 dark:text-slate-200">FOTO ODP / JALUR <span class="text-rose-500">*</span></span>
                                        <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">Wajib Diisi (JPG, PNG)</span>
                                    </div>
                                </div>

                                <div id="preview-container-survey_photo" style="display: none;" class="py-2 flex flex-col items-center justify-center">
                                    <div class="relative inline-block w-full">
                                        <img id="preview-img-survey_photo" class="max-h-32 max-w-full rounded-lg object-contain border border-slate-200 dark:border-slate-700 shadow-sm mx-auto" src="" alt="Preview Foto ODP">
                                        <button type="button" onclick="clearFile('survey_photo')" class="absolute -top-2.5 -right-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-full w-6 h-6 flex items-center justify-center shadow-md hover:scale-110 transition-transform focus:outline-none cursor-pointer" title="Hapus File">
                                            <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'x','class' => 'w-3 h-3']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'x','class' => 'w-3 h-3']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
                                        </button>
                                    </div>
                                    <span class="block text-[10px] font-bold text-emerald-600 dark:text-emerald-400 mt-2">✓ Foto ODP Terpilih</span>
                                </div>

                                <div class="mt-2">
                                    <input type="file" name="survey_photo" id="survey_photo" accept="image/*" capture="environment" class="hidden" onchange="onFileChange('survey_photo')">
                                    <label for="survey_photo" class="block w-full text-center bg-sky-600 hover:bg-sky-700 text-white text-[11px] font-semibold py-2 px-3 rounded-lg cursor-pointer transition-colors shadow-sm focus:outline-none">
                                        Pilih Foto ODP
                                    </label>
                                    <span id="file-label-survey_photo" class="block text-[10px] text-slate-400 dark:text-slate-500 text-center mt-1.5 font-mono truncate">Belum ada file</span>
                                </div>

                                <?php $__errorArgs = ['survey_photo'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <p class="text-[10px] text-rose-600 dark:text-rose-400 mt-2"><?php echo e($message); ?></p>
                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                            </div>

                        </div>
                    </div>

                    <!-- STEP 3 PANEL: Layanan & Paket (Read-Only) -->
                    <div id="step-panel-3" class="step-panel space-y-6 hidden">
                        <div class="border-b border-slate-100 dark:border-slate-700/60 pb-3">
                            <h4 class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">3. LAYANAN &amp; PAKET LAYANAN INTERNET</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Rincian paket layanan internet yang dipilih saat pendaftaran.</p>
                        </div>

                        <div class="bg-slate-50/70 dark:bg-slate-900/50 rounded-xl p-5 border border-slate-200/60 dark:border-slate-700/60 grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-4 text-xs">
                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Paket Internet</span>
                                <span class="block text-sm font-bold text-slate-800 dark:text-slate-100"><?php echo e($customer->internetPackage->package_code ?? '-'); ?> - <?php echo e($customer->internetPackage->name ?? 'Belum Dipilih'); ?></span>
                            </div>

                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Biaya Bulanan Dasar</span>
                                <span class="block text-sm data-text font-bold text-slate-800 dark:text-slate-100">Rp <?php echo e(number_format($customer->internetPackage->monthly_price ?? 0, 0, ',', '.')); ?></span>
                            </div>

                            <div class="pt-3 border-t border-slate-200/60 dark:border-slate-700/60 md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Jenis Kontrak</span>
                                    <span class="block text-xs font-bold uppercase text-slate-800 dark:text-slate-200"><?php echo e($customer->customerService->contract_type ?? 'Sewa'); ?></span>
                                </div>

                                <div>
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Masa Kontrak</span>
                                    <span class="block text-xs font-semibold text-slate-800 dark:text-slate-200"><?php echo e($customer->customerService->contract_period_months ?? 12); ?> Bulan</span>
                                </div>

                                <div>
                                    <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Diskon Promosi</span>
                                    <span class="block text-xs data-text font-semibold text-slate-800 dark:text-slate-200">Rp <?php echo e(number_format($customer->discount_amount ?? 0, 0, ',', '.')); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 4 PANEL: Laporan Survey Lapangan -->
                    <div id="step-panel-4" class="step-panel space-y-6 hidden">
                        <div class="border-b border-slate-100 dark:border-slate-700/60 pb-3">
                            <h4 class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">4. LAPORAN SURVEY LAPANGAN</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Masukkan data teknis hasil pengamatan dan estimasi kebutuhan di lokasi.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-4 text-xs">
                            <!-- ODP Terdekat -->
                            <div>
                                <label for="nearest_odp" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">ODP Terdekat <span class="text-rose-500">*</span></label>
                                <input type="text" name="nearest_odp" id="nearest_odp" value="<?php echo e(old('nearest_odp')); ?>" class="w-full text-xs data-text px-3 py-2.5 border <?php $__errorArgs = ['nearest_odp'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors" placeholder="Contoh: ODP-BBD-01">
                                <?php $__errorArgs = ['nearest_odp'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1"><?php echo e($message); ?></p>
                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                            </div>

                            <!-- Estimasi Kabel -->
                            <div>
                                <label for="cable_estimation_meter" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Estimasi Kabel (Meter) <span class="text-rose-500">*</span></label>
                                <input type="number" name="cable_estimation_meter" id="cable_estimation_meter" min="0" value="<?php echo e(old('cable_estimation_meter')); ?>" class="w-full text-xs data-text px-3 py-2.5 border <?php $__errorArgs = ['cable_estimation_meter'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors" placeholder="Contoh: 150">
                                <?php $__errorArgs = ['cable_estimation_meter'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1"><?php echo e($message); ?></p>
                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                            </div>

                            <!-- Tanggal Request Pemasangan (opsional) -->
                            <div>
                                <label for="requested_installation_date" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Tanggal Request Pemasangan</label>
                                <input type="date" name="requested_installation_date" id="requested_installation_date" min="<?php echo e(now()->toDateString()); ?>" value="<?php echo e(old('requested_installation_date')); ?>" class="w-full text-xs font-sans px-3 py-2.5 border <?php $__errorArgs = ['requested_installation_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors">
                                <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-1 leading-relaxed">Kosongkan jika pelanggan tidak meminta tanggal spesifik. Diisi hanya bila pelanggan minta dipasang di tanggal tertentu — task pemasangan menunggu sampai tanggal itu tiba.</span>
                                <?php $__errorArgs = ['requested_installation_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1"><?php echo e($message); ?></p>
                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                            </div>

                            <!-- Tingkat Kesulitan -->
                            <div>
                                <label for="difficulty_level" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Tingkat Kesulitan <span class="text-rose-500">*</span></label>
                                <select name="difficulty_level" id="difficulty_level" class="w-full text-xs font-sans px-3 py-2.5 border <?php $__errorArgs = ['difficulty_level'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors">
                                    <option value="" disabled <?php echo e(old('difficulty_level') ? '' : 'selected'); ?>>Pilih Tingkat Kesulitan</option>
                                    <option value="MUDAH" <?php echo e(old('difficulty_level') === 'MUDAH' ? 'selected' : ''); ?>>MUDAH</option>
                                    <option value="SEDANG" <?php echo e(old('difficulty_level') === 'SEDANG' ? 'selected' : ''); ?>>SEDANG</option>
                                    <option value="SULIT" <?php echo e(old('difficulty_level') === 'SULIT' ? 'selected' : ''); ?>>SULIT</option>
                                </select>
                                <?php $__errorArgs = ['difficulty_level'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1"><?php echo e($message); ?></p>
                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                            </div>

                            <!-- Estimasi Kebutuhan Alat (material terstruktur) -->
                            <div class="md:col-span-2 pt-3 border-t border-slate-100 dark:border-slate-700/60">
                                <label class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Estimasi Kebutuhan Alat &amp; Material</label>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mb-3 leading-relaxed">Perkiraan material yang akan dipakai saat pemasangan. Teknisi pemasangan mengisi realisasinya, dan selisihnya dipakai admin verifikasi. Estimasi kabel di atas otomatis jadi satu baris.</p>
                                <?php if (isset($component)) { $__componentOriginalb68f671ec54a2cca8cb81c23fde57f6e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb68f671ec54a2cca8cb81c23fde57f6e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.material-rows','data' => ['name' => 'materials','items' => $items,'categories' => $itemCategories,'rows' => $materialRows,'emptyLabel' => 'Belum ada material diestimasi. Klik Tambah Barang bila perlu.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('material-rows'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'materials','items' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($items),'categories' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($itemCategories),'rows' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($materialRows),'empty-label' => 'Belum ada material diestimasi. Klik Tambah Barang bila perlu.']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb68f671ec54a2cca8cb81c23fde57f6e)): ?>
<?php $attributes = $__attributesOriginalb68f671ec54a2cca8cb81c23fde57f6e; ?>
<?php unset($__attributesOriginalb68f671ec54a2cca8cb81c23fde57f6e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb68f671ec54a2cca8cb81c23fde57f6e)): ?>
<?php $component = $__componentOriginalb68f671ec54a2cca8cb81c23fde57f6e; ?>
<?php unset($__componentOriginalb68f671ec54a2cca8cb81c23fde57f6e); ?>
<?php endif; ?>
                            </div>

                            <!-- Alat kerja terstruktur — dibaca teknisi pemasangan sebelum berangkat -->
                            <div class="md:col-span-2 pt-3 border-t border-slate-100 dark:border-slate-700/60">
                                <?php if (isset($component)) { $__componentOriginal88d1d7b146291f94782fde2f624df93c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal88d1d7b146291f94782fde2f624df93c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.work-tool-checklist','data' => ['name' => 'work_tools','tools' => $workTools,'rows' => $workToolRows,'label' => 'Alat Kerja Yang Perlu Dibawa Teknisi','hint' => 'Centang alat yang harus dibawa tim pemasangan nanti. Ini hasil pengamatan medan Anda — teknisi pemasangan membacanya sebelum berangkat. Material habis pakai dicatat di daftar di atas, bukan di sini.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('work-tool-checklist'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'work_tools','tools' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($workTools),'rows' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($workToolRows),'label' => 'Alat Kerja Yang Perlu Dibawa Teknisi','hint' => 'Centang alat yang harus dibawa tim pemasangan nanti. Ini hasil pengamatan medan Anda — teknisi pemasangan membacanya sebelum berangkat. Material habis pakai dicatat di daftar di atas, bukan di sini.']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal88d1d7b146291f94782fde2f624df93c)): ?>
<?php $attributes = $__attributesOriginal88d1d7b146291f94782fde2f624df93c; ?>
<?php unset($__attributesOriginal88d1d7b146291f94782fde2f624df93c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal88d1d7b146291f94782fde2f624df93c)): ?>
<?php $component = $__componentOriginal88d1d7b146291f94782fde2f624df93c; ?>
<?php unset($__componentOriginal88d1d7b146291f94782fde2f624df93c); ?>
<?php endif; ?>
                            </div>

                            <!-- Kendala peralatan (catatan bebas, BUKAN daftar alat maupun material) -->
                            <div class="md:col-span-2">
                                <label for="required_tools" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Catatan Kendala Peralatan</label>
                                <textarea name="required_tools" id="required_tools" rows="2" class="w-full text-xs font-sans px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors" placeholder="Cth: Tangga harus ekstra panjang, lokasi masuk gang sempit..."><?php echo e(old('required_tools')); ?></textarea>
                                <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-1">Keterangan yang tidak masuk daftar centang di atas — kendala akses lokasi, spesifikasi alat yang tidak biasa.</span>
                            </div>

                            <!-- Catatan Teknis Survey -->
                            <div class="md:col-span-2">
                                <label for="survey_note" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Catatan Teknis Survey</label>
                                <textarea name="survey_note" id="survey_note" rows="3" class="w-full text-xs font-sans px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors" placeholder="Tuliskan kendala teknis atau informasi penting untuk tim instalasi..."><?php echo e(old('survey_note')); ?></textarea>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- BUTTONS NAVIGATION FOOTER -->
                <div class="px-4 sm:px-7 py-3.5 sm:py-4 bg-slate-50/90 dark:bg-slate-900/60 border-t border-slate-200 dark:border-slate-700/60 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-2.5 sm:gap-3 shrink-0">
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <button type="button" id="btn-prev" onclick="prevStep()" style="display: none;" class="w-full sm:w-auto px-4 py-2.5 sm:py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors text-xs font-semibold cursor-pointer focus:outline-none inline-flex items-center justify-center gap-1.5 shadow-sm">
                            <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'chevron-left','class' => 'w-2.5 h-2.5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'chevron-left','class' => 'w-2.5 h-2.5']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?> Sebelumnya
                        </button>
                        <a href="<?php echo e(route('surveys.queue')); ?>" class="w-full sm:w-auto px-4 py-2.5 sm:py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors text-xs font-semibold cursor-pointer focus:outline-none text-center inline-flex items-center justify-center">
                            Batal
                        </a>
                    </div>

                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <button type="button" id="btn-next" onclick="nextStep()" class="w-full sm:w-auto px-5 py-2.5 sm:py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg transition-colors text-xs font-semibold cursor-pointer focus:outline-none inline-flex items-center justify-center gap-1.5 shadow-sm">
                            Lanjut <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'chevron-right','class' => 'w-2.5 h-2.5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'chevron-right','class' => 'w-2.5 h-2.5']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
                        </button>

                        <button type="submit" id="btn-submit" style="display: none;" class="w-full sm:w-auto px-6 py-2.5 sm:py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors text-xs font-semibold cursor-pointer focus:outline-none inline-flex items-center justify-center gap-1.5 shadow-sm">
                            <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'save','class' => 'w-3 h-3']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'save','class' => 'w-3 h-3']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?> Simpan Laporan Survey
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
<script>
    /* ── Wizard Form Stepper & Live Validation Logic ── */
    let currentActiveStep = 1;
    const totalStepsCount = 4;

    // Step 1 & 3 read-only (data registrasi), jadi tidak punya konfigurasi field.
    const formFields = {
        'dokumen': {
            required: ['house_photo', 'survey_photo'],
            optional: []
        },
        'laporan': {
            required: ['nearest_odp', 'cable_estimation_meter', 'difficulty_level'],
            // requested_installation_date sengaja optional — mayoritas pelanggan
            // tidak minta tanggal tertentu, jadi tidak boleh menahan progress bar.
            optional: ['required_tools', 'survey_note', 'requested_installation_date']
        }
    };

    const stepKeys = {
        2: 'dokumen',
        4: 'laporan'
    };

    // Step yang punya field wajib — sisanya read-only dan selalu dianggap lengkap.
    const inputSteps = [2, 4];

    // Step read-only: selalu hijau kecuali sedang aktif.
    const readOnlySteps = [1, 3];

    document.addEventListener("DOMContentLoaded", function() {
        const inputs = document.querySelectorAll('#wizard-form input, #wizard-form select, #wizard-form textarea');
        inputs.forEach(input => {
            input.addEventListener('input', runLiveProgressUpdates);
            input.addEventListener('change', runLiveProgressUpdates);
        });

        updateWizardButtons();
        runLiveProgressUpdates();
    });

    /*
     * Sengaja pakai inline style, BUKAN class 'hidden': elemen-elemen ini juga
     * memakai utility display ('inline-flex' / 'flex'), dan di CSS Tailwind
     * keduanya utility display dengan specificity sama — yang belakangan menang,
     * sehingga 'hidden' tidak berefek. Inline style selalu menang.
     */
    function setElementVisible(el, visible) {
        if (! el) {
            return;
        }
        // String kosong = balik ke display dari class, bukan dipaksa 'block'.
        el.style.display = visible ? '' : 'none';
    }

    /*
     * Aturan tombol wizard — satu-satunya tempat visibilitas tombol ditentukan.
     * Step pertama  : Batal + Lanjut
     * Step tengah   : Sebelumnya + Batal + Lanjut
     * Step terakhir : Sebelumnya + Batal + Simpan Laporan Survey
     * "Batal" selalu tampil, jadi tidak ikut diatur di sini.
     */
    function updateWizardButtons() {
        const isFirstStep = currentActiveStep === 1;
        const isLastStep = currentActiveStep === totalStepsCount;

        setElementVisible(document.getElementById('btn-prev'), ! isFirstStep);
        setElementVisible(document.getElementById('btn-next'), ! isLastStep);
        setElementVisible(document.getElementById('btn-submit'), isLastStep);
    }

    /* File Change & Preview Helper */
    function onFileChange(fieldId) {
        const input = document.getElementById(fieldId);
        const label = document.getElementById('file-label-' + fieldId);
        const defaultPlaceholder = document.getElementById('default-placeholder-' + fieldId);
        const previewContainer = document.getElementById('preview-container-' + fieldId);
        const previewImg = document.getElementById('preview-img-' + fieldId);

        if (input.files && input.files.length > 0) {
            const file = input.files[0];
            label.textContent = file.name;
            input.setAttribute('data-populated', 'true');

            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (previewImg) previewImg.src = e.target.result;
                    if (defaultPlaceholder) defaultPlaceholder.classList.add('hidden');
                    setElementVisible(previewContainer, true);
                };
                reader.readAsDataURL(file);
            }
        } else {
            label.textContent = "Belum ada file";
            input.removeAttribute('data-populated');
            if (defaultPlaceholder) defaultPlaceholder.classList.remove('hidden');
            setElementVisible(previewContainer, false);
            if (previewImg) previewImg.src = '';
        }
        runLiveProgressUpdates();
    }

    function clearFile(fieldId) {
        const input = document.getElementById(fieldId);
        if (input) {
            input.value = '';
            onFileChange(fieldId);
        }
    }

    /* Live Stepper Auditor & Progress Calculator */
    function runLiveProgressUpdates() {
        let totalRequiredFieldsCount = 0;
        let filledRequiredFieldsCount = 0;

        inputSteps.forEach(step => {
            const config = formFields[stepKeys[step]];
            let requiredMissing = [];
            let optionalMissing = [];

            config.required.forEach(field => {
                totalRequiredFieldsCount++;
                const el = document.getElementById(field);
                if (el) {
                    const isFilePopulated = el.type === 'file' && el.getAttribute('data-populated') === 'true';
                    if (el.value.trim() !== "" || isFilePopulated) {
                        filledRequiredFieldsCount++;
                    } else {
                        requiredMissing.push(getLabelName(field));
                    }
                }
            });

            config.optional.forEach(field => {
                const el = document.getElementById(field);
                if (el && el.value.trim() === "") {
                    optionalMissing.push(getLabelName(field));
                }
            });

            updateStepNavStatus(step, requiredMissing, optionalMissing);
        });

        const progressPercentage = totalRequiredFieldsCount > 0 ? Math.round((filledRequiredFieldsCount / totalRequiredFieldsCount) * 100) : 0;
        const pctEl = document.getElementById('progress-percentage');
        const filledEl = document.getElementById('filled-fields-count');
        const totalEl = document.getElementById('total-fields-count');
        const fillEl = document.getElementById('progress-bar-fill');

        if (pctEl) pctEl.textContent = progressPercentage + '%';
        if (filledEl) filledEl.textContent = filledRequiredFieldsCount;
        if (totalEl) totalEl.textContent = totalRequiredFieldsCount;
        if (fillEl) fillEl.style.width = progressPercentage + '%';
    }

    function updateStepNavStatus(step, requiredMissing, optionalMissing) {
        const navBtn = document.getElementById('step-nav-' + step);
        const iconDiv = document.getElementById('step-nav-icon-' + step);
        const statusSpan = document.getElementById('step-nav-status-' + step);
        const missingSpan = document.getElementById('step-nav-missing-' + step);

        if (!navBtn || !iconDiv || !statusSpan || !missingSpan) return;

        iconDiv.innerHTML = '';
        missingSpan.textContent = '';

        if (requiredMissing.length > 0) {
            statusSpan.textContent = 'Belum Lengkap';
            statusSpan.className = 'text-[9px] font-bold block uppercase tracking-wider text-rose-600 dark:text-rose-400 mt-0.5';
            iconDiv.innerHTML = `<span class="w-6 h-6 rounded-full bg-rose-100 dark:bg-rose-900/40 border border-rose-200 dark:border-rose-700 flex items-center justify-center text-rose-600 dark:text-rose-400"><?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'x','class' => 'w-3 h-3']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'x','class' => 'w-3 h-3']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?></span>`;
            missingSpan.textContent = 'Wajib diisi: ' + requiredMissing.join(', ');

            if (currentActiveStep !== step) {
                navBtn.className = "w-full text-left p-3.5 rounded-xl border border-rose-200 dark:border-rose-900/50 bg-rose-50/40 dark:bg-rose-950/20 hover:bg-rose-50 dark:hover:bg-rose-900/30 transition-all group focus:outline-none";
            }
        } else {
            statusSpan.textContent = 'Lengkap';
            statusSpan.className = 'text-[9px] font-bold block uppercase tracking-wider text-emerald-600 dark:text-emerald-400 mt-0.5';
            iconDiv.innerHTML = `<span class="w-6 h-6 rounded-full bg-emerald-500 text-white flex items-center justify-center"><?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'check','class' => 'w-3 h-3']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'check','class' => 'w-3 h-3']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?></span>`;
            missingSpan.textContent = optionalMissing.length > 0 ? 'Beberapa opsional kosong' : 'Semua terisi';

            if (currentActiveStep !== step) {
                navBtn.className = "w-full text-left p-3.5 rounded-xl border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/40 dark:bg-emerald-950/20 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-all group focus:outline-none";
            }
        }

        if (currentActiveStep === step) {
            navBtn.className = "w-full text-left p-3.5 rounded-xl border-2 border-sky-500 bg-sky-50/50 dark:bg-sky-900/20 transition-all group focus:outline-none shadow-sm";
        }
    }

    function getLabelName(field) {
        const labels = {
            house_photo: 'Foto Rumah',
            survey_photo: 'Foto ODP Terdekat',
            nearest_odp: 'ODP Terdekat',
            cable_estimation_meter: 'Estimasi Kabel',
            difficulty_level: 'Tingkat Kesulitan',
            required_tools: 'Alat Khusus',
            survey_note: 'Catatan Teknis',
            requested_installation_date: 'Tanggal Request Pemasangan'
        };
        return labels[field] || field;
    }

    /* Stepper Page Switcher */
    function goToStep(stepNumber) {
        document.getElementById('step-panel-' + currentActiveStep).classList.add('hidden');
        currentActiveStep = stepNumber;
        document.getElementById('step-panel-' + currentActiveStep).classList.remove('hidden');

        // Mobile stepper: step read-only tetap hijau, step input netral.
        for (let i = 1; i <= totalStepsCount; i++) {
            const mBtn = document.getElementById('mobile-step-btn-' + i);
            if (! mBtn) continue;

            if (i === currentActiveStep) {
                mBtn.className = "py-2.5 px-2 rounded-lg text-xs font-bold bg-sky-50 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-700 transition-all flex items-center justify-center gap-1.5 shadow-sm";
            } else if (readOnlySteps.includes(i)) {
                mBtn.className = "py-2.5 px-2 rounded-lg text-xs font-medium text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 transition-all flex items-center justify-center gap-1.5";
            } else {
                mBtn.className = "py-2.5 px-2 rounded-lg text-xs font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-all flex items-center justify-center gap-1.5";
            }
        }

        updateWizardButtons();
        runLiveProgressUpdates();

        // Step read-only tidak lewat updateStepNavStatus(), jadi highlight-nya diurus di sini.
        readOnlySteps.forEach(step => {
            const navBtn = document.getElementById('step-nav-' + step);
            if (! navBtn) return;

            if (currentActiveStep === step) {
                navBtn.className = "w-full text-left p-3.5 rounded-xl border-2 border-sky-500 bg-sky-50/50 dark:bg-sky-900/20 transition-all group focus:outline-none shadow-sm";
            } else {
                navBtn.className = "w-full text-left p-3.5 rounded-xl border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/40 dark:bg-emerald-950/20 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-all group focus:outline-none";
            }
        });
    }

    function nextStep() {
        if (currentActiveStep < totalStepsCount) {
            goToStep(currentActiveStep + 1);
        }
    }

    function prevStep() {
        if (currentActiveStep > 1) {
            goToStep(currentActiveStep - 1);
        }
    }
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/surveys/report.blade.php ENDPATH**/ ?>