<div class="flex items-center justify-between pb-4 border-b border-border">
    <div>
        <h3 class="text-sm font-bold text-text-main uppercase tracking-wider">Data Survey Teknis Pelanggan</h3>
        <p class="text-xs text-text-muted mt-0.5">Informasi hasil survey lapangan untuk persiapan instalasi.</p>
    </div>
    <div class="flex items-center gap-2">
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('customers.detail.survey.update')): ?>
            <?php if($customer->status === 'survey_in_progress'): ?>
                <a href="<?php echo e(route('customers.survey.report', $customer)); ?>" class="btn-primary inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold shadow-sm">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Lapor Hasil Survey
                </a>
            <?php elseif($customer->status === 'waiting_survey'): ?>
                <span class="text-[10px] italic px-2 py-1 rounded border" style="background:var(--color-warning-bg); color:var(--color-warning); border-color:var(--color-warning-border)">Mulai survey dari menu Antrean Survey terlebih dahulu.</span>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('customers.detail.survey.reject')): ?>
            <?php if(in_array($customer->status, ['waiting_survey', 'survey_in_progress'])): ?>
                <button type="button" onclick="document.getElementById('cancel-survey-modal').classList.remove('hidden')" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md border" style="color:var(--color-error); border-color:var(--color-error-border); background:var(--color-error-bg);">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Batalkan Survey
                </button>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('customers.detail.survey.reject')): ?>
<?php if(in_array($customer->status, ['waiting_survey', 'survey_in_progress'])): ?>
<div id="cancel-survey-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div class="bg-surface border border-border rounded-lg shadow-lg w-full max-w-md p-5">
        <h4 class="text-sm font-bold text-text-main mb-1">Batalkan Survey — Tidak Layak Pasang</h4>
        <p class="text-xs text-text-muted mb-4">Pelanggan akan diubah statusnya menjadi <strong>ditolak</strong> dan tidak bisa lanjut ke tahap pemasangan. Tindakan ini tidak bisa dibatalkan.</p>
        <form action="<?php echo e(route('customers.survey.cancel', $customer)); ?>" method="POST">
            <?php echo csrf_field(); ?>
            <label class="block text-xs font-semibold text-text-secondary mb-1">Alasan <span class="text-error">*</span></label>
            <textarea name="reason" rows="3" required class="w-full text-xs border border-border rounded-md px-3 py-2 mb-4 bg-surface text-text-main" placeholder="Contoh: Alamat tidak ditemukan, lokasi di luar jangkauan ODP, pelanggan menolak, dll."></textarea>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('cancel-survey-modal').classList.add('hidden')" class="btn-secondary text-xs px-3 py-1.5">Batal</button>
                <button type="submit" class="text-xs px-3 py-1.5 rounded-md font-semibold text-white" style="background:var(--color-error);">Ya, Batalkan Survey</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if($customer->surveys->count() > 0): ?>
    <div class="space-y-6">
        <?php $__currentLoopData = $customer->surveys()->latest()->get(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $survey): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="border border-border rounded-lg overflow-hidden">
                <div class="px-5 py-3 bg-surface-muted border-b border-border flex items-center justify-between">
                    <span class="text-xs font-bold text-text-main">Survey - <?php echo e(\App\Support\IndonesianDate::date($survey->survey_date)); ?></span>
                    <?php
                        $statusLabel = match($survey->survey_status) {
                            'completed' => 'Selesai',
                            'failed' => 'Tidak Layak',
                            'pending' => 'Menunggu',
                            default => $survey->survey_status,
                        };
                        $statusStyle = match($survey->survey_status) {
                            'completed' => 'background:var(--color-success-bg); color:var(--color-success); border-color:var(--color-success-border);',
                            'failed' => 'background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border);',
                            'pending' => 'background:var(--color-warning-bg); color:var(--color-warning); border-color:var(--color-warning-border);',
                            default => 'background:var(--color-surface-muted); color:var(--color-text-main); border-color:var(--color-border);',
                        };
                    ?>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wider" style="<?php echo e($statusStyle); ?>">
                        <?php echo e($statusLabel); ?>

                    </span>
                </div>
                <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
                    <div class="space-y-3">
                        <?php
                            $currentSurveyorNum = 1;
                            if ($survey->surveyors && preg_match('/Petugas Survey (\d+)/i', $survey->surveyors, $matches)) {
                                $currentSurveyorNum = (int)$matches[1];
                            }
                        ?>
                        <div class="flex justify-between border-b border-border py-1">
                            <span class="text-text-muted">Petugas Survey <?php echo e($currentSurveyorNum); ?> (Submitter)</span>
                            <span class="font-semibold text-text-main"><?php echo e($survey->technician->name ?? $survey->surveyors ?? '-'); ?></span>
                        </div>
                        <?php if($survey->surveyor2 || $survey->surveyor_2_id): ?>
                        <?php
                            $s2Num = $currentSurveyorNum == 1 ? 2 : 1;
                            if ($currentSurveyorNum == 3) {
                                $s2Num = 1;
                            }
                        ?>
                        <div class="flex justify-between border-b border-border py-1">
                            <span class="text-text-muted">Petugas Survey <?php echo e($s2Num); ?></span>
                            <span class="font-semibold text-text-main"><?php echo e($survey->surveyor2->name ?? '-'); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if($survey->surveyor3 || $survey->surveyor_3_id): ?>
                        <?php
                            $s3Num = 3;
                            if ($currentSurveyorNum == 3) {
                                $s3Num = 2;
                            } elseif ($currentSurveyorNum == 2) {
                                $s3Num = 3;
                            }
                        ?>
                        <div class="flex justify-between border-b border-border py-1">
                            <span class="text-text-muted">Petugas Survey <?php echo e($s3Num); ?></span>
                            <span class="font-semibold text-text-main"><?php echo e($survey->surveyor3->name ?? '-'); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex justify-between border-b border-border py-1">
                            <span class="text-text-muted">Waktu Survey</span>
                            <span class="font-semibold text-text-main"><?php echo e($survey->start_time ? substr($survey->start_time, 0, 5) : '-'); ?> - <?php echo e($survey->end_time ? substr($survey->end_time, 0, 5) : '-'); ?></span>
                        </div>
                        <div class="flex justify-between border-b border-border py-1">
                            <span class="text-text-muted">Estimasi Kabel</span>
                            <span class="font-semibold text-text-main"><?php echo e($survey->cable_estimation_meter ?? 0); ?> Meter</span>
                        </div>
                        <div class="flex justify-between border-b border-border py-1">
                            <span class="text-text-muted">ODP Terdekat</span>
                            <span class="font-semibold text-text-main"><?php echo e($survey->nearest_odp ?? '-'); ?></span>
                        </div>
                        <?php if($survey->requested_installation_date): ?>
                        <div class="flex justify-between border-b border-border py-1">
                            <span class="text-text-muted">Request Pemasangan</span>
                            <span class="font-semibold text-text-main font-mono"><?php echo e(\App\Support\IndonesianDate::date($survey->requested_installation_date)); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if($survey->assigned_at): ?>
                        <div class="flex justify-between border-b border-border py-1">
                            <span class="text-text-muted">Tanggal Penugasan FOP</span>
                            <span class="font-semibold text-text-main font-mono"><?php echo e(\App\Support\IndonesianDate::date($survey->assigned_at)); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if($survey->fop_id): ?>
                        <div class="flex justify-between border-b border-border py-1">
                            <span class="text-text-muted">ID FOP / Penugasan</span>
                            <span class="font-mono font-semibold text-text-main"><?php echo e($survey->fop_id); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if($survey->end_date): ?>
                        <div class="flex justify-between border-b border-border py-1">
                            <span class="text-text-muted">Tanggal Selesai Survey</span>
                            <span class="font-semibold text-text-main font-mono"><?php echo e(\App\Support\IndonesianDate::date($survey->end_date)); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if($survey->duration_minutes): ?>
                        <div class="flex justify-between border-b border-border py-1">
                            <span class="text-text-muted">Durasi Survey</span>
                            <span class="font-semibold text-text-main"><?php echo e($survey->duration_minutes); ?> Menit</span>
                        </div>
                        <?php endif; ?>
                        <?php if($survey->surveyors): ?>
                        <div class="flex justify-between border-b border-border py-1">
                            <span class="text-text-muted">Tim Survey (Surveyors)</span>
                            <span class="font-semibold text-text-main"><?php echo e($survey->surveyors); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php
                            // Estimasi material dicari lewat customer_id, bukan lewat $survey:
                            // barisnya menempel di FopTask Survey, sementara halaman ini
                            // bicara soal pelanggan. Lihat TaskMaterialService.
                            $estimasiMaterial = \App\Models\TaskMaterial::where('customer_id', $customer->id)
                                ->estimasi()->orderBy('id')->get();
                        ?>
                        <?php if($estimasiMaterial->isNotEmpty()): ?>
                        <div class="pt-2">
                            <span class="block text-text-muted mb-1">Estimasi Kebutuhan Alat:</span>
                            <div class="rounded border border-border overflow-hidden">
                                <?php $__currentLoopData = $estimasiMaterial; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $material): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <div class="flex justify-between px-3 py-1.5 <?php echo e($loop->even ? 'bg-surface-muted' : ''); ?>">
                                    <span class="text-text-secondary"><?php echo e($material->item_name); ?></span>
                                    <span class="font-mono font-semibold text-text-main"><?php echo e(rtrim(rtrim(number_format($material->qty, 2, ',', '.'), '0'), ',')); ?> <?php echo e($material->unit); ?></span>
                                </div>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="pt-2">
                            <span class="block text-text-muted mb-1">Alat Khusus / Kendala Peralatan:</span>
                            <p class="p-3 bg-surface-muted rounded border border-border italic"><?php echo e($survey->required_tools ?? 'Tidak ada catatan'); ?></p>
                        </div>
                        <div class="pt-2">
                            <span class="block text-text-muted mb-1">Catatan Survey:</span>
                            <p class="p-3 bg-surface-muted rounded border border-border italic"><?php echo e($survey->survey_note ?? 'Tidak ada catatan'); ?></p>
                        </div>
                    </div>
                    <div>
                        <span class="block text-text-muted mb-2 font-bold uppercase text-[9px] tracking-wider">Foto ODP / Survey Lapangan</span>
                        <?php if($survey->survey_photo): ?>
                            <div class="border border-border rounded overflow-hidden shadow-sm">
                                <img src="<?php echo e(asset('storage/' . $survey->survey_photo)); ?>" alt="Foto Survey" class="w-full object-cover max-h-64">
                                <div class="p-2 bg-surface-muted text-center">
                                    <a href="<?php echo e(asset('storage/' . $survey->survey_photo)); ?>" target="_blank" class="text-[10px] font-bold" style="color:var(--color-info)">LIHAT FULL RESOLUSI</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="h-48 bg-surface-muted border border-dashed border-border rounded flex flex-col items-center justify-center text-text-muted">
                                <svg class="h-10 w-10 mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span class="text-[10px] font-bold uppercase tracking-wider">Tidak Ada Foto ODP/Survey</span>
                            </div>
                        <?php endif; ?>

                        
                        <div class="mt-4">
                            <span class="block text-text-muted mb-2 font-bold uppercase text-[9px] tracking-wider">Foto Rumah Pelanggan</span>
                            <?php if($survey->house_photo): ?>
                                <div class="border border-border rounded overflow-hidden shadow-sm">
                                    <img src="<?php echo e(asset('storage/' . $survey->house_photo)); ?>" alt="Foto Rumah" class="w-full object-cover max-h-48">
                                    <div class="p-2 bg-surface-muted text-center">
                                        <a href="<?php echo e(asset('storage/' . $survey->house_photo)); ?>" target="_blank" class="text-[10px] font-bold" style="color:var(--color-info)">LIHAT FULL RESOLUSI</a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="h-32 bg-surface-muted border border-dashed border-border rounded flex flex-col items-center justify-center text-text-muted">
                                    <svg class="h-8 w-8 mb-1 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                    </svg>
                                    <span class="text-[10px] font-bold uppercase tracking-wider">Belum Ada Foto Rumah</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
<?php else: ?>
    <div class="py-12 text-center text-text-muted bg-surface-muted/20 border border-dashed border-border rounded-lg">
        <svg class="mx-auto h-12 w-12 text-text-muted mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 20l-5.447-2.724A2 2 0 013 15.447V5.553a2 2 0 011.553-1.944L9 2 15 5l5.447-2.724A2 2 0 0123 4.224v9.894a2 2 0 01-1.553 1.944L15 19l-6 1z" />
        </svg>
        <h4 class="text-sm font-semibold text-text-main">Belum ada data survey</h4>
        <p class="text-xs text-text-muted mt-1">Silakan isi hasil survey lapangan melalui tombol di atas.</p>
    </div>
<?php endif; ?>



<?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/customers/tabs/_survey.blade.php ENDPATH**/ ?>