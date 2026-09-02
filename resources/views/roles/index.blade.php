@extends('layouts.app')

@section('title', 'Manajemen Role & Permission')
@section('page_title', 'Manajemen Role & Permission')

@section('content')

@php
    $userRoleCode     = auth()->user()->role?->code;
    $managementScope  = config('rbac.role_management_scope', []);
    $canManageRoles   = $managementScope[$userRoleCode] ?? [];
    $isOwner          = $userRoleCode === 'owner';
    $canCreateRole    = \App\Models\Role::canBeCreatedBy(auth()->user());

    // Stat Summary
    $totalRoles     = $roles->count();
    $systemRoles    = $roles->where('is_system', true)->count();
    $customRoles    = $roles->where('is_system', false)->count();
    $totalUsers     = $roles->sum('users_count');
@endphp

<div x-data="rolesManagement()" class="space-y-5">

    {{-- ================================================ --}}
    {{-- 1. PAGE HEADER (NAKED — Tanpa Card Wrapper)      --}}
    {{-- ================================================ --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">
                <span>Pengaturan</span>
                <svg class="h-3 w-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                <span class="text-slate-700 dark:text-slate-200">Pengguna & RBAC</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                Manajemen Role & Permission
            </h1>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                Kelola master data jabatan (Role) dan matriks hak akses (Permission) pengguna sistem.
            </p>
        </div>

        @if(auth()->user()->hasPermission('roles.create') && $canCreateRole)
            <div class="flex-shrink-0">
                <button
                    type="button"
                    onclick="openCreateModal()"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-sky-600 hover:bg-sky-700 active:bg-sky-800 dark:bg-sky-500 dark:hover:bg-sky-600 rounded-lg shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                >
                    <svg class="h-4 w-4 stroke-[2.5]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    Tambah Role
                </button>
            </div>
        @endif
    </div>

    {{-- ================================================ --}}
    {{-- 2. SUMMARY STRIP (Flat Bar dengan Divider)       --}}
    {{-- ================================================ --}}
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700/80 rounded-lg overflow-hidden shadow-xs">
        <div class="grid grid-cols-2 md:grid-cols-4 divide-y md:divide-y-0 md:divide-x divide-slate-200 dark:divide-slate-700/80">
            <div class="p-3.5 sm:p-4 flex flex-col gap-0.5">
                <span class="text-[10px] font-bold tracking-wider uppercase text-slate-400 dark:text-slate-400">
                    TOTAL ROLE
                </span>
                <div class="flex items-baseline gap-2">
                    <span class="font-mono text-xl sm:text-2xl font-bold text-slate-900 dark:text-slate-100 tabular-nums">
                        {{ $totalRoles }}
                    </span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">jabatan</span>
                </div>
            </div>

            <div class="p-3.5 sm:p-4 flex flex-col gap-0.5">
                <span class="text-[10px] font-bold tracking-wider uppercase text-slate-400 dark:text-slate-400">
                    ROLE SISTEM
                </span>
                <div class="flex items-baseline gap-2">
                    <span class="font-mono text-xl sm:text-2xl font-bold text-sky-600 dark:text-sky-400 tabular-nums">
                        {{ $systemRoles }}
                    </span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">bawaan</span>
                </div>
            </div>

            <div class="p-3.5 sm:p-4 flex flex-col gap-0.5">
                <span class="text-[10px] font-bold tracking-wider uppercase text-slate-400 dark:text-slate-400">
                    ROLE CUSTOM
                </span>
                <div class="flex items-baseline gap-2">
                    <span class="font-mono text-xl sm:text-2xl font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">
                        {{ $customRoles }}
                    </span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">tambahan</span>
                </div>
            </div>

            <div class="p-3.5 sm:p-4 flex flex-col gap-0.5">
                <span class="text-[10px] font-bold tracking-wider uppercase text-slate-400 dark:text-slate-400">
                    TOTAL PENGGUNA
                </span>
                <div class="flex items-baseline gap-2">
                    <span class="font-mono text-xl sm:text-2xl font-bold text-slate-900 dark:text-slate-100 tabular-nums">
                        {{ $totalUsers }}
                    </span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">user aktif</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ================================================ --}}
    {{-- 3. FILTER BAR (NAKED — Tanpa Card Wrapper)       --}}
    {{-- ================================================ --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 flex-1 max-w-xl">
            {{-- Search Input (Pill shape) --}}
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 dark:text-slate-400">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <input
                    type="text"
                    x-model="searchQuery"
                    placeholder="Cari nama role atau kode..."
                    class="w-full pl-9 pr-8 py-1.5 text-sm bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/80 rounded-full text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-all"
                >
                <button
                    x-show="searchQuery.length > 0"
                    @click="searchQuery = ''"
                    type="button"
                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-300"
                >
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Type Select Filter --}}
            <div class="w-full sm:w-44">
                <select
                    x-model="typeFilter"
                    class="w-full px-3 py-1.5 text-sm bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/80 rounded-lg text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-all cursor-pointer"
                >
                    <option value="all">Semua Tipe</option>
                    <option value="system">Sistem</option>
                    <option value="custom">Custom</option>
                    <option value="protected">Dilindungi</option>
                </select>
            </div>
        </div>

        <div class="text-xs text-slate-500 dark:text-slate-400 self-end sm:self-center">
            Menampilkan <span class="font-mono font-semibold text-slate-700 dark:text-slate-200" x-text="filteredRolesCount"></span> dari <span class="font-mono font-semibold text-slate-700 dark:text-slate-200">{{ $totalRoles }}</span> role
        </div>
    </div>

    {{-- ================================================ --}}
    {{-- 4. TABLE PANEL (1 CARD BUDGET)                   --}}
    {{-- ================================================ --}}
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700/80 rounded-lg shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm border-collapse">
                <thead>
                    <tr class="bg-slate-50/80 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-700/80 text-slate-500 dark:text-slate-400 font-semibold text-xs tracking-wide">
                        <th scope="col" class="py-3 px-4">Nama Role</th>
                        <th scope="col" class="py-3 px-4">Kode</th>
                        <th scope="col" class="py-3 px-4">Deskripsi</th>
                        <th scope="col" class="py-3 px-4 text-center">Users</th>
                        <th scope="col" class="py-3 px-4 text-center">Permissions</th>
                        <th scope="col" class="py-3 px-4 text-center">Tipe</th>
                        <th scope="col" class="py-3 px-4 text-right pr-6">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200/70 dark:divide-slate-700/60">
                    @forelse($roles as $role)
                        @php $canManage = $role->canBeManagedBy(auth()->user()); @endphp
                        <tr
                            x-show="matchesFilter('{{ strtolower(addslashes($role->name)) }}', '{{ strtolower(addslashes($role->code)) }}', {{ $role->is_system ? 'true' : 'false' }}, {{ $role->isProtected() ? 'true' : 'false' }})"
                            class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/50 {{ !$canManage && !$isOwner ? 'opacity-60' : '' }}"
                        >
                            {{-- Nama Role --}}
                            <td class="py-3.5 px-4 font-semibold text-slate-900 dark:text-slate-100">
                                <div class="flex items-center gap-2">
                                    <span>{{ $role->name }}</span>
                                    @if($role->isOwner())
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400 border border-amber-200/60 dark:border-amber-800/60">
                                            <svg class="h-3 w-3 fill-amber-500" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                            </svg>
                                            Owner
                                        </span>
                                    @endif
                                </div>
                            </td>

                            {{-- Kode --}}
                            <td class="py-3.5 px-4">
                                <span class="font-mono text-xs text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded border border-slate-200 dark:border-slate-700/60">
                                    {{ $role->code }}
                                </span>
                            </td>

                            {{-- Deskripsi --}}
                            <td class="py-3.5 px-4 text-slate-500 dark:text-slate-400 max-w-xs truncate text-xs sm:text-sm">
                                {{ $role->description ?: '—' }}
                            </td>

                            {{-- Users count --}}
                            <td class="py-3.5 px-4 text-center">
                                <span class="font-mono text-sm font-semibold text-slate-900 dark:text-slate-100">
                                    {{ $role->users_count }}
                                </span>
                            </td>

                            {{-- Permissions count --}}
                            <td class="py-3.5 px-4 text-center">
                                @if($role->isOwner())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400">
                                        Semua
                                    </span>
                                @else
                                    <span class="font-mono text-sm font-semibold text-slate-900 dark:text-slate-100">
                                        {{ $role->permissions_count }}
                                    </span>
                                @endif
                            </td>

                            {{-- Tipe --}}
                            <td class="py-3.5 px-4 text-center">
                                @if($role->isProtected())
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 border border-rose-200/60 dark:border-rose-800/60">
                                        <svg class="h-3 w-3 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                                        </svg>
                                        Dilindungi
                                    </span>
                                @elseif($role->is_system)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-400 border border-sky-200/60 dark:border-sky-800/60">
                                        Sistem
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                        Custom
                                    </span>
                                @endif
                            </td>

                            {{-- Aksi --}}
                            <td class="py-3.5 px-4 text-right pr-6">
                                <div class="flex items-center justify-end gap-2.5">
                                    {{-- Matrix Permission --}}
                                    @if($role->isOwner())
                                        <span class="text-xs text-slate-400 dark:text-slate-400 italic">Full Access</span>
                                    @elseif($canManage && auth()->user()->hasPermission('roles.update'))
                                        <a href="{{ route('roles.matrix', $role) }}"
                                           class="inline-flex items-center gap-1 text-xs font-semibold text-sky-600 dark:text-sky-400 hover:text-sky-700 dark:hover:text-sky-300 hover:underline underline-offset-2 transition-colors"
                                           title="Atur permission role {{ $role->name }}">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                            </svg>
                                            Matrix
                                        </a>
                                    @endif

                                    {{-- Edit --}}
                                    @if($canManage && auth()->user()->hasPermission('roles.update'))
                                        <button
                                            type="button"
                                            onclick="openEditModal({{ $role->id }}, '{{ addslashes($role->name) }}', '{{ addslashes($role->code) }}', '{{ addslashes($role->description ?? '') }}', {{ $role->is_system ? 'true' : 'false' }})"
                                            class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 hover:underline underline-offset-2 transition-colors"
                                            title="Edit role {{ $role->name }}"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            Edit
                                        </button>
                                    @endif

                                    {{-- Hapus --}}
                                    @if($canManage && auth()->user()->hasPermission('roles.delete') && !$role->isProtected())
                                        <form
                                            action="{{ route('roles.destroy', $role) }}"
                                            method="POST"
                                            class="inline"
                                            onsubmit="return confirmRoleDestroy(event, this, '{{ addslashes($role->name) }}', {{ $role->users_count }})"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="inline-flex items-center gap-1 text-xs font-semibold text-rose-600 dark:text-rose-400 hover:text-rose-700 dark:hover:text-rose-300 hover:underline underline-offset-2 transition-colors"
                                                title="Hapus role {{ $role->name }}"
                                            >
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                                Hapus
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center">
                                <div class="flex flex-col items-center justify-center gap-2 text-slate-400 dark:text-slate-400">
                                    <svg class="h-10 w-10 stroke-[1.25]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 00-3.741-.479 3 3 0 00-3.74 3.741m-6-6.75a3 3 0 013-3h.008a3 3 0 013 3v.008a3 3 0 01-3 3H6a3 3 0 01-3-3v-.008zm0-9.75a3 3 0 013-3h.008a3 3 0 013 3v.008a3 3 0 01-3 3H6a3 3 0 01-3-3V2.25z"/>
                                    </svg>
                                    <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Belum ada role terdaftar</p>
                                    @if($canCreateRole)
                                        <button
                                            type="button"
                                            onclick="openCreateModal()"
                                            class="mt-1 text-xs font-semibold text-sky-600 dark:text-sky-400 hover:underline"
                                        >
                                            + Tambah Role Pertama
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse

                    {{-- Empty Search Result --}}
                    <tr x-show="filteredRolesCount === 0 && {{ $totalRoles }} > 0" style="display: none;">
                        <td colspan="7" class="py-12 text-center">
                            <div class="flex flex-col items-center justify-center gap-2 text-slate-400 dark:text-slate-400">
                                <svg class="h-9 w-9 stroke-[1.5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                                </svg>
                                <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Tidak ada role yang cocok</p>
                                <p class="text-xs text-slate-400 dark:text-slate-400">Coba ubah kata kunci pencarian atau filter tipe.</p>
                                <button
                                    @click="searchQuery = ''; typeFilter = 'all'"
                                    type="button"
                                    class="mt-1 text-xs font-semibold text-sky-600 dark:text-sky-400 hover:underline"
                                >
                                    Reset Filter
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

{{-- ================================================ --}}
{{-- MODAL FORM: Tambah / Edit Role                   --}}
{{-- ================================================ --}}
<x-ui.modal name="role-modal" title="Form Role" maxWidth="md">
    <form id="roleForm" method="POST" action="{{ route('roles.store') }}" novalidate>
        @csrf
        <input type="hidden" name="_method" id="formMethod" value="POST">

        <div class="space-y-4">

            {{-- Nama Role --}}
            <div class="flex flex-col gap-1.5">
                <label for="roleName" class="text-xs sm:text-sm font-semibold text-slate-900 dark:text-slate-100">
                    Nama Role
                    <span class="text-rose-500 ml-0.5" aria-hidden="true">*</span>
                </label>
                <x-ui.input
                    type="text"
                    name="name"
                    id="roleName"
                    required
                    placeholder="Contoh: Supervisor POP"
                    autocomplete="off"
                    :error="$errors->has('name')"
                />
                @error('name')
                    <p class="text-xs font-medium text-rose-500 dark:text-rose-400" role="alert">{{ $message }}</p>
                @enderror
                <p class="text-[11px] text-slate-500 dark:text-slate-400">Nama resmi yang tampil di interface pengguna.</p>
            </div>

            {{-- Kode Role --}}
            <div class="flex flex-col gap-1.5">
                <label for="roleCode" class="text-xs sm:text-sm font-semibold text-slate-900 dark:text-slate-100">
                    Kode Role
                    <span class="text-rose-500 ml-0.5" aria-hidden="true">*</span>
                </label>
                <x-ui.input
                    type="text"
                    name="code"
                    id="roleCode"
                    required
                    placeholder="Contoh: supervisor_pop"
                    pattern="[a-z0-9_]+"
                    autocomplete="off"
                    :error="$errors->has('code')"
                />
                @error('code')
                    <p class="text-xs font-medium text-rose-500 dark:text-rose-400" role="alert">{{ $message }}</p>
                @enderror
                <p class="text-[11px] text-slate-500 dark:text-slate-400" id="codeHint">
                    Huruf kecil, angka, dan underscore. Contoh: <span class="font-mono text-slate-700 dark:text-slate-300">pop_admin</span>
                </p>
            </div>

            {{-- Deskripsi --}}
            <div class="flex flex-col gap-1.5">
                <label for="roleDescription" class="text-xs sm:text-sm font-semibold text-slate-900 dark:text-slate-100">
                    Deskripsi
                    <span class="text-xs font-normal text-slate-400 dark:text-slate-400 ml-1">(opsional)</span>
                </label>
                <x-ui.textarea
                    name="description"
                    id="roleDescription"
                    rows="3"
                    placeholder="Jelaskan fungsi dan hak tanggung jawab role ini..."
                    :error="$errors->has('description')"
                />
                @error('description')
                    <p class="text-xs font-medium text-rose-500 dark:text-rose-400" role="alert">{{ $message }}</p>
                @enderror
            </div>

        </div>

        <x-slot name="footer">
            <div class="flex items-center justify-end gap-2">
                <x-ui.button type="button" variant="secondary" @click="show = false">
                    Batal
                </x-ui.button>
                <x-ui.button type="submit" variant="primary" id="modalSubmitBtn">
                    Simpan Role
                </x-ui.button>
            </div>
        </x-slot>
    </form>
</x-ui.modal>

@section('scripts')
<script>
    function rolesManagement() {
        return {
            searchQuery: '',
            typeFilter: 'all',
            filteredRolesCount: {{ $totalRoles }},

            matchesFilter(name, code, isSystem, isProtected) {
                const q = this.searchQuery.toLowerCase().trim();
                const matchesSearch = !q || name.includes(q) || code.includes(q);

                let matchesType = true;
                if (this.typeFilter === 'system') {
                    matchesType = isSystem;
                } else if (this.typeFilter === 'custom') {
                    matchesType = !isSystem;
                } else if (this.typeFilter === 'protected') {
                    matchesType = isProtected;
                }

                const result = matchesSearch && matchesType;
                
                this.$nextTick(() => {
                    this.updateCount();
                });

                return result;
            },

            updateCount() {
                const visibleRows = document.querySelectorAll('tbody tr[x-show]:not([style*="display: none"])');
                this.filteredRolesCount = visibleRows.length;
            }
        }
    }

    // ---- Toast untuk info scope akses (jika role non-Owner dengan scope terbatas) ----
    @if(!$isOwner && !empty($canManageRoles))
    @php
        $scopeRoleLabels = array_values(array_map(
            function($r) { return ucfirst(str_replace('_', ' ', $r)); },
            $canManageRoles
        ));
    @endphp
    document.addEventListener('DOMContentLoaded', () => {
        const roles = @json($scopeRoleLabels);
        if (window.Toast) {
            Toast.info(
                'Akses Terbatas',
                'Anda hanya dapat mengelola role: ' + roles.join(', ') + '.',
                6000
            );
        }
    });
    @endif

    function openCreateModal() {
        const form = document.getElementById('roleForm');
        form.action = '{{ route("roles.store") }}';
        document.getElementById('formMethod').value = 'POST';

        document.getElementById('roleName').value = '';
        document.getElementById('roleCode').value = '';
        document.getElementById('roleCode').disabled = false;
        document.getElementById('roleDescription').value = '';

        document.getElementById('codeHint').innerHTML =
            'Huruf kecil, angka, dan underscore. Contoh: <span class="font-mono text-slate-700 dark:text-slate-300">pop_admin</span>';
        resetFieldErrors();

        const modalTitle = document.querySelector('[id="modal-title"]');
        if (modalTitle) modalTitle.textContent = 'Tambah Role';
        document.getElementById('modalSubmitBtn').textContent = 'Simpan Role';

        window.dispatchEvent(new CustomEvent('open-modal', { detail: 'role-modal' }));
    }

    // ---- Buka modal untuk EDIT role ----
    function openEditModal(id, name, code, description, isSystem) {
        const form = document.getElementById('roleForm');
        form.action = '{{ url("roles") }}/' + id;
        document.getElementById('formMethod').value = 'PUT';

        document.getElementById('roleName').value = name;
        document.getElementById('roleCode').value = code;
        document.getElementById('roleDescription').value = description;

        const codeInput = document.getElementById('roleCode');
        const codeHint  = document.getElementById('codeHint');
        if (isSystem) {
            codeInput.disabled = true;
            codeHint.textContent = 'Kode role sistem tidak dapat diubah.';
        } else {
            codeInput.disabled = false;
            codeHint.innerHTML = 'Huruf kecil, angka, dan underscore. Contoh: <span class="font-mono text-slate-700 dark:text-slate-300">pop_admin</span>';
        }

        resetFieldErrors();

        const modalTitle = document.querySelector('[id="modal-title"]');
        if (modalTitle) modalTitle.textContent = 'Edit Role';
        document.getElementById('modalSubmitBtn').textContent = 'Perbarui Role';

        window.dispatchEvent(new CustomEvent('open-modal', { detail: 'role-modal' }));
    }

    // ---- Konfirmasi hapus role via Komponen Kustom Dialog Alert / Confirm ----
    function confirmRoleDestroy(event, form, roleName, usersCount) {
        event.preventDefault();
        if (usersCount > 0) {
            if (window.Alert) {
                window.Alert(
                    'Tidak Dapat Menghapus Role',
                    `Role "${roleName}" tidak dapat dihapus.\nMasih ada ${usersCount} user yang menggunakan role ini.\nPindahkan user ke role lain terlebih dahulu.`,
                    'error'
                );
            } else {
                alert(`Role "${roleName}" tidak dapat dihapus karena masih digunakan oleh ${usersCount} user.`);
            }
            return false;
        }
        if (window.confirmDelete) {
            window.confirmDelete(
                `Apakah Anda yakin ingin menghapus role "${roleName}"?\n\nTindakan ini tidak dapat dibatalkan.`,
                form
            );
        } else {
            if (confirm(`Apakah Anda yakin ingin menghapus role "${roleName}"?`)) {
                form.submit();
            }
        }
        return false;
    }

    // ---- Reset state error field ----
    function resetFieldErrors() {
        document.querySelectorAll('#roleForm [role="alert"]').forEach(el => el.remove());
        document.querySelectorAll('#roleForm .border-rose-500').forEach(el => {
            el.classList.remove('border-rose-500');
        });
    }

    // ---- Auto-generate kode dari nama (hanya saat tambah baru) ----
    document.getElementById('roleName')?.addEventListener('input', function () {
        const codeInput  = document.getElementById('roleCode');
        const formMethod = document.getElementById('formMethod').value;

        if (formMethod === 'POST' && !codeInput.dataset.manualEdit) {
            const generated = this.value
                .toLowerCase()
                .trim()
                .replace(/\s+/g, '_')
                .replace(/[^a-z0-9_]/g, '')
                .substring(0, 50);
            codeInput.value = generated;
        }
    });

    document.getElementById('roleCode')?.addEventListener('input', function () {
        if (document.getElementById('formMethod').value === 'POST') {
            this.dataset.manualEdit = '1';
        }
    });
</script>
@endsection

@endsection
