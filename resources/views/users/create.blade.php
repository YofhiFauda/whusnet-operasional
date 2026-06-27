@extends('layouts.app')

@section('title', 'Tambah User')
@section('page_title', 'Tambah User')

@section('content')
<div class="mb-4">
    <a href="{{ route('users.index') }}" class="text-sm text-sky-600 hover:text-sky-800">Kembali ke Daftar User</a>
</div>

<div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <form id="userForm" action="{{ route('users.store') }}" method="POST" class="space-y-6">
        @csrf
        @include('users._form', ['roles' => $roles, 'popTree' => $popTree])

        <div class="flex justify-end gap-3 border-t border-slate-200 pt-4">
            <a href="{{ route('users.index') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Batal</a>
            <button type="button" id="btnReviewAccess" onclick="openPreviewModal()" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Review Access</button>
        </div>
    </form>
</div>
@endsection
