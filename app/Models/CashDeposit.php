<?php

namespace App\Models;

use App\Enums\CashDepositChannel;
use App\Enums\CashDepositStatus;
use App\Support\Money;
use App\Traits\HasPopScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu sesi serah-terima uang admin → owner/bank.
 *
 * Angka-angkanya punya dua sifat yang tak boleh tertukar — sama persis dengan
 * [[CollectorDeposit]]:
 *   - `computedAmount()` — TURUNAN dari sumber yang tertaut (setoran kolektor
 *     + pembayaran manual tunai). Tak pernah disimpan.
 *   - `declared_amount` / `difference` — CATATAN HASIL PEMERIKSAAN, disimpan.
 *
 * docs/plan/kolektor/analisa-setoran-kas-admin.md §4.
 */
class CashDeposit extends Model
{
    use HasPopScope;

    protected $fillable = [
        'deposit_number',
        'depositor_id',
        'pop_id',
        'status',
        'declared_amount',
        'difference',
        'note',
        'channel',
        'bank_name',
        'account_number',
        'reference_no',
        'proof_path',
        'submitted_at',
        'verified_by',
        'verified_at',
        'written_off_by',
        'written_off_at',
        'write_off_reason',
        'idempotency_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CashDepositStatus::class,
            'channel' => CashDepositChannel::class,
            'declared_amount' => 'decimal:2',
            'difference' => 'decimal:2',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'written_off_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function depositor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'depositor_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * @return BelongsTo<Pop, $this>
     */
    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }

    /**
     * Setoran kolektor yang uangnya ikut disetorkan di sesi ini.
     *
     * @return HasMany<CollectorDeposit, $this>
     */
    public function collectorDeposits(): HasMany
    {
        return $this->hasMany(CollectorDeposit::class, 'cash_deposit_id');
    }

    /**
     * Pembayaran manual di kantor yang ikut disetorkan di sesi ini.
     *
     * @return HasMany<Payment, $this>
     */
    public function manualPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'cash_deposit_id');
    }

    /**
     * Sentinel titik nol disembunyikan dari SEMUA daftar & laporan.
     *
     * Scope-nya di model, bukan disalin ke tiap query pemanggil: baris ini
     * bukan setoran, dan satu tempat yang lupa menyaringnya sudah cukup untuk
     * menampilkan "setoran Rp0" tanpa penyetor di layar Owner.
     *
     * @param  Builder<CashDeposit>  $query
     * @return Builder<CashDeposit>
     */
    public function scopeRealDeposits(Builder $query): Builder
    {
        return $query->where('status', '!=', CashDepositStatus::SALDO_AWAL->value);
    }

    /**
     * Σ uang yang seharusnya ada di sesi ini menurut catatan sistem — DIHITUNG,
     * tidak disimpan.
     *
     * Dua suku, sesuai dua sumber kas admin (§2):
     *   - setoran kolektor → uang fisik yang benar-benar diterima kantor;
     *   - pembayaran manual tunai → nominalnya apa adanya.
     */
    public function computedAmount(): float
    {
        $dariKolektor = Money::sum(
            $this->collectorDeposits()
                ->get(['declared_amount', 'difference'])
                ->map(fn (CollectorDeposit $deposit) => $deposit->cashReceivedByOffice())
        );

        $dariManual = Money::of($this->manualPayments()->sum('amount'));

        return Money::add($dariKolektor, $dariManual);
    }

    /**
     * Jumlah baris sumber — dipakai pesan notifikasi & ringkasan kartu.
     */
    public function sourceCount(): int
    {
        return $this->collectorDeposits()->count() + $this->manualPayments()->count();
    }
}
