
<?php
    $installation = $installation ?? $customer->latestInstallation;

    $statusLabel = match($customer->status) {
        'waiting_acc', 'surveyed' => 'MENUNGGU ACC',
        'waiting_installation' => 'MENUNGGU PEMASANGAN',
        'installation_in_progress' => 'MULAI PASANG',
        'revision_installation' => 'REVISI PEMASANGAN',
        'installed', 'verification_admin' => 'VERIFIKASI ADMIN',
        default => $customer->status,
    };

    $statusStyle = match($customer->status) {
        'waiting_acc', 'surveyed' => 'background:var(--color-warning-bg); color:var(--color-warning); border-color:var(--color-warning-border);',
        'waiting_installation' => 'background:var(--color-surface-muted); color:var(--color-text-main); border-color:var(--color-border);',
        'installation_in_progress' => 'background:var(--color-info-bg); color:var(--color-info); border-color:var(--color-info-border);',
        'revision_installation' => 'background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border);',
        'installed', 'verification_admin' => 'background:var(--color-success-bg); color:var(--color-success); border-color:var(--color-success-border);',
        default => 'background:var(--color-surface-muted); color:var(--color-text-main); border-color:var(--color-border);',
    };

    $showPulse = in_array($customer->status, ['installation_in_progress', 'revision_installation']);

    $canValidate = auth()->user()->hasPermission('customers.detail.installation.validate') || auth()->user()->hasFullAccess();
    $detailUrl = match(true) {
        $canValidate => route('customers.verification.admin', $customer),
        in_array($customer->status, ['installation_in_progress', 'revision_installation', 'installed', 'verification_admin']) => route('customers.installation.report', $customer),
        in_array($customer->status, ['waiting_acc', 'surveyed', 'waiting_installation']) => route('customers.survey.report', $customer),
        auth()->user()->hasPermission('customers.detail.view') => route('customers.show', $customer),
        default => route('customers.fieldwork', $customer),
    };
?>
<td class="px-6 py-3.5 text-center" id="customer-status-cell-<?php echo e($customer->id); ?>">
    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold tracking-wide uppercase border" style="<?php echo e($statusStyle); ?>">
        <?php if($showPulse): ?>
            <span class="w-1.5 h-1.5 rounded-full animate-pulse" style="background:currentColor"></span>
        <?php endif; ?>
        <?php echo e($statusLabel); ?>

    </span>
</td>
<td class="px-6 py-3.5 font-mono text-xs" id="customer-live-cell-<?php echo e($customer->id); ?>">
    <?php if(($customer->status === 'installation_in_progress' || $customer->status === 'revision_installation') && $installation && $installation->started_at): ?>
        <div class="font-bold" id="countdown-<?php echo e($customer->id); ?>" data-start="<?php echo e($installation->started_at->toIso8601String()); ?>" style="color:var(--color-info)">
            Menghitung...
        </div>
    <?php elseif($customer->status === 'waiting_installation' || $customer->status === 'waiting_acc' || $customer->status === 'surveyed'): ?>
        <?php
            $surveyCompletedAt = $customer->tasks->first()?->completed_at;
        ?>
        <?php if($surveyCompletedAt): ?>
            <?php if (isset($component)) { $__componentOriginalb8d3d89751f3d81017aa8a59bd985fb5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb8d3d89751f3d81017aa8a59bd985fb5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.countdown-timer','data' => ['deadline' => ''.e(\Carbon\Carbon::parse($surveyCompletedAt)->addDays(3)->toIso8601String()).'','totalSeconds' => 259200,'label' => 'Sisa Pemasangan','compact' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('countdown-timer'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['deadline' => ''.e(\Carbon\Carbon::parse($surveyCompletedAt)->addDays(3)->toIso8601String()).'','total-seconds' => 259200,'label' => 'Sisa Pemasangan','compact' => true]); ?>
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
            <span class="text-text-muted">Belum Mulai</span>
        <?php endif; ?>
    <?php else: ?>
        <span class="text-success font-bold">Selesai</span>
    <?php endif; ?>
</td>
<td class="px-6 py-3.5 text-right whitespace-nowrap" id="customer-action-cell-<?php echo e($customer->id); ?>">
    <div class="flex items-center justify-end gap-2">
        <button type="button" class="text-text-muted hover:text-primary transition-colors p-1" title="Generate/Lihat QR" onclick="window.Toast.info('Mockup', 'Generate/Lihat QR')">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
            </svg>
        </button>

        <?php if($customer->status === 'waiting_acc' || $customer->status === 'surveyed'): ?>
            <?php if($canValidate): ?>
            <a href="<?php echo e(route('customers.verification.admin', $customer)); ?>" class="bg-warning hover:bg-warning/90 text-white text-[11px] font-bold uppercase tracking-wider py-1.5 px-3 rounded shadow-sm transition-colors cursor-pointer text-center inline-flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                Detail & Review
            </a>
            <?php else: ?>
            <a href="<?php echo e($detailUrl); ?>" class="text-text-muted hover:text-primary transition-colors p-1" title="Detail">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
            </a>
            <?php endif; ?>
        <?php elseif($customer->status === 'installed' || $customer->status === 'verification_admin'): ?>
            <?php if($canValidate): ?>
            <a href="<?php echo e(route('customers.verification.admin', $customer)); ?>" class="bg-success hover:bg-success/90 text-white text-[11px] font-bold uppercase tracking-wider py-1.5 px-3 rounded shadow-sm transition-colors cursor-pointer text-center inline-flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Verifikasi Admin
            </a>
            <?php else: ?>
            <a href="<?php echo e($detailUrl); ?>" class="text-text-muted hover:text-primary transition-colors p-1" title="Detail">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
            </a>
            <?php endif; ?>
        <?php else: ?>
            <a href="<?php echo e($detailUrl); ?>" class="text-text-muted hover:text-primary transition-colors p-1" title="Detail">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
            </a>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('customers.detail.installation.validate')): ?>
            <button type="button" onclick="openRejectModal('<?php echo e($customer->id); ?>')" class="text-text-muted hover:text-error transition-colors p-1" title="Batal / Gagal">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
            <?php endif; ?>

            <?php if($customer->status === 'waiting_installation'): ?>
                <form action="<?php echo e(route('customers.installation.start', $customer)); ?>" method="POST" class="inline-block m-0 p-0" onsubmit="event.preventDefault(); window.confirmAction('Mulai proses pemasangan untuk pelanggan ini?', this);">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="bg-primary hover:bg-primary/90 text-white text-[11px] font-bold uppercase tracking-wider py-1.5 px-3 rounded shadow-sm transition-colors cursor-pointer">
                        Start Proses
                    </button>
                </form>
            <?php elseif($customer->status === 'installation_in_progress'): ?>
                <a href="<?php echo e(route('customers.installation.report', $customer)); ?>" class="bg-success hover:bg-success/90 text-white text-[11px] font-bold uppercase tracking-wider py-1.5 px-3 rounded shadow-sm transition-colors cursor-pointer text-center">
                    Lapor Pemasangan
                </a>
            <?php elseif($customer->status === 'revision_installation'): ?>
                <a href="<?php echo e(route('customers.installation.report', $customer)); ?>" class="bg-error hover:bg-error/90 text-white text-[11px] font-bold uppercase tracking-wider py-1.5 px-3 rounded shadow-sm transition-colors cursor-pointer text-center">
                    Revisi
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</td>
<?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/verifications/partials/queue-status-cells.blade.php ENDPATH**/ ?>