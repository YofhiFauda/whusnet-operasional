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
            
            <!-- Global Alert Errors -->
            @if ($errors->any())
                <x-ui.alert-banner type="error" title="Gagal masuk:" class="mb-6">
                    <ul class="list-disc list-inside mt-1 space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-ui.alert-banner>
            @endif

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
                <div class="space-y-1.5">
                    <label for="password" class="block text-sm font-medium text-text-main">Kata Sandi</label>
                    <x-ui.input 
                        name="password" 
                        id="password" 
                        type="password" 
                        required
                        placeholder="••••••••"
                        :error="$errors->has('password')"
                        class="h-10"
                    />
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
        </x-ui.card>

        <!-- Footer Text -->
        <p class="text-center text-text-muted text-xs mt-8">
            &copy; {{ date('Y') }} WHUSNET. Hak Cipta Dilindungi Undang-Undang.
        </p>
    </div>

</body>
</html>
