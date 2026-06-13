<div class="flex items-center justify-between pb-4 border-b border-slate-100">
    <div>
        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Data Survey Teknis Pelanggan</h3>
        <p class="text-xs text-slate-500 mt-0.5">Informasi hasil survey lapangan untuk persiapan instalasi.</p>
    </div>
    @can('fill_survey')
        <button onclick="openSurveyModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white rounded-md transition-colors text-xs font-semibold shadow-sm cursor-pointer focus:outline-none">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            Isi Hasil Survey
        </button>
    @endcan
</div>

@if($customer->surveys->count() > 0)
    <div class="space-y-6">
        @foreach($customer->surveys()->latest()->get() as $survey)
            <div class="border border-slate-200 rounded-lg overflow-hidden">
                <div class="px-5 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-700">Survey - {{ \App\Support\IndonesianDate::date($survey->survey_date) }}</span>
                    @php
                        $statusClass = match($survey->survey_status) {
                            'completed' => 'bg-green-100 text-green-800 border-green-200',
                            'failed' => 'bg-red-100 text-red-800 border-red-200',
                            'pending' => 'bg-amber-100 text-amber-800 border-amber-200',
                            default => 'bg-slate-100 text-slate-800 border-slate-200',
                        };
                    @endphp
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wider {{ $statusClass }}">
                        {{ $survey->survey_status }}
                    </span>
                </div>
                <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
                    <div class="space-y-3">
                        <div class="flex justify-between border-b border-slate-50 py-1">
                            <span class="text-slate-500">Petugas Survey</span>
                            <span class="font-semibold text-slate-800">{{ $survey->technician->name ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between border-b border-slate-50 py-1">
                            <span class="text-slate-500">Waktu Survey</span>
                            <span class="font-semibold text-slate-800">{{ $survey->start_time ? substr($survey->start_time, 0, 5) : '-' }} - {{ $survey->end_time ? substr($survey->end_time, 0, 5) : '-' }}</span>
                        </div>
                        <div class="flex justify-between border-b border-slate-50 py-1">
                            <span class="text-slate-500">Estimasi Kabel</span>
                            <span class="font-semibold text-slate-800">{{ $survey->cable_estimation_meter ?? 0 }} Meter</span>
                        </div>
                        <div class="flex justify-between border-b border-slate-50 py-1">
                            <span class="text-slate-500">ODP Terdekat</span>
                            <span class="font-semibold text-slate-800">{{ $survey->nearest_odp ?? '-' }}</span>
                        </div>
                        <div class="pt-2">
                            <span class="block text-slate-500 mb-1">Kebutuhan Alat:</span>
                            <p class="p-3 bg-slate-50 rounded border border-slate-100 italic">{{ $survey->required_tools ?? 'Tidak ada catatan' }}</p>
                        </div>
                        <div class="pt-2">
                            <span class="block text-slate-500 mb-1">Catatan Survey:</span>
                            <p class="p-3 bg-slate-50 rounded border border-slate-100 italic">{{ $survey->survey_note ?? 'Tidak ada catatan' }}</p>
                        </div>
                    </div>
                    <div>
                        <span class="block text-slate-500 mb-2 font-bold uppercase text-[9px] tracking-wider">Foto Hasil Survey</span>
                        @if($survey->survey_photo)
                            <div class="border border-slate-200 rounded overflow-hidden shadow-sm">
                                <img src="{{ asset('storage/' . $survey->survey_photo) }}" alt="Foto Survey" class="w-full object-cover max-h-64">
                                <div class="p-2 bg-slate-50 text-center">
                                    <a href="{{ asset('storage/' . $survey->survey_photo) }}" target="_blank" class="text-[10px] font-bold text-sky-600 hover:text-sky-800">LIHAT FULL RESOLUSI</a>
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
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 20l-5.447-2.724A2 2 0 013 15.447V5.553a2 2 0 011.553-1.944L9 2 15 5l5.447-2.724A2 2 0 0123 4.224v9.894a2 2 0 01-1.553 1.944L15 19l-6 1z" />
        </svg>
        <h4 class="text-sm font-semibold text-slate-700">Belum ada data survey</h4>
        <p class="text-xs text-slate-500 mt-1">Silakan isi hasil survey lapangan melalui tombol di atas.</p>
    </div>
@endif

<!-- Modal Input Survey -->
@can('fill_survey')
<div id="survey-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-lg shadow-xl border border-slate-200 w-full max-w-2xl overflow-hidden transform transition-all">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Input Hasil Survey Lapangan</h3>
            <button onclick="closeSurveyModal()" class="text-slate-400 hover:text-slate-600 focus:outline-none cursor-pointer">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form action="{{ route('customers.survey.store', $customer->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Status Survey</label>
                    <div class="flex gap-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="survey_status" value="completed" class="text-sky-600 focus:ring-sky-500" checked>
                            <span class="ml-2 text-xs text-slate-700">Berhasil (Surveyed)</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="survey_status" value="failed" class="text-sky-600 focus:ring-sky-500">
                            <span class="ml-2 text-xs text-slate-700">Gagal / Dibatalkan</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label for="survey_date" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Tanggal Survey</label>
                    <input type="date" name="survey_date" id="survey_date" value="{{ date('Y-m-d') }}" required
                           class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs font-mono">
                </div>

                <div>
                    <label for="technician_id" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Petugas Survey</label>
                    <select name="technician_id" id="technician_id" required
                            class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs">
                        <option value="{{ auth()->id() }}">{{ auth()->user()->name }} (Saya)</option>
                    </select>
                </div>

                <div>
                    <label for="start_time" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Jam Mulai</label>
                    <input type="time" name="start_time" id="start_time" value="09:00"
                           class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs font-mono">
                </div>

                <div>
                    <label for="end_time" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Jam Selesai</label>
                    <input type="time" name="end_time" id="end_time" value="10:00"
                           class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs font-mono">
                </div>

                <div>
                    <label for="cable_estimation_meter" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Estimasi Kabel (Meter)</label>
                    <input type="number" name="cable_estimation_meter" id="cable_estimation_meter" value="0" min="0" required
                           class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs font-mono">
                </div>

                <div>
                    <label for="nearest_odp" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">ODP Terdekat</label>
                    <input type="text" name="nearest_odp" id="nearest_odp" placeholder="Contoh: ODP-SMN-01"
                           class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs font-mono">
                </div>

                <div class="md:col-span-2">
                    <label for="required_tools" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Kebutuhan Alat Khusus</label>
                    <textarea name="required_tools" id="required_tools" rows="2" placeholder="Sebutkan alat khusus jika dibutuhkan..."
                              class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs"></textarea>
                </div>

                <div class="md:col-span-2">
                    <label for="survey_note" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Catatan Hasil Survey</label>
                    <textarea name="survey_note" id="survey_note" rows="3" placeholder="Tuliskan catatan detail hasil survey lapangan..."
                              class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs"></textarea>
                </div>

                <div class="md:col-span-2">
                    <label for="survey_photo" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Foto Lokasi / ODP</label>
                    <input type="file" name="survey_photo" id="survey_photo" accept="image/*"
                           class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs">
                    <p class="text-[9px] text-slate-400 mt-1 italic">Format JPG/PNG, maksimal 2MB.</p>
                </div>
            </div>
            <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-100 flex justify-end gap-2 text-xs">
                <button type="button" onclick="closeSurveyModal()" class="px-3 py-1.5 border border-slate-200 text-slate-700 bg-white hover:bg-slate-50 font-semibold rounded-md shadow-sm transition-colors cursor-pointer">
                    Batal
                </button>
                <button type="submit" class="px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white font-semibold rounded-md shadow-sm transition-colors cursor-pointer">
                    Simpan Hasil Survey
                </button>
            </div>
        </form>
    </div>
</div>
@endcan
