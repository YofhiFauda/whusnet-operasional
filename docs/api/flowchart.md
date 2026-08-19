# Flowchart — API Eksternal

## 1. Webhook Pemasangan — dari tombol Aktivasi sampai sistem luar

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
    K --> P["dispatch(InstallationActivated) — EVENT BARU"]
    P --> PR[InstallationWebhookPresenter<br/>rakit data SEKALI]
    PR --> H1[outbox: Website B<br/>transport=http_json]
    PR --> H2[outbox: Telegram Eksternal<br/>transport=telegram]

    H1 --> Q["COMMIT :751"]
    H2 --> Q
    Q --> Q2[Step 6 Laporan Speedtest terbuka]
    Q2 -.->|"storeSpeedtest() — penyelesaian.<br/>TIDAK memicu webhook apa pun"| Q3[verification_admin]

    Q -->|afterCommit| R[Worker Horizon]
    R --> R1{transport?}
    R1 -->|http_json| S1["toJson() + HMAC X-Whusnet-Signature<br/>POST ke URL konsumen"]
    R1 -->|telegram| S0{Teks sama dengan<br/>kiriman terakhir?}
    S0 -->|ya| SK[status=skipped]
    S0 -->|tidak| S2["toTelegramText() + bot_token dari config endpoint<br/>POST api.telegram.org/sendMessage"]
    S1 --> T{Berhasil?}
    S2 --> T
    T -->|ya| U[status=delivered, delivered_at]
    T -->|tidak| V[attempts++, next_attempt_at, last_error]
    V --> W{attempts >= 8?}
    W -->|belum| R
    W -->|habis| X[status=failed + consecutive_failures++]
    X --> Y{Lewat ambang?}
    Y -->|ya| Z[is_active=false + beri tahu Owner]
    Y -->|tidak| AA[Berhenti untuk event ini]
```

Telegram **Internal** tidak muncul di diagram ini sama sekali — ia tetap berjalan
inline lewat `TelegramBotService` di enam tempat yang sudah ada, tidak disentuh, dan
tidak melewati outbox. Yang ada di sini hanya Telegram **Eksternal**.

Lima hal yang menentukan benar-tidaknya alur ini:

**Pemicunya tombol Aktivasi, bukan Mulai Pemasangan dan bukan penyelesaian laporan.**
Pada titik inilah keenam data yang diminta — nama, POP, desa, paket, SN, ODP — sudah
lengkap di database untuk pertama kalinya. Di tombol Mulai Pemasangan (`start()`)
SN dan ODP belum ada; di `storeSpeedtest()` sudah terlambat.

**Event `InstallationActivated` harus dibuat baru.** `storePemasangan()` tidak
menyiarkan apa pun hari ini — `broadcast()` di controller ini hanya ada di `:101`,
`:524`, dan `:873`. Ini satu-satunya bagian rancangan yang menuntut perubahan pada
`CustomerInstallationController`.

**Baris outbox ditulis di dalam transaksi, pengiriman terjadi setelah commit.**
`storePemasangan()` memakai `DB::beginTransaction()` manual (`:661`) dengan commit di
`:751`. Job yang berjalan sebelum commit membaca `customer_technical_details` yang
belum tertulis, jadi SN dan ODP terkirim `null` padahal teknisi baru saja mengisinya.
Kalau transaksi di-rollback, sistem luar sudah diberi tahu soal pemasangan yang tidak
pernah tercatat.

**Retry mengirim payload yang tersimpan, bukan merakit ulang.** Satu baris outbox =
satu event; `attempts` dinaikkan di tempat. Merakit ulang dari model berarti percobaan
ke-3 bisa membawa data yang sudah berubah, lalu dibuang penerima sebagai duplikat
`event_id` — perubahan itu hilang tanpa jejak.

**Kegagalan tidak boleh hilang diam-diam.** Baris `failed` tetap tinggal sebagai
daftar rekonsiliasi. `delivered` yang dipruning 90 hari, bukan `failed`.

**Pemilihan endpoint terjadi saat baris outbox dibuat.** Satu kejadian bisa punya
banyak pelanggan endpoint; data dirakit **sekali** oleh presenter, satu baris outbox
per endpoint, lalu tiap baris dirender dan diautentikasi menurut transport-nya
sendiri. Website B mati tidak menahan Telegram, dan sebaliknya.

**Dua transport sengaja berbeda perilaku pada Aktivasi berulang.** Website B menerima
setiap penekanan — penekanan kedua sering membawa SN/ODP yang benar. Telegram melewati
kiriman yang teksnya tidak berubah, karena kanal yang dibaca manusia punya batas
sekitar 20 pesan/menit dan membanjirinya berarti membuang pesan, bukan sekadar berisik.

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

---

## 3. Portal — klaim akun, login, dan rotasi token

```mermaid
flowchart TD
    subgraph Klaim sekali seumur akun
        A[Kartu pelanggan: login_id + PIN 6 digit] --> B["POST /auth/claim"]
        B --> C{PIN benar? lockout 5x/15m}
        C -->|tidak| D[Gagal, failed_attempts++ di DB]
        C -->|ya| E{Akun sudah pernah diklaim?}
        E -->|ya| F[Tolak, arahkan ke Lupa Password]
        E -->|tidak| G[Pelanggan tetapkan password sendiri, >=10 karakter]
        G --> H[customer_portal_accounts: status=active, claimed_at]
    end

    subgraph Login seterusnya
        I["POST /auth/login: login_id + password"] --> J{Throttle: 5/15m per IP+login_id<br/>DAN 20/15m per IP}
        J -->|lewat batas| K[429]
        J -->|lolos| L{locked_until masih berlaku?}
        L -->|ya| K
        L -->|tidak| M{Password cocok?}
        M -->|tidak| N[failed_attempts++ di DB, mungkin locked_until]
        M -->|ya| O[access 15 menit + refresh 30 hari]
    end

    O --> P[Portal simpan di sesi server-side HttpOnly]
    P --> Q{Access expired?}
    Q -->|ya| R["POST /auth/refresh"]
    R --> S{Refresh sudah pernah dipakai?}
    S -->|ya| T[TOKEN DICURI: cabut seluruh rantai, paksa login ulang]
    S -->|tidak| U[Terbitkan pasangan baru, tandai yang lama terpakai]
    U --> P
```

Hitungan kegagalan disimpan **di DB**, bukan hanya di rate limiter. Limiter tinggal di
cache, dan cache bisa di-flush — lockout ikut hilang bersamanya. Alasan yang sama
dipakai untuk lockout PIN di §6.5.4.

Dua limiter untuk kredensial, bukan satu. Kunci per-`login_id` saja memberi ember baru
untuk tiap login ID, jadi penyapuan satu percobaan × 1.900 akun dari satu IP tidak
pernah menyentuh batas. Limiter per-IP yang menghentikannya.

Pelanggan tanpa akun portal dijawab **401 kredensial salah**, bukan "akun belum
diaktifkan" — pesan kedua memberi tahu penebak bahwa login ID itu valid, dan seluruh
guna throttle hilang.

---

## 4. Portal — pengambilan data dan penjaga kepemilikan

```mermaid
flowchart TD
    A[Request: X-Portal-Client + Bearer token] --> B{Client secret valid?}
    B -->|tidak| C[401 — tuas darurat: cabut secret, portal mati]
    B -->|ya| D[Resolve customer_id DARI TOKEN]
    D --> E[Query dibuka lewat titik yang sudah terfilter customer_id]
    E --> F{Daftar atau detail?}
    F -->|daftar| G[Filter tampilan: status, periode, paginasi]
    F -->|detail| H[Binding by INV- / PAY- / TKT-]
    H --> I{Ketemu DI DALAM query terfilter?}
    I -->|tidak| J[404]
    I -->|ya| K[Resource: daftar putih kolom + nominal string desimal]
    G --> K
    K --> L["{ data, meta }"]
```

Penjaganya ada di **satu** tempat: query dibuka sudah terfilter, dan binding mencari
di dalam query itu — bukan `Invoice::where('invoice_number', ...)` lalu dicek
belakangan. Bedanya kelihatan saat controller keenam ditulis oleh orang yang belum
membaca dokumen ini: pola pertama gagal aman, pola kedua gagal terbuka.

Nomor milik pelanggan lain dijawab **404**, sama persis dengan nomor yang tidak ada.

---

## 5. Status tiket — kenapa `tickets.status` tidak boleh dibaca langsung

```mermaid
flowchart TD
    A[Tiket] --> B{handler?}
    B -->|helpdesk, open| C[Diterima]
    B -->|noc, open| D[Sedang Ditangani]
    B -->|closed| E[Selesai]
    B -->|cancelled| F[Dibatalkan]
    B -->|fop| G["Ticket::resolveStatus()<br/>ambil status dari FopTask"]
    G --> H{TaskStatus}
    H -->|selesai| E
    H -->|dibatalkan| F
    H -->|draft/terjadwal/in_progress/pending| D
    H -->|null: FopTask hilang, orphan| D
```

Begitu `handler = FOP`, `TicketHandlingStatus` **berhenti bermakna** — status
sesungguhnya turun dari FopTask/Task. Presenter yang cuma membaca `tickets.status`
akan menampilkan "Sedang Ditangani" selamanya untuk tiket yang sudah lama selesai di
lapangan.

`Ticket::resolveStatus()` (`app/Models/Ticket.php:439`) sudah menangani sisi itu —
pakai, jangan tulis resolusi kedua. Yang **tidak** dipakai adalah
`Ticket::statusLabel()` (`:447`): ia label untuk staf dan mengembalikan "Diproses NOC",
"Ditangani Helpdesk", "Terputus" — struktur organisasi internal yang §6.6.7 larang
keluar.

Tiket orphan tampil sebagai "Sedang Ditangani", bukan "Terputus". Orphan adalah
kegagalan data internal kita; memindahkannya ke layar pelanggan tidak menolong
siapa pun. `Ticket::isOrphan()` (`:83`) tetap memunculkannya di sisi internal.

---

## 6. Kwitansi — dua bagian, dua mekanisme

```mermaid
flowchart LR
    subgraph "Jalur pencatatan uang (sudah ada)"
        A1[Admin/kasir<br/>PaymentService] --> P[(payments)]
        A2[Batch kolektor<br/>CollectorPaymentService] --> P
        A3[Kolektor mandiri<br/>CollectorPaymentController] --> P
    end
    P --> RC["Invoice::recalculateFromPayments()"]
    RC --> IU[INSERT webhook_outbox<br/>invoice.updated, state penuh, tanpa PII]
    IU -.->|Bagian B: kabar| PORTAL[Portal]
    P --> R["ReceiptPresenter::for()"]
    R --> V1[Struk thermal 80mm]
    R --> V2[Lembar A4]
    R --> V3[Kartu kolektor]
    R --> V4[Resource portal:<br/>buang penerima/penagih/catatan]
    V4 -.->|"Bagian A: isi, ditarik lewat GET"| PORTAL
```

**Bagian A — isi kwitansi.** Tidak ada yang perlu dibangun: kwitansi turunan dari
baris `payments`, dirakit saat diminta oleh presenter yang sama untuk keempat
keluaran. Pembayaran yang dicatat kolektor di lapangan langsung tersedia begitu
barisnya tersimpan. Yang **wajib** ditambahkan hanyalah pemangkasan `penerima`,
`penagih`, dan `catatan` — ketiganya data pegawai, dan tanpa dipangkas satu endpoint
kwitansi membatalkan daftar putih endpoint `/me/payments` di sebelahnya.

**Bagian B — kabar bahwa pembayaran selesai.** Karena portal aplikasi terpisah tanpa
akses DB, ia tidak tahu ada pembayaran baru sampai seseorang membuka halaman. Titik
picunya `Invoice::recalculateFromPayments()` — **bukan** `PaymentObserver`, yang
melewatkan jalur penolakan pembayaran dan menembak sebelum invoice selesai dihitung.

Webhook memberi tahu, API yang jadi kebenaran. Kalau webhook hilang, portal tetap
benar begitu halaman dibuka, karena halamannya menarik dari `GET /me/invoices`.
