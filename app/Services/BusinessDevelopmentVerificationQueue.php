<?php

namespace App\Services;

use App\Enums\WorkflowTransition;
use App\Models\Customer;
use App\Models\PackageCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class BusinessDevelopmentVerificationQueue
{
    public function __construct(private readonly EffectiveAccessService $access) {}

    /**
     * @return Builder<Customer>
     */
    public function forUser(User $user): Builder
    {
        $customers = Customer::query()
            ->where('status', WorkflowTransition::WAITING_BUSINESS_DEVELOPMENT_VERIFICATION->value);

        if (! $this->access->hasAllPopAccess($user)) {
            $customers->whereIn('pop_id', $this->access->getAllowedPopIds($user));
        }

        if (! $user->hasPermission('customer_acquisitions.installation_fee.update')) {
            $categories = PackageCategory::query()
                ->where('installation_fee_approval_role_id', $user->role_id)
                ->pluck('name');

            $customers->whereHas('customerService.internetPackage', function (Builder $query) use ($categories): void {
                $query->whereIn('category', $categories);
            });
        }

        return $customers;
    }
}
