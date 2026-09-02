{{-- Tombol kerapatan hidup di _list_filters.blade.php, jadi skriptnya ikut ke
     mana partial itu di-include (List, Putus, Gagal) — bukan cuma di List. --}}
<script>
    /* ── Kerapatan tabel ── */
    function setDensity(mode) {
        document.documentElement.classList.toggle('density-compact', mode === 'compact');
        localStorage.setItem('whusnet-density', mode);
        syncDensityButtons();
    }

    function syncDensityButtons() {
        const compact = document.documentElement.classList.contains('density-compact');
        const on  = 'px-2.5 py-1 rounded-lg transition-all bg-white dark:bg-slate-900 text-sky-600 dark:text-sky-400 font-semibold shadow-sm';
        const off = 'px-2.5 py-1 rounded-lg transition-all text-slate-500 hover:text-slate-700 dark:hover:text-slate-200';
        const c = document.getElementById('density-compact');
        const f = document.getElementById('density-comfortable');
        if (c) c.className = compact ? on : off;
        if (f) f.className = compact ? off : on;
    }

    (function () {
        if (localStorage.getItem('whusnet-density') === 'compact') {
            document.documentElement.classList.add('density-compact');
        }
        syncDensityButtons();
    })();
</script>
