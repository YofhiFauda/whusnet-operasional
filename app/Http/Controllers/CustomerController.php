<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\City;
use App\Models\District;
use App\Models\Village;
use App\Models\InternetPackage;
use App\Models\SubscriptionStatus;
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

        $query = Customer::query()
            ->with(['city', 'district', 'village', 'internetPackage', 'subscriptionStatus']);

        // Search filter
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('customer_code', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
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

        $customers = $query->orderBy('customer_code', 'asc')->paginate(10)->withQueryString();

        // Data for filter selects
        $districts = District::orderBy('name')->get();
        $packages = InternetPackage::orderBy('name')->get();
        $subscriptionStatuses = SubscriptionStatus::query()
            ->where('is_active', true)
            ->orderBy('workflow_order')
            ->get();

        // Customer count by status (for badge list / submenus)
        $statusCounts = Customer::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $totalCustomers = Customer::count();

        return view('customers.index', compact(
            'customers', 
            'districts', 
            'packages', 
            'statusCounts', 
            'totalCustomers',
            'subscriptionStatuses',
            'search',
            'status',
            'districtId',
            'packageId'
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
        return view('customers.create', compact('districts', 'packages', 'cities'));
    }

    /**
     * Store a newly created customer in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:150',
            'identity_number' => 'required|string|max:50',
            'gender' => 'required|string|max:20',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:100',
            'registration_date' => 'required|date',
            'address' => 'required|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'city_id' => 'required|exists:cities,id',
            'district_id' => 'required|exists:districts,id',
            'village_id' => 'required|exists:villages,id',
            'internet_package_id' => 'required|exists:internet_packages,id',
            'contract_period_months' => 'required|integer|min:1',
            'discount_amount' => 'required|numeric|min:0',
            'tax_percent' => 'required|numeric|between:0,100',
            
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

        if ($request->hasFile('foto_ktp')) {
            $validated['foto_ktp'] = $request->file('foto_ktp')->store('documents', 'public');
        }
        if ($request->hasFile('foto_rumah')) {
            $validated['foto_rumah'] = $request->file('foto_rumah')->store('documents', 'public');
        }
        if ($request->hasFile('foto_kontrak')) {
            $validated['foto_kontrak'] = $request->file('foto_kontrak')->store('documents', 'public');
        }

        // Generate customer_code (e.g. WHUS-2026-0001)
        $year = now()->format('Y');
        $latestCustomer = Customer::orderBy('id', 'desc')->first();
        $nextId = $latestCustomer ? $latestCustomer->id + 1 : 1;
        $customerCode = "WHUS-{$year}-" . str_pad((string)$nextId, 4, '0', STR_PAD_LEFT);
        
        $validated['customer_code'] = $customerCode;

        Customer::create($validated);

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
        return view('customers.edit', compact('customer', 'districts', 'packages', 'cities'));
    }

    /**
     * Update the specified customer in storage.
     */
    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:150',
            'identity_number' => 'required|string|max:50',
            'gender' => 'required|string|max:20',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:100',
            'registration_date' => 'required|date',
            'address' => 'required|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'city_id' => 'required|exists:cities,id',
            'district_id' => 'required|exists:districts,id',
            'village_id' => 'required|exists:villages,id',
            'internet_package_id' => 'required|exists:internet_packages,id',
            'contract_period_months' => 'required|integer|min:1',
            'discount_amount' => 'required|numeric|min:0',
            'tax_percent' => 'required|numeric|between:0,100',
            
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

        // Handle deletions
        if ($request->input('delete_foto_ktp') == '1') {
            if ($customer->foto_ktp) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($customer->foto_ktp);
            }
            $validated['foto_ktp'] = null;
        }
        if ($request->input('delete_foto_rumah') == '1') {
            if ($customer->foto_rumah) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($customer->foto_rumah);
            }
            $validated['foto_rumah'] = null;
        }
        if ($request->input('delete_foto_kontrak') == '1') {
            if ($customer->foto_kontrak) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($customer->foto_kontrak);
            }
            $validated['foto_kontrak'] = null;
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

        $customer->update($validated);

        return redirect()->route('customers.show', $customer->id)->with('success', "Data pelanggan {$customer->full_name} berhasil diperbarui!");
    }

    /**
     * Display the detailed customer view with 12 tabs.
     */
    public function show(Customer $customer)
    {
        $customer->load(['city', 'district', 'village', 'internetPackage', 'subscriptionStatus']);

        $status = $customer->status;
        $regDate = $customer->registration_date;
        $monthlyPrice = $customer->internetPackage ? (float)$customer->internetPackage->monthly_price : 150000.0;
        
        // Extended Pricing snapshot
        $contractPeriod = $customer->contract_period_months ?? 12;
        $discountAmount = (float)($customer->discount_amount ?? 0.00);
        $taxPercent = (float)($customer->tax_percent ?? 11.00);
        
        $taxableAmount = max(0, $monthlyPrice - $discountAmount);
        $taxAmount = round($taxableAmount * ($taxPercent / 100), 2);
        $totalMonthlyCost = $taxableAmount + $taxAmount;

        // Dynamic Prorate Billing Calculation
        $daysInMonth = $regDate->daysInMonth;
        $activeDays = $daysInMonth - $regDate->day + 1;
        $prorateAmount = round(($activeDays / $daysInMonth) * $monthlyPrice, 2);
        
        $installationFee = $customer->internetPackage ? (float)$customer->internetPackage->installation_fee : 150000.0;
        $extraCableFee = 25000.0; // 5 meters @ 5000/meter
        $totalInitialPayment = $prorateAmount + $installationFee + $extraCableFee;

        // Display ID (CID for active, REQ for others)
        $isCustomer = $status === 'active';
        $displayId = $isCustomer ? str_replace('WHUS-', 'CID-', $customer->customer_code) : str_replace('WHUS-', 'REQ-', $customer->customer_code);
        $completedStatuses = ['active', 'suspended', 'terminated'];

        // Determine current status rank
        $statusRank = match ($status) {
            'registered' => 1,
            'waiting_survey' => 2,
            'surveyed' => 3,
            'waiting_installation' => 4,
            'installed' => 5,
            'active', 'suspended', 'terminated' => 6,
            default => 1,
        };

        // 1. TIMELINE DATA (Status-dependent workflow)
        $timeline = [
            [
                'step' => 'Registrasi',
                'title' => 'Pendaftaran Pelanggan',
                'date' => IndonesianDate::date($regDate),
                'notes' => 'Registrasi berhasil oleh Admin dengan Kode ' . $customer->customer_code,
                'status' => 'completed',
            ],
            [
                'step' => 'Survey',
                'title' => 'Survey Kelayakan Lokasi',
                'date' => $statusRank >= 3 ? IndonesianDate::date($regDate->copy()->addDays(2)) : '-',
                'notes' => $statusRank >= 3 
                    ? 'Hasil: LAYAK (Kabel dropcore 85m, ODP terdekat ' . ($customer->odp_code ?? 'ODP-PON-024') . ')'
                    : ($statusRank == 2 ? 'Survey sedang dijadwalkan / dalam proses.' : 'Menunggu penyelesaian tahap sebelumnya.'),
                'status' => $statusRank >= 3 ? 'completed' : ($statusRank == 2 ? 'current' : 'pending'),
            ],
            [
                'step' => 'FOP',
                'title' => 'Penugasan FOP Jaringan',
                'date' => $statusRank >= 4 ? IndonesianDate::date($regDate->copy()->addDays(3)) : '-',
                'notes' => $statusRank >= 4 
                    ? 'Penugasan FOP-2026-081 diterbitkan untuk tim teknisi Ponorogo'
                    : ($statusRank == 3 ? 'Tim FOP sedang mempersiapkan SPK pemasangan.' : 'Menunggu penyelesaian tahap sebelumnya.'),
                'status' => $statusRank >= 4 ? 'completed' : ($statusRank == 3 ? 'current' : 'pending'),
            ],
            [
                'step' => 'Pemasangan',
                'title' => 'Penarikan Kabel & ONT',
                'date' => $statusRank >= 5 ? IndonesianDate::date($regDate->copy()->addDays(4)) : '-',
                'notes' => $statusRank >= 5 
                    ? 'Pemasangan perangkat ONT selesai. Redaman awal -17.80 dBm'
                    : ($statusRank == 4 ? 'Teknisi sedang melakukan penarikan kabel dropcore ke lokasi.' : 'Menunggu penyelesaian tahap sebelumnya.'),
                'status' => $statusRank >= 5 ? 'completed' : ($statusRank == 4 ? 'current' : 'pending'),
            ],
            [
                'step' => 'Uji Layanan',
                'title' => 'Quality & Speedtest Validation',
                'date' => $statusRank >= 5 ? IndonesianDate::date($regDate->copy()->addDays(5)) : '-',
                'notes' => $statusRank >= 5 
                    ? 'Uji Speedtest lulus dengan Kualitas A+ (Packet loss 0%)'
                    : 'Menunggu perangkat ONT terpasang.',
                'status' => $statusRank >= 5 ? 'completed' : 'pending',
            ],
            [
                'step' => 'Aktivasi',
                'title' => 'Aktivasi PPPoE Billing',
                'date' => $statusRank >= 6 ? IndonesianDate::date($regDate->copy()->addDays(5)) : '-',
                'notes' => $statusRank >= 6 
                    ? ($status === 'active' ? 'Layanan telah aktif sepenuhnya' : ($status === 'suspended' ? 'Layanan diisolir sementara' : 'Layanan diterminasi'))
                    : 'Menunggu proses pemasangan & uji layanan selesai.',
                'status' => $statusRank >= 6 
                    ? ($status === 'suspended' ? 'warning' : ($status === 'terminated' ? 'danger' : 'completed'))
                    : 'pending',
            ],
        ];

        // 2. SURVEY LOGS
        $survey = [
            'status' => $statusRank >= 3 ? 'Completed' : ($statusRank == 2 ? 'Scheduled' : 'Pending'),
            'badge_class' => $statusRank >= 3 ? 'bg-green-50 text-green-700 border border-green-100' : 'bg-amber-50 text-amber-700 border border-amber-100',
            'badge_text' => $statusRank >= 3 ? 'Layak Pasang' : ($statusRank == 2 ? 'Dalam Penjadwalan' : 'Menunggu Tahapan'),
            'start_date' => $statusRank >= 3 ? IndonesianDate::dateTime($regDate->copy()->addDays(2)->setTime(9, 0)) : ($statusRank == 2 ? 'Terjadwal' : '-'),
            'end_date' => $statusRank >= 3 ? IndonesianDate::dateTime($regDate->copy()->addDays(2)->setTime(10, 30)) : '-',
            'duration' => $statusRank >= 3 ? '1 Jam 30 Menit' : '-',
            'surveyors' => $statusRank >= 3 ? ['Rafi Ahmad (Surveyor)', 'Budi Sudarsono (Surveyor)'] : [],
            'tools' => $statusRank >= 3 ? ['Kabel Drop Core 85m', 'Tiang Besi 1 unit', 'S-Hanger 3 pcs', 'Protection Sleeve 2 pcs'] : [],
            'latitude' => $customer->latitude ?? 'Belum terdata',
            'longitude' => $customer->longitude ?? 'Belum terdata',
            'notes' => $statusRank >= 3 
                ? 'Jalur kabel aman, tidak melewati jalan raya besar. ODP ' . ($customer->odp_code ?? 'ODP-PON-024') . ' port 5 tersedia redaman ODP -16.5 dBm.'
                : ($statusRank == 2 ? 'Jadwal survey dalam antrean tim lapangan.' : 'Belum dijadwalkan.'),
        ];

        // 3. FOP DATA
        $fop = [
            'fop_id' => $statusRank >= 4 ? 'FOP-2026-081' : 'N/A',
            'assigned_survey' => $statusRank >= 2 ? IndonesianDate::dateTime($regDate->copy()->addDay()) : '-',
            'assigned_installation' => $statusRank >= 4 ? IndonesianDate::dateTime($regDate->copy()->addDays(3)) : '-',
            'coordinator' => $statusRank >= 3 ? 'Bambang Tri (FOP Leader)' : '-',
            'status' => $statusRank >= 4 ? 'Completed' : ($statusRank == 3 ? 'Assigned' : 'Pending'),
        ];

        // 4. INSTALLATION DATA
        $installation = [
            'status' => $statusRank >= 5 ? 'Success' : ($statusRank == 4 ? 'In Progress' : 'Pending'),
            'badge_class' => $statusRank >= 5 ? 'bg-green-50 text-green-700 border border-green-100' : 'bg-amber-50 text-amber-700 border border-amber-100',
            'badge_text' => $statusRank >= 5 ? 'Selesai Terpasang' : ($statusRank == 4 ? 'Dalam Pemasangan' : 'Menunggu Antrean'),
            'date' => $statusRank >= 5 ? IndonesianDate::date($regDate->copy()->addDays(4)) : '-',
            'start_time' => $statusRank >= 5 ? IndonesianDate::time($regDate->copy()->addDays(4)->setTime(13, 0)) : '-',
            'end_time' => $statusRank >= 5 ? IndonesianDate::time($regDate->copy()->addDays(4)->setTime(15, 30)) : '-',
            'technicians' => $statusRank >= 4 ? ['Roni Setiawan (Teknisi 1)', 'Andik Vermansyah (Teknisi 2)'] : [],
            'materials' => $statusRank >= 5 ? 'Kabel Dropcore 80m terpakai, ONT ' . ($customer->ont_sn ?? 'ZTE') . ' 1 Unit, Patchcord 3m 1 Pcs, Fast Connector 2 Pcs.' : '-',
            'notes' => $statusRank >= 5 ? 'ONT diletakkan di ruang keluarga. Hasil rapi dan redaman awal bagus.' : ($statusRank == 4 ? 'Pemasangan sedang dipersiapkan oleh teknisi.' : 'Belum terpasang.'),
        ];

        // 5. ACTIVATION DATA
        $activation = [
            'status' => $statusRank >= 6 ? 'Active' : 'Pending',
            'date' => $statusRank >= 6 ? IndonesianDate::date($regDate->copy()->addDays(5)) : '-',
            'time' => $statusRank >= 6 ? IndonesianDate::time($regDate->copy()->addDays(5)->setTime(10, 0)) : '-',
            'staff' => $statusRank >= 6 ? 'Dani Siregar (NOC)' : '-',
            'profile_pppoe' => ($statusRank >= 6 && $customer->ont_sn) ? strtolower(str_replace(' ', '_', $customer->full_name)) . '@whusnet' : 'Belum terkonfigurasi',
        ];

        // 6. TECHNICAL PROFILE
        $technical = [
            'cid' => $status === 'active' ? 'CID-PON-' . str_pad($customer->id, 4, '0', STR_PAD_LEFT) : 'N/A (Tarik Ke Stock)',
            'ip_address' => $customer->ip_address ?? 'Belum dialokasikan',
            'sn' => $customer->ont_sn ?? 'Belum terpasang',
            'passive_device' => 'Splitter 1:8, Patchcord SC-UPC 3 meter',
            'branch' => '03 (Ponorogo)',
            'pop' => 'POP-MAIN-PON',
            'olt' => $customer->olt_code ?? 'Belum ditentukan',
            'olt_port' => $customer->olt_code ? 'GPON 0/1/4' : 'Belum ditentukan',
            'odp' => $customer->odp_code ?? 'Belum terhubung',
            'odp_port' => $customer->odp_code ? 'Port 05' : 'Belum terhubung',
            'router' => 'Router-Core-Ponorogo',
            'initial_attenuation' => $customer->ont_sn ? '-17.80 dBm' : 'Belum diuji',
            'actual_attenuation' => $status === 'active' ? '-18.25 dBm' : 'Belum diuji',
            'vlan' => $customer->vlan_id ?? 'Belum ditentukan',
            'notes' => $customer->ont_sn ? 'Menggunakan IP Dynamic Private dari PPPoE Pool.' : 'Belum terkonfigurasi.',
        ];

        // 7. SPEEDTEST / TEST REPORT
        $testReport = [
            'date' => $statusRank >= 5 ? IndonesianDate::dateTime($regDate->copy()->addDays(5)) : '-',
            'attenuation' => $statusRank >= 5 ? '-17.85 dBm' : '-',
            'jitter' => $statusRank >= 5 ? '1.8 ms' : '-',
            'latency' => $statusRank >= 5 ? '7.2 ms' : '-',
            'upload' => $statusRank >= 5 ? ($customer->internetPackage ? round((float)$customer->internetPackage->download_speed_mbps * 0.95, 2) . ' Mbps' : '47.5 Mbps') : 'N/A',
            'download' => $statusRank >= 5 ? ($customer->internetPackage ? round((float)$customer->internetPackage->download_speed_mbps * 0.96, 2) . ' Mbps' : '48.2 Mbps') : 'N/A',
            'packet_loss' => $statusRank >= 5 ? '0%' : '-',
            'match_percent' => $statusRank >= 5 ? '96%' : '-',
            'quality_score' => $statusRank >= 5 ? 'A+ (Excellent)' : 'Belum diuji',
            'staff' => $statusRank >= 5 ? 'Dani Siregar (NOC Validation)' : '-',
            'speedtest_photo' => $statusRank >= 5 ? 'https://images.unsplash.com/photo-1551836022-d5d88e9218df?auto=format&fit=crop&w=800&q=80' : null,
        ];

        // 8. INITIAL PAYMENT INVOICE
        $initialPayment = [
            'invoice_code' => $statusRank >= 5 ? 'INV-' . $regDate->format('Ymd') . '-' . str_pad($customer->id, 4, '0', STR_PAD_LEFT) : 'Belum terbit',
            'registration_date' => IndonesianDate::date($regDate),
            'activation_date' => $statusRank >= 6 ? IndonesianDate::date($regDate->copy()->addDays(5)) : '-',
            'days_in_month' => $daysInMonth,
            'active_days' => $activeDays,
            'monthly_price' => $monthlyPrice,
            'prorate_amount' => $prorateAmount,
            'installation_fee' => $installationFee,
            'extra_cable_fee' => $extraCableFee,
            'total' => $totalInitialPayment,
            'status' => $statusRank >= 6 ? 'Lunas' : 'Belum Lunas',
            'payment_date' => $statusRank >= 6 ? IndonesianDate::date($regDate->copy()->addDays(6)) : '-',
            'payment_method' => $statusRank >= 6 ? 'Bank Transfer (Mandiri)' : '-',
        ];

        // 9. REFERRAL INFO
        $referral = [
            'sales_id' => $customer->sales_code ?? '-',
            'sales_name' => $customer->sales_code ? 'Sales ' . $customer->sales_code : '-',
            'sales_phone' => $customer->sales_code ? '082134567890' : '-',
            'agent_id' => $customer->agent_code ?? '-',
            'agent_name' => $customer->agent_code ? 'Agent ' . $customer->agent_code : '-',
            'agent_phone' => $customer->agent_code ? '085799988811' : '-',
            'referred_customer' => $customer->referral_customer_code ?? '-',
            'notes' => $customer->sales_code || $customer->agent_code ? 'Pelanggan terdaftar via tim akuisisi lapangan.' : 'Pendaftaran mandiri.',
        ];

        // 10. WORKFLOW TIMELOG & SIGNATURE LOGS
        $baseRegDate = $customer->registration_date->copy();
        $workflowLog = [
            'registration' => [
                'date' => IndonesianDate::dateTime($baseRegDate->copy()->setTime(13, 40, 57)),
                'user' => 'Nama Pengguna A',
            ],
            'survey' => [
                'date' => $statusRank >= 3 ? IndonesianDate::dateTime($baseRegDate->copy()->addDays(1)->setTime(13, 40, 57)) : '-',
                'user' => $statusRank >= 3 ? 'Nama Pengguna B' : '-',
            ],
            'admin_filter' => [
                'date' => $statusRank >= 4 ? IndonesianDate::dateTime($baseRegDate->copy()->addDays(3)->setTime(13, 40, 57)) : '-',
                'user' => $statusRank >= 4 ? 'Nama Pengguna C' : '-',
            ],
            'technician_process' => [
                'date' => $statusRank >= 5 ? IndonesianDate::dateTime($baseRegDate->copy()->addDays(6)->setTime(13, 40, 57)) : '-',
                'user' => $statusRank >= 5 ? 'Nama Pengguna D' : '-',
            ],
            'verification' => [
                'date' => $statusRank >= 6 ? IndonesianDate::dateTime($baseRegDate->copy()->addDays(11)->setTime(9, 21, 55)) : '-',
                'user' => $statusRank >= 6 ? 'Nama Pengguna E' : '-',
            ],
        ];

        return view('customers.show', compact(
            'customer',
            'displayId',
            'timeline',
            'survey',
            'fop',
            'installation',
            'activation',
            'technical',
            'testReport',
            'initialPayment',
            'referral',
            'monthlyPrice',
            'contractPeriod',
            'discountAmount',
            'taxPercent',
            'taxAmount',
            'totalMonthlyCost',
            'workflowLog'
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
