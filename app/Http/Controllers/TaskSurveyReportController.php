<?php

namespace App\Http\Controllers;

use App\Models\CustomerSurvey;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaskSurveyReportController extends Controller
{
    public function __construct(private readonly TaskService $taskService) {}

    /**
     * Simpan laporan survey 5-step dan selesaikan task.
     * Guard: task.status.complete + anggota tim
     */
    public function store(Request $request, Task $task): JsonResponse
    {
        $this->authorize('statusComplete', $task);

        $validated = $request->validate([
            // Step 3 — Cek Sinyal
            'signal_strength_dbm' => 'nullable|integer|min:-120|max:0',
            'signal_note'         => 'nullable|string|max:500',

            // Step 4 — Teknis Jaringan
            'cable_estimation_meter' => 'required|integer|min:0',
            'nearest_odp'            => 'required|string|max:255',
            'media_type'             => 'required|in:Fiber,Wireless,UTP',

            // Step 5 — Kesimpulan
            'survey_result'  => 'required|in:layak,tidak_layak,kunjungan_ulang',
            'reject_reason'  => 'required_if:survey_result,tidak_layak|nullable|string|max:1000',
            'difficulty_level' => 'required|in:MUDAH,SEDANG,SULIT',

            // Tanda tangan teknisi (base64 data URI dari canvas)
            'signature_data'  => 'required|string',
        ]);

        // Pastikan teknisi memang anggota tim task ini
        abort_unless(
            $task->isMember(auth()->id()),
            403,
            'Anda bukan anggota tim task ini.'
        );

        // Pastikan task masih in_progress
        abort_unless(
            $task->status->value === 'in_progress',
            422,
            'Task hanya bisa diselesaikan dari status In Progress.'
        );

        // Pastikan minimal 1 foto bukti sudah diupload
        abort_unless(
            $task->evidences()->count() >= 1,
            422,
            'Minimal 1 foto bukti harus diupload sebelum menyelesaikan task.'
        );

        DB::transaction(function () use ($task, $validated) {
            $user = auth()->user();

            // Simpan tanda tangan teknisi sebagai file PNG
            $signaturePath = null;
            if (!empty($validated['signature_data'])) {
                $signaturePath = $this->saveSignature(
                    $validated['signature_data'],
                    "signatures/task-{$task->id}"
                );
            }

            // Susun catatan gabungan
            $surveyNote = "Tingkat Kesulitan: {$validated['difficulty_level']}";
            $surveyNote .= "\nMedia: {$validated['media_type']}";
            if (!empty($validated['signal_strength_dbm'])) {
                $surveyNote .= "\nSignal: {$validated['signal_strength_dbm']} dBm";
            }
            if (!empty($validated['signal_note'])) {
                $surveyNote .= "\nCatatan Sinyal: {$validated['signal_note']}";
            }
            if ($validated['survey_result'] === 'tidak_layak' && !empty($validated['reject_reason'])) {
                $surveyNote .= "\nAlasan Tidak Layak: {$validated['reject_reason']}";
            }

            // Mapping hasil survey ke status customer_surveys
            $surveyStatus = match ($validated['survey_result']) {
                'layak'           => 'completed',
                'tidak_layak'     => 'failed',
                'kunjungan_ulang' => 'pending',
            };

            // Update atau buat record customer_survey jika task terhubung ke pelanggan
            if ($task->customer_id) {
                $survey = CustomerSurvey::where('customer_id', $task->customer_id)
                    ->latest()
                    ->first();

                if ($survey) {
                    $completedAt = now();
                    $survey->survey_status          = $surveyStatus;
                    $survey->cable_estimation_meter = $validated['cable_estimation_meter'];
                    $survey->nearest_odp            = $validated['nearest_odp'];
                    $survey->survey_note            = $surveyNote;
                    $survey->completed_at           = $completedAt;

                    if ($survey->started_at) {
                        $survey->duration_minutes = $survey->started_at->diffInMinutes($completedAt);
                        $survey->end_date         = $completedAt->toDateString();
                        $survey->end_time         = $completedAt->toTimeString();
                    }

                    // Simpan path tanda tangan teknisi ke survey_photo jika belum ada foto ODP
                    if ($signaturePath && !$survey->survey_photo) {
                        $survey->survey_photo = $signaturePath;
                    }

                    $survey->save();

                    // PENTING (S8.9-T004): 
                    // Teknisi hanya submit laporan lapangan. 
                    // Transisi customer ke Waiting ACC (Workflow) HANYA terjadi 
                    // setelah FOP meng-approve hasil laporan ini. (No Auto-Update)
                }
            }

            // Selesaikan task (catat completed_at)
            $this->taskService->complete($task, $user);
        });

        return response()->json([
            'success' => true,
            'message' => 'Laporan survey berhasil disimpan. Task telah diselesaikan.',
        ]);
    }

    /**
     * Simpan base64 signature ke storage, return path relatif.
     */
    private function saveSignature(string $dataUri, string $directory): ?string
    {
        try {
            // Format: "data:image/png;base64,xxxxxxx"
            if (!str_contains($dataUri, ',')) {
                return null;
            }

            $base64 = explode(',', $dataUri, 2)[1];
            $binary = base64_decode($base64);

            if (!$binary) {
                return null;
            }

            $filename  = $directory . '/' . uniqid('sig_', true) . '.png';
            $disk = \Illuminate\Support\Facades\Storage::disk('public');
            $disk->put($filename, $binary);

            return $filename;
        } catch (\Exception $e) {
            Log::error('Gagal simpan signature: ' . $e->getMessage());
            return null;
        }
    }
}
