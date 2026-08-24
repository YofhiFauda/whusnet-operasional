<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\WorkflowTransition;
use App\Http\Controllers\Concerns\RedirectsToCustomer;
use App\Http\Controllers\Concerns\RendersCustomerList;
use App\Http\Requests\CustomerRegistrationRequest;
use App\Models\AuditLog;
use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerDevice;
use App\Models\CustomerInstallation;
use App\Models\CustomerService;
use App\Models\CustomerStatusLog;
use App\Models\CustomerSurvey;
use App\Models\CustomerTechnicalDetail;
use App\Models\Distribution;
use App\Models\District;
use App\Models\FopTask;
use App\Models\ImportBatch;
use App\Models\ImportError;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Person;
use App\Models\Pop;
use App\Models\PopSequence;
use App\Models\SubscriptionStatus;
use App\Models\Task;
use App\Models\User;
use App\Models\Village;
use App\Notifications\AppNotification;
use App\Services\CustomerValidationService;
use App\Services\CustomerWorkflowService;
use App\Services\EffectiveAccessService;
use App\Services\FileUploadService;
use App\Services\FopTaskProvisioningService;
use App\Services\TelegramBotService;
use App\Services\TicketService;
use App\Support\IndonesianDate;
use App\Support\RupiahInput;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Spatie\SimpleExcel\SimpleExcelReader;
use Spatie\SimpleExcel\SimpleExcelWriter;

class CustomerController extends Controller
{
    use RedirectsToCustomer;
    use RendersCustomerList;

    /**
     * Display a listing of the customers with search and filters.
     *
     * List Pelanggan Putus & List Pelanggan Gagal PUNYA route + permission
     * sendiri sekarang (CustomerTerminatedController / CustomerFailedController)
     * — kalau ada link/bookmark lama yang masih pakai
     * /customers?status_group=terminated|failed, redirect ke route barunya
     * biar permission-nya bener-bener kecek di sana, bukan numpang
     * customers.view di sini.
     */
    public function index(Request $request)
    {
        $statusGroup = trim((string) $request->query('status_group', ''));
        if ($statusGroup === 'terminated') {
            return redirect()->route('customers.terminated');
        }
        if ($statusGroup === 'failed') {
            return redirect()->route('customers.failed');
        }

        return $this->renderCustomerList($request);
    }

    /**
     * Kembalikan pelanggan yang ditolak (rejected) ke status sebelum penolakan,
     * supaya bisa lanjut diproses lagi tanpa daftar ulang dari nol.
     */
    public function restoreFromFailed(Customer $customer)
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.installation.validate'), 403);

        if ($customer->status !== 'rejected') {
            return redirect()->back()->with('error', 'Pelanggan ini tidak dalam status ditolak.');
        }

        $lastRejectLog = AuditLog::where('auditable_type', Customer::class)
            ->where('auditable_id', $customer->id)
            ->where('module', 'Customer Workflow')
            ->where('action', 'status_transition')
            ->whereJsonContains('new_values->status', 'rejected')
            ->orderByDesc('created_at')
            ->first();

        $previousStatus = $lastRejectLog?->old_values['status'] ?? null;
        if (! $previousStatus || ! WorkflowTransition::tryFrom($previousStatus)) {
            return redirect()->back()->with('error', 'Status sebelum penolakan tidak ditemukan, tidak bisa dikembalikan otomatis.');
        }

        DB::transaction(function () use ($customer, $previousStatus) {
            $oldStatus = $customer->status;
            $customer->update(['status' => $previousStatus]);

            AuditLog::create([
                'user_id' => auth()->id(),
                'module' => 'Customer Workflow',
                'action' => 'status_restore',
                'auditable_type' => Customer::class,
                'auditable_id' => $customer->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => $previousStatus, 'note' => 'Dikembalikan dari Ditolak'],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);
        });

        // Arahkan ke halaman detail, bukan redirect()->back() ke daftar "Pelanggan
        // Gagal". Pelanggan yang dikembalikan sudah keluar dari daftar itu, jadi
        // kalau balik ke sana user melihatnya "menghilang". Landing di detail bikin
        // pelanggannya langsung kelihatan, plus pesan menyebut tahap tujuannya biar
        // jelas ke mana dia pindah (dan di tab mana bisa dicari lagi).
        $statusLabel = SubscriptionStatus::where('code', $previousStatus)->value('name') ?? $previousStatus;

        return $this->redirectToCustomer($customer)
            ->with('success', "Pelanggan dikembalikan ke tahap \"{$statusLabel}\" dan bisa dilanjutkan prosesnya.");
    }

    /**
     * Ajukan pengambilan alat pelanggan putus langganan — bikin Task FOP
     * kategori Ambil Modem (DEAC), bukan langsung tandai `device_retrieved_at`.
     * FOP assign teknisi lewat /fop-tasks seperti biasa; `device_retrieved_at`
     * baru keisi otomatis setelah teknisi menyelesaikan task itu (lihat
     * TaskService::complete()). Alurnya sengaja disamakan dengan MTN/C-REQ
     * (detail + pelaporan lewat pipeline Task FOP yang sama).
     */
    public function retrieveDevice(Customer $customer, TicketService $ticketService)
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.devices.retrieve'), 403);

        if ($customer->status !== 'terminated') {
            return redirect()->back()->with('error', 'Pelanggan ini tidak dalam status putus langganan.');
        }

        $device = $customer->customerDevice;
        if (! $device) {
            return redirect()->back()->with('error', 'Data alat pelanggan tidak ditemukan.');
        }

        if ($device->device_retrieved_at) {
            return redirect()->back()->with('error', 'Alat pelanggan ini sudah ditandai diambil.');
        }

        if (! $customer->pop_id || ! $customer->village_id) {
            return redirect()->back()->with('error', 'Pelanggan ini belum lengkap POP/Desa — lengkapi dulu datanya sebelum membuat task pengambilan alat.');
        }

        $hasOpenTask = FopTask::where('customer_id', $customer->id)
            ->where('category', TaskType::AMBIL_MODEM->value)
            ->whereNotIn('status', [TaskStatus::SELESAI->value, TaskStatus::DIBATALKAN->value])
            ->exists();

        if ($hasOpenTask) {
            return redirect()->back()->with('error', 'Sudah ada task pengambilan alat yang masih berjalan untuk pelanggan ini.');
        }

        $fopTask = $ticketService->createDeviceRetrievalTask($customer, auth()->user());

        return redirect()->back()->with('success', "Task FOP {$fopTask->task_number} untuk pengambilan alat berhasil dibuat. Alat akan otomatis ditandai diambil setelah teknisi menyelesaikan task.");
    }

    /**
     * Aktifkan kembali pelanggan yang putus langganan ("Langganan Lagi"),
     * langsung ke status active tanpa lewat survey/verifikasi ulang.
     */
    public function reactivate(Customer $customer)
    {
        abort_unless(auth()->user()->hasPermission('customers.detail.installation.validate'), 403);

        if ($customer->status !== 'terminated') {
            return redirect()->back()->with('error', 'Pelanggan ini tidak dalam status putus langganan.');
        }

        DB::transaction(function () use ($customer) {
            $customer->update(['status' => 'active']);

            if ($customer->customerService) {
                $customer->customerService->update(['service_status' => 'aktif']);
            }

            AuditLog::create([
                'user_id' => auth()->id(),
                'module' => 'customers',
                'action' => 'reactivate',
                'auditable_type' => Customer::class,
                'auditable_id' => $customer->id,
                'old_values' => ['status' => 'terminated'],
                'new_values' => ['status' => 'active', 'note' => 'Langganan Lagi'],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);
        });

        return redirect()->back()->with('success', 'Pelanggan berhasil diaktifkan kembali.');
    }

    /**
     * Show the form for creating a new customer.
     */
    public function create()
    {
        // Fase 5.4 — $districts TIDAK dimuat: form create memakai cascade async
        // (city → /api/cities/{city}/districts → /api/districts/{d}/villages),
        // jadi memuat SELURUH district di sini sia-sia (dead weight yang meledak
        // saat wilayah bertambah). $cities dipertahankan — top-level, kecil,
        // memang dirender.
        $packages = InternetPackage::orderBy('name')->get();
        $cities = City::orderBy('name')->get();
        $pops = Pop::forUser()->where('type', 'cabang')->get();

        return view('customers.create', compact('packages', 'cities', 'pops'));
    }

    /**
     * Store a newly created customer in storage.
     */
    public function store(CustomerRegistrationRequest $request)
    {
        $validated = $request->validated();

        // Skip Survey — CustomerRegistrationRequest::authorize() sudah nolak
        // (403) kalau field ini truthy tapi actor gak punya permission
        // customers.registration.skip_survey, jadi di titik ini boolean-nya
        // sudah aman dipercaya.
        $skipSurvey = (bool) ($validated['skip_survey'] ?? false);
        unset($validated['skip_survey']);

        $validated['created_by'] = auth()->id();
        // Skip Survey lompat langsung ke antrean ACC Admin — pelanggan gak
        // pernah masuk antrean Survey teknisi sama sekali (lihat blok 5 di
        // bawah yang di-skip kalau $skipSurvey).
        $validated['status'] = $skipSurvey ? 'waiting_acc' : 'waiting_survey';
        $validated['updated_by'] = auth()->id();

        $statusMapping = [
            'active' => 'aktif',
            'suspended' => 'isolir',
            'terminated' => 'berhenti',
            'rejected' => 'nonaktif',
            'waiting_survey' => 'survey',
            'surveyed' => 'survey',
            // Skip Survey mendarat di sini langsung dari registrasi — treat
            // sama kayak 'surveyed', survey-nya (versi Sales) sudah selesai.
            'waiting_acc' => 'survey',
            'waiting_installation' => 'menunggu_pemasangan',
            'installed' => 'menunggu_pemasangan',
            'registered' => 'calon_pelanggan',
        ];
        // Nilai turunan status buat customer_services.service_status. Dulu
        // disimpan di kolom customers.customer_status (zombie — duplikat `status`
        // yang gampang menyimpang). Sekarang variabel lokal, tak dipersist ke
        // customers. Sumber kebenaran service_status = customer_services.
        $serviceStatus = $statusMapping[$validated['status']] ?? 'calon_pelanggan';

        $fotoRumah = $request->file('foto_rumah');
        unset($validated['foto_rumah']);
        $fotoKontrak = $request->file('foto_kontrak');
        unset($validated['foto_kontrak']);

        // Data survey (Skip Survey) — bukan kolom customers, ditangani
        // terpisah di blok 5 lewat CustomerSurvey::create().
        $surveyPhoto = $request->file('survey_photo');
        $nearestOdp = $validated['nearest_odp'] ?? null;
        $cableEstimationMeter = $validated['cable_estimation_meter'] ?? null;
        $difficultyLevel = $validated['difficulty_level'] ?? null;
        $requestedInstallationDate = $validated['requested_installation_date'] ?? null;
        unset($validated['survey_photo'], $validated['nearest_odp'], $validated['cable_estimation_meter'], $validated['difficulty_level'], $validated['requested_installation_date']);

        // Generate customer_code via POP sequence generator
        $pop = Pop::findOrFail($validated['pop_id']);
        $customerCode = $pop->generateRegistrationNumber();
        $validated['customer_code'] = $customerCode;

        $customer = DB::transaction(function () use ($validated, $serviceStatus, $fotoRumah, $fotoKontrak, $skipSurvey, $surveyPhoto, $nearestOdp, $cableEstimationMeter, $difficultyLevel, $requestedInstallationDate) {
            // Pendaftaran baru lewat UI = orang baru → person baru berdiri sendiri
            // (tanpa legacy_key). Pencarian "mungkin orang yang sama?" saat
            // registrasi adalah pekerjaan gel.2; di sini cukup jaga invarian
            // "tiap customer punya person".
            $validated['person_id'] = Person::create()->id;

            // 1. Create customer record
            $customer = Customer::create($validated);

            $updates = [];
            if ($fotoRumah instanceof UploadedFile) {
                $updates['foto_rumah'] = FileUploadService::uploadSurveyPhoto($fotoRumah, $customer, 'house');
            }
            if ($fotoKontrak instanceof UploadedFile) {
                $updates['foto_kontrak'] = FileUploadService::uploadInstallationPhoto($fotoKontrak, $customer, 'kontrak');
            }
            if (! empty($updates)) {
                $customer->update($updates);
            }

            // 2. Create customer address
            $cityName = null;
            if (! empty($validated['city_id'])) {
                $cityName = City::where('id', $validated['city_id'])->value('name');
            }
            $districtName = null;
            if (! empty($validated['district_id'])) {
                $districtName = District::where('id', $validated['district_id'])->value('name');
            }
            $villageName = null;
            if (! empty($validated['village_id'])) {
                $villageName = Village::where('id', $validated['village_id'])->value('name');
            }

            $customer->customerAddress()->create([
                'full_address' => $validated['address'] ?? null,
                'province' => 'Jawa Timur',
                'city' => $cityName,
                'district' => $districtName,
                'village' => $villageName,
                'city_id' => $validated['city_id'] ?? null,
                'district_id' => $validated['district_id'] ?? null,
                'village_id' => $validated['village_id'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'house_photo' => $validated['foto_rumah'] ?? null,
                'contract_photo' => $validated['foto_kontrak'] ?? null,
            ]);

            // 3. Create customer service if package is chosen
            if (! empty($validated['internet_package_id'])) {
                $package = InternetPackage::findOrFail($validated['internet_package_id']);

                $monthlyPrice = (float) $package->monthly_price;
                $discount = (float) ($validated['discount_amount'] ?? 0.00);
                $ppn = (float) ($validated['tax_percent'] ?? 0.00);
                $otherFee = (float) ($validated['other_fee'] ?? 0.00);

                // Calculate total bill
                $discountedPrice = max(0, $monthlyPrice - $discount);
                $totalBill = $discountedPrice * (1 + $ppn / 100) + $otherFee;

                $downLabel = isset($package->download_speed_mbps) ? $package->download_speed_mbps.' Mbps' : null;
                $upLabel = isset($package->upload_speed_mbps) ? $package->upload_speed_mbps.' Mbps' : null;

                $activationDate = $validated['registration_date'] ?? null;
                $dueDate = null;
                if ($activationDate) {
                    $dueDate = Carbon::parse($activationDate)->addMonth()->format('Y-m-d');
                }

                $customer->customerService()->create([
                    'internet_package_id' => $package->id,
                    'package_name_snapshot' => $package->name,
                    'download_speed_snapshot' => $downLabel,
                    'upload_speed_snapshot' => $upLabel,
                    'monthly_price' => $monthlyPrice,
                    'discount' => $discount,
                    'ppn' => $ppn,
                    'other_fee' => $otherFee,
                    'total_monthly_bill' => $totalBill,
                    'activation_date' => $activationDate,
                    'due_date' => $dueDate,
                    'billing_cycle' => 'monthly',
                    'service_status' => $serviceStatus,
                    'billing_status' => ($validated['status'] === 'active' || $serviceStatus === 'aktif') ? 'active' : 'pending',
                ]);
            }

            // 4. Evaluate data completeness via service and flash warning to user
            $customer->load('customerService');
            /** @var CustomerValidationService $validationService */
            $validationService = app(CustomerValidationService::class);
            $completenessResult = $validationService->validate($customer);

            if (! empty($completenessResult['missing_required'])) {
                $missingLabels = array_values($completenessResult['missing_required']);
                session()->flash('warning', 'Data pelanggan disimpan sebagai "'.ucwords(str_replace('_', ' ', $completenessResult['completeness_status'])).'", tetapi masih memerlukan data berikut agar Lengkap: '.implode(', ', $missingLabels));
            }

            if ($skipSurvey) {
                // 5. Skip Survey — Sales sudah input data survey lengkap di form
                //    registrasi. TIDAK ada Task/FopTask SURVEY yang lahir sama
                //    sekali (gak ada teknisi yang perlu disurvei-tugaskan), dan
                //    pelanggan langsung nangkring di antrean ACC Admin lewat
                //    status waiting_acc yang sudah di-set di atas.
                $surveyPhotoPath = null;
                if ($surveyPhoto instanceof UploadedFile) {
                    $surveyPhotoPath = FileUploadService::uploadSurveyPhoto($surveyPhoto, $customer, 'odp');
                }

                $note = $difficultyLevel ? ('Tingkat Kesulitan: '.$difficultyLevel) : '';
                $note .= ($note ? "\n" : '').'Catatan: Diinput oleh Sales saat Registrasi (Skip Survey).';

                CustomerSurvey::create([
                    'customer_id' => $customer->id,
                    'survey_status' => 'completed',
                    'nearest_odp' => $nearestOdp,
                    'cable_estimation_meter' => $cableEstimationMeter,
                    // uploadSurveyPhoto('house') di atas nulis ke folder yang sama
                    // persis dipakai Laporan Survey teknisi — path-nya reuse, bukan
                    // upload dobel.
                    'house_photo' => $updates['foto_rumah'] ?? null,
                    'survey_photo' => $surveyPhotoPath,
                    'survey_note' => $note,
                    'technician_id' => auth()->id(),
                    'requested_installation_date' => $requestedInstallationDate,
                ]);
            } else {
                // 5. Sentralisasi Tiket: Auto-create Task antrean (Survey) + FopTask
                //    anchor-nya. FopTask dibuat di sini, bukan menunggu papan
                //    /fop-tasks dibuka: dia anchor wajib task_materials &
                //    task_work_tools, dan tanpa itu isian estimasi material serta
                //    checklist alat di laporan survey hilang tanpa pesan error.
                $year = date('Y');
                $count = Task::whereYear('created_at', $year)->count() + 1;
                Task::create([
                    'task_number' => sprintf('TASK-%s-%04d', $year, $count),
                    'task_type' => TaskType::SURVEY->value,
                    'title' => 'Survey Calon Pelanggan: '.$customer->full_name,
                    'description' => null,
                    'pop_id' => $customer->pop_id,
                    'customer_id' => $customer->id,
                    'status' => TaskStatus::PENDING->value,
                    'created_by' => auth()->id() ?? 1,
                    'updated_by' => auth()->id() ?? 1,
                ]);

                app(FopTaskProvisioningService::class)->ensureForCustomer($customer, TaskType::SURVEY);
            }

            return $customer;
        });

        // Landing di detail (bukan list) setelah registrasi: registrasi = awal
        // workflow pelanggan (draft → survey → verifikasi), jadi user biasanya
        // lanjut kerja di record yang baru dibuat (lengkapi data, assign survey).
        // Sekalian menyeragamkan dengan update/ticket/task yang sudah ke detail.
        //
        // TAPI cuma kalau actor punya customers.detail.view — permission itu
        // independen dari customers.create (mis. Sales input-only lewat Role
        // Matrix). Tanpa cek ini, submit sukses tapi langsung ke-403 karena
        // redirect buta ke customers.show (dead end, data padahal tersimpan).
        // Fallback: balik ke form registrasi kosong, siap input berikutnya.
        return $this->redirectToCustomer($customer, 'customers.create')
            ->with('success', "Pelanggan {$validated['full_name']} berhasil ditambahkan dengan ID REG {$customerCode}!");
    }

    /**
     * Assign a survey to a technician and transition status to waiting_survey.
     */
    public function assignSurvey(Request $request, Customer $customer, CustomerWorkflowService $workflowService)
    {
        abort_unless(auth()->user()->hasPermission('customers.update'), 403);

        $validated = $request->validate([
            'technician_id' => 'required|exists:users,id',
            'scheduled_date' => 'required|date',
            'note' => 'nullable|string',
        ]);

        DB::transaction(function () use ($customer, $validated, $workflowService) {
            // Create the initial customer_surveys record with pending status
            CustomerSurvey::create([
                'customer_id' => $customer->id,
                'technician_id' => $validated['technician_id'],
                'survey_date' => $validated['scheduled_date'],
                'assigned_at' => now(),
                'survey_status' => 'pending',
                'survey_note' => $validated['note'] ?? null,
            ]);

            // Transition customer status
            $workflowService->transition($customer, WorkflowTransition::WAITING_SURVEY, 'Assigned to survey');
        });

        return redirect()->back()->with('success', 'Pelanggan berhasil di-assign ke tim survey.');
    }

    /**
     * Show the form for editing the specified customer.
     */
    public function edit(Customer $customer)
    {
        if (! $customer->exists) {
            $customer = Customer::findOrFail(request()->route('customer'));
        }

        $this->authorizeCustomerPopScope($customer);

        // Fase 5.4 — $districts dead weight (form edit pakai cascade async). Buang.
        $packages = InternetPackage::orderBy('name')->get();
        $cities = City::orderBy('name')->get();
        $pops = Pop::forUser()->where('type', 'cabang')->get();
        $distributions = Distribution::orderBy('code')->get();

        return view('customers.edit', compact('customer', 'packages', 'cities', 'pops', 'distributions'));
    }

    /**
     * Update the specified customer in storage.
     */
    public function update(Request $request, Customer $customer)
    {
        // Ensure customer is resolved even if route model binding is bypassed in tests
        if (! $customer->exists) {
            $customer = Customer::findOrFail($request->route('customer'));
        }

        $this->authorizeCustomerPopScope($customer);

        // Sama seperti jalur registrasi (CustomerRegistrationRequest): kolom
        // rupiah diketik berformat ribuan. `tax_percent` bukan rupiah, jadi
        // sengaja dilewat.
        $request->merge(RupiahInput::parseKeys(
            $request->only(['discount_amount', 'other_fee']),
            'discount_amount',
            'other_fee',
        ));

        $validated = $request->validate([
            'full_name' => 'required|string|max:150',
            'identity_number' => 'nullable|string|max:50',
            'gender' => 'nullable|string|max:20',
            'primary_phone' => 'required|string|max:20',
            'alternative_phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'registration_date' => 'required|date',
            'pop_id' => 'required|exists:pops,id',
            'distribution_id' => 'nullable|exists:distributions,id',
            'address' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'city_id' => 'nullable|exists:cities,id',
            'district_id' => 'nullable|exists:districts,id',
            'village_id' => 'nullable|exists:villages,id',
            'internet_package_id' => 'nullable|exists:internet_packages,id',
            'contract_period_months' => 'nullable|integer|min:1',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_percent' => 'nullable|numeric|between:0,100',
            'other_fee' => 'nullable|numeric|min:0',

            // Referrals
            'sales_code' => 'nullable|string|max:30',
            'agent_code' => 'nullable|string|max:30',
            'referral_customer_code' => 'nullable|string|max:30',

            // Technical specs
            'ont_sn' => 'nullable|string|max:100',
            'odp_code' => 'nullable|string|max:50',
            'olt_code' => 'nullable|string|max:50',
            'vlan_id' => 'nullable|string|max:20',

            // Status
            'status' => 'required|string|max:50',
        ]);

        $validated['updated_by'] = auth()->id();

        $statusMapping = [
            'active' => 'aktif',
            'suspended' => 'isolir',
            'terminated' => 'berhenti',
            'rejected' => 'nonaktif',
            'waiting_survey' => 'survey',
            'surveyed' => 'survey',
            'waiting_installation' => 'menunggu_pemasangan',
            'installed' => 'menunggu_pemasangan',
            'registered' => 'calon_pelanggan',
        ];
        // Lokal, tak dipersist ke customers (kolom customer_status di-drop).
        $serviceStatus = $statusMapping[$validated['status']] ?? 'calon_pelanggan';

        $originalFiles = Customer::query()
            ->whereKey($customer->getKey())
            ->first(['foto_rumah', 'foto_kontrak'])
            ?->only(['foto_rumah', 'foto_kontrak']) ?? [
                'foto_rumah' => null,
                'foto_kontrak' => null,
            ];

        // Handle deletions
        if ($request->input('delete_foto_rumah') == '1') {
            $validated['foto_rumah'] = null;
        } else {
            $validated['foto_rumah'] = $customer->foto_rumah;
        }
        if ($request->input('delete_foto_kontrak') == '1') {
            $validated['foto_kontrak'] = null;
        } else {
            $validated['foto_kontrak'] = $customer->foto_kontrak;
        }

        // Handle new uploads
        if ($request->hasFile('foto_rumah')) {
            $validated['foto_rumah'] = FileUploadService::uploadSurveyPhoto($request->file('foto_rumah'), $customer, 'house');
        }
        if ($request->hasFile('foto_kontrak')) {
            $validated['foto_kontrak'] = FileUploadService::uploadInstallationPhoto($request->file('foto_kontrak'), $customer, 'kontrak');
        }

        DB::transaction(function () use ($customer, $validated, $serviceStatus) {
            // 1. Update customer record
            $customer->update($validated);

            // 1b. Auto-generate / update CID berdasarkan status pelanggan
            // Sesuai spesifikasi-pop-distribusi-cid.md:
            // - Status active/suspended: generate CID lengkap jika ada distribusi,
            //   atau set ke format C00RQ###### jika belum ada distribusi
            // - Status terminated: hapus cid (kembali tampilkan REQ ID murni)
            $newStatus = strtolower((string) ($validated['status'] ?? ''));
            $pop = $customer->pop;

            if ($pop && in_array($newStatus, ['active', 'suspended'], true)) {
                $distribution = $customer->distribution;
                if ($distribution) {
                    // Ada distribusi → generate CID lengkap
                    $customer->load(['village', 'customerTechnicalDetail']);
                    $newCid = $pop->generateComplexCid($customer, $distribution);
                } else {
                    // Belum ada distribusi → format default C00RQ######
                    $reqId = $pop->extractBareRegistrationId($customer->customer_code);
                    $newCid = sprintf('%s00%s', $pop->cid_prefix, $reqId);
                }

                if ($customer->cid !== $newCid) {
                    $customer->updateQuietly(['cid' => $newCid]);
                }
            } elseif ($newStatus === 'terminated') {
                // Terminate: cid tidak lagi aktif, tapi simpan sebagai histori (jangan hapus)
                // Display akan dikembalikan ke REQ ID murni via display_id accessor
                // Tidak perlu update kolom cid
            }

            // 2. Update address record
            $cityName = null;
            if (! empty($validated['city_id'])) {
                $cityName = City::where('id', $validated['city_id'])->value('name');
            }
            $districtName = null;
            if (! empty($validated['district_id'])) {
                $districtName = District::where('id', $validated['district_id'])->value('name');
            }
            $villageName = null;
            if (! empty($validated['village_id'])) {
                $villageName = Village::where('id', $validated['village_id'])->value('name');
            }

            $customer->customerAddress()->updateOrCreate([], [
                'full_address' => $validated['address'] ?? null,
                'province' => 'Jawa Timur',
                'city' => $cityName,
                'district' => $districtName,
                'village' => $villageName,
                'city_id' => $validated['city_id'] ?? null,
                'district_id' => $validated['district_id'] ?? null,
                'village_id' => $validated['village_id'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'house_photo' => $validated['foto_rumah'] ?? null,
                'contract_photo' => $validated['foto_kontrak'] ?? null,
            ]);

            // 3. Update customer service
            if (! empty($validated['internet_package_id'])) {
                $package = InternetPackage::findOrFail($validated['internet_package_id']);

                $monthlyPrice = (float) $package->monthly_price;
                $discount = (float) ($validated['discount_amount'] ?? 0.00);
                $ppn = (float) ($validated['tax_percent'] ?? 0.00);
                $otherFee = (float) ($validated['other_fee'] ?? 0.00);

                // Calculate total bill
                $discountedPrice = max(0, $monthlyPrice - $discount);
                $totalBill = $discountedPrice * (1 + $ppn / 100) + $otherFee;

                $downLabel = isset($package->download_speed_mbps) ? $package->download_speed_mbps.' Mbps' : null;
                $upLabel = isset($package->upload_speed_mbps) ? $package->upload_speed_mbps.' Mbps' : null;

                $activationDate = $validated['registration_date'] ?? null;
                $dueDate = null;
                if ($activationDate) {
                    $dueDate = Carbon::parse($activationDate)->addMonth()->format('Y-m-d');
                }

                $customer->customerService()->updateOrCreate([], [
                    'internet_package_id' => $package->id,
                    'package_name_snapshot' => $package->name,
                    'download_speed_snapshot' => $downLabel,
                    'upload_speed_snapshot' => $upLabel,
                    'monthly_price' => $monthlyPrice,
                    'discount' => $discount,
                    'ppn' => $ppn,
                    'other_fee' => $otherFee,
                    'total_monthly_bill' => $totalBill,
                    'activation_date' => $activationDate,
                    'due_date' => $dueDate,
                    'billing_cycle' => 'monthly',
                    'service_status' => $serviceStatus,
                    'billing_status' => ($validated['status'] === 'active' || $serviceStatus === 'aktif') ? 'active' : 'pending',
                ]);
            } else {
                $customer->customerService()->delete();
            }

            // 4. Evaluate data completeness via service and flash warning to user
            $customer->load('customerService');
            /** @var CustomerValidationService $validationService */
            $validationService = app(CustomerValidationService::class);
            $completenessResult = $validationService->validate($customer);

            if (! empty($completenessResult['missing_required'])) {
                $missingLabels = array_values($completenessResult['missing_required']);
                session()->flash('warning', 'Data pelanggan berhasil diperbarui dengan status "'.ucwords(str_replace('_', ' ', $completenessResult['completeness_status'])).'", tetapi masih memerlukan data berikut agar Lengkap: '.implode(', ', $missingLabels));
            }
        });

        foreach ($originalFiles as $field => $path) {
            if ($path && $path !== ($validated[$field] ?? null)) {
                $disk = Storage::disk('public');
                $disk->delete($path);

                $absolutePath = $disk->path($path);
                if (File::isFile($absolutePath)) {
                    File::delete($absolutePath);
                }
            }
        }

        return $this->redirectToCustomer($customer)->with('success', "Data pelanggan {$customer->full_name} berhasil diperbarui!");
    }

    /**
     * Remove the specified customer from storage.
     */
    public function destroy(Customer $customer)
    {
        abort_unless(auth()->user()->hasPermission('customers.delete'), 403);
        $this->authorizeCustomerPopScope($customer);

        DB::transaction(function () use ($customer) {
            $customer->delete();
        });

        return redirect()->back()->with('success', 'Data pelanggan berhasil dihapus!');
    }

    public function show(Customer $customer)
    {
        if (! $customer->exists) {
            $customer = Customer::findOrFail(request()->route('customer'));
        }

        $this->authorizeCustomerPopScope($customer);

        $customer->load([
            'city',
            'district',
            'village',
            'internetPackage',
            'subscriptionStatus',
            'pop',
            'collector',
            'customerAddress',
            'customerService',
            'customerTechnicalDetail',
            // .role ikut dimuat: tabel "Ringkasan Waktu & Penanggung Jawab" di tab
            // Ringkasan menampilkan role tiap PIC.
            'creator.role',
            'updater',
            'customerService.activatedBy.role',
            'surveys.technician.role',
            'surveys.surveyor2',
            'surveys.surveyor3',
            'installations.technician.role',
            'installations.technician2',
            'installations.technician3',
            'customerDevice',
            'documents.uploader',
            'invoices' => function ($query) {
                $query->with('creator')->orderBy('billing_period', 'desc');
            },
            'payments' => function ($query) {
                $query->with(['invoice', 'receiver'])->latest('payment_date')->latest('id');
            },
        ]);

        $status = $customer->status;
        $regDate = Carbon::parse($customer->registration_date);

        // Dynamic completeness calculation
        $completeness = $customer->dataCompleteness();

        // Format display ID berdasarkan siklus hidup pelanggan
        // menggunakan accessor display_id di Customer model (sesuai spesifikasi-pop-distribusi-cid.md):
        // - REQ ID murni (RQ######): pending, survey, pemasangan, installed, terminated
        // - CID lengkap (D2X6CRQ######_DESA_NAMA): aktif + punya distribusi
        // - Format default (C00RQ######): aktif tanpa distribusi
        // loadMissing(): `pop` sudah termasuk di load() 17-relasi beberapa baris di
        // atas, dan load() akan menembak DB lagi untuk relasi yang sudah dimuat.
        $customer->loadMissing(['pop', 'distribution', 'miniPop']); // pastikan relasi dimuat untuk accessor
        $displayId = $customer->display_id;
        $displayIdLabel = $customer->display_id_label;

        // Mini POP (OLT) di bawah Cabang POP customer ini — buat modal assignment
        // Mini POP + Distribusi pasca pemasangan/aktivasi (lihat CustomerNetworkAssignmentController).
        $availableMiniPops = $customer->pop_id
            ? Pop::where('parent_id', $customer->pop_id)
                ->where('type', 'mini_pop')
                ->orderBy('name')
                ->get(['id', 'name', 'pop_code'])
            : collect();

        $availableDistributions = Distribution::whereIn('pop_id', $availableMiniPops->pluck('id'))
            ->orderBy('code')
            ->get(['id', 'pop_id', 'code', 'name']);

        // Keep backward compat for views that still reference $isCustomer / $isActive
        $isActive = in_array($status, ['active', 'suspended']);
        $isCustomer = $isActive;

        // Determine current status rank for timeline
        $statusRank = match ($status) {
            'registered' => 1,
            'waiting_survey' => 2,
            'surveyed' => 3,
            'waiting_installation' => 4,
            'installed' => 5,
            'active', 'suspended', 'terminated' => 6,
            default => 1,
        };

        // Timeline & tabel "Ringkasan Waktu + PIC" di tab Ringkasan.
        //
        // Sebelumnya blok ini MENGARANG tanggal (regDate->addDays(2)/addDays(4))
        // dan menempel angka literal ("dropcore 85m", "Redaman awal -17.80 dBm")
        // untuk SEMUA pelanggan. Data karangan di halaman detail lebih berbahaya
        // daripada kolom kosong — petugas tidak bisa membedakan mana tanggal
        // survey sungguhan dan mana tanggal hasil aritmetika. Sekarang murni dari
        // customer_surveys / customer_installations / customer_services; kalau
        // record-nya belum ada, tanggalnya '-'.
        $latestSurvey = $customer->surveys()->latest('id')->first();
        $latestInstallation = $customer->installations()->latest('id')->first();
        $service = $customer->customerService;

        $surveyDate = $latestSurvey?->end_date ?? $latestSurvey?->survey_date;
        $installationDate = $latestInstallation?->finished_date ?? $latestInstallation?->scheduled_date;
        $activationDate = $service?->activation_date;

        $timeline = [
            [
                'step' => 'Registrasi',
                'title' => 'Pendaftaran Pelanggan',
                'date' => IndonesianDate::date($regDate),
                'notes' => 'Kode registrasi '.$customer->customer_code,
                'status' => 'completed',
            ],
            [
                'step' => 'Survey',
                'title' => 'Survey Lokasi & Kelayakan',
                'date' => $surveyDate ? IndonesianDate::date($surveyDate) : '-',
                'notes' => $latestSurvey
                    ? trim(($latestSurvey->survey_status === 'completed' ? 'LAYAK' : strtoupper((string) $latestSurvey->survey_status))
                        .' — dropcore '.($latestSurvey->cable_estimation_meter ?? 0).'m, ODP '.($latestSurvey->nearest_odp ?: '-'))
                    : ($statusRank == 2 ? 'Sedang dijadwalkan / dalam antrean.' : 'Belum ada laporan survey.'),
                'status' => $latestSurvey && $latestSurvey->survey_status === 'completed'
                    ? 'completed'
                    : ($statusRank == 2 || $latestSurvey ? 'current' : 'pending'),
            ],
            [
                'step' => 'Pemasangan',
                'title' => 'Penarikan Kabel & Pemasangan ONT',
                'date' => $installationDate ? IndonesianDate::date($installationDate) : '-',
                'notes' => $latestInstallation
                    ? 'SN ONT: '.($customer->ont_sn ?: '-')
                        .($customer->customerTechnicalDetail?->initial_attenuation ? ', redaman awal '.$customer->customerTechnicalDetail->initial_attenuation.' dBm' : '')
                    : ($statusRank == 4 ? 'Teknisi sedang melakukan penarikan kabel dropcore.' : 'Belum ada laporan pemasangan.'),
                'status' => $latestInstallation && $latestInstallation->installation_status === 'completed'
                    ? 'completed'
                    : ($latestInstallation ? 'current' : 'pending'),
            ],
            [
                'step' => 'Aktivasi Billing',
                'title' => 'Aktivasi Layanan & Siap Billing',
                'date' => $activationDate ? IndonesianDate::date($activationDate) : '-',
                'notes' => $activationDate
                    ? ($service->activation_time ? substr((string) $service->activation_time, 0, 5).' WIB' : 'Layanan aktif')
                    : 'Menunggu proses pemasangan & uji speedtest selesai.',
                'status' => $activationDate
                    ? ($status === 'suspended' ? 'warning' : ($status === 'terminated' ? 'danger' : 'completed'))
                    : 'pending',
            ],
        ];

        // Rekap "siapa mengerjakan apa, kapan" — dipakai tabel Ringkasan Waktu &
        // Penanggung Jawab di tab Ringkasan. Sengaja hanya tahap yang punya
        // record; tahap tanpa data tetap dibariskan dengan PIC '-' supaya urutan
        // workflow tetap terbaca utuh.
        $workflowStages = [
            [
                'no' => 1,
                'title' => 'Registrasi & Input Pelanggan',
                'subtitle' => 'Pendaftaran Awal Data Pelanggan',
                // Pelanggan hasil migrasi legacy: $customer->created_at &
                // creator selalu mencatat SAAT IMPORT dijalankan (wall clock +
                // admin yang jalanin command), bukan kapan/siapa yang beneran
                // mendaftarkan pelanggan itu di sistem lama. registration_date
                // & registered_by_name (diisi khusus dari jalur migrasi) yang
                // jadi sumber kebenaran kalau ada.
                'at' => $customer->registered_by_name ? $regDate : $customer->created_at,
                'date_fallback' => IndonesianDate::date($regDate),
                'pic' => $customer->registered_by_name ?? $customer->creator?->name,
                'pic_role' => $customer->registered_by_name ? 'Data Migrasi Legacy' : $customer->creator?->role?->name,
                'accent' => 'sky',
            ],
            [
                'no' => 2,
                'title' => 'Survey Lokasi & Jalur Optik',
                'subtitle' => 'Pemeriksaan Jalur & ODP',
                'at' => $latestSurvey?->assigned_at,
                'date_fallback' => $surveyDate ? IndonesianDate::date($surveyDate) : null,
                'pic' => $latestSurvey?->technician?->name ?? $latestSurvey?->surveyors,
                'pic_role' => $latestSurvey?->technician?->role?->name ?? 'Surveyor FOP',
                'accent' => 'indigo',
            ],
            [
                'no' => 3,
                'title' => 'Review & Filter Admin',
                'subtitle' => 'Verifikasi Hasil Survey (ACC)',
                'at' => $service?->admin_filter_at,
                'date_fallback' => null,
                'pic' => $service?->admin_filter_by_name,
                'pic_role' => 'Admin POP',
                'accent' => 'amber',
            ],
            [
                'no' => 4,
                'title' => 'Proses Pemasangan Perangkat & FO',
                'subtitle' => 'Penarikan Dropcore & ONT',
                'at' => $latestInstallation?->assigned_at,
                'date_fallback' => $installationDate ? IndonesianDate::date($installationDate) : null,
                'pic' => $latestInstallation?->technician?->name ?? $latestInstallation?->technicians,
                'pic_role' => $latestInstallation?->technician?->role?->name ?? 'Teknisi FOP',
                'accent' => 'purple',
            ],
            [
                'no' => 5,
                'title' => 'Aktivasi Layanan & Verifikasi Final',
                'subtitle' => 'Siap Billing & Dial-up PPPoE',
                'at' => null,
                'date_fallback' => $activationDate
                    ? IndonesianDate::date($activationDate).($service->activation_time ? ' ('.substr((string) $service->activation_time, 0, 5).' WIB)' : '')
                    : null,
                'pic' => $service?->activatedBy?->name ?? $service?->activated_by_name,
                'pic_role' => $service?->activatedBy?->role?->name ?? 'Verifikator',
                'accent' => 'emerald',
            ],
        ];

        // Anomali migrasi legacy: tanggal registrasi bisa tercatat LEBIH AKHIR
        // dari tanggal survey/pasang. Ditandai eksplisit di UI supaya tidak
        // dilaporkan berulang sebagai bug tampilan.
        $timelineAnomaly = $surveyDate && $regDate->gt(Carbon::parse($surveyDate));

        // 1. Audit Logs untuk Riwayat Perubahan Data Pelanggan
        $auditableConditions = [
            [Customer::class, $customer->id],
        ];
        if ($customer->customerAddress) {
            $auditableConditions[] = [CustomerAddress::class, $customer->customerAddress->id];
        }
        if ($customer->customerService) {
            $auditableConditions[] = [CustomerService::class, $customer->customerService->id];
        }
        if ($customer->customerDevice) {
            $auditableConditions[] = [CustomerDevice::class, $customer->customerDevice->id];
        }
        if ($customer->customerTechnicalDetail) {
            $auditableConditions[] = [CustomerTechnicalDetail::class, $customer->customerTechnicalDetail->id];
        }

        $auditLogs = AuditLog::with('user.role')
            ->where(function ($q) use ($auditableConditions) {
                foreach ($auditableConditions as $index => $condition) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $q->$method(function ($sub) use ($condition) {
                        $sub->where('auditable_type', $condition[0])
                            ->where('auditable_id', $condition[1]);
                    });
                }
            })
            ->orderBy('created_at', 'desc')
            // audit_logs tidak pernah dipangkas — pelanggan berumur 3 tahun bisa
            // menumpuk ratusan baris dan SEMUANYA dirender ke satu halaman detail.
            // Dibatasi ke 50 terbaru; riwayat lengkap tetap bisa dilihat lewat
            // halaman Riwayat tersendiri.
            ->limit(50)
            ->get();

        $statusLogs = CustomerStatusLog::with('user.role')
            ->where('customer_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        // 2. Tasks & FopTasks untuk Riwayat Ticketing
        $customerTasks = $customer->tasks()
            ->with([
                'teamMembers.user',
                'creator',
                'fop',
                'auditLogs.user.role',
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        $customerFopTasks = $customer->fopTasks()
            ->with(['technicians', 'village', 'pop'])
            ->orderBy('task_date', 'desc')
            ->get();

        // Daftar user aktif untuk dropdown petugas (survey, pemasangan)
        $activeUsers = User::where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('customers.show', compact(
            'customer',
            'displayId',
            'displayIdLabel',
            'completeness',
            'timeline',
            'workflowStages',
            'timelineAnomaly',
            'activeUsers',
            'auditLogs',
            'statusLogs',
            'customerTasks',
            'customerFopTasks',
            'availableMiniPops',
            'availableDistributions'
        ));
    }

    /**
     * Guard per-record — renderCustomerList()/index() sudah di-scope
     * applyUserScope(), tapi show/edit/update/destroy diakses LANGSUNG lewat
     * ID lewat route model binding, gak pernah lewat query index() itu.
     * Tanpa ini, user ber-scope selected_pop/pop_tree tinggal tebak/iterasi ID
     * pelanggan buat baca/ubah/hapus data pelanggan POP lain manapun — IDOR
     * penuh di model paling sensitif (lihat docs/plan/analisa-celah-scope-pop.md
     * temuan #8).
     */
    private function authorizeCustomerPopScope(Customer $customer): void
    {
        $access = app(EffectiveAccessService::class);
        $user = auth()->user();

        if ($access->hasAllPopAccess($user)) {
            return;
        }

        abort_unless(
            in_array((int) $customer->pop_id, $access->getAllowedPopIds($user), true),
            403,
            'Anda tidak memiliki akses ke pelanggan di POP ini.'
        );
    }

    /**
     * Get payment info & quick hub details for the customer action modal.
     */
    public function paymentInfo(Customer $customer)
    {
        // Pastikan user punya akses ke POP pelanggan. Pakai EffectiveAccessService
        // (jalur benar, paham pop_tree) — BUKAN $user->pops() pivot legacy yang
        // dipakai sebelumnya, yang gak paham hierarki pop_tree (lihat CLAUDE.md
        // § POP Scope, docs/plan/analisa-celah-scope-pop.md temuan #7).
        $access = app(EffectiveAccessService::class);
        if (! $access->hasAllPopAccess(auth()->user())
            && ! in_array((int) $customer->pop_id, $access->getAllowedPopIds(auth()->user()), true)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Cari invoice terbaru yang belum lunas
        $latestInvoice = $customer->invoices()
            ->whereIn('invoice_status', [InvoiceStatus::BELUM_DIBAYAR->value, InvoiceStatus::SEBAGIAN->value])
            ->latest('issue_date')
            ->first();

        // Hitung total piutang (sum dari remaining_amount semua invoice yang belum lunas)
        $totalPiutang = $customer->invoices()
            ->whereIn('invoice_status', [InvoiceStatus::BELUM_DIBAYAR->value, InvoiceStatus::SEBAGIAN->value])
            ->sum('remaining_amount');

        // Recent payments (3 pembayaran terakhir)
        $recentPayments = Payment::where('customer_id', $customer->id)
            ->with('invoice:id,invoice_number')
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->take(3)
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'date' => IndonesianDate::date($p->payment_date),
                    'amount' => (float) $p->amount,
                    'method' => ucfirst($p->payment_method),
                    'invoice_number' => $p->invoice?->invoice_number ?? '-',
                    // Struk dicetak per pembayaran, bukan per invoice — satu invoice
                    // bisa dicicil beberapa kali dan tiap setoran punya struk sendiri.
                    'receipt_url' => route('payments.receipt', $p->id),
                ];
            });

        $service = $customer->customerService;
        $device = $customer->customerDevice;
        $address = $customer->customerAddress;
        $completeness = $customer->dataCompleteness();

        $lat = $address?->latitude;
        $lng = $address?->longitude;
        $mapsUrl = ($lat && $lng) ? "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}" : null;

        // Berkas Foto Rumah untuk kartu pratinjau di tab Profil & Berkas.
        // Yang dikirim cuma dokumen TERBARU per tipe: kartu di modal hanya ruang
        // untuk satu pratinjau, riwayat lengkapnya tetap di halaman Detail.
        $latestDocuments = $customer->documents()
            ->whereIn('document_type', [DocumentType::RUMAH->value])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('document_type')
            ->map(fn ($docs) => $docs->first());

        $documentPayload = collect([DocumentType::RUMAH->value])
            ->mapWithKeys(function (string $type) use ($latestDocuments) {
                $doc = $latestDocuments->get($type);

                return [$type => [
                    'exists' => $doc !== null,
                    // URL file selalu lewat controller (cek permission + POP scope),
                    // jangan pernah bikin URL storage langsung yang bisa ditebak.
                    'url' => $doc ? route('customers.documents.show', $doc->id) : null,
                    'uploaded_at' => $doc ? IndonesianDate::date($doc->created_at) : null,
                ]];
            });

        return response()->json([
            'invoice_id' => $latestInvoice ? $latestInvoice->id : null,
            // Target POST form "Catat Pembayaran" di Quick Hub. Dikirim SERVER-SIDE,
            // bukan dirakit klien dari invoice_id (`/invoices/${id}/payments` sebagai
            // string literal) — path route tidak boleh diduplikasi di JS, dan kalau
            // dirakit di klien, form tanpa nilai ini diam-diam POST ke URL halaman.
            // ADHOC-20 langkah 3.
            'payment_store_url' => $latestInvoice ? route('invoices.payments.store', $latestInvoice->id) : null,
            'invoice_number' => $latestInvoice ? $latestInvoice->invoice_number : null,
            'total_amount' => $latestInvoice ? (float) $latestInvoice->total_amount : 0,
            'remaining_amount' => $latestInvoice ? (float) $latestInvoice->remaining_amount : 0,
            'discount' => $latestInvoice ? (float) $latestInvoice->discount : 0,
            'total_piutang' => (float) $totalPiutang,
            'billing_period' => $latestInvoice ? $latestInvoice->billing_period : null,
            'due_date' => $latestInvoice && $latestInvoice->due_date ? $latestInvoice->due_date->format('d/m/Y') : null,
            'technical' => [
                'pppoe_username' => $service?->pppoe_username ?? '-',
                'onu_sn' => $device?->onu_sn ?? $device?->mac_address ?? '-',
                'router_sn' => $device?->router_sn ?? '-',
                'device_brand' => $device?->device_brand ?? '-',
                'contract_type' => match ($service?->contract_type) {
                    'sewa' => 'Sewa', 'beli' => 'Beli', default => '-'
                },
                'distribution' => $customer->distribution?->name ?? '-',
                'pop_name' => $customer->pop?->name ?? '-',
            ],
            'location' => [
                'latitude' => $lat ?? '',
                'longitude' => $lng ?? '',
                'maps_url' => $mapsUrl,
            ],
            'recent_payments' => $recentPayments,
            'documents' => $documentPayload,
            'documents_upload_url' => route('customers.documents.store', $customer->id),
            'completeness' => [
                'percentage' => $completeness['percentage'],
                'missing_required' => $completeness['missing_required'],
            ],
        ]);
    }

    /**
     * Show the batch import page.
     */
    public function importForm()
    {
        $packages = InternetPackage::orderBy('name')->get(['id', 'package_code', 'name', 'monthly_price']);
        $villages = Village::with('district')->orderBy('name')->get(['id', 'name', 'district_id']);

        return view('customers.import', compact('packages', 'villages'));
    }

    /**
     * Display the import history.
     */
    public function importHistory()
    {
        $batches = ImportBatch::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('customers.import_history', compact('batches'));
    }

    /**
     * Display the import batch detail and errors.
     */
    public function importBatchDetail($id)
    {
        $batch = ImportBatch::with(['user', 'errors'])
            ->findOrFail($id);

        return view('customers.import_batch_detail', compact('batch'));
    }

    /**
     * Download the customer import template for legacy customer migration.
     */
    public function downloadImportTemplate()
    {
        $sheets = [
            'customers' => [
                'headers' => ['old_customer_id', 'old_request_id', 'customer_code', 'full_name', 'identity_number', 'gender', 'phone', 'alternative_phone', 'email', 'customer_type', 'company_name', 'npwp', 'full_address', 'old_region_id', 'city', 'district', 'village', 'old_branch_id', 'old_account_status', 'registration_date', 'profile_photo', 'pop_code', 'pop_name', 'distribution_code', 'latitude', 'longitude', 'foto_rumah', 'foto_kontrak', 'sales_code', 'agent_code', 'referral_customer_code'],
                'data' => [['PE000001', 'RQ000001', 'C00RQ000001', 'Budi Santoso', '3502180101900001', 'Laki-laki', '081234567890', '', 'budi@example.com', 'rumah', '', '', 'Jl. Merdeka No. 10', 'WL0001', 'Ponorogo', 'Sukorejo', 'Sukorejo', 'CB001', 'ACTIVE', '2025-05-06', 'foto.jpg', 'SMN', 'POP Sukorejo', '-7.8712', '111.4623', 'foto_rumah.jpg', 'foto_kontrak.jpg', 'SLS001', '', '']],
            ],
            'packages' => [
                'headers' => ['old_package_id', 'name', 'package_type', 'category', 'monthly_price', 'upload_speed', 'download_speed', 'upload_limit', 'download_limit', 'olt_profile', 'ppp_profile', 'bonus', 'description'],
                'data' => [['PK000001', 'WHUSNET 20 Mbps', 'Broadband', 'Paket Home Broadband', '150000', '20', '20', '', '', 'OLT-20M', 'PPP-20M', '', 'Paket legacy']],
            ],
            'services' => [
                'headers' => ['old_request_id', 'old_customer_id', 'old_package_id', 'old_cost_id', 'request_status', 'installation_status', 'service_status', 'activation_date', 'survey_at', 'approved_at', 'processed_at', 'finished_at', 'verified_at', 'network_type', 'member_type', 'reason', 'profile', 'contract_type', 'activation_time', 'activated_by_name', 'survey_date', 'survey_start_time', 'survey_end_time', 'surveyors', 'survey_assigned_at', 'survey_fop_id', 'required_tools', 'survey_photo', 'survey_note', 'survey_duration_minutes', 'installation_date', 'installation_start_time', 'installation_end_time', 'installation_technicians', 'installation_photo', 'installation_note', 'installation_assigned_at', 'installation_fop_id', 'other_fee', 'device_retrieved_status'],
                'data' => [['RQ000001', 'PE000001', 'PK000001', 'IN000001', 'ACTIVE', 'Berhasil', 'ACTIVE', '2025-05-06', '', '', '', '', '', 'KABEL', '0', '', 'PPP-20M', 'bulanan', '10:00:00', 'Admin', '2025-05-05', '09:00:00', '09:30:00', 'Teknisi A', '2025-05-05 09:00:00', 'RQ000001', 'Tangga, Fiber', 'survey_rumah.jpg', 'Ada ODP dekat', '30', '2025-05-06', '10:00:00', '11:00:00', 'Teknisi B', 'pasang.jpg', 'Selesai pasang', '2025-05-06 10:00:00', 'RQ000001', '0', '']],
            ],
            'technical_details' => [
                'headers' => ['old_report_id', 'old_customer_id', 'old_request_id', 'connection_type', 'test_upload', 'test_download', 'ssid', 'antenna_mac', 'router_mac', 'router_or_ont_serial', 'odp_number', 'odp_port', 'olt_port', 'wireless_signal', 'fiber_signal', 'location_source', 'note', 'speedtest_photo', 'form_photo', 'signed_form_photo', 'router_photo', 'cable_photo', 'passive_device', 'branch_number', 'pop_number', 'router_number', 'initial_attenuation', 'actual_attenuation', 'test_date', 'test_time', 'jitter_ms', 'latency_ms', 'packet_loss_percent', 'speed_conformity_percent', 'quality_score'],
                'data' => [['REP000001', 'PE000001', 'RQ000001', 'FTTH', '20', '20', 'WHUSNET-BUDI', '', 'AA:BB:CC:DD:EE:FF', 'SN123456', 'ODP-SMN-001', '1', '1/1/1', '', '-18', 'Tiang 01', 'Data teknis legacy', '', '', '', '', '', 'FAT-01', 'CB001', 'WL0001', 'RTR-001', '-18.5', '-19.2', '2025-05-06', '11:00:00', '2.5', '12.0', '0.00', '95.0', '5']],
            ],
            'invoices' => [
                'headers' => ['old_invoice_id', 'old_cost_id', 'old_customer_id', 'old_request_id', 'billing_period', 'issue_date', 'due_date', 'installation_fee', 'monthly_fee', 'other_fee', 'total_amount', 'status', 'prorate_amount', 'extra_cable_fee', 'extra_installation_fee', 'extra_pole_fee'],
                'data' => [['INVOICE-0001', 'IN000001', 'PE000001', 'RQ000001', '2025-05', '2025-05-06', '2025-05-10', '0', '150000', '0', '150000', 'belum_dibayar', '', '0', '0', '0']],
            ],
            'payments' => [
                'headers' => ['old_payment_id', 'old_transaction_id', 'old_invoice_id', 'old_customer_id', 'old_request_id', 'payment_date', 'billing_period', 'payment_method', 'amount', 'received_by_old', 'deposited_by_old', 'note', 'status'],
                'data' => [['PAY000001', 'IN000001', '', 'PE000001', 'RQ000001', '2025-05-07', '2025-05', 'cash', '150000', 'PG000005', '', 'Pembayaran legacy', 'valid']],
            ],
        ];

        $tempFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'temp-template-import-'.uniqid().'.xlsx';

        if (! class_exists(\ZipArchive::class)) {
            // Fallback for environment lacking ZipArchive extension (e.g. host machine's CLI)
            file_put_contents($tempFile, 'Dummy Excel Content (Missing ZipArchive)');
        } else {
            $writer = SimpleExcelWriter::create($tempFile);

            $isFirstSheet = true;
            foreach ($sheets as $sheetName => $sheet) {
                if (! $isFirstSheet) {
                    $writer->addNewSheetAndMakeItCurrent();
                }
                $writer->nameCurrentSheet($sheetName);
                $writer->addHeader($sheet['headers']);
                $writer->addRows($sheet['data']);
                $isFirstSheet = false;
            }

            $writer->close();
        }

        return response()->download($tempFile, 'template-import-pelanggan.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Validate the parsed rows from import (Excel/CSV multi-sheet).
     */
    /** @var array<string,int|null> */
    private array $branchPopIdCache = [];

    private function resolveBranchPopId(?string $branchPopCode): ?int
    {
        $branchPopCode = trim((string) $branchPopCode);
        if ($branchPopCode === '') {
            return null;
        }
        if (array_key_exists($branchPopCode, $this->branchPopIdCache)) {
            return $this->branchPopIdCache[$branchPopCode];
        }

        return $this->branchPopIdCache[$branchPopCode] = Pop::where('pop_code', $branchPopCode)
            ->orWhere('code', $branchPopCode)
            ->value('id');
    }

    /**
     * Resolve a customer's DB id from its legacy old_customer_id, scoped to the
     * given branch POP. Legacy old_customer_id (PE...) is only unique within its
     * source branch — an unscoped lookup can silently return a different
     * branch's customer that happens to reuse the same raw legacy code.
     */
    private function findScopedCustomerId(?string $oldCustomerId, ?string $branchPopCode): ?int
    {
        $oldCustomerId = trim((string) $oldCustomerId);
        if ($oldCustomerId === '') {
            return null;
        }

        $branchPopId = $this->resolveBranchPopId($branchPopCode);

        return $this->scopeToBranchPopDirect(Customer::where('old_customer_id', $oldCustomerId), $branchPopId)->value('id');
    }

    /**
     * Restrict a legacy-duplicate-check query to records belonging to the given
     * branch POP (matches records on that POP directly, or on a mini-POP whose
     * parent is that branch POP). Pass null $branchPopId to leave unscoped.
     */
    private function scopeToBranchPopDirect($query, ?int $branchPopId)
    {
        if (! $branchPopId) {
            return $query;
        }

        return $query->where(function ($q) use ($branchPopId) {
            $q->where('pop_id', $branchPopId)
                ->orWhereHas('pop', fn ($pq) => $pq->where('parent_id', $branchPopId));
        });
    }

    /**
     * Same as scopeToBranchPopDirect but for models without their own pop_id
     * (CustomerService, CustomerTechnicalDetail) — scopes via the related customer.
     */
    private function scopeToBranchPopViaCustomer($query, ?int $branchPopId)
    {
        if (! $branchPopId) {
            return $query;
        }

        return $query->whereHas('customer', function ($cq) use ($branchPopId) {
            $cq->where('pop_id', $branchPopId)
                ->orWhereHas('pop', fn ($pq) => $pq->where('parent_id', $branchPopId));
        });
    }

    public function validateImport(Request $request)
    {
        try {
            if ($request->hasFile('file')) {
                $path = $request->file('file')->getRealPath();
                $sheets = [
                    'customers' => SimpleExcelReader::create($path, 'xlsx')->fromSheetName('customers')->getRows()->toArray(),
                    'packages' => SimpleExcelReader::create($path, 'xlsx')->fromSheetName('packages')->getRows()->toArray(),
                    'services' => SimpleExcelReader::create($path, 'xlsx')->fromSheetName('services')->getRows()->toArray(),
                    'technical_details' => SimpleExcelReader::create($path, 'xlsx')->fromSheetName('technical_details')->getRows()->toArray(),
                    'invoices' => SimpleExcelReader::create($path, 'xlsx')->fromSheetName('invoices')->getRows()->toArray(),
                    'payments' => SimpleExcelReader::create($path, 'xlsx')->fromSheetName('payments')->getRows()->toArray(),
                ];
            } else {
                $sheets = $request->input('sheets', []);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membaca file Excel. Pastikan format file sesuai template dan memiliki 6 sheet lengkap (customers, packages, services, technical_details, invoices, payments).',
                'error' => $e->getMessage(),
            ], 422);
        }

        $customersData = $sheets['customers'] ?? [];
        $packagesData = $sheets['packages'] ?? [];
        $servicesData = $sheets['services'] ?? [];
        $techDetailsData = $sheets['technical_details'] ?? [];
        $invoicesData = $sheets['invoices'] ?? [];
        $paymentsData = $sheets['payments'] ?? [];

        // Legacy dumps from different branches (e.g. sand_db vs jetis_db) reuse the
        // same sequential ID scheme (PE000001, RQ000001, ...) starting from 1 in
        // each source install, so old_customer_id/old_request_id/old_cost_id are
        // NOT globally unique across branches. Scope "already imported" duplicate
        // checks below to the branch being imported (via branch_pop_code, set by
        // MigrateLegacyDataCommand on every sheet row) so importing one branch
        // doesn't get silently blocked because another branch already used the
        // same legacy numbering.
        $branchPopCode = trim((string) (
            $customersData[0]['branch_pop_code']
            ?? $servicesData[0]['branch_pop_code']
            ?? $invoicesData[0]['branch_pop_code']
            ?? $paymentsData[0]['branch_pop_code']
            ?? ''
        ));
        $branchPopId = $branchPopCode !== ''
            ? Pop::where('pop_code', $branchPopCode)->orWhere('code', $branchPopCode)->value('id')
            : null;

        // 1. Validate Packages Sheet
        $validatedPackages = [];
        $seenPackageIds = [];
        foreach ($packagesData as $index => $row) {
            $oldPackageId = trim((string) ($row['old_package_id'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $monthlyPriceInput = trim((string) ($row['monthly_price'] ?? ''));

            $errors = [];
            $warnings = [];
            $statusRow = 'valid';

            if ($oldPackageId === '') {
                $errors[] = 'ID paket lama wajib diisi.';
                $statusRow = 'error';
            } else {
                $pkgKey = strtolower($oldPackageId);
                if (isset($seenPackageIds[$pkgKey])) {
                    $errors[] = 'ID paket lama duplikat di sheet ini.';
                    $statusRow = 'error';
                }
                $seenPackageIds[$pkgKey] = true;
            }

            if ($name === '') {
                $errors[] = 'Nama paket wajib diisi.';
                $statusRow = 'error';
            }

            if ($monthlyPriceInput === '') {
                $errors[] = 'Harga paket wajib diisi.';
                $statusRow = 'error';
            } elseif (! is_numeric($monthlyPriceInput)) {
                $errors[] = 'Harga paket harus berupa angka.';
                $statusRow = 'error';
            }

            $validatedPackages[] = array_merge($row, [
                'original_no' => $index + 1,
                'old_package_id' => $oldPackageId,
                'name' => $name,
                'monthly_price' => is_numeric($monthlyPriceInput) ? (float) $monthlyPriceInput : null,
                'status_row' => $statusRow,
                'errors' => $errors,
                'warnings' => $warnings,
            ]);
        }

        // 2. Validate Customers Sheet
        $validatedCustomers = [];
        $seenCustomerIds = [];
        $seenPhones = [];
        foreach ($customersData as $index => $row) {
            $oldCustomerId = $this->cleanLegacyValue($row['old_customer_id'] ?? null) ?? '';
            if ($this->isInternalLegacyAccountId($oldCustomerId)) {
                continue; // Skip internal account rows (like PG...)
            }
            $fullName = $this->cleanLegacyValue($row['full_name'] ?? null) ?? '';
            $primaryPhone = $this->cleanLegacyValue($row['primary_phone'] ?? $row['phone'] ?? null) ?? '';
            $fullAddress = $this->cleanLegacyValue($row['full_address'] ?? null) ?? '';
            $identityNumber = $this->cleanLegacyValue($row['identity_number'] ?? null);
            $villageNameInput = $this->cleanLegacyValue($row['village'] ?? null) ?? '';
            $districtNameInput = $this->cleanLegacyValue($row['district'] ?? null) ?? '';
            $cityNameInput = $this->cleanLegacyValue($row['city'] ?? null) ?? '';
            $popCodeInput = $this->cleanLegacyValue($row['pop_code'] ?? null) ?? '';
            $popNameInput = $this->cleanLegacyValue($row['pop_name'] ?? null) ?? '';
            $distributionCodeInput = $this->cleanLegacyValue($row['distribution_code'] ?? null) ?? '';
            if ($distributionCodeInput === '0') {
                $distributionCodeInput = '';
            }

            $errors = [];
            $warnings = [];
            $statusRow = 'valid';

            if ($oldCustomerId === '') {
                $errors[] = 'ID pelanggan lama wajib diisi.';
                $statusRow = 'error';
            } else {
                $custKey = strtolower($oldCustomerId);
                if (isset($seenCustomerIds[$custKey])) {
                    $errors[] = 'ID pelanggan lama duplikat di sheet ini.';
                    $statusRow = 'error';
                }
                $seenCustomerIds[$custKey] = true;

                if ($this->scopeToBranchPopDirect(Customer::where('old_customer_id', $oldCustomerId), $branchPopId)->exists()) {
                    $errors[] = 'ID pelanggan lama sudah terdaftar di database.';
                    $statusRow = 'error';
                }
            }

            if ($fullName === '' && $primaryPhone === '' && empty($identityNumber) && $fullAddress === '') {
                $errors[] = 'Minimal salah satu identitas pelanggan wajib diisi: nama, HP, NIK, atau alamat.';
                $statusRow = 'error';
            }

            if ($fullName === '') {
                $warnings[] = 'Nama lengkap kosong; pelanggan akan disimpan dengan ID lama sebagai nama sementara.';
                $fullName = $oldCustomerId !== '' ? $oldCustomerId : 'Pelanggan Legacy';
            }

            if ($primaryPhone !== '') {
                $phoneKey = preg_replace('/\D+/', '', $primaryPhone) ?: $primaryPhone;
                if (isset($seenPhones[$phoneKey])) {
                    $warnings[] = 'Nomor HP duplikat di sheet ini; tetap diimport untuk review legacy.';
                }
                $seenPhones[$phoneKey] = true;

                if (Customer::where('primary_phone', $primaryPhone)->exists()) {
                    $warnings[] = 'Nomor HP sudah terdaftar di database; duplikasi dicegah berdasarkan ID pelanggan lama.';
                }
            } else {
                $warnings[] = 'Nomor HP kosong atau bernilai null; pelanggan akan masuk sebagai perlu dilengkapi.';
            }

            if ($fullAddress === '') {
                $warnings[] = 'Alamat lengkap kosong; pelanggan akan masuk sebagai perlu dilengkapi.';
            }

            $villageId = null;
            $districtId = null;
            $cityId = null;
            $villageName = null;
            $districtName = null;
            $cityName = null;

            if ($villageNameInput !== '') {
                $village = Village::with('district.city')
                    ->where('name', $villageNameInput)
                    ->when($districtNameInput !== '', function ($q) use ($districtNameInput) {
                        $q->whereHas('district', function ($dq) use ($districtNameInput) {
                            $dq->where('name', $districtNameInput);
                        });
                    })
                    ->first();

                if ($village) {
                    $villageId = $village->id;
                    $villageName = $village->name;
                    $districtId = $village->district_id;
                    $districtName = $village->district?->name;
                    $cityId = $village->district?->city_id;
                    $cityName = $village->district?->city?->name;
                } else {
                    $warnings[] = "Desa/Kelurahan '{$villageNameInput}' tidak ditemukan di master wilayah; teks legacy tetap disimpan.";
                    $villageName = $villageNameInput;
                    $districtName = $districtNameInput ?: null;
                    $cityName = $cityNameInput ?: null;
                }
            } else {
                // Try to resolve from fullAddress if available
                $resolved = $fullAddress !== '' ? $this->resolveRegionFromAddress($fullAddress) : null;
                if ($resolved) {
                    $villageId = $resolved['village_id'];
                    $villageName = $resolved['village_name'];
                    $districtId = $resolved['district_id'];
                    $districtName = $resolved['district_name'];
                    $cityId = $resolved['city_id'];
                    $cityName = $resolved['city_name'];
                } else {
                    $warnings[] = 'Desa/Kelurahan kosong; pelanggan akan masuk sebagai perlu dilengkapi.';
                    $districtName = $districtNameInput ?: null;
                    $cityName = $cityNameInput ?: null;
                }
            }

            if ($districtNameInput === '') {
                $warnings[] = 'Kecamatan kosong; pelanggan akan masuk sebagai perlu dilengkapi.';
            }
            if ($cityNameInput === '') {
                $warnings[] = 'Kota/Kabupaten kosong; pelanggan akan masuk sebagai perlu dilengkapi.';
            }

            $pop = null;
            if ($popCodeInput === '' && $popNameInput === '') {
                $warnings[] = 'POP/Cabang kosong; pelanggan tetap diimport untuk review dan belum siap billing.';
            } else {
                $pop = Pop::where('status', 'active')
                    ->where(function ($q) use ($popCodeInput, $popNameInput) {
                        if ($popCodeInput !== '') {
                            $q->where('pop_code', $popCodeInput)->orWhere('code', $popCodeInput);
                        }
                        if ($popNameInput !== '') {
                            $q->orWhere('name', $popNameInput);
                        }
                    })
                    ->first();

                if (! $pop) {
                    $warnings[] = 'POP tidak ditemukan atau tidak aktif; pelanggan tetap diimport untuk review dan belum siap billing.';
                }
            }

            $rawLat = $row['latitude'] ?? null;
            $rawLon = $row['longitude'] ?? null;
            $normalizedLat = $this->normalizeCoordinate($rawLat);
            $normalizedLon = $this->normalizeCoordinate($rawLon);

            if (($rawLat !== null && $rawLat !== '' && $normalizedLat === null) ||
                ($rawLon !== null && $rawLon !== '' && $normalizedLon === null)) {
                $warnings[] = 'Format koordinat latitude/longitude tidak valid; nilai diabaikan.';
            }

            $validatedCustomers[] = array_merge($row, [
                'original_no' => $index + 1,
                'old_customer_id' => $oldCustomerId,
                'full_name' => $fullName,
                'phone' => $primaryPhone,
                'primary_phone' => $primaryPhone,
                'alternative_phone' => $this->cleanLegacyValue($row['alternative_phone'] ?? null),
                'email' => $this->cleanLegacyValue($row['email'] ?? null),
                'identity_number' => $identityNumber,
                'gender' => $this->cleanLegacyValue($row['gender'] ?? null) ?? 'Laki-laki',
                'customer_type' => $this->cleanLegacyValue($row['customer_type'] ?? null),
                'company_name' => $this->cleanLegacyValue($row['company_name'] ?? null),
                'npwp' => $this->cleanLegacyValue($row['npwp'] ?? null),
                'old_account_status' => $this->cleanLegacyValue($row['old_account_status'] ?? null),
                'full_address' => $fullAddress,
                'old_region_id' => $this->cleanLegacyValue($row['old_region_id'] ?? null),
                'old_branch_id' => $this->cleanLegacyValue($row['old_branch_id'] ?? null),
                'registration_date' => $this->normalizeLegacyDateTime($row['registration_date'] ?? null) ?? now()->format('Y-m-d H:i:s'),
                'pop_id' => $pop?->id,
                'pop_name' => $pop?->name,
                'pop_code' => $pop?->pop_code,
                'distribution_code' => $distributionCodeInput,
                'city_id' => $cityId,
                'district_id' => $districtId,
                'village_id' => $villageId,
                'village_name' => $villageName,
                'district_name' => $districtName,
                'city_name' => $cityName,
                'latitude' => $normalizedLat,
                'longitude' => $normalizedLon,
                'status_row' => $statusRow,
                'errors' => $errors,
                'warnings' => $warnings,
            ]);
        }

        // 3. Validate Services Sheet
        $validatedServices = [];
        $seenRequestIds = [];
        foreach ($servicesData as $index => $row) {
            $oldRequestId = $this->cleanLegacyValue($row['old_request_id'] ?? null) ?? '';
            $oldCustomerId = $this->cleanLegacyValue($row['old_customer_id'] ?? null) ?? '';
            if ($this->isInternalLegacyAccountId($oldCustomerId)) {
                continue; // Skip services belonging to internal users
            }
            $oldPackageId = $this->cleanLegacyValue($row['old_package_id'] ?? null) ?? '';
            $requestStatus = $this->cleanLegacyValue($row['request_status'] ?? null);
            $serviceStatusInput = $this->cleanLegacyValue($row['service_status'] ?? null) ?? $requestStatus ?? '';

            $errors = [];
            $warnings = [];
            $statusRow = 'valid';

            if ($oldRequestId === '') {
                $errors[] = 'ID layanan/request lama wajib diisi.';
                $statusRow = 'error';
            } else {
                $reqKey = strtolower($oldRequestId);
                if (isset($seenRequestIds[$reqKey])) {
                    $errors[] = 'ID request lama duplikat di sheet ini.';
                    $statusRow = 'error';
                }
                $seenRequestIds[$reqKey] = true;

                if ($this->scopeToBranchPopViaCustomer(CustomerService::where('old_request_id', $oldRequestId), $branchPopId)->exists()) {
                    $errors[] = 'ID request lama sudah terdaftar di database.';
                    $statusRow = 'error';
                }
            }

            if ($oldCustomerId === '') {
                $errors[] = 'ID pelanggan lama wajib diisi.';
                $statusRow = 'error';
            } else {
                $customerInSheet = collect($validatedCustomers)->firstWhere('old_customer_id', $oldCustomerId);
                $customerInDb = Customer::where('old_customer_id', $oldCustomerId)->exists();
                if (! $customerInSheet && ! $customerInDb) {
                    $errors[] = "Pelanggan dengan ID '{$oldCustomerId}' tidak ditemukan.";
                    $statusRow = 'error';
                }
            }

            $package = null;
            if ($oldPackageId === '') {
                $errors[] = 'ID paket lama wajib diisi.';
                $statusRow = 'error';
            } else {
                $packageInSheet = collect($validatedPackages)->firstWhere('old_package_id', $oldPackageId);
                $packageInDb = InternetPackage::where('old_package_id', $oldPackageId)
                    ->orWhere('package_code', $oldPackageId)
                    ->first();

                if (! $packageInSheet && ! $packageInDb) {
                    $errors[] = "Paket dengan ID '{$oldPackageId}' tidak ditemukan.";
                    $statusRow = 'error';
                } else {
                    $package = $packageInDb ?? $packageInSheet;
                }
            }

            $serviceStatus = $this->mapLegacyServiceStatus($serviceStatusInput);
            $validStatuses = SubscriptionStatus::query()->where('is_active', true)->pluck('code')->all();

            if ($serviceStatusInput === '') {
                $warnings[] = 'Status layanan kosong; dimapping ke registered untuk review.';
                $serviceStatus = 'registered';
            } elseif (! in_array($serviceStatus, $validStatuses, true)) {
                $errors[] = "Status layanan '{$serviceStatusInput}' tidak didukung.";
                $statusRow = 'error';
            }

            $validatedServices[] = array_merge($row, [
                'original_no' => $index + 1,
                'old_request_id' => $oldRequestId,
                'old_customer_id' => $oldCustomerId,
                'old_package_id' => $oldPackageId,
                'old_cost_id' => $this->cleanLegacyValue($row['old_cost_id'] ?? null),
                'request_status' => $requestStatus,
                'installation_status' => $this->cleanLegacyValue($row['installation_status'] ?? null),
                'network_type' => $this->cleanLegacyValue($row['network_type'] ?? null),
                'member_type' => $this->cleanLegacyValue($row['member_type'] ?? null),
                'reason' => $this->cleanLegacyValue($row['reason'] ?? null),
                'package_id' => $package instanceof InternetPackage ? $package->id : ($package['old_package_id'] ?? null),
                'package_name' => $package instanceof InternetPackage ? $package->name : ($package['name'] ?? null),
                // Harga di sheet menang atas harga paket kalau terisi dan > 0:
                // data legacy punya paket 'default'/'undefined' berharga 0 yang
                // dipakai puluhan pelanggan yang sebenarnya bayar penuh. Harga
                // paket cuma dipakai sebagai fallback, bukan sebaliknya, supaya
                // tagihan bulanan berikutnya tidak terbit Rp 0.
                'monthly_price' => is_numeric($row['monthly_price'] ?? null) && (float) $row['monthly_price'] > 0
                    ? (float) $row['monthly_price']
                    : ($package instanceof InternetPackage ? $package->monthly_price : ($package['monthly_price'] ?? 0)),
                'other_fee' => is_numeric($row['other_fee'] ?? null) ? (float) $row['other_fee'] : 0,
                'service_status' => $serviceStatus,
                'activation_date' => $this->normalizeLegacyDate($row['activation_date'] ?? $row['finished_at'] ?? null),
                'due_date' => $this->normalizeLegacyDate($row['due_date'] ?? null),
                'status_row' => $statusRow,
                'errors' => $errors,
                'warnings' => $warnings,
            ]);
        }

        // 4. Validate Technical Details Sheet
        $validatedTechDetails = [];
        $seenReportIds = [];
        foreach ($techDetailsData as $index => $row) {
            $oldReportId = trim((string) ($row['old_report_id'] ?? ''));
            $oldCustomerId = trim((string) ($row['old_customer_id'] ?? ''));
            if ($this->isInternalLegacyAccountId($oldCustomerId)) {
                continue; // Skip tech details belonging to internal users
            }
            $oldRequestId = trim((string) ($row['old_request_id'] ?? ''));

            $errors = [];
            $warnings = [];
            $statusRow = 'valid';

            if ($oldReportId === '') {
                $errors[] = 'ID detail teknis/report lama wajib diisi.';
                $statusRow = 'error';
            } else {
                $repKey = strtolower($oldReportId);
                if (isset($seenReportIds[$repKey])) {
                    $errors[] = 'ID detail teknis lama duplikat di sheet ini.';
                    $statusRow = 'error';
                }
                $seenReportIds[$repKey] = true;

                if ($this->scopeToBranchPopViaCustomer(CustomerTechnicalDetail::where('old_report_id', $oldReportId), $branchPopId)->exists()) {
                    $errors[] = 'ID detail teknis lama sudah terdaftar di database.';
                    $statusRow = 'error';
                }
            }

            if ($oldCustomerId === '') {
                $errors[] = 'ID pelanggan lama wajib diisi.';
                $statusRow = 'error';
            } else {
                $customerInSheet = collect($validatedCustomers)->firstWhere('old_customer_id', $oldCustomerId);
                $customerInDb = Customer::where('old_customer_id', $oldCustomerId)->exists();
                if (! $customerInSheet && ! $customerInDb) {
                    $errors[] = "Pelanggan dengan ID '{$oldCustomerId}' tidak ditemukan.";
                    $statusRow = 'error';
                }
            }

            $validatedTechDetails[] = array_merge($row, [
                'original_no' => $index + 1,
                'old_report_id' => $oldReportId,
                'old_customer_id' => $oldCustomerId,
                'old_request_id' => $oldRequestId,
                'connection_type' => trim((string) ($row['connection_type'] ?? 'FTTH')),
                'ont_sn' => trim((string) ($row['router_or_ont_serial'] ?? $row['ont_sn'] ?? '')),
                'odp_code' => trim((string) ($row['odp_number'] ?? $row['odp_code'] ?? '')),
                'olt_code' => trim((string) ($row['olt_port'] ?? $row['olt_code'] ?? '')),
                'vlan_id' => trim((string) ($row['vlan_id'] ?? '')),
                'initial_attenuation' => $this->cleanDecimal($row['initial_attenuation'] ?? null, -999.99, 999.99),
                'actual_attenuation' => $this->cleanDecimal($row['actual_attenuation'] ?? null, -999.99, 999.99),
                'jitter_ms' => $this->cleanDecimal($row['jitter_ms'] ?? null, -999999.99, 999999.99),
                'latency_ms' => $this->cleanDecimal($row['latency_ms'] ?? null, -999999.99, 999999.99),
                'packet_loss_percent' => $this->cleanDecimal($row['packet_loss_percent'] ?? null, -999.99, 999.99),
                'speed_conformity_percent' => $this->cleanDecimal($row['speed_conformity_percent'] ?? null, -999.99, 999.99),
                'status_row' => $statusRow,
                'errors' => $errors,
                'warnings' => $warnings,
            ]);
        }

        // 5. Validate Invoices Sheet
        $validatedInvoices = [];
        $seenInvoiceIds = [];
        foreach ($invoicesData as $index => $row) {
            $oldInvoiceId = $this->cleanLegacyValue($row['old_invoice_id'] ?? null);
            $oldCostId = $this->cleanLegacyValue($row['old_cost_id'] ?? null);
            $oldRequestId = $this->cleanLegacyValue($row['old_request_id'] ?? null);
            $oldCustomerId = $this->cleanLegacyValue($row['old_customer_id'] ?? null) ?? '';
            if ($this->isInternalLegacyAccountId($oldCustomerId)) {
                continue; // Skip invoices belonging to internal users
            }
            $billingPeriod = $this->normalizeBillingPeriod($row['billing_period'] ?? null)
                ?? $this->normalizeBillingPeriod($row['issue_date'] ?? null)
                ?? '';
            $totalAmountInput = $this->cleanLegacyValue($row['total_amount'] ?? null) ?? '';
            $legacyInvoiceKey = $oldInvoiceId ?: $oldCostId;

            $errors = [];
            $warnings = [];
            $statusRow = 'valid';

            if (empty($legacyInvoiceKey)) {
                $errors[] = 'ID invoice lama atau ID biaya lama wajib diisi.';
                $statusRow = 'error';
            } else {
                $invKey = strtolower($legacyInvoiceKey);
                if (isset($seenInvoiceIds[$invKey])) {
                    $errors[] = 'ID invoice lama duplikat di sheet ini.';
                    $statusRow = 'error';
                }
                $seenInvoiceIds[$invKey] = true;

                $invoiceDupQuery = Invoice::where(function ($q) use ($legacyInvoiceKey) {
                    $q->where('old_invoice_id', $legacyInvoiceKey)->orWhere('old_cost_id', $legacyInvoiceKey);
                });
                if ($this->scopeToBranchPopDirect($invoiceDupQuery, $branchPopId)->exists()) {
                    $errors[] = 'ID invoice/biaya lama sudah terdaftar di database.';
                    $statusRow = 'error';
                }
            }

            if ($oldCustomerId === '' && empty($oldRequestId)) {
                $errors[] = 'ID pelanggan lama atau ID request lama wajib diisi.';
                $statusRow = 'error';
            } elseif ($oldCustomerId !== '') {
                $customerInSheet = collect($validatedCustomers)->firstWhere('old_customer_id', $oldCustomerId);
                $customerInDb = Customer::where('old_customer_id', $oldCustomerId)->exists();
                if (! $customerInSheet && ! $customerInDb) {
                    $warnings[] = "Pelanggan dengan ID '{$oldCustomerId}' belum ditemukan saat validasi; akan dicoba cocok lewat request saat import.";
                }
            }

            if ($billingPeriod === '') {
                $errors[] = 'Periode tagihan (YYYY-MM) wajib diisi.';
                $statusRow = 'error';
            } elseif (! preg_match('/^\d{4}-\d{2}$/', $billingPeriod)) {
                $errors[] = 'Format periode tagihan harus YYYY-MM.';
                $statusRow = 'error';
            }

            if ($totalAmountInput === '') {
                $errors[] = 'Total tagihan wajib diisi.';
                $statusRow = 'error';
            } elseif (! is_numeric($totalAmountInput)) {
                $errors[] = 'Total tagihan harus berupa angka.';
                $statusRow = 'error';
            }

            $validatedInvoices[] = array_merge($row, [
                'original_no' => $index + 1,
                'old_invoice_id' => $oldInvoiceId ?: $oldCostId,
                'old_cost_id' => $oldCostId,
                'old_request_id' => $oldRequestId,
                'old_customer_id' => $oldCustomerId,
                'billing_period' => $billingPeriod,
                'total_amount' => is_numeric($totalAmountInput) ? (float) $totalAmountInput : 0,
                'issue_date' => $this->normalizeLegacyDate($row['issue_date'] ?? null) ?? now()->format('Y-m-d'),
                'due_date' => $this->normalizeLegacyDate($row['due_date'] ?? null) ?? now()->addDays(10)->format('Y-m-d'),
                'installation_fee' => is_numeric($row['installation_fee'] ?? null) ? (float) $row['installation_fee'] : 0,
                'monthly_fee' => is_numeric($row['monthly_fee'] ?? null) ? (float) $row['monthly_fee'] : null,
                'other_fee' => is_numeric($row['other_fee'] ?? null) ? (float) $row['other_fee'] : 0,
                'status' => $this->mapLegacyInvoiceStatus($row['status'] ?? null),
                'status_row' => $statusRow,
                'errors' => $errors,
                'warnings' => $warnings,
            ]);
        }

        // 6. Validate Payments Sheet
        $validatedPayments = [];
        $seenPaymentIds = [];
        foreach ($paymentsData as $index => $row) {
            $oldPaymentId = $this->cleanLegacyValue($row['old_payment_id'] ?? null) ?? '';
            $oldInvoiceId = $this->cleanLegacyValue($row['old_invoice_id'] ?? null);
            $oldTransactionId = $this->cleanLegacyValue($row['old_transaction_id'] ?? null);
            $oldRequestId = $this->cleanLegacyValue($row['old_request_id'] ?? null);
            $oldCustomerId = $this->cleanLegacyValue($row['old_customer_id'] ?? null);
            if ($this->isInternalLegacyAccountId($oldCustomerId)) {
                continue; // Skip payments belonging to internal users
            }
            $billingPeriod = $this->normalizeBillingPeriod($row['billing_period'] ?? null);
            $amountInput = $this->cleanLegacyValue($row['amount'] ?? null) ?? '';
            $rawPaymentDate = $this->cleanLegacyValue($row['payment_date'] ?? null);
            $paymentDateInput = $this->normalizeLegacyDate($rawPaymentDate);

            $errors = [];
            $warnings = [];
            $statusRow = 'valid';

            if ($oldPaymentId === '') {
                $errors[] = 'ID pembayaran lama wajib diisi.';
                $statusRow = 'error';
            } else {
                $payKey = strtolower($oldPaymentId);
                if (isset($seenPaymentIds[$payKey])) {
                    $errors[] = 'ID pembayaran lama duplikat di sheet ini.';
                    $statusRow = 'error';
                }
                $seenPaymentIds[$payKey] = true;

                if ($this->scopeToBranchPopDirect(Payment::where('old_payment_id', $oldPaymentId), $branchPopId)->exists()) {
                    $errors[] = 'ID pembayaran lama sudah terdaftar di database.';
                    $statusRow = 'error';
                }
            }

            if (empty($oldInvoiceId) && empty($oldTransactionId) && empty($oldRequestId)) {
                $errors[] = 'ID invoice, ID transaksi, atau ID request lama wajib diisi.';
                $statusRow = 'error';
            } elseif (! empty($oldInvoiceId)) {
                $invoiceInSheet = collect($validatedInvoices)->firstWhere('old_invoice_id', $oldInvoiceId);
                $invoiceInDb = Invoice::where('old_invoice_id', $oldInvoiceId)->exists();
                if (! $invoiceInSheet && ! $invoiceInDb) {
                    $warnings[] = "Invoice dengan ID '{$oldInvoiceId}' belum ditemukan saat validasi; akan dicoba cocok lewat transaksi/request saat import.";
                }
            }

            if ($amountInput === '') {
                $errors[] = 'Nominal bayar wajib diisi.';
                $statusRow = 'error';
            } elseif (! is_numeric($amountInput)) {
                $errors[] = 'Nominal bayar harus berupa angka.';
                $statusRow = 'error';
            }

            if (empty($rawPaymentDate)) {
                $errors[] = 'Tanggal bayar wajib diisi.';
                $statusRow = 'error';
            } elseif (empty($paymentDateInput)) {
                $errors[] = 'Tanggal bayar tidak valid.';
                $statusRow = 'error';
            }

            $validatedPayments[] = array_merge($row, [
                'original_no' => $index + 1,
                'old_payment_id' => $oldPaymentId,
                'old_invoice_id' => $oldInvoiceId,
                'old_transaction_id' => $oldTransactionId,
                'old_request_id' => $oldRequestId,
                'old_customer_id' => $oldCustomerId,
                'billing_period' => $billingPeriod,
                'amount' => is_numeric($amountInput) ? (float) $amountInput : 0,
                'payment_date' => $paymentDateInput,
                'payment_method' => $this->mapLegacyPaymentMethod($row['payment_method'] ?? null),
                'received_by_old' => $this->cleanLegacyValue($row['received_by_old'] ?? null),
                'deposited_by_old' => $this->cleanLegacyValue($row['deposited_by_old'] ?? null),
                'status' => $this->mapLegacyPaymentStatus($row['status'] ?? null),
                'status_row' => $statusRow,
                'errors' => $errors,
                'warnings' => $warnings,
            ]);
        }

        return response()->json([
            'success' => true,
            'sheets' => [
                'customers' => ['rows' => $validatedCustomers],
                'packages' => ['rows' => $validatedPackages],
                'services' => ['rows' => $validatedServices],
                'technical_details' => ['rows' => $validatedTechDetails],
                'invoices' => ['rows' => $validatedInvoices],
                'payments' => ['rows' => $validatedPayments],
            ],
        ]);
    }

    public function confirmImport(Request $request)
    {
        $sheets = $request->input('sheets', []);
        $fileName = $request->input('file_name', 'Multi-sheet Import');

        if (is_string($sheets)) {
            $sheets = json_decode($sheets, true);
        }

        if (empty($sheets)) {
            return redirect()->route('customers.import')->withErrors('Tidak ada data yang di-import.');
        }

        $customersData = isset($sheets['customers']['rows']) ? $sheets['customers']['rows'] : ($sheets['customers'] ?? []);
        $packagesData = isset($sheets['packages']['rows']) ? $sheets['packages']['rows'] : ($sheets['packages'] ?? []);
        $servicesData = isset($sheets['services']['rows']) ? $sheets['services']['rows'] : ($sheets['services'] ?? []);
        $techDetailsData = isset($sheets['technical_details']['rows']) ? $sheets['technical_details']['rows'] : ($sheets['technical_details'] ?? []);
        $invoicesData = isset($sheets['invoices']['rows']) ? $sheets['invoices']['rows'] : ($sheets['invoices'] ?? []);
        $paymentsData = isset($sheets['payments']['rows']) ? $sheets['payments']['rows'] : ($sheets['payments'] ?? []);

        // Count totals
        $totalRows = count($customersData) + count($packagesData) + count($servicesData) + count($techDetailsData) + count($invoicesData) + count($paymentsData);
        $invalidRows = 0;
        $validRows = 0;

        foreach ([$customersData, $packagesData, $servicesData, $techDetailsData, $invoicesData, $paymentsData] as $sheetData) {
            foreach ($sheetData as $row) {
                if (($row['status_row'] ?? '') === 'error') {
                    $invalidRows++;
                } else {
                    $validRows++;
                }
            }
        }

        $batch = ImportBatch::create([
            'batch_number' => ImportBatch::generateBatchNumber(),
            'file_name' => $fileName,
            'uploaded_by' => auth()->id(),
            'total_rows' => $totalRows,
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
            'status' => 'pending',
        ]);

        $insertedCount = 0;

        try {
            DB::transaction(function () use (
                $customersData, $packagesData, $servicesData, $techDetailsData, $invoicesData, $paymentsData,
                $batch, &$insertedCount
            ) {
                // Keep track of imported models mapping (old_id => new_id)
                $packagesMap = [];
                $customersMap = [];
                $invoicesMap = [];

                // 1. Process Packages
                foreach ($packagesData as $row) {
                    if (($row['status_row'] ?? '') === 'error') {
                        $this->logImportError($batch->id, $row, 'Packages', 'Baris error pada sheet Packages.');

                        continue;
                    }

                    // Check if package already exists in DB
                    $package = InternetPackage::where('old_package_id', $row['old_package_id'])
                        ->orWhere('package_code', $row['old_package_id'])
                        ->first();

                    if (! $package) {
                        $package = InternetPackage::create([
                            'package_code' => $row['old_package_id'],
                            'old_package_id' => $row['old_package_id'],
                            'name' => $row['name'],
                            'monthly_price' => $row['monthly_price'],
                            'ppn' => 0.00,
                            'total_price' => $row['monthly_price'],
                            'category' => $row['category'] ?? 'Paket Home Broadband',
                            'package_group' => $row['package_type'] ?? 'Broadband',
                            'bandwidth_label' => ($row['download_speed'] ?? '10').' Mbps',
                            'download_speed_mbps' => $row['download_speed'] ?? 10.00,
                            'upload_speed_mbps' => $row['upload_speed'] ?? 10.00,
                            'is_active' => true,
                        ]);
                    }

                    $packagesMap[$row['old_package_id']] = $package->id;
                    $insertedCount++;
                }

                // Pre-seed each target POP's registration-number sequence with the
                // highest numeric suffix among literal legacy customer_code values
                // in THIS batch. Without this, a row whose legacy code was cleared
                // (duplicate) can call Pop::generateRegistrationNumber() before a
                // later row with a literal code in the same "RQ" namespace is
                // inserted — the generator only checks already-committed rows, so
                // it can hand out a number a pending literal-code row needs,
                // causing a customer_code unique-constraint crash mid-batch.
                $maxLegacyNumberByPop = [];
                foreach ($customersData as $row) {
                    if (($row['status_row'] ?? '') === 'error') {
                        continue;
                    }
                    $code = trim((string) ($row['customer_code'] ?? ''));
                    $popIdForRow = $row['pop_id'] ?? null;
                    if ($code === '' || ! $popIdForRow) {
                        continue;
                    }
                    if (preg_match('/(\d+)$/', $code, $m)) {
                        $num = (int) $m[1];
                        $maxLegacyNumberByPop[$popIdForRow] = max($maxLegacyNumberByPop[$popIdForRow] ?? 0, $num);
                    }
                }
                foreach ($maxLegacyNumberByPop as $popId => $maxNum) {
                    $seq = PopSequence::firstOrCreate(
                        ['pop_id' => $popId, 'sequence_type' => PopSequence::TYPE_REGISTRATION],
                        ['current_number' => 0]
                    );
                    if ($seq->current_number < $maxNum) {
                        $seq->current_number = $maxNum;
                        $seq->save();
                    }
                }

                // 2. Process Customers
                foreach ($customersData as $row) {
                    if (($row['status_row'] ?? '') === 'error') {
                        $this->logImportError($batch->id, $row, 'Customers', 'Baris error pada sheet Customers.');

                        continue;
                    }

                    if (empty($row['old_customer_id'])) {
                        $this->logImportError($batch->id, $row, 'Customers', 'ID pelanggan lama kosong.');

                        continue;
                    }

                    if ($this->isInternalLegacyAccountId($row['old_customer_id'])) {
                        continue; // skip internal accounts in confirm
                    }

                    $pop = ! empty($row['pop_id']) ? Pop::find($row['pop_id']) : null;
                    $distribution = null;
                    $distributionCode = trim((string) ($row['distribution_code'] ?? ''));
                    if ($distributionCode === '0') {
                        $distributionCode = '';
                    }
                    if ($distributionCode !== '' && $pop) {
                        $distribution = Distribution::firstOrCreate(
                            [
                                'code' => $distributionCode,
                            ],
                            [
                                'pop_id' => $pop->id,
                                'name' => $distributionCode,
                            ]
                        );
                    }
                    $customerCode = ! empty($row['customer_code']) ? $row['customer_code'] : ($pop?->generateRegistrationNumber() ?: $row['old_customer_id']);

                    // Backfill identitas person (rancangan-fase4-persons.md §3.2).
                    // Namespace = CABANG POP (unik per instalasi), BUKAN old_branch_id
                    // yang bertabrakan lintas dump (jetis & sandya sama-sama IDCABANG=1).
                    // Anchor = IDPENGGUNA (old_customer_id), BUKAN customer_code yang
                    // bisa di-auto-generate saat bentrok → non-deterministik. firstOrCreate
                    // memastikan import ulang memungut person yang SAMA, jadi kerja merge
                    // manual gel.2 tidak hangus. IDPENGGUNA kosong → person berdiri sendiri.
                    $person = $this->resolveLegacyPerson(
                        $pop,
                        $row['old_customer_id'] ?? null,
                    );

                    $customer = Customer::create([
                        'person_id' => $person->id,
                        'customer_code' => $customerCode,
                        'old_customer_id' => $row['old_customer_id'],
                        'old_request_id' => $row['old_request_id'] ?? null,
                        'full_name' => $row['full_name'] ?: $row['old_customer_id'],
                        'identity_number' => $row['identity_number'] ?? null,
                        'gender' => $row['gender'] ?? 'Laki-laki',
                        'customer_type' => $row['customer_type'] ?? null,
                        'company_name' => $row['company_name'] ?? null,
                        'npwp' => $row['npwp'] ?? null,
                        'primary_phone' => $this->cleanLegacyValue($row['primary_phone'] ?? $row['phone'] ?? null) ?? '',
                        'alternative_phone' => $row['alternative_phone'] ?? null,
                        'email' => $row['email'] ?? null,
                        'registration_date' => $row['registration_date'] ?? now()->format('Y-m-d'),
                        'registered_by_name' => $row['registered_by_name'] ?? null,
                        'pop_id' => $pop?->id,
                        'distribution_id' => $distribution?->id,
                        'status' => 'registered', // Default, updated by service activation or mapping
                        'created_by' => auth()->id(),
                        'foto_rumah' => $row['foto_rumah'] ?? null,
                        'foto_kontrak' => $row['foto_kontrak'] ?? null,
                        'sales_code' => $row['sales_code'] ?? null,
                        'agent_code' => $row['agent_code'] ?? null,
                        'referral_customer_code' => $row['referral_customer_code'] ?? null,
                        'address' => $this->resolveLegacyAddressText($row),
                        'latitude' => $row['latitude'] ?? null,
                        'longitude' => $row['longitude'] ?? null,
                        'city_id' => $row['city_id'] ?? null,
                        'district_id' => $row['district_id'] ?? null,
                        'village_id' => $row['village_id'] ?? null,
                    ]);

                    CustomerAddress::create([
                        'customer_id' => $customer->id,
                        'old_region_id' => $row['old_region_id'] ?? null,
                        'old_branch_id' => $row['old_branch_id'] ?? null,
                        'full_address' => $this->resolveLegacyAddressText($row),
                        'city' => $row['city_name'] ?? $row['city'] ?? null,
                        'district' => $row['district_name'] ?? $row['district'] ?? null,
                        'village' => $row['village_name'] ?? $row['village'] ?? null,
                        'city_id' => $row['city_id'] ?? null,
                        'district_id' => $row['district_id'] ?? null,
                        'village_id' => $row['village_id'] ?? null,
                        'latitude' => $row['latitude'] ?? null,
                        'longitude' => $row['longitude'] ?? null,
                        'house_photo' => $row['foto_rumah'] ?? $row['house_photo'] ?? null,
                        'contract_photo' => $row['foto_kontrak'] ?? $row['contract_photo'] ?? null,
                    ]);

                    $customer->refresh();
                    if ($customer->data_completeness_status === 'draft') {
                        $customer->updateQuietly(['data_completeness_status' => 'perlu_dilengkapi']);
                    }

                    $customersMap[$row['old_customer_id']] = $customer->id;
                    $insertedCount++;
                }

                // 3. Process Services
                foreach ($servicesData as $row) {
                    if (($row['status_row'] ?? '') === 'error') {
                        $this->logImportError($batch->id, $row, 'Services', 'Baris error pada sheet Services.');

                        continue;
                    }

                    if ($this->isInternalLegacyAccountId($row['old_customer_id'] ?? null)) {
                        continue; // skip services for internal accounts
                    }

                    $customerId = $customersMap[$row['old_customer_id']] ?? $this->findScopedCustomerId($row['old_customer_id'], $row['branch_pop_code'] ?? null);
                    $packageId = $packagesMap[$row['old_package_id']] ?? InternetPackage::where('old_package_id', $row['old_package_id'])->value('id');

                    if (! $customerId || ! $packageId) {
                        $this->logImportError($batch->id, $row, 'Services', 'Gagal memetakan Customer ID atau Package ID.');

                        continue;
                    }

                    // Scoped by customer_id, not just old_request_id: legacy request
                    // codes (RQ...) are only unique within their source branch, so a
                    // global check here would silently skip a different branch's
                    // service just because another branch already used the same
                    // raw legacy number.
                    $existingService = CustomerService::where('old_request_id', $row['old_request_id'])
                        ->where('customer_id', $customerId)
                        ->first();
                    if ($existingService) {
                        continue;
                    }

                    $package = InternetPackage::findOrFail($packageId);
                    $monthlyPrice = (float) ($row['monthly_price'] ?? $package->monthly_price);
                    $ppnPercent = 0.00; // Legacy data uses 0% PPN
                    $otherFee = (float) ($row['other_fee'] ?? 0);
                    $totalBill = $monthlyPrice; // Biaya bulanan hanya berdasarkan dari Harga paket, biaya diluar standar (other_fee) tidak masuk ke tagihan bulanan rutin

                    $customer = Customer::findOrFail($customerId);
                    $serviceStatus = $this->mapLegacyServiceStatus($row['service_status'] ?? $row['request_status'] ?? null);

                    // Create service
                    $service = CustomerService::create([
                        'customer_id' => $customerId,
                        'internet_package_id' => $packageId,
                        'old_request_id' => $row['old_request_id'],
                        'old_cost_id' => $row['old_cost_id'] ?? null,
                        'request_status' => $row['request_status'] ?? null,
                        'installation_status' => $row['installation_status'] ?? null,
                        'network_type' => $row['network_type'] ?? null,
                        'member_type' => $row['member_type'] ?? null,
                        'reason' => $row['reason'] ?? null,
                        'package_name_snapshot' => $package->name,
                        'download_speed_snapshot' => $package->download_speed_mbps,
                        'upload_speed_snapshot' => $package->upload_speed_mbps,
                        'monthly_price' => $monthlyPrice,
                        'discount' => 0.00,
                        'ppn' => $ppnPercent,
                        'other_fee' => $otherFee > 0 ? $otherFee : null,
                        'total_monthly_bill' => $totalBill,
                        // No fallback to now() here: a fabricated "activated today" date
                        // makes a decades-old migrated customer look freshly activated,
                        // which fools anything reasoning about "billed this activation
                        // period" (e.g. GenerateMonthlyInvoicesCommand's double-bill guard).
                        // Leave it null if the legacy dump genuinely has no date for it.
                        'activation_date' => $row['activation_date'] ?? null,
                        'due_date' => $row['due_date'] ?? null,
                        'service_status' => $serviceStatus,
                        'billing_status' => ($serviceStatus === 'active') ? 'active' : 'inactive',
                        'profile' => $row['profile'] ?? null,
                        'contract_type' => $row['contract_type'] ?? null,
                        'activation_time' => $row['activation_time'] ?? null,
                        'activated_by_name' => $row['activated_by_name'] ?? null,
                        'admin_filter_at' => $row['admin_filter_at'] ?? null,
                        'admin_filter_by_name' => $row['admin_filter_by'] ?? null,
                    ]);

                    // Survey creation
                    if (! empty($row['survey_date']) || ! empty($row['surveyors'])) {
                        CustomerSurvey::create([
                            'customer_id' => $customerId,
                            'survey_status' => 'completed',
                            'survey_date' => $row['survey_date'] ?? null,
                            'start_time' => $row['survey_start_time'] ?? null,
                            'end_time' => $row['survey_end_time'] ?? null,
                            'duration_minutes' => $row['survey_duration_minutes'] ?? null,
                            'surveyors' => $row['surveyors'] ?? null,
                            'assigned_at' => $row['survey_assigned_at'] ?? null,
                            'fop_id' => $row['survey_fop_id'] ?? null,
                            'required_tools' => $row['required_tools'] ?? null,
                            'survey_photo' => $row['survey_photo'] ?? null,
                            'survey_note' => $row['survey_note'] ?? null,
                        ]);
                    }

                    // Installation creation
                    if (! empty($row['installation_date']) || ! empty($row['installation_technicians'])) {
                        CustomerInstallation::create([
                            'customer_id' => $customerId,
                            'installation_status' => 'completed',
                            'scheduled_date' => $row['installation_date'] ?? null,
                            'scheduled_time' => $row['installation_start_time'] ?? null,
                            'finished_date' => $row['installation_date'] ?? null,
                            'start_time' => $row['installation_start_time'] ?? null,
                            'end_time' => $row['installation_end_time'] ?? null,
                            'technicians' => $row['installation_technicians'] ?? null,
                            'assigned_at' => $row['installation_assigned_at'] ?? null,
                            'fop_id' => $row['installation_fop_id'] ?? null,
                            'installation_photo' => $row['installation_photo'] ?? null,
                            'installation_note' => $row['installation_note'] ?? null,
                        ]);
                    }

                    $updateData = [
                        'internet_package_id' => $packageId,
                        'status' => $serviceStatus,
                    ];

                    // Generate CID if active or suspended
                    if (in_array($serviceStatus, ['active', 'suspended'], true)) {
                        $pop = $customer->pop;
                        $distribution = $customer->distribution;
                        if ($pop) {
                            $updateData['cid'] = $pop->generateComplexCid($customer, $distribution);
                        }
                    }

                    $customer->updateQuietly($updateData);
                    $customer->recalculateCompleteness();

                    // Halaman "Pelanggan Gagal"/"Putus Langganan" nampilin alasan +
                    // tanggal dari AuditLog transisi asli — data import gak pernah
                    // lewat transisi itu (updateQuietly di atas gak nyatet apa-apa),
                    // jadi kolom itu kosong buat pelanggan migrasi. Bikin AuditLog
                    // sintetis pakai ALASAN/tanggal legacy biar dua halaman itu bisa
                    // nampilin data yang sama seperti di sistem lama.
                    $legacyChangedAt = ! empty($row['status_changed_at'])
                        ? Carbon::parse($row['status_changed_at'])
                        : $customer->created_at;
                    $legacyReason = trim((string) ($row['reason'] ?? '')) ?: 'Data migrasi dari sistem lama (alasan tidak tercatat).';

                    if ($serviceStatus === 'rejected') {
                        // Fase 5.1 — stempel tanggal reject ke kolom nyata pakai
                        // tanggal legacy, supaya ORDER BY tab Gagal langsung benar
                        // tanpa harus jalankan command backfill setelah import.
                        $customer->updateQuietly(['rejected_at' => $legacyChangedAt]);

                        AuditLog::create([
                            'user_id' => $batch->uploaded_by,
                            'module' => 'Customer Workflow',
                            'action' => 'status_transition',
                            'auditable_type' => Customer::class,
                            'auditable_id' => $customer->id,
                            'old_values' => ['status' => 'registered'],
                            'new_values' => ['status' => 'rejected', 'note' => $legacyReason],
                            'ip_address' => null,
                            'user_agent' => null,
                            'created_at' => $legacyChangedAt,
                        ]);
                    } elseif ($serviceStatus === 'terminated') {
                        $customer->updateQuietly(['terminated_at' => $legacyChangedAt]);

                        AuditLog::create([
                            'user_id' => $batch->uploaded_by,
                            'module' => 'customers',
                            'action' => 'terminate',
                            'auditable_type' => Customer::class,
                            'auditable_id' => $customer->id,
                            'old_values' => ['status' => 'active'],
                            'new_values' => ['status' => 'terminated', 'reason' => $legacyReason],
                            'ip_address' => null,
                            'user_agent' => null,
                            'created_at' => $legacyChangedAt,
                        ]);

                        // Status Alat (List Putus Langganan) — pelanggan migrasi gak
                        // pernah lewat CustomerInstallationController (yang biasanya
                        // bikin CustomerDevice), jadi row-nya harus dibikin manual di
                        // sini juga, disinkron ke device_retrieved_at kalau data lama
                        // udah nyatet 'Sudah diambil'. Tanpa ini, Status Alat SELALU
                        // "Belum di Ambil" buat semua pelanggan hasil import legacy,
                        // gak peduli data aslinya gimana.
                        $deviceRetrievedStatus = trim((string) ($row['device_retrieved_status'] ?? ''));
                        if (! $customer->customerDevice) {
                            CustomerDevice::create([
                                'customer_id' => $customer->id,
                                'device_type' => 'Data Migrasi Legacy',
                                'device_retrieved_at' => $deviceRetrievedStatus === 'Sudah diambil' ? $legacyChangedAt : null,
                            ]);
                        }
                    }

                    $insertedCount++;
                }

                // 4. Process Technical Details
                foreach ($techDetailsData as $row) {
                    if (($row['status_row'] ?? '') === 'error') {
                        $this->logImportError($batch->id, $row, 'Technical Details', 'Baris error pada sheet Technical Details.');

                        continue;
                    }

                    if ($this->isInternalLegacyAccountId($row['old_customer_id'] ?? null)) {
                        continue; // skip internal account technical details
                    }

                    $customerId = $customersMap[$row['old_customer_id']] ?? $this->findScopedCustomerId($row['old_customer_id'], $row['branch_pop_code'] ?? null);

                    if (! $customerId) {
                        $this->logImportError($batch->id, $row, 'Technical Details', 'Gagal memetakan Customer ID.');

                        continue;
                    }

                    if (! empty($row['old_report_id']) && CustomerTechnicalDetail::where('old_report_id', $row['old_report_id'])->where('customer_id', $customerId)->exists()) {
                        continue;
                    }

                    CustomerTechnicalDetail::create([
                        'customer_id' => $customerId,
                        'old_report_id' => $row['old_report_id'],
                        'old_customer_id' => $row['old_customer_id'],
                        'old_request_id' => $row['old_request_id'] ?? null,
                        'connection_type' => $row['connection_type'] ?? 'FTTH',
                        'test_upload' => $row['test_upload'] ?? null,
                        'test_download' => $row['test_download'] ?? null,
                        'ssid' => $row['ssid'] ?? null,
                        'antenna_mac' => $row['antenna_mac'] ?? null,
                        'router_mac' => $row['router_mac'] ?? null,
                        'router_or_ont_serial' => $row['ont_sn'] ?? null,
                        'odp_number' => $row['odp_code'] ?? null,
                        'odp_port' => $row['odp_port'] ?? null,
                        'olt_port' => $row['olt_code'] ?? null,
                        'wireless_signal' => $row['wireless_signal'] ?? null,
                        'fiber_signal' => $row['fiber_signal'] ?? null,
                        'location_source' => $row['location_source'] ?? null,
                        'note' => $row['note'] ?? null,
                        'speedtest_photo' => $row['speedtest_photo'] ?? null,
                        'form_photo' => $row['form_photo'] ?? null,
                        'signed_form_photo' => $row['signed_form_photo'] ?? null,
                        'router_photo' => $row['router_photo'] ?? null,
                        'cable_photo' => $row['cable_photo'] ?? null,
                        'passive_device' => $row['passive_device'] ?? null,
                        'branch_number' => $row['branch_number'] ?? null,
                        'pop_number' => $row['pop_number'] ?? null,
                        'router_number' => $row['router_number'] ?? null,
                        'initial_attenuation' => $row['initial_attenuation'] ?? null,
                        'actual_attenuation' => $row['actual_attenuation'] ?? null,
                        'test_date' => $row['test_date'] ?? null,
                        'test_time' => $row['test_time'] ?? null,
                        'jitter_ms' => $row['jitter_ms'] ?? null,
                        'latency_ms' => $row['latency_ms'] ?? null,
                        'packet_loss_percent' => $row['packet_loss_percent'] ?? null,
                        'speed_conformity_percent' => $row['speed_conformity_percent'] ?? null,
                        'quality_score' => $row['quality_score'] ?? null,
                    ]);

                    // Update parent customer technical fields directly for detail compatibility
                    $customer = Customer::findOrFail($customerId);
                    $customer->updateQuietly([
                        'ont_sn' => $row['ont_sn'] ?? null,
                        'odp_code' => $row['odp_code'] ?? null,
                        'olt_code' => $row['olt_code'] ?? null,
                        'vlan_id' => $row['vlan_id'] ?? null,
                    ]);

                    $insertedCount++;
                }

                // 5. Process Invoices
                foreach ($invoicesData as $row) {
                    if (($row['status_row'] ?? '') === 'error') {
                        $this->logImportError($batch->id, $row, 'Invoices', 'Baris error pada sheet Invoices.');

                        continue;
                    }

                    if ($this->isInternalLegacyAccountId($row['old_customer_id'] ?? null)) {
                        continue; // skip internal accounts
                    }

                    $customerId = $customersMap[$row['old_customer_id'] ?? ''] ?? $this->findScopedCustomerId($row['old_customer_id'] ?? null, $row['branch_pop_code'] ?? null);
                    if (! $customerId && ! empty($row['old_request_id'])) {
                        $customerId = $this->scopeToBranchPopViaCustomer(
                            CustomerService::where('old_request_id', $row['old_request_id']),
                            $this->resolveBranchPopId($row['branch_pop_code'] ?? null)
                        )->value('customer_id');
                    }

                    if (! $customerId) {
                        $this->logImportError($batch->id, $row, 'Invoices', 'Gagal memetakan Customer ID.');

                        continue;
                    }

                    $customer = Customer::findOrFail($customerId);
                    $service = $customer->customerService;

                    if (! $service) {
                        $this->logImportError($batch->id, $row, 'Invoices', 'Layanan aktif untuk pelanggan tidak ditemukan.');

                        continue;
                    }

                    $legacyInvoiceId = $row['old_invoice_id'] ?: ($row['old_cost_id'] ?? null);
                    $existingInvoice = null;
                    if ($legacyInvoiceId || ! empty($row['old_cost_id'])) {
                        // Scoped by customer_id: old_invoice_id/old_cost_id (from
                        // legacy IDBIAYA) are only unique within their source branch.
                        $existingInvoice = Invoice::query()
                            ->where('customer_id', $customerId)
                            ->where(function ($query) use ($legacyInvoiceId, $row) {
                                if ($legacyInvoiceId) {
                                    $query->where('old_invoice_id', $legacyInvoiceId);
                                }
                                if (! empty($row['old_cost_id'])) {
                                    $query->orWhere('old_cost_id', $row['old_cost_id']);
                                }
                            })
                            ->first();
                    }

                    if ($existingInvoice) {
                        $invoicesMap[$legacyInvoiceId] = $existingInvoice->id;
                        if (! empty($row['old_cost_id'])) {
                            $invoicesMap[$row['old_cost_id']] = $existingInvoice->id;
                        }

                        continue;
                    }

                    // old_cost_id (from legacy IDBIAYA) is only unique within its
                    // source branch, but invoice_number has a global unique
                    // constraint — disambiguate with the target customer_id when
                    // another branch already claimed the same raw legacy number.
                    $invoiceNumber = 'INV-'.$legacyInvoiceId;
                    if (Invoice::where('invoice_number', $invoiceNumber)->exists()) {
                        $invoiceNumber = 'INV-'.$legacyInvoiceId.'-C'.$customerId;
                    }
                    $totalAmount = (float) $row['total_amount'];

                    // Legacy data mixes biaya pasang (install) & biaya bulanan into one
                    // record; MigrateLegacyDataCommand now splits it into one invoice
                    // per billing period and tags each with the real type.
                    $invoiceType = InvoiceType::tryFrom((string) ($row['invoice_type'] ?? ''))
                        ?? InvoiceType::BULANAN;

                    // `subtotal` diturunkan dari `total_amount`, bukan dijumlah ulang
                    // dari komponennya. Rumus lama (pasang + materai + prorata)
                    // menghitung materai DUA KALI karena `prorate_amount` yang dikirim
                    // command sudah termasuk materai, dan `installation_fee` tidak
                    // pernah ikut tersimpan — hasilnya 1.687 dari 1.707 invoice AWAL
                    // punya subtotal yang tidak nyambung dengan totalnya (mis. subtotal
                    // Rp 11.000 untuk tagihan Rp 330.000). Invoice legacy selalu
                    // PPN 0% & diskon 0, jadi subtotal = total.
                    $legacyPpn = 0.00;
                    $legacyDiscount = 0.00;
                    $subtotal = $totalAmount - $legacyPpn + $legacyDiscount;

                    $invoice = Invoice::create([
                        'invoice_number' => $invoiceNumber,
                        'invoice_type' => $invoiceType->value,
                        'old_invoice_id' => $legacyInvoiceId,
                        'old_cost_id' => $row['old_cost_id'] ?? null,
                        'old_request_id' => $row['old_request_id'] ?? null,
                        'customer_id' => $customerId,
                        'pop_id' => $customer->pop_id,
                        'customer_service_id' => $service->id,
                        'internet_package_id' => $service->internet_package_id,
                        'billing_period' => $row['billing_period'],
                        'issue_date' => $this->normalizeLegacyDate($row['issue_date'] ?? null) ?? now()->format('Y-m-d'),
                        'due_date' => $this->normalizeLegacyDate($row['due_date'] ?? null) ?? now()->addDays(10)->format('Y-m-d'),
                        'subtotal' => $subtotal,
                        'discount' => $legacyDiscount,
                        'ppn' => $legacyPpn, // Legacy invoices use 0% PPN
                        'total_amount' => $totalAmount,
                        'paid_amount' => 0.00,
                        'remaining_amount' => $totalAmount,
                        'invoice_status' => $this->mapLegacyInvoiceStatus($row['status'] ?? null),
                        'created_by' => auth()->id(),
                        'prorate_amount' => $row['prorate_amount'] ?? null,
                        'extra_cable_fee' => $row['extra_cable_fee'] ?? null,
                        'other_fee' => $row['other_fee'] ?? null,
                        'extra_installation_fee' => $row['extra_installation_fee'] ?? null,
                        'extra_pole_fee' => $row['extra_pole_fee'] ?? null,
                    ]);

                    $invoicesMap[$legacyInvoiceId] = $invoice->id;
                    if (! empty($row['old_cost_id'])) {
                        $invoicesMap[$row['old_cost_id']] = $invoice->id;
                    }
                    $insertedCount++;
                }

                // 6. Process Payments
                foreach ($paymentsData as $row) {
                    if (($row['status_row'] ?? '') === 'error') {
                        $this->logImportError($batch->id, $row, 'Payments', 'Baris error pada sheet Payments.');

                        continue;
                    }

                    if ($this->isInternalLegacyAccountId($row['old_customer_id'] ?? null)) {
                        continue; // skip internal account payments
                    }

                    // Resolve the payment's owning customer FIRST, scoped to the
                    // branch being imported. Legacy RQ/PE/IDBIAYA numbers restart
                    // from 1 in every branch dump, so the invoice lookup below MUST
                    // be constrained to this customer — kalau tidak, pembayaran bisa
                    // nyangkut ke invoice cabang lain yang kebetulan pakai nomor
                    // legacy yang sama (mis. RQ000005 ada di jetis_db & sand_db,
                    // masing-masing milik pelanggan berbeda: Hanif vs Eva).
                    $paymentCustomerId = $customersMap[$row['old_customer_id'] ?? '']
                        ?? $this->findScopedCustomerId($row['old_customer_id'] ?? null, $row['branch_pop_code'] ?? null);
                    if (! $paymentCustomerId && ! empty($row['old_request_id'])) {
                        $paymentCustomerId = $this->scopeToBranchPopViaCustomer(
                            CustomerService::where('old_request_id', $row['old_request_id']),
                            $this->resolveBranchPopId($row['branch_pop_code'] ?? null)
                        )->value('customer_id');
                    }

                    $invoiceId = $this->resolveLegacyInvoiceId($row, $invoicesMap, $paymentCustomerId ? (int) $paymentCustomerId : null);

                    if (! $invoiceId) {
                        $this->logImportError($batch->id, $row, 'Payments', 'Gagal memetakan Invoice ID.');

                        continue;
                    }

                    // Scoped by invoice_id, not just old_payment_id: legacy payment
                    // codes are only unique within their source branch.
                    if (Payment::where('old_payment_id', $row['old_payment_id'])->where('invoice_id', $invoiceId)->exists()) {
                        continue;
                    }

                    $invoice = Invoice::findOrFail($invoiceId);
                    // old_payment_id's "-PASANG" (installation-fee) variant is
                    // derived from legacy IDBIAYA, which is only unique within its
                    // source branch — disambiguate if another branch already
                    // claimed the same raw payment number.
                    $paymentNumber = 'PAY-'.$row['old_payment_id'];
                    if (Payment::where('payment_number', $paymentNumber)->exists()) {
                        $paymentNumber = 'PAY-'.$row['old_payment_id'].'-I'.$invoiceId;
                    }
                    $amount = (float) $row['amount'];
                    if ($amount <= 0) {
                        continue;
                    }

                    $payment = Payment::create([
                        'payment_number' => $paymentNumber,
                        'old_payment_id' => $row['old_payment_id'],
                        'old_transaction_id' => $row['old_transaction_id'] ?? null,
                        'old_request_id' => $row['old_request_id'] ?? null,
                        'billing_period' => $row['billing_period'] ?? null,
                        'received_by_old' => $row['received_by_old'] ?? null,
                        'deposited_by_old' => $row['deposited_by_old'] ?? null,
                        'invoice_id' => $invoiceId,
                        'customer_id' => $invoice->customer_id,
                        'pop_id' => $invoice->pop_id,
                        'payment_date' => $this->normalizeLegacyDate($row['payment_date'] ?? null) ?? now()->format('Y-m-d'),
                        'payment_method' => $this->mapLegacyPaymentMethod($row['payment_method'] ?? null),
                        'amount' => $amount,
                        'payment_status' => $this->mapLegacyPaymentStatus($row['status'] ?? null),
                        'received_by' => auth()->id(),
                        'note' => $row['note'] ?? 'Imported legacy payment',
                    ]);

                    // Update invoice paid & remaining amounts
                    $newPaidAmount = (float) $invoice->payments()->where('payment_status', PaymentStatus::VALID->value)->sum('amount');
                    $newRemaining = max(0.00, (float) $invoice->total_amount - $newPaidAmount);
                    $newStatus = $newPaidAmount <= 0
                        ? InvoiceStatus::BELUM_DIBAYAR->value
                        : ($newRemaining <= 0 ? InvoiceStatus::LUNAS->value : InvoiceStatus::SEBAGIAN->value);

                    $invoice->update([
                        'paid_amount' => $newPaidAmount,
                        'remaining_amount' => $newRemaining,
                        'invoice_status' => $newStatus,
                    ]);

                    $insertedCount++;
                }

                // Log audit batch
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'module' => 'Import Pelanggan',
                    'action' => 'confirm',
                    'auditable_type' => get_class($batch),
                    'auditable_id' => $batch->id,
                    'new_values' => ['imported_count' => $insertedCount],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                $batch->update([
                    'imported_rows' => $insertedCount,
                    'status' => 'imported',
                ]);
            });
        } catch (\Exception $e) {
            $batch->update(['status' => 'failed']);
            Log::error('Multi-sheet Import Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            // Import massal sebelumnya nol notif hasil sama sekali (docs/plan/
            // analisa-status-implementasi-notifikasi.md §5) — meski proses ini
            // sinkron (uploader langsung lihat hasil di response), notif tetap
            // ninggalin jejak di /notifications biar batch_number gak ilang
            // kalau lupa dicatat manual.
            auth()->user()?->notify(new AppNotification(
                title: 'Import Pelanggan Gagal: '.$batch->batch_number,
                message: "Import gagal — {$e->getMessage()}",
                actionUrl: route('customers.import.batch-detail', $batch->id),
                type: NotificationType::ERROR
            ));

            return redirect()->route('customers.import')->withErrors('Gagal meng-import data: '.$e->getMessage());
        }

        auth()->user()?->notify(new AppNotification(
            title: 'Import Pelanggan Selesai: '.$batch->batch_number,
            message: "Berhasil meng-import {$insertedCount} baris data dari sheet migrasi.",
            actionUrl: route('customers.import.batch-detail', $batch->id),
            type: NotificationType::SUCCESS
        ));

        return redirect()->route('customers.index')->with('success', "Berhasil meng-import {$insertedCount} baris data dari sheet migrasi! (Batch: {$batch->batch_number})");
    }

    private function resolveRegionFromAddress(string $address): ?array
    {
        $villages = Village::with('district.city')->get();
        $matches = [];

        foreach ($villages as $v) {
            $vName = $v->name;
            $dName = $v->district->name;
            $cName = $v->district->city->name;

            if (stripos($address, $vName) !== false) {
                $hasDistrict = stripos($address, $dName) !== false;
                $hasCity = stripos($address, $cName) !== false;

                $score = 1;
                if ($hasDistrict) {
                    $score += 2;
                }
                if ($hasCity) {
                    $score += 1;
                }

                if ($hasDistrict && (preg_match('/kec\b/i', $address) || preg_match('/kecamatan\b/i', $address))) {
                    if (preg_match('/kec(amatan)?\s*'.preg_quote($dName, '/').'/i', $address)) {
                        $score += 2;
                    }
                }

                $matches[] = [
                    'village' => $v,
                    'score' => $score,
                ];
            }
        }

        if (empty($matches)) {
            return null;
        }

        usort($matches, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        $best = $matches[0]['village'];

        return [
            'village_id' => $best->id,
            'village_name' => $best->name,
            'district_id' => $best->district_id,
            'district_name' => $best->district?->name,
            'city_id' => $best->district?->city_id,
            'city_name' => $best->district?->city?->name,
        ];
    }

    private function logImportError($batchId, $row, $sheetName, $message)
    {
        ImportError::create([
            'import_batch_id' => $batchId,
            'row_number' => $row['original_no'] ?? null,
            'field_name' => $sheetName,
            'error_message' => "[{$sheetName}] ".$message,
            'raw_data' => $row,
        ]);
    }

    private function isInternalLegacyAccountId(mixed $value): bool
    {
        $legacyId = strtoupper($this->cleanLegacyValue($value) ?? '');

        return $legacyId !== '' && str_starts_with($legacyId, 'PG');
    }

    private function resolveLegacyAddressText(array $row): string
    {
        $streetAddress = trim((string) ($row['full_address'] ?? $row['address'] ?? $row['ALMT'] ?? $row['ALAMAT'] ?? ''));
        if ($streetAddress !== '' && ! in_array(strtolower($streetAddress), ['-', 'null', 'n/a'], true)) {
            return $streetAddress;
        }

        $parts = array_filter([
            trim((string) ($row['village_name'] ?? $row['village'] ?? $row['DESA'] ?? '')),
            trim((string) ($row['district_name'] ?? $row['district'] ?? $row['KEC'] ?? '')),
            trim((string) ($row['city_name'] ?? $row['city'] ?? $row['KOTA'] ?? '')),
        ]);

        if ($parts !== []) {
            return implode(', ', $parts);
        }

        return $streetAddress !== '' ? $streetAddress : '-';
    }

    /**
     * Resolve (atau buat) Person untuk baris legacy berdasar anchor stabil
     * "{cabang_pop_id}:{IDPENGGUNA}". Idempoten lintas import ulang — kunci utama
     * mekanisme persons (rancangan-fase4-persons.md §3.2). IDPENGGUNA kosong →
     * person baru tanpa legacy_key (tak bisa di-anchor, tapi tetap punya identitas).
     *
     * Namespace WAJIB memakai CABANG POP, BUKAN `old_branch_id` (IDCABANG legacy).
     * Terbukti dua dump terpisah (jetis_db & sand_db) sama-sama memakai IDCABANG=1
     * dan IDPENGGUNA mulai dari PE000001, jadi "1:PE000042" bertabrakan lintas
     * cabang dan pelanggan Sandya nyantol ke person Jetis. Kelas bug yang sama
     * dengan tabrakan customer_code lintas cabang. Cabang POP unik per instalasi.
     */
    private function resolveLegacyPerson(?Pop $pop, ?string $legacyCustomerId): Person
    {
        $legacyCustomerId = trim((string) ($legacyCustomerId ?? ''));
        if ($legacyCustomerId === '') {
            return Person::create();
        }

        $branchNs = $this->resolveCabangPopId($pop) ?? 'nopop';
        $legacyKey = $branchNs.':'.$legacyCustomerId;

        return Person::firstOrCreate(['legacy_key' => $legacyKey]);
    }

    /**
     * Naik ke akar pohon POP (cabang) dari sebuah POP (bisa Mini POP). Sengaja
     * TANPA cache: walk ini murah (pohon POP dangkal, PK lookup di tabel kecil).
     * Cache keyed pop_id pernah dicoba tapi rapuh — instance controller bisa
     * di-reuse lintas request/test sementara pop_id di-reuse setelah refresh DB,
     * membuat cache menunjuk cabang yang salah. Recompute selalu benar.
     */
    private function resolveCabangPopId(?Pop $pop): ?int
    {
        if (! $pop) {
            return null;
        }

        $node = $pop;
        while ($node && $node->parent_id) {
            $node = Pop::find($node->parent_id);
        }

        return $node?->id ?? $pop->id;
    }

    private function cleanLegacyValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return in_array(strtolower($value), ['null', 'nil', 'n/a', '-'], true) ? null : $value;
    }

    private function normalizeLegacyDate(mixed $value): ?string
    {
        $value = $this->cleanLegacyValue($value);
        if ($value === null || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Sama seperti normalizeLegacyDate(), tapi jam:menit:detik dipertahankan.
     * Dipakai khusus registration_date — tabel Ringkasan Waktu & Penanggung
     * Jawab di halaman detail pelanggan nampilin jam registrasi, jadi
     * normalizeLegacyDate() yang motong ke Y-m-d bakal bikin jamnya
     * selalu 00:00:00.
     */
    private function normalizeLegacyDateTime(mixed $value): ?string
    {
        $value = $this->cleanLegacyValue($value);
        if ($value === null || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeBillingPeriod(mixed $value): ?string
    {
        $value = $this->cleanLegacyValue($value);
        if ($value === null || str_starts_with($value, '0000-00')) {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            return $value;
        }

        try {
            return Carbon::parse($value)->format('Y-m');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function normalizeCoordinate(mixed $value): ?string
    {
        $value = $this->cleanLegacyValue($value);
        if ($value === null) {
            return null;
        }

        // Replace comma with dot
        $value = str_replace(',', '.', $value);

        // Keep only digits, dots, minus sign
        $value = preg_replace('/[^\d\.\-]/', '', $value);

        if (! is_numeric($value)) {
            return null;
        }

        $floatVal = (float) $value;

        // If the absolute value is out of range (> 180), it means it's a shifted coordinate (missing decimal point)
        if (abs($floatVal) > 180) {
            // Strip any existing dot or minus sign to get only digits
            $isNegative = str_starts_with($value, '-');
            $digits = preg_replace('/[^\d]/', '', $value);

            if ($digits === '') {
                return null;
            }

            if ($isNegative) {
                // Negative coordinates in Indonesia are always latitude (around -7 or -8)
                // Place the dot after the first digit
                $normalized = '-'.substr($digits, 0, 1).'.'.substr($digits, 1);
            } else {
                // Positive coordinates
                if (str_starts_with($digits, '1')) {
                    // Longitude in Indonesia is around 110-115
                    // Place the dot after the first 3 digits
                    $normalized = substr($digits, 0, 3).'.'.substr($digits, 3);
                } else {
                    // Positive latitude
                    $normalized = substr($digits, 0, 1).'.'.substr($digits, 1);
                }
            }
            $value = $normalized;
        }

        return is_numeric($value) ? $value : null;
    }

    private function cleanDecimal(mixed $value, float $min = -999.99, float $max = 999.99): ?float
    {
        $cleaned = $this->cleanLegacyValue($value);
        if ($cleaned === null) {
            return null;
        }

        // Replace comma with dot
        $cleaned = str_replace(',', '.', $cleaned);

        // Keep only digits, dots, minus sign
        $cleaned = preg_replace('/[^\d\.\-]/', '', $cleaned);

        if (! is_numeric($cleaned)) {
            return null;
        }

        $val = (float) $cleaned;

        return ($val >= $min && $val <= $max) ? $val : null;
    }

    private function mapLegacyServiceStatus(?string $status): string
    {
        $normalized = strtolower(str_replace([' ', '-', '/'], '_', $this->cleanLegacyValue($status) ?? ''));

        return [
            'active' => 'active',
            'aktif' => 'active',
            'putus' => 'terminated',
            'berhenti' => 'terminated',
            'terminated' => 'terminated',
            'gagal' => 'rejected',
            'rejected' => 'rejected',
            // 'DISURVEI' di sistem lama = survey SUDAH selesai + admin sudah ACC
            // (DISURVEY & DIACC keduanya terisi, DIPROSES masih kosong) — itu
            // tahap "Verif & Pemasangan" (nunggu tim instalasi), BUKAN "Survey"
            // (yang berarti belum disurvey sama sekali). Ketuker sebelumnya bikin
            // pelanggan migrasi nyasar ke antrian Survey padahal harusnya di
            // antrian Verif & Pemasangan.
            'disurvei' => 'waiting_installation',
            'survey' => 'waiting_survey',
            'menunggu_survey' => 'waiting_survey',
            // 'PENGAJUAN' = request udah diajukan di sistem lama (masuk tabel
            // prosedure_permintaan_wifi), DISURVEY masih kosong = belum disurvey
            // sama sekali. Itu tahap "Survey" (nunggu dijadwalkan), bukan
            // "registered" polos — kalau dipetakan ke registered, pelanggan ini
            // gak nongol di antrian Survey sampai ada yang manual mulai survey.
            'pengajuan' => 'waiting_survey',
            'calon_pelanggan' => 'registered',
            'terdaftar' => 'registered',
            'registered' => 'registered',
            'menunggu_pemasangan' => 'waiting_installation',
            'waiting_installation' => 'waiting_installation',
            'isolir' => 'suspended',
            'suspended' => 'suspended',
            '' => 'registered',
        ][$normalized] ?? $normalized;
    }

    private function mapLegacyInvoiceStatus(mixed $status): string
    {
        $normalized = strtolower(str_replace([' ', '-'], '_', $this->cleanLegacyValue($status) ?? ''));

        return match ($normalized) {
            'lunas', 'paid' => InvoiceStatus::LUNAS->value,
            'sebagian', 'partial' => InvoiceStatus::SEBAGIAN->value,
            'batal', 'cancelled' => InvoiceStatus::BATAL->value,
            'belum_dibayar', 'unpaid', '' => InvoiceStatus::BELUM_DIBAYAR->value,
            default => InvoiceStatus::BELUM_DIBAYAR->value,
        };
    }

    private function mapLegacyPaymentMethod(mixed $method): string
    {
        $normalized = strtolower(str_replace([' ', '-'], '_', $this->cleanLegacyValue($method) ?? 'cash'));

        return [
            'tunai' => 'cash',
            'cash' => 'cash',
            'transfer' => 'transfer',
            'bank_transfer' => 'transfer',
            'qris' => 'qris',
        ][$normalized] ?? 'lainnya';
    }

    private function mapLegacyPaymentStatus(mixed $status): string
    {
        $normalized = strtolower(str_replace([' ', '-'], '_', $this->cleanLegacyValue($status) ?? 'valid'));

        return match ($normalized) {
            // 'pending' legacy tak lagi punya padanan — PaymentStatus::PENDING
            // dihapus (sistem ini tak punya alur verifikasi bertahap, semua
            // jalur insert baru selalu VALID langsung). Data legacy berstatus
            // "pending" berarti sudah tercatat sebagai transaksi, jadi masuk
            // akal dipetakan VALID juga, bukan dibuang atau diberi status
            // yang tak ada lagi.
            'valid', 'diterima', 'lunas', 'pending', '' => PaymentStatus::VALID->value,
            'ditolak', 'rejected', 'batal' => PaymentStatus::DITOLAK->value,
            default => PaymentStatus::VALID->value,
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $invoicesMap
     * @param  int|null  $customerId  Pelanggan pemilik pembayaran (sudah di-scope
     *                                per cabang). Semua fallback DB di bawah
     *                                dibatasi ke pelanggan ini.
     */
    private function resolveLegacyInvoiceId(array $row, array $invoicesMap, ?int $customerId = null): ?int
    {
        foreach (['old_invoice_id', 'old_transaction_id'] as $field) {
            $key = $row[$field] ?? null;
            if ($key && isset($invoicesMap[$key])) {
                return $invoicesMap[$key];
            }
        }

        // Tiap fallback DB di bawah mencocokkan nomor legacy mentah
        // (old_invoice_id / old_cost_id / old_request_id) yang HANYA unik dalam
        // cabang asalnya. Batasi tiap lookup ke pelanggan pemilik pembayaran biar
        // tabrakan lintas cabang (nomor RQ/IDBIAYA yang sama dipakai ulang di
        // cabang lain) gak diam-diam menempelkan pembayaran ini ke invoice milik
        // pelanggan cabang lain. Tanpa scope ini, pembayaran Eva (sand_db,
        // RQ000005) bisa nyangkut ke invoice Hanif (jetis_db, RQ000005).
        $scope = fn ($query) => $customerId ? $query->where('customer_id', $customerId) : $query;

        if (! empty($row['old_invoice_id'])) {
            $invoiceId = $scope(Invoice::where('old_invoice_id', $row['old_invoice_id']))->value('id');
            if ($invoiceId) {
                return (int) $invoiceId;
            }
        }

        if (! empty($row['old_transaction_id'])) {
            $invoiceId = $scope(Invoice::where('old_cost_id', $row['old_transaction_id']))->value('id');
            if ($invoiceId) {
                return (int) $invoiceId;
            }
        }

        if (! empty($row['old_request_id']) && ! empty($row['billing_period'])) {
            $invoiceId = $scope(Invoice::where('old_request_id', $row['old_request_id'])
                ->where('billing_period', $row['billing_period']))->value('id');
            if ($invoiceId) {
                return (int) $invoiceId;
            }
        }

        return null;
    }

    public function activate(Customer $customer)
    {
        if (! $customer->exists) {
            $customer = Customer::findOrFail(request()->route('customer'));
        }

        $hasWorkflowTask = $customer->tasks()
            ->whereIn('task_type', [TaskType::SURVEY->value, TaskType::PEMASANGAN->value])
            ->exists();
        if ($hasWorkflowTask) {
            return $this->redirectToCustomer($customer)
                ->with('error', 'Aktivasi manual hanya untuk data migrasi lama. Pelanggan ini punya riwayat Task Survey/Pemasangan, harus diaktifkan lewat alur verifikasi normal.');
        }

        // Hanya untuk pelanggan migrasi yang TERBUKTI sudah aktif di sistem lama
        // (request_status legacy = ACTIVE). Pelanggan migrasi yang di sistem lama
        // masih nyangkut di SRV/PSB (PENGAJUAN/DIPROSES/GAGAL) tetap harus lewat
        // alur SRV/PSB normal di sistem baru, bukan di-bypass di sini.
        $legacyRequestStatus = $customer->customerService?->request_status;
        if (! $customer->old_customer_id || $legacyRequestStatus !== 'ACTIVE') {
            return $this->redirectToCustomer($customer)
                ->with('error', 'Aktivasi manual hanya untuk pelanggan migrasi yang terbukti sudah aktif di sistem lama.');
        }

        $completeness = $customer->dataCompleteness();
        if (! $completeness['is_ready_billing']) {
            return $this->redirectToCustomer($customer)
                ->with('error', 'Pelanggan tidak bisa diaktifkan karena data wajib belum lengkap.');
        }

        if (! $customer->internet_package_id) {
            return $this->redirectToCustomer($customer)
                ->with('error', 'Pelanggan tidak memiliki paket internet aktif.');
        }

        $service = $customer->customerService;
        if (! $service) {
            return $this->redirectToCustomer($customer)
                ->with('error', 'Data layanan pelanggan tidak ditemukan.');
        }

        if ($service->total_monthly_bill <= 0) {
            return $this->redirectToCustomer($customer)
                ->with('error', 'Total tagihan bulanan tidak valid (harus lebih besar dari 0).');
        }

        $pop = $customer->pop;
        if (! $pop) {
            return $this->redirectToCustomer($customer)
                ->with('error', 'POP/Cabang pelanggan tidak ditemukan.');
        }

        if (! $pop->cid_prefix) {
            return $this->redirectToCustomer($customer)
                ->with('error', 'Konfigurasi prefix CID pada POP asal pelanggan belum lengkap. Pastikan field cid_prefix terisi.');
        }

        // Load relasi teknis dan distribusi untuk generate CID kompleks
        $customer->loadMissing(['customerTechnicalDetail', 'distribution', 'village']);
        DB::transaction(function () use ($customer, $service, $pop) {
            // Generate CID kompleks: {pop.cid_prefix}{olt_number}{dist_code}{customer_code}_{village}_{name}
            // Contoh: D2X6CRQ001296_MANGKUJAYAN_DYAHGALUH
            $distribution = $customer->distribution;
            $cid = $pop->generateComplexCid($customer, $distribution);

            $oldValues = [
                'cid' => $customer->cid,
                'status' => $customer->status,
                'data_completeness_status' => $customer->data_completeness_status,
                'service_status' => $service->service_status,
                'billing_status' => $service->billing_status,
            ];

            $customer->update([
                'cid' => $cid,
                'status' => 'active',
                'data_completeness_status' => 'siap_billing',
            ]);

            $service->update([
                'service_status' => 'aktif',
                'billing_status' => 'active',
                'activated_by_name' => auth()->user()->name,
                'activated_by_user_id' => auth()->id(),
                'activation_time' => $service->activation_time ?? now()->format('H:i:s'),
            ]);

            $newValues = [
                'cid' => $cid,
                'status' => 'active',
                'data_completeness_status' => 'siap_billing',
                'service_status' => 'aktif',
                'billing_status' => 'active',
            ];

            AuditLog::create([
                'user_id' => auth()->id(),
                'module' => 'Data Pelanggan',
                'action' => 'activate',
                'auditable_type' => get_class($customer),
                'auditable_id' => $customer->id,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);

            try {
                $telegram = app(TelegramBotService::class);
                $message = "🎉 <b>Pelanggan Aktif</b>\n";
                $message .= "Pelanggan: {$customer->full_name}\n";
                $message .= "CID: {$cid}\n";
                $message .= "No. HP: {$customer->primary_phone}\n";
                $message .= "POP: {$customer->pop->name}\n";
                $message .= 'Telah berhasil diaktivasi dan masuk siklus penagihan.';
                $telegram->sendMessage($message);
            } catch (\Exception $e) {
                Log::error('Gagal mengirim notifikasi Telegram: '.$e->getMessage());
            }
        });

        // Reload untuk mendapatkan CID yang baru disimpan
        $customer->refresh();

        return $this->redirectToCustomer($customer)
            ->with('success', "Layanan pelanggan berhasil diaktifkan! CID: {$customer->cid}");
    }

    /**
     * S5-T003 — Buat Tagihan Manual
     * Handle POST request to create a manual invoice for a customer.
     */
    public function storeManualInvoice(Request $request, Customer $customer)
    {
        if (! $customer->exists) {
            $customer = Customer::findOrFail($request->route('customer'));
        }

        // 1. Authorization checks
        if (! auth()->user()->hasPermission('invoices.create')) {
            abort(403, 'Anda tidak memiliki akses untuk membuat tagihan.');
        }

        // Scope check for user's assigned POPs
        if (! Customer::query()->applyUserScope()->where('id', $customer->id)->exists()) {
            abort(403, 'Anda tidak memiliki akses ke data pelanggan di POP ini.');
        }

        // 2. Validate request
        // Nominal prorata & biaya tambahan diketik berformat ribuan.
        $request->merge(RupiahInput::parseKeys(
            $request->only(['prorate_amount', 'extra_cable_fee', 'extra_installation_fee', 'extra_pole_fee']),
            'prorate_amount',
            'extra_cable_fee',
            'extra_installation_fee',
            'extra_pole_fee',
        ));

        $validated = $request->validate([
            'billing_period' => 'required|date_format:Y-m',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issue_date',
            'invoice_type' => ['required', Rule::enum(InvoiceType::class)],
            'prorate_amount' => 'nullable|numeric|min:0',
            'extra_cable_fee' => 'nullable|numeric|min:0',
            'extra_installation_fee' => 'nullable|numeric|min:0',
            'extra_pole_fee' => 'nullable|numeric|min:0',
        ]);

        $billingPeriod = $validated['billing_period'];
        $issueDate = $validated['issue_date'];
        $dueDate = $validated['due_date'];
        $invoiceType = InvoiceType::from($validated['invoice_type']);
        $prorateAmount = (float) ($validated['prorate_amount'] ?? 0);
        $extraCableFee = (float) ($validated['extra_cable_fee'] ?? 0);
        $extraInstallationFee = (float) ($validated['extra_installation_fee'] ?? 0);
        $extraPoleFee = (float) ($validated['extra_pole_fee'] ?? 0);

        // 3. Business logic checks
        // Cek pelanggan aktif/siap billing
        if (! in_array($customer->status, ['active', 'suspended']) && $customer->data_completeness_status !== 'siap_billing') {
            return redirect()->back()->withErrors(['error' => 'Tagihan hanya bisa dibuat untuk pelanggan dengan status aktif atau siap billing.']);
        }

        $service = $customer->customerService;
        if (! $service) {
            return redirect()->back()->withErrors(['error' => 'Pelanggan tidak memiliki layanan aktif.']);
        }

        // Cek invoice dobel untuk periode + jenis tagihan yang sama (bukan
        // seluruh periode, karena AWAL dan BULANAN sah muncul bersamaan di
        // periode yang sama — misal saat reaktivasi).
        $exists = Invoice::where('customer_id', $customer->id)
            ->where('billing_period', $billingPeriod)
            ->where('invoice_type', $invoiceType->value)
            ->exists();

        if ($exists) {
            return redirect()->back()->withErrors(['billing_period' => "Tagihan {$invoiceType->label()} untuk periode {$billingPeriod} sudah pernah dibuat untuk pelanggan ini."]);
        }

        // 4. Generate invoice number sequentially (e.g., format INV-YYYYMM-[counter] where counter increment is locked for update)
        $periodCode = str_replace('-', '', $billingPeriod);

        $invoice = DB::transaction(function () use (
            $customer, $service, $billingPeriod, $issueDate, $dueDate, $periodCode, $invoiceType,
            $prorateAmount, $extraCableFee, $extraInstallationFee, $extraPoleFee
        ) {
            $lastInvoice = Invoice::where('invoice_number', 'like', "INV-{$periodCode}-%")
                ->orderBy('invoice_number', 'desc')
                ->lockForUpdate()
                ->first();

            $nextSeq = 1;
            if ($lastInvoice) {
                $parts = explode('-', $lastInvoice->invoice_number);
                if (count($parts) === 3) {
                    $nextSeq = ((int) $parts[2]) + 1;
                }
            }
            $invoiceNumber = sprintf('INV-%s-%04d', $periodCode, $nextSeq);

            // Rincian biaya dari service snapshot
            $subtotal = (float) $service->monthly_price;
            $discount = (float) ($service->discount ?? 0.00);
            $ppnPercent = (float) ($service->ppn ?? 0.00);

            // Hitung PPN dari (subtotal - discount) × rate
            $afterDiscount = max(0, $subtotal - $discount);
            $ppnAmount = round($afterDiscount * ($ppnPercent / 100), 2);
            $nettMonthly = $afterDiscount + $ppnAmount;

            // Total = tagihan bulanan nett + semua biaya tambahan
            $totalAmount = $nettMonthly + $prorateAmount + $extraCableFee + $extraInstallationFee + $extraPoleFee;
            $paidAmount = 0.00;
            $remainingAmount = $totalAmount;

            $newInvoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'invoice_type' => $invoiceType->value,
                'customer_id' => $customer->id,
                'pop_id' => $customer->pop_id,
                'customer_service_id' => $service->id,
                'internet_package_id' => $service->internet_package_id,
                'billing_period' => $billingPeriod,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'ppn' => $ppnPercent,
                'prorate_amount' => $prorateAmount > 0 ? $prorateAmount : null,
                'extra_cable_fee' => $extraCableFee > 0 ? $extraCableFee : null,
                'extra_installation_fee' => $extraInstallationFee > 0 ? $extraInstallationFee : null,
                'extra_pole_fee' => $extraPoleFee > 0 ? $extraPoleFee : null,
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'invoice_status' => InvoiceStatus::BELUM_DIBAYAR->value,
                'created_by' => auth()->id(),
            ]);

            // Save changes to audit log (Sprint 8: audit_logs)
            AuditLog::create([
                'user_id' => auth()->id(),
                'module' => 'Tagihan',
                'action' => 'create',
                'auditable_type' => get_class($newInvoice),
                'auditable_id' => $newInvoice->id,
                'old_values' => null,
                'new_values' => $newInvoice->toArray(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);

            return $newInvoice;
        });

        return $this->redirectToCustomer($customer)
            ->with('success', "Tagihan manual dengan nomor {$invoice->invoice_number} berhasil dibuat!");
    }
}
