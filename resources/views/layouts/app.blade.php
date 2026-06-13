<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Whusnet Operasional')</title>

    <!-- Styles / Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full text-slate-900 font-sans antialiased">
    <div class="min-h-full flex flex-col md:flex-row">
        <!-- Sidebar Container -->
        <aside id="sidebar" class="bg-slate-900 text-white w-64 shrink-0 transition-all duration-300 ease-in-out md:flex md:flex-col md:h-screen md:sticky md:top-0 hidden z-30">
            <script>
                if (localStorage.getItem('sidebar-collapsed') === 'true') {
                    document.getElementById('sidebar').classList.add('collapsed');
                }
            </script>
            <!-- Brand Section -->
            <div class="h-16 flex items-center justify-between px-6 border-b border-slate-800 brand-container shrink-0">
                <a href="/" class="flex items-center gap-2 font-bold text-lg tracking-wide hover:opacity-95 transition-opacity brand-link">
                    <!-- Sky Blue SVG Dot/Logo -->
                    <svg class="h-6 w-6 text-sky-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span class="text-white sidebar-text">WHUS<span class="text-sky-500">NET</span></span>
                </a>
                <button onclick="toggleSidebar()" class="md:hidden p-1 rounded hover:bg-slate-800 text-slate-400 hover:text-white transition-colors cursor-pointer">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Navigation Links -->
            <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
                <!-- Dashboard Link -->
                <a href="/" title="Dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium transition-colors cursor-pointer hover:bg-slate-800 hover:text-white {{ Request::is('/') ? 'bg-sky-600 text-white' : 'text-slate-300' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span class="sidebar-text">Dashboard</span>
                </a>

                @if(auth()->user()->hasPermission('view_users') || auth()->user()->hasPermission('manage_users'))
                <!-- SETTINGS Dropdown -->
                <div>
                    <button onclick="toggleSubmenu('submenu-settings', 'chevron-settings')" title="Sistem" class="w-full flex items-center justify-between px-3 py-2.5 rounded-md text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white transition-colors cursor-pointer focus:outline-none focus:bg-slate-800">
                        <span class="flex items-center gap-3">
                            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span class="sidebar-text">SISTEM</span>
                        </span>
                        <svg id="chevron-settings" class="chevron-icon h-4 w-4 transform transition-transform duration-200 {{ Request::is('users*') ? 'rotate-180' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <!-- Submenu -->
                    <div id="submenu-settings" class="submenu-container mt-1 pl-11 pr-2 space-y-1 transition-all duration-300 ease-in-out {{ Request::is('users*') ? '' : 'hidden' }}">
                        @if(auth()->user()->hasPermission('view_users'))
                            <a href="/users" class="block py-2 px-3 rounded-md text-xs font-medium transition-colors cursor-pointer hover:bg-slate-800 hover:text-white {{ Request::is('users*') ? 'text-sky-400 bg-slate-800/50' : 'text-slate-400' }}">
                                Manajemen User & POP
                            </a>
                        @endif
                    </div>
                </div>
                @endif

                @if(auth()->user()->hasPermission('view_customers') || auth()->user()->hasPermission('create_customers') || auth()->user()->hasPermission('import_customers'))
                <!-- PELANGGAN Dropdown -->
                <div>
                    <button onclick="toggleSubmenu('submenu-pelanggan', 'chevron-pelanggan')" title="Pelanggan" class="w-full flex items-center justify-between px-3 py-2.5 rounded-md text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white transition-colors cursor-pointer focus:outline-none focus:bg-slate-800">
                        <span class="flex items-center gap-3">
                            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <span class="sidebar-text">PELANGGAN</span>
                        </span>
                        <svg id="chevron-pelanggan" class="chevron-icon h-4 w-4 transform transition-transform duration-200 {{ Request::is('customers*') ? 'rotate-180' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <!-- Submenu -->
                    <div id="submenu-pelanggan" class="submenu-container mt-1 pl-11 pr-2 space-y-1 transition-all duration-300 ease-in-out {{ Request::is('customers*') ? '' : 'hidden' }}">
                        @if(auth()->user()->hasPermission('view_customers'))
                            <a href="/customers" class="block py-2 px-3 rounded-md text-xs font-medium transition-colors cursor-pointer hover:bg-slate-800 hover:text-white {{ Request::is('customers') && !Request::is('customers/create') && !Request::is('customers/import') ? 'text-sky-400 bg-slate-800/50' : 'text-slate-400' }}">
                                List Pelanggan
                            </a>
                        @endif
                        @if(auth()->user()->hasPermission('create_customers'))
                            <a href="/customers/create" class="block py-2 px-3 rounded-md text-xs font-medium transition-colors cursor-pointer hover:bg-slate-800 hover:text-white {{ Request::is('customers/create') ? 'text-sky-400 bg-slate-800/50' : 'text-slate-400' }}">
                                Input Pelanggan
                            </a>
                        @endif
                        @if(auth()->user()->hasPermission('import_customers'))
                            <a href="/customers/import" class="block py-2 px-3 rounded-md text-xs font-medium transition-colors cursor-pointer hover:bg-slate-800 hover:text-white {{ Request::is('customers/import') ? 'text-sky-400 bg-slate-800/50' : 'text-slate-400' }}">
                                Import Pelanggan
                            </a>
                        @endif
                    </div>
                </div>
                @endif

                @if(auth()->user()->hasPermission('view_invoices'))
                <!-- Billing Link -->
                <a href="{{ route('invoices.index') }}" title="Tagihan" class="flex items-center gap-3 px-3 py-2.5 rounded-md text-sm font-medium transition-colors cursor-pointer hover:bg-slate-800 hover:text-white {{ Request::is('invoices*') ? 'bg-sky-600 text-white' : 'text-slate-300' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 14h6m-6 4h6M5 3h14a2 2 0 012 2v16l-3-2-3 2-3-2-3 2-3-2-3 2V5a2 2 0 012-2z" />
                    </svg>
                    <span class="sidebar-text">Tagihan</span>
                </a>
                @endif

                @if(auth()->user()->hasPermission('view_pop') || auth()->user()->hasPermission('view_packages'))
                <!-- Master Data Dropdown -->
                <div>
                    <button onclick="toggleSubmenu('submenu-master', 'chevron-master')" title="Master Data" class="w-full flex items-center justify-between px-3 py-2.5 rounded-md text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white transition-colors cursor-pointer focus:outline-none focus:bg-slate-800">
                        <span class="flex items-center gap-3">
                            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                            </svg>
                            <span class="sidebar-text">Master Data</span>
                        </span>
                        <svg id="chevron-master" class="chevron-icon h-4 w-4 transform transition-transform duration-200 {{ Request::is('master*') ? 'rotate-180' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <!-- Submenu -->
                    <div id="submenu-master" class="submenu-container mt-1 pl-11 pr-2 space-y-1 transition-all duration-300 ease-in-out {{ Request::is('master*') ? '' : 'hidden' }}">
                        @if(auth()->user()->hasPermission('view_pop'))
                            <a href="/master/wilayah" class="block py-2 px-3 rounded-md text-xs font-medium transition-colors cursor-pointer hover:bg-slate-800 hover:text-white {{ Request::is('master/wilayah') ? 'text-sky-400 bg-slate-800/50' : 'text-slate-400' }}">
                                Master Data Wilayah
                            </a>
                            <a href="/master/pop" class="block py-2 px-3 rounded-md text-xs font-medium transition-colors cursor-pointer hover:bg-slate-800 hover:text-white {{ Request::is('master/pop*') ? 'text-sky-400 bg-slate-800/50' : 'text-slate-400' }}">
                                Master POP/Cabang
                            </a>
                        @endif
                        @if(auth()->user()->hasPermission('view_packages'))
                            <a href="/master/paket" class="block py-2 px-3 rounded-md text-xs font-medium transition-colors cursor-pointer hover:bg-slate-800 hover:text-white {{ Request::is('master/paket*') ? 'text-sky-400 bg-slate-800/50' : 'text-slate-400' }}">
                                Master Paket Internet
                            </a>
                            <a href="/master/status-langganan" class="block py-2 px-3 rounded-md text-xs font-medium transition-colors cursor-pointer hover:bg-slate-800 hover:text-white {{ Request::is('master/status-langganan') ? 'text-sky-400 bg-slate-800/50' : 'text-slate-400' }}">
                                Master Status Pelanggan
                            </a>
                        @endif
                    </div>
                </div>
                @endif
            </nav>

            <!-- Sidebar Footer -->
            <div class="p-4 border-t border-slate-800 flex items-center gap-3 sidebar-footer shrink-0">
                <div class="h-9 w-9 rounded-full bg-slate-800 flex items-center justify-center font-bold text-sky-400 border border-slate-700 shrink-0">
                    {{ strtoupper(substr(Auth::user()->name ?? 'AD', 0, 2)) }}
                </div>
                <div class="flex-1 min-w-0 sidebar-footer-info">
                    <p class="text-xs font-semibold text-white truncate">{{ Auth::user()->name ?? 'Administrator' }}</p>
                    <p class="text-[10px] text-slate-400 truncate">{{ Auth::user()->email ?? 'admin@whusnet.net' }}</p>
                </div>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                    @csrf
                </form>
                <button onclick="event.preventDefault(); document.getElementById('logout-form').submit();" 
                        class="p-1.5 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-slate-800 transition-colors cursor-pointer focus:outline-none sidebar-footer-info" 
                        title="Logout">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                </button>
            </div>
        </aside>

        <!-- Mobile Header -->
        <header class="md:hidden bg-slate-900 text-white h-16 flex items-center justify-between px-6 z-20 shadow-md">
            <a href="/" class="flex items-center gap-2 font-bold text-lg tracking-wide">
                <svg class="h-6 w-6 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <span>WHUS<span class="text-sky-500">NET</span></span>
            </a>
            <button onclick="toggleSidebar()" class="p-2 rounded hover:bg-slate-800 text-slate-400 hover:text-white cursor-pointer">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </header>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0 bg-slate-50">
            <!-- Top Navbar -->
            <header class="h-16 bg-white border-b border-slate-200 hidden md:flex items-center justify-between px-8 shadow-sm">
                <div class="flex items-center gap-4">
                    <button onclick="toggleDesktopSidebar()" class="p-2 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-800 transition-colors cursor-pointer focus:outline-none" title="Toggle Sidebar">
                        <!-- Lucide Panel Left Icon -->
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="18" height="18" x="3" y="3" rx="2"/>
                            <path d="M9 3v18"/>
                        </svg>
                    </button>
                    <h2 class="text-lg font-semibold text-slate-800">@yield('page_title', 'Dashboard')</h2>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-xs text-slate-500 data-text">{{ \App\Support\IndonesianDate::dateTime(now()) }}</span>
                    <div class="h-8 w-px bg-slate-200"></div>
                    <div class="flex items-center gap-2 text-sm text-slate-700">
                        <span class="font-medium">Admin Panel</span>
                    </div>
                </div>
            </header>

            <!-- Main Dynamic Page Content -->
            <main class="flex-1 p-6 md:p-8 overflow-y-auto">
                @yield('content')
            </main>
        </div>
    </div>

    <!-- Toggle Handlers Script -->
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            if (sidebar.classList.contains('hidden')) {
                sidebar.classList.remove('hidden');
                sidebar.classList.add('flex');
            } else {
                sidebar.classList.remove('flex');
                sidebar.classList.add('hidden');
            }
        }

        function toggleDesktopSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('collapsed');
            
            // Save state to localStorage
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebar-collapsed', isCollapsed);
        }

        function toggleSubmenu(menuId, chevronId) {
            const sidebar = document.getElementById('sidebar');
            // If sidebar is collapsed, expand it first
            if (sidebar.classList.contains('collapsed')) {
                sidebar.classList.remove('collapsed');
                localStorage.setItem('sidebar-collapsed', 'false');
            }

            const menu = document.getElementById(menuId);
            const chevron = document.getElementById(chevronId);
            
            if (menu.classList.contains('hidden')) {
                menu.classList.remove('hidden');
                chevron.classList.add('rotate-180');
            } else {
                menu.classList.add('hidden');
                chevron.classList.remove('rotate-180');
            }
        }
    </script>
    
    @yield('scripts')
</body>
</html>
