<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Pop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 5.3 — pencarian pelanggan diarahkan per bentuk input (prefix untuk
 * kode/nomor, substring untuk nama) menggantikan LIKE '%x%' di 8 kolom.
 *
 * Mengunci KONTRAK BARU (perubahan perilaku yang disetujui): pencarian kode
 * pakai PREFIX (potongan tengah kode tak lagi cocok), pencarian nama tetap
 * substring, dan query berdigit tidak mencocokkan nama.
 */
class CustomerSearchPrefixRoutingTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pop = Pop::firstOrCreate(['pop_code' => 'C'], [
            'code' => 'C', 'name' => 'Jetis', 'type' => 'cabang', 'status' => 'active',
            'registration_prefix' => 'RQ', 'cid_prefix' => 'C',
        ]);
        $this->loginAsAdmin();
    }

    private function customer(array $attr): Customer
    {
        // status 'active' eksplisit — factory default 'aktif' (nilai lawas) tidak
        // dipetakan status_group mana pun sehingga baris lenyap dari list.
        return Customer::factory()->create(array_merge([
            'pop_id' => $this->pop->id,
            'status' => 'active',
        ], $attr));
    }

    public function test_kode_dicari_dengan_prefix_bukan_substring_tengah(): void
    {
        $c = $this->customer(['customer_code' => 'C1X4ARQ000631', 'full_name' => 'Masudah']);

        // Prefix cocok.
        $this->get('/customers?search=C1X4A')->assertSee('Masudah');
        // Potongan TENGAH tidak lagi cocok (kontrak baru prefix).
        $this->get('/customers?search=RQ000631')->assertDontSee('Masudah');
    }

    public function test_nama_tetap_substring_tengah(): void
    {
        $this->customer(['full_name' => 'Ahmad Subarjo', 'customer_code' => 'RQ000001']);

        $this->get('/customers?search=barjo')->assertSee('Ahmad Subarjo'); // potongan tengah nama
    }

    public function test_query_berdigit_tidak_mencocokkan_nama(): void
    {
        // Nama mengandung angka tak lazim; query berdigit dirutekan ke kolom kode,
        // jadi TIDAK mencari di full_name.
        $this->customer(['full_name' => 'Agent 007', 'customer_code' => 'RQ000009']);

        $this->get('/customers?search=007')->assertDontSee('Agent 007');
    }

    public function test_nama_berdigit_dengan_spasi_tetap_dicari_sebagai_nama(): void
    {
        // Nama pelanggan Bisnis lazim mengandung digit (nama usaha/kode
        // cabang) tapi tetap nama biasa (ada spasi) — beda dari "Agent 007"
        // (satu token) di atas. Bug nyata (laporan user 2026-11-20): pelanggan
        // Bisnis hilang dari List Pelanggan saat dicari pakai nama, padahal
        // tampil di List Pelanggan Bisnis (search-nya tidak punya gate ini).
        $this->customer(['full_name' => 'Testing110 Bisnis', 'customer_code' => 'RQ000099']);

        // Assert pakai customer_code (bukan nama yang dicari) — nama yang
        // dicari ikut di-echo balik ke kotak pencarian apa pun hasilnya, jadi
        // assertSee(nama) bisa lolos palsu walau barisnya sendiri tidak ada.
        $this->get('/customers?search=Testing110 Bisnis')->assertSee('RQ000099');
    }

    public function test_hp_dan_nik_dicari_prefix(): void
    {
        $c = $this->customer([
            'full_name' => 'Budi',
            'customer_code' => 'RQ000002',
            'primary_phone' => '081234567890',
            'identity_number' => '3502181212900001',
        ]);

        $this->get('/customers?search=08123456')->assertSee('Budi');   // HP prefix
        $this->get('/customers?search=35021812')->assertSee('Budi');   // NIK prefix
    }
}
