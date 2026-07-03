@extends('layouts.app')

@section('title', 'Permission Matrix — ' . $role->name)
@section('page_title', 'Permission Matrix: ' . $role->name)

@section('content')

{{-- Breadcrumb & Info header --}}
<div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex items-center gap-2">
        <a href="{{ route('roles.index') }}"
           class="text-sm font-medium text-primary hover:text-primary-hover transition-colors flex items-center gap-1"
           aria-label="Kembali ke daftar role">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Daftar Role
        </a>
        <span class="text-text-disabled text-sm">/</span>
        <span class="text-sm text-text-muted">{{ $role->name }}</span>
    </div>

    <div class="flex items-center gap-2 text-sm text-text-secondary">
        <span>Role:</span>
        <span class="font-semibold text-text-main">{{ $role->name }}</span>
        @if($role->is_system)
            <x-ui.badge variant="info">Sistem</x-ui.badge>
        @else
            <x-ui.badge variant="neutral">Custom</x-ui.badge>
        @endif
    </div>
</div>

{{-- ================================================ --}}
{{-- FORM MATRIX PERMISSION                          --}}
{{-- ================================================ --}}
<form action="{{ route('roles.update', $role) }}" method="POST" id="matrixForm">
    @csrf
    @method('PUT')

    <x-ui.card padding="compact">

        {{-- Card Header --}}
        <x-slot name="header">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-md font-semibold text-text-main">Feature Tree Permission Matrix</h2>
                    <p class="mt-0.5 text-sm text-text-muted">
                        Centang aksi yang diizinkan untuk role
                        <strong class="text-text-secondary">{{ $role->name }}</strong>.
                        Perubahan berlaku untuk semua user dengan role ini.
                    </p>
                </div>

                {{-- Permission counter --}}
                <div class="flex items-center gap-3 text-sm">
                    <div class="text-center min-w-[64px] rounded-md border border-border bg-surface-muted px-3 py-1.5">
                        <div class="data-text text-lg font-semibold text-text-main leading-tight" id="permCount">
                            {{ count($rolePermissions) }}
                        </div>
                        <div class="text-xs text-text-muted">aktif</div>
                    </div>

                    {{-- Bulk action controls --}}
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <button type="button" onclick="selectAll()"
                                class="text-primary hover:text-primary-hover font-medium underline-offset-2 hover:underline transition-colors">
                            Pilih Semua
                        </button>
                        <span class="text-text-disabled">|</span>
                        <button type="button" onclick="deselectAll()"
                                class="text-text-muted hover:text-text-secondary font-medium underline-offset-2 hover:underline transition-colors">
                            Hapus Semua
                        </button>
                    </div>
                </div>
            </div>
        </x-slot>

        {{-- Feature Tree — negative margin untuk full-bleed mengimbangi padding compact (p-2 sm:p-3) --}}
        <div id="featureTree" class="-mx-2 -mb-2 sm:-mx-3 sm:-mb-3 border-t border-border">

            @forelse($features as $feature)

            {{-- Root Feature Row --}}
            <div class="feature-group border-b border-border last:border-b-0"
                 x-data="{ expanded: true }">

                {{-- Root header (clickable to toggle) --}}
                <div class="flex items-center justify-between px-4 py-3 cursor-pointer
                            bg-surface-muted hover:bg-border/30 transition-colors select-none"
                     @click="expanded = !expanded">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <svg class="h-4 w-4 text-text-muted transition-transform flex-shrink-0"
                             :class="expanded ? 'rotate-90' : ''"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                        <div class="min-w-0">
                            <span class="text-sm font-semibold text-text-main">{{ $feature->name }}</span>
                        </div>
                    </div>

                    {{-- Toggle semua di feature ini --}}
                    <div @click.stop class="flex items-center gap-1.5 ml-4 flex-shrink-0">
                        <label class="inline-flex items-center gap-1.5 cursor-pointer text-xs text-text-muted hover:text-text-secondary">
                            <input type="checkbox"
                                   class="feature-toggle rounded border-border text-primary focus:ring-2 focus:ring-primary-border"
                                   data-feature="{{ $feature->id }}"
                                   onchange="toggleFeatureGroup(this)"
                                   aria-label="Pilih semua permission {{ $feature->name }}">
                            <span class="hidden sm:inline">Pilih semua</span>
                        </label>
                    </div>
                </div>

                {{-- Root Feature Content --}}
                <div x-show="expanded" x-collapse class="bg-surface">

                    {{-- Root-level permissions --}}
                    @if(isset($permissions[$feature->id]) && $permissions[$feature->id]->count())
                    <div class="px-6 py-3 flex flex-wrap gap-x-6 gap-y-3 border-b border-border/50"
                         data-feature-id="{{ $feature->id }}">
                        @foreach($permissions[$feature->id] as $perm)
                        <label class="inline-flex items-center gap-2 cursor-pointer group">
                            <input type="checkbox"
                                   name="permissions[]"
                                   value="{{ $perm->id }}"
                                   @checked(in_array($perm->id, $rolePermissions))
                                   onchange="updatePermCount()"
                                   class="perm-checkbox rounded border-border text-primary focus:ring-2 focus:ring-primary-border group-hover:border-primary transition-colors">
                            <span class="text-sm text-text-secondary group-hover:text-text-main transition-colors">
                                {{ $perm->action->name ?? $perm->name ?? $perm->code }}
                            </span>
                        </label>
                        @endforeach
                    </div>
                    @elseif($feature->children->count() === 0)
                    <p class="px-6 py-3 text-xs text-text-disabled italic">
                        Tidak ada permission untuk fitur ini.
                    </p>
                    @endif

                    {{-- Sub Features --}}
                    @if($feature->children->count() > 0)
                    <div class="divide-y divide-border/40">
                        @foreach($feature->children as $child)
                        <div class="pl-8" x-data="{ childExpanded: true }">

                            {{-- Sub Feature Header --}}
                            <div class="flex items-center justify-between px-4 py-2.5 cursor-pointer
                                        hover:bg-surface-muted/60 transition-colors"
                                 @click="childExpanded = !childExpanded">
                                <div class="flex items-center gap-2 min-w-0">
                                    <svg class="h-3.5 w-3.5 text-text-muted transition-transform flex-shrink-0"
                                         :class="childExpanded ? 'rotate-90' : ''"
                                         fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                    <span class="text-sm font-medium text-text-secondary">{{ $child->name }}</span>
                                </div>
                            </div>

                            {{-- Sub Feature Permissions + Grandchildren --}}
                            <div x-show="childExpanded" x-collapse class="pb-2">

                                @if(isset($permissions[$child->id]) && $permissions[$child->id]->count())
                                <div class="px-5 py-2 flex flex-wrap gap-x-6 gap-y-2.5"
                                     data-feature-id="{{ $child->id }}">
                                    @foreach($permissions[$child->id] as $perm)
                                    <label class="inline-flex items-center gap-2 cursor-pointer group">
                                        <input type="checkbox"
                                               name="permissions[]"
                                               value="{{ $perm->id }}"
                                               @checked(in_array($perm->id, $rolePermissions))
                                               onchange="updatePermCount()"
                                               class="perm-checkbox rounded border-border text-primary focus:ring-2 focus:ring-primary-border group-hover:border-primary transition-colors">
                                        <span class="text-sm text-text-secondary group-hover:text-text-main transition-colors">
                                            {{ $perm->action->name ?? $perm->name ?? $perm->code }}
                                        </span>
                                    </label>
                                    @endforeach
                                </div>
                                @else
                                    @if($child->children->count() === 0)
                                    <p class="px-5 py-2 text-xs text-text-disabled italic">
                                        Tidak ada permission spesifik.
                                    </p>
                                    @endif
                                @endif

                                {{-- Mini Features (Grandchildren) --}}
                                @if($child->children->count() > 0)
                                <div class="mx-5 mt-1 divide-y divide-border/30 border-l-2 border-border pl-4">
                                    @foreach($child->children as $grandchild)
                                    <div class="py-2.5">
                                        {{-- Mini feature label --}}
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="inline-block h-1.5 w-1.5 rounded-full bg-primary/50 flex-shrink-0"></span>
                                            <span class="text-xs font-semibold text-text-secondary uppercase tracking-wide">
                                                {{ $grandchild->name }}
                                            </span>
                                        </div>

                                        @if(isset($permissions[$grandchild->id]) && $permissions[$grandchild->id]->count())
                                        <div class="flex flex-wrap gap-x-5 gap-y-2"
                                             data-feature-id="{{ $grandchild->id }}">
                                            @foreach($permissions[$grandchild->id] as $perm)
                                            @php $isSensitive = str_contains($perm->code, 'sensitive'); @endphp
                                            <label class="inline-flex items-center gap-1.5 cursor-pointer group">
                                                <input type="checkbox"
                                                       name="permissions[]"
                                                       value="{{ $perm->id }}"
                                                       @checked(in_array($perm->id, $rolePermissions))
                                                       onchange="updatePermCount()"
                                                       class="perm-checkbox rounded border-border focus:ring-2 transition-colors
                                                              {{ $isSensitive
                                                                  ? 'text-error border-error-border focus:ring-error-border'
                                                                  : 'text-primary focus:ring-primary-border group-hover:border-primary' }}">
                                                <span class="text-xs transition-colors
                                                             {{ $isSensitive
                                                                 ? 'text-error font-medium group-hover:opacity-80'
                                                                 : 'text-text-secondary group-hover:text-text-main' }}">
                                                    {{ $perm->action->name ?? $perm->name ?? $perm->code }}
                                                    @if($isSensitive)
                                                        <span class="ml-0.5 text-error" title="Aksi sensitif — berikan hati-hati" aria-hidden="true">⚠</span>
                                                    @endif
                                                </span>
                                            </label>
                                            @endforeach
                                        </div>
                                        @else
                                        <p class="text-xs text-text-disabled italic">Tidak ada permission.</p>
                                        @endif
                                    </div>
                                    @endforeach
                                </div>
                                @endif

                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif

                </div>
            </div>

            @empty
            <div class="px-6 py-16 text-center">
                <div class="flex flex-col items-center gap-3 text-text-muted">
                    <svg class="h-10 w-10 text-text-disabled" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <p class="text-sm font-medium">Feature tree belum tersedia</p>
                    <p class="text-xs text-text-disabled max-w-xs text-center">
                        Jalankan <span class="data-text">php artisan rbac:generate-permissions</span>
                        untuk membuat permission dari feature tree.
                    </p>
                </div>
            </div>
            @endforelse

        </div>

        {{-- Card Footer / Action Bar --}}
        <x-slot name="footer">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-text-muted">
                    Semua perubahan permission tercatat di
                    <span class="font-medium text-text-secondary">Audit Log</span>
                    dan berlaku segera untuk semua user dengan role ini.
                </p>
                <div class="flex gap-2 flex-shrink-0">
                    <a href="{{ route('roles.index') }}" class="btn-secondary">
                        Batal
                    </a>
                    <button type="submit" form="matrixForm" class="btn-primary">
                        Simpan Permission
                    </button>
                </div>
            </div>
        </x-slot>

    </x-ui.card>

</form>

@section('scripts')
<script>
    // ---- Toast warning untuk role sistem ----
    @if($role->is_system && !$role->isOwner())
    document.addEventListener('DOMContentLoaded', () => {
        Toast.warning(
            'Role Sistem',
            'Perubahan permission berlaku untuk semua user dengan role {{ $role->name }}. Berhati-hati saat mengurangi permission aktif.',
            7000
        );
    });
    @endif
    function updatePermCount() {
        const count = document.querySelectorAll('.perm-checkbox:checked').length;
        document.getElementById('permCount').textContent = count;
        updateFeatureToggles();
    }

    // Pilih semua checkbox
    function selectAll() {
        document.querySelectorAll('.perm-checkbox').forEach(cb => { cb.checked = true; });
        updatePermCount();
    }

    // Hapus semua checkbox
    function deselectAll() {
        document.querySelectorAll('.perm-checkbox').forEach(cb => { cb.checked = false; });
        updatePermCount();
    }

    // Toggle semua permission dalam satu feature
    function toggleFeatureGroup(toggleCb) {
        const featureId   = toggleCb.dataset.feature;
        const featureGroup = document.querySelector(`[data-feature-id="${featureId}"]`);
        if (featureGroup) {
            featureGroup.querySelectorAll('.perm-checkbox').forEach(cb => {
                cb.checked = toggleCb.checked;
            });
        }
        updatePermCount();
    }

    // Sinkronisasi state checkbox "Pilih semua" tiap feature
    function updateFeatureToggles() {
        document.querySelectorAll('.feature-toggle').forEach(toggle => {
            const featureId    = toggle.dataset.feature;
            const featureGroup = document.querySelector(`[data-feature-id="${featureId}"]`);
            if (!featureGroup) return;

            const all     = featureGroup.querySelectorAll('.perm-checkbox');
            const checked = featureGroup.querySelectorAll('.perm-checkbox:checked');

            toggle.indeterminate = checked.length > 0 && checked.length < all.length;
            toggle.checked       = all.length > 0 && checked.length === all.length;
        });
    }

    // Inisialisasi saat halaman dimuat
    document.addEventListener('DOMContentLoaded', () => {
        updatePermCount();
        document.querySelectorAll('.perm-checkbox').forEach(cb => {
            cb.addEventListener('change', updateFeatureToggles);
        });
        updateFeatureToggles();
    });
</script>
@endsection

@endsection
