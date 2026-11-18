<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DeviceRetrievalLog;
use App\Models\Task;
use App\Models\TaskDeviceRetrieval;
use App\Support\DeviceRetrievalPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Penyaji foto kondisi alat (disk privat). Foto ini dulu tampil di 6 halaman
 * lewat URL publik `/storage/device-retrieval/...`; sekarang semua lewat sini.
 *
 * Boleh lihat kalau:
 *  - boleh melihat task-nya (TaskPolicy::view — teknisi pelaksana, atau
 *    pemegang task.view.all dalam POP scope), ATAU
 *  - memegang salah satu permission halaman yang menampilkan foto ini
 *    DAN pelanggannya dalam POP scope user.
 *
 * docs/plan/warehouse/analisa-review-penarikan-deac.md §3.6
 */
class DeviceRetrievalPhotoController extends Controller
{
    /**
     * Permission halaman-halaman yang menampilkan foto ini: Review Penarikan,
     * Detail Pelanggan (tab Perangkat), Riwayat Task FOP, Riwayat Pengambilan
     * Alat (gudang), Terima Retur Modem.
     */
    private const PAGE_PERMISSIONS = [
        'device_retrieval_review.view',
        'customers.detail.view',
        'fop_tasks.view',
        'warehouse.view',
        'warehouse_reassign.create',
    ];

    public function retrieval(Request $request, TaskDeviceRetrieval $retrieval): StreamedResponse
    {
        $task = $retrieval->task;
        $this->authorizePhoto($request, $task?->customer, $task);

        // Foto percobaan yang ditolak dihapus setelah 30 hari (retensi K5).
        abort_if($retrieval->photo_purged_at !== null, 404);

        return $this->respond($retrieval->condition_photo);
    }

    public function log(Request $request, DeviceRetrievalLog $log): StreamedResponse
    {
        $this->authorizePhoto($request, $log->customer, $log->task);

        return $this->respond($log->condition_photo);
    }

    private function authorizePhoto(Request $request, ?Customer $customer, ?Task $task): void
    {
        $user = $request->user();

        if ($task && Gate::forUser($user)->allows('view', $task)) {
            return;
        }

        $hasPagePermission = collect(self::PAGE_PERMISSIONS)->contains(fn (string $code) => $user->hasPermission($code));

        abort_unless($customer && $hasPagePermission, 403);
        abort_unless(Customer::applyUserScope($user)->whereKey($customer->id)->exists(), 403);
    }

    private function respond(?string $path): StreamedResponse
    {
        $disk = DeviceRetrievalPhoto::diskFor($path);
        abort_unless($disk !== null, 404);

        return Storage::disk($disk)->response($path);
    }
}
