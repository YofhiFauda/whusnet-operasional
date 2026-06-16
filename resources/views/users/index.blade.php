@extends('layouts.app')

@section('title', 'Manajemen User & POP - Whusnet Operasional')
@section('page_title', 'Manajemen User & POP')

@section('content')
@if(session('success'))
<div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
    {{ session('success') }}
</div>
@endif

<div class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h2 class="text-sm font-bold text-slate-800">Daftar User</h2>
            <p class="mt-1 text-xs text-slate-500">Kelola user internal, role, dan penugasan POP/cabang dari satu halaman.</p>
        </div>
        @if(auth()->user()->hasPermission('manage_users'))
        <a href="{{ route('users.create') }}" class="inline-flex items-center justify-center rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">
            Tambah User
        </a>
        @endif
    </div>

    <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-md border border-slate-200 px-3 py-2 text-center">
            <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Total User</div>
            <div class="text-lg font-bold text-slate-800">{{ $totalUsers }}</div>
        </div>
        <div class="rounded-md border border-slate-200 px-3 py-2 text-center">
            <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Owner/Admin</div>
            <div class="text-lg font-bold text-slate-800">{{ $fullAccessUsers }}</div>
        </div>
        <div class="rounded-md border border-slate-200 px-3 py-2 text-center">
            <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Dengan POP</div>
            <div class="text-lg font-bold text-slate-800">{{ $withPopUsers }}</div>
        </div>
        <div class="rounded-md border border-slate-200 px-3 py-2 text-center">
            <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Tanpa POP</div>
            <div class="text-lg font-bold text-slate-800">{{ $withoutPopUsers }}</div>
        </div>
    </div>
</div>

<div class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
    <form action="{{ route('users.index') }}" method="GET" class="grid grid-cols-1 gap-4 lg:grid-cols-5 lg:items-end">
        <div class="lg:col-span-2">
            <label for="search" class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-500">Cari User</label>
            <input
                id="search"
                name="search"
                type="text"
                value="{{ $search }}"
                placeholder="Cari nama, email, atau phone..."
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
            >
        </div>

        <div>
            <label for="role_id" class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-500">Role</label>
            <select id="role_id" name="role_id" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                <option value="">Semua Role</option>
                @foreach($roles as $role)
                    <option value="{{ $role->id }}" @selected((string) $roleId === (string) $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="status" class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-500">Status</label>
            <select id="status" name="status" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                <option value="">Semua Status</option>
                <option value="active" @selected($status === 'active')>Aktif</option>
                <option value="inactive" @selected($status === 'inactive')>Nonaktif</option>
            </select>
        </div>

        <div>
            <label for="pop_id" class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-500">POP</label>
            <select id="pop_id" name="pop_id" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                <option value="">Semua POP</option>
                @foreach($pops as $pop)
                    <option value="{{ $pop->id }}" @selected((string) $popId === (string) $pop->id)>{{ $pop->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="inline-flex flex-1 items-center justify-center rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">
                Filter
            </button>
            <a href="{{ route('users.index') }}" class="inline-flex items-center justify-center rounded-md border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                Reset
            </a>
        </div>
    </form>
</div>

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Nama</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Role</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">POP</th>
                    <th class="px-6 py-3 text-center text-xs font-bold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-6 py-3 text-center text-xs font-bold uppercase tracking-wider text-slate-500">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @forelse($users as $user)
                <tr class="hover:bg-slate-50/60">
                    <td class="px-6 py-4">
                        <div class="text-sm font-semibold text-slate-800">{{ $user->name }}</div>
                        <div class="text-xs text-slate-400 font-mono">{{ $user->phone ?? '-' }}</div>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600">{{ $user->email }}</td>
                    <td class="px-6 py-4">
                        <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-semibold text-slate-700">
                            {{ optional($user->role)->name ?? '-' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600">
                        @if($user->pops->isEmpty())
                            <span class="text-slate-400">Belum ditugaskan</span>
                        @else
                            <div class="space-y-1">
                                @foreach($user->pops->take(3) as $pop)
                                    <div class="text-xs text-slate-700">
                                        <span class="font-semibold">{{ $pop->name }}</span>
                                        <span class="text-slate-400">({{ $pop->code }})</span>
                                    </div>
                                @endforeach
                                @if($user->pops->count() > 3)
                                    <div class="text-xs text-slate-400">+{{ $user->pops->count() - 3 }} POP lainnya</div>
                                @endif
                            </div>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $user->status === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' }}">
                            {{ $user->status === 'active' ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        @if(auth()->user()->hasPermission('manage_users'))
                        <div class="flex items-center justify-center gap-2">
                            <a href="{{ route('users.edit', $user) }}" class="inline-flex items-center rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                Edit
                            </a>
                            <a href="{{ route('users.pops.edit', $user) }}" class="inline-flex items-center rounded-md border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-100">
                                Assign POP
                            </a>
                        </div>
                        @else
                        <span class="text-xs text-slate-400">-</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">
                        Tidak ada user yang cocok dengan filter.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($users->hasPages())
    <div class="border-t border-slate-200 bg-slate-50 px-6 py-4">
        {{ $users->links() }}
    </div>
    @endif
</div>
@endsection
