<?php

namespace Database\Seeders;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerDevice;
use App\Models\CustomerInstallation;
use App\Models\CustomerService;
use App\Models\CustomerSurvey;
use App\Models\CustomerTechnicalDetail;
use App\Models\Distribution;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskTeam;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Prevent events from overriding our manual status assignments during seeding
        Customer::unsetEventDispatcher();
        CustomerService::unsetEventDispatcher();
        CustomerAddress::unsetEventDispatcher();
        CustomerSurvey::unsetEventDispatcher();
        CustomerInstallation::unsetEventDispatcher();
        CustomerDevice::unsetEventDispatcher();
        CustomerTechnicalDetail::unsetEventDispatcher();
        Task::unsetEventDispatcher();

        // 1. Get region references
        $city = City::query()->where('name', 'Ponorogo')->first() ?? City::query()->first();
        if (! $city) {
            return;
        }

        $districts = District::query()
            ->where('city_id', $city->id)
            ->with('villages')
            ->orderBy('name')
            ->get()
            ->filter(fn (District $district) => $district->villages->isNotEmpty())
            ->values();

        $packages = InternetPackage::query()->where('is_active', true)->orderBy('package_code')->get();

        // If pops table is empty, seed MasterPopSeeder
        if (Pop::query()->count() === 0) {
            $this->call(MasterPopSeeder::class);
        }

        // Safe fetch mini POPs
        $pops = Pop::query()->where('type', 'mini_pop')->where('status', 'active')->orderBy('code')->get();
        if ($pops->isEmpty()) {
            // Fallback to any active POP if no mini_pop is seeded
            $pops = Pop::query()->where('status', 'active')->orderBy('code')->get();
        }
        if ($pops->isEmpty()) {
            $defaultPop = Pop::create([
                'code' => 'C1',
                'pop_code' => 'C1',
                'name' => 'Jetis C1',
                'type' => 'mini_pop',
                'status' => 'active',
                'registration_prefix' => 'RQ',
                'cid_prefix' => 'C',
            ]);
            $pops = collect([$defaultPop]);
        }

        // Partition POPs to alternate between Jetis (starts with C) and Siman (starts with D)
        $jetisPops = $pops->filter(fn ($p) => str_starts_with($p->code, 'C'))->values();
        $simanPops = $pops->filter(fn ($p) => str_starts_with($p->code, 'D'))->values();

        $getPopByIndex = function ($i) use ($pops, $jetisPops, $simanPops) {
            $isJetis = ($i % 2 === 0);
            if ($isJetis && $jetisPops->isNotEmpty()) {
                return $jetisPops[$i % $jetisPops->count()];
            }
            if (! $isJetis && $simanPops->isNotEmpty()) {
                return $simanPops[$i % $simanPops->count()];
            }

            return $pops[$i % $pops->count()];
        };

        if ($districts->isEmpty() || $packages->isEmpty()) {
            return;
        }

        // 2. Ensure technician and FOP roles and users exist
        $roleTeknisi = Role::firstOrCreate(
            ['code' => 'teknisi'],
            [
                'name' => 'Teknisi',
                'guard_name' => 'web',
                'description' => 'Teknisi Lapangan dan Jaringan',
                'is_system' => true,
            ]
        );

        $roleFop = Role::firstOrCreate(
            ['code' => 'fop'],
            [
                'name' => 'FOP',
                'guard_name' => 'web',
                'description' => 'Field Operations',
                'is_system' => true,
            ]
        );

        $technician = User::where('role_id', $roleTeknisi->id)->first();
        if (! $technician) {
            $technician = User::create([
                'name' => 'Teknisi Joko',
                'email' => 'teknisi.joko@whusnet.com',
                'phone' => '081234567891',
                'password' => bcrypt('password'),
                'status' => 'active',
                'role_id' => $roleTeknisi->id,
            ]);
        }

        $fopUser = User::where('role_id', $roleFop->id)->first();
        if (! $fopUser) {
            $fopUser = User::create([
                'name' => 'FOP Rian',
                'email' => 'fop.rian@whusnet.com',
                'phone' => '081234567892',
                'password' => bcrypt('password'),
                'status' => 'active',
                'role_id' => $roleFop->id,
            ]);
        }

        // 3. Clean up existing customer/task/invoice records to prevent duplicate key collisions
        DB::table('task_teams')->delete();
        DB::table('tasks')->delete();
        DB::table('customer_devices')->delete();
        DB::table('customer_technical_details')->delete();
        DB::table('customer_installations')->delete();
        DB::table('customer_surveys')->delete();
        DB::table('invoices')->delete();
        DB::table('customer_services')->delete();
        DB::table('customer_addresses')->delete();
        DB::table('customer_status_logs')->delete();
        DB::table('customer_documents')->delete();
        DB::table('customers')->delete();

        // 4. Data helper setup
        $names = [
            'Budi Santoso', 'Siti Aminah', 'Joko Widodo', 'Dewi Lestari', 'Agus Susanto',
            'Rian Hidayat', 'Mega Utami', 'Adi Nugroho', 'Sri Wahyuni', 'Eko Prasetyo',
            'Indah Permatasari', 'Bambang Hermawan', 'Kartika Sari', 'Hendarto', 'Rina Marlina',
            'Dian Wijaya', 'Slamet Riyadi', 'Yuliana', 'Aris Munandar', 'Fitriani',
            'Heri Setiawan', 'Ani Suryani', 'Dedi Kurniawan', 'Tuti Alawiyah', 'Roni Wijaya',
            'Lilis Suryani', 'Andi Pratama', 'Novianti', 'Edi Sunarto', 'Ratna Sari',
            'Taufik Hidayat', 'Lia Lestari', 'Hendra Setiawan', 'Yuni Shara', 'Ferry Salim',
            'Rani Mukerji', 'Anwar Ibrahim', 'Zulkifli Hasan', 'Prabowo Subianto', 'Megawati',
        ];

        $streetNames = [
            'Jl. Raya Ponorogo', 'Jl. Merdeka', 'Jl. Sudirman', 'Jl. Pahlawan', 'Jl. Diponegoro',
            'Jl. Gajah Mada', 'Jl. HOS Cokroaminoto', 'Jl. Jendral Sudirman', 'Jl. Ahmad Yani',
        ];

        $globalIdx = 1;

        $createCustomerRecord = function ($status, $customerStatus, $completenessStatus, $pop, $district, $village, $package, $regDate, $simple = false) use (&$globalIdx, $names, $streetNames, $city) {
            $name = $names[($globalIdx - 1) % count($names)];
            $emailName = strtolower(str_replace(' ', '.', $name));
            $num = $globalIdx;

            $address = $streetNames[($globalIdx - 1) % count($streetNames)]
                .' No. '.(12 + $globalIdx)
                .', RT '.str_pad((string) (($globalIdx % 9) + 1), 2, '0', STR_PAD_LEFT)
                .'/RW '.str_pad((string) (($globalIdx % 6) + 1), 2, '0', STR_PAD_LEFT)
                .', '.$village->name.', '.$district->name;

            // Generate registration code: e.g. C00RQ000001
            $customerCode = sprintf('%s00%s%06d', $pop->cid_prefix, $pop->registration_prefix, $globalIdx);

            $customer = Customer::create([
                'customer_code' => $customerCode,
                'full_name' => $name,
                'identity_number' => '3502'.str_pad((string) (101990000000 + $num), 12, '0', STR_PAD_LEFT),
                'gender' => $num % 2 === 0 ? 'Perempuan' : 'Laki-laki',
                'email' => $emailName.str_pad((string) $num, 2, '0', STR_PAD_LEFT).'@whusnet.test',
                'primary_phone' => '08'.(12 + ($num % 8)).str_pad((string) (34000000 + ($num * 1379)), 8, '0', STR_PAD_LEFT),
                'registration_date' => $regDate->format('Y-m-d'),
                'status' => $status,
                'data_completeness_status' => $completenessStatus,
                'address' => $address,
                'latitude' => -7.8650000 + ($num * 0.0021000),
                'longitude' => 111.4620000 + ($num * 0.0023000),
                'city_id' => $city->id,
                'district_id' => $district->id,
                'village_id' => $village->id,
                'pop_id' => $pop->id,
                'internet_package_id' => $package->id,
                'contract_period_months' => $package->contract_period_months ?? 12,
                'discount_amount' => $num % 5 === 0 ? 25000 : 0,
                'tax_percent' => 11,
                'sales_code' => 'SLS-PON-'.str_pad((string) (($num % 3) + 1), 3, '0', STR_PAD_LEFT),
                'agent_code' => 'AGT-SMN-'.str_pad((string) (($num % 4) + 1), 3, '0', STR_PAD_LEFT),
                'created_at' => $regDate,
                'updated_at' => now(),
            ]);

            // Create Address Relationship
            if (! $simple) {
                CustomerAddress::create([
                    'customer_id' => $customer->id,
                    'full_address' => $address,
                    'province' => 'Jawa Timur',
                    'city' => $city->name,
                    'district' => $district->name,
                    'village' => $village->name,
                    'city_id' => $city->id,
                    'district_id' => $district->id,
                    'village_id' => $village->id,
                    'latitude' => $customer->latitude,
                    'longitude' => $customer->longitude,
                ]);
            }

            $globalIdx++;

            return $customer;
        };

        $createServiceRecord = function ($customer, $package, $status, $activationDate = null, $dueDate = null) {
            $discount = $customer->discount_amount ?? 0.00;
            $ppnPercent = 11.00;
            $discountedPrice = max(0, $package->monthly_price - $discount);
            $totalBill = $discountedPrice * (1 + $ppnPercent / 100);

            return CustomerService::create([
                'customer_id' => $customer->id,
                'internet_package_id' => $package->id,
                'package_name_snapshot' => $package->name,
                'download_speed_snapshot' => $package->download_speed_mbps.' Mbps',
                'upload_speed_snapshot' => $package->upload_speed_mbps.' Mbps',
                'monthly_price' => $package->monthly_price,
                'discount' => $discount,
                'ppn' => $ppnPercent,
                'total_monthly_bill' => $totalBill,
                'activation_date' => $activationDate,
                'due_date' => $dueDate,
                'billing_cycle' => 'monthly',
                'service_status' => $status,
                'billing_status' => $status === 'aktif' ? 'active' : 'pending',
            ]);
        };

        // --- 4.5 Seed simple registered customers (5 customers) for testing cascade and firstOrFail dependencies ---
        for ($i = 0; $i < 5; $i++) {
            $pop = $getPopByIndex($i);
            $district = $districts[$i % $districts->count()];
            $village = $district->villages->first();
            $package = $packages[$i % $packages->count()];
            $regDate = Carbon::now()->subDays(40 - $i);

            $createCustomerRecord(
                'registered',
                'calon_pelanggan',
                'draft',
                $pop,
                $district,
                $village,
                $package,
                $regDate,
                true // simple = true (no address, no service, no tasks)
            );
        }

        // --- 5. Seed waiting_survey (6 customers) ---
        for ($i = 0; $i < 6; $i++) {
            $pop = $getPopByIndex($i);
            $district = $districts[$i % $districts->count()];
            $village = $district->villages->first();
            $package = $packages[$i % $packages->count()];
            $regDate = Carbon::now()->subDays(6 - $i);

            $customer = $createCustomerRecord(
                'waiting_survey',
                'survey',
                'perlu_dilengkapi',
                $pop,
                $district,
                $village,
                $package,
                $regDate
            );

            $createServiceRecord($customer, $package, 'nonaktif');

            // Default: none scheduled
            $isScheduled = false;

            $task = Task::create([
                'task_number' => sprintf('TASK-%s-SURV-%04d', Carbon::now()->year, $customer->id),
                'task_type' => TaskType::SURVEY->value,
                'title' => 'Survey Pelanggan: '.$customer->full_name,
                'description' => 'Lakukan survey kelayakan jaringan untuk pelanggan '.$customer->full_name,
                'pop_id' => $customer->pop_id,
                'customer_id' => $customer->id,
                'status' => $isScheduled ? TaskStatus::TERJADWAL->value : TaskStatus::PENDING->value,
                'scheduled_at' => $isScheduled ? $regDate->copy()->addDay()->setHour(9)->setMinute(0) : null,
                'created_by' => $fopUser->id,
                'updated_by' => $fopUser->id,
            ]);

            if ($isScheduled) {
                TaskTeam::create([
                    'task_id' => $task->id,
                    'user_id' => $technician->id,
                    'role_in_task' => 'Lead',
                ]);

                CustomerSurvey::create([
                    'customer_id' => $customer->id,
                    'survey_status' => 'pending',
                    'technician_id' => $technician->id,
                    'assigned_at' => $regDate,
                    'fop_id' => $fopUser->id,
                ]);
            }
        }

        // --- 6. Seed waiting_acc (3 customers) ---
        for ($i = 0; $i < 3; $i++) {
            $pop = $getPopByIndex($i);
            $district = $districts[$i % $districts->count()];
            $village = $district->villages->first();
            $package = $packages[$i % $packages->count()];
            $regDate = Carbon::now()->subDays(10 - $i);

            $customer = $createCustomerRecord(
                'waiting_acc',
                'survey',
                'perlu_dilengkapi',
                $pop,
                $district,
                $village,
                $package,
                $regDate
            );

            $createServiceRecord($customer, $package, 'nonaktif');

            // Completed Survey Task
            $task = Task::create([
                'task_number' => sprintf('TASK-%s-SURV-%04d', Carbon::now()->year, $customer->id),
                'task_type' => TaskType::SURVEY->value,
                'title' => 'Survey Pelanggan: '.$customer->full_name,
                'pop_id' => $customer->pop_id,
                'customer_id' => $customer->id,
                'status' => TaskStatus::SELESAI->value,
                'fop_review_status' => 'pending',
                'scheduled_at' => $regDate->copy()->addDay()->setHour(9),
                'started_at' => $regDate->copy()->addDay()->setHour(9)->setMinute(15),
                'completed_at' => $regDate->copy()->addDay()->setHour(10)->setMinute(30),
                'created_by' => $fopUser->id,
                'updated_by' => $fopUser->id,
            ]);

            TaskTeam::create([
                'task_id' => $task->id,
                'user_id' => $technician->id,
                'role_in_task' => 'Lead',
            ]);

            CustomerSurvey::create([
                'customer_id' => $customer->id,
                'survey_status' => 'completed',
                'technician_id' => $technician->id,
                'assigned_at' => $regDate,
                'started_at' => $task->started_at,
                'completed_at' => $task->completed_at,
                'survey_date' => $task->scheduled_at->toDateString(),
                'start_time' => $task->started_at->toTimeString(),
                'end_time' => $task->completed_at->toTimeString(),
                'required_tools' => 'Tang, Splicer, OPM, Dropcore',
                'cable_estimation_meter' => 120,
                'nearest_odp' => 'ODP-PON-'.str_pad((string) (10 + $i), 3, '0', STR_PAD_LEFT),
                'survey_note' => 'Lokasi terjangkau. ODP terdekat aktif, port tersedia. Signal strength -19 dBm. Rekomendasi media: Fiber.',
                'house_photo' => 'documents/simulasi/house.jpg',
                'survey_photo' => 'documents/simulasi/survey.jpg',
                'fop_id' => $fopUser->id,
            ]);
        }

        // --- 7. Seed waiting_installation (6 customers) ---
        for ($i = 0; $i < 6; $i++) {
            $pop = $getPopByIndex($i);
            $district = $districts[$i % $districts->count()];
            $village = $district->villages->first();
            $package = $packages[$i % $packages->count()];
            $regDate = Carbon::now()->subDays(15 - $i);

            $customer = $createCustomerRecord(
                'waiting_installation',
                'menunggu_pemasangan',
                'perlu_dilengkapi',
                $pop,
                $district,
                $village,
                $package,
                $regDate
            );

            $createServiceRecord($customer, $package, 'nonaktif');

            // Approved Survey Task
            $surveyTask = Task::create([
                'task_number' => sprintf('TASK-%s-SURV-%04d', Carbon::now()->year, $customer->id),
                'task_type' => TaskType::SURVEY->value,
                'title' => 'Survey Pelanggan: '.$customer->full_name,
                'pop_id' => $customer->pop_id,
                'customer_id' => $customer->id,
                'status' => TaskStatus::SELESAI->value,
                'fop_review_status' => 'approved',
                'scheduled_at' => $regDate->copy()->addDay()->setHour(9),
                'started_at' => $regDate->copy()->addDay()->setHour(9)->setMinute(15),
                'completed_at' => $regDate->copy()->addDay()->setHour(10)->setMinute(30),
                'created_by' => $fopUser->id,
                'updated_by' => $fopUser->id,
            ]);

            TaskTeam::create([
                'task_id' => $surveyTask->id,
                'user_id' => $technician->id,
                'role_in_task' => 'Lead',
            ]);

            CustomerSurvey::create([
                'customer_id' => $customer->id,
                'survey_status' => 'completed',
                'technician_id' => $technician->id,
                'assigned_at' => $regDate,
                'started_at' => $surveyTask->started_at,
                'completed_at' => $surveyTask->completed_at,
                'survey_date' => $surveyTask->scheduled_at->toDateString(),
                'start_time' => $surveyTask->started_at->toTimeString(),
                'end_time' => $surveyTask->completed_at->toTimeString(),
                'required_tools' => 'Tang, Splicer, OPM, Dropcore',
                'cable_estimation_meter' => 120,
                'nearest_odp' => 'ODP-PON-01',
                'fop_id' => $fopUser->id,
            ]);

            // Default: none scheduled
            $isScheduled = false;

            $installTask = Task::create([
                'task_number' => sprintf('TASK-%s-INST-%04d', Carbon::now()->year, $customer->id),
                'task_type' => TaskType::PEMASANGAN->value,
                'title' => 'Pemasangan Baru: '.$customer->full_name,
                'pop_id' => $customer->pop_id,
                'customer_id' => $customer->id,
                'status' => $isScheduled ? TaskStatus::TERJADWAL->value : TaskStatus::PENDING->value,
                'scheduled_at' => $isScheduled ? $regDate->copy()->addDays(3)->setHour(13)->setMinute(0) : null,
                'created_by' => $fopUser->id,
                'updated_by' => $fopUser->id,
            ]);

            if ($isScheduled) {
                TaskTeam::create([
                    'task_id' => $installTask->id,
                    'user_id' => $technician->id,
                    'role_in_task' => 'Lead',
                ]);
            }

            CustomerInstallation::create([
                'customer_id' => $customer->id,
                'installation_status' => 'scheduled',
                'scheduled_date' => $isScheduled ? $installTask->scheduled_at->toDateString() : null,
                'scheduled_time' => $isScheduled ? $installTask->scheduled_at->toTimeString() : null,
                'technician_id' => $isScheduled ? $technician->id : null,
                'assigned_at' => $regDate->copy()->addDays(2),
                'fop_id' => $fopUser->id,
            ]);
        }

        // --- 8. Seed verification_admin (7 customers) ---
        for ($i = 0; $i < 7; $i++) {
            $pop = $getPopByIndex($i);
            $district = $districts[$i % $districts->count()];
            $village = $district->villages->first();
            $package = $packages[$i % $packages->count()];
            $regDate = Carbon::now()->subDays(20 - $i);

            $customer = $createCustomerRecord(
                'verification_admin',
                'menunggu_pemasangan',
                'lengkap',
                $pop,
                $district,
                $village,
                $package,
                $regDate
            );

            $createServiceRecord($customer, $package, 'nonaktif');

            // Approved Survey Task
            $surveyTask = Task::create([
                'task_number' => sprintf('TASK-%s-SURV-%04d', Carbon::now()->year, $customer->id),
                'task_type' => TaskType::SURVEY->value,
                'title' => 'Survey Pelanggan: '.$customer->full_name,
                'pop_id' => $customer->pop_id,
                'customer_id' => $customer->id,
                'status' => TaskStatus::SELESAI->value,
                'fop_review_status' => 'approved',
                'scheduled_at' => $regDate->copy()->addDay()->setHour(9),
                'started_at' => $regDate->copy()->addDay()->setHour(9)->setMinute(15),
                'completed_at' => $regDate->copy()->addDay()->setHour(10)->setMinute(30),
                'created_by' => $fopUser->id,
                'updated_by' => $fopUser->id,
            ]);

            TaskTeam::create([
                'task_id' => $surveyTask->id,
                'user_id' => $technician->id,
                'role_in_task' => 'Lead',
            ]);

            CustomerSurvey::create([
                'customer_id' => $customer->id,
                'survey_status' => 'completed',
                'technician_id' => $technician->id,
                'assigned_at' => $regDate,
                'started_at' => $surveyTask->started_at,
                'completed_at' => $surveyTask->completed_at,
                'survey_date' => $surveyTask->scheduled_at->toDateString(),
                'start_time' => $surveyTask->started_at->toTimeString(),
                'end_time' => $surveyTask->completed_at->toTimeString(),
                'required_tools' => 'Tang, OPM, Splicer',
                'cable_estimation_meter' => 100,
                'nearest_odp' => 'ODP-PON-02',
                'fop_id' => $fopUser->id,
            ]);

            // Selesai Installation Task (waiting for review)
            $installTask = Task::create([
                'task_number' => sprintf('TASK-%s-INST-%04d', Carbon::now()->year, $customer->id),
                'task_type' => TaskType::PEMASANGAN->value,
                'title' => 'Pemasangan Baru: '.$customer->full_name,
                'pop_id' => $customer->pop_id,
                'customer_id' => $customer->id,
                'status' => TaskStatus::SELESAI->value,
                'fop_review_status' => 'pending',
                'scheduled_at' => $regDate->copy()->addDays(3)->setHour(13)->setMinute(0),
                'started_at' => $regDate->copy()->addDays(3)->setHour(13)->setMinute(15),
                'completed_at' => $regDate->copy()->addDays(3)->setHour(16)->setMinute(0),
                'created_by' => $fopUser->id,
                'updated_by' => $fopUser->id,
            ]);

            TaskTeam::create([
                'task_id' => $installTask->id,
                'user_id' => $technician->id,
                'role_in_task' => 'Lead',
            ]);

            CustomerInstallation::create([
                'customer_id' => $customer->id,
                'installation_status' => 'completed',
                'scheduled_date' => $installTask->scheduled_at->toDateString(),
                'scheduled_time' => $installTask->scheduled_at->toTimeString(),
                'technician_id' => $technician->id,
                'finished_date' => $installTask->completed_at->toDateString(),
                'started_at' => $installTask->started_at,
                'completed_at' => $installTask->completed_at,
                'end_time' => $installTask->completed_at->toTimeString(),
                'installation_photo' => 'documents/simulasi/installation.jpg',
                'contract_photo' => 'documents/simulasi/contract.jpg',
                'signature_photo' => 'documents/simulasi/signature.jpg',
                'installation_note' => 'Instalasi selesai. Kabel rapi, redaman bagus, ONT online dan berfungsi.',
                'assigned_at' => $regDate->copy()->addDays(2),
                'fop_id' => $fopUser->id,
            ]);

            // Device details
            $sn = 'ZTEG'.str_pad((string) (8600000000 + $customer->id), 10, '0', STR_PAD_LEFT);
            $mac = 'AA:BB:CC:DD:EE:'.str_pad(dechex($customer->id), 2, '0', STR_PAD_LEFT);
            $ssid = 'Whusnet_'.str_replace(' ', '', $customer->full_name);

            CustomerDevice::create([
                'customer_id' => $customer->id,
                'device_type' => 'ont',
                'brand' => 'ZTE',
                'model' => 'F609',
                'serial_number' => $sn,
                'mac_address' => $mac,
                'wifi_ssid' => $ssid,
                'wifi_password' => 'password123',
                'connection_mode' => 'pppoe',
                'pppoe_username' => 'pppoe_'.$customer->id,
                'pppoe_password' => 'pass_'.$customer->id,
            ]);

            // Get a real distribution for this mini POP
            $dist = Distribution::where('pop_id', $pop->id)->first();

            CustomerTechnicalDetail::create([
                'customer_id' => $customer->id,
                'ssid' => $ssid,
                'router_mac' => $mac,
                'router_or_ont_serial' => $sn,
                'odp_number' => $dist?->code ?? 'ODP-PON-02',
                'odp_port' => '5',
                'olt_number' => '1',
                'olt_slot' => '2',
                'olt_port' => '3',
                'vlan' => '100',
                'test_upload' => 18.5,
                'test_download' => 19.2,
                'jitter_ms' => 2.1,
                'latency_ms' => 6.0,
                'packet_loss_percent' => 0.0,
                'speedtest_photo' => 'documents/simulasi/speedtest.jpg',
            ]);
        }

        // --- 9. Seed active (5 customers for compatibility with existing tests) ---
        for ($i = 0; $i < 5; $i++) {
            $pop = $getPopByIndex($i);
            $district = $districts[$i % $districts->count()];
            $village = $district->villages->first();
            $package = $packages[$i % $packages->count()];
            $regDate = Carbon::now()->subDays(30 - $i);

            $customer = $createCustomerRecord(
                'active',
                'aktif',
                'siap_billing',
                $pop,
                $district,
                $village,
                $package,
                $regDate
            );

            // Get a real distribution for this mini POP
            $dist = Distribution::where('pop_id', $pop->id)->first();
            $customer->distribution_id = $dist?->id;

            // Set active customer CID
            $customer->cid = $pop->generateComplexCid($customer, $dist);
            $customer->saveQuietly();

            $createServiceRecord(
                $customer,
                $package,
                'aktif',
                $regDate->copy()->addDays(5)->toDateString(),
                $regDate->copy()->addDays(5)->addMonth()->toDateString()
            );

            // Survey Approved Task
            $surveyTask = Task::create([
                'task_number' => sprintf('TASK-%s-SURV-%04d', Carbon::now()->year, $customer->id),
                'task_type' => TaskType::SURVEY->value,
                'title' => 'Survey Pelanggan: '.$customer->full_name,
                'pop_id' => $customer->pop_id,
                'customer_id' => $customer->id,
                'status' => TaskStatus::SELESAI->value,
                'fop_review_status' => 'approved',
                'scheduled_at' => $regDate->copy()->addDay()->setHour(9),
                'started_at' => $regDate->copy()->addDay()->setHour(9)->setMinute(15),
                'completed_at' => $regDate->copy()->addDay()->setHour(10)->setMinute(30),
                'created_by' => $fopUser->id,
                'updated_by' => $fopUser->id,
            ]);

            TaskTeam::create([
                'task_id' => $surveyTask->id,
                'user_id' => $technician->id,
                'role_in_task' => 'Lead',
            ]);

            CustomerSurvey::create([
                'customer_id' => $customer->id,
                'survey_status' => 'completed',
                'technician_id' => $technician->id,
                'assigned_at' => $regDate,
                'started_at' => $surveyTask->started_at,
                'completed_at' => $surveyTask->completed_at,
                'survey_date' => $surveyTask->scheduled_at->toDateString(),
                'start_time' => $surveyTask->started_at->toTimeString(),
                'end_time' => $surveyTask->completed_at->toTimeString(),
                'required_tools' => 'Tang, OPM, Splicer',
                'cable_estimation_meter' => 100,
                'nearest_odp' => 'ODP-PON-02',
                'fop_id' => $fopUser->id,
            ]);

            // Completed Installation Task (FOP approved)
            $installTask = Task::create([
                'task_number' => sprintf('TASK-%s-INST-%04d', Carbon::now()->year, $customer->id),
                'task_type' => TaskType::PEMASANGAN->value,
                'title' => 'Pemasangan Baru: '.$customer->full_name,
                'pop_id' => $customer->pop_id,
                'customer_id' => $customer->id,
                'status' => TaskStatus::SELESAI->value,
                'fop_review_status' => 'approved',
                'scheduled_at' => $regDate->copy()->addDays(3)->setHour(13)->setMinute(0),
                'started_at' => $regDate->copy()->addDays(3)->setHour(13)->setMinute(15),
                'completed_at' => $regDate->copy()->addDays(3)->setHour(16)->setMinute(0),
                'created_by' => $fopUser->id,
                'updated_by' => $fopUser->id,
            ]);

            TaskTeam::create([
                'task_id' => $installTask->id,
                'user_id' => $technician->id,
                'role_in_task' => 'Lead',
            ]);

            CustomerInstallation::create([
                'customer_id' => $customer->id,
                'installation_status' => 'completed',
                'scheduled_date' => $installTask->scheduled_at->toDateString(),
                'scheduled_time' => $installTask->scheduled_at->toTimeString(),
                'technician_id' => $technician->id,
                'finished_date' => $installTask->completed_at->toDateString(),
                'started_at' => $installTask->started_at,
                'completed_at' => $installTask->completed_at,
                'end_time' => $installTask->completed_at->toTimeString(),
                'installation_photo' => 'documents/simulasi/installation.jpg',
                'contract_photo' => 'documents/simulasi/contract.jpg',
                'signature_photo' => 'documents/simulasi/signature.jpg',
                'installation_note' => 'Instalasi selesai and disetujui.',
                'assigned_at' => $regDate->copy()->addDays(2),
                'fop_id' => $fopUser->id,
            ]);

            // Device details
            $sn = 'ZTEG'.str_pad((string) (8600000000 + $customer->id), 10, '0', STR_PAD_LEFT);
            $mac = 'AA:BB:CC:DD:EE:'.str_pad(dechex($customer->id), 2, '0', STR_PAD_LEFT);
            $ssid = 'Whusnet_'.str_replace(' ', '', $customer->full_name);

            CustomerDevice::create([
                'customer_id' => $customer->id,
                'device_type' => 'ont',
                'brand' => 'ZTE',
                'model' => 'F609',
                'serial_number' => $sn,
                'mac_address' => $mac,
                'wifi_ssid' => $ssid,
                'wifi_password' => 'password123',
                'connection_mode' => 'pppoe',
                'pppoe_username' => 'pppoe_'.$customer->id,
                'pppoe_password' => 'pass_'.$customer->id,
            ]);

            CustomerTechnicalDetail::create([
                'customer_id' => $customer->id,
                'ssid' => $ssid,
                'router_mac' => $mac,
                'router_or_ont_serial' => $sn,
                'odp_number' => $dist?->code ?? 'ODP-PON-02',
                'odp_port' => '5',
                'olt_number' => '1',
                'olt_slot' => '2',
                'olt_port' => '3',
                'vlan' => '100',
                'test_upload' => 18.5,
                'test_download' => 19.2,
                'jitter_ms' => 2.1,
                'latency_ms' => 6.0,
                'packet_loss_percent' => 0.0,
                'speedtest_photo' => 'documents/simulasi/speedtest.jpg',
            ]);
        }
    }
}
