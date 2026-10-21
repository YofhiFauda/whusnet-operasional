# PETA DAN STRUKTUR INFRASTRUKTUR TERBARU

## SUSUNAN DAN HIRARKI TERBARU
### CABANG/POP
 - Kode Infra
 - latitude dan Longtitude
 
### Mini POP
 - Kode Infra
 - Dari Cabang Mana
 - latitude dan Longtitude

### OLT
 - Kode Infra
 - Dari Cabang Mana
 - Dari Mini POP Mana

### SLOT OLT
 - Kode Infra
 - Dari Cabang Mana
 - Dari Mini POP Mana
 - Dari OLT Mana
 - Nama OLT
 - Kapasitas Slot

 ### PON/PORT
 - Kode Infra
 - Dari Cabang Mana
 - Dari Mini POP Mana
 - Dari OLT Mana
 - Nomer Slot
 - Kapasitas Port

### ODP
 - Kode Infra
 - Dari Cabang Mana
 - Dari Mini POP Mana
 - Dari OLT Mana
 - Nomer Slot
 - Nomor PON/PORT
 - Kapasitas ODP

## PEMBAGIAN OLT DAN SWITCH

### PEMBAGIAN OLT
- MINI POP
    - OLT 1
        - SLOT 1
            - PON/PORT 1
                - ODP 1 
                    - PELANGGAN A
                    - PELANGGAN B
                    - PELANGGAN C
                    - PELANGGAN D
                - ODP 2
                    - PELANGGAN A
                    - PELANGGAN B
                - ODP 3
                    - PELANGGAN A
                    - PELANGGAN B
                - ODP 4
                    - PELANGGAN A
                    - PELANGGAN B
                - ODP 5
                    - PELANGGAN A
                    - PELANGGAN B
            - PON/PORT 2
                - ODP 1
                    - PELANGGAN A
                    - PELANGGAN B
                - ODP 2
                    - PELANGGAN A
                    - PELANGGAN B
                - ODP 3
                    - PELANGGAN A
                    - PELANGGAN B
        - SLOT 2
            - PON/PORT 1
                - ODP 1
                - ODP 2
                - ODP 3
            - PON/PORT 2
                - ODP 1
                - ODP 2
                - ODP 3
        - SLOT 3
            - PON/PORT 1
                - ODP 1
                - ODP 2
                - ODP 3
            - PON/PORT 2
                - ODP 1
                - ODP 2
                - ODP 3
            - PON/PORT 2

    - OLT 2
        - SLOT 1
        - SLOT 2


### PEMBAGIAN SWITCH
- MINI POP
    - SWITCH 1
        - SLOT 1
            - PORT 1
            - PORT 2
            - PORT 3
        - SLOT 2
            - PORT 1
        - SLOT 3
            - PORT 1
            - PORT 2
    - SWITCH 2
        - SLOT 1
            - PORT 1
            - PORT 2
            - PORT 3
        - SLOT 2
            - PORT 1
            - PORT 2
            - PORT 3
        - SLOT 3
            - PORT 1
            - PORT 2



## Hirarki Laporan O-REQ DAN INFR-REQ:
Mengganti Lokasi yang dikerjakan  dengan 5 Dropdown.
1. Mini POP
2. OLT
3. SLOT
4. PON/PORT
5. ODP.

Pilihan Dikosongkan (bernilai null) tersedia hanya pada dropdown di bawah tingkat minimum scope task. Jika scope task hanya Mini POP, Mini POP wajib dipilih dan tidak dapat dikosongkan.
Pada hirarki laporan, jika satu dropdown yang boleh dikosongkan bernilai 'Dikosongkan', semua dropdown turunannya otomatis null dan disabled. Minimal satu dropdown lokasi tetap terisi.
Contoh 1:
1. Mini POP = kode mini pop
2. OLT = (memilih Dropdown 'Dikosongkan')
3. SLOT = (otomatis 'Dikosongkan')
4. PON/PORT = (otomatis 'Dikosongkan')
5. ODP. = (otomatis 'Dikosongkan')


Contoh2:
1. Mini POP = kode mini pop
2. OLT = kode olt
3. SLOT = slot ke 1 
4. PON/PORT = (otomatis 'Dikosongkan')
5. ODP. = (otomatis 'Dikosongkan')



## SCOPE dalam Pelaporan Pemasangan Alat Baru. (O-REQ DAN INFR-REQ), Penambahan Node, Task FOP
Contoh kasus SCOPE Cabang/Mini POP atau INfra lain
Alat yang berada di masing masing node infra sudah pasti berbeda beda
jika saya memilih Mini POP = C1 maka akan menampilkan Alat/Perangkat yang hanya berada di C1.
Jika saya memilih Mini POP = C1 dan OLT A maka akan menampilkan Alat/Perangkat yang berada di C1 dan OLT A
jika saya memilih Mini POP = D4 maka akan menampilkan Alat/Perangkat yang hanya berada di D4.

Hal tersebut di lakukan untuk Filtering dari Perangkat agar Implementasi pada Laporan O-Req dan INFR-REQ mudah di lakukan.


## MASTER NODE DAN JARINGAN
Pada Master Node Jaringan & Port  di Penambahan/Edit Node, harus menekan node tersebut lalu pada Node Induk itu harus di Bawah node yang dipilihnya.
Contoh 1:
Saya menekan Node OLT, setelah itu menekan Tambah Node Baru atau Sub Node, maka yang terlihat adalah Perangkat yang berada di OLT tersebut.
Contoh 2:
Jika saya menekan Node Switch, setelah itu menekan Tambah Node Baru atau Sub Node, maka yang terlihat adalah Perangkat yang berada di Switch tersebut.


## TASK FOP (O-REQ dan INFR-REQ)
ketika memilih Task O-REQ atau INFR-REQ maka akan terdapat Dropdown Cabang/ POP, Dropdown Mini POP, dan yang terakhir adalah Dropdown untuk Perangkat/Node/Sub Node yang berada di dalam Mini POP tersebut.
Contoh 1:
Saya memilih Cabang/POP = C1, Mini POP = C1 M1, maka akan menampilkan Alat/Perangkat yang berada di C1 M1 tersebut.
Contoh 2:
Saya memilih Cabang/POP = C1, Mini POP = C1 M1, dan OLT = OLT1, maka akan menampilkan Alat/Perangkat yang berada di C1 M1 OLT1 tersebut.

Pada Task FOP untuk O-REQ dan INFR-REQ, setelah memilih salah satu Cabang/POP, Mini POP, dan Perangkat/Node/Sub Node, maka akan di FILTER data yang akan di masukan kedalam SCOPE, agar tidak terjadi kesalahan pada saat implementasi. jadi pada saat Implementasi di Laporan O-REQ dan INFR-REQ tidak bisa memilih Node yang tidak berada di dalam SCOPE tersebut.



## KEPUTUSAN KLARIFIKASI 20 OKTOBER 2026

1. Jalur Switch hanya sampai Switch → Slot → Port. Pada laporan, dropdown sesudah Port dinonaktifkan dan bernilai null. Jalur OLT tetap Mini POP → OLT → Slot → PON/Port → ODP.
2. «Dikosongkan» adalah pilihan eksplisit bernilai null. Semua dropdown turunannya otomatis null dan disabled. Lokasi akhir pekerjaan adalah tingkat terdalam yang masih terisi: berhenti di Mini POP berarti barang boleh dipasang langsung pada Mini POP; berhenti di OLT berarti barang boleh dipasang langsung pada OLT. Nilai «Belum dipilih» harus dibedakan dari pilihan «Dikosongkan». Jika scope task hanya sampai Mini POP, dropdown Mini POP wajib terisi dan tidak menyediakan «Dikosongkan»; hanya dropdown setelahnya yang boleh null. Semua dropdown lokasi tidak boleh kosong.
3. Scope yang dipilih saat Task FOP dibuat meliputi node terpilih beserta seluruh turunannya. Scope OLT mencakup Slot, PON, dan ODP di bawah OLT itu; scope Mini POP mencakup OLT/Switch dan turunannya. Setiap aksi laporan tetap mencatat lokasi akhirnya sendiri dan server menolak lokasi di luar scope task, cabang, atau izin POP aktor.
4. Aset lama untuk cabut/ganti dipilih menurut lokasi terpasang. Barang baru untuk pasang dipilih dari custody tim serta harus cocok dengan jenis lokasi tujuan. Pemasangan langsung pada Mini POP harus dicatat sebagai lokasi Mini POP, bukan disamarkan sebagai node anak atau stok Gudang.
