<?php

namespace App\Support;

/**
 * Bentuk rule validasi "alasan" (reason) yang berulang di FopTaskController &
 * TaskController — dulu di-copy-paste 5x dengan angka max yang gak konsisten
 * (255/500/1000) tanpa acuan jelas. Max-length di sini sengaja gak disatuin
 * jadi 1 konstanta — tiap kolom DB punya kapasitas beda (mis. Task.cancel_reason
 * varchar(255) vs Task.reject_reason varchar(1000)), jadi max wajib dikirim
 * eksplisit per call-site biar validasi gak lebih longgar dari kolomnya
 * (kalau lolos validasi tapi kepanjangan buat DB, insert-nya error/truncate).
 */
final class ReasonValidationRule
{
    public static function required(int $maxLength): array
    {
        return ['required', 'string', "max:{$maxLength}"];
    }

    public static function requiredIf(string $statusField, string $statusValue, int $maxLength): array
    {
        return ['nullable', "required_if:{$statusField},{$statusValue}", 'string', "max:{$maxLength}"];
    }
}
