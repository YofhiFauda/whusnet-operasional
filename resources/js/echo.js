import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: (import.meta.env.VITE_REVERB_HOST || '').replace(/^https?:\/\/|^https?\/\//, ''),
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    // Gunakan window.location.origin agar selalu menunjuk ke host yang SAMA
    // dengan halaman ini — tidak bergantung pada APP_URL di .env atau meta tag.
    // Ini mencegah cross-origin auth request yang bisa memblokir cookie.
    authEndpoint: window.location.origin + '/broadcasting/auth',
    auth: {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
            'X-Requested-With': 'XMLHttpRequest',
        },
    },
    withCredentials: true,
});
