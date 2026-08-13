{{-- Style bersama tiga halaman daftar pelanggan (List, Putus, Gagal).
     Aturan #customerTable cuma kepakai di List biasa, tapi sengaja ikut di sini
     supaya cuma ada SATU tempat mengubah animasi/kerapatan tabel. --}}
<style>
    .toggle-checkbox:checked + .toggle-label .check-icon { display: block; }
    .toggle-checkbox:checked + .toggle-label .x-icon { display: none; }
    .toggle-checkbox:not(:checked) + .toggle-label .check-icon { display: none; }
    .toggle-checkbox:not(:checked) + .toggle-label .x-icon { display: block; }

    /* Custom scrollbar & mobile tab scrollbar */
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

    /* Compact table padding override */
    #customerTable tbody td { padding-top: 0.75rem; padding-bottom: 0.75rem; }
    html.density-compact #customerTable tbody td { padding-top: 0.4rem !important; padding-bottom: 0.4rem !important; }

    /* Baris aktif navigasi keyboard */
    #customerTable tbody tr.row-active { outline: 2px solid #0284c7; outline-offset: -2px; }
    html.dark #customerTable tbody tr.row-active { outline-color: #38bdf8; }

    /* Touch target minimum height for mobile accessibility */
    .touch-target { min-height: 40px; }

    /* Keyframe Animations */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(6px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes popIn {
        0% { opacity: 0; transform: scale(0.9); }
        65% { transform: scale(1.03); }
        100% { opacity: 1; transform: scale(1); }
    }
    @keyframes pulseGlow {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.6; transform: scale(1.25); }
    }
    .animate-fade-in { animation: fadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    .animate-pop-in { animation: popIn 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    .animate-pulse-glow { animation: pulseGlow 2s infinite ease-in-out; }

    .btn-interactive { transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1); }
    .btn-interactive:hover { transform: translateY(-1px); }
    .btn-interactive:active { transform: translateY(0) scale(0.98); }

    .card-interactive { transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1); }
    .card-interactive:hover { transform: translateY(-2px); }
</style>
