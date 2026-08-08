-- Fixture migrasi legacy untuk regresi duplikasi tagihan & pembayaran.
-- Empat skenario yang diangkat dari data asli jetis_db_aplikasi_jetis.sql:
--
--   RQ000004  Ardiyanto  — dua bukti bayar dengan BULANTAGIHAN identik (duplikat
--                          sistem lama) + TGLINSERT yang meleset tiga tahun
--   RQ000821  Singgih    — dua bukti bayar periode BERBEDA (dua tagihan sah)
--   RQ001191  Wiyono     — baris biaya tanpa biaya pasang & bulanan (log aktivasi)
--                          plus baris reaktivasi yang sah
--   RQ000032  Sukirman   — satu-satunya baris registrasi asli (BIAYAPASANG > 0)

INSERT INTO `paket` (`KODEPAKET`, `NAMA_PAKET`, `JENIS_PAKET`, `KATEGORI_PAKET`, `HARGA`, `SPEEDUP`, `SPEEDDOWN`, `LIMITUP`, `LIMITDOWN`, `PROFILOLT`, `PROFILPPP`, `BONUS`, `KETERANGAN`) VALUES
('PK000003', 'Up to 5 Mbps 165k', 'Praba', '01.2', 165000, 5000, 5000, 1250, 1250, '', '', '', ''),
('PK000009', 'Up to 10 Mbps 110k', 'Praba', '01.2', 110000, 10000, 10000, 2500, 2500, '', '', '', '');

INSERT INTO `pengguna` (`IDPENGGUNA`, `IDJABATAN`, `IDCABANG`, `NAMADEPAN`, `NAMABELAKANG`, `REKOMENDASI`, `HP`, `TLP`, `IDWILAYAH`, `KOTA`, `KEC`, `DESA`, `ALMT`, `EMAIL`, `STATUSAKUN`, `NAMAPERUSAHAAN`, `NPWP`, `KTP_SIM`, `FOTOKTP`, `JENISKELAMIN`, `JENISPELANGGAN`, `FOTO`, `inserted_at`) VALUES
('PE000002', '5', '1', 'ardiyanto cahyo', 'nugroho', '', '6281234500002', '', '3502170006', 'KABUPATEN PONOROGO', 'JETIS', 'WONOKETRO', 'Dukuh Krajan RT 01 RW 01', '', 'aktif', '', '', '3502170000000002', '', 'L', '', '', '2022-01-06 08:34:22'),
('PE000817', '5', '1', 'singgih', 'hardiyanto', '', '6281234500817', '', '3502170006', 'KABUPATEN PONOROGO', 'JETIS', 'WONOKETRO', 'Dukuh Josari RT 02 RW 02', '', 'aktif', '', '', '3502170000000817', '', 'L', '', '', '2023-08-01 08:00:00'),
('PE001187', '5', '1', 'wiyono', 'wonoketro', '', '6281234501187', '', '3502170006', 'KABUPATEN PONOROGO', 'JETIS', 'WONOKETRO', 'Dukuh Wonoketro RT 03 RW 02', '', 'aktif', '', '', '3502170000001187', '', 'L', '', '', '2024-06-10 09:05:32'),
('PE000028', '5', '1', 'sukirman', 'pasang', '', '6281234500028', '', '3502170006', 'KABUPATEN PONOROGO', 'JETIS', 'WONOKETRO', 'Dukuh Bajang RT 04 RW 01', '', 'aktif', '', '', '3502170000000028', '', 'L', '', '', '2024-03-01 04:00:00');

INSERT INTO `prosedure_permintaan_wifi` (`KODEAPP`, `kategori_perangkat_jaringan`, `kode_kontrol_distribusi`, `IDPERMINTAAN`, `IDPENGGUNA`, `STATUS`, `TGL_AKTIFPUTUS`, `STATUSPASANG`, `ALASAN`, `STATUSTINDAKANALAT`, `STATUSLANGGANAN`, `UBAHKONEKSI`, `IDPAKET`, `DISURVEY`, `DIACC`, `DIPROSES`, `CREATED`, `DILAPORKAN`, `TGLDIACC`, `TGLSURVEY`, `TGLDIPROSES`, `TGLSELESAI`, `STATUSALAT`, `VERIFIED`, `VERIFIED_AT`, `JENISJARINGAN`, `JENISMEMBER`, `IDBIAYA`, `inserted_at`, `updated_at`) VALUES
('C', 1, 'X4A', 'RQ000004', 'PE000002', 'ACTIVE', '2022-02-07', 'Berhasil', '', '', '', 1, 'PK000003', '6', '6', '6', 'PE21000001', '6', '2022-02-07 12:13:42', '2022-02-07 11:43:31', '2022-02-07 12:13:50', '2022-02-07 20:36:19', 'SEWA', '6', '2022-02-07 20:46:45', 'KABEL', 2, 'IN000035', '2022-01-06 08:34:22', '2022-05-10 03:45:55'),
('C', 1, 'X4C', 'RQ000821', 'PE000817', 'ACTIVE', '2023-08-16', 'Berhasil', '', '', '', 1, 'PK000009', '6', '6', '6', 'PE21000001', '6', '2023-08-15 12:13:42', '2023-08-15 11:43:31', '2023-08-16 12:13:50', '2023-08-16 13:36:19', 'SEWA', '6', '2023-08-16 13:46:45', 'KABEL', 2, 'IN000905', '2023-08-01 08:00:00', '2023-08-16 13:47:46'),
('C', 1, 'X4C', 'RQ001191', 'PE001187', 'ACTIVE', '2024-06-26', 'Berhasil', '', '', '', 1, 'PK000009', '6', '6', '6', 'PE21000001', '6', '2024-06-25 04:44:38', '2024-06-25 00:28:24', '2024-06-25 05:18:27', '2024-06-25 09:54:50', 'SEWA', '6', '2024-06-26 02:15:09', 'KABEL', 2, 'IN001635', '2024-06-10 09:05:32', '2025-05-05 07:32:59'),
('C', 1, 'X4A', 'RQ000032', 'PE000028', 'ACTIVE', '2024-03-19', 'Berhasil', '', '', '', 1, 'PK000009', '6', '6', '6', 'PE21000001', '6', '2024-03-18 04:00:00', '2024-03-18 03:00:00', '2024-03-19 04:00:00', '2024-03-19 04:15:00', 'SEWA', '6', '2024-03-19 04:20:00', 'KABEL', 2, 'IN000028', '2024-03-01 04:00:00', '2024-03-19 04:20:04');

-- TGLINSERT sengaja dibiarkan seperti aslinya: kolom ON UPDATE, jadi nilainya
-- bisa bertahun-tahun setelah tagihan sebenarnya (IN000035 di bawah).
INSERT INTO `biaya_tagihan` (`IDBIAYA`, `IDPELANGGAN`, `IDPERMINTAAN`, `BIAYAPASANG`, `BIAYABULANAN`, `BIAYALAINLAIN`, `TOTALBIAYA`, `TGLINSERT`) VALUES
('IN000035', 'PE000002', 'RQ000004', 0, 165000, 11000, 176, '2025-05-08 01:28:22'),
('IN000905', 'PE000817', 'RQ000821', 0, 110645, 11000, 122, '2023-10-01 12:20:52'),
('IN001266', 'PE001187', 'RQ001191', 0, 0, 11000, 0, '2024-06-26 02:15:10'),
('IN001267', 'PE001187', 'RQ001191', 0, 0, 11000, 0, '2024-06-26 02:15:13'),
('IN001635', 'PE001187', 'RQ001191', 0, 109032, 11000, 120032, '2025-05-05 07:32:59'),
('IN000028', 'PE000028', 'RQ000032', 250000, 110000, 11000, 110, '2024-03-19 04:20:04');

INSERT INTO `apikeuangan_buktitransaksitagihan` (`IDUNIQ`, `IDTRANSAKSI`, `IDPERMINTAAN`, `NOINDEXTAGIHAN`, `BAYAR`, `BULANTAGIHAN`, `FLAG`, `INSERTED_AT`, `notivwa`) VALUES
('6361f31b0fa', 'IN000035', 'RQ000004', 0, 165000, '2022-11-02', 2, '2022-11-02 04:33:31', 0),
('63aa65564fd', 'IN000035', 'RQ000004', 0, 165000, '2022-11-02', 0, '2022-12-27 03:24:06', 0),
('64dcd37eb34', 'IN000905', 'RQ000821', 0, 110645, '2023-08-16', 0, '2023-08-16 13:47:46', 0),
('64dcd37eb99', 'IN000905', 'RQ000821', 0, 110645, '2023-09-16', 0, '2023-09-16 09:12:00', 0),
('667b79addf3', 'IN001266', 'RQ001191', 0, 0, '2024-06-26', 0, '2024-06-26 02:15:10', 0),
('681869ab9a6', 'IN001635', 'RQ001191', 0, 120032, '2025-05-05', 0, '2025-05-05 07:32:59', 0),
('65f9a1b2c30', 'IN000028', 'RQ000032', 0, 180000, '2024-03-19', 0, '2024-03-19 04:25:00', 0),
('6602a1b2c31', 'IN000028', 'RQ000032', 0, 110000, '2024-04-05', 0, '2024-04-05 03:10:00', 0);

-- IN000905 sengaja punya dua baris: bulan & metode berbeda. Pembayaran September
-- harus memakai baris keduanya, bukan mencatut metode/penerima baris Agustus.
-- Catatan: parser dump tidak boleh menemukan komentar di antara baris VALUES.
INSERT INTO `apikeuangan_buktitransaksilunas` (`IDUNIQ`, `IDTRANSAKSI`, `IDPERMINTAAN`, `TGLBAYAR`, `BULANTAGIHAN`, `JENISPEMBAYARAN`, `BAYAR`, `IDPENERIMA`, `IDPENYETOR`, `KET`, `NOINDEXTAGIHAN`) VALUES
('6369f634ae9', 'IN000035', '', '2022-11-02 04:33:31', '', 'Cash', 165000, 'PG000004', '', '', 0),
('6369f634af0', 'IN000905', '', '2023-08-16 13:47:46', '', 'Cash', 110645, 'PG000004', '', '', 0),
('6369f634af5', 'IN000905', '', '2023-09-16 09:12:00', '', 'Transfer', 110645, 'PG000009', '', 'Setor via bank', 0),
('6369f634af1', 'IN001635', '', '2025-05-05 07:32:59', '', 'Transfer', 120032, 'PG000004', '', '', 0),
('6369f634af2', 'IN000028', '', '2024-03-19 04:25:00', '', 'Cash', 180000, 'PG000004', '', '', 0);

INSERT INTO `riwayat_pelanggan` (`ID`, `IDPERMINTAAN`, `CREATEBY`, `STATUSTINDAKAN`, `STATUSLANGGANAN`, `ALASAN`, `TGLTINDAKAN`, `STATUSTINDAKANALAT`) VALUES
('6361f31b001', 'RQ000004', 'PG000004', 'Berhasil Active', '', '', '2022-02-07 20:46:45', ''),
('64dcd37e001', 'RQ000821', 'PG000004', 'Berhasil Active', '', '', '2023-08-16 13:47:46', ''),
('667b79ad001', 'RQ001191', 'PG000004', 'Berhasil Active', '', '', '2024-06-26 02:15:09', ''),
('65f9a1b2001', 'RQ000032', 'PG000004', 'Berhasil Active', '', '', '2024-03-19 04:20:04', '');
