@extends('layouts.app')

@section('title', 'Permission Matrix — ' . $role->name)
@section('page_title', 'Permission Matrix: ' . $role->name)

@section('content')

@php
// 1. Pemetaan Kategori Fungsional (Grouping Berdasarkan Fungsi)
$functionalCategories = [
    'group_users' => [
        'title' => 'Pengguna & Hak Akses',
        'subtitle' => 'Pengaturan akun staf, grup peran jabatan, dan pembatasan izin keamanan sistem.',
        'icon' => 'user-group',
        'badge' => 'bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-400 border-indigo-200 dark:border-indigo-800',
        'features' => ['users', 'roles', 'audit_logs'],
    ],
    'group_master' => [
        'title' => 'Master Data & Infrastruktur',
        'subtitle' => 'Pengelolaan data area wilayah, cabang POP, paket internet, barang stok, dan aturan SLA.',
        'icon' => 'database',
        'badge' => 'bg-sky-50 dark:bg-sky-950/50 text-sky-700 dark:text-sky-400 border-sky-200 dark:border-sky-800',
        'features' => [
            'master_wilayah', 'pops', 'master_distribusi', 'packages',
            'master_status_pelanggan', 'sla_timeline', 'item_categories',
            'items', 'work_tools', 'ticket_issue_categories'
        ],
    ],
    'group_customers' => [
        'title' => 'Data Pelanggan & Profil',
        'subtitle' => 'Pusat pengelolaan registrasi pelanggan, impor data massal, status layanan, dan berkas pelanggan.',
        'icon' => 'users',
        'badge' => 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
        'features' => ['customers'],
    ],
    'group_field' => [
        'title' => 'Layanan Lapangan & Teknisi',
        'subtitle' => 'Manajemen pekerjaan teknisi di lapangan: survei lokasi, pasang baru, tiket FOP, dan kelola tugas.',
        'icon' => 'wrench',
        'badge' => 'bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800',
        'features' => [
            'customers.detail.survey', 'customers.detail.installation',
            'fop_tasks', 'tasks', 'tickets', 'noc_dashboard', 'noc_worksheet'
        ],
    ],
    'group_billing' => [
        'title' => 'Tagihan & Keuangan',
        'subtitle' => 'Penerbitan tagihan bulanan, pencatatan pembayaran kasir, serta penagihan lapangan kolektor.',
        'icon' => 'credit-card',
        'badge' => 'bg-violet-50 dark:bg-violet-950/50 text-violet-700 dark:text-violet-400 border-violet-200 dark:border-violet-800',
        'features' => ['invoices', 'payments', 'kolektor', 'collector_worksheet', 'cash_deposit'],
    ],
    'group_reports' => [
        'title' => 'Dashboard & Laporan',
        'subtitle' => 'Ringkasan indikator kinerja operasional (KPI) dan ekspor laporan rekapitulasi data.',
        'icon' => 'chart-bar',
        'badge' => 'bg-teal-50 dark:bg-teal-950/50 text-teal-700 dark:text-teal-400 border-teal-200 dark:border-teal-800',
        'features' => ['dashboard', 'reports'],
    ],
    'group_warehouse' => [
        'title' => 'Gudang & Inventory',
        'subtitle' => 'Distribusi barang dari Gudang Pusat ke Cabang, custody teknisi, dan pelacakan aset bernomor seri.',
        'icon' => 'archive-box',
        'badge' => 'bg-orange-50 dark:bg-orange-950/50 text-orange-700 dark:text-orange-400 border-orange-200 dark:border-orange-800',
        'features' => [
            'warehouse', 'warehouse_transfer', 'warehouse_issue',
            'warehouse_custody', 'warehouse_traceability',
        ],
    ],
];

// 2. Pemetaan Nama Ramah (Human-Friendly) & Deskripsi Fitur
$featureMeta = [
    'dashboard' => ['name' => 'Dashboard Utama Operasional', 'desc' => 'Ringkasan KPI, grafik indikator jaringan, dan status billing pelanggan secara umum.'],
    'users' => ['name' => 'Manajemen Pengguna & Staf', 'desc' => 'Kelola akun staf, tambah user baru, atur role jabatan, dan alokasi wilayah kerja (POP Scope).'],
    'roles' => ['name' => 'Peran Jabatan & Hak Akses', 'desc' => 'Kelola grup jabatan (Role) dan matriks izin hak akses fitur untuk tiap peran.'],
    'master_wilayah' => ['name' => 'Master Area & Wilayah', 'desc' => 'Mengatur hirarki wilayah operasional (Provinsi, Kabupaten/Kota, Kecamatan, Desa/Kelurahan).'],
    'pops' => ['name' => 'Point of Presence (POP) / Cabang', 'desc' => 'Mengatur kantor cabang / POP utama dan sub-POP tempat bertugas staf dan cakupan jaringan.'],
    'master_distribusi' => ['name' => 'Master Distribusi Jaringan', 'desc' => 'Kelola infrastruktur jaringan distribusi kabel fiber optic, ODP, ODC, dan tiang.'],
    'packages' => ['name' => 'Paket Internet & Tarif', 'desc' => 'Mengatur produk layanan internet, kecepatan (bandwidth), dan harga berlangganan bulanan.'],
    'master_status_pelanggan' => ['name' => 'Master Status Pelanggan', 'desc' => 'Kelola tahapan status siklus pelanggan (Draft, Perlu Dilengkapi, Lengkap, Siap Billing).'],
    'sla_timeline' => ['name' => 'Aturan Batas Waktu (SLA)', 'desc' => 'Mengatur batas waktu wajib mulai penanganan (SLA) per jenis tugas dan paket internet.'],
    'item_categories' => ['name' => 'Kategori Barang & Perangkat', 'desc' => 'Mengatur pengelompokan jenis barang stok (Modem, Kabel, Aksesoris, dll).'],
    'items' => ['name' => 'Master Stok Barang & Material', 'desc' => 'Kelola katalog barang, unit stok, dan material untuk pemasangan atau perbaikan.'],
    'work_tools' => ['name' => 'Master Alat Kerja Teknisi', 'desc' => 'Kelola inventaris peralatan teknis (Splicer, OPM, Tang, Tangga, dll) yang dipinjam staf.'],
    'ticket_issue_categories' => ['name' => 'Kategori Kendala Tiket', 'desc' => 'Mengatur klasifikasi jenis gangguan jaringan atau keluhan pelanggan.'],
    'customers' => ['name' => 'Master Data Pelanggan', 'desc' => 'Pusat data pelanggan, penambahan manual, pencarian, dan pemantauan status berlangganan.'],
    'customers.import' => ['name' => 'Impor Data Pelanggan Massal', 'desc' => 'Fasilitas mengunggah dan mengimpor file Excel / CSV data pelanggan lama ke sistem.'],
    'customers.detail' => ['name' => 'Akses Detail Pelanggan', 'desc' => 'Melihat profil lengkap pelanggan (identitas, alamat, billing, dan riwayat teknis).'],
    'customers.detail.identity' => ['name' => 'Identitas & Profil Pelanggan', 'desc' => 'Mengatur nama, NIK, nomor HP, email, dan data diri pelanggan.'],
    'customers.detail.address' => ['name' => 'Alamat & Lokasi GPS Pelanggan', 'desc' => 'Mengatur koordinat peta GPS, alamat rumah, RT/RW, dan kelurahan pelanggan.'],
    'customers.detail.packages' => ['name' => 'Paket & Tarif Khusus Pelanggan', 'desc' => 'Mengatur penetapan paket internet berlangganan dan diskon custom pelanggan.'],
    'customers.detail.devices' => ['name' => 'Konfigurasi Modem & Perangkat', 'desc' => 'Melihat dan mengatur data teknis ONT/Modem, MAC Address, IP, Port OLT, dan akun PPPoE.'],
    'customers.detail.documents' => ['name' => 'Berkas & Dokumen Pelanggan', 'desc' => 'Mengunggah dan mengelola foto rumah dan formulir kontrak registrasi.'],
    'customers.terminated' => ['name' => 'Daftar Pelanggan Putus/Berhenti', 'desc' => 'Melihat riwayat pelanggan yang sudah tidak berlangganan (churn/terminasi).'],
    'customers.failed' => ['name' => 'Daftar Pelanggan Batal/Gagal', 'desc' => 'Melihat riwayat calon pelanggan yang batal dipasang atau gagal survei.'],
    'customers.detail.survey' => ['name' => 'Survei Kelayakan Lokasi', 'desc' => 'Menginput dan mengecek hasil survei jaringan, redaman kabel, dan foto lokasi calon pelanggan.'],
    'customers.detail.installation' => ['name' => 'Instalasi & Pemasangan Baru', 'desc' => 'Pelaporan pengerjaan instalasi perangkat di lokasi pelanggan dan klaim aktivasi.'],
    'fop_tasks' => ['name' => 'Perencanaan Tiket Lapangan (FOP)', 'desc' => 'Membuat tiket awal pengerjaan teknisi (SURVEY/PASANG/PERBAIKAN) dan penugasan tim.'],
    'tasks' => ['name' => 'Pelaksanaan & Eksekusi Tugas Teknisi', 'desc' => 'Mengisi checklist pengerjaan tugas di lapangan dan pengunggahan bukti pekerjaan.'],
    'tickets' => ['name' => 'Pusat Tiket Gangguan (NOC)', 'desc' => 'Mengelola tiket penanganan gangguan jaringan dan koordinasi tim helpdesk/NOC.'],
    'noc_dashboard' => ['name' => 'Dashboard Monitoring NOC', 'desc' => 'Monitoring status jaringan realtime, agregasi tiket gangguan, dan grafik insiden.'],
    'noc_worksheet' => ['name' => 'Lembar Kerja NOC', 'desc' => 'Pengawasan teknis harian oleh staf NOC terhadap tiket kendala aktif.'],
    'invoices' => ['name' => 'Tagihan & Billing Bulanan', 'desc' => 'Penerbitan tagihan rutin bulanan, rincian biaya, penyesuaian nominal, dan pencetakan invoice.'],
    'payments' => ['name' => 'Pencatatan Pembayaran & Kasir', 'desc' => 'Input pembayaran tagihan, penerimaan uang kasir, upload bukti bayar, dan verifikasi.'],
    'kolektor' => ['name' => 'Worklist Penagihan Kolektor', 'desc' => 'Tampilan khusus penagih lapangan (Kolektor) untuk mencatat uang tagihan yang diterima di tempat.'],
    'collector_worksheet' => ['name' => 'Pengawasan Admin Kolektor', 'desc' => 'Dashboard monitor admin untuk memantau setoran kas penagihan dari para kolektor.'],
    'cash_deposit' => ['name' => 'Setoran Kas Admin ke Owner / Bank', 'desc' => 'Uang yang sudah diterima admin (dari setoran kolektor + pembayaran tunai di kantor) diteruskan ke Owner atau rekening bank, lalu diperiksa.'],
    'reports' => ['name' => 'Laporan Operasional & Keuangan', 'desc' => 'Laporan rekapitulasi pembayaran, piutang pelanggan, statistik aktivasi, dan cetak laporan.'],
    'audit_logs' => ['name' => 'Catatan Aktivitas Sistem (Audit Log)', 'desc' => 'Jejak audit keamanan yang mencatat seluruh aksi penting pengguna dalam sistem.'],
    'warehouse' => ['name' => 'Dashboard & Ledger Gudang', 'desc' => 'Ringkasan stok tiap gudang, barang hampir habis, dan riwayat seluruh transaksi keluar-masuk barang.'],
    'warehouse_transfer' => ['name' => 'Transfer Antar Gudang', 'desc' => 'Pengiriman barang dari Gudang Pusat ke Gudang Cabang, dan konfirmasi penerimaan fisik di cabang.'],
    'warehouse_issue' => ['name' => 'Issue Barang ke Teknisi', 'desc' => 'Mengeluarkan barang dari gudang cabang untuk dibawa teknisi ke lapangan.'],
    'warehouse_custody' => ['name' => 'Custody Barang di Tangan Teknisi', 'desc' => 'Melihat barang yang sedang dipegang tiap teknisi (belum dipasang/dikembalikan).'],
    'warehouse_traceability' => ['name' => 'Pelacakan Aset (Asset Traceability)', 'desc' => 'Menelusuri riwayat lengkap satu barang bernomor seri, dari gudang sampai ke pelanggan.'],
];

// 3. Pemetaan Aksi Hak Akses (Human-Friendly Action Labels, Badges & Deskripsi Fungsi)
$actionMetaMap = [
    'view' => ['label' => 'Lihat Data', 'desc' => 'Dapat mengakses & melihat daftar data.', 'type' => 'read', 'badge' => 'bg-sky-50 text-sky-700 dark:bg-sky-950/60 dark:text-sky-400 border-sky-200 dark:border-sky-800'],
    'detail' => ['label' => 'Lihat Detail', 'desc' => 'Dapat melihat rincian informasi lengkap.', 'type' => 'read', 'badge' => 'bg-sky-50 text-sky-700 dark:bg-sky-950/60 dark:text-sky-400 border-sky-200 dark:border-sky-800'],
    'create' => ['label' => 'Tambah / Buat', 'desc' => 'Dapat membuat atau menambahkan data baru.', 'type' => 'write', 'badge' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800'],
    'update' => ['label' => 'Ubah / Edit', 'desc' => 'Dapat mengubah atau memperbarui informasi data.', 'type' => 'write', 'badge' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400 border-amber-200 dark:border-amber-800'],
    'delete' => ['label' => 'Hapus Data', 'desc' => 'Dapat menghapus data dari sistem.', 'type' => 'danger', 'badge' => 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400 border-rose-200 dark:border-rose-800'],
    'import' => ['label' => 'Impor File', 'desc' => 'Dapat mengimpor data massal dari Excel/CSV.', 'type' => 'write', 'badge' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-400 border-indigo-200 dark:border-indigo-800'],
    'export' => ['label' => 'Ekspor / Unduh', 'desc' => 'Dapat mengekspor data ke file Excel/PDF.', 'type' => 'read', 'badge' => 'bg-teal-50 text-teal-700 dark:bg-teal-950/60 dark:text-teal-400 border-teal-200 dark:border-teal-800'],
    'print' => ['label' => 'Cetak Dokumen', 'desc' => 'Dapat mencetak berkas / cetak faktur.', 'type' => 'read', 'badge' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700'],
    'approve' => ['label' => 'Setujui / Verifikasi', 'desc' => 'Dapat menyetujui pengajuan / pembayaran.', 'type' => 'write', 'badge' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800'],
    'reject' => ['label' => 'Tolak Pengajuan', 'desc' => 'Dapat menolak pengajuan / transaksi.', 'type' => 'danger', 'badge' => 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400 border-rose-200 dark:border-rose-800'],
    'activate' => ['label' => 'Aktivasi Layanan', 'desc' => 'Dapat mengaktifkan kembali layanan.', 'type' => 'write', 'badge' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800'],
    'deactivate' => ['label' => 'Non-Aktifkan', 'desc' => 'Dapat menonaktifkan / mengisolir layanan.', 'type' => 'warning', 'badge' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400 border-amber-200 dark:border-amber-800'],
    'assign' => ['label' => 'Tugaskan Teknisi', 'desc' => 'Dapat menugaskan staf / teknisi pelaksana.', 'type' => 'write', 'badge' => 'bg-purple-50 text-purple-700 dark:bg-purple-950/60 dark:text-purple-400 border-purple-200 dark:border-purple-800'],
    'validate' => ['label' => 'Validasi Data', 'desc' => 'Dapat memverifikasi / memvalidasi data.', 'type' => 'write', 'badge' => 'bg-cyan-50 text-cyan-700 dark:bg-cyan-950/60 dark:text-cyan-400 border-cyan-200 dark:border-cyan-800'],
    'cancel' => ['label' => 'Batalkan Tagihan', 'desc' => 'Dapat membatalkan tagihan / proses.', 'type' => 'danger', 'badge' => 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400 border-rose-200 dark:border-rose-800'],
    'upload' => ['label' => 'Unggah Berkas', 'desc' => 'Dapat mengunggah foto / dokumen pendukung.', 'type' => 'write', 'badge' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-400 border-indigo-200 dark:border-indigo-800'],
    'download' => ['label' => 'Unduh Berkas', 'desc' => 'Dapat mengunduh berkas / dokumen fisik.', 'type' => 'read', 'badge' => 'bg-teal-50 text-teal-700 dark:bg-teal-950/60 dark:text-teal-400 border-teal-200 dark:border-teal-800'],
    'view_sensitive' => ['label' => 'Password & Port (Sensitif)', 'desc' => 'Melihat password PPPoE, WiFi, & port OLT.', 'type' => 'sensitive', 'badge' => 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 border-rose-300 dark:border-rose-700 font-semibold'],
    'update_sensitive' => ['label' => 'Ubah Konfig Sensitif', 'desc' => 'Mengubah password & parameter jaringan sensitif.', 'type' => 'sensitive', 'badge' => 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 border-rose-300 dark:border-rose-700 font-semibold'],
    'pay' => ['label' => 'Catat Pembayaran', 'desc' => 'Mencatat uang bayar yang diterima dari pelanggan.', 'type' => 'write', 'badge' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800'],
    'deposit' => ['label' => 'Setor Penagihan', 'desc' => 'Menyetorkan uang hasil penagihan ke kasir.', 'type' => 'write', 'badge' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-400 border-indigo-200 dark:border-indigo-800'],
    'visit' => ['label' => 'Catat Kunjungan', 'desc' => 'Mencatat laporan hasil kunjungan lapangan.', 'type' => 'write', 'badge' => 'bg-sky-50 text-sky-700 dark:bg-sky-950/60 dark:text-sky-400 border-sky-200 dark:border-sky-800'],
    'receive' => ['label' => 'Konfirmasi Terima', 'desc' => 'Dapat mengonfirmasi penerimaan fisik barang kiriman.', 'type' => 'write', 'badge' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800'],
];

// 3b. Deskripsi PER KODE PERMISSION — sumbernya fungsi NYATA di modul
//     (route yang digerbangi + guard di controller/policy), bukan arti
//     generik kata kerjanya. Ditaruh di sini karena permission digenerate
//     PermissionGeneratorService tanpa kolom `description`, jadi tanpa map
//     ini semua checkbox cuma nampilin teks generik dari $actionMetaMap
//     ("Dapat menghapus data dari sistem") yang sering TIDAK sesuai
//     kenyataan — beberapa permission malah tidak menggerbangi route apa pun.
//
//     Prioritas: map ini > $perm->description (DB) > $actionMetaMap generik.
//     Permission yang lahir dari config/rbac.php tapi belum dipasang ke route
//     mana pun DITANDAI eksplisit "[Belum aktif]" — jangan diam-diam
//     dideskripsikan seolah berfungsi, itu bikin admin salah kira sudah
//     membatasi sesuatu.
$permissionDescMap = [
    // ── Dashboard ────────────────────────────────────────────────
    'dashboard.view' => 'Buka halaman Dashboard: ringkasan pelanggan, tagihan, dan aktivitas terbaru dalam POP scope-nya.',

    // ── Pengguna & Hak Akses ─────────────────────────────────────
    'users.view' => 'Buka daftar user staf beserta role, status akun, dan preview hak akses efektifnya.',
    'users.create' => 'Tambah akun staf baru berikut role jabatan dan cakupan POP-nya.',
    'users.update' => 'Ubah data user: role, status akun, dan cakupan POP (POP scope).',
    'users.delete' => '[Belum aktif] Tidak menggerbangi route apa pun — user dinonaktifkan lewat status, bukan dihapus.',
    'roles.view' => 'Buka daftar Role dan halaman Permission Matrix ini.',
    'roles.create' => 'Buat role jabatan baru (role kustom).',
    'roles.update' => 'Simpan perubahan centang permission di matrix ini dan ubah nama role.',
    'roles.delete' => 'Hapus role. Role sistem & role `owner` tetap terlindungi walau permission ini diberikan.',
    'audit_logs.view' => 'Buka halaman Audit Log dan blok riwayat perubahan di detail pembayaran.',
    'audit_logs.export' => '[Belum aktif] Belum ada route ekspor audit log — halaman audit hanya bisa dibaca.',

    // ── Master Data & Infrastruktur ──────────────────────────────
    'master_wilayah.view' => 'Buka Master Wilayah: hirarki provinsi → kota/kabupaten → kecamatan → desa.',
    'master_wilayah.create' => '[Belum aktif] Data wilayah diisi lewat seeder, belum ada form tambah di UI.',
    'master_wilayah.update' => '[Belum aktif] Data wilayah diisi lewat seeder, belum ada form ubah di UI.',
    'master_wilayah.delete' => '[Belum aktif] Tidak menggerbangi route apa pun — wilayah tidak dihapus dari UI.',
    'pops.view' => 'Buka daftar POP/Cabang & Mini POP beserta prefix penomoran CID pelanggan.',
    'pops.create' => 'Tambah POP atau Mini POP baru, termasuk prefix CID-nya.',
    'pops.update' => 'Ubah data POP: nama, induk, wilayah, prefix CID, dan status aktif.',
    'pops.delete' => '[Belum aktif] POP dinonaktifkan lewat status, tidak pernah dihapus (CID lama harus tetap terlacak).',
    'master_distribusi.view' => 'Lihat daftar titik distribusi jaringan (ODC/ODP/tiang) beserta kapasitas port.',
    'master_distribusi.create' => 'Tambah titik distribusi baru ke master jaringan.',
    'master_distribusi.update' => 'Ubah data titik distribusi: nama, POP, kapasitas port, koordinat.',
    'master_distribusi.delete' => 'Hapus titik distribusi dari master jaringan.',
    'packages.view' => 'Lihat daftar paket internet: kecepatan, harga bulanan, dan status aktifnya.',
    'packages.create' => 'Tambah paket internet baru beserta tarif bulanannya.',
    'packages.update' => 'Ubah paket internet dan aktif/nonaktifkan paket lewat tombol Toggle Status.',
    'packages.delete' => '[Belum aktif] Paket dinonaktifkan lewat Toggle Status, bukan dihapus — tagihan lama harus tetap merujuk paketnya.',
    'master_status_pelanggan.view' => 'Lihat daftar master status pelanggan (Draft, Perlu Dilengkapi, Lengkap, Siap Billing).',
    'master_status_pelanggan.create' => '[Belum aktif] Daftar status di-seed sistem, belum ada form tambah di UI.',
    'master_status_pelanggan.update' => '[Belum aktif] Daftar status di-seed sistem, belum ada form ubah di UI.',
    'master_status_pelanggan.delete' => '[Belum aktif] Status pelanggan tidak dihapus dari UI.',
    'sla_timeline.view' => 'Buka halaman Timeline SLA: batas waktu penanganan per paket internet.',
    'sla_timeline.update' => 'Ubah batas jam SLA per paket. Ini SLA paket, bukan SLA pengerjaan teknisi.',
    'item_categories.view' => 'Lihat daftar kategori barang (Modem, Kabel, Aksesoris, dll).',
    'item_categories.create' => 'Tambah kategori barang baru.',
    'item_categories.update' => 'Ubah nama kategori barang dan status aktifnya.',
    'item_categories.delete' => '[Belum aktif] Kategori dinonaktifkan lewat status aktif, bukan dihapus.',
    'items.view' => 'Lihat katalog barang & material (modem, kabel, konektor) beserta satuannya.',
    'items.create' => 'Tambah barang/material baru ke katalog.',
    'items.update' => 'Ubah data barang dan status aktifnya.',
    'items.delete' => '[Belum aktif] Barang dinonaktifkan lewat status aktif — laporan lama harus tetap punya rujukan.',
    'work_tools.view' => 'Lihat daftar alat kerja teknisi (splicer, OPM, tangga, dll).',
    'work_tools.create' => 'Tambah alat kerja baru ke inventaris.',
    'work_tools.update' => 'Ubah data alat kerja dan status aktifnya.',
    'work_tools.delete' => '[Belum aktif] Alat kerja dinonaktifkan lewat status aktif, bukan dihapus.',
    'ticket_issue_categories.view' => 'Lihat daftar kategori kendala tiket (jenis gangguan/keluhan).',
    'ticket_issue_categories.create' => 'Tambah kategori kendala tiket baru.',
    'ticket_issue_categories.update' => 'Ubah nama kategori kendala dan status aktifnya.',
    'ticket_issue_categories.delete' => '[Belum aktif] Kategori dinonaktifkan lewat status aktif biar tiket lama tidak kehilangan jejak.',

    // ── Data Pelanggan ───────────────────────────────────────────
    'customers.view' => 'Buka halaman List Data Pelanggan. Isinya otomatis dibatasi POP scope user.',
    'customers.create' => 'Buka form Pendaftaran Pelanggan dan menyimpan pelanggan baru.',
    'customers.update' => 'Edit data pelanggan & assign survey. Menggerbangi SEMUA tab detail sekaligus, bukan per-tab.',
    'customers.delete' => 'Hapus pelanggan dari daftar (soft delete).',
    'customers.deactivate' => 'Putus langganan pelanggan aktif (terminasi). Terpisah dari Edit karena mematikan layanan.',
    'customers.import.view' => 'Buka halaman Import Pelanggan, riwayat batch import, dan unduh template.',
    'customers.import.import' => 'Unggah, validasi, dan eksekusi import massal pelanggan dari Excel/CSV.',
    'customers.detail.view' => 'Buka halaman Detail Pelanggan. Terpisah dari akses List — bisa diberikan sendiri-sendiri.',
    'customers.terminated.view' => 'Buka halaman List Pelanggan Putus Langganan (arsip terminasi).',
    'customers.failed.view' => 'Buka halaman List Pelanggan Gagal (batal pasang / gagal survei).',
    'customers.detail.identity.view' => 'Tampilkan blok Identitas di Detail Pelanggan (nama, NIK, kontak, data diri).',
    'customers.detail.identity.update' => '[Belum aktif] Pengeditan identitas masih ikut `customers.update`, belum dipisah per tab.',
    'customers.detail.address.view' => 'Tampilkan blok Alamat & titik koordinat pelanggan di halaman Detail.',
    'customers.detail.address.update' => '[Belum aktif] Pengeditan alamat masih ikut `customers.update`, belum dipisah per tab.',
    'customers.detail.packages.view' => 'Tampilkan blok Paket & Layanan pelanggan: paket aktif, harga, jatuh tempo.',
    'customers.detail.packages.update' => '[Belum aktif] Perubahan paket & tarif masih ikut `customers.update`.',
    'customers.detail.devices.view' => 'Buka halaman Perangkat & Pemasangan pelanggan (ONT, ODP, redaman) tanpa perlu akses Detail Pelanggan penuh.',
    'customers.detail.devices.update' => 'Simpan data perangkat pelanggan: tipe ONT, serial number, penempatan ODP.',
    'customers.detail.devices.view_sensitive' => 'Tampilkan kredensial PPPoE/WiFi dan port OLT pelanggan (tersembunyi tanpa izin ini).',
    'customers.detail.devices.update_sensitive' => 'Ubah kredensial PPPoE/WiFi & parameter jaringan sensitif pelanggan.',
    'customers.detail.devices.retrieve' => 'Catat pengambilan alat dari pelanggan putus langganan (memunculkan task DEAC).',
    'customers.detail.documents.view' => 'Buka & unduh berkas dokumen pelanggan. Berkas dilayani lewat controller, bukan URL publik.',
    'customers.detail.documents.upload' => 'Unggah dokumen pelanggan (foto rumah, formulir registrasi).',
    'customers.detail.documents.download' => '[Belum aktif] Unduhan berkas digerbangi `customers.detail.documents.view`, bukan permission ini.',
    'customers.detail.documents.delete' => '[Belum aktif] Dokumen pelanggan belum bisa dihapus dari UI.',

    // ── Layanan Lapangan ─────────────────────────────────────────
    'customers.detail.survey.view' => 'Buka Antrean Survey dan halaman laporan hasil survey pelanggan.',
    'customers.detail.survey.update' => 'Mulai survey dan simpan hasilnya (kelayakan, redaman, foto lokasi). Wajib anggota tim task survey berjalan.',
    'customers.detail.survey.validate' => 'Ubah data survey walau tahap survey sudah lewat — hak koreksi Admin/Verifikator.',
    'customers.detail.survey.reject' => 'Batalkan survey pelanggan (calon pelanggan masuk daftar gagal).',
    'customers.detail.installation.view' => 'Buka Antrean Verifikasi dan halaman laporan pemasangan pelanggan.',
    'customers.detail.installation.update' => 'Mulai pemasangan, isi laporan instalasi & test report. Wajib anggota tim task PSB berjalan.',
    'customers.detail.installation.validate' => 'Verifikasi Admin: proses ke tim, verifikasi final, revisi/tolak hasil pasang, dan atur network assignment.',
    'customers.detail.installation.activate' => 'Aktifkan layanan pelanggan setelah verifikasi lolos (pelanggan mulai ditagih).',
    'customers.detail.installation.reject' => 'Batalkan pemasangan yang sedang berjalan.',
    'fop_tasks.view' => 'Buka papan Task FOP, riwayat, dan detail tiket FOP.',
    'fop_tasks.create' => 'Buat tiket FOP baru langsung dari papan /fop-tasks (TFOP-…).',
    'fop_tasks.update' => 'Ubah tiket FOP, assign ke tim, tukar teknisi, dan pindah tim.',
    'fop_tasks.update_sensitive' => 'Ubah Tipe Task & Prioritas tiket FOP — dicek berbasis diff, bukan sekadar tombol.',
    'fop_tasks.cancel' => 'Batalkan tiket FOP (termasuk pembatalan pasca-FOP dari halaman Task). Ini satu-satunya jalur batal setelah tiket masuk FOP.',
    'fop_tasks.delete' => 'Hapus tiket FOP dari papan.',
    'task.view.all' => 'Lihat SEMUA task teknisi + Dashboard FOP dan pipeline-nya (lintas teknisi, dalam POP scope).',
    'task.view.own' => 'Lihat halaman Task Saya & riwayat task milik teknisi yang login saja.',
    'task.lookup' => 'Pakai pencarian pelanggan & cek konflik jadwal di form task/modal FOP.',
    'task.manage' => 'Edit detail & jadwal task teknisi (tanggal, deskripsi, penugasan).',
    'task.assign.team' => 'Ubah susunan tim pelaksana pada sebuah task.',
    'task.edit.type' => 'Ubah kategori/tipe task yang sudah dibuat. Sengaja tidak diberikan default ke role mana pun.',
    'task.cancel' => 'Batalkan task teknisi lewat halaman /tasks.',
    'task.conflict.override' => 'Tetap simpan jadwal walau sistem mendeteksi bentrok jadwal teknisi.',
    'task.reject' => 'Tolak task yang berstatus pending (sisi FOP).',
    'task.approve' => 'Setujui hasil task teknisi pada langkah review FOP.',
    'task.execute' => 'Kerjakan task di lapangan: mulai, lapor selesai, lapor nanti (pending), dan reschedule.',
    'tickets.view' => 'Buka detail tiket, drawer detail di worksheet, dan unduh lampiran tiket.',
    'tickets.create' => 'Buka halaman New Ticket dan simpan tiket baru (TKT-…), termasuk cek duplikat.',
    'tickets.update' => 'Aksi tiket: Selesai, Assign NOC, Assign FOP, dan Kembalikan ke Helpdesk. Siapa yang sedang pegang tiket tetap dicek terpisah.',
    'tickets.cancel' => 'Batalkan tiket SEBELUM masuk FOP. Setelah masuk FOP, pembatalan hanya lewat `fop_tasks.cancel`.',
    'tickets.selesai.view' => 'Buka halaman arsip Ticket Selesai.',
    'tickets.dibatalkan.view' => 'Buka halaman arsip Ticket Dibatalkan.',
    'tickets.history.view' => 'Buka History Ticketing: semua tiket lintas handler & status, termasuk yang masih berjalan.',
    'tickets.history.export' => 'Unduh isi History Ticketing ke Excel.',
    'noc_worksheet.view' => 'Buka halaman kerja Worksheet NOC (tiket yang dipegang NOC + jejak eskalasi ke FOP).',
    'noc_worksheet.masuk.view' => '[Nonaktif] Tab lama Ticket Masuk, sudah dilebur ke Worksheet NOC. Tidak menggerbangi apa pun.',
    'noc_worksheet.diproses.view' => '[Nonaktif] Tab lama Ticket Diproses, sudah dilebur ke Worksheet NOC. Tidak menggerbangi apa pun.',
    'noc_dashboard.view' => 'Buka Dashboard NOC: monitoring tiket gangguan dan agregat insiden.',

    // ── Tagihan & Keuangan ───────────────────────────────────────
    'invoices.view' => 'Buka daftar Tagihan (semua/lunas/belum lunas) dan detail per invoice.',
    'invoices.create' => 'Terbitkan tagihan manual untuk seorang pelanggan di luar siklus bulanan.',
    'invoices.update' => '[Belum aktif] Belum ada route ubah invoice — nominal tagihan terbit sengaja tidak diedit dari UI.',
    'invoices.delete' => '[Belum aktif] Belum ada route hapus invoice — tagihan lunas tidak boleh hilang dari jejak.',
    'invoices.print' => '[Belum aktif] Cetak invoice belum punya route sendiri; struk pembayaran ikut `payments.view`.',
    'payments.view' => 'Buka daftar Pembayaran, detail pembayaran, halaman lebih-bayar, dan cetak kwitansi.',
    'payments.create' => 'Catat pembayaran atas invoice mana pun, termasuk mencatat setoran mewakili kolektor.',
    'payments.reject' => 'Tolak pembayaran yang sudah tercatat (pembayaran ditolak tidak boleh jadi lunas).',
    'payments.update' => '[Belum aktif] Belum ada route ubah pembayaran — koreksi dilakukan lewat tolak + catat ulang.',
    'payments.delete' => '[Belum aktif] Belum ada route hapus pembayaran — jejak kas tidak dihapus.',
    'payments.validate' => '[Belum aktif] Verifikasi kas kolektor memakai `collector_worksheet.validate`, bukan permission ini.',
    'payments.approve' => '[Belum aktif] Belum dipakai route mana pun.',
    'kolektor.view' => 'Buka Worklist Kolektor: hanya pelanggan yang di-assign ke kolektor yang login.',
    'kolektor.pay' => 'Catat pembayaran dari worklist SENDIRI. Bukan hak bayar invoice umum — kolektornya diambil dari user login.',
    'kolektor.deposit' => 'Setor seluruh saldo hasil tagihan ke admin. Dipisah dari mencatat bayar agar hak pegang kas bisa dicabut sendiri.',
    'kolektor.visit' => 'Catat kunjungan tanpa uang (tidak ada orang / menolak / janji bayar). Kontrol anti-fraud modul kolektor.',
    'collector_worksheet.view' => 'Buka Worksheet Admin Kolektor: daftar kolektor, progres, dan unduh berkas kwitansi.',
    'collector_worksheet.assign' => 'Assign pelanggan ke kolektor dan melepas assignment-nya.',
    'collector_worksheet.validate' => 'Cross check & verifikasi setoran kolektor. Verifikator tidak boleh sama dengan penyetor.',
    'collector_worksheet.approve' => 'Hapus buku selisih setoran — titik kerugian diakui. Sengaja tidak diberikan ke admin yang memverifikasi.',
    'collector_worksheet.print' => 'Cetak kwitansi pembayaran ber-QR untuk seorang kolektor.',
    'collector_worksheet.upload' => 'Unggah kwitansi terpindai, cocokkan manual, dan lepas kecocokannya.',
    'cash_deposit.create' => 'Memegang kas & menyetorkannya ke Owner/bank dari Worksheet Admin, plus melihat riwayat setorannya SENDIRI. Ini yang dibutuhkan admin.',
    'cash_deposit.view' => 'Pandangan PEMERIKSA: posisi kas admin mana pun dalam scope, antrean pemeriksaan, dan rincian sumber sampai nama pelanggan. Bukan untuk admin penyetor.',
    'cash_deposit.validate' => 'Periksa uang yang diserahkan lalu tutup setoran kas. Pemeriksa tidak boleh sama dengan penyetor.',
    'cash_deposit.approve' => 'Tutup selisih kas — titik kerugian (atau kelebihan) diakui. Sengaja terpisah dari memeriksa.',

    // ── Laporan ──────────────────────────────────────────────────
    'reports.view' => 'Buka SEMUA halaman laporan (pelanggan, tagihan, pembayaran, import) sekaligus tombol ekspornya.',
    'reports.export' => '[Belum aktif] Tombol ekspor laporan sudah digerbangi `reports.view`; permission ini belum dipasang ke route.',
    'reports.print' => '[Belum aktif] Belum ada route cetak laporan terpisah.',
];

// Helper Function: Ambil Info Aksi
$getPermissionInfo = function($perm) use ($actionMetaMap, $permissionDescMap) {
    $actionCode = $perm->action->code->value ?? $perm->action->code ?? '';
    if (!$actionCode && str_contains($perm->code, '.')) {
        $parts = explode('.', $perm->code);
        $actionCode = end($parts);
    }
    
    // Map per-kode menang atas description DB & teks generik per-action:
    // dia satu-satunya yang tahu route/guard NYATA di balik checkbox ini.
    $desc = $permissionDescMap[$perm->code] ?? $perm->description ?? '';

    if (isset($actionMetaMap[$actionCode])) {
        $meta = $actionMetaMap[$actionCode];
        $label = $meta['label'];
        if ($perm->name && $perm->name !== $actionCode && !str_contains(strtolower($perm->name), 'view')) {
            $label = $perm->name;
        }
        if (!$desc) {
            $desc = $meta['desc'];
        }
        return [
            'label' => $label,
            'desc' => $desc,
            'badge' => $meta['badge'],
            'type' => $meta['type'],
        ];
    }
    
    return [
        'label' => $perm->name ?? $perm->code,
        'desc' => $desc ?: 'Hak akses operasional modul.',
        'badge' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700',
        'type' => 'other',
    ];
};

// 4. Kelompokkan Root Feature ke dalam Kategori Fungsional
$groupedFeatures = [];
foreach ($functionalCategories as $catKey => $catMeta) {
    $groupedFeatures[$catKey] = [
        'meta' => $catMeta,
        'items' => [],
    ];
}
$groupedFeatures['group_other'] = [
    'meta' => [
        'title' => 'Modul Tambahan Lainnya',
        'subtitle' => 'Fitur dan modul pendukung operasional tambahan.',
        'icon' => 'folder-open',
        'badge' => 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700',
        'features' => [],
    ],
    'items' => [],
];

foreach ($features as $f) {
    $assignedCategory = 'group_other';
    foreach ($functionalCategories as $catKey => $catMeta) {
        if (in_array($f->code, $catMeta['features'])) {
            $assignedCategory = $catKey;
            break;
        }
    }
    $groupedFeatures[$assignedCategory]['items'][] = $f;
}
@endphp

<div class="space-y-6" x-data="{ activeCategory: 'all', permSearch: '', filterMatchCount: 0 }">

    {{-- ================================================ --}}
    {{-- 1. PAGE HEADER & BREADCRUMB (NAKED)             --}}
    {{-- ================================================ --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">
                <a href="{{ route('roles.index') }}"
                   class="text-sky-600 dark:text-sky-400 hover:underline flex items-center gap-1 transition-colors">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Daftar Peran Jabatan (Role)
                </a>
                <span class="text-slate-400 dark:text-slate-400">/</span>
                <span class="text-slate-700 dark:text-slate-200 font-semibold">Permission Matrix</span>
            </div>

            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    Matrix Hak Akses: {{ $role->name }}
                </h1>
                @if($role->isProtected())
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 border border-rose-200/60 dark:border-rose-800/60">
                        <svg class="h-3 w-3 stroke-[2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                        </svg>
                        Dilindungi Sistem
                    </span>
                @elseif($role->is_system)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-400 border border-sky-200/60 dark:border-sky-800/60">
                        Role Standar
                    </span>
                @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                        Role Kustom
                    </span>
                @endif
            </div>
            <p class="mt-1 text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                Atur kewenangan aksi operasional untuk jabatan <strong class="text-slate-700 dark:text-slate-200">{{ $role->name }}</strong> berdasarkan urutan fungsi kerja.
            </p>
        </div>

        {{-- Top Right Back Action --}}
        <div class="flex-shrink-0">
            <a href="{{ route('roles.index') }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs sm:text-sm font-semibold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-all shadow-2xs">
                <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Kembali ke Daftar
            </a>
        </div>
    </div>

    {{-- ================================================ --}}
    {{-- 2. CONTROL BAR & CATEGORY TABS (NAKED)           --}}
    {{-- ================================================ --}}
    <div class="space-y-3">
        {{-- Search & Counter Strip --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 bg-white dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700/80 rounded-lg p-3 sm:p-4 shadow-xs">
            
            {{-- Counter display --}}
            <div class="flex items-center gap-3">
                <div class="flex items-baseline gap-2 px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                    <span class="font-mono text-lg font-bold text-sky-600 dark:text-sky-400 tabular-nums" id="permCount">
                        {{ count($rolePermissions) }}
                    </span>
                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-400">permission aktif</span>
                </div>

                {{-- Live Search Permission --}}
                <div class="relative flex-1 min-w-[240px] max-w-md">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <input
                        type="text"
                        x-model="permSearch"
                        @input="filterPermissions($event.target.value)"
                        placeholder="Cari fitur, hak akses, atau kode..."
                        class="w-full pl-9 pr-8 py-1.5 text-xs sm:text-sm bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700/80 rounded-full text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-all"
                    >
                    <button
                        x-show="permSearch.length > 0"
                        @click="permSearch = ''; filterPermissions('')"
                        type="button"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-300"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Bulk Actions --}}
            <div class="flex items-center gap-2 flex-wrap text-xs">
                <button type="button" onclick="selectAll()"
                        class="px-2.5 py-1.5 font-semibold text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-950/50 rounded-lg border border-sky-100 dark:border-sky-900/50 transition-colors">
                    ✓ Pilih Semua Hak Akses
                </button>
                <span class="text-slate-300 dark:text-slate-700">|</span>
                <button type="button" onclick="deselectAll()"
                        class="px-2.5 py-1.5 font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 transition-colors">
                    ✕ Hapus Semua
                </button>
                <span class="text-slate-300 dark:text-slate-700">|</span>
                <button type="button" onclick="expandAllGroups()"
                        class="px-2.5 py-1.5 font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-colors">
                    Buka Semua Group
                </button>
                <span class="text-slate-300 dark:text-slate-700">|</span>
                <button type="button" onclick="collapseAllGroups()"
                        class="px-2.5 py-1.5 font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-colors">
                    Tutup Semua Group
                </button>
            </div>

        </div>

        {{-- Category Filter Navigation Tabs --}}
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-thin">
            <button type="button"
                    @click="activeCategory = 'all'"
                    :class="activeCategory === 'all' ? 'bg-sky-600 text-white font-semibold shadow-xs' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60 border border-slate-200 dark:border-slate-700/80'"
                    class="px-3 py-1.5 rounded-full text-xs whitespace-nowrap transition-all">
                🌐 Semua Fitur
            </button>
            @foreach($functionalCategories as $catKey => $catMeta)
            <button type="button"
                    @click="activeCategory = '{{ $catKey }}'"
                    :class="activeCategory === '{{ $catKey }}' ? 'bg-sky-600 text-white font-semibold shadow-xs' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60 border border-slate-200 dark:border-slate-700/80'"
                    class="px-3 py-1.5 rounded-full text-xs whitespace-nowrap transition-all">
                {{ $catMeta['title'] }}
            </button>
            @endforeach
        </div>
    </div>

    {{-- ================================================ --}}
    {{-- 3. FORM MATRIX PERMISSION (1 MAIN PANEL CARD)    --}}
    {{-- ================================================ --}}
    <form action="{{ route('roles.update', $role) }}" method="POST" id="matrixForm" onsubmit="return handleFormSubmit(this);">
        @csrf
        @method('PUT')

        <div class="bg-white dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700/80 rounded-lg shadow-xs overflow-hidden">

            {{-- Panel Header --}}
            <div class="px-4 sm:px-6 py-3.5 bg-slate-50/80 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-700/80 flex items-center justify-between">
                <div>
                    <h2 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                        Feature Tree Permission Matrix — Konfigurasi Hak Akses Berdasarkan Fungsi
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Centang hak akses yang dibutuhkan oleh role ini. Perubahan langsung berlaku secara realtime.
                    </p>
                </div>
                <div class="hidden sm:block">
                    <span class="text-[11px] font-semibold text-slate-400 dark:text-slate-400 uppercase tracking-wider">
                        STATUS PERMISSION
                    </span>
                </div>
            </div>

            {{-- Functional Category Groups Container --}}
            <div id="featureTree" class="divide-y divide-slate-200 dark:divide-slate-700/80">

                @foreach($groupedFeatures as $catKey => $catData)
                @if(count($catData['items']) > 0)
                <div class="category-block border-b border-slate-200 dark:border-slate-700/80 last:border-b-0"
                     x-show="activeCategory === 'all' || activeCategory === '{{ $catKey }}'"
                     data-category-key="{{ $catKey }}">

                    {{-- Functional Category Section Header --}}
                    <div class="px-4 sm:px-6 py-3 bg-slate-100/70 dark:bg-slate-900/60 border-b border-slate-200/80 dark:border-slate-700/80 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div class="flex items-center gap-2.5">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $catData['meta']['badge'] }}">
                                {{ $catData['meta']['title'] }}
                            </span>
                            <span class="text-xs text-slate-500 dark:text-slate-400 hidden md:inline">
                                — {{ $catData['meta']['subtitle'] }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button"
                                    onclick="toggleCategoryGroup('{{ $catKey }}', true)"
                                    class="text-[11px] font-semibold text-sky-600 dark:text-sky-400 hover:underline">
                                Pilih Semua Kategori Ini
                            </button>
                            <span class="text-slate-300 dark:text-slate-700">|</span>
                            <button type="button"
                                    onclick="toggleCategoryGroup('{{ $catKey }}', false)"
                                    class="text-[11px] font-medium text-slate-500 dark:text-slate-400 hover:underline">
                                Hapus
                            </button>
                        </div>
                    </div>

                    {{-- Features List under Category --}}
                    <div class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        @foreach($catData['items'] as $feature)

                        {{-- Root Feature Node --}}
                        <div class="feature-group-node"
                             data-feature-code="{{ $feature->code }}"
                             data-feature-name="{{ strtolower($featureMeta[$feature->code]['name'] ?? $feature->name) }}"
                             x-data="{ expanded: true }">

                            {{-- Feature Header --}}
                            <div class="flex items-center justify-between px-4 sm:px-6 py-3 bg-slate-50/40 dark:bg-slate-800/40 hover:bg-slate-100/60 dark:hover:bg-slate-700/40 transition-colors select-none cursor-pointer"
                                 @click="expanded = !expanded">
                                <div class="flex items-center gap-3 min-w-0">
                                    <button type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-transform duration-200 focus:outline-none"
                                            :class="expanded ? 'rotate-90' : ''">
                                        <svg class="h-4 w-4 stroke-[2.5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </button>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-sm font-bold text-slate-900 dark:text-slate-100">
                                                {{ $featureMeta[$feature->code]['name'] ?? $feature->name }}
                                            </span>

                                        </div>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                            {{ $featureMeta[$feature->code]['desc'] ?? $descriptions[$feature->code] ?? 'Pengaturan hak akses modul ' . $feature->name }}
                                        </p>
                                    </div>
                                </div>

                                {{-- Toggle Feature Group Checkbox --}}
                                <div @click.stop class="flex items-center gap-2 ml-4 flex-shrink-0">
                                    <label class="inline-flex items-center gap-1.5 cursor-pointer text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white">
                                        <input type="checkbox"
                                               class="feature-toggle rounded border-slate-300 dark:border-slate-600 text-sky-600 focus:ring-2 focus:ring-sky-500/20"
                                               data-feature="{{ $feature->id }}"
                                               onchange="toggleFeatureGroup(this)"
                                               aria-label="Pilih semua permission {{ $feature->name }}">
                                        <span class="hidden sm:inline">Pilih Fitur Ini</span>
                                    </label>
                                </div>
                            </div>

                            {{-- Feature Content (Permissions & Children) --}}
                            <div x-show="expanded" x-collapse class="bg-white dark:bg-slate-800/80">

                                {{-- Root Level Permissions --}}
                                @if(isset($permissions[$feature->id]) && $permissions[$feature->id]->count())
                                <div class="px-6 sm:px-10 py-3 flex flex-wrap gap-2 border-b border-slate-100 dark:border-slate-700/50"
                                     data-feature-id="{{ $feature->id }}">
                                    @foreach($permissions[$feature->id] as $perm)
                                    @php
                                        $info = $getPermissionInfo($perm);
                                        $isSensitive = str_contains($perm->code, 'sensitive');
                                    @endphp
                                    <label class="perm-label flex items-start gap-2.5 cursor-pointer p-2.5 rounded-lg border border-slate-200 dark:border-slate-700/80 bg-slate-50/50 dark:bg-slate-800/50 hover:bg-slate-100/80 dark:hover:bg-slate-700/60 transition-all group select-none flex-1 min-w-[210px] max-w-xs"
                                           data-perm-code="{{ $perm->code }}"
                                           data-perm-name="{{ strtolower($info['label'] . ' ' . $info['desc']) }}"
                                           title="{{ $info['desc'] }}">
                                        <input type="checkbox"
                                               name="permissions[]"
                                               value="{{ $perm->id }}"
                                               @checked(in_array($perm->id, $rolePermissions))
                                               data-feature-id="{{ $feature->id }}"
                                               data-parent-feature-id="{{ $feature->parent_id ?? '' }}"
                                               data-permission-code="{{ $perm->code }}"
                                               data-is-view="{{ str_ends_with($perm->code, '.view') || $perm->code === 'task.view.all' || $perm->code === 'task.view.own' ? 'true' : 'false' }}"
                                               data-independent-channel="{{ str_ends_with($feature->code, '.qr') ? 'true' : 'false' }}"
                                               onchange="handleCheckboxChange(this)"
                                               class="perm-checkbox mt-0.5 rounded border-slate-300 dark:border-slate-600 transition-all focus:ring-2
                                                      {{ $isSensitive
                                                          ? 'text-rose-600 focus:ring-rose-500/20 border-rose-300 dark:border-rose-700'
                                                          : 'text-sky-600 focus:ring-sky-500/20' }}">
                                        <div class="flex flex-col gap-1 min-w-0">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="px-2 py-0.5 rounded-md text-[11px] font-semibold border {{ $info['badge'] }}">
                                                    {{ $info['label'] }}
                                                    @if($isSensitive)
                                                        <span class="ml-0.5 text-rose-600 font-bold" title="Aksi sensitif — berikan dengan ekstra hati-hati">⚠</span>
                                                    @endif
                                                </span>
                                            </div>
                                            <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-snug">
                                                {{ $info['desc'] }}
                                            </p>
                                        </div>
                                    </label>
                                    @endforeach
                                </div>
                                @elseif($feature->children->count() === 0)
                                <p class="px-6 sm:px-10 py-2.5 text-xs text-slate-400 dark:text-slate-400 italic">
                                    Tidak ada permission spesifik untuk fitur ini.
                                </p>
                                @endif

                                {{-- Sub-Features --}}
                                @if($feature->children->count() > 0)
                                <div class="divide-y divide-slate-100 dark:divide-slate-700/50">
                                    @foreach($feature->children as $child)
                                    <div class="pl-4 sm:pl-8"
                                         data-feature-code="{{ $child->code }}"
                                         data-feature-name="{{ strtolower($featureMeta[$child->code]['name'] ?? $child->name) }}"
                                         x-data="{ childExpanded: true }">

                                        {{-- Sub Feature Header --}}
                                        <div class="flex items-center justify-between px-4 sm:px-6 py-2.5 hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition-colors select-none cursor-pointer"
                                             @click="childExpanded = !childExpanded">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <button type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-transform duration-200 focus:outline-none"
                                                        :class="childExpanded ? 'rotate-90' : ''">
                                                    <svg class="h-3.5 w-3.5 stroke-[2.5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                                    </svg>
                                                </button>
                                                <div class="min-w-0">
                                                    <div class="flex items-center gap-2 flex-wrap">
                                                        <span class="text-xs sm:text-sm font-semibold text-slate-800 dark:text-slate-200">
                                                            {{ $featureMeta[$child->code]['name'] ?? $child->name }}
                                                        </span>
                                                    </div>
                                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                                        {{ $featureMeta[$child->code]['desc'] ?? $descriptions[$child->code] ?? 'Akses sub-fitur ' . $child->name }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Sub Feature Permissions & Grandchildren --}}
                                        <div x-show="childExpanded" x-collapse class="pb-2">
                                            @if(isset($permissions[$child->id]) && $permissions[$child->id]->count())
                                            <div class="px-6 sm:px-8 py-2.5 flex flex-wrap gap-2"
                                                 data-feature-id="{{ $child->id }}">
                                                @foreach($permissions[$child->id] as $perm)
                                                @php
                                                    $info = $getPermissionInfo($perm);
                                                    $isSensitive = str_contains($perm->code, 'sensitive');
                                                @endphp
                                                <label class="perm-label flex items-start gap-2.5 cursor-pointer p-2.5 rounded-lg border border-slate-200 dark:border-slate-700/80 bg-slate-50/50 dark:bg-slate-800/50 hover:bg-slate-100/80 dark:hover:bg-slate-700/60 transition-all group select-none flex-1 min-w-[210px] max-w-xs"
                                                       data-perm-code="{{ $perm->code }}"
                                                       data-perm-name="{{ strtolower($info['label'] . ' ' . $info['desc']) }}"
                                                       title="{{ $info['desc'] }}">
                                                    <input type="checkbox"
                                                           name="permissions[]"
                                                           value="{{ $perm->id }}"
                                                           @checked(in_array($perm->id, $rolePermissions))
                                                           data-feature-id="{{ $child->id }}"
                                                           data-parent-feature-id="{{ $child->parent_id ?? '' }}"
                                                           data-permission-code="{{ $perm->code }}"
                                                           data-is-view="{{ str_ends_with($perm->code, '.view') || $perm->code === 'task.view.all' || $perm->code === 'task.view.own' ? 'true' : 'false' }}"
                                                           data-independent-channel="{{ str_ends_with($child->code, '.qr') ? 'true' : 'false' }}"
                                                           onchange="handleCheckboxChange(this)"
                                                           class="perm-checkbox mt-0.5 rounded border-slate-300 dark:border-slate-600 transition-all focus:ring-2
                                                                  {{ $isSensitive
                                                                      ? 'text-rose-600 focus:ring-rose-500/20 border-rose-300 dark:border-rose-700'
                                                                      : 'text-sky-600 focus:ring-sky-500/20' }}">
                                                    <div class="flex flex-col gap-1 min-w-0">
                                                        <div class="flex items-center gap-1.5 flex-wrap">
                                                            <span class="px-2 py-0.5 rounded-md text-[11px] font-semibold border {{ $info['badge'] }}">
                                                                {{ $info['label'] }}
                                                                @if($isSensitive)
                                                                    <span class="ml-0.5 text-rose-600 font-bold" title="Aksi sensitif">⚠</span>
                                                                @endif
                                                            </span>
                                                        </div>
                                                        <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-snug">
                                                            {{ $info['desc'] }}
                                                        </p>
                                                    </div>
                                                </label>
                                                @endforeach
                                            </div>
                                            @endif

                                            {{-- Mini Features (Grandchildren) --}}
                                            @if($child->children->count() > 0)
                                            <div class="mx-4 sm:mx-6 mt-1 border-l-2 border-slate-200 dark:border-slate-700/80 pl-4 space-y-2.5">
                                                @foreach($child->children as $grandchild)
                                                <div class="py-2"
                                                     data-feature-code="{{ $grandchild->code }}"
                                                     data-feature-name="{{ strtolower($featureMeta[$grandchild->code]['name'] ?? $grandchild->name) }}">
                                                    
                                                    {{-- Mini feature label --}}
                                                    <div class="flex flex-col gap-0.5 mb-2">
                                                        <div class="flex items-center gap-2">
                                                            <span class="h-1.5 w-1.5 rounded-full bg-sky-500"></span>
                                                            <span class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                                                {{ $featureMeta[$grandchild->code]['name'] ?? $grandchild->name }}
                                                            </span>
                                                            <span class="font-mono text-[10px] text-slate-400 dark:text-slate-400">
                                                                ({{ $grandchild->code }})
                                                            </span>
                                                        </div>
                                                        <p class="text-[11px] text-slate-500 dark:text-slate-400 pl-3.5">
                                                            {{ $featureMeta[$grandchild->code]['desc'] ?? $descriptions[$grandchild->code] ?? '' }}
                                                        </p>
                                                    </div>

                                                    @if(isset($permissions[$grandchild->id]) && $permissions[$grandchild->id]->count())
                                                    <div class="flex flex-wrap gap-2 pl-3.5"
                                                         data-feature-id="{{ $grandchild->id }}">
                                                        @foreach($permissions[$grandchild->id] as $perm)
                                                        @php
                                                            $info = $getPermissionInfo($perm);
                                                            $isSensitive = str_contains($perm->code, 'sensitive');
                                                        @endphp
                                                        <label class="perm-label flex items-start gap-2.5 cursor-pointer p-2.5 rounded-lg border border-slate-200 dark:border-slate-700/80 bg-slate-50/50 dark:bg-slate-800/50 hover:bg-slate-100/80 dark:hover:bg-slate-700/60 transition-all group select-none flex-1 min-w-[210px] max-w-xs"
                                                               data-perm-code="{{ $perm->code }}"
                                                               data-perm-name="{{ strtolower($info['label'] . ' ' . $info['desc']) }}"
                                                               title="{{ $info['desc'] }}">
                                                            <input type="checkbox"
                                                                   name="permissions[]"
                                                                   value="{{ $perm->id }}"
                                                                   @checked(in_array($perm->id, $rolePermissions))
                                                                   data-feature-id="{{ $grandchild->id }}"
                                                                   data-parent-feature-id="{{ $grandchild->parent_id ?? '' }}"
                                                                   data-permission-code="{{ $perm->code }}"
                                                                   data-is-view="{{ str_ends_with($perm->code, '.view') || $perm->code === 'task.view.all' || $perm->code === 'task.view.own' ? 'true' : 'false' }}"
                                                                   data-independent-channel="{{ str_ends_with($grandchild->code, '.qr') ? 'true' : 'false' }}"
                                                                   onchange="handleCheckboxChange(this)"
                                                                   class="perm-checkbox mt-0.5 rounded border-slate-300 dark:border-slate-600 transition-all focus:ring-2
                                                                          {{ $isSensitive
                                                                              ? 'text-rose-600 focus:ring-rose-500/20 border-rose-300 dark:border-rose-700'
                                                                              : 'text-sky-600 focus:ring-sky-500/20' }}">
                                                            <div class="flex flex-col gap-1 min-w-0">
                                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                                    <span class="px-2 py-0.5 rounded-md text-[11px] font-semibold border {{ $info['badge'] }}">
                                                                        {{ $info['label'] }}
                                                                        @if($isSensitive)
                                                                            <span class="ml-0.5 text-rose-600 font-bold" title="Aksi sensitif">⚠</span>
                                                                        @endif
                                                                    </span>
                                                                </div>
                                                                <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-snug">
                                                                    {{ $info['desc'] }}
                                                                </p>
                                                            </div>
                                                        </label>
                                                        @endforeach
                                                    </div>
                                                    @endif
                                                </div>
                                                @endforeach
                                            </div>
                                            @endif

                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                                @endif

                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
                @endforeach

            </div>

            {{-- Panel Bottom Action Sticky Bar --}}
            <div class="sticky bottom-0 z-10 px-4 sm:px-6 py-3.5 bg-slate-50/95 dark:bg-slate-900/95 backdrop-blur-xs border-t border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row items-center justify-between gap-3 shadow-lg">
                <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                    <svg class="h-4 w-4 text-sky-600 dark:text-sky-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Perubahan permission akan dicatat ke <strong class="text-slate-700 dark:text-slate-300">Audit Log</strong> dan berlaku instan untuk staf terkait.</span>
                </div>
                <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                    <a href="{{ route('roles.index') }}"
                       class="px-4 py-2 text-xs sm:text-sm font-semibold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-all">
                        Batal
                    </a>
                    <button type="submit"
                            form="matrixForm"
                            class="px-5 py-2 text-xs sm:text-sm font-semibold text-white bg-sky-600 hover:bg-sky-700 active:bg-sky-800 dark:bg-sky-500 dark:hover:bg-sky-600 rounded-lg shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-sky-500">
                        Simpan Permission
                    </button>
                </div>
            </div>

        </div>
    </form>

</div>

@section('scripts')
<script>
    let formSubmitted = false;
    function handleFormSubmit(form) {
        if (formSubmitted) return false;
        formSubmitted = true;

        const submitButtons = form.querySelectorAll('button[type="submit"]');
        submitButtons.forEach(btn => {
            btn.disabled = true;
            btn.innerHTML = `
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Menyimpan...
            `;
        });
        return true;
    }

    // Toast warning untuk role sistem
    @if($role->is_system && !$role->isOwner())
    document.addEventListener('DOMContentLoaded', () => {
        if (window.Toast) {
            Toast.warning(
                'Role Standar Sistem',
                'Perubahan permission berlaku untuk semua pengguna dengan role {{ $role->name }}.',
                7000
            );
        }
    });
    @endif

    function updatePermCount() {
        const count = document.querySelectorAll('.perm-checkbox:checked').length;
        const el = document.getElementById('permCount');
        if (el) el.textContent = count;
        updateFeatureToggles();
    }

    // Dependency chaining resolution (parent-child)
    function resolvePermissionDependencies() {
        const checkboxes = Array.from(document.querySelectorAll('.perm-checkbox'));
        
        function isFeatureViewChecked(featureId) {
            if (!featureId) return true;
            
            const viewCheckbox = checkboxes.find(cb => 
                cb.dataset.featureId === featureId.toString() && 
                cb.dataset.isView === 'true'
            );
            
            if (!viewCheckbox) return true;
            
            if (!viewCheckbox.checked || viewCheckbox.disabled) {
                return false;
            }
            
            const parentId = viewCheckbox.dataset.parentFeatureId;
            return isFeatureViewChecked(parentId);
        }
        
        checkboxes.forEach(cb => {
            // Channel QR/Portal (`*.qr`, mis. `tickets.qr`, `kolektor.qr`) SENGAJA
            // dilepas dari rantai dependensi "wajib centang .view induk dulu".
            // Fitur `.qr` itu sub-fitur PENGELOMPOKAN menu (nempel di bawah
            // `tickets`/`kolektor` biar rapi di UI), BUKAN tab navigasi yang
            // butuh akses Lihat Data dashboard Operasional induknya — aksesnya
            // datang dari scan QR/Portal Pelanggan, jalur terpisah total dari
            // `tickets.view`/`kolektor.view`. Kalau tetap dirantai, role macam
            // Kolektor jadi TERPAKSA dapat izin dashboard penuh (`tickets.view`)
            // cuma buat bisa centang `tickets.qr.create` — mencampur channel
            // Operasional dengan channel Portal/QR yang harus independen.
            if (cb.dataset.independentChannel === 'true') {
                cb.disabled = false;
                cb.classList.remove('opacity-40', 'cursor-not-allowed');
                return;
            }

            const parentId = cb.dataset.parentFeatureId;

            if (parentId) {
                const parentViewActive = isFeatureViewChecked(parentId);
                if (!parentViewActive) {
                    cb.checked = false;
                    cb.disabled = true;
                    cb.classList.add('opacity-40', 'cursor-not-allowed');
                } else {
                    cb.disabled = false;
                    cb.classList.remove('opacity-40', 'cursor-not-allowed');
                }
            }
        });
    }

    function handleCheckboxChange(changedCb) {
        // Lihat catatan `data-independent-channel` di resolvePermissionDependencies()
        // — centang aksi channel QR/Portal TIDAK boleh ikut memaksa-centang
        // `.view` dashboard Operasional milik fitur induknya.
        if (changedCb.checked && changedCb.dataset.independentChannel !== 'true') {
            let parentId = changedCb.dataset.parentFeatureId;
            const checkboxes = Array.from(document.querySelectorAll('.perm-checkbox'));

            while (parentId) {
                const parentViewCb = checkboxes.find(cb =>
                    cb.dataset.featureId === parentId.toString() &&
                    cb.dataset.isView === 'true'
                );
                if (parentViewCb && !parentViewCb.checked) {
                    parentViewCb.checked = true;
                    parentId = parentViewCb.dataset.parentFeatureId;
                } else {
                    break;
                }
            }
        }

        resolvePermissionDependencies();
        updatePermCount();
    }

    function selectAll() {
        document.querySelectorAll('.perm-checkbox').forEach(cb => { 
            cb.checked = true; 
            cb.disabled = false;
            cb.classList.remove('opacity-40', 'cursor-not-allowed');
        });
        resolvePermissionDependencies();
        updatePermCount();
    }

    function deselectAll() {
        document.querySelectorAll('.perm-checkbox').forEach(cb => { cb.checked = false; });
        resolvePermissionDependencies();
        updatePermCount();
    }

    function toggleCategoryGroup(catKey, isCheck) {
        const catBlock = document.querySelector(`[data-category-key="${catKey}"]`);
        if (catBlock) {
            catBlock.querySelectorAll('.perm-checkbox').forEach(cb => {
                if (!cb.disabled) {
                    cb.checked = isCheck;
                }
            });
        }
        resolvePermissionDependencies();
        updatePermCount();
    }

    function toggleFeatureGroup(toggleCb) {
        const featureId = toggleCb.dataset.feature;
        const featureGroup = document.querySelector(`[data-feature-id="${featureId}"]`);
        if (featureGroup) {
            featureGroup.querySelectorAll('.perm-checkbox').forEach(cb => {
                if (!cb.disabled) {
                    cb.checked = toggleCb.checked;
                }
            });
        }
        resolvePermissionDependencies();
        updatePermCount();
    }

    function updateFeatureToggles() {
        document.querySelectorAll('.feature-toggle').forEach(toggle => {
            const featureId = toggle.dataset.feature;
            const featureGroup = document.querySelector(`[data-feature-id="${featureId}"]`);
            if (!featureGroup) return;

            const all = featureGroup.querySelectorAll('.perm-checkbox');
            const checked = featureGroup.querySelectorAll('.perm-checkbox:checked');

            toggle.indeterminate = checked.length > 0 && checked.length < all.length;
            toggle.checked = all.length > 0 && checked.length === all.length;
        });
    }

    function expandAllGroups() {
        document.querySelectorAll('.feature-group-node').forEach(el => {
            if (el.__x) el.__x.$data.expanded = true;
        });
    }

    function collapseAllGroups() {
        document.querySelectorAll('.feature-group-node').forEach(el => {
            if (el.__x) el.__x.$data.expanded = false;
        });
    }

    function filterPermissions(query) {
        const q = query.toLowerCase().trim();
        const permLabels = document.querySelectorAll('.perm-label');

        if (!q) {
            permLabels.forEach(el => el.classList.remove('hidden'));
            document.querySelectorAll('[data-feature-code]').forEach(el => el.classList.remove('hidden'));
            document.querySelectorAll('[data-category-key]').forEach(el => el.classList.remove('hidden'));
            return;
        }

        permLabels.forEach(el => {
            const code = el.dataset.permCode.toLowerCase();
            const name = el.dataset.permName.toLowerCase();
            if (code.includes(q) || name.includes(q)) {
                el.classList.remove('hidden');
            } else {
                el.classList.add('hidden');
            }
        });

        // Sembunyikan node fitur jika tidak ada permission yang cocok
        document.querySelectorAll('[data-feature-code]').forEach(group => {
            const visiblePerms = group.querySelectorAll('.perm-label:not(.hidden)');
            const featureName = group.dataset.featureName || '';
            const featureCode = group.dataset.featureCode || '';

            if (visiblePerms.length > 0 || featureName.includes(q) || featureCode.includes(q)) {
                group.classList.remove('hidden');
                if (group.__x) group.__x.$data.expanded = true;
            } else {
                group.classList.add('hidden');
            }
        });

        // Sembunyikan kategori jika seluruh fiturnya tersembunyi
        document.querySelectorAll('[data-category-key]').forEach(catBlock => {
            const visibleFeatures = catBlock.querySelectorAll('[data-feature-code]:not(.hidden)');
            if (visibleFeatures.length > 0) {
                catBlock.classList.remove('hidden');
            } else {
                catBlock.classList.add('hidden');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        resolvePermissionDependencies();
        updatePermCount();
        document.querySelectorAll('.perm-checkbox').forEach(cb => {
            cb.addEventListener('change', () => {
                resolvePermissionDependencies();
                updateFeatureToggles();
            });
        });
        updateFeatureToggles();
    });
</script>
@endsection

@section('styles')
<style>
    /* Styling scrollbar halus untuk category tabs & filter */
    .scrollbar-thin::-webkit-scrollbar {
        height: 4px;
    }
    .scrollbar-thin::-webkit-scrollbar-track {
        background: transparent;
    }
    .scrollbar-thin::-webkit-scrollbar-thumb {
        background: rgba(148, 163, 184, 0.4);
        border-radius: 4px;
    }
</style>
@endsection

@endsection
