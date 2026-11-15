<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\WorkflowTransitionPermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        // Pelaksana task lapangan = SEMUA Role::TECHNICIAN_CODES, bukan cuma
        // 'teknisi'. PIC gudang cabang ('pic_gudang') itu teknisi yang
        // merangkap (dropdown penugasan FOP sudah pakai TECHNICIAN_CODES),
        // jadi dia DITUGASKAN task seperti teknisi biasa. Dulu daftar ini
        // ditulis ['owner','admin','teknisi'] ⇒ TaskPolicy::canTransition()
        // menolak pic_gudang ⇒ tombol Mulai (C-REQ/O-REQ/INFR) & tombol
        // Laporan SEMUA tipe task hilang di Tasks Saya / Detail Task
        // (ADHOC-297 bug #2, laporan user 2026-11-13). Role teknisi baru
        // cukup ditambah di Role::TECHNICIAN_CODES — jangan tulis ulang di sini.
        $executorRoles = ['owner', 'admin', ...Role::TECHNICIAN_CODES];

        $transitions = [
            [
                'from_status' => 'pending',
                'to_status' => 'terjadwal',
                'permission_name' => 'task.manage',
                'roles' => ['owner', 'admin', 'helpdesk', 'fop'],
            ],
            [
                'from_status' => 'pending',
                'to_status' => 'dibatalkan',
                'permission_name' => 'task.cancel',
                'roles' => ['owner', 'admin', 'fop'],
            ],
            [
                'from_status' => 'pending',
                'to_status' => 'rejected',
                'permission_name' => 'task.reject',
                'roles' => ['owner', 'admin', 'fop'],
            ],
            [
                'from_status' => 'pending',
                'to_status' => 'pending',
                'permission_name' => 'task.execute',
                'roles' => $executorRoles,
            ],
            [
                'from_status' => 'terjadwal',
                'to_status' => 'in_progress',
                'permission_name' => 'task.execute',
                'roles' => $executorRoles,
            ],
            [
                'from_status' => 'in_progress',
                'to_status' => 'selesai',
                'permission_name' => 'task.execute',
                'roles' => $executorRoles,
            ],
            [
                'from_status' => 'selesai',
                'to_status' => 'approved',
                'permission_name' => 'task.approve',
                'roles' => ['owner', 'admin', 'fop'],
            ],
            [
                'from_status' => 'selesai',
                'to_status' => 'rejected',
                'permission_name' => 'task.reject',
                'roles' => ['owner', 'admin', 'fop'],
            ],
            [
                'from_status' => 'terjadwal',
                'to_status' => 'pending',
                'permission_name' => 'task.execute',
                'roles' => $executorRoles,
            ],
            [
                'from_status' => 'in_progress',
                'to_status' => 'pending',
                'permission_name' => 'task.execute',
                'roles' => $executorRoles,
            ],
            [
                'from_status' => 'terjadwal',
                'to_status' => 'dibatalkan',
                'permission_name' => 'task.cancel',
                'roles' => ['owner', 'admin', 'fop'],
            ],
            [
                'from_status' => 'in_progress',
                'to_status' => 'dibatalkan',
                'permission_name' => 'task.cancel',
                'roles' => ['owner', 'admin', 'fop'],
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
