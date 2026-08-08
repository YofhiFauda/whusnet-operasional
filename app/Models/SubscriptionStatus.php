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
            'sky' => 'bg-sky-50 dark:bg-sky-900/20 text-sky-700 dark:text-sky-400 border-sky-100 dark:border-sky-800/30',
            'blue' => 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 border-blue-100 dark:border-blue-800/30',
            'amber' => 'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400 border-amber-100 dark:border-amber-800/30',
            'green' => 'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 border-green-100 dark:border-green-800/30',
            'red' => 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 border-red-100 dark:border-red-800/30',
            default => 'bg-slate-50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-300 border-slate-100 dark:border-slate-700/50',
        };
    }
}
