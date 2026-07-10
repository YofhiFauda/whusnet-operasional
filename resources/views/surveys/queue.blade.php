@extends('layouts.app')

@section('title', 'Antrean Survey - Whusnet Operasional')
@section('page_title', 'Antrean Survey Lapangan')

@section('content')
<!-- Top Action Bar -->
<div class="flex justify-between items-center mb-6">
    <h3 class="text-text-main text-sm font-semibold uppercase tracking-wider">Antrean Survey Pelanggan</h3>
</div>

<!-- Filter & Search Panel -->
<div class="bg-surface border border-border rounded-lg p-6 mb-6">
    <form action="{{ route('surveys.queue') }}" method="GET" class="flex flex-col sm:flex-row gap-4 items-end">
        <!-- Search -->
        <div class="flex-1 w-full">
            <label for="search" class="block text-xs font-semibold text-text-muted mb-2">CARI PELANGGAN</label>
            <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Cari nama, No. HP, atau ID Lama..." class="w-full font-sans text-sm px-3 py-2 border border-border rounded-md bg-background text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25">
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-2 w-full sm:w-auto">
            <button type="submit" class="flex-1 sm:flex-none text-sm font-semibold py-2 px-6 rounded-md text-white transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary/25" style="background:var(--color-primary)">
                Cari
            </button>
            <a href="{{ route('surveys.queue') }}" class="flex-1 sm:flex-none bg-surface-muted hover:bg-border text-text-secondary text-sm font-semibold py-2 px-4 rounded-md transition-colors cursor-pointer text-center focus:outline-none border border-border">
                Reset
            </a>
        </div>
    </form>
</div>

<!-- Table Content -->
<div class="bg-surface border border-border rounded-lg overflow-hidden">
    <div class="border-b border-border px-6 py-3 flex items-center justify-between" style="background:var(--color-primary-soft)">
        <span class="text-sm font-bold uppercase tracking-wider" style="color:var(--color-primary)">Daftar Antrean Survey</span>
    </div>

    <!-- Table Container -->
    <div class="overflow-x-hidden sm:overflow-x-auto bg-surface">
        <table class="w-full border-collapse text-left text-sm text-text-main">
            <thead class="hidden sm:table-header-group">
                <tr class="bg-surface-muted border-b border-border text-text-muted font-semibold text-xs">
                    <th class="px-6 py-3.5 w-12 text-center">NO</th>
                    <th class="px-6 py-3.5">ID</th>
                    <th class="px-6 py-3.5">NAMA</th>
                    <th class="px-6 py-3.5">HP</th>
                    <th class="px-6 py-3.5">DESA</th>
                    <th class="px-6 py-3.5 text-center">STATUS</th>
                    <th class="px-6 py-3.5">INSERTED AT</th>
                    <th class="px-6 py-3.5 text-center">SISA SLA</th>
                    <th class="px-6 py-3.5 text-right">ACTION</th>
                </tr>
            </thead>
            <tbody class="block sm:table-row-group divide-y sm:divide-y-0 divide-border">
                @forelse($customers as $customer)
                @php
                    $survey = $customer->latestSurvey()->first();
                @endphp
                <tr class="hover:bg-surface-muted transition-colors flex flex-col sm:table-row p-4 sm:p-0 border-b sm:border-0 border-border">
                    <td class="hidden sm:table-cell px-6 py-3.5 text-center text-text-muted font-mono">{{ $loop->iteration }}</td>
                    
                    <td class="flex justify-between items-center sm:table-cell px-0 py-1 sm:px-6 sm:py-3.5">
                        <span class="sm:hidden text-[10px] font-bold text-text-muted uppercase">ID</span>
                        <span class="whitespace-nowrap font-mono text-text-main font-semibold sm:font-normal">{{ $customer->display_id }}</span>
                    </td>
                    
                    <td class="flex justify-between items-center sm:table-cell px-0 py-1 sm:px-6 sm:py-3.5">
                        <span class="sm:hidden text-[10px] font-bold text-text-muted uppercase">NAMA</span>
                        <span class="font-medium text-text-main">{{ $customer->full_name }}</span>
                    </td>
                    
                    <td class="flex justify-between items-center sm:table-cell px-0 py-1 sm:px-6 sm:py-3.5">
                        <span class="sm:hidden text-[10px] font-bold text-text-muted uppercase">HP</span>
                        <span class="font-mono text-text-secondary">{{ $customer->primary_phone }}</span>
                    </td>
                    
                    <td class="flex justify-between items-center sm:table-cell px-0 py-1 sm:px-6 sm:py-3.5">
                        <span class="sm:hidden text-[10px] font-bold text-text-muted uppercase">DESA</span>
                        <span class="font-medium text-text-secondary">{{ $customer->village->name ?? '-' }}</span>
                    </td>
                    
                    <td class="flex justify-between items-center sm:table-cell px-0 py-1 sm:px-6 sm:py-3.5 sm:text-center">
                        <span class="sm:hidden text-[10px] font-bold text-text-muted uppercase">STATUS</span>
                        <div>
                            @if($customer->status === 'waiting_survey')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold tracking-wide uppercase border" style="background:var(--color-warning-bg); color:var(--color-warning); border-color:var(--color-warning-border)">
                                    <span class="w-1.5 h-1.5 rounded-full" style="background:var(--color-warning)"></span> Menunggu Survey
                                </span>
                            @elseif($customer->status === 'survey_in_progress')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold tracking-wide uppercase border" style="background:var(--color-info-bg); color:var(--color-info); border-color:var(--color-info-border)">
                                    <span class="w-1.5 h-1.5 rounded-full animate-pulse" style="background:var(--color-info)"></span> Proses Survey
                                </span>
                            @endif
                        </div>
                    </td>
                    
                    <td class="flex justify-between items-center sm:table-cell px-0 py-1 sm:px-6 sm:py-3.5">
                        <span class="sm:hidden text-[10px] font-bold text-text-muted uppercase">INSERTED AT</span>
                        <span class="font-mono text-xs text-text-secondary">{{ $customer->created_at->format('Y-m-d H:i:s') }}</span>
                    </td>
                    
                    <td class="flex justify-between items-center sm:table-cell px-0 py-1 sm:px-6 sm:py-3.5 sm:text-center">
                        <span class="sm:hidden text-[10px] font-bold text-text-muted uppercase">SISA SLA</span>
                        @if(in_array($customer->status, ['waiting_survey', 'survey_in_progress']))
                            <x-countdown-timer 
                                deadline="{{ $customer->created_at->addDay()->toIso8601String() }}" 
                                :total-seconds="86400" 
                                label="Sisa Waktu Survey" 
                                :compact="true"
                            />
                        @else
                            <span class="text-text-muted">-</span>
                        @endif
                    </td>
                    
                    <td class="flex justify-end items-center sm:table-cell px-0 pt-3 sm:px-6 sm:py-3.5 mt-2 sm:mt-0 border-t sm:border-0 border-border border-dashed sm:text-right whitespace-nowrap">
                        <div class="flex items-center w-full sm:w-auto justify-end gap-2">
                            <a href="{{ route('customers.show', $customer) }}" class="text-text-muted hover:text-primary transition-colors p-1" title="Detail">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                            </a>
                            
                            @if($customer->status === 'waiting_survey')
                                <form action="{{ route('customers.survey.start', $customer) }}" method="POST" onsubmit="event.preventDefault(); window.confirmAction('Mulai proses survey untuk pelanggan ini?', this);" class="flex-1 sm:flex-none">
                                    @csrf
                                    <button type="submit" class="w-full sm:w-auto text-[11px] font-bold uppercase tracking-wider py-1.5 px-3 rounded shadow-sm transition-colors cursor-pointer text-white" style="background:var(--color-warning)">
                                        Mulai Survey
                                    </button>
                                </form>
                            @elseif($customer->status === 'survey_in_progress')
                                <a href="{{ route('customers.survey.report', $customer) }}" class="flex-1 sm:flex-none text-center w-full sm:w-auto text-[11px] font-bold uppercase tracking-wider py-1.5 px-3 rounded shadow-sm transition-colors cursor-pointer inline-block text-white" style="background:var(--color-success)">
                                    Lapor Data
                                </a>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr class="block sm:table-row">
                    <td colspan="9" class="px-6 py-8 text-center text-text-muted">
                        <div class="flex flex-col items-center justify-center gap-2">
                            <svg class="w-8 h-8 text-border" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                            <span class="text-sm font-medium">Tidak ada antrean survey saat ini.</span>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    @if($customers->hasPages())
        <div class="border-t border-border px-6 py-4 bg-surface-muted">
            {{ $customers->links() }}
        </div>
    @endif
</div>
@endsection
