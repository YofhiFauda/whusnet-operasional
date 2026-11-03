{{--
    Status+handler tiket, dipakai DUA jalur: inline di tickets/show.blade.php
    (render awal) dan response endpoint tickets.status-panel (live-refresh via
    Echo tickets.{popId}/TicketQueueUpdated). Branch isBatch() diputuskan DI
    SINI (bukan dioper dari caller) biar partial ini bisa langsung dipakai
    controller tanpa caller perlu tahu/hitung ulang isBatch().
--}}
@if($ticket->isBatch())
    <div id="ticket-status-panel" class="p-4 rounded-lg border border-border bg-surface shadow-2xs space-y-1">
        <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted flex items-center justify-between">
            <span>Meja Penanggungjawab</span>
            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
        </span>
        <div class="text-base font-bold text-text-main">
            {{ $ticket->handler->label() }}
        </div>
        <p class="text-[11px] text-text-muted">
            Status: <strong class="text-text-main">{{ $ticket->statusLabel() }}</strong>
        </p>
    </div>
@else
    <span id="ticket-status-panel" class="text-xs font-bold px-2.5 py-0.5 rounded border {{ $ticket->statusBadgeClasses() }}">
        {{ $ticket->statusLabel() }}
    </span>
@endif
