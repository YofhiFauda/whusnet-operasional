# Keputusan & Analisa — API Baru: Topologi Jaringan & Konfirmasi Assignment

Dokumen pendamping. `business-logic.md`/`database-schema.md` menjelaskan **rancangan
seperti apa adanya**; berkas ini menjelaskan **kenapa jadi begitu, dan apa yang
ditolak di jalan**.

---

## 1. Riwayat revisi

| Rev | Tanggal | Yang berubah | Pemicu |
|---|---|---|---|
| 1 | 2026-08-20 | Rancangan awal: satu endpoint tulis (`network-assignment`) | Pertanyaan "bagaimana Website B atur Mini POP/Distribusi" |
| 2 | 2026-08-20 | Ditambah endpoint baca (`pop-distribusi`) — ditemukan Website B tidak tahu kode Mini POP/Distribusi tanpa referensi | Pertanyaan "bagaimana mereka tau kode yang ada" |
| 3 | 2026-08-20 | Folder dokumen dipisah dari `api-webhook-pemasangan`/`api-portal-pelanggan` | Permintaan restrukturisasi |
| 4 | 2026-08-20 | Rename endpoint #1 `network-topology` → `pop-distribusi`, config token `NETWORK_TOPOLOGY_READ_TOKEN` → `POP_DISTRIBUSI_READ_TOKEN` | Permintaan penamaan |
| 5 | 2026-08-20 | Endpoint #2 ditambah field opsional `perangkat.pppoe_username`/`pppoe_password`/`ip_address` — Website B isi ulang kredensial jaringan di momen konfirmasi aktivasi yang sama | Kebutuhan: username/password PPPoE + IP di Whusnet terisi otomatis begitu Website B aktivasi |
| 6 | 2026-08-22 | Ditambah: dedup `idempotency_key` beneran (kolom `audit_logs.idempotency_key` + cek sebelum proses), transaction+row lock, `mini_pop_code`/`distribution_code` jadi `required` bareng (bukan `nullable` seperti controller staf), rate limit + threshold alert dikonkretkan, syarat retensi `webhook_outbox` ≥90 hari | Review celah logic sebelum implementasi — lihat §10-§13 di bawah |
| 7 | 2026-08-22 | Seluruh pertanyaan `rencana-implementasi.md` dijawab resmi: validasi gagal langsung ditolak (bukan approval), assignment **selalu manual** oleh petugas Website B, kredensial jaringan **independen timing** dari assignment (bukan wajib bareng — koreksi asumsi rev 5), `pppoe_password` tetap plaintext, rate limit + alert dikonkretkan, retensi outbox 90 hari. Dedup idempotency di-scope ulang ke `idempotency_key`+`request_hash` (bukan key doang) supaya request kredensial susulan gak salah ke-block | Konfirmasi alur nyata dari pemilik produk — lihat §14-§15 di bawah |
| 8 | 2026-08-22 | `perangkat` ditambah 4 field: `olt_number`, `olt_slot`, `olt_port`, `vlan` — target tabel `customer_technical_details`, **beda** dari `pppoe_username`/`pppoe_password` yang ke `customer_devices` | Hasil akhir assignment ternyata termasuk detail titik sambung OLT, bukan cuma kredensial PPPoE — lihat §16 |
| 9 | 2026-08-22 | `perangkat.ip_address` **dihapus** dari kontrak — konsep "IP jaringan pelanggan" dihapus dari seluruh sistem (keputusan produk), `customers.ip_address`/`customer_devices.ip_address` tidak ada lagi | Perintah eksplisit menghapus IP Address dari seluruh sistem, bukan cuma endpoint ini |
| 10 | 2026-08-22 | Response endpoint #2 ditambah `mini_pop_code`/`distribution_code` (bukti balik assignment) dan `cid` sekarang berisi **preview** (bukan `null` polos) kalau pelanggan belum aktif, dibarengi flag baru `cid_final` — `customers.cid` TIDAK ikut berubah, cuma respons | Assignment sempat diragukan sukses karena respons lama `{"cid": null}` gak informatif — lihat §18 |
| 11 | 2026-08-22 | Revisi §18: `cid_final` **dihapus** — dianggap cuma mindah ambiguitas (`null` vs `cid_final=false`), bukan menyelesaikannya. Key `cid` sekarang **dihilangkan total** dari respons kalau belum final, bukan `null`/preview | *Presence* key dianggap sinyal yang lebih bersih daripada nilai + flag terpisah |
| 12 | 2026-08-22 | **Endpoint #2 dipecah jadi dua**: `POST /installations/network-assignment` (Mini POP/Distribusi doang, `mini_pop_code`+`distribution_code` jadi `required`) dan `POST /installations/network-device` (`perangkat` doang, endpoint baru). Berbagi token tulis, rate limiter & audit action terpisah | Permintaan eksplisit pemilik produk supaya dua hal ini bisa berkembang independen ke depannya — lihat §19 |

---

## 2. Kenapa dua endpoint, bukan satu

Alternatif yang sempat terlihat lebih sederhana: satu endpoint saja, Website B kirim
kode Mini POP/Distribusi langsung tanpa referensi baca terpisah — mereka "tebak" dari
dokumentasi statis yang dikirim manual sekali di awal.

**Ditolak.** Mini POP/Distribusi jarang berubah tapi **tetap** berubah (bulanan,
kadang ada tambahan). Dokumentasi statis (spreadsheet, PDF) basi begitu ada
perubahan dan tidak ada yang menjamin Website B selalu pakai versi terbaru. Endpoint
baca yang bisa dipanggil kapan saja menghapus kelas masalah itu — sinkron selalu
terjamin karena mereka membaca langsung dari sumbernya, bukan salinan yang bisa
basi.

## 3. Kenapa bukan dikirim di payload `installation.activated`

Alternatif lain: sisipkan daftar Mini POP/Distribusi (di-scope ke POP pelanggan) di
payload `api-webhook-pemasangan`, jadi Website B tidak perlu endpoint kedua.

**Ditolak.** `installation.activated` bisa terkirim berkali-kali untuk satu
pelanggan (lihat `api-webhook-pemasangan/business-logic.md` "Aktivasi bisa ditekan berkali-kali") —
mengulang daftar topologi lengkap di **setiap** pengiriman itu boros, terutama karena
data itu tidak berubah antar pengiriman. Endpoint baca terpisah, dipanggil sesuka
Website B (cache, refresh bulanan), jauh lebih murah untuk data yang jarang berubah.

Juga: payload `api-webhook-pemasangan` di-scope per pelanggan (POP tunggal), sementara kebutuhan
nyatanya "daftar lengkap semua POP" (dikonfirmasi) — scope yang beda membuat
penyisipan ke payload jadi tidak pas secara desain, bukan cuma soal boros.

## 4. Kenapa validasi Mini POP/Distribusi harus reuse, bukan ditulis ulang

Alternatif yang tergoda: tulis validasi baru yang lebih sederhana khusus untuk
endpoint API (skip beberapa pengecekan demi ringkas).

**Ditolak.** `CustomerNetworkAssignmentController::update()` sudah punya aturan
validasi yang benar dan sudah diuji lewat jalur staf: Mini POP harus anak Cabang POP
pelanggan, Distribusi harus anak Mini POP yang dipilih, status pelanggan tidak boleh
di tahap belum-mulai-pasang. Menulis ulang aturan yang berbeda untuk jalur API berarti
dua sumber kebenaran untuk satu invarian data — begitu salah satu diubah, yang lain
lupa diikutkan, dan validasi antara jalur staf vs jalur API diam-diam menyimpang.

## 5. Kenapa dua kredensial (baca vs tulis), bukan satu

Alternatif: satu token bearer untuk kedua endpoint, lebih sedikit yang perlu
dikelola.

**Ditolak.** Dua endpoint ini beda kelas risiko total. Baca topologi cuma expose
struktur internal (nama Mini POP, kode Distribusi) — bocor pun dampaknya kecil.
Tulis assignment mengubah `cid` pelanggan — identitas yang dipakai di seluruh
sistem (billing, dokumen legal, kwitansi). Kalau token baca bocor (risiko lebih
tinggi karena lebih sering dipakai/logged), dampaknya tidak ikut membuka jalur
tulis. Prinsip yang sama dengan pemisahan secret HMAC arah keluar vs token callback
arah masuk di `api-webhook-pemasangan`.

## 6. Kenapa `idempotency_key`, bukan `customer_id` atau `cid`, sebagai kunci

Dibahas detail di `database-schema.md`. Ringkas: `customer_id` tidak pernah boleh
diterima dari luar (larangan keras lintas-API), `cid` nullable dan justru salah satu
nilai yang ditulis endpoint ini.

## 7. Kenapa validasi gagal langsung ditolak, bukan menunggu approval staf

Alternatif: endpoint ini cuma "mengusulkan" assignment, staf tetap harus approve
manual sebelum benar-benar tersimpan.

**Diputuskan (resmi, 2026-08-22): langsung ditolak, tanpa approval manual.** Review
otomatis dari sistem provisioning (validasi Mini POP/Distribusi cocok dengan Cabang
POP pelanggan) pada dasarnya melakukan pemeriksaan yang sama dengan yang staf
lakukan manual — gak ada nilai tambah nunggu approval buat pemeriksaan yang sudah
pasti benar/salah secara aturan. Konsisten juga dengan fakta bahwa **assignment-nya
sendiri sudah manual** (petugas Website B yang mengonfirmasi, lihat §14) — lapisan
approval kedua di sisi Whusnet jadi redundan.

## 8. Kenapa kredensial jaringan (PPPoE/IP) tetap di endpoint #2, bukan endpoint ketiga

**Keputusan di section ini DIBALIK di rev 12 — lihat §19.** Endpoint ketiga
akhirnya dibuat juga, tapi bukan karena analisis di bawah ini salah (analisisnya
tetap valid secara teknis) — murni keputusan produk soal fleksibilitas
pengembangan ke depan. Dipertahankan di sini sebagai riwayat, bukan dihapus.

**Rev 5 (asumsi awal, sudah dikoreksi — lihat §14):** kredensial dianggap selalu
datang bareng momen konfirmasi Mini POP/Distribusi (satu kejadian bisnis). Ternyata
salah — dikonfirmasi pemilik produk (2026-08-22) kredensial jaringan **independen
timing-nya** dari assignment: assignment selalu manual, kredensial boleh menyusul
kapan saja tergantung integrasi Website B.

**Endpoint tetap satu, bukan dipecah jadi endpoint ketiga** — tapi alasannya
berubah dari rev 5. Bukan lagi "keduanya satu momen jadi harus satu request", tapi:
`perangkat` **dari awal sudah opsional** (rev 5 sendiri sudah mendesainnya begitu),
jadi endpoint ini sudah gak pernah memaksa keduanya datang bareng — masalah yang
tadinya jadi alasan pertimbangan endpoint ketiga ("satu endpoint memaksa bareng")
gak pernah benar-benar ada. Satu endpoint dipertahankan karena reuse
validasi/auth/audit yang sama dan menyentuh entity yang sama (pelanggan + Mini
POP/Distribusi + perangkat jaringan) — endpoint ketiga cuma nambah permukaan tanpa
keuntungan nyata. Body wajib punya **minimal salah satu** dari pasangan
`mini_pop_code`+`distribution_code` atau `perangkat` (lihat `business-logic.md`
§"Alur nyata").

## 9. Kenapa `pppoe_password` tidak dienkripsi sekarang, meski lewat jalur baru

Alternatif: tambahkan `encrypted` cast ke `customer_devices.pppoe_password` sebagai
bagian dari pekerjaan ini, karena sekarang ada jalur tulis baru dari luar organisasi
ke kolom itu.

**Ditunda, bukan ditolak** — lihat pertanyaan terbuka di `rencana-implementasi.md`.
Alasan menunda: kolom ini sudah plaintext sejak jalur staf (`storePemasangan()`),
dan mengenkripsinya cuma untuk jalur API baru berarti satu kolom, dua kebijakan
penyimpanan tergantung siapa yang menulis — lebih membingungkan daripada aman.
Kalau dienkripsi, itu keputusan yang berlaku untuk **seluruh** penulis kolom
tersebut (termasuk wizard teknisi), bukan tambalan khusus endpoint ini — dan itu di
luar cakupan pekerjaan menambahkan dua endpoint baru.

## 10. Kenapa `idempotency_key` butuh kolom dedup sendiri, bukan cuma dipakai lookup

Rancangan awal cuma pakai `idempotency_key` buat resolve pelanggan (§6). Itu
menutup masalah "ID internal gak boleh bocor keluar", tapi gak menutup masalah lain:
nama "idempotency key" menjanjikan **aman diulang**, sementara rancangan awal tetap
menjalankan ulang langkah 1-7 tiap kali key yang sama datang lagi. Retry jaringan
dari sisi Website B itu normal, bukan kasus tepi — kalau gak ditutup, tiap retry
nambah baris `audit_logs` baru padahal secara bisnis cuma satu kejadian.

**Solusi:** kolom `audit_logs.idempotency_key` + cek `exists()` sebelum jalanin
langkah manapun. Ditolak alternatif bikin tabel baru khusus (`network_assignment_requests`)
karena `audit_logs` sudah nyimpen data yang dibutuhkan buat nge-cache respons lama
(`old_values`/`new_values`) — nambah tabel cuma buat dedup adalah kompleksitas yang
gak perlu untuk kebutuhan sekecil ini.

## 11. Kenapa dibungkus transaction + row lock, bukan andalkan dedup check saja

Dedup check (§10) doang gak cukup kalau dua request dengan key sama datang **nyaris
bersamaan** — keduanya bisa lolos cek `exists()` sebelum salah satunya sempat
nulis baris pertama (classic check-then-act race). `lockForUpdate()` di baris
pelanggan + `DB::transaction()` ngebungkus cek-dan-tulis jadi satu unit atomik:
request kedua nunggu lock request pertama lepas, baru lihat key udah tercatat.

## 12. Kenapa `mini_pop_code`+`distribution_code` gak ikut `nullable` dari controller staf

Reuse validasi (§4 di atas) tetap berlaku buat *aturan* validasinya (Mini POP anak
Cabang POP pelanggan, dst) — tapi bukan berarti *constraint* `nullable`/`required`
ikut disalin mentah. Controller staf aman `nullable` karena modalnya nunjukin nilai
"current" dulu, jadi field kosong = sengaja di-unset. Endpoint API gak punya
konteks itu di sisi Website B — field yang gak dikirim lebih mungkin berarti
"gak tau/gak berubah" daripada "sengaja kosongkan", tapi kalau divalidasi
`nullable` hasilnya sama: nge-null-in kolom yang sudah tersimpan. Karena efeknya
destruktif dan diam-diam (gak ada error yang muncul), endpoint API mewajibkan
keduanya `required` sampai ada bukti nyata Website B butuh partial update — baru
saat itu ditambah pola eksplisit kayak `perangkat`, bukan warisan implisit dari
controller lain.

## 13. Kenapa rate limit endpoint #2 dan threshold alert dikonkretkan sekarang

Pertanyaan #3/#4 di `rencana-implementasi.md` awalnya dibiarkan tanpa angka. Itu
oke buat "belum diputuskan", tapi endpoint #2 nulis identitas pelanggan (CID) dan
gak pernah punya limiter yang disebut sama sekali (beda dari endpoint #1 yang
minimal disinggung). Angka 20/menit (tulis) dan ambang 5×422 beruntun/10 menit
diusulkan sebagai **default kerja**, bukan keputusan final — supaya rancangan gak
berhenti di "pemilik produk belum jawab" pada bagian yang justru paling berisiko
(permukaan tulis eksternal ke data pelanggan), dan pemilik produk tinggal
menyetujui/mengoreksi angka, bukan mulai dari nol.

## 14. Alur konfirmasi manual (dikonfirmasi pemilik produk, 2026-08-22)

Pertanyaan #2 `rencana-implementasi.md` dijawab eksplisit: setelah Website B terima
`installation.activated`, assignment Mini POP/Distribusi **selalu dicek dan
dikonfirmasi manual oleh petugas mereka** — bukan otomatis oleh sistem Website B.
Kredensial jaringan (`perangkat`) **bisa manual atau otomatis, tergantung integrasi
Website B sendiri** — Whusnet cuma menyediakan endpoint, tidak menentukan cara atau
kapan mereka mengisinya.

Konsekuensi ke rancangan:

- **Tidak ada SLA/timeout alert** untuk `customers.mini_pop_id` yang kosong lama —
  itu ekspektasi normal (jawaban §7 tabel pertanyaan #2), bukan tanda ada yang
  error, karena memang nunggu petugas manusia.
- **Validasi gagal tetap langsung ditolak** (§7 di atas) — assignment yang
  dikonfirmasi manual oleh petugas Website B tidak berarti perlu approval kedua di
  sisi Whusnet; validasi otomatis cukup sebagai penjaga terakhir.
- Karena kredensial independen timing-nya, endpoint #2 harus bisa menerima
  `perangkat`-saja tanpa `mini_pop_code`/`distribution_code` di request susulan —
  lihat §15 soal bagaimana ini mempengaruhi desain dedup.

## 15. Kenapa dedup idempotency di-scope ke `idempotency_key`+`request_hash`, bukan key saja

§10 (rev 6) awalnya mendesain dedup cuma cek `idempotency_key` — cukup untuk asumsi
rev 5 bahwa assignment+kredensial selalu satu request. Begitu §14 mengoreksi asumsi
itu (kredensial boleh menyusul lewat request terpisah, **key yang sama dipakai
ulang** karena masih menunjuk aktivasi yang sama), desain §10 jadi salah: request
kredensial susulan akan salah dikenali sebagai "key ini udah diproses" dan
**gak diproses** — bug baru yang lebih parah dari masalah yang tadinya mau
ditutup.

**Solusi:** dedup di-scope ke `idempotency_key` **+ hash isi body**
(`audit_logs.request_hash`, `sha256` body ternormalisasi). Key+hash identik = retry
beneran = jangan proses ulang. Key sama tapi hash beda = request baru yang sah
(assignment lalu kredensial, atau sebaliknya) = proses seperti biasa. Ditolak
alternatif "reset dedup tiap X menit" (key dianggap boleh dipakai ulang setelah
jeda waktu) karena rentang waktu antara assignment dan kredensial susulan gak
punya batas atas yang jelas (bisa berhari-hari, lihat §14) — bergantung ke jeda
waktu bakal salah tolak retry yang telat, atau salah terima duplikat yang
kecepetan.

## 16. Kenapa field OLT (`olt_number`/`olt_slot`/`olt_port`/`vlan`) masuk `perangkat`, ke tabel berbeda

Hasil akhir yang dikonfirmasi pemilik produk (2026-08-22): `perangkat` bukan cuma
kredensial PPPoE, tapi juga detail titik sambung fisik OLT — total 6 field:
`pppoe_username`, `pppoe_password`, `olt_number`, `olt_slot`, `olt_port`, `vlan`.
(Rev awal ada `ip_address` juga — dihapus lagi lewat §17, lihat itu.)

**Kenapa tetap satu object `perangkat`, bukan dipecah** (mis. `perangkat` vs
`topologi_olt` terpisah). Dari sudut pandang Website B, field-field ini muncul
dari **kejadian yang sama** — provisioning fisik yang mereka lakukan begitu
mengaktifkan pelanggan. Website B gak perlu tahu (dan gak boleh perlu tahu) bahwa
Whusnet menyimpan dua field pertama di `customer_devices` dan empat field
belakang di `customer_technical_details` — itu murni pembagian tabel internal
warisan dari desain sebelum endpoint ini ada (`storePemasangan()` sudah menulis ke
dua tabel itu sejak awal untuk data yang secara konsep sama-sama "kredensial &
topologi jaringan hasil pemasangan"). Memecah jadi dua object di kontrak API
cuma memindahkan detail implementasi ke luar organisasi tanpa manfaat.

**Kenapa bukan tabel baru yang menyatukan ketujuhnya.** Ditolak — itu berarti
migrasi data dari dua tabel existing plus mengubah semua penulis lain
(`storePemasangan()`, modal staf `CustomerDeviceController`) buat ikut nulis ke
tabel baru. Di luar cakupan pekerjaan menambahkan endpoint API; kalau nanti
penyatuan tabel `customer_devices`+`customer_technical_details` diputuskan, itu
keputusan terpisah yang mempengaruhi seluruh sistem, bukan tambalan endpoint ini.

**Kenapa `vlan` (endpoint ini) bukan `vlan_id` (`customer_devices`).** Dua kolom
beda yang kebetulan mirip nama: `customer_technical_details.vlan` (nomor VLAN di
titik OLT, yang dimaksud field `perangkat.vlan`) vs `customer_devices.vlan_id`
(kolom lain, konteks device, **tidak disentuh** endpoint ini). Implementasi wajib
sadar bedanya — salah pasang kolom di sini gampang lolos code review karena
namanya mirip tapi tabelnya beda.

## 17. Kenapa `perangkat.ip_address` dihapus dari kontrak (2026-08-22)

Rev 5-8 (§16) mendesain `perangkat.ip_address` → `customer_devices.ip_address`.
**Keputusan produk yang lebih baru membatalkan ini sepenuhnya**: konsep "IP
jaringan pelanggan" dihapus dari SELURUH sistem, bukan cuma API ini — perintahnya
eksplisit "hapus dari frontend, database, logic, atau apa pun yang berhubungan",
dan kehilangan data kolom ini disepakati boleh terjadi (masih tahap development).

Konsekuensi ke rancangan ini:
- `customers.ip_address` dan `customer_devices.ip_address` di-drop
  (`2026_08_22_120000_drop_ip_address_columns`).
- `perangkat` di endpoint #2 turun dari 7 field jadi 6 — `ip_address` bukan lagi
  bagian kontrak, tidak "dikosongkan"/`nullable` tapi **dihapus total**, supaya
  konsisten dengan alasan penghapusan (bukan sekadar tidak dipakai, tapi memang
  sudah tidak ada tempat menyimpannya).
- Semua contoh JSON, tabel field, dan uraian "tiga field pertama" di
  `business-logic.md`/`database-schema.md` disesuaikan mengikuti.
- Ini **tidak** memengaruhi `audit_logs.ip_address` — itu IP request HTTP untuk
  jejak forensik pemanggil API, konsep berbeda total, di luar cakupan
  penghapusan ini (lihat `business-logic.md` §Audit).

## 18. Kenapa key `cid` dihilangkan total kalau belum final, bukan `null` atau preview

Kebutuhan yang muncul: Website B (dan Whusnet sendiri saat debug) sempat ragu
apakah assignment beneran sukses, karena respons `{"cid": null}` — walau
`mini_pop_id`/`distribution_id` sudah tersimpan benar — kelihatan seperti
kegagalan. `null` di situ sebenarnya sesuai desain (CID cuma final saat
`active`/`suspended`), tapi gak informatif buat pemanggil.

**Alternatif yang ditolak #1: generate & simpan `customers.cid` begitu Mini
POP/Distribusi ke-assign, gak nunggu status aktif.** Ini mengubah aturan CID
yang sudah didokumentasikan ketat (spesifikasi-pop-distribusi-cid.md,
`Pop::resolveDisplayId()`, `getDisplayIdLabelAttribute()`) — REQ ID murni
sebelum aktif itu keputusan sengaja, bukan kebetulan, dan konsekuensinya
menyebar ke List Pelanggan, Detail Pelanggan, dan setiap tempat yang
membedakan tampilan REQ ID vs CID berdasarkan status. Mengubahnya demi satu
endpoint API adalah perubahan aturan bisnis lintas-sistem, bukan hal yang
pantas diputuskan diam-diam sebagai efek samping "tampilkan CID di respons".

**Alternatif yang ditolak #2 (rev 10, sempat diimplementasikan lalu dibatalkan):
`cid` = preview dihitung ulang tiap panggilan + flag `cid_final` (boolean)
pembeda.** `Pop::generateComplexCid()` dipanggil dengan `mini_pop_id`/
`distribution_id` yang baru saja di-assign (tanpa menulis ke
`customers.cid`), `cid_final=true`/`false` membedakan preview vs final.
**Dibatalkan setelah dipertimbangkan ulang:** ini cuma MEMINDAHKAN masalah
ambiguitas, bukan menyelesaikannya — sekarang ada DUA cara beda buat bilang
"ini belum final" (`cid: null` di desain awal, `cid_final: false` di rev 10),
dan Website B tetap harus tahu utak-atik logic tambahan (baca dua field,
bukan satu) buat menyimpulkan satu hal yang sama: sudah final atau belum.

**Solusi final (rev 11): key `cid` DIHILANGKAN TOTAL dari respons kalau belum
final** — bukan `null`, bukan preview, bukan ditemani flag terpisah. *Presence*
key itu sendiri adalah sinyalnya: `isset($response['cid'])` true → final
tersimpan di `customers.cid`, false → belum. Satu sumber kebenaran, bukan nilai
+ flag yang bisa saling kontradiksi kalau salah satu lupa disinkronkan.
`customers.cid` sendiri tidak pernah disentuh oleh keputusan ini — aturan CID
(REQ ID murni sebelum aktif) sama sekali tidak berubah, cuma cara
merepresentasikannya di respons API yang berubah.

**Kenapa ini lebih baik dari `null` di desain awal.** `null` sebagai NILAI
bisa dibaca banyak makna oleh klien yang gak hati-hati (belum diisi? gagal?
memang kosong?) — konvensi umum REST: kalau suatu data belum relevan/berlaku,
jangan sertakan field-nya sama sekali, bukan isi `null`. Presence-based ini
konsisten dengan pola itu.

## 19. Kenapa endpoint #2 akhirnya dipecah jadi dua (rev 12), membalik keputusan §8

§8 (dan §16) sudah menganalisis dan MENOLAK pemisahan endpoint kredensial
jaringan dari assignment Mini POP/Distribusi — alasannya valid: `perangkat`
dari awal sudah opsional, jadi masalah "satu endpoint memaksa dua hal datang
bareng" yang biasanya jadi alasan pemisahan gak pernah benar-benar ada di
rancangan ini. Satu endpoint sudah bisa dipakai terpisah lewat field yang
dikirim (assignment-saja, atau `perangkat`-saja kalau assignment sudah ada).

**Kenapa tetap dipecah kalau analisisnya sendiri bilang gak perlu.** Ini BUKAN
pembatalan analisis §8 — analisisnya tetap benar untuk pertanyaan "apakah versi
gabungan bisa berfungsi dengan benar" (jawabannya ya, dan versi gabungan yang
sempat berjalan di rev 1-11 memang benar secara teknis). Yang berubah adalah
pertanyaannya: pemilik produk memutuskan berdasarkan pertimbangan **evolusi ke
depan** — dua endpoint terpisah lebih mudah dikembangkan independen (mis. kalau
suatu hari `network-assignment` butuh field tambahan yang gak relevan buat
`network-device`, atau sebaliknya, atau salah satu butuh rate limit/kebijakan
retry yang beda jauh) daripada terus menambah kondisional ke satu endpoint yang
sama. Ini keputusan **arsitektur untuk fleksibilitas**, bukan perbaikan bug atau
celah yang ditemukan di versi gabungan.

**Apa yang TETAP dipertahankan dari versi gabungan, dipindah utuh ke dua
endpoint:**
- Resolusi pelanggan lewat `idempotency_key` → `webhook_outbox` (§6), row lock
  (§11) — sama persis di kedua endpoint, lewat method privat yang dipakai
  bareng di `NetworkAssignmentService`.
- Dedup key+hash (§10, §15) — masih perlu, malah alasannya makin kuat: kalau
  dulu "request susulan" berarti request kedua ke endpoint yang SAMA, sekarang
  bisa berarti request kedua ke endpoint yang BEDA (assignment lalu device)
  dengan `idempotency_key` yang sama. Cek dedup TETAP di-scope ke key+hash,
  bukan key doang, dengan alasan yang sama persis — cuma sekarang lintas
  endpoint, bukan lintas request ke endpoint yang sama.
- Aturan keamanan `pppoe_password` (§ "Keamanan kredensial jaringan" di
  business-logic.md) — utuh, pindah ke endpoint #3.
- Prinsip "dua tabel, satu object `perangkat`" (§16) — utuh, `perangkat` tetap
  satu object di body endpoint #3, bukan dipecah lagi jadi per-tabel.

**Apa yang berubah karena pemisahan:**
- `mini_pop_code`/`distribution_code` di endpoint #2 jadi `required` polos
  (sebelumnya sudah `required_with` satu sama lain sejak §12 rev 6 — sekarang
  makin sederhana karena gak ada lagi mode "kirim `perangkat` doang" di
  endpoint yang sama untuk dijadikan alasan `nullable`).
- Endpoint #3 (`network-device`) `perangkat`-nya jadi `required` sebagai objek
  (bukan opsional lagi, karena endpoint ini SATU-SATUNYA tujuannya memang
  mengisi `perangkat` — objek kosong di endpoint khusus perangkat gak ada
  gunanya, beda dari dulu waktu `perangkat` cuma salah satu dari dua opsi).
- Rate limiter dipisah per endpoint (`network-assignment-write` vs
  `network-device-write`) meski token-nya sama — supaya kegagalan beruntun di
  satu endpoint gak menghabiskan kuota endpoint yang lain. Threshold alert
  Telegram juga dipisah cache namespace-nya (`network-assignment:422:...` vs
  `network-device:422:...`) dengan alasan sama.
- `audit_logs.action` beda nilai: `network_assignment` vs
  `network_device_update` — supaya riwayat audit tetap bisa dibedakan per jenis
  kejadian meski sama-sama lewat `NetworkAssignmentService`.

**Kenapa endpoint #3 tetap BERBAGI token tulis dengan endpoint #2, bukan token
ketiga.** Alasan §5 (kelas risiko baca vs tulis) masih berlaku utuh — endpoint
#2 dan #3 sama-sama menulis data pelanggan, kelas risikonya sama, jadi gak ada
alasan baru buat token ketiga. Pemisahan yang diminta pemilik produk soal
"evolusi independen" cukup dipenuhi lewat endpoint (URL) dan rate limiter yang
terpisah — token bukan sumbu pemisahan yang relevan di sini.
