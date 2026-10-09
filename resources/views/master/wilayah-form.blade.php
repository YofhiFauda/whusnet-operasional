@extends('layouts.app')

@php
    $isEdit = $region !== null;
    $action = $isEdit
        ? route('master.wilayah.update', ['level' => $level, 'id' => $region->id])
        : route('master.wilayah.store');
@endphp

@section('title', ($isEdit ? 'Ubah ' : 'Tambah ') . $levelLabel)
@section('page_title', ($isEdit ? 'Ubah ' : 'Tambah ') . $levelLabel)

@section('content')
<div class="max-w-xl space-y-6">
    <div class="flex items-center gap-2 text-xs font-medium text-slate-500 dark:text-slate-400">
        <a href="{{ route('master.wilayah.kelola', ['level' => $level]) }}" class="text-sky-600 dark:text-sky-400 hover:underline">Kelola Wilayah — {{ $levelLabel }}</a>
        <span>/</span>
        <span class="text-slate-700 dark:text-slate-200 font-semibold">{{ $isEdit ? 'Ubah' : 'Tambah' }}</span>
    </div>

    @if($errors->any())
        <div class="px-4 py-3 rounded-lg text-sm bg-rose-50 text-rose-800 border border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $action }}" class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 space-y-5">
        @csrf
        @if($isEdit)
            @method('PUT')
        @else
            <input type="hidden" name="level" value="{{ $level }}">
        @endif

        @if($isEdit && $parentLabel)
            <div class="text-sm text-slate-600 dark:text-slate-300">
                Induk: <span class="font-semibold">{{ $parentLabel }}</span>
                <span class="text-xs text-slate-400">(induk tidak bisa diubah)</span>
            </div>
        @endif

        @if(! $isEdit && $level === 'kecamatan')
            <div>
                <label for="city_id" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Kota/Kabupaten</label>
                <select id="city_id" name="city_id" required class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100">
                    <option value="">— pilih —</option>
                    @foreach($cities as $city)
                        <option value="{{ $city->id }}" @selected(old('city_id') == $city->id)>{{ $city->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @if(! $isEdit && $level === 'desa')
            <div>
                <label for="district_id" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Kecamatan</label>
                <select id="district_id" name="district_id" required class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100">
                    <option value="">— pilih —</option>
                    @foreach($districts as $district)
                        <option value="{{ $district->id }}" @selected(old('district_id') == $district->id)>{{ $district->name }}, {{ $district->city?->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div>
            <label for="name" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Nama {{ $levelLabel }}</label>
            <input id="name" type="text" name="name" required maxlength="100"
                   value="{{ old('name', $region?->name) }}"
                   class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100">
        </div>

        @if($level === 'desa')
        <div>
            <label for="postal_code" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Kode Pos <span class="text-xs text-slate-400">(opsional)</span></label>
            <input id="postal_code" type="text" name="postal_code" maxlength="10"
                   value="{{ old('postal_code', $region?->postal_code) }}"
                   class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100">
        </div>
        @endif

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-sky-600 rounded-lg hover:bg-sky-700">Simpan</button>
            <a href="{{ route('master.wilayah.kelola', ['level' => $level]) }}" class="text-sm text-slate-600 dark:text-slate-300 hover:underline">Batal</a>
        </div>
    </form>
</div>
@endsection
