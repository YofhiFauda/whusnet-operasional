@extends('layouts.app')

@section('title', 'Antrean Survey - Whusnet Operasional')
@section('page_title', 'Antrean Survey Lapangan')

@section('content')
<!-- Top Action Bar -->
<div class="flex justify-between items-center mb-6">
    <h3 class="text-slate-800 text-sm font-semibold uppercase tracking-wider">Antrean Survey Pelanggan</h3>
</div>

<!-- Filter & Search Panel -->
<div class="bg-white border border-slate-200 rounded-lg p-6 mb-6">
    <form action="{{ route('surveys.queue') }}" method="GET" class="flex flex-col sm:flex-row gap-4 items-end">
        <!-- Search -->
        <div class="flex-1">
            <label for="search" class="block text-xs font-semibold text-slate-500 mb-2">CARI PELANGGAN</label>
            <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Cari nama, No. HP, atau ID Lama..." class="w-full font-sans text-sm px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-2">
            <button type="submit" class="bg-sky-600 hover:bg-sky-700 text-white text-sm font-medium py-2 px-6 rounded-md transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-sky-500/25">
                Cari
            </button>
            <a href="{{ route('surveys.queue') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium py-2 px-4 rounded-md transition-colors cursor-pointer text-center focus:outline-none">
                Reset
            </a>
        </div>
    </form>
</div>

<!-- Table Content -->
<div class="bg-white border border-slate-200 rounded-lg overflow-hidden">
    <div class="border-b border-slate-200 bg-sky-50 px-6 py-3 flex items-center justify-between">
        <span class="text-sm font-bold text-sky-800 uppercase tracking-wider">Daftar Antrean Survey</span>
    </div>

    <!-- Table Container -->
    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-sm text-slate-700">
            <thead>
                <tr class="bg-slate-50/50 border-b border-slate-200 text-slate-500 font-semibold text-xs">
                    <th class="px-6 py-3.5 w-12 text-center">NO</th>
                    <th class="px-6 py-3.5">ID</th>
                    <th class="px-6 py-3.5">NAMA</th>
                    <th class="px-6 py-3.5">HP</th>
                    <th class="px-6 py-3.5">DESA</th>
                    <th class="px-6 py-3.5 text-center">STATUS</th>
                    <th class="px-6 py-3.5">INSERTED AT</th>
                    <th class="px-6 py-3.5">WAKTU (LIVE)</th>
                    <th class="px-6 py-3.5 text-right">ACTION</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($customers as $customer)
                @php
                    $survey = $customer->latestSurvey()->first();
                @endphp
                <tr class="hover:bg-slate-50/45 transition-colors">
                    <td class="px-6 py-3.5 text-center text-slate-400 font-mono">{{ $loop->iteration }}</td>
                    <td class="px-6 py-3.5 whitespace-nowrap font-mono">{{ $customer->display_id }}</td>
                    <td class="px-6 py-3.5 font-medium text-slate-900">{{ $customer->full_name }}</td>
                    <td class="px-6 py-3.5 font-mono">{{ $customer->primary_phone }}</td>
                    <td class="px-6 py-3.5 font-medium">{{ $customer->village->name ?? '-' }}</td>
                    <td class="px-6 py-3.5 text-center">
                        @if($customer->status === 'waiting_survey')
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold tracking-wide uppercase bg-amber-50 text-amber-700 border border-amber-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> WAITING
                            </span>
                        @elseif($customer->status === 'survey_in_progress')
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold tracking-wide uppercase bg-sky-50 text-sky-700 border border-sky-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-sky-500 animate-pulse"></span> IN PROGRESS
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-3.5 font-mono text-xs">{{ $customer->created_at->format('Y-m-d H:i:s') }}</td>
                    <td class="px-6 py-3.5 font-mono text-xs">
                        @if($customer->status === 'survey_in_progress' && $survey && $survey->started_at)
                            <!-- Placeholder for Countdown Component -->
                            <div class="text-sky-600 font-bold" id="countdown-{{ $customer->id }}" data-start="{{ $survey->started_at->toIso8601String() }}">
                                Menghitung...
                            </div>
                        @else
                            <span class="text-slate-400">-</span>
                        @endif
                    </td>
                    <td class="px-6 py-3.5 text-right whitespace-nowrap">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('customers.show', $customer) }}" class="text-slate-400 hover:text-sky-600 transition-colors p-1" title="Detail">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                            </a>
                            
                            @if($customer->status === 'waiting_survey')
                                <form action="{{ route('customers.survey.start', $customer) }}" method="POST" onsubmit="event.preventDefault(); window.confirmAction('Mulai proses survey untuk pelanggan ini?', this);">
                                    @csrf
                                    <button type="submit" class="bg-sky-600 hover:bg-sky-700 text-white text-[11px] font-bold uppercase tracking-wider py-1.5 px-3 rounded shadow-sm transition-colors cursor-pointer">
                                        Mulai Survey
                                    </button>
                                </form>
                            @elseif($customer->status === 'survey_in_progress')
                                <a href="{{ route('customers.survey.report', $customer) }}" class="bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold uppercase tracking-wider py-1.5 px-3 rounded shadow-sm transition-colors cursor-pointer inline-block">
                                    Lapor Data
                                </a>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-6 py-8 text-center text-slate-500">
                        <div class="flex flex-col items-center justify-center gap-2">
                            <svg class="w-8 h-8 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                            <span class="text-sm font-medium">Tidak ada antrean survey saat ini.</span>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    @if($customers->hasPages())
        <div class="border-t border-slate-200 px-6 py-4 bg-slate-50/50">
            {{ $customers->links() }}
        </div>
    @endif
</div>

<script>

    // Live Countdown Logic
    document.addEventListener('DOMContentLoaded', function() {
        const countdownElements = document.querySelectorAll('[id^="countdown-"]');
        
        function updateCountdowns() {
            const now = new Date();
            
            countdownElements.forEach(el => {
                const startTimeStr = el.getAttribute('data-start');
                if (!startTimeStr) return;
                
                const startTime = new Date(startTimeStr);
                const diffMs = now - startTime;
                
                if (diffMs < 0) {
                    el.textContent = "00:00:00";
                    return;
                }
                
                const hours = Math.floor(diffMs / 3600000);
                const minutes = Math.floor((diffMs % 3600000) / 60000);
                const seconds = Math.floor((diffMs % 60000) / 1000);
                
                const h = String(hours).padStart(2, '0');
                const m = String(minutes).padStart(2, '0');
                const s = String(seconds).padStart(2, '0');
                
                el.textContent = `${h}:${m}:${s}`;
            });
        }
        
        if (countdownElements.length > 0) {
            updateCountdowns(); // Initial call
            setInterval(updateCountdowns, 1000); // Update every second
        }
    });
</script>
@endsection
