# WORKHEET TICKETING
**Ringkasan :**  Ini merupakan lembar kerja dari NOC dan Helpdesk dalam menanggani pelanggan yang dimana sebelumnya menggunakan Excel dalam mencatat issue atau keluhan pelanggan, dan sekarang di ganti pada sistem ini


**Contoh Sub Kategori*
------------------
- Lemot = low 
- LOS = Medium 
- Backbone CUT = High
- ODP LOS  = High 


Master Issue / Complain ( sub kategori) = untuk Issue di buatkan menjadi Master namun juga bisa di isi manual
Detail Keluhan (Customer Complaint)  = Sub Kategori
Didalam Master terdapat Nama Issue atau Komplain dan bisa menggunakan SLA berdasarkan paket atau menggunakan Prioritas


Task Harus bersih atau ada beberapa saja setiap hari

New Ticket = Worksheet Helpdesk (digunakan untuk menyelesaikan task komplain dari pelanggan)

Dashboard NOC
Halaman Ticket Masuk - dibatalkan = NOC


Skenario Helpdesk bisa selesaikan konfigurasi sendiri.
1. Helpdesk Menerima komplain
2. Helpdesk OpenTicket
3. Helpdesk Melakukan Perbaikan
4. Helpdesk Close Ticket

Skenario Helpdesk gak bisa selesaikan konfigurasi atau yakin perlu TIM NOC (Konfigurasi Sulit/Routing)
1. Helpdesk Menerima komplain
2. Helpdesk OpenTicket
3. Helpdesk Tidak Bisa Memperbaiki
4. Helpdesk Kirim Ticket Ke NOC

Skenario Helpdesk gak bisa selesaikan konfigurasi dan yakin perlu TIM LAPANGAN (ODP LOS)
1. Helpdesk Menerima komplain
2. Helpdesk OpenTicket
3. Helpdesk Tidak Bisa Memperbaiki
4. Helpdesk Kirim Ticket Ke FOP


———————————

Skenario NOC Bisa selesaikan konfigurasi sendiri
1. NOC menerima Ticket Dari Helpdesk
2. NOC Melakukan perbaikan
3. NOC Close Ticket

Skenario NOC Tidak bisa selesaikan konfigurasi sendiri dan butuh tim lapangan
1. NOC menerima Ticket Dari Helpdesk
2. NOC Gagal Melakukan perbaikan
3. NOC Kirim Ticket Ke FOP

——————
Skenario FOP
1. FOP Menerima Ticket dari Helpdesk dan NOC
2. FOP Melakukan penjadwalan Teknisi
3. FOP Menerima Laporan Dari Teknisi dan Verifikasi hasil baru bisa Close Ticket




-