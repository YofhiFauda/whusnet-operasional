# Analisa & Rencana Implementasi: Sorting List Task FOP

Berdasarkan permintaan Anda, kita perlu mengatur urutan (*sorting*) dari tabel Task FOP agar lebih cerdas dan relevan bagi operasional di lapangan. Ada 4 indikator utama: Status, Prioritas, SLA, dan Tanggal.

## Analisa Logika Sorting (Urutan)

Untuk mengakomodasi ke-4 indikator tersebut dalam satu *query* database secara bersamaan, kita harus menentukan hierarki (urutan prioritas) dari *sorting* itu sendiri. 

Berikut adalah **usulan hierarki sorting** yang akan diterapkan pada *query* (dari yang paling utama hingga yang paling akhir):

### 1. Berdasarkan Status (Paling Utama)
Semua task yang masih aktif harus berada di atas, yang sudah tidak aktif di bawah.
- **Urutan 1:** `Proses` & `Pending` (Masih berjalan/tertunda)
- **Urutan 2:** `Selesai` & `Cancel` (Sudah dikerjakan/dibatalkan)
*(Catatan: Saat ini sistem FOP Task hanya memiliki status Proses, Pending, Cancel. Nanti kita tambahkan 'Selesai' jika memang dibutuhkan, atau cukup pisahkan yang aktif dan tidak aktif).*

### 2. Berdasarkan Prioritas
Setelah dipisahkan berdasarkan status aktif/tidak aktif, task yang aktif akan diurutkan berdasarkan label prioritasnya.
- **Urutan 1:** `Urgent`
- **Urutan 2:** `High`
- **Urutan 3:** `Medium`
- **Urutan 4:** `low`

### 3. Berdasarkan SLA (Khusus Survey & PSB) vs Tanggal
Ini adalah bagian yang perlu penegasan. Anda menyebutkan:
> "2. susunan Task berdasarkan Tanggal terbaru - terlama"
> "3. Susunan berdasarkan SLA (Khusus Survey dan Pemasangan)"

Secara logika SLA: Task Survey/Pemasangan yang **paling lama** dibuat justru adalah yang **paling mendekati batas waktu (SLA-nya mau habis)**. 
Oleh karena itu, usulan saya untuk *sorting* level 3 & 4 adalah:

- **Jika Task = Survey / PSB (SLA):** Diurutkan dari **Tanggal TERLAMA ke TERBARU** (`created_at ASC`). Tujuannya agar task yang sudah antre paling lama (SLA paling kritis) berada di urutan teratas di dalam kelompok prioritasnya.
- **Jika Task = Kategori Lain (MTN, C-REQ, dll):** Diurutkan dari **Tanggal TERBARU ke TERLAMA** (`created_at DESC`). Tujuannya agar tiket gangguan atau *request* terbaru langsung terlihat di atas.

## Contoh Hasil Pengurutan (Simulasi)
Jika diimplementasikan, urutan baris di tabel Anda akan terlihat seperti ini:
1. [Proses] - [Urgent] - [Survey] - (Dibuat 2 hari lalu) -> *Naik karena SLA kritis*
2. [Proses] - [Urgent] - [PSB] - (Dibuat 1 hari lalu)
3. [Proses] - [Urgent] - [MTN] - (Dibuat 1 jam lalu) -> *Naik karena terbaru*
4. [Proses] - [High] - [Survey] - (Dibuat 12 jam lalu)
5. [Pending] - [Medium] - [C-REQ] - (Dibuat 2 jam lalu)
6. [Cancel] - [low] - [Survey] - (Dibuat 5 hari lalu) -> *Turun ke bawah karena tidak aktif*

## Rencana Kode (Query Builder)

Kita akan mengubah *query* utama di `FopTaskController@index` menjadi seperti ini:

```php
$query = FopTask::with(['village', 'technicians'])
    // 1. Status (Proses/Pending di atas, Cancel di bawah)
    ->orderByRaw("CASE WHEN status IN ('Proses', 'Pending') THEN 1 ELSE 2 END")
    // 2. Prioritas (Urgent -> High -> Medium -> Low)
    ->orderByRaw("CASE priority 
        WHEN 'Urgent' THEN 1 
        WHEN 'High' THEN 2 
        WHEN 'Medium' THEN 3 
        WHEN 'low' THEN 4 
        ELSE 5 END")
    // 3 & 4. SLA & Tanggal
    // Jika Survey/PSB: Urutkan Terlama (ASC) agar yang SLA-nya kritis di atas
    // Jika Lainnya: Urutkan Terbaru (DESC)
    ->orderByRaw("CASE WHEN category IN ('Survey', 'PSB') THEN created_at END ASC")
    ->orderByRaw("CASE WHEN category NOT IN ('Survey', 'PSB') THEN created_at END DESC");
```

## Open Questions (Mohon Konfirmasi)

> [!IMPORTANT]
> 1. Apakah Anda setuju dengan logika pengurutan **Tanggal Terlama di Atas (ASC)** khusus untuk `Survey` & `PSB` agar yang SLA-nya mau habis tampil lebih dulu? (Sedangkan tiket lainnya tetap Tanggal Terbaru (DESC) di atas).
> 2. Apakah di FOP Task perlu saya tambahkan juga status **'Selesai'** ke dalam kode/UI? Saat ini di *dropdown* yang ada di kode *Controller* dan UI (*index.blade.php*) FOP Tasks hanya ada `Proses`, `Pending`, `Cancel`. Jika ditambahkan, Task 'Selesai' akan ditaruh di urutan paling bawah bersama 'Cancel'.
