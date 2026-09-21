<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\ItemCondition;
use App\Enums\OwnershipMode;
use App\Enums\SerialStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\TrackingType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Warehouse\Concerns\AuthorizesWarehousePop;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\Pop;
use App\Models\TaskDeviceRetrieval;
use App\Services\EffectiveAccessService;
use App\Services\FileUploadService;
use App\Services\InventoryReassignService;
use App\Services\LegacyDeviceHintService;
use App\Support\RupiahInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * "Terima modem dari pelanggan" — pelanggan yang sudah putus mengantar modem
 * sendiri ke gudang, TANPA task DEAC (ADHOC-88, keputusan user 2026-09-21:
 * fitur khusus, bukan lewat Barang Masuk). Satu langkah, tanpa transit:
 * staf gudang sudah memegang fisiknya. Lihat
 * `InventoryReassignService::receiveSerialFromCustomerAtWarehouse()` untuk
 * alasan tidak memakai jalur pengadaan.
 *
 * Halaman create tiga keadaan (cari → pilih pelanggan → isi form), semuanya
 * server-rendered lewat query string, supaya tidak butuh endpoint pencarian
 * JSON. Permission REUSE `warehouse_reassign.create`.
 */
class WarehouseCustomerReturnController extends Controller
{
    use AuthorizesWarehousePop;

    public function create(Request $request, EffectiveAccessService $access, LegacyDeviceHintService $hints): View|RedirectResponse
    {
        $user = auth()->user();
        $search = trim((string) $request->query('q'));
        $customer = null;
        $matches = collect();
        $blockedReason = null;

        if ($request->filled('customer')) {
            $customer = $this->scopedTerminatedCustomers($access)->find($request->query('customer'));

            if (! $customer) {
                return redirect()->route('warehouse.returns.from-customer.create')
                    ->with('error', 'Pelanggan tidak ditemukan, belum putus langganan, atau di luar akses POP Anda.');
            }

            $blockedReason = $this->blockedReason($customer);
        } elseif ($search !== '') {
            $like = '%'.$search.'%';

            $matches = $this->scopedTerminatedCustomers($access)
                ->where(function ($q) use ($like) {
                    $q->where('full_name', 'like', $like)
                        ->orWhere('customer_code', 'like', $like)
                        ->orWhere('cid', 'like', $like)
                        ->orWhere('primary_phone', 'like', $like)
                        ->orWhereHas('customerTechnicalDetail', fn ($d) => $d->where('router_or_ont_serial', 'like', $like));
                })
                ->orderBy('full_name')
                ->limit(15)
                ->get();
        }

        $cabangPops = Pop::warehouse()
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->whereIn('id', $access->getAllowedPopIds($user)))
            ->orderBy('name')
            ->get();

        $items = $this->serialItems();
        $accessoryOptions = TaskDeviceRetrieval::ACCESSORY_OPTIONS;
        $legacyHint = $customer ? $hints->forCustomer($customer) : null;
        $installedSerials = $customer
            ? InventorySerial::where('customer_id', $customer->id)->where('status', SerialStatus::INSTALLED->value)->with('item')->get()
            : collect();

        return view('warehouse.returns.from-customer', compact(
            'search', 'customer', 'matches', 'blockedReason', 'cabangPops', 'items', 'accessoryOptions', 'legacyHint', 'installedSerials'
        ));
    }

    public function store(Request $request, EffectiveAccessService $access, InventoryReassignService $service): RedirectResponse
    {
        $user = auth()->user();

        // Baris SN kosong (baris repeatable yang tak diisi) dibuang sebelum
        // validasi; nominal dinormalkan di server ("150.000" → 150000).
        $request->merge([
            'serials' => collect($request->input('serials', []))
                ->filter(fn ($row) => is_array($row) && trim((string) ($row['serial_number'] ?? '')) !== '')
                ->values()
                ->all(),
            'estimated_value' => RupiahInput::parse($request->input('estimated_value')),
        ]);

        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'cabang_pop_id' => ['required', 'integer', 'exists:pops,id'],
            'serials' => ['required', 'array', 'min:1'],
            'serials.*.serial_number' => ['required', 'string', 'max:100'],
            'serials.*.item_id' => ['nullable', 'integer', 'exists:items,id'],
            'condition' => ['required', Rule::in([ItemCondition::USED_GOOD->value, ItemCondition::USED_DAMAGED->value])],
            'condition_photo' => ['required', 'image', 'max:2048'],
            'accessories' => ['nullable', 'array'],
            'accessories.*' => ['string', Rule::in(array_keys(TaskDeviceRetrieval::ACCESSORY_OPTIONS))],
            // Opsional: kosong = Rp 0 di Laporan Bulanan. Berlaku per unit.
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'serials.required' => 'Isi minimal satu nomor seri (SN) modem yang diterima.',
            'serials.min' => 'Isi minimal satu nomor seri (SN) modem yang diterima.',
        ]);

        $customer = $this->scopedTerminatedCustomers($access)->find($validated['customer_id']);
        abort_unless($customer, 403, 'Pelanggan belum putus langganan atau di luar akses POP Anda.');

        $cabang = Pop::findOrFail($validated['cabang_pop_id']);
        $this->assertPopInScope($cabang, $user, $access);

        if ($reason = $this->blockedReason($customer)) {
            throw ValidationException::withMessages(['customer_id' => $reason]);
        }

        $duplicate = collect($validated['serials'])->map(fn ($row) => mb_strtolower(trim($row['serial_number'])))->duplicates()->first();
        if ($duplicate !== null) {
            throw ValidationException::withMessages(['serials' => "SN {$duplicate} diisi lebih dari sekali."]);
        }

        $photoPath = FileUploadService::uploadDeviceRetrievalPhoto($request->file('condition_photo'), $customer);

        try {
            // Satu transaksi: kalau SN kedua ditolak, SN pertama ikut batal —
            // staf tidak perlu menebak mana yang sudah tercatat.
            DB::transaction(function () use ($validated, $service, $customer, $cabang, $user, $photoPath) {
                foreach ($validated['serials'] as $row) {
                    $service->receiveSerialFromCustomerAtWarehouse(
                        $row['serial_number'],
                        ! empty($row['item_id']) ? Item::find($row['item_id']) : null,
                        $customer,
                        $cabang,
                        ItemCondition::from($validated['condition']),
                        isset($validated['estimated_value']) ? (float) $validated['estimated_value'] : null,
                        $user,
                        $validated['notes'] ?? null,
                        $photoPath,
                        $validated['accessories'] ?? [],
                    );
                }
            });
        } catch (InvalidArgumentException $e) {
            // Foto sudah terunggah sebelum gerak inventori — jangan tinggalkan file yatim.
            Storage::disk('public')->delete($photoPath);

            throw ValidationException::withMessages(['serials' => $e->getMessage()]);
        }

        return redirect()->route('warehouse.retrievals.index')
            ->with('success', count($validated['serials']).' modem dari '.$customer->full_name.' diterima di '.$cabang->name.'.');
    }

    /**
     * Hanya pelanggan yang SUDAH putus dan dalam scope POP aktor (jalur
     * `EffectiveAccessService`, bukan `user_pops` lama).
     */
    private function scopedTerminatedCustomers(EffectiveAccessService $access)
    {
        $user = auth()->user();

        return Customer::query()
            ->where('status', 'terminated')
            ->when(! $access->hasAllPopAccess($user), fn ($q) => $q->whereIn('pop_id', $access->getAllowedPopIds($user)));
    }

    /**
     * Task Ambil Modem yang masih berjalan untuk pelanggan ini berarti modemnya
     * SEDANG dijemput teknisi — menerima lewat jalur ini juga bikin dua
     * pengambilan untuk satu perangkat.
     */
    private function blockedReason(Customer $customer): ?string
    {
        $hasOpenTask = FopTask::where('customer_id', $customer->id)
            ->where('category', TaskType::AMBIL_MODEM->value)
            ->whereNotIn('status', [TaskStatus::SELESAI->value, TaskStatus::DIBATALKAN->value])
            ->exists();

        return $hasOpenTask
            ? 'Masih ada task Ambil Alat yang berjalan untuk pelanggan ini. Selesaikan atau batalkan task itu dulu supaya modem tidak tercatat dua kali.'
            : null;
    }

    private function serialItems()
    {
        return Item::active()
            ->where('tracking_type', TrackingType::SERIALIZED->value)
            ->where('ownership_mode', OwnershipMode::INSTALLABLE->value)
            ->orderBy('name')
            ->get();
    }
}
