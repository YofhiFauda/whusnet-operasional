@extends('layouts.app')

{{-- Grup `failed`/`terminated` TIDAK dilayani view ini lagi — keduanya punya
     halaman sendiri (customers/failed.blade.php, customers/terminated.blade.php)
     dengan route + permission sendiri; CustomerController::index() me-redirect
     /customers?status_group=failed|terminated ke sana. Yang tersisa di sini:
     daftar utama + grup `survey`/`verification` yang memang masih tampil sebagai
     varian List Pelanggan. --}}
@php
    $pageTitle = match ($statusGroup) {
        'survey' => 'Survey Pelanggan',
        'verification' => 'Verifikasi Pelanggan',
        default => 'List Pelanggan',
    };
@endphp

@section('title', $pageTitle . ' - Whusnet Operasional')
@section('page_title', $pageTitle)
@section('breadcrumb_parent', 'Pelanggan')
@section('breadcrumb_parent_url', '/customers')

@section('content')
@include('customers.partials._list_styles')
@include('customers.partials._list_header')
@include('customers.partials._list_stats')
@include('customers.partials._list_filters')

{{-- Bulk action bar & checkbox pemilihan baris DIHAPUS: satu-satunya aksi massal
     yang tersedia ("Cetak Tagihan Massal") cuma toast placeholder tanpa backend,
     sementara kolom checkbox memakan lebar tabel dan menyandera navigasi keyboard
     (dulu daftar baris dideteksi dari .select-customer).

     Endpoint `invoices.payments.bulk-store` yang dulu disebut di sini SUDAH
     DIHAPUS (2026-08-10): tidak pernah punya UI maupun test, dan jaminannya
     menyimpang dari jalur batch kolektor — transaksi per-invoice (bukan
     all-or-nothing), tanpa idempotency, dan error ditelan jadi angka "gagal"
     tanpa alasan. Aksi massal yang benar-benar ada dan dipakai: tab Pembayaran
     di Worksheet Kolektor (`payment-batches.store`), lewat
     CollectorPaymentService. --}}

{{-- ────────────────────────────────────────────────────────────
     MAIN DATA CONTAINER: TABLE VIEW (DESKTOP) & CARD VIEW (MOBILE)
──────────────────────────────────────────────────────────── --}}
<div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm overflow-hidden mb-6">
    @if(!empty($statusGroup))
    {{-- Header grup survey/verification. Grup failed/terminated tidak lewat sini
         lagi — punya halaman sendiri. --}}
    <div class="border-b border-slate-200 dark:border-slate-800 px-6 py-3 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/30">
        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">
            @if($statusGroup === 'survey') Daftar Survey Pelanggan
            @elseif($statusGroup === 'verification') Daftar Verifikasi Pelanggan
            @endif
        </span>
        <a href="{{ route('customers.index') }}" class="text-xs text-sky-600 dark:text-sky-400 hover:underline">Lihat Semua Pelanggan</a>
    </div>
    @endif

    @include('customers.partials._list_table')

    @include('customers.partials._list_pagination')
</div>

@include('customers.partials._quick_hub_modal')
@endsection


@section('scripts')
@include('customers.partials._list_scripts')
@endsection
