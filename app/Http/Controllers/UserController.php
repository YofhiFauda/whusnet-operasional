<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Pop;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with(['role', 'pops'])->orderBy('name')->paginate(15);
        return view('users.index', compact('users'));
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
}
