# User Flow — Modul Kolektor

Dua pengguna, dua halaman, dua ritme kerja: kolektor bergerak sepanjang hari di lapangan, admin bekerja saat kolektor pulang membawa uang.

---

## A. Kolektor — sehari di lapangan

### A-1. Buka Worklist (`/collector-worklist`)

Yang dilihat pertama:

| Panel | Isi |
|---|---|
| **Saldo Belum Disetor** | uang tagihan yang masih di tangannya + jumlah pembayaran |
| **Kurang Setor** | kewajiban yang belum ditutup — **tidak** ikut nol saat dia setor |
| Daftar setoran berjalan | yang menunggu verifikasi & yang masih kurang setor |
| Tabel tagihan | pelanggan yang sudah masuk jendela tagih (7 hari sebelum jatuh tempo) |

Yang muncul di tabel: pelanggan yang punya **minimal satu** tagihan masuk jendela — dan begitu masuk, **seluruh** tunggakannya ikut tampil. Sekali datang, selesai semua.

### A-2. Menagih → pelanggan bayar

Per baris tersedia nominal, metode, dan tanggal ditagih.

| Cara | Aksi |
|---|---|
| 1-by-1 | ubah nominal bila perlu → tombol **Bayar** pada barisnya |
| Massal | centang beberapa baris → **Bayar Massal (Baris Terpilih)** |

Hasilnya:
- **Lunas** → baris hilang dari daftar, saldo bertambah.
- **Cicilan** → nominal diturunkan; invoice jadi `sebagian`, sisa tetap di daftar, saldo bertambah **sebesar uang yang diterima**.
- Kunjungan hari itu **tercatat otomatis** sebagai `bayar` — kolektor tidak perlu mencatat apa pun lagi.

Kalau gagal, pesan muncul sebagai toast dan barisnya tetap di tempat. **Tekan Bayar lagi aman** — sistem memakai ulang kunci yang sama, jadi tidak akan tercatat dua kali.

Ditolak dengan pesan jelas bila: bukan pelanggannya, di luar POP-nya, invoice sudah lunas/batal, nominal melebihi sisa, atau tanggal ditagih diisi masa depan.

### A-3. Menagih → pelanggan TIDAK bayar

Panel **Catat Kunjungan Tanpa Hasil**:

| Field | Aturan |
|---|---|
| Pelanggan | hanya dari worklist hari itu |
| Hasil | Tidak Ada Orang / Menolak Bayar / Janji Bayar — **"Bayar" tidak ada di sini** |
| Tanggal Janji | wajib bila Janji Bayar; diabaikan untuk hasil lain |
| Catatan | mis. "rumah kosong, tetangga bilang keluar kota" |

Kunjungan hari itu tampil di bawah panel supaya kolektor tahu apa saja yang sudah dilaporkan.

> **Kenapa ini tidak boleh dilewati.** Kunjungan yang tidak menghasilkan uang adalah satu-satunya cara sistem membedakan "pelanggan belum didatangi" dari "didatangi lalu uangnya raib". Kolektor yang rajin mengisi ini justru yang paling terlindungi ketika ada pelanggan mengaku sudah bayar.

Bila pelanggan yang tadi "tidak ada orang" ternyata membayar sore harinya, catatan itu **otomatis berubah** jadi `bayar` — tidak perlu dihapus manual. Sebaliknya, kunjungan `bayar` **tidak bisa** ditimpa jadi "tidak ada orang"; kalau pembayarannya keliru, yang dibatalkan adalah pembayarannya.

### A-4. Pulang → setor ke admin

Tombol **Setor ke Admin** muncul selama saldo > 0. Konfirmasinya menyebut angka:

> "Setorkan seluruh saldo Rp350.000 (4 pembayaran) ke admin? Saldo Anda jadi nol dan menunggu verifikasi."

Setelah dikirim:
- saldo kembali **0**;
- setoran berstatus **Menunggu Verifikasi Admin**;
- admin/pop_admin di POP itu menerima notifikasi;
- **Worksheet Admin yang sedang terbuka langsung berbunyi** (2026-08-11) — toast *"Kolektor Bayu menyetor Rp680.065 (DEP-2026-0004) — menunggu verifikasi"*. Admin tidak perlu memuat ulang untuk tahu ada uang yang menunggu dihitung.

Setor selalu **seluruh** saldo — tidak ada opsi sebagian.

Kalau kolektor masih menagih setelah menyetor, pembayaran itu masuk **saldo baru** dan tidak menggeser angka yang sedang dihitung admin.

### A-4b. Rute berubah saat kolektor di jalan (2026-08-11)

Kalau admin menambah atau melepas pelanggan dari rutenya, kolektor **langsung diberi tahu** — notifikasi in-app + toast kalau Worklist-nya terbuka:

> Budi Santoso dikeluarkan dari rute penagihan Anda — jangan ditagih lagi.

Sebelum ini assign/lepas tidak memberi tahu siapa pun. Kolektor baru tahu saat kebetulan membuka Worklist, dan pelanggan yang dilepas **setelah** dia berangkat berarti dia menagih orang yang bukan lagi tanggungannya — kunjungan sia-sia, dan kalau uangnya terlanjur diterima, uang yang tak punya tempat mendarat di sistem.

Notifikasi in-app sengaja tetap ada di samping toast: kolektor biasanya sedang di jalan, bukan sedang menatap layar. Toast hilang, lonceng bertahan.

### A-5. Menerima hasil verifikasi

Notifikasi berjudul mengikuti statusnya:

| Status | Isi kabar |
|---|---|
| Terverifikasi | uang fisik cocok, setoran ditutup |
| Kurang Setor | nominal selisih + catatan admin |
| Lebih Setor (dikembalikan) | nominal kelebihan, sudah dikembalikan tunai |
| Dihapus Buku | nominal yang dihapus + alasan kantor (2026-08-11) |

Kurang setor tetap tampil di worklist-nya sampai dilunasi.

**Kalau Worklist-nya sedang terbuka, kabarnya sampai seketika** (2026-08-11) — toast *"Setoran DEP-2026-0004 sudah diperiksa kantor — Terverifikasi"*. Sebelumnya kolektor hanya melihat saldonya berubah sendiri tanpa penjelasan apa pun.

**Angkanya ikut berubah otomatis** (2026-08-21, sebelumnya cuma toast + bilah "Muat ulang" manual — dicabut atas permintaan eksplisit user, lihat `business-logic.md` §9). Halaman fetch-ulang dirinya sendiri & menambal isinya begitu kabar masuk, TANPA syarat "skip kalau lagi ada form kebuka" — kalau kolektor lagi ngisi form Kunjungan pas kabar masuk, isian yang belum disimpan bisa ketiban data fresh. Keputusan sadar, bukan bug.

---

## B. Admin — di kantor

### B-1. Worksheet Admin (`/collector-worksheet`)

Dua panel:

| Panel | Isi & aksi |
|---|---|
| Kiri | daftar kolektor + jumlah pelanggan + total tunggakan |
| Kanan | pelanggan **belum punya kolektor** (dalam POP scope admin), bisa dicari & difilter |

Assign: centang pelanggan → pilih kolektor tujuan → **Assign Terpilih**. Ditolak seluruh batch bila ada pelanggan yang POP-nya di luar scope kolektor tujuan, dengan menyebut nama pelanggannya. Kolektor tujuannya **langsung diberi tahu** (2026-08-11) — lihat A-4b.

**Halaman ini bersuara sendiri** (2026-08-11). Selama terbuka, ia menerima kabar seluruh aktivitas kas kolektor di POP dalam scope admin:

| Kejadian | Yang muncul |
|---|---|
| Kolektor mencatat pembayaran | *"Bayu mencatat 4 pembayaran (Rp350.000) — saldonya bertambah."* |
| Kolektor menyetor | *"Bayu menyetor Rp680.065 (DEP-2026-0004) — menunggu verifikasi."* |
| Setoran diverifikasi / dilunasi / dihapus buku | menyebut nomor setoran + status akhirnya |
| Pembayaran ditolak | *"PAY-… milik Bayu ditolak — saldonya berkurang."* |

Semuanya menambal angka di layar secara otomatis (2026-08-21) — fetch-ulang halaman + ganti isinya begitu kabar masuk, gak ada bilah "Muat ulang" lagi. Halaman ini juga sekarang dengar setoran kas **admin sendiri** ke Owner (`App\Events\CashDepositUpdated`) — kalau setorannya diperiksa/ditutup selisih pas Worksheet lagi terbuka, ke-update sendiri.

### B-2. Detail kolektor — 4 tab

| Tab | Untuk apa |
|---|---|
| **Pembayaran** | seluruh tunggakan kolektor ini (tanpa jendela tagih) + bayar mewakili |
| **Setoran** | hitung uang fisik, verifikasi, tangani selisih, hapus buku |
| **Kunjungan** | laporan aging + riwayat kunjungan |
| **Kwitansi** | cetak ber-QR, upload bulk, pantau pencocokan, cocokkan manual |
| **Atur Pelanggan** | assign / lepas pelanggan |

Di atas tab selalu tampil dua angka: **Saldo Belum Disetor** dan **Kurang Setor**.

> Admin yang tidak membawahi seluruh POP jejak uang kolektor akan menerima **403**, bukan halaman dengan data tersaring. Halaman ini menyajikan angka total; total yang disaring diam-diam akan menyesatkan penghitungan uang fisik.

### B-3. Cross check setoran

Saat kolektor menyerahkan uang, admin membuka tab **Setoran** → setoran berstatus Menunggu Verifikasi, lalu:

1. Hitung uang fisik di meja.
2. Isi **Uang Fisik Dihitung**.
3. Bila kolektor sekalian melunasi selisih lama: pilih setoran yang dilunasi + isi **Nominal Pelunasan** (field terpisah).
4. Isi **Catatan** — wajib bila ada selisih.
5. **Verifikasi Setoran**.

Contoh yang benar:

```
Total pembayaran hari ini (sistem)  : 280.000
Pelunasan selisih SETOR-2026-0012   :  30.000
──────────────────────────────────────────────
Diharapkan                          : 310.000
Uang fisik dihitung                 : 310.000
Selisih                             :       0   → Terverifikasi
                                                 SETOR-0012 → Selisih Lunas
```

> **Jangan melebur uang pelunasan ke "Uang Fisik Dihitung".** Hasilnya tercatat sebagai *lebih setor* dan lahir selisih baru yang menggantung, sementara selisih lama tetap terbuka.

Setelah terverifikasi, pembayaran di dalam setoran **tidak bisa ditolak lagi** — konfirmasinya menyebutkan ini sebelum admin menekan tombol.

Begitu verifikasi disimpan, **kolektornya langsung diberi kabar** (2026-08-11): notifikasi in-app + toast realtime kalau Worklist-nya sedang terbuka. Admin lain yang membuka Worksheet kolektor yang sama juga ikut mendengar, sehingga dua admin tidak menghitung setoran yang sudah ditutup orang lain.

### B-4. Kalau uang fisik tidak cocok

| Kondisi | Status | Tindak lanjut |
|---|---|---|
| Fisik **kurang** | **Kurang Setor** | sisa kewajiban tampil di kartu setoran; ditutup lewat pelunasan pada setoran berikutnya, atau hapus buku Owner |
| Fisik **lebih** | **Lebih Setor (dikembalikan)** | uang dikembalikan tunai saat itu juga; status final, tak menyisakan kewajiban |

Keduanya wajib disertai catatan.

### B-5. Hapus buku (Owner)

Hanya untuk setoran **Kurang Setor**, hanya Owner, wajib beralasan, dan tidak boleh dilakukan oleh kolektor yang bersangkutan. Konfirmasinya menyebut nominal dan menegaskan tak bisa dibatalkan.

Ini titik pengakuan kerugian — sengaja dipisah dari kewenangan verifikasi supaya admin yang menemukan selisih tidak menutup temuannya sendiri.

### B-6. Membaca laporan aging (tab Kunjungan)

Tabel per pelanggan tertunggak: tunggakan, kunjungan gagal, total kunjungan, terakhir dikunjungi. Diurutkan dari yang paling sering gagal; **≥3 kunjungan gagal disorot**.

Cara membacanya: satu baris belum tentu berarti apa-apa. Yang layak ditindaklanjuti adalah **pola** — pelanggan yang berulang kali "tidak ada orang" sementara tunggakannya menua. Konfirmasi ke pelanggan adalah langkah berikutnya, bukan menuduh kolektor.

### B-7. Kwitansi (tab Kwitansi)

Alur tiga langkah, dan **tak satu pun menahan verifikasi setoran**:

1. **Cetak.** Centang pembayaran → *Buka Halaman Cetak* → tab baru berisi kwitansi 2 kolom siap print. Tiap kwitansi memuat QR **dan** nomor pembayaran sebagai teks.
   → Yang muncul di daftar **hanya pembayaran yang setorannya sudah diperiksa kantor** dan belum punya kwitansi. Selama uangnya masih di tas kolektor, belum ada dasar menerbitkan bukti. Pembayaran yang **ditolak** juga tak pernah muncul.
   → Kolektor tidak bisa mencetak apa pun; ini murni pekerjaan kantor.
2. **Upload.** Setelah discan/difoto, unggah banyak berkas sekaligus (JPG/PNG/WEBP/PDF, maks 8 MB, maks 100 berkas). Sebelum menekan tombol, ringkasan pilihan muncul (*"12 berkas dipilih · 3,4 MB"*); tombolnya terkunci selama mengirim. Pembacaan berjalan di latar belakang — halaman tidak menunggu.
   → **Simpan PDF hasil Print apa adanya, jangan di-scan ulang.** PDF cetak membawa lapisan teks sehingga seluruh nomor terbaca pasti; lembar yang di-scan jadi gambar kehilangan itu dan berakhir sebagai kerja manual. Satu lembar berisi 8 kwitansi bisa diunggah utuh — sistem mencatatnya jadi 8 baris, satu per pembayaran.
3. **Pantau & rapikan.** **Panel progres** di atas tab menampilkan *"Membaca kwitansi… N berkas tersisa"* dengan progress bar, plus tiga penghitung: Cocok / Antre / Perlu cek. Begitu selesai, toast memberi kabar hasilnya dan halaman menyegarkan diri. Yang **Nomor Tidak Dikenali** atau **Gagal Dibaca** muncul dengan dropdown untuk dicocokkan manual.

| Status | Artinya | Yang perlu dilakukan |
|---|---|---|
| Menunggu Diproses / Sedang Dibaca | antre atau sedang dibaca queue | tunggu — panel progres menghitung sisanya sendiri |
| Cocok | sudah menempel ke pembayaran | — |
| Nomor Tidak Dikenali | nomor terbaca tapi tak ada pembayarannya | periksa — biasanya salah cetak atau salah berkas |
| Gagal Dibaca | tak terbaca sama sekali | cocokkan manual — biasanya kualitas gambar |

Tiap baris punya **dua tautan**, dan bedanya penting:

| Tautan | Isinya |
|---|---|
| **Kwitansi** | kwitansi satuan pelanggan itu, dirender ulang **dari data**. Presisi sempurna, dan statusnya selalu terkini — pembayaran yang kelak ditolak akan tampil "Ditolak" |
| **Lembar asal** | berkas unggahan apa adanya — arsip bahwa kertasnya benar tercetak & diserahkan. Bisa memuat 8 kwitansi sekaligus |

Salah cocok? **Lepas kaitan** mengembalikannya ke antrean pencocokan, dan tindakan itu tercatat di audit log. Berkasnya tetap terikat POP semula — melepas kaitan bukan berarti dokumen itu jadi milik semua cabang.

Berkas yang **belum pernah** tercocokkan hanya terlihat oleh yang mengunggahnya (dan pemegang akses seluruh POP). Kalau rekan sesama admin mengunggah dan tak sempat merapikan, mintalah dia yang menyelesaikan — atau unggah ulang dari berkas aslinya.

> Kwitansi ini arsip **bukti bagi pelanggan**, bukan alat pengawas kolektor. Ia dicetak setelah pembayaran tersimpan, jadi kolektor yang tak melaporkan uang tak pernah mencetak apa pun. Yang mengawasi itu tetap laporan aging di tab Kunjungan.

---

## C. Skenario lengkap — dua hari

**Hari 1**

| Waktu | Pelaku | Kejadian |
|---|---|---|
| pagi | Kolektor | Worklist: 5 pelanggan. Tagih A (150rb lunas), B (100rb dari 250rb, cicil) |
| siang | Kolektor | C tidak ada orang → catat kunjungan. D janji bayar tanggal 12 → catat + tanggal janji |
| siang | Kolektor | Balik ke C, ternyata ada → tagih 100rb. Kunjungan C **otomatis berubah** jadi `bayar` |
| sore | Kolektor | Saldo Rp350.000 → **Setor ke Admin** → saldo 0, status Menunggu Verifikasi |
| sore | Admin | Hitung uang: hanya Rp320.000. Isi declared 320rb + catatan "kolektor mengaku terpakai dulu" → **Kurang Setor Rp30.000** |

**Hari 2**

| Waktu | Pelaku | Kejadian |
|---|---|---|
| pagi | Kolektor | Worklist tetap menampilkan kurang setor Rp30.000 |
| siang | Kolektor | Tagih total Rp280.000 → setor |
| sore | Admin | Uang fisik Rp310.000. Isi declared 310rb + pelunasan Rp30.000 untuk setoran hari 1 |
| sore | Sistem | Setoran hari 2 **Terverifikasi**; setoran hari 1 **Selisih Lunas**; Kurang Setor kolektor kembali **Rp0** |

---

## D. Yang membuat pengguna terhalang (dan kenapa)

| Pesan | Penyebab | Bukan bug |
|---|---|---|
| "Tidak ada pembayaran yang belum disetorkan" | saldo kosong | benar — tak ada yang bisa disetor |
| "Setoran ini sudah diverifikasi sebelumnya" | dua admin membuka setoran yang sama | benar — verifikasi hanya sekali |
| "Anda tidak boleh memverifikasi setoran Anda sendiri" | penonton = penyetor | benar — cross check butuh dua orang |
| "Setoran ini memuat pembayaran di luar scope POP Anda" | setoran lintas POP | benar — yang menutup harus membawahi semuanya |
| "Kunjungan hari itu sudah tercatat sebagai Bayar" | mencoba menimpa jejak pembayaran | benar — batalkan pembayarannya kalau keliru |
| "Kolektor ini masih memegang saldo / kurang setor" | menonaktifkan kolektor bermuatan | benar — uangnya harus pulang dulu |
| Halaman kas kolektor **403** | admin di luar POP jejak uang kolektor | benar — lihat B-2 |
| "Invoice … sudah Batal (berubah sejak form dibuka)" | invoice dibatalkan admin selagi kolektor menagih | benar — pembayaran tak boleh mendarat di tagihan mati |
| "Kwitansi ini belum tercocokkan dan diunggah orang lain" | membuka berkas yatim milik admin lain | benar — lihat B-7 |

Semua konfirmasi dan pesan memakai komponen dialog & alert global aplikasi (`window.Dialog`, `x-ui.alert`, `window.Toast`), bukan `confirm()`/`alert()` bawaan browser.
