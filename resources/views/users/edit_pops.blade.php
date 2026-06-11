@extends('layouts.app')

@section('title', 'Assign POP ke User')

@section('content')
<div class="mb-6">
    <a href="{{ route('users.index') }}" class="text-sm text-blue-600 hover:text-blue-800 flex items-center mb-4">
        <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Kembali ke Daftar User
    </a>
    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Assign POP/Cabang</h1>
    <p class="text-sm text-slate-500 mt-1">Atur hak akses wilayah operasional untuk user <span class="font-semibold text-slate-700">{{ $user->name }}</span></p>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden max-w-3xl">
    <form action="{{ route('users.pops.update', $user) }}" method="POST">
        @csrf
        @method('PUT')
        
        <div class="p-6 border-b border-slate-200">
            <div class="mb-6 bg-slate-50 p-4 rounded-lg border border-slate-100 flex items-start">
                <div class="flex-shrink-0 mt-0.5">
                    <svg class="w-5 h-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-slate-800">Informasi User</h3>
                    <div class="mt-1 text-sm text-slate-600">
                        <p><strong>Nama:</strong> {{ $user->name }}</p>
                        <p><strong>Email:</strong> {{ $user->email }}</p>
                        <p><strong>Role:</strong> {{ optional($user->role)->name ?? '-' }}</p>
                    </div>
                </div>
            </div>

            @if(in_array(optional($user->role)->name, ['Owner', 'Admin Pusat']))
                <div class="bg-purple-50 text-purple-800 p-4 rounded-lg border border-purple-100 mb-6">
                    <strong>Catatan:</strong> User ini memiliki role <strong>{{ $user->role->name }}</strong>, sehingga secara otomatis memiliki akses ke <strong>semua POP</strong>. Pilihan di bawah hanya sebagai data opsional dan tidak akan membatasi aksesnya.
                </div>
            @endif

            <h3 class="text-base font-semibold text-slate-900 mb-4">Pilih POP / Cabang</h3>
            
            <div class="space-y-3">
                @php
                    $assignedPops = $user->pops->pluck('id')->toArray();
                @endphp
                
                @forelse($pops as $pop)
                    <label class="flex items-start p-3 border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-50 transition-colors {{ in_array($pop->id, $assignedPops) ? 'bg-blue-50/50 border-blue-200' : '' }}">
                        <div class="flex items-center h-5">
                            <input type="checkbox" name="pop_ids[]" value="{{ $pop->id }}" 
                                class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500"
                                {{ in_array($pop->id, $assignedPops) ? 'checked' : '' }}>
                        </div>
                        <div class="ml-3 flex-1">
                            <span class="block text-sm font-medium text-slate-900">{{ $pop->name }}</span>
                            <span class="block text-xs text-slate-500">Kode: {{ $pop->code }} &bull; Tipe: <span class="capitalize">{{ str_replace('_', ' ', $pop->type) }}</span></span>
                        </div>
                    </label>
                @empty
                    <div class="text-sm text-slate-500 p-4 text-center border border-dashed border-slate-300 rounded-lg">
                        Belum ada data POP/Cabang di sistem.
                    </div>
                @endforelse
            </div>
            
            @error('pop_ids')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-3">
            <a href="{{ route('users.index') }}" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-500 transition-colors">
                Batal
            </a>
            <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                Simpan Penugasan
            </button>
        </div>
    </form>
</div>
@endsection
