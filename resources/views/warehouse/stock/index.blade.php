@extends('layouts.app')

@section('title', 'Kelola Stok Gudang - Whusnet Operasional')
@section('page_title', 'Kelola Stok')

@section('content')
<x-warehouse.header active="stock" />

<div class="mb-6 space-y-4">
    @php
        $collapseGudangOver = 5;
        $forceOpen = $lockedSinglePop !== null || $boardFilters->isFiltering();
        $popCount = $viewMode === 'matrix' ? $matrix['pops']->count() : $groups->count();
        $startOpen = $forceOpen || $popCount <= $collapseGudangOver;
    @endphp

    @if($viewMode === 'matrix')
    <!-- matriks:awal -->
    <div x-data="stockBoard({ collapseGudangOver: {{ $collapseGudangOver }} })"
         data-pop-count="{{ $popCount }}"
         data-force-open="{{ $forceOpen ? 'true' : 'false' }}">
        @include('warehouse.stock.board._toolbar')
        @include('warehouse.stock.board._matrix')
    </div>
    <!-- matriks:akhir -->
    @else
    <!-- papan:awal -->
    <div x-data="stockBoard({ collapseGudangOver: {{ $collapseGudangOver }} })"
         data-pop-count="{{ $popCount }}"
         data-force-open="{{ $forceOpen ? 'true' : 'false' }}">
        @include('warehouse.stock.board._toolbar')
        @include('warehouse.stock.board._table')
    </div>
    <!-- papan:akhir -->
    @endif
</div>
@endsection