<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\City;
use App\Models\District;
use App\Models\Village;
use App\Models\InternetPackage;
use App\Models\Distribution;
use App\Models\SubscriptionStatus;
use App\Models\Pop;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\CustomerTechnicalDetail;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\CustomerValidationService;
use App\Support\IndonesianDate;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Spatie\SimpleExcel\SimpleExcelReader;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    /**
     * Display a listing of the customers with search and filters.
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $statusGroup = trim((string) $request->query('status_group', ''));
        // Default to empty string '' (Semua active & suspend) if not specified
        $status = $request->query('status', '');
        $districtId = $request->query('district_id', '');
        $packageId = $request->query('package_id', '');
        $popId = $request->query('pop_id', '');
        $completenessStatus = $request->query('completeness_status', '');

        $query = Customer::query()
            ->applyUserScope()
            ->with(['city', 'district', 'village', 'internetPackage', 'subscriptionStatus', 'pop', 'distribution', 'customerAddress']);

        // Search filter
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('customer_code', 'like', "%{$search}%")
                  ->orWhere('old_customer_id', 'like', "%{$search}%")
                  ->orWhere('old_request_id', 'like', "%{$search}%")
                  ->orWhere('cid', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('primary_phone', 'like', "%{$search}%")
                  ->orWhere('identity_number', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($status !== '') {
            $query->where('status', $status);
        } elseif ($statusGroup !== '') {
            $statuses = match ($statusGroup) {
                'survey' => ['waiting_survey', 'surveyed'],
                'verification' => ['waiting_installation', 'installed'],
                'failed' => ['failed', 'rejected', 'gagal'],
                'terminated' => ['terminated', 'putus'],
                default => []
            };
            if (!empty($statuses)) {
                $query->whereIn('status', $statuses);
            }
        } else {
            // Default view shows only active & suspended customers
            $query->whereIn('status', ['active', 'suspended']);
        }

        // District filter
        if ($districtId !== '') {
            $query->where('district_id', $districtId);
        }

        // Service package filter
        if ($packageId !== '') {
            $query->where('internet_package_id', $packageId);
        }

        // POP filter
        if ($popId !== '') {
            $query->where('pop_id', $popId);
        }

        // Completeness status filter
        if ($completenessStatus !== '') {
            $query->where('data_completeness_status', $completenessStatus);
        }

        $customers = $query->orderBy('customer_code', 'asc')->paginate(10)->withQueryString();

        // Data for filter selects
        $districts = District::orderBy('name')->get();
        $packages = InternetPackage::orderBy('name')->get();
        $pops = Pop::forUser()->orderBy('name')->get();
        $subscriptionStatuses = SubscriptionStatus::query()
            ->where('is_active', true)
            ->orderBy('workflow_order')
            ->get();

        // Customer count by status (for badge list / submenus)
        $statusCounts = Customer::applyUserScope()->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Total is active + suspended customers
        $totalCustomers = ($statusCounts['active'] ?? 0) + ($statusCounts['suspended'] ?? 0);

        return view('customers.index', compact(
            'customers', 
            'districts', 
            'packages', 
            'pops',
            'statusCounts', 
            'totalCustomers',
            'subscriptionStatuses',
            'search',
            'status',
            'statusGroup',
            'districtId',
            'packageId',
            'popId',
            'completenessStatus'
        ));
    }

    /**
     * Show the form for creating a new customer.
     */
    public function create()
    {
        $districts = \App\Models\District::orderBy('name')->get();
        $packages = \App\Models\InternetPackage::orderBy('name')->get();
        $cities = \App\Models\City::orderBy('name')->get();
        $pops = \App\Models\Pop::forUser()->where('type', 'cabang')->get();
        $distributions = \App\Models\Distribution::with('pop')->orderBy('name')->get();
        return view('customers.create', compact('districts', 'packages', 'cities', 'pops', 'distributions'));
    }

    /**
     * Store a newly created customer in storage.
     */
    public function store(\App\Http\Requests\CustomerRegistrationRequest $request)
    {
        $validated = $request->validated();

        $validated['phone'] = $validated['primary_phone'];
        $validated['created_by'] = auth()->id();
        $validated['status'] = 'waiting_survey';
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
        $validated['customer_status'] = $statusMapping[$validated['status']] ?? 'calon_pelanggan';

        $fotoKtp = $request->file('foto_ktp');
        unset($validated['foto_ktp']);
        $fotoRumah = $request->file('foto_rumah');
        unset($validated['foto_rumah']);
        $fotoKontrak = $request->file('foto_kontrak');
        unset($validated['foto_kontrak']);

        // Generate customer_code via POP sequence generator
        $pop = Pop::findOrFail($validated['pop_id']);
        $customerCode = $pop->generateRegistrationNumber();
        $validated['customer_code'] = $customerCode;

        $customer = \Illuminate\Support\Facades\DB::transaction(function() use ($validated, $fotoKtp, $fotoRumah, $fotoKontrak) {
            // 1. Create customer record
            $customer = Customer::create($validated);

            $updates = [];
            if ($fotoKtp instanceof UploadedFile) {
                $updates['foto_ktp'] = \App\Services\FileUploadService::uploadCustomerRegistrationDoc($fotoKtp, $customer, 'ktp');
            }
            if ($fotoRumah instanceof UploadedFile) {
                $updates['foto_rumah'] = \App\Services\FileUploadService::uploadSurveyPhoto($fotoRumah, $customer, 'house');
            }
            if ($fotoKontrak instanceof UploadedFile) {
                $updates['foto_kontrak'] = \App\Services\FileUploadService::uploadInstallationPhoto($fotoKontrak, $customer, 'kontrak');
            }
            if (!empty($updates)) {
                $customer->update($updates);
            }

            // 2. Create customer address
            $cityName = null;
            if (!empty($validated['city_id'])) {
                $cityName = \App\Models\City::where('id', $validated['city_id'])->value('name');
            }
            $districtName = null;
            if (!empty($validated['district_id'])) {
                $districtName = \App\Models\District::where('id', $validated['district_id'])->value('name');
            }
            $villageName = null;
            if (!empty($validated['village_id'])) {
                $villageName = \App\Models\Village::where('id', $validated['village_id'])->value('name');
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
                'ktp_photo' => $validated['foto_ktp'] ?? null,
                'contract_photo' => $validated['foto_kontrak'] ?? null,
            ]);

            // 3. Create customer service if package is chosen
            if (!empty($validated['internet_package_id'])) {
                $package = \App\Models\InternetPackage::findOrFail($validated['internet_package_id']);
                
                $monthlyPrice = (float)$package->monthly_price;
                $discount = (float)($validated['discount_amount'] ?? 0.00);
                $ppn = (float)($validated['tax_percent'] ?? 0.00);
                $otherFee = (float)($validated['other_fee'] ?? 0.00);

                // Calculate total bill
                $discountedPrice = max(0, $monthlyPrice - $discount);
                $totalBill = $discountedPrice * (1 + $ppn / 100) + $otherFee;

                $downLabel = isset($package->download_speed_mbps) ? $package->download_speed_mbps . ' Mbps' : null;
                $upLabel = isset($package->upload_speed_mbps) ? $package->upload_speed_mbps . ' Mbps' : null;

                $activationDate = $validated['registration_date'] ?? null;
                $dueDate = null;
                if ($activationDate) {
                    $dueDate = \Carbon\Carbon::parse($activationDate)->addMonth()->format('Y-m-d');
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
                    'service_status' => $validated['customer_status'],
                    'billing_status' => ($validated['status'] === 'active' || $validated['customer_status'] === 'aktif') ? 'active' : 'pending',
                ]);
            }

            // 4. Evaluate data completeness via service and flash warning to user
            $customer->load('customerService');
            /** @var CustomerValidationService $validationService */
            $validationService = app(CustomerValidationService::class);
            $completenessResult = $validationService->validate($customer);

            if (! empty($completenessResult['missing_required'])) {
                $missingLabels = array_values($completenessResult['missing_required']);
                session()->flash('warning', 'Data pelanggan disimpan sebagai "' . ucwords(str_replace('_', ' ', $completenessResult['completeness_status'])) . '", tetapi masih memerlukan data berikut agar Lengkap: ' . implode(', ', $missingLabels));
            }

            // 5. Sentralisasi Tiket: Auto-create Task antrean (Survey)
            $year  = date('Y');
            $count = \App\Models\Task::whereYear('created_at', $year)->count() + 1;
            \App\Models\Task::create([
                'task_number' => sprintf('TASK-%s-%04d', $year, $count),
                'task_type'   => \App\Enums\TaskType::SURVEY->value,
                'title'       => 'Survey Calon Pelanggan: ' . $customer->full_name,
                'description' => null,
                'pop_id'      => $customer->pop_id,
                'customer_id' => $customer->id,
                'status'      => \App\Enums\TaskStatus::PENDING->value,
                'created_by'  => auth()->id() ?? 1,
                'updated_by'  => auth()->id() ?? 1,
            ]);

            return $customer;
        });

        return redirect()->route('customers.index')->with('success', "Pelanggan {$validated['full_name']} berhasil ditambahkan dengan ID REG {$customerCode}!");
    }

    /**
     * Assign a survey to a technician and transition status to waiting_survey.
     */
    public function assignSurvey(Request $request, Customer $customer, \App\Services\CustomerWorkflowService $workflowService)
    {
        abort_unless(auth()->user()->hasPermission('customers.update'), 403);

        $validated = $request->validate([
            'technician_id' => 'required|exists:users,id',
            'scheduled_date' => 'required|date',
            'note' => 'nullable|string',
        ]);

        \Illuminate\Support\Facades\DB::transaction(function() use ($customer, $validated, $workflowService) {
            // Create the initial customer_surveys record with pending status
            \App\Models\CustomerSurvey::create([
                'customer_id' => $customer->id,
                'technician_id' => $validated['technician_id'],
                'survey_date' => $validated['scheduled_date'],
                'assigned_at' => now(),
                'survey_status' => 'pending',
                'survey_note' => $validated['note'] ?? null,
            ]);

            // Transition customer status
            $workflowService->transition($customer, \App\Enums\WorkflowTransition::WAITING_SURVEY, 'Assigned to survey');
        });

        return redirect()->back()->with('success', 'Pelanggan berhasil di-assign ke tim survey.');
    }

    /**
     * Show the form for editing the specified customer.
     */
    public function edit(Customer $customer)
    {
        if (!$customer->exists) {
            $customer = Customer::findOrFail(request()->route('customer'));
        }

        $districts = \App\Models\District::orderBy('name')->get();
        $packages = \App\Models\InternetPackage::orderBy('name')->get();
        $cities = \App\Models\City::orderBy('name')->get();
        $pops = \App\Models\Pop::forUser()->where('type', 'cabang')->get();
        $distributions = \App\Models\Distribution::with('pop')->orderBy('name')->get();
        return view('customers.edit', compact('customer', 'districts', 'packages', 'cities', 'pops', 'distributions'));
    }

    /**
     * Update the specified customer in storage.
     */
    public function update(Request $request, Customer $customer)
    {
        // Ensure customer is resolved even if route model binding is bypassed in tests
        if (!$customer->exists) {
            $customer = Customer::findOrFail($request->route('customer'));
        }

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
            'ip_address' => 'nullable|string|max:45',
            'odp_code' => 'nullable|string|max:50',
            'olt_code' => 'nullable|string|max:50',
            'vlan_id' => 'nullable|string|max:20',
            
            // Status
            'status' => 'required|string|max:50',
        ]);

        $validated['phone'] = $validated['primary_phone'];
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
        $validated['customer_status'] = $statusMapping[$validated['status']] ?? 'calon_pelanggan';

        $originalFiles = Customer::query()
            ->whereKey($customer->getKey())
            ->first(['foto_ktp', 'foto_rumah', 'foto_kontrak'])
            ?->only(['foto_ktp', 'foto_rumah', 'foto_kontrak']) ?? [
                'foto_ktp' => null,
                'foto_rumah' => null,
                'foto_kontrak' => null,
            ];

        // Handle deletions
        if ($request->input('delete_foto_ktp') == '1') {
            $validated['foto_ktp'] = null;
        } else {
            $validated['foto_ktp'] = $customer->foto_ktp;
        }
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
        if ($request->hasFile('foto_ktp')) {
            $validated['foto_ktp'] = \App\Services\FileUploadService::uploadCustomerRegistrationDoc($request->file('foto_ktp'), $customer, 'ktp');
        }
        if ($request->hasFile('foto_rumah')) {
            $validated['foto_rumah'] = \App\Services\FileUploadService::uploadSurveyPhoto($request->file('foto_rumah'), $customer, 'house');
        }
        if ($request->hasFile('foto_kontrak')) {
            $validated['foto_kontrak'] = \App\Services\FileUploadService::uploadInstallationPhoto($request->file('foto_kontrak'), $customer, 'kontrak');
        }

        \Illuminate\Support\Facades\DB::transaction(function() use ($customer, $validated) {
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
            if (!empty($validated['city_id'])) {
                $cityName = \App\Models\City::where('id', $validated['city_id'])->value('name');
            }
            $districtName = null;
            if (!empty($validated['district_id'])) {
                $districtName = \App\Models\District::where('id', $validated['district_id'])->value('name');
            }
            $villageName = null;
            if (!empty($validated['village_id'])) {
                $villageName = \App\Models\Village::where('id', $validated['village_id'])->value('name');
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
                'ktp_photo' => $validated['foto_ktp'] ?? null,
                'contract_photo' => $validated['foto_kontrak'] ?? null,
            ]);

            // 3. Update customer service
            if (!empty($validated['internet_package_id'])) {
                $package = \App\Models\InternetPackage::findOrFail($validated['internet_package_id']);
                
                $monthlyPrice = (float)$package->monthly_price;
                $discount = (float)($validated['discount_amount'] ?? 0.00);
                $ppn = (float)($validated['tax_percent'] ?? 0.00);
                $otherFee = (float)($validated['other_fee'] ?? 0.00);

                // Calculate total bill
                $discountedPrice = max(0, $monthlyPrice - $discount);
                $totalBill = $discountedPrice * (1 + $ppn / 100) + $otherFee;

                $downLabel = isset($package->download_speed_mbps) ? $package->download_speed_mbps . ' Mbps' : null;
                $upLabel = isset($package->upload_speed_mbps) ? $package->upload_speed_mbps . ' Mbps' : null;

                $activationDate = $validated['registration_date'] ?? null;
                $dueDate = null;
                if ($activationDate) {
                    $dueDate = \Carbon\Carbon::parse($activationDate)->addMonth()->format('Y-m-d');
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
                    'service_status' => $validated['customer_status'],
                    'billing_status' => ($validated['status'] === 'active' || $validated['customer_status'] === 'aktif') ? 'active' : 'pending',
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
                session()->flash('warning', 'Data pelanggan berhasil diperbarui dengan status "' . ucwords(str_replace('_', ' ', $completenessResult['completeness_status'])) . '", tetapi masih memerlukan data berikut agar Lengkap: ' . implode(', ', $missingLabels));
            }
        });

        foreach ($originalFiles as $field => $path) {
            if ($path && $path !== ($validated[$field] ?? null)) {
                $disk = \Illuminate\Support\Facades\Storage::disk('public');
                $disk->delete($path);

                $absolutePath = $disk->path($path);
                if (File::isFile($absolutePath)) {
                    File::delete($absolutePath);
                }
            }
        }

        return redirect()->route('customers.show', $customer->id)->with('success', "Data pelanggan {$customer->full_name} berhasil diperbarui!");
    }

    /**
     * Remove the specified customer from storage.
     */
    public function destroy(Customer $customer)
    {
        abort_unless(auth()->user()->hasPermission('customers.delete'), 403);
        
        \Illuminate\Support\Facades\DB::transaction(function() use ($customer) {
            $customer->delete();
        });
        
        return redirect()->back()->with('success', "Data pelanggan berhasil dihapus!");
    }

    public function show(Customer $customer)
    {
        if (!$customer->exists) {
            $customer = Customer::findOrFail(request()->route('customer'));
        }

        $customer->load([
            'city', 
            'district', 
            'village', 
            'internetPackage', 
            'subscriptionStatus', 
            'pop', 
            'customerAddress', 
            'customerService', 
            'customerTechnicalDetail',
            'creator', 
            'updater',
            'installations.technician',
            'customerDevice',
            'documents.uploader',
            'invoices' => function ($query) {
                $query->orderBy('billing_period', 'desc');
            },
            'payments' => function ($query) {
                $query->with(['invoice', 'receiver'])->latest('payment_date')->latest('id');
            },
        ]);

        $status = $customer->status;
        $regDate = \Carbon\Carbon::parse($customer->registration_date);
        
        // Dynamic completeness calculation
        $completeness = $customer->dataCompleteness();

        // Format display ID berdasarkan siklus hidup pelanggan
        // menggunakan accessor display_id di Customer model (sesuai spesifikasi-pop-distribusi-cid.md):
        // - REQ ID murni (RQ######): pending, survey, pemasangan, installed, terminated
        // - CID lengkap (D2X6CRQ######_DESA_NAMA): aktif + punya distribusi
        // - Format default (C00RQ######): aktif tanpa distribusi
        $customer->load(['pop', 'distribution']); // pastikan relasi dimuat untuk accessor
        $displayId = $customer->display_id;
        $displayIdLabel = $customer->display_id_label;

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

        // Generate dynamic timeline based on status
        $timeline = [
            [
                'step' => 'Registrasi',
                'title' => 'Pendaftaran Pelanggan',
                'date' => IndonesianDate::date($regDate),
                'notes' => 'Pendaftaran berhasil dengan kode registrasi ' . $customer->customer_code,
                'status' => 'completed',
            ],
            [
                'step' => 'Survey',
                'title' => 'Survey Lokasi & Kelayakan',
                'date' => $statusRank >= 3 ? IndonesianDate::date($regDate->copy()->addDays(2)) : '-',
                'notes' => $statusRank >= 3 
                    ? 'Hasil: LAYAK (Kabel dropcore 85m, ODP terdekat: ' . ($customer->odp_code ?? '-') . ')'
                    : ($statusRank == 2 ? 'Sedang dijadwalkan / dalam antrean.' : 'Menunggu penyelesaian tahap sebelumnya.'),
                'status' => $statusRank >= 3 ? 'completed' : ($statusRank == 2 ? 'current' : 'pending'),
            ],
            [
                'step' => 'Pemasangan',
                'title' => 'Penarikan Kabel & Pemasangan ONT',
                'date' => $statusRank >= 5 ? IndonesianDate::date($regDate->copy()->addDays(4)) : '-',
                'notes' => $statusRank >= 5 
                    ? 'Pemasangan ONT selesai (SN: ' . ($customer->ont_sn ?? '-') . '). Redaman awal: -17.80 dBm'
                    : ($statusRank == 4 ? 'Teknisi sedang melakukan penarikan kabel dropcore.' : 'Menunggu penyelesaian tahap sebelumnya.'),
                'status' => $statusRank >= 5 ? 'completed' : ($statusRank == 4 ? 'current' : 'pending'),
            ],
            [
                'step' => 'Aktivasi',
                'title' => 'Aktivasi PPPoE Billing',
                'date' => $statusRank >= 6 ? IndonesianDate::date($regDate->copy()->addDays(5)) : '-',
                'notes' => $statusRank >= 6 
                    ? ($status === 'active' ? 'Layanan telah aktif sepenuhnya.' : ($status === 'suspended' ? 'Layanan diisolir sementara.' : 'Layanan diterminasi.'))
                    : 'Menunggu proses pemasangan & uji speedtest selesai.',
                'status' => $statusRank >= 6 
                    ? ($status === 'suspended' ? 'warning' : ($status === 'terminated' ? 'danger' : 'completed'))
                    : 'pending',
            ],
        ];

        // 1. Audit Logs untuk Riwayat Perubahan Data Pelanggan
        $auditableConditions = [
            [\App\Models\Customer::class, $customer->id],
        ];
        if ($customer->customerAddress) {
            $auditableConditions[] = [\App\Models\CustomerAddress::class, $customer->customerAddress->id];
        }
        if ($customer->customerService) {
            $auditableConditions[] = [\App\Models\CustomerService::class, $customer->customerService->id];
        }
        if ($customer->customerDevice) {
            $auditableConditions[] = [\App\Models\CustomerDevice::class, $customer->customerDevice->id];
        }
        if ($customer->customerTechnicalDetail) {
            $auditableConditions[] = [\App\Models\CustomerTechnicalDetail::class, $customer->customerTechnicalDetail->id];
        }

        $auditLogs = \App\Models\AuditLog::with('user.role')
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
            ->get();

        $statusLogs = \App\Models\CustomerStatusLog::with('user.role')
            ->where('customer_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // 2. Tasks & FopTasks untuk Riwayat Ticketing
        $customerTasks = $customer->tasks()
            ->with([
                'teamMembers.user', 
                'checklists', 
                'evidences', 
                'creator', 
                'fop', 
                'auditLogs.user.role'
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        $customerFopTasks = $customer->fopTasks()
            ->with(['technicians', 'village', 'pop'])
            ->orderBy('task_date', 'desc')
            ->get();

        // Daftar user aktif untuk dropdown petugas (survey, pemasangan)
        $activeUsers = \App\Models\User::where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('customers.show', compact(
            'customer',
            'displayId',
            'displayIdLabel',
            'completeness',
            'timeline',
            'activeUsers',
            'auditLogs',
            'statusLogs',
            'customerTasks',
            'customerFopTasks'
        ));
    }

    /**
     * Get payment info for the customer action modal.
     */
    public function paymentInfo(Customer $customer)
    {
        // Pastikan user punya akses ke POP pelanggan
        if (!auth()->user()->hasFullAccess() && !in_array($customer->pop_id, auth()->user()->pops()->pluck('pops.id')->toArray())) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Cari invoice terbaru yang belum lunas
        $latestInvoice = $customer->invoices()
            ->whereIn('invoice_status', ['belum_dibayar', 'sebagian'])
            ->latest('issue_date')
            ->first();

        // Hitung total piutang (sum dari remaining_amount semua invoice yang belum lunas)
        $totalPiutang = $customer->invoices()
            ->whereIn('invoice_status', ['belum_dibayar', 'sebagian'])
            ->sum('remaining_amount');

        return response()->json([
            'invoice_id' => $latestInvoice ? $latestInvoice->id : null,
            'invoice_number' => $latestInvoice ? $latestInvoice->invoice_number : null,
            'total_amount' => $latestInvoice ? (float) $latestInvoice->total_amount : 0,
            'remaining_amount' => $latestInvoice ? (float) $latestInvoice->remaining_amount : 0,
            'discount' => $latestInvoice ? (float) $latestInvoice->discount : 0,
            'total_piutang' => (float) $totalPiutang,
            'billing_period' => $latestInvoice ? $latestInvoice->billing_period : null,
            'due_date' => $latestInvoice && $latestInvoice->due_date ? $latestInvoice->due_date->format('d/m/Y') : null,
        ]);
    }

    /**
     * Show the batch import page.
     */
    public function importForm()
    {
        $packages = \App\Models\InternetPackage::orderBy('name')->get(['id', 'package_code', 'name', 'monthly_price']);
        $villages = \App\Models\Village::with('district')->orderBy('name')->get(['id', 'name', 'district_id']);
        
        return view('customers.import', compact('packages', 'villages'));
    }

    /**
     * Display the import history.
     */
    public function importHistory()
    {
        $batches = \App\Models\ImportBatch::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('customers.import_history', compact('batches'));
    }

    /**
     * Display the import batch detail and errors.
     */
    public function importBatchDetail($id)
    {
        $batch = \App\Models\ImportBatch::with(['user', 'errors'])
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
                'headers' => ['old_customer_id', 'old_request_id', 'customer_code', 'full_name', 'identity_number', 'gender', 'phone', 'alternative_phone', 'email', 'customer_type', 'company_name', 'npwp', 'full_address', 'old_region_id', 'city', 'district', 'village', 'old_branch_id', 'old_account_status', 'registration_date', 'ktp_photo', 'profile_photo', 'pop_code', 'pop_name', 'distribution_code', 'latitude', 'longitude', 'foto_ktp', 'foto_rumah', 'foto_kontrak', 'sales_code', 'agent_code', 'referral_customer_code'],
                'data' => [['PE000001', 'RQ000001', 'C00RQ000001', 'Budi Santoso', '3502180101900001', 'Laki-laki', '081234567890', '', 'budi@example.com', 'rumah', '', '', 'Jl. Merdeka No. 10', 'WL0001', 'Ponorogo', 'Sukorejo', 'Sukorejo', 'CB001', 'ACTIVE', '2025-05-06', 'ktp.jpg', 'foto.jpg', 'SMN', 'POP Sukorejo', '-7.8712', '111.4623', 'foto_ktp.jpg', 'foto_rumah.jpg', 'foto_kontrak.jpg', 'SLS001', '', '']]
            ],
            'packages' => [
                'headers' => ['old_package_id', 'name', 'package_type', 'category', 'monthly_price', 'upload_speed', 'download_speed', 'upload_limit', 'download_limit', 'olt_profile', 'ppp_profile', 'bonus', 'description'],
                'data' => [['PK000001', 'WHUSNET 20 Mbps', 'Broadband', 'Paket Home Broadband', '150000', '20', '20', '', '', 'OLT-20M', 'PPP-20M', '', 'Paket legacy']]
            ],
            'services' => [
                'headers' => ['old_request_id', 'old_customer_id', 'old_package_id', 'old_cost_id', 'request_status', 'installation_status', 'service_status', 'activation_date', 'survey_at', 'approved_at', 'processed_at', 'finished_at', 'verified_at', 'network_type', 'member_type', 'reason', 'profile', 'contract_type', 'activation_time', 'activated_by_name', 'survey_date', 'survey_start_time', 'survey_end_time', 'surveyors', 'survey_assigned_at', 'survey_fop_id', 'required_tools', 'survey_photo', 'survey_note', 'survey_duration_minutes', 'installation_date', 'installation_start_time', 'installation_end_time', 'installation_technicians', 'installation_photo', 'installation_note', 'installation_assigned_at', 'installation_fop_id', 'other_fee'],
                'data' => [['RQ000001', 'PE000001', 'PK000001', 'IN000001', 'ACTIVE', 'Berhasil', 'ACTIVE', '2025-05-06', '', '', '', '', '', 'KABEL', '0', '', 'PPP-20M', 'bulanan', '10:00:00', 'Admin', '2025-05-05', '09:00:00', '09:30:00', 'Teknisi A', '2025-05-05 09:00:00', 'RQ000001', 'Tangga, Fiber', 'survey_rumah.jpg', 'Ada ODP dekat', '30', '2025-05-06', '10:00:00', '11:00:00', 'Teknisi B', 'pasang.jpg', 'Selesai pasang', '2025-05-06 10:00:00', 'RQ000001', '0']]
            ],
            'technical_details' => [
                'headers' => ['old_report_id', 'old_customer_id', 'old_request_id', 'connection_type', 'test_upload', 'test_download', 'ssid', 'ip_address', 'antenna_mac', 'router_mac', 'router_or_ont_serial', 'odp_number', 'odp_port', 'olt_port', 'wireless_signal', 'fiber_signal', 'location_source', 'note', 'speedtest_photo', 'form_photo', 'signed_form_photo', 'router_photo', 'cable_photo', 'passive_device', 'branch_number', 'pop_number', 'router_number', 'initial_attenuation', 'actual_attenuation', 'test_date', 'test_time', 'jitter_ms', 'latency_ms', 'packet_loss_percent', 'speed_conformity_percent', 'quality_score'],
                'data' => [['REP000001', 'PE000001', 'RQ000001', 'FTTH', '20', '20', 'WHUSNET-BUDI', '192.168.1.10', '', 'AA:BB:CC:DD:EE:FF', 'SN123456', 'ODP-SMN-001', '1', '1/1/1', '', '-18', 'Tiang 01', 'Data teknis legacy', '', '', '', '', '', 'FAT-01', 'CB001', 'WL0001', 'RTR-001', '-18.5', '-19.2', '2025-05-06', '11:00:00', '2.5', '12.0', '0.00', '95.0', '5']]
            ],
            'invoices' => [
                'headers' => ['old_invoice_id', 'old_cost_id', 'old_customer_id', 'old_request_id', 'billing_period', 'issue_date', 'due_date', 'installation_fee', 'monthly_fee', 'other_fee', 'total_amount', 'status', 'prorate_amount', 'extra_cable_fee', 'extra_installation_fee', 'extra_pole_fee'],
                'data' => [['INVOICE-0001', 'IN000001', 'PE000001', 'RQ000001', '2025-05', '2025-05-06', '2025-05-10', '0', '150000', '0', '150000', 'belum_dibayar', '', '0', '0', '0']]
            ],
            'payments' => [
                'headers' => ['old_payment_id', 'old_transaction_id', 'old_invoice_id', 'old_customer_id', 'old_request_id', 'payment_date', 'billing_period', 'payment_method', 'amount', 'received_by_old', 'deposited_by_old', 'note', 'status'],
                'data' => [['PAY000001', 'IN000001', '', 'PE000001', 'RQ000001', '2025-05-07', '2025-05', 'cash', '150000', 'PG000005', '', 'Pembayaran legacy', 'valid']]
            ],
        ];

        $tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'temp-template-import-' . uniqid() . '.xlsx';

        if (!class_exists(\ZipArchive::class)) {
            // Fallback for environment lacking ZipArchive extension (e.g. host machine's CLI)
            file_put_contents($tempFile, 'Dummy Excel Content (Missing ZipArchive)');
        } else {
            $writer = SimpleExcelWriter::create($tempFile);
            
            $isFirstSheet = true;
            foreach ($sheets as $sheetName => $sheet) {
                if (!$isFirstSheet) {
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
                'error' => $e->getMessage()
            ], 422);
        }
        
        $customersData = $sheets['customers'] ?? [];
        $packagesData = $sheets['packages'] ?? [];
        $servicesData = $sheets['services'] ?? [];
        $techDetailsData = $sheets['technical_details'] ?? [];
        $invoicesData = $sheets['invoices'] ?? [];
        $paymentsData = $sheets['payments'] ?? [];

        // 1. Validate Packages Sheet
        $validatedPackages = [];
        $seenPackageIds = [];
        foreach ($packagesData as $index => $row) {
            $oldPackageId = trim((string)($row['old_package_id'] ?? ''));
            $name = trim((string)($row['name'] ?? ''));
            $monthlyPriceInput = trim((string)($row['monthly_price'] ?? ''));
            
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
            } elseif (!is_numeric($monthlyPriceInput)) {
                $errors[] = 'Harga paket harus berupa angka.';
                $statusRow = 'error';
            }

            $validatedPackages[] = array_merge($row, [
                'original_no' => $index + 1,
                'old_package_id' => $oldPackageId,
                'name' => $name,
                'monthly_price' => is_numeric($monthlyPriceInput) ? (float)$monthlyPriceInput : null,
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

                if (Customer::where('old_customer_id', $oldCustomerId)->exists()) {
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

                if (Customer::where('phone', $primaryPhone)->orWhere('primary_phone', $primaryPhone)->exists()) {
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

                if (!$pop) {
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
                'registration_date' => $this->normalizeLegacyDate($row['registration_date'] ?? null) ?? now()->format('Y-m-d'),
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

                if (CustomerService::where('old_request_id', $oldRequestId)->exists()) {
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
                if (!$customerInSheet && !$customerInDb) {
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

                if (!$packageInSheet && !$packageInDb) {
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
            } elseif (!in_array($serviceStatus, $validStatuses, true)) {
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
                'monthly_price' => $package instanceof InternetPackage ? $package->monthly_price : ($package['monthly_price'] ?? 0),
                'other_fee' => is_numeric($row['other_fee'] ?? null) ? (float)$row['other_fee'] : 0,
                'service_status' => $serviceStatus,
                'activation_date' => $this->normalizeLegacyDate($row['activation_date'] ?? $row['finished_at'] ?? null) ?? now()->format('Y-m-d'),
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
            $oldReportId = trim((string)($row['old_report_id'] ?? ''));
            $oldCustomerId = trim((string)($row['old_customer_id'] ?? ''));
            if ($this->isInternalLegacyAccountId($oldCustomerId)) {
                continue; // Skip tech details belonging to internal users
            }
            $oldRequestId = trim((string)($row['old_request_id'] ?? ''));

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

                if (CustomerTechnicalDetail::where('old_report_id', $oldReportId)->exists()) {
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
                if (!$customerInSheet && !$customerInDb) {
                    $errors[] = "Pelanggan dengan ID '{$oldCustomerId}' tidak ditemukan.";
                    $statusRow = 'error';
                }
            }

            $validatedTechDetails[] = array_merge($row, [
                'original_no' => $index + 1,
                'old_report_id' => $oldReportId,
                'old_customer_id' => $oldCustomerId,
                'old_request_id' => $oldRequestId,
                'connection_type' => trim((string)($row['connection_type'] ?? 'FTTH')),
                'ont_sn' => trim((string)($row['router_or_ont_serial'] ?? $row['ont_sn'] ?? '')),
                'ip_address' => trim((string)($row['ip_address'] ?? '')),
                'odp_code' => trim((string)($row['odp_number'] ?? $row['odp_code'] ?? '')),
                'olt_code' => trim((string)($row['olt_port'] ?? $row['olt_code'] ?? '')),
                'vlan_id' => trim((string)($row['vlan_id'] ?? '')),
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

                if (Invoice::where('old_invoice_id', $legacyInvoiceKey)->orWhere('old_cost_id', $legacyInvoiceKey)->exists()) {
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
                if (!$customerInSheet && !$customerInDb) {
                    $warnings[] = "Pelanggan dengan ID '{$oldCustomerId}' belum ditemukan saat validasi; akan dicoba cocok lewat request saat import.";
                }
            }

            if ($billingPeriod === '') {
                $errors[] = 'Periode tagihan (YYYY-MM) wajib diisi.';
                $statusRow = 'error';
            } elseif (!preg_match('/^\d{4}-\d{2}$/', $billingPeriod)) {
                $errors[] = 'Format periode tagihan harus YYYY-MM.';
                $statusRow = 'error';
            }

            if ($totalAmountInput === '') {
                $errors[] = 'Total tagihan wajib diisi.';
                $statusRow = 'error';
            } elseif (!is_numeric($totalAmountInput)) {
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
                'total_amount' => is_numeric($totalAmountInput) ? (float)$totalAmountInput : 0,
                'issue_date' => $this->normalizeLegacyDate($row['issue_date'] ?? null) ?? now()->format('Y-m-d'),
                'due_date' => $this->normalizeLegacyDate($row['due_date'] ?? null) ?? now()->addDays(10)->format('Y-m-d'),
                'installation_fee' => is_numeric($row['installation_fee'] ?? null) ? (float)$row['installation_fee'] : 0,
                'monthly_fee' => is_numeric($row['monthly_fee'] ?? null) ? (float)$row['monthly_fee'] : null,
                'other_fee' => is_numeric($row['other_fee'] ?? null) ? (float)$row['other_fee'] : 0,
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

                if (Payment::where('old_payment_id', $oldPaymentId)->exists()) {
                    $errors[] = 'ID pembayaran lama sudah terdaftar di database.';
                    $statusRow = 'error';
                }
            }

            if (empty($oldInvoiceId) && empty($oldTransactionId) && empty($oldRequestId)) {
                $errors[] = 'ID invoice, ID transaksi, atau ID request lama wajib diisi.';
                $statusRow = 'error';
            } elseif (!empty($oldInvoiceId)) {
                $invoiceInSheet = collect($validatedInvoices)->firstWhere('old_invoice_id', $oldInvoiceId);
                $invoiceInDb = Invoice::where('old_invoice_id', $oldInvoiceId)->exists();
                if (!$invoiceInSheet && !$invoiceInDb) {
                    $warnings[] = "Invoice dengan ID '{$oldInvoiceId}' belum ditemukan saat validasi; akan dicoba cocok lewat transaksi/request saat import.";
                }
            }

            if ($amountInput === '') {
                $errors[] = 'Nominal bayar wajib diisi.';
                $statusRow = 'error';
            } elseif (!is_numeric($amountInput)) {
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
                'amount' => is_numeric($amountInput) ? (float)$amountInput : 0,
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

        $batch = \App\Models\ImportBatch::create([
            'batch_number' => \App\Models\ImportBatch::generateBatchNumber(),
            'file_name' => $fileName,
            'uploaded_by' => auth()->id(),
            'total_rows' => $totalRows,
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
            'status' => 'pending',
        ]);

        $insertedCount = 0;

        try {
            \Illuminate\Support\Facades\DB::transaction(function() use (
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

                    if (!$package) {
                        $package = InternetPackage::create([
                            'package_code' => $row['old_package_id'],
                            'old_package_id' => $row['old_package_id'],
                            'name' => $row['name'],
                            'monthly_price' => $row['monthly_price'],
                            'ppn' => 0.00,
                            'total_price' => $row['monthly_price'],
                            'category' => $row['category'] ?? 'Paket Home Broadband',
                            'package_group' => $row['package_type'] ?? 'Broadband',
                            'bandwidth_label' => ($row['download_speed'] ?? '10') . ' Mbps',
                            'download_speed_mbps' => $row['download_speed'] ?? 10.00,
                            'upload_speed_mbps' => $row['upload_speed'] ?? 10.00,
                            'is_active' => true,
                        ]);
                    }

                    $packagesMap[$row['old_package_id']] = $package->id;
                    $insertedCount++;
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

                    $pop = !empty($row['pop_id']) ? Pop::find($row['pop_id']) : null;
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
                    $customerCode = !empty($row['customer_code']) ? $row['customer_code'] : ($pop?->generateRegistrationNumber() ?: $row['old_customer_id']);

                    $customer = Customer::create([
                        'customer_code' => $customerCode,
                        'old_customer_id' => $row['old_customer_id'],
                        'old_request_id' => $row['old_request_id'] ?? null,
                        'full_name' => $row['full_name'] ?: $row['old_customer_id'],
                        'identity_number' => $row['identity_number'] ?? null,
                        'gender' => $row['gender'] ?? 'Laki-laki',
                        'customer_type' => $row['customer_type'] ?? null,
                        'company_name' => $row['company_name'] ?? null,
                        'npwp' => $row['npwp'] ?? null,
                        'old_account_status' => $row['old_account_status'] ?? null,
                        'phone' => $this->cleanLegacyValue($row['phone'] ?? null) ?? '',
                        'primary_phone' => $this->cleanLegacyValue($row['primary_phone'] ?? $row['phone'] ?? null) ?? '',
                        'alternative_phone' => $row['alternative_phone'] ?? null,
                        'email' => $row['email'] ?? null,
                        'registration_date' => $row['registration_date'] ?? now()->format('Y-m-d'),
                        'pop_id' => $pop?->id,
                        'distribution_id' => $distribution?->id,
                        'status' => 'registered', // Default, updated by service activation or mapping
                        'customer_status' => 'calon_pelanggan',
                        'created_by' => auth()->id(),
                        'foto_ktp' => $row['foto_ktp'] ?? null,
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
                        'ktp_photo' => $row['foto_ktp'] ?? $row['ktp_photo'] ?? null,
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

                    $customerId = $customersMap[$row['old_customer_id']] ?? Customer::where('old_customer_id', $row['old_customer_id'])->value('id');
                    $packageId = $packagesMap[$row['old_package_id']] ?? InternetPackage::where('old_package_id', $row['old_package_id'])->value('id');

                    if (!$customerId || !$packageId) {
                        $this->logImportError($batch->id, $row, 'Services', 'Gagal memetakan Customer ID atau Package ID.');
                        continue;
                    }

                    $existingService = CustomerService::where('old_request_id', $row['old_request_id'])->first();
                    if ($existingService) {
                        continue;
                    }

                    $package = InternetPackage::findOrFail($packageId);
                    $monthlyPrice = (float)($row['monthly_price'] ?? $package->monthly_price);
                    $ppnPercent = 0.00; // Legacy data uses 0% PPN
                    $otherFee = (float)($row['other_fee'] ?? 0);
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
                        'activation_date' => $row['activation_date'] ?? now()->format('Y-m-d'),
                        'due_date' => $row['due_date'] ?? null,
                        'service_status' => $serviceStatus,
                        'billing_status' => ($serviceStatus === 'active') ? 'active' : 'inactive',
                        'profile' => $row['profile'] ?? null,
                        'contract_type' => $row['contract_type'] ?? null,
                        'activation_time' => $row['activation_time'] ?? null,
                        'activated_by_name' => $row['activated_by_name'] ?? null,
                    ]);

                    // Survey creation
                    if (!empty($row['survey_date']) || !empty($row['surveyors'])) {
                        \App\Models\CustomerSurvey::create([
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
                    if (!empty($row['installation_date']) || !empty($row['installation_technicians'])) {
                        \App\Models\CustomerInstallation::create([
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

                    $custStatus = $this->mapServiceStatusToCustomerStatus($serviceStatus);

                    $updateData = [
                        'internet_package_id' => $packageId,
                        'status' => $serviceStatus,
                        'customer_status' => $custStatus,
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

                    $customerId = $customersMap[$row['old_customer_id']] ?? Customer::where('old_customer_id', $row['old_customer_id'])->value('id');

                    if (!$customerId) {
                        $this->logImportError($batch->id, $row, 'Technical Details', 'Gagal memetakan Customer ID.');
                        continue;
                    }

                    if (!empty($row['old_report_id']) && CustomerTechnicalDetail::where('old_report_id', $row['old_report_id'])->exists()) {
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
                        'ip_address' => $row['ip_address'] ?? null,
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
                        'ip_address' => $row['ip_address'] ?? null,
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

                    $customerId = $customersMap[$row['old_customer_id'] ?? ''] ?? Customer::where('old_customer_id', $row['old_customer_id'] ?? null)->value('id');
                    if (!$customerId && !empty($row['old_request_id'])) {
                        $customerId = CustomerService::where('old_request_id', $row['old_request_id'])->value('customer_id');
                    }

                    if (!$customerId) {
                        $this->logImportError($batch->id, $row, 'Invoices', 'Gagal memetakan Customer ID.');
                        continue;
                    }

                    $customer = Customer::findOrFail($customerId);
                    $service = $customer->customerService;

                    if (!$service) {
                        $this->logImportError($batch->id, $row, 'Invoices', 'Layanan aktif untuk pelanggan tidak ditemukan.');
                        continue;
                    }

                    $legacyInvoiceId = $row['old_invoice_id'] ?: ($row['old_cost_id'] ?? null);
                    $existingInvoice = null;
                    if ($legacyInvoiceId || !empty($row['old_cost_id'])) {
                        $existingInvoice = Invoice::query()
                            ->where(function ($query) use ($legacyInvoiceId, $row) {
                                if ($legacyInvoiceId) {
                                    $query->where('old_invoice_id', $legacyInvoiceId);
                                }
                                if (!empty($row['old_cost_id'])) {
                                    $query->orWhere('old_cost_id', $row['old_cost_id']);
                                }
                            })
                            ->first();
                    }

                    if ($existingInvoice) {
                        $invoicesMap[$legacyInvoiceId] = $existingInvoice->id;
                        if (!empty($row['old_cost_id'])) {
                            $invoicesMap[$row['old_cost_id']] = $existingInvoice->id;
                        }
                        continue;
                    }

                    $invoiceNumber = 'INV-' . $legacyInvoiceId;
                    $totalAmount = (float)$row['total_amount'];

                    $invoice = Invoice::create([
                        'invoice_number' => $invoiceNumber,
                        'invoice_type' => \App\Enums\InvoiceType::BULANAN->value,
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
                        'subtotal' => $row['monthly_fee'] ?? $service->monthly_price,
                        'discount' => 0.00,
                        'ppn' => 0.00, // Legacy invoices use 0% PPN
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
                    if (!empty($row['old_cost_id'])) {
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

                    if (Payment::where('old_payment_id', $row['old_payment_id'])->exists()) {
                        continue;
                    }

                    $invoiceId = $this->resolveLegacyInvoiceId($row, $invoicesMap);

                    if (!$invoiceId) {
                        $this->logImportError($batch->id, $row, 'Payments', 'Gagal memetakan Invoice ID.');
                        continue;
                    }

                    $invoice = Invoice::findOrFail($invoiceId);
                    $paymentNumber = 'PAY-' . $row['old_payment_id'];
                    $amount = (float)$row['amount'];

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
                    $newPaidAmount = (float)$invoice->payments()->where('payment_status', 'valid')->sum('amount');
                    $newRemaining = max(0.00, (float)$invoice->total_amount - $newPaidAmount);
                    $newStatus = $newPaidAmount <= 0 ? 'belum_dibayar' : ($newRemaining <= 0 ? 'lunas' : 'sebagian');

                    $invoice->update([
                        'paid_amount' => $newPaidAmount,
                        'remaining_amount' => $newRemaining,
                        'invoice_status' => $newStatus,
                    ]);

                    $insertedCount++;
                }

                // Log audit batch
                \App\Models\AuditLog::create([
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
            \Illuminate\Support\Facades\Log::error('Multi-sheet Import Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->route('customers.import')->withErrors('Gagal meng-import data: ' . $e->getMessage());
        }

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
                if ($hasDistrict) $score += 2;
                if ($hasCity) $score += 1;
                
                if ($hasDistrict && (preg_match('/kec\b/i', $address) || preg_match('/kecamatan\b/i', $address))) {
                    if (preg_match('/kec(amatan)?\s*' . preg_quote($dName, '/') . '/i', $address)) {
                        $score += 2;
                    }
                }
                
                $matches[] = [
                    'village' => $v,
                    'score' => $score
                ];
            }
        }
        
        if (empty($matches)) {
            return null;
        }
        
        usort($matches, function($a, $b) {
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
        \App\Models\ImportError::create([
            'import_batch_id' => $batchId,
            'row_number' => $row['original_no'] ?? null,
            'field_name' => $sheetName,
            'error_message' => "[{$sheetName}] " . $message,
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
        if ($streetAddress !== '' && !in_array(strtolower($streetAddress), ['-', 'null', 'n/a'], true)) {
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
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
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
            return \Carbon\Carbon::parse($value)->format('Y-m');
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

        if (!is_numeric($value)) {
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
                $normalized = '-' . substr($digits, 0, 1) . '.' . substr($digits, 1);
            } else {
                // Positive coordinates
                if (str_starts_with($digits, '1')) {
                    // Longitude in Indonesia is around 110-115
                    // Place the dot after the first 3 digits
                    $normalized = substr($digits, 0, 3) . '.' . substr($digits, 3);
                } else {
                    // Positive latitude
                    $normalized = substr($digits, 0, 1) . '.' . substr($digits, 1);
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

        if (!is_numeric($cleaned)) {
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
            'disurvei' => 'waiting_survey',
            'survey' => 'waiting_survey',
            'menunggu_survey' => 'waiting_survey',
            'pengajuan' => 'registered',
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

        return [
            'lunas' => 'lunas',
            'paid' => 'lunas',
            'sebagian' => 'sebagian',
            'partial' => 'sebagian',
            'batal' => 'batal',
            'cancelled' => 'batal',
            'belum_dibayar' => 'belum_dibayar',
            'unpaid' => 'belum_dibayar',
            '' => 'belum_dibayar',
        ][$normalized] ?? 'belum_dibayar';
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

        return [
            'valid' => 'valid',
            'diterima' => 'valid',
            'lunas' => 'valid',
            'pending' => 'pending',
            'ditolak' => 'ditolak',
            'rejected' => 'ditolak',
            'batal' => 'ditolak',
            '' => 'valid',
        ][$normalized] ?? 'valid';
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, int> $invoicesMap
     */
    private function resolveLegacyInvoiceId(array $row, array $invoicesMap): ?int
    {
        foreach (['old_invoice_id', 'old_transaction_id'] as $field) {
            $key = $row[$field] ?? null;
            if ($key && isset($invoicesMap[$key])) {
                return $invoicesMap[$key];
            }
        }

        if (!empty($row['old_invoice_id'])) {
            $invoiceId = Invoice::where('old_invoice_id', $row['old_invoice_id'])->value('id');
            if ($invoiceId) {
                return (int) $invoiceId;
            }
        }

        if (!empty($row['old_transaction_id'])) {
            $invoiceId = Invoice::where('old_cost_id', $row['old_transaction_id'])->value('id');
            if ($invoiceId) {
                return (int) $invoiceId;
            }
        }

        if (!empty($row['old_request_id']) && !empty($row['billing_period'])) {
            $invoiceId = Invoice::where('old_request_id', $row['old_request_id'])
                ->where('billing_period', $row['billing_period'])
                ->value('id');
            if ($invoiceId) {
                return (int) $invoiceId;
            }
        }

        return null;
    }


    public function activate(Customer $customer)
    {
        if (!$customer->exists) {
            $customer = Customer::findOrFail(request()->route('customer'));
        }

        $completeness = $customer->dataCompleteness();
        if (!$completeness['is_ready_billing']) {
            return redirect()->route('customers.show', $customer->id)
                ->with('error', 'Pelanggan tidak bisa diaktifkan karena data wajib belum lengkap.');
        }

        if (!$customer->internet_package_id) {
            return redirect()->route('customers.show', $customer->id)
                ->with('error', 'Pelanggan tidak memiliki paket internet aktif.');
        }

        $service = $customer->customerService;
        if (!$service) {
            return redirect()->route('customers.show', $customer->id)
                ->with('error', 'Data layanan pelanggan tidak ditemukan.');
        }

        if ($service->total_monthly_bill <= 0) {
            return redirect()->route('customers.show', $customer->id)
                ->with('error', 'Total tagihan bulanan tidak valid (harus lebih besar dari 0).');
        }

        $pop = $customer->pop;
        if (!$pop) {
            return redirect()->route('customers.show', $customer->id)
                ->with('error', 'POP/Cabang pelanggan tidak ditemukan.');
        }

        if (!$pop->cid_prefix) {
            return redirect()->route('customers.show', $customer->id)
                ->with('error', 'Konfigurasi prefix CID pada POP asal pelanggan belum lengkap. Pastikan field cid_prefix terisi.');
        }

        // Load relasi teknis dan distribusi untuk generate CID kompleks
        $customer->loadMissing(['customerTechnicalDetail', 'distribution', 'village']);
        \Illuminate\Support\Facades\DB::transaction(function () use ($customer, $service, $pop) {
            // Generate CID kompleks: {pop.cid_prefix}{olt_number}{dist_code}{customer_code}_{village}_{name}
            // Contoh: D2X6CRQ001296_MANGKUJAYAN_DYAHGALUH
            $distribution = $customer->distribution;
            $cid = $pop->generateComplexCid($customer, $distribution);

            $oldValues = [
                'cid' => $customer->cid,
                'status' => $customer->status,
                'customer_status' => $customer->customer_status,
                'data_completeness_status' => $customer->data_completeness_status,
                'service_status' => $service->service_status,
                'billing_status' => $service->billing_status,
            ];

            $customer->update([
                'cid' => $cid,
                'status' => 'active',
                'customer_status' => 'aktif',
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
                'customer_status' => 'aktif',
                'data_completeness_status' => 'siap_billing',
                'service_status' => 'aktif',
                'billing_status' => 'active',
            ];

            \App\Models\AuditLog::create([
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
                $telegram = app(\App\Services\TelegramBotService::class);
                $message = "🎉 <b>Pelanggan Aktif</b>\n";
                $message .= "Pelanggan: {$customer->full_name}\n";
                $message .= "CID: {$cid}\n";
                $message .= "No. HP: {$customer->phone}\n";
                $message .= "POP: {$customer->pop->name}\n";
                $message .= "Telah berhasil diaktivasi dan masuk siklus penagihan.";
                $telegram->sendMessage($message);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Gagal mengirim notifikasi Telegram: ' . $e->getMessage());
            }
        });

        // Reload untuk mendapatkan CID yang baru disimpan
        $customer->refresh();

        return redirect()->route('customers.show', $customer->id)
            ->with('success', "Layanan pelanggan berhasil diaktifkan! CID: {$customer->cid}");
    }

    private function mapServiceStatusToCustomerStatus(string $status): string
    {
        $mapping = [
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

        return $mapping[$status] ?? 'calon_pelanggan';
    }

    /**
     * S5-T003 — Buat Tagihan Manual
     * Handle POST request to create a manual invoice for a customer.
     */
    public function storeManualInvoice(Request $request, Customer $customer)
    {
        if (!$customer->exists) {
            $customer = Customer::findOrFail($request->route('customer'));
        }

        // 1. Authorization checks
        if (!auth()->user()->hasPermission('invoices.create')) {
            abort(403, 'Anda tidak memiliki akses untuk membuat tagihan.');
        }

        // Scope check for user's assigned POPs
        if (!Customer::query()->applyUserScope()->where('id', $customer->id)->exists()) {
            abort(403, 'Anda tidak memiliki akses ke data pelanggan di POP ini.');
        }

        // 2. Validate request
        $validated = $request->validate([
            'billing_period'          => 'required|date_format:Y-m',
            'issue_date'              => 'required|date',
            'due_date'                => 'required|date|after_or_equal:issue_date',
            'prorate_amount'          => 'nullable|numeric|min:0',
            'extra_cable_fee'         => 'nullable|numeric|min:0',
            'extra_installation_fee'  => 'nullable|numeric|min:0',
            'extra_pole_fee'          => 'nullable|numeric|min:0',
        ]);

        $billingPeriod        = $validated['billing_period'];
        $issueDate            = $validated['issue_date'];
        $dueDate              = $validated['due_date'];
        $prorateAmount        = (float)($validated['prorate_amount'] ?? 0);
        $extraCableFee        = (float)($validated['extra_cable_fee'] ?? 0);
        $extraInstallationFee = (float)($validated['extra_installation_fee'] ?? 0);
        $extraPoleFee         = (float)($validated['extra_pole_fee'] ?? 0);

        // 3. Business logic checks
        // Cek pelanggan aktif/siap billing
        if (!in_array($customer->status, ['active', 'suspended']) && $customer->data_completeness_status !== 'siap_billing') {
            return redirect()->back()->withErrors(['error' => 'Tagihan hanya bisa dibuat untuk pelanggan dengan status aktif atau siap billing.']);
        }

        $service = $customer->customerService;
        if (!$service) {
            return redirect()->back()->withErrors(['error' => 'Pelanggan tidak memiliki layanan aktif.']);
        }

        // Cek invoice dobel untuk periode yang sama
        $exists = \App\Models\Invoice::where('customer_id', $customer->id)
            ->where('billing_period', $billingPeriod)
            ->exists();

        if ($exists) {
            return redirect()->back()->withErrors(['billing_period' => "Tagihan untuk periode {$billingPeriod} sudah pernah dibuat untuk pelanggan ini."]);
        }

        // 4. Generate invoice number sequentially (e.g., format INV-YYYYMM-[counter] where counter increment is locked for update)
        $periodCode = str_replace('-', '', $billingPeriod);
        
        $invoice = \Illuminate\Support\Facades\DB::transaction(function () use (
            $customer, $service, $billingPeriod, $issueDate, $dueDate, $periodCode,
            $prorateAmount, $extraCableFee, $extraInstallationFee, $extraPoleFee
        ) {
            $lastInvoice = \App\Models\Invoice::where('invoice_number', 'like', "INV-{$periodCode}-%")
                ->orderBy('invoice_number', 'desc')
                ->lockForUpdate()
                ->first();

            $nextSeq = 1;
            if ($lastInvoice) {
                $parts = explode('-', $lastInvoice->invoice_number);
                if (count($parts) === 3) {
                    $nextSeq = ((int)$parts[2]) + 1;
                }
            }
            $invoiceNumber = sprintf('INV-%s-%04d', $periodCode, $nextSeq);

            // Rincian biaya dari service snapshot
            $subtotal    = (float)$service->monthly_price;
            $discount    = (float)($service->discount ?? 0.00);
            $ppnPercent  = (float)($service->ppn ?? 0.00);

            // Hitung PPN dari (subtotal - discount) × rate
            $afterDiscount = max(0, $subtotal - $discount);
            $ppnAmount     = round($afterDiscount * ($ppnPercent / 100), 2);
            $nettMonthly   = $afterDiscount + $ppnAmount;

            // Total = tagihan bulanan nett + semua biaya tambahan
            $totalAmount     = $nettMonthly + $prorateAmount + $extraCableFee + $extraInstallationFee + $extraPoleFee;
            $paidAmount      = 0.00;
            $remainingAmount = $totalAmount;

            $newInvoice = \App\Models\Invoice::create([
                'invoice_number'          => $invoiceNumber,
                'invoice_type'            => ($extraInstallationFee > 0 || $prorateAmount > 0) ? \App\Enums\InvoiceType::AWAL->value : \App\Enums\InvoiceType::BULANAN->value,
                'customer_id'             => $customer->id,
                'pop_id'                  => $customer->pop_id,
                'customer_service_id'     => $service->id,
                'internet_package_id'     => $service->internet_package_id,
                'billing_period'          => $billingPeriod,
                'issue_date'              => $issueDate,
                'due_date'                => $dueDate,
                'subtotal'                => $subtotal,
                'discount'                => $discount,
                'ppn'                     => $ppnPercent,
                'prorate_amount'          => $prorateAmount > 0 ? $prorateAmount : null,
                'extra_cable_fee'         => $extraCableFee > 0 ? $extraCableFee : null,
                'extra_installation_fee'  => $extraInstallationFee > 0 ? $extraInstallationFee : null,
                'extra_pole_fee'          => $extraPoleFee > 0 ? $extraPoleFee : null,
                'total_amount'            => $totalAmount,
                'paid_amount'             => $paidAmount,
                'remaining_amount'        => $remainingAmount,
                'invoice_status'          => 'belum_dibayar',
                'created_by'              => auth()->id(),
            ]);

            // Save changes to audit log (Sprint 8: audit_logs)
            \App\Models\AuditLog::create([
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

        return redirect()->route('customers.show', $customer->id)
            ->with('success', "Tagihan manual dengan nomor {$invoice->invoice_number} berhasil dibuat!");
    }

}
