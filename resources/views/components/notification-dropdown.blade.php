<div x-data="notificationDropdown()" class="relative mr-2">
    <button @click="open = !open" @click.outside="open = false" class="relative p-2 rounded-full hover:bg-slate-100 transition-colors">
        <svg class="h-5 w-5 text-text-secondary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>
        <span x-show="unreadCount > 0" x-text="unreadCount" class="absolute top-0 right-0 inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold leading-none text-white transform translate-x-1/4 -translate-y-1/4 bg-red-600 rounded-full"></span>
    </button>

    <div x-show="open" x-transition.opacity.duration.200ms style="display: none;" class="absolute right-0 mt-2 w-80 bg-white border border-border rounded-lg shadow-lg z-50 overflow-hidden">
        <div class="px-4 py-3 border-b border-border flex items-center justify-between bg-surface">
            <h3 class="text-sm font-semibold text-text-main">Notifikasi</h3>
            <button @click="markAllAsRead" x-show="unreadCount > 0" class="text-xs text-primary hover:underline">Tandai semua dibaca</button>
        </div>
        <div class="max-h-96 overflow-y-auto divide-y divide-border">
            <template x-for="notif in notifications" :key="notif.id">
                <a :href="notif.data.action_url || '#'" @click="markAsRead(notif.id)" class="block px-4 py-3 hover:bg-slate-50 transition-colors" :class="{'bg-primary/5': !notif.read_at}">
                    <div class="flex gap-3">
                        <div class="mt-0.5">
                            <span class="inline-flex items-center justify-center h-8 w-8 rounded-full" :class="notif.data.type === 'error' ? 'bg-red-100 text-red-600' : 'bg-blue-100 text-blue-600'">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path x-show="notif.data.type === 'error'" stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    <path x-show="notif.data.type !== 'error'" stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-text-main truncate" x-text="notif.data.title"></p>
                            <p class="text-xs text-text-secondary mt-0.5 line-clamp-2" x-text="notif.data.message"></p>
                            <p class="text-[10px] text-text-muted mt-1" x-text="formatDate(notif.created_at)"></p>
                        </div>
                    </div>
                </a>
            </template>
            <div x-show="notifications.length === 0" class="px-4 py-8 text-center">
                <p class="text-sm text-text-muted">Tidak ada notifikasi.</p>
            </div>
        </div>
        <div class="px-4 py-2.5 border-t border-border bg-surface text-center">
            <a href="/notifications" class="text-xs font-semibold text-sky-600 hover:text-sky-800 transition-colors block">Lihat Semua Notifikasi</a>
        </div>
    </div>
</div>

<script>
function notificationDropdown() {
    return {
        open: false,
        notifications: @json(auth()->user()->notifications()->take(10)->get()),
        unreadCount: {{ auth()->user()->unreadNotifications()->count() }},
        
        init() {
            if (window.Echo) {
                window.Echo.private('App.Models.User.' + {{ auth()->id() }})
                    .notification((notification) => {
                        this.notifications.unshift({
                            id: notification.id,
                            data: notification,
                            created_at: new Date().toISOString(),
                            read_at: null
                        });
                        this.unreadCount++;
                    });
            }
        },
        
        async markAsRead(id) {
            const notif = this.notifications.find(n => n.id === id);
            if (notif && !notif.read_at) {
                notif.read_at = new Date().toISOString();
                this.unreadCount = Math.max(0, this.unreadCount - 1);
                
                await fetch(`/notifications/${id}/read`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
                });
            }
        },
        
        async markAllAsRead() {
            this.notifications.forEach(n => n.read_at = new Date().toISOString());
            this.unreadCount = 0;
            
            await fetch('/notifications/mark-all-read', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                }
            });
        },
        
        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute:'2-digit' });
        }
    }
}
</script>
