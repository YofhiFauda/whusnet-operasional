
<?php if (! $__env->hasRenderedOnce('b7a615a4-31bf-4b8b-8427-7ae4b7836c55')): $__env->markAsRenderedOnce('b7a615a4-31bf-4b8b-8427-7ae4b7836c55'); ?>
<?php $__env->startPush('scripts'); ?>
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

        window.Dialog.show({
            title: opts.title,
            icon: opts.icon || 'warning',
            contentHtml: `
                <p class="mb-4">${escape(opts.message)}</p>
                <label for="${fieldId}" class="block text-xs font-semibold text-text-secondary mb-1.5">
                    ${escape(opts.label)}
                </label>
                <textarea id="${fieldId}" rows="3" maxlength="1000"
                    class="w-full text-sm rounded-lg border border-border bg-background p-2.5 text-text-main focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500 transition-all"></textarea>
                <p id="${errorId}" class="hidden text-xs text-rose-600 mt-1.5">Alasan wajib diisi untuk aksi ini.</p>
            `,
            buttons: [
                {
                    text: 'Batal',
                    type: 'secondary',
                    onClick: () => window.Dialog.close(),
                },
                {
                    text: opts.confirmText || 'Ya, Lanjutkan',
                    type: opts.confirmType || 'primary',
                    onClick: (e) => {
                        const button = e.currentTarget;
                        const input = document.getElementById(fieldId);
                        const reason = (input?.value || '').trim();

                        if (opts.required && reason === '') {
                            document.getElementById(errorId)?.classList.remove('hidden');
                            // window.Dialog nge-disable tombol begitu diklik —
                            // hidupin lagi biar user bisa submit ulang abis
                            // ngisi alasannya (kalau enggak, dialog jadi buntu).
                            button.disabled = false;
                            button.classList.remove('opacity-50', 'cursor-not-allowed');
                            input?.focus();

                            return;
                        }

                        window.Dialog.close();
                        opts.onConfirm(reason);
                    },
                },
            ],
        });

        setTimeout(() => document.getElementById(fieldId)?.focus(), 350);
    };
</script>
<?php $__env->stopPush(); ?>
<?php endif; ?>
<?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/tickets/partials/action-dialog.blade.php ENDPATH**/ ?>