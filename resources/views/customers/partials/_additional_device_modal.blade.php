{{--
    Modal "Tambah Perangkat Tambahan" — meniru struktur modal device utama
    (#device-modal di customers.tabs._device: backdrop fixed + hidden class,
    bukan Alpine x-data/x-show). Fungsi open/close didefinisikan per halaman
    yang me-render partial ini (customers.show & customers.fieldwork), sama
    seperti openDeviceModal()/closeDeviceModal() — lihat komentar di
    customers/show.blade.php soal kenapa fungsinya harus didefinisikan di
    SETIAP halaman, bukan cuma salah satu.
--}}
@can('customers.detail.devices.update')
<div id="additional-device-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700 w-full max-w-lg overflow-hidden max-h-[90vh] flex flex-col">
        <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between shrink-0">
            <h3 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">Tambah Perangkat Tambahan</h3>
            <button type="button" onclick="closeAdditionalDeviceModal()" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>
        <form action="{{ route('customers.additional-device.store', $customer->id) }}" method="POST" class="overflow-y-auto">
            @csrf
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <div>
                    <label for="additional_device_type" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Jenis Perangkat</label>
                    <select name="device_type" id="additional_device_type" required class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-200">
                        @foreach(['AP' => 'Access Point (AP)', 'STB' => 'Set Top Box (STB)', 'Extender' => 'Extender', 'Lainnya' => 'Lainnya'] as $value => $label)
                            <option value="{{ $value }}" {{ old('device_type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="additional_brand" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Merk</label>
                    <input type="text" name="brand" id="additional_brand" value="{{ old('brand') }}" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label for="additional_model" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Tipe / Model</label>
                    <input type="text" name="model" id="additional_model" value="{{ old('model') }}" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label for="additional_serial_number" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Serial Number</label>
                    <input type="text" name="serial_number" id="additional_serial_number" value="{{ old('serial_number') }}" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>
                <div>
                    <label for="additional_mac_address" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">MAC Address</label>
                    <input type="text" name="mac_address" id="additional_mac_address" value="{{ old('mac_address') }}" placeholder="AA:BB:CC:DD:EE:FF" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-mono text-slate-800 dark:text-slate-200">
                </div>
                <div class="md:col-span-2">
                    <label for="additional_technical_note" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Catatan Teknis</label>
                    <textarea name="technical_note" id="additional_technical_note" rows="3" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-200">{{ old('technical_note') }}</textarea>
                </div>
            </div>
            <div class="px-5 py-3.5 bg-slate-50 dark:bg-slate-900/50 border-t border-slate-200 dark:border-slate-700 flex justify-end gap-2 text-xs">
                <button type="button" onclick="closeAdditionalDeviceModal()" class="px-4 py-2 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 font-semibold rounded-lg shadow-sm cursor-pointer">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white font-semibold rounded-lg shadow-sm cursor-pointer">
                    Simpan Perangkat Tambahan
                </button>
            </div>
        </form>
    </div>
</div>
@endcan
