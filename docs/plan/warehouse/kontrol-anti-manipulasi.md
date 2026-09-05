# Kontrol Anti-Manipulasi Modul Gudang/Inventory

Lanjutan dari [`warehouse_inventory_asset_traceability_analysis.md`](warehouse_inventory_asset_traceability_analysis.md), [`_advanced`](warehouse_inventory_asset_traceability_analysis_advanced), dan [`rancangan-ui.md`](rancangan-ui.md). Dokumen-dokumen sebelumnya jawab **alur normal** (barang ngalir HQ→Cabang→Teknisi→Pelanggan). Dokumen ini jawab pertanyaan beda: **di mana alur itu bisa dimanipulasi**, khususnya karena struktur Anak Gudang bakal banyak (tiap cabang) — makin banyak titik, makin banyak peluang barang "bocor" tanpa ketauan.

Ini kontrol yang **nempel di alur existing**, bukan alur baru. Poin "pemakaian material non-serial self-reported" (kabel/connector/sleeve) sengaja **tidak dibahas di sini** — user punya pendekatan sendiri untuk itu, menyusul terpisah.

---

## 1. Segregation of Duties untuk Kerugian — Pengaju ≠ Penyetuju

**Masalah:** `pop_admin` di satu cabang biasanya orang tunggal — dia yang pegang gudang, dia juga yang bisa catat `LOST`/`DAMAGED`/`SCRAPPED`/`ADJUSTMENT`. Kalau dia sendiri yang ajukan DAN sendiri yang "sah"-kan, klaim kerugian jadi kedok sempurna buat nutupin barang yang sebenarnya dijual/dipakai pribadi.

**Preseden yang sudah ada di repo:** `CashDepositService` (Kolektor 2.0) — kolektor yang setor TIDAK BOLEH jadi orang yang memverifikasi setorannya sendiri, admin/owner yang verifikasi terpisah. Pola yang sama harus dipasang di sini.

**Keputusan user:** belum ada threshold nominal — masih rancangan awal, angka pasti belum bisa ditentukan tanpa data operasional riil. Basis monitoring untuk sekarang **bukan** gerbang approval bertingkat berdasarkan nilai uang, tapi **status barang** dari `warehouse_inventory_asset_traceability_analysis_advanced` §15 (`AVAILABLE`/`RESERVED`/`INSTALLED`/`RETURNED`/`LOST`/`DAMAGED`) — tiap transisi ke `LOST`/`DAMAGED` tercatat sebagai perubahan status di ledger dan kelihatan HQ, bukan diblok nunggu approval sebelum tercatat.

**Kontrol (versi awal, tanpa threshold):**
- Tiap transisi status ke `LOST`/`DAMAGED`/`SCRAPPED` dicatat sebagai transaksi ledger biasa (lihat §6, append-only) — `reported_by` (siapa yang lapor) tetap direkam terpisah dari `reviewed_by`/siapa yang lihat di dashboard HQ, walau belum ada gerbang blokir sebelum tercatat.
- Dashboard HQ (§2.1 `rancangan-ui.md`) menampilkan **semua** transisi `LOST`/`DAMAGED`/`SCRAPPED` terbaru lintas cabang sebagai daftar yang wajib dipantau manual — monitoring pasca-fakta, bukan pre-approval.
- Gerbang approval bertingkat (`PENDING_APPROVAL` + threshold nominal) **ditunda** ke fase lanjut — baru masuk akal dibangun setelah ada data riil pola kerugian per cabang (butuh threshold yang masuk akal, bukan angka tebakan).

---

## 2. Bukti Fisik Wajib untuk Klaim Kerugian

**Masalah:** "Modem hilang" / "kabel rusak" cuma teks bebas gampang dipalsuin — gak ada cara beda antara klaim jujur dan klaim buat nutupin pencurian.

**Preseden yang sudah ada di repo:** `FileUploadService` — dipakai evidence tiket, foto laporan pemasangan/survey, dsb. Pola upload+validasi sudah matang, tinggal dipakai ulang.

**Kontrol:**
- Klaim `LOST` wajib **BAP** (berita acara kehilangan) — minimal catatan terstruktur (kapan, di mana, kronologi), foto kalau ada (mis. lokasi kejadian, sisa kemasan).
- Klaim `DAMAGED` wajib **foto kondisi fisik barang** — tanpa foto, klaim `PENDING_APPROVAL` gak bisa disetujui (guard di Service, bukan validasi UI doang yang bisa dilewat lewat request langsung).
- Foto dan BAP ikut tersimpan permanen di ledger (`inventory_transactions` terkait) — bagian dari audit trail, bukan lampiran terpisah yang bisa "ilang".

---

## 3. Custody Menumpuk Lama — Badge Durasi, Bukan Alert Ambang Waktu

**Masalah:** Rancangan awal cuma mencatat `Issued ≠ Installed` sebagai angka laporan (§14 doc pertama). Itu pasif — HQ harus buka laporan dan mikir sendiri. Barang yang "dipegang teknisi, gak kunjung dipasang, gak kunjung dibalikin" itu pola klasik barang dijual off-book / dipakai pribadi.

**Keputusan user:** tidak perlu ambang waktu tetap (bukan "> 14 hari otomatis flag") — cukup **badge informasional** yang nunjukin sudah berapa lama/jam barang itu di-custody, biar manusia (HQ) yang menilai wajar atau tidak, bukan sistem yang nge-judge otomatis.

**Kontrol:**
- Tiap baris custody (`inventory_serials`/`inventory_balances` per teknisi) punya `issued_at`. Di tiap tampilan custody (Dashboard HQ §2.1, "Stok Saya" §2.5, "Lihat Semua" §2.6 `rancangan-ui.md`) tempel **badge durasi** — dihitung dari `issued_at` sampai sekarang, ditampilkan dalam jam (< 24 jam) atau hari (≥ 1 hari).
- Tidak ada logika threshold/alert otomatis di baliknya — badge murni informasi, HQ yang memutuskan mana yang mencurigakan berdasarkan konteks (jenis pekerjaan, jarak lokasi, dll — hal yang sistem gak bisa nilai sendiri).
- Bisa diurutkan (sort) dari yang paling lama belum bergerak — memudahkan HQ nyisir tanpa perlu sistem nentuin sendiri apa itu "lama".

---

## 4. Serah-Terima Fisik — Acknowledgment Digital, Bukan Kertas Doang

**Masalah:** `image-2.png` (flowchart lifecycle SN) nyebut "tanda tangan bon" — kalau itu cuma kertas fisik yang diisi admin gudang atas nama teknisi, gampang disangkal belakangan ("saya gak pernah terima barang itu / saya terima lebih sedikit dari yang dicatat").

**Kontrol:**
- Konfirmasi **terima** (baik di titik Transfer→Cabang maupun Issue→Teknisi) wajib aksi digital dari pihak **penerima sendiri** — bukan admin yang input "atas nama" penerima.
- Teknisi login sendiri, buka halaman Issue yang ditujukan ke dia, klik "Saya terima barang ini" — tercatat `received_ack_by` + timestamp, beda dari `issued_by` (admin gudang yang keluarin).
- Kalau ada selisih pas ambil fisik (admin bilang kasih 5, teknisi cuma pegang 3), teknisi punya opsi "Tolak sebagian" saat ack — bukan cuma terima-semua-atau-tidak-sama-sekali. Selisih ini otomatis tercatat sebagai diskrepansi, bukan disembunyikan salah satu pihak.

---

## 5. Stock Opname Sesuai Kebutuhan — Bukan Jadwal Kalender Tetap

**Masalah:** Rancangan awal (§18 doc pertama) sudah benar soal ADJUSTMENT lewat transaksi (bukan edit angka stok langsung), tapi kalau opname cuma dilakukan "kalau curiga", selisih kecil per bulan bisa numpuk tanpa siapa pun notice.

**Keputusan user:** siklusnya **tidak menentu** secara alami — kadang opname sebelum jadwal apa pun, kadang barang keburu habis duluan karena lonjakan pemasangan. Jadwal kalender tetap (mis. "wajib tiap tanggal 1") gak cocok sama pola operasional riil, jadi **jangan** dipaksakan jadi jadwal baku dengan status "Overdue" yang dihitung dari kalender.

**Kontrol (disesuaikan — event-driven, bukan calendar-driven):**
- Opname bisa dipicu **kapan saja**, oleh siapa saja yang berwenang (`pop_admin` cabangnya, `admin`/`owner`) — tidak terikat tanggal, sejalan dengan kenyataan stok bisa habis mendadak saat pemasangan lagi ramai.
- Yang dicatat bukan "kepatuhan ke jadwal", tapi **riwayat kapan terakhir opname per cabang per item** — ditampilkan di Dashboard HQ (§2.1 `rancangan-ui.md`) sebagai info ("Opname terakhir: 12 hari lalu"), bukan status lulus/gagal.
- Hasil opname (termasuk yang selisihnya NOL) tetap wajib tercatat sebagai transaksi `STOCK_OPNAME` — prinsip ini tetap berlaku terlepas dari kapan waktunya, supaya "belum pernah opname" vs "baru saja opname hasilnya pas" tetap beda status yang kelihatan di ledger.
- Stok yang keburu habis (`AVAILABLE` mendekati/menyentuh nol) justru jadi salah satu **pemicu alami** opname mendadak — sejalan dengan pola riil yang disebut user, bukan sesuatu yang perlu dilawan dengan jadwal paksa.

---

## 6. Ledger Append-Only — Tegas, Bukan Sekadar Prinsip

**Masalah:** §25 doc pertama sudah bilang "jangan `UPDATE stock` langsung", tapi belum tegas soal baris ledger itu sendiri boleh diedit/dihapus atau tidak.

**Kontrol:**
- Baris `inventory_transactions` **tidak boleh** di-edit atau dihapus siapa pun, termasuk `owner` — sama prinsipnya dengan "tagihan lunas tidak dihapus sembarangan" (aturan keras billing yang sudah berlaku di modul lain).
- Salah catat dilawan dengan baris **koreksi baru** (`ADJUSTMENT` dengan referensi ke transaksi yang salah), bukan mengubah baris lama — biar riwayat forensik tetap utuh kalau suatu saat ada audit/investigasi selisih.
- Ini ditegakkan di level Model/Observer (larangan `update()`/`delete()` pada `InventoryTransaction`), bukan cuma konvensi kerja.

---

## Keputusan yang Sudah Diambil (2026-09-02)

1. **Threshold nominal approval kerugian** — belum ditentukan, sengaja ditunda (poin 1). Monitoring untuk sekarang berbasis status barang (§15 doc advanced), bukan gerbang approval berjenjang nilai uang. Threshold baru dibahas ulang setelah ada data operasional riil.
2. **Ambang waktu custody "menggantung"** — tidak dipakai (poin 3). Diganti badge durasi (jam/hari) yang informasional, penilaian tetap di tangan HQ.
3. **Siklus stock opname** — tidak dipatok ke jadwal kalender (poin 5), karena polanya alami tidak menentu (kadang sebelum jadwal, kadang dipicu stok abis mendadak saat pemasangan ramai). Diganti pencatatan "kapan terakhir opname" + opname dipicu kapan saja sesuai kebutuhan.

## 7. Verifikasi Material Non-Serial — Structural Constraint + Return Reconciliation

**Keputusan user (menjawab poin yang sebelumnya ditunda).** Beda pendekatan dari anomaly detection yang tadinya diusulkan: alih-alih mendeteksi kebohongan (reaktif, threshold subjektif, bisa di-game dengan angka "normal"), **batasi struktural berapa yang bisa diklaim** — overclaim gak mungkin secara sistem, bukan sekadar dicurigai belakangan.

**Aturan intinya:** teknisi cuma bisa klaim sejumlah yang ADA di custody-nya. Titik.

```
ISSUE (gudang → teknisi)
  └── custody terbentuk di TechnicianCustody, qty terkunci

LAPORAN SUBMIT
  └── qty diklaim ≤ qty custody → sistem enforce (InsufficientCustodyException kalau lebih)
  └── sisa yang gak diklaim → status PARTIALLY_USED, visible ke admin gudang

RETURN (teknisi → gudang, wajib buat sisa yang gak dipakai)
  └── admin gudang input qty FISIK yang diterima (bukan cuma percaya sistem)
  └── sistem compare: qty_expected (dari sisa custody) vs qty_actual (fisik)
  └── selisih → dicatat ADJUSTMENT dengan reason=shrinkage_on_return, wajib catatan alasan, di-flag review atasan
```

**Kenapa ini lebih kuat dari anomaly detection:** ketahuan PER TRANSAKSI (bukan nunggu data historis numpuk), diverifikasi FISIK oleh pihak kedua (admin gudang megang barangnya, bukan algoritma nebak), dan gak ada false positive dari variasi instalasi (rumah vs gedung beda kebutuhan kabel — anomaly detection gampang salah tuduh, structural constraint gak peduli itu, cuma peduli "yang dikembalikan cocok gak sama yang sisa di sistem").

**Celah yang diakui secara eksplisit (bukan diabaikan):** kalau teknisi dan admin gudang berkolusi — admin gudang konfirmasi terima 20m padahal fisiknya cuma 15m — sistem gak bisa deteksi ini. Mitigasi: stock opname periodik (§5) — kalau admin gudang rutin "menerima" lebih dari yang ada, stok gudang bakal over di sistem tapi under di fisik, ketahuan pas opname. Ini kejujuran soal batas sistem, bukan celah yang ditutup-tutupi.

**Konsekuensi struktural (2 hal baru yang perlu ditambah ke rancangan data):**

1. **Tabel `TechnicianCustody` baru** — custody buat item QUANTITY/BATCH (beda dari `inventory_serials` yang khusus serialized per-unit). Lihat `rancangan-ui.md` §3.8.
2. **Dua vocabulary status berbeda, sengaja bukan satu:** status kanonik §15 (`AVAILABLE/RESERVED/INSTALLED/...`) tetap khusus `inventory_serials` (unit serial, atomic — gak ada "terpasang sebagian"). `TechnicianCustody` py status sendiri (`ISSUED/PARTIALLY_USED/RETURNED/CONSUMED`) karena qty MEMANG bisa parsial — bukan pelanggaran aturan "satu acuan status" (§16.6 doc advanced), itu dua entitas dengan sifat lifecycle yang beda secara fundamental.

**Koreksi kecil dari draf user:** `SHRINKAGE` gak perlu jadi transaction type baru di ledger — reuse `ADJUSTMENT` yang udah ada di daftar tipe (§29.9 doc pertama) dengan field `reason=shrinkage_on_return`. Nambah type ledger baru tiap ada skenario spesifik bikin vocabulary numpuk tanpa perlu (prinsip sama kenapa status disatuin di §16.6).
