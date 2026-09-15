<?php

namespace App\Enums;

enum DocumentType: string
{
    case RUMAH = 'rumah';
    case KONTRAK = 'kontrak';
    case SURVEY = 'survey';
    case PEMASANGAN = 'pemasangan';
    // FAB = Formulir Akan Berlangganan Bisnis — wajib diunggah saat Registrasi
    // kalau paket yang dipilih masuk daftar RestrictedPackage (paket yang
    // divalidasi/diverifikasi Business Development, Skema 1). Lihat
    // CustomerRegistrationRequest::rules() & CustomerController::store().
    case FAB = 'fab';

    public function label(): string
    {
        return match ($this) {
            self::RUMAH => 'Foto Rumah',
            self::KONTRAK => 'Dokumen Kontrak',
            self::SURVEY => 'Foto Survey',
            self::PEMASANGAN => 'Foto Pemasangan',
            self::FAB => 'Formulir Akan Berlangganan Bisnis (FAB)',
        };
    }
}
