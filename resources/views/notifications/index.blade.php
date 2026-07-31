@extends('layouts.app')

@section('title', 'Dashboard Notifikasi - Whusnet Operasional')
@section('page_title', 'Dashboard Notifikasi')

@section('content')
<div class="space-y-6">
    <!-- Filter Panel -->
    <x-ui.card class="p-4 sm:p-5 shadow-xs">
        <form method="GET" action="{{ route('notifications.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <label for="date" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wider">Tanggal</label>
                <x-ui.input type="date" name="date" id="date" value="{{ request('date') }}" />
            </div>

            <div>
                <label for="type" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wider">Tipe Notifikasi</label>
                <x-ui.select name="type" id="type">
                    <option value="">Semua Tipe</option>
                    <option value="info" @selected(request('type') === 'info')>Info (Biru)</option>
                    <option value="success" @selected(request('type') === 'success')>Success (Hijau)</option>
                    <option value="warning" @selected(request('type') === 'warning')>Warning (Kuning)</option>
                    <option value="error" @selected(request('type') === 'error')>Error (Merah)</option>
                </x-ui.select>
            </div>

            @if(auth()->user()->hasPermission('task.view.all'))
            <div>
                <label for="user_id" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wider">Penerima</label>
                <x-ui.select name="user_id" id="user_id">
                    <option value="">Semua Penerima</option>
                    @foreach($filterUsers as $fUser)
                        <option value="{{ $fUser->id }}" @selected(request('user_id') == $fUser->id)>
                            {{ $fUser->name }} ({{ $fUser->role->name ?? 'No Role' }})
                        </option>
                    @endforeach
                </x-ui.select>
            </div>
            @endif

            <div class="flex gap-2">
                <x-ui.button type="submit" variant="primary" class="w-full md:w-auto">
                    <svg class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    Filter
                </x-ui.button>
                <x-ui.button type="button" variant="secondary" tag="a" href="{{ route('notifications.index') }}" class="w-full md:w-auto text-center">
                    Reset
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <!-- Notification Cards List -->
    <div class="space-y-3">
        @forelse($notifications as $notif)
            @php
                $data = $notif->data;
                $isRead = !is_null($notif->read_at);
                $type = $data['type'] ?? 'info';
                
                $borderClass = $isRead 
                    ? 'border-slate-200 dark:border-slate-800' 
                    : 'border-slate-200 dark:border-slate-800 border-l-4 border-l-sky-500 dark:border-l-sky-400';
                    
                $bgClass = $isRead 
                    ? 'bg-white dark:bg-slate-800/60 opacity-80 dark:opacity-75' 
                    : 'bg-sky-50/20 dark:bg-sky-950/15';
                
                $badgeVariant = match($type) {
                    'success' => 'success',
                    'warning' => 'warning',
                    'error' => 'error',
                    default => 'info',
                };
            @endphp
            
            <div id="notif-row-{{ $notif->id }}" 
                 class="flex flex-col sm:flex-row items-start sm:items-center justify-between p-4 sm:p-4.5 border rounded-xl transition-all duration-200 {{ $borderClass }} {{ $bgClass }} hover:border-slate-300 dark:hover:border-slate-700">
                <div class="flex items-start gap-3.5 sm:gap-4 flex-1 min-w-0">
                    <div class="mt-0.5 shrink-0">
                        <span class="inline-flex items-center justify-center h-9 w-9 rounded-full border @if($type === 'success') bg-emerald-50 text-emerald-600 border-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-900/40 @elseif($type === 'error') bg-rose-50 text-rose-600 border-rose-100 dark:bg-rose-950/40 dark:text-rose-400 dark:border-rose-900/40 @elseif($type === 'warning') bg-amber-50 text-amber-600 border-amber-100 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-900/40 @else bg-sky-50 text-sky-600 border-sky-100 dark:bg-sky-950/40 dark:text-sky-400 dark:border-sky-900/40 @endif">
                            @if($type === 'success')
                                <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            @elseif($type === 'error')
                                <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            @elseif($type === 'warning')
                                <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            @else
                                <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            @endif
                        </span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100 truncate">{{ $data['title'] ?? 'No Title' }}</h3>
                            <x-ui.badge variant="{{ $badgeVariant }}">{{ strtoupper($type) }}</x-ui.badge>
                            @if(auth()->user()->hasPermission('task.view.all') && $notif->notifiable)
                                <span class="text-xs text-slate-500 dark:text-slate-400 font-normal">untuk <strong class="text-slate-700 dark:text-slate-300 font-medium">{{ $notif->notifiable->name }}</strong></span>
                            @endif
                        </div>
                        <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 mt-1 leading-relaxed">{{ $data['message'] ?? 'No Message' }}</p>
                        <div class="flex flex-wrap items-center gap-3 mt-2 text-xs text-slate-500 dark:text-slate-400">
                            <span class="font-mono text-slate-500 dark:text-slate-400 text-[11px]">{{ \App\Support\IndonesianDate::dateTime($notif->created_at) }}</span>
                            @if(!empty($data['action_url']))
                                <div class="h-3 w-px bg-slate-200 dark:bg-slate-700"></div>
                                <a href="{{ $data['action_url'] }}" class="text-sky-600 dark:text-sky-400 hover:text-sky-800 dark:hover:text-sky-300 hover:underline inline-flex items-center gap-1 font-semibold transition-colors">
                                    Lihat Detail
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                    </svg>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
                
                @if(auth()->id() === $notif->notifiable_id)
                <div class="mt-3 sm:mt-0 sm:ml-4 shrink-0 flex gap-2">
                    <button type="button" 
                            onclick="toggleRead('{{ $notif->id }}', this)" 
                            data-read="{{ $isRead ? 'true' : 'false' }}"
                            class="px-3 py-1.5 border rounded-lg text-xs font-medium transition-all duration-150 cursor-pointer @if($isRead) bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100 hover:text-slate-900 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700 dark:hover:bg-slate-700 @else bg-sky-50/80 text-sky-700 border-sky-200/80 hover:bg-sky-100 dark:bg-sky-950/50 dark:text-sky-300 dark:border-sky-800/60 dark:hover:bg-sky-900/40 @endif">
                        {{ $isRead ? 'Tandai Belum Dibaca' : 'Tandai Dibaca' }}
                    </button>
                </div>
                @endif
            </div>
        @empty
            <x-ui.card class="p-8 text-center">
                <svg class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0a2 2 0 01-2 2H6a2 2 0 01-2-2m16 0V9a2 2 0 00-2-2H6a2 2 0 00-2 2v2m16 4h-4a2 2 0 00-2 2v1a2 2 0 01-2 2H8a2 2 0 01-2-2v-1a2 2 0 00-2-2H2" />
                </svg>
                <h3 class="mt-4 text-sm font-semibold text-slate-900 dark:text-slate-100">Tidak Ada Notifikasi</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Tidak ada notifikasi yang ditemukan pada filter ini.</p>
            </x-ui.card>
        @endforelse
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $notifications->links() }}
    </div>
</div>
@endsection

@section('scripts')
<script>
async function toggleRead(id, button) {
    const isRead = button.getAttribute('data-read') === 'true';
    const url = isRead ? `/notifications/${id}/unread` : `/notifications/${id}/read`;
    
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            }
        });
        
        if (response.ok) {
            const newReadState = !isRead;
            button.setAttribute('data-read', newReadState.toString());
            
            const card = document.getElementById(`notif-row-${id}`);
            if (newReadState) {
                card.classList.remove('bg-sky-50/20', 'dark:bg-sky-950/15', 'border-l-4', 'border-l-sky-500', 'dark:border-l-sky-400');
                card.classList.add('bg-white', 'border-slate-200', 'opacity-80', 'dark:bg-slate-800/60', 'dark:border-slate-800', 'dark:opacity-75');
                button.innerHTML = 'Tandai Belum Dibaca';
                button.className = 'px-3 py-1.5 border rounded-lg text-xs font-medium transition-all duration-150 cursor-pointer bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100 hover:text-slate-900 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700 dark:hover:bg-slate-700';
            } else {
                card.classList.remove('bg-white', 'border-slate-200', 'opacity-80', 'dark:bg-slate-800/60', 'dark:border-slate-800', 'dark:opacity-75');
                card.classList.add('bg-sky-50/20', 'dark:bg-sky-950/15', 'border-slate-200', 'dark:border-slate-800', 'border-l-4', 'border-l-sky-500', 'dark:border-l-sky-400');
                button.innerHTML = 'Tandai Dibaca';
                button.className = 'px-3 py-1.5 border rounded-lg text-xs font-medium transition-all duration-150 cursor-pointer bg-sky-50/80 text-sky-700 border-sky-200/80 hover:bg-sky-100 dark:bg-sky-950/50 dark:text-sky-300 dark:border-sky-800/60 dark:hover:bg-sky-900/40';
            }
            
            Toast.success('Berhasil', newReadState ? 'Notifikasi ditandai telah dibaca.' : 'Notifikasi ditandai belum dibaca.');
        } else {
            Toast.error('Gagal', 'Terjadi kesalahan sistem.');
        }
    } catch (e) {
        Toast.error('Error', 'Gagal memproses permintaan.');
    }
}
</script>
@endsection
