@extends('layouts.app')

@section('title', 'Detail POP - Whusnet Operasional')
@section('page_title', 'Detail POP / Cabang')

@section('content')
<!-- Back link -->
<div class="mb-6">
    <a href="{{ route('master.pop.index') }}" class="inline-flex items-center gap-2 text-xs font-bold text-slate-500 hover:text-slate-800 transition-colors">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Kembali ke Daftar POP
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Left Column: Profile Card & Location Details -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Main Card -->
        <div class="bg-white border border-slate-200 rounded-lg overflow-hidden shadow-sm">
            <!-- Header -->
            <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 bg-sky-100 text-sky-600 rounded-lg flex items-center justify-center font-bold text-sm shrink-0">
                        {{ strtoupper(substr($pop->name, 0, 2)) }}
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-800 tracking-tight">{{ $pop->name }}</h3>
                        <p class="text-[11px] font-mono text-slate-400 mt-0.5">Kode: {{ $pop->code }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Type Badge -->
                    @if($pop->type === 'pusat')
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-100 uppercase tracking-wide">Pusat</span>
                    @elseif($pop->type === 'cabang')
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-purple-50 text-purple-700 border border-purple-100 uppercase tracking-wide">Cabang</span>
                    @else
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-100 uppercase tracking-wide">Mini POP</span>
                    @endif

                    <!-- Status Badge -->
                    @if($pop->status === 'active')
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-green-50 text-green-700 border border-green-100">Aktif</span>
                    @else
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-100">Nonaktif</span>
                    @endif
                </div>
            </div>

            <!-- Body -->
            <div class="p-6 space-y-6">
                <!-- Info Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <!-- PIC Info -->
                    <div>
                        <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2.5">Penanggung Jawab (PIC)</h4>
                        @if($pop->pic_name)
                        <div class="bg-slate-50 border border-slate-100 rounded-lg p-3.5">
                            <p class="text-sm font-bold text-slate-800">{{ $pop->pic_name }}</p>
                            @if($pop->pic_phone)
                            <a href="tel:{{ $pop->pic_phone }}" class="text-xs font-mono text-sky-600 hover:text-sky-800 mt-1 flex items-center gap-1.5 hover:underline">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                                {{ $pop->pic_phone }}
                            </a>
                            @endif
                        </div>
                        @else
                        <p class="text-xs text-slate-400 italic">Belum ada data PIC.</p>
                        @endif
                    </div>

                    <!-- Geolocation Info -->
                    <div>
                        <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2.5">Koordinat Geografis</h4>
                        <div class="bg-slate-50 border border-slate-100 rounded-lg p-3.5 font-mono text-xs text-slate-700 space-y-1">
                            <div class="flex justify-between">
                                <span class="text-slate-400 font-sans">Latitude:</span>
                                <span class="font-bold data-text">{{ $pop->latitude ?? 'Belum diset' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400 font-sans">Longitude:</span>
                                <span class="font-bold data-text">{{ $pop->longitude ?? 'Belum diset' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Identifier Settings -->
                <div class="border-t border-slate-100 pt-5">
                    <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2.5">Pengaturan Identifier</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="bg-slate-50 border border-slate-100 rounded-lg p-3.5">
                            <span class="block text-[10px] text-slate-400 font-bold uppercase tracking-wide">Kode Identifier POP</span>
                            <span class="block text-sm font-mono font-bold text-slate-800 mt-1">{{ $pop->pop_code ?? '-' }}</span>
                        </div>
                        <div class="bg-slate-50 border border-slate-100 rounded-lg p-3.5">
                            <span class="block text-[10px] text-slate-400 font-bold uppercase tracking-wide">Format ID Request</span>
                            <span class="block text-sm font-mono font-bold text-slate-800 mt-1">{{ $pop->registration_prefix && $pop->pop_code ? $pop->registration_prefix . '-' . $pop->pop_code . '-000001' : '-' }}</span>
                        </div>
                        <div class="bg-slate-50 border border-slate-100 rounded-lg p-3.5">
                            <span class="block text-[10px] text-slate-400 font-bold uppercase tracking-wide">Format CID</span>
                            <span class="block text-sm font-mono font-bold text-slate-800 mt-1">{{ $pop->cid_prefix && $pop->pop_code ? $pop->cid_prefix . '-' . $pop->pop_code . '-000001' : '-' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Location Address -->
                <div class="border-t border-slate-100 pt-5">
                    <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2.5">Alamat Lengkap</h4>
                    <p class="text-sm text-slate-800 leading-relaxed font-medium">
                        {{ $pop->address ?? 'Alamat fisik belum diisi.' }}
                    </p>
                    @if($pop->city || $pop->district || $pop->village)
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @if($pop->village)
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600">Desa: {{ $pop->village }}</span>
                        @endif
                        @if($pop->district)
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600">Kec: {{ $pop->district }}</span>
                        @endif
                        @if($pop->city)
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600">Kab/Kota: {{ $pop->city }}</span>
                        @endif
                    </div>
                    @endif
                </div>
            </div>

            <!-- Footer Actions -->
            @if(auth()->user()->hasPermission('manage_pop'))
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-3">
                <form action="{{ route('master.pop.toggle', $pop) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" 
                            class="inline-flex items-center justify-center gap-2 px-4 py-2 border border-slate-300 rounded-md shadow-sm text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 transition-colors focus:outline-none cursor-pointer"
                            onclick="return confirm('Apakah Anda yakin ingin mengubah status POP {{ $pop->name }}?')">
                        <svg class="h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Ubah Status ({{ $pop->status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }})
                    </button>
                </form>
                
                <a href="{{ route('master.pop.edit', $pop) }}" class="inline-flex items-center justify-center gap-2 px-4 py-2 border border-transparent rounded-md shadow-sm text-xs font-semibold text-white bg-sky-600 hover:bg-sky-700 transition-colors focus:outline-none cursor-pointer">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                    </svg>
                    Ubah POP
                </a>
            </div>
            @endif
        </div>
    </div>

    <!-- Right Column: Parent-Child Hierarchies -->
    <div class="space-y-6">
        <!-- Parent POP Details -->
        <div class="bg-white border border-slate-200 rounded-lg p-6 shadow-sm">
            <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-4 border-b border-slate-100 pb-2">Parent POP (Atasan)</h4>
            @if($pop->parent)
            <div class="flex items-start gap-3 bg-slate-50 border border-slate-100 rounded-lg p-4">
                <div class="h-8 w-8 bg-sky-100 text-sky-600 rounded-md flex items-center justify-center font-bold text-xs shrink-0">
                    {{ strtoupper(substr($pop->parent->name, 0, 2)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <a href="{{ route('master.pop.show', $pop->parent) }}" class="text-sm font-bold text-slate-800 hover:text-sky-600 transition-colors block truncate">{{ $pop->parent->name }}</a>
                    <span class="block text-[10px] text-slate-400 font-mono mt-0.5">Kode: {{ $pop->parent->code }}</span>
                    <div class="mt-2 flex items-center gap-1.5">
                        @if($pop->parent->type === 'pusat')
                        <span class="inline-flex items-center px-1.5 py-0.25 rounded text-[9px] font-bold bg-blue-50 text-blue-700 border border-blue-100 uppercase">Pusat</span>
                        @elseif($pop->parent->type === 'cabang')
                        <span class="inline-flex items-center px-1.5 py-0.25 rounded text-[9px] font-bold bg-purple-50 text-purple-700 border border-purple-100 uppercase">Cabang</span>
                        @else
                        <span class="inline-flex items-center px-1.5 py-0.25 rounded text-[9px] font-bold bg-amber-50 text-amber-700 border border-amber-100 uppercase">Mini POP</span>
                        @endif
                    </div>
                </div>
            </div>
            @else
            <p class="text-xs text-slate-400 italic">POP ini tidak memiliki parent (adalah tingkat teratas / root POP).</p>
            @endif
        </div>

        <!-- Child POPs List -->
        <div class="bg-white border border-slate-200 rounded-lg p-6 shadow-sm">
            <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-4 border-b border-slate-100 pb-2">Child POP (Keturunan)</h4>
            @if($pop->children->isEmpty())
            <p class="text-xs text-slate-400 italic">Tidak ada POP lain di bawah POP ini.</p>
            @else
            <div class="space-y-3">
                @foreach($pop->children as $child)
                <div class="flex items-center justify-between gap-4 p-3 bg-slate-50 border border-slate-100 rounded-lg">
                    <div class="min-w-0">
                        <a href="{{ route('master.pop.show', $child) }}" class="text-xs font-bold text-slate-800 hover:text-sky-600 transition-colors block truncate">{{ $child->name }}</a>
                        <span class="text-[10px] font-mono text-slate-400 block mt-0.5">Kode: {{ $child->code }}</span>
                    </div>
                    <div class="shrink-0 flex items-center gap-1.5">
                        @if($child->type === 'pusat')
                        <span class="inline-flex items-center px-1.5 py-0.25 rounded text-[8px] font-bold bg-blue-50 text-blue-700 border border-blue-100 uppercase">Pusat</span>
                        @elseif($child->type === 'cabang')
                        <span class="inline-flex items-center px-1.5 py-0.25 rounded text-[8px] font-bold bg-purple-50 text-purple-700 border border-purple-100 uppercase">Cabang</span>
                        @else
                        <span class="inline-flex items-center px-1.5 py-0.25 rounded text-[8px] font-bold bg-amber-50 text-amber-700 border border-amber-100 uppercase">Mini POP</span>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
