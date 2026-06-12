<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\City;
use App\Models\District;
use App\Models\Village;
use App\Models\InternetPackage;
use App\Models\SubscriptionStatus;
use App\Models\Pop;
use App\Services\CustomerValidationService;
use App\Support\IndonesianDate;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * Display a listing of the customers with search and filters.
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $districtId = $request->query('district_id', '');
        $packageId = $request->query('package_id', '');
        $popId = $request->query('pop_id', '');
        $completenessStatus = $request->query('completeness_status', '');

        $query = Customer::query()
            ->forUser()
            ->with(['city', 'district', 'village', 'internetPackage', 'subscriptionStatus', 'pop']);

        // Search filter
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('customer_code', 'like', "%{$search}%")
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
        $statusCounts = Customer::forUser()->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $totalCustomers = Customer::forUser()->count();

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
        $pops = \App\Models\Pop::forUser()->get();
        return view('customers.create', compact('districts', 'packages', 'cities', 'pops'));
    }

    /**
     * Store a newly created customer in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:150',
            'identity_number' => 'nullable|string|max:50',
            'gender' => 'nullable|string|max:20',
            'primary_phone' => 'required|string|max:20',
            'alternative_phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'registration_date' => 'required|date',
            'pop_id' => 'required|exists:pops,id',
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
            
            // Documents
            'foto_ktp' => 'nullable|file|image|max:2048',
            'foto_rumah' => 'nullable|file|image|max:2048',
            'foto_kontrak' => 'nullable|file|mimes:jpeg,png,pdf|max:2048',
        ]);

        $validated['phone'] = $validated['primary_phone'];
        $validated['created_by'] = auth()->id();
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

        if ($request->hasFile('foto_ktp')) {
            $validated['foto_ktp'] = $request->file('foto_ktp')->store('documents', 'public');
        }
        if ($request->hasFile('foto_rumah')) {
            $validated['foto_rumah'] = $request->file('foto_rumah')->store('documents', 'public');
        }
        if ($request->hasFile('foto_kontrak')) {
            $validated['foto_kontrak'] = $request->file('foto_kontrak')->store('documents', 'public');
        }

        // Generate customer_code via POP sequence generator
        $pop = Pop::findOrFail($validated['pop_id']);
        $customerCode = $pop->generateRegistrationNumber();
        $validated['customer_code'] = $customerCode;

        $customer = \Illuminate\Support\Facades\DB::transaction(function() use ($validated) {
            // 1. Create customer record
            $customer = Customer::create($validated);

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

                // Calculate total bill
                $discountedPrice = max(0, $monthlyPrice - $discount);
                $totalBill = $discountedPrice * (1 + $ppn / 100);

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

            return $customer;
        });

        return redirect()->route('customers.index')->with('success', "Pelanggan {$validated['full_name']} berhasil ditambahkan dengan ID REG {$customerCode}!");
    }

    /**
     * Show the form for editing the specified customer.
     */
    public function edit(Customer $customer)
    {
        $districts = \App\Models\District::orderBy('name')->get();
        $packages = \App\Models\InternetPackage::orderBy('name')->get();
        $cities = \App\Models\City::orderBy('name')->get();
        $pops = \App\Models\Pop::forUser()->get();
        return view('customers.edit', compact('customer', 'districts', 'packages', 'cities', 'pops'));
    }

    /**
     * Update the specified customer in storage.
     */
    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:150',
            'identity_number' => 'nullable|string|max:50',
            'gender' => 'nullable|string|max:20',
            'primary_phone' => 'required|string|max:20',
            'alternative_phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'registration_date' => 'required|date',
            'pop_id' => 'required|exists:pops,id',
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

        // Handle deletions
        if ($request->input('delete_foto_ktp') == '1') {
            if ($customer->foto_ktp) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($customer->foto_ktp);
            }
            $validated['foto_ktp'] = null;
        } else {
            $validated['foto_ktp'] = $customer->foto_ktp;
        }
        if ($request->input('delete_foto_rumah') == '1') {
            if ($customer->foto_rumah) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($customer->foto_rumah);
            }
            $validated['foto_rumah'] = null;
        } else {
            $validated['foto_rumah'] = $customer->foto_rumah;
        }
        if ($request->input('delete_foto_kontrak') == '1') {
            if ($customer->foto_kontrak) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($customer->foto_kontrak);
            }
            $validated['foto_kontrak'] = null;
        } else {
            $validated['foto_kontrak'] = $customer->foto_kontrak;
        }

        // Handle new uploads
        if ($request->hasFile('foto_ktp')) {
            if ($customer->foto_ktp) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($customer->foto_ktp);
            }
            $validated['foto_ktp'] = $request->file('foto_ktp')->store('documents', 'public');
        }
        if ($request->hasFile('foto_rumah')) {
            if ($customer->foto_rumah) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($customer->foto_rumah);
            }
            $validated['foto_rumah'] = $request->file('foto_rumah')->store('documents', 'public');
        }
        if ($request->hasFile('foto_kontrak')) {
            if ($customer->foto_kontrak) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($customer->foto_kontrak);
            }
            $validated['foto_kontrak'] = $request->file('foto_kontrak')->store('documents', 'public');
        }

        \Illuminate\Support\Facades\DB::transaction(function() use ($customer, $validated) {
            // 1. Update customer record
            $customer->update($validated);

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

                // Calculate total bill
                $discountedPrice = max(0, $monthlyPrice - $discount);
                $totalBill = $discountedPrice * (1 + $ppn / 100);

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

        return redirect()->route('customers.show', $customer->id)->with('success', "Data pelanggan {$customer->full_name} berhasil diperbarui!");
    }

    public function show(Customer $customer)
    {
        $customer->load([
            'city', 
            'district', 
            'village', 
            'internetPackage', 
            'subscriptionStatus', 
            'pop', 
            'customerAddress', 
            'customerService', 
            'creator', 
            'updater'
        ]);

        $status = $customer->status;
        $regDate = \Carbon\Carbon::parse($customer->registration_date);
        
        // Dynamic completeness calculation
        $completeness = $customer->dataCompleteness();

        // Format display ID (CID for active/suspended, REQ for others)
        $isCustomer = in_array($status, ['active', 'suspended']);
        $displayId = $isCustomer 
            ? str_replace('WHUS-', 'CID-', $customer->customer_code) 
            : str_replace('WHUS-', 'REQ-', $customer->customer_code);

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

        return view('customers.show', compact(
            'customer',
            'displayId',
            'completeness',
            'timeline'
        ));
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
     * Validate the parsed rows from import (Excel/CSV or Copy-Paste).
     */
    public function validateImport(Request $request)
    {
        $rows = $request->input('rows', []);
        $validatedRows = [];

        foreach ($rows as $row) {
            $originalNo = $row['no'] ?? '';
            $originalId = trim((string)($row['id'] ?? ''));
            $originalNama = trim((string)($row['nama'] ?? ''));
            $originalDesa = trim((string)($row['desa'] ?? ''));
            $originalPaket = trim((string)($row['paket'] ?? ''));
            $originalHp = trim((string)($row['hp'] ?? ''));
            $originalKoordinat = trim((string)($row['koordinat'] ?? ''));

            $errors = [];
            $warnings = [];
            $statusRow = 'valid';

            // 1. Validate Nama (Mandatory)
            if ($originalNama === '') {
                $errors[] = 'Nama lengkap wajib diisi.';
                $statusRow = 'error';
            }

            // 2. Validate HP (Mandatory)
            if ($originalHp === '') {
                $errors[] = 'Nomor HP wajib diisi.';
                $statusRow = 'error';
            } else {
                // Check if duplicate phone exists in database
                $phoneExists = \App\Models\Customer::where('phone', $originalHp)->exists();
                if ($phoneExists) {
                    $warnings[] = 'Nomor HP sudah terdaftar di database.';
                }
            }

            // 3. Validate Identity NIK (Mandatory)
            if ($originalId === '') {
                $errors[] = 'ID/NIK wajib diisi.';
                $statusRow = 'error';
            } else {
                // Check duplicate NIK
                $nikExists = \App\Models\Customer::where('identity_number', $originalId)->exists();
                if ($nikExists) {
                    $warnings[] = 'ID/NIK sudah terdaftar di database.';
                }
            }

            // 4. Match Desa (Village)
            $villageId = null;
            $districtId = null;
            $cityId = null;
            $villageName = null;

            if ($originalDesa !== '') {
                $village = \App\Models\Village::with('district.city')
                    ->where('name', 'like', $originalDesa)
                    ->first();

                if ($village) {
                    $villageId = $village->id;
                    $villageName = $village->name;
                    $districtId = $village->district_id;
                    if ($village->district) {
                        $cityId = $village->district->city_id;
                    }
                } else {
                    $warnings[] = "Desa '{$originalDesa}' tidak ditemukan di database. Silakan pilih manual.";
                    if ($statusRow !== 'error') $statusRow = 'warning';
                }
            } else {
                $errors[] = 'Nama desa wajib diisi.';
                $statusRow = 'error';
            }

            // 5. Match Paket (Service Package)
            $packageId = null;
            $packageCode = null;

            if ($originalPaket !== '') {
                $package = \App\Models\InternetPackage::where('package_code', 'like', $originalPaket)
                    ->orWhere('name', 'like', $originalPaket)
                    ->first();

                if ($package) {
                    $packageId = $package->id;
                    $packageCode = $package->package_code;
                } else {
                    $warnings[] = "Paket '{$originalPaket}' tidak ditemukan di database. Silakan pilih manual.";
                    if ($statusRow !== 'error') $statusRow = 'warning';
                }
            } else {
                $errors[] = 'Paket internet wajib diisi.';
                $statusRow = 'error';
            }

            // 6. Parse Koordinat
            $latitude = null;
            $longitude = null;
            if ($originalKoordinat !== '') {
                $coords = explode(',', $originalKoordinat);
                if (count($coords) === 2) {
                    $lat = trim($coords[0]);
                    $lng = trim($coords[1]);
                    if (is_numeric($lat) && is_numeric($lng)) {
                        $latitude = floatval($lat);
                        $longitude = floatval($lng);
                    } else {
                        $warnings[] = 'Format koordinat tidak valid (harus angka numerik: lat, long).';
                        if ($statusRow !== 'error') $statusRow = 'warning';
                    }
                } else {
                    $warnings[] = 'Format koordinat tidak valid (harus dipisah koma: lat, long).';
                    if ($statusRow !== 'error') $statusRow = 'warning';
                }
            }

            $validatedRows[] = [
                'original_no' => $originalNo,
                'original_id' => $originalId,
                'original_nama' => $originalNama,
                'original_desa' => $originalDesa,
                'original_paket' => $originalPaket,
                'original_hp' => $originalHp,
                'original_koordinat' => $originalKoordinat,

                'full_name' => $originalNama,
                'identity_number' => $originalId,
                'gender' => 'Laki-laki', // default
                'phone' => $originalHp,
                'email' => null,
                'registration_date' => now()->format('Y-m-d'),
                'address' => $originalDesa ? "Alamat Kel. {$originalDesa}" : '',
                
                'city_id' => $cityId,
                'district_id' => $districtId,
                'village_id' => $villageId,
                'village_name' => $villageName,
                
                'internet_package_id' => $packageId,
                'package_code' => $packageCode,
                
                'contract_period_months' => 12,
                'discount_amount' => 0,
                'tax_percent' => 11,
                'status' => 'registered',
                'latitude' => $latitude,
                'longitude' => $longitude,

                'status_row' => $statusRow,
                'errors' => $errors,
                'warnings' => $warnings,
            ];
        }

        return response()->json([
            'success' => true,
            'rows' => $validatedRows,
        ]);
    }

    /**
     * Confirm and store the finalized imported customers.
     */
    public function confirmImport(Request $request)
    {
        $rows = $request->input('rows', []);
        
        if (is_string($rows)) {
            $rows = json_decode($rows, true);
        }

        if (empty($rows) || !is_array($rows)) {
            return redirect()->route('customers.import')->withErrors('Tidak ada data yang di-import atau format data tidak valid.');
        }

        $insertedCount = 0;

        \Illuminate\Support\Facades\DB::transaction(function() use ($rows, &$insertedCount) {
            $year = now()->format('Y');
            
            // Find latest customer code or ID
            $latestCustomer = \App\Models\Customer::orderBy('id', 'desc')->first();
            $nextId = $latestCustomer ? $latestCustomer->id + 1 : 1;

            foreach ($rows as $row) {
                // double check required database fields
                if (empty($row['full_name']) || empty($row['phone']) || empty($row['village_id']) || empty($row['internet_package_id'])) {
                    continue; // Skip invalid rows that somehow bypassed frontend
                }

                $customerCode = "WHUS-{$year}-" . str_pad((string)$nextId, 4, '0', STR_PAD_LEFT);
                $nextId++;

                \App\Models\Customer::create([
                    'customer_code' => $customerCode,
                    'full_name' => $row['full_name'],
                    'identity_number' => $row['identity_number'] ?? '',
                    'gender' => $row['gender'] ?? 'Laki-laki',
                    'phone' => $row['phone'],
                    'email' => $row['email'] ?? null,
                    'registration_date' => $row['registration_date'] ?? now()->format('Y-m-d'),
                    'address' => $row['address'] ?? ("Alamat Kel. " . ($row['village_name'] ?? '')),
                    'city_id' => $row['city_id'] ?? (\App\Models\City::first()->id ?? 1),
                    'district_id' => $row['district_id'],
                    'village_id' => $row['village_id'],
                    'internet_package_id' => $row['internet_package_id'],
                    'contract_period_months' => $row['contract_period_months'] ?? 12,
                    'discount_amount' => $row['discount_amount'] ?? 0,
                    'tax_percent' => $row['tax_percent'] ?? 11,
                    'status' => $row['status'] ?? 'registered',
                    'latitude' => $row['latitude'] ?? null,
                    'longitude' => $row['longitude'] ?? null,
                ]);
                $insertedCount++;
            }
        });

        return redirect()->route('customers.index')->with('success', "Berhasil meng-import {$insertedCount} data pelanggan baru!");
    }
}
