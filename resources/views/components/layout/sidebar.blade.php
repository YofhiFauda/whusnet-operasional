<aside class="w-64 bg-surface border-r border-border h-screen sticky top-0 hidden md:flex flex-col transition-all duration-300" :class="{ 'w-16': !sidebarOpen, 'w-64': sidebarOpen }">
    <div class="h-16 flex items-center justify-center border-b border-border px-4">
        <span class="font-bold text-lg text-primary truncate" x-show="sidebarOpen">Whusnet OSS</span>
        <span class="font-bold text-lg text-primary" x-show="!sidebarOpen">W</span>
    </div>
    
    <nav class="flex-1 overflow-y-auto p-3 space-y-1.5">
        @if(auth()->user()->hasPermission('dashboard.view'))
        <a href="{{ route('dashboard') }}" title="Dashboard"
           class="flex items-center gap-3 px-3 py-2 rounded-md transition-colors {{ request()->routeIs('dashboard') ? 'bg-primary-soft text-primary-hover font-semibold' : 'text-text-secondary hover:bg-surface-muted hover:text-text-main' }}"
           :class="{ 'justify-center px-0': !sidebarOpen }">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layout-dashboard flex-shrink-0"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
            <span x-show="sidebarOpen" class="truncate">Dashboard</span>
        </a>
        @endif

        <!-- Manajemen Pelanggan -->
        @if(auth()->user()->hasPermission('customers.view') || auth()->user()->hasPermission('customers.detail.survey.view') || auth()->user()->hasPermission('customers.detail.installation.view'))
        <div class="pt-3 pb-1">
            <p x-show="sidebarOpen" class="px-3 text-xs font-semibold text-text-muted uppercase tracking-wider">Pelanggan</p>
            <div x-show="!sidebarOpen" class="w-full border-t border-border my-2"></div>
        </div>

        @if(auth()->user()->hasPermission('customers.view'))
        <a href="{{ route('customers.index') }}" title="Pelanggan"
           class="flex items-center gap-3 px-3 py-2 rounded-md transition-colors {{ request()->routeIs('customers.*') ? 'bg-primary-soft text-primary-hover font-semibold' : 'text-text-secondary hover:bg-surface-muted hover:text-text-main' }}"
           :class="{ 'justify-center px-0': !sidebarOpen }">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span x-show="sidebarOpen" class="truncate">Pelanggan</span>
        </a>
        @endif

        @if(auth()->user()->hasPermission('customers.detail.survey.view'))
        <a href="{{ route('surveys.queue') }}" title="Antrean Survey"
           class="flex items-center gap-3 px-3 py-2 rounded-md transition-colors {{ request()->routeIs('surveys.*') ? 'bg-primary-soft text-primary-hover font-semibold' : 'text-text-secondary hover:bg-surface-muted hover:text-text-main' }}"
           :class="{ 'justify-center px-0': !sidebarOpen }">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
            <span x-show="sidebarOpen" class="truncate">Antrean Survey</span>
        </a>
        @endif
        
        @if(auth()->user()->hasPermission('customers.detail.installation.view'))
        <a href="{{ route('verifications.queue') }}" title="Verifikasi & Instalasi"
           class="flex items-center gap-3 px-3 py-2 rounded-md transition-colors {{ request()->routeIs('verifications.*') ? 'bg-primary-soft text-primary-hover font-semibold' : 'text-text-secondary hover:bg-surface-muted hover:text-text-main' }}"
           :class="{ 'justify-center px-0': !sidebarOpen }">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><path d="m9 15 2 2 4-4"/></svg>
            <span x-show="sidebarOpen" class="truncate">Verifikasi & Instalasi</span>
        </a>
        @endif
        @endif

        <!-- Billing -->
        @if(auth()->user()->hasPermission('invoices.view') || auth()->user()->hasPermission('payments.view'))
        <div class="pt-3 pb-1">
            <p x-show="sidebarOpen" class="px-3 text-xs font-semibold text-text-muted uppercase tracking-wider">Keuangan</p>
            <div x-show="!sidebarOpen" class="w-full border-t border-border my-2"></div>
        </div>

        @if(auth()->user()->hasPermission('invoices.view'))
        <a href="{{ route('invoices.index') }}" title="Tagihan"
           class="flex items-center gap-3 px-3 py-2 rounded-md transition-colors {{ request()->routeIs('invoices.*') ? 'bg-primary-soft text-primary-hover font-semibold' : 'text-text-secondary hover:bg-surface-muted hover:text-text-main' }}"
           :class="{ 'justify-center px-0': !sidebarOpen }">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            <span x-show="sidebarOpen" class="truncate">Tagihan</span>
        </a>
        @endif

        @if(auth()->user()->hasPermission('payments.view'))
        <a href="{{ route('payments.index') }}" title="Pembayaran"
           class="flex items-center gap-3 px-3 py-2 rounded-md transition-colors {{ request()->routeIs('payments.*') ? 'bg-primary-soft text-primary-hover font-semibold' : 'text-text-secondary hover:bg-surface-muted hover:text-text-main' }}"
           :class="{ 'justify-center px-0': !sidebarOpen }">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
            <span x-show="sidebarOpen" class="truncate">Pembayaran</span>
        </a>
        @endif
        @endif

        <!-- Laporan -->
        @if(auth()->user()->hasPermission('reports.view'))
        <div class="pt-3 pb-1">
            <p x-show="sidebarOpen" class="px-3 text-xs font-semibold text-text-muted uppercase tracking-wider">Laporan</p>
            <div x-show="!sidebarOpen" class="w-full border-t border-border my-2"></div>
        </div>

        <a href="{{ route('reports.customers.index') }}" title="Laporan"
           class="flex items-center gap-3 px-3 py-2 rounded-md transition-colors {{ request()->routeIs('reports.*') ? 'bg-primary-soft text-primary-hover font-semibold' : 'text-text-secondary hover:bg-surface-muted hover:text-text-main' }}"
           :class="{ 'justify-center px-0': !sidebarOpen }">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
            <span x-show="sidebarOpen" class="truncate">Laporan</span>
        </a>
        @endif

        <!-- Master Data -->
        @if(auth()->user()->hasPermission('packages.view') || auth()->user()->hasPermission('pops.view') || auth()->user()->hasPermission('items.view') || auth()->user()->hasPermission('item_categories.view') || auth()->user()->hasPermission('work_tools.view'))
        <div class="pt-3 pb-1">
            <p x-show="sidebarOpen" class="px-3 text-xs font-semibold text-text-muted uppercase tracking-wider">Master Data</p>
            <div x-show="!sidebarOpen" class="w-full border-t border-border my-2"></div>
        </div>

        @if(auth()->user()->hasPermission('packages.view'))
        <a href="{{ route('master.paket.index') }}" title="Paket Internet"
           class="flex items-center gap-3 px-3 py-2 rounded-md transition-colors {{ request()->routeIs('master.paket.*') ? 'bg-primary-soft text-primary-hover font-semibold' : 'text-text-secondary hover:bg-surface-muted hover:text-text-main' }}"
           :class="{ 'justify-center px-0': !sidebarOpen }">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
            <span x-show="sidebarOpen" class="truncate">Paket Internet</span>
        </a>
        @endif

        @if(auth()->user()->hasPermission('packages.view'))
        <a href="{{ route('master.sla-timeline.index') }}" title="Master Timeline SLA"
           class="flex items-center gap-3 px-3 py-2 rounded-md transition-colors {{ request()->routeIs('master.sla-timeline.*') ? 'bg-primary-soft text-primary-hover font-semibold' : 'text-text-secondary hover:bg-surface-muted hover:text-text-main' }}"
           :class="{ 'justify-center px-0': !sidebarOpen }">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <span x-show="sidebarOpen" class="truncate">Master Timeline SLA</span>
        </a>
        @endif

        @if(auth()->user()->hasPermission('pops.view'))
        <a href="{{ route('master.pop.index') }}" title="POP / Cabang"
           class="flex items-center gap-3 px-3 py-2 rounded-md transition-colors {{ request()->routeIs('master.pop.*') ? 'bg-primary-soft text-primary-hover font-semibold' : 'text-text-secondary hover:bg-surface-muted hover:text-text-main' }}"
           :class="{ 'justify-center px-0': !sidebarOpen }">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <span x-show="sidebarOpen" class="truncate">POP / Cabang</span>
        </a>
        @endif

        @if(auth()->user()->hasPermission('items.view'))
        <a href="{{ route('master.items.index') }}" title="Barang / Material"
           class="flex items-center gap-3 px-3 py-2 rounded-md transition-colors {{ request()->routeIs('master.items.*') ? 'bg-primary-soft text-primary-hover font-semibold' : 'text-text-secondary hover:bg-surface-muted hover:text-text-main' }}"
           :class="{ 'justify-center px-0': !sidebarOpen }">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0"><path d="m21 16-9 5-9-5V8l9-5 9 5z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
            <span x-show="sidebarOpen" class="truncate">Barang / Material</span>
        </a>
        @endif

        @if(auth()->user()->hasPermission('item_categories.view'))
        <a href="{{ route('master.item-categories.index') }}" title="Kategori Barang"
           class="flex items-center gap-3 px-3 py-2 rounded-md transition-colors {{ request()->routeIs('master.item-categories.*') ? 'bg-primary-soft text-primary-hover font-semibold' : 'text-text-secondary hover:bg-surface-muted hover:text-text-main' }}"
           :class="{ 'justify-center px-0': !sidebarOpen }">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0"><path d="M7 7h.01"/><path d="M20.4 14.5 16 10 4 20"/><path d="M3 7a4 4 0 0 1 4-4h5a2 2 0 0 1 1.4.6l7 7a2 2 0 0 1 0 2.8l-7 7a2 2 0 0 1-2.8 0l-7-7A2 2 0 0 1 3 12z"/></svg>
            <span x-show="sidebarOpen" class="truncate">Kategori Barang</span>
        </a>
        @endif

        @if(auth()->user()->hasPermission('work_tools.view'))
        <a href="{{ route('master.work-tools.index') }}" title="Alat Kerja"
           class="flex items-center gap-3 px-3 py-2 rounded-md transition-colors {{ request()->routeIs('master.work-tools.*') ? 'bg-primary-soft text-primary-hover font-semibold' : 'text-text-secondary hover:bg-surface-muted hover:text-text-main' }}"
           :class="{ 'justify-center px-0': !sidebarOpen }">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
            <span x-show="sidebarOpen" class="truncate">Alat Kerja</span>
        </a>
        @endif
        @endif

        <!-- Sistem -->
        @if(auth()->user()->hasPermission('users.view') || auth()->user()->hasPermission('audit_logs.view'))
        <div class="pt-3 pb-1">
            <p x-show="sidebarOpen" class="px-3 text-xs font-semibold text-text-muted uppercase tracking-wider">Sistem</p>
            <div x-show="!sidebarOpen" class="w-full border-t border-border my-2"></div>
        </div>

        @if(auth()->user()->hasPermission('users.view'))
        <a href="{{ route('users.index') }}" title="Pengguna"
           class="flex items-center gap-3 px-3 py-2 rounded-md transition-colors {{ request()->routeIs('users.*') ? 'bg-primary-soft text-primary-hover font-semibold' : 'text-text-secondary hover:bg-surface-muted hover:text-text-main' }}"
           :class="{ 'justify-center px-0': !sidebarOpen }">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span x-show="sidebarOpen" class="truncate">Pengguna</span>
        </a>
        @endif

        @if(auth()->user()->hasPermission('audit_logs.view'))
        <a href="{{ route('audit-logs.index') }}" title="Audit Log"
           class="flex items-center gap-3 px-3 py-2 rounded-md transition-colors {{ request()->routeIs('audit-logs.*') ? 'bg-primary-soft text-primary-hover font-semibold' : 'text-text-secondary hover:bg-surface-muted hover:text-text-main' }}"
           :class="{ 'justify-center px-0': !sidebarOpen }">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            <span x-show="sidebarOpen" class="truncate">Audit Log</span>
        </a>
        @endif
        @endif
    </nav>
</aside>
