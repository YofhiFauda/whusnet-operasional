<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditLogs;
use App\Services\CustomerValidationService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'customer_code',
    'old_customer_id',
    'old_request_id',
    'cid',
    'full_name',
    'identity_number',
    'gender',
    'customer_type',
    'company_name',
    'npwp',
    'old_account_status',
    'email',
    'phone',
    'primary_phone',
    'alternative_phone',
    'registration_date',
    'data_completeness_status',
    'customer_status',
    'pop_id',
    'distribution_id',
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
    'created_by',
    'updated_by',
])]
class Customer extends Model
{
    use RecordsAuditLogs, HasFactory;

    protected string $auditModule = 'Data Pelanggan';

    protected array $auditHidden = [
        'foto_ktp',
        'foto_rumah',
        'foto_kontrak',
    ];

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
     * @return BelongsTo<Pop, $this>
     */
    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }

    /**
     * The distribution area this customer belongs to.
     * Used as part of CID generation: {pop.cid_prefix}{olt_number}{distribution.code}...
     *
     * @return BelongsTo<Distribution, $this>
     */
    public function distribution(): BelongsTo
    {
        return $this->belongsTo(Distribution::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return HasOne<CustomerAddress, $this>
     */
    public function customerAddress(): HasOne
    {
        return $this->hasOne(CustomerAddress::class);
    }

    /**
     * @return HasOne<CustomerService, $this>
     */
    public function customerService(): HasOne
    {
        return $this->hasOne(CustomerService::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<CustomerSurvey, $this>
     */
    public function surveys(): HasMany
    {
        return $this->hasMany(CustomerSurvey::class);
    }

    /**
     * @return HasMany<CustomerInstallation, $this>
     */
    public function installations(): HasMany
    {
        return $this->hasMany(CustomerInstallation::class);
    }

    /**
     * Get the latest survey.
     * 
     * @return HasOne<CustomerSurvey, $this>
     */
    public function latestSurvey(): HasOne
    {
        return $this->hasOne(CustomerSurvey::class)->latestOfMany();
    }

    /**
     * @return HasOne<CustomerInstallation, $this>
     */
    public function latestInstallation(): HasOne
    {
        return $this->hasOne(CustomerInstallation::class)->latestOfMany();
    }

    /**
     * @return HasOne<CustomerDevice, $this>
     */
    public function customerDevice(): HasOne
    {
        return $this->hasOne(CustomerDevice::class);
    }

    /**
     * @return HasOne<CustomerTechnicalDetail, $this>
     */
    public function customerTechnicalDetail(): HasOne
    {
        return $this->hasOne(CustomerTechnicalDetail::class);
    }

    /**
     * @return HasMany<CustomerDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(CustomerDocument::class);
    }

    /**
     * Auto-update data_completeness_status whenever the model is saved.
     * Uses CustomerValidationService to derive the correct status.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saved(function (Customer $customer) {
            $customer->recalculateCompleteness();
        });
    }

    /**
     * Recalculate completeness and update the database column quietly.
     */
    public function recalculateCompleteness(): void
    {
        $this->unsetRelation('customerService');
        $this->unsetRelation('customerAddress');

        /** @var CustomerValidationService $service */
        $service = app(CustomerValidationService::class);
        $result = $service->validate($this);
        $newStatus = $result['completeness_status'];

        if ($this->data_completeness_status !== $newStatus) {
            $this->updateQuietly(['data_completeness_status' => $newStatus]);
        }
    }

    /**
     * Calculate data completeness using CustomerValidationService.
     *
     * Returns:
     *   percentage        (int)    : 0–100 fill percentage
     *   filled_count      (int)    : number of filled fields
     *   total_count       (int)    : total fields evaluated
     *   missing_required  (array)  : [key => label] of unfilled required fields
     *   missing_optional  (array)  : [key => label] of unfilled optional fields
     *   is_ready_billing  (bool)   : true when all required fields are filled
     *   completeness_status (string): derived status string
     */
    public function dataCompleteness(): array
    {
        /** @var CustomerValidationService $service */
        $service = app(CustomerValidationService::class);
        return $service->validate($this);
    }

    /**
     * Compute the display identifier based on customer status.
     *
     * Sesuai spesifikasi-pop-distribusi-cid.md:
     * - REQ ID murni (RQ######) saat: pending/survey/pemasangan/installed/terminated
     * - CID lengkap saat: aktif + punya distribusi
     * - C00RQ###### saat: aktif tanpa distribusi
     *
     * @return string
     */
    public function getDisplayIdAttribute(): string
    {
        $pop = $this->pop;
        if (!$pop) {
            return $this->customer_code;
        }

        return $pop->resolveDisplayId($this);
    }

    /**
     * Get the label type for the display ID.
     * Returns 'REQ ID', 'CID', or 'ID' depending on the customer's status.
     *
     * @return string
     */
    public function getDisplayIdLabelAttribute(): string
    {
        $status = strtolower((string) ($this->status ?? ''));

        $bareStatuses = ['terminated', 'pending', 'waiting_survey', 'surveyed', 'waiting_installation', 'installed'];
        if (in_array($status, $bareStatuses, true)) {
            return 'REQ ID';
        }

        if (in_array($status, ['active', 'suspended'], true)) {
            if ($this->distribution_id && $this->cid) {
                return 'CID';
            }
            return 'ID';
        }

        return 'ID';
    }

    /**
     * Scope a query to only include customers from POPs accessible by the user.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \App\Models\User|null $user
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForUser($query, $user = null)
    {
        $user = $user ?? auth()->user();

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasFullAccess()) {
            return $query;
        }

        $assignedPopIds = $user->pops()->pluck('pops.id')->toArray();
        return $query->whereIn('pop_id', $assignedPopIds);
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
