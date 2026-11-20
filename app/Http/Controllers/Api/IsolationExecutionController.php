<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\IsolationExecutionException;
use App\Http\Controllers\Controller;
use App\Models\IsolationCase;
use App\Services\IsolationExecutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint eksekusi isolir dari Web A
 * (docs/superpowers/specs/2026-11-20-webhook-isolir-web-a-design.md).
 */
class IsolationExecutionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'case_number' => ['required', 'string', 'exists:isolation_cases,case_number'],
        ]);

        $case = IsolationCase::where('case_number', $data['case_number'])->firstOrFail();

        try {
            $result = app(IsolationExecutionService::class)->suspendFromWebA($case);
        } catch (IsolationExecutionException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'case_number' => $case->case_number,
            'status' => 'suspended',
            'already_suspended' => $result['already_suspended'],
        ]);
    }
}
