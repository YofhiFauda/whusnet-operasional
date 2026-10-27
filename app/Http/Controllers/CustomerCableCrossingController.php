<?php

namespace App\Http\Controllers;

use App\Enums\CableCrossingStatus;
use App\Models\CustomerCableCrossing;
use App\Services\EffectiveAccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Daftar pemantauan Kabel Melintang Jalan (ADHOC-205). Tim memakainya untuk
 * menindaklanjuti rute yang belum ditarik. Dibatasi POP scope pelanggan.
 */
class CustomerCableCrossingController extends Controller
{
    public function index(Request $request, EffectiveAccessService $access): View
    {
        $user = $request->user();
        $filter = (string) $request->query('status', 'open');

        $statuses = match ($filter) {
            CableCrossingStatus::TERPASANG->value => [CableCrossingStatus::TERPASANG->value],
            CableCrossingStatus::TIDAK_BISA_DITARIK->value => [CableCrossingStatus::TIDAK_BISA_DITARIK->value],
            CableCrossingStatus::DITARIK->value => [CableCrossingStatus::DITARIK->value],
            default => [CableCrossingStatus::TERPASANG->value, CableCrossingStatus::TIDAK_BISA_DITARIK->value],
        };

        $crossings = CustomerCableCrossing::query()
            ->with(['customer.pop', 'points', 'deactTask'])
            ->whereIn('status', $statuses)
            ->when(! $access->hasAllPopAccess($user), function ($query) use ($access, $user) {
                $query->whereHas('customer', fn ($customer) => $customer->whereIn('pop_id', $access->getAllowedPopIds($user)));
            })
            ->orderBy('dicatat_at')
            ->paginate(25)
            ->withQueryString();

        $filterOptions = [
            'open' => 'Belum selesai',
            CableCrossingStatus::TERPASANG->value => CableCrossingStatus::TERPASANG->label(),
            CableCrossingStatus::TIDAK_BISA_DITARIK->value => CableCrossingStatus::TIDAK_BISA_DITARIK->label(),
            CableCrossingStatus::DITARIK->value => CableCrossingStatus::DITARIK->label(),
        ];

        return view('customer-cable-crossings.index', compact('crossings', 'filter', 'filterOptions'));
    }
}
