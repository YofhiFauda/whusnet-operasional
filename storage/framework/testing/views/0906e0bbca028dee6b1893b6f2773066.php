

<?php
    $tech = $customer->customerTechnicalDetail;
    $latestInst = $customer->installations()->latest()->first();

    // Material TERPAKAI = realisasi pemasangan (beda dari ESTIMASI di tab Survey).
    // Alat kerja difilter ke FopTask PSB; task_work_tools tidak punya kolom kind.
    $materialTerpakai = \App\Models\TaskMaterial::where('customer_id', $customer->id)
        ->terpakai()->orderBy('id')->get();

    $alatPemasangan = \App\Models\TaskWorkTool::where('customer_id', $customer->id)
        ->whereHas('fopTask', fn ($q) => $q->where('category', \App\Enums\TaskType::PEMASANGAN->value))
        ->orderBy('id')->get();

    $hasTestReport = $tech && ($tech->test_date || $tech->test_download !== null || $tech->test_upload !== null || $tech->latency_ms !== null);
?>

<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between pb-4 border-b border-slate-200 dark:border-slate-700">
    <div>
        <h3 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">Hasil Laporan Pemasangan Perangkat &amp; Laporan Uji Speed Test</h3>
        <p class="text-[11px] text-slate-500 mt-0.5">Jadwal, tim teknisi, material terpakai, alat kerja, foto berita acara, dan metrik hasil pengujian layanan.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2 shrink-0">
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('customers.detail.installation.update')): ?>
            <button type="button" onclick="openTestReportModal()" class="inline-flex items-center gap-1.5 px-3.5 py-2 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/50 text-slate-700 dark:text-slate-300 rounded-lg transition-colors text-xs font-semibold shadow-sm cursor-pointer">
                <i class="fa-solid fa-gauge-high"></i>
                Isi Laporan Uji (Speedtest)
            </button>
            <button type="button" onclick="openInstallationModal()" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg transition-colors text-xs font-semibold shadow-sm cursor-pointer">
                <i class="fa-solid fa-pen-to-square"></i>
                Isi Data Pemasangan
            </button>
        <?php endif; ?>

        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('customers.detail.installation.reject')): ?>
            <?php if(in_array($customer->status, ['waiting_installation', 'installation_in_progress', 'revision_installation'])): ?>
                <button type="button" onclick="document.getElementById('cancel-installation-modal').classList.remove('hidden')"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg border bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 border-rose-200 dark:border-rose-800 hover:bg-rose-100 dark:hover:bg-rose-950/60 transition-colors cursor-pointer">
                    <i class="fa-solid fa-xmark"></i>
                    Batalkan Pemasangan
                </button>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('customers.detail.installation.reject')): ?>
<?php if(in_array($customer->status, ['waiting_installation', 'installation_in_progress', 'revision_installation'])): ?>
<div id="cancel-installation-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-xl w-full max-w-md p-5">
        <h4 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider mb-1">Batalkan Pemasangan — Tidak Layak Lanjut</h4>
        <p class="text-[11px] text-slate-500 mb-4">Pelanggan akan diubah statusnya menjadi <strong>ditolak</strong> dan tidak bisa lanjut ke tahap aktivasi. Tindakan ini tidak bisa dibatalkan.</p>
        <form action="<?php echo e(route('customers.installation.cancel', $customer)); ?>" method="POST">
            <?php echo csrf_field(); ?>
            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Alasan <span class="text-rose-600">*</span></label>
            <textarea name="reason" rows="3" required
                      class="w-full text-xs border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 mb-4 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200"
                      placeholder="Contoh: Pelanggan menolak pemasangan, lokasi tidak sesuai, dll."></textarea>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('cancel-installation-modal').classList.add('hidden')"
                        class="px-4 py-2 text-xs font-semibold rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 cursor-pointer">Batal</button>
                <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg text-white bg-rose-600 hover:bg-rose-700 shadow-sm cursor-pointer">Ya, Batalkan Pemasangan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if($customer->installations->count() > 0): ?>
    <div class="space-y-6">
        <?php $__currentLoopData = $customer->installations()->latest()->get(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $installation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $statusLabel = match($installation->installation_status) {
                    'completed' => 'Terpasang & Aktif',
                    'failed' => 'Gagal',
                    'in_progress' => 'Proses',
                    'scheduled' => 'Terjadwal',
                    default => $installation->installation_status,
                };
                $statusBadge = match($installation->installation_status) {
                    'completed' => 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
                    'failed' => 'bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 border-rose-200 dark:border-rose-800',
                    'in_progress' => 'bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 border-sky-200 dark:border-sky-800',
                    'scheduled' => 'bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 border-amber-200 dark:border-amber-800',
                    default => 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-600',
                };

                $timTeknisi = collect([
                    $installation->technicians ?: ($installation->technician->name ?? null),
                    $installation->technician2->name ?? null,
                    $installation->technician3->name ?? null,
                ])->filter()->implode(', ');

                $tglJadwal = $installation->scheduled_date ? \App\Support\IndonesianDate::date($installation->scheduled_date) : '-';
                $tglSelesai = $installation->finished_date ? \App\Support\IndonesianDate::date($installation->finished_date) : $tglJadwal;
            ?>

            <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
                <div class="px-5 py-3.5 bg-slate-50/50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700 flex flex-wrap gap-2 justify-between items-center">
                    <span class="text-xs font-bold text-slate-900 dark:text-slate-100">Pemasangan — <?php echo e($tglJadwal); ?></span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wide <?php echo e($statusBadge); ?>"><?php echo e($statusLabel); ?></span>
                </div>

                <div class="p-5 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
                        <div class="space-y-2.5">
                            <div class="flex justify-between gap-3 py-1 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">ID FOP Penugasan Pemasangan</span>
                                <span class="font-mono font-bold text-sky-600 dark:text-sky-400 searchable-text"><?php echo e($installation->fop_id ?: '-'); ?></span>
                            </div>
                            <div class="flex justify-between gap-3 py-1 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Waktu Penugasan FOP Pasang</span>
                                <span class="font-mono font-semibold text-slate-900 dark:text-slate-100 searchable-text">
                                    <?php echo e($installation->assigned_at ? \App\Support\IndonesianDate::dateTime($installation->assigned_at) . ' WIB' : '-'); ?>

                                </span>
                            </div>
                            <div class="flex justify-between gap-3 py-1 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Jadwal Pemasangan</span>
                                <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text">
                                    <?php echo e($tglJadwal); ?><?php echo e($installation->scheduled_time ? ' — ' . substr($installation->scheduled_time, 0, 5) . ' WIB' : ''); ?>

                                </span>
                            </div>
                            <div class="flex justify-between gap-3 py-1 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Tanggal &amp; Waktu Mulai Pasang</span>
                                <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text">
                                    <?php echo e($tglJadwal); ?> — <?php echo e($installation->start_time ? substr($installation->start_time, 0, 5) . ' WIB' : '-'); ?>

                                </span>
                            </div>
                            <div class="flex justify-between gap-3 py-1 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Tanggal &amp; Waktu Selesai Pasang</span>
                                <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text">
                                    <?php echo e($tglSelesai); ?> — <?php echo e($installation->end_time ? substr($installation->end_time, 0, 5) . ' WIB' : '-'); ?>

                                </span>
                            </div>
                        </div>

                        <div class="space-y-2.5">
                            <div class="flex justify-between gap-3 py-1 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Teknisi 1 (Utama)</span>
                                <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($installation->technicians ?: ($installation->technician->name ?? '-')); ?></span>
                            </div>
                            <div class="flex justify-between gap-3 py-1 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Teknisi Bertugas (Tim)</span>
                                <span class="font-semibold text-slate-900 dark:text-slate-100 text-right searchable-text"><?php echo e($timTeknisi ?: '-'); ?></span>
                            </div>
                            <div class="flex justify-between gap-3 py-1 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Status Pengujian Uji Coba</span>
                                <span class="font-bold searchable-text <?php echo e($hasTestReport ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'); ?>">
                                    <?php echo e($hasTestReport ? 'Lolos Pengujian Uji Coba' : 'Belum Ada Laporan Uji'); ?>

                                </span>
                            </div>
                            <div class="pt-1">
                                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Catatan Pemasangan</span>
                                <p class="p-3 bg-slate-50 dark:bg-slate-900/50 rounded border border-slate-200 dark:border-slate-700 italic text-slate-600 dark:text-slate-300 searchable-text">
                                    <?php echo e($installation->installation_note ?: 'Tidak ada catatan'); ?>

                                </p>
                            </div>
                        </div>
                    </div>

                    
                    <div>
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">
                            <i class="fa-solid fa-boxes-stacked text-sky-600 mr-1"></i> 1. TABEL PERANGKAT PASIF &amp; MATERIAL TERPAKAI AKTUAL
                        </span>
                        <?php if($materialTerpakai->isNotEmpty()): ?>
                            <div class="overflow-x-auto border border-slate-200 dark:border-slate-700 rounded-lg">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="bg-slate-50/60 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-700 text-[10px] font-bold text-slate-400 uppercase">
                                            <th class="px-4 py-2">Nama Barang / Perangkat Pasif</th>
                                            <th class="px-4 py-2 text-center">Jumlah (Qty)</th>
                                            <th class="px-4 py-2">Satuan</th>
                                            <th class="px-4 py-2">Keterangan Pemasangan</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700 font-mono">
                                        <?php $__currentLoopData = $materialTerpakai; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $material): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
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
                                Belum ada material terpakai yang dicatat teknisi pemasangan.
                            </div>
                        <?php endif; ?>
                    </div>

                    
                    <div>
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">
                            <i class="fa-solid fa-screwdriver-wrench text-indigo-600 mr-1"></i> 2. TABEL ALAT KERJA YANG DIBAWA &amp; DIGUNAKAN (TOOLS PEMASANGAN)
                        </span>
                        <?php if($alatPemasangan->isNotEmpty()): ?>
                            <div class="overflow-x-auto border border-slate-200 dark:border-slate-700 rounded-lg">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="bg-slate-50/60 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-700 text-[10px] font-bold text-slate-400 uppercase">
                                            <th class="px-4 py-2">Nama Alat Kerja (Tools Lapangan)</th>
                                            <th class="px-4 py-2">Keterangan / Penggunaan Field</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                        <?php $__currentLoopData = $alatPemasangan; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $alat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
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
                                Belum ada checklist alat kerja pada FOP Task Pemasangan pelanggan ini.
                            </div>
                        <?php endif; ?>
                    </div>

                    
                    <div>
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">FOTO DOKUMENTASI PEMASANGAN, SPEED TEST &amp; BERITA ACARA</span>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <?php $__currentLoopData = [
                                ['path' => $installation->installation_photo, 'title' => 'FOTO PEMASANGAN LAPANGAN', 'icon' => 'fa-wifi'],
                                ['path' => $installation->signature_photo, 'title' => 'FOTO TTD PELANGGAN', 'icon' => 'fa-signature'],
                                ['path' => $tech?->speedtest_photo, 'title' => 'FOTO BUKTI SPEED TEST', 'icon' => 'fa-gauge-high'],
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
        <i class="fa-solid fa-screwdriver-wrench text-3xl mb-2 text-slate-300 dark:text-slate-600"></i>
        <h4 class="text-xs font-bold text-slate-900 dark:text-slate-100">Belum Ada Data Pemasangan</h4>
        <p class="text-[11px] text-slate-500 mt-1">Silakan isi jadwal atau hasil pemasangan melalui tombol di atas.</p>
    </div>
<?php endif; ?>


<?php if($hasTestReport): ?>
    <?php
        $paketTarget = (float) ($customer->internetPackage->download_speed_mbps ?? 0);
        $conformity = $tech->speed_conformity_percent;
        if ($conformity === null && $paketTarget > 0 && $tech->test_download !== null) {
            $conformity = round(((float) $tech->test_download / $paketTarget) * 100, 1);
        }
        $conformityBadge = $conformity === null
            ? 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-600'
            : ($conformity >= 80
                ? 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800'
                : ($conformity >= 50
                    ? 'bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 border-amber-200 dark:border-amber-800'
                    : 'bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 border-rose-200 dark:border-rose-800'));
    ?>

    <div class="p-4 bg-slate-50/70 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 rounded-lg space-y-3">
        <div class="flex flex-wrap gap-2 items-center justify-between pb-2 border-b border-slate-200 dark:border-slate-700">
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">LAPORAN UJI HASIL SPEEDTEST (QUALITY REPORT)</span>
                <span class="text-xs font-bold text-slate-900 dark:text-slate-100">
                    Waktu Uji: <?php echo e($tech->test_date ? \App\Support\IndonesianDate::date($tech->test_date) : '-'); ?><?php echo e($tech->test_time ? ' — ' . substr($tech->test_time, 0, 5) . ' WIB' : ''); ?>

                </span>
            </div>
            <span class="text-[10px] font-mono font-bold px-2.5 py-1 rounded border <?php echo e($conformityBadge); ?>">
                <?php if($conformity !== null): ?>
                    <?php echo e(number_format($conformity, 1)); ?>% (Target Paket <?php echo e($paketTarget > 0 ? rtrim(rtrim(number_format($paketTarget, 2, '.', ''), '0'), '.') . ' Mbps' : '-'); ?> — Hasil <?php echo e($tech->test_download !== null ? number_format($tech->test_download, 2) . ' Mbps' : '-'); ?>)
                <?php else: ?>
                    Kesesuaian paket belum bisa dihitung
                <?php endif; ?>
            </span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 font-mono text-xs">
            <div class="p-3 bg-white dark:bg-slate-800 rounded border border-slate-200 dark:border-slate-700">
                <span class="text-[10px] text-slate-400 font-sans block">Speed Download</span>
                <span class="text-base font-bold text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($tech->test_download !== null ? number_format($tech->test_download, 2) . ' Mbps' : '-'); ?></span>
            </div>
            <div class="p-3 bg-white dark:bg-slate-800 rounded border border-slate-200 dark:border-slate-700">
                <span class="text-[10px] text-slate-400 font-sans block">Speed Upload</span>
                <span class="text-base font-bold text-slate-900 dark:text-slate-100 searchable-text"><?php echo e($tech->test_upload !== null ? number_format($tech->test_upload, 2) . ' Mbps' : '-'); ?></span>
            </div>
            <div class="p-3 bg-white dark:bg-slate-800 rounded border border-slate-200 dark:border-slate-700">
                <span class="text-[10px] text-slate-400 font-sans block">Latency (Ping) / Jitter</span>
                <span class="text-base font-bold text-slate-900 dark:text-slate-100 searchable-text">
                    <?php echo e($tech->latency_ms !== null ? number_format($tech->latency_ms, 1) . 'ms' : '-'); ?> / <?php echo e($tech->jitter_ms !== null ? number_format($tech->jitter_ms, 1) . 'ms' : '-'); ?>

                </span>
            </div>
            <div class="p-3 bg-white dark:bg-slate-800 rounded border border-slate-200 dark:border-slate-700">
                <span class="text-[10px] text-slate-400 font-sans block">Packet Loss / Rating</span>
                <span class="text-base font-bold text-emerald-600 dark:text-emerald-400 searchable-text">
                    <?php echo e($tech->packet_loss_percent !== null ? number_format($tech->packet_loss_percent, 2) . '%' : '0.00%'); ?> (<?php echo e($tech->quality_score ? $tech->quality_score . '/5 ★' : '—'); ?>)
                </span>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-x-6 gap-y-1 text-xs text-slate-600 dark:text-slate-300 pt-2 border-t border-slate-200 dark:border-slate-700">
            <span>Sinyal Redaman Awal Pemasangan: <strong class="font-mono text-slate-900 dark:text-slate-100"><?php echo e($tech->initial_attenuation ?: '-'); ?> dBm</strong></span>
            <span>Redaman Terkini (Aktual): <strong class="font-mono text-emerald-600 dark:text-emerald-400"><?php echo e($tech->actual_attenuation ?: '-'); ?> dBm</strong></span>
            <span>Skor Kualitas Uji: <strong class="font-bold text-emerald-600 dark:text-emerald-400"><?php echo e($tech->quality_score ? $tech->quality_score . '/5' : '-'); ?></strong></span>
        </div>
    </div>
<?php endif; ?>

<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('fill_installation')): ?>
<div id="installation-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700 w-full max-w-2xl overflow-hidden max-h-[90vh] flex flex-col">
        <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between shrink-0">
            <h3 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">Input Data Pemasangan</h3>
            <button type="button" onclick="closeInstallationModal()" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>
        <form action="<?php echo e(route('customers.installation.store', $customer->id)); ?>" method="POST" enctype="multipart/form-data" class="overflow-y-auto">
            <?php echo csrf_field(); ?>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Status Pemasangan</label>
                    <div class="flex flex-wrap gap-4">
                        <?php $__currentLoopData = ['scheduled' => 'Terjadwal', 'in_progress' => 'Proses', 'completed' => 'Selesai', 'failed' => 'Gagal']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <label class="inline-flex items-center">
                                <input type="radio" name="installation_status" value="<?php echo e($value); ?>" class="accent-sky-600"
                                       <?php echo e(($latestInst && $latestInst->installation_status === $value) || (! $latestInst && $value === 'scheduled') ? 'checked' : ''); ?>>
                                <span class="ml-2 text-xs text-slate-800 dark:text-slate-200"><?php echo e($label); ?></span>
                            </label>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>

                <div>
                    <label for="scheduled_date" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Tanggal Jadwal</label>
                    <input type="date" name="scheduled_date" id="scheduled_date" value="<?php echo e($latestInst && $latestInst->scheduled_date ? $latestInst->scheduled_date->format('Y-m-d') : date('Y-m-d')); ?>" required
                           class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>

                <div>
                    <label for="scheduled_time" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Jam Jadwal</label>
                    <input type="time" name="scheduled_time" id="scheduled_time" value="<?php echo e($latestInst && $latestInst->scheduled_time ? substr($latestInst->scheduled_time, 0, 5) : '09:00'); ?>"
                           class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>

                <div>
                    <label for="start_time" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Waktu Mulai Pemasangan</label>
                    <input type="time" name="start_time" id="start_time" value="<?php echo e($latestInst && $latestInst->start_time ? substr($latestInst->start_time, 0, 5) : ''); ?>"
                           class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>

                <div>
                    <label for="end_time" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Waktu Selesai Pemasangan</label>
                    <input type="time" name="end_time" id="end_time" value="<?php echo e($latestInst && $latestInst->end_time ? substr($latestInst->end_time, 0, 5) : ''); ?>"
                           class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>

                <div>
                    <label for="finished_date" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Tanggal Selesai (Aktual)</label>
                    <input type="date" name="finished_date" id="finished_date" value="<?php echo e($latestInst && $latestInst->finished_date ? $latestInst->finished_date->format('Y-m-d') : ''); ?>"
                           class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>

                <div>
                    <label for="fop_id_inst" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">ID FOP / Penugasan</label>
                    <input type="text" name="fop_id" id="fop_id_inst" placeholder="TFOP-2026-...." value="<?php echo e($latestInst ? $latestInst->fop_id : ''); ?>"
                           class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>

                <div>
                    <label for="assigned_at_inst" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Waktu Penugasan Pemasangan</label>
                    <input type="datetime-local" name="assigned_at" id="assigned_at_inst" value="<?php echo e($latestInst && $latestInst->assigned_at ? $latestInst->assigned_at->format('Y-m-d\TH:i') : ''); ?>"
                           class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>

                <div>
                    <label for="installation_technician_id" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Teknisi 1 (Utama)</label>
                    <select name="technician_id" id="installation_technician_id" required
                            class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-200">
                        <option value="<?php echo e(auth()->id()); ?>" <?php echo e($latestInst && $latestInst->technician_id == auth()->id() ? 'selected' : ''); ?>><?php echo e(auth()->user()->name); ?> (Saya)</option>
                        <?php if(isset($activeUsers)): ?>
                            <?php $__currentLoopData = $activeUsers->where('id', '!=', auth()->id()); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($u->id); ?>" <?php echo e($latestInst && $latestInst->technician_id == $u->id ? 'selected' : ''); ?>><?php echo e($u->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div>
                    <label for="technician_2_id" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Teknisi 2</label>
                    <select name="technician_2_id" id="technician_2_id"
                            class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-200">
                        <option value="">— Tidak ada —</option>
                        <?php if(isset($activeUsers)): ?>
                            <?php $__currentLoopData = $activeUsers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($u->id); ?>" <?php echo e($latestInst && $latestInst->technician_2_id == $u->id ? 'selected' : ''); ?>><?php echo e($u->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div>
                    <label for="technician_3_id" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Teknisi 3</label>
                    <select name="technician_3_id" id="technician_3_id"
                            class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-200">
                        <option value="">— Tidak ada —</option>
                        <?php if(isset($activeUsers)): ?>
                            <?php $__currentLoopData = $activeUsers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($u->id); ?>" <?php echo e($latestInst && $latestInst->technician_3_id == $u->id ? 'selected' : ''); ?>><?php echo e($u->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label for="technicians" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Nama Tim Teknisi (Opsional)</label>
                    <input type="text" name="technicians" id="technicians" placeholder="Contoh: Tim Pemasangan - Budi, Andi" value="<?php echo e($latestInst ? $latestInst->technicians : ''); ?>"
                           class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-200">
                </div>

                <div class="md:col-span-2">
                    <label for="installation_note" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Catatan Pemasangan</label>
                    <textarea name="installation_note" id="installation_note" rows="3" placeholder="Tuliskan catatan pemasangan pelanggan..."
                              class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-200"><?php echo e($latestInst ? $latestInst->installation_note : ''); ?></textarea>
                </div>

                <div class="md:col-span-2">
                    <label for="installation_photo" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Foto Pemasangan</label>
                    <input type="file" name="installation_photo" id="installation_photo" accept="image/*" capture="environment"
                           class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-200">
                    <p class="text-[9px] text-slate-400 mt-1 italic">Format JPG/PNG, maksimal 2MB.</p>
                </div>
            </div>
            <div class="px-5 py-3.5 bg-slate-50 dark:bg-slate-900/50 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-2 text-xs">
                <button type="button" onclick="closeInstallationModal()" class="px-4 py-2 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 font-semibold rounded-lg shadow-sm cursor-pointer">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white font-semibold rounded-lg shadow-sm cursor-pointer">
                    Simpan Data Pemasangan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Input Laporan Uji (Speedtest) -->
<div id="test-report-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700 w-full max-w-2xl overflow-hidden max-h-[90vh] flex flex-col">
        <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between shrink-0">
            <h3 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">Laporan Hasil Pengujian Layanan (Speedtest)</h3>
            <button type="button" onclick="closeTestReportModal()" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>
        <form action="<?php echo e(route('customers.test-report.store', $customer->id)); ?>" method="POST" enctype="multipart/form-data" class="overflow-y-auto">
            <?php echo csrf_field(); ?>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <div>
                    <label for="test_date" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Tanggal Uji</label>
                    <input type="date" name="test_date" id="test_date" value="<?php echo e(date('Y-m-d')); ?>" required
                           class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>

                <div>
                    <label for="test_time" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Jam Uji</label>
                    <input type="time" name="test_time" id="test_time" value="<?php echo e(date('H:i')); ?>"
                           class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>

                <div>
                    <label for="test_download" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Speed Download (Mbps)</label>
                    <input type="number" step="0.01" name="test_download" id="test_download" required
                           class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>

                <div>
                    <label for="test_upload" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Speed Upload (Mbps)</label>
                    <input type="number" step="0.01" name="test_upload" id="test_upload" required
                           class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>

                <div>
                    <label for="latency_ms" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Latency (Ping ms)</label>
                    <input type="number" step="0.1" name="latency_ms" id="latency_ms"
                           class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>

                <div>
                    <label for="jitter_ms" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Jitter (ms)</label>
                    <input type="number" step="0.1" name="jitter_ms" id="jitter_ms"
                           class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>

                <div>
                    <label for="packet_loss_percent" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Packet Loss (%)</label>
                    <input type="number" step="0.01" name="packet_loss_percent" id="packet_loss_percent"
                           class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>

                <div>
                    <label for="quality_score" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Skor Kualitas (1-5)</label>
                    <select name="quality_score" id="quality_score"
                            class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-200">
                        <option value="5">⭐⭐⭐⭐⭐ (Sangat Bagus)</option>
                        <option value="4">⭐⭐⭐⭐ (Bagus)</option>
                        <option value="3" selected>⭐⭐⭐ (Cukup)</option>
                        <option value="2">⭐⭐ (Kurang)</option>
                        <option value="1">⭐ (Buruk)</option>
                    </select>
                </div>

                <div>
                    <label for="initial_attenuation" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Redaman Awal (dBm)</label>
                    <input type="text" name="initial_attenuation" id="initial_attenuation" placeholder="-19.50"
                           class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>

                <div>
                    <label for="actual_attenuation" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Redaman Aktual (dBm)</label>
                    <input type="text" name="actual_attenuation" id="actual_attenuation" placeholder="-21.20"
                           class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>

                <div class="md:col-span-2">
                    <label for="speedtest_photo" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Foto Hasil Speedtest</label>
                    <input type="file" name="speedtest_photo" id="speedtest_photo" accept="image/*" capture="environment"
                           class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-200">
                    <p class="text-[9px] text-slate-400 mt-1 italic">Format JPG/PNG, maksimal 2MB.</p>
                </div>
            </div>
            <div class="px-5 py-3.5 bg-slate-50 dark:bg-slate-900/50 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-2 text-xs">
                <button type="button" onclick="closeTestReportModal()" class="px-4 py-2 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 font-semibold rounded-lg shadow-sm cursor-pointer">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white font-semibold rounded-lg shadow-sm cursor-pointer">
                    Simpan Laporan Hasil Uji
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/customers/tabs/_installation.blade.php ENDPATH**/ ?>