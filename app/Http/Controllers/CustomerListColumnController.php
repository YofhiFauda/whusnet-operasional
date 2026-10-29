<?php

namespace App\Http\Controllers;

use App\Services\CustomerListColumnService;
use App\Support\CustomerListColumns;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Simpan/reset kolom List Pelanggan milik user yang sedang login. Dipanggil
 * lewat fetch dari halaman daftar (preferensi tampilan, bukan data bisnis),
 * jadi membalas JSON — bukan PRG.
 */
class CustomerListColumnController extends Controller
{
    public function update(Request $request, CustomerListColumnService $service): JsonResponse
    {
        $validated = $request->validate([
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['required', 'string', 'distinct', Rule::in(CustomerListColumns::keys())],
        ]);

        $service->save($request->user(), $validated['columns']);

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, CustomerListColumnService $service): JsonResponse
    {
        $service->reset($request->user());

        return response()->json(['success' => true]);
    }
}
