<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\InternetPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InternetPackageController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $category = $request->query('category');
        $status = $request->query('status');

        $packages = InternetPackage::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('package_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('package_group', 'like', "%{$search}%")
                        ->orWhere('bandwidth_label', 'like', "%{$search}%")
                        ->orWhere('profile', 'like', "%{$search}%")
                        ->orWhere('technical_profile', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($category && array_key_exists($category, InternetPackage::CATEGORIES), function ($query) use ($category) {
                $query->where('category', $category);
            })
            ->when($status === 'active', function ($query) {
                $query->where('is_active', true);
            })
            ->when($status === 'inactive', function ($query) {
                $query->where('is_active', false);
            })
            ->orderBy('category')
            ->orderBy('package_code')
            ->paginate(15)
            ->withQueryString();

        return view('master.paket.index', compact('packages', 'search', 'category', 'status'));
    }

    public function create(): View
    {
        return view('master.paket.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePackage($request);
        $validated['total_price'] = (new InternetPackage($validated))->calculateTotalPrice();

        InternetPackage::create($validated);

        return redirect()
            ->route('master.paket.index')
            ->with('success', 'Paket internet "' . $validated['package_code'] . '" berhasil ditambahkan.');
    }

    public function edit(InternetPackage $paket): View
    {
        return view('master.paket.edit', compact('paket'));
    }

    public function update(Request $request, InternetPackage $paket): RedirectResponse
    {
        $validated = $this->validatePackage($request, $paket);
        $paket->fill($validated);
        $validated['total_price'] = $paket->calculateTotalPrice();

        $paket->update($validated);

        return redirect()
            ->route('master.paket.index')
            ->with('success', 'Paket internet "' . $paket->package_code . '" berhasil diperbarui.');
    }

    public function toggleStatus(InternetPackage $paket): RedirectResponse
    {
        $paket->update(['is_active' => ! $paket->is_active]);

        $statusText = $paket->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Paket \"{$paket->package_code}\" berhasil {$statusText}.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePackage(Request $request, ?InternetPackage $package = null): array
    {
        $validated = $request->validate([
            'package_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('internet_packages', 'package_code')->ignore($package),
            ],
            'name' => 'required|string|max:150',
            'category' => ['required', 'string', Rule::in(array_keys(InternetPackage::CATEGORIES))],
            'package_group' => 'required|string|max:150',
            'bandwidth_label' => 'required|string|max:50',
            'download_speed_mbps' => 'nullable|numeric|min:0|max:100000',
            'upload_speed_mbps' => 'nullable|numeric|min:0|max:100000',
            'contention_ratio' => 'nullable|integer|min:1|max:255',
            'monthly_price' => 'required|numeric|min:0',
            'ppn' => 'nullable|numeric|min:0|max:100',
            'discount_default' => 'nullable|numeric|min:0|max:100',
            'modem' => 'nullable|string|max:100',
            'max_users' => 'nullable|integer|min:1',
            'ip_address_type' => 'nullable|string|max:100',
            'contract_period_months' => 'nullable|integer|min:1|max:120',
            'installation_fee' => 'nullable|numeric|min:0',
            'installation_fee_label' => 'nullable|string|max:150',
            'profile' => 'nullable|string|max:100',
            'technical_profile' => 'nullable|string|max:100',
            'terms' => 'nullable|string',
            'description' => 'nullable|string',
            'is_active' => 'required|boolean',
        ]);

        $validated['ppn'] = $validated['ppn'] ?? 0;
        $validated['discount_default'] = $validated['discount_default'] ?? 0;

        return $validated;
    }
}
