<?php

namespace App\Http\Controllers;

use App\Enums\ScopeType;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Services\EffectiveAccessService;
use App\Services\UserScopeManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
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
        $popTree = $this->buildPopTree();

        return view('users.create', compact('roles', 'popTree'));
    }

    public function store(Request $request, UserScopeManagementService $scopeService)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'role_id' => ['required', 'exists:roles,id'],
            'scope_type' => ['required', Rule::in(['all_pop', 'selected_pop'])],
            'pop_ids' => ['nullable', 'array'],
            'pop_ids.*' => ['exists:pops,id'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required' => 'Nama user wajib diisi.',
            'email.required' => 'Email user wajib diisi.',
            'email.email' => 'Format email user tidak valid.',
            'email.unique' => 'Email user sudah digunakan.',
            'status.required' => 'Status user wajib dipilih.',
            'status.enum' => 'Status user tidak valid. Pilih aktif atau nonaktif.',
            'role_id.required' => 'Role user wajib dipilih.',
            'role_id.exists' => 'Role user yang dipilih tidak valid.',
            'scope_type.required' => 'Scope wilayah data wajib dipilih.',
            'scope_type.in' => 'Tipe scope tidak valid. Pilih Seluruh POP, Cabang POP, atau Distribusi POP.',
            'pop_ids.array' => 'Format POP yang dipilih tidak valid.',
            'pop_ids.*.exists' => 'Salah satu POP yang dipilih tidak ditemukan.',
            'password.required' => 'Password user wajib diisi.',
            'password.min' => 'Password user minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $validator->after(function ($validator) use ($request) {
            if ($request->role_id && $request->scope_type) {
                $role = Role::find($request->role_id);
                if ($role) {
                    $scopeType = $request->scope_type;

                    if ($role->code === 'owner' && $scopeType !== 'all_pop') {
                        $validator->errors()->add('scope_type', 'Role Owner wajib menggunakan scope Seluruh POP.');
                    } elseif ($role->code === 'atasan' && $scopeType !== 'all_pop') {
                        $validator->errors()->add('scope_type', 'Role Atasan wajib menggunakan scope Seluruh POP.');
                    } elseif ($role->code === 'pop_admin' && $scopeType !== 'selected_pop') {
                        $validator->errors()->add('scope_type', 'Role Admin POP wajib menggunakan scope Cabang POP.');
                    }

                    if ($scopeType === 'selected_pop' && empty($request->pop_ids)) {
                        $validator->errors()->add('pop_ids', 'Minimal 1 POP target wajib dipilih untuk scope ini.');
                    }
                }
            }
        });

        $validated = $validator->validate();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'status' => $validated['status'],
            'role_id' => $validated['role_id'],
            'password' => Hash::make($validated['password']),
        ]);

        $scopeService->syncUserRoleScope($user, $validated['scope_type'], $validated['pop_ids'] ?? []);

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'User Management',
            'action' => 'create',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'new_values' => $user->only(['name', 'email', 'phone', 'status', 'role_id']),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return redirect()->route('users.index')->with('success', 'User berhasil ditambahkan.');
    }

    public function edit(User $user)
    {
        $roles = Role::orderBy('name')->get();
        $popTree = $this->buildPopTree();

        return view('users.edit', compact('user', 'roles', 'popTree'));
    }

    public function update(Request $request, User $user, UserScopeManagementService $scopeService)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'role_id' => ['required', 'exists:roles,id'],
            'scope_type' => ['required', Rule::in(['all_pop', 'selected_pop'])],
            'pop_ids' => ['nullable', 'array'],
            'pop_ids.*' => ['exists:pops,id'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required' => 'Nama user wajib diisi.',
            'email.required' => 'Email user wajib diisi.',
            'email.email' => 'Format email user tidak valid.',
            'email.unique' => 'Email user sudah digunakan.',
            'status.required' => 'Status user wajib dipilih.',
            'status.enum' => 'Status user tidak valid. Pilih aktif atau nonaktif.',
            'role_id.required' => 'Role user wajib dipilih.',
            'role_id.exists' => 'Role user yang dipilih tidak valid.',
            'scope_type.required' => 'Scope wilayah data wajib dipilih.',
            'scope_type.in' => 'Tipe scope tidak valid. Pilih Seluruh POP, Cabang POP, atau Distribusi POP.',
            'pop_ids.array' => 'Format POP yang dipilih tidak valid.',
            'pop_ids.*.exists' => 'Salah satu POP yang dipilih tidak ditemukan.',
            'password.min' => 'Password user minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $validator->after(function ($validator) use ($request) {
            if ($request->role_id && $request->scope_type) {
                $role = Role::find($request->role_id);
                if ($role) {
                    $scopeType = $request->scope_type;

                    if ($role->code === 'owner' && $scopeType !== 'all_pop') {
                        $validator->errors()->add('scope_type', 'Role Owner wajib menggunakan scope Seluruh POP.');
                    } elseif ($role->code === 'atasan' && $scopeType !== 'all_pop') {
                        $validator->errors()->add('scope_type', 'Role Atasan wajib menggunakan scope Seluruh POP.');
                    } elseif ($role->code === 'pop_admin' && $scopeType !== 'selected_pop') {
                        $validator->errors()->add('scope_type', 'Role Admin POP wajib menggunakan scope Cabang POP.');
                    }

                    if ($scopeType === 'selected_pop' && empty($request->pop_ids)) {
                        $validator->errors()->add('pop_ids', 'Minimal 1 POP target wajib dipilih untuk scope ini.');
                    }
                }
            }
        });

        $validated = $validator->validate();

        $oldValues = $user->only(['name', 'email', 'phone', 'status', 'role_id']);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'status' => $validated['status'],
            'role_id' => $validated['role_id'],
        ]);

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();
        $scopeService->syncUserRoleScope($user, $validated['scope_type'], $validated['pop_ids'] ?? []);

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'User Management',
            'action' => 'update',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'old_values' => $oldValues,
            'new_values' => $user->only(['name', 'email', 'phone', 'status', 'role_id']),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return redirect()->route('users.index')->with('success', 'User berhasil diperbarui.');
    }

    public function editPops(User $user)
    {
        $popTree = $this->buildPopTree();

        return view('users.edit_pops', compact('user', 'popTree'));
    }

    public function updatePops(Request $request, User $user)
    {
        $validated = $request->validate([
            'pop_ids' => 'nullable|array',
            'pop_ids.*' => 'exists:pops,id',
        ], [
            'pop_ids.array' => 'Format POP yang dipilih tidak valid.',
            'pop_ids.*.exists' => 'Salah satu POP yang dipilih tidak ditemukan.',
        ]);

        $oldPopIds = $user->pops()->pluck('pops.id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $newPopIds = collect($validated['pop_ids'] ?? [])->map(fn ($id) => (int) $id)->sort()->values()->all();

        $user->pops()->sync($validated['pop_ids'] ?? []);

        // Clear access cache
        app(EffectiveAccessService::class)->clearCache($user);

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

    public function previewAccess(Request $request)
    {
        $roleId = $request->input('role_id');
        $scopeType = $request->input('scope_type');
        $popIds = $request->input('pop_ids', []);

        if (! $roleId) {
            return response()->json(['error' => 'Role tidak dipilih'], 400);
        }

        $role = Role::with(['permissions.feature', 'permissions.action'])->find($roleId);
        if (! $role) {
            return response()->json(['error' => 'Role tidak valid'], 400);
        }

        $featuresSummary = [];
        $sensitiveActions = [];

        foreach ($role->permissions as $permission) {
            $featureName = $permission->feature->name ?? 'Global';
            $actionName = $permission->name ?? $permission->action->name ?? '';
            $actionCode = $permission->action->code ?? '';

            if (! isset($featuresSummary[$featureName])) {
                $featuresSummary[$featureName] = [];
            }
            $featuresSummary[$featureName][] = ucfirst($actionName);

            if (in_array($actionCode, ['view_sensitive', 'update_sensitive', 'delete', 'cancel', 'reject', 'void'])) {
                $sensitiveActions[] = "Dapat melakukan $actionName pada data $featureName";
            }
        }

        $formattedFeatures = [];
        if ($role->code === 'owner' || $role->name === 'Owner') {
            $formattedFeatures[] = 'Semua Fitur (Akses Penuh / Full Access)';
            $sensitiveActions[] = 'Dapat melakukan SEMUA tindakan sensitif (Bypass Akses)';
        } else {
            foreach ($featuresSummary as $feature => $actions) {
                $actionsStr = implode(', ', array_unique($actions));
                $formattedFeatures[] = "$feature ($actionsStr)";
            }
            if (empty($formattedFeatures)) {
                $formattedFeatures[] = 'Belum ada hak akses (Permission) yang diatur untuk jabatan ini. Silakan atur di menu Role & Permission.';
            }
        }

        $popNames = [];
        if (in_array($scopeType, ['selected_pop', 'pop_tree']) && ! empty($popIds)) {
            $popNames = Pop::whereIn('id', $popIds)->pluck('name')->toArray();
        }

        return response()->json([
            'role_name' => $role->name,
            'is_system' => $role->is_system,
            'scope_type' => $scopeType,
            'scope_label' => ScopeType::tryFrom($scopeType)?->label() ?? $scopeType,
            'pops' => $popNames,
            'features' => $formattedFeatures,
            'sensitive_actions' => array_unique($sensitiveActions),
        ]);
    }

    /**
     * Build a hierarchical POP tree for the view.
     * Root POPs (parent_id = null) with up to 2 levels of children eager-loaded.
     */
    private function buildPopTree()
    {
        return Pop::with(['children' => function ($q) {
            $q->orderBy('name')->with(['children' => function ($q2) {
                $q2->orderBy('name');
            }]);
        }])
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();
    }
}
