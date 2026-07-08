# Gap — Survey "Tidak Layak Pasang" Gak Pernah Ditangani

**Status:** ✅ Fixed 2026-07-08.
**Severity:** Tinggi — pelanggan yang lokasinya tidak layak pasang jadi "zombie" (nyangkut selamanya di status `survey_in_progress`), gak pernah ke-mark selesai/gagal.

## Ringkasan

Ditemukan lewat pertanyaan pemilik produk (bukan lewat kode/testing): "gimana kalau pas survey ternyata tidak layak pasang?"

Kondisi kode **sebelum** perbaikan:

1. **UI form Lapor Survey (`resources/views/surveys/report.blade.php`) hardcode** `<input type="hidden" name="survey_status" value="completed">` — teknisi **tidak punya cara** memilih `failed` sama sekali, walau validasi backend sudah menerima value ini (`in:pending,completed,failed`).
2. **`CustomerSurveyController::store()` cuma nangani cabang `survey_status === 'completed'`** — tidak ada `else`. Kalau saja `failed` bisa dikirim (misal lewat request manual), datanya tersimpan tapi:
   - `customer.status` tetap `survey_in_progress` selamanya — tidak ada transisi ke status manapun.
   - Task Survey terkait tidak pernah di-close.
   - Tiket Pemasangan tidak akan pernah terbentuk (karena workflow gak maju), tapi bukan hasil keputusan eksplisit — cuma efek samping dari tidak adanya handling.
3. Field teknis (`cable_estimation_meter`, `nearest_odp`, `survey_photo`, `house_photo`, `difficulty_level`) **wajib** (`required`) tanpa syarat — kalau lokasi memang tidak bisa disurvei penuh (misal alamat gak ketemu/ditolak warga), teknisi gak bisa submit laporan gagal karena field-field ini gak akan pernah terisi.

State machine (`WorkflowTransition::allowedNextTransitions()`) sebenarnya **sudah** mengizinkan `SURVEY_IN_PROGRESS → REJECTED` — cuma gak pernah dipanggil dari jalur survey. Satu-satunya tempat `REJECTED` benar-benar dipakai adalah `CustomerVerificationController::reject()` (nolak hasil survey yang **sudah completed** tapi gak lolos verifikasi admin — beda kasus dari survey yang gagal di lapangan).

## Perbaikan Diterapkan

1. **View** `resources/views/surveys/report.blade.php`:
   - Hidden input `survey_status` diberi `id="survey_status_input"` biar bisa diubah lewat JS (sebelumnya statis `value="completed"`).
   - Tombol baru **"Tidak Layak Pasang"** (merah, di footer step 4, sejajar tombol "Simpan Laporan Survey") — klik → `reportFailed()`: validasi `survey_note` wajib diisi (alasan), konfirmasi via `confirm()`, set `survey_status_input.value = 'failed'`, submit form.
2. **`CustomerSurveyController::store()`**:
   - Validasi field teknis (`cable_estimation_meter`, `nearest_odp`, `survey_photo`, `house_photo`, `difficulty_level`) diubah dari `required` mutlak jadi `required_if:survey_status,completed` — opsional kalau `failed`, karena situs mungkin memang tidak bisa disurvei penuh.
   - `survey_note` jadi `required_if:survey_status,failed` — alasan tidak layak pasang wajib diisi.
   - Cabang baru: kalau `survey_status === 'failed'` dan customer masih `survey_in_progress` → Task Survey terkait di-**cancel** (`TaskService::cancel()`, reuse status `DIBATALKAN` + `cancel_reason` — tidak menambah state baru di `TaskStatus` enum), customer di-**transition ke `WorkflowTransition::REJECTED`** (reuse state final yang sudah ada, dengan alasan dari `survey_note`).
   - Redirect disesuaikan: `failed` kembali ke Antrean Survey (bukan ke antrean verifikasi yang gak relevan lagi buat pelanggan yang udah `rejected`).
3. **Notifikasi Telegram/event khusus survey gagal** — **belum dibuat**, sengaja ditunda (disepakati sebagai iterasi berikutnya, bukan blocking).

## Susulan: Jalur Cepat Batalkan Survey dari Status (✅ 2026-07-08)

Jalur di atas (form Lapor Survey lengkap) cuma bisa dipicu **teknisi** yang lagi ngerjain survey. FOP/NOC/Admin yang mantau dari halaman detail pelanggan gak punya cara cepat nandain "tidak layak pasang" tanpa masuk ke akun teknisi. Ditambah:

1. **RBAC baru, bukan hardcode** — action `reject` ditambah ke feature `customers.detail.survey` di `config/rbac.php` (`allowed_actions`), permission `customers.detail.survey.reject` **digenerate otomatis** lewat `PermissionGeneratorService` (bukan ditulis manual). Di-assign ke role yang sudah punya `.validate` untuk fitur ini: `fop`, `noc` (`RolePermissionSeeder.php`) — `admin`/`owner` dapat otomatis (wildcard `customers.detail.*` / `*`). `teknisi`/`helpdesk` sengaja tidak dapat (teknisi sudah punya jalur lengkap via form Lapor Survey).
2. **`CustomerSurveyController::cancel()`** (baru) — endpoint `POST /customers/{customer}/survey/cancel`, guard status (`waiting_survey`/`survey_in_progress` saja), `reason` wajib. Logic-nya reuse persis: cancel Task Survey terkait, tulis `survey_status=failed`+alasan ke `CustomerSurvey`, transition customer ke `REJECTED`.
3. **UI** — tombol **"Batalkan Survey"** (merah) di tab Survey halaman detail pelanggan (`customers/tabs/_survey.blade.php`), muncul kalau status `waiting_survey`/`survey_in_progress` **dan** user punya permission `customers.detail.survey.reject`. Klik → modal alasan wajib diisi → submit.

## Yang Sengaja Tidak Diubah

- **Tidak ada state baru** di `WorkflowTransition`/`TaskStatus` — sepenuhnya reuse `REJECTED` dan `DIBATALKAN` yang sudah ada, konsisten dengan jalur reject-verifikasi (`CustomerVerificationController::reject()`).
- **Tidak ada perubahan skema database** — `survey_note` (kolom existing) dipakai sebagai alasan, `cancel_reason` (kolom existing di `tasks`) dipakai sebagai alasan pembatalan task.
- Pelanggan yang sudah `REJECTED` lewat jalur ini **tidak bisa di-follow-up ulang** (state final, `allowedNextTransitions()` kosong) — sama seperti pelanggan yang direject di tahap verifikasi.
