# Panduan Integrasi — Webhook Pemasangan Whusnet

> **Dokumen ini untuk pihak luar (Website B).** Isinya cukup buat implementasi
> tanpa perlu akses ke sistem internal Whusnet. Kalau ada pertanyaan di luar
> yang dijawab di sini, hubungi tim Whusnet.

## Ringkasan

Whusnet Operasional mengirim **satu event**, `installation.activated`, ke URL
yang Anda sediakan, setiap kali teknisi menyelesaikan pemasangan di lapangan.
Anda **tidak perlu** memanggil sistem Whusnet untuk mengambil data — semua
data yang dibutuhkan sudah ada di dalam body request yang kami kirim.

**Yang Anda siapkan:**
1. Satu endpoint HTTPS yang menerima `POST` dan memverifikasi tanda tangan (lihat di bawah).
2. Kirim balik ke kami: URL endpoint tersebut + kabari kalau ada kendala saat menyepakati secret.

Kami akan memberi Anda secret (string acak) lewat jalur terpisah dari email —
jangan pernah bagikan secret ini di tempat lain.

---

## 0. Alur Integrasi End-to-End

Gambaran lengkap dari nol sampai jalan — baca ini dulu sebelum ke bagian teknis.

### Peta alur (siapa manggil siapa)

```
[Teknisi tekan "Aktivasi" di sistem Whusnet]
                │
                ▼
   [Whusnet (Pengirim)]  ────  POST + X-Whusnet-Signature  ────▶  [Website B (Penerima)]
                │                                                    │
                │◀─────────────────  200 OK  ───────────────────────┘
                │
     (gagal/timeout? retry otomatis: 1m → 5m → 30m → 2j → 6j, maks 8x)
```

**Whusnet selalu yang memulai koneksi** (bertindak sebagai *client*).
**Website B selalu yang menerima** (bertindak sebagai *server*). Arah ini
tetap — tidak pernah kebalik, tidak ada langkah di mana Anda memanggil balik
ke Whusnet untuk event ini.

### Yang dibutuhkan dari tiap sisi

| Dari Whusnet (sudah siap, sudah diuji) | Dari Website B (perlu disiapkan) |
|---|---|
| Kode pengirim: trigger event, susun payload, tanda tangani HMAC, antre & retry otomatis | Endpoint HTTPS publik yang menerima `POST` |
| Secret HMAC — kami generate, diserahkan lewat jalur terpisah dari email | Logic verifikasi signature (§3) |
| Payload terstruktur & konsisten (§5) | Logic simpan data + penanganan idempotensi (§4) |
| Retry otomatis kalau pengiriman gagal | Server yang hidup 24 jam — bukan mesin development yang sewaktu-waktu mati |

### Tahapan implementasi

**Tahap 1 — Kesepakatan** (sebelum Anda menulis kode apa pun)
- Kami kirim dokumen ini.
- Anda konfirmasi paham bentuk payload (§5) dan mekanisme verifikasi (§3).
- Kami generate secret HMAC, serahkan lewat jalur aman terpisah dari email
  (chat terenkripsi, diserahkan langsung, dll — bukan ditempel di badan email).

**Tahap 2 — Development di sisi Anda**
- Bangun endpoint mengikuti §2–§4, contoh kode §6 sebagai titik awal.
- Boleh pakai layanan seperti webhook.site di sisi Anda lebih dulu kalau mau
  lihat bentuk request asli sebelum menulis logic verifikasi — tapi contoh
  payload di §5 sudah representatif, jadi ini opsional.

**Tahap 3 — Uji bersama (staging)**
- Anda kasih kami URL endpoint yang **masih tahap uji** — boleh pakai tunnel
  (ngrok, Cloudflare Tunnel, dsb.) kalau server production belum siap.
- Kami arahkan pengiriman ke URL itu, trigger beberapa kali pakai data
  pelanggan uji.
- Anda cek: signature valid? data lengkap sesuai §5? kirim ulang data yang
  sama (simulasi retry) — pastikan tidak tercatat dobel (§4)?

**Tahap 4 — Go-live**
- Ganti URL ke endpoint production Anda yang permanen, kabari kami.
- Sejak titik ini, **setiap** Aktivasi pelanggan asli langsung masuk ke
  sistem Anda — tidak ada tombol "mulai" terpisah, begitu URL production
  terpasang di sisi kami, pengiriman langsung aktif.

### Pertanyaan yang sering muncul

**Kami perlu akses ke database Whusnet?**
Tidak. Semua data yang Anda butuhkan sudah ada di body request yang kami
kirim (§5) — endpoint Anda tidak pernah query balik ke sistem kami.

**Kami perlu polling/nanya ke Whusnet secara berkala?**
Tidak. Kami yang mendorong data begitu ada kejadian (tombol Aktivasi
ditekan) — endpoint Anda cukup menunggu request masuk, murni pasif.

**Bagaimana kalau server kami mati sebentar?**
Kami retry otomatis sampai 8 kali selama rentang 6 jam ke depan (§2). Selama
server Anda hidup lagi dalam rentang itu, data tetap sampai — tidak hilang.

**Bisa kami yang tentukan format datanya?**
Tidak — bentuk payload (§5) sudah baku dari sisi kami, itu bagian dari
kontrak yang kami desain. Kalau ada field yang kurang buat kebutuhan Anda,
sampaikan ke kami; itu jadi perubahan versi payload baru, bukan sesuatu yang
Anda ubah sendiri di sisi penerima.

**Kenapa bukan kami yang bikin URL-nya di server Whusnet?**
Karena Whusnet yang mengirim (client), Anda yang menerima (server) — secara
teknis, alamat tujuan sebuah kiriman HTTP harus ada di server pihak yang
menerima. Analoginya: Anda yang punya nomor telepon dan menentukan siapa yang
boleh menelepon (verifikasi signature = caller ID); Whusnet menyimpan nomor
itu di kontak kami lalu menelepon begitu tombol ditekan. Nomor teleponnya
secara fisik ada di tangan Anda — bukan sesuatu yang bisa kami buatkan dari
sisi kami.

---

## 1. Kapan dikirim

Sekali setiap kali teknisi menekan tombol "Aktivasi" setelah data pemasangan
(SN, ODP, foto) tersimpan. **Bisa terkirim lebih dari sekali** untuk pelanggan
yang sama — teknisi bisa meralat data (SN salah ketik, dsb.) dan menekan
Aktivasi lagi. Setiap penekanan adalah pengiriman yang sah dan harus diproses
(lihat §4, Idempotensi).

## 2. Cara mengirim

- Method: `POST`
- Content-Type: `application/json`
- Header wajib: `X-Whusnet-Signature: t=<unix_timestamp>,v1=<hex_hmac_sha256>`
- Sukses = respons `2xx` dalam waktu wajar (idealnya < 5 detik — proses berat
  lakukan di background setelah membalas 200, bukan sebelum membalas).
- Gagal (bukan 2xx, atau timeout) = kami retry otomatis dengan jeda:
  1 menit → 5 menit → 30 menit → 2 jam → 6 jam, maksimal 8 kali percobaan.

## 3. Verifikasi tanda tangan (WAJIB)

Header `X-Whusnet-Signature` berisi dua bagian dipisah koma: `t` (timestamp
Unix saat dikirim) dan `v1` (HMAC-SHA256 hex).

**Langkah verifikasi, urut:**

1. Parse `t` dan `v1` dari header.
2. **Tolak** kalau `|waktu_sekarang - t| > 300` detik (5 menit) — mencegah replay.
3. Hitung ulang: `HMAC_SHA256(secret, "{t}.{raw_body}")` — **`raw_body` HARUS
   body mentah persis seperti diterima**, BUKAN hasil `json_decode` lalu
   `json_encode` ulang. Urutan key JSON bisa berubah kalau di-serialize ulang,
   dan signature akan gagal cocok tanpa sebab yang kelihatan.
4. Bandingkan hasil hitung dengan `v1` pakai **constant-time compare**
   (`hash_equals` di PHP, `crypto.timingSafeEqual` di Node, `hmac.compare_digest`
   di Python) — jangan `==`/`===` biasa.
5. Kalau gagal di langkah manapun → balas `401`, jangan proses body-nya.

## 4. Idempotensi — WAJIB dipahami sebelum implementasi

Ada dua kunci berbeda tujuan di payload:

| Kunci | Berubah kapan | Untuk apa |
|---|---|---|
| `event_id` | Tetap sama di semua percobaan ulang pengiriman event yang sama | Buang duplikat akibat retry jaringan — kalau Anda sudah pernah proses `event_id` ini sukses, cukup balas `200` lagi, jangan proses ulang |
| `idempotency_key` | Berubah tiap kali Aktivasi ditekan (`...activation:1`, `...activation:2`, dst) | Kenali bahwa event ini state TERBARU untuk pemasangan itu |

**Aturan pemrosesan:** simpan data sebagai **upsert** berdasarkan pelanggan,
bukan insert baru tiap kali. Kalau nomor `activation` yang baru datang **lebih
kecil** dari yang terakhir Anda proses (bisa terjadi karena urutan retry tidak
terjamin), **abaikan** — jangan menimpa data yang lebih baru dengan yang lebih
lama.

## 5. Contoh payload lengkap

```json
{
  "event": "installation.activated",
  "event_id": "0f4a9b2e-7c31-4d5a-9f10-2b8e6c5a1d33",
  "idempotency_key": "installation:8842:activation:1",
  "occurred_at": "2026-08-20T14:32:07+07:00",
  "data": {
    "customer": {
      "cid": "C1X4ARQ000631",
      "nama": "Masudah Yuni Fitri"
    },
    "pop": { "code": "PNR-JTS", "name": "Jetis", "type": "cabang" },
    "desa": { "id": 3517, "name": "Joresan", "kecamatan": "Mlarak", "kota": "Kabupaten Ponorogo" },
    "paket": { "code": "PKT-20M", "name": "Home 20 Mbps", "bandwidth": "20 Mbps", "harga_bulanan": "150000.00" },
    "perangkat": {
      "sn": "ZTEGC1234567",
      "odp": "ODP-JTS-04",
      "odp_port": "3",
      "olt": "OLT01/1/3",
      "vlan": "120"
    },
    "task": {
      "number": "TASK-2026-0184",
      "started_at": "2026-08-18T09:12:00+07:00"
    }
  }
}
```

### Deskripsi field

| Field | Tipe | Nullable | Catatan |
|---|---|---|---|
| `event` | string | tidak | selalu `"installation.activated"` |
| `event_id` | string (UUID) | tidak | unik per percobaan pengiriman event yang sama |
| `idempotency_key` | string | tidak | lihat §4 |
| `occurred_at` | string (ISO-8601) | tidak | waktu event terjadi di sisi Whusnet |
| `data.customer.cid` | string | **ya** | nullable untuk pelanggan yang belum aktif penuh |
| `data.customer.nama` | string | tidak | |
| `data.pop.*` | object | tidak | cabang Whusnet yang menangani pelanggan |
| `data.desa.*` | object | boleh field kosong | mengikuti kelengkapan data alamat pelanggan |
| `data.paket.harga_bulanan` | **string desimal** | tidak | `"150000.00"` — **JANGAN** parse sebagai float/number, parse sebagai decimal/string. Kesalahan pembulatan pernah jadi masalah nyata di sistem kami |
| `data.perangkat.sn` / `.odp` / `.odp_port` / `.olt` / `.vlan` | string | **ya**, salah satu bisa kosong | data perangkat hasil pemasangan lapangan |
| `data.task.number` | string | tidak (kalau `data.task` ada) | |
| `data.task.started_at` | string (ISO-8601) | boleh null | |
| `data.task` | object | **ya, seluruh object bisa `null`** | jika task tidak ditemukan di sisi kami |

**Field yang TIDAK PERNAH ada di payload ini** (jangan diasumsikan/ditunggu):
`task.completed_at` (task belum selesai di titik ini — itu memang benar,
bukan bug), `login_id` (menyusul versi payload berikutnya begitu portal
pelanggan jadi), nomor HP, alamat lengkap, NIK, koordinat, kredensial perangkat
(`pppoe_password`, `wifi_password`).

## 6. Contoh kode penerima (receiver)

Ganti `WHUSNET_WEBHOOK_SECRET` dengan secret yang kami berikan. Semua contoh
di bawah: verifikasi dulu, baru proses.

### PHP (framework apa saja — baca raw body langsung)

```php
<?php
$rawBody = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_X_WHUSNET_SIGNATURE'] ?? '';

if (!preg_match('/^t=(\d+),v1=([0-9a-f]+)$/', $sigHeader, $m)) {
    http_response_code(401);
    exit;
}
[$t, $signature] = [$m[1], $m[2]];

if (abs(time() - (int) $t) > 300) {
    http_response_code(401); // timestamp kedaluwarsa
    exit;
}

$secret = getenv('WHUSNET_WEBHOOK_SECRET');
$expected = hash_hmac('sha256', "{$t}.{$rawBody}", $secret);

if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    exit;
}

$payload = json_decode($rawBody, true);
// TODO: upsert data berdasarkan payload['idempotency_key']
//       lihat §4 sebelum menulis logic ini.

http_response_code(200);
echo json_encode(['received' => true]);
```

### Node.js (Express)

```js
const express = require('express');
const crypto = require('crypto');
const app = express();

app.post(
  '/webhooks/whusnet-installation',
  express.raw({ type: 'application/json' }), // WAJIB raw, bukan express.json()
  (req, res) => {
    const sigHeader = req.header('X-Whusnet-Signature') || '';
    const m = sigHeader.match(/^t=(\d+),v1=([0-9a-f]+)$/);
    if (!m) return res.status(401).end();

    const [, t, signature] = m;
    if (Math.abs(Date.now() / 1000 - Number(t)) > 300) {
      return res.status(401).end(); // timestamp kedaluwarsa
    }

    const rawBody = req.body; // Buffer
    const expected = crypto
      .createHmac('sha256', process.env.WHUSNET_WEBHOOK_SECRET)
      .update(`${t}.`)
      .update(rawBody)
      .digest('hex');

    const sigBuf = Buffer.from(signature, 'utf8');
    const expBuf = Buffer.from(expected, 'utf8');
    const valid =
      sigBuf.length === expBuf.length &&
      crypto.timingSafeEqual(sigBuf, expBuf);

    if (!valid) return res.status(401).end();

    const payload = JSON.parse(rawBody.toString('utf8'));
    // TODO: upsert data berdasarkan payload.idempotency_key — lihat §4.

    res.status(200).json({ received: true });
  }
);

app.listen(3000);
```

### Python (Flask)

```python
import hashlib
import hmac
import json
import os
import time

from flask import Flask, request, jsonify

app = Flask(__name__)


@app.post("/webhooks/whusnet-installation")
def receive_installation_webhook():
    sig_header = request.headers.get("X-Whusnet-Signature", "")
    try:
        t_str, v1_str = sig_header.split(",")
        t = t_str.split("=", 1)[1]
        v1 = v1_str.split("=", 1)[1]
    except (ValueError, IndexError):
        return "", 401

    if abs(time.time() - int(t)) > 300:
        return "", 401  # timestamp kedaluwarsa

    raw_body = request.get_data()  # bytes mentah, BUKAN request.json
    secret = os.environ["WHUSNET_WEBHOOK_SECRET"].encode("utf-8")
    message = f"{t}.".encode("utf-8") + raw_body
    expected = hmac.new(secret, message, hashlib.sha256).hexdigest()

    if not hmac.compare_digest(expected, v1):
        return "", 401

    payload = json.loads(raw_body)
    # TODO: upsert data berdasarkan payload["idempotency_key"] — lihat §4.

    return jsonify(received=True), 200
```

## 7. Checklist sebelum go-live

- [ ] Endpoint bisa diakses dari internet lewat `https://` (bukan `http://` —
      kami menolak mengirim ke URL non-HTTPS).
- [ ] Verifikasi signature sudah diimplementasi persis §3 (raw body, bukan
      re-serialize; constant-time compare).
- [ ] Endpoint membalas `2xx` dengan cepat, proses berat dipindah ke
      background job di sisi Anda.
- [ ] Logic penyimpanan data sudah menangani `event_id` (dedupe) dan
      `idempotency_key` (upsert, nomor lebih rendah diabaikan) — lihat §4.
- [ ] Sudah dites kirim payload contoh (§5) secara manual dan berhasil diproses.
- [ ] URL endpoint final + konfirmasi secret sudah dikirim balik ke tim Whusnet.

## 8. Kontak

Kendala teknis atau pertanyaan di luar cakupan dokumen ini: hubungi tim
Whusnet Operasional lewat jalur yang sudah disepakati.
