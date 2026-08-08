{{--
    Halaman Ticket Dibatalkan — file sendiri, dirender
    TicketDibatalkanController. Kembaran tickets/selesai.blade.php; bedanya
    cuma identitas halaman + permission-nya (tickets.dibatalkan.view).
--}}
@extends('layouts.app')

@section('title', 'Ticket Dibatalkan — Ticket Service Desk')
@section('page_title', 'Ticket Dibatalkan')

@section('content')
    @include('tickets.partials.archive')
@endsection
