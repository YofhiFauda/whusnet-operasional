<?php

namespace App\Support;

use App\Enums\TrackingType;
use Illuminate\Http\Request;

/**
 * Filter papan Kelola Stok, dinormalisasi SEKALI di sini supaya controller,
 * service, dan view tidak masing-masing menafsirkan query string mentah.
 *
 * `popId` datang sudah di-resolve (dibatasi POP scope) oleh pemanggil — kelas
 * ini sengaja tidak tahu soal scope, jadi tidak boleh dipakai menggantikan
 * pengecekan akses. Nilai asing (kategori non-numerik, tracking di luar enum)
 * dibuang jadi null, bukan 422, karena ini filter tampilan.
 */
final readonly class BoardFilters
{
    public function __construct(
        public ?int $popId,
        public ?int $categoryId,
        public ?string $tracking,
        public string $search,
        public bool $lowOnly,
    ) {}

    public static function fromRequest(Request $request, ?int $resolvedPopId): self
    {
        $category = $request->query('category_id');
        $tracking = $request->query('tracking');
        // `?search[]=x` datang sebagai array; (string) array melempar error.
        $search = $request->query('search', '');

        return new self(
            popId: $resolvedPopId,
            categoryId: is_scalar($category) && ctype_digit((string) $category) ? (int) $category : null,
            tracking: is_string($tracking) && in_array($tracking, array_column(TrackingType::cases(), 'value'), true)
                ? $tracking
                : null,
            search: LikeSearch::sanitize(is_string($search) ? $search : ''),
            lowOnly: $request->boolean('low_stock_only'),
        );
    }

    /** Gudang yang dipilih tidak dihitung: itu pemilihan konteks, bukan penyaringan baris. */
    public function isFiltering(): bool
    {
        return $this->categoryId !== null
            || $this->tracking !== null
            || $this->search !== ''
            || $this->lowOnly;
    }
}
