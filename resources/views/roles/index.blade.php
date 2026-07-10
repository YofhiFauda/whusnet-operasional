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
@endphp

{{-- Page Header + Tombol Tambah --}}
<div class="mb-4 flex items-center justify-between gap-4">
    <div>
        <p class="text-sm text-text-muted">
            Kelola master data jabatan (Role) dan hak akses (Permission) pengguna sistem.
        </p>
    </div>
    @if(auth()->user()->hasPermission('roles.create') && $canCreateRole)
        <x-ui.button
            type="button"
            variant="primary"
            onclick="openCreateModal()"
            aria-label="Tambah role baru"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah Role
        </x-ui.button>
    @endif
</div>

{{-- Tabel Role --}}
<div class="table-wrapper">
    <table class="table">
        <thead>
            <tr>
                <th>Nama Role</th>
                <th>Kode</th>
                <th>Deskripsi</th>
                <th class="text-center">Users</th>
                <th class="text-center">Permissions</th>
                <th class="text-center">Tipe</th>
                <th class="text-right pr-4">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($roles as $role)
            @php $canManage = $role->canBeManagedBy(auth()->user()); @endphp
            <tr class="{{ !$canManage && !$isOwner ? 'opacity-60' : '' }}">

                {{-- Nama Role --}}
                <td class="font-medium text-text-main">
                    <div class="flex items-center gap-2">
                        <span>{{ $role->name }}</span>
                        <!-- @if($role->isOwner())
                            <x-ui.badge variant="warning">
                                <svg class="h-3 w-3 mr-0.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                                Owner
                            </x-ui.badge>
                        @elseif($role->is_system)
                            <x-ui.badge variant="neutral">Sistem</x-ui.badge>
                        @endif -->
                    </div>
                </td>

                {{-- Kode --}}
                <td>
                    <span class="data-text text-xs text-text-secondary">{{ $role->code }}</span>
                </td>

                {{-- Deskripsi --}}
                <td class="text-sm text-text-secondary max-w-xs truncate">
                    {{ $role->description ?: '—' }}
                </td>

                {{-- Users count --}}
                <td class="text-center">
                    <span class="data-text text-sm font-medium text-text-main">{{ $role->users_count }}</span>
                </td>

                {{-- Permissions count --}}
                <td class="text-center">
                    @if($role->isOwner())
                        <span class="text-xs text-warning font-medium">Semua</span>
                    @else
                        <span class="data-text text-sm font-medium text-text-main">{{ $role->permissions_count }}</span>
                    @endif
                </td>

                {{-- Tipe --}}
                <td class="text-center">
                    @if($role->isProtected())
                        <x-ui.badge variant="error">
                            <svg class="h-3 w-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            Dilindungi
                        </x-ui.badge>
                    @elseif($role->is_system)
                        <x-ui.badge variant="info">Sistem</x-ui.badge>
                    @else
                        <x-ui.badge variant="neutral">Custom</x-ui.badge>
                    @endif
                </td>

                {{-- Aksi --}}
                <td class="text-right pr-4">
                    <div class="flex items-center justify-end gap-3">

                        {{-- Matrix Permission --}}
                        @if($role->isOwner())
                            <span class="text-xs text-text-muted italic">Full Access</span>
                        @elseif($canManage && auth()->user()->hasPermission('roles.update'))
                            <a href="{{ route('roles.matrix', $role) }}"
                               class="text-sm font-medium text-primary hover:text-primary-hover transition-colors"
                               title="Atur permission role {{ $role->name }}">
                                Matrix
                            </a>
                        @endif

                        {{-- Edit --}}
                        @if($canManage && auth()->user()->hasPermission('roles.update'))
                            <button
                                type="button"
                                onclick="openEditModal({{ $role->id }}, '{{ addslashes($role->name) }}', '{{ addslashes($role->code) }}', '{{ addslashes($role->description ?? '') }}', {{ $role->is_system ? 'true' : 'false' }})"
                                class="text-sm font-medium text-success hover:opacity-80 transition-opacity"
                                title="Edit role {{ $role->name }}"
                            >
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
                                <button type="submit"
                                        class="text-sm font-medium text-error hover:opacity-80 transition-opacity"
                                        title="Hapus role {{ $role->name }}">
                                    Hapus
                                </button>
                            </form>
                        @endif

                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="py-16 text-center">
                    <div class="flex flex-col items-center gap-3 text-text-muted">
                        <svg class="h-10 w-10 text-text-disabled" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <p class="text-sm font-medium">Belum ada role terdaftar</p>
                        @if($canCreateRole)
                            <x-ui.button type="button" variant="secondary" onclick="openCreateModal()">
                                Tambah Role Pertama
                            </x-ui.button>
                        @endif
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- ================================================ --}}
{{-- MODAL: Tambah / Edit Role                        --}}
{{-- ================================================ --}}
<x-ui.modal name="role-modal" title="Form Role" maxWidth="md">
    <form id="roleForm" method="POST" action="{{ route('roles.store') }}" novalidate>
        @csrf
        <input type="hidden" name="_method" id="formMethod" value="POST">

        <div class="space-y-4">

            {{-- Nama Role --}}
            <div class="flex flex-col gap-1.5">
                <label for="roleName" class="text-sm font-medium text-text-main">
                    Nama Role
                    <span class="text-error ml-0.5" aria-hidden="true">*</span>
                </label>
                <x-ui.input
                    type="text"
                    name="name"
                    id="roleName"
                    required
                    placeholder="Contoh: Supervisor"
                    autocomplete="off"
                    :error="$errors->has('name')"
                />
                @error('name')
                    <p class="text-xs font-medium text-error" role="alert">{{ $message }}</p>
                @enderror
                <p class="text-xs text-text-muted">Nama yang tampil untuk user dan dalam laporan.</p>
            </div>

            {{-- Kode Role --}}
            <div class="flex flex-col gap-1.5">
                <label for="roleCode" class="text-sm font-medium text-text-main">
                    Kode Role
                    <span class="text-error ml-0.5" aria-hidden="true">*</span>
                </label>
                <x-ui.input
                    type="text"
                    name="code"
                    id="roleCode"
                    required
                    placeholder="Contoh: supervisor"
                    pattern="[a-z0-9_]+"
                    autocomplete="off"
                    :error="$errors->has('code')"
                />
                @error('code')
                    <p class="text-xs font-medium text-error" role="alert">{{ $message }}</p>
                @enderror
                <p class="text-xs text-text-muted" id="codeHint">
                    Gunakan huruf kecil, angka, dan underscore saja. Contoh: <span class="data-text">pop_admin</span>
                </p>
            </div>

            {{-- Deskripsi --}}
            <div class="flex flex-col gap-1.5">
                <label for="roleDescription" class="text-sm font-medium text-text-main">
                    Deskripsi
                    <span class="text-xs font-normal text-text-muted ml-1">(opsional)</span>
                </label>
                <x-ui.textarea
                    name="description"
                    id="roleDescription"
                    rows="3"
                    placeholder="Jelaskan fungsi dan tanggung jawab role ini..."
                    :error="$errors->has('description')"
                />
                @error('description')
                    <p class="text-xs font-medium text-error" role="alert">{{ $message }}</p>
                @enderror
            </div>

        </div>

        <x-slot name="footer">
            <x-ui.button type="button" variant="secondary" @click="show = false">
                Batal
            </x-ui.button>
            <x-ui.button type="submit" variant="primary" id="modalSubmitBtn">
                Simpan Role
            </x-ui.button>
        </x-slot>
    </form>
</x-ui.modal>

@section('scripts')
<script>
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
        Toast.info(
            'Akses Terbatas',
            'Anda hanya dapat mengelola role: ' + roles.join(', ') + '.',
            6000
        );
    });
    @endif
    function openCreateModal() {
        const form = document.getElementById('roleForm');
        form.action = '{{ route("roles.store") }}';
        document.getElementById('formMethod').value = 'POST';

        // Reset semua field
        document.getElementById('roleName').value = '';
        document.getElementById('roleCode').value = '';
        document.getElementById('roleCode').disabled = false;
        document.getElementById('roleDescription').value = '';

        // Reset hint & error states
        document.getElementById('codeHint').textContent =
            'Gunakan huruf kecil, angka, dan underscore saja. Contoh: pop_admin';
        resetFieldErrors();

        // Update judul modal
        document.querySelector('[id="modal-title"]').textContent = 'Tambah Role';
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

        // Kunci field code untuk role sistem
        const codeInput = document.getElementById('roleCode');
        const codeHint  = document.getElementById('codeHint');
        if (isSystem) {
            codeInput.disabled = true;
            codeHint.textContent = 'Kode role sistem tidak dapat diubah.';
        } else {
            codeInput.disabled = false;
            codeHint.textContent = 'Gunakan huruf kecil, angka, dan underscore saja. Contoh: pop_admin';
        }

        resetFieldErrors();

        document.querySelector('[id="modal-title"]').textContent = 'Edit Role';
        document.getElementById('modalSubmitBtn').textContent = 'Perbarui Role';

        window.dispatchEvent(new CustomEvent('open-modal', { detail: 'role-modal' }));
    }

    // ---- Konfirmasi hapus role via Komponen Kustom Dialog Alert / Confirm ----
    function confirmRoleDestroy(event, form, roleName, usersCount) {
        event.preventDefault();
        if (usersCount > 0) {
            window.Alert(
                'Tidak Dapat Menghapus Role',
                `Role "${roleName}" tidak dapat dihapus.\nMasih ada ${usersCount} user yang menggunakan role ini.\nPindahkan user ke role lain terlebih dahulu.`,
                'error'
            );
            return false;
        }
        window.confirmDelete(
            `Apakah Anda yakin ingin menghapus role "${roleName}"?\n\nTindakan ini tidak dapat dibatalkan.`,
            form
        );
        return false;
    }

    // ---- Reset state error field ----
    function resetFieldErrors() {
        document.querySelectorAll('#roleForm [role="alert"]').forEach(el => el.remove());
        document.querySelectorAll('#roleForm .border-error').forEach(el => {
            el.classList.remove('border-error');
        });
    }

    // ---- Auto-generate kode dari nama (hanya saat tambah baru) ----
    document.getElementById('roleName')?.addEventListener('input', function () {
        const codeInput  = document.getElementById('roleCode');
        const formMethod = document.getElementById('formMethod').value;

        // Hanya auto-generate saat tambah baru dan field belum pernah diedit
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
        // Tandai sebagai sudah diedit manual
        if (document.getElementById('formMethod').value === 'POST') {
            this.dataset.manualEdit = '1';
        }
    });
</script>
@endsection

@endsection
