<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Login - Whusnet Billing & Operasional</title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Styles / Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="min-h-full flex items-center justify-center p-4 bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 text-slate-100 selection:bg-sky-500 selection:text-white relative overflow-hidden">
    
    <!-- Background Decorative Glowing Blobs -->
    <div class="absolute -top-40 -right-40 w-96 h-96 bg-sky-500/10 rounded-full blur-[100px] pointer-events-none"></div>
    <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-indigo-500/10 rounded-full blur-[100px] pointer-events-none"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-sky-600/5 rounded-full blur-[120px] pointer-events-none"></div>

    <!-- Login Container -->
    <div class="w-full max-w-md relative z-10">
        <!-- Brand / Logo -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center h-16 w-16 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-xl shadow-sky-500/5 mb-4">
                <svg class="h-10 w-10 text-sky-400 drop-shadow-[0_0_8px_rgba(56,189,248,0.3)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-white">WHUS<span class="text-sky-400">NET</span></h1>
            <p class="text-slate-400 text-sm mt-1">Sistem Billing & Operasional ISP Internal</p>
        </div>

        <!-- Glassmorphic Form Card -->
        <div class="bg-slate-900/50 backdrop-blur-xl border border-slate-800/80 rounded-2xl shadow-2xl p-8 hover:border-slate-700/50 transition-all duration-300">
            
            <!-- Global Alert Errors -->
            @if ($errors->any())
                <div class="mb-6 p-4 rounded-lg bg-red-950/40 border border-red-900/50 text-red-300 text-sm flex items-start gap-3">
                    <svg class="h-5 w-5 text-red-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div>
                        <span class="font-semibold">Gagal masuk:</span>
                        <ul class="list-disc list-inside mt-1 space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <form action="{{ route('login') }}" method="POST" class="space-y-6">
                @csrf

                <!-- Email Input -->
                <div>
                    <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Alamat Email</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.206" />
                            </svg>
                        </span>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                            class="w-full pl-11 pr-4 py-3 rounded-xl bg-slate-950/60 border border-slate-800/80 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500/50 focus:border-sky-500 hover:border-slate-700/60 transition-all @error('email') border-red-500 focus:ring-red-500/50 focus:border-red-500 @enderror"
                            placeholder="nama@email.com">
                    </div>
                </div>

                <!-- Password Input -->
                <div>
                    <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Kata Sandi</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </span>
                        <input type="password" name="password" id="password" required
                            class="w-full pl-11 pr-4 py-3 rounded-xl bg-slate-950/60 border border-slate-800/80 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500/50 focus:border-sky-500 hover:border-slate-700/60 transition-all @error('password') border-red-500 focus:ring-red-500/50 focus:border-red-500 @enderror"
                            placeholder="••••••••">
                    </div>
                </div>

                <!-- Remember Me Checkbox -->
                <div class="flex items-center justify-between">
                    <label class="relative flex items-center gap-3 cursor-pointer group">
                        <input type="checkbox" name="remember" id="remember" class="sr-only peer">
                        <div class="h-5 w-5 rounded-md bg-slate-950/60 border border-slate-800/80 flex items-center justify-center text-white peer-checked:bg-sky-600 peer-checked:border-sky-500 peer-focus:ring-2 peer-focus:ring-sky-500/30 transition-all">
                            <!-- Checkmark icon -->
                            <svg class="h-3.5 w-3.5 opacity-0 peer-checked:group-hover:opacity-100 peer-checked:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <span class="text-sm text-slate-400 group-hover:text-slate-300 transition-colors select-none">Ingat Saya</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="w-full py-3.5 bg-sky-600 hover:bg-sky-500 text-white font-semibold rounded-xl shadow-lg shadow-sky-600/10 hover:shadow-sky-500/20 active:scale-[0.98] transition-all duration-150 flex items-center justify-center gap-2 cursor-pointer">
                    <span>Masuk ke Sistem</span>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </button>
            </form>
        </div>

        <!-- Footer Text -->
        <p class="text-center text-slate-500 text-xs mt-8">
            &copy; {{ date('Y') }} WHUSNET. Hak Cipta Dilindungi Undang-Undang.
        </p>
    </div>

</body>
</html>
