# Dokumentasi Data Master

Modul Master Data bertugas menyimpan referensi tetap atau data *lookup* (kamus data) yang krusial untuk operasional ISP, mulai dari data referensi wilayah hingga pengaturan jaringan (POP, Router). Data master sangat jarang berubah namun sangat vital bagi modul transaksi (Pelanggan, Tagihan).

## Struktur Modul Master Data

Untuk mempelajari setiap modul master, silakan buka sub-folder masing-masing yang berisi README, Schema DB, Flowchart, dan User Flow:

| Modul | Folder / Tautan | Penjelasan Singkat |
| --- | --- | --- |
| **Paket Internet** | [`docs/master/internet-package/`](internet-package/README.md) | Kamus paket layanan dan harganya. |
| **Status Pelanggan** | [`docs/master/status-pelanggan/`](status-pelanggan/README.md) | Urutan workflow pelanggan (state machine). |
| **Wilayah** | [`docs/master/wilayah/`](wilayah/README.md) | Hierarki Kota, Kecamatan, Kelurahan. |
| **POP (Cabang)** | [`docs/master/pop/`](pop/README.md) | Titik Point of Presence untuk RBAC dan prefix id pelanggan. |
| **Distribusi** | [`docs/master/distribution/`](distribution/README.md) | Kode titik distribusi jaringan — segmen ke-3 di CID pelanggan. |
| **Master Timeline SLA** | [`docs/master/sla-timeline/`](sla-timeline/README.md) | Batas waktu wajib mulai ditangani per jenis tiket, beda-beda per paket internet. |
| **Barang / Material** | [`docs/master/item/`](item/README.md) | Daftar barang yang boleh dicatat di Estimasi Kebutuhan Alat & Perangkat Pasif Terpakai. |

Setiap folder di atas minimal memiliki 4 file standar:
1. `README.md` - Penjelasan umum fitur.
2. `database-schema.md` - Diagram Entity Relationship (ERD) dan field.
3. `flowchart.md` - Alur sistem (system flow) di belakang layar.
4. `user-flow.md` - Langkah demi langkah skenario pengguna dalam aplikasi.

`pop/` dan `distribution/` juga punya `business-logic.md` (aturan hierarki POP, generate REQ ID/CID, keunikan kode) — keduanya saling terkait erat, lihat [docs/master/pop/README.md](pop/README.md).
