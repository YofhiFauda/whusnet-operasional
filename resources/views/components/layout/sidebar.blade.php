<aside class="w-64 bg-surface border-r border-border h-screen sticky top-0 hidden md:flex flex-col transition-all duration-300" :class="{ 'w-16': !sidebarOpen, 'w-64': sidebarOpen }">
    <div class="h-16 flex items-center justify-center border-b border-border px-4">
        <span class="font-bold text-lg text-primary truncate" x-show="sidebarOpen">Whusnet OSS</span>
        <span class="font-bold text-lg text-primary" x-show="!sidebarOpen">W</span>
    </div>
    
    <nav class="flex-1 overflow-y-auto p-4 space-y-2">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3 py-2 rounded-md {{ request()->routeIs('dashboard') ? 'bg-primary-soft text-primary-hover font-semibold' : 'text-text-secondary hover:bg-surface-muted hover:text-text-main' }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layout-dashboard flex-shrink-0"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
            <span x-show="sidebarOpen" class="truncate">Dashboard</span>
        </a>
        <!-- Add other menu items here -->
    </nav>
</aside>
