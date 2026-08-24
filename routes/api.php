<?php

use App\Http\Controllers\Api\NetworkAssignmentController;
use App\Http\Controllers\Api\NetworkDeviceController;
use App\Http\Controllers\Api\PopDistribusiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Baru — Topologi Jaringan & Konfirmasi Assignment
|--------------------------------------------------------------------------
|
| docs/api/api-pop-distribusi/. Arah MASUK — Website B yang connect ke sini,
| kebalikan dari api-webhook-pemasangan (murni outbound, TIDAK PUNYA route
| di sini sama sekali — jangan dicampur). Tiga endpoint, dua token bearer
| (baca vs tulis, keputusan.md §5) — network-assignment & network-device
| BERBAGI token tulis yang sama (satu kelas risiko: sama-sama menulis data
| pelanggan), tapi rate limiter TERPISAH per endpoint (keputusan.md §19)
| supaya kegagalan beruntun di satu endpoint tidak memengaruhi yang lain.
|
*/

Route::prefix('v1')->group(function () {
    Route::middleware(['pop_distribusi.read', 'throttle:pop-distribusi-read'])
        ->get('/pop-distribusi', [PopDistribusiController::class, 'index'])
        ->name('api.pop-distribusi.index');

    Route::middleware(['network_assignment.write', 'throttle:network-assignment-write'])
        ->post('/installations/network-assignment', [NetworkAssignmentController::class, 'store'])
        ->name('api.installations.network-assignment');

    Route::middleware(['network_assignment.write', 'throttle:network-device-write'])
        ->post('/installations/network-device', [NetworkDeviceController::class, 'store'])
        ->name('api.installations.network-device');
});
