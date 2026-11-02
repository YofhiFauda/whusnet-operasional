<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\User;
use App\Models\UserTablePreference;
use App\Support\CustomerListColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Kolom List Pelanggan per user: baca/simpan/reset preferensi dan terapkan sort
 * header. Semua key divalidasi terhadap katalog {@see CustomerListColumns}.
 */
class CustomerListColumnService
{
    /**
     * Kolom yang TAMPIL untuk user, berurutan, keyed by key katalog.
     * Kunci usang di preferensi (kolom yang sudah dihapus dari katalog)
     * dibuang saat baca; kalau tak ada yang tersisa, jatuh ke default supaya
     * tabel tidak pernah kosong.
     *
     * @return array<string, array<string, mixed>>
     */
    public function columnsFor(User $user): array
    {
        $catalog = CustomerListColumns::all();

        $stored = UserTablePreference::query()
            ->where('user_id', $user->id)
            ->where('table_key', CustomerListColumns::TABLE_KEY)
            ->value('columns');

        $keys = array_values(array_filter(
            (array) $stored,
            fn ($key) => is_string($key) && isset($catalog[$key])
        ));

        if ($keys === []) {
            $keys = CustomerListColumns::defaultKeys();
        }

        return collect($keys)->mapWithKeys(fn (string $key) => [$key => $catalog[$key]])->all();
    }

    /**
     * Kolom default (dipakai grup Survey/Verifikasi yang tidak ikut
     * preferensi).
     *
     * @return array<string, array<string, mixed>>
     */
    public function defaultColumns(): array
    {
        $catalog = CustomerListColumns::all();

        return collect(CustomerListColumns::defaultKeys())
            ->mapWithKeys(fn (string $key) => [$key => $catalog[$key]])
            ->all();
    }

    /**
     * @param  list<string>  $keys  key kolom tampil, berurutan
     */
    public function save(User $user, array $keys): void
    {
        UserTablePreference::updateOrCreate(
            ['user_id' => $user->id, 'table_key' => CustomerListColumns::TABLE_KEY],
            ['columns' => array_values($keys)],
        );
    }

    public function reset(User $user): void
    {
        UserTablePreference::query()
            ->where('user_id', $user->id)
            ->where('table_key', CustomerListColumns::TABLE_KEY)
            ->delete();
    }

    public function isValidSort(?string $sort): bool
    {
        return $sort !== null && in_array($sort, CustomerListColumns::sortableKeys(), true);
    }

    /**
     * Terapkan ORDER BY dari header yang diklik. Return false (query tak
     * disentuh) bila `sort` bukan kolom katalog, supaya pemanggil jatuh ke
     * urutan default. `dir` selain `desc` dianggap `asc`.
     *
     * @param  Builder<Customer>  $query
     */
    public function applySort(Builder $query, ?string $sort, ?string $dir): bool
    {
        if (! $this->isValidSort($sort)) {
            return false;
        }

        $spec = CustomerListColumns::all()[$sort]['sort'];
        $direction = strtolower((string) $dir) === 'desc' ? 'desc' : 'asc';

        // Subquery korelasi, bukan JOIN: JOIN ke tabel master akan menyeret
        // kolom `name`/`id` mereka ke select pelanggan (ambigu) dan bisa
        // menggandakan baris. Nama tabel/kolom di bawah hanya datang dari
        // katalog statis, tidak pernah dari input klien.
        $expression = match ($spec['type']) {
            'column' => 'customers.'.$spec['column'],
            'relation' => DB::table($spec['table'])
                ->select($spec['column'])
                ->whereColumn($spec['table'].'.id', 'customers.'.$spec['fk'])
                ->limit(1),
            'invoice' => DB::table('invoices')
                ->select($spec['column'])
                ->whereColumn('invoices.customer_id', 'customers.id')
                ->orderByDesc('invoices.id')
                ->limit(1),
        };

        // customers.id sebagai pemutus seri: tanpa ini baris dengan nilai sama
        // bisa berpindah antar halaman paginasi.
        $query->orderBy($expression, $direction)->orderBy('customers.id');

        return true;
    }
}
