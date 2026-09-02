

<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between pb-4 border-b border-slate-200 dark:border-slate-700">
    <div>
        <h3 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">Hasil Laporan Survey Lapangan (Data FOP &amp; Survey)</h3>
        <p class="text-[11px] text-slate-500 mt-0.5">Waktu penugasan, tim surveyor, kelayakan lokasi, estimasi material &amp; alat kerja, serta foto dokumentasi.</p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('customers.detail.survey.update')): ?>
            <?php if($customer->status === 'survey_in_progress'): ?>
                <a href="<?php echo e(route('customers.survey.report', ['customer' => $customer, 'return_to' => route('customers.show', $customer)])); ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg transition-colors text-xs font-semibold shadow-sm">
                    <i class="fa-solid fa-pen-to-square"></i>
                    Lapor Hasil Survey
                </a>
            <?php elseif($customer->status === 'waiting_survey'): ?>
                <span class="text-[10px] italic px-2.5 py-1 rounded border bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 border-amber-200 dark:border-amber-800">
                    Mulai survey dari menu Antrean Survey terlebih dahulu.
                </span>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('customers.detail.survey.reject')): ?>
            <?php if(in_array($customer->status, ['waiting_survey', 'survey_in_progress'])): ?>
                <button type="button" onclick="document.getElementById('cancel-survey-modal').classList.remove('hidden')"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg border bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 border-rose-200 dark:border-rose-800 hover:bg-rose-100 dark:hover:bg-rose-950/60 transition-colors cursor-pointer">
                    <i class="fa-solid fa-xmark"></i>
                    Batalkan Survey
                </button>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('customers.detail.survey.reject')): ?>
<?php if(in_array($customer->status, ['waiting_survey', 'survey_in_progress'])): ?>
<div id="cancel-survey-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-xl w-full max-w-md p-5">
        <h4 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider mb-1">Batalkan Survey — Tidak Layak Pasang</h4>
        <p class="text-[11px] text-slate-500 mb-4">Pelanggan akan diubah statusnya menjadi <strong>ditolak</strong> dan tidak bisa lanjut ke tahap pemasangan. Tindakan ini tidak bisa dibatalkan.</p>
        <form action="<?php echo e(route('customers.survey.cancel', $customer)); ?>" method="POST">
            <?php echo csrf_field(); ?>
            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Alasan <span class="text-rose-600">*</span></label>
            <textarea name="reason" rows="3" required
                      class="w-full text-xs border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 mb-4 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200"
                      placeholder="Contoh: Alamat tidak ditemukan, lokasi di luar jangkauan ODP, pelanggan menolak, dll."></textarea>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('cancel-survey-modal').classList.add('hidden')"
                        class="px-4 py-2 text-xs font-semibold rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 cursor-pointer">Batal</button>
                <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg text-white bg-rose-600 hover:bg-rose-700 shadow-sm cursor-pointer">Ya, Batalkan Survey</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if($customer->surveys->count() > 0): ?>
    <?php
        // Material estimasi & alat kerja dicari lewat customer_id: barisnya menempel
        // di FopTask (lihat migration task_materials / task_work_tools), sementara
        // halaman ini bicara soal pelanggan. Alat kerja difilter ke FopTask SURVEY
        // supaya tidak tercampur dengan alat pemasangan — tabel task_work_tools
        // memang TIDAK punya kolom kind (estimasi/terpakai).
        $estimasiMaterial = \App\Models\TaskMaterial::where('customer_id', $customer->id)
            ->estimasi()->orderBy('id')->get();

        $alatSurvey = \App\Models\TaskWorkTool::where('customer_id', $customer->id)
            ->whereHas('fopTask', fn ($q) => $q->where('category', \App\Enums\TaskType::SURVEY->value))
            ->orderBy('id')->get();
    ?>

    <div class="space-y-6">
        <?php $__currentLoopData = $customer->surveys()->latest()->get(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $survey): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $statusLabel = match($survey->survey_status) {
                    'completed' => 'Selesai & Layak',
                    'failed' => 'Tidak Layak',
                    'pending' => 'Menunggu',
                    default => $survey->survey_status,
                };
                $statusBadge = match($survey->survey_status) {
                    'completed' => 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
                    'failed' => 'bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 border-rose-200 dark:border-rose-800',
                    'pending' => 'bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 border-amber-200 dark:border-amber-800',
                    default => 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-600',
                };

                // Nomor petugas mengikuti penomoran yang tercatat di kolom surveyors
                // (laporan lapangan menuliskan "Petugas Survey N"), bukan urutan kolom DB.
                $currentSurveyorNum = 1;
                if ($survey->surveyors && preg_match('/Petugas Survey (\d+)/i', $survey->surveyors, $matches)) {
                    $currentSurveyorNum = (int) $matches[1];
                }
                $timSurvey = collect([
                    $survey->technician->name ?? $survey->surveyors,
                    $survey->surveyor2->name ?? null,
                    $survey->surveyor3->name ?? null,
                ])->filter()->implode(', ');

                $odpPort = $customer->customerDevice->odp_port ?? $customer->customerTechnicalDetail?->odp_port;
                $tglMulai = $survey->survey_date ? \App\Support\IndonesianDate::date($survey->survey_date) : '-';
                $tglSelesai = $survey->end_date ? \App\Support\IndonesianDate::date($survey->end_date) : $tglMulai;
            ?>

            <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
                <div class="px-5 py-3.5 bg-slate-50/50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700 flex flex-wrap gap-2 justify-between items-center">
                    <span class="text-xs font-bold text-slate-900 dark:text-slate-100">
                        Survey — <?php echo e($tglMulai); ?>

                    </span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wide <?php echo e($statusBadge); ?>">
                        <?php echo e($statusLabel); ?>

                    </span>
                </div>

                <div class="p-5 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
                        <div class="space-y-2.5">
                            <div class="flex justify-between gap-3 py-1 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">ID FOP Penugasan Survey</span>
                                <span class="font-mono font-bold text-sky-600 dark:text-sky-400 searchable-text"><?php echo e($survey->fop_id ?: '-'); ?></span>
                            </div>
                            <div class="flex justify-between gap-3 py-1 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Waktu Penugasan FOP Survey</span>
                                <span class="font-mono font-semibold text-slate-900 dark:text-slate-100 searchable-text">
                                    <?php echo e($survey->assigned_at ? \App\Support\IndonesianDate::dateTime($survey->assigned_at) . ' WIB' : '-'); ?>

                                </span>
                            </div>
                            <div class="flex justify-between gap-3 py-1 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Tanggal &amp; Waktu Mulai Survey</span>
                                <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text">
                                    <?php echo e($tglMulai); ?> — <?php echo e($survey->start_time ? substr($survey->start_time, 0, 5) . ' WIB' : '-'); ?>

                                </span>
                            </div>
                            <div class="flex justify-between gap-3 py-1 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Tanggal &amp; Waktu Selesai Survey</span>
                                <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text">
                                    <?php echo e($tglSelesai); ?> — <?php echo e($survey->end_time ? substr($survey->end_time, 0, 5) . ' WIB' : '-'); ?>

                                </span>
                            </div>
                            <div class="flex justify-between gap-3 py-1 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Durasi Pelaksanaan Survey</span>
                                <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400 searchable-text">
                                    <?php echo e($survey->duration_minutes ? $survey->duration_minutes . ' Menit' : '-'); ?>

                                </span>
                            </div>
                            <?php if($survey->requested_installation_date): ?>
                            <div class="flex justify-between gap-3 py-1 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Tanggal Minta Pasang (Pelanggan)</span>
                                <span class="font-mono font-semibold text-slate-900 dark:text-slate-100 searchable-text"><?php echo e(\App\Support\IndonesianDate::date($survey->requested_installation_date)); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="space-y-2.5">
                            <div class="flex justify-between gap-3 py-1 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Petugas Survey <?php echo e($currentSurveyorNum); ?> (Submitter)</span>
                                <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($survey->technician->name ?? $survey->surveyors ?? '-'); ?></span>
                            </div>
                            <div class="flex justify-between gap-3 py-1 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Tim Survey (Petugas Lapangan)</span>
                                <span class="font-semibold text-slate-900 dark:text-slate-100 text-right searchable-text"><?php echo e($timSurvey ?: '-'); ?></span>
                            </div>
                            <div class="flex justify-between gap-3 py-1 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">ODP Terdekat &amp; Port</span>
                                <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text">
                                    <?php echo e($survey->nearest_odp ?: '-'); ?><?php echo e($odpPort ? ' (Port ' . $odpPort . ')' : ''); ?>

                                </span>
                            </div>
                            <div class="flex justify-between gap-3 py-1 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Estimasi Jarak Kabel Dropcore</span>
                                <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($survey->cable_estimation_meter ?? 0); ?> Meter</span>
                            </div>
                            <div class="flex justify-between gap-3 py-1 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Status Kelayakan Lokasi</span>
                                <span class="font-bold searchable-text <?php echo e($survey->survey_status === 'completed' ? 'text-emerald-600 dark:text-emerald-400' : ($survey->survey_status === 'failed' ? 'text-rose-600 dark:text-rose-400' : 'text-amber-600 dark:text-amber-400')); ?>">
                                    <?php echo e($survey->survey_status === 'completed' ? 'LAYAK (Siap Pasang)' : ($survey->survey_status === 'failed' ? 'TIDAK LAYAK' : 'BELUM DINILAI')); ?>

                                </span>
                            </div>
                        </div>
                    </div>

                    
                    <div>
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">
                            <i class="fa-solid fa-boxes-stacked text-sky-600 mr-1"></i> 1. TABEL ESTIMASI KEBUTUHAN MATERIAL / BARANG
                        </span>
                        <?php if($estimasiMaterial->isNotEmpty()): ?>
                            <div class="overflow-x-auto border border-slate-200 dark:border-slate-700 rounded-lg">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="bg-slate-50/60 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-700 text-[10px] font-bold text-slate-400 uppercase">
                                            <th class="px-4 py-2">Nama Barang / Material Pasif</th>
                                            <th class="px-4 py-2 text-center">Jumlah (Qty)</th>
                                            <th class="px-4 py-2">Satuan</th>
                                            <th class="px-4 py-2">Keterangan Tambahan</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700 font-mono">
                                        <?php $__currentLoopData = $estimasiMaterial; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $material): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors">
                                                <td class="px-4 py-2 font-sans font-semibold text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($material->item_name); ?></td>
                                                <td class="px-4 py-2 text-center font-bold text-slate-900 dark:text-slate-100"><?php echo e(rtrim(rtrim(number_format($material->qty, 2, ',', '.'), '0'), ',')); ?></td>
                                                <td class="px-4 py-2 font-sans text-slate-700 dark:text-slate-300"><?php echo e($material->unit); ?></td>
                                                <td class="px-4 py-2 font-sans text-slate-500"><?php echo e($material->note ?: $material->category_label); ?></td>
                                            </tr>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="py-5 text-center text-[11px] text-slate-400 bg-slate-50/50 dark:bg-slate-900/30 border border-dashed border-slate-200 dark:border-slate-700 rounded-lg">
                                Belum ada estimasi material yang dicatat surveyor.
                            </div>
                        <?php endif; ?>
                    </div>

                    
                    <div>
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">
                            <i class="fa-solid fa-screwdriver-wrench text-indigo-600 mr-1"></i> 2. TABEL ESTIMASI ALAT KERJA YANG PERLU DIBAWA (TOOLS SURVEY)
                        </span>
                        <?php if($alatSurvey->isNotEmpty()): ?>
                            <div class="overflow-x-auto border border-slate-200 dark:border-slate-700 rounded-lg">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="bg-slate-50/60 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-700 text-[10px] font-bold text-slate-400 uppercase">
                                            <th class="px-4 py-2">Nama Alat Kerja (Tools)</th>
                                            <th class="px-4 py-2">Keterangan / Fungsi Utama</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                        <?php $__currentLoopData = $alatSurvey; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $alat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors">
                                                <td class="px-4 py-2 font-semibold text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($alat->tool_name); ?></td>
                                                <td class="px-4 py-2 text-slate-500"><?php echo e($alat->note ?: ($alat->workTool->note ?? '-')); ?></td>
                                            </tr>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="py-5 text-center text-[11px] text-slate-400 bg-slate-50/50 dark:bg-slate-900/30 border border-dashed border-slate-200 dark:border-slate-700 rounded-lg">
                                Belum ada checklist alat kerja pada FOP Task Survey pelanggan ini.
                            </div>
                        <?php endif; ?>
                        
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
                        <div>
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Alat Khusus / Kendala Peralatan</span>
                            <p class="p-3 bg-slate-50 dark:bg-slate-900/50 rounded border border-slate-200 dark:border-slate-700 italic text-slate-600 dark:text-slate-300 searchable-text">
                                <?php echo e($survey->required_tools ?: 'Tidak ada catatan'); ?>

                            </p>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Catatan Survey</span>
                            <p class="p-3 bg-slate-50 dark:bg-slate-900/50 rounded border border-slate-200 dark:border-slate-700 italic text-slate-600 dark:text-slate-300 searchable-text">
                                <?php echo e($survey->survey_note ?: 'Tidak ada catatan'); ?>

                            </p>
                        </div>
                    </div>

                    
                    <div>
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">FOTO DOKUMENTASI HASIL SURVEY LAPANGAN</span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <?php $__currentLoopData = [
                                ['path' => $survey->survey_photo, 'title' => 'Foto ODP (Survey)', 'icon' => 'fa-network-wired'],
                                ['path' => $survey->house_photo, 'title' => 'Foto Rumah Pelanggan', 'icon' => 'fa-house-user'],
                            ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $photo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-3 bg-slate-50 dark:bg-slate-900/40 text-center">
                                    <?php $url = foto_publik($photo['path']); ?>
                                    <?php if($url): ?>
                                        <div class="h-32 rounded mb-2 overflow-hidden bg-slate-200 dark:bg-slate-700">
                                            <img src="<?php echo e($url); ?>" alt="<?php echo e($photo['title']); ?>" class="w-full h-32 object-cover">
                                        </div>
                                        <span class="block text-xs font-bold text-slate-800 dark:text-slate-200"><?php echo e($photo['title']); ?></span>
                                        <a href="<?php echo e($url); ?>" target="_blank" class="text-[10px] text-sky-600 font-bold hover:underline">Lihat Full Resolusi ↗</a>
                                    <?php elseif($photo['path']): ?>
                                        <div class="h-32 bg-slate-200 dark:bg-slate-700 rounded mb-2 flex flex-col items-center justify-center text-slate-500">
                                            <i class="fa-solid fa-triangle-exclamation text-3xl mb-1 text-yellow-500"></i>
                                            <span class="text-[10px] font-mono">FILE TIDAK ADA</span>
                                        </div>
                                        <span class="block text-xs font-bold text-slate-800 dark:text-slate-200"><?php echo e($photo['title']); ?></span>
                                        <span class="text-[10px] text-yellow-600 dark:text-yellow-400 font-semibold">Berkas hilang di penyimpanan</span>
                                    <?php else: ?>
                                        <div class="h-32 bg-slate-200 dark:bg-slate-700 rounded mb-2 flex flex-col items-center justify-center text-slate-500">
                                            <i class="fa-solid <?php echo e($photo['icon']); ?> text-3xl mb-1 text-slate-400"></i>
                                            <span class="text-[10px] font-mono">TIDAK ADA FILE</span>
                                        </div>
                                        <span class="block text-xs font-bold text-slate-800 dark:text-slate-200"><?php echo e($photo['title']); ?></span>
                                        <span class="text-[10px] text-slate-400 font-semibold">Belum diunggah</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
<?php else: ?>
    <div class="py-12 text-center text-slate-400 bg-slate-50/50 dark:bg-slate-900/30 border border-dashed border-slate-200 dark:border-slate-700 rounded-lg">
        <i class="fa-solid fa-map-location-dot text-3xl mb-2 text-slate-300 dark:text-slate-600"></i>
        <h4 class="text-xs font-bold text-slate-900 dark:text-slate-100">Belum Ada Data Survey</h4>
        <p class="text-[11px] text-slate-500 mt-1">Silakan isi hasil survey lapangan melalui tombol di atas.</p>
    </div>
<?php endif; ?>
<?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/customers/tabs/_survey.blade.php ENDPATH**/ ?>