<?php

namespace Tests\Concerns;

use App\Enums\DeviceRetrievalOutcome;
use App\Enums\DeviceRetrievalReviewStatus;
use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\District;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskDeviceRetrieval;
use App\Models\TaskTeam;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Models\Village;
use Database\Seeders\ActionSeeder;
use Database\Seeders\DeviceRetrievalReviewFeatureSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaskFeatureSeeder;
use Database\Seeders\WorkflowTransitionPermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Data bantu Review Penarikan Alat (docs/plan/warehouse/
 * analisa-review-penarikan-deac.md): dua Cabang (A dalam scope FOP/CS/BD,
 * B di luar), pelanggan putus langganan di A lengkap POP+Desa, dan helper
 * membuat N percobaan gagal langsung di DB (tanpa lewat form teknisi —
 * form sendiri diuji di test khusus).
 */
trait BuildsDeviceRetrievalReviewScenario
{
    protected Pop $popA;

    protected Pop $popB;

    protected Village $village;

    protected User $fop;

    protected User $cs;

    protected User $bd;

    protected User $teknisi;

    protected User $owner;

    protected Customer $customer;

    protected function setUpReviewScenario(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(TaskFeatureSeeder::class);
        $this->seed(DeviceRetrievalReviewFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(WorkflowTransitionPermissionSeeder::class);

        foreach (Permission::all() as $permission) {
            if ($permission->code) {
                Gate::define($permission->code, fn ($user) => $user->hasPermission($permission->code));
            }
        }

        $city = City::firstOrCreate(['name' => 'Ponorogo']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Review Test']);
        $this->village = Village::create(['district_id' => $district->id, 'name' => 'Desa Review', 'postal_code' => '63491']);

        $this->popA = Pop::create(['code' => 'RV-A', 'pop_code' => 'RVA', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Review A', 'type' => 'cabang', 'status' => 'active']);
        $this->popB = Pop::create(['code' => 'RV-B', 'pop_code' => 'RVB', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Review B', 'type' => 'cabang', 'status' => 'active']);

        $this->fop = $this->makeScopedUser('fop', $this->popA);
        $this->cs = $this->makeScopedUser('customer_service', $this->popA);
        $this->bd = $this->makeScopedUser('business_development', $this->popA);
        $this->teknisi = $this->makeScopedUser('teknisi', $this->popA);

        $ownerRole = Role::where('code', 'owner')->firstOrFail();
        $this->owner = User::factory()->create(['role_id' => $ownerRole->id]);
        $this->owner->roleScopes()->create(['role_id' => $ownerRole->id, 'scope_type' => ScopeType::ALL_POP->value]);

        $this->customer = $this->makeTerminatedCustomer($this->popA);
    }

    protected function makeScopedUser(string $roleCode, Pop $pop): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id]);
        $scope = UserRoleScope::create(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => ScopeType::SELECTED_POP]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pop->id]);
        $user->pops()->attach($pop->id);

        return $user;
    }

    protected function makeTerminatedCustomer(Pop $pop, ?Carbon $terminatedAt = null): Customer
    {
        $customer = Customer::factory()->create([
            'pop_id' => $pop->id,
            'village_id' => $this->village->id,
            'status' => 'terminated',
            'terminated_at' => $terminatedAt ?? now()->subDays(60),
        ]);
        CustomerDevice::create(['customer_id' => $customer->id, 'device_type' => 'ONT', 'brand' => 'ZTE', 'model' => 'F609']);

        return $customer;
    }

    /**
     * Satu percobaan gagal = task DEAC selesai + laporan gagal ber-review_status.
     */
    protected function makeAttempt(
        Customer $customer,
        DeviceRetrievalReviewStatus $status = DeviceRetrievalReviewStatus::MENUNGGU,
        ?Carbon $createdAt = null,
        ?string $photo = null,
    ): TaskDeviceRetrieval {
        $task = Task::create([
            'task_number' => 'TASK-RV-'.random_int(100000, 999999),
            'task_type' => TaskType::AMBIL_MODEM->value,
            'title' => 'Ambil Modem '.$customer->id,
            'pop_id' => $customer->pop_id,
            'customer_id' => $customer->id,
            'status' => TaskStatus::SELESAI->value,
            'created_by' => $this->owner->id,
            'updated_by' => $this->owner->id,
        ]);
        TaskTeam::create(['task_id' => $task->id, 'user_id' => $this->teknisi->id, 'role_in_task' => 'lead']);

        $photo ??= 'device-retrieval/lokasi-'.$task->id.'.jpg';
        Storage::disk('local')->put($photo, 'jpg');

        $retrieval = TaskDeviceRetrieval::create([
            'task_id' => $task->id,
            'outcome' => DeviceRetrievalOutcome::TIDAK_DITEMUKAN,
            'notes' => 'Rumah kosong percobaan '.$task->id,
            'condition_photo' => $photo,
            'review_status' => $status,
            'reviewed_by' => $status === DeviceRetrievalReviewStatus::MENUNGGU ? null : $this->fop->id,
            'reviewed_at' => $status === DeviceRetrievalReviewStatus::MENUNGGU ? null : now(),
        ]);

        if ($createdAt) {
            $retrieval->forceFill(['created_at' => $createdAt])->saveQuietly();
        }

        return $retrieval;
    }

    /**
     * N percobaan: N-1 pertama ditolak, terakhir menunggu review.
     */
    protected function makeAttempts(Customer $customer, int $count): TaskDeviceRetrieval
    {
        $last = null;

        for ($i = 1; $i <= $count; $i++) {
            $last = $this->makeAttempt(
                $customer,
                $i === $count ? DeviceRetrievalReviewStatus::MENUNGGU : DeviceRetrievalReviewStatus::DITOLAK,
                now()->subDays(40 - $i),
            );
        }

        return $last;
    }
}
