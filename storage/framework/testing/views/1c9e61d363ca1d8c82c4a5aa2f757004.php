<?php $__env->startSection('title', 'Detail Pelanggan - Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', 'Detail Pelanggan'); ?>

<?php $__env->startSection('content'); ?>
<!-- Actions Header -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl font-bold text-text-main tracking-tight">Detail Pelanggan: <?php echo e($customer->full_name); ?></h1>
    </div>
    <div class="flex gap-2">
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('customers.detail.installation.activate')): ?>
            <?php
                $hasWorkflowTask = $customerTasks->whereIn('task_type', [\App\Enums\TaskType::SURVEY->value, \App\Enums\TaskType::PEMASANGAN->value])->isNotEmpty();
                $isProvenLegacyActive = $customer->old_customer_id && $customer->customerService?->request_status === 'ACTIVE';
            ?>
            <?php if($customer->data_completeness_status !== 'siap_billing' && $customer->status !== 'active' && !$hasWorkflowTask && $isProvenLegacyActive): ?>
                <form action="<?php echo e(route('customers.activate', $customer->id)); ?>" method="POST" class="inline" onsubmit="event.preventDefault(); window.confirmAction('Pelanggan ini belum aktif lewat proses verifikasi normal. Aktifkan manual sekarang? CID akan dibuat dan tagihan pertama akan diterbitkan.', this);">
                    <?php echo csrf_field(); ?>
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-green-650 hover:bg-green-700 disabled:bg-surface-muted disabled:cursor-not-allowed disabled:text-text-muted text-white rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none cursor-pointer"
                            title="Khusus pelanggan yang belum otomatis aktif dari alur verifikasi (mis. data migrasi lama). Untuk pelanggan baru, aktivasi sudah otomatis saat verifikasi admin selesai."
                            <?php if(!$completeness['is_ready_billing']): ?> disabled title="Data profil belum lengkap untuk diaktifkan" <?php endif; ?>>
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Aktivasi Manual
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
        <a href="/customers/<?php echo e($customer->id); ?>/edit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-primary hover:bg-primary/90 text-white rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none cursor-pointer">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            Edit Profil
        </a>
        <a href="/customers" class="inline-flex items-center gap-1.5 px-4 py-2 border border-border bg-surface hover:bg-surface-muted text-text-secondary rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none cursor-pointer">
            Kembali
        </a>
    </div>
</div>

<?php
    $completeness = $customer->dataCompleteness();
    // missing_required and missing_optional are [key => label] maps returned by CustomerValidationService
    $missingRequiredLabels = array_values($completeness['missing_required']);
    $missingOptionalLabels = array_values($completeness['missing_optional']);
?>

<!-- Data Completeness Alert / Progress Bar -->
<div class="bg-surface border border-border rounded-lg p-5 mb-6 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div class="flex-1">
        <div class="flex items-center gap-2">
            <h3 class="text-xs font-bold text-text-main uppercase tracking-wider">Kelengkapan Data Profil Pelanggan</h3>
            <?php if(count($completeness['missing_required']) > 0): ?>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-error-bg text-error border border-error-border uppercase tracking-wider">Kurang Data Wajib</span>
            <?php elseif(count($completeness['missing_optional']) > 0): ?>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-warning-bg text-warning border border-warning-border uppercase tracking-wider">Kurang Data Opsional</span>
            <?php else: ?>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-success-bg text-success border border-success-border uppercase tracking-wider">100% Lengkap</span>
            <?php endif; ?>
        </div>
        <p class="text-xs text-text-muted mt-1 leading-relaxed">
            <?php if(count($completeness['missing_required']) > 0): ?>
                <strong class="text-error">Kekurangan Data Wajib:</strong> <?php echo e(implode(', ', $missingRequiredLabels)); ?>. Mohon segera lakukan pengeditan profil.
            <?php elseif(count($completeness['missing_optional']) > 0): ?>
                <strong class="text-warning">Kekurangan Data Opsional:</strong> <?php echo e(implode(', ', $missingOptionalLabels)); ?>.
            <?php else: ?>
                Semua data administrasi dan teknis telah terisi lengkap.
            <?php endif; ?>
        </p>
    </div>
    <div class="w-full md:w-64 shrink-0">
        <div class="flex items-center justify-between mb-1.5 text-xs font-semibold">
            <span class="text-text-muted">Persentase Terisi</span>
            <span class="text-primary data-text"><?php echo e($completeness['percentage']); ?>%</span>
        </div>
        <div class="w-full bg-surface-muted rounded-full h-2.5 overflow-hidden border border-border">
            <div class="h-full rounded-full transition-all duration-300 <?php echo e(count($completeness['missing_required']) > 0 ? 'bg-error' : (count($completeness['missing_optional']) > 0 ? 'bg-warning' : 'bg-success')); ?>" style="width: <?php echo e($completeness['percentage']); ?>%"></div>
        </div>
    </div>
</div>

<!-- Main Grid -->
<div class="grid grid-cols-1 lg:grid-cols-4 gap-8 items-start">
    
    <!-- LEFT COLUMN: Profile Card & Timeline Progress -->
    <div class="lg:col-span-1 flex flex-col gap-6">
        <!-- Profile summary card -->
        <div class="bg-surface border border-border rounded-lg p-6 shadow-sm">
            <div class="flex flex-col items-center text-center">
                <!-- Avatar / Initials -->
                <div class="h-16 w-16 bg-primary-soft border border-primary-border rounded-full flex items-center justify-center font-bold text-xl text-primary mb-4 shadow-inner">
                    <?php echo e(strtoupper(substr($customer->full_name, 0, 2))); ?>

                </div>
                <h3 class="font-bold text-text-main text-base leading-tight"><?php echo e($customer->full_name); ?></h3>
                <span class="text-[9px] font-bold text-text-muted uppercase tracking-wider mt-1"><?php echo e($displayIdLabel ?? 'ID'); ?></span>
                <span class="text-xs font-mono text-text-secondary mt-0.5 data-text"><?php echo e($displayId); ?></span>
                
                <!-- Status Badge -->
                <div class="mt-3">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-semibold border <?php echo e($customer->subscriptionStatus?->badgeClasses() ?? 'bg-surface-muted text-text-secondary border-border'); ?>">
                        <?php echo e($customer->subscriptionStatus->name ?? Str::headline($customer->status)); ?>

                    </span>
                </div>
            </div>

            <!-- Contacts list -->
            <div class="mt-6 space-y-3 pt-6 border-t border-border text-xs text-text-secondary">
                <div class="flex items-center gap-3">
                    <svg class="h-4 w-4 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                    </svg>
                    <span class="font-mono data-text"><?php echo e($customer->primary_phone); ?></span>
                </div>
                <div class="flex items-center gap-3">
                    <svg class="h-4 w-4 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <span class="truncate"><?php echo e($customer->email); ?></span>
                </div>
            </div>

        </div>

        <!-- Vertical Workflow Timeline Card -->
        <div class="bg-surface border border-border rounded-lg p-6 shadow-sm">
            <h4 class="text-xs font-bold text-text-muted uppercase tracking-wider mb-4">TIMELINE PROSES</h4>
            
            <div class="flow-root">
                <ul class="-mb-8">
                    <?php $__currentLoopData = $timeline; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $step): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li>
                        <div class="relative pb-8">
                            <!-- Line connecting dots -->
                            <?php if(!$loop->last): ?>
                            <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-border" aria-hidden="true"></span>
                            <?php endif; ?>
                            
                            <div class="relative flex space-x-3">
                                <div>
                                    <!-- Indicator Dot -->
                                    <?php if($step['status'] === 'completed'): ?>
                                        <span class="h-8 w-8 rounded-full bg-success flex items-center justify-center ring-4 ring-surface text-white">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </span>
                                    <?php elseif($step['status'] === 'current'): ?>
                                        <span class="h-8 w-8 rounded-full bg-primary flex items-center justify-center ring-4 ring-surface text-white animate-pulse">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </span>
                                    <?php elseif($step['status'] === 'warning'): ?>
                                        <span class="h-8 w-8 rounded-full bg-warning flex items-center justify-center ring-4 ring-surface text-white">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                            </svg>
                                        </span>
                                    <?php elseif($step['status'] === 'danger'): ?>
                                        <span class="h-8 w-8 rounded-full bg-error-bg flex items-center justify-center ring-4 ring-surface text-error border border-error-border">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </span>
                                    <?php else: ?>
                                        <span class="h-8 w-8 rounded-full bg-surface-muted flex items-center justify-center ring-4 ring-surface text-text-muted border border-border">
                                            <span class="text-xs font-bold font-mono"><?php echo e($loop->iteration); ?></span>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1 min-w-0 pt-1.5">
                                    <p class="text-xs font-semibold text-text-main"><?php echo e($step['title']); ?></p>
                                    <p class="text-[10px] text-text-muted mt-0.5 font-mono data-text"><?php echo e($step['date']); ?></p>
                                    <p class="text-[10px] text-text-secondary mt-1 leading-relaxed"><?php echo e($step['notes']); ?></p>
                                </div>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN: 10-Tab Area -->
    <div class="lg:col-span-3 flex flex-col">
        <!-- Tabs Buttons Nav -->
        <div class="border-b border-border bg-surface rounded-t-lg overflow-x-auto flex shadow-sm scrollbar-none">
            <button onclick="switchTab('ringkasan')" id="tab-btn-ringkasan" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-primary text-primary focus:outline-none cursor-pointer whitespace-nowrap">Ringkasan</button>
            <?php if(auth()->user()->hasPermission('customers.detail.identity.view')): ?>
            <button onclick="switchTab('identitas')" id="tab-btn-identitas" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-text-muted hover:text-text-main focus:outline-none cursor-pointer whitespace-nowrap">Identitas</button>
            <?php endif; ?>
            <?php if(auth()->user()->hasPermission('customers.detail.address.view')): ?>
            <button onclick="switchTab('alamat')" id="tab-btn-alamat" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-text-muted hover:text-text-main focus:outline-none cursor-pointer whitespace-nowrap">Alamat</button>
            <?php endif; ?>
            <button onclick="switchTab('pop')" id="tab-btn-pop" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-text-muted hover:text-text-main focus:outline-none cursor-pointer whitespace-nowrap">POP/Cabang</button>
            <?php if(auth()->user()->hasPermission('customers.detail.survey.view')): ?>
            <button onclick="switchTab('survey')" id="tab-btn-survey" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-text-muted hover:text-text-main focus:outline-none cursor-pointer whitespace-nowrap">Survey</button>
            <?php endif; ?>
            <?php if(auth()->user()->hasPermission('customers.detail.installation.view')): ?>
            <button onclick="switchTab('pemasangan')" id="tab-btn-pemasangan" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-text-muted hover:text-text-main focus:outline-none cursor-pointer whitespace-nowrap">Pemasangan</button>
            <?php endif; ?>
            <?php if(auth()->user()->hasPermission('customers.detail.devices.view')): ?>
            <button onclick="switchTab('perangkat')" id="tab-btn-perangkat" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-text-muted hover:text-text-main focus:outline-none cursor-pointer whitespace-nowrap">Perangkat</button>
            <?php endif; ?>
            <?php if(auth()->user()->hasPermission('customers.detail.packages.view')): ?>
            <button onclick="switchTab('paket-layanan')" id="tab-btn-paket-layanan" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-text-muted hover:text-text-main focus:outline-none cursor-pointer whitespace-nowrap">Paket & Layanan</button>
            <?php endif; ?>
            <button onclick="switchTab('billing')" id="tab-btn-billing" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-text-muted hover:text-text-main focus:outline-none cursor-pointer whitespace-nowrap">Billing</button>
            <button onclick="switchTab('tagihan')" id="tab-btn-tagihan" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-text-muted hover:text-text-main focus:outline-none cursor-pointer whitespace-nowrap">Tagihan</button>
            <button onclick="switchTab('pembayaran')" id="tab-btn-pembayaran" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-text-muted hover:text-text-main focus:outline-none cursor-pointer whitespace-nowrap">Pembayaran</button>
            <?php if(auth()->user()->hasPermission('customers.detail.documents.view')): ?>
            <button onclick="switchTab('dokumen')" id="tab-btn-dokumen" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-text-muted hover:text-text-main focus:outline-none cursor-pointer whitespace-nowrap">Dokumen</button>
            <?php endif; ?>
            <button onclick="switchTab('riwayat-ticketing')" id="tab-btn-riwayat-ticketing" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-text-muted hover:text-text-main focus:outline-none cursor-pointer whitespace-nowrap">Riwayat Ticketing</button>
            <button onclick="switchTab('riwayat-perubahan')" id="tab-btn-riwayat-perubahan" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-text-muted hover:text-text-main focus:outline-none cursor-pointer whitespace-nowrap">Riwayat Perubahan</button>
            <?php if($customer->customerTechnicalDetail): ?>
            <button onclick="switchTab('teknis-lama')" id="tab-btn-teknis-lama" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-text-muted hover:text-text-main focus:outline-none cursor-pointer whitespace-nowrap">Detail Teknis Lama</button>
            <?php endif; ?>
        </div>

        <!-- Tabs Content Body -->
        <div class="bg-surface border-x border-b border-border rounded-b-lg p-6 min-h-[450px] shadow-sm">
            
            <!-- Tab 1: Ringkasan -->
            <div id="tab-content-ringkasan" class="tab-content space-y-6">
                <!-- Completeness Overview -->
                <div class="border border-border rounded-lg p-5 bg-surface-muted/30">
                    <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-2">STATUS KELENGKAPAN DATA</span>
                    <div class="flex items-center gap-3">
                        <?php
                            $completenessColor = match($customer->data_completeness_status) {
                                'siap_billing' => 'bg-success-bg text-success border-success-border',
                                'lengkap' => 'bg-success-bg text-success border-success-border',
                                'perlu_dilengkapi' => 'bg-warning-bg text-warning border-warning-border',
                                'draft' => 'bg-surface-muted text-text-secondary border-border',
                                default => 'bg-surface-muted text-text-secondary border-border',
                            };
                            $completenessLabel = match($customer->data_completeness_status) {
                                'siap_billing' => 'Siap Billing',
                                'lengkap' => 'Lengkap',
                                'perlu_dilengkapi' => 'Perlu Dilengkapi',
                                'draft' => 'Draft',
                                default => ucwords(str_replace('_', ' ', $customer->data_completeness_status)),
                            };
                        ?>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border <?php echo e($completenessColor); ?>">
                            <?php echo e($completenessLabel); ?>

                        </span>
                        <span class="text-xs text-text-muted">
                            Profil data terisi <strong class="text-primary"><?php echo e($completeness['percentage']); ?>%</strong> dari total 28 parameter evaluasi.
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Active Subscription card -->
                    <div class="border border-border rounded-lg p-4 bg-surface-muted/30">
                        <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-2">LANGGANAN AKTIF</span>
                        <?php if($customer->internetPackage): ?>
                            <h4 class="text-sm font-semibold text-text-main"><?php echo e($customer->internetPackage->package_code); ?> - <?php echo e($customer->internetPackage->name); ?></h4>
                            <p class="text-xs text-text-muted mt-1">Kategori: <?php echo e($customer->internetPackage->category); ?></p>
                            <div class="flex items-baseline mt-3">
                                <span class="text-lg font-bold text-text-main data-text">Rp <?php echo e(number_format($customer->customerService?->total_monthly_bill ?? (($customer->internetPackage->monthly_price - ($customer->discount_amount ?? 0)) * (1 + ($customer->tax_percent ?? 0) / 100)), 0, ',', '.')); ?></span>
                                <span class="text-[10px] text-text-muted ml-1">/bulan (Nett)</span>
                            </div>
                        <?php else: ?>
                            <p class="text-text-muted text-xs py-2">Belum memilih paket layanan</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Address Card -->
                    <div class="border border-border rounded-lg p-4 bg-surface-muted/30">
                        <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-2">ALAMAT INSTALASI</span>
                        <p class="text-xs text-text-secondary font-semibold leading-relaxed"><?php echo e($customer->address ?? 'Belum diisi'); ?></p>
                        <p class="text-[10px] text-text-muted mt-1.5">
                            Kel. <?php echo e($customer->village->name ?? ($customer->customerAddress->village ?? '-')); ?>, 
                            Kec. <?php echo e($customer->district->name ?? ($customer->customerAddress->district ?? '-')); ?>, 
                            <?php echo e($customer->city->name ?? ($customer->customerAddress->city ?? '-')); ?>

                        </p>
                        <div class="mt-2.5 flex items-center gap-1.5 font-mono text-[9px] text-text-muted data-text">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            </svg>
                            <span>Lat/Long: <?php echo e($customer->latitude ?? ($customer->customerAddress->latitude ?? '-')); ?>, <?php echo e($customer->longitude ?? ($customer->customerAddress->longitude ?? '-')); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Technical summary card -->
                <div class="border border-border rounded-lg p-5">
                    <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-3">RINGKASAN TEKNIS JARINGAN</span>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                        <div>
                            <span class="block text-[9px] font-bold text-text-muted uppercase">Nomer CID / REQ ID</span>
                            <?php if(auth()->user()->hasPermission('customers.detail.installation.validate')): ?>
                            <button type="button" x-data @click="$dispatch('open-modal', 'network-assignment')"
                                    class="font-mono font-medium text-primary hover:underline data-text cursor-pointer text-left font-sans"
                                    title="Klik untuk atur Mini POP & Distribusi">
                                <?php echo e($displayId ?? $customer->cid ?? 'Belum dialokasikan'); ?>

                            </button>
                            <?php else: ?>
                            <span class="font-mono font-medium text-text-main data-text"><?php echo e($displayId ?? $customer->cid ?? 'Belum dialokasikan'); ?></span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="block text-[9px] font-bold text-text-muted uppercase">POP Cabang</span>
                            <span class="font-medium text-text-main"><?php echo e($customer->pop->name ?? '-'); ?></span>
                        </div>
                        <div>
                            <span class="block text-[9px] font-bold text-text-muted uppercase">Mini POP (OLT)</span>
                            <span class="font-medium text-text-main"><?php echo e($customer->miniPop->name ?? 'Belum di-assign'); ?></span>
                        </div>
                        <div>
                            <span class="block text-[9px] font-bold text-text-muted uppercase">Distribusi</span>
                            <span class="font-medium text-text-main"><?php echo e($customer->distribution->code ?? 'Belum di-assign'); ?></span>
                        </div>
                        <div>
                            <span class="block text-[9px] font-bold text-text-muted uppercase">IP Address</span>
                            <span class="font-mono font-medium text-text-main data-text"><?php echo e($customer->ip_address ?? 'Belum dialokasikan'); ?></span>
                        </div>
                        <div>
                            <span class="block text-[9px] font-bold text-text-muted uppercase">ONT Serial Number</span>
                            <span class="font-mono font-medium text-text-main data-text"><?php echo e($customer->ont_sn ?? 'Belum terpasang'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Missing Fields alert -->
                <?php if(count($completeness['missing_required']) > 0 || count($completeness['missing_optional']) > 0): ?>
                <div class="border border-warning-border rounded-lg p-5 bg-warning-bg/40">
                    <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-2">PARAMETER YANG BELUM LENGKAP</span>
                    <div class="space-y-3 text-xs">
                        <?php if(count($completeness['missing_required']) > 0): ?>
                        <div>
                            <span class="font-semibold text-error block mb-1">Field Wajib (Mencegah Layanan Aktif/Billing):</span>
                            <div class="flex flex-wrap gap-1.5">
                                <?php $__currentLoopData = $missingRequiredLabels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <span class="px-2.5 py-0.5 bg-error-bg text-error border border-error-border rounded text-[10px] font-mono"><?php echo e($label); ?></span>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if(count($completeness['missing_optional']) > 0): ?>
                        <div>
                            <span class="font-semibold text-warning block mb-1">Field Opsional/Teknis:</span>
                            <div class="flex flex-wrap gap-1.5">
                                <?php $__currentLoopData = $missingOptionalLabels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <span class="px-2.5 py-0.5 bg-warning-bg text-warning border border-warning-border rounded text-[10px] font-mono"><?php echo e($label); ?></span>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Tab 2: Identitas -->
            <?php if(auth()->user()->hasPermission('customers.detail.identity.view')): ?>
            <div id="tab-content-identitas" class="tab-content hidden space-y-6">
                <div class="border border-border rounded-lg overflow-hidden">
                    <div class="px-5 py-3.5 bg-surface-muted/50 border-b border-border">
                        <span class="text-xs font-semibold text-text-secondary">Formulir Identitas Pelanggan</span>
                    </div>
                    <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-xs">
                        <div class="py-1.5 border-b border-border/50 flex justify-between">
                            <span class="text-text-muted">Nama Lengkap</span>
                            <span class="font-semibold text-text-main"><?php echo e($customer->full_name); ?></span>
                        </div>
                        <div class="py-1.5 border-b border-border/50 flex justify-between">
                            <span class="text-text-muted">Nomor Identitas (NIK)</span>
                            <span class="font-mono font-medium text-text-main data-text"><?php echo e($customer->identity_number ?? 'Belum diisi'); ?></span>
                        </div>
                        <div class="py-1.5 border-b border-border/50 flex justify-between">
                            <span class="text-text-muted">Jenis Kelamin</span>
                            <span class="font-semibold text-text-main"><?php echo e($customer->gender?->label() ?? 'Belum diisi'); ?></span>
                        </div>
                        <div class="py-1.5 border-b border-border/50 flex justify-between">
                            <span class="text-text-muted">Nomor HP Utama</span>
                            <span class="font-mono font-medium text-text-main data-text"><?php echo e($customer->primary_phone ?? 'Belum diisi'); ?></span>
                        </div>
                        <div class="py-1.5 border-b border-border/50 flex justify-between">
                            <span class="text-text-muted">Nomor HP Alternatif</span>
                            <span class="font-mono font-medium text-text-main data-text"><?php echo e($customer->alternative_phone ?? 'Belum diisi'); ?></span>
                        </div>
                        <div class="py-1.5 border-b border-border/50 flex justify-between">
                            <span class="text-text-muted">Alamat Email</span>
                            <span class="font-semibold text-text-main"><?php echo e($customer->email ?? 'Belum diisi'); ?></span>
                        </div>
                        <div class="py-1.5 border-b border-border/50 flex justify-between md:col-span-2">
                            <span class="text-text-muted">Tanggal Registrasi</span>
                            <span class="font-mono font-medium text-text-main data-text"><?php echo e(\App\Support\IndonesianDate::date($customer->registration_date)); ?></span>
                        </div>
                    </div>
                </div>

                <div class="border border-border rounded-lg overflow-hidden">
                    <div class="px-5 py-3.5 bg-surface-muted/50 border-b border-border">
                        <span class="text-xs font-semibold text-text-secondary">Informasi Referral & Sales</span>
                    </div>
                    <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-6 text-xs">
                        <div class="border border-border rounded-lg p-4 bg-surface-muted/30">
                            <span class="block text-[9px] font-bold text-text-muted uppercase">ID SALES</span>
                            <span class="font-mono font-medium text-text-main mt-1 block data-text"><?php echo e($customer->sales_code ?? '-'); ?></span>
                        </div>
                        <div class="border border-border rounded-lg p-4 bg-surface-muted/30">
                            <span class="block text-[9px] font-bold text-text-muted uppercase">KODE AGENT</span>
                            <span class="font-mono font-medium text-text-main mt-1 block data-text"><?php echo e($customer->agent_code ?? '-'); ?></span>
                        </div>
                        <div class="border border-border rounded-lg p-4 bg-surface-muted/30">
                            <span class="block text-[9px] font-bold text-text-muted uppercase">REFERRAL PELANGGAN</span>
                            <span class="font-mono font-medium text-text-main mt-1 block data-text"><?php echo e($customer->referral_customer_code ?? '-'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Tab 3: Alamat -->
            <?php if(auth()->user()->hasPermission('customers.detail.address.view')): ?>
            <div id="tab-content-alamat" class="tab-content hidden space-y-6">
                <div class="border border-border rounded-lg overflow-hidden">
                    <div class="px-5 py-3.5 bg-surface-muted/50 border-b border-border">
                        <span class="text-xs font-semibold text-text-secondary">Alamat Instalasi Detail</span>
                    </div>
                    <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-xs">
                        <div class="py-1.5 border-b border-border/50 flex justify-between md:col-span-2">
                            <span class="text-text-muted">Alamat Lengkap</span>
                            <span class="font-semibold text-text-main text-right"><?php echo e($customer->address ?? 'Belum diisi'); ?></span>
                        </div>
                        <div class="py-1.5 border-b border-border/50 flex justify-between">
                            <span class="text-text-muted">Desa / Kelurahan</span>
                            <span class="font-semibold text-text-main"><?php echo e($customer->village->name ?? ($customer->customerAddress->village ?? 'Belum diisi')); ?></span>
                        </div>
                        <div class="py-1.5 border-b border-border/50 flex justify-between">
                            <span class="text-text-muted">Kecamatan</span>
                            <span class="font-semibold text-text-main"><?php echo e($customer->district->name ?? ($customer->customerAddress->district ?? 'Belum diisi')); ?></span>
                        </div>
                        <div class="py-1.5 border-b border-border/50 flex justify-between">
                            <span class="text-text-muted">Kota / Kabupaten</span>
                            <span class="font-semibold text-text-main"><?php echo e($customer->city->name ?? ($customer->customerAddress->city ?? 'Belum diisi')); ?></span>
                        </div>
                        <div class="py-1.5 border-b border-border/50 flex justify-between">
                            <span class="text-text-muted">Provinsi</span>
                            <span class="font-semibold text-text-main"><?php echo e($customer->customerAddress->province ?? 'Jawa Timur'); ?></span>
                        </div>
                        <div class="py-1.5 border-b border-border/50 flex justify-between">
                            <span class="text-text-muted">Garis Lintang (Latitude)</span>
                            <span class="font-mono font-medium text-text-main data-text"><?php echo e($customer->latitude ?? ($customer->customerAddress->latitude ?? 'Belum diisi')); ?></span>
                        </div>
                        <div class="py-1.5 border-b border-border/50 flex justify-between">
                            <span class="text-text-muted">Garis Bujur (Longitude)</span>
                            <span class="font-mono font-medium text-text-main data-text"><?php echo e($customer->longitude ?? ($customer->customerAddress->longitude ?? 'Belum diisi')); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Tab 4: POP/Cabang -->
            <div id="tab-content-pop" class="tab-content hidden space-y-6">
                <div class="border border-border rounded-lg overflow-hidden">
                    <div class="px-5 py-3.5 bg-surface-muted/50 border-b border-border">
                        <span class="text-xs font-semibold text-text-secondary">Data POP / Cabang Terkoneksi</span>
                    </div>
                    <?php if($customer->pop): ?>
                    <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-xs">
                        <div class="py-1.5 border-b border-border/50 flex justify-between">
                            <span class="text-text-muted">Nama POP / Cabang</span>
                            <span class="font-semibold text-text-main"><?php echo e($customer->pop->name); ?></span>
                        </div>
                        <div class="py-1.5 border-b border-border/50 flex justify-between">
                            <span class="text-text-muted">Kode Cabang</span>
                            <span class="font-mono font-semibold text-text-main data-text"><?php echo e($customer->pop->code); ?></span>
                        </div>
                        <div class="py-1.5 border-b border-border/50 flex justify-between">
                            <span class="text-text-muted">Tipe Cabang</span>
                            <span class="font-semibold text-text-main uppercase text-[10px] tracking-wider"><?php echo e($customer->pop->type); ?></span>
                        </div>
                        <div class="py-1.5 border-b border-border/50 flex justify-between">
                            <span class="text-text-muted">Status POP</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-success-bg text-success border border-success-border uppercase">
                                <?php echo e($customer->pop->status ? 'Aktif' : 'Nonaktif'); ?>

                            </span>
                        </div>
                        <div class="py-1.5 border-b border-border/50 flex justify-between md:col-span-2">
                            <span class="text-text-muted">Alamat Kantor POP</span>
                            <span class="font-semibold text-text-main text-right">
                                <?php echo e($customer->pop->address); ?>, Kel. <?php echo e($customer->pop->village); ?>, Kec. <?php echo e($customer->pop->district); ?>, <?php echo e($customer->pop->city); ?>

                            </span>
                        </div>
                        <div class="py-1.5 border-b border-border/50 flex justify-between">
                            <span class="text-text-muted">Penanggung Jawab (PIC)</span>
                            <span class="font-semibold text-text-main"><?php echo e($customer->pop->pic_name ?? 'Belum ditentukan'); ?></span>
                        </div>
                        <div class="py-1.5 border-b border-border/50 flex justify-between">
                            <span class="text-text-muted">Nomor HP PIC</span>
                            <span class="font-mono font-medium text-text-main data-text"><?php echo e($customer->pop->pic_phone ?? 'Belum ditentukan'); ?></span>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="p-8 text-center text-text-muted text-xs">
                        <svg class="mx-auto h-8 w-8 text-border mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        Belum ada POP/Cabang yang di-assign untuk pelanggan ini.
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab: Survey -->
            <?php if(auth()->user()->hasPermission('customers.detail.survey.view')): ?>
            <div id="tab-content-survey" class="tab-content hidden space-y-6">
                <?php echo $__env->make('customers.tabs._survey', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </div>
            <?php endif; ?>

            <!-- Tab: Pemasangan -->
            <?php if(auth()->user()->hasPermission('customers.detail.installation.view')): ?>
            <div id="tab-content-pemasangan" class="tab-content hidden space-y-6">
                <?php echo $__env->make('customers.tabs._installation', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </div>
            <?php endif; ?>

            <?php if(auth()->user()->hasPermission('customers.detail.devices.view')): ?>
            <div id="tab-content-perangkat" class="tab-content hidden space-y-6">
                <?php echo $__env->make('customers.tabs._device', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </div>
            <?php endif; ?>

            <!-- Tab 5: Paket & Layanan -->
            <?php if(auth()->user()->hasPermission('customers.detail.packages.view')): ?>
            <div id="tab-content-paket-layanan" class="tab-content hidden space-y-6">
                <div class="border border-border rounded-lg overflow-hidden">
                    <div class="px-5 py-3.5 bg-surface-muted/50 border-b border-border">
                        <span class="text-xs font-semibold text-text-secondary">Paket Internet & Status Layanan Jaringan</span>
                    </div>
                    <div class="p-5 text-xs space-y-5">
                        <?php if($customer->internetPackage): ?>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="border border-border rounded-lg p-4 bg-surface-muted/30">
                                <span class="block text-[9px] font-bold text-text-muted uppercase">KODE LAYANAN</span>
                                <span class="font-mono font-semibold text-text-main mt-1 block data-text"><?php echo e($customer->internetPackage->package_code); ?> - <?php echo e($customer->internetPackage->name); ?></span>
                            </div>
                            <div class="border border-border rounded-lg p-4 bg-surface-muted/30">
                                <span class="block text-[9px] font-bold text-text-muted uppercase">KATEGORI LAYANAN</span>
                                <span class="font-semibold text-text-main mt-1 block"><?php echo e($customer->internetPackage->category); ?></span>
                            </div>
                            <div class="border border-border rounded-lg p-4 bg-surface-muted/30">
                                <span class="block text-[9px] font-bold text-text-muted uppercase">KECEPATAN (BANDWIDTH)</span>
                                <span class="font-mono font-semibold text-primary mt-1 block data-text">
                                    <?php echo e($customer->internetPackage->download_speed_mbps); ?> Mbps Down / <?php echo e($customer->internetPackage->upload_speed_mbps); ?> Mbps Up
                                </span>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="p-4 text-center text-text-muted">
                            Belum ada paket internet yang terpilih.
                        </div>
                        <?php endif; ?>

                        <div class="border border-border rounded-lg p-4 bg-surface-muted/30">
                            <span class="block text-[9px] font-bold text-text-muted uppercase tracking-wider mb-2">INTEGRASI TEKNIS</span>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3 font-mono text-[11px] data-text">
                                <div class="flex justify-between py-1 border-b border-border/50">
                                    <span class="text-text-muted">ONT Serial Number</span>
                                    <span class="text-text-main font-medium"><?php echo e($customer->ont_sn ?? 'Belum terpasang'); ?></span>
                                </div>
                                <div class="flex justify-between py-1 border-b border-border/50">
                                    <span class="text-text-muted">IP Address Dialed</span>
                                    <span class="text-text-main font-medium"><?php echo e($customer->ip_address ?? 'Belum teralokasi'); ?></span>
                                </div>
                                <div class="flex justify-between py-1 border-b border-border/50">
                                    <span class="text-text-muted">Nama Perangkat OLT</span>
                                    <span class="text-text-main font-medium"><?php echo e($customer->olt_code ?? 'Belum diisi'); ?></span>
                                </div>
                                
                                <div class="flex justify-between py-1 border-b border-border/50">
                                    <span class="text-text-muted">
                                        Nomor OLT
                                        <span class="text-[9px] text-primary font-bold ml-1" title="Nilai ini digunakan saat generate CID pelanggan">[CID]</span>
                                    </span>
                                    <?php $oltNumber = $customer->customerTechnicalDetail?->olt_number; ?>
                                    <?php if($oltNumber): ?>
                                        <span class="text-primary font-bold"><?php echo e($oltNumber); ?></span>
                                    <?php else: ?>
                                        <span class="text-warning font-bold italic">Belum diisi teknisi</span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex justify-between py-1 border-b border-border/50">
                                    <span class="text-text-muted">Kode Kotak ODP</span>
                                    <span class="text-text-main font-medium"><?php echo e($customer->odp_code ?? 'Belum terhubung'); ?></span>
                                </div>
                                <div class="flex justify-between py-1 border-b border-border/50">
                                    <span class="text-text-muted">VLAN ID Jaringan</span>
                                    <span class="text-text-main font-medium"><?php echo e($customer->vlan_id ?? 'Belum ditentukan'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Tab 6: Billing -->
            <div id="tab-content-billing" class="tab-content hidden space-y-6">
                <div class="border border-border rounded-lg overflow-hidden">
                    <div class="px-5 py-3.5 bg-surface-muted/50 border-b border-border">
                        <span class="text-xs font-semibold text-text-secondary">Rincian Biaya Bulanan & Billing Cycle</span>
                    </div>
                    <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-8 text-xs">
                        <?php
                            $monthlyPrice  = (float)($customer->customerService?->monthly_price ?? ($customer->internetPackage?->monthly_price ?? 0));
                            $discount      = (float)($customer->customerService?->discount ?? ($customer->discount_amount ?? 0));
                            $otherFee      = (float)($customer->customerService?->other_fee ?? 0);
                            // Gunakan nilai ppn dari customer_services secara langsung (bisa 0 atau nilai tertentu)
                            // Jangan fallback ke 11 karena 0 berarti memang tanpa PPN
                            $ppnPercent    = $customer->customerService ? (float)$customer->customerService->ppn : (float)($customer->tax_percent ?? 0);

                            $discountedPrice = max(0, $monthlyPrice - $discount);
                            $ppnAmount       = round($discountedPrice * ($ppnPercent / 100), 2);
                            // Gunakan total dari service snapshot jika tersedia, hitung ulang hanya sebagai fallback
                            $totalBill = $customer->customerService
                                ? (float)$customer->customerService->total_monthly_bill
                                : ($discountedPrice + $ppnAmount + $otherFee);
                        ?>
                        <div>
                            <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-4">BREAKDOWN BIAYA NETT</span>
                            <div class="space-y-3 font-mono text-[11px] data-text">
                                <div class="flex justify-between text-text-muted">
                                    <span>Harga Paket Internet</span>
                                    <span>Rp <?php echo e(number_format($monthlyPrice, 2, ',', '.')); ?></span>
                                </div>
                                <div class="flex justify-between text-success">
                                    <span>Potongan Diskon</span>
                                    <span>- Rp <?php echo e(number_format($discount, 2, ',', '.')); ?></span>
                                </div>
                                <div class="flex justify-between text-text-muted">
                                    <span>PPN (<?php echo e(number_format($ppnPercent, 0)); ?>%)</span>
                                    <span>Rp <?php echo e(number_format($ppnAmount, 2, ',', '.')); ?></span>
                                </div>
                                <?php if($otherFee > 0): ?>
                                <div class="flex justify-between text-text-muted">
                                    <span>Biaya Lain di luar Standar</span>
                                    <span>Rp <?php echo e(number_format($otherFee, 2, ',', '.')); ?></span>
                                </div>
                                <?php endif; ?>
                                <hr class="border-dashed border-border">
                                <div class="flex justify-between text-xs font-bold text-text-main">
                                    <span>Total Biaya Per Bulan</span>
                                    <span>Rp <?php echo e(number_format($totalBill, 2, ',', '.')); ?></span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-4">MASA AKTIF & PERIODE</span>
                            <div class="space-y-3 text-xs">
                                <div class="flex justify-between py-1.5 border-b border-border/50">
                                    <span class="text-text-muted">Tanggal Aktivasi Layanan</span>
                                    <span class="font-mono font-semibold text-text-main data-text">
                                        <?php echo e($customer->customerService?->activation_date ? \App\Support\IndonesianDate::date($customer->customerService->activation_date) : 'Belum diaktivasi'); ?>

                                    </span>
                                </div>
                                <?php if($customer->customerService?->activation_time): ?>
                                <div class="flex justify-between py-1.5 border-b border-border/50">
                                    <span class="text-text-muted">Waktu Aktivasi</span>
                                    <span class="font-mono font-semibold text-text-main data-text">
                                        <?php echo e(substr($customer->customerService->activation_time, 0, 5)); ?> WIB
                                    </span>
                                </div>
                                <?php endif; ?>
                                <?php if($customer->customerService?->activated_by_name || $customer->customerService?->activatedBy): ?>
                                <div class="flex justify-between py-1.5 border-b border-border/50">
                                    <span class="text-text-muted">Petugas Aktivasi</span>
                                    <span class="font-semibold text-text-main">
                                        <?php echo e($customer->customerService->activatedBy->name ?? $customer->customerService->activated_by_name ?? '-'); ?>

                                    </span>
                                </div>
                                <?php endif; ?>
                                <div class="flex justify-between py-1.5 border-b border-border/50">
                                    <span class="text-text-muted">Tanggal Jatuh Tempo Bulanan</span>
                                    <span class="font-mono font-semibold text-text-main data-text">
                                        <?php echo e($customer->customerService?->due_date ? \App\Support\IndonesianDate::date($customer->customerService->due_date) : 'Belum ditentukan'); ?>

                                    </span>
                                </div>
                                <div class="flex justify-between py-1.5 border-b border-border/50">
                                    <span class="text-text-muted">Siklus Billing</span>
                                    <span class="font-semibold text-text-main capitalize">
                                        <?php echo e($customer->customerService?->billing_cycle ?? 'Monthly'); ?>

                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 7: Tagihan -->
            <div id="tab-content-tagihan" class="tab-content hidden space-y-6">
                <div class="flex items-center justify-between pb-4 border-b border-border">
                    <div>
                        <h3 class="text-sm font-bold text-text-main uppercase tracking-wider">Riwayat Tagihan Pelanggan</h3>
                        <p class="text-xs text-text-muted mt-0.5">Daftar invoice tagihan bulanan yang diterbitkan secara manual maupun sistem.</p>
                    </div>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('invoices.create')): ?>
                        <?php if((in_array($customer->status, ['active', 'suspended']) || $customer->data_completeness_status === 'siap_billing') && $customer->customerService): ?>
                            <button onclick="openInvoiceModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary hover:bg-primary/90 text-white rounded-md transition-colors text-xs font-semibold shadow-sm cursor-pointer focus:outline-none">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Buat Tagihan Manual
                            </button>
                        <?php else: ?>
                            <button disabled class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-surface-muted border border-border text-text-disabled rounded-md text-xs font-semibold cursor-not-allowed focus:outline-none" title="Pelanggan harus berstatus Active atau Siap Billing dengan layanan aktif">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                </svg>
                                Buat Tagihan Manual
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
                        ['title' => 'Tagihan Awal / Registrasi', 'rows' => $invoicesAwal, 'badge' => 'bg-warning-bg text-warning border-warning-border'],
                        ['title' => 'Tagihan Bulanan', 'rows' => $invoicesBulanan, 'badge' => 'bg-primary-soft text-primary border-primary-border'],
                    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="<?php echo e(!$loop->first ? 'mt-6' : ''); ?>">
                            <h4 class="text-[11px] font-bold text-text-muted uppercase tracking-wider mb-2"><?php echo e($group['title']); ?> (<?php echo e($group['rows']->count()); ?>)</h4>
                            <?php if($group['rows']->count() > 0): ?>
                                <div class="overflow-x-auto border border-border rounded-lg shadow-sm">
                                    <table class="w-full text-left border-collapse text-xs">
                                        <thead>
                                            <tr class="bg-surface-muted/50 border-b border-border font-semibold text-text-secondary uppercase tracking-wider text-[10px]">
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
                                        <tbody class="divide-y divide-border text-text-secondary">
                                            <?php $__currentLoopData = $group['rows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <tr class="hover:bg-surface-muted/30 transition-colors" id="invoice-row-<?php echo e($invoice->id); ?>">
                                                    <td class="px-4 py-3 font-mono font-bold text-text-main">
                                                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('invoices.view')): ?>
                                                            <a href="<?php echo e(route('invoices.show', $invoice->id)); ?>" class="text-primary hover:underline"><?php echo e($invoice->invoice_number); ?></a>
                                                        <?php else: ?>
                                                            <?php echo e($invoice->invoice_number); ?>

                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wide <?php echo e($group['badge']); ?>">
                                                            <?php echo e($invoice->invoice_type?->label() ?? ucfirst((string) $invoice->invoice_type)); ?>

                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3 font-mono"><?php echo e($invoice->billing_period); ?></td>
                                                    <td class="px-4 py-3"><?php echo e(\App\Support\IndonesianDate::date($invoice->issue_date)); ?></td>
                                                    <td class="px-4 py-3"><?php echo e(\App\Support\IndonesianDate::date($invoice->due_date)); ?></td>
                                                    <td class="px-4 py-3 text-right font-mono font-semibold">Rp <?php echo e(number_format($invoice->total_amount, 2, ',', '.')); ?></td>
                                                    <td class="px-4 py-3 text-center">
                                                        <?php
                                                            $statusClass = match($invoice->invoice_status->value) {
                                                                'lunas' => 'bg-success-bg text-success border-success-border',
                                                                'sebagian' => 'bg-info-bg text-info border-info-border',
                                                                'belum_dibayar' => 'bg-warning-bg text-warning border-warning-border',
                                                                'batal' => 'bg-error-bg text-error border-error-border',
                                                                default => 'bg-surface-muted text-text-secondary border-border',
                                                            };
                                                            $statusLabel = $invoice->invoice_status->label();
                                                        ?>
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wide <?php echo e($statusClass); ?>" id="invoice-status-badge-<?php echo e($invoice->id); ?>" data-badge-style="tag">
                                                            <?php echo e($statusLabel); ?>

                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3 text-text-muted"><?php echo e($invoice->creator->name ?? 'System'); ?></td>
                                                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('payments.create')): ?>
                                                        <td class="px-4 py-3 text-center">
                                                            <?php if(!in_array($invoice->invoice_status->value, ['lunas', 'batal'], true)): ?>
                                                                <button type="button"
                                                                        onclick="openQuickPaymentModal(<?php echo e($invoice->id); ?>, '<?php echo e($invoice->invoice_number); ?>', <?php echo e((float) $invoice->remaining_amount); ?>)"
                                                                        class="px-2.5 py-1 bg-success hover:bg-success/90 text-white rounded text-[10px] font-bold uppercase tracking-wide shadow-sm cursor-pointer">
                                                                    Bayar
                                                                </button>
                                                            <?php else: ?>
                                                                <span class="text-text-disabled text-[10px]">—</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="py-6 text-center text-text-muted bg-surface-muted/20 border border-dashed border-border rounded-lg text-xs">
                                    Belum ada <?php echo e(strtolower($group['title'])); ?>.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php else: ?>
                    <div class="py-12 text-center text-text-muted bg-surface-muted/20 border border-dashed border-border rounded-lg">
                        <svg class="mx-auto h-12 w-12 text-border mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <h4 class="text-sm font-semibold text-text-main">Belum ada tagihan terbit</h4>
                        <p class="text-xs text-text-muted mt-1">Gunakan tombol "Buat Tagihan Manual" untuk membuat invoice pertama pelanggan.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Modal Pembuatan Tagihan Manual -->
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('invoices.create')): ?>
                <?php if((in_array($customer->status, ['active', 'suspended']) || $customer->data_completeness_status === 'siap_billing') && $customer->customerService): ?>
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
                    <div id="manual-invoice-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity z-50 flex items-center justify-center p-4 hidden">
                        <div class="bg-surface rounded-lg shadow-xl border border-border w-full max-w-md overflow-hidden transform transition-all">
                            <div class="px-5 py-4 border-b border-border flex items-center justify-between">
                                <h3 class="text-xs font-bold text-text-main uppercase tracking-wider">Buat Tagihan Manual</h3>
                                <button onclick="closeInvoiceModal()" class="text-text-muted hover:text-text-main focus:outline-none cursor-pointer">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            <form action="<?php echo e(route('customers.invoices.manual', $customer->id)); ?>" method="POST">
                                <?php echo csrf_field(); ?>
                                <div class="p-6 space-y-4">
                                    <!-- Snapshot Biaya Info -->
                                    <?php
                                        $svc = $customer->customerService;
                                        $svcMonthly = (float)$svc->monthly_price;
                                        $svcDiscount = (float)($svc->discount ?? 0);
                                        $svcPpnRate = (float)($svc->ppn ?? 0);
                                        $svcAfterDiscount = max(0, $svcMonthly - $svcDiscount);
                                        $svcPpnAmount = round($svcAfterDiscount * ($svcPpnRate / 100), 2);
                                        $svcTotal = $svcAfterDiscount + $svcPpnAmount;
                                    ?>
                                    <div class="p-4 bg-surface-muted/50 border border-border rounded-lg text-xs">
                                        <span class="block text-[9px] font-bold text-text-muted uppercase tracking-wider mb-2">Rincian Paket & Layanan</span>
                                        <div class="space-y-1.5 font-medium text-text-secondary">
                                            <div class="flex justify-between">
                                                <span class="text-text-muted">Paket Internet:</span>
                                                <span class="font-semibold text-text-main"><?php echo e($svc->package_name_snapshot); ?></span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-text-muted">Harga Bulanan:</span>
                                                <span class="font-mono text-text-main" id="modal-monthly-price">Rp <?php echo e(number_format($svcMonthly, 0, ',', '.')); ?></span>
                                            </div>
                                            <?php if($svcDiscount > 0): ?>
                                                <div class="flex justify-between text-success">
                                                    <span>Potongan Diskon:</span>
                                                    <span class="font-mono">- Rp <?php echo e(number_format($svcDiscount, 0, ',', '.')); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if($svcPpnRate > 0): ?>
                                                <div class="flex justify-between">
                                                    <span class="text-text-muted">PPN (<?php echo e(number_format($svcPpnRate, 0)); ?>%):</span>
                                                    <span class="font-mono text-text-main">Rp <?php echo e(number_format($svcPpnAmount, 0, ',', '.')); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <hr class="border-dashed border-border my-1.5">
                                            <div class="flex justify-between font-bold text-text-main">
                                                <span>Total Tagihan Nett:</span>
                                                <span class="font-mono text-primary">Rp <?php echo e(number_format($svcTotal, 0, ',', '.')); ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="billing_period" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Periode Tagihan (Bulan/Tahun)</label>
                                        <input type="month" name="billing_period" id="billing_period" value="<?php echo e($defaultPeriod); ?>" required
                                               class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs font-mono text-text-main">
                                    </div>

                                    <div>
                                        <label for="issue_date" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Tanggal Terbit</label>
                                        <input type="date" name="issue_date" id="issue_date" value="<?php echo e($defaultIssueDate); ?>" required
                                               class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs font-mono text-text-main">
                                    </div>

                                    <div>
                                        <label for="due_date" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Tanggal Jatuh Tempo</label>
                                        <input type="date" name="due_date" id="due_date" value="<?php echo e($defaultDueDate); ?>" required
                                               class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs font-mono text-text-main">
                                    </div>

                                    <div>
                                        <label for="invoice_type" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">
                                            Jenis Tagihan
                                            <span class="text-text-muted font-normal normal-case">— pilih manual, jangan andalkan tebakan sistem</span>
                                        </label>
                                        <select name="invoice_type" id="invoice_type" required
                                                class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs font-semibold text-text-main">
                                            <option value="bulanan" <?php echo e($customer->invoices->count() > 0 ? 'selected' : ''); ?>>Tagihan Bulanan Rutin</option>
                                            <option value="awal" <?php echo e($customer->invoices->count() === 0 ? 'selected' : ''); ?>>Tagihan Awal (PSB)</option>
                                            <option value="reaktivasi">Tagihan Reaktivasi</option>
                                        </select>
                                    </div>

                                    <!-- Biaya Tambahan Di Luar Standar -->
                                    <div class="border-t border-border pt-4">
                                        <div class="flex items-center justify-between mb-3">
                                            <span class="block text-[9px] font-bold text-text-muted uppercase tracking-wider">Biaya Tambahan (Opsional)</span>
                                            <?php if($customer->customerService?->activation_date): ?>
                                                <button type="button" onclick="autoCalculateProrate()" class="text-[9px] font-bold text-primary hover:underline uppercase tracking-wider cursor-pointer font-sans">
                                                    Hitung Otomatis Prorate
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <div class="space-y-3">
                                            <div>
                                                <label for="prorate_amount" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">
                                                    Tagihan Prorate
                                                    <span class="text-text-muted font-normal normal-case">— biaya hari aktivasi s/d akhir bulan</span>
                                                </label>
                                                <input type="number" name="prorate_amount" id="prorate_amount" value="<?php echo e(old('prorate_amount', 0)); ?>" min="0" step="1"
                                                       class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs font-mono text-text-main"
                                                       oninput="recalcInvoiceTotal()">
                                            </div>
                                            <div>
                                                <label for="extra_installation_fee" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Biaya Pemasangan / Jasa Instalasi</label>
                                                <?php
                                                    $defaultInstallFee = 0;
                                                    if ($customer->invoices->count() === 0 && $customer->internetPackage) {
                                                        $defaultInstallFee = (float)$customer->internetPackage->installation_fee;
                                                    }
                                                ?>
                                                <input type="number" name="extra_installation_fee" id="extra_installation_fee" value="<?php echo e(old('extra_installation_fee', $defaultInstallFee)); ?>" min="0" step="1"
                                                       class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs font-mono text-text-main"
                                                       oninput="recalcInvoiceTotal()">
                                                <?php if($defaultInstallFee > 0): ?>
                                                    <p class="text-[9px] text-text-muted mt-1 italic">Default dari Master Paket: Rp <?php echo e(number_format($defaultInstallFee, 0, ',', '.')); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <label for="extra_cable_fee" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Biaya Kabel Tambahan</label>
                                                <input type="number" name="extra_cable_fee" id="extra_cable_fee" value="<?php echo e(old('extra_cable_fee', 0)); ?>" min="0" step="1"
                                                       class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs font-mono text-text-main"
                                                       oninput="recalcInvoiceTotal()">
                                            </div>
                                            <div>
                                                <label for="extra_pole_fee" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Tambahan Tiang</label>
                                                <input type="number" name="extra_pole_fee" id="extra_pole_fee" value="<?php echo e(old('extra_pole_fee', 0)); ?>" min="0" step="1"
                                                       class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs font-mono text-text-main"
                                                       oninput="recalcInvoiceTotal()">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Preview Total -->
                                    <div class="bg-surface-muted/40 border border-border rounded-lg p-4 text-xs">
                                        <span class="block text-[9px] font-bold text-text-muted uppercase tracking-wider mb-2">Preview Total Tagihan</span>
                                        <div class="space-y-1.5">
                                            <div class="flex justify-between text-text-muted">
                                                <span>Tagihan Bulanan Nett:</span>
                                                <span class="font-mono" id="preview-nett">Rp <?php echo e(number_format($svcTotal, 0, ',', '.')); ?></span>
                                            </div>
                                            <div class="flex justify-between text-text-muted" id="preview-prorate-row" style="display:none!important">
                                                <span>+ Prorate:</span>
                                                <span class="font-mono" id="preview-prorate">Rp 0</span>
                                            </div>
                                            <div class="flex justify-between text-text-muted" id="preview-cable-row" style="display:none!important">
                                                <span>+ Kabel:</span>
                                                <span class="font-mono" id="preview-cable">Rp 0</span>
                                            </div>
                                            <div class="flex justify-between text-text-muted" id="preview-install-row" style="display:none!important">
                                                <span>+ Instalasi:</span>
                                                <span class="font-mono" id="preview-install">Rp 0</span>
                                            </div>
                                            <div class="flex justify-between text-text-muted" id="preview-pole-row" style="display:none!important">
                                                <span>+ Tiang:</span>
                                                <span class="font-mono" id="preview-pole">Rp 0</span>
                                            </div>
                                            <hr class="border-dashed border-border">
                                            <div class="flex justify-between font-bold text-text-main text-sm">
                                                <span>Total Bayar:</span>
                                                <span class="font-mono text-primary" id="preview-total">Rp <?php echo e(number_format($svcTotal, 0, ',', '.')); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-5 py-3.5 bg-surface-muted/50 border-t border-border flex justify-end gap-2 text-xs">
                                    <button type="button" onclick="closeInvoiceModal()" class="px-3 py-1.5 border border-border text-text-secondary bg-surface hover:bg-surface-muted font-semibold rounded-md shadow-sm transition-colors cursor-pointer">
                                        Batal
                                    </button>
                                    <button type="submit" class="px-3 py-1.5 bg-primary hover:bg-primary/90 text-white font-semibold rounded-md shadow-sm transition-colors cursor-pointer">
                                        Proses Tagihan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php echo $__env->make('payments.partials.quick-payment-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

            <!-- Tab 8: Pembayaran -->
            <div id="tab-content-pembayaran" class="tab-content hidden space-y-6">
                <div class="flex items-center justify-between pb-4 border-b border-border">
                    <div>
                        <h3 class="text-sm font-bold text-text-main uppercase tracking-wider">Riwayat Pembayaran Pelanggan</h3>
                        <p class="text-xs text-text-muted mt-0.5">Pembayaran yang terhubung ke invoice pelanggan ini.</p>
                    </div>
                </div>

                <?php if($customer->payments && $customer->payments->count() > 0): ?>
                    <div class="overflow-x-auto border border-border rounded-lg shadow-sm">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-surface-muted/50 border-b border-border font-semibold text-text-secondary uppercase tracking-wider text-[10px]">
                                    <th class="px-4 py-3">No. Pembayaran</th>
                                    <th class="px-4 py-3">No. Tagihan</th>
                                    <th class="px-4 py-3">Tanggal</th>
                                    <th class="px-4 py-3">Metode</th>
                                    <th class="px-4 py-3 text-right">Nominal</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Bukti</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border text-text-secondary">
                                <?php $__currentLoopData = $customer->payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <tr class="hover:bg-surface-muted/30 transition-colors">
                                        <td class="px-4 py-3 font-mono font-bold text-text-main"><?php echo e($payment->payment_number); ?></td>
                                        <td class="px-4 py-3 font-mono">
                                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('invoices.view')): ?>
                                                <a href="<?php echo e(route('invoices.show', $payment->invoice_id)); ?>" class="text-primary hover:underline"><?php echo e($payment->invoice->invoice_number ?? '-'); ?></a>
                                            <?php else: ?>
                                                <?php echo e($payment->invoice->invoice_number ?? '-'); ?>

                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3"><?php echo e(\App\Support\IndonesianDate::date($payment->payment_date)); ?></td>
                                        <td class="px-4 py-3"><?php echo e(strtoupper($payment->payment_method)); ?></td>
                                        <td class="px-4 py-3 text-right font-mono font-semibold text-text-main">Rp <?php echo e(number_format((float) $payment->amount, 2, ',', '.')); ?></td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wide bg-success-bg text-success border-success-border">
                                                <?php echo e($payment->payment_status->label()); ?>

                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <?php if($payment->proof_file): ?>
                                                <a href="<?php echo e(asset('storage/' . $payment->proof_file)); ?>" target="_blank" class="text-primary hover:underline font-semibold">Lihat bukti</a>
                                            <?php else: ?>
                                                <span class="text-text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="py-12 text-center text-text-muted bg-surface-muted/20 border border-dashed border-border rounded-lg">
                        <svg class="mx-auto h-12 w-12 text-border mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                        <h4 class="text-sm font-semibold text-text-main">Belum ada riwayat pembayaran</h4>
                        <p class="text-xs text-text-muted mt-1">Pembayaran akan tampil setelah finance mencatat pembayaran invoice.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tab 9: Dokumen -->
            <div id="tab-content-dokumen" class="tab-content hidden space-y-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">LAMPIRAN DOKUMEN PENDUKUNG</span>
                        <p class="text-xs text-text-muted">Dokumen disimpan private dan hanya bisa dibuka oleh user dengan permission dokumen pelanggan.</p>
                    </div>
                </div>

                <?php if(auth()->user()->hasPermission('customers.detail.documents.view')): ?>
                    <?php if(auth()->user()->hasPermission('upload_customer_documents')): ?>
                        <form method="POST" action="<?php echo e(route('customers.documents.store', ['customer' => $customer->id])); ?>" enctype="multipart/form-data" class="border border-border rounded-lg p-4 bg-surface-muted/30">
                            <?php echo csrf_field(); ?>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                                <div>
                                    <label for="document_type" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Jenis Dokumen</label>
                                    <select name="document_type" id="document_type" class="w-full text-sm px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25" required>
                                        <?php $__currentLoopData = \App\Enums\DocumentType::cases(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($type->value); ?>"><?php echo e($type->label()); ?></option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="document_file" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">File</label>
                                    <input type="file" name="document_file" id="document_file" accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf" capture="environment" class="w-full text-sm px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25" required>
                                    <p class="text-[10px] text-text-muted mt-1">Format: JPG, PNG, WEBP, PDF. Maksimal 4 MB.</p>
                                </div>
                                <div>
                                    <button type="submit" class="w-full px-4 py-2 bg-primary hover:bg-primary/90 text-white text-xs font-bold rounded-md uppercase tracking-wide transition-colors">
                                        Upload Dokumen
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>

                    <?php if($customer->documents->isNotEmpty()): ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php $__currentLoopData = $customer->documents->sortByDesc('created_at'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $document): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <div class="border border-border rounded-lg overflow-hidden flex flex-col justify-between hover:border-primary transition-colors shadow-sm bg-surface">
                                    <div class="p-4 bg-surface-muted/30 flex items-center justify-center h-36 border-b border-border">
                                        <?php if($document->isImage()): ?>
                                            <img src="<?php echo e(route('customers.documents.show', $document->id)); ?>" alt="<?php echo e($document->typeLabel()); ?>" class="max-h-28 max-w-full rounded object-contain shadow-sm">
                                        <?php else: ?>
                                            <div class="h-28 w-28 bg-error-bg border border-error-border rounded-lg flex flex-col items-center justify-center text-error shadow-sm">
                                                <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                <span class="text-[10px] font-bold mt-2">DOKUMEN PDF</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="p-3 bg-surface flex items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <span class="block text-xs font-semibold text-text-main truncate"><?php echo e($document->typeLabel()); ?></span>
                                            <span class="block text-[9px] text-text-muted mt-0.5 font-mono"><?php echo e($document->created_at?->format('d/m/Y H:i')); ?></span>
                                            <span class="block text-[9px] text-text-muted mt-0.5 truncate">Upload: <?php echo e($document->uploader?->name ?? '-'); ?></span>
                                        </div>
                                        <a href="<?php echo e(route('customers.documents.show', $document->id)); ?>" target="_blank" class="shrink-0 p-1.5 text-primary hover:bg-surface-muted rounded cursor-pointer transition-colors" title="Buka Dokumen">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12H9m12 0A9 9 0 113 12a9 9 0 0118 0z" />
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                    <?php else: ?>
                        <div class="py-12 text-center text-text-muted bg-surface-muted/20 border border-dashed border-border rounded-lg">
                            <svg class="mx-auto h-12 w-12 text-border mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <h4 class="text-sm font-semibold text-text-main">Belum ada dokumen pelanggan</h4>
                            <p class="text-xs text-text-muted mt-1">Dokumen KTP, rumah, kontrak, survey, dan pemasangan akan tampil setelah diupload.</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="py-12 text-center text-text-muted bg-surface-muted/20 border border-dashed border-border rounded-lg">
                        <h4 class="text-sm font-semibold text-text-main">Akses dokumen dibatasi</h4>
                        <p class="text-xs text-text-muted mt-1">User Anda tidak memiliki permission untuk melihat dokumen pelanggan.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tab: Riwayat Ticketing -->
            <div id="tab-content-riwayat-ticketing" class="tab-content hidden space-y-6">
                <?php echo $__env->make('customers.tabs._riwayat_ticketing', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </div>

            <!-- Tab: Riwayat Perubahan -->
            <div id="tab-content-riwayat-perubahan" class="tab-content hidden space-y-6">
                <?php echo $__env->make('customers.tabs._riwayat_perubahan', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </div>

            <?php if($customer->customerTechnicalDetail): ?>
            <!-- Tab: Detail Teknis Lama -->
            <div id="tab-content-teknis-lama" class="tab-content hidden space-y-6">
                <div class="border border-border rounded-lg overflow-hidden bg-surface shadow-sm">
                    <div class="px-5 py-3.5 bg-surface-muted/50 border-b border-border">
                        <span class="text-xs font-bold text-text-secondary uppercase tracking-wider">Detail Teknis Jaringan Lama (Hasil Migrasi)</span>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 text-xs">
                        <div class="py-2 border-b border-border/50 flex justify-between">
                            <span class="text-text-muted">ID Report Lama</span>
                            <span class="font-mono font-bold text-text-main"><?php echo e($customer->customerTechnicalDetail->old_report_id); ?></span>
                        </div>
                        <div class="py-2 border-b border-border/50 flex justify-between">
                            <span class="text-text-muted">ID Request/Layanan Lama</span>
                            <span class="font-mono font-semibold text-text-main"><?php echo e($customer->customerTechnicalDetail->old_request_id ?? '-'); ?></span>
                        </div>
                        <div class="py-2 border-b border-border/50 flex justify-between">
                            <span class="text-text-muted">Tipe Koneksi</span>
                            <span class="font-semibold text-text-main"><?php echo e($customer->customerTechnicalDetail->connection_type ?? '-'); ?></span>
                        </div>
                        <div class="py-2 border-b border-border/50 flex justify-between">
                            <span class="text-text-muted">ONT Serial Number</span>
                            <span class="font-mono font-semibold text-text-main"><?php echo e($customer->customerTechnicalDetail->router_or_ont_serial ?? '-'); ?></span>
                        </div>
                        <div class="py-2 border-b border-border/50 flex justify-between">
                            <span class="text-text-muted">IP Address</span>
                            <span class="font-mono font-semibold text-text-main"><?php echo e($customer->customerTechnicalDetail->ip_address ?? '-'); ?></span>
                        </div>
                        <div class="py-2 border-b border-border/50 flex justify-between">
                            <span class="text-text-muted">SSID WiFi</span>
                            <span class="font-semibold text-text-main"><?php echo e($customer->customerTechnicalDetail->ssid ?? '-'); ?></span>
                        </div>
                        <div class="py-2 border-b border-border/50 flex justify-between">
                            <span class="text-text-muted">Antenna MAC</span>
                            <span class="font-mono text-text-main"><?php echo e($customer->customerTechnicalDetail->antenna_mac ?? '-'); ?></span>
                        </div>
                        <div class="py-2 border-b border-border/50 flex justify-between">
                            <span class="text-text-muted">Router MAC</span>
                            <span class="font-mono text-text-main"><?php echo e($customer->customerTechnicalDetail->router_mac ?? '-'); ?></span>
                        </div>
                        <div class="py-2 border-b border-border/50 flex justify-between">
                            <span class="text-text-muted">Nomor ODP / Port</span>
                            <span class="font-semibold text-text-main"><?php echo e($customer->customerTechnicalDetail->odp_number ?? '-'); ?> / <?php echo e($customer->customerTechnicalDetail->odp_port ?? '-'); ?></span>
                        </div>
                        <div class="py-2 border-b border-border/50 flex justify-between">
                            <span class="text-text-muted">Port OLT</span>
                            <span class="font-semibold text-text-main"><?php echo e($customer->customerTechnicalDetail->olt_port ?? '-'); ?></span>
                        </div>
                        <div class="py-2 border-b border-border/50 flex justify-between">
                            <span class="text-text-muted">Signal Wireless / Kabel</span>
                            <span class="font-semibold text-text-main"><?php echo e($customer->customerTechnicalDetail->wireless_signal ?? '-'); ?> / <?php echo e($customer->customerTechnicalDetail->fiber_signal ?? '-'); ?></span>
                        </div>
                        <div class="py-2 border-b border-border/50 flex justify-between">
                            <span class="text-text-muted">Lokasi Pemancar / Source</span>
                            <span class="font-semibold text-text-main"><?php echo e($customer->customerTechnicalDetail->location_source ?? '-'); ?></span>
                        </div>
                        <div class="py-2 border-b border-border/50 flex justify-between">
                            <span class="text-text-muted">Hasil Test Speed</span>
                            <span class="font-mono font-semibold text-primary"><?php echo e($customer->customerTechnicalDetail->test_download ?? '-'); ?> Mbps Down / <?php echo e($customer->customerTechnicalDetail->test_upload ?? '-'); ?> Mbps Up</span>
                        </div>
                        <div class="py-2 border-b border-border/50 flex justify-between md:col-span-2">
                            <span class="text-text-muted">Catatan Teknis Pemasangan</span>
                            <span class="text-text-main font-semibold"><?php echo e($customer->customerTechnicalDetail->note ?? '-'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
<script>
    function switchTab(tabId) {
        // Hide all tab panels
        document.querySelectorAll('.tab-content').forEach(el => {
            el.classList.add('hidden');
        });
        
        // Show active tab panel
        document.getElementById('tab-content-' + tabId).classList.remove('hidden');

        // Reset active state for buttons
        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.classList.remove('border-primary', 'text-primary');
            btn.classList.add('border-transparent', 'text-text-muted');
        });

        // Add active state to clicked button
        const activeBtn = document.getElementById('tab-btn-' + tabId);
        activeBtn.classList.add('border-primary', 'text-primary');
        activeBtn.classList.remove('border-transparent', 'text-text-muted');
    }

    function openInvoiceModal() {
        const modal = document.getElementById('manual-invoice-modal');
        if (modal) {
            modal.classList.remove('hidden');
            recalcInvoiceTotal(); // Recalculate initially for pre-filled fees
        }
    }

    function closeInvoiceModal() {
        const modal = document.getElementById('manual-invoice-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    // Recalculate invoice total preview saat biaya tambahan diubah
    const BASE_NETT = <?php echo e((float)$customer->customerService?->total_monthly_bill ?? 0); ?>;
    function recalcInvoiceTotal() {
        const prorate = parseFloat(document.getElementById('prorate_amount')?.value || 0) || 0;
        const cable   = parseFloat(document.getElementById('extra_cable_fee')?.value || 0) || 0;
        const install = parseFloat(document.getElementById('extra_installation_fee')?.value || 0) || 0;
        const pole    = parseFloat(document.getElementById('extra_pole_fee')?.value || 0) || 0;
        const total   = BASE_NETT + prorate + cable + install + pole;

        const fmt = v => 'Rp ' + Math.round(v).toLocaleString('id-ID');
        const showRow = (rowId, valId, label, v) => {
            const row = document.getElementById(rowId);
            const el  = document.getElementById(valId);
            if (row && el) {
                row.style.display = v > 0 ? 'flex' : 'none';
                el.textContent = fmt(v);
            }
        };

        showRow('preview-prorate-row', 'preview-prorate', 'Prorate', prorate);
        showRow('preview-cable-row',   'preview-cable',   'Kabel',   cable);
        showRow('preview-install-row', 'preview-install', 'Instalasi', install);
        showRow('preview-pole-row',    'preview-pole',    'Tiang',   pole);

        const totalEl = document.getElementById('preview-total');
        if (totalEl) totalEl.textContent = fmt(total);
    }

    function autoCalculateProrate() {
        const activationDateStr = "<?php echo e($customer->customerService?->activation_date); ?>";
        if (!activationDateStr) {
            window.Toast.warning('Peringatan', 'Tanggal aktivasi belum diisi di sistem. Silakan pastikan data aktivasi sudah ada.');
            return;
        }

        // Activation date from DB (Y-m-d)
        const activationDate = new Date(activationDateStr);
        const year = activationDate.getFullYear();
        const month = activationDate.getMonth();
        
        // Last day of that specific month
        const lastDayOfMonth = new Date(year, month + 1, 0).getDate();
        const startDay = activationDate.getDate();
        
        // Days remaining in month (inclusive of activation day)
        // Logic: (LastDay - StartDay) + 1
        const daysRemaining = (lastDayOfMonth - startDay) + 1;
        
        // Calculate: (Monthly Bill / 30) * Days
        const monthlyBill = <?php echo e((float)$customer->customerService?->total_monthly_bill ?? 0); ?>;
        const prorate = (monthlyBill / 30) * daysRemaining;
        
        const prorateInput = document.getElementById('prorate_amount');
        if (prorateInput) {
            prorateInput.value = Math.round(prorate);
            recalcInvoiceTotal();
        }
    }

    function openSurveyModal() {
        const modal = document.getElementById('survey-modal');
        if (modal) {
            modal.classList.remove('hidden');
        }
    }

    function closeSurveyModal() {
        const modal = document.getElementById('survey-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    function openInstallationModal() {
        const modal = document.getElementById('installation-modal');
        if (modal) {
            modal.classList.remove('hidden');
        }
    }

    function closeInstallationModal() {
        const modal = document.getElementById('installation-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    function openTestReportModal() {
        const modal = document.getElementById('test-report-modal');
        if (modal) {
            modal.classList.remove('hidden');
        }
    }

    function closeTestReportModal() {
        const modal = document.getElementById('test-report-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    function openDeviceModal() {
        const modal = document.getElementById('device-modal');
        if (modal) {
            modal.classList.remove('hidden');
        }
    }

    function closeDeviceModal() {
        const modal = document.getElementById('device-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }
</script>

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
        
        <div class="pb-3 mb-4 border-b border-border space-y-1">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-[10px] font-bold text-text-muted uppercase tracking-wider block">Pelanggan</span>
                    <span class="text-sm font-bold text-text-main"><?php echo e($customer->full_name); ?></span>
                </div>
                <div class="text-right">
                    <span class="text-[10px] font-bold text-text-muted uppercase tracking-wider block">ID Jaringan</span>
                    <span class="font-mono text-xs text-text-main bg-surface-muted px-1.5 py-0.5 rounded border border-border"><?php echo e($customer->cid ?? 'DRAFT'); ?></span>
                </div>
            </div>
            <div class="text-xs text-text-muted pt-1 flex items-center gap-1">
                <svg class="w-3.5 h-3.5 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                <span>Cabang: <strong class="text-text-secondary font-semibold"><?php echo e($customer->pop->name ?? '-'); ?> (<?php echo e($customer->pop->pop_code ?? ''); ?>)</strong></span>
            </div>
        </div>

        
        <div class="flex gap-2 text-xs text-text-muted leading-relaxed bg-primary-soft/30 p-2.5 rounded-md border border-primary-border/40 mb-4">
            <svg class="w-4 h-4 text-primary shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Mini POP (OLT) & Distribusi dapat diatur setelah pemasangan dimulai, dan disesuaikan dengan konfigurasi MikroTik aktual.</span>
        </div>

        <form action="<?php echo e(route('customers.network-assignment.update', $customer)); ?>" method="POST" class="space-y-4">
            <?php echo csrf_field(); ?>
            <?php echo method_field('PUT'); ?>

            <div class="space-y-1.5">
                <label class="block text-xs font-semibold uppercase tracking-wider text-text-muted">Mini POP (OLT)</label>
                <select name="mini_pop_id" x-model="miniPopId" class="w-full text-sm px-3 py-2 border border-slate-200 rounded-md bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all duration-200 cursor-pointer">
                    <option value="">— Belum di-assign —</option>
                    <?php $__currentLoopData = $availableMiniPops; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $miniPop): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($miniPop->id); ?>">[<?php echo e($miniPop->pop_code); ?>] <?php echo e($miniPop->name); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
                <?php if($availableMiniPops->isEmpty()): ?>
                    <div class="flex gap-1.5 items-start mt-1 text-[11px] text-warning">
                        <svg class="w-3.5 h-3.5 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>Belum ada Mini POP terdaftar di bawah Cabang POP pelanggan ini — tambahkan dulu lewat Master POP.</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-semibold uppercase tracking-wider text-text-muted">Distribusi</label>
                <select name="distribution_id" class="w-full text-sm px-3 py-2 border border-slate-200 rounded-md bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all duration-200 cursor-pointer">
                    <option value="">— Belum di-assign —</option>
                    <?php $__currentLoopData = $availableDistributions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dist): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($dist->id); ?>"
                                x-show="miniPopId == <?php echo e($dist->pop_id); ?>"
                                <?php echo e(old('distribution_id', $customer->distribution_id) == $dist->id ? 'selected' : ''); ?>>
                            [<?php echo e($dist->code); ?>] <?php echo e($dist->name); ?>

                        </option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
                <p class="text-[11px] text-text-muted">Daftar Distribusi otomatis mengikuti Mini POP yang dipilih di atas.</p>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-border mt-4">
                <button type="button" @click="$dispatch('close-modal', 'network-assignment')"
                        class="text-sm font-semibold px-4 py-2 rounded-md border border-slate-200 bg-white hover:bg-slate-50 text-text-secondary transition-colors duration-200 cursor-pointer">Batal</button>
                <button type="submit" class="text-sm font-semibold px-5 py-2 rounded-md text-white bg-sky-600 hover:bg-sky-700 shadow-sm transition-colors duration-200 cursor-pointer">Simpan</button>
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


<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/customers/show.blade.php ENDPATH**/ ?>