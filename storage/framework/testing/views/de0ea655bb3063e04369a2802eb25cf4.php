<div class="space-y-6 max-w-7xl mx-auto pb-12">

    
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-xl font-extrabold text-text-main tracking-tight flex items-center gap-2">
                    <svg class="h-6 w-6 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                    <?php echo e($activeBucket->label()); ?>

                </h1>
                <?php $activeCount = collect($archiveTabs)->firstWhere('active')['count'] ?? 0; ?>
                <span id="bucket-count-badge" data-count="<?php echo e($activeCount); ?>" class="text-xs font-bold font-mono px-2.5 py-0.5 rounded-full border <?php echo e($activeBucket->badgeClasses()); ?>">
                    <?php echo e($activeCount); ?> Tickets
                </span>
                
                <button type="button" id="ticket-bucket-refresh-btn" onclick="window.ticketBucketRefresh && window.ticketBucketRefresh()"
                        class="text-xs font-semibold text-sky-600 dark:text-sky-400 hover:underline flex items-center gap-1 cursor-pointer">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>Refresh</span>
                </button>
            </div>
            <p class="text-xs text-text-muted mt-1 font-medium"><?php echo e($activeBucket->description()); ?></p>
        </div>
    </div>

    
    <div id="bucket-tabs-container" class="flex items-center gap-1 border-b border-border overflow-x-auto pb-px">
        <?php $__currentLoopData = $archiveTabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tab): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <a href="<?php echo e($tab['url']); ?>"
               class="shrink-0 inline-flex items-center gap-2 px-4 py-2.5 text-xs font-bold uppercase tracking-wider border-b-2 transition-all
                      <?php echo e($tab['active']
                            ? 'border-sky-600 text-sky-600 bg-sky-50/50 dark:bg-sky-950/20 rounded-t-lg'
                            : 'border-transparent text-text-muted hover:text-text-main hover:bg-slate-50 dark:hover:bg-slate-900/40'); ?>">
                <span><?php echo e($tab['label']); ?></span>
                <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-full
                            <?php echo e($tab['active'] ? 'bg-sky-600 text-white' : 'bg-slate-200 dark:bg-slate-800 text-text-muted'); ?>">
                    <?php echo e($tab['count']); ?>

                </span>
            </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

    
    <form method="GET" action="<?php echo e($currentUrl); ?>"
          class="bg-surface border border-border rounded-xl p-4 shadow-xs flex flex-col md:flex-row md:items-center gap-3">
        
        
        <div class="relative flex-1">
            <svg class="h-4 w-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-text-muted pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input type="text" name="q" value="<?php echo e(request('q')); ?>"
                   placeholder="Cari nomor ticket, CID, nama pelanggan, atau keluhan..."
                   class="w-full pl-10 pr-4 py-2 text-xs font-mono rounded-lg border border-border bg-background text-text-main placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
        </div>

        
        <div class="relative">
            <select name="type" class="text-xs rounded-lg border border-border bg-background px-3 py-2 text-text-main font-mono focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all">
                <option value="">Semua Tipe</option>
                <?php $__currentLoopData = $typeOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $opt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($opt['value']); ?>" <?php if(request('type') === $opt['value']): echo 'selected'; endif; ?>>
                        <?php echo e($opt['value']); ?> — <?php echo e($opt['label']); ?>

                    </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>

        
        <label class="inline-flex items-center gap-2 text-xs font-semibold text-text-secondary cursor-pointer whitespace-nowrap bg-background border border-border px-3 py-2 rounded-lg">
            <input type="checkbox" name="mine" value="1" <?php if(request()->boolean('mine')): echo 'checked'; endif; ?>
                   class="rounded border-border text-sky-600 focus:ring-sky-500/30">
            <span>Ticket Saya</span>
        </label>

        
        <div class="flex items-center gap-2">
            <button type="submit" 
                    class="px-4 py-2 rounded-lg bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900 text-xs font-bold uppercase tracking-wider hover:bg-slate-800 transition-all cursor-pointer">
                Terapkan Filter
            </button>

            <?php if(request()->hasAny(['q', 'type', 'mine'])): ?>
                <a href="<?php echo e($currentUrl); ?>" 
                   class="px-3 py-2 text-xs font-bold text-text-muted hover:text-rose-600 transition-colors">
                    Reset
                </a>
            <?php endif; ?>
        </div>
    </form>

    
    <div id="tickets-list-container" class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs divide-y divide-border">
        <?php $__empty_1 = true; $__currentLoopData = $tickets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ticket): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php
                $borderAccent = $ticket->bucket()->borderAccentClasses();
                $ticketActions = $ticket->actionFlagsFor(auth()->user());
                $hasActions = $ticketActions['can_close'] || $ticketActions['can_escalate_noc'] || $ticketActions['can_escalate_fop'] || $ticketActions['can_return_to_helpdesk'] || $ticketActions['can_cancel'];
            ?>

            <div class="flex flex-col border-l-4 <?php echo e($borderAccent); ?> group" data-ticket-row="<?php echo e($ticket->id); ?>">
                <a href="<?php echo e(route('tickets.show', $ticket)); ?>"
                   class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 hover:bg-sky-50/50 dark:hover:bg-slate-900/40 transition-colors cursor-pointer">

                    
                    <div class="flex items-start gap-3.5 min-w-0 flex-1">
                        
                        <div class="h-9 w-9 shrink-0 rounded-lg bg-slate-100 dark:bg-slate-800 border border-border flex items-center justify-center text-xs font-bold font-mono text-slate-700 dark:text-slate-300 group-hover:border-sky-500 transition-colors">
                            <?php echo e(strtoupper(substr($ticket->creator->name ?? '?', 0, 2))); ?>

                        </div>

                        <div class="min-w-0 flex-1">
                            
                            <div class="flex items-center gap-2 flex-wrap text-xs">
                                <span class="font-bold text-text-main group-hover:text-sky-600 transition-colors truncate">
                                    <?php echo e($ticket->creator->name ?? '—'); ?>

                                </span>

                                <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded border <?php echo e($ticket->type->badgeClasses()); ?>">
                                    <?php echo e($ticket->type->value); ?>

                                </span>

                                <span class="text-[10px] font-bold px-2 py-0.5 rounded border <?php echo e($ticket->statusBadgeClasses()); ?>">
                                    <?php echo e($ticket->statusLabel()); ?>

                                </span>

                                <?php if($ticket->priority): ?>
                                    <span class="text-[10px] font-semibold text-text-muted">
                                        • <?php echo e($ticket->priority->value); ?>

                                    </span>
                                <?php endif; ?>

                                <?php if($ticket->attachments_count > 0): ?>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-mono font-medium text-sky-600 bg-sky-50 dark:bg-sky-950/50 px-1.5 py-0.5 rounded border border-sky-200 dark:border-sky-900">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                        </svg>
                                        <?php echo e($ticket->attachments_count); ?>

                                    </span>
                                <?php endif; ?>
                            </div>

                            
                            <div class="flex items-center gap-2 mt-1 truncate">
                                <span class="font-mono font-bold text-xs text-sky-600 dark:text-sky-400 data-text"><?php echo e($ticket->ticket_number); ?></span>
                                <span class="text-text-muted text-xs">—</span>
                                <span class="text-xs font-semibold text-text-main truncate"><?php echo e($ticket->customer->full_name ?? $ticket->customer_name ?? '—'); ?></span>
                                <span class="text-xs font-mono text-text-muted">(<?php echo e($ticket->customer->display_id ?? $ticket->customer?->cid ?? '—'); ?>)</span>
                            </div>
                            
                            <div class="flex items-center gap-3 mt-1 text-xs text-text-muted truncate">
                                <?php if($ticket->issueCategory): ?>
                                    <span class="flex items-center gap-1">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                        </svg>
                                        <?php echo e($ticket->issueCategory->name); ?>

                                    </span>
                                <?php endif; ?>

                                <span class="flex items-center gap-1">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                    </svg>
                                    <?php echo e($ticket->customer->primary_phone ?? $ticket->customer_phone ?? '—'); ?>

                                </span>
                            </div>

                            
                            <p class="text-xs text-text-muted truncate mt-0.5 line-clamp-1">
                                <?php echo e($ticket->detail_keluhan); ?>

                            </p>

                            
                            <div class="flex items-center gap-3 mt-1.5 flex-wrap text-[10px] text-text-muted font-medium">
                                <?php if($ticket->escalatedToNocBy()): ?>
                                    <span>→ NOC oleh <span class="font-bold text-text-secondary"><?php echo e($ticket->escalatedToNocBy()->name); ?></span></span>
                                <?php endif; ?>
                                <?php if($ticket->escalatedToFopBy()): ?>
                                    <span>→ FOP oleh <span class="font-bold text-text-secondary"><?php echo e($ticket->escalatedToFopBy()->name); ?></span></span>
                                <?php endif; ?>
                                <?php if($ticket->returnedToHelpdeskBy()): ?>
                                    <span>↩ Kembali ke Helpdesk oleh <span class="font-bold text-text-secondary"><?php echo e($ticket->returnedToHelpdeskBy()->name); ?></span></span>
                                <?php endif; ?>
                                <?php if($ticket->closedBy()): ?>
                                    <span>✓ Selesai oleh <span class="font-bold text-emerald-600 dark:text-emerald-400"><?php echo e($ticket->closedBy()->name); ?></span></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    
                    <div class="shrink-0 flex sm:flex-col items-center sm:items-end justify-between text-xs text-text-muted pt-2 sm:pt-0 border-t sm:border-t-0 border-border">
                        <span class="font-mono text-[11px] text-text-secondary">
                            <?php echo e(\App\Support\IndonesianDate::dateTime($ticket->created_at)); ?>

                        </span>

                        <div class="flex items-center gap-1.5 mt-1">
                            <svg class="h-3.5 w-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span class="font-bold text-[11px] text-text-main"><?php echo e($ticket->pop->name ?? '—'); ?></span>
                        </div>
                    </div>
                </a>

                
                <?php if($hasActions): ?>
                <div class="flex items-center gap-2 px-4 pb-3 pl-[4.4rem]">
                    <?php if($ticketActions['can_close']): ?>
                    <button type="button"
                            onclick="confirmTicketRowAction(this, '<?php echo e(route('tickets.close', $ticket)); ?>', {}, 'Selesaikan Tiket', 'Apa yang sudah dikerjakan? (opsional)', false, 'Tandai tiket <?php echo e($ticket->ticket_number); ?> selesai?')"
                            class="px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer">
                        Selesai
                    </button>
                    <?php endif; ?>

                    <?php if($ticketActions['can_escalate_noc']): ?>
                    <button type="button"
                            onclick="confirmTicketRowAction(this, '<?php echo e(route('tickets.escalate', $ticket)); ?>', {target: 'noc'}, 'Kirim Tiket ke NOC', 'Catatan buat NOC (opsional)', false, 'Kirim tiket <?php echo e($ticket->ticket_number); ?> ke NOC?')"
                            class="px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-amber-600 text-white hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer">
                        Ke NOC
                    </button>
                    <?php endif; ?>

                    <?php if($ticketActions['can_escalate_fop']): ?>
                    <button type="button"
                            onclick="confirmTicketRowAction(this, '<?php echo e(route('tickets.escalate', $ticket)); ?>', {target: 'fop'}, 'Kirim Tiket ke FOP', 'Catatan buat FOP (opsional)', false, 'Kirim tiket <?php echo e($ticket->ticket_number); ?> ke FOP? Task FOP baru akan dibuat.')"
                            class="px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-sky-600 text-white hover:bg-sky-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer">
                        Ke FOP
                    </button>
                    <?php endif; ?>

                    
                    <?php if($ticketActions['can_return_to_helpdesk']): ?>
                    <button type="button"
                            onclick="confirmTicketRowAction(this, '<?php echo e(route('tickets.return-to-helpdesk', $ticket)); ?>', {}, 'Kembalikan ke Helpdesk', 'Alasan dikembalikan (opsional)', false, 'Kembalikan tiket <?php echo e($ticket->ticket_number); ?> ke Helpdesk?')"
                            class="px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-slate-600 text-white hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer">
                        Kembalikan
                    </button>
                    <?php endif; ?>

                    <?php if($ticketActions['can_cancel']): ?>
                    <button type="button"
                            onclick="confirmTicketRowAction(this, '<?php echo e(route('tickets.cancel', $ticket)); ?>', {}, 'Batalkan Tiket', 'Alasan pembatalan (wajib diisi)', true, 'Batalkan tiket <?php echo e($ticket->ticket_number); ?>? Tindakan ini gak bisa dibatalin balik.')"
                            class="px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-red-600 text-white hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer">
                        Batalkan
                    </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="p-16 text-center space-y-3">
                <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-text-muted">
                    <svg class="h-6 w-6 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-bold text-text-main">Tidak ada ticket di <?php echo e($activeBucket->label()); ?></p>
                    <p class="text-xs text-text-muted mt-0.5"><?php echo e($activeBucket->description()); ?></p>
                </div> 
            </div>
        <?php endif; ?>
    </div>

    
    <div id="tickets-pagination-container" class="pt-2"><?php echo e($tickets->links()); ?></div>

    
    <?php echo $__env->make('tickets.partials.action-dialog', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div>

<?php $__env->startPush('scripts'); ?>
<script>
    /**
     * Selesai/Ke NOC/Ke FOP/Kembalikan/Batalkan dari list bucket — fetch() JSON,
     * hapus baris in-place, TANPA navigasi (Gap #1 KRITIS, lihat komentar di
     * atas tombol). Native <button type="button"> sengaja dipakai (bukan
     * <form>) biar gak ketiban global document 'submit' listener di
     * layouts/app.blade.php yang bakal munculin dialog konfirmasi KEDUA.
     */
    async function performTicketAction(row, url, payload) {
        const buttons = row ? row.querySelectorAll('button') : [];
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

            // Ticket pindah bucket — baris ini gak lagi termasuk bucket yang
            // lagi dibuka, hapus dari list (bukan cuma disable tombolnya).
            if (row) {
                row.style.transition = 'opacity 0.25s, max-height 0.25s';
                row.style.opacity = '0';
                row.style.maxHeight = '0px';
                row.style.overflow = 'hidden';
                setTimeout(() => {
                    row.remove();
                    decrementBucketCountBadge();
                }, 250);
            }
        } catch (e) {
            window.Toast?.error('Gagal', 'Aksi gagal, coba lagi.');
            buttons.forEach(b => { b.disabled = false; });
        }
    }

    /**
     * Jembatan tombol baris → dialog global (window.confirmTicketAction, lihat
     * tickets/partials/action-dialog.blade.php) → performTicketAction().
     * Dialog-nya yang urus konfirmasi + validasi alasan; fungsi ini cuma
     * nyambungin baris mana yang dipencet sama endpoint tujuannya.
     */
    function confirmTicketRowAction(button, url, payloadBase, title, label, required, confirmText) {
        const row = button.closest('[data-ticket-row]');

        window.confirmTicketAction({
            title,
            message: confirmText,
            label,
            required,
            confirmText: required ? 'Ya, Batalkan' : 'Ya, Lanjutkan',
            confirmType: required ? 'danger' : 'primary',
            icon: required ? 'error' : 'warning',
            onConfirm: (reason) => performTicketAction(row, url, { ...payloadBase, reason }),
        });
    }

    function decrementBucketCountBadge() {
        const badge = document.getElementById('bucket-count-badge');
        if (!badge) return;
        const next = Math.max(0, parseInt(badge.dataset.count || '0', 10) - 1);
        badge.dataset.count = String(next);
        badge.textContent = `${next} Tickets`;
    }

    /**
     * Auto-refresh (Gap #3) — subscribe channel tickets.{popId} per POP yang
     * kelihatan user, dengerin App\Events\TicketQueueUpdated. Pola sama kayak
     * fop/dashboard.blade.php: refetch URL SENDIRI (bucket + filter query
     * string kepertahankan), lalu innerHTML-swap container yang berubah —
     * BUKAN rebuild HTML manual di JS (mismatch risk sama Blade rendering).
     */
    (function initTicketBucketAutoRefresh() {
        const allowedPopIds = <?php echo json_encode($allowedPopIds ?? [], 15, 512) ?>;
        let refreshing = false;

        async function refetchAndSwap() {
            if (refreshing) return;
            refreshing = true;
            const btn = document.getElementById('ticket-bucket-refresh-btn');
            btn?.classList.add('opacity-50');
            try {
                const res = await fetch(window.location.href, { headers: { 'Accept': 'text/html' } });
                const html = await res.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');

                // bucket-count-badge di-outerHTML (bukan innerHTML) — elemen ini
                // bawa atribut data-count yang dibaca decrementBucketCountBadge()
                // pas aksi lokal (ticketRowAction()); innerHTML doang bakal
                // ninggalin data-count basi dan bikin hitungan drift abis refresh.
                const badge = document.getElementById('bucket-count-badge');
                const newBadge = doc.getElementById('bucket-count-badge');
                if (badge && newBadge) badge.outerHTML = newBadge.outerHTML;

                ['bucket-tabs-container', 'tickets-list-container', 'tickets-pagination-container'].forEach(id => {
                    const el = document.getElementById(id);
                    const newEl = doc.getElementById(id);
                    if (el && newEl) el.innerHTML = newEl.innerHTML;
                });
            } catch (e) {
                // Diam-diam gagal — tombol Refresh manual tetap bisa dicoba lagi.
            } finally {
                refreshing = false;
                btn?.classList.remove('opacity-50');
            }
        }

        window.ticketBucketRefresh = refetchAndSwap;

        let attempts = 0;
        const setupEcho = () => {
            if (typeof window.Echo === 'undefined' || !window.Echo) {
                attempts++;
                if (attempts < 20) setTimeout(setupEcho, 100);
                return;
            }
            allowedPopIds.forEach(popId => {
                window.Echo.private(`tickets.${popId}`)
                    .listen('.TicketQueueUpdated', () => refetchAndSwap());
            });
        };
        setupEcho();
    })();
</script>
<?php $__env->stopPush(); ?>

<?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/tickets/partials/archive.blade.php ENDPATH**/ ?>