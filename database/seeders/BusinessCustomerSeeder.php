<?php

namespace Database\Seeders;

use App\Enums\SerialStatus;
use App\Models\Customer;
use App\Models\CustomerAcquisition;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\PackageCategory;
use App\Models\Pop;
use App\Models\Role;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * BusinessCustomerSeeder — data List Pelanggan Bisnis
 * (`/business-development/business-customers`), disalin dari tabel sumber
 * `docs/plan/bussiness-development/tabel_paket_bisnis.md` (7 pelanggan,
 * Mei–Agustus 2026).
 *
 * Halaman itu MURNI turunan (tanpa tabel sendiri), jadi seeder ini mengisi
 * tabel aslinya: kategori paket Bisnis (+ role validator BD), paket,
 * pelanggan, layanan, baris `customer_acquisitions` (Biaya Instalasi), dan
 * `inventory_serials` berstatus INSTALLED (Alat yang Ditinggalkan).
 *
 * Catatan pemetaan dari tabel sumber:
 *  - "Harga Sesudah PPN" = `total_monthly_bill`. Dua pelanggan Broadband
 *    (Anjalis, Kos The Cozy) PPN 0% → angkanya sama dengan Harga Paket.
 *    Empat lainnya PPN 11%; Rp433.000 di tabel sumber adalah pembulatan
 *    Rp390.000 × 1,11 (= Rp432.900) — angka tabel yang dipakai apa adanya.
 *  - SAVE PLUS GREBEG SURO: ONT F670-nya "sudah didismantle" (sudah ditarik),
 *    jadi TIDAK ditanam sebagai unit terpasang — kolom Alat-nya kosong (—).
 *  - Nomor SN karangan (`BIZ-…`): tabel sumber cuma mencatat jenis & jumlah
 *    alat, bukan SN-nya. Ganti dengan SN riil kalau datanya ada.
 *
 * Idempotent (firstOrCreate/updateOrCreate) — aman dijalankan ulang. Butuh
 * RoleSeeder + CustomerAcquisitionFeatureSeeder (pemetaan kategori Bisnis →
 * role BD) sudah jalan; kategori dipetakan ulang di sini kalau belum.
 *
 * Jalankan: php artisan db:seed --class=BusinessCustomerSeeder
 */
class BusinessCustomerSeeder extends Seeder
{
    private const BROADBAND = 'Paket Bisnis Broadband';

    private const UKM = 'Paket Bisnis UKM';

    public function run(): void
    {
        // Master kategori barang (modem_ont/router_gateway/access_point) —
        // idempotent, dan dirujuk barang-barang di bawah.
        $this->call(ItemCategorySeeder::class);

        $busdevRoleId = Role::where('code', 'business_development')->value('id');
        if (! $busdevRoleId) {
            $this->command->error('Role business_development tidak ditemukan. Jalankan RoleSeeder dulu.');

            return;
        }

        // Kategori Bisnis = kategori yang punya role validator Biaya
        // Instalasi — itu yang dipakai halaman List Pelanggan Bisnis untuk
        // menentukan "pelanggan Bisnis".
        foreach ([self::BROADBAND => 10, self::UKM => 20] as $name => $sort) {
            $category = PackageCategory::firstOrCreate(['name' => $name], ['is_active' => true, 'sort_order' => $sort]);
            $category->update(['installation_fee_approval_role_id' => $busdevRoleId]);
        }

        // `MasterPopSeeder` sengaja tidak dipanggil DatabaseSeeder (POP diisi
        // admin lewat UI), jadi POP bisa saja belum ada di DB baru.
        $pop = Pop::first() ?? Pop::factory()->create(['name' => 'POP Demo Bisnis']);

        $items = $this->items();

        foreach ($this->rows() as $index => $row) {
            $package = $this->package($row['category'], $row['price']);

            $customer = Customer::where('full_name', $row['name'])->first()
                ?? Customer::factory()->create([
                    'full_name' => $row['name'],
                    'status' => 'active',
                    'pop_id' => $pop->id,
                    'internet_package_id' => $package->id,
                ]);

            CustomerService::updateOrCreate(
                ['customer_id' => $customer->id],
                [
                    'internet_package_id' => $package->id,
                    'package_name_snapshot' => $package->name,
                    'monthly_price' => $row['price'],
                    'discount' => 0,
                    'ppn' => $row['ppn'],
                    'total_monthly_bill' => $row['total'],
                    'activation_date' => $row['activated'],
                    'service_status' => 'aktif',
                    'billing_status' => 'pending',
                ]
            );

            // Dibuat langsung (bypass CustomerObserver) — pola sama
            // BusinessDevelopmentSeeder; seeder tak perlu mensimulasikan
            // alur verifikasi penuh. Periode = bulan aktivasi.
            $activatedAt = Carbon::parse($row['activated']);
            CustomerAcquisition::updateOrCreate(
                ['customer_id' => $customer->id],
                [
                    'periode' => $activatedAt->format('Y-m'),
                    'verified_at' => $activatedAt,
                    'installation_fee' => $row['installation_fee'],
                ]
            );

            foreach ($row['equipment'] as $itemCode => $qty) {
                for ($n = 1; $n <= $qty; $n++) {
                    InventorySerial::firstOrCreate(
                        ['serial_number' => sprintf('BIZ-%s-%d-%d', $itemCode, $index + 1, $n)],
                        [
                            'item_id' => $items[$itemCode]->id,
                            'status' => SerialStatus::INSTALLED->value,
                            'customer_id' => $customer->id,
                            'installed_at' => $activatedAt,
                        ]
                    );
                }
            }
        }

        $this->command->info('BusinessCustomerSeeder: 7 pelanggan Bisnis ditanam (lihat /business-development/business-customers).');
    }

    /**
     * Satu paket per kombinasi kategori + harga — tabel sumber cuma mencatat
     * "Harga Paket yang Diambil", bukan kode paket katalog.
     */
    private function package(string $category, int $price): InternetPackage
    {
        $isBroadband = $category === self::BROADBAND;

        return InternetPackage::updateOrCreate(
            ['package_code' => sprintf('BIZ-%s-%d', $isBroadband ? 'BB' : 'UKM', $price)],
            [
                'name' => sprintf('%s Rp%s', $isBroadband ? 'Bisnis Broadband' : 'Bisnis UKM', number_format($price, 0, ',', '.')),
                'category' => $category,
                'package_group' => $isBroadband ? 'Bisnis Broadband 1:4 & 1:8' : 'Cafe & Warung Kopi / UKM',
                'bandwidth_label' => 'Custom',
                'monthly_price' => $price,
                'is_active' => true,
            ]
        );
    }

    /**
     * @return array<string, Item>
     */
    private function items(): array
    {
        $categoryIds = ItemCategory::pluck('id', 'code');

        $catalog = [
            'ONT-F670L' => ['ONT F670L', ItemCategorySeeder::CODE_MODEM_ONT],
            'MTK-L009UIGS' => ['Mikrotik L009UiGS', ItemCategorySeeder::CODE_ROUTER_GATEWAY],
            'AP-OMADA-AX1800' => ['TpLink Omada AX1800', ItemCategorySeeder::CODE_ACCESS_POINT],
            'AP-FIBERHOME' => ['AP FiberHome', ItemCategorySeeder::CODE_ACCESS_POINT],
            'AP-ARCHER-AX12' => ['AP TpLink Archer AX12', ItemCategorySeeder::CODE_ACCESS_POINT],
            'AP-WIFI6' => ['AP WiFi 6', ItemCategorySeeder::CODE_ACCESS_POINT],
        ];

        $items = [];
        foreach ($catalog as $code => [$name, $categoryCode]) {
            $items[$code] = Item::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'item_category_id' => $categoryIds[$categoryCode],
                    'unit' => 'pcs',
                    'is_active' => true,
                    'tracking_type' => 'serialized',
                    'ownership_mode' => 'installable',
                ]
            );
        }

        return $items;
    }

    /**
     * @return list<array{name: string, category: string, price: int, ppn: int, total: int, installation_fee: int, activated: string, equipment: array<string, int>}>
     */
    private function rows(): array
    {
        return [
            [
                'name' => 'PT. ANJALIS GROUP INDONESIA', 'category' => self::BROADBAND,
                'price' => 5500000, 'ppn' => 0, 'total' => 5500000,
                'installation_fee' => 2500000, 'activated' => '2026-05-07',
                'equipment' => ['ONT-F670L' => 1, 'MTK-L009UIGS' => 1, 'AP-OMADA-AX1800' => 3],
            ],
            [
                'name' => 'KOS THE COZY (DUTA MAHARDIKA)', 'category' => self::BROADBAND,
                'price' => 1190000, 'ppn' => 0, 'total' => 1190000,
                'installation_fee' => 0, 'activated' => '2026-05-12',
                'equipment' => ['AP-FIBERHOME' => 6],
            ],
            [
                'name' => 'WARUNG KOPI CANGKIR KUMPUL', 'category' => self::UKM,
                'price' => 390000, 'ppn' => 11, 'total' => 433000,
                'installation_fee' => 0, 'activated' => '2026-05-20',
                'equipment' => ['ONT-F670L' => 1, 'AP-ARCHER-AX12' => 1],
            ],
            [
                'name' => 'TEDUH ALAMI RESTO FAMILY & CAFE', 'category' => self::UKM,
                'price' => 390000, 'ppn' => 11, 'total' => 433000,
                'installation_fee' => 0, 'activated' => '2026-05-15',
                'equipment' => ['ONT-F670L' => 1, 'AP-OMADA-AX1800' => 1],
            ],
            [
                'name' => 'SPPG BALONG', 'category' => self::UKM,
                'price' => 390000, 'ppn' => 11, 'total' => 433000,
                'installation_fee' => 250000, 'activated' => '2026-05-12',
                'equipment' => ['ONT-F670L' => 2],
            ],
            [
                // ONT F670 sudah didismantle → tidak ada alat terpasang.
                'name' => 'SAVE PLUS GREBEG SURO', 'category' => self::BROADBAND,
                'price' => 1500000, 'ppn' => 11, 'total' => 1665000,
                'installation_fee' => 500000, 'activated' => '2026-06-06',
                'equipment' => [],
            ],
            [
                'name' => 'TAMAN JATIMORI', 'category' => self::UKM,
                'price' => 250000, 'ppn' => 11, 'total' => 277500,
                'installation_fee' => 500000, 'activated' => '2026-08-13',
                'equipment' => ['AP-WIFI6' => 1],
            ],
        ];
    }
}
