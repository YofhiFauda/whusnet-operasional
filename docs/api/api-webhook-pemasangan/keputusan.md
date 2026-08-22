# Keputusan & Analisa — API 1: Webhook Pemasangan

Dokumen pendamping. `business-logic.md`/`database-schema.md`/`flowchart.md`
menjelaskan **rancangan seperti apa adanya**; berkas ini menjelaskan **kenapa jadi
begitu, dan apa yang ditolak di jalan.**

Alasannya praktis: rancangan yang sudah rapi terbaca seolah selalu begitu. Tanpa
catatan ini, enam bulan lagi seseorang akan mengusulkan ulang salah satu alternatif
yang sudah ditolak — dan tidak ada yang bisa menjawab kenapa dulu tidak dipakai.

---

## 1. Riwayat revisi

| Rev | Tanggal | Yang berubah | Pemicu |
|---|---|---|---|
| 1 | 2026-08-18 | Rancangan awal: 2 event (`started`+`completed`), Sanctum, login `cid`, password di `customers` | Permintaan awal |
| 2 | 2026-08-18 | 15 temuan review ditambal | Review kode |
| 3 | 2026-08-18 | Trigger dikoreksi ke tombol **Aktivasi Laporan Speedtest** | Koreksi pemilik produk |
| 4 | 2026-08-19 | Fan-out dua transport; Telegram Internal vs Eksternal dipisah | Skenario Website B + Telegram |
| 5 | 2026-08-19 | Hanya `InstallationActivated`; `installation.completed` dihapus dari kontrak | Keputusan pemilik produk |
| 6 | 2026-08-19 | Log keputusan ini dibuat | Analisa sebelumnya belum tercatat |
| 7 | 2026-08-20 | Callback Hasil Provisioning dirinci; gap validasi SSRF di `webhook_endpoints.url` dicatat | Diskusi rancangan |
| 8 | 2026-08-20 | **Dibalik**: `webhook_endpoints` (tabel + form admin) dicabut. Website B, Telegram Internal, Telegram Eksternal semuanya **hardcode** di `config/webhooks.php` + `.env`. Alasan: cuma 1 konsumen tiap transport sekarang — tabel+form dinamis untuk konsumen yang belum ada adalah abstraksi sebelum dibutuhkan. Gap SSRF jadi tidak relevan | Keputusan pemilik produk |
| 9 | 2026-08-20 | **Diimplementasikan.** Ditemukan: `->afterCommit()` polos berbahaya di queue `sync`; properti `$afterCommit` bentrok trait `Queueable`; nomor aktivasi dihitung di PHP bukan raw SQL; `login_id` di-omit; `olt` digabung 3 kolom; guard HTTPS ditambah setelah testing manual | Implementasi + testing lewat Cloudflare Tunnel |
| 10 | 2026-08-20 | Modul dipecah jadi `docs/api/api-webhook-pemasangan/`, `api-portal-pelanggan/`, `api-pop-distribusi/` — folder terpisah per API | Permintaan restrukturisasi dokumen |

---

## 2. Kenapa Webhook, bukan REST

### Alasan pokok: kebutuhannya berbentuk kejadian

Permintaan aslinya *"trigger saat tombol Aktivasi ditekan"*. REST tidak bisa
menyatakan "ketika X terjadi" — ia hanya menjawab kalau ada yang bertanya. Memakai
REST berarti konsumen harus **menebak** kapan harus bertanya.

### Empat argumen pendukung

**1. Biaya polling ditanggung dua pihak, hasilnya lebih buruk.** Supaya Website B
menangkap Aktivasi dalam satu menit, ia polling tiap menit — 1.440 permintaan/hari
yang 99% lebih mengembalikan "tidak ada apa-apa".

**2. Latensi menentukan kegunaannya.** Provisioning idealnya jalan saat teknisi masih
di lokasi — kalau layanan gagal menyala, dia masih bisa membetulkan. Webhook sampai
dalam hitungan detik; polling dalam hitungan interval.

**3. Telegram sebagai tujuan hanya bisa menerima dorongan.** Ia tidak akan pernah
menarik data dari kita.

**4. Aktivasi berulang justru butuh push.** Teknisi meralat SN lalu tekan Aktivasi
lagi. Dengan push, koreksi sampai seketika.

### Empat kelemahan webhook — dicatat, bukan disembunyikan

| Kelemahan | Dampak | Penawar |
|---|---|---|
| Konsumen mati = data hilang dari sisi mereka | Setelah 8 percobaan habis, kabar itu tidak pernah sampai | Endpoint baca rekonsiliasi (§3) |
| Tidak bisa backfill | Konsumen baru mulai dengan riwayat nol | Endpoint baca rekonsiliasi (§3) |
| Tidak bisa menjawab "status pelanggan X sekarang apa?" | Webhook hanya mengabarkan perubahan | Endpoint baca rekonsiliasi (§3) |
| Beban onboarding lebih berat | Konsumen wajib punya endpoint HTTPS publik + verifikasi HMAC | Diterima sadar; transport `telegram` jadi jalur ringan |

---

## 3. Endpoint baca rekonsiliasi — dirancang, belum masuk fase

```
GET /api/v1/installations/{cid}
GET /api/v1/installations?activated_since=2026-08-01T00:00:00%2B07:00&cursor=…
```

- Isi respons **identik** dengan `data` di payload webhook — dirakit presenter yang
  sama.
- Auth: token klien tetap, hardcode di `.env`. Tidak ada pembatas `pop_id`.
- Paginasi cursor, bukan offset.
- Rate limit sendiri; ini endpoint pemulihan, bukan pengganti webhook.

Prinsip yang sama seperti `api-portal-pelanggan`: **webhook memberi tahu, API yang jadi kebenaran.**

---

## 4. Peta pengembangan

### Murah, tapi sejak rev. 8 tidak lagi nol-kode

| Pengembangan | Yang perlu diubah |
|---|---|
| Event baru (`customer.suspended`, dst) | Satu listener baru + satu entri destinasi di `config/webhooks.php` |
| Tujuan baru (WhatsApp, email, Slack) | Satu adapter transport + satu entri config |
| Konsumen baru (Website C, dst) | Satu entri config **dan** satu pemanggilan eksplisit di listener |
| Routing per cabang | **Dilepas di rev. 8.** Kalau dibutuhkan lagi, ini alasan pertama untuk membangun ulang tabel dinamis |

### Paling bernilai berikutnya: arah balik

Website B melaporkan hasil provisioning — `provisioning.succeeded` /
`provisioning.failed` beserta alasannya. Rancangan sudah dirinci di
`business-logic.md` bagian "Callback Hasil Provisioning", skema di
`database-schema.md` §3. Belum masuk `rencana-implementasi.md` sebagai fase resmi —
dua pertanyaan di §7 masih harus dijawab.

**Hanya berlaku untuk `transport=http_json`.** Telegram tidak punya cara
mengautentikasi diri ke kita per-request, jadi kanal itu terstruktur read-only.

### Butuh naik versi

- Perubahan bentuk payload apa pun.
- ODP jadi master data.

### Jangan dikembangkan

Memakai pesan Telegram sebagai pemicu tindakan otomatis — tidak bisa diverifikasi
tanda tangannya.

---

## 5. Rekomendasi terbuka: field `version` di payload

**Belum diterapkan.** Usul: tambahkan `"version": 1` di level atas payload sekarang,
saat belum ada satu konsumen pun — gratis sekarang, mahal setelah tiga mitra
terhubung.

---

## 6. Alternatif yang ditolak

| Ditolak | Kenapa |
|---|---|
| Trigger di tombol **Mulai Pemasangan** | `start()` hanya memindahkan status. SN dan ODP belum ada sama sekali |
| Trigger di **penyelesaian laporan** (`storeSpeedtest()`) | Terlalu belakang |
| **Dua event** (`started` + `completed`) | Begitu titik pemicu benar, event kedua tidak menambah apa pun — dihapus rev. 5 |
| **Menumpang event yang sudah ada** (`InstallationStarted`/`InstallationCompleted`) | Menautkan nasib webhook eksternal ke event internal dashboard FOP |
| **Klaim "nol edit controller"** | Terbukti salah — `storePemasangan()` tidak menyiarkan apa pun |
| Kolom **`secret_hash`** | HMAC menuntut secret bisa dibaca ulang. Jadi `secret_encrypted`, lalu sejak rev. 8 plaintext `.env` |
| **Satu baris outbox per percobaan** | Merakit ulang payload berisiko mengirim data yang sudah berubah, dibuang penerima sebagai duplikat |
| **Simpan payload hanya saat gagal** | Menghilangkan kemampuan menjawab "apa persisnya yang kalian kirim ke kami" |
| **Dua tabel outbox** (installation vs portal) | Mekanismenya identik sampai backoff. Satu tabel `webhook_outbox` |
| **Telegram Eksternal memakai `config('services.telegram.*')`** | Pesan pihak luar mendarat di grup internal |
| **Telegram menerima setiap penekanan Aktivasi** | Batas ~20 pesan/menit; membanjiri = pesan dibuang |
| **Memindahkan 6 pemanggilan Telegram Internal ke outbox** | Menyentuh empat modul sekaligus, task tersendiri |
| **Tabel `webhook_endpoints` + form admin dinamis** (rev. 1-7) | Ditolak rev. 8 — melayani konsumen yang belum ada |
| **`->afterCommit()` langsung dari `PendingDispatch`** | Di queue `sync`, mengeksekusi job inline saat `DB::commit()` — exception job bisa memicu `DB::rollBack()` palsu setelah commit sukses. Dibungkus try/catch eksplisit |
| **Properti `public bool $afterCommit`** di job | Bentrok trait `Queueable` yang sudah mendeklarasikannya beda tipe — fatal error komposisi class |
| **Raw SQL `SUBSTRING_INDEX`/`CAST AS UNSIGNED`** untuk hitung nomor aktivasi | MySQL-only, repo ini default sqlite. Dihitung di PHP |

---

## 7. Pertanyaan yang masih terbuka

| # | Pertanyaan | Kenapa tidak ditebak |
|---|---|---|
| 1 | Field `version` di payload ditambahkan sekarang? | Lihat §5. Gratis sekarang, mahal nanti |
| 2 | Endpoint rekonsiliasi masuk fase resmi atau menyusul? | Lihat §3. Belum ada yang memintanya |
| 3 | Hasil provisioning ditulis kemana — catatan teks di histori task, atau kolom status khusus di task? | Belum diputuskan. Log `installation_provisioning_callbacks` tetap sumber kebenaran |
| 4 | `status=failed` (bisnis) memicu notifikasi aktif ke teknisi (Telegram Internal), atau cukup tercatat pasif? | Belum diputuskan pemilik produk |

---

## 8. Gap ditemukan saat review

| Gap | Risiko | Status |
|---|---|---|
| `webhook_endpoints.url` tidak divalidasi terhadap rentang IP privat/loopback | SSRF | **Moot sejak rev. 8** — tidak ada lagi form isian URL, tujuan hardcode di `.env` |
