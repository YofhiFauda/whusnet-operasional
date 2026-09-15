<?php $__env->startSection('title', 'Edit Pelanggan - Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', 'Edit Data Pelanggan'); ?>
<?php $__env->startSection('breadcrumb_parent', 'Pelanggan'); ?>
<?php $__env->startSection('breadcrumb_parent_url', '/customers'); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-6xl mx-auto space-y-6">

    <!-- LAYER 1: NAKED PAGE HEADER (Strict Design System Rule: No card wrapper) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5 flex-wrap">
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-slate-50 tracking-tight">Edit Data Pelanggan</h1>
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
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700/60">
                    <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'id-card','class' => 'w-2.5 h-2.5 mr-1.5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'id-card','class' => 'w-2.5 h-2.5 mr-1.5']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?> <?php echo e($customer->display_id_label); ?>: <?php echo e($customer->display_id); ?>

                </span>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                Ubah data identitas, alamat, POP, dokumen, layanan, referral, dan parameter teknis pelanggan.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="<?php echo e(route('customers.show', $customer->id)); ?>" class="px-3.5 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors inline-flex items-center gap-2">
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
                <span>Kembali ke Detail</span>
            </a>
        </div>
    </div>

    <!-- MAIN FORM CONTAINER -->
    <form action="/customers/<?php echo e($customer->id); ?>" method="POST" enctype="multipart/form-data" id="wizard-form" class="space-y-6">
        <?php echo csrf_field(); ?>
        <?php echo method_field('PUT'); ?>

        <!-- TOP PANEL: Dynamic Completeness Progress Bar -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-xl p-4 sm:p-5 shadow-sm space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-sky-50 dark:bg-sky-900/40 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0 border border-sky-200 dark:border-sky-800/50">
                        <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'list-checks','class' => 'w-3 h-3']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'list-checks','class' => 'w-3 h-3']); ?>
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
                        <h3 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">Kelengkapan Formulir Edit Pelanggan</h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Semua data akan divalidasi sebelum disimpan ke master pelanggan</p>
                    </div>
                </div>
                <div class="text-right">
                    <span id="progress-percentage" class="text-sm sm:text-base font-extrabold text-sky-600 dark:text-sky-400 data-text">0%</span>
                    <span class="text-[11px] text-slate-500 dark:text-slate-400 block"><span id="filled-fields-count" class="data-text font-semibold">0</span> dari <span id="total-fields-count" class="data-text font-semibold">0</span> field terisi</span>
                </div>
            </div>

            <!-- Progress Bar Fill Strip -->
            <div class="w-full bg-slate-100 dark:bg-slate-700/60 rounded-full h-2.5 overflow-hidden border border-slate-200/60 dark:border-slate-700">
                <div id="progress-bar-fill" class="bg-gradient-to-r from-sky-500 to-sky-600 h-full w-0 transition-all duration-500 ease-out" style="width: 0%;"></div>
            </div>
        </div>

        <!-- MOBILE RESPONSIVE STEPPER (Visible on < lg screens: 3 cols) -->
        <div class="lg:hidden bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-xl p-3 shadow-sm">
            <div class="grid grid-cols-3 gap-2 text-center">
                <?php
                    $stepLabels = [
                        1 => 'Identitas',
                        2 => 'Alamat',
                        3 => 'POP',
                        4 => 'Dokumen',
                        5 => 'Layanan',
                        6 => 'Referral',
                        7 => 'Teknis',
                    ];
                ?>
                <?php $__currentLoopData = $stepLabels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stepNumber => $stepLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <button type="button" onclick="goToStep(<?php echo e($stepNumber); ?>)" id="mobile-step-btn-<?php echo e($stepNumber); ?>" class="py-2.5 px-2 rounded-lg text-xs font-medium <?php echo e($stepNumber === 1 ? 'font-bold bg-sky-50 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-700 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/40'); ?> transition-all flex items-center justify-center gap-1.5">
                        <span class="w-4 h-4 rounded-full <?php echo e($stepNumber === 1 ? 'bg-sky-600 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300'); ?> text-[9px] font-bold flex items-center justify-center shrink-0"><?php echo e($stepNumber); ?></span>
                        <span class="truncate text-[11px] font-semibold"><?php echo e($stepLabel); ?></span>
                    </button>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>

        <!-- MAIN GRID LAYOUT -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            <!-- LEFT COLUMN: Desktop Stepper Checklist (Visible on >= lg screens) -->
            <div class="hidden lg:block lg:col-span-4 space-y-4">
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-xl p-5 shadow-sm space-y-4">
                    <h4 class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Tahapan Formulir</h4>

                    <div class="space-y-3">
                        <?php
                            // Label & ikon step mengikuti pengelompokan tab Detail Pelanggan
                            // (identitas/alamat/pop dipisah) supaya urutannya jelas — dulu
                            // semuanya dijejal jadi satu step "Data Diri & Wilayah".
                            $stepIcons = [
                                1 => 'id-card',
                                2 => 'house',
                                3 => 'network',
                                4 => 'file-text',
                                5 => 'calculator',
                                6 => 'send',
                                7 => 'cpu',
                            ];
                        ?>
                        <?php $__currentLoopData = $stepLabels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stepNumber => $stepLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <button type="button" onclick="goToStep(<?php echo e($stepNumber); ?>)" id="step-nav-<?php echo e($stepNumber); ?>" class="w-full text-left p-3.5 rounded-xl border <?php echo e($stepNumber === 1 ? 'border-2 border-sky-500 bg-sky-50/50 dark:bg-sky-900/20' : 'border-slate-200 dark:border-slate-700/60 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/40'); ?> transition-all group focus:outline-none">
                                <div class="flex items-start gap-3">
                                    <div class="mt-0.5 shrink-0" id="step-nav-icon-<?php echo e($stepNumber); ?>">
                                        <span class="w-6 h-6 rounded-full bg-slate-100 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 flex items-center justify-center text-slate-400 dark:text-slate-500">
                                            <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => ''.e($stepIcons[$stepNumber]).'','class' => 'w-3 h-3']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => ''.e($stepIcons[$stepNumber]).'','class' => 'w-3 h-3']); ?>
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
                                        <span class="block text-xs font-bold text-slate-900 dark:text-slate-100"><?php echo e($stepNumber); ?>. <?php echo e($stepLabel); ?></span>
                                        <span id="step-nav-status-<?php echo e($stepNumber); ?>" class="text-[9px] font-bold uppercase tracking-wider block mt-0.5">Mengevaluasi...</span>
                                        <span id="step-nav-missing-<?php echo e($stepNumber); ?>" class="text-[10px] text-slate-500 dark:text-slate-400 block mt-1 leading-relaxed"></span>
                                    </div>
                                </div>
                            </button>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Wizard Steps Form Panels -->
            <div class="lg:col-span-8 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 rounded-xl shadow-sm overflow-hidden min-h-[520px] flex flex-col justify-between">

                <!-- FORM BODY -->
                <div class="p-5 sm:p-7 flex-1">

                    <!-- STEP 1 PANEL: Identitas Pelanggan -->
                    <div id="step-panel-1" class="step-panel space-y-6">
                        <div class="border-b border-slate-100 dark:border-slate-700/60 pb-3">
                            <h4 class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">1. IDENTITAS PELANGGAN</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Ubah data diri dan kontak pelanggan jika diperlukan</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                            <div>
                                <label for="full_name" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Nama Lengkap <span class="text-rose-500">*</span></label>
                                <input type="text" name="full_name" id="full_name" value="<?php echo e(old('full_name', $customer->full_name)); ?>" class="w-full text-xs font-sans px-3 py-2.5 border <?php $__errorArgs = ['full_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors" placeholder="Contoh: Budi Santoso">
                                <?php $__errorArgs = ['full_name'];
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

                            <div>
                                <label for="identity_number" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Nomor Identitas (NIK) <span class="text-rose-500">*</span></label>
                                <input type="text" name="identity_number" id="identity_number" value="<?php echo e(old('identity_number', $customer->identity_number)); ?>" maxlength="16" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 16);" class="w-full text-xs font-mono px-3 py-2.5 border <?php $__errorArgs = ['identity_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors" placeholder="3502182039200001">
                                <?php $__errorArgs = ['identity_number'];
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

                            <div>
                                <label for="gender" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Jenis Kelamin <span class="text-rose-500">*</span></label>
                                <select name="gender" id="gender" class="w-full text-xs font-sans px-3 py-2.5 border <?php $__errorArgs = ['gender'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors">
                                    <option value="" disabled>Pilih Jenis Kelamin</option>
                                    <option value="Laki-laki" <?php echo e(old('gender', $customer->gender?->value) === 'Laki-laki' ? 'selected' : ''); ?>>Laki-laki</option>
                                    <option value="Perempuan" <?php echo e(old('gender', $customer->gender?->value) === 'Perempuan' ? 'selected' : ''); ?>>Perempuan</option>
                                </select>
                                <?php $__errorArgs = ['gender'];
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

                            <div>
                                <label for="primary_phone" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Nomor HP Utama (WhatsApp) <span class="text-rose-500">*</span></label>
                                <input type="text" name="primary_phone" id="primary_phone" value="<?php echo e(old('primary_phone', $customer->primary_phone ?? $customer->phone)); ?>" class="w-full text-xs font-mono px-3 py-2.5 border <?php $__errorArgs = ['primary_phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors" placeholder="082139xxxxxx">
                                <?php $__errorArgs = ['primary_phone'];
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

                            <div>
                                <label for="alternative_phone" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">Nomor HP Alternatif (Opsional)</label>
                                <input type="text" name="alternative_phone" id="alternative_phone" value="<?php echo e(old('alternative_phone', $customer->alternative_phone)); ?>" class="w-full text-xs font-mono px-3 py-2.5 border <?php $__errorArgs = ['alternative_phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors" placeholder="082139xxxxxx">
                                <?php $__errorArgs = ['alternative_phone'];
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

                            <div>
                                <label for="npwp" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">NPWP (Opsional)</label>
                                <input type="text" name="npwp" id="npwp" value="<?php echo e(old('npwp', $customer->npwp)); ?>" class="w-full text-xs font-mono px-3 py-2.5 border <?php $__errorArgs = ['npwp'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors" placeholder="12.345.678.9-012.000">
                                <?php $__errorArgs = ['npwp'];
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

                            <div>
                                <label for="email" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">Alamat Email (Opsional)</label>
                                <input type="email" name="email" id="email" value="<?php echo e(old('email', $customer->email)); ?>" class="w-full text-xs font-sans px-3 py-2.5 border <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors" placeholder="budi@gmail.com">
                                <?php $__errorArgs = ['email'];
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

                            <div>
                                <label for="registration_date" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Tanggal Registrasi <span class="text-rose-500">*</span></label>
                                <input type="date" name="registration_date" id="registration_date" value="<?php echo e(old('registration_date', $customer->registration_date ? $customer->registration_date->format('Y-m-d') : '')); ?>" class="w-full text-xs font-sans px-3 py-2.5 border <?php $__errorArgs = ['registration_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors">
                                <?php $__errorArgs = ['registration_date'];
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
                        </div>
                    </div>

                    <!-- STEP 2 PANEL: Alamat & Lokasi -->
                    <div id="step-panel-2" class="step-panel space-y-6 hidden">
                        <div class="border-b border-slate-100 dark:border-slate-700/60 pb-3">
                            <h4 class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">2. ALAMAT &amp; LOKASI</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Ubah alamat instalasi dan titik koordinat pelanggan</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                            <div class="md:col-span-2">
                                <label for="address" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Alamat Instalasi Lengkap <span class="text-rose-500">*</span></label>
                                <textarea name="address" id="address" rows="2" class="w-full text-xs font-sans px-3 py-2.5 border <?php $__errorArgs = ['address'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors" placeholder="Nama Jalan, RT/RW, nomor rumah, detail lainnya..."><?php echo e(old('address', $customer->address)); ?></textarea>
                                <?php $__errorArgs = ['address'];
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

                            <!-- Region Selection -->
                            <div>
                                <label for="city_id" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Kota / Kabupaten <span class="text-rose-500">*</span></label>
                                <select name="city_id" id="city_id" onchange="loadDistricts(this.value)" class="w-full text-xs font-sans px-3 py-2.5 border <?php $__errorArgs = ['city_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors">
                                    <option value="" disabled selected>Pilih Kota</option>
                                    <?php $__currentLoopData = $cities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $city): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($city->id); ?>" <?php echo e(old('city_id', $customer->city_id) == $city->id ? 'selected' : ''); ?>><?php echo e($city->name); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                                <?php $__errorArgs = ['city_id'];
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

                            <div>
                                <label for="district_id" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Kecamatan <span class="text-rose-500">*</span></label>
                                <select name="district_id" id="district_id" onchange="loadVillages(this.value)" class="w-full text-xs font-sans px-3 py-2.5 border <?php $__errorArgs = ['district_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors">
                                    <option value="" disabled>Pilih Kecamatan (Pilih Kota Dulu)</option>
                                </select>
                                <?php $__errorArgs = ['district_id'];
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

                            <div>
                                <label for="village_id" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Desa / Kelurahan <span class="text-rose-500">*</span></label>
                                <select name="village_id" id="village_id" class="w-full text-xs font-sans px-3 py-2.5 border <?php $__errorArgs = ['village_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors">
                                    <option value="" disabled selected>Pilih Desa</option>
                                    <!-- Async Populated -->
                                </select>
                                <?php $__errorArgs = ['village_id'];
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

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label for="latitude" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">Latitude</label>
                                    <input type="text" name="latitude" id="latitude" value="<?php echo e(old('latitude', $customer->latitude)); ?>" class="w-full text-xs font-mono px-3 py-2.5 border <?php $__errorArgs = ['latitude'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors" placeholder="-7.86940">
                                    <?php $__errorArgs = ['latitude'];
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
                                <div>
                                    <label for="longitude" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">Longitude</label>
                                    <input type="text" name="longitude" id="longitude" value="<?php echo e(old('longitude', $customer->longitude)); ?>" class="w-full text-xs font-mono px-3 py-2.5 border <?php $__errorArgs = ['longitude'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors" placeholder="111.46210">
                                    <?php $__errorArgs = ['longitude'];
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
                            </div>
                        </div>
                    </div>

                    <!-- STEP 3 PANEL: POP & Distribusi -->
                    <div id="step-panel-3" class="step-panel space-y-6 hidden">
                        <div class="border-b border-slate-100 dark:border-slate-700/60 pb-3">
                            <h4 class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">3. POP &amp; DISTRIBUSI</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Ubah penugasan POP Cabang dan kode distribusi (ODP) pelanggan</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                            <div>
                                <label for="pop_id" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">POP Cabang <span class="text-rose-500">*</span></label>
                                <select name="pop_id" id="pop_id" class="w-full text-xs font-sans px-3 py-2.5 border <?php $__errorArgs = ['pop_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors">
                                    <option value="" disabled selected>Pilih POP Cabang</option>
                                    <?php $__currentLoopData = $pops; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pop): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($pop->id); ?>" <?php echo e(old('pop_id', $customer->pop_id) == $pop->id ? 'selected' : ''); ?>><?php echo e($pop->name); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                                <?php $__errorArgs = ['pop_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                    <p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1"><?php echo e($message); ?></p>
                                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1 leading-relaxed">Mini POP diatur terpisah lewat modal "Atur Mini POP &amp; Distribusi" di halaman Detail Pelanggan (pasca pemasangan).</p>
                            </div>

                            <div>
                                <label for="distribution_id" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">KODE DISTRIBUSI (ODP)</label>
                                <select name="distribution_id" id="distribution_id" class="w-full text-xs font-sans px-3 py-2.5 border <?php $__errorArgs = ['distribution_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors">
                                    <option value="">Pilih Kode Distribusi</option>
                                    <?php $__currentLoopData = $distributions ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dist): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($dist->id); ?>" <?php echo e(old('distribution_id', $customer->distribution_id) == $dist->id ? 'selected' : ''); ?>><?php echo e($dist->code); ?> - <?php echo e($dist->name); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                                <?php $__errorArgs = ['distribution_id'];
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

                            <!-- Konteks read-only: POP saat ini & wilayah, biar operator lihat -->
                            <div class="md:col-span-2 pt-3 border-t border-slate-100 dark:border-slate-700/60 bg-slate-50/70 dark:bg-slate-900/50 rounded-xl p-4">
                                <span class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Mini POP Saat Ini</span>
                                <span class="block text-xs font-bold text-slate-800 dark:text-slate-100"><?php echo e($customer->miniPop->name ?? 'Belum diatur'); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 4 PANEL: Dokumen Lampiran -->
                    <div id="step-panel-4" class="step-panel space-y-6 hidden">
                        <div class="border-b border-slate-100 dark:border-slate-700/60 pb-3">
                            <h4 class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">4. UPLOAD DOKUMEN LAMPIRAN</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Ubah lampiran dokumen pendukung pelanggan (opsional)</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Foto Rumah -->
                            <div class="border-2 border-dashed <?php $__errorArgs = ['foto_rumah'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-400 bg-rose-50/20 <?php else: ?> border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> hover:border-sky-500 dark:hover:border-sky-400 rounded-xl p-4 text-center transition-all shadow-sm flex flex-col justify-between relative group">
                                <input type="hidden" name="delete_foto_rumah" id="delete_foto_rumah" value="0">
                                <?php
                                    $fotoRumahUrl = foto_publik($customer->foto_rumah);
                                ?>
                                <div id="default-placeholder-foto_rumah" class="py-4 space-y-2 <?php echo e($fotoRumahUrl ? 'hidden' : ''); ?>">
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
                                    <span class="block text-xs font-bold text-slate-800 dark:text-slate-200">FOTO RUMAH</span>
                                    <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">Format: JPG, PNG (Max 2MB)</span>
                                </div>

                                <div id="preview-container-foto_rumah" style="display: <?php echo e($fotoRumahUrl ? '' : 'none'); ?>;" class="py-2 flex flex-col items-center justify-center">
                                    <div class="relative inline-block w-full">
                                        <img id="preview-img-foto_rumah" class="max-h-32 max-w-full rounded-lg object-contain border border-slate-200 dark:border-slate-700 shadow-sm mx-auto" src="<?php echo e($fotoRumahUrl ?? ''); ?>" alt="Preview Foto Rumah">
                                        <button type="button" onclick="clearFile('foto_rumah')" class="absolute -top-2.5 -right-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-full w-6 h-6 flex items-center justify-center shadow-md hover:scale-110 transition-transform focus:outline-none cursor-pointer" title="Hapus File">
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
                                    <span class="block text-[10px] font-bold text-emerald-600 dark:text-emerald-400 mt-2">✓ Foto Rumah Tersimpan</span>
                                </div>

                                <div class="mt-2">
                                    <input type="file" name="foto_rumah" id="foto_rumah" accept="image/*" capture="environment" class="hidden" onchange="onFileChange('foto_rumah')" <?php if($customer->foto_rumah): ?> data-populated="true" <?php endif; ?>>
                                    <label for="foto_rumah" class="block w-full text-center bg-sky-600 hover:bg-sky-700 text-white text-[11px] font-semibold py-2 px-3 rounded-lg cursor-pointer transition-colors shadow-sm focus:outline-none">
                                        <?php echo e($customer->foto_rumah ? 'Ganti Foto Rumah' : 'Pilih Foto Rumah'); ?>

                                    </label>
                                    <span id="file-label-foto_rumah" class="block text-[10px] text-slate-400 dark:text-slate-500 text-center mt-1.5 font-mono truncate">
                                        <?php if($customer->foto_rumah): ?>
                                            <?php if($fotoRumahUrl): ?>
                                                <?php echo e(basename($customer->foto_rumah)); ?>

                                            <?php else: ?>
                                                [Berkas Tidak Ditemukan] <?php echo e(basename($customer->foto_rumah)); ?>

                                            <?php endif; ?>
                                        <?php else: ?>
                                            Belum ada file
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php $__errorArgs = ['foto_rumah'];
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

                            <!-- Foto Kontrak -->
                            <div class="border-2 border-dashed <?php $__errorArgs = ['foto_kontrak'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-400 bg-rose-50/20 <?php else: ?> border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> hover:border-sky-500 dark:hover:border-sky-400 rounded-xl p-4 text-center transition-all shadow-sm flex flex-col justify-between relative group">
                                <input type="hidden" name="delete_foto_kontrak" id="delete_foto_kontrak" value="0">
                                <?php
                                    $fotoKontrakUrl = foto_publik($customer->foto_kontrak);
                                    $isPdf = $customer->foto_kontrak && Str::endsWith(strtolower($customer->foto_kontrak), '.pdf');
                                ?>
                                <div id="default-placeholder-foto_kontrak" class="py-4 space-y-2 <?php echo e($fotoKontrakUrl ? 'hidden' : ''); ?>">
                                    <div class="w-10 h-10 mx-auto rounded-full bg-sky-50 dark:bg-sky-900/40 text-sky-600 dark:text-sky-400 flex items-center justify-center text-lg border border-sky-200 dark:border-sky-800">
                                        <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'file-text','class' => 'w-4 h-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'file-text','class' => 'w-4 h-4']); ?>
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
                                    <span class="block text-xs font-bold text-slate-800 dark:text-slate-200">FOTO KONTRAK</span>
                                    <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">Format: JPG, PNG, PDF (Max 2MB)</span>
                                </div>

                                <div id="preview-container-foto_kontrak" style="display: <?php echo e($fotoKontrakUrl ? '' : 'none'); ?>;" class="py-2 flex flex-col items-center justify-center">
                                    <div class="relative inline-block w-full">
                                        <img id="preview-img-foto_kontrak" class="max-h-32 max-w-full rounded-lg object-contain border border-slate-200 dark:border-slate-700 shadow-sm mx-auto <?php echo e($isPdf ? 'hidden' : ''); ?>" src="<?php echo e(! $isPdf ? $fotoKontrakUrl : ''); ?>" alt="Preview Foto Kontrak">

                                        <!-- PDF Icon Preview -->
                                        <div id="preview-pdf-foto_kontrak" class="h-28 w-28 mx-auto bg-red-500/10 border border-red-500/20 rounded-lg flex flex-col items-center justify-center text-red-500 shadow-sm <?php echo e(! $isPdf ? 'hidden' : ''); ?>">
                                            <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'file-text','class' => 'w-8 h-8']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'file-text','class' => 'w-8 h-8']); ?>
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
                                            <span class="text-[10px] font-bold mt-2">DOKUMEN PDF</span>
                                        </div>

                                        <button type="button" onclick="clearFile('foto_kontrak')" class="absolute -top-2.5 -right-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-full w-6 h-6 flex items-center justify-center shadow-md hover:scale-110 transition-transform focus:outline-none cursor-pointer" title="Hapus File">
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
                                    <span class="block text-[10px] font-bold text-emerald-600 dark:text-emerald-400 mt-2">✓ Foto Kontrak Tersimpan</span>
                                </div>

                                <div class="mt-2">
                                    <input type="file" name="foto_kontrak" id="foto_kontrak" accept="image/*,application/pdf" capture="environment" class="hidden" onchange="onFileChange('foto_kontrak')" <?php if($customer->foto_kontrak): ?> data-populated="true" <?php endif; ?>>
                                    <label for="foto_kontrak" class="block w-full text-center bg-sky-600 hover:bg-sky-700 text-white text-[11px] font-semibold py-2 px-3 rounded-lg cursor-pointer transition-colors shadow-sm focus:outline-none">
                                        <?php echo e($customer->foto_kontrak ? 'Ganti Foto Kontrak' : 'Pilih Foto Kontrak'); ?>

                                    </label>
                                    <span id="file-label-foto_kontrak" class="block text-[10px] text-slate-400 dark:text-slate-500 text-center mt-1.5 font-mono truncate">
                                        <?php if($customer->foto_kontrak): ?>
                                            <?php if($fotoKontrakUrl): ?>
                                                <?php echo e(basename($customer->foto_kontrak)); ?>

                                            <?php else: ?>
                                                [Berkas Tidak Ditemukan] <?php echo e(basename($customer->foto_kontrak)); ?>

                                            <?php endif; ?>
                                        <?php else: ?>
                                            Belum ada file
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php $__errorArgs = ['foto_kontrak'];
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

                    <!-- STEP 5 PANEL: Layanan & Paket -->
                    <div id="step-panel-5" class="step-panel space-y-6 hidden">
                        <div class="border-b border-slate-100 dark:border-slate-700/60 pb-3">
                            <h4 class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">5. LAYANAN &amp; PAKET LAYANAN INTERNET</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Ubah paket internet dan rincian parameter kontrak berlangganan</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                            <div class="md:col-span-2">
                                <label for="internet_package_id" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Paket Internet <span class="text-rose-500">*</span></label>
                                <select name="internet_package_id" id="internet_package_id" onchange="updateLayananBreakdown()" class="w-full text-xs font-sans px-3 py-2.5 border <?php $__errorArgs = ['internet_package_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors">
                                    <option value="" disabled>Pilih Paket Internet</option>
                                    <?php $__currentLoopData = $packages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $package): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($package->id); ?>" data-price="<?php echo e($package->monthly_price); ?>" <?php echo e(old('internet_package_id', $customer->internet_package_id) == $package->id ? 'selected' : ''); ?>><?php echo e($package->package_code); ?> — <?php echo e($package->name); ?> (Rp <?php echo e(number_format($package->monthly_price, 0, ',', '.')); ?>/bln)</option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                                <?php $__errorArgs = ['internet_package_id'];
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

                            <div>
                                <label for="jenis_kontrak" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Jenis Kontrak</label>
                                <select name="jenis_kontrak" id="jenis_kontrak" class="w-full text-xs font-sans px-3 py-2.5 border <?php $__errorArgs = ['jenis_kontrak'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors">
                                    <option value="" <?php echo e(old('jenis_kontrak', $customer->customerService?->contract_type) ? '' : 'selected'); ?>>— Belum diisi —</option>
                                    <option value="sewa" <?php echo e(old('jenis_kontrak', $customer->customerService?->contract_type) === 'sewa' ? 'selected' : ''); ?>>Sewa (Modem Dipinjamkan ISP)</option>
                                    <option value="beli" <?php echo e(old('jenis_kontrak', $customer->customerService?->contract_type) === 'beli' ? 'selected' : ''); ?>>Beli Putus (Modem Milik Pelanggan)</option>
                                </select>
                                <?php $__errorArgs = ['jenis_kontrak'];
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

                            <div>
                                <label for="contract_period_months" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Masa Kontrak (Bulan) <span class="text-rose-500">*</span></label>
                                <input type="number" name="contract_period_months" id="contract_period_months" min="1" value="<?php echo e(old('contract_period_months', $customer->contract_period_months)); ?>" class="w-full text-xs font-mono px-3 py-2.5 border <?php $__errorArgs = ['contract_period_months'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors" placeholder="12">
                                <?php $__errorArgs = ['contract_period_months'];
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

                            <div>
                                <?php
                                    // Sumber kebenaran diskon/PPN adalah customer_services
                                    // (dipakai Detail Pelanggan & billing sungguhan), BUKAN
                                    // customers.discount_amount/tax_percent — dua kolom itu
                                    // cuma snapshot awal registrasi dan gampang menyimpang dari
                                    // customer_services begitu pelanggan diverifikasi/diubah
                                    // admin (kejadian nyata: pelanggan CID C1X4ARQ000004 py
                                    // customers.tax_percent=11 tapi customer_services.ppn=0 —
                                    // total_monthly_bill sungguhan pakai yang 0). Edit dulu baca
                                    // dari customers, jadi buka halaman ini lalu Simpan TANPA
                                    // ubah apa pun diam-diam menimpa PPN asli dengan angka basi.
                                    // Fallback ke kolom customers cuma kalau belum ada baris
                                    // customer_services sama sekali — pola SAMA PERSIS dengan
                                    // resources/views/customers/show.blade.php baris 14-17.
                                    $currentDiscount = $customer->customerService?->discount ?? $customer->discount_amount ?? 0;
                                    $currentPpn = $customer->customerService ? $customer->customerService->ppn : ($customer->tax_percent ?? 0);
                                ?>
                                <label for="discount_amount" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">Diskon Promosi (Rp) <span class="text-rose-500">*</span></label>
                                
                                <input type="text" inputmode="decimal" data-rupiah name="discount_amount" id="discount_amount" oninput="updateLayananBreakdown()" value="<?php echo e(old('discount_amount', \App\Helpers\FormatHelper::rupiahInput($currentDiscount))); ?>" class="w-full text-xs font-mono px-3 py-2.5 border <?php $__errorArgs = ['discount_amount'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors" placeholder="10000">
                                <?php $__errorArgs = ['discount_amount'];
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

                            <div>
                                <label for="tax_percent" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">PPN (%) <span class="text-rose-500">*</span></label>
                                <input type="number" name="tax_percent" id="tax_percent" oninput="updateLayananBreakdown()" value="<?php echo e(old('tax_percent', $currentPpn)); ?>" class="w-full text-xs font-mono px-3 py-2.5 border <?php $__errorArgs = ['tax_percent'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors" placeholder="11">
                                <?php $__errorArgs = ['tax_percent'];
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

                            <div>
                                <label for="other_fee" class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">Biaya Lain (Materai dkk) — Sekali di Tagihan Registrasi</label>
                                <input type="text" inputmode="decimal" data-rupiah name="other_fee" id="other_fee" oninput="updateLayananBreakdown()" value="<?php echo e(old('other_fee', \App\Helpers\FormatHelper::rupiahInput($customer->customerService?->other_fee ?? 0))); ?>" class="w-full text-xs font-mono px-3 py-2.5 border <?php $__errorArgs = ['other_fee'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors" placeholder="11000">
                                <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1 leading-relaxed">Cth: materai. Cuma masuk <strong>Tagihan Registrasi</strong> (sekali, saat Aktivasi) — <strong class="text-amber-600 dark:text-amber-400">TIDAK pernah ikut Tagihan Bulanan</strong>, lihat pemisahan tab Tagihan di Detail Pelanggan.</p>
                                <?php $__errorArgs = ['other_fee'];
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
                        </div>

                        <!-- Live Price Breakdown Card -->
                        <div id="layanan-breakdown-card" class="bg-sky-50/70 dark:bg-sky-900/20 border border-sky-200 dark:border-sky-800/60 rounded-xl p-4 sm:p-5 space-y-3">
                            <div class="flex items-center justify-between border-b border-sky-200/60 dark:border-sky-800/50 pb-2">
                                <span class="text-xs font-bold text-sky-900 dark:text-sky-200 uppercase tracking-wider flex items-center gap-1.5">
                                    <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'calculator','class' => 'w-4 h-4 text-sky-600 dark:text-sky-400']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'calculator','class' => 'w-4 h-4 text-sky-600 dark:text-sky-400']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?> Rincian Estimasi Tagihan Bulanan
                                </span>
                                <span class="text-[10px] text-sky-700 dark:text-sky-300 bg-sky-100 dark:bg-sky-900/60 px-2 py-0.5 rounded font-semibold">Live Preview</span>
                            </div>
                            <div class="space-y-2 text-xs">
                                <div class="flex justify-between text-slate-600 dark:text-slate-300">
                                    <span>Harga Paket Dasar:</span>
                                    <span id="preview-base-price" class="data-text font-semibold">Rp 0</span>
                                </div>
                                <div class="flex justify-between text-slate-600 dark:text-slate-300">
                                    <span>Diskon Promosi:</span>
                                    <span id="preview-discount" class="data-text font-semibold text-rose-600 dark:text-rose-400">- Rp 0</span>
                                </div>
                                <div class="flex justify-between text-slate-600 dark:text-slate-300">
                                    <span id="preview-tax-label">PPN (11%):</span>
                                    <span id="preview-tax" class="data-text font-semibold">Rp 0</span>
                                </div>
                                <div class="flex justify-between pt-2 border-t border-sky-200/60 dark:border-sky-800/50 text-slate-900 dark:text-slate-100 font-bold text-sm">
                                    <span>Total Tagihan Bulanan (Nett):</span>
                                    <span id="preview-total-monthly" class="data-text text-sky-600 dark:text-sky-400">Rp 0</span>
                                </div>
                                <div class="flex justify-between pt-2 mt-1 border-t border-dashed border-sky-200/60 dark:border-sky-800/50 text-slate-500 dark:text-slate-400">
                                    <span>+ Biaya Lain (sekali, Tagihan Registrasi):</span>
                                    <span id="preview-other-fee" class="data-text font-semibold">Rp 0</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 6 PANEL: Referral -->
                    <div id="step-panel-6" class="step-panel space-y-6 hidden">
                        <div class="border-b border-slate-100 dark:border-slate-700/60 pb-3">
                            <h4 class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">6. INFORMASI REFERRAL &amp; AKUISISI</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Menentukan komisi Sales/Agent</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">ID Sales</label>
                                
                                <?php if(auth()->user()->role?->is_package_restricted): ?>
                                    <input type="text" value="<?php echo e(auth()->user()->name); ?> (Anda)" disabled
                                           class="w-full text-xs font-mono px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400">
                                    <input type="hidden" name="sales_user_id" value="<?php echo e(auth()->id()); ?>">
                                <?php else: ?>
                                    
                                    <p class="text-[10px] text-amber-600 dark:text-amber-400 mb-1.5">
                                        Auto-deteksi ID Sales cuma berlaku untuk role
                                        <span class="font-semibold"><?php echo e($restrictedRoleNames->implode(', ') ?: '(belum ada role diatur)'); ?></span>.
                                        Role Anda (<?php echo e(auth()->user()->role?->name ?? '—'); ?>) di luar itu — pilih manual di bawah.
                                    </p>
                                    <select name="sales_user_id" class="w-full text-xs font-mono px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors">
                                        <option value="">— Tidak ada —</option>
                                        <?php $__currentLoopData = $salesUsers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $salesUser): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($salesUser->id); ?>" <?php echo e(old('sales_user_id', $customer->sales_user_id) == $salesUser->id ? 'selected' : ''); ?>><?php echo e($salesUser->name); ?></option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </select>
                                <?php endif; ?>
                                <?php $__errorArgs = ['sales_user_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                <?php if($customer->sales_code): ?>
                                    <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">Data lama (kode bebas): <span class="font-mono"><?php echo e($customer->sales_code); ?></span></p>
                                <?php endif; ?>
                            </div>

                            <div>
                                <label class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">ID Agent</label>
                                <?php if($agents->isNotEmpty()): ?>
                                    <select name="agent_id" class="w-full text-xs font-mono px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors">
                                        <option value="">— Tidak ada —</option>
                                        <?php $__currentLoopData = $agents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $agent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($agent->id); ?>" <?php echo e(old('agent_id', $customer->agent_id) == $agent->id ? 'selected' : ''); ?>><?php echo e($agent->code); ?> — <?php echo e($agent->name); ?></option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </select>
                                <?php else: ?>
                                    <input type="text" value="<?php echo e($customer->agent?->name ?? '—'); ?>" disabled
                                           class="w-full text-xs font-mono px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400">
                                <?php endif; ?>
                                <?php $__errorArgs = ['agent_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                <?php if($customer->agent_code): ?>
                                    <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">Data lama (kode bebas): <span class="font-mono"><?php echo e($customer->agent_code); ?></span></p>
                                <?php endif; ?>
                            </div>

                            <div class="relative" x-data="referralSearch()">
                                <label class="block mb-1.5 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-400">ID Referral Pelanggan</label>
                                <input type="text" x-model="query" @input.debounce.400ms="search()" placeholder="Cari nama/CID pelanggan existing..."
                                       class="w-full text-xs font-mono px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors">
                                <input type="hidden" name="referral_customer_id" :value="selectedId">
                                <ul x-show="results.length > 0" x-cloak class="absolute z-10 w-full mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg max-h-48 overflow-y-auto text-xs">
                                    <template x-for="r in results" :key="r.id">
                                        <li @click="pick(r)" class="px-3 py-2 hover:bg-sky-50 dark:hover:bg-sky-900/30 cursor-pointer" x-text="r.customer_code + ' — ' + r.full_name"></li>
                                    </template>
                                </ul>
                                <?php $__errorArgs = ['referral_customer_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                <?php if($customer->referral_customer_code): ?>
                                    <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">Data lama (kode bebas): <span class="font-mono"><?php echo e($customer->referral_customer_code); ?></span></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 7 PANEL: Operasional Awal & Teknis -->
                    <div id="step-panel-7" class="step-panel space-y-6 hidden">
                        <?php
                            // Detail perangkat & jaringan terstruktur (2026-09-12) — sumber
                            // aslinya Laporan Pemasangan (CustomerInstallationController::
                            // storePemasangan()), sekarang juga bisa dikoreksi dari sini.
                            // Sudah di-eager-load di CustomerController::edit().
                            $dev7 = $customer->customerDevice;
                            $tech7 = $customer->customerTechnicalDetail;
                        ?>
                        
                        <input type="hidden" name="status" id="status" value="<?php echo e(old('status', $customer->status)); ?>">

                        <div class="border-b border-slate-100 dark:border-slate-700/60 pb-3">
                            <h4 class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">7. PARAMETER TEKNIS</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Ubah konfigurasi data perangkat &amp; jaringan pelanggan</p>
                        </div>

                        <!-- Sub-section: Informasi Perangkat Aktif (sama field dengan Laporan Pemasangan) -->
                        <div class="pt-4 border-t border-slate-100 dark:border-slate-700/60 space-y-3">
                            <h5 class="text-xs font-bold text-sky-700 dark:text-sky-400 uppercase tracking-wider flex items-center gap-1.5">
                                <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'cpu','class' => 'w-4 h-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'cpu','class' => 'w-4 h-4']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?> Informasi Perangkat Aktif (Lengkap)
                            </h5>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">Field yang sama dengan yang diisi teknisi di Laporan Pemasangan. Kosongkan untuk menghapus nilai yang sudah tersimpan.</p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                                <div>
                                    <label for="device_type" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Jenis Perangkat</label>
                                    <select name="device_type" id="device_type" class="w-full text-xs font-sans px-3 py-2 border <?php $__errorArgs = ['device_type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                                        <option value="">— Belum diisi —</option>
                                        <option value="ont" <?php echo e(old('device_type', $dev7->device_type ?? '') === 'ont' ? 'selected' : ''); ?>>ONT</option>
                                        <option value="modem" <?php echo e(old('device_type', $dev7->device_type ?? '') === 'modem' ? 'selected' : ''); ?>>Modem</option>
                                        <option value="onu" <?php echo e(old('device_type', $dev7->device_type ?? '') === 'onu' ? 'selected' : ''); ?>>ONU</option>
                                        <option value="router" <?php echo e(old('device_type', $dev7->device_type ?? '') === 'router' ? 'selected' : ''); ?>>Router</option>
                                        <option value="other" <?php echo e(old('device_type', $dev7->device_type ?? '') === 'other' ? 'selected' : ''); ?>>Lainnya</option>
                                    </select>
                                    <?php $__errorArgs = ['device_type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                </div>

                                <div>
                                    <label for="connection_mode" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Mode Koneksi</label>
                                    <select name="connection_mode" id="connection_mode" class="w-full text-xs font-sans px-3 py-2 border <?php $__errorArgs = ['connection_mode'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                                        <option value="">— Belum diisi —</option>
                                        <option value="pppoe" <?php echo e(old('connection_mode', $dev7->connection_mode ?? '') === 'pppoe' ? 'selected' : ''); ?>>PPPoE</option>
                                        <option value="bridge" <?php echo e(old('connection_mode', $dev7->connection_mode ?? '') === 'bridge' ? 'selected' : ''); ?>>Bridge</option>
                                        <option value="static" <?php echo e(old('connection_mode', $dev7->connection_mode ?? '') === 'static' ? 'selected' : ''); ?>>Static IP</option>
                                        <option value="dhcp" <?php echo e(old('connection_mode', $dev7->connection_mode ?? '') === 'dhcp' ? 'selected' : ''); ?>>DHCP</option>
                                        <option value="router" <?php echo e(old('connection_mode', $dev7->connection_mode ?? '') === 'router' ? 'selected' : ''); ?>>Router</option>
                                        <option value="other" <?php echo e(old('connection_mode', $dev7->connection_mode ?? '') === 'other' ? 'selected' : ''); ?>>Lainnya</option>
                                    </select>
                                    <?php $__errorArgs = ['connection_mode'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                </div>

                                <div>
                                    <label for="brand" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Merk Perangkat</label>
                                    <input type="text" name="brand" id="brand" value="<?php echo e(old('brand', $dev7->brand ?? '')); ?>" class="w-full text-xs font-sans px-3 py-2 border <?php $__errorArgs = ['brand'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="ZTE / Huawei / FiberHome">
                                    <?php $__errorArgs = ['brand'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                </div>

                                <div>
                                    <label for="model" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Tipe Model</label>
                                    <input type="text" name="model" id="model" value="<?php echo e(old('model', $dev7->model ?? '')); ?>" class="w-full text-xs font-sans px-3 py-2 border <?php $__errorArgs = ['model'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="Contoh: F609 / HG8245H">
                                    <?php $__errorArgs = ['model'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                </div>

                                <div>
                                    <label for="serial_number" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Serial Number (SN) Perangkat</label>
                                    <input type="text" name="serial_number" id="serial_number" value="<?php echo e(old('serial_number', $dev7->serial_number ?? '')); ?>" class="w-full text-xs font-mono px-3 py-2 border <?php $__errorArgs = ['serial_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="ZTEGC1234567">
                                    <?php $__errorArgs = ['serial_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                </div>

                                <div>
                                    <label for="mac_address" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">MAC Address</label>
                                    <input type="text" name="mac_address" id="mac_address" value="<?php echo e(old('mac_address', $dev7->mac_address ?? '')); ?>" class="w-full text-xs font-mono px-3 py-2 border <?php $__errorArgs = ['mac_address'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="00:11:22:33:44:55">
                                    <?php $__errorArgs = ['mac_address'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                </div>

                                <div>
                                    <label for="pppoe_username" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Username PPPoE</label>
                                    <input type="text" name="pppoe_username" id="pppoe_username" value="<?php echo e(old('pppoe_username', $dev7->pppoe_username ?? '')); ?>" class="w-full text-xs font-mono px-3 py-2 border <?php $__errorArgs = ['pppoe_username'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="user_ponorogo_01">
                                    <?php $__errorArgs = ['pppoe_username'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                </div>

                                <div>
                                    <label for="pppoe_password" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Password PPPoE</label>
                                    <input type="text" name="pppoe_password" id="pppoe_password" value="<?php echo e(old('pppoe_password', $dev7->pppoe_password ?? '')); ?>" class="w-full text-xs font-mono px-3 py-2 border <?php $__errorArgs = ['pppoe_password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="Password PPPOE">
                                    <?php $__errorArgs = ['pppoe_password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                </div>

                                <div>
                                    <label for="wifi_ssid" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">SSID WiFi</label>
                                    <input type="text" name="wifi_ssid" id="wifi_ssid" value="<?php echo e(old('wifi_ssid', $dev7->wifi_ssid ?? '')); ?>" class="w-full text-xs font-sans px-3 py-2 border <?php $__errorArgs = ['wifi_ssid'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="SSID Pelanggan">
                                    <?php $__errorArgs = ['wifi_ssid'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                </div>

                                <div>
                                    <label for="wifi_password" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Password WiFi</label>
                                    <input type="text" name="wifi_password" id="wifi_password" value="<?php echo e(old('wifi_password', $dev7->wifi_password ?? '')); ?>" class="w-full text-xs font-sans px-3 py-2 border <?php $__errorArgs = ['wifi_password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="Password SSID Pelanggan">
                                    <?php $__errorArgs = ['wifi_password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Sub-section: Distribusi Jaringan Detail (ODP / OLT) -->
                        <div class="pt-4 border-t border-slate-100 dark:border-slate-700/60 space-y-3">
                            <h5 class="text-xs font-bold text-sky-700 dark:text-sky-400 uppercase tracking-wider flex items-center gap-1.5">
                                <?php if (isset($component)) { $__componentOriginal56804098dcf376a0e2227cb77b6cd00a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.icon','data' => ['name' => 'workflow','class' => 'w-4 h-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'workflow','class' => 'w-4 h-4']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $attributes = $__attributesOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__attributesOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a)): ?>
<?php $component = $__componentOriginal56804098dcf376a0e2227cb77b6cd00a; ?>
<?php unset($__componentOriginal56804098dcf376a0e2227cb77b6cd00a); ?>
<?php endif; ?> Distribusi Jaringan Detail (ODP / OLT)
                            </h5>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">"Nomor OLT" di sini (bukan "Nama/Kode Perangkat OLT" di atas) yang dipakai generator CID begitu status jadi Active/Suspended.</p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label for="odp_number" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Nomor ODP</label>
                                        <input type="text" name="odp_number" id="odp_number" value="<?php echo e(old('odp_number', $tech7->odp_number ?? '')); ?>" class="w-full text-xs font-mono px-3 py-2 border <?php $__errorArgs = ['odp_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="ODP-01">
                                        <?php $__errorArgs = ['odp_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                    </div>
                                    <div>
                                        <label for="odp_port" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Port ODP</label>
                                        <input type="text" name="odp_port" id="odp_port" value="<?php echo e(old('odp_port', $tech7->odp_port ?? '')); ?>" class="w-full text-xs font-mono px-3 py-2 border <?php $__errorArgs = ['odp_port'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="Port 4">
                                        <?php $__errorArgs = ['odp_port'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                    </div>
                                </div>

                                <div class="grid grid-cols-3 gap-2">
                                    <div class="min-w-0">
                                        <label for="olt_number" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300 truncate">Nomor OLT</label>
                                        <input type="text" name="olt_number" id="olt_number" value="<?php echo e(old('olt_number', $tech7->olt_number ?? '')); ?>" placeholder="1" class="w-full min-w-0 text-xs font-mono px-2.5 py-2 border <?php $__errorArgs = ['olt_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                                        <?php $__errorArgs = ['olt_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                    </div>
                                    <div class="min-w-0">
                                        <label for="olt_slot" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300 truncate">Slot OLT</label>
                                        <input type="text" name="olt_slot" id="olt_slot" value="<?php echo e(old('olt_slot', $tech7->olt_slot ?? '')); ?>" placeholder="2" class="w-full min-w-0 text-xs font-mono px-2.5 py-2 border <?php $__errorArgs = ['olt_slot'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                                        <?php $__errorArgs = ['olt_slot'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                    </div>
                                    <div class="min-w-0">
                                        <label for="olt_port" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300 truncate">Port OLT</label>
                                        <input type="text" name="olt_port" id="olt_port" value="<?php echo e(old('olt_port', $tech7->olt_port ?? '')); ?>" placeholder="3" class="w-full min-w-0 text-xs font-mono px-2.5 py-2 border <?php $__errorArgs = ['olt_port'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                                        <?php $__errorArgs = ['olt_port'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label for="vlan" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">VLAN (Jaringan)</label>
                                        <input type="text" name="vlan" id="vlan" value="<?php echo e(old('vlan', $tech7->vlan ?? '')); ?>" class="w-full text-xs font-mono px-3 py-2 border <?php $__errorArgs = ['vlan'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="100">
                                        <?php $__errorArgs = ['vlan'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                    </div>
                                    <div>
                                        <label for="router_number" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Nomor Router</label>
                                        <input type="text" name="router_number" id="router_number" value="<?php echo e(old('router_number', $tech7->router_number ?? '')); ?>" class="w-full text-xs font-mono px-3 py-2 border <?php $__errorArgs = ['router_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="Distribusi">
                                        <?php $__errorArgs = ['router_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                    </div>
                                </div>

                                <div>
                                    <label for="initial_attenuation" class="block mb-1 font-bold uppercase text-[10px] tracking-wide text-slate-600 dark:text-slate-300">Redaman Awal Pemasangan (dBm)</label>
                                    <input type="text" name="initial_attenuation" id="initial_attenuation" value="<?php echo e(old('initial_attenuation', $tech7->initial_attenuation ?? '')); ?>" class="w-full text-xs font-mono px-3 py-2 border <?php $__errorArgs = ['initial_attenuation'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-500 <?php else: ?> border-slate-200 dark:border-slate-700 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" placeholder="-19.5">
                                    <?php $__errorArgs = ['initial_attenuation'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-[11px] text-rose-600 dark:text-rose-400 mt-1"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                </div>
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
                        <a href="<?php echo e(route('customers.show', $customer->id)); ?>" class="w-full sm:w-auto px-4 py-2.5 sm:py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors text-xs font-semibold cursor-pointer focus:outline-none text-center inline-flex items-center justify-center">
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

                        <button type="submit" id="btn-submit" style="display: none;" class="w-full sm:w-auto px-6 py-2.5 sm:py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg transition-colors text-xs font-semibold cursor-pointer focus:outline-none inline-flex items-center justify-center gap-1.5 shadow-sm">
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
<?php endif; ?> Simpan Perubahan
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
    /* Skema 3 (2026-09-12) — autocomplete ID Referral Pelanggan (sama
       komponen dengan customers/create.blade.php). */
    document.addEventListener('alpine:init', () => {
        Alpine.data('referralSearch', () => ({
            query: <?php echo json_encode(old('referral_customer_id') ? '' : ($customer->referralCustomer ? $customer->referralCustomer->customer_code.' — '.$customer->referralCustomer->full_name : ''), 15, 512) ?>,
            results: [],
            selectedId: '<?php echo e(old('referral_customer_id', $customer->referral_customer_id)); ?>',
            async search() {
                if (this.query.length < 2) {
                    this.results = [];
                    return;
                }
                const res = await fetch(`<?php echo e(route('customers.search-referral')); ?>?q=${encodeURIComponent(this.query)}`);
                this.results = res.ok ? await res.json() : [];
            },
            pick(r) {
                this.selectedId = r.id;
                this.query = r.customer_code + ' — ' + r.full_name;
                this.results = [];
            },
        }));
    });

    /* ── Wizard Form Stepper & Live Validation Logic ── */
    let currentActiveStep = 1;
    const totalStepsCount = 7;

    // Field wizard dikelompokkan mengikuti tab Detail Pelanggan
    // (identitas/alamat/pop dipisah) supaya per-step gak campur aduk.
    const formFields = {
        'identitas': {
            required: ['full_name', 'identity_number', 'gender', 'primary_phone', 'registration_date'],
            optional: ['email', 'alternative_phone', 'npwp']
        },
        'alamat': {
            required: ['address', 'city_id', 'district_id', 'village_id'],
            optional: ['latitude', 'longitude']
        },
        'pop': {
            required: ['pop_id'],
            optional: ['distribution_id']
        },
        'dokumen': {
            required: [],
            optional: ['foto_rumah', 'foto_kontrak']
        },
        'layanan': {
            required: ['internet_package_id', 'contract_period_months', 'discount_amount', 'tax_percent'],
            optional: ['other_fee', 'jenis_kontrak']
        },
        'referral': {
            required: [],
            optional: ['sales_code', 'agent_code', 'referral_customer_code']
        },
        'operasional': {
            // `status` gak lagi diedit dari sini (hidden input, selalu terisi) —
            // required cuma formalitas biar step ini gak pernah nyantol
            // "Belum Lengkap" di stepper. Field asli step ini sekarang cuma
            // detail perangkat & jaringan terstruktur di bawah.
            required: ['status'],
            optional: [
                // Detail perangkat & jaringan terstruktur (2026-09-12) — sama
                // field dengan Laporan Pemasangan, tapi semua opsional di sini
                // (gak ada gerbang workflow Aktivasi kayak di sana).
                'device_type', 'connection_mode', 'brand', 'model', 'serial_number', 'mac_address',
                'pppoe_username', 'pppoe_password', 'wifi_ssid', 'wifi_password',
                'odp_number', 'odp_port', 'olt_number', 'olt_slot', 'olt_port', 'vlan', 'router_number', 'initial_attenuation'
            ]
        }
    };

    const stepKeys = {
        1: 'identitas',
        2: 'alamat',
        3: 'pop',
        4: 'dokumen',
        5: 'layanan',
        6: 'referral',
        7: 'operasional'
    };

    document.addEventListener("DOMContentLoaded", function() {
        const inputs = document.querySelectorAll('#wizard-form input, #wizard-form select, #wizard-form textarea');
        inputs.forEach(input => {
            input.addEventListener('input', runLiveProgressUpdates);
            input.addEventListener('change', runLiveProgressUpdates);
        });

        updateWizardButtons();
        runLiveProgressUpdates();
        updateLayananBreakdown();
    });

    // Populate active city/district
    const activeCityId = "<?php echo e(old('city_id', $customer->city_id)); ?>";
    const activeDistrictId = "<?php echo e(old('district_id', $customer->district_id)); ?>";
    const activeVillageId = "<?php echo e(old('village_id', $customer->village_id)); ?>";

    if (activeCityId) {
        loadDistricts(activeCityId, activeDistrictId, activeVillageId);
    }

    /*
     * Sengaja pakai inline style, BUKAN class 'hidden': elemen-elemen ini juga
     * memakai utility display ('inline-flex' / 'flex'), dan di CSS Tailwind
     * keduanya utility display dengan specificity sama — yang belakangan menang,
     * sehingga 'hidden' tidak berefek. Inline style selalu menang. Sama pola
     * dengan surveys/report.blade.php & installations/report.blade.php.
     */
    function setElementVisible(el, visible) {
        if (! el) {
            return;
        }
        el.style.display = visible ? '' : 'none';
    }

    /*
     * Aturan tombol wizard — satu-satunya tempat visibilitas tombol ditentukan.
     * Step pertama  : Batal + Lanjut
     * Step tengah   : Sebelumnya + Batal + Lanjut
     * Step terakhir : Sebelumnya + Batal + Simpan Perubahan
     */
    function updateWizardButtons() {
        const isFirstStep = currentActiveStep === 1;
        const isLastStep = currentActiveStep === totalStepsCount;

        setElementVisible(document.getElementById('btn-prev'), ! isFirstStep);
        setElementVisible(document.getElementById('btn-next'), ! isLastStep);
        setElementVisible(document.getElementById('btn-submit'), isLastStep);
    }

    // Dynamic dropdown for Districts
    function loadDistricts(cityId, selectedDistrictId = null, selectedVillageId = null) {
        const districtSelect = document.getElementById('district_id');
        const villageSelect = document.getElementById('village_id');

        districtSelect.innerHTML = '<option value="" disabled selected>Memuat kecamatan...</option>';
        villageSelect.innerHTML = '<option value="" disabled selected>Pilih Desa (Pilih Kecamatan Dulu)</option>';

        fetch(`/api/cities/${cityId}/districts`)
            .then(res => res.json())
            .then(districts => {
                districtSelect.innerHTML = '<option value="" disabled selected>Pilih Kecamatan</option>';
                districts.forEach(d => {
                    const opt = document.createElement('option');
                    opt.value = d.id;
                    opt.textContent = d.name;
                    if (selectedDistrictId && selectedDistrictId == d.id) {
                        opt.selected = true;
                    }
                    districtSelect.appendChild(opt);
                });

                if (selectedDistrictId) {
                    loadVillages(selectedDistrictId, selectedVillageId);
                }

                runLiveProgressUpdates();
            })
            .catch(err => {
                console.error("Gagal memuat kecamatan:", err);
                districtSelect.innerHTML = '<option value="" disabled selected>Gagal memuat kecamatan</option>';
            });
    }

    // Dynamic dropdown for Villages
    function loadVillages(districtId, selectedVillageId = null) {
        const villageSelect = document.getElementById('village_id');
        villageSelect.innerHTML = '<option value="" disabled selected>Memuat desa...</option>';

        fetch(`/api/districts/${districtId}/villages`)
            .then(res => res.json())
            .then(villages => {
                villageSelect.innerHTML = '<option value="" disabled selected>Pilih Desa</option>';
                villages.forEach(v => {
                    const opt = document.createElement('option');
                    opt.value = v.id;
                    opt.textContent = v.name + (v.postal_code ? ` (${v.postal_code})` : '');
                    if (selectedVillageId && selectedVillageId == v.id) {
                        opt.selected = true;
                    }
                    villageSelect.appendChild(opt);
                });
                runLiveProgressUpdates();
            })
            .catch(err => {
                console.error("Gagal memuat desa:", err);
                villageSelect.innerHTML = '<option value="" disabled selected>Gagal memuat desa</option>';
            });
    }

    // File selection UI update helper
    function onFileChange(fieldId) {
        const input = document.getElementById(fieldId);
        const label = document.getElementById('file-label-' + fieldId);
        const defaultPlaceholder = document.getElementById('default-placeholder-' + fieldId);
        const previewContainer = document.getElementById('preview-container-' + fieldId);
        const previewImg = document.getElementById('preview-img-' + fieldId);
        const previewPdf = document.getElementById('preview-pdf-' + fieldId);

        // Reset the delete flag to 0 if a new file is uploaded
        const deleteInput = document.getElementById('delete_' + fieldId);
        if (deleteInput && input.files && input.files.length > 0) {
            deleteInput.value = '0';
        }

        if (input.files && input.files.length > 0) {
            const file = input.files[0];
            label.textContent = file.name;
            input.setAttribute('data-populated', 'true');

            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (previewImg) {
                        previewImg.src = e.target.result;
                        previewImg.classList.remove('hidden');
                    }
                    if (previewPdf) {
                        previewPdf.classList.add('hidden');
                    }
                    if (defaultPlaceholder) defaultPlaceholder.classList.add('hidden');
                    setElementVisible(previewContainer, true);
                };
                reader.readAsDataURL(file);
            } else if (file.type === 'application/pdf') {
                if (previewImg) {
                    previewImg.classList.add('hidden');
                    previewImg.src = '';
                }
                if (previewPdf) {
                    previewPdf.classList.remove('hidden');
                }
                if (defaultPlaceholder) defaultPlaceholder.classList.add('hidden');
                setElementVisible(previewContainer, true);
            } else {
                if (previewImg) {
                    previewImg.classList.add('hidden');
                    previewImg.src = '';
                }
                if (previewPdf) {
                    previewPdf.classList.add('hidden');
                }
                if (defaultPlaceholder) defaultPlaceholder.classList.remove('hidden');
                setElementVisible(previewContainer, false);
            }
        } else {
            const isDeleted = deleteInput && deleteInput.value === '1';
            if (isDeleted || !input.hasAttribute('data-populated')) {
                label.textContent = "Belum ada file";
                input.removeAttribute('data-populated');
                if (defaultPlaceholder) defaultPlaceholder.classList.remove('hidden');
                setElementVisible(previewContainer, false);
                if (previewImg) {
                    previewImg.src = '';
                }
            }
        }
        runLiveProgressUpdates();
    }

    // Clear file selection helper
    function clearFile(fieldId) {
        const input = document.getElementById(fieldId);
        if (input) {
            input.value = '';
            const deleteInput = document.getElementById('delete_' + fieldId);
            if (deleteInput) {
                deleteInput.value = '1';
            }
            onFileChange(fieldId);
        }
    }

    // Estimate monthly total preview
    function updateLayananBreakdown() {
        const packageSelect = document.getElementById('internet_package_id');
        const discountInput = document.getElementById('discount_amount');
        const taxInput = document.getElementById('tax_percent');

        const selectedOption = packageSelect.options[packageSelect.selectedIndex];
        let basePrice = 0;
        if (selectedOption && selectedOption.value) {
            basePrice = parseFloat(selectedOption.getAttribute('data-price')) || 0;
        }

        // Diskon & biaya lain bermasking ribuan — parseFloat('10.000') = 10.
        // `tax_percent` TIDAK dimasking (persen), jadi tetap parseFloat.
        const angka = (el) => (el && window.Rupiah ? window.Rupiah.angka(el.value) : parseFloat(el ? el.value : 0)) || 0;

        const discount = angka(discountInput);
        const taxPercent = parseFloat(taxInput.value) || 0;
        const otherFeeInput = document.getElementById('other_fee');
        const otherFee = angka(otherFeeInput);

        const taxable = Math.max(0, basePrice - discount);
        const tax = Math.round(taxable * (taxPercent / 100));
        // SENGAJA TIDAK ikutkan otherFee — Tagihan Bulanan murni harga+PPN
        // (samain dengan GenerateMonthlyInvoicesCommand & total_monthly_bill
        // server-side). otherFee ditampilkan terpisah di baris sendiri
        // (sekali, Tagihan Registrasi), bukan ditambah ke total di sini.
        const total = taxable + tax;

        const formatRupiah = (val) => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(val));

        document.getElementById('preview-base-price').textContent = formatRupiah(basePrice);
        document.getElementById('preview-discount').textContent = '- ' + formatRupiah(discount);
        document.getElementById('preview-tax-label').textContent = `PPN (${taxPercent}%):`;
        document.getElementById('preview-tax').textContent = formatRupiah(tax);
        const otherFeeEl = document.getElementById('preview-other-fee');
        if (otherFeeEl) {
            otherFeeEl.textContent = formatRupiah(otherFee);
        }
        document.getElementById('preview-total-monthly').textContent = formatRupiah(total);
    }

    /* Live Stepper Auditor & Progress Calculator */
    function runLiveProgressUpdates() {
        let totalFieldsCount = 0;
        let filledFieldsCount = 0;

        for (let step = 1; step <= totalStepsCount; step++) {
            const stepKey = stepKeys[step];
            const config = formFields[stepKey];
            let requiredMissing = [];
            let optionalMissing = [];

            config.required.forEach(field => {
                totalFieldsCount++;
                const el = document.getElementById(field);
                if (el) {
                    const isFilePopulated = el.type === 'file' && el.getAttribute('data-populated') === 'true';
                    if (el.value.trim() !== "" || isFilePopulated) {
                        filledFieldsCount++;
                    } else {
                        requiredMissing.push(getLabelName(field));
                    }
                }
            });

            config.optional.forEach(field => {
                totalFieldsCount++;
                const el = document.getElementById(field);
                if (el) {
                    const isFilePopulated = el.type === 'file' && el.getAttribute('data-populated') === 'true';
                    if (el.value.trim() !== "" || isFilePopulated) {
                        filledFieldsCount++;
                    } else {
                        optionalMissing.push(getLabelName(field));
                    }
                }
            });

            updateStepNavStatus(step, requiredMissing, optionalMissing);
        }

        const progressPercentage = totalFieldsCount > 0 ? Math.round((filledFieldsCount / totalFieldsCount) * 100) : 0;
        const pctEl = document.getElementById('progress-percentage');
        const filledEl = document.getElementById('filled-fields-count');
        const totalEl = document.getElementById('total-fields-count');
        const fillEl = document.getElementById('progress-bar-fill');

        if (pctEl) pctEl.textContent = progressPercentage + '%';
        if (filledEl) filledEl.textContent = filledFieldsCount;
        if (totalEl) totalEl.textContent = totalFieldsCount;
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
            statusSpan.className = 'text-[9px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400 block mt-0.5';
            iconDiv.innerHTML = `<span class="w-6 h-6 rounded-full bg-rose-100 dark:bg-rose-900/40 border border-rose-200 dark:border-rose-700 flex items-center justify-center text-rose-600 dark:text-rose-400">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </span>`;
            missingSpan.textContent = 'Wajib diisi: ' + requiredMissing.join(', ');

            if (currentActiveStep !== step) {
                navBtn.className = "w-full text-left p-3.5 rounded-xl border border-rose-200 dark:border-rose-900/50 bg-rose-50/40 dark:bg-rose-950/20 hover:bg-rose-50 dark:hover:bg-rose-900/30 transition-all group focus:outline-none";
            }
        } else if (optionalMissing.length > 0) {
            statusSpan.textContent = 'Kekurangan Data';
            statusSpan.className = 'text-[9px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400 block mt-0.5';
            iconDiv.innerHTML = `<span class="w-6 h-6 rounded-full bg-amber-100 dark:bg-amber-900/40 border border-amber-200 dark:border-amber-700 flex items-center justify-center text-amber-600 dark:text-amber-400">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
            </span>`;
            missingSpan.textContent = 'Kurang: ' + optionalMissing.join(', ');

            if (currentActiveStep !== step) {
                navBtn.className = "w-full text-left p-3.5 rounded-xl border border-amber-200 dark:border-amber-900/50 bg-amber-50/40 dark:bg-amber-950/20 hover:bg-amber-50 dark:hover:bg-amber-900/30 transition-all group focus:outline-none";
            }
        } else {
            statusSpan.textContent = 'Lengkap';
            statusSpan.className = 'text-[9px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 block mt-0.5';
            iconDiv.innerHTML = `<span class="w-6 h-6 rounded-full bg-emerald-500 text-white flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3 h-3"><path d="M20 6 9 17l-5-5"/></svg>
            </span>`;
            missingSpan.textContent = 'Semua data terisi';

            if (currentActiveStep !== step) {
                navBtn.className = "w-full text-left p-3.5 rounded-xl border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/40 dark:bg-emerald-950/20 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-all group focus:outline-none";
            }
        }

        if (currentActiveStep === step) {
            navBtn.className = "w-full text-left p-3.5 rounded-xl border-2 border-sky-500 bg-sky-50/50 dark:bg-sky-900/20 transition-all group focus:outline-none shadow-sm";
        }
    }

    // Field names translator helper
    function getLabelName(field) {
        const labels = {
            full_name: 'Nama Lengkap',
            identity_number: 'NIK',
            gender: 'Jenis Kelamin',
            primary_phone: 'Nomor HP Utama',
            alternative_phone: 'Nomor HP Alternatif',
            npwp: 'NPWP',
            email: 'Email',
            registration_date: 'Tgl Registrasi',
            address: 'Alamat',
            city_id: 'Kota',
            district_id: 'Kecamatan',
            village_id: 'Desa',
            latitude: 'Latitude',
            longitude: 'Longitude',
            pop_id: 'POP Cabang',
            distribution_id: 'Kode Distribusi',
            foto_rumah: 'Foto Rumah',
            foto_kontrak: 'Foto Kontrak',
            internet_package_id: 'Paket Internet',
            jenis_kontrak: 'Jenis Kontrak',
            contract_period_months: 'Masa Kontrak',
            discount_amount: 'Diskon',
            tax_percent: 'PPN',
            other_fee: 'Biaya Lain',
            sales_code: 'Kode Sales',
            agent_code: 'Kode Agent',
            referral_customer_code: 'Ref Pelanggan',
            status: 'Status Awal',
            device_type: 'Jenis Perangkat',
            connection_mode: 'Mode Koneksi',
            brand: 'Merk Perangkat',
            model: 'Tipe Model',
            serial_number: 'SN Perangkat',
            mac_address: 'MAC Address',
            pppoe_username: 'Username PPPoE',
            pppoe_password: 'Password PPPoE',
            wifi_ssid: 'SSID WiFi',
            wifi_password: 'Password WiFi',
            odp_number: 'Nomor ODP',
            odp_port: 'Port ODP',
            olt_number: 'Nomor OLT',
            olt_slot: 'Slot OLT',
            olt_port: 'Port OLT',
            vlan: 'VLAN Jaringan',
            router_number: 'Nomor Router',
            initial_attenuation: 'Redaman Awal'
        };
        return labels[field] || field;
    }

    /* Stepper Page Switcher */
    function goToStep(stepNumber) {
        document.getElementById('step-panel-' + currentActiveStep).classList.add('hidden');
        currentActiveStep = stepNumber;
        document.getElementById('step-panel-' + currentActiveStep).classList.remove('hidden');

        // Mobile stepper highlight
        for (let i = 1; i <= totalStepsCount; i++) {
            const mBtn = document.getElementById('mobile-step-btn-' + i);
            if (! mBtn) continue;

            if (i === currentActiveStep) {
                mBtn.className = "py-2.5 px-2 rounded-lg text-xs font-bold bg-sky-50 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-700 transition-all flex items-center justify-center gap-1.5 shadow-sm";
                mBtn.querySelector('span.w-4').className = "w-4 h-4 rounded-full bg-sky-600 text-white text-[9px] font-bold flex items-center justify-center shrink-0";
            } else {
                mBtn.className = "py-2.5 px-2 rounded-lg text-xs font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-all flex items-center justify-center gap-1.5";
                mBtn.querySelector('span.w-4').className = "w-4 h-4 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-[9px] font-bold flex items-center justify-center shrink-0";
            }
        }

        updateWizardButtons();
        runLiveProgressUpdates();
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

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/customers/edit.blade.php ENDPATH**/ ?>