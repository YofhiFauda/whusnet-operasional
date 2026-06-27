@extends('layouts.app')

@section('title', 'Assign POP — ' . $user->name)
@section('page_title', 'Assign POP: ' . $user->name)

@section('content')

<div class="mb-4">
    <a href="{{ route('users.index') }}"
       class="inline-flex items-center gap-1 text-sm font-medium text-primary hover:text-primary-hover transition-colors">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Kembali ke Daftar User
    </a>
</div>

<div class="max-w-2xl">
    {{-- Info user --}}
    <div class="mb-4 rounded-md border border-border bg-surface-muted px-4 py-3 flex items-start gap-3">
        <div class="h-9 w-9 rounded-full bg-primary/10 flex items-center justify-center font-bold text-primary flex-shrink-0 text-sm">
            {{ strtoupper(substr($user->name, 0, 2)) }}
        </div>
        <div class="min-w-0">
            <p class="text-sm font-semibold text-text-main">{{ $user->name }}</p>
            <p class="text-xs text-text-muted">{{ $user->email }}</p>
            <p class="text-xs text-text-muted mt-0.5">
                Role: <span class="font-medium text-text-secondary">{{ $user->role?->name ?? '—' }}</span>
            </p>
        </div>
        @if($user->hasFullAccess())
            <div class="ml-auto flex-shrink-0">
                <span class="inline-flex items-center rounded-full bg-warning-bg border border-warning-border px-2.5 py-0.5 text-xs font-semibold text-warning">
                    Akses Penuh
                </span>
            </div>
        @endif
    </div>

    @if($user->hasFullAccess())
        <div class="mb-4 rounded-md bg-info-bg border border-info-border px-4 py-3 text-sm text-text-secondary">
            <p>
                User ini memiliki role <strong>{{ $user->role->name }}</strong> dengan akses penuh ke semua POP.
                Pilihan di bawah disimpan sebagai referensi data saja dan tidak membatasi aksesnya.
            </p>
        </div>
    @endif

    <form action="{{ route('users.pops.update', $user) }}" method="POST">
        @csrf
        @method('PUT')

        <x-ui.card padding="compact">
            <x-slot name="header">
                <div>
                    <h2 class="text-md font-semibold text-text-main">Pilih POP / Cabang</h2>
                    <p class="mt-0.5 text-sm text-text-muted">
                        Pilih cabang yang dapat diakses berdasarkan hierarki.
                        Memilih POP induk akan otomatis mencakup semua sub-POP di bawahnya.
                    </p>
                </div>
            </x-slot>

            @php
                $assignedPopIds = $user->pops->pluck('id')->map(fn($id) => (int)$id)->all();
            @endphp

            <x-ui.pop-tree-picker
                :popTree="$popTree"
                :selected="$assignedPopIds"
                name="pop_ids[]"
                id="pop-tree-edit"
            />

            @error('pop_ids')
                <p class="mt-2 text-xs text-error">{{ $message }}</p>
            @enderror

            <x-slot name="footer">
                <div class="flex justify-end gap-2">
                    <a href="{{ route('users.index') }}" class="btn-secondary">Batal</a>
                    <button type="submit" class="btn-primary">Simpan Penugasan</button>
                </div>
            </x-slot>
        </x-ui.card>
    </form>
</div>

@endsection
