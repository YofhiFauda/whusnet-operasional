<div class="space-y-6">
    <!-- Header Summary -->
    <div class="border border-border rounded-xl p-5 bg-gradient-to-r from-surface-muted/30 via-surface to-surface-muted/30 shadow-sm flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-sky-100 text-sky-600 flex items-center justify-center font-bold text-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-text-main">List Riwayat Perbaruan Data Pelanggan</h3>
            </div>
            <p class="text-xs text-text-muted mt-1.5">
                Setiap perubahan data (seperti update paket internet, informasi kontak, alamat, atau perangkat) dicatat secara terperinci mencakup nilai sebelum dan sesudah, pelaku pembaruan, serta waktu kejadian.
            </p>
        </div>

        <?php
            $activationDate = $customer->customerService?->activation_date ? \Carbon\Carbon::parse($customer->customerService->activation_date) : null;
        ?>

        <?php if($activationDate): ?>
        <div class="bg-sky-50/10 border border-sky-500/20 rounded-lg px-3.5 py-2.5 text-right shrink-0">
            <span class="block text-[10px] font-bold text-sky-500 uppercase tracking-wider">Tanggal Aktivasi Layanan</span>
            <span class="text-xs font-bold text-sky-400"><?php echo e(\App\Support\IndonesianDate::date($activationDate)); ?></span>
            <span class="block text-[10px] text-sky-500 mt-0.5 font-medium">Perubahan setelah tanggal ini berstatus Pasca Aktivasi</span>
        </div>
        <?php elseif($customer->status === 'active'): ?>
        <div class="bg-emerald-50/10 border border-emerald-500/20 rounded-lg px-3.5 py-2.5 text-right shrink-0">
            <span class="block text-[10px] font-bold text-emerald-500 uppercase tracking-wider">Status Layanan</span>
            <span class="text-xs font-bold text-emerald-500">Aktif Beroperasi</span>
        </div>
        <?php endif; ?>
    </div>

    <?php
        // Master reference tables for human-readable resolving
        $packageMap = \App\Models\InternetPackage::pluck('name', 'id')->toArray();
        $popMap = \App\Models\Pop::pluck('name', 'id')->toArray();
        $cityMap = \App\Models\City::pluck('name', 'id')->toArray();
        $districtMap = \App\Models\District::pluck('name', 'id')->toArray();
        $villageMap = \App\Models\Village::pluck('name', 'id')->toArray();

        // Field label dictionary
        $fieldDictionary = [
            'full_name' => 'Nama Lengkap Pelanggan',
            'identity_number' => 'No. Identitas (KTP/SIM)',
            'gender' => 'Jenis Kelamin',
            'phone' => 'No. Telepon / WhatsApp',
            'primary_phone' => 'No. Telepon Utama',
            'alternative_phone' => 'No. Telepon Alternatif',
            'email' => 'Alamat Email',
            'customer_code' => 'Kode Pelanggan / ID Req',
            'cid' => 'Customer ID (CID)',
            'status' => 'Status Pelanggan',
            'customer_status' => 'Status Pelanggan',
            'registration_date' => 'Tanggal Registrasi',
            'address' => 'Alamat Rumah / Jalan',
            'full_address' => 'Alamat Lengkap',
            'rt' => 'RT',
            'rw' => 'RW',
            'postal_code' => 'Kode Pos',
            'pop_id' => 'POP / Cabang Area',
            'distribution_id' => 'ODP / Distribusi Area',
            'city_id' => 'Kota / Kabupaten',
            'district_id' => 'Kecamatan',
            'village_id' => 'Desa / Kelurahan',
            'latitude' => 'Koordinat Latitude',
            'longitude' => 'Koordinat Longitude',
            'internet_package_id' => 'Paket Internet',
            'package_name_snapshot' => 'Nama Paket Layanan',
            'monthly_price' => 'Biaya Bulanan',
            'discount' => 'Potongan Diskon',
            'discount_amount' => 'Nominal Diskon',
            'ppn' => 'PPN (%)',
            'tax_percent' => 'Pajak PPN (%)',
            'other_fee' => 'Biaya Tambahan Lainnya',
            'total_monthly_bill' => 'Total Tagihan Bulanan',
            'contract_period_months' => 'Masa Kontrak (Bulan)',
            'download_speed_mbps' => 'Kecepatan Download',
            'upload_speed_mbps' => 'Kecepatan Upload',
            'activation_date' => 'Tanggal Aktivasi Layanan',
            'due_date' => 'Tanggal Jatuh Tempo',
            'billing_cycle' => 'Siklus Tagihan Bulanan',
            'service_status' => 'Status Aktif Layanan',
            'billing_status' => 'Status Tagihan / Billing',
            'ont_sn' => 'Serial Number (SN) ONT/Modem',
            'ont_serial_number' => 'Serial Number (SN) ONT/Modem',
            'ont_mac_address' => 'MAC Address ONT/Modem',
            'ip_address' => 'IP Address / Static IP',
            'odp_code' => 'Kode ODP / Kotak Distribusi',
            'odp_port' => 'Port ODP',
            'olt_code' => 'Kode OLT / Perangkat Pusat',
            'vlan_id' => 'VLAN ID Network',
            'modem_sn' => 'Serial Number Router',
            'router_type' => 'Tipe Perangkat Router',
            'sales_code' => 'Kode Sales',
            'agent_code' => 'Kode Agent / Mitra',
            'referral_customer_code' => 'Kode Referral Pelanggan',
            'note' => 'Catatan Tambahan',
            'notes' => 'Catatan Tambahan',
        ];

        // Ignored system fields
        $ignoredFields = ['id', 'customer_id', 'created_at', 'updated_at', 'updated_by', 'remember_token', 'password'];

        $formatValue = function($key, $value) use ($packageMap, $popMap, $cityMap, $districtMap, $villageMap) {
            if (is_null($value) || $value === '') return '- (Kosong)';
            if (is_bool($value)) return $value ? 'Ya' : 'Tidak';
            if (is_array($value)) return json_encode($value);

            if ($key === 'internet_package_id') return $packageMap[$value] ?? "ID #$value";
            if ($key === 'pop_id') return $popMap[$value] ?? "ID #$value";
            if ($key === 'city_id') return $cityMap[$value] ?? "ID #$value";
            if ($key === 'district_id') return $districtMap[$value] ?? "ID #$value";
            if ($key === 'village_id') return $villageMap[$value] ?? "ID #$value";

            if (in_array($key, ['monthly_price', 'discount', 'discount_amount', 'other_fee', 'total_monthly_bill'])) {
                if (is_numeric($value)) {
                    return 'Rp ' . number_format((float)$value, 0, ',', '.');
                }
            }

            if (in_array($key, ['download_speed_mbps', 'upload_speed_mbps'])) {
                if (is_numeric($value)) return $value . ' Mbps';
            }

            if (in_array($key, ['status', 'customer_status', 'service_status'])) {
                return match(strtolower((string)$value)) {
                    'active', 'aktif' => 'Aktif (Beroperasi)',
                    'suspended', 'isolir' => 'Terisolir / Suspend',
                    'terminated', 'berhenti' => 'Terminated (Putus Langganan)',
                    'waiting_survey', 'survey' => 'Menunggu Survey',
                    'waiting_installation', 'menunggu_pemasangan' => 'Menunggu Pemasangan',
                    'registered', 'calon_pelanggan' => 'Calon Pelanggan / Terdaftar',
                    default => ucfirst((string)$value)
                };
            }

            return (string)$value;
        };

        // Determine category of change
        $determineCategory = function($changedKeys) {
            $packageKeys = ['internet_package_id', 'package_name_snapshot', 'monthly_price', 'discount_amount', 'tax_percent', 'other_fee', 'total_monthly_bill', 'download_speed_mbps', 'upload_speed_mbps'];
            $techKeys = ['ont_sn', 'ont_serial_number', 'ip_address', 'odp_code', 'olt_code', 'vlan_id', 'modem_sn', 'router_type'];
            $contactKeys = ['full_name', 'phone', 'primary_phone', 'alternative_phone', 'email', 'identity_number'];
            $addressKeys = ['address', 'full_address', 'rt', 'rw', 'postal_code', 'city_id', 'district_id', 'village_id', 'pop_id', 'latitude', 'longitude'];
            $statusKeys = ['status', 'customer_status', 'service_status', 'billing_status'];

            $hasPackage = count(array_intersect($changedKeys, $packageKeys)) > 0;
            $hasTech = count(array_intersect($changedKeys, $techKeys)) > 0;
            $hasContact = count(array_intersect($changedKeys, $contactKeys)) > 0;
            $hasAddress = count(array_intersect($changedKeys, $addressKeys)) > 0;
            $hasStatus = count(array_intersect($changedKeys, $statusKeys)) > 0;

            if ($hasPackage) {
                return ['label' => 'Perbaruan Paket Layanan & Biaya', 'icon' => '📦', 'badge_class' => 'bg-sky-50/10 text-sky-500 border-sky-500/20'];
            }
            if ($hasTech) {
                return ['label' => 'Perbaruan Perangkat & Teknis Jaringan', 'icon' => '🔧', 'badge_class' => 'bg-teal-50/10 text-teal-500 border-teal-500/20'];
            }
            if ($hasContact || $hasAddress) {
                return ['label' => 'Perbaruan Profil & Alamat Pelanggan', 'icon' => '👤', 'badge_class' => 'bg-blue-50/10 text-blue-500 border-blue-500/20'];
            }
            if ($hasStatus) {
                return ['label' => 'Perbaruan Status Pelanggan', 'icon' => '🔄', 'badge_class' => 'bg-emerald-50/10 text-emerald-500 border-emerald-500/20'];
            }
            return ['label' => 'Perbaruan Data Pelanggan', 'icon' => '📝', 'badge_class' => 'bg-purple-50/10 text-purple-500 border-purple-500/20'];
        };

        // Group updates by (timestamp_minute + user_id) so simultaneous edits merge cleanly
        $groupedUpdates = [];
        $creationLog = null;

        foreach ($auditLogs as $alog) {
            if ($alog->action === 'create') {
                if (!$creationLog || ($alog->created_at && $alog->created_at->lt($creationLog['created_at']))) {
                    $creationLog = [
                        'user_name' => $alog->user ? $alog->user->name : 'Petugas Registrasi',
                        'user_role' => $alog->user ? ($alog->user->role?->name ?? 'Petugas') : 'System',
                        'created_at' => $alog->created_at ?: $customer->created_at,
                    ];
                }
                continue;
            }

            if ($alog->action !== 'update') continue;

            $oldValues = $alog->old_values ?? [];
            $newValues = $alog->new_values ?? [];

            // Extract real changed keys
            $changedItems = [];
            foreach ($newValues as $k => $newVal) {
                if (in_array($k, $ignoredFields)) continue;
                $oldVal = $oldValues[$k] ?? null;

                // Compare formatted string to ignore loose type differences
                $formattedOld = $formatValue($k, $oldVal);
                $formattedNew = $formatValue($k, $newVal);

                if ($formattedOld !== $formattedNew) {
                    $label = $fieldDictionary[$k] ?? ucwords(str_replace('_', ' ', $k));
                    $changedItems[$label] = [
                        'key' => $k,
                        'label' => $label,
                        'old' => $formattedOld,
                        'new' => $formattedNew,
                    ];
                }
            }

            if (empty($changedItems)) continue;

            $timeGroup = $alog->created_at ? $alog->created_at->format('Y-m-d H:i') : 'Unknown';
            $userId = $alog->user_id ?: 'system';
            $groupKey = $timeGroup . '_' . $userId;

            $isPostActivation = false;
            if ($activationDate && $alog->created_at && $alog->created_at->gte($activationDate)) {
                $isPostActivation = true;
            } elseif (!$activationDate && in_array($customer->status, ['active', 'suspended', 'terminated'])) {
                $isPostActivation = true;
            }

            if (!isset($groupedUpdates[$groupKey])) {
                $groupedUpdates[$groupKey] = [
                    'group_key' => $groupKey,
                    'created_at' => $alog->created_at,
                    'user_name' => $alog->user ? $alog->user->name : 'System Automasi',
                    'user_role' => $alog->user ? ($alog->user->role?->name ?? 'Petugas') : 'System',
                    'is_post_activation' => $isPostActivation,
                    'changes' => [],
                ];
            }

            // Merge items avoiding duplicates
            foreach ($changedItems as $lbl => $itemData) {
                $groupedUpdates[$groupKey]['changes'][$lbl] = $itemData;
            }
        }

        // Also merge customer status transitions from CustomerStatusLog
        foreach ($statusLogs as $slog) {
            $timeGroup = $slog->created_at ? $slog->created_at->format('Y-m-d H:i') : 'Unknown';
            $userId = $slog->user_id ?: 'system';
            $groupKey = $timeGroup . '_' . $userId;

            $oldFormatted = $formatValue('status', $slog->from_status);
            $newFormatted = $formatValue('status', $slog->to_status);

            if ($oldFormatted !== $newFormatted) {
                if (!isset($groupedUpdates[$groupKey])) {
                    $isPostActivation = ($activationDate && $slog->created_at && $slog->created_at->gte($activationDate)) || (!$activationDate && in_array($customer->status, ['active', 'suspended', 'terminated']));
                    $groupedUpdates[$groupKey] = [
                        'group_key' => $groupKey,
                        'created_at' => $slog->created_at,
                        'user_name' => $slog->user ? $slog->user->name : 'System Automasi',
                        'user_role' => $slog->user ? ($slog->user->role?->name ?? 'Petugas') : 'System',
                        'is_post_activation' => $isPostActivation,
                        'changes' => [],
                    ];
                }
                $groupedUpdates[$groupKey]['changes']['Status Pelanggan'] = [
                    'key' => 'status',
                    'label' => 'Status Pelanggan',
                    'old' => $oldFormatted,
                    'new' => $newFormatted . ($slog->note ? " (Catatan: {$slog->note})" : ''),
                ];
            }
        }

        // Sort descending by created_at
        usort($groupedUpdates, function($a, $b) {
            return ($b['created_at']?->timestamp ?? 0) <=> ($a['created_at']?->timestamp ?? 0);
        });
    ?>

    <?php if(empty($groupedUpdates)): ?>
        <div class="py-12 text-center text-text-muted border border-border rounded-xl bg-surface-muted/20">
            <svg class="mx-auto h-12 w-12 text-border mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h4 class="text-sm font-semibold text-text-main">Belum Ada Perbaruan Data</h4>
            <p class="text-xs text-text-muted mt-1 max-w-md mx-auto">
                Sejak pelanggan ini didaftarkan, belum ada aktivitas perubahan data (seperti penggantian paket, update alamat, atau ganti perangkat) yang tercatat oleh sistem.
            </p>
        </div>
    <?php else: ?>
        <!-- Timeline List Perbaruan Data -->
        <div class="relative pl-6 border-l-2 border-primary/20 space-y-6 ml-2 my-4">
            <?php $__currentLoopData = $groupedUpdates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    $changedKeysList = array_map(fn($item) => $item['key'], $group['changes']);
                    $categoryInfo = $determineCategory($changedKeysList);
                ?>

                <div class="relative group">
                    <!-- Timeline Bullet Indicator -->
                    <div class="absolute -left-[31px] top-1.5 w-6 h-6 rounded-full border-2 border-surface flex items-center justify-center shadow-sm <?php echo e($group['is_post_activation'] ? 'bg-sky-600 text-white' : 'bg-slate-500 text-white'); ?>">
                        <span class="text-xs leading-none"><?php echo e($categoryInfo['icon']); ?></span>
                    </div>

                    <!-- Card List Item -->
                    <div class="border border-border rounded-xl bg-surface shadow-sm overflow-hidden transition-all hover:shadow-md hover:border-border/80">
                        <!-- Card Header Bar -->
                        <div class="px-5 py-3.5 bg-surface-muted/50 border-b border-border flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5 flex-wrap">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold border <?php echo e($categoryInfo['badge_class']); ?>">
                                    <?php echo e($categoryInfo['icon']); ?> <?php echo e($categoryInfo['label']); ?>

                                </span>
                            </div>

                            <div class="flex items-center gap-2 text-xs">
                                <?php if($group['is_post_activation']): ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-sky-50/10 text-sky-500 border border-sky-500/20">
                                        Pasca Aktivasi
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium bg-surface-muted text-text-muted border border-border">
                                        Pra Aktivasi
                                    </span>
                                <?php endif; ?>

                                <span class="font-bold text-text-main">
                                    <?php echo e($group['created_at'] ? \Carbon\Carbon::parse($group['created_at'])->translatedFormat('d M Y, H:i') . ' WIB' : '-'); ?>

                                </span>
                            </div>
                        </div>

                        <!-- Card Body: User Info & Table of Changed Fields -->
                        <div class="p-5">
                            <div class="flex items-center justify-between gap-2 mb-3.5 pb-3 border-b border-border text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="text-text-muted">Diperbarui Oleh:</span>
                                    <span class="font-bold text-text-main bg-surface-muted px-2 py-0.5 rounded border border-border">
                                        <?php echo e($group['user_name']); ?>

                                    </span>
                                    <span class="text-text-muted font-medium">(<?php echo e($group['user_role']); ?>)</span>
                                </div>
                                <span class="text-[11px] text-text-muted font-mono">
                                    Total <?php echo e(count($group['changes'])); ?> atribut diubah
                                </span>
                            </div>

                            <!-- List of Changes Table -->
                            <div class="border border-border rounded-lg overflow-hidden">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="bg-surface-muted/80 border-b border-border text-[11px] font-bold text-text-muted">
                                            <th class="py-2.5 px-4 w-1/3">Data / Atribut yang Diperbarui</th>
                                            <th class="py-2.5 px-4 w-1/3 text-error bg-error-bg/10">Data Sebelum Perubahan</th>
                                            <th class="py-2.5 px-4 w-1/3 text-success bg-success-bg/10">Data Sesudah Perubahan</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border">
                                        <?php $__currentLoopData = $group['changes']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $change): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <tr class="hover:bg-surface-muted/50 transition-colors">
                                                <td class="py-3 px-4 font-bold text-text-main bg-surface-muted/20">
                                                    <?php echo e($change['label']); ?>

                                                </td>
                                                <td class="py-3 px-4 text-error bg-error-bg/5 font-medium break-words">
                                                    <?php echo e($change['old']); ?>

                                                </td>
                                                <td class="py-3 px-4 text-success bg-success-bg/5 font-bold break-words">
                                                    <?php echo e($change['new']); ?>

                                                </td>
                                            </tr>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    <?php endif; ?>

    <!-- Initial Creation Milestone -->
    <?php if($creationLog): ?>
        <div class="mt-8 border border-dashed border-border rounded-xl p-4 bg-surface-muted/30 flex items-center justify-between text-xs text-text-secondary">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">
                    ✨
                </div>
                <div>
                    <span class="font-bold text-text-main">Registrasi & Pembuatan Data Awal Pelanggan</span>
                    <p class="text-[11px] text-text-muted mt-0.5">Didaftarkan ke dalam sistem oleh <strong class="text-text-secondary"><?php echo e($creationLog['user_name']); ?></strong> (<?php echo e($creationLog['user_role']); ?>)</p>
                </div>
            </div>
            <div class="text-right font-mono text-[11px] text-text-muted font-semibold">
                <?php echo e($creationLog['created_at'] ? \Carbon\Carbon::parse($creationLog['created_at'])->translatedFormat('d M Y, H:i') . ' WIB' : '-'); ?>

            </div>
        </div>
    <?php endif; ?>
</div>
<?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/customers/tabs/_riwayat_perubahan.blade.php ENDPATH**/ ?>