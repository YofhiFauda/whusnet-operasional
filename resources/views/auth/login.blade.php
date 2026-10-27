<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, interactive-widget=resizes-content">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#081d32">
    <title>Login - Ruang Kerja Whusnet</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script>
        // Set the theme before paint to prevent a flash of the wrong palette.
        (function () {
            const saved = localStorage.getItem('whusnet-theme');
            const dark = saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            color-scheme: light;
            --surface: #ffffff;
            --surface-subtle: #f6f9fc;
            --text: #14263b;
            --muted: #65758a;
            --muted-soft: #8391a3;
            --stroke: #dbe4ed;
            --stroke-soft: #ebeff4;
            --field: #ffffff;
            --primary: #1169bc;
            --primary-hover: #0c559a;
            --focus-ring: rgba(17, 105, 188, 0.17);
        }
        html.dark {
            color-scheme: dark;
            --surface: #0f1a2b;
            --surface-subtle: #14243a;
            --text: #eef5fd;
            --muted: #a7b7c9;
            --muted-soft: #8295ab;
            --stroke: #34465c;
            --stroke-soft: #26384e;
            --field: #132238;
            --primary: #2588d6;
            --primary-hover: #3b99e1;
            --focus-ring: rgba(58, 158, 234, 0.22);
        }
        *, *::before, *::after { box-sizing: border-box; }
        html { min-width: 320px; }
        body {
            margin: 0;
            min-height: 100vh;
            min-height: 100dvh;
            background: var(--surface);
            color: var(--text);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            -webkit-font-smoothing: antialiased;
            transition: background-color .2s ease, color .2s ease;
        }
        button, input { font: inherit; }
        button { -webkit-tap-highlight-color: transparent; }
        button:focus-visible, summary:focus-visible, a:focus-visible, input:focus-visible {
            outline: 3px solid var(--focus-ring);
            outline-offset: 3px;
        }
        [x-cloak] { display: none !important; }
        ::selection { background: #b4ddfa; color: #0c365f; }

        /* Desktop architecture: balanced auth panel, expansive hero. */
        .auth-shell {
            display: grid;
            /* grid-template-columns: minmax(0, 1fr) 650px; */
            grid-template-columns: 65% 35%;
            min-height: 100vh;
            min-height: 100dvh;
        }

        .hero {
            isolation: isolate;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 46px;
            min-width: 0;
            min-height: 100dvh;
            /* padding: clamp(40px, 6vw, 76px); */
            padding: clamp(45px, 8vw, 85px);
            overflow: hidden;
            color: #fff;
            background: #071c33;
        }
        /* Only the atmosphere moves; layout and typography stay stable. */
        .hero::before {
            content: '';
            position: absolute;
            z-index: -2;
            inset: -16%;
            background:
                radial-gradient(ellipse 65% 52% at 93% 17%, rgba(40, 166, 210, .66), transparent 76%),
                radial-gradient(ellipse 60% 65% at 11% 89%, rgba(16, 80, 145, .72), transparent 75%),
                radial-gradient(ellipse 55% 55% at 60% 56%, rgba(18, 112, 177, .30), transparent 76%),
                linear-gradient(138deg, #071527 4%, #082a4b 48%, #091a32 100%);
            transform: translate3d(-1%, -1%, 0) scale(1);
            transform-origin: center;
            will-change: transform;
            animation: gradient-drift 24s ease-in-out infinite alternate;
        }
        .hero::after {
            content: '';
            position: absolute;
            inset: 0;
            z-index: -1;
            pointer-events: none;
            background: linear-gradient(100deg, rgba(4, 13, 30, .15) 10%, transparent 65%);
        }
        .network-lines {
            position: absolute;
            right: -14%;
            top: 17%;
            width: min(82%, 850px);
            height: 72%;
            pointer-events: none;
            opacity: .46;
        }
        .network-lines path {
            stroke: rgba(154, 223, 249, .27);
            stroke-width: 1;
            fill: none;
            vector-effect: non-scaling-stroke;
        }
        .network-lines .signal {
            stroke: rgba(169, 234, 255, .65);
            stroke-width: 1.4;
            stroke-dasharray: 5 16;
            animation: path-flow 28s linear infinite;
        }
        .hero-top, .hero-bottom, .hero-body { position: relative; z-index: 1; }
        .hero-top { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .brand { display: inline-flex; align-items: center; gap: 12px; text-decoration: none; color: inherit; }
        .brand-symbol { width: 37px; height: 37px; flex: none; }
        .brand-wordmark { font-size: 17px; font-weight: 800; letter-spacing: -.055em; }
        .brand-caption { display: block; margin-top: 1px; font-size: 10px; font-weight: 500; letter-spacing: .065em; color: rgba(221, 241, 255, .62); }
        .hero-body { max-width: 680px; width: 100%; margin-block: auto; padding-block: 28px; }
        .hero-eyebrow { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; font-size: 11px; font-weight: 700; letter-spacing: .15em; text-transform: uppercase; color: #b5e8fd; }
        /* .hero-eyebrow::before { content: ''; display: inline-block; height: 1px; width: 30px; background: #80c7e9; } */
        .hero h1 {
            margin: 0;
            max-width: 675px;
            font-size: clamp(38px, 4.2vw, 66px);
            line-height: 1.095;
            letter-spacing: -.066em;
            font-weight: 600;
            text-wrap: balance;
        }
        .hero h1 em { font-style: normal; color: #9cddf5; }
        .hero-description { max-width: 520px; margin: 26px 0 0; font-size: clamp(14px, 1.05vw, 16px); line-height: 1.8; color: rgba(228, 242, 253, .78); }
        .hero-features {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: clamp(12px, 2vw, 30px);
            /* margin-top: clamp(45px, 5.5vw, 84px); */
            margin-top: clamp(14px, 5.5vw, 30px);
            padding-top: 25px;
            border-top: 1px solid rgba(228, 242, 253, .22);
        }
        .feature-line { width: 24px; height: 2px; margin-bottom: 14px; background: rgba(159, 221, 248, .86); }
        .feature-title { margin: 0; font-size: 13px; font-weight: 600; letter-spacing: -.015em; color: #f3faff; }
        .feature-desc { margin: 6px 0 0; font-size: 12px; line-height: 1.6; color: rgba(225, 241, 254, .64); }
        .hero-bottom { display: flex; align-items: center; justify-content: space-between; gap: 24px; padding-top: 22px; color: rgba(225, 241, 254, .56); font-size: 11px; }
        .hero-bottom strong { color: rgba(244, 251, 255, .87); font-weight: 500; }

        .signin-pane {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-width: 0;
            min-height: 100dvh;
            padding: 28px clamp(32px, 4vw, 48px) 24px;
            background: var(--surface);
            border-left: 1px solid var(--stroke-soft);
        }
        .workspace-note { font-size: 10.5px; letter-spacing: .12em; font-weight: 700; color: var(--muted-soft); text-transform: uppercase; }
        .mobile-brand { display: none; align-items: center; gap: 9px; color: var(--text); font-size: 15px; font-weight: 800; letter-spacing: -.04em; }
        .mobile-brand .brand-symbol { width: 30px; height: 30px; }
        .theme-switch { display: inline-flex; align-items: center; justify-content: center; flex: none; width: 36px; height: 36px; border-radius: 9px; border: 1px solid var(--stroke-soft); background: transparent; color: var(--muted); cursor: pointer; transition: color .2s, background .2s, border-color .2s; }
        .theme-switch:hover { background: var(--surface-subtle); color: var(--text); border-color: var(--stroke); }
        .theme-switch svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

        .signin-center { width: 100%; max-width: 480px; margin: auto; padding: 28px 0 24px; }
        .form-eyebrow { display: inline-block; margin-bottom: 13px; color: var(--primary); font-size: 11px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
        .signin-heading { margin: 0; font-size: clamp(25px, 1.9vw, 29px); line-height: 1.2; letter-spacing: -.048em; font-weight: 700; text-wrap: balance; }
        .signin-description { margin: 9px 0 24px; color: var(--muted); font-size: 13.5px; line-height: 1.6; }
        .login-form { display: flex; flex-direction: column; gap: 18px; }
        .field { display: block; }
        .field-heading { display: flex; align-items: center; justify-content: space-between; gap: 14px; margin-bottom: 8px; }
        .field-label { display: block; color: var(--text); font-size: 13px; font-weight: 600; }
        .field-control { position: relative; }
        .field-input {
            display: block; width: 100%; height: 46px;
            border: 1px solid var(--stroke); border-radius: 10px;
            background: var(--field); color: var(--text);
            padding: 0 14px; font-size: 14px; outline: none;
            transition: border-color .18s ease, box-shadow .18s ease, background-color .18s ease;
        }
        .field-input::placeholder { color: var(--muted-soft); opacity: 1; }
        .field-input:hover { border-color: #b2c3d3; }
        html.dark .field-input:hover { border-color: #5d7187; }
        .field-input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px var(--focus-ring); }
        .field-input.has-error { border-color: #d74747; }
        .field-input.has-error:focus { box-shadow: 0 0 0 4px rgba(215, 71, 71, .12); }
        .password-input { padding-right: 50px; }
        .reveal-password { position: absolute; top: 0; right: 4px; bottom: 0; display: grid; place-items: center; width: 42px; padding: 0; color: var(--muted); border: none; background: transparent; cursor: pointer; }
        .reveal-password:hover { color: var(--text); }
        .reveal-password svg { width: 18px; height: 18px; stroke: currentColor; stroke-width: 1.7; fill: none; stroke-linecap: round; stroke-linejoin: round; }
        .field-error { margin: 7px 0 0; color: #dc4b4b; font-size: 12px; line-height: 1.5; }
        .form-error-summary { border-left: 3px solid #dc4b4b; background: var(--surface-subtle); padding: 10px 13px; color: var(--text); border-radius: 0 7px 7px 0; font-size: 12.5px; line-height: 1.5; }
        .recover-details { position: relative; }
        .recover { color: var(--primary); font-size: 12px; font-weight: 600; cursor: pointer; list-style: none; }
        .recover::-webkit-details-marker { display: none; }
        .recover:hover { text-decoration: underline; text-underline-offset: 3px; }
        .recover-help {
            position: absolute; top: calc(100% + 10px); right: 0; z-index: 5;
            width: min(260px, 75vw); margin: 0; padding: 12px 14px;
            background: var(--surface); border: 1px solid var(--stroke);
            box-shadow: 0 10px 28px rgba(13, 31, 53, .11);
            border-radius: 9px; color: var(--muted);
            font-size: 12px; line-height: 1.55;
        }
        .remember-row { display: flex; align-items: center; margin-top: -2px; }
        .remember-option { display: inline-flex; align-items: center; gap: 10px; color: var(--muted); font-size: 12px; cursor: pointer; user-select: none; }
        .remember-option input { width: 15px; height: 15px; margin: 0; accent-color: var(--primary); cursor: pointer; }
        .submit-button {
            width: 100%; height: 46px; display: flex; align-items: center; justify-content: center; gap: 9px;
            padding: 0 18px; border: none; border-radius: 10px;
            color: white; background: var(--primary);
            font-size: 14px; font-weight: 700; cursor: pointer;
            transition: background-color .18s, transform .18s, box-shadow .18s;
        }
        .submit-button:hover:not(:disabled) { background: var(--primary-hover); box-shadow: 0 6px 16px rgba(4, 64, 119, .15); transform: translateY(-1px); }
        .submit-button:active:not(:disabled) { transform: translateY(0); box-shadow: none; }
        .submit-button:disabled { opacity: .68; cursor: progress; }
        .submit-arrow { width: 16px; height: 16px; stroke: currentColor; stroke-width: 1.7; fill: none; stroke-linecap: round; stroke-linejoin: round; }
        .spinner { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,.35); border-top-color: white; border-radius: 50%; animation: spin .7s linear infinite; }
        .access-assistance { display: flex; gap: 12px; margin-top: 26px; padding-top: 18px; border-top: 1px solid var(--stroke-soft); }
        .assistance-symbol { flex: none; display: grid; place-items: center; width: 30px; height: 30px; border: 1px solid var(--stroke-soft); border-radius: 8px; color: var(--primary); background: var(--surface-subtle); }
        .assistance-symbol svg { width: 16px; height: 16px; stroke: currentColor; stroke-width: 1.7; fill: none; stroke-linecap: round; stroke-linejoin: round; }
        .assistance-title { margin: 0; font-size: 12px; font-weight: 700; color: var(--text); }
        .assistance-description { margin: 4px 0 0; font-size: 11.5px; line-height: 1.55; color: var(--muted); }
        .signin-footer { display: flex; align-items: center; justify-content: space-between; gap: 16px; width: 100%; color: var(--muted-soft); font-size: 11px; line-height: 1.4; }
        .signin-footer span:last-child { text-align: right; }

        @keyframes gradient-drift {
            0% { transform: translate3d(-1.6%, -1%, 0) scale(1); }
            50% { transform: translate3d(1.4%, 1.2%, 0) scale(1.035); }
            100% { transform: translate3d(-.8%, 2%, 0) scale(1.055); }
        }
        @keyframes path-flow { to { stroke-dashoffset: -210; } }
        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width: 1199px) {
            /* .auth-shell { grid-template-columns: minmax(0, 1fr) 650px; } */
            .auth-shell { grid-template-columns: 65% 35%; }
            .hero-features { gap: 15px; }
            .signin-pane { padding: 24px 30px 20px; }
            .signin-center { max-width: 350px; }
        }
        @media (max-width: 1023px) {
            .auth-shell { display: block; }
            .hero { display: none; }
            .signin-pane { padding: 28px clamp(20px, 5vw, 44px) 24px; border-left: none; }
            .workspace-note { display: none; }
            .mobile-brand { display: inline-flex; }
            .signin-center { max-width: 360px; padding-block: 40px; }
            .signin-footer { max-width: 360px; margin-inline: auto; }
        }
        @media (max-width: 520px) {
            .signin-pane { padding: 18px 18px 20px; }
            .signin-center { padding-block: 28px; max-width: 100%; }
            .signin-heading { font-size: 24px; }
            .signin-description { margin-bottom: 20px; }
            .signin-footer { font-size: 10px; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; scroll-behavior: auto !important; transition-duration: .01ms !important; }
            .hero::before { will-change: auto; }
        }
    </style>
</head>

<body x-data="{
    dark: document.documentElement.classList.contains('dark'),
    toggleTheme() {
        this.dark = !this.dark;
        document.documentElement.classList.toggle('dark', this.dark);
        localStorage.setItem('whusnet-theme', this.dark ? 'dark' : 'light');
    }
}">
    <main class="auth-shell">
        <!-- Left: information hierarchy first, animated gradient second. -->
        <aside class="hero" aria-labelledby="hero-title">
            <svg class="network-lines" viewBox="0 0 650 700" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
                <path d="M-120 96 C100 140 165 20 330 155 S550 344 780 220" />
                <path d="M-120 147 C130 194 174 78 338 211 S556 400 780 277" />
                <path d="M-120 201 C115 235 200 130 346 269 S568 454 780 335" />
                <path d="M-120 257 C140 297 216 190 358 325 S580 510 780 392" />
                <path d="M-120 309 C122 364 215 250 363 384 S589 569 780 449" />
                <path class="signal" d="M-120 201 C115 235 200 130 346 269 S568 454 780 335" />
            </svg>

            <header class="hero-top">
                <div class="brand" aria-label="Ruang Kerja Whusnet">
                    <svg class="brand-symbol" viewBox="0 0 40 40" fill="none" aria-hidden="true">
                        <rect width="40" height="40" rx="10" fill="white" fill-opacity=".12" />
                        <path d="M8.5 13.5L14.8 27 20 16.4 25.2 27 31.5 13.5" stroke="#F1FBFF" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M17 10.5H23" stroke="#8EDCF7" stroke-width="2.5" stroke-linecap="round" />
                    </svg>
                    <span>
                        <span class="brand-wordmark"><span>RUANG KERJA</span> <span>WHUSNET</span></span>
                        <span class="brand-caption">OPERATIONAL PLATFORM</span>
                    </span>
                </div>
            </header>

            <section class="hero-body">
                <!-- <span class="hero-eyebrow">Dari pelanggan baru sampai jaringan menyala</span> -->
                <h1 id="hero-title">Satu tempat,<br><em>semua kerja lapangan dan tagihan selesai.</em></h1>
                <p class="hero-description">
                    Dari pendaftaran pelanggan, tagihan bulanan, sampai pantau kondisi jaringan ke tiap cabang semua bisa dikerjakan tanpa bongkar banyak aplikasi.
                </p>
                <div class="hero-features" aria-label="Fokus operasional Ruang Kerja Whusnet">
                    <div>
                        <div class="feature-line" aria-hidden="true"></div>
                        <h2 class="feature-title">Data pelanggan rapi</h2>
                        <p class="feature-desc">Semua riwayat dan paket tersimpan, tinggal dicari.</p>
                    </div>
                    <div>
                        <div class="feature-line" aria-hidden="true"></div>
                        <h2 class="feature-title">Tagihan jalan sendiri</h2>
                        <p class="feature-desc">Dari tagihan sampai pembayaran, tercatat otomatis.</p>
                    </div>
                    <div>
                        <div class="feature-line" aria-hidden="true"></div>
                        <h2 class="feature-title">Jaringan tiap cabang</h2>
                        <p class="feature-desc">Tahu kondisi tiap titik, tanpa harus turun ke lokasi.</p>
                    </div>
                </div>
            </section>

            <footer class="hero-bottom">
                <span>© 2026 Whusnet by Connexa Digital Network</span>
            </footer>
        </aside>

        <!-- Right: no ornamental cards; meaningful help text anchors the form. -->
        <section class="signin-pane" aria-labelledby="signin-title">
            <div class="signin-center">
                <span class="form-eyebrow">RUANG KERJA WHUSNET / SIGN IN</span>
                <h2 class="signin-heading" id="signin-title">Senang Anda kembali.</h2>
                <p class="signin-description">Masuk pakai akun kerja Anda, lanjutkan dari tempat terakhir Anda berhenti.</p>

                @if ($errors->any())
                    <p class="form-error-summary" role="alert">Login belum berhasil. Periksa kembali informasi akun Anda.</p>
                @endif

                <form action="{{ route('login') }}" method="POST" class="login-form"
                    x-data="{ submitting: false, showPassword: false }"
                    x-on:submit="submitting = true">
                    @csrf

                    <div class="field">
                        <div class="field-heading">
                            <label class="field-label" for="email">Email kerja</label>
                        </div>
                        <div class="field-control">
                            <input class="field-input @error('email') has-error @enderror"
                                id="email" name="email" type="email"
                                value="{{ old('email') }}" autocomplete="username" autofocus required
                                placeholder="nama@perusahaan.com"
                                @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                        </div>
                        @error('email')
                            <p id="email-error" class="field-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <div class="field-heading">
                            <label class="field-label" for="password">Kata sandi</label>
                            <details class="recover-details">
                                <summary class="recover">Lupa kata sandi?</summary>
                                <p class="recover-help">Lupa sandi? Minta admin atau tim support bantu buatkan yang baru.</p>
                            </details>
                        </div>
                        <div class="field-control">
                            <input class="field-input password-input @error('password') has-error @enderror"
                                id="password" name="password" type="password"
                                x-bind:type="showPassword ? 'text' : 'password'"
                                autocomplete="current-password" required placeholder="Masukkan kata sandi"
                                @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                            <button type="button" class="reveal-password"
                                x-on:click="showPassword = !showPassword"
                                title="Tampilkan / sembunyikan kata sandi"
                                x-bind:aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                                x-bind:aria-pressed="showPassword.toString()">
                                <svg x-show="!showPassword" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M2.3 12s3.5-6.5 9.7-6.5 9.7 6.5 9.7 6.5-3.5 6.5-9.7 6.5S2.3 12 2.3 12Z" /><circle cx="12" cy="12" r="3" />
                                </svg>
                                <svg x-show="showPassword" x-cloak viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M3 3l18 18M10.6 5.6A11.2 11.2 0 0 1 12 5.5c6.2 0 9.7 6.5 9.7 6.5a13 13 0 0 1-3.1 3.7M6.1 6.8A14.3 14.3 0 0 0 2.3 12s3.5 6.5 9.7 6.5c1.2 0 2.3-.2 3.3-.6M10 10a3 3 0 0 0 4 4" />
                                </svg>
                            </button>
                        </div>
                        @error('password')
                            <p id="password-error" class="field-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="remember-row">
                        <label class="remember-option" for="remember">
                            <input id="remember" type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                            <span>Ingat sesi saya</span>
                        </label>
                    </div>

                    <button class="submit-button" type="submit" x-bind:disabled="submitting">
                        <span x-show="!submitting">Masuk ke Workspace</span>
                        <svg x-show="!submitting" class="submit-arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h15m-6-6 6 6-6 6" /></svg>
                        <span x-show="submitting" x-cloak class="spinner" aria-hidden="true"></span>
                        <span x-show="submitting" x-cloak>Memverifikasi...</span>
                    </button>
                </form>

                <div class="access-assistance">
                    <span class="assistance-symbol" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4" /></svg>
                    </span>
                    <div>
                        <p class="assistance-title">Akses khusus tim internal</p>
                        <p class="assistance-description">Belum memiliki akun atau mengalami kendala masuk? Hubungi administrator Ruang Kerja Whusnet untuk mendapatkan bantuan.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <x-toast />
</body>
</html>
