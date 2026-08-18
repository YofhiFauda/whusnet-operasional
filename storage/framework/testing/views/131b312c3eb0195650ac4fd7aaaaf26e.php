<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>" class="h-full bg-slate-50 dark:bg-slate-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    <title><?php echo $__env->yieldContent('title', 'Whusnet Operasional'); ?></title>

    
    <script>
        (function () {
            const saved = localStorage.getItem('whusnet-theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (saved === 'dark' || (!saved && prefersDark)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <!-- Alpine.js & NProgress: dibundel lewat Vite (resources/js/app.js), bukan CDN.
         Alpine sengaja TIDAK punya tag <script> sendiri lagi — dua sumber sekaligus
         bikin Alpine memperingatkan "detected multiple instances". -->

    <!-- Styles / Scripts -->
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
</head>
<body class="h-full text-slate-800 dark:text-slate-100 antialiased font-sans selection:bg-sky-500 selection:text-white">

<div class="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-900">

    
    <aside id="sidebar"
           class="sidebar-light fixed inset-y-0 left-0 z-40 w-64 flex flex-col justify-between
                  transition-transform duration-300 md:static md:translate-x-0 -translate-x-full">

        
        <div class="h-16 flex items-center justify-between px-5 border-b border-slate-100 dark:border-slate-800/60 shrink-0 brand-container">
            <div class="flex items-center gap-3 brand-link">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-sky-500 to-blue-600 flex items-center justify-center text-white font-black text-base shadow-sm shadow-sky-500/25 shrink-0">
                    W
                </div>
                <div class="flex items-center gap-2 sidebar-text">
                    <span class="font-extrabold text-slate-900 dark:text-slate-50 text-base leading-none tracking-tight">WHUSNET</span>
                </div>
            </div>
            <button onclick="toggleSidebar()"
                    class="md:hidden p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        
        <nav class="flex-1 overflow-y-auto pl-3.5 pr-2 py-4 space-y-6 custom-scrollbar">

            
            <div>
                <div class="sidebar-group-header">
                    <p class="px-3 text-[10px] font-bold text-slate-400/90 dark:text-slate-500 uppercase tracking-widest mb-2 sidebar-text">Operasional</p>
                    <div class="sidebar-divider hidden border-t border-slate-200/80 dark:border-slate-700/60 my-2.5 mx-2"></div>
                </div>
                <div class="space-y-1">

                    <?php if(auth()->user()->hasPermission('dashboard.view')): ?>
                    <a href="/" title="Dashboard"
                       class="sidebar-nav-item <?php echo e(Request::is('/') ? 'sidebar-nav-item-active' : ''); ?>">
                        <div class="flex items-center gap-3 sidebar-item-content">
                            <svg class="h-5 w-5 shrink-0 text-slate-400 dark:text-slate-500 <?php echo e(Request::is('/') ? 'text-sky-600 dark:text-sky-400' : ''); ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12c.552 0 1.005-.449.95-.998a10 10 0 0 0-8.953-8.951c-.55-.055-.998.398-.998.95v8a1 1 0 0 0 1 1z"/><path stroke-linecap="round" stroke-linejoin="round" d="M21.21 15.89A10 10 0 1 1 8 2.83"/>
                            </svg>
                            <span class="sidebar-text">Dashboard</span>
                        </div>
                    </a>
                    <?php endif; ?>

                    <?php if(auth()->user()->hasPermission('customers.view') || auth()->user()->hasPermission('customers.create') || auth()->user()->hasPermission('customers.import.import') || auth()->user()->hasPermission('customers.import.view') || auth()->user()->hasPermission('customers.detail.survey.view') || auth()->user()->hasPermission('customers.detail.installation.view') || auth()->user()->hasPermission('customers.terminated.view') || auth()->user()->hasPermission('customers.failed.view')): ?>
                    
                    <div class="space-y-1">
                        <button onclick="toggleSubmenu('submenu-pelanggan', 'chevron-pelanggan')"
                                title="Pelanggan"
                                class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                                       <?php echo e(Request::is('customers*') || Request::is('surveys*') || Request::is('verifications*')
                                           ? 'bg-sky-50 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300 font-semibold'
                                           : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100/80 dark:hover:bg-slate-700/60 hover:text-slate-900 dark:hover:text-slate-50'); ?>">
                            <div class="flex items-center gap-3 sidebar-item-content">
                                <svg class="h-5 w-5 shrink-0 <?php echo e(Request::is('customers*') || Request::is('surveys*') || Request::is('verifications*') ? 'text-sky-600 dark:text-sky-400' : 'text-slate-400 dark:text-slate-500'); ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M22 21v-2a4 4 0 0 0-3-3.87"/><path stroke-linecap="round" stroke-linejoin="round" d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                                <span class="sidebar-text">Pelanggan</span>
                            </div>
                            <svg id="chevron-pelanggan"
                                 class="chevron-icon h-3.5 w-3.5 shrink-0 transition-transform duration-300
                                        <?php echo e(Request::is('customers*') || Request::is('surveys*') || Request::is('verifications*') ? 'rotate-180 text-sky-600 dark:text-sky-400' : 'text-slate-300 dark:text-slate-600'); ?>"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/>
                            </svg>
                        </button>

                        <div id="submenu-pelanggan"
                             class="submenu-container <?php echo e(Request::is('customers*') || Request::is('surveys*') || Request::is('verifications*') ? 'is-open' : ''); ?>">
                            <div class="submenu-inner mt-1 ml-3.5 pl-3 border-l border-slate-200/80 dark:border-slate-700/60 space-y-0.5 text-xs pr-1">

                                <?php if(auth()->user()->hasPermission('customers.create')): ?>
                                <a href="/customers/create"
                                   class="block py-1.5 px-3 rounded-md transition-colors
                                          <?php echo e(Request::is('customers/create') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">
                                    Registrasi Pelanggan
                                </a>
                                <?php endif; ?>

                                <?php if(auth()->user()->hasPermission('customers.detail.survey.view')): ?>
                                <a href="<?php echo e(route('surveys.queue')); ?>"
                                   class="flex items-center justify-between py-1.5 px-3 rounded-md transition-colors
                                          <?php echo e(Request::routeIs('surveys.queue') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">
                                    <span>Antrean Survey</span>
                                    <?php if(isset($badge_survey_count) && $badge_survey_count > 0): ?>
                                        <span class="bg-sky-500/15 text-sky-600 dark:text-sky-400 border border-sky-500/20 text-[9px] font-bold px-1.5 py-0.5 rounded-full"><?php echo e($badge_survey_count); ?></span>
                                    <?php endif; ?>
                                </a>
                                <?php endif; ?>
                                <?php if(auth()->user()->hasPermission('customers.detail.installation.view')): ?>
                                <a href="<?php echo e(route('verifications.queue')); ?>"
                                   class="flex items-center justify-between py-1.5 px-3 rounded-md transition-colors
                                          <?php echo e(Request::routeIs('verifications.queue') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">
                                    <span>Verif &amp; Pemasangan</span>
                                    <?php if(isset($badge_verification_count) && $badge_verification_count > 0): ?>
                                        <span class="bg-sky-500/15 text-sky-600 dark:text-sky-400 border border-sky-500/20 text-[9px] font-bold px-1.5 py-0.5 rounded-full"><?php echo e($badge_verification_count); ?></span>
                                    <?php endif; ?>
                                </a>
                                <?php endif; ?>
                                <?php if(auth()->user()->hasPermission('customers.view')): ?>
                                <a href="/customers"
                                   aria-current="<?php echo e(Request::is('customers') && !Request::is('customers/create') && !Request::is('customers/import') && !request()->has('status_group') ? 'page' : ''); ?>"
                                   class="block py-1.5 px-3 rounded-md transition-colors
                                          <?php echo e(Request::is('customers') && !Request::is('customers/create') && !Request::is('customers/import') && !request()->has('status_group') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">
                                    List Pelanggan
                                </a>
                                <?php endif; ?>
                                <?php if(auth()->user()->hasPermission('customers.failed.view')): ?>
                                <a href="<?php echo e(route('customers.failed')); ?>"
                                   class="block py-1.5 px-3 rounded-md transition-colors
                                          <?php echo e(Request::routeIs('customers.failed') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">
                                    Pelanggan Gagal
                                </a>
                                <?php endif; ?>
                                <?php if(auth()->user()->hasPermission('customers.terminated.view')): ?>
                                <a href="<?php echo e(route('customers.terminated')); ?>"
                                   class="block py-1.5 px-3 rounded-md transition-colors
                                          <?php echo e(Request::routeIs('customers.terminated') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">
                                    Pelanggan Putus
                                </a>
                                <?php endif; ?>

                                <?php if(auth()->user()->hasPermission('customers.import.import') || auth()->user()->hasPermission('customers.import.view') || auth()->user()->hasPermission('customers.import')): ?>
                                <a href="/customers/import"
                                   class="block py-1.5 px-3 rounded-md transition-colors
                                          <?php echo e(Request::is('customers/import') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">
                                    Import Pelanggan
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if(auth()->user()->hasPermission('invoices.view')): ?>
                    <div class="space-y-1">
                        <button onclick="toggleSubmenu('submenu-tagihan', 'chevron-tagihan')"
                                title="Tagihan"
                                class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                                       <?php echo e(Request::is('invoices*') ? 'bg-sky-50 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300 font-semibold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100/80 dark:hover:bg-slate-700/60 hover:text-slate-900 dark:hover:text-slate-50'); ?>">
                            <div class="flex items-center gap-3 sidebar-item-content">
                                <svg class="h-5 w-5 shrink-0 <?php echo e(Request::is('invoices*') ? 'text-sky-600 dark:text-sky-400' : 'text-slate-400 dark:text-slate-500'); ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M14 8H8"/><path d="M16 12H8"/><path d="M13 16H8"/>
                                </svg>
                                <span class="sidebar-text">Tagihan</span>
                            </div>
                            <svg id="chevron-tagihan"
                                 class="chevron-icon h-3.5 w-3.5 shrink-0 transition-transform duration-300 <?php echo e(Request::is('invoices*') ? 'rotate-180 text-sky-600 dark:text-sky-400' : 'text-slate-300 dark:text-slate-600'); ?>"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/>
                            </svg>
                        </button>
                        
                        <div id="submenu-tagihan"
                             class="submenu-container <?php echo e(Request::is('invoices*') ? 'is-open' : ''); ?>">
                            <div class="submenu-inner mt-1 ml-3.5 pl-3 border-l border-slate-200/80 dark:border-slate-700/60 space-y-0.5 text-xs pr-1">
                                <a href="<?php echo e(route('invoices.belum-lunas')); ?>"
                                   class="block py-1.5 px-3 rounded-md transition-colors <?php echo e(Request::routeIs('invoices.belum-lunas') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">
                                    Tagihan Belum Lunas
                                </a>
                                <a href="<?php echo e(route('invoices.index')); ?>"
                                   class="block py-1.5 px-3 rounded-md transition-colors <?php echo e(Request::routeIs('invoices.index') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">
                                    Semua Tagihan
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if(auth()->user()->hasPermission('payments.view')): ?>
                    <div class="space-y-1">
                        <button onclick="toggleSubmenu('submenu-pembayaran', 'chevron-pembayaran')"
                                title="Pembayaran"
                                class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                                       <?php echo e(Request::is('payments*') ? 'bg-sky-50 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300 font-semibold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100/80 dark:hover:bg-slate-700/60 hover:text-slate-900 dark:hover:text-slate-50'); ?>">
                            <div class="flex items-center gap-3 sidebar-item-content">
                                <svg class="h-5 w-5 shrink-0 <?php echo e(Request::is('payments*') ? 'text-sky-600 dark:text-sky-400' : 'text-slate-400 dark:text-slate-500'); ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>
                                </svg>
                                <span class="sidebar-text">Pembayaran</span>
                            </div>
                            <svg id="chevron-pembayaran"
                                 class="chevron-icon h-3.5 w-3.5 shrink-0 transition-transform duration-300 <?php echo e(Request::is('payments*') ? 'rotate-180 text-sky-600 dark:text-sky-400' : 'text-slate-300 dark:text-slate-600'); ?>"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/>
                            </svg>
                        </button>
                        
                        <div id="submenu-pembayaran"
                             class="submenu-container <?php echo e(Request::is('payments*') ? 'is-open' : ''); ?>">
                            <div class="submenu-inner mt-1 ml-3.5 pl-3 border-l border-slate-200/80 dark:border-slate-700/60 space-y-0.5 text-xs pr-1">
                                <a href="<?php echo e(route('payments.index')); ?>"
                                   class="block py-1.5 px-3 rounded-md transition-colors <?php echo e(Request::routeIs('payments.index') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">
                                    Riwayat Transaksi Pembayaran
                                </a>
                                <a href="<?php echo e(route('payments.overpay')); ?>"
                                   class="block py-1.5 px-3 rounded-md transition-colors <?php echo e(Request::routeIs('payments.overpay') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">
                                    Pembayaran Lebih (Overpay)
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if(auth()->user()->hasPermission('collector_worksheet.view')): ?>
                    <a href="<?php echo e(route('collector-worksheet.index')); ?>" title="Worksheet Admin (Kolektor)"
                       class="flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                              <?php echo e(Request::is('collector-worksheet*') || Request::is('payment-batches*') ? 'bg-sky-50 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300 font-semibold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100/80 dark:hover:bg-slate-700/60 hover:text-slate-900 dark:hover:text-slate-50'); ?>">
                        <div class="flex items-center gap-3 sidebar-item-content">
                            <svg class="h-5 w-5 shrink-0 <?php echo e(Request::is('collector-worksheet*') || Request::is('payment-batches*') ? 'text-sky-600 dark:text-sky-400' : 'text-slate-400 dark:text-slate-500'); ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 0 0-3-3.87M9 20H4v-2a4 4 0 0 1 3-3.87m6-8.13a4 4 0 1 1 0 8 4 4 0 0 1 0-8Zm6 8a4 4 0 1 1 0-8"/>
                            </svg>
                            <span class="sidebar-text">Worksheet Admin</span>
                        </div>
                    </a>
                    <?php endif; ?>

                    <?php if(auth()->user()->hasPermission('cash_deposit.view')): ?>
                    <a href="<?php echo e(route('cash-deposits.index')); ?>" title="Setoran Kas (Admin → Owner/Bank)"
                       class="flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                              <?php echo e(Request::is('cash-deposits*') ? 'bg-sky-50 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300 font-semibold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100/80 dark:hover:bg-slate-700/60 hover:text-slate-900 dark:hover:text-slate-50'); ?>">
                        <div class="flex items-center gap-3 sidebar-item-content">
                            <svg class="h-5 w-5 shrink-0 <?php echo e(Request::is('cash-deposits*') ? 'text-sky-600 dark:text-sky-400' : 'text-slate-400 dark:text-slate-500'); ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 0 0 3-3V8a3 3 0 0 0-3-3H6a3 3 0 0 0-3 3v8a3 3 0 0 0 3 3Z"/>
                            </svg>
                            <span class="sidebar-text">Setoran Kas</span>
                        </div>
                    </a>
                    <?php endif; ?>

                    <?php if(auth()->user()->hasPermission('kolektor.view')): ?>
                    <a href="<?php echo e(route('collector-worklist.index')); ?>" title="Worklist Kolektor"
                       class="flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                              <?php echo e(Request::is('collector-worklist*') ? 'bg-sky-50 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300 font-semibold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100/80 dark:hover:bg-slate-700/60 hover:text-slate-900 dark:hover:text-slate-50'); ?>">
                        <div class="flex items-center gap-3 sidebar-item-content">
                            <svg class="h-5 w-5 shrink-0 <?php echo e(Request::is('collector-worklist*') ? 'text-sky-600 dark:text-sky-400' : 'text-slate-400 dark:text-slate-500'); ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2m-6 9 2 2 4-4"/>
                            </svg>
                            <span class="sidebar-text">Worklist Kolektor</span>
                        </div>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            
            <?php if(auth()->user()->hasPermission('task.view.all') || auth()->user()->hasPermission('task.view.own') || auth()->user()->hasPermission('tickets.view')): ?>
            <div>
                <div class="sidebar-group-header">
                    <p class="px-3 text-[10px] font-bold text-slate-400/90 dark:text-slate-500 uppercase tracking-widest mb-2 sidebar-text">Jaringan &amp; Lapangan</p>
                    <div class="sidebar-divider hidden border-t border-slate-200/80 dark:border-slate-700/60 my-2.5 mx-2"></div>
                </div>
                <div class="space-y-1">

                    <?php if(auth()->user()->hasPermission('task.view.all') || auth()->user()->hasPermission('task.view.own')): ?>
                    <?php if(auth()->user()->hasPermission('task.view.all')): ?>
                    <div class="space-y-1">
                        <button onclick="toggleSubmenu('submenu-tasks', 'chevron-tasks')"
                                title="Penjadwalan Teknis"
                                class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                                       <?php echo e(Request::is('tasks*') || Request::is('fop*') ? 'bg-sky-50 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300 font-semibold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100/80 dark:hover:bg-slate-700/60 hover:text-slate-900 dark:hover:text-slate-50'); ?>">
                            <div class="flex items-center gap-3 sidebar-item-content">
                                <svg class="h-5 w-5 shrink-0 <?php echo e(Request::is('tasks*') || Request::is('fop*') ? 'text-sky-600 dark:text-sky-400' : 'text-slate-400 dark:text-slate-500'); ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m3 17 2 2 4-4"/><path stroke-linecap="round" stroke-linejoin="round" d="m3 7 2 2 4-4"/><path d="M13 6h8"/><path d="M13 12h8"/><path d="M13 18h8"/>
                                </svg>
                                <span class="sidebar-text">Penjadwalan Teknis</span>
                            </div>
                            <svg id="chevron-tasks"
                                 class="chevron-icon h-3.5 w-3.5 shrink-0 transition-transform duration-300 <?php echo e(Request::is('tasks*') || Request::is('fop*') ? 'rotate-180 text-sky-600 dark:text-sky-400' : 'text-slate-300 dark:text-slate-600'); ?>"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/>
                            </svg>
                        </button>
                        <div id="submenu-tasks"
                             class="submenu-container <?php echo e(Request::is('tasks*') || Request::is('fop*') ? 'is-open' : ''); ?>">
                            <div class="submenu-inner mt-1 ml-3.5 pl-3 border-l border-slate-200/80 dark:border-slate-700/60 space-y-0.5 text-xs pr-1">
                                <a href="<?php echo e(route('fop.dashboard')); ?>"
                                   class="block py-1.5 px-3 rounded-md transition-colors <?php echo e(Request::routeIs('fop.dashboard') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">
                                    FOP Dashboard
                                </a>
                                <?php if(auth()->user()->hasPermission('fop_tasks.view')): ?>
                                <a href="<?php echo e(route('fop-tasks.index')); ?>"
                                   class="block py-1.5 px-3 rounded-md transition-colors <?php echo e(Request::routeIs('fop-tasks.index') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">
                                    Task FOP
                                </a>
                                <a href="<?php echo e(route('fop-tasks.history')); ?>"
                                   class="block py-1.5 px-3 rounded-md transition-colors <?php echo e(Request::routeIs('fop-tasks.history') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">
                                    Riwayat Task FOP
                                </a>
                                <?php endif; ?>
                                <?php if(auth()->user()->hasPermission('task.view.own')): ?>
                                <a href="<?php echo e(route('tasks.own')); ?>"
                                   class="block py-1.5 px-3 rounded-md transition-colors <?php echo e(Request::routeIs('tasks.own') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">
                                    Task Saya
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <a href="<?php echo e(route('tasks.own')); ?>" title="Task Saya"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                              <?php echo e(Request::routeIs('tasks.own') ? 'bg-sky-50 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300 font-semibold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100/80 dark:hover:bg-slate-700/60 hover:text-slate-900 dark:hover:text-slate-50'); ?>">
                        <div class="flex items-center gap-3 sidebar-item-content">
                            <svg class="h-5 w-5 shrink-0 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m3 17 2 2 4-4"/><path d="M13 6h8"/><path d="M13 12h8"/><path d="M13 18h8"/>
                            </svg>
                            <span class="sidebar-text">Task Saya</span>
                        </div>
                    </a>
                    <?php endif; ?>
                    <?php endif; ?>

                    <?php if(auth()->user()->hasPermission('tickets.view') || auth()->user()->hasPermission('tickets.create') || auth()->user()->hasPermission('noc_worksheet.view') || auth()->user()->hasPermission('noc_dashboard.view')): ?>
                    <div class="space-y-1">
                        <button onclick="toggleSubmenu('submenu-ticketing', 'chevron-ticketing')"
                                title="Ticketing"
                                class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                                       <?php echo e((Request::is('tickets*') || Request::is('noc/*')) ? 'bg-sky-50 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300 font-semibold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100/80 dark:hover:bg-slate-700/60 hover:text-slate-900 dark:hover:text-slate-50'); ?>">
                            <div class="flex items-center gap-3 sidebar-item-content">
                                <svg class="h-5 w-5 shrink-0 <?php echo e((Request::is('tickets*') || Request::is('noc/*')) ? 'text-sky-600 dark:text-sky-400' : 'text-slate-400 dark:text-slate-500'); ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 11h3a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 11h-3a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1z"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 11a9 9 0 1 1 18 0"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 16v2a4 4 0 0 1-4 4h-5"/>
                                </svg>
                                <span class="sidebar-text">Ticketing</span>
                            </div>
                            <svg id="chevron-ticketing"
                                 class="chevron-icon h-3.5 w-3.5 shrink-0 transition-transform duration-300 <?php echo e((Request::is('tickets*') || Request::is('noc/*')) ? 'rotate-180 text-sky-600 dark:text-sky-400' : 'text-slate-300 dark:text-slate-600'); ?>"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/>
                            </svg>
                        </button>
                        <div id="submenu-ticketing"
                             class="submenu-container <?php echo e((Request::is('tickets*') || Request::is('noc/*')) ? 'is-open' : ''); ?>">
                            <div class="submenu-inner mt-1 ml-3.5 pl-3 border-l border-slate-200/80 dark:border-slate-700/60 space-y-0.5 text-xs pr-1">
                                <?php if(auth()->user()->hasPermission('tickets.create') || auth()->user()->hasPermission('tickets.view') || auth()->user()->hasPermission('tickets.update')): ?>
                                <a href="<?php echo e(route('tickets.create')); ?>"
                                   class="block py-1.5 px-3 rounded-md transition-colors <?php echo e(Request::routeIs('tickets.create') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">
                                    Worksheet Helpdesk
                                </a>
                                <?php endif; ?>

                                <?php if(auth()->user()->hasPermission('noc_worksheet.view')): ?>
                                <a href="<?php echo e(route('noc.worksheet')); ?>"
                                   class="block py-1.5 px-3 rounded-md transition-colors <?php echo e(Request::routeIs('noc.worksheet*') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">
                                    Worksheet NOC
                                </a>
                                <?php endif; ?>

                                <?php if(auth()->user()->hasPermission('noc_dashboard.view')): ?>
                                <a href="<?php echo e(route('noc.dashboard')); ?>"
                                   class="block py-1.5 px-3 rounded-md transition-colors <?php echo e(Request::routeIs('noc.dashboard') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">
                                    Dashboard NOC
                                </a>
                                <?php endif; ?>

                                <?php if(auth()->user()->hasPermission('tickets.selesai.view')): ?>
                                <a href="<?php echo e(route('tickets.selesai')); ?>"
                                   class="block py-1.5 px-3 rounded-md transition-colors <?php echo e(Request::routeIs('tickets.selesai') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">
                                    Ticket Selesai
                                </a>
                                <?php endif; ?>
                                <?php if(auth()->user()->hasPermission('tickets.dibatalkan.view')): ?>
                                <a href="<?php echo e(route('tickets.dibatalkan')); ?>"
                                   class="block py-1.5 px-3 rounded-md transition-colors <?php echo e(Request::routeIs('tickets.dibatalkan') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">
                                    Ticket Dibatalkan
                                </a>
                                <?php endif; ?>

                                <?php if(auth()->user()->hasPermission('tickets.history.view')): ?>
                                <a href="<?php echo e(route('tickets.history')); ?>"
                                   class="block py-1.5 px-3 rounded-md transition-colors <?php echo e(Request::routeIs('tickets.history') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">
                                    History Ticketing
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            
            <?php if(auth()->user()->hasPermission('reports.view') || auth()->user()->hasPermission('audit_logs.view')): ?>
            <div>
                <div class="sidebar-group-header">
                    <p class="px-3 text-[10px] font-bold text-slate-400/90 dark:text-slate-500 uppercase tracking-widest mb-2 sidebar-text">Laporan</p>
                    <div class="sidebar-divider hidden border-t border-slate-200/80 dark:border-slate-700/60 my-2.5 mx-2"></div>
                </div>
                <div class="space-y-1">

                    <?php if(auth()->user()->hasPermission('reports.view')): ?>
                    <div class="space-y-1">
                        <button onclick="toggleSubmenu('submenu-laporan', 'chevron-laporan')"
                                title="Laporan Keuangan"
                                class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                                       <?php echo e(Request::is('reports*') ? 'bg-sky-50 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300 font-semibold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100/80 dark:hover:bg-slate-700/60 hover:text-slate-900 dark:hover:text-slate-50'); ?>">
                            <div class="flex items-center gap-3 sidebar-item-content">
                                <svg class="h-5 w-5 shrink-0 <?php echo e(Request::is('reports*') ? 'text-sky-600 dark:text-sky-400' : 'text-slate-400 dark:text-slate-500'); ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v16a2 2 0 0 0 2 2h16"/><path stroke-linecap="round" stroke-linejoin="round" d="m19 9-5 5-4-4-3 3"/>
                                </svg>
                                <span class="sidebar-text">Laporan Keuangan</span>
                            </div>
                            <svg id="chevron-laporan"
                                 class="chevron-icon h-3.5 w-3.5 shrink-0 transition-transform duration-300 <?php echo e(Request::is('reports*') ? 'rotate-180 text-sky-600 dark:text-sky-400' : 'text-slate-300 dark:text-slate-600'); ?>"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/>
                            </svg>
                        </button>
                        <div id="submenu-laporan"
                             class="submenu-container <?php echo e(Request::is('reports*') ? 'is-open' : ''); ?>">
                            <div class="submenu-inner mt-1 ml-3.5 pl-3 border-l border-slate-200/80 dark:border-slate-700/60 space-y-0.5 text-xs pr-1">
                                <a href="<?php echo e(route('reports.customers.index')); ?>"
                                   class="block py-1.5 px-3 rounded-md transition-colors <?php echo e(Request::is('reports/customers*') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">
                                    Laporan Pelanggan
                                </a>
                                <a href="<?php echo e(route('reports.invoices.index')); ?>"
                                   class="block py-1.5 px-3 rounded-md transition-colors <?php echo e(Request::is('reports/invoices*') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">
                                    Laporan Tagihan
                                </a>
                                <a href="<?php echo e(route('reports.payments.index')); ?>"
                                   class="block py-1.5 px-3 rounded-md transition-colors <?php echo e(Request::is('reports/payments*') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">
                                    Laporan Pembayaran
                                </a>
                                <a href="<?php echo e(route('reports.imports.index')); ?>"
                                   class="block py-1.5 px-3 rounded-md transition-colors <?php echo e(Request::is('reports/imports*') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">
                                    Laporan Import Data
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if(auth()->user()->hasPermission('audit_logs.view')): ?>
                    <a href="<?php echo e(route('audit-logs.index')); ?>" title="Audit Log"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                              <?php echo e(Request::is('audit-logs*') ? 'bg-sky-50 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300 font-semibold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100/80 dark:hover:bg-slate-700/60 hover:text-slate-900 dark:hover:text-slate-50'); ?>">
                        <div class="flex items-center gap-3 sidebar-item-content">
                            <svg class="h-5 w-5 shrink-0 <?php echo e(Request::is('audit-logs*') ? 'text-sky-600 dark:text-sky-400' : 'text-slate-400 dark:text-slate-500'); ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                            <span class="sidebar-text">Audit Log</span>
                        </div>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            
            <?php if(auth()->user()->hasPermission('pops.view') || auth()->user()->hasPermission('packages.view') || auth()->user()->hasPermission('master_wilayah.view') || auth()->user()->hasPermission('master_distribusi.view') || auth()->user()->hasPermission('master_status_pelanggan.view') || auth()->user()->hasPermission('sla_timeline.view') || auth()->user()->hasPermission('ticket_issue_categories.view') || auth()->user()->hasPermission('items.view') || auth()->user()->hasPermission('item_categories.view') || auth()->user()->hasPermission('work_tools.view') || auth()->user()->hasPermission('users.view') || auth()->user()->hasPermission('roles.view')): ?>
            <div>
                <div class="sidebar-group-header">
                    <p class="px-3 text-[10px] font-bold text-slate-400/90 dark:text-slate-500 uppercase tracking-widest mb-2 sidebar-text">Master &amp; Pengaturan</p>
                    <div class="sidebar-divider hidden border-t border-slate-200/80 dark:border-slate-700/60 my-2.5 mx-2"></div>
                </div>
                <div class="space-y-1">

                    <?php if(auth()->user()->hasPermission('pops.view') || auth()->user()->hasPermission('packages.view') || auth()->user()->hasPermission('master_wilayah.view') || auth()->user()->hasPermission('master_distribusi.view') || auth()->user()->hasPermission('master_status_pelanggan.view') || auth()->user()->hasPermission('sla_timeline.view') || auth()->user()->hasPermission('ticket_issue_categories.view') || auth()->user()->hasPermission('items.view') || auth()->user()->hasPermission('item_categories.view') || auth()->user()->hasPermission('work_tools.view')): ?>
                    <div class="space-y-1">
                        <button onclick="toggleSubmenu('submenu-master', 'chevron-master')"
                                title="Master Data"
                                class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                                       <?php echo e(Request::is('master*') ? 'bg-sky-50 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300 font-semibold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100/80 dark:hover:bg-slate-700/60 hover:text-slate-900 dark:hover:text-slate-50'); ?>">
                            <div class="flex items-center gap-3 sidebar-item-content">
                                <svg class="h-5 w-5 shrink-0 <?php echo e(Request::is('master*') ? 'text-sky-600 dark:text-sky-400' : 'text-slate-400 dark:text-slate-500'); ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <ellipse cx="12" cy="5" rx="9" ry="3"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 5V19A9 3 0 0 0 21 19V5"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 12A9 3 0 0 0 21 12"/>
                                </svg>
                                <span class="sidebar-text">Master Data</span>
                            </div>
                            <svg id="chevron-master"
                                 class="chevron-icon h-3.5 w-3.5 shrink-0 transition-transform duration-300 <?php echo e(Request::is('master*') ? 'rotate-180 text-sky-600 dark:text-sky-400' : 'text-slate-300 dark:text-slate-600'); ?>"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/>
                            </svg>
                        </button>
                        <div id="submenu-master"
                             class="submenu-container <?php echo e(Request::is('master*') ? 'is-open' : ''); ?>">
                            <div class="submenu-inner mt-1 ml-3.5 pl-3 border-l border-slate-200/80 dark:border-slate-700/60 space-y-0.5 text-xs pr-1">
                                <?php if(auth()->user()->hasPermission('master_wilayah.view')): ?>
                                <a href="/master/wilayah" class="block py-1.5 px-3 rounded-md transition-colors <?php echo e(Request::is('master/wilayah') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">Master Data Wilayah</a>
                                <?php endif; ?>
                                <?php if(auth()->user()->hasPermission('pops.view')): ?>
                                <a href="/master/pop" class="block py-1.5 px-3 rounded-md transition-colors <?php echo e(Request::is('master/pop*') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">Master POP/Cabang</a>
                                <?php endif; ?>
                                <?php if(auth()->user()->hasPermission('master_distribusi.view')): ?>
                                <a href="/master/distribusi" class="block py-1.5 px-3 rounded-md transition-colors <?php echo e(Request::is('master/distribusi*') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">Master Distribusi</a>
                                <?php endif; ?>
                                <?php if(auth()->user()->hasPermission('packages.view')): ?>
                                <a href="/master/paket" class="block py-1.5 px-3 rounded-md transition-colors <?php echo e(Request::is('master/paket*') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">Master Paket Internet</a>
                                <?php endif; ?>
                                <?php if(auth()->user()->hasPermission('master_status_pelanggan.view')): ?>
                                <a href="/master/status-langganan" class="block py-1.5 px-3 rounded-md transition-colors <?php echo e(Request::is('master/status-langganan') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">Master Status Pelanggan</a>
                                <?php endif; ?>
                                <?php if(auth()->user()->hasPermission('sla_timeline.view')): ?>
                                <a href="/master/sla-timeline" class="block py-1.5 px-3 rounded-md transition-colors <?php echo e(Request::is('master/sla-timeline*') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">Master Timeline SLA</a>
                                <?php endif; ?>
                                <?php if(auth()->user()->hasPermission('ticket_issue_categories.view')): ?>
                                <a href="/master/issue-categories" class="block py-1.5 px-3 rounded-md transition-colors <?php echo e(Request::is('master/issue-categories*') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">Master Kategori Tiket</a>
                                <?php endif; ?>
                                <?php if(auth()->user()->hasPermission('items.view')): ?>
                                <a href="/master/items" class="block py-1.5 px-3 rounded-md transition-colors <?php echo e(Request::is('master/items*') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">Master Item (Barang)</a>
                                <?php endif; ?>
                                <?php if(auth()->user()->hasPermission('item_categories.view')): ?>
                                <a href="/master/item-categories" class="block py-1.5 px-3 rounded-md transition-colors <?php echo e(Request::is('master/item-categories*') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">Master Kategori Item</a>
                                <?php endif; ?>
                                <?php if(auth()->user()->hasPermission('work_tools.view')): ?>
                                <a href="/master/work-tools" class="block py-1.5 px-3 rounded-md transition-colors <?php echo e(Request::is('master/work-tools*') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">Master Alat Kerja</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if(auth()->user()->hasPermission('users.view') || auth()->user()->hasPermission('roles.view')): ?>
                    <div class="space-y-1">
                        <button onclick="toggleSubmenu('submenu-settings', 'chevron-settings')"
                                title="Pengguna & RBAC"
                                class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                                       <?php echo e(Request::is('users*') || Request::is('roles*') ? 'bg-sky-50 dark:bg-sky-900/30 text-sky-700 dark:text-sky-300 font-semibold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100/80 dark:hover:bg-slate-700/60 hover:text-slate-900 dark:hover:text-slate-50'); ?>">
                            <div class="flex items-center gap-3 sidebar-item-content">
                                <svg class="h-5 w-5 shrink-0 <?php echo e(Request::is('users*') || Request::is('roles*') ? 'text-sky-600 dark:text-sky-400' : 'text-slate-400 dark:text-slate-500'); ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <circle cx="18" cy="15" r="3"/><circle cx="9" cy="7" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M10 15H6a4 4 0 0 0-4 4v2"/>
                                </svg>
                                <span class="sidebar-text">Pengguna &amp; RBAC</span>
                            </div>
                            <svg id="chevron-settings"
                                 class="chevron-icon h-3.5 w-3.5 shrink-0 transition-transform duration-300 <?php echo e(Request::is('users*') || Request::is('roles*') ? 'rotate-180 text-sky-600 dark:text-sky-400' : 'text-slate-300 dark:text-slate-600'); ?>"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/>
                            </svg>
                        </button>
                        <div id="submenu-settings"
                             class="submenu-container <?php echo e(Request::is('users*') || Request::is('roles*') ? 'is-open' : ''); ?>">
                            <div class="submenu-inner mt-1 ml-3.5 pl-3 border-l border-slate-200/80 dark:border-slate-700/60 space-y-0.5 text-xs pr-1">
                                <?php if(auth()->user()->hasPermission('users.view')): ?>
                                <a href="/users" class="block py-1.5 px-3 rounded-md transition-colors <?php echo e(Request::is('users*') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">Manajemen User &amp; POP</a>
                                <?php endif; ?>
                                <?php if(auth()->user()->hasPermission('roles.view')): ?>
                                <a href="<?php echo e(route('roles.index')); ?>" class="block py-1.5 px-3 rounded-md transition-colors <?php echo e(Request::is('roles*') ? 'sidebar-subitem-active' : 'text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-sky-50/50 dark:hover:bg-sky-900/20'); ?>">Role &amp; Permission</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        </nav>

        
        <div class="p-3 border-t border-slate-100 dark:border-slate-800/60 bg-slate-50/60 dark:bg-slate-900/60 shrink-0 sidebar-footer">
            <div class="flex items-center justify-between p-2 rounded-xl bg-white dark:bg-slate-800/80 border border-slate-200/70 dark:border-slate-700/70 shadow-xs sidebar-footer-box transition-all hover:border-slate-300 dark:hover:border-slate-600">
                <div class="flex items-center gap-2.5 overflow-hidden sidebar-user-info">
                    <div class="relative shrink-0">
                        <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-sky-500 to-indigo-600 text-white font-bold text-xs flex items-center justify-center shadow-xs" title="<?php echo e(Auth::user()->name ?? 'Administrator'); ?>">
                            <?php echo e(strtoupper(substr(Auth::user()->name ?? 'AD', 0, 2))); ?>

                        </div>
                        <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-emerald-500 rounded-full ring-2 ring-white dark:ring-slate-800"></span>
                    </div>
                    <div class="truncate sidebar-footer-info">
                        <p class="text-xs font-semibold text-slate-800 dark:text-slate-100 truncate"><?php echo e(Auth::user()->name ?? 'Administrator'); ?></p>
                        <p class="text-[10px] text-slate-400 dark:text-slate-500 truncate"><?php echo e(Auth::user()->email ?? 'admin@whusnet.net'); ?></p>
                    </div>
                </div>
                <form id="logout-form" action="<?php echo e(route('logout')); ?>" method="POST" class="hidden"><?php echo csrf_field(); ?></form>
                <button onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                        class="p-1.5 text-slate-400 dark:text-slate-500 hover:text-rose-600 dark:hover:text-rose-400 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors sidebar-footer-info"
                        title="Keluar">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16 17 5-5-5-5"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 12H9"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    </svg>
                </button>
            </div>
        </div>
    </aside>

    
    <div id="sidebar-backdrop"
         onclick="toggleSidebar()"
         class="fixed inset-0 z-30 bg-slate-900/40 dark:bg-slate-950/60 backdrop-blur-sm hidden md:hidden"></div>

    
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

        
        <header class="glass-header sticky top-0 z-30 border-b border-slate-200/80 dark:border-slate-700 px-4 sm:px-6 h-16 flex items-center justify-between gap-4">

            
            <div class="flex items-center gap-2 min-w-0">
                
                <button onclick="toggleSidebar()"
                        class="md:hidden p-2 text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-100 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 12h16M4 6h16M4 18h16"/>
                    </svg>
                </button>

                
                <nav aria-label="Breadcrumb" class="flex items-center gap-1.5 text-xs sm:text-sm text-slate-500 dark:text-slate-400 min-w-0">
                    
                    <button onclick="toggleDesktopSidebar()"
                            class="hidden md:flex p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-500 hover:text-slate-800 dark:hover:text-slate-400 dark:hover:text-slate-100 transition-colors"
                            title="Toggle Sidebar">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="18" height="18" x="3" y="3" rx="2"/>
                            <path d="M9 3v18"/>
                        </svg>
                    </button>

                    <a href="/" class="hidden sm:flex items-center gap-1 hover:text-slate-700 dark:hover:text-slate-200 transition-colors shrink-0">
                        <span>Home</span>
                    </a>

                    <?php if (! empty(trim($__env->yieldContent('breadcrumb_parent')))): ?>
                    <svg class="hidden sm:block h-3 w-3 text-slate-300 dark:text-slate-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/>
                    </svg>
                    <a href="<?php echo $__env->yieldContent('breadcrumb_parent_url', '#'); ?>" class="hover:text-slate-700 dark:hover:text-slate-200 transition-colors truncate shrink-0">
                        <?php echo $__env->yieldContent('breadcrumb_parent'); ?>
                    </a>
                    <?php endif; ?>

                    <svg class="hidden sm:block h-3 w-3 text-slate-300 dark:text-slate-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/>
                    </svg>
                    <span aria-current="page" class="font-semibold text-sky-600 dark:text-sky-400 truncate"><?php echo $__env->yieldContent('page_title', 'Dashboard'); ?></span>
                </nav>
            </div>

            
            <div class="hidden md:block flex-1 max-w-sm lg:max-w-md">
                <div class="relative">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 dark:text-slate-500 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.3-4.3"/>
                    </svg>
                    <input type="search" id="globalSearch" onkeydown="handleGlobalSearch(event)"
                           placeholder="Cari pelanggan, CID, invoice, tiket, IP…"
                           class="w-full h-9 pl-10 pr-16 rounded-full border border-slate-200 dark:border-slate-700
                                  bg-white/70 dark:bg-slate-800/70 text-xs text-slate-800 dark:text-slate-100
                                  placeholder-slate-400 dark:placeholder-slate-500
                                  focus:outline-none focus:bg-white dark:focus:bg-slate-800
                                  focus:border-sky-600 dark:focus:border-sky-500
                                  focus:ring-2 focus:ring-sky-600/12 transition-all">
                    <kbd class="hidden lg:block absolute right-3 top-1/2 -translate-y-1/2 font-mono text-[10px] text-slate-400 dark:text-slate-500 border border-slate-200 dark:border-slate-700 rounded px-1.5 py-0.5 bg-slate-50 dark:bg-slate-900">/</kbd>
                </div>
            </div>

            
            <div class="flex items-center gap-1 sm:gap-2">
                
                <button onclick="toggleTheme(event)" id="themeToggle" aria-label="Ganti tema" title="Ganti Tema (Ctrl+D / Alt+T)"
                        class="p-2 text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400
                               hover:bg-sky-50 dark:hover:bg-sky-900/30 rounded-lg transition-all duration-200 active:scale-90 focus:outline-none focus:ring-2 focus:ring-sky-500/20">
                    <svg id="themeIconMoon" class="h-5 w-5 theme-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9"/>
                    </svg>
                    <svg id="themeIconSun" class="h-5 w-5 hidden theme-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>
                    </svg>
                </button>

                
                <button onclick="openHelp()" aria-label="Bantuan dan pintasan keyboard" title="Bantuan (?)"
                        class="p-2 text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400
                               hover:bg-sky-50 dark:hover:bg-sky-900/30 rounded-lg transition-colors">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 17h.01"/>
                    </svg>
                </button>

                
                <?php if (isset($component)) { $__componentOriginal0676521d0d1386b8a24fdc18016b8d4a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal0676521d0d1386b8a24fdc18016b8d4a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.notification-dropdown','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('notification-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal0676521d0d1386b8a24fdc18016b8d4a)): ?>
<?php $attributes = $__attributesOriginal0676521d0d1386b8a24fdc18016b8d4a; ?>
<?php unset($__attributesOriginal0676521d0d1386b8a24fdc18016b8d4a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal0676521d0d1386b8a24fdc18016b8d4a)): ?>
<?php $component = $__componentOriginal0676521d0d1386b8a24fdc18016b8d4a; ?>
<?php unset($__componentOriginal0676521d0d1386b8a24fdc18016b8d4a); ?>
<?php endif; ?>

                <div class="h-5 w-px bg-slate-200 dark:bg-slate-700"></div>

                
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" @click.outside="open = false"
                            class="flex items-center gap-2 py-1 pl-1 pr-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                        <span class="w-8 h-8 rounded-lg bg-sky-100 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300
                                     border border-sky-200 dark:border-sky-700 font-bold text-[11px]
                                     flex items-center justify-center">
                            <?php echo e(strtoupper(substr(Auth::user()->name ?? 'AD', 0, 2))); ?>

                        </span>
                        <span class="hidden lg:flex flex-col items-start leading-tight">
                            <span class="text-xs font-semibold text-slate-800 dark:text-slate-100"><?php echo e(Auth::user()->name ?? 'Administrator'); ?></span>
                            <span class="text-[10px] text-slate-400 dark:text-slate-500"><?php echo e(\App\Support\IndonesianDate::dateTime(now())); ?></span>
                        </span>
                        <svg class="h-3 w-3 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/>
                        </svg>
                    </button>

                    
                    <div x-show="open" x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-56 bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-lg py-1 text-xs z-50"
                         style="display:none">
                        <div class="px-3 py-2 border-b border-slate-100 dark:border-slate-700/60">
                            <p class="font-semibold text-slate-800 dark:text-slate-100"><?php echo e(Auth::user()->name ?? 'Administrator'); ?></p>
                            <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5"><?php echo e(Auth::user()->email ?? 'admin@whusnet.net'); ?></p>
                        </div>
                        <button class="w-full px-3 py-2 flex items-center gap-2.5 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors">
                            <svg class="h-4 w-4 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            Profil Saya
                        </button>
                        <div class="my-1 border-t border-slate-100 dark:border-slate-700/60"></div>
                        <button onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                                class="w-full px-3 py-2 flex items-center gap-2.5 text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-900/30 transition-colors">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m16 17 5-5-5-5"/><path stroke-linecap="round" stroke-linejoin="round" d="M21 12H9"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/></svg>
                            Keluar
                        </button>
                    </div>
                </div>
            </div>
        </header>

        
        <main class="flex-1 p-4 sm:p-6 lg:p-5 xl:p-8 overflow-y-auto">
            <?php echo $__env->yieldContent('content'); ?>
        </main>
    </div>
</div>


<script>
    /* ── Sidebar Toggle ── */
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');
        const isHidden = sidebar.classList.contains('-translate-x-full');
        if (isHidden) {
            sidebar.classList.remove('-translate-x-full');
            backdrop && backdrop.classList.remove('hidden');
        } else {
            sidebar.classList.add('-translate-x-full');
            backdrop && backdrop.classList.add('hidden');
        }
    }

    function toggleDesktopSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('collapsed');
        localStorage.setItem('sidebar-collapsed', sidebar.classList.contains('collapsed'));
    }

    function toggleSubmenu(menuId, chevronId) {
        const sidebar = document.getElementById('sidebar');
        if (sidebar.classList.contains('collapsed')) {
            sidebar.classList.remove('collapsed');
            localStorage.setItem('sidebar-collapsed', 'false');
        }
        const menu = document.getElementById(menuId);
        const chevron = document.getElementById(chevronId);
        if (menu) {
            menu.classList.toggle('is-open');
            chevron && chevron.classList.toggle('rotate-180');
        }
    }

    /* ── Restore sidebar collapse state ── */
    (function () {
        if (localStorage.getItem('sidebar-collapsed') === 'true') {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) sidebar.classList.add('collapsed');
        }
    })();

    /* ── Theme Toggle with Circular Reveal Motion & View Transitions API ── */
    function toggleTheme(event) {
        const html = document.documentElement;
        const isDarkNow = html.classList.contains('dark');
        const willBeDark = !isDarkNow;

        const performToggle = () => {
            html.classList.toggle('dark', willBeDark);
            localStorage.setItem('whusnet-theme', willBeDark ? 'dark' : 'light');
            syncThemeIcon(willBeDark);
        };

        const prefersReducedMotion = window.matchMedia('(prefers-color-scheme: reduce)').matches ||
                                     window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (document.startViewTransition && !prefersReducedMotion) {
            let x = window.innerWidth / 2;
            let y = window.innerHeight / 2;

            if (event && (event.clientX !== undefined || (event.touches && event.touches[0]))) {
                x = event.clientX ?? event.touches[0].clientX;
                y = event.clientY ?? event.touches[0].clientY;
            } else {
                const btn = document.getElementById('themeToggle');
                if (btn) {
                    const rect = btn.getBoundingClientRect();
                    x = rect.left + rect.width / 2;
                    y = rect.top + rect.height / 2;
                }
            }

            const endRadius = Math.hypot(
                Math.max(x, window.innerWidth - x),
                Math.max(y, window.innerHeight - y)
            );

            html.classList.add('theme-transitioning');

            const transition = document.startViewTransition(() => {
                performToggle();
            });

            transition.ready.then(() => {
                const clipPath = [
                    `circle(0px at ${x}px ${y}px)`,
                    `circle(${endRadius}px at ${x}px ${y}px)`
                ];

                const animation = html.animate(
                    {
                        clipPath: willBeDark ? clipPath : [...clipPath].reverse()
                    },
                    {
                        duration: 450,
                        easing: 'cubic-bezier(0.4, 0, 0.2, 1)',
                        pseudoElement: willBeDark
                            ? '::view-transition-new(root)'
                            : '::view-transition-old(root)'
                    }
                );

                animation.onfinish = () => {
                    html.classList.remove('theme-transitioning');
                };
            }).catch(() => {
                html.classList.remove('theme-transitioning');
            });
        } else {
            html.classList.add('theme-transitioning-fallback');
            performToggle();
            setTimeout(() => {
                html.classList.remove('theme-transitioning-fallback');
            }, 300);
        }
    }

    function syncThemeIcon(isDark) {
        const moon = document.getElementById('themeIconMoon');
        const sun = document.getElementById('themeIconSun');
        if (!moon || !sun) return;

        if (isDark) {
            moon.classList.add('hidden', 'scale-0', '-rotate-90');
            moon.classList.remove('scale-100', 'rotate-0');

            sun.classList.remove('hidden');
            requestAnimationFrame(() => {
                sun.classList.remove('scale-0', 'rotate-90', 'opacity-0');
                sun.classList.add('scale-100', 'rotate-0', 'opacity-100');
            });
        } else {
            sun.classList.add('hidden', 'scale-0', 'rotate-90');
            sun.classList.remove('scale-100', 'rotate-0');

            moon.classList.remove('hidden');
            requestAnimationFrame(() => {
                moon.classList.remove('scale-0', '-rotate-90', 'opacity-0');
                moon.classList.add('scale-100', 'rotate-0', 'opacity-100');
            });
        }
    }

    /* Init theme icons based on current mode */
    (function () {
        const isDark = document.documentElement.classList.contains('dark');
        syncThemeIcon(isDark);
    })();

    /* Listen to OS changes if user has not set preference */
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
        if (localStorage.getItem('whusnet-theme')) return;
        document.documentElement.classList.toggle('dark', e.matches);
        syncThemeIcon(e.matches);
    });

    /* ── Bantuan / Pintasan Keyboard ── */
    function setHelpTab(tab) {
        const on  = 'px-3 py-2 text-xs font-semibold border-b-2 transition-colors flex items-center gap-1.5 border-sky-600 dark:border-sky-400 text-sky-600 dark:text-sky-400';
        const off = 'px-3 py-2 text-xs font-semibold border-b-2 transition-colors flex items-center gap-1.5 border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-100';
        ['keys', 'actions'].forEach(t => {
            const btn = document.getElementById('helptab-' + t);
            const pane = document.getElementById('helppane-' + t);
            if (btn) { btn.className = t === tab ? on : off; btn.setAttribute('aria-selected', t === tab); }
            if (pane) pane.classList.toggle('hidden', t !== tab);
        });
    }

    function openHelp(tab = 'keys') {
        setHelpTab(tab);
        const m = document.getElementById('shortcutsModal');
        if (m) m.classList.remove('hidden');
    }

    function closeHelp() {
        const m = document.getElementById('shortcutsModal');
        if (m) m.classList.add('hidden');
    }

    /* Fokuskan pencarian global saat menekan "/" di mana saja */
    function handleGlobalSearch(e) {
        if (e.key === 'Escape') e.target.blur();
    }

    document.addEventListener('keydown', e => {
        const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)
                    || document.activeElement.isContentEditable;
        const helpOpen = !document.getElementById('shortcutsModal')?.classList.contains('hidden');

        if (e.key === 'Escape' && helpOpen) { closeHelp(); return; }
        if (typing) return;

        if ((e.ctrlKey && e.key.toLowerCase() === 'd') || (e.altKey && e.key.toLowerCase() === 't')) {
            e.preventDefault();
            toggleTheme(e);
            return;
        }

        if (e.key === '/') {
            const search = document.getElementById('globalSearch');
            if (search) { e.preventDefault(); search.focus(); }
            return;
        }
        if (e.key === '?') { e.preventDefault(); openHelp(); }
    });
</script>
<script>
    window.confirmAction = function(message, formElement) {
        window.Dialog.show({
            title: 'Konfirmasi',
            message: message,
            icon: 'warning',
            buttons: [
                { text: 'Batal', type: 'secondary' },
                { text: 'Lanjutkan', type: 'primary', onClick: () => {
                    window.Dialog.close();
                    if (formElement && formElement.submit) {
                        if (typeof NProgress !== 'undefined') NProgress.start();
                        formElement.submit();
                    }
                }}
            ]
        });
    };

    window.confirmDelete = function(message, formElement) {
        window.Dialog.show({
            title: 'Konfirmasi Hapus',
            message: message,
            icon: 'error',
            buttons: [
                { text: 'Batal', type: 'secondary' },
                { text: 'Ya, Hapus', type: 'danger', onClick: () => {
                    window.Dialog.close();
                    if (formElement && formElement.submit) {
                        if (typeof NProgress !== 'undefined') NProgress.start();
                        formElement.submit();
                    }
                }}
            ]
        });
    };

    window.Alert = function(title, message, icon = 'info') {
        window.Dialog.show({
            title: title,
            message: message,
            icon: icon,
            buttons: [
                { text: 'Tutup', type: 'secondary', onClick: () => window.Dialog.close() }
            ]
        });
    };

    window.Confirm = function(title, message, icon = 'warning', onConfirm = null, onCancel = null) {
        window.Dialog.show({
            title: title,
            message: message,
            icon: icon,
            buttons: [
                { text: 'Batal', type: 'secondary', onClick: () => {
                    window.Dialog.close();
                    if (typeof onCancel === 'function') onCancel();
                }},
                { text: 'Ya, Lanjutkan', type: 'primary', onClick: () => {
                    window.Dialog.close();
                    if (typeof onConfirm === 'function') onConfirm();
                }}
            ]
        });
    };

    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (form.method && form.method.toLowerCase() === 'get') return;
        if (form.classList.contains('no-confirm') ||
            form.id === 'logout-form' ||
            (form.action && (form.action.includes('login') || form.action.includes('logout')))) {
            return;
        }
        const onsubmitAttr = form.getAttribute('onsubmit');
        if (onsubmitAttr && (onsubmitAttr.includes('confirmAction') || onsubmitAttr.includes('confirmDelete') || onsubmitAttr.includes('Confirm'))) {
            return;
        }
        if (e.defaultPrevented) return;
        if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
            e.preventDefault();
            form.reportValidity();
            return;
        }
        e.preventDefault();

        let method = form.method.toUpperCase();
        const methodInput = form.querySelector('input[name="_method"]');
        if (methodInput) method = methodInput.value.toUpperCase();

        let actionWord = 'menyimpan', title = 'Konfirmasi Simpan', icon = 'warning',
            confirmText = 'Ya, Simpan', confirmType = 'primary';
        if (method === 'DELETE') {
            actionWord = 'menghapus'; title = 'Konfirmasi Hapus'; icon = 'error';
            confirmText = 'Ya, Hapus'; confirmType = 'danger';
        } else if (method === 'PUT' || method === 'PATCH') {
            actionWord = 'mengubah'; title = 'Konfirmasi Ubah'; confirmText = 'Ya, Ubah';
        } else {
            title = 'Konfirmasi Tambah'; confirmText = 'Ya, Lanjutkan';
        }

        let customMessage = null;
        if (e.submitter && e.submitter.getAttribute('data-confirm')) {
            customMessage = e.submitter.getAttribute('data-confirm');
        } else if (form.getAttribute('data-confirm')) {
            customMessage = form.getAttribute('data-confirm');
        }

        window.Dialog.show({
            title: title,
            message: customMessage || `Apakah Anda yakin ingin ${actionWord} data ini?`,
            icon: icon,
            buttons: [
                { text: 'Batal', type: 'secondary', onClick: () => {
                    window.Dialog.close();
                    if (typeof NProgress !== 'undefined') NProgress.done();
                }},
                { text: confirmText, type: confirmType, onClick: () => {
                    window.Dialog.close();
                    form.submit();
                }}
            ]
        });
    });
</script>


<style>
    .kbd {
        font-family: ui-monospace, 'JetBrains Mono', monospace;
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 4px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        color: #334155;
        white-space: nowrap;
    }
    html.dark .kbd { border-color: #334155; background: #0f172a; color: #cbd5e1; }
</style>
<div id="shortcutsModal" onclick="closeHelp()" role="dialog" aria-modal="true" aria-labelledby="shortcutsTitle"
     class="hidden fixed inset-0 z-50 bg-slate-900/50 dark:bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
    <div onclick="event.stopPropagation()" class="bg-white dark:bg-slate-800 rounded-lg max-w-lg w-full max-h-[85vh] flex flex-col shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">

        <div class="px-5 py-3 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between shrink-0">
            <h2 id="shortcutsTitle" class="font-bold text-slate-900 dark:text-slate-50 text-sm flex items-center gap-2">
                <svg class="h-4 w-4 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 17h.01"/>
                </svg>
                Bantuan
            </h2>
            <button onclick="closeHelp()" aria-label="Tutup" class="text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>

        
        <div class="flex border-b border-slate-100 dark:border-slate-700/60 px-2 shrink-0" role="tablist">
            <button onclick="setHelpTab('keys')" id="helptab-keys" role="tab"
                    class="px-3 py-2 text-xs font-semibold border-b-2 transition-colors flex items-center gap-1.5">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect width="20" height="16" x="2" y="4" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 8h.01M10 8h.01M14 8h.01M18 8h.01M8 12h.01M12 12h.01M16 12h.01M7 16h10"/></svg>
                Pintasan
            </button>
            <button onclick="setHelpTab('actions')" id="helptab-actions" role="tab"
                    class="px-3 py-2 text-xs font-semibold border-b-2 transition-colors flex items-center gap-1.5">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m3 17 2 2 4-4"/><path d="M13 6h8"/><path d="M13 12h8"/><path d="M13 18h8"/></svg>
                Tombol Aksi
            </button>
        </div>

        <div class="overflow-y-auto p-5 text-xs text-slate-600 dark:text-slate-300">

            
            <div id="helppane-keys" role="tabpanel" aria-labelledby="helptab-keys" class="space-y-5">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-400 dark:text-slate-500 mb-2">Tabel (List Pelanggan)</p>
                    <dl class="space-y-1.5">
                        <div class="flex justify-between gap-4"><dt>Pindah baris</dt><dd><kbd class="kbd">&uarr;</kbd> <kbd class="kbd">&darr;</kbd></dd></div>
                        <div class="flex justify-between gap-4"><dt>Baris pertama / terakhir</dt><dd><kbd class="kbd">Home</kbd> <kbd class="kbd">End</kbd></dd></div>
                        <div class="flex justify-between gap-4"><dt>Halaman sebelum / sesudah</dt><dd><kbd class="kbd">PgUp</kbd> <kbd class="kbd">PgDn</kbd></dd></div>
                        <div class="flex justify-between gap-4"><dt>Buka menu aksi baris</dt><dd><kbd class="kbd">Enter</kbd></dd></div>
                    </dl>
                </div>
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-400 dark:text-slate-500 mb-2">Global</p>
                    <dl class="space-y-1.5">
                        <div class="flex justify-between gap-4"><dt>Fokus pencarian global</dt><dd><kbd class="kbd">/</kbd></dd></div>
                        <div class="flex justify-between gap-4"><dt>Ganti tema Light/Dark</dt><dd><kbd class="kbd">Ctrl</kbd>+<kbd class="kbd">D</kbd> / <kbd class="kbd">Alt</kbd>+<kbd class="kbd">T</kbd></dd></div>
                        <div class="flex justify-between gap-4"><dt>Tutup menu / modal</dt><dd><kbd class="kbd">Esc</kbd></dd></div>
                        <div class="flex justify-between gap-4"><dt>Buka bantuan ini</dt><dd><kbd class="kbd">?</kbd></dd></div>
                    </dl>
                </div>
                <p class="text-[11px] text-slate-400 dark:text-slate-500 border-t border-slate-100 dark:border-slate-700/60 pt-3">
                    Pintasan tabel tidak aktif saat kursor berada di kolom isian atau saat modal terbuka &mdash;
                    kecuali <kbd class="kbd">Alt</kbd>+<kbd class="kbd">N</kbd> yang berlaku di mana saja.
                </p>
            </div>

            
            <div id="helppane-actions" role="tabpanel" aria-labelledby="helptab-actions" class="hidden space-y-5">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-400 dark:text-slate-500 mb-2">Menu Aksi Baris &mdash; tombol [&hellip;] di kolom paling kanan</p>
                    <dl class="space-y-2.5">
                        <div class="flex gap-3">
                            <svg class="h-4 w-4 text-slate-400 dark:text-slate-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.88 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.88 0"/><circle cx="12" cy="12" r="3"/></svg>
                            <div><dt class="font-semibold text-slate-800 dark:text-slate-100">Lihat Detail</dt>
                                 <dd>Buka halaman pelanggan: layanan, riwayat tagihan, tiket, dan perangkat.</dd></div>
                        </div>
                        <div class="flex gap-3">
                            <svg class="h-4 w-4 text-slate-400 dark:text-slate-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                            <div><dt class="font-semibold text-slate-800 dark:text-slate-100">Edit Data</dt>
                                 <dd>Ubah identitas, alamat, paket, dan harga. Perubahan masuk audit log.</dd></div>
                        </div>
                        <div class="flex gap-3">
                            <svg class="h-4 w-4 text-slate-400 dark:text-slate-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14" rx="1"/></svg>
                            <div><dt class="font-semibold text-slate-800 dark:text-slate-100">Cetak Tagihan</dt>
                                 <dd>Hasilkan PDF invoice periode berjalan. Tidak mengubah status bayar.</dd></div>
                        </div>
                        <div class="flex gap-3">
                            <svg class="h-4 w-4 text-emerald-500 mt-0.5 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.16-.17.2-.35.22-.64.08-.3-.15-1.26-.47-2.4-1.48-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.6.13-.14.3-.35.44-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.61-.92-2.2-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.88 1.22 3.07c.15.2 2.1 3.2 5.08 4.49.7.3 1.26.49 1.7.63.7.22 1.36.19 1.87.11.57-.08 1.76-.72 2-1.41.25-.7.25-1.29.18-1.41-.08-.13-.27-.2-.57-.35M12.05 21.8h-.01a9.87 9.87 0 0 1-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37a9.86 9.86 0 0 1-1.51-5.26C2.16 6.45 6.6 2.02 12.05 2.02a9.82 9.82 0 0 1 6.99 2.9 9.83 9.83 0 0 1 2.89 6.99c0 5.45-4.44 9.89-9.88 9.89"/></svg>
                            <div><dt class="font-semibold text-slate-800 dark:text-slate-100">Kirim WhatsApp</dt>
                                 <dd>Buka percakapan ke nomor pelanggan untuk pengingat tagihan.</dd></div>
                        </div>
                        <div class="flex gap-3">
                            <svg class="h-4 w-4 text-amber-600 dark:text-amber-400 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="m4.9 4.9 14.2 14.2"/></svg>
                            <div><dt class="font-semibold text-amber-700 dark:text-amber-300">Isolir Layanan &mdash; bisa dibatalkan</dt>
                                 <dd>Blokir akses internet sementara. Pelanggan tetap terdaftar dan tagihan tetap berjalan. Butuh konfirmasi; untuk mengembalikan buka menu yang sama &rarr; <em>Buka Isolir</em>.</dd></div>
                        </div>
                        <div class="flex gap-3">
                            <svg class="h-4 w-4 text-rose-600 dark:text-rose-400 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18"/><path stroke-linecap="round" stroke-linejoin="round" d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            <div><dt class="font-semibold text-rose-700 dark:text-rose-300">Terminasi &mdash; permanen</dt>
                                 <dd>Putus langganan dan keluarkan dari billing aktif. Butuh konfirmasi dan <strong>tidak bisa dibatalkan</strong> dari halaman ini.</dd></div>
                        </div>
                    </dl>
                </div>
                <div class="border-t border-slate-100 dark:border-slate-700/60 pt-4">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-400 dark:text-slate-500 mb-2">Aksi Massal</p>
                    <p class="mb-2">Centang kotak di kiri baris &mdash; atau kotak di baris judul untuk memilih seluruh baris
                       <em>di halaman yang sedang tampil</em> &mdash; lalu pakai bilah biru yang muncul di atas tabel.</p>
                    <p class="text-[11px] text-slate-400 dark:text-slate-500">
                       Centang di baris judul sengaja tidak menyapu seluruh hasil filter: memilih ribuan baris
                       yang tidak terlihat terlalu berisiko untuk aksi seperti Isolir.
                    </p>
                </div>
                <div class="border-t border-slate-100 dark:border-slate-700/60 pt-4">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-400 dark:text-slate-500 mb-2">Membaca Kolom Tagihan</p>
                    <dl class="space-y-1.5">
                        <div class="flex justify-between gap-4"><dt>Nominal hijau + &ldquo;Lunas&rdquo;</dt><dd class="text-emerald-600 dark:text-emerald-400">sudah dibayar</dd></div>
                        <div class="flex justify-between gap-4"><dt>Nominal netral + &ldquo;Belum dibayar&rdquo;</dt><dd class="text-slate-500 dark:text-slate-400">belum jatuh tempo</dd></div>
                        <div class="flex justify-between gap-4"><dt>Nominal &amp; tanggal merah</dt><dd class="text-rose-600 dark:text-rose-400">sudah lewat tempo</dd></div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    /*
     * Masking nominal rupiah: kasir mengetik 150000, layar menampilkan 150.000.
     *
     * Dipasang di layout (bukan resources/js) supaya berlaku di semua form uang
     * tanpa build step, sejalan dengan Alpine & banner realtime di atas.
     *
     * Input bertanda `data-rupiah` WAJIB `type="text"` — `type="number"` menolak
     * titik dan mengosongkan sendiri isinya begitu formatnya "tidak valid".
     *
     * Nilai dikembalikan ke angka polos SAAT SUBMIT, jadi payload tetap
     * `150000`. Server tetap menormalkan sendiri lewat `App\Support\RupiahInput`
     * — nominal uang tidak boleh bergantung pada JS yang jalan.
     */
    (function () {
        const digitSebelumKursor = (teks, posisi) => (teks.slice(0, posisi).match(/\d/g) || []).length;

        const format = (mentah) => {
            // Satu koma desimal saja; sisanya dibuang supaya "1,5,7" tak lahir.
            const [utuh, ...ekor] = mentah.replace(/[^\d,]/g, '').split(',');
            const ribuan = utuh.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

            if (ekor.length === 0) return ribuan;

            return ribuan + ',' + ekor.join('').slice(0, 2);
        };

        /*
         * Nilai yang DATANG DARI SERVER ambigu, sementara ketikan tidak.
         *
         * Saat mengetik, titik selalu berarti pemisah ribuan — itulah kenapa
         * format() membuangnya. Tapi `old()` sesudah validasi gagal berisi
         * bentuk MESIN hasil RupiahInput, di mana titik justru desimal:
         * `150000.50`. Melewatkannya ke format() menghasilkan `15.000.050` —
         * seratus kali lipat, langsung terkirim ulang saat pengguna menekan
         * simpan lagi. Titik desimal karena itu dikenali lebih dulu di sini,
         * dengan aturan yang SAMA seperti App\Support\RupiahInput di server:
         * hanya `\d+.\d{1,2}` tanpa koma yang dianggap desimal.
         */
        const dariServer = (nilai) => {
            const teks = String(nilai ?? '').trim();

            return /^-?\d+\.\d{1,2}$/.test(teks) ? teks.replace('.', ',') : teks;
        };

        window.Rupiah = {
            format,
            /** Nilai bawaan/`old()` dari server → tampilan bermasking. */
            formatDariServer: (nilai) => format(dariServer(nilai)),
            /** "150.000,50" → "150000.50" — bentuk yang dikirim ke server. */
            polos: (nilai) => String(nilai ?? '').replace(/[^\d,-]/g, '').replace(',', '.'),
            /** Angka siap hitung; NaN kalau kosong/bukan angka. */
            angka: (nilai) => parseFloat(window.Rupiah.polos(nilai)),
        };

        const rapikan = (input) => {
            const sebelum = input.value;
            const posisi = input.selectionStart ?? sebelum.length;
            const digit = digitSebelumKursor(sebelum, posisi);

            input.value = format(sebelum);

            // Kursor dikembalikan berdasarkan JUMLAH DIGIT sebelumnya, bukan
            // indeks karakter: titik yang baru disisipkan menggeser indeks dan
            // kursor akan meloncat ke tempat yang salah saat menyunting tengah.
            let hitung = 0;
            let target = input.value.length;
            for (let i = 0; i < input.value.length; i++) {
                if (/\d/.test(input.value[i])) hitung++;
                if (hitung === digit) { target = i + 1; break; }
            }
            if (digit === 0) target = input.value.length;

            if (document.activeElement === input) {
                input.setSelectionRange(target, target);
            }
        };

        document.addEventListener('input', (e) => {
            if (e.target instanceof HTMLInputElement && e.target.matches('[data-rupiah]')) {
                rapikan(e.target);
            }
        });

        // Format nilai awal (hasil `old()` atau nilai bawaan server) — lewat
        // formatDariServer, bukan format(), karena bentuk mesin bisa membawa
        // titik desimal.
        const formatAwal = () => document.querySelectorAll('[data-rupiah]').forEach((input) => {
            if (input.value !== '') input.value = window.Rupiah.formatDariServer(input.value);
        });

        document.addEventListener('DOMContentLoaded', formatAwal);
        // Tersedia untuk markup yang disisipkan SESUDAH halaman termuat.
        // Pemanggil AJAX yang ada sekarang (modal Bayar Cepat, tabel batch,
        // modal Hub pelanggan) memformat fieldnya masing-masing saat mengisi.
        window.formatInputRupiah = formatAwal;

        // Capture phase: normalisasi harus selesai sebelum handler submit lain
        // (mis. pengumpul baris batch) membaca nilainya.
        document.addEventListener('submit', (e) => {
            if (!(e.target instanceof HTMLFormElement)) return;
            e.target.querySelectorAll('[data-rupiah]').forEach((input) => {
                input.value = window.Rupiah.polos(input.value);
            });
        }, true);
    })();
</script>

<?php echo $__env->yieldContent('scripts'); ?>
<?php echo $__env->yieldPushContent('scripts'); ?>
<?php if (isset($component)) { $__componentOriginal7cfab914afdd05940201ca0b2cbc009b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7cfab914afdd05940201ca0b2cbc009b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.toast','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('toast'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7cfab914afdd05940201ca0b2cbc009b)): ?>
<?php $attributes = $__attributesOriginal7cfab914afdd05940201ca0b2cbc009b; ?>
<?php unset($__attributesOriginal7cfab914afdd05940201ca0b2cbc009b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7cfab914afdd05940201ca0b2cbc009b)): ?>
<?php $component = $__componentOriginal7cfab914afdd05940201ca0b2cbc009b; ?>
<?php unset($__componentOriginal7cfab914afdd05940201ca0b2cbc009b); ?>
<?php endif; ?>
<?php if (isset($component)) { $__componentOriginalbe1fd8fb3c7c5070d318ff3f0952937e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbe1fd8fb3c7c5070d318ff3f0952937e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dialog','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dialog'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbe1fd8fb3c7c5070d318ff3f0952937e)): ?>
<?php $attributes = $__attributesOriginalbe1fd8fb3c7c5070d318ff3f0952937e; ?>
<?php unset($__attributesOriginalbe1fd8fb3c7c5070d318ff3f0952937e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbe1fd8fb3c7c5070d318ff3f0952937e)): ?>
<?php $component = $__componentOriginalbe1fd8fb3c7c5070d318ff3f0952937e; ?>
<?php unset($__componentOriginalbe1fd8fb3c7c5070d318ff3f0952937e); ?>
<?php endif; ?>


<div id="realtime-offline-banner"
     class="hidden fixed bottom-4 left-1/2 -translate-x-1/2 z-[60] max-w-[calc(100%-2rem)]
            flex items-center gap-2.5 px-4 py-2.5 rounded-xl shadow-lg
            bg-amber-50 text-amber-900 border border-amber-200
            dark:bg-amber-950/90 dark:text-amber-200 dark:border-amber-800">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/>
    </svg>
    <span class="text-xs font-semibold">
        Koneksi realtime terputus — data di halaman ini mungkin tertinggal.
    </span>
    <button type="button" onclick="window.location.reload()"
            class="text-xs font-bold underline underline-offset-2 hover:opacity-80 cursor-pointer shrink-0">
        Muat ulang
    </button>
</div>

<script>
    (function () {
        const banner = document.getElementById('realtime-offline-banner');
        if (!banner) return;

        // Jangan langsung berteriak saat halaman baru dibuka: koneksi WebSocket
        // wajar melewati `connecting` sebentar. Banner cuma muncul kalau putusnya
        // BERTAHAN — kalau tidak, tiap kali membuka halaman admin melihat
        // peringatan yang langsung hilang, dan lama-lama peringatan itu diabaikan
        // justru saat benar-benar penting.
        const TUNDA_MS = 8000;
        let timer = null;

        const tampilkan = () => banner.classList.remove('hidden');
        const sembunyikan = () => {
            clearTimeout(timer);
            timer = null;
            banner.classList.add('hidden');
        };

        const tandaiPutus = () => {
            if (timer) return;
            timer = setTimeout(tampilkan, TUNDA_MS);
        };

        const pasang = () => {
            const pusher = window.Echo?.connector?.pusher;
            if (!pusher) return;

            pusher.connection.bind('state_change', (states) => {
                if (states.current === 'connected') {
                    sembunyikan();
                } else if (['unavailable', 'disconnected', 'failed'].includes(states.current)) {
                    tandaiPutus();
                }
            });

            // Halaman yang dibuka saat server WebSocket sudah mati tidak pernah
            // mengalami `state_change` ke `connected`, jadi keadaan awal ikut
            // diperiksa — kalau tidak, justru kasus paling parah yang senyap.
            if (['unavailable', 'disconnected', 'failed'].includes(pusher.connection.state)) {
                tandaiPutus();
            }
        };

        if (window.Echo) {
            pasang();
        } else {
            window.addEventListener('echo:ready', pasang, { once: true });
        }
    })();
</script>
</body>
</html>
<?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/layouts/app.blade.php ENDPATH**/ ?>