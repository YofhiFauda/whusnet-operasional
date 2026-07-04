@extends('layouts.app')

@php
    $pageTitle = 'Daftar Tagihan';
    $formAction = route('invoices.index');
    if (isset($statusGroup) && $statusGroup !== '') {
        if ($statusGroup === 'lunas') {
            $pageTitle = 'Tagihan Lunas';
            $formAction = route('invoices.lunas');
        } elseif ($statusGroup === 'belum_lunas') {
            $pageTitle = 'Tagihan Belum Lunas';
            $formAction = route('invoices.belum-lunas');
        }
    }
@endphp

@section('title', $pageTitle . ' - Whusnet Operasional')
@section('page_title', $pageTitle)

@section('content')
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h3 class="text-slate-800 text-sm font-semibold uppercase tracking-wider">Daftar dan Filter {{ $pageTitle }}</h3>
        <p class="text-xs text-slate-500 mt-1">Tagihan berasal dari pelanggan aktif dan layanan pelanggan yang sudah tersimpan.</p>
    </div>
    <a href="/customers" class="inline-flex items-center gap-1.5 px-4 py-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none">
        Buka Data Pelanggan
    </a>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
    <div class="bg-white border border-orange-200 rounded-lg p-4 flex items-center justify-between">
        <div>
            <span class="block text-[10px] font-bold text-orange-600 uppercase tracking-wider mb-1">Total Nunggak — Tagihan Awal</span>
            <span class="block text-lg font-bold text-slate-800 font-mono">Rp {{ number_format($unpaidAwalTotal, 0, ',', '.') }}</span>
        </div>
        <span class="inline-flex items-center px-2 py-1 rounded text-[10px] font-bold border uppercase tracking-wide bg-orange-50 text-orange-700 border-orange-200">Awal / Reaktivasi</span>
    </div>
    <div class="bg-white border border-sky-200 rounded-lg p-4 flex items-center justify-between">
        <div>
            <span class="block text-[10px] font-bold text-sky-600 uppercase tracking-wider mb-1">Total Nunggak — Tagihan Bulanan</span>
            <span class="block text-lg font-bold text-slate-800 font-mono">Rp {{ number_format($unpaidBulananTotal, 0, ',', '.') }}</span>
        </div>
        <span class="inline-flex items-center px-2 py-1 rounded text-[10px] font-bold border uppercase tracking-wide bg-sky-50 text-sky-700 border-sky-200">Bulanan</span>
    </div>
</div>

<div class="bg-white border border-slate-200 rounded-lg p-6 mb-6">
    <form action="{{ $formAction }}" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
        <div>
            <label for="search" class="block text-xs font-semibold text-slate-500 mb-2">CARI TAGIHAN</label>
            <input type="text" name="search" id="search" value="{{ $search }}" placeholder="Cari Nama, No. Tagihan, atau ID Invoice Lama..." class="w-full font-sans text-sm px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
        </div>

        <div>
            <label for="pop_id" class="block text-xs font-semibold text-slate-500 mb-2">POP / CABANG</label>
            <select name="pop_id" id="pop_id" class="w-full font-sans text-sm px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
                <option value="">Semua POP</option>
                @foreach($pops as $pop)
                    <option value="{{ $pop->id }}" {{ (string) $popId === (string) $pop->id ? 'selected' : '' }}>{{ $pop->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="billing_period" class="block text-xs font-semibold text-slate-500 mb-2">PERIODE</label>
            <input type="month" name="billing_period" id="billing_period" value="{{ $billingPeriod }}" class="w-full font-sans text-sm px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
        </div>

        <div>
            <label for="invoice_type" class="block text-xs font-semibold text-slate-500 mb-2">JENIS TAGIHAN</label>
            <select name="invoice_type" id="invoice_type" class="w-full font-sans text-sm px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
                <option value="">Semua Jenis</option>
                <option value="awal" {{ ($invoiceType ?? '') === 'awal' ? 'selected' : '' }}>Tagihan Awal (PSB)</option>
                <option value="bulanan" {{ ($invoiceType ?? '') === 'bulanan' ? 'selected' : '' }}>Tagihan Bulanan Rutin</option>
                <option value="reaktivasi" {{ ($invoiceType ?? '') === 'reaktivasi' ? 'selected' : '' }}>Tagihan Reaktivasi</option>
            </select>
        </div>

        <div>
            <label for="status" class="block text-xs font-semibold text-slate-500 mb-2">STATUS</label>
            <select name="status" id="status" class="w-full font-sans text-sm px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
                <option value="">Semua Status</option>
                @foreach($allowedStatuses as $invoiceStatus)
                    @if(!isset($statusGroup) || $statusGroup === '' || 
                        ($statusGroup === 'lunas' && $invoiceStatus === 'lunas') || 
                        ($statusGroup === 'belum_lunas' && ($invoiceStatus === 'belum_dibayar' || $invoiceStatus === 'sebagian')))
                        <option value="{{ $invoiceStatus }}" {{ $status === $invoiceStatus ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $invoiceStatus)) }}</option>
                    @endif
                @endforeach
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="flex-1 bg-sky-600 hover:bg-sky-700 text-white text-sm font-medium py-2 px-4 rounded-md transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-sky-500/25">
                Filter
            </button>
            <a href="{{ $formAction }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium py-2 px-4 rounded-md transition-colors cursor-pointer text-center focus:outline-none">
                Reset
            </a>
        </div>
    </form>
</div>

<div class="bg-white border border-slate-200 rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-sm text-slate-700">
            <thead>
                <tr class="bg-slate-50/50 border-b border-slate-200 text-slate-500 font-semibold text-xs">
                    @can('payments.create')
                        <th class="px-4 py-3.5 w-8 text-center">
                            <input type="checkbox" id="bulk-select-all" onclick="toggleAllBulkPay(this)" class="rounded border-slate-300 cursor-pointer">
                        </th>
                    @endcan
                    <th class="px-6 py-3.5 w-12 text-center">NO</th>
                    <th class="px-6 py-3.5">NO. TAGIHAN</th>
                    <th class="px-6 py-3.5">PELANGGAN</th>
                    <th class="px-6 py-3.5">POP</th>
                    <th class="px-6 py-3.5">PERIODE</th>
                    <th class="px-6 py-3.5 text-right">TOTAL</th>
                    <th class="px-6 py-3.5 text-right">SISA</th>
                    <th class="px-6 py-3.5 text-center">STATUS</th>
                    <th class="px-6 py-3.5 text-right">ACTION</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($invoices as $invoice)
                    @php
                        $badgeClass = match($invoice->invoice_status) {
                            'lunas' => 'bg-green-50 text-green-700 border-green-100',
                            'sebagian' => 'bg-amber-50 text-amber-700 border-amber-100',
                            'batal' => 'bg-red-50 text-red-700 border-red-100',
                            default => 'bg-slate-50 text-slate-700 border-slate-100',
                        };
                    @endphp
                    <tr class="hover:bg-slate-50/45 transition-colors" id="invoice-row-{{ $invoice->id }}">
                        @can('payments.create')
                            <td class="px-4 py-3.5 text-center">
                                @if(!in_array($invoice->invoice_status, ['lunas', 'batal']))
                                    <input type="checkbox" class="bulk-pay-checkbox rounded border-slate-300 cursor-pointer" value="{{ $invoice->id }}" onclick="updateBulkPayBar()">
                                @endif
                            </td>
                        @endcan
                        <td class="px-6 py-3.5 text-center text-slate-400 data-text">{{ ($invoices->currentPage() - 1) * $invoices->perPage() + $loop->iteration }}</td>
                        <td class="px-6 py-3.5 whitespace-nowrap">
                            <a href="{{ route('invoices.show', $invoice->id) }}" class="font-mono font-bold text-sky-700 hover:text-sky-900">{{ $invoice->invoice_number }}</a>
                            <div class="flex items-center flex-wrap gap-1.5 mt-1">
                                @if($invoice->old_invoice_id || $invoice->old_cost_id)
                                <span title="Data Migrasi (ID Lama: {{ $invoice->old_invoice_id ?: $invoice->old_cost_id }})" class="px-1.5 py-0.5 text-[9px] font-bold rounded border bg-sky-50 text-sky-700 border-sky-200">
                                    Migrasi #{{ $invoice->old_invoice_id ?: $invoice->old_cost_id }}
                                </span>
                                @endif
                                @if($invoice->invoice_type)
                                <span class="px-1.5 py-0.5 text-[9px] font-bold rounded border bg-indigo-50 text-indigo-700 border-indigo-100">
                                    {{ $invoice->invoice_type->label() }}
                                </span>
                                @endif
                                <span class="text-[10px] text-slate-400">Tempo {{ optional($invoice->due_date)->format('d/m/Y') }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-3.5 whitespace-nowrap">
                            <div class="font-semibold text-slate-900">{{ $invoice->customer->full_name ?? '-' }}</div>
                            <div class="text-[10px] text-slate-400 font-mono">{{ $invoice->customer->cid ?? $invoice->customer->customer_code ?? '-' }}</div>
                        </td>
                        <td class="px-6 py-3.5 whitespace-nowrap font-medium text-slate-800">{{ $invoice->pop->name ?? '-' }}</td>
                        <td class="px-6 py-3.5 whitespace-nowrap font-mono">{{ $invoice->billing_period }}</td>
                        <td class="px-6 py-3.5 text-right font-mono font-semibold">Rp {{ number_format((float) $invoice->total_amount, 2, ',', '.') }}</td>
                        <td class="px-6 py-3.5 text-right font-mono">Rp {{ number_format((float) $invoice->remaining_amount, 2, ',', '.') }}</td>
                        <td class="px-6 py-3.5 text-center whitespace-nowrap">
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full border {{ $badgeClass }}" id="invoice-status-badge-{{ $invoice->id }}" data-badge-style="pill">
                                {{ ucwords(str_replace('_', ' ', $invoice->invoice_status)) }}
                            </span>
                        </td>
                        <td class="px-6 py-3.5 text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-1.5">
                                @can('payments.create')
                                    @if(!in_array($invoice->invoice_status, ['lunas', 'batal']))
                                        <button type="button"
                                                onclick="openQuickPaymentModal({{ $invoice->id }}, '{{ $invoice->invoice_number }}', {{ (float) $invoice->remaining_amount }})"
                                                class="inline-flex items-center px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md transition-colors text-xs font-semibold cursor-pointer">
                                            Bayar
                                        </button>
                                    @endif
                                @endcan
                                <a href="{{ route('invoices.show', $invoice->id) }}" class="inline-flex items-center px-3 py-1.5 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 rounded-md transition-colors text-xs font-semibold">
                                    Detail
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-6 py-10 text-center text-sm text-slate-500">Belum ada tagihan yang sesuai filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="px-6 py-4 border-t border-slate-100">
        {{ $invoices->links() }}
    </div>
</div>

@can('payments.create')
    <!-- Bulk-pay floating bar -->
    <div id="bulk-pay-bar" class="hidden fixed bottom-6 left-1/2 -translate-x-1/2 bg-slate-900 text-white rounded-lg shadow-xl px-5 py-3 flex items-center gap-3 z-40 text-xs">
        <span id="bulk-pay-count" class="font-semibold">0 tagihan dipilih</span>
        <input type="date" id="bulk-pay-date" class="px-2 py-1.5 rounded-md text-slate-900 text-xs">
        <select id="bulk-pay-method" class="px-2 py-1.5 rounded-md text-slate-900 text-xs">
            <option value="cash">Cash</option>
            <option value="transfer">Transfer</option>
            <option value="qris">QRIS</option>
            <option value="lainnya">Lainnya</option>
        </select>
        <button type="button" id="bulk-pay-submit" onclick="submitBulkPay()" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 rounded-md font-semibold cursor-pointer">
            Bayar Massal (Lunas Penuh)
        </button>
        <button type="button" onclick="clearBulkPaySelection()" class="text-slate-300 hover:text-white cursor-pointer">Batal</button>
    </div>

    @push('scripts')
        <script>
            function getSelectedBulkPayIds() {
                return Array.from(document.querySelectorAll('.bulk-pay-checkbox:checked')).map(el => el.value);
            }

            function updateBulkPayBar() {
                const ids = getSelectedBulkPayIds();
                const bar = document.getElementById('bulk-pay-bar');
                document.getElementById('bulk-pay-count').textContent = ids.length + ' tagihan dipilih';
                bar.classList.toggle('hidden', ids.length === 0);
                if (!document.getElementById('bulk-pay-date').value) {
                    document.getElementById('bulk-pay-date').value = new Date().toISOString().slice(0, 10);
                }
            }

            function toggleAllBulkPay(checkbox) {
                document.querySelectorAll('.bulk-pay-checkbox').forEach(el => { el.checked = checkbox.checked; });
                updateBulkPayBar();
            }

            function clearBulkPaySelection() {
                document.querySelectorAll('.bulk-pay-checkbox').forEach(el => { el.checked = false; });
                document.getElementById('bulk-select-all').checked = false;
                updateBulkPayBar();
            }

            function submitBulkPay() {
                const ids = getSelectedBulkPayIds();
                if (ids.length === 0) return;

                const btn = document.getElementById('bulk-pay-submit');
                btn.disabled = true;
                btn.textContent = 'Memproses...';

                fetch('{{ route('invoices.payments.bulk-store') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        invoice_ids: ids,
                        payment_date: document.getElementById('bulk-pay-date').value,
                        payment_method: document.getElementById('bulk-pay-method').value,
                    }),
                })
                    .then(async (res) => {
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || !data.success) {
                            throw new Error(data.message || 'Gagal memproses pembayaran massal.');
                        }
                        return data;
                    })
                    .then((data) => {
                        alert(data.message);
                        window.location.reload();
                    })
                    .catch((err) => {
                        alert(err.message);
                        btn.disabled = false;
                        btn.textContent = 'Bayar Massal (Lunas Penuh)';
                    });
            }
        </script>
    @endpush
@endcan

@include('partials.quick-payment-modal')
@endsection
