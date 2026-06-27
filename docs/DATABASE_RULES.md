# DATABASE_RULES.md

# Website Billing ISP Berbasis Master Data Pelanggan

## Tujuan Dokumen

Dokumen ini berisi aturan database yang wajib diikuti saat membangun Website Billing ISP.

AI/developer wajib membaca dokumen ini sebelum membuat:

* migration,
* model,
* relationship,
* seeder,
* query,
* validasi database,
* import,
* invoice,
* payment,
* audit log.

Jika ada instruksi coding yang bertentangan dengan dokumen ini, AI wajib berhenti dan meminta konfirmasi.

---

# 1. Prinsip Utama Database

Database sistem ini harus menjadikan pelanggan sebagai pusat relasi.

Urutan relasi utama:

```txt
POP/Cabang
→ Pelanggan
→ Layanan/Paket Aktif
→ Invoice/Tagihan
→ Payment/Pembayaran
→ Laporan
```

Aturan utama:

1. Customer wajib terhubung ke POP.
2. Customer wajib terhubung ke package jika masuk billing.
3. Invoice wajib terhubung ke customer.
4. Invoice wajib terhubung ke POP.
5. Payment wajib terhubung ke invoice.
6. Payment wajib terhubung ke customer.
7. Payment wajib terhubung ke POP.
8. Data hasil input manual dan import wajib masuk struktur tabel yang sama.
9. Data lama boleh memiliki ID pelanggan lama.
10. Sistem baru tetap harus memiliki ID pelanggan baru.
11. Data penting tidak boleh dihapus sembarangan.
12. Perubahan data penting wajib masuk audit log.

---

# 2. Entitas Wajib MVP

Tabel utama MVP:

```txt
users
roles
features
actions
permissions
role_permissions
pops
user_role_scopes
user_role_scope_targets
internet_packages
customers
customer_addresses
customer_services
customer_surveys
customer_installations
customer_devices
customer_documents
invoices
payments
import_batches
import_errors
audit_logs
```

Jika ada kebutuhan tabel baru, AI wajib menjelaskan:

1. Nama tabel.
2. Alasan tabel dibutuhkan.
3. Modul yang menggunakan tabel.
4. Relasi tabel.
5. Apakah tabel masuk MVP atau post-MVP.

---

# 3. Aturan Tabel `users`

## Fungsi

Menyimpan user internal sistem.

## Field Minimal

```txt
id
name
email
password
phone
status
created_at
updated_at
```

## Aturan

1. Email harus unique.
2. Password wajib di-hash.
3. User memiliki role.
4. User dapat memiliki akses ke satu atau banyak POP.
5. User nonaktif tidak boleh login.
6. Perubahan user harus masuk audit log.

## Index/Constraint

```txt
unique(email)
index(status)
```

---

# 4. Aturan Tabel `roles`

## Fungsi

Menyimpan role user.

## Role Wajib

```txt
Owner
Admin Pusat
Admin Cabang
Finance/Kasir
Teknisi
Customer Service
```

## Field Minimal

```txt
id
name
slug
description
created_at
updated_at
```

## Aturan

1. Role wajib memiliki slug unique.
2. Role tidak boleh duplikat.
3. Role Owner tidak boleh dihapus sembarangan.
4. Perubahan role harus masuk audit log.

## Index/Constraint

```txt
unique(slug)
```

---

# 5. Aturan Tabel `features`, `actions`, `permissions`

## Fungsi

Sistem RBAC dinamis memecah kapabilitas menjadi Feature dan Action.

## Field Minimal `features`

```txt
id
parent_id
code
name
sort_order
```

## Field Minimal `actions`

```txt
id
code
```

## Field Minimal `permissions`

```txt
id
feature_id
action_id
code
```

## Aturan

1. `features.code` merepresentasikan modul (misal: `customer`, `invoice`).
2. `actions.code` merepresentasikan aksi (misal: `create`, `update`, `validate`).
3. `permissions.code` digenerate otomatis dengan format `{feature_code}.{action_code}` (misal: `customer.create`).
4. Permission tidak boleh dibuat untuk fitur post-MVP kecuali sudah disetujui.
5. Perubahan permission harus masuk audit log.

## Index/Constraint

```txt
unique(permissions.code)
unique(feature_id, action_id)
foreign(feature_id)
foreign(action_id)
```

---

# 6. Aturan Tabel `role_permissions`

## Fungsi

Pivot role dan permission.

## Field Minimal

```txt
id
role_id
permission_id
created_at
updated_at
```

## Aturan

1. Satu kombinasi role dan permission tidak boleh duplikat.
2. Jika role dihapus, relasi permission ikut ditangani.
3. Perubahan role-permission harus masuk audit log.

## Index/Constraint

```txt
unique(role_id, permission_id)
foreign(role_id)
foreign(permission_id)
```

---

# 7. Aturan Tabel `pops`

## Fungsi

Menyimpan struktur POP/Cabang.

## Field Minimal

```txt
id
pop_code
name
type
parent_id
registration_prefix
cid_prefix
address
village
district
city
latitude
longitude
pic_name
pic_phone
status
created_at
updated_at
```

## Tipe POP

```txt
pusat
cabang
mini_pop
```

## Status POP

```txt
aktif
nonaktif
```

## Aturan

1. Setiap POP wajib memiliki `pop_code`.
2. `pop_code` harus unique.
3. POP dapat memiliki parent.
4. POP dapat memiliki child.
5. POP nonaktif tidak boleh dipilih untuk pelanggan baru.
6. POP yang masih memiliki pelanggan aktif tidak boleh dihapus sembarangan.
7. Gunakan status nonaktif daripada hard delete.
8. Perubahan POP harus masuk audit log.

## Aturan Prefix ID

1. `registration_prefix` digunakan untuk ID Request/Registrasi.
2. `cid_prefix` digunakan untuk CID pelanggan aktif.
3. Prefix dapat berbeda per POP.
4. Jika semua POP memakai prefix yang sama, tetap simpan di tabel agar fleksibel.

## Index/Constraint

```txt
unique(pop_code)
index(type)
index(parent_id)
index(status)
```

---

# 8. Aturan Tabel `user_role_scopes` & `user_role_scope_targets`

## Fungsi

Menentukan hak akses role user dan batasan wilayah (scope) POP-nya secara dinamis.

## Field Minimal `user_role_scopes`

```txt
id
user_id
role_id
scope_type
```

## Field Minimal `user_role_scope_targets`

```txt
user_role_scope_id
pop_id
```

## Tipe Scope (`scope_type`)

```txt
all_pop
selected_pop
pop_tree
assigned_only
own_created
```

## Aturan

1. Setiap user yang memiliki role harus ditentukan tipe scopenya (`scope_type`).
2. Jika `scope_type` adalah `selected_pop`, maka target POP disimpan di `user_role_scope_targets`.
3. Digunakan oleh Global Scope Eloquent (misal: `PopScope`) untuk memfilter data.
4. Perubahan scope harus masuk audit log.

## Index/Constraint

```txt
unique(user_id, role_id)
primary(user_role_scope_id, pop_id)
foreign(user_id)
foreign(role_id)
foreign(pop_id)
```

---

# 9. Aturan Tabel `internet_packages`

## Fungsi

Master paket internet.

## Field Minimal

```txt
id
name
category
download_speed_mbps
upload_speed_mbps
monthly_price
ppn
discount_default
total_price
technical_profile
description
status
created_at
updated_at
```

## Kategori Paket

```txt
home
business
dedicated
promo
```

## Status Paket

```txt
aktif
nonaktif
```

## Aturan

1. Paket aktif dapat dipilih untuk pelanggan baru.
2. Paket nonaktif tidak dapat dipilih untuk pelanggan baru.
3. Paket nonaktif tetap muncul pada histori pelanggan lama.
4. Perubahan harga master paket tidak boleh mengubah invoice lama.
5. Saat paket dipilih pelanggan, data harga harus disimpan sebagai snapshot di `customer_services`.

## Index/Constraint

```txt
index(category)
index(status)
```

---

# 10. Aturan Tabel `customers`

## Fungsi

Master pelanggan utama.

## Field Minimal

```txt
id
registration_number
cid
old_customer_id
full_name
identity_number
gender
primary_phone
alternative_phone
email
registration_date
data_completeness_status
customer_status
pop_id
created_by
updated_by
created_at
updated_at
```

## Status Kelengkapan Data

```txt
draft
perlu_dilengkapi
lengkap
siap_billing
```

## Status Pelanggan

```txt
calon_pelanggan
survey
menunggu_pemasangan
aktif
isolir
nonaktif
berhenti
```

## Aturan

1. `registration_number` adalah ID Request/Registrasi sistem baru.
2. `registration_number` wajib unique.
3. `cid` boleh kosong sebelum pelanggan aktif.
4. `cid` wajib unique jika sudah terisi.
5. `old_customer_id` digunakan untuk data migrasi dari sistem lama.
6. `old_customer_id` boleh kosong untuk pelanggan baru.
7. Customer wajib memiliki `pop_id`.
8. Customer belum lengkap tetap boleh disimpan.
9. Customer belum siap billing tidak boleh dibuatkan invoice.
10. Customer yang sudah aktif harus memiliki CID jika aturan CID digunakan.
11. Perubahan customer harus masuk audit log.

## Index/Constraint

```txt
unique(registration_number)
unique(cid)
index(old_customer_id)
index(pop_id)
index(data_completeness_status)
index(customer_status)
index(primary_phone)
```

Catatan:

Jika database tidak mengizinkan unique nullable dengan aman, buat validasi aplikasi agar CID tidak duplikat saat terisi.

---

# 11. Aturan Tabel `customer_addresses`

## Fungsi

Menyimpan alamat pelanggan.

## Field Minimal

```txt
id
customer_id
full_address
village
district
city
province
latitude
longitude
house_photo
ktp_photo
contract_photo
created_at
updated_at
```

## Aturan

1. Customer memiliki satu data alamat utama.
2. Alamat lengkap, desa, kecamatan, dan kota wajib untuk siap billing.
3. Foto bersifat opsional pada MVP.
4. Perubahan alamat harus memengaruhi status kelengkapan jika field wajib kosong.

## Index/Constraint

```txt
unique(customer_id)
index(village)
index(district)
index(city)
```

---

# 12. Aturan Tabel `customer_services`

## Fungsi

Menyimpan data paket/layanan pelanggan.

## Field Minimal

```txt
id
customer_id
internet_package_id
package_name_snapshot
download_speed_snapshot
upload_speed_snapshot
monthly_price
discount
ppn
total_monthly_bill
activation_date
due_date
billing_cycle
service_status
billing_status
created_at
updated_at
```

## Aturan Snapshot

Saat pelanggan memilih paket, sistem harus menyimpan snapshot:

1. Nama paket.
2. Kecepatan download.
3. Kecepatan upload.
4. Harga bulanan.
5. Diskon.
6. PPN.
7. Total tagihan bulanan.

Tujuannya agar invoice lama tidak berubah jika master paket diedit.

## Aturan

1. Customer dapat memiliki layanan.
2. MVP boleh menggunakan satu layanan aktif per customer.
3. Layanan aktif menjadi dasar invoice.
4. Customer belum memiliki layanan aktif tidak boleh dibuatkan invoice.
5. Perubahan layanan harus masuk audit log.

## Index/Constraint

```txt
index(customer_id)
index(internet_package_id)
index(service_status)
index(billing_status)
```

Jika MVP hanya mendukung satu layanan aktif per customer, validasi di aplikasi:

```txt
one active service per customer
```

---

# 13. Aturan Tabel `customer_surveys`

## Fungsi

Menyimpan data survey pelanggan.

## Field Minimal

```txt
id
customer_id
survey_status
survey_date
start_time
end_time
technician_id
required_tools
cable_estimation_meter
nearest_odp
survey_photo
survey_note
created_at
updated_at
```

## Aturan

1. Survey bersifat opsional untuk MVP awal.
2. Teknisi dapat mengisi survey jika memiliki permission.
3. Data survey tampil di detail pelanggan.
4. Perubahan survey harus masuk audit log jika sudah operasional.

## Index/Constraint

```txt
index(customer_id)
index(survey_status)
index(technician_id)
```

---

# 14. Aturan Tabel `customer_installations`

## Fungsi

Menyimpan data pemasangan pelanggan.

## Field Minimal

```txt
id
customer_id
installation_status
scheduled_date
scheduled_time
technician_id
finished_date
installation_photo
installation_note
created_at
updated_at
```

## Aturan

1. Pemasangan bersifat opsional untuk MVP billing awal.
2. Teknisi dapat mengisi pemasangan jika memiliki permission.
3. Data pemasangan tampil di detail pelanggan.
4. Perubahan pemasangan harus masuk audit log jika sudah operasional.

## Index/Constraint

```txt
index(customer_id)
index(installation_status)
index(technician_id)
```

---

# 15. Aturan Tabel `customer_devices`

## Fungsi

Menyimpan data modem/ONT/router pelanggan.

## Field Minimal

```txt
id
customer_id
device_type
brand
model
serial_number
mac_address
pppoe_username
pppoe_password
wifi_ssid
wifi_password
ip_address
vlan_id
odp
odp_port
signal_rx_power
connection_mode
technical_note
created_at
updated_at
```

## Aturan Field Sensitif

Field sensitif:

```txt
pppoe_password
wifi_password
pppoe_username
ip_address
vlan_id
```

Aturan:

1. Field sensitif hanya boleh dilihat role yang memiliki permission.
2. Finance tidak boleh mengubah data modem.
3. CS tidak boleh melihat password teknis jika tidak diberi permission.
4. Perubahan data perangkat harus masuk audit log.
5. Jika memungkinkan, password teknis dienkripsi di database.

## Index/Constraint

```txt
index(customer_id)
index(serial_number)
index(mac_address)
index(odp)
```

---

# 16. Aturan Tabel `customer_documents`

## Fungsi

Menyimpan dokumen pelanggan.

## Field Minimal

```txt
id
customer_id
document_type
file_path
uploaded_by
created_at
updated_at
```

## Jenis Dokumen

```txt
ktp
rumah
kontrak
survey
pemasangan
lainnya
```

## Aturan

1. Dokumen bersifat opsional pada MVP.
2. Dokumen harus terhubung ke customer.
3. Dokumen tertentu dapat dibatasi berdasarkan permission.
4. File tidak boleh ditimpa tanpa catatan.
5. Perubahan dokumen penting masuk audit log.

## Index/Constraint

```txt
index(customer_id)
index(document_type)
```

---

# 17. Aturan Tabel `invoices`

## Fungsi

Menyimpan tagihan pelanggan.

## Field Minimal

```txt
id
invoice_number
customer_id
pop_id
customer_service_id
internet_package_id
billing_period
issue_date
due_date
subtotal
discount
ppn
total_amount
paid_amount
remaining_amount
invoice_status
created_by
created_at
updated_at
```

## Status Invoice

```txt
belum_dibayar
sebagian
lunas
batal
```

## Aturan

1. Invoice wajib terhubung ke customer.
2. Invoice wajib terhubung ke POP.
3. Invoice wajib terhubung ke layanan pelanggan.
4. Invoice dibuat dari pelanggan aktif/siap billing.
5. Invoice mengambil harga dari layanan pelanggan.
6. Invoice memiliki periode.
7. Invoice tidak boleh dobel untuk customer dan periode sama.
8. Invoice lunas tidak boleh dihapus sembarangan.
9. Invoice batal tidak boleh menerima pembayaran baru.
10. Perubahan invoice harus masuk audit log.

## Index/Constraint

```txt
unique(invoice_number)
unique(customer_id, billing_period)
index(customer_id)
index(pop_id)
index(invoice_status)
index(billing_period)
index(due_date)
```

---

# 18. Aturan Tabel `payments`

## Fungsi

Menyimpan pembayaran invoice.

## Field Minimal

```txt
id
payment_number
invoice_id
customer_id
pop_id
payment_date
payment_method
amount
received_by
proof_file
payment_status
note
created_at
updated_at
```

## Metode Pembayaran

```txt
cash
transfer
qris
lainnya
```

## Status Pembayaran

```txt
pending
valid
ditolak
```

## Aturan

1. Payment wajib terhubung ke invoice.
2. Payment wajib terhubung ke customer.
3. Payment wajib terhubung ke POP.
4. Payment wajib memiliki nominal.
5. Payment valid memengaruhi status invoice.
6. Payment pending tidak boleh membuat invoice menjadi lunas.
7. Payment ditolak tidak boleh memengaruhi invoice.
8. Perubahan payment harus masuk audit log.
9. Payment gateway tidak dibuat di MVP.

## Index/Constraint

```txt
unique(payment_number)
index(invoice_id)
index(customer_id)
index(pop_id)
index(payment_date)
index(payment_method)
index(payment_status)
```

---

# 19. Aturan Tabel `import_batches`

## Fungsi

Menyimpan log batch import.

## Field Minimal

```txt
id
batch_number
file_name
uploaded_by
total_rows
valid_rows
invalid_rows
imported_rows
status
created_at
updated_at
```

## Status Import

```txt
uploaded
previewed
validated
imported
failed
cancelled
```

## Aturan

1. Setiap upload import harus memiliki batch.
2. Data belum masuk master pelanggan sebelum konfirmasi.
3. Import harus menyimpan jumlah valid dan invalid.
4. Import gagal harus memiliki alasan.
5. Import batch tidak boleh dihapus sembarangan.

## Index/Constraint

```txt
unique(batch_number)
index(uploaded_by)
index(status)
index(created_at)
```

---

# 20. Aturan Tabel `import_errors`

## Fungsi

Menyimpan error per baris saat import.

## Field Minimal

```txt
id
import_batch_id
row_number
field_name
error_message
raw_data
created_at
updated_at
```

## Aturan

1. Satu baris dapat memiliki banyak error.
2. Error harus menjelaskan penyebab data gagal.
3. Raw data disimpan agar admin bisa melacak sumber error.
4. Data error tidak boleh masuk master pelanggan.

## Index/Constraint

```txt
index(import_batch_id)
index(row_number)
index(field_name)
```

---

# 21. Aturan Tabel `audit_logs`

## Fungsi

Menyimpan riwayat perubahan data penting.

## Field Minimal

```txt
id
user_id
module
action
auditable_type
auditable_id
old_values
new_values
ip_address
user_agent
created_at
```

## Action

```txt
create
update
delete
import
activation
payment_validation
cancel
```

## Modul Wajib Audit

```txt
customers
pops
internet_packages
invoices
payments
users
roles
permissions
customer_devices
imports
```

## Aturan

1. Audit log tidak boleh diedit oleh user biasa.
2. Audit log tidak boleh dihapus sembarangan.
3. Owner/Admin Pusat dapat melihat audit log.
4. Perubahan data penting wajib tercatat.
5. Audit log harus mencatat user yang melakukan aksi.

## Index/Constraint

```txt
index(user_id)
index(module)
index(action)
index(auditable_type, auditable_id)
index(created_at)
```

---

# 22. Aturan Soft Delete dan Nonaktif

Gunakan aturan berikut:

## Master Data

Untuk data master seperti POP dan paket:

1. Jangan langsung hard delete.
2. Gunakan status `nonaktif`.
3. Data nonaktif tetap muncul di histori lama.
4. Data nonaktif tidak bisa dipilih untuk transaksi baru.

## Data Transaksi

Untuk invoice dan payment:

1. Jangan hard delete invoice lunas.
2. Gunakan status `batal` jika perlu membatalkan invoice.
3. Payment yang salah sebaiknya dikoreksi dengan audit log.
4. Semua perubahan transaksi harus tercatat.

---

# 23. Aturan Unique dan Duplicate

Data yang wajib unique:

```txt
users.email
roles.slug
permissions.slug
pops.pop_code
customers.registration_number
customers.cid
invoices.invoice_number
payments.payment_number
import_batches.batch_number
```

Data yang tidak boleh dobel secara bisnis:

```txt
invoices.customer_id + invoices.billing_period
role_permissions.role_id + role_permissions.permission_id
user_pops.user_id + user_pops.pop_id
```

---

# 24. Aturan Snapshot Data

Snapshot wajib digunakan untuk data yang bisa berubah di master.

## Snapshot Paket pada Customer Service

Simpan:

```txt
package_name_snapshot
download_speed_snapshot
upload_speed_snapshot
monthly_price
discount
ppn
total_monthly_bill
```

## Snapshot Invoice

Invoice harus menyimpan nilai transaksi saat invoice dibuat:

```txt
subtotal
discount
ppn
total_amount
```

Tujuannya:

1. Invoice lama tidak berubah jika harga paket diubah.
2. Laporan keuangan tetap akurat.
3. Histori pelanggan tetap valid.

---

# 25. Aturan Query Scope POP

Setiap query berikut harus memperhatikan scope POP user:

```txt
customers
invoices
payments
dashboard statistics
reports
import batches
customer technical data
```

Aturan:

1. Owner melihat semua.
2. Admin Pusat melihat semua.
3. Admin Cabang hanya melihat POP yang ditugaskan.
4. Finance/Kasir melihat sesuai POP yang ditugaskan.
5. Teknisi melihat sesuai assignment/POP.
6. Customer Service melihat sesuai POP yang ditugaskan.

---

# 26. Larangan Database

AI/developer tidak boleh:

1. Membuat invoice tanpa customer.
2. Membuat payment tanpa invoice.
3. Membuat customer tanpa POP.
4. Membuat invoice untuk customer belum siap billing.
5. Menghapus invoice lunas tanpa audit log.
6. Menghapus payment valid tanpa audit log.
7. Mengubah harga invoice lama ketika harga paket berubah.
8. Mengabaikan POP scope pada query.
9. Menyimpan password user tanpa hash.
10. Menampilkan field sensitif tanpa permission.
11. Membuat tabel post-MVP tanpa persetujuan.
12. Membuat integrasi MikroTik/payment gateway di MVP.

---

# 27. Acceptance Criteria Database

Database dianggap sesuai jika:

* [ ] Semua tabel MVP tersedia.
* [ ] Relasi utama sesuai PRD.
* [ ] Customer terhubung ke POP.
* [ ] Customer service terhubung ke package.
* [ ] Invoice terhubung ke customer dan POP.
* [ ] Payment terhubung ke invoice, customer, dan POP.
* [ ] Import batch dan import error tersedia.
* [ ] Audit log tersedia.
* [ ] Unique constraint penting tersedia.
* [ ] Index penting tersedia.
* [ ] Harga paket tersimpan sebagai snapshot.
* [ ] Query cabang dapat dibatasi berdasarkan POP.
* [ ] Field sensitif dapat dibatasi berdasarkan permission.
