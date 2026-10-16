# Peta Infrastruktur ISP

Halaman untuk mencatat perangkat jaringan ISP beserta kapasitas dan slot kosongnya, lalu menampilkannya sebagai topologi dari pusat sampai ONT pelanggan.

File HTML-nya mandiri (`peta-infrastruktur-isp.html`). CSS dan JavaScript sudah ada di dalamnya, cukup dibuka di browser. Satu-satunya sumber luar adalah font Google Fonts, dan bila tidak termuat, halaman memakai font sistem.

---

## 1. Hierarki perangkat

Setiap perangkat adalah satu node dengan satu induk. Urutan induk ke anak yang diizinkan:

```
Pusat (Data Center / NOC)
 └─ Core Router / Core Switch
     ├─ Layanan pusat (Upstream IIX / IP Transit, BNG/BRAS, Radius, Billing, Monitoring)
     └─ Backbone / Transport (fiber, metro-ethernet, radio)
         └─ Cabang / POP
             └─ Router/Switch Aggregation
                 ├─ OLT  (OLT ke-2, ke-3 dst. dalam satu POP = "Mini POP")
                 │   └─ PON Port (slot/port, mis. 1/1/1)
                 │       └─ Kabel Feeder
                 │           └─ ODP + splitter 1:8 / 1:16
                 │               └─ Kabel Drop
                 │                   └─ ONT/ONU (rumah pelanggan)
                 └─ Backbone lanjutan ke POP lain
```

| Induk | Anak yang boleh ditambahkan |
|---|---|
| Data Center | Core |
| Core | Layanan, Backbone |
| Backbone | POP |
| POP | Aggregation |
| Aggregation | OLT, Backbone |
| OLT | PON (lewat peta slot, bukan tombol tambah) |
| PON | Feeder (maksimal satu) |
| Feeder | ODP |
| ODP | Drop |
| Drop | ONT (maksimal satu) |

---

## 2. Cara kapasitas dihitung

| Tipe | Arti "kapasitas" | "Terpakai" dihitung dari | Satuan |
|---|---|---|---|
| Data Center | Tinggi rack | Total tinggi rack (`ru`) perangkat di bawahnya + U lain | U |
| Core | Jumlah port | Jumlah anak langsung + port lain | port |
| Layanan | Tidak ada, hanya tinggi rack (`ru`) | - | - |
| Backbone | Kapasitas link | Diisi manual (`usedGbps`) | Gbps |
| POP | Tinggi rack | Total `ru` Aggregation dan OLT + U lain | U |
| Aggregation | Jumlah port | Jumlah anak langsung + port lain | port |
| OLT | Slot chassis. Kapasitas PON = jumlah port semua kartu terpasang | Jumlah port PON yang aktif | PON |
| PON | Maksimal ONT per port (default 128) | Jumlah ONT di bawahnya | ONT |
| Feeder | Jumlah core | Jumlah ODP + core lain | core |
| ODP | Port splitter | Jumlah drop + port lain | port |
| Drop | Tidak ada | - | - |
| ONT | Tidak ada | - | - |

Kolom "lain" (`extraUsed`) untuk kapasitas yang terpakai tapi tidak dicatat sebagai node, misalnya port uplink atau UPS di rack.

**Warna utilisasi:** di bawah 70% hijau, 70 sampai 89% kuning, 90% ke atas merah (node ditandai PENUH di topologi). Ringkasan di bagian atas halaman menghitung perangkat dengan utilisasi 85% ke atas.

**Kode warna serat** pada feeder mengikuti urutan standar 12 warna: Biru, Jingga, Hijau, Coklat, Abu-abu, Putih, Merah, Hitam, Kuning, Ungu, Merah muda, Aqua. Feeder di atas 12 core dibagi per tube.

**Mini POP:** OLT pertama di sebuah POP berlabel "OLT utama", OLT berikutnya otomatis "Mini POP #2", "#3", dan seterusnya.

---

## 3. Aturan validasi

Penambahan perangkat ditolak dengan pesan jelas bila:

1. Core, Aggregation, Feeder, atau ODP induknya sudah penuh (tidak ada port atau core kosong).
2. Rack Data Center atau POP tidak cukup untuk tinggi rack perangkat baru (`ru`).
3. PON sudah memiliki feeder, atau Drop sudah terhubung ke ONT.

Port PON hanya bisa diaktifkan di slot yang sudah dipasangi kartu. Kartu PON hanya bisa dilepas bila tidak ada port yang aktif di dalamnya.

---

## 4. Cara memakai

**Topologi**
- Klik node untuk membuka panel detail di kanan.
- Klik lingkaran di sisi kanan node untuk menciutkan atau membuka cabang. Angka di lingkaran adalah jumlah anak langsung yang tersembunyi.
- Pilihan "Kedalaman" menciutkan sekaligus: sampai POP, OLT, ODP, atau ONT (semua).
- Tombol +/− mengatur zoom. Kanvas otomatis berpusat ke node yang dipilih.

**Panel detail**
- Rack Data Center/POP: setiap U ditampilkan, termasuk blok "Kosong".
- Core/Aggregation: peta port, terpakai atau kosong.
- OLT: peta slot. Slot kosong punya tombol "Pasang kartu PON". Klik port kosong untuk mengaktifkan PON (opsional sekaligus membuat feeder).
- Feeder: daftar core dengan warna serat dan ODP yang memakainya.
- ODP: peta port splitter. Klik port kosong atau tombol "Pasang pelanggan" untuk membuat Drop dan ONT sekaligus.
- Bagian "Detail" untuk mengubah nama, model, status, kapasitas, dan catatan. Bagian "Hapus" menghapus perangkat beserta semua turunannya, dengan konfirmasi.

**Inventaris**
- Tabel semua perangkat dengan filter tipe, pencarian, dan urutan (topologi, utilisasi tertinggi, kosong terbanyak).

**Pencarian** di bilah atas mencari nama, model, serial ONT, paket, dan alamat. Enter membuka hasil pertama.

**Tema:** tombol di kanan atas bergantian antara ikuti sistem, terang, dan gelap.

---

## 5. Penyimpanan data

Data disimpan di `localStorage` browser, jadi hanya ada di browser dan perangkat yang dipakai. Untuk cadangan atau pindah perangkat:

1. Klik **Data**, lalu **Salin** untuk mengambil seluruh data sebagai JSON.
2. Di tempat tujuan, buka **Data**, tempel JSON, lalu **Terapkan JSON**.
3. **Kembalikan data contoh** mengganti semua data dengan data contoh bawaan.

### Bentuk data

Data adalah objek `{ id: node }`. Hierarki dibentuk lewat `parentId` (adjacency list).

| Field | Tipe | Keterangan |
|---|---|---|
| `id` | string | Unik |
| `type` | string | `datacenter`, `core`, `service`, `transport`, `pop`, `agg`, `olt`, `pon`, `feeder`, `odp`, `drop`, `ont` |
| `parentId` | string atau null | Induk. Null untuk Data Center |
| `name`, `model`, `note` | string | Nama, model perangkat, catatan |
| `status` | string | `aktif`, `rencana`, `gangguan`, `nonaktif` |
| `capacity` | number | Arti sesuai tipe (lihat tabel bagian 2) |
| `extraUsed` | number | Kapasitas terpakai yang tidak dicatat sebagai node |
| `ru` | number | Tinggi rack (U) yang dipakai perangkat |
| `cards` | object | Khusus OLT: `{ "1": { "model": "GPON C+ 16 port", "ports": 16 } }`, kunci adalah nomor slot |
| `slot`, `port` | number | Khusus PON, membentuk label `1/slot/port` |
| `usedGbps`, `linkKind` | number, string | Khusus Backbone. `linkKind`: `fiber`, `metro-ethernet`, `radio` |
| `serviceKind` | string | Khusus Layanan: `upstream`, `bng`, `radius`, `billing`, `monitoring`, `lainnya` |
| `lengthM` | number | Panjang kabel (m) untuk Backbone, Feeder, Drop |
| `serial`, `plan`, `address` | string | Khusus ONT |

Contoh satu OLT:

```json
{
  "s20": {
    "id": "s20", "type": "olt", "parentId": "s13",
    "name": "OLT-A-01", "model": "OLT chassis 4 slot", "status": "aktif",
    "capacity": 4, "ru": 4, "extraUsed": 0,
    "cards": {
      "1": { "model": "GPON C+ 16 port", "ports": 16 },
      "2": { "model": "GPON C+ 16 port", "ports": 16 }
    }
  }
}
```

---

## 6. Topologi data contoh

Ringkasan yang dibuat dari data contoh bawaan. Angka dalam kurung adalah terpakai/kapasitas. Pelanggan hanya ditampilkan untuk dua kabel drop pertama di ODP-A01-001.

```
DATACENTER: Data Center Pusat  (15/42 U)
    └─ CORE: CORE-01  (9/16 port)
        └─ SERVICE: Upstream IIX / IP Transit
        └─ SERVICE: BNG / BRAS
        └─ SERVICE: Radius
        └─ SERVICE: Billing
        └─ SERVICE: Server Monitoring
        └─ TRANSPORT: BB-A Fiber 10G  (6.4/10 Gbps)
            └─ POP: POP Cabang A  (8/12 U)
                └─ AGG: AGG-A-01  (4/24 port)
                    └─ OLT: OLT-A-01  (4/32 PON) [slot terpasang 2/4]
                        └─ PON: PON 1/1/1  (23/128 ONT)
                            └─ FEEDER: FDR-A01-1.1  (4/24 core)
                                └─ ODP: ODP-A01-001  (9/16 port)
                                    └─ DROP: DROP-001
                                        └─ ONT: Siti Nugroho  (20 Mbps, ZTEGC0DE1EEF)
                                    └─ DROP: DROP-002
                                        └─ ONT: Agus Riyadi  (30 Mbps, ZTEGC0DE3DDE)
                                └─ ODP: ODP-A01-002  (8/8 port)
                                └─ ODP: ODP-A01-003  (4/16 port)
                                └─ ODP: ODP-A01-004  (2/8 port)
                        └─ PON: PON 1/1/2  (9/128 ONT)
                            └─ FEEDER: FDR-A01-1.2  (2/12 core)
                                └─ ODP: ODP-A01-005  (6/8 port)
                                └─ ODP: ODP-A01-006  (3/16 port)
                        └─ PON: PON 1/1/3  (2/128 ONT)
                            └─ FEEDER: FDR-A01-1.3  (1/12 core)
                                └─ ODP: ODP-A01-007  (2/8 port)
                        └─ PON: PON 1/2/1  (5/128 ONT)
                            └─ FEEDER: FDR-A01-2.1  (1/12 core)
                                └─ ODP: ODP-A01-008  (5/16 port)
                    └─ OLT: OLT-A-02 (Mini POP)  (1/8 PON) [slot terpasang 1/2]
                        └─ PON: PON 1/1/1  (4/128 ONT)
                            └─ FEEDER: FDR-A02-1.1  (2/12 core)
                                └─ ODP: ODP-A02-001  (3/8 port)
                                └─ ODP: ODP-A02-002  (1/8 port)
        └─ TRANSPORT: BB-B Metro-E 2G  (1.1/2 Gbps)
            └─ POP: POP Cabang B  (4/8 U)
                └─ AGG: AGG-B-01  (2/8 port)
                    └─ OLT: OLT-B-01  (2/16 PON) [slot terpasang 1/2]
                        └─ PON: PON 1/1/1  (7/128 ONT)
                            └─ FEEDER: FDR-B01-1.1  (1/12 core)
                                └─ ODP: ODP-B01-001  (7/16 port)
                        └─ PON: PON 1/1/2  (4/128 ONT)
                            └─ FEEDER: FDR-B01-1.2  (1/12 core)
                                └─ ODP: ODP-B01-002  (4/8 port)
        └─ TRANSPORT: BB-C Radio 1G  (0/1 Gbps) [rencana]
            └─ POP: POP Cabang C  (3/6 U) [rencana]
                └─ AGG: AGG-C-01  (1/8 port) [rencana]
                    └─ OLT: OLT-C-01  (0/0 PON) [slot terpasang 0/2] [rencana]
```

Total data contoh: 54 pelanggan (ONT), 7 dari 56 port PON terpakai, 6 slot OLT kosong, dan 82 port ODP kosong.

---

## 7. Sketsa skema untuk Laravel

Bila data ini mau dipindah ke database, pola adjacency list yang sama bisa dipakai langsung.

```php
// nodes: satu tabel untuk seluruh hierarki
Schema::create('nodes', function (Blueprint $t) {
    $t->ulid('id')->primary();
    $t->foreignUlid('parent_id')->nullable()->constrained('nodes')->cascadeOnDelete();
    $t->string('type');                       // datacenter, core, ..., ont
    $t->string('name');
    $t->string('model')->nullable();
    $t->string('status')->default('aktif');   // aktif, rencana, gangguan, nonaktif
    $t->unsignedInteger('capacity')->default(0);
    $t->unsignedInteger('extra_used')->default(0);
    $t->unsignedTinyInteger('ru')->default(0);
    $t->json('attrs')->nullable();           // slot/port, usedGbps, linkKind, serial, plan, dst.
    $t->text('note')->nullable();
    $t->timestamps();
    $t->index(['parent_id', 'type']);
});

// kartu PON per slot OLT
Schema::create('olt_cards', function (Blueprint $t) {
    $t->id();
    $t->foreignUlid('olt_id')->constrained('nodes')->cascadeOnDelete();
    $t->unsignedTinyInteger('slot');
    $t->string('model');
    $t->unsignedTinyInteger('ports');
    $t->unique(['olt_id', 'slot']);
});
```

Aturan kapasitas pada bagian 2 dan validasi pada bagian 3 sebaiknya diletakkan di service class atau form request, supaya konsisten antara API dan tampilan.
