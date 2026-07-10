-- phpMyAdmin SQL Dump
-- version 5.1.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jun 11, 2026 at 07:48 AM
-- Server version: 10.4.31-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sand_db_sandya`
--

DELIMITER $$
--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `fRupiah` (`number` BIGINT) RETURNS VARCHAR(255) CHARSET latin1 COLLATE latin1_swedish_ci DETERMINISTIC BEGIN
    DECLARE hasil VARCHAR(255);

    -- Format angka ke format ribuan (Rupiah)
    SET hasil = REPLACE(REPLACE(REPLACE(FORMAT(`number`, 0), '.', '|'), ',', '.'), '|', ',');

    RETURN hasil;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `apiakses`
--

CREATE TABLE `apiakses` (
  `IDUSERS` int(3) NOT NULL,
  `NAME` varchar(100) NOT NULL,
  `ACCESS` tinyint(1) NOT NULL,
  `USERNAME` varchar(30) NOT NULL,
  `PASSWORD` varchar(250) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `apiakses`
--

INSERT INTO `apiakses` (`IDUSERS`, `NAME`, `ACCESS`, `USERNAME`, `PASSWORD`) VALUES
(1, 'Api Mesin OLT', 1, 'apioltmaster', '3a91482a4a46a09a086f4f48268c494daaed9c0a');

-- --------------------------------------------------------

--
-- Table structure for table `apikeuangan_buktitransaksilunas`
--

CREATE TABLE `apikeuangan_buktitransaksilunas` (
  `IDUNIQ` varchar(11) NOT NULL,
  `IDTRANSAKSI` varchar(23) NOT NULL,
  `IDPERMINTAAN` varchar(11) NOT NULL,
  `TGLBAYAR` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `BULANTAGIHAN` varchar(7) NOT NULL,
  `JENISPEMBAYARAN` varchar(8) NOT NULL,
  `BAYAR` float NOT NULL,
  `IDPENERIMA` varchar(11) NOT NULL,
  `IDPENYETOR` varchar(11) NOT NULL,
  `KET` text NOT NULL,
  `NOINDEXTAGIHAN` int(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `apikeuangan_buktitransaksipemasangan`
--

CREATE TABLE `apikeuangan_buktitransaksipemasangan` (
  `IDPERMINTAAN` varchar(11) NOT NULL,
  `TGLBAYAR` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `apikeuangan_buktitransaksitagihan`
--

CREATE TABLE `apikeuangan_buktitransaksitagihan` (
  `IDUNIQ` varchar(11) NOT NULL,
  `IDTRANSAKSI` varchar(23) NOT NULL,
  `IDPERMINTAAN` varchar(11) NOT NULL,
  `NOINDEXTAGIHAN` int(2) NOT NULL,
  `BAYAR` float NOT NULL,
  `BULANTAGIHAN` date NOT NULL,
  `FLAG` int(1) NOT NULL,
  `INSERTED_AT` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `notivwa` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `apikeuangan_buktitransaksitagihan`
--

INSERT INTO `apikeuangan_buktitransaksitagihan` (`IDUNIQ`, `IDTRANSAKSI`, `IDPERMINTAAN`, `NOINDEXTAGIHAN`, `BAYAR`, `BULANTAGIHAN`, `FLAG`, `INSERTED_AT`, `notivwa`) VALUES
('681980066b8', 'IN000001', 'RQ000008', 0, 0, '2025-05-06', 0, '2025-05-06 03:20:38', 0),
('6895ac8e2c0', 'IN000002', 'RQ000013', 0, 12000, '2025-08-08', 0, '2025-08-08 07:51:42', 0),
('689c5125d59', 'IN000003', 'RQ000016', 0, 167806, '2025-08-13', 0, '2025-08-13 08:47:33', 0),
('689c5126aea', 'IN000004', '', 0, 0, '2025-08-13', 0, '2025-08-13 08:47:34', 0),
('689ca10bcd3', 'IN000005', 'RQ000014', 0, 12000, '2025-08-13', 0, '2025-08-13 14:28:27', 0),
('689ed2a1a78', 'IN000006', 'RQ000015', 0, 12000, '2025-08-15', 0, '2025-08-15 06:24:33', 0),
('689ed2a24ef', 'IN000007', '', 0, 0, '2025-08-15', 0, '2025-08-15 06:24:34', 0),
('689ed31e32e', 'IN000008', 'RQ000017', 0, 157161, '2025-08-15', 0, '2025-08-15 06:26:38', 0),
('689ed31ef27', 'IN000009', '', 0, 0, '2025-08-15', 0, '2025-08-15 06:26:39', 0),
('68a82e3e6d7', 'IN000010', 'RQ000018', 0, 159903, '2025-08-22', 0, '2025-08-22 08:45:50', 0),
('68ac14be01b', 'IN000011', 'RQ000019', 0, 12000, '2025-08-25', 0, '2025-08-25 07:46:06', 0),
('68ac14becf9', 'IN000012', '', 0, 0, '2025-08-25', 0, '2025-08-25 07:46:06', 0),
('68b78d5ea0e', 'IN000013', 'RQ000022', 0, 163245, '2025-09-03', 0, '2025-09-03 00:35:42', 0),
('68b78d695b0', 'IN000014', 'RQ000023', 0, 163245, '2025-09-03', 0, '2025-09-03 00:35:53', 0),
('68bf88f19db', 'IN000015', 'RQ000024', 0, 52000, '2025-09-09', 0, '2025-09-09 01:54:57', 0),
('68c27d2edfc', 'IN000016', 'RQ000021', 0, 12000, '2025-09-11', 0, '2025-09-11 07:41:34', 0),
('68c27d2ff25', 'IN000017', '', 0, 0, '2025-09-11', 0, '2025-09-11 07:41:36', 0),
('68d241b4332', 'IN000018', 'RQ000027', 0, 12000, '2025-09-23', 0, '2025-09-23 06:44:04', 0),
('68d241b4e86', 'IN000019', '', 0, 0, '2025-09-23', 0, '2025-09-23 06:44:04', 0),
('68d242315a9', 'IN000020', 'RQ000028', 0, 12000, '2025-09-23', 0, '2025-09-23 06:46:09', 0),
('68d242322a4', 'IN000021', '', 0, 0, '2025-09-23', 0, '2025-09-23 06:46:10', 0),
('68d9e913362', 'IN000022', 'RQ000029', 0, 62000, '2025-09-29', 0, '2025-09-29 02:04:03', 0),
('68d9e91419d', 'IN000023', '', 0, 0, '2025-09-29', 0, '2025-09-29 02:04:04', 0),
('68e921ce20c', 'IN000024', 'RQ000034', 0, 12000, '2025-10-10', 0, '2025-10-10 15:10:06', 0),
('68ee138c118', 'IN000025', 'RQ000033', 0, 62000, '2025-10-14', 0, '2025-10-14 09:10:36', 0),
('68ee138cb6d', 'IN000026', '', 0, 0, '2025-10-14', 0, '2025-10-14 09:10:36', 0),
('68f98421778', 'IN000027', 'RQ000035', 0, 12000, '2025-10-23', 0, '2025-10-23 01:25:53', 0),
('68f98422484', 'IN000028', '', 0, 0, '2025-10-23', 0, '2025-10-23 01:25:54', 0),
('69058e874a7', 'IN000029', 'RQ000036', 0, 12000, '2025-11-01', 0, '2025-11-01 04:37:27', 0),
('6905ab395e9', 'IN000030', 'RQ000038', 0, 191500, '2025-11-01', 0, '2025-11-01 06:39:53', 0),
('6905ab3a3a5', 'IN000031', '', 0, 0, '2025-11-01', 0, '2025-11-01 06:39:54', 0),
('6912a139645', 'IN000032', 'RQ000039', 0, 52000, '2025-11-11', 0, '2025-11-11 02:36:41', 0),
('6912a13a63c', 'IN000033', '', 0, 0, '2025-11-11', 0, '2025-11-11 02:36:42', 0),
('6912a20cca7', 'IN000034', 'RQ000040', 0, 156500, '2025-11-11', 0, '2025-11-11 02:40:12', 0),
('6912a20dc82', 'IN000035', '', 0, 0, '2025-11-11', 0, '2025-11-11 02:40:13', 0),
('695cb4b7744', 'IN000036', 'RQ000043', 0, 150000, '2026-01-06', 0, '2026-01-06 07:07:35', 0),
('695cbfa2139', 'IN000037', 'RQ000006', 0, 0, '2026-01-06', 0, '2026-01-06 07:54:10', 0),
('695cbfa3075', 'IN000038', '', 0, 0, '2026-01-06', 0, '2026-01-06 07:54:11', 0),
('695e0b17001', 'IN000039', 'RQ000012', 0, 0, '2026-01-07', 0, '2026-01-07 07:28:23', 0);

-- --------------------------------------------------------

--
-- Table structure for table `apikeuangan_buktitransaksiterkumpul`
--

CREATE TABLE `apikeuangan_buktitransaksiterkumpul` (
  `IDUNIQ` varchar(11) NOT NULL,
  `IDTRANSAKSI` varchar(23) NOT NULL,
  `TGLBAYAR` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `JENISPEMBAYARAN` varchar(8) NOT NULL,
  `BAYAR` float NOT NULL,
  `IDPENERIMA` varchar(11) NOT NULL,
  `NOINDEXTAGIHAN` int(2) NOT NULL,
  `KET` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `apikeuangan_detailjurnalpemasukan`
--

CREATE TABLE `apikeuangan_detailjurnalpemasukan` (
  `IDJURNALPEMASUKAN` varchar(11) NOT NULL,
  `KODEDATA` int(2) NOT NULL,
  `KATEGORI` varchar(10) NOT NULL,
  `JENISBAYAR` varchar(20) NOT NULL,
  `JUDUL` varchar(50) NOT NULL,
  `HARGA` float NOT NULL,
  `QTY` int(2) NOT NULL,
  `TOTAL` float NOT NULL,
  `KETERANGAN` varchar(200) NOT NULL,
  `INFO` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `apikeuangan_detailjurnalpengeluaran`
--

CREATE TABLE `apikeuangan_detailjurnalpengeluaran` (
  `IDJURNALPENGELUARAN` varchar(11) NOT NULL,
  `KODEDATA` int(2) NOT NULL,
  `KATEGORI` varchar(10) NOT NULL,
  `JENISBAYAR` varchar(20) NOT NULL,
  `JUDUL` varchar(50) NOT NULL,
  `HARGA` float NOT NULL,
  `QTY` int(2) NOT NULL,
  `TOTAL` float NOT NULL,
  `KETERANGAN` varchar(200) NOT NULL,
  `INFO` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `apikeuangan_jurnaloperasioanl`
--

CREATE TABLE `apikeuangan_jurnaloperasioanl` (
  `IDOPERASIONAL` varchar(10) NOT NULL,
  `IDTEKNISI` varchar(12) NOT NULL,
  `DEBIT` float NOT NULL,
  `KREDIT` float NOT NULL,
  `IDJURNALPENGELUARAN` varchar(12) NOT NULL,
  `FLAGSTOR` tinyint(1) NOT NULL,
  `Inserted_At` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `apikeuangan_jurnalpemasukan`
--

CREATE TABLE `apikeuangan_jurnalpemasukan` (
  `IDJURNALPEMASUKAN` varchar(11) NOT NULL,
  `SUBTOTAL` float NOT NULL,
  `IDPENGGUNA` varchar(11) NOT NULL,
  `Inserted_By` varchar(11) NOT NULL,
  `Inserted_At` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `FLAGSTOR` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `apikeuangan_jurnalpengeluaran`
--

CREATE TABLE `apikeuangan_jurnalpengeluaran` (
  `IDJURNALPENGELUARAN` varchar(11) NOT NULL,
  `SUBTOTAL` float NOT NULL,
  `SELLER` varchar(50) NOT NULL,
  `Inserted_By` varchar(11) NOT NULL,
  `Inserted_At` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `apikeuangan_jurnaltranfer`
--

CREATE TABLE `apikeuangan_jurnaltranfer` (
  `IDJURNALTRANFER` varchar(11) NOT NULL,
  `IDPENGIRIM` varchar(11) NOT NULL,
  `IDPENERIMA` varchar(11) NOT NULL,
  `DEBIT` float NOT NULL,
  `KREDIT` float NOT NULL,
  `JENISBAYAR` varchar(20) NOT NULL,
  `KETERANGAN` varchar(200) NOT NULL,
  `INFO` varchar(200) NOT NULL,
  `Inserted_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `barang`
--

CREATE TABLE `barang` (
  `KODEBARANG` varchar(11) NOT NULL,
  `MERKBARANG` varchar(11) DEFAULT NULL,
  `UNIT` int(6) NOT NULL,
  `UKURAN` varchar(50) DEFAULT NULL,
  `IP` varchar(15) DEFAULT NULL,
  `MASK` varchar(12) DEFAULT NULL,
  `MACADDRESS` varchar(35) NOT NULL,
  `HARGABELI` float(255,0) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `beritainfo`
--

CREATE TABLE `beritainfo` (
  `IDCONTENT` varchar(11) NOT NULL,
  `IMG` varchar(150) NOT NULL,
  `TITLE` varchar(30) NOT NULL,
  `CONTENT` text NOT NULL,
  `TGL` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `biaya_tagihan`
--

CREATE TABLE `biaya_tagihan` (
  `IDBIAYA` varchar(15) NOT NULL,
  `IDPELANGGAN` varchar(11) NOT NULL,
  `IDPERMINTAAN` varchar(11) NOT NULL,
  `BIAYAPASANG` int(50) NOT NULL,
  `BIAYABULANAN` int(50) NOT NULL,
  `BIAYALAINLAIN` int(50) NOT NULL,
  `TOTALBIAYA` int(50) NOT NULL,
  `TGLINSERT` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `biaya_tagihan`
--

INSERT INTO `biaya_tagihan` (`IDBIAYA`, `IDPELANGGAN`, `IDPERMINTAAN`, `BIAYAPASANG`, `BIAYABULANAN`, `BIAYALAINLAIN`, `TOTALBIAYA`, `TGLINSERT`) VALUES
('IN000001', 'PE000008', 'RQ000008', 0, 0, 0, 0, '2025-05-06 03:20:38'),
('IN000002', 'PE000013', 'RQ000013', 0, 0, 12000, 12000, '2025-08-08 07:51:42'),
('IN000003', 'PE000016', 'RQ000016', 0, 95806, 72000, 167806, '2025-08-13 08:47:33'),
('IN000004', '', '', 0, 0, 0, 0, '2025-08-13 08:47:34'),
('IN000005', 'PE000014', 'RQ000014', 0, 0, 12000, 12000, '2025-08-13 14:28:27'),
('IN000006', 'PE000015', 'RQ000015', 0, 0, 12000, 12000, '2025-08-15 06:24:33'),
('IN000007', '', '', 0, 0, 0, 0, '2025-08-15 06:24:34'),
('IN000008', 'PE000017', 'RQ000017', 0, 85161, 72000, 157161, '2025-08-15 06:26:38'),
('IN000009', '', '', 0, 0, 0, 0, '2025-08-15 06:26:39'),
('IN000010', 'PE000018', 'RQ000018', 0, 47903, 112000, 159903, '2025-08-22 08:45:50'),
('IN000011', 'PE000019', 'RQ000019', 0, 0, 12000, 12000, '2025-08-25 07:46:06'),
('IN000012', '', '', 0, 0, 0, 0, '2025-08-25 07:46:06'),
('IN000013', 'PE000022', 'RQ000022', 0, 51245, 112000, 163245, '2025-09-03 00:35:42'),
('IN000014', 'PE000023', 'RQ000023', 0, 51245, 112000, 163245, '2025-09-03 00:35:53'),
('IN000015', 'PE000024', 'RQ000024', 0, 0, 52000, 52000, '2025-09-09 01:54:57'),
('IN000016', 'PE000021', 'RQ000021', 0, 0, 12000, 12000, '2025-09-11 07:41:34'),
('IN000017', '', '', 0, 0, 0, 0, '2025-09-11 07:41:36'),
('IN000018', 'PE000027', 'RQ000027', 0, 0, 12000, 12000, '2025-09-23 06:44:04'),
('IN000019', '', '', 0, 0, 0, 0, '2025-09-23 06:44:04'),
('IN000020', 'PE000028', 'RQ000028', 0, 0, 12000, 12000, '2025-09-23 06:46:09'),
('IN000021', '', '', 0, 0, 0, 0, '2025-09-23 06:46:10'),
('IN000022', 'PE000029', 'RQ000029', 0, 0, 62000, 62000, '2025-09-29 02:04:03'),
('IN000023', '', '', 0, 0, 0, 0, '2025-09-29 02:04:04'),
('IN000024', 'PE000034', 'RQ000034', 0, 0, 12000, 12000, '2025-10-10 15:10:06'),
('IN000025', 'PE000033', 'RQ000033', 0, 0, 62000, 62000, '2025-10-14 09:10:36'),
('IN000026', '', '', 0, 0, 0, 0, '2025-10-14 09:10:36'),
('IN000027', 'PE000035', 'RQ000035', 0, 0, 12000, 12000, '2025-10-23 01:25:53'),
('IN000028', '', '', 0, 0, 0, 0, '2025-10-23 01:25:54'),
('IN000029', 'PE000036', 'RQ000036', 0, 0, 12000, 12000, '2025-11-01 04:37:27'),
('IN000030', 'PE000038', 'RQ000038', 0, 159500, 32000, 191500, '2025-11-01 06:39:53'),
('IN000031', '', '', 0, 0, 0, 0, '2025-11-01 06:39:54'),
('IN000032', 'PE000039', 'RQ000039', 0, 0, 52000, 52000, '2025-11-11 02:36:41'),
('IN000033', '', '', 0, 0, 0, 0, '2025-11-11 02:36:42'),
('IN000034', 'PE000040', 'RQ000040', 0, 104500, 52000, 156500, '2025-11-11 02:40:12'),
('IN000035', '', '', 0, 0, 0, 0, '2025-11-11 02:40:13'),
('IN000036', 'PE000043', 'RQ000043', 0, 138000, 12000, 150000, '2026-01-06 07:07:35'),
('IN000037', 'PE000006', 'RQ000006', 0, 133065, 0, 0, '2026-01-06 07:54:10'),
('IN000038', '', '', 0, 0, 0, 0, '2026-01-06 07:54:11'),
('IN000039', 'PE000012', 'RQ000012', 0, 0, 0, 0, '2026-01-07 07:28:23');

-- --------------------------------------------------------

--
-- Table structure for table `cabang`
--

CREATE TABLE `cabang` (
  `ID` int(4) NOT NULL,
  `CABANG` varchar(6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `cabang`
--

INSERT INTO `cabang` (`ID`, `CABANG`) VALUES
(1, 'sandya');

-- --------------------------------------------------------

--
-- Table structure for table `cron`
--

CREATE TABLE `cron` (
  `id` int(5) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `command` varchar(255) NOT NULL,
  `interval_sec` int(10) NOT NULL,
  `last_run_at` timestamp NULL DEFAULT current_timestamp(),
  `next_run_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `detail_aktivasi_pppoe`
--

CREATE TABLE `detail_aktivasi_pppoe` (
  `IDDETAILAKTIVASIPPPOE` varchar(11) NOT NULL,
  `IDPENGGUNA` varchar(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `detail_aktivasi_queue`
--

CREATE TABLE `detail_aktivasi_queue` (
  `IDDETAILAKTIVASIQUEUE` varchar(11) NOT NULL,
  `IDPENGGUNA` varchar(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `detail_jurnal_harian`
--

CREATE TABLE `detail_jurnal_harian` (
  `IDDETAILJURNAL` varchar(11) NOT NULL,
  `IDPENGGUNA` varchar(11) NOT NULL,
  `DEBET` float NOT NULL,
  `KREDIT` float NOT NULL,
  `TGL` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `detail_komisi_mitra`
--

CREATE TABLE `detail_komisi_mitra` (
  `IDKOMISI` varchar(11) NOT NULL,
  `IDPERMINTAAN` varchar(11) NOT NULL,
  `IDMITRA` varchar(11) NOT NULL,
  `KOMISI` float NOT NULL,
  `TGL` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ip_kosong`
--

CREATE TABLE `ip_kosong` (
  `IPADDRESS` varchar(20) NOT NULL,
  `PARENT` varchar(20) NOT NULL,
  `KATEGORISTOK` varchar(20) NOT NULL,
  `JENISIP` varchar(20) NOT NULL,
  `LOKASIROUTER` varchar(10) NOT NULL,
  `STATUS` int(1) NOT NULL,
  `IDPELANGGAN` varchar(11) NOT NULL,
  `IDDETAILAKTIVASIQUEUE` varchar(11) NOT NULL,
  `IDDETAILAKTIVASIPPPOE` varchar(11) NOT NULL,
  `PROFILPPP` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jabatan`
--

CREATE TABLE `jabatan` (
  `ID` int(4) NOT NULL,
  `IDLEVEL` int(4) NOT NULL,
  `JABATAN` varchar(100) NOT NULL,
  `KET` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `jabatan`
--

INSERT INTO `jabatan` (`ID`, `IDLEVEL`, `JABATAN`, `KET`) VALUES
(1, 1, 'Admin', 'Staf Kuangan & CS'),
(2, 2, 'Manajer Utama', 'Pimpinan Utama'),
(3, 3, 'SPV Gudang', 'Kepala Gudang'),
(4, 4, 'Staf Gudang', 'Staf Pergudangan'),
(5, 3, 'SPV Teknisi', 'Kepala Teknisi'),
(6, 3, 'SPV Survey', 'Kepala Surviyor'),
(7, 5, 'Pelanggan Antena', 'Pelanggan Antena'),
(8, 5, 'Pelanggan', 'Pelanggan Internet'),
(9, 6, 'Mitra Net', 'Mitra Whusnet');

-- --------------------------------------------------------

--
-- Table structure for table `jenka_paket`
--

CREATE TABLE `jenka_paket` (
  `ID` varchar(5) NOT NULL,
  `PARENT_PAKET` varchar(3) NOT NULL,
  `NAME` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `jenka_paket`
--

INSERT INTO `jenka_paket` (`ID`, `PARENT_PAKET`, `NAME`) VALUES
('01.1', '1', 'Pascabayar'),
('01.2', '2', 'INTERNET'),
('1', '0', 'JENIS'),
('2', '0', 'KATEGORI'),
('kateg', '2', 'tes'),
('Praba', '1', 'Prabayar');

-- --------------------------------------------------------

--
-- Table structure for table `jurnalharian`
--

CREATE TABLE `jurnalharian` (
  `KODEJURNAL` varchar(11) NOT NULL,
  `IDPENGGUNA` varchar(11) NOT NULL,
  `KATEGORI` varchar(30) NOT NULL,
  `HARGA` float NOT NULL,
  `QTY` mediumint(9) NOT NULL,
  `TGL` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `KET` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kantor`
--

CREATE TABLE `kantor` (
  `ID` int(11) NOT NULL,
  `KANTOR` varchar(50) NOT NULL,
  `LAT_PERUSAHAAN` varchar(22) NOT NULL,
  `LONG_PERUSAHAAN` varchar(22) NOT NULL,
  `RADIUSINMETER` bigint(3) NOT NULL,
  `ID_CABANG` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kategori_barang`
--

CREATE TABLE `kategori_barang` (
  `KODEKATEGORIBARANG` varchar(11) NOT NULL,
  `BARANG` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kategori_perangkat_jaringan`
--

CREATE TABLE `kategori_perangkat_jaringan` (
  `id` int(2) NOT NULL,
  `nama_perangkat` varchar(50) NOT NULL,
  `fungsi_singkat` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `kategori_perangkat_jaringan`
--

INSERT INTO `kategori_perangkat_jaringan` (`id`, `nama_perangkat`, `fungsi_singkat`) VALUES
(0, 'Default', 'Default'),
(1, 'OLT Sandya', '-');

-- --------------------------------------------------------

--
-- Table structure for table `kode_kontrol_distribusi`
--

CREATE TABLE `kode_kontrol_distribusi` (
  `kode` varchar(20) NOT NULL,
  `nama` varchar(100) DEFAULT NULL,
  `deskripsi` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `kode_kontrol_distribusi`
--

INSERT INTO `kode_kontrol_distribusi` (`kode`, `nama`, `deskripsi`) VALUES
('0', 'default', 'default'),
('X13A', 'Distribusi 13A', '-'),
('X1A', 'Distribusi 1A', '-'),
('X1B', 'Distribusi 1B', '-'),
('X3A', 'Distribusi 3A', '-'),
('X3B', 'Distribusi 3B', '-'),
('X3C', 'Distribusi 3C', '-'),
('X4A', 'Distribusi 4A', '-'),
('X4B', 'Distribusi 4B', '-'),
('X4C', 'Distribusi 4C', '-'),
('X6A', 'Distribusi 6A', '-'),
('X6B', 'Distribusi 6B', '-'),
('X7A', 'Distribusi 7A', '-'),
('X8A', 'Distribusi 8A', '-');

-- --------------------------------------------------------

--
-- Table structure for table `komisi`
--

CREATE TABLE `komisi` (
  `ID` int(2) NOT NULL,
  `IDMITRA` varchar(11) NOT NULL,
  `KOMISI` varchar(20) NOT NULL,
  `JENIS` varchar(10) NOT NULL,
  `KETERANGAN` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `komisi_mitra`
--

CREATE TABLE `komisi_mitra` (
  `IDKOMISI` varchar(11) NOT NULL,
  `IDMITRA` varchar(11) NOT NULL,
  `DEBET` float NOT NULL,
  `KREDIT` float NOT NULL,
  `TGL` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `laporan_pemasangan_wifi`
--

CREATE TABLE `laporan_pemasangan_wifi` (
  `IDREPORT` varchar(15) NOT NULL,
  `IDPENGGUNA` varchar(11) DEFAULT NULL,
  `IDPERMINTAAN` varchar(11) NOT NULL,
  `STATUSPASANG` varchar(10) DEFAULT NULL,
  `BRG_INDOOR` varchar(15) DEFAULT NULL,
  `BRG_OUTDOOR` varchar(30) DEFAULT NULL,
  `SINYAL` varchar(10) DEFAULT NULL,
  `JENIS` varchar(25) DEFAULT NULL,
  `TESTUP` varchar(20) DEFAULT NULL,
  `TESTDOWN` varchar(20) DEFAULT NULL,
  `STATUS` varchar(8) DEFAULT NULL,
  `BTS` varchar(15) DEFAULT NULL,
  `SSID` varchar(30) DEFAULT NULL,
  `FOTOTOWER` varchar(50) DEFAULT NULL,
  `FOTOKABEL` varchar(50) DEFAULT NULL,
  `FOTOROOTER` varchar(50) DEFAULT NULL,
  `FOTOSPEED` varchar(50) NOT NULL,
  `FOTOFORMULIR` varchar(50) NOT NULL,
  `FOTOTTDFORMULIR` varchar(50) NOT NULL,
  `PINGGATEWAY` varchar(30) NOT NULL,
  `PINGGOOGLE` varchar(30) NOT NULL,
  `IPADDR` varchar(30) DEFAULT NULL,
  `MACADDR_ANTENA` varchar(30) DEFAULT NULL,
  `MACADDR_ROOTER` varchar(30) DEFAULT NULL,
  `SNROOTER_FIBER` varchar(30) DEFAULT NULL,
  `NOMOR_ODP` varchar(30) DEFAULT NULL,
  `NOMOR_PORT_ODP` varchar(30) DEFAULT NULL,
  `NOMOR_PORT_OLT` varchar(30) DEFAULT NULL,
  `SIGNAL_WIRELESS` varchar(30) DEFAULT NULL,
  `SIGNAL_KABEL` varchar(30) DEFAULT NULL,
  `HISTORYROOTER` varchar(150) DEFAULT NULL,
  `HISTORYANTENA` varchar(150) DEFAULT NULL,
  `HISTORYDETAIL` varchar(250) DEFAULT NULL,
  `LOKASIPEMANCAR` varchar(50) DEFAULT NULL,
  `KETERANGAN` text NOT NULL,
  `IDODPLOCATION` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `laporan_pemasangan_wifi`
--

INSERT INTO `laporan_pemasangan_wifi` (`IDREPORT`, `IDPENGGUNA`, `IDPERMINTAAN`, `STATUSPASANG`, `BRG_INDOOR`, `BRG_OUTDOOR`, `SINYAL`, `JENIS`, `TESTUP`, `TESTDOWN`, `STATUS`, `BTS`, `SSID`, `FOTOTOWER`, `FOTOKABEL`, `FOTOROOTER`, `FOTOSPEED`, `FOTOFORMULIR`, `FOTOTTDFORMULIR`, `PINGGATEWAY`, `PINGGOOGLE`, `IPADDR`, `MACADDR_ANTENA`, `MACADDR_ROOTER`, `SNROOTER_FIBER`, `NOMOR_ODP`, `NOMOR_PORT_ODP`, `NOMOR_PORT_OLT`, `SIGNAL_WIRELESS`, `SIGNAL_KABEL`, `HISTORYROOTER`, `HISTORYANTENA`, `HISTORYDETAIL`, `LOKASIPEMANCAR`, `KETERANGAN`, `IDODPLOCATION`) VALUES
('681980066b7c8', 'PE000008', 'RQ000008', NULL, NULL, NULL, NULL, 'KABEL', '', '', NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', 'SMN_RQ000716@PurnamaAyuLestari', '', '', 'ZICG10237307', '236 sandia', '6 sandia', 'Olt sandia', '22', '', NULL, NULL, NULL, '', '', ''),
('68b78d5ea0e53', 'PE000022', 'RQ000022', NULL, NULL, NULL, NULL, 'KABEL', '22', '23', NULL, NULL, NULL, NULL, NULL, '8aa23dc3644ed6c0675f3c740a3584ec.jpg', '27d9895bd25dbceec61aa18b38783309.jpg', 'e0fe2af0f7d8eae261b8e966cdae521b.jpg', '808d80431c4f3bae64b4f8f770941566.jpeg', '7', '26', 'SMN_RQ000825_TAMANARUM_FUADACH', NULL, NULL, 'HWTCA9017840', 'OLT 4 PON 2 ODP 164', '7', 'Sandia ', '17', NULL, NULL, NULL, NULL, '', '-', ''),
('68b78d695b056', 'PE000023', 'RQ000023', NULL, NULL, NULL, NULL, 'KABEL', '22', '23', NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '7', '26', 'SMN_RQ000825_TAMANARUM_FUADACH', '', NULL, 'HWTCA9017840', 'OLT 4 PON 2 ODP 164', '7', 'Sandia ', '17', NULL, NULL, NULL, NULL, '', '-', ''),
('695cb4b7743f1', 'PE000043', 'RQ000043', NULL, NULL, NULL, NULL, 'KABEL', '13', '13', NULL, NULL, NULL, NULL, NULL, 'dbf8f56d42436aa919c1308712786bba.jpg', '483315c3e945ed0a727e0c0926e7917b.jpg', 'df248db344c2e560915a170c7fe6496a.jpg', 'b88adce4b65960713f8d02e39d1ec5a0.jpg', '14', '25', 'SMN_RQ000830_BROTONEGARAN_ACHM', NULL, NULL, 'HWTCA901D4E8', 'F1-01-00-PON7-ODP92 (sandia)', '7', 'F1-01-00-PON7-ODP92 (sandia)', '-19', NULL, NULL, NULL, NULL, '', 'Pemasangan selesai ', ''),
('lp688d61b6062d4', 'PE000013', 'RQ000013', NULL, NULL, NULL, NULL, 'KABEL', '12', '12', NULL, NULL, NULL, NULL, NULL, 'c04588ef66d98035c88ce61e1ce9b19e.jpg', 'f63095eadd5b1e4416c088022151ee6e.jpg', '969cd0a145eb5ee8a72a1ae23389c941.jpg', 'd0b461689c327e5cfe86508c9927e495.jpg', '14', '25', '0', NULL, NULL, 'HWTCA90892C8', NULL, NULL, NULL, '-19', NULL, NULL, NULL, NULL, NULL, 'Aktifasi selesai', ''),
('lp689c0f666a164', 'PE000016', 'RQ000016', NULL, NULL, NULL, NULL, 'KABEL', '22', '20', NULL, NULL, NULL, NULL, NULL, '797d0d2653b95f4694ba49e325ad3406.jpg', '83d9d17870931cb93d4b71be53ec0870.jpg', '83f5db7a2c4d44326f6243de6ab37331.jpg', '662f330fecce649d5f1b8d427fd0e8ed.jpg', '14', '23', '0', NULL, NULL, 'ZICG29667709', '0', '0', '0', '-19', NULL, NULL, NULL, NULL, 'INTERKONEKSI SANDYA', 'Aktifasi selesai', ''),
('lp689c7ef8deda0', 'PE000014', 'RQ000014', NULL, NULL, NULL, NULL, 'KABEL', '49', '45', NULL, NULL, NULL, NULL, NULL, 'a3d97c4a54c850901e98776f2cbdc72e.jpg', 'c24d457311560384e7de5df6b8f66005.jpg', '0b40c429cbb25034cd3190d45f44e31b.jpeg', '39e9da50f0a29fbe5d8a3bc4d71ccdc4.jpg', '6', '23', '0', NULL, NULL, 'HWTCA9089418', '0', '0', '0', '-19', NULL, NULL, NULL, NULL, 'INTERKONEKSI SANDYA', 'Aktifasi selesai', ''),
('lp689ecdc04ef77', 'PE000017', 'RQ000017', NULL, NULL, NULL, NULL, 'KABEL', '25', '25', NULL, NULL, NULL, NULL, NULL, 'd2ae8edb680be056e29945db457277e5.jpg', '64aa650df9e6f99bacb3a1294f2e24d1.jpg', '00ccbed759f37da3944842e39c891bf4.jpg', 'a2ce9a421c24d1c5a6e38ba070be69c6.jpg', '3', '3', '0', NULL, NULL, 'ZICG296A1997', '0', '0', '0', '-20', NULL, NULL, NULL, NULL, 'INTERKONEKSI SANDYA', 'Selesai', ''),
('lp689ecf7c4a757', 'PE000015', 'RQ000015', NULL, NULL, NULL, NULL, 'KABEL', '12', '12', NULL, NULL, NULL, NULL, NULL, 'ec12687aa3bcf0bef839415980010a72.jpg', 'df882d3f37807809ae2de175010d8128.jpg', 'cf93795fb56802f21923f32c5c208731.jpg', '0c002e074f831e6558d586ca58349635.jpg', '7', '26', '0', NULL, NULL, 'ZICG2312AA91', '0', '0', '0', '-19', NULL, NULL, NULL, NULL, 'INTERKONEKSI SANDYA', 'Selesai', ''),
('lp68a7f37139dd6', 'PE000018', 'RQ000018', NULL, NULL, NULL, NULL, 'KABEL', '22', '23', NULL, NULL, NULL, NULL, NULL, '23d871ff0b9f2b16d83d9c5f023829ed.jpg', 'c8f922578d66e945124ea4d301d0096b.jpg', 'b1031fa82694a1d62e039af6e4c80b19.jpg', 'e6c2afbb90c477b3d3cb90a9b79eaa09.jpg', '16', '12', '0', NULL, NULL, 'HWTCA901D4D0', '2pon14', '2', '2/14', '21', NULL, NULL, NULL, NULL, 'Siman', '-', ''),
('lp68abe629973f5', 'PE000019', 'RQ000019', NULL, NULL, NULL, NULL, 'KABEL', '23', '22', NULL, NULL, NULL, NULL, NULL, 'eb7492446c9ce1ff6d3931e9efde65f5.jpg', 'cd16a91c8561acf38acac3a68f9cccda.jpg', '71ac96e898fcac1e176bea8d4fabc0f6.jpg', '8b2c38f28d0193240c9bcbd9e3b8f714.jpg', '2', '9', '0', NULL, NULL, 'HWTCDF670148G', 'Odp', '4', '111', '19', NULL, NULL, NULL, NULL, 'Sandia', '-', ''),
('lp68bf6c91a3f9f', 'PE000024', 'RQ000024', NULL, NULL, NULL, NULL, 'KABEL', '12', '12', NULL, NULL, NULL, NULL, NULL, 'af555ebf5fbb385721fdb7ee11f5ea66.jpg', '728718d3b3362c6b24cfa59238945fd1.jpg', '0c800f5b49489a8fdf2f24f095d16e73.jpg', '6b4878979451a30c6347093850543b87.jpg', '14', '26', '0', NULL, NULL, 'HWTCDF67D110', '0', '0', '0', '-19', NULL, NULL, NULL, NULL, 'INTERKONEKSI SANDYA', 'Selesai', ''),
('lp68c147dec90fe', 'PE000021', 'RQ000021', NULL, NULL, NULL, NULL, 'KABEL', '23', '22', NULL, NULL, NULL, NULL, NULL, 'f4edd8cd407b41afb5877c7feabde281.jpg', '5ffd39a575cdb76e086dfdb162eb2e28.jpg', '39fb1cbcb2025ee35b9bc31ed12d0f14.jpg', '00c30782cf9fe50e03a121325946336c.jpg', '6', '9', '0', NULL, NULL, 'HWTCDF67D100', '-', '-', '1/1/1', '20', NULL, NULL, NULL, NULL, 'Sandia ', '-', ''),
('lp68d149b7d4f72', 'PE000027', 'RQ000027', NULL, NULL, NULL, NULL, 'KABEL', '25', '25', NULL, NULL, NULL, NULL, NULL, '6189a7da1c87a00f9fa5b1820844283f.jpg', 'f904d981a76f23d237e3214c373669a8.jpg', '3f9d74a1cccf88ad48be1c84d5869c9b.jpg', '237361604bd9f999e4e6a1bf15591805.jpg', '5', '26', '0', NULL, NULL, 'ZICG2967363B', '0', '0', '0', '-23', NULL, NULL, NULL, NULL, 'INTERKONEKSI SANDYA', 'Selesai', ''),
('lp68d14a93094a1', 'PE000028', 'RQ000028', NULL, NULL, NULL, NULL, 'KABEL', '12', '12', NULL, NULL, NULL, NULL, NULL, '1c1281b04255eb48ae9adccc1ea4846f.jpg', '9c4b1245ce1f3a118930ed115eeb47f6.jpg', '06ab758369f23cf71fc9cc3c35e8ce22.jpg', 'ba4ec1eea81825acc962268cb8ba0efb.jpg', '14', '55', '0', NULL, NULL, 'ZICG2978E034', '0', '0', '0', '-19', NULL, NULL, NULL, NULL, 'INTERKONEKSI SANDYA', 'Selesai', ''),
('lp68d7b25bee791', 'PE000029', 'RQ000029', NULL, NULL, NULL, NULL, 'KABEL', '12', '12', NULL, NULL, NULL, NULL, NULL, 'f4f191995e629fdd5cabac2891acee94.jpg', 'd0b7fd5f226e455cc54d0c2bebf832ff.jpg', 'fff84a011a28807dfdbbd1a868e4ac35.jpg', 'a85b9c50aeb41b3e181939b9ae63f62f.jpg', '5', '9', '0', NULL, NULL, 'CIOT0308DD0F', 'Sandia', '5', '1/3/4', '-20', NULL, NULL, NULL, NULL, 'Siman', '-', ''),
('lp68e8e40323163', 'PE000034', 'RQ000034', NULL, NULL, NULL, NULL, 'KABEL', '12.3', '12.1', NULL, NULL, NULL, NULL, NULL, 'a7bc793f677ed731238f47e76f2a22b5.jpg', 'a5e16e1872ecd7854d55876eb69ed844.jpg', '10dfe97798efd7164e5ab76e9e93b920.jpg', 'e46c135ba057ee49c3bafe8ef2a22ed8.jpg', '17', '23', '0', NULL, NULL, 'ZICG29711781', 'Sandya', '10', '1', '-21', NULL, NULL, NULL, NULL, 'Interkoneksi sandya', 'Pemasangan sudah selesai. Uang aktifasi dibawa yusron', ''),
('lp68ee0d78aebd4', 'PE000033', 'RQ000033', NULL, NULL, NULL, NULL, 'KABEL', '23.2', '24.3', NULL, NULL, NULL, NULL, NULL, '4b67bcf1c50a64d41b818e549a98283f.jpg', '590c7ec0f20ff126c0aa66afc86996ac.jpg', '3ee106c708f60a3550fd28378558b744.jpg', '791cf1e03da0e53bde1d57c6caf8f136.jpg', '11', '23', '0', NULL, NULL, 'HWTCDF6A9CA8', 'F1-02-00-PON1-ODP54', '6', '1', '-20', NULL, NULL, NULL, NULL, 'Sandya. Ina lite', 'Pemasangan sudah selesai. Pembawa uang imam. Paket 150.25mbbs', ''),
('lp68f8e052b60ed', 'PE000035', 'RQ000035', NULL, NULL, NULL, NULL, 'KABEL', '12.3', '12.6', NULL, NULL, NULL, NULL, NULL, '1ce02ac99126eff662f30b0f99841414.jpg', '92f1d67a74195f5f20fd375e94b8b59c.jpg', '003ca886aa9da5362025d3bc5a2055be.jpg', '3885079c5695b30b7641e6b936eaf1ff.jpg', '16', '29', '0', NULL, NULL, 'HWTCDF6A1C48', 'ODPF1-01-00-PON7-ODP92', '8', '7', '-18', NULL, NULL, NULL, NULL, 'Sandya', 'Pemasangan sudah selesai', ''),
('lp6904bb03064ec', 'PE000038', 'RQ000038', NULL, NULL, NULL, NULL, 'KABEL', '24.2', '23.4', NULL, NULL, NULL, NULL, NULL, '6db9791107b20eeea28758ff1907beb1.jpg', 'd8f9d1365dc2e9d4f962def72a48466a.jpg', '54fae5bba51821720f39ace026bece97.jpg', '72118b3ac5a20b16ae6592b372930753.jpg', '18', '23', '0', NULL, NULL, 'HWTCDF6A18E0', 'F1-OLT2-PON14-ODP149', '4', '14', '-18', NULL, NULL, NULL, NULL, 'Sandya(ina lite) ', 'Pemasangan sudah selesai', ''),
('lp6904bd8d137fd', 'PE000036', 'RQ000036', NULL, NULL, NULL, NULL, 'KABEL', '12.4', '12.8', NULL, NULL, NULL, NULL, NULL, '4ad9ae53c432aa0dad08d677bf4e4135.jpg', '77f132f90166fdc55e1c6b1a9029f92d.jpg', 'a0a414f8bae2a5b1890bcd8a3b2f18e5.jpg', '22e8ebbf5c897cdba999145e60a37263.jpg', '17', '24', '0', NULL, NULL, 'HWTCDF6A43A8', 'F1-OLT1-PON13-ODP80', '0', '13', '-17', NULL, NULL, NULL, NULL, 'OLT Sandya(ina lite) ', 'Pemasangan sudah selesai', ''),
('lp6910bc081515a', 'PE000040', 'RQ000040', NULL, NULL, NULL, NULL, 'KABEL', '23.5', '23.3', NULL, NULL, NULL, NULL, NULL, '6050588febfbdd4ca9d4cffffe86b463.jpg', '9f34052116bea3212bdd398570f15be2.jpg', '73c79a8def08f5411c325041fa32ff00.jpg', '0dbd8df50dc65dd92bc70d00c9e5b0b3.jpg', '16', '21', '0', NULL, NULL, 'HWTCDF6A7A68', 'ODP284', '1', '3', '-18', NULL, NULL, NULL, NULL, 'Sandya', 'Pemasangan sudah selesai', ''),
('lp691280521182d', 'PE000039', 'RQ000039', NULL, NULL, NULL, NULL, 'KABEL', '14.3', '14.6', NULL, NULL, NULL, NULL, NULL, '5f657e031b2be2f9411c5f29c756aad7.jpg', '3f08410a249057cdc19141f40a2042b5.jpg', 'bebb96f56c91821e8b91a7370759db8e.jpg', '300fbb9fbc4b8fc807c11a331e13e224.jpg', '17', '24', '0', NULL, NULL, 'HWTCDF6A1958', 'F1-OLT2-PON14-ODP149', '5', '14', '-19', NULL, NULL, NULL, NULL, 'Sandya', 'Pemasangan sudah selesai. ', ''),
('lp695cbd7211308', 'PE000006', 'RQ000006', NULL, NULL, NULL, NULL, 'KABEL', '13', '13', NULL, NULL, NULL, NULL, NULL, '75e2235833d48530eae083f1ba3afc48.png', '9e45705ebbce5744024211bcc87567d0.png', 'be860b16c47f4d535ffe248fd2da01fb.png', '87b3c6fefa8a95bdaf31a2447172b7cc.png', '12', '123', '0', NULL, NULL, '0', '0', '0', '0', '-22', NULL, NULL, NULL, NULL, 'Interkoneksi sandya', 'Selsai', ''),
('lp695cc07f5b6ee', 'PE000012', 'RQ000012', NULL, NULL, NULL, NULL, 'KABEL', '13', '13', NULL, NULL, NULL, NULL, NULL, '63747f04266182fc3bec3ffc7c7053a9.png', 'd49fe6262b3caa7f38ef88f776cc0a37.png', '1ab60a54586f9db79c9fbe83bcf03aba.png', '3cc37f790f8909da3060e7a6397b2f5b.png', '12', '133', '0', NULL, NULL, '0', '0', '0', '0', '-23', NULL, NULL, NULL, NULL, 'Interkoneksi sandya', 'Selsai', ''),
('lpw67dac915cb86', 'PE000001', 'RQ000001', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, '', '', ''),
('lpw6809b46886f7', 'PE000004', 'RQ000004', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, '', '', ''),
('lpw6809b5846b00', 'PE000005', 'RQ000005', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, '', '', ''),
('lpw6818b4d3a721', 'PE000007', 'RQ000007', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, '', '', ''),
('lpw682add00b5e1', 'PE000009', 'RQ000009', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, '', '', ''),
('lpw684fc4856805', 'PE000010', 'RQ000010', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, '', '', ''),
('lpw685b9d5e004b', 'PE000011', 'RQ000011', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, '', '', ''),
('lpw68aeb35968d0', 'PE000020', 'RQ000020', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', ''),
('lpw68c0d07a63b2', 'PE000025', 'RQ000025', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', ''),
('lpw68c4f8785439', 'PE000026', 'RQ000026', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', ''),
('lpw68d4b61a05b9', 'PE000030', 'RQ000030', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', ''),
('lpw68d9dea3e8bb', 'PE000031', 'RQ000031', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', ''),
('lpw68da3c63b5f9', 'PE000032', 'RQ000032', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', ''),
('lpw68fc3d4f8caa', 'PE000037', 'RQ000037', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', ''),
('lpw691c0cf17d28', 'PE000041', 'RQ000041', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', ''),
('lpw692d166b1768', 'PE000042', 'RQ000042', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', ''),
('lpw6989478ee3ac', 'PE000044', 'RQ000044', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '');

-- --------------------------------------------------------

--
-- Table structure for table `level`
--

CREATE TABLE `level` (
  `ID` int(11) NOT NULL,
  `LEVEL` varchar(10) DEFAULT NULL,
  `ICON` varchar(30) NOT NULL,
  `KATEGORI` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `level`
--

INSERT INTO `level` (`ID`, `LEVEL`, `ICON`, `KATEGORI`) VALUES
(1, 'Admin', 'bx bx-user-pin', '1'),
(2, 'Manajer', 'bx bx-user-voice', '1'),
(3, 'SPV', 'bx bx-sitemap', '1'),
(4, 'Staf', 'bx bxs-group', '1'),
(5, 'Customer', 'bx', '1'),
(6, 'Mitra', '', '1'),
(7, 'NOC', '', '1');

-- --------------------------------------------------------

--
-- Table structure for table `merk_barang`
--

CREATE TABLE `merk_barang` (
  `IDMERK` varchar(11) NOT NULL,
  `KATEGORIBARANG` varchar(11) NOT NULL,
  `NAMABARANG` varchar(150) DEFAULT NULL,
  `MODEL` varchar(150) NOT NULL,
  `TYPE` varchar(150) NOT NULL,
  `SERIALNUMBER` varchar(15) NOT NULL,
  `VERSI` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `nomor_port_odp`
--

CREATE TABLE `nomor_port_odp` (
  `ID` mediumint(8) UNSIGNED NOT NULL,
  `IDODPLOCATION` varchar(10) NOT NULL,
  `NOMOR_PORT_ODP` varchar(7) NOT NULL,
  `PARENT` varchar(7) NOT NULL,
  `IDPENGGUNA` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `noonu1024`
--

CREATE TABLE `noonu1024` (
  `id` int(4) NOT NULL,
  `noonu` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `odp_location`
--

CREATE TABLE `odp_location` (
  `IDLOCATION` varchar(20) NOT NULL,
  `LATITUDE` varchar(50) NOT NULL,
  `LONGITUDE` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `olt_aktifasi`
--

CREATE TABLE `olt_aktifasi` (
  `IDAKTIFASI` varchar(25) NOT NULL,
  `IDPELANGGAN` varchar(20) NOT NULL,
  `INDEXONU` varchar(25) NOT NULL,
  `NOONU` int(11) NOT NULL,
  `INDEXONUAKTIF` varchar(12) NOT NULL,
  `IDMESIN` varchar(15) NOT NULL,
  `SN` varchar(20) NOT NULL,
  `IDONUREGISTER` varchar(15) NOT NULL,
  `JENISDIAL` varchar(15) NOT NULL,
  `VLAN` varchar(10) NOT NULL,
  `PROFIL_PPP` varchar(20) NOT NULL,
  `PPPOE_USERNAME` varchar(25) NOT NULL,
  `PPPOE_PASSWORD` varchar(200) NOT NULL,
  `PAKET` varchar(10) NOT NULL,
  `IPADDRESS` varchar(11) NOT NULL,
  `LOKASIOLT` varchar(10) NOT NULL,
  `PROFIL_KONEKSIOLT` varchar(5) NOT NULL,
  `TYPEMODEM` varchar(50) NOT NULL,
  `PROFIL_KONEKSI` varchar(15) DEFAULT NULL,
  `FLAGOLT` tinyint(1) DEFAULT NULL,
  `FLAGROUTER` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `olt_check_dbm`
--

CREATE TABLE `olt_check_dbm` (
  `ID` varchar(25) NOT NULL,
  `IDMESIN` varchar(15) NOT NULL,
  `INDEXONU.SLOT.REGISTER` varchar(25) NOT NULL,
  `IS_EXECUTED` tinyint(1) NOT NULL,
  `RX_POWER` varchar(255) DEFAULT NULL,
  `UPLOAD_RX` varchar(20) DEFAULT NULL,
  `UPLOAD_TX` varchar(20) DEFAULT NULL,
  `UPLOAD_ATTENUATION` varchar(20) DEFAULT NULL,
  `DOWN_RX` varchar(20) DEFAULT NULL,
  `DOWN_TX` varchar(20) DEFAULT NULL,
  `DOWN_ATTENUATION` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `olt_newSignal`
--

CREATE TABLE `olt_newSignal` (
  `ID` varchar(5) NOT NULL,
  `INTERFACES` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `olt_report_signal`
--

CREATE TABLE `olt_report_signal` (
  `ID` varchar(25) NOT NULL,
  `IDMESIN` varchar(15) NOT NULL,
  `TIME` varchar(20) NOT NULL,
  `INDEXONU.SLOT.REGISTER` varchar(25) NOT NULL,
  `RX_POWER` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `olt_report_state`
--

CREATE TABLE `olt_report_state` (
  `ID` varchar(12) NOT NULL,
  `IDMESIN` varchar(15) NOT NULL,
  `TIME` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `INDEXONU.SLOT.REGISTER` varchar(25) DEFAULT NULL,
  `PHASE_STATE` varchar(15) DEFAULT NULL,
  `OMCC_STATE` varchar(15) DEFAULT NULL,
  `CHANNEL` varchar(15) DEFAULT NULL,
  `ADMIN_STATE` varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `olt_slot_register`
--

CREATE TABLE `olt_slot_register` (
  `IDMESIN` varchar(15) NOT NULL,
  `INDEXONU` varchar(25) NOT NULL,
  `NOONU` int(4) NOT NULL,
  `LOKASIOLT` varchar(15) NOT NULL,
  `JENISLAYANAN` varchar(15) NOT NULL,
  `JENISDIAL` varchar(10) NOT NULL,
  `IDPELANGGAN` varchar(11) NOT NULL,
  `NAMAPELANGGAN` varchar(150) NOT NULL,
  `SN` varchar(20) NOT NULL,
  `PHASE_STATE` varchar(15) NOT NULL,
  `ADMIN_STATE` varchar(15) NOT NULL,
  `OMCC_STATE` varchar(15) NOT NULL,
  `CHANNEL` varchar(10) NOT NULL,
  `RX_POWER` varchar(255) NOT NULL,
  `PREVIOUS_RXPOWER` varchar(20) NOT NULL,
  `UPLOAD_RX` varchar(20) DEFAULT NULL,
  `UPLOAD_TX` varchar(20) DEFAULT NULL,
  `UPLOAD_ATTENUATION` varchar(20) DEFAULT NULL,
  `DOWN_RX` varchar(20) DEFAULT NULL,
  `DOWN_TX` varchar(20) DEFAULT NULL,
  `DOWN_ATTENUATION` varchar(20) DEFAULT NULL,
  `STATUS_SIGNAL` varchar(35) DEFAULT NULL,
  `PROFILKONEKSI` varchar(10) NOT NULL,
  `VLAN` varchar(20) NOT NULL,
  `USERPPP` varchar(50) NOT NULL,
  `PASSPPP` varchar(50) NOT NULL,
  `PROFILPPP` varchar(20) NOT NULL,
  `IPADDRESS` varchar(11) NOT NULL,
  `PAKETQUEUE` varchar(10) NOT NULL,
  `PARENT` varchar(25) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `olt_slot_unregister`
--

CREATE TABLE `olt_slot_unregister` (
  `IDMESIN` varchar(15) NOT NULL,
  `INDEXONU` varchar(25) NOT NULL,
  `SN` varchar(20) NOT NULL,
  `PHASE_STATE` varchar(15) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `paket`
--

CREATE TABLE `paket` (
  `KODEPAKET` varchar(11) NOT NULL,
  `NAMA_PAKET` varchar(190) DEFAULT NULL,
  `JENIS_PAKET` varchar(50) NOT NULL,
  `KATEGORI_PAKET` varchar(50) NOT NULL,
  `HARGA` int(15) DEFAULT NULL,
  `SPEEDUP` int(15) DEFAULT NULL,
  `SPEEDDOWN` int(15) DEFAULT NULL,
  `LIMITUP` int(15) DEFAULT NULL,
  `LIMITDOWN` int(15) DEFAULT NULL,
  `PROFILOLT` varchar(30) NOT NULL,
  `PROFILPPP` varchar(30) NOT NULL,
  `BONUS` varchar(130) NOT NULL,
  `KETERANGAN` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `paket`
--

INSERT INTO `paket` (`KODEPAKET`, `NAMA_PAKET`, `JENIS_PAKET`, `KATEGORI_PAKET`, `HARGA`, `SPEEDUP`, `SPEEDDOWN`, `LIMITUP`, `LIMITDOWN`, `PROFILOLT`, `PROFILPPP`, `BONUS`, `KETERANGAN`) VALUES
('PK000001', 'default', '01.1', '01.2', 0, 0, 0, 0, 0, '', '', '', ''),
('PK000002', 'Whus Speed Up To 25', 'Praba', '01.2', 165000, 25000, 25000, 25000, 25000, '', '', '', 'Paket per 2025');

-- --------------------------------------------------------

--
-- Table structure for table `penagihan`
--

CREATE TABLE `penagihan` (
  `IDTAGIHAN` varchar(15) NOT NULL,
  `IDPELANGGAN` varchar(11) NOT NULL,
  `IDPERMINTAAN` varchar(11) NOT NULL,
  `TGLPENAGIHAN` date DEFAULT NULL,
  `TOTALBULANAN` float DEFAULT NULL,
  `STATUS` varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pengguna`
--

CREATE TABLE `pengguna` (
  `IDPENGGUNA` varchar(11) NOT NULL,
  `IDJABATAN` varchar(11) NOT NULL,
  `IDCABANG` varchar(11) NOT NULL,
  `NAMADEPAN` varchar(50) DEFAULT NULL,
  `NAMABELAKANG` varchar(50) NOT NULL,
  `REKOMENDASI` varchar(11) NOT NULL,
  `HP` varchar(15) DEFAULT NULL,
  `TLP` varchar(15) DEFAULT NULL,
  `IDWILAYAH` varchar(15) NOT NULL,
  `KOTA` varchar(100) DEFAULT NULL,
  `KEC` varchar(100) DEFAULT NULL,
  `DESA` varchar(100) DEFAULT NULL,
  `ALMT` varchar(255) DEFAULT NULL,
  `EMAIL` varchar(150) DEFAULT NULL,
  `STATUSAKUN` varchar(12) DEFAULT NULL,
  `NAMAPERUSAHAAN` varchar(150) DEFAULT NULL,
  `NPWP` varchar(16) DEFAULT NULL,
  `KTP_SIM` varchar(16) DEFAULT NULL,
  `FOTOKTP` varchar(50) NOT NULL,
  `JENISKELAMIN` varchar(2) NOT NULL,
  `JENISPELANGGAN` varchar(8) NOT NULL,
  `FOTO` varchar(30) NOT NULL,
  `inserted_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `pengguna`
--

INSERT INTO `pengguna` (`IDPENGGUNA`, `IDJABATAN`, `IDCABANG`, `NAMADEPAN`, `NAMABELAKANG`, `REKOMENDASI`, `HP`, `TLP`, `IDWILAYAH`, `KOTA`, `KEC`, `DESA`, `ALMT`, `EMAIL`, `STATUSAKUN`, `NAMAPERUSAHAAN`, `NPWP`, `KTP_SIM`, `FOTOKTP`, `JENISKELAMIN`, `JENISPELANGGAN`, `FOTO`, `inserted_at`) VALUES
('PE000001', '8', '1', 'dinar-markondang', '', '', '628977955002', NULL, '3502120014', NULL, NULL, NULL, 'Jl. Ponorogo - Solo, Plosojenar, Kel. Kauman, Kec. Ponorogo', '123@gmail.com', '', NULL, '0', '3502120509950001', '9bbfb852175cb02146252af1fccd0ebd.jpeg', 'L', 'RUMAHAN', '', '2025-03-19 13:39:35'),
('PE000004', '8', '1', 'eva-rodiana-sari', '', '', 'null', NULL, '3502170009', NULL, NULL, NULL, ' Jl.Bangka, No. 11 A, Kel. Tamanarum, Kec. Ponorogo', '123@gmail.com', '', NULL, '0', '3502177009870003', 'a09a55ada4b97ac0c579278352bfe6d9.jpeg', 'P', 'RUMAHAN', '', '2025-04-24 03:47:52'),
('PE000005', '8', '1', 'eva-rosdiana-sari', '', '', 'null', NULL, '3502170009', NULL, NULL, NULL, 'Jl.Bangka, No. 11 A, Kel. Tamanarum, Kec. Ponorogo', '123@gmail.com', '', NULL, '0', '3502177009870003', 'cd337d1aeffefa4ed3321a9bcb923ca0.jpeg', 'P', 'RUMAHAN', '', '2025-04-24 03:52:36'),
('PE000006', '8', '1', 'wahyu-aulia-zahro', '', '', '6282233357153', NULL, '3502170014', NULL, NULL, NULL, 'Jl. Krakatau No.50 B, RT/RW : 003/001, Kel. Banyudono, Kec. Ponorogo', '123@gmail.com', '', NULL, '0', '3502174205030001', 'd146c09f7b9b28c0dfacefd6b7ec1c71.jpeg', 'P', 'RUMAHAN', '', '2025-05-05 12:52:04'),
('PE000007', '8', '1', 'muhammad-syifaa-hanafi', '', '', '6282333524970', NULL, '3502170014', NULL, NULL, NULL, 'Jl. Sulawesi, Kel. Banyudono, Kec. Ponorogo', '123@gmail.com', '', NULL, '0', '3524243107030001', 'c6e780f5ebcf8d7866151916ca5586eb.jpeg', 'L', 'RUMAHAN', '', '2025-05-05 12:53:39'),
('PE000008', '8', '1', 'purnama-ayu-lestari-putri', '', '', '6281774844126', NULL, '3502170005', 'KABUPATEN PONOROGO', 'PONOROGO', 'SURODIKRAMAN', 'Jl. Ramawijaya Gg. I No.31F, Pesantren, Surodikraman', '0', 'aktif', NULL, '0', '3502174707000001', '', 'P', 'RUMAHAN', '', '2025-05-06 03:20:38'),
('PE000009', '8', '1', 'ihda-ainin-nadhira', '', '', '6287858611922', NULL, '3502170002', NULL, NULL, NULL, 'Ponorogo,Brakungan, Brotonegaran, Kec. Ponorogo, Kabupaten Ponorogo, Jawa Timur', '123@gmail.com', '', NULL, '0', '3502086510050001', 'd3946d2b048a39c4c4465280d8524d17.jpg', 'P', 'RUMAHAN', '', '2025-05-19 07:25:52'),
('PE000010', '8', '1', 'nindia-galuh-prismadani', '', '', '6282257982328', NULL, '3502170010', NULL, NULL, NULL, 'Perumahan Kriyamaha Residence No. C1, Jl. Cakraninggrat, Area Sawah, Kauman, Kec. Ponorogo, Kabupaten Ponorogo, Jawa Timur ', '123@gmail.com', '', NULL, '0', '3502136205950001', 'eef8dd398fc351f81367261af990c1ee.jpeg', 'P', 'RUMAHAN', '', '2025-06-16 07:15:17'),
('PE000011', '8', '1', 'anang-kurniawan', '', '', 'null', NULL, '3502120016', NULL, NULL, NULL, 'Jl Diponegoro 48 , Dusun Merbot RT 01 RW 01, Desa Kauman, Kec. Kauman, Kab. Ponorogo, Jawa Timur', '123@gmail.com', '', NULL, '0', '3502171805750002', '72de0c9959116f093594920f76228e49.jpg', 'L', 'RUMAHAN', '', '2025-06-25 06:55:26'),
('PE000012', '8', '1', 'ragil-cahya-adi-prastya', '', '', '62895397047938', NULL, '3502170010', NULL, NULL, NULL, 'Jl. Kyai Mojo No. 28,Sukun, Kauman, Kec. Ponorogo, Kabupaten Ponorogo, Jawa Timur', '123@gmail.com', '', NULL, '0', '3502082908940001', 'f069286c36aff24ad563573aa31ebe59.jpg', 'L', 'RUMAHAN', '', '2025-07-01 08:37:28'),
('PE000013', '8', '1', 'afdhal-fardhika-achmad', '', '', '6285134478038', NULL, '3502170013', NULL, 'PONOROGO', 'MANGKUJAYAN', 'Jl. Bali, Mangkujayan, Kec. Ponorogo, Kabupaten Ponorogo', '....@gmail.com', '', NULL, '0', '6472050702010006', '5d3ae94f2e969549e7c6bc96e35d4bd6.jpeg', 'L', 'RUMAHAN', '', '2025-08-08 07:52:29'),
('PE000014', '8', '1', 'fuad-aan-maulana-rodli', '', '', '6285235205999', NULL, '3502170011', NULL, 'PONOROGO', 'TAMBAKBAYAN', 'Ponpes Hikmatul Qur\'an Spesialis Tahfidz Entrepreneur & Taubater, Jl. Astrokoro No.39, Krajan, Tambakbayan, Kec. Ponorogo, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502211506060001', 'f3b4716ddc80acd3b96a9449a103d114.jpeg', 'L', 'RUMAHAN', '', '2025-08-15 08:28:01'),
('PE000015', '8', '1', 'bambang-saktiawan', '', '', '6285852516896', NULL, '3502170013', NULL, NULL, NULL, 'Jl. Ternate No.35b, Krajan, Mangkujayan, Kec. Ponorogo, Kabupaten Ponorogo', '....@gmail.com', '', NULL, '0', '3502170110730003', 'fff24c08dfed0467235f22860f482491.jpeg', 'L', 'RUMAHAN', '', '2025-08-02 08:54:28'),
('PE000016', '8', '1', 'reza-wahyu-dwi-ardiansah', '', '', '6287733882144', NULL, '3502170011', NULL, NULL, NULL, 'Perumahan Green Palace B4, Jl. Astrokoro, Tambakbayan, Kec. Ponorogo, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3577011206000001', 'efc874065f2782fc1efc6c30e957d891.jpeg', 'L', 'RUMAHAN', '', '2025-08-06 08:58:12'),
('PE000017', '8', '1', 'citra-noriya', '', '', '6287734182287', NULL, '3502170011', NULL, NULL, NULL, 'Perumahan Green Palace, Tambakbayan, Kec. Ponorogo, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502095011000001', '4d589df85368e4cf6e0d17df03c83d62.jpeg', 'P', 'RUMAHAN', '', '2025-08-12 09:03:17'),
('PE000018', '8', '1', 'sri-mulyani', '', '', '6283845924877', NULL, '3502170014', NULL, NULL, NULL, 'Jl. Krakatau, Banyudono,Kec. Ponorogo, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502094611610001', '0588b71ad1b531346decfb2ba1477edb.jpg', 'P', 'RUMAHAN', '', '2025-08-22 02:23:42'),
('PE000019', '8', '1', 'fatkhul-zamroni', '', '', '6282141413655', NULL, '3502170014', NULL, NULL, NULL, 'Jl. KS. Suitubun, No. 9, RT/RW : 002/002, Kel. Banyudono, Kec. Ponorogo', '123@gmail.com', '', NULL, '0', '3502171605810005', '5551804c6b4fa975059785e0f90573f5.jpeg', 'L', 'RUMAHAN', '', '2025-08-23 12:22:31'),
('PE000020', '8', '1', 'aries-prasetyawan', '', '', '6285854634585', NULL, '3502170011', NULL, NULL, NULL, 'Jl. Trunojoyo No.126,Tambakbayan, Kec. Ponorogo, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502120204870002', '999bdcfa46a6b4b6e3e665dc8d142ea7.jpeg', 'L', 'RUMAHAN', '', '2025-08-27 07:27:21'),
('PE000021', '8', '1', 'pangestu-adita-pratama', '', '', '6285655578571', NULL, '3502170002', NULL, NULL, NULL, 'Jl. Sadewo No.12, Krajan, Brotonegaran, Kec. Ponorogo, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502162905020001', 'd34dfb70a35628bc43d0b1f1e77ec778.jpg', 'L', 'RUMAHAN', '', '2025-08-30 07:06:29'),
('PE000022', '8', '1', 'fuad-achmadi', '', '', '628819246252', NULL, '3502170009', 'KABUPATEN PONOROGO', 'PONOROGO', 'TAMAN ARUM', 'Jalan Bhayangkara GG 2 no 11 Tamanarum ponorogo kota', '123@gmail.com', 'aktif', NULL, '', '502170103830003', '6bd4c885984b9556a63d57a0c1e309d0.jpg', 'L', 'RUMAHAN', '', '2025-09-03 00:37:32'),
('PE000023', '8', '1', 'fuad-achmadi', '', '', '628819246252', NULL, '3502170009', 'KABUPATEN PONOROGO', 'PONOROGO', 'TAMAN ARUM', 'Jalan Bhayangkara GG 2 no 11 Tamanarum ponorogo kota', '123@gmail.com', 'aktif', NULL, '', '502170103830003', '', 'L', 'RUMAHAN', '', '2025-09-03 00:35:53'),
('PE000024', '8', '1', 'anita-dewi-rozalia-putri', '', '', '6282229278448', NULL, '3502170013', NULL, NULL, NULL, 'l. Kalimantan No.1b RT. 05/RW. 01,Mangkujayan, Kec. Ponorogo, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502175807940002', '11eedbdd2856f7b8118bd51a1f7b283a.jpeg', 'P', 'RUMAHAN', '', '2025-09-05 07:00:20'),
('PE000025', '8', '1', 'samsudin', '', '', '6281359993131', NULL, '3502170002', NULL, NULL, NULL, 'Jl. Sadewo, Krajan,Brotonegaran, Kec. Ponorogo, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502172910690001', '8e86d553cb5f6d276b3e7a569bcad146.jpeg', 'L', 'RUMAHAN', '', '2025-09-10 01:12:26'),
('PE000026', '8', '1', 'alma-irsyadul-haqqi', '', '', '6289527603088', NULL, '3502170002', NULL, NULL, NULL, 'Jl. Imam Bonjol Gg. IV, Brakungan, Brotonegaran, Kec. Ponorogo, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502172712050001', 'd6b85174b1a0a821ce21e9f4f4271ddd.jpeg', 'L', 'RUMAHAN', '', '2025-09-13 04:52:08'),
('PE000027', '8', '1', 'farid-wajdi-ardjono', '', '', '6282225246003', NULL, '3502170011', NULL, NULL, NULL, 'Jl. Astrokoro No.40, Tambakbayan, Kec. Ponorogo, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502171105890003', '4a607781bab1bfaf069aaa8cb9fd6eb3.jpg', 'L', 'RUMAHAN', '', '2025-09-13 07:27:41'),
('PE000028', '8', '1', 'muh-busri', '', '', '628123439330', NULL, '3502170014', NULL, NULL, NULL, 'Jl. Bhayangkara Gg. I No.3, Banyudono, Kec. Ponorogo, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502170706580004', '14fbbb130f76e3b13ae77c50476db7fa.jpeg', 'L', 'RUMAHAN', '', '2025-09-15 07:52:53'),
('PE000029', '8', '1', 'siti-aminah-romdiati', '', '', '6285735090530', NULL, '3502170002', NULL, NULL, NULL, 'Jl. Semar No.31B, RT.04/RW.03, Krajan, Brotonegaran, Kec. Ponorogo, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502025304910001', 'ec20316e0a66a92ac566bb42965068eb.jpeg', 'P', 'RUMAHAN', '', '2025-09-22 06:42:10'),
('PE000030', '8', '1', 'mulyono', '', '', '6285755813498', NULL, '3502120016', NULL, NULL, NULL, 'Tengah,Kauman, Kec. Kauman, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '8311154408710', '6f7f7fb42773941e2396181465be6695.png', 'L', 'RUMAHAN', '', '2025-09-25 03:25:14'),
('PE000031', '8', '1', 'siti-komariyah', '', '', '6288806658457', NULL, '3502170014', NULL, NULL, NULL, 'Kalisa Silver Ponorogo, Jl. Soekarno Hatta No.241, Banyudono, Kec. Ponorogo, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502136709870002', '58a6a4241388e214ab90f83381520919.jpeg', 'P', 'RUMAHAN', '', '2025-09-29 01:19:31'),
('PE000032', '8', '1', 'budi-santoso', '', '', '6289603230614', NULL, '3502170014', NULL, NULL, NULL, 'Potong Rambut Dion, Jl. Sumatra No. 33A,Banyudono, Kec. Ponorogo, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502171011640003', '96be81f6db4a78012de8738eb32314dc.jpeg', 'L', 'RUMAHAN', '', '2025-09-29 07:59:31'),
('PE000033', '8', '1', 'bima-orbita-dirgantara', '', '', '6287758404402', NULL, '3502170010', NULL, NULL, NULL, 'Perumahan Citra Puri Kencana No. 23, Kauman, Kec. Ponorogo, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502110508950001', 'd3e0fb2d12d745293e08588620c7c778.jpeg', 'L', 'RUMAHAN', '', '2025-10-02 09:31:04'),
('PE000034', '8', '1', 'suwarni', '', '', '6281336663290', NULL, '3502170010', NULL, NULL, NULL, 'Jl. Pangeran Hidayatulloh No. 40, Kauman, Kec. Ponorogo, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502175509650001', 'b9cb1e4a884f2947e3b9c3349e598c95.jpeg', 'P', 'RUMAHAN', '', '2025-10-09 07:00:14'),
('PE000035', '8', '1', 'wiji-asih', '', '', '6287755702244', NULL, '3502170002', NULL, NULL, NULL, 'Jl. Semar No. 12B, Brotonegaran, Kec. Ponorogo, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502176506840004', '558419c0b079042898bd226fd881faff.jpeg', 'P', 'RUMAHAN', '', '2025-10-15 04:38:52'),
('PE000036', '8', '1', 'bayu-prasetyo', '', '', '6281234118199', NULL, '3502170002', NULL, NULL, NULL, 'Apotek Dexa Jl. Imam Bonjol No. 85, Brakungan, Brotonegaran, Kec. Ponorogo, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502051403850004', '901b425f841a7ae721c9db48157051d9.png', 'L', 'RUMAHAN', '', '2025-10-18 07:10:57'),
('PE000037', '8', '1', 'awang-yudha-aji-kusuma', '', '', '628817120172', NULL, '3502170013', NULL, NULL, NULL, 'Jl. Urip Sumoharjo 12-10,Temengungan, Mangkujayan, Kec. Ponorogo', '123@gmail.com', '', NULL, '0', '3502051307040001', 'a04ba2811f94e7daca0b3fad3147a050.jpeg', 'L', 'RUMAHAN', '', '2025-10-25 03:00:31'),
('PE000038', '8', '1', 'alvian-rozaq', '', '', '6289523231927', NULL, '3502170014', NULL, NULL, NULL, 'Jl. Soekarno-Hatta, Banyudono, Kec. Ponorogo, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '6310100103020001', '6978b17a3eb921e2bfbd1d3e9f645e93.jpeg', 'L', 'RUMAHAN', '', '2025-10-27 04:51:53'),
('PE000039', '8', '1', 'kevin-herlambang-dwi', '', '', '6281259797987', NULL, '3502170014', NULL, NULL, NULL, 'Jl. Soekarno-Hatta Gg. 6, Banyudono, Kec. Ponorogo, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502170504960003', '686f3587368f7c2b3627dfb0a6d11daa.jpeg', 'L', 'RUMAHAN', '', '2025-10-27 06:33:22'),
('PE000040', '8', '1', 'aji-wibowo', '', '', '6281330385758', NULL, '3502120013', NULL, NULL, NULL, 'Jl. Gerdon, Sumoroto, Somoroto, Kec. Kauman, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '15449703000171', 'efaf39b8fb567fb5eac37dc9b05be93c.jpeg', 'L', 'RUMAHAN', '', '2025-10-28 04:40:40'),
('PE000041', '8', '1', 'firdaus-hasan-al-bana', '', '', '6281357694546', NULL, '3502120013', NULL, NULL, NULL, 'Cangkir Kumpul ll, RT.003/RT.003 Dukuh Wetan, Sumoroto, Somoroto, Kec. Kauman, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '15449501000123', '4d77245cd9dfc6678c13f931d98b5f3b.jpeg', 'L', 'RUMAHAN', '', '2025-11-18 06:06:41'),
('PE000042', '8', '1', 'endah-puji-rahayu', '', '', '6281236705104', NULL, '3502120013', NULL, NULL, NULL, 'MPM Somoroto, Somoroto, Kec. Kauman, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502135008900004', '05b51de2ccea45685b9041d707f272bb.jpeg', 'P', 'RUMAHAN', '', '2025-12-01 04:15:39'),
('PE000043', '8', '1', 'achmad-devaldo', '', '', '6285784757501', NULL, '3502170002', 'KABUPATEN PONOROGO', 'PONOROGO', 'BROTONEGARAN', 'Jl Semar No 31 Kelurahan Brotonegaran Ponorogo', '123@gmail.com', 'aktif', NULL, '', '3502042212040001', 'd46f1d34e05d39d6afe66c7538240565.jpg', 'L', 'RUMAHAN', '', '2026-01-06 07:08:09'),
('PE000044', '8', '1', 'guntur-caroko-bintang', '', '', '62895341030537', NULL, '3502170002', NULL, NULL, NULL, 'Jl. Naroyono RT. 002/RW. 006, Brakungan, Brotonegaran, Kec. Ponorogo, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502170710000004', '421e4f08bd52170f1924de53aeecfdd8.jpg', 'L', 'RUMAHAN', '', '2026-02-09 02:33:50'),
('PG000001', '1', '1', 'Faruqi R', '', '', '-', '-', '3502110020', 'KABUPATEN PONOROGO', 'BALONG', 'NGAMPEL', '-', '-', '', NULL, NULL, '-', '', '', '', '', '2021-10-01 00:34:36'),
('PG000002', '1', '1', 'Taufik', '', '', '-', '-', '3502110020', 'KABUPATEN PONOROGO', 'BALONG', 'NGAMPEL', '-', '-', '', NULL, NULL, '-', '', '', '', '', '2021-10-01 00:37:02'),
('PG000003', '1', '1', 'etik', '', '', '-', '-', '3502110020', 'KABUPATEN PONOROGO', 'BALONG', 'NGAMPEL', '-', '-', '', NULL, NULL, '-', '', '', '', '', '2021-10-01 00:37:27'),
('PG000004', '1', '1', 'AN Muslim Rohman', '', '', '-', '-', '3502110020', 'KABUPATEN PONOROGO', 'BALONG', 'NGAMPEL', '-', '-', '', NULL, NULL, '-', '', '', '', '', '2021-10-01 00:38:13'),
('PG000005', '1', '1', 'ausya', '', '', '-', '-', '3502110020', 'KABUPATEN PONOROGO', 'BALONG', 'NGAMPEL', '-', '-', '', NULL, NULL, '-', '', '', '', '', '2021-10-01 00:39:10'),
('PG000006', '1', '1', 'Arli Ahmadari', '', '', '-', '-', '3502110020', 'KABUPATEN PONOROGO', 'BALONG', 'NGAMPEL', '-', '-', '', NULL, NULL, '-', '', '', '', '', '2021-10-01 00:45:16'),
('PG000008', '1', '1', 'Miftahul Arifki', '', '', '-', '-', '3502110020', 'KABUPATEN PONOROGO', 'BALONG', 'NGAMPEL', '-', '-', '', NULL, NULL, '-', '', '', '', '', '2021-10-01 00:48:36'),
('PG000010', '5', '1', 'Mawan Aldiyansah', '', '', '-', '-', '3502110020', 'KABUPATEN PONOROGO', 'BALONG', 'NGAMPEL', '-', '-', '', NULL, NULL, '-', '', '', '', '', '2025-10-10 04:30:04'),
('PG000011', '1', '1', 'fuad', '', '', '-', '-', '3502110020', 'KABUPATEN PONOROGO', 'BALONG', 'NGAMPEL', '-', '-', '', NULL, NULL, '-', '', '', '', '', '2021-10-01 00:50:41'),
('PG000013', '1', '1', 'lutfiah', '', '', '0', '-', '3502090014', 'KABUPATEN PONOROGO', 'SIMAN', 'PATIHAN KIDUL', '-', '0', '', NULL, NULL, '123', '', '', '', '', '2025-03-17 06:04:22'),
('PG000014', '5', '1', 'Lugas', '', '', '', '', '3502170006', 'KABUPATEN PONOROGO', 'PONOROGO', 'PURBOSUMAN', '', '', '', NULL, NULL, '', '', '', '', '', '2025-05-06 01:41:34'),
('PG000015', '1', '1', 'ALVIAN', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, '', '', '', '', '', '2025-10-05 15:45:00'),
('PG000016', '5', '1', 'ALVIAN', '', '', '62895630812251', '0', '3502190010', 'KABUPATEN PONOROGO', 'JENANGAN', 'JENANGAN', 'jenangan', '0', '', NULL, NULL, '1', '', '', '', '', '2025-10-05 15:47:08'),
('PG000017', '1', '1', 'Lisvi Fitria Nur Lita', '', '', '6281939354069', '0', '3502090005', 'KABUPATEN PONOROGO', 'SIMAN', 'SEKARAN', 'null', '0', '', NULL, NULL, '1', '', '', '', '', '2025-10-06 08:26:26'),
('PG000018', '1', '1', 'nensimira', '', '', '0', '0', '3502040004', 'KABUPATEN PONOROGO', 'SAMBIT', 'MAGUWAN', 'maguwan', '0', '', NULL, NULL, '123', '', '', '', '', '2026-01-06 07:02:39');

-- --------------------------------------------------------

--
-- Table structure for table `prosedure_permintaan_wifi`
--

CREATE TABLE `prosedure_permintaan_wifi` (
  `master_id` int(11) GENERATED ALWAYS AS (cast(substr(`IDPERMINTAAN`,3) as unsigned)) STORED,
  `KODEAPP` char(2) NOT NULL DEFAULT 'J',
  `kategori_perangkat_jaringan` int(2) NOT NULL,
  `kode_kontrol_distribusi` varchar(10) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '0',
  `IDPERMINTAAN` varchar(11) NOT NULL,
  `IDPENGGUNA` varchar(11) DEFAULT NULL,
  `STATUS` varchar(20) DEFAULT NULL,
  `TGL_AKTIFPUTUS` date NOT NULL,
  `STATUSPASANG` varchar(25) NOT NULL,
  `ALASAN` text NOT NULL,
  `STATUSTINDAKANALAT` varchar(100) NOT NULL,
  `STATUSLANGGANAN` varchar(70) NOT NULL,
  `UBAHKONEKSI` tinyint(1) NOT NULL,
  `IDPAKET` varchar(11) NOT NULL,
  `DISURVEY` varchar(70) DEFAULT NULL,
  `DIACC` varchar(70) DEFAULT NULL,
  `DIPROSES` varchar(70) DEFAULT NULL,
  `CREATED` varchar(25) DEFAULT NULL,
  `DILAPORKAN` varchar(11) DEFAULT NULL,
  `TGLDIACC` datetime DEFAULT NULL,
  `TGLSURVEY` datetime DEFAULT NULL,
  `TGLDIPROSES` datetime DEFAULT NULL,
  `TGLSELESAI` datetime DEFAULT NULL,
  `STATUSALAT` varchar(10) NOT NULL,
  `VERIFIED` varchar(15) NOT NULL,
  `VERIFIED_AT` datetime NOT NULL,
  `JENISJARINGAN` char(8) NOT NULL,
  `JENISMEMBER` int(2) NOT NULL,
  `IDBIAYA` varchar(15) NOT NULL,
  `inserted_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `prosedure_permintaan_wifi`
--

INSERT INTO `prosedure_permintaan_wifi` (`KODEAPP`, `kategori_perangkat_jaringan`, `kode_kontrol_distribusi`, `IDPERMINTAAN`, `IDPENGGUNA`, `STATUS`, `TGL_AKTIFPUTUS`, `STATUSPASANG`, `ALASAN`, `STATUSTINDAKANALAT`, `STATUSLANGGANAN`, `UBAHKONEKSI`, `IDPAKET`, `DISURVEY`, `DIACC`, `DIPROSES`, `CREATED`, `DILAPORKAN`, `TGLDIACC`, `TGLSURVEY`, `TGLDIPROSES`, `TGLSELESAI`, `STATUSALAT`, `VERIFIED`, `VERIFIED_AT`, `JENISJARINGAN`, `JENISMEMBER`, `IDBIAYA`, `inserted_at`, `updated_at`) VALUES
('J', 0, '0', 'RQ000001', 'PE000001', 'PENGAJUAN', '0000-00-00', '', '', '', '', 0, 'PK000001', NULL, NULL, NULL, 'PG000013', NULL, NULL, NULL, NULL, NULL, 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2025-03-19 13:39:35', '0000-00-00 00:00:00'),
('J', 0, '0', 'RQ000004', 'PE000004', 'GAGAL', '0000-00-00', 'Gagal', 'Salah input nama', 'Belum Terpasang', '', 0, 'PK000001', NULL, NULL, NULL, 'PG000005', NULL, NULL, NULL, NULL, NULL, 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2025-04-24 03:47:52', '2025-04-24 03:50:33'),
('J', 0, '0', 'RQ000005', 'PE000005', 'GAGAL', '0000-00-00', 'Gagal', 'Sudang pasang wifi lain, orangnya tidak sabaran.', 'Belum Terpasang', '', 0, 'PK000001', NULL, NULL, NULL, 'PG000005', NULL, NULL, NULL, NULL, NULL, 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2025-04-24 03:52:36', '2025-04-28 00:54:11'),
('J', 1, '0', 'RQ000006', 'PE000006', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000002', 'PG000014', 'PG000005', 'PG000005', 'PG000005', 'PG000014', '2025-05-10 17:05:22', '2025-05-06 10:16:15', '2025-05-28 11:54:25', '2026-01-06 14:44:50', 'SEWA', 'PG000017', '2026-01-06 14:54:10', 'KABEL', 0, 'IN000037', '2025-05-05 12:52:04', '2026-01-06 07:54:10'),
('J', 0, '0', 'RQ000007', 'PE000007', 'GAGAL', '0000-00-00', 'Gagal', 'SALAH APLIKASI', 'Belum Terpasang', '', 0, 'PK000002', NULL, NULL, NULL, 'PG000005', NULL, NULL, NULL, NULL, NULL, 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2025-05-05 12:53:39', '2025-05-06 06:21:28'),
('J', 1, '0', 'RQ000008', 'PE000008', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000002', NULL, NULL, NULL, 'PG000005', NULL, NULL, NULL, NULL, NULL, 'SEWA', 'PG000005', '0000-00-00 00:00:00', 'KABEL', 0, 'IN000001', '2025-05-06 03:20:38', '0000-00-00 00:00:00'),
('J', 1, '0', 'RQ000009', 'PE000009', 'DISURVEI', '0000-00-00', 'Verifikasi Proses', '', '', '', 0, 'PK000001', 'PG000005', 'PG000005', NULL, 'PG000005', NULL, '2025-06-02 07:35:39', '2025-05-26 17:51:00', NULL, '2025-05-26 19:47:16', 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2025-05-19 07:25:52', '0000-00-00 00:00:00'),
('J', 0, '0', 'RQ000010', 'PE000010', 'PENGAJUAN', '0000-00-00', '', '', '', '', 0, 'PK000002', NULL, NULL, NULL, 'PG000005', NULL, NULL, NULL, NULL, NULL, 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2025-06-16 07:15:17', '0000-00-00 00:00:00'),
('J', 1, 'X6A', 'RQ000011', 'PE000011', 'DISURVEI', '0000-00-00', 'Verifikasi Proses', '', '', '', 0, 'PK000002', 'PG000005', 'PG000005', NULL, 'PG000005', NULL, '2025-06-25 14:16:31', '2025-06-25 14:08:10', NULL, '2025-06-25 14:08:46', 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2025-06-25 06:55:26', '0000-00-00 00:00:00'),
('J', 1, 'X6A', 'RQ000012', 'PE000012', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', 'PG000014', 'PG000005', 'PG000014', 'PG000005', 'PG000014', '2025-07-08 15:31:18', '2025-07-07 16:43:54', '2026-01-06 14:55:59', '2026-01-06 14:57:51', 'SEWA', 'PG000017', '2026-01-07 14:28:22', 'KABEL', 0, 'IN000039', '2025-07-01 08:37:28', '2026-01-07 07:28:22'),
('J', 1, '0', 'RQ000013', 'PE000013', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', 'PG000005', 'PG000005', 'PG000005', 'PG000005', 'PG000005', '2025-07-29 11:23:44', '2025-07-28 14:12:25', '2025-08-02 07:51:20', '2025-08-02 07:54:15', 'SEWA', 'PG000005', '2025-08-08 14:51:42', 'KABEL', 0, 'IN000002', '2025-07-28 07:05:37', '2025-08-08 07:55:40'),
('J', 0, '0', 'RQ000014', 'PE000014', 'PUTUS', '0000-00-00', 'Terpasang', 'Wifi lemot', 'Sudah diambil', '', 0, 'PK000001', 'PG000005', 'PG000005', 'PG000005', 'PG000005', 'PG000005', '2025-08-04 15:40:16', '2025-08-02 15:44:36', '2025-08-13 09:45:28', '2025-08-13 19:03:06', 'SEWA', 'PG000005', '2025-08-13 21:28:27', 'KABEL', 0, 'IN000005', '2025-08-01 08:29:02', '2025-09-16 08:26:34'),
('J', 1, '0', 'RQ000015', 'PE000015', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', 'PG000005', 'PG000005', 'PG000005', 'PG000005', 'PG000005', '2025-08-15 13:06:19', '2025-08-15 11:57:55', '2025-08-15 13:07:40', '2025-08-15 13:11:08', 'SEWA', 'PG000005', '2025-08-15 13:24:33', 'KABEL', 0, 'IN000006', '2025-08-02 08:54:28', '2025-08-15 06:24:33'),
('J', 1, '0', 'RQ000016', 'PE000016', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000002', 'PG000005', 'PG000005', 'PG000005', 'PG000005', 'PG000005', '2025-08-09 08:54:14', '2025-08-08 15:19:02', '2025-08-13 09:45:07', '2025-08-13 11:07:02', 'SEWA', 'PG000005', '2025-08-13 15:47:33', 'KABEL', 0, 'IN000003', '2025-08-06 08:58:12', '2025-08-13 08:47:33'),
('J', 1, '0', 'RQ000017', 'PE000017', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000002', 'PG000005', 'PG000005', 'PG000005', 'PG000005', 'PG000005', '2025-08-13 13:16:03', '2025-08-13 10:35:11', '2025-08-15 09:05:29', '2025-08-15 13:03:44', 'SEWA', 'PG000005', '2025-08-15 13:26:38', 'KABEL', 0, 'IN000008', '2025-08-12 09:03:17', '2025-08-15 06:26:38'),
('J', 1, '0', 'RQ000018', 'PE000018', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000002', 'PG000005', 'PG000005', 'PG000005', 'PG000005', 'PG000005', '2025-08-22 11:11:27', '2025-08-22 10:51:59', '2025-08-22 11:30:06', '2025-08-22 11:34:57', 'SEWA', 'PG000005', '2025-08-22 15:45:50', 'KABEL', 0, 'IN000010', '2025-08-22 02:23:42', '2025-08-22 08:45:50'),
('J', 1, '0', 'RQ000019', 'PE000019', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', 'PG000005', 'PG000005', 'PG000005', 'PG000005', 'PG000005', '2025-08-25 11:24:37', '2025-08-25 09:48:45', '2025-08-25 11:24:50', '2025-08-25 11:27:23', 'SEWA', 'PG000005', '2025-08-25 14:46:06', 'KABEL', 0, 'IN000011', '2025-08-23 12:22:31', '2025-08-25 07:46:06'),
('J', 0, '0', 'RQ000020', 'PE000020', 'GAGAL', '0000-00-00', 'Gagal', 'Pelanggan minta batal, karena beberapa alasan internal. Pelanggan tidak memberikan detail alasannya', 'Belum Terpasang', '', 0, 'PK000001', 'PG000005', 'PG000005', NULL, 'PG000005', NULL, '2025-08-28 11:55:16', '2025-08-28 08:52:09', NULL, '2025-08-28 08:53:48', 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2025-08-27 07:27:21', '2025-09-01 02:13:23'),
('J', 1, '0', 'RQ000021', 'PE000021', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', 'PG000005', 'PG000005', 'PG000005', 'PG000005', 'PG000005', '2025-09-01 09:21:59', '2025-09-01 07:58:35', '2025-09-08 13:32:52', '2025-09-10 16:41:51', 'SEWA', 'PG000005', '2025-09-11 14:41:34', 'KABEL', 0, 'IN000016', '2025-08-30 07:06:29', '2025-09-11 07:41:34'),
('J', 1, '0', 'RQ000022', 'PE000022', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', NULL, NULL, NULL, 'PG000005', NULL, NULL, NULL, NULL, NULL, 'SEWA', 'PG000005', '0000-00-00 00:00:00', 'KABEL', 0, 'IN000013', '2025-09-03 00:35:42', '2025-09-03 00:40:18'),
('J', 0, '0', 'RQ000023', 'PE000023', 'PUTUS', '0000-00-00', 'Terpasang', 'Data double', '', '', 0, 'PK000001', NULL, NULL, NULL, 'PG000005', NULL, NULL, NULL, NULL, NULL, 'SEWA', 'PG000005', '0000-00-00 00:00:00', 'KABEL', 0, 'IN000014', '2025-09-03 00:35:53', '2025-09-03 00:36:19'),
('J', 1, '0', 'RQ000024', 'PE000024', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', 'PG000005', 'PG000005', 'PG000005', 'PG000005', 'PG000005', '2025-09-08 09:28:16', '2025-09-08 08:43:25', '2025-09-08 10:42:37', '2025-09-09 06:53:54', 'SEWA', 'PG000005', '2025-09-09 08:54:57', 'KABEL', 0, 'IN000015', '2025-09-05 07:00:20', '2025-09-09 01:54:57'),
('J', 0, '0', 'RQ000025', 'PE000025', 'DISURVEI', '0000-00-00', 'Verifikasi Proses', '', '', '', 0, 'PK000001', 'PG000005', 'PG000005', NULL, 'PG000005', NULL, '2025-09-29 21:00:48', '2025-09-10 16:33:49', NULL, '2025-09-10 16:38:59', 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2025-09-10 01:12:26', '0000-00-00 00:00:00'),
('J', 0, '0', 'RQ000026', 'PE000026', 'DISURVEI', '0000-00-00', 'Verifikasi Proses', '', '', '', 0, 'PK000001', 'PG000005', 'PG000005', NULL, 'PG000005', NULL, '2025-09-15 13:28:35', '2025-09-13 13:37:18', NULL, '2025-09-13 13:38:32', 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2025-09-13 04:52:08', '0000-00-00 00:00:00'),
('J', 1, '0', 'RQ000027', 'PE000027', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', 'PG000005', 'PG000005', 'PG000005', 'PG000005', 'PG000005', '2025-09-16 08:43:36', '2025-09-15 15:16:24', '2025-09-16 15:15:01', '2025-09-22 20:06:00', 'SEWA', 'PG000005', '2025-09-23 13:44:04', 'KABEL', 0, 'IN000018', '2025-09-13 07:27:41', '2025-09-23 06:44:04'),
('J', 1, '0', 'RQ000028', 'PE000028', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', 'PG000005', 'PG000005', 'PG000005', 'PG000005', 'PG000005', '2025-09-16 08:43:25', '2025-09-15 15:52:48', '2025-09-22 20:01:10', '2025-09-22 20:09:39', 'SEWA', 'PG000005', '2025-09-23 13:46:09', 'KABEL', 0, 'IN000020', '2025-09-15 07:52:53', '2025-09-23 06:46:09'),
('J', 1, '0', 'RQ000029', 'PE000029', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', 'PG000005', 'PG000005', 'PG000005', 'PG000005', 'PG000005', '2025-09-26 07:48:15', '2025-09-25 15:45:11', '2025-09-27 16:43:43', '2025-09-27 16:46:05', 'SEWA', 'PG000005', '2025-09-29 09:04:03', 'KABEL', 0, 'IN000022', '2025-09-22 06:42:10', '2025-09-29 02:04:03'),
('J', 0, '0', 'RQ000030', 'PE000030', 'PENGAJUAN', '0000-00-00', '', '', '', '', 0, 'PK000001', NULL, NULL, NULL, 'PG000005', NULL, NULL, NULL, NULL, NULL, 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2025-09-25 03:25:14', '0000-00-00 00:00:00'),
('J', 0, '0', 'RQ000031', 'PE000031', 'DISURVEI', '0000-00-00', 'Verifikasi Proses', '', '', '', 0, 'PK000002', 'PG000005', 'PG000005', NULL, 'PG000005', NULL, '2025-11-02 23:05:31', '2025-09-29 16:05:54', NULL, '2025-09-29 16:40:17', 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2025-09-29 01:19:31', '0000-00-00 00:00:00'),
('J', 0, '0', 'RQ000032', 'PE000032', 'GAGAL', '0000-00-00', 'Gagal', 'Pindah ke OLT Siman', 'Belum Terpasang', '', 0, 'PK000001', NULL, NULL, NULL, 'PG000005', NULL, NULL, NULL, NULL, NULL, 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2025-09-29 07:59:31', '2025-09-29 08:52:31'),
('J', 1, '0', 'RQ000033', 'PE000033', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', 'PG000005', 'PG000005', 'PG000010', 'PG000005', 'PG000010', '2025-10-04 15:59:53', '2025-10-04 14:07:26', '2025-10-14 15:39:21', '2025-10-14 15:44:42', 'SEWA', 'PG000017', '2025-10-14 16:10:36', 'KABEL', 0, 'IN000025', '2025-10-02 09:31:04', '2025-10-14 09:10:36'),
('J', 1, '0', 'RQ000034', 'PE000034', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', 'PG000017', 'PG000017', 'PG000010', 'PG000017', 'PG000010', '2025-10-09 14:48:46', '2025-10-09 14:48:06', '2025-10-10 17:41:12', '2025-10-10 17:46:28', 'SEWA', 'PG000005', '2025-10-10 22:10:06', 'KABEL', 0, 'IN000024', '2025-10-09 07:00:14', '2025-10-10 15:10:06'),
('J', 1, '0', 'RQ000035', 'PE000035', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', 'PG000005', 'PG000017', 'PG000010', 'PG000017', 'PG000010', '2025-10-18 07:49:43', '2025-10-15 14:58:56', '2025-10-22 20:18:27', '2025-10-22 20:46:59', 'SEWA', 'PG000017', '2025-10-23 08:25:53', 'KABEL', 0, 'IN000027', '2025-10-15 04:38:52', '2025-10-23 01:25:53'),
('J', 1, '0', 'RQ000036', 'PE000036', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', 'PG000005', 'PG000017', 'PG000010', 'PG000017', 'PG000010', '2025-10-24 06:27:58', '2025-10-23 14:11:41', '2025-10-31 20:41:08', '2025-10-31 20:45:49', 'SEWA', 'PG000017', '2025-11-01 11:37:27', 'KABEL', 0, 'IN000029', '2025-10-18 07:10:57', '2025-11-01 04:37:27'),
('J', 0, '0', 'RQ000037', 'PE000037', 'GAGAL', '0000-00-00', 'Gagal', 'Kelamaan pasangnya', 'Belum Terpasang', '', 0, 'PK000001', NULL, NULL, NULL, 'PG000017', NULL, NULL, NULL, NULL, NULL, 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2025-10-25 03:00:31', '2025-10-25 07:42:40'),
('J', 1, '0', 'RQ000038', 'PE000038', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000002', 'PG000014', 'PG000017', 'PG000010', 'PG000017', 'PG000010', '2025-10-29 07:21:19', '2025-10-27 16:57:18', '2025-10-31 20:29:40', '2025-10-31 20:34:59', 'SEWA', 'PG000017', '2025-11-01 13:39:53', 'KABEL', 0, 'IN000030', '2025-10-27 04:51:53', '2025-11-01 06:39:53'),
('J', 1, '0', 'RQ000039', 'PE000039', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', 'PG000014', 'PG000005', 'PG000010', 'PG000017', 'PG000010', '2025-10-29 00:22:46', '2025-10-27 15:43:40', '2025-10-31 20:35:17', '2025-11-11 07:16:18', 'SEWA', 'PG000017', '2025-11-11 09:36:41', 'KABEL', 0, 'IN000032', '2025-10-27 06:33:22', '2025-11-11 02:36:41'),
('J', 1, '0', 'RQ000040', 'PE000040', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000002', 'PG000005', 'PG000017', 'PG000010', 'PG000017', 'PG000010', '2025-11-01 13:10:29', '2025-10-31 15:03:56', '2025-11-09 23:00:47', '2025-11-09 23:06:32', 'SEWA', 'PG000017', '2025-11-11 09:40:12', 'KABEL', 0, 'IN000034', '2025-10-28 04:40:40', '2025-11-11 02:40:12'),
('J', 0, '0', 'RQ000041', 'PE000041', 'GAGAL', '0000-00-00', 'Gagal', 'Pindah App. Whusnet', 'Belum Terpasang', '', 0, 'PK000001', NULL, NULL, NULL, 'PG000017', NULL, NULL, NULL, NULL, NULL, 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2025-11-18 06:06:41', '2025-11-28 07:48:45'),
('J', 0, '0', 'RQ000042', 'PE000042', 'GAGAL', '0000-00-00', 'Gagal', 'Pindah ke App. Whusnet', 'Belum Terpasang', '', 0, 'PK000001', 'PG000005', NULL, NULL, 'PG000017', NULL, NULL, '2025-12-01 14:54:19', NULL, '2025-12-01 14:56:10', 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2025-12-01 04:15:39', '2025-12-01 23:21:41'),
('J', 1, '0', 'RQ000043', 'PE000043', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', NULL, NULL, NULL, 'PG000017', NULL, NULL, NULL, NULL, NULL, 'SEWA', 'PG000017', '0000-00-00 00:00:00', 'KABEL', 0, 'IN000036', '2026-01-06 07:07:35', '2026-01-06 07:10:54'),
('J', 0, '0', 'RQ000044', 'PE000044', 'GAGAL', '0000-00-00', 'Gagal', 'Pindah ke aplikasi whusnet', 'Belum Terpasang', '', 0, 'PK000001', NULL, NULL, NULL, 'PG000017', NULL, NULL, NULL, NULL, NULL, 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2026-02-09 02:33:50', '2026-02-11 03:13:16');

-- --------------------------------------------------------

--
-- Table structure for table `riwayatstatus_penggunabarang`
--

CREATE TABLE `riwayatstatus_penggunabarang` (
  `ID` varchar(11) NOT NULL,
  `KODEBARANG` varchar(11) NOT NULL,
  `MERKBARANG` varchar(11) NOT NULL,
  `IDPERMINTAAN` varchar(11) NOT NULL,
  `IDPENGGUNA` varchar(11) NOT NULL,
  `KETERANGAN` text NOT NULL,
  `TGLKEMBALI` datetime NOT NULL,
  `inserted_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `riwayatstatus_penggunabarang`
--

INSERT INTO `riwayatstatus_penggunabarang` (`ID`, `KODEBARANG`, `MERKBARANG`, `IDPERMINTAAN`, `IDPENGGUNA`, `KETERANGAN`, `TGLKEMBALI`, `inserted_at`) VALUES
('6895acbd05d', '1', '1', 'RQ000013', 'PE000013', 'DISEWA', '0000-00-00 00:00:00', '2025-08-08 07:52:29'),
('6895ad621d1', '1', '1', 'RQ000013', 'PE000013', 'DISEWA', '0000-00-00 00:00:00', '2025-08-08 07:55:14'),
('689eef91bf0', '1', '1', 'RQ000014', 'PE000014', 'DISEWA', '0000-00-00 00:00:00', '2025-08-15 08:28:01'),
('68b78dab5f8', '1', '1', 'RQ000022', 'PE000022', 'DISEWA', '0000-00-00 00:00:00', '2025-09-03 00:36:59'),
('68b78dccbc7', '1', '1', 'RQ000022', 'PE000022', 'DISEWA', '0000-00-00 00:00:00', '2025-09-03 00:37:32'),
('695cb4d9f3c', '1', '1', 'RQ000043', 'PE000043', 'DISEWA', '0000-00-00 00:00:00', '2026-01-06 07:08:09'),
('695cb4edbde', '1', '1', 'RQ000043', 'PE000043', 'DISEWA', '0000-00-00 00:00:00', '2026-01-06 07:08:29');

-- --------------------------------------------------------

--
-- Table structure for table `riwayat_pelanggan`
--

CREATE TABLE `riwayat_pelanggan` (
  `ID` varchar(11) NOT NULL,
  `IDPERMINTAAN` varchar(11) NOT NULL,
  `CREATEBY` varchar(11) NOT NULL,
  `STATUSTINDAKAN` varchar(50) NOT NULL,
  `STATUSLANGGANAN` varchar(50) NOT NULL,
  `ALASAN` text NOT NULL,
  `TGLTINDAKAN` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `STATUSTINDAKANALAT` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `riwayat_pelanggan`
--

INSERT INTO `riwayat_pelanggan` (`ID`, `IDPERMINTAAN`, `CREATEBY`, `STATUSTINDAKAN`, `STATUSLANGGANAN`, `ALASAN`, `TGLTINDAKAN`, `STATUSTINDAKANALAT`) VALUES
('67dac915cb6', 'RQ000001', 'PE000001', 'PENGAJUAN', '', '', '2025-03-19 13:39:33', ''),
('6809b46886d', 'RQ000004', 'PE000004', 'PENGAJUAN', '', '', '2025-04-24 03:47:52', ''),
('6809b509e8c', 'RQ000004', 'PG000005', 'Gagal', '', 'Salah input nama', '2025-04-24 03:50:33', 'Belum Terpasang'),
('6809b5846ae', 'RQ000005', 'PE000005', 'PENGAJUAN', '', '', '2025-04-24 03:52:36', ''),
('680ed1b317f', 'RQ000005', 'PG000005', 'Gagal', '', 'Sudang pasang wifi lain, orangnya tidak sabaran.', '2025-04-28 00:54:11', 'Belum Terpasang'),
('6818b474ab6', 'RQ000006', 'PE000006', 'PENGAJUAN', '', '', '2025-05-05 12:52:04', ''),
('6818b4d3a6f', 'RQ000007', 'PE000007', 'PENGAJUAN', '', '', '2025-05-05 12:53:39', ''),
('68197effa73', 'RQ000006', 'PG000014', 'Dalam Proses Survei', '', '', '2025-05-06 03:16:15', ''),
('681980066b8', 'RQ000008', 'PG000005', 'Berhasil Active', '', '', '2025-05-06 03:20:38', ''),
('68198177e1c', '', 'PG000005', 'OFF', '', '', '2025-05-06 03:26:47', ''),
('6819a1b4bb7', 'RQ000006', 'PG000014', 'Proses Survei Selesai', '', '', '2025-05-06 05:44:20', ''),
('6819aa683b2', 'RQ000007', 'PG000005', 'Gagal', '', 'SALAH APLIKASI', '2025-05-06 06:21:28', 'Belum Terpasang'),
('681f24e23ad', 'RQ000006', 'PG000005', 'Verifikasi Proses', '', '', '2025-05-10 10:05:22', ''),
('682add00b5b', 'RQ000009', 'PE000009', 'PENGAJUAN', '', '', '2025-05-19 07:25:52', ''),
('683447946e2', 'RQ000009', 'PG000005', 'Dalam Proses Survei', '', '', '2025-05-26 10:51:00', ''),
('683462d46a8', 'RQ000009', 'PG000005', 'Proses Survei Selesai', '', '', '2025-05-26 12:47:16', ''),
('6836970173c', 'RQ000006', 'PG000005', 'Dalam Proses Pasang', '', '', '2025-05-28 04:54:25', ''),
('683cf1db4ec', 'RQ000009', 'PG000005', 'Verifikasi Proses', '', '', '2025-06-02 00:35:39', ''),
('684fc48567d', 'RQ000010', 'PE000010', 'PENGAJUAN', '', '', '2025-06-16 07:15:17', ''),
('685b9d5e002', 'RQ000011', 'PE000011', 'PENGAJUAN', '', '', '2025-06-25 06:55:25', ''),
('685ba05a8ec', 'RQ000011', 'PG000005', 'Dalam Proses Survei', '', '', '2025-06-25 07:08:10', ''),
('685ba07e2db', 'RQ000011', 'PG000005', 'Proses Survei Selesai', '', '', '2025-06-25 07:08:46', ''),
('685ba24f76d', 'RQ000011', 'PG000005', 'Verifikasi Proses', '', '', '2025-06-25 07:16:31', ''),
('68639e4876b', 'RQ000012', 'PE000012', 'PENGAJUAN', '', '', '2025-07-01 08:37:28', ''),
('686b96da41a', 'RQ000012', 'PG000014', 'Dalam Proses Survei', '', '', '2025-07-07 09:43:54', ''),
('686b97612ed', 'RQ000012', 'PG000014', 'Proses Survei Selesai', '', '', '2025-07-07 09:46:09', ''),
('686cd756d71', 'RQ000012', 'PG000005', 'Verifikasi Proses', '', '', '2025-07-08 08:31:18', ''),
('68872141cf7', 'RQ000013', 'PE000013', 'PENGAJUAN', '', '', '2025-07-28 07:05:37', ''),
('688722d99ba', 'RQ000013', 'PG000005', 'Dalam Proses Survei', '', '', '2025-07-28 07:12:25', ''),
('6887231495b', 'RQ000013', 'PG000005', 'Proses Survei Selesai', '', '', '2025-07-28 07:13:24', ''),
('68884cd08fe', 'RQ000013', 'PG000005', 'Verifikasi Proses', '', '', '2025-07-29 04:23:44', ''),
('688c7aceb82', 'RQ000014', 'PE000014', 'PENGAJUAN', '', '', '2025-08-01 08:29:02', ''),
('688d6108c6a', 'RQ000013', 'PG000005', 'Dalam Proses Pasang', '', '', '2025-08-02 00:51:20', ''),
('688d61b73af', 'RQ000013', 'PG000005', 'Terpasang', '', '', '2025-08-02 00:54:15', ''),
('688dcff4d4a', 'RQ000014', 'PG000005', 'Dalam Proses Survei', '', '', '2025-08-02 08:44:36', ''),
('688dd244b1d', 'RQ000015', 'PE000015', 'PENGAJUAN', '', '', '2025-08-02 08:54:28', ''),
('688dd703341', 'RQ000014', 'PG000005', 'Proses Survei Selesai', '', '', '2025-08-02 09:14:43', ''),
('689071f05ac', 'RQ000014', 'PG000005', 'Verifikasi Proses', '', '', '2025-08-04 08:40:16', ''),
('68931924e19', 'RQ000016', 'PE000016', 'PENGAJUAN', '', '', '2025-08-06 08:58:12', ''),
('6895ac8e2bf', 'RQ000013', 'PG000005', 'Berhasil Active', '', '', '2025-08-08 07:51:42', ''),
('6895b2f6409', 'RQ000016', 'PG000005', 'Dalam Proses Survei', '', '', '2025-08-08 08:19:02', ''),
('6895b6a810e', 'RQ000016', 'PG000005', 'Proses Survei Selesai', '', '', '2025-08-08 08:34:48', ''),
('6896aa4679d', 'RQ000016', 'PG000005', 'Verifikasi Proses', '', '', '2025-08-09 01:54:14', ''),
('689b0355260', 'RQ000017', 'PE000017', 'PENGAJUAN', '', '', '2025-08-12 09:03:17', ''),
('689bfc33820', 'RQ000016', 'PG000005', 'Dalam Proses Pasang', '', '', '2025-08-13 02:45:07', ''),
('689bfc48601', 'RQ000014', 'PG000005', 'Dalam Proses Pasang', '', '', '2025-08-13 02:45:28', ''),
('689c07ef215', 'RQ000017', 'PG000005', 'Dalam Proses Survei', '', '', '2025-08-13 03:35:11', ''),
('689c082a099', 'RQ000017', 'PG000005', 'Proses Survei Selesai', '', '', '2025-08-13 03:36:10', ''),
('689c0f66b86', 'RQ000016', 'PG000005', 'Terpasang', '', '', '2025-08-13 04:07:02', ''),
('689c2da3c19', 'RQ000017', 'PG000005', 'Verifikasi Proses', '', '', '2025-08-13 06:16:03', ''),
('689c5125d59', 'RQ000016', 'PG000005', 'Berhasil Active', '', '', '2025-08-13 08:47:33', ''),
('689c5126ae9', '', 'PG000005', 'Berhasil Active', '', '', '2025-08-13 08:47:34', ''),
('689c7efa389', 'RQ000014', 'PG000005', 'Terpasang', '', '', '2025-08-13 12:03:06', ''),
('689ca10bcd3', 'RQ000014', 'PG000005', 'Berhasil Active', '', '', '2025-08-13 14:28:27', ''),
('689e95e987c', 'RQ000017', 'PG000005', 'Dalam Proses Pasang', '', '', '2025-08-15 02:05:29', ''),
('689ebe5324d', 'RQ000015', 'PG000005', 'Dalam Proses Survei', '', '', '2025-08-15 04:57:55', ''),
('689ec8185bd', 'RQ000015', 'PG000005', 'Proses Survei Selesai', '', '', '2025-08-15 05:39:36', ''),
('689ecdc0dc3', 'RQ000017', 'PG000005', 'Terpasang', '', '', '2025-08-15 06:03:44', ''),
('689ece5b110', 'RQ000015', 'PG000005', 'Verifikasi Proses', '', '', '2025-08-15 06:06:19', ''),
('689eceac8b4', 'RQ000015', 'PG000005', 'Dalam Proses Pasang', '', '', '2025-08-15 06:07:40', ''),
('689ecf7c962', 'RQ000015', 'PG000005', 'Terpasang', '', '', '2025-08-15 06:11:08', ''),
('689ed2a1a78', 'RQ000015', 'PG000005', 'Berhasil Active', '', '', '2025-08-15 06:24:33', ''),
('689ed2a24ee', '', 'PG000005', 'Berhasil Active', '', '', '2025-08-15 06:24:34', ''),
('689ed31e32d', 'RQ000017', 'PG000005', 'Berhasil Active', '', '', '2025-08-15 06:26:38', ''),
('689ed31ef27', '', 'PG000005', 'Berhasil Active', '', '', '2025-08-15 06:26:38', ''),
('68a7d4ae9b8', 'RQ000018', 'PE000018', 'PENGAJUAN', '', '', '2025-08-22 02:23:42', ''),
('68a7e95f130', 'RQ000018', 'PG000005', 'Dalam Proses Survei', '', '', '2025-08-22 03:51:59', ''),
('68a7edbbc30', 'RQ000018', 'PG000005', 'Proses Survei Selesai', '', '', '2025-08-22 04:10:35', ''),
('68a7edef8b9', 'RQ000018', 'PG000005', 'Verifikasi Proses', '', '', '2025-08-22 04:11:27', ''),
('68a7f24e6a0', 'RQ000018', 'PG000005', 'Dalam Proses Pasang', '', '', '2025-08-22 04:30:06', ''),
('68a7f3718dd', 'RQ000018', 'PG000005', 'Terpasang', '', '', '2025-08-22 04:34:57', ''),
('68a82e3e6d6', 'RQ000018', 'PG000005', 'Berhasil Active', '', '', '2025-08-22 08:45:50', ''),
('68a9b2872e2', 'RQ000019', 'PE000019', 'PENGAJUAN', '', '', '2025-08-23 12:22:31', ''),
('68abcf0d4ce', 'RQ000019', 'PG000005', 'Dalam Proses Survei', '', '', '2025-08-25 02:48:45', ''),
('68abd2099b1', 'RQ000019', 'PG000005', 'Proses Survei Selesai', '', '', '2025-08-25 03:01:29', ''),
('68abe585e25', 'RQ000019', 'PG000005', 'Verifikasi Proses', '', '', '2025-08-25 04:24:37', ''),
('68abe592f02', 'RQ000019', 'PG000005', 'Dalam Proses Pasang', '', '', '2025-08-25 04:24:50', ''),
('68abe62b01a', 'RQ000019', 'PG000005', 'Terpasang', '', '', '2025-08-25 04:27:23', ''),
('68ac14be01b', 'RQ000019', 'PG000005', 'Berhasil Active', '', '', '2025-08-25 07:46:06', ''),
('68ac14becf9', '', 'PG000005', 'Berhasil Active', '', '', '2025-08-25 07:46:06', ''),
('68aeb35968a', 'RQ000020', 'PE000020', 'PENGAJUAN', '', '', '2025-08-27 07:27:21', ''),
('68afb649a45', 'RQ000020', 'PG000005', 'Dalam Proses Survei', '', '', '2025-08-28 01:52:09', ''),
('68afb6ace59', 'RQ000020', 'PG000005', 'Proses Survei Selesai', '', '', '2025-08-28 01:53:48', ''),
('68afe134e75', 'RQ000020', 'PG000005', 'Verifikasi Proses', '', '', '2025-08-28 04:55:16', ''),
('68b2a2f5072', 'RQ000021', 'PE000021', 'PENGAJUAN', '', '', '2025-08-30 07:06:29', ''),
('68b4efbb011', 'RQ000021', 'PG000005', 'Dalam Proses Survei', '', '', '2025-09-01 00:58:35', ''),
('68b4fc799d1', 'RQ000021', 'PG000005', 'Proses Survei Selesai', '', '', '2025-09-01 01:52:57', ''),
('68b50143db6', 'RQ000020', 'PG000005', 'Gagal', '', 'Pelanggan minta batal, karena beberapa alasan internal. Pelanggan tidak memberikan detail alasannya', '2025-09-01 02:13:23', 'Belum Terpasang'),
('68b50347d6a', 'RQ000021', 'PG000005', 'Verifikasi Proses', '', '', '2025-09-01 02:21:59', ''),
('68b78d5ea0f', 'RQ000022', 'PG000005', 'Berhasil Active', '', '', '2025-09-03 00:35:42', ''),
('68b78d695b1', 'RQ000023', 'PG000005', 'Berhasil Active', '', '', '2025-09-03 00:35:53', ''),
('68b78d83cb9', 'RQ000023', 'PG000005', 'Putus Langganan', '', 'Data double', '2025-09-03 00:36:19', ''),
('68ba8a84c05', 'RQ000024', 'PE000024', 'PENGAJUAN', '', '', '2025-09-05 07:00:20', ''),
('68be34bd320', 'RQ000024', 'PG000005', 'Dalam Proses Survei', '', '', '2025-09-08 01:43:25', ''),
('68be34ec34e', 'RQ000024', 'PG000005', 'Proses Survei Selesai', '', '', '2025-09-08 01:44:12', ''),
('68be3f40228', 'RQ000024', 'PG000005', 'Verifikasi Proses', '', '', '2025-09-08 02:28:16', ''),
('68be50adc74', 'RQ000024', 'PG000005', 'Dalam Proses Pasang', '', '', '2025-09-08 03:42:37', ''),
('68be7894cf7', 'RQ000021', 'PG000005', 'Dalam Proses Pasang', '', '', '2025-09-08 06:32:52', ''),
('68bf6c92d55', 'RQ000024', 'PG000005', 'Terpasang', '', '', '2025-09-08 23:53:54', ''),
('68bf88f19da', 'RQ000024', 'PG000005', 'Berhasil Active', '', '', '2025-09-09 01:54:57', ''),
('68c0d07a638', 'RQ000025', 'PE000025', 'PENGAJUAN', '', '', '2025-09-10 01:12:26', ''),
('68c145fdbcc', 'RQ000025', 'PG000005', 'Dalam Proses Survei', '', '', '2025-09-10 09:33:49', ''),
('68c14733d79', 'RQ000025', 'PG000005', 'Proses Survei Selesai', '', '', '2025-09-10 09:38:59', ''),
('68c147dfe3b', 'RQ000021', 'PG000005', 'Terpasang', '', '', '2025-09-10 09:41:51', ''),
('68c27d2edfb', 'RQ000021', 'PG000005', 'Berhasil Active', '', '', '2025-09-11 07:41:34', ''),
('68c27d2ff25', '', 'PG000005', 'Berhasil Active', '', '', '2025-09-11 07:41:35', ''),
('68c4f878540', 'RQ000026', 'PE000026', 'PENGAJUAN', '', '', '2025-09-13 04:52:08', ''),
('68c5111ec63', 'RQ000026', 'PG000005', 'Dalam Proses Survei', '', '', '2025-09-13 06:37:18', ''),
('68c511681d0', 'RQ000026', 'PG000005', 'Proses Survei Selesai', '', '', '2025-09-13 06:38:32', ''),
('68c51cede00', 'RQ000027', 'PE000027', 'PENGAJUAN', '', '', '2025-09-13 07:27:41', ''),
('68c7b2132e4', 'RQ000026', 'PG000005', 'Verifikasi Proses', '', '', '2025-09-15 06:28:35', ''),
('68c7c5d5aa8', 'RQ000028', 'PE000028', 'PENGAJUAN', '', '', '2025-09-15 07:52:53', ''),
('68c7cb5805a', 'RQ000027', 'PG000005', 'Dalam Proses Survei', '', '', '2025-09-15 08:16:24', ''),
('68c7cba903e', 'RQ000027', 'PG000005', 'Proses Survei Selesai', '', '', '2025-09-15 08:17:45', ''),
('68c7d3e08f7', 'RQ000028', 'PG000005', 'Dalam Proses Survei', '', '', '2025-09-15 08:52:48', ''),
('68c7d417c2a', 'RQ000028', 'PG000005', 'Proses Survei Selesai', '', '', '2025-09-15 08:53:43', ''),
('68c8c0bda5f', 'RQ000028', 'PG000005', 'Verifikasi Proses', '', '', '2025-09-16 01:43:25', ''),
('68c8c0c85c8', 'RQ000027', 'PG000005', 'Verifikasi Proses', '', '', '2025-09-16 01:43:36', ''),
('68c91c85b87', 'RQ000027', 'PG000005', 'Dalam Proses Pasang', '', '', '2025-09-16 08:15:01', ''),
('68c91f25071', 'RQ000014', 'PG000005', 'Putus Langganan', '', 'Wifi lemot', '2025-09-16 08:26:13', ''),
('68c91f3abc4', '', 'PG000005', 'Alat telah kembali', '', '', '2025-09-16 08:26:34', 'Sudah diambil'),
('68d0efc2e50', 'RQ000029', 'PE000029', 'PENGAJUAN', '', '', '2025-09-22 06:42:10', ''),
('68d148963ba', 'RQ000028', 'PG000005', 'Dalam Proses Pasang', '', '', '2025-09-22 13:01:10', ''),
('68d149b8692', 'RQ000027', 'PG000005', 'Terpasang', '', '', '2025-09-22 13:06:00', ''),
('68d14a939fa', 'RQ000028', 'PG000005', 'Terpasang', '', '', '2025-09-22 13:09:39', ''),
('68d241b4331', 'RQ000027', 'PG000005', 'Berhasil Active', '', '', '2025-09-23 06:44:04', ''),
('68d241b4e85', '', 'PG000005', 'Berhasil Active', '', '', '2025-09-23 06:44:04', ''),
('68d242315a9', 'RQ000028', 'PG000005', 'Berhasil Active', '', '', '2025-09-23 06:46:09', ''),
('68d242322a3', '', 'PG000005', 'Berhasil Active', '', '', '2025-09-23 06:46:10', ''),
('68d4b61a058', 'RQ000030', 'PE000030', 'PENGAJUAN', '', '', '2025-09-25 03:25:14', ''),
('68d50117e89', 'RQ000029', 'PG000005', 'Dalam Proses Survei', '', '', '2025-09-25 08:45:11', ''),
('68d50147040', 'RQ000029', 'PG000005', 'Proses Survei Selesai', '', '', '2025-09-25 08:45:59', ''),
('68d5e2cf685', 'RQ000029', 'PG000005', 'Verifikasi Proses', '', '', '2025-09-26 00:48:15', ''),
('68d7b1cf78f', 'RQ000029', 'PG000005', 'Dalam Proses Pasang', '', '', '2025-09-27 09:43:43', ''),
('68d7b25d50b', 'RQ000029', 'PG000005', 'Terpasang', '', '', '2025-09-27 09:46:05', ''),
('68d9dea3e89', 'RQ000031', 'PE000031', 'PENGAJUAN', '', '', '2025-09-29 01:19:31', ''),
('68d9e913361', 'RQ000029', 'PG000005', 'Berhasil Active', '', '', '2025-09-29 02:04:03', ''),
('68d9e91419d', '', 'PG000005', 'Berhasil Active', '', '', '2025-09-29 02:04:04', ''),
('68da3c63b5d', 'RQ000032', 'PE000032', 'PENGAJUAN', '', '', '2025-09-29 07:59:31', ''),
('68da48cf6a3', 'RQ000032', 'PG000005', 'Gagal', '', 'Pindah ke OLT Siman', '2025-09-29 08:52:31', 'Belum Terpasang'),
('68da4bf2a56', 'RQ000031', 'PG000005', 'Dalam Proses Survei', '', '', '2025-09-29 09:05:54', ''),
('68da5401b5c', 'RQ000031', 'PG000005', 'Proses Survei Selesai', '', '', '2025-09-29 09:40:17', ''),
('68da9110d0c', 'RQ000025', 'PG000005', 'Verifikasi Proses', '', '', '2025-09-29 14:00:48', ''),
('68de465802d', 'RQ000033', 'PE000033', 'PENGAJUAN', '', '', '2025-10-02 09:31:04', ''),
('68e0c7aec2e', 'RQ000033', 'PG000005', 'Dalam Proses Survei', '', '', '2025-10-04 07:07:26', ''),
('68e0c875d42', 'RQ000033', 'PG000005', 'Proses Survei Selesai', '', '', '2025-10-04 07:10:45', ''),
('68e0e20973f', 'RQ000033', 'PG000005', 'Verifikasi Proses', '', '', '2025-10-04 08:59:53', ''),
('68e75d7e7ad', 'RQ000034', 'PE000034', 'PENGAJUAN', '', '', '2025-10-09 07:00:14', ''),
('68e768b6949', 'RQ000034', 'PG000017', 'Dalam Proses Survei', '', '', '2025-10-09 07:48:06', ''),
('68e768d5222', 'RQ000034', 'PG000017', 'Proses Survei Selesai', '', '', '2025-10-09 07:48:37', ''),
('68e768def09', 'RQ000034', 'PG000017', 'Verifikasi Proses', '', '', '2025-10-09 07:48:46', ''),
('68e8e2c84a1', 'RQ000034', 'PG000010', 'Dalam Proses Pasang', '', '', '2025-10-10 10:41:12', ''),
('68e8e4047a5', 'RQ000034', 'PG000010', 'Terpasang', '', '', '2025-10-10 10:46:28', ''),
('68e921ce20b', 'RQ000034', 'PG000005', 'Berhasil Active', '', '', '2025-10-10 15:10:06', ''),
('68ee0c3950c', 'RQ000033', 'PG000010', 'Dalam Proses Pasang', '', '', '2025-10-14 08:39:21', ''),
('68ee0d7a083', 'RQ000033', 'PG000010', 'Terpasang', '', '', '2025-10-14 08:44:42', ''),
('68ee138c118', 'RQ000033', 'PG000017', 'Berhasil Active', '', '', '2025-10-14 09:10:36', ''),
('68ee138cb6c', '', 'PG000017', 'Berhasil Active', '', '', '2025-10-14 09:10:36', ''),
('68ef255c475', 'RQ000035', 'PE000035', 'PENGAJUAN', '', '', '2025-10-15 04:38:52', ''),
('68ef544007f', 'RQ000035', 'PG000014', 'Dalam Proses Survei', '', '', '2025-10-15 07:58:56', ''),
('68ef54ba633', 'RQ000035', 'PG000005', 'Proses Survei Selesai', '', '', '2025-10-15 08:00:58', ''),
('68f2e4275e4', 'RQ000035', 'PG000017', 'Verifikasi Proses', '', '', '2025-10-18 00:49:43', ''),
('68f33d81aff', 'RQ000036', 'PE000036', 'PENGAJUAN', '', '', '2025-10-18 07:10:57', ''),
('68f8d9a3de5', 'RQ000035', 'PG000010', 'Dalam Proses Pasang', '', '', '2025-10-22 13:18:27', ''),
('68f8e053638', 'RQ000035', 'PG000010', 'Terpasang', '', '', '2025-10-22 13:46:59', ''),
('68f98421777', 'RQ000035', 'PG000017', 'Berhasil Active', '', '', '2025-10-23 01:25:53', ''),
('68f98422484', '', 'PG000017', 'Berhasil Active', '', '', '2025-10-23 01:25:54', ''),
('68f9d52d7d9', 'RQ000036', 'PG000005', 'Dalam Proses Survei', '', '', '2025-10-23 07:11:41', ''),
('68f9d599873', 'RQ000036', 'PG000005', 'Proses Survei Selesai', '', '', '2025-10-23 07:13:29', ''),
('68fab9fe1ee', 'RQ000036', 'PG000017', 'Verifikasi Proses', '', '', '2025-10-23 23:27:58', ''),
('68fc3d4f8c9', 'RQ000037', 'PE000037', 'PENGAJUAN', '', '', '2025-10-25 03:00:31', ''),
('68fc7f70c27', 'RQ000037', 'PG000017', 'Gagal', '', 'Kelamaan pasangnya', '2025-10-25 07:42:40', 'Belum Terpasang'),
('68fefa695d9', 'RQ000038', 'PE000038', 'PENGAJUAN', '', '', '2025-10-27 04:51:53', ''),
('68ff1232b6b', 'RQ000039', 'PE000039', 'PENGAJUAN', '', '', '2025-10-27 06:33:22', ''),
('68ff30bc4a6', 'RQ000039', 'PG000014', 'Dalam Proses Survei', '', '', '2025-10-27 08:43:40', ''),
('68ff30ef7d6', 'RQ000039', 'PG000014', 'Proses Survei Selesai', '', '', '2025-10-27 08:44:31', ''),
('68ff41fe72f', 'RQ000038', 'PG000014', 'Dalam Proses Survei', '', '', '2025-10-27 09:57:18', ''),
('68ff422850c', 'RQ000038', 'PG000014', 'Proses Survei Selesai', '', '', '2025-10-27 09:58:00', ''),
('69004948827', 'RQ000040', 'PE000040', 'PENGAJUAN', '', '', '2025-10-28 04:40:40', ''),
('6900fbe6e1d', 'RQ000039', 'PG000005', 'Verifikasi Proses', '', '', '2025-10-28 17:22:46', ''),
('69015dff390', 'RQ000038', 'PG000017', 'Verifikasi Proses', '', '', '2025-10-29 00:21:19', ''),
('69046d6c160', 'RQ000040', 'PG000005', 'Dalam Proses Survei', '', '', '2025-10-31 08:03:56', ''),
('69046d94387', 'RQ000040', 'PG000005', 'Proses Survei Selesai', '', '', '2025-10-31 08:04:36', ''),
('6904b9c4906', 'RQ000038', 'PG000010', 'Dalam Proses Pasang', '', '', '2025-10-31 13:29:40', ''),
('6904bb03211', 'RQ000038', 'PG000010', 'Terpasang', '', '', '2025-10-31 13:34:59', ''),
('6904bb156d0', 'RQ000039', 'PG000010', 'Dalam Proses Pasang', '', '', '2025-10-31 13:35:17', ''),
('6904bc74b76', 'RQ000036', 'PG000010', 'Dalam Proses Pasang', '', '', '2025-10-31 13:41:08', ''),
('6904bd8d2f0', 'RQ000036', 'PG000010', 'Terpasang', '', '', '2025-10-31 13:45:49', ''),
('69058e874a6', 'RQ000036', 'PG000017', 'Berhasil Active', '', '', '2025-11-01 04:37:27', ''),
('6905a4550bc', 'RQ000040', 'PG000017', 'Verifikasi Proses', '', '', '2025-11-01 06:10:29', ''),
('6905ab395e9', 'RQ000038', 'PG000017', 'Berhasil Active', '', '', '2025-11-01 06:39:53', ''),
('6905ab3a3a5', '', 'PG000017', 'Berhasil Active', '', '', '2025-11-01 06:39:54', ''),
('6907814b4c1', 'RQ000031', 'PG000005', 'Verifikasi Proses', '', '', '2025-11-02 16:05:31', ''),
('6910baaf2cf', 'RQ000040', 'PG000010', 'Dalam Proses Pasang', '', '', '2025-11-09 16:00:47', ''),
('6910bc082d9', 'RQ000040', 'PG000010', 'Terpasang', '', '', '2025-11-09 16:06:32', ''),
('6912805228b', 'RQ000039', 'PG000010', 'Terpasang', '', '', '2025-11-11 00:16:18', ''),
('6912a139644', 'RQ000039', 'PG000017', 'Berhasil Active', '', '', '2025-11-11 02:36:41', ''),
('6912a13a63c', '', 'PG000017', 'Berhasil Active', '', '', '2025-11-11 02:36:42', ''),
('6912a20cca7', 'RQ000040', 'PG000017', 'Berhasil Active', '', '', '2025-11-11 02:40:12', ''),
('6912a20dc82', '', 'PG000017', 'Berhasil Active', '', '', '2025-11-11 02:40:13', ''),
('691c0cf17d1', 'RQ000041', 'PE000041', 'PENGAJUAN', '', '', '2025-11-18 06:06:41', ''),
('692953dd6af', 'RQ000041', 'PG000017', 'Gagal', '', 'Pindah App. Whusnet', '2025-11-28 07:48:45', 'Belum Terpasang'),
('692d166b175', 'RQ000042', 'PE000042', 'PENGAJUAN', '', '', '2025-12-01 04:15:39', ''),
('692d49ab71e', 'RQ000042', 'PG000005', 'Dalam Proses Survei', '', '', '2025-12-01 07:54:19', ''),
('692d4a1a212', 'RQ000042', 'PG000005', 'Proses Survei Selesai', '', '', '2025-12-01 07:56:10', ''),
('692e2305cad', 'RQ000042', 'PG000017', 'Gagal', '', 'Pindah ke App. Whusnet', '2025-12-01 23:21:41', 'Belum Terpasang'),
('695cb4b7744', 'RQ000043', 'PG000017', 'Berhasil Active', '', '', '2026-01-06 07:07:35', ''),
('695cbd72273', 'RQ000006', 'PG000014', 'Terpasang', '', '', '2026-01-06 07:44:50', ''),
('695cbfa2139', 'RQ000006', 'PG000017', 'Berhasil Active', '', '', '2026-01-06 07:54:10', ''),
('695cbfa3075', '', 'PG000017', 'Berhasil Active', '', '', '2026-01-06 07:54:11', ''),
('695cc00fe2f', 'RQ000012', 'PG000014', 'Dalam Proses Pasang', '', '', '2026-01-06 07:55:59', ''),
('695cc07f6f9', 'RQ000012', 'PG000014', 'Terpasang', '', '', '2026-01-06 07:57:51', ''),
('695e0b17001', 'RQ000012', 'PG000017', 'Berhasil Active', '', '', '2026-01-07 07:28:22', ''),
('6989478ee39', 'RQ000044', 'PE000044', 'PENGAJUAN', '', '', '2026-02-09 02:33:50', ''),
('698bf3cce24', 'RQ000044', 'PG000017', 'Gagal', '', 'Pindah ke aplikasi whusnet', '2026-02-11 03:13:16', 'Belum Terpasang');

-- --------------------------------------------------------

--
-- Table structure for table `router`
--

CREATE TABLE `router` (
  `IDROUTER` int(10) NOT NULL,
  `IP_ROUTER` varchar(15) NOT NULL,
  `NAMA_ROUTER` varchar(30) NOT NULL,
  `USERNAME` varchar(50) NOT NULL,
  `PASSWORD` varchar(250) NOT NULL,
  `PORT` varchar(5) NOT NULL,
  `STATUSROUTER` int(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `satuan`
--

CREATE TABLE `satuan` (
  `IDSATUAN` int(11) NOT NULL,
  `NAMASATUAN` varchar(30) DEFAULT NULL,
  `SEBUTAN` varchar(5) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `setting_billing`
--

CREATE TABLE `setting_billing` (
  `IDSETTING` int(5) NOT NULL,
  `JENIS_SETTING` varchar(200) NOT NULL,
  `VALUE_SETTING` varchar(200) NOT NULL,
  `SATUAN_SETTING` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `setting_billing`
--

INSERT INTO `setting_billing` (`IDSETTING`, `JENIS_SETTING`, `VALUE_SETTING`, `SATUAN_SETTING`) VALUES
(1, 'Tanggal jatuh tempo untuk pembarayan metode Pascabayar - Billing Cycle', '10', ''),
(2, 'Minimal berapa hari generate Invoice sebelum jatuh tempo untuk billing Fixed Date, isi 7 paling cepat', '7', ''),
(3, 'Batas waktu paling lambat untuk pembayaran invoice setelah jatuh tempo sebelum user pppoe di suspend, isi 0 jika tidak pernah', '7', ''),
(4, 'Berapa hari pelanggan di kirim notifikasi sebelum jatuh tempo, isi 0 jika tidak pernah', '7', ''),
(5, 'Waktu pelanggan di suspend / isolir oleh sistem jika tagihan belum di bayar', '23:45:00', ''),
(6, 'Kirim notifikasi saat terbit invoice', '1', ''),
(7, 'Kirim notifikasi status pembayaran', '1', ''),
(8, 'Kirim notifikasi status member', '1', ''),
(9, 'Merge Invoice: Jika invoice bulan lalu belum dibayar, invoice akan digabungkan dengan invoice bulan sekarang', '1', ''),
(10, 'Footer notifikasi WhatsApp', 'Lorem ipsum dolor sit amet consectetur, adipisicing elit. A aliquid quia sunt nobis? Neque, ea expedita, ', ''),
(11, 'Signature Invoice PDF', 'Quod officiis rem nobis nihil eum blanditiis minima itaque, cum inventore dolores adipisci exercitationem.', '');

-- --------------------------------------------------------

--
-- Table structure for table `set_app`
--

CREATE TABLE `set_app` (
  `ID` int(1) NOT NULL,
  `NAMA` varchar(50) NOT NULL,
  `ALAMAT` varchar(100) NOT NULL,
  `KONTAK` varchar(15) NOT NULL,
  `IDCABANG` int(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `statusbarang`
--

CREATE TABLE `statusbarang` (
  `IDSTATUSBARANG` int(11) NOT NULL,
  `KODEBARANG` varchar(10) DEFAULT NULL,
  `IDMERK` varchar(11) NOT NULL,
  `TGLINPUT` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `TGLUPDATE` datetime NOT NULL DEFAULT current_timestamp(),
  `TGLKEMBALI` date DEFAULT NULL,
  `STATUSBARANG` varchar(70) DEFAULT NULL,
  `STATUSKEADAAN` varchar(70) DEFAULT NULL,
  `SUMBERDAYAID` varchar(11) DEFAULT NULL,
  `IDPERMINTAAN` varchar(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sumberdaya`
--

CREATE TABLE `sumberdaya` (
  `IDSD` int(11) NOT NULL,
  `NAMA` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `survey_pemasangan_wifi`
--

CREATE TABLE `survey_pemasangan_wifi` (
  `IDSURVEY` varchar(11) NOT NULL,
  `IDPENGGUNA` varchar(11) DEFAULT NULL,
  `IDPERMINTAAN` varchar(11) NOT NULL,
  `LAT` varchar(50) DEFAULT NULL,
  `LONG` varchar(50) DEFAULT NULL,
  `GEOGRAFIS` varchar(150) DEFAULT NULL,
  `SINYAL` varchar(100) DEFAULT NULL,
  `FOTORUMAH` varchar(100) DEFAULT NULL,
  `ESTIMASIKEBUTUHAN` text DEFAULT NULL,
  `ALATPASIF` text NOT NULL,
  `KATEGORITINGKAT` varchar(10) NOT NULL,
  `BIAYAPEMASANGAN` float DEFAULT NULL,
  `TGLSURVEY` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `survey_pemasangan_wifi`
--

INSERT INTO `survey_pemasangan_wifi` (`IDSURVEY`, `IDPENGGUNA`, `IDPERMINTAAN`, `LAT`, `LONG`, `GEOGRAFIS`, `SINYAL`, `FOTORUMAH`, `ESTIMASIKEBUTUHAN`, `ALATPASIF`, `KATEGORITINGKAT`, `BIAYAPEMASANGAN`, `TGLSURVEY`) VALUES
('spw67dac915', 'PE000001', 'RQ000001', '-7.866487', '111.409471', NULL, NULL, NULL, NULL, '', '', NULL, NULL),
('spw6809b468', 'PE000004', 'RQ000004', '-7.86798', '111.46490', NULL, NULL, NULL, NULL, '', '', NULL, NULL),
('spw6809b584', 'PE000005', 'RQ000005', '-7.86798', '111.46490', NULL, NULL, NULL, NULL, '', '', NULL, NULL),
('spw6819a1b3', 'PE000006', 'RQ000006', '-7.857107', '111.468594', NULL, NULL, '3b7700bf7351698331a21b5f9a64b43e.jpg', 'Kabel cd 160m \r\nModen gpon \r\nPaycord\r\nAmbil koneksi dari  F1-01-00-PON4-ODP149', 'Kabel fiber dan patchore ', 'SEDANG', NULL, '2026-01-06 07:44:50'),
('spw6818b4d3', 'PE000007', 'RQ000007', '-7.862132', '111.468182', NULL, NULL, NULL, NULL, '', '', NULL, NULL),
('681980066b7', 'PE000008', 'RQ000008', '-7.873273', '111.471100', NULL, NULL, NULL, 'Router\r\nPathcore\r\nKabel fo 150 meter\r\n', '', 'MUDAH', NULL, '2025-05-06 03:23:51'),
('spw683462d4', 'PE000009', 'RQ000009', '-7.874967', '111.457384', NULL, NULL, 'ce58d3e2c46e8d8bfdf8bdea036c85da.jpg', 'DROPCORE 170m\r\nPatchore \r\nRouter xpon\r\nAmbil odp sandia \r\nF1-01-00-PON7-ODP20\r\nPelanggan ambil paket 13mbps', '', 'SEDANG', NULL, '2025-05-26 12:47:16'),
('spw684fc485', 'PE000010', 'RQ000010', '-7.866349', '111.452863', NULL, NULL, NULL, NULL, '', '', NULL, NULL),
('spw685ba07d', 'PE000011', 'RQ000011', '-7.866511', '111.405616', NULL, NULL, 'f0fedbb72ba5d577d6f9793712d1b041.jpg', 'Dropcor 280m\r\nPatchore\r\nRouter xpon\r\n', '', 'SULIT', NULL, '2025-06-25 07:08:45'),
('spw686b9760', 'PE000012', 'RQ000012', '-7.871125', '111.457744', NULL, NULL, '6a699522a5772415dcdf0307afe8d1e4.jpg', 'Kabel fiber 150m -+\r\nModem gpon \r\nColok port sandya \r\nAlat pasif gpon dll\r\nPaket 138 k ', 'Kabel fiber dan patchore', 'SULIT', NULL, '2026-01-06 07:57:51'),
('spw68872313', 'PE000013', 'RQ000013', '-7.871080', '111.467212', NULL, NULL, '986e005bbccad7453e12e81cc9df84e1.jpg', 'Kabel fo 100m\r\nPathcore\r\nRouter xphone', '', 'SEDANG', NULL, '2025-08-08 07:55:14'),
('spw688dd702', 'PE000014', 'RQ000014', '-7.866548', '111.457401', NULL, NULL, 'c12c6af3dc033ddf25c5bd16a747de94.jpg', 'Dropcore 180\r\nPatchore\r\nRouter xpon', '', 'SULIT', NULL, '2025-08-02 09:14:42'),
('spw689ec817', 'PE000015', 'RQ000015', '-7.863987', '111.462886', NULL, NULL, 'd7464caf878a6ff4f25141891890962c.jpg', 'Dropcore 150m\r\nPatchore \r\nRouter xpon ', '', 'SULIT', NULL, '2025-08-15 05:39:35'),
('spw6895b6a6', 'PE000016', 'RQ000016', '-7.867313', '111.455424', NULL, NULL, '18eea423d147f6cdc6d502658311b072.jpg', 'Router\r\nPathcore\r\nKabel fo 160m', '', 'SEDANG', NULL, '2025-08-08 08:34:46'),
('spw689c0828', 'PE000017', 'RQ000017', '-7.867296', '111.455452', NULL, NULL, 'd2f28fc2d86bdd06f3d67deb72380a26.jpg', 'Kabel fo 180m\r\nRouter\r\nPathcore', '', 'SEDANG', NULL, '2025-08-13 03:36:08'),
('spw68a7edba', 'PE000018', 'RQ000018', '-7.857094', '111.468021', NULL, NULL, 'dfa3e42c11ac56fb07424e438db289a2.jpg', 'Dropcore 150m\r\nRouter xpon\r\nPatchore ', '-', 'SULIT', NULL, '2025-08-22 04:34:57'),
('spw68abd208', 'PE000019', 'RQ000019', '-7.866602', '111.468009', NULL, NULL, '28e1e1681db0dbcdfeacdf8780cbb68d.jpg', 'Dropcore 150\r\nPatchore \r\nRouter xpon', '-', 'SULIT', NULL, '2025-08-25 04:27:23'),
('spw68afb6ab', 'PE000020', 'RQ000020', '-7.864792', '111.453754', NULL, NULL, '48fcd4b3294e123a28c3aed519a62fa0.jpg', 'Router\r\nPathcore\r\nKabel fo +/- 200m', '', 'SULIT', NULL, '2025-08-28 01:53:47'),
('spw68b4fc79', 'PE000021', 'RQ000021', '-7.873464', '111.460406', NULL, NULL, NULL, '150 25mbps\r\n60m\r\nPatchore \r\nRouter xpon', '', 'SULIT', NULL, '2025-09-01 01:52:57'),
('68b78d5ea0d', 'PE000022', 'RQ000022', '-7.869077', '111.466276', NULL, NULL, 'be358821dac88d4bcd10af64f9182692.jpg', 'Dropcore 200m\r\nPatchore\r\nRouter xpon\r\nIkut odp sandia', '', 'SULIT', NULL, '2025-09-03 00:37:32'),
('68b78d695af', 'PE000023', 'RQ000023', '-7.869077', '111.466276', NULL, NULL, NULL, 'Dropcore 200m\nPatchore\nRouter xpon\nIkut odp sandia', '', 'SULIT', NULL, NULL),
('spw68be34ea', 'PE000024', 'RQ000024', '-7.859154', '111.461010', NULL, NULL, 'f20b0af5bfd0bd50a6db3a38bef3b1a4.jpg', 'Router xphone\r\nPathcore\r\nKabel fo 180m', '', 'SULIT', NULL, '2025-09-08 01:44:10'),
('spw68c14733', 'PE000025', 'RQ000025', '-7.873535', '111.458417', NULL, NULL, '17b669cceccba31982e495d2f06e9e26.jpg', 'Dropcore 150-160\r\nPatchore\r\nRouter xpon\r\nKonek sandia', '', 'SULIT', NULL, '2025-09-10 09:38:59'),
('spw68c51166', 'PE000026', 'RQ000026', '-7.872698', '111.456275', NULL, NULL, 'd481de7dcd43872310db5a3d80bf07e7.jpg', 'Kabel fo 300m\r\nRouter\r\nPathcore', '', 'SEDANG', NULL, '2025-09-13 06:38:30'),
('spw68c7cba8', 'PE000027', 'RQ000027', '-7.866426', '111.458347', NULL, NULL, 'badd047c75be4bdabe51058624fa58bc.jpg', 'Kabel 150\r\nRouter\r\nPathcore', '', 'SEDANG', NULL, '2025-09-15 08:17:44'),
('spw68c7d416', 'PE000028', 'RQ000028', '-7.866509', '111.468148', NULL, NULL, 'b296b1c4401eb2b636ea9c0eb7c48616.jpg', 'Kabel 150\r\nRouter\r\nPathcore', '', 'SEDANG', NULL, '2025-09-15 08:53:42'),
('spw68d50146', 'PE000029', 'RQ000029', '-7.876179', '111.457796', NULL, NULL, 'f97c212949476480c4f13da7ddd721e4.jpg', 'Dropcore 150-170\r\nPatchore\r\nRouter xpon ', '', 'SULIT', NULL, '2025-09-25 08:45:58'),
('spw68d4b61a', 'PE000030', 'RQ000030', '-7.857975', '111.408024', NULL, NULL, NULL, NULL, '', '', NULL, NULL),
('spw68da5400', 'PE000031', 'RQ000031', '-7.864128', '111.469383', NULL, NULL, '801331e428f1e16fda5b7b3df8591565.jpg', 'Dropcore 270m\r\nPatchore\r\nRouter xpon \r\nAmbil odp dari sandia/Siman titik sama saja', '', 'SULIT', NULL, '2025-09-29 09:40:16'),
('spw68da3c63', 'PE000032', 'RQ000032', '-7.860140', '111.468787', NULL, NULL, NULL, NULL, '', '', NULL, NULL),
('spw68e0c875', 'PE000033', 'RQ000033', '-7.867462', '111.453677', NULL, NULL, '8bdff4283d934fa04950ea2d5afd9bcf.jpg', 'Dropcore 200m\r\nPatchore\r\nRouter xpon \r\nPelanggan request pemasangan bulan november', 'Dc 200m, pathcrd', 'SULIT', NULL, '2025-10-14 08:44:42'),
('spw68e768d5', 'PE000034', 'RQ000034', '-7.870019', '111.459322', NULL, NULL, '1a35dabc0bb63b3640d21624c2583686.jpg', 'Dropcore 150m\r\nPatchore\r\nRouter xpon \r\nAmbil odp sandia', 'Dc 120m\r\nPathcrd 1 set', 'SULIT', NULL, '2025-10-10 10:46:28'),
('spw68ef54b9', 'PE000035', 'RQ000035', '-7.875645', '111.460240', NULL, NULL, 'ee225641109b6f5863ad3212aa3bfd62.jpg', 'Router xphone\r\nPathcore\r\nKabel fo 200m', '', 'SEDANG', NULL, '2025-10-15 08:00:57'),
('spw68f9d599', 'PE000036', 'RQ000036', '-7.872373', '111.457194', NULL, NULL, '1dcfb5c82e3db84c721e4a4c59ab5f12.jpg', 'Kabel ±100\r\nPatch core \r\nModem', 'Pathcrd 2 ', 'SEDANG', NULL, '2025-10-31 13:45:49'),
('spw68fc3d4f', 'PE000037', 'RQ000037', '-7.864884', '111.461731', NULL, NULL, NULL, NULL, '', '', NULL, NULL),
('spw68ff4227', 'PE000038', 'RQ000038', '-7.856689', '111.467788', NULL, NULL, 'e13925f4841579c57f7ec00a01c6dceb.jpg', 'Kabel fiber 170m \r\nModem xpon \r\nPatch chord 1 pasang ', 'Pathcrd 2', 'SULIT', NULL, '2025-10-31 13:34:59'),
('spw68ff30ef', 'PE000039', 'RQ000039', '-7.856688', '111.467759', NULL, NULL, '098af1ddd891cd6db54cccca8c155408.jpg', 'Kabel fiber 170m \r\nModem x pon \r\nPatch chord 1 pasang ', 'Pathcrd 2', 'SULIT', NULL, '2025-11-11 00:16:18'),
('spw69046d94', 'PE000040', 'RQ000040', '-7.871947', '111.405577', NULL, NULL, '4c60bb4ebf97096b7dee398f32830799.jpg', 'Router\r\nPathcore\r\nKabel fo 200m', 'Pathcrd 2', 'SEDANG', NULL, '2025-11-09 16:06:32'),
('spw691c0cf1', 'PE000041', 'RQ000041', '-7.866827', '111.407419', NULL, NULL, NULL, NULL, '', '', NULL, NULL),
('spw692d4a19', 'PE000042', 'RQ000042', '-7.866885', '111.407687', NULL, NULL, 'c56ee9fbc93aa1d70bf8676337757fcf.jpg', 'Router xphone\r\nPathcore\r\nKabel fo 120m\r\nNb. Jika mau pasang kabari dulu biar bisa menyiapkan administrasi nya', '', 'SEDANG', NULL, '2025-12-01 07:56:09'),
('695cb4b7743', 'PE000043', 'RQ000043', '-7.876708', '-7.876708', NULL, NULL, '8023def334f09a8313f6ee727bb0646a.jpg', 'Dropcore 250\r\nPatchore \r\nRouter xpon \r\nOdp ODP : F1-01-00-PON7-ODP92 (sandia)', '', 'SULIT', NULL, '2026-01-06 07:08:09'),
('spw6989478e', 'PE000044', 'RQ000044', '-7.873769', '111.455750', NULL, NULL, NULL, NULL, '', '', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tampilan`
--

CREATE TABLE `tampilan` (
  `IDTAMPILAN` int(11) NOT NULL,
  `NAMA` varchar(50) NOT NULL,
  `ALT` varchar(100) NOT NULL,
  `IMG` varchar(50) NOT NULL,
  `SETUP` varchar(200) NOT NULL,
  `TGL_UPDATE` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `tampilan`
--

INSERT INTO `tampilan` (`IDTAMPILAN`, `NAMA`, `ALT`, `IMG`, `SETUP`, `TGL_UPDATE`) VALUES
(1, 'Logo Aplikasi', 'logo-aplikasi', 'Koala.jpg', '', '2021-07-02 01:44:51'),
(2, 'Faficon Aplikasi', 'faficon-aplikasi', 'Tulips.jpg', '', '2021-07-02 01:44:22'),
(3, 'Logo Invoice', 'logo-invoice', 'Chrysanthemum.jpg', '', '2021-07-02 01:42:15'),
(4, 'Logo Email', 'logo-email', '6f1eab21e2cd35941b4957b20186e918.jpeg', '', '2021-09-03 23:59:31'),
(5, 'Setting Wa - Template Pesan', 'wa_list', 'null', '', '2021-08-31 14:52:40'),
(6, 'Setting Url Review', 'url_review', 'null', 'http://review.whusnet.com', '2021-09-24 02:11:29');

-- --------------------------------------------------------

--
-- Table structure for table `tb_alamat`
--

CREATE TABLE `tb_alamat` (
  `IDWILAYAH` varchar(15) NOT NULL,
  `KOTA` varchar(50) NOT NULL,
  `KEC` varchar(50) NOT NULL,
  `DESA` varchar(50) NOT NULL,
  `CONCAT` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `tb_alamat`
--

INSERT INTO `tb_alamat` (`IDWILAYAH`, `KOTA`, `KEC`, `DESA`, `CONCAT`) VALUES
('3502010001', 'KABUPATEN PONOROGO', 'NGRAYUN', 'BAOSANKIDUL', 'BAOSANKIDUL-NGRAYUN-KABUPATEN PONOROGO'),
('3502010002', 'KABUPATEN PONOROGO', 'NGRAYUN', 'WONODADI', 'WONODADI-NGRAYUN-KABUPATEN PONOROGO'),
('3502010003', 'KABUPATEN PONOROGO', 'NGRAYUN', 'SENDANG', 'SENDANG-NGRAYUN-KABUPATEN PONOROGO'),
('3502010004', 'KABUPATEN PONOROGO', 'NGRAYUN', 'MRAYAN', 'MRAYAN-NGRAYUN-KABUPATEN PONOROGO'),
('3502010005', 'KABUPATEN PONOROGO', 'NGRAYUN', 'BINADE', 'BINADE-NGRAYUN-KABUPATEN PONOROGO'),
('3502010006', 'KABUPATEN PONOROGO', 'NGRAYUN', 'BAOSANLOR', 'BAOSANLOR-NGRAYUN-KABUPATEN PONOROGO'),
('3502010007', 'KABUPATEN PONOROGO', 'NGRAYUN', 'NGRAYUN', 'NGRAYUN-NGRAYUN-KABUPATEN PONOROGO'),
('3502010008', 'KABUPATEN PONOROGO', 'NGRAYUN', 'TEMON', 'TEMON-NGRAYUN-KABUPATEN PONOROGO'),
('3502010009', 'KABUPATEN PONOROGO', 'NGRAYUN', 'SELUR', 'SELUR-NGRAYUN-KABUPATEN PONOROGO'),
('3502010010', 'KABUPATEN PONOROGO', 'NGRAYUN', 'CEPOKO', 'CEPOKO-NGRAYUN-KABUPATEN PONOROGO'),
('3502010011', 'KABUPATEN PONOROGO', 'NGRAYUN', 'GEDANGAN', 'GEDANGAN-NGRAYUN-KABUPATEN PONOROGO'),
('3502020001', 'KABUPATEN PONOROGO', 'SLAHUNG', 'TUGUREJO', 'TUGUREJO-SLAHUNG-KABUPATEN PONOROGO'),
('3502020002', 'KABUPATEN PONOROGO', 'SLAHUNG', 'SENEPO', 'SENEPO-SLAHUNG-KABUPATEN PONOROGO'),
('3502020003', 'KABUPATEN PONOROGO', 'SLAHUNG', 'SLAHUNG', 'SLAHUNG-SLAHUNG-KABUPATEN PONOROGO'),
('3502020004', 'KABUPATEN PONOROGO', 'SLAHUNG', 'CALUK', 'CALUK-SLAHUNG-KABUPATEN PONOROGO'),
('3502020005', 'KABUPATEN PONOROGO', 'SLAHUNG', 'BROTO', 'BROTO-SLAHUNG-KABUPATEN PONOROGO'),
('3502020006', 'KABUPATEN PONOROGO', 'SLAHUNG', 'MENGGARE', 'MENGGARE-SLAHUNG-KABUPATEN PONOROGO'),
('3502020007', 'KABUPATEN PONOROGO', 'SLAHUNG', 'KAMBENG', 'KAMBENG-SLAHUNG-KABUPATEN PONOROGO'),
('3502020008', 'KABUPATEN PONOROGO', 'SLAHUNG', 'WATES', 'WATES-SLAHUNG-KABUPATEN PONOROGO'),
('3502020009', 'KABUPATEN PONOROGO', 'SLAHUNG', 'NGILO-ILO', 'NGILO-ILO-SLAHUNG-KABUPATEN PONOROGO'),
('3502020010', 'KABUPATEN PONOROGO', 'SLAHUNG', 'DURI', 'DURI-SLAHUNG-KABUPATEN PONOROGO'),
('3502020011', 'KABUPATEN PONOROGO', 'SLAHUNG', 'NGLONING', 'NGLONING-SLAHUNG-KABUPATEN PONOROGO'),
('3502020012', 'KABUPATEN PONOROGO', 'SLAHUNG', 'PLANCUNGAN', 'PLANCUNGAN-SLAHUNG-KABUPATEN PONOROGO'),
('3502020013', 'KABUPATEN PONOROGO', 'SLAHUNG', 'JEBENG', 'JEBENG-SLAHUNG-KABUPATEN PONOROGO'),
('3502020014', 'KABUPATEN PONOROGO', 'SLAHUNG', 'GALAK', 'GALAK-SLAHUNG-KABUPATEN PONOROGO'),
('3502020015', 'KABUPATEN PONOROGO', 'SLAHUNG', 'TRUNENG', 'TRUNENG-SLAHUNG-KABUPATEN PONOROGO'),
('3502020016', 'KABUPATEN PONOROGO', 'SLAHUNG', 'SIMO', 'SIMO-SLAHUNG-KABUPATEN PONOROGO'),
('3502020017', 'KABUPATEN PONOROGO', 'SLAHUNG', 'CRABAK', 'CRABAK-SLAHUNG-KABUPATEN PONOROGO'),
('3502020018', 'KABUPATEN PONOROGO', 'SLAHUNG', 'MOJOPITU', 'MOJOPITU-SLAHUNG-KABUPATEN PONOROGO'),
('3502020019', 'KABUPATEN PONOROGO', 'SLAHUNG', 'GUNDIK', 'GUNDIK-SLAHUNG-KABUPATEN PONOROGO'),
('3502020020', 'KABUPATEN PONOROGO', 'SLAHUNG', 'NAILAN', 'NAILAN-SLAHUNG-KABUPATEN PONOROGO'),
('3502020021', 'KABUPATEN PONOROGO', 'SLAHUNG', 'GOMBANG', 'GOMBANG-SLAHUNG-KABUPATEN PONOROGO'),
('3502020022', 'KABUPATEN PONOROGO', 'SLAHUNG', 'JANTI', 'JANTI-SLAHUNG-KABUPATEN PONOROGO'),
('3502030001', 'KABUPATEN PONOROGO', 'BUNGKAL', 'PELEM', 'PELEM-BUNGKAL-KABUPATEN PONOROGO'),
('3502030002', 'KABUPATEN PONOROGO', 'BUNGKAL', 'KORIPAN', 'KORIPAN-BUNGKAL-KABUPATEN PONOROGO'),
('3502030003', 'KABUPATEN PONOROGO', 'BUNGKAL', 'BEKARE', 'BEKARE-BUNGKAL-KABUPATEN PONOROGO'),
('3502030004', 'KABUPATEN PONOROGO', 'BUNGKAL', 'NAMBAK', 'NAMBAK-BUNGKAL-KABUPATEN PONOROGO'),
('3502030005', 'KABUPATEN PONOROGO', 'BUNGKAL', 'KALISAT', 'KALISAT-BUNGKAL-KABUPATEN PONOROGO'),
('3502030006', 'KABUPATEN PONOROGO', 'BUNGKAL', 'MUNGGU', 'MUNGGU-BUNGKAL-KABUPATEN PONOROGO'),
('3502030007', 'KABUPATEN PONOROGO', 'BUNGKAL', 'PAGER', 'PAGER-BUNGKAL-KABUPATEN PONOROGO'),
('3502030008', 'KABUPATEN PONOROGO', 'BUNGKAL', 'BELANG', 'BELANG-BUNGKAL-KABUPATEN PONOROGO'),
('3502030009', 'KABUPATEN PONOROGO', 'BUNGKAL', 'BUNGKAL', 'BUNGKAL-BUNGKAL-KABUPATEN PONOROGO'),
('3502030010', 'KABUPATEN PONOROGO', 'BUNGKAL', 'KETONGGO', 'KETONGGO-BUNGKAL-KABUPATEN PONOROGO'),
('3502030011', 'KABUPATEN PONOROGO', 'BUNGKAL', 'KUNTI', 'KUNTI-BUNGKAL-KABUPATEN PONOROGO'),
('3502030012', 'KABUPATEN PONOROGO', 'BUNGKAL', 'BANCAR', 'BANCAR-BUNGKAL-KABUPATEN PONOROGO'),
('3502030013', 'KABUPATEN PONOROGO', 'BUNGKAL', 'PADAS', 'PADAS-BUNGKAL-KABUPATEN PONOROGO'),
('3502030014', 'KABUPATEN PONOROGO', 'BUNGKAL', 'BUNGU', 'BUNGU-BUNGKAL-KABUPATEN PONOROGO'),
('3502030015', 'KABUPATEN PONOROGO', 'BUNGKAL', 'KUPUK', 'KUPUK-BUNGKAL-KABUPATEN PONOROGO'),
('3502030016', 'KABUPATEN PONOROGO', 'BUNGKAL', 'SAMBILAWANG', 'SAMBILAWANG-BUNGKAL-KABUPATEN PONOROGO'),
('3502030017', 'KABUPATEN PONOROGO', 'BUNGKAL', 'KWAJON', 'KWAJON-BUNGKAL-KABUPATEN PONOROGO'),
('3502030018', 'KABUPATEN PONOROGO', 'BUNGKAL', 'BEDIWETAN', 'BEDIWETAN-BUNGKAL-KABUPATEN PONOROGO'),
('3502030019', 'KABUPATEN PONOROGO', 'BUNGKAL', 'BEDIKULON', 'BEDIKULON-BUNGKAL-KABUPATEN PONOROGO'),
('3502040001', 'KABUPATEN PONOROGO', 'SAMBIT', 'GAJAH', 'GAJAH-SAMBIT-KABUPATEN PONOROGO'),
('3502040002', 'KABUPATEN PONOROGO', 'SAMBIT', 'WRINGINANOM', 'WRINGINANOM-SAMBIT-KABUPATEN PONOROGO'),
('3502040003', 'KABUPATEN PONOROGO', 'SAMBIT', 'NGADISANAN', 'NGADISANAN-SAMBIT-KABUPATEN PONOROGO'),
('3502040004', 'KABUPATEN PONOROGO', 'SAMBIT', 'MAGUWAN', 'MAGUWAN-SAMBIT-KABUPATEN PONOROGO'),
('3502040005', 'KABUPATEN PONOROGO', 'SAMBIT', 'NGLEWAN', 'NGLEWAN-SAMBIT-KABUPATEN PONOROGO'),
('3502040006', 'KABUPATEN PONOROGO', 'SAMBIT', 'BEDINGIN', 'BEDINGIN-SAMBIT-KABUPATEN PONOROGO'),
('3502040007', 'KABUPATEN PONOROGO', 'SAMBIT', 'BANCANGAN', 'BANCANGAN-SAMBIT-KABUPATEN PONOROGO'),
('3502040008', 'KABUPATEN PONOROGO', 'SAMBIT', 'CAMPUREJO', 'CAMPUREJO-SAMBIT-KABUPATEN PONOROGO'),
('3502040009', 'KABUPATEN PONOROGO', 'SAMBIT', 'CAMPURSARI', 'CAMPURSARI-SAMBIT-KABUPATEN PONOROGO'),
('3502040010', 'KABUPATEN PONOROGO', 'SAMBIT', 'BULU', 'BULU-SAMBIT-KABUPATEN PONOROGO'),
('3502040011', 'KABUPATEN PONOROGO', 'SAMBIT', 'SAMBIT', 'SAMBIT-SAMBIT-KABUPATEN PONOROGO'),
('3502040012', 'KABUPATEN PONOROGO', 'SAMBIT', 'BESUKI', 'BESUKI-SAMBIT-KABUPATEN PONOROGO'),
('3502040013', 'KABUPATEN PONOROGO', 'SAMBIT', 'WILANGAN', 'WILANGAN-SAMBIT-KABUPATEN PONOROGO'),
('3502040014', 'KABUPATEN PONOROGO', 'SAMBIT', 'BANGSALAN', 'BANGSALAN-SAMBIT-KABUPATEN PONOROGO'),
('3502040015', 'KABUPATEN PONOROGO', 'SAMBIT', 'KEMUNING', 'KEMUNING-SAMBIT-KABUPATEN PONOROGO'),
('3502040016', 'KABUPATEN PONOROGO', 'SAMBIT', 'JRAKAH', 'JRAKAH-SAMBIT-KABUPATEN PONOROGO'),
('3502050001', 'KABUPATEN PONOROGO', 'SAWOO', 'TUMPUK', 'TUMPUK-SAWOO-KABUPATEN PONOROGO'),
('3502050002', 'KABUPATEN PONOROGO', 'SAWOO', 'PANGKAL', 'PANGKAL-SAWOO-KABUPATEN PONOROGO'),
('3502050003', 'KABUPATEN PONOROGO', 'SAWOO', 'TUMPAKPELEM', 'TUMPAKPELEM-SAWOO-KABUPATEN PONOROGO'),
('3502050004', 'KABUPATEN PONOROGO', 'SAWOO', 'TEMPURAN', 'TEMPURAN-SAWOO-KABUPATEN PONOROGO'),
('3502050005', 'KABUPATEN PONOROGO', 'SAWOO', 'SRITI', 'SRITI-SAWOO-KABUPATEN PONOROGO'),
('3502050006', 'KABUPATEN PONOROGO', 'SAWOO', 'TEMON', 'TEMON-SAWOO-KABUPATEN PONOROGO'),
('3502050007', 'KABUPATEN PONOROGO', 'SAWOO', 'SAWOO', 'SAWOO-SAWOO-KABUPATEN PONOROGO'),
('3502050008', 'KABUPATEN PONOROGO', 'SAWOO', 'PRAYUNGAN', 'PRAYUNGAN-SAWOO-KABUPATEN PONOROGO'),
('3502050009', 'KABUPATEN PONOROGO', 'SAWOO', 'TUGUREJO', 'TUGUREJO-SAWOO-KABUPATEN PONOROGO'),
('3502050010', 'KABUPATEN PONOROGO', 'SAWOO', 'GROGOL', 'GROGOL-SAWOO-KABUPATEN PONOROGO'),
('3502050011', 'KABUPATEN PONOROGO', 'SAWOO', 'KETRO', 'KETRO-SAWOO-KABUPATEN PONOROGO'),
('3502050012', 'KABUPATEN PONOROGO', 'SAWOO', 'KORI', 'KORI-SAWOO-KABUPATEN PONOROGO'),
('3502050013', 'KABUPATEN PONOROGO', 'SAWOO', 'BONDRANG', 'BONDRANG-SAWOO-KABUPATEN PONOROGO'),
('3502050014', 'KABUPATEN PONOROGO', 'SAWOO', 'NGINDENG', 'NGINDENG-SAWOO-KABUPATEN PONOROGO'),
('3502060001', 'KABUPATEN PONOROGO', 'SOOKO', 'NGADIROJO', 'NGADIROJO-SOOKO-KABUPATEN PONOROGO'),
('3502060002', 'KABUPATEN PONOROGO', 'SOOKO', 'KLEPU', 'KLEPU-SOOKO-KABUPATEN PONOROGO'),
('3502060003', 'KABUPATEN PONOROGO', 'SOOKO', 'SURU', 'SURU-SOOKO-KABUPATEN PONOROGO'),
('3502060004', 'KABUPATEN PONOROGO', 'SOOKO', 'SOOKO', 'SOOKO-SOOKO-KABUPATEN PONOROGO'),
('3502060005', 'KABUPATEN PONOROGO', 'SOOKO', 'BEDOHO', 'BEDOHO-SOOKO-KABUPATEN PONOROGO'),
('3502060006', 'KABUPATEN PONOROGO', 'SOOKO', 'JURUG', 'JURUG-SOOKO-KABUPATEN PONOROGO'),
('3502061001', 'KABUPATEN PONOROGO', 'PUDAK', 'BANJARJO', 'BANJARJO-PUDAK-KABUPATEN PONOROGO'),
('3502061002', 'KABUPATEN PONOROGO', 'PUDAK', 'PUDAK WETAN', 'PUDAK WETAN-PUDAK-KABUPATEN PONOROGO'),
('3502061003', 'KABUPATEN PONOROGO', 'PUDAK', 'PUDAK KULON', 'PUDAK KULON-PUDAK-KABUPATEN PONOROGO'),
('3502061004', 'KABUPATEN PONOROGO', 'PUDAK', 'KRISIK', 'KRISIK-PUDAK-KABUPATEN PONOROGO'),
('3502061005', 'KABUPATEN PONOROGO', 'PUDAK', 'TAMBANG', 'TAMBANG-PUDAK-KABUPATEN PONOROGO'),
('3502061006', 'KABUPATEN PONOROGO', 'PUDAK', 'BARENG', 'BARENG-PUDAK-KABUPATEN PONOROGO'),
('3502070001', 'KABUPATEN PONOROGO', 'PULUNG', 'KARANGPATIHAN', 'KARANGPATIHAN-PULUNG-KABUPATEN PONOROGO'),
('3502070002', 'KABUPATEN PONOROGO', 'PULUNG', 'TEGALREJO', 'TEGALREJO-PULUNG-KABUPATEN PONOROGO'),
('3502070003', 'KABUPATEN PONOROGO', 'PULUNG', 'BEDRUG', 'BEDRUG-PULUNG-KABUPATEN PONOROGO'),
('3502070004', 'KABUPATEN PONOROGO', 'PULUNG', 'WAGIRKIDUL', 'WAGIRKIDUL-PULUNG-KABUPATEN PONOROGO'),
('3502070005', 'KABUPATEN PONOROGO', 'PULUNG', 'SINGGAHAN', 'SINGGAHAN-PULUNG-KABUPATEN PONOROGO'),
('3502070006', 'KABUPATEN PONOROGO', 'PULUNG', 'PATIK', 'PATIK-PULUNG-KABUPATEN PONOROGO'),
('3502070007', 'KABUPATEN PONOROGO', 'PULUNG', 'PULUNG', 'PULUNG-PULUNG-KABUPATEN PONOROGO'),
('3502070008', 'KABUPATEN PONOROGO', 'PULUNG', 'PULUNG MERDIKO', 'PULUNG MERDIKO-PULUNG-KABUPATEN PONOROGO'),
('3502070009', 'KABUPATEN PONOROGO', 'PULUNG', 'SIDOHARJO', 'SIDOHARJO-PULUNG-KABUPATEN PONOROGO'),
('3502070010', 'KABUPATEN PONOROGO', 'PULUNG', 'WOTAN', 'WOTAN-PULUNG-KABUPATEN PONOROGO'),
('3502070011', 'KABUPATEN PONOROGO', 'PULUNG', 'PLUNTURAN', 'PLUNTURAN-PULUNG-KABUPATEN PONOROGO'),
('3502070012', 'KABUPATEN PONOROGO', 'PULUNG', 'POMAHAN', 'POMAHAN-PULUNG-KABUPATEN PONOROGO'),
('3502070013', 'KABUPATEN PONOROGO', 'PULUNG', 'KESUGIHAN', 'KESUGIHAN-PULUNG-KABUPATEN PONOROGO'),
('3502070014', 'KABUPATEN PONOROGO', 'PULUNG', 'SERAG', 'SERAG-PULUNG-KABUPATEN PONOROGO'),
('3502070015', 'KABUPATEN PONOROGO', 'PULUNG', 'WAYANG', 'WAYANG-PULUNG-KABUPATEN PONOROGO'),
('3502070016', 'KABUPATEN PONOROGO', 'PULUNG', 'MUNGGUNG', 'MUNGGUNG-PULUNG-KABUPATEN PONOROGO'),
('3502070017', 'KABUPATEN PONOROGO', 'PULUNG', 'BEKIRING', 'BEKIRING-PULUNG-KABUPATEN PONOROGO'),
('3502070018', 'KABUPATEN PONOROGO', 'PULUNG', 'BANARAN', 'BANARAN-PULUNG-KABUPATEN PONOROGO'),
('3502080001', 'KABUPATEN PONOROGO', 'MLARAK', 'TUGU', 'TUGU-MLARAK-KABUPATEN PONOROGO'),
('3502080002', 'KABUPATEN PONOROGO', 'MLARAK', 'CANDI', 'CANDI-MLARAK-KABUPATEN PONOROGO'),
('3502080003', 'KABUPATEN PONOROGO', 'MLARAK', 'TOTOKAN', 'TOTOKAN-MLARAK-KABUPATEN PONOROGO'),
('3502080004', 'KABUPATEN PONOROGO', 'MLARAK', 'NGRUKEM', 'NGRUKEM-MLARAK-KABUPATEN PONOROGO'),
('3502080005', 'KABUPATEN PONOROGO', 'MLARAK', 'SIWALAN', 'SIWALAN-MLARAK-KABUPATEN PONOROGO'),
('3502080006', 'KABUPATEN PONOROGO', 'MLARAK', 'JORESAN', 'JORESAN-MLARAK-KABUPATEN PONOROGO'),
('3502080007', 'KABUPATEN PONOROGO', 'MLARAK', 'NGLUMPANG', 'NGLUMPANG-MLARAK-KABUPATEN PONOROGO'),
('3502080008', 'KABUPATEN PONOROGO', 'MLARAK', 'GONTOR', 'GONTOR-MLARAK-KABUPATEN PONOROGO'),
('3502080009', 'KABUPATEN PONOROGO', 'MLARAK', 'GANDU', 'GANDU-MLARAK-KABUPATEN PONOROGO'),
('3502080010', 'KABUPATEN PONOROGO', 'MLARAK', 'JABUNG', 'JABUNG-MLARAK-KABUPATEN PONOROGO'),
('3502080011', 'KABUPATEN PONOROGO', 'MLARAK', 'BAJANG', 'BAJANG-MLARAK-KABUPATEN PONOROGO'),
('3502080012', 'KABUPATEN PONOROGO', 'MLARAK', 'MLARAK', 'MLARAK-MLARAK-KABUPATEN PONOROGO'),
('3502080013', 'KABUPATEN PONOROGO', 'MLARAK', 'SERANGAN', 'SERANGAN-MLARAK-KABUPATEN PONOROGO'),
('3502080014', 'KABUPATEN PONOROGO', 'MLARAK', 'SUREN', 'SUREN-MLARAK-KABUPATEN PONOROGO'),
('3502080015', 'KABUPATEN PONOROGO', 'MLARAK', 'KAPONAN', 'KAPONAN-MLARAK-KABUPATEN PONOROGO'),
('3502090001', 'KABUPATEN PONOROGO', 'SIMAN', 'DEMANGAN', 'DEMANGAN-SIMAN-KABUPATEN PONOROGO'),
('3502090002', 'KABUPATEN PONOROGO', 'SIMAN', 'NGABAR', 'NGABAR-SIMAN-KABUPATEN PONOROGO'),
('3502090003', 'KABUPATEN PONOROGO', 'SIMAN', 'MADUSARI', 'MADUSARI-SIMAN-KABUPATEN PONOROGO'),
('3502090004', 'KABUPATEN PONOROGO', 'SIMAN', 'BETON', 'BETON-SIMAN-KABUPATEN PONOROGO'),
('3502090005', 'KABUPATEN PONOROGO', 'SIMAN', 'SEKARAN', 'SEKARAN-SIMAN-KABUPATEN PONOROGO'),
('3502090006', 'KABUPATEN PONOROGO', 'SIMAN', 'BRAHU', 'BRAHU-SIMAN-KABUPATEN PONOROGO'),
('3502090007', 'KABUPATEN PONOROGO', 'SIMAN', 'KEPUH RUBUH', 'KEPUH RUBUH-SIMAN-KABUPATEN PONOROGO'),
('3502090008', 'KABUPATEN PONOROGO', 'SIMAN', 'SAWUH', 'SAWUH-SIMAN-KABUPATEN PONOROGO'),
('3502090009', 'KABUPATEN PONOROGO', 'SIMAN', 'JARAK', 'JARAK-SIMAN-KABUPATEN PONOROGO'),
('3502090010', 'KABUPATEN PONOROGO', 'SIMAN', 'TRANJANG', 'TRANJANG-SIMAN-KABUPATEN PONOROGO'),
('3502090011', 'KABUPATEN PONOROGO', 'SIMAN', 'PIJERAN', 'PIJERAN-SIMAN-KABUPATEN PONOROGO'),
('3502090012', 'KABUPATEN PONOROGO', 'SIMAN', 'MANUK', 'MANUK-SIMAN-KABUPATEN PONOROGO'),
('3502090013', 'KABUPATEN PONOROGO', 'SIMAN', 'SIMAN', 'SIMAN-SIMAN-KABUPATEN PONOROGO'),
('3502090014', 'KABUPATEN PONOROGO', 'SIMAN', 'PATIHAN KIDUL', 'PATIHAN KIDUL-SIMAN-KABUPATEN PONOROGO'),
('3502090015', 'KABUPATEN PONOROGO', 'SIMAN', 'RONOSENTANAN', 'RONOSENTANAN-SIMAN-KABUPATEN PONOROGO'),
('3502090016', 'KABUPATEN PONOROGO', 'SIMAN', 'TAJUG', 'TAJUG-SIMAN-KABUPATEN PONOROGO'),
('3502090017', 'KABUPATEN PONOROGO', 'SIMAN', 'RONOWIJAYAN', 'RONOWIJAYAN-SIMAN-KABUPATEN PONOROGO'),
('3502090018', 'KABUPATEN PONOROGO', 'SIMAN', 'MANGUNSUMAN', 'MANGUNSUMAN-SIMAN-KABUPATEN PONOROGO'),
('3502100001', 'KABUPATEN PONOROGO', 'JETIS', 'NGASINAN', 'NGASINAN-JETIS-KABUPATEN PONOROGO'),
('3502100002', 'KABUPATEN PONOROGO', 'JETIS', 'KUTUKULON', 'KUTUKULON-JETIS-KABUPATEN PONOROGO'),
('3502100003', 'KABUPATEN PONOROGO', 'JETIS', 'KUTUWETAN', 'KUTUWETAN-JETIS-KABUPATEN PONOROGO'),
('3502100004', 'KABUPATEN PONOROGO', 'JETIS', 'KRADENAN', 'KRADENAN-JETIS-KABUPATEN PONOROGO'),
('3502100005', 'KABUPATEN PONOROGO', 'JETIS', 'MOJOMATI', 'MOJOMATI-JETIS-KABUPATEN PONOROGO'),
('3502100006', 'KABUPATEN PONOROGO', 'JETIS', 'COPER', 'COPER-JETIS-KABUPATEN PONOROGO'),
('3502100007', 'KABUPATEN PONOROGO', 'JETIS', 'MOJOREJO', 'MOJOREJO-JETIS-KABUPATEN PONOROGO'),
('3502100008', 'KABUPATEN PONOROGO', 'JETIS', 'KARANGGEBANG', 'KARANGGEBANG-JETIS-KABUPATEN PONOROGO'),
('3502100009', 'KABUPATEN PONOROGO', 'JETIS', 'JETIS', 'JETIS-JETIS-KABUPATEN PONOROGO'),
('3502100010', 'KABUPATEN PONOROGO', 'JETIS', 'TEGALSARI', 'TEGALSARI-JETIS-KABUPATEN PONOROGO'),
('3502100011', 'KABUPATEN PONOROGO', 'JETIS', 'WONOKETRO', 'WONOKETRO-JETIS-KABUPATEN PONOROGO'),
('3502100012', 'KABUPATEN PONOROGO', 'JETIS', 'JOSARI', 'JOSARI-JETIS-KABUPATEN PONOROGO'),
('3502100013', 'KABUPATEN PONOROGO', 'JETIS', 'TURI', 'TURI-JETIS-KABUPATEN PONOROGO'),
('3502100014', 'KABUPATEN PONOROGO', 'JETIS', 'WINONG', 'WINONG-JETIS-KABUPATEN PONOROGO'),
('3502110001', 'KABUPATEN PONOROGO', 'BALONG', 'PANDAK', 'PANDAK-BALONG-KABUPATEN PONOROGO'),
('3502110002', 'KABUPATEN PONOROGO', 'BALONG', 'BULUKIDUL', 'BULUKIDUL-BALONG-KABUPATEN PONOROGO'),
('3502110003', 'KABUPATEN PONOROGO', 'BALONG', 'BULAK', 'BULAK-BALONG-KABUPATEN PONOROGO'),
('3502110004', 'KABUPATEN PONOROGO', 'BALONG', 'NGENDUT', 'NGENDUT-BALONG-KABUPATEN PONOROGO'),
('3502110005', 'KABUPATEN PONOROGO', 'BALONG', 'KARANGPATIHAN', 'KARANGPATIHAN-BALONG-KABUPATEN PONOROGO'),
('3502110006', 'KABUPATEN PONOROGO', 'BALONG', 'SUMBEREJO', 'SUMBEREJO-BALONG-KABUPATEN PONOROGO'),
('3502110007', 'KABUPATEN PONOROGO', 'BALONG', 'NGUMPUL', 'NGUMPUL-BALONG-KABUPATEN PONOROGO'),
('3502110008', 'KABUPATEN PONOROGO', 'BALONG', 'NGRAKET', 'NGRAKET-BALONG-KABUPATEN PONOROGO'),
('3502110009', 'KABUPATEN PONOROGO', 'BALONG', 'DADAPAN', 'DADAPAN-BALONG-KABUPATEN PONOROGO'),
('3502110010', 'KABUPATEN PONOROGO', 'BALONG', 'SINGKIL', 'SINGKIL-BALONG-KABUPATEN PONOROGO'),
('3502110011', 'KABUPATEN PONOROGO', 'BALONG', 'KARANGAN', 'KARANGAN-BALONG-KABUPATEN PONOROGO'),
('3502110012', 'KABUPATEN PONOROGO', 'BALONG', 'BAJANG', 'BAJANG-BALONG-KABUPATEN PONOROGO'),
('3502110013', 'KABUPATEN PONOROGO', 'BALONG', 'BALONG', 'BALONG-BALONG-KABUPATEN PONOROGO'),
('3502110014', 'KABUPATEN PONOROGO', 'BALONG', 'JALEN', 'JALEN-BALONG-KABUPATEN PONOROGO'),
('3502110015', 'KABUPATEN PONOROGO', 'BALONG', 'KARANGMOJO', 'KARANGMOJO-BALONG-KABUPATEN PONOROGO'),
('3502110016', 'KABUPATEN PONOROGO', 'BALONG', 'SEDARAT', 'SEDARAT-BALONG-KABUPATEN PONOROGO'),
('3502110017', 'KABUPATEN PONOROGO', 'BALONG', 'PURWOREJO', 'PURWOREJO-BALONG-KABUPATEN PONOROGO'),
('3502110018', 'KABUPATEN PONOROGO', 'BALONG', 'TATUNG', 'TATUNG-BALONG-KABUPATEN PONOROGO'),
('3502110019', 'KABUPATEN PONOROGO', 'BALONG', 'MUNENG', 'MUNENG-BALONG-KABUPATEN PONOROGO'),
('3502110020', 'KABUPATEN PONOROGO', 'BALONG', 'NGAMPEL', 'NGAMPEL-BALONG-KABUPATEN PONOROGO'),
('3502120001', 'KABUPATEN PONOROGO', 'KAUMAN', 'TEGALOMBO', 'TEGALOMBO-KAUMAN-KABUPATEN PONOROGO'),
('3502120002', 'KABUPATEN PONOROGO', 'KAUMAN', 'NONGKODONO', 'NONGKODONO-KAUMAN-KABUPATEN PONOROGO'),
('3502120003', 'KABUPATEN PONOROGO', 'KAUMAN', 'SUKOSARI', 'SUKOSARI-KAUMAN-KABUPATEN PONOROGO'),
('3502120004', 'KABUPATEN PONOROGO', 'KAUMAN', 'NGRANDU', 'NGRANDU-KAUMAN-KABUPATEN PONOROGO'),
('3502120005', 'KABUPATEN PONOROGO', 'KAUMAN', 'NGLARANGAN', 'NGLARANGAN-KAUMAN-KABUPATEN PONOROGO'),
('3502120006', 'KABUPATEN PONOROGO', 'KAUMAN', 'BRINGIN', 'BRINGIN-KAUMAN-KABUPATEN PONOROGO'),
('3502120007', 'KABUPATEN PONOROGO', 'KAUMAN', 'PENGKOL', 'PENGKOL-KAUMAN-KABUPATEN PONOROGO'),
('3502120008', 'KABUPATEN PONOROGO', 'KAUMAN', 'GABEL', 'GABEL-KAUMAN-KABUPATEN PONOROGO'),
('3502120009', 'KABUPATEN PONOROGO', 'KAUMAN', 'CILUK', 'CILUK-KAUMAN-KABUPATEN PONOROGO'),
('3502120010', 'KABUPATEN PONOROGO', 'KAUMAN', 'SEMANDING', 'SEMANDING-KAUMAN-KABUPATEN PONOROGO'),
('3502120011', 'KABUPATEN PONOROGO', 'KAUMAN', 'TOSANAN', 'TOSANAN-KAUMAN-KABUPATEN PONOROGO'),
('3502120012', 'KABUPATEN PONOROGO', 'KAUMAN', 'MARON', 'MARON-KAUMAN-KABUPATEN PONOROGO'),
('3502120013', 'KABUPATEN PONOROGO', 'KAUMAN', 'SOMOROTO', 'SOMOROTO-KAUMAN-KABUPATEN PONOROGO'),
('3502120014', 'KABUPATEN PONOROGO', 'KAUMAN', 'PLOSOJENAR', 'PLOSOJENAR-KAUMAN-KABUPATEN PONOROGO'),
('3502120015', 'KABUPATEN PONOROGO', 'KAUMAN', 'CARAT', 'CARAT-KAUMAN-KABUPATEN PONOROGO'),
('3502120016', 'KABUPATEN PONOROGO', 'KAUMAN', 'KAUMAN', 'KAUMAN-KAUMAN-KABUPATEN PONOROGO'),
('3502130001', 'KABUPATEN PONOROGO', 'JAMBON', 'KREBET', 'KREBET-JAMBON-KABUPATEN PONOROGO'),
('3502130002', 'KABUPATEN PONOROGO', 'JAMBON', 'JONGGOL', 'JONGGOL-JAMBON-KABUPATEN PONOROGO'),
('3502130003', 'KABUPATEN PONOROGO', 'JAMBON', 'POKO', 'POKO-JAMBON-KABUPATEN PONOROGO'),
('3502130004', 'KABUPATEN PONOROGO', 'JAMBON', 'BRINGINAN', 'BRINGINAN-JAMBON-KABUPATEN PONOROGO'),
('3502130005', 'KABUPATEN PONOROGO', 'JAMBON', 'SENDANG', 'SENDANG-JAMBON-KABUPATEN PONOROGO'),
('3502130006', 'KABUPATEN PONOROGO', 'JAMBON', 'KARANG LOKIDUL', 'KARANG LOKIDUL-JAMBON-KABUPATEN PONOROGO'),
('3502130007', 'KABUPATEN PONOROGO', 'JAMBON', 'BULU LOR', 'BULU LOR-JAMBON-KABUPATEN PONOROGO'),
('3502130008', 'KABUPATEN PONOROGO', 'JAMBON', 'JAMBON', 'JAMBON-JAMBON-KABUPATEN PONOROGO'),
('3502130009', 'KABUPATEN PONOROGO', 'JAMBON', 'BLEMBEM', 'BLEMBEM-JAMBON-KABUPATEN PONOROGO'),
('3502130010', 'KABUPATEN PONOROGO', 'JAMBON', 'PULOSARI', 'PULOSARI-JAMBON-KABUPATEN PONOROGO'),
('3502130011', 'KABUPATEN PONOROGO', 'JAMBON', 'MENANG', 'MENANG-JAMBON-KABUPATEN PONOROGO'),
('3502130012', 'KABUPATEN PONOROGO', 'JAMBON', 'SRANDIL', 'SRANDIL-JAMBON-KABUPATEN PONOROGO'),
('3502130013', 'KABUPATEN PONOROGO', 'JAMBON', 'SIDOHARJO', 'SIDOHARJO-JAMBON-KABUPATEN PONOROGO'),
('3502140001', 'KABUPATEN PONOROGO', 'BADEGAN', 'DAYAKAN', 'DAYAKAN-BADEGAN-KABUPATEN PONOROGO'),
('3502140002', 'KABUPATEN PONOROGO', 'BADEGAN', 'KARANGAN', 'KARANGAN-BADEGAN-KABUPATEN PONOROGO'),
('3502140003', 'KABUPATEN PONOROGO', 'BADEGAN', 'TANJUNGGUNUNG', 'TANJUNGGUNUNG-BADEGAN-KABUPATEN PONOROGO'),
('3502140004', 'KABUPATEN PONOROGO', 'BADEGAN', 'KARANGJOHO', 'KARANGJOHO-BADEGAN-KABUPATEN PONOROGO'),
('3502140005', 'KABUPATEN PONOROGO', 'BADEGAN', 'TANJUNGREJO', 'TANJUNGREJO-BADEGAN-KABUPATEN PONOROGO'),
('3502140006', 'KABUPATEN PONOROGO', 'BADEGAN', 'BANDARALIM', 'BANDARALIM-BADEGAN-KABUPATEN PONOROGO'),
('3502140007', 'KABUPATEN PONOROGO', 'BADEGAN', 'KAPURAN', 'KAPURAN-BADEGAN-KABUPATEN PONOROGO'),
('3502140008', 'KABUPATEN PONOROGO', 'BADEGAN', 'BADEGAN', 'BADEGAN-BADEGAN-KABUPATEN PONOROGO'),
('3502140009', 'KABUPATEN PONOROGO', 'BADEGAN', 'WATUBONANG', 'WATUBONANG-BADEGAN-KABUPATEN PONOROGO'),
('3502140010', 'KABUPATEN PONOROGO', 'BADEGAN', 'BITING', 'BITING-BADEGAN-KABUPATEN PONOROGO'),
('3502150001', 'KABUPATEN PONOROGO', 'SAMPUNG', 'GELANGKULON', 'GELANGKULON-SAMPUNG-KABUPATEN PONOROGO'),
('3502150002', 'KABUPATEN PONOROGO', 'SAMPUNG', 'KARANG WALUH', 'KARANG WALUH-SAMPUNG-KABUPATEN PONOROGO'),
('3502150003', 'KABUPATEN PONOROGO', 'SAMPUNG', 'GLINGGANG', 'GLINGGANG-SAMPUNG-KABUPATEN PONOROGO'),
('3502150004', 'KABUPATEN PONOROGO', 'SAMPUNG', 'CARANG REJO', 'CARANG REJO-SAMPUNG-KABUPATEN PONOROGO'),
('3502150005', 'KABUPATEN PONOROGO', 'SAMPUNG', 'TULUNG', 'TULUNG-SAMPUNG-KABUPATEN PONOROGO'),
('3502150006', 'KABUPATEN PONOROGO', 'SAMPUNG', 'KUNTI', 'KUNTI-SAMPUNG-KABUPATEN PONOROGO'),
('3502150007', 'KABUPATEN PONOROGO', 'SAMPUNG', 'PAGERUKIR', 'PAGERUKIR-SAMPUNG-KABUPATEN PONOROGO'),
('3502150008', 'KABUPATEN PONOROGO', 'SAMPUNG', 'POHIJO', 'POHIJO-SAMPUNG-KABUPATEN PONOROGO'),
('3502150009', 'KABUPATEN PONOROGO', 'SAMPUNG', 'JENANGAN', 'JENANGAN-SAMPUNG-KABUPATEN PONOROGO'),
('3502150010', 'KABUPATEN PONOROGO', 'SAMPUNG', 'NGLURUP', 'NGLURUP-SAMPUNG-KABUPATEN PONOROGO'),
('3502150011', 'KABUPATEN PONOROGO', 'SAMPUNG', 'SAMPUNG', 'SAMPUNG-SAMPUNG-KABUPATEN PONOROGO'),
('3502150012', 'KABUPATEN PONOROGO', 'SAMPUNG', 'RINGIN PUTIH', 'RINGIN PUTIH-SAMPUNG-KABUPATEN PONOROGO'),
('3502160001', 'KABUPATEN PONOROGO', 'SUKOREJO', 'MOROSARI', 'MOROSARI-SUKOREJO-KABUPATEN PONOROGO'),
('3502160002', 'KABUPATEN PONOROGO', 'SUKOREJO', 'SRAGI', 'SRAGI-SUKOREJO-KABUPATEN PONOROGO'),
('3502160003', 'KABUPATEN PONOROGO', 'SUKOREJO', 'KALIMALANG', 'KALIMALANG-SUKOREJO-KABUPATEN PONOROGO'),
('3502160004', 'KABUPATEN PONOROGO', 'SUKOREJO', 'KARANGLOLOR', 'KARANGLOLOR-SUKOREJO-KABUPATEN PONOROGO'),
('3502160005', 'KABUPATEN PONOROGO', 'SUKOREJO', 'GANDUKEPUH', 'GANDUKEPUH-SUKOREJO-KABUPATEN PONOROGO'),
('3502160006', 'KABUPATEN PONOROGO', 'SUKOREJO', 'NAMBANGREJO', 'NAMBANGREJO-SUKOREJO-KABUPATEN PONOROGO'),
('3502160007', 'KABUPATEN PONOROGO', 'SUKOREJO', 'LENGKONG', 'LENGKONG-SUKOREJO-KABUPATEN PONOROGO'),
('3502160008', 'KABUPATEN PONOROGO', 'SUKOREJO', 'GOLAN', 'GOLAN-SUKOREJO-KABUPATEN PONOROGO'),
('3502160009', 'KABUPATEN PONOROGO', 'SUKOREJO', 'BANGUNREJO', 'BANGUNREJO-SUKOREJO-KABUPATEN PONOROGO'),
('3502160010', 'KABUPATEN PONOROGO', 'SUKOREJO', 'SUKOREJO', 'SUKOREJO-SUKOREJO-KABUPATEN PONOROGO'),
('3502160011', 'KABUPATEN PONOROGO', 'SUKOREJO', 'NAMPAN', 'NAMPAN-SUKOREJO-KABUPATEN PONOROGO'),
('3502160012', 'KABUPATEN PONOROGO', 'SUKOREJO', 'KRANGGAN', 'KRANGGAN-SUKOREJO-KABUPATEN PONOROGO'),
('3502160013', 'KABUPATEN PONOROGO', 'SUKOREJO', 'GELANGLOR', 'GELANGLOR-SUKOREJO-KABUPATEN PONOROGO'),
('3502160014', 'KABUPATEN PONOROGO', 'SUKOREJO', 'SIDOREJO', 'SIDOREJO-SUKOREJO-KABUPATEN PONOROGO'),
('3502160015', 'KABUPATEN PONOROGO', 'SUKOREJO', 'GEGERAN', 'GEGERAN-SUKOREJO-KABUPATEN PONOROGO'),
('3502160016', 'KABUPATEN PONOROGO', 'SUKOREJO', 'PRAJEGAN', 'PRAJEGAN-SUKOREJO-KABUPATEN PONOROGO'),
('3502160017', 'KABUPATEN PONOROGO', 'SUKOREJO', 'SERANGAN', 'SERANGAN-SUKOREJO-KABUPATEN PONOROGO'),
('3502160018', 'KABUPATEN PONOROGO', 'SUKOREJO', 'KEDUNG BANTENG', 'KEDUNG BANTENG-SUKOREJO-KABUPATEN PONOROGO'),
('3502170001', 'KABUPATEN PONOROGO', 'PONOROGO', 'PAJU', 'PAJU-PONOROGO-KABUPATEN PONOROGO'),
('3502170002', 'KABUPATEN PONOROGO', 'PONOROGO', 'BROTONEGARAN', 'BROTONEGARAN-PONOROGO-KABUPATEN PONOROGO'),
('3502170003', 'KABUPATEN PONOROGO', 'PONOROGO', 'PAKUNDEN', 'PAKUNDEN-PONOROGO-KABUPATEN PONOROGO'),
('3502170004', 'KABUPATEN PONOROGO', 'PONOROGO', 'KEPATIHAN', 'KEPATIHAN-PONOROGO-KABUPATEN PONOROGO'),
('3502170005', 'KABUPATEN PONOROGO', 'PONOROGO', 'SURODIKRAMAN', 'SURODIKRAMAN-PONOROGO-KABUPATEN PONOROGO'),
('3502170006', 'KABUPATEN PONOROGO', 'PONOROGO', 'PURBOSUMAN', 'PURBOSUMAN-PONOROGO-KABUPATEN PONOROGO'),
('3502170007', 'KABUPATEN PONOROGO', 'PONOROGO', 'TONATAN', 'TONATAN-PONOROGO-KABUPATEN PONOROGO'),
('3502170008', 'KABUPATEN PONOROGO', 'PONOROGO', 'BANGUNSARI', 'BANGUNSARI-PONOROGO-KABUPATEN PONOROGO'),
('3502170009', 'KABUPATEN PONOROGO', 'PONOROGO', 'TAMAN ARUM', 'TAMAN ARUM-PONOROGO-KABUPATEN PONOROGO'),
('3502170010', 'KABUPATEN PONOROGO', 'PONOROGO', 'KAUMAN', 'KAUMAN-PONOROGO-KABUPATEN PONOROGO'),
('3502170011', 'KABUPATEN PONOROGO', 'PONOROGO', 'TAMBAKBAYAN', 'TAMBAKBAYAN-PONOROGO-KABUPATEN PONOROGO'),
('3502170012', 'KABUPATEN PONOROGO', 'PONOROGO', 'PINGGIRSARI', 'PINGGIRSARI-PONOROGO-KABUPATEN PONOROGO'),
('3502170013', 'KABUPATEN PONOROGO', 'PONOROGO', 'MANGKUJAYAN', 'MANGKUJAYAN-PONOROGO-KABUPATEN PONOROGO'),
('3502170014', 'KABUPATEN PONOROGO', 'PONOROGO', 'BANYUDONO', 'BANYUDONO-PONOROGO-KABUPATEN PONOROGO'),
('3502170015', 'KABUPATEN PONOROGO', 'PONOROGO', 'NOLOGATEN', 'NOLOGATEN-PONOROGO-KABUPATEN PONOROGO'),
('3502170016', 'KABUPATEN PONOROGO', 'PONOROGO', 'COKROMENGGALAN', 'COKROMENGGALAN-PONOROGO-KABUPATEN PONOROGO'),
('3502170017', 'KABUPATEN PONOROGO', 'PONOROGO', 'KENITEN', 'KENITEN-PONOROGO-KABUPATEN PONOROGO'),
('3502170018', 'KABUPATEN PONOROGO', 'PONOROGO', 'JINGGLONG', 'JINGGLONG-PONOROGO-KABUPATEN PONOROGO'),
('3502170019', 'KABUPATEN PONOROGO', 'PONOROGO', 'BEDURI', 'BEDURI-PONOROGO-KABUPATEN PONOROGO'),
('3502180001', 'KABUPATEN PONOROGO', 'BABADAN', 'KERTOSARI', 'KERTOSARI-BABADAN-KABUPATEN PONOROGO'),
('3502180002', 'KABUPATEN PONOROGO', 'BABADAN', 'CEKOK', 'CEKOK-BABADAN-KABUPATEN PONOROGO'),
('3502180003', 'KABUPATEN PONOROGO', 'BABADAN', 'PATIHAN WETAN', 'PATIHAN WETAN-BABADAN-KABUPATEN PONOROGO'),
('3502180004', 'KABUPATEN PONOROGO', 'BABADAN', 'KADIPATEN', 'KADIPATEN-BABADAN-KABUPATEN PONOROGO'),
('3502180005', 'KABUPATEN PONOROGO', 'BABADAN', 'JAPAN', 'JAPAN-BABADAN-KABUPATEN PONOROGO'),
('3502180006', 'KABUPATEN PONOROGO', 'BABADAN', 'GUPOLO', 'GUPOLO-BABADAN-KABUPATEN PONOROGO'),
('3502180007', 'KABUPATEN PONOROGO', 'BABADAN', 'POLOREJO', 'POLOREJO-BABADAN-KABUPATEN PONOROGO'),
('3502180008', 'KABUPATEN PONOROGO', 'BABADAN', 'BARENG', 'BARENG-BABADAN-KABUPATEN PONOROGO'),
('3502180009', 'KABUPATEN PONOROGO', 'BABADAN', 'NGUNUT', 'NGUNUT-BABADAN-KABUPATEN PONOROGO'),
('3502180010', 'KABUPATEN PONOROGO', 'BABADAN', 'SUKOSARI', 'SUKOSARI-BABADAN-KABUPATEN PONOROGO'),
('3502180011', 'KABUPATEN PONOROGO', 'BABADAN', 'LEMBAH', 'LEMBAH-BABADAN-KABUPATEN PONOROGO'),
('3502180012', 'KABUPATEN PONOROGO', 'BABADAN', 'PONDOK', 'PONDOK-BABADAN-KABUPATEN PONOROGO'),
('3502180013', 'KABUPATEN PONOROGO', 'BABADAN', 'BABADAN', 'BABADAN-BABADAN-KABUPATEN PONOROGO'),
('3502180014', 'KABUPATEN PONOROGO', 'BABADAN', 'PURWOSARI', 'PURWOSARI-BABADAN-KABUPATEN PONOROGO'),
('3502180015', 'KABUPATEN PONOROGO', 'BABADAN', 'TRISONO', 'TRISONO-BABADAN-KABUPATEN PONOROGO'),
('3502190001', 'KABUPATEN PONOROGO', 'JENANGAN', 'MRICAN', 'MRICAN-JENANGAN-KABUPATEN PONOROGO'),
('3502190002', 'KABUPATEN PONOROGO', 'JENANGAN', 'SINGOSAREN', 'SINGOSAREN-JENANGAN-KABUPATEN PONOROGO'),
('3502190003', 'KABUPATEN PONOROGO', 'JENANGAN', 'SETONO', 'SETONO-JENANGAN-KABUPATEN PONOROGO'),
('3502190004', 'KABUPATEN PONOROGO', 'JENANGAN', 'PLALANGAN', 'PLALANGAN-JENANGAN-KABUPATEN PONOROGO'),
('3502190005', 'KABUPATEN PONOROGO', 'JENANGAN', 'NGRUPIT', 'NGRUPIT-JENANGAN-KABUPATEN PONOROGO'),
('3502190006', 'KABUPATEN PONOROGO', 'JENANGAN', 'SEDAH', 'SEDAH-JENANGAN-KABUPATEN PONOROGO'),
('3502190007', 'KABUPATEN PONOROGO', 'JENANGAN', 'PINTU', 'PINTU-JENANGAN-KABUPATEN PONOROGO'),
('3502190008', 'KABUPATEN PONOROGO', 'JENANGAN', 'PANJENG', 'PANJENG-JENANGAN-KABUPATEN PONOROGO'),
('3502190009', 'KABUPATEN PONOROGO', 'JENANGAN', 'JIMBE', 'JIMBE-JENANGAN-KABUPATEN PONOROGO'),
('3502190010', 'KABUPATEN PONOROGO', 'JENANGAN', 'JENANGAN', 'JENANGAN-JENANGAN-KABUPATEN PONOROGO'),
('3502190011', 'KABUPATEN PONOROGO', 'JENANGAN', 'SRATEN', 'SRATEN-JENANGAN-KABUPATEN PONOROGO'),
('3502190012', 'KABUPATEN PONOROGO', 'JENANGAN', 'KEMIRI', 'KEMIRI-JENANGAN-KABUPATEN PONOROGO'),
('3502190013', 'KABUPATEN PONOROGO', 'JENANGAN', 'SEMANDING', 'SEMANDING-JENANGAN-KABUPATEN PONOROGO'),
('3502190014', 'KABUPATEN PONOROGO', 'JENANGAN', 'TANJUNG SARI', 'TANJUNG SARI-JENANGAN-KABUPATEN PONOROGO'),
('3502190015', 'KABUPATEN PONOROGO', 'JENANGAN', 'NGLAYANG', 'NGLAYANG-JENANGAN-KABUPATEN PONOROGO'),
('3502190016', 'KABUPATEN PONOROGO', 'JENANGAN', 'PARINGAN', 'PARINGAN-JENANGAN-KABUPATEN PONOROGO'),
('3502190017', 'KABUPATEN PONOROGO', 'JENANGAN', 'WATES', 'WATES-JENANGAN-KABUPATEN PONOROGO'),
('3502200001', 'KABUPATEN PONOROGO', 'NGEBEL', 'NGROGUNG', 'NGROGUNG-NGEBEL-KABUPATEN PONOROGO'),
('3502200002', 'KABUPATEN PONOROGO', 'NGEBEL', 'SAHANG', 'SAHANG-NGEBEL-KABUPATEN PONOROGO'),
('3502200003', 'KABUPATEN PONOROGO', 'NGEBEL', 'WAGIRLOR', 'WAGIRLOR-NGEBEL-KABUPATEN PONOROGO'),
('3502200004', 'KABUPATEN PONOROGO', 'NGEBEL', 'TALUN', 'TALUN-NGEBEL-KABUPATEN PONOROGO'),
('3502200005', 'KABUPATEN PONOROGO', 'NGEBEL', 'GONDOWIDO', 'GONDOWIDO-NGEBEL-KABUPATEN PONOROGO'),
('3502200006', 'KABUPATEN PONOROGO', 'NGEBEL', 'PUPUS', 'PUPUS-NGEBEL-KABUPATEN PONOROGO'),
('3502200007', 'KABUPATEN PONOROGO', 'NGEBEL', 'NGEBEL', 'NGEBEL-NGEBEL-KABUPATEN PONOROGO'),
('3502200008', 'KABUPATEN PONOROGO', 'NGEBEL', 'SEMPU', 'SEMPU-NGEBEL-KABUPATEN PONOROGO');

-- --------------------------------------------------------

--
-- Table structure for table `tb_databot`
--

CREATE TABLE `tb_databot` (
  `id` varchar(13) NOT NULL,
  `quetions` varchar(150) NOT NULL,
  `replies` varchar(550) NOT NULL,
  `type` varchar(30) NOT NULL,
  `list` varchar(200) NOT NULL,
  `text` varchar(550) NOT NULL,
  `footer` varchar(100) NOT NULL,
  `title` varchar(200) NOT NULL,
  `button` varchar(100) NOT NULL,
  `lat` varchar(50) NOT NULL,
  `long` varchar(50) NOT NULL,
  `namakontak` varchar(50) NOT NULL,
  `perusahaankontak` varchar(50) NOT NULL,
  `nomorkontak` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tb_history_process_finish_installation`
--

CREATE TABLE `tb_history_process_finish_installation` (
  `IDUNIQ` varchar(12) NOT NULL,
  `IDREQUEST` varchar(11) NOT NULL,
  `NOTE` text NOT NULL,
  `INSERTED_AT` timestamp NOT NULL DEFAULT current_timestamp(),
  `CODENOTIF` varchar(50) NOT NULL,
  `STATUSNOTIF` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tb_history_process_not_active`
--

CREATE TABLE `tb_history_process_not_active` (
  `IDUNIQ` varchar(12) NOT NULL,
  `IDREQUEST` varchar(11) NOT NULL,
  `NOTE` text NOT NULL,
  `INSERTED_AT` timestamp NOT NULL DEFAULT current_timestamp(),
  `CODENOTIF` varchar(50) NOT NULL,
  `STATUSNOTIF` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tb_history_process_start_installation`
--

CREATE TABLE `tb_history_process_start_installation` (
  `IDUNIQ` varchar(12) NOT NULL,
  `IDREQUEST` varchar(11) NOT NULL,
  `NOTE` text NOT NULL,
  `INSERTED_AT` timestamp NOT NULL DEFAULT current_timestamp(),
  `CODENOTIF` varchar(50) NOT NULL,
  `STATUSNOTIF` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `tb_history_process_start_installation`
--

INSERT INTO `tb_history_process_start_installation` (`IDUNIQ`, `IDREQUEST`, `NOTE`, `INSERTED_AT`, `CODENOTIF`, `STATUSNOTIF`) VALUES
('his683697017', 'RQ000006', 'PROSES PEMASANGAN', '2025-05-28 04:54:25', '14', 1),
('his688d6108c', 'RQ000013', 'PROSES PEMASANGAN', '2025-08-02 00:51:20', '14', 1),
('his689bfc338', 'RQ000016', 'PROSES PEMASANGAN', '2025-08-13 02:45:07', '14', 1),
('his689bfc486', 'RQ000014', 'PROSES PEMASANGAN', '2025-08-13 02:45:28', '14', 1),
('his689e95e98', 'RQ000017', 'PROSES PEMASANGAN', '2025-08-15 02:05:29', '14', 1),
('his689eceac8', 'RQ000015', 'PROSES PEMASANGAN', '2025-08-15 06:07:40', '14', 1),
('his68a7f24e6', 'RQ000018', 'PROSES PEMASANGAN', '2025-08-22 04:30:06', '14', 1),
('his68abe592f', 'RQ000019', 'PROSES PEMASANGAN', '2025-08-25 04:24:50', '14', 1),
('his68be50adc', 'RQ000024', 'PROSES PEMASANGAN', '2025-09-08 03:42:37', '14', 1),
('his68be7894c', 'RQ000021', 'PROSES PEMASANGAN', '2025-09-08 06:32:52', '14', 1),
('his68c91c85b', 'RQ000027', 'PROSES PEMASANGAN', '2025-09-16 08:15:01', '14', 1),
('his68d148963', 'RQ000028', 'PROSES PEMASANGAN', '2025-09-22 13:01:10', '14', 1),
('his68d7b1cf7', 'RQ000029', 'PROSES PEMASANGAN', '2025-09-27 09:43:43', '14', 1),
('his68e8e2c84', 'RQ000034', 'PROSES PEMASANGAN', '2025-10-10 10:41:12', '14', 1),
('his68ee0c395', 'RQ000033', 'PROSES PEMASANGAN', '2025-10-14 08:39:21', '14', 1),
('his68f8d9a3d', 'RQ000035', 'PROSES PEMASANGAN', '2025-10-22 13:18:27', '14', 1),
('his6904b9c49', 'RQ000038', 'PROSES PEMASANGAN', '2025-10-31 13:29:40', '14', 1),
('his6904bb156', 'RQ000039', 'PROSES PEMASANGAN', '2025-10-31 13:35:17', '14', 1),
('his6904bc74b', 'RQ000036', 'PROSES PEMASANGAN', '2025-10-31 13:41:08', '14', 1),
('his6910baaf2', 'RQ000040', 'PROSES PEMASANGAN', '2025-11-09 16:00:47', '14', 1),
('his695cc00fe', 'RQ000012', 'PROSES PEMASANGAN', '2026-01-06 07:55:59', '14', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tb_history_process_survey`
--

CREATE TABLE `tb_history_process_survey` (
  `IDUNIQ` varchar(12) NOT NULL,
  `IDREQUEST` varchar(11) NOT NULL,
  `NOTE` text NOT NULL,
  `INSERTED_AT` timestamp NOT NULL DEFAULT current_timestamp(),
  `CODENOTIF` varchar(50) NOT NULL,
  `STATUSNOTIF` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `tb_history_process_survey`
--

INSERT INTO `tb_history_process_survey` (`IDUNIQ`, `IDREQUEST`, `NOTE`, `INSERTED_AT`, `CODENOTIF`, `STATUSNOTIF`) VALUES
('his68197effa', 'RQ000006', 'PROSES SURVEY', '2025-05-06 03:16:15', '14', 1),
('his683447946', 'RQ000009', 'PROSES SURVEY', '2025-05-26 10:51:00', '14', 1),
('his685ba05a8', 'RQ000011', 'PROSES SURVEY', '2025-06-25 07:08:10', '14', 1),
('his686b96da4', 'RQ000012', 'PROSES SURVEY', '2025-07-07 09:43:54', '14', 1),
('his688722d99', 'RQ000013', 'PROSES SURVEY', '2025-07-28 07:12:25', '14', 1),
('his688dcff4d', 'RQ000014', 'PROSES SURVEY', '2025-08-02 08:44:36', '14', 1),
('his6895b2f64', 'RQ000016', 'PROSES SURVEY', '2025-08-08 08:19:02', '14', 1),
('his689c07ef2', 'RQ000017', 'PROSES SURVEY', '2025-08-13 03:35:11', '14', 1),
('his689ebe532', 'RQ000015', 'PROSES SURVEY', '2025-08-15 04:57:55', '14', 1),
('his68a7e95f1', 'RQ000018', 'PROSES SURVEY', '2025-08-22 03:51:59', '14', 1),
('his68abcf0d4', 'RQ000019', 'PROSES SURVEY', '2025-08-25 02:48:45', '14', 1),
('his68afb649a', 'RQ000020', 'PROSES SURVEY', '2025-08-28 01:52:09', '14', 1),
('his68b4efbb0', 'RQ000021', 'PROSES SURVEY', '2025-09-01 00:58:35', '14', 1),
('his68be34bd3', 'RQ000024', 'PROSES SURVEY', '2025-09-08 01:43:25', '14', 1),
('his68c145fdb', 'RQ000025', 'PROSES SURVEY', '2025-09-10 09:33:49', '14', 1),
('his68c5111ec', 'RQ000026', 'PROSES SURVEY', '2025-09-13 06:37:18', '14', 1),
('his68c7cb580', 'RQ000027', 'PROSES SURVEY', '2025-09-15 08:16:24', '14', 1),
('his68c7d3e08', 'RQ000028', 'PROSES SURVEY', '2025-09-15 08:52:48', '14', 1),
('his68d50117e', 'RQ000029', 'PROSES SURVEY', '2025-09-25 08:45:11', '14', 1),
('his68da4bf2a', 'RQ000031', 'PROSES SURVEY', '2025-09-29 09:05:54', '14', 1),
('his68e0c7aec', 'RQ000033', 'PROSES SURVEY', '2025-10-04 07:07:26', '14', 1),
('his68e768b69', 'RQ000034', 'PROSES SURVEY', '2025-10-09 07:48:06', '14', 1),
('his68ef54400', 'RQ000035', 'PROSES SURVEY', '2025-10-15 07:58:56', '14', 1),
('his68f9d52d7', 'RQ000036', 'PROSES SURVEY', '2025-10-23 07:11:41', '14', 1),
('his68ff30bc4', 'RQ000039', 'PROSES SURVEY', '2025-10-27 08:43:40', '14', 1),
('his68ff41fe7', 'RQ000038', 'PROSES SURVEY', '2025-10-27 09:57:18', '14', 1),
('his69046d6c1', 'RQ000040', 'PROSES SURVEY', '2025-10-31 08:03:56', '14', 1),
('his692d49ab7', 'RQ000042', 'PROSES SURVEY', '2025-12-01 07:54:19', '14', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tb_history_process_team_installation`
--

CREATE TABLE `tb_history_process_team_installation` (
  `IDUNIQ` varchar(12) NOT NULL,
  `IDREQUEST` varchar(11) NOT NULL,
  `NOTE` text NOT NULL,
  `INSERTED_AT` timestamp NOT NULL DEFAULT current_timestamp(),
  `CODENOTIF` varchar(50) NOT NULL,
  `STATUSNOTIF` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tb_history_process_verification`
--

CREATE TABLE `tb_history_process_verification` (
  `IDUNIQ` varchar(12) NOT NULL,
  `IDREQUEST` varchar(11) NOT NULL,
  `NOTE` text NOT NULL,
  `INSERTED_AT` timestamp NOT NULL DEFAULT current_timestamp(),
  `CODENOTIF` varchar(50) NOT NULL,
  `STATUSNOTIF` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tb_history_registrations`
--

CREATE TABLE `tb_history_registrations` (
  `IDUNIQ` varchar(12) NOT NULL,
  `IDREQUEST` varchar(11) NOT NULL,
  `NOTE` varchar(255) NOT NULL,
  `INSERTED_AT` timestamp NOT NULL DEFAULT current_timestamp(),
  `CODENOTIF` varchar(50) NOT NULL,
  `STATUSNOTIF` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `tb_history_registrations`
--

INSERT INTO `tb_history_registrations` (`IDUNIQ`, `IDREQUEST`, `NOTE`, `INSERTED_AT`, `CODENOTIF`, `STATUSNOTIF`) VALUES
('his67dac915c', 'RQ000001', 'PENGAJUAN PELANGGAN', '2025-03-19 13:39:33', '13', 1),
('his680749f38', 'RQ000002', 'PENGAJUAN PELANGGAN', '2025-04-22 07:49:07', '13', 0),
('his68074d562', 'RQ000003', 'PENGAJUAN PELANGGAN', '2025-04-22 08:03:34', '13', 0),
('his6809b4688', 'RQ000004', 'PENGAJUAN PELANGGAN', '2025-04-24 03:47:52', '13', 1),
('his6809b5846', 'RQ000005', 'PENGAJUAN PELANGGAN', '2025-04-24 03:52:36', '13', 1),
('his6818b474a', 'RQ000006', 'PENGAJUAN PELANGGAN', '2025-05-05 12:52:04', '13', 1),
('his6818b4d3a', 'RQ000007', 'PENGAJUAN PELANGGAN', '2025-05-05 12:53:39', '13', 1),
('his682add00b', 'RQ000009', 'PENGAJUAN PELANGGAN', '2025-05-19 07:25:52', '13', 1),
('his684fc4856', 'RQ000010', 'PENGAJUAN PELANGGAN', '2025-06-16 07:15:17', '13', 1),
('his685b9d5e0', 'RQ000011', 'PENGAJUAN PELANGGAN', '2025-06-25 06:55:26', '13', 1),
('his68639e487', 'RQ000012', 'PENGAJUAN PELANGGAN', '2025-07-01 08:37:28', '13', 1),
('his68872141c', 'RQ000013', 'PENGAJUAN PELANGGAN', '2025-07-28 07:05:37', '13', 1),
('his688c7aceb', 'RQ000014', 'PENGAJUAN PELANGGAN', '2025-08-01 08:29:02', '13', 1),
('his688dd244b', 'RQ000015', 'PENGAJUAN PELANGGAN', '2025-08-02 08:54:28', '13', 1),
('his68931924e', 'RQ000016', 'PENGAJUAN PELANGGAN', '2025-08-06 08:58:12', '13', 1),
('his689b03552', 'RQ000017', 'PENGAJUAN PELANGGAN', '2025-08-12 09:03:17', '13', 1),
('his68a7d4ae9', 'RQ000018', 'PENGAJUAN PELANGGAN', '2025-08-22 02:23:42', '13', 1),
('his68a9b2872', 'RQ000019', 'PENGAJUAN PELANGGAN', '2025-08-23 12:22:31', '13', 1),
('his68aeb3596', 'RQ000020', 'PENGAJUAN PELANGGAN', '2025-08-27 07:27:21', '13', 1),
('his68b2a2f50', 'RQ000021', 'PENGAJUAN PELANGGAN', '2025-08-30 07:06:29', '13', 1),
('his68ba8a84c', 'RQ000024', 'PENGAJUAN PELANGGAN', '2025-09-05 07:00:20', '13', 1),
('his68c0d07a6', 'RQ000025', 'PENGAJUAN PELANGGAN', '2025-09-10 01:12:26', '13', 1),
('his68c4f8785', 'RQ000026', 'PENGAJUAN PELANGGAN', '2025-09-13 04:52:08', '13', 1),
('his68c51cede', 'RQ000027', 'PENGAJUAN PELANGGAN', '2025-09-13 07:27:41', '13', 1),
('his68c7c5d5a', 'RQ000028', 'PENGAJUAN PELANGGAN', '2025-09-15 07:52:53', '13', 1),
('his68d0efc2e', 'RQ000029', 'PENGAJUAN PELANGGAN', '2025-09-22 06:42:10', '13', 1),
('his68d4b61a0', 'RQ000030', 'PENGAJUAN PELANGGAN', '2025-09-25 03:25:14', '13', 1),
('his68d9dea3e', 'RQ000031', 'PENGAJUAN PELANGGAN', '2025-09-29 01:19:31', '13', 1),
('his68da3c63b', 'RQ000032', 'PENGAJUAN PELANGGAN', '2025-09-29 07:59:31', '13', 1),
('his68de46580', 'RQ000033', 'PENGAJUAN PELANGGAN', '2025-10-02 09:31:04', '13', 1),
('his68e75d7e7', 'RQ000034', 'PENGAJUAN PELANGGAN', '2025-10-09 07:00:14', '13', 1),
('his68ef255c4', 'RQ000035', 'PENGAJUAN PELANGGAN', '2025-10-15 04:38:52', '13', 1),
('his68f33d81b', 'RQ000036', 'PENGAJUAN PELANGGAN', '2025-10-18 07:10:57', '13', 1),
('his68fc3d4f8', 'RQ000037', 'PENGAJUAN PELANGGAN', '2025-10-25 03:00:31', '13', 1),
('his68fefa695', 'RQ000038', 'PENGAJUAN PELANGGAN', '2025-10-27 04:51:53', '13', 1),
('his68ff1232b', 'RQ000039', 'PENGAJUAN PELANGGAN', '2025-10-27 06:33:22', '13', 1),
('his690049488', 'RQ000040', 'PENGAJUAN PELANGGAN', '2025-10-28 04:40:40', '13', 1),
('his691c0cf17', 'RQ000041', 'PENGAJUAN PELANGGAN', '2025-11-18 06:06:41', '13', 1),
('his692d166b1', 'RQ000042', 'PENGAJUAN PELANGGAN', '2025-12-01 04:15:39', '13', 1),
('his6989478ee', 'RQ000044', 'PENGAJUAN PELANGGAN', '2026-02-09 02:33:50', '13', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tb_history_report_survey`
--

CREATE TABLE `tb_history_report_survey` (
  `IDUNIQ` varchar(12) NOT NULL,
  `IDREQUEST` varchar(11) NOT NULL,
  `NOTE` text NOT NULL,
  `INSERTED_AT` timestamp NOT NULL DEFAULT current_timestamp(),
  `CODENOTIF` varchar(50) NOT NULL,
  `STATUSNOTIF` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `tb_history_report_survey`
--

INSERT INTO `tb_history_report_survey` (`IDUNIQ`, `IDREQUEST`, `NOTE`, `INSERTED_AT`, `CODENOTIF`, `STATUSNOTIF`) VALUES
('his6819a1b4b', 'RQ000006', 'REPORT SURVEY', '2025-05-06 05:44:20', '15', 1),
('his683462d46', 'RQ000009', 'REPORT SURVEY', '2025-05-26 12:47:16', '15', 1),
('his685ba07e2', 'RQ000011', 'REPORT SURVEY', '2025-06-25 07:08:46', '15', 1),
('his686b97612', 'RQ000012', 'REPORT SURVEY', '2025-07-07 09:46:09', '15', 1),
('his688723149', 'RQ000013', 'REPORT SURVEY', '2025-07-28 07:13:24', '15', 1),
('his688dd7033', 'RQ000014', 'REPORT SURVEY', '2025-08-02 09:14:43', '15', 1),
('his6895b6a81', 'RQ000016', 'REPORT SURVEY', '2025-08-08 08:34:48', '15', 1),
('his689c082a0', 'RQ000017', 'REPORT SURVEY', '2025-08-13 03:36:10', '15', 1),
('his689ec8185', 'RQ000015', 'REPORT SURVEY', '2025-08-15 05:39:36', '15', 1),
('his68a7edbbc', 'RQ000018', 'REPORT SURVEY', '2025-08-22 04:10:35', '15', 1),
('his68abd2099', 'RQ000019', 'REPORT SURVEY', '2025-08-25 03:01:29', '15', 1),
('his68afb6ace', 'RQ000020', 'REPORT SURVEY', '2025-08-28 01:53:48', '15', 1),
('his68b4fc799', 'RQ000021', 'REPORT SURVEY', '2025-09-01 01:52:57', '15', 1),
('his68be34ec3', 'RQ000024', 'REPORT SURVEY', '2025-09-08 01:44:12', '15', 1),
('his68c14733d', 'RQ000025', 'REPORT SURVEY', '2025-09-10 09:38:59', '15', 1),
('his68c511681', 'RQ000026', 'REPORT SURVEY', '2025-09-13 06:38:32', '15', 1),
('his68c7cba90', 'RQ000027', 'REPORT SURVEY', '2025-09-15 08:17:45', '15', 1),
('his68c7d417c', 'RQ000028', 'REPORT SURVEY', '2025-09-15 08:53:43', '15', 1),
('his68d501470', 'RQ000029', 'REPORT SURVEY', '2025-09-25 08:45:59', '15', 1),
('his68da5401b', 'RQ000031', 'REPORT SURVEY', '2025-09-29 09:40:17', '15', 1),
('his68e0c875d', 'RQ000033', 'REPORT SURVEY', '2025-10-04 07:10:45', '15', 1),
('his68e768d52', 'RQ000034', 'REPORT SURVEY', '2025-10-09 07:48:37', '15', 1),
('his68ef54ba6', 'RQ000035', 'REPORT SURVEY', '2025-10-15 08:00:58', '15', 1),
('his68f9d5998', 'RQ000036', 'REPORT SURVEY', '2025-10-23 07:13:29', '15', 1),
('his68ff30ef7', 'RQ000039', 'REPORT SURVEY', '2025-10-27 08:44:31', '15', 1),
('his68ff42285', 'RQ000038', 'REPORT SURVEY', '2025-10-27 09:58:00', '15', 1),
('his69046d943', 'RQ000040', 'REPORT SURVEY', '2025-10-31 08:04:36', '15', 1),
('his692d4a1a2', 'RQ000042', 'REPORT SURVEY', '2025-12-01 07:56:10', '15', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tb_jenismember`
--

CREATE TABLE `tb_jenismember` (
  `idmonth` int(2) NOT NULL,
  `name` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `tb_jenismember`
--

INSERT INTO `tb_jenismember` (`idmonth`, `name`) VALUES
(0, 'biasa'),
(2, 'member'),
(4, 'member'),
(6, 'member'),
(8, 'member'),
(10, 'member'),
(12, 'member'),
(14, 'member'),
(16, 'member'),
(18, 'member'),
(20, 'member'),
(22, 'member'),
(24, 'member');

-- --------------------------------------------------------

--
-- Table structure for table `tb_log`
--

CREATE TABLE `tb_log` (
  `id` varchar(11) NOT NULL,
  `versi` varchar(6) NOT NULL,
  `jenis_log` varchar(30) NOT NULL,
  `log` varchar(200) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tb_menu`
--

CREATE TABLE `tb_menu` (
  `menuid` int(11) NOT NULL,
  `menuparentid` int(11) NOT NULL,
  `submenuparentid` int(11) NOT NULL,
  `menuname` varchar(30) NOT NULL,
  `menuicon` varchar(30) NOT NULL,
  `menulink` varchar(40) NOT NULL,
  `menucode` varchar(30) NOT NULL,
  `menuavail` varchar(20) NOT NULL,
  `menualias` varchar(30) NOT NULL,
  `menusort` int(3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `tb_menu`
--

INSERT INTO `tb_menu` (`menuid`, `menuparentid`, `submenuparentid`, `menuname`, `menuicon`, `menulink`, `menucode`, `menuavail`, `menualias`, `menusort`) VALUES
(2, 0, 0, 'SETTING DATA', 'bx bx-wrench', '#', '', '', '', 4),
(4, 2, 0, 'Alat / Perangkat', '', 'master/Controll_barang', '', '', 'data-barang', 3),
(5, 2, 0, 'Master Kategori Distribusi', '', 'master/Controll_distribusi', '', '', 'data-kategori-distribusi', 4),
(6, 2, 0, 'Master Kategori OLT', '', 'master/Controll_kategoriOlt', '', '', 'data-kategori-olt', 5),
(7, 0, 0, 'SETTING APLIKASI', 'bx bx-folder', '#', '', '', '', 2),
(10, 0, 0, 'PELANGGAN', 'bx bxs-edit-alt', '#', '', '', '', 1),
(11, 10, 0, 'Registrasi pelanggan', '', 'pelanggan/Controll_registrasiPelanggan', '', '', 'data-registrasi-pelanggan', 1),
(12, 10, 0, 'Survei', '', 'pelanggan/Controll_antrianSurvei', '', '', 'data-antrian-survei', 2),
(13, 10, 0, 'Verifikasi Admin & Teknisi', '', 'pelanggan/Controll_antrianProses', '', '', 'data-antrian-proses', 3),
(14, 10, 0, 'List Pelanggan', '', 'pelanggan/Controll_data_pelanggan', '', '', 'data-pelanggan', 4),
(15, 2, 0, 'Paket', '', 'master/Controll_Paket', '', '', 'data-paket', 1),
(16, 2, 0, 'Pengguna', '', 'setting/Controll_pengguna', '', '', 'data-pengguna-aplikasi', 4),
(17, 0, 0, 'RIWAYAT', 'bx bx-history', '#', '', '', '', 3),
(18, 17, 0, 'Alat', '', 'riwayat/Controll_riwayatAlat', '', '', 'data-riwayat-alat', 1),
(19, 17, 0, 'Riwayat Pelanggan', '', 'riwayat/Controll_riwayatPelanggan', '', '', 'data-riwayat-pelanggan', 2),
(20, 2, 0, 'Master Jenis Paket', '', 'master/Controll_jenKaPaket', '', '', 'data-jenis-paket', 3),
(21, 7, 0, 'Akses Jabatan', '', 'settings/Controll_jabatan', '', '', 'data-setup-jabatan', 6),
(22, 10, 0, 'List Gagal', '', 'pelanggan/Controll_listGagalPasang', '', '', 'data-list-gagal-pasang', 5),
(23, 17, 0, 'Riwayat Transaksi', '', 'riwayat/Controll_riwayatTransaksi', '', '', 'data-riwayat-transaksi', 3),
(24, 10, 0, 'List Putus Langganan', '', 'pelanggan/Controll_listPutusPasang', '', '', 'data-list-putus-langganan', 6),
(25, 0, 0, 'TOOLS', 'bx bxs-magic-wand', '#', '', '', '', 5),
(26, 25, 0, 'IP Kosong', '', 'tools/Controll_ipkosong', '', '', 'data-ipkosong', 3),
(27, 25, 0, 'OLT', '', 'tools/Controll_olt', '', '', 'data-olt', 1),
(28, 25, 0, 'Aktivasi Pelanggan', '', 'tools/Controll_aktivasiPelanggan', '', '', 'data-aktivasi-pelanggan', 2),
(29, 7, 0, 'Setting Aplikasi', '', 'settings/Controll_snmpProtocol', '', '', 'form-snmp-protocol', 1),
(31, 2, 0, 'Import', '', 'settings/Controll_import', '', '', 'data-import', 8),
(34, 2, 0, 'Reset', '', 'settings/Controll_Reset', '', '', 'data-reset', 9),
(35, 2, 0, 'Backup and Restore', '', 'settings/Controll_Backres', '', '', 'data-backup-and-restore', 10),
(37, 0, 0, 'GANGGUAN', 'bx bxs-magic-wand', '#', '', '', '', 6),
(38, 37, 0, 'Tambah Tiket', '', 'gangguan/Controll_tambahtiket', '', '', 'Data-tambah-tiket', 1),
(39, 37, 0, 'Tiket Masuk', '', 'gangguan/Controll_tiketmasuk', '', '', 'data-tiket-masuk', 2),
(40, 37, 0, 'Tiket Proses', '', 'gangguan/Controll_tiketproses', '', '', 'data-tiket-proses', 3),
(41, 37, 0, 'Riwayat Selesai', '', 'gangguan/Controll_riwayat_tiketselesai', '', '', 'data-riwayat-tiket-selesai', 4),
(42, 37, 0, 'Riwayat Gagal', '', 'gangguan/Controll_riwayat_tiketgagal', '', '', 'data-riwayat-tiket-gagal', 5),
(43, 2, 0, 'Master Kota', '', 'master/Controll_wilayah', '', '', 'data-wilayah', 6);

-- --------------------------------------------------------

--
-- Table structure for table `tb_menujabatan`
--

CREATE TABLE `tb_menujabatan` (
  `JABATANID` int(11) NOT NULL,
  `menuid` int(11) NOT NULL,
  `status` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `tb_menujabatan`
--

INSERT INTO `tb_menujabatan` (`JABATANID`, `menuid`, `status`) VALUES
(7, 7, 'YES'),
(7, 10, 'YES'),
(2, 10, 'YES'),
(2, 14, 'YES'),
(2, 13, 'YES'),
(2, 12, 'YES'),
(2, 11, 'YES'),
(2, 24, 'YES'),
(2, 22, 'YES'),
(2, 17, 'YES'),
(2, 18, 'YES'),
(2, 23, 'YES'),
(2, 19, 'YES'),
(2, 2, 'YES'),
(2, 16, 'YES'),
(2, 15, 'YES'),
(2, 30, 'YES'),
(2, 6, 'YES'),
(2, 5, 'YES'),
(2, 4, 'YES'),
(2, 3, 'YES'),
(2, 21, 'NO'),
(2, 25, 'YES'),
(2, 28, 'YES'),
(2, 27, 'YES'),
(2, 26, 'YES'),
(2, 7, 'YES'),
(2, 29, 'YES'),
(2, 9, 'YES'),
(2, 8, 'YES'),
(2, 20, 'YES'),
(6, 10, 'YES'),
(6, 14, 'NO'),
(6, 13, 'NO'),
(6, 12, 'YES'),
(6, 11, 'NO'),
(6, 24, 'NO'),
(6, 22, 'NO'),
(6, 17, 'NO'),
(6, 23, 'NO'),
(6, 19, 'NO'),
(6, 18, 'NO'),
(6, 2, 'NO'),
(6, 16, 'NO'),
(6, 15, 'NO'),
(6, 31, 'NO'),
(6, 30, 'NO'),
(6, 6, 'NO'),
(6, 5, 'NO'),
(6, 4, 'NO'),
(6, 3, 'NO'),
(6, 21, 'NO'),
(6, 25, 'NO'),
(6, 32, 'NO'),
(6, 28, 'NO'),
(6, 27, 'NO'),
(6, 26, 'NO'),
(6, 7, 'NO'),
(6, 33, 'NO'),
(6, 29, 'NO'),
(6, 9, 'NO'),
(6, 8, 'NO'),
(6, 20, 'NO'),
(0, 10, 'YES'),
(0, 24, 'YES'),
(0, 22, 'NO'),
(0, 14, 'NO'),
(0, 13, 'NO'),
(0, 12, 'NO'),
(0, 11, 'NO'),
(0, 17, 'NO'),
(0, 23, 'NO'),
(0, 19, 'NO'),
(0, 18, 'NO'),
(0, 2, 'NO'),
(0, 4, 'NO'),
(0, 21, 'NO'),
(0, 16, 'NO'),
(0, 15, 'NO'),
(0, 35, 'NO'),
(0, 34, 'NO'),
(0, 31, 'NO'),
(0, 25, 'NO'),
(0, 28, 'NO'),
(0, 27, 'NO'),
(0, 26, 'NO'),
(0, 7, 'NO'),
(0, 9, 'NO'),
(0, 20, 'NO'),
(0, 36, 'NO'),
(0, 29, 'NO'),
(1, 10, 'YES'),
(1, 22, 'YES'),
(1, 14, 'YES'),
(1, 13, 'YES'),
(1, 12, 'YES'),
(1, 11, 'YES'),
(1, 24, 'YES'),
(1, 17, 'YES'),
(1, 19, 'YES'),
(1, 18, 'YES'),
(1, 23, 'YES'),
(1, 2, 'YES'),
(1, 43, 'YES'),
(1, 20, 'YES'),
(1, 16, 'YES'),
(1, 15, 'YES'),
(1, 35, 'YES'),
(1, 34, 'YES'),
(1, 31, 'YES'),
(1, 6, 'YES'),
(1, 5, 'YES'),
(1, 4, 'YES'),
(1, 25, 'YES'),
(1, 28, 'YES'),
(1, 27, 'YES'),
(1, 26, 'YES'),
(1, 37, 'YES'),
(1, 42, 'YES'),
(1, 41, 'YES'),
(1, 40, 'YES'),
(1, 39, 'YES'),
(1, 38, 'YES'),
(1, 7, 'YES'),
(1, 21, 'YES'),
(1, 29, 'YES'),
(5, 10, 'YES'),
(5, 24, 'NO'),
(5, 22, 'NO'),
(5, 14, 'NO'),
(5, 13, 'YES'),
(5, 12, 'YES'),
(5, 11, 'YES'),
(5, 17, 'NO'),
(5, 23, 'NO'),
(5, 19, 'NO'),
(5, 18, 'NO'),
(5, 2, 'NO'),
(5, 4, 'NO'),
(5, 43, 'YES'),
(5, 20, 'YES'),
(5, 16, 'YES'),
(5, 15, 'YES'),
(5, 35, 'YES'),
(5, 34, 'YES'),
(5, 31, 'YES'),
(5, 6, 'YES'),
(5, 5, 'YES'),
(5, 25, 'YES'),
(5, 26, 'YES'),
(5, 28, 'YES'),
(5, 27, 'YES'),
(5, 37, 'YES'),
(5, 42, 'YES'),
(5, 41, 'YES'),
(5, 40, 'YES'),
(5, 39, 'YES'),
(5, 38, 'YES'),
(5, 7, 'YES'),
(5, 21, 'YES'),
(5, 29, 'YES');

-- --------------------------------------------------------

--
-- Table structure for table `tb_nolimitcron`
--

CREATE TABLE `tb_nolimitcron` (
  `ID` int(1) NOT NULL COMMENT '1=update-data-tagihan-bulanan, 2=notifWA_tagihan-bulanan,3=update-data-tagihan-bulananmember, 4=notifWA_tagihan-bulananmember',
  `START` int(2) NOT NULL,
  `LENGHT` int(2) NOT NULL,
  `BULAN` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tb_promo`
--

CREATE TABLE `tb_promo` (
  `IDPROMO` varchar(20) NOT NULL,
  `TITLE` varchar(100) NOT NULL,
  `IMG` varchar(200) NOT NULL,
  `DESKRIPSI` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tb_setup_notif`
--

CREATE TABLE `tb_setup_notif` (
  `ID` int(4) NOT NULL,
  `SUBAPP` varchar(30) NOT NULL,
  `APPNAME` varchar(50) NOT NULL COMMENT 'telegram/whatsapp',
  `CHANNEL` varchar(100) NOT NULL COMMENT 'nama channel'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `tb_setup_notif`
--

INSERT INTO `tb_setup_notif` (`ID`, `SUBAPP`, `APPNAME`, `CHANNEL`) VALUES
(1, 'APLIKASI', 'telegram', '@WhusnetNotifTier01'),
(2, 'APLIKASI', 'telegram', '@WhusnetNotifTier02');

-- --------------------------------------------------------

--
-- Table structure for table `tb_tiernotif`
--

CREATE TABLE `tb_tiernotif` (
  `ID` int(3) NOT NULL,
  `GROUPACCOUNTS` int(3) NOT NULL,
  `TITLE` varchar(50) NOT NULL,
  `APPNOTIF` int(4) NOT NULL,
  `DUETIME` varchar(50) NOT NULL,
  `NOTE` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `tb_tiernotif`
--

INSERT INTO `tb_tiernotif` (`ID`, `GROUPACCOUNTS`, `TITLE`, `APPNOTIF`, `DUETIME`, `NOTE`) VALUES
(11, 1, 'Notification App Gangguan 01', 1, '24', 'notif untuk gangguan - tier 1'),
(12, 1, 'Notification App Gangguan 02', 2, '24', 'notif untuk gangguan - tier 2'),
(13, 2, 'Notif Registration Tele', 2, '0', 'Notif info Mulai Registration'),
(14, 3, 'Notification Mulai Proses 02', 2, '0', 'Notif info Mulai Proses'),
(15, 0, 'Notification Telat Proses 01', 1, '27', 'Notif info telat Proses');

-- --------------------------------------------------------

--
-- Table structure for table `tb_urut`
--

CREATE TABLE `tb_urut` (
  `idurut` int(11) NOT NULL,
  `namaForm` text NOT NULL,
  `noUrut` char(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `tb_urut`
--

INSERT INTO `tb_urut` (`idurut`, `namaForm`, `noUrut`) VALUES
(1, 'RQ', '44'),
(2, 'PE', '44'),
(3, 'US', '0'),
(4, 'PG', '18'),
(6, 'ME', '0'),
(7, 'MR', '0'),
(8, 'BR', '0'),
(9, 'IN', '39'),
(10, 'PL', '0'),
(11, 'PK', '2'),
(12, 'PP', '0'),
(13, 'PQ', '0'),
(14, 'TI', '0');

-- --------------------------------------------------------

--
-- Table structure for table `tglpenagihan`
--

CREATE TABLE `tglpenagihan` (
  `id` int(2) NOT NULL,
  `penagihan` varchar(5) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tiket_kategori`
--

CREATE TABLE `tiket_kategori` (
  `IDKATEGORITIKET` int(3) NOT NULL,
  `KATEGORI` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `tiket_kategori`
--

INSERT INTO `tiket_kategori` (`IDKATEGORITIKET`, `KATEGORI`) VALUES
(1, 'Gangguan'),
(2, 'Migrasi Paket'),
(3, 'Speed OnDeman'),
(4, 'Lainnya'),
(11, 'FO Cut');

-- --------------------------------------------------------

--
-- Table structure for table `tiket_masuk`
--

CREATE TABLE `tiket_masuk` (
  `NOTIKET` varchar(15) NOT NULL,
  `IDPELANGGAN` varchar(11) DEFAULT NULL,
  `IDKATEGORITIKET` int(3) DEFAULT NULL,
  `GANGGUANLAIN` text DEFAULT NULL,
  `KELUHAN` text DEFAULT NULL,
  `LAT` varchar(20) DEFAULT NULL,
  `LONG` varchar(20) NOT NULL,
  `LAT_LOKASIGANGGUAN` varchar(50) NOT NULL DEFAULT '',
  `LONG_LOKASIGANGGUAN` varchar(50) NOT NULL DEFAULT '',
  `INSERTEDBY` varchar(11) DEFAULT NULL,
  `INSERTEDAT` datetime NOT NULL,
  `PROCESSEDBY` varchar(11) NOT NULL,
  `IMG_LOCATION` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tiket_proses`
--

CREATE TABLE `tiket_proses` (
  `NOTIKET` varchar(15) NOT NULL,
  `PROCESSEDBY` varchar(11) DEFAULT NULL,
  `PROCESSEDAT` timestamp NOT NULL DEFAULT current_timestamp(),
  `FLAG` tinyint(1) NOT NULL COMMENT '0="belum diproses";1="proses selesai/berhasil";2="proses gagal"',
  `FINISHEDAT` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tiket_riwayatgagal`
--

CREATE TABLE `tiket_riwayatgagal` (
  `NOTIKET` varchar(11) NOT NULL,
  `FOTOKARYAWAN` varchar(150) DEFAULT NULL,
  `FOTOKONEKSI` varchar(150) DEFAULT NULL,
  `RATTING` int(2) DEFAULT NULL,
  `REVIEW` text DEFAULT NULL,
  `KONFIRMASI` text NOT NULL,
  `CATATANTEKNISI` text NOT NULL,
  `FAILEDBY` varchar(11) DEFAULT NULL,
  `FAILEDAT` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tiket_riwayatselesai`
--

CREATE TABLE `tiket_riwayatselesai` (
  `NOTIKET` varchar(11) NOT NULL,
  `FOTOKARYAWAN` varchar(150) DEFAULT NULL,
  `FOTOKONEKSI` varchar(150) DEFAULT NULL,
  `RATTING` int(2) DEFAULT NULL,
  `REVIEW` text DEFAULT NULL,
  `KONFIRMASI` text NOT NULL,
  `CATATANTEKNISI` text NOT NULL,
  `FINISHEDBY` varchar(11) DEFAULT NULL,
  `FINISHEDAT` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transaksi`
--

CREATE TABLE `transaksi` (
  `NO_TRANSAKSI` varchar(16) NOT NULL,
  `NO_REK` varchar(6) DEFAULT NULL,
  `AKUN` varchar(4) DEFAULT NULL,
  `DEBET` float(255,0) DEFAULT NULL,
  `KREDIT` float(255,0) DEFAULT NULL,
  `SALDO` float(255,0) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `IDUSERS` varchar(10) NOT NULL,
  `IDPENGGUNA` varchar(11) DEFAULT NULL,
  `USERNAME` varchar(30) DEFAULT NULL,
  `PASSWORD` varchar(250) DEFAULT NULL,
  `IDCABANG` int(11) DEFAULT NULL,
  `JABATANID` int(11) DEFAULT NULL,
  `AKSESANDROID` tinyint(1) NOT NULL,
  `AKSESAPPPELANGGANID` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Untuk App Pelanggan',
  `ASADMIN_APPKEUANGAN` varchar(4) NOT NULL COMMENT '	admin app keuangan hanya boleh satu saja.',
  `ENTRYBY` varchar(30) DEFAULT NULL,
  `ENTRYDATE` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `LASTLOGIN` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `LASTIPADDR` varchar(30) DEFAULT NULL,
  `BEARER` varchar(250) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`IDUSERS`, `IDPENGGUNA`, `USERNAME`, `PASSWORD`, `IDCABANG`, `JABATANID`, `AKSESANDROID`, `AKSESAPPPELANGGANID`, `ASADMIN_APPKEUANGAN`, `ENTRYBY`, `ENTRYDATE`, `LASTLOGIN`, `LASTIPADDR`, `BEARER`) VALUES
('6156ba0c2c', 'PG000001', 'faruqi', 'a4416712755cf7c534696241f3330be2c3fce216', 1, 1, 1, 1, '1', '1', '2021-10-01 14:35:35', '2021-10-01 00:00:00', '127.0.0.1', ''),
('6156ba9e6f', 'PG000002', 'taufik', 'd95f4131ee69b6383f80e52f9cd5128fcba780f0', 1, 1, 0, 1, '0', '0', '2021-10-01 14:44:35', '2021-10-01 00:00:00', '127.0.0.1', ''),
('6156bab76b', 'PG000003', 'etik', 'ca322994365d59e91fd9eb68bd61bf8aaf872c8b', 1, 1, 0, 1, '0', '0', '2021-10-01 14:44:08', '2021-10-01 00:00:00', '127.0.0.1', ''),
('6156bae559', 'PG000004', 'rohman', 'd027bfa3841878306051ad37195a509ab55177ae', 1, 1, 0, 1, '1', '1', '2021-10-01 14:43:45', '2021-10-01 00:00:00', '127.0.0.1', ''),
('6156bb1e75', 'PG000005', 'ausya', 'f65e44fadb585310cf729d4e81f3d0ec14362d87', 1, 1, 0, 1, '0', '0', '2021-10-01 14:42:56', '2021-10-01 00:00:00', '127.0.0.1', ''),
('6156bc8ce3', 'PG000006', 'arly', 'ff831b0fdfa2d6e3a6715da1afeddd8b66f21fe9', 1, 1, 0, 1, '0', '0', '2021-10-01 14:52:36', '2021-10-01 00:00:00', '127.0.0.1', ''),
('6156bd54b1', 'PG000008', 'rifki', '7f21c292013257a6752eb6d574a11bb84b0100e6', 1, 1, 0, 1, '1', '1', '2021-10-01 14:54:30', '2021-10-01 00:00:00', '127.0.0.1', ''),
('6156bda369', 'PG000010', 'mawan', '3326491defc3ea48ceaa21797ed052135a545061', 1, 5, 0, 1, '', 'PG000005', '2025-10-10 11:39:10', '2025-10-10 00:00:00', '182.1.119.190', ''),
('6156bdd1a5', 'PG000011', 'fuad', '3326491defc3ea48ceaa21797ed052135a545061', 1, 1, 0, 1, '', 'PG000005', '2025-10-23 12:51:56', '2025-10-23 00:00:00', '103.186.9.200', ''),
('67d7bb667a', 'PG000013', 'lutfiah', 'c85b5ad7409cd3e2c3e574c870ace0470de0c498', 1, 1, 0, 1, '', 'PG000001', '2025-03-17 13:04:41', '2025-03-17 00:00:00', '103.171.244.101', ''),
('67dac915c9', 'PE000001', 'dinar-markondang', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000013', '2025-03-19 00:00:00', NULL, '103.171.244.111', ''),
('6809b46885', 'PE000004', 'eva-rodiana-sari', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000005', '2025-04-24 00:00:00', NULL, '180.248.6.217', ''),
('6809b58469', 'PE000005', 'eva-rosdiana-sari', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000005', '2025-04-24 00:00:00', NULL, '180.248.6.217', ''),
('6818b474a9', 'PE000006', 'wahyu-aulia-zahro', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000005', '2025-05-05 00:00:00', NULL, '103.171.244.110', ''),
('6818b4d3a5', 'PE000007', 'muhammad-syifaa-hanafi', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000005', '2025-05-05 00:00:00', NULL, '103.171.244.110', ''),
('681968ce05', 'PG000014', 'Lugas', 'd989dece28bf312158101ffe597d5f0ce7f28434', 1, 5, 0, 1, '', 'PG000002', '2025-05-06 08:42:17', '2025-05-06 00:00:00', '114.5.240.80', ''),
('6819800667', 'PE000008', 'PurnamaAyuSurodikraman', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000005', '2025-05-06 00:00:00', NULL, NULL, ''),
('682add00b1', 'PE000009', 'ihda-ainin-nadhira', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000005', '2025-05-19 00:00:00', NULL, '103.171.244.109', ''),
('684fc48563', 'PE000010', 'nindia-galuh-prismadani', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000005', '2025-06-16 00:00:00', NULL, '103.171.244.111', ''),
('685b9d5df1', 'PE000011', 'anang-kurniawan', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000005', '2025-06-25 00:00:00', NULL, '103.171.244.109', ''),
('68639e4873', 'PE000012', 'ragil-cahya-adi-prastya', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000005', '2025-07-01 00:00:00', NULL, '103.171.244.111', ''),
('68872141cc', 'PE000013', 'afdhal-fardhika-achmad', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000005', '2025-07-28 00:00:00', NULL, '103.186.9.108', ''),
('688c7aceb5', 'PE000014', 'fuad-aan-maulana-rodli', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000005', '2025-08-01 00:00:00', NULL, '103.186.9.108', ''),
('688dd244af', 'PE000015', 'bambang-saktiawan', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000005', '2025-08-02 00:00:00', NULL, '103.186.9.108', ''),
('68931924df', 'PE000016', 'reza-wahyu-dwi-ardiansah', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000005', '2025-08-06 00:00:00', NULL, '103.186.9.108', ''),
('689b035522', 'PE000017', 'citra-noriya', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000005', '2025-08-12 00:00:00', NULL, '103.186.9.108', ''),
('68a7d4ae99', 'PE000018', 'sri-mulyani', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000005', '2025-08-22 00:00:00', NULL, '103.186.9.108', ''),
('68a9b2872b', 'PE000019', 'fatkhul-zamroni', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000005', '2025-08-23 00:00:00', NULL, '103.186.9.97', ''),
('68aeb35966', 'PE000020', 'aries-prasetyawan', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000005', '2025-08-27 00:00:00', NULL, '103.186.9.111', ''),
('68b2a2f503', 'PE000021', 'pangestu-adita-pratama', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000005', '2025-08-30 00:00:00', NULL, '103.186.9.110', ''),
('68b78d5e9c', 'PE000022', 'fuad-achmadi', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000005', '2025-09-03 00:00:00', NULL, NULL, ''),
('68b78d6956', 'PE000023', 'fuad-achmadi', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000005', '2025-09-03 00:00:00', NULL, NULL, ''),
('68ba8a84bd', 'PE000024', 'anita-dewi-rozalia-putri', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000005', '2025-09-05 00:00:00', NULL, '103.186.9.110', ''),
('68c0d07a5f', 'PE000025', 'samsudin', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000005', '2025-09-10 00:00:00', NULL, '103.186.9.110', ''),
('68c4f8784f', 'PE000026', 'alma-irsyadul-haqqi', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000005', '2025-09-13 00:00:00', NULL, '103.186.9.110', ''),
('68c51ceddd', 'PE000027', 'farid-wajdi-ardjono', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000005', '2025-09-13 00:00:00', NULL, '103.186.9.110', ''),
('68c7c5d5a7', 'PE000028', 'muh-busri', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000005', '2025-09-15 00:00:00', NULL, '103.186.9.110', ''),
('68d0efc2e2', 'PE000029', 'siti-aminah-romdiati', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000005', '2025-09-22 00:00:00', NULL, '103.186.9.111', ''),
('68d4b61a02', 'PE000030', 'mulyono', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000005', '2025-09-25 00:00:00', NULL, '103.186.9.200', ''),
('68d9dea3e5', 'PE000031', 'siti-komariyah', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000005', '2025-09-29 00:00:00', NULL, '103.186.9.200', ''),
('68da3c63b2', 'PE000032', 'budi-santoso', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000005', '2025-09-29 00:00:00', NULL, '103.186.9.200', ''),
('68de465800', 'PE000033', 'bima-orbita-dirgantara', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000005', '2025-10-02 00:00:00', NULL, '103.186.9.200', ''),
('68e2927c74', 'PG000015', NULL, NULL, 1, 1, 0, 1, '', 'PG000005', '2025-10-05 00:00:00', NULL, '103.186.9.67', ''),
('68e292fc09', 'PG000016', 'ALVIAN', 'f155420910971bd231e121a2534c25978c732a10', 1, 5, 0, 1, '', 'PG000005', '2025-10-05 22:48:40', '2025-10-05 00:00:00', '103.186.9.67', ''),
('68e37d32bb', 'PG000017', 'lisvi', '7c222fb2927d828af22f592134e8932480637c0d', 1, 1, 0, 1, '', 'PG000005', '2025-10-06 15:26:45', '2025-10-06 00:00:00', '103.186.9.200', ''),
('68e75d7e78', 'PE000034', 'suwarni', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000017', '2025-10-09 00:00:00', NULL, '103.186.9.200', ''),
('68ef255c44', 'PE000035', 'wiji-asih', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000017', '2025-10-15 00:00:00', NULL, '103.186.9.200', ''),
('68f33d81ad', 'PE000036', 'bayu-prasetyo', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000017', '2025-10-18 00:00:00', NULL, '103.186.9.200', ''),
('68fc3d4f89', 'PE000037', 'awang-yudha-aji-kusuma', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000017', '2025-10-25 00:00:00', NULL, '103.186.9.200', ''),
('68fefa695b', 'PE000038', 'alvian-rozaq', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000017', '2025-10-27 00:00:00', NULL, '103.186.9.200', ''),
('68ff1232b4', 'PE000039', 'kevin-herlambang-dwi', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000017', '2025-10-27 00:00:00', NULL, '103.186.9.200', ''),
('6900494880', 'PE000040', 'aji-wibowo', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000017', '2025-10-28 00:00:00', NULL, '103.186.9.200', ''),
('691c0cf17b', 'PE000041', 'firdaus-hasan-al-bana', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000017', '2025-11-18 00:00:00', NULL, '165.99.202.249', ''),
('692d166b15', 'PE000042', 'endah-puji-rahayu', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000017', '2025-12-01 00:00:00', NULL, '165.99.202.249', ''),
('695cb38f7d', 'PG000018', 'nensimira', 'eaf00b74e25128076aca8fbe09969e18e4e4b110', 1, 1, 1, 1, 'true', 'PG000008', '2026-01-06 14:03:01', '2026-01-06 00:00:00', '165.99.202.248', ''),
('695cb4b770', 'PE000043', '', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000017', '2026-01-06 00:00:00', NULL, NULL, ''),
('6989478ee1', 'PE000044', 'guntur-caroko-bintang', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000017', '2026-02-09 00:00:00', NULL, '165.99.202.251', '');

-- --------------------------------------------------------

--
-- Table structure for table `wa_template_pesan`
--

CREATE TABLE `wa_template_pesan` (
  `ID` int(6) NOT NULL,
  `TITLE` varchar(150) NOT NULL,
  `ALIAS` varchar(150) NOT NULL,
  `TEMPLATEMESSAGE` text NOT NULL,
  `CREATEDAT` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `wa_template_pesan`
--

INSERT INTO `wa_template_pesan` (`ID`, `TITLE`, `ALIAS`, `TEMPLATEMESSAGE`, `CREATEDAT`) VALUES
(1, 'temp selesai 01', 'temp-wa-selesai', 'Kami dari Pt Whusnet, ingin mengabarkan bahwa saat ini tiket gangguan anda telah *Selesai*. \\n\\n mohon untuk berkenan memberikan review untuk kemajuan kami di link yang terlampir.\\n\\n Trimakasih atas perhatiannya.\\n -- Pt. Whusnet --', '2021-09-27 05:02:00'),
(2, 'temp proses', 'temp-wa-proses', 'Kami dari Pt Whusnet, ingin mengabarkan bahwa saat ini tiket gangguan anda akan *Segera Diproses*.\\n\\n Trimakasih atas perhatiannya.\\n -- Pt. Whusnet --', '2021-09-24 02:03:55'),
(3, 'temp selesai 02', 'temp-wa-selesai', 'gangguan anda telah selesai kami tangani. \\n Mohon berkenan untuk mengisi review / ulasan untuk kami di link yang terlampir. trimakasih.', '2021-09-27 05:01:53'),
(4, 'temp 04', 'temp-wa-selesai', 'contoh', '2021-09-27 22:01:55'),
(5, 'temp 05', '', '', '2021-08-31 15:38:24');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `apiakses`
--
ALTER TABLE `apiakses`
  ADD PRIMARY KEY (`IDUSERS`);

--
-- Indexes for table `apikeuangan_buktitransaksilunas`
--
ALTER TABLE `apikeuangan_buktitransaksilunas`
  ADD PRIMARY KEY (`IDUNIQ`);

--
-- Indexes for table `apikeuangan_buktitransaksipemasangan`
--
ALTER TABLE `apikeuangan_buktitransaksipemasangan`
  ADD PRIMARY KEY (`IDPERMINTAAN`);

--
-- Indexes for table `apikeuangan_buktitransaksitagihan`
--
ALTER TABLE `apikeuangan_buktitransaksitagihan`
  ADD PRIMARY KEY (`IDUNIQ`);

--
-- Indexes for table `apikeuangan_buktitransaksiterkumpul`
--
ALTER TABLE `apikeuangan_buktitransaksiterkumpul`
  ADD PRIMARY KEY (`IDUNIQ`);

--
-- Indexes for table `apikeuangan_detailjurnalpemasukan`
--
ALTER TABLE `apikeuangan_detailjurnalpemasukan`
  ADD PRIMARY KEY (`IDJURNALPEMASUKAN`);

--
-- Indexes for table `apikeuangan_detailjurnalpengeluaran`
--
ALTER TABLE `apikeuangan_detailjurnalpengeluaran`
  ADD PRIMARY KEY (`IDJURNALPENGELUARAN`);

--
-- Indexes for table `apikeuangan_jurnaloperasioanl`
--
ALTER TABLE `apikeuangan_jurnaloperasioanl`
  ADD PRIMARY KEY (`IDOPERASIONAL`);

--
-- Indexes for table `apikeuangan_jurnalpemasukan`
--
ALTER TABLE `apikeuangan_jurnalpemasukan`
  ADD PRIMARY KEY (`IDJURNALPEMASUKAN`);

--
-- Indexes for table `apikeuangan_jurnalpengeluaran`
--
ALTER TABLE `apikeuangan_jurnalpengeluaran`
  ADD PRIMARY KEY (`IDJURNALPENGELUARAN`);

--
-- Indexes for table `apikeuangan_jurnaltranfer`
--
ALTER TABLE `apikeuangan_jurnaltranfer`
  ADD PRIMARY KEY (`IDJURNALTRANFER`);

--
-- Indexes for table `barang`
--
ALTER TABLE `barang`
  ADD PRIMARY KEY (`KODEBARANG`);

--
-- Indexes for table `beritainfo`
--
ALTER TABLE `beritainfo`
  ADD PRIMARY KEY (`IDCONTENT`);

--
-- Indexes for table `biaya_tagihan`
--
ALTER TABLE `biaya_tagihan`
  ADD PRIMARY KEY (`IDBIAYA`);

--
-- Indexes for table `cabang`
--
ALTER TABLE `cabang`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `cron`
--
ALTER TABLE `cron`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `detail_aktivasi_pppoe`
--
ALTER TABLE `detail_aktivasi_pppoe`
  ADD PRIMARY KEY (`IDDETAILAKTIVASIPPPOE`);

--
-- Indexes for table `detail_aktivasi_queue`
--
ALTER TABLE `detail_aktivasi_queue`
  ADD PRIMARY KEY (`IDDETAILAKTIVASIQUEUE`);

--
-- Indexes for table `detail_jurnal_harian`
--
ALTER TABLE `detail_jurnal_harian`
  ADD PRIMARY KEY (`IDDETAILJURNAL`);

--
-- Indexes for table `detail_komisi_mitra`
--
ALTER TABLE `detail_komisi_mitra`
  ADD PRIMARY KEY (`IDKOMISI`);

--
-- Indexes for table `ip_kosong`
--
ALTER TABLE `ip_kosong`
  ADD PRIMARY KEY (`IPADDRESS`);

--
-- Indexes for table `jabatan`
--
ALTER TABLE `jabatan`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `jenka_paket`
--
ALTER TABLE `jenka_paket`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `jurnalharian`
--
ALTER TABLE `jurnalharian`
  ADD PRIMARY KEY (`KODEJURNAL`);

--
-- Indexes for table `kantor`
--
ALTER TABLE `kantor`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `kategori_barang`
--
ALTER TABLE `kategori_barang`
  ADD PRIMARY KEY (`KODEKATEGORIBARANG`);

--
-- Indexes for table `kategori_perangkat_jaringan`
--
ALTER TABLE `kategori_perangkat_jaringan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `kode_kontrol_distribusi`
--
ALTER TABLE `kode_kontrol_distribusi`
  ADD PRIMARY KEY (`kode`);

--
-- Indexes for table `komisi`
--
ALTER TABLE `komisi`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `komisi_mitra`
--
ALTER TABLE `komisi_mitra`
  ADD PRIMARY KEY (`IDKOMISI`);

--
-- Indexes for table `laporan_pemasangan_wifi`
--
ALTER TABLE `laporan_pemasangan_wifi`
  ADD PRIMARY KEY (`IDREPORT`),
  ADD KEY `idx_idpengguna` (`IDPENGGUNA`),
  ADD KEY `idx_ipaddr` (`IPADDR`);

--
-- Indexes for table `level`
--
ALTER TABLE `level`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `merk_barang`
--
ALTER TABLE `merk_barang`
  ADD PRIMARY KEY (`IDMERK`);

--
-- Indexes for table `nomor_port_odp`
--
ALTER TABLE `nomor_port_odp`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `noonu1024`
--
ALTER TABLE `noonu1024`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `odp_location`
--
ALTER TABLE `odp_location`
  ADD PRIMARY KEY (`IDLOCATION`);

--
-- Indexes for table `olt_aktifasi`
--
ALTER TABLE `olt_aktifasi`
  ADD PRIMARY KEY (`IDAKTIFASI`);

--
-- Indexes for table `olt_check_dbm`
--
ALTER TABLE `olt_check_dbm`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `olt_newSignal`
--
ALTER TABLE `olt_newSignal`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `olt_report_signal`
--
ALTER TABLE `olt_report_signal`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `olt_report_state`
--
ALTER TABLE `olt_report_state`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `olt_slot_register`
--
ALTER TABLE `olt_slot_register`
  ADD PRIMARY KEY (`IDMESIN`);

--
-- Indexes for table `olt_slot_unregister`
--
ALTER TABLE `olt_slot_unregister`
  ADD PRIMARY KEY (`IDMESIN`);

--
-- Indexes for table `paket`
--
ALTER TABLE `paket`
  ADD PRIMARY KEY (`KODEPAKET`),
  ADD KEY `idx_kodepaket` (`KODEPAKET`),
  ADD KEY `idx_namapaket` (`NAMA_PAKET`);

--
-- Indexes for table `penagihan`
--
ALTER TABLE `penagihan`
  ADD PRIMARY KEY (`IDTAGIHAN`);

--
-- Indexes for table `pengguna`
--
ALTER TABLE `pengguna`
  ADD PRIMARY KEY (`IDPENGGUNA`),
  ADD KEY `idx_idpengguna` (`IDPENGGUNA`),
  ADD KEY `idx_idwilayah` (`IDWILAYAH`),
  ADD KEY `idx_hp` (`HP`),
  ADD KEY `idx_namadepan` (`NAMADEPAN`);

--
-- Indexes for table `prosedure_permintaan_wifi`
--
ALTER TABLE `prosedure_permintaan_wifi`
  ADD PRIMARY KEY (`IDPERMINTAAN`),
  ADD KEY `prosedure_permintaan_wifi_ibfk_2` (`kode_kontrol_distribusi`),
  ADD KEY `prosedure_permintaan_wifi_ibfk_1` (`kategori_perangkat_jaringan`),
  ADD KEY `idx_master_id` (`master_id`),
  ADD KEY `idx_status` (`STATUS`),
  ADD KEY `idx_idpengguna` (`IDPENGGUNA`),
  ADD KEY `idx_idpaket` (`IDPAKET`),
  ADD KEY `idx_idpermintaan` (`IDPERMINTAAN`);

--
-- Indexes for table `riwayatstatus_penggunabarang`
--
ALTER TABLE `riwayatstatus_penggunabarang`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `riwayat_pelanggan`
--
ALTER TABLE `riwayat_pelanggan`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `router`
--
ALTER TABLE `router`
  ADD PRIMARY KEY (`IDROUTER`);

--
-- Indexes for table `satuan`
--
ALTER TABLE `satuan`
  ADD PRIMARY KEY (`IDSATUAN`);

--
-- Indexes for table `setting_billing`
--
ALTER TABLE `setting_billing`
  ADD PRIMARY KEY (`IDSETTING`);

--
-- Indexes for table `set_app`
--
ALTER TABLE `set_app`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `statusbarang`
--
ALTER TABLE `statusbarang`
  ADD PRIMARY KEY (`IDSTATUSBARANG`);

--
-- Indexes for table `tampilan`
--
ALTER TABLE `tampilan`
  ADD PRIMARY KEY (`IDTAMPILAN`);

--
-- Indexes for table `tb_alamat`
--
ALTER TABLE `tb_alamat`
  ADD PRIMARY KEY (`IDWILAYAH`);

--
-- Indexes for table `tb_history_process_finish_installation`
--
ALTER TABLE `tb_history_process_finish_installation`
  ADD PRIMARY KEY (`IDUNIQ`);

--
-- Indexes for table `tb_history_process_not_active`
--
ALTER TABLE `tb_history_process_not_active`
  ADD PRIMARY KEY (`IDUNIQ`);

--
-- Indexes for table `tb_history_process_start_installation`
--
ALTER TABLE `tb_history_process_start_installation`
  ADD PRIMARY KEY (`IDUNIQ`);

--
-- Indexes for table `tb_history_process_survey`
--
ALTER TABLE `tb_history_process_survey`
  ADD PRIMARY KEY (`IDUNIQ`);

--
-- Indexes for table `tb_history_process_team_installation`
--
ALTER TABLE `tb_history_process_team_installation`
  ADD PRIMARY KEY (`IDUNIQ`);

--
-- Indexes for table `tb_history_process_verification`
--
ALTER TABLE `tb_history_process_verification`
  ADD PRIMARY KEY (`IDUNIQ`);

--
-- Indexes for table `tb_history_registrations`
--
ALTER TABLE `tb_history_registrations`
  ADD PRIMARY KEY (`IDUNIQ`);

--
-- Indexes for table `tb_history_report_survey`
--
ALTER TABLE `tb_history_report_survey`
  ADD PRIMARY KEY (`IDUNIQ`);

--
-- Indexes for table `tb_menu`
--
ALTER TABLE `tb_menu`
  ADD PRIMARY KEY (`menuid`);

--
-- Indexes for table `tb_setup_notif`
--
ALTER TABLE `tb_setup_notif`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `tb_tiernotif`
--
ALTER TABLE `tb_tiernotif`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `tb_urut`
--
ALTER TABLE `tb_urut`
  ADD PRIMARY KEY (`idurut`);

--
-- Indexes for table `tiket_kategori`
--
ALTER TABLE `tiket_kategori`
  ADD PRIMARY KEY (`IDKATEGORITIKET`);

--
-- Indexes for table `tiket_masuk`
--
ALTER TABLE `tiket_masuk`
  ADD PRIMARY KEY (`NOTIKET`);

--
-- Indexes for table `tiket_riwayatgagal`
--
ALTER TABLE `tiket_riwayatgagal`
  ADD PRIMARY KEY (`NOTIKET`);

--
-- Indexes for table `tiket_riwayatselesai`
--
ALTER TABLE `tiket_riwayatselesai`
  ADD PRIMARY KEY (`NOTIKET`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`IDUSERS`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `kantor`
--
ALTER TABLE `kantor`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `nomor_port_odp`
--
ALTER TABLE `nomor_port_odp`
  MODIFY `ID` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `statusbarang`
--
ALTER TABLE `statusbarang`
  MODIFY `IDSTATUSBARANG` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tb_setup_notif`
--
ALTER TABLE `tb_setup_notif`
  MODIFY `ID` int(4) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tb_tiernotif`
--
ALTER TABLE `tb_tiernotif`
  MODIFY `ID` int(3) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `prosedure_permintaan_wifi`
--
ALTER TABLE `prosedure_permintaan_wifi`
  ADD CONSTRAINT `prosedure_permintaan_wifi_ibfk_1` FOREIGN KEY (`kategori_perangkat_jaringan`) REFERENCES `kategori_perangkat_jaringan` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `prosedure_permintaan_wifi_ibfk_2` FOREIGN KEY (`kode_kontrol_distribusi`) REFERENCES `kode_kontrol_distribusi` (`kode`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
