@extends('layouts.app')

@section('title', 'Worksheet NOC — Ticket Service Desk')
@section('page_title', 'Worksheet NOC')

@php
    $tabLabels = ['masuk' => 'Ticket Masuk', 'diproses' => 'Ticket Diproses'];
    $tabDescriptions = [
        'masuk' => 'Ticket dikirim Helpdesk, belum di-check — Oncheck NOC dulu buat ambil alih.',
        'diproses' => 'Ticket yang udah lo Oncheck — selesaikan atau kirim ke FOP.',
    ];
@endphp

@section('content')
<div class="space-y-6 max-w-7xl mx-auto pb-12">

    <div>
        <h1 class="text-xl font-extrabold text-text-main tracking-tight">Worksheet NOC — {{ $tabLabels[$activeTab] }}</h1>
        <p class="text-xs text-text-muted mt-1 font-medium">{{ $tabDescriptions[$activeTab] }}</p>
    </div>

    {{--
        Dua tab = dua HALAMAN mandiri (route + permission sendiri, lihat
        NocWorksheetController). Yang muncul cuma yang user punya aksesnya.
    --}}
    <div class="flex items-center gap-1 border-b border-border overflow-x-auto pb-px">
        @foreach($tabs as $tab)
            <a href="{{ $tab['url'] }}"
               class="shrink-0 inline-flex items-center gap-2 px-4 py-2.5 text-xs font-bold uppercase tracking-wider border-b-2 transition-all
                      {{ $tab['active']
                            ? 'border-amber-600 text-amber-600 bg-amber-50/50 dark:bg-amber-950/20 rounded-t-lg'
                            : 'border-transparent text-text-muted hover:text-text-main hover:bg-slate-50 dark:hover:bg-slate-900/40' }}">
                <span>{{ $tab['label'] }}</span>
                <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-full
                            {{ $tab['active'] ? 'bg-amber-600 text-white' : 'bg-slate-200 dark:bg-slate-800 text-text-muted' }}">
                    {{ $tab['count'] }}
                </span>
            </a>
        @endforeach
    </div>

    <div class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs divide-y divide-border">
        @forelse($tickets as $ticket)
            @php $ticketActions = $ticket->actionFlagsFor(auth()->user()); @endphp

            <div class="flex flex-col border-l-4 border-l-amber-500 group" data-ticket-row="{{ $ticket->id }}">
                <a href="{{ route('tickets.show', $ticket) }}"
                   class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 hover:bg-amber-50/50 dark:hover:bg-slate-900/40 transition-colors cursor-pointer">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap text-xs">
                            <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded border {{ $ticket->type->badgeClasses() }}">{{ $ticket->type->value }}</span>
                            @if($ticket->priority)
                                <span class="text-[10px] font-semibold text-text-muted">• {{ $ticket->priority->value }}</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 mt-1 truncate">
                            <span class="font-mono font-bold text-xs text-sky-600 dark:text-sky-400 data-text">{{ $ticket->ticket_number }}</span>
                            <span class="text-text-muted text-xs">—</span>
                            <span class="text-xs font-semibold text-text-main truncate">{{ $ticket->customer->full_name ?? $ticket->customer_name ?? '—' }}</span>
                        </div>
                        <p class="text-xs text-text-muted truncate mt-0.5 line-clamp-1">{{ $ticket->detail_keluhan }}</p>
                        <div class="mt-1.5 text-[10px] text-text-muted font-medium">
                            Dikirim oleh <span class="font-bold text-text-secondary">{{ $ticket->creator->name ?? '—' }}</span>
                            @if($ticket->checkedBy())
                                — di-check oleh <span class="font-bold text-amber-700 dark:text-amber-400">{{ $ticket->checkedBy()->name }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="shrink-0 text-xs text-text-muted">
                        <span class="font-mono text-[11px]">{{ \App\Support\IndonesianDate::dateTime($ticket->created_at) }}</span>
                    </div>
                </a>

                <div class="flex items-center gap-2 px-4 pb-3 pl-4">
                    @if($ticketActions['can_oncheck_noc'])
                    <button type="button"
                            onclick="confirmTicketRowAction(this, '{{ route('tickets.oncheck-noc', $ticket) }}', {}, 'Oncheck NOC', 'Catatan (opsional)', false, 'Ambil alih tiket {{ $ticket->ticket_number }}?')"
                            class="px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-amber-600 text-white hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer">
                        Oncheck NOC
                    </button>
                    @endif

                    @if($ticketActions['can_close'])
                    <button type="button"
                            onclick="confirmTicketRowAction(this, '{{ route('tickets.close', $ticket) }}', {}, 'Selesaikan Tiket', 'Apa yang sudah dikerjakan? (opsional)', false, 'Tandai tiket {{ $ticket->ticket_number }} selesai?')"
                            class="px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer">
                        Selesai
                    </button>
                    @endif

                    @if($ticketActions['can_escalate_fop'])
                    <button type="button"
                            onclick="confirmTicketRowAction(this, '{{ route('tickets.escalate', $ticket) }}', {target: 'fop'}, 'Kirim Tiket ke FOP', 'Catatan buat FOP (opsional)', false, 'Kirim tiket {{ $ticket->ticket_number }} ke FOP?')"
                            class="px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-sky-600 text-white hover:bg-sky-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer">
                        Assign FOP
                    </button>
                    @endif

                    @if($ticketActions['can_return_to_helpdesk'])
                    <button type="button"
                            onclick="confirmTicketRowAction(this, '{{ route('tickets.return-to-helpdesk', $ticket) }}', {}, 'Kembalikan ke Helpdesk', 'Alasan dikembalikan (opsional)', false, 'Kembalikan tiket {{ $ticket->ticket_number }} ke Helpdesk?')"
                            class="px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-slate-600 text-white hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer">
                        Kembalikan
                    </button>
                    @endif

                    @if($ticketActions['can_cancel'])
                    <button type="button"
                            onclick="confirmTicketRowAction(this, '{{ route('tickets.cancel', $ticket) }}', {}, 'Batalkan Tiket', 'Alasan pembatalan (wajib diisi)', true, 'Batalkan tiket {{ $ticket->ticket_number }}?')"
                            class="px-3 py-1.5 rounded-md text-[10px] font-bold uppercase tracking-wide bg-red-600 text-white hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer">
                        Batalkan
                    </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="p-16 text-center space-y-3">
                <p class="text-sm font-bold text-text-main">Tidak ada ticket di tab ini</p>
            </div>
        @endforelse
    </div>

    <div class="pt-2">{{ $tickets->links() }}</div>

    {{--
        Konfirmasi + input alasan buat semua tombol aksi baris — numpang
        window.Dialog global (lihat tickets/partials/action-dialog.blade.php),
        bukan modal sendiri per halaman lagi.
    --}}
    @include('tickets.partials.action-dialog')
</div>
@endsection

@push('scripts')
<script>
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

            if (row) {
                row.style.transition = 'opacity 0.25s, max-height 0.25s';
                row.style.opacity = '0';
                row.style.maxHeight = '0px';
                row.style.overflow = 'hidden';
                setTimeout(() => { row.remove(); }, 250);
            }
        } catch (e) {
            window.Toast?.error('Gagal', 'Aksi gagal, coba lagi.');
            buttons.forEach(b => { b.disabled = false; });
        }
    }

    /**
     * Jembatan tombol baris → dialog global (window.confirmTicketAction, lihat
     * tickets/partials/action-dialog.blade.php) → performTicketAction().
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
</script>
@endpush
