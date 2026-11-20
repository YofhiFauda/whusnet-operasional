<?php

namespace App\Models;

use App\Enums\IsolationAdminDecision;
use App\Enums\IsolationCaseStatus;
use App\Enums\IsolationTriggerType;
use App\Traits\HasPopScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu kasus = satu siklus isolir untuk satu layanan, dari pertama kali
 * memenuhi syarat sampai selesai/dibatalkan
 * (docs/plan/Rancangan_Monitoring_Isolir_Pelanggan.md §12.1). SATU-SATUNYA
 * sumber kebenaran lifecycle isolir — lihat `IsolationCaseService`.
 */
#[Fillable([
    'case_number', 'customer_id', 'service_id', 'pop_id', 'status', 'trigger_type',
    'billing_period', 'outstanding_amount_at_creation', 'current_outstanding_amount',
    'detected_at', 'isolation_started_at', 'suspended_at', 'paid_at',
    'restoration_started_at', 'restored_at', 'closed_at', 'cancelled_at',
    'cancellation_reason', 'active_slot',
    'admin_decision', 'admin_decision_note', 'admin_decision_until', 'admin_decided_by', 'admin_decided_at',
])]
class IsolationCase extends Model
{
    use HasFactory, HasPopScope;

    protected function casts(): array
    {
        return [
            'status' => IsolationCaseStatus::class,
            'trigger_type' => IsolationTriggerType::class,
            'outstanding_amount_at_creation' => 'decimal:2',
            'current_outstanding_amount' => 'decimal:2',
            'detected_at' => 'datetime',
            'isolation_started_at' => 'datetime',
            'suspended_at' => 'datetime',
            'paid_at' => 'datetime',
            'restoration_started_at' => 'datetime',
            'restored_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'admin_decision' => IsolationAdminDecision::class,
            'admin_decision_until' => 'date',
            'admin_decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<CustomerService, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(CustomerService::class, 'service_id');
    }

    /**
     * @return BelongsTo<Pop, $this>
     */
    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }

    /**
     * @return HasMany<IsolationCaseInvoice, $this>
     */
    public function caseInvoices(): HasMany
    {
        return $this->hasMany(IsolationCaseInvoice::class);
    }

    /**
     * @return HasMany<IsolationEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(IsolationEvent::class)->orderBy('occurred_at', 'desc');
    }

    /**
     * @return HasMany<IsolationAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(IsolationAction::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function adminDecidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_decided_by');
    }

    /**
     * Isi/kosongkan `active_slot` sesuai status (guard BR-03, lihat
     * migration `create_isolation_cases_table`). WAJIB dipanggil setiap kali
     * `status` berubah — dipanggil dari `IsolationCaseService`, bukan
     * ditebak ulang di tempat lain.
     */
    public function syncActiveSlot(): void
    {
        $this->active_slot = $this->status->isActive() ? 1 : null;
    }
}
