<?php $__env->startSection('title', 'Worksheet NOC — Ticket Service Desk'); ?>
<?php $__env->startSection('page_title', 'Worksheet NOC'); ?>

<?php
    use App\Http\Controllers\TicketHistoryController;
    use App\Support\IndonesianDate;
?>

<?php $__env->startSection('content'); ?>

<div class="space-y-5 pb-12" x-data="nocWorksheet()">

    
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <h1 class="text-xl font-extrabold text-text-main tracking-tight">Worksheet NOC</h1>
            <p class="text-xs text-text-muted mt-1 font-medium">
                <?php if($tab === 'assign_fop'): ?>
                    Tiket yang sudah NOC teruskan ke FOP. Progres pengerjaan lapangan dibaca di halaman Task FOP.
                <?php else: ?>
                    Ticket yang lagi diproses NOC — klik barisnya untuk melihat detail dan mengambil tindakan.
                <?php endif; ?>
            </p>
        </div>
        <span class="text-[11px] font-mono font-bold px-2.5 py-1 rounded-full bg-amber-600 text-white shrink-0">
            <?php echo e($tickets->total()); ?> Ticket
        </span>
    </div>

    
    <div class="flex items-center gap-1 p-1 rounded-lg bg-surface-muted border border-border w-fit text-xs">
        <?php $__currentLoopData = [
            'masuk' => ['label' => 'Tiket Masuk', 'badge' => 'bg-amber-600'],
            'assign_fop' => ['label' => 'Assign FOP', 'badge' => 'bg-sky-600'],
        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tabValue => $meta): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <a href="<?php echo e(route('noc.worksheet', array_merge(request()->except(['tab', 'page']), ['tab' => $tabValue]))); ?>"
               class="flex items-center gap-1.5 px-3 py-1.5 rounded-md transition-colors <?php echo e($tab === $tabValue ? 'bg-surface text-text-main font-bold shadow-sm' : 'text-text-muted hover:text-text-main'); ?>">
                <span><?php echo e($meta['label']); ?></span>
                <span class="shrink-0 px-1.5 py-px rounded-full text-[10px] font-bold font-mono text-white <?php echo e($meta['badge']); ?>"><?php echo e($tabCounts[$tabValue]); ?></span>
            </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

    
    <?php
        $activeSecondaryFilters = array_filter(array_diff_key($filters, ['q' => '']));
        $hasActiveSecondary = count($activeSecondaryFilters) > 0;
        $totalActiveFilters = count(array_filter($filters));
    ?>

    <form method="GET" action="<?php echo e(route('noc.worksheet')); ?>"
          x-data="{ showFilters: <?php echo e($hasActiveSecondary ? 'true' : 'false'); ?> }"
          class="rounded-xl border border-border bg-surface shadow-xs transition-all">
        <input type="hidden" name="tab" value="<?php echo e($tab); ?>">

        
        <div class="p-3 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
            
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-text-muted">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input type="text" name="q" value="<?php echo e($filters['q']); ?>"
                       placeholder="Cari tiket, nama pelanggan, CID, desa, keluhan..."
                       class="w-full pl-9 pr-8 text-xs rounded-lg border border-border bg-background py-2 text-text-main placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all font-medium">
                <?php if($filters['q']): ?>
                    <a href="<?php echo e(route('noc.worksheet', array_merge(request()->except(['q', 'page']), ['tab' => $tab]))); ?>"
                       class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-text-muted hover:text-text-main"
                       title="Hapus pencarian">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </a>
                <?php endif; ?>
            </div>

            
            <div class="flex items-center gap-2 shrink-0">
                <button type="button" @click="showFilters = !showFilters"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-border bg-background hover:bg-surface-muted text-xs font-semibold text-text-secondary hover:text-text-main transition-colors cursor-pointer"
                        :class="{ 'border-sky-500 text-sky-600 dark:text-sky-400 bg-sky-50/50 dark:bg-sky-950/30': showFilters || <?php echo e($hasActiveSecondary ? 'true' : 'false'); ?> }">
                    <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    <span>Filter</span>
                    <?php if(count($activeSecondaryFilters) > 0): ?>
                        <span class="px-1.5 py-0.5 text-[10px] font-bold font-mono rounded-full bg-sky-600 text-white">
                            <?php echo e(count($activeSecondaryFilters)); ?>

                        </span>
                    <?php endif; ?>
                    <svg class="w-3 h-3 text-text-muted transition-transform duration-200" :class="{ 'rotate-180': showFilters }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-sky-600 text-white text-xs font-bold uppercase tracking-wider hover:bg-sky-700 active:scale-95 transition-all cursor-pointer shadow-xs">
                    Terapkan
                </button>

                <?php if($totalActiveFilters > 0): ?>
                    <a href="<?php echo e(route('noc.worksheet', ['tab' => $tab])); ?>"
                       class="px-3 py-2 rounded-lg text-xs font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors">
                        Reset
                    </a>
                <?php endif; ?>
            </div>
        </div>

        
        <?php if($totalActiveFilters > 0): ?>
            <div class="px-3 pb-3 flex items-center gap-1.5 flex-wrap border-t border-border/50 pt-2.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted mr-1">Filter Aktif:</span>

                <?php if($filters['q']): ?>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-surface-muted border border-border text-text-main">
                        <span class="text-text-muted">Cari:</span> "<?php echo e($filters['q']); ?>"
                        <a href="<?php echo e(route('noc.worksheet', array_merge(request()->except(['q', 'page']), ['tab' => $tab]))); ?>" class="hover:text-rose-500 ml-0.5 font-bold">×</a>
                    </span>
                <?php endif; ?>

                <?php if($filters['pop_id']): ?>
                    <?php $popName = collect($popOptions)->firstWhere('id', (int) $filters['pop_id'])?->name; ?>
                    <?php if($popName): ?>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-surface-muted border border-border text-text-main">
                            <span class="text-text-muted">POP:</span> <?php echo e($popName); ?>

                            <a href="<?php echo e(route('noc.worksheet', array_merge(request()->except(['pop_id', 'page']), ['tab' => $tab]))); ?>" class="hover:text-rose-500 ml-0.5 font-bold">×</a>
                        </span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if($filters['issue_category_id']): ?>
                    <?php $catName = collect($categoryOptions)->firstWhere('id', (int) $filters['issue_category_id'])?->name; ?>
                    <?php if($catName): ?>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-surface-muted border border-border text-text-main">
                            <span class="text-text-muted">Kategori:</span> <?php echo e($catName); ?>

                            <a href="<?php echo e(route('noc.worksheet', array_merge(request()->except(['issue_category_id', 'page']), ['tab' => $tab]))); ?>" class="hover:text-rose-500 ml-0.5 font-bold">×</a>
                        </span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if($filters['priority']): ?>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-surface-muted border border-border text-text-main">
                        <span class="text-text-muted">Prioritas:</span> <?php echo e($filters['priority']); ?>

                        <a href="<?php echo e(route('noc.worksheet', array_merge(request()->except(['priority', 'page']), ['tab' => $tab]))); ?>" class="hover:text-rose-500 ml-0.5 font-bold">×</a>
                    </span>
                <?php endif; ?>

                <?php if($filters['type']): ?>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-surface-muted border border-border text-text-main">
                        <span class="text-text-muted">Tipe:</span> <?php echo e($filters['type']); ?>

                        <a href="<?php echo e(route('noc.worksheet', array_merge(request()->except(['type', 'page']), ['tab' => $tab]))); ?>" class="hover:text-rose-500 ml-0.5 font-bold">×</a>
                    </span>
                <?php endif; ?>

                <?php if($filters['created_by']): ?>
                    <?php $creatorName = collect($creatorOptions)->firstWhere('id', (int) $filters['created_by'])?->name; ?>
                    <?php if($creatorName): ?>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-surface-muted border border-border text-text-main">
                            <span class="text-text-muted">Pengirim:</span> <?php echo e($creatorName); ?>

                            <a href="<?php echo e(route('noc.worksheet', array_merge(request()->except(['created_by', 'page']), ['tab' => $tab]))); ?>" class="hover:text-rose-500 ml-0.5 font-bold">×</a>
                        </span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if($filters['date_from'] || $filters['date_to']): ?>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-surface-muted border border-border text-text-main">
                        <span class="text-text-muted">Tanggal:</span> <?php echo e($filters['date_from'] ?: '—'); ?> s/d <?php echo e($filters['date_to'] ?: '—'); ?>

                        <a href="<?php echo e(route('noc.worksheet', array_merge(request()->except(['date_from', 'date_to', 'page']), ['tab' => $tab]))); ?>" class="hover:text-rose-500 ml-0.5 font-bold">×</a>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        
        <div x-show="showFilters" x-collapse x-cloak class="p-3 border-t border-border bg-surface-muted/40 space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="space-y-1">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted">POP / Cabang</label>
                    <select name="pop_id" class="w-full text-xs rounded-lg border border-border bg-background px-2.5 py-1.5 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                        <option value="">Semua POP</option>
                        <?php $__currentLoopData = $popOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pop): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($pop->id); ?>" <?php if((string) $filters['pop_id'] === (string) $pop->id): echo 'selected'; endif; ?>><?php echo e($pop->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <div class="space-y-1">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted">Kategori Issue</label>
                    <select name="issue_category_id" class="w-full text-xs rounded-lg border border-border bg-background px-2.5 py-1.5 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                        <option value="">Semua Kategori</option>
                        <?php $__currentLoopData = $categoryOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($category->id); ?>" <?php if((string) $filters['issue_category_id'] === (string) $category->id): echo 'selected'; endif; ?>><?php echo e($category->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <div class="space-y-1">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted">Prioritas</label>
                    <select name="priority" class="w-full text-xs rounded-lg border border-border bg-background px-2.5 py-1.5 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                        <option value="">Semua Prioritas</option>
                        <?php $__currentLoopData = $priorityOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $priority): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($priority->value); ?>" <?php if($filters['priority'] === $priority->value): echo 'selected'; endif; ?>><?php echo e($priority->value); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <div class="space-y-1">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted">Tipe Tiket</label>
                    <select name="type" class="w-full text-xs rounded-lg border border-border bg-background px-2.5 py-1.5 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                        <option value="">Semua Tipe</option>
                        <?php $__currentLoopData = $typeOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $opt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($opt['value']); ?>" <?php if($filters['type'] === $opt['value']): echo 'selected'; endif; ?>><?php echo e($opt['value']); ?> — <?php echo e($opt['label']); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <div class="space-y-1">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted">Dikirim Oleh</label>
                    <select name="created_by" class="w-full text-xs rounded-lg border border-border bg-background px-2.5 py-1.5 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                        <option value="">Semua User</option>
                        <?php $__currentLoopData = $creatorOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $creator): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($creator->id); ?>" <?php if((string) $filters['created_by'] === (string) $creator->id): echo 'selected'; endif; ?>><?php echo e($creator->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <div class="space-y-1">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted">Dari Tanggal</label>
                    <input type="date" name="date_from" value="<?php echo e($filters['date_from']); ?>"
                           class="w-full text-xs rounded-lg border border-border bg-background px-2.5 py-1.5 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                </div>

                <div class="space-y-1">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted">Sampai Tanggal</label>
                    <input type="date" name="date_to" value="<?php echo e($filters['date_to']); ?>"
                           class="w-full text-xs rounded-lg border border-border bg-background px-2.5 py-1.5 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                </div>
            </div>
        </div>
    </form>

    
    <div class="rounded-xl border border-border bg-surface overflow-hidden">
        <div class="overflow-x-auto custom-scrollbar min-h-[300px]">
            <table class="w-full text-xs whitespace-nowrap">
                <thead class="bg-surface-muted dark:bg-slate-900/60 text-text-muted">
                    <tr class="text-left">
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider">Masuk</th>
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider">Tiket</th>
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider">Nama / CID</th>
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider">HP</th>
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider">Desa</th>
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider">POP</th>
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider">Aduan</th>
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider">Kategori</th>
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider">Prioritas</th>
                        <?php if($tab === 'assign_fop'): ?>
                            <th class="px-3 py-2.5 font-bold uppercase tracking-wider">Status</th>
                            <th class="px-3 py-2.5 font-bold uppercase tracking-wider" title="Kapan tiket diserahkan ke FOP">Diserahkan</th>
                            <th class="px-3 py-2.5 font-bold uppercase tracking-wider" title="Yang mengirim tiket ke FOP">Dikirim Oleh</th>
                        <?php else: ?>
                            
                            <th class="px-3 py-2.5 font-bold uppercase tracking-wider text-right" title="Lama tiket menunggu di meja NOC">Umur</th>
                        <?php endif; ?>
                        <th class="px-3 py-2.5 font-bold uppercase tracking-wider text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <?php $__empty_1 = true; $__currentLoopData = $tickets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ticket): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <?php
                            $actions = $ticket->actionFlagsFor(auth()->user());
                            $ageMinutes = (int) $ticket->created_at->diffInMinutes(now());
                            $ageLabel = sprintf('%dj %02dm', intdiv($ageMinutes, 60), $ageMinutes % 60);
                            $ageClass = match (true) {
                                $ageMinutes >= 1440 => 'text-rose-600 dark:text-rose-400 font-bold',
                                $ageMinutes >= 480 => 'text-amber-600 dark:text-amber-400 font-bold',
                                default => 'text-text-muted',
                            };

                        ?>

                        
                        <tr data-ticket-row="<?php echo e($ticket->id); ?>"
                            data-ticket-code="<?php echo e($ticket->ticket_number); ?>"
                            <?php if($actions['can_close']): ?> data-url-close="<?php echo e(route('tickets.close', $ticket)); ?>" <?php endif; ?>
                            <?php if($actions['can_escalate_fop']): ?> data-url-escalate="<?php echo e(route('tickets.escalate', $ticket)); ?>" <?php endif; ?>
                            <?php if($actions['can_return_to_helpdesk']): ?> data-url-return="<?php echo e(route('tickets.return-to-helpdesk', $ticket)); ?>" <?php endif; ?>
                            <?php if($actions['can_cancel']): ?> data-url-cancel="<?php echo e(route('tickets.cancel', $ticket)); ?>" <?php endif; ?>
                            @click="openDetail(<?php echo e($ticket->id); ?>)"
                            class="hover:bg-amber-50/50 dark:hover:bg-slate-800/40 transition-colors cursor-pointer">
                            <td class="px-3 py-2.5 font-mono text-text-muted"><?php echo e(IndonesianDate::dateTime($ticket->created_at)); ?></td>
                            <td class="px-3 py-2.5">
                                
                                <span class="font-mono font-bold text-sky-600 dark:text-sky-400 hover:underline">
                                    <?php echo e($ticket->ticket_number); ?>

                                </span>
                                <span class="block text-[10px] font-mono text-text-muted"><?php echo e($ticket->type->value); ?></span>
                            </td>
                            <td class="px-3 py-2.5">
                                <span class="font-semibold text-text-main"><?php echo e($ticket->customer->full_name ?? $ticket->customer_name ?? '—'); ?></span>
                                <span class="block font-mono text-[10px] text-text-muted"><?php echo e($ticket->customer?->display_id ?? '—'); ?></span>
                            </td>
                            <td class="px-3 py-2.5 font-mono text-text-secondary"><?php echo e($ticket->customer_phone ?? '—'); ?></td>
                            
                            <td class="px-3 py-2.5 text-text-secondary"><?php echo e($ticket->customer_village ?? '—'); ?></td>
                            <td class="px-3 py-2.5 text-text-secondary"><?php echo e($ticket->pop?->name ?? '—'); ?></td>
                            <td class="px-3 py-2.5 max-w-xs truncate text-text-secondary" title="<?php echo e($ticket->detail_keluhan); ?>"><?php echo e($ticket->detail_keluhan); ?></td>
                            <td class="px-3 py-2.5 text-text-secondary"><?php echo e($ticket->issueCategory?->name ?? '—'); ?></td>
                            <td class="px-3 py-2.5">
                                <?php if($ticket->priority): ?>
                                    <span class="inline-block px-2 py-0.5 rounded border text-[10px] font-bold
                                        <?php switch($ticket->priority->value):
                                            case ('Urgent'): ?> bg-rose-50 dark:bg-rose-900/20 border-rose-200 dark:border-rose-800/50 text-rose-700 dark:text-rose-400 <?php break; ?>
                                            <?php case ('High'): ?> bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800/50 text-amber-700 dark:text-amber-400 <?php break; ?>
                                            <?php default: ?> bg-surface-muted border-border text-text-secondary
                                        <?php endswitch; ?>">
                                        <?php echo e($ticket->priority->value); ?>

                                    </span>
                                <?php else: ?>
                                    <span class="text-text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <?php if($tab === 'assign_fop'): ?>
                                <td class="px-3 py-2.5">
                                    <span class="inline-block px-2 py-0.5 rounded border text-[10px] font-bold <?php echo e(TicketHistoryController::statusBadgeFor($ticket)); ?>">
                                        <?php echo e(TicketHistoryController::statusLabelFor($ticket)); ?>

                                    </span>
                                </td>
                                <td class="px-3 py-2.5 font-mono text-text-muted">
                                    <?php echo e($ticket->resolved_at ? IndonesianDate::dateTime($ticket->resolved_at) : '—'); ?>

                                </td>
                                <td class="px-3 py-2.5 text-text-secondary"><?php echo e($ticket->escalatedToFopBy()?->name ?? '—'); ?></td>
                            <?php else: ?>
                                <td class="px-3 py-2.5 font-mono text-right <?php echo e($ageClass); ?>"><?php echo e($ageLabel); ?></td>
                            <?php endif; ?>

                            
                            <td class="px-3 py-2.5 text-center shrink-0" @click.stop>
                                <?php
                                    $hasActions = $actions['can_close'] || $actions['can_escalate_fop'] || $actions['can_return_to_helpdesk'] || $actions['can_cancel'];
                                ?>
                                <?php if($hasActions): ?>
                                    <div class="relative inline-block text-left" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
                                        <button type="button"
                                                @click.stop="open = !open"
                                                class="px-2.5 py-1 rounded-lg border border-border bg-surface hover:bg-surface-muted text-text-secondary hover:text-text-main text-xs font-semibold inline-flex items-center gap-1.5 transition-colors shadow-xs cursor-pointer">
                                            <span>Tindakan</span>
                                            <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </button>

                                        <div x-show="open"
                                             x-transition:enter="transition ease-out duration-100"
                                             x-transition:enter-start="transform opacity-0 scale-95"
                                             x-transition:enter-end="transform opacity-100 scale-100"
                                             x-transition:leave="transition ease-in duration-75"
                                             x-transition:leave-start="transform opacity-100 scale-100"
                                             x-transition:leave-end="transform opacity-0 scale-95"
                                             class="absolute right-0 z-50 mt-1 w-44 rounded-xl border border-border bg-surface shadow-lg py-1 divide-y divide-border text-left"
                                             style="display: none;">
                                            <div class="py-1">
                                                <?php if($actions['can_close']): ?>
                                                    <button type="button"
                                                            @click.stop="open = false; $dispatch('ticket-drawer-action', { id: <?php echo e($ticket->id); ?>, action: 'close' })"
                                                            class="w-full text-left px-3 py-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/30 flex items-center gap-2 transition-colors cursor-pointer">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                        <span>Selesai</span>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if($actions['can_escalate_fop']): ?>
                                                    <button type="button"
                                                            @click.stop="open = false; $dispatch('ticket-drawer-action', { id: <?php echo e($ticket->id); ?>, action: 'fop' })"
                                                            class="w-full text-left px-3 py-1.5 text-xs font-medium text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-950/30 flex items-center gap-2 transition-colors cursor-pointer">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                                        <span>Assign FOP</span>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                            <?php if($actions['can_return_to_helpdesk'] || $actions['can_cancel']): ?>
                                                <div class="py-1">
                                                    <?php if($actions['can_return_to_helpdesk']): ?>
                                                        <button type="button"
                                                                @click.stop="open = false; $dispatch('ticket-drawer-action', { id: <?php echo e($ticket->id); ?>, action: 'return' })"
                                                                class="w-full text-left px-3 py-1.5 text-xs font-medium text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/30 flex items-center gap-2 transition-colors cursor-pointer">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
                                                            <span>Kembalikan</span>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if($actions['can_cancel']): ?>
                                                        <button type="button"
                                                                @click.stop="open = false; $dispatch('ticket-drawer-action', { id: <?php echo e($ticket->id); ?>, action: 'cancel' })"
                                                                class="w-full text-left px-3 py-1.5 text-xs font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30 flex items-center gap-2 transition-colors cursor-pointer">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                            <span>Batalkan</span>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-text-muted text-[11px]">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="<?php echo e($tab === 'assign_fop' ? 13 : 11); ?>" class="px-3 py-10 text-center text-text-muted">
                                <?php if($tab === 'assign_fop'): ?>
                                    Belum ada tiket yang diteruskan NOC ke FOP.
                                <?php else: ?>
                                    Belum ada ticket yang diproses NOC.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div><?php echo e($tickets->links()); ?></div>

    
    <?php echo $__env->make('tickets.partials.detail-drawer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    
    <?php echo $__env->make('tickets.partials.action-dialog', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    function nocWorksheet() {
        return {
            openDetail(id) {
                window.dispatchEvent(new CustomEvent('open-ticket-drawer', { detail: { id } }));
            },
        };
    }

    /**
     * Tombol aksi di drawer cuma men-dispatch niat; konfirmasi + POST tetap di
     * halaman ini (satu sumber per halaman). URL endpoint dibaca dari
     * data-attribute baris — cuma dirender kalau flag aksinya nyala, jadi tab
     * read-only gak punya URL buat dipakai sama sekali.
     */
    window.addEventListener('ticket-drawer-action', (event) => {
        const { id, action } = event.detail;
        const row = document.querySelector(`[data-ticket-row="${id}"]`);

        if (! row) {
            return;
        }

        const code = row.dataset.ticketCode;

        const map = {
            close: {
                url: row.dataset.urlClose,
                payload: {},
                title: 'Selesaikan Tiket',
                label: 'Apa yang sudah dikerjakan? (opsional)',
                message: `Tandai tiket ${code} selesai?`,
                required: false,
            },
            fop: {
                url: row.dataset.urlEscalate,
                payload: { target: 'fop' },
                title: 'Kirim Tiket ke FOP',
                label: 'Catatan buat FOP (opsional)',
                message: `Kirim tiket ${code} ke FOP?`,
                required: false,
            },
            return: {
                url: row.dataset.urlReturn,
                payload: {},
                title: 'Kembalikan ke Helpdesk',
                label: 'Alasan dikembalikan (opsional)',
                message: `Kembalikan tiket ${code} ke Helpdesk?`,
                required: false,
            },
            cancel: {
                url: row.dataset.urlCancel,
                payload: {},
                title: 'Batalkan Tiket',
                label: 'Alasan pembatalan (wajib diisi)',
                message: `Batalkan tiket ${code}?`,
                required: true,
            },
        }[action];

        if (! map || ! map.url) {
            return;
        }

        window.confirmTicketAction({
            title: map.title,
            message: map.message,
            label: map.label,
            required: map.required,
            confirmText: map.required ? 'Ya, Batalkan' : 'Ya, Lanjutkan',
            confirmType: map.required ? 'danger' : 'primary',
            icon: map.required ? 'error' : 'warning',
            onConfirm: (reason) => performTicketAction(id, map.url, { ...map.payload, reason }),
        });
    });

    /**
     * POST ke endpoint TicketController yang sudah ada (halaman ini gak punya
     * logic mutasi sendiri), lalu tutup drawer & buang barisnya dari tabel —
     * tiket yang sudah diaksi gak lagi masuk tab ini.
     */
    async function performTicketAction(ticketId, url, payload) {
        const row = document.querySelector(`[data-ticket-row="${ticketId}"]`);
        const buttons = document.querySelectorAll('[data-drawer-action]');
        buttons.forEach(b => { b.disabled = true; });

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(payload),
            });
            const body = await res.json();

            if (!res.ok) {
                window.Toast?.error('Gagal', body.message || 'Aksi gagal, coba lagi.');
                buttons.forEach(b => { b.disabled = false; });
                return;
            }

            window.Toast?.success('Berhasil', body.message);
            window.dispatchEvent(new CustomEvent('close-ticket-drawer'));

            if (row) {
                row.style.transition = 'opacity 0.25s';
                row.style.opacity = '0';
                setTimeout(() => { row.remove(); }, 250);
            }
        } catch (e) {
            window.Toast?.error('Gagal', 'Aksi gagal, coba lagi.');
            buttons.forEach(b => { b.disabled = false; });
        }
    }
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/noc/worksheet.blade.php ENDPATH**/ ?>