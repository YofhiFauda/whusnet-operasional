# Analisa Aspek Krusial Keandalan & Keamanan pada Whusnet Operasional

Dokumen ini merangkum poin-poin krusial yang wajib diimplementasikan dan dijaga ketat agar aplikasi operasional internal ISP (Whusnet) dapat berjalan secara aman, presisi, dan andal di lingkungan produksi (*production*).

---

## 1. Proteksi Kebocoran Data Wilayah (Strict POP Scoping)
*   **Modul Terkait:** Master POP/Cabang, Pelanggan, Tagihan, Tugas Teknisi.
*   **Tantangan:** Admin Cabang atau teknisi di POP Ponorogo tidak boleh melihat data dari POP Madiun. Kebocoran data antar wilayah operasional melanggar batasan RBAC.
*   **Solusi & Best Practice:**
    *   Terapkan **Laravel Global Query Scopes** pada model utama: `Customer`, `FopTask`, `Invoice`, `Payment`.
    *   Gunakan trait scope global yang secara otomatis membatasi data query berdasarkan POP yang ditugaskan ke user login:
        ```php
        // Contoh implementasi global scope Laravel
        static::addGlobalScope('pop_scope', function (Builder $builder) {
            if (auth()->check() && !auth()->user()->hasFullAccess()) {
                $popIds = auth()->user()->pops()->pluck('pops.id')->toArray();
                $builder->whereIn('pop_id', $popIds);
            }
        });
        ```

---

## 2. Presisi Finansial & Keamanan Pembayaran (Double-Submit Guard)
*   **Modul Terkait:** Invoices & Payments.
*   **Tantangan:** Koneksi internet lambat sering kali memicu kasir menekan tombol *"Bayar"* berulang kali (double click/submit), yang berpotensi melipatgandakan catatan transaksi pembayaran di database untuk satu tagihan.
*   **Solusi & Best Practice:**
    *   **Frontend:** Gunakan state Alpine.js atau vanilla JS untuk menonaktifkan (`disabled`) tombol submit segera setelah klik pertama dilakukan.
    *   **Backend:** Gunakan **Database Transactions** disertai **Pessimistic Locking** (`lockForUpdate()`) ketika memperbarui status invoice dan membuat riwayat pembayaran:
        ```php
        DB::transaction(function () use ($invoiceId, $amount) {
            $invoice = Invoice::where('id', $invoiceId)->lockForUpdate()->first();
            // Lakukan kalkulasi sisa tagihan & simpan pembayaran
        });
        ```

---

## 3. Ketahanan Impor Data Massal (Robust Import Recovery)
*   **Modul Terkait:** Import Excel/CSV Pelanggan Lama.
*   **Tantangan:** Data warisan (legacy) dari database lama sering kali kotor atau memiliki format yang salah. Jika sistem membatalkan seluruh proses impor hanya karena beberapa baris data yang cacat, efisiensi kerja admin akan terganggu.
*   **Solusi & Best Practice:**
    *   Terapkan mekanisme **Partial Import** (impor sebagian).
    *   Data yang valid harus tetap masuk ke database, sementara baris data yang gagal ditolak dan direkam detail nomor baris serta alasan kesalahannya pada tabel `import_errors`.
    *   Tampilkan rangkuman data error ini di UI agar admin dapat mengunduh daftar error tersebut untuk dikoreksi secara mandiri tanpa perlu mengulang impor seluruh file.

---

## 4. Imutabilitas Tagihan Lunas (Immutable Paid Invoices)
*   **Modul Terkait:** Invoices & Financial Audit.
*   **Tantangan:** Manipulasi nominal atau penghapusan tagihan yang telah dinyatakan lunas oleh staff biasa rentan disalahgunakan untuk tindakan fraud/kecurangan keuangan.
*   **Solusi & Best Practice:**
    *   Buat otorisasi ketat melalui **Laravel Model Policy** (`InvoicePolicy`).
    *   Kunci hak akses untuk mengubah (`update`) atau menghapus (`delete`) data invoice yang sudah berstatus `Selesai` atau `Lunas`.
    *   Jika ada kesalahan input pada tagihan lunas, penyelesaian wajib melalui proses **Void/Pembatalan Resmi** yang tercatat di audit log dan memerlukan otorisasi tingkat supervisor/Owner.

---

## 5. Detail Perubahan Riwayat Data (Comprehensive Audit Trail)
*   **Modul Terkait:** Audit Log.
*   **Tantangan:** Mengetahui *"siapa yang mengubah data"* tidaklah cukup untuk menyelesaikan perselisihan data (*data dispute*). Sistem perlu mengetahui nilai asli sebelum dan sesudah data diubah.
*   **Solusi & Best Practice:**
    *   Log perubahan tidak hanya mencatat aktivitas secara mentah, tetapi harus menyimpan struktur data lama (*original values*) dan data baru (*dirty/changed values*) dalam format JSON.
    *   Gunakan Laravel Model Observers untuk mendeteksi perubahan model secara terpusat:
        ```php
        public function updated(Customer $customer)
        {
            AuditLog::create([
                'user_id' => auth()->id(),
                'model' => Customer::class,
                'model_id' => $customer->id,
                'action' => 'update',
                'old_values' => json_encode(array_intersect_key($customer->getOriginal(), $customer->getChanges())),
                'new_values' => json_encode($customer->getChanges()),
            ]);
        }
        ```

---

## 6. Peringatan Dini Pelanggaran SLA Tugas Lapangan
*   **Modul Terkait:** Task FOP & Tugas Teknisi.
*   **Tantangan:** Keterlambatan survei dan pemasangan baru berdampak langsung pada reputasi ISP. FOP membutuhkan indikator visual yang jelas mengenai tugas mana yang mendekati batas waktu penyelesaian.
*   **Solusi & Best Practice:**
    *   Hitung mundur durasi SLA secara dinamis berdasarkan jenis tugas sejak tugas tersebut dibuat.
    *   Jika sisa waktu penyelesaian kurang dari ambang batas (misal: 2 jam), ubah prioritas task secara otomatis di dashboard FOP menjadi **Urgent (Merah)** dan kirimkan notifikasi in-app kepada admin FOP agar mereka dapat mengalokasikan teknisi cadangan dengan cepat.
