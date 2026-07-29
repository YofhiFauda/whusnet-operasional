{{--
    Halaman Ticket Selesai — file sendiri (bukan lagi numpang view bucket
    generik), dirender TicketSelesaiController. Isi list/toolbar/aksi-nya
    dishare sama halaman Ticket Dibatalkan lewat partial di bawah; yang beda
    cuma identitas halaman + permission-nya (tickets.selesai.view).
--}}
@extends('layouts.app')

@section('title', 'Ticket Selesai — Ticket Service Desk')
@section('page_title', 'Ticket Selesai')

@section('content')
    @include('tickets.partials.archive')
@endsection
