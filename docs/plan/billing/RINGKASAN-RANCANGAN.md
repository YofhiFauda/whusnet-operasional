# Ringkasan Rancangan Billing — Status & Urutan Kerja

**Dibuat:** 2026-09-22. Rekap 10 dokumen rancangan `docs/plan/billing/` hasil sesi review — biar gampang dicek progres & urutan kerja tanpa buka satu-satu.

**Urutan pengerjaan disarankan:** ADHOC-84 → ADHOC-70 → ADHOC-69 → ADHOC-87 → ADHOC-92 → ADHOC-68 (94 & 95 independen, bisa disisipkan kapan aja).

---

## 1. Tagihan Manual — ADHOC-70

**Dokumen:** [`analisa-rancangan-tagihan-manual.md`](analisa-rancangan-tagihan-manual.md)
**Status:** Hampir final — rancangan matang, 2 detail teknis sengaja ditunda ke saat coding (bukan blocker):
- Bentuk penyimpanan jenis/sub/deskripsi di kolom `invoices` (kolom baru vs reuse existing) — cek dulu saat implementasi.
- Pola pencarian pelanggan di form (`whereHas` server-side vs typeahead AJAX) — pilih yang paling konsisten dengan form lain.

**Inti:** halaman `/invoices/create` baru (bukan modal), 3 jenis: Perbaikan / Lainnya (sub diketik bebas) / Pindah Lokasi. Invoice + Payment terbit sekaligus dalam satu submit. `InvoiceType::MANUAL` baru, di luar `SUBSCRIPTION_TYPES`. Modal lama di Detail Pelanggan dihapus bersih.

**Fondasi buat:** ADHOC-69 (denda putus langganan pakai jalur ini).

---

## 2. Putus Langganan — ADHOC-69

**Dokumen:** [`analisa-rancangan-putus-langganan.md`](analisa-rancangan-putus-langganan.md) (sumber ide: [`skema-putus-langganan.md`](skema-putus-langganan.md))
**Status:** ✅ Clear, tidak ada pertanyaan terbuka.

**Inti:**
- Denda **cuma berlaku untuk masa langganan ≤ 1 tahun** — wajib diisi manual (boleh diprefill dari default alasan). **Masa > 1 tahun: TIDAK ada denda sama sekali**, field-nya gak muncul di form.
- Tanpa prorate — invoice Bulanan periode berjalan tetap terbit penuh apa adanya.
- Master alasan putus (`CustomerTerminationReason`) — hapus diblok kalau masih dipakai.
- Kolom "siapa yang registrasi" — **bukan kolom baru**, reuse `customers.sales_user_id` (udah ada, buat komisi) + `customer_surveys.technician_id` (teknisi survei).
- Data lama (`termination_reason_id` pelanggan yang udah lama putus) dibiarkan NULL, gak di-backfill.
- Dikerjakan **utuh sekaligus** setelah ADHOC-70 kelar (gak dipecah walau teknisnya bisa).

**Gantung ke:** ADHOC-70 (bentuk invoice denda).

---

## 3. Upgrade/Downgrade Paket — ADHOC-68

**Dokumen:** [`upgrade-downgrade/analisa-upgrade-downgrade-paket.md`](upgrade-downgrade/analisa-upgrade-downgrade-paket.md) (sumber ide: [`upgrade-downgrade/Skema-downgrade-dan-upgrade.md`](upgrade-downgrade/Skema-downgrade-dan-upgrade.md))
**Status:** ✅ Clear, tidak ada pertanyaan terbuka.

**Inti:**
- **Satu rumus** buat upgrade & downgrade: `total = prorate_paket_lama + prorate_paket_baru`, `sisa = total − sudah_dibayar` (clamp 0, minus jadi Deposit). Bukan 2 rumus terpisah "Postpaid/Prepaid" — istilah itu cuma hasil dari rumus yang sama, tergantung berapa yang udah dibayar.
- **Upgrade diblok** kalau ada piutang periode sebelumnya (termasuk status `sebagian`/cicilan). **Downgrade tetap boleh** jalan meski ada piutang.
- Ganti paket 2x+ dalam sebulan — didukung (generalisasi n-segmen).
- Diskon/PPN/`other_fee` — ngikut data pelanggan apa adanya, dikenain sekali di total akhir, **tidak** ikut diprorate per hari.
- Tidak berbagi helper prorate dengan ADHOC-69 (putus langganan sengaja tanpa prorate).

---

## 4. Piutang & Laporan Kas (referensi, bukan ADHOC tersendiri)

**Dokumen:** [`analisa-skema-piutang-dan-laporan-kas.md`](analisa-skema-piutang-dan-laporan-kas.md)
**Status:** ✅ Selesai — dokumen referensi/definisi (piutang, cash-basis vs accrual-basis, aturan tutup buku), bukan rancangan fitur baru berdiri sendiri. FIFO yang tadinya disebut di sini digabung jadi bagian ADHOC-84.

---

## 5. Alokasi Pembayaran & Saldo — ADHOC-84

**Dokumen:** [`analisa-skema-alokasi-pembayaran-dan-saldo.md`](analisa-skema-alokasi-pembayaran-dan-saldo.md)
**Status:** ✅ Clear, tidak ada pertanyaan terbuka.

**Inti:**
- **Dropdown manual "Alokasi Pembayaran" TIDAK dikerjakan** — mekanisme bulanan/piutang/cicilan/lebih-bayar udah otomatis & benar di `PaymentService`, gak perlu diketik kasir.
- Konfirmasi modal cuma buat **lebih bayar** (nangkep salah ketik nominal).
- Piutang lama = peringatan doang buat admin (gak blokir); **FIFO cuma buat Kolektor** (isian awal, bisa diubah).
- **Klasifikasi tetap ditampilkan** (§8) di 5 tempat: Laporan Tagihan, Laporan Pembayaran (+filter), Laporan Bulanan Admin, Laporan Bayar Kolektor, Audit Internal — dihitung lewat satu method `Payment::classification()` (pola sama `installmentContext()`), badge = beberapa pil kecil sebelahan. Laporan Bulanan Admin & Bayar Kolektor **gak perlu filter tambahan**, cukup kolom.

**Fondasi buat:** kerja duluan, area `PaymentService` yang disentuh task lain.

---

## 6. Perubahan Kwitansi — ADHOC-94

**Dokumen:** [`analisa-rancangan-perubahan-kwitansi.md`](analisa-rancangan-perubahan-kwitansi.md) (referensi visual: [`kwitansi.md`](kwitansi.md)/[`kwitansi.html`](kwitansi.html))
**Status:** ✅ Clear, tidak ada pertanyaan terbuka.

**Inti:**
- **Satu template kwitansi identik** buat staf & Portal pelanggan — struk thermal 80mm & layout A4-Stripe lama **dihapus total**.
- Info staf (Diterima oleh/Catatan/status badge berwarna) dibuang dari kertas.
- Kertas cetak fisik: **NCR 2-ply blangko polos, ukuran 9,5×5,5 inci** — `@page` dikunci ke ukuran itu (khusus jalur cetak fisik staf; PDF Portal tetap A4).
- `$isCustomerCopy` dihapus, `$isPdf` dipertahankan (murni teknis render).
- JSON Portal (`PaymentReceiptResource`) gak dipangkas lebih jauh, tetap ikut satu sumber `ReceiptPresenter`.

---

## 7. Master Rekening Bank + Nama Pengirim + Sembunyikan Badge — ADHOC-95

**Dokumen:** [`analisa-rancangan-master-rekening-transfer.md`](analisa-rancangan-master-rekening-transfer.md)
**Status:** ✅ Clear, tidak ada pertanyaan terbuka.

**Inti (3 perubahan satu batch):**
1. **Master Rekening Bank** (`bank_accounts`, global bukan per-POP) — dropdown pilih rekening di form bayar Transfer, ganti input teks bebas. `payments.bank_account_id` FK + snapshot nama/nomor tetap di kolom lama (riwayat gak berubah kalau rekening diedit belakangan). Permission `master.rekening` lewat matrix RBAC (gak dihardcode ke role tertentu).
2. **Kolom "Nama Pengirim"** (`payments.sender_name`) — cuma tampil buat metode Transfer & Kolektor, gak masuk kwitansi cetak.
3. **Sembunyikan badge status** "Belum Dibayar"/"Sebagian" — khusus halaman Tagihan Belum Lunas, elemen badge **gak dirender sama sekali** (bukan pil kosong) buat kedua status. Daftar Tagihan umum gak berubah.

---

## 8. *(digabung ke #7 — satu dokumen, tiga perubahan)*

---

## 9. Saldo Pelanggan (Bayar di Muka + Auto-Pakai) — ADHOC-92

**Dokumen:** [`analisa-rancangan-saldo-pelanggan.md`](analisa-rancangan-saldo-pelanggan.md)
**Status:** ✅ Clear, siap kerja — dependency ADHOC-90 udah Selesai.

**Inti:**
- Studi kasus: paket 150k, prorate 100k, bayar 550k sekaligus → AWAL tetap 100k, 450k masuk Saldo, otomatis dipakai bayar BULANAN berikutnya begitu terbit (FIFO periode tertua dulu).
- Saldo kurang dari tagihan → tetap dipakai semua, sisa jadi cicilan (bukan nunggu saldo cukup).
- Kolom baru `payments.balance_used_amount` (disetujui) supaya laporan kas gak salah hitung saldo sebagai uang fisik.
- **Risiko utama:** credit lama bisa langsung kepake di generator tanggal 1 pertama setelah deploy — wajib `billing:apply-balance --dry-run` dulu sebelum go-live.

---

## 10. Request Deaktivasi + Cuti Berlangganan — ADHOC-87

**Dokumen:** [`analisa-rancangan-request-deaktivasi-bebas-tagihan-periode.md`](analisa-rancangan-request-deaktivasi-bebas-tagihan-periode.md)
**Status:** Hampir clear — keputusan utama udah dijawab semua (2026-09-21). Beberapa **default rancangan** (K1/K2/K3/K5/K6 di §7 dokumen: hard-delete jadi status `batal` bukan `DELETE`, satu kemampuan dua pintu, cuma invoice belum dibayar yang bisa dibebaskan, Cuti gak auto-isolir, cabut waiver gak hidupin invoice lagi) **boleh jalan apa adanya** kecuali lo koreksi — bukan blocker, cuma perlu direview sekilas sebelum coding.

**Inti:**
- Satu kemampuan, dua pintu: **Request Putus Langganan** (dropdown riwayat tagihan + catatan, terminate) dan **Cuti Berlangganan** (bebasin tagihan periode tanpa ubah status pelanggan).
- Tabel baru `customer_billing_waivers`. Jendela mundur **1 bulan** (bulan berjalan + 1 sebelumnya).
- Hanya invoice **Bulanan** tanpa pembayaran (`paid_amount = 0`) yang bisa dibebaskan — Aktivasi/Tagihan Manual/denda gak kena.
- Irisan sama ADHOC-69 (form Putus Langganan pakai ini dalam satu transaksi) & ADHOC-70 (denda tetap lewat Tagihan Manual, gak kena waiver).

---

## Checklist Cepat

- [ ] ADHOC-84 — Alokasi Pembayaran & Saldo
- [ ] ADHOC-70 — Tagihan Manual
- [ ] ADHOC-69 — Putus Langganan
- [ ] ADHOC-87 — Request Deaktivasi + Cuti
- [ ] ADHOC-92 — Saldo Pelanggan
- [ ] ADHOC-68 — Upgrade/Downgrade Paket
- [ ] ADHOC-94 — Perubahan Kwitansi *(independen, sisipkan kapan aja)*
- [ ] ADHOC-95 — Master Rekening + Nama Pengirim + Badge *(independen, sisipkan kapan aja)*
