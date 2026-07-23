<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Task;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FileUploadService
{
    /**
     * Dapatkan pengenal unik pelanggan (CID / Customer Code / Request ID).
     * Contoh output: C00RQ00012, RQ00012
     */
    public static function getCustomerIdentifier(?Customer $customer): string
    {
        if (! $customer) {
            return 'UNKNOWN';
        }

        return trim((string) ($customer->cid ?: ($customer->customer_code ?: ($customer->old_request_id ?: ($customer->old_customer_id ?: sprintf('RQ%05d', $customer->id))))));
    }

    /**
     * Dapatkan nama bersih pelanggan untuk penamaan file.
     * Contoh output: Budi Santoso
     */
    public static function getCustomerName(?Customer $customer): string
    {
        if (! $customer) {
            return 'Pelanggan';
        }

        $name = trim(preg_replace('/[^A-Za-z0-9 _-]/', '', $customer->full_name ?? 'Pelanggan'));

        return $name !== '' ? $name : 'Pelanggan';
    }

    /**
     * 1. Foto Bukti Task Lapangan (Task Evidences)
     * Aturan folder: task-evidences/{task_id}
     * Contoh format: TASK-2026-0008_C00RQ00012_Budi Santoso.jpg
     */
    public static function uploadTaskEvidence(UploadedFile $file, Task $task): string
    {
        $task->loadMissing('customer');
        $taskId = $task->task_number ?: sprintf('TASK-%04d', $task->id);
        $customerId = self::getCustomerIdentifier($task->customer);
        $customerName = self::getCustomerName($task->customer);
        $ext = $file->getClientOriginalExtension();

        $folder = "task-evidences/{$taskId}";
        $baseName = "{$taskId}_{$customerId}_{$customerName}";
        $fileName = self::getUniqueFileName($folder, $baseName, $ext);

        return $file->storeAs($folder, $fileName, 'public');
    }

    /**
     * 2. Registrasi Pelanggan
     * Aturan folder: registrations/ktp
     * Contoh format: ktp_C00RQ00012_Budi Santoso.jpg
     */
    public static function uploadCustomerRegistrationDoc(UploadedFile $file, Customer $customer, string $type): string
    {
        $customerId = self::getCustomerIdentifier($customer);
        $customerName = self::getCustomerName($customer);
        $ext = $file->getClientOriginalExtension();

        if ($type === 'ktp') {
            $folder = 'registrations/ktp';
            $baseName = "ktp_{$customerId}_{$customerName}";
        } elseif ($type === 'rumah') {
            $folder = 'surveys/rumah';
            $baseName = "rumah_{$customerId}_{$customerName}";
        } elseif ($type === 'kontrak') {
            $folder = 'installations/kontrak';
            $baseName = "kontrak_{$customerId}_{$customerName}";
        } else {
            $folder = "registrations/{$type}";
            $baseName = "{$type}_{$customerId}_{$customerName}";
        }

        $fileName = self::getUniqueFileName($folder, $baseName, $ext);

        return $file->storeAs($folder, $fileName, 'public');
    }

    /**
     * 3. Laporan Hasil Survey
     * Aturan folder:
     * - surveys/rumah = rumah_C00RQ00012_Budi Santoso.jpg
     * - surveys/odp   = odp_C00RQ00012_Budi Santoso.jpg
     */
    public static function uploadSurveyPhoto(UploadedFile $file, Customer $customer, string $photoType): string
    {
        $customerId = self::getCustomerIdentifier($customer);
        $customerName = self::getCustomerName($customer);
        $ext = $file->getClientOriginalExtension();

        if (in_array(strtolower($photoType), ['house', 'rumah'], true)) {
            $folder = 'surveys/rumah';
            $prefix = 'rumah';
        } else {
            $folder = 'surveys/odp';
            $prefix = 'odp';
        }

        $baseName = "{$prefix}_{$customerId}_{$customerName}";
        $fileName = self::getUniqueFileName($folder, $baseName, $ext);

        return $file->storeAs($folder, $fileName, 'public');
    }

    /**
     * 4. Laporan Hasil Pemasangan (PSB)
     * Aturan folder:
     * - installations/pemasangan = pemasangan_C00RQ00012_Budi Santoso.jpg
     * - installations/kontrak    = kontrak_C00RQ00012_Budi Santoso.jpg
     * - installations/ttd        = ttd_C00RQ00012_Budi Santoso.jpg
     * - installations/speedtests = speedtest_C00RQ00012_Budi Santoso.jpg
     */
    public static function uploadInstallationPhoto(UploadedFile $file, Customer $customer, string $photoType): string
    {
        $customerId = self::getCustomerIdentifier($customer);
        $customerName = self::getCustomerName($customer);
        $ext = $file->getClientOriginalExtension();

        $folder = match (strtolower($photoType)) {
            'kontrak', 'contract' => 'installations/kontrak',
            'ttd', 'signature' => 'installations/ttd',
            'speedtest', 'speedtests' => 'installations/speedtests',
            default => 'installations/pemasangan',
        };

        $prefix = match (strtolower($photoType)) {
            'kontrak', 'contract' => 'kontrak',
            'ttd', 'signature' => 'ttd',
            'speedtest', 'speedtests' => 'speedtest',
            default => 'pemasangan',
        };

        $baseName = "{$prefix}_{$customerId}_{$customerName}";
        $fileName = self::getUniqueFileName($folder, $baseName, $ext);

        return $file->storeAs($folder, $fileName, 'public');
    }

    /**
     * 5. Laporan Maintenance
     * Aturan folder:
     * - maintenance/opm       = opm_C00RQ00012_Budi Santoso.jpg
     * - maintenance/speedtest = speedtest_C00RQ00012_Budi Santoso.jpg
     */
    public static function uploadMaintenancePhoto(UploadedFile $file, ?Customer $customer, string $photoType): string
    {
        $customerId = self::getCustomerIdentifier($customer);
        $customerName = self::getCustomerName($customer);
        $ext = $file->getClientOriginalExtension();

        if (strtolower($photoType) === 'opm') {
            $folder = 'maintenance/opm';
            $prefix = 'opm';
        } else {
            $folder = 'maintenance/speedtest';
            $prefix = 'speedtest';
        }

        $baseName = "{$prefix}_{$customerId}_{$customerName}";
        $fileName = self::getUniqueFileName($folder, $baseName, $ext);

        return $file->storeAs($folder, $fileName, 'public');
    }

    /**
     * 6. Bukti Transfer Pembayaran
     * Aturan folder: payments/{id_pelanggan}/{awal|bulanan|reaktivasi}
     * Contoh format:
     * - pembayaran-awal_02-06-2026_RQ00012_Budi Santoso.jpg
     * - bulan_02-07-2026_RQ00012_Budi Santoso.jpg
     * - reaktivasi_02-10-2026_RQ00012_Budi Santoso.jpg
     */
    public static function uploadPaymentProof(UploadedFile $file, ?Customer $customer, ?string $invoiceType, string $paymentDate): string
    {
        $customerId = self::getCustomerIdentifier($customer);
        $customerName = self::getCustomerName($customer);
        $ext = $file->getClientOriginalExtension();

        // Gunakan format DD-MM-YYYY agar aman di OS filesystem (menghindari subfolder tak sengaja akibat karakter '/')
        $timestamp = strtotime($paymentDate);
        $dateStr = $timestamp ? date('d-m-Y', $timestamp) : date('d-m-Y');

        $type = strtolower((string) ($invoiceType ?: 'bulanan'));
        if ($type === 'awal') {
            $category = 'awal';
            $prefix = 'pembayaran-awal';
        } elseif ($type === 'reaktivasi') {
            $category = 'reaktivasi';
            $prefix = 'reaktivasi';
        } else {
            $category = 'bulanan';
            $prefix = 'bulan';
        }

        $folder = "payments/{$customerId}/{$category}";
        $baseName = "{$prefix}_{$dateStr}_{$customerId}_{$customerName}";
        $fileName = self::getUniqueFileName($folder, $baseName, $ext);

        return $file->storeAs($folder, $fileName, 'public');
    }

    /**
     * Pastikan nama file unik di folder storage disk public jika sudah ada file berformat sama.
     */
    private static function getUniqueFileName(string $folder, string $baseName, string $ext): string
    {
        $fileName = "{$baseName}.{$ext}";
        $counter = 2;

        while (Storage::disk('public')->exists("{$folder}/{$fileName}")) {
            $fileName = "{$baseName}_{$counter}.{$ext}";
            $counter++;
        }

        return $fileName;
    }
}
