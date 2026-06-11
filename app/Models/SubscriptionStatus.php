<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'workflow_order',
    'badge_color',
    'description',
    'is_terminal',
    'is_active',
])]
class SubscriptionStatus extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'workflow_order' => 'integer',
            'is_terminal' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'status', 'code');
    }

    public function badgeClasses(): string
    {
        return match ($this->badge_color) {
            'sky' => 'bg-sky-50 text-sky-700 border-sky-100',
            'blue' => 'bg-blue-50 text-blue-700 border-blue-100',
            'amber' => 'bg-amber-50 text-amber-700 border-amber-100',
            'green' => 'bg-green-50 text-green-700 border-green-100',
            'red' => 'bg-red-50 text-red-700 border-red-100',
            default => 'bg-slate-50 text-slate-700 border-slate-100',
        };
    }
}
