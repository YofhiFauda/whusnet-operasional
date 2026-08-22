# Keputusan & Analisa — API Baru: Topologi Jaringan & Konfirmasi Assignment

Dokumen pendamping. `business-logic.md`/`database-schema.md` menjelaskan **rancangan
seperti apa adanya**; berkas ini menjelaskan **kenapa jadi begitu, dan apa yang
ditolak di jalan**.

---

## 1. Riwayat revisi

| Rev | Tanggal | Yang berubah | Pemicu |
|---|---|---|---|
| 1 | 2026-08-20 | Rancangan awal: satu endpoint tulis (`network-assignment`) | Pertanyaan "bagaimana Website B atur Mini POP/Distribusi" |
| 2 | 2026-08-20 | Ditambah endpoint baca (`pop-distribusi`) — ditemukan Website B tidak tahu kode Mini POP/Distribusi tanpa referensi | Pertanyaan "bagaimana mereka tau kode yang ada" |
| 3 | 2026-08-20 | Folder dokumen dipisah dari `api-webhook-pemasangan`/`api-portal-pelanggan` | Permintaan restrukturisasi |
| 4 | 2026-08-20 | Rename endpoint #1 `network-topology` → `pop-distribusi`, config token `NETWORK_TOPOLOGY_READ_TOKEN` → `POP_DISTRIBUSI_READ_TOKEN` | Permintaan penamaan |
| 5 | 2026-08-20 | Endpoint #2 ditambah field opsional `perangkat.pppoe_username`/`pppoe_password`/`ip_address` — Website B isi ulang kredensial jaringan di momen konfirmasi aktivasi yang sama | Kebutuhan: username/password PPPoE + IP di Whusnet terisi otomatis begitu Website B aktivasi |

---

## 2. Kenapa dua endpoint, bukan satu

Alternatif yang sempat terlihat lebih sederhana: satu endpoint saja, Website B kirim
kode Mini POP/Distribusi langsung tanpa referensi baca terpisah — mereka "tebak" dari
dokumentasi statis yang dikirim manual sekali di awal.

**Ditolak.** Mini POP/Distribusi jarang berubah tapi **tetap** berubah (bulanan,
kadang ada tambahan). Dokumentasi statis (spreadsheet, PDF) basi begitu ada
perubahan dan tidak ada yang menjamin Website B selalu pakai versi terbaru. Endpoint
baca yang bisa dipanggil kapan saja menghapus kelas masalah itu — sinkron selalu
terjamin karena mereka membaca langsung dari sumbernya, bukan salinan yang bisa
basi.

## 3. Kenapa bukan dikirim di payload `installation.activated`

Alternatif lain: sisipkan daftar Mini POP/Distribusi (di-scope ke POP pelanggan) di
payload `api-webhook-pemasangan`, jadi Website B tidak perlu endpoint kedua.

**Ditolak.** `installation.activated` bisa terkirim berkali-kali untuk satu
pelanggan (lihat `api-webhook-pemasangan/business-logic.md` "Aktivasi bisa ditekan berkali-kali") —
mengulang daftar topologi lengkap di **setiap** pengiriman itu boros, terutama karena
data itu tidak berubah antar pengiriman. Endpoint baca terpisah, dipanggil sesuka
Website B (cache, refresh bulanan), jauh lebih murah untuk data yang jarang berubah.

Juga: payload `api-webhook-pemasangan` di-scope per pelanggan (POP tunggal), sementara kebutuhan
nyatanya "daftar lengkap semua POP" (dikonfirmasi) — scope yang beda membuat
penyisipan ke payload jadi tidak pas secara desain, bukan cuma soal boros.

## 4. Kenapa validasi Mini POP/Distribusi harus reuse, bukan ditulis ulang

Alternatif yang tergoda: tulis validasi baru yang lebih sederhana khusus untuk
endpoint API (skip beberapa pengecekan demi ringkas).

**Ditolak.** `CustomerNetworkAssignmentController::update()` sudah punya aturan
validasi yang benar dan sudah diuji lewat jalur staf: Mini POP harus anak Cabang POP
pelanggan, Distribusi harus anak Mini POP yang dipilih, status pelanggan tidak boleh
di tahap belum-mulai-pasang. Menulis ulang aturan yang berbeda untuk jalur API berarti
dua sumber kebenaran untuk satu invarian data — begitu salah satu diubah, yang lain
lupa diikutkan, dan validasi antara jalur staf vs jalur API diam-diam menyimpang.

## 5. Kenapa dua kredensial (baca vs tulis), bukan satu

Alternatif: satu token bearer untuk kedua endpoint, lebih sedikit yang perlu
dikelola.

**Ditolak.** Dua endpoint ini beda kelas risiko total. Baca topologi cuma expose
struktur internal (nama Mini POP, kode Distribusi) — bocor pun dampaknya kecil.
Tulis assignment mengubah `cid` pelanggan — identitas yang dipakai di seluruh
sistem (billing, dokumen legal, kwitansi). Kalau token baca bocor (risiko lebih
tinggi karena lebih sering dipakai/logged), dampaknya tidak ikut membuka jalur
tulis. Prinsip yang sama dengan pemisahan secret HMAC arah keluar vs token callback
arah masuk di `api-webhook-pemasangan`.

## 6. Kenapa `idempotency_key`, bukan `customer_id` atau `cid`, sebagai kunci

Dibahas detail di `database-schema.md`. Ringkas: `customer_id` tidak pernah boleh
diterima dari luar (larangan keras lintas-API), `cid` nullable dan justru salah satu
nilai yang ditulis endpoint ini.

## 7. Kenapa validasi gagal langsung ditolak, bukan menunggu approval staf

Alternatif: endpoint ini cuma "mengusulkan" assignment, staf tetap harus approve
manual sebelum benar-benar tersimpan.

**Belum diputuskan pemilik produk** — default rancangan saat ini (langsung eksekusi
kalau valid) diusulkan karena review otomatis dari sistem provisioning (validasi
Mini POP/Distribusi cocok dengan Cabang POP pelanggan) pada dasarnya melakukan
pemeriksaan yang sama dengan yang staf lakukan manual. Tapi ini bisa berubah — lihat
`rencana-implementasi.md`.

## 8. Kenapa kredensial jaringan (PPPoE/IP) digabung ke endpoint #2, bukan endpoint ketiga

Alternatif: `POST /api/v1/installations/network-credentials` terpisah, dipanggil
kapan saja Website B siap, tidak harus bareng konfirmasi Mini POP/Distribusi.

**Ditolak untuk sekarang.** Kebutuhan yang disampaikan: begitu Website B melakukan
aktivasi (momen yang sama dengan konfirmasi Mini POP/Distribusi), mereka sudah tahu
atau baru saja menetapkan kredensial PPPoE + IP pelanggan — satu kejadian bisnis,
bukan dua kejadian dengan jarak waktu. Endpoint terpisah berarti dua request untuk
satu momen, dan risiko baru: salah satu request sukses, satunya gagal, pelanggan
punya Mini POP benar tapi kredensial jaringan basi (atau sebaliknya) tanpa cara
mudah tahu bagian mana yang belum sinkron.

**Kapan alternatif ini relevan dipertimbangkan ulang:** kalau nyatanya kredensial
PPPoE/IP sering ditetapkan Website B **belakangan**, terpisah jauh dari momen
konfirmasi Mini POP/Distribusi (mis. Mini POP dikonfirmasi manual oleh NOC dulu,
PPPoE baru menyusul beberapa hari kemudian dari sistem provisioning berbeda) — kalau
itu polanya, satu endpoint yang memaksa keduanya datang bareng jadi penghalang,
bukan penyederhana, dan endpoint ketiga jadi pilihan yang lebih tepat.

## 9. Kenapa `pppoe_password` tidak dienkripsi sekarang, meski lewat jalur baru

Alternatif: tambahkan `encrypted` cast ke `customer_devices.pppoe_password` sebagai
bagian dari pekerjaan ini, karena sekarang ada jalur tulis baru dari luar organisasi
ke kolom itu.

**Ditunda, bukan ditolak** — lihat pertanyaan terbuka di `rencana-implementasi.md`.
Alasan menunda: kolom ini sudah plaintext sejak jalur staf (`storePemasangan()`),
dan mengenkripsinya cuma untuk jalur API baru berarti satu kolom, dua kebijakan
penyimpanan tergantung siapa yang menulis — lebih membingungkan daripada aman.
Kalau dienkripsi, itu keputusan yang berlaku untuk **seluruh** penulis kolom
tersebut (termasuk wizard teknisi), bukan tambalan khusus endpoint ini — dan itu di
luar cakupan pekerjaan menambahkan dua endpoint baru.
