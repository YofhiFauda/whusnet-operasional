<?php $__env->startSection('title', 'Permission Matrix — ' . $role->name); ?>
<?php $__env->startSection('page_title', 'Permission Matrix: ' . $role->name); ?>

<?php $__env->startSection('content'); ?>

<?php
$descriptions = [
    // Root features
    'dashboard' => 'Halaman utama pemantauan indikator operasional, termasuk ringkasan status billing pelanggan.',
    'master_wilayah' => 'Mengelola data administratif wilayah (Provinsi, Kabupaten, Kecamatan, Desa/Kelurahan).',
    'pops' => 'Mengelola data Point of Presence (POP) / Kantor Cabang operasional. Bagian dari menu gabungan <span class="font-medium text-text-secondary">"Manajemen User & POP"</span> di sidebar — centang di sini (POP/Cabang) sesuai akses yang mau diberikan.',
    'master_distribusi' => 'Mengelola data infrastruktur/jaringan distribusi kabel di lapangan.',
    'packages' => 'Mengelola master produk layanan internet dan harga berlangganan bulanan.',
    'master_status_pelanggan' => 'Mengelola status siklus hidup pelanggan (Draft, Perlu Dilengkapi, Lengkap, Siap Billing).',
    'sla_timeline' => 'Mengatur batas waktu wajib mulai ditangani (SLA) per Paket Internet untuk tiap jenis tiket (Survey, Pemasangan, MTN, dll) — bukan durasi pengerjaan teknisi di lapangan.',
    'users' => 'Mengelola akun pengguna sistem, penugasan role, dan cakupan wilayah kerja (User Scope). Bagian dari menu gabungan <span class="font-medium text-text-secondary">"Manajemen User & POP"</span> di sidebar — centang di sini (User Management) sesuai akses yang mau diberikan.',
    'roles' => 'Mengelola grup peran jabatan (Role) dan matriks izin hak akses (Permission Matrix).',
    'customers' => 'Pusat master data pelanggan, mencakup penambahan manual, pencarian, dan pengelolaan status.',
    'invoices' => 'Mengelola penerbitan, penyesuaian nominal, penghapusan, dan pencetakan tagihan bulanan pelanggan.',
    'payments' => 'Mengelola pencatatan pembayaran tagihan, verifikasi bukti transfer, approval, atau penolakan.',
    'reports' => 'Menyusun laporan operasional pelanggan, riwayat tagihan, mutasi pembayaran, serta opsi ekspor file.',
    'audit_logs' => 'Catatan kronologis aktivitas krusial yang dilakukan staf di sistem untuk audit keamanan.',
    'fop_tasks' => 'Layer 1 (Tiket FOP) — Pembuatan tiket awal, penentuan tipe pekerjaan (SURVEY/PSB/MTN), prioritas, dan tim pelaksana. Begitu teknisi di-assign, tiket auto-lanjut ke <span class="font-medium text-text-secondary">"Eksekusi Task"</span> di bawah.',
    'tasks' => 'Layer 2 (Eksekusi Task) — Kelanjutan otomatis dari Tiket FOP setelah teknisi di-assign untuk pengerjaan & pelaporan checklist lapangan. Bukan modul terpisah — jangan samakan dengan hak akses Tiket FOP.',
    'customers.detail.survey' => 'Hasil survey kelayakan, foto lokasi, status kelayakan, dan catatan surveyor. Sengaja dipisah dari "Pelanggan" — role yang cuma butuh isi laporan survey (mis. Teknisi) gak perlu dikasih akses List/Detail Pelanggan penuh.',
    'customers.detail.installation' => 'Instalasi perangkat di rumah pelanggan, status pengerjaan, dan validasi aktivasi. Sengaja dipisah dari "Pelanggan" — role yang cuma butuh isi laporan pemasangan (mis. Teknisi) gak perlu dikasih akses List/Detail Pelanggan penuh.',

    // Sub features
    'customers.import' => 'Modul import massal data pelanggan lama via file Excel / CSV.',
    'customers.detail' => 'Informasi detail pelanggan secara spesifik (identitas, alamat, billing, progress lapangan).',

    // Mini features (grandchildren)
    'customers.detail.identity' => 'Data personal pelanggan, kontak, NIK, dan berkas identitas.',
    'customers.detail.address' => 'Data koordinat GPS, alamat, kelurahan, kecamatan, dan kota/kabupaten.',
    'customers.detail.packages' => 'Paket layanan internet terhubung dan harga custom pelanggan. Status aktivasi layanan diatur lewat "Pemasangan Pelanggan", bukan di sini.',
    'customers.detail.devices' => 'Data teknis modem, SN perangkat, port OLT, password PPPoE, dan nama/password WiFi.',
    'customers.detail.documents' => 'Dokumen pendukung seperti KTP, foto rumah, form registrasi fisik, dsb.',
];
?>


<div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex items-center gap-2">
        <a href="<?php echo e(route('roles.index')); ?>"
           class="text-sm font-medium text-primary hover:text-primary-hover transition-colors flex items-center gap-1"
           aria-label="Kembali ke daftar role">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Daftar Role
        </a>
        <span class="text-text-disabled text-sm">/</span>
        <span class="text-sm text-text-muted"><?php echo e($role->name); ?></span>
    </div>

    <div class="flex items-center gap-2 text-sm text-text-secondary">
        <span>Role:</span>
        <span class="font-semibold text-text-main"><?php echo e($role->name); ?></span>
        <?php if($role->is_system): ?>
            <?php if (isset($component)) { $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.badge','data' => ['variant' => 'info']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'info']); ?>Sistem <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $attributes = $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $component = $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
        <?php else: ?>
            <?php if (isset($component)) { $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.badge','data' => ['variant' => 'neutral']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'neutral']); ?>Custom <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $attributes = $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $component = $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
        <?php endif; ?>
    </div>
</div>




<form action="<?php echo e(route('roles.update', $role)); ?>" method="POST" id="matrixForm" onsubmit="return handleFormSubmit(this);">
    <?php echo csrf_field(); ?>
    <?php echo method_field('PUT'); ?>

    <?php if (isset($component)) { $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.card','data' => ['padding' => 'compact']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['padding' => 'compact']); ?>

        
         <?php $__env->slot('header', null, []); ?> 
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-md font-semibold text-text-main">Feature Tree Permission Matrix</h2>
                    <p class="mt-0.5 text-sm text-text-muted">
                        Centang aksi yang diizinkan untuk role
                        <strong class="text-text-secondary"><?php echo e($role->name); ?></strong>.
                        Perubahan berlaku untuk semua user dengan role ini.
                    </p>
                </div>

                
                <div class="flex items-center gap-3 text-sm">
                    <div class="text-center min-w-[64px] rounded-md border border-border bg-surface-muted px-3 py-1.5">
                        <div class="data-text text-lg font-semibold text-text-main leading-tight" id="permCount">
                            <?php echo e(count($rolePermissions)); ?>

                        </div>
                        <div class="text-xs text-text-muted">aktif</div>
                    </div>

                    
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <button type="button" onclick="selectAll()"
                                class="text-primary hover:text-primary-hover font-medium underline-offset-2 hover:underline transition-colors">
                            Pilih Semua
                        </button>
                        <span class="text-text-disabled">|</span>
                        <button type="button" onclick="deselectAll()"
                                class="text-text-muted hover:text-text-secondary font-medium underline-offset-2 hover:underline transition-colors">
                            Hapus Semua
                        </button>
                    </div>
                </div>
            </div>
         <?php $__env->endSlot(); ?>

        
        <div id="featureTree" class="-mx-2 -mb-2 sm:-mx-3 sm:-mb-3 border-t border-border">

            <?php $__empty_1 = true; $__currentLoopData = $features; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $feature): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>

            
            <div class="feature-group border-b border-border last:border-b-0"
                 x-data="{ expanded: true }">

                
                <div class="flex items-center justify-between px-4 py-3 cursor-pointer
                            bg-surface-muted hover:bg-border/30 transition-colors select-none"
                     @click="expanded = !expanded">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <svg class="h-4 w-4 text-text-muted transition-transform flex-shrink-0"
                             :class="expanded ? 'rotate-90' : ''"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        <div class="min-w-0">
                            <span class="text-sm font-semibold text-text-main"><?php echo e($feature->name); ?></span>
                            <?php if(isset($descriptions[$feature->code])): ?>
                                <p class="text-xs text-text-muted mt-0.5">
                                    <?php echo $descriptions[$feature->code]; ?>

                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    
                    <div @click.stop class="flex items-center gap-1.5 ml-4 flex-shrink-0">
                        <label class="inline-flex items-center gap-1.5 cursor-pointer text-xs text-text-muted hover:text-text-secondary">
                            <input type="checkbox"
                                   class="feature-toggle rounded border-border text-primary focus:ring-2 focus:ring-primary-border"
                                   data-feature="<?php echo e($feature->id); ?>"
                                   onchange="toggleFeatureGroup(this)"
                                   aria-label="Pilih semua permission <?php echo e($feature->name); ?>">
                            <span class="hidden sm:inline">Pilih semua</span>
                        </label>
                    </div>
                </div>

                
                <div x-show="expanded" x-collapse class="bg-surface">

                    
                    <?php if(isset($permissions[$feature->id]) && $permissions[$feature->id]->count()): ?>
                    <div class="px-6 py-3 flex flex-wrap gap-x-6 gap-y-3 border-b border-border/50"
                         data-feature-id="<?php echo e($feature->id); ?>">
                        <?php $__currentLoopData = $permissions[$feature->id]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $perm): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php $isSensitive = str_contains($perm->code, 'sensitive'); ?>
                        <label class="inline-flex items-center gap-2 cursor-pointer group">
                            <input type="checkbox"
                                   name="permissions[]"
                                   value="<?php echo e($perm->id); ?>"
                                   <?php if(in_array($perm->id, $rolePermissions)): echo 'checked'; endif; ?>
                                   data-feature-id="<?php echo e($feature->id); ?>"
                                   data-parent-feature-id="<?php echo e($feature->parent_id ?? ''); ?>"
                                   data-permission-code="<?php echo e($perm->code); ?>"
                                   data-is-view="<?php echo e(str_ends_with($perm->code, '.view') || $perm->code === 'task.view.all' || $perm->code === 'task.view.own' ? 'true' : 'false'); ?>"
                                   onchange="handleCheckboxChange(this)"
                                   class="perm-checkbox rounded border-border focus:ring-2 transition-colors
                                          <?php echo e($isSensitive
                                              ? 'text-error border-error-border focus:ring-error-border'
                                              : 'text-primary focus:ring-primary-border group-hover:border-primary'); ?>">
                            <span class="text-sm transition-colors
                                         <?php echo e($isSensitive
                                             ? 'text-error font-medium group-hover:opacity-80'
                                             : 'text-text-secondary group-hover:text-text-main'); ?>">
                                <?php echo e($perm->name ?? $perm->action->name ?? $perm->code); ?>

                                <?php if($isSensitive): ?>
                                    <span class="ml-0.5 text-error" title="Aksi sensitif — berikan hati-hati" aria-hidden="true">⚠</span>
                                <?php endif; ?>
                            </span>
                        </label>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                    <?php elseif($feature->children->count() === 0): ?>
                    <p class="px-6 py-3 text-xs text-text-disabled italic">
                        Tidak ada permission untuk fitur ini.
                    </p>
                    <?php endif; ?>

                    
                    <?php if($feature->children->count() > 0): ?>
                    <div class="divide-y divide-border/40">
                        <?php $__currentLoopData = $feature->children; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $child): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="pl-8" x-data="{ childExpanded: true }">

                            
                            <div class="flex items-center justify-between px-4 py-2.5 cursor-pointer
                                        hover:bg-surface-muted/60 transition-colors"
                                 @click="childExpanded = !childExpanded">
                                <div class="flex items-center gap-2 min-w-0">
                                    <svg class="h-3.5 w-3.5 text-text-muted transition-transform flex-shrink-0"
                                         :class="childExpanded ? 'rotate-90' : ''"
                                         fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                    <div class="min-w-0">
                                        <span class="text-sm font-medium text-text-secondary"><?php echo e($child->name); ?></span>
                                        <?php if(isset($descriptions[$child->code])): ?>
                                            <p class="text-xs text-text-muted/80 mt-0.5">
                                                <?php echo $descriptions[$child->code]; ?>

                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            
                            <div x-show="childExpanded" x-collapse class="pb-2">

                                <?php if(isset($permissions[$child->id]) && $permissions[$child->id]->count()): ?>
                                <div class="px-5 py-2 flex flex-wrap gap-x-6 gap-y-2.5"
                                     data-feature-id="<?php echo e($child->id); ?>">
                                    <?php $__currentLoopData = $permissions[$child->id]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $perm): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <?php $isSensitive = str_contains($perm->code, 'sensitive'); ?>
                                    <label class="inline-flex items-center gap-2 cursor-pointer group">
                                        <input type="checkbox"
                                               name="permissions[]"
                                               value="<?php echo e($perm->id); ?>"
                                               <?php if(in_array($perm->id, $rolePermissions)): echo 'checked'; endif; ?>
                                               data-feature-id="<?php echo e($child->id); ?>"
                                               data-parent-feature-id="<?php echo e($child->parent_id ?? ''); ?>"
                                               data-permission-code="<?php echo e($perm->code); ?>"
                                               data-is-view="<?php echo e(str_ends_with($perm->code, '.view') || $perm->code === 'task.view.all' || $perm->code === 'task.view.own' ? 'true' : 'false'); ?>"
                                               onchange="handleCheckboxChange(this)"
                                               class="perm-checkbox rounded border-border focus:ring-2 transition-colors
                                                      <?php echo e($isSensitive
                                                          ? 'text-error border-error-border focus:ring-error-border'
                                                          : 'text-primary focus:ring-primary-border group-hover:border-primary'); ?>">
                                        <span class="text-sm transition-colors
                                                     <?php echo e($isSensitive
                                                         ? 'text-error font-medium group-hover:opacity-80'
                                                         : 'text-text-secondary group-hover:text-text-main'); ?>">
                                            <?php echo e($perm->name ?? $perm->action->name ?? $perm->code); ?>

                                            <?php if($isSensitive): ?>
                                                <span class="ml-0.5 text-error" title="Aksi sensitif — berikan hati-hati" aria-hidden="true">⚠</span>
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </div>
                                <?php else: ?>
                                    <?php if($child->children->count() === 0): ?>
                                    <p class="px-5 py-2 text-xs text-text-disabled italic">
                                        Tidak ada permission spesifik.
                                    </p>
                                    <?php endif; ?>
                                <?php endif; ?>

                                
                                <?php if($child->children->count() > 0): ?>
                                <div class="mx-5 mt-1 divide-y divide-border/30 border-l-2 border-border pl-4">
                                    <?php $__currentLoopData = $child->children; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $grandchild): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <div class="py-2.5">
                                        
                                        <div class="flex flex-col gap-0.5 mb-2">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-block h-1.5 w-1.5 rounded-full bg-primary/50 flex-shrink-0"></span>
                                                <span class="text-xs font-semibold text-text-secondary uppercase tracking-wide">
                                                    <?php echo e($grandchild->name); ?>

                                                </span>
                                            </div>
                                            <?php if(isset($descriptions[$grandchild->code])): ?>
                                                <p class="text-[11px] text-text-muted/70 pl-3.5">
                                                    <?php echo $descriptions[$grandchild->code]; ?>

                                                </p>
                                            <?php endif; ?>
                                        </div>

                                        <?php if(isset($permissions[$grandchild->id]) && $permissions[$grandchild->id]->count()): ?>
                                        <div class="flex flex-wrap gap-x-5 gap-y-2"
                                             data-feature-id="<?php echo e($grandchild->id); ?>">
                                            <?php $__currentLoopData = $permissions[$grandchild->id]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $perm): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <?php $isSensitive = str_contains($perm->code, 'sensitive'); ?>
                                            <label class="inline-flex items-center gap-1.5 cursor-pointer group">
                                                <input type="checkbox"
                                                       name="permissions[]"
                                                       value="<?php echo e($perm->id); ?>"
                                                       <?php if(in_array($perm->id, $rolePermissions)): echo 'checked'; endif; ?>
                                                       data-feature-id="<?php echo e($grandchild->id); ?>"
                                                       data-parent-feature-id="<?php echo e($grandchild->parent_id ?? ''); ?>"
                                                       data-permission-code="<?php echo e($perm->code); ?>"
                                                       data-is-view="<?php echo e(str_ends_with($perm->code, '.view') || $perm->code === 'task.view.all' || $perm->code === 'task.view.own' ? 'true' : 'false'); ?>"
                                                       onchange="handleCheckboxChange(this)"
                                                       class="perm-checkbox rounded border-border focus:ring-2 transition-colors
                                                              <?php echo e($isSensitive
                                                                  ? 'text-error border-error-border focus:ring-error-border'
                                                                  : 'text-primary focus:ring-primary-border group-hover:border-primary'); ?>">
                                                <span class="text-xs transition-colors
                                                             <?php echo e($isSensitive
                                                                 ? 'text-error font-medium group-hover:opacity-80'
                                                                 : 'text-text-secondary group-hover:text-text-main'); ?>">
                                                    <?php echo e($perm->name ?? $perm->action->name ?? $perm->code); ?>

                                                    <?php if($isSensitive): ?>
                                                        <span class="ml-0.5 text-error" title="Aksi sensitif — berikan hati-hati" aria-hidden="true">⚠</span>
                                                    <?php endif; ?>
                                                </span>
                                            </label>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </div>
                                        <?php else: ?>
                                        <p class="text-xs text-text-disabled italic">Tidak ada permission.</p>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </div>
                                <?php endif; ?>

                            </div>
                        </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="px-6 py-16 text-center">
                <div class="flex flex-col items-center gap-3 text-text-muted">
                    <svg class="h-10 w-10 text-text-disabled" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <p class="text-sm font-medium">Feature tree belum tersedia</p>
                    <p class="text-xs text-text-disabled max-w-xs text-center">
                        Jalankan <span class="data-text">php artisan rbac:generate-permissions</span>
                        untuk membuat permission dari feature tree.
                    </p>
                </div>
            </div>
            <?php endif; ?>

        </div>

        
         <?php $__env->slot('footer', null, []); ?> 
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-text-muted">
                    Semua perubahan permission tercatat di
                    <span class="font-medium text-text-secondary">Audit Log</span>
                    dan berlaku segera untuk semua user dengan role ini.
                </p>
                <div class="flex gap-2 flex-shrink-0">
                    <a href="<?php echo e(route('roles.index')); ?>" class="btn-secondary">
                        Batal
                    </a>
                    <button type="submit" form="matrixForm" class="btn-primary">
                        Simpan Permission
                    </button>
                </div>
            </div>
         <?php $__env->endSlot(); ?>

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

</form>

<?php $__env->startSection('scripts'); ?>
<script>
    let formSubmitted = false;
    function handleFormSubmit(form) {
        if (formSubmitted) return false;
        formSubmitted = true;

        const submitButtons = form.querySelectorAll('button[type="submit"]');
        submitButtons.forEach(btn => {
            btn.disabled = true;
            if (btn.classList.contains('btn-primary')) {
                btn.innerHTML = `
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" style="vertical-align: middle;">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Menyimpan...
                `;
            }
        });
        return true;
    }

    // ---- Toast warning untuk role sistem ----
    <?php if($role->is_system && !$role->isOwner()): ?>
    document.addEventListener('DOMContentLoaded', () => {
        Toast.warning(
            'Role Sistem',
            'Perubahan permission berlaku untuk semua user dengan role <?php echo e($role->name); ?>. Berhati-hati saat mengurangi permission aktif.',
            7000
        );
    });
    <?php endif; ?>
    function updatePermCount() {
        const count = document.querySelectorAll('.perm-checkbox:checked').length;
        document.getElementById('permCount').textContent = count;
        updateFeatureToggles();
    }

    // Dependency chaining (parent-child) resolution
    function resolvePermissionDependencies() {
        const checkboxes = Array.from(document.querySelectorAll('.perm-checkbox'));
        
        // Helper to recursively check if a parent feature's view permission is checked
        function isFeatureViewChecked(featureId) {
            if (!featureId) return true;
            
            const viewCheckbox = checkboxes.find(cb => 
                cb.dataset.featureId === featureId.toString() && 
                cb.dataset.isView === 'true'
            );
            
            if (!viewCheckbox) return true;
            
            if (!viewCheckbox.checked || viewCheckbox.disabled) {
                return false;
            }
            
            const parentId = viewCheckbox.dataset.parentFeatureId;
            return isFeatureViewChecked(parentId);
        }
        
        // Apply top-down disabling for children if parent view is unchecked
        checkboxes.forEach(cb => {
            const parentId = cb.dataset.parentFeatureId;
            
            if (parentId) {
                const parentViewActive = isFeatureViewChecked(parentId);
                if (!parentViewActive) {
                    cb.checked = false;
                    cb.disabled = true;
                    cb.classList.add('opacity-50');
                } else {
                    cb.disabled = false;
                    cb.classList.remove('opacity-50');
                }
            }
        });
    }

    // Handle check state change with dependency checks
    function handleCheckboxChange(changedCb) {
        if (changedCb.checked) {
            // Bottom-up auto-checking: checking a child automatically checks its parent feature's view permission
            let parentId = changedCb.dataset.parentFeatureId;
            const checkboxes = Array.from(document.querySelectorAll('.perm-checkbox'));
            
            while (parentId) {
                const parentViewCb = checkboxes.find(cb => 
                    cb.dataset.featureId === parentId.toString() && 
                    cb.dataset.isView === 'true'
                );
                if (parentViewCb && !parentViewCb.checked) {
                    parentViewCb.checked = true;
                    parentId = parentViewCb.dataset.parentFeatureId;
                } else {
                    break;
                }
            }
        }
        
        resolvePermissionDependencies();
        updatePermCount();
    }

    // Pilih semua checkbox
    function selectAll() {
        document.querySelectorAll('.perm-checkbox').forEach(cb => { 
            cb.checked = true; 
            cb.disabled = false;
            cb.classList.remove('opacity-50');
        });
        resolvePermissionDependencies();
        updatePermCount();
    }

    // Hapus semua checkbox
    function deselectAll() {
        document.querySelectorAll('.perm-checkbox').forEach(cb => { cb.checked = false; });
        resolvePermissionDependencies();
        updatePermCount();
    }

    // Toggle semua permission dalam satu feature
    function toggleFeatureGroup(toggleCb) {
        const featureId   = toggleCb.dataset.feature;
        const featureGroup = document.querySelector(`[data-feature-id="${featureId}"]`);
        if (featureGroup) {
            featureGroup.querySelectorAll('.perm-checkbox').forEach(cb => {
                if (!cb.disabled) {
                    cb.checked = toggleCb.checked;
                }
            });
        }
        resolvePermissionDependencies();
        updatePermCount();
    }

    // Sinkronisasi state checkbox "Pilih semua" tiap feature
    function updateFeatureToggles() {
        document.querySelectorAll('.feature-toggle').forEach(toggle => {
            const featureId    = toggle.dataset.feature;
            const featureGroup = document.querySelector(`[data-feature-id="${featureId}"]`);
            if (!featureGroup) return;

            const all     = featureGroup.querySelectorAll('.perm-checkbox');
            const checked = featureGroup.querySelectorAll('.perm-checkbox:checked');

            toggle.indeterminate = checked.length > 0 && checked.length < all.length;
            toggle.checked       = all.length > 0 && checked.length === all.length;
        });
    }

    // Inisialisasi saat halaman dimuat
    document.addEventListener('DOMContentLoaded', () => {
        resolvePermissionDependencies();
        updatePermCount();
        document.querySelectorAll('.perm-checkbox').forEach(cb => {
            cb.addEventListener('change', () => {
                resolvePermissionDependencies();
                updateFeatureToggles();
            });
        });
        updateFeatureToggles();
    });
</script>
<?php $__env->stopSection(); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/roles/matrix.blade.php ENDPATH**/ ?>