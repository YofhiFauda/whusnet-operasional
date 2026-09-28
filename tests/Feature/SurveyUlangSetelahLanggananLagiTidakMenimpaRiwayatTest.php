<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerInstallation;
use App\Models\CustomerSurvey;
use App\Models\Pop;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi 2026-09-28: Langganan Lagi dengan alat sudah diambil (ADHOC-102)
 * mengirim pelanggan ke Antrean Survey → survey & pemasangan ulang. "Mulai"
 * dulu memakai ulang record survey/pemasangan lama: riwayat masa langganan
 * pertama tertimpa, dan `completed_at` lama bikin durasi sesi baru tidak
 * pernah tercatat (laporan melewati perhitungan kalau completed_at terisi).
 *
 * Aturan sekarang:
 * - record dari SEBELUM pelanggan putus (created_at < terminated_at) → record baru;
 * - record siklus yang sama (mis. lanjut setelah Pending) → dipakai lagi,
 *   waktu selesainya dikosongkan karena "Mulai" = sesi kerja baru.
 */
class SurveyUlangSetelahLanggananLagiTidakMenimpaRiwayatTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $this->pop = Pop::firstOrCreate(['pop_code' => 'C'], [
            'code' => 'C', 'name' => 'Jetis', 'type' => 'cabang', 'status' => 'active',
            'registration_prefix' => 'RQ', 'cid_prefix' => 'C',
        ]);
    }

    private function makeCustomer(string $status, ?string $terminatedAt): Customer
    {
        return Customer::create([
            'customer_code' => 'ULG-'.fake()->unique()->numerify('#####'),
            'full_name' => 'Pelanggan Langganan Lagi',
            'primary_phone' => '081234500000',
            'status' => $status,
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => '2026-01-01',
            'terminated_at' => $terminatedAt,
        ]);
    }

    private function oldRecord(string $class, Customer $customer, string $createdAt): object
    {
        $record = (new $class)->forceFill([
            'customer_id' => $customer->id,
            'started_at' => $createdAt,
            'completed_at' => Carbon::parse($createdAt)->addHour(),
        ]);
        $record->created_at = $createdAt;
        $record->save();

        return $record;
    }

    #[Test]
    public function survey_ulang_setelah_langganan_lagi_membuat_record_baru(): void
    {
        $customer = $this->makeCustomer('waiting_survey', '2026-06-01 00:00:00');
        $old = $this->oldRecord(CustomerSurvey::class, $customer, '2026-01-05 09:00:00');

        $this->post(route('customers.survey.start', $customer))->assertRedirect();

        $this->assertSame(2, CustomerSurvey::where('customer_id', $customer->id)->count());
        // Riwayat survey pertama utuh.
        $this->assertSame('2026-01-05 09:00:00', $old->fresh()->started_at->toDateTimeString());
        $this->assertNotNull($old->fresh()->completed_at);

        $new = $customer->latestSurvey()->first();
        $this->assertNotSame($old->id, $new->id);
        $this->assertNull($new->completed_at);
    }

    #[Test]
    public function survey_siklus_yang_sama_dipakai_lagi_dan_waktu_selesai_dikosongkan(): void
    {
        // Belum pernah putus: survey yang dihentikan (timer ditutup saat
        // Pending) lalu dimulai lagi tetap satu record, tapi completed_at
        // lamanya harus hilang supaya durasi sesi baru dihitung ulang.
        $customer = $this->makeCustomer('waiting_survey', null);
        $old = $this->oldRecord(CustomerSurvey::class, $customer, '2026-01-05 09:00:00');

        $this->post(route('customers.survey.start', $customer))->assertRedirect();

        $this->assertSame(1, CustomerSurvey::where('customer_id', $customer->id)->count());
        $this->assertNull($old->fresh()->completed_at);
        $this->assertNull($old->fresh()->duration_minutes);
    }

    #[Test]
    public function pemasangan_ulang_setelah_langganan_lagi_membuat_record_baru(): void
    {
        $customer = $this->makeCustomer('waiting_installation', '2026-06-01 00:00:00');
        $old = $this->oldRecord(CustomerInstallation::class, $customer, '2026-01-10 09:00:00');

        $this->post(route('customers.installation.start', $customer))->assertRedirect();

        $this->assertSame(2, CustomerInstallation::where('customer_id', $customer->id)->count());
        $this->assertSame('2026-01-10 09:00:00', $old->fresh()->started_at->toDateTimeString());
        $this->assertNotNull($old->fresh()->completed_at);
    }
}
