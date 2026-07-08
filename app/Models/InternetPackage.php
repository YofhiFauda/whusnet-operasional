<?php

namespace App\Models;

use App\Enums\TaskType;
use App\Models\Concerns\RecordsAuditLogs;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'package_code',
    'old_package_id',
    'name',
    'category',
    'package_group',
    'bandwidth_label',
    'download_speed_mbps',
    'upload_speed_mbps',
    'contention_ratio',
    'monthly_price',
    'ppn',
    'discount_default',
    'total_price',
    'modem',
    'features',
    'max_users',
    'ip_address_type',
    'contract_period_months',
    'installation_fee',
    'installation_fee_label',
    'profile',
    'technical_profile',
    'terms',
    'description',
    'is_active',
])]
class InternetPackage extends Model
{
    use RecordsAuditLogs;

    protected string $auditModule = 'Master Paket';

    public const CATEGORIES = [
        'Paket Home Broadband' => 'Paket Home Broadband',
        'Paket Bisnis Broadband' => 'Paket Bisnis Broadband',
        'Paket Bisnis UKM' => 'Paket Bisnis UKM',
        'Paket Bisnis Dedicated' => 'Paket Bisnis Dedicated',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'download_speed_mbps' => 'decimal:2',
            'upload_speed_mbps' => 'decimal:2',
            'contention_ratio' => 'integer',
            'monthly_price' => 'decimal:2',
            'ppn' => 'decimal:2',
            'discount_default' => 'decimal:2',
            'total_price' => 'decimal:2',
            'features' => 'array',
            'max_users' => 'integer',
            'contract_period_months' => 'integer',
            'installation_fee' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function slaSettings(): HasMany
    {
        return $this->hasMany(PackageSlaSetting::class);
    }

    /**
     * Batas waktu wajib mulai ditangani (Master Timeline), dalam jam, untuk
     * jenis tiket tertentu. Fallback ke TaskType::defaultHandlingSlaHours()
     * kalau paket ini belum diatur admin di Master Timeline SLA.
     */
    public function getHandlingSla(TaskType $type): int
    {
        $setting = $this->relationLoaded('slaSettings')
            ? $this->slaSettings->firstWhere('task_type', $type)
            : $this->slaSettings()->where('task_type', $type->value)->first();

        if ($setting && $setting->is_active) {
            return $setting->sla_hours;
        }

        return $type->defaultHandlingSlaHours();
    }

    public function calculateTotalPrice(): float
    {
        $price = (float) $this->monthly_price;

        if ((float) $this->discount_default > 0) {
            $price -= $price * ((float) $this->discount_default / 100);
        }

        if ((float) $this->ppn > 0) {
            $price += $price * ((float) $this->ppn / 100);
        }

        return round($price, 2);
    }

    public function getMonthlyPriceFormattedAttribute(): string
    {
        return 'Rp ' . number_format((float) $this->monthly_price, 0, ',', '.');
    }

    public function getTotalPriceFormattedAttribute(): string
    {
        return 'Rp ' . number_format((float) $this->total_price, 0, ',', '.');
    }

    public function getInstallationFeeFormattedAttribute(): string
    {
        if ($this->installation_fee === null) {
            return $this->installation_fee_label ?? '-';
        }

        return 'Rp ' . number_format((float) $this->installation_fee, 0, ',', '.');
    }
}
