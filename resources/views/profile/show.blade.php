@extends('layouts.app')

@section('title', 'Profil Saya - Whusnet Operasional')
@section('page_title', 'Profil Saya')

@section('content')
<div class="max-w-7xl mx-auto space-y-6 pb-12" x-data="{
    copiedEmail: false,
    copiedPhone: false,
    copyToClipboard(text, type) {
        if (!text || text === '-') return;
        navigator.clipboard.writeText(text).then(() => {
            if (type === 'email') {
                this.copiedEmail = true;
                setTimeout(() => this.copiedEmail = false, 2000);
            } else if (type === 'phone') {
                this.copiedPhone = true;
                setTimeout(() => this.copiedPhone = false, 2000);
            }
        });
    }
}">

    {{-- Alert Notifikasi Sukses --}}
    @if (session('success'))
        <x-ui.alert variant="success" class="shadow-sm">
            <div class="flex items-center justify-between">
                <span>{{ session('success') }}</span>
                <span class="text-xs font-semibold uppercase tracking-wider opacity-80">Berhasil</span>
            </div>
        </x-ui.alert>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════
         1. HERO / BANNER PROFIL PENGGUNA
    ═══════════════════════════════════════════════════════════════ --}}
    <div class="relative overflow-hidden rounded-2xl border border-slate-200/80 dark:border-slate-700/80 bg-white dark:bg-slate-800 shadow-sm">
        {{-- Banner Decorative Background --}}
        <div class="h-28 sm:h-36 w-full bg-gradient-to-r from-sky-600 via-indigo-600 to-sky-700 dark:from-slate-900 dark:via-sky-950 dark:to-indigo-950 relative overflow-hidden">
            <div class="absolute inset-0 opacity-10 dark:opacity-20 bg-[radial-gradient(#fff_1px,transparent_1px)] [background-size:16px_16px]"></div>
            <div class="absolute -right-10 -bottom-10 w-48 h-48 rounded-full bg-white/10 blur-2xl"></div>
            <div class="absolute left-1/3 -top-10 w-40 h-40 rounded-full bg-sky-400/20 blur-xl"></div>
        </div>

        {{-- Hero Body --}}
        <div class="px-4 sm:px-6 pb-6 pt-0 relative">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 -mt-12 sm:-mt-14 mb-4">
                {{-- Avatar & Identity --}}
                <div class="flex flex-col sm:flex-row items-center sm:items-end gap-4 text-center sm:text-left">
                    {{-- Avatar Initial --}}
                    <div class="relative shrink-0">
                        <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-2xl bg-gradient-to-br from-sky-500 to-indigo-600 p-1 shadow-lg ring-4 ring-white dark:ring-slate-800 flex items-center justify-center text-white font-extrabold text-2xl sm:text-3xl tracking-wider select-none">
                            {{ strtoupper(substr($user->name ?? 'AD', 0, 2)) }}
                        </div>
                        {{-- Online Status Dot --}}
                        <span class="absolute bottom-1 right-1 flex h-4 w-4">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full {{ $user->status?->value === 'active' ? 'bg-emerald-400' : 'bg-slate-400' }} opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-4 w-4 {{ $user->status?->value === 'active' ? 'bg-emerald-500' : 'bg-slate-500' }} ring-2 ring-white dark:ring-slate-800"></span>
                        </span>
                    </div>

                    {{-- Name & Email --}}
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white tracking-tight">{{ $user->name }}</h1>
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $user->status?->value === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800' : 'bg-slate-100 text-slate-700 border border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $user->status?->value === 'active' ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                {{ $user->status?->value === 'active' ? 'Akun Aktif' : 'Nonaktif' }}
                            </span>
                        </div>

                        <div class="flex flex-wrap items-center justify-center sm:justify-start gap-3 text-xs text-slate-500 dark:text-slate-400">
                            {{-- Email with Copy --}}
                            <button @click="copyToClipboard('{{ $user->email }}', 'email')"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 hover:text-sky-600 dark:hover:text-sky-400 transition-colors group cursor-pointer"
                                    title="Klik untuk menyalin email">
                                <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-sky-600 dark:group-hover:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                <span>{{ $user->email }}</span>
                                <span x-show="copiedEmail" x-transition.opacity class="text-[10px] bg-sky-100 text-sky-700 dark:bg-sky-900/60 dark:text-sky-300 font-semibold px-1.5 py-0.2 rounded">Tersalin!</span>
                            </button>

                            @if($user->phone)
                                <span class="hidden sm:inline text-slate-300 dark:text-slate-600">•</span>
                                {{-- Phone with direct link --}}
                                <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $user->phone) }}" target="_blank" rel="noopener noreferrer"
                                   class="inline-flex items-center gap-1.5 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors"
                                   title="Hubungi via WhatsApp">
                                    <svg class="w-3.5 h-3.5 text-emerald-500" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                                    </svg>
                                    <span>{{ $user->phone }}</span>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Role Badge & ID Meta --}}
                <div class="flex flex-wrap items-center justify-center sm:justify-end gap-2 pt-2 sm:pt-0">
                    <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-sky-50 dark:bg-sky-950/50 border border-sky-200 dark:border-sky-800 text-sky-800 dark:text-sky-200 text-xs font-semibold shadow-xs">
                        <svg class="w-4 h-4 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        <span>Role: {{ $user->role->name ?? 'User' }}</span>
                    </div>

                    <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-700/60 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-300 text-xs font-medium">
                        <span class="text-slate-400 dark:text-slate-500 font-mono">ID:</span>
                        <span class="font-mono font-semibold">#{{ $user->id }}</span>
                    </div>
                </div>
            </div>

            {{-- Quick Metadata Bar --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-4 border-t border-slate-100 dark:border-slate-700/70 text-xs">
                <div class="bg-slate-50/80 dark:bg-slate-900/40 p-2.5 rounded-xl border border-slate-100 dark:border-slate-800">
                    <span class="text-slate-400 dark:text-slate-500 block text-[11px]">Terdaftar Sejak</span>
                    <span class="font-semibold text-slate-700 dark:text-slate-200">{{ \App\Support\IndonesianDate::date($user->created_at) }}</span>
                </div>

                <div class="bg-slate-50/80 dark:bg-slate-900/40 p-2.5 rounded-xl border border-slate-100 dark:border-slate-800">
                    <span class="text-slate-400 dark:text-slate-500 block text-[11px]">Cakupan Scope</span>
                    <span class="font-semibold text-slate-700 dark:text-slate-200">
                        @if($user->hasFullAccess() || ($user->roleScopes->first()?->scope_type?->value === 'all_pop'))
                            Nasional (Semua POP)
                        @elseif($user->pops->isNotEmpty())
                            {{ $user->pops->count() }} Cabang POP
                        @else
                            Default Role
                        @endif
                    </span>
                </div>

                <div class="bg-slate-50/80 dark:bg-slate-900/40 p-2.5 rounded-xl border border-slate-100 dark:border-slate-800">
                    <span class="text-slate-400 dark:text-slate-500 block text-[11px]">Guard Otentikasi</span>
                    <span class="font-semibold text-slate-700 dark:text-slate-200 font-mono">{{ $user->role->guard_name ?? 'web' }}</span>
                </div>

                <div class="bg-slate-50/80 dark:bg-slate-900/40 p-2.5 rounded-xl border border-slate-100 dark:border-slate-800">
                    <span class="text-slate-400 dark:text-slate-500 block text-[11px]">Terakhir Diperbarui</span>
                    <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $user->updated_at ? \App\Support\IndonesianDate::dateTime($user->updated_at) : '-' }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         2. UNIFIED 2x2 QUADRANT GRID
            - Kiri Atas   : Card Data Diri & Akun
            - Kanan Atas  : Card Cakupan Wilayah & Hak Akses
            - Kiri Bawah  : Card Keamanan & Ganti Password
            - Kanan Bawah : Card Aktivitas Terakhir & Sesi Perangkat
    ═══════════════════════════════════════════════════════════════ --}}
    <div class="rounded-2xl border border-slate-200/90 dark:border-slate-700/90 bg-slate-200/90 dark:bg-slate-700/90 grid grid-cols-1 lg:grid-cols-2 gap-px shadow-sm overflow-hidden">

        {{-- ── 1. KIRI ATAS (Top-Left): KARTU DATA DIRI & AKUN ── --}}
        <div class="bg-white dark:bg-slate-800 p-5 sm:p-7 flex flex-col justify-between">
            <div>
                {{-- Header --}}
                <div class="flex items-center justify-between pb-4 mb-5 border-b border-slate-100 dark:border-slate-700/70">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-sky-100 dark:bg-sky-900/40 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-800 dark:text-slate-100 leading-tight">Data Diri &amp; Akun</h2>
                            <p class="text-xs text-slate-400 dark:text-slate-500">Informasi identitas akun yang terdaftar di sistem</p>
                        </div>
                    </div>

                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                        Read-only
                    </span>
                </div>

                {{-- Detail Grid --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    {{-- Nama --}}
                    <div class="p-3.5 rounded-xl bg-slate-50/70 dark:bg-slate-900/30 border border-slate-100 dark:border-slate-700/50">
                        <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 flex items-center justify-between">
                            <span>Nama Lengkap</span>
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100 break-words">{{ $user->name }}</dd>
                    </div>

                    {{-- Email --}}
                    <div class="p-3.5 rounded-xl bg-slate-50/70 dark:bg-slate-900/30 border border-slate-100 dark:border-slate-700/50">
                        <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 flex items-center justify-between">
                            <span>Alamat Email</span>
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/></svg>
                        </dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100 font-mono break-all">{{ $user->email }}</dd>
                    </div>

                    {{-- Telepon --}}
                    <div class="p-3.5 rounded-xl bg-slate-50/70 dark:bg-slate-900/30 border border-slate-100 dark:border-slate-700/50">
                        <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 flex items-center justify-between">
                            <span>Nomor Telepon / WA</span>
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        </dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">
                            @if($user->phone)
                                <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $user->phone) }}" target="_blank" rel="noopener noreferrer" class="text-sky-600 dark:text-sky-400 hover:underline">
                                    {{ $user->phone }}
                                </a>
                            @else
                                <span class="text-slate-400 italic">Belum diisi</span>
                            @endif
                        </dd>
                    </div>

                    {{-- Role --}}
                    <div class="p-3.5 rounded-xl bg-slate-50/70 dark:bg-slate-900/30 border border-slate-100 dark:border-slate-700/50">
                        <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 flex items-center justify-between">
                            <span>Peran Akun</span>
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        </dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">
                            {{ $user->role->name ?? '-' }}
                        </dd>
                    </div>
                </div>
            </div>

            {{-- Notice Box --}}
            <div class="mt-5 p-3 rounded-xl bg-sky-50/60 dark:bg-sky-950/30 border border-sky-100 dark:border-sky-900/50 flex items-start gap-2.5 text-xs text-sky-800 dark:text-sky-300">
                <svg class="w-4 h-4 text-sky-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="leading-relaxed text-[11px]">
                    Data identitas akun dikelola secara terpusat oleh Administrator. Hubungi admin jika terdapat pembaruan data.
                </p>
            </div>
        </div>

        {{-- ── 2. KANAN ATAS (Top-Right): KARTU CAKUPAN WILAYAH & HAK AKSES ── --}}
        <div class="bg-white dark:bg-slate-800 p-5 sm:p-7 flex flex-col justify-between">
            <div>
                {{-- Header --}}
                <div class="flex items-center justify-between pb-4 mb-5 border-b border-slate-100 dark:border-slate-700/70">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-800 dark:text-slate-100 leading-tight">Cakupan Wilayah &amp; Hak Akses</h2>
                            <p class="text-xs text-slate-400 dark:text-slate-500">Wilayah operasional POP dan kapabilitas peran akun</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    {{-- Role Description Banner --}}
                    <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-100 dark:border-slate-700/60 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 block">Role &amp; Deskripsi</span>
                            <span class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $user->role->name ?? '-' }}</span>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $user->role->description ?: 'Pengguna sistem operasional Whusnet.' }}</p>
                        </div>
                        <div class="shrink-0">
                            @if($user->hasFullAccess())
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-300 dark:border-indigo-800">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Super Access
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600">
                                    Role Terbatas
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Daftar Cabang POP yang Ditugaskan --}}
                    <div>
                        <div class="flex items-center justify-between mb-2.5">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Cabang POP Terhubung</span>
                            @if($user->hasFullAccess() || ($user->roleScopes->first()?->scope_type?->value === 'all_pop'))
                                <span class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Nasional (Semua Wilayah)
                                </span>
                            @else
                                <span class="text-[11px] font-medium text-slate-400">{{ $user->pops->count() }} POP Ditugaskan</span>
                            @endif
                        </div>

                        @if($user->pops->isNotEmpty())
                            @if($user->hasFullAccess() || ($user->roleScopes->first()?->scope_type?->value === 'all_pop'))
                                <div class="mb-3 p-3 rounded-xl bg-emerald-50/60 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/40 flex items-center gap-2.5 text-xs text-emerald-800 dark:text-emerald-300">
                                    <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>Akun ini memiliki hak akses global (Nasional) beserta penugasan cabang utama di bawah ini:</span>
                                </div>
                            @endif

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 max-h-52 overflow-y-auto custom-scrollbar p-1">
                                @foreach($user->pops as $pop)
                                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200/70 dark:border-slate-700/60 flex items-center justify-between gap-2">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-semibold text-xs text-slate-800 dark:text-slate-100 truncate">{{ $pop->name }}</span>
                                                <span class="px-1.5 py-0.2 rounded text-[10px] font-mono bg-sky-100 text-sky-700 dark:bg-sky-900/50 dark:text-sky-300 font-semibold">{{ $pop->code }}</span>
                                            </div>
                                            <p class="text-[11px] text-slate-400 dark:text-slate-500 truncate mt-0.5">
                                                {{ $pop->city ?: $pop->district ?: 'Cabang POP' }}
                                            </p>
                                        </div>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold {{ $pop->type === 'induk' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' }}">
                                            {{ ucfirst($pop->type ?? 'Cabang') }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @elseif($user->hasFullAccess() || ($user->roleScopes->first()?->scope_type?->value === 'all_pop'))
                            <div class="p-4 rounded-xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/40 flex items-start gap-3">
                                <div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="text-xs">
                                    <p class="font-bold text-emerald-900 dark:text-emerald-200">Akses Seluruh Wilayah (Nasional)</p>
                                    <p class="text-emerald-700/80 dark:text-emerald-400/80 mt-0.5 leading-relaxed">
                                        Akun ini memiliki hak akses tanpa batas wilayah pada seluruh master POP, distribusi jaringan, pelanggan, dan transaksi di semua cabang Whusnet.
                                    </p>
                                </div>
                            </div>
                        @else
                            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/30 border border-dashed border-slate-200 dark:border-slate-700 text-center text-xs text-slate-400">
                                <p>Belum ada penugasan Cabang POP spesifik.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ── 3. KIRI BAWAH (Bottom-Left): KARTU KEAMANAN & GANTI PASSWORD ── --}}
        <div class="bg-white dark:bg-slate-800 p-5 sm:p-7 flex flex-col justify-between"
             x-data="{
                showCurrent: false,
                showNew: false,
                showConfirm: false,
                newPassword: '',
                confirmPassword: '',
                submitting: false,
                get hasMinLength() { return this.newPassword.length >= 8; },
                get hasMixedCase() { return /[A-Z]/.test(this.newPassword) && /[a-z]/.test(this.newPassword); },
                get hasNumber() { return /[0-9]/.test(this.newPassword); },
                get hasSymbol() { return /[^A-Za-z0-9]/.test(this.newPassword); },
                get strengthScore() {
                    let s = 0;
                    if (this.newPassword.length >= 8) s++;
                    if (this.newPassword.length >= 12) s++;
                    if (/[A-Z]/.test(this.newPassword) && /[a-z]/.test(this.newPassword)) s++;
                    if (/[0-9]/.test(this.newPassword)) s++;
                    if (/[^A-Za-z0-9]/.test(this.newPassword)) s++;
                    return Math.min(4, s);
                },
                get strengthLabel() {
                    if (!this.newPassword) return '';
                    const labels = ['Sangat Lemah', 'Lemah', 'Sedang', 'Kuat', 'Sangat Kuat'];
                    return labels[this.strengthScore] || '';
                },
                get strengthColor() {
                    const colors = ['bg-rose-500', 'bg-amber-500', 'bg-yellow-500', 'bg-sky-500', 'bg-emerald-500'];
                    return colors[this.strengthScore] || 'bg-slate-200';
                },
                get isMatched() {
                    return this.confirmPassword.length > 0 && this.newPassword === this.confirmPassword;
                }
             }">

            <div>
                {{-- Header --}}
                <div class="flex items-center gap-2.5 pb-4 mb-5 border-b border-slate-100 dark:border-slate-700/70">
                    <div class="w-8 h-8 rounded-lg bg-rose-100 dark:bg-rose-900/40 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-800 dark:text-slate-100 leading-tight">Keamanan &amp; Ganti Password</h2>
                        <p class="text-xs text-slate-400 dark:text-slate-500">Perbarui kata sandi akun Anda secara berkala</p>
                    </div>
                </div>

                <form action="{{ route('profile.password') }}" method="POST" @submit="submitting = true" class="space-y-4">
                    @csrf
                    @method('PUT')

                    {{-- Password Lama --}}
                    <div>
                        <label for="current_password" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">
                            Password Lama <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <input id="current_password"
                                   name="current_password"
                                   :type="showCurrent ? 'text' : 'password'"
                                   placeholder="Masukkan password saat ini"
                                   required
                                   autocomplete="current-password"
                                   class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900/50 pl-3.5 pr-10 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20 transition-colors @error('current_password') border-rose-500 dark:border-rose-500 ring-2 ring-rose-500/20 @enderror">

                            <button type="button"
                                    @click="showCurrent = !showCurrent"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer focus:outline-none"
                                    tabindex="-1"
                                    title="Tampilkan / sembunyikan password">
                                <svg x-show="!showCurrent" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg x-show="showCurrent" x-cloak class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                </svg>
                            </button>
                        </div>
                        @error('current_password')
                            <p class="mt-1.5 text-xs text-rose-600 dark:text-rose-400 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    {{-- Password Baru --}}
                    <div>
                        <label for="password" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">
                            Password Baru <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <input id="password"
                                   name="password"
                                   :type="showNew ? 'text' : 'password'"
                                   x-model="newPassword"
                                   placeholder="Min. 8 karakter, huruf besar/kecil, angka & simbol"
                                   required
                                   autocomplete="new-password"
                                   class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900/50 pl-3.5 pr-10 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20 transition-colors @error('password') border-rose-500 dark:border-rose-500 ring-2 ring-rose-500/20 @enderror">

                            <button type="button"
                                    @click="showNew = !showNew"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer focus:outline-none"
                                    tabindex="-1"
                                    title="Tampilkan / sembunyikan password">
                                <svg x-show="!showNew" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg x-show="showNew" x-cloak class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                </svg>
                            </button>
                        </div>
                        @error('password')
                            <p class="mt-1.5 text-xs text-rose-600 dark:text-rose-400 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                {{ $message }}
                            </p>
                        @enderror

                        {{-- Strength Meter Bar --}}
                        <div x-show="newPassword.length > 0" x-transition.opacity class="mt-2 space-y-1">
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-400">Kekuatan Sandi:</span>
                                <span class="font-bold" :class="{
                                    'text-rose-500': strengthScore <= 1,
                                    'text-amber-500': strengthScore === 2,
                                    'text-sky-500': strengthScore === 3,
                                    'text-emerald-500': strengthScore === 4
                                }" x-text="strengthLabel"></span>
                            </div>
                            <div class="h-1.5 w-full bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden flex gap-1">
                                <div class="h-full rounded-full transition-all duration-300"
                                     :class="strengthColor"
                                     :style="`width: ${Math.max(15, (strengthScore / 4) * 100)}%`"></div>
                            </div>
                        </div>

                        {{-- Password Criteria Checklist --}}
                        <div class="mt-2.5 space-y-1 text-[11px] text-slate-500 dark:text-slate-400">
                            <div class="flex items-center gap-1.5" :class="hasMinLength ? 'text-emerald-600 dark:text-emerald-400' : ''">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                <span>Minimal 8 karakter</span>
                            </div>
                            <div class="flex items-center gap-1.5" :class="hasMixedCase ? 'text-emerald-600 dark:text-emerald-400' : ''">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                <span>Huruf besar dan huruf kecil</span>
                            </div>
                            <div class="flex items-center gap-1.5" :class="hasNumber ? 'text-emerald-600 dark:text-emerald-400' : ''">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                <span>Minimal 1 angka</span>
                            </div>
                            <div class="flex items-center gap-1.5" :class="hasSymbol ? 'text-emerald-600 dark:text-emerald-400' : ''">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                <span>Minimal 1 simbol (!@#$%dst)</span>
                            </div>
                        </div>
                    </div>

                    {{-- Konfirmasi Password Baru --}}
                    <div>
                        <label for="password_confirmation" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">
                            Konfirmasi Password Baru <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <input id="password_confirmation"
                                   name="password_confirmation"
                                   :type="showConfirm ? 'text' : 'password'"
                                   x-model="confirmPassword"
                                   placeholder="Ulangi password baru"
                                   required
                                   autocomplete="new-password"
                                   class="w-full rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900/50 pl-3.5 pr-10 py-2.5 text-sm text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20 transition-colors">

                            <button type="button"
                                    @click="showConfirm = !showConfirm"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer focus:outline-none"
                                    tabindex="-1"
                                    title="Tampilkan / sembunyikan password">
                                <svg x-show="!showConfirm" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg x-show="showConfirm" x-cloak class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                </svg>
                            </button>
                        </div>

                        {{-- Match Status Feedback --}}
                        <template x-if="confirmPassword.length > 0">
                            <p class="mt-1.5 text-xs flex items-center gap-1 font-medium"
                               :class="isMatched ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="isMatched ? 'M5 13l4 4L19 7' : 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'"/>
                                </svg>
                                <span x-text="isMatched ? 'Konfirmasi password cocok.' : 'Konfirmasi password belum sama.'"></span>
                            </p>
                        </template>
                    </div>

                    {{-- Submit Button --}}
                    <div class="pt-3 border-t border-slate-100 dark:border-slate-700/70">
                        <button type="submit"
                                :disabled="submitting"
                                class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-700 hover:to-indigo-700 text-white font-semibold text-sm shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-sky-500/50 transition-all duration-200 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed">
                            <template x-if="!submitting">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                                </svg>
                            </template>
                            <template x-if="submitting">
                                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </template>
                            <span x-text="submitting ? 'Menyimpan...' : 'Perbarui Password'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ── 4. KANAN BAWAH (Bottom-Right): KARTU AKTIVITAS TERAKHIR & PERANGKAT ── --}}
        <div class="bg-white dark:bg-slate-800 p-5 sm:p-7 flex flex-col justify-between space-y-6">
            {{-- Aktivitas Terakhir --}}
            <div class="space-y-4">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-700/70">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-800 dark:text-slate-100 leading-tight">Aktivitas Terakhir</h2>
                            <p class="text-xs text-slate-400 dark:text-slate-500">Catatan riwayat aksi terkini akun Anda</p>
                        </div>
                    </div>

                    {{-- Tombol "Lihat Semua" cuma buat Owner (2026-09-02) — halaman
                         profil ini dilihat SEMUA role, bukan cuma yang punya
                         audit_logs.view. Riwayat lengkap user lain lewat Audit Log
                         itu data sensitif lintas akun, jadi dibatasi role, bukan
                         sekadar permission. --}}
                    @if(auth()->user()->hasRole('owner'))
                        <a href="{{ route('audit-logs.index', ['user_id' => $user->id]) }}" class="text-xs font-semibold text-sky-600 dark:text-sky-400 hover:underline">
                            Lihat Semua
                        </a>
                    @endif
                </div>

                @if(isset($recentActivities) && $recentActivities->isNotEmpty())
                    <div class="space-y-2.5">
                        @foreach($recentActivities as $log)
                            <div class="p-3 rounded-xl bg-slate-50/80 dark:bg-slate-900/40 border border-slate-100 dark:border-slate-700/50 flex items-start justify-between gap-3 text-xs">
                                <div class="min-w-0 space-y-0.5">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $log->module }}</span>
                                        <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-mono uppercase font-semibold {{ match($log->action) {
                                            'create' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
                                            'update' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
                                            'delete' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
                                            default => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
                                        } }}">
                                            {{ $log->action }}
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-slate-400 dark:text-slate-500">
                                        {{ $log->auditable_type ? class_basename($log->auditable_type) . ' #' . $log->auditable_id : 'Aksi Sistem' }}
                                    </p>
                                </div>
                                <span class="text-[11px] text-slate-400 dark:text-slate-500 shrink-0">
                                    {{ \App\Support\IndonesianDate::dateTime($log->created_at) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-6 rounded-xl bg-slate-50/60 dark:bg-slate-900/30 border border-dashed border-slate-200 dark:border-slate-700 text-center text-xs text-slate-400">
                        <p>Belum ada catatan aktivitas terbaru.</p>
                    </div>
                @endif
            </div>
        </div>

    </div>

</div>
@endsection
