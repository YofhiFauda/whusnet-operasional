# Analisis Kelengkapan Migrasi: jetis_db → Project

**Akurasi Keseluruhan:** ~68% dari data teknis yang dibutuhkan sudah ter-cover

---

## DATA TEKNIS / PERANGKAT

| Field yang Dibutuhkan | SQL Source | Project (migration) | Status |
| :--- | :--- | :--- | :--- |
| **CID** | `pengguna.IDPENGGUNA` prefix | `customers.cid` | ✅ Ada, *nullable* saat putus |
| **IP Address (Dial up)** | `laporan.IPADDR` | `customer_technical_details.ip_address` | ✅ Migrasi OK |
| **SN Router/ONT** | `laporan.SNROOTER_FIBER` | `customer_technical_details.router_or_ont_serial` | ✅ Migrasi OK |
| **Perangkat Pasif** | `survey.ALATPASIF` (text bebas) | `customer_surveys` (text estimate saja) | ⚠️ Tidak terstruktur |
| **Nomer Cabang POP** | `cabang` table | `pops` (type=cabang) | ✅ Ada, lebih baik dari source |
| **Nomer Mini POP** | Tidak ada di source | `pops` (type=mini_pop) | ✅ Didesain lebih baik |
| **Nomor Distribusi** | `kode_kontrol_distribusi` | `distributions` + `customers.distribution_id` | ✅ Migrasi OK |
| **Nomor OLT** | `laporan.NOMOR_PORT_OLT` (gabung) | `customer_technical_details.olt_number` | ✅ Dipisah dengan benar |
| **Slot OLT** | Gabung di `NOMOR_PORT_OLT` (format "1/2/3") | `customer_technical_details.olt_slot` | ✅ Dipisah dengan benar |
| **Nomor Port OLT** | Gabung di `NOMOR_PORT_OLT` | `customer_technical_details.olt_port` | ✅ Dipisah dengan benar |
| **Nomer ODP** | `laporan.NOMOR_ODP` | `customer_technical_details.odp_number` | ✅ Migrasi OK |
| **Sinyal Pemasangan** | `laporan.SIGNAL_KABEL` | `customer_technical_details.fiber_signal` | ✅ Tapi single field |
| **Redaman Aktual** | Tidak ada di source | Tidak ada | ❌ BELUM ADA di keduanya |
| **VLAN** | Tidak ada di source laporan | `customer_technical_details.vlan` | ✅ Sudah ditambahkan |
| **Catatan Teknis** | `laporan.KETERANGAN` | `customer_technical_details.note` | ✅ Migrasi OK |

---

## LAPORAN UJI

| Field yang Dibutuhkan | SQL Source | Project | Status |
| :--- | :--- | :--- | :--- |
| **Tanggal Uji** | Tidak ada field khusus | Tidak ada | ❌ MISSING di keduanya |
| **Waktu Uji** | Tidak ada field khusus | Tidak ada | ❌ MISSING di keduanya |
| **Sinyal Redaman** | `laporan.SIGNAL_KABEL` | `fiber_signal` | ✅ OK |
| **Foto Speedtest** | `laporan.FOTOSPEED` | `speedtest_photo` | ✅ Migrasi OK |
| **Jitter** | Tidak ada di source | Tidak ada | ❌ BELUM ADA |
| **Latency** | `laporan.PINGGATEWAY` + `laporan.PINGGOOGLE` | Tidak dimigrasikan | ❌ HILANG DALAM MIGRASI |
| **Speed Upload** | `laporan.TESTUP` | `test_upload` | ✅ Migrasi OK |
| **Speed Download** | `laporan.TESTDOWN` | `test_download` | ✅ Migrasi OK |
| **Packet Loss %** | Tidak ada di source | Tidak ada | ❌ BELUM ADA |
| **% Sesuai Paket** | Tidak ada di source | Tidak ada | ❌ Perlu kalkulasi baru |
| **Skor Kualitas** | Tidak ada di source | Tidak ada | ❌ Fitur baru, belum ada |

---

## PEMBAYARAN & TAGIHAN

| Entitas | SQL Source | Project | Status |
| :--- | :--- | :--- | :--- |
| **Biaya Pasang** | `biaya_tagihan.BIAYAPASANG` | `customer_services` + `invoices` | ✅ Cukup |
| **Biaya Bulanan** | `biaya_tagihan.BIAYABULANAN` | `customer_services.monthly_price` | ✅ Migrasi OK |
| **Total Tagihan** | `penagihan.TOTALTAGIHAN` | `invoices.total_amount` | ✅ OK |
| **Tanggal Tagihan** | `penagihan.TGLPENAGIHAN` | `invoices.issue_date` + `due_date` | ✅ Lebih detail |
| **Status Tagihan** | `penagihan.STATUS` | `invoices.invoice_status` | ✅ OK |
| **Bukti Pembayaran** | `apikeuangan_buktitransaksitagihan` | `payments.proof_file` | ✅ OK |
| **Riwayat Pembayaran** | `penagihan` + `apikeuangan*` | `payments` table | ✅ OK |

**Akurasi Pembayaran/Tagihan:** ~95% — paling lengkap dari tiga area.

---

## Field HILANG dalam Migrasi (Ada di SQL Source, Tidak di Project)

### `laporan_pemasangan_wifi`
* `PINGGATEWAY` → Latency ke gateway (ms)
* `PINGGOOGLE` → Latency ke Google (ms)
* `BTS` → Nama BTS/tower pemancar
* `HISTORYROOTER`, `HISTORYANTENA`, `HISTORYDETAIL` → Riwayat perangkat lama

> ⚠️ **Kritis:** `PINGGATEWAY` / `PINGGOOGLE` merupakan data latency yang diminta user. Datanya tersedia di source lama tetapi belum dipetakan ke dalam migrasi project baru.

---

## Field BARU yang Diminta User (Belum Ada di Source Maupun Project)

Field berikut perlu dibuat dari nol karena tidak/belum tersimpan di database lama:

1.  `test_date` — Tanggal Uji
2.  `test_time` — Waktu Uji
3.  `jitter_ms` — Jitter
4.  `packet_loss_percent` — Packet Loss %
5.  `package_compliance_percent` — % Sesuai Paket *(Formula hitung: $\frac{\text{test\_speed}}{\text{package\_speed}} \times 100$)*
6.  `quality_score` — Skor Kualitas (baik input manual atau kalkulasi otomatis)
7.  `fiber_signal_actual` — Redaman Aktual (field `fiber_signal` yang ada saat ini hanya mencatat kondisi awal pemasangan saja)

---

## Rekomendasi Tindakan

### Prioritas Tinggi (Tambahkan ke `customer_technical_details` atau tabel terkait)
```sql
ALTER TABLE customer_technical_details 
ADD COLUMN ping_gateway_ms VARCHAR(20) NULL, -- Berasal dari PINGGATEWAY di source lama
ADD COLUMN ping_google_ms VARCHAR(20) NULL,  -- Berasal dari PINGGOOGLE di source lama
ADD COLUMN test_date DATE NULL,              -- Menyimpan tanggal uji baru
ADD COLUMN test_time TIME NULL,              -- Menyimpan waktu uji baru
ADD COLUMN jitter_ms VARCHAR(20) NULL,       -- Field baru penampung jitter
ADD COLUMN packet_loss_pct DECIMAL(5,2) NULL, -- Field baru penampung packet loss %
ADD COLUMN fiber_signal_actual VARCHAR(20) NULL; -- Redaman aktual saat ini (real-time/terbaru)
```

### Prioritas sedang:
- package_compliance_percent → bisa dikalkuld vs package speed
- quality_score → simpan sebagai integer (0-100)
- Memperbaiki no tagihan dan no pembayaran agar menghilangkan kata LEGACY Ketika migrasi dari data lama
- Memperbaiki tagihan awal, tagihan bulanan berdasarkan data lama yang dimana  Biaya diluar standart yang seharusnya hanya berlaku untuk pembayaran awal justru sekarang malah ikut bulanan, jadi  Biaya diluar standart biasannya buat pembelian materai sedangkan biaya bulanan hanya berdasarkan dari Harga paket
- Mempertegas pada backend atau frontend pada Halaman Tagihan, Pembayaran, Laporan, Detail Pelanggan sehingga Finance atau admin dapat mengetahui Ketika ingin audit

### Tidak perlu migrasi:
- HISTORYROOTER/ANTENA — data lama, tidak relevan workflow baru