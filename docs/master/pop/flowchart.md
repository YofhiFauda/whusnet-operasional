# Flowchart — Master POP

## 1. Create/Edit POP (cegah circular parent)

```
Admin isi form POP (code, pop_code, registration_prefix, cid_prefix, name, type, parent_id, ...)
        │
        ▼
normalizeIdentifierInput() — uppercase + trim code/pop_code/registration_prefix/cid_prefix
        │
        ▼
Validasi: code unik, pop_code unik + format [A-Z0-9]+(-[A-Z0-9]+)*, prefix format [A-Z0-9]+
        │
        ▼
   (khusus EDIT) parent_id dipilih?
        │
        ▼
   getDescendantIds(pop) — rekursif turun semua children
        │
        ▼
   parent_id yang dipilih ada di [pop.id, ...descendants]? ──ya──▶ TOLAK "circular reference"
        │ tidak
        ▼
Pop::create()/update()
```

## 2. Generate REQ ID (`generateRegistrationNumber()`)

```
Registrasi pelanggan baru → Pop::generateRegistrationNumber()
        │
        ▼
cid_prefix & registration_prefix terisi? ──tidak──▶ throw LogicException
        │ ya
        ▼
DB transaction:
  lock row PopSequence (pop_id, type=registration) — buat baru kalau belum ada
        │
        ▼
  cek MAX angka REQ ID existing di customers utk POP ini
  current_number di sequence < max existing? ──ya──▶ sync current_number = max existing
        │
        ▼
  loop: current_number++, candidate = "{prefix}{6 digit}"
        sampai candidate BELUM dipakai (Customer::where(customer_code, candidate)->exists() == false)
        │
        ▼
  save sequence, commit
        │
        ▼
return candidate (e.g. RQ000021)
```

## 3. Generate CID — satu rumus (`CustomerCidService` → `Pop::generateComplexCid()`, ADHOC-107)

```
Dipicu oleh:
  ├─ CustomerObserver::updating()  — pop_id/mini_pop_id/distribution_id berubah, atau CID kosong
  ├─ Modal "Atur Mini POP & Distribusi" / API network-assignment — sync() eksplisit tiap simpan
  └─ Aktivasi (finalVerify(), activate()) & import (updateQuietly) — resolve() eksplisit
        │
        ▼
status ∈ {active, suspended}? ──tidak──▶ CID tidak disentuh
        │ ya
        ▼
Cabang (query by pop_id) punya cid_prefix? ──tidak──▶ CID lama dibiarkan
        │ ya
        ▼
reqId   = extractBareRegistrationId(customer_code)        — REQ ID permanen
segMini = mini_pop_id ada? → pop_code Mini POP − cid_prefix  :  '0'
          (TANPA fallback pop_code Cabang / olt_number — dihapus K3)
distCode= distribution_id ada? → distribution.code : '0'
        │
        ▼
CID = "{cid_prefix}{segMini}{distCode}{reqId}"   mis. C00RQ… / C10RQ… / C14ARQ…
        │
        ▼
PPPoE username TIDAK diubah otomatis → peringatan kalau tidak diawali "{CID}_"
```

## 4. Assign/Ganti Mini POP & Distribusi (✅ Fixed 2026-07-07, lihat [bug.md](bug.md))

```
Admin klik CID/REQ ID di halaman detail pelanggan → modal terbuka
        │
        ▼
status pelanggan ∈ {registered..waiting_installation, rejected}? ──ya──▶ TOLAK
        │ tidak (udah mulai pemasangan atau lebih lanjut)
        ▼
Pilih Mini POP (dropdown: anak Cabang POP pelanggan)
Pilih Distribusi (dropdown ke-filter otomatis: anak Mini POP terpilih)
        │
        ▼
Submit PUT /customers/{customer}/network-assignment
        │
        ▼
Validasi silang: Mini POP.parent_id == customer.pop_id?
                 Distribusi.pop_id == mini_pop_id terpilih?
        │
        ▼ (lolos)
customer.mini_pop_id = ..., customer.distribution_id = ...
        │
        ▼
status ∈ {active, suspended}? ──ya──▶ CustomerCidService::sync() — rumus §3, juga saat pilihan tidak berubah (perbaikan manual)
        │
        ▼
Save + AuditLog('update_network_assignment')
```

## 4a. Pindah POP lewat Edit Pelanggan (ADHOC-104 → ADHOC-107, final 2026-09-29)

```
Admin buka /customers/{id}/edit → step "POP & Distribusi"
  (form sudah menampilkan: piutang penghalang, atau tagihan bulan ini yang akan ikut pindah)
        │
        ▼
Pilih POP Cabang (dropdown: Pop::forUser(), type=cabang)
Mini POP & Distribusi terbuka HANYA kalau Cabang diganti DAN
  pelanggan pasca-pemasangan DAN user punya customers.detail.installation.validate
        │
        ▼
Submit PUT /customers/{customer}
        │
        ▼
Validasi: POP dalam scope user?                                  ──tidak──▶ TOLAK (pop_id)
          REQ ID sudah dipakai di POP tujuan?                    ──ya─────▶ TOLAK (pop_id)
          Ada piutang / tagihan `sebagian` / tagihan sudah dibayar sebagian?
                                                                 ──ya─────▶ TOLAK (pop_id, jumlah & total)
          Mini POP/Distribusi diisi padahal terkunci?            ──ya─────▶ TOLAK
          Mini POP bukan anak POP / Distribusi bukan anak Mini POP? ─ya──▶ TOLAK
        │ (lolos)
        ▼
$customer->update()
  └─ CustomerObserver::updating()      ← jalan juga dari import/tinker/API
       1. masih ada tagihan penghalang? → CustomerRelocationBlockedException (rollback)
       2. mini_pop bukan anak pop_id → NULL; distribusi bukan anak mini_pop_id → NULL
       3. collector_id = NULL (selalu)
       4. CID dibuat ulang (active/suspended)
  └─ CustomerObserver::updated() (pop_id berubah)
       ├─ cabut token QR aktif
       └─ tagihan bulan berjalan/sesudahnya `belum_dibayar` tanpa pembayaran valid
          → invoices.pop_id = POP baru (per model, tercatat audit)
        │
        ▼
Tagihan periode lalu & pembayaran lama tetap di POP lama; tagihan bulan depan terbit di POP baru
```

## 5. Resolve Display ID (dipanggil kapan pun UI perlu tampilkan identitas pelanggan)

```
Pop::resolveDisplayId(customer)
        │
        ▼
status in [terminated, failed, rejected, putus, gagal]? ──ya──▶ return REQ ID murni
        │ tidak
        ▼
customer.distribution_id ADA dan customer.cid ADA? ──ya──▶ return CID lengkap (customer.cid)
        │ tidak
        ▼
return default "{cid_prefix}00{reqId}"
```

## 6. Toggle Status POP

```
Admin klik toggle status (POST /master/pop/{pop}/toggle)
        │
        ▼
status: active ↔ inactive (simple flip, gak ada validasi dependency)
```
