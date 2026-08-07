<div x-data="notificationDropdown()" class="relative mr-2">
    <button @click="open = !open" @click.outside="open = false" 
            class="relative p-2 text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-900/30 rounded-lg transition-colors"
            title="Notifikasi" aria-label="Notifikasi">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>
        <span x-show="unreadCount > 0" x-text="unreadCount" 
              class="absolute top-1 right-1 inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold leading-none text-white transform translate-x-1/3 -translate-y-1/3 bg-red-600 dark:bg-red-500 rounded-full shadow-sm"></span>
    </button>

    <div x-show="open" x-transition.opacity.duration.200ms style="display: none;" 
         class="absolute right-0 mt-2 w-80 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-xl z-50 overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700/80 flex items-center justify-between bg-slate-50/80 dark:bg-slate-900/60">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Notifikasi</h3>
            <button @click="markAllAsRead" x-show="unreadCount > 0" 
                    class="text-xs text-sky-600 dark:text-sky-400 hover:text-sky-800 dark:hover:text-sky-300 font-medium hover:underline transition-colors">Tandai semua dibaca</button>
        </div>
        <div class="max-h-96 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700/60 custom-scrollbar">
            <template x-for="notif in notifications" :key="notif.id">
                <a :href="notif.data.action_url || '#'" @click="markAsRead(notif.id)" 
                   class="block px-4 py-3 transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/50" 
                   :class="notif.read_at ? 'bg-white dark:bg-slate-800' : 'bg-sky-50/50 dark:bg-sky-950/30'">
                    <div class="flex gap-3">
                        <div class="mt-0.5 shrink-0">
                            <span class="inline-flex items-center justify-center h-8 w-8 rounded-full" 
                                  :class="{
                                      'bg-emerald-100 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400': notif.data.type === 'success',
                                      'bg-amber-100 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400': notif.data.type === 'warning',
                                      'bg-rose-100 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400': notif.data.type === 'error',
                                      'bg-sky-100 text-sky-600 dark:bg-sky-950/60 dark:text-sky-400': !notif.data.type || notif.data.type === 'info'
                                  }">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path x-show="notif.data.type === 'error'" stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    <path x-show="notif.data.type === 'warning'" stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    <path x-show="notif.data.type === 'success'" stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    <path x-show="!notif.data.type || notif.data.type === 'info'" stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100 truncate" x-text="notif.data.title"></p>
                            <p class="text-xs text-slate-600 dark:text-slate-300 mt-0.5 line-clamp-2 leading-relaxed" x-text="notif.data.message"></p>
                            <p class="text-[10px] text-slate-400 dark:text-slate-500 font-mono mt-1" x-text="formatDate(notif.created_at)"></p>
                        </div>
                    </div>
                </a>
            </template>
            <div x-show="notifications.length === 0" class="px-4 py-8 text-center">
                <p class="text-sm text-slate-500 dark:text-slate-400">Tidak ada notifikasi.</p>
            </div>
        </div>
        <div class="px-4 py-2.5 border-t border-slate-100 dark:border-slate-700/80 bg-slate-50/80 dark:bg-slate-900/60 text-center">
            <a href="/notifications" class="text-xs font-semibold text-sky-600 dark:text-sky-400 hover:text-sky-800 dark:hover:text-sky-300 transition-colors block">Lihat Semua Notifikasi</a>
        </div>
    </div>
</div>

<script>
function notificationDropdown() {
    return {
        open: false,
        notifications: @json(auth()->user()->notifications()->take(10)->get()),
        unreadCount: {{ auth()->user()->unreadNotificationsCountCached() }},
        
        init() {
            // Komponen ini mount di layout global (tiap halaman) — kalau Alpine
            // init() jalan sebelum bundle Vite selesai load echo.js, window.Echo
            // masih undefined dan listener gak pernah nempel sekali pun ke
            // channel-nya. Badge jadi keliatan realtime tapi sebenernya statis
            // (cuma reflect state saat page load), sampai reload manual. Retry
            // pattern sama kayak technicianNotifier() di tasks/own.blade.php.
            let attempts = 0;
            const maxAttempts = 10;

            const attach = () => {
                if (typeof window.Echo !== 'undefined') {
                    window.Echo.private('App.Models.User.' + {{ auth()->id() }})
                        .notification((notification) => {
                            this.notifications.unshift({
                                id: notification.id,
                                data: notification,
                                created_at: new Date().toISOString(),
                                read_at: null
                            });
                            this.unreadCount++;

                            // Sebelumnya notif baru cuma nambah angka badge
                            // diam-diam — gak ada yang nyadar ada kejadian baru
                            // kalau dropdown-nya gak lagi dibuka (mis. tiket
                            // masuk NOC, task selesai buat FOP, laporan disetujui
                            // buat teknisi). Toast pop-up biar kelihatan LANGSUNG
                            // tanpa harus klik lonceng dulu.
                            if (window.Toast) {
                                const type = notification.type || 'info';
                                (window.Toast[type] || window.Toast.info)(notification.title, notification.message);
                            }
                        })
                        // Sinkron lintas tab — mark-read di tab lain (device sama,
                        // login sama) langsung nurunin badge di sini juga, gak
                        // nunggu reload manual. toOthers() di controller udah
                        // nyaring biar tab yang barusan aksi gak dapet balik
                        // event punya sendiri (state lokalnya udah di-update
                        // optimistically di markAsRead()/markAllAsRead()).
                        .listen('.NotificationsMarkedRead', (e) => {
                            this.unreadCount = e.unread_count;
                        });
                    return;
                }

                attempts++;
                if (attempts < maxAttempts) {
                    setTimeout(attach, 300);
                } else {
                    console.warn('[notificationDropdown] window.Echo tidak tersedia setelah 3 detik. Notifikasi real-time tidak aktif.');
                }
            };

            attach();
        },
        
        // fetch() gak otomatis nempelin header X-Socket-ID kayak axios global
        // (beda dari yang dipakai Echo internal) — tanpa ini toOthers() di
        // NotificationController gak bisa nyaring tab pengirim, jadi tab yang
        // barusan aksi ikut nerima balik event NotificationsMarkedRead punya
        // sendiri (harmless — cuma nimpa unreadCount dgn nilai yang sama —
        // tapi lebih bersih kalau dicegah dari sononya).
        socketHeaders() {
            const headers = {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            };
            if (window.Echo && window.Echo.socketId()) {
                headers['X-Socket-ID'] = window.Echo.socketId();
            }
            return headers;
        },

        async markAsRead(id) {
            const notif = this.notifications.find(n => n.id === id);
            if (notif && !notif.read_at) {
                notif.read_at = new Date().toISOString();
                this.unreadCount = Math.max(0, this.unreadCount - 1);

                await fetch(`/notifications/${id}/read`, {
                    method: 'POST',
                    headers: this.socketHeaders()
                });
            }
        },

        async markAllAsRead() {
            this.notifications.forEach(n => n.read_at = new Date().toISOString());
            this.unreadCount = 0;

            await fetch('/notifications/mark-all-read', {
                method: 'POST',
                headers: this.socketHeaders()
            });
        },
        
        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute:'2-digit' });
        }
    }
}
</script>
