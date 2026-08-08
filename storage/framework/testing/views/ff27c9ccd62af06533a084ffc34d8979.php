<?php $__env->startSection('title', 'Dashboard - Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', 'Dashboard'); ?>

<?php $__env->startSection('content'); ?>
<?php
    $currency = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
    $percent = fn ($value, $total) => number_format(((int) $value / max(1, (int) $total)) * 100, 1) . '%';
    $statusLabels = [
        'draft' => 'Draft',
        'perlu_dilengkapi' => 'Perlu Dilengkapi',
        'lengkap' => 'Lengkap',
        'siap_billing' => 'Siap Billing',
        'belum_dibayar' => 'Belum Dibayar',
        'sebagian' => 'Dibayar Sebagian',
        'lunas' => 'Lunas',
        'batal' => 'Batal',
    ];
?>

<div class="space-y-6">
    <!-- Filter Panel (Naked, following Design.md §1.5) -->
    <form method="GET" action="<?php echo e(route('dashboard')); ?>" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end mb-6">
        <div>
            <label for="pop_id" class="block text-xs font-semibold text-text-secondary mb-1.5">Filter POP</label>
            <?php if (isset($component)) { $__componentOriginal231e2c645bf8af0c5c05a5dc5a94c862 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal231e2c645bf8af0c5c05a5dc5a94c862 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.select','data' => ['name' => 'pop_id','id' => 'pop_id']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.select'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'pop_id','id' => 'pop_id']); ?>
                <option value="">Semua POP yang dapat diakses</option>
                <?php $__currentLoopData = $pops; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pop): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($pop->id); ?>" <?php if((string) $filters['pop_id'] === (string) $pop->id): echo 'selected'; endif; ?>>
                        <?php echo e($pop->name); ?>

                    </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal231e2c645bf8af0c5c05a5dc5a94c862)): ?>
<?php $attributes = $__attributesOriginal231e2c645bf8af0c5c05a5dc5a94c862; ?>
<?php unset($__attributesOriginal231e2c645bf8af0c5c05a5dc5a94c862); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal231e2c645bf8af0c5c05a5dc5a94c862)): ?>
<?php $component = $__componentOriginal231e2c645bf8af0c5c05a5dc5a94c862; ?>
<?php unset($__componentOriginal231e2c645bf8af0c5c05a5dc5a94c862); ?>
<?php endif; ?>
        </div>

        <div>
            <label for="period_from" class="block text-xs font-semibold text-text-secondary mb-1.5">Periode Dari</label>
            <?php if (isset($component)) { $__componentOriginal65bd7e7dbd93cec773ad6501ce127e46 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.input','data' => ['type' => 'month','name' => 'period_from','id' => 'period_from','value' => ''.e($filters['period_from']).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'month','name' => 'period_from','id' => 'period_from','value' => ''.e($filters['period_from']).'']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46)): ?>
<?php $attributes = $__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46; ?>
<?php unset($__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal65bd7e7dbd93cec773ad6501ce127e46)): ?>
<?php $component = $__componentOriginal65bd7e7dbd93cec773ad6501ce127e46; ?>
<?php unset($__componentOriginal65bd7e7dbd93cec773ad6501ce127e46); ?>
<?php endif; ?>
        </div>

        <div>
            <label for="period_to" class="block text-xs font-semibold text-text-secondary mb-1.5">Periode Sampai</label>
            <?php if (isset($component)) { $__componentOriginal65bd7e7dbd93cec773ad6501ce127e46 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.input','data' => ['type' => 'month','name' => 'period_to','id' => 'period_to','value' => ''.e($filters['period_to']).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'month','name' => 'period_to','id' => 'period_to','value' => ''.e($filters['period_to']).'']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46)): ?>
<?php $attributes = $__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46; ?>
<?php unset($__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal65bd7e7dbd93cec773ad6501ce127e46)): ?>
<?php $component = $__componentOriginal65bd7e7dbd93cec773ad6501ce127e46; ?>
<?php unset($__componentOriginal65bd7e7dbd93cec773ad6501ce127e46); ?>
<?php endif; ?>
        </div>

        <div class="flex gap-2">
            <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['type' => 'submit','variant' => 'primary','class' => 'w-full md:w-auto']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'submit','variant' => 'primary','class' => 'w-full md:w-auto']); ?>
                Terapkan Filter
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala8bb031a483a05f647cb99ed3a469847)): ?>
<?php $attributes = $__attributesOriginala8bb031a483a05f647cb99ed3a469847; ?>
<?php unset($__attributesOriginala8bb031a483a05f647cb99ed3a469847); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala8bb031a483a05f647cb99ed3a469847)): ?>
<?php $component = $__componentOriginala8bb031a483a05f647cb99ed3a469847; ?>
<?php unset($__componentOriginala8bb031a483a05f647cb99ed3a469847); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['type' => 'button','variant' => 'secondary','tag' => 'a','href' => ''.e(route('dashboard')).'','class' => 'w-full md:w-auto text-center']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'button','variant' => 'secondary','tag' => 'a','href' => ''.e(route('dashboard')).'','class' => 'w-full md:w-auto text-center']); ?>
                Reset
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala8bb031a483a05f647cb99ed3a469847)): ?>
<?php $attributes = $__attributesOriginala8bb031a483a05f647cb99ed3a469847; ?>
<?php unset($__attributesOriginala8bb031a483a05f647cb99ed3a469847); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala8bb031a483a05f647cb99ed3a469847)): ?>
<?php $component = $__componentOriginala8bb031a483a05f647cb99ed3a469847; ?>
<?php unset($__componentOriginala8bb031a483a05f647cb99ed3a469847); ?>
<?php endif; ?>
        </div>
    </form>

    <!-- Summary Cards Grid (Metric Cards, following Design.md §1.3 & §5) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <!-- Total Pelanggan (Metric Card) -->
        <div class="metric-card">
            <div>
                <div class="metric-card-label">
                    <span>Total Pelanggan</span>
                </div>
                <div class="metric-card-value-container">
                    <p class="metric-card-value"><?php echo e(number_format($stats['total_customers'])); ?></p>
                </div>
            </div>
            <p class="metric-card-footer">Sesuai filter POP</p>
        </div>

        <!-- Pelanggan Aktif (Metric Card - Success) -->
        <div class="metric-card status-success">
            <div>
                <div class="metric-card-label">
                    <span>Pelanggan Aktif</span>
                </div>
                <div class="metric-card-value-container">
                    <p class="metric-card-value"><?php echo e(number_format($stats['active_customers'])); ?></p>
                </div>
            </div>
            <p class="metric-card-footer"><span class="font-mono"><?php echo e($percent($stats['active_customers'], $stats['total_customers'])); ?></span> dari total pelanggan</p>
        </div>

        <!-- Data Belum Lengkap (Operational Status Card - Warning) -->
        <div class="metric-card status-warning">
            <div>
                <div class="metric-card-label">
                    <span>Data Belum Lengkap</span>
                </div>
                <div class="metric-card-value-container">
                    <p class="metric-card-value"><?php echo e(number_format($stats['incomplete_customers'])); ?></p>
                </div>
            </div>
            <p class="metric-card-footer"><span class="font-mono"><?php echo e($percent($stats['incomplete_customers'], $stats['total_customers'])); ?></span> perlu dilengkapi</p>
        </div>

        <!-- Siap Billing (Metric Card - Info) -->
        <div class="metric-card status-info">
            <div>
                <div class="metric-card-label">
                    <span>Siap Billing</span>
                </div>
                <div class="metric-card-value-container">
                    <p class="metric-card-value"><?php echo e(number_format($stats['ready_billing_customers'])); ?></p>
                </div>
            </div>
            <p class="metric-card-footer"><span class="font-mono"><?php echo e($percent($stats['ready_billing_customers'], $stats['total_customers'])); ?></span> siap ditagih</p>
        </div>

        <!-- Tagihan Periode (Metric Card) -->
        <div class="metric-card">
            <div>
                <div class="metric-card-label">
                    <span>Tagihan Periode (<?php echo e($filters['period_label']); ?>)</span>
                </div>
                <div class="metric-card-value-container">
                    <p class="metric-card-value text-2xl"><?php echo e($currency($stats['total_invoices_amount'])); ?></p>
                </div>
            </div>
            <p class="metric-card-footer">Berdasarkan periode tagihan</p>
        </div>

        <!-- Pembayaran Periode (Metric Card - Success) -->
        <div class="metric-card status-success">
            <div>
                <div class="metric-card-label">
                    <span>Pembayaran Periode (<?php echo e($filters['period_label']); ?>)</span>
                </div>
                <div class="metric-card-value-container">
                    <p class="metric-card-value text-2xl"><?php echo e($currency($stats['total_payments_amount'])); ?></p>
                </div>
            </div>
            <p class="metric-card-footer">Hanya pembayaran valid</p>
        </div>

        <!-- Total Tunggakan (Operational Status Card - Danger) -->
        <div class="metric-card status-error">
            <div>
                <div class="metric-card-label">
                    <span>Total Tunggakan</span>
                </div>
                <div class="metric-card-value-container">
                    <p class="metric-card-value text-2xl"><?php echo e($currency($stats['total_unpaid_amount'])); ?></p>
                </div>
            </div>
            <p class="metric-card-footer">Invoice belum lunas pada filter</p>
        </div>

        <!-- Tagihan Jatuh Tempo (Operational Status Card - Danger) -->
        <div class="metric-card status-error">
            <div>
                <div class="metric-card-label">
                    <span>Tagihan Jatuh Tempo</span>
                </div>
                <div class="metric-card-value-container">
                    <p class="metric-card-value"><?php echo e(number_format($stats['due_invoices_count'])); ?></p>
                </div>
            </div>
            <p class="metric-card-footer">Invoice belum lunas melewati batas</p>
        </div>
    </div>

    <!-- Details Section Grid -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <!-- Customers by POP (Insight Card) -->
        <?php if (isset($component)) { $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.card','data' => ['class' => 'p-5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'p-5']); ?>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-text-main">Total Pelanggan per POP</h3>
                <?php if (isset($component)) { $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.badge','data' => ['type' => 'neutral','class' => 'font-mono']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'neutral','class' => 'font-mono']); ?><?php echo e($customersByPop->count()); ?> POP <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $attributes = $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $component = $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
            </div>

            <div class="space-y-3">
                <?php $__empty_1 = true; $__currentLoopData = $customersByPop; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div>
                        <div class="flex justify-between gap-4 text-sm">
                            <span class="font-medium text-text-secondary"><?php echo e($row->pop?->name ?? 'Tanpa POP'); ?></span>
                            <span class="font-semibold text-text-main font-mono"><?php echo e(number_format($row->total)); ?></span>
                        </div>
                        <div class="mt-1.5 h-2 rounded-full bg-surface-muted">
                            <div class="h-2 rounded-full bg-sky-655 bg-primary" style="width: <?php echo e(min(100, ((int) $row->total / max(1, (int) $stats['total_customers'])) * 100)); ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <p class="text-sm text-text-muted">Belum ada pelanggan pada filter ini.</p>
                <?php endif; ?>
            </div>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $attributes = $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $component = $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>

        <!-- Due Invoices Table -->
        <?php if (isset($component)) { $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.card','data' => ['class' => 'p-5 xl:col-span-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'p-5 xl:col-span-2']); ?>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-text-main">Tagihan Jatuh Tempo</h3>
                <?php if(auth()->user()->hasPermission('view_invoices')): ?>
                    <?php if (isset($component)) { $__componentOriginal606bedd6108050b8303bc7c381e2387c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal606bedd6108050b8303bc7c381e2387c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.link','data' => ['href' => ''.e(route('invoices.index')).'','class' => 'text-xs']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => ''.e(route('invoices.index')).'','class' => 'text-xs']); ?>Lihat Semua <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal606bedd6108050b8303bc7c381e2387c)): ?>
<?php $attributes = $__attributesOriginal606bedd6108050b8303bc7c381e2387c; ?>
<?php unset($__attributesOriginal606bedd6108050b8303bc7c381e2387c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal606bedd6108050b8303bc7c381e2387c)): ?>
<?php $component = $__componentOriginal606bedd6108050b8303bc7c381e2387c; ?>
<?php unset($__componentOriginal606bedd6108050b8303bc7c381e2387c); ?>
<?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if (isset($component)) { $__componentOriginal793d2b22631f88b8a3d00569a12acf88 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal793d2b22631f88b8a3d00569a12acf88 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.table','data' => ['headers' => ['Invoice', 'Pelanggan', 'POP', 'Jatuh Tempo', 'Sisa Tagihan']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.table'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['headers' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['Invoice', 'Pelanggan', 'POP', 'Jatuh Tempo', 'Sisa Tagihan'])]); ?>
                <?php $__empty_1 = true; $__currentLoopData = $dueInvoices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td class="data-cell text-left font-medium text-text-main"><?php echo e($invoice->invoice_number); ?></td>
                        <td class="text-left text-text-secondary"><?php echo e($invoice->customer?->full_name ?? '-'); ?></td>
                        <td class="text-left text-text-muted"><?php echo e($invoice->pop?->name ?? '-'); ?></td>
                        <td class="data-cell text-left text-error font-semibold"><?php echo e(optional($invoice->due_date)->format('d/m/Y')); ?></td>
                        <td class="data-cell text-right font-semibold text-text-main"><?php echo e($currency($invoice->remaining_amount)); ?></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="5" class="py-6 text-center text-text-muted">Tidak ada tagihan jatuh tempo.</td>
                    </tr>
                <?php endif; ?>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal793d2b22631f88b8a3d00569a12acf88)): ?>
<?php $attributes = $__attributesOriginal793d2b22631f88b8a3d00569a12acf88; ?>
<?php unset($__attributesOriginal793d2b22631f88b8a3d00569a12acf88); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal793d2b22631f88b8a3d00569a12acf88)): ?>
<?php $component = $__componentOriginal793d2b22631f88b8a3d00569a12acf88; ?>
<?php unset($__componentOriginal793d2b22631f88b8a3d00569a12acf88); ?>
<?php endif; ?>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $attributes = $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $component = $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>
    </div>

    <!-- Incomplete Customers and Quick Access Grid -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <!-- Incomplete Customers Table -->
        <?php if (isset($component)) { $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.card','data' => ['class' => 'p-5 xl:col-span-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'p-5 xl:col-span-2']); ?>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-text-main">Pelanggan yang Perlu Dilengkapi</h3>
                <?php if(auth()->user()->hasPermission('view_customers')): ?>
                    <?php if (isset($component)) { $__componentOriginal606bedd6108050b8303bc7c381e2387c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal606bedd6108050b8303bc7c381e2387c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.link','data' => ['href' => ''.e(route('customers.index', ['completeness_status' => 'perlu_dilengkapi'])).'','class' => 'text-xs']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => ''.e(route('customers.index', ['completeness_status' => 'perlu_dilengkapi'])).'','class' => 'text-xs']); ?>Lihat Semua <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal606bedd6108050b8303bc7c381e2387c)): ?>
<?php $attributes = $__attributesOriginal606bedd6108050b8303bc7c381e2387c; ?>
<?php unset($__attributesOriginal606bedd6108050b8303bc7c381e2387c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal606bedd6108050b8303bc7c381e2387c)): ?>
<?php $component = $__componentOriginal606bedd6108050b8303bc7c381e2387c; ?>
<?php unset($__componentOriginal606bedd6108050b8303bc7c381e2387c); ?>
<?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if (isset($component)) { $__componentOriginal793d2b22631f88b8a3d00569a12acf88 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal793d2b22631f88b8a3d00569a12acf88 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.table','data' => ['headers' => ['ID Pelanggan', 'Nama', 'POP', 'Status Kelengkapan', 'Terakhir Diupdate']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.table'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['headers' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['ID Pelanggan', 'Nama', 'POP', 'Status Kelengkapan', 'Terakhir Diupdate'])]); ?>
                <?php $__empty_1 = true; $__currentLoopData = $incompleteCustomers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $customer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td class="data-cell text-left font-medium text-text-main"><?php echo e($customer->customer_code); ?></td>
                        <td class="text-left text-text-secondary"><?php echo e($customer->full_name); ?></td>
                        <td class="text-left text-text-muted"><?php echo e($customer->pop?->name ?? '-'); ?></td>
                        <td class="text-left">
                            <?php if (isset($component)) { $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.badge','data' => ['type' => 'warning']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'warning']); ?>
                                <?php echo e($statusLabels[$customer->data_completeness_status] ?? $customer->data_completeness_status); ?>

                             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $attributes = $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $component = $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
                        </td>
                        <td class="data-cell text-left text-text-muted"><?php echo e(optional($customer->updated_at)->format('d/m/Y')); ?></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="5" class="py-6 text-center text-text-muted">Tidak ada pelanggan yang perlu dilengkapi.</td>
                    </tr>
                <?php endif; ?>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal793d2b22631f88b8a3d00569a12acf88)): ?>
<?php $attributes = $__attributesOriginal793d2b22631f88b8a3d00569a12acf88; ?>
<?php unset($__attributesOriginal793d2b22631f88b8a3d00569a12acf88); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal793d2b22631f88b8a3d00569a12acf88)): ?>
<?php $component = $__componentOriginal793d2b22631f88b8a3d00569a12acf88; ?>
<?php unset($__componentOriginal793d2b22631f88b8a3d00569a12acf88); ?>
<?php endif; ?>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $attributes = $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $component = $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>

        <!-- Quick Access Card -->
        <?php if (isset($component)) { $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.card','data' => ['class' => 'p-5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'p-5']); ?>
            <h3 class="text-sm font-semibold text-text-main mb-4">Akses Cepat</h3>
            <div class="space-y-3">
                <?php $hasQuickAction = false; ?>

                <?php if(auth()->user()->hasPermission('view_customers')): ?>
                    <?php $hasQuickAction = true; ?>
                    <a href="<?php echo e(route('customers.index')); ?>" class="flex items-center justify-between rounded-md border border-border p-3 text-sm font-medium text-text-secondary hover:bg-surface-muted hover:border-primary-border hover:text-primary transition-all duration-150 cursor-pointer">
                        <span>Data Pelanggan</span>
                        <svg class="h-4 w-4 text-text-disabled hover:text-primary transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                <?php endif; ?>

                <?php if(auth()->user()->hasPermission('view_invoices')): ?>
                    <?php $hasQuickAction = true; ?>
                    <a href="<?php echo e(route('invoices.index')); ?>" class="flex items-center justify-between rounded-md border border-border p-3 text-sm font-medium text-text-secondary hover:bg-surface-muted hover:border-primary-border hover:text-primary transition-all duration-150 cursor-pointer">
                        <span>Daftar Tagihan</span>
                        <svg class="h-4 w-4 text-text-disabled hover:text-primary transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                <?php endif; ?>

                <?php if(auth()->user()->hasPermission('view_payments')): ?>
                    <?php $hasQuickAction = true; ?>
                    <a href="<?php echo e(route('payments.index')); ?>" class="flex items-center justify-between rounded-md border border-border p-3 text-sm font-medium text-text-secondary hover:bg-surface-muted hover:border-primary-border hover:text-primary transition-all duration-150 cursor-pointer">
                        <span>Riwayat Pembayaran</span>
                        <svg class="h-4 w-4 text-text-disabled hover:text-primary transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                <?php endif; ?>

                <?php if(!$hasQuickAction): ?>
                    <p class="text-sm text-text-muted">Tidak ada akses cepat yang tersedia untuk peran Anda.</p>
                <?php endif; ?>

                <div class="mt-6 pt-4 border-t border-border">
                    <p class="text-xs font-semibold text-text-muted mb-3 uppercase tracking-wider">Uji Coba UI (Toast & Dialog)</p>
                    <div class="grid grid-cols-2 gap-2 mb-3">
                        <button type="button" onclick="Toast.success('Berhasil', 'Aksi berhasil dilakukan dengan aman.')" class="px-3 py-2 bg-success-bg text-success hover:opacity-90 border border-success-border rounded-md text-xs font-medium transition-colors text-center cursor-pointer">Test Success</button>
                        <button type="button" onclick="Toast.error('Sistem Error', 'Terjadi kesalahan saat memproses data Anda. Silakan coba lagi nanti.')" class="px-3 py-2 bg-error-bg text-error hover:opacity-90 border border-error-border rounded-md text-xs font-medium transition-colors text-center cursor-pointer">Test Error</button>
                        <button type="button" onclick="Toast.warning('Perhatian', 'Kuota penyimpanan Anda hampir penuh.')" class="px-3 py-2 bg-warning-bg text-warning hover:opacity-90 border border-warning-border rounded-md text-xs font-medium transition-colors text-center cursor-pointer">Test Warning</button>
                        <button type="button" onclick="Toast.info('Pembaruan', 'Sistem akan melakukan maintenance pada pukul 00:00 WIB.')" class="px-3 py-2 bg-info-bg text-info hover:opacity-90 border border-info-border rounded-md text-xs font-medium transition-colors text-center cursor-pointer">Test Info</button>
                    </div>
                    <div class="grid grid-cols-1 gap-2">
                        <button type="button" onclick="openIsolirDialog()" class="px-3 py-2 bg-slate-800 dark:bg-slate-700 text-white hover:bg-slate-700 dark:hover:bg-slate-600 border border-slate-700 dark:border-slate-600 rounded-md text-xs font-medium transition-colors text-center cursor-pointer">Test Dialog Form (Isolir)</button>
                    </div>
                </div>
            </div>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $attributes = $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $component = $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>
    </div>
</div>

<?php $__env->startSection('scripts'); ?>
<script>
function openIsolirDialog() {
    // 1. Definisikan string HTML untuk Content/Form
    const formHtml = `
        <form id="formIsolir" onsubmit="submitIsolir(event)">
            <div class="mb-4">
                <label class="block text-sm font-medium text-text-secondary mb-1">
                    Alasan Isolir Koneksi <span class="text-rose-500">*</span>
                </label>
                <textarea id="alasanIsolir" name="alasan" rows="3" required
                    class="w-full rounded-lg border-border bg-surface text-text-main shadow-sm focus:border-sky-500 focus:ring-sky-500 text-sm p-2.5 border outline-none"
                    placeholder="Masukkan alasan pemutusan/isolir..."></textarea>
            </div>
            <div class="text-xs text-warning flex items-start gap-1.5 bg-warning-bg p-2.5 rounded border border-warning-border">
                <svg class="h-4 w-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span>Tindakan ini akan memutus akses pelanggan dari jaringan secara otomatis.</span>
            </div>
        </form>
    `;

    // 2. Trigger Global Dialog
    window.Dialog.show({
        title: 'Isolir Koneksi Pelanggan',
        icon: 'warning',
        contentHtml: formHtml,
        buttons: [
            {
                text: 'Batal',
                type: 'secondary',
            },
            {
                text: 'Simpan Isolir',
                type: 'submit',
                formId: 'formIsolir'
            }
        ]
    });
}

function submitIsolir(event) {
    event.preventDefault();
    const alasan = document.getElementById('alasanIsolir').value;
    console.log("Memproses isolir dengan alasan:", alasan);
    window.Dialog.close();
    window.Toast.success('Berhasil', 'Koneksi pelanggan telah diisolir.');
}
</script>
<?php $__env->stopSection(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/dashboard.blade.php ENDPATH**/ ?>