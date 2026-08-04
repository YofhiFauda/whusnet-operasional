@extends('layouts.app')

@section('title', 'Kolektor: ' . $collector->name . ' - Whusnet Operasional')
@section('page_title', $collector->name)

@section('content')
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h3 class="text-slate-800 dark:text-slate-200 text-sm font-semibold uppercase tracking-wider">Kolektor — {{ $collector->name }}</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Worklist, bayar 1-by-1/massal, dan atur pelanggan tanggung jawab kolektor ini.</p>
    </div>
    <a href="{{ route('collectors.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none">
        Semua Kolektor
    </a>
</div>

@if ($errors->any())
    <div class="mb-6 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 text-red-700 dark:text-red-400 text-sm rounded-lg p-4">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

@if (session('success'))
    <div class="mb-6 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-sm rounded-lg p-4">
        {{ session('success') }}
    </div>
@endif

<div id="batch-alert" class="hidden mb-6 text-sm rounded-lg p-4"></div>

<!-- Tabs -->
<div class="flex gap-1 border-b border-slate-200 dark:border-slate-700 mb-6">
    <a href="{{ route('collectors.show', ['collector' => $collector->id, 'tab' => 'worklist']) }}"
       class="px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition-colors {{ $tab === 'worklist' ? 'border-sky-600 text-sky-700 dark:text-sky-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200' }}">
        Worklist &amp; Bayar
    </a>
    <a href="{{ route('collectors.show', ['collector' => $collector->id, 'tab' => 'assign']) }}"
       class="px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition-colors {{ $tab === 'assign' ? 'border-sky-600 text-sky-700 dark:text-sky-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200' }}">
        Atur Pelanggan
    </a>
</div>

@if ($tab === 'worklist')
    {{-- ============ TAB: WORKLIST & BAYAR ============ --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-sm text-slate-700 dark:text-slate-200">
                <thead>
                    <tr class="bg-slate-50/50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 font-semibold text-xs">
                        <th class="px-4 py-3.5 w-8 text-center">
                            <input type="checkbox" id="cb-select-all" onclick="cbToggleAll(this)" class="rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900 cursor-pointer">
                        </th>
                        <th class="px-6 py-3.5">PELANGGAN</th>
                        <th class="px-6 py-3.5">NO. TAGIHAN</th>
                        <th class="px-6 py-3.5 text-right">SISA</th>
                        <th class="px-6 py-3.5">NOMINAL BAYAR</th>
                        <th class="px-6 py-3.5">METODE</th>
                        <th class="px-6 py-3.5">TGL DITAGIH</th>
                        <th class="px-6 py-3.5 text-right">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    @forelse ($invoices as $invoice)
                        <tr class="hover:bg-slate-50/45 dark:hover:bg-slate-700/25 transition-colors" data-invoice-row="{{ $invoice->id }}">
                            <td class="px-4 py-3.5 text-center">
                                <input type="checkbox" class="cb-row-checkbox rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900 cursor-pointer" value="{{ $invoice->id }}">
                            </td>
                            <td class="px-6 py-3.5 whitespace-nowrap">
                                <div class="font-semibold text-slate-900 dark:text-slate-200">{{ $invoice->customer->full_name ?? '-' }}</div>
                                <div class="text-[10px] text-slate-400 dark:text-slate-500 font-mono">{{ $invoice->customer->cid ?? $invoice->customer->customer_code ?? '-' }}</div>
                            </td>
                            <td class="px-6 py-3.5 font-mono whitespace-nowrap">{{ $invoice->invoice_number }}</td>
                            <td class="px-6 py-3.5 text-right font-mono">Rp {{ number_format((float) $invoice->remaining_amount, 0, ',', '.') }}</td>
                            <td class="px-6 py-3.5">
                                <input type="number" step="0.01" min="1" max="{{ (float) $invoice->remaining_amount }}" value="{{ (float) $invoice->remaining_amount }}" class="cb-amount w-32 font-sans text-sm px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-200 focus:outline-none focus:border-sky-500">
                            </td>
                            <td class="px-6 py-3.5">
                                <select class="cb-method text-sm px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-200 focus:outline-none focus:border-sky-500">
                                    <option value="cash">Cash</option>
                                    <option value="transfer">Transfer</option>
                                    <option value="qris">QRIS</option>
                                    <option value="lainnya">Lainnya</option>
                                </select>
                            </td>
                            <td class="px-6 py-3.5">
                                <input type="date" class="cb-collected-date text-sm px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-200 focus:outline-none focus:border-sky-500" value="{{ now()->format('Y-m-d') }}">
                            </td>
                            <td class="px-6 py-3.5 text-right whitespace-nowrap">
                                <button type="button" onclick="cbSubmitSingle({{ $invoice->id }})" class="inline-flex items-center px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md transition-colors text-xs font-semibold cursor-pointer">
                                    Bayar
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">Kolektor ini tidak punya pelanggan dengan tunggakan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700">
            {{ $invoices->links() }}
        </div>
    </div>

    @if ($invoices->isNotEmpty())
    <div class="mt-6 sticky bottom-4">
        <div class="bg-slate-900 text-white rounded-lg shadow-xl px-5 py-3 flex flex-wrap items-center gap-3 text-xs">
            <span id="cb-count" class="font-semibold">0 baris dipilih</span>
            <button type="button" id="cb-submit" onclick="cbSubmitBatch()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 rounded-md font-semibold cursor-pointer disabled:opacity-50" disabled>
                Bayar Massal (Baris Terpilih)
            </button>
        </div>
    </div>
    @endif

    @push('scripts')
    <script>
        const CB_COLLECTOR_ID = {{ $collector->id }};
        const CB_STORE_URL = '{{ route('collector-batch.store', $collector->id) }}';

        function cbGenerateKey() {
            return 'batch-' + CB_COLLECTOR_ID + '-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10);
        }

        function cbToggleAll(checkbox) {
            document.querySelectorAll('.cb-row-checkbox').forEach(el => { el.checked = checkbox.checked; });
            cbUpdateCount();
        }

        function cbUpdateCount() {
            const checked = document.querySelectorAll('.cb-row-checkbox:checked').length;
            document.getElementById('cb-count').textContent = checked + ' baris dipilih';
            const btn = document.getElementById('cb-submit');
            if (btn) btn.disabled = checked === 0;
        }

        document.addEventListener('change', function (e) {
            if (e.target.classList.contains('cb-row-checkbox')) {
                cbUpdateCount();
            }
        });

        function cbShowAlert(message, isError) {
            const alertEl = document.getElementById('batch-alert');
            alertEl.textContent = message;
            alertEl.className = 'mb-6 text-sm rounded-lg p-4 ' + (isError
                ? 'bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 text-red-700 dark:text-red-400'
                : 'bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-emerald-700 dark:text-emerald-400');
        }

        function cbRowToPayload(tr) {
            return {
                invoice_id: parseInt(tr.querySelector('.cb-row-checkbox').value, 10),
                amount: parseFloat(tr.querySelector('.cb-amount').value),
                payment_method: tr.querySelector('.cb-method').value,
                collected_date: tr.querySelector('.cb-collected-date').value,
            };
        }

        function cbPost(rows, submittingBtn, restoreLabel) {
            fetch(CB_STORE_URL, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ idempotency_key: cbGenerateKey(), rows: rows }),
            })
                .then(async (res) => {
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.success) {
                        const detail = (data.failures || []).map(f => f.reason).filter(Boolean).join(' | ');
                        throw new Error((data.message || 'Gagal diproses.') + (detail ? ' — ' + detail : ''));
                    }
                    return data;
                })
                .then((data) => {
                    cbShowAlert(data.message, false);
                    document.getElementById('batch-alert').classList.remove('hidden');
                    setTimeout(() => window.location.reload(), 1200);
                })
                .catch((err) => {
                    cbShowAlert(err.message, true);
                    document.getElementById('batch-alert').classList.remove('hidden');
                    if (submittingBtn) {
                        submittingBtn.disabled = false;
                        submittingBtn.textContent = restoreLabel;
                    }
                });
        }

        // Bayar 1-by-1 — langsung submit satu baris, tanpa perlu centang.
        function cbSubmitSingle(invoiceId) {
            const tr = document.querySelector('tr[data-invoice-row="' + invoiceId + '"]');
            if (!tr) return;

            const btn = tr.querySelector('button');
            btn.disabled = true;
            btn.textContent = 'Memproses...';

            cbPost([cbRowToPayload(tr)], btn, 'Bayar');
        }

        // Bayar massal — semua baris yang dicentang.
        function cbSubmitBatch() {
            const rows = [];
            document.querySelectorAll('.cb-row-checkbox:checked').forEach(checkbox => {
                rows.push(cbRowToPayload(checkbox.closest('tr')));
            });

            if (rows.length === 0) return;

            const btn = document.getElementById('cb-submit');
            btn.disabled = true;
            btn.textContent = 'Memproses...';

            cbPost(rows, btn, 'Bayar Massal (Baris Terpilih)');
        }
    </script>
    @endpush

@else
    {{-- ============ TAB: ATUR PELANGGAN ============ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
            <div class="px-6 py-3.5 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-700/50">
                <h4 class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider">Pelanggan Saat Ini ({{ $assignedCustomers->total() }})</h4>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-700/50 max-h-[32rem] overflow-y-auto">
                @forelse ($assignedCustomers as $customer)
                    <div class="px-6 py-3 flex items-center justify-between gap-3">
                        <div>
                            <div class="font-semibold text-sm text-slate-900 dark:text-slate-200">{{ $customer->full_name }}</div>
                            <div class="text-[10px] text-slate-400 dark:text-slate-500 font-mono">{{ $customer->cid ?? $customer->customer_code }} &bull; {{ $customer->pop->name ?? '-' }}</div>
                        </div>
                        <form action="{{ route('collectors.release', ['collector' => $collector->id, 'customer' => $customer->id]) }}" method="POST" onsubmit="return confirm('Lepas {{ $customer->full_name }} dari kolektor ini?');">
                            @csrf
                            <button type="submit" class="text-xs font-semibold text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 cursor-pointer">
                                Lepas
                            </button>
                        </form>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada pelanggan ter-assign.</div>
                @endforelse
            </div>
            <div class="px-6 py-3 border-t border-slate-100 dark:border-slate-700">
                {{ $assignedCustomers->links() }}
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
            <div class="px-6 py-3.5 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-700/50">
                <h4 class="text-xs font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider">Tambah / Pindahkan Pelanggan</h4>
            </div>
            <div class="p-6">
                <form action="{{ route('collectors.show', ['collector' => $collector->id, 'tab' => 'assign']) }}" method="GET" class="flex gap-2 mb-4">
                    <input type="hidden" name="tab" value="assign">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama, kode, atau CID..." class="flex-1 font-sans text-sm px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-200 focus:outline-none focus:border-sky-500">
                    <button type="submit" class="bg-sky-600 hover:bg-sky-700 text-white text-sm font-medium py-2 px-4 rounded-md transition-colors cursor-pointer">Cari</button>
                </form>

                @if ($searchResults !== null)
                    <form action="{{ route('collectors.assign', $collector->id) }}" method="POST">
                        @csrf
                        <div class="divide-y divide-slate-100 dark:divide-slate-700/50 max-h-80 overflow-y-auto border border-slate-100 dark:border-slate-700 rounded-md mb-3">
                            @forelse ($searchResults as $customer)
                                <label class="px-4 py-2.5 flex items-center gap-3 hover:bg-slate-50 dark:hover:bg-slate-700/30 cursor-pointer">
                                    <input type="checkbox" name="customer_ids[]" value="{{ $customer->id }}" class="rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900">
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-medium text-slate-800 dark:text-slate-200 truncate">{{ $customer->full_name }}</div>
                                        <div class="text-[10px] text-slate-400 dark:text-slate-500">
                                            {{ $customer->pop->name ?? '-' }}
                                            @if($customer->collector)
                                                &bull; Sekarang: {{ $customer->collector->name }}
                                            @endif
                                        </div>
                                    </div>
                                </label>
                            @empty
                                <div class="px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400">Tidak ada pelanggan cocok.</div>
                            @endforelse
                        </div>
                        @if ($searchResults->isNotEmpty())
                            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold py-2.5 rounded-md transition-colors cursor-pointer">
                                Assign Terpilih ke {{ $collector->name }}
                            </button>
                        @endif
                        <div class="mt-2">{{ $searchResults->links() }}</div>
                    </form>
                @else
                    <p class="text-xs text-slate-400 dark:text-slate-500 text-center py-8">Cari pelanggan dulu buat ditambah/dipindah ke kolektor ini.</p>
                @endif
            </div>
        </div>
    </div>
@endif
@endsection
