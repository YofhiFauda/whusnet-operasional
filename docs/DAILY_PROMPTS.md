# DAILY_PROMPTS.md

# Prompt Harian untuk AI Coding

## Tujuan Dokumen

Dokumen ini berisi prompt siap pakai agar AI bekerja sesuai scope PRD.

Gunakan urutan ini setiap hari:

```txt
1. Prompt Awal Sesi
2. Prompt Scope Check
3. Prompt Builder
4. Prompt Reviewer
5. Prompt Fix Jika Ada Error
6. Prompt Update TASKS
7. Prompt Commit Message
```

---

# 1. Prompt Awal Sesi

Gunakan ini setiap pertama kali membuka AI coding.

```md
Baca semua dokumen project berikut sebelum bekerja:

- AGENTS.md
- docs/PROJECT_CONTEXT.md
- docs/MVP_SCOPE.md
- docs/IMPLEMENTATION_PLAN.md
- docs/TASKS.md
- docs/ACCEPTANCE_CRITERIA.md
- docs/DATABASE_CONCEPT.md
- docs/BUSINESS_RULES.md
- docs/STATUS_FLOW.md
- docs/RBAC_MATRIX.md
- docs/DATABASE_RULES.md
- docs/IMPORT_SPEC.md
- docs/ID_NUMBERING_RULES.md
- docs/DEFINITION_OF_DONE.md
- docs/PAGE_STRUCTURE.md
- docs/CUSTOMER_DETAIL_SPEC.md
- docs/MVP_SUCCESS_CHECKLIST.md

Jangan coding dulu.

Tugas Anda sekarang hanya memahami posisi project.

Jawab:

## Posisi Project
- Sprint aktif:
- Task aktif:
- Modul aktif:

## Scope yang Boleh Dikerjakan
Tuliskan hanya hal yang boleh dikerjakan berdasarkan task aktif.

## Scope yang Tidak Boleh Dikerjakan
Tuliskan modul/fitur yang tidak boleh disentuh sekarang.

## Dokumen yang Relevan
Sebutkan dokumen mana yang paling relevan untuk task aktif.

## Acceptance Criteria
Tuliskan acceptance criteria task aktif.

## Risiko Scope Creep
Jelaskan risiko jika task melebar ke modul lain.

## Rekomendasi
Apakah task ini siap dikerjakan atau perlu dipecah lagi?

Jangan implementasi sebelum saya menyetujui.
```

---

# 2. Prompt Scope Check Sebelum Coding

Gunakan ini sebelum menyuruh AI menulis kode.

```md
Lakukan scope check untuk task aktif di docs/TASKS.md.

Jangan coding dulu.

Jawab:

1. Task aktif apa?
2. Sprint aktif apa?
3. Modul apa yang boleh disentuh?
4. Modul apa yang tidak boleh disentuh?
5. File apa yang akan dibuat?
6. File apa yang akan diubah?
7. Tabel database apa yang terlibat?
8. Route apa yang akan dibuat/diubah?
9. Permission apa yang dibutuhkan?
10. Apakah POP scope diperlukan?
11. Apakah audit log diperlukan?
12. Business rules apa yang relevan?
13. Status flow apa yang relevan?
14. Database rules apa yang relevan?
15. Acceptance criteria apa yang harus dipenuhi?
16. Cara test manualnya bagaimana?
17. Apakah ada risiko keluar dari MVP?
18. Apakah ada requirement yang masih ambigu?

Jangan coding sebelum saya menyetujui rencana Anda.
```

---

# 3. Prompt Builder

Gunakan setelah rencana AI sudah benar.

```md
Anda bertindak sebagai Builder.

Kerjakan hanya task aktif di docs/TASKS.md.

Ikuti dokumen berikut:

- AGENTS.md
- docs/TASKS.md
- docs/BUSINESS_RULES.md
- docs/STATUS_FLOW.md
- docs/RBAC_MATRIX.md
- docs/DATABASE_RULES.md
- docs/DEFINITION_OF_DONE.md

Aturan:

1. Jangan mengerjakan task lain.
2. Jangan mengerjakan sprint berikutnya.
3. Jangan membuat fitur post-MVP.
4. Jangan mengubah file yang tidak relevan.
5. Jangan membuat asumsi jika requirement belum jelas.
6. Jangan membuat invoice jika task bukan invoice.
7. Jangan membuat payment jika task bukan payment.
8. Jangan membuat fitur MikroTik, payment gateway, auto suspend, WhatsApp, ticketing, monitoring, inventory kompleks, atau mobile app.
9. Gunakan validasi sesuai business rules.
10. Terapkan permission jika membuat route/menu.
11. Terapkan POP scope jika data berhubungan dengan cabang.
12. Gunakan audit log jika task menyentuh data penting dan audit foundation sudah tersedia.

Setelah implementasi, berikan output:

## Task Selesai
Nama task:

## Scope Check
- Sesuai task aktif: Ya/Tidak
- Keluar MVP: Ya/Tidak
- Menyentuh sprint lain: Ya/Tidak

## File Diubah
- file 1
- file 2

## Alasan Perubahan
Jelaskan singkat.

## Cara Test
1. ...
2. ...
3. ...

## Acceptance Criteria
- [x] ...
- [ ] ...

## Risiko / Catatan
Tuliskan jika ada.

## Update TASKS.md
Tuliskan perubahan status task.

## Next Task
Sebutkan task berikutnya.
```

---

# 4. Prompt Reviewer

Gunakan setelah AI selesai coding.

```md
Anda bertindak sebagai Reviewer.

Review perubahan kode terakhir berdasarkan:

- AGENTS.md
- docs/TASKS.md
- docs/ACCEPTANCE_CRITERIA.md
- docs/BUSINESS_RULES.md
- docs/STATUS_FLOW.md
- docs/RBAC_MATRIX.md
- docs/DATABASE_RULES.md
- docs/IMPORT_SPEC.md
- docs/ID_NUMBERING_RULES.md
- docs/DEFINITION_OF_DONE.md
- docs/PAGE_STRUCTURE.md
- docs/CUSTOMER_DETAIL_SPEC.md

Jangan membuat fitur baru.

Cek:

1. Apakah perubahan sesuai task aktif?
2. Apakah ada fitur di luar scope MVP?
3. Apakah ada modul sprint berikutnya yang ikut tersentuh?
4. Apakah acceptance criteria terpenuhi?
5. Apakah business rules dilanggar?
6. Apakah status flow dilanggar?
7. Apakah RBAC sudah benar?
8. Apakah route aman dari akses langsung?
9. Apakah query data POP sudah dibatasi jika relevan?
10. Apakah field sensitif aman?
11. Apakah database sesuai rules?
12. Apakah import sesuai spec jika task import?
13. Apakah ID numbering sesuai rules jika task ID?
14. Apakah audit log diperlukan dan sudah ada?
15. Apakah ada file yang tidak seharusnya diubah?
16. Apakah task boleh ditandai Done?

Output review:

## Kesimpulan Review
Layak / Belum Layak

## Temuan
Tuliskan temuan.

## Pelanggaran Scope
Tuliskan jika ada.

## Perbaikan Wajib
Tuliskan perbaikan yang harus dilakukan.

## Perbaikan Opsional
Tuliskan perbaikan tambahan.

## Status Acceptance Criteria
- [x] ...
- [ ] ...

## Keputusan
Boleh lanjut / Perbaiki dulu
```

---

# 5. Prompt Fix Error

Gunakan jika ada error.

```md
Anda bertindak sebagai Debugger.

Saya mendapatkan error berikut:

[PASTE ERROR DI SINI]

Baca dokumen berikut:

- AGENTS.md
- docs/TASKS.md
- docs/BUSINESS_RULES.md
- docs/DATABASE_RULES.md
- docs/DEFINITION_OF_DONE.md

Aturan:

1. Fokus hanya pada error ini.
2. Jangan membuat fitur baru.
3. Jangan mengerjakan task lain.
4. Jangan menyentuh modul lain jika tidak terkait error.
5. Jangan mengubah business rules.
6. Jelaskan penyebab error.
7. Jelaskan file yang perlu dicek.
8. Jelaskan rencana perbaikan.
9. Jangan coding sebelum saya setujui.
10. Setelah fix, jelaskan cara test.

Output:

## Penyebab Error
...

## File yang Terkait
...

## Rencana Perbaikan
...

## Risiko
...

## Cara Test Setelah Fix
...
```

---

# 6. Prompt Update TASKS.md

Gunakan setelah task benar-benar selesai.

```md
Baca docs/TASKS.md dan docs/DEFINITION_OF_DONE.md.

Task aktif sudah selesai dan sudah lolos review.

Tugas Anda:

1. Pindahkan task aktif dari In Progress ke Done.
2. Tambahkan catatan hasil test.
3. Jadikan task berikutnya sebagai In Progress.
4. Jangan mengubah urutan sprint.
5. Jangan membuat task baru kecuali memang ada gap dari PRD.
6. Jika ada task baru, masukkan sebagai Todo dan jelaskan alasannya.

Sebelum mengubah, tampilkan dulu:

## Task yang Akan Diubah ke Done
...

## Task Berikutnya yang Akan Menjadi In Progress
...

## Alasan
...

Tunggu persetujuan sebelum update.
```

---

# 7. Prompt Cegah AI Keluar Scope

Gunakan jika AI mulai membahas fitur lain.

```md
Rencana Anda keluar scope.

Task aktif saat ini hanya:

[ISI TASK AKTIF]

Jangan membahas atau membuat:

- Modul sprint berikutnya
- Fitur post-MVP
- MikroTik
- Payment gateway
- Auto suspend
- WhatsApp notification
- Ticketing kompleks
- Monitoring OLT/SNMP
- Inventory kompleks
- Mobile app
- Invoice jika task bukan invoice
- Payment jika task bukan payment

Ulangi scope check berdasarkan:

- docs/TASKS.md
- docs/MVP_SCOPE.md
- docs/BUSINESS_RULES.md
- docs/DEFINITION_OF_DONE.md

Fokus hanya pada task aktif.
```

---

# 8. Prompt Untuk Database/Migration

Gunakan sebelum membuat migration/model.

```md
Baca dokumen berikut:

- docs/DATABASE_RULES.md
- docs/DATABASE_CONCEPT.md
- docs/BUSINESS_RULES.md
- docs/TASKS.md

Saya ingin Anda membuat database/migration sesuai task aktif.

Jangan coding dulu.

Jawab:

1. Tabel apa yang akan dibuat/diubah?
2. Field apa saja yang dibutuhkan?
3. Tipe data apa yang digunakan?
4. Field mana yang wajib?
5. Field mana yang nullable?
6. Unique constraint apa yang dibutuhkan?
7. Index apa yang dibutuhkan?
8. Foreign key apa yang dibutuhkan?
9. Relasi model apa yang dibutuhkan?
10. Apakah perlu soft delete atau status nonaktif?
11. Apakah data ini perlu audit log?
12. Apakah ada risiko melanggar PRD?
13. Apakah tabel ini termasuk MVP?

Jangan implementasi sebelum saya menyetujui.
```

---

# 9. Prompt Untuk Halaman UI

Gunakan sebelum membuat halaman.

```md
Baca dokumen berikut:

- docs/PAGE_STRUCTURE.md
- docs/RBAC_MATRIX.md
- docs/BUSINESS_RULES.md
- docs/TASKS.md

Saya ingin Anda membuat halaman sesuai task aktif.

Jangan coding dulu.

Jawab:

1. Halaman apa yang akan dibuat?
2. Tujuan halaman?
3. Role apa yang boleh mengakses?
4. Permission apa yang dibutuhkan?
5. Data apa yang ditampilkan?
6. Kolom tabel apa saja?
7. Filter apa saja?
8. Search berdasarkan apa?
9. Tombol aksi apa saja?
10. Field form apa saja?
11. Validasi form apa saja?
12. Apakah halaman membutuhkan POP scope?
13. Apakah field sensitif perlu dibatasi?
14. Acceptance criteria apa yang harus terpenuhi?

Jangan implementasi sebelum saya menyetujui.
```

---

# 10. Prompt Untuk Detail Pelanggan

Gunakan sebelum membuat halaman detail pelanggan.

```md
Baca dokumen berikut:

- docs/CUSTOMER_DETAIL_SPEC.md
- docs/BUSINESS_RULES.md
- docs/STATUS_FLOW.md
- docs/RBAC_MATRIX.md
- docs/DATABASE_RULES.md
- docs/TASKS.md

Saya ingin Anda membuat atau mengubah Detail Pelanggan sesuai task aktif.

Jangan coding dulu.

Jawab:

1. Tab apa yang akan dibuat/diubah?
2. Data apa yang ditampilkan?
3. Field apa yang bisa diedit?
4. Role apa yang boleh melihat?
5. Role apa yang boleh mengubah?
6. Permission apa yang dibutuhkan?
7. Field sensitif apa yang harus dibatasi?
8. Apakah perubahan ini memengaruhi status kelengkapan?
9. Apakah perubahan ini memengaruhi status layanan?
10. Apakah perubahan ini memengaruhi billing?
11. Apakah audit log diperlukan?
12. Apakah POP scope diperlukan?
13. Acceptance criteria apa yang harus terpenuhi?

Jangan implementasi sebelum saya menyetujui.
```

---

# 11. Prompt Untuk Import

Gunakan sebelum membuat fitur import.

```md
Baca dokumen berikut:

- docs/IMPORT_SPEC.md
- docs/BUSINESS_RULES.md
- docs/DATABASE_RULES.md
- docs/STATUS_FLOW.md
- docs/ID_NUMBERING_RULES.md
- docs/TASKS.md

Saya ingin Anda mengerjakan task import yang sedang aktif.

Jangan coding dulu.

Jawab:

1. Task import aktif apa?
2. Bagian import mana yang dikerjakan?
3. Apakah ini template, upload, mapping, preview, validasi, konfirmasi, atau log import?
4. Field wajib apa saja?
5. Field opsional apa saja?
6. Validasi apa saja?
7. Data invalid disimpan ke mana?
8. Data valid masuk ke tabel apa saja?
9. Apakah import membuat ID Request?
10. Apakah import membuat CID?
11. Apakah import membuat invoice? Jawaban harus tidak untuk MVP.
12. Apakah import membuat payment? Jawaban harus tidak.
13. Bagaimana cara test?
14. Acceptance criteria apa yang harus terpenuhi?

Jangan implementasi sebelum saya menyetujui.
```

---

# 12. Prompt Untuk ID Numbering

Gunakan sebelum membuat ID Request atau CID.

```md
Baca dokumen berikut:

- docs/ID_NUMBERING_RULES.md
- docs/DATABASE_RULES.md
- docs/BUSINESS_RULES.md
- docs/STATUS_FLOW.md
- docs/TASKS.md

Saya ingin Anda mengerjakan aturan ID berdasarkan POP.

Jangan coding dulu.

Jawab:

1. Task aktif apa?
2. Apakah task ini membuat ID Request, CID, atau sequence?
3. Tabel apa saja yang terlibat?
4. Field apa yang perlu ditambahkan?
5. Kapan ID Request dibuat?
6. Kapan CID dibuat?
7. Format ID apa yang digunakan?
8. Bagaimana mencegah ID duplikat?
9. Apakah perlu database transaction atau row lock?
10. Apakah import boleh membuat CID?
11. Apakah aktivasi wajib membuat CID?
12. Acceptance criteria apa yang harus dipenuhi?

Jangan implementasi sebelum saya menyetujui.
```

---

# 13. Prompt Commit Message

Gunakan setelah task selesai.

```md
Buatkan commit message untuk perubahan terakhir.

Format:

type(scope): short summary

Body:
- Apa yang berubah
- Kenapa berubah
- File/modul utama yang terdampak
- Cara test
- Acceptance criteria yang terpenuhi

Gunakan type:
- feat
- fix
- docs
- refactor
- test
- chore

Jangan terlalu panjang.
```

---

# 14. Prompt Khusus Advanced Hierarchical RBAC

Gunakan prompt ini saat hendak menginstruksikan AI untuk mengimplementasikan modul Advanced Hierarchical RBAC agar tidak salah arsitektur.

## 14.1 Prompt Advanced RBAC Scope Check Sebelum Coding
```md
Baca dokumen docs/RBAC_MATRIX.md dan docs/DATABASE_RULES.md.
Sebelum Anda menulis kode, mari lakukan Scope Check khusus Advanced RBAC.

Jawab pertanyaan ini secara terstruktur:
1. Modul/fitur apa yang akan Anda sentuh?
2. Bagaimana struktur permission {feature_code}.{action_code} yang akan digunakan?
3. Apakah user target memerlukan User Scope (all_pop, selected_pop, pop_tree, assigned_only, own_created)?
4. Bagaimana Anda akan memisahkan hak akses aksi (Role) dengan hak akses wilayah data (Scope)?
5. Apakah ada potensi "Role per Cabang" yang tidak sengaja terbuat? (Wajib Jawab: Tidak boleh ada role cabang).
6. Apakah ada hardcode izin nama role di middleware/controller? (Wajib Jawab: Tidak boleh, harus murni permission string).
7. Bagaimana audit log akan mencatat perubahan ini?
```

## 14.2 Prompt Pembuatan Feature Tree & Action Permission
```md
Saya ingin mengimplementasikan model dan migration untuk Feature Tree dan Action.
Patuhi aturan berikut:
1. Gunakan tabel `features` dengan field `parent_id` self-referencing untuk mendukung hierarki bertingkat.
2. Sediakan unique constraint pada `features.code` dan `actions.code`.
3. Sediakan unique constraint kombinasi `(feature_id, action_id)` di tabel `permissions`.
4. Model `Permission` wajib memiliki relasi `belongsTo` ke `Feature` dan `Action`.
5. Kode permission dinamis digenerate otomatis dengan format: {feature_code}.{action_code}.
6. Hindari overengineering, buat se-sederhana mungkin untuk MVP.
```

## 14.3 Prompt Pembuatan Permission Generator
```md
Buatlah service/command generator PHP Artisan `php artisan rbac:generate-permissions`.
Kriteria:
1. Generator harus idempotent (bisa dijalankan berulang tanpa menduplikasi data).
2. Hanya generate kombinasi feature-action yang relevan berdasarkan array konfigurasi, jangan generate semua action untuk semua feature jika tidak masuk akal.
3. Berikan log summary jumlah permission baru yang dibuat atau dilewati (skipped).
```

## 14.4 Prompt Konfigurasi Matrix Permission & User Role Scope
```md
Implementasikan tabel dan model untuk `user_role_scopes` dan `user_role_scope_targets`.
Aturan bisnis:
1. Hubungkan `user_id` dan `role_id` dengan scope type (`all_pop`, `selected_pop`, `pop_tree`, `assigned_only`, `own_created`).
2. Sediakan unique constraint kombinasi `(user_id, role_id)` di tabel `user_role_scopes` agar satu user tidak memiliki role yang sama dengan scope berbeda secara ganda.
3. Target POP untuk scope `selected_pop` / `pop_tree` disimpan secara dinamis di tabel pivot `user_role_scope_targets`.
4. Buat service `EffectiveAccessService` untuk menghitung perizinan efektif dan scope POP milik user saat login.
```

## 14.5 Prompt Form User & Preview Effective Permission
```md
Implementasikan form tambah/edit user pada Blade View.
Kriteria UI:
1. Gunakan layout Sidebar (bukan top nav).
2. Jika dropdown Role / Scope dirubah, gunakan event handler JavaScript untuk menampilkan "Panel Preview Effective Permission" di sisi kanan.
3. Panel preview harus menampilkan daftar izin bersih (izin akhir) user secara instan.
4. Tampilkan warning jika scope target POP kosong saat scope `selected_pop` atau `pop_tree` dipilih.
```

## 14.6 Prompt Middleware & POP Scope Enforcement
```md
Amankan route dan query database pelanggan/keuangan.
Instruksi:
1. Buat middleware `CheckFeaturePermission` yang memverifikasi `$user->userCan('{feature}.{action}')` menggunakan database check.
2. Terapkan Global Scope Eloquent `PopScope` pada model `Customer`, `Invoice`, dan `Payment`.
3. Di dalam `PopScope`, filter query POP secara otomatis berdasarkan tipe scope user login (mencegah kebocoran data antar-cabang).
4. Sembunyikan field password PPPoE/WiFi di UI menggunakan masking default, dan reveal hanya jika memiliki permission `view_sensitive`.
```

## 14.7 Prompt Unit & Integration Test Advanced RBAC
```md
Tulis unit test dan integration test untuk Advanced RBAC.
Skenario Pengujian wajib:
1. Test NOC Pusat dengan scope `all_pop` dapat melihat seluruh pelanggan.
2. Test Admin POP Ponorogo dengan scope `selected_pop` ditolak ketika memodifikasi data pelanggan di POP Siman.
3. Test Teknisi ditolak mengakses menu pembayaran atau laporan keuangan.
4. Test Direct Access URL: Memanggil endpoint POST `/payments` menggunakan user tanpa izin `payments.create` menghasilkan response 403.
```

