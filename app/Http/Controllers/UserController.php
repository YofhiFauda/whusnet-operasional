<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Pop;
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

        $user->pops()->sync($validated['pop_ids'] ?? []);

        return redirect()->route('users.index')->with('success', 'POP assignment updated successfully.');
    }
}
