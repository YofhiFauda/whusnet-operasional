<?php
    $status = $customer->status;
    $isSurveyStage = in_array($status, ['waiting_survey', 'survey_in_progress']);
    $isWaitingAccStage = in_array($status, ['waiting_acc', 'surveyed']);
    $isInstallationStage = in_array($status, ['waiting_installation', 'installation_in_progress', 'revision_installation']);
    // 'waiting_business_development_verification' (kategori Bisnis, gate BD,
    // ADHOC-67 susulan) TERMASUK di sini — pelanggan tahap ini SUDAH lolos
    // CS (tab Registrasi/Survey/Pemasangan/Pengujian sudah final), cuma tab
    // Verifikasi yang beda isinya (lihat cabang BD di dalamnya).
    $isVerifAdminStage = in_array($status, ['installed', 'verification_admin', 'active', \App\Enums\WorkflowTransition::WAITING_BUSINESS_DEVELOPMENT_VERIFICATION->value]);
    $isWaitingBdStageForBadge = $status === \App\Enums\WorkflowTransition::WAITING_BUSINESS_DEVELOPMENT_VERIFICATION->value;

    $showTabSurvey = !$isSurveyStage;
    $showTabPemasangan = !$isSurveyStage && !$isWaitingAccStage;
    $showTabPengujian = $isVerifAdminStage;
    $showTabVerifikasi = $isVerifAdminStage;

    $breadcrumbQueueName = match(true) {
        $isSurveyStage => 'Antrean Survey',
        $isWaitingBdStageForBadge => 'Menunggu Verifikasi BD',
        default => 'Antrean Verifikasi & Pemasangan',
    };
    $breadcrumbQueueRoute = match(true) {
        $isSurveyStage => route('surveys.queue'),
        $isWaitingBdStageForBadge => route('business-development-verifications.index'),
        default => route('verifications.queue'),
    };

    $stageBadgeLabel = match(true) {
        $isSurveyStage => 'Antrean Survey',
        $isWaitingAccStage => 'Menunggu ACC Survey',
        $isInstallationStage => 'Proses Pemasangan',
        $isWaitingBdStageForBadge => 'Verifikasi BD',
        $isVerifAdminStage => 'Verifikasi Admin',
        default => Str::headline($status),
    };
?>

<?php $__env->startSection('title', 'Verifikasi Admin - ' . $customer->full_name); ?>
<?php $__env->startSection('page_title', 'Verifikasi Admin'); ?>
<?php $__env->startSection('breadcrumb_parent', $breadcrumbQueueName); ?>
<?php $__env->startSection('breadcrumb_parent_url', $breadcrumbQueueRoute); ?>

<?php $__env->startSection('content'); ?>




<div class="bg-surface border border-border rounded-xl shadow-sm p-6 mb-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-100 dark:bg-emerald-950 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            </div>
            <div>
                <h2 class="text-lg font-bold text-text-main"><?php echo e($customer->full_name); ?></h2>
                <div class="flex items-center gap-3 mt-1 flex-wrap">
                    <span class="text-xs font-mono text-text-muted"><?php echo e($customer->display_id); ?></span>
                    <span class="text-text-disabled">·</span>
                    <span class="text-xs text-text-secondary"><?php echo e($customer->primary_phone); ?></span>
                    <span class="text-text-disabled">·</span>
                    <span class="text-xs text-text-secondary"><?php echo e($customer->pop->name ?? '-'); ?></span>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold tracking-wide uppercase bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <?php echo e($stageBadgeLabel); ?>

            </span>
            <a href="<?php echo e(route('customers.show', $customer)); ?>" class="text-xs font-semibold text-text-secondary hover:text-primary transition-colors px-3 py-1.5 border border-border rounded-md hover:border-primary-border hover:bg-primary-soft">
                Detail Profil →
            </a>
        </div>
    </div>
</div>


<div class="mb-6">
    <div class="bg-surface border border-border rounded-xl shadow-sm overflow-hidden">
        <div class="flex flex-wrap md:flex-nowrap border-b border-border" id="tab-nav">
            <button type="button" onclick="switchTab('registrasi')" id="tab-btn-registrasi"
                class="tab-btn flex-1 px-4 py-4 text-sm font-semibold text-center border-b-2 border-primary text-primary bg-primary-soft/40 transition-all focus:outline-none">
                <span class="flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Data Registrasi
                </span>
            </button>
            <?php if($showTabSurvey): ?>
            <button type="button" onclick="switchTab('survey')" id="tab-btn-survey"
                class="tab-btn flex-1 px-4 py-4 text-sm font-semibold text-center border-b-2 border-transparent text-text-secondary hover:text-text-main hover:bg-surface-muted/50 transition-all focus:outline-none">
                <span class="flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                    Survey
                </span>
            </button>
            <?php endif; ?>
            <?php if($showTabPemasangan): ?>
            <button type="button" onclick="switchTab('pemasangan')" id="tab-btn-pemasangan"
                class="tab-btn flex-1 px-4 py-4 text-sm font-semibold text-center border-b-2 border-transparent text-text-secondary hover:text-text-main hover:bg-surface-muted/50 transition-all focus:outline-none">
                <span class="flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Pemasangan
                </span>
            </button>
            <?php endif; ?>
            <?php if($showTabPengujian): ?>
            <button type="button" onclick="switchTab('pengujian')" id="tab-btn-pengujian"
                class="tab-btn flex-1 px-4 py-4 text-sm font-semibold text-center border-b-2 border-transparent text-text-secondary hover:text-text-main hover:bg-surface-muted/50 transition-all focus:outline-none">
                <span class="flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Pengujian
                </span>
            </button>
            <?php endif; ?>
            <?php if($showTabVerifikasi): ?>
            <button type="button" onclick="switchTab('verifikasi')" id="tab-btn-verifikasi"
                class="tab-btn flex-1 px-4 py-4 text-sm font-semibold text-center border-b-2 border-transparent text-text-secondary hover:text-text-main hover:bg-surface-muted/50 transition-all focus:outline-none">
                <span class="flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Aktivasi
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-warning-bg text-warning border border-warning-border uppercase tracking-wide">Aksi</span>
                </span>
            </button>
            <?php endif; ?>
        </div>

        
        
        
        <div id="tab-registrasi" class="tab-panel p-6 md:p-8">
            <?php echo $__env->make('verifications.partials._registration-info', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>

        
        
        
        <div id="tab-survey" class="tab-panel p-6 md:p-8 hidden">
            <?php
                $survey = $customer->latestSurvey;
            ?>

            <?php if($survey): ?>
            <div class="mb-6 bg-primary-soft border border-primary-border rounded-xl p-5">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-primary mb-1">Status Survey</span>
                        <?php
                            $sStatus = $survey->survey_status ?? 'pending';
                            $sc = ['completed' => 'bg-emerald-100 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-400 border-emerald-200 dark:border-emerald-900', 'failed' => 'bg-red-100 dark:bg-red-950/40 text-red-800 dark:text-red-400 border-red-200 dark:border-red-900', 'in_progress' => 'bg-sky-100 dark:bg-sky-950/40 text-sky-850 dark:text-sky-400 border-sky-200 dark:border-sky-900', 'scheduled' => 'bg-surface-muted text-text-secondary border-border'];
                        ?>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wide border <?php echo e($sc[$sStatus] ?? $sc['scheduled']); ?>">
                            <?php echo e(ucfirst($sStatus)); ?>

                        </span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-primary mb-1">Mulai Survey</span>
                        <span class="block text-sm font-mono font-bold text-text-main">
                            <?php echo e($survey->started_at ? $survey->started_at->format('d M Y, H:i') : '-'); ?>

                        </span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-primary mb-1">Selesai Survey</span>
                        <span class="block text-sm font-mono font-bold text-text-main">
                            <?php echo e($survey->completed_at ? $survey->completed_at->format('d M Y, H:i') : '-'); ?>

                        </span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-primary mb-1">Durasi Survey</span>
                        <span class="block text-sm font-mono font-bold text-emerald-700 dark:text-emerald-400">
                            <?php echo e($survey->duration_minutes ? $survey->duration_minutes . ' Menit' : '-'); ?>

                        </span>
                    </div>
                </div>
            </div>

            <h4 class="text-xs font-bold text-text-muted uppercase tracking-wider mb-4 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                Tim Surveyor
            </h4>
            <div class="bg-surface-muted dark:bg-transparent border border-border rounded-xl p-5 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Teknisi Utama</span>
                        <span class="block text-sm font-bold text-text-main"><?php echo e($survey->technician->name ?? '-'); ?></span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Surveyor 2</span>
                        <span class="block text-sm text-text-main"><?php echo e($survey->surveyor2->name ?? '-'); ?></span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Surveyor 3</span>
                        <span class="block text-sm text-text-main"><?php echo e($survey->surveyor3->name ?? '-'); ?></span>
                    </div>
                </div>
            </div>

            <h4 class="text-xs font-bold text-text-muted uppercase tracking-wider mb-4 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Hasil Survey Teknis
            </h4>
            <div class="bg-surface-muted dark:bg-transparent border border-border rounded-xl p-5 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">ODP Terdekat</span>
                        <span class="block text-sm font-mono font-bold text-text-main"><?php echo e($survey->nearest_odp ?? '-'); ?></span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Estimasi Kabel (Meter)</span>
                        <span class="block text-sm font-mono font-bold text-text-main"><?php echo e($survey->cable_estimation_meter ?? '-'); ?> Meter</span>
                    </div>
                    <div>
                        
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">FOP Penanggung Jawab</span>
                        <span class="block text-sm text-text-main"><?php echo e($survey->fop->name ?? '-'); ?></span>
                    </div>
                    <?php if($survey->requested_installation_date): ?>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Request Pemasangan Pelanggan</span>
                        <span class="block text-sm font-mono font-bold text-text-main"><?php echo e(\App\Support\IndonesianDate::date($survey->requested_installation_date)); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if($survey->required_tools): ?>
                    <div class="md:col-span-3">
                        
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Catatan Kendala Peralatan</span>
                        <p class="text-sm text-text-secondary whitespace-pre-wrap"><?php echo e($survey->required_tools); ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if($survey->survey_note): ?>
                    <div class="md:col-span-3 pt-4 border-t border-border mt-2">
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Catatan Surveyor</span>
                        <p class="text-sm text-text-secondary whitespace-pre-wrap"><?php echo e($survey->survey_note); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php echo $__env->make('verifications.partials.materials', [
                'title' => 'Estimasi Material Hasil Survey',
                'emptyText' => 'Surveyor tidak mencatat estimasi material.',
                'rows' => $surveyMaterials,
            ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

            <?php echo $__env->make('verifications.partials.work-tools', [
                'title' => 'Alat Kerja Dicatat Surveyor',
                'rows' => $surveyWorkTools,
            ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

            <?php
                $surveyPhotoUrl = foto_publik($survey->survey_photo);
                $surveyHousePhotoUrl = foto_publik($survey->house_photo);
            ?>
            <?php if($surveyPhotoUrl || $surveyHousePhotoUrl): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <?php if($surveyPhotoUrl): ?>
                <div>
                    <h4 class="text-xs font-bold text-text-muted uppercase tracking-wider mb-4">Foto Lokasi / Survey</h4>
                    <div class="bg-surface-muted dark:bg-transparent border border-border rounded-xl p-4 text-center inline-block w-full">
                        <img src="<?php echo e($surveyPhotoUrl); ?>" alt="Foto Survey" class="max-h-48 object-contain mx-auto rounded-lg shadow-sm cursor-pointer hover:opacity-90" onclick="openPhotoLightbox('<?php echo e($surveyPhotoUrl); ?>', 'Foto Survey')">
                    </div>
                </div>
                <?php endif; ?>
                <?php if($surveyHousePhotoUrl): ?>
                <div>
                    <h4 class="text-xs font-bold text-text-muted uppercase tracking-wider mb-4">Foto Rumah</h4>
                    <div class="bg-surface-muted dark:bg-transparent border border-border rounded-xl p-4 text-center inline-block w-full">
                        <img src="<?php echo e($surveyHousePhotoUrl); ?>" alt="Foto Rumah" class="max-h-48 object-contain mx-auto rounded-lg shadow-sm cursor-pointer hover:opacity-90" onclick="openPhotoLightbox('<?php echo e($surveyHousePhotoUrl); ?>', 'Foto Rumah')">
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if($isWaitingAccStage): ?>
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('customers.detail.installation.validate')): ?>
                <div class="mt-6 p-5 bg-warning-bg border border-warning-border rounded-xl flex flex-col md:flex-row items-center justify-between gap-4">
                    <div>
                        <h5 class="text-sm font-bold text-warning">Persetujuan Hasil Survey</h5>
                        <p class="text-xs text-warning mt-0.5">Survey telah selesai dilaporkan. Periksa data registrasi dan hasil survey di atas, lalu tentukan keputusan persetujuan.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="button" onclick="openRejectModal()" class="px-4 py-2 bg-error-bg hover:bg-error-bg/80 text-error border border-error-border text-xs font-bold uppercase tracking-wider rounded-lg transition-colors cursor-pointer">
                            Batalkan / Gagal
                        </button>
                        <form action="<?php echo e(route('customers.verification.process-to-team', $customer)); ?>" method="POST" onsubmit="event.preventDefault(); window.confirmAction('Setujui hasil survey dan proses pelanggan ini ke tim pemasangan?', this);">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold uppercase tracking-wider rounded-lg shadow-sm transition-colors cursor-pointer">
                                Setujui & Proses ke Tim Pemasangan
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php else: ?>
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 flex items-center gap-3">
                <svg class="w-6 h-6 text-warning shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <p class="text-sm text-warning font-medium">Tidak ada data laporan survey untuk pelanggan ini.</p>
            </div>
            <?php endif; ?>
        </div>

        
        
        
        <div id="tab-pemasangan" class="tab-panel p-6 md:p-8 hidden">

            <?php
                $installation = $customer->latestInstallation;
                $device = $customer->customerDevice;
                $techDetail = $customer->customerTechnicalDetail;
            ?>

            
            <?php if($installation): ?>
            <div class="mb-6 bg-primary-soft border border-primary-border rounded-xl p-5">
                <div class="flex flex-wrap gap-6">
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-primary mb-1">Mulai Pemasangan</span>
                        <span class="block text-sm font-mono font-bold text-text-main">
                            <?php echo e($installation->started_at ? $installation->started_at->format('d M Y, H:i') : '-'); ?>

                        </span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-primary mb-1">Selesai Pemasangan</span>
                        <span class="block text-sm font-mono font-bold text-text-main">
                            <?php echo e($installation->completed_at ? $installation->completed_at->format('d M Y, H:i') : '-'); ?>

                        </span>
                    </div>
                    <?php if($installation->started_at && $installation->completed_at): ?>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-primary mb-1">Durasi (SLA)</span>
                        <?php
                            $duration = $installation->started_at->diff($installation->completed_at);
                        ?>
                        <span class="block text-sm font-mono font-bold text-emerald-700 dark:text-emerald-400">
                            <?php echo e($duration->h); ?>j <?php echo e($duration->i); ?>m <?php echo e($duration->s); ?>d
                        </span>
                    </div>
                    <?php endif; ?>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-primary mb-1">Status Pemasangan</span>
                        <?php
                            $statusMap = [
                                'completed' => ['label' => 'Selesai', 'class' => 'bg-success-bg text-success border-success-border'], 
                                'failed' => ['label' => 'Gagal', 'class' => 'bg-error-bg text-error border-error-border'], 
                                'in_progress' => ['label' => 'Proses', 'class' => 'bg-primary-soft text-primary border-primary-border'], 
                                'scheduled' => ['label' => 'Terjadwal', 'class' => 'bg-surface-muted text-text-secondary border-border']
                            ];
                            $statusInfo = $statusMap[$installation->installation_status] ?? ['label' => ucfirst($installation->installation_status ?? '-'), 'class' => 'bg-surface-muted text-text-secondary border-border'];
                        ?>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wide border <?php echo e($statusInfo['class']); ?>">
                            <?php echo e($statusInfo['label']); ?>

                        </span>
                    </div>
                </div>
                <?php if($installation->installation_note): ?>
                <div class="mt-4 pt-4 border-t border-primary-border">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-primary mb-1">Catatan Pemasangan</span>
                    <p class="text-sm text-text-secondary whitespace-pre-wrap"><?php echo e($installation->installation_note); ?></p>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="mb-6 bg-warning-bg border border-warning-border rounded-xl p-4 flex items-center gap-3">
                <svg class="w-5 h-5 text-warning shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <p class="text-sm text-warning font-medium">Data riwayat pemasangan tidak ditemukan untuk pelanggan ini.</p>
            </div>
            <?php endif; ?>

            
            <div class="mb-6">
                <h4 class="text-xs font-bold text-text-muted uppercase tracking-wider mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2v-4M9 21H5a2 2 0 01-2-2v-4m0 0h18"/></svg>
                    Data Perangkat
                </h4>
                <div class="bg-surface-muted dark:bg-transparent border border-border rounded-xl p-5">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-5">
                        <?php
                            $deviceFields = [
                                ['label' => 'Jenis Perangkat', 'value' => strtoupper($device->device_type ?? '-')],
                                ['label' => 'Mode Koneksi', 'value' => strtoupper($device->connection_mode ?? '-')],
                                ['label' => 'Merk', 'value' => $device->brand ?? '-'],
                                ['label' => 'Tipe / Model', 'value' => $device->model ?? '-'],
                                ['label' => 'Serial Number', 'value' => $device->serial_number ?? '-', 'mono' => true],
                                ['label' => 'MAC Address', 'value' => $device->mac_address ?? '-', 'mono' => true],
                                ['label' => 'Username PPPoE', 'value' => $device->pppoe_username ?? '-', 'mono' => true],
                                ['label' => 'Password PPPoE', 'value' => $device->pppoe_password ?? '-', 'mono' => true],
                                ['label' => 'SSID WiFi', 'value' => $device->wifi_ssid ?? ($techDetail->ssid ?? '-')],
                                ['label' => 'Password WiFi', 'value' => $device->wifi_password ?? '-'],
                            ];
                        ?>
                        <?php $__currentLoopData = $deviceFields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div>
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1"><?php echo e($field['label']); ?></span>
                            <span class="block text-sm <?php echo e(($field['mono'] ?? false) ? 'font-mono' : ''); ?> text-text-main font-medium">
                                <?php echo e($field['value']); ?>

                            </span>
                        </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
            </div>

            <?php echo $__env->make('verifications.partials.installed-devices', [
                'rows' => $installedSerials,
            ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

            <?php echo $__env->make('verifications.partials.materials', [
                'title' => 'Material Terpakai Saat Pemasangan',
                'emptyText' => 'Tim pemasangan tidak mencatat material terpakai.',
                'rows' => $installationMaterials,
            ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

            
            
            <?php if(!empty($materialVariance)): ?>
            <div class="mb-6">
                <h4 class="text-xs font-bold text-text-muted uppercase tracking-wider mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    Material — Estimasi vs Terpakai
                </h4>
                <div class="bg-surface-muted dark:bg-transparent border border-border rounded-xl p-5 overflow-x-auto">
                    <table class="w-full text-sm min-w-[480px]">
                        <thead>
                            <tr class="text-[10px] font-bold uppercase tracking-wider text-text-muted border-b border-border">
                                <th class="text-left pb-2">Barang</th>
                                <th class="text-right pb-2">Estimasi</th>
                                <th class="text-right pb-2">Terpakai</th>
                                <th class="text-right pb-2">Selisih</th>
                                <th class="text-left pb-2 pl-4">Satuan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <?php $__currentLoopData = $materialVariance; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr>
                                <td class="py-2 text-text-main font-medium"><?php echo e($row['label']); ?></td>
                                <td class="py-2 text-right font-mono text-text-muted"><?php echo e(rtrim(rtrim(number_format($row['estimasi'], 2, ',', '.'), '0'), ',')); ?></td>
                                <td class="py-2 text-right font-mono text-text-main font-semibold"><?php echo e(rtrim(rtrim(number_format($row['terpakai'], 2, ',', '.'), '0'), ',')); ?></td>
                                <td class="py-2 text-right font-mono font-semibold <?php echo e($row['selisih'] > 0 ? 'text-error' : ($row['selisih'] < 0 ? 'text-success' : 'text-text-muted')); ?>">
                                    <?php echo e($row['selisih'] > 0 ? '+' : ''); ?><?php echo e(rtrim(rtrim(number_format($row['selisih'], 2, ',', '.'), '0'), ',')); ?>

                                </td>
                                <td class="py-2 pl-4 text-text-muted"><?php echo e($row['unit']); ?></td>
                            </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <?php echo $__env->make('verifications.partials.work-tools', [
                'title' => 'Alat Kerja Dipakai Tim Pemasangan',
                'rows' => $installationWorkTools,
            ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

            
            <div class="mb-6">
                <h4 class="text-xs font-bold text-text-muted uppercase tracking-wider mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/></svg>
                    Informasi Distribusi Jaringan (ODP / OLT)
                </h4>
                <div class="bg-surface-muted dark:bg-transparent border border-border rounded-xl p-5">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-5">
                        <?php
                            $netFields = [
                                ['label' => 'Nomor ODP', 'value' => $techDetail->odp_number ?? '-', 'mono' => true],
                                ['label' => 'Port ODP', 'value' => $techDetail->odp_port ?? '-', 'mono' => true],
                                ['label' => 'Nomor OLT', 'value' => $techDetail->olt_number ?? '-', 'mono' => true],
                                ['label' => 'Slot OLT', 'value' => $techDetail->olt_slot ?? '-', 'mono' => true],
                                ['label' => 'Port OLT', 'value' => $techDetail->olt_port ?? '-', 'mono' => true],
                                ['label' => 'VLAN', 'value' => $techDetail->vlan ?? '-', 'mono' => true],
                                ['label' => 'Nomor Router', 'value' => $techDetail->router_number ?? '-', 'mono' => true],
                                ['label' => 'Redaman Awal (dBm)', 'value' => $techDetail->initial_attenuation ?? '-', 'mono' => true],
                            ];
                        ?>
                        <?php $__currentLoopData = $netFields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div>
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1"><?php echo e($field['label']); ?></span>
                            <span class="block text-sm <?php echo e(($field['mono'] ?? false) ? 'font-mono' : ''); ?> text-text-main font-medium">
                                <?php echo e($field['value']); ?>

                            </span>
                        </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
            </div>

            
            <?php if($installation): ?>
            <?php
                $installationPhotoUrl = foto_publik($installation->installation_photo);
                $contractPhotoUrl = foto_publik($installation->contract_photo);
                $signaturePhotoUrl = foto_publik($installation->signature_photo);
            ?>
            <?php if($installationPhotoUrl || $contractPhotoUrl || $signaturePhotoUrl): ?>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <?php if($installationPhotoUrl): ?>
                <div>
                    <h4 class="text-xs font-bold text-text-muted uppercase tracking-wider mb-4">Foto Pemasangan</h4>
                    <div class="bg-surface-muted dark:bg-transparent border border-border rounded-xl p-4 text-center">
                        <img src="<?php echo e($installationPhotoUrl); ?>" 
                             alt="Foto Pemasangan" 
                             class="h-32 object-contain mx-auto rounded-lg shadow-sm cursor-pointer hover:opacity-90 transition-opacity"
                             onclick="openPhotoLightbox('<?php echo e($installationPhotoUrl); ?>', 'Foto Pemasangan')">
                    </div>
                </div>
                <?php endif; ?>
                <?php if($contractPhotoUrl): ?>
                <div>
                    <h4 class="text-xs font-bold text-text-muted uppercase tracking-wider mb-4">Foto Kontrak</h4>
                    <div class="bg-surface-muted dark:bg-transparent border border-border rounded-xl p-4 text-center">
                        <img src="<?php echo e($contractPhotoUrl); ?>" 
                             alt="Foto Kontrak" 
                             class="h-32 object-contain mx-auto rounded-lg shadow-sm cursor-pointer hover:opacity-90 transition-opacity"
                             onclick="openPhotoLightbox('<?php echo e($contractPhotoUrl); ?>', 'Foto Kontrak')">
                    </div>
                </div>
                <?php endif; ?>
                <?php if($signaturePhotoUrl): ?>
                <div>
                    <h4 class="text-xs font-bold text-text-muted uppercase tracking-wider mb-4">Foto TTD Pelanggan</h4>
                    <div class="bg-surface-muted dark:bg-transparent border border-border rounded-xl p-4 text-center">
                        <img src="<?php echo e($signaturePhotoUrl); ?>" 
                             alt="Foto TTD Pelanggan" 
                             class="h-32 object-contain mx-auto rounded-lg shadow-sm cursor-pointer hover:opacity-90 transition-opacity"
                             onclick="openPhotoLightbox('<?php echo e($signaturePhotoUrl); ?>', 'Foto TTD Pelanggan')">
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        
        
        
        <div id="tab-pengujian" class="tab-panel p-6 md:p-8 hidden">

            <?php if($techDetail): ?>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <?php
                    $package = $customer->internetPackage;
                    $downloadSpeed = $techDetail->test_download;
                    $uploadSpeed = $techDetail->test_upload;
                    $conformity = $techDetail->speed_conformity_percent;

                    $downloadColor = $conformity >= 90 ? 'emerald' : ($conformity >= 70 ? 'amber' : 'red');
                ?>

                <div class="bg-primary-soft border border-primary-border rounded-xl p-4 text-center">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-primary mb-1">Download</span>
                    <span class="block text-2xl font-extrabold font-mono text-primary"><?php echo e($downloadSpeed ?? '-'); ?></span>
                    <span class="block text-xs text-text-muted mt-1">Mbps</span>
                </div>

                <div class="bg-primary-soft border border-primary-border rounded-xl p-4 text-center">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-primary mb-1">Upload</span>
                    <span class="block text-2xl font-extrabold font-mono text-primary"><?php echo e($uploadSpeed ?? '-'); ?></span>
                    <span class="block text-xs text-text-muted mt-1">Mbps</span>
                </div>

                <div class="bg-warning-bg border border-warning-border rounded-xl p-4 text-center">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-warning mb-1">Latency</span>
                    <span class="block text-2xl font-extrabold font-mono text-warning"><?php echo e($techDetail->latency_ms ?? '-'); ?></span>
                    <span class="block text-xs text-text-muted mt-1">ms</span>
                </div>

                <div class="bg-info-bg border border-info-border rounded-xl p-4 text-center">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-info mb-1">Jitter</span>
                    <span class="block text-2xl font-extrabold font-mono text-info"><?php echo e($techDetail->jitter_ms ?? '-'); ?></span>
                    <span class="block text-xs text-text-muted mt-1">ms</span>
                </div>
            </div>

            
            <div class="bg-surface-muted dark:bg-transparent border border-border rounded-xl p-5 mb-6">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-5">
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Packet Loss</span>
                        <span class="block text-sm font-mono font-bold <?php echo e(($techDetail->packet_loss_percent ?? 0) <= 1 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400'); ?>">
                            <?php echo e($techDetail->packet_loss_percent !== null ? $techDetail->packet_loss_percent . '%' : '-'); ?>

                        </span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Redaman Aktual (dBm)</span>
                        <span class="block text-sm font-mono font-bold text-text-main"><?php echo e($techDetail->actual_attenuation ?? '-'); ?></span>
                    </div>
                    <?php if($package): ?>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Paket (Target)</span>
                        <span class="block text-sm font-bold text-text-main"><?php echo e($package->name); ?> (<?php echo e($package->download_speed_mbps ?? '-'); ?> Mbps)</span>
                    </div>
                    <?php endif; ?>
                    <?php if($conformity !== null): ?>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">% Sesuai Paket</span>
                        <span class="block text-xl font-extrabold font-mono <?php echo e($downloadColor === 'emerald' ? 'text-emerald-700 dark:text-emerald-400' : ($downloadColor === 'amber' ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400')); ?>">
                            <?php echo e(number_format($conformity, 1)); ?>%
                        </span>
                    </div>
                    <?php endif; ?>
                </div>

                
                <?php if($conformity !== null): ?>
                <div class="mt-4 pt-4 border-t border-border">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted">Kesesuaian Kecepatan Paket</span>
                        <span class="text-xs font-bold font-mono <?php echo e($downloadColor === 'emerald' ? 'text-emerald-600 dark:text-emerald-400' : ($downloadColor === 'amber' ? 'text-amber-650 dark:text-amber-400' : 'text-red-600 dark:text-red-400')); ?>"><?php echo e(number_format($conformity, 1)); ?>%</span>
                    </div>
                    <div class="w-full bg-surface-muted rounded-full h-2.5 overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-700 <?php echo e($downloadColor === 'emerald' ? 'bg-emerald-500' : ($downloadColor === 'amber' ? 'bg-amber-400' : 'bg-red-500')); ?>"
                             style="width: <?php echo e(min(100, $conformity)); ?>%">
                        </div>
                    </div>
                    <div class="flex justify-between text-[9px] text-text-muted mt-1">
                        <span>0%</span>
                        <span class="text-amber-500">70% (Min)</span>
                        <span class="text-emerald-500">90% (Ideal)</span>
                        <span>100%</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            
            <?php if($speedtestPhotoUrl = foto_publik($techDetail->speedtest_photo)): ?>
            <div>
                <h4 class="text-xs font-bold text-text-muted uppercase tracking-wider mb-4">Foto Hasil Speedtest</h4>
                <div class="bg-surface-muted dark:bg-transparent border border-border rounded-xl p-4 inline-block">
                    <img src="<?php echo e($speedtestPhotoUrl); ?>"
                         alt="Foto Speedtest"
                         class="max-h-64 max-w-full rounded-lg object-contain border border-border shadow-sm cursor-pointer hover:opacity-90 transition-opacity"
                         onclick="openPhotoLightbox('<?php echo e($speedtestPhotoUrl); ?>', 'Foto Speedtest')">
                </div>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="bg-warning-bg border border-warning-border rounded-xl p-5 flex items-center gap-3">
                <svg class="w-6 h-6 text-warning shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <p class="text-sm text-warning font-medium">Data pengujian (speedtest) belum tersedia untuk pelanggan ini. Pastikan teknisi telah mengisi laporan pemasangan dengan hasil speedtest.</p>
            </div>
            <?php endif; ?>
        </div>

        
        
        
        <div id="tab-verifikasi" class="tab-panel p-6 md:p-8 hidden">

            <?php
                $service = $customer->customerService;
                $isWaitingBdStage = $customer->status === \App\Enums\WorkflowTransition::WAITING_BUSINESS_DEVELOPMENT_VERIFICATION->value;
            ?>

            <?php if($isWaitingBdStage): ?>
                
                <div class="mb-6 bg-success-bg border border-success-border rounded-xl p-4">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-success shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div>
                            <p class="text-sm font-semibold text-success dark:text-white">Langkah Terakhir: Verifikasi BD</p>
                            <p class="text-xs text-success dark:text-white mt-1">CS sudah menerbitkan tagihan awal &amp; menyelesaikan teknis (lihat tab sebelumnya). Isi Biaya Instalasi lalu tekan "Verifikasi &amp; Aktifkan" untuk menerbitkan tagihan Biaya Instalasi sekaligus mengaktifkan pelanggan ini. <span class="font-semibold">Tidak ada jalur tolak</span> — pastikan nominalnya benar sebelum submit.</p>
                        </div>
                    </div>
                </div>

                <?php if($service): ?>
                <div class="mb-6">
                    <h4 class="text-xs font-bold text-text-muted uppercase tracking-wider mb-4">Ringkasan Layanan</h4>
                    <div class="bg-surface-muted dark:bg-transparent border border-border rounded-xl p-5">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-5">
                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Paket Internet</span>
                                <span class="block text-sm font-bold text-text-main"><?php echo e($customer->internetPackage->name ?? '-'); ?></span>
                            </div>
                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Biaya Bulanan</span>
                                <span class="block text-sm font-mono font-bold text-text-main">Rp <?php echo e(number_format($service->total_monthly_bill ?? 0, 0, ',', '.')); ?></span>
                            </div>
                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Diskon</span>
                                <span class="block text-sm font-mono text-text-main">Rp <?php echo e(number_format($service->discount ?? 0, 0, ',', '.')); ?></span>
                            </div>
                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">PPN</span>
                                <span class="block text-sm font-mono text-text-main"><?php echo e(number_format($service->ppn ?? 0, 0, ',', '.')); ?>%</span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                
                <?php if($initialInvoice): ?>
                <div class="mb-6">
                    <div class="flex flex-wrap gap-2 items-center justify-between mb-3">
                        <h4 class="text-xs font-bold text-text-muted uppercase tracking-wider">Hasil Verifikasi CS — <?php echo e($initialInvoice->invoice_number); ?></h4>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wide <?php echo e($initialInvoice->invoice_status->value === 'lunas' ? 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800' : 'bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 border-amber-200 dark:border-amber-800'); ?>">
                            <?php echo e($initialInvoice->invoice_status->label()); ?>

                        </span>
                    </div>
                    <div class="bg-surface border border-border rounded-xl p-5">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-4 mb-4">
                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Tanggal Aktivasi</span>
                                <span class="block text-sm font-bold text-text-main"><?php echo e($initialInvoice->issue_date?->translatedFormat('d F Y') ?? '-'); ?></span>
                            </div>
                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Prorata</span>
                                <span class="block text-sm font-mono text-text-main">Rp <?php echo e(number_format((float) $initialInvoice->prorate_amount, 0, ',', '.')); ?></span>
                            </div>
                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Biaya Pemasangan (CS)</span>
                                <span class="block text-sm font-mono text-text-main">Rp <?php echo e(number_format((float) $initialInvoice->extra_installation_fee, 0, ',', '.')); ?></span>
                            </div>
                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Total Tagihan Awal</span>
                                <span class="block text-sm font-mono font-bold text-primary">Rp <?php echo e(number_format((float) $initialInvoice->total_amount, 0, ',', '.')); ?></span>
                            </div>
                        </div>
                        <a href="<?php echo e(route('invoices.show', $initialInvoice)); ?>" class="text-xs font-semibold text-primary hover:underline">
                            Lihat Detail Tagihan Awal →
                        </a>
                    </div>
                </div>
                <?php elseif($pendingInitialInvoice): ?>
                
                <div class="mb-6">
                    <div class="flex flex-wrap gap-2 items-center justify-between mb-3">
                        <h4 class="text-xs font-bold text-text-muted uppercase tracking-wider">Hasil Verifikasi CS</h4>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wide bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 border-amber-200 dark:border-amber-800">
                            Belum Terbit
                        </span>
                    </div>
                    <div class="bg-surface border border-border rounded-xl p-5">
                        <p class="text-xs text-text-muted mb-4">Tagihan awal baru terbit setelah Anda verifikasi di bawah, digabung SATU invoice dengan Biaya Instalasi yang Anda isi — angka berikut hasil hitung CS saat verifikasi, belum termasuk Biaya Instalasi.</p>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-4">
                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Tanggal Aktivasi</span>
                                <span class="block text-sm font-bold text-text-main"><?php echo e(\Illuminate\Support\Carbon::parse($pendingInitialInvoice['issue_date'])->translatedFormat('d F Y')); ?></span>
                            </div>
                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Prorata</span>
                                <span class="block text-sm font-mono text-text-main">Rp <?php echo e(number_format((float) $pendingInitialInvoice['billing']['prorate_amount'], 0, ',', '.')); ?></span>
                            </div>
                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Biaya Pemasangan (CS)</span>
                                <span class="block text-sm font-mono text-text-main">Rp <?php echo e(number_format((float) $pendingInitialInvoice['billing']['extra_installation_fee'], 0, ',', '.')); ?></span>
                            </div>
                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Subtotal CS (Estimasi, Belum + Biaya Instalasi)</span>
                                <span class="block text-sm font-mono font-bold text-primary">Rp <?php echo e(number_format((float) $pendingInitialInvoice['billing']['total_amount'], 0, ',', '.')); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if($customer->canInstallationFeeBeValidatedBy(auth()->user())): ?>
                    <form method="POST" action="<?php echo e(route('business-development-verifications.verify', $customer)); ?>" class="space-y-6">
                        <?php echo csrf_field(); ?>
                        <?php echo method_field('PUT'); ?>

                        <div>
                            <h4 class="text-xs font-bold text-text-muted uppercase tracking-wider mb-4">Form Verifikasi BD</h4>
                            <div class="bg-surface border border-border rounded-xl p-6 space-y-5 shadow-sm">
                                <div>
                                    <label for="installation_fee" class="block text-xs font-semibold text-text-secondary uppercase tracking-wider mb-2">BIAYA INSTALASI <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-text-disabled text-sm font-medium">Rp</span>
                                        
                                        <input type="text" inputmode="decimal" data-rupiah name="installation_fee" id="installation_fee"
                                            value="<?php echo e(old('installation_fee', \App\Helpers\FormatHelper::rupiahInput($customer->customerService?->internetPackage?->installation_fee ?? 0))); ?>" required autofocus
                                            class="w-full pl-9 text-sm px-3 py-2.5 border border-border rounded-lg bg-surface font-mono text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25">
                                    </div>
                                    <?php $__errorArgs = ['installation_fee'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                        <p class="text-xs text-error mt-1"><?php echo e($message); ?></p>
                                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                    <p class="text-[11px] text-text-muted mt-1">Terisi otomatis dari biaya instalasi bawaan paket — boleh diubah kalau ada nego harga. Submit langsung menerbitkan tagihan Insidental (kategori Jasa Instalasi), terpisah dari Tagihan Awal yang dibuat CS.</p>
                                </div>
                            </div>
                        </div>

                        <div class="pt-5 border-t border-border flex flex-col sm:flex-row justify-between items-center gap-4">
                            <a href="<?php echo e(route('business-development-verifications.index')); ?>"
                                class="text-sm font-medium text-text-secondary hover:text-text-main transition-colors px-4 py-2 border border-border rounded-lg hover:bg-surface-muted">
                                ← Kembali ke Antrean
                            </a>
                            <button type="submit"
                                class="flex justify-center items-center gap-2 px-6 py-2.5 text-sm font-bold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-all shadow-sm hover:shadow-md active:scale-95 cursor-pointer focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Verifikasi & Aktifkan
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="bg-surface-muted border border-border rounded-xl p-5 text-sm text-text-secondary">
                        Anda tidak punya izin memvalidasi Biaya Instalasi kategori paket ini.
                    </div>
                <?php endif; ?>
            <?php else: ?>
            
            <div class="mb-6 bg-success-bg border border-success-border rounded-xl p-4">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-success shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <?php if($customer->needsBusdevInstallationFeeVerification()): ?>
                            <p class="text-sm font-semibold text-success dark:text-white">Langkah Terakhir: Verifikasi CS (Kategori Bisnis — Lanjut ke BD)</p>
                            <p class="text-xs text-success dark:text-white mt-1">Periksa kembali data pemasangan dan pengujian di tab sebelumnya, kemudian isi form di bawah ini untuk menerbitkan tagihan pertama. Pelanggan kategori Bisnis <span class="font-semibold">belum resmi Aktif</span> setelah ini — menunggu Business Development (BD) verifikasi Biaya Instalasi dulu.</p>
                        <?php else: ?>
                            <p class="text-sm font-semibold text-success dark:text-white">Langkah Terakhir: Verifikasi & Aktivasi Pelanggan</p>
                            <p class="text-xs text-success dark:text-white mt-1">Periksa kembali data pemasangan dan pengujian di tab sebelumnya, kemudian isi form di bawah ini untuk mengaktifkan pelanggan dan menerbitkan tagihan pertama.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            
            <?php if($service): ?>
            <div class="mb-6">
                <h4 class="text-xs font-bold text-text-muted uppercase tracking-wider mb-4">Ringkasan Layanan</h4>
                <div class="bg-surface-muted dark:bg-transparent border border-border rounded-xl p-5">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-5">
                        <div>
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Paket Internet</span>
                            <span class="block text-sm font-bold text-text-main"><?php echo e($customer->internetPackage->name ?? '-'); ?></span>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Biaya Bulanan</span>
                            <span class="block text-sm font-mono font-bold text-text-main">Rp <?php echo e(number_format($service->total_monthly_bill ?? 0, 0, ',', '.')); ?></span>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Diskon</span>
                            <span class="block text-sm font-mono text-text-main">Rp <?php echo e(number_format($service->discount ?? 0, 0, ',', '.')); ?></span>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">PPN</span>
                            <span class="block text-sm font-mono text-text-main"><?php echo e(number_format($service->ppn ?? 0, 0, ',', '.')); ?>%</span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            


            <form id="verifyForm" method="POST" action="<?php echo e(route('customers.verification.final', $customer)); ?>" class="space-y-6"
                  data-needs-bd="<?php echo e($customer->needsBusdevInstallationFeeVerification() ? '1' : '0'); ?>">
                <?php echo csrf_field(); ?>

                <div>
                    <h4 class="text-xs font-bold text-text-muted uppercase tracking-wider mb-4">Form Penerbitan Tagihan Pertama</h4>
                    <div class="bg-surface border border-border rounded-xl p-6 space-y-5 shadow-sm">

                        
                        <div class="mb-5">
                            <label for="issue_date" class="block text-xs font-semibold text-text-secondary uppercase tracking-wider mb-2">TANGGAL AKTIVASI <span class="text-red-500">*</span></label>
                            <input type="date" name="issue_date" id="issue_date"
                                class="w-full text-sm px-3 py-2.5 border border-border rounded-lg focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25 bg-surface text-text-main"
                                required value="<?php echo e(old('issue_date', date('Y-m-d'))); ?>" onchange="resetProrateOverride(); calculateFees();">
                            <p class="text-[10px] text-text-muted mt-1">
                                Menentukan tagihan prorata, periode tagihan (<span id="derived_period_info" class="font-semibold text-text-secondary">—</span>),
                                dan jatuh tempo. Tagihan pertama dibayar saat aktivasi, jadi jatuh temponya tanggal ini juga.
                            </p>
                        </div>

                        <div class="border-t border-border pt-5">
                            
                            <div id="billing_params" class="hidden"
                                data-monthly-price="<?php echo e($service->monthly_price ?? 0); ?>"
                                data-discount="<?php echo e($service->discount ?? 0); ?>"
                                data-ppn="<?php echo e($service->ppn ?? 0); ?>"></div>

                            <h5 class="text-xs font-bold text-text-muted uppercase tracking-wider mb-4">Biaya Sekali Bayar</h5>

                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                                <div>
                                    <label for="extra_installation_fee" class="block text-xs font-semibold text-text-secondary uppercase tracking-wider mb-2">BIAYA PEMASANGAN</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-text-disabled text-sm font-medium">Rp</span>
                                        <?php if($customer->needsBusdevInstallationFeeVerification()): ?>
                                            
                                            <input type="text" disabled
                                                class="w-full pl-9 text-sm px-3 py-2.5 border border-border rounded-lg bg-surface-muted font-mono text-text-disabled cursor-not-allowed"
                                                value="0">
                                            <input type="hidden" name="extra_installation_fee" value="0">
                                        <?php else: ?>
                                            
                                            <input type="text" inputmode="decimal" data-rupiah name="extra_installation_fee" id="fv_extra_installation_fee"
                                                class="w-full pl-9 text-sm px-3 py-2.5 border border-border rounded-lg bg-surface font-mono text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25"
                                                value="<?php echo e(old('extra_installation_fee', \App\Helpers\FormatHelper::rupiahInput($customer->internetPackage->installation_fee ?? 0))); ?>" onkeyup="calculateFees()" onchange="calculateFees()">
                                        <?php endif; ?>
                                    </div>
                                    <?php if($customer->needsBusdevInstallationFeeVerification()): ?>
                                        <p class="text-[11px] text-text-muted mt-1">Kategori Bisnis — biaya instalasi diisi &amp; ditagih BD setelah pelanggan ini diverifikasi.</p>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <label for="other_fee" class="block text-xs font-semibold text-text-secondary uppercase tracking-wider mb-2">MATERAI</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-text-disabled text-sm font-medium">Rp</span>
                                        
                                        <input type="text" inputmode="decimal" data-rupiah name="other_fee" id="fv_other_fee"
                                            class="w-full pl-9 text-sm px-3 py-2.5 border border-border rounded-lg bg-surface font-mono text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25"
                                            value="<?php echo e(old('other_fee', 0)); ?>" onkeyup="calculateFees()" onchange="calculateFees()">
                                    </div>
                                </div>
                                <div>
                                    <label for="extra_cable_fee" class="block text-xs font-semibold text-text-secondary uppercase tracking-wider mb-2">KABEL TAMBAHAN</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-text-disabled text-sm font-medium">Rp</span>
                                        <input type="text" inputmode="decimal" data-rupiah name="extra_cable_fee" id="fv_extra_cable_fee"
                                            class="w-full pl-9 text-sm px-3 py-2.5 border border-border rounded-lg bg-surface font-mono text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25"
                                            value="<?php echo e(old('extra_cable_fee', 0)); ?>" onkeyup="calculateFees()" onchange="calculateFees()">
                                    </div>
                                </div>
                                <div>
                                    <label for="extra_pole_fee" class="block text-xs font-semibold text-text-secondary uppercase tracking-wider mb-2">TAMBAHAN TIANG</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-text-disabled text-sm font-medium">Rp</span>
                                        <input type="text" inputmode="decimal" data-rupiah name="extra_pole_fee" id="fv_extra_pole_fee"
                                            class="w-full pl-9 text-sm px-3 py-2.5 border border-border rounded-lg bg-surface font-mono text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25"
                                            value="<?php echo e(old('extra_pole_fee', 0)); ?>" onkeyup="calculateFees()" onchange="calculateFees()">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-5">
                                <label for="fv_prorate_amount" class="block text-xs font-semibold text-text-secondary uppercase tracking-wider mb-2">BIAYA BERLANGGANAN (PRORATA)</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-text-disabled text-sm font-medium">Rp</span>
                                    
                                    <input type="text" inputmode="decimal" data-rupiah name="prorate_amount_override" id="fv_prorate_amount"
                                        class="w-full pl-9 text-sm px-3 py-2.5 border border-border rounded-lg bg-surface font-mono text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25"
                                        value="<?php echo e(old('prorate_amount_override', '')); ?>" oninput="onProrateManualEdit()">
                                </div>
                                <p class="text-[10px] text-text-muted mt-1">Otomatis dari prorata (<span id="prorate_auto_hint">—</span>). Bisa diedit manual bila perlu.</p>
                            </div>

                            
                            <div class="mt-6 rounded-xl border border-border bg-surface-muted dark:bg-transparent p-5">
                                <p id="kwitansi_header" class="text-xs font-semibold text-text-muted mb-4">—</p>

                                <dl class="space-y-2 text-sm">
                                    <div class="flex justify-between gap-4">
                                        <dt id="kwitansi_langganan_label" class="text-text-secondary">Langganan bulan ini</dt>
                                        <dd id="kwitansi_prorata" class="font-mono text-text-main shrink-0">Rp 0</dd>
                                    </div>
                                    <div class="flex justify-between gap-4">
                                        <dt class="text-text-secondary">Biaya pemasangan</dt>
                                        <dd id="kwitansi_pemasangan" class="font-mono text-text-main shrink-0">Rp 0</dd>
                                    </div>
                                    <div class="flex justify-between gap-4">
                                        <dt class="text-text-secondary">Materai</dt>
                                        <dd id="kwitansi_materai" class="font-mono text-text-main shrink-0">Rp 0</dd>
                                    </div>
                                    <div class="flex justify-between gap-4">
                                        <dt class="text-text-secondary">Kabel tambahan</dt>
                                        <dd id="kwitansi_kabel" class="font-mono text-text-main shrink-0">Rp 0</dd>
                                    </div>
                                    <div class="flex justify-between gap-4">
                                        <dt class="text-text-secondary">Tiang tambahan</dt>
                                        <dd id="kwitansi_tiang" class="font-mono text-text-main shrink-0">Rp 0</dd>
                                    </div>

                                    
                                    <?php if((float) ($service->discount ?? 0) > 0): ?>
                                    <div class="flex justify-between gap-4">
                                        <dt class="text-text-secondary">Diskon</dt>
                                        <dd id="kwitansi_diskon" class="font-mono text-success shrink-0">Rp 0</dd>
                                    </div>
                                    <?php endif; ?>

                                    <?php if((float) ($service->ppn ?? 0) > 0): ?>
                                    <div class="flex justify-between gap-4">
                                        <dt class="text-text-secondary">PPN <?php echo e(rtrim(rtrim(number_format($service->ppn, 2, ',', '.'), '0'), ',')); ?>%</dt>
                                        <dd id="kwitansi_ppn" class="font-mono text-text-main shrink-0">Rp 0</dd>
                                    </div>
                                    <?php endif; ?>
                                </dl>

                                <div class="mt-4 pt-4 border-t border-border flex justify-between gap-4 items-baseline">
                                    <span class="text-xs font-bold uppercase tracking-wider text-text-secondary">Tagihan Pertama</span>
                                    <span id="kwitansi_total" class="text-xl font-extrabold font-mono text-primary shrink-0">Rp 0</span>
                                </div>

                                <p class="text-xs text-text-muted mt-2">Dibayar saat aktivasi.</p>
                                <p id="kwitansi_bulan_depan" class="text-xs text-text-muted mt-1">—</p>
                            </div>
                        </div>

                        
                        <div class="pt-5 border-t border-border flex flex-col sm:flex-row justify-between items-center gap-4">
                            <a href="<?php echo e(route('verifications.queue')); ?>"
                                class="text-sm font-medium text-text-secondary hover:text-text-main transition-colors px-4 py-2 border border-border rounded-lg hover:bg-surface-muted">
                                ← Kembali ke Antrean
                            </a>
                            <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                                <button type="button" onclick="openRejectModal()" class="flex justify-center items-center gap-2 px-6 py-2.5 text-sm font-bold text-error bg-surface border border-error-border rounded-lg hover:bg-error-bg/20 transition-all shadow-sm active:scale-95 cursor-pointer focus:outline-none focus:ring-2 focus:ring-error/25">
                                    Tolak
                                </button>
                                <button type="button" onclick="openRevisiModal()" class="flex justify-center items-center gap-2 px-6 py-2.5 text-sm font-bold text-text-main bg-surface border border-border rounded-lg hover:bg-surface-muted transition-all shadow-sm active:scale-95 cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary/25">
                                    Revisi Pemasangan
                                </button>
                                <button type="submit" id="btn-activate"
                                    class="flex justify-center items-center gap-2 px-6 py-2.5 text-sm font-bold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-all shadow-sm hover:shadow-md active:scale-95 cursor-pointer focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    
                                    <?php echo e($customer->needsBusdevInstallationFeeVerification() ? 'Verifikasi & Terbitkan Tagihan' : 'Aktivasi & Terbitkan Tagihan'); ?>

                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>


<div id="photo-lightbox" class="fixed inset-0 z-[200] hidden items-center justify-center bg-black/80 backdrop-blur-sm" onclick="closePhotoLightbox()">
    <div class="relative max-w-3xl max-h-[85vh] mx-4">
        <button type="button" onclick="closePhotoLightbox()" class="absolute -top-10 right-0 text-white text-2xl font-bold hover:text-slate-300 transition-colors focus:outline-none">✕</button>
        <img id="lightbox-img" src="" alt="" class="max-w-full max-h-[80vh] object-contain rounded-lg shadow-2xl border border-white/10">
        <p id="lightbox-caption" class="text-center text-white text-xs font-semibold mt-3 uppercase tracking-wider"></p>
    </div>
</div>


<div id="revisiModal" class="fixed inset-0 z-[100] flex items-center justify-center hidden bg-black/50 backdrop-blur-sm transition-opacity opacity-0 duration-300">
    <div class="bg-surface rounded-xl border border-border shadow-2xl w-full max-w-md overflow-hidden transform scale-95 transition-transform duration-300">
        <div class="flex justify-between items-center px-6 py-4 border-b border-border bg-warning-bg">
            <h3 class="text-lg font-bold text-warning">Revisi Pemasangan</h3>
            <button type="button" onclick="closeRevisiModal()" class="text-text-muted hover:text-text-main transition-colors focus:outline-none rounded-md hover:bg-surface-muted p-1 cursor-pointer">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <form id="revisiForm" method="POST" action="<?php echo e(route('customers.verification.revisi', $customer->id)); ?>">
            <?php echo csrf_field(); ?>
            <div class="p-6">
                <p class="text-sm text-text-secondary mb-4">Pelanggan akan dikembalikan ke antrean pemasangan. Silakan tulis catatan perbaikan untuk teknisi:</p>
                <div class="mb-4">
                    <label for="reason" class="block text-xs font-semibold text-text-secondary uppercase tracking-wider mb-2">Catatan Revisi <span class="text-red-500">*</span></label>
                    <textarea name="reason" id="reason" rows="3" class="w-full text-sm px-3 py-2 border border-border bg-surface text-text-main rounded-lg focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25" required placeholder="Contoh: Kabel perlu dirapikan, redaman terlalu tinggi, dll."></textarea>
                </div>
            </div>
            
            <div class="px-6 py-4 border-t border-border bg-surface-muted dark:bg-transparent flex justify-end gap-3">
                <button type="button" onclick="closeRevisiModal()" class="px-5 py-2 text-sm font-medium text-text-secondary bg-surface border border-border rounded-md hover:bg-surface-muted transition-colors cursor-pointer">Batal</button>
                <button type="submit" class="px-5 py-2 text-sm font-medium text-white bg-warning hover:bg-warning/90 transition-colors shadow-sm cursor-pointer">Kirim ke Teknisi</button>
            </div>
        </form>
    </div>
</div>


<div id="rejectModal" class="fixed inset-0 z-[100] flex items-center justify-center hidden bg-black/50 backdrop-blur-sm transition-opacity opacity-0 duration-300">
    <div class="bg-surface rounded-xl border border-border shadow-2xl w-full max-w-md overflow-hidden transform scale-95 transition-transform duration-300">
        <div class="flex justify-between items-center px-6 py-4 border-b border-border bg-error-bg">
            <h3 class="text-lg font-bold text-error">Tolak Pelanggan</h3>
            <button type="button" onclick="closeRejectModal()" class="text-text-muted hover:text-text-main transition-colors focus:outline-none rounded-md hover:bg-surface-muted p-1 cursor-pointer">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <form id="rejectForm" method="POST" action="<?php echo e(route('customers.verification.reject', $customer->id)); ?>">
            <?php echo csrf_field(); ?>
            <div class="p-6">
                <p class="text-sm text-error bg-error-bg/60 border border-error-border rounded-lg px-3 py-2 mb-4 font-medium">
                    Ini keputusan <span class="font-bold">final</span>. Pelanggan tidak bisa dibuka lagi — masuk daftar Pelanggan Gagal, dan kalau mau lanjut harus registrasi ulang dari awal.
                </p>
                <div class="mb-4">
                    <label for="reject_reason" class="block text-xs font-semibold text-text-secondary uppercase tracking-wider mb-2">Alasan Penolakan <span class="text-red-500">*</span></label>
                    <textarea name="reason" id="reject_reason" rows="3" class="w-full text-sm px-3 py-2 border border-border bg-surface text-text-main rounded-lg focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25" required placeholder="Contoh: Pelanggan tidak memenuhi kriteria / belum melunasi pembayaran."></textarea>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-border bg-surface-muted dark:bg-transparent flex justify-end gap-3">
                <button type="button" onclick="closeRejectModal()" class="px-5 py-2 text-sm font-medium text-text-secondary bg-surface border border-border rounded-md hover:bg-surface-muted transition-colors cursor-pointer">Batal</button>
                <button type="submit" class="px-5 py-2 text-sm font-medium text-white bg-error hover:bg-error/90 transition-colors shadow-sm cursor-pointer">Tolak Final</button>
            </div>
        </form>
    </div>
</div>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
<script>
    // ── TAB SWITCHING ──────────────────────────────────────────────────
    function switchTab(tab) {
        document.querySelectorAll('.tab-panel').forEach(el => el.classList.add('hidden'));
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('border-primary', 'text-primary', 'bg-primary-soft/40');
            btn.classList.add('border-transparent', 'text-text-secondary');
        });

        document.getElementById('tab-' + tab).classList.remove('hidden');

        const activeBtn = document.getElementById('tab-btn-' + tab);
        activeBtn.classList.remove('border-transparent', 'text-text-secondary');
        activeBtn.classList.add('border-primary', 'text-primary', 'bg-primary-soft/40');
    }

    // ── LIGHTBOX ───────────────────────────────────────────────────────
    function openPhotoLightbox(src, caption) {
        const lb = document.getElementById('photo-lightbox');
        document.getElementById('lightbox-img').src = src;
        document.getElementById('lightbox-caption').textContent = caption || '';
        lb.classList.remove('hidden');
        lb.classList.add('flex');
    }

    function closePhotoLightbox() {
        const lb = document.getElementById('photo-lightbox');
        lb.classList.add('hidden');
        lb.classList.remove('flex');
    }

    // Escape key for modals
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closePhotoLightbox();
            if (typeof closeRevisiModal === 'function') closeRevisiModal();
            if (typeof closeRejectModal === 'function') closeRejectModal();
        }
    });

    // ── MODAL REVISI ───────────────────────────────────────────────────
    function openRevisiModal() {
        const modal = document.getElementById('revisiModal');
        modal.classList.remove('hidden');
        // Trigger reflow
        void modal.offsetWidth;
        modal.classList.remove('opacity-0');
        modal.querySelector('div').classList.remove('scale-95');
        modal.querySelector('textarea').focus();
    }

    function closeRevisiModal() {
        const modal = document.getElementById('revisiModal');
        modal.classList.add('opacity-0');
        modal.querySelector('div').classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    // ── MODAL TOLAK ────────────────────────────────────────────────────
    function openRejectModal() {
        const modal = document.getElementById('rejectModal');
        modal.classList.remove('hidden');
        void modal.offsetWidth;
        modal.classList.remove('opacity-0');
        modal.querySelector('div').classList.remove('scale-95');
        modal.querySelector('textarea').focus();
    }

    function closeRejectModal() {
        const modal = document.getElementById('rejectModal');
        modal.classList.add('opacity-0');
        modal.querySelector('div').classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    // ── CALCULATION LOGIC ──────────────────────────────────────────────
    // PREVIEW SAJA. Angka final dihitung ulang server di
    // App\Services\InitialInvoiceService — rumus di sini wajib dijaga identik
    // supaya yang dilihat admin sama dengan yang tersimpan, tapi kalau menyimpang
    // yang menang tetap server.
    const rupiah = (n) => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(n * 100) / 100);
    const namaBulan = (d) => new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' }).format(d);

    // Admin mengedit field prorata manual → tandai dirty supaya calculateFees()
    // berhenti menimpanya, sampai tanggal aktivasi diganti lagi
    // (resetProrateOverride, dipanggil dari onchange issue_date).
    function onProrateManualEdit() {
        document.getElementById('fv_prorate_amount').dataset.dirty = '1';
        calculateFees();
    }

    function resetProrateOverride() {
        const el = document.getElementById('fv_prorate_amount');
        delete el.dataset.dirty;
        el.value = '';
    }

    function calculateFees() {
        // Cabang BD (WAITING_BUSINESS_DEVELOPMENT_VERIFICATION) tidak
        // merender form CS sama sekali — `billing_params`/`verifyForm`
        // gak ada di DOM. Guard di sini (satu titik), bukan di tiap
        // pemanggil (inline onkeyup/onchange DAN initial call di
        // DOMContentLoaded), supaya aman dari null tanpa nyisir semua
        // titik panggil satu-satu.
        const billingParams = document.getElementById('billing_params');
        if (!billingParams) {
            return;
        }

        // Parameter layanan dititipkan di data-* supaya tidak ikut ter-POST.
        const params = billingParams.dataset;
        const baseMonthly = parseFloat(params.monthlyPrice) || 0;
        const discount = parseFloat(params.discount) || 0;
        const ppnRate = parseFloat(params.ppn) || 0;

        // Kolom rupiah bermasking ribuan (data-rupiah): parseFloat('150.000')
        // = 150, dan seluruh pratinjau kwitansi ikut salah. `params.*` di atas
        // TIDAK dimasking — itu data-* dari server, bukan ketikan admin.
        const angkaRupiah = (id) => {
            const el = document.getElementById(id);

            return (el && window.Rupiah ? window.Rupiah.angka(el.value) : parseFloat(el?.value)) || 0;
        };

        const instFee = angkaRupiah('fv_extra_installation_fee');
        const cableFee = angkaRupiah('fv_extra_cable_fee');
        const poleFee = angkaRupiah('fv_extra_pole_fee');
        const otherFee = angkaRupiah('fv_other_fee');

        // Calculate Prorate (auto). Nilai final dipakai kwitansi diambil dari
        // input fv_prorate_amount — auto-filled di sini kecuali admin sudah
        // edit manual (dataset.dirty), lihat onProrateManualEdit().
        const prorateInput = document.getElementById('fv_prorate_amount');
        const issueDateInput = document.getElementById('issue_date').value;
        let autoProrateAmount = 0;
        let daysActive = 0;
        let daysInMonth = 30;

        if (issueDateInput) {
            const issueDateObj = new Date(issueDateInput);
            const year = issueDateObj.getFullYear();
            const month = issueDateObj.getMonth();
            const date = issueDateObj.getDate();
            
            daysInMonth = new Date(year, month + 1, 0).getDate();

            // Hari aktivasi TIDAK ditagih (konvensi legacy); aktivasi di hari
            // terakhir bulan ditagih sebulan penuh. Wajib identik dengan
            // App\Services\InitialInvoiceService::calculate().
            daysActive = daysInMonth - date;
            const isFullMonthEdgeCase = daysActive <= 0;
            if (isFullMonthEdgeCase) {
                daysActive = daysInMonth;
            }

            // Prorate formula = (daysActive / daysInMonth) * baseMonthly
            autoProrateAmount = Math.round((daysActive / daysInMonth) * baseMonthly);

            const bulanAktivasi = namaBulan(issueDateObj);
            const fmtTanggal = (d) => new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }).format(d);
            const tanggalAktivasi = fmtTanggal(issueDateObj);

            // Rentang hari yang benar-benar ditagih: mulai sehari setelah
            // aktivasi (hari aktivasi gratis) sampai akhir bulan. Kasus
            // aktivasi di hari terakhir bulan itu pengecualian — bukan rentang
            // tanggal, tapi sebulan penuh (lihat komentar di atas & docblock
            // InitialInvoiceService::calculate()).
            let periodeTagihanText;
            if (isFullMonthEdgeCase) {
                periodeTagihanText = `1 bulan penuh (aktivasi di hari terakhir bulan, bukan dihitung per hari)`;
            } else {
                const mulaiTagih = new Date(year, month, date + 1);
                const akhirBulan = new Date(year, month, daysInMonth);
                periodeTagihanText = `${daysActive} hari (${fmtTanggal(mulaiTagih)}–${fmtTanggal(akhirBulan)})`;
            }

            document.getElementById('kwitansi_header').textContent =
                `Aktif ${tanggalAktivasi} (hari aktivasi gratis) · ditagih ${periodeTagihanText}`;
            document.getElementById('kwitansi_langganan_label').textContent =
                `Langganan ${bulanAktivasi} — ditagih ${periodeTagihanText}`;

            // Periode & jatuh tempo tidak lagi diinput admin — tampilkan hasil
            // turunannya supaya tetap terlihat sebelum submit.
            const periodInfo = document.getElementById('derived_period_info');
            if (periodInfo) {
                periodInfo.textContent = bulanAktivasi;
            }

            // Bulan berikutnya: harga penuh, tanpa prorata dan tanpa materai.
            // Wajib sama dengan next_month_amount di InitialInvoiceService dan
            // dengan nominal yang nanti diterbitkan GenerateMonthlyInvoicesCommand.
            const bulanDepanObj = new Date(issueDateObj.getFullYear(), issueDateObj.getMonth() + 1, 1);
            const nextAfterDiscount = Math.max(0, baseMonthly - discount);
            const nextMonthAmount = nextAfterDiscount + Math.round(nextAfterDiscount * (ppnRate / 100) * 100) / 100;
            document.getElementById('kwitansi_bulan_depan').textContent =
                `Mulai ${namaBulan(bulanDepanObj)}: ${rupiah(nextMonthAmount)}/bulan, jatuh tempo tanggal 10.`;
        } else {
            autoProrateAmount = baseMonthly;
        }

        document.getElementById('prorate_auto_hint').textContent = rupiah(autoProrateAmount);

        // Auto-fill field prorata kecuali admin sudah edit manual (dirty).
        if (!prorateInput.dataset.dirty) {
            // Ikut format ribuan supaya sama dengan kolom rupiah lain di form.
            prorateInput.value = window.Rupiah
                ? window.Rupiah.format(String(autoProrateAmount))
                : autoProrateAmount;
        }
        const prorateAmount = angkaRupiah('fv_prorate_amount');

        // Subtotal = prorata + biaya sekali bayar (termasuk materai); PPN dihitung
        // dari subtotal setelah diskon (persen, sama seperti render di
        // invoices/show.blade.php).
        const subtotal = prorateAmount + instFee + cableFee + poleFee + otherFee;
        const afterDiscount = Math.max(0, subtotal - discount);
        const ppnAmount = Math.round(afterDiscount * (ppnRate / 100) * 100) / 100;
        const total = afterDiscount + ppnAmount;

        document.getElementById('kwitansi_prorata').textContent = rupiah(prorateAmount);
        document.getElementById('kwitansi_pemasangan').textContent = rupiah(instFee);
        document.getElementById('kwitansi_materai').textContent = rupiah(otherFee);
        document.getElementById('kwitansi_kabel').textContent = rupiah(cableFee);
        document.getElementById('kwitansi_tiang').textContent = rupiah(poleFee);
        document.getElementById('kwitansi_total').textContent = rupiah(total);

        // Baris diskon & PPN hanya dirender kalau nilainya > 0 (lihat Blade).
        const diskonEl = document.getElementById('kwitansi_diskon');
        if (diskonEl) {
            diskonEl.textContent = '− ' + rupiah(Math.min(discount, subtotal));
        }
        const ppnEl = document.getElementById('kwitansi_ppn');
        if (ppnEl) {
            ppnEl.textContent = rupiah(ppnAmount);
        }

        // Dipakai dialog konfirmasi sebelum submit.
        document.getElementById('verifyForm').dataset.totalAmount = total;
    }

    // ── CONFIRM ACTIVATION ─────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        // Initial calculation
        calculateFees();

        const verifyForm = document.getElementById('verifyForm');
        if (verifyForm) {
            verifyForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const total = verifyForm.dataset.totalAmount || 0;
                const totalFormatted = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(total);
                const needsBd = verifyForm.dataset.needsBd === '1';

                const message = needsBd
                    ? `
                    <div class="whitespace-normal space-y-4 text-left">
                        <p class="text-sm text-text-secondary leading-relaxed">
                            Tindakan ini akan memproses verifikasi CS dan mengarahkan data pelanggan ke antrean verifikasi Business Development:
                        </p>

                        <div class="space-y-2.5">
                            <!-- Card 1: Tagihan Pertama -->
                            <div class="p-3.5 bg-surface border border-border rounded-xl flex items-center justify-between gap-3 shadow-xs">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-9 h-9 rounded-lg bg-sky-50 text-sky-600 dark:bg-sky-950/60 dark:text-sky-400 flex items-center justify-center shrink-0 border border-sky-100 dark:border-sky-900/50">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-xs font-semibold text-text-secondary">Tagihan Pertama (Prorata)</div>
                                        <div class="text-[11px] text-text-muted truncate">Diterbitkan tanpa biaya instalasi</div>
                                    </div>
                                </div>
                                <div class="font-mono font-bold text-sm text-text-main bg-surface-muted px-2.5 py-1 rounded-md border border-border shrink-0">
                                    ${totalFormatted}
                                </div>
                            </div>

                            <!-- Card 2: Status Pelanggan & Alur BD -->
                            <div class="p-3.5 bg-amber-50/50 dark:bg-amber-950/20 border border-amber-200/70 dark:border-amber-800/40 rounded-xl space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-md bg-amber-100 text-amber-700 dark:bg-amber-900/60 dark:text-amber-300 flex items-center justify-center shrink-0">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <span class="text-xs font-semibold text-amber-900 dark:text-amber-200">Status Selanjutnya</span>
                                    </div>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200 border border-amber-300 dark:border-amber-700">
                                        Menunggu Verifikasi BD
                                    </span>
                                </div>
                                <p class="text-xs text-amber-900/90 dark:text-amber-300/90 leading-relaxed pl-8">
                                    Pelanggan <span class="font-bold underline decoration-amber-400 underline-offset-2">belum resmi Aktif</span> sampai Tim Business Development memvalidasi & mengisi <span class="font-semibold">Biaya Instalasi</span>.
                                </p>
                            </div>
                        </div>

                        <p class="text-xs font-medium text-text-muted pt-1">
                            Apakah Anda yakin ingin memproses verifikasi dan melanjutkan ke BD?
                        </p>
                    </div>
                `
                    : `
                    <div class="whitespace-normal space-y-4 text-left">
                        <p class="text-sm text-text-secondary leading-relaxed">
                            Tindakan ini akan mengaktifkan layanan pelanggan dengan rincian berikut:
                        </p>

                        <div class="space-y-2.5">
                            <!-- Card 1: Status Pelanggan -->
                            <div class="p-3.5 bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200/70 dark:border-emerald-800/40 rounded-xl flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300 flex items-center justify-center shrink-0 border border-emerald-200 dark:border-emerald-800">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-xs font-semibold text-text-secondary">Status Layanan</div>
                                        <div class="text-[11px] text-text-muted">Pelanggan langsung resmi aktif</div>
                                    </div>
                                </div>
                                <span class="inline-flex items-center px-2.5 py-1 rounded text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-700 shrink-0">
                                    Aktif
                                </span>
                            </div>

                            <!-- Card 2: Tagihan Pertama -->
                            <div class="p-3.5 bg-surface border border-border rounded-xl flex items-center justify-between gap-3 shadow-xs">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-9 h-9 rounded-lg bg-sky-50 text-sky-600 dark:bg-sky-950/60 dark:text-sky-400 flex items-center justify-center shrink-0 border border-sky-100 dark:border-sky-900/50">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-xs font-semibold text-text-secondary">Tagihan Pertama</div>
                                        <div class="text-[11px] text-text-muted truncate">Total tagihan awal terbit</div>
                                    </div>
                                </div>
                                <div class="font-mono font-bold text-sm text-text-main bg-surface-muted px-2.5 py-1 rounded-md border border-border shrink-0">
                                    ${totalFormatted}
                                </div>
                            </div>
                        </div>

                        <p class="text-xs font-medium text-text-muted pt-1">
                            Apakah Anda yakin ingin memproses aktivasi pelanggan ini?
                        </p>
                    </div>
                `;

                window.Dialog.show({
                    title: needsBd ? 'Konfirmasi Verifikasi CS (Lanjut ke BD)' : 'Konfirmasi Aktivasi Pelanggan',
                    contentHtml: message,
                    icon: 'warning',
                    buttons: [
                        { text: 'Batal', type: 'secondary' },
                        { text: needsBd ? 'Lanjutkan' : 'Lanjutkan Aktivasi', type: 'primary', onClick: () => {
                            window.Dialog.close();
                            // Submit programatik melewati listener `submit`
                            // global — kolom biaya bermasking dibersihkan di sini.
                            window.Rupiah?.normalisasiForm(verifyForm);
                            verifyForm.submit();
                        }}
                    ]
                });
            });
        }

        // Auto-switch to verifikasi tab if there are errors
        <?php if($errors->any()): ?>
        switchTab('verifikasi');
        <?php endif; ?>
    });
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/verifications/admin.blade.php ENDPATH**/ ?>