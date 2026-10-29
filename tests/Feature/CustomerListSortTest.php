<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Pop;
use App\Support\CustomerListColumns;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Sort ASC/DESC lewat klik header List Pelanggan. Dilakukan di level query
 * (bukan di JS) supaya benar lintas halaman paginasi.
 */
class CustomerListSortTest extends TestCase
{
    use RefreshDatabase;

    private function pop(): Pop
    {
        return Pop::firstOrCreate(['pop_code' => 'C'], [
            'code' => 'C', 'name' => 'Jetis', 'type' => 'cabang', 'status' => 'active',
            'registration_prefix' => 'RQ', 'cid_prefix' => 'C',
        ]);
    }

    /** @return array<string, array{0: string, 1: string, 2: string, 3: string}> */
    public static function kolomSortableBerbasisKolomDb(): array
    {
        return [
            'nama' => ['nama', 'full_name', 'Aaa', 'Zzz'],
            'email' => ['email', 'email', 'a@x.test', 'z@x.test'],
            'telepon' => ['telepon', 'primary_phone', '0811111111', '0899999999'],
            'alamat' => ['alamat', 'address', 'Jl. Aaa', 'Jl. Zzz'],
        ];
    }

    #[DataProvider('kolomSortableBerbasisKolomDb')]
    public function test_sort_asc_dan_desc_berbasis_kolom_customers(string $key, string $column, string $low, string $high): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();
        $pop = $this->pop();

        Customer::factory()->create(['pop_id' => $pop->id, 'status' => 'active', 'full_name' => 'Tengah', $column => $low]);
        Customer::factory()->create(['pop_id' => $pop->id, 'status' => 'active', 'full_name' => 'Tengah2', $column => $high]);

        $asc = $this->get(route('customers.index', ['sort' => $key, 'dir' => 'asc']))->assertOk()->getContent();
        $desc = $this->get(route('customers.index', ['sort' => $key, 'dir' => 'desc']))->assertOk()->getContent();

        $this->assertLessThan(strpos($asc, $high), strpos($asc, $low));
        $this->assertLessThan(strpos($desc, $low), strpos($desc, $high));
    }

    public function test_sort_kolom_relasi_pop_desa_memakai_nama_pop(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();
        $popA = Pop::create(['pop_code' => 'A', 'code' => 'A', 'name' => 'AlfaCabang', 'type' => 'cabang', 'status' => 'active', 'registration_prefix' => 'RA', 'cid_prefix' => 'A']);
        $popZ = Pop::create(['pop_code' => 'Z', 'code' => 'Z', 'name' => 'ZuluCabang', 'type' => 'cabang', 'status' => 'active', 'registration_prefix' => 'RZ', 'cid_prefix' => 'Z']);
        Customer::factory()->create(['pop_id' => $popZ->id, 'status' => 'active', 'full_name' => 'Pelanggan Z']);
        Customer::factory()->create(['pop_id' => $popA->id, 'status' => 'active', 'full_name' => 'Pelanggan A']);

        $asc = $this->get(route('customers.index', ['sort' => 'pop_desa', 'dir' => 'asc']))->assertOk()->getContent();

        $this->assertLessThan(strpos($asc, 'Pelanggan Z'), strpos($asc, 'Pelanggan A'));
    }

    public function test_sort_dan_dir_tidak_valid_jatuh_ke_urutan_default_tanpa_error(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();
        $pop = $this->pop();
        Customer::factory()->create(['pop_id' => $pop->id, 'status' => 'active', 'full_name' => 'Satu']);

        $this->get(route('customers.index', ['sort' => 'id; drop table customers', 'dir' => 'sideways']))
            ->assertOk()->assertSee('Satu');
        $this->get(route('customers.index', ['sort' => 'nama', 'dir' => 'sideways']))
            ->assertOk()->assertSee('Satu');
    }

    public function test_setiap_kolom_sortable_katalog_bisa_diurutkan_tanpa_error_sql(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();
        $pop = $this->pop();
        Customer::factory()->create(['pop_id' => $pop->id, 'status' => 'active']);

        foreach (CustomerListColumns::sortableKeys() as $key) {
            foreach (['asc', 'desc'] as $dir) {
                $this->get(route('customers.index', ['sort' => $key, 'dir' => $dir]))
                    ->assertOk();
            }
        }
    }

    public function test_link_sort_header_mempertahankan_filter_dan_siklus_asc_desc_default(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $belumSort = $this->get(route('customers.index', ['search' => 'Budi']))->assertOk()->getContent();
        $this->assertStringContainsString('sort=nama', $belumSort);
        $this->assertStringContainsString('dir=asc', $belumSort);
        $this->assertStringContainsString('search=Budi', $belumSort);

        $sudahAsc = $this->get(route('customers.index', ['sort' => 'nama', 'dir' => 'asc']))->getContent();
        $this->assertStringContainsString('sort=nama&amp;dir=desc', $sudahAsc);

        $sudahDesc = $this->get(route('customers.index', ['sort' => 'nama', 'dir' => 'desc']))->getContent();
        $this->assertStringNotContainsString('sort=nama', $sudahDesc);
    }

    public function test_sort_via_subquery_tidak_menggandakan_atau_menghilangkan_baris(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();
        $pop = $this->pop();
        Customer::factory()->count(3)->create(['pop_id' => $pop->id, 'status' => 'active']);

        // Sort dikerjakan dengan subquery (bukan JOIN), jadi jumlah baris &
        // total paginasi harus sama persis dengan tanpa sort. applyUserScope()
        // tetap menempel di query dasar — sort hanya menambah ORDER BY.
        foreach (['pop_desa', 'paket', 'tagihan', 'jatuh_tempo', 'kolektor'] as $key) {
            $response = $this->get(route('customers.index', ['sort' => $key, 'dir' => 'asc']))->assertOk();
            $this->assertSame(3, $response->viewData('customers')->total(), $key);
        }
    }
}
