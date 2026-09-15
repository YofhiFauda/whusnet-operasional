<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Daftar paket GLOBAL yang boleh dipilih role ber-
 * `roles.is_package_restricted = true` (Skema 1, 2026-09-12). Dikelola
 * Business Development lewat halaman Restriksi Paket — lihat
 * InternetPackage::availableFor().
 */
class RestrictedPackage extends Model
{
    protected $fillable = ['package_id'];

    public function package(): BelongsTo
    {
        return $this->belongsTo(InternetPackage::class, 'package_id');
    }
}
