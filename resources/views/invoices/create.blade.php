@extends('layouts.app')

@section('title', 'Buat Tagihan Manual - Whusnet Operasional')
@section('page_title', 'Buat Tagihan Manual')

@section('content')
<div class="space-y-6">
    <div>
        <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs text-text-muted mb-1">
            <a href="{{ route('invoices.index') }}" class="hover:text-text-main transition-colors">Daftar Tagihan</a>
            <svg class="h-3 w-3 text-text-muted/60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="font-semibold text-text-main">Buat Tagihan Manual</span>
        </nav>
        <h1 class="text-xl sm:text-2xl font-bold text-text-main tracking-tight">Buat Tagihan Manual</h1>
        <p class="text-xs text-text-muted mt-1">Perbaikan, Lainnya, atau Pindah Lokasi. Tagihan terbit sebagai Belum Dibayar — catat pembayarannya dari Detail Tagihan / List Tagihan.</p>
    </div>

    @if(! $customer)
        {{-- Pintu masuk polos (Halaman Tagihan): cari dulu, baru form muncul. --}}
        <div class="bg-surface border border-border rounded-xl shadow-2xs p-6 space-y-4">
            <form action="{{ route('invoices.create') }}" method="GET" class="flex items-end gap-3">
                <div class="flex-1">
                    <label for="q" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1.5">Cari Pelanggan (CID / Nama)</label>
                    <input type="text" name="q" id="q" value="{{ $search }}" placeholder="Ketik CID atau nama pelanggan..." autofocus
                           class="w-full px-3 py-2 border border-border rounded-lg shadow-2xs focus:ring-2 focus:ring-primary/25 focus:border-primary text-xs bg-surface text-text-main transition-colors">
                </div>
                <button type="submit" class="px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg shadow-2xs transition-colors cursor-pointer">
                    Cari
                </button>
            </form>

            @if($search !== '')
                <div class="divide-y divide-border border border-border rounded-lg overflow-hidden">
                    @forelse($searchResults as $result)
                        <a href="{{ route('invoices.create', ['customer_id' => $result->id]) }}"
                           class="flex items-center justify-between px-4 py-3 text-xs hover:bg-surface-muted/60 transition-colors">
                            <div>
                                <span class="block font-semibold text-text-main">{{ $result->full_name }}</span>
                                <span class="block text-[10px] text-text-muted font-mono">{{ $result->cid ?? $result->customer_code ?? '-' }}</span>
                            </div>
                            <span class="text-[10px] font-bold text-primary uppercase">Pilih →</span>
                        </a>
                    @empty
                        <p class="px-4 py-6 text-center text-xs text-text-muted">Tidak ada pelanggan yang cocok dengan pencarian ini.</p>
                    @endforelse
                </div>
            @endif
        </div>
    @elseif($customerError)
        <div class="bg-surface border border-border rounded-xl shadow-2xs p-6 space-y-3">
            <p class="text-xs font-semibold text-red-600 dark:text-red-400">{{ $customerError }}</p>
            <a href="{{ route('invoices.create') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:underline">
                ← Cari pelanggan lain
            </a>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-surface border border-border rounded-xl shadow-2xs overflow-hidden">
                <div class="px-6 py-4 border-b border-border bg-surface-muted/30">
                    <h2 class="text-xs font-bold text-text-main uppercase tracking-wider">Form Tagihan Manual</h2>
                    <p class="text-[11px] text-text-muted mt-0.5">Deskripsi &amp; nominal diisi manual — tidak diambil dari paket langganan.</p>
                </div>

                <form action="{{ route('invoices.store') }}" method="POST" class="p-6 space-y-5">
                    @csrf
                    <input type="hidden" name="customer_id" value="{{ $customer->id }}">

                    <div class="p-3 bg-surface-muted/50 border border-border rounded-lg text-xs">
                        <span class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1">Pelanggan</span>
                        <span class="font-bold text-text-main">{{ $customer->full_name }}</span>
                        <span class="block text-[10px] text-text-muted font-mono mt-0.5">{{ $customer->cid ?? $customer->customer_code ?? '-' }}</span>
                    </div>

                    <div>
                        <label for="manual_category" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1.5">Jenis Tagihan</label>
                        <select name="manual_category" id="manual_category" required onchange="miToggleCategoryFields()"
                                class="w-full px-3 py-2 border border-border rounded-lg shadow-2xs focus:ring-2 focus:ring-primary/25 focus:border-primary text-xs font-semibold bg-surface text-text-main transition-colors">
                            @foreach($categories as $category)
                                <option value="{{ $category->value }}" @selected(old('manual_category') === $category->value)>{{ $category->label() }}</option>
                            @endforeach
                        </select>
                        @error('manual_category')<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div id="mi-subtype-wrap" class="hidden">
                        <label for="manual_subtype_name" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1.5">Nama Sub (mis. Over Kabel, Pendapatan A)</label>
                        <input type="text" name="manual_subtype_name" id="manual_subtype_name" value="{{ old('manual_subtype_name') }}" maxlength="150"
                               class="w-full px-3 py-2 border border-border rounded-lg shadow-2xs focus:ring-2 focus:ring-primary/25 focus:border-primary text-xs bg-surface text-text-main transition-colors">
                        @error('manual_subtype_name')<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="description" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1.5">Deskripsi Tagihan</label>
                        <textarea name="description" id="description" rows="3" required placeholder="Jelaskan pekerjaan/tagihan ini..."
                                  class="w-full px-3 py-2 border border-border rounded-lg shadow-2xs focus:ring-2 focus:ring-primary/25 focus:border-primary text-xs bg-surface text-text-main placeholder:text-text-muted/60 transition-colors">{{ old('description') }}</textarea>
                        @error('description')<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="amount" class="block text-[10px] font-bold text-text-muted uppercase tracking-wider mb-1.5">Nominal Tagihan (Rp)</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none font-mono text-xs font-bold text-text-muted">Rp</span>
                            <input type="text" inputmode="decimal" name="amount" id="amount" data-rupiah value="{{ old('amount') }}" required
                                   class="w-full pl-9 pr-3 py-2 border border-border rounded-lg shadow-2xs focus:ring-2 focus:ring-primary/25 focus:border-primary text-xs font-mono font-bold bg-surface text-text-main transition-colors">
                        </div>
                        @error('amount')<p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-border">
                        <a href="{{ route('invoices.create') }}" class="px-4 py-2 border border-border text-text-secondary bg-surface hover:bg-surface-muted font-semibold rounded-lg shadow-2xs transition-colors text-xs">
                            Batal
                        </a>
                        <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg shadow-2xs transition-colors text-xs cursor-pointer">
                            Proses Tagihan
                        </button>
                    </div>
                </form>
            </div>

            <div class="bg-surface border border-border rounded-xl p-6 shadow-2xs h-fit space-y-3 text-xs">
                <h2 class="text-xs font-bold text-text-main uppercase tracking-wider pb-3 border-b border-border">Ringkasan</h2>
                <div>
                    <p class="text-[10px] font-semibold text-text-muted uppercase tracking-wider">POP / Cabang</p>
                    <p class="font-medium text-text-main mt-0.5">{{ $customer->pop->name ?? '-' }}</p>
                </div>
                @if($customer->customerService)
                    <div>
                        <p class="text-[10px] font-semibold text-text-muted uppercase tracking-wider">Paket Langganan</p>
                        <p class="font-medium text-text-main mt-0.5">{{ $customer->customerService->package_name_snapshot }}</p>
                    </div>
                @endif
                <p class="text-[11px] text-text-muted pt-2 border-t border-border">
                    Tagihan Manual boleh terbit di periode yang sama dengan tagihan bulanan pelanggan — keduanya jenis tagihan yang berbeda.
                </p>
                <p class="text-[11px] text-text-muted pt-2 border-t border-border">
                    Tagihan terbit sebagai <strong>Belum Dibayar</strong>. Catat pembayarannya nanti dari halaman Detail Tagihan atau List Tagihan (tombol Bayar/Bayar Cicil).
                </p>
            </div>
        </div>
    @endif
</div>

@if($customer && ! $customerError)
<script>
    function miToggleCategoryFields() {
        const category = document.getElementById('manual_category').value;
        const wrap = document.getElementById('mi-subtype-wrap');
        const input = document.getElementById('manual_subtype_name');
        const isLainnya = category === 'lainnya';

        wrap.classList.toggle('hidden', !isLainnya);
        input.required = isLainnya;
    }
    document.addEventListener('DOMContentLoaded', miToggleCategoryFields);
</script>
@endif
@endsection
