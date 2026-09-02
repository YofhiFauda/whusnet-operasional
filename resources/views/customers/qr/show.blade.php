@extends('layouts.app')

@section('title', 'QR Pelanggan: ' . $customer->full_name . ' — Whusnet Operasional')
@section('page_title', 'QR Pelanggan')

@section('content')
<div x-data="qrPinPage()">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-slate-50 tracking-tight">{{ $customer->full_name }}</h1>
            <p class="text-sm text-text-secondary mt-0.5">{{ $customer->display_id }} · {{ $customer->pop?->name ?? 'Tanpa POP' }}</p>
        </div>
        <a href="{{ route('customers.show', $customer) }}" class="text-sm font-medium text-sky-600 hover:text-sky-700">
            &larr; Kembali ke Detail Pelanggan
        </a>
    </div>

    @if (session('success'))
        <div class="mb-6 p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-sm text-emerald-700 dark:text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 p-3 rounded-lg bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 text-sm text-rose-700 dark:text-rose-300">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-surface border border-border rounded-xl p-6 space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-wider text-text-secondary">Token QR</h2>

            @if ($activeToken)
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-text-muted">Token</dt>
                        <dd class="font-mono text-text-main">{{ $activeToken->token }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">Diterbitkan</dt>
                        <dd class="text-text-main">{{ \App\Support\IndonesianDate::dateTime($activeToken->issued_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">Diterbitkan oleh</dt>
                        <dd class="text-text-main">{{ $activeToken->issuedBy?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">Jumlah scan</dt>
                        <dd class="text-text-main">{{ $activeToken->scan_count }}{{ $activeToken->last_scanned_at ? ' · terakhir '.\App\Support\IndonesianDate::dateTime($activeToken->last_scanned_at) : '' }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">Login ID Portal</dt>
                        <dd class="font-mono text-text-main">{{ $customer->portal_login_id ?? '— (POP belum punya kode registrasi)' }}</dd>
                    </div>
                    <div>
                        <dt class="text-text-muted">Status Akun Portal</dt>
                        <dd class="text-text-main">
                            @if (! $portalAccount)
                                <span class="text-text-muted">Belum ada</span>
                            @elseif ($portalAccount->status === 'active')
                                <span class="font-semibold text-emerald-600 dark:text-emerald-400">Aktif (sudah diklaim)</span>
                            @else
                                <span class="font-semibold text-amber-600 dark:text-amber-400">Menunggu klaim</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                <div class="flex flex-wrap gap-3 pt-2">
                    {{-- pin_hash reversible sejak 2026-08-26 (perintah
                         eksplisit user) — halaman cetak reprintable ini
                         SEKARANG ikut nunjukin PIN kapan pun dibuka ulang,
                         bukan cuma modal sekali-tampil. Lihat
                         CustomerQrTokenService::revealPin(). --}}
                    <a href="{{ route('customers.qr.print', $customer) }}" target="_blank"
                       class="px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold">
                        Cetak Stiker
                    </a>

                    @if (auth()->user()->hasPermission('customers.qr.create'))
                        <button type="button" x-show="!pinExists" x-cloak :disabled="loading" @click="issuePin()"
                                class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white text-sm font-semibold cursor-pointer">
                            <span x-show="!loading">Terbitkan PIN</span>
                            <span x-show="loading" x-cloak>Memproses…</span>
                        </button>
                    @endif

                    @if (auth()->user()->hasPermission('customers.qr.cancel'))
                        <button type="button" x-show="pinExists" x-cloak :disabled="loading"
                                @click="$dispatch('open-modal', 'reset-pin-confirm')"
                                class="px-4 py-2 rounded-lg border border-amber-300 dark:border-amber-800 text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20 disabled:opacity-60 text-sm font-semibold cursor-pointer">
                            <span x-show="!loading">Reset PIN</span>
                            <span x-show="loading" x-cloak>Memproses…</span>
                        </button>
                    @endif

                    @if (auth()->user()->hasPermission('customers.qr.cancel'))
                        <button type="button" @click="$dispatch('open-modal', 'revoke-confirm')"
                                class="px-4 py-2 rounded-lg border border-rose-300 dark:border-rose-800 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20 text-sm font-semibold cursor-pointer">
                            Cabut Token
                        </button>
                    @endif

                    {{-- "Lupa Password" — cuma relevan kalau akun portal
                         UDAH diklaim (`active`). Akun `pending_claim` gak
                         punya password buat "dilupakan" sama sekali, dan
                         controller nolak 409 kalau dipaksa. --}}
                    @if (auth()->user()->hasPermission('customers.qr.cancel') && $portalAccount?->status === 'active')
                        <button type="button" :disabled="loading" @click="$dispatch('open-modal', 'reset-portal-account-confirm')"
                                class="px-4 py-2 rounded-lg border border-rose-300 dark:border-rose-800 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20 disabled:opacity-60 text-sm font-semibold cursor-pointer">
                            <span x-show="!loading">Reset Akun Portal (Lupa Password)</span>
                            <span x-show="loading" x-cloak>Memproses…</span>
                        </button>
                    @endif
                </div>

                <div class="pt-4 border-t border-border text-sm">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-text-muted mb-2">Status PIN</h3>
                    <template x-if="pinExists">
                        <dl class="grid grid-cols-2 gap-3 text-xs">
                            <div>
                                <dt class="text-text-muted">Diterbitkan</dt>
                                <dd class="text-text-main" x-text="pinIssuedAtLabel"></dd>
                            </div>
                            <div>
                                <dt class="text-text-muted">Status</dt>
                                <dd class="font-semibold" :class="pinStatusClass" x-text="pinStatusLabel"></dd>
                            </div>
                        </dl>
                    </template>
                    <p x-show="!pinExists" class="text-xs text-text-secondary">Belum ada PIN diterbitkan.</p>

                    <template x-if="historyItems.length > 0">
                        <div class="mt-3">
                            <h4 class="text-[11px] font-bold uppercase tracking-wider text-text-muted mb-1">Riwayat PIN</h4>
                            <ul class="space-y-1 text-xs text-text-secondary">
                                <template x-for="row in historyItems" :key="row.at + row.action">
                                    <li x-text="row.action + ' — ' + row.at + ' oleh ' + row.by"></li>
                                </template>
                            </ul>
                        </div>
                    </template>
                </div>
            @else
                <p class="text-sm text-text-secondary">Pelanggan ini belum punya token QR aktif.</p>

                @if (auth()->user()->hasPermission('customers.qr.create'))
                    <button type="button" :disabled="loading" @click="issuePin(true)"
                            class="px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-700 disabled:opacity-60 text-white text-sm font-semibold cursor-pointer">
                        <span x-show="!loading">Terbitkan QR + PIN</span>
                        <span x-show="loading" x-cloak>Memproses…</span>
                    </button>
                @endif
            @endif

            @if ($revokedTokens->isNotEmpty())
                <div class="pt-4 border-t border-border">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-text-muted mb-2">Riwayat Token Dicabut</h3>
                    <ul class="space-y-2 text-xs text-text-secondary">
                        @foreach ($revokedTokens as $revoked)
                            <li class="font-mono">
                                {{ $revoked->token }} — dicabut {{ \App\Support\IndonesianDate::dateTime($revoked->revoked_at) }}
                                @if ($revoked->revoke_reason) ({{ $revoked->revoke_reason }}) @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="bg-surface border border-border rounded-xl p-6 flex flex-col items-center justify-center gap-3">
            @if ($qrDataUri)
                <img src="{{ $qrDataUri }}" alt="QR Pelanggan {{ $customer->full_name }}" class="w-40 h-40">
                <p class="text-[11px] font-mono text-text-muted break-all text-center">{{ $dispatchUrl }}</p>
            @else
                <div class="w-40 h-40 rounded-lg bg-surface-muted flex items-center justify-center text-text-muted text-xs text-center px-4">
                    Belum ada QR
                </div>
            @endif
        </div>
    </div>

    @if ($activeToken && auth()->user()->hasPermission('customers.qr.cancel'))
        <x-ui.modal name="revoke-confirm" title="Cabut Token QR" maxWidth="sm">
            <form action="{{ route('customers.qr.revoke', $customer) }}" method="POST" class="space-y-4">
                @csrf
                <p class="text-sm text-text-secondary">Stiker/kartu lama tidak akan berlaku lagi begitu token ini dicabut.</p>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="$dispatch('close-modal', 'revoke-confirm')"
                            class="px-4 py-2 rounded-lg text-sm font-semibold text-text-secondary cursor-pointer">Batal</button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold cursor-pointer">Cabut</button>
                </div>
            </form>
        </x-ui.modal>

        <x-ui.modal name="reset-pin-confirm" title="Reset PIN Pelanggan" maxWidth="sm">
            <div class="space-y-4">
                <p class="text-sm text-text-secondary">Apakah Anda yakin ingin me-reset PIN pelanggan ini? PIN lama akan langsung tidak aktif begitu proses selesai.</p>
                <div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-xs text-amber-700 dark:text-amber-300">
                    <strong>PENTING:</strong> PIN ini cuma gerbang halaman tagihan publik + kunci klaim awal — <strong>password Portal pelanggan (kalau sudah pernah diklaim) TIDAK ikut berubah</strong>, dan sesi login Portal yang sedang jalan tetap aktif, tidak ter-logout. Kalau yang lupa justru PASSWORD Portal-nya (bukan PIN ini), itu aksi TERPISAH (menu khusus "Lupa Password", cuma muncul buat akun yang sudah aktif) — jangan pakai Reset PIN ini buat itu.
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="$dispatch('close-modal', 'reset-pin-confirm')"
                            class="px-4 py-2 rounded-lg text-sm font-semibold text-text-secondary cursor-pointer">Batal</button>
                    <button type="button" @click="$dispatch('close-modal', 'reset-pin-confirm'); resetPin()"
                            class="px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold cursor-pointer">Ya, Reset PIN</button>
                </div>
            </div>
        </x-ui.modal>

        @if ($portalAccount?->status === 'active')
            <x-ui.modal name="reset-portal-account-confirm" title="Reset Akun Portal (Lupa Password)" maxWidth="sm">
                <div class="space-y-4">
                    <p class="text-sm text-text-secondary">
                        Ini buat pelanggan yang <strong>lupa password Portal</strong>-nya. Password lama
                        langsung tidak berlaku dan <strong>SEMUA sesi login portal pelanggan ini dicabut</strong> —
                        pelanggan wajib klaim ulang pakai PIN baru yang bakal ditampilkan setelah ini,
                        lalu pilih password baru sendiri.
                    </p>
                    <div class="p-3 rounded-lg bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 text-xs text-rose-700 dark:text-rose-300">
                        <strong>PENTING:</strong> Ini <strong>BUKAN</strong> "Reset PIN" biasa — pakai ini
                        HANYA kalau pelanggan lupa <strong>PASSWORD Portal</strong>-nya (akun sudah pernah
                        diklaim/aktif). Password lama langsung mati, status akun turun jadi "menunggu
                        klaim" lagi, dan SEMUA perangkat yang lagi login Portal pelanggan ini otomatis
                        ter-logout paksa.
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="$dispatch('close-modal', 'reset-portal-account-confirm')"
                                class="px-4 py-2 rounded-lg text-sm font-semibold text-text-secondary cursor-pointer">Batal</button>
                        <button type="button" @click="$dispatch('close-modal', 'reset-portal-account-confirm'); resetPortalAccount()"
                                class="px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold cursor-pointer">Ya, Reset Akun</button>
                    </div>
                </div>
            </x-ui.modal>
        @endif
    @endif

    {{--
        Modal HASIL PIN — dipakai DUA jalur (terbit pertama & reset). PIN
        baru SUDAH TERSIMPAN di DB & PIN lama SUDAH MATI begitu modal ini
        kebuka — konfirmasi terjadi SEBELUM ini (native confirm() di tombol
        Reset PIN, lihat atas), modal ini murni tampilan hasil, BUKAN titik
        konfirmasi. Koreksi 2026-08-26: PIN TIDAK LAGI cuma bisa dilihat di
        sini — `pin_hash` reversible sekarang, "Cetak Stiker" (link di atas)
        bisa nunjukin PIN yang sama kapan pun dibuka ulang. Modal ini tetap
        dipertahankan sebagai konfirmasi instan pas terbit/reset. --}}
    <x-ui.modal name="pin-reveal" title="Kartu Pelanggan (dengan PIN)" maxWidth="sm">
        {{-- Layout disamakan sama print.blade.php biar hasil cetaknya
             konsisten — kartu ini cetak cepat di tempat, "Cetak Stiker"
             di atas kartu ini untuk cetak ulang kapan pun. --}}
        <div id="pin-print-card" class="text-center space-y-2">
            <img :src="pinPreview.qrDataUri" alt="QR" class="w-32 h-32 mx-auto">
            <div class="font-bold uppercase text-sm text-text-main" x-text="pinPreview.customer.full_name"></div>
            <div class="text-xs font-mono text-text-muted" x-text="pinPreview.customer.customer_code + ' · ' + (pinPreview.customer.pop_name || '')"></div>
            <p class="text-[10px] text-text-secondary">Scan QR ini pakai kamera HP untuk cek &amp; bayar tagihan.</p>

            <div class="border-t border-dashed border-border pt-2">
                <div class="text-xs font-mono font-bold text-sky-600" x-text="'Login ID: ' + (pinPreview.customer.portal_login_id || '—')"></div>
                <p class="text-[10px] text-text-secondary">Login ID untuk aktivasi akun Portal Pelanggan (aplikasi terpisah).</p>
            </div>

            <div>
                <div class="text-[10px] font-bold uppercase tracking-wider text-text-muted">PIN Aktivasi</div>
                <div x-show="pinModalOpen" x-transition:enter="transition ease-out duration-500"
                     x-transition:enter-start="opacity-0 scale-75" x-transition:enter-end="opacity-100 scale-100"
                     class="text-3xl font-extrabold tracking-[0.3em] font-mono bg-surface-muted rounded-lg py-3 border border-dashed border-border text-text-main"
                     x-text="pinPreview.pin"></div>
            </div>

            <p class="text-xs text-text-secondary">Serahkan ke pelanggan sekarang. Bisa dicetak ulang kapan pun lewat tombol "Cetak Stiker".</p>
        </div>

        <x-slot name="footer">
            <button type="button" @click="closePinModal()"
                    class="px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold cursor-pointer">
                Selesai
            </button>
        </x-slot>
    </x-ui.modal>

    <style>
        @media print {
            body * { visibility: hidden; }
            #pin-print-card, #pin-print-card * { visibility: visible; }
            #pin-print-card { position: absolute; top: 0; left: 0; width: 100%; }
        }
    </style>
</div>

<script>
    function qrPinPage() {
        return {
            loading: false,
            pinExists: @json((bool) ($activeToken?->pin_hash)),
            pinMustChange: @json((bool) ($activeToken?->pin_must_change)),
            pinIssuedAtLabel: @json($activeToken?->pin_issued_at ? \App\Support\IndonesianDate::dateTime($activeToken->pin_issued_at) : ''),
            historyItems: @json($pinHistory ?? []),
            hadTokenOnLoad: @json((bool) $activeToken),
            pinModalOpen: false,
            pinPreview: { pin: '', qrDataUri: '', customer: {} },

            get pinStatusLabel() {
                return this.pinMustChange ? 'Belum pernah dipakai (wajib ganti)' : 'Aktif';
            },
            get pinStatusClass() {
                return this.pinMustChange ? 'text-amber-500' : 'text-emerald-500';
            },

            async issuePin(isFirstToken = false) {
                await this.callPinEndpoint('{{ route('customers.qr.issue', $customer) }}', isFirstToken);
            },

            async resetPin() {
                await this.callPinEndpoint('{{ route('customers.qr.pin.reissue', $customer) }}', false);
            },

            async resetPortalAccount() {
                // reloadOnClose = true — dl "Status Akun Portal" & tombol
                // ini sendiri (cuma muncul kalau status masih 'active')
                // dirender server-side, gak ada state Alpine buat itu.
                await this.callPinEndpoint('{{ route('customers.qr.portal-account.reset', $customer) }}', true);
            },

            async callPinEndpoint(url, reloadOnClose) {
                this.loading = true;
                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                    });

                    const contentType = res.headers.get('content-type') || '';
                    if (!res.ok || !contentType.includes('application/json')) {
                        // Kasus di luar dugaan UI ini (mis. token tiba-tiba gak
                        // aktif lagi) — biar server yang jawab lewat halaman
                        // biasa, bukan dipaksa jadi JSON di sini.
                        window.location.reload();
                        return;
                    }

                    const data = await res.json();

                    this.pinPreview = {
                        pin: data.pin,
                        qrDataUri: data.qr_data_uri,
                        customer: data.customer,
                    };
                    this.pinIssuedAtLabel = data.pin_issued_at;
                    this.pinMustChange = data.pin_must_change;
                    this.historyItems = data.history;
                    this.pinExists = true;
                    this.hadTokenOnLoad = this.hadTokenOnLoad || reloadOnClose;
                    this.pinReloadOnClose = reloadOnClose;

                    this.pinModalOpen = true;
                    window.dispatchEvent(new CustomEvent('open-modal', { detail: 'pin-reveal' }));
                } finally {
                    this.loading = false;
                }
            },

            closePinModal() {
                this.pinModalOpen = false;
                window.dispatchEvent(new CustomEvent('close-modal', { detail: 'pin-reveal' }));

                // Token BARU pertama kali kebentuk di request ini (halaman
                // dimuat tanpa token sama sekali) — bagian QR/dl token belum
                // pernah dirender PHP-nya, satu-satunya cara nampilin itu
                // tanpa duplikasi seluruh markup di JS adalah reload.
                if (this.pinReloadOnClose) {
                    window.location.reload();
                }
            },
        };
    }
</script>
@endsection
