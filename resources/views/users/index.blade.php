@extends('layouts.app')

@section('title', 'Manajemen User')

@section('content')
<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Manajemen User</h1>
        <p class="text-sm text-slate-500 mt-1">Daftar user dan penugasan POP/Cabang</p>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500">
                    <th class="px-6 py-4 font-medium">Nama User</th>
                    <th class="px-6 py-4 font-medium">Role</th>
                    <th class="px-6 py-4 font-medium">POP Ditugaskan</th>
                    <th class="px-6 py-4 font-medium text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 text-sm">
                @forelse($users as $user)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="font-medium text-slate-900">{{ $user->name }}</div>
                            <div class="text-slate-500 text-xs">{{ $user->email }}</div>
                        </td>
                        <td class="px-6 py-4">
                            @if($user->role)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100">
                                    {{ $user->role->name }}
                                </span>
                            @else
                                <span class="text-slate-400 italic">No Role</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if(in_array(optional($user->role)->name, ['Owner', 'Admin Pusat']))
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-50 text-purple-700 border border-purple-100">
                                    Semua POP (Akses Penuh)
                                </span>
                            @else
                                @forelse($user->pops as $pop)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200 mr-1 mb-1">
                                        {{ $pop->name }}
                                    </span>
                                @empty
                                    <span class="text-slate-400 italic text-xs">Belum ada POP ditugaskan</span>
                                @endforelse
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('users.pops.edit', $user) }}" class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors border border-blue-100">
                                <svg class="w-3.5 h-3.5 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                </svg>
                                Assign POP
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-slate-500">
                            Tidak ada data user.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    @if($users->hasPages())
        <div class="px-6 py-4 border-t border-slate-200">
            {{ $users->links() }}
        </div>
    @endif
</div>
@endsection
