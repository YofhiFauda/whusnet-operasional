# Fitur Data Pelanggan

Fitur Data Pelanggan adalah modul utama untuk mengelola calon pelanggan dan pelanggan ISP. Modul ini mencakup daftar pelanggan, registrasi, edit data, detail operasional, dan import batch.

## File Terkait

| Bagian | File |
| --- | --- |
| Controller | `app/Http/Controllers/CustomerController.php` |
| Model | `app/Models/Customer.php` |
| Daftar | `resources/views/customers/index.blade.php` |
| Registrasi | `resources/views/customers/create.blade.php` |
| Edit | `resources/views/customers/edit.blade.php` |
| Detail | `resources/views/customers/show.blade.php` |
| Import | `resources/views/customers/import.blade.php` |
| Route | `routes/web.php` |

## Fungsi Utama

1. Menampilkan daftar pelanggan dengan pencarian dan filter.
2. Menambahkan pelanggan baru melalui form registrasi.
3. Mengubah data pelanggan.
4. Menampilkan detail pelanggan dengan tab operasional.
5. Mengimport banyak pelanggan sekaligus.
6. Menghitung kelengkapan data pelanggan.
7. Menampilkan progress workflow berdasarkan status langganan.

## Data yang Dikelola

| Kelompok | Field |
| --- | --- |
| Identitas | `customer_code`, `full_name`, `identity_number`, `gender`, `phone`, `email` |
| Registrasi | `registration_date`, `status` |
| Alamat | `address`, `city_id`, `district_id`, `village_id`, `latitude`, `longitude` |
| Layanan | `internet_package_id`, `contract_period_months`, `discount_amount`, `tax_percent` |
| Referral | `sales_code`, `agent_code`, `referral_customer_code` |
| Teknis | `ont_sn`, `ip_address`, `odp_code`, `olt_code`, `vlan_id` |
| Dokumen | `foto_ktp`, `foto_rumah`, `foto_kontrak` |

## Relasi Model

| Relasi | Keterangan |
| --- | --- |
| `Customer belongsTo City` | Kota/kabupaten lokasi pelanggan. |
| `Customer belongsTo District` | Kecamatan lokasi pelanggan. |
| `Customer belongsTo Village` | Desa/kelurahan lokasi pelanggan. |
| `Customer belongsTo InternetPackage` | Paket layanan yang dipilih. |
| `Customer belongsTo SubscriptionStatus` | Status workflow berdasarkan `customers.status = subscription_statuses.code`. |

## Status Workflow Pelanggan

Status default disediakan oleh `SubscriptionStatusSeeder`:

| Urutan | Code | Nama | Terminal |
| --- | --- | --- | --- |
| 1 | `registered` | Registered | Tidak |
| 2 | `waiting_survey` | Waiting Survey | Tidak |
| 3 | `surveyed` | Surveyed | Tidak |
| 4 | `waiting_installation` | Waiting Installation | Tidak |
| 5 | `installed` | Installed | Tidak |
| 6 | `active` | Active | Tidak |
| 7 | `suspended` | Suspended | Tidak |
| 8 | `terminated` | Terminated | Ya |
| 9 | `rejected` | Rejected | Ya |

## Tab Detail Pelanggan

Halaman detail menyusun informasi pelanggan dalam beberapa area operasional:

1. Ringkasan dan identitas tampilan.
2. Timeline workflow.
3. Survey.
4. FOP.
5. Pemasangan.
6. Aktivasi.
7. Profil teknis.
8. Uji layanan.
9. Invoice pembayaran awal.
10. Referral.
11. Workflow timelog.
12. Data paket dan biaya.

Catatan: Beberapa informasi detail masih bersifat simulatif dan dihitung dari `registration_date` serta `status`.

