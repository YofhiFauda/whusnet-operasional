<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Show the login form.
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Handle the login request.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::user();

            // Check if user status is active
            if ($user->status !== UserStatus::ACTIVE) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                throw ValidationException::withMessages([
                    'email' => 'Akun Anda tidak aktif. Silakan hubungi administrator.',
                ]);
            }

            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        throw ValidationException::withMessages([
            'email' => __('auth.failed'),
        ]);
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request)
    {
        // Lepas subscription push browser ini dari akun yang keluar (review
        // ADHOC-297 #9). PushManager per device, bukan per akun: tanpa ini
        // push milik user sebelumnya tetap tampil di perangkat bersama.
        // Endpoint dikirim resources/js/webpush.js lewat field tersembunyi
        // #logout-form; hanya subscription MILIK user ini yang bisa terhapus.
        $endpoint = $request->input('push_endpoint');
        if ($request->user() && is_string($endpoint) && $endpoint !== '' && strlen($endpoint) <= 1024) {
            $request->user()->deletePushSubscription($endpoint);
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
