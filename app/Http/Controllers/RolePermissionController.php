<?php

namespace App\Http\Controllers;

use App\Models\Feature;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RolePermissionController extends Controller
{
    /**
     * Helper: ambil user yang sedang login (sudah dijamin ada oleh middleware auth).
     */
    private function currentUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    /**
     * Daftar semua role beserta konteks akses user yang sedang login.
     */
    public function index(): View
    {
        $roles = Role::withCount('permissions', 'users')->orderBy('id')->get();
        $currentUser = $this->currentUser();

        return view('roles.index', compact('roles', 'currentUser'));
    }

    /**
     * Tampilkan matrix permission untuk role tertentu.
     * Hanya bisa diakses jika user berhak mengelola role tersebut.
     */
    public function matrix(Role $role): View|RedirectResponse
    {
        $currentUser = $this->currentUser();

        // Guard: cek apakah user berhak mengelola role ini
        if (! $role->canBeManagedBy($currentUser)) {
            abort(403, 'Anda tidak memiliki wewenang untuk mengatur permission role ini.');
        }

        // Owner memiliki akses penuh — tidak perlu matrix
        if ($role->isOwner()) {
            return redirect()->route('roles.index')
                ->with('info', 'Role Owner memiliki akses penuh ke seluruh fitur sistem. Permission-nya tidak dapat diubah.');
        }

        $features = Feature::getTree();
        $permissions = Permission::with('action')->get()->groupBy('feature_id');
        $rolePermissions = $role->permissions->pluck('id')->toArray();

        return view('roles.matrix', compact('role', 'features', 'permissions', 'rolePermissions', 'currentUser'));
    }

    /**
     * Simpan perubahan permission untuk role (dari halaman matrix).
     */
    public function update(Request $request, Role $role, RoleManagementService $roleManagementService): RedirectResponse
    {
        $currentUser = $this->currentUser();

        // Guard 1: cek apakah user berhak mengelola role ini
        if (! $role->canBeManagedBy($currentUser)) {
            abort(403, 'Anda tidak memiliki wewenang untuk mengubah permission role ini.');
        }

        // Guard 2: role Owner tidak bisa diubah permission-nya — ia punya akses penuh
        if ($role->isOwner()) {
            return back()->with('error', 'Role Owner memiliki akses penuh. Permission-nya tidak dapat diubah.');
        }

        $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $roleManagementService->syncPermissions($role, $request->input('permissions', []));

        return redirect()->route('roles.index')
            ->with('success', "Permission role \"{$role->name}\" berhasil diperbarui.");
    }

    /**
     * Buat role baru. Hanya Owner yang dapat menambah role baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $currentUser = $this->currentUser();

        // Hanya Owner yang boleh membuat role baru
        if (! Role::canBeCreatedBy($currentUser)) {
            abort(403, 'Hanya Owner yang dapat membuat role baru.');
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'code' => ['required', 'string', 'max:50', 'unique:roles,code', 'regex:/^[a-z0-9_]+$/'],
            'description' => 'nullable|string|max:1000',
        ], [
            'code.regex' => 'Kode role hanya boleh menggunakan huruf kecil, angka, dan underscore.',
        ]);

        Role::create([
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
            'is_system' => false,
        ]);

        return redirect()->route('roles.index')
            ->with('success', "Role \"{$request->name}\" berhasil ditambahkan.");
    }

    /**
     * Update data role (nama, kode, deskripsi).
     */
    public function updateRole(Request $request, Role $role): RedirectResponse
    {
        $currentUser = $this->currentUser();

        // Guard: cek apakah user berhak mengelola role ini
        if (! $role->canBeManagedBy($currentUser)) {
            abort(403, 'Anda tidak memiliki wewenang untuk mengubah role ini.');
        }

        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,'.$role->id,
            'code' => ['required', 'string', 'max:50', 'unique:roles,code,'.$role->id, 'regex:/^[a-z0-9_]+$/'],
            'description' => 'nullable|string|max:1000',
        ], [
            'code.regex' => 'Kode role hanya boleh menggunakan huruf kecil, angka, dan underscore.',
        ]);

        // Kode role sistem (is_system = true) tidak boleh diubah oleh siapapun
        if ($role->is_system && $request->code !== $role->code) {
            return back()->with('error', 'Kode role sistem tidak boleh diubah.');
        }

        $role->update([
            'name' => $request->name,
            'code' => $role->is_system ? $role->code : $request->code,
            'description' => $request->description,
        ]);

        return redirect()->route('roles.index')
            ->with('success', "Role \"{$role->name}\" berhasil diperbarui.");
    }

    /**
     * Hapus role.
     */
    public function destroy(Role $role): RedirectResponse
    {
        $currentUser = $this->currentUser();

        // Guard 1: cek apakah user berhak mengelola role ini
        if (! $role->canBeManagedBy($currentUser)) {
            abort(403, 'Anda tidak memiliki wewenang untuk menghapus role ini.');
        }

        // Guard 2: role yang dilindungi sistem tidak bisa dihapus siapapun
        if ($role->isProtected()) {
            return back()->with('error', "Role \"{$role->name}\" adalah role inti sistem dan tidak dapat dihapus.");
        }

        // Guard 3: tidak bisa hapus role yang masih dipakai user
        $userCount = $role->users()->count();
        if ($userCount > 0) {
            return back()->with('error', "Role \"{$role->name}\" tidak dapat dihapus karena masih digunakan oleh {$userCount} user.");
        }

        $roleName = $role->name;
        $role->delete();

        return redirect()->route('roles.index')
            ->with('success', "Role \"{$roleName}\" berhasil dihapus.");
    }
}
