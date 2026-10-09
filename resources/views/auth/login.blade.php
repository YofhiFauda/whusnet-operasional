<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Login - Whusnet Billing & Operasional</title>

    <!-- Google Fonts: Inter & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Styles / Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full flex items-center justify-center p-4 bg-surface-muted relative overflow-hidden font-sans antialiased text-text-main selection:bg-primary-light selection:text-primary-dark">
    
    <!-- Login Container -->
    <div class="w-full max-w-md relative z-10">
        <!-- Brand / Logo -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center h-16 w-16 rounded-lg bg-primary/10 border border-primary/20 mb-4 shadow-sm">
                <svg class="h-8 w-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-text-main">WHUS<span class="text-primary">NET</span></h1>
            <p class="text-text-muted text-sm mt-1">Sistem Billing & Operasional ISP Internal</p>
        </div>

        <!-- Form Card -->
        <x-ui.card class="p-8 shadow-large">
            
            <!-- Global Alert Errors handled by Toast -->

            <form action="{{ route('login') }}" method="POST" class="space-y-6">
                @csrf

                <!-- Email Input -->
                <div class="space-y-1.5">
                    <label for="email" class="block text-sm font-medium text-text-main">Alamat Email</label>
                    <x-ui.input 
                        name="email" 
                        id="email" 
                        type="email" 
                        value="{{ old('email') }}" 
                        required 
                        autofocus
                        placeholder="nama@email.com"
                        :error="$errors->has('email')"
                        class="h-10"
                    />
                    @error('email')
                        <p class="text-xs text-error mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password Input -->
                <div class="space-y-1.5" x-data="{ showPassword: false }">
                    <label for="password" class="block text-sm font-medium text-text-main">Kata Sandi</label>
                    <div class="relative">
                        <x-ui.input 
                            name="password" 
                            id="password" 
                            x-bind:type="showPassword ? 'text' : 'password'" 
                            required
                            placeholder="••••••••"
                            :error="$errors->has('password')"
                            class="h-10 pr-10"
                        />
                        <button type="button"
                                @click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer focus:outline-none"
                                tabindex="-1"
                                title="Tampilkan / sembunyikan kata sandi">
                            <svg x-show="!showPassword" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <svg x-show="showPassword" x-cloak class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                            </svg>
                        </button>
                    </div>
                    @error('password')
                        <p class="text-xs text-error mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Remember Me Checkbox -->
                <div class="flex items-center justify-between pt-1">
                    <x-ui.checkbox 
                        name="remember" 
                        id="remember" 
                        label="Ingat Saya"
                    />
                </div>

                <!-- Submit Button -->
                <x-ui.button type="submit" variant="primary" class="w-full justify-center h-10 text-base">
                    Masuk ke Sistem
                </x-ui.button>
            </form>

            @if(app()->environment('local'))
            <!-- Development Login Helper -->
            <div class="mt-8 pt-6 border-t border-border-main">
                <p class="text-xs font-semibold text-text-muted mb-3 text-center uppercase tracking-wider">Development Login Helper</p>
                <div class="grid grid-cols-2 gap-2 max-h-48 overflow-y-auto pr-1 custom-scrollbar">
                    @foreach(\App\Models\User::with('role')->get() as $devUser)
                    <x-ui.button 
                        type="button" 
                        variant="outline" 
                        class="h-auto py-2 px-2 flex flex-col items-center justify-center gap-0.5 hover:border-primary hover:text-primary transition-colors"
                        onclick="fillLogin('{{ $devUser->email }}', 'password')"
                        title="Login sebagai {{ $devUser->name }}"
                    >
                        <span class="text-xs font-semibold">{{ $devUser->role->name ?? 'User Tanpa Role' }}</span>
                        <span class="text-[10px] opacity-70 truncate w-full text-center">{{ $devUser->email }}</span>
                    </x-ui.button>
                    @endforeach
                </div>
            </div>
            
            <script>
                function fillLogin(email, password) {
                    document.getElementById('email').value = email;
                    document.getElementById('password').value = password;
                }
            </script>
            @endif
        </x-ui.card>

        <!-- Footer Text -->
        <p class="text-center text-text-muted text-xs mt-8">
            &copy; {{ date('Y') }} WHUSNET. Hak Cipta Dilindungi Undang-Undang.
        </p>
    </div>

    <x-toast />
</body>
</html>
