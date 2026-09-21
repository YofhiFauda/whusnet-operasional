> Rancangan lengkap & gap terhadap kode: `analisa-rancangan-putus-langganan.md` (ADHOC-69). Bullet ke-4 dirinci di §3.1a dokumen itu.

## SKEMA
Putus Langganan:
Pada saat pelanggan putus langganan di tengah bulan maka Catatan piutang tersebut akan akan tercatat di List Putus Langganan dan Invoice akan terbit + Denda putus langganan (bisa di isi manual)
-Pada putusn Langganan terdapat kolom itu pelanggan siapa (berdasarkan siapa yang registrasi sales/teknisi siapa saja)
-jika ingin memutuskan pelanggan itu harus berdasarkan alas an (nanti di generate by master) yang dimana pada list pelanggan putus terdapat alas an tersebut yang bisa di sorting atau di filter. (ini untuk memudahkan Customer service), Masternya nanti itu seperti Pindah, Kompetitor, dll yang bisa di tambah dan di edit sendiri.
-Kalau pelanggan putus dan masa langgananya (dihitung dari tanggal_aktivasi di customer_services sampai tanggal putus diajukan) masih <=1 tahun, nominal denda default dari master alasan **tidak dipakai** — diisi manual per kasus oleh admin/CS saat submit form putus (gantikan, bukan tambahan di atas denda alasan).
