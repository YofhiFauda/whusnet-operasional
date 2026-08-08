{{--
    Tab Perangkat — mengikuti redesign_detail_pelanggan_whusnet.html (TAB 7):
    4 sub-section berwarna (ONT aktif → OLT/ODP → pasif outdoor → record migrasi).

    Nilai dibaca berlapis: customer_devices (sumber utama) → customer_technical_details
    (hasil migrasi) → kolom denormal di customers. Dulu dua cabang @if yang
    menduplikasi seluruh markup; sekarang satu markup dengan fallback per-field,
    supaya tampilan tidak lagi menyimpang antar cabang tiap kali ada field baru.
--}}

@php
    $device = $customer->customerDevice;
    $tech = $customer->customerTechnicalDetail;

    $canViewSensitiveDeviceFields = auth()->user()->hasPermission('customers.detail.devices.view_sensitive');
    $maskSensitive = fn ($value) => $canViewSensitiveDeviceFields ? ($value ?: '-') : ($value ? '********' : '-');

    // Dropdown cuma kategori aktif; label baca SEMUA kategori (termasuk yang
    // dinonaktifkan) supaya perangkat lama tetap terbaca namanya.
    $passiveDeviceCategories = \App\Models\ItemCategory::active()->ordered()->get();
    $passiveDeviceTypeLabel = fn ($code) => \App\Models\ItemCategory::labelFor($code);

    // Jenis perangkat: kalau tabel perangkat belum terisi, diturunkan dari tipe
    // koneksi migrasi (wireless → ROUTER, fiber/ont → ONT).
    $deviceType = $device?->device_type ? strtoupper($device->device_type) : null;
    if (! $deviceType) {
        $connType = strtolower((string) $tech?->connection_type);
        if ($connType && (str_contains($connType, 'wireless') || str_contains($connType, 'radio'))) {
            $deviceType = 'ROUTER';
        } elseif ($connType && (str_contains($connType, 'fiber') || str_contains($connType, 'ont') || str_contains($connType, 'onu') || str_contains($connType, 'kabel'))) {
            $deviceType = 'ONT';
        } elseif ($customer->ont_sn) {
            $deviceType = 'ONT';
        }
    }

    $brandModel = trim(($device?->brand ?? '').' '.($device?->model ?? '')) ?: ($tech?->passive_device ?: null);
    $serialNumber = $device?->serial_number ?: ($tech?->router_or_ont_serial ?: $customer->ont_sn);
    $macAddress = $device?->mac_address ?: ($tech?->router_mac ?: $tech?->antenna_mac);
    $ipAddress = $device?->ip_address ?: ($tech?->ip_address ?: $customer->ip_address);
    $vlanId = $device?->vlan_id ?: ($tech?->vlan ?: $customer->vlan_id);
    $ssid = $device?->wifi_ssid ?: $tech?->ssid;
    $odpCode = $device?->odp ?: ($tech?->odp_number ?: $customer->odp_code);
    $odpPort = $device?->odp_port ?: $tech?->odp_port;
    $rxPower = $device?->signal_rx_power !== null
        ? $device->signal_rx_power.' dBm'
        : (($tech?->fiber_signal ?: $tech?->wireless_signal) ?: null);
    $technicalNote = $device?->technical_note ?: $tech?->note;

    $isFallbackOnly = ! $device && ($tech || $customer->ont_sn || $customer->ip_address);
    $hasAnyDeviceData = $device || $isFallbackOnly;

    // Material pasif yang benar-benar terpakai saat pemasangan.
    $materialPasif = \App\Models\TaskMaterial::where('customer_id', $customer->id)
        ->terpakai()->orderBy('id')->get();
@endphp

<!-- Sub-header & Action button -->
<div class="px-5 py-3.5 bg-slate-50/60 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 rounded-lg flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h3 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">Spesifikasi Lengkap Perangkat &amp; Infrastruktur Jaringan</h3>
        <p class="text-[11px] text-slate-500 mt-0.5">Informasi perangkat aktif (ONT/Modem/Router), PPPoE, WiFi, OLT Node, ODP, VLAN, dan Perangkat Pasif Outdoor.</p>
    </div>
    @can('customers.detail.devices.update')
        <button type="button" onclick="openDeviceModal()" class="px-3.5 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-xs font-semibold shadow-sm shrink-0 cursor-pointer">
            <i class="fa-solid fa-pen-to-square mr-1"></i> Isi / Ubah Data Perangkat
        </button>
    @endcan
</div>

@if($isFallbackOnly)
    <div class="px-4 py-2.5 rounded-lg border bg-amber-50/60 dark:bg-amber-950/30 border-amber-200 dark:border-amber-800 flex flex-wrap items-center gap-2 text-[11px] font-medium text-amber-700 dark:text-amber-400">
        <span class="px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wide bg-amber-100 dark:bg-amber-950/60 border-amber-300 dark:border-amber-800">Data Perangkat Migrasi</span>
        <span>Data ini tampil dari detail teknis migrasi karena tabel perangkat pelanggan belum terisi.</span>
    </div>
@endif

@if($hasAnyDeviceData)
    <!-- 1. PERANGKAT AKTIF UTAMA (ONT / MODEM KLIEN) -->
    <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
        <div class="px-5 py-3 bg-sky-50/50 dark:bg-sky-950/20 border-b border-slate-200 dark:border-slate-700 flex flex-wrap gap-2 items-center justify-between">
            <span class="text-xs font-bold text-sky-700 dark:text-sky-300 uppercase tracking-wider">
                <i class="fa-solid fa-router mr-1.5"></i> 1. PERANGKAT AKTIF UTAMA (ONT / MODEM KLIEN)
            </span>
            <span class="px-2 py-0.5 rounded text-[10px] font-bold border bg-sky-100 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border-sky-300 dark:border-sky-800">
                {{ $deviceType ?: 'PERANGKAT' }} — {{ $serialNumber ? 'TERPASANG' : 'BELUM TERPASANG' }}
            </span>
        </div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3.5 text-xs">
            <div class="space-y-3">
                <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-1">
                    <span class="text-slate-400">Jenis Perangkat</span>
                    <span class="font-semibold text-slate-900 dark:text-slate-100 uppercase searchable-text">{{ $deviceType ?: '-' }}</span>
                </div>
                <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-1">
                    <span class="text-slate-400">Merk / Model Tipe</span>
                    <span class="font-semibold text-slate-900 dark:text-slate-100 text-right searchable-text">{{ $brandModel ?: '-' }}</span>
                </div>
                <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-1">
                    <span class="text-slate-400">Serial Number (SN)</span>
                    <span class="font-mono font-bold text-sky-600 dark:text-sky-400 searchable-text">{{ $serialNumber ?: '-' }}</span>
                </div>
                <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-1">
                    <span class="text-slate-400">MAC Address</span>
                    <span class="font-mono font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $macAddress ?: '-' }}</span>
                </div>
                <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-1">
                    <span class="text-slate-400">IP Address Dialed</span>
                    <span class="font-mono font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $maskSensitive($ipAddress) }}</span>
                </div>
                <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-1">
                    <span class="text-slate-400">Mode Koneksi</span>
                    <span class="font-mono font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $device?->connection_mode ?: ($tech?->connection_type ?: '-') }}</span>
                </div>
            </div>

            <div class="space-y-3">
                <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-1">
                    <span class="text-slate-400">Username PPPoE</span>
                    <span class="font-mono font-bold text-slate-900 dark:text-slate-100 searchable-text">{{ $device?->pppoe_username ?: '-' }}</span>
                </div>
                <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-1">
                    <span class="text-slate-400">Password PPPoE</span>
                    <span class="font-mono text-slate-500">{{ $maskSensitive($device?->pppoe_password) }}</span>
                </div>
                <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-1">
                    <span class="text-slate-400">Nama SSID WiFi</span>
                    <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $ssid ?: '-' }}</span>
                </div>
                <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-1">
                    <span class="text-slate-400">Password WiFi</span>
                    <span class="font-mono text-slate-500">{{ $maskSensitive($device?->wifi_password) }}</span>
                </div>
                <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-1">
                    <span class="text-slate-400">VLAN ID Klien</span>
                    <span class="font-mono font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $maskSensitive($vlanId) }}</span>
                </div>
                <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-1">
                    <span class="text-slate-400">Redaman Optik (Rx Power)</span>
                    <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400 searchable-text">{{ $rxPower ?: '-' }}</span>
                </div>
            </div>

            <div class="md:col-span-2 pt-2">
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">CATATAN TEKNIS PEMASANGAN PERANGKAT:</span>
                <p class="p-3 bg-slate-50 dark:bg-slate-900/50 rounded border border-slate-200 dark:border-slate-700 italic text-slate-600 dark:text-slate-300 searchable-text">
                    {{ $technicalNote ?: 'Tidak ada catatan' }}
                </p>
            </div>
        </div>
    </div>

    <!-- 2. INFRASTRUKTUR OLT NODE & DISTRIBUSI ODP -->
    <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
        <div class="px-5 py-3 bg-indigo-50/50 dark:bg-indigo-950/20 border-b border-slate-200 dark:border-slate-700 flex flex-wrap gap-2 items-center justify-between">
            <span class="text-xs font-bold text-indigo-700 dark:text-indigo-300 uppercase tracking-wider">
                <i class="fa-solid fa-diagram-project mr-1.5"></i> 2. INFRASTRUKTUR OLT NODE &amp; DISTRIBUSI ODP
            </span>
            <span class="px-2 py-0.5 rounded text-[10px] font-bold border bg-indigo-100 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border-indigo-300 dark:border-indigo-800">
                TOPOLOGI OLT &amp; ODP
            </span>
        </div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3.5 text-xs">
            <div class="space-y-3">
                <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-1">
                    <span class="text-slate-400">POP / Cabang Induk</span>
                    <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text">
                        {{ $customer->pop->name ?? 'Belum di-assign' }}{{ $customer->pop?->pop_code ? ' ('.$customer->pop->pop_code.')' : '' }}
                    </span>
                </div>
                <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-1">
                    <span class="text-slate-400">Mini POP (OLT Location)</span>
                    <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $customer->miniPop->name ?? 'Belum di-assign' }}</span>
                </div>
                <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-1">
                    <span class="text-slate-400">Nama Perangkat OLT</span>
                    <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $customer->olt_code ?: '-' }}</span>
                </div>
                <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-1">
                    <span class="text-slate-400">Nomor OLT [CID Generator]</span>
                    {{-- Pakai accessor, bukan $displayId: partial ini juga di-include
                         halaman Perangkat & Pemasangan (customers.fieldwork) yang
                         tidak mengoper variabel itu. --}}
                    <span class="font-mono font-bold text-sky-600 dark:text-sky-400 searchable-text">{{ $customer->display_id }}</span>
                </div>
            </div>

            <div class="space-y-3">
                <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-1">
                    <span class="text-slate-400">Slot OLT</span>
                    <span class="font-mono font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $tech?->olt_slot ?: '-' }}</span>
                </div>
                <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-1">
                    <span class="text-slate-400">Nomor Port PON OLT</span>
                    <span class="font-mono font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $tech?->olt_port ?: '-' }}</span>
                </div>
                <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-1">
                    <span class="text-slate-400">Kode Box ODP / Port</span>
                    <span class="font-mono font-bold text-slate-900 dark:text-slate-100 searchable-text">
                        {{ $odpCode ?: '-' }}{{ $odpPort ? ' (Port '.$odpPort.')' : '' }}
                    </span>
                </div>
                <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-1">
                    <span class="text-slate-400">Jalur Distribusi</span>
                    <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text">
                        {{ $customer->distribution ? '['.$customer->distribution->code.'] '.$customer->distribution->name : 'Belum di-assign' }}
                    </span>
                </div>
                <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-1">
                    <span class="text-slate-400">VLAN Jaringan OLT</span>
                    <span class="font-mono font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $maskSensitive($tech?->vlan) }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. PERANGKAT PASIF OUTDOOR & KEBUTUHAN KABEL FO -->
    <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
        <div class="px-5 py-3 bg-emerald-50/50 dark:bg-emerald-950/20 border-b border-slate-200 dark:border-slate-700 flex flex-wrap gap-2 items-center justify-between">
            <span class="text-xs font-bold text-emerald-700 dark:text-emerald-300 uppercase tracking-wider">
                <i class="fa-solid fa-toolbox mr-1.5"></i> 3. PERANGKAT PASIF OUTDOOR &amp; KEBUTUHAN KABEL FO
            </span>
            <span class="px-2 py-0.5 rounded text-[10px] font-bold border bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800">
                MATERIAL PASIF
            </span>
        </div>
        <div class="p-5 space-y-4 text-xs">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3">
                <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-1">
                    <span class="text-slate-400">Perangkat Pasif Utama</span>
                    <span class="font-semibold text-slate-900 dark:text-slate-100 text-right searchable-text">{{ $tech?->passive_device ?: '-' }}</span>
                </div>
                <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-1">
                    <span class="text-slate-400">Jenis Perangkat Pasif</span>
                    <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $passiveDeviceTypeLabel($tech?->passive_device_type) }}</span>
                </div>
                <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-1">
                    <span class="text-slate-400">Panjang / Jumlah Terpakai</span>
                    <span class="font-mono font-bold text-slate-900 dark:text-slate-100 searchable-text">{{ $tech?->passive_device_qty ?: '-' }}</span>
                </div>
                <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-1">
                    <span class="text-slate-400">Redaman Awal vs Terkini</span>
                    <span class="font-mono font-semibold text-slate-900 dark:text-slate-100 searchable-text">
                        {{ $tech?->initial_attenuation !== null && $tech?->initial_attenuation !== '' ? $tech->initial_attenuation.' dBm' : '-' }}
                        /
                        {{ $tech?->actual_attenuation !== null && $tech?->actual_attenuation !== '' ? $tech->actual_attenuation.' dBm' : '-' }}
                    </span>
                </div>
                <div class="flex justify-between gap-3 border-b border-slate-100 dark:border-slate-700/50 py-1 md:col-span-2">
                    <span class="text-slate-400">Catatan Perangkat Pasif</span>
                    <span class="font-semibold text-slate-900 dark:text-slate-100 text-right searchable-text">{{ $tech?->passive_device_note ?: '-' }}</span>
                </div>
            </div>

            <div class="pt-2">
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">RINCIAN AKSESORIS MATERIAL PASIF TERPAKAI</span>
                @if($materialPasif->isNotEmpty())
                    <div class="overflow-x-auto border border-slate-200 dark:border-slate-700 rounded-lg">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50/60 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-700 text-[10px] font-bold text-slate-400 uppercase">
                                    <th class="px-4 py-2">Material / Aksesoris</th>
                                    <th class="px-4 py-2 text-center">Jumlah</th>
                                    <th class="px-4 py-2">Satuan</th>
                                    <th class="px-4 py-2">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700 font-mono">
                                @foreach($materialPasif as $material)
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors">
                                        <td class="px-4 py-2 font-sans font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $material->item_name }}</td>
                                        <td class="px-4 py-2 text-center font-bold text-slate-900 dark:text-slate-100">{{ rtrim(rtrim(number_format($material->qty, 2, ',', '.'), '0'), ',') }}</td>
                                        <td class="px-4 py-2 font-sans text-slate-700 dark:text-slate-300">{{ $material->unit }}</td>
                                        <td class="px-4 py-2 font-sans text-slate-500">{{ $material->note ?: $material->category_label }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="py-5 text-center text-[11px] text-slate-400 bg-slate-50/50 dark:bg-slate-900/30 border border-dashed border-slate-200 dark:border-slate-700 rounded-lg">
                        Belum ada rincian material pasif terpakai untuk pelanggan ini.
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- 4. NOMOR REGIONAL & RECORD TEKNIS MIGRASI -->
    @if($tech)
    <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
        <div class="px-5 py-3 bg-slate-100/60 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-700 flex flex-wrap gap-2 items-center justify-between">
            <span class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                <i class="fa-solid fa-server mr-1.5"></i> 4. NOMOR REGIONAL &amp; RECORD TEKNIS MIGRASI
            </span>
            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                SYSTEM LOG
            </span>
        </div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-6 text-xs font-mono">
            @foreach ([
                ['label' => 'NOMOR CABANG (IDCABANG)', 'value' => $tech->branch_number],
                ['label' => 'NOMOR POP (IDWILAYAH)', 'value' => $tech->pop_number],
                ['label' => 'NOMOR ROUTER / MAC (MIGRASI)', 'value' => $tech->router_number],
            ] as $record)
                <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-3 bg-slate-50/50 dark:bg-slate-900/30">
                    <span class="block text-[9px] font-bold text-slate-400 uppercase font-sans">{{ $record['label'] }}</span>
                    <span class="font-bold text-slate-900 dark:text-slate-100 mt-1 block searchable-text">{{ $record['value'] ?: '-' }}</span>
                </div>
            @endforeach
        </div>
    </div>
    @endif
@else
    <div class="py-12 text-center text-slate-400 bg-slate-50/50 dark:bg-slate-900/30 border border-dashed border-slate-200 dark:border-slate-700 rounded-lg">
        <i class="fa-solid fa-router text-3xl mb-2 text-slate-300 dark:text-slate-600"></i>
        <h4 class="text-xs font-bold text-slate-900 dark:text-slate-100">Belum Ada Data Perangkat</h4>
        <p class="text-[11px] text-slate-500 mt-1">Silakan isi data modem, ONT, atau router pelanggan melalui tombol di atas.</p>
    </div>
@endif

@can('fill_device')
<div id="device-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700 w-full max-w-3xl overflow-hidden max-h-[90vh] flex flex-col">
        <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between shrink-0">
            <h3 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">Input Data Perangkat Pelanggan</h3>
            <button type="button" onclick="closeDeviceModal()" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>
        <form action="{{ route('customers.device.store', $customer->id) }}" method="POST" class="overflow-y-auto">
            @csrf
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <div>
                    <label for="device_type" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Jenis Perangkat</label>
                    @php
                        $defaultDeviceType = old('device_type', $device?->device_type) ?: strtolower((string) $deviceType) ?: 'ont';
                    @endphp
                    <select name="device_type" id="device_type" required class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-200">
                        @foreach(['modem' => 'Modem', 'ont' => 'ONT', 'onu' => 'ONU', 'router' => 'Router', 'other' => 'Lainnya'] as $value => $label)
                            <option value="{{ $value }}" {{ $defaultDeviceType === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="connection_mode" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Mode Koneksi</label>
                    @php
                        $defaultConnMode = old('connection_mode', $device?->connection_mode);
                        if (! $defaultConnMode && ! $device && $tech?->connection_type) {
                            $ct = strtolower($tech->connection_type);
                            foreach (['pppoe', 'static', 'dhcp', 'bridge', 'router'] as $mode) {
                                if (str_contains($ct, $mode)) {
                                    $defaultConnMode = $mode;
                                    break;
                                }
                            }
                        }
                    @endphp
                    <select name="connection_mode" id="connection_mode" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-200">
                        <option value="">Pilih mode</option>
                        @foreach(['bridge' => 'Bridge', 'router' => 'Router', 'pppoe' => 'PPPoE', 'static' => 'Static', 'dhcp' => 'DHCP', 'other' => 'Lainnya'] as $value => $label)
                            <option value="{{ $value }}" {{ $defaultConnMode === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="brand" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Merk</label>
                    @php
                        $defaultBrand = $device?->brand;
                        if (! $defaultBrand && ! $device && $tech?->passive_device) {
                            $defaultBrand = explode(' ', trim($tech->passive_device), 2)[0] ?? '';
                        }
                    @endphp
                    <input type="text" name="brand" id="brand" value="{{ old('brand', $defaultBrand) }}" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label for="model" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Tipe</label>
                    @php
                        $defaultModel = $device?->model;
                        if (! $defaultModel && ! $device && $tech?->passive_device) {
                            $defaultModel = explode(' ', trim($tech->passive_device), 2)[1] ?? '';
                        }
                    @endphp
                    <input type="text" name="model" id="model" value="{{ old('model', $defaultModel) }}" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label for="serial_number" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Serial Number</label>
                    <input type="text" name="serial_number" id="serial_number" value="{{ old('serial_number', $serialNumber) }}" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label for="mac_address" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">MAC Address</label>
                    <input type="text" name="mac_address" id="mac_address" value="{{ old('mac_address', $macAddress) }}" placeholder="AA:BB:CC:DD:EE:FF" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label for="pppoe_username" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Username PPPoE</label>
                    <input type="text" name="pppoe_username" id="pppoe_username" value="{{ old('pppoe_username', $device?->pppoe_username) }}" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label for="pppoe_password" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Password PPPoE</label>
                    <input type="text" name="pppoe_password" id="pppoe_password" value="{{ $canViewSensitiveDeviceFields ? old('pppoe_password', $device?->pppoe_password) : '' }}" {{ ! $canViewSensitiveDeviceFields ? 'placeholder=********' : '' }} class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label for="wifi_ssid" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">SSID WiFi</label>
                    <input type="text" name="wifi_ssid" id="wifi_ssid" value="{{ old('wifi_ssid', $ssid) }}" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label for="wifi_password" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Password WiFi</label>
                    <input type="text" name="wifi_password" id="wifi_password" value="{{ $canViewSensitiveDeviceFields ? old('wifi_password', $device?->wifi_password) : '' }}" {{ ! $canViewSensitiveDeviceFields ? 'placeholder=********' : '' }} class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label for="ip_address" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">IP Address</label>
                    <input type="text" name="ip_address" id="ip_address" value="{{ $canViewSensitiveDeviceFields ? old('ip_address', $ipAddress) : '' }}" {{ ! $canViewSensitiveDeviceFields ? 'placeholder=********' : 'placeholder=192.168.1.1' }} class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label for="vlan_id" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">VLAN ID</label>
                    <input type="text" name="vlan_id" id="vlan_id" value="{{ $canViewSensitiveDeviceFields ? old('vlan_id', $device?->vlan_id ?? $customer->vlan_id) : '' }}" {{ ! $canViewSensitiveDeviceFields ? 'placeholder=********' : '' }} class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label for="odp" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">ODP</label>
                    <input type="text" name="odp" id="odp" value="{{ old('odp', $odpCode) }}" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label for="odp_port" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Port ODP</label>
                    <input type="text" name="odp_port" id="odp_port" value="{{ old('odp_port', $odpPort) }}" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label for="olt_number" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Nomor OLT</label>
                    <input type="text" name="olt_number" id="olt_number" value="{{ old('olt_number', $tech?->olt_number) }}" placeholder="Contoh: OLT-01" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label for="olt_slot" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Slot OLT</label>
                    <input type="text" name="olt_slot" id="olt_slot" value="{{ old('olt_slot', $tech?->olt_slot) }}" placeholder="Contoh: 0/1" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label for="olt_port_tech" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Nomor Port OLT</label>
                    <input type="text" name="olt_port" id="olt_port_tech" value="{{ old('olt_port', $tech?->olt_port) }}" placeholder="Contoh: 1" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label for="tech_vlan" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">VLAN (Jaringan)</label>
                    <input type="text" name="tech_vlan" id="tech_vlan" value="{{ $canViewSensitiveDeviceFields ? old('tech_vlan', $tech?->vlan) : '' }}" {{ ! $canViewSensitiveDeviceFields ? 'placeholder=********' : 'placeholder=Contoh:\ 100' }} class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>

                {{-- === SECTION: Detail Teknis Tambahan (dari gap migrasi) === --}}
                <div class="md:col-span-2 pt-3 mt-3 border-t border-slate-200 dark:border-slate-700">
                    <h4 class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-3">Detail Teknis Tambahan</h4>
                </div>

                <div>
                    <label for="passive_device" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Perangkat Pasif (Merk/Tipe)</label>
                    <input type="text" name="passive_device" id="passive_device" value="{{ old('passive_device', $tech?->passive_device) }}" placeholder="Contoh: Antena Grid 25dBi" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label for="passive_device_type" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Jenis Perangkat Pasif</label>
                    <select name="passive_device_type" id="passive_device_type" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-200">
                        <option value="">Pilih jenis</option>
                        @foreach($passiveDeviceCategories as $category)
                            <option value="{{ $category->code }}" {{ old('passive_device_type', $tech?->passive_device_type) === $category->code ? 'selected' : '' }}>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="passive_device_qty" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Jumlah / Panjang</label>
                    <input type="text" name="passive_device_qty" id="passive_device_qty" value="{{ old('passive_device_qty', $tech?->passive_device_qty) }}" placeholder="Contoh: 150 meter / 1 pasang" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-200">
                </div>
                <div class="md:col-span-2">
                    <label for="passive_device_note" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Catatan Detail Perangkat Pasif</label>
                    <input type="text" name="passive_device_note" id="passive_device_note" value="{{ old('passive_device_note', $tech?->passive_device_note) }}" placeholder="Contoh: Dropcore 90m + Router ZTE 1 + Klem dan isolasi" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label for="router_number" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Nomor Router / MAC</label>
                    <input type="text" name="router_number" id="router_number" value="{{ old('router_number', $tech?->router_number) }}" placeholder="Contoh: RTR-001" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label for="branch_number" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Nomor Cabang (IDCABANG)</label>
                    <input type="text" name="branch_number" id="branch_number" value="{{ old('branch_number', $tech?->branch_number) }}" placeholder="Contoh: CB001" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label for="pop_number" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Nomor POP (IDWILAYAH)</label>
                    <input type="text" name="pop_number" id="pop_number" value="{{ old('pop_number', $tech?->pop_number) }}" placeholder="Contoh: WL0001" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label for="initial_attenuation_dev" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Redaman Awal (dBm)</label>
                    <input type="text" name="initial_attenuation" id="initial_attenuation_dev" value="{{ old('initial_attenuation', $tech?->initial_attenuation) }}" placeholder="-19.50" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label for="actual_attenuation_dev" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Redaman Aktual (dBm)</label>
                    <input type="text" name="actual_attenuation" id="actual_attenuation_dev" value="{{ old('actual_attenuation', $tech?->actual_attenuation) }}" placeholder="-21.20" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>
                {{-- === END SECTION: Detail Teknis Tambahan === --}}

                <div>
                    <label for="signal_rx_power" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Redaman Perangkat (dBm)</label>
                    @php
                        $sigPower = old('signal_rx_power', $device?->signal_rx_power);
                        if (! $device && $tech) {
                            $rawSig = $tech->fiber_signal ?? $tech->wireless_signal;
                            if (is_numeric($rawSig)) {
                                $sigPower = $rawSig;
                            } elseif ($rawSig) {
                                preg_match('/-\d+(?:\.\d+)?/', $rawSig, $matches);
                                if (! empty($matches)) {
                                    $sigPower = $matches[0];
                                }
                            }
                        }
                    @endphp
                    <input type="number" step="0.01" name="signal_rx_power" id="signal_rx_power" value="{{ $sigPower }}" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>
                <div class="md:col-span-2">
                    <label for="technical_note" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Catatan Teknis</label>
                    <textarea name="technical_note" id="technical_note" rows="3" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-200">{{ old('technical_note', $technicalNote) }}</textarea>
                </div>
            </div>
            <div class="px-5 py-3.5 bg-slate-50 dark:bg-slate-900/50 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-2 text-xs">
                <button type="button" onclick="closeDeviceModal()" class="px-4 py-2 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 font-semibold rounded-lg shadow-sm cursor-pointer">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white font-semibold rounded-lg shadow-sm cursor-pointer">
                    Simpan Data Perangkat
                </button>
            </div>
        </form>
    </div>
</div>
@endcan
