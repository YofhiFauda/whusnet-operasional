<div class="flex items-center justify-between pb-4 border-b border-slate-100">
    <div>
        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Data Pemasangan Pelanggan</h3>
        <p class="text-xs text-slate-500 mt-0.5">Jadwal, teknisi, status, foto, dan catatan hasil pemasangan pelanggan.</p>
    </div>
    @can('fill_installation')
        <button onclick="openInstallationModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white rounded-md transition-colors text-xs font-semibold shadow-sm cursor-pointer focus:outline-none">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            Isi Data Pemasangan
        </button>
    @endcan
</div>

@if($customer->installations->count() > 0)
    <div class="space-y-6">
        @foreach($customer->installations()->latest()->get() as $installation)
            <div class="border border-slate-200 rounded-lg overflow-hidden">
                <div class="px-5 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-700">Pemasangan - {{ \App\Support\IndonesianDate::date($installation->scheduled_date) }}</span>
                    @php
                        $statusClass = match($installation->installation_status) {
                            'completed' => 'bg-green-100 text-green-800 border-green-200',
                            'failed' => 'bg-red-100 text-red-800 border-red-200',
                            'in_progress' => 'bg-sky-100 text-sky-800 border-sky-200',
                            'scheduled' => 'bg-amber-100 text-amber-800 border-amber-200',
                            default => 'bg-slate-100 text-slate-800 border-slate-200',
                        };
                    @endphp
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wider {{ $statusClass }}">
                        {{ str_replace('_', ' ', $installation->installation_status) }}
                    </span>
                </div>
                <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
                    <div class="space-y-3">
                        <div class="flex justify-between border-b border-slate-50 py-1">
                            <span class="text-slate-500">Teknisi Pemasangan</span>
                            <span class="font-semibold text-slate-800">{{ $installation->technicians ?: ($installation->technician->name ?? '-') }}</span>
                        </div>
                        <div class="flex justify-between border-b border-slate-50 py-1">
                            <span class="text-slate-500">Jadwal</span>
                            <span class="font-semibold text-slate-800">{{ \App\Support\IndonesianDate::date($installation->scheduled_date) }} {{ $installation->scheduled_time ? substr($installation->scheduled_time, 0, 5) : '' }}</span>
                        </div>
                        <div class="flex justify-between border-b border-slate-50 py-1">
                            <span class="text-slate-500">Tanggal Selesai</span>
                            <span class="font-semibold text-slate-800">{{ $installation->finished_date ? \App\Support\IndonesianDate::date($installation->finished_date) : '-' }}</span>
                        </div>
                        @if($installation->start_time || $installation->end_time)
                            <div class="flex justify-between border-b border-slate-50 py-1">
                                <span class="text-slate-500">Jam Pemasangan</span>
                                <span class="font-semibold text-slate-800 font-mono">{{ $installation->start_time ? substr($installation->start_time, 0, 5) : '-' }} - {{ $installation->end_time ? substr($installation->end_time, 0, 5) : '-' }}</span>
                            </div>
                        @endif
                        @if($installation->fop_id)
                            <div class="flex justify-between border-b border-slate-50 py-1">
                                <span class="text-slate-500">ID FOP</span>
                                <span class="font-semibold text-slate-800 font-mono">{{ $installation->fop_id }}</span>
                            </div>
                        @endif
                        @if($installation->assigned_at)
                            <div class="flex justify-between border-b border-slate-50 py-1">
                                <span class="text-slate-500">Waktu Penugasan</span>
                                <span class="font-semibold text-slate-800 font-mono">{{ \App\Support\IndonesianDate::dateTime($installation->assigned_at) }}</span>
                            </div>
                        @endif
                        <div class="pt-2">
                            <span class="block text-slate-500 mb-1">Catatan Pemasangan:</span>
                            <p class="p-3 bg-slate-50 rounded border border-slate-100 italic">{{ $installation->installation_note ?? 'Tidak ada catatan' }}</p>
                        </div>
                    </div>
                    <div>
                        <span class="block text-slate-500 mb-2 font-bold uppercase text-[9px] tracking-wider">Foto Pemasangan</span>
                        @if($installation->installation_photo)
                            <div class="border border-slate-200 rounded overflow-hidden shadow-sm">
                                <img src="{{ asset('storage/' . $installation->installation_photo) }}" alt="Foto Pemasangan" class="w-full object-cover max-h-64">
                                <div class="p-2 bg-slate-50 text-center">
                                    <a href="{{ asset('storage/' . $installation->installation_photo) }}" target="_blank" class="text-[10px] font-bold text-sky-600 hover:text-sky-800">LIHAT FULL RESOLUSI</a>
                                </div>
                            </div>
                        @else
                            <div class="h-48 bg-slate-50 border border-dashed border-slate-200 rounded flex flex-col items-center justify-center text-slate-400">
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
    <div class="py-12 text-center text-slate-400 bg-slate-50/20 border border-dashed border-slate-200 rounded-lg">
        <svg class="mx-auto h-12 w-12 text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4" />
        </svg>
        <h4 class="text-sm font-semibold text-slate-700">Belum ada data pemasangan</h4>
        <p class="text-xs text-slate-500 mt-1">Silakan isi jadwal atau hasil pemasangan melalui tombol di atas.</p>
    </div>
@endif

@if($customer->customerTechnicalDetail)
    @php
        $tech = $customer->customerTechnicalDetail;
    @endphp
    @if($tech->test_date || $tech->test_download || $tech->test_upload || $tech->latency_ms !== null)
        <div class="mt-8 pt-6 border-t border-slate-200">
            <div>
                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Laporan Hasil Pengujian Layanan (Speedtest)</h3>
                <p class="text-xs text-slate-500 mt-0.5">Metrik kualitas koneksi, kecepatan upload/download, latensi, dan kesesuaian profil paket.</p>
            </div>
            
            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                <!-- Speed test info -->
                <div class="border border-slate-200 rounded-lg p-4 bg-slate-50 flex flex-col justify-between">
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">Hasil Pengujian Kecepatan</span>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center border-b border-slate-100 pb-1">
                                <span class="text-slate-500">Download</span>
                                <span class="font-bold text-slate-800 font-mono text-sm">{{ $tech->test_download !== null ? number_format($tech->test_download, 2) . ' Mbps' : '-' }}</span>
                            </div>
                            <div class="flex justify-between items-center border-b border-slate-100 pb-1">
                                <span class="text-slate-500">Upload</span>
                                <span class="font-bold text-slate-800 font-mono text-sm">{{ $tech->test_upload !== null ? number_format($tech->test_upload, 2) . ' Mbps' : '-' }}</span>
                            </div>
                            @if($tech->speed_conformity_percent !== null)
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-500">Kesesuaian Paket</span>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold font-mono {{ $tech->speed_conformity_percent >= 80 ? 'bg-green-100 text-green-800' : ($tech->speed_conformity_percent >= 50 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                        {{ number_format($tech->speed_conformity_percent, 1) }}%
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="mt-3 text-[10px] text-slate-400 font-mono">
                        Uji: {{ $tech->test_date ? \App\Support\IndonesianDate::date($tech->test_date) : '-' }} {{ $tech->test_time ? substr($tech->test_time, 0, 5) : '' }}
                    </div>
                </div>

                <!-- Signal & ping info -->
                <div class="border border-slate-200 rounded-lg p-4 bg-slate-50 flex flex-col justify-between">
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">Kualitas & Kinerja Koneksi</span>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center border-b border-slate-100 pb-1">
                                <span class="text-slate-500">Latency (Ping)</span>
                                <span class="font-semibold text-slate-800 font-mono">{{ $tech->latency_ms !== null ? number_format($tech->latency_ms, 1) . ' ms' : '-' }}</span>
                            </div>
                            <div class="flex justify-between items-center border-b border-slate-100 pb-1">
                                <span class="text-slate-500">Jitter</span>
                                <span class="font-semibold text-slate-800 font-mono">{{ $tech->jitter_ms !== null ? number_format($tech->jitter_ms, 1) . ' ms' : '-' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-slate-500">Packet Loss</span>
                                <span class="font-semibold text-slate-800 font-mono">{{ $tech->packet_loss_percent !== null ? number_format($tech->packet_loss_percent, 2) . '%' : '0.00%' }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center justify-between">
                        <span class="text-[10px] text-slate-400 uppercase tracking-wider font-bold">Skor Sinyal:</span>
                        <div class="flex gap-0.5 text-amber-500">
                            @if($tech->quality_score)
                                @for($i = 1; $i <= 5; $i++)
                                    <svg class="h-3 w-3 {{ $i <= $tech->quality_score ? 'fill-current' : 'text-slate-300' }}" viewBox="0 0 20 20" fill="none" stroke="currentColor">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                @endfor
                            @else
                                <span class="text-[10px] font-semibold text-slate-500">-</span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Speedtest Photo -->
                <div class="border border-slate-200 rounded-lg p-4 bg-slate-50 flex flex-col justify-between">
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">Foto Hasil Speedtest</span>
                        @if($tech->speedtest_photo)
                            <div class="border border-slate-200 rounded overflow-hidden shadow-sm max-h-24 bg-white relative group">
                                <img src="{{ asset('storage/' . $tech->speedtest_photo) }}" alt="Foto Speedtest" class="w-full object-cover max-h-24">
                                <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                                    <a href="{{ asset('storage/' . $tech->speedtest_photo) }}" target="_blank" class="text-[9px] bg-white text-slate-800 font-bold px-2 py-1 rounded shadow">LIHAT FULL</a>
                                </div>
                            </div>
                        @else
                            <div class="h-20 bg-white border border-dashed border-slate-200 rounded flex flex-col items-center justify-center text-slate-400">
                                <svg class="h-6 w-6 opacity-40 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span class="text-[9px] font-semibold">Tidak Ada Foto Speedtest</span>
                            </div>
                        @endif
                    </div>
                    @if($tech->speedtest_photo)
                        <div class="mt-2 text-right">
                            <a href="{{ asset('storage/' . $tech->speedtest_photo) }}" target="_blank" class="text-[10px] font-bold text-sky-600 hover:text-sky-800">Buka Gambar ↗</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
@endif

@can('fill_installation')
<div id="installation-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-lg shadow-xl border border-slate-200 w-full max-w-2xl overflow-hidden transform transition-all">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Input Data Pemasangan</h3>
            <button onclick="closeInstallationModal()" class="text-slate-400 hover:text-slate-600 focus:outline-none cursor-pointer">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form action="{{ route('customers.installation.store', $customer->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Status Pemasangan</label>
                    <div class="flex flex-wrap gap-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="installation_status" value="scheduled" class="text-sky-600 focus:ring-sky-500" checked>
                            <span class="ml-2 text-xs text-slate-700">Terjadwal</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="installation_status" value="in_progress" class="text-sky-600 focus:ring-sky-500">
                            <span class="ml-2 text-xs text-slate-700">Proses</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="installation_status" value="completed" class="text-sky-600 focus:ring-sky-500">
                            <span class="ml-2 text-xs text-slate-700">Selesai</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="installation_status" value="failed" class="text-sky-600 focus:ring-sky-500">
                            <span class="ml-2 text-xs text-slate-700">Gagal</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label for="scheduled_date" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Tanggal Jadwal</label>
                    <input type="date" name="scheduled_date" id="scheduled_date" value="{{ date('Y-m-d') }}" required
                           class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs font-mono">
                </div>

                <div>
                    <label for="scheduled_time" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Jam Jadwal</label>
                    <input type="time" name="scheduled_time" id="scheduled_time" value="09:00"
                           class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs font-mono">
                </div>

                <div>
                    <label for="installation_technician_id" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Teknisi Pemasangan</label>
                    <select name="technician_id" id="installation_technician_id" required
                            class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs">
                        <option value="{{ auth()->id() }}">{{ auth()->user()->name }} (Saya)</option>
                    </select>
                </div>

                <div>
                    <label for="finished_date" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Tanggal Selesai</label>
                    <input type="date" name="finished_date" id="finished_date"
                           class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs font-mono">
                </div>

                <div class="md:col-span-2">
                    <label for="installation_note" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Catatan Pemasangan</label>
                    <textarea name="installation_note" id="installation_note" rows="3" placeholder="Tuliskan catatan pemasangan pelanggan..."
                              class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs"></textarea>
                </div>

                <div class="md:col-span-2">
                    <label for="installation_photo" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Foto Pemasangan</label>
                    <input type="file" name="installation_photo" id="installation_photo" accept="image/*"
                           class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs">
                    <p class="text-[9px] text-slate-400 mt-1 italic">Format JPG/PNG, maksimal 2MB.</p>
                </div>
            </div>
            <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-100 flex justify-end gap-2 text-xs">
                <button type="button" onclick="closeInstallationModal()" class="px-3 py-1.5 border border-slate-200 text-slate-700 bg-white hover:bg-slate-50 font-semibold rounded-md shadow-sm transition-colors cursor-pointer">
                    Batal
                </button>
                <button type="submit" class="px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white font-semibold rounded-md shadow-sm transition-colors cursor-pointer">
                    Simpan Data Pemasangan
                </button>
            </div>
        </form>
    </div>
</div>
@endcan
