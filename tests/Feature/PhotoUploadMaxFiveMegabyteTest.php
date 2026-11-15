<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Pop;
use App\Support\UploadLimit;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi: foto kamera HP (3–6 MB) ditolak karena sebagian besar form foto
 * dibatasi 2 MB, dan hint "Maks. 2MB" di-hardcode terpisah dari rule.
 * Keputusan user 2026-11-14: sementara semua upload foto maks 5 MB, satu
 * sumber di App\Support\UploadLimit (rule server + hint form).
 */
class PhotoUploadMaxFiveMegabyteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function batas_upload_foto_lima_mb(): void
    {
        $this->assertSame('max:5120', UploadLimit::rule());
        $this->assertSame('5 MB', UploadLimit::label());
    }

    /**
     * Penjaga: rule upload foto/bukti di app/ tidak boleh menulis angka `max:`
     * sendiri — wajib lewat UploadLimit supaya rule & hint tetap sama.
     * Kwitansi massal (8 MB, bukan upload foto per form) sengaja dikecualikan.
     */
    #[Test]
    public function rule_upload_foto_tidak_menulis_angka_max_sendiri(): void
    {
        $pengecualian = ['PaymentReceiptController.php'];
        $pelanggaran = [];

        foreach (File::allFiles(app_path()) as $file) {
            if ($file->getExtension() !== 'php' || in_array($file->getFilename(), $pengecualian, true)) {
                continue;
            }

            foreach (file($file->getPathname()) as $nomor => $baris) {
                $ruleFile = preg_match("/'image'|\|image\b|mimes:|'file'/", $baris);
                if ($ruleFile && preg_match('/max:\d+/', $baris)) {
                    $pelanggaran[] = $file->getRelativePathname().':'.($nomor + 1);
                }
            }
        }

        $this->assertSame([], $pelanggaran, 'Pakai App\Support\UploadLimit::rule() untuk batas ukuran upload.');
    }

    #[Test]
    public function foto_empat_setengah_mb_diterima_dan_enam_mb_ditolak(): void
    {
        $this->seed(DatabaseSeeder::class);
        Storage::fake();
        Storage::fake('local');
        Storage::fake('public');
        $admin = $this->loginAsAdmin();

        $pop = Pop::create([
            'code' => 'UPL1', 'pop_code' => 'UPL1', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'POP Upload', 'type' => 'cabang', 'status' => 'active',
        ]);
        $customer = Customer::create([
            'customer_code' => 'UPL-'.random_int(10000, 99999),
            'full_name' => 'Pelanggan Upload',
            'primary_phone' => '081234500000',
            'status' => 'active',
            'pop_id' => $pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('customers.documents.store', $customer), [
                'document_type' => 'kontrak',
                'document_file' => UploadedFile::fake()->image('kontrak.jpg')->size(4600),
            ])
            ->assertSessionDoesntHaveErrors('document_file');

        $this->actingAs($admin)
            ->post(route('customers.documents.store', $customer), [
                'document_type' => 'kontrak',
                'document_file' => UploadedFile::fake()->image('kontrak.jpg')->size(6000),
            ])
            ->assertSessionHasErrors('document_file');
    }

    #[Test]
    public function form_registrasi_menampilkan_hint_maks_lima_mb(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->actingAs($this->loginAsAdmin())
            ->get(route('customers.create'))
            ->assertOk()
            ->assertSee('Maks. 5 MB')
            ->assertDontSee('Max 2MB');
    }
}
