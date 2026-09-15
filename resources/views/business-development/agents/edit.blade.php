@extends('layouts.app')

@section('title', 'Ubah Agent - Whusnet Operasional')
@section('page_title', 'Ubah Agent')

@section('content')
<form action="{{ route('business-development.agents.update', $agent) }}" method="POST" class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 max-w-xl">
    @csrf
    @method('PUT')
    @include('business-development.agents._form')

    <button type="submit" class="px-4 py-2 text-sm font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition-colors">
        Simpan Perubahan
    </button>
</form>
@endsection
