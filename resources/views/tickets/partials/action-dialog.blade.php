{{--
    Dialog konfirmasi + input alasan buat SEMUA aksi tiket (Selesai / Ke NOC /
    Ke FOP / Oncheck NOC / Kembalikan / Batalkan), dipakai bareng tiga tempat:
    panel "List Task Ticketing" (tickets/create.blade.php), halaman arsip
    (tickets/partials/archive.blade.php), dan Worksheet NOC
    (noc/worksheet.blade.php).

    Numpang window.Dialog global (components/dialog.blade.php) — BUKAN
    confirm() native, dan bukan modal sendiri-sendiri per halaman kayak
    sebelumnya. Alasannya: satu tampilan konsisten, dan confirm() native gak
    bisa nampung textarea alasan (padahal `reason` itu yang ngisi
    ticket_histories.reason — lihat TicketService).

    Pemanggil yang urus POST-nya sendiri (tiap halaman beda cara: Alpine
    fetch vs helper global), helper ini cuma balikin `reason` lewat callback.
--}}
@once
@push('scripts')
<script>
    /**
     * Konfirmasi aksi tiket + ambil alasan/catatan.
     *
     * @param {Object}   opts
     * @param {string}   opts.title        Judul dialog.
     * @param {string}   opts.message      Kalimat konfirmasi.
     * @param {string}   opts.label        Label textarea.
     * @param {boolean}  opts.required     Alasan wajib diisi (dipakai Batalkan).
     * @param {string}   [opts.confirmText]
     * @param {string}   [opts.confirmType] primary | danger
     * @param {string}   [opts.icon]        warning | error | info | success
     * @param {Function} opts.onConfirm    Dipanggil dengan (reason).
     */
    window.confirmTicketAction = function (opts) {
        const fieldId = 'ticket-action-reason';
        const errorId = fieldId + '-error';

        const escape = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        })[c]);

        const doConfirm = () => {
            const input = document.getElementById(fieldId);
            const reason = (input?.value || '').trim();

            if (opts.required && reason === '') {
                document.getElementById(errorId)?.classList.remove('hidden');
                input?.focus();
                return;
            }

            window.Dialog.close();
            opts.onConfirm(reason);
        };

        window.Dialog.show({
            title: opts.title,
            icon: opts.icon || 'warning',
            contentHtml: `
                <p class="mb-3 text-text-main font-medium">${escape(opts.message)}</p>
                <label for="${fieldId}" class="block text-xs font-bold text-text-secondary mb-1 uppercase tracking-wider">
                    ${escape(opts.label)}
                </label>
                <textarea id="${fieldId}" rows="3" maxlength="1000"
                    placeholder="Ketik alasan / catatan jika ada, lalu tekan Enter..."
                    class="w-full text-xs rounded-lg border border-border bg-background p-2.5 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all"></textarea>
                <div class="flex items-center justify-between mt-1 text-[10px] text-text-muted">
                    <span>Tekan <kbd class="px-1 py-0.2 bg-surface-muted rounded border border-border font-mono">Enter</kbd> untuk konfirmasi, <kbd class="px-1 py-0.2 bg-surface-muted rounded border border-border font-mono">Shift+Enter</kbd> baris baru</span>
                    <span id="${errorId}" class="hidden text-rose-600 font-semibold">Alasan wajib diisi</span>
                </div>
            `,
            buttons: [
                {
                    text: 'Batal (Esc)',
                    type: 'secondary',
                    onClick: () => window.Dialog.close(),
                },
                {
                    text: (opts.confirmText || 'Ya, Lanjutkan') + ' (Enter)',
                    type: opts.confirmType || 'primary',
                    onClick: () => doConfirm(),
                },
            ],
        });

        setTimeout(() => {
            const textarea = document.getElementById(fieldId);
            if (textarea) {
                textarea.focus();
                textarea.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        e.stopPropagation();
                        doConfirm();
                    } else if (e.key === 'Escape') {
                        e.preventDefault();
                        e.stopPropagation();
                        window.Dialog.close();
                    }
                });
            }
        }, 50);
    };
</script>
@endpush
@endonce
