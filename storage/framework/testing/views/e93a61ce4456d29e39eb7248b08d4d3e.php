<?php $__env->startSection('title', 'Antrean Survey - Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', 'Antrean Survey Lapangan'); ?>

<?php $__env->startSection('content'); ?>
<!-- Top Action Bar -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
    <div>
        <h3 class="text-text-main text-base sm:text-lg font-bold tracking-tight">Antrean Survey Pelanggan</h3>
        <p class="text-xs sm:text-sm text-text-muted mt-0.5">Daftar pelanggan yang menunggu survey dan sedang dalam proses survey lapangan.</p>
    </div>
    <span class="self-start sm:self-auto inline-flex items-center gap-1.5 px-3 py-1 rounded-lg border border-border bg-surface text-xs text-text-muted shadow-2xs">
        <span class="font-mono font-semibold text-text-main"><?php echo e($customers->total()); ?></span> antrean
    </span>
</div>

<!-- Filter & Search Panel -->
<div class="bg-surface border border-border rounded-lg p-4 sm:p-5 mb-5 shadow-2xs">
    <form action="<?php echo e(route('surveys.queue')); ?>" method="GET" class="flex flex-col sm:flex-row gap-3 sm:gap-4 items-stretch sm:items-end">
        <!-- Search -->
        <div class="flex-1 min-w-0">
            <label for="search" class="block text-xs font-semibold text-text-muted mb-1.5 uppercase tracking-wider">Cari Pelanggan</label>
            <div class="relative">
                <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-text-muted pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                </svg>
                <input type="text" name="search" id="search" value="<?php echo e(request('search')); ?>" placeholder="Cari nama, No. HP, atau ID Lama..." class="w-full font-sans text-sm pl-9 pr-3 py-2 border border-border rounded-lg bg-surface text-text-main placeholder:text-text-muted focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all">
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-2 shrink-0">
            <button type="submit" class="flex-1 sm:flex-none text-sm font-semibold py-2 px-5 rounded-lg text-white transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary/25 inline-flex items-center justify-center gap-1.5" style="background:var(--color-primary)">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" /></svg>
                <span>Cari</span>
            </button>
            <a href="<?php echo e(route('surveys.queue')); ?>" class="flex-1 sm:flex-none bg-surface-muted hover:bg-border text-text-secondary text-sm font-semibold py-2 px-4 rounded-lg transition-colors cursor-pointer text-center focus:outline-none border border-border inline-flex items-center justify-center">
                Reset
            </a>
        </div>
    </form>
</div>

<!-- Queue Content Panel -->
<div class="@container bg-surface border border-border rounded-lg overflow-hidden shadow-xs">
    <div class="border-b border-border px-4 sm:px-6 py-3 flex items-center justify-between" style="background:var(--color-primary-soft)">
        <span class="text-xs sm:text-sm font-bold uppercase tracking-wider" style="color:var(--color-primary)">Daftar Antrean Survey</span>
    </div>

    
    <div class="hidden @min-[56rem]:block overflow-x-auto">
        <table class="w-full min-w-[880px] border-collapse text-left text-xs lg:text-sm text-text-main">
            <thead>
                <tr class="bg-surface-muted border-b border-border text-text-muted font-semibold text-[11px] uppercase tracking-wider">
                    <th scope="col" class="px-3.5 py-3 w-12 text-center">NO</th>
                    <th scope="col" class="px-3.5 py-3">ID</th>
                    <th scope="col" class="px-3.5 py-3">NAMA</th>
                    <th scope="col" class="px-3.5 py-3">HP</th>
                    <th scope="col" class="px-3.5 py-3">DESA</th>
                    <th scope="col" class="px-3.5 py-3 text-center">STATUS</th>
                    <th scope="col" class="px-3.5 py-3">INSERTED AT</th>
                    <th scope="col" class="px-3.5 py-3 text-center">SISA SLA</th>
                    <th scope="col" class="px-3.5 py-3 text-right">ACTION</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                <?php $__empty_1 = true; $__currentLoopData = $customers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $customer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <?php
                    $survey = $customer->latestSurvey;
                    $actor = auth()->user();
                    $canSurveyReport = $actor->hasPermission('customers.detail.survey.view') || $actor->hasPermission('customers.detail.survey.update');
                    $canFieldwork = $actor->hasPermission('customers.detail.devices.view') || $actor->hasPermission('customers.detail.installation.view');
                    $detailUrl = match(true) {
                        $actor->hasPermission('customers.detail.installation.validate') || $actor->hasFullAccess() => route('customers.verification.admin', $customer),
                        $canSurveyReport && $customer->status === 'survey_in_progress' => route('customers.survey.report', $customer),
                        $actor->hasPermission('customers.detail.view') => route('customers.show', $customer),
                        $canFieldwork => route('customers.fieldwork', $customer),
                        default => null,
                    };
                ?>
                <tr class="hover:bg-surface-muted transition-colors">
                    <td class="px-3.5 py-3 text-center text-text-muted font-mono text-xs"><?php echo e($loop->iteration); ?></td>
                    
                    <td class="px-3.5 py-3 whitespace-nowrap">
                        <span class="font-mono text-xs font-semibold text-text-main"><?php echo e($customer->display_id); ?></span>
                    </td>
                    
                    <td class="px-3.5 py-3 font-medium text-text-main max-w-[160px] xl:max-w-xs truncate" title="<?php echo e($customer->full_name); ?>">
                        <?php echo e($customer->full_name); ?>

                    </td>
                    
                    <td class="px-3.5 py-3 whitespace-nowrap font-mono text-xs text-text-secondary">
                        <a href="tel:<?php echo e($customer->primary_phone); ?>" class="hover:text-primary transition-colors"><?php echo e($customer->primary_phone); ?></a>
                    </td>
                    
                    <td class="px-3.5 py-3 text-text-secondary max-w-[130px] xl:max-w-none truncate" title="<?php echo e($customer->village->name ?? '-'); ?>">
                        <?php echo e($customer->village->name ?? '-'); ?>

                    </td>
                    
                    <td class="px-3.5 py-3 text-center whitespace-nowrap">
                        <?php if($customer->status === 'waiting_survey'): ?>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold tracking-wide uppercase border" style="background:var(--color-warning-bg); color:var(--color-warning); border-color:var(--color-warning-border)">
                                <span class="w-1.5 h-1.5 rounded-full" style="background:var(--color-warning)"></span> Menunggu Survey
                            </span>
                        <?php elseif($customer->status === 'survey_in_progress'): ?>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold tracking-wide uppercase border" style="background:var(--color-info-bg); color:var(--color-info); border-color:var(--color-info-border)">
                                <span class="w-1.5 h-1.5 rounded-full animate-pulse" style="background:var(--color-info)"></span> Proses Survey
                            </span>
                        <?php endif; ?>
                    </td>
                    
                    <td class="px-3.5 py-3 font-mono text-xs text-text-secondary whitespace-nowrap" title="<?php echo e($customer->created_at->format('Y-m-d H:i:s')); ?>">
                        <?php echo e($customer->created_at->format('Y-m-d H:i')); ?>

                    </td>
                    
                    <td class="px-3.5 py-3 text-center whitespace-nowrap">
                        <?php if(in_array($customer->status, ['waiting_survey', 'survey_in_progress'])): ?>
                            <?php if (isset($component)) { $__componentOriginalb8d3d89751f3d81017aa8a59bd985fb5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb8d3d89751f3d81017aa8a59bd985fb5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.countdown-timer','data' => ['deadline' => ''.e($customer->created_at->addDay()->toIso8601String()).'','totalSeconds' => 86400,'label' => 'Sisa Waktu Survey','compact' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('countdown-timer'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['deadline' => ''.e($customer->created_at->addDay()->toIso8601String()).'','total-seconds' => 86400,'label' => 'Sisa Waktu Survey','compact' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb8d3d89751f3d81017aa8a59bd985fb5)): ?>
<?php $attributes = $__attributesOriginalb8d3d89751f3d81017aa8a59bd985fb5; ?>
<?php unset($__attributesOriginalb8d3d89751f3d81017aa8a59bd985fb5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb8d3d89751f3d81017aa8a59bd985fb5)): ?>
<?php $component = $__componentOriginalb8d3d89751f3d81017aa8a59bd985fb5; ?>
<?php unset($__componentOriginalb8d3d89751f3d81017aa8a59bd985fb5); ?>
<?php endif; ?>
                        <?php else: ?>
                            <span class="text-text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    
                    <td class="px-3.5 py-3 text-right whitespace-nowrap">
                        <div class="inline-flex items-center justify-end gap-1.5">
                            <?php if($detailUrl): ?>
                                <a href="<?php echo e($detailUrl); ?>" class="p-1.5 text-text-muted hover:text-primary hover:bg-surface-muted rounded-md transition-colors" title="Detail Pelanggan">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                </a>
                            <?php endif; ?>
                            
                            <?php if($customer->status === 'waiting_survey'): ?>
                                <form action="<?php echo e(route('customers.survey.start', $customer)); ?>" method="POST" onsubmit="event.preventDefault(); window.confirmAction('Mulai proses survey untuk pelanggan ini?', this);" class="inline-block">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit" class="text-[11px] font-bold uppercase tracking-wider py-1.5 px-3 rounded shadow-xs transition-colors cursor-pointer text-white" style="background:var(--color-warning)">
                                        Mulai Survey
                                    </button>
                                </form>
                            <?php elseif($customer->status === 'survey_in_progress'): ?>
                                <a href="<?php echo e(route('customers.survey.report', $customer)); ?>" class="text-[11px] font-bold uppercase tracking-wider py-1.5 px-3 rounded shadow-xs transition-colors cursor-pointer inline-block text-white" style="background:var(--color-success)">
                                    Lapor Data
                                </a>
                            <?php endif; ?>

                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('customers.detail.survey.reject')): ?>
                                <button type="button"
                                        onclick="openCancelSurveyModal('<?php echo e(route('customers.survey.cancel', $customer)); ?>', '<?php echo e(addslashes($customer->full_name)); ?>')"
                                        class="text-[11px] font-bold uppercase tracking-wider py-1.5 px-3 rounded shadow-xs transition-colors cursor-pointer text-white"
                                        style="background:var(--color-error)">
                                    Batalkan
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="9" class="px-6 py-12 text-center text-text-muted">
                        <div class="flex flex-col items-center justify-center gap-2">
                            <svg class="w-8 h-8 text-border" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                            <span class="text-sm font-medium">Tidak ada antrean survey saat ini.</span>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    
    <div class="@min-[56rem]:hidden p-3.5 sm:p-4 bg-background/40">
        <div class="grid grid-cols-1 @min-[34rem]:grid-cols-2 gap-3.5">
            <?php $__empty_1 = true; $__currentLoopData = $customers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $customer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php
                $survey = $customer->latestSurvey;
                $actor = auth()->user();
                $canSurveyReport = $actor->hasPermission('customers.detail.survey.view') || $actor->hasPermission('customers.detail.survey.update');
                $canFieldwork = $actor->hasPermission('customers.detail.devices.view') || $actor->hasPermission('customers.detail.installation.view');
                $detailUrl = match(true) {
                    $actor->hasPermission('customers.detail.installation.validate') || $actor->hasFullAccess() => route('customers.verification.admin', $customer),
                    $canSurveyReport && $customer->status === 'survey_in_progress' => route('customers.survey.report', $customer),
                    $actor->hasPermission('customers.detail.view') => route('customers.show', $customer),
                    $canFieldwork => route('customers.fieldwork', $customer),
                    default => null,
                };
            ?>
            <article class="bg-surface border border-border rounded-lg p-4 flex flex-col justify-between shadow-2xs hover:border-primary/40 transition-colors">
                <div>
                    
                    <div class="flex items-start justify-between gap-2 mb-2.5">
                        <div class="min-w-0">
                            <span class="inline-block font-mono text-xs font-bold text-text-muted uppercase tracking-wider"><?php echo e($customer->display_id); ?></span>
                            <h4 class="text-sm font-bold text-text-main truncate mt-0.5" title="<?php echo e($customer->full_name); ?>"><?php echo e($customer->full_name); ?></h4>
                            <a href="tel:<?php echo e($customer->primary_phone); ?>" class="inline-flex items-center gap-1 font-mono text-xs text-text-muted hover:text-primary transition-colors mt-0.5">
                                <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                <span><?php echo e($customer->primary_phone); ?></span>
                            </a>
                        </div>
                        <div class="shrink-0">
                            <?php if($customer->status === 'waiting_survey'): ?>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10px] font-bold tracking-wide uppercase border" style="background:var(--color-warning-bg); color:var(--color-warning); border-color:var(--color-warning-border)">
                                    <span class="w-1.5 h-1.5 rounded-full" style="background:var(--color-warning)"></span> Menunggu
                                </span>
                            <?php elseif($customer->status === 'survey_in_progress'): ?>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10px] font-bold tracking-wide uppercase border" style="background:var(--color-info-bg); color:var(--color-info); border-color:var(--color-info-border)">
                                    <span class="w-1.5 h-1.5 rounded-full animate-pulse" style="background:var(--color-info)"></span> Proses
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    
                    <dl class="grid grid-cols-2 gap-x-2 gap-y-2 text-xs border-t border-border/80 pt-2.5 mb-3">
                        <div class="min-w-0">
                            <dt class="text-[10px] font-semibold uppercase tracking-wider text-text-muted">Desa</dt>
                            <dd class="text-text-main font-medium truncate mt-0.5" title="<?php echo e($customer->village->name ?? '-'); ?>"><?php echo e($customer->village->name ?? '-'); ?></dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-[10px] font-semibold uppercase tracking-wider text-text-muted">Masuk Antrean</dt>
                            <dd class="font-mono text-xs text-text-secondary mt-0.5"><?php echo e($customer->created_at->format('d/m/y H:i')); ?></dd>
                        </div>
                        <div class="col-span-2 min-w-0">
                            <dt class="text-[10px] font-semibold uppercase tracking-wider text-text-muted mb-1">Sisa SLA</dt>
                            <dd>
                                <?php if(in_array($customer->status, ['waiting_survey', 'survey_in_progress'])): ?>
                                    <?php if (isset($component)) { $__componentOriginalb8d3d89751f3d81017aa8a59bd985fb5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb8d3d89751f3d81017aa8a59bd985fb5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.countdown-timer','data' => ['deadline' => ''.e($customer->created_at->addDay()->toIso8601String()).'','totalSeconds' => 86400,'label' => 'Sisa Waktu Survey','compact' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('countdown-timer'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['deadline' => ''.e($customer->created_at->addDay()->toIso8601String()).'','total-seconds' => 86400,'label' => 'Sisa Waktu Survey','compact' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb8d3d89751f3d81017aa8a59bd985fb5)): ?>
<?php $attributes = $__attributesOriginalb8d3d89751f3d81017aa8a59bd985fb5; ?>
<?php unset($__attributesOriginalb8d3d89751f3d81017aa8a59bd985fb5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb8d3d89751f3d81017aa8a59bd985fb5)): ?>
<?php $component = $__componentOriginalb8d3d89751f3d81017aa8a59bd985fb5; ?>
<?php unset($__componentOriginalb8d3d89751f3d81017aa8a59bd985fb5); ?>
<?php endif; ?>
                                <?php else: ?>
                                    <span class="text-text-muted">-</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                    </dl>
                </div>

                
                <div class="border-t border-border pt-3 flex items-center justify-between gap-2 mt-auto">
                    <?php if($detailUrl): ?>
                        <a href="<?php echo e($detailUrl); ?>" class="p-1.5 text-text-muted hover:text-primary hover:bg-surface-muted rounded-md transition-colors cursor-pointer shrink-0" title="Detail Pelanggan">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                        </a>
                    <?php else: ?>
                        <div></div>
                    <?php endif; ?>

                    <div class="flex items-center gap-1.5 flex-wrap justify-end">
                        <?php if($customer->status === 'waiting_survey'): ?>
                            <form action="<?php echo e(route('customers.survey.start', $customer)); ?>" method="POST" onsubmit="event.preventDefault(); window.confirmAction('Mulai proses survey untuk pelanggan ini?', this);">
                                <?php echo csrf_field(); ?>
                                <button type="submit" class="text-xs font-semibold py-1.5 px-3 rounded-md shadow-xs transition-colors cursor-pointer text-white" style="background:var(--color-warning)">
                                    Mulai Survey
                                </button>
                            </form>
                        <?php elseif($customer->status === 'survey_in_progress'): ?>
                            <a href="<?php echo e(route('customers.survey.report', $customer)); ?>" class="text-center text-xs font-semibold py-1.5 px-3 rounded-md shadow-xs transition-colors cursor-pointer inline-flex items-center text-white" style="background:var(--color-success)">
                                Lapor Data
                            </a>
                        <?php endif; ?>

                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('customers.detail.survey.reject')): ?>
                            <button type="button"
                                    onclick="openCancelSurveyModal('<?php echo e(route('customers.survey.cancel', $customer)); ?>', '<?php echo e(addslashes($customer->full_name)); ?>')"
                                    class="text-xs font-semibold py-1.5 px-3 rounded-md shadow-xs transition-colors cursor-pointer text-white"
                                    style="background:var(--color-error)">
                                Batalkan
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="col-span-full flex flex-col items-center justify-center gap-2 py-12 px-4 text-center text-text-muted">
                <svg class="w-8 h-8 text-border" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                <span class="text-sm font-medium">Tidak ada antrean survey saat ini.</span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if($customers->hasPages()): ?>
        <div class="border-t border-border px-4 sm:px-6 py-3.5 bg-surface-muted/50 dark:bg-transparent">
            <?php echo e($customers->links()); ?>

        </div>
    <?php endif; ?>
</div>

<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('customers.detail.survey.reject')): ?>
<div id="cancel-survey-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div class="bg-surface border border-border rounded-lg shadow-lg w-full max-w-md p-5">
        <h4 class="text-sm font-bold text-text-main mb-1">Batalkan Survey — Tidak Layak Pasang</h4>
        <p class="text-xs text-text-muted mb-4">
            Survey <strong id="cancel-survey-modal-name"></strong> akan diubah statusnya menjadi <strong>ditolak</strong>
            dan tidak bisa lanjut ke tahap pemasangan. Tindakan ini tidak bisa dibatalkan.
        </p>
        <form id="cancel-survey-modal-form" method="POST">
            <?php echo csrf_field(); ?>
            <label class="block text-xs font-semibold text-text-secondary mb-1">Alasan <span class="text-error">*</span></label>
            <textarea name="reason" rows="3" required class="w-full text-xs border border-border rounded-md px-3 py-2 mb-4 bg-background text-text-main focus:outline-none focus:border-error focus:ring-1 focus:ring-error" placeholder="Contoh: Alamat tidak ditemukan, lokasi di luar jangkauan ODP, pelanggan menolak, dll."></textarea>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('cancel-survey-modal').classList.add('hidden')" class="btn-secondary text-xs px-3 py-1.5 cursor-pointer">Batal</button>
                <button type="submit" class="text-xs px-3 py-1.5 rounded-md font-semibold text-white cursor-pointer shadow-xs" style="background:var(--color-error);">Ya, Batalkan Survey</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openCancelSurveyModal(actionUrl, customerName) {
        document.getElementById('cancel-survey-modal-form').setAttribute('action', actionUrl);
        document.getElementById('cancel-survey-modal-name').textContent = customerName;
        document.getElementById('cancel-survey-modal').classList.remove('hidden');
    }
</script>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/surveys/queue.blade.php ENDPATH**/ ?>