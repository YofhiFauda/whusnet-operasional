<div class="flex items-center justify-between pb-4 border-b border-border">
    <div>
        <h3 class="text-sm font-bold text-text-main uppercase tracking-wider">Data Pemasangan Pelanggan</h3>
        <p class="text-xs text-text-muted mt-0.5">Jadwal, teknisi, status, foto, dan catatan hasil pemasangan pelanggan.</p>
    </div>
    @can('customers.detail.installation.update')
        <div class="flex gap-2">
            <button onclick="openTestReportModal()" class="btn-secondary inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold shadow-sm">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                Isi Laporan Uji (Speedtest)
            </button>
            <button onclick="openInstallationModal()" class="btn-primary inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold shadow-sm">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Isi Data Pemasangan
            </button>
        </div>
    @endcan
</div>

@if($customer->installations->count() > 0)
    <div class="space-y-6">
        @foreach($customer->installations()->latest()->get() as $installation)
            <div class="border border-border rounded-lg overflow-hidden">
                <div class="px-5 py-3 bg-surface-muted border-b border-border flex items-center justify-between">
                    <span class="text-xs font-bold text-text-main">Pemasangan - {{ \App\Support\IndonesianDate::date($installation->scheduled_date) }}</span>
                    @php
                        $statusLabel = match($installation->installation_status) {
                            'completed' => 'Selesai',
                            'failed' => 'Gagal',
                            'in_progress' => 'Proses',
                            'scheduled' => 'Terjadwal',
                            default => $installation->installation_status,
                        };
                        $statusStyle = match($installation->installation_status) {
                            'completed' => 'background:var(--color-success-bg); color:var(--color-success); border-color:var(--color-success-border);',
                            'failed' => 'background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border);',
                            'in_progress' => 'background:var(--color-info-bg); color:var(--color-info); border-color:var(--color-info-border);',
                            'scheduled' => 'background:var(--color-warning-bg); color:var(--color-warning); border-color:var(--color-warning-border);',
                            default => 'background:var(--color-surface-muted); color:var(--color-text-main); border-color:var(--color-border);',
                        };
                    @endphp
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wider" style="{{ $statusStyle }}">
                        {{ $statusLabel }}
                    </span>
                </div>
                <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
                    <div class="space-y-3">
                        <div class="flex justify-between border-b border-border py-1">
                            <span class="text-text-muted">Teknisi 1 (Utama)</span>
                            <span class="font-semibold text-text-main">{{ $installation->technicians ?: ($installation->technician->name ?? '-') }}</span>
                        </div>
                        @if($installation->technician2)
                        <div class="flex justify-between border-b border-border py-1">
                            <span class="text-text-muted">Teknisi 2</span>
                            <span class="font-semibold text-text-main">{{ $installation->technician2->name }}</span>
                        </div>
                        @endif
                        @if($installation->technician3)
                        <div class="flex justify-between border-b border-border py-1">
                            <span class="text-text-muted">Teknisi 3</span>
                            <span class="font-semibold text-text-main">{{ $installation->technician3->name }}</span>
                        </div>
                        @endif
                        <div class="flex justify-between border-b border-border py-1">
                            <span class="text-text-muted">Jadwal</span>
                            <span class="font-semibold text-text-main">{{ \App\Support\IndonesianDate::date($installation->scheduled_date) }} {{ $installation->scheduled_time ? substr($installation->scheduled_time, 0, 5) : '' }}</span>
                        </div>
                        <div class="flex justify-between border-b border-border py-1">
                            <span class="text-text-muted">Tanggal Selesai</span>
                            <span class="font-semibold text-text-main">{{ $installation->finished_date ? \App\Support\IndonesianDate::date($installation->finished_date) : '-' }}</span>
                        </div>
                        @if($installation->start_time || $installation->end_time)
                            <div class="flex justify-between border-b border-border py-1">
                                <span class="text-text-muted">Jam Pemasangan</span>
                                <span class="font-semibold text-text-main font-mono">{{ $installation->start_time ? substr($installation->start_time, 0, 5) : '-' }} - {{ $installation->end_time ? substr($installation->end_time, 0, 5) : '-' }}</span>
                            </div>
                        @endif
                        @if($installation->fop_id)
                            <div class="flex justify-between border-b border-border py-1">
                                <span class="text-text-muted">ID FOP</span>
                                <span class="font-semibold text-text-main font-mono">{{ $installation->fop_id }}</span>
                            </div>
                        @endif
                        @if($installation->assigned_at)
                            <div class="flex justify-between border-b border-border py-1">
                                <span class="text-text-muted">Waktu Penugasan</span>
                                <span class="font-semibold text-text-main font-mono">{{ \App\Support\IndonesianDate::dateTime($installation->assigned_at) }}</span>
                            </div>
                        @endif
                        <div class="pt-2">
                            <span class="block text-text-muted mb-1">Catatan Pemasangan:</span>
                            <p class="p-3 bg-surface-muted rounded border border-border italic">{{ $installation->installation_note ?? 'Tidak ada catatan' }}</p>
                        </div>
                    </div>
                    <div>
                        <span class="block text-text-muted mb-2 font-bold uppercase text-[9px] tracking-wider">Foto Pemasangan</span>
                        @if($installation->installation_photo)
                            <div class="border border-border rounded overflow-hidden shadow-sm">
                                <img src="{{ asset('storage/' . $installation->installation_photo) }}" alt="Foto Pemasangan" class="w-full object-cover max-h-64">
                                <div class="p-2 bg-surface-muted text-center">
                                    <a href="{{ asset('storage/' . $installation->installation_photo) }}" target="_blank" class="text-[10px] font-bold" style="color:var(--color-info)">LIHAT FULL RESOLUSI</a>
                                </div>
                            </div>
                        @else
                            <div class="h-48 bg-surface-muted border border-dashed border-border rounded flex flex-col items-center justify-center text-text-muted">
                                <svg class="h-10 w-10 mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span class="text-[10px] font-bold uppercase tracking-wider">Tidak Ada Foto</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@else
    <div class="py-12 text-center text-text-muted bg-surface-muted/20 border border-dashed border-border rounded-lg">
        <svg class="mx-auto h-12 w-12 text-text-muted mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4" />
        </svg>
        <h4 class="text-sm font-semibold text-text-main">Belum ada data pemasangan</h4>
        <p class="text-xs text-text-muted mt-1">Silakan isi jadwal atau hasil pemasangan melalui tombol di atas.</p>
    </div>
@endif

@if($customer->customerTechnicalDetail)
    @php
        $tech = $customer->customerTechnicalDetail;
    @endphp
    @if($tech->test_date || $tech->test_download || $tech->test_upload || $tech->latency_ms !== null)
        <div class="mt-8 pt-6 border-t border-border">
            <div>
                <h3 class="text-sm font-bold text-text-main uppercase tracking-wider">Laporan Hasil Pengujian Layanan (Speedtest)</h3>
                <p class="text-xs text-text-muted mt-0.5">Metrik kualitas koneksi, kecepatan upload/download, latensi, dan kesesuaian profil paket.</p>
            </div>
            
            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                <!-- Speed test info -->
                <div class="border border-border rounded-lg p-4 bg-surface-muted flex flex-col justify-between">
                    <div>
                        <span class="text-[10px] font-bold text-text-muted uppercase tracking-wider block mb-2">Hasil Pengujian Kecepatan</span>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center border-b border-border pb-1">
                                <span class="text-text-muted">Download</span>
                                <span class="font-bold text-text-main font-mono text-sm">{{ $tech->test_download !== null ? number_format($tech->test_download, 2) . ' Mbps' : '-' }}</span>
                            </div>
                            <div class="flex justify-between items-center border-b border-border pb-1">
                                <span class="text-text-muted">Upload</span>
                                <span class="font-bold text-text-main font-mono text-sm">{{ $tech->test_upload !== null ? number_format($tech->test_upload, 2) . ' Mbps' : '-' }}</span>
                            </div>
                            @if($tech->speed_conformity_percent !== null)
                                @php
                                    $conformityStyle = $tech->speed_conformity_percent >= 80
                                        ? 'background:var(--color-success-bg); color:var(--color-success);'
                                        : ($tech->speed_conformity_percent >= 50
                                            ? 'background:var(--color-warning-bg); color:var(--color-warning);'
                                            : 'background:var(--color-error-bg); color:var(--color-error);');
                                @endphp
                                <div class="flex justify-between items-center">
                                    <span class="text-text-muted">Kesesuaian Paket</span>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold font-mono" style="{{ $conformityStyle }}">
                                        {{ number_format($tech->speed_conformity_percent, 1) }}%
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="mt-3 text-[10px] text-text-muted font-mono">
                        Uji: {{ $tech->test_date ? \App\Support\IndonesianDate::date($tech->test_date) : '-' }} {{ $tech->test_time ? substr($tech->test_time, 0, 5) : '' }}
                    </div>
                </div>

                <!-- Signal & ping info -->
                <div class="border border-border rounded-lg p-4 bg-surface-muted flex flex-col justify-between">
                    <div>
                        <span class="text-[10px] font-bold text-text-muted uppercase tracking-wider block mb-2">Kualitas & Kinerja Koneksi</span>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center border-b border-border pb-1">
                                <span class="text-text-muted">Latency (Ping)</span>
                                <span class="font-semibold text-text-main font-mono">{{ $tech->latency_ms !== null ? number_format($tech->latency_ms, 1) . ' ms' : '-' }}</span>
                            </div>
                            <div class="flex justify-between items-center border-b border-border pb-1">
                                <span class="text-text-muted">Jitter</span>
                                <span class="font-semibold text-text-main font-mono">{{ $tech->jitter_ms !== null ? number_format($tech->jitter_ms, 1) . ' ms' : '-' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-text-muted">Packet Loss</span>
                                <span class="font-semibold text-text-main font-mono">{{ $tech->packet_loss_percent !== null ? number_format($tech->packet_loss_percent, 2) . '%' : '0.00%' }}</span>
                            </div>
                            @if($tech->initial_attenuation || $tech->actual_attenuation)
                                <div class="flex justify-between items-center border-t border-border mt-1 pt-1">
                                    <span class="text-text-muted">Redaman (Awal / Aktual)</span>
                                    <span class="font-semibold text-text-main font-mono text-[10px]">{{ $tech->initial_attenuation ?: '-' }} / {{ $tech->actual_attenuation ?: '-' }} dBm</span>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="mt-3 flex items-center justify-between">
                        <span class="text-[10px] text-text-muted uppercase tracking-wider font-bold">Skor Sinyal:</span>
                        <div class="flex gap-0.5" style="color:var(--color-warning)">
                            @if($tech->quality_score)
                                @for($i = 1; $i <= 5; $i++)
                                    <svg class="h-3 w-3 {{ $i <= $tech->quality_score ? 'fill-current' : 'text-text-muted' }}" viewBox="0 0 20 20" fill="none" stroke="currentColor">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                @endfor
                            @else
                                <span class="text-[10px] font-semibold text-text-muted">-</span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Speedtest Photo -->
                <div class="border border-border rounded-lg p-4 bg-surface-muted flex flex-col justify-between">
                    <div>
                        <span class="text-[10px] font-bold text-text-muted uppercase tracking-wider block mb-2">Foto Hasil Speedtest</span>
                        @if($tech->speedtest_photo)
                            <div class="border border-border rounded overflow-hidden shadow-sm max-h-24 bg-white relative group">
                                <img src="{{ asset('storage/' . $tech->speedtest_photo) }}" alt="Foto Speedtest" class="w-full object-cover max-h-24">
                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                                    <a href="{{ asset('storage/' . $tech->speedtest_photo) }}" target="_blank" class="text-[9px] bg-white text-text-main font-bold px-2 py-1 rounded shadow">LIHAT FULL</a>
                                </div>
                            </div>
                        @else
                            <div class="h-20 bg-white border border-dashed border-border rounded flex flex-col items-center justify-center text-text-muted">
                                <svg class="h-6 w-6 opacity-40 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span class="text-[9px] font-semibold">Tidak Ada Foto Speedtest</span>
                            </div>
                        @endif
                    </div>
                    @if($tech->speedtest_photo)
                        <div class="mt-2 text-right">
                            <a href="{{ asset('storage/' . $tech->speedtest_photo) }}" target="_blank" class="text-[10px] font-bold" style="color:var(--color-info)">Buka Gambar ↗</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
@endif

@can('fill_installation')
<div id="installation-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-lg shadow-xl border border-border w-full max-w-2xl overflow-hidden transform transition-all">
        <div class="px-5 py-4 border-b border-border flex items-center justify-between">
            <h3 class="text-xs font-bold text-text-main uppercase tracking-wider">Input Data Pemasangan</h3>
            <button onclick="closeInstallationModal()" class="text-text-muted hover:text-text-muted focus:outline-none cursor-pointer">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form action="{{ route('customers.installation.store', $customer->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Status Pemasangan</label>
                    <div class="flex flex-wrap gap-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="installation_status" value="scheduled" style="accent-color:var(--color-info)" checked>
                            <span class="ml-2 text-xs text-text-main">Terjadwal</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="installation_status" value="in_progress" style="accent-color:var(--color-info)">
                            <span class="ml-2 text-xs text-text-main">Proses</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="installation_status" value="completed" style="accent-color:var(--color-info)">
                            <span class="ml-2 text-xs text-text-main">Selesai</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="installation_status" value="failed" style="accent-color:var(--color-info)">
                            <span class="ml-2 text-xs text-text-main">Gagal</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label for="scheduled_date" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Tanggal Jadwal</label>
                    <input type="date" name="scheduled_date" id="scheduled_date" value="{{ date('Y-m-d') }}" required
                           class="w-full px-3 py-2 border border-border rounded-md shadow-sm focus:ring-primary/25 focus:border-primary text-xs font-mono">
                </div>

                <div>
                    <label for="scheduled_time" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Jam Jadwal</label>
                    <input type="time" name="scheduled_time" id="scheduled_time" value="09:00"
                           class="w-full px-3 py-2 border border-border rounded-md shadow-sm focus:ring-primary/25 focus:border-primary text-xs font-mono">
                </div>

                <div>
                    <label for="start_time" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Waktu Mulai Pemasangan</label>
                    <input type="time" name="start_time" id="start_time"
                           class="w-full px-3 py-2 border border-border rounded-md shadow-sm focus:ring-primary/25 focus:border-primary text-xs font-mono">
                </div>

                <div>
                    <label for="end_time" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Waktu Selesai Pemasangan</label>
                    <input type="time" name="end_time" id="end_time"
                           class="w-full px-3 py-2 border border-border rounded-md shadow-sm focus:ring-primary/25 focus:border-primary text-xs font-mono">
                </div>

                <div>
                    <label for="finished_date" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Tanggal Selesai (Aktual)</label>
                    <input type="date" name="finished_date" id="finished_date"
                           class="w-full px-3 py-2 border border-border rounded-md shadow-sm focus:ring-primary/25 focus:border-primary text-xs font-mono">
                </div>

                <div>
                    <label for="fop_id_inst" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">ID FOP / Penugasan</label>
                    <input type="text" name="fop_id" id="fop_id_inst" placeholder="FOP-2026-..."
                           class="w-full px-3 py-2 border border-border rounded-md shadow-sm focus:ring-primary/25 focus:border-primary text-xs font-mono">
                </div>

                <div>
                    <label for="assigned_at_inst" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Waktu Penugasan Pemasangan</label>
                    <input type="datetime-local" name="assigned_at" id="assigned_at_inst"
                           class="w-full px-3 py-2 border border-border rounded-md shadow-sm focus:ring-primary/25 focus:border-primary text-xs font-mono">
                </div>

                <div>
                    <label for="installation_technician_id" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Teknisi 1 (Utama)</label>
                    <select name="technician_id" id="installation_technician_id" required
                            class="w-full px-3 py-2 border border-border rounded-md shadow-sm focus:ring-primary/25 focus:border-primary text-xs">
                        <option value="{{ auth()->id() }}">{{ auth()->user()->name }} (Saya)</option>
                        @if(isset($activeUsers))
                            @foreach($activeUsers->where('id', '!=', auth()->id()) as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <div>
                    <label for="technician_2_id" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Teknisi 2</label>
                    <select name="technician_2_id" id="technician_2_id"
                            class="w-full px-3 py-2 border border-border rounded-md shadow-sm focus:ring-primary/25 focus:border-primary text-xs">
                        <option value="">— Tidak ada —</option>
                        @if(isset($activeUsers))
                            @foreach($activeUsers as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <div>
                    <label for="technician_3_id" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Teknisi 3</label>
                    <select name="technician_3_id" id="technician_3_id"
                            class="w-full px-3 py-2 border border-border rounded-md shadow-sm focus:ring-primary/25 focus:border-primary text-xs">
                        <option value="">— Tidak ada —</option>
                        @if(isset($activeUsers))
                            @foreach($activeUsers as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label for="technicians" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Nama Tim Teknisi (Opsional)</label>
                    <input type="text" name="technicians" id="technicians" placeholder="Contoh: Tim Pemasangan - Budi, Andi"
                           class="w-full px-3 py-2 border border-border rounded-md shadow-sm focus:ring-primary/25 focus:border-primary text-xs">
                </div>

                <div class="md:col-span-2">
                    <label for="installation_note" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Catatan Pemasangan</label>
                    <textarea name="installation_note" id="installation_note" rows="3" placeholder="Tuliskan catatan pemasangan pelanggan..."
                              class="w-full px-3 py-2 border border-border rounded-md shadow-sm focus:ring-primary/25 focus:border-primary text-xs"></textarea>
                </div>

                <div class="md:col-span-2">
                    <label for="installation_photo" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Foto Pemasangan</label>
                    <input type="file" name="installation_photo" id="installation_photo" accept="image/*" capture="environment"
                           class="w-full px-3 py-2 border border-border rounded-md shadow-sm focus:ring-primary/25 focus:border-primary text-xs">
                    <p class="text-[9px] text-text-muted mt-1 italic">Format JPG/PNG, maksimal 2MB.</p>
                </div>
            </div>
            <div class="px-5 py-3.5 bg-surface-muted border-t border-border flex justify-end gap-2 text-xs">
                <button type="button" onclick="closeInstallationModal()" class="px-3 py-1.5 border border-border text-text-main bg-white hover:bg-surface-muted font-semibold rounded-md shadow-sm transition-colors cursor-pointer">
                    Batal
                </button>
                <button type="submit" class="btn-primary px-3 py-1.5 font-semibold shadow-sm">
                    Simpan Data Pemasangan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Input Laporan Uji (Speedtest) -->
<div id="test-report-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-lg shadow-xl border border-border w-full max-w-2xl overflow-hidden transform transition-all">
        <div class="px-5 py-4 border-b border-border flex items-center justify-between">
            <h3 class="text-xs font-bold text-text-main uppercase tracking-wider">Laporan Hasil Pengujian Layanan (Speedtest)</h3>
            <button onclick="closeTestReportModal()" class="text-text-muted hover:text-text-muted focus:outline-none cursor-pointer">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form action="{{ route('customers.test-report.store', $customer->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="test_date" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Tanggal Uji</label>
                    <input type="date" name="test_date" id="test_date" value="{{ date('Y-m-d') }}" required
                           class="w-full px-3 py-2 border border-border rounded-md shadow-sm focus:ring-primary/25 focus:border-primary text-xs font-mono">
                </div>

                <div>
                    <label for="test_time" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Jam Uji</label>
                    <input type="time" name="test_time" id="test_time" value="{{ date('H:i') }}"
                           class="w-full px-3 py-2 border border-border rounded-md shadow-sm focus:ring-primary/25 focus:border-primary text-xs font-mono">
                </div>

                <div>
                    <label for="test_download" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Speed Download (Mbps)</label>
                    <input type="number" step="0.01" name="test_download" id="test_download" required
                           class="w-full px-3 py-2 border border-border rounded-md shadow-sm focus:ring-primary/25 focus:border-primary text-xs font-mono">
                </div>

                <div>
                    <label for="test_upload" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Speed Upload (Mbps)</label>
                    <input type="number" step="0.01" name="test_upload" id="test_upload" required
                           class="w-full px-3 py-2 border border-border rounded-md shadow-sm focus:ring-primary/25 focus:border-primary text-xs font-mono">
                </div>

                <div>
                    <label for="latency_ms" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Latency (Ping ms)</label>
                    <input type="number" step="0.1" name="latency_ms" id="latency_ms"
                           class="w-full px-3 py-2 border border-border rounded-md shadow-sm focus:ring-primary/25 focus:border-primary text-xs font-mono">
                </div>

                <div>
                    <label for="jitter_ms" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Jitter (ms)</label>
                    <input type="number" step="0.1" name="jitter_ms" id="jitter_ms"
                           class="w-full px-3 py-2 border border-border rounded-md shadow-sm focus:ring-primary/25 focus:border-primary text-xs font-mono">
                </div>

                <div>
                    <label for="packet_loss_percent" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Packet Loss (%)</label>
                    <input type="number" step="0.01" name="packet_loss_percent" id="packet_loss_percent"
                           class="w-full px-3 py-2 border border-border rounded-md shadow-sm focus:ring-primary/25 focus:border-primary text-xs font-mono">
                </div>

                <div>
                    <label for="quality_score" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Skor Kualitas (1-5)</label>
                    <select name="quality_score" id="quality_score"
                            class="w-full px-3 py-2 border border-border rounded-md shadow-sm focus:ring-primary/25 focus:border-primary text-xs">
                        <option value="5">⭐⭐⭐⭐⭐ (Sangat Bagus)</option>
                        <option value="4">⭐⭐⭐⭐ (Bagus)</option>
                        <option value="3" selected>⭐⭐⭐ (Cukup)</option>
                        <option value="2">⭐⭐ (Kurang)</option>
                        <option value="1">⭐ (Buruk)</option>
                    </select>
                </div>

                <div>
                    <label for="initial_attenuation" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Redaman Awal (dBm)</label>
                    <input type="text" name="initial_attenuation" id="initial_attenuation" placeholder="-19.50"
                           class="w-full px-3 py-2 border border-border rounded-md shadow-sm focus:ring-primary/25 focus:border-primary text-xs font-mono">
                </div>

                <div>
                    <label for="actual_attenuation" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Redaman Aktual (dBm)</label>
                    <input type="text" name="actual_attenuation" id="actual_attenuation" placeholder="-21.20"
                           class="w-full px-3 py-2 border border-border rounded-md shadow-sm focus:ring-primary/25 focus:border-primary text-xs font-mono">
                </div>

                <div class="md:col-span-2">
                    <label for="speedtest_photo" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Foto Hasil Speedtest</label>
                    <input type="file" name="speedtest_photo" id="speedtest_photo" accept="image/*" capture="environment"
                           class="w-full px-3 py-2 border border-border rounded-md shadow-sm focus:ring-primary/25 focus:border-primary text-xs">
                    <p class="text-[9px] text-text-muted mt-1 italic">Format JPG/PNG, maksimal 2MB.</p>
                </div>
            </div>
            <div class="px-5 py-3.5 bg-surface-muted border-t border-border flex justify-end gap-2 text-xs">
                <button type="button" onclick="closeTestReportModal()" class="px-3 py-1.5 border border-border text-text-main bg-white hover:bg-surface-muted font-semibold rounded-md shadow-sm transition-colors cursor-pointer">
                    Batal
                </button>
                <button type="submit" class="btn-primary px-3 py-1.5 font-semibold shadow-sm">
                    Simpan Laporan Hasil Uji
                </button>
            </div>
        </form>
    </div>
</div>
@endcan


