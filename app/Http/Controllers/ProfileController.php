<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Halaman profil milik user yang sedang login — bukan `users.edit`
     * (itu buat admin ubah data user lain lewat matrix role). Di sini user
     * cuma boleh lihat data diri + ganti password sendiri, tanpa gerbang
     * permission karena aksinya selalu ke akun sendiri (auth()->user()).
     */
    public function show(): View
    {
        $user = auth()->user()->load(['role', 'pops', 'roleScopes.targets.pop']);

        $recentActivities = AuditLog::where('user_id', $user->id)
            ->latest('id')
            ->take(5)
            ->get();

        return view('profile.show', [
            'user' => $user,
            'recentActivities' => $recentActivities,
        ]);
    }

    /**
     * Ganti password sendiri. Wajib isi password lama supaya sesi yang lupa
     * logout di komputer bersama tidak bisa dipakai orang lain buat ambil
     * alih akun cuma dengan submit form ini.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', Password::min(8)->mixedCase()->numbers()->symbols(), 'confirmed'],
        ], [
            'current_password.required' => 'Password lama wajib diisi.',
            'password.required' => 'Password baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
            // Kunci "password.mixed" dst — bukan `validation.php` biasa (lihat
            // Illuminate\Validation\Rules\Password::passes()). Wajib dioverride
            // manual di sini karena lang/id/validation.php sudah memakai key
            // top-level "password" buat string lain (pesan login gagal),
            // jadi nested lookup "validation.password.mixed" gak pernah ke-resolve.
            'password.min' => 'Password baru minimal 8 karakter.',
            'password.mixed' => 'Password baru wajib kombinasi huruf besar dan huruf kecil.',
            'password.numbers' => 'Password baru wajib mengandung minimal 1 angka.',
            'password.symbols' => 'Password baru wajib mengandung minimal 1 simbol (!@#$% dst).',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Password lama tidak sesuai.'])->withInput();
        }

        $user->forceFill(['password' => Hash::make($validated['password'])])->save();

        AuditLog::create([
            'user_id' => $user->id,
            'module' => 'User Management',
            'action' => 'update',
            'auditable_type' => $user::class,
            'auditable_id' => $user->id,
            'new_values' => ['password' => '(diubah sendiri lewat halaman Profil)'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return redirect()->route('profile.show')->with('success', 'Password berhasil diubah.');
    }
}
