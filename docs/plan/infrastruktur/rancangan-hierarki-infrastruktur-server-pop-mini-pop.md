# Rancangan Hierarki Infrastruktur Server–POP–Mini POP

Tanggal: 20 Oktober 2026  
Status: rancangan untuk ditinjau; belum menjadi task implementasi.  
Ruang lingkup: Master Node, Peta Topologi, slot/port, relasi pelanggan, dan aset Gudang.  
Klasifikasi: infrastruktur post-MVP. Sprint aktif tetap S8.10-T003 (FOP Notification Dashboard).

## 1. Tujuan

Master Node dan Peta harus memperlihatkan jalur infrastruktur dengan urutan yang sama. Setiap tingkat menjelaskan lokasi, perangkat, slot, port, distribusi, atau perangkat pelanggan. Perubahan struktur kelak harus mempertahankan pelanggan, stok, riwayat pekerjaan, dan batas akses POP.

~~~text
Server Utama
└── POP
    └── Mini POP
        ├── OLT
        │   └── Slot
        │       └── PON
        │           └── ODP
        │               └── ONT pelanggan
        └── Switch
            └── Slot
                └── Port
~~~

POP mencakup POP Pusat dan POP Cabang yang sudah ada. Bila keduanya berlaku, tampilkan Server Utama → POP Pusat → POP Cabang → Mini POP. Satu Mini POP boleh mempunyai banyak OLT dan Switch. Jalur Switch berhenti pada Port dalam susunan utama ini; sambungan sesudah Port dicatat sebagai koneksi fisik tersendiri jika ada.

## 2. Arti dan sumber data setiap tingkat

| Tingkat | Arti | Sumber kebenaran yang dituju |
|---|---|---|
| Server Utama | Titik sumber jaringan | Node server; barang fisik dari ledger Gudang |
| POP dan Mini POP | Wilayah/lokasi, dasar POP scope | Relasi parent pada tabel pops; uplink menunjukkan sumber jaringan |
| OLT dan Switch | Posisi perangkat yang dapat berganti unit fisik | Network node; SN/model dari aset Gudang terpasang |
| Slot | Posisi di dalam OLT/Switch | Satu model slot perangkat, nomor unik per perangkat |
| PON | Antarmuka keluaran OLT pada slot tertentu | Port bertipe PON milik slot OLT |
| Port Switch | Antarmuka keluaran Switch | Port milik slot Switch |
| ODP | Titik distribusi berlokasi dan berport | Network node ODP, ditautkan ke PON terverifikasi |
| ONT pelanggan | Perangkat pelanggan pada port ODP | Assignment pelanggan dan data perangkat/serial INSTALLED; bukan network node |

Pohon yang terlihat merupakan proyeksi beberapa relasi, bukan satu tabel pohon tunggal. POP/Mini POP tetap lokasi, slot dan port tetap bagian perangkat, serta ONT tetap milik pelanggan. Posisi jaringan tidak berganti identitas ketika barang fisiknya diganti.

### Keputusan rinci

- OLT pada rancangan ini memetakan tipe olt_chassis yang ada. Kartu OLT lama dipetakan ke isi slot saat transisi; satu slot hanya memuat satu kartu aktif.
- Switch operasional pada struktur baru hanya berada di Mini POP dan menjadi cabang sejajar OLT. Tipe aggregation_switch adalah representasi lama: data dummy yang diverifikasi boleh direset, sedangkan data nyata yang dipertahankan harus dipetakan ke Mini POP yang benar sebelum diubah.
- Switch modular memakai nomor slot fisik. Switch port tetap memakai Slot 1 berlabel «slot internal/logis», agar urutan Switch → Slot → Port tetap konsisten tanpa mengesankan ada kartu yang bisa dicabut.
- PON mempunyai identitas lengkap OLT / Slot / nomor PON. Satu PON dapat melayani beberapa ODP sesuai kapasitas yang dicatat. ODP dasar mempunyai satu uplink PON aktif yang terverifikasi.
- Satu port ODP hanya boleh mempunyai satu assignment pelanggan aktif. ONT ditampilkan sebagai daun visual bila assignment aktif dan perangkat tersedia. Bila belum ada SN, tampilkan pelanggan dengan label «ONT belum tercatat».
- Beberapa ONT terpasang milik satu pelanggan harus terlihat semua. Detail SN hanya terlihat bila aktor punya izin data perangkat sensitif.
- OLT/Switch yang saat ini langsung berlokasi di POP Cabang masuk daftar verifikasi. Jangan membuat Mini POP fiktif atau memindahkan lokasi otomatis.

## 3. Hubungan dengan struktur yang ada

| Saat ini | Arah rancangan | Aturan transisi |
|---|---|---|
| pops sudah membentuk Pusat → Cabang → Mini POP | Pertahankan sebagai lokasi/scope | Jangan membuat node POP duplikat |
| network_nodes memakai parent_id atau anchor_pop_id | Pertahankan untuk node fisik | Jangan memindah parent/anchor sebelum referensi baru diverifikasi |
| olt_slots menghubungkan chassis dan kartu | Jadikan sumber migrasi slot perangkat yang konsisten | Audit slot/kartu ganda atau yatim; simpan ID dan histori lama |
| olt_card dan pon_port dapat menjadi node | Kartu menjadi isi slot; PON menjadi port pada slot | Untuk data nyata gunakan pemetaan eksplisit; data topologi dummy yang terverifikasi boleh direset sesuai §16.4 |
| network_node_ports dan network_port_assignments menyimpan port/pemakaian | Bedakan port, koneksi antar-port, dan penghuni pelanggan | Jaga assignment aktif dan riwayat pelepasan |
| Seeder lama memuat Backbone, Agregasi, Feeder, Drop Cable, ODC | Tinjau sebagai representasi lama; Switch operasional baru hanya berada di Mini POP | Ganti seeder dummy dengan struktur baru; petakan hanya relasi data nyata yang harus dipertahankan |
| ONT tersimpan pada data pelanggan | Tetap menjadi perangkat pelanggan | Tampilkan melalui assignment ODP yang sah |

Parent node menjawab «berada di bawah posisi apa»; koneksi port menjawab «tersambung ke mana». Keduanya perlu simbol/garis berbeda di Peta. Server yang saat ini di-anchor ke POP Pusat dapat tampil di atas POP sebagai proyeksi, tanpa membuat siklus parent di database.

## 4. Integritas, stok, dan keamanan

1. OLT/Switch harus punya satu lokasi POP/Mini POP yang sah. Slot punya satu perangkat induk dan nomor unik di dalamnya.
2. PON hanya milik slot OLT; Port Switch hanya milik slot Switch. Nomor port unik dalam slot.
3. ODP → PON serta pelanggan → port ODP menggunakan referensi ID terverifikasi, bukan kecocokan teks kode atau SN.
4. Status «belum didata», «kosong terverifikasi», «terpakai terverifikasi», «terpakai belum terverifikasi», dan «rusak/nonaktif» dibedakan. Kapasitas kosong tidak boleh menghitung port yang belum didata.
5. Node, slot, atau port yang mempunyai koneksi/aset aktif tidak boleh dihapus atau dipindah lintas cabang tanpa rekonsiliasi. Histori assignment, transaksi, dan audit tetap tersimpan.
6. Issue barang ke teknisi belum berarti terpasang. Ledger deploy/cabut Gudang tetap sumber bukti barang pada Mini POP, node, atau pelanggan; SN tidak menjadi identitas permanen node.
7. Master Node, Peta, inventaris, pencarian, detail, breadcrumb, dan ekspor harus membatasi data dengan POP scope aktor. Server pusat dan uplink lintas POP hanya memperlihatkan rincian sesuai izin.
8. Perubahan node, koneksi, slot, dan aset tetap memakai permission fungsi dan audit log. SN ONT memakai izin data pelanggan sensitif.

## 5. Tampilan operasional

### Master Node

Navigasi utama mengikuti hierarki pada bagian 1. POP/Mini POP diberi penanda lokasi, OLT/Switch/ODP diberi penanda node fisik, slot/port menampilkan nomor, dan ONT menampilkan pelanggan. Klik unsur membuka detail tanpa mengubah status. Detail berisi breadcrumb, status verifikasi, koneksi, kapasitas, aset terpasang, dan riwayat task.

Aksi tambah mengikuti konteks: di Mini POP tambah OLT/Switch; di perangkat tambah slot; di slot OLT tambah PON; di PON hubungkan ODP; di port ODP tempatkan pelanggan. Pencarian/filter mempertahankan leluhur hasil supaya jalur tetap terbaca, tanpa memuat node cabang di luar scope.

### Peta dan Inventaris

Tab Topologi memakai hierarki yang sama dengan Master Node. Garis lokasi/naungan dibedakan dari garis koneksi kabel. Slot, PON, port, dan ONT tidak membuat pin koordinat sendiri; semuanya muncul pada panel perangkat atau ODP terkait. Koneksi yang belum diverifikasi ditandai jelas.

Tab Inventaris menunjukkan barang, SN/lot/qty, lokasi terpasang (Mini POP atau node), teknisi, task, dan transaksi asal. Posisi slot/port kosong tetap dapat ada tanpa aset. Penggantian perangkat mengubah aset terpasang dan ledger, bukan menghapus titik topologi.

## 6. Alur contoh

1. Admin memilih Mini POP dalam scope dan mencatat OLT atau Switch sebagai posisi.
2. Operator mencatat slot dan port, lalu menautkan aset Gudang melalui laporan pemasangan infrastruktur yang valid.
3. Pada jalur OLT, operator menghubungkan ODP ke PON dan menempatkan pelanggan pada port ODP; ONT dibaca dari data perangkat pelanggan.
4. Pada jalur Switch, operator melihat Slot → Port dan mencatat tujuan port bila sambungan fisiknya diketahui. Tujuan tidak otomatis menjadi ODP.
5. Saat barang diganti atau dilepas, posisi dan riwayat tetap ada. Ledger dan assignment menunjukkan keadaan terbaru.

## 7. Rencana transisi saat kelak diimplementasikan

1. Buat inventaris baca saja per cabang: POP, Mini POP, OLT, Switch, kartu, slot, PON, ODP, port, assignment, dan serial; catat lokasi/parent ambigu.
2. Tetapkan satu pemilik data slot/port dan kontrak koneksi. Migrasi awal harus aditif dan dapat rollback; jangan drop tabel/kolom lama.
3. Backfill hanya hubungan yang terbukti melalui FK dan nomor unik. Kasus ambigu masuk daftar review manual dengan ID asal, POP, alasan, dan keputusan operator.
4. Bangun satu proyeksi hierarki berscope untuk Master Node dan Peta. Bandingkan jumlah ODP, pelanggan terhubung, port aktif, dan aset per POP dengan data lama.
5. Alihkan jalur tulis bertahap: node/slot/port, assignment, laporan O-REQ/INFR-REQ, Gudang, dan instalasi pelanggan. Hindari dua sumber kebenaran aktif tanpa sinkronisasi teruji.
6. Hentikan representasi node PON/kartu lama setelah seluruh rujukan, seeder, laporan, dan audit memakai pemetaan baru. Riwayat tetap dapat dibaca.

Urutan ini adalah rencana desain, bukan izin menjalankan migrasi pada sprint aktif.

## 8. Kriteria penerimaan implementasi kelak

- Master Node dan Peta menampilkan Server → POP → Mini POP, lalu kedua cabang OLT → Slot → PON → ODP → ONT dan Switch → Slot → Port.
- POP/Mini POP tidak menjadi network node duplikat; ONT pelanggan tidak menjadi network node.
- Slot/port unik; satu port ODP tidak menampung dua pelanggan aktif; data legacy ambigu tidak disambungkan otomatis.
- Jalur ODP/ONT memiliki referensi terverifikasi atau label jelas bahwa koneksi belum diverifikasi.
- Jumlah pelanggan terhubung, port aktif, serta aset per POP cocok dengan data sebelum transisi; selisih tercatat untuk review.
- Pengguna berscope cabang tidak dapat melihat node, port, SN, breadcrumb, atau ekspor cabang lain.
- Penggantian/pemindahan aset menjaga ledger Gudang dan audit. Tidak ada riwayat yang hilang.
- Tes kelak mencakup dua cabang, banyak OLT/Switch per Mini POP, Switch port tetap, slot kosong, ODP banyak pelanggan, ONT tanpa SN, konflik port, dan data legacy ambigu.

## 9. Batas pekerjaan dan rujukan

Komponen yang perlu dinilai saat implementasi: pops, network_nodes, tipe/aturan node, olt_slots, network_node_ports, network_port_assignments, Task FOP, laporan O-REQ/INFR-REQ, InventoryService, InfrastructureMapService, Master Node, Peta, seeder topologi, instalasi pelanggan, POP scope, dan ledger Gudang. Ini bukan instruksi untuk mengubahnya sekaligus.

Billing, invoice, payment, role matrix, dan notifikasi FOP tetap mengikuti alur yang ada. Integrasi otomatis router/OLT, SNMP, provisioning, dan monitoring jaringan tidak termasuk rancangan.

Acuan: docs/TASKS.md; docs/PROJECT_CONTEXT.md; docs/MVP_SCOPE.md; docs/DATABASE_CONCEPT.md; docs/Website_Billing_ISP_PRD.md; docs/plan/infrastruktur/analisa-tumpang-tindih-konsep-network-node.md; docs/plan/infrastruktur/analisa-simplifikasi-master-node-dan-port-visual.md; docs/plan/infrastruktur/rancangan-operasional-infrastruktur-dinamis.md; docs/plan/infrastruktur/rancangan-modem-di-odp-dan-pohon-master-node.md.

## 10. Aturan operasional yang sudah diputuskan

Bagian 1–9 menjelaskan struktur dan transisi. Bagian 10–15 adalah kontrak operasional dari penjelasan-infrastruktur-baru.md dan klarifikasi user pada 20 Oktober 2026. Bila contoh lama bertentangan dengan aturan di bawah, aturan di bawah yang berlaku untuk rancangan ini.

### 10.1 Lokasi minimum, «Dikosongkan», dan lokasi akhir

- Task FOP O-REQ/INFR-REQ menentukan akar scope pekerjaan. Pada form laporan, tingkat yang menjadi akar scope adalah pilihan minimum yang wajib terisi. Bila akar scope Mini POP C1-M1, dropdown Mini POP berisi C1-M1 dan tidak menyediakan «Dikosongkan». Bila akar scope Cabang/POP dan pekerjaan kabel berada langsung di POP, POP wajib terisi sementara Mini POP boleh «Dikosongkan».
- Dropdown setelah akar scope boleh memiliki pilihan eksplisit «Dikosongkan» bernilai null. «Belum dipilih» adalah keadaan berbeda dan tidak boleh otomatis dianggap permintaan pemasangan pada induk.
- Begitu suatu tingkat dipilih «Dikosongkan», seluruh dropdown turunannya menjadi null dan disabled. Server harus menolak atau mengabaikan nilai turunan yang dipaksakan melalui request, kemudian menentukan lokasi akhir dari tingkat terdalam yang masih terisi.
- Semua dropdown lokasi, termasuk POP yang menjadi akar scope, null tidak valid. Paling sedikit akar scope task harus tetap terpilih pada setiap aksi yang membutuhkan lokasi.
- Nilai null pada anak berarti «lokasi aksi berhenti di induk», bukan «barang tidak punya lokasi». Jika tindakan adalah pemasangan aset berserial yang kompatibel, OLT null setelah Mini POP C1-M1 memasang aset langsung di Mini POP C1-M1; Slot null setelah OLT A memasang aset langsung di OLT A. Jika tindakan adalah pemakaian kabel, pilihan yang sama hanya menunjukkan lokasi pekerjaan, bukan saldo kabel terpasang.

| Scope dan pilihan laporan | Lokasi aksi; juga lokasi aset bila pemasangan aset berserial kompatibel |
|---|---|
| Mini POP C1-M1; OLT/Switch = Dikosongkan | Mini POP C1-M1 |
| Mini POP C1-M1; OLT A; Slot = Dikosongkan | OLT A |
| Mini POP C1-M1; OLT A; Slot 1; PON = Dikosongkan | Slot 1 pada OLT A |
| Mini POP C1-M1; Switch B; Slot 2; Port 4 | Port 4 pada Slot 2 Switch B |
| Scope POP Cabang C1; POP C1 terisi; Mini POP = Dikosongkan; aksi pemakaian kabel | POP C1 sebagai lokasi pekerjaan; latitude/longitude dapat dicatat |
| POP/akar scope = null; semua berikutnya = null | Ditolak; tidak ada lokasi aksi |

Contoh ini menentukan **Lokasi yang dikerjakan**, yaitu tingkat terdalam yang terisi dalam subtree task, untuk setiap aksi termasuk pemakaian kabel. Untuk aset berserial yang dipasang, lokasi aset terpasang menunjuk posisi tepat dari aksi itu (Mini POP, OLT/Switch/ODP, Slot, atau PON/Port) bila jenis barang kompatibel. Contoh: kartu berserial di Slot 1 tersimpan pada ID Slot 1, bukan hanya ID OLT induknya. Untuk kabel, pilihan terdalam tetap dicatat sebagai lokasi aksi, sedangkan POP/Mini POP adalah area induknya dan latitude/longitude dapat menandai titik fisik pekerjaan. Ujung sumber/tujuan sambungan dicatat terpisah dari lokasi aksi. Pemakaian kabel hanya mengurangi saldo sisa roll; tidak membuat saldo kabel terpasang pada lokasi aksi atau ujung sambungan. Koordinat tidak menggantikan ID lokasi atau pemeriksaan scope. Pemeriksaan kantor O-REQ tanpa tindakan jaringan dapat memakai alur tanpa lokasi sesuai aturan O-REQ yang sudah ada; aturan minimum lokasi berlaku saat aksi mempunyai target jaringan atau pemasangan barang.

### 10.2 Dropdown laporan O-REQ/INFR-REQ

Cabang/POP dari task menjadi konteks sebelum dropdown lokasi. Lima tingkat awal pada penjelasan user berlaku untuk cabang OLT. Setelah Mini POP, pilihan perangkat bercabang:

~~~text
Cabang/POP task → Mini POP → OLT    → Slot → PON/Port → ODP
                            ↘ Switch → Slot → Port
~~~

- Mini POP hanya menampilkan lokasi dalam Cabang/POP task dan POP scope aktor.
- OLT/Switch hanya menampilkan perangkat dalam Mini POP terpilih. Slot hanya milik perangkat terpilih; PON/Port hanya milik slot terpilih; ODP hanya milik PON terpilih.
- Pada jalur Switch, hierarki berhenti di Port. Dropdown ODP dan tingkat setelah Port disabled serta null; server tidak menerima ODP palsu di bawah Switch.
- Perubahan induk menghapus semua pilihan anak sebelumnya, termasuk SN lama, port, dan referensi koneksi yang bergantung pada lokasi lama.
- Jika aksi laporan jamak, setiap baris mempunyai lokasi akhir sendiri. Scope task sama untuk semua baris, tetapi operator dapat bekerja pada beberapa turunan yang berbeda dalam scope itu.
- Pilihan yang ditampilkan UI dan validasi server harus memakai aturan relasi yang sama; ID yang sah secara global tetapi bukan anak induk terpilih harus ditolak.

### 10.3 Scope Task FOP adalah batas subtree

Pada pembuatan Task FOP O-REQ/INFR-REQ, FOP memilih Cabang/POP; Mini POP dan perangkat/node/subnode dipilih bila pekerjaan memang berada di tingkat tersebut. Pilihan terdalam yang terisi menjadi akar subtree pekerjaan. Ini adalah scope task, bukan lokasi pemasangan otomatis bagi setiap barang. Jika pekerjaan kabel langsung di POP, akar scope dapat berhenti pada POP; Mini POP boleh null tanpa membuat semua dropdown kosong.

| Akar scope task | Aksi laporan yang diizinkan | Aksi yang ditolak |
|---|---|---|
| POP Cabang C1 | POP itu, seluruh Mini POP dan turunan dalam Cabang C1 sesuai jenis tindakan | POP Cabang lain |
| Mini POP C1-M1 | Mini POP itu, OLT/Switch di dalamnya, dan seluruh turunannya | Mini POP C1-M2 atau cabang lain |
| OLT A | OLT A dan seluruh Slot, PON, ODP turunannya | OLT B, Switch saudara, atau Mini POP induk sebagai lokasi aksi |
| Slot 1 OLT A | Slot 1 dan PON/ODP turunannya | Slot 2 OLT A |
| Switch B | Switch B dan seluruh Slot/Port turunannya | OLT atau Switch lain |

Lokasi aksi yang sah adalah irisan: izin POP aktor ∩ Cabang/POP task ∩ subtree akar scope task. Pemeriksaan ini wajib di server saat laporan disimpan; filter browser hanya membantu pengguna. Semua lokasi dalam baris aksi harus dicek, termasuk sumber/tujuan sambungan, SN lama, port, dan lokasi koreksi material. Mengubah akar scope task setelah penugasan memerlukan audit dan tidak boleh mengubah tafsir laporan yang sudah tersimpan.

### 10.4 Filter perangkat berbeda menurut tindakan

Tiga daftar yang terlihat mirip mempunyai sumber kebenaran berbeda:

1. **Lokasi yang boleh dipilih:** subtree scope Task FOP, dibatasi Cabang task dan POP scope aktor.
2. **Barang/SN lama untuk cabut atau ganti:** aset yang benar-benar berstatus terpasang pada lokasi akhir aksi. Jangan menawarkan SN OLT B ketika aksi pada OLT A, walau keduanya di Mini POP yang sama.
3. **Barang/SN baru untuk pasang:** barang yang sudah di-issue dan masih berada dalam custody tim task, dengan kategori yang cocok bagi lokasi akhir. Barang ini belum berada di Mini POP/OLT tujuan sebelum laporan disimpan.

Saat Mini POP C1-M1 dipilih, antarmuka dapat menunjukkan aset yang terpasang langsung pada Mini POP dan ringkasan aset turunannya dalam kelompok terpisah. Saat OLT A dipilih, breadcrumb tetap menunjukkan Mini POP C1-M1; aset langsung pada OLT A ditampilkan sebagai milik OLT A. Daftar SN lama yang dapat dicabut selalu mengikuti lokasi aksi yang tepat, bukan seluruh ringkasan subtree. Ini menjaga contoh filter perangkat dalam penjelasan user tanpa mencampur kepemilikan lokasi.

## 11. Pemasangan langsung pada Mini POP: keadaan dan celah saat ini

### 11.1 Hasil bisnis yang diwajibkan

Contoh: Task FOP Cabang C1 berscope Mini POP C1-M1; teknisi membawa aset infrastruktur SN-123; pada laporan Mini POP C1-M1 terisi dan OLT dipilih «Dikosongkan». Setelah laporan sah:

- SN-123 berstatus terpasang, bukan stok Gudang dan bukan lagi custody teknisi.
- Lokasi terpasang SN-123 adalah Mini POP C1-M1, tanpa OLT/slot/port tujuan.
- Ledger mencatat asal teknisi, task, waktu, dan lokasi akhir Mini POP C1-M1; pencabutan atau perpindahan kelak merujuk lokasi yang sama.
- Inventaris Mini POP menampilkan SN-123 sebagai aset langsung Mini POP, berbeda dari aset pada OLT anak.

~~~text
Gudang C1 (tersedia)
→ Issue ke teknisi (dibawa; belum terpasang)
→ Laporan pemasangan di Mini POP C1-M1
→ DEPLOYED pada Mini POP C1-M1
~~~

### 11.2 Mengapa jalur kode sekarang belum mencukupi

Mini POP disimpan sebagai POP, sedangkan OLT/Switch/ODP adalah network node. Alur aksi infrastruktur saat ini memerlukan network_node_id untuk pasang/ganti/cabut. InventoryService::deploySerial() menerima NetworkNode, mengisi inventory_serials.current_network_node_id, dan menulis inventory_transactions.to_network_node_id. Deploy qty/roll dan infrastructure_balances juga berorientasi node.

Kolom to_pop_id pada transaksi deploy node sekarang adalah konteks cabang hasil resolvePop(), **bukan bukti bahwa barang dipasang langsung pada Mini POP**. Kolom inventory_serials.current_pop_id digunakan saat barang berada di Gudang; memakainya begitu saja untuk aset DEPLOYED akan membuat keadaan Gudang dan pemasangan Mini POP sulit dibedakan. Dengan demikian, mengirim null network_node_id dari dropdown belum membuat deploy Mini POP bekerja.

Kontrak data implementasi kelak harus menyimpan **jenis lokasi akhir + ID lokasi akhir** untuk aset terpasang dan ledger. Minimal, «POP/Mini POP terpasang» harus dapat dibedakan dari «network node terpasang» dan dari «POP Gudang/konteks cabang». Hanya satu tujuan pemasangan aktif untuk satu SN. Khusus kabel, saldo laporan adalah **sisa kabel**; pemakaian mengurangi sisa tanpa membuat saldo kabel terpasang di lokasi pemakaian. Harga kabel ditetapkan saat pencatatan awal, bukan dihitung ulang untuk setiap pemakaian. Lokasi aksi kabel tetap mengikuti pilihan terdalam di laporan, POP/Mini POP diturunkan sebagai area induk, dan koordinat latitude/longitude dapat menandai titik fisik. Bila kabel menghubungkan dua titik, ujung sumber/tujuan dicatat sebagai relasi koneksi tersendiri. Cabut/koreksi aset berserial serta aturan saldo material selain kabel tetap mengikuti jenis lokasi masing-masing. Bentuk kolom/migrasi diputuskan sesudah audit skema dan data; jangan merekayasa node Mini POP fiktif sebagai jalan pintas.

## 12. Master Node, identitas, dan kapasitas

| Tingkat | Data yang diminta pada penjelasan user | Cara penyajian dalam rancangan |
|---|---|---|
| Cabang/POP | Kode infra, latitude, longitude | Disimpan pada POP; koordinat menunjukkan lokasi cabang |
| Mini POP | Kode infra, Cabang asal, latitude, longitude | Parent Cabang berupa relasi, bukan teks bebas |
| OLT / Switch | Kode infra, Cabang, Mini POP | Lokasi utama adalah Mini POP; Cabang diturunkan dari parent Mini POP |
| Slot OLT / Switch | Kode infra, perangkat induk, nama perangkat, nomor slot, kapasitas | Induk dan namanya diambil dari OLT/Switch; nomor slot unik dalam perangkat; kapasitas diberi satuan |
| PON / Port | Kode infra, Cabang, Mini POP, OLT/Switch, nomor slot, nomor port, kapasitas | Jalur leluhur diturunkan dari relasi; nomor unik dalam slot; kapasitas tidak dicampur dengan kapasitas ODP |
| ODP | Kode infra, jalur Cabang–Mini POP–OLT–Slot–PON, kapasitas ODP | Satu uplink PON aktif terverifikasi; kapasitas ODP dinyatakan sebagai jumlah port pelanggan |
| ONT pelanggan | Pelanggan pada port ODP, identitas perangkat bila tercatat | Dibaca dari assignment dan data perangkat pelanggan; bukan node baru |

Kode infra yang ditampilkan pada setiap tingkat harus dapat ditelusuri ke ID yang stabil. Nama OLT dan jalur Cabang/Mini POP pada slot, PON, dan ODP diturunkan dari relasi induk agar perubahan nama tidak meninggalkan salinan teks yang bertentangan.

- Pada Master Node, memilih suatu induk lalu menekan «Tambah Node/Sub Node» mengisi konteks induk tersebut dan hanya menawarkan jenis anak yang sah. Memilih OLT menampilkan perangkat/anak OLT itu; memilih Switch menampilkan anak Switch itu. Edit identitas tidak boleh diam-diam memindahkan parent; perpindahan memakai alur move dengan guard relasi dan POP scope.
- Kode infra pada POP/Mini POP dan node adalah identitas lokasinya. Kode OLT/ODP mengikuti keunikan node yang berlaku; nomor slot unik dalam OLT/Switch; nomor PON/Port unik dalam slot. Label jalur lengkap diturunkan dari relasi induk, bukan menyalin Cabang, Mini POP, OLT, dan Slot sebagai teks yang dapat saling bertentangan pada setiap anak.
- Koordinat utama berada pada POP/Mini POP dan node fisik yang memang punya titik peta. Slot/PON/Port mewarisi lokasi perangkat; ONT mengikuti pelanggan.
- Kapasitas harus selalu menyebut satuannya: jumlah slot pada OLT/Switch, jumlah PON/Port pada slot, batas koneksi yang dapat didukung PON, dan jumlah port pelanggan pada ODP. Jangan menjumlahkan «port» lintas tingkat sebagai satu ukuran tanpa label.
- Contoh nama ODP 1 dan Pelanggan A yang berulang dalam penjelasan user adalah ilustrasi. Data nyata memakai ID/kode unik sesuai induknya; satu pelanggan tidak boleh tampil sebagai dua assignment port aktif yang bertabrakan.

## 13. Perubahan aplikasi yang kelak diperlukan

| Bagian | Kondisi saat analisis | Perubahan yang diperlukan kelak |
|---|---|---|
| Form Task FOP | Menyimpan pop_id; belum menyimpan akar scope Mini POP/node secara terstruktur | Simpan akar scope terverifikasi, audit perubahan, teruskan ke Task teknisi |
| Form laporan | Satu pilihan «Lokasi yang dikerjakan» berupa node dari seluruh Cabang task | Dropdown bertingkat per jalur OLT/Switch dan lokasi akhir per baris aksi |
| Validasi aksi | Memeriksa Cabang task dan POP scope aktor | Tambahkan pemeriksaan relasi parent, akar subtree task, dan lokasi akhir |
| Aset/SN lama | Kandidat dapat berasal dari node mana pun dalam Cabang task | Batasi pada lokasi akhir yang dipilih dalam aksi |
| Barang baru | Berasal dari custody tim | Pertahankan custody, tambah kecocokan jenis barang/lokasi akhir |
| Aset berserial dan kabel | Target aset serta saldo infrastruktur sekarang berbasis network node | Simpan aset berserial pada posisi tepat termasuk Mini POP/Slot/Port; kabel hanya mengurangi saldo sisa dan mencatat POP/Mini POP serta koordinat pekerjaan |
| Master Node/Peta | Pohon node dan POP sudah ada, slot OLT tersendiri | Proyeksi hierarki tunggal; cabang Switch, slot/port, dan label hubungan yang konsisten |

Daftar ini adalah dampak rancangan, bukan izin mengubah seluruh modul pada sprint aktif. Billing dan status invoice tidak bergantung pada perubahan pohon ini.

## 14. Urutan transisi dan bukti yang diperlukan

1. Audit read-only jumlah POP/Mini POP, node, slot, PON, ODP, assignment, aset DEPLOYED, dan saldo per lokasi. Catat hubungan ambigu sebelum migrasi.
2. Tetapkan kontrak lokasi aksi, lokasi aset berserial, dan akar scope task. Untuk data nyata, buat skema aditif dengan rollback serta pemetaan ID lama; jangan hapus parent, slot, port, atau ledger nyata. Untuk data topologi yang terverifikasi hanya dummy, reset diperbolehkan sesuai §16.4.
3. Bangun validasi server untuk minimum lokasi, relasi induk, subtree task, custody, aset terpasang, dan POP scope sebelum mengganti UI laporan.
4. Bangun proyeksi hierarki yang sama untuk Master Node, Peta, Task FOP, dan laporan. Uji Switch berhenti di Port serta OLT dapat berakhir di Mini POP/OLT/Slot/PON/ODP.
5. Pindahkan jalur pemasangan/cabut/koreksi dan inventaris ke kontrak lokasi baru secara bertahap. Rekonsiliasi jumlah dan status aset sebelum cutover; kasus ambigu menunggu review operator.
6. Perbarui seeder dan jalur legacy setelah semua referensi nyata terbukti. Pertahankan audit, histori, dan rollback baca data nyata; data topologi dummy dapat direset sesuai §16.4.

## 15. Acceptance criteria tambahan untuk alur operasional

- Task FOP berscope Mini POP C1-M1 mewajibkan C1-M1 terpilih; semua dropdown lokasi null ditolak. Untuk pekerjaan kabel berscope POP C1, POP C1 wajib terpilih tetapi Mini POP boleh «Dikosongkan».
- Pada pemasangan aset berserial yang kompatibel, OLT «Dikosongkan» pada scope Mini POP memasang aset langsung di Mini POP; Slot «Dikosongkan» pada OLT memasang aset langsung di OLT. Status/ledger/inventaris mencerminkan lokasi yang sama. Untuk kabel, pilihan tersebut hanya menentukan lokasi pekerjaan dan pengurangan sisa.
- Task berscope OLT A mengizinkan aksi pada OLT A dan Slot/PON/ODP turunannya, tetapi menolak OLT B dan Mini POP induk sebagai lokasi aksi.
- Jalur Switch berhenti pada Port; ODP disabled/null di UI dan request ODP palsu ditolak server.
- Ganti pilihan induk membersihkan semua pilihan anak, SN lama, dan port yang bergantung pada pilihan lama; request yang dimanipulasi tetap ditolak.
- SN lama untuk cabut/ganti hanya berasal dari lokasi akhir aksi; barang baru hanya dari custody tim dan cocok dengan lokasi. Status Gudang, custody, dan DEPLOYED tidak tertukar.
- Dua baris aksi dalam satu laporan boleh memakai dua turunan berbeda dari scope task, tetapi keduanya harus lolos seluruh guard server.
- POP scope terbatas tidak membocorkan lokasi, SN, aset, breadcrumb, pencarian, atau ekspor cabang lain.
- Aset berserial yang dipasang di Slot 1 OLT A dicatat pada ID Slot 1; aksi cabut di Slot 2 tidak boleh menawarkan SN tersebut. Aset berserial pada Port Switch mengikuti ID Port, bukan hanya ID Switch. Kategori barang yang tidak kompatibel dengan posisi pilihan ditolak.
- Pemakaian kabel tetap menyimpan tingkat terdalam yang dipilih sebagai Lokasi yang dikerjakan; POP/Mini POP adalah area induk dan koordinat dapat menandai titik fisik. Jika akar scope task POP, POP boleh menjadi satu-satunya dropdown terisi. Ujung sambungan divalidasi terpisah; laporan mengurangi sisa kabel tanpa membuat saldo kabel terpasang pada Slot/PON/Port atau menghitung ulang harga awal.

## 16. Penutupan celah kajian 21 Oktober 2026

Bagian ini menggabungkan kajian ulang rancangan dan keputusan user pada 21 Oktober 2026. Keputusan di bawah mengikat untuk implementasi kelak; bagian 1–15 yang bertentangan diperbarui oleh bagian ini. Perubahan hanya pada dokumentasi, bukan izin mengubah aplikasi saat sprint aktif.

### 16.1 Kontrak kolom lokasi akhir (menutup §11.2)

Aset DEPLOYED langsung pada Mini POP (tanpa OLT/Switch tujuan) memakai kolom khusus, bukan menumpang `current_pop_id`/`to_pop_id` yang sudah bermakna konteks Gudang/snapshot cabang.

- `inventory_serials` mendapat kolom baru nullable `current_mini_pop_id` (FK ke `pops`). Diisi hanya saat `status = DEPLOYED` dan lokasi akhir adalah Mini POP tanpa node turunan (OLT/Switch "Dikosongkan" sesuai §10.1). `current_pop_id` tetap khusus konteks Gudang; kedua kolom tidak pernah terisi bersamaan pada baris yang sama.
- `inventory_transactions` mendapat kolom baru nullable `to_mini_pop_id`, sejajar `to_network_node_id`. Transaksi deploy langsung ke Mini POP mengisi `to_mini_pop_id` dan mengosongkan `to_network_node_id`. Jalur cabut/pindah harus mempunyai pasangan lokasi asal Mini POP; jalur Slot dan Port harus mempunyai lokasi asal/tujuan tersendiri dengan FK ke posisi tepat. `to_pop_id` tetap snapshot cabang dengan makna yang sudah ada, tidak berubah.
- Untuk aset berserial, kontrak lokasi aktif adalah **jenis posisi + ID posisi** yang menunjuk tepat satu dari Mini POP, network node (OLT/Switch/ODP), Slot perangkat, atau PON/Port perangkat. Slot dan port perlu FK ke identitas posisinya sendiri; ID OLT/Switch induk tidak boleh dipakai sebagai pengganti. Aset di Gudang, custody teknisi, pelanggan, atau posisi jaringan saling eksklusif. Validasi eksklusivitas dan relasi induk dilakukan oleh layanan; skema akhir untuk FK Slot/Port ditetapkan setelah model slot/port baru diaudit. Jangan menyimpan pasangan type/ID yang tidak mempunyai pemeriksaan referensi dan scope.
- `NetworkNode::deployedSerials()` mendapat pasangan `Pop::deployedSerials()` (via `current_mini_pop_id`). Detail Slot dan Port juga menampilkan aset berserial yang secara langsung terpasang pada posisi tersebut, terpisah dari ringkasan aset induk/turunan. Daftar SN untuk cabut/ganti hanya memakai lokasi tepat milik aksi, bukan seluruh subtree.
- Transaksi pasang, cabut, ganti, pindah, dan koreksi aset berserial menyimpan lokasi asal/tujuan dengan jenis posisi dan ID posisi yang sama; satu SN tidak boleh aktif di dua posisi. Migrasi data nyata bersifat aditif serta menjaga ledger dan audit. Data topologi yang terverifikasi hanya dummy boleh direset sesuai §16.2/§16.4.

### 16.2 Penggantian `aggregation_switch` menjadi `switch_mini_pop` (menutup poin 46)

Tipe `aggregation_switch` tidak dipakai pada struktur baru; Switch operasional berada di Mini POP dengan tipe `switch_mini_pop`. Data topologi lama pada lingkungan yang terbukti hanya berisi dummy boleh direset dan dibuat ulang sesuai struktur baru. Jika ada data nyata yang harus dipertahankan, lokasi Switch lama perlu dipetakan ke Mini POP yang tepat sebelum perubahan tipe dan relasi; mengganti tipe saja tidak memindahkan lokasi POP Cabang menjadi Mini POP.

- Tambah `network_node_types` baru dengan code `switch_mini_pop` (`has_ports = true`, mengikuti struktur Switch → Slot → Port; berbeda dari `aggregation_switch` lama yang `has_ports = false`).
- Untuk data dummy yang sudah diverifikasi, reset topologi lama dan buat Switch baru dengan anchor Mini POP yang benar; tidak perlu mempertahankan ID, riwayat, atau audit baris dummy. Untuk data nyata yang dipertahankan, petakan Switch lama ke Mini POP tujuan, verifikasi uplink/anak/relasi terkait, lalu ubah tipe dan lokasi dengan audit.
- Baris `network_node_types` untuk code `aggregation_switch` tetap ada di tabel (tidak dihapus) agar data historis/audit lama yang merujuk code ini tetap dapat dibaca — pola yang sama dengan `TicketHistoryAction::dicek_noc` yang dipertahankan meski aksinya sudah usang. Code ini tidak lagi ditawarkan di form/seeder baru.
- Aturan slot Switch pada §1 ("Switch modular memakai nomor slot fisik, Switch port tetap memakai Slot 1 berlabel slot internal/logis") berlaku untuk `switch_mini_pop`, bukan lagi untuk `aggregation_switch`.

### 16.3 Kapasitas PON manual dan perhitungan Red Zone

- Kapasitas setiap PON diisi manual pada PON yang bersangkutan. Nilainya dapat ditambah atau dikurangi oleh pengguna berizin; perubahan dicatat dalam audit. Tidak ada kapasitas baku atau rasio split yang dipaksakan untuk semua PON. Satuan kapasitas dan satuan pemakaian harus sama dan ditampilkan dengan jelas. Kapasitas PON tidak dicampur dengan kapasitas ODP yang menyatakan jumlah port pelanggan.
- **Keputusan satuan 21 Oktober 2026:** kapasitas PON dihitung sebagai jumlah pelanggan aktif yang terhubung melalui PON tersebut. Satu pelanggan dengan assignment port ODP aktif dan koneksi ODP–PON aktif terverifikasi dihitung satu unit; beberapa ONT milik pelanggan yang sama tetap terlihat pada tampilan pelanggan tetapi tidak menggandakan pemakaian PON. Assignment aktif ganda untuk pelanggan yang sama perlu ditolak atau direkonsiliasi. Kapasitas ODP tetap bersatuan port pelanggan. Red Zone PON membandingkan jumlah pelanggan aktif terverifikasi terhadap kapasitas manual PON dan ambang yang ditetapkan pada PON; koneksi yang belum terverifikasi tidak dihitung sebagai pemakaian terverifikasi.
- Sesuai §2, PON pada struktur baru adalah **port bertipe PON milik slot OLT**, bukan baris `network_nodes` baru. Baris `network_nodes` bertipe `pon_port` dan kolom `network_nodes.capacity` merupakan representasi lama yang perlu dipetakan saat transisi; keduanya tidak boleh dijadikan sumber kebenaran baru yang bertentangan dengan identitas OLT/Slot/PON.
- Sebelum kapasitas PON boleh dikurangi, validasi server membandingkan angka baru dengan pemakaian aktif dalam satuan yang sama. Pengurangan di bawah pemakaian aktif ditolak sampai koneksi yang terdampak ditangani; nilai sebelumnya dan sesudahnya serta pelaku perubahan masuk audit.
- **Red Zone PON belum otomatis tersedia pada kode saat ini.** `NetworkNode::isInRedZone()` membaca `red_zone_threshold` dan rasio `used_ports_count / ports_count` milik network node; fungsi itu tidak membaca kapasitas manual PON dan tidak memakai `monitors_capacity` sebagai syarat. Mengaktifkan `monitors_capacity` pada tipe `pon_port` saja tidak cukup. Saat implementasi, gunakan satuan pelanggan aktif yang sudah diputuskan di atas, hitung pemakaian dari assignment pelanggan pada koneksi ODP–PON terverifikasi, lalu buat perhitungan ambang PON yang sesuai model port baru; uji kondisi kosong, tepat di ambang, penuh, serta pengurangan kapasitas. Sebelum itu tersedia, UI tidak boleh mengklaim status Red Zone PON.

### 16.4 Kriteria selesai transisi (menutup §14 langkah 6)

§14 langkah 6 diperjelas dengan checklist konkret, diturunkan dari daftar komponen §9. Checklist berikut berlaku untuk **data nyata yang harus dipertahankan**. Data lama pada lingkungan yang sudah diverifikasi hanya berisi **dummy** boleh direset saat transisi tanpa menunggu rekonsiliasi satu siklus; pastikan dahulu cakupan reset tidak mencakup pelanggan, stok, transaksi, atau histori nyata.

| # | Rujukan yang diperiksa | Bukti selesai |
|---|---|---|
| 1 | `olt_slots`, `olt_card`, `pon_port` lama sebagai node | Tidak ada jalur tulis baru (controller/service) yang membuat baris baru dengan representasi lama; hanya dibaca untuk histori |
| 2 | `InventoryService` (deploy/cabut/saldo) | Aset berserial memakai lokasi akhir yang tepat sesuai §16.1; laporan kabel menghitung sisa tanpa saldo kabel terpasang; cabut/koreksi mengikuti aturan jenis barang |
| 3 | `InfrastructureMapService`, Master Node, Peta | Proyeksi hierarki tunggal §1 sudah menjadi satu-satunya tampilan; tidak ada halaman yang masih merender pohon lama secara terpisah |
| 4 | Task FOP, laporan O-REQ/INFR-REQ | Dropdown bertingkat §10.2 dan validasi lokasi akhir §10.1/§10.3 sudah jadi satu-satunya jalur input laporan |
| 5 | Seeder topologi (`NetworkInfrastructureExampleSeeder`, `WhusnetTopologySeeder`, dll.) | Seeder baru/berjalan hanya membuat data dengan pemetaan baru; seeder lama diberi anotasi usang atau dihapus setelah tidak ada test yang bergantung padanya |
| 6 | Laporan rekonsiliasi jumlah (ODP, pelanggan terhubung, port aktif, aset per POP) | Selisih antara proyeksi baru dan data lama = 0 selama satu siklus tutup periode penuh berturut-turut, dicatat sebagai bukti tertulis |
| 7 | Audit log dan histori | Audit baca tetap bisa menampilkan baris lama tanpa error; tidak ada fitur yang butuh menulis ke representasi lama lagi |

Untuk data nyata yang dipertahankan, representasi lama dihentikan hanya setelah ketujuh baris di atas punya bukti tertulis "selesai". Baris dan histori nyata tetap dapat dibaca; "dihentikan" berarti berhenti ditulisi. Untuk data dummy yang sudah diverifikasi, reset diperbolehkan sesuai pengantar bagian ini.

## 17. Penegasan 23 Oktober 2026 — Chassis/Rack/Card adalah aset, bukan node

Klarifikasi user 2026-10-23 terhadap jawaban yang salah kutip spec lama
(`docs/superpowers/specs/2026-10-09-aset-infrastruktur-lokasi-design.md` §10–11
dan `docs/superpowers/specs/2026-10-12-laporan-oreq-infr-req-design.md` §8,
sekarang sudah ditandai usang di berkas masing-masing). Bagian ini menegaskan
ulang, dengan kalimat eksplisit, prinsip yang **sudah berjalan di kode** sejak
ADHOC-202 Task 2 (`feat: model kanonis Slot, PON, dan Port Switch`) — bukan
rancangan baru yang belum dikerjakan:

1. **Hierarki yang benar:** `Mini POP → OLT/Switch → Slot → PON/Port → ODP →
   ONT pelanggan`. Satu Mini POP boleh menaungi **lebih dari satu OLT** (dan
   lebih dari satu Switch) — ini sudah berlaku sejak Fase 0 (§11 dokumen spec
   2026-10-09), bukan perubahan baru.
2. **OLT itu sendiri adalah satu node (`network_nodes`, tipe `olt_chassis` —
   nama *code* historis, artinya sekarang = OLT, bukan "pembungkus chassis
   yang menaungi node kartu terpisah").** Tidak ada lagi node chassis dan
   node kartu sebagai dua tingkat topologi berbeda.
3. **Slot (`infrastructure_slots`) FK langsung ke node OLT/Switch itu**
   (`network_node_id`, lihat `InfrastructureSlot::device()`), bukan ke node
   chassis. Slot tetap ada walau unit fisiknya (chassis/kartu) diganti.
4. **Chassis, rack, dan kartu OLT adalah aset Gudang** (baris
   `inventory_serials`, tipe barang `olt_module`) yang **di-deploy ke slot**
   lewat `InventoryService::deploySerial()` → `current_infrastructure_slot_id`.
   Mereka **bukan** baris `network_nodes` — tidak punya `parent_id`/
   `anchor_pop_id` sendiri, tidak muncul sebagai cabang terpisah di Master
   Node. Mengganti unit fisik = ganti SN yang terpasang di slot yang sama;
   posisi slot (dan identitas Slot/PON/ODP di bawahnya) tidak berubah.
5. **Tipe `olt_card`** (representasi lama, kartu sebagai anak node chassis)
   sudah masuk `InfrastructureCutover::LEGACY_NODE_TYPES` bersama `pon_port`
   dan `aggregation_switch` — jalur tulis baru ke tipe ini ditutup lewat
   `assertLegacyWriteAllowed()`. Baris lama tetap bisa dibaca untuk histori,
   tidak dihapus.
6. **Konsekuensi langsung:** menambah unit OLT baru di satu Mini POP = tambah
   satu node OLT baru (bukan tambah "chassis" di bawah OLT yang sudah ada).
   Mengganti/menambah kartu OLT di slot yang sama = mutasi aset (deploy/
   retrieve SN), bukan mutasi topologi (tidak lewat `NetworkNodeService::move()`).

Tidak ada perubahan skema/kode baru akibat penegasan ini — bagian ini
mendokumentasikan ulang model yang sudah diimplementasikan, supaya dokumen
lama (§16 di atas dan spec 2026-10-09/12) tidak lagi dibaca sebagai bertentangan.


