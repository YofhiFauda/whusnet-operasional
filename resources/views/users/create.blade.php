@extends('layouts.app')

@section('title', 'Tambah User')
@section('page_title', 'Tambah User')

@section('content')
<div class="mb-4">
    <a href="{{ route('users.index') }}" class="text-sm text-sky-600 hover:text-sky-800 dark:text-sky-300">Kembali ke Daftar User</a>
</div>

<div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-6 shadow-sm">
    <form id="userForm" action="{{ route('users.store') }}" method="POST" class="space-y-6">
        @csrf
        @include('users._form', ['roles' => $roles, 'popTree' => $popTree])

        <div class="flex justify-end gap-3 border-t border-slate-200 dark:border-slate-700 pt-4">
            <a href="{{ route('users.index') }}" class="rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 dark:bg-slate-800/50">Batal</a>
            <button type="button" id="btnReviewAccess" onclick="openPreviewModal()" class="rounded-md bg-sky-600 dark:bg-sky-500 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700 dark:hover:bg-sky-600">Review Access</button>
        </div>
    </form>
</div>
@endsection
