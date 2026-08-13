@extends('layouts.app')

@section('title', 'Master Timeline SLA - Whusnet Operasional')
@section('page_title', 'Master Timeline SLA')

@section('content')
<div x-data="slaTimelineMatrix()">
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-5 mb-6">
        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Master Timeline SLA</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Batas waktu wajib mulai ditangani per jenis tiket, berbeda-beda per paket internet.
            Ini bukan durasi pengerjaan teknisi di lapangan.
        </p>
    </div>

    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400">
                <tr>
                    <th class="text-left px-4 py-3 font-medium sticky left-0 bg-slate-50 dark:bg-slate-800/50">Paket Internet</th>
                    @foreach ($taskTypes as $type)
                        <th class="text-left px-4 py-3 font-medium whitespace-nowrap">{{ $type->label() }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                @foreach ($packages as $package)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-200 sticky left-0 bg-white dark:bg-slate-800 whitespace-nowrap">
                            {{ $package->name }}
                            <div class="text-xs text-slate-400 dark:text-slate-500">{{ $package->package_code }}</div>
                        </td>
                        @foreach ($taskTypes as $type)
                            @php
                                $setting = $package->slaSettings->firstWhere('task_type', $type);
                            @endphp
                            <td class="px-4 py-2">
                                <div class="flex items-center gap-1">
                                    <input
                                        type="number"
                                        min="1"
                                        class="w-16 rounded-md border border-slate-300 dark:border-slate-600 px-2 py-1 text-sm"
                                        value="{{ $setting?->sla_duration ?? $type->defaultHandlingSlaHours() }}"
                                        @change="save({{ $package->id }}, '{{ $type->value }}', $event.target.value, $refs.unit_{{ $package->id }}_{{ $loop->index }}.value, $event)"
                                        x-ref="duration_{{ $package->id }}_{{ $loop->index }}"
                                    >
                                    <select
                                        class="rounded-md border border-slate-300 dark:border-slate-600 px-1 py-1 text-sm"
                                        x-ref="unit_{{ $package->id }}_{{ $loop->index }}"
                                        @change="save({{ $package->id }}, '{{ $type->value }}', $refs.duration_{{ $package->id }}_{{ $loop->index }}.value, $event.target.value, $event)"
                                    >
                                        <option value="hour" @selected(($setting?->sla_unit ?? 'day') === 'hour')>jam</option>
                                        <option value="day" @selected(($setting?->sla_unit ?? 'day') === 'day')>hari</option>
                                    </select>
                                </div>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="text-xs mt-3" x-show="savedMessage" x-cloak x-text="savedMessage"
       :class="savedError ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-slate-500 dark:text-slate-400'"></p>
</div>

@push('scripts')
<script>
    /**
     * Matriks SLA per paket — simpan otomatis tiap kali nilai berubah.
     *
     * Versi lama cuma `.then(res => res.json()).then(() => savedMessage =
     * 'Tersimpan.')` — tanpa cek `res.ok`, tanpa `.catch`. Ditolak 403, gagal
     * validasi 422, atau server 500, layar TETAP menulis "Tersimpan.". Admin
     * menutup halaman yakin SLA sudah berubah padahal tidak. Simpan-otomatis
     * tanpa tombol membuat kebohongan itu makin mahal: tak ada satu pun langkah
     * lain yang memberi kesempatan sadar.
     */
    function slaTimelineMatrix() {
        return {
            savedMessage: '',
            savedError: false,

            async save(packageId, taskType, duration, unit, event) {
                const input = event?.target;
                const sebelumnya = input?.defaultValue;

                this.savedMessage = 'Menyimpan…';
                this.savedError = false;

                try {
                    const res = await fetch(`{{ url('/master/sla-timeline') }}/${packageId}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'X-HTTP-Method-Override': 'PUT',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ task_type: taskType, sla_duration: duration, sla_unit: unit }),
                    });

                    const data = await res.json().catch(() => ({}));

                    if (!res.ok) {
                        // Pesan validasi Laravel bersarang di `errors`; ambil yang
                        // pertama supaya admin tahu APA yang salah, bukan cuma
                        // "gagal".
                        const pesan = data.message
                            || Object.values(data.errors ?? {})[0]?.[0]
                            || `Gagal menyimpan (HTTP ${res.status}).`;

                        throw new Error(pesan);
                    }

                    // `defaultValue` diikutkan supaya percobaan berikutnya punya
                    // titik pulang yang benar, bukan nilai gagal sebelumnya.
                    if (input) input.defaultValue = input.value;

                    this.savedMessage = 'Tersimpan.';
                    setTimeout(() => (this.savedMessage = ''), 2000);

                    if (window.Toast) {
                        window.Toast.show('success', 'SLA tersimpan', '', 2500);
                    }
                } catch (e) {
                    // Nilai dikembalikan ke semula: membiarkan angka baru
                    // terpampang setelah gagal simpan sama menyesatkannya dengan
                    // menulis "Tersimpan.".
                    if (input && sebelumnya !== undefined) input.value = sebelumnya;

                    this.savedError = true;
                    this.savedMessage = e.message || 'Gagal menyimpan — nilai dikembalikan.';

                    if (window.Toast) {
                        window.Toast.show('error', 'SLA gagal disimpan', this.savedMessage, 6000);
                    }
                }
            },
        };
    }
</script>
@endpush
@endsection
