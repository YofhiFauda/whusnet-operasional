# Business Logic — Customer Verifikasi & Onboarding Lifecycle

## 1. State Machine (`WorkflowTransition` enum)

14 status, tiap status punya daftar tujuan valid (`allowedNextTransitions()`). `CustomerWorkflowService::transition()` **menolak transisi ilegal** — lempar exception kalau target status gak ada di daftar allowed dari status sekarang.

| Status | Transisi Valid Berikutnya |
|--------|---------------------------|
| `registered` | `waiting_survey`, `waiting_acc` (2026-08-21 — khusus Skip Survey, lihat §3.1), `rejected` |
| `waiting_survey` | `survey_in_progress`, `rejected` |
| `survey_in_progress` | `waiting_acc`, `surveyed`, `rejected` |
| `surveyed` | `waiting_acc`, `waiting_installation`, `rejected` |
| `waiting_acc` | `waiting_installation`, `survey_in_progress`, `rejected` |
| `waiting_installation` | `installation_in_progress`, `rejected` |
| `installation_in_progress` | `verification_admin`, `installed`, `waiting_installation`, `rejected` |
| `installed` | `verification_admin`, `waiting_installation`, `rejected` |
| `verification_admin` | `active`, `waiting_installation`, `revision_installation`, `installation_in_progress`, `rejected` |
| `revision_installation` | `verification_admin`, `installed`, `waiting_installation`, `rejected` |
| `active` | `installed`, `verification_admin`, `revision_installation`, `suspended`, `terminated` |
| `suspended` | `active`, `terminated` |
| `terminated` | *(final, tidak ada transisi keluar)* |
| `rejected` | *(final, tidak ada transisi keluar)* |

**Catatan penting:** registrasi (`CustomerController::store`) langsung set `status = 'waiting_survey'` (normal) atau `status = 'waiting_acc'` (Skip Survey, §3.1), **melewati** `registered` — status `registered` di enum lebih sebagai state teoretis/starting point default (`$customer->status ?? 'registered'` di `CustomerWorkflowService`), bukan state yang benar-benar disinggahi di alur manapun saat ini. Edge `registered → waiting_acc` tetap didaftarkan di `allowedNextTransitions()` biar state machine-nya sah kalau suatu saat ada kode lain yang transisi eksplisit lewat `CustomerWorkflowService`, walau jalur Skip Survey sendiri nge-set status langsung di `Customer::create()` (customer belum ada baris buat ditransisikan lewat service).

## 2. Efek Samping Otomatis Tiap Transisi

`CustomerWorkflowService::transition()` gak cuma update `status` — ada 3 efek samping yang jalan otomatis dalam 1 DB transaction:

1. **Audit** — `AuditLog` (module `Customer Workflow`) + `CustomerStatusLog` (immutable, `from_status`/`to_status`/`changed_by`/`note`) — dua-duanya jalan tiap transisi, gak bisa di-skip.
2. **Auto-create Task** — kalau target status `waiting_survey` atau `waiting_installation`, dan belum ada `Task` aktif (status `pending`/`terjadwal`/`in_progress`) dengan tipe yang sama untuk customer itu → bikin `Task` baru status `pending`. Ini yang mengisi antrean kerja teknisi/FOP — **jangan bikin Task manual duplikat** untuk 2 status ini, sistem udah handle.
3. **Notifikasi aktivasi** — target status `active` → dispatch job `SendCustomerActivationNotification` (async, gak blocking response).

## 3. Tahap 1 — Registrasi

- Entry point: `CustomerController::store()`, permission `customers.create`.
- Status langsung `waiting_survey` (lihat catatan di atas) — bukan lewat `registered` dulu.
- `customer_status` (field terpisah, label operasional Bahasa Indonesia) di-mapping dari `status` lewat tabel statis di controller (e.g. `waiting_survey` → `survey`).
- Nomor pelanggan (`customer_code`) di-generate dari sequence per-POP (`Pop::generateRegistrationNumber()`).
- Foto KTP/rumah/kontrak diupload terpisah dari field lain, disimpan di `customers.foto_*` (redundant dengan `customer_addresses.house_photo`/`ktp_photo`/`contract_photo` — dua sumber sama, legacy duplikasi kolom).

### 3.1 Skip Survey (2026-08-21)

Role dengan permission `customers.registration.skip_survey` (default **Sales**, lihat `docs/rbac/business-logic.md` §9) bisa melewati Tahap 2 (Survey) sepenuhnya — input data survey langsung di form Registrasi, dipakai buat pelanggan yang sudah jelas titik & kondisi lokasinya tanpa perlu kunjungan teknisi terpisah.

**Gerbang otorisasi dua lapis** (bukan cuma UI hide): `@can` sembunyikan checkbox di blade, dan `CustomerRegistrationRequest::authorize()` nolak 403 kalau `skip_survey=1` dikirim tanpa permission — supaya klien yang maksa gak lolos diam-diam dengan validasi field survey yang membingungkan.

Begitu checkbox **"Skip Survey — Input Data Survey Langsung"** dicentang, field berikut jadi **wajib** (sama persis field wajib di Lapor Survey teknisi, `survey_status=completed`):
- Latitude/Longitude (`customer_addresses`) — semula opsional di registrasi biasa.
- ODP Terdekat, Estimasi Kabel (Meter), Tingkat Kesulitan.
- Foto Rumah, Foto ODP — diupload lewat `FileUploadService::uploadSurveyPhoto()`, folder & disk sama persis jalur teknisi (`surveys/rumah` / `surveys/odp`, disk `public`).

**Efek di `CustomerController::store()`:**
1. `status` di-set `waiting_acc` langsung (bukan `waiting_survey`) — lompat `waiting_survey`/`survey_in_progress`/`surveyed` sepenuhnya.
2. `CustomerSurvey` dibuat otomatis: `survey_status=completed`, `technician_id`=user Sales yang input (kolom ini generik "siapa yang mengisi", bukan eksklusif role teknisi), `survey_note` diberi tag `"Diinput oleh Sales saat Registrasi (Skip Survey)"` (plus `"Tingkat Kesulitan: …"` kalau diisi) — pola sama dengan `CustomerSurveyController::store()`. `started_at`/`completed_at`/`duration_minutes` dibiarkan kosong — gak ada kunjungan lapangan beneran yang perlu dicatat durasinya.
3. **Task/FopTask SURVEY TIDAK dibuat sama sekali** — beda dari alur normal (§2 poin 2, auto-create Task) yang selalu bikin `Task`+`FopTask` kategori SURVEY. Gak ada teknisi yang perlu ditugaskan survei, jadi gak ada antrean yang perlu dibuat, dan gak ada anchor `task_materials`/`task_work_tools` (estimasi material dari Skip Survey memang di luar scope — beda dari Lapor Survey teknisi yang punya form estimasi alat).
4. `customer_services.service_status` di-mapping `waiting_acc → 'survey'` (sama perlakuan dengan `surveyed`/`waiting_survey`).

**Alur setelahnya sama persis pelanggan survey normal** — begitu di `waiting_acc`, masuk antrean ACC Admin (Tahap 3 di bawah), lanjut `waiting_installation` → provisioning `FopTask` PEMASANGAN via `FopTaskProvisioningService`, dst. Tidak ada percabangan khusus Skip Survey di tahap-tahap berikutnya.

## 4. Tahap 2 — Survey (`CustomerSurveyController`)

### Mulai Survey (`start()`)

- Guard status: kalau `registered` → auto-transition ke `waiting_survey` dulu (defensive, jarang kepakai karena registrasi udah langsung `waiting_survey`), lalu **wajib** `waiting_survey` sebelum lanjut.
- **Guard konflik jadwal teknisi:** kalau teknisi (atau anggota tim Task Survey) sedang punya Task lain berstatus `in_progress`, mulai survey **ditolak** — teknisi harus selesaikan/pending-kan task berjalan itu dulu. Berlaku juga di tahap Pemasangan (aturan sama, dicek 2x di controller berbeda).
- Set `CustomerSurvey.started_at`, transition customer ke `survey_in_progress`, mulai `Task` terkait (`TaskService::start()`).
- Broadcast event `SurveyStarted`.

### Lapor Survey (`store()`)

- Guard status: cuma bisa submit kalau status masih `survey_in_progress`.
- Validasi: `survey_status` (`pending`/`completed`/`failed`). `cable_estimation_meter`, `nearest_odp`, `survey_photo`+`house_photo`, `difficulty_level` (`MUDAH`/`SEDANG`/`SULIT`) **wajib hanya kalau `survey_status=completed`** (`required_if`) — kalau `failed`, field teknis ini opsional karena situs mungkin memang tidak bisa disurvei penuh. `difficulty_level` (kalau ada) digabung jadi teks di `survey_note`.
- **Multi-surveyor:** kalau Task punya >1 anggota tim, sistem catat siapa "Petugas Survey 1/2/3" berdasar urutan anggota — `surveyor_2_id`/`surveyor_3_id` diisi otomatis dari anggota lain (maks 3 surveyor tercatat by design kolom).
- Kalau `survey_status = completed` **dan** status customer masih `survey_in_progress` → complete Task Survey, transition ke `waiting_acc`, broadcast `SurveyCompleted`, kirim notifikasi Telegram.
- **Kalau `survey_status = failed` (✅ ditambahkan 2026-07-08 — sebelumnya gap, lihat [bug.md](bug.md)):** `survey_note` jadi **wajib** (alasan tidak layak pasang, ditombolkan lewat tombol "Tidak Layak Pasang" terpisah di UI — bukan dropdown, biar teknisi gak salah pencet). Task Survey terkait di-**cancel** (`TaskService::cancel()`, status `DIBATALKAN` + `cancel_reason`), customer di-**transition ke `rejected`** (state final, sama mekanisme dengan reject di tahap verifikasi — lihat §7). Tiket Pemasangan otomatis **tidak akan pernah terbentuk** karena workflow tidak pernah sampai `waiting_acc`.
- Kalau `survey_status = pending` (laporan draf/belum final) → data tersimpan, status customer/task tidak berubah, technician bisa submit ulang nanti.
- **Navigasi "Kembali" & redirect sukses ikut halaman asal (`return_to`, 2026-08-06):** halaman `/customers/{id}/survey/report` diakses dari beberapa entry point (Detail Task teknisi, Dashboard Task Saya, Antrean Survey, Verifikasi Queue, Detail Pelanggan) — sebelumnya tombol "Kembali" dan redirect setelah submit sukses **hardcoded** ke `surveys.queue`/`verifications.queue`, jadi teknisi yang masuk dari Detail Task malah dilempar ke halaman admin yang gak relevan. Sekarang tiap pemanggil kirim `return_to` (query saat GET, hidden field saat POST), divalidasi `App\Support\SafeUrl::resolveReturnTo()` (cuma terima same-origin URL, cegah open redirect), fallback ke `surveys.queue` kalau kosong/invalid — `report()` & `store()` di `CustomerSurveyController`.

#### Tanggal Request Pemasangan (`requested_installation_date`, 2026-07-31)

Field **opsional** di Step 4 form Lapor Survey. Diisi hanya kalau pelanggan minta dipasang di tanggal tertentu; kosong = "secepatnya".

- Validasi `nullable|date|after_or_equal:today` — tanggal lampau ditolak, karena kalau lolos task-nya lahir langsung dalam kondisi TERLAMBAT di papan FOP.
- **`customer_surveys.requested_installation_date` adalah satu-satunya sumber kebenaran.** `fop_tasks.client_request_date` untuk kategori PSB cuma nilai turunan yang di-refresh tiap auto-sync papan FOP — lihat [docs/fop-task/business-logic.md](../fop-task/README.md) & `FopTaskController::autoSyncAndCalculatePriority()`.
- Efeknya di papan FOP: selama tanggalnya belum tiba, task tenggelam ke dasar papan, prioritas dipaksa `LOW`, dan **tidak dihitung SLA-nya**. Begitu tanggalnya tiba, deadline = akhir hari tanggal itu dan countdown berjalan; lewat tengah malam → timer negatif (`−18:25:02`).

#### Estimasi Kebutuhan Alat (`task_materials`, kind `estimasi`, 2026-07-31)

Daftar material terstruktur (baris berulang: barang, tipe, jumlah, satuan, catatan) menggantikan peran `required_tools` sebagai pencatat material.

- `required_tools` **tidak dihapus** (ada data survey lama) — turun peran jadi "Alat Khusus / Kendala Peralatan": peralatan kerja non-habis-pakai (tangga, bor). Material habis pakai masuk `task_materials`.
- `cable_estimation_meter` tetap dipakai dan **otomatis diturunkan** jadi satu baris `kabel_dropcore` (qty = nilai itu, unit meter) — teknisi tidak diminta mengisi angka yang sama dua kali. Kalau teknisi sudah menambah baris dropcore manual, baris otomatis tidak dibuat (cegah dobel).
- Baris menempel di **FopTask kategori SURVEY** milik pelanggan. Kalau anchor-nya belum ada, **dibuat saat itu juga** lewat `FopTaskProvisioningService::ensureForCustomer()` (2026-08-11) — sebelumnya baris material dilewat diam-diam, dan itu ternyata membuang seluruh isian teknisi tanpa satu pun pesan error (lihat ADHOC-28 di `docs/TASKS.md`).
- Barang dipilih dari **Master Barang** (`items`). Barang di luar master dicatat lewat pilihan "Lainnya" (`item_id` null) supaya teknisi tidak terhambat di lapangan.

### Batalkan Survey — sebelum/selagi dikerjakan (`cancel()`)

**Beda dari §"Lapor Survey" `survey_status=failed` di atas** — itu jalur "teknisi UDAH di lokasi, submit laporan gagal survey". Method `cancel()` ini buat kasus SEBELUM laporan ada sama sekali: **belum ditugaskan** (`waiting_survey`, belum ada teknisi/Task jalan) ATAU **udah ditugaskan tapi gak jadi/dibatalkan** (`waiting_survey` juga — status customer TETAP `waiting_survey` walau Task Survey-nya udah `terjadwal`/teknisi udah di-assign, sampai teknisi beneran pencet "Mulai Survey") ATAU **lagi dikerjakan tapi dibatalkan di tengah jalan** (`survey_in_progress`, belum submit laporan).

- Guard: `customer->status` harus `waiting_survey` atau `survey_in_progress`, permission `customers.detail.survey.reject`.
- 1 transaksi: cari Task Survey terkait yang belum `selesai`/`dibatalkan` → `TaskService::cancel()` (status `dibatalkan`, otomatis sync ke `FopTask` via `TaskObserver`) → `CustomerSurvey.survey_status = failed` (record survey terbaru, dibuat baru kalau belum ada) → `CustomerWorkflowService::transition(customer, REJECTED, $reason)`.
- **Tombol ada di 2 tempat** (2026-07-21): tab Survey halaman Customer detail (`resources/views/customers/tabs/_survey.blade.php`, tombol "Batalkan Survey") DAN tabel Antrean Survey Lapangan (`/surveys/queue`, tombol "Batalkan" per baris — **baru ditambah**, sebelumnya cuma ada "Mulai Survey"/"Lapor Data", gak ada opsi batalkan sama sekali dari halaman ini).
- **Ini SATU-SATUNYA jalur sah buat batalin Task Survey** — `TaskPolicy::cancel()` block cancel langsung dari halaman Task buat task_type SURVEY (berlaku semua role termasuk owner), dan `FopTaskController::update()` nolak (422) kalau coba cancel tiket FOP kategori Survey. Alasannya: cancel dari Task/FopTask doang gak nyentuh `Customer.status` — pelanggan bakal nyangkut permanen di status lama, gak pernah masuk List Pelanggan Gagal. Lihat `docs/fop-task/flowchart.md` § 12.

## 5. Tahap 3 — Verifikasi Survey → Proses ke Tim (`CustomerVerificationController::processToTeam`)

- Guard status: cuma dari `waiting_acc` atau `surveyed`.
- Bikin/pakai record `CustomerInstallation` (status `scheduled`) kalau belum ada yang `scheduled`/`in_progress`.
- Transition ke `waiting_installation` (efek samping: auto-create Task Pemasangan).
- **Auto-approve** Task Survey terkait yang statusnya `fop_review_status = pending` → langsung `approved` (FOP yang klik "Proses ke TIM" dianggap sekaligus meng-approve laporan survey teknisi — 1 aksi, 2 efek).
- **Notifikasi in-app (2026-08-06/07):** tim survey (teknisi yang laporannya baru disetujui) dapet notif SUCCESS. **Terpisah**, role `fop` di POP pelanggan ini JUGA dapet notif INFO soal Task Pemasangan baru yang perlu di-assign tim — gap yang sempat kelewat (admin verifikasi yang aksi di sini, tapi FOP-lah yang bakal assign tim ke Task Pemasangan baru itu, bukan admin). Notif FOP di-skip kalau Task Pemasangan yang dipakai BUKAN baru (udah ada `teamMembers`, mis. reuse dari jalur revisi) — biar gak double-notif tiap `processToTeam()` kepanggil ulang. Detail: `docs/plan/analisa-status-implementasi-notifikasi.md` §8.2.

## 6. Tahap 4 — Pemasangan (`CustomerInstallationController`)

### Mulai Pemasangan (`start()`)

- Guard status: harus `waiting_installation`.
- Guard konflik jadwal teknisi — sama persis logic-nya dengan Survey (lihat §4).
- Transition ke `installation_in_progress`, broadcast `InstallationStarted`.

### Lapor Pemasangan (`store()`)

- Guard status: `installation_in_progress` atau `revision_installation` (revisi bisa submit ulang laporan tanpa perlu "start" ulang).
- **Validasi kondisional ketat saat `installation_status = completed`:** foto pemasangan, foto kontrak, foto TTD pelanggan, DAN foto speedtest **wajib ada** (baik dari upload baru atau yang udah tersimpan sebelumnya) — kalau salah satu kosong, submit ditolak dengan pesan spesifik per field.
- Data teknis disimpan **dobel** ke 2 tabel: `CustomerTechnicalDetail` (sumber utama, field lengkap: ODP/OLT/VLAN/speedtest/attenuation) dan `CustomerDevice` (legacy, subset field device/PPPoE/WiFi) — keduanya di-`updateOrCreate` dalam transaksi yang sama.
- **Speed conformity** dihitung otomatis: `(test_download / paket.download_speed_mbps) * 100` — dipakai buat verifikasi hasil instalasi sesuai spek paket yang dijual.
- **Perangkat Pasif Terpakai wajib minimal 1 baris saat `completed`** (2026-07-31) — lihat sub-bagian di bawah.
- Efek per `installation_status`:
  - `completed` → complete Task Pemasangan, transition 2x berturutan: `installed` lalu langsung `verification_admin` (skip berhenti di `installed`), broadcast `InstallationCompleted`, notifikasi Telegram.
  - `failed` → transition balik ke `waiting_installation` ("Instalasi gagal/butuh revisi. Menunggu penjadwalan ulang").
  - lainnya (progress belum selesai) → data tersimpan, status customer/task tidak berubah.
- **Navigasi "Kembali" & redirect sukses ikut halaman asal (`return_to`, 2026-08-06)** — sama mekanismenya dengan Lapor Survey (lihat §4): `return_to` divalidasi `App\Support\SafeUrl::resolveReturnTo()`, fallback ke `verifications.queue`. Fix ini juga membetulkan link "Lapor Pemasangan"/"Revisi" survey dari Verifikasi Queue yang sebelumnya salah fallback ke Antrean Survey kalau customer masih tahap survey.

#### Perangkat Pasif Terpakai (`task_materials`, kind `terpakai`, 2026-07-31)

**Beda tegas dari Estimasi Kebutuhan Alat di §4.** Estimasi = perkiraan surveyor. Perangkat Pasif = material yang **benar-benar dipakai** saat pemasangan. Selisih keduanya adalah nilai bisnis utamanya, dan nanti jadi input langsung modul Inventory.

- Form Step 5 diisi awal (**prefill**) dari baris `estimasi` milik pelanggan; kalau laporan pernah disimpan/direvisi, baris `terpakai` yang tersimpan yang menang. Teknisi mengubah jumlah ke realita, boleh tambah/hapus baris. Tanpa prefill, seksi ini cenderung dikosongkan dan perbandingannya jadi tak berguna.
- **Wajib minimal satu baris valid kalau `installation_status = completed`** (qty > 0 dan barang terisi). Kalau `failed`/revisi, tidak wajib — mengikuti pola field lain di form ini.
- Baris menempel di **FopTask kategori PEMASANGAN**; sama seperti estimasi, anchor dibuat lewat `FopTaskProvisioningService::ensureForCustomer()` kalau belum ada (2026-08-11).
- **Tidak menggantikan `customer_technical_details.passive_device*`.** Dua-duanya tetap ada dan tidak digabung:
  | | `task_materials` (kind `terpakai`) | `customer_technical_details.passive_device*` |
  |---|---|---|
  | Makna | konsumsi material saat pekerjaan (biaya) | aset terpasang permanen di sisi pelanggan |
  | Bentuk | banyak baris + qty + satuan | 4 kolom flat, 1 baris |
  | Diisi | teknisi, saat Laporan Pemasangan | admin, lewat tab Perangkat |
- Perbandingan **Estimasi vs Terpakai + selisih** tampil di halaman Verifikasi Admin (`verifications/admin.blade.php`). Di situlah admin menilai apakah estimasi survey meleset atau pemakaian tidak wajar — sengaja tanpa ambang otomatis, itu keputusan manusia.

### Batalkan Pemasangan — sebelum/selagi dikerjakan (`cancel()`, **baru 2026-07-21**)

Setara `CustomerSurveyController::cancel()` di §4, tapi buat tahap Pemasangan — sebelumnya **GAK ADA jalur ini sama sekali** buat PSB (gap: satu-satunya cara batalin Task Pemasangan yang lagi jalan ya lewat tombol Cancel langsung di halaman Task, yang BYPASS `Customer.status` sepenuhnya).

- Guard: `customer->status` harus `waiting_installation`, `installation_in_progress`, atau `revision_installation`, permission **`customers.detail.installation.reject`** (baru ditambah ke `config/rbac.php` + role `noc`/`fop` di `RolePermissionSeeder`, sebelumnya cuma ada `.view/.update/.validate/.activate` — gak ada `.reject`).
- 1 transaksi: cari Task Pemasangan terkait yang belum `selesai`/`dibatalkan` → `TaskService::cancel()` → `CustomerInstallation.installation_status = failed` (+ append alasan ke `notes`) → `CustomerWorkflowService::transition(customer, REJECTED, $reason)`.
- Tombol "Batalkan Pemasangan" di tab Pemasangan halaman Customer detail (`resources/views/customers/tabs/_installation.blade.php`).
- Sama kayak Survey: ini satu-satunya jalur sah, `TaskPolicy::cancel()` + `FopTaskController::update()` block cancel langsung buat task_type PEMASANGAN.

## 7. Tahap 5 — Verifikasi Admin & Aktivasi (`CustomerVerificationController`)

### Halaman baca (`showAdmin`, `/verifications/{customer}/admin`)

Halaman ini **harus memperlihatkan apa yang benar-benar diinput teknisi** — itu satu-satunya dasar admin memutuskan aktivasi. Yang ditampilkan per tab (2026-08-11, ADHOC-28):

| Tab | Blok | Sumber |
|---|---|---|
| Survey | Estimasi Material Hasil Survey | `task_materials` kind `estimasi` (`TaskMaterialService::estimatesForCustomer()`) |
| Survey | Alat Kerja Dicatat Surveyor | `task_work_tools` milik FopTask kategori SURVEY |
| Survey | Catatan Kendala Peralatan | `customer_surveys.required_tools` — teks bebas, disembunyikan kalau kosong |
| Survey | FOP Penanggung Jawab | `customer_surveys.fop_id` → relasi `CustomerSurvey::fop()` |
| Pemasangan | Material Terpakai Saat Pemasangan | `task_materials` kind `terpakai` milik FopTask kategori PSB |
| Pemasangan | Alat Kerja Dipakai Tim Pemasangan | `task_work_tools` milik FopTask kategori PSB |
| Pemasangan | Estimasi vs Terpakai + selisih | `TaskMaterialService::varianceForCustomer()` |

Dua catatan yang gampang salah dibaca:

- **Daftar baris dan tabel variance dua-duanya perlu ada.** Variance mengagregasi per barang dan membuang catatan per baris, jadi ia tidak bisa menggantikan daftar yang benar-benar diisi teknisi.
- Blok material & alat **tetap dirender walau kosong**, dengan keterangan "tidak mencatat". Seksi yang hilang saat kosong bikin admin tidak bisa membedakan "teknisi tidak mengisi" dari "halaman memang tidak punya seksi itu" — persis kebingungan yang memicu ADHOC-28.

Sebelumnya `customer_surveys.fop_id` tampil mentah dengan label "Kebutuhan FOP / Tiang". Kolomnya menunjuk ke `users` (FOP yang menugaskan), jadi label dan isinya tidak pernah nyambung.

### Approve → Aktivasi (`finalVerify`)

- Guard status implisit: view `showAdmin()` cuma terbuka untuk status `installed`/`verification_admin`.
- **Bikin Invoice AWAL** — subtotal/discount/ppn/prorate/extra fee **diinput manual oleh Admin** di form ini (bukan otomatis dari `customer_service.monthly_price` — beda dari invoice bulanan rutin, lihat [docs/billing-pembayaran](../billing-pembayaran/README.md)).
- **Generate CID** (`Pop::generateComplexCid()`) — baru di-generate di titik ini, bukan saat registrasi. Sebelum aktif, pelanggan gak punya CID.
- Set `customer.status=active`, `customer_status=aktif`, `data_completeness_status=siap_billing`; `customer_service.service_status=aktif`, `billing_status=active`, catat siapa & kapan aktivasi (`activated_by_name`, `activated_by_user_id`, `activation_time`).
- **Auto-approve** Task Pemasangan terkait yang `fop_review_status=pending`.
- Ini **satu-satunya jalur normal** yang mengubah status ke `active` — gak ada jalur otomatis lain.
- **Notifikasi in-app (2026-08-06/07):** tim pemasangan dapet notif SUCCESS ("pelanggan resmi aktif"). **Terpisah**, pendaftar asli pelanggan (`customers.created_by` — Sales/CS yang mendaftarkan, relasi `Customer::creator()`) juga dapet notif SUCCESS Customer Lifecycle, di-skip kalau yang eksekusi `finalVerify()` = pendaftar itu sendiri. Detail: `docs/plan/analisa-status-implementasi-notifikasi.md` §8.2 (tim) & §8.5 (pendaftar).

### Reject (`reject`)

- Transition ke `rejected` — dipakai kalau pelanggan dibatalkan total di titik verifikasi manapun. Pelanggan yang ditolak otomatis masuk list **Pelanggan Gagal** (`CustomerController` statusGroup `failed`, `whereIn('status', ['failed','rejected','gagal'])`) — no-op, gak ada kode tambahan buat ini.
- **Stage-aware sejak fix reject-sync gap (2026-07-14):** tahap ditentukan dari `$customer->status` SEBELUM `transition()` dipanggil (transition-nya sendiri ngubah status jadi `rejected`, jadi harus direkam duluan) — `installation_in_progress|revision_installation|installed|verification_admin` = tahap install → target **Task Pemasangan**; selain itu (`waiting_acc|survey_in_progress|surveyed|waiting_installation`) = tahap survey → target **Task Survey** (behavior lama). SEBELUM fix ini, `reject()` SELALU nyentuh Task Survey — kalau ditolak di tahap install, Task Pemasangan gak pernah ke-update, nyangkut permanen di antrian FOP aktif. Detail: `docs/project_verifikasi_reject_gap.md`.
- **Notifikasi in-app (2026-08-06):** tim task (Survey/Pemasangan sesuai tahap) yang laporannya ditolak dapet notif ERROR. Detail: `docs/plan/analisa-status-implementasi-notifikasi.md` §8.2.
- Auto-reject Task (Survey atau Pemasangan, sesuai tahap) yang masih `fop_review_status=pending` → `fop_review_status=rejected` + `reject_reason`. `Task.status` **TETAP `selesai`** (beda dari Revisi di bawah yang revert ke `in_progress`) — downstream `TaskObserver` TETAP sync `FopTask` ke `Selesai` (bukan `Cancel` — kerjaan lapangan teknisi sukses, yang ditolak keputusan bisnis customer-nya, 2 hal beda sengaja gak dicampur), cuma label histori granularnya jadi `selesai_ditolak_verifikasi`. Di Riwayat FOP, badge "Verifikasi: Ditolak" muncul sebagai kolom/badge KEDUA (overlay dari `FopTask::verificationStatus()`), terpisah dari badge status utama "Selesai" (lihat [docs/fop-task/flowchart.md § 9](../fop-task/flowchart.md#9-status-realtime--sync-task-eksekusi--foptask-task-9)).
- UI: tombol "Tolak" tersedia di halaman queue (tahap survey, sudah lama ada) DAN di halaman Verif & Pemasangan `verifications/admin.blade.php` (tahap install). Modal Tolak eksplisit warning "final, gak bisa dibuka lagi, harus registrasi ulang dari awal" — **catatan: warning ini sekarang gak 100% akurat**, lihat "Kembalikan" di bawah (ditambahkan belakangan sebagai jalur darurat, `allowedNextTransitions()` enum `REJECTED` sendiri tetap kosong/terminal, gak diubah).
- **Tombol Delete pelanggan dihapus dari `/verifications/queue` (2026-07-20)**: sebelumnya tiap baris antrean punya icon Delete (`customers.destroy`, hard-delete permanen, permission `customers.delete`) tanpa peduli status — berbahaya karena SRV/PSB pada dasarnya gak boleh dihapus (bisa juga crash FK karena `tasks.customer_id` `onDelete('restrict')` kalau customer punya Task terkait). Diganti icon "Batal / Gagal" yang manggil modal reject yang sama (`openRejectModal()`, POST ke `customers.verification.reject`) — sekarang berlaku SERAGAM di semua status queue (`waiting_acc`, `surveyed`, `waiting_installation`, `installation_in_progress`, `revision_installation`, `installed`, `verification_admin`), bukan cuma `surveyed` seperti sebelumnya. Gate permission `customers.detail.installation.validate` (sama kayak permission route reject).

### Kembalikan dari Pelanggan Gagal (`CustomerController::restoreFromFailed`, 2026-07-20)

- List **Pelanggan Gagal** (`/customers/failed`) dirapikan jadi tabel ringkas: CID, Nama, Alasan, Tanggal Ditolak, Action (Detail + **Kembalikan**). Alasan & tanggal dibaca dari `AuditLog` transisi terakhir ke `rejected` (module `Customer Workflow`, action `status_transition`) — bukan kolom dedicated, karena `Customer`/`CustomerStatusLog` gak punya kolom alasan reject sendiri.
- **Urut DESC berdasarkan tanggal ditolak (2026-07-20)**: `CustomerController::index()` orderBy subquery `AuditLog::selectRaw('MAX(created_at)')` (scoped ke module+action+`new_values->status=rejected` per customer) — bukan `customer_code` seperti grup status lain. Perlu subquery karena "tanggal ditolak" bukan kolom asli di `customers`, dihitung on-the-fly dari `AuditLog`; sorting harus terjadi SEBELUM `paginate()`, gak bisa sort di PHP setelah data diambil (cuma benar untuk 1 halaman, bukan global).
- **Kembalikan** — tombol cuma muncul kalau `AuditLog` reject terakhir punya `old_values.status` yang valid (status SEBELUM ditolak). Aksi ini **bypass** `WorkflowTransition::REJECTED->allowedNextTransitions()` (yang tetap kosong di enum, gak diubah) — set `customer.status` langsung balik ke status sebelum ditolak lewat `Customer::update()`, BUKAN lewat `CustomerWorkflowService::transition()`. Konsekuensi: gak tercatat di `customer_status_logs` (state-machine log resmi), cuma di `AuditLog` module `Customer Workflow` action baru `status_restore`.
- Permission: `customers.detail.installation.validate` (sama dengan permission Reject/Approve/Revisi — no perm baru).
- **Implikasi ke narasi "final, harus registrasi ulang dari awal"**: sekarang ada jalur resmi buat mengembalikan pelanggan yang ditolak TANPA registrasi ulang, asal FOP/Admin punya alasan (mis. reject keliru pencet). Ini keputusan bisnis eksplisit dari user session 2026-07-20 — beda dari desain awal reject (`docs/project_verifikasi_reject_gap.md`) yang mendeklarasikan reject 100% terminal.

### Aktivasi Manual — jalur khusus data migrasi (`CustomerController::activate`, 2026-07-20)

Escape hatch buat pelanggan hasil **migrasi legacy** yang di sistem lama udah lama aktif (bayar, terpasang), tapi di sistem baru nyangkut belum `active` karena gak pernah punya Task Survey/Pemasangan di sini (alur normal §4-§7 gak pernah kelewatan). Tombol "Aktivasi Manual" muncul di halaman detail Customer (bukan di alur SRV/PSB manapun) HANYA kalau **SEMUA** syarat ini kepenuhi:

1. Permission `customers.detail.installation.activate`.
2. `customer.old_customer_id` terisi (bukti hasil import legacy, bukan pelanggan baru).
3. `customer.customerService.request_status === 'ACTIVE'` (bukti di sistem lama BENAR sudah aktif — bukan `PENGAJUAN`/`DIPROSES`/`GAGAL`. Lihat §10 buat detail nilai legacy ini).
4. Belum ada `Task` type Survey/Pemasangan sama sekali buat customer ini (belum pernah kesentuh alur normal sistem baru).
5. Belum `active`/`siap_billing`.

Kalau salah satu gak kepenuhi (termasuk pelanggan migrasi yang di sistem lama JUGA masih stuck di tahap survey/pemasangan — `request_status` bukan `ACTIVE`), tombol disembunyikan **dan** endpoint nolak walau di-POST langsung (guard server-side, bukan cuma UI). Efeknya sama kayak `finalVerify()` normal: generate CID (`Pop::generateComplexCid()`), `status=active`, `customer_status=aktif`, `data_completeness_status=siap_billing`, `service_status=aktif`, `billing_status=active` — tapi **tanpa** bikin Invoice awal manual (beda dari §7 approve normal) dan **tanpa** lewat `CustomerWorkflowService::transition()` (update langsung, gak tercatat di `customer_status_logs`).

**Kenapa gak boleh dipakai buat pelanggan SRV/PSB yang lagi jalan**: kalau gate #4 (no-Task) doang dipakai, pelanggan migrasi yang di sistem lama JUGA stuck di tahap survey (`request_status=PENGAJUAN`/`DIPROSES`) bisa ketuker lolos juga (kebetulan belum ada Task di sistem baru) — makanya gate #3 (`request_status=ACTIVE`) ditambah, biar cuma pelanggan yang TERBUKTI udah aktif di sistem lama yang bisa lewat jalur ini. Pelanggan migrasi yang stuck SRV/PSB di sistem lama harus tetap lewat alur normal §4-§7 di sistem baru.

### Revisi (`revisi`)

- Transition ke `revision_installation` — **bukan final**, pelanggan bisa lanjut lagi (lihat tabel transisi §1: `revision_installation` bisa balik ke `verification_admin`/`installed`/`waiting_installation`).
- Update `CustomerInstallation.installation_status` balik ke `in_progress` (supaya teknisi bisa submit ulang laporan) + prepend catatan revisi ke `installation_note`.
- Auto-revert Task Pemasangan terkait: status Task balik ke `in_progress`, `fop_review_status=rejected`.
- **Notifikasi in-app (2026-08-06):** tim pemasangan dapet notif WARNING ("perlu revisi"). Detail: `docs/plan/analisa-status-implementasi-notifikasi.md` §8.2.

## 8. Teknisi Fieldwork Page — Data Teknis Terpisah dari Detail Pelanggan (2026-07-28)

### Latar Belakang (RBAC Split)

Sebelumnya, teknisi akses data Perangkat & Pemasangan lewat halaman `/customers/{id}` (Detail Pelanggan), yang sama dengan tab-tab lain (identitas/alamat/paket/billing/dokumen/riwayat). Masalah: permission gate semua tab itu dengan `customers.view`/`customers.detail.view` — kalau teknisi dikasih akses, dia bisa lihat data identitas/billing/dokumen yang seharusnya hanya admin/noc/fop lihat.

**Solusi (2026-07-28):** Halaman baru `/customers/{id}/perangkat-pemasangan` khusus teknisi fieldwork.

### Halaman Fieldwork (`CustomerFieldworkController`)

**Route & Guard:**
```php
Route::middleware('permission:customers.detail.devices.view|customers.detail.installation.view')->group(function () {
    Route::get('/customers/{customer}/perangkat-pemasangan', [CustomerFieldworkController::class, 'show'])->name('customers.fieldwork');
});
```

**Controller:**
```php
class CustomerFieldworkController extends Controller {
    public function show(Customer $customer) {
        // Load HANYA 3 relasi yang needed buat device & installation tab
        $customer->load(['customerDevice', 'customerTechnicalDetail', 'installations.technician']);
        return view('customers.fieldwork', compact('customer'));
    }
}
```

**View (`customers/fieldwork.blade.php`):**
- Render tab `_installation` + `_device` (reuse partial dari halaman Detail)
- Modal form "Isi Perangkat" + "Isi Pemasangan" (sama persis)
- **TANPA** tab lain (identitas/alamat/paket/billing/dokumen/riwayat)

### Permission Vs Route

| Halaman | Permission | Teknisi Akses? | Data Dimuat |
|---------|------------|------|------|
| `/customers` (List) | `customers.view` | ✗ BLOK | - |
| `/customers/{id}` (Detail) | `customers.detail.view` | ✗ BLOK | 17 relasi |
| `/customers/{id}/perangkat-pemasangan` (Fieldwork) | `customers.detail.devices.view` OR `customers.detail.installation.view` | ✓ BOLEH | 3 relasi |

**Teknisi permission set:**
- ✓ `customers.detail.devices.*` (view, create, update, view_sensitive, update_sensitive)
- ✓ `customers.detail.installation.*` (view, update, validate, activate, reject)
- ✗ `customers.view` — tidak ada
- ✗ `customers.detail.view` — tidak ada

Dengan set ini, teknisi:
- Can access fieldwork page (permission middleware OR logic lolos)
- Cannot access List Data / List Putus / List Gagal / Detail Pelanggan (routes return 403)
- Cannot see sidebar items untuk list/detail pages (Blade `@can()` gate blok render)

### Backward Compat & Testing

**Old links:** Kalau ada dokumentasi/cheatsheet yang refer `/customers/{id}` buat teknisi lihat device — UPDATE ke `/customers/{id}/perangkat-pemasangan`. Tests updated:

- `CustomerDeviceTest::test_device_data_is_visible_on_customer_detail()` → use `route('customers.fieldwork', $customer->id)`
- `CustomerDeviceSensitiveFieldTest::test_teknisi_can_see_and_update_sensitive_fields()` → use fieldwork route
- `CustomerInstallationTest::test_installation_data_is_visible_on_customer_detail()` → use fieldwork route

Modal form submission (POST `/customers/{id}/device` dan `/customers/{id}/installation`) **tetap sama** — teknisi post dari fieldwork page, action handler tidak berubah.

### Design Rationale

Fieldwork page load minimal relasi (`customerDevice`, `customerTechnicalDetail`, `installations`) — tidak perlu `customers.full_load()` yang 17 relasi. Spesialisasi ini juga force teknisi ke "field-only" mode semantik: harusnya HANYA isi teknis, bukan edit identitas/billing/dokumen/riwayat.

Jika di futur ada kebutuhan teknisi lihat identitas/alamat *saat di lokasi* (misalnya verifikasi data di rumah), bisa expand fieldwork page dengan select query, bukan give `customers.detail.view` permission yang buka semua tab.

## 8. Terminasi Layanan (`CustomerTerminationController`)

- Endpoint tunggal `__invoke()` — set `customer.status=terminated`, `customer_service.service_status=berhenti`.
- **Tidak lewat `CustomerWorkflowService::transition()`** — update status langsung tanpa validasi state machine (beda dari semua transisi lain di atas). Implikasi: terminasi bisa terjadi dari status manapun (termasuk yang secara teori gak valid menurut tabel `WorkflowTransition`), dan **tidak** tercatat di `customer_status_logs` (cuma di `AuditLog` biasa, module `customers`, action `terminate`).
- Alasan `required` di form — disimpan cuma di `AuditLog.new_values`, gak ada kolom dedicated buat alasan terminasi di tabel manapun.
- **Notifikasi in-app (2026-08-07):** pendaftar asli pelanggan (`customers.created_by`, relasi `Customer::creator()`) dapet notif ERROR, di-skip kalau yang eksekusi terminasi = pendaftar itu sendiri. **`suspended` SENGAJA gak dapet notif serupa** — beda dari `active`/`terminated` yang masing-masing punya SATU action controller khusus, `suspended` cuma keset lewat form edit generik (`CustomerController::update()`), risiko false-positive kalau dipaksa. Detail: `docs/plan/analisa-status-implementasi-notifikasi.md` §8.5.

### List Putus Langganan + Ambil Alat + Langganan Lagi (2026-07-20)

- List **Putus Langganan** (`/customers/terminated`) dirapikan jadi tabel: ID, Nama, Kontrak (Sewa/Beli — dari `customer_service.contract_type`), Alasan Putus, Tanggal Pemutusan (dibaca dari `AuditLog` module `customers` action `terminate`, sama pola kayak Pelanggan Gagal — bukan kolom dedicated), Status Alat, Action.
- **Urut DESC berdasarkan Tanggal Pemutusan (2026-07-20)**: sama pola persis kayak Pelanggan Gagal di atas — subquery `AuditLog::selectRaw('MAX(created_at)')` (module `customers`, action `terminate`), bukan `customer_code`.
- **Status Alat** — kolom baru `customer_devices.device_retrieved_at` (nullable timestamp, migrasi `2026_07_18_163955_add_device_retrieved_at_to_customer_devices_table`). Null = "Belum di Ambil", terisi = "Sudah di Ambil". **Tidak ada kolom ini sebelumnya** — sebelum perubahan ini, gak ada cara sistem tahu status pengambilan alat pelanggan yang putus.
- **Ambil Alat** (`CustomerController::retrieveDevice`) — cuma muncul kalau alat belum diambil, set `device_retrieved_at = now()`. Guard: `customer.status === 'terminated'` dan `customerDevice` harus ada. Permission `customers.detail.devices.retrieve` *(dipisah dari `customers.update` 2026-07-20, lihat [docs/rbac/business-logic.md § 3.1](../rbac/business-logic.md#31-langkah-nambah-permission-baru-fitur-existing--contoh-nyata-customersdetaildevicesretrieve))*.
- **Langganan Lagi** (`CustomerController::reactivate`) — muncul di SEMUA baris terminated (gak digate status alat). Set `customer.status = 'active'` LANGSUNG (bukan lewat alur survey/verifikasi ulang, keputusan bisnis eksplisit: infrastruktur dianggap masih terpasang) + `customer_service.service_status = 'aktif'`. Sama kayak Kembalikan (§7), ini **bypass** `CustomerWorkflowService::transition()` — `terminated` di enum `WorkflowTransition` tetap `[]` (terminal, gak diubah), transisi balik dicatat cuma di `AuditLog` action baru `reactivate`, BUKAN di `customer_status_logs`. Permission `customers.detail.installation.validate`.
- **Implikasi:** `terminated` sekarang, kayak `rejected`, punya jalur resmi buat "gak beneran final" — dua-duanya ($7 Kembalikan & sini) sengaja gak ubah state machine enum, cuma nambah endpoint yang mem-bypass-nya dengan guard longgar (status check doang, gak ada validasi `allowedNextTransitions()`).

## 9. Migrasi Data Legacy (`CustomerController::confirmImport` + `MigrateLegacyDataCommand`, 2026-07-20)

Data lama (`sand_db_sandya.sql`, `jetis_db_aplikasi_jetis.sql`, tabel `prosedure_permintaan_wifi`) diimport lewat `php artisan app:import-legacy-sql` → internal call ke `CustomerController::validateImport()` + `confirmImport()`. Status `Customer` hasil import ditentukan dari `mapLegacyServiceStatus()`, yang menerjemahkan `prosedure_permintaan_wifi.STATUS` (legacy) ke `WorkflowTransition` (baru):

| STATUS legacy | Arti (dari kolom `DISURVEY`/`DIACC`/`DIPROSES`) | Status baru |
|---|---|---|
| `PENGAJUAN` | Request masuk, `DISURVEY` masih kosong — belum disurvey sama sekali | `waiting_survey` |
| `DISURVEI` | `DISURVEY` & `DIACC` keduanya terisi, `DIPROSES` masih kosong — survey selesai + admin sudah ACC, tinggal nunggu tim pasang | `waiting_installation` |
| `ACTIVE` | Semua tahap (survey/ACC/proses) terisi | `active` |
| `GAGAL` | — | `rejected` |
| `PUTUS` | — | `terminated` |

**Riwayat bug yang udah diperbaiki** (jangan diulang kalau nambah mapping baru): `DISURVEI` sempat ke-mapping ke `waiting_survey` (harusnya `waiting_installation`) dan `PENGAJUAN` sempat ke-mapping ke `registered` (harusnya `waiting_survey`, karena `registered` bukan status yang disinggahi di alur normal — lihat §1) — dua-duanya bikin pelanggan migrasi nyasar ke antrean/hilang dari antrean yang salah (Survey vs Verif & Pemasangan ketuker).

### Kontrak (Sewa/Beli) — sumber kolom salah (fixed 2026-07-20)

`customer_services.contract_type` sebelumnya diisi dari `STATUSLANGGANAN` (kosong di semua data legacy yang ada) — harusnya dari `STATUSALAT` (isinya `SEWA`/`BELI`). Nilai dinormalisasi lowercase (`strtolower`) saat import karena `resources/views/customers/index.blade.php` (`match()` di tabel Putus Langganan) match case-sensitive ke `'sewa'`/`'beli'`.

### AuditLog sintetis buat alasan + tanggal (fixed 2026-07-20)

List Pelanggan Gagal & Putus Langganan baca alasan/tanggal dari `AuditLog` (lihat §7 & §8) — tapi `confirmImport()` set status pakai `Customer::updateQuietly()`, yang **gak** lewat `CustomerWorkflowService::transition()` sehingga gak pernah nulis `AuditLog`. Hasilnya: pelanggan migrasi yang `rejected`/`terminated` selalu kosong alasan+tanggalnya. Fix: `confirmImport()` sekarang bikin `AuditLog` manual pas `$serviceStatus` resolve ke `rejected` (module `Customer Workflow`, action `status_transition`, format sama kayak transition asli) atau `terminated` (module `customers`, action `terminate`) — alasan dari `ALASAN` legacy (`CustomerService.reason`), tanggal dari field baru `status_changed_at` (`MigrateLegacyDataCommand`: `TGLSELESAI` kalau ada, fallback ke `updated_at` baris legacy — BUKAN tanggal karangan/`now()`, konsisten dengan aturan anti-fabrikasi tanggal yang udah ada di `activation_date`).

**Catatan batas**: `old_values.status` di AuditLog sintetis ini di-default `'registered'` (buat rejected) / `'active'` (buat terminated) — data legacy gak selalu jelas tahap PERSIS sebelum gagal/putus, jadi tombol **Kembalikan** (§7)/**Langganan Lagi** (§8) buat pelanggan migrasi yang gagal bakal balikin ke `registered`, bukan ke tahap SRV/PSB terakhir yang sebenarnya.

Lihat juga §7 "Aktivasi Manual" — jalur aktivasi khusus buat pelanggan migrasi yang TERBUKTI `request_status=ACTIVE` di data lama tapi belum kesentuh alur normal di sistem baru.

### Notifikasi hasil import (2026-08-07)

`confirmImport()` (dipanggil baik dari UI `/customers/import` maupun internal `php artisan app:import-legacy-sql`) sekarang notif `auth()->user()` (uploader) sendiri — SUCCESS + jumlah baris ter-import kalau sukses, ERROR + pesan exception kalau gagal (`catch` block, `$batch->status='failed'`). **Koreksi asumsi lama:** gak ada class `CustomersImport` terpisah — importnya inline lewat `Spatie\SimpleExcel\SimpleExcelReader`, dan prosesnya **sinkron** dalam satu request HTTP, bukan queued job. Notif tetap berguna buat ninggalin jejak `batch_number` di `/notifications` walau hasilnya udah keliatan langsung di response. Detail: `docs/plan/analisa-status-implementasi-notifikasi.md` §8.6.

## 10. Kaitan dengan Modul Lain

- **FOP Task** — `CustomerWorkflowService` auto-create `Task` (bukan `FopTask`) saat masuk `waiting_survey`/`waiting_installation`. `FopTaskController::autoSyncAndCalculatePriority()` yang kemudian sinkron `FopTask` dari status `Customer` (lihat [docs/fop-task/flowchart.md](../fop-task/flowchart.md)) — jadi ada **2 lapis auto-sync** yang jalan independen: satu bikin `Task` (level workflow), satu bikin `FopTask` (level tiket FOP).
- **FOP Task — status Task selesai + nunggu keputusan**: begitu `Task.status=selesai` (tahap survey maupun install, nunggu aksi Approve/Revisi/Tolak di modul ini), `FopTask` terkait LANGSUNG `selesai` (unifikasi enum 2026-07-20 — `FopTask.status` sekarang mirror langsung `Task.status`, bukan mapping bucket `FopTaskStatus` yang udah dihapus) — otomatis udah gak nangkring di antrian FOP aktif (selesai emang gak pernah masuk antrian, gak butuh logic exclude khusus). Nasib keputusan (Menunggu/Diterima/Ditolak) tampil sebagai badge KEDUA di Riwayat FOP (`FopTask::verificationStatus()`), + link balik ke halaman verifikasi ini kalau masih Menunggu. Keputusan sebenarnya (Approve/Revisi/Tolak) TETAP di modul Customer ini, Riwayat FOP cuma informasional. Lihat `docs/fop-task/flowchart.md` § 11 & `docs/project_verifikasi_reject_gap.md` (§ DESAIN FINAL).
- **FOP Task — cancel SRV/PSB terkunci dari Task/FopTask (2026-07-21)**: `TaskPolicy::cancel()` + `FopTaskController::update()` block cancel/dibatalkan buat task_type SURVEY/PEMASANGAN, di SEMUA role. Satu-satunya jalur sah: `CustomerSurveyController::cancel()` (§4) / `CustomerInstallationController::cancel()` (§6) di modul ini — biar `Customer.status` konsisten ikut `rejected`. Lihat `docs/fop-task/flowchart.md` § 12.
- **Billing** — `finalVerify()` satu-satunya titik yang bikin Invoice tipe `awal`. Invoice bulanan rutin (`billing:generate-monthly-invoices`) baru mulai jalan bulan **setelah** bulan aktivasi ini (guard anti-dobel-tagih, lihat [docs/billing-pembayaran/flowchart.md](../billing-pembayaran/flowchart.md)).
- **RBAC** — semua guard di atas granular per sub-fitur (`customers.detail.survey.*`, `customers.detail.installation.*`) — role Teknisi biasanya cuma punya `.update` (submit laporan), role FOP/Admin punya `.validate` (approve/reject/revisi/aktivasi).
