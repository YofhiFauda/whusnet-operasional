<!DOCTYPE html>
<html lang="id" style="overflow-x: hidden;">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-url" content="{{ rtrim(config('app.url'), '/') }}">
    @auth
        <meta name="user-id" content="{{ Auth::id() }}">
    @endauth
    <title>FinanceOps</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <meta name="notif-unread-url" content="{{ route('notifications.unreadCount') }}">
    <link href="https://unpkg.com/nprogress@0.2.0/nprogress.css" rel="stylesheet">
    <script src="https://unpkg.com/nprogress@0.2.0/nprogress.js"></script>
    @stack('styles')
    <style>
        /* NProgress Override */
        #nprogress .bar {
            background: #3b82f6 !important;
            height: 3px !important;
        }

        #nprogress .peg {
            box-shadow: 0 0 10px #3b82f6, 0 0 5px #3b82f6 !important;
        }

        #nprogress .spinner-icon {
            border-top-color: #3b82f6 !important;
            border-left-color: #3b82f6 !important;
        }

        /* Page Transition Animations */
        .page-enter {
            opacity: 0;
            animation: pageFadeIn 0.3s ease-out forwards;
        }

        @keyframes pageFadeIn {
            from {
                opacity: 0;
                margin-top: 8px;
            }

            to {
                opacity: 1;
                margin-top: 0;
            }
        }

        /* Sidebar Collapse Animations & Styles */
        @media (min-width: 768px) {
            #mobile-sidebar {
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                will-change: width;
            }

            #mobile-sidebar.sidebar-collapsed {
                width: 88px !important;
            }

            /* Smoothly hide text labels */
            .sidebar-text,
            .financeops-text,
            .profile-details,
            #pengeluaran-lain-chevron,
            #sidebarProfileChevron,
            #mobile-sidebar nav p.text-xs {
                transition: opacity 0.3s ease, transform 0.3s ease, width 0.3s ease, margin 0.3s ease;
                opacity: 1;
                transform: translateX(0);
                white-space: nowrap;
                display: inline-block;
            }

            #mobile-sidebar.sidebar-collapsed .sidebar-text,
            #mobile-sidebar.sidebar-collapsed .financeops-text,
            #mobile-sidebar.sidebar-collapsed .profile-details,
            #mobile-sidebar.sidebar-collapsed #pengeluaran-lain-chevron,
            #mobile-sidebar.sidebar-collapsed #sidebarProfileChevron,
            #mobile-sidebar.sidebar-collapsed nav p.text-xs {
                opacity: 0;
                transform: translateX(-10px);
                width: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                pointer-events: none;
                overflow: hidden;
                flex: 0 0 0 !important;
                /* Force flex-1 to collapse */
            }

            /* Adjust nav items layout */
            #mobile-sidebar nav {
                transition: padding 0.3s ease;
                display: flex;
                flex-direction: column;
                align-items: stretch;
            }

            #mobile-sidebar.sidebar-collapsed nav {
                padding-left: 0 !important;
                padding-right: 0 !important;
                align-items: center;
            }

            #mobile-sidebar nav a,
            #mobile-sidebar button#pengeluaran-lain-toggle {
                transition: all 0.3s ease;
                overflow: hidden;
                display: flex;
                align-items: center;
            }

            #mobile-sidebar.sidebar-collapsed a,
            #mobile-sidebar.sidebar-collapsed button#pengeluaran-lain-toggle,
            #mobile-sidebar.sidebar-collapsed #sidebarProfileBtn {
                justify-content: center !important;
                padding: 0 !important;
                margin-left: auto !important;
                margin-right: auto !important;
                width: 48px;
                height: 48px;
                gap: 0 !important;
                border-radius: 12px;
            }

            #mobile-sidebar.sidebar-collapsed .financeops-logo-container {
                justify-content: center !important;
                padding: 1rem 0 !important;
                gap: 0 !important;
            }

            #mobile-sidebar.sidebar-collapsed i[data-lucide] {
                margin: 0 !important;
                transition: transform 0.3s ease;
                width: 20px;
                height: 20px;
                flex-shrink: 0;
            }

            /* Special handling for pengeluaran lain menu */
            #mobile-sidebar.sidebar-collapsed #pengeluaran-lain-menu {
                padding: 0 !important;
                margin: 4px 0 8px 0 !important;
                border: none !important;
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 8px;
                width: 100%;
            }

            #mobile-sidebar.sidebar-collapsed #pengeluaran-lain-menu a {
                width: 40px;
                height: 40px;
                padding: 0 !important;
                justify-content: center;
                margin: 0 !important;
            }

            #mobile-sidebar.sidebar-collapsed .notif-badge-sidebar {
                position: absolute;
                top: 4px;
                right: 4px;
                transform: scale(0.75);
                min-width: 18px;
                height: 18px;
                font-size: 9px;
            }
        }
    </style>

    <style>
        /* Profile Card Styles */
        .profile-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
        }

        .profile-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border-color: #cbd5e1;
        }

        .profile-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e2e8f0;
            flex-shrink: 0;
        }

        .profile-info {
            flex: 1;
            min-width: 0;
        }

        .profile-name {
            font-size: 15px;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
            line-height: 1.3;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .profile-email {
            font-size: 13px;
            color: #64748b;
            margin: 2px 0 0 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Logout Button Styles */
        .logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px 16px;
            margin-top: 12px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s ease;
            text-decoration: none;
            cursor: pointer;
            /* Blue Gradient Outline */
            border: 2px solid transparent;
            background: linear-gradient(#ffffff, #ffffff) padding-box,
                linear-gradient(135deg, #3b82f6, #8b5cf6) border-box;
            color: #3b82f6;
        }

        .logout-btn:hover,
        .logout-btn.active {
            background: #eef2ff;
            color: #4f46e5;
            border-color: #eef2ff;
        }

        .logout-btn:active {
            transform: scale(0.98);
        }

        .logout-btn i {
            width: 18px;
            height: 18px;
        }

        /* Mobile Responsive */
        @media (max-width: 640px) {
            .profile-card {
                padding: 12px 16px;
                gap: 12px;
            }

            .profile-avatar {
                width: 40px;
                height: 40px;
            }

            .profile-name {
                font-size: 14px;
            }

            .profile-email {
                font-size: 12px;
            }

            .logout-btn {
                padding: 10px 14px;
                font-size: 13px;
                margin-top: 10px;
            }

            .logout-btn i {
                width: 16px;
                height: 16px;
            }
        }

        /* Extra Small Mobile */
        @media (max-width: 380px) {
            .profile-card {
                padding: 10px 14px;
                gap: 10px;
            }

            .profile-avatar {
                width: 36px;
                height: 36px;
            }

            .profile-name {
                font-size: 13px;
            }

            .profile-email {
                font-size: 11px;
            }

            .logout-btn {
                padding: 9px 12px;
                font-size: 12px;
            }
        }

        /* Notification Badge Styles */
        .notif-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            border-radius: 9999px;
            background: linear-gradient(135deg, #ef4444, #f43f5e);
            color: #fff;
            font-size: 11px;
            font-weight: 800;
            line-height: 1;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
            animation: badgePulse 2s ease-in-out infinite;
            transition: all 0.3s ease;
        }

        .notif-badge.badge-hidden {
            display: none;
        }

        .notif-badge-mobile {
            position: absolute;
            top: 2px;
            right: 2px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 18px;
            height: 18px;
            padding: 0 4px;
            border-radius: 9999px;
            background: linear-gradient(135deg, #ef4444, #f43f5e);
            color: #fff;
            font-size: 10px;
            font-weight: 800;
            line-height: 1;
            border: 2px solid #fff;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
            animation: badgePulse 2s ease-in-out infinite;
            transition: all 0.3s ease;
        }

        .notif-badge-mobile.badge-hidden {
            display: none;
        }

        .notif-badge-sidebar {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 22px;
            height: 22px;
            padding: 0 6px;
            border-radius: 9999px;
            background: linear-gradient(135deg, #ef4444, #f43f5e);
            color: #fff;
            font-size: 11px;
            font-weight: 800;
            line-height: 1;
            margin-left: auto;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
            animation: badgePulse 2s ease-in-out infinite;
            transition: all 0.3s ease;
        }

        .notif-badge-sidebar.badge-hidden {
            display: none;
        }

        @keyframes badgePulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }
        }

        /* Custom Thin Scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #e2e8f0;
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #cbd5e1;
        }

        /* Modal & Keyboard Stability Improvements */
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.95) translateY(10px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .animate-modal {
            animation: modalFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes cardStagger {
            from {
                opacity: 0;
                transform: translateY(12px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stagger-item {
            animation: cardStagger 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
    </style>
</head>

<body
    class="font-sans bg-gradient-to-br from-slate-50 to-slate-100 antialiased min-h-screen flex flex-col overflow-x-hidden w-full max-w-full"
    style="max-width: 100%; width: 100%;">

    @if(Auth::user()->role === 'teknisi')
        {{-- ===================== LAYOUT TEKNISI: TOP NAVBAR ===================== --}}
        <nav class="bg-white border-b border-slate-200 sticky top-0 z-50">
            <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">

                    {{-- 1. Brand / Logo --}}
                    <div class="flex items-center gap-2 sm:gap-3">
                        <div
                            class="w-8 h-8 rounded-lg bg-linear-to-r from-sky-600 to-sky-500 flex items-center justify-center shadow-md shrink-0">
                            <i data-lucide="receipt" class="w-4 h-4 text-white"></i>
                        </div>
                        <span
                            class="font-bold text-lg sm:text-xl text-slate-800 tracking-tight hidden xs:block">FinanceOps</span>
                    </div>

                    {{-- 2. Menu & Profile Area --}}
                    <div class="flex items-center gap-1 sm:gap-4">

                        {{-- === MENU NAVIGASI === --}}

                        {{-- A. Tampilan Desktop/Tablet (Teks + Icon) --}}
                        <div class="hidden sm:flex items-center gap-2 mr-2 border-r border-slate-200 pr-4">
                            <a href="{{ route('transactions.create') }}"
                                class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold transition-colors
                                {{ request()->routeIs('transactions.create') ? 'bg-linear-to-r from-sky-600 to-sky-500 text-white' : 'text-slate-600 hover:bg-slate-50' }}">
                                <i data-lucide="file-up" class="w-4 h-4"></i> Input Transaksi
                            </a>
                            <a href="{{ route('transactions.index') }}"
                                class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold transition-colors
                                {{ request()->routeIs('transactions.index') ? 'bg-linear-to-r from-sky-600 to-sky-500 text-white' : 'text-slate-600 hover:bg-slate-50' }}">
                                <i data-lucide="clock" class="w-4 h-4"></i> Daftar Transaksi
                            </a>
                            <a href="{{ route('notifications.index') }}"
                                class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold transition-colors relative
                                {{ request()->routeIs('notifications.index') ? 'bg-linear-to-r from-sky-600 to-sky-500 text-white' : 'text-slate-600 hover:bg-slate-50' }}">
                                <i data-lucide="bell" class="w-4 h-4"></i> Notifikasi
                                <span id="notif-count-desktop" class="notif-badge badge-hidden">0</span>
                            </a>
                        </div>

                        {{-- B. Tampilan Mobile/Smartphone (Hanya Icon) --}}
                        <div class="flex sm:hidden items-center gap-0.5 mr-0">
                            <a href="{{ route('transactions.create') }}"
                                class="flex flex-col items-center justify-center w-9 h-9 rounded-xl transition-colors relative
                                {{ request()->routeIs('transactions.create') ? 'text-sky-600 bg-sky-50' : 'text-slate-500 hover:bg-slate-100' }}">
                                <i data-lucide="file-up" class="w-4.5 h-4.5"></i>
                                @if(request()->routeIs('transactions.create'))
                                    <span class="absolute bottom-1 w-1 h-1 bg-blue-600 rounded-full"></span>
                                @endif
                            </a>

                            <a href="{{ route('transactions.index') }}"
                                class="flex flex-col items-center justify-center w-9 h-9 rounded-xl transition-colors relative
                                {{ request()->routeIs('transactions.index') ? 'text-sky-600 bg-sky-50' : 'text-slate-500 hover:bg-slate-100' }}">
                                <i data-lucide="clock" class="w-4.5 h-4.5"></i>
                                @if(request()->routeIs('transactions.index'))
                                    <span class="absolute bottom-1 w-1 h-1 bg-blue-600 rounded-full"></span>
                                @endif
                            </a>

                            <a href="{{ route('notifications.index') }}"
                                class="flex flex-col items-center justify-center w-9 h-9 rounded-xl transition-colors relative
                                {{ request()->routeIs('notifications.index') ? 'text-sky-600 bg-sky-50' : 'text-slate-500 hover:bg-slate-100' }}">
                                <i data-lucide="bell" class="w-4.5 h-4.5"></i>
                                <span id="notif-count-mobile" class="notif-badge-mobile badge-hidden">0</span>
                            </a>
                        </div>

                        {{-- 3. Profile Dropdown dengan Card Style --}}
                        <div class="relative">
                            <button id="profileBtn" onclick="window.toggleProfileDropdown(event)"
                                class="flex items-center gap-1.5 p-0.5 pr-1 rounded-full hover:bg-slate-100 transition-all outline-none border border-transparent hover:border-slate-200 cursor-pointer pointer-events-auto">
                                <div class="hidden sm:block text-right">
                                    <p class="text-sm font-bold text-slate-800 leading-tight">{{ Auth::user()->name }}</p>
                                    <p class="text-[10px] font-medium text-slate-500 uppercase tracking-wider">Teknisi</p>
                                </div>

                                <div
                                    class="w-9 h-9 rounded-full bg-linear-to-r from-sky-600 to-sky-500 flex items-center justify-center text-white font-bold shadow-inner text-xs border-2 border-white">
                                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                </div>
                            </button>

                            {{-- DROPDOWN PROFILE CARD --}}
                            <div id="profileDropdown"
                                class="hidden absolute right-0 mt-3 w-72 bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden origin-top-right z-50">

                                {{-- Header: Avatar + Name + Email --}}
                                <div class="px-4 py-4 flex items-center gap-3 border-b border-slate-100">
                                    @if(Auth::user()->avatar)
                                        <img src="{{ asset('storage/' . Auth::user()->avatar) }}" alt="{{ Auth::user()->name }}"
                                            class="w-12 h-12 rounded-full object-cover border-2 border-white shadow">
                                    @else
                                        <div
                                            class="w-12 h-12 rounded-full bg-linear-to-r from-sky-600 to-sky-500 flex items-center justify-center text-white font-bold text-lg shrink-0">
                                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                        </div>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <p class="font-bold text-slate-800 text-[15px] leading-tight truncate">
                                            {{ Auth::user()->name }}</p>
                                        <p class="text-xs text-slate-500 truncate mt-0.5">{{ Auth::user()->email }}</p>
                                    </div>
                                </div>

                                {{-- Menu Items --}}
                                <div class="py-2">
                                    {{-- <a href=""
                                        class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                                        <i data-lucide="user" class="w-[18px] h-[18px] text-slate-500"></i>
                                        <span>Profil Saya</span>
                                    </a> --}}
                                    <button type="button" onclick="openBankAccountsModal({{ Auth::id() }})"
                                        class="w-full flex items-center gap-3 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors text-left">
                                        <i data-lucide="credit-card" class="w-[18px] h-[18px] text-slate-500"></i>
                                        <span>Rekening Saya</span>
                                    </button>
                                    {{-- <a href=""
                                        class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                                        <i data-lucide="settings" class="w-[18px] h-[18px] text-slate-500"></i>
                                        <span>Pengaturan</span>
                                    </a> --}}
                                </div>

                                {{-- Logout --}}
                                <div class="border-t border-slate-100 py-2">
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit"
                                            class="w-full flex items-center gap-3 px-4 py-2.5 text-sm font-semibold text-rose-600 hover:bg-rose-50 transition-colors">
                                            <i data-lucide="log-out" class="w-[18px] h-[18px]"></i>
                                            <span>Keluar (Logout)</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        {{-- Konten Halaman --}}
        {{-- <main class="max-w-7xl mx-auto px-1 sm:px-6 lg:px-8 py-1 page-enter"> --}}
            <main class="w-full mx-auto page-enter overflow-x-hidden">
                @yield('content')
            </main>

    @else
            {{-- ===================== LAYOUT ADMIN/ATASAN/OWNER: SIDEBAR ===================== --}}
            <div class="flex h-screen overflow-hidden">
                <div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden"
                    onclick="toggleMobileSidebar()"></div>

                <aside id="mobile-sidebar" class="fixed md:static inset-y-0 left-0 w-72 bg-white border-r border-slate-200 
                text-slate-700 flex flex-col shrink-0 z-50 
                transform -translate-x-full md:translate-x-0 transition-transform duration-300">

                    <div class="p-4 flex items-center gap-3 border-b border-slate-100 financeops-logo-container">
                        <div
                            class="w-10 h-10 rounded-xl bg-linear-to-r from-sky-600 to-sky-500 flex items-center justify-center shadow-md shrink-0">
                            <i data-lucide="receipt" class="w-5 h-5 text-white"></i>
                        </div>
                        <h1 class="font-bold text-lg text-slate-800 financeops-text">FinanceOps</h1>
                    </div>

                    <nav
                        class="flex-1 px-4 md:px-6 py-6 space-y-2 overflow-y-auto min-h-0 custom-scrollbar text-xs sm:text-sm">
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2 ml-2">Menu Utama</p>

                        <a href="{{ route('dashboard') }}"
                            class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all shrink-0
                        {{ request()->routeIs('dashboard') ? 'bg-linear-to-r from-sky-600 to-sky-500 text-white shadow-lg' : 'hover:bg-slate-100 text-slate-600' }}">
                            <i data-lucide="home" class="w-5 h-5 shrink-0"></i> <span class="sidebar-text">Dashboard</span>
                        </a>

                        <a href="{{ route('transactions.create') }}"
                            class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all shrink-0
                        {{ request()->routeIs('transactions.create') ? 'bg-linear-to-r from-sky-600 to-sky-500 text-white shadow-lg' : 'hover:bg-slate-100 text-slate-600' }}">
                            <i data-lucide="file-up" class="w-5 h-5 shrink-0"></i> <span class="sidebar-text">Input
                                Rembush dan Pengajuan</span>
                        </a>

                        @if(in_array(Auth::user()->role, ['atasan', 'owner']))
                            <a href="{{ route('pembelian.loading') }}"
                                class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all shrink-0
                            {{ request()->routeIs('pembelian.*') ? 'bg-linear-to-r from-sky-600 to-sky-500 text-white shadow-lg' : 'hover:bg-slate-100 text-slate-600' }}">
                                <i data-lucide="package" class="w-5 h-5 shrink-0"></i> <span class="sidebar-text">Input
                                    Pembelian (Gudang) </span>
                            </a>
                        @endif

                        {{-- ▼ Input Pengeluaran Lain (dropdown) - admin, atasan, owner --}}
                        @if(in_array(Auth::user()->role, ['admin', 'atasan', 'owner']))
                            @php
                                $isPengeluaranLain = request()->is('pengeluaran-lain/*');
                            @endphp
                            <div>
                                <button id="pengeluaran-lain-toggle" onclick="togglePengeluaranLain()"
                                    class="w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all shrink-0
                                {{ $isPengeluaranLain ? 'bg-indigo-50 text-indigo-700' : 'hover:bg-slate-100 text-slate-600' }}">
                                    <i data-lucide="wallet" class="w-5 h-5 flex-shrink-0"></i>
                                    <span class="flex-1 text-left sidebar-text">Input Pengeluaran Lain</span>
                                    <i data-lucide="chevron-down" id="pengeluaran-lain-chevron"
                                        class="w-5 h-5 shrink-0 transition-transform duration-200 {{ $isPengeluaranLain ? 'rotate-180' : '' }}"></i>
                                </button>
                                <div id="pengeluaran-lain-menu"
                                    class="mt-1 ml-4 pl-4 border-l-2 border-slate-200 space-y-1 {{ $isPengeluaranLain ? '' : 'hidden' }}">
                                    <a href="{{ route('pengeluaran-lain.bayar-hutang.index') }}"
                                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all shrink-0
                                    {{ request()->is('pengeluaran-lain/bayar-hutang*') ? 'bg-red-50 text-red-600' : 'hover:bg-slate-100 text-slate-600' }}">
                                        <i data-lucide="credit-card" class="w-5 h-5 flex-shrink-0"></i> <span
                                            class="sidebar-text">Bayar Hutang</span>
                                    </a>
                                    <a href="{{ route('pengeluaran-lain.piutang-usaha.index') }}"
                                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all shrink-0
                                    {{ request()->is('pengeluaran-lain/piutang-usaha*') ? 'bg-blue-50 text-blue-600' : 'hover:bg-slate-100 text-slate-600' }}">
                                        <i data-lucide="trending-up" class="w-5 h-5 flex-shrink-0"></i> <span
                                            class="sidebar-text">Piutang Usaha</span>
                                    </a>
                                    @if(in_array(Auth::user()->role, ['atasan', 'owner']))
                                        <a href="{{ route('pengeluaran-lain.prive.index') }}"
                                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all shrink-0
                                        {{ request()->is('pengeluaran-lain/prive*') ? 'bg-purple-50 text-purple-600' : 'hover:bg-slate-100 text-slate-600' }}">
                                            <i data-lucide="user-check" class="w-5 h-5 flex-shrink-0"></i> <span
                                                class="sidebar-text">Prive</span>
                                        </a>
                                    @endif
                                    <a href="{{ route('pengeluaran-lain.gaji.index') }}"
                                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all shrink-0
                                    {{ request()->is('pengeluaran-lain/gaji*') ? 'bg-green-50 text-green-600' : 'hover:bg-slate-100 text-slate-600' }}">
                                        <i data-lucide="banknote" class="w-5 h-5 flex-shrink-0"></i> <span
                                            class="sidebar-text">Gaji</span>
                                    </a>
                                </div>
                            </div>
                        @endif

                        <a href="{{ route('transactions.index') }}"
                            class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all shrink-0
                        {{ request()->routeIs('transactions.index') ? 'bg-linear-to-r from-sky-600 to-sky-500 text-white shadow-lg' : 'hover:bg-slate-100 text-slate-600' }}">
                            <i data-lucide="clock" class="w-5 h-5 shrink-0"></i> <span class="sidebar-text">Daftar
                                Transaksi</span>
                        </a>

                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2 ml-2 mt-4">Administrasi
                        </p>
                        @if(in_array(Auth::user()->role, ['admin', 'atasan', 'owner']))
                            <a href="{{ route('users.index') }}"
                                class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all shrink-0
                            {{ request()->routeIs('users.*') ? 'bg-linear-to-r from-sky-600 to-sky-500 text-white shadow-lg' : 'hover:bg-slate-100 text-slate-600' }}">
                                <i data-lucide="users" class="w-5 h-5 shrink-0"></i> <span class="sidebar-text">Kelola
                                    Pengguna</span>
                            </a>

                            <a href="{{ route('branches.index') }}"
                                class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all shrink-0
                            {{ request()->routeIs('branches.*') ? 'bg-linear-to-r from-sky-600 to-sky-500 text-white shadow-lg' : 'hover:bg-slate-100 text-slate-600' }}">
                                <i data-lucide="building-2" class="w-5 h-5 shrink-0"></i> <span class="sidebar-text">Kelola
                                    Cabang</span>
                            </a>

                            <a href="{{ route('transaction-categories.index') }}"
                                class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all shrink-0
                            {{ request()->routeIs('transaction-categories.*') ? 'bg-linear-to-r from-sky-600 to-sky-500 text-white shadow-lg' : 'hover:bg-slate-100 text-slate-600' }}">
                                <i data-lucide="tags" class="w-5 h-5 shrink-0"></i> <span class="sidebar-text">Kelola
                                    Kategori</span>
                            </a>

                            @if(Auth::user()->role === 'owner')
                                <a href="{{ route('price-index.index') }}"
                                    class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all shrink-0
                                {{ request()->routeIs('price-index.*') ? 'bg-linear-to-r from-sky-600 to-sky-500 text-white shadow-lg' : 'hover:bg-slate-100 text-slate-600' }}">
                                    <i data-lucide="bookmark" class="w-5 h-5 shrink-0"></i> <span class="sidebar-text">Referensi
                                        Harga</span>
                                </a>
                            @endif

                            <a href="{{ route('activity-logs.index') }}"
                                class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all shrink-0
                            {{ request()->routeIs('activity-logs.index') ? 'bg-linear-to-r from-sky-600 to-sky-500 text-white shadow-lg' : 'hover:bg-slate-100 text-slate-600' }}">
                                <i data-lucide="file-text" class="w-5 h-5 shrink-0"></i> <span class="sidebar-text">Log
                                    Aktivitas</span>
                            </a>

                            <a href="{{ route('notifications.index') }}"
                                class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all relative shrink-0
                            {{ request()->routeIs('notifications.index') ? 'bg-linear-to-r from-sky-600 to-sky-500 text-white shadow-lg' : 'hover:bg-slate-100 text-slate-600' }}">
                                <i data-lucide="bell" class="w-5 h-5 shrink-0"></i> <span class="sidebar-text">Notifikasi</span>
                                <span id="notif-count-sidebar" class="notif-badge-sidebar badge-hidden">0</span>
                            </a>
                        @endif
                    </nav>

                    <div class="p-4 md:p-6 relative">
                        {{-- Dropdown menu (muncul di ATAS card) --}}
                        <div id="sidebarProfileDropdown"
                            class="hidden absolute left-4 md:left-6 w-64 md:w-60 bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden z-[60]"
                            style="bottom: 80%; margin-bottom: 8px; top: auto;">

                            {{-- Header --}}
                            <div class="px-4 py-4 flex items-center gap-3 border-b border-slate-100">
                                @if(Auth::user()->avatar)
                                    <img src="{{ asset('storage/' . Auth::user()->avatar) }}" alt="{{ Auth::user()->name }}"
                                        class="w-9 h-9 rounded-full object-cover border-2 border-white shadow">
                                @else
                                    <div
                                        class="w-9 h-9 rounded-full bg-linear-to-r from-sky-600 to-sky-500 flex items-center justify-center text-white font-bold shrink-0">
                                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                    </div>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <p class="font-bold text-slate-800 text-sm leading-tight truncate">
                                        {{ Auth::user()->name }}</p>
                                    <p class="text-xs text-slate-500 truncate mt-0.5">{{ Auth::user()->email }}</p>
                                </div>
                            </div>

                            {{-- Menu --}}
                            <div class="py-2">
                                {{-- <a href=""
                                    class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                                    <i data-lucide="user" class="w-[18px] h-[18px] text-slate-500"></i>
                                    <span>Profil Saya</span>
                                </a>
                                <a href=""
                                    class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                                    <i data-lucide="settings" class="w-[18px] h-[18px] text-slate-500"></i>
                                    <span>Pengaturan</span>
                                </a> --}}
                            </div>

                            {{-- Logout --}}
                            <div class="border-t border-slate-100 py-2">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit"
                                        class="w-full flex items-center gap-3 px-4 py-2.5 text-sm font-semibold text-rose-600 hover:bg-rose-50 transition-colors">
                                        <i data-lucide="log-out" class="w-[18px] h-[18px]"></i>
                                        <span>Keluar (Logout)</span>
                                    </button>
                                </form>
                            </div>
                        </div>

                        {{-- Profile Card Trigger Button --}}
                        <button type="button" id="sidebarProfileBtn" onclick="toggleSidebarProfile(event)"
                            class="w-full flex items-center gap-3 p-3 rounded-2xl bg-white border border-slate-200 hover:border-indigo-300 hover:shadow-md transition-all">
                            @if(Auth::user()->avatar)
                                <img src="{{ asset('storage/' . Auth::user()->avatar) }}" alt="{{ Auth::user()->name }}"
                                    class="w-9 h-9 rounded-full object-cover shrink-0">
                            @else
                                <div
                                    class="w-9 h-9 rounded-full bg-linear-to-r from-sky-600 to-sky-500 flex items-center justify-center text-white font-bold shrink-0">
                                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                </div>
                            @endif
                            <div class="flex-1 min-w-0 text-left profile-details">
                                <p class="font-bold text-slate-800 text-sm leading-tight truncate">{{ Auth::user()->name }}
                                </p>
                                <p class="text-xs text-slate-500 truncate mt-0.5">{{ ucfirst(Auth::user()->role) }}</p>
                            </div>
                            <i data-lucide="chevron-up" id="sidebarProfileChevron"
                                class="w-4 h-4 text-slate-400 transition-transform shrink-0"></i>
                        </button>
                    </div>
                </aside>

                @php
                    $hideHeaderOnPc = false; // Always show header on PC now for Breadcrumbs and sidebar toggle
                @endphp

                <main class="flex-1 flex flex-col min-w-0 bg-gray-50/50 overflow-hidden page-enter">
                    <header
                        class="bg-white/80 backdrop-blur-xl sticky top-0 z-20 px-4 md:px-6 py-3 md:py-4 flex justify-between items-center border-b border-gray-100 shrink-0">
                        <div class="flex items-center gap-3 md:gap-4 min-w-0 flex-1">
                            {{-- Mobile Sidebar Toggle --}}
                            <button onclick="toggleMobileSidebar()"
                                class="md:hidden p-2 hover:bg-slate-100 rounded-lg transition-colors text-slate-600">
                                <i data-lucide="menu" class="w-5 h-5"></i>
                            </button>
                            {{-- Desktop Sidebar Toggle --}}
                            <button onclick="toggleDesktopSidebar()"
                                class="hidden md:flex p-2 hover:bg-slate-100 rounded-lg transition-colors text-slate-500">
                                <i data-lucide="menu" class="w-5 h-5"></i>
                            </button>

                            <div class="flex items-center min-w-0 flex-1">
                                {{-- Breadcrumb: Sembunyi di Mobile (hidden), Tampil di Tablet/Desktop (md:flex) --}}
                                <nav class="hidden md:flex text-sm font-medium text-slate-500" aria-label="Breadcrumb">
                                    <ol class="inline-flex items-center space-x-1 md:space-x-2">
                                        <li class="inline-flex items-center">
                                            <a href="{{ route('dashboard') }}"
                                                class="inline-flex items-center hover:text-indigo-600 transition-colors text-xs">
                                                <i data-lucide="home" class="w-4 h-4 mr-1.5"></i>
                                                Home
                                            </a>
                                        </li>
                                        @hasSection('breadcrumb')
                                            @yield('breadcrumb')
                                        @else
                                            <li>
                                                <div class="flex items-center">
                                                    <i data-lucide="chevron-right" class="w-4 h-4 mx-1.5 text-slate-400"></i>
                                                    <span
                                                        class="text-slate-800 font-reguler text-xs">@yield('page-title', 'Halaman')</span>
                                                </div>
                                            </li>
                                        @endif
                                    </ol>
                                </nav>

                                {{-- Page Title: Disembunyikan sepenuhnya (diganti menjadi "hidden") --}}
                                <h2 class="hidden text-lg font-bold text-slate-900 tracking-tight truncate">
                                    @yield('page-title', '')
                                </h2>
                            </div>
                        </div>
                    </header>

                    {{-- <div class="flex-1 overflow-y-auto lg:p-6"> --}}
                        <div class="flex-1 overflow-y-auto scrollbar-hide">
                            <div class="max-w-8xl mx-auto">
                                @yield('content')
                            </div>
                        </div>
                </main>
            </div>
        @endif

        {{-- ===================== BANK ACCOUNTS MODAL (Shared for Technicians) ===================== --}}
        <div id="bankAccountsModal" class="fixed inset-0 z-[60] hidden overflow-hidden">
            {{-- Overlay with improved glassmorphism --}}
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-[2px] transition-opacity duration-300"
                onclick="closeBankAccountsModal()"></div>

            {{-- Modal Content: Centered on PC, Bottom sheet feel on Mobile --}}
            <div
                class="absolute inset-x-0 bottom-0 sm:inset-0 flex items-end sm:items-center justify-center p-0 sm:p-4 pointer-events-none">
                <div class="bg-white w-full sm:max-w-xl rounded-t-[2.5rem] sm:rounded-[2rem] shadow-2xl pointer-events-auto transform transition-all duration-300 scale-95 opacity-0 flex flex-col max-h-[90dvh] sm:max-h-[85vh] overflow-hidden"
                    id="bankAccountsModalContent">

                    {{-- Pull Bar for Mobile (Visual only) --}}
                    <div class="sm:hidden flex justify-center pt-3 pb-1 shrink-0">
                        <div class="w-12 h-1.5 bg-slate-200 rounded-full"></div>
                    </div>

                    {{-- Header --}}
                    <div
                        class="px-5 py-4 sm:px-8 sm:py-6 border-b border-slate-100 flex items-center justify-between shrink-0">
                        <div class="flex items-center gap-3 sm:gap-4">
                            <div
                                class="w-10 h-10 sm:w-12 sm:h-12 rounded-2xl bg-indigo-50 flex items-center justify-center text-indigo-600 shrink-0">
                                <i data-lucide="credit-card" class="w-5 h-5 sm:w-6 sm:h-6"></i>
                            </div>
                            <div>
                                <h3 class="text-lg sm:text-xl font-black text-slate-800 leading-tight">Daftar Rekening
                                </h3>
                                <p class="text-[11px] sm:text-sm font-medium text-slate-500">Kelola rekening bank atau
                                    e-wallet</p>
                            </div>
                        </div>
                        <button onclick="closeBankAccountsModal()"
                            class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl hover:bg-slate-50 flex items-center justify-center text-slate-400 hover:text-slate-600 transition-colors shrink-0">
                            <i data-lucide="x" class="w-5 h-5 sm:w-6 sm:h-6"></i>
                        </button>
                    </div>

                    {{-- List Section --}}
                    <div class="flex-1 overflow-y-auto px-5 py-4 sm:px-8 sm:py-6 custom-scrollbar"
                        id="bankAccountsListContainer">
                        <div id="bankAccountsLoading" class="flex flex-col items-center justify-center py-10">
                            <div
                                class="w-8 h-8 border-3 border-indigo-100 border-t-indigo-600 rounded-full animate-spin">
                            </div>
                            <p class="mt-3 text-[11px] font-bold text-slate-400 uppercase tracking-widest">Memuat
                                data...</p>
                        </div>
                        <div id="bankAccountsList" class="space-y-3 sm:space-y-4 hidden">
                            {{-- Dynamically populated --}}
                        </div>
                        <div id="bankAccountsEmpty"
                            class="hidden flex flex-col items-center justify-center py-10 text-center">
                            <div
                                class="w-14 h-14 rounded-2xl bg-slate-50 flex items-center justify-center text-slate-300 mb-4">
                                <i data-lucide="wallet" class="w-7 h-7"></i>
                            </div>
                            <p class="text-slate-500 font-bold text-sm">Belum ada rekening</p>
                            <p class="text-slate-400 text-[10px] mt-1">Tambahkan rekening untuk mempermudah transaksi
                            </p>
                        </div>
                    </div>

                    {{-- Form Section --}}
                    <div id="bankAccountFormContainer"
                        class="hidden px-5 py-5 sm:px-8 sm:py-7 border-t border-slate-100 bg-slate-50/50">
                        <h4 class="text-[11px] sm:text-sm font-black text-slate-800 uppercase tracking-wider mb-4"
                            id="formTitle">Tambah Rekening Baru</h4>
                        <form id="bankAccountForm" onsubmit="saveBankAccount(event)" class="space-y-3 sm:space-y-4">
                            <input type="hidden" id="bank_account_id">
                            <input type="hidden" id="target_user_id">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4">
                                <div>
                                    <label
                                        class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5 ml-1">Bank
                                        / E-Wallet</label>
                                    <input type="text" id="modal_bank_name" name="modal_bank_name" required
                                        placeholder="Contoh: BCA, MANDIRI, DANA"
                                        class="w-full px-4 py-3 sm:py-3.5 rounded-xl border border-slate-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition-all font-bold text-slate-800 text-sm sm:text-base placeholder:text-slate-300 uppercase">
                                </div>
                                <div>
                                    <label
                                        class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5 ml-1">Nomor
                                        Rekening</label>
                                    <input type="text" id="modal_account_number" name="modal_account_number" required
                                        placeholder="Nomor rekening/HP" inputmode="numeric"
                                        oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                        class="w-full px-4 py-3 sm:py-3.5 rounded-xl border border-slate-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition-all font-bold text-slate-800 text-sm sm:text-base placeholder:text-slate-300">
                                </div>
                            </div>
                            <div>
                                <label
                                    class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5 ml-1">Atas
                                    Nama</label>
                                <input type="text" id="modal_account_name" name="modal_account_name" required
                                    placeholder="Nama pemilik rekening"
                                    class="w-full px-4 py-3 sm:py-3.5 rounded-xl border border-slate-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 transition-all font-bold text-slate-800 text-sm sm:text-base placeholder:text-slate-300 uppercase">
                            </div>

                            <div class="flex items-center gap-3 pt-2">
                                <button type="button" onclick="hideBankAccountForm()"
                                    class="flex-1 px-4 py-3 rounded-xl border border-slate-200 text-slate-600 font-bold text-sm hover:bg-white transition-all">
                                    Batal
                                </button>
                                <button type="submit" id="saveAccountBtn"
                                    class="flex-[2] px-4 py-3 rounded-xl bg-indigo-600 text-white font-bold text-sm hover:bg-indigo-700 shadow-lg shadow-indigo-600/20 transition-all flex items-center justify-center gap-2">
                                    <i data-lucide="save" class="w-4 h-4"></i>
                                    <span>Simpan Rekening</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    {{-- Footer Section --}}
                    <div class="px-5 py-4 sm:px-8 sm:py-6 border-t border-slate-100 flex items-center justify-between shrink-0 mb-safe"
                        id="modalFooter">
                        <button type="button" onclick="showBankAccountForm()"
                            class="px-5 py-3 sm:px-6 sm:py-3 rounded-xl bg-slate-900 text-white font-bold text-sm hover:bg-slate-800 shadow-xl transition-all flex items-center gap-2">
                            <i data-lucide="plus" class="w-4 h-4 text-slate-400"></i>
                            <span>Tambah Rekening</span>
                        </button>
                        <div class="text-right hidden xs:block">
                            <p class="text-[9px] font-black text-slate-300 uppercase tracking-widest leading-none">
                                FinanceOps</p>
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-1">Pay Securely
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Delete Reason Modal (Admin only) --}}
        <div id="deleteAccountReasonModal" class="fixed inset-0 z-[70] hidden">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeDeleteReasonModal()"></div>
            <div class="absolute inset-0 flex items-center justify-center p-4 pointer-events-none">
                <div
                    class="bg-white w-full max-w-md rounded-3xl shadow-2xl pointer-events-auto p-8 text-center sm:text-left">
                    <div
                        class="w-16 h-16 rounded-2xl bg-rose-50 flex items-center justify-center text-rose-600 mx-auto sm:mx-0 mb-6">
                        <i data-lucide="alert-triangle" class="w-8 h-8"></i>
                    </div>
                    <h3 class="text-2xl font-black text-slate-800 mb-2">Konfirmasi Hapus</h3>
                    <p class="text-slate-500 font-medium mb-6">Penghapusan rekening oleh Admin/Owner memerlukan alasan
                        penyingkatan.</p>

                    <form onsubmit="confirmDeleteAccount(event)">
                        <input type="hidden" id="delete_target_id">
                        <div class="mb-6">
                            <label
                                class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5 ml-1 text-left">Alasan
                                Penghapusan</label>
                            <textarea id="delete_reason" required
                                placeholder="Contoh: Rekening sudah tidak aktif / Salah input"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-rose-500 focus:ring-4 focus:ring-rose-500/10 transition-all font-bold text-slate-800 placeholder:text-slate-300 min-h-[100px]"></textarea>
                        </div>
                        <div class="flex items-center gap-3">
                            <button type="button" onclick="closeDeleteReasonModal()"
                                class="flex-1 px-6 py-3 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 transition-all">
                                Batal
                            </button>
                            <button type="submit"
                                class="flex-1 px-6 py-3 rounded-xl bg-rose-600 text-white font-bold hover:bg-rose-700 shadow-lg shadow-rose-600/20 transition-all">
                                Hapus Permanen
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>


            // Global Scroll Lock Helper (Consolidated)
            window.toggleBodyScroll = function (disable) {
                if (disable) {
                    const scrollY = window.scrollY;
                    document.body.dataset.scrollY = scrollY;
                    document.documentElement.style.overflow = 'hidden';
                    document.body.style.overflow = 'hidden';
                    document.body.style.position = 'fixed';
                    document.body.style.top = `-${scrollY}px`;
                    document.body.style.width = '100%';
                } else {
                    const scrollY = document.body.dataset.scrollY || '0';
                    document.documentElement.style.overflow = '';
                    document.body.style.overflow = '';
                    document.body.style.position = '';
                    document.body.style.top = '';
                    document.body.style.width = '';
                    window.scrollTo(0, parseInt(scrollY));
                }
            };

            // ─────────────────────────────────────────────────────────
            // NOTIFICATION BADGE COUNTER
            // ─────────────────────────────────────────────────────────
            function updateNotificationBadge() {
                const url = document.querySelector('meta[name="notif-unread-url"]')?.content;
                if (!url) return;
                fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                    .then(r => r.json())
                    .then(data => {
                        const count = data.count || 0;
                        const badges = [
                            document.getElementById('notif-count-desktop'),
                            document.getElementById('notif-count-mobile'),
                            document.getElementById('notif-count-sidebar')
                        ];
                        badges.forEach(badge => {
                            if (!badge) return;

                            // Update text content
                            badge.textContent = count > 99 ? '99+' : count;

                            // Show/Hide badge based on count
                            if (count > 0) {
                                badge.classList.remove('badge-hidden');
                            } else {
                                badge.classList.add('badge-hidden');
                            }
                        });
                    })
                    .catch(() => { });
            }
            // Call on page load
            document.addEventListener('DOMContentLoaded', () => updateNotificationBadge());

            function toggleMobileSidebar() {
                const sidebar = document.getElementById('mobile-sidebar');
                const overlay = document.getElementById('sidebar-overlay');
                if (!sidebar) return;
                sidebar.classList.toggle('-translate-x-full');
                overlay.classList.toggle('hidden');
                document.body.style.overflow = sidebar.classList.contains('-translate-x-full') ? '' : 'hidden';
            }

            function toggleDesktopSidebar() {
                const sidebar = document.getElementById('mobile-sidebar');
                if (!sidebar) return;
                sidebar.classList.toggle('sidebar-collapsed');
                localStorage.setItem('sidebar-collapsed', sidebar.classList.contains('sidebar-collapsed'));
            }

            function togglePengeluaranLain() {
                const menu = document.getElementById('pengeluaran-lain-menu');
                const chevron = document.getElementById('pengeluaran-lain-chevron');
                if (!menu) return;

                // If sidebar is collapsed, expanding this menu might look weird, so let's uncollapse sidebar first if needed
                const sidebar = document.getElementById('mobile-sidebar');
                if (sidebar && sidebar.classList.contains('sidebar-collapsed') && window.innerWidth >= 768) {
                    toggleDesktopSidebar();
                }

                menu.classList.toggle('hidden');
                chevron && chevron.classList.toggle('rotate-180');
            }

            document.addEventListener('DOMContentLoaded', () => {
                const sidebar = document.getElementById('mobile-sidebar');
                if (sidebar && localStorage.getItem('sidebar-collapsed') === 'true') {
                    if (window.innerWidth >= 768) {
                        sidebar.classList.add('sidebar-collapsed');
                    }
                }

                if (typeof NProgress !== 'undefined') {
                    NProgress.configure({ showSpinner: false, minimum: 0.1 });
                    NProgress.done();
                }
                lucide.createIcons();

                // ── PROFILE DROPDOWN LOGIC ──
                window.toggleProfileDropdown = function (e) {
                    if (e) e.stopPropagation();
                    const dropdown = document.getElementById('profileDropdown');
                    if (dropdown) {
                        dropdown.classList.toggle('hidden');
                    }
                };


                const dropdown = document.getElementById('profileDropdown');
                if (dropdown) {
                    dropdown.addEventListener('click', (e) => e.stopPropagation());
                    document.addEventListener('click', () => dropdown.classList.add('hidden'));
                }

                // ── SIDEBAR PROFILE DROPDOWN (Admin/Atasan/Owner) ──
                window.toggleSidebarProfile = function (e) {
                    if (e) e.stopPropagation();
                    const dd = document.getElementById('sidebarProfileDropdown');
                    const chev = document.getElementById('sidebarProfileChevron');
                    if (dd) {
                        dd.classList.toggle('hidden');
                        if (chev) chev.classList.toggle('rotate-180');
                    }
                };
                const sidebarDD = document.getElementById('sidebarProfileDropdown');
                if (sidebarDD) {
                    sidebarDD.addEventListener('click', (e) => e.stopPropagation());
                    document.addEventListener('click', () => {
                        sidebarDD.classList.add('hidden');
                        document.getElementById('sidebarProfileChevron')?.classList.remove('rotate-180');
                    });
                }

            });

            window.addEventListener('beforeunload', () => {
                if (typeof NProgress !== 'undefined') NProgress.start();
            });

            // ─────────────────────────────────────────────────────────
            // BANK ACCOUNTS MANAGEMENT LOGIC
            // ─────────────────────────────────────────────────────────
            const bankAccountsModal = document.getElementById('bankAccountsModal');
            const bankAccountsContent = document.getElementById('bankAccountsModalContent');

            function openBankAccountsModal(userId) {
                document.getElementById('target_user_id').value = userId;
                bankAccountsModal.classList.remove('hidden');
                window.toggleBodyScroll(true);
                setTimeout(() => {
                    bankAccountsContent.classList.add('animate-modal');
                    bankAccountsContent.classList.remove('scale-95', 'opacity-0');
                    bankAccountsContent.classList.add('scale-100', 'opacity-100');
                }, 10);
                fetchBankAccounts(userId);
            }

            function closeBankAccountsModal() {
                bankAccountsContent.classList.remove('animate-modal');
                bankAccountsContent.classList.add('scale-95', 'opacity-0');
                bankAccountsContent.classList.remove('scale-100', 'opacity-100');
                window.toggleBodyScroll(false);
                setTimeout(() => {
                    bankAccountsModal.classList.add('hidden');
                    hideBankAccountForm();
                }, 300);
            }

            function fetchBankAccounts(userId) {
                const list = document.getElementById('bankAccountsList');
                const loading = document.getElementById('bankAccountsLoading');
                const empty = document.getElementById('bankAccountsEmpty');

                list.classList.add('hidden');
                loading.classList.remove('hidden');
                empty.classList.add('hidden');

                fetch(`/user-bank-accounts/${userId}`)
                    .then(r => r.json())
                    .then(accounts => {
                        loading.classList.add('hidden');
                        list.innerHTML = '';

                        if (accounts.length === 0) {
                            empty.classList.remove('hidden');
                            return;
                        }

                        list.classList.remove('hidden');
                        accounts.forEach((acc, index) => {
                            const card = `
                        <div class="stagger-item group bg-white border border-slate-200 rounded-2xl p-3 sm:p-4 hover:border-indigo-500 hover:shadow-xl hover:shadow-indigo-500/5 transition-all flex items-center justify-between cursor-pointer active:scale-[0.98]" 
                             style="animation-delay: ${index * 0.05}s"
                             onclick='showBankAccountForm(${JSON.stringify(acc)})'>
                            <div class="flex items-center gap-3 sm:gap-4 overflow-hidden">
                                <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl bg-slate-50 text-slate-400 group-hover:bg-indigo-50 group-hover:text-indigo-600 flex items-center justify-center transition-colors shrink-0">
                                    <i data-lucide="landmark" class="w-5 h-5 sm:w-6 sm:h-6"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 mb-0.5">
                                        <span class="text-[9px] font-black bg-slate-800 text-white px-1.5 py-0.5 rounded uppercase tracking-widest shrink-0">${acc.bank_name}</span>
                                        <h4 class="text-sm font-black text-slate-800 tracking-tight truncate">${acc.account_number}</h4>
                                    </div>
                                    <p class="text-[10px] sm:text-xs font-bold text-slate-400 uppercase truncate">${acc.account_name}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-1 sm:gap-2 shrink-0 ml-2">
                                <button onclick="event.stopPropagation(); deleteBankAccount(${acc.id})" 
                                        class="w-8 h-8 sm:w-9 sm:h-9 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center hover:bg-rose-100 transition-all active:scale-90"
                                        title="Hapus Rekening">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>
                    `;
                            list.insertAdjacentHTML('beforeend', card);
                        });
                        lucide.createIcons();
                    });
            }

            function showBankAccountForm(data = null) {
                const container = document.getElementById('bankAccountFormContainer');
                const footer = document.getElementById('modalFooter');
                const list = document.getElementById('bankAccountsListContainer');
                const title = document.getElementById('formTitle');
                const submitBtnText = document.querySelector('#saveAccountBtn span');

                document.getElementById('bank_account_id').value = data ? data.id : '';
                document.getElementById('modal_bank_name').value = data ? data.bank_name : '';
                document.getElementById('modal_account_number').value = data ? data.account_number : '';
                document.getElementById('modal_account_name').value = data ? data.account_name : '';

                title.textContent = data ? 'Edit Rekening' : 'Tambah Rekening Baru';
                submitBtnText.textContent = data ? 'Update Rekening' : 'Simpan Rekening';

                list.classList.add('hidden');
                footer.classList.add('hidden');
                container.classList.remove('hidden');
            }

            function hideBankAccountForm() {
                document.getElementById('bankAccountFormContainer').classList.add('hidden');
                document.getElementById('modalFooter').classList.remove('hidden');
                document.getElementById('bankAccountsListContainer').classList.remove('hidden');
                document.getElementById('bankAccountForm').reset();
            }

            function saveBankAccount(e) {
                e.preventDefault();
                const id = document.getElementById('bank_account_id').value;
                const userId = document.getElementById('target_user_id').value;
                const url = id ? `/user-bank-accounts/${id}` : '/user-bank-accounts';
                const method = id ? 'PUT' : 'POST';

                const btn = document.getElementById('saveAccountBtn');
                const originalContent = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = `<div class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>`;

                fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        user_id: userId,
                        bank_name: document.getElementById('modal_bank_name').value,
                        account_number: document.getElementById('modal_account_number').value,
                        account_name: document.getElementById('modal_account_name').value
                    })
                })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            hideBankAccountForm();
                            fetchBankAccounts(userId);
                        } else {
                            alert('Gagal menyimpan rekening: ' + (res.message || 'Error tidak diketahui'));
                        }
                    })
                    .finally(() => {
                        btn.disabled = false;
                        btn.innerHTML = originalContent;
                        lucide.createIcons();
                    });
            }

            function deleteBankAccount(id) {
                const isAdmin = {{ Auth::user()->canManageUsers() ? 'true' : 'false' }};
                const currentUserId = {{ Auth::id() }};
                const targetUserId = document.getElementById('target_user_id').value;

                // If admin and deleting someone else's account, ask for reason
                if (isAdmin && currentUserId != targetUserId) {
                    document.getElementById('delete_target_id').value = id;
                    document.getElementById('deleteAccountReasonModal').classList.remove('hidden');
                } else {
                    if (confirm('Apakah Anda yakin ingin menghapus rekening ini?')) {
                        executeDelete(id);
                    }
                }
            }

            function closeDeleteReasonModal() {
                document.getElementById('deleteAccountReasonModal').classList.add('hidden');
                document.getElementById('delete_reason').value = '';
            }

            function confirmDeleteAccount(e) {
                e.preventDefault();
                const id = document.getElementById('delete_target_id').value;
                const reason = document.getElementById('delete_reason').value;
                executeDelete(id, reason);
            }

            function executeDelete(id, reason = null) {
                const userId = document.getElementById('target_user_id').value;
                const body = reason ? JSON.stringify({ reason }) : null;

                fetch(`/user-bank-accounts/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: body
                })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            closeDeleteReasonModal();
                            fetchBankAccounts(userId);
                        } else {
                            alert('Gagal menghapus rekening');
                        }
                    });
            }

// ─────────────────────────────────────────────────────────
// REAL-TIME BROADCASTING LISTENERS (LARAVEL ECHO)
// ─────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.Echo !== 'undefined') {
        const userId = Number({{ Auth::id() ?? 'null' }});
        const userRole = "{{ trim((string) Auth::user()->role) }}".toLowerCase();
        const canSubscribeGlobal = {{ Auth::user()->canManageStatus() ? 'true' : 'false' }};
        const canSubscribeManagementNotifications = ['owner', 'atasan'].includes(userRole);

        if (!userId) {
            console.warn('[Echo] Real-time subscription skipped: authenticated user id is missing.');
            return;
        }

        const subscribePrivate = (channelName) => {
            try {
                const channel = window.Echo.private(channelName);

                if (channel && typeof channel.error === 'function') {
                    channel.error((error) => {
                        console.warn(`[Echo] Private channel auth failed: ${channelName}`, error);
                    });
                }

                return channel;
            } catch (error) {
                console.warn(`[Echo] Private channel subscription failed: ${channelName}`, error);
                return null;
            }
        };

        // ── Toast Queue System ──
        const toastQueue = [];
        let isProcessingQueue = false;
        const MAX_TOASTS = 3;
        const TOAST_DURATION = 6000; // 6 Detik

        const processToastQueue = () => {
            if (isProcessingQueue || toastQueue.length === 0) return;

            const currentToasts = document.querySelectorAll('.realtime-toast-item').length;
            if (currentToasts >= MAX_TOASTS) {
                setTimeout(processToastQueue, 1000);
                return;
            }

            isProcessingQueue = true;
            const { title, message, theme, iconName } = toastQueue.shift();
            renderToast(title, message, theme, iconName);

            setTimeout(() => {
                isProcessingQueue = false;
                processToastQueue();
            }, 1000); // Jeda 1 detik antar toast
        };

        const showRealtimeToast = (title, message, theme, iconName) => {
            toastQueue.push({ title, message, theme, iconName });
            processToastQueue();
        };

        const renderToast = (title, message, theme, iconName) => {
            const toastId = 'toast-' + Date.now();

            let container = document.getElementById('toast-container-stack');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container-stack';
                container.className = 'fixed top-24 right-0 sm:right-6 md:right-8 z-[110] flex flex-col gap-3 pointer-events-none items-center sm:items-end w-full sm:max-w-sm px-4 sm:px-0';
                document.body.appendChild(container);
            }

            // HTML Toast Baru: Flat design, border tegas, glassmorphism, progress bar
            const html = `
            <div id="${toastId}" class="realtime-toast-item group relative overflow-hidden pointer-events-auto flex items-start w-full sm:w-auto sm:min-w-[340px] bg-white/95 backdrop-blur-md rounded-xl shadow-lg border border-slate-200/80 opacity-0 translate-x-12 scale-95 transition-all duration-500 ease-[cubic-bezier(0.16,1,0.3,1)]">
                
                <!-- Icon Area (Flat Colors) -->
                <div class="flex-shrink-0 w-12 h-12 m-3 rounded-lg flex items-center justify-center ${theme.iconBg} ${theme.iconText}">
                    <i data-lucide="${iconName || 'bell'}" class="w-6 h-6"></i>
                </div>
                
                <!-- Text Content -->
                <div class="flex-1 min-w-0 py-3 pr-2">
                    <h3 class="text-sm font-bold text-slate-800 mb-0.5 leading-tight">${title}</h3>
                    <p class="text-[13px] text-slate-500 font-medium leading-snug">${message}</p>
                </div>
                
                <!-- Close Button -->
                <button type="button" class="flex-shrink-0 m-2 bg-transparent text-slate-400 hover:text-slate-800 rounded-lg p-1.5 hover:bg-slate-100 transition-colors" 
                    onclick="const el = document.getElementById('${toastId}'); if(el){ el.classList.remove('opacity-100', 'translate-x-0', 'scale-100'); el.classList.add('opacity-0', 'translate-x-12', 'scale-95'); setTimeout(()=>el.remove(), 500); }">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>

                <!-- Animated Progress Bar -->
                <div class="absolute bottom-0 left-0 h-[3px] ${theme.barBg} w-full transition-all ease-linear" id="progress-${toastId}" style="transition-duration: ${TOAST_DURATION}ms;"></div>
            </div>
            `;

            container.insertAdjacentHTML('beforeend', html);
            const el = document.getElementById(toastId);
            const progress = document.getElementById(`progress-${toastId}`);

            if (!el) return;

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            // Animasi Masuk (Slide & Scale)
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    if (!el) return;
                    el.classList.remove('opacity-0', 'translate-x-12', 'scale-95');
                    el.classList.add('opacity-100', 'translate-x-0', 'scale-100');
                    
                    // Trigger Progress Bar
                    if(progress) {
                        progress.style.width = '0%';
                    }
                });
            });

            // Animasi Keluar Otomatis
            setTimeout(() => {
                const liveEl = document.getElementById(toastId);
                if (liveEl) {
                    liveEl.classList.remove('opacity-100', 'translate-x-0', 'scale-100');
                    liveEl.classList.add('opacity-0', 'translate-x-12', 'scale-95');
                    setTimeout(() => { const r = document.getElementById(toastId); if (r) r.remove(); }, 500);
                }
            }, TOAST_DURATION);
        };

        // ── Tema Warna Flat / Minimalist ──
        const THEMES = {
            success: { iconBg: 'bg-emerald-100', iconText: 'text-emerald-600', barBg: 'bg-emerald-500' },
            error: { iconBg: 'bg-rose-100', iconText: 'text-rose-600', barBg: 'bg-rose-500' },
            info: { iconBg: 'bg-blue-100', iconText: 'text-blue-600', barBg: 'bg-blue-500' },
            warning: { iconBg: 'bg-amber-100', iconText: 'text-amber-600', barBg: 'bg-amber-500' },
            purple: { iconBg: 'bg-purple-100', iconText: 'text-purple-600', barBg: 'bg-purple-500' },
        };

        window.showToast = (message, type = 'success') => {
            let theme = THEMES.success;
            let iconName = 'check-circle';
            let title = 'Berhasil';

            if (type === 'error') {
                theme = THEMES.error; iconName = 'alert-circle'; title = 'Gagal';
            } else if (type === 'info') {
                theme = THEMES.info; iconName = 'info'; title = 'Informasi';
            } else if (type === 'warning') {
                theme = THEMES.warning; iconName = 'alert-triangle'; title = 'Peringatan';
            }

            showRealtimeToast(title, message, theme, iconName);
        };

        @if(session('notification'))
            window.showToast(@json(session('notification')), 'success');
        @endif

        @if(session('success'))
            window.showToast(@json(session('success')), 'success');
        @endif

        @if(session('error'))
            window.showToast(@json(session('error')), 'error');
        @endif

        // ── Listener NOTIFIKASI SYSTEM ──
        const notificationChannel = subscribePrivate(`notifications.${userId}`);
        if (notificationChannel) {
            notificationChannel
            .listen('.notification.received', (e) => {
                // console.log('🔔 [NOTIF] Notification Received:', e);
                if (typeof updateNotificationBadge === 'function') updateNotificationBadge();

                let theme = THEMES.purple;
                let iconName = 'bell';

                if (e.type === 'ocr_status') {
                    const isSuccess = e.title.toLowerCase().includes('berhasil');
                    theme = isSuccess ? THEMES.success : THEMES.error;
                    iconName = isSuccess ? 'sparkles' : 'alert-triangle';
                } else if (e.type === 'transaction_status') {
                    const titleLower = e.title.toLowerCase();
                    if (titleLower.includes('cash siap')) {
                        theme = THEMES.warning; iconName = 'banknote';
                    } else if (titleLower.includes('disetujui owner')) {
                        theme = THEMES.success; iconName = 'check-circle';
                    } else if (titleLower.includes('disetujui')) {
                        theme = THEMES.info; iconName = 'clipboard-check';
                    } else if (titleLower.includes('diproses')) {
                        theme = THEMES.info; iconName = 'clock';
                    } else if (titleLower.includes('selesai')) {
                        theme = THEMES.success; iconName = 'check-circle';
                    } else if (titleLower.includes('ditolak')) {
                        theme = THEMES.error; iconName = 'x-circle';
                    }
                } else if (e.type === 'owner_approval') {
                    theme = THEMES.purple; iconName = 'shield-check';
                }

                showRealtimeToast(e.title, e.message, theme, iconName);
            });
        }

        // ── Define Realtime Handlers ──
        window.handleRealtimeTransactionCreation = function (transaction) {
            // console.log('🆕 [REALTIME] Transaction Created:', transaction);
            if (typeof SearchEngine !== 'undefined' && SearchEngine.addTransaction) {
                SearchEngine.addTransaction(transaction);
            }
        };

        window.handleRealtimeTransactionUpdate = function (transaction) {
            // console.log('🔄 [REALTIME] Transaction Updated:', transaction);
            if (typeof SearchEngine !== 'undefined' && SearchEngine.updateTransaction) {
                SearchEngine.updateTransaction(transaction);
            }
        };

        window.handleRealtimeTransactionDeletion = function (data) {
            // console.log('🗑️ [REALTIME] Transaction Deleted:', data);
            if (typeof SearchEngine !== 'undefined' && SearchEngine.deleteTransaction) {
                SearchEngine.deleteTransaction(data.id);
            }
        };

        // ── STRATEGY: Role-Based Channel Subscription ──
        // Teknisi: Only personal channel (transactions.{userId})
        // Admin/Owner/Atasan: Both personal + global channel (transactions)
        
        // 1. Personal Channel - ALL ROLES (for their own transactions)
        const personalTransactionChannel = subscribePrivate(`transactions.${userId}`);
        if (personalTransactionChannel) {
            personalTransactionChannel
            .listen('.transaction.created', (e) => {
                // console.log('📥 [PERSONAL] Transaction Created:', e);
                if (e.transaction) window.handleRealtimeTransactionCreation(e.transaction);
            })
            .listen('.transaction.updated', (e) => {
                // console.log('📥 [PERSONAL] Transaction Updated:', e);
                if (e.transaction) window.handleRealtimeTransactionUpdate(e.transaction);
            });
        }

        // 2. OCR Channel - ALL ROLES (for OCR processing updates)
        const ocrChannel = subscribePrivate(`ocr.${userId}`);
        if (ocrChannel) {
            ocrChannel
            .listen('.ocr.updated', (e) => {
                // console.log('📥 [OCR] Status Updated:', e);
                if (e.payload && e.payload.transaction) window.handleRealtimeTransactionUpdate(e.payload.transaction);
            });
        }

        // 3. Global Channel - ONLY for Admin/Owner/Atasan (to see all transactions)
        if (canSubscribeGlobal) {
            const globalTransactionChannel = subscribePrivate('transactions');
            if (globalTransactionChannel) {
                globalTransactionChannel
                .listen('.transaction.created', (e) => {
                    // console.log('📡 [GLOBAL] Transaction Created:', e);
                    // Only add if it's NOT from current user (to avoid duplicate from personal channel)
                    if (e.transaction && e.transaction.submitted_by !== userId) {
                        window.handleRealtimeTransactionCreation(e.transaction);
                    }
                })
                .listen('.transaction.updated', (e) => {
                    // console.log('📡 [GLOBAL] Transaction Updated:', e);
                    // Only update if it's NOT from current user (to avoid duplicate from personal channel)
                    if (e.transaction && e.transaction.submitted_by !== userId) {
                        window.handleRealtimeTransactionUpdate(e.transaction);
                    }
                })
                .listen('.transaction.deleted', (e) => {
                    // console.log('📡 [GLOBAL] Transaction Deleted:', e);
                    if (e.id) window.handleRealtimeTransactionDeletion(e);
                });
            }
        }

        if (canSubscribeManagementNotifications) {
            const managementNotificationChannel = subscribePrivate('notifications.management');
            if (managementNotificationChannel) {
                managementNotificationChannel
                .listen('PriceAnomalyDetected', (e) => {
                    // console.log('Price Anomaly Detected:', e);
                    const msg = `Item "${e.item_name}" melebihi harga referensi (+${e.excess_percentage}%). <br><a href="${e.url}" class="text-blue-600 font-bold hover:underline mt-1 inline-block">Review Sekarang →</a>`;
                    
                    showRealtimeToast('⚠️ Anomali Harga!', msg, THEMES.error, 'alert-circle');

                    if (typeof fetchNotifications === 'function') fetchNotifications();
                });
            }
        }
    }
});
        </script>
        @stack('modals')
        <x-confirm-modal id="globalConfirmModal" />
        @stack('scripts')
    @include('transactions.partials.forms.shared.camera-modal')
</body>

</html>
