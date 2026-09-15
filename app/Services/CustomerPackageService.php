<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class CustomerPackageService
{
    /**
     * Ganti paket internet pelanggan aktif (Paket A → Paket B).
     *
     * Cuma menimpa kolom terkait paket (paket, snapshot kecepatan, harga,
     * total tagihan bulanan) — diskon/PPN/biaya lain pelanggan TIDAK ikut
     * direset, itu kesepakatan terpisah dari pilihan paket.
     *
     * Kenapa gak nyentuh invoice/tagihan sama sekali: `monthly_price` di sini
     * cuma dibaca ULANG tiap `GenerateMonthlyInvoicesCommand` jalan bikin
     * tagihan BULANAN periode baru — tagihan periode berjalan yang sudah
     * terbit (biasanya tanggal 1) sudah snapshot harga lama di baris
     * invoice-nya sendiri dan TIDAK ikut berubah. Efeknya otomatis baru
     * kepakai mulai periode berikutnya, tanpa perlu kolom "pending" tambahan.
     */
    public function change(Customer $customer, InternetPackage $newPackage): CustomerService
    {
        $service = $customer->customerService;

        if (! $service) {
            throw new RuntimeException('Pelanggan belum punya layanan aktif — pakai form edit pelanggan untuk memilih paket pertama kali.');
        }

        if ($service->internet_package_id === $newPackage->id) {
            throw new InvalidArgumentException('Paket baru sama dengan paket yang sedang berjalan.');
        }

        return DB::transaction(function () use ($customer, $service, $newPackage) {
            $downLabel = $newPackage->download_speed_mbps !== null ? $newPackage->download_speed_mbps.' Mbps' : null;
            $upLabel = $newPackage->upload_speed_mbps !== null ? $newPackage->upload_speed_mbps.' Mbps' : null;

            $monthlyPrice = (float) $newPackage->monthly_price;
            $discount = (float) ($service->discount ?? 0);
            $ppnPercent = (float) ($service->ppn ?? 0);
            $otherFee = (float) ($service->other_fee ?? 0);

            $discountedPrice = max(0, $monthlyPrice - $discount);
            $totalBill = $discountedPrice * (1 + $ppnPercent / 100) + $otherFee;

            $service->update([
                'internet_package_id' => $newPackage->id,
                'package_name_snapshot' => $newPackage->name,
                'download_speed_snapshot' => $downLabel,
                'upload_speed_snapshot' => $upLabel,
                'monthly_price' => $monthlyPrice,
                'total_monthly_bill' => $totalBill,
            ]);

            // customers.internet_package_id itu FK langsung legacy yang masih
            // dibaca beberapa jalur lama (SLA paket tiket — lihat
            // docs/master/sla-timeline/, relasi Customer::internetPackage()).
            // customer_services tetap sumber kebenaran utama, tapi dua kolom
            // ini wajib disinkron di titik yang sama supaya gak menyimpang.
            $customer->update(['internet_package_id' => $newPackage->id]);

            return $service->fresh();
        });
    }
}
