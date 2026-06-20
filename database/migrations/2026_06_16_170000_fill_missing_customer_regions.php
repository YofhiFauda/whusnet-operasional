<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Customer;
use App\Models\Village;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $customers = Customer::whereNull('village_id')->whereNotNull('address')->where('address', '<>', '')->get();
        if ($customers->isEmpty()) {
            return;
        }

        $villages = Village::with('district.city')->get();

        foreach ($customers as $customer) {
            $address = $customer->address;
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
            
            if (!empty($matches)) {
                usort($matches, function($a, $b) {
                    return $b['score'] <=> $a['score'];
                });
                
                $best = $matches[0]['village'];
                
                // Update customer model
                $customer->updateQuietly([
                    'city_id' => $best->district?->city_id,
                    'district_id' => $best->district_id,
                    'village_id' => $best->id,
                ]);
                
                // Update or create customerAddress
                $customer->customerAddress()->updateOrCreate([], [
                    'city_id' => $best->district?->city_id,
                    'district_id' => $best->district_id,
                    'village_id' => $best->id,
                    'city' => $best->district?->city?->name,
                    'district' => $best->district?->name,
                    'village' => $best->name,
                ]);
                
                // Recalculate completeness
                $customer->recalculateCompleteness();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration needed for data fixing
    }
};
