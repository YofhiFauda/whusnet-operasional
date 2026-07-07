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

## 3. Generate CID (`generateComplexCid()`, dipanggil dari `finalVerify()`)

```
Verifikasi Admin approve aktivasi (lihat docs/customer-lifecycle/flowchart.md §5)
        │
        ▼
Pop::generateComplexCid(customer, distribution)
        │
        ▼
reqId = extractBareRegistrationId(customer.customer_code)  — strip prefix lama kalau ada
        │
        ▼
oltNumber = resolveMiniPopSegment(customer, technicalDetail?.olt_number)
        │
        ├─ customer.pop.pop_code diawali cid_prefix POP? ──ya──▶ ambil sisa string, bersihkan non-alnum
        ├─ tidak → pakai fallback olt_number (dibersihkan sama)
        └─ tidak ada juga → default '1'
        │
        ▼
distCode = distribution?.code ?? 'XX'
        │
        ▼
CID = "{cid_prefix}{oltNumber}{distCode}{reqId}"
        │
        ▼
(dipakai lagi oleh) generatePppoeUsername() → CID + "_{DESA}_{NAMA}"
```

## 4. Resolve Display ID (dipanggil kapan pun UI perlu tampilkan identitas pelanggan)

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

## 5. Toggle Status POP

```
Admin klik toggle status (POST /master/pop/{pop}/toggle)
        │
        ▼
status: active ↔ inactive (simple flip, gak ada validasi dependency)
```
