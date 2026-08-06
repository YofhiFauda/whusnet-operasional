

<div x-data="ticketDetailDrawer()" x-on:open-ticket-drawer.window="open($event.detail.id)"
     x-on:close-ticket-drawer.window="close()"
     x-on:keydown.escape.window="close()"
     x-effect="document.body.classList.toggle('overflow-hidden', shown); window.dispatchEvent(new CustomEvent(shown ? 'ticket-drawer-shown' : 'ticket-drawer-hidden'))">

    
    <div x-show="shown" x-transition.opacity @click="close()"
         class="fixed inset-0 top-16 bg-slate-950/60 backdrop-blur-sm z-[60]" x-cloak></div>

    
    <div x-show="shown" x-cloak
         x-transition:enter="transform transition ease-in-out duration-300"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transform transition ease-in-out duration-200"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         
         class="fixed top-16 right-0 bottom-0 h-[calc(100dvh-4rem)] w-full max-w-2xl bg-surface border-l border-border shadow-2xl z-[60] flex flex-col overflow-hidden"
         role="dialog" aria-modal="true" aria-label="Detail Ticket">

        
        <div class="p-4 bg-surface-muted border-b border-border flex items-start justify-between shrink-0">
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-base font-extrabold font-mono text-sky-600 dark:text-sky-400" x-text="ticket?.code ?? '—'"></h2>
                    <template x-if="ticket?.priority">
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded border border-border bg-surface text-text-secondary" x-text="ticket.priority"></span>
                    </template>
                    <template x-if="ticket?.type">
                        <span class="px-2 py-0.5 text-[10px] font-bold font-mono rounded border border-border bg-surface text-text-secondary" x-text="ticket.type_label ?? ticket.type"></span>
                    </template>
                </div>
                <p class="text-[11px] text-text-muted mt-0.5">Detail Ticket — routing Helpdesk / NOC / FOP</p>
            </div>
            <button type="button" @click="close()"
                    class="shrink-0 w-8 h-8 rounded-lg border border-border bg-surface text-text-muted hover:text-text-main hover:bg-surface-muted flex items-center justify-center cursor-pointer">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <span class="sr-only">Tutup</span>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto custom-scrollbar p-4 space-y-4 text-xs">

            <template x-if="loading">
                <div class="space-y-3">
                    <div class="h-20 rounded-xl bg-surface-muted animate-pulse"></div>
                    <div class="h-32 rounded-xl bg-surface-muted animate-pulse"></div>
                    <div class="h-24 rounded-xl bg-surface-muted animate-pulse"></div>
                </div>
            </template>

            <template x-if="!loading && failed">
                <p class="p-4 rounded-xl border border-border bg-surface-muted text-text-muted">
                    Gagal memuat detail ticket. Tutup drawer dan coba lagi.
                </p>
            </template>

            <template x-if="!loading && ticket">
                <div class="space-y-4">

                    
                    <div class="rounded-xl border border-border bg-surface-muted p-4 space-y-3">
                        <div class="flex items-center justify-between gap-2 border-b border-border pb-2.5 flex-wrap">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-semibold text-text-muted">Status:</span>
                                <span class="px-2.5 py-0.5 text-xs font-bold rounded border" :class="ticket.status_badge" x-text="ticket.status_label"></span>
                            </div>
                            <template x-if="ticket.fop_task_number">
                                <span class="px-2.5 py-0.5 text-[10px] font-bold font-mono rounded border border-border bg-surface text-text-secondary"
                                      x-text="'Task FOP ' + ticket.fop_task_number"></span>
                            </template>
                        </div>
                        <dl class="grid grid-cols-2 gap-2">
                            <div>
                                <dt class="text-[10px] font-bold uppercase tracking-wider text-text-muted">Dikirim Oleh</dt>
                                <dd class="text-text-secondary font-medium" x-text="ticket.created_by ?? '—'"></dd>
                            </div>
                            <div>
                                <dt class="text-[10px] font-bold uppercase tracking-wider text-text-muted">Waktu Masuk</dt>
                                <dd class="text-text-secondary font-mono" x-text="ticket.created_at"></dd>
                            </div>
                            <div>
                                <dt class="text-[10px] font-bold uppercase tracking-wider text-text-muted">Kategori Issue</dt>
                                <dd class="text-text-secondary" x-text="ticket.issue_category ?? '—'"></dd>
                            </div>
                            <div>
                                
                                <dt class="text-[10px] font-bold uppercase tracking-wider text-text-muted">Selesai / Diserahkan</dt>
                                <dd class="text-text-secondary font-mono">
                                    <span x-text="ticket.resolved_at ?? '—'"></span>
                                    <span x-show="ticket.solving_time" class="text-text-muted" x-text="' (' + ticket.solving_time + ')'"></span>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    
                    <template x-if="ticket.fop_task">
                        <div class="rounded-xl border border-sky-200 dark:border-sky-900/50 bg-sky-50/50 dark:bg-slate-900/40 p-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="p-2 rounded-lg bg-sky-100 dark:bg-sky-950 text-sky-600 dark:text-sky-400 shrink-0">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                </div>
                                <div class="text-xs min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-text-main">Task FOP Lapangan Terkait:</span>
                                        <span class="font-mono font-bold text-sky-600 dark:text-sky-400" x-text="ticket.fop_task.number"></span>
                                    </div>
                                    <p class="text-text-muted mt-0.5 truncate">
                                        Teknisi: <span class="font-medium text-text-main" x-text="ticket.fop_task.technicians || 'Belum ada teknisi FOP ditugaskan'"></span>
                                    </p>
                                </div>
                            </div>
                            <template x-if="ticket.fop_task.can_view">
                                <a :href="ticket.fop_task.url"
                                   class="shrink-0 inline-flex items-center gap-1 px-3 py-1.5 rounded bg-sky-600 text-white text-xs font-bold hover:bg-sky-700 transition-colors shadow-xs">
                                    Buka Task FOP →
                                </a>
                            </template>
                        </div>
                    </template>

                    
                    <template x-if="ticket.fop_task_orphan">
                        <div class="rounded-xl border border-border bg-surface-muted p-3.5 flex items-center gap-2 text-text-muted">
                            <svg class="h-4 w-4 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Task FOP untuk ticket ini sudah tidak aktif — data ticket tetap tersimpan sebagai riwayat audit.</span>
                        </div>
                    </template>

                    
                    <div class="rounded-xl border border-border bg-surface-muted p-4">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-text-muted mb-2.5">Aksi Ticket</p>

                        <div class="flex items-center gap-2 flex-wrap">
                            <template x-if="ticket.actions?.can_close">
                                <button type="button" data-drawer-action @click="act('close')"
                                        class="px-3 py-2 rounded-lg text-[10px] font-bold uppercase tracking-wide bg-emerald-600 text-white hover:bg-emerald-700 active:scale-95 transition-all cursor-pointer">
                                    Selesai
                                </button>
                            </template>
                            <template x-if="ticket.actions?.can_escalate_noc">
                                <button type="button" data-drawer-action @click="act('noc')"
                                        class="px-3 py-2 rounded-lg text-[10px] font-bold uppercase tracking-wide bg-amber-600 text-white hover:bg-amber-700 active:scale-95 transition-all cursor-pointer">
                                    Assign NOC
                                </button>
                            </template>
                            <template x-if="ticket.actions?.can_escalate_fop">
                                <button type="button" data-drawer-action @click="act('fop')"
                                        class="px-3 py-2 rounded-lg text-[10px] font-bold uppercase tracking-wide bg-sky-600 text-white hover:bg-sky-700 active:scale-95 transition-all cursor-pointer">
                                    Assign FOP
                                </button>
                            </template>
                            <template x-if="ticket.actions?.can_return_to_helpdesk">
                                <button type="button" data-drawer-action @click="act('return')"
                                        class="px-3 py-2 rounded-lg text-[10px] font-bold uppercase tracking-wide bg-slate-600 text-white hover:bg-slate-700 active:scale-95 transition-all cursor-pointer">
                                    Kembalikan
                                </button>
                            </template>
                            <template x-if="ticket.actions?.can_cancel">
                                <button type="button" data-drawer-action @click="act('cancel')"
                                        class="px-3 py-2 rounded-lg text-[10px] font-bold uppercase tracking-wide bg-red-600 text-white hover:bg-red-700 active:scale-95 transition-all cursor-pointer">
                                    Batalkan
                                </button>
                            </template>
                        </div>

                        <p x-show="!hasAnyAction" class="text-[11px] text-text-muted">
                            Ticket sudah di tangan FOP. Pembatalan &amp; perubahan berikutnya dilakukan dari modul Task FOP.
                        </p>
                    </div>

                    
                    <div class="rounded-xl border border-border bg-surface-muted p-4 space-y-3">
                        <h3 class="text-[11px] font-bold uppercase tracking-wider text-sky-600 dark:text-sky-400 border-b border-border pb-2">
                            Snapshot Pelanggan
                        </h3>

                        <div class="rounded-lg border border-border bg-surface overflow-hidden">
                            <div class="grid grid-cols-2 border-b border-border">
                                <div class="p-2.5 border-r border-border">
                                    <span class="block text-[10px] font-bold uppercase text-text-muted">Nama</span>
                                    <span class="font-semibold text-text-main" x-text="ticket.customer.name ?? '—'"></span>
                                </div>
                                <div class="p-2.5">
                                    <span class="block text-[10px] font-bold uppercase text-text-muted">CID</span>
                                    <span class="font-bold font-mono text-sky-600 dark:text-sky-400" x-text="ticket.customer.cid ?? '—'"></span>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 border-b border-border">
                                <div class="p-2.5 border-r border-border">
                                    <span class="block text-[10px] font-bold uppercase text-text-muted">No HP</span>
                                    <template x-if="ticket.customer.phone">
                                        <a :href="'https://wa.me/' + ticket.customer.phone" target="_blank" rel="noopener"
                                           class="font-mono text-emerald-600 dark:text-emerald-400 hover:underline" x-text="ticket.customer.phone"></a>
                                    </template>
                                    <span x-show="!ticket.customer.phone" class="text-text-muted">—</span>
                                </div>
                                <div class="p-2.5">
                                    <span class="block text-[10px] font-bold uppercase text-text-muted">Paket</span>
                                    <span class="font-semibold text-text-secondary" x-text="ticket.customer.package ?? '—'"></span>
                                </div>
                            </div>
                            <div class="p-2.5 border-b border-border">
                                <span class="block text-[10px] font-bold uppercase text-text-muted">Alamat / Desa</span>
                                <span class="text-text-secondary" x-text="[ticket.customer.address, ticket.customer.village].filter(Boolean).join(' — ') || '—'"></span>
                                <template x-if="ticket.customer.maps_url">
                                    <a :href="ticket.customer.maps_url" target="_blank" rel="noopener"
                                       class="block mt-1 font-bold text-sky-600 dark:text-sky-400 hover:underline">Buka di Google Maps</a>
                                </template>
                            </div>
                            <div class="grid grid-cols-2">
                                <div class="p-2.5 border-r border-border">
                                    <span class="block text-[10px] font-bold uppercase text-text-muted">POP / ODP</span>
                                    <span class="text-text-secondary"
                                          x-text="(ticket.customer.pop ?? '—') + ' / ' + (ticket.customer.odp ?? '—')"></span>
                                </div>
                                <div class="p-2.5">
                                    <span class="block text-[10px] font-bold uppercase text-text-muted">Perangkat</span>
                                    <span class="font-mono text-text-secondary" x-text="ticket.customer.device ?? '—'"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    
                    <div class="rounded-xl border border-border bg-surface-muted p-4 space-y-3">
                        <h3 class="text-[11px] font-bold uppercase tracking-wider text-sky-600 dark:text-sky-400 border-b border-border pb-2">
                            Keluhan &amp; Catatan
                        </h3>
                        <div>
                            <p class="text-[10px] font-bold uppercase text-text-muted mb-1">Detail Keluhan</p>
                            <p class="p-3 rounded-lg border border-border bg-surface text-text-secondary whitespace-pre-line"
                               x-text="ticket.detail_keluhan || '—'"></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase text-text-muted mb-1">Catatan Teknis</p>
                            <p class="p-3 rounded-lg border border-border bg-surface text-text-muted italic whitespace-pre-line"
                               x-text="ticket.catatan_teknis || '—'"></p>
                        </div>
                    </div>

                    
                    <div x-show="ticket.attachments.length" class="rounded-xl border border-border bg-surface-muted p-4 space-y-2">
                        <h3 class="text-[11px] font-bold uppercase tracking-wider text-text-secondary border-b border-border pb-2">Lampiran</h3>
                        <template x-for="file in ticket.attachments" :key="file.url">
                            <a :href="file.url" target="_blank" rel="noopener"
                               class="flex items-center justify-between gap-2 p-2.5 rounded-lg border border-border bg-surface hover:bg-surface-muted transition-colors">
                                <div class="flex items-center gap-2 min-w-0">
                                    <template x-if="file.is_image">
                                        <svg class="h-4 w-4 shrink-0 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </template>
                                    <template x-if="!file.is_image">
                                        <svg class="h-4 w-4 shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                    </template>
                                    <span class="truncate font-medium text-text-main" x-text="file.name"></span>
                                </div>
                                <span class="text-[10px] text-text-muted shrink-0 font-mono" x-text="(file.size || '') + (file.uploader ? ' • ' + file.uploader : '')"></span>
                            </a>
                        </template>
                    </div>

                    
                    <template x-if="ticket.fop_task && ticket.fop_task.histories && ticket.fop_task.histories.length">
                        <div class="rounded-xl border border-border bg-surface-muted p-4 space-y-2">
                            <h3 class="text-[11px] font-bold uppercase tracking-wider text-text-secondary border-b border-border pb-2">
                                Riwayat Task FOP (Teknisi Lapangan)
                            </h3>
                            <template x-for="(history, index) in ticket.fop_task.histories" :key="index">
                                <div class="p-2.5 rounded-lg border border-border bg-surface space-y-1">
                                    <div class="flex items-center justify-between gap-2 flex-wrap">
                                        <span class="px-2 py-0.5 rounded border border-border bg-surface-muted text-[10px] font-bold font-mono text-text-main" x-text="history.label"></span>
                                        <span class="text-[10px] font-mono text-text-muted" x-text="history.happened_at"></span>
                                    </div>
                                    <p class="text-text-secondary">oleh <span class="font-semibold text-text-main" x-text="history.changed_by"></span></p>
                                </div>
                            </template>
                        </div>
                    </template>

                    
                    <div class="rounded-xl border border-border bg-surface-muted p-4 space-y-2">
                        <h3 class="text-[11px] font-bold uppercase tracking-wider text-text-secondary border-b border-border pb-2">
                            Riwayat Ticket &amp; Audit
                        </h3>
                        <template x-for="(history, index) in ticket.histories" :key="index">
                            <div class="p-2.5 rounded-lg border border-border bg-surface space-y-1">
                                <div class="flex items-center justify-between gap-2 flex-wrap">
                                    <span class="px-2 py-0.5 rounded border text-[10px] font-bold font-mono" :class="history.badge" x-text="history.label"></span>
                                    <span class="text-[10px] font-mono text-text-muted" x-text="history.happened_at"></span>
                                </div>
                                <p class="text-text-secondary">oleh <span class="font-semibold text-text-main" x-text="history.actor"></span></p>
                                <p x-show="history.reason" class="p-2 rounded-md border border-border bg-surface-muted font-mono text-text-muted"
                                   x-text="'Alasan: ' + history.reason"></p>
                            </div>
                        </template>
                        <p x-show="!ticket.histories.length" class="text-text-muted">Belum ada riwayat.</p>
                    </div>
                </div>
            </template>
        </div>

        <div class="p-3 bg-surface-muted border-t border-border flex items-center justify-between shrink-0">
            <span class="text-[10px] text-text-muted">Semua perubahan status tercatat di riwayat ticket</span>
            <button type="button" @click="close()"
                    class="px-4 py-1.5 rounded-lg border border-border bg-surface text-text-secondary hover:bg-surface-muted text-xs font-bold cursor-pointer">
                Tutup
            </button>
        </div>
    </div>
</div>

<?php $__env->startPush('scripts'); ?>
<script>
    function ticketDetailDrawer() {
        return {
            shown: false,
            loading: false,
            failed: false,
            ticket: null,

            get hasAnyAction() {
                const a = this.ticket?.actions ?? {};

                return Boolean(a.can_close || a.can_escalate_noc || a.can_escalate_fop || a.can_return_to_helpdesk || a.can_cancel);
            },

            async open(id) {
                // Tiket yang sama dibuka ulang tetap di-fetch: flag aksi &
                // riwayat bisa berubah gara-gara aksi user lain.
                this.shown = true;
                this.loading = true;
                this.failed = false;
                this.ticket = null;

                try {
                    const res = await fetch(`<?php echo e(url('/api/tickets')); ?>/${id}/detail`, {
                        headers: { 'Accept': 'application/json' },
                    });

                    if (! res.ok) {
                        this.failed = true;
                        return;
                    }

                    this.ticket = await res.json();
                } catch (e) {
                    this.failed = true;
                } finally {
                    this.loading = false;
                }
            },

            close() {
                this.shown = false;
            },

            /**
             * Aksi TIDAK dieksekusi di sini — halaman pemanggil yang punya
             * dialog konfirmasi + jalur POST-nya sendiri (satu sumber per
             * halaman, biar riwayat & refresh daftar tetap konsisten).
             */
            act(action) {
                window.dispatchEvent(new CustomEvent('ticket-drawer-action', {
                    detail: { id: this.ticket.id, action },
                }));
            },
        };
    }
</script>
<?php $__env->stopPush(); ?>
<?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/tickets/partials/detail-drawer.blade.php ENDPATH**/ ?>