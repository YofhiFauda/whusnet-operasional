<div class="flex items-center justify-between pb-4 border-b border-slate-100">
    <div>
        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Data Survey Teknis Pelanggan</h3>
        <p class="text-xs text-slate-500 mt-0.5">Informasi hasil survey lapangan untuk persiapan instalasi.</p>
    </div>
    @can('fill_survey')
        @if($customer->status === 'survey_in_progress')
            <a href="{{ route('customers.survey.report', $customer) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white rounded-md transition-colors text-xs font-semibold shadow-sm cursor-pointer focus:outline-none">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Lapor Hasil Survey
            </a>
        @elseif($customer->status === 'waiting_survey')
            <span class="text-[10px] text-amber-600 italic border border-amber-200 bg-amber-50 px-2 py-1 rounded">Mulai survey dari menu Antrean Survey terlebih dahulu.</span>
        @endif
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
                            <span class="text-slate-500">Petugas Survey 1</span>
                            <span class="font-semibold text-slate-800">{{ $survey->technician->name ?? $survey->surveyors ?? '-' }}</span>
                        </div>
                        @if($survey->surveyor2 || $survey->surveyor_2_id)
                        <div class="flex justify-between border-b border-slate-50 py-1">
                            <span class="text-slate-500">Petugas Survey 2</span>
                            <span class="font-semibold text-slate-800">{{ $survey->surveyor2->name ?? '-' }}</span>
                        </div>
                        @endif
                        @if($survey->surveyor3 || $survey->surveyor_3_id)
                        <div class="flex justify-between border-b border-slate-50 py-1">
                            <span class="text-slate-500">Petugas Survey 3</span>
                            <span class="font-semibold text-slate-800">{{ $survey->surveyor3->name ?? '-' }}</span>
                        </div>
                        @endif
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
                        @if($survey->assigned_at)
                        <div class="flex justify-between border-b border-slate-50 py-1">
                            <span class="text-slate-500">Tanggal Penugasan FOP</span>
                            <span class="font-semibold text-slate-800 font-mono">{{ \App\Support\IndonesianDate::date($survey->assigned_at) }}</span>
                        </div>
                        @endif
                        @if($survey->fop_id)
                        <div class="flex justify-between border-b border-slate-50 py-1">
                            <span class="text-slate-500">ID FOP / Penugasan</span>
                            <span class="font-mono font-semibold text-slate-800">{{ $survey->fop_id }}</span>
                        </div>
                        @endif
                        @if($survey->end_date)
                        <div class="flex justify-between border-b border-slate-50 py-1">
                            <span class="text-slate-500">Tanggal Selesai Survey</span>
                            <span class="font-semibold text-slate-800 font-mono">{{ \App\Support\IndonesianDate::date($survey->end_date) }}</span>
                        </div>
                        @endif
                        @if($survey->duration_minutes)
                        <div class="flex justify-between border-b border-slate-50 py-1">
                            <span class="text-slate-500">Durasi Survey</span>
                            <span class="font-semibold text-slate-800">{{ $survey->duration_minutes }} Menit</span>
                        </div>
                        @endif
                        @if($survey->surveyors)
                        <div class="flex justify-between border-b border-slate-50 py-1">
                            <span class="text-slate-500">Tim Survey (Surveyors)</span>
                            <span class="font-semibold text-slate-800">{{ $survey->surveyors }}</span>
                        </div>
                        @endif
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
                        <span class="block text-slate-500 mb-2 font-bold uppercase text-[9px] tracking-wider">Foto ODP / Survey Lapangan</span>
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
                                <span class="text-[10px] font-bold uppercase tracking-wider">Tidak Ada Foto ODP/Survey</span>
                            </div>
                        @endif

                        {{-- Foto Rumah Pelanggan --}}
                        <div class="mt-4">
                            <span class="block text-slate-500 mb-2 font-bold uppercase text-[9px] tracking-wider">Foto Rumah Pelanggan</span>
                            @if($survey->house_photo)
                                <div class="border border-slate-200 rounded overflow-hidden shadow-sm">
                                    <img src="{{ asset('storage/' . $survey->house_photo) }}" alt="Foto Rumah" class="w-full object-cover max-h-48">
                                    <div class="p-2 bg-slate-50 text-center">
                                        <a href="{{ asset('storage/' . $survey->house_photo) }}" target="_blank" class="text-[10px] font-bold text-sky-600 hover:text-sky-800">LIHAT FULL RESOLUSI</a>
                                    </div>
                                </div>
                            @else
                                <div class="h-32 bg-slate-50 border border-dashed border-slate-200 rounded flex flex-col items-center justify-center text-slate-400">
                                    <svg class="h-8 w-8 mb-1 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                    </svg>
                                    <span class="text-[10px] font-bold uppercase tracking-wider">Belum Ada Foto Rumah</span>
                                </div>
                            @endif
                        </div>
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


