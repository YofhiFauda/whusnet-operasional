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
                            <span class="font-semibold text-slate-800">{{ $installation->technician->name ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between border-b border-slate-50 py-1">
                            <span class="text-slate-500">Jadwal</span>
                            <span class="font-semibold text-slate-800">{{ \App\Support\IndonesianDate::date($installation->scheduled_date) }} {{ $installation->scheduled_time ? substr($installation->scheduled_time, 0, 5) : '' }}</span>
                        </div>
                        <div class="flex justify-between border-b border-slate-50 py-1">
                            <span class="text-slate-500">Tanggal Selesai</span>
                            <span class="font-semibold text-slate-800">{{ $installation->finished_date ? \App\Support\IndonesianDate::date($installation->finished_date) : '-' }}</span>
                        </div>
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
