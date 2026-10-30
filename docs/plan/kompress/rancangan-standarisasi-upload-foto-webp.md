# Rancangan Standarisasi Upload Foto dan Kompresi WebP

**Status:** Rancangan, belum diimplementasikan. Dibuat 2026-10-29; direvisi setelah tinjauan celah alur asinkron.

## Tujuan dan ruang lingkup

Pengguna dapat mengunggah JPG/JPEG, PNG, atau WebP sampai 10 MB per file. Sesudah form dikirim, sistem menangani konversi dan kompresi tanpa langkah tambahan dari pengguna. JPG/JPEG dan PNG disimpan sebagai WebP. WebP yang sudah sampai dengan 1 MiB tidak dikompresi ulang; WebP yang lebih besar diproses dengan target sampai dengan 1 MiB, dengan kebijakan pengecualian bukti yang tetap terbaca.

Rancangan ini berlaku untuk foto pada 24 halaman yang teridentifikasi dalam audit kode. Beberapa form juga menerima PDF; PDF tidak dikonversi dan batas PDF yang berlaku saat ini tidak berubah sampai ada keputusan tersendiri. Foto lama di storage tidak dimigrasikan dalam tahap awal.

## Kondisi saat ini

- Batas upload gambar per modul berbeda: umumnya 2 MB, beberapa 4 MB dan 5 MB, lampiran kuitansi kolektor 8 MB per file.
- `app/Services/FileUploadService.php` menyimpan unggahan dengan `storeAs()` tanpa resize, konversi, atau kompresi.
- `docker/php/local.ini` dan `docker/php/production.ini` menetapkan `upload_max_filesize = 64M`. Saat implementasi tetap perlu memeriksa batas request server/proxy.
- Atribut `accept` pada form tidak cukup sebagai validasi; backend harus memeriksa isi dan MIME file.
- `docker-compose.prod.yml` memasang volume `storage_data:/var/www/storage` pada `app` dan `horizon`. Disk `local` berakar di `storage/app/private`; ini dapat dipakai bersama **jika deployment Coolify menjalankan compose tersebut dan volume yang sama**. Verifikasi runtime wajib sebelum coding. Bila deployment ternyata terpisah tanpa volume bersama, pilih shared volume atau disk privat S3/MinIO terlebih dahulu.
- Container `horizon` produksi dibatasi 0,5 CPU dan 512 MB RAM dalam compose. Kapasitas foto belum terbukti; jangan menaikkan jumlah worker tanpa uji beban.
- `Dockerfile.prod` memasang `libwebp-dev` dan mengonfigurasi GD dengan `--with-webp`; kemampuan encode/decode WebP tetap perlu diuji pada image hasil build.

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

## Keputusan yang sudah dikunci

1. Input gambar JPG/JPEG, PNG, dan WebP; maksimum **10 MiB per file = 10.485.760 byte = 10240 KiB**. Label UI boleh menulis “10 MB”, dengan penjelasan internal bahwa satuan teknisnya MiB. PDF tetap memakai aturan saat ini dan tidak masuk konversi foto.
2. Target normal file final **<= 1 MiB = 1.048.576 byte**. WebP input yang sudah <= 1 MiB langsung siap tanpa encode ulang, **setelah** pemeriksaan header, dimensi, dan validasi decode. Dengan demikian kasus tepat 1 MiB konsisten.
3. JPG/JPEG dan PNG dikonversi menjadi WebP. WebP di atas 1 MiB diproses ulang. Foto bukti yang tidak dapat mencapai target tanpa merusak keterbacaan dapat diterima sebagai `over_target` dengan batas keras **yang diusulkan 2 MiB**, bukan ditolak terlambat setelah respons upload. Angka 2 MiB masih perlu persetujuan.
4. Pemrosesan dilakukan asinkron. Redis berisi ID/path dan metadata kecil untuk job, bukan byte gambar. Foto sementara disimpan pada disk privat yang dapat dibaca **web dan worker**.
5. PDF, foto lama, dan aturan bisnis modul tidak berubah secara diam-diam.

## Alur penyimpanan dan status

1. Web memvalidasi jenis file berdasarkan isi, ukuran 10 MiB, batas piksel/dimensi, dan kemampuan decoder. Semua WebP, termasuk yang <= 1 MiB, diperiksa agar file rusak atau gambar berukuran piksel ekstrem tidak lolos.
2. WebP <= 1 MiB yang valid disimpan sebagai final dan berstatus `ready`. Gambar lain disimpan di disk privat sementara; dibuat catatan upload berstatus `pending` dengan ID unik, pemilik, field tujuan, path sementara, versi unggahan, dan waktu dibuat.
3. Sesudah file sementara ada dan transaksi database commit, job dikirim dengan `afterCommit`. Respons menyatakan **upload diterima dan foto sedang diproses**; ini belum berarti bukti sudah siap.
4. Worker membaca foto dari disk yang sama, menandai `processing`, melakukan resize/konversi, menyimpan hasil ke path final baru, lalu memeriksa MIME, dimensi, dan ukuran file hasil. Update referensi database dilakukan hanya bila versi unggahan itu masih terbaru untuk field tujuan. Retry harus idempoten.
5. Sesudah path final dan status `ready` atau `over_target` tersimpan, file asli sementara boleh dihapus. Pada `failed`, file asli **tetap disimpan** untuk retry/admin sampai masa retensi yang ditetapkan; file lama dari proses penggantian tetap berlaku.
6. Job yang hilang atau macet ditangani scheduler: scan `pending`/`processing` yang melewati ambang, cek kondisi file dan lease/lock, lalu kirim ulang secara idempoten. TTL, batas percobaan, alarm, dan pembersihan file yatim harus didefinisikan sebelum implementasi.

## Pemetaan dengan kolom foto yang sudah ada

Saat ini berbagai modul menyimpan path di kolom masing-masing, misalnya `customer_surveys.survey_photo`, `customer_installations.installation_photo`, dan `task_maintenances.opm_photo`. Kolom lama **tidak boleh sementara diisi path file privat**: pembaca lama dapat menampilkannya sebagai URL publik yang rusak, dan worker dapat menimpa foto baru.

**Pilihan rancangan yang direkomendasikan:** satu tabel metadata upload/media untuk status, path temp, path final, pemilik/POP, nama field, versi, ukuran, dan kegagalan. Kolom path lama tetap menunjuk **hanya** ke foto final yang siap dipakai, diperbarui secara atomik setelah job berhasil. Ini menjaga pembaca lama sambil memberi satu sumber status untuk 24 halaman. Sebelum coding, audit semua pembaca/penulis path serta pastikan cara mengikat media ke model dan field tidak membuka akses lintas POP. Opsi migrasi penuh ke tabel media polimorfik dapat dinilai terpisah; tidak diasumsikan sebagai perubahan otomatis.

## Hubungan status foto dengan form dan tugas

Form laporan **boleh disubmit ketika foto `pending`** agar staf tidak menunggu di lokasi. Status laporan/tugas yang sudah ada tetap harus mencatat bahwa bukti masih diproses; aksi yang benar-benar mensyaratkan bukti siap (misalnya validasi/penutupan yang memakai foto itu) hanya berjalan saat foto `ready` atau `over_target` yang diizinkan. UI menampilkan status dan alasan `failed`; kegagalan harus menghasilkan notifikasi/tindak lanjut yang dapat dilakukan tanpa mengulang laporan. Sebelum coding, petakan aturan ini untuk setiap modul karena status tugas dan syarat bukti berbeda.

File sementara berada di disk **privat**. Akses pratinjau/status melalui controller yang memeriksa permission dan POP scope; jangan membangun URL publik ke file `pending`. File final juga perlu diaudit karena konfigurasi saat ini melayani `storage/app/public` langsung melalui nginx.

## Kebijakan kualitas dan kapasitas

- Untuk bukti berisi teks, uji batas bawah dimensi dan kualitas pada sampel nyata. Awal uji yang **diusulkan**, bukan angka final: sisi terpanjang >= 1280 px dan kualitas >= 60; jika di bawah itu teks tidak terbaca, simpan sebagai `over_target` hingga batas keras.
- Pertimbangkan WebP lossless untuk PNG berwarna sedikit/transparan; bandingkan ukuran dan keterbacaan. Pencarian kualitas dengan binary search terbatas (sekitar empat percobaan) dapat menghemat CPU dibanding penurunan berulang, tetapi hasilnya harus dibuktikan lewat pengukuran.
- Antrean foto memiliki worker dan batas CPU/RAM sendiri agar antrean `default`, `kwitansi`, dan `webhooks` tidak terganggu. Ukur penggunaan memori saat decode foto berdimensi besar, panjang antrean, waktu tunggu, waktu proses, dan ruang file sementara.
- Resize opsional di browser dapat menghemat kuota staf pada sinyal lemah, tetapi server tetap memvalidasi dan memproses ulang. Jangan menjadikan kemampuan browser sebagai syarat upload.
- Pilot satu kelompok kecil, misalnya Tiket (satu halaman), dengan feature flag dan rollback sebelum sebar ke halaman lain. Uji juga kondisi Redis restart, worker mati, job ganda, dan penggantian foto cepat.

## Keputusan dan verifikasi sebelum coding

1. **Storage deployment:** verifikasi bahwa web dan Horizon produksi sungguh memakai `storage_data` bersama. Jika arsitektur Coolify berbeda, putuskan shared volume atau S3/MinIO privat lebih dahulu.
2. **Batas pengecualian:** setujui atau ubah batas keras 2 MiB untuk `over_target`, kategori bukti yang boleh memakainya, serta tindakan bila hasil tetap melewati batas keras.
3. **Skema media:** setujui pendekatan tabel metadata upload + kolom lama untuk path final; tentukan pemetaan model/field dan aturan versi penggantian foto.
4. **Workflow per modul:** tentukan status/aksi yang boleh berjalan saat `pending`, `processing`, `failed`, dan `over_target`; jangan membuat laporan tugas macet atau menganggap bukti gagal sebagai selesai.
5. **Retensi:** tentukan berapa lama file temp `failed` disimpan dan siapa yang dapat retry atau menangani manual.
6. **Kualitas:** uji sampel untuk menentukan dimensi/kualitas minimum; periksa dukungan GD/Imagick WebP pada image runtime.
7. **PDF:** tetap sesuai batas per modul yang sudah ada. Jika hendak diseragamkan, buat keputusan dan rancangan terpisah.

## Kriteria penerimaan implementasi kelak

- Semua jalur gambar pilot menerima format dan ukuran yang ditetapkan; WebP kecil tetap diperiksa decoder/dimensi sebelum `ready`.
- Web dan worker membaca file sementara yang sama pada deployment nyata; Redis hanya menyimpan ID/path.
- Respons upload cepat, sementara status foto dapat ditelusuri sampai `ready`, `over_target`, atau `failed`.
- Kolom foto lama tidak pernah berisi path temp; update final aman terhadap retry, job ganda, dan unggahan pengganti.
- File asli hanya dihapus setelah final siap; file `failed` dapat di-retry atau ditangani admin sesuai retensi.
- Scheduler memulihkan catatan `pending` yang kehilangan job; uji Redis restart dan worker mati.
- Bukti tetap terbaca dan POP scope berlaku pada file sementara, status, pratinjau, serta file final.
- Uji beban menentukan jumlah worker dalam batas CPU/RAM aktual sebelum cakupan diperluas.

## Urutan kerja kelak

Audit pembaca/penulis path dan workflow tiap modul -> verifikasi storage/codec runtime -> putuskan skema/status/pengecualian -> pilot dengan feature flag -> uji fungsional, visual, dan beban -> perluas bertahap. **Dokumen ini tidak mengizinkan implementasi sebelum user memintanya.**

## Asumsi kapasitas VPS untuk perencanaan

User merencanakan **VPS 4 vCPU, 8 GB RAM, 50 GB storage**. Bila Coolify, aplikasi Laravel (app/nginx/Horizon/Reverb/scheduler), MySQL, Redis, dan pemrosesan foto semuanya ditempatkan pada VPS ini, seluruh service berbagi CPU, RAM, I/O, dan disk fisik walaupun MySQL/Redis dideploy sebagai resource terpisah di Coolify. Lokasi nyata MySQL/Redis belum diverifikasi.

**Penilaian awal:** 4 vCPU/8 GB RAM layak sebagai titik awal pilot dan penggunaan internal sedang, dengan antrean foto dibatasi. Ini belum menjamin kapasitas produksi tanpa uji upload serentak. Compose saat ini membatasi container Horizon pada 0,5 CPU dan 512 MB RAM; batas tersebut perlu diuji ulang sebelum menambah worker foto. Build Docker di VPS yang sama juga membutuhkan ruang dan memori sementara.

**50 GB storage tidak layak dijadikan kapasitas final foto lokal** untuk skala pelanggan besar. Ilustrasi batas atas: 30.000 pelanggan x 1 foto x 1 MiB ~= 29,3 GiB; tiga foto per pelanggan ~= 87,9 GiB. Perhitungan ini belum memasukkan PDF, foto `over_target`, file sementara 10 MiB, MySQL, image/layer Docker, log, sistem operasi, atau backup. Kapasitas nyata bergantung jumlah foto dan ukuran rata-rata.

**Rekomendasi infrastruktur:** bila semua berkas tetap di VPS, siapkan volume foto yang dapat diperbesar; target perencanaan awal **150-200 GB** untuk skenario puluhan ribu pelanggan dengan beberapa foto per pelanggan, lalu sesuaikan dengan proyeksi nyata. Alternatifnya, pertahankan VPS 50 GB untuk aplikasi/database tetapi gunakan object storage **di luar VPS** untuk foto dan backup. MinIO pada disk 50 GB yang sama tidak menambah kapasitas. Backup wajib berada di luar disk/server yang sama. Tetapkan rotasi log, ambang peringatan kapasitas, dan ruang bebas untuk file sementara serta redeploy.

Sebelum memilih paket VPS final, ukur jumlah pelanggan, rata-rata foto per pelanggan, ukuran WebP rata-rata, pertumbuhan bulanan, retensi file gagal, ukuran database/backup, dan puncak upload bersamaan. Jangan menetapkan jumlah worker atau klaim kapasitas pasti dari spesifikasi VPS saja.

## Audit konfigurasi PHP dan container untuk VPS 4 vCPU / 8 GB / 50 GB

| Komponen | Konfigurasi repo saat ini | Implikasi sebelum fitur foto |
|---|---|---|
| PHP dan nginx | `upload_max_filesize = 64M`, `post_max_size = 64M`, `max_file_uploads = 20`, `client_max_body_size 64M` | Satu foto 10 MiB dapat lewat; beberapa foto dalam satu form tetap dibatasi **total request** 64M. Batas gambar 10 MiB harus ditegakkan oleh validasi aplikasi, bukan mengubah limit PHP global tanpa mengaudit import/lampiran lain. |
| PHP-FPM | `FPM_MAX_CHILDREN=20` default, `memory_limit=512M` per proses; service `app` tidak memiliki batas RAM container | Angka 20 bukan jaminan aman pada RAM 8 GB. Ukur RSS puncak per worker dan jumlah request serentak; 20 proses pada batas PHP teoritis dapat melebihi RAM VPS. Kompresi tidak dijalankan di proses request. |
| Horizon | Container dibatasi 0,5 CPU / 512 MB; pool produksi dapat mencapai 10 worker default + 4 kwitansi + 2 webhook | Jangan menambah job foto ke container/limit ini tanpa uji memori. Rencanakan worker foto terpisah, mulai satu proses dengan batas CPU/RAM yang diuji, agar antrean penting tidak tertahan. |
| Storage | Volume `storage_data` bersama di compose, tetapi hanya 50 GB disk VPS yang direncanakan | Bila MySQL, Redis, Docker, log, file final, dan temp berbagi disk ini, ruang foto cepat habis. MinIO pada disk VPS yang sama tidak menambah kapasitas. |

Penilaian ini berasal dari konfigurasi repo, **bukan pengukuran VPS yang berjalan**. Lokasi nyata MySQL/Redis, limit container yang diterapkan Coolify, penggunaan RAM puncak, dan ruang disk bebas perlu diperiksa pada deployment. Untuk pilot satu worker foto mungkin cukup; jumlah worker final ditetapkan melalui uji beban dan pemantauan. Backup foto/database perlu berada di luar disk VPS.

## Masa simpan foto asli sementara (usulan operasional)

| Kondisi | Berapa lama file asli 10 MiB disimpan | Kapan boleh dihapus |
|---|---|---|
| WebP masuk <= 1 MiB | Tidak membuat file temp untuk kompresi; file valid langsung menjadi final. | Mengikuti kebijakan penghapusan foto final modul, bukan jadwal pembersihan temp. |
| Job berhasil: `ready` atau `over_target` | Sampai WebP final tersimpan dan referensi database/status berhasil di-commit. Normalnya selama antrean dan proses berlangsung; **tidak ada jaminan menit tertentu** tanpa uji beban. | Hapus **segera setelah commit sukses**. Jika penghapusan gagal, pembersih terjadwal mencoba lagi setiap jam dan memberi alarm bila masih tertinggal. |
| Masih `pending`/`processing` | Tetap disimpan selama job sah masih berjalan atau menunggu. | Jangan hapus hanya karena umur file. Jika usia status > 15 menit, beri peringatan dan periksa/re-dispatch secara idempoten; angka 15 menit adalah ambang alarm awal, bukan waktu hapus. |
| Job `failed` | **Minimal 7 hari** sejak gagal sebagai usulan waktu retry/penanganan admin; pengunggah dan admin diberi tahu segera. | Setelah hari ke-7, hapus hanya bila foto sudah diganti, diproses ulang, atau admin telah menyelesaikan kasus dan bukti aman. File yang belum terselesaikan **tidak dihapus diam-diam**; eskalasi dan, jika perlu, pindahkan ke arsip privat di luar VPS. |
| File temp yatim tanpa catatan upload/job | Maksimal 24 jam sebagai usulan masa toleransi. | Pembersih boleh menghapus setelah memastikan file tidak direferensikan job, database, atau proses aktif. |

Dengan aturan ini, **foto asli yang berhasil diproses dibuang segera**, sedangkan foto gagal tidak mempunyai tanggal hapus otomatis yang mengorbankan bukti. Pada VPS 50 GB, kegagalan yang menumpuk harus memicu alarm kapasitas; bila diperlukan batas retensi keras di VPS, harus tersedia arsip di luar VPS atau keputusan admin sebelum penghapusan. File WebP final tidak ikut dihapus oleh pembersih file sementara.

Angka **15 menit, 1 jam, 7 hari, dan 24 jam adalah usulan**, belum diimplementasikan. Saat pilot, ukur durasi antrean dan tingkat kegagalan untuk menyesuaikannya tanpa mengurangi keamanan bukti.

## Auto retry dan perlindungan foto penting (usulan)

**Status saat ini:** aturan berikut adalah rancangan; kode upload sekarang belum menjalankan auto retry kompresi.

1. Jika worker terhenti, Redis sementara tidak tersedia, atau pembacaan/penulisan storage gagal sesaat, job dicoba **maksimal tiga kali ulang** setelah percobaan pertama, dengan jeda awal 1, 5, lalu 15 menit. Retry hanya untuk kegagalan sementara; job harus idempoten agar tidak membuat file ganda atau menimpa unggahan terbaru.
2. File rusak/format tidak valid semestinya ditolak sebelum respons upload. Jika kerusakan baru terdeteksi di worker, atau sumber daya seperti disk penuh tetap bermasalah, jangan mengulang tanpa batas. Tandai `failed`, catat penyebab, dan beri tahu pengunggah serta admin.
3. Selama `pending`, `processing`, maupun `failed`, **foto asli tetap di storage privat bersama**. Pengguna berizin dapat melihat atau mengunduh file asli melalui controller dengan pemeriksaan permission dan POP scope, sehingga bukti tidak hilang hanya karena kompresi gagal. Akses darurat perlu dicatat di audit log.
4. Sesudah semua retry otomatis habis, admin dapat menjalankan retry manual setelah penyebab diperbaiki. Bila bukti valid tetapi kompresor tetap gagal, admin perlu jalur penanganan manual yang menjaga file asli sebagai bukti privat dan menandai pengecualian secara tercatat; kebijakan apakah file asli itu boleh menjadi bukti final belum diputuskan.
5. Laporan yang sudah disubmit tetap tersimpan. Status foto `failed` harus terlihat jelas dan memicu tindak lanjut; validasi/penutupan yang membutuhkan bukti siap mengikuti aturan workflow per modul, tanpa memaksa teknisi mengisi ulang seluruh laporan.
6. Scheduler memeriksa record `pending`/`processing` yang kehilangan job dan mengirim ulang secara idempoten. Ini melindungi dari job yang hilang setelah Redis restart, tetapi tidak menggantikan backup/persistence Redis.
7. **Tujuh hari pada tabel retensi adalah batas peninjauan dan eskalasi, bukan jadwal hapus otomatis untuk bukti gagal.** File asli baru boleh dihapus sesudah WebP final aman, bukti pengganti terverifikasi, atau keputusan admin dengan arsip aman. Jika belum selesai, file harus tetap tersedia dan kapasitas storage diberi alarm.

Jeda retry, jumlah percobaan, hak akses bukti asli, dan jalur pengecualian perlu diuji pada pilot sebelum dipakai di seluruh 24 halaman. Tidak ada foto asli yang dibuang hanya karena job gagal.
