# Flowchart — API 1: Webhook Pemasangan

## 1. Dari tombol Aktivasi sampai sistem luar

```mermaid
flowchart TD
    A1[Step 1-4 wizard: survei, jadwal, mulai pemasangan] --> A2[Step 5: Laporan Pemasangan & Perangkat<br/>isi SN, ODP, OLT, VLAN, foto, material]
    A2 --> A[["Tombol AKTIVASI LAPORAN SPEEDTEST"]]
    A --> B["CustomerInstallationController::storePemasangan()"]
    B --> K{"DB::beginTransaction :661"}
    K --> L["customer_technical_details :695<br/>router_or_ont_serial, odp_number, odp_port"]
    K --> M["customer_devices :735<br/>serial_number — TANPA odp"]
    K --> N[installation_status TETAP in_progress]
    K --> O[Task PSB TIDAK diselesaikan]
    K --> P["dispatch(InstallationActivated)"]
    P --> PR[InstallationWebhookPresenter<br/>rakit data SEKALI]
    PR --> H1[outbox: Website B<br/>transport=http_json]
    PR --> H2[outbox: Telegram Eksternal<br/>transport=telegram]

    H1 --> Q["COMMIT :751"]
    H2 --> Q
    Q --> Q2[Step 6 Laporan Speedtest terbuka]
    Q2 -.->|"storeSpeedtest() — penyelesaian.<br/>TIDAK memicu webhook apa pun"| Q3[verification_admin]

    Q -->|"DB::afterCommit() + try/catch"| R[Worker Horizon]
    R --> R1{transport?}
    R1 -->|http_json| S1["toJson() + HMAC X-Whusnet-Signature<br/>POST ke URL konsumen"]
    R1 -->|telegram| S0{Teks sama dengan<br/>kiriman terakhir?}
    S0 -->|ya| SK[status=skipped]
    S0 -->|tidak| S2["toTelegramText() + bot_token dari config webhooks.telegram_external<br/>POST api.telegram.org/sendMessage"]
    S1 --> T{Berhasil?}
    S2 --> T
    T -->|ya| U[status=delivered, delivered_at]
    T -->|tidak| V[attempts++, next_attempt_at, last_error]
    V --> W{attempts >= 8?}
    W -->|belum| R
    W -->|habis| X[status=failed]
    X --> Y[Log + alert manual ke Owner]
```

Telegram **Internal** tidak muncul di diagram ini sama sekali — ia tetap berjalan
inline lewat `TelegramBotService` di enam tempat yang sudah ada, tidak disentuh, dan
tidak melewati outbox. Yang ada di sini hanya Telegram **Eksternal**.

Lima hal yang menentukan benar-tidaknya alur ini:

**Pemicunya tombol Aktivasi, bukan Mulai Pemasangan dan bukan penyelesaian laporan.**
Pada titik inilah keenam data yang diminta — nama, POP, desa, paket, SN, ODP — sudah
lengkap di database untuk pertama kalinya.

**Event `InstallationActivated` dibuat baru.** `storePemasangan()` tidak menyiarkan
apa pun sebelum perubahan ini — `broadcast()` di controller ini hanya ada di titik
lain (`start()`, `store()` legacy, `storeSpeedtest()`).

**Baris outbox ditulis di dalam transaksi, pengiriman terjadi setelah commit —
dibungkus try/catch eksplisit.** `storePemasangan()` memakai `DB::beginTransaction()`
manual (`:661`) dengan commit di `:751`. Dispatch job dibungkus
`DB::afterCommit(fn () => try { ... } catch (Throwable $e) { Log::error(...) })` di
`SendInstallationActivatedWebhooks::dispatchAfterCommitSafely()` — bukan
`->afterCommit()` polos, karena di queue `sync` itu mengeksekusi job inline saat
`DB::commit()` dipanggil, dan kalau job melempar, exception-nya bisa naik balik ke
`storePemasangan()`'s try/catch dan memicu `DB::rollBack()` padahal commit sudah
sukses. Kegagalan kirim webhook tidak boleh pernah menggagalkan alur yang
memicunya.

**Retry mengirim payload yang tersimpan, bukan merakit ulang.** Satu baris outbox =
satu event; `attempts` dinaikkan di tempat.

**Kegagalan tidak boleh hilang diam-diam.** Baris `failed` tetap tinggal sebagai
daftar rekonsiliasi. `delivered` yang dipruning 90 hari, bukan `failed`.

**Dua transport sengaja berbeda perilaku pada Aktivasi berulang.** Website B menerima
setiap penekanan. Telegram melewati kiriman yang teksnya tidak berubah.

---

## 2. Aktivasi ditekan berulang — kenapa `event_id` saja tidak cukup

```mermaid
flowchart LR
    A[Tekan Aktivasi<br/>SN salah ketik] --> B["installation.activated<br/>event_id=E1<br/>idempotency_key=…:activation:1"]
    B --> C{Kenapa ditekan lagi?}
    C -->|"Teknisi ralat SN/ODP<br/>di layar yang sama"| E
    C -->|"Verifikasi admin menolak<br/>→ revision_installation"| E[Tekan Aktivasi lagi<br/>SN/ODP diperbaiki]
    E --> F["installation.activated<br/>event_id=E2<br/>idempotency_key=…:activation:2"]
    F --> G{Penerima}
    G -->|"pakai event_id saja"| H[Dua pemasangan terdaftar<br/>provisioning dobel, SN berbeda]
    G -->|"pakai idempotency_key"| I[Upsert state pemasangan<br/>activation:2 menang]
```

`storePemasangan()` tidak mengunci apa pun setelah sukses — `updateOrCreate` di `:695`
dan `:735` memang dirancang supaya teknisi bisa meralat. Ditambah alur revisi
(`revision_installation` diterima di `:574`), penekanan berulang adalah **jalur
normal**, bukan kasus tepi.

`event_id` menjawab "apakah kiriman ini duplikat jaringan". `idempotency_key` menjawab
"apakah event ini menggantikan yang sebelumnya". Keduanya dibutuhkan, dan keduanya
punya aturan berbeda soal kapan nilainya sama.
