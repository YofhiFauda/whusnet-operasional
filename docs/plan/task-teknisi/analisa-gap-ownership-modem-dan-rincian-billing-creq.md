# Analisa Gap: Kepemilikan Modem (Beli vs Sewa) & Rincian Nominal Verifikasi C-REQ

Tanggal: 2026-10-08
Status: **analisa saja — belum ada perubahan kode.**
Terkait: `docs/task-teknisi/business-logic.md` §7b (Kategori C-REQ), ADHOC-108/ADHOC-152.

---

## Gap 1 — Sistem tidak punya pembeda modem Beli vs Sewa

**Kondisi sekarang:**
`OwnershipMode` (`app/Enums/OwnershipMode.php`) cuma 2 nilai: `INSTALLABLE` (boleh dipasang ke pelanggan — modem/ONT) dan `COMPANY_ASSET` (gak pernah ke pelanggan — alat kerja macam OTDR). Ini bukan sumbu beli/sewa — ini sumbu "boleh dipasang atau cuma dipinjam-pakai teknisi".

Semua modem yang lewat `inventory_serials` (ter-track sistem) statusnya bisa `INSTALLED → RETURNED` — artinya perusahaan tetap pemilik, modem WAJIB balik ke gudang pas pelanggan putus langganan (lewat DEAC/Ambil Alat). Ini model **Sewa** secara implisit, dan ini satu-satunya model yang didukung.

Kalau pelanggan beneran **beli modem sendiri** (device jadi milik pelanggan permanen, gak perlu balik pas putus):
- Modem itu gak pernah lewat Issue/Install dari gudang → gak pernah masuk `inventory_serials` sama sekali.
- Cuma tercatat sebagai metadata di `customer_devices` (brand/model/serial) — di LUAR seluruh sistem stok/retur/traceability.
- DEAC/Ambil Alat tetap nyari SN di `inventory_serials` pas pelanggan putus — SN milik-sendiri pelanggan gak akan ketemu di situ. Gak ada mekanisme eksplisit "tandai modem ini milik pelanggan, skip retur" — hasilnya cuma SN gak ketemu, bukan ditangani sebagai kasus yang dikenali sistem.

**Pertanyaan:**
1. Bisnis emang butuh bedain Beli vs Sewa di level sistem (bukan cuma catatan manual)? Kalau iya, aturan apa yang beda antara dua jenis ini — cuma soal "wajib ditarik pas putus" doang, atau ada aturan lain (garansi, harga jual ke pelanggan, biaya perawatan)?
2. Kalau pelanggan beli modem sendiri sekarang, gimana cara admin mencatatnya saat ini (langsung isi `customer_devices` manual tanpa lewat gudang sama sekali, atau ada proses lain yang belum ketangkep di analisa ini)?
3. Dan mengenai modem tersebut ternyata Modem tersebut pelanggan bisa beli sendiri atau beli dari kantor, namun tetap di kenakan biaaya(dari pernyataan ini saya rasa ada kejanggalan, karena teknisi hanya bisa input modem dari gudang kita, tidak bisa input dari modem luar. bagaimana solvingnnya)??


**JAWABAN**
NAHHH kita meang harus membedakan karena untuk tambah modem itu, pelanggan beli modem sendiri. Jadi modem itu gak perlu balik ke gudang pas pelanggan putus langganan. Makanya pas deactivate harus dicek apakah modem itu milik perusahaan atau milik pelanggan. Kalau pelanggan beli modem sendiri, modem itu gak pernah lewat Issue/Install dari gudang → gak pernah masuk `inventory_serials` sama sekali. Cuma tercatat sebagai metadata di `customer_devices` (brand/model/serial) — di LUAR seluruh sistem stok/retur/traceability. dan jugaaa pada list pelanggan atau apapun ada label modem sewa atau beli untuk membedakannyaaa.

**Klarifikasi (2026-10-08): "beli" itu 2 skenario beda, kebentur di mekanisme sekarang.**

- **Beli sendiri (dari luar, bukan dari kita)** — gak lewat gudang kita sama sekali, SN gak akan pernah ada di `inventory_serials`. Udah bener secara teknis sesuai jawaban di atas: cuma `customer_devices` doang, di luar sistem stok. Gak ada tagihan dari kita (kita gak jual apa-apa).
- **Beli dari kantor (modem kita, tapi JADI MILIK pelanggan permanen + DIKENAKAN BIAYA jual)** — SN-nya **TETAP dari custody teknisi/gudang kita**, mekanismenya **SAMA PERSIS** kayak Sewa/Tambah Modem sekarang (Issue → Install, SN masuk `inventory_serials`, kena biaya lewat Verifikasi C-REQ). Teknisi **TIDAK** nginput "modem luar" — dia tetap pasang SN dari custody seperti biasa. Yang kurang CUMA **flag kepemilikan** nempel di SN itu, nentuin dia gak perlu balik pas putus. Bukan soal "teknisi gak bisa input modem luar" (itu memang benar & gak perlu diubah), tapi soal status SN SETELAH terpasang.

**Rencana solving Gap 1 (diajukan, belum dikerjakan — perlu konfirmasi):**
1. **Kolom baru `inventory_serials.ownership`** (`company`/`customer`, default `company`) — SATU-SATUNYA penanda "modem ini wajib balik pas putus atau gak", dicek SATU TEMPAT (Service), bukan ditebak ulang di tiap halaman.
2. **Diisi pas instalasi atau pas verifikasi billing** — opsi: checkbox "Modem ini dijual ke pelanggan (gak perlu ditarik)" di form yang sama kayak Tambah Modem, ATAU CS yang set pas approve billing kategori "jual modem" di Verifikasi C-REQ. (Perlu diputuskan siapa yang paling tepat set — teknisi di lapangan, atau CS yang mastiin transaksi jual beneran terjadi.)
3. **DEAC/Ambil Alat cek flag ini** — SN `ownership=customer` **SKIP** dari daftar wajib diambil; `device_retrieved_at` langsung dianggap selesai TANPA lewat retur 3-tahap (gak ada yang balik ke gudang — emang bukan punya kita lagi). Kemungkinan butuh outcome baru di `DeviceRetrievalOutcome` (sekarang: `DIAMBIL`/`TIDAK_DITEMUKAN`/`DITOLAK`) — mis. `MILIK_PELANGGAN`, biar jelas beda dari "gak ketemu"/"ditolak pelanggan".
4. **Label "Sewa"/"Beli"** di Detail Pelanggan & Lacak Barang — turunan dari flag ini, bukan field baru per halaman (pola sama `<x-warehouse.origin-badge>` yang udah ada).
5. **Beli sendiri (luar)** — gak ada perubahan, tetap `customer_devices` doang, otomatis "gak perlu ditarik" karena emang gak pernah tercatat gudang. Mungkin perlu label yang SAMA ("Beli") biar konsisten tampilannya walau sumber datanya beda (ada `inventory_serials` vs cuma `customer_devices`).

**Pertanyaan tambahan:**
3. Siapa yang nentuin status jual (company→customer) — teknisi di form lapangan, atau CS pas verifikasi billing di kantor?
4. Modem yang UDAH terpasang sebelum fix ini (semua `ownership` bakal default `company`/Sewa) — ada yang perlu dikoreksi manual jadi "Beli", atau dibiarkan (anggap semua riwayat lama Sewa)?

---

## Gap 2 — Verifikasi Biaya C-REQ cuma 1 field nominal, gak ada rincian per-item terstruktur

**Kondisi sekarang:**
`TaskCreqBillingController::approve()` — CS isi **1 field**: `amount` (nominal rupiah), diketik manual, "**tidak pernah diambil dari catatan teknisi**" (komentar eksplisit di kode). CS menentukan nominal ini dengan membaca laporan lengkap yang tampil di halaman yang sama: `kendala_teknis`, kategori C-REQ, tikor, material terpakai (list barang+qty), SN modem terpasang, alat kerja, foto, dan `billing_note` (catatan bebas dari teknisi).

Gak ada struktur "item + harga per baris" yang wajib diisi. Kalau task punya beberapa pekerjaan berbayar sekaligus (mis. tambah modem DAN pasang kabel ekstra DAN ongkos jasa), semuanya cuma nyampur di satu nominal + satu `billing_note` teks bebas. Kalau `billing_note` teknisi kurang detail, CS gak punya cara lain tau rinciannya selain nanya balik di luar sistem (chat/telepon) — sistem gak paksa breakdown.

**Pertanyaan:**
1. Breakdown per-item (misal: baris "Modem tambahan: Rp150.000", baris "Kabel 20m: Rp50.000") itu emang dibutuhkan buat laporan/audit, atau nominal gabungan + catatan teks itu udah cukup buat kebutuhan bisnis sekarang?
2. Kalau breakdown dibutuhkan, siapa yang ngisi rinciannya — teknisi pas submit laporan (nambah form input per-item), atau CS pas verifikasi (CS yang breakdown ulang dari catatan teknisi)?

---

## Gap 3 — Laporan O-REQ & INFR REQ cuma numpang form MTN generik, gak sesuai tujuan aslinya

**Kondisi sekarang:**
`TaskType::OREQ` ("Office Request", SLA 4 jam/48 jam) dan `TaskType::INFR` ("Infrastruktur Request", SLA 8 jam/72 jam) — beda dari MTN CUMA soal label, warna badge, dan SLA. **Form laporannya 100% sama** dengan MTN (`TaskMaintenanceController`, field generik: kendala teknis, foto OPM/speedtest, Material Terpakai, Modem/Perangkat Aktif). Gak kayak C-REQ yang punya dropdown `CReqCategory` buat bedain jenis pekerjaan — O-REQ dan INFR REQ gak punya struktur pembeda apa pun.

Akibat konkret:
- **INFR REQ** (tujuannya: pasang/benerin infrastruktur jaringan — OLT, Switch, dst) kena **Gap 4** langsung — gak ada cara catat perangkat infra yang dipasang/diganti.
- **O-REQ** ("Office Request" — kebutuhan internal kantor, bisa macem-macem: beli alat, servis internal, dll) juga gak punya kategori/struktur — sama datar kayak laporan maintenance rutin, gak ada cara bedain "O-REQ ini soal apa" selain baca `kendala_teknis` bebas teks.

**Pertanyaan:**
1. O-REQ itu sendiri — contoh konkret pekerjaan apa aja yang masuk sini? (Biar jelas butuh kategori terstruktur kayak C-REQ, atau emang cukup bebas teks karena variasinya terlalu luas buat di-enum-kan.)
2. INFR REQ — penyelesaiannya nebeng solusi Gap 4 (section "Perangkat Infrastruktur") aja, atau INFR REQ butuh field tambahan lain di luar soal perangkat (mis. nomor tiket gangguan jaringan, dampak ke berapa pelanggan, dll)?


---

## Gap 4 — Perangkat Aktif non-pelanggan (OLT, Switch, SFP/Transceiver, Access Point) gak bisa "dipasang" lewat laporan task mana pun

**Kondisi sekarang:**
Yang bisa dicatat "terpasang" lewat laporan task sekarang cuma 2 jenis:
- **Modem/ONT** — lewat section "Modem/Perangkat Aktif" (`installSerial()`), tapi WAJIB terikat `customer_id` (satu pelanggan tertentu).
- **Kabel & ODP (splitter)** — lewat section "Material Terpakai", karena `equipment_class=pasif` (meteran/qty, gak perlu per-unit SN).

**OLT, Switch Jaringan, SFP/Transceiver, Access Point** — semua `equipment_class=aktif` di master barang (`ItemCategorySeeder`), bisa diterima gudang & diissue ke teknisi, TAPI:
- Gak muncul di "Material Terpakai" (section itu cuma nerima `pasif`).
- Gak bisa lewat "Modem/Perangkat Aktif" juga — section itu nempelin ke `customer_id`, padahal OLT/Switch/dkk itu infrastruktur POP/jaringan, BUKAN punya satu pelanggan, dan task-nya (biasanya **O-REQ**/**INFR REQ**) sering gak punya `customer_id` sama sekali (nullable khusus task non-pelanggan).
- Akibat: SN perangkat ini nyangkut di custody teknisi (`ISSUED`) SELAMANYA — gak ada status/jalur buat bilang "ini udah terpasang di POP X". Satu-satunya jalan keluar cuma balik ke gudang (`RETURNED`), padahal kenyataannya terpasang permanen di jaringan.

**Rencana solving (diajukan, belum dikerjakan — perlu konfirmasi):**
1. **Status baru**: pakai `SerialStatus::IN_USE` yang SUDAH ADA di enum tapi belum pernah ditulis kode manapun — reuse buat makna "terpasang di infrastruktur POP", beda dari `INSTALLED` (khusus pelanggan).
2. **`OwnershipMode` butuh nilai ketiga**: `INFRASTRUCTURE` — OLT/Switch/SFP/AP boleh "terpasang" tapi ke POP/site, bukan pelanggan. Beda dari `INSTALLABLE` (ke pelanggan) dan `COMPANY_ASSET` (gak pernah terpasang sama sekali, cuma pinjam-balik).
3. **Method baru `InventoryService::installToInfrastructure()`** — paralel `installSerial()`, target `Pop $pop` (bukan `Customer $customer`). SN → `IN_USE`, `current_pop_id` = POP lokasi pasang (reuse kolom yang udah ada), `customer_id` tetap null, `fop_task_id` terisi.
4. **Section form baru "Perangkat Infrastruktur"** di laporan task — muncul khusus `task_type` **O-REQ**/**INFR REQ**, dropdown isinya SN custody tim yang `ownership_mode=INFRASTRUCTURE`. Target lokasi = `task->pop`, TIDAK butuh `task->customer`.
5. **Jalur bongkar/ganti** — reuse pola retur 3-tahap yang udah dibangun (RETURNED → Cabang → Pusat), titik pencabutannya POP bukan pelanggan.

**Pertanyaan:**
1. Setuju arah solusi di atas (status `IN_USE` + `OwnershipMode::INFRASTRUCTURE` + section form baru khusus O-REQ/INFR REQ), atau ada bagian yang mau diubah?
2. OLT/Switch/SFP/AP yang udah diissue ke teknisi SEBELUM fix ini (kalau ada) — perlu dibackfill manual jadi `IN_USE`, atau dibiarkan (data lama apa adanya, aturan baru cuma berlaku ke depan)?

---

## Gap 5 — Barang retur bekas gak bisa "dijual" antar cabang, laporan gak nampung penjualan barang gudang

**Kondisi sekarang:**
Retur 3-tahap (§12b, ADHOC-108) sudah bikin barang bekas (DEAC/Tambah Modem/Migrasi/Walk-in) berakhir sebagai **stok Pusat** setelah Tahap 3 dikonfirmasi — kondisi (`used_good`/`used_damaged`) sudah ketangkep lewat `ItemCondition` + origin-nya (asal cabang mana) sudah ketangkep lewat `DeviceRetrievalLog`/origin-badge.

Yang **belum ada**: mekanisme "Pusat jual barang itu ke Cabang lain" sebagai transaksi berbeda dari replenishment stok biasa.
- Jalur stock Pusat→Cabang yang ada sekarang (`InventoryTransfer`/dispatch biasa) itu buat **alokasi stok baru** (barang NEW dari pembelian), bukan buat "mindahin + jual barang bekas retur ke cabang lain". Gak ada flag "ini transfer biasa" vs "ini penjualan barang bekas" — kalau dipaksa pakai jalur yang sama, laporan gak bisa misahin dua jenis pergerakan ini.
- Gak ada field harga/nilai jual nempel ke transfer manapun — jadi gak ada cara catat "barang ini terjual seharga Rp X" di laporan gudang.
- Gak ada guard yang nolak barang `condition=used_damaged` dipilih buat dijual — kalau pakai jalur dispatch biasa, SN rusak bisa aja ketransfer tanpa penghalang (barang rusak harusnya CUMA bisa nyangkut di Modem Rusak, gak boleh ikut alur jual).
- Siapa yang boleh memicu pemindahan ini belum ditegakkan eksplisit — ketentuannya **hak CUMA di Gudang Pusat** (cabang gak boleh inisiasi "jual" sendiri), tapi kontrol akses dispatch sekarang scope-nya umum (permission `warehouse_reassign.create`), belum spesifik "hanya Pusat yang boleh pilih mode jual".

**Rencana solving (diajukan, belum dikerjakan — perlu konfirmasi):**
1. **Flag baru di transaksi dispatch** — mis. `inventory_transfers.purpose` (`restock`/`sale`, default `restock`). Opsi "sale" cuma muncul/boleh dipilih kalau aktor punya akses Pusat (bukan Cabang).
2. **Guard kondisi** — form pilih SN buat mode `sale` cuma nampilin SN dengan `condition != used_damaged`. Barang rusak otomatis gak kepilih, konsisten sama aturan "barang rusak gak bisa dijual".
3. **Field harga jual** — nominal per SN/per transfer, diisi Pusat saat dispatch mode `sale`. Nanti jadi sumber laporan "Barang Terjual".
4. **Laporan baru/tambahan** — daftar transaksi `purpose=sale`: SN, asal cabang (origin-badge yang udah ada), cabang tujuan, harga jual, tanggal. Kemungkinan halaman baru di bawah Laporan Gudang, atau tab tambahan di laporan yang udah ada.
5. **Cabang tujuan terima seperti biasa** — tetap lewat Terima Transfer yang sudah ada (gak perlu jalur baru di sisi penerima), SN masuk stok Cabang B siap diissue/dipasang seperti stok lain.

**Pertanyaan:**
1. "Jual" di sini maksudnya transfer **antar cabang internal** (Cabang B dianggap "beli" dari Pusat, dicatat sebagai pendapatan internal kantor), atau barang itu dijual ke **pihak luar/pelanggan langsung** (bukan ke cabang lain)? Dari penjelasan "pusat ambil dari cabang A, jual ke cabang B" kedengarannya internal antar cabang — konfirmasi?
2. Harga jual ditentukan manual tiap transaksi oleh Pusat, atau ada daftar harga standar per kategori+kondisi barang (jadi Pusat cuma pilih dari daftar, gak input manual tiap kali)?
3. Barang rusak yang gak bisa dijual — tetap nyangkut selamanya di Modem Rusak (Gap existing di §12b), atau nanti ada proses lain (jual rongsokan/scrap) yang juga perlu laporan sendiri?
4. Laporan "Barang Terjual" ini perlu masuk ke laporan keuangan/billing juga (jadi pendapatan tercatat), atau cukup laporan internal gudang doang (soal stok, gak nyentuh invoice/pembayaran)?

---

