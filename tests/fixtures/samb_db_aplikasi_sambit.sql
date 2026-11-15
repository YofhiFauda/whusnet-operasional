-- Fixture khusus regresi ADHOC dummy-account exclude. Nama file harus SAMA
-- PERSIS dengan dump Sambit asli (`samb_db_aplikasi_sambit.sql`) karena
-- `MigrateLegacyDataCommand::DUMMY_LEGACY_ACCOUNTS` di-key per basename file.
-- PE000060 adalah salinan baris dummy Faker lorem-ipsum asli dari dump
-- Sambit produksi; PE000099 pelanggan asli pembanding yang WAJIB tetap masuk.

INSERT INTO `cabang` (`ID`, `CABANG`, `LAT_PERUSAHAAN`, `LONG_PERUSAHAAN`) VALUES
('1', 'SAMBIT', '-7', '111');

INSERT INTO `paket` (`KODEPAKET`, `NAMA_PAKET`, `JENIS_PAKET`, `KATEGORI_PAKET`, `HARGA`, `SPEEDUP`, `SPEEDDOWN`, `LIMITUP`, `LIMITDOWN`, `PROFILOLT`, `PROFILPPP`, `BONUS`, `KETERANGAN`) VALUES
('PK000003', 'Up to 5 Mbps 165k', 'Praba', '01.2', '165000', '5000', '5000', '1250', '1250', '', '', '', '');

INSERT INTO `pengguna` (`IDPENGGUNA`, `IDJABATAN`, `IDCABANG`, `NAMADEPAN`, `NAMABELAKANG`, `REKOMENDASI`, `HP`, `TLP`, `IDWILAYAH`, `KOTA`, `KEC`, `DESA`, `ALMT`, `EMAIL`, `STATUSAKUN`, `NAMAPERUSAHAAN`, `NPWP`, `KTP_SIM`, `FOTOKTP`, `JENISKELAMIN`, `JENISPELANGGAN`, `FOTO`, `inserted_at`) VALUES
('PE000060', '8', '1', 'nisi-voluptate-natus-soluta-esse-consequatur-deser', '', '', 'Quia aliquam vi', NULL, '3502010002', NULL, NULL, NULL, 'Odio do molestias do', 'Sunt non Nam volupt', '', NULL, 'Sapiente minus o', '1234657431240', '1c8d910eb836c0f085eb29c8ec8d9cd2.png', 'L', 'RUMAHAN', '', '2025-06-12 13:52:19'),
('PE000099', '8', '1', 'sutrisno', 'wahyudi', '', '6285200000099', NULL, '3502010099', 'KABUPATEN PONOROGO', 'SAMBIT', 'SAMBIT', 'Dukuh Sambit RT 01 RW 01', 'sutrisno@example.com', 'aktif', NULL, '', '3502099901990001', 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6.jpg', 'L', 'RUMAHAN', '', '2025-06-12 09:00:00');

INSERT INTO `prosedure_permintaan_wifi` (`KODEAPP`, `kategori_perangkat_jaringan`, `kode_kontrol_distribusi`, `IDPERMINTAAN`, `IDPENGGUNA`, `STATUS`, `TGL_AKTIFPUTUS`, `STATUSPASANG`, `ALASAN`, `STATUSTINDAKANALAT`, `STATUSLANGGANAN`, `UBAHKONEKSI`, `IDPAKET`, `DISURVEY`, `DIACC`, `DIPROSES`, `CREATED`, `DILAPORKAN`, `TGLDIACC`, `TGLSURVEY`, `TGLDIPROSES`, `TGLSELESAI`, `STATUSALAT`, `VERIFIED`, `VERIFIED_AT`, `JENISJARINGAN`, `JENISMEMBER`, `IDBIAYA`, `inserted_at`, `updated_at`) VALUES
('G', '1', 'X4A', 'RQ000061', 'PE000060', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', '1', 'PK000003', '6', '6', '6', 'PE21000001', '6', '2025-06-12 13:52:19', '2025-06-12 13:52:19', '2025-06-12 13:52:19', '2025-06-13 08:00:00', 'SEWA', '6', '2025-06-13 08:10:00', 'KABEL', '2', 'IN000061', '2025-06-12 13:52:19', '2025-06-13 08:00:00'),
('G', '1', 'X4B', 'RQ000099', 'PE000099', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', '1', 'PK000003', '6', '6', '6', 'PE21000001', '6', '2025-06-12 09:00:00', '2025-06-12 09:00:00', '2025-06-12 09:00:00', '2025-06-13 09:00:00', 'SEWA', '6', '2025-06-13 09:10:00', 'KABEL', '0', 'IN000099', '2025-06-12 09:00:00', '2025-06-13 09:00:00');

INSERT INTO `laporan_pemasangan_wifi` (`IDREPORT`, `IDPENGGUNA`, `IDPERMINTAAN`, `STATUSPASANG`, `BRG_INDOOR`, `BRG_OUTDOOR`, `SINYAL`, `JENIS`, `TESTUP`, `TESTDOWN`, `STATUS`, `BTS`, `SSID`, `FOTOTOWER`, `FOTOKABEL`, `FOTOROOTER`, `FOTOSPEED`, `FOTOFORMULIR`, `FOTOTTDFORMULIR`, `PINGGATEWAY`, `PINGGOOGLE`, `IPADDR`, `MACADDR_ANTENA`, `MACADDR_ROOTER`, `SNROOTER_FIBER`, `NOMOR_ODP`, `NOMOR_PORT_ODP`, `NOMOR_PORT_OLT`, `SIGNAL_WIRELESS`, `SIGNAL_KABEL`, `HISTORYROOTER`, `HISTORYANTENA`, `HISTORYDETAIL`, `LOKASIPEMANCAR`, `KETERANGAN`, `IDODPLOCATION`) VALUES
('lp000061', 'PE000060', 'RQ000061', NULL, NULL, NULL, '', 'KABEL', '5', '5', NULL, NULL, NULL, NULL, NULL, 'a.jpeg', 'b.png', 'c.jpeg', 'd.jpeg', '1', '1', '10.0.0.61', '', '', 'SN000061', 'Odp 1', '1', '1', '-10', '', NULL, NULL, NULL, '', 'Tidak ada', ''),
('lp000099', 'PE000099', 'RQ000099', NULL, NULL, NULL, '', 'KABEL', '5', '5', NULL, NULL, NULL, NULL, NULL, 'a2.jpeg', 'b2.png', 'c2.jpeg', 'd2.jpeg', '1', '1', '10.0.0.99', '', '', 'SN000099', 'Odp 2', '1', '1', '-10', '', NULL, NULL, NULL, '', 'Tidak ada', '');

INSERT INTO `riwayat_pelanggan` (`ID`, `IDPERMINTAAN`, `CREATEBY`, `STATUSTINDAKAN`, `STATUSLANGGANAN`, `ALASAN`, `TGLTINDAKAN`, `STATUSTINDAKANALAT`) VALUES
('r000061', 'RQ000061', 'PE000060', 'Berhasil Active', '', '', '2025-06-13 08:10:00', ''),
('r000099', 'RQ000099', 'PE000099', 'Berhasil Active', '', '', '2025-06-13 09:10:00', '');

INSERT INTO `biaya_tagihan` (`IDBIAYA`, `IDPELANGGAN`, `IDPERMINTAAN`, `BIAYAPASANG`, `BIAYABULANAN`, `BIAYALAINLAIN`, `TOTALBIAYA`, `TGLINSERT`) VALUES
('IN000061', 'PE000060', 'RQ000061', '0', '165000', '11000', '176000', '2025-06-13 08:00:00'),
('IN000099', 'PE000099', 'RQ000099', '0', '165000', '11000', '176000', '2025-06-13 09:00:00');

INSERT INTO `apikeuangan_buktitransaksitagihan` (`IDUNIQ`, `IDTRANSAKSI`, `IDPERMINTAAN`, `NOINDEXTAGIHAN`, `BAYAR`, `BULANTAGIHAN`, `FLAG`, `INSERTED_AT`, `notivwa`) VALUES
('u000061', 'IN000061', 'RQ000061', '0', '176000', '2025-06-13', '2', '2025-06-13 08:05:00', '0'),
('u000099', 'IN000099', 'RQ000099', '0', '176000', '2025-06-13', '2', '2025-06-13 09:05:00', '0');
