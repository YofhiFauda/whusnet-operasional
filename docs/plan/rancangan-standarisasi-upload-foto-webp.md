# Rancangan Standarisasi Upload Foto dan Kompresi WebP

**Status:** Rancangan, belum diimplementasikan. Dibuat atas permintaan user pada 2026-10-29; alur Queue/Redis dan aturan WebP telah dikonfirmasi.

## Tujuan dan ruang lingkup

Pengguna dapat mengunggah JPG/JPEG, PNG, atau WebP sampai 10 MB per file. Sesudah form dikirim, sistem menangani konversi dan kompresi tanpa langkah tambahan dari pengguna. JPG/JPEG dan PNG disimpan sebagai WebP. WebP yang sudah sampai dengan 1 MB tidak dikompresi ulang; WebP yang lebih besar diproses agar hasil kompresinya di bawah 1 MB.

Rancangan ini berlaku untuk foto pada 24 halaman yang teridentifikasi dalam audit kode. Beberapa form juga menerima PDF; PDF tidak dikonversi dan batas PDF yang berlaku saat ini tidak berubah sampai ada keputusan tersendiri. Foto lama di storage tidak dimigrasikan dalam tahap awal.

## Kondisi saat ini

- Batas upload gambar per modul berbeda: umumnya 2 MB, beberapa 4 MB dan 5 MB, lampiran kuitansi kolektor 8 MB per file.
- `app/Services/FileUploadService.php` menyimpan unggahan dengan `storeAs()` tanpa resize, konversi, atau kompresi.
- `docker/php/local.ini` dan `docker/php/production.ini` menetapkan `upload_max_filesize = 64M`. Saat implementasi tetap perlu memeriksa batas request server/proxy.
- Atribut `accept` pada form tidak cukup sebagai validasi; backend harus memeriksa isi dan MIME file.

### Inventaris 24 halaman

| Kelompok | Halaman |
|---|---|
| Pelanggan (5) | `customers/create`, `customers/edit`, `customers/index` (modal Quick Hub), `customers/show`, `customers/fieldwork` (tab pemasangan) |
| Survei dan pemasangan (2) | `surveys/report`, `installations/report` |
| Tugas lapangan dan FOP (4) | `tasks/device-retrieval-report`, `tasks/maintenance-report`, `tasks/infrastructure-report`, `fop_tasks/index` |
| Pembayaran dan kolektor (4) | `payments/create`, `payments/edit`, `collector-worksheet/show`, `collector-worksheet/index` (komponen setor kas) |
| Tiket (1) | `tickets/create` |
| Gudang (8) | `warehouse/branch-sales/show`, `warehouse/returns/from-customer`, `warehouse/adjustments/repair-serial`, `warehouse/adjustments/unit-opname`, `warehouse/adjustments/serial`, `warehouse/adjustments/roll`, `warehouse/adjustments/custody`, `warehouse/transfers/show` |

Komponen upload yang dipakai ulang dihitung menurut halaman tempat ia muncul. Inventaris ini perlu dicocokkan ulang dengan route, controller, dan validasi backend sebelum implementasi karena repo dapat berubah.

## Aturan hasil yang diinginkan

1. Gambar masuk: JPG/JPEG, PNG, dan WebP, maksimal 10 MB **per file**, termasuk unggahan jamak.
2. JPG/JPEG dan PNG selalu dikonversi ke WebP. WebP yang sudah <= 1 MB disimpan tanpa encode ulang.
3. WebP berukuran lebih dari 1 MB diproses dengan penurunan dimensi dan/atau kualitas bertahap.
4. Hasil akhir memakai ekstensi `.webp`, MIME `image/webp`, dan path/URL aplikasi mengarah ke file hasil akhir.
5. Target **hasil kompresi** adalah kurang dari 1 MB. Sesuai keputusan user, WebP masuk yang tepat 1 MB boleh langsung siap tanpa kompresi. Definisi byte untuk 1 MB dan 10 MB harus diputuskan sebelum coding.
6. PDF tetap mengikuti aturan format dan ukuran masing-masing modul sampai kebijakan PDF dibahas terpisah.

## Alur pemrosesan asinkron yang dipilih

1. Pengguna mengunggah JPG/JPEG, PNG, atau WebP sampai 10 MB per file. Server memvalidasi MIME/isi, ukuran, serta batas dimensi/piksel.
2. WebP yang sudah <= 1 MB langsung disimpan sebagai file final berstatus `ready`, tanpa job kompresi.
3. JPG/JPEG, PNG, dan WebP di atas 1 MB disimpan sementara di storage. Catatan foto berstatus `pending` dibuat. Sesudah file dan catatan tersedia, job dikirim ke Queue berbasis Redis. Redis hanya menyimpan ID/path, bukan data file.
4. Server langsung merespons bahwa foto diterima dan sedang diproses. Pengguna tidak menunggu konversi; foto `pending` belum boleh dianggap bukti siap pakai.
5. Worker pada antrean foto mengubah status menjadi `processing`, memperbaiki orientasi, menjaga transparansi, melakukan resize/konversi WebP, serta menurunkan dimensi/kualitas secara bertahap dengan menjaga keterbacaan.
6. Worker menyimpan hasil sebagai file baru, memverifikasi MIME dan ukuran, lalu memperbarui path dan status menjadi `ready`. File asli sementara baru dihapus setelah file final dan referensi database berhasil disimpan.
7. Jika job gagal, status menjadi `failed` dan alasan dicatat. Retry harus idempoten; penggantian foto tidak boleh menghapus foto lama atau menimpa unggahan lebih baru.

### Kendali beban dan pemulihan

- Antrean foto dan batas worker/memorinya terpisah dari antrean `default`, `kwitansi`, dan `webhooks`; jumlah worker ditentukan melalui uji beban.
- Batasi dimensi/piksel dan memori saat decoding. Sediakan ruang file sementara untuk lonjakan upload serentak.
- Pantau panjang antrean, waktu tunggu, durasi job, kegagalan, CPU/RAM, dan kapasitas storage.
- Bersihkan file sementara yatim dengan aturan umur dan pengecekan status agar file aktif tidak ikut terhapus.
- Form yang mewajibkan foto bukti hanya menganggap bukti siap ketika status `ready`. UI menampilkan status `pending`, `processing`, dan `failed`.

## Keputusan yang masih terbuka

1. Jika foto bukti tidak bisa diperkecil ke bawah 1 MB tanpa kehilangan keterbacaan: **tolak upload** atau **izinkan pengecualian** untuk kategori bukti tertentu?
2. Apakah 1 MB berarti `< 1.000.000` byte atau `< 1.048.576` byte? Apakah batas masuk 10 MB desimal atau 10 MiB?
3. Apakah PDF tetap memiliki batas per modul yang sekarang, atau akan diseragamkan dalam rancangan terpisah?

## Kriteria penerimaan implementasi kelak

- Semua 24 halaman menerima tiga format gambar tersebut hingga batas masuk yang disepakati, dengan validasi backend.
- JPG/JPEG dan PNG tersimpan sebagai WebP; WebP masuk yang sampai dengan 1 MB tidak dikompresi ulang.
- Hasil yang melebihi target ditangani menurut keputusan kualitas/pengecualian, dan ukuran dicek pada file tersimpan.
- File rusak, format lain, ukuran berlebih, dan dimensi ekstrem ditolak dengan pesan yang tepat.
- Orientasi, transparansi, dan keterbacaan foto bukti terjaga.
- Respons upload tidak menunggu job; Redis hanya menyimpan ID/path; status `pending` -> `processing` -> `ready`/`failed` dapat ditelusuri.
- Unggahan jamak, penggantian foto, retry idempoten, kegagalan proses, pembersihan file sementara, akses file, dan POP scope diuji.
- PDF dan foto lama tidak berubah tanpa keputusan eksplisit.

## Langkah implementasi kelak

Setelah keputusan terbuka dijawab: audit ulang route/validasi seluruh form, pilih pemroses gambar yang tersedia tanpa mengubah dependensi tanpa persetujuan, bangun antrean foto dan jalur pemrosesan bersama, terapkan bertahap per kelompok, lalu jalankan tes fitur, uji visual, dan uji beban upload serentak. Task aktif lain tetap mengikuti `docs/TASKS.md`.
