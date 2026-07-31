<div class="flex items-center justify-between pb-4 border-b border-border">
    <div>
        <h3 class="text-sm font-bold text-text-main uppercase tracking-wider">Data Perangkat Pelanggan</h3>
        <p class="text-xs text-text-muted mt-0.5">Informasi modem, ONT, router, PPPoE, WiFi, ODP, VLAN, dan catatan teknis.</p>
    </div>
    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('customers.detail.devices.update')): ?>
        <button onclick="openDeviceModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary hover:bg-primary/90 text-white rounded-md transition-colors text-xs font-semibold shadow-sm cursor-pointer focus:outline-none">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            Isi / Ubah Perangkat
        </button>
    <?php endif; ?>
</div>

<?php
    $device = $customer->customerDevice;
    $technicalDetail = $customer->customerTechnicalDetail;
    $canViewSensitiveDeviceFields = auth()->user()->hasPermission('customers.detail.devices.view_sensitive');
    $maskSensitive = fn ($value) => $canViewSensitiveDeviceFields ? ($value ?: '-') : ($value ? '********' : '-');
    $passiveDeviceTypeLabels = [
        'splitter_odp' => 'Splitter / ODP',
        'kabel_dropcore' => 'Kabel Dropcore / FO',
        'patch_cord' => 'Patch Cord / Fast Connector',
        'media_converter' => 'Media Converter',
        'antena_radio' => 'Antena / Radio Grid',
        'aksesoris_pasang' => 'Aksesoris Pemasangan (Klem/Isolasi/Paku)',
        'lainnya' => 'Lainnya',
    ];
?>

<?php if($device): ?>
    <div class="border border-border rounded-lg overflow-hidden">
        <div class="px-5 py-3 bg-surface-muted/50 border-b border-border flex items-center justify-between">
            <span class="text-xs font-bold text-text-main">
                <?php echo e(strtoupper($device->device_type)); ?> - <?php echo e($device->brand ?: 'Merk belum diisi'); ?> <?php echo e($device->model ?: ''); ?>

            </span>
            <span class="px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wider bg-primary-soft text-primary border-primary-border">
                Data Perangkat
            </span>
        </div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
            <div class="space-y-3">
                <div class="flex justify-between border-b border-border/50 py-1">
                    <span class="text-text-muted">Jenis Perangkat</span>
                    <span class="font-semibold text-text-main"><?php echo e(strtoupper($device->device_type)); ?></span>
                </div>
                <div class="flex justify-between border-b border-border/50 py-1">
                    <span class="text-text-muted">Merk / Tipe</span>
                    <span class="font-semibold text-text-main"><?php echo e(trim(($device->brand ?? '-') . ' ' . ($device->model ?? ''))); ?></span>
                </div>
                <div class="flex justify-between border-b border-border/50 py-1">
                    <span class="text-text-muted">Serial Number</span>
                    <span class="font-semibold text-text-main font-mono"><?php echo e($device->serial_number ?: '-'); ?></span>
                </div>
                <div class="flex justify-between border-b border-border/50 py-1">
                    <span class="text-text-muted">MAC Address</span>
                    <span class="font-semibold text-text-main font-mono"><?php echo e($device->mac_address ?: '-'); ?></span>
                </div>
                <div class="flex justify-between border-b border-border/50 py-1">
                    <span class="text-text-muted">IP Address</span>
                    <span class="font-semibold text-text-main font-mono"><?php echo e($maskSensitive($device->ip_address)); ?></span>
                </div>
                <div class="flex justify-between border-b border-border/50 py-1">
                    <span class="text-text-muted">VLAN ID</span>
                    <span class="font-semibold text-text-main font-mono"><?php echo e($maskSensitive($device->vlan_id)); ?></span>
                </div>
            </div>
            <div class="space-y-3">
                <div class="flex justify-between border-b border-border/50 py-1">
                    <span class="text-text-muted">Username PPPoE</span>
                    <span class="font-semibold text-text-main font-mono"><?php echo e($device->pppoe_username ?: '-'); ?></span>
                </div>
                <div class="flex justify-between border-b border-border/50 py-1">
                    <span class="text-text-muted">Password PPPoE</span>
                    <span class="font-semibold text-text-main font-mono"><?php echo e($maskSensitive($device->pppoe_password)); ?></span>
                </div>
                <div class="flex justify-between border-b border-border/50 py-1">
                    <span class="text-text-muted">SSID WiFi</span>
                    <span class="font-semibold text-text-main"><?php echo e($device->wifi_ssid ?: '-'); ?></span>
                </div>
                <div class="flex justify-between border-b border-border/50 py-1">
                    <span class="text-text-muted">Password WiFi</span>
                    <span class="font-semibold text-text-main font-mono"><?php echo e($maskSensitive($device->wifi_password)); ?></span>
                </div>
                <div class="flex justify-between border-b border-border/50 py-1">
                    <span class="text-text-muted">ODP / Port</span>
                    <span class="font-semibold text-text-main"><?php echo e($device->odp ?: '-'); ?> / <?php echo e($device->odp_port ?: '-'); ?></span>
                </div>
                <div class="flex justify-between border-b border-border/50 py-1">
                    <span class="text-text-muted">Redaman / Mode</span>
                    <span class="font-semibold text-text-main"><?php echo e($device->signal_rx_power !== null ? $device->signal_rx_power . ' dBm' : '-'); ?> / <?php echo e($device->connection_mode ?: '-'); ?></span>
                </div>
            </div>
            <div class="md:col-span-2 pt-2">
                <span class="block text-text-muted mb-1">Catatan Teknis:</span>
                <p class="p-3 bg-surface-muted rounded border border-border italic text-text-secondary"><?php echo e($device->technical_note ?: 'Tidak ada catatan'); ?></p>
            </div>
        </div>
    </div>
<?php else: ?>
    <?php if($technicalDetail || $customer->ont_sn || $customer->ip_address): ?>
        <?php
            $fallbackDeviceType = '-';
            if ($technicalDetail?->connection_type) {
                $connType = strtolower($technicalDetail->connection_type);
                if (str_contains($connType, 'wireless') || str_contains($connType, 'radio')) {
                    $fallbackDeviceType = 'ROUTER';
                } elseif (str_contains($connType, 'fiber') || str_contains($connType, 'ont') || str_contains($connType, 'onu')) {
                    $fallbackDeviceType = 'ONT';
                } else {
                    $fallbackDeviceType = strtoupper($technicalDetail->connection_type);
                }
            } elseif ($customer->ont_sn) {
                $fallbackDeviceType = 'ONT';
            }
        ?>
        <div class="border border-border rounded-lg overflow-hidden">
            <div class="px-5 py-3 bg-warning-bg/50 border-b border-warning-border flex items-center justify-between">
                <span class="text-xs font-bold text-warning uppercase tracking-wider">Data Perangkat Migrasi</span>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wider bg-warning-bg text-warning border-warning-border">
                    Fallback
                </span>
            </div>
            <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
                <div class="space-y-3">
                    <div class="flex justify-between border-b border-border/50 py-1">
                        <span class="text-text-muted">Jenis Perangkat</span>
                        <span class="font-semibold text-text-main"><?php echo e($fallbackDeviceType); ?></span>
                    </div>
                    <div class="flex justify-between border-b border-border/50 py-1">
                        <span class="text-text-muted">Merk / Tipe</span>
                        <span class="font-semibold text-text-main"><?php echo e($technicalDetail?->passive_device ?: '-'); ?></span>
                    </div>
                    <div class="flex justify-between border-b border-border/50 py-1">
                        <span class="text-text-muted">Jenis Perangkat Pasif</span>
                        <span class="font-semibold text-text-main"><?php echo e($passiveDeviceTypeLabels[$technicalDetail?->passive_device_type] ?? '-'); ?></span>
                    </div>
                    <div class="flex justify-between border-b border-border/50 py-1">
                        <span class="text-text-muted">Jumlah / Panjang</span>
                        <span class="font-semibold text-text-main"><?php echo e($technicalDetail?->passive_device_qty ?: '-'); ?></span>
                    </div>
                    <div class="flex justify-between border-b border-border/50 py-1">
                        <span class="text-text-muted">Catatan Perangkat Pasif</span>
                        <span class="font-semibold text-text-main"><?php echo e($technicalDetail?->passive_device_note ?: '-'); ?></span>
                    </div>
                    <div class="flex justify-between border-b border-border/50 py-1">
                        <span class="text-text-muted">Serial Number</span>
                        <span class="font-semibold text-text-main font-mono"><?php echo e($technicalDetail?->router_or_ont_serial ?? $customer->ont_sn ?? '-'); ?></span>
                    </div>
                    <div class="flex justify-between border-b border-border/50 py-1">
                        <span class="text-text-muted">MAC Address</span>
                        <span class="font-semibold text-text-main font-mono"><?php echo e($technicalDetail?->router_mac ?? $technicalDetail?->antenna_mac ?? '-'); ?></span>
                    </div>
                    <div class="flex justify-between border-b border-border/50 py-1">
                        <span class="text-text-muted">IP Address</span>
                        <span class="font-semibold text-text-main font-mono"><?php echo e($maskSensitive($technicalDetail?->ip_address ?? $customer->ip_address)); ?></span>
                    </div>
                    <div class="flex justify-between border-b border-border/50 py-1">
                        <span class="text-text-muted">VLAN ID</span>
                        <span class="font-semibold text-text-main font-mono"><?php echo e($maskSensitive($customer->vlan_id)); ?></span>
                    </div>
                </div>
                <div class="space-y-3">
                    <div class="flex justify-between border-b border-border/50 py-1">
                        <span class="text-text-muted">Username PPPoE</span>
                        <span class="font-semibold text-text-main font-mono">-</span>
                    </div>
                    <div class="flex justify-between border-b border-border/50 py-1">
                        <span class="text-text-muted">Password PPPoE</span>
                        <span class="font-semibold text-text-main font-mono">-</span>
                    </div>
                    <div class="flex justify-between border-b border-border/50 py-1">
                        <span class="text-text-muted">SSID WiFi</span>
                        <span class="font-semibold text-text-main"><?php echo e($technicalDetail?->ssid ?? '-'); ?></span>
                    </div>
                    <div class="flex justify-between border-b border-border/50 py-1">
                        <span class="text-text-muted">Password WiFi</span>
                        <span class="font-semibold text-text-main font-mono">-</span>
                    </div>
                    <div class="flex justify-between border-b border-border/50 py-1">
                        <span class="text-text-muted">ODP / Port</span>
                        <span class="font-semibold text-text-main"><?php echo e($technicalDetail?->odp_number ?? $customer->odp_code ?? '-'); ?> / <?php echo e($technicalDetail?->odp_port ?? '-'); ?></span>
                    </div>
                    <div class="flex justify-between border-b border-border/50 py-1">
                        <span class="text-text-muted">Redaman / Mode</span>
                        <span class="font-semibold text-text-main"><?php echo e(($technicalDetail?->fiber_signal ?? $technicalDetail?->wireless_signal) ?: '-'); ?> / <?php echo e($technicalDetail?->connection_type ?: '-'); ?></span>
                    </div>
                </div>
                <div class="md:col-span-2 pt-2">
                    <span class="block text-text-muted mb-1">Catatan Teknis:</span>
                    <p class="p-3 bg-surface-muted rounded border border-border italic text-text-secondary"><?php echo e($technicalDetail?->note ?? 'Tidak ada catatan'); ?></p>
                </div>
            </div>
            <div class="px-5 py-3 bg-warning-bg/40 text-[10px] font-medium text-warning border-t border-warning-border">
                Data ini tampil dari detail teknis migrasi karena tabel perangkat pelanggan belum terisi.
            </div>
        </div>
    <?php else: ?>
        <div class="py-12 text-center text-text-muted bg-surface-muted/20 border border-dashed border-border rounded-lg">
            <svg class="mx-auto h-12 w-12 text-border mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
            <h4 class="text-sm font-semibold text-text-main">Belum ada data perangkat</h4>
            <p class="text-xs text-text-muted mt-1">Silakan isi data modem, ONT, atau router pelanggan melalui tombol di atas.</p>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if($customer->customerTechnicalDetail): ?>
    <?php
        $tech = $customer->customerTechnicalDetail;
    ?>
    <div class="mt-6 border border-border rounded-lg overflow-hidden">
        <div class="px-5 py-3 bg-surface-muted/50 border-b border-border flex items-center justify-between">
            <span class="text-xs font-bold text-text-main">Detail Teknis Tambahan & Migrasi</span>
            <span class="px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wider bg-surface-muted text-text-muted border-border">
                Log Teknis
            </span>
        </div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
            <div class="space-y-3">
                <div class="flex justify-between border-b border-border/50 py-1">
                    <span class="text-text-muted">Perangkat Pasif (Outdoor)</span>
                    <span class="font-semibold text-text-main"><?php echo e($tech->passive_device ?: '-'); ?></span>
                </div>
                <div class="flex justify-between border-b border-border/50 py-1">
                    <span class="text-text-muted">Jenis Perangkat Pasif</span>
                    <span class="font-semibold text-text-main"><?php echo e($passiveDeviceTypeLabels[$tech->passive_device_type] ?? '-'); ?></span>
                </div>
                <div class="flex justify-between border-b border-border/50 py-1">
                    <span class="text-text-muted">Jumlah / Panjang</span>
                    <span class="font-semibold text-text-main"><?php echo e($tech->passive_device_qty ?: '-'); ?></span>
                </div>
                <div class="flex justify-between border-b border-border/50 py-1">
                    <span class="text-text-muted">Catatan Perangkat Pasif</span>
                    <span class="font-semibold text-text-main"><?php echo e($tech->passive_device_note ?: '-'); ?></span>
                </div>
                <div class="flex justify-between border-b border-border/50 py-1">
                    <span class="text-text-muted">Nomor Cabang (IDCABANG)</span>
                    <span class="font-semibold text-text-main font-mono"><?php echo e($tech->branch_number ?: '-'); ?></span>
                </div>
                <div class="flex justify-between border-b border-border/50 py-1">
                    <span class="text-text-muted">Nomor POP (IDWILAYAH)</span>
                    <span class="font-semibold text-text-main font-mono"><?php echo e($tech->pop_number ?: '-'); ?></span>
                </div>
                <div class="flex justify-between border-b border-border/50 py-1">
                    <span class="text-text-muted">Nomor OLT</span>
                    <span class="font-semibold text-text-main font-mono"><?php echo e($tech->olt_number ?: '-'); ?></span>
                </div>
                <div class="flex justify-between border-b border-border/50 py-1">
                    <span class="text-text-muted">Slot OLT</span>
                    <span class="font-semibold text-text-main font-mono"><?php echo e($tech->olt_slot ?: '-'); ?></span>
                </div>
                <div class="flex justify-between border-b border-border/50 py-1">
                    <span class="text-text-muted">Nomor Port OLT</span>
                    <span class="font-semibold text-text-main font-mono"><?php echo e($tech->olt_port ?: '-'); ?></span>
                </div>
            </div>
            <div class="space-y-3">
                <div class="flex justify-between border-b border-border/50 py-1">
                    <span class="text-text-muted">Nomor Router / MAC</span>
                    <span class="font-semibold text-text-main font-mono"><?php echo e($tech->router_number ?: '-'); ?></span>
                </div>
                <div class="flex justify-between border-b border-border/50 py-1">
                    <span class="text-text-muted">Redaman Awal</span>
                    <span class="font-semibold text-text-main font-mono"><?php echo e($tech->initial_attenuation !== null ? $tech->initial_attenuation . ' dB' : '-'); ?></span>
                </div>
                <div class="flex justify-between border-b border-border/50 py-1">
                    <span class="text-text-muted">Redaman Terkini (Actual)</span>
                    <span class="font-semibold text-text-main font-mono"><?php echo e($tech->actual_attenuation !== null ? $tech->actual_attenuation . ' dB' : '-'); ?></span>
                </div>
                <div class="flex justify-between border-b border-border/50 py-1">
                    <span class="text-text-muted">VLAN (Teknis Jaringan)</span>
                    <span class="font-semibold text-text-main font-mono"><?php echo e($maskSensitive($tech->vlan)); ?></span>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('fill_device')): ?>
<div id="device-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-surface rounded-lg shadow-xl border border-border w-full max-w-3xl overflow-hidden transform transition-all">
        <div class="px-5 py-4 border-b border-border flex items-center justify-between">
            <h3 class="text-xs font-bold text-text-main uppercase tracking-wider">Input Data Perangkat Pelanggan</h3>
            <button onclick="closeDeviceModal()" class="text-text-muted hover:text-text-main focus:outline-none cursor-pointer">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form action="<?php echo e(route('customers.device.store', $customer->id)); ?>" method="POST">
            <?php echo csrf_field(); ?>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="device_type" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Jenis Perangkat</label>
                    <select name="device_type" id="device_type" required class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs text-text-main">
                        <?php
                            $defaultDeviceType = old('device_type', $device?->device_type);
                            if (!$defaultDeviceType && !$device) {
                                if ($technicalDetail?->connection_type) {
                                    $connType = strtolower($technicalDetail->connection_type);
                                    if (str_contains($connType, 'wireless') || str_contains($connType, 'radio')) {
                                        $defaultDeviceType = 'router';
                                    } else {
                                        $defaultDeviceType = 'ont';
                                    }
                                } else {
                                    $defaultDeviceType = 'ont';
                                }
                            }
                        ?>
                        <?php $__currentLoopData = ['modem' => 'Modem', 'ont' => 'ONT', 'onu' => 'ONU', 'router' => 'Router', 'other' => 'Lainnya']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($value); ?>" <?php echo e($defaultDeviceType === $value ? 'selected' : ''); ?>><?php echo e($label); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div>
                    <label for="connection_mode" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Mode Koneksi</label>
                    <select name="connection_mode" id="connection_mode" class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs text-text-main">
                        <option value="">Pilih mode</option>
                        <?php
                            $defaultConnMode = old('connection_mode', $device?->connection_mode);
                            if (!$defaultConnMode && !$device && $technicalDetail?->connection_type) {
                                $connType = strtolower($technicalDetail->connection_type);
                                if (str_contains($connType, 'pppoe')) {
                                    $defaultConnMode = 'pppoe';
                                } elseif (str_contains($connType, 'static')) {
                                    $defaultConnMode = 'static';
                                } elseif (str_contains($connType, 'dhcp')) {
                                    $defaultConnMode = 'dhcp';
                                } elseif (str_contains($connType, 'bridge')) {
                                    $defaultConnMode = 'bridge';
                                } elseif (str_contains($connType, 'router')) {
                                    $defaultConnMode = 'router';
                                }
                            }
                        ?>
                        <?php $__currentLoopData = ['bridge' => 'Bridge', 'router' => 'Router', 'pppoe' => 'PPPoE', 'static' => 'Static', 'dhcp' => 'DHCP', 'other' => 'Lainnya']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($value); ?>" <?php echo e($defaultConnMode === $value ? 'selected' : ''); ?>><?php echo e($label); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div>
                    <label for="brand" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Merk</label>
                    <?php
                        $defaultBrand = $device?->brand;
                        if (!$defaultBrand && !$device && $technicalDetail?->passive_device) {
                            $parts = explode(' ', trim($technicalDetail->passive_device), 2);
                            $defaultBrand = $parts[0] ?? '';
                        }
                    ?>
                    <input type="text" name="brand" id="brand" value="<?php echo e(old('brand', $defaultBrand)); ?>" class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs text-text-main">
                </div>
                <div>
                    <label for="model" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Tipe</label>
                    <?php
                        $defaultModel = $device?->model;
                        if (!$defaultModel && !$device && $technicalDetail?->passive_device) {
                            $parts = explode(' ', trim($technicalDetail->passive_device), 2);
                            $defaultModel = $parts[1] ?? '';
                        }
                    ?>
                    <input type="text" name="model" id="model" value="<?php echo e(old('model', $defaultModel)); ?>" class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs text-text-main">
                </div>
                <div>
                    <label for="serial_number" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Serial Number</label>
                    <input type="text" name="serial_number" id="serial_number" value="<?php echo e(old('serial_number', $device?->serial_number ?? $technicalDetail?->router_or_ont_serial ?? $customer->ont_sn)); ?>" class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs font-mono text-text-main">
                </div>
                <div>
                    <label for="mac_address" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">MAC Address</label>
                    <input type="text" name="mac_address" id="mac_address" value="<?php echo e(old('mac_address', $device?->mac_address ?? $technicalDetail?->router_mac ?? $technicalDetail?->antenna_mac)); ?>" placeholder="AA:BB:CC:DD:EE:FF" class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs font-mono text-text-main">
                </div>
                <div>
                    <label for="pppoe_username" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Username PPPoE</label>
                    <input type="text" name="pppoe_username" id="pppoe_username" value="<?php echo e(old('pppoe_username', $device?->pppoe_username)); ?>" class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs font-mono text-text-main">
                </div>
                <div>
                    <label for="pppoe_password" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Password PPPoE</label>
                    <input type="text" name="pppoe_password" id="pppoe_password" value="<?php echo e($canViewSensitiveDeviceFields ? old('pppoe_password', $device?->pppoe_password) : ''); ?>" <?php echo e(!$canViewSensitiveDeviceFields ? 'placeholder=********' : ''); ?> class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs font-mono text-text-main">
                </div>
                <div>
                    <label for="wifi_ssid" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">SSID WiFi</label>
                    <input type="text" name="wifi_ssid" id="wifi_ssid" value="<?php echo e(old('wifi_ssid', $device?->wifi_ssid ?? $technicalDetail?->ssid)); ?>" class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs text-text-main">
                </div>
                <div>
                    <label for="wifi_password" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Password WiFi</label>
                    <input type="text" name="wifi_password" id="wifi_password" value="<?php echo e($canViewSensitiveDeviceFields ? old('wifi_password', $device?->wifi_password) : ''); ?>" <?php echo e(!$canViewSensitiveDeviceFields ? 'placeholder=********' : ''); ?> class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs font-mono text-text-main">
                </div>
                <div>
                    <label for="ip_address" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">IP Address</label>
                    <input type="text" name="ip_address" id="ip_address" value="<?php echo e($canViewSensitiveDeviceFields ? old('ip_address', $device?->ip_address ?? $technicalDetail?->ip_address ?? $customer->ip_address) : ''); ?>" <?php echo e(!$canViewSensitiveDeviceFields ? 'placeholder=********' : 'placeholder=192.168.1.1'); ?> class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs font-mono text-text-main">
                </div>
                <div>
                    <label for="vlan_id" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">VLAN ID</label>
                    <input type="text" name="vlan_id" id="vlan_id" value="<?php echo e($canViewSensitiveDeviceFields ? old('vlan_id', $device?->vlan_id ?? $customer->vlan_id) : ''); ?>" <?php echo e(!$canViewSensitiveDeviceFields ? 'placeholder=********' : ''); ?> class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs font-mono text-text-main">
                </div>
                <div>
                    <label for="odp" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">ODP</label>
                    <input type="text" name="odp" id="odp" value="<?php echo e(old('odp', $device?->odp ?? $technicalDetail?->odp_number ?? $customer->odp_code)); ?>" class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs font-mono text-text-main">
                </div>
                <div>
                    <label for="odp_port" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Port ODP</label>
                    <input type="text" name="odp_port" id="odp_port" value="<?php echo e(old('odp_port', $device?->odp_port ?? $technicalDetail?->odp_port)); ?>" class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs font-mono text-text-main">
                </div>
                <div>
                    <label for="olt_number" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Nomor OLT</label>
                    <input type="text" name="olt_number" id="olt_number" value="<?php echo e(old('olt_number', $technicalDetail?->olt_number)); ?>" placeholder="Contoh: OLT-01" class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs font-mono text-text-main">
                </div>
                <div>
                    <label for="olt_slot" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Slot OLT</label>
                    <input type="text" name="olt_slot" id="olt_slot" value="<?php echo e(old('olt_slot', $technicalDetail?->olt_slot)); ?>" placeholder="Contoh: 0/1" class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs font-mono text-text-main">
                </div>
                <div>
                    <label for="olt_port_tech" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Nomor Port OLT</label>
                    <input type="text" name="olt_port" id="olt_port_tech" value="<?php echo e(old('olt_port', $technicalDetail?->olt_port)); ?>" placeholder="Contoh: 1" class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs font-mono text-text-main">
                </div>
                <div>
                    <label for="tech_vlan" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">VLAN (Jaringan)</label>
                    <input type="text" name="tech_vlan" id="tech_vlan" value="<?php echo e($canViewSensitiveDeviceFields ? old('tech_vlan', $technicalDetail?->vlan) : ''); ?>" <?php echo e(!$canViewSensitiveDeviceFields ? 'placeholder=********' : 'placeholder=Contoh:\ 100'); ?> class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs font-mono text-text-main">
                </div>

                
                <div class="md:col-span-2 pt-3 mt-3 border-t border-border">
                    <h4 class="text-[10px] font-bold text-text-secondary uppercase tracking-wider mb-3">Detail Teknis Tambahan</h4>
                </div>

                <div>
                    <label for="passive_device" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Perangkat Pasif (Merk/Tipe)</label>
                    <input type="text" name="passive_device" id="passive_device" value="<?php echo e(old('passive_device', $technicalDetail?->passive_device)); ?>" placeholder="Contoh: Antena Grid 25dBi" class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs text-text-main">
                </div>
                <div>
                    <label for="passive_device_type" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Jenis Perangkat Pasif</label>
                    <select name="passive_device_type" id="passive_device_type" class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs text-text-main">
                        <option value="">Pilih jenis</option>
                        <?php $__currentLoopData = $passiveDeviceTypeLabels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($value); ?>" <?php echo e(old('passive_device_type', $technicalDetail?->passive_device_type) === $value ? 'selected' : ''); ?>><?php echo e($label); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div>
                    <label for="passive_device_qty" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Jumlah / Panjang</label>
                    <input type="text" name="passive_device_qty" id="passive_device_qty" value="<?php echo e(old('passive_device_qty', $technicalDetail?->passive_device_qty)); ?>" placeholder="Contoh: 150 meter / 1 pasang" class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs text-text-main">
                </div>
                <div class="md:col-span-2">
                    <label for="passive_device_note" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Catatan Detail Perangkat Pasif</label>
                    <input type="text" name="passive_device_note" id="passive_device_note" value="<?php echo e(old('passive_device_note', $technicalDetail?->passive_device_note)); ?>" placeholder="Contoh: Dropcore 90m + Router ZTE 1 + Klem dan isolasi" class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs text-text-main">
                </div>
                <div>
                    <label for="router_number" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Nomor Router / MAC</label>
                    <input type="text" name="router_number" id="router_number" value="<?php echo e(old('router_number', $technicalDetail?->router_number)); ?>" placeholder="Contoh: RTR-001" class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs font-mono text-text-main">
                </div>
                <div>
                    <label for="branch_number" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Nomor Cabang (IDCABANG)</label>
                    <input type="text" name="branch_number" id="branch_number" value="<?php echo e(old('branch_number', $technicalDetail?->branch_number)); ?>" placeholder="Contoh: CB001" class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs font-mono text-text-main">
                </div>
                <div>
                    <label for="pop_number" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Nomor POP (IDWILAYAH)</label>
                    <input type="text" name="pop_number" id="pop_number" value="<?php echo e(old('pop_number', $technicalDetail?->pop_number)); ?>" placeholder="Contoh: WL0001" class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs font-mono text-text-main">
                </div>
                <div>
                    <label for="initial_attenuation" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Redaman Awal (dBm)</label>
                    <input type="text" name="initial_attenuation" id="initial_attenuation_dev" value="<?php echo e(old('initial_attenuation', $technicalDetail?->initial_attenuation)); ?>" placeholder="-19.50" class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs font-mono text-text-main">
                </div>
                <div>
                    <label for="actual_attenuation" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Redaman Aktual (dBm)</label>
                    <input type="text" name="actual_attenuation" id="actual_attenuation_dev" value="<?php echo e(old('actual_attenuation', $technicalDetail?->actual_attenuation)); ?>" placeholder="-21.20" class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs font-mono text-text-main">
                </div>
                

                <div>
                    <label for="signal_rx_power" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Redaman Perangkat (dBm)</label>
                    <?php
                        $sigPower = old('signal_rx_power', $device?->signal_rx_power);
                        if (!$device && $technicalDetail) {
                            $rawSig = $technicalDetail->fiber_signal ?? $technicalDetail->wireless_signal;
                            if (is_numeric($rawSig)) {
                                $sigPower = $rawSig;
                            } else {
                                preg_match('/-\d+(?:\.\d+)?/', $rawSig, $matches);
                                if (!empty($matches)) {
                                    $sigPower = $matches[0];
                                }
                            }
                        }
                    ?>
                    <input type="number" step="0.01" name="signal_rx_power" id="signal_rx_power" value="<?php echo e($sigPower); ?>" class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs font-mono text-text-main">
                </div>
                <div class="md:col-span-2">
                    <label for="technical_note" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Catatan Teknis</label>
                    <textarea name="technical_note" id="technical_note" rows="3" class="w-full px-3 py-2 bg-surface border border-border rounded-md shadow-sm focus:ring-primary focus:border-primary text-xs text-text-main"><?php echo e(old('technical_note', $device?->technical_note ?? $technicalDetail?->note)); ?></textarea>
                </div>
            </div>
            <div class="px-5 py-3.5 bg-surface-muted/50 border-t border-border flex justify-end gap-2 text-xs">
                <button type="button" onclick="closeDeviceModal()" class="px-3 py-1.5 border border-border text-text-secondary bg-surface hover:bg-surface-muted font-semibold rounded-md shadow-sm transition-colors cursor-pointer">
                    Batal
                </button>
                <button type="submit" class="px-3 py-1.5 bg-primary hover:bg-primary/90 text-white font-semibold rounded-md shadow-sm transition-colors cursor-pointer">
                    Simpan Data Perangkat
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/customers/tabs/_device.blade.php ENDPATH**/ ?>