<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Models\City;
use App\Models\Customer;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\Village;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Fitur Foto KTP dihapus total (keputusan produk 2026-08-13): identitas cukup
 * diwakili NIK (`customers.identity_number`), foto fisiknya tidak disimpan.
 *
 * Penghapusannya menyentuh banyak lapis (form, request, kolom DB, enum dokumen),
 * jadi gampang "hidup lagi" sebagian — mis. seseorang menambah balik kolom foto
 * atau case enum KTP tanpa sadar. Test ini menjaga semua lapisnya sekaligus.
 */
class KtpPhotoRemovedFromSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_kolom_foto_ktp_tidak_ada_lagi_di_database(): void
    {
        $this->assertFalse(Schema::hasColumn('customers', 'foto_ktp'));
        $this->assertFalse(Schema::hasColumn('customer_addresses', 'ktp_photo'));
    }

    public function test_document_type_tidak_punya_case_ktp(): void
    {
        $this->assertNull(DocumentType::tryFrom('ktp'));
    }

    public function test_form_registrasi_tidak_menampilkan_upload_foto_ktp(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $response = $this->get(route('customers.create'));

        $response->assertOk();
        $response->assertDontSee('name="foto_ktp"', false);
        // NIK tetap ada — yang dihapus cuma fotonya.
        $response->assertSee('name="identity_number"', false);
    }

    public function test_registrasi_sukses_tanpa_foto_ktp(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $pop = Pop::firstOrCreate(['pop_code' => 'KTP1'], [
            'code' => 'POP-KTP-1',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Tanpa KTP',
            'type' => 'cabang',
            'status' => 'active',
        ]);
        $city = City::firstOrFail();
        $district = District::where('city_id', $city->id)->firstOrFail();
        $village = Village::where('district_id', $district->id)->firstOrFail();
        $package = InternetPackage::firstOrFail();

        $response = $this->post('/customers', [
            'full_name' => 'Pelanggan Tanpa KTP',
            'identity_number' => '3502181010900009',
            'gender' => 'Laki-laki',
            'primary_phone' => '081234567899',
            'registration_date' => '2026-08-13',
            'pop_id' => $pop->id,
            'address' => 'Jl. Tanpa KTP No. 1',
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'internet_package_id' => $package->id,
            'contract_period_months' => 12,
            'status' => 'registered',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customers', [
            'full_name' => 'Pelanggan Tanpa KTP',
            'identity_number' => '3502181010900009',
        ]);
    }

    public function test_upload_dokumen_bertipe_ktp_ditolak(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();
        Storage::fake('public');

        $pop = Pop::firstOrCreate(['pop_code' => 'KTP2'], [
            'code' => 'POP-KTP-2',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Tanpa KTP 2',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'customer_code' => 'CKTP-0001',
            'full_name' => 'Pelanggan Dokumen',
            'primary_phone' => '081234567898',
            'registration_date' => '2026-08-13',
            'status' => 'registered',
            'pop_id' => $pop->id,
        ]);

        $response = $this->from(route('customers.show', $customer->id))
            ->post(route('customers.documents.store', $customer->id), [
                'document_type' => 'ktp',
                'document_file' => UploadedFile::fake()->image('ktp.jpg'),
            ]);

        $response->assertSessionHasErrors(['document_type']);
        $this->assertDatabaseCount('customer_documents', 0);
    }
}
