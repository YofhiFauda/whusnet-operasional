<?php

namespace App\Console\Commands\Operational;

use App\Enums\InvoiceType;
use App\Enums\WorkflowTransition;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\CustomerTerminationReason;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Pop;
use App\Models\RevenueCategory;
use App\Models\RevenueSubcategory;
use App\Models\User;
use App\Services\CustomerTerminationService;
use App\Services\InvoiceItemBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\SimpleExcel\SimpleExcelReader;
use Throwable;

class CustomerPackageReconcileFromExcelCommand extends Command
{
    /**
     * Generalisasi dari command satu-off `app:sambit-okt2026-reconcile`
     * (dibuat 2026-11-02 utk cabang Sambit) — dirubah per permintaan user
     * biar bisa dipakai cabang mana pun yang punya rekap Excel manual
     * kolektor dengan format serupa. Fokus utama Data Pelanggan & Paket
     * (+harga layanan yang ikut nempel ke paket), DAN ikut membetulkan
     * invoice BULANAN yang belum dibayar sama sekali kalau kena generate
     * duluan (scheduler) pakai harga yang belum dibetulkan — SATU command,
     * supaya end user tidak perlu jalanin command kedua terpisah buat itu
     * (dulu ada `app:correct-stale-monthly-invoices` sendiri, DIGABUNG ke
     * sini 2026-11-02 atas permintaan user: "1 command aja biar semuanya
     * teratasi"). Kolom "Collector" di Excel SENGAJA tidak pernah dipetakan
     * ke field apa pun — belum ada keputusan soal pemetaannya ke user/
     * kolektor sistem, jangan ditambahkan sampai ada keputusan eksplisit.
     * Invoice yang SUDAH kena bayar sebagian/lunas TIDAK PERNAH disentuh —
     * itu butuh keputusan eksplisit beda, bukan backfill otomatis.
     *
     * Setiap cabang punya kuirk beda (nama ditulis bebas kolektor, duplikat
     * nama, typo kode paket) — SATU set alias/skip/typo-fix tidak akan pas
     * dipakai cabang lain begitu saja. Daripada hardcode per cabang di kelas
     * PHP (dan harus bikin file command baru tiap cabang), konfigurasi itu
     * dipisah ke JSON per cabang lewat --config, hasil review manual
     * laporan reconcile cabang itu (JANGAN diisi otomatis dari fuzzy-
     * matching — satu alias salah nempelin harga pelanggan ke orang yang
     * salah). Lihat `docs/database/excel/sambit-okt2026-config.json` sebagai
     * contoh/referensi cabang Sambit.
     *
     * Root cause harga awal (temuan Sambit, kemungkinan berlaku juga cabang
     * lain yang sumbernya sama): `monthly_price` hasil migrasi
     * `app:import-legacy-sql` diisi dari `biaya_tagihan.BIAYABULANAN` baris
     * TERAKHIR per request — bisa kecatet promo/diskon/tagihan parsial,
     * bukan harga paket bulanan rutin.
     *
     * `internet_package_id` diganti kalau layanan masih "default" ATAU paket
     * yang terpasang SAAT INI tidak sama HARGA+SPEED dengan paket target
     * hasil resolve KODE Excel di katalog (bukan cuma bandingkan harga, dan
     * bukan cuma bandingkan id baris katalog — lihat komentar di `handle()`
     * utk 2 temuan nyata yang mematahkan diskriminator yang lebih naif).
     * Kalau kode Excel tidak dikenal katalog, fallback ke bandingkan harga
     * paket SAAT INI vs nominal Excel (gak bisa pastikan tier yang benar).
     * Tier yang harga+speed-nya SUDAH sama dengan target TIDAK diganti
     * walau beda id/skema penamaan katalog — cuma churn, bukan perbaikan.
     * Nama ambigu (>1 atau 0 kandidat persis) dilewati total, dilaporkan —
     * BUTUH keputusan manual, tidak ditebak.
     *
     * SENGAJA tidak lewat `CustomerPackageService::change()` — service itu
     * untuk event upgrade/downgrade LIVE (reprorate invoice periode
     * berjalan), bukan backfill koreksi data migrasi lama. Pola
     * `updateQuietly()` konsisten dengan `FixLegacyBillingBatch2Command`.
     *
     * **`manual_overrides` (2026-11-02, migrasi skala besar >10k pelanggan
     * lintas cabang):** nama yang GAK BISA dicocokkan otomatis (ambigu, salah
     * ketik parah, atau memang gak ada baris Excel-nya) tetap WAJIB lewat
     * command ini, JANGAN dibetulin manual via tombol "Ganti Paket" di UI.
     * Alasannya BUKAN selera — "Ganti Paket" lewat `CustomerPackageService::
     * change()`, yang MEMANG DIRANCANG reprorate invoice periode berjalan
     * kalau sudah terbit (skema upgrade/downgrade, ADHOC-68). Itu benar untuk
     * event ganti paket SUNGGUHAN, tapi SALAH untuk koreksi data migrasi:
     * pelanggan migrasi TIDAK PERNAH beneran pakai "paket lama" yang salah
     * itu — itu cuma kesalahan data, bukan hari yang beneran dia jalani di
     * paket lama. Mem-prorate koreksi migrasi berarti menagih pelanggan
     * sebagian pakai harga yang gak pernah mereka pakai sungguhan. `handle()`
     * di bawah SENGAJA menerapkan koreksi `manual_overrides` lewat jalur yang
     * SAMA PERSIS dengan baris Excel (`applyCorrection()`, timpa langsung +
     * koreksi invoice stale, TANPA prorate) — supaya migrasi besar yang
     * reviewnya bisa berbulan-bulan (mis. 5.000 pelanggan, 2.000 auto-match
     * Excel + 3.000 sisanya baru kelar direview minggu ke-3, padahal tagihan
     * bulanan sudah terbit duluan di antaranya) tetap aman dari jebakan itu
     * di SEMUA baris, bukan cuma yang auto-match.
     *
     * **`--terminate-missing` (2026-11-05, opt-in, default OFF):** pelanggan
     * di Pop yang sama tapi TIDAK ADA baris Excel-nya (bukan nama yang
     * di-skip, bukan nama ambigu, bukan yang dikoreksi lewat
     * `manual_overrides`) dianggap sudah putus langganan — rekap Excel
     * kolektor jadi sumber kebenaran TERBARU, bukan cuma buat koreksi
     * harga/paket. Jalan lewat `CustomerTerminationService::terminate()`,
     * jalur resmi YANG SAMA dengan form "Request Putus Langganan" di UI —
     * BUKAN update status manual di sini, supaya audit log, invariant
     * `terminated_at`, dan state machine `WorkflowTransition` tetap
     * terjaga. Status alat "Modem belum diambil" TIDAK PERNAH field
     * tersendiri yang perlu diisi manual — begitu pelanggan baru pertama
     * kali di-terminate, `customer_devices.device_retrieved_at` otomatis
     * tetap NULL (belum ada task AMBIL_MODEM yang selesai), jadi tombol
     * "Ambil Alat" otomatis muncul di List Putus Langganan tanpa command
     * ini perlu menyentuhnya sama sekali.
     *
     * Alasan putus (`termination_reason_id`) SENGAJA bukan opsi — command
     * `firstOrCreate()` satu baris master alasan tetap bernama konstan
     * (lihat `TERMINATION_REASON_NAME`) kalau belum ada, lalu selalu pakai
     * itu. Idempotent lintas cabang/environment; admin tetap bebas kelola
     * baris ini normal lewat UI Master Alasan Putus Langganan sesudahnya.
     * `penalty_amount` SELALU 0 — ini batch koreksi data, bukan keputusan
     * denda per pelanggan (beda dari form UI yang wajib diisi admin).
     *
     * `--actor-id` wajib kalau `--terminate-missing` — console command
     * tidak punya `Auth::user()`, dan `AuditLog`/`CustomerTerminationService`
     * butuh user id pelaku yang valid.
     */
    private const TERMINATION_REASON_NAME = 'Rekonsiliasi Data Excel';

    protected $signature = 'app:customer-package-reconcile-excel
                        {file : Path Excel sumber (relatif ke base_path, atau absolut)}
                        {--pop-id= : ID Pop tujuan. Wajib kalau nama Pop ambigu/tidak diisi --pop-name}
                        {--pop-name= : Nama Pop tujuan, alternatif --pop-id}
                        {--config= : Path JSON alias/skip/typo-fix/manual_overrides khusus cabang ini (opsional, lihat contoh docs/database/excel/sambit-okt2026-config.json)}
                        {--terminate-missing : Putus langganan pelanggan di Pop ini yang TIDAK ada baris Excel-nya (opt-in, default OFF)}
                        {--actor-id= : User id pelaku, wajib kalau --terminate-missing (dicatat di audit log)}
                        {--apply : Tulis perubahan ke database. Tanpa flag ini cuma dry-run (tampilkan diff)}';

    // php artisan app:customer-package-reconcile-excel "docs/database/excel/OKT_sambit_Tagihan Sambit Oktober 2026.xlsx" --pop-name=Sambit --config=docs/database/excel/sambit-okt2026-config.json --apply
    protected $description = 'Koreksi monthly_price & paket pelanggan satu Pop sesuai rekap Excel kolektor (dry-run default, Data Pelanggan & Paket SAJA — bukan Tagihan)';

    public function handle(): int
    {
        $pop = $this->resolvePop();
        if (! $pop) {
            $this->error('Pop tidak ditemukan. Isi --pop-id atau --pop-name.');

            return self::FAILURE;
        }

        $filePath = $this->argument('file');
        $fullPath = str_starts_with($filePath, '/') ? $filePath : base_path($filePath);

        if (! file_exists($fullPath)) {
            $this->error("File Excel tidak ditemukan: {$fullPath}");

            return self::FAILURE;
        }

        [$nameAliases, $skipNames, $typoFixes, $manualOverrides] = $this->loadConfig();
        if ($nameAliases === null) {
            return self::FAILURE;
        }

        $terminateMissing = (bool) $this->option('terminate-missing');
        $actor = null;
        if ($terminateMissing) {
            $actorId = $this->option('actor-id');
            $actor = $actorId ? User::find((int) $actorId) : null;
            if (! $actor) {
                $this->error('--terminate-missing butuh --actor-id yang valid (user id pelaku, dicatat di audit log).');

                return self::FAILURE;
            }
        }

        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'MODE APPLY — perubahan akan ditulis ke database.' : 'MODE DRY-RUN — tidak ada perubahan ditulis. Jalankan dengan --apply untuk eksekusi.');

        $changed = [];
        $unchanged = 0;
        $skipped = [];
        // Dipakai `--terminate-missing` — siapa pun yang namanya MUNCUL di
        // Excel (walau gagal dikoreksi otomatis: di-skip config, ambigu >1
        // kandidat) dianggap "masih ada" dan TIDAK boleh ikut diputus. Diisi
        // SELAMA loop ini berjalan (lookup-nya dipindah ke atas pengecekan
        // skip_names), bukan cuma dari baris yang berhasil dikoreksi.
        $presentCustomerIds = [];

        foreach (SimpleExcelReader::create($fullPath)->getRows() as $row) {
            $namaExcel = trim((string) ($row['Nama'] ?? ''));
            $nominal = (int) ($row['Paket'] ?? 0);
            $kodeRaw = trim((string) ($row['paket '] ?? ''));

            if ($namaExcel === '' || $nominal <= 0) {
                continue;
            }

            $resolvedName = $nameAliases[$namaExcel] ?? $namaExcel;

            $candidates = Customer::query()
                ->where('pop_id', $pop->id)
                ->whereRaw('LOWER(TRIM(full_name)) = ?', [mb_strtolower(trim($resolvedName))])
                ->get();

            foreach ($candidates as $candidate) {
                $presentCustomerIds[$candidate->id] = true;
            }

            if (in_array($namaExcel, $skipNames, true)) {
                $skipped[] = [$namaExcel, 'ditandai skip di config — datanya belum bisa dipercaya, perlu ditelusuri manual dulu'];

                continue;
            }

            if ($candidates->count() !== 1) {
                $skipped[] = [$namaExcel, $candidates->count() === 0 ? 'nama ini tidak ditemukan di sistem' : "ada {$candidates->count()} pelanggan dengan nama sama di sistem — perlu dicek manual, nama saja tidak cukup unik"];

                continue;
            }

            $customer = $candidates->first();
            $service = $customer->customerService;

            if (! $service) {
                $skipped[] = [$namaExcel, 'pelanggan ketemu, tapi belum punya data layanan (customer_service) — gak bisa dikoreksi'];

                continue;
            }

            $isDefault = $service->internet_package_id === null
                || mb_strtolower((string) $service->internetPackage?->name) === 'default';

            // Resolve dulu paket target dari KODE Excel (bukan cuma harga) —
            // katalog bisa punya 2 baris harga sama tapi speed/tier beda
            // (temuan nyata: PK000002 "110K Up to 5M" 3Mbps vs NetP110 25Mbps,
            // KEDUANYA 110000 — diskriminator harga-saja salah nyimpulkan tier
            // sudah benar padahal beda tier sama sekali). Kalau kode Excel
            // dikenal katalog, bandingkan ke paket itu lewat HARGA+SPEED, BUKAN
            // id baris katalog — katalog juga punya baris id BEDA tapi
            // harga+speed IDENTIK (mis. "Net198" vs "SPESIAL 200Mbps-198K",
            // keduanya 198000/200Mbps — skema penamaan beda doang, bukan tier
            // beda; ganti id di sini cuma churn tanpa guna, sama kasusnya
            // dengan "NEW 45 Mbps (150K)" vs "Net150").
            $code = $typoFixes[$kodeRaw] ?? $kodeRaw;
            $targetPackage = InternetPackage::where('package_code', $code)->first();

            if ($targetPackage) {
                $current = $service->internetPackage;
                $samePriceAndSpeed = $current
                    && abs((float) $current->monthly_price - (float) $targetPackage->monthly_price) <= 0.004
                    && (float) $current->download_speed_mbps === (float) $targetPackage->download_speed_mbps;
                $packageTierWrong = $isDefault || ! $samePriceAndSpeed;
            } elseif ($isDefault) {
                $skipped[] = [$namaExcel, "paket pelanggan masih kosong, dan kode paket '{$kodeRaw}' di Excel tidak dikenal katalog — perlu di-assign manual dulu"];

                continue;
            } else {
                // Kode Excel tidak dikenal katalog — gak bisa pastikan tier
                // yang benar, fallback ke "cuma betulkan harga" (bandingkan
                // harga paket SAAT INI vs nominal Excel, seperti sebelumnya).
                $currentPackagePrice = $service->internetPackage ? (float) $service->internetPackage->monthly_price : null;
                $packageTierWrong = $currentPackagePrice === null || abs($currentPackagePrice - $nominal) > 0.004;
            }

            if (! $packageTierWrong) {
                $targetPackage = null;
            }

            $oldPrice = (float) $service->monthly_price;
            $newPrice = (float) $nominal;
            $priceChanged = abs($oldPrice - $newPrice) > 0.004;
            $packageChanged = $targetPackage && (int) $service->internet_package_id !== $targetPackage->id;

            if (! $priceChanged && ! $packageChanged) {
                $unchanged++;

                continue;
            }

            $changed[] = [
                $customer->id,
                $customer->full_name,
                number_format($oldPrice, 0, ',', '.'),
                number_format($newPrice, 0, ',', '.'),
                $targetPackage ? $targetPackage->name : ($service->internetPackage?->name ?? '-'),
                'Excel',
            ];

            if ($apply) {
                DB::transaction(fn () => $this->applyCorrection($customer, $service, $targetPackage, $newPrice));
            }
        }

        // manual_overrides — nama yang gak bisa dicocokkan otomatis dari
        // Excel (ambigu/typo parah/gak ada barisnya) tetap lewat SATU command
        // ini, jalur koreksi yang SAMA (applyCorrection(), non-prorate) —
        // lihat docblock kelas ini kenapa "Ganti Paket" UI gak boleh dipakai
        // buat kasus migrasi.
        foreach ($manualOverrides as $cid => $packageCode) {
            $customer = Customer::query()
                ->where('pop_id', $pop->id)
                ->where('cid', $cid)
                ->first();

            if (! $customer) {
                $skipped[] = ["[manual_override] {$cid}", 'CID ini tidak ditemukan di Cabang yang dipilih — cek lagi CID-nya atau Cabang yang dipilih'];

                continue;
            }

            // CID disebut eksplisit di manual_overrides = admin tahu
            // pelanggan ini ADA, walau koreksinya di bawah nanti gagal
            // (service/kode paket invalid) — tetap jangan ikut diputus.
            $presentCustomerIds[$customer->id] = true;

            $service = $customer->customerService;
            if (! $service) {
                $skipped[] = [$customer->full_name." ({$cid})", 'pelanggan ketemu, tapi belum punya data layanan (customer_service) — gak bisa dikoreksi'];

                continue;
            }

            $targetPackage = InternetPackage::where('package_code', $packageCode)->first();
            if (! $targetPackage) {
                $skipped[] = [$customer->full_name." ({$cid})", "kode paket manual_override '{$packageCode}' tidak ada di katalog — cek lagi penulisannya"];

                continue;
            }

            $oldPrice = (float) $service->monthly_price;
            $newPrice = (float) $targetPackage->monthly_price;
            $priceChanged = abs($oldPrice - $newPrice) > 0.004;
            $packageChanged = (int) $service->internet_package_id !== $targetPackage->id;

            if (! $priceChanged && ! $packageChanged) {
                $unchanged++;

                continue;
            }

            $changed[] = [
                $customer->id,
                $customer->full_name,
                number_format($oldPrice, 0, ',', '.'),
                number_format($newPrice, 0, ',', '.'),
                $targetPackage->name,
                'Manual Override',
            ];

            if ($apply) {
                DB::transaction(fn () => $this->applyCorrection($customer, $service, $targetPackage, $newPrice));
            }
        }

        $terminated = [];
        if ($terminateMissing) {
            $candidates = Customer::query()
                ->where('pop_id', $pop->id)
                ->whereNotIn('id', array_keys($presentCustomerIds))
                ->whereIn('status', [WorkflowTransition::ACTIVE->value, WorkflowTransition::SUSPENDED->value])
                ->get();

            foreach ($candidates as $customer) {
                $note = 'Reconcile Excel '.basename($fullPath)." ({$pop->name}, ".now()->toDateString().'): tidak ditemukan di rekap';

                if ($apply) {
                    // `firstOrCreate` baru dijalankan sekarang (bukan di atas
                    // sebelum loop) — supaya dry-run TIDAK meninggalkan baris
                    // master alasan baru di DB walau tidak ada kandidat yang
                    // benar-benar diputus.
                    $reason = CustomerTerminationReason::firstOrCreate(
                        ['name' => self::TERMINATION_REASON_NAME],
                        ['default_penalty_amount' => 0, 'is_active' => true],
                    );

                    try {
                        app(CustomerTerminationService::class)->terminate($customer, [
                            'termination_reason_id' => $reason->id,
                            'termination_note' => $note,
                            'penalty_amount' => 0,
                        ], $actor->id);
                    } catch (Throwable $e) {
                        $skipped[] = [$customer->full_name." ({$customer->id})", 'gagal diputus otomatis: '.$e->getMessage()];

                        continue;
                    }
                }

                $terminated[] = [$customer->id, $customer->full_name];
            }
        }

        if ($changed !== []) {
            $this->table(['Customer ID', 'Nama', 'Harga Lama', 'Harga Baru', 'Paket', 'Sumber'], $changed);
        }

        if ($terminated !== []) {
            $this->newLine();
            $this->warn('Akan Diputus Langganan (Pelanggan Luar Excel):');
            $this->table(['Customer ID', 'Nama'], $terminated);
        }

        if ($skipped !== []) {
            $this->newLine();
            $this->warn('Dilewati (butuh review manual):');
            $this->table(['Nama Excel', 'Alasan'], $skipped);
        }

        $this->newLine();
        $this->info(sprintf(
            '%s: %d diubah, %d sudah cocok (tidak disentuh), %d dilewati, %d diputus langganan.',
            $apply ? 'Selesai' : 'Dry-run selesai',
            count($changed),
            $unchanged,
            count($skipped),
            count($terminated),
        ));

        return self::SUCCESS;
    }

    /**
     * Satu-satunya titik tulis koreksi paket/harga + sinkron `customers`
     * + koreksi invoice stale — dipakai baris Excel MAUPUN `manual_overrides`,
     * supaya dua jalur itu gak pernah menyimpang (lihat docblock kelas).
     */
    private function applyCorrection(Customer $customer, CustomerService $service, ?InternetPackage $targetPackage, float $newPrice): void
    {
        $discount = (float) ($service->discount ?? 0);
        $ppnPercent = (float) ($service->ppn ?? 0);
        $discounted = max(0.0, $newPrice - $discount);
        $totalMonthlyBill = round($discounted * (1 + $ppnPercent / 100), 2);

        $attributes = [
            'monthly_price' => $newPrice,
            'total_monthly_bill' => $totalMonthlyBill,
        ];

        if ($targetPackage) {
            $attributes['internet_package_id'] = $targetPackage->id;
            $attributes['package_name_snapshot'] = $targetPackage->name;
            $attributes['download_speed_snapshot'] = $targetPackage->download_speed_mbps !== null ? $targetPackage->download_speed_mbps.' Mbps' : null;
            $attributes['upload_speed_snapshot'] = $targetPackage->upload_speed_mbps !== null ? $targetPackage->upload_speed_mbps.' Mbps' : null;
        }

        $service->updateQuietly($attributes);

        // `customers.internet_package_id` adalah KOLOM SENDIRI, duplikat
        // dari `customer_services.internet_package_id` — List Pelanggan &
        // Detail Pelanggan baca dari kolom Customer ini, BUKAN dari
        // customer_service (lihat `CustomerPackageService::change()` baris
        // `$customer->update(['internet_package_id' => ...])`, satu-satunya
        // invariant yang menjaga 2 kolom ini sinkron). Lupa update ini =
        // paket di List/Detail tetap kelihatan data lama walau
        // customer_service-nya sudah benar (bug nyata, ketauan user
        // 2026-11-02).
        if ($targetPackage) {
            $customer->updateQuietly(['internet_package_id' => $targetPackage->id]);
        }

        // Invoice BULANAN yang belum dibayar sama sekali (`paid_amount = 0`)
        // bisa udah terbit DULUAN (lewat `billing:generate-monthly-invoices`,
        // jalan sendiri lewat scheduler, bisa bersamaan dengan command ini)
        // pakai `monthly_price` yang BELUM dibetulkan — nominalnya nyangkut
        // stale walau customer_service di atas sudah benar (temuan nyata
        // user 2026-11-02). Disamakan di sini, SATU jalur, TANPA prorate —
        // beda dari `CustomerPackageService::change()` yang sengaja memblend
        // hari lama+baru buat event ganti paket SUNGGUHAN. HANYA invoice
        // belum_dibayar+paid_amount=0 — invoice yang sudah kena bayar
        // sebagian/lunas TIDAK PERNAH disentuh di sini.
        $service->refresh();
        Invoice::where('customer_service_id', $service->id)
            ->where('invoice_type', InvoiceType::BULANAN->value)
            ->where('invoice_status', 'belum_dibayar')
            ->where('paid_amount', 0)
            ->each(function (Invoice $invoice) use ($service) {
                $discount = (float) ($service->discount ?? 0);
                $ppnPercent = (float) ($service->ppn ?? 0);
                $afterDiscount = max(0, (float) $service->monthly_price - $discount);
                $ppnAmount = round($afterDiscount * ($ppnPercent / 100), 2);
                $totalAmount = $afterDiscount + $ppnAmount;

                if (abs((float) $invoice->total_amount - $totalAmount) <= 0.004) {
                    return;
                }

                $invoice->update([
                    'internet_package_id' => $service->internet_package_id,
                    'subtotal' => (float) $service->monthly_price,
                    'discount' => $discount,
                    'ppn' => $ppnPercent,
                    'total_amount' => $totalAmount,
                    'remaining_amount' => $totalAmount,
                ]);

                app(InvoiceItemBuilder::class)->rebuildFor($invoice, [[
                    'category_code' => RevenueCategory::CODE_JASA_LAYANAN_INTERNET,
                    'subcategory_code' => RevenueSubcategory::CODE_LANGGANAN_BULANAN,
                    'description' => "Langganan {$invoice->billing_period}",
                    'amount' => (float) $service->monthly_price,
                ]]);
            });
    }

    private function resolvePop(): ?Pop
    {
        if ($this->option('pop-id')) {
            return Pop::find((int) $this->option('pop-id'));
        }

        if ($this->option('pop-name')) {
            return Pop::where('name', $this->option('pop-name'))->first();
        }

        return null;
    }

    /**
     * @return array{0: array<string, string>|null, 1: list<string>, 2: array<string, string>, 3: array<string, string>}
     */
    private function loadConfig(): array
    {
        $path = $this->option('config');
        if (! $path) {
            return [[], [], [], []];
        }

        $fullPath = str_starts_with($path, '/') ? $path : base_path($path);
        if (! file_exists($fullPath)) {
            $this->error("File config tidak ditemukan: {$fullPath}");

            return [null, [], [], []];
        }

        $data = json_decode((string) file_get_contents($fullPath), true);
        if (! is_array($data)) {
            $this->error("File config bukan JSON valid: {$fullPath}");

            return [null, [], [], []];
        }

        return [
            $data['name_aliases'] ?? [],
            $data['skip_names'] ?? [],
            $data['package_code_typo_fixes'] ?? [],
            // cid => package_code — koreksi manual yang gak bisa dicocokkan
            // otomatis dari Excel, lewat jalur applyCorrection() yang SAMA
            // (non-prorate). Lihat docblock kelas ini.
            $data['manual_overrides'] ?? [],
        ];
    }
}
