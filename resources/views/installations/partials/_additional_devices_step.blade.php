{{--
    Langkah Perangkat Tambahan (ADHOC-190 task 4, spec §4.2) — form SENDIRI
    (bukan bagian form-pemasangan), karena submit-nya langsung memasang SN ke
    pelanggan. Tidak tampil sama sekali kalau paket tidak mengatur perangkat
    tambahan ($requiredExtraDevices = 0).
--}}
@if($requiredExtraDevices > 0)
    @php
        $installedExtraDevices = $customer->additionalDevices()->whereNotNull('inventory_serial_id')->where('status', 'terpasang')->get();
        $outcomeOptions = ['terpasang' => 'Terpasang', 'kembali_gudang' => 'Kembali ke Gudang', 'rusak' => 'Rusak', 'hilang' => 'Hilang'];
        $oldDecisions = collect(old('decisions', []))->keyBy('inventory_serial_id');
    @endphp
    <form id="form-additional-devices" action="{{ route('customers.installation.additional-devices.store', $customer->id) }}" method="POST"
          class="p-4 sm:p-5 rounded-lg bg-slate-50/70 dark:bg-slate-900/40 border border-slate-200/70 dark:border-slate-700/60 space-y-3">
        @csrf
        <input type="hidden" name="return_to" value="{{ $returnTo }}">

        <div>
            <label class="block font-bold uppercase text-[10px] tracking-wide text-slate-700 dark:text-slate-300">
                Perangkat Tambahan (AP / STB) — Wajib {{ $requiredExtraDevices }} Perangkat
            </label>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">Beri status akhir untuk perangkat tambahan pelanggan ini. Perangkat yang dibawa untuk pelanggan lain biarkan kosong. Wajib disimpan sebelum Laporan Speedtest.</p>
        </div>

        @if($installation?->extra_devices_reported_at)
            <p class="text-[11px] text-emerald-700 dark:text-emerald-400 font-semibold">✓ Sudah dilaporkan {{ $installation->extra_devices_reported_at->format('d/m/Y H:i') }}.</p>
        @endif
        @error('extra_devices')
            <p class="text-[11px] text-rose-600 dark:text-rose-400 font-semibold">{{ $message }}</p>
        @enderror
        @error('decisions')
            <p class="text-[11px] text-rose-600 dark:text-rose-400 font-semibold">{{ $message }}</p>
        @enderror
        @foreach($errors->get('decisions.*') as $messages)
            <p class="text-[11px] text-rose-600 dark:text-rose-400 font-semibold">{{ $messages[0] }}</p>
        @endforeach

        @if($installedExtraDevices->isNotEmpty())
            <ul class="text-[11px] text-emerald-700 dark:text-emerald-400 space-y-0.5">
                @foreach($installedExtraDevices as $device)
                    <li>✓ {{ $device->device_type }} — SN {{ $device->serial_number }} (sudah terpasang)</li>
                @endforeach
            </ul>
        @endif

        @if($eligibleExtraDeviceSerials->isEmpty())
            <p class="text-[11px] text-amber-600 dark:text-amber-400 font-semibold">⚠ Tim ini tidak memegang perangkat tambahan di custody. Kalau perangkat kurang di luar kendali Anda, laporkan kekurangan di bawah.</p>
        @else
            <div class="space-y-2">
                @foreach($eligibleExtraDeviceSerials as $index => $serial)
                    @php $selectedOutcome = $oldDecisions[$serial->id]['outcome'] ?? ''; @endphp
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 p-2.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
                        <div class="text-xs">
                            <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $serial->item?->category?->name }} — {{ $serial->item?->name }}</span>
                            <span class="block font-mono text-[11px] text-slate-500 dark:text-slate-400">SN {{ $serial->serial_number }}</span>
                        </div>
                        <input type="hidden" name="decisions[{{ $index }}][inventory_serial_id]" value="{{ $serial->id }}">
                        <select name="decisions[{{ $index }}][outcome]" class="text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500">
                            <option value="">— Bukan untuk pelanggan ini —</option>
                            @foreach($outcomeOptions as $value => $label)
                                <option value="{{ $value }}" @selected($selectedOutcome === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="space-y-2">
            <label class="inline-flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                <input type="checkbox" name="kekurangan_perangkat" value="1" id="kekurangan_perangkat"
                       onchange="document.getElementById('kekurangan-perangkat-alasan-wrap').classList.toggle('hidden', ! this.checked)"
                       @checked(old('kekurangan_perangkat'))>
                <span>Laporkan kekurangan perangkat (rusak/hilang di luar kendali)</span>
            </label>
            <div id="kekurangan-perangkat-alasan-wrap" class="{{ old('kekurangan_perangkat') ? '' : 'hidden' }}">
                <textarea name="kekurangan_perangkat_alasan" rows="2" maxlength="500"
                          class="w-full text-xs px-3.5 py-2.5 border @error('kekurangan_perangkat_alasan') border-rose-400 @else border-slate-200 dark:border-slate-700 @enderror rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500"
                          placeholder="Alasan kekurangan perangkat (wajib)...">{{ old('kekurangan_perangkat_alasan') }}</textarea>
                @error('kekurangan_perangkat_alasan')
                    <p class="text-[11px] text-rose-600 dark:text-rose-400 font-semibold mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="w-full sm:w-auto px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold shadow-2xs inline-flex items-center justify-center gap-1.5">
                <x-ui.icon name="save" class="w-3.5 h-3.5" />
                <span>Simpan Perangkat Tambahan</span>
            </button>
        </div>
    </form>
@endif
