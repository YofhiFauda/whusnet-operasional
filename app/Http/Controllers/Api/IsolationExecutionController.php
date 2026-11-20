<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint eksekusi isolir dari Web A
 * (docs/superpowers/specs/2026-11-20-webhook-isolir-web-a-design.md). Stub —
 * diisi Task 5 plan, dibuat di sini cuma supaya route Task 3 bisa diuji
 * token-nya tanpa menunggu logic eksekusi selesai.
 */
class IsolationExecutionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'belum diimplementasi'], 501);
    }
}
