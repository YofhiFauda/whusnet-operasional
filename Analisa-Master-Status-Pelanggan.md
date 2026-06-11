# Analisa Master Status Pelanggan

## 1. Kenapa Master Status Pelanggan Perlu Diimplementasikan

Master Status Pelanggan diperlukan karena status pelanggan/langganan menjadi dasar utama workflow operasional. Status seperti `registered`, `waiting_survey`, `waiting_installation`, `active`, `suspended`, dan `terminated` bukan sekadar label tampilan, tetapi menentukan posisi pelanggan di dalam proses bisnis.

Tanpa master status, nilai status berisiko tersebar sebagai teks hardcode di controller, view, dashboard, filter, laporan, billing, instalasi, dan modul lain. Akibatnya, sistem mudah menjadi tidak konsisten. Contohnya satu bagian memakai `Active`, bagian lain memakai `active`, dan bagian lain lagi memakai `Aktif`.

Dengan master status:

- Alur pelanggan lebih jelas dari registrasi sampai aktivasi, suspend, terminasi, atau penolakan.
- Filter pelanggan, dashboard, laporan, dan detail pelanggan memakai sumber data status yang sama.
- Sistem bisa membedakan status proses dan status akhir seperti `terminated` atau `rejected`.
- Status dapat menjadi dasar aturan workflow, misalnya hanya pelanggan dengan status `surveyed` yang boleh masuk ke `waiting_installation`.
- Perubahan label tampilan bisa dilakukan tanpa mengubah logika sistem selama `code` tetap stabil.

## 2. Kekurangan Master Status Pelanggan

Master Status Pelanggan memiliki risiko karena menjadi titik sensitif di sistem. Jika status diubah sembarangan, efeknya bisa menjalar ke workflow, dashboard, billing, laporan, dan proses operasional lain.

Beberapa kekurangannya:

- Sistem harus disiplin memakai `code` sebagai nilai teknis, bukan label tampilan.
- Master status perlu aturan transisi, bukan hanya daftar status.
- Status bawaan sistem tidak boleh diperlakukan sama seperti master data biasa.
- Jika pengguna bisa menghapus atau mengubah status inti, data historis dan workflow bisa rusak.
- Database existing perlu migrasi status lama agar tidak tercampur, misalnya `Active` harus dikonversi menjadi `active`.

Karena itu, master status perlu pengamanan pada level desain data, UI, dan logic aplikasi.

## 3. Tambah, Edit, dan Hapus Status Ke Depan

Ke depan fitur tambah, edit, dan hapus status bisa disediakan, tetapi harus dibatasi. Tidak semua bagian dari status boleh diubah secara bebas.

Rekomendasi:

- **Tambah status** boleh untuk kebutuhan operasional tambahan atau custom workflow.
- **Edit label, deskripsi, warna badge, dan urutan tampilan** boleh karena tidak langsung merusak referensi teknis.
- **Edit `code` status inti** sebaiknya tidak boleh, karena `code` dipakai oleh sistem sebagai referensi workflow.
- **Hapus status** sebaiknya tidak menggunakan hard delete. Gunakan `is_active = false` atau archive.
- **Status inti** seperti `registered`, `active`, `suspended`, `terminated`, dan `rejected` harus dikunci sebagai system status.

Desain ideal untuk master status:

| Field | Fungsi |
| --- | --- |
| `code` | Nilai teknis permanen yang dipakai sistem. |
| `name` | Label tampilan yang boleh diedit. |
| `description` | Penjelasan status untuk operasional. |
| `workflow_order` | Urutan status dalam workflow. |
| `badge_color` | Warna tampilan status di UI. |
| `is_terminal` | Menandai status akhir seperti `terminated` atau `rejected`. |
| `is_active` | Menentukan status masih bisa digunakan atau tidak. |
| `is_system` | Menandai status bawaan sistem yang tidak boleh dihapus atau diubah `code`-nya. |
| `allowed_next_statuses` | Aturan status berikutnya yang valid. |

Dengan pendekatan ini, Master Status Pelanggan tetap fleksibel untuk kebutuhan operasional, tetapi tidak merusak jalannya sistem.
