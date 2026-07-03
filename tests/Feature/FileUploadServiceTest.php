<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Task;
use App\Services\FileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileUploadServiceTest extends TestCase
{
    public function test_upload_task_evidence_naming_rule()
    {
        Storage::fake('public');

        $customer = new Customer([
            'cid' => 'C00RQ00012',
            'full_name' => 'Budi Santoso',
        ]);
        $customer->id = 12;

        $task = new Task([
            'task_number' => 'TASK-2026-0008',
        ]);
        $task->id = 8;
        $task->setRelation('customer', $customer);

        $file = UploadedFile::fake()->image('bukti.jpg');
        $path = FileUploadService::uploadTaskEvidence($file, $task);

        $this->assertEquals('task-evidences/TASK-2026-0008/TASK-2026-0008_C00RQ00012_Budi Santoso.jpg', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_upload_customer_registration_naming_rule()
    {
        Storage::fake('public');

        $customer = new Customer([
            'cid' => 'C00RQ00012',
            'full_name' => 'Budi Santoso',
        ]);
        $customer->id = 12;

        $file = UploadedFile::fake()->image('ktp.jpg');
        $path = FileUploadService::uploadCustomerRegistrationDoc($file, $customer, 'ktp');

        $this->assertEquals('registrations/ktp/ktp_C00RQ00012_Budi Santoso.jpg', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_upload_survey_naming_rule()
    {
        Storage::fake('public');

        $customer = new Customer([
            'cid' => 'C00RQ00012',
            'full_name' => 'Budi Santoso',
        ]);
        $customer->id = 12;

        $fileRumah = UploadedFile::fake()->image('rumah.jpg');
        $pathRumah = FileUploadService::uploadSurveyPhoto($fileRumah, $customer, 'rumah');
        $this->assertEquals('surveys/rumah/rumah_C00RQ00012_Budi Santoso.jpg', $pathRumah);

        $fileOdp = UploadedFile::fake()->image('odp.jpg');
        $pathOdp = FileUploadService::uploadSurveyPhoto($fileOdp, $customer, 'odp');
        $this->assertEquals('surveys/odp/odp_C00RQ00012_Budi Santoso.jpg', $pathOdp);
    }

    public function test_upload_installation_naming_rule()
    {
        Storage::fake('public');

        $customer = new Customer([
            'cid' => 'C00RQ00012',
            'full_name' => 'Budi Santoso',
        ]);
        $customer->id = 12;

        $filePemasangan = UploadedFile::fake()->image('psb.jpg');
        $pathPemasangan = FileUploadService::uploadInstallationPhoto($filePemasangan, $customer, 'pemasangan');
        $this->assertEquals('installations/pemasangan/pemasangan_C00RQ00012_Budi Santoso.jpg', $pathPemasangan);

        $fileKontrak = UploadedFile::fake()->image('kontrak.jpg');
        $pathKontrak = FileUploadService::uploadInstallationPhoto($fileKontrak, $customer, 'kontrak');
        $this->assertEquals('installations/kontrak/kontrak_C00RQ00012_Budi Santoso.jpg', $pathKontrak);

        $fileTtd = UploadedFile::fake()->image('ttd.jpg');
        $pathTtd = FileUploadService::uploadInstallationPhoto($fileTtd, $customer, 'ttd');
        $this->assertEquals('installations/ttd/ttd_C00RQ00012_Budi Santoso.jpg', $pathTtd);

        $fileSpeedtest = UploadedFile::fake()->image('st.jpg');
        $pathSpeedtest = FileUploadService::uploadInstallationPhoto($fileSpeedtest, $customer, 'speedtest');
        $this->assertEquals('installations/speedtests/speedtest_C00RQ00012_Budi Santoso.jpg', $pathSpeedtest);
    }

    public function test_upload_maintenance_naming_rule()
    {
        Storage::fake('public');

        $customer = new Customer([
            'cid' => 'C00RQ00012',
            'full_name' => 'Budi Santoso',
        ]);
        $customer->id = 12;

        $fileOpm = UploadedFile::fake()->image('opm.jpg');
        $pathOpm = FileUploadService::uploadMaintenancePhoto($fileOpm, $customer, 'opm');
        $this->assertEquals('maintenance/opm/opm_C00RQ00012_Budi Santoso.jpg', $pathOpm);

        $fileSpeedtest = UploadedFile::fake()->image('speedtest.jpg');
        $pathSpeedtest = FileUploadService::uploadMaintenancePhoto($fileSpeedtest, $customer, 'speedtest');
        $this->assertEquals('maintenance/speedtest/speedtest_C00RQ00012_Budi Santoso.jpg', $pathSpeedtest);
    }

    public function test_upload_payment_proof_naming_rule()
    {
        Storage::fake('public');

        $customer = new Customer([
            'customer_code' => 'RQ00012',
            'full_name' => 'Budi Santoso',
        ]);
        $customer->id = 12;

        $fileAwal = UploadedFile::fake()->image('bayar_awal.jpg');
        $pathAwal = FileUploadService::uploadPaymentProof($fileAwal, $customer, 'awal', '2026-06-02');
        $this->assertEquals('payments/RQ00012/awal/pembayaran-awal_02-06-2026_RQ00012_Budi Santoso.jpg', $pathAwal);

        $fileBulanan = UploadedFile::fake()->image('bayar_bulan.jpg');
        $pathBulanan = FileUploadService::uploadPaymentProof($fileBulanan, $customer, 'bulanan', '2026-07-02');
        $this->assertEquals('payments/RQ00012/bulanan/bulan_02-07-2026_RQ00012_Budi Santoso.jpg', $pathBulanan);
    }
}
