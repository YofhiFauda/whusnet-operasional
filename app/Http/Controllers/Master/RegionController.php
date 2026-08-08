<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\Request;

class RegionController extends Controller
{
    /**
     * Display region master data for address filters and reports.
     *
     * @return mixed
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $cities = City::query()
            ->with(['districts' => function ($districtQuery) use ($search): void {
                $districtQuery
                    ->when($search !== '', function ($query) use ($search): void {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhereHas('villages', function ($villageQuery) use ($search): void {
                                $villageQuery->where('name', 'like', "%{$search}%");
                            });
                    })
                    ->with(['villages' => function ($villageQuery) use ($search): void {
                        $villageQuery
                            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                            ->orderBy('name');
                    }])
                    ->orderBy('name');
            }])
            ->get();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'data' => $cities,
            ]);
        }

        return view('master.wilayah', compact('cities', 'search'));
    }
}
