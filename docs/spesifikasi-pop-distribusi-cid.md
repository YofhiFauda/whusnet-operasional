# Spesifikasi Perbaikan: Cabang/POP, Distribusi, CID & REQ ID

## Tujuan
Memperbaiki modul Cabang/POP, Distribusi, serta logika CID, REQ ID, dan Pelanggan ID pada sistem, sekaligus menyusun aturan migrasi dari database lama. Urutan pengerjaan **wajib** mengikuti urutan berikut karena setiap modul bergantung pada modul sebelumnya:

1. Cabang / POP
2. Distribusi
3. CID, REQ ID, dan Pelanggan ID (termasuk aturan migrasi data lama)

---

## 1. Struktur Hierarki Cabang/POP

| Level | Contoh | Keterangan |
|---|---|---|
| POP Pusat | (nama ISP) | Level tertinggi, merepresentasikan ISP itu sendiri |
| Cabang POP | Jetis | Anak dari POP Pusat |
| Mini POP | C1, C2, C3 | Anak dari Cabang POP |

**Output yang diharapkan:** setiap Cabang POP punya kode unik (mis. huruf), dan setiap Mini POP punya nomor urut yang terikat ke Cabang POP induknya.

⚠️ **Perlu dikonfirmasi:** Bagaimana kode Cabang POP ditentukan (manual saat input, atau auto-generate)? Contoh: apakah "Jetis" → kode `D` ditentukan manual oleh admin?
**Jawab**
Yaaa perlu input Manual

---

## 2. Struktur Distribusi

**Input (form/tabel):**

| No | Kode | Nama Distribusi | Deskripsi | Cabang |
|---|---|---|---|---|

**Output contoh kode:** `X4A`, `X4B`, `X4C`

**Aturan:**
- Setiap Distribusi **wajib** terhubung ke satu Cabang POP (relasi many-to-one).
- ⚠️ **Perlu dikonfirmasi:** apakah kode distribusi (`X4A`, dst) harus unik secara global di seluruh sistem, atau hanya unik di dalam satu Cabang?

- **Jawab**
Harus Unik secara gloval di seluruh sistem dan di tentukan manual oleh admin

---

## 3. Aturan CID dan REQ ID

### 3.1 Alur Status Pelanggan
```
Input Pelanggan → REQ ID dibuat → Registrasi → Pemasangan → Aktivasi → CID dibuat
```

| Status Pelanggan | ID yang Dipakai |
|---|---|
| Baru daftar (belum diproses) | REQ ID |
| Aktif | CID + REQ ID |
| Suspend | CID + REQ ID |
| Terminate | REQ ID format default (lepas dari distribusi) |

### 3.2 Format REQ ID (murni)
```
RQ001296
```
Nomor urut registrasi, prefix `RQ` + nomor urut.

### 3.3 Format CID (status Aktif/Suspend, masih masuk Distribusi)
```
D2X6CRQ001296_MANGKUJAYAN_DYAHGALUH

D        = Kode Cabang POP
2        = Nomor Mini POP
X6C      = Kode Distribusi
RQ001296 = REQ ID
_MANGKUJAYAN_DYAHGALUH = ⚠️ perlu dikonfirmasi: apakah ini kelurahan + nama pelanggan? 
**Jawab**
kelurahan/Desa + Nama Pelanggan
```

### 3.4 Format ID saat Aktifasi namun belum ada distribusi
```
C00RQ001296

C   = Kode Cabang POP
00  = Default (sudah tidak masuk Mini POP/Distribusi)
RQ001296 = REQ ID
```

### 3.4 Format ID saat Terminate
```
RQ001296

RQ001296 = REQ ID
```


🔴 **Inkonsistensi pada draft awal yang perlu diluruskan:**
Contoh awal menyebut "saat input pelanggan, REQ ID = `C00RQ000021`" — padahal format ini sama persis dengan format ID **Terminate** (`Cabang + 00 + REQID`), bukan REQ ID murni (`RQ000021`). Perlu dipastikan: apakah saat pelanggan baru pertama kali input (belum ada distribusi), ID yang ditampilkan memang `RQ000021` saja, dan format `C00RQ000021` hanya muncul ketika berstatus Terminate?

**Jawab** Pelanggan Baru yang baru di registrasi, survey, pemasangan itu masih akan menggunakan REQ ID murni = RQ000021, ketika di aktifasi maka akan di masukan ke distribusi dan akan di buatkan CID = D2X6CRQ000021_MANGKUJAYAN_DYAHGALUH namun ketika sudah di aktifasi namun belum di berikan distribusi maka akan default menjadi C00RQ000021. ketika terminate maka akan di ganti menjadi RQ000021 = sama seperti waktu regitrasi

---

## 4. Aturan Migrasi dari Database Lama

🔴 **Prinsip wajib:** Skema Cabang/POP, Mini POP, dan Distribusi yang dipakai untuk migrasi **adalah skema yang sudah ada dan sudah didefinisikan pada Bagian 1 & 2 di atas** (bukan tabel/struktur baru, bukan kolom tambahan ala "legacy"). Migrasi ini **murni proses mapping** dari kolom-kolom tabel lama ke struktur Cabang/POP, Mini POP, Distribusi, REQ ID, dan CID yang sudah berjalan saat ini.

Implikasinya:
- **Jangan** membuat tabel/kolom tambahan khusus untuk menampung data migrasi (mis. `legacy_id`, `old_distribusi`, dsb). Itu berisiko merusak/menduplikasi data yang sudah ada.
- **Jangan** membuat ulang master data Cabang POP/Mini POP/Distribusi dari data lama — gunakan master data yang sekarang sudah ada di sistem, lalu cocokkan (match) nilai dari kolom lama ke ID master data yang sesuai.
- Jika ada kode dari data lama (`KODEAPP`, `kategori_perangkat_jaringan`, `kode_kontrol_distribusi`) yang **tidak ditemukan padanannya** di master data saat ini, perlakukan sebagai data exception/perlu review manual — jangan auto-insert sebagai master data baru.

**Sumber:** tabel `prosedure_permintaan_wifi`

| Kolom Lama | Dipetakan Menjadi |
|---|---|
| `KODEAPP` | Kode Cabang POP (contoh: `C`) |
| `kategori_perangkat_jaringan` | Nomor Mini POP |
| `kode_kontrol_distribusi` | Kode Distribusi (contoh: `X4A`); bernilai `'0'` jika pelanggan tidak/belum masuk distribusi |
| `IDPERMINTAAN` | REQ ID (contoh: `RQ000472`) |
| `STATUS` | Status pelanggan (`ACTIVE`, `GAGAL`, dst) |

**Logika konversi:**

```
JIKA STATUS = Terminate/Gagal  ATAU  kode_kontrol_distribusi = '0'
    => Pelanggan_ID = {IDPERMINTAAN}
       Contoh: RQ000472


JIKA STATUS = Active/Suspend  (tidak masuk distribusi)
    => Pelanggan_ID = {KODEAPP} + "00" + {IDPERMINTAAN}
       Contoh (baris 2): C00RQ000473


JIKA STATUS = Active/Suspend  (masih masuk distribusi)
    => Pelanggan_ID = {KODEAPP} + {kategori_perangkat_jaringan} + {kode_kontrol_distribusi} + {IDPERMINTAAN}
       Contoh (baris 2): C1X4DRQ000473
```

---

## 5. Checklist Konfirmasi Sebelum Implementasi
- [ ] Aturan penentuan kode huruf Cabang POP (manual/auto)
- [ ] Sumber data bagian `_KELURAHAN_NAMA` pada CID
- [ ] Konsistensi REQ ID murni vs format gabungan saat input pelanggan pertama kali
- [ ] Keunikan kode Distribusi: global atau per-cabang
- [ ] Perilaku CID saat pelanggan suspend lalu aktif kembali (apakah CID tetap sama atau regenerate)
- [ ] Perilaku jika pelanggan terminate lalu aktif kembali (apakah dapat REQ ID baru atau pakai yang lama)
