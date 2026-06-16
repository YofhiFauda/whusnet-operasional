<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Pop;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $roleId = $request->query('role_id', '');
        $status = trim((string) $request->query('status', ''));
        $popId = $request->query('pop_id', '');

        $query = User::query()->with(['role', 'pops'])->withCount('pops');

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($roleId !== '') {
            $query->where('role_id', $roleId);
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($popId !== '') {
            $query->whereHas('pops', function ($builder) use ($popId): void {
                $builder->whereKey($popId);
            });
        }

        $users = $query->orderBy('name')->paginate(15)->withQueryString();

        $roles = Role::orderBy('name')->get();
        $pops = Pop::orderBy('name')->get();

        $summaryQuery = User::query();
        if ($search !== '') {
            $summaryQuery->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }
        if ($roleId !== '') {
            $summaryQuery->where('role_id', $roleId);
        }
        if ($status !== '') {
            $summaryQuery->where('status', $status);
        }
        if ($popId !== '') {
            $summaryQuery->whereHas('pops', function ($builder) use ($popId): void {
                $builder->whereKey($popId);
            });
        }

        $summaryUsers = $summaryQuery->with(['role', 'pops'])->get();
        $totalUsers = $summaryUsers->count();
        $fullAccessUsers = $summaryUsers->filter(fn (User $user) => $user->hasFullAccess())->count();
        $withPopUsers = $summaryUsers->filter(fn (User $user) => $user->pops->isNotEmpty())->count();
        $withoutPopUsers = $summaryUsers->filter(fn (User $user) => $user->pops->isEmpty())->count();

        return view('users.index', compact(
            'users',
            'roles',
            'pops',
            'search',
            'roleId',
            'status',
            'popId',
            'totalUsers',
            'fullAccessUsers',
            'withPopUsers',
            'withoutPopUsers'
        ));
    }

    public function create()
    {
        $roles = Role::orderBy('name')->get();
        $pops = Pop::orderBy('name')->get();

        return view('users.create', compact('roles', 'pops'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'role_id' => ['required', 'exists:roles,id'],
            'pop_ids' => ['nullable', 'array'],
            'pop_ids.*' => ['exists:pops,id'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required' => 'Nama user wajib diisi.',
            'email.required' => 'Email user wajib diisi.',
            'email.email' => 'Format email user tidak valid.',
            'email.unique' => 'Email user sudah digunakan.',
            'status.required' => 'Status user wajib dipilih.',
            'status.in' => 'Status user tidak valid. Pilih aktif atau nonaktif.',
            'role_id.required' => 'Role user wajib dipilih.',
            'role_id.exists' => 'Role user yang dipilih tidak valid.',
            'pop_ids.array' => 'Format POP yang dipilih tidak valid.',
            'pop_ids.*.exists' => 'Salah satu POP yang dipilih tidak ditemukan.',
            'password.required' => 'Password user wajib diisi.',
            'password.min' => 'Password user minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'status' => $validated['status'],
            'role_id' => $validated['role_id'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->pops()->sync($validated['pop_ids'] ?? []);

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'User Management',
            'action' => 'create',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'new_values' => array_merge(
                $user->only(['name', 'email', 'phone', 'status', 'role_id']),
                ['pop_ids' => $this->popIdValues($user)]
            ),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return redirect()->route('users.index')->with('success', 'User berhasil ditambahkan.');
    }

    public function edit(User $user)
    {
        $roles = Role::orderBy('name')->get();
        $pops = Pop::orderBy('name')->get();

        return view('users.edit', compact('user', 'roles', 'pops'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'role_id' => ['required', 'exists:roles,id'],
            'pop_ids' => ['nullable', 'array'],
            'pop_ids.*' => ['exists:pops,id'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required' => 'Nama user wajib diisi.',
            'email.required' => 'Email user wajib diisi.',
            'email.email' => 'Format email user tidak valid.',
            'email.unique' => 'Email user sudah digunakan.',
            'status.required' => 'Status user wajib dipilih.',
            'status.in' => 'Status user tidak valid. Pilih aktif atau nonaktif.',
            'role_id.required' => 'Role user wajib dipilih.',
            'role_id.exists' => 'Role user yang dipilih tidak valid.',
            'pop_ids.array' => 'Format POP yang dipilih tidak valid.',
            'pop_ids.*.exists' => 'Salah satu POP yang dipilih tidak ditemukan.',
            'password.min' => 'Password user minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $oldValues = $user->only(['name', 'email', 'phone', 'status', 'role_id']);
        $oldValues['pop_ids'] = $user->pops()->pluck('pops.id')->map(fn ($id) => (int) $id)->values()->all();

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'status' => $validated['status'],
            'role_id' => $validated['role_id'],
        ]);

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();
        $user->pops()->sync($validated['pop_ids'] ?? []);

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'User Management',
            'action' => 'update',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'old_values' => $oldValues,
            'new_values' => array_merge(
                $user->only(['name', 'email', 'phone', 'status', 'role_id']),
                ['pop_ids' => $this->popIdValues($user)]
            ),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return redirect()->route('users.index')->with('success', 'User berhasil diperbarui.');
    }

    public function editPops(User $user)
    {
        $pops = Pop::orderBy('name')->get();
        return view('users.edit_pops', compact('user', 'pops'));
    }

    public function updatePops(Request $request, User $user)
    {
        $validated = $request->validate([
            'pop_ids' => 'nullable|array',
            'pop_ids.*' => 'exists:pops,id'
        ], [
            'pop_ids.array' => 'Format POP yang dipilih tidak valid.',
            'pop_ids.*.exists' => 'Salah satu POP yang dipilih tidak ditemukan.',
        ]);

        $oldPopIds = $user->pops()->pluck('pops.id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $newPopIds = collect($validated['pop_ids'] ?? [])->map(fn ($id) => (int) $id)->sort()->values()->all();

        $user->pops()->sync($validated['pop_ids'] ?? []);

        if ($oldPopIds !== $newPopIds) {
            AuditLog::create([
                'user_id' => auth()->id(),
                'module' => 'User Management',
                'action' => 'assign_pop',
                'auditable_type' => User::class,
                'auditable_id' => $user->id,
                'old_values' => ['pop_ids' => $oldPopIds],
                'new_values' => ['pop_ids' => $newPopIds],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);
        }

        return redirect()->route('users.index')->with('success', 'POP assignment updated successfully.');
    }

    /**
     * @return array<int, int>
     */
    private function popIdValues(User $user): array
    {
        return $user->pops()
            ->pluck('pops.id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
