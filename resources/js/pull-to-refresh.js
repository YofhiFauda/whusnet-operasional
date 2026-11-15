/* ── Pull-to-refresh (tarik ke bawah untuk memuat ulang) ──
   Kenapa bikin sendiri, bukan pakai bawaan browser: layout utama sengaja
   mengunci `html, body { overflow: hidden; overscroll-behavior: none }` dan
   yang bergulir cuma `<main id="main-content">` (header diam, h-dvh pas,
   tanpa pantulan iOS). Pull-to-refresh bawaan browser cuma jalan kalau
   DOKUMEN yang bergulir — jadi di layout ini mati total. Di PWA standalone
   (iOS & Android) fitur itu memang tidak ada sama sekali. Melepas kunci
   overflow cuma demi gestur ini akan merusak shell + halaman Ticketing yang
   mengandalkan overflow-hidden, dan tetap tidak menolong PWA.

   Aturan aktif (semua harus terpenuhi, kalau tidak gestur dilepas ke scroll
   biasa):
   - sentuhan 1 jari, mulai DI DALAM #main-content, #main-content di posisi
     paling atas;
   - tidak di dalam elemen ber-position fixed (modal, drawer, bottom sheet),
     dialog, atau elemen ber-`data-no-pull-refresh`;
   - tidak di dalam area gulir dalam (tabel/list) yang belum di posisi atas —
     kalau iya, tarikan itu milik area tersebut;
   - arah tarikan dominan vertikal ke bawah (geser horizontal tabel aman);
   - halaman tidak mematikannya lewat `data-pull-to-refresh="off"` di <main>
     (Peta Infrastruktur: kanvasnya di-pan pakai jari).

   Form POST yang sudah diisi tapi belum disimpan → minta konfirmasi dulu,
   supaya isian pembayaran/laporan lapangan tidak hilang karena tarikan tak
   sengaja.

   Sebelum reload dipancarkan event `pull-to-refresh` (cancelable) di
   document — halaman yang punya cara refresh lebih ringan boleh
   preventDefault() dan menanganinya sendiri. File ini mandiri (tanpa
   import) supaya bisa diuji langsung lewat Playwright addScriptTag.
   Penjaga: tests/Browser/scenarios/pull-to-refresh.spec.js. */
(() => {
    if (window.__pullToRefreshInstalled) {
        return;
    }
    window.__pullToRefreshInstalled = true;

    /** Jarak indikator (px) yang dianggap "cukup ditarik". */
    const THRESHOLD = 70;
    /** Batas jarak indikator — tarikan lebih jauh tidak menambah apa-apa. */
    const MAX_PULL = 110;
    /** Gerakan jari minimal sebelum arah tarikan diputuskan. */
    const SLOP = 8;
    /** Rasio jari → indikator, biar terasa "berat" seperti aplikasi native. */
    const RESISTANCE = 0.55;

    let gesture = null;
    let refreshing = false;
    let hasUnsavedForm = false;
    let indicator = null;

    const mainContent = () => document.getElementById('main-content');

    function isScrollable(style) {
        return /(auto|scroll|overlay)/.test(style.overflowY);
    }

    function canStartFrom(target, main) {
        if (!main || main.dataset.pullToRefresh === 'off') {
            return false;
        }
        if (!(target instanceof Element) || !main.contains(target)) {
            return false;
        }
        if (target.closest('[data-no-pull-refresh], [aria-modal="true"], [role="dialog"], dialog')) {
            return false;
        }

        for (let el = target; el && el !== main; el = el.parentElement) {
            const style = window.getComputedStyle(el);
            if (style.position === 'fixed') {
                return false;
            }
            if (el.scrollTop > 0 && isScrollable(style)) {
                return false;
            }
        }

        return main.scrollTop <= 0;
    }

    function ensureStyle() {
        if (document.getElementById('ptr-style')) {
            return;
        }
        const style = document.createElement('style');
        style.id = 'ptr-style';
        style.textContent = `
            .ptr-indicator{position:fixed;left:50%;z-index:45;display:flex;align-items:center;gap:.5rem;
              padding:.5rem .875rem;border-radius:9999px;font:500 12px/1.2 ui-sans-serif,system-ui,sans-serif;
              background:#fff;color:#334155;border:1px solid #e2e8f0;
              box-shadow:0 4px 14px rgba(15,23,42,.12);pointer-events:none;opacity:0;will-change:transform,opacity}
            .ptr-indicator.ptr-dark{background:#1e293b;color:#e2e8f0;border-color:#334155}
            .ptr-indicator.ptr-animate{transition:transform .2s ease,opacity .2s ease}
            .ptr-indicator svg{width:16px;height:16px;flex:none;transition:transform .15s ease}
            .ptr-indicator.ptr-armed{color:#0284c7}
            .ptr-indicator.ptr-dark.ptr-armed{color:#38bdf8}
            .ptr-indicator.ptr-armed svg{transform:rotate(180deg)}
            .ptr-indicator.ptr-loading svg{animation:ptr-spin .8s linear infinite}
            @keyframes ptr-spin{to{transform:rotate(360deg)}}
            @media (prefers-reduced-motion:reduce){.ptr-indicator,.ptr-indicator svg{transition:none!important}}
        `;
        document.head.appendChild(style);
    }

    const ARROW_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14"/><path d="m19 12-7 7-7-7"/></svg>';
    const SPINNER_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg>';

    /* Indikator ditempel ke <body>; hx-boost menukar isi body tiap navigasi,
       jadi elemen lama bisa sudah lepas dari DOM — buat ulang kalau begitu. */
    function getIndicator() {
        ensureStyle();
        if (!indicator || !indicator.isConnected) {
            indicator = document.createElement('div');
            indicator.className = 'ptr-indicator';
            indicator.setAttribute('role', 'status');
            indicator.setAttribute('aria-live', 'polite');
            indicator.innerHTML = `${ARROW_ICON}<span></span>`;
            document.body.appendChild(indicator);
        }
        indicator.classList.toggle('ptr-dark', document.documentElement.classList.contains('dark'));

        return indicator;
    }

    function setLabel(el, text) {
        const span = el.querySelector('span');
        if (span.textContent !== text) {
            span.textContent = text;
        }
    }

    function render(distance, top) {
        const el = getIndicator();
        const armed = distance >= THRESHOLD;
        el.classList.remove('ptr-animate', 'ptr-loading');
        el.classList.toggle('ptr-armed', armed);
        el.style.top = `${top}px`;
        el.style.opacity = String(Math.min(1, distance / (THRESHOLD * 0.6)));
        el.style.transform = `translate(-50%, ${distance - 40}px)`;
        setLabel(el, armed ? 'Lepas untuk memuat ulang' : 'Tarik untuk memuat ulang');
    }

    function showLoading() {
        const el = getIndicator();
        el.classList.add('ptr-animate', 'ptr-loading');
        el.classList.remove('ptr-armed');
        el.style.opacity = '1';
        el.style.transform = `translate(-50%, ${THRESHOLD * 0.6}px)`;
        el.querySelector('svg').outerHTML = SPINNER_ICON;
        setLabel(el, 'Memuat ulang…');
    }

    function hide() {
        if (!indicator || !indicator.isConnected) {
            return;
        }
        indicator.classList.add('ptr-animate');
        indicator.classList.remove('ptr-armed', 'ptr-loading');
        indicator.style.opacity = '0';
        indicator.style.transform = 'translate(-50%, -40px)';
        const svg = indicator.querySelector('svg');
        if (svg) {
            svg.outerHTML = ARROW_ICON;
        }
    }

    function reloadPage() {
        refreshing = true;
        showLoading();
        const event = new CustomEvent('pull-to-refresh', { cancelable: true });
        if (document.dispatchEvent(event)) {
            window.location.reload();

            return;
        }
        // Halaman menangani refresh sendiri.
        refreshing = false;
        hide();
    }

    function requestRefresh() {
        if (!hasUnsavedForm) {
            reloadPage();

            return;
        }

        hide();
        const title = 'Muat Ulang Halaman?';
        const message = 'Isian form yang belum disimpan akan hilang.';
        if (typeof window.Confirm === 'function') {
            window.Confirm(title, message, 'warning', reloadPage);
        } else if (window.confirm(`${title}\n${message}`)) {
            reloadPage();
        }
    }

    document.addEventListener('touchstart', (e) => {
        gesture = null;
        if (refreshing || e.touches.length !== 1) {
            return;
        }
        const main = mainContent();
        if (!canStartFrom(e.target, main)) {
            return;
        }
        const touch = e.touches[0];
        gesture = {
            main,
            startX: touch.clientX,
            startY: touch.clientY,
            top: main.getBoundingClientRect().top,
            pulling: false,
            distance: 0,
        };
    }, { passive: true, capture: true });

    /* passive:false WAJIB — begitu tarikan diambil alih, preventDefault()
       mencegah pantulan/scroll bawaan di saat yang sama. Selama gesture belum
       diambil alih handler ini langsung return, jadi scroll biasa tidak
       pernah diblok. */
    document.addEventListener('touchmove', (e) => {
        if (!gesture) {
            return;
        }
        if (e.touches.length !== 1) {
            cancelGesture();

            return;
        }
        const touch = e.touches[0];
        const dx = touch.clientX - gesture.startX;
        const dy = touch.clientY - gesture.startY;

        if (!gesture.pulling) {
            if (Math.abs(dx) < SLOP && Math.abs(dy) < SLOP) {
                return;
            }
            if (dy <= 0 || Math.abs(dx) > Math.abs(dy) || gesture.main.scrollTop > 0) {
                gesture = null;

                return;
            }
            gesture.pulling = true;
        }

        gesture.distance = Math.min(MAX_PULL, Math.max(0, (dy - SLOP) * RESISTANCE));
        if (e.cancelable) {
            e.preventDefault();
        }
        render(gesture.distance, gesture.top);
    }, { passive: false, capture: true });

    function cancelGesture() {
        if (gesture?.pulling) {
            hide();
        }
        gesture = null;
    }

    document.addEventListener('touchend', () => {
        if (!gesture) {
            return;
        }
        const shouldRefresh = gesture.pulling && gesture.distance >= THRESHOLD;
        gesture = null;
        if (shouldRefresh) {
            requestRefresh();
        } else {
            hide();
        }
    }, { capture: true });

    document.addEventListener('touchcancel', cancelGesture, { capture: true });

    /* Lacak isian form yang belum disimpan. Cuma form POST (termasuk PUT/
       DELETE Laravel lewat _method) — form filter/pencarian GET tidak
       dianggap "isian berharga". */
    function trackUnsaved(e) {
        const form = e.target?.form;
        if (form instanceof HTMLFormElement && form.method === 'post' && !form.hasAttribute('data-no-unsaved-guard')) {
            hasUnsavedForm = true;
        }
    }
    document.addEventListener('input', trackUnsaved, true);
    document.addEventListener('change', trackUnsaved, true);
    document.addEventListener('submit', () => {
        hasUnsavedForm = false;
    }, true);

    // Navigasi hx-boost = halaman baru tanpa reload penuh → status ikut direset.
    document.addEventListener('htmx:afterSettle', (e) => {
        if (e.detail?.boosted) {
            hasUnsavedForm = false;
            gesture = null;
            refreshing = false;
        }
    });

    // Kembali lewat back-forward cache: halaman lama bisa masih "Memuat ulang…".
    window.addEventListener('pageshow', (e) => {
        if (e.persisted) {
            refreshing = false;
            gesture = null;
            hide();
        }
    });
})();
