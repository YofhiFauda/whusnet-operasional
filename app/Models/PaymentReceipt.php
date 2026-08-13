<?php

namespace App\Models;

use App\Enums\ReceiptMatchMethod;
use App\Enums\ReceiptStatus;
use App\Traits\HasPopScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu berkas kwitansi. Dokumen, bukan uang — lihat migration untuk alasan
 * pemisahan sumbu kas vs dokumen.
 */
class PaymentReceipt extends Model
{
    use HasPopScope;

    protected $fillable = [
        'payment_id',
        'pop_id',
        'uploaded_by',
        'original_filename',
        'path',
        'mime_type',
        'size_bytes',
        'checksum',
        'status',
        'match_method',
        'detected_number',
        'attempts',
        'last_error',
        'matched_by',
        'matched_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReceiptStatus::class,
            'match_method' => ReceiptMatchMethod::class,
            'matched_at' => 'datetime',
            'size_bytes' => 'integer',
            'attempts' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function matcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }

    /**
     * Berkas yang boleh dilihat pada panel Kwitansi milik SATU kolektor.
     *
     * Dua kelompok dengan aturan akses berbeda, sengaja disatukan di sini
     * supaya daftar dan penghitung progresnya mustahil menyimpang:
     *
     *   - **Sudah tercocokkan** → disaring POP scope seperti query lain, dan
     *     dibatasi pembayaran yang ditagih kolektor ini.
     *   - **Belum tercocokkan** → belum punya `pop_id` sehingga tak bisa
     *     di-scope; penggantinya kepemilikan unggahan. Tanpa pembatas itu,
     *     panel membeberkan SELURUH kwitansi yatim di sistem — nama berkas,
     *     pengunggah, nomor yang terbaca — ke tiap admin, lintas cabang.
     *
     * Yang belum tercocokkan tetap ditampilkan: berkas tanpa pemilik adalah
     * pekerjaan yang tertinggal, bukan sesuatu yang boleh hilang diam-diam.
     *
     * @param  Builder<PaymentReceipt>  $query
     */
    public function scopeForWorksheet($query, User $collector, User $viewer)
    {
        return $query->where(function ($outer) use ($collector, $viewer) {
            $outer->where(function ($orphan) use ($viewer) {
                $orphan->whereNull('payment_id')
                    ->where('uploaded_by', $viewer->id);
            })->orWhere(function ($owned) use ($collector) {
                $owned->whereNotNull('payment_id')
                    ->applyUserScope()
                    ->whereHas('payment', fn ($q) => $q->where('collected_by', $collector->id));
            });
        });
    }
}
