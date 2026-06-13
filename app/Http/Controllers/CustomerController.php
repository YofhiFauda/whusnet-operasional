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
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            'updater',
            'installations.technician',
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
    public function downloadImportTemplate(): StreamedResponse
    {
        $headers = [
            'old_customer_id',
            'full_name',
            'primary_phone',
            'full_address',
            'village',
            'district',
            'city',
            'pop_code',
            'pop_name',
            'package_name',
            'monthly_price',
            'activation_date',
            'due_date',
            'service_status',
            'identity_number',
            'alternative_phone',
            'email',
            'latitude',
            'longitude',
            'ont_sn',
            'ip_address',
            'odp_code',
            'olt_code',
            'vlan_id',
            'technical_note',
        ];

        $exampleRow = [
            'OLD-0001',
            'Budi Santoso',
            '081234567890',
            'Jl. Merdeka No. 10 RT 01 RW 02',
            'Sukorejo',
            'Sukorejo',
            'Ponorogo',
            'SMN',
            'POP Sukorejo',
            'WHUSNET 20 Mbps',
            '150000',
            '2026-06-01',
            '2026-07-01',
            'aktif',
            '3502180101900001',
            '081298765432',
            'budi@example.com',
            '-7.86940',
            '111.46210',
            'ONT123456789',
            '192.168.1.10',
            'ODP-SMN-001',
            'OLT-SMN-01',
            '100',
            'Field teknis opsional dapat dikosongkan',
        ];

        return response()->streamDownload(function () use ($headers, $exampleRow) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            fputcsv($handle, $exampleRow);
            fclose($handle);
        }, 'template-import-pelanggan.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Validate the parsed rows from import (Excel/CSV or Copy-Paste).
     */
    public function validateImport(Request $request)
    {
        $rows = $request->input('rows', []);
        $validatedRows = [];
        $seenOldCustomerIds = [];
        $seenPhones = [];

        foreach ($rows as $row) {
            $originalNo = $row['no'] ?? '';
            $oldCustomerId = trim((string)($row['old_customer_id'] ?? $row['id'] ?? ''));
            $fullName = trim((string)($row['full_name'] ?? $row['nama'] ?? ''));
            $primaryPhone = trim((string)($row['primary_phone'] ?? $row['hp'] ?? ''));
            $fullAddress = trim((string)($row['full_address'] ?? $row['address'] ?? ''));
            $villageNameInput = trim((string)($row['village'] ?? $row['desa'] ?? ''));
            $districtNameInput = trim((string)($row['district'] ?? ''));
            $cityNameInput = trim((string)($row['city'] ?? ''));
            $popCodeInput = trim((string)($row['pop_code'] ?? ''));
            $popNameInput = trim((string)($row['pop_name'] ?? ''));
            $packageNameInput = trim((string)($row['package_name'] ?? $row['paket'] ?? ''));
            $monthlyPriceInput = trim((string)($row['monthly_price'] ?? ''));
            $activationDateInput = trim((string)($row['activation_date'] ?? ''));
            $dueDateInput = trim((string)($row['due_date'] ?? ''));
            $serviceStatusInput = trim((string)($row['service_status'] ?? $row['status'] ?? ''));
            $identityNumber = trim((string)($row['identity_number'] ?? ''));
            $alternativePhone = trim((string)($row['alternative_phone'] ?? ''));
            $email = trim((string)($row['email'] ?? ''));
            $latitudeInput = trim((string)($row['latitude'] ?? ''));
            $longitudeInput = trim((string)($row['longitude'] ?? ''));
            $legacyCoordinateInput = trim((string)($row['koordinat'] ?? ''));
            $ontSn = trim((string)($row['ont_sn'] ?? ''));
            $ipAddress = trim((string)($row['ip_address'] ?? ''));
            $odpCode = trim((string)($row['odp_code'] ?? ''));
            $oltCode = trim((string)($row['olt_code'] ?? ''));
            $vlanId = trim((string)($row['vlan_id'] ?? ''));
            $technicalNote = trim((string)($row['technical_note'] ?? ''));

            $errors = [];
            $warnings = [];
            $statusRow = 'valid';

            if ($oldCustomerId === '') {
                $errors[] = 'ID pelanggan lama wajib diisi.';
                $statusRow = 'error';
            } else {
                $oldCustomerKey = strtolower($oldCustomerId);

                if (isset($seenOldCustomerIds[$oldCustomerKey])) {
                    $errors[] = 'ID pelanggan lama duplikat di file import.';
                    $statusRow = 'error';
                }

                $seenOldCustomerIds[$oldCustomerKey] = true;

                if (\App\Models\Customer::where('old_customer_id', $oldCustomerId)->exists()) {
                    $errors[] = 'ID pelanggan lama sudah terdaftar di database.';
                    $statusRow = 'error';
                }
            }

            if ($fullName === '') {
                $errors[] = 'Nama lengkap wajib diisi.';
                $statusRow = 'error';
            }

            if ($primaryPhone === '') {
                $errors[] = 'Nomor HP wajib diisi.';
                $statusRow = 'error';
            } else {
                $phoneKey = preg_replace('/\D+/', '', $primaryPhone) ?: $primaryPhone;

                if (isset($seenPhones[$phoneKey])) {
                    $errors[] = 'Nomor HP duplikat di file import.';
                    $statusRow = 'error';
                }

                $seenPhones[$phoneKey] = true;

                $phoneExists = \App\Models\Customer::where('phone', $primaryPhone)
                    ->orWhere('primary_phone', $primaryPhone)
                    ->exists();
                if ($phoneExists) {
                    $errors[] = 'Nomor HP sudah terdaftar di database.';
                    $statusRow = 'error';
                }
            }

            if ($fullAddress === '') {
                $errors[] = 'Alamat lengkap wajib diisi.';
                $statusRow = 'error';
            }

            $villageId = null;
            $districtId = null;
            $cityId = null;
            $villageName = null;
            $districtName = null;
            $cityName = null;

            if ($villageNameInput !== '') {
                $village = \App\Models\Village::with('district.city')
                    ->where('name', $villageNameInput)
                    ->when($districtNameInput !== '', function ($query) use ($districtNameInput) {
                        $query->whereHas('district', function ($districtQuery) use ($districtNameInput) {
                            $districtQuery->where('name', $districtNameInput);
                        });
                    })
                    ->when($cityNameInput !== '', function ($query) use ($cityNameInput) {
                        $query->whereHas('district.city', function ($cityQuery) use ($cityNameInput) {
                            $cityQuery->where('name', $cityNameInput);
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
                    $errors[] = "Desa/Kelurahan '{$villageNameInput}' tidak ditemukan di master wilayah.";
                    $statusRow = 'error';
                }
            } else {
                $errors[] = 'Desa/Kelurahan wajib diisi.';
                $statusRow = 'error';
            }

            if ($districtNameInput === '') {
                $errors[] = 'Kecamatan wajib diisi.';
                $statusRow = 'error';
            }

            if ($cityNameInput === '') {
                $errors[] = 'Kota/Kabupaten wajib diisi.';
                $statusRow = 'error';
            }

            $pop = null;
            if ($popCodeInput === '' && $popNameInput === '') {
                $errors[] = 'POP/Cabang wajib diisi.';
                $statusRow = 'error';
            } else {
                $pop = \App\Models\Pop::query()
                    ->where('status', 'active')
                    ->where(function ($query) use ($popCodeInput, $popNameInput) {
                        if ($popCodeInput !== '') {
                            $query->where('pop_code', $popCodeInput)
                                ->orWhere('code', $popCodeInput);
                        }

                        if ($popNameInput !== '') {
                            $query->orWhere('name', $popNameInput);
                        }
                    })
                    ->first();

                if (! $pop) {
                    $errors[] = 'POP/Cabang tidak ditemukan atau tidak aktif.';
                    $statusRow = 'error';
                }
            }

            $packageId = null;
            $packageCode = null;
            $packageName = null;
            $packageMonthlyPrice = null;

            if ($packageNameInput !== '') {
                $package = \App\Models\InternetPackage::active()
                    ->where(function ($query) use ($packageNameInput) {
                        $query->where('package_code', $packageNameInput)
                            ->orWhere('name', $packageNameInput);
                    })
                    ->first();

                if ($package) {
                    $packageId = $package->id;
                    $packageCode = $package->package_code;
                    $packageName = $package->name;
                    $packageMonthlyPrice = (float) $package->monthly_price;
                } else {
                    $errors[] = "Paket '{$packageNameInput}' tidak ditemukan di master paket aktif.";
                    $statusRow = 'error';
                }
            } else {
                $errors[] = 'Paket internet wajib diisi.';
                $statusRow = 'error';
            }

            $monthlyPrice = null;
            if ($monthlyPriceInput === '') {
                $errors[] = 'Harga paket wajib diisi.';
                $statusRow = 'error';
            } elseif (! is_numeric($monthlyPriceInput)) {
                $errors[] = 'Harga paket harus berupa angka.';
                $statusRow = 'error';
            } else {
                $monthlyPrice = (float) $monthlyPriceInput;

                if ($packageMonthlyPrice !== null && abs($monthlyPrice - $packageMonthlyPrice) > 0.01) {
                    $warnings[] = 'Harga paket berbeda dari master paket. Nilai import akan menjadi snapshot layanan.';
                    if ($statusRow !== 'error') {
                        $statusRow = 'warning';
                    }
                }
            }

            $activationDate = null;
            if ($activationDateInput === '') {
                $errors[] = 'Tanggal aktivasi wajib diisi.';
                $statusRow = 'error';
            } else {
                try {
                    $activationDate = \Carbon\Carbon::parse($activationDateInput)->format('Y-m-d');
                } catch (\Throwable) {
                    $errors[] = 'Tanggal aktivasi tidak valid.';
                    $statusRow = 'error';
                }
            }

            $dueDate = null;
            if ($dueDateInput === '') {
                $errors[] = 'Tanggal jatuh tempo wajib diisi.';
                $statusRow = 'error';
            } else {
                try {
                    $dueDate = \Carbon\Carbon::parse($dueDateInput)->format('Y-m-d');
                } catch (\Throwable) {
                    $errors[] = 'Tanggal jatuh tempo tidak valid.';
                    $statusRow = 'error';
                }
            }

            if ($activationDate && $dueDate && $dueDate < $activationDate) {
                $errors[] = 'Tanggal jatuh tempo tidak boleh lebih awal dari tanggal aktivasi.';
                $statusRow = 'error';
            }

            $statusAliases = [
                'calon_pelanggan' => 'registered',
                'terdaftar' => 'registered',
                'survey' => 'waiting_survey',
                'menunggu_survey' => 'waiting_survey',
                'menunggu_pemasangan' => 'waiting_installation',
                'aktif' => 'active',
                'isolir' => 'suspended',
                'nonaktif' => 'rejected',
                'berhenti' => 'terminated',
            ];

            $normalizedStatusInput = strtolower(str_replace([' ', '-'], '_', $serviceStatusInput));
            $serviceStatus = $statusAliases[$normalizedStatusInput] ?? $normalizedStatusInput;
            $validStatuses = \App\Models\SubscriptionStatus::query()
                ->where('is_active', true)
                ->pluck('code')
                ->all();

            if ($serviceStatusInput === '') {
                $errors[] = 'Status layanan wajib diisi.';
                $statusRow = 'error';
            } elseif (! in_array($serviceStatus, $validStatuses, true)) {
                $errors[] = 'Status layanan tidak sesuai pilihan sistem.';
                $statusRow = 'error';
            }

            $latitude = null;
            $longitude = null;
            if ($legacyCoordinateInput !== '' && ($latitudeInput === '' || $longitudeInput === '')) {
                $coords = explode(',', $legacyCoordinateInput);
                if (count($coords) === 2) {
                    $latitudeInput = trim($coords[0]);
                    $longitudeInput = trim($coords[1]);
                }
            }

            if ($latitudeInput !== '') {
                if (is_numeric($latitudeInput) && (float) $latitudeInput >= -90 && (float) $latitudeInput <= 90) {
                    $latitude = (float) $latitudeInput;
                } else {
                    $warnings[] = 'Latitude tidak valid.';
                    if ($statusRow !== 'error') $statusRow = 'warning';
                }
            }

            if ($longitudeInput !== '') {
                if (is_numeric($longitudeInput) && (float) $longitudeInput >= -180 && (float) $longitudeInput <= 180) {
                    $longitude = (float) $longitudeInput;
                } else {
                    $warnings[] = 'Longitude tidak valid.';
                    if ($statusRow !== 'error') $statusRow = 'warning';
                }
            }

            $missingTechnicalFields = [];
            foreach ([
                'ont_sn' => $ontSn,
                'ip_address' => $ipAddress,
                'odp_code' => $odpCode,
                'olt_code' => $oltCode,
                'vlan_id' => $vlanId,
                'technical_note' => $technicalNote,
            ] as $field => $value) {
                if ($value === '') {
                    $missingTechnicalFields[] = $field;
                }
            }

            if ($missingTechnicalFields !== []) {
                $warnings[] = 'Field teknis opsional belum lengkap: ' . implode(', ', $missingTechnicalFields) . '.';
                if ($statusRow !== 'error') {
                    $statusRow = 'warning';
                }
            }

            $validatedRows[] = [
                'original_no' => $originalNo,
                'old_customer_id' => $oldCustomerId,
                'full_name' => $fullName,
                'identity_number' => $identityNumber,
                'gender' => 'Laki-laki', // default
                'phone' => $primaryPhone,
                'primary_phone' => $primaryPhone,
                'alternative_phone' => $alternativePhone !== '' ? $alternativePhone : null,
                'email' => $email !== '' ? $email : null,
                'registration_date' => $activationDate,
                'address' => $fullAddress,
                
                'pop_id' => $pop?->id,
                'pop_code' => $pop?->pop_code,
                'pop_name' => $pop?->name,
                'city_id' => $cityId,
                'district_id' => $districtId,
                'village_id' => $villageId,
                'village_name' => $villageName,
                'district_name' => $districtName,
                'city_name' => $cityName,
                
                'internet_package_id' => $packageId,
                'package_code' => $packageCode,
                'package_name' => $packageName,
                
                'contract_period_months' => 12,
                'discount_amount' => 0,
                'tax_percent' => 11,
                'monthly_price' => $monthlyPrice,
                'activation_date' => $activationDate,
                'due_date' => $dueDate,
                'status' => $serviceStatus ?: null,
                'service_status' => $serviceStatus ?: null,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'ont_sn' => $ontSn !== '' ? $ontSn : null,
                'ip_address' => $ipAddress !== '' ? $ipAddress : null,
                'odp_code' => $odpCode !== '' ? $odpCode : null,
                'olt_code' => $oltCode !== '' ? $oltCode : null,
                'vlan_id' => $vlanId !== '' ? $vlanId : null,
                'technical_note' => $technicalNote !== '' ? $technicalNote : null,
                'technical_incomplete' => $missingTechnicalFields !== [],
                'missing_technical_fields' => $missingTechnicalFields,

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

    public function confirmImport(Request $request)
    {
        $rows = $request->input('rows', []);
        $fileName = $request->input('file_name', 'Manual Input / Paste');
        
        if (is_string($rows)) {
            $rows = json_decode($rows, true);
        }

        if (empty($rows) || !is_array($rows)) {
            return redirect()->route('customers.import')->withErrors('Tidak ada data yang di-import atau format data tidak valid.');
        }

        $totalRows = count($rows);
        $invalidRows = 0;
        $validRows = 0;
        $insertedCount = 0;

        foreach ($rows as $row) {
            if (($row['status_row'] ?? '') === 'error') {
                $invalidRows++;
            } else {
                $validRows++;
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

        try {
            \Illuminate\Support\Facades\DB::transaction(function() use ($rows, $batch, &$insertedCount) {
                foreach ($rows as $row) {
                    // Skip if status is error or missing required DB fields
                    if (($row['status_row'] ?? '') === 'error') {
                        foreach (($row['errors'] ?? []) as $errMsg) {
                            \App\Models\ImportError::create([
                                'import_batch_id' => $batch->id,
                                'row_number' => $row['original_no'] ?? null,
                                'error_message' => $errMsg,
                                'raw_data' => $row,
                            ]);
                        }
                        continue;
                    }

                    if (empty($row['full_name']) || (empty($row['phone']) && empty($row['primary_phone'])) || empty($row['village_id']) || empty($row['internet_package_id']) || empty($row['pop_id'])) {
                        \App\Models\ImportError::create([
                            'import_batch_id' => $batch->id,
                            'row_number' => $row['original_no'] ?? null,
                            'error_message' => 'Data wajib database kosong (Nama/HP/Wilayah/Paket/POP).',
                            'raw_data' => $row,
                        ]);
                        continue;
                    }

                    $pop = \App\Models\Pop::findOrFail($row['pop_id']);
                    $customerCode = $pop->generateRegistrationNumber();

                    $customer = \App\Models\Customer::create([
                        'customer_code' => $customerCode,
                        'old_customer_id' => $row['old_customer_id'] ?? null,
                        'full_name' => $row['full_name'],
                        'identity_number' => $row['identity_number'] ?? '',
                        'gender' => $row['gender'] ?? 'Laki-laki',
                        'phone' => $row['phone'] ?? $row['primary_phone'],
                        'primary_phone' => $row['primary_phone'] ?? $row['phone'],
                        'alternative_phone' => $row['alternative_phone'] ?? null,
                        'email' => $row['email'] ?? null,
                        'registration_date' => $row['registration_date'] ?? now()->format('Y-m-d'),
                        'pop_id' => $row['pop_id'],
                        'status' => $row['status'] ?? 'registered',
                        'customer_status' => $this->mapServiceStatusToCustomerStatus($row['status'] ?? 'registered'),
                        'created_by' => auth()->id(),
                        
                        // Technical fields
                        'ont_sn' => $row['ont_sn'] ?? null,
                        'ip_address' => $row['ip_address'] ?? null,
                        'odp_code' => $row['odp_code'] ?? null,
                        'olt_code' => $row['olt_code'] ?? null,
                        'vlan_id' => $row['vlan_id'] ?? null,
                    ]);

                    \App\Models\CustomerAddress::create([
                        'customer_id' => $customer->id,
                        'full_address' => $row['address'] ?? $row['full_address'] ?? ("Alamat Kel. " . ($row['village_name'] ?? '')),
                        'city_id' => $row['city_id'],
                        'district_id' => $row['district_id'],
                        'village_id' => $row['village_id'],
                        'latitude' => $row['latitude'] ?? null,
                        'longitude' => $row['longitude'] ?? null,
                    ]);

                    $package = \App\Models\InternetPackage::find($row['internet_package_id']);
                    
                    \App\Models\CustomerService::create([
                        'customer_id' => $customer->id,
                        'internet_package_id' => $row['internet_package_id'],
                        'package_name_snapshot' => $package->name,
                        'download_speed_snapshot' => $package->download_speed_mbps,
                        'upload_speed_snapshot' => $package->upload_speed_mbps,
                        'monthly_price' => $row['monthly_price'] ?? $package->monthly_price,
                        'discount' => $row['discount_amount'] ?? 0,
                        'ppn' => $row['tax_percent'] ?? 11,
                        'total_monthly_bill' => ($row['monthly_price'] ?? $package->monthly_price) + (($row['monthly_price'] ?? $package->monthly_price) * ($row['tax_percent'] ?? 11) / 100) - ($row['discount_amount'] ?? 0),
                        'activation_date' => $row['activation_date'] ?? $row['registration_date'] ?? now()->format('Y-m-d'),
                        'due_date' => $row['due_date'] ?? null,
                        'service_status' => $row['status'] ?? 'registered',
                        'billing_status' => 'inactive',
                    ]);

                    $insertedCount++;
                }
                
                $batch->update([
                    'imported_rows' => $insertedCount,
                    'status' => 'imported',
                ]);
            });
        } catch (\Exception $e) {
            $batch->update(['status' => 'failed']);
            \Illuminate\Support\Facades\Log::error('Import Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->route('customers.import')->withErrors('Gagal meng-import data: ' . $e->getMessage());
        }

        return redirect()->route('customers.index')->with('success', "Berhasil meng-import {$insertedCount} data pelanggan baru! (Batch: {$batch->batch_number})");
    }

    public function activate(Customer $customer)
    {
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

        if (!$pop->cid_prefix || !$pop->pop_code) {
            return redirect()->route('customers.show', $customer->id)
                ->with('error', 'Konfigurasi prefix CID atau kode POP pada POP asal pelanggan belum lengkap.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($customer, $service, $pop) {
            $cid = $pop->generateCid();

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
        });

        return redirect()->route('customers.show', $customer->id)
            ->with('success', "Layanan pelanggan berhasil diaktifkan dengan CID: {$customer->cid}!");
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
        // 1. Authorization checks
        if (!auth()->user()->hasPermission('create_invoices')) {
            abort(403, 'Anda tidak memiliki akses untuk membuat tagihan.');
        }

        // Scope check for user's assigned POPs
        if (!Customer::query()->forUser()->where('id', $customer->id)->exists()) {
            abort(403, 'Anda tidak memiliki akses ke data pelanggan di POP ini.');
        }

        // 2. Validate request
        $validated = $request->validate([
            'billing_period' => 'required|date_format:Y-m',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issue_date',
        ]);

        $billingPeriod = $validated['billing_period'];
        $issueDate = $validated['issue_date'];
        $dueDate = $validated['due_date'];

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
        
        $invoice = \Illuminate\Support\Facades\DB::transaction(function () use ($customer, $service, $billingPeriod, $issueDate, $dueDate, $periodCode) {
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

            // Fetch pricing details from service snapshot
            $subtotal = (float)$service->monthly_price;
            $discount = (float)($service->discount ?? 0.00);
            $ppnPercent = (float)($service->ppn ?? 0.00);
            $totalAmount = (float)$service->total_monthly_bill;
            $paidAmount = 0.00;
            $remainingAmount = $totalAmount;

            $newInvoice = \App\Models\Invoice::create([
                'invoice_number' => $invoiceNumber,
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
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'invoice_status' => 'belum_dibayar',
                'created_by' => auth()->id(),
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
