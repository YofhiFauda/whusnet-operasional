@extends('layouts.app')

@section('title', 'Master Alasan Putus Langganan - Whusnet Operasional')
@section('page_title', 'Master Alasan Putus Langganan')

@section('content')
<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 mb-6 shadow-sm">
    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">Master Alasan Putus Langganan</h3>
    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Klasifikasi alasan di dropdown form Putus Langganan — bisa difilter/sort di List Pelanggan Putus. Alasan yang masih dipakai minimal 1 pelanggan tidak bisa dihapus.</p>
</div>

<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-5 mb-6 shadow-sm">
    <form action="{{ route('master.termination-reasons.index') }}" method="GET" class="flex flex-col lg:flex-row items-end lg:items-center justify-between gap-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 w-full lg:max-w-2xl">
            <div>
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Pencarian</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Nama alasan..."
                       class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Status</label>
                <select name="status" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 bg-white dark:bg-slate-800">
                    <option value="">Semua Status</option>
                    <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Aktif</option>
                    <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                </select>
            </div>
        </div>

        <div class="flex items-center gap-2 w-full lg:w-auto shrink-0 justify-end">
            <button type="submit" class="flex-1 lg:flex-none inline-flex items-center justify-center gap-2 px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm text-sm font-semibold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors focus:outline-none cursor-pointer">
                Filter
            </button>

            @if($search || $status)
            <a href="{{ route('master.termination-reasons.index') }}" class="inline-flex items-center justify-center p-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm text-sm font-medium text-slate-500 dark:text-slate-400 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors focus:outline-none cursor-pointer" title="Reset Filters">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.28 15m-2.802-5.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
                </svg>
            </a>
            @endif

            @if(auth()->user()->hasPermission('termination_reasons.create'))
            <button type="button"
                    onclick="openReasonModal('create')"
                    class="flex-1 lg:flex-none inline-flex items-center justify-center gap-2 px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-semibold text-white bg-sky-600 dark:bg-sky-500 hover:bg-sky-700 transition-colors focus:outline-none cursor-pointer">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Alasan
            </button>
            @endif
        </div>
    </form>
</div>

<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden shadow-sm">
    @if($reasons->isEmpty())
    <div class="p-16 text-center">
        <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Tidak ada Alasan Putus Langganan ditemukan</h4>
        <p class="text-xs text-slate-400 dark:text-slate-500 max-w-sm mx-auto mt-1">
            @if($search || $status)
            Silakan reset filter pencarian atau ubah parameter filter Anda.
            @else
            Tambahkan alasan dulu — form Putus Langganan butuh minimal satu alasan aktif.
            @endif
        </p>
        @if(!$search && !$status && auth()->user()->hasPermission('termination_reasons.create'))
        <div class="mt-4">
            <button type="button"
                    onclick="openReasonModal('create')"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 border border-transparent rounded-md shadow-sm text-xs font-semibold text-white bg-sky-600 hover:bg-sky-700 transition-colors cursor-pointer">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Alasan
            </button>
        </div>
        @endif
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800/50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider w-16">No</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Nama Alasan</th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Nilai Awal Denda</th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Dipakai</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                    <th scope="col" class="px-6 py-3 text-center text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider w-32">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
                @foreach($reasons as $index => $reason)
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                        {{ $reasons->firstItem() + $index }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $reason->name }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-mono text-slate-600 dark:text-slate-400">{{ format_rupiah($reason->default_penalty_amount) }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-xs font-mono text-slate-500 dark:text-slate-400">{{ number_format($reason->customers_count) }} pelanggan</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if($reason->is_active)
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/50">Aktif</span>
                        @else
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-slate-100 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700">Nonaktif</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                        <div class="flex items-center justify-center gap-2">
                            @if(auth()->user()->hasPermission('termination_reasons.update'))
                            <button type="button"
                                    onclick="openReasonModal('edit', @js([
                                        'id' => $reason->id,
                                        'name' => $reason->name,
                                        'default_penalty_amount' => (int) $reason->default_penalty_amount,
                                        'is_active' => $reason->is_active ? 1 : 0,
                                        'update_url' => route('master.termination-reasons.update', $reason),
                                    ]))"
                                    class="p-1 text-slate-400 dark:text-slate-500 hover:text-sky-600 hover:bg-sky-50 dark:hover:bg-sky-900/20 rounded-md transition-colors cursor-pointer"
                                    title="Ubah Alasan">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </button>

                            <form action="{{ route('master.termination-reasons.toggle', $reason) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit"
                                        class="p-1 text-slate-400 dark:text-slate-500 hover:text-amber-600 dark:hover:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-md transition-colors cursor-pointer"
                                        title="{{ $reason->is_active ? 'Nonaktifkan' : 'Aktifkan' }} Alasan"
                                        onclick="event.preventDefault(); window.confirmDelete(@js('Apakah Anda yakin ingin '.($reason->is_active ? 'menonaktifkan' : 'mengaktifkan').' alasan '.($reason->name).'?'), this.closest('form'))">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </button>
                            </form>
                            @endif

                            @if(auth()->user()->hasPermission('termination_reasons.delete'))
                            <form action="{{ route('master.termination-reasons.destroy', $reason) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        {{ $reason->customers_count > 0 ? 'disabled' : '' }}
                                        class="p-1 rounded-md transition-colors {{ $reason->customers_count > 0 ? 'text-slate-300 dark:text-slate-700 cursor-not-allowed' : 'text-slate-400 dark:text-slate-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 cursor-pointer' }}"
                                        title="{{ $reason->customers_count > 0 ? 'Masih dipakai '.$reason->customers_count.' pelanggan, tidak bisa dihapus' : 'Hapus Alasan' }}"
                                        @if($reason->customers_count === 0)
                                        onclick="event.preventDefault(); window.confirmDelete(@js('Hapus permanen alasan '.($reason->name).'? Tindakan ini tidak bisa dibatalkan.'), this.closest('form'))"
                                        @endif>
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($reasons->hasPages())
    <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700/50 bg-slate-50/50">
        {{ $reasons->links() }}
    </div>
    @endif
    @endif
</div>

{{-- Modal Tambah / Edit Alasan Putus Langganan --}}
@if(auth()->user()->hasPermission('termination_reasons.create') || auth()->user()->hasPermission('termination_reasons.update'))
<div id="reason-modal-wrapper" role="dialog" aria-modal="true" aria-labelledby="reason-modal-title" class="fixed inset-0 z-50 overflow-y-auto flex items-end sm:items-center justify-center p-0 sm:p-4 md:p-6 hidden">
    <!-- Backdrop Blur -->
    <div onclick="closeReasonModal()" class="fixed inset-0 bg-slate-900/60 backdrop-blur-md transition-opacity"></div>

    <!-- Modal Dialog Sheet -->
    <div class="relative bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-t-3xl sm:rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden z-10 max-h-[90vh] flex flex-col transform transition-all duration-300">
        <!-- Mobile Pull Drag Handle Indicator -->
        <div class="w-10 h-1 bg-slate-300 dark:bg-slate-700 rounded-full mx-auto mt-2.5 mb-1 sm:hidden shrink-0"></div>

        <!-- Header -->
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/75 dark:bg-slate-800/40 shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <div id="reason-modal-icon-container" class="w-10 h-10 rounded-xl bg-sky-100 dark:bg-sky-950 text-sky-600 dark:text-sky-400 flex items-center justify-center font-bold shrink-0 shadow-sm" aria-hidden="true">
                    <!-- Create Icon (Plus) -->
                    <svg id="reason-icon-create" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                    <!-- Edit Icon (Pencil) -->
                    <svg id="reason-icon-edit" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                </div>
                <div class="min-w-0">
                    <h2 class="font-bold text-slate-900 dark:text-white text-base leading-tight truncate" id="reason-modal-title">Tambah Alasan Putus Langganan</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate" id="reason-modal-subtitle">Klasifikasi baru di dropdown form Putus Langganan.</p>
                </div>
            </div>
            <button type="button" onclick="closeReasonModal()" aria-label="Tutup modal" class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors shrink-0 cursor-pointer">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Form -->
        <form id="reason-modal-form" method="POST" action="{{ route('master.termination-reasons.store') }}" onsubmit="submitReasonModal(event)" class="flex flex-col flex-1 overflow-hidden">
            @csrf
            <input type="hidden" name="_method" id="reason-form-method" value="POST" disabled>

            <div class="p-5 sm:p-6 space-y-4 overflow-y-auto flex-1 overscroll-y-contain">
                <!-- Inline Errors Alert -->
                <div id="reason-modal-errors" class="hidden text-xs text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 rounded-xl p-3 space-y-1"></div>

                <!-- Nama Alasan -->
                <div>
                    <label for="modal-reason-name" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">
                        Nama Alasan <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="name" id="modal-reason-name" required maxlength="150"
                           placeholder="Contoh: Pindah Rumah, Kompetitor, Masalah Biaya"
                           class="w-full px-3.5 py-2.5 border border-slate-300 dark:border-slate-600 rounded-xl text-sm text-slate-800 dark:text-slate-200 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                    <p class="text-[11px] text-slate-400 mt-1">Nama alasan pemutusan yang akan ditampilkan pada pilihan form.</p>
                </div>

                <!-- Nilai Awal Denda -->
                <div>
                    <label for="modal-reason-penalty" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">
                        Nilai Awal Denda (Rp)
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-xs font-mono font-bold text-slate-400 dark:text-slate-500">Rp</span>
                        <input type="text" inputmode="decimal" name="default_penalty_amount" id="modal-reason-penalty" data-rupiah value="0"
                               class="w-full pl-10 pr-3.5 py-2.5 border border-slate-300 dark:border-slate-600 rounded-xl text-sm font-mono text-slate-800 dark:text-slate-200 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Titik awal (prefill) denda jika masa langganan pelanggan &le; 1 tahun. Admin tetap bisa menyesuaikan saat memutus langganan.</p>
                </div>

                <!-- Status Alasan -->
                <div>
                    <label for="modal-reason-status" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">
                        Status <span class="text-rose-500">*</span>
                    </label>
                    <select name="is_active" id="modal-reason-status" required
                            class="w-full px-3.5 py-2.5 border border-slate-300 dark:border-slate-600 rounded-xl text-sm text-slate-800 dark:text-slate-200 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                        <option value="1">Aktif (Bisa Dipilih)</option>
                        <option value="0">Nonaktif (Disembunyikan dari Pilihan)</option>
                    </select>
                    <p class="text-[11px] text-slate-400 mt-1">Nonaktif = tidak muncul di form baru, data pelanggan lama yang sudah memakainya tetap aman.</p>
                </div>
            </div>

            <!-- Footer Buttons -->
            <div class="px-5 py-3.5 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex items-center justify-end gap-2.5 shrink-0">
                <button type="button" onclick="closeReasonModal()"
                        class="px-4 py-2 text-xs font-semibold rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer">
                    Batal
                </button>
                <button type="submit" id="reason-modal-submit-btn"
                        class="inline-flex items-center justify-center gap-1.5 px-5 py-2 text-xs font-semibold rounded-xl text-white bg-sky-600 hover:bg-sky-700 shadow-md shadow-sky-600/20 transition-all cursor-pointer">
                    <span id="reason-modal-submit-text">Simpan Alasan</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const CREATE_REASON_URL = @js(route('master.termination-reasons.store'));

    function openReasonModal(mode, data = null) {
        const modal = document.getElementById('reason-modal-wrapper');
        const form = document.getElementById('reason-modal-form');
        const titleEl = document.getElementById('reason-modal-title');
        const subtitleEl = document.getElementById('reason-modal-subtitle');
        const iconCreate = document.getElementById('reason-icon-create');
        const iconEdit = document.getElementById('reason-icon-edit');
        const iconBox = document.getElementById('reason-modal-icon-container');
        const submitText = document.getElementById('reason-modal-submit-text');
        const methodInput = document.getElementById('reason-form-method');
        const nameInput = document.getElementById('modal-reason-name');
        const penaltyInput = document.getElementById('modal-reason-penalty');
        const statusSelect = document.getElementById('modal-reason-status');
        const errorsBox = document.getElementById('reason-modal-errors');

        if (!modal || !form) return;

        // Reset errors
        if (errorsBox) {
            errorsBox.classList.add('hidden');
            errorsBox.innerHTML = '';
        }

        if (mode === 'edit' && data) {
            titleEl.textContent = 'Ubah Alasan Putus Langganan';
            subtitleEl.textContent = 'Perbarui data klasifikasi alasan pemutusan langganan.';
            submitText.textContent = 'Simpan Perubahan';
            form.action = data.update_url;
            methodInput.value = 'PUT';
            methodInput.disabled = false;

            iconCreate.classList.add('hidden');
            iconEdit.classList.remove('hidden');
            iconBox.className = 'w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-950 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold shrink-0 shadow-sm';

            nameInput.value = data.name || '';
            const rawPenalty = data.default_penalty_amount !== undefined ? data.default_penalty_amount : 0;
            penaltyInput.value = window.Rupiah ? window.Rupiah.format(String(rawPenalty)) : String(rawPenalty);
            statusSelect.value = String(data.is_active !== undefined ? data.is_active : '1');
        } else {
            titleEl.textContent = 'Tambah Alasan Putus Langganan';
            subtitleEl.textContent = 'Klasifikasi baru di dropdown form Putus Langganan.';
            submitText.textContent = 'Simpan Alasan';
            form.action = CREATE_REASON_URL;
            methodInput.value = 'POST';
            methodInput.disabled = true;

            iconCreate.classList.remove('hidden');
            iconEdit.classList.add('hidden');
            iconBox.className = 'w-10 h-10 rounded-xl bg-sky-100 dark:bg-sky-950 text-sky-600 dark:text-sky-400 flex items-center justify-center font-bold shrink-0 shadow-sm';

            nameInput.value = '';
            penaltyInput.value = '0';
            statusSelect.value = '1';
        }

        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        setTimeout(() => nameInput.focus(), 50);
    }

    function closeReasonModal() {
        const modal = document.getElementById('reason-modal-wrapper');
        if (modal) {
            modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }
    }

    function renderReasonErrors(errors) {
        const errBox = document.getElementById('reason-modal-errors');
        if (!errBox) return;
        const messages = Object.values(errors || {}).flat();
        if (!messages.length) {
            errBox.classList.add('hidden');
            errBox.innerHTML = '';
            return;
        }
        errBox.innerHTML = messages.map(m => `<p>• ${m}</p>`).join('');
        errBox.classList.remove('hidden');
    }

    function submitReasonModal(event) {
        event.preventDefault();
        const form = document.getElementById('reason-modal-form');
        const submitBtn = document.getElementById('reason-modal-submit-btn');
        const submitText = document.getElementById('reason-modal-submit-text');
        const errorsBox = document.getElementById('reason-modal-errors');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || form.querySelector('input[name="_token"]')?.value;

        if (!form) return;

        // Normalisasi format rupiah sebelum FormData dibaca
        window.Rupiah && window.Rupiah.normalisasiForm(form);

        const formData = new FormData(form);
        const originalText = submitText.textContent;
        submitBtn.disabled = true;
        submitText.textContent = 'Menyimpan...';
        if (errorsBox) {
            errorsBox.classList.add('hidden');
            errorsBox.innerHTML = '';
        }

        fetch(form.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: formData,
        })
        .then(async (res) => {
            const data = await res.json().catch(() => ({}));
            if (res.status === 422 && data.errors) {
                renderReasonErrors(data.errors);
                throw null;
            }
            if (!res.ok) {
                throw new Error(data.message || 'Terjadi kesalahan saat menyimpan alasan.');
            }
            return data;
        })
        .then((data) => {
            closeReasonModal();
            if (window.Toast) {
                window.Toast.success('Berhasil', data.message || 'Data alasan putus langganan berhasil disimpan.');
            }
            window.location.reload();
        })
        .catch((err) => {
            if (err === null) return;
            console.error(err);
            if (window.Toast) {
                window.Toast.error('Gagal', err.message || 'Terjadi kesalahan sistem.');
            }
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitText.textContent = originalText;
        });
    }

    // Escape listener to close modal
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const modal = document.getElementById('reason-modal-wrapper');
            if (modal && !modal.classList.contains('hidden')) {
                closeReasonModal();
            }
        }
    });
</script>
@endif
@endsection
