<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_code',
    'full_name',
    'identity_number',
    'gender',
    'email',
    'phone',
    'registration_date',
    'status',
    'address',
    'latitude',
    'longitude',
    'city_id',
    'district_id',
    'village_id',
    'internet_package_id',
    'contract_period_months',
    'discount_amount',
    'tax_percent',
    'sales_code',
    'agent_code',
    'referral_customer_code',
    'ont_sn',
    'ip_address',
    'odp_code',
    'olt_code',
    'vlan_id',
    'foto_ktp',
    'foto_rumah',
    'foto_kontrak',
])]
class Customer extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'registration_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * @return BelongsTo<District, $this>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /**
     * @return BelongsTo<Village, $this>
     */
    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    /**
     * @return BelongsTo<InternetPackage, $this>
     */
    public function internetPackage(): BelongsTo
    {
        return $this->belongsTo(InternetPackage::class, 'internet_package_id');
    }

    /**
     * @return BelongsTo<SubscriptionStatus, $this>
     */
    public function subscriptionStatus(): BelongsTo
    {
        return $this->belongsTo(SubscriptionStatus::class, 'status', 'code');
    }

    /**
     * Calculate data completeness percentage.
     * Checks how many of the 25 fields are filled.
     */
    public function dataCompleteness(): array
    {
        $fields = [
            'full_name', 'identity_number', 'gender', 'phone', 'email',
            'registration_date', 'address', 'city_id', 'district_id', 'village_id',
            'latitude', 'longitude', 'foto_ktp', 'foto_rumah', 'foto_kontrak',
            'internet_package_id', 'contract_period_months', 'discount_amount', 'tax_percent',
            'sales_code', 'agent_code', 'referral_customer_code',
            'status', 'ont_sn', 'ip_address', 'odp_code', 'olt_code', 'vlan_id'
        ];

        $requiredFields = [
            'full_name', 'identity_number', 'gender', 'phone', 'registration_date',
            'address', 'city_id', 'district_id', 'village_id', 'internet_package_id',
            'contract_period_months', 'discount_amount', 'tax_percent', 'status'
        ];

        $filledCount = 0;
        $totalCount = count($fields);
        $missingRequired = [];
        $missingOptional = [];

        foreach ($fields as $field) {
            $val = $this->{$field};
            $isFilled = !is_null($val) && trim((string)$val) !== '';
            if ($isFilled) {
                $filledCount++;
            } else {
                if (in_array($field, $requiredFields)) {
                    $missingRequired[] = $field;
                } else {
                    $missingOptional[] = $field;
                }
            }
        }

        $percentage = round(($filledCount / $totalCount) * 100);

        return [
            'percentage' => (int)$percentage,
            'filled_count' => $filledCount,
            'total_count' => $totalCount,
            'missing_required' => $missingRequired,
            'missing_optional' => $missingOptional,
            'is_complete' => count($missingRequired) === 0 && count($missingOptional) === 0,
            'has_warnings' => count($missingRequired) === 0 && count($missingOptional) > 0,
        ];
    }

    /**
     * Determine workflow stages progress.
     */
    public function workflowProgress(): array
    {
        $status = strtolower($this->status);
        
        $stages = [
            'registrasi' => ['label' => 'R', 'name' => 'Registrasi', 'status' => 'completed', 'color' => 'bg-green-500'],
            'survey' => ['label' => 'S', 'name' => 'Survey', 'status' => 'pending', 'color' => 'bg-slate-200 text-slate-400'],
            'pemasangan' => ['label' => 'P', 'name' => 'Pemasangan', 'status' => 'pending', 'color' => 'bg-slate-200 text-slate-400'],
            'uji' => ['label' => 'U', 'name' => 'Uji Layanan', 'status' => 'pending', 'color' => 'bg-slate-200 text-slate-400'],
            'aktivasi' => ['label' => 'A', 'name' => 'Aktivasi', 'status' => 'pending', 'color' => 'bg-slate-200 text-slate-400']
        ];

        $completedStatuses = ['active', 'suspended', 'terminated'];

        // Survey
        if (in_array($status, ['surveyed', 'waiting_installation', 'installed', ...$completedStatuses])) {
            $stages['survey'] = ['label' => 'S', 'name' => 'Survey', 'status' => 'completed', 'color' => 'bg-green-500 text-white'];
        } elseif ($status === 'waiting_survey') {
            $stages['survey'] = ['label' => 'S', 'name' => 'Survey', 'status' => 'in_progress', 'color' => 'bg-amber-500 text-white animate-pulse'];
        }
        
        // Pemasangan (Instalasi) & Uji Layanan
        if (in_array($status, ['installed', ...$completedStatuses])) {
            $stages['pemasangan'] = ['label' => 'P', 'name' => 'Pemasangan', 'status' => 'completed', 'color' => 'bg-green-500 text-white'];
            $stages['uji'] = ['label' => 'U', 'name' => 'Uji Layanan', 'status' => 'completed', 'color' => 'bg-green-500 text-white'];
        } elseif ($status === 'waiting_installation') {
            $stages['pemasangan'] = ['label' => 'P', 'name' => 'Pemasangan', 'status' => 'in_progress', 'color' => 'bg-amber-500 text-white animate-pulse'];
        }

        // Aktivasi
        if (in_array($status, ['active', 'suspended'])) {
            $stages['aktivasi'] = ['label' => 'A', 'name' => 'Aktivasi', 'status' => 'completed', 'color' => $status === 'suspended' ? 'bg-amber-500 text-white' : 'bg-green-500 text-white'];
        } elseif ($status === 'terminated') {
            $stages['aktivasi'] = ['label' => 'A', 'name' => 'Aktivasi', 'status' => 'terminated', 'color' => 'bg-red-500 text-white'];
        } elseif ($status === 'installed') {
            $stages['aktivasi'] = ['label' => 'A', 'name' => 'Aktivasi', 'status' => 'in_progress', 'color' => 'bg-amber-500 text-white animate-pulse'];
        }

        return $stages;
    }
}
