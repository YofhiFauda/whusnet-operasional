<?php

namespace Tests\Feature;

use App\Http\Controllers\CustomerController;
use App\Models\Person;
use App\Models\Pop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Regresi bug backfill lintas cabang.
 *
 * Dua dump legacy terpisah (jetis_db & sand_db) sama-sama memakai IDCABANG=1 dan
 * IDPENGGUNA mulai dari PE000001. Versi awal backfill me-namespace legacy_key
 * dengan `old_branch_id` ("1:PE000042"), sehingga 42 pelanggan Sandya nyantol ke
 * person Jetis — satu person dipakai dua orang berbeda.
 *
 * legacy_key wajib di-namespace dengan CABANG POP (unik per instalasi), bukan
 * old_branch_id. Test ini menjaga IDPENGGUNA yang sama di dua cabang berbeda
 * TETAP menghasilkan dua person berbeda.
 */
class PersonBackfillCrossBranchCollisionTest extends TestCase
{
    use RefreshDatabase;

    private function cabang(string $code): Pop
    {
        return Pop::create([
            'code' => $code,
            'pop_code' => $code,
            'registration_prefix' => 'C',
            'cid_prefix' => $code,
            'name' => "Cabang {$code}",
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function resolvePerson(?Pop $pop, string $legacyId): Person
    {
        // resolveLegacyPerson private — panggil lewat refleksi karena logika
        // namespace-nya justru inti yang sedang dijaga.
        $m = new ReflectionMethod(CustomerController::class, 'resolveLegacyPerson');
        $m->setAccessible(true);

        return $m->invoke(app(CustomerController::class), $pop, $legacyId);
    }

    public function test_idpengguna_sama_di_dua_cabang_menghasilkan_person_berbeda(): void
    {
        $jetis = $this->cabang('CJET');
        $sandya = $this->cabang('CSAN');

        $p1 = $this->resolvePerson($jetis, 'PE000042');
        $p2 = $this->resolvePerson($sandya, 'PE000042');

        $this->assertNotEquals($p1->id, $p2->id, 'PE000042 di dua cabang berbeda harus jadi dua person.');
        $this->assertSame(2, Person::whereNotNull('legacy_key')->count());
    }

    public function test_idpengguna_sama_di_cabang_sama_idempoten_satu_person(): void
    {
        $jetis = $this->cabang('CJET');

        $a = $this->resolvePerson($jetis, 'PE000042');
        $b = $this->resolvePerson($jetis, 'PE000042'); // import ulang

        $this->assertSame($a->id, $b->id, 'IDPENGGUNA sama di cabang sama harus memungut person yang sama (idempoten).');
        $this->assertSame(1, Person::whereNotNull('legacy_key')->count());
    }

    public function test_mini_pop_mewarisi_namespace_cabang_induknya(): void
    {
        $jetis = $this->cabang('CJET');
        $miniPop = Pop::create([
            'code' => 'CJET-M1',
            'pop_code' => 'CJET1',
            'name' => 'Mini Jetis 1',
            'type' => 'mini',
            'status' => 'active',
            'parent_id' => $jetis->id,
        ]);

        // Pelanggan lewat mini-pop dan lewat cabang langsung, IDPENGGUNA sama →
        // person sama (mini-pop naik ke cabang induk untuk namespace).
        $viaMini = $this->resolvePerson($miniPop, 'PE000042');
        $viaCabang = $this->resolvePerson($jetis, 'PE000042');

        $this->assertSame($viaMini->id, $viaCabang->id);
    }
}
