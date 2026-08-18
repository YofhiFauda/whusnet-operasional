<?php

namespace App\Models;

use App\Enums\DepositStatus;
use App\Enums\PaymentStatus;
use App\Support\Money;
use App\Traits\HasPopScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu sesi serah-terima uang fisik kolektor → admin.
 *
 * Angka-angka di sini punya dua sifat berbeda dan jangan tertukar:
 *   - `computedAmount()` — TURUNAN, dihitung dari payment yang tertaut. Tak
 *     pernah disimpan.
 *   - `declared_amount` / `difference` — CATATAN HASIL VERIFIKASI, disimpan.
 *     Aman beku karena payment tak bisa keluar dari setoran terverifikasi.
 *
 * docs/plan/kolektor/analisa-alur-kolektor-2.0.md §11.
 */
class CollectorDeposit extends Model
{
    use HasPopScope;

    protected $fillable = [
        'deposit_number',
        'collector_id',
        'pop_id',
        'status',
        'declared_amount',
        'difference',
        'settlement_amount',
        'settled_amount',
        'settles_deposit_id',
        'cash_deposit_id',
        'note',
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
            'status' => DepositStatus::class,
            'declared_amount' => 'decimal:2',
            'difference' => 'decimal:2',
            'settlement_amount' => 'decimal:2',
            'settled_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'written_off_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    /**
     * @return BelongsTo<Pop, $this>
     */
    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class, 'pop_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'collector_deposit_id');
    }

    /**
     * Setoran bermasalah yang dilunasi oleh setoran ini.
     *
     * @return BelongsTo<CollectorDeposit, $this>
     */
    public function settlesDeposit(): BelongsTo
    {
        return $this->belongsTo(self::class, 'settles_deposit_id');
    }

    /**
     * Σ payment VALID yang tertaut setoran ini — DIHITUNG, tidak disimpan.
     * Payment yang ditolak tak boleh ikut: uangnya memang tak pernah sah.
     */
    public function computedAmount(): float
    {
        return Money::of($this->payments()
            ->where('payment_status', PaymentStatus::VALID->value)
            ->sum('amount'));
    }

    /**
     * Setoran kas admin yang menyerap uang setoran ini — `null` selama uangnya
     * masih ada di tangan admin.
     *
     * @return BelongsTo<CashDeposit, $this>
     */
    public function cashDeposit(): BelongsTo
    {
        return $this->belongsTo(CashDeposit::class, 'cash_deposit_id');
    }

    /**
     * Uang yang benar-benar mendarat di kas kantor dari setoran ini.
     *
     * Yang dipakai `declared_amount` (uang fisik yang dihitung di meja), BUKAN
     * `computedAmount()`: yang berpindah ke brankas adalah lembaran uangnya,
     * bukan angka yang tercatat sistem. Kurang setor otomatis terpantul di sini
     * tanpa penyesuaian tambahan.
     *
     * Selisih POSITIF dikurangkan lagi karena kelebihan setor dikembalikan
     * fisik ke kolektor saat itu juga (§B-8 no. 6) — uang itu tak pernah
     * menjadi kas kantor. Kalau tidak dikurangkan, admin diminta menyetorkan
     * uang yang sudah dia kembalikan.
     *
     * docs/plan/kolektor/analisa-setoran-kas-admin.md §2.
     */
    public function cashReceivedByOffice(): float
    {
        $lebih = Money::atLeastZero($this->difference);

        return Money::atLeastZero(Money::sub($this->declared_amount, $lebih));
    }

    /**
     * Sisa kewajiban kolektor dari setoran ini.
     *
     * Cuma bermakna untuk `difference` NEGATIF (uang fisik kurang dari yang
     * tercatat). `difference` positif = lebih setor — itu bukan utang
     * kolektor, dan sengaja tidak diperlakukan sebagai piutang balik: uang
     * lebih dikembalikan fisik saat itu juga, sama seperti kembalian ke
     * pelanggan (§B-8 no. 6 yang masih berlaku).
     */
    public function outstandingShortfall(): float
    {
        if ($this->status !== DepositStatus::SELISIH) {
            return 0.0;
        }

        // Sisa kewajiban ini yang menentukan setoran masih muncul di daftar
        // "Kurang Setor" atau tidak. Galat float di sini berarti kolektor
        // dikejar sisa Rp0,000001 yang mustahil dilunasi.
        $shortfall = Money::atLeastZero(Money::sub(0, $this->difference));

        return Money::atLeastZero(Money::sub($shortfall, $this->settled_amount));
    }
}
