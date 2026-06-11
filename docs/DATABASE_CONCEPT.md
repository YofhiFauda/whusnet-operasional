# Database Concept
# Website Billing ISP Berbasis Master Data Pelanggan

## Prinsip Database
Database harus menjadikan pelanggan sebagai pusat relasi.

Pelanggan terhubung ke:

- POP/Cabang
- Paket/Layanan
- Alamat
- Survey
- Pemasangan
- Perangkat
- Dokumen
- Tagihan
- Pembayaran

Billing tidak boleh dibuat tanpa pelanggan.

---

## Entitas Utama

### 1. users
Menyimpan user internal sistem.

Relasi:
- users memiliki role.
- users dapat memiliki akses ke banyak POP.

Field konseptual:
- id
- name
- email
- password
- phone
- status
- created_at
- updated_at

---

### 2. roles
Menyimpan role user.

Role utama:
- Owner
- Admin Pusat
- Admin Cabang
- Finance/Kasir
- Teknisi
- Customer Service

Field konseptual:
- id
- name
- guard_name
- description
- created_at
- updated_at

---

### 3. permissions
Menyimpan permission sistem.

Field konseptual:
- id
- name
- module
- description
- created_at
- updated_at

---

### 4. role_permissions
Pivot role dan permission.

Field konseptual:
- id
- role_id
- permission_id

---

### 5. pops
Menyimpan struktur POP/Cabang.

Field konseptual:
- id
- code
- name
- type
- parent_id
- address
- village
- district
- city
- latitude
- longitude
- pic_name
- pic_phone
- status
- created_at
- updated_at

Tipe POP:
- pusat
- cabang
- mini_pop

Relasi:
- POP dapat memiliki parent POP.
- POP dapat memiliki banyak child POP.
- POP memiliki banyak pelanggan.

---

### 6. user_pops
Pivot user dan POP.

Field konseptual:
- id
- user_id
- pop_id
- created_at
- updated_at

Fungsi:
- Membatasi user agar hanya melihat data POP tertentu.
- Digunakan terutama untuk Admin Cabang, Finance cabang, CS cabang, dan Teknisi.

---

### 7. internet_packages
Master paket internet.

Field konseptual:
- id
- package_code
- name
- category
- package_group
- bandwidth_label
- download_speed_mbps
- upload_speed_mbps
- contention_ratio
- monthly_price
- ppn
- discount_default
- total_price
- modem
- features
- max_users
- ip_address_type
- contract_period_months
- installation_fee
- installation_fee_label
- profile
- technical_profile
- terms
- description
- is_active
- created_at
- updated_at

Kategori:
- Paket Home Broadband
- Paket Bisnis Broadband
- Paket Bisnis UKM
- Paket Bisnis Dedicated

Catatan:
Menu aplikasi tetap menggunakan nama "Paket Internet", tetapi sumber data teknis adalah `internet_packages` sesuai Rancangan Master Paket WHUSNET.

---

### 8. customers
Master pelanggan.

Field konseptual:
- id
- customer_code
- old_customer_id
- full_name
- identity_number
- gender
- primary_phone
- alternative_phone
- email
- registration_date
- data_completeness_status
- customer_status
- pop_id
- created_by
- updated_by
- created_at
- updated_at

Status kelengkapan:
- draft
- perlu_dilengkapi
- lengkap
- siap_billing

Status pelanggan:
- calon_pelanggan
- survey
- menunggu_pemasangan
- aktif
- isolir
- nonaktif
- berhenti

---

### 9. customer_addresses
Alamat pelanggan.

Field konseptual:
- id
- customer_id
- full_address
- village
- district
- city
- province
- latitude
- longitude
- house_photo
- ktp_photo
- contract_photo
- created_at
- updated_at

Relasi:
- customer memiliki satu address.

---

### 10. customer_services
Data paket/layanan pelanggan.

Field konseptual:
- id
- customer_id
- internet_package_id
- package_name_snapshot
- download_speed_snapshot
- upload_speed_snapshot
- monthly_price
- discount
- ppn
- total_monthly_bill
- activation_date
- due_date
- billing_cycle
- service_status
- billing_status
- created_at
- updated_at

Catatan:
Gunakan snapshot harga dan nama paket agar histori tagihan tidak berubah jika master paket diedit.

---

### 11. customer_surveys
Data survey pelanggan.

Field konseptual:
- id
- customer_id
- survey_status
- survey_date
- start_time
- end_time
- technician_id
- required_tools
- cable_estimation_meter
- nearest_odp
- survey_photo
- survey_note
- created_at
- updated_at

---

### 12. customer_installations
Data pemasangan pelanggan.

Field konseptual:
- id
- customer_id
- installation_status
- scheduled_date
- scheduled_time
- technician_id
- finished_date
- installation_photo
- installation_note
- created_at
- updated_at

---

### 13. customer_devices
Data modem/ONT/router pelanggan.

Field konseptual:
- id
- customer_id
- device_type
- brand
- model
- serial_number
- mac_address
- pppoe_username
- pppoe_password
- wifi_ssid
- wifi_password
- ip_address
- vlan_id
- odp
- odp_port
- signal_rx_power
- connection_mode
- technical_note
- created_at
- updated_at

Catatan keamanan:
Field sensitif seperti password PPPoE dan password WiFi harus dibatasi aksesnya.

---

### 14. customer_documents
Dokumen pelanggan.

Field konseptual:
- id
- customer_id
- document_type
- file_path
- uploaded_by
- created_at
- updated_at

Jenis dokumen:
- ktp
- rumah
- kontrak
- survey
- pemasangan
- lainnya

---

### 15. invoices
Tagihan pelanggan.

Field konseptual:
- id
- invoice_number
- customer_id
- pop_id
- customer_service_id
- internet_package_id
- billing_period
- issue_date
- due_date
- subtotal
- discount
- ppn
- total_amount
- paid_amount
- remaining_amount
- invoice_status
- created_by
- created_at
- updated_at

Status invoice:
- belum_dibayar
- sebagian
- lunas
- batal

Aturan:
- Invoice harus terhubung ke customer.
- Invoice harus mengikuti POP customer.
- Invoice harus memiliki periode.
- Invoice tidak boleh dobel untuk customer dan periode yang sama.

---

### 16. payments
Pembayaran tagihan.

Field konseptual:
- id
- payment_number
- invoice_id
- customer_id
- pop_id
- payment_date
- payment_method
- amount
- received_by
- proof_file
- payment_status
- note
- created_at
- updated_at

Metode pembayaran:
- cash
- transfer
- qris
- lainnya

Status pembayaran:
- pending
- valid
- ditolak

---

### 17. import_batches
Log batch import.

Field konseptual:
- id
- batch_number
- file_name
- uploaded_by
- total_rows
- valid_rows
- invalid_rows
- imported_rows
- status
- created_at
- updated_at

Status:
- pending
- previewed
- imported
- failed

---

### 18. import_errors
Data error import.

Field konseptual:
- id
- import_batch_id
- row_number
- field_name
- error_message
- raw_data
- created_at
- updated_at

---

### 19. audit_logs
Riwayat perubahan data penting.

Field konseptual:
- id
- user_id
- module
- action
- auditable_type
- auditable_id
- old_values
- new_values
- ip_address
- user_agent
- created_at

Action:
- create
- update
- delete
- import
- payment_validation

---

## Relasi Utama
Relasi konseptual:

- User memiliki Role.
- Role memiliki banyak Permission.
- User dapat memiliki akses ke banyak POP.
- POP dapat memiliki parent POP.
- POP memiliki banyak Customer.
- Customer memiliki Address.
- Customer memiliki Service.
- Customer memiliki Survey.
- Customer memiliki Installation.
- Customer memiliki Device.
- Customer memiliki Document.
- Customer memiliki banyak Invoice.
- Customer memiliki banyak Payment.
- Internet Package digunakan oleh Customer Service.
- Invoice dimiliki Customer.
- Invoice memiliki banyak Payment.
- Import Batch memiliki banyak Import Error.
- Audit Log mencatat perubahan data penting.

## Aturan Database Penting
1. Jangan hapus data penting secara hard delete jika masih dibutuhkan histori.
2. Gunakan status aktif/nonaktif untuk master data.
3. Gunakan snapshot harga pada customer service dan invoice.
4. Jangan membuat invoice tanpa customer.
5. Jangan membuat payment tanpa invoice.
6. Jangan membuat pelanggan siap billing jika field wajib belum lengkap.
7. Gunakan audit log untuk perubahan penting.
