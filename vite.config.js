import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // resources/js/qr-scan.js & resources/js/barcode-scan.js ENTRY
            // TERPISAH (bukan digabung app.js) — dua-duanya pakai kamera
            // (getUserMedia) yang cuma relevan di halaman masing-masing
            // (Scan QR Internal / Lacak Barang-SN), gak perlu dikirim ke
            // SEMUA halaman.
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/qr-scan.js', 'resources/js/barcode-scan.js'],
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
