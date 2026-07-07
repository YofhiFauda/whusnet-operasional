# Flowchart: Generate Sequence per POP

Berikut adalah alur bagaimana Master POP digunakan di backend untuk memberikan `customer_code` (ID Pelanggan) baru yang berurutan per cabang.

```mermaid
flowchart TD
    Start([Simpan Customer Baru]) --> AssignPOP[Pelanggan di-assign ke POP ID: 1 (MLG)]
    AssignPOP --> LockSequence[DB Transaction: Lock row pop_sequences untuk MLG bulan ini]
    LockSequence --> CekSeq{Ada Record Sequence?}
    
    CekSeq -->|Belum Ada| BuatSeq[Buat record baru, last_sequence = 1]
    CekSeq -->|Sudah Ada| TambahSeq[last_sequence = last_sequence + 1]
    
    BuatSeq --> FormatCode[Generate ID misal: MLG-202606-0001]
    TambahSeq --> FormatCode
    
    FormatCode --> SaveCustomer[Save record Customer dengan code tersebut]
    SaveCustomer --> Commit[Commit Transaction]
```
