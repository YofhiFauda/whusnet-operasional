<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WorkflowTransitionPermission;
use App\Models\Role;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class WorkflowTransitionPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        WorkflowTransitionPermission::truncate();
        DB::table('role_workflow_transition')->truncate();
        Schema::enableForeignKeyConstraints();

        $transitions = [
            [
                'from_status' => 'pending',
                'to_status' => 'terjadwal',
                'permission_name' => 'task.manage',
                'roles' => ['owner', 'admin', 'helpdesk', 'fop']
            ],
            [
                'from_status' => 'pending',
                'to_status' => 'dibatalkan',
                'permission_name' => 'task.cancel',
                'roles' => ['owner', 'admin', 'fop']
            ],
            [
                'from_status' => 'pending',
                'to_status' => 'rejected',
                'permission_name' => 'task.reject',
                'roles' => ['owner', 'admin', 'fop']
            ],
            [
                'from_status' => 'pending',
                'to_status' => 'pending',
                'permission_name' => 'task.execute',
                'roles' => ['owner', 'admin', 'teknisi']
            ],
            [
                'from_status' => 'terjadwal',
                'to_status' => 'in_progress',
                'permission_name' => 'task.execute',
                'roles' => ['owner', 'admin', 'teknisi']
            ],
            [
                'from_status' => 'in_progress',
                'to_status' => 'selesai',
                'permission_name' => 'task.execute',
                'roles' => ['owner', 'admin', 'teknisi']
            ],
            [
                'from_status' => 'selesai',
                'to_status' => 'approved',
                'permission_name' => 'task.approve',
                'roles' => ['owner', 'admin', 'fop']
            ],
            [
                'from_status' => 'selesai',
                'to_status' => 'rejected',
                'permission_name' => 'task.reject',
                'roles' => ['owner', 'admin', 'fop']
            ],
            [
                'from_status' => 'terjadwal',
                'to_status' => 'pending',
                'permission_name' => 'task.execute',
                'roles' => ['owner', 'admin', 'teknisi']
            ],
            [
                'from_status' => 'in_progress',
                'to_status' => 'pending',
                'permission_name' => 'task.execute',
                'roles' => ['owner', 'admin', 'teknisi']
            ],
            [
                'from_status' => 'terjadwal',
                'to_status' => 'dibatalkan',
                'permission_name' => 'task.cancel',
                'roles' => ['owner', 'admin', 'fop']
            ],
            [
                'from_status' => 'in_progress',
                'to_status' => 'dibatalkan',
                'permission_name' => 'task.cancel',
                'roles' => ['owner', 'admin', 'fop']
            ],
        ];

        foreach ($transitions as $trans) {
            $permission = WorkflowTransitionPermission::create([
                'from_status' => $trans['from_status'],
                'to_status' => $trans['to_status'],
                'permission_name' => $trans['permission_name'],
            ]);

            $roleIds = Role::whereIn('code', $trans['roles'])->pluck('id')->toArray();
            $permission->roles()->sync($roleIds);
        }
    }
}
