# Flowchart — Modul Gudang

## 1. Receive (Barang Masuk)

```mermaid
flowchart TD
    A[Admin Pusat: buka Receive create] --> B{Pop tipe pusat?}
    B -- tidak --> X1[Ditolak: RECEIVE hanya di Pusat]
    B -- ya --> C[Input baris item]
    C --> D{tracking_type}
    D -- SERIALIZED --> E[Textarea SN + harga satuan]
    D -- QUANTITY/BATCH --> F[Qty + lot_no jika BATCH + harga satuan]
    E --> G{SN dobel/sudah ada?}
    G -- ya --> X2[Ditolak: pesan spesifik per kasus]
    G -- tidak --> H[Buat InventorySerial AVAILABLE + ledger RECEIVE]
    F --> I{harga > 0?}
    I -- tidak --> X3[Ditolak: harga wajib > 0]
    I -- ya --> J[Increment InventoryBalance + ledger RECEIVE]
    H --> K[Satu reference_number RCV-... untuk semua baris]
    J --> K
    K --> L[Redirect ke Receive show]
```

## 2. Transfer Pusat → Cabang (2 Fase)

```mermaid
flowchart TD
    subgraph Dispatch [Fase 1: Dispatch — Admin Pusat]
        A1[createTransfer] --> A2{from=pusat, to=cabang?}
        A2 -- tidak --> XA[Ditolak]
        A2 -- ya --> A3[Buat InventoryTransfer status=in_transit]
        A3 --> A4[SERIALIZED: SN lockForUpdate AVAILABLE→TRANSFERRED, current_pop_id=null]
        A3 --> A5[QUANTITY/BATCH: decrement InventoryBalance Pusat]
        A4 --> A6[Ledger TRANSFER: from_pop_id + inventory_transfer_id]
        A5 --> A6
    end

    A6 --> B1[Transfer in_transit, stok Pusat sudah berkurang]

    subgraph Confirm [Fase 2: Confirm — Admin Cabang]
        B1 --> B2[receiveTransfer: lock+re-fetch transfer]
        B2 --> B3{isInTransit?}
        B3 -- tidak --> XB[Ditolak: sudah dikonfirmasi]
        B3 -- ya --> B4[Per baris dispatch: SN dikonfirmasi?]
        B4 -- tidak/mismatch --> B5[SN tetap TRANSFERRED — limbo, investigasi manual]
        B4 -- ya --> B6[SN → AVAILABLE, current_pop_id=Cabang. Ledger TRANSFER to_pop_id]
        B4 -- qty partial --> B7[increment InventoryBalance Cabang sebesar qty dikonfirmasi]
        B5 --> B8{Ada baris tidak 100% cocok?}
        B6 --> B8
        B7 --> B8
        B8 -- ya --> B9[status = RECEIVED_PARTIAL]
        B8 -- tidak --> B10[status = RECEIVED]
    end
```

## 3. Issue (Cabang → Teknisi)

```mermaid
flowchart TD
    A[Admin Cabang: buka Issue create] --> B{Pop tipe cabang?}
    B -- tidak --> X[Ditolak]
    B -- ya --> C[Pilih teknisi + baris item]
    C --> D{tracking_type}
    D -- SERIALIZED --> E[SN lock AVAILABLE→ISSUED, current_technician_id, issued_from_pop_id]
    D -- QUANTITY/BATCH --> F[decrement InventoryBalance Cabang]
    F --> G[Buat baris TechnicianCustody BARU status=ISSUED]
    E --> H[Ledger ISSUE: from_pop_id + to_technician_id, satu ref ISS-...]
    G --> H
    H --> I[Redirect ke Issue show]
```

## 4. Lifecycle Stock Request

```mermaid
stateDiagram-v2
    [*] --> PENDING: create() oleh Cabang
    PENDING --> PARTIAL: recordDelivery() qty < total
    PENDING --> FULFILLED: recordDelivery() qty = total, atau fulfill()
    PENDING --> REJECTED: reject() — hanya PENDING murni
    PENDING --> CANCELLED: cancel() oleh pengaju — hanya PENDING murni
    PARTIAL --> PARTIAL: recordDelivery() lanjutan
    PARTIAL --> FULFILLED: recordDelivery() lengkap, atau fulfill()
    FULFILLED --> [*]
    REJECTED --> [*]
    CANCELLED --> [*]

    note right of PARTIAL
        Tidak bisa reject/cancel lagi —
        barang sudah mulai bergerak
    end note
    note right of FULFILLED
        Tidak memindahkan barang apa pun —
        Transfer sungguhan tetap terpisah
    end note
```

## 5. State SerialStatus & Transisi

```mermaid
stateDiagram-v2
    [*] --> AVAILABLE: RECEIVE (receiveSerialized)
    AVAILABLE --> TRANSFERRED: TRANSFER dispatch
    TRANSFERRED --> AVAILABLE: TRANSFER confirm (di gudang tujuan)
    TRANSFERRED --> TRANSFERRED: confirm tidak mencakup SN ini (limbo)
    AVAILABLE --> ISSUED: ISSUE (issueSerialized)
    ISSUED --> AVAILABLE: RETURN (returnSerialToWarehouse)
    ISSUED --> INSTALLED: INSTALL (installSerial, guard OwnershipMode::INSTALLABLE)
    ISSUED --> ISSUED: TRANSFER_CUSTODY (pindah antar teknisi)
    INSTALLED --> AVAILABLE: RETURN (returnInstalledSerialFromCustomer, ke issued_from_pop_id)
    AVAILABLE --> LOST: ADJUSTMENT (evidence wajib)
    AVAILABLE --> DAMAGED: ADJUSTMENT (evidence wajib)
    AVAILABLE --> QUARANTINE: ADJUSTMENT (tanpa evidence)
    ISSUED --> LOST: ADJUSTMENT (evidence wajib)
    ISSUED --> DAMAGED: ADJUSTMENT (evidence wajib)
    INSTALLED --> LOST: ADJUSTMENT (evidence wajib, customer_id/fop_task_id dikosongkan)
    LOST --> SCRAPPED: ADJUSTMENT
    DAMAGED --> SCRAPPED: ADJUSTMENT
    QUARANTINE --> AVAILABLE: ADJUSTMENT (lolos cek)
    SCRAPPED --> [*]: final, tidak bisa diubah lagi
```

Catatan: `RESERVED`/`IN_USE`/`RETURNED` ada di enum tapi belum punya jalur transisi eksplisit di Service saat ini (dicadangkan untuk kebutuhan mendatang).

## 6. Guard POP Scope (Controller Detail/Mutasi)

```mermaid
flowchart TD
    A[Request masuk ke controller detail/mutasi] --> B[Middleware permission:warehouse_*]
    B --> C{hasAllPopAccess user?}
    C -- ya --> F[Lolos — akses semua POP]
    C -- tidak --> D[getAllowedPopIds user]
    D --> E{pop.id in allowedPopIds?}
    E -- ya --> F
    E -- tidak --> G[abort 403: 'Anda tidak memiliki akses ke Gudang X']

    style B fill:#f5f5f5
    note1[Dropdown POP di form create HANYA filter tampilan —\nguard sungguhan wajib di titik create/store/show,\nbukan cuma andalkan pilihan dropdown]
```
