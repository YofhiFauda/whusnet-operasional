@extends('layouts.app')

@section('title', 'Busdev - Whusnet Operasional')
@section('page_title', 'Busdev - Pelanggan Aktif < 30 Hari')

@section('content')
{{-- Flash messages ditangani otomatis oleh global Component Toast (<x-toast />) --}}

<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 mb-6">
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Pelanggan Baru Diverifikasi Admin</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 max-w-3xl">
                Kebentuk otomatis begitu pelanggan diverifikasi admin (jadi Aktif) — dikelompokkan per bulan verifikasi.
                <span class="font-semibold text-slate-600 dark:text-slate-300">Reset tanggal 1</span>: begitu bulan berganti, tabel bulan berjalan mulai kosong lagi, sedangkan bulan lalu tetap bisa dibuka lewat filter periode di bawah sebagai arsip.
                Kolom <span class="font-semibold text-slate-600 dark:text-slate-300">Harga Dikurangi PPN</span> otomatis (Biaya Langganan − PPN 11%), bukan input manual.
                Kolom <span class="font-semibold text-slate-600 dark:text-slate-300">Biaya Instalasi</span> cuma muncul buat kategori Bisnis — begitu diisi & ditekan "Terbitkan", langsung jadi tagihan sungguhan (terpisah dari Tagihan Awal yang dibuat CS) dan terkunci.
            </p>
        </div>
        <div class="text-center shrink-0">
            <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">PELANGGAN</span>
            <span class="text-lg font-bold text-slate-800 dark:text-slate-200 data-text">{{ $records->count() }}</span>
        </div>
    </div>
</div>

<!-- Filter Periode + Role/Nama (2026-09-12) -->
<div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 mb-6 shadow-xs">
    <form action="{{ route('customer-acquisitions.index') }}" method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
        <div class="md:col-span-3">
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Periode (Bulan Verifikasi)</label>
            <select name="periode" onchange="this.form.submit()" class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                @foreach($periodeOptions as $opt)
                    <option value="{{ $opt }}" {{ $periode === $opt ? 'selected' : '' }}>
                        {{ \Carbon\Carbon::createFromFormat('Y-m', $opt)->locale('id')->translatedFormat('F Y') }}{{ $opt === now()->format('Y-m') ? ' (Berjalan)' : '' }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Filter "diinput oleh" — role & nama, khusus role ber-restriksi
             paket (yang disebut Busdev "role dengan pembatasan paket").
             Pilih Role dulu → dropdown Nama otomatis dipersempit (reload). --}}
        <div class="md:col-span-3">
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Role Penginput</label>
            <select name="role_id" onchange="this.form.submit()" class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                <option value="">Semua Role</option>
                @foreach($restrictedRoles as $role)
                    <option value="{{ $role->id }}" {{ (string) $roleId === (string) $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="md:col-span-3">
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Nama Penginput</label>
            <select name="sales_user_id" onchange="this.form.submit()" class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                <option value="">Semua Nama</option>
                @foreach($nameOptions as $person)
                    <option value="{{ $person->id }}" {{ (string) $salesUserId === (string) $person->id ? 'selected' : '' }}>{{ $person->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="md:col-span-3 flex items-center justify-end gap-2">
            @if($roleId || $salesUserId)
                <a href="{{ route('customer-acquisitions.index', ['periode' => $periode]) }}" class="text-[11px] text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 underline">Reset filter</a>
            @endif
            @if($isCurrentPeriode)
                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60">Bulan Berjalan</span>
            @else
                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-700/60 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-600">Arsip</span>
            @endif
        </div>
    </form>
</div>

<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden shadow-xs">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="bg-white dark:bg-slate-800 border-b border-slate-100 dark:border-slate-700/60 text-slate-400 dark:text-slate-500 uppercase text-[10px] font-bold tracking-wider">
                    <th class="px-4 py-2.5">No.</th>
                    <th class="px-4 py-2.5">Nama</th>
                    <th class="px-4 py-2.5">POP/Cabang</th>
                    <th class="px-4 py-2.5">Alamat</th>
                    <th class="px-4 py-2.5">Tanggal Aktivasi</th>
                    <th class="px-4 py-2.5">Diinput Oleh</th>
                    <th class="px-4 py-2.5 text-right">Biaya Langganan</th>
                    <th class="px-4 py-2.5 text-right">Harga Dikurangi PPN</th>
                    <th class="px-4 py-2.5 text-right">Biaya Instalasi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @forelse($records as $i => $record)
                    @php $customer = $record->customer; @endphp
                    <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-900/30 transition-colors">
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $i + 1 }}</td>
                        <td class="px-4 py-3 font-semibold text-slate-700 dark:text-slate-200">{{ $customer->full_name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $customer->pop?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400 max-w-xs truncate" title="{{ $customer->clean_address }}">{{ $customer->clean_address }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                            {{ $customer->customerService?->activation_date ? \App\Helpers\FormatHelper::tanggal($customer->customerService->activation_date) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                            {{-- Fallback kode lama (varchar) kalau FK sales_user_id belum keisi (data pra-Skema 3) --}}
                            @if($customer->salesUser)
                                {{ $customer->salesUser->name }}
                                <span class="block text-[10px] text-slate-400 dark:text-slate-500">{{ $customer->salesUser->role?->name ?? '—' }}</span>
                            @else
                                {{ $customer->sales_code ?? '—' }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-slate-700 dark:text-slate-300">
                            {{ $customer->customerService ? \App\Helpers\FormatHelper::rupiah($customer->customerService->total_monthly_bill) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-slate-700 dark:text-slate-300">
                            {{ $record->harga_dikurangi_ppn !== null ? \App\Helpers\FormatHelper::rupiah($record->harga_dikurangi_ppn) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if(! $record->needsInstallationFeeValidation())
                                <span class="text-slate-300 dark:text-slate-600">—</span>
                            @elseif($record->installationFeeInvoice)
                                {{-- Udah jadi tagihan sungguhan — terkunci, gak bisa diketik
                                     ulang dari sini. Koreksi lewat menu Tagihan biasa. --}}
                                <div class="flex flex-col items-end gap-0.5">
                                    <span class="font-mono text-slate-700 dark:text-slate-300">{{ \App\Helpers\FormatHelper::rupiah($record->installation_fee) }}</span>
                                    <a href="{{ route('invoices.show', $record->installationFeeInvoice) }}" class="text-[10px] text-sky-600 dark:text-sky-400 hover:underline">
                                        {{ $record->installationFeeInvoice->invoice_number }}
                                    </a>
                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold
                                        {{ $record->installationFeeInvoice->invoice_status === \App\Enums\InvoiceStatus::LUNAS
                                            ? 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60'
                                            : 'bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800/60' }}">
                                        {{ $record->installationFeeInvoice->invoice_status->label() }}
                                    </span>
                                </div>
                            @elseif($record->canBeValidatedBy(auth()->user()))
                                {{-- Gerbang dinamis: role yang wajib dipunyai user beda per
                                     baris, tergantung kategori paket pelanggan (dipilih admin
                                     di Master Kategori Paket). Bukan @can statis. Submit di sini
                                     langsung menerbitkan tagihan (INSIDENTAL) — bukan cuma catatan. --}}
                                <form action="{{ route('customer-acquisitions.installation-fee.update', $record) }}" method="POST" class="flex items-center justify-end gap-1.5">
                                    @csrf
                                    @method('PUT')
                                    <span class="text-slate-400">Rp</span>
                                    <input type="number" step="0.01" min="0.01" name="installation_fee" required
                                           value="{{ $record->installation_fee }}"
                                           class="w-28 px-2 py-1 text-right font-mono border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                                    <button type="submit" class="px-2 py-1 text-[11px] font-semibold rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors cursor-pointer">
                                        Terbitkan
                                    </button>
                                </form>
                            @else
                                <span class="text-slate-400 dark:text-slate-500 text-[11px]">Menunggu Validasi</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-6 text-center text-slate-400 dark:text-slate-500">
                            Belum ada pelanggan diverifikasi pada periode ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
