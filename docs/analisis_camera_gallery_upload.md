# Analisis Fitur Camera & Gallery Upload

**Konteks:** Upload nota/struk/bukti transaksi pada aplikasi FinanceOps berbasis Laravel, terutama melalui browser di perangkat mobile.

## 1. Ringkasan

Fitur ini memungkinkan pengguna mengunggah gambar melalui dua sumber:

1. **Ambil Foto** — memotret langsung menggunakan kamera bawaan perangkat (native camera).
2. **Pilih dari Galeri** — memilih gambar yang tersimpan di perangkat melalui pemilih gambar/file bawaan sistem operasi.

Istilah yang direkomendasikan:

- **Nama fitur:** Camera & Gallery Upload
- **Nama komponen:** Image Source Picker (`ImageSourcePicker`)
- **Label aksi UI:** **Ambil Foto** dan **Pilih dari Galeri**

> **Catatan:** Pada aplikasi web, membuka kamera atau galeri menggunakan mekanisme bawaan browser/sistem operasi. Tampilan dan opsi yang muncul dapat berbeda antara Android, iOS, dan masing-masing browser.

## 2. Terminologi

| Istilah | Definisi | Kegunaan |
| --- | --- | --- |
| **Image Source Picker** | Komponen untuk memilih sumber gambar | Nama komponen pada UI/Design System |
| **Native Camera Capture** | Mengambil foto menggunakan kamera bawaan perangkat | Aksi **Ambil Foto** |
| **Gallery Image Picker** | Memilih gambar tersimpan pada perangkat | Aksi **Pilih dari Galeri** |
| **Mobile File Picker** | Pemilih file bawaan browser/sistem operasi | Mekanisme pemilihan file |
| **Camera & Gallery Upload** | Kemampuan mengunggah gambar dari kamera atau galeri | Nama fitur pada PRD |

## 3. Rekomendasi UI/UX

Pada area **Upload Bukti Transaksi**, tampilkan dua opsi yang mudah dikenali:

| Aksi | Ikon | Keterangan |
| --- | --- | --- |
| **Ambil Foto** | Kamera | Gunakan kamera HP untuk mengambil foto nota atau struk |
| **Pilih dari Galeri** | Gambar/Galeri | Gunakan foto nota atau struk yang sudah tersimpan |

Alur pengguna yang direkomendasikan:

1. Pengguna membuka halaman **Upload Bukti Transaksi**.
2. Pengguna memilih **Ambil Foto** atau **Pilih dari Galeri**.
3. Browser membuka antarmuka kamera atau pemilih file sesuai pilihan dan dukungan perangkat.
4. Setelah gambar dipilih/diambil, aplikasi menampilkan **preview**.
5. Aplikasi memvalidasi tipe dan ukuran berkas, lalu mengunggahnya ke server.
6. Proses OCR/autofill dapat dilanjutkan seperti alur upload yang sudah ada.

## 4. Implementasi Mobile Web (HTML5)

### 4.1. Pemilihan gambar dari perangkat

```html
<input type="file" accept="image/*">
```

Input ini membatasi pemilihan ke berkas gambar. Bergantung pada browser/perangkat, antarmuka sistem dapat juga menawarkan pilihan untuk mengambil foto.

### 4.2. Meminta penggunaan kamera belakang

```html
<input type="file" accept="image/*" capture="environment">
```

- `accept="image/*"` menyatakan jenis berkas yang diinginkan adalah gambar.
- `capture="environment"` meminta penggunaan kamera yang menghadap ke belakang (jika didukung).
- Nilai lain, `capture="user"`, biasanya meminta kamera depan.

**Batasan penting:** `capture` adalah petunjuk bagi browser; tidak menjamin semua browser langsung membuka kamera belakang atau menampilkan antarmuka yang sama. Perlu uji di Android Chrome dan iOS Safari.

### 4.3. Dua aksi UI terpisah

Untuk antarmuka yang eksplisit, siapkan dua input file yang diaktifkan oleh tombol masing-masing:

```html
<button type="button" onclick="document.getElementById('cameraInput').click()">
  Ambil Foto
</button>
<button type="button" onclick="document.getElementById('galleryInput').click()">
  Pilih dari Galeri
</button>

<input id="cameraInput" type="file" accept="image/*" capture="environment" hidden>
<input id="galleryInput" type="file" accept="image/*" hidden>
```

Contoh di atas menunjukkan konsep UI. Handler JavaScript untuk preview, validasi, upload, dan status kesalahan perlu ditambahkan. **Input tanpa `capture` tidak menjamin hanya galeri yang muncul**, karena browser dapat menyediakan opsi sumber lain.

## 5. Pertimbangan Teknis

- **Validasi ganda:** validasi ukuran dan MIME/type pada browser serta validasi ulang di Laravel; atribut `accept` bukan pengamanan server.
- **Format foto:** perangkat dapat menghasilkan JPEG, PNG, HEIC/HEIF, atau format lain bergantung perangkat dan browser. Tentukan format yang diterima backend serta strategi konversi jika diperlukan.
- **Preview dan koreksi:** tampilkan pratinjau dengan opsi **Ambil Ulang** atau **Ganti Foto** sebelum upload.
- **Izin kamera:** kamera dikelola oleh browser/OS; pengguna dapat menolak izin atau membatalkan pengambilan foto. Tangani pembatalan dengan wajar.
- **Aksesibilitas:** gunakan label tombol yang jelas, status validasi yang terbaca, dan ukuran target sentuh yang nyaman.
- **OCR:** berikan petunjuk singkat agar nota berada dalam bingkai, tidak buram, dan teks terbaca.
- **Konsistensi:** komponen ini bertugas memilih sumber gambar; proses upload dan OCR tetap memakai pipeline FinanceOps yang sudah ada.

## 6. Konvensi Penamaan untuk PRD / Design System

```yaml
feature_name: Camera & Gallery Upload
component_name: ImageSourcePicker
ui_actions:
  - Take Photo        # Ambil Foto
  - Choose from Gallery # Pilih dari Galeri
platforms:
  - Mobile Web
  - Android Browser
  - iOS Safari
capabilities:
  - Native Camera Capture
  - Image Selection
  - Image Preview
  - Upload Validation
```

## 7. Keputusan/Rekomendasi

Gunakan **Camera & Gallery Upload** sebagai **nama fitur** dan **Image Source Picker** sebagai **nama komponen UI**. Pada halaman upload nota FinanceOps, tampilkan dua tindakan eksplisit: **Ambil Foto** dan **Pilih dari Galeri**.

Pendekatan ini tidak memerlukan pembuatan aplikasi kamera tersendiri. Browser menggunakan fasilitas kamera dan pemilih file sistem operasi, dengan perilaku yang tetap perlu diuji pada perangkat target.
