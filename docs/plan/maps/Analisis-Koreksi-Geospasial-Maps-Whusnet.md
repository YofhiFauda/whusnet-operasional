# Analisis & Koreksi: Rencana Geospasial Maps — Whusnet Operasional

---

## Ringkasan Eksekutif

Dokumen rencana asli sudah sangat solid secara arsitektur — terutama fondasi data (Fase 0), pemisahan entry point JS, dan keputusan `preferCanvas`. Namun ada **4 gap kritis** dan **1 peluang besar yang terlewat**: mapping otomatis dari data pemasangan pelanggan.

---

## Bagian 1 — Koreksi & Penguatan Rencana Asli

### 1.1 Gap Kritis: Server & OLT Tidak Punya Entitas

**Masalah:** Rencana Fase 1 hanya membuat tabel `network_assets` untuk ODC dan ODP. OLT dan Server (backbone) disebutkan di bagian skalabilitas tapi **tidak ada entitas eksplisit** dan tidak masuk migrasi.

**Koreksi:**

Tabel `network_assets` sudah punya kolom `type` (enum), jadi tinggal perluas enum-nya:

```php
// app/Enums/NetworkAssetType.php
enum NetworkAssetType: string
{
    case SERVER  = 'server';   // ← TAMBAH: titik upstream/backbone
    case OLT     = 'olt';      // ← TAMBAH
    case ODC     = 'odc';
    case ODP     = 'odp';
    case HUB     = 'hub';      // ← TAMBAH: untuk topologi HFC/hybrid
    case SPLITTER = 'splitter'; // ← opsional, sesuai topologi lapangan
}
```

Hirarki parent-child yang sudah ada (`parent_id` self-ref) cukup menampung:

```
SERVER → OLT → ODC → ODP → (pelanggan)
```

**Yang harus ditambah di migrasi:**

```php
$table->string('brand')->nullable();       // Huawei, ZTE, Nokia
$table->string('model')->nullable();       // MA5800, C600, dll
$table->string('serial_number')->nullable();
$table->integer('total_ports')->nullable(); // untuk OLT: jumlah slot/port PON
$table->integer('used_ports')->virtualAs(  // computed, bukan stored counter
    '(SELECT COUNT(*) FROM network_assets children WHERE children.parent_id = id)'
)->nullable();
```

---

### 1.2 Koreksi: Modem Tidak Masuk `network_assets`

**Masalah:** Dokumen asli tidak menyebut modem/ONT/CPE sama sekali, padahal ini **aset yang paling sering bermasalah** di ISP dan paling perlu dilacak lokasi + statusnya.

**Koreksi:**

Modem/ONT bukan infrastruktur jaringan pasif — ini adalah **aset customer-facing** yang lokasi fisiknya = lokasi pelanggan. Jangan masukkan ke `network_assets`. Buat tabel terpisah:

```php
// Migrasi: create_customer_devices_table
Schema::create('customer_devices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained();
    $table->foreignId('network_asset_id')->nullable()->constrained(); // ODP upstream-nya
    $table->enum('type', ['ont', 'onu', 'modem', 'router', 'stb']);
    $table->string('brand')->nullable();
    $table->string('model')->nullable();
    $table->string('serial_number')->nullable()->unique();
    $table->string('mac_address')->nullable();
    $table->string('pon_port')->nullable(); // mis. "0/1/0:3" untuk gpon
    $table->enum('status', ['active', 'inactive', 'faulty', 'returned']);
    $table->timestamps();
});
```

**Benefit:** Saat ada tiket gangguan, NOC bisa langsung lihat: pelanggan ini pakai ONT model apa, di port berapa, upstream ODP mana → tahu scope gangguan tanpa telepon teknisi.

---

### 1.3 Koreksi: `Hub` Perlu Dibedakan dari ODP

**Masalah:** Dokumen asli hanya menyebut "hub" di judul tapi tidak ada penjelasan apakah hub = junction box pasif, hub Ethernet aktif, atau distribution point lain.

**Koreksi:**

Di topologi GPON murni, tidak ada "hub". Tapi di banyak ISP daerah yang pakai **topologi hybrid** (fiber backbone + distribusi ethernet/coax terakhir), hub adalah **active ethernet switch** yang ada di antara ODP dan pelanggan.

Rekomendasi: tambah `hub` ke enum `NetworkAssetType` dengan kolom tambahan:

```php
$table->boolean('is_active_device')->default(false); 
// true = OLT/Hub (butuh power, monitoring), false = ODC/ODP (pasif)
$table->string('ip_address')->nullable();    // untuk device aktif
$table->string('uplink_port')->nullable();   // port yang ke upstream
```

Ini juga membuka pintu integrasi SNMP monitoring di masa depan.

---

### 1.4 Koreksi: Garis Penghubung Harus Punya Tipe

**Masalah:** Fase 3 menyebutkan "garis penghubung parent-child sebagai polyline sederhana — bukan rute kabel sebenarnya". Ini benar, tapi tidak ada mekanisme untuk menyimpan **path kabel nyata** jika suatu saat tersedia.

**Koreksi — tambah tabel `network_links` opsional:**

```php
Schema::create('network_links', function (Blueprint $table) {
    $table->id();
    $table->foreignId('from_asset_id')->constrained('network_assets');
    $table->foreignId('to_asset_id')->constrained('network_assets');
    $table->enum('link_type', ['logical', 'physical']); 
    // logical = garis lurus (fase awal), physical = koordinat kabel nyata
    $table->json('waypoints')->nullable(); 
    // [[lat,lng],[lat,lng],...] untuk physical — isi nanti dari survey kabel
    $table->decimal('length_meters', 10, 2)->nullable();
    $table->string('cable_type')->nullable(); // SM, MM, G652D, dll
    $table->timestamps();
});
```

Untuk Fase 3 sekarang: render semua link sebagai `logical`. Tabel sudah siap saat tim survey kabel ingin input jalur nyata.

---

### 1.5 Koreksi Minor: Risiko Koordinat Redundan Perlu Timeline Penghapusan

**Masalah:** Dokumen asli menyebutkan `customers.latitude/longitude` harus jadi "kolom legacy read-only" tapi tidak ada timeline penghapusan atau mekanisme deprecation yang jelas.

**Koreksi:**

Tambahkan kolom `_deprecated_at` atau komentar migrasi yang eksplisit:

```php
// Di migrasi backfill (Fase 0.2):
// Setelah backfill selesai, tandai kolom legacy:
$table->timestamp('coord_migrated_at')->nullable(); // di tabel customers
// Baru hapus di Sprint 11+ setelah konfirmasi semua view sudah pakai accessor
```

---

## Bagian 2 — PELUANG BESAR: Mapping Otomatis dari Data Pemasangan Pelanggan

> **Pertanyaan inti:** Apakah bisa mapping otomatis berdasarkan data Pemasangan Pelanggan?

**Jawaban singkat: YA, dan ini justru cara paling realistis untuk mengisi data koordinat ODP yang menjadi bottleneck utama.**

### 2.1 Mengapa Ini Kritis

Dokumen asli menyebutkan bahwa pengisian koordinat ODP adalah "kerja lapangan manual: ratusan ODP harus dikunjungi & dipin" dan bisa "berbulan-bulan". Ini adalah hambatan terbesar seluruh proyek peta.

Namun **data yang dibutuhkan sudah ada**: setiap pelanggan yang terpasang sudah punya:
- Koordinat lokasi (dari teknisi saat instalasi atau survey)
- Nama ODP yang melayani mereka (`odp_code` / `nearest_odp`)

Artinya: **lokasi ODP bisa diaproksimasi dari rata-rata/median koordinat pelanggan yang terhubung ke ODP tersebut.**

---

### 2.2 Arsitektur: Auto-Mapping Pipeline

```
Data Pemasangan Pelanggan
        │
        ▼
[Fase A] geo:infer-odp-location
        │  GROUP BY odp_code
        │  → hitung centroid dari koordinat pelanggan
        │  → hitung radius sebaran (standar deviasi)
        │  → hitung confidence score
        ▼
network_assets (ODP)
        │  lat/lng terisi otomatis dengan flag: 'inferred'
        │
        ▼
[Fase B] Validasi Lapangan (teknisi)
        │  Teknisi datang ke lokasi, geser pin ke posisi tepat
        │  Flag berubah: 'inferred' → 'verified'
        ▼
Peta Aset Akurat
```

---

### 2.3 Implementasi Konkret

#### Command `geo:infer-odp-locations`

```php
// app/Console/Commands/GeoInferOdpLocationsCommand.php

class GeoInferOdpLocationsCommand extends Command
{
    protected $signature = 'geo:infer-odp-locations 
                            {--pop= : Filter per POP ID}
                            {--min-customers=3 : Minimum pelanggan untuk inferensi}
                            {--dry-run : Preview tanpa menyimpan}';

    public function handle(): int
    {
        // 1. Kumpulkan semua ODP yang belum punya koordinat terverifikasi
        $odpsWithoutCoords = NetworkAsset::where('type', NetworkAssetType::ODP)
            ->whereNull('verified_at')  // belum diverifikasi lapangan
            ->whereNull('latitude')     // belum punya koordinat sama sekali
            ->orWhere('coord_source', 'inferred') // atau sudah inferred tapi belum verified
            ->get();

        foreach ($odpsWithoutCoords as $odp) {
            // 2. Cari semua pelanggan yang terhubung ke ODP ini
            $customers = Customer::whereHas('address', fn($q) => 
                    $q->whereNotNull('latitude')->whereNotNull('longitude')
                )
                ->where(function($q) use ($odp) {
                    $q->where('odp_code', $odp->code)
                      ->orWhereHas('technicalDetail', fn($q2) => 
                          $q2->where('odp_number', $odp->code)
                      )
                      ->orWhere('network_asset_id', $odp->id);
                })
                ->get();

            if ($customers->count() < $this->option('min-customers')) {
                $this->warn("ODP {$odp->code}: hanya {$customers->count()} pelanggan, skip.");
                continue;
            }

            // 3. Hitung centroid & confidence
            $result = $this->calculateCentroid($customers);
            
            $this->line(sprintf(
                "ODP %-10s → lat: %s, lng: %s | radius: %dm | confidence: %s%% | dari %d pelanggan",
                $odp->code,
                $result['lat'],
                $result['lng'],
                $result['radius_meters'],
                $result['confidence'],
                $customers->count()
            ));

            if (!$this->option('dry-run')) {
                $odp->update([
                    'latitude'      => $result['lat'],
                    'longitude'     => $result['lng'],
                    'coord_source'  => 'inferred',
                    'coord_confidence' => $result['confidence'],
                    'coord_radius_m'   => $result['radius_meters'],
                    'inferred_from_count' => $customers->count(),
                ]);
            }
        }

        return 0;
    }

    private function calculateCentroid(Collection $customers): array
    {
        $coords = $customers->map(fn($c) => [
            'lat' => (float) $c->address->latitude,
            'lng' => (float) $c->address->longitude,
        ]);

        $avgLat = $coords->avg('lat');
        $avgLng = $coords->avg('lng');

        // Hitung radius sebaran (jarak rata-rata dari centroid)
        $distances = $coords->map(fn($c) => 
            $this->haversineMeters($avgLat, $avgLng, $c['lat'], $c['lng'])
        );
        
        $avgRadius  = $distances->avg();
        $maxRadius  = $distances->max();

        // Confidence: makin kecil radius sebaran → makin yakin posisi ODP
        // ODP GPON idealnya radius pelanggan < 500m (fiber drop ±100-300m)
        $confidence = match(true) {
            $maxRadius <= 100  => 95,
            $maxRadius <= 200  => 85,
            $maxRadius <= 500  => 70,
            $maxRadius <= 1000 => 50,
            default            => 25, // sebaran terlalu jauh, mungkin data salah ODP
        };

        return [
            'lat'           => round($avgLat, 7),
            'lng'           => round($avgLng, 7),
            'radius_meters' => (int) $avgRadius,
            'confidence'    => $confidence,
        ];
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371000;
        $φ1 = deg2rad($lat1); $φ2 = deg2rad($lat2);
        $Δφ = deg2rad($lat2 - $lat1);
        $Δλ = deg2rad($lng2 - $lng1);
        $a = sin($Δφ/2)**2 + cos($φ1)*cos($φ2)*sin($Δλ/2)**2;
        return $R * 2 * atan2(sqrt($a), sqrt(1-$a));
    }
}
```

#### Kolom Tambahan di Migrasi `network_assets`

```php
// Tambahkan di migrasi Fase 1 atau buat migrasi baru
$table->enum('coord_source', ['manual', 'inferred', 'verified'])->default('manual')->nullable();
$table->unsignedTinyInteger('coord_confidence')->nullable(); // 0-100
$table->unsignedInteger('coord_radius_m')->nullable();       // radius sebaran pelanggan
$table->unsignedInteger('inferred_from_count')->nullable();  // jumlah pelanggan sumber
$table->timestamp('verified_at')->nullable();                // diisi saat teknisi konfirmasi
$table->foreignId('verified_by')->nullable()->constrained('users');
```

---

### 2.4 Auto-Update: Event-Driven Inference

Selain command batch, buat otomatis **saat ada instalasi baru**:

```php
// app/Observers/CustomerObserver.php

class CustomerObserver
{
    public function updated(Customer $customer): void
    {
        // Jika koordinat berubah DAN customer punya ODP terhubung
        if ($customer->isDirty(['network_asset_id']) || 
            $customer->address?->isDirty(['latitude', 'longitude'])) {
            
            if ($customer->network_asset_id) {
                UpdateOdpInferredLocation::dispatch($customer->network_asset_id)
                    ->delay(now()->addMinutes(5)); // batch delay, hindari spam job
            }
        }
    }
}

// app/Jobs/UpdateOdpInferredLocation.php
// Job ini re-run kalkulasi centroid untuk 1 ODP setelah ada perubahan pelanggan
// Hanya update kalau coord_source masih 'inferred' (tidak override verified)
```

---

### 2.5 UI: Visualisasi Confidence di Peta

Di peta aset (Fase 3), tampilkan marker ODP dengan warna berbeda berdasarkan `coord_source`:

| Status | Warna Marker | Arti |
|---|---|---|
| `verified` | Hijau solid | Koordinat sudah dikonfirmasi lapangan |
| `inferred` confidence > 80% | Kuning | Estimasi akurat, prioritas rendah untuk verifikasi |
| `inferred` confidence 50-80% | Oranye | Estimasi cukup, perlu verifikasi saat ada kunjungan |
| `inferred` confidence < 50% | Merah | Estimasi buruk (sebaran pelanggan terlalu jauh) |
| `null` (tidak ada data) | Abu-abu | Sama sekali belum ada data |

```javascript
// Di map.js — fungsi warna marker ODP
function odpMarkerColor(feature) {
    const src = feature.properties.coord_source;
    const conf = feature.properties.coord_confidence;
    
    if (src === 'verified')           return '#22c55e'; // green-500
    if (src === 'inferred') {
        if (conf >= 80)               return '#eab308'; // yellow-500
        if (conf >= 50)               return '#f97316'; // orange-500
        return '#ef4444';                                // red-500
    }
    return '#6b7280'; // gray-500
}
```

**Popup ODP** juga tampilkan info inference:

```
📡 ODP-JTS-045
Status: Estimasi otomatis (confidence 78%)
Dari: 12 pelanggan, radius ±185m
⚠️ Belum diverifikasi lapangan
[Verifikasi Sekarang] [Lihat Pelanggan]
```

---

### 2.6 Integrasi dengan GPS Check-in Teknisi (Fase 5)

Ini adalah **multiplier terbesar**: saat teknisi check-in di task instalasi pelanggan baru, sistem bisa langsung:

1. Catat koordinat teknisi sebagai koordinat pelanggan baru (sudah di plan Fase 5)
2. **TAMBAHAN:** Jika pelanggan punya `network_asset_id` (ODP), trigger `UpdateOdpInferredLocation`
3. Jika koordinat teknisi **lebih dekat ke ODP yang sudah `verified`** dibanding lokasi pelanggan yang tersimpan → anggap koordinat pelanggan yang perlu dikoreksi, bukan ODP

```php
// Di TaskService::start() — setelah menyimpan koordinat check-in:
if ($task->customer?->network_asset_id && $checkInCoordinate) {
    // Re-infer lokasi ODP dengan data terbaru
    UpdateOdpInferredLocation::dispatch($task->customer->network_asset_id);
    
    // Cek apakah koordinat pelanggan perlu dikoreksi
    $coordDiff = haversineMeters(
        $checkInCoordinate->lat, $checkInCoordinate->lng,
        $task->customer->address->latitude, 
        $task->customer->address->longitude
    );
    
    if ($coordDiff > 200) { // 200m threshold sudah ada di plan asli
        // Tandai koordinat pelanggan perlu review
        $task->customer->address->update(['coord_needs_review' => true]);
        // Simpan posisi teknisi sebagai kandidat koreksi
        $task->customer->address->update([
            'checkin_suggested_lat' => $checkInCoordinate->lat,
            'checkin_suggested_lng' => $checkInCoordinate->lng,
        ]);
    }
}
```

---

### 2.7 Dashboard Verifikasi ODP

Tambahkan view khusus supervisor lapangan:

```
/master/network-asset/verify-queue

Antrian Verifikasi ODP (berdasarkan wilayah teknisi)

Filter: [Semua | Confidence < 50% | Belum sama sekali]

┌─────────────┬──────────┬──────────────┬──────────┬──────────┐
│ ODP         │ Confidence │ Pelanggan  │ Radius   │ Aksi     │
├─────────────┼──────────┼──────────────┼──────────┼──────────┤
│ ODP-JTS-045 │ 78%      │ 12           │ ±185m    │ [Verif]  │
│ ODP-JTS-112 │ 42%      │ 3            │ ±890m    │ [Verif]  │
│ ODP-JTS-033 │ 95%      │ 24           │ ±45m     │ [Verif]  │
└─────────────┴──────────┴──────────────┴──────────┴──────────┘

Tip: Teknisi bisa verifikasi sambil jalan — tap ODP terdekat saat lewat area.
```

Teknisi verifikasi langsung dari HP: buka ODP di peta → "Saya di sini sekarang" → pin bergeser ke posisi teknisi → status `verified`.

---

## Bagian 3 — Revisi Roadmap (Urutan Fase yang Disempurnakan)

### Perubahan dari Dokumen Asli

| Fase Asli | Perubahan | Alasan |
|---|---|---|
| Fase 0 | Tetap (wajib gerbang) | — |
| Fase 1 | + enum `server, olt, hub` + tabel `customer_devices` + tabel `network_links` + kolom inference | Completeness aset |
| Fase 2 | Tetap | — |
| **Fase 2.5 (BARU)** | `geo:infer-odp-locations` + observer + jobs | Isi data ODP otomatis, sebelum peta dibuka |
| Fase 3 | + warna confidence di marker + popup inference info | Data inference sudah tersedia |
| Fase 4 | Tetap | — |
| Fase 5 | + trigger re-inference ODP dari check-in | Multiplier efek GPS check-in |

---

### Fase 2.5 — Auto-Inference (BARU, antara Fase 2 dan 3)

**Durasi estimasi:** 3-4 hari kerja  
**Dependencies:** Fase 1 (tabel `network_assets` + relasi `customers.network_asset_id`)

Pekerjaan:
- [ ] Migrasi: tambah kolom inference ke `network_assets`
- [ ] `geo:seed-odp-from-legacy` sudah di Fase 1 → jalankan duluan
- [ ] Command `geo:infer-odp-locations` dengan `--dry-run` → review output
- [ ] `CustomerObserver` + job `UpdateOdpInferredLocation`
- [ ] View `/master/network-asset/verify-queue`
- [ ] Test: `OdpInferenceCommandTest`, `CustomerObserverOdpUpdateTest`

---

## Bagian 4 — Checklist Koreksi Prioritas Tinggi

### Wajib Sebelum Fase 1 Dimulai

- [ ] Perluas enum `NetworkAssetType` dengan `server`, `olt`, `hub`
- [ ] Tambah kolom `is_active_device`, `ip_address` di migrasi `network_assets`
- [ ] Buat tabel `customer_devices` untuk modem/ONT
- [ ] Buat tabel `network_links` (logical dulu, physical nanti)
- [ ] Tambah kolom `coord_source`, `coord_confidence`, `coord_radius_m`, `verified_at`

### Wajib Sebelum Fase 3 Dimulai

- [ ] Jalankan `geo:infer-odp-locations --dry-run` → review hasilnya
- [ ] Konfirmasi threshold confidence (100m/200m/500m) sesuai topologi lapangan
- [ ] Buat `CustomerObserver` + job inference

### Wajib Sebelum Go-Live Peta

- [ ] Semua endpoint GeoJSON punya `applyUserScope()` — tidak ada kebocoran lintas POP
- [ ] `throttle` aktif di semua endpoint geo
- [ ] Test: login sebagai `pop_admin` → `/api/geo/network` → nol titik dari POP lain
- [ ] `maxBounds` dikunci ke wilayah operasional (Jawa Timur / per region)

---

## Bagian 5 — Hal yang Dipertahankan dari Dokumen Asli (Tidak Diubah)

Bagian berikut dari dokumen asli sudah **benar dan tidak perlu dikoreksi**:

- ✅ Fase 0 sebagai gerbang wajib — keputusan arsitektur terbaik
- ✅ Leaflet + OSM — pilihan tepat untuk skala ~10 user internal
- ✅ `preferCanvas: true` + `L.circleMarker` — benar, DOM O(1) bukan O(n)
- ✅ Entry point JS terpisah (`map.js`) — penting, jangan digabung ke `app.js`
- ✅ Bbox query + cap 500 titik + fallback agregat grid — sudah optimal
- ✅ GPS check-in tidak wajib (tidak boleh blokir "Mulai Task") — keputusan produk tepat
- ✅ Geocoding Nominatim untuk alamat desa — benar ditolak, akurasi memang buruk
- ✅ Panel peta dikecualikan dari `refreshDashboardContainers()` — wajib, sudah benar
- ✅ Tidak pakai PostGIS/SPATIAL INDEX sekarang — benar, sqlite test suite akan pecah
- ✅ `haversine` di PHP untuk "ODP terdekat" — cukup untuk ≤ ratusan kandidat

---

## Penutup

Dokumen asli sudah sangat matang. Tiga hal yang paling perlu ditambahkan:

1. **Lengkapi entitas aset:** Server, OLT, Hub, Modem/ONT harus masuk skema — kalau tidak, peta akan "bolong" di lapisan backbone dan lapisan customer-edge.

2. **Jalankan auto-inference sebelum Fase 3** — tanpa ini, peta ODP akan kosong berbulan-bulan dan investasi fitur sia-sia. Data pemasangan pelanggan yang sudah ada adalah tambang emas yang belum ditambang.

3. **Buat `network_links`** — polylne "garis lurus" saja sudah cukup untuk sekarang, tapi struktur data harus siap menerima jalur kabel fisik tanpa migrasi ulang di masa depan.
