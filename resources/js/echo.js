import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const isSecure = window.location.protocol === 'https:';
const currentHost = window.location.hostname;
const currentPort = window.location.port || (isSecure ? 443 : 80);

// Gunakan hostname aktif jika berjalan di Cloudflare Tunnel (yang URL-nya dinamis) atau localhost
const configuredHost = (import.meta.env.VITE_REVERB_HOST || '').replace(/^https?:\/\/|^https?\/\//, '');
const useDynamicHost = !configuredHost || configuredHost.includes('trycloudflare.com') || configuredHost === 'localhost' || configuredHost === '127.0.0.1';

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: useDynamicHost ? currentHost : configuredHost,
    wsPort: useDynamicHost ? currentPort : (import.meta.env.VITE_REVERB_PORT ?? 80),
    wssPort: useDynamicHost ? currentPort : (import.meta.env.VITE_REVERB_PORT ?? 443),
    forceTLS: useDynamicHost ? isSecure : ((import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https'),
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
