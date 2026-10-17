<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilempar `AdditionalDeviceCustodyService::assertSufficientForTask()` kalau
 * tim yang mau dijadwalkan ke Task Pemasangan belum pegang custody perangkat
 * tambahan (AP/STB) yang cukup buat paket pelanggannya (ADHOC-190).
 *
 * Kelas tersendiri, BUKAN `\RuntimeException` polos: controller assign FOP
 * menangkap guard ini di dalam blok transaction yang juga bisa melempar
 * `ModelNotFoundException` (turunan `RuntimeException`) — kalau yang
 * ditangkap `RuntimeException` generik, 404 asli malah tampil sebagai pesan
 * "custody kurang" di form. Tangkap kelas ini saja.
 */
class InsufficientExtraDeviceCustodyException extends RuntimeException {}
