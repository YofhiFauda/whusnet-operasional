# Flowchart — API Eksternal

## 1. Webhook Pemasangan — dari tombol Aktivasi sampai sistem luar

Wizard Laporan Pemasangan punya enam langkah. Yang memicu webhook adalah **tombol
Aktivasi** di panel step 6, yang mengirim form step 5 — bukan tombol Mulai Pemasangan,
dan bukan Laporan Speedtest.

```mermaid
flowchart TD
    W1[Step 1-4: data pelanggan & foto] --> W5[Step 5: Perangkat — SN, ODP, OLT, VLAN]
    W5 --> BTN[["Tombol AKTIVASI"]]
    BTN --> A["CustomerInstallationController::storePemasangan()"]
    A --> GATE{3 foto + minimal 1 material?}
    GATE -->|tidak| ERR[Balik ke form, tidak ada event]
    GATE -->|ya| TX{"DB::beginTransaction :660"}
    TX --> D[customer_installations: foto, catatan<br/>status TETAP in_progress]
    TX --> E[customer_technical_details: SN, ODP, ODP port, OLT, VLAN]
    TX --> F[task_materials + task_work_tools]
    TX --> G["customer_devices: serial_number :735<br/>(TANPA odp)"]
    G --> EV["event(InstallationActivated) — pernyataan TERAKHIR"]
    EV -.->|listener| H[INSERT webhook_outbox, status=pending]
    H --> Q["DB::commit :752"]
    Q --> W6[Step 6: Laporan Speedtest terbuka]

    Q -->|afterCommit| R[Worker Horizon queue 'webhook']
    R --> S[Tanda tangani HMAC atas payload TERSIMPAN]
    S --> T{2xx?}
    T -->|ya| U[status=delivered, delivered_at]
    T -->|tidak| V[attempts++, next_attempt_at, last_error]
    V --> X{attempts >= 8?}
    X -->|belum| R
    X -->|habis| Y[status=failed + consecutive_failures++]
    Y --> Z{Lewat ambang?}
    Z -->|ya| Z1[is_active=false + beri tahu Owner]
    Z -->|tidak| Z2[Berhenti untuk event ini]
```

Lima hal yang menentukan benar-tidaknya alur ini:

**Event di-dispatch paling akhir, sebelum `DB::commit()`.** `storePemasangan()` menulis
berurutan dan `customer_devices` adalah yang **terakhir** (`:735-750`). Event yang
terbit sebelum baris itu menghasilkan payload tanpa data perangkat — persis data yang
jadi alasan event ini ada.

**Event ini belum ada di repo.** Tidak seperti rancangan sebelumnya yang menumpang
`InstallationStarted`/`InstallationCompleted`, di sini `App\Events\InstallationActivated`
harus dibuat dan di-dispatch dari controller. Satu baris, tapi controller memang
tersentuh.

**Baris outbox ditulis di dalam transaksi, pengiriman terjadi setelah commit.**
`storePemasangan()` memakai `DB::beginTransaction()` manual (`:660`) dengan commit di
`:752`. Job yang berjalan sebelum commit membaca data yang belum tertulis; kalau
transaksi di-rollback, sistem luar sudah diberi tahu soal pemasangan yang tidak pernah
tercatat.

**Gerbang tiga foto + satu material berjalan lebih dulu.** Aktivasi yang ditolak karena
foto kontrak belum diunggah tidak menghasilkan event apa pun — dan itu benar: tidak ada
yang perlu dikabarkan.

**Retry mengirim payload yang tersimpan, bukan merakit ulang.** Satu baris outbox =
satu event; `attempts` dinaikkan di tempat. Merakit ulang dari model berarti percobaan
ke-3 bisa membawa data yang sudah berubah, lalu dibuang penerima sebagai duplikat
`event_id` — perubahan itu hilang tanpa jejak.

**Kegagalan tidak boleh hilang diam-diam.** Baris `failed` tetap tinggal sebagai
daftar rekonsiliasi. `delivered` yang dipruning 90 hari, bukan `failed`.

**Pemilihan endpoint terjadi saat baris outbox dibuat.** Satu kejadian bisa punya
banyak pelanggan endpoint; payload dirakit sekali, satu baris outbox per endpoint,
ditandatangani dengan secret masing-masing.

---

## 2. Aktivasi ditekan berkali-kali — kenapa `event_id` saja tidak cukup

`storePemasangan()` tidak punya gerbang "sudah pernah diaktivasi". Ia menerima status
`installation_in_progress` **dan** `revision_installation` (`:573-577`), dan isinya
sekadar `updateOrCreate`. Jadi penekanan berulang adalah keadaan normal, bukan
penyalahgunaan.

```mermaid
flowchart LR
    A[Aktivasi ditekan] --> B["installation.activated<br/>event_id=E1<br/>idempotency_key=installation:1174"]
    B --> C{Kenapa ditekan lagi?}
    C -->|SN salah ketik, diperbaiki| E
    C -->|Foto kontrak buram, diunggah ulang| E
    C -->|Verifikasi admin menolak → revisi| E
    E[Aktivasi ditekan lagi] --> F["installation.activated<br/>event_id=E2<br/>idempotency_key=installation:1174"]
    F --> G{Penerima}
    G -->|"pakai event_id saja"| H[Dua pemasangan terdaftar<br/>provisioning dobel]
    G -->|"pakai idempotency_key"| I[Upsert state pemasangan<br/>E2 menang]
```

`event_id` menjawab "apakah kiriman ini duplikat jaringan" — nilainya sama hanya untuk
percobaan ulang **pengiriman** kejadian yang sama.

`idempotency_key` menjawab "apakah event ini menggantikan yang sebelumnya" — nilainya
sama untuk semua Aktivasi terhadap baris `customer_installations` yang sama, karena
satu baris = satu pemasangan fisik. Koreksi terhadap pemasangan yang sama memang harus
berbagi kunci.

Keduanya dibutuhkan, dan keduanya punya aturan berbeda soal kapan nilainya sama.

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
