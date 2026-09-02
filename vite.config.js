import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // resources/js/qr-scan.js ENTRY TERPISAH (bukan digabung app.js) —
            // library decode QR (jsqr) cukup berat buat dikirim ke SEMUA
            // halaman padahal cuma dipakai satu halaman (Scan QR Internal).
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/qr-scan.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
