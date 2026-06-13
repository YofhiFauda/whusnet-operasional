@extends('layouts.app')

@section('title', 'Input Pembayaran - Whusnet Operasional')
@section('page_title', 'Input Pembayaran')

@section('content')
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <nav class="flex text-xs font-semibold text-slate-400 uppercase tracking-wider gap-2 mb-2">
            <a href="{{ route('invoices.index') }}" class="hover:text-slate-700 transition-colors">Daftar Tagihan</a>
            <span>/</span>
            <a href="{{ route('invoices.show', $invoice->id) }}" class="hover:text-slate-700 transition-colors">{{ $invoice->invoice_number }}</a>
            <span>/</span>
            <span class="text-slate-600">Input Pembayaran</span>
        </nav>
        <h1 class="text-xl font-bold text-slate-800 tracking-tight">Input Pembayaran {{ $invoice->invoice_number }}</h1>
    </div>
    <a href="{{ route('invoices.show', $invoice->id) }}" class="inline-flex items-center gap-1.5 px-4 py-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none">
        Kembali
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 bg-white border border-slate-200 rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Form Pembayaran</h2>
            <p class="text-xs text-slate-500 mt-1">Pembayaran otomatis memperbarui total terbayar, sisa tagihan, dan status invoice.</p>
        </div>

        <form action="{{ route('invoices.payments.store', $invoice->id) }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-5">
            @csrf

            @if($errors->any())
                <div class="rounded-md border border-red-100 bg-red-50 px-4 py-3 text-xs text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <div>
                <label for="payment_date" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Tanggal Bayar</label>
                <input type="date" name="payment_date" id="payment_date" value="{{ old('payment_date', now()->format('Y-m-d')) }}" required
                       class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs font-mono">
            </div>

            <div>
                <label for="payment_method" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Metode Bayar</label>
                <select name="payment_method" id="payment_method" required class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs">
                    @foreach(['cash' => 'Cash', 'transfer' => 'Transfer', 'qris' => 'QRIS', 'lainnya' => 'Lainnya'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="amount" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Nominal Bayar</label>
                <input type="number" name="amount" id="amount" value="{{ old('amount', (float) $invoice->remaining_amount) }}" min="1" max="{{ (float) $invoice->remaining_amount }}" step="0.01" required
                       class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs font-mono">
                <p class="text-[10px] text-slate-500 mt-1">Maksimal sebesar sisa tagihan: Rp {{ number_format((float) $invoice->remaining_amount, 2, ',', '.') }}.</p>
            </div>

            <div>
                <label for="proof_file" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Bukti Pembayaran</label>
                <input type="file" name="proof_file" id="proof_file" accept=".jpg,.jpeg,.png,.pdf"
                       class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs">
                <p class="text-[10px] text-slate-500 mt-1">Opsional. Format: JPG, PNG, atau PDF maksimal 2 MB.</p>
            </div>

            <div>
                <label for="note" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Catatan</label>
                <textarea name="note" id="note" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs">{{ old('note') }}</textarea>
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-slate-100">
                <a href="{{ route('invoices.show', $invoice->id) }}" class="px-4 py-2 border border-slate-200 text-slate-700 bg-white hover:bg-slate-50 font-semibold rounded-md shadow-sm transition-colors text-xs">
                    Batal
                </a>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-md shadow-sm transition-colors text-xs">
                    Simpan Pembayaran
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white border border-slate-200 rounded-lg p-6 h-fit">
        <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4">Ringkasan Tagihan</h2>
        <div class="space-y-3 text-sm">
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Pelanggan</p>
                <p class="font-semibold text-slate-900 mt-1">{{ $invoice->customer->full_name ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">POP / Cabang</p>
                <p class="text-slate-900 mt-1">{{ $invoice->pop->name ?? '-' }}</p>
            </div>
            <div class="pt-3 border-t border-slate-100 space-y-2">
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500">Total</span>
                    <span class="font-mono text-slate-900">Rp {{ number_format((float) $invoice->total_amount, 2, ',', '.') }}</span>
                </div>
                <div class="flex justify-between gap-4">
                    <span class="text-slate-500">Terbayar</span>
                    <span class="font-mono text-slate-900">Rp {{ number_format((float) $invoice->paid_amount, 2, ',', '.') }}</span>
                </div>
                <div class="flex justify-between gap-4">
                    <span class="font-bold text-slate-800">Sisa</span>
                    <span class="font-mono font-bold text-slate-900">Rp {{ number_format((float) $invoice->remaining_amount, 2, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
