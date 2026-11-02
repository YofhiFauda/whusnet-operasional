-- phpMyAdmin SQL Dump
-- version 5.1.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Waktu pembuatan: 02 Nov 2026 pada 01.52
-- Versi server: 10.4.31-MariaDB
-- Versi PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `samb_db_aplikasi_sambit`
--

DELIMITER $$
--
-- Fungsi
--
CREATE DEFINER=`samb_admin_db_aplikasi_sambit`@`localhost` FUNCTION `fRupiah` (`number` BIGINT) RETURNS VARCHAR(255) CHARSET latin1 COLLATE latin1_swedish_ci  BEGIN
    DECLARE hasil VARCHAR(255);
    SET hasil = REPLACE(REPLACE(REPLACE(FORMAT(number, 0), '.', '|'), ',', '.'), '|', ',');
    RETURN (hasil);
    END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `apiakses`
--

CREATE TABLE `apiakses` (
  `IDUSERS` int(3) NOT NULL,
  `NAME` varchar(100) NOT NULL,
  `ACCESS` tinyint(1) NOT NULL,
  `USERNAME` varchar(30) NOT NULL,
  `PASSWORD` varchar(250) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `apiakses`
--

INSERT INTO `apiakses` (`IDUSERS`, `NAME`, `ACCESS`, `USERNAME`, `PASSWORD`) VALUES
(1, 'Api Mesin OLT', 1, 'apioltmaster', '3a91482a4a46a09a086f4f48268c494daaed9c0a');

-- --------------------------------------------------------

--
-- Struktur dari tabel `apikeuangan_buktitransaksilunas`
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
-- Struktur dari tabel `apikeuangan_buktitransaksipemasangan`
--

CREATE TABLE `apikeuangan_buktitransaksipemasangan` (
  `IDPERMINTAAN` varchar(11) NOT NULL,
  `TGLBAYAR` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `apikeuangan_buktitransaksitagihan`
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
-- Dumping data untuk tabel `apikeuangan_buktitransaksitagihan`
--

INSERT INTO `apikeuangan_buktitransaksitagihan` (`IDUNIQ`, `IDTRANSAKSI`, `IDPERMINTAAN`, `NOINDEXTAGIHAN`, `BAYAR`, `BULANTAGIHAN`, `FLAG`, `INSERTED_AT`, `notivwa`) VALUES
('660a2a814ba', 'IN000001', 'RQ000012', 0, 300333, '2024-04-01', 0, '2024-04-01 03:31:13', 0),
('660e119911d', 'IN000002', 'RQ000011', 0, 124667, '2024-04-04', 0, '2024-04-04 02:34:03', 0),
('6628affa495', 'IN000003', 'RQ000013', 0, 38000, '2024-04-24', 0, '2024-04-24 07:08:42', 0),
('6628b03f6f0', 'IN000004', 'RQ000008', 0, 34000, '2024-04-24', 0, '2024-04-24 07:09:51', 0),
('6628b09eabe', 'IN000005', 'RQ000010', 0, 37000, '2024-04-24', 0, '2024-04-24 07:11:27', 0),
('6634522e239', 'IN000006', 'RQ000001', 0, 111355, '2024-05-03', 0, '2024-05-03 02:55:42', 0),
('6634523d3dc', 'IN000007', 'RQ000002', 0, 111355, '2024-05-03', 0, '2024-05-03 02:55:58', 0),
('6634524d1d2', 'IN000008', 'RQ000003', 0, 129419, '2024-05-03', 0, '2024-05-03 02:56:13', 0),
('6634525d5a3', 'IN000009', 'RQ000004', 0, 161032, '2024-05-03', 0, '2024-05-03 02:56:29', 0),
('66345271386', 'IN000010', 'RQ000006', 0, 147484, '2024-05-03', 0, '2024-05-03 02:56:49', 0),
('6634542c711', 'IN000011', 'RQ000007', 0, 12000, '2024-05-03', 0, '2024-05-03 03:04:18', 0),
('6634543233d', 'IN000012', 'RQ000007', 0, 12000, '2024-05-03', 0, '2024-05-03 03:04:18', 0),
('663454bd598', 'IN000013', 'RQ000009', 0, 12000, '2024-05-03', 0, '2024-05-03 03:06:44', 0),
('663454c48f3', 'IN000014', 'RQ000009', 0, 12000, '2024-05-03', 0, '2024-05-03 03:06:44', 0),
('663457e6747', 'IN000015', 'RQ000005', 0, 129419, '2024-05-03', 0, '2024-05-03 03:20:06', 0),
('663458a3c0f', 'IN000016', 'RQ000014', 0, 111355, '2024-05-03', 0, '2024-05-03 03:23:16', 0),
('6634596918a', 'IN000017', 'RQ000015', 0, 145484, '2024-05-03', 0, '2024-05-07 02:34:00', 0),
('6635a79a5ee', 'IN000018', 'RQ000016', 0, 125226, '2024-05-04', 0, '2024-05-04 03:12:27', 0),
('6635a8f8cf5', 'IN000019', 'RQ000018', 0, 12000, '2024-05-04', 0, '2024-05-04 03:18:17', 0),
('6638858c60a', 'IN000020', 'RQ000019', 0, 99710, '2024-05-06', 0, '2024-05-06 07:23:57', 0),
('66415d7b056', 'IN000021', 'RQ000017', 0, 179484, '2024-05-13', 0, '2024-05-13 00:23:24', 0),
('6685a38d770', 'IN000022', 'RQ000023', 0, 0, '2024-07-04', 0, '2024-07-03 19:16:29', 0),
('6685a3f0143', 'IN000023', 'RQ000024', 0, 0, '2024-07-04', 0, '2024-07-03 19:18:08', 0),
('669f4dd3107', 'IN000024', 'RQ000028', 0, 118167, '2024-07-23', 0, '2024-09-11 06:00:33', 0),
('669f4e53bcf', 'IN000025', 'RQ000028', 0, 118167, '2024-07-23', 0, '2024-09-11 06:00:33', 0),
('669f4e5416a', 'IN000026', 'RQ000028', 0, 118167, '2024-07-23', 0, '2024-09-11 06:00:33', 0),
('669f4e556fc', 'IN000027', 'RQ000028', 0, 118167, '2024-07-23', 0, '2024-09-11 06:00:33', 0),
('669f4e572f2', 'IN000028', 'RQ000028', 0, 118167, '2024-07-23', 0, '2024-09-11 06:00:33', 0),
('669f4e57a59', 'IN000029', 'RQ000028', 0, 118167, '2024-07-23', 0, '2024-09-11 06:00:33', 0),
('669f4e59037', 'IN000030', 'RQ000028', 0, 118167, '2024-07-23', 0, '2024-09-11 06:00:33', 0),
('66ce99c5b9d', 'IN000031', 'RQ000029', 0, 25355, '2024-08-28', 0, '2024-08-28 03:30:14', 0),
('66d11e095cb', 'IN000032', 'RQ000025', 0, 16839, '2024-08-30', 0, '2024-08-30 01:19:05', 0),
('6711c015840', 'IN000033', 'RQ000030', 0, 312000, '2024-10-18', 0, '2024-10-18 01:55:36', 0),
('6711c09f23c', 'IN000035', 'RQ000032', 0, 74903, '2024-10-18', 0, '2024-10-18 01:58:00', 0),
('6711c0a8134', 'IN000036', 'RQ000032', 0, 74903, '2024-10-18', 0, '2024-10-18 01:58:03', 0),
('67345ecb123', 'IN000037', 'RQ000033', 0, 72000, '2024-11-13', 0, '2024-11-13 08:09:51', 0),
('673460d2aa8', 'IN000038', 'RQ000035', 0, 99333, '2024-11-13', 0, '2024-11-13 08:18:29', 0),
('675d0d48be3', 'IN000039', 'RQ000037', 0, 72323, '2024-12-14', 0, '2024-12-14 04:44:57', 0),
('676cc815a12', 'IN000040', 'RQ000038', 0, 29742, '2024-12-26', 0, '2024-12-26 03:05:58', 0),
('6774b557262', 'IN000041', 'RQ000039', 0, 0, '2025-01-01', 0, '2025-01-01 03:24:07', 0),
('677741d9601', 'IN000042', 'RQ000040', 0, 12000, '2025-01-03', 0, '2025-01-03 01:48:09', 0),
('677741d973e', 'IN000043', 'RQ000041', 0, 12000, '2025-01-03', 0, '2025-01-03 01:48:09', 0),
('677741d9863', 'IN000044', 'RQ000042', 0, 12000, '2025-01-03', 0, '2025-01-03 01:48:09', 0),
('677741d9986', 'IN000045', 'RQ000043', 0, 12000, '2025-01-03', 0, '2025-01-03 01:48:09', 0),
('677741d9a3c', 'IN000046', 'RQ000044', 0, 12000, '2025-01-03', 0, '2025-01-03 01:48:09', 0),
('677746da42b', 'IN000047', 'RQ000045', 0, 12000, '2025-01-03', 0, '2025-01-03 02:09:30', 0),
('67a2dcdb6c2', 'IN000048', 'RQ000047', 0, 102357, '2025-02-05', 0, '2025-02-05 03:36:59', 0),
('67bd794053a', 'IN000049', 'RQ000048', 0, 33214, '2025-02-25', 0, '2025-02-25 08:03:14', 0),
('67c7a934c87', 'IN000050', 'RQ000049', 0, 127742, '2025-03-05', 0, '2025-03-05 01:30:29', 0),
('67e21a4052b', 'IN000051', 'RQ000050', 0, 73290, '2025-03-25', 0, '2025-03-25 02:51:44', 0),
('67f9d31b917', 'IN000052', 'RQ000051', 0, 102000, '2025-04-12', 0, '2025-04-12 02:42:35', 0),
('6808b08f6b1', 'IN000053', 'RQ000052', 0, 44200, '2025-04-23', 0, '2025-04-23 09:19:11', 0),
('6846b8144c4', 'IN000054', 'RQ000053', 0, 207000, '2025-06-09', 0, '2025-06-09 10:31:48', 0),
('6847f4283b0', 'IN000055', 'RQ000054', 0, 198333, '2025-06-10', 0, '2025-06-10 09:00:24', 0),
('6847fa0dbde', 'IN000056', 'RQ000055', 0, 75675800, '2025-06-10', 0, '2025-06-10 09:25:33', 0),
('6847fa0ec8e', 'IN000057', '', 0, 0, '2025-06-10', 0, '2025-06-10 09:25:34', 0),
('684ad6dea90', 'IN000058', 'RQ000056', 0, 105118, '2025-06-12', 0, '2025-06-12 13:32:14', 0),
('684afa27d47', 'IN000059', 'RQ000057', 0, 90074, '2025-06-12', 0, '2025-06-12 16:02:47', 0),
('684b26e51b8', 'IN000060', 'RQ000061', 0, 73767, '2025-06-13', 0, '2025-06-12 19:13:41', 0),
('684b26facb8', 'IN000061', 'RQ000060', 0, 164395, '2025-06-13', 0, '2025-06-12 19:14:02', 0),
('684ccdb52e1', 'IN000062', 'RQ000012', 0, 0, '2025-06-14', 0, '2025-06-14 01:17:41', 0),
('684f8a2a03f', 'IN000063', 'RQ000059', 0, 0, '2025-06-16', 0, '2025-06-16 03:06:18', 0),
('684f8a2b0ea', 'IN000064', '', 0, 0, '2025-06-16', 0, '2025-06-16 03:06:19', 0),
('684f8a3eb9d', 'IN000065', 'RQ000056', 0, 0, '2025-06-16', 0, '2025-06-16 03:06:38', 0),
('684f8a3f814', 'IN000066', '', 0, 0, '2025-06-16', 0, '2025-06-16 03:06:39', 0),
('685840daec9', 'IN000067', 'RQ000065', 0, 32247, '2025-06-23', 0, '2025-06-22 17:43:54', 0),
('685c4fde5cc', 'IN000068', 'RQ000067', 0, 20140, '2025-06-26', 0, '2025-06-25 19:37:02', 0),
('68615e4614a', 'IN000069', 'RQ000072', 0, 165, '2025-06-29', 0, '2025-06-29 15:39:50', 0),
('68663276caa', 'IN000070', 'RQ000070', 0, 273935, '2025-07-03', 0, '2025-07-03 07:34:14', 0),
('68663277ac0', 'IN000071', '', 0, 0, '2025-07-03', 0, '2025-07-03 07:34:15', 0),
('686c6cfd045', 'IN000072', 'RQ000063', 0, 0, '2025-07-08', 0, '2025-07-08 00:57:33', 0),
('686cc309c6c', 'IN000073', 'RQ000073', 0, 93613, '2025-07-08', 0, '2025-07-08 07:04:41', 0),
('686cc30a79d', 'IN000074', '', 0, 0, '2025-07-08', 0, '2025-07-08 07:04:42', 0),
('688b1cb70bb', 'IN000075', 'RQ000076', 0, 15548, '2025-07-31', 0, '2025-07-31 07:35:19', 0),
('688b1cb7cdb', 'IN000076', '', 0, 0, '2025-07-31', 0, '2025-07-31 07:35:19', 0),
('6899a1035af', 'IN000077', 'RQ000074', 0, 508774, '2025-08-11', 0, '2025-08-11 07:51:31', 0),
('6899a10457f', 'IN000078', '', 0, 0, '2025-08-11', 0, '2025-08-11 07:51:32', 0),
('689aa835a7b', 'IN000079', 'RQ000077', 0, 12000, '2025-08-12', 0, '2025-08-12 02:34:29', 0),
('68e76f5d6a1', 'IN000080', 'RQ000078', 0, 119935, '2025-10-09', 0, '2025-10-09 08:16:29', 0),
('68e76f5e41d', 'IN000081', '', 0, 0, '2025-10-09', 0, '2025-10-09 08:16:30', 0),
('690066978df', 'IN000082', 'RQ000081', 0, 26516, '2025-10-28', 0, '2025-10-28 06:45:43', 0),
('690066987e0', 'IN000083', '', 0, 0, '2025-10-28', 0, '2025-10-28 06:45:44', 0),
('6911b0fabbc', 'IN000084', 'RQ000082', 0, 85333, '2025-11-10', 0, '2025-11-10 09:31:38', 0),
('69576a9b833', 'IN000085', 'RQ000087', 0, 191097, '2026-01-02', 0, '2026-01-02 06:50:03', 0),
('69576a9c449', 'IN000086', '', 0, 0, '2026-01-02', 0, '2026-01-02 06:50:04', 0),
('6986e9b0ed5', 'IN000087', 'RQ000088', 0, 162000, '2026-02-07', 0, '2026-05-07 03:29:47', 0),
('6986e9b1c5e', 'IN000088', '', 0, 0, '2026-02-07', 0, '2026-02-07 07:28:49', 0),
('69eae3fc86b', 'IN000089', 'RQ000091', 0, 162000, '2026-04-24', 0, '2026-05-07 03:29:47', 0),
('69f2fe02c59', 'IN000090', 'RQ000092', 0, 210000, '2026-04-30', 0, '2026-05-07 03:30:33', 0),
('69fc518a322', 'IN000091', 'RQ000094', 0, 228129, '2026-05-07', 0, '2026-05-07 08:47:06', 0),
('69fd4b6e9f7', 'IN000092', 'RQ000095', 0, 114387, '2026-05-08', 0, '2026-05-08 02:33:18', 0),
('6a02d5a9959', 'IN000093', 'RQ000096', 0, 116581, '2026-05-12', 0, '2026-05-12 07:24:25', 0),
('6a0d6cb9e57', 'IN000094', 'RQ000098', 0, 60968, '2026-05-20', 0, '2026-05-20 08:11:37', 0),
('6a193e874a4', 'IN000095', 'RQ000099', 0, 12000, '2026-05-29', 0, '2026-05-29 07:21:43', 0),
('6a1a8e5c0d0', 'IN000096', 'RQ000097', 0, 16839, '2026-05-30', 0, '2026-05-30 07:14:36', 0),
('6a2284c28a2', 'IN000097', 'RQ000103', 0, 12000, '2026-06-05', 0, '2026-06-05 08:11:46', 0),
('6a228afdcf5', 'IN000098', 'RQ000100', 0, 12000, '2026-06-05', 0, '2026-06-05 08:38:21', 0),
('6a23d3b580d', 'IN000099', 'RQ000102', 0, 170400, '2026-06-06', 0, '2026-06-06 08:00:53', 0),
('6a2a66a3140', 'IN000100', 'RQ000104', 0, 129400, '2026-06-11', 0, '2026-06-11 07:41:23', 0),
('6a3a28b55d0', 'IN000101', 'RQ000107', 0, 58200, '2026-06-23', 0, '2026-06-23 06:33:25', 0),
('6a421013e9a', 'IN000102', 'RQ000109', 0, 16600, '2026-06-29', 0, '2026-06-29 06:26:27', 0),
('6a45dcc618d', 'IN000103', 'RQ000108', 0, 0, '2026-07-02', 0, '2026-07-02 03:36:38', 0),
('6a72e9cc8e3', 'IN000104', 'RQ000110', 0, 178065, '2026-08-05', 0, '2026-08-05 07:44:12', 0),
('6a801cec2ba', 'IN000105', 'RQ000111', 0, 103226, '2026-08-15', 0, '2026-08-15 08:01:48', 0),
('6aa4ed963ab', 'IN000106', 'RQ000113', 0, 12000, '2026-09-12', 0, '2026-09-12 06:13:42', 0);

-- --------------------------------------------------------

--
-- Struktur dari tabel `apikeuangan_buktitransaksiterkumpul`
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
-- Struktur dari tabel `apikeuangan_detailjurnalpemasukan`
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
-- Struktur dari tabel `apikeuangan_detailjurnalpengeluaran`
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
-- Struktur dari tabel `apikeuangan_jurnaloperasioanl`
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
-- Struktur dari tabel `apikeuangan_jurnalpemasukan`
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
-- Struktur dari tabel `apikeuangan_jurnalpengeluaran`
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
-- Struktur dari tabel `apikeuangan_jurnaltranfer`
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
-- Struktur dari tabel `barang`
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

--
-- Dumping data untuk tabel `barang`
--

INSERT INTO `barang` (`KODEBARANG`, `MERKBARANG`, `UNIT`, `UKURAN`, `IP`, `MASK`, `MACADDRESS`, `HARGABELI`) VALUES
('BR000001', 'MR000001', 1, NULL, NULL, NULL, 'B0:53:65:08:6E:6F', NULL),
('BR000002', 'MR000002', 1, NULL, NULL, NULL, '123123', NULL),
('BR000003', 'MR000003', 1, NULL, NULL, NULL, '44:22:95:31:A3:48', NULL),
('BR000004', 'MR000004', 1, NULL, NULL, NULL, 'B0:30:55:EF:8D:F0', NULL),
('BR000005', 'MR000005', 1, NULL, NULL, NULL, 'B0:53:65:01:7C:0F', NULL),
('BR000006', 'MR000006', 1, NULL, NULL, NULL, 'E0:A1:CE:CC:FA:6A', NULL),
('BR000007', 'MR000007', 1, NULL, NULL, NULL, '', NULL),
('BR000008', 'MR000008', 1, NULL, NULL, NULL, '', NULL),
('BR000009', 'MR000009', 1, NULL, NULL, NULL, '', NULL),
('BR000010', 'MR000010', 1, NULL, NULL, NULL, '', NULL),
('BR000011', 'MR000011', 1, NULL, NULL, NULL, '', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `beritainfo`
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
-- Struktur dari tabel `biaya_tagihan`
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
-- Dumping data untuk tabel `biaya_tagihan`
--

INSERT INTO `biaya_tagihan` (`IDBIAYA`, `IDPELANGGAN`, `IDPERMINTAAN`, `BIAYAPASANG`, `BIAYABULANAN`, `BIAYALAINLAIN`, `TOTALBIAYA`, `TGLINSERT`) VALUES
('IN000001', 'PE000012', 'RQ000012', 10000, 280333, 10000, 300333, '2024-04-01 03:31:13'),
('IN000002', 'PE000011', 'RQ000011', 0, 112667, 12000, 124667, '2024-04-04 02:34:03'),
('IN000003', 'PE000013', 'RQ000013', 0, 26000, 12000, 38000, '2024-04-24 07:08:42'),
('IN000004', 'PE000008', 'RQ000008', 0, 22000, 12000, 34000, '2024-04-24 07:09:51'),
('IN000005', 'PE000010', 'RQ000010', 0, 26000, 11000, 37000, '2024-04-24 07:11:27'),
('IN000006', 'PE000001', 'RQ000001', 0, 99355, 12000, 111355, '2024-05-03 02:55:42'),
('IN000007', 'PE000002', 'RQ000002', 0, 99355, 12000, 111355, '2024-05-03 02:55:58'),
('IN000008', 'PE000003', 'RQ000003', 0, 117419, 12000, 129419, '2024-05-03 02:56:13'),
('IN000009', 'PE000004', 'RQ000004', 0, 149032, 12000, 161032, '2024-05-03 02:56:29'),
('IN000010', 'PE000006', 'RQ000006', 0, 135484, 12000, 147484, '2024-05-03 02:56:49'),
('IN000011', 'PE000007', 'RQ000007', 0, 0, 12000, 12000, '2024-05-03 03:04:18'),
('IN000012', 'PE000007', 'RQ000007', 0, 0, 12000, 12000, '2024-05-03 03:04:18'),
('IN000013', 'PE000009', 'RQ000009', 0, 0, 12000, 12000, '2024-05-03 03:06:44'),
('IN000014', 'PE000009', 'RQ000009', 0, 0, 12000, 12000, '2024-05-03 03:06:44'),
('IN000015', 'PE000005', 'RQ000005', 0, 117419, 12000, 129419, '2024-05-03 03:20:06'),
('IN000016', 'PE000014', 'RQ000014', 0, 99355, 12000, 111355, '2024-05-03 03:23:16'),
('IN000017', 'PE000015', 'RQ000015', 0, 145484, 12000, 157, '2024-05-07 02:34:00'),
('IN000018', 'PE000016', 'RQ000016', 0, 113226, 12000, 125226, '2024-05-04 03:12:27'),
('IN000019', 'PE000018', 'RQ000018', 0, 0, 12000, 12000, '2024-05-04 03:18:17'),
('IN000020', 'PE000019', 'RQ000019', 0, 88710, 11000, 99710, '2024-05-06 07:23:57'),
('IN000021', 'PE000017', 'RQ000017', 0, 75484, 104000, 179484, '2024-05-13 00:23:24'),
('IN000022', 'PE000023', 'RQ000023', 0, 0, 0, 0, '2024-07-03 19:16:29'),
('IN000023', 'PE000024', 'RQ000024', 0, 0, 0, 0, '2024-07-03 19:18:08'),
('IN000024', 'PE000032', 'RQ000028', 0, 118167, 12000, 130, '2024-09-11 06:00:33'),
('IN000025', 'PE000032', 'RQ000028', 0, 118167, 12000, 130, '2024-09-11 06:00:33'),
('IN000026', 'PE000032', 'RQ000028', 0, 118167, 12000, 130, '2024-09-11 06:00:33'),
('IN000027', 'PE000032', 'RQ000028', 0, 118167, 12000, 130, '2024-09-11 06:00:33'),
('IN000028', 'PE000032', 'RQ000028', 0, 118167, 12000, 130, '2024-09-11 06:00:33'),
('IN000029', 'PE000032', 'RQ000028', 0, 118167, 12000, 130, '2024-09-11 06:00:33'),
('IN000030', 'PE000032', 'RQ000028', 0, 118167, 12000, 130, '2024-09-11 06:00:33'),
('IN000031', 'PE000033', 'RQ000029', 0, 13355, 12000, 25355, '2024-08-28 03:30:14'),
('IN000032', 'PE000025', 'RQ000025', 0, 4839, 12000, 16839, '2024-08-30 01:19:05'),
('IN000033', 'PE000034', 'RQ000030', 0, 0, 312000, 312000, '2024-10-18 01:55:36'),
('IN000035', 'PE000036', 'RQ000032', 0, 62903, 12000, 74903, '2024-10-18 01:58:00'),
('IN000036', 'PE000036', 'RQ000032', 0, 62903, 12000, 74903, '2024-10-18 01:58:03'),
('IN000037', 'PE000037', 'RQ000033', 0, 0, 72000, 72000, '2024-11-13 08:09:51'),
('IN000038', 'PE000039', 'RQ000035', 0, 62333, 37000, 99333, '2024-11-13 08:18:29'),
('IN000039', 'PE000041', 'RQ000037', 0, 60323, 12000, 72323, '2024-12-14 04:44:57'),
('IN000040', 'PE000042', 'RQ000038', 0, 17742, 12000, 29742, '2024-12-26 03:05:58'),
('IN000041', 'PE000043', 'RQ000039', 0, 0, 0, 0, '2025-01-01 03:24:07'),
('IN000042', 'PE000044', 'RQ000040', 12000, 0, 0, 12000, '2025-01-03 01:48:09'),
('IN000043', 'PE000045', 'RQ000041', 12000, 0, 0, 12000, '2025-01-03 01:48:09'),
('IN000044', 'PE000046', 'RQ000042', 12000, 0, 0, 12000, '2025-01-03 01:48:09'),
('IN000045', 'PE000047', 'RQ000043', 12000, 0, 0, 12000, '2025-01-03 01:48:09'),
('IN000046', 'PE000048', 'RQ000044', 12000, 0, 0, 12000, '2025-01-03 01:48:09'),
('IN000047', 'PE000049', 'RQ000045', 12000, 0, 0, 12000, '2025-01-03 02:09:30'),
('IN000048', 'PE000051', 'RQ000047', 0, 90357, 12000, 102357, '2025-02-05 03:36:59'),
('IN000049', 'PE000052', 'RQ000048', 0, 21214, 12000, 33214, '2025-02-25 08:03:14'),
('IN000050', 'PE000053', 'RQ000049', 0, 115742, 12000, 127742, '2025-03-05 01:30:29'),
('IN000051', 'PE000054', 'RQ000050', 0, 21290, 52000, 73290, '2025-03-25 02:51:44'),
('IN000052', 'PE000055', 'RQ000051', 0, 90000, 12000, 102000, '2025-04-12 02:42:35'),
('IN000053', 'PE000056', 'RQ000052', 0, 32200, 12000, 44200, '2025-04-23 09:19:11'),
('IN000054', 'PE000057', 'RQ000053', 50000, 77000, 80000, 207000, '2025-06-09 10:31:48'),
('IN000055', 'PE000058', 'RQ000054', 50000, 73333, 75000, 198333, '2025-06-10 09:00:24'),
('IN000056', 'PE000059', 'RQ000055', 43243423, 0, 32432423, 75675846, '2025-06-10 09:25:33'),
('IN000057', '', '', 0, 0, 0, 0, '2025-06-10 09:25:34'),
('IN000058', 'US000001', 'RQ000056', 37, 105000, 81, 105118, '2025-06-12 13:32:14'),
('IN000059', 'PE000060', 'RQ000057', 4, 90000, 70, 90074, '2025-06-12 16:02:47'),
('IN000060', 'PE000063', 'RQ000061', 76, 73667, 24, 73767, '2025-06-12 19:13:41'),
('IN000061', 'PE000062', 'RQ000060', 37, 164333, 25, 164395, '2025-06-12 19:14:02'),
('IN000062', 'PE000012', 'RQ000012', 0, 154667, 0, 0, '2025-06-14 01:17:41'),
('IN000063', 'PE000061', 'RQ000059', 0, 0, 0, 0, '2025-06-16 03:06:18'),
('IN000064', '', '', 0, 0, 0, 0, '2025-06-16 03:06:19'),
('IN000065', 'US000001', 'RQ000056', 0, 81667, 0, 0, '2025-06-16 03:06:38'),
('IN000066', '', '', 0, 0, 0, 0, '2025-06-16 03:06:39'),
('IN000067', 'US000005', 'RQ000065', 5, 32200, 42, 32247, '2025-06-22 17:43:54'),
('IN000068', 'PE000065', 'RQ000067', 98, 20000, 42, 20140, '2025-06-25 19:37:02'),
('IN000069', 'PE000069', 'RQ000072', 85, 0, 80, 165, '2025-06-29 15:39:50'),
('IN000070', 'PE000067', 'RQ000070', 0, 261935, 12000, 273935, '2025-07-03 07:34:14'),
('IN000071', '', '', 0, 0, 0, 0, '2025-07-03 07:34:15'),
('IN000072', 'US000003', 'RQ000063', 0, 146903, 0, 0, '2025-07-08 00:57:33'),
('IN000073', 'PE000070', 'RQ000073', 0, 81613, 12000, 93613, '2025-07-08 07:04:41'),
('IN000074', '', '', 0, 0, 0, 0, '2025-07-08 07:04:42'),
('IN000075', 'PE000073', 'RQ000076', 0, 3548, 12000, 15548, '2025-07-31 07:35:19'),
('IN000076', '', '', 0, 0, 0, 0, '2025-07-31 07:35:19'),
('IN000077', 'PE000071', 'RQ000074', 0, 96774, 412000, 508774, '2025-08-11 07:51:31'),
('IN000078', '', '', 0, 0, 0, 0, '2025-08-11 07:51:32'),
('IN000079', 'PE000074', 'RQ000077', 0, 0, 12000, 12000, '2025-08-12 02:34:29'),
('IN000080', 'PE000075', 'RQ000078', 0, 97935, 22000, 119935, '2025-10-09 08:16:29'),
('IN000081', '', '', 0, 0, 0, 0, '2025-10-09 08:16:30'),
('IN000082', 'PE000078', 'RQ000081', 0, 14516, 12000, 26516, '2025-10-28 06:45:43'),
('IN000083', '', '', 0, 0, 0, 0, '2025-10-28 06:45:44'),
('IN000084', 'PE000079', 'RQ000082', 0, 73333, 12000, 85333, '2025-11-10 09:31:38'),
('IN000085', 'PE000084', 'RQ000087', 0, 129097, 62000, 191097, '2026-01-02 06:50:03'),
('IN000086', '', '', 0, 0, 0, 0, '2026-01-02 06:50:04'),
('IN000087', 'PE000085', 'RQ000088', 0, 150000, 12000, 162000, '2026-05-07 03:29:46'),
('IN000088', '', '', 0, 0, 0, 0, '2026-02-07 07:28:49'),
('IN000089', 'PE000088', 'RQ000091', 0, 150000, 12000, 162000, '2026-05-07 03:29:47'),
('IN000090', 'PE000089', 'RQ000092', 0, 198000, 12000, 210000, '2026-05-07 03:30:32'),
('IN000091', 'PE000091', 'RQ000094', 0, 116129, 112000, 228129, '2026-05-07 08:47:06'),
('IN000092', 'PE000092', 'RQ000095', 0, 102387, 12000, 114387, '2026-05-08 02:33:18'),
('IN000093', 'PE000093', 'RQ000096', 0, 84581, 32000, 116581, '2026-05-12 07:24:25'),
('IN000094', 'PE000095', 'RQ000098', 0, 48968, 12000, 60968, '2026-05-20 08:11:37'),
('IN000095', 'PE000096', 'RQ000099', 0, 0, 12000, 12000, '2026-05-29 07:21:43'),
('IN000096', 'PE000094', 'RQ000097', 0, 4839, 12000, 16839, '2026-05-30 07:14:36'),
('IN000097', 'PE000100', 'RQ000103', 0, 0, 12000, 12000, '2026-06-05 08:11:46'),
('IN000098', 'PE000097', 'RQ000100', 0, 0, 12000, 12000, '2026-06-05 08:38:21'),
('IN000099', 'PE000099', 'RQ000102', 0, 158400, 12000, 170400, '2026-06-06 08:00:53'),
('IN000100', 'PE000101', 'RQ000104', 0, 87400, 42000, 129400, '2026-06-11 07:41:23'),
('IN000101', 'PE000104', 'RQ000107', 0, 46200, 12000, 58200, '2026-06-23 06:33:25'),
('IN000102', 'PE000106', 'RQ000109', 0, 4600, 12000, 16600, '2026-06-29 06:26:27'),
('IN000103', 'PE000105', 'RQ000108', 0, 0, 0, 0, '2026-07-02 03:36:38'),
('IN000104', 'PE000107', 'RQ000110', 0, 166065, 12000, 178065, '2026-08-05 07:44:12'),
('IN000105', 'PE000108', 'RQ000111', 0, 71226, 32000, 103226, '2026-08-15 08:01:48'),
('IN000106', 'PE000110', 'RQ000113', 0, 0, 12000, 12000, '2026-09-12 06:13:42');

-- --------------------------------------------------------

--
-- Struktur dari tabel `cabang`
--

CREATE TABLE `cabang` (
  `ID` int(4) NOT NULL,
  `CABANG` varchar(6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `cabang`
--

INSERT INTO `cabang` (`ID`, `CABANG`) VALUES
(1, 'SAWOO');

-- --------------------------------------------------------

--
-- Struktur dari tabel `cron`
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
-- Struktur dari tabel `detail_aktivasi_pppoe`
--

CREATE TABLE `detail_aktivasi_pppoe` (
  `IDDETAILAKTIVASIPPPOE` varchar(11) NOT NULL,
  `IDPENGGUNA` varchar(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `detail_aktivasi_queue`
--

CREATE TABLE `detail_aktivasi_queue` (
  `IDDETAILAKTIVASIQUEUE` varchar(11) NOT NULL,
  `IDPENGGUNA` varchar(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `detail_jurnal_harian`
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
-- Struktur dari tabel `detail_komisi_mitra`
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
-- Struktur dari tabel `ip_kosong`
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
-- Struktur dari tabel `jabatan`
--

CREATE TABLE `jabatan` (
  `ID` int(4) NOT NULL,
  `IDLEVEL` int(4) NOT NULL,
  `JABATAN` varchar(100) NOT NULL,
  `KET` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `jabatan`
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
-- Struktur dari tabel `jenka_paket`
--

CREATE TABLE `jenka_paket` (
  `ID` varchar(5) NOT NULL,
  `PARENT_PAKET` varchar(3) NOT NULL,
  `NAME` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `jenka_paket`
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
-- Struktur dari tabel `jurnalharian`
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
-- Struktur dari tabel `kategori_barang`
--

CREATE TABLE `kategori_barang` (
  `KODEKATEGORIBARANG` varchar(11) NOT NULL,
  `BARANG` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `kategori_perangkat_jaringan`
--

CREATE TABLE `kategori_perangkat_jaringan` (
  `id` int(2) NOT NULL,
  `nama_perangkat` varchar(50) NOT NULL,
  `fungsi_singkat` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data untuk tabel `kategori_perangkat_jaringan`
--

INSERT INTO `kategori_perangkat_jaringan` (`id`, `nama_perangkat`, `fungsi_singkat`) VALUES
(0, 'Default', 'Default'),
(1, 'OLT Bedingin', '-');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kode_kontrol_distribusi`
--

CREATE TABLE `kode_kontrol_distribusi` (
  `kode` varchar(20) NOT NULL,
  `nama` varchar(100) DEFAULT NULL,
  `deskripsi` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data untuk tabel `kode_kontrol_distribusi`
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
-- Struktur dari tabel `komisi`
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
-- Struktur dari tabel `komisi_mitra`
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
-- Struktur dari tabel `laporan_pemasangan_wifi`
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
-- Dumping data untuk tabel `laporan_pemasangan_wifi`
--

INSERT INTO `laporan_pemasangan_wifi` (`IDREPORT`, `IDPENGGUNA`, `IDPERMINTAAN`, `STATUSPASANG`, `BRG_INDOOR`, `BRG_OUTDOOR`, `SINYAL`, `JENIS`, `TESTUP`, `TESTDOWN`, `STATUS`, `BTS`, `SSID`, `FOTOTOWER`, `FOTOKABEL`, `FOTOROOTER`, `FOTOSPEED`, `FOTOFORMULIR`, `FOTOTTDFORMULIR`, `PINGGATEWAY`, `PINGGOOGLE`, `IPADDR`, `MACADDR_ANTENA`, `MACADDR_ROOTER`, `SNROOTER_FIBER`, `NOMOR_ODP`, `NOMOR_PORT_ODP`, `NOMOR_PORT_OLT`, `SIGNAL_WIRELESS`, `SIGNAL_KABEL`, `HISTORYROOTER`, `HISTORYANTENA`, `HISTORYDETAIL`, `LOKASIPEMANCAR`, `KETERANGAN`, `IDODPLOCATION`) VALUES
('6685a38d76fa5', 'PE000023', 'RQ000023', NULL, NULL, NULL, NULL, 'KABEL', '', '', NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, '', '', ''),
('6685a3f014333', 'PE000024', 'RQ000024', NULL, NULL, NULL, NULL, 'KABEL', '', '', NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, '', '', ''),
('6774b557261be', 'PE000043', 'RQ000039', NULL, NULL, NULL, NULL, 'KABEL', '', '', NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, '', '', ''),
('677741d96012d', 'PE000044', 'RQ000040', NULL, NULL, NULL, NULL, 'KABEL', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', NULL, '', NULL, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '', '', ''),
('677741d973df3', 'PE000045', 'RQ000041', NULL, NULL, NULL, NULL, 'KABEL', '', '', NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, '', '', ''),
('677741d98630c', 'PE000046', 'RQ000042', NULL, NULL, NULL, NULL, 'KABEL', '', '', NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, '', '', ''),
('677741d9985b7', 'PE000047', 'RQ000043', NULL, NULL, NULL, NULL, 'KABEL', '', '', NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, '', '', ''),
('677741d9a3ba6', 'PE000048', 'RQ000044', NULL, NULL, NULL, NULL, 'KABEL', '', '', NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, '', '', ''),
('677746da42ad0', 'PE000049', 'RQ000045', NULL, NULL, NULL, NULL, 'KABEL', '', '', NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, '', '', ''),
('lp660a29b14ee3a', 'PE000011', 'RQ000011', NULL, NULL, NULL, '', 'KABEL', '23.5', '22.5', NULL, NULL, NULL, NULL, NULL, 'effe7fcb5e00be31a32bb85fff8e1665.jpeg', 'd4e5841d61b922ddd55978616a5d5394.jpeg', '3aedb878bb40aaa9725013e67fd9c4ab.jpeg', '145e086ad8efcc51687d05dcee7306b4.jpeg', '19', '25', '10.44.2.76', '', '', 'CIOT03086E6F', 'BDG 1 ODP 2', '1', '1/2/1', '-21.5', '', NULL, NULL, NULL, '', 'aktivasi pelanggan selesai', ''),
('lp660a2a644f404', 'PE000012', 'RQ000012', NULL, NULL, NULL, '', 'KABEL', '11111', '11111', NULL, NULL, NULL, NULL, NULL, '8590c2c7c237467877d4a964648e5c41.png', 'ada15550a1d299fd6e95c729027147cc.png', '50b130a3b6af9d242c00407cc19d160b.png', '7a248e302d5e9e477320b847adefd493.png', '11111', '11111', '11111', '', '', '11111', '11111', '11111', '11111', '11111', '', NULL, NULL, NULL, 'tes', 'tes', ''),
('lp66275c7167c4c', 'PE000010', 'RQ000010', NULL, NULL, NULL, '', 'KABEL', '9,24', '9,22', NULL, NULL, NULL, NULL, NULL, '23fd2acaa8a9b0b540edf76903bffd58.jpg', '2705bdf2b9fcc8ec76810248ecaca098.png', '8e35e8e13b33ffce8af7262e54abd040.jpg', '5dd6040314230e1d700a30b4541e18be.jpg', '30', '28', '10.110.1.5', '', '', 'CIOT1331A348', 'BDG 1 ODP 6', '1', '1', '-24', '', NULL, NULL, NULL, 'Olt Sambit', 'Aktivasi pelanggan selesai ', ''),
('lp66275d6414ea4', 'PE000013', 'RQ000013', NULL, NULL, NULL, '', 'KABEL', '9,24', '9,22', NULL, NULL, NULL, NULL, NULL, '13d7824025ad9338775cf650950e3f5d.jpg', '1a232e9cbdee29cf397a9d5e28cb8c2f.png', '7b13ec9320684aba132835dc5f9d73ef.jpg', 'e16c228e4e785842a1b5e7cbe4c1186b.jpg', '28', '25', '10.110.1.6', '', '', 'CIOT11EF8DF0', 'BDG 1 ODP 6', '2', '1/1/1', '-24', '', NULL, NULL, NULL, 'Olt Whusnet', 'Aktivasi pelanggan selesai ', ''),
('lp66275f9f8bbdd', 'PE000008', 'RQ000008', NULL, NULL, NULL, '', 'KABEL', '9,26', '9,23', NULL, NULL, NULL, NULL, NULL, '849b71d9bf6dd0fd7b0c563e143e9152.jpg', '83cd36ce788f2327b58f0c486d8acf5e.png', 'd1c914132ac2853298323bcce61e8a57.jpg', 'cb2599117299d6332d9024cd10028bfb.jpg', '29', '27', '10.110.1.2', '', '', 'CIOT03017C0F', 'BDG 4 ODP 16', '1', '1/1/4', '-24', '', NULL, NULL, NULL, 'Olt Sambit', 'Aktivasi pelanggan selesai ', ''),
('lp663443d415d30', 'PE000001', 'RQ000001', NULL, NULL, NULL, '', 'KABEL', '23.5', '22.5', NULL, NULL, NULL, NULL, NULL, '06d9b2ed2bd4a2490b892969a0649642.png', '08ad0263082c92518a93385661837f1a.jpg', '29a9a3a6df2b5790814aa5d2130fa4f2.jpeg', 'afd34dae2bea837e9aa2238ab1278d4f.png', '5', '35', '10.101.2.97', '', '', '22040P6018614', '3', '1', '3', '-23.00', '', NULL, NULL, NULL, 'OLT SAMBIT', 'SUDAH DIPASANG', ''),
('lp66344508038c8', 'PE000002', 'RQ000002', NULL, NULL, NULL, '', 'KABEL', '23.5', '22.5', NULL, NULL, NULL, NULL, NULL, '04496d7fd026d8bbbb10eb1fe1075290.png', 'fc0db301d80e45955fddab55acd5384c.jpg', 'c4c059176149e9237a00ed48408759ca.jpeg', '393717f35bcaf40ee89250f5cc7293af.png', '19', '25', '10.44.0.1', '', '', 'ZICG2312818D', 'BDG 4 ODP 11', '1', '1/1/4', '-22', '', NULL, NULL, NULL, 'OLT SAMBIT', 'SUDAH DIPASANG', ''),
('lp663446055a485', 'PE000003', 'RQ000003', NULL, NULL, NULL, '', 'KABEL', '23.5', '22.5', NULL, NULL, NULL, NULL, NULL, '6febd7c31bfedd19582b9f5233dc3668.jpeg', 'c8783fec4f2edc92854ecc729c479df9.jpg', 'c1b38296bb53bd4ebd3b07e35c4ed671.jpeg', '61a0f6a54cac7bb631a14c436198d9d0.jpeg', '19', '25', '10.44.2.65', '', '', 'CIOT03063BBF', 'BDG 4 ODP 13', '1', '1/1/4', '-19.1', '', NULL, NULL, NULL, 'OLT SAMBIT', 'SUDAH TERPASANG', ''),
('lp6634485b2f7a4', 'PE000004', 'RQ000004', NULL, NULL, NULL, '', 'KABEL', '28,0', '26,3', NULL, NULL, NULL, NULL, NULL, '9a53675042391f1084df15986b81da15.jpeg', '7f3f529c4b3f2b9e8b542085b7638b77.jpg', '14083f6e57421e1e747db492028371c5.jpeg', 'a9b5b4c40af3a536ce09da42a10d2405.jpeg', '19', '25', '10.44.5.60', '', '', 'ZICG232E2856', 'ODP BEDINGIN', '1', '1/1/6', '-22,6', '', NULL, NULL, NULL, 'OLT SAMBIT', 'SUDAH DIPASANG', ''),
('lp6634498819570', 'PE000006', 'RQ000006', NULL, NULL, NULL, '', 'KABEL', '23.5', '22.5', NULL, NULL, NULL, NULL, NULL, '3e180fed16c32a15e392dc2a096682a5.jpeg', '36ea03c96dca668846209976ca01c4f3.jpg', '4dcc0437b6dd7219dcc5d5eb384e38df.jpeg', 'fa7995c9f7b1597588104cfd42c49179.jpeg', '19', '25', '10.44.3.38', '', '', 'CIOT030436DF', 'BDG 7 ODP 4', '2', '1/2/7', '-24', '', NULL, NULL, NULL, 'OLT SAMBIT', 'SUDAH DIPASANG', ''),
('lp6634541f06e0b', 'PE000007', 'RQ000007', NULL, NULL, NULL, '', 'KABEL', '23.5', '22.5', NULL, NULL, NULL, NULL, NULL, '003515059dc39a45727a4ef4a417779a.png', 'f01021ad84bad922230ea1f6e0154d07.jpg', 'd7b473d6acd69aeeca4cfecd22265b65.jpg', '321cefb02e06de15db34fff8e56e3f1f.jpeg', '1', '35', '10.3.5.32', '', '', 'ZTEGCA22A57B', '3', '3', '1', '-20', '', NULL, NULL, NULL, 'OLT SAMBIT', 'SUDAH DIPASANG', ''),
('lp663454939aa32', 'PE000009', 'RQ000009', NULL, NULL, NULL, '', 'KABEL', '23.5', '22.5', NULL, NULL, NULL, NULL, NULL, '890e19daf4ae709be81440a3645fdbb8.jpeg', '000c527e89924f419b40674f298d7037.jpg', '0f6035640d2c7c7796c55117967acb57.jpg', 'b2486964524fa47d506f073ef2385e09.jpeg', '1', '25', '10.3.5.32', '', '', 'ZTEGCA22A57B', '3', '2', '6', '-20', '', NULL, NULL, NULL, 'OLT SAMBIT', 'SUDAH DIPASANG', ''),
('lp663457b38eb38', 'PE000005', 'RQ000005', NULL, NULL, NULL, '', 'KABEL', '23.5', '22.5', NULL, NULL, NULL, NULL, NULL, '285f3f6e4765bc9aa66a4ec402a17a96.jpeg', '5d75a272f5dd0fb52506a6dae886f0b4.jpg', 'a726f35e9dd5855600a6f65824509f1f.jpg', '3fa05e97c69a59c581ed25b835a54e04.png', '5', '25', '10.105.2.21', NULL, '', 'ZTEGCA22A57B', '3', '4', '1', '-20', '', NULL, NULL, NULL, 'OLT SAMBIT', 'SUDAH DIPASANG', ''),
('lp663458944806f', 'PE000014', 'RQ000014', NULL, NULL, NULL, '', 'KABEL', '23.5', '22.5', NULL, NULL, NULL, NULL, NULL, '881524adff2c92f4979473948a2dd111.jpeg', 'bbca234d1abc3a3f0191927527b84bc2.jpg', '049b4fd3a93507e3e0a9bb35ccbe32d9.jpg', 'b0cd8cbd3a337ad7e76c33285a65daa0.png', '1', '25', '10.3.5.32', '', '', '22040P6018614', '13', '3', '5', '-20', '', NULL, NULL, NULL, 'OLT SAMBIT', 'SUDAH DIPASANG', ''),
('lp66345956b9680', 'PE000015', 'RQ000015', NULL, NULL, NULL, '', 'KABEL', '23.5', '22.5', NULL, NULL, NULL, NULL, NULL, '7167028a5466dc35565ed13ea1639889.jpeg', '1e90d696e781557081dd8467a925f6b8.jpg', 'f4d1b568762d39d84de0ddb36502d01f.jpeg', 'f128c6c84721b6cd676601d85ebbbcb9.jpeg', '5', '25', '10.3.5.32', '', '', 'ZTEGCA22A57B', '13', '3', '1', '-20', '', NULL, NULL, NULL, 'OLT SAMBIT', 'SUDAH TERPASANG', ''),
('lp6634c97818f28', 'PE000016', 'RQ000016', NULL, NULL, NULL, '', 'KABEL', '9.89', '9.99', NULL, NULL, NULL, NULL, NULL, 'd25c0285eb83a3801fb32a74ff2b37f5.jpg', '4631526dd9008d64f27d2df6a41c38eb.jpg', 'e76d7f840b168a3eafe2d59e7cb44da1.jpg', '99c5eca0f00745edf302b1b037ce34b8.jpg', '11', '23', '10.110.1.8', '', '', 'cI0T12c263fb', 'BDG 8 ODP 10', '1', '1/2/8', '-20', '', NULL, NULL, NULL, 'sambit', 'koneksi sudah on sesui permintaan pelanggan', ''),
('lp66359087ccae6', 'PE000018', 'RQ000018', NULL, NULL, NULL, '', 'KABEL', '23.5', '22.5', NULL, NULL, NULL, NULL, NULL, '25e2d030379002107676721f69a40f45.jpg', '5ed332d461e55b43ac328901feab23e7.jpg', 'e39a02a15ca25261052fef979b1c5774.jpg', '8d29869bc36144880d465387185a8795.png', '1', '5', '192.168.24.84', '', '', 'ZTEGCA22A57B', '13', '1', '1', '-20', '', NULL, NULL, NULL, 'OLT SAMBIT', 'SUDAH DIPASANG', ''),
('lp663593c99be01', 'PE000019', 'RQ000019', NULL, NULL, NULL, '', 'KABEL', '23.5', '22.5', NULL, NULL, NULL, NULL, NULL, 'e515f06209602080bdc2a3df0ca65866.jpeg', '1f95012ca01f436f6ccedf53855e9515.jpg', '592e52cda42de4627b15960b9cf4f169.jpg', '943a25358cffb1e13bce336b05b30f64.jpeg', '30', '30', '10.3.5.156', '', '', 'ZTEGCA22A57B', '13', '1', '1', '-20', '', NULL, NULL, NULL, 'OLT SAMBIT', 'SUDAH TERPASANG', ''),
('lp663f3da113c8f', 'PE000017', 'RQ000017', NULL, NULL, NULL, '', 'KABEL', '13', '12', NULL, NULL, NULL, NULL, NULL, 'a2338ddae32dbf022770443bc1accff0.jpg', '79a233bef0d42dbfd6e1bd5238c9f14b.jpg', '8bb443115dfa5510dae59bd60856e752.jpg', '414a55f0f5a24e172e10e319c8cef5a5.jpg', '14', '12', '10.110.0.57', '', '', 'CIOT15AC9360', '16', '6', '16', '12', '', NULL, NULL, NULL, '', 'Tambah box', ''),
('lp669c8a66cc725', 'PE000032', 'RQ000028', NULL, NULL, NULL, '', 'KABEL', '5000', '5000', NULL, NULL, NULL, NULL, NULL, '67a12fabd76505dbd3209b47af685d4e.jpeg', '8aae4dd626c224797d6d9a00a834d45f.png', 'd154f548d9b7ef52d7e48adc0894b171.jpeg', 'be23a60e1e7d0fb2253b3416a3a3a949.jpeg', '5', '25', 'RQ000028@MeidianaRiska', '', '', 'ZTEGD1DB8569', 'BDG 2 ODP 5', '2', '2', '-21', '', NULL, NULL, NULL, 'OLT BEDINGIN', 'Belum ada', ''),
('lp66ce8e5f279fd', 'PE000033', 'RQ000029', NULL, NULL, NULL, '', 'KABEL', '12.9', '12.6', NULL, NULL, NULL, NULL, NULL, 'e53bee21a2bcc4e62f4886e019e4055a.jpg', '5ccd357b8acdee94f27e7e8ce22c04b9.jpg', '1e8232d49afe1da30adf0d3539f00344.jpg', 'ec753200d4399ab7a186e1512a986089.jpg', '3', '25', 'BedinginNurHayati', '', '', 'ZTEGC85290B5', 'BDG 5 ODP 1', '2', '1/1/5', '-12', '', NULL, NULL, NULL, 'OLT SAMBIT', 'Aktifasi pelanggan selesai ', ''),
('lp66ced7c346872', 'PE000025', 'RQ000025', NULL, NULL, NULL, '', 'KABEL', '10', '10', NULL, NULL, NULL, NULL, NULL, '426971d4f2b19aa2245a1e4562e9ab8c.jpg', '0844fa66b81b641c0f8b0712139f41de.png', '65e34bf7a535b7f169e92dde45dd7b01.jpg', '7a15871c3de8fe280cccbfe8f4a63a29.jpg', '1', '25', '10.3.5.32', '', '', 'ZICG2335BE18 ', '5', '1', '5', '-25', '', NULL, NULL, NULL, 'OLT SAMBIT', 'MIGRASI WHUSNET', ''),
('lp670f2d7b9eb22', 'PE000036', 'RQ000032', NULL, NULL, NULL, '', 'KABEL', '15.4', '13.9', NULL, NULL, NULL, NULL, NULL, '732a395891fdf15c2f4c36489a49c984.jpg', '4ea88d63d4f2a650657a18beb9cd7cf5.jpg', 'a91f5f187a80d2a1a8dc7b509d617be7.jpg', '7b8ea18bf4c4ad4dc4fe32bb3d2a25ca.jpg', '5', '32', 'RQ000032@WahidAsariWringinanom', '', '', 'ZXICFF07AAE8', 'BDG 4 ODP 12', '1', '1/2/4', '-11.8', '', NULL, NULL, NULL, 'OLT SAMBIT', 'aktifasi pelanggan selesai', ''),
('lp6711a70a53da5', 'PE000034', 'RQ000030', NULL, NULL, NULL, '', 'KABEL', '4.38', '4.68', NULL, NULL, NULL, NULL, NULL, 'f7bb77145ba37f13bc20415e886126c2.jpg', '6f7f2434c2e57ee1761c4b6613cdfeff.jpg', '95781dd663533e9c0f3077432f4182a9.jpg', '5dc86415af393bc7ca89948e9e435a11.jpg', '5', '39', 'RQ000030@HennySriKupuk', '', '', 'ZXICFF0EDC68', 'BDG 1 ODP 14', '1', '1/2/1', '-10.5', '', NULL, NULL, NULL, 'OLT SAMBIT', 'aktifasi pelanggan selesai', ''),
('lp673358a03692c', 'PE000037', 'RQ000033', NULL, NULL, NULL, '', 'KABEL', '4.64', '4.62', NULL, NULL, NULL, NULL, NULL, 'fe38e2a10244cf8e595e3a09f957790f.jpg', '4977917ef6a66e0aeb01fc59f75b9c21.jpg', '5782d59a62e0227fdee1c56c329d9f35.jpg', 'fad747c460c967174deb0e6e24ba6d45.jpg', '3', '20', 'RQ000033@IkaYohanaRatnasariSam', '', '', 'ZXICFF040018', 'BDG 4 ODP 14', '5', '1/2/4', '-22.5', '', NULL, NULL, NULL, 'OLT SAMBIT', 'aktifasi pelanggan selesai', ''),
('lp67335b216fa6c', 'PE000039', 'RQ000035', NULL, NULL, NULL, '', 'KABEL', '4.72', '4.82', NULL, NULL, NULL, NULL, NULL, '05644cad4391af859e98f4f530f99d10.jpg', 'dc336c8d5e969f3fdceda81ce94485de.jpg', 'd2e0b988801e0be18e33be5485d19a61.jpg', 'bc38e29dbb3fc8c902f2895dc982e486.jpg', '5', '20', 'RQ000035@LukmanAsifuddinCampur', '', '', 'ZTEGC85A9D1F', 'BDG 7 ODP 4', '3', '1/2/7', '-25. 2', '', NULL, NULL, NULL, 'OLT SAMBIT', 'aktifasi pelanggan selesai', ''),
('lp6758135bea476', 'PE000041', 'RQ000037', NULL, NULL, NULL, '', 'KABEL', '11,2', '11,4', NULL, NULL, NULL, NULL, NULL, '2e6a0c4fec0bc63a6928ad43fbf5765d.jpg', '221eea786ccca6a8409827a1ed854c1e.jpg', 'f3d14fc79e7929e35bb3043bdd638d70.jpg', '9923f2cbf5dee876b5fac5bc8346a653.jpg', '4', '27', 'RQ000037@FeryDwiMartdiawanKupu', '', '', 'ZTEGC883A1CE', '1', '5', '1/2/1:8', '20,3', '', NULL, NULL, NULL, '', 'Pemasangan selesai', ''),
('lp676bf153643fc', 'PE000042', 'RQ000038', NULL, NULL, NULL, '', 'KABEL', '11.28', '11.88', NULL, NULL, NULL, NULL, NULL, '89da6095f0836e40139b66f9d6d63e06.jpg', '33a528af91e724471b6c3b293b8d7508.jpg', '70e82c208ad356184f8bece5ea305972.jpg', 'e27bbed6bd5662766c921a465a57e031.jpg', '11', '23', 'RQ000038@AgusWardanaWringinano', '', '', 'ZTEGCE792F27', 'Bdg 4 odp 5', '1', '1/2/4:11', '-11', '', NULL, NULL, NULL, 'bedingin', 'aktivasi selesai', ''),
('lp679623a979a5e', 'PE000051', 'RQ000047', NULL, NULL, NULL, '', 'KABEL', '23,84', '17,52', NULL, NULL, NULL, NULL, NULL, 'cc7f2d9dc9f5d424d935b6f941aa5ec2.jpg', 'fa047221d0bb9782cbda8facc4a325a8.jpg', 'f47db3d844cd61395eae32d37c53414a.jpg', '8498eaf9e571724edffee28524944565.jpg', '7', '14', 'RQ000047@ImamChoirulAnamWringi', '', '', 'ZICG0384D2A5', '5', '5', '1', '-17', '', NULL, NULL, NULL, 'Bedingin', 'Selesai', ''),
('lp67b8893fcc27d', 'PE000052', 'RQ000048', NULL, NULL, NULL, '', 'KABEL', '49,6', '48,5', NULL, NULL, NULL, NULL, NULL, '34cee29fe5da022dbeddc6d1b4de6808.jpg', '2c71b7fad2c5820405c5c3a381f21bae.jpg', 'bed9c3e420597b98312bcc6e65ee4342.jpg', '2b78984dcc6912571d69ac75383ce6c7.jpg', '2', '10', 'RQ000048@BagasMimboHandokoWrin', '', '', ' ZTEGCD76FC95', ' BDG 1 ODP 7', '1', '1', '-18,7', '', NULL, NULL, NULL, 'Olt sambit', 'Aktivasi pelanggan selesai', ''),
('lp67c6efba46988', 'PE000053', 'RQ000049', NULL, NULL, NULL, '', 'KABEL', '12.99', '12..87', NULL, NULL, NULL, NULL, NULL, 'f81aa4f1225ee1a10dff2d1a3286ec3e.jpg', '827ee167d2572c774132e2f313681f77.jpg', 'fc075bf373fb995a8dfac276ab33893a.jpg', '7133874f4be22af87d3ffbf8fe16bc4b.jpg', '32', '12', 'RQ000049_BedinginNurIkhwan', '', '', 'HWTCA901DF78', 'BDG 9 ODP 5', '1', 'sementara ikut pot 1', '-11', '', NULL, NULL, NULL, 'sambit', 'sudah on sesui permintaan pelanggan \r\nNb :sementara langsung ambil dari beckbone bablasan langsung ambil dari jc perem4 b bancangan dan di olt mancep pot 1 spliter  1:4 .1:8 di Olt / server sambit', ''),
('lp67e195678a4fa', 'PE000054', 'RQ000050', NULL, NULL, NULL, '', 'KABEL', '11', '11', NULL, NULL, NULL, NULL, NULL, '37c2329621b9fda6b3744b7fa8275661.jpeg', '264b53131000f00cb0ae7bfb7fcc02ab.png', '7b481b547ec209029abc33104fc52487.jpeg', '0c6e1ddb7f4df024b28890e5398d2d3a.jpeg', '10', '21', 'G1X4B000050_NGLEWAN_ANSORI', '', '', 'ZXICFF0F2D68', 'BDG 8 ODP 15', '1', '8', '10', '', NULL, NULL, NULL, 'Sambit', 'Aktivasi Pelanggan Selesai', ''),
('lp67f7259c6bba1', 'PE000055', 'RQ000051', NULL, NULL, NULL, '', 'KABEL', '15', '15', NULL, NULL, NULL, NULL, NULL, 'ab6d58162eb1b61c658df0666e5b486a.jpeg', '86c1c5cc005688966ce926faad377c9f.jpeg', 'd4df3b29ddf50036d9a56f65133feeeb.jpeg', '88aa111f4b01098923c09acddb7d6bcf.jpeg', '21', '23', 'G1X4BRQ000051_WRINGINANOM_EKOS', '', '', 'zixcff1e8b68', 'BDG 3 ODP 15', '2', '3', '15', '', NULL, NULL, NULL, 'Sambit', 'Migrasi', ''),
('lp680781b54b284', 'PE000056', 'RQ000052', NULL, NULL, NULL, '', 'KABEL', '13', '13', NULL, NULL, NULL, NULL, NULL, 'd1d67082daf1610ecec6733d181e194e.jpg', '62e87bf4a6d4020fac053b2ffa0123a6.jpg', '1b0789c572dcf9b3c0bb8bd59f0ddd08.jpg', '29b5382947a2e54627bc97ba39fe2967.jpg', '14', '25', 'G1X4BRQ000052_NGLEWAN_YULIUSSU', '', '', 'ZXICFF1D7028', 'BDG8 ODP 15', '2', 'BDG8 ODP 15 ', '-24.1', '', NULL, NULL, NULL, 'OLT SAMBIT ', 'Pemasangan selesai ', ''),
('lp684691766635a', 'PE000057', 'RQ000053', NULL, NULL, NULL, NULL, 'KABEL', '10', '12', NULL, NULL, NULL, NULL, NULL, '85a418b21f20022295cb5f0e6dd21f23.png', 'bdd5c71295eeaeb74d7f4ae274b23c39.png', '51e006d9de20c728c3880b50e0f46335.png', 'a3ca0bf74202f84334abe97bf01509b6.png', '12', '110', '192.192.192.192', '', '', '2111323232232', '212121212', '121212', '6767', '1212', '', NULL, NULL, NULL, 'MANUK', 'OK', ''),
('lp6847f3a0a4dc0', 'PE000058', 'RQ000054', NULL, NULL, NULL, NULL, 'KABEL', '20', '30', NULL, NULL, NULL, NULL, NULL, 'c0c8d151b48652f9b77542e6f1062c1c.png', '72be005a10cdbffe0f7c0c881f5e2200.png', 'e0a28f0f7fe771055e0b7969e501927a.png', '3fbc1c71f52a8eda387567a73115869d.png', '5', '10', '19.19.19.19', '', '', '23672643727324', '343436', '90909', '8080', '45', '', NULL, NULL, NULL, 'ponorogo', 'OK', ''),
('lp6847f9e0708ea', 'PE000059', 'RQ000055', NULL, NULL, NULL, NULL, 'KABEL', '34', '344', NULL, NULL, NULL, NULL, NULL, '4be9e95dd3321b4eb354329a8c74d00c.png', '0dd37b9651883e276a4ec33e8a4ad5bf.png', '329fb35288c9f59d2f6011fd225966ac.png', '36190e386fa791c6e699cb90e12bc7df.png', '4324', '343', '4324324', '', '', '3432432', '4334', '34324', '432432', '34324', '', NULL, NULL, NULL, '3432432', '324324234432', ''),
('lp684ad6cd114cb', 'US000001', 'RQ000056', NULL, NULL, NULL, NULL, 'KABEL', 'Itaque nesciunt dol', 'Reprehenderit velit ', NULL, NULL, NULL, NULL, NULL, '7c9c2426ee391d6b5ea93298ed8fc5f1.png', 'ba7e763d93fced0aa99d6de8d3eed992.png', '981323f7c73c6366810b33fcbdaa5d54.png', '4dac9f1177f019daedf941651ba3d62f.png', 'Odit ea officia face', 'Obcaecati voluptas v', 'Dolore est qui et an', '', '', 'Reiciendis soluta ne', 'Vel vel quisquam est', 'Amet ad quis enim e', 'Nostrud quam illo is', 'Excepturi qui molest', '', NULL, NULL, NULL, 'Itaque laborum in so', 'Perspiciatis eos re', ''),
('lp684ae04682031', 'PE000060', 'RQ000057', NULL, NULL, NULL, NULL, 'KABEL', 'Sunt saepe voluptas ', 'Ex aliqua Quaerat d', NULL, NULL, NULL, NULL, NULL, '3dd4c144b9db7d04c7d983d4c7bd3acb.jpg', '87ca7f54740301cfaf27f590f4b8a515.jpg', '813ff7d47b73f873ed0b9c681364c39c.jpg', 'c3108a53ec4a94e28c52851adf12571f.jpg', 'Molestiae atque sit', 'Ab quibusdam rem con', 'Modi accusantium lab', '', '', 'Sit beatae corporis', 'Ut dignissimos id qu', 'Maiores voluptatum q', 'Provident neque ani', 'Placeat accusantium', '', NULL, NULL, NULL, 'Odit excepteur nostr', 'Repellendus Libero ', ''),
('lp684b1c8f0aaca', 'PE000062', 'RQ000060', NULL, NULL, NULL, NULL, 'KABEL', 'Accusantium nulla co', 'Reprehenderit dolore', NULL, NULL, NULL, NULL, NULL, 'efdd22f16bc63cc6f47ace7e37001f40.jpg', 'a0e19229325c9fd4ac80c01f8f8f176b.jpg', 'd6cc531f029b254a912a7d005ca71b13.jpg', '0bbdfa56596491dc4db5b96f9eef68fe.jpg', 'Facere quia asperior', 'Quam repudiandae mol', 'Recusandae Eaque ul', '', '', 'In quos nihil ab vit', 'In est harum eligen', 'Laborum Quisquam qu', 'Officiis culpa conse', 'Iure laudantium qui', '', NULL, NULL, NULL, 'Rerum mollitia aut l', 'Amet nostrum odio u', ''),
('lp684b250048ad4', 'PE000063', 'RQ000061', NULL, NULL, NULL, NULL, 'KABEL', 'Aperiam dolor nulla ', 'Dolore anim odit dol', NULL, NULL, NULL, NULL, NULL, '9281741149777e2a934a1b7406498499.jpg', '079b66591ec77fb75907f5e7538154c0.jpg', '92762d62c8e49b0b1951c4e7d71f8976.jpg', '92450397a3144545010ea4e0b18be00f.jpg', 'Irure earum dicta qu', 'Non excepturi iure m', 'Laboriosam ut digni', '', '', 'Incididunt at facili', 'Qui amet ut volupta', 'Non tenetur esse ame', 'Nobis excepturi cons', 'Excepturi ipsam irur', '', NULL, NULL, NULL, 'Eius animi aliquid ', 'Alias aut et possimu', ''),
('lp684bad9490b2e', 'PE000061', 'RQ000059', NULL, NULL, NULL, NULL, 'KABEL', 'Officia aperiam ad i', 'Non id in excepturi ', NULL, NULL, NULL, NULL, NULL, '8fea35b7c7babd5e873b689a7f1d749b.jpg', 'f10b7576438112a9a67e82ebd273eefe.jpg', '1f5b3397dc641d51ccb8524d38edfecc.jpg', 'cdb2228a19ef765bc417b6431a0d741b.jpg', 'Sequi voluptate et v', 'Nihil voluptatum cum', 'Voluptates alias et ', '', '', 'Laboris unde tempora', 'Elit sint ullam ips', 'Nihil corrupti volu', 'Enim nemo quis enim ', 'Quo excepteur aut ut', '', NULL, NULL, NULL, 'Voluptatem Veniam ', 'Cupiditate itaque so', ''),
('lp685840c4bd2e0', 'US000005', 'RQ000065', NULL, NULL, NULL, NULL, 'KABEL', 'Et id laborum nihil', 'Quibusdam sunt esse', NULL, NULL, NULL, NULL, NULL, '554fdbfc1836171c5cbacbe1f9fb7ce4.jpg', '8975210b7bd77e7c3e02c2ab0205c470.jpg', '1ae92b8bbc13755c3d05e4391797f352.jpg', 'bef03642ffee6e18f280197ccc535f36.jpg', 'Ad qui ea eum ad nih', 'Aut quis esse et op', 'Non quia nihil odit ', '', '', 'Sed aliquid quidem i', 'A mollitia quo quia ', 'Aliquam autem porro ', 'Nobis sit iste pers', 'Nesciunt commodo ac', '', NULL, NULL, NULL, 'Et eu aut doloribus ', 'Expedita non sunt mo', ''),
('lp685c1713a98a1', 'US000003', 'RQ000063', NULL, NULL, NULL, NULL, 'KABEL', 'Assumenda sit disti', 'Elit eu sed dolore ', NULL, NULL, NULL, NULL, NULL, 'f35a3789333404cde038ee5864fce7ff.jpg', '415b1c1b2eeabc43a986935e7c01fcce.jpg', 'ad1dcdaaba62d8af9f1e24d3c7e950e1.jpg', 'd5c6a80630cc32f7a19ba0c304d98b5c.jpg', 'Ex voluptatum duis e', 'Sunt quae sint nulla', 'Do error esse mollit', '', '', 'Et adipisicing irure', 'Ut tenetur provident', 'Laborum ad illo nesc', 'Voluptatem natus et ', 'Est consequatur maxi', '', NULL, NULL, NULL, 'Quis itaque laborum', 'Ab consectetur minim', ''),
('lp685c4faf61a54', 'PE000065', 'RQ000067', NULL, NULL, NULL, NULL, 'KABEL', 'Dolor eos dolore qu', 'Ipsa irure quia adi', NULL, NULL, NULL, NULL, NULL, '9c79f2b2afea6703a7b5e648ee21211e.png', '228c47ae00b81fbd8d7fe3928ddaec24.jpg', 'ff548d836652c7410b6be60f3cdf2d43.jpg', 'aca4be4673c7e3665b0576d6b28757c0.jpg', 'Magna nostrud mollit', 'Et eiusmod quo sit d', 'Dolore reprehenderit', '', '', 'Exercitationem tenet', 'Aspernatur enim solu', 'Laboriosam quia eiu', 'Ratione quia qui tot', 'Rerum culpa accusam', '', NULL, NULL, NULL, 'Quam nostrud officia', 'Omnis aliquam qui re', ''),
('lp68615e3cbddcf', 'PE000069', 'RQ000072', NULL, NULL, NULL, NULL, 'KABEL', 'Ut quam modi corrupt', 'Consequatur consequa', NULL, NULL, NULL, NULL, NULL, 'cdab624ae3849f0f5f017941d848a682.jpg', '5293b18f575004aacb46dd4c093c8354.jpg', '4d381be428aa8915f72db70dafbe5543.jpg', 'ad21af92fe2122fd91c87de3f57c6a59.jpg', 'Fugiat reiciendis in', 'Quisquam aut quis ab', 'Est sit veritatis ex', '', '', 'Quia voluptas volupt', 'Ipsum ullamco animi', 'Culpa minima ea vol', 'Est beatae nulla num', 'Placeat error est ', '', NULL, NULL, NULL, 'Vero ex ipsum distin', 'Et enim reprehenderi', ''),
('lp6865ca40e2335', 'PE000067', 'RQ000070', NULL, NULL, NULL, NULL, 'KABEL', '87.99', '96.21', NULL, NULL, NULL, NULL, NULL, 'afc67327863182c48e99d4b59a13d8ee.jpg', '50f5de3636443541e9416ef6dca4c3b4.jpg', '9812465ff21c286698cda9ad1d839b4e.jpg', '60fee75629714527c7cde2505500c26e.jpg', '22', '32', 'G1X4BRQ000070_SAMBILAWANG_RIDW', '', '', 'ZTEGD3307338', 'BDG 4 odp 14', '5', '4', '-21', '', NULL, NULL, NULL, 'bedingin', 'aktivasi selesai\r\ndengan paket 100 mbps Rp.330.000', ''),
('lp686bb2c8e0386', 'PE000070', 'RQ000073', NULL, NULL, NULL, NULL, 'KABEL', '4.98', '4.67', NULL, NULL, NULL, NULL, NULL, 'dfbc2f751a70f89a07bf9bd76e85a8f4.jpg', '341d386e58ca2fc08216c44839328427.jpg', 'd64c9fe6e606bc6c20816344410e9927.jpg', '3ddf7b138926524b43b36fff7e30f07c.jpg', '22', '32', 'G1X4BRQ000073_ARISSUBAGYO_WRIN', '', '', 'ZICG2990727C', 'BDG 4 ODP 14', '7', '4', '-21', '', NULL, NULL, NULL, 'olt bedingin sambit', 'aktivasi selesai\r\npaket Rp.111.000  ( gratis pasang )', ''),
('lp688b0930953e0', 'PE000073', 'RQ000076', NULL, NULL, NULL, NULL, 'KABEL', '5.34', '5.24', NULL, NULL, NULL, NULL, NULL, '76c1359c37399e32be9690c0e4521264.jpg', 'e612933f1671723a2c1c55986cc9d81f.jpg', 'afd2410795d352ef05d62221ae357f11.jpg', 'ec43f8e260f45bbf53c3a3f30e05d749.jpg', '16', '35', '0', NULL, NULL, 'ZICG297C4175', '13', '2', '0', '-12', NULL, NULL, NULL, NULL, 'Sambit', 'Selesai', ''),
('lp6899341f5a5c1', 'PE000071', 'RQ000074', NULL, NULL, NULL, NULL, 'KABEL', '14.6', '13.4', NULL, NULL, NULL, NULL, NULL, '386dc250c7ecf9e333a6f7cc020d9e61.jpg', '7d5b1b6fcde9b46e5777bbe1171a5a26.jpg', '9957cc718c8eb463731addf092f1d0d1.jpg', '9c1c3427e389f86d223e97ce843a5115.jpg', '3', '32', '0', NULL, NULL, '22381A1010161', 'HUBTELAGA', '3', '0', '-06.7', NULL, NULL, NULL, NULL, 'PEMANCAR TELAGA', 'aktifasi pelanggan selesai', ''),
('lp6899cda7636a1', 'PE000074', 'RQ000077', NULL, NULL, NULL, NULL, 'KABEL', '24.5', '25.6', NULL, NULL, NULL, NULL, NULL, '1a1eafb1c97a3e3d4abb66b01f0b6f4e.jpg', '8f102d9cdfd4490bdb088b24a2c06251.jpg', '260175a1748a55cfbab8de212fe0d86c.jpg', '99ef806e56712659dfeb6b4cb758a9aa.jpg', '10', '32', '0', NULL, NULL, 'ZICG29695342', 'BDG5ODP1', '3', '1/1/5', '-10.3', NULL, NULL, NULL, NULL, 'OLT Sambit', 'aktifasi pelanggan selesai', ''),
('lp68e71e14d8dd8', 'PE000075', 'RQ000078', NULL, NULL, NULL, NULL, 'KABEL', '13', '13', NULL, NULL, NULL, NULL, NULL, 'fbdd3415fdd81f30e058d001466848a8.jpg', 'c833e186230e5623e3bce138e23ce36c.jpg', 'c161daf700a3b7f06a3112fb7b6c44d1.jpg', 'b527f41e47663515631b5d653466cb02.jpg', '6', '9', '.', NULL, NULL, 'HWTCDF6A7018', 'BDG7odp4', '4', '0/3/15', '-15', NULL, NULL, NULL, NULL, 'Sambit', 'Sudah selesai ', ''),
('lp68ff401cb246c', 'PE000078', 'RQ000081', NULL, NULL, NULL, NULL, 'KABEL', '14.8', '14.9', NULL, NULL, NULL, NULL, NULL, 'f90c5c221cf41d55b8fe7c9bf03c3364.jpg', '656830e56443c32e5906ee78affde064.jpg', '0d365ba3b7ffbd6b4e981a2e8495781e.jpg', '92a3f094e9ebe2dbdc21407b0464ba4f.jpg', '22', '2', '0', NULL, NULL, 'HWTCDF6AC940', 'BDG6ODP13', '3', '2', '18', NULL, NULL, NULL, NULL, 'olt bedingin', 'aktivasi selesai', ''),
('lp690ef74c9f96e', 'PE000079', 'RQ000082', NULL, NULL, NULL, NULL, 'KABEL', '9.8', '9.7', NULL, NULL, NULL, NULL, NULL, 'bdae936a8e3f58d6f791380875610cee.jpg', 'bf056ca468299841c5efe7187b8049e6.jpg', '16ffc39961d95c8fe9170d6c73ca2fa2.jpg', 'eb9ca07eb0bc199fe680e3ec600389a5.jpg', '16', '7', '0', NULL, NULL, 'HWTCDF6A4328', 'BDG6ODP13', '4', '1', '-17', NULL, NULL, NULL, NULL, 'olt sambit', 'aktivasi selesai( ambil paket Rp. 110.000 / 10 mbps )gratis pasang ', ''),
('lp6954ea27db08b', 'PE000084', 'RQ000087', NULL, NULL, NULL, NULL, 'KABEL', '12.3', '11.2', NULL, NULL, NULL, NULL, NULL, '7dd91efea8cf00e5a70698c9ebf39fbc.jpg', '35ae2026c5e3c908f36a107589e42c06.jpg', '42de009212a05eef9a845241dffc701a.jpg', '1c21004351690cf044cd52bcfdfa479e.jpg', '12', '27', '0', NULL, NULL, 'HWTCDF6AD608', 'Sblw-8', '5', '1', '-19', NULL, NULL, NULL, NULL, 'Olt sambit', 'Pemasangan sudah selesai, pembayaran lewat CCTV micro tech. ', ''),
('lp6985e783bb92b', 'PE000085', 'RQ000088', NULL, NULL, NULL, NULL, 'KABEL', '24.8', '24.9', NULL, NULL, NULL, NULL, NULL, 'b4208c2e21156a8afca9d01fd35ca3d8.jpg', '5765a5807f21e48afa0cba30b43d37e3.jpg', 'd1b5dd30dfce024b566b858af93d502d.jpg', '0f528909b34a79dfc88a9a23d6100d4a.jpg', '23', '11', '0', NULL, NULL, 'ZXICFF2CWB88', 'BDG7ODP2', '1', '3', '-6', NULL, NULL, NULL, NULL, 'olt bedingin', 'aktivasi selesai ', ''),
('lp69e9f0015b4fe', 'PE000088', 'RQ000091', NULL, NULL, NULL, NULL, 'KABEL', '24.4', '24.8', NULL, NULL, NULL, NULL, NULL, '86486e5240d61bef869ba950af5e165d.jpg', '44f4eb57e720e014e8f0abcc13ae2380.jpg', '501e04ba43295fb73b26a9f0913ed070.jpg', '72e81e8c42cc78a2bc762913c8fb9dc4.jpg', '11', '9', '0', NULL, NULL, 'ZICGCG08B64A9', 'WRA2', '3', '0/0/2', '-19', NULL, NULL, NULL, NULL, 'olt bedingin', 'aktivasi selesai ', ''),
('lp69f2ed7a11cdf', 'PE000089', 'RQ000092', NULL, NULL, NULL, NULL, 'KABEL', '60.6', '63.9', NULL, NULL, NULL, NULL, NULL, '776e8403a231ce3d246025af24774034.jpg', '1a30c4da4083eeb708eff23adf632133.jpg', 'd545e30f843e62212bb9dff75aedc977.jpg', 'f31d6627de2537021edeeabe008b7b8f.jpg', '22', '11', '0', NULL, NULL, 'ZTEGC4555037', 'wra16', '3', '2', '-20', NULL, NULL, NULL, NULL, 'olt sambit', 'aktivasi selesai', ''),
('lp69fc165d4384a', 'PE000091', 'RQ000094', NULL, NULL, NULL, NULL, 'KABEL', '45', '45', NULL, NULL, NULL, NULL, NULL, '13dc175449ae84e77f3d35a730acccb9.jpg', 'be7888ea256ce742b0c38201ae832454.jpg', '0e705cbcbac56b45559eacf13d965806.jpg', '70d19e8a54985f80dd8e0c9bd462eaa5.jpg', '3', '6', '.', NULL, NULL, 'ZTEGC8885938', 'KWHN5', '2', '0/2/16', '-15', NULL, NULL, NULL, NULL, 'sambit ', 'sudah selesai ', ''),
('lp69fc5903b1fb4', 'PE000092', 'RQ000095', NULL, NULL, NULL, NULL, 'KABEL', '24.8', '25.9', NULL, NULL, NULL, NULL, NULL, 'b7694d6321d30f78d7f577a8c3266857.jpg', 'a02039a6bca0c20f80f0fe3a23a2ba29.jpg', 'df37f2036dcdb59b9fc6488813dca172.jpg', 'e791651f3ee2135d82f190524a320d2d.jpg', '22', '11', '0', NULL, NULL, 'ZTEGC1F11AC7', 'WRA16', '2', '0/1/3', '-22', NULL, NULL, NULL, NULL, 'olt sambit', 'aktivasi selesai', ''),
('lp69fed1eb44d5e', 'PE000093', 'RQ000096', NULL, NULL, NULL, NULL, 'KABEL', '24.8', '24.9', NULL, NULL, NULL, NULL, NULL, 'feb0e8bcdc0f5635d222d67a4ebbe589.jpg', '90938fb137eb96792ca2c0d64196cbe4.jpg', 'da27ed9955c3a79b782223b7ec8dd98a.jpg', 'cf562607a49bca5a5e4f134eef670fce.jpg', '22', '11', '0', NULL, NULL, 'CDTC500DCEE2', 'KPK20', '3', '3', '-13', NULL, NULL, NULL, NULL, 'Olt bedingin sambit ponrgo', 'aktivasi selesai', ''),
('lp6a0c37544edcf', 'PE000095', 'RQ000098', NULL, NULL, NULL, NULL, 'KABEL', '12', '12', NULL, NULL, NULL, NULL, NULL, 'e1b3943862aa1b60e3eecb5afd0943a2.jpg', 'b0d5e259f177016e68b94b2541c66805.jpg', 'e5fb03dacb478256e727b48464abd187.jpg', '5303ae42b7c4a7bd6eb5af3b321062cb.jpg', '13', '26', '192.168.101.1', NULL, NULL, 'ZTEGC88B55B1', '16', '4', '1/3/8', '-22', NULL, NULL, NULL, NULL, 'olt smbit', 'sudah terpasang', ''),
('lp6a1820cf35595', 'PE000096', 'RQ000099', NULL, NULL, NULL, NULL, 'KABEL', '30', '30', NULL, NULL, NULL, NULL, NULL, '435efd44aecd9535eed01793c48a9c92.jpg', 'f7b17a721decc5bd6d9a7bdee04bd28e.jpg', '114c871851ed8a011e056052b2377413.jpg', 'ca6070dd611c740ba304cc73a1a950af.jpg', '30.6', '30', '192.168.101.1', NULL, NULL, 'CDTC500EAEF2', 'kwjn8', '1', '1/3/8', '-14', NULL, NULL, NULL, NULL, 'sambit', 'sudah terpasang', ''),
('lp6a1a189d3b2b6', 'PE000094', 'RQ000097', NULL, NULL, NULL, NULL, 'KABEL', '30', '30', NULL, NULL, NULL, NULL, NULL, '063b508b1bec9e48890a29f3aafe68b5.jpg', 'f16a12be877a43bb5d86536b859d005e.jpg', 'a291a093a9e7608b792cd70d7ae18eae.jpg', '0e806bdde92fc92ef3efbf89d3848b27.jpg', '13', '27', '192.168.101.1', NULL, NULL, 'CDTC500A1138', 'BDGN8', '1', '1/3/8', '-14', NULL, NULL, NULL, NULL, 'SAMBIT', 'sudah terpasang', ''),
('lp6a1ff25075d04', 'PE000100', 'RQ000103', NULL, NULL, NULL, NULL, 'KABEL', '42', '45', NULL, NULL, NULL, NULL, NULL, 'bb52c7f28c70820379db9941ecef2ff0.jpg', 'bcd32bde5eb940cff9d43641168a87d1.jpg', '27c7a1d213286b680b5cc668251d956d.jpg', '41f81cc7e70b01538624236fcf2fc490.jpg', '12.3', '250.5', '193.168.101.1', NULL, NULL, 'CDTC500E7FCC', 'WRA34', 'PORT-1', '1/1/2', '-27', NULL, NULL, NULL, NULL, 'sambit ', '.', ''),
('lp6a1ffec34b884', 'PE000099', 'RQ000102', NULL, NULL, NULL, NULL, 'KABEL', '100', '99', NULL, NULL, NULL, NULL, NULL, 'aeec991fd7b43196d8746401dee36303.jpeg', 'b747b8250da3a7f418c621f6cb9bd806.png', '3ca989aee141214c88a17f380d29a19d.jpeg', 'e659854918d0e0cc8ab2924e43b9ff68.jpeg', '37', '25', '192.169.101.1', NULL, NULL, 'CDTC500AEG', 'WRA-6', '4', '1/1/1', '-25', NULL, NULL, NULL, NULL, 'Sambit', 'Oke', ''),
('lp6a1fffba79177', 'PE000097', 'RQ000100', NULL, NULL, NULL, NULL, 'KABEL', '98', '96', NULL, NULL, NULL, NULL, NULL, '15fedfafcc801d8e32e4d62363179a25.jpg', '1c1ab6961194f28808a5fb763aebdccc.jpg', '14557cc6da95d3a1f1517462e5abd6e0.jpg', 'f572f90c620434973ad9fbd8ee11b40e.jpg', '12.3', '250.5', '192.168.101.1', NULL, NULL, 'CDTCAFCB6BA5', 'BCGN15', 'PORT-1', '1//2', '-15', NULL, NULL, NULL, NULL, 'whusnet ', '.', ''),
('lp6a2956ffaf7c3', 'PE000101', 'RQ000104', NULL, NULL, NULL, NULL, 'KABEL', '45', '43', NULL, NULL, NULL, NULL, NULL, '9f228bf9713a04fcfa7976e83530269b.jpg', '666a95c3a4f77aa86e9858f8f95a0dbd.jpg', 'f35b874f3a1b47bc63b96ceb0404d433.jpg', '2aeae1151800715657797882ab7049e8.jpg', '12.3', '250.5', '192.168.101.1', NULL, NULL, 'CDTCAFCB6C09', 'NGLW15', 'PORT-1', '1/1/2', '-20', NULL, NULL, NULL, NULL, 'bedingin', '.', ''),
('lp6a365bd4058d4', 'PE000104', 'RQ000107', NULL, NULL, NULL, NULL, 'KABEL', '200', '200', NULL, NULL, NULL, NULL, NULL, 'f734baf598b697d761215c32303b6c0d.jpg', '1251954c4b6106e06105a7cfc0dc5c61.jpg', '9e3bdaf65387eaa9791e940d67ab6e4e.jpg', '19469eee4752dd0457c3c260d02ad805.jpg', '12', '123', '0', NULL, NULL, 'FHTTC1846275', 'Wra34', '5', '000', '-25', NULL, NULL, NULL, NULL, 'Olt sambit', 'Selsai', ''),
('lp6a411ee12d314', 'PE000106', 'RQ000109', NULL, NULL, NULL, NULL, 'KABEL', '45.6', '45', NULL, NULL, NULL, NULL, NULL, 'aee0dc068217b8a03d5be7c8b7e5155e.jpg', '8131f8dc27e56f668380501d70ad4a4c.jpg', '9ea42d1bea2b3287962725cb82cde81e.jpg', 'af93d876c0e14ccb346cbb509c3fed9a.jpg', '4', '26', '192.168.1.1', NULL, NULL, 'FHTTC184C8B', 'CPRJ17', '2', '1/3/8', '-19', NULL, NULL, NULL, NULL, 'SAMBIT', 'sudah terpasang', ''),
('lp6a44d6d3d1523', 'PE000105', 'RQ000108', NULL, NULL, NULL, NULL, 'KABEL', '20', '25', NULL, NULL, NULL, NULL, NULL, '63ab15a07652a038397c727b041ab272.jpg', '0e70ff46bcf2889cdb4196bb0752cfaf.jpg', '273ef86df4fcf9c4311d94b75b69701a.jpg', '41755546766e76b88877dcc9f76e3a90.jpg', '3', '10', '192.168.1.1', NULL, NULL, 'ZTEGD1C26404', 'WRA34', '7', '1/1/8', '-26', NULL, NULL, NULL, NULL, NULL, 'Sudah selesai', ''),
('lp6a71ea6d2b44e', 'PE000107', 'RQ000110', NULL, NULL, NULL, NULL, 'KABEL', '45', '45', NULL, NULL, NULL, NULL, NULL, '1ddeaa0efd3f2591cda12d536a8562ef.jpg', '8be9bfdcffd58657512338c2aa82cd78.jpg', 'ff987a818989c817ecf571f5e146a695.jpg', '74951ad1abf9d34378d7855f25ac8585.jpg', '13', '26', '192.168.1.1', NULL, NULL, 'FHTTC1841A37', 'BCNG14', '1', '1/3/8', '-9', NULL, NULL, NULL, NULL, 'SAMBIT', 'sudah terpasang', ''),
('lp6a7edbf769ca1', 'PE000108', 'RQ000111', NULL, NULL, NULL, NULL, 'KABEL', '36', '34', NULL, NULL, NULL, NULL, NULL, 'd9cf5a3898463e248bda96b7dc34fba1.jpg', '5ea6e4a697121756b5a155e12f1d6587.jpg', '57bfb258db0241124edba1c6cdb08ed5.jpg', '9040c246d95dca1f2a2236ba9987b69e.jpg', '13', '27', '192.168.1.1', NULL, NULL, 'FHTTC1842C54', 'WRA12', '6', '1/3/8', '-22', NULL, NULL, NULL, NULL, 'sambit', 'sudah terpasang', ''),
('lp6aa4256196648', 'PE000110', 'RQ000113', NULL, NULL, NULL, NULL, 'KABEL', '35', '34', NULL, NULL, NULL, NULL, NULL, 'd7764a233ea44fd23f5cbcc844916f46.jpeg', '0aacd74e46026c0a923ce896a809decd.png', 'a674a68db35f5c397d44f981620b5f6d.jpeg', 'a5805f865f099705aa92a709926466a4.jpeg', '15', '25', '.', NULL, NULL, '54504C474F9F7E40', 'WRA9', '2', '3', '-12', NULL, NULL, NULL, NULL, 'Sambit', 'Sudah terpasang', ''),
('lpw6644644df1b0', 'PE000020', 'RQ000020', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, '', '', ''),
('lpw6654881e6839', 'PE000021', 'RQ000021', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, '', '', ''),
('lpw66694670f407', 'PE000022', 'RQ000022', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, '', '', ''),
('lpw668e00032c4e', 'PE000026', 'RQ000026', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, '', '', ''),
('lpw668f30fbcbfa', 'PE000031', 'RQ000027', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, '', '', ''),
('lpw66f78f855c39', 'PE000035', 'RQ000031', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, '', '', ''),
('lpw6718800799e0', 'PE000038', 'RQ000034', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, '', '', ''),
('lpw67498918651e', 'PE000040', 'RQ000036', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, '', '', ''),
('lpw6778fc1cdb54', 'PE000050', 'RQ000046', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, '', '', ''),
('lpw684afa8b9932', 'US000002', 'RQ000058', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, '', '', ''),
('lpw684bacde513c', 'PE000064', 'RQ000062', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, '', '', ''),
('lpw6852bb1cd92b', 'US000004', 'RQ000064', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, '', '', ''),
('lpw685bfd4487ae', 'US000006', 'RQ000066', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, '', '', ''),
('lpw685c3f793ba9', 'US000007', 'RQ000068', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, '', '', ''),
('lpw685c49bfaa9d', 'PE000066', 'RQ000069', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, '', '', ''),
('lpw685f9cb5b658', 'PE000068', 'RQ000071', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, NULL, NULL, '', '', ''),
('lpw6881dd749bb1', 'PE000072', 'RQ000075', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', ''),
('lpw68e47ac817c0', 'PE000076', 'RQ000079', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', ''),
('lpw68e4d0f079b6', 'PE000077', 'RQ000080', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', ''),
('lpw690972010810', 'PE000080', 'RQ000083', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', ''),
('lpw693d0c6ddc07', 'PE000081', 'RQ000084', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', ''),
('lpw6944ad5dc07d', 'PE000082', 'RQ000085', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', ''),
('lpw6948f0d3c74d', 'PE000083', 'RQ000086', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', ''),
('lpw69cb0b9a2f62', 'PE000086', 'RQ000089', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', ''),
('lpw69d741f88b0e', 'PE000087', 'RQ000090', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', ''),
('lpw69f051a590ea', 'PE000090', 'RQ000093', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', ''),
('lpw6a13ba3ccf5b', 'PE000098', 'RQ000101', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', ''),
('lpw6a1e834ca1da', 'PE000102', 'RQ000105', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', ''),
('lpw6a20e2fa2422', 'PE000103', 'RQ000106', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', ''),
('lpw6a9a63868440', 'PE000109', 'RQ000112', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', ''),
('lpw6ae5962762b7', 'PE000111', 'RQ000114', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '');

-- --------------------------------------------------------

--
-- Struktur dari tabel `level`
--

CREATE TABLE `level` (
  `ID` int(11) NOT NULL,
  `LEVEL` varchar(10) DEFAULT NULL,
  `ICON` varchar(30) NOT NULL,
  `KATEGORI` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `level`
--

INSERT INTO `level` (`ID`, `LEVEL`, `ICON`, `KATEGORI`) VALUES
(1, 'Admin', 'bx bx-user-pin', '1'),
(2, 'Manajer', 'bx bx-user-voice', '1'),
(3, 'SPV', 'bx bx-sitemap', '1'),
(4, 'Staf', 'bx bxs-group', '1'),
(5, 'Customer', 'bx', '1'),
(6, 'Mitra', '', '1');

-- --------------------------------------------------------

--
-- Struktur dari tabel `merk_barang`
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

--
-- Dumping data untuk tabel `merk_barang`
--

INSERT INTO `merk_barang` (`IDMERK`, `KATEGORIBARANG`, `NAMABARANG`, `MODEL`, `TYPE`, `SERIALNUMBER`, `VERSI`) VALUES
('MR000001', '', 'XPON', '', '', 'CIOT03086E6F', ''),
('MR000002', '', 'tes-alat', '', '', '123123', ''),
('MR000003', '', 'China Mobile', '', '', 'CIOT1331A348', ''),
('MR000004', '', 'China Mobile ', '', '', 'CIOT11EF8DF0', ''),
('MR000005', '', 'XPON', '', '', 'CIOT03017C0F', ''),
('MR000006', '', 'modem gpon zte', '', '', 'ZTEGD1DB8569', ''),
('MR000007', '', 'F663NV3A', '', '', 'ZXICFF07AAE8', ''),
('MR000008', '', 'F663nv3a', '', '', 'ZXICFF0EDC68', ''),
('MR000009', '', 'F663nv3a', '', '', 'ZXICFF040018', ''),
('MR000010', '', 'ZTE F670L', '', '', 'ZTEGC85A9D1F', ''),
('MR000011', '', 'F663', '', '', 'ZXICFF1D7028', '');

-- --------------------------------------------------------

--
-- Struktur dari tabel `noonu1024`
--

CREATE TABLE `noonu1024` (
  `id` int(4) NOT NULL,
  `noonu` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `odp_location`
--

CREATE TABLE `odp_location` (
  `IDLOCATION` varchar(20) NOT NULL,
  `LATITUDE` varchar(50) NOT NULL,
  `LONGITUDE` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `olt_aktifasi`
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
-- Struktur dari tabel `olt_check_dbm`
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
-- Struktur dari tabel `olt_report_signal`
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
-- Struktur dari tabel `olt_report_state`
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
-- Struktur dari tabel `olt_slot_register`
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
-- Struktur dari tabel `olt_slot_unregister`
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
-- Struktur dari tabel `paket`
--

CREATE TABLE `paket` (
  `KODEPAKET` varchar(11) NOT NULL,
  `NAMA_PAKET` varchar(20) DEFAULT NULL,
  `JENIS_PAKET` varchar(50) NOT NULL,
  `KATEGORI_PAKET` varchar(50) NOT NULL,
  `HARGA` int(11) DEFAULT NULL,
  `SPEEDUP` int(15) DEFAULT NULL,
  `SPEEDDOWN` int(15) DEFAULT NULL,
  `LIMITUP` int(15) DEFAULT NULL,
  `LIMITDOWN` int(15) DEFAULT NULL,
  `PROFILOLT` varchar(10) NOT NULL,
  `PROFILPPP` varchar(15) NOT NULL,
  `BONUS` varchar(130) NOT NULL,
  `KETERANGAN` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `paket`
--

INSERT INTO `paket` (`KODEPAKET`, `NAMA_PAKET`, `JENIS_PAKET`, `KATEGORI_PAKET`, `HARGA`, `SPEEDUP`, `SPEEDDOWN`, `LIMITUP`, `LIMITDOWN`, `PROFILOLT`, `PROFILPPP`, `BONUS`, `KETERANGAN`) VALUES
('PK000001', 'default', '01.1', '01.2', 0, 0, 0, 0, 0, '', '', '', ''),
('PK000002', '110K Up to 5M', 'Praba', '01.2', 110000, 3000, 3000, 2000, 2000, '5M', '', '', ''),
('PK000003', '130K Up to 10M', 'Praba', '01.2', 130000, 5000, 5000, 2000, 2000, '10M', '', '', ''),
('PK000004', '150K Up to 15M', 'Praba', '01.2', 150000, 7000, 7000, 5000, 5000, '15M', '', '', ''),
('PK000005', '165K Up to 25M', 'Praba', '01.2', 165000, 7000, 7000, 5000, 5000, '25M', '', '', ''),
('PK000006', '220K Up to 50M', 'Praba', '01.2', 220000, 15000, 15000, 5000, 5000, '50M', '', '', ''),
('PK000007', '290K Up to 100M', 'Praba', '01.2', 290000, 20000, 20000, 10000, 10000, '100M', '', '', ''),
('PK000008', 'Whus Speed Up To 5', 'Praba', '01.2', 115000, 5000, 5000, 0, 0, '', '', '', ''),
('PK000009', 'Whus Speed Up To 13', 'Praba', '01.2', 138000, 13000, 13000, 0, 0, '', '', '', ''),
('PK000010', 'Whus Speed Up To 15', 'Praba', '01.2', 150000, 15000, 15000, 0, 0, '', '', '', ''),
('PK000011', 'Whus Speed Up To 25', 'Praba', '01.2', 167000, 25000, 25000, 0, 0, '', '', '', ''),
('PK000012', 'Whus Speed Up To 30', 'Praba', '01.2', 175000, 30000, 30000, 0, 0, '', '', '', ''),
('PK000013', '138K up to 13M', 'Praba', '01.2', 138000, 13000, 13000, 13000, 13000, '', '', '', ''),
('PK000014', '198K Up to 50M', 'Praba', '01.2', 198000, 50000, 50000, 50000, 50000, '', '', '', ''),
('PK000017', 'Corporate 100 Mbps', 'Praba', '01.2', 500000, 100, 100, 100, 100, '', '', '', 'Paket Corporate'),
('PK000018', 'Corporate 200 Mbps', 'Praba', '01.2', 850000, 200, 200, 200, 200, '', '', '', 'Paket Corporate'),
('PK000019', 'Corporate 300 Mbps', 'Praba', '01.2', 1100000, 300, 300, 300, 300, '', '', '', 'Paket Corporate'),
('PK000020', 'NEW 35 Mbps (138K)', 'Praba', '01.2', 138000, 35000, 35000, 0, 0, '', '', '', 'PAKET MERDEKA 2025'),
('PK000021', 'NEW 45 Mbps (150K)', 'Praba', '01.2', 150000, 45000, 45000, 0, 0, '', '', '', 'PAKET MERDEKA 2025'),
('PK000022', 'NEW 70 Mbps (165K)', 'Praba', '01.2', 165000, 70000, 70000, 0, 0, '', '', '', 'PAKET MERDEKA 2025'),
('PK000023', 'NEW 100 Mbps (198K)', '01.1', '01.2', 198000, 100000, 99995, 0, 0, '', '', '', 'PAKET MERDEKA 2025'),
('PK000024', 'NEW 115 Mbps (330K)', 'Praba', '01.2', 330000, 115000, 115000, 0, 0, '', '', '', 'PAKET MERDEKA 2025'),
('PK000025', 'SPESIAL 65Mbps(150K)', 'Praba', '01.2', 150000, 65000, 65000, 65000, 65000, '', '', '', ''),
('PK000026', 'SPESIAL 50Mbps(138K)', 'Praba', '01.2', 138000, 50000, 50000, 50000, 50000, '', '', '', ''),
('PK000027', 'SPESIAL 200Mbps-198K', '01.1', '01.2', 198000, 200000, 200000, 200000, 200000, '', '', '', '');

-- --------------------------------------------------------

--
-- Struktur dari tabel `penagihan`
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
-- Struktur dari tabel `pengguna`
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
-- Dumping data untuk tabel `pengguna`
--

INSERT INTO `pengguna` (`IDPENGGUNA`, `IDJABATAN`, `IDCABANG`, `NAMADEPAN`, `NAMABELAKANG`, `REKOMENDASI`, `HP`, `TLP`, `IDWILAYAH`, `KOTA`, `KEC`, `DESA`, `ALMT`, `EMAIL`, `STATUSAKUN`, `NAMAPERUSAHAAN`, `NPWP`, `KTP_SIM`, `FOTOKTP`, `JENISKELAMIN`, `JENISPELANGGAN`, `FOTO`, `inserted_at`) VALUES
('PE000001', '8', '1', 'tria-mustika-aji', '', '', '6283130013169', NULL, '3502040011', 'KABUPATEN PONOROGO', 'SAMBIT', 'SAMBIT', 'Dukuh Kwajon, RT 00/ RW 00, Ds. Kwajon Sambit', '0', 'aktif', NULL, '', '3502032207870001', '25e7524fcf74cad4372b4d05e816cfaf.jpeg', 'L', 'RUMAHAN', '', '2024-03-22 02:29:46'),
('PE000002', '8', '1', 'bayu-hiendhiarta', '', '', '6283130013169', NULL, '3502040002', 'KABUPATEN PONOROGO', 'SAMBIT', 'WRINGINANOM', 'Dukuh Krajan RT/RW 05/01 Desa Wringinanom Sambit', '0', 'aktif', NULL, '', '87', 'dad8144641542fd6aeb67104e9eb7882.jpeg', 'L', 'RUMAHAN', '', '2024-05-13 00:46:34'),
('PE000003', '8', '1', 'nurcholis-sofyan-tsawri', '', '', '6281916088895', NULL, '3502040002', 'KABUPATEN PONOROGO', 'SAMBIT', 'WRINGINANOM', 'Dukuh Krajan, RT 002/ RW 002, Ds. Wringinanom Sambit', '0', 'aktif', NULL, '', '3502042803960002', '71724c12480939b99db4c7310465e901.jpeg', 'L', 'RUMAHAN', '', '2024-03-22 02:39:12'),
('PE000004', '8', '1', 'very-angriawan', '', '', '6288989486131', NULL, '3502040006', 'KABUPATEN PONOROGO', 'SAMBIT', 'BEDINGIN', 'Dukuh Krajan RT/RW 03/02 Desa Bedingin Sambit', '0', 'aktif', NULL, '', '3502101270488000', '54eff67c40fe461ed260a1620c07593f.jpeg', 'L', 'RUMAHAN', '', '2024-03-22 02:41:40'),
('PE000005', '8', '1', 'nanang-dianarko', '', '', '6282334100046', NULL, '3502030015', 'KABUPATEN PONOROGO', 'BUNGKAL', 'KUPUK', 'Dukuh Patran RT/RW 02/02 Desa Kupuk Bungkal', '-', 'aktif', NULL, '', '350203306760041', '76c6d3c107bcdf7a4630f457c7d6b7d5.jpeg', 'L', 'RUMAHAN', '', '2024-05-03 03:10:19'),
('PE000006', '8', '1', 'adelia-dewi-maharani', '', '', '6285648593097', NULL, '3502040007', 'KABUPATEN PONOROGO', 'SAMBIT', 'BANCANGAN', 'Dukuh Ngelo RT/RW 03/02 Desa Bancangan Sambit', '0', 'aktif', NULL, '', '3502044401070001', 'cd241728ede6eaa9a348b979009fb5a1.jpeg', 'P', 'RUMAHAN', '', '2024-03-22 03:01:46'),
('PE000007', '8', '1', 'fitdyah-rusdiana', '', '', '6282241063412', NULL, '3502030015', 'KABUPATEN PONOROGO', 'BUNGKAL', 'KUPUK', 'Dukuh Patran RT/RW 02/01 Desa Kupuk Bungkal', '0', 'aktif', NULL, '', '3502036812890001', '8b8aee9bac8cb67568f9b0ed07917a60.jpeg', 'P', 'RUMAHAN', '', '2024-03-27 02:29:06'),
('PE000008', '8', '1', 'mulyono', '', '', '6285204229279', NULL, '3502040002', 'KABUPATEN PONOROGO', 'SAMBIT', 'WRINGINANOM', 'Dukuh Krajan RT/RW 03/02 Desa Wringinanom Sambit', '0', 'aktif', NULL, '', '3502040404650002', 'b12bc03bb74ff31985cc2b2f31e388f9.jpeg', 'L', 'RUMAHAN', '', '2024-03-27 02:37:02'),
('PE000009', '8', '1', 'widatul-khiyaroh', '', '', '6283850558536', NULL, '3502030016', 'KABUPATEN PONOROGO', 'BUNGKAL', 'SAMBILAWANG', 'Dukuh Ngijo RT/RW 02/02 Desa Sambilawang Bungkal', '0', 'aktif', NULL, '', '3502034706810004', '256bd1047b9cc9bce7d0c34fb6483085.jpeg', 'P', 'RUMAHAN', '', '2024-03-27 02:44:14'),
('PE000010', '8', '1', 'muhamad-komarudin', '', '', '1', NULL, '3502040002', 'KABUPATEN PONOROGO', 'SAMBIT', 'WRINGINANOM', 'Dukuh Krajan RT/RW 04/02 Desa Wringinanom Sambit', '0', 'aktif', NULL, '', '3502042911860003', 'fdb6e8bf53d0658807bf5dcfa3cf6503.jpeg', 'L', 'RUMAHAN', '', '2024-04-01 01:57:49'),
('PE000011', '8', '1', 'yogha-christian-gumilar', '', '', '6282228281738', NULL, '3502030015', 'KABUPATEN PONOROGO', 'BUNGKAL', 'KUPUK', 'Dukuh Patran RT/RW 02/02 Desa Kupuk Bungkal', '0', 'aktif', NULL, '', '3502117006580250', 'e17dad0f7bbad78f70d550c965fd0768.jpeg', 'L', 'RUMAHAN', '', '2024-04-01 03:11:04'),
('PE000012', '8', '1', 'tes-multi-ahmad-fatoni-1', '', '', '6285236768521', NULL, '3502180003', 'KABUPATEN PONOROGO', 'BABADAN', 'PATIHAN WETAN', 'Jl. Mekar sari', 'ahmadfatonitesdata@gmail.com', 'aktif', NULL, '', '3502111307980001', '63a308413b02e9e496feee48c39e623b.png', 'L', 'RUMAHAN', '', '2024-04-01 03:26:58'),
('PE000013', '8', '1', 'kristina-damayanti', '', '', '6281515208817', NULL, '3502040002', 'KABUPATEN PONOROGO', 'SAMBIT', 'WRINGINANOM', 'Dukuh Krajan RT/RW 04/02 Desa Duri Wringinanom Sambit', '0', 'aktif', NULL, '', '3502046308010001', 'f761f29ec5e523790f53434d8252df09.jpeg', 'P', 'RUMAHAN', '', '2024-04-17 02:17:45'),
('PE000014', '8', '1', 'sogol', '', '', '1', NULL, '3502030015', 'KABUPATEN PONOROGO', 'BUNGKAL', 'KUPUK', 'Dukuh Patran RT/RW 02/01 Desa Kupuk Bungkal', '0', 'aktif', NULL, '', '3502032310630002', '7fa97a9a8586e2b37279a86f3dc1ae5f.jpeg', 'L', 'RUMAHAN', '', '2024-04-24 07:00:42'),
('PE000015', '8', '1', 'edhi-kurniawan', '', '', '6281384285817', NULL, '3502040006', 'KABUPATEN PONOROGO', 'SAMBIT', 'BEDINGIN', 'Jl A Yani RT/RW 01/01 Desa Bedingin Sambit', '0', 'aktif', NULL, '', '3502040405900002', 'd1c15ce19e60e20312b0a0b18ab7ec9e.jpeg', 'L', 'RUMAHAN', '', '2024-05-03 03:27:24'),
('PE000016', '8', '1', 'andi-maslikhul-qirom', '', '', '6285843768400', NULL, '3502040005', 'KABUPATEN PONOROGO', 'SAMBIT', 'NGLEWAN', 'Dukuh Nepen RT/RW 01/01 Desa Nglewan Sambit', '0', 'aktif', NULL, '', '3502041305990001', '49fcb4cc67e49f416bef2dff5912c353.jpeg', 'L', 'RUMAHAN', '', '2024-04-24 07:25:01'),
('PE000017', '8', '1', 'hariyadi', '', '', '6285204874195', NULL, '3502040002', 'KABUPATEN PONOROGO', 'SAMBIT', 'WRINGINANOM', 'Dkh Krajan RT03 RW02 Ds Wringinanom', '0', 'aktif', NULL, '0', '3502042704850002', '3729ba072d8c94a1d7a514002b7a5063.jpeg', 'L', 'RUMAHAN', '', '2024-05-02 13:46:37'),
('PE000018', '8', '1', 'wawan-yayuk', '', '', '6285259459114', NULL, '3502030016', 'KABUPATEN PONOROGO', 'BUNGKAL', 'SAMBILAWANG', 'Patran, Sambilawang, Kec. Bungkal', '0', 'aktif', NULL, '', '1', '', 'L', 'RUMAHAN', '', '2024-05-04 01:23:34'),
('PE000019', '8', '1', 'rini', '', '', '886901184777', NULL, '3502040007', 'KABUPATEN PONOROGO', 'SAMBIT', 'BANCANGAN', 'Dukuh Ngelo RT/RW 03/02 Desa Bancangan', '0', 'aktif', NULL, '', '3502047006710018', 'd3892bc792f3cfa656d73a6760a750a4.jpeg', 'P', 'RUMAHAN', '', '2024-05-04 01:40:22'),
('PE000020', '8', '1', 'samsulhuda', '', '', '6285230107232', NULL, '3502040005', 'KABUPATEN PONOROGO', 'SAMBIT', 'NGLEWAN', 'Dukuh Nepen RT/RW 01/01 Desa Nglewan Sambit', '0', 'aktif', NULL, '', '3502117006580250', 'c25c62858eb829bdedfca6ee939ce2ec.jpeg', 'L', 'RUMAHAN', '', '2024-05-15 07:29:18'),
('PE000021', '8', '1', 'suwito', '', '', '0896-8149-7500', NULL, '3502040002', 'KABUPATEN PONOROGO', 'SAMBIT', 'WRINGINANOM', 'Ngelo bancangan', '.', 'aktif', NULL, '.', '3502040707790001', '1e63ae4b376293fcfa7e6f0b1b50ba89.jpg', 'L', 'RUMAHAN', '', '2024-05-27 13:18:23'),
('PE000022', '8', '1', 'anamul-mustaqim', '', '', '6287810546009', NULL, '3502040002', 'KABUPATEN PONOROGO', 'SAMBIT', 'WRINGINANOM', 'Jl. Raya Gajah, Tambang, Wringinanom, Kec. Sambit, Kabupaten Ponorogo', '0', 'aktif', NULL, '', '3502030409890002', 'b39dba2c04ba5041cb1d7d7fa6caefa4.jpeg', 'L', 'RUMAHAN', '', '2024-06-12 06:55:45'),
('PE000023', '8', '1', 'nyamin-wringinanom', '', '', '6285334745500', NULL, '3502040002', 'KABUPATEN PONOROGO', 'SAMBIT', 'WRINGINANOM', 'Dukuh Tambong', '0', 'aktif', NULL, '', '123123', '', 'L', 'RUMAHAN', '', '2024-07-03 19:16:29'),
('PE000024', '8', '1', 'smp-3-sambit', '', '', '6289654290308', NULL, '3502040002', 'KABUPATEN PONOROGO', 'SAMBIT', 'WRINGINANOM', 'Wringinanom', '0', 'aktif', NULL, '0', '3502092206910010', '', 'L', 'RUMAHAN', '', '2024-07-03 19:18:08'),
('PE000025', '8', '1', 'rokayatin', '', '', '62', NULL, '3502040002', 'KABUPATEN PONOROGO', 'SAMBIT', 'WRINGINANOM', 'Dukuh Tambong RT/RW 05/01 Desa Wringinanom Sambit', '0', 'aktif', NULL, '', '3502044406890003', '7689426dac5815b256ab1b11d924f5f5.jpg', 'P', 'RUMAHAN', '', '2024-07-10 03:18:57'),
('PE000026', '8', '1', 'nyamin', '', '', '62', NULL, '3502040002', 'KABUPATEN PONOROGO', 'SAMBIT', 'WRINGINANOM', 'Dukuh Tambong RT/RW 05/01 Desa Wringinanom Sambit', '0', 'aktif', NULL, '', '3502040201780002', '6f3385f6235d4049b044a30926174445.jpg', 'P', 'RUMAHAN', '', '2024-07-10 03:29:07'),
('PE000031', '8', '1', 'mediana-hiska-wati', '', '', '628564742980', NULL, '3502040002', 'KABUPATEN PONOROGO', 'SAMBIT', 'WRINGINANOM', 'Dukuh Tambang RT/RW 03/02 Desa Wringinanom Sambit', '0', 'aktif', NULL, '', '3502116105940001', '98fb232e012b7de4d39e747a27668706.jpg', 'P', 'RUMAHAN', '', '2024-07-11 01:10:22'),
('PE000032', '8', '1', 'meidiana-riska-wati', '', '', '6285746612767', NULL, '3502040002', 'KABUPATEN PONOROGO', 'SAMBIT', 'WRINGINANOM', 'Dkh Tambong 03/02 Ds Wringinanom ', '0', 'aktif', NULL, '0', '3502116105940001', '123267649e64142ad222067aab56b55b.jpeg', 'P', 'RUMAHAN', '', '2024-07-21 04:00:31'),
('PE000033', '8', '1', 'nur-hayati', '', '', '628155635134', NULL, '3502040006', 'KABUPATEN PONOROGO', 'SAMBIT', 'BEDINGIN', 'Jl Surti Kanti No 1 RT 02 RW 1 Desa Bedingin', '0', 'aktif', NULL, '', '3502046604830003', 'b8869ce0449556923e03d13de16c3e24.jpg', 'P', 'RUMAHAN', '', '2024-07-27 03:00:44'),
('PE000034', '8', '1', 'henny-sri-jaristy', '', '', '6285706531966', NULL, '3502030015', 'KABUPATEN PONOROGO', 'BUNGKAL', 'KUPUK', 'Dukuh Gandu RT/RW 02/01 Kupuk Bungkal', '0', 'aktif', NULL, '', '3502036608780002', 'ad1330b7c7315a8818afa2e318947bad.jpg', 'P', 'RUMAHAN', '', '2024-09-24 01:23:17'),
('PE000035', '8', '1', 'ariftes01', '', '', '083845478148', NULL, '3502100007', 'KABUPATEN PONOROGO', 'JETIS', 'MOJOREJO', 'grisimai', 'arifefendi304@gmail.com', 'aktif', NULL, '321654', '23156456', '6a360879f706ea4a508d3ad6b4e11867.png', 'L', 'RUMAHAN', '', '2024-09-28 05:09:25'),
('PE000036', '8', '1', 'wahid-asari', '', '', '628125978786', NULL, '3502040002', 'KABUPATEN PONOROGO', 'SAMBIT', 'WRINGINANOM', 'Jl. Ahmad Yani,Krajan, Wringinanom, Kec. Sambit, Kabupaten Ponorogo, Jawa Timur 63462', '0', 'aktif', NULL, '', '3502092305850005', '4d8e18d632b84e830fc717c80096941f.jpg', 'L', 'RUMAHAN', '', '2024-10-03 08:23:54'),
('PE000037', '8', '1', 'ika-yohana-ratnasari', '', '', '6281572324144', NULL, '3502030016', 'KABUPATEN PONOROGO', 'BUNGKAL', 'SAMBILAWANG', 'Dukuh Patran RT/RW 03/01 Desa Sambilawang Bungkal', '0', 'aktif', NULL, '', '35021', '9365146127cdffb173cee314ccb6571b.jpg', 'P', 'RUMAHAN', '', '2024-10-14 01:25:32'),
('PE000038', '8', '1', 'testingnotif', '', '', 'null', NULL, '3502170001', 'KABUPATEN PONOROGO', 'PONOROGO', 'PAJU', '-', '0', 'aktif', NULL, '0', '0', '91ddccc2e8ca7fb0c084fb3b219ad268.png', 'L', 'RUMAHAN', '', '2024-10-23 04:48:07'),
('PE000039', '8', '1', 'lukman-asifuddin', '', '', '6285785263558', NULL, '3502040008', 'KABUPATEN PONOROGO', 'SAMBIT', 'CAMPUREJO', 'Mlandangan, Campurejo, Kec. Sambit, Kabupaten Ponorogo, Jawa Timur', '0', 'aktif', NULL, '', '3502093112920001', '2faa7c33a63a88e65660c97315282e89.jpg', 'L', 'RUMAHAN', '', '2024-11-02 01:49:34'),
('PE000040', '8', '1', 'sumarsih', '', '', '62895703376633', NULL, '3502040006', 'KABUPATEN PONOROGO', 'SAMBIT', 'BEDINGIN', 'Jl Surtikanti RT/RW 01/01 Bedingin Sambit', '0', 'aktif', NULL, '', '3502046712700002', 'bf0b8a2398aca5eb9ae36634811daa66.jpeg', 'L', 'RUMAHAN', '', '2024-11-29 09:27:53'),
('PE000041', '8', '1', 'fery-dwi-martdiawan', '', '', '6287761646911', NULL, '3502030015', 'KABUPATEN PONOROGO', 'BUNGKAL', 'KUPUK', 'Dukuh Patran, Kupuk, Kec. Bungkal, Kabupaten Ponorogo, Jawa Timur 63462', '0', 'aktif', NULL, '', '3502092503990001', 'd74957dd47d9c877dc5d82822dfab7e2.jpg', 'L', 'RUMAHAN', '', '2025-02-24 09:04:51'),
('PE000042', '8', '1', 'agus-wardana', '', '', '6281991088880', NULL, '3502040002', 'KABUPATEN PONOROGO', 'SAMBIT', 'WRINGINANOM', 'Dukuh Krajan RT/RW 01/01 Desa Wringinanom Sambit', '0', 'aktif', NULL, '', '3502112408960001', 'b53af19abba5ac2f83d4ff97f3e49023.jpeg', 'L', 'RUMAHAN', '', '2024-12-07 09:20:08'),
('PE000043', '8', '1', 'backbonesambit', '', '', '1', NULL, '3502040006', 'KABUPATEN PONOROGO', 'SAMBIT', 'BEDINGIN', 'bedingin', '1', 'aktif', NULL, '1', '1', '', 'L', 'RUMAHAN', '', '2025-01-01 03:24:07'),
('PE000044', '8', '1', 'veri-toko', '', '', '6285736147999', NULL, '3502030016', 'KABUPATEN PONOROGO', 'BUNGKAL', 'SAMBILAWANG', 'Kabupaten Ponorogo', '0', 'aktif', NULL, '', '35021', '', 'L', 'RUMAHAN', '', '2026-02-17 03:45:55'),
('PE000045', '8', '1', 'nanang-dianarko-toko', '', '', '6282334100046', NULL, '3502030015', 'KABUPATEN PONOROGO', 'BUNGKAL', 'KUPUK', 'Dukuh Patran RT/RW 02/02 Desa Kupuk Bungkal', '0', 'aktif', NULL, '', '350203306760041', '', 'L', 'RUMAHAN', '', '2025-01-03 01:48:09'),
('PE000046', '8', '1', 'luki-novitasari', '', '', '62', NULL, '3502040002', 'KABUPATEN PONOROGO', 'SAMBIT', 'WRINGINANOM', 'Dukuh Tambong RT/RW 04/02 Desa Wringinanom Sambit', '0', 'aktif', NULL, '', '35021', '', 'P', 'RUMAHAN', '', '2025-01-03 01:48:09'),
('PE000047', '8', '1', 'sarno-mandor', '', '', '6281359068194', NULL, '3502040002', 'KABUPATEN PONOROGO', 'SAMBIT', 'WRINGINANOM', 'Banyuripan, Wringinanom, Kec. Sambit, Kabupaten Ponorogo, Jawa Timur', '0', 'aktif', NULL, '', '35021', '', 'L', 'RUMAHAN', '', '2025-01-03 01:48:09'),
('PE000048', '8', '1', 'heri-kasiono', '', '', '62895807407474', NULL, '3502030016', 'KABUPATEN PONOROGO', 'BUNGKAL', 'SAMBILAWANG', 'Dukuh Ngijo RT/RW 01/02 Desa Sambilawang Bungkal', '0', 'aktif', NULL, '', '3502032700475000', '', 'L', 'RUMAHAN', '', '2025-01-03 01:48:09'),
('PE000049', '8', '1', 'sulastri-anggres', '', '', 'null', NULL, '3502040002', 'KABUPATEN PONOROGO', 'SAMBIT', 'WRINGINANOM', 'Tambang, RT/RW 03/01, Wringinanom, Kec. Sambit, Kabupaten Ponorogo, Jawa Timur 63474', '0', 'aktif', NULL, '', '35021', '', 'P', 'RUMAHAN', '', '2025-01-03 02:09:30'),
('PE000050', '8', '1', 'wateno', '', '', '6285859556328', NULL, '3502030016', 'KABUPATEN PONOROGO', 'BUNGKAL', 'SAMBILAWANG', 'Dukuh Suki, RT/RW 02/02, Sambilawang, Kec. Bungkal, Kabupaten Ponorogo, Jawa Timur 60111', '0', 'aktif', NULL, '', '3502030507700007', 'cea1b4cdc9d659f1ddc2e4b6ea784c54.jpg', 'L', 'RUMAHAN', '', '2025-01-04 09:15:09'),
('PE000051', '8', '1', 'imam-choirul-anam', '', '', '6285816647365', NULL, '3502040002', 'KABUPATEN PONOROGO', 'SAMBIT', 'WRINGINANOM', 'Dukuh Tambong RT/RW 03/02 Desa Wringinanom Sambit', '0', 'aktif', NULL, '', '3502041503960001', 'f5a58b7ad52ec7783ab420a45615d0bb.jpeg', 'L', 'RUMAHAN', '', '2025-01-23 01:58:26'),
('PE000052', '8', '1', 'bagas-mimbo-handoko', '', '', '6281233142065', NULL, '3502040002', 'KABUPATEN PONOROGO', 'SAMBIT', 'WRINGINANOM', 'Jl. Parikesit, Nambang, Wringinanom, Kec. Sambit, Kabupaten Ponorogo, Jawa Timur 63474', '0', 'aktif', NULL, '', '3502091809050001', '809e2e20222423af4f588c0ca5602bf8.jpg', 'L', 'RUMAHAN', '', '2025-02-13 02:25:48'),
('PE000053', '8', '1', 'nur-ikhwan', '', '', '6285213068992', NULL, '3502040006', 'KABUPATEN PONOROGO', 'SAMBIT', 'BEDINGIN', 'Jl. A. Yani, RT/RW : 002/001, Kel. Bedingin, Kec. Sambit', '0', 'aktif', NULL, '', '3502040309920002', '5af6f750f1a4bd8d64291c6f8dd7b810.jpg', 'L', 'RUMAHAN', '', '2025-03-03 11:30:49'),
('PE000054', '8', '1', 'ansori', '', '', '6283141604273', NULL, '3502040005', NULL, NULL, NULL, 'Dukuh Banyon RT/RW 02/01 Nglewan Sambit', '123@gmail.com', '', NULL, '0', '3502041207760001', '6e2f6c10066b853b6864988fd6f8add3.jpg', 'L', 'RUMAHAN', '', '2025-03-20 07:43:33'),
('PE000055', '8', '1', 'eko-setiawan', '', '', '6282247541118', NULL, '3502040002', NULL, NULL, NULL, 'Dukuh Tambong RT/RW 04/01 Desa Wringinanom Sambit', '123@gmail.com', '', NULL, '0', '3502040704890001', '1e375f297621d426aa01cae6c0033ea2.jpeg', 'L', 'RUMAHAN', '', '2025-04-02 07:50:30'),
('PE000056', '8', '1', 'yulius-subhil-mubarok', '', '', '6281230811631', NULL, '3502040005', NULL, NULL, NULL, 'Dkh. Banyon, RT/RW : 002/001, Kel. Nglewan, Kec. Sambit', '123@gmail.com', '', NULL, '0', '3502041907960002', '15eb885bcb845b33091bc20668775c82.jpg', 'L', 'RUMAHAN', '', '2025-04-06 06:58:16'),
('PE000057', '8', '1', 'wawan-sumarwan', '', '', 'nul', NULL, '3502090012', NULL, NULL, NULL, 'JL. Bagus Sekali no 678', 'hsdshg@s.m', '', NULL, '-9898398384', '5236526525242121', '4760ca2e34a8287fb5ac0bdb4d8fc6f6.png', 'L', 'RUMAHAN', '', '2025-06-08 09:02:05'),
('PE000058', '8', '1', 'somat', '', '', '0812353505599', NULL, '3502010002', NULL, NULL, NULL, 'jl. Raya', 'woke@gmail.com', '', NULL, '32432424324324', '32223432433434', '4d6788baae2364e8f8a4e0281b39a410.png', 'L', 'RUMAHAN', '', '2025-06-10 08:47:10'),
('PE000059', '8', '1', 'yanto', '', '', '081273737373', NULL, '3502010002', NULL, NULL, NULL, 'jl. Raya', 'wo@gmail.com', '', NULL, '0433243243424432', '32432324343324', '5cbe276928aa7481051306b2de932359.png', 'L', 'RUMAHAN', '', '2025-06-10 09:16:05'),
('PE000060', '8', '1', 'nisi-voluptate-natus-soluta-esse-consequatur-deser', '', '', 'Quia aliquam vi', NULL, '3502010002', NULL, NULL, NULL, 'Odio do molestias do', 'Sunt non Nam volupt', '', NULL, 'Sapiente minus o', '1234657431240', '1c8d910eb836c0f085eb29c8ec8d9cd2.png', 'L', 'RUMAHAN', '', '2025-06-12 13:52:19'),
('PE000061', '8', '1', 'alias-esse-aut-quisquam-ipsam-velit-tempora-sint-e', '', '', 'Esse laboris al', NULL, '3502020002', NULL, NULL, NULL, 'Iure magna dicta rer', 'Quisquam porro molli', '', NULL, 'Aliquid rem volu', '32234565324564', 'd2bef3387ac48efb344d583e3ce2d1a9.jpg', 'L', 'RUMAHAN', '', '2025-06-12 18:05:55'),
('PE000062', '8', '1', 'ipsum-ullamco-in-explicabo-dolorem-reprehenderit-h', '', '', 'Dolor hic venia', NULL, '3502020014', NULL, NULL, NULL, 'Consequatur assumen', 'In at reprehenderit', '', NULL, 'Nulla quibusdam ', '32234565324564', '77dee24ddc3d1ad5a07b4bca4774e013.jpg', 'P', 'RUMAHAN', '', '2025-06-12 18:15:16'),
('PE000063', '8', '1', 'quos-pariatur-pariatur-nulla-dignissimos-veniam-ne', '', '', 'In enim est cor', NULL, '3502010003', NULL, NULL, NULL, 'Consequuntur eiusmod', 'Porro consequuntur i', '', NULL, 'Officiis tempor ', '32234565324564', 'c05bca4bd77a4b0301ab799dbf3eeeab.jpg', 'P', 'RUMAHAN', '', '2025-06-12 19:04:13'),
('PE000064', '8', '1', 'quo-nostrud-officia-sunt-molestiae-ut-enim-aliquam', '', '', 'Dolore dolorem ', NULL, '3502010003', NULL, NULL, NULL, 'Voluptatem unde mole', 'Qui minima ratione a', '', NULL, 'Est aut velit do', '32234565324564', '0881b9a320152fc449589e61ce60db41.jpg', 'L', 'RUMAHAN', '', '2025-06-13 04:45:47'),
('PE000065', '8', '1', 'modi-ut-quis-eligendi-ut-est-velit-quo-atque-quaer', '', '', 'Doloribus volup', NULL, '3502010002', NULL, NULL, NULL, 'Error omnis nisi Nam', 'Qui voluptate dolore', '', NULL, 'Est sint explica', '5346564324567543', 'c364805ef2fc82c11ff020a972c014f3.png', 'P', 'RUMAHAN', '', '2025-06-25 19:32:38'),
('PE000066', '8', '1', 'dolor-sapiente-ex-veniam-commodo-deleniti-ut-quis-', '', '', 'Et aliquid cons', NULL, '3502010004', NULL, NULL, NULL, 'Molestiae ipsum velit sint voluptate necessitatibus vitae numquam molestiae quos eius amet', 'pycymu@mailinator.com', '', NULL, 'Sed similique en', '5346564324567543', 'ec75708ae28a4c6951c3769061ab50a8.jpg', 'P', 'RUMAHAN', '', '2025-06-25 19:10:55'),
('PE000067', '8', '1', 'ridwan-prasetyo', '', '', '6282210979697', NULL, '3502030016', NULL, NULL, NULL, 'Jl. Beringin,Patran, Sambilawang, Kec. Bungkal, Kabupaten Ponorogo, Jawa Timur', '123@gmail.com', '', NULL, '0', '3502031405940002', 'fb33470d4d482d17e15519c0cd77bec7.jpg', 'L', 'RUMAHAN', '', '2025-06-27 03:47:27'),
('PE000068', '8', '1', 'esse-est-hic-sed-tempor-qui-ad-amet-nulla-tempor-a', '', '', 'Duis sit tempor', NULL, '3502010007', NULL, NULL, NULL, 'Et voluptas occaecat', 'Praesentium nihil te', '', NULL, 'Illum numquam su', '5346564324567543', '541f68f8bdde31f843a63fc4796c3398.png', 'P', 'RUMAHAN', '', '2025-06-28 07:42:13'),
('PE000069', '8', '1', 'marsa-amalia', '', '', 'Sunt fugit ea d', NULL, '3502010001', NULL, NULL, NULL, 'Accusantium voluptat', 'Est ducimus blandit', '', NULL, '0', '32234565324564', '6e4bc6ad71acfcdd3d55cc43e8e80f22.png', 'L', 'RUMAHAN', '', '2025-06-28 07:46:45'),
('PE000070', '8', '1', 'aris-subagyo', '', '', '6287702223321', NULL, '3502040002', NULL, NULL, NULL, 'Jl. Ahmad Yani,Krajan, Wringinanom, Kec. Sambit, Kabupaten Ponorogo, Jawa Timur', '123@gmail.com', '', NULL, '0', '3502031509680001', 'e6c07577414c165033f560adc5a1b963.jpeg', 'L', 'RUMAHAN', '', '2025-07-04 06:57:26'),
('PE000071', '8', '1', 'ida-erlina', '', '', '6285655769402', NULL, '3502040002', NULL, 'SAMBIT', 'WRINGINANOM', 'RT 01/RW 01 Dukuh Banyuripan Desa Wringinanom, Kec. Sambit', '123@gmail.com', '', NULL, '0', '3502047107780001', 'e80274490a4a5a8398156b1fde1b1a8c.jpeg', 'P', 'RUMAHAN', '', '2025-08-13 04:18:11'),
('PE000072', '8', '1', 'nasrullah-hasri', '', '', '6287795199234', NULL, '3502040012', NULL, NULL, NULL, 'Kantor LPK Ganbaro Gakkou, Besuki, Kec. Sambit, Kabupaten Ponorogo, Jawa Timur', '123@gmail.com', '', NULL, '0', '3311081106900001', '728fb8b24332dfdf313b082e17b017e8.jpg', 'L', 'RUMAHAN', '', '2025-07-24 07:15:00'),
('PE000073', '8', '1', 'koiriah', '', '', '628977924942', NULL, '3502030016', NULL, NULL, NULL, 'Ngijo,Sambilawang, Kec. Bungkal, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502034310800004', '7224be694cdc5751bf4089babf0a4f4a.jpeg', 'L', 'RUMAHAN', '', '2025-07-28 02:29:09'),
('PE000074', '8', '1', 'sunarmi', '', '', '6285746136759', NULL, '3502040006', NULL, NULL, NULL, 'Jl. A.Yani, Krajan, Bedingin, Kec. Sambit, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3506256705770003', '395e1a8dd8b51ce40f1dfd53a665d42c.jpeg', 'P', 'RUMAHAN', '', '2025-08-07 06:47:20'),
('PE000075', '8', '1', 'risky-multiyani', '', '', '85264813754', NULL, '3502040007', NULL, NULL, NULL, 'Jl. Ngasinan RT.3/RW.2, Dkh. Ngelo, Ds. Bancangan, Kec. Sambit, Ponorogo', '123@gmail.com', '', NULL, '0', '33062203502045', '22754a2baa4df27472b5d5c564f24a19.jpeg', 'P', 'RUMAHAN', '', '2025-10-06 01:19:00'),
('PE000076', '8', '1', 'hengky-indra-kusuma', '', '', '6285204500554', NULL, '3502040009', NULL, NULL, NULL, 'Dukuh Bedali RT.004/RW.001, Desa Campursari, Kecamatan Sambit, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3518060601860007', '37b3b1d2472e8cc7db15f5dff7342caf.jpeg', 'L', 'RUMAHAN', '', '2025-10-07 02:28:24'),
('PE000077', '8', '1', 'danang-wahyu-rino-syahputra', '', '', '6285135847858', NULL, '3502040015', NULL, NULL, NULL, 'RT.02/RW.01 Jogowangsan, Kemuning, Kec. Sambit, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502040201050002', 'cb8ff8af98a3c08223fa90032ab62a50.jpeg', 'L', 'RUMAHAN', '', '2025-10-07 08:36:00'),
('PE000078', '8', '1', 'punjung-ali-setiawan', '', '', '6285737428014', NULL, '3502030016', NULL, NULL, NULL, 'Dukuh Ngijo RT 002/RW 002, Sambilawang, Kec. Bungkal, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '350203043960001', '6230c9071acad0d8d578758a3d2214cd.jpeg', 'L', 'RUMAHAN', '', '2025-10-20 03:42:57'),
('PE000079', '8', '1', 'rusmi', '', '', '6285816132028', NULL, '3502030016', NULL, NULL, NULL, 'Jl. Kresno,Ngijo Gedangan RT. 2/RW. 2, Sambilawang, Kec. Bungkal, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502034804820003', 'c13ce9dd2874bacce3826700a9e83f54.jpeg', 'P', 'RUMAHAN', '', '2025-10-31 06:26:45'),
('PE000080', '8', '1', 'alfian-asfahani-putra', '', '', '6285888642010', NULL, '3502040014', NULL, NULL, NULL, 'Asemkolik,Bangsalan, Kec. Sambit, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502040210980001', '576296ed26c1590bbe207eb8f7e2276e.jpeg', 'L', 'RUMAHAN', '', '2025-11-04 03:24:49'),
('PE000081', '8', '1', 'agung-cahyono', '', '', '6281230043937', NULL, '3502040008', NULL, NULL, NULL, 'Kebatan,Campurejo, Kec. Sambit, Kabupaten Ponorogo', '....@gmail.com', '', NULL, '0', '3502041704930001', '56682272a941033f39b74c36fb053ebb.jpeg', 'L', 'RUMAHAN', '', '2025-12-13 06:49:17'),
('PE000082', '8', '1', 'bayu-arga-adhi-sukma', '', '', '6282359151622', NULL, '3502040010', NULL, NULL, NULL, 'Jl. Salam I, Bulu I, Bulu, Kec. Sambit, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502041407890002', '2038226fb55247df337dce212a566441.jpeg', 'L', 'RUMAHAN', '', '2025-12-19 01:41:49'),
('PE000083', '8', '1', 'baitul-mawardani', '', '', '6288217906093', NULL, '3502040011', NULL, NULL, NULL, ' (RUKO) Jl. Ponorogo-Trenggalek RT. 01/RW. 02, Dsn. Tamansari, Des. Sambit, Kec. Sambit, Kabupaten PonorogoPonorogo', '123@gmail.com', '', NULL, '0', '3502105401040001', 'd016572b3bcc52b8bd349b929d09dc3f.jpeg', 'P', 'RUMAHAN', '', '2025-12-22 07:18:43'),
('PE000084', '8', '1', 'zainal-abidin', '', '', '6285855447769', NULL, '3502030016', NULL, NULL, NULL, 'Dkh. Ngijo RT. 002/RW. 002, Sambilawang, Kec. Bungkal, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502032404980002', '79366468c7a4759bf3d4acd32c9558c6.jpeg', 'L', 'RUMAHAN', '', '2025-12-27 02:22:48'),
('PE000085', '8', '1', 'khoirur-rosiqi', '', '', '6285855857014', NULL, '3502040008', NULL, NULL, NULL, 'Jl. Ponorogo-Trenggalek, Mlandangan, Campurejo, Kec. Sambit, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3529070808020004', 'f290bd2d616b0865e48191142fa7b37a.jpg', 'L', 'RUMAHAN', '', '2026-02-02 04:33:32'),
('PE000086', '8', '1', 'andika-ahmad-weldan', '', '', '6282132435391', NULL, '3502030015', NULL, NULL, NULL, 'Dkh. Ringin Surup RT.002/RW. 001, Kupuk, Bungkal. Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502031109000003', 'd1f76f49ace57efe1b9816b43e4b92d8.jpg', 'L', 'RUMAHAN', '', '2026-03-30 23:47:38'),
('PE000087', '8', '1', 'nanik-suyatini', '', '', '6287700990606', NULL, '3502040014', NULL, NULL, NULL, 'Jl. Menak Agung No. 19, Bangsalan, Kec. Sambit, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502045904660001', 'b11b53444e083aa556ffb84015ce2339.jpg', 'P', 'RUMAHAN', '', '2026-04-09 06:06:48'),
('PE000088', '8', '1', 'muji-mulyani', '', '', '6282141218725', NULL, '3502040002', NULL, NULL, NULL, 'Dkh. Nambang RT. 001/RW. 001, Wringinanom, Kec. Sambit, Kabupaten Ponorogo', '....@gmail.com', '', NULL, '0', '3502045308830006', 'c318fb67b62660744e0deb8610d8f015.jpg', 'L', 'RUMAHAN', '', '2026-04-20 02:01:55'),
('PE000089', '8', '1', 'wahyu-imam-nurhanto', '', '', '6285736467543', NULL, '3502040005', NULL, NULL, NULL, 'Dkh. Depok RT. 002/RW. 002, Nglewan, Kec. Sambit, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502040107930001', 'ec96834378c7353cb582e2f0a7b33a0c.jpg', 'L', 'RUMAHAN', '', '2026-04-28 04:06:36'),
('PE000090', '8', '1', 'retno-tri-wuryani', '', '', '6281212441139', NULL, '3502040004', NULL, NULL, NULL, 'Dsn. Glagahan RT. 002/RW. 001, Maguwan, Kec. Sambit, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502045301810005', 'c3cd24cdd51e61b8d3174fd6c9b26676.jpg', 'P', 'RUMAHAN', '', '2026-04-28 06:20:21'),
('PE000091', '8', '1', 'sutaji', '', '', '6285334804303', NULL, '3502030017', NULL, NULL, NULL, 'Dkh. Kwajon Kulon RT. 001/RW. 002, Kwajon, Kec. Bungkal, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502031406820003', '84272b02ef5b6ccfc98fc6251663b67f.jpg', 'L', 'RUMAHAN', '', '2026-05-02 03:54:10'),
('PE000092', '8', '1', 'samud', '', '', '6285235357604', NULL, '3502040005', NULL, NULL, NULL, 'Dkh. Depok RT. 002/RW. 002, Nglewan, Kec. Sambit, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502041905580001', '7b1a34af695aed0955c8025820a287f8.jpg', 'L', 'RUMAHAN', '', '2026-05-05 04:39:07'),
('PE000093', '8', '1', 'agus-suwarno', '', '', '6285649013653', NULL, '3502030015', NULL, NULL, NULL, 'Jl. Ahmad Yani No.2 RT. 003/RW. 001, Patran, Kupuk, Kec. Bungkal, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502030606680005', 'b15b1c65014ec408f3ef3c8c56a4ae62.jpg', 'L', 'RUMAHAN', '', '2026-05-07 03:28:46'),
('PE000094', '8', '1', 'nagrio-pislendra-lembong', '', '', '6285731144704', NULL, '3502040006', NULL, 'SAMBIT', 'BEDINGIN', 'Dkh. Kambangrejo RT. 002/RW. 001, Bedingin, Kec. Sambit, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3578061903880004', '0a382ff38f8e8c0043acfc9380a28259.jpg', 'L', 'RUMAHAN', '', '2026-06-27 01:57:40'),
('PE000095', '8', '1', 'sri-ani', '', '', '6282233568680', NULL, '3502040002', NULL, NULL, NULL, 'Dkh. Krajan RT. 001/RW. 002, Wringinanom, Kec. Sambit, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502044506720004', '416e476f4a17326501603366af0db4c7.jpg', 'P', 'RUMAHAN', '', '2026-05-15 00:46:16'),
('PE000096', '8', '1', 'devi-sartika', '', '', '6283870809703', NULL, '3502030017', NULL, NULL, NULL, 'Dkh. Kwajon Kulon RT. 001/RW. 001, Kwajon, Kec. Bungkal, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502035208840003', '740a4c2917d87c71ebc9b4a413b730da.jpg', 'P', 'RUMAHAN', '', '2026-05-18 01:49:31'),
('PE000097', '8', '1', 'andika-irfan-ramadani', '', '', '62822572060', NULL, '3502040008', NULL, NULL, NULL, 'Jl. Ponorogo - Trenggalek, Mlandangan, Campurejo, Kec. Sambit, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502141602940001', 'cf22d4ceec47a0d8ca204f5acc47e141.jpg', 'L', 'RUMAHAN', '', '2026-05-19 07:21:46'),
('PE000098', '8', '1', 'hariyadi-rt01rw01', '', '', '6281217168041', NULL, '3502040002', NULL, NULL, NULL, 'Dkh. Krajan RT. 001/RW. 001, Wringinanom, Kec. Sambit, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502040712820001', '64573534084e99d5a7e5b702edb4badd.png', 'L', 'RUMAHAN', '', '2026-05-25 02:55:56'),
('PE000099', '8', '1', 'sofyan-nur-wahid', '', '', '6285232890460', NULL, '3502040005', NULL, NULL, NULL, 'Dkh. Depok RT. 003/RW. 002, Nglewan, Kec. Sambit, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502042003950001', '20ecf7d0278f4c41864f6ffb7816058a.jpg', 'L', 'RUMAHAN', '', '2026-05-30 08:35:03'),
('PE000100', '8', '1', 'latiful-khoiron', '', '', '6285258864547', NULL, '3502040005', NULL, NULL, NULL, 'Dkh. Depok RT. 003/RW. 002, Nglewan, Kec. Sambit, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502040108980001', '1018cbc9a706dab3bbe7232b422e31db.jpg', 'L', 'RUMAHAN', '', '2026-06-01 01:06:20'),
('PE000101', '8', '1', 'muhammad-sjahson-mujtahiddin', '', '', '6287825588165', NULL, '3502040005', NULL, NULL, NULL, 'Dkh. Banyon RT. 001/RW. 001, Nglewan, Kec. Sambit, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502173108650003', '9356ad88d0a3d6362ffaf8f689b2fcc1.jpg', 'L', 'RUMAHAN', '', '2026-06-02 00:20:46'),
('PE000102', '8', '1', 'dimas-ghio-ferry', '', '', '628211477655', NULL, '3502040008', NULL, NULL, NULL, 'Jl. Jetis Sambit, Mlandangan, Campurejo, Kec. Sambit, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502040707000005', 'de743017f323488591260165dd4da4ea.jpg', 'L', 'RUMAHAN', '', '2026-06-02 07:16:28'),
('PE000103', '8', '1', 'arya-henindra-febriansyah', '', '', '6285231636076', NULL, '3502040006', NULL, NULL, NULL, 'Jl. Gendari Dkh. Krajan RT. 003/RW. 002, Bedingin, Kec. Sambit, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '3502160502010003', '5cf23c23e52a42ae9dfc601b262553d9.jpg', 'L', 'RUMAHAN', '', '2026-06-04 02:29:14'),
('PE000104', '8', '1', 'soiman', '', '', '6285204874198', NULL, '3502040005', NULL, NULL, NULL, 'Ds. Depok RT. 002/RW. 002, Nglewan, Kec. Sambit, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '15447401000236', 'f3143608478e6e828c6c7576f3b56faa.jpg', 'L', 'RUMAHAN', '', '2026-06-17 09:00:35'),
('PE000105', '8', '1', 'soiman-masjid-nahdlatul-ummah', '', '', '6285204874198', NULL, '3502040005', NULL, NULL, NULL, 'Masjid Nahdlatul Ummah, Area Sawah, Nglewan, Kec. Sambit, Kabupaten Ponorogo', '123@gmail.com', '', NULL, '0', '15447401000236', 'c2ac8c0df42547e5021f8d4aa2190497.jpg', 'L', 'RUMAHAN', '', '2026-06-23 02:25:59'),
('PE000106', '8', '1', 'indra-nur-cahyono', '', '', '6281218055307', NULL, '3502040008', NULL, NULL, NULL, 'Dsn. Mlandangan RT. 03/RW. 01, Campurejo, Kec. Sambit, Kabupaten Ponorogo', '....@gmail.com', '', NULL, '0', '15390609000149', 'a55b7beb588d1a7484830d30c2d7e40b.jpg', 'L', 'RUMAHAN', '', '2026-06-25 03:31:42'),
('PE000107', '8', '1', 'muhamad-suhadak', '', '', '6289665489429', NULL, '3502040007', NULL, NULL, NULL, 'Warkp Joledheng, Jl. Ponorogo - Trenggalek, Mlandangan, Bancangan, Kec. Sambit, Kabupaten Ponorogo', '-@gmail.com', '', NULL, '0', '3502161703830002', 'a26470bd76db120f7cd82fea596762ee.png', 'L', 'RUMAHAN', '', '2026-07-31 07:55:05'),
('PE000108', '8', '1', 'endah-budi-utami', '', '', '6282326035817', NULL, '3502040002', NULL, 'SAMBIT', 'WRINGINANOM', 'Dkh. Krajan, Wringinanom, Kec. Sambit, Kabupaten Ponorogo', '123@gmail.con', '', NULL, '0', '3502045202640001', 'd4b4b871e3a778d310fdff0309a7401b.jpg', 'P', 'RUMAHAN', '', '2026-08-15 08:03:14'),
('PE000109', '8', '1', 'sutini', '', '', ' 85292576376', NULL, '3502040002', NULL, NULL, NULL, 'Dkh. Tambang RT. 002/RW. 001, Wringinanom, Kec. Sambit, Kabupaten Ponorogo', '123@gmail.con', '', NULL, '0', '3502045103750001', '103f487af035b41582ba07cf566db48c.jpg', 'P', 'RUMAHAN', '', '2026-09-04 06:21:58'),
('PE000110', '8', '1', 'sriani-2', '', '', '6282233568680', NULL, '3502040002', NULL, NULL, NULL, 'Dkh. Krajan, Wringinanom, Kec. Sambit, Kabupaten Ponorogo', '123@gmail.con', '', NULL, '0', '3502044506720004', '4b3af9a762b1c93e8bfc584d56976aba.jpg', 'P', 'RUMAHAN', '', '2026-09-07 01:09:49'),
('PE000111', '8', '1', 'ahmad-zaini', '', '', '6281973041930', NULL, '3502040011', NULL, NULL, NULL, 'Dkh. Tamansari, Sambit, Kec. Sambit, Kabupaten Ponorogo', '123@gmail.con', '', NULL, '0', '3527112810970003', '42fb97c4e3510a842746fbce045101cc.jpg', 'L', 'RUMAHAN', '', '2026-10-31 07:17:59'),
('PG000001', '1', '1', 'Faruqi R', '', '', '-', '-', '3502110020', 'KABUPATEN PONOROGO', 'BALONG', 'NGAMPEL', '-', '-', '', NULL, NULL, '-', '', '', '', '', '2021-10-01 00:34:36'),
('PG000002', '1', '1', 'Taufik', '', '', '-', '-', '3502110020', 'KABUPATEN PONOROGO', 'BALONG', 'NGAMPEL', '-', '-', '', NULL, NULL, '-', '', '', '', '', '2021-10-01 00:37:02'),
('PG000003', '1', '1', 'etik', '', '', '-', '-', '3502110020', 'KABUPATEN PONOROGO', 'BALONG', 'NGAMPEL', '-', '-', '', NULL, NULL, '-', '', '', '', '', '2021-10-01 00:37:27'),
('PG000004', '1', '1', 'An Muslim Rohman', '', '', '-', '-', '3502110020', 'KABUPATEN PONOROGO', 'BALONG', 'NGAMPEL', '-', '-', '', NULL, NULL, '-', '', '', '', '', '2024-03-16 06:45:23'),
('PG000005', '1', '1', 'ausya', '', '', '-', '-', '3502110020', 'KABUPATEN PONOROGO', 'BALONG', 'NGAMPEL', '-', '-', '', NULL, NULL, '-', '', '', '', '', '2021-10-01 00:39:10'),
('PG000006', '1', '1', 'Arli Ahmadari', '', '', '-', '-', '3502110020', 'KABUPATEN PONOROGO', 'BALONG', 'NGAMPEL', '-', '-', '', NULL, NULL, '-', '', '', '', '', '2021-10-01 00:45:16'),
('PG000008', '1', '1', 'Miftahul Arifki', '', '', '-', '-', '3502110020', 'KABUPATEN PONOROGO', 'BALONG', 'NGAMPEL', '-', '-', '', NULL, NULL, '-', '', '', '', '', '2021-10-01 00:48:36'),
('PG000010', '1', '1', 'Mawan Aldiyansah', '', '', '-', '-', '3502110020', 'KABUPATEN PONOROGO', 'BALONG', 'NGAMPEL', '-', '-', '', NULL, NULL, '-', '', '', '', '', '2021-10-01 00:49:55'),
('PG000014', '5', '1', 'Imam Basori', '', '', '6285257338821', '6285257338821', '3502080002', 'KABUPATEN PONOROGO', 'MLARAK', 'CANDI', 'Candi Mlarak', 'imambasori2107@gmail.com', '', NULL, NULL, '3502082107980001', '', '', '', '', '2024-03-16 07:25:28'),
('PG000016', '5', '1', 'Zulfan Azkia Shofa', '', '', '085641617371', '085641617371', '3502050002', 'KABUPATEN PONOROGO', 'SAWOO', 'PANGKAL', 'Dukuh Pangkal RT.01/RW.01', '', '', NULL, NULL, '', '', '', '', '', '2024-03-18 02:13:09'),
('PG000018', '1', '1', 'arifqi', '', '', '6285707442228', '0', '3502070003', 'KABUPATEN PONOROGO', 'PULUNG', 'BEDRUG', '0', '0', '', NULL, NULL, '0', '', '', '', '', '2024-03-18 02:28:27'),
('PG000020', '1', '1', 'Yusril Fahrezi Firdaus', '', '', '6285895770137', '6285895770137', '3502050002', 'KABUPATEN PONOROGO', 'SAWOO', 'PANGKAL', 'Pangkal', 'yusril10tkjb2019@gmail.com', '', NULL, NULL, '3502052604040004', '', '', '', '', '2024-05-01 08:22:39'),
('PG000022', '2', '1', 'enggar pradepta', '', '', '6287702223321', '0', '3502030012', 'KABUPATEN PONOROGO', 'BUNGKAL', 'BANCAR', 'desa bancar', '0', '', NULL, NULL, '0', '', '', '', '', '2024-05-09 09:02:15'),
('PG000023', '5', '1', 'Alvian Andre Wibisono', '', '', '0895630812251', '0895630812251', '3502190010', 'KABUPATEN PONOROGO', 'JENANGAN', 'JENANGAN', 'Dukuh Gogokalang RT.04/RW.01 Desa Jenangan', 'loropikir728@gmail.com', '', NULL, NULL, '3502180907010003', '', '', '', '', '2024-05-10 01:21:35'),
('PG000024', '1', '1', 'arifqi', '', '', '85707442228', '0', '3502020021', 'KABUPATEN PONOROGO', 'SLAHUNG', 'GOMBANG', 'RENTFIRE', '', '', NULL, NULL, '', '', '', '', '', '2024-07-16 00:42:53'),
('PG000028', '1', '1', 'pkl1', '', '', '1', '1', '3502020003', 'KABUPATEN PONOROGO', 'SLAHUNG', 'SLAHUNG', '1', '1', '', NULL, NULL, '1', '', '', '', '', '2024-12-17 02:35:55'),
('PG000032', '1', '1', 'Ridwan Arisandi', '', '', '6289687938315', '', '3502170008', 'KABUPATEN PONOROGO', 'PONOROGO', 'BANGUNSARI', '', '', '', NULL, NULL, '', '', '', '', '', '2025-04-14 07:24:59'),
('PG000034', '1', '1', 'webapptesting', '', '', '6281219999821', '6281219999821', '3502170006', 'KABUPATEN PONOROGO', 'PONOROGO', 'PURBOSUMAN', 'Purbosuman', '', '', NULL, NULL, '3502101154890006', '', '', '', '', '2025-05-22 11:59:14'),
('PG000035', '5', '1', 'rio bagus saputro', '', '', '6282141406012', '1', '3502030016', 'KABUPATEN PONOROGO', 'BUNGKAL', 'SAMBILAWANG', 'sambilawang ', '0', '', NULL, NULL, '1', '', '', '', '', '2025-11-15 02:10:10'),
('PG000036', '1', '1', 'Lisvi Fitria Nur Lita', '', '', 'null', '0', '3502090005', 'KABUPATEN PONOROGO', 'SIMAN', 'SEKARAN', '0', '0', '', NULL, NULL, '0', '', '', '', '', '2025-11-19 04:39:39'),
('PG000037', '5', '1', 'fariel arvianta', '', '', '21', '21', '3502050002', 'KABUPATEN PONOROGO', 'SAWOO', 'PANGKAL', 'sawoo', '', '', NULL, NULL, '', '', '', '', '', '2026-01-25 07:19:02'),
('PG000038', '5', '1', 'Arjun Ariyanto', '', '', '62', '0', '3502080002', 'KABUPATEN PONOROGO', 'MLARAK', 'CANDI', '0', '09', '', NULL, NULL, '0', '', '', '', '', '2026-06-01 03:23:06'),
('PG000040', '5', '1', 'dwi saputra', '', '', '62', '0', '3502080002', 'KABUPATEN PONOROGO', 'MLARAK', 'CANDI', 'candi', '0', '', NULL, NULL, '00', '', '', '', '', '2026-06-27 01:53:42'),
('US000001', '8', '1', 'dolore-voluptas-mollit-explicabo-magna-illo-quis-t', '', '', 'Dolor ipsum qui', NULL, '3502020007', NULL, NULL, NULL, 'Natus debitis omnis ', 'Eu ducimus commodi ', '', NULL, 'Nulla beatae fug', '1234657431243', '358d66a8a13ca23552fc26854a8c9837.png', 'P', 'BISNIS', '', '2025-06-12 13:24:06'),
('US000002', '8', '1', 'duis-earum-quaerat-libero-dolor-tempore-non-elit-n', '', '', 'Accusamus est e', NULL, '3502020006', NULL, NULL, NULL, 'Et modi asperiores molestiae consequat Dolore sit ea dolor aliquip pariatur Velit tempor est', 'leda@mailinator.com', '', NULL, 'Ea odit eiusmod ', '1234656431243', 'eb53972c7afe0ef676a35df7eef90260.jpg', 'L', 'BISNIS', '', '2025-06-12 16:04:27'),
('US000003', '8', '1', 'ex-sit-similique-eum-ut-quis', '', '', 'Est pariatur Al', NULL, '3502061005', NULL, NULL, NULL, 'Laboriosam pariatur', 'Voluptatem et nihil', '', NULL, 'Dolores facilis ', '32234565324564', '8ef455bdf48413da96b021948d0eee97.jpg', 'L', 'BISNIS', '', '2025-06-22 17:40:55'),
('US000004', '8', '1', 'amet-inventore-sunt-incidunt-molestias-ut-temporib', '', '', 'Necessitatibus ', NULL, '3502010002', NULL, NULL, NULL, 'Doloremque dolor lau', 'Itaque duis amet au', '', NULL, '0', '32234565324564', 'de76a2a9786a148addc9b0d3d3350d0d.png', 'P', 'BISNIS', '', '2025-06-25 15:16:20'),
('US000005', '8', '1', 'duis-eum-praesentium-esse-commodi-iste', '', '', 'Inventore in ne', NULL, '3502060001', NULL, NULL, NULL, 'Ut id vel provident', 'Id nemo proident n', '', NULL, 'Alias ea ratione', '32234565324564', '6e2ac18a666f8c287672b593867cbb0b.png', 'P', 'BISNIS', '', '2025-06-22 17:41:35'),
('US000006', '8', '1', 'amet-velit-dolores-deserunt-dolorem-culpa-ut-exerc', '', '', 'Sint praesentiu', NULL, '3502050011', NULL, NULL, NULL, 'Quas tenetur fuga Qui ducimus non sed nemo natus est sunt ipsam', 'kovuv@mailinator.com', '', NULL, 'Voluptas dolores', '5346564324567543', 'e3f38af9f1a242462f9768817117c5ea.jpg', 'L', 'BISNIS', '', '2025-06-25 13:44:36'),
('US000007', '8', '1', 'labore-id-sunt-blanditiis-odio-non-amet-hic-dolore', '', '', 'Corrupti omnis ', NULL, '3502020004', NULL, NULL, NULL, 'Consequatur aut sed eos error dolores ad excepteur repellendus Eu nisi adipisci laboris eveniet', 'loxuripu@mailinator.com', '', NULL, 'Laudantium nisi ', '5346564324567543', '2c013d8020aec22b9cda8ed23af9ee03.jpg', 'L', 'BISNIS', '', '2025-06-25 18:27:05');

-- --------------------------------------------------------

--
-- Struktur dari tabel `person`
--

CREATE TABLE `person` (
  `id` int(4) NOT NULL,
  `name` varchar(50) NOT NULL,
  `email` varchar(50) NOT NULL,
  `fact` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `prosedure_permintaan_wifi`
--

CREATE TABLE `prosedure_permintaan_wifi` (
  `master_id` int(11) GENERATED ALWAYS AS (cast(substr(`IDPERMINTAAN`,3) as unsigned)) STORED,
  `KODEAPP` char(2) NOT NULL DEFAULT 'G',
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
-- Dumping data untuk tabel `prosedure_permintaan_wifi`
--

INSERT INTO `prosedure_permintaan_wifi` (`KODEAPP`, `kategori_perangkat_jaringan`, `kode_kontrol_distribusi`, `IDPERMINTAAN`, `IDPENGGUNA`, `STATUS`, `TGL_AKTIFPUTUS`, `STATUSPASANG`, `ALASAN`, `STATUSTINDAKANALAT`, `STATUSLANGGANAN`, `UBAHKONEKSI`, `IDPAKET`, `DISURVEY`, `DIACC`, `DIPROSES`, `CREATED`, `DILAPORKAN`, `TGLDIACC`, `TGLSURVEY`, `TGLDIPROSES`, `TGLSELESAI`, `STATUSALAT`, `VERIFIED`, `VERIFIED_AT`, `JENISJARINGAN`, `JENISMEMBER`, `IDBIAYA`, `inserted_at`, `updated_at`) VALUES
('G', 1, 'X1B', 'RQ000001', 'PE000001', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000002', 'PG000015', 'PG000015', 'PG000015', 'PG000015', 'PG000015', '2024-04-01 15:19:58', '2024-04-01 09:11:28', '2024-04-24 14:03:15', '2024-05-03 08:54:28', 'SEWA', 'PG000015', '2024-05-03 09:55:42', 'KABEL', 0, 'IN000006', '2024-03-22 02:29:46', '2024-05-03 02:55:42'),
('G', 0, '0', 'RQ000002', 'PE000002', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000002', 'PG000015', 'PG000015', 'PG000015', 'PG000015', 'PG000015', '2024-04-01 15:20:03', '2024-04-01 09:11:35', '2024-05-03 08:54:36', '2024-05-03 08:59:36', 'SEWA', 'PG000015', '2024-05-03 09:55:57', 'KABEL', 0, 'IN000007', '2024-03-22 02:35:39', '2024-05-13 00:49:14'),
('G', 0, '0', 'RQ000003', 'PE000003', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000003', 'PG000015', 'PG000015', 'PG000015', 'PG000015', 'PG000015', '2024-04-01 15:26:10', '2024-04-01 09:11:46', '2024-05-03 08:55:45', '2024-05-03 09:03:49', 'SEWA', 'PG000015', '2024-05-03 09:56:13', 'KABEL', 0, 'IN000008', '2024-03-22 02:39:12', '2024-05-03 02:56:13'),
('G', 1, 'X1B', 'RQ000004', 'PE000004', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000005', 'PG000015', 'PG000015', 'PG000015', 'PG000015', 'PG000015', '2024-04-02 10:45:00', '2024-04-01 09:11:52', '2024-05-03 08:55:51', '2024-05-03 09:13:47', 'SEWA', 'PG000015', '2024-05-03 09:56:29', 'KABEL', 0, 'IN000009', '2024-03-22 02:41:40', '2024-05-03 02:56:29'),
('G', 1, 'X4B', 'RQ000005', 'PE000005', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000003', 'PG000015', 'PG000015', 'PG000015', 'PG000015', 'PG000015', '2024-05-03 10:12:35', '2024-04-03 07:21:39', '2024-05-03 10:16:50', '2024-05-03 10:19:15', 'SEWA', 'PG000015', '2024-05-03 10:20:06', 'KABEL', 0, 'IN000015', '2024-03-22 02:55:40', '2025-11-15 04:07:10'),
('G', 0, '0', 'RQ000006', 'PE000006', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000004', 'PG000015', 'PG000015', 'PG000015', 'PG000015', 'PG000015', '2024-04-02 10:45:05', '2024-04-01 09:12:02', '2024-05-03 08:55:58', '2024-05-03 09:18:48', 'SEWA', 'PG000015', '2024-05-03 09:56:49', 'KABEL', 0, 'IN000010', '2024-03-22 03:01:46', '2024-05-03 02:56:49'),
('G', 0, '0', 'RQ000007', 'PE000007', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', 'PG000015', 'PG000015', 'PG000015', 'PG000015', 'PG000015', '2024-04-02 10:45:10', '2024-04-01 09:12:08', '2024-05-03 08:56:04', '2024-05-03 10:03:59', 'SEWA', 'PG000015', '2024-05-03 10:04:18', 'KABEL', 0, 'IN000012', '2024-03-27 02:29:06', '2024-05-03 03:04:18'),
('G', 0, '0', 'RQ000008', 'PE000008', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000002', 'PG000015', 'PG000015', 'PG000017', 'PG000015', 'PG000017', '2024-04-02 10:45:15', '2024-04-01 09:12:16', '2024-04-23 14:07:40', '2024-04-23 14:13:35', 'SEWA', 'PG000015', '2024-04-24 14:09:51', 'KABEL', 0, 'IN000004', '2024-03-27 02:37:02', '2024-04-24 07:09:51'),
('G', 0, '0', 'RQ000009', 'PE000009', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', 'PG000015', 'PG000015', 'PG000017', 'PG000015', 'PG000015', '2024-04-02 10:45:19', '2024-04-01 09:12:23', '2024-04-23 14:04:43', '2024-05-03 10:05:55', 'SEWA', 'PG000015', '2024-05-03 10:06:44', 'KABEL', 0, 'IN000014', '2024-03-27 02:44:14', '2024-05-03 03:06:44'),
('G', 0, '0', 'RQ000010', 'PE000010', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000003', 'PG000017', 'PG000015', 'PG000017', 'PG000015', 'PG000017', '2024-04-23 09:43:55', '2024-04-22 15:08:57', '2024-04-23 13:54:29', '2024-04-23 14:00:01', 'SEWA', 'PG000015', '2024-04-24 14:11:26', 'KABEL', 0, 'IN000005', '2024-04-01 01:57:49', '2024-04-24 07:11:26'),
('G', 0, '0', 'RQ000011', 'PE000011', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000003', 'PG000017', 'PG000015', 'PG000015', 'PG000015', 'PG000015', '2024-04-01 10:11:34', '2024-04-01 10:06:28', '2024-04-01 10:11:45', '2024-04-01 10:27:45', 'SEWA', 'PG000015', '2024-04-04 09:34:01', 'KABEL', 0, 'IN000002', '2024-04-01 03:01:40', '2024-04-04 02:34:01'),
('G', 0, '0', 'RQ000012', 'PE000012', 'PUTUS', '0000-00-00', 'Terpasang', 'Tes data', 'Sudah diambil', 'Kembali Proses', 0, 'PK000007', 'PG000001', 'PG000001', 'PG000001', 'PG000001', 'PG000001', '2024-04-01 10:28:15', '2024-04-01 10:27:26', '2024-04-01 10:28:29', '2024-04-01 10:30:44', 'SEWA', 'PG000032', '2025-06-14 08:17:41', 'KABEL', 0, 'IN000062', '2024-04-01 03:26:58', '2025-06-29 10:50:42'),
('G', 1, 'X4B', 'RQ000013', 'PE000013', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000003', 'PG000017', 'PG000015', 'PG000017', 'PG000015', 'PG000017', '2024-04-23 09:44:00', '2024-04-22 15:08:50', '2024-04-23 13:54:22', '2024-04-23 14:04:04', 'SEWA', 'PG000015', '2024-04-24 14:08:42', 'KABEL', 0, 'IN000003', '2024-04-17 02:17:45', '2024-04-24 07:08:42'),
('G', 0, '0', 'RQ000014', 'PE000014', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000002', 'PG000015', 'PG000015', 'PG000015', 'PG000015', 'PG000015', '2024-05-03 10:12:46', '2024-05-03 10:10:26', '2024-05-03 10:16:56', '2024-05-03 10:23:00', 'SEWA', 'PG000015', '2024-05-03 10:23:15', 'KABEL', 0, 'IN000016', '2024-04-24 07:00:42', '2024-05-03 03:23:15'),
('G', 0, '0', 'RQ000015', 'PE000015', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000003', 'PG000015', 'PG000015', 'PG000015', 'PG000015', 'PG000015', '2024-05-03 10:12:51', '2024-05-03 10:10:32', '2024-05-03 10:17:04', '2024-05-03 10:26:14', 'SEWA', 'PG000015', '2024-05-03 10:26:33', 'KABEL', 0, 'IN000017', '2024-04-24 07:02:47', '2024-05-03 03:29:23'),
('G', 0, '0', 'RQ000016', 'PE000016', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000003', 'PG000014', 'PG000015', 'PG000015', 'PG000015', 'PG000014', '2024-05-03 08:28:06', '2024-05-01 09:05:21', '2024-05-03 08:56:11', '2024-05-03 18:24:40', 'SEWA', 'PG000015', '2024-05-04 10:12:26', 'KABEL', 0, 'IN000018', '2024-04-24 07:25:01', '2024-05-04 03:12:26'),
('G', 1, 'X4B', 'RQ000017', 'PE000017', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000003', 'PG000017', 'PG000015', 'PG000023', 'PG000008', 'PG000023', '2024-05-04 09:24:03', '2024-05-04 09:20:37', '2024-05-10 08:51:35', '2024-05-11 16:42:57', 'SEWA', 'PG000008', '2024-05-13 07:23:23', 'KABEL', 0, 'IN000021', '2024-05-02 13:46:37', '2024-05-13 00:23:23'),
('G', 0, '0', 'RQ000018', 'PE000018', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', 'PG000015', 'PG000015', 'PG000015', 'PG000015', 'PG000015', '2024-05-04 08:27:37', '2024-05-04 08:26:33', '2024-05-04 08:28:00', '2024-05-04 08:33:59', 'SEWA', 'PG000015', '2024-05-04 10:18:16', 'KABEL', 0, 'IN000019', '2024-05-04 01:23:34', '2024-05-04 03:18:16'),
('G', 0, '0', 'RQ000019', 'PE000019', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000002', 'PG000015', 'PG000015', 'PG000015', 'PG000015', 'PG000015', '2024-05-04 08:42:37', '2024-05-04 08:40:33', '2024-05-04 08:42:43', '2024-05-04 08:47:53', 'SEWA', 'PG000015', '2024-05-06 14:23:56', 'KABEL', 0, 'IN000020', '2024-05-04 01:40:22', '2024-05-06 07:23:56'),
('G', 0, '0', 'RQ000020', 'PE000020', 'GAGAL', '0000-00-00', 'Gagal', 'tidak jadi karena uang gak ada', 'Belum Terpasang', '', 0, 'PK000003', NULL, NULL, NULL, 'PG000015', NULL, NULL, NULL, NULL, NULL, 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2024-05-15 07:29:18', '2024-06-01 01:44:40'),
('G', 0, '0', 'RQ000021', 'PE000021', 'GAGAL', '0000-00-00', 'Gagal', 'Tidak jadi', 'Belum Terpasang', '', 0, 'PK000003', 'PG000015', NULL, NULL, 'PG000022', NULL, NULL, '2024-06-01 17:15:11', NULL, '2024-06-21 08:43:25', 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2024-05-27 13:18:23', '2024-06-21 01:43:40'),
('G', 0, '0', 'RQ000022', 'PE000022', 'GAGAL', '0000-00-00', 'Gagal', 'tidak jadi', 'Belum Terpasang', '', 0, 'PK000001', NULL, NULL, NULL, 'PG000015', NULL, NULL, NULL, NULL, NULL, 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2024-06-12 06:55:45', '2024-07-11 01:36:16'),
('G', 0, '0', 'RQ000023', 'PE000023', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', NULL, NULL, NULL, 'PG000008', NULL, NULL, NULL, NULL, NULL, 'SEWA', 'PG000008', '0000-00-00 00:00:00', 'KABEL', 0, 'IN000022', '2024-07-03 19:16:29', '0000-00-00 00:00:00'),
('G', 0, '0', 'RQ000024', 'PE000024', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', NULL, NULL, NULL, 'PG000008', NULL, NULL, NULL, NULL, NULL, 'SEWA', 'PG000008', '0000-00-00 00:00:00', 'KABEL', 0, 'IN000023', '2024-07-03 19:18:08', '0000-00-00 00:00:00'),
('G', 0, '0', 'RQ000025', 'PE000025', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000004', 'PG000015', 'PG000015', 'PG000015', 'PG000015', 'PG000015', '2024-08-28 14:49:41', '2024-07-10 15:38:30', '2024-08-28 14:49:48', '2024-08-28 14:54:43', 'SEWA', 'PG000015', '2024-08-30 08:19:05', 'KABEL', 0, 'IN000032', '2024-07-10 03:18:57', '2024-08-30 01:19:05'),
('G', 0, '0', 'RQ000026', 'PE000026', 'GAGAL', '0000-00-00', '', '', '', '', 0, 'undefined', NULL, NULL, NULL, 'PG000015', NULL, NULL, NULL, NULL, NULL, 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2024-07-10 03:29:07', '0000-00-00 00:00:00'),
('G', 0, '0', 'RQ000027', 'PE000031', 'GAGAL', '0000-00-00', 'Gagal', 'data ganda', 'Belum Terpasang', '', 0, 'PK000001', NULL, NULL, NULL, 'PG000015', NULL, NULL, NULL, NULL, NULL, 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2024-07-11 01:10:22', '2024-07-21 04:01:26'),
('G', 0, '0', 'RQ000028', 'PE000032', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', 'PG000024', 'PG000024', 'PG000024', 'PG000024', 'PG000024', '2024-07-21 11:04:57', '2024-07-21 11:01:31', '2024-07-21 11:05:03', '2024-07-21 11:11:18', 'SEWA', 'PG000015', '2024-07-23 13:31:53', 'KABEL', 0, 'IN000030', '2024-07-21 04:00:31', '2024-09-11 06:01:27'),
('G', 1, 'X4B', 'RQ000029', 'PE000033', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000009', 'PG000016', 'PG000015', 'PG000016', 'PG000015', 'PG000016', '2024-08-26 07:45:15', '2024-08-24 11:39:11', '2024-08-28 09:34:13', '2024-08-28 09:41:35', 'SEWA', 'PG000015', '2024-08-28 10:30:13', 'KABEL', 0, 'IN000031', '2024-07-27 03:00:44', '2024-08-28 03:30:13'),
('G', 1, '0', 'RQ000030', 'PE000034', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', 'PG000025', 'PG000015', 'PG000025', 'PG000015', 'PG000025', '2024-10-08 11:11:14', '2024-10-08 07:36:13', '2024-10-16 09:55:32', '2024-10-18 07:08:42', 'SEWA', 'PG000015', '2024-10-18 08:55:33', 'KABEL', 0, 'IN000033', '2024-09-24 01:23:17', '2024-10-18 01:55:33'),
('G', 0, '0', 'RQ000031', 'PE000035', 'GAGAL', '0000-00-00', 'Gagal', 'tes data', 'Belum Terpasang', '', 0, 'PK000002', NULL, NULL, NULL, 'PG000001', NULL, NULL, NULL, NULL, NULL, 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2024-09-28 05:09:25', '2024-10-02 03:50:49'),
('G', 1, 'X4B', 'RQ000032', 'PE000036', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000010', 'PG000025', 'PG000015', 'PG000025', 'PG000015', 'PG000025', '2024-10-08 11:11:19', '2024-10-08 07:36:20', '2024-10-16 09:55:21', '2024-10-16 10:05:31', 'SEWA', 'PG000015', '2024-10-18 08:58:00', 'KABEL', 0, 'IN000036', '2024-10-03 08:23:54', '2024-10-18 01:58:00'),
('G', 0, '0', 'RQ000033', 'PE000037', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', 'PG000017', 'PG000015', 'PG000004', 'PG000015', 'PG000025', '2024-10-28 11:18:32', '2024-10-28 09:10:16', '2024-11-04 12:33:50', '2024-11-12 20:31:12', 'SEWA', 'PG000015', '2024-11-13 15:09:47', 'KABEL', 0, 'IN000037', '2024-10-14 01:25:32', '2024-11-13 08:09:47'),
('G', 0, '0', 'RQ000034', 'PE000038', 'GAGAL', '0000-00-00', 'Gagal', 'tes data', 'Belum Terpasang', '', 0, 'PK000001', 'PG000001', NULL, NULL, 'PG000001', NULL, NULL, '2024-10-23 11:49:13', NULL, '2024-10-23 11:50:41', 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2024-10-23 04:48:07', '2024-10-23 09:27:30'),
('G', 1, 'X1B', 'RQ000035', 'PE000039', 'PUTUS', '0000-00-00', 'Terpasang', 'Pindah rumah', 'Sudah diambil', '', 0, 'PK000002', 'PG000025', 'PG000015', 'PG000025', 'PG000015', 'PG000025', '2024-11-11 13:53:58', '2024-11-10 21:35:16', '2024-11-12 20:31:58', '2024-11-12 20:41:53', 'SEWA', 'PG000015', '2024-11-13 15:18:26', 'KABEL', 0, 'IN000038', '2024-11-02 01:49:34', '2026-03-05 23:11:45'),
('G', 0, '0', 'RQ000036', 'PE000040', 'GAGAL', '0000-00-00', 'Gagal', 'tidak jadi', 'Belum Terpasang', '', 0, 'PK000002', 'PG000014', 'PG000015', NULL, 'PG000015', NULL, '2024-12-04 08:13:54', '2024-12-03 20:26:41', NULL, '2024-12-03 20:47:06', 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2024-11-29 09:27:53', '2025-01-18 07:01:47'),
('G', 0, '0', 'RQ000037', 'PE000041', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', 'PG000027', 'PG000015', 'PG000027', 'PG000015', 'PG000027', '2024-12-10 13:12:02', '2024-12-10 11:30:38', '2024-12-10 17:07:14', '2024-12-10 17:09:31', 'SEWA', 'PG000015', '2024-12-14 11:44:56', 'KABEL', 0, 'IN000039', '2024-12-04 07:12:49', '2025-02-24 09:08:55'),
('G', 1, 'X4B', 'RQ000038', 'PE000042', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000002', 'PG000014', 'PG000015', 'PG000014', 'PG000015', 'PG000014', '2024-12-25 16:25:06', '2024-12-25 13:47:41', '2024-12-25 18:41:54', '2024-12-25 18:49:39', 'SEWA', 'PG000015', '2024-12-26 10:05:57', 'KABEL', 0, 'IN000040', '2024-12-07 09:20:08', '2024-12-26 03:05:57'),
('G', 0, '0', 'RQ000039', 'PE000043', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', NULL, NULL, NULL, 'PG000024', NULL, NULL, NULL, NULL, NULL, 'SEWA', 'PG000024', '0000-00-00 00:00:00', 'KABEL', 0, 'IN000041', '2025-01-01 03:24:07', '0000-00-00 00:00:00'),
('G', 1, 'X1B', 'RQ000040', 'PE000044', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', NULL, NULL, NULL, 'PG000015', NULL, NULL, NULL, NULL, NULL, 'SEWA', 'PG000015', '0000-00-00 00:00:00', 'KABEL', 0, 'IN000042', '2025-01-03 01:48:09', '2026-02-17 03:49:10'),
('G', 1, 'X4B', 'RQ000041', 'PE000045', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', NULL, NULL, NULL, 'PG000015', NULL, NULL, NULL, NULL, NULL, 'SEWA', 'PG000015', '0000-00-00 00:00:00', 'KABEL', 0, 'IN000043', '2025-01-03 01:48:09', '0000-00-00 00:00:00'),
('G', 1, 'X4B', 'RQ000042', 'PE000046', 'PUTUS', '0000-00-00', 'Terpasang', 'Sudah tidak ada yang memakai karena ditunggal kerja di luar', 'Sudah diambil', '', 0, 'PK000001', NULL, NULL, NULL, 'PG000015', NULL, NULL, NULL, NULL, NULL, 'SEWA', 'PG000015', '0000-00-00 00:00:00', 'KABEL', 0, 'IN000044', '2025-01-03 01:48:09', '2026-04-06 05:35:46'),
('G', 1, 'X4B', 'RQ000043', 'PE000047', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', NULL, NULL, NULL, 'PG000015', NULL, NULL, NULL, NULL, NULL, 'SEWA', 'PG000015', '0000-00-00 00:00:00', 'KABEL', 0, 'IN000045', '2025-01-03 01:48:09', '0000-00-00 00:00:00'),
('G', 1, 'X4B', 'RQ000044', 'PE000048', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', NULL, NULL, NULL, 'PG000015', NULL, NULL, NULL, NULL, NULL, 'SEWA', 'PG000015', '0000-00-00 00:00:00', 'KABEL', 0, 'IN000046', '2025-01-03 01:48:09', '0000-00-00 00:00:00'),
('G', 0, '0', 'RQ000045', 'PE000049', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', NULL, NULL, NULL, 'PG000015', NULL, NULL, NULL, NULL, NULL, 'SEWA', 'PG000015', '0000-00-00 00:00:00', 'KABEL', 0, 'IN000047', '2025-01-03 02:09:30', '0000-00-00 00:00:00'),
('G', 0, '0', 'RQ000046', 'PE000050', 'GAGAL', '0000-00-00', 'Gagal', 'tidak jadi pasang', 'Belum Terpasang', '', 0, 'PK000002', 'PG000014', 'PG000015', NULL, 'PG000015', NULL, '2025-01-18 13:56:53', '2025-01-07 08:54:28', NULL, '2025-01-18 11:50:04', 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2025-01-04 09:15:09', '2025-02-06 08:02:09'),
('G', 0, '0', 'RQ000047', 'PE000051', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000002', 'PG000006', 'PG000015', 'PG000006', 'PG000015', 'PG000006', '2025-01-25 15:07:38', '2025-01-24 16:31:52', '2025-01-26 18:54:36', '2025-01-26 18:59:37', 'SEWA', 'PG000015', '2025-02-05 10:36:59', 'KABEL', 0, 'IN000048', '2025-01-23 01:58:26', '2025-02-05 03:36:59'),
('G', 0, '0', 'RQ000048', 'PE000052', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000014', 'PG000029', 'PG000015', 'PG000029', 'PG000015', 'PG000029', '2025-02-21 19:28:29', '2025-02-20 11:47:41', '2025-02-21 21:06:38', '2025-02-21 21:10:07', 'SEWA', 'PG000015', '2025-02-25 15:03:12', 'KABEL', 0, 'IN000049', '2025-02-13 02:25:48', '2025-02-25 08:03:12'),
('G', 1, 'X4B', 'RQ000049', 'PE000053', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000013', 'PG000014', 'PG000015', 'PG000014', 'PG000015', 'PG000014', '2025-03-04 13:09:31', '2025-03-03 16:38:43', '2025-03-04 13:40:36', '2025-03-04 19:19:06', 'SEWA', 'PG000015', '2025-03-05 08:30:28', 'KABEL', 0, 'IN000050', '2025-02-24 11:43:17', '2025-03-05 01:30:28'),
('G', 0, '0', 'RQ000050', 'PE000054', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000002', 'PG000017', 'PG000015', 'PG000017', 'PG000015', 'PG000017', '2025-03-22 08:07:21', '2025-03-21 09:08:20', '2025-03-25 00:19:47', '2025-03-25 00:24:55', 'SEWA', 'PG000015', '2025-03-25 09:51:44', 'KABEL', 0, 'IN000051', '2025-03-20 07:43:33', '2025-03-25 02:51:44'),
('G', 0, '0', 'RQ000051', 'PE000055', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000004', 'PG000017', 'PG000015', 'PG000017', 'PG000015', 'PG000017', '2025-04-08 14:04:58', '2025-04-08 10:12:51', '2025-04-10 08:11:22', '2025-04-10 08:57:48', 'SEWA', 'PG000015', '2025-04-12 09:42:35', 'KABEL', 0, 'IN000052', '2025-04-02 07:50:30', '2025-04-12 02:42:35'),
('G', 0, '0', 'RQ000052', 'PE000056', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000013', 'PG000029', 'PG000032', 'PG000026', 'PG000015', 'PG000026', '2025-04-16 12:01:04', '2025-04-15 10:41:20', '2025-04-22 18:39:26', '2025-04-22 18:47:01', 'SEWA', 'PG000032', '2025-04-23 16:19:11', 'KABEL', 0, 'IN000053', '2025-04-06 06:58:16', '2025-04-23 09:19:11'),
('G', 0, '0', 'RQ000053', 'PE000057', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000002', 'PG000034', 'PG000034', 'PG000034', 'PG000034', 'PG000034', '2025-06-09 14:35:57', '2025-06-09 14:26:23', '2025-06-09 14:39:38', '2025-06-09 14:47:02', 'SEWA', 'PG000034', '2025-06-09 17:31:48', 'KABEL', 0, 'IN000054', '2025-06-08 09:02:05', '2025-06-09 10:31:48'),
('G', 0, '0', 'RQ000054', 'PE000058', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000002', 'PG000034', 'PG000034', 'PG000034', 'PG000034', 'PG000034', '2025-06-10 15:53:57', '2025-06-10 15:50:47', '2025-06-10 15:55:58', '2025-06-10 15:58:09', 'BELI', 'PG000034', '2025-06-10 16:00:24', 'KABEL', 0, 'IN000055', '2025-06-10 08:47:10', '2025-06-10 09:00:24'),
('G', 0, '0', 'RQ000055', 'PE000059', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', 'PG000034', 'PG000034', 'PG000034', 'PG000034', 'PG000034', '2025-06-10 16:19:52', '2025-06-10 16:18:20', '2025-06-10 16:21:45', '2025-06-10 16:24:48', 'BELI', 'PG000034', '2025-06-10 16:25:33', 'KABEL', 0, 'IN000056', '2025-06-10 09:16:05', '2025-06-10 09:25:33'),
('G', 0, '0', 'RQ000056', 'US000001', 'ACTIVE', '0000-00-00', 'Berhasil', 'tes aja', '', 'Kembali Proses', 0, 'PK000012', 'PG000034', 'PG000034', 'PG000034', 'PG000034', 'PG000034', '2025-06-12 20:30:50', '2025-06-12 11:10:49', '2025-06-12 20:31:05', '2025-06-12 20:31:57', 'SEWA', 'PG000032', '2025-06-16 10:06:38', 'KABEL', 0, 'IN000065', '2025-06-12 04:08:48', '2025-06-16 03:06:38'),
('G', 0, '0', 'RQ000057', 'PE000060', 'PUTUS', '0000-00-00', 'Terpasang', '', 'Hak milik', '', 0, 'PK000010', 'PG000034', 'PG000034', 'PG000034', 'PG000034', 'PG000034', '2025-06-12 20:52:38', '2025-06-12 20:39:47', '2025-06-12 20:52:45', '2025-06-12 21:12:23', 'BELI', 'PG000034', '2025-06-12 23:02:47', 'KABEL', 0, 'IN000059', '2025-06-12 04:11:34', '2025-06-12 19:49:05'),
('G', 0, '0', 'RQ000058', 'US000002', 'GAGAL', '0000-00-00', 'Gagal', 'Test data', 'Belum Terpasang', '', 0, 'PK000001', 'PG000034', 'PG000034', 'PG000034', 'PG000034', NULL, '2025-06-13 01:00:26', '2025-06-12 23:06:00', '2025-06-13 01:09:58', '2025-06-13 00:56:31', 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2025-06-12 16:04:27', '2025-06-16 03:06:27'),
('G', 0, '0', 'RQ000059', 'PE000061', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', 'PG000034', 'PG000034', 'PG000034', 'PG000034', 'PG000034', '2025-06-13 11:03:21', '2025-06-13 01:05:35', '2025-06-13 11:03:27', '2025-06-13 11:48:21', 'BELI', 'PG000032', '2025-06-16 10:06:18', 'KABEL', 0, 'IN000063', '2025-06-12 18:05:26', '2025-06-16 03:06:18'),
('G', 0, '0', 'RQ000060', 'PE000062', 'PUTUS', '0000-00-00', 'Terpasang', 'Nihil quibusdam sit ', 'Hak milik', '', 0, 'PK000007', 'PG000034', 'PG000034', 'PG000034', 'PG000034', 'PG000034', '2025-06-13 01:15:36', '2025-06-13 01:14:37', '2025-06-13 01:15:48', '2025-06-13 01:29:35', 'BELI', 'PG000034', '2025-06-13 02:14:02', 'KABEL', 0, 'IN000061', '2025-06-12 18:14:03', '2025-06-13 07:35:33'),
('G', 0, '0', 'RQ000061', 'PE000063', 'PUTUS', '0000-00-00', 'Terpasang', 'Id est anim consect', 'Hak milik', '', 0, 'PK000003', 'PG000034', 'PG000034', 'PG000034', 'PG000034', 'PG000034', '2025-06-13 02:04:32', '2025-06-13 02:03:41', '2025-06-13 02:04:46', '2025-06-13 02:05:36', 'BELI', 'PG000034', '2025-06-13 02:13:41', 'KABEL', 0, 'IN000060', '2025-06-12 19:03:12', '2025-06-13 07:36:00'),
('G', 0, '0', 'RQ000062', 'PE000064', 'GAGAL', '0000-00-00', 'Gagal', 'Test data', 'Belum Terpasang', '', 0, 'PK000009', 'PG000034', NULL, NULL, 'PG000034', NULL, NULL, '2025-06-13 11:45:26', NULL, '2025-06-13 11:45:47', 'BELI', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2025-06-13 04:45:18', '2025-06-16 03:06:06'),
('G', 0, '0', 'RQ000063', 'US000003', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000014', 'PG000034', 'PG000034', 'PG000034', 'PG000034', 'PG000034', '2025-06-25 22:16:28', '2025-06-16 18:12:06', '2025-06-25 22:16:33', '2025-06-25 22:34:45', 'SEWA', 'PG000032', '2025-07-08 07:57:33', 'KABEL', 0, 'IN000072', '2025-06-13 04:54:09', '2025-07-08 00:57:33'),
('G', 0, '0', 'RQ000064', 'US000004', 'GAGAL', '0000-00-00', 'Gagal', 'tes data', 'Belum Terpasang', '', 0, 'PK000012', 'PG000034', 'PG000034', NULL, 'PG000034', NULL, '2025-06-26 02:26:55', '2025-06-25 22:15:55', NULL, '2025-06-25 22:16:20', 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2025-06-18 13:11:56', '2025-07-08 00:57:59'),
('G', 0, '0', 'RQ000065', 'US000005', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000009', 'PG000034', 'PG000034', 'PG000034', 'PG000034', 'PG000034', '2025-06-23 00:42:02', '2025-06-23 00:41:16', '2025-06-23 00:42:08', '2025-06-23 00:43:32', 'SEWA', 'PG000034', '2025-06-23 00:43:54', 'KABEL', 0, 'IN000067', '2025-06-22 17:40:10', '2025-06-22 17:43:54'),
('G', 0, '0', 'RQ000066', 'US000006', 'GAGAL', '0000-00-00', 'Gagal', 'tes', 'Belum Terpasang', '', 0, 'PK000001', NULL, NULL, NULL, 'PG000034', NULL, NULL, NULL, NULL, NULL, 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2025-06-25 13:44:36', '2025-08-03 16:51:21'),
('G', 0, '0', 'RQ000067', 'PE000065', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000004', 'PG000034', 'PG000034', 'PG000034', 'PG000034', 'PG000034', '2025-06-26 02:33:56', '2025-06-25 23:47:45', '2025-06-26 02:34:49', '2025-06-26 02:36:15', 'SEWA', 'PG000034', '2025-06-26 02:37:02', 'KABEL', 0, 'IN000068', '2025-06-25 16:47:24', '2025-06-25 19:37:02'),
('G', 0, '0', 'RQ000068', 'US000007', 'GAGAL', '0000-00-00', 'Gagal', 'tes', 'Belum Terpasang', '', 0, 'PK000001', 'PG000005', NULL, NULL, 'PG000034', NULL, NULL, '2025-06-26 02:27:28', NULL, '2025-08-03 23:51:57', 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2025-06-25 18:27:05', '2025-08-03 16:52:19'),
('G', 0, '0', 'RQ000069', 'PE000066', 'GAGAL', '0000-00-00', 'Gagal', 'tes', 'Belum Terpasang', '', 0, 'PK000008', NULL, NULL, NULL, 'PG000034', NULL, NULL, NULL, NULL, NULL, 'BELI', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2025-06-25 19:10:55', '2025-08-03 16:51:33'),
('G', 1, 'X4B', 'RQ000070', 'PE000067', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000007', 'PG000006', 'PG000032', 'PG000006', 'PG000032', 'PG000014', '2025-07-02 10:02:09', '2025-07-02 09:46:18', '2025-07-02 10:19:24', '2025-07-03 07:09:38', 'SEWA', 'PG000032', '2025-07-03 14:34:14', 'KABEL', 0, 'IN000070', '2025-06-27 03:47:27', '2025-07-03 07:34:14'),
('G', 0, '0', 'RQ000071', 'PE000068', 'GAGAL', '0000-00-00', 'Gagal', 'tes data', 'Belum Terpasang', '', 0, 'PK000009', 'PG000034', 'PG000034', 'PG000034', 'PG000034', NULL, '2025-06-28 14:42:25', '2025-06-28 14:41:49', '2025-06-28 14:42:30', '2025-06-28 14:42:13', 'BELI', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2025-06-28 07:41:41', '2025-07-08 00:57:47'),
('G', 0, '0', 'RQ000072', 'PE000069', 'PUTUS', '0000-00-00', 'Terpasang', 'Maiores recusandae ', 'Proses ambil', '', 0, 'PK000001', 'PG000034', 'PG000034', 'PG000034', 'PG000034', 'PG000034', '2025-06-28 14:46:53', '2025-06-28 14:46:12', '2025-06-28 14:46:59', '2025-06-29 22:39:40', 'SEWA', 'PG000034', '2025-06-29 22:39:50', 'KABEL', 0, 'IN000069', '2025-06-28 07:45:56', '2025-06-29 15:40:55'),
('G', 1, 'X4B', 'RQ000073', 'PE000070', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000002', 'PG000006', 'PG000032', 'PG000006', 'PG000032', 'PG000014', '2025-07-07 16:21:16', '2025-07-07 16:09:25', '2025-07-07 16:35:12', '2025-07-07 18:43:06', 'SEWA', 'PG000032', '2025-07-08 14:04:41', 'KABEL', 0, 'IN000073', '2025-07-04 06:57:26', '2025-07-08 07:04:41'),
('G', 1, 'X1B', 'RQ000074', 'PE000071', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000010', 'PG000004', 'PG000032', 'PG000004', 'PG000032', 'PG000004', '2025-07-23 08:54:01', '2025-07-22 13:06:28', '2025-08-01 07:29:44', '2025-08-11 07:06:57', 'SEWA', 'PG000032', '2025-08-11 14:51:31', 'KABEL', 0, 'IN000077', '2025-07-18 03:10:53', '2025-08-13 04:19:03'),
('G', 0, '0', 'RQ000075', 'PE000072', 'GAGAL', '0000-00-00', 'Gagal', 'pindah ke Whusnet', 'Belum Terpasang', '', 0, 'PK000001', NULL, NULL, NULL, 'PG000032', NULL, NULL, NULL, NULL, NULL, 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2025-07-24 07:15:00', '2025-07-26 13:43:13'),
('G', 1, 'X4B', 'RQ000076', 'PE000073', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000002', 'PG000006', 'PG000032', 'PG000006', 'PG000032', 'PG000006', '2025-07-30 08:32:15', '2025-07-29 16:45:35', '2025-07-31 11:53:26', '2025-07-31 13:12:01', 'SEWA', 'PG000032', '2025-07-31 14:35:19', 'KABEL', 0, 'IN000075', '2025-07-28 02:29:09', '2025-07-31 07:35:19'),
('G', 1, 'X1B', 'RQ000077', 'PE000074', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', 'PG000004', 'PG000005', 'PG000004', 'PG000032', 'PG000004', '2025-08-11 17:49:22', '2025-08-11 17:47:12', '2025-08-11 17:55:28', '2025-08-11 18:02:01', 'SEWA', 'PG000032', '2025-08-12 09:34:29', 'KABEL', 0, 'IN000079', '2025-08-07 06:47:20', '2025-08-12 02:34:29'),
('G', 1, 'X4B', 'RQ000078', 'PE000075', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000013', 'PG000013', 'PG000032', 'PG000013', 'PG000032', 'PG000013', '2025-10-08 13:41:07', '2025-10-08 13:37:50', '2025-10-09 09:26:58', '2025-10-09 09:29:41', 'SEWA', 'PG000032', '2025-10-09 15:16:29', 'KABEL', 0, 'IN000080', '2025-10-06 01:19:00', '2025-10-09 08:16:29'),
('G', 0, '0', 'RQ000079', 'PE000076', 'GAGAL', '0000-00-00', 'Gagal', 'Data di OLT Jetis', 'Belum Terpasang', '', 0, 'PK000001', 'PG000013', 'PG000032', NULL, 'PG000032', NULL, '2025-10-08 13:40:54', '2025-10-08 13:36:15', NULL, '2025-10-08 13:36:59', 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2025-10-07 02:28:24', '2025-10-09 02:13:24'),
('G', 0, '0', 'RQ000080', 'PE000077', 'GAGAL', '0000-00-00', 'Gagal', 'HTB kabel jauh', 'Belum Terpasang', '', 0, 'PK000004', NULL, NULL, NULL, 'PG000032', NULL, NULL, NULL, NULL, NULL, 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2025-10-07 08:36:00', '2025-10-17 00:52:42'),
('G', 0, '0', 'RQ000081', 'PE000078', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000010', 'PG000014', 'PG000005', 'PG000014', 'PG000032', 'PG000014', '2025-10-26 23:39:58', '2025-10-25 08:42:10', '2025-10-27 07:39:34', '2025-10-27 16:49:17', 'SEWA', 'PG000032', '2025-10-28 13:45:43', 'KABEL', 0, 'IN000082', '2025-10-20 03:42:57', '2025-10-28 06:45:43'),
('G', 0, '0', 'RQ000082', 'PE000079', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000002', 'PG000014', 'PG000032', 'PG000014', 'PG000032', 'PG000014', '2025-11-07 07:42:10', '2025-11-06 14:22:23', '2025-11-08 14:34:12', '2025-11-08 14:54:53', 'SEWA', 'PG000032', '2025-11-10 16:31:38', 'KABEL', 0, 'IN000084', '2025-10-31 06:26:45', '2025-11-10 09:31:38'),
('G', 0, '0', 'RQ000083', 'PE000080', 'GAGAL', '0000-00-00', 'Gagal', 'Belum ada jalur', 'Belum Terpasang', '', 0, 'PK000005', NULL, NULL, NULL, 'PG000032', NULL, NULL, NULL, NULL, NULL, 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2025-11-04 03:24:49', '2025-11-08 02:25:42'),
('G', 0, '0', 'RQ000084', 'PE000081', 'GAGAL', '0000-00-00', 'Gagal', 'Pindah ke Aplikasi Jetis', 'Belum Terpasang', '', 0, 'PK000001', NULL, NULL, NULL, 'PG000036', NULL, NULL, NULL, NULL, NULL, 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2025-12-13 06:49:17', '2025-12-19 03:23:18'),
('G', 0, '0', 'RQ000085', 'PE000082', 'GAGAL', '0000-00-00', 'Gagal', 'Pindah ke Aplikasi Jetis', 'Belum Terpasang', '', 0, 'PK000013', NULL, NULL, NULL, 'PG000036', NULL, NULL, NULL, NULL, NULL, 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2025-12-19 01:41:49', '2025-12-19 03:24:44'),
('G', 0, '0', 'RQ000086', 'PE000083', 'GAGAL', '0000-00-00', 'Gagal', 'Pindah ke aplikasi Whusnet', 'Belum Terpasang', '', 0, 'PK000001', 'PG000014', 'PG000036', NULL, 'PG000036', NULL, '2026-01-02 13:47:02', '2025-12-31 14:53:04', NULL, '2025-12-31 14:56:35', 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2025-12-22 07:18:43', '2026-01-06 23:44:47'),
('G', 0, '0', 'RQ000087', 'PE000084', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000009', 'PG000005', 'PG000036', 'PG000010', 'PG000036', 'PG000010', '2025-12-29 10:42:22', '2025-12-29 09:02:44', '2025-12-31 16:13:36', '2025-12-31 16:17:28', 'SEWA', 'PG000036', '2026-01-02 13:50:03', 'KABEL', 0, 'IN000085', '2025-12-27 02:22:48', '2026-01-02 06:50:03'),
('G', 1, '0', 'RQ000088', 'PE000085', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000021', 'PG000014', 'PG000036', 'PG000014', 'PG000036', 'PG000014', '2026-02-06 13:49:11', '2026-02-06 11:09:17', '2026-02-06 20:03:24', '2026-02-06 20:07:16', 'SEWA', 'PG000036', '2026-02-07 14:28:48', 'KABEL', 0, 'IN000087', '2026-02-02 04:33:32', '2026-02-07 07:28:48'),
('G', 0, '0', 'RQ000089', 'PE000086', 'GAGAL', '0000-00-00', 'Gagal', 'Sudah pasang provider lain', 'Belum Terpasang', '', 0, 'PK000021', NULL, NULL, NULL, 'PG000036', NULL, NULL, NULL, NULL, NULL, 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2026-03-30 23:47:38', '2026-04-03 04:04:10'),
('G', 0, '0', 'RQ000090', 'PE000087', 'GAGAL', '0000-00-00', 'Gagal', 'Pindah aplikasi Jetis', 'Belum Terpasang', '', 0, 'PK000020', NULL, NULL, NULL, 'PG000036', NULL, NULL, NULL, NULL, NULL, 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2026-04-09 06:06:48', '2026-04-13 07:08:09'),
('G', 1, 'X1B', 'RQ000091', 'PE000088', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000021', 'PG000014', 'PG000036', 'PG000014', 'PG000036', 'PG000014', '2026-04-23 11:09:23', '2026-04-23 10:38:53', '2026-04-23 12:46:37', '2026-04-23 17:10:09', 'SEWA', 'PG000036', '2026-04-24 10:31:08', 'KABEL', 0, 'IN000089', '2026-04-20 02:01:55', '2026-04-24 03:31:08'),
('G', 1, 'X1B', 'RQ000092', 'PE000089', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000023', 'PG000014', 'PG000036', 'PG000014', 'PG000036', 'PG000014', '2026-04-30 09:56:31', '2026-04-29 12:00:21', '2026-04-30 09:58:03', '2026-04-30 12:49:46', 'SEWA', 'PG000036', '2026-04-30 14:00:18', 'KABEL', 0, 'IN000090', '2026-04-28 04:06:36', '2026-04-30 07:00:18'),
('G', 0, '0', 'RQ000093', 'PE000090', 'GAGAL', '0000-00-00', 'Gagal', 'Tidak tercover', 'Belum Terpasang', '', 0, 'PK000023', 'PG000014', NULL, NULL, 'PG000036', NULL, NULL, '2026-04-30 12:44:22', NULL, '2026-04-30 12:45:49', 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2026-04-28 06:20:21', '2026-05-02 04:38:18'),
('G', 1, 'X1B', 'RQ000094', 'PE000091', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000021', 'PG000013', 'PG000036', 'PG000013', 'PG000036', 'PG000013', '2026-05-05 07:29:33', '2026-05-04 15:34:16', '2026-05-07 11:28:06', '2026-05-07 11:34:37', 'SEWA', 'PG000036', '2026-05-07 15:47:06', 'KABEL', 0, 'IN000091', '2026-05-02 03:54:10', '2026-05-07 08:47:06'),
('G', 1, 'X1B', 'RQ000095', 'PE000092', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000020', 'PG000014', 'PG000036', 'PG000014', 'PG000036', 'PG000014', '2026-05-07 11:20:55', '2026-05-07 09:02:57', '2026-05-07 14:52:14', '2026-05-07 16:19:00', 'SEWA', 'PG000036', '2026-05-08 09:33:18', 'KABEL', 0, 'IN000092', '2026-05-05 04:39:07', '2026-05-08 02:33:18'),
('G', 1, 'X1B', 'RQ000096', 'PE000093', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000020', 'PG000014', 'PG000036', 'PG000014', 'PG000036', 'PG000014', '2026-05-09 07:13:16', '2026-05-07 16:30:09', '2026-05-09 11:55:38', '2026-05-09 13:19:24', 'SEWA', 'PG000036', '2026-05-12 14:24:25', 'KABEL', 0, 'IN000093', '2026-05-07 03:28:46', '2026-05-12 07:24:25'),
('G', 1, 'X1B', 'RQ000097', 'PE000094', 'ACTIVE', '0000-00-00', 'Berhasil', 'Pemasangan terlalu lama, karena masih harus menunggu penarikan ODP', 'Belum Terpasang', 'Kembali Pengajuan', 0, 'PK000021', 'PG000013', 'PG000002', 'PG000013', 'PG000036', 'PG000005', '2026-05-15 16:29:25', '2026-05-15 13:56:19', '2026-05-28 14:16:33', '2026-05-30 05:52:13', 'SEWA', 'PG000036', '2026-05-30 14:14:36', 'KABEL', 0, 'IN000096', '2026-05-11 04:24:49', '2026-06-27 02:07:42'),
('G', 1, 'X1B', 'RQ000098', 'PE000095', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000020', 'PG000013', 'PG000002', 'PG000005', 'PG000036', 'PG000005', '2026-05-15 16:29:35', '2026-05-15 13:57:11', '2026-05-19 17:07:54', '2026-05-19 17:11:32', 'SEWA', 'PG000036', '2026-05-20 15:11:37', 'KABEL', 0, 'IN000094', '2026-05-15 00:46:16', '2026-05-20 08:11:37'),
('G', 1, 'X1B', 'RQ000099', 'PE000096', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', 'PG000005', 'PG000036', 'PG000005', 'PG000036', 'PG000005', '2026-05-25 08:37:49', '2026-05-23 18:23:41', '2026-05-28 17:59:14', '2026-05-28 18:02:39', 'SEWA', 'PG000036', '2026-05-29 14:21:43', 'KABEL', 0, 'IN000095', '2026-05-18 01:49:32', '2026-05-29 07:21:43'),
('G', 1, 'X1B', 'RQ000100', 'PE000097', 'ACTIVE', '0000-00-00', 'Berhasil', 'Pindah ke OLT Jetis', 'Belum Terpasang', 'Kembali Pengajuan', 0, 'PK000001', 'PG000005', 'PG000036', 'PG000037', 'PG000036', 'PG000037', '2026-05-25 09:13:48', '2026-05-23 18:25:33', '2026-06-03 16:22:35', '2026-06-03 17:19:38', 'SEWA', 'PG000036', '2026-06-05 15:38:21', 'KABEL', 0, 'IN000098', '2026-05-19 07:21:46', '2026-06-05 08:38:21'),
('G', 0, '0', 'RQ000101', 'PE000098', 'GAGAL', '0000-00-00', 'Gagal', 'Keberatan biaya over kabel', 'Belum Terpasang', '', 0, 'PK000020', NULL, NULL, NULL, 'PG000036', NULL, NULL, NULL, NULL, NULL, 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2026-05-25 02:55:56', '2026-06-01 00:48:49'),
('G', 1, 'X1B', 'RQ000102', 'PE000099', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000023', 'PG000006', 'PG000036', 'PG000006', 'PG000036', 'PG000006', '2026-06-02 07:38:09', '2026-06-01 16:48:29', '2026-06-03 17:12:47', '2026-06-03 17:15:31', 'SEWA', 'PG000036', '2026-06-06 15:00:53', 'KABEL', 0, 'IN000099', '2026-05-30 08:35:03', '2026-06-06 08:00:53'),
('G', 1, 'X1B', 'RQ000103', 'PE000100', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', 'PG000038', 'PG000036', 'PG000037', 'PG000036', 'PG000037', '2026-06-02 07:53:18', '2026-06-01 14:58:41', '2026-06-03 16:20:30', '2026-06-03 16:22:25', 'SEWA', 'PG000036', '2026-06-05 15:11:46', 'KABEL', 0, 'IN000097', '2026-06-01 01:06:20', '2026-06-05 08:11:46'),
('G', 1, 'X1B', 'RQ000104', 'PE000101', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000026', 'PG000037', 'PG000036', 'PG000037', 'PG000036', 'PG000037', '2026-06-05 13:15:10', '2026-06-04 19:47:33', '2026-06-10 19:17:52', '2026-06-10 19:22:24', 'SEWA', 'PG000036', '2026-06-11 14:41:23', 'KABEL', 0, 'IN000100', '2026-06-02 00:20:46', '2026-06-11 07:41:23'),
('G', 0, '0', 'RQ000105', 'PE000102', 'GAGAL', '0000-00-00', 'Gagal', 'Sudah pasang provider lain', 'Belum Terpasang', '', 0, 'PK000021', NULL, NULL, NULL, 'PG000036', NULL, NULL, NULL, NULL, NULL, 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2026-06-02 07:16:28', '2026-06-02 08:02:16'),
('G', 0, '0', 'RQ000106', 'PE000103', 'GAGAL', '0000-00-00', 'Gagal', 'Tidak setuju dengan biaya tambahannya', 'Belum Terpasang', '', 0, 'PK000020', 'PG000038', NULL, NULL, 'PG000036', NULL, NULL, '2026-06-05 10:06:17', NULL, '2026-06-05 10:07:49', 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2026-06-04 02:29:14', '2026-06-05 03:59:22'),
('G', 1, 'X1B', 'RQ000107', 'PE000104', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000027', 'PG000039', 'PG000036', 'PG000039', 'PG000036', 'PG000039', '2026-06-20 13:35:08', '2026-06-20 10:54:36', '2026-06-20 16:20:33', '2026-06-20 16:22:28', 'SEWA', 'PG000036', '2026-06-23 13:33:25', 'KABEL', 0, 'IN000101', '2026-06-17 09:00:35', '2026-06-23 06:33:25'),
('G', 1, 'X1B', 'RQ000108', 'PE000105', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', 'PG000038', 'PG000036', 'PG000038', 'PG000036', 'PG000038', '2026-07-01 13:34:53', '2026-06-30 19:57:24', '2026-07-01 15:55:51', '2026-07-01 15:58:59', 'SEWA', 'PG000036', '2026-07-02 10:36:38', 'KABEL', 0, 'IN000103', '2026-06-23 02:25:59', '2026-07-02 03:36:38'),
('G', 1, 'X1B', 'RQ000109', 'PE000106', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000020', 'PG000005', 'PG000036', 'PG000040', 'PG000036', 'PG000040', '2026-06-26 13:26:06', '2026-06-26 10:29:15', '2026-06-28 20:13:53', '2026-06-28 20:17:21', 'SEWA', 'PG000036', '2026-06-29 13:26:27', 'KABEL', 0, 'IN000102', '2026-06-25 03:31:42', '2026-06-29 06:26:27'),
('G', 1, '0', 'RQ000110', 'PE000107', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000023', 'PG000040', 'PG000036', 'PG000040', 'PG000036', 'PG000040', '2026-08-03 11:28:43', '2026-08-02 08:51:17', '2026-08-04 20:32:13', '2026-08-04 20:34:37', 'SEWA', 'PG000036', '2026-08-05 14:44:12', 'KABEL', 0, 'IN000104', '2026-07-31 07:55:05', '2026-08-05 07:44:12'),
('G', 1, 'X1B', 'RQ000111', 'PE000108', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000020', 'PG000040', 'PG000036', 'PG000040', 'PG000036', 'PG000040', '2026-08-14 15:22:26', '2026-08-14 15:16:11', '2026-08-14 16:10:21', '2026-08-14 16:12:24', 'SEWA', 'PG000036', '2026-08-15 15:01:48', 'KABEL', 0, 'IN000105', '2026-08-14 07:04:13', '2026-08-15 08:17:48'),
('G', 0, '0', 'RQ000112', 'PE000109', 'GAGAL', '0000-00-00', 'Gagal', 'Rumah akan segera dikosongkan (pindah rumah)', 'Belum Terpasang', '', 0, 'PK000020', NULL, NULL, NULL, 'PG000036', NULL, NULL, NULL, NULL, NULL, 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2026-09-04 06:21:58', '2026-09-07 01:23:29'),
('G', 0, '0', 'RQ000113', 'PE000110', 'ACTIVE', '0000-00-00', 'Berhasil', '', '', '', 0, 'PK000001', 'PG000005', 'PG000036', 'PG000005', 'PG000005', 'PG000005', '2026-09-10 15:18:06', '2026-09-10 12:13:20', '2026-09-11 22:57:01', '2026-09-11 22:59:30', 'SEWA', 'PG000036', '2026-09-12 13:13:42', 'KABEL', 0, 'IN000106', '2026-09-07 01:09:49', '2026-09-12 06:13:42'),
('G', 0, '0', 'RQ000114', 'PE000111', 'PENGAJUAN', '0000-00-00', '', '', '', '', 0, 'PK000001', NULL, NULL, NULL, 'PG000036', NULL, NULL, NULL, NULL, NULL, 'SEWA', '', '0000-00-00 00:00:00', 'KABEL', 0, '', '2026-10-31 07:17:59', '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Struktur dari tabel `riwayatstatus_penggunabarang`
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
-- Dumping data untuk tabel `riwayatstatus_penggunabarang`
--

INSERT INTO `riwayatstatus_penggunabarang` (`ID`, `KODEBARANG`, `MERKBARANG`, `IDPERMINTAAN`, `IDPENGGUNA`, `KETERANGAN`, `TGLKEMBALI`, `inserted_at`) VALUES
('689c1203ea9', '1', '1', 'RQ000074', 'PE000071', 'DISEWA', '0000-00-00 00:00:00', '2025-08-13 04:18:11'),
('6917fbe7eaf', '1', '1', 'RQ000005', 'PE000005', 'DISEWA', '0000-00-00 00:00:00', '2025-11-15 04:04:55'),
('6993e4737af', '1', '1', 'RQ000040', 'PE000044', 'DISEWA', '0000-00-00 00:00:00', '2026-02-17 03:45:55'),
('6a3f2e140c9', '1', '1', 'RQ000097', 'PE000094', 'DISEWA', '0000-00-00 00:00:00', '2026-06-27 01:57:40'),
('6a801d42772', '1', '1', 'RQ000111', 'PE000108', 'DISEWA', '0000-00-00 00:00:00', '2026-08-15 08:03:14');

-- --------------------------------------------------------

--
-- Struktur dari tabel `riwayat_pelanggan`
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
-- Dumping data untuk tabel `riwayat_pelanggan`
--

INSERT INTO `riwayat_pelanggan` (`ID`, `IDPERMINTAAN`, `CREATEBY`, `STATUSTINDAKAN`, `STATUSLANGGANAN`, `ALASAN`, `TGLTINDAKAN`, `STATUSTINDAKANALAT`) VALUES
('65fced19a13', 'RQ000001', 'PE000001', 'PENGAJUAN', '', '', '2024-03-22 02:29:45', ''),
('65fcee7b25e', 'RQ000002', 'PE000002', 'PENGAJUAN', '', '', '2024-03-22 02:35:39', ''),
('65fcef508a6', 'RQ000003', 'PE000003', 'PENGAJUAN', '', '', '2024-03-22 02:39:12', ''),
('65fcefe47c8', 'RQ000004', 'PE000004', 'PENGAJUAN', '', '', '2024-03-22 02:41:40', ''),
('65fcf32c9a3', 'RQ000005', 'PE000005', 'PENGAJUAN', '', '', '2024-03-22 02:55:40', ''),
('65fcf49a222', 'RQ000006', 'PE000006', 'PENGAJUAN', '', '', '2024-03-22 03:01:46', ''),
('66038471ead', 'RQ000007', 'PE000007', 'PENGAJUAN', '', '', '2024-03-27 02:29:05', ''),
('6603864d682', 'RQ000008', 'PE000008', 'PENGAJUAN', '', '', '2024-03-27 02:37:01', ''),
('660387fe943', 'RQ000009', 'PE000009', 'PENGAJUAN', '', '', '2024-03-27 02:44:14', ''),
('660a12aa2c8', '', 'PG000015', 'Gagal', '', 'data salah', '2024-04-01 01:49:30', 'Belum Terpasang'),
('660a149d051', 'RQ000010', 'PE000010', 'PENGAJUAN', '', '', '2024-04-01 01:57:49', ''),
('660a17d017a', 'RQ000001', 'PG000017', 'Dalam Proses Survei', '', '', '2024-04-01 02:11:28', ''),
('660a17d7269', 'RQ000002', 'PG000017', 'Dalam Proses Survei', '', '', '2024-04-01 02:11:35', ''),
('660a17dd45a', 'RQ000003', 'PG000017', 'Dalam Proses Survei', '', '', '2024-04-01 02:11:41', ''),
('660a17e253b', 'RQ000003', 'PG000017', 'Dalam Proses Survei', '', '', '2024-04-01 02:11:46', ''),
('660a17e8f04', 'RQ000004', 'PG000017', 'Dalam Proses Survei', '', '', '2024-04-01 02:11:52', ''),
('660a17f2426', 'RQ000006', 'PG000017', 'Dalam Proses Survei', '', '', '2024-04-01 02:12:02', ''),
('660a17f8ee3', 'RQ000007', 'PG000017', 'Dalam Proses Survei', '', '', '2024-04-01 02:12:08', ''),
('660a1800013', 'RQ000008', 'PG000017', 'Dalam Proses Survei', '', '', '2024-04-01 02:12:16', ''),
('660a1807f20', 'RQ000009', 'PG000017', 'Dalam Proses Survei', '', '', '2024-04-01 02:12:23', ''),
('660a23944d8', 'RQ000011', 'PE000011', 'PENGAJUAN', '', '', '2024-04-01 03:01:40', ''),
('660a24b49b2', 'RQ000011', 'PG000017', 'Dalam Proses Survei', '', '', '2024-04-01 03:06:28', ''),
('660a25c803d', 'RQ000011', 'PG000017', 'Proses Survei Selesai', '', '', '2024-04-01 03:11:04', ''),
('660a25e629e', 'RQ000011', 'PG000015', 'Verifikasi Proses', '', '', '2024-04-01 03:11:34', ''),
('660a25f1973', 'RQ000011', 'PG000015', 'Dalam Proses Pasang', '', '', '2024-04-01 03:11:45', ''),
('660a2982bd0', 'RQ000012', 'PE000012', 'PENGAJUAN', '', '', '2024-04-01 03:26:58', ''),
('660a299e1b6', 'RQ000012', 'PG000001', 'Dalam Proses Survei', '', '', '2024-04-01 03:27:26', ''),
('660a29b14ed', 'RQ000011', 'PG000015', 'Terpasang', '', '', '2024-04-01 03:27:45', ''),
('660a29b7205', 'RQ000012', 'PG000001', 'Proses Survei Selesai', '', '', '2024-04-01 03:27:51', ''),
('660a29cfe2b', 'RQ000012', 'PG000001', 'Verifikasi Proses', '', '', '2024-04-01 03:28:15', ''),
('660a29dd8e3', 'RQ000012', 'PG000001', 'Dalam Proses Pasang', '', '', '2024-04-01 03:28:29', ''),
('660a2a644f3', 'RQ000012', 'PG000001', 'Terpasang', '', '', '2024-04-01 03:30:44', ''),
('660a2a814b9', 'RQ000012', 'PG000001', 'Berhasil Active', '', '', '2024-04-01 03:31:13', ''),
('660a6c6ad5b', 'RQ000001', 'PG000015', 'Proses Survei Selesai', '', '', '2024-04-01 08:12:26', ''),
('660a6ca1a17', 'RQ000002', 'PG000015', 'Proses Survei Selesai', '', '', '2024-04-01 08:13:21', ''),
('660a6ccb8a5', 'RQ000003', 'PG000015', 'Proses Survei Selesai', '', '', '2024-04-01 08:14:03', ''),
('660a6cfb0f2', 'RQ000004', 'PG000015', 'Proses Survei Selesai', '', '', '2024-04-01 08:14:51', ''),
('660a6d23b70', 'RQ000006', 'PG000015', 'Proses Survei Selesai', '', '', '2024-04-01 08:15:31', ''),
('660a6d7fe5f', 'RQ000007', 'PG000015', 'Proses Survei Selesai', '', '', '2024-04-01 08:17:03', ''),
('660a6e01cbb', 'RQ000008', 'PG000015', 'Proses Survei Selesai', '', '', '2024-04-01 08:19:13', ''),
('660a6e21a59', 'RQ000009', 'PG000015', 'Proses Survei Selesai', '', '', '2024-04-01 08:19:45', ''),
('660a6e2ee21', 'RQ000001', 'PG000015', 'Verifikasi Proses', '', '', '2024-04-01 08:19:58', ''),
('660a6e338dd', 'RQ000002', 'PG000015', 'Verifikasi Proses', '', '', '2024-04-01 08:20:03', ''),
('660a6fa257c', 'RQ000003', 'PG000015', 'Verifikasi Proses', '', '', '2024-04-01 08:26:10', ''),
('660b7f3c54c', 'RQ000004', 'PG000015', 'Verifikasi Proses', '', '', '2024-04-02 03:45:00', ''),
('660b7f41d73', 'RQ000006', 'PG000015', 'Verifikasi Proses', '', '', '2024-04-02 03:45:05', ''),
('660b7f46522', 'RQ000007', 'PG000015', 'Verifikasi Proses', '', '', '2024-04-02 03:45:10', ''),
('660b7f4b259', 'RQ000008', 'PG000015', 'Verifikasi Proses', '', '', '2024-04-02 03:45:15', ''),
('660b7f4fb18', 'RQ000009', 'PG000015', 'Verifikasi Proses', '', '', '2024-04-02 03:45:19', ''),
('660ca113aca', 'RQ000005', 'PG000016', 'Dalam Proses Survei', '', '', '2024-04-03 00:21:39', ''),
('660e119911c', 'RQ000011', 'PG000015', 'Berhasil Active', '', '', '2024-04-04 02:34:01', ''),
('661f31499d3', 'RQ000013', 'PE000013', 'PENGAJUAN', '', '', '2024-04-17 02:17:45', ''),
('66261b12d04', 'RQ000013', 'PG000017', 'Dalam Proses Survei', '', '', '2024-04-22 08:08:50', ''),
('66261b19329', 'RQ000010', 'PG000017', 'Dalam Proses Survei', '', '', '2024-04-22 08:08:57', ''),
('66261b68463', 'RQ000010', 'PG000017', 'Proses Survei Selesai', '', '', '2024-04-22 08:10:16', ''),
('66271b46e0b', 'RQ000013', 'PG000017', 'Proses Survei Selesai', '', '', '2024-04-23 02:21:58', ''),
('6627206b10f', 'RQ000010', 'PG000015', 'Verifikasi Proses', '', '', '2024-04-23 02:43:55', ''),
('66272070c87', 'RQ000013', 'PG000015', 'Verifikasi Proses', '', '', '2024-04-23 02:44:00', ''),
('66275b1e76f', 'RQ000013', 'PG000017', 'Dalam Proses Pasang', '', '', '2024-04-23 06:54:22', ''),
('66275b25d77', 'RQ000010', 'PG000017', 'Dalam Proses Pasang', '', '', '2024-04-23 06:54:29', ''),
('66275c7167b', 'RQ000010', 'PG000017', 'Terpasang', '', '', '2024-04-23 07:00:01', ''),
('66275d6414d', 'RQ000013', 'PG000017', 'Terpasang', '', '', '2024-04-23 07:04:04', ''),
('66275d8ba7f', 'RQ000009', 'PG000017', 'Dalam Proses Pasang', '', '', '2024-04-23 07:04:43', ''),
('66275e3c161', 'RQ000008', 'PG000017', 'Dalam Proses Pasang', '', '', '2024-04-23 07:07:40', ''),
('66275f9f8bb', 'RQ000008', 'PG000017', 'Terpasang', '', '', '2024-04-23 07:13:35', ''),
('6628ae1a01c', 'RQ000014', 'PE000014', 'PENGAJUAN', '', '', '2024-04-24 07:00:42', ''),
('6628ae96e6b', 'RQ000015', 'PE000015', 'PENGAJUAN', '', '', '2024-04-24 07:02:46', ''),
('6628aeb35f5', 'RQ000001', 'PG000015', 'Dalam Proses Pasang', '', '', '2024-04-24 07:03:15', ''),
('6628affa494', 'RQ000013', 'PG000015', 'Berhasil Active', '', '', '2024-04-24 07:08:42', ''),
('6628b03f6ef', 'RQ000008', 'PG000015', 'Berhasil Active', '', '', '2024-04-24 07:09:51', ''),
('6628b09eabe', 'RQ000010', 'PG000015', 'Berhasil Active', '', '', '2024-04-24 07:11:26', ''),
('6628b3cd473', 'RQ000016', 'PE000016', 'PENGAJUAN', '', '', '2024-04-24 07:25:01', ''),
('6631a3619f2', 'RQ000016', 'PG000014', 'Dalam Proses Survei', '', '', '2024-05-01 02:05:21', ''),
('663229e6ea2', 'RQ000016', 'PG000014', 'Proses Survei Selesai', '', '', '2024-05-01 11:39:18', ''),
('6633993cb1c', 'RQ000017', 'PE000017', 'PENGAJUAN', '', '', '2024-05-02 13:46:36', ''),
('66343da64cc', 'RQ000016', 'PG000015', 'Verifikasi Proses', '', '', '2024-05-03 01:28:06', ''),
('663443d415c', 'RQ000001', 'PG000015', 'Terpasang', '', '', '2024-05-03 01:54:28', ''),
('663443dc803', 'RQ000002', 'PG000015', 'Dalam Proses Pasang', '', '', '2024-05-03 01:54:36', ''),
('663444213d5', 'RQ000003', 'PG000015', 'Dalam Proses Pasang', '', '', '2024-05-03 01:55:45', ''),
('663444271ed', 'RQ000004', 'PG000015', 'Dalam Proses Pasang', '', '', '2024-05-03 01:55:51', ''),
('6634442e8a1', 'RQ000006', 'PG000015', 'Dalam Proses Pasang', '', '', '2024-05-03 01:55:58', ''),
('66344434775', 'RQ000007', 'PG000015', 'Dalam Proses Pasang', '', '', '2024-05-03 01:56:04', ''),
('6634443b37e', 'RQ000016', 'PG000015', 'Dalam Proses Pasang', '', '', '2024-05-03 01:56:11', ''),
('66344508038', 'RQ000002', 'PG000015', 'Terpasang', '', '', '2024-05-03 01:59:36', ''),
('663446055a3', 'RQ000003', 'PG000015', 'Terpasang', '', '', '2024-05-03 02:03:49', ''),
('6634485b2f6', 'RQ000004', 'PG000015', 'Terpasang', '', '', '2024-05-03 02:13:47', ''),
('66344988194', 'RQ000006', 'PG000015', 'Terpasang', '', '', '2024-05-03 02:18:48', ''),
('6634522e239', 'RQ000001', 'PG000015', 'Berhasil Active', '', '', '2024-05-03 02:55:42', ''),
('6634523d3dc', 'RQ000002', 'PG000015', 'Berhasil Active', '', '', '2024-05-03 02:55:57', ''),
('6634524d1d2', 'RQ000003', 'PG000015', 'Berhasil Active', '', '', '2024-05-03 02:56:13', ''),
('6634525d5a2', 'RQ000004', 'PG000015', 'Berhasil Active', '', '', '2024-05-03 02:56:29', ''),
('66345271386', 'RQ000006', 'PG000015', 'Berhasil Active', '', '', '2024-05-03 02:56:49', ''),
('6634541f06d', 'RQ000007', 'PG000015', 'Terpasang', '', '', '2024-05-03 03:03:59', ''),
('6634542c710', 'RQ000007', 'PG000015', 'Berhasil Active', '', '', '2024-05-03 03:04:12', ''),
('6634543233c', 'RQ000007', 'PG000015', 'Berhasil Active', '', '', '2024-05-03 03:04:18', ''),
('663454939a9', 'RQ000009', 'PG000015', 'Terpasang', '', '', '2024-05-03 03:05:55', ''),
('663454bd598', 'RQ000009', 'PG000015', 'Berhasil Active', '', '', '2024-05-03 03:06:37', ''),
('663454c48f3', 'RQ000009', 'PG000015', 'Berhasil Active', '', '', '2024-05-03 03:06:44', ''),
('6634559ba24', 'RQ000005', 'PG000015', 'Proses Survei Selesai', '', '', '2024-05-03 03:10:19', ''),
('663455a20ac', 'RQ000014', 'PG000015', 'Dalam Proses Survei', '', '', '2024-05-03 03:10:26', ''),
('663455a8c24', 'RQ000015', 'PG000015', 'Dalam Proses Survei', '', '', '2024-05-03 03:10:32', ''),
('663455e86e2', 'RQ000014', 'PG000015', 'Proses Survei Selesai', '', '', '2024-05-03 03:11:36', ''),
('6634561610b', 'RQ000015', 'PG000015', 'Proses Survei Selesai', '', '', '2024-05-03 03:12:22', ''),
('6634562356a', 'RQ000005', 'PG000015', 'Verifikasi Proses', '', '', '2024-05-03 03:12:35', ''),
('6634562ea6e', 'RQ000014', 'PG000015', 'Verifikasi Proses', '', '', '2024-05-03 03:12:46', ''),
('663456335de', 'RQ000015', 'PG000015', 'Verifikasi Proses', '', '', '2024-05-03 03:12:51', ''),
('66345722b46', 'RQ000005', 'PG000015', 'Dalam Proses Pasang', '', '', '2024-05-03 03:16:50', ''),
('66345728e03', 'RQ000014', 'PG000015', 'Dalam Proses Pasang', '', '', '2024-05-03 03:16:56', ''),
('66345730209', 'RQ000015', 'PG000015', 'Dalam Proses Pasang', '', '', '2024-05-03 03:17:04', ''),
('663457b38ea', 'RQ000005', 'PG000015', 'Terpasang', '', '', '2024-05-03 03:19:15', ''),
('663457e6747', 'RQ000005', 'PG000015', 'Berhasil Active', '', '', '2024-05-03 03:20:06', ''),
('6634589447f', 'RQ000014', 'PG000015', 'Terpasang', '', '', '2024-05-03 03:23:00', ''),
('663458a3c0e', 'RQ000014', 'PG000015', 'Berhasil Active', '', '', '2024-05-03 03:23:15', ''),
('66345956b95', 'RQ000015', 'PG000015', 'Terpasang', '', '', '2024-05-03 03:26:14', ''),
('66345969189', 'RQ000015', 'PG000015', 'Berhasil Active', '', '', '2024-05-03 03:26:33', ''),
('6634c97818e', 'RQ000016', 'PG000014', 'Terpasang', '', '', '2024-05-03 11:24:40', ''),
('66358e154ad', 'RQ000018', 'PE000018', 'PENGAJUAN', '', '', '2024-05-04 01:23:33', ''),
('66358ec9905', 'RQ000018', 'PG000015', 'Dalam Proses Survei', '', '', '2024-05-04 01:26:33', ''),
('66358eff973', 'RQ000018', 'PG000015', 'Proses Survei Selesai', '', '', '2024-05-04 01:27:27', ''),
('66358f09be1', 'RQ000018', 'PG000015', 'Verifikasi Proses', '', '', '2024-05-04 01:27:37', ''),
('66358f20a3e', 'RQ000018', 'PG000015', 'Dalam Proses Pasang', '', '', '2024-05-04 01:28:00', ''),
('66359087cca', 'RQ000018', 'PG000015', 'Terpasang', '', '', '2024-05-04 01:33:59', ''),
('66359206318', 'RQ000019', 'PE000019', 'PENGAJUAN', '', '', '2024-05-04 01:40:22', ''),
('663592115ae', 'RQ000019', 'PG000015', 'Dalam Proses Survei', '', '', '2024-05-04 01:40:33', ''),
('66359283ac1', 'RQ000019', 'PG000015', 'Proses Survei Selesai', '', '', '2024-05-04 01:42:27', ''),
('6635928d566', 'RQ000019', 'PG000015', 'Verifikasi Proses', '', '', '2024-05-04 01:42:37', ''),
('66359293db2', 'RQ000019', 'PG000015', 'Dalam Proses Pasang', '', '', '2024-05-04 01:42:43', ''),
('663593c99bd', 'RQ000019', 'PG000015', 'Terpasang', '', '', '2024-05-04 01:47:53', ''),
('66359b7524c', 'RQ000017', 'PG000017', 'Dalam Proses Survei', '', '', '2024-05-04 02:20:37', ''),
('66359bcc38a', 'RQ000017', 'PG000017', 'Proses Survei Selesai', '', '', '2024-05-04 02:22:04', ''),
('66359c43c5f', 'RQ000017', 'PG000015', 'Verifikasi Proses', '', '', '2024-05-04 02:24:03', ''),
('6635a79a5ed', 'RQ000016', 'PG000015', 'Berhasil Active', '', '', '2024-05-04 03:12:26', ''),
('6635a8f8cf5', 'RQ000018', 'PG000015', 'Berhasil Active', '', '', '2024-05-04 03:18:16', ''),
('6638858c60a', 'RQ000019', 'PG000015', 'Berhasil Active', '', '', '2024-05-06 07:23:56', ''),
('663d7da716a', 'RQ000017', 'PG000023', 'Dalam Proses Pasang', '', '', '2024-05-10 01:51:35', ''),
('663f3da113b', 'RQ000017', 'PG000023', 'Terpasang', '', '', '2024-05-11 09:42:57', ''),
('66415d7b055', 'RQ000017', 'PG000008', 'Berhasil Active', '', '', '2024-05-13 00:23:23', ''),
('6644644df1a', 'RQ000020', 'PE000020', 'PENGAJUAN', '', '', '2024-05-15 07:29:17', ''),
('6654881e682', 'RQ000021', 'PE000021', 'PENGAJUAN', '', '', '2024-05-27 13:18:22', ''),
('665a7d08116', 'RQ000020', 'PG000015', 'Gagal', '', 'tidak jadi karena uang gak ada', '2024-06-01 01:44:40', 'Belum Terpasang'),
('665af4af3a9', 'RQ000021', 'PG000014', 'Dalam Proses Survei', '', '', '2024-06-01 10:15:11', ''),
('66694670f3f', 'RQ000022', 'PE000022', 'PENGAJUAN', '', '', '2024-06-12 06:55:44', ''),
('6674dabd55f', 'RQ000021', 'PG000015', 'Proses Survei Selesai', '', '', '2024-06-21 01:43:25', ''),
('6674dacc561', 'RQ000021', 'PG000015', 'Gagal', '', 'Tidak jadi', '2024-06-21 01:43:40', 'Belum Terpasang'),
('6685a38d770', 'RQ000023', 'PG000008', 'Berhasil Active', '', '', '2024-07-03 19:16:29', ''),
('6685a3f0144', 'RQ000024', 'PG000008', 'Berhasil Active', '', '', '2024-07-03 19:18:08', ''),
('668dfda133d', 'RQ000025', 'PE000025', 'PENGAJUAN', '', '', '2024-07-10 03:18:57', ''),
('668e00032c4', 'RQ000026', 'PE000026', 'PENGAJUAN', '', '', '2024-07-10 03:29:07', ''),
('668e4886847', 'RQ000025', 'PG000017', 'Dalam Proses Survei', '', '', '2024-07-10 08:38:30', ''),
('668f30fbcbe', 'RQ000027', 'PE000031', 'PENGAJUAN', '', '', '2024-07-11 01:10:19', ''),
('668f37101ad', 'RQ000022', 'PG000015', 'Gagal', '', 'tidak jadi', '2024-07-11 01:36:16', 'Belum Terpasang'),
('669c87ddc96', 'RQ000028', 'PE000032', 'PENGAJUAN', '', '', '2024-07-21 04:00:29', ''),
('669c88160b2', 'RQ000027', 'PG000024', 'Gagal', '', 'data ganda', '2024-07-21 04:01:26', 'Belum Terpasang'),
('669c881ba51', 'RQ000028', 'PG000024', 'Dalam Proses Survei', '', '', '2024-07-21 04:01:31', ''),
('669c88db0f7', 'RQ000028', 'PG000024', 'Proses Survei Selesai', '', '', '2024-07-21 04:04:43', ''),
('669c88e9369', 'RQ000028', 'PG000024', 'Verifikasi Proses', '', '', '2024-07-21 04:04:57', ''),
('669c88ef6be', 'RQ000028', 'PG000024', 'Dalam Proses Pasang', '', '', '2024-07-21 04:05:03', ''),
('669c8a66cc6', 'RQ000028', 'PG000024', 'Terpasang', '', '', '2024-07-21 04:11:18', ''),
('669f4dd3106', 'RQ000028', 'PG000015', 'Berhasil Active', '', '', '2024-07-23 06:29:39', ''),
('669f4e53bcf', 'RQ000028', 'PG000015', 'Berhasil Active', '', '', '2024-07-23 06:31:47', ''),
('669f4e5416a', 'RQ000028', 'PG000015', 'Berhasil Active', '', '', '2024-07-23 06:31:48', ''),
('669f4e556fc', 'RQ000028', 'PG000015', 'Berhasil Active', '', '', '2024-07-23 06:31:49', ''),
('669f4e572f1', 'RQ000028', 'PG000015', 'Berhasil Active', '', '', '2024-07-23 06:31:51', ''),
('669f4e57a58', 'RQ000028', 'PG000015', 'Berhasil Active', '', '', '2024-07-23 06:31:51', ''),
('669f4e59036', 'RQ000028', 'PG000015', 'Berhasil Active', '', '', '2024-07-23 06:31:53', ''),
('66a462db5b1', 'RQ000029', 'PE000033', 'PENGAJUAN', '', '', '2024-07-27 03:00:43', ''),
('66c963ef2dc', 'RQ000029', 'PG000016', 'Dalam Proses Survei', '', '', '2024-08-24 04:39:11', ''),
('66c964a7b3c', 'RQ000029', 'PG000016', 'Proses Survei Selesai', '', '', '2024-08-24 04:42:15', ''),
('66cbd01b79e', 'RQ000029', 'PG000015', 'Verifikasi Proses', '', '', '2024-08-26 00:45:15', ''),
('66ce8ca5c9e', 'RQ000029', 'PG000016', 'Dalam Proses Pasang', '', '', '2024-08-28 02:34:13', ''),
('66ce8e5f279', 'RQ000029', 'PG000016', 'Terpasang', '', '', '2024-08-28 02:41:35', ''),
('66ce99c5b9c', 'RQ000029', 'PG000015', 'Berhasil Active', '', '', '2024-08-28 03:30:13', ''),
('66ced68d843', 'RQ000025', 'PG000015', 'Proses Survei Selesai', '', '', '2024-08-28 07:49:33', ''),
('66ced695b74', 'RQ000025', 'PG000015', 'Verifikasi Proses', '', '', '2024-08-28 07:49:41', ''),
('66ced69c517', 'RQ000025', 'PG000015', 'Dalam Proses Pasang', '', '', '2024-08-28 07:49:48', ''),
('66ced7c3467', 'RQ000025', 'PG000015', 'Terpasang', '', '', '2024-08-28 07:54:43', ''),
('66d11e095cb', 'RQ000025', 'PG000015', 'Berhasil Active', '', '', '2024-08-30 01:19:05', ''),
('66f21484e80', 'RQ000030', 'PE000034', 'PENGAJUAN', '', '', '2024-09-24 01:23:16', ''),
('66f25d7ca62', '', 'PG000015', 'Gagal', '', 'tidak jadi', '2024-09-24 06:34:36', 'Belum Terpasang'),
('66f78f855c2', 'RQ000031', 'PE000035', 'PENGAJUAN', '', '', '2024-09-28 05:09:25', ''),
('66fcc3190c4', 'RQ000031', 'PG000015', 'Gagal', '', 'tes data', '2024-10-02 03:50:49', 'Belum Terpasang'),
('66fe549a6bd', 'RQ000032', 'PE000036', 'PENGAJUAN', '', '', '2024-10-03 08:23:54', ''),
('67047e7db70', 'RQ000030', 'PG000025', 'Dalam Proses Survei', '', '', '2024-10-08 00:36:13', ''),
('67047e8443d', 'RQ000032', 'PG000025', 'Dalam Proses Survei', '', '', '2024-10-08 00:36:20', ''),
('67047ebe404', 'RQ000030', 'PG000025', 'Proses Survei Selesai', '', '', '2024-10-08 00:37:18', ''),
('67048001775', 'RQ000032', 'PG000025', 'Proses Survei Selesai', '', '', '2024-10-08 00:42:41', ''),
('6704b0e2821', 'RQ000030', 'PG000015', 'Verifikasi Proses', '', '', '2024-10-08 04:11:14', ''),
('6704b0e70ad', 'RQ000032', 'PG000015', 'Verifikasi Proses', '', '', '2024-10-08 04:11:19', ''),
('670c730c817', 'RQ000033', 'PE000037', 'PENGAJUAN', '', '', '2024-10-14 01:25:32', ''),
('670f2b19cf7', 'RQ000032', 'PG000025', 'Dalam Proses Pasang', '', '', '2024-10-16 02:55:21', ''),
('670f2b24314', 'RQ000030', 'PG000025', 'Dalam Proses Pasang', '', '', '2024-10-16 02:55:32', ''),
('670f2d7b9ea', 'RQ000032', 'PG000025', 'Terpasang', '', '', '2024-10-16 03:05:31', ''),
('6711a70a53c', 'RQ000030', 'PG000025', 'Terpasang', '', '', '2024-10-18 00:08:42', ''),
('6711c01583f', 'RQ000030', 'PG000015', 'Berhasil Active', '', '', '2024-10-18 01:55:33', ''),
('6711c09f23b', 'RQ000032', 'PG000015', 'Berhasil Active', '', '', '2024-10-18 01:57:51', ''),
('6711c0a8134', 'RQ000032', 'PG000015', 'Berhasil Active', '', '', '2024-10-18 01:58:00', ''),
('6718800799d', 'RQ000034', 'PE000038', 'PENGAJUAN', '', '', '2024-10-23 04:48:07', ''),
('67188049458', 'RQ000034', 'PG000001', 'Dalam Proses Survei', '', '', '2024-10-23 04:49:13', ''),
('671880a1da0', 'RQ000034', 'PG000001', 'Proses Survei Selesai', '', '', '2024-10-23 04:50:41', ''),
('6718c182090', 'RQ000034', 'PG000015', 'Gagal', '', 'tes data', '2024-10-23 09:27:30', 'Belum Terpasang'),
('671ef288b34', 'RQ000033', 'PG000017', 'Dalam Proses Survei', '', '', '2024-10-28 02:10:16', ''),
('671ef2d259b', 'RQ000033', 'PG000017', 'Proses Survei Selesai', '', '', '2024-10-28 02:11:30', ''),
('671f1098bde', 'RQ000033', 'PG000015', 'Verifikasi Proses', '', '', '2024-10-28 04:18:32', ''),
('6725852dbdc', 'RQ000035', 'PE000039', 'PENGAJUAN', '', '', '2024-11-02 01:49:33', ''),
('67285cbe35a', 'RQ000033', 'PG000004', 'Dalam Proses Pasang', '', '', '2024-11-04 05:33:50', ''),
('6730c4a495c', 'RQ000035', 'PG000025', 'Dalam Proses Survei', '', '', '2024-11-10 14:35:16', ''),
('6730c8019f9', 'RQ000035', 'PG000025', 'Proses Survei Selesai', '', '', '2024-11-10 14:49:37', ''),
('6731aa067ee', 'RQ000035', 'PG000015', 'Verifikasi Proses', '', '', '2024-11-11 06:53:58', ''),
('673358a0368', 'RQ000033', 'PG000025', 'Terpasang', '', '', '2024-11-12 13:31:12', ''),
('673358cecff', 'RQ000035', 'PG000025', 'Dalam Proses Pasang', '', '', '2024-11-12 13:31:58', ''),
('67335b216f9', 'RQ000035', 'PG000025', 'Terpasang', '', '', '2024-11-12 13:41:53', ''),
('67345ecb123', 'RQ000033', 'PG000015', 'Berhasil Active', '', '', '2024-11-13 08:09:47', ''),
('673460d2aa7', 'RQ000035', 'PG000015', 'Berhasil Active', '', '', '2024-11-13 08:18:26', ''),
('67498918651', 'RQ000036', 'PE000040', 'PENGAJUAN', '', '', '2024-11-29 09:27:52', ''),
('674f07118ba', 'RQ000036', 'PG000014', 'Dalam Proses Survei', '', '', '2024-12-03 13:26:41', ''),
('674f0bda008', 'RQ000036', 'PG000014', 'Proses Survei Selesai', '', '', '2024-12-03 13:47:06', ''),
('674facd2d24', 'RQ000036', 'PG000015', 'Verifikasi Proses', '', '', '2024-12-04 01:13:54', ''),
('675000f0889', 'RQ000037', 'PE000041', 'PENGAJUAN', '', '', '2024-12-04 07:12:48', ''),
('67541348059', 'RQ000038', 'PE000042', 'PENGAJUAN', '', '', '2024-12-07 09:20:08', ''),
('6757c3ee5cb', 'RQ000037', 'PG000027', 'Dalam Proses Survei', '', '', '2024-12-10 04:30:38', ''),
('6757c42a61d', 'RQ000037', 'PG000027', 'Proses Survei Selesai', '', '', '2024-12-10 04:31:38', ''),
('6757dbb2d2a', 'RQ000037', 'PG000015', 'Verifikasi Proses', '', '', '2024-12-10 06:12:02', ''),
('675812d2ec2', 'RQ000037', 'PG000027', 'Dalam Proses Pasang', '', '', '2024-12-10 10:07:14', ''),
('6758135bea3', 'RQ000037', 'PG000027', 'Terpasang', '', '', '2024-12-10 10:09:31', ''),
('675d0d48be2', 'RQ000037', 'PG000015', 'Berhasil Active', '', '', '2024-12-14 04:44:56', ''),
('676baa8dd35', 'RQ000038', 'PG000014', 'Dalam Proses Survei', '', '', '2024-12-25 06:47:41', ''),
('676bb0225e8', 'RQ000038', 'PG000014', 'Proses Survei Selesai', '', '', '2024-12-25 07:11:30', ''),
('676bcf72065', 'RQ000038', 'PG000015', 'Verifikasi Proses', '', '', '2024-12-25 09:25:06', ''),
('676bef82e44', 'RQ000038', 'PG000014', 'Dalam Proses Pasang', '', '', '2024-12-25 11:41:54', ''),
('676bf153643', 'RQ000038', 'PG000014', 'Terpasang', '', '', '2024-12-25 11:49:39', ''),
('676cc815a12', 'RQ000038', 'PG000015', 'Berhasil Active', '', '', '2024-12-26 03:05:57', ''),
('6774b557262', 'RQ000039', 'PG000024', 'Berhasil Active', '', '', '2025-01-01 03:24:07', ''),
('677741d9602', 'RQ000040', 'PG000015', 'Berhasil Active', '', '', '2025-01-03 01:48:09', ''),
('677741d973e', 'RQ000041', 'PG000015', 'Berhasil Active', '', '', '2025-01-03 01:48:09', ''),
('677741d9864', 'RQ000042', 'PG000015', 'Berhasil Active', '', '', '2025-01-03 01:48:09', ''),
('677741d9986', 'RQ000043', 'PG000015', 'Berhasil Active', '', '', '2025-01-03 01:48:09', ''),
('677741d9a3c', 'RQ000044', 'PG000015', 'Berhasil Active', '', '', '2025-01-03 01:48:09', ''),
('677746da42b', 'RQ000045', 'PG000015', 'Berhasil Active', '', '', '2025-01-03 02:09:30', ''),
('6778fc1cdb4', 'RQ000046', 'PE000050', 'PENGAJUAN', '', '', '2025-01-04 09:15:08', ''),
('677c8954309', 'RQ000046', 'PG000014', 'Dalam Proses Survei', '', '', '2025-01-07 01:54:28', ''),
('678b32fc6ed', 'RQ000046', 'PG000014', 'Proses Survei Selesai', '', '', '2025-01-18 04:50:04', ''),
('678b50b5840', 'RQ000046', 'PG000015', 'Verifikasi Proses', '', '', '2025-01-18 06:56:53', ''),
('678b51db7e3', 'RQ000036', 'PG000015', 'Gagal', '', 'tidak jadi', '2025-01-18 07:01:47', 'Belum Terpasang'),
('6791a241d6c', 'RQ000047', 'PE000051', 'PENGAJUAN', '', '', '2025-01-23 01:58:25', ''),
('67935e08152', 'RQ000047', 'PG000006', 'Dalam Proses Survei', '', '', '2025-01-24 09:31:52', ''),
('67935e74cf7', 'RQ000047', 'PG000006', 'Proses Survei Selesai', '', '', '2025-01-24 09:33:40', ''),
('67949bca129', 'RQ000047', 'PG000015', 'Verifikasi Proses', '', '', '2025-01-25 08:07:38', ''),
('6796227c97d', 'RQ000047', 'PG000006', 'Dalam Proses Pasang', '', '', '2025-01-26 11:54:36', ''),
('679623a9798', 'RQ000047', 'PG000006', 'Terpasang', '', '', '2025-01-26 11:59:37', ''),
('67a2dcdb6c2', 'RQ000047', 'PG000015', 'Berhasil Active', '', '', '2025-02-05 03:36:59', ''),
('67a46c818f9', 'RQ000046', 'PG000015', 'Gagal', '', 'tidak jadi pasang', '2025-02-06 08:02:09', 'Belum Terpasang'),
('67ad582bd3e', 'RQ000048', 'PE000052', 'PENGAJUAN', '', '', '2025-02-13 02:25:47', ''),
('67b6b3edd72', 'RQ000048', 'PG000029', 'Dalam Proses Survei', '', '', '2025-02-20 04:47:41', ''),
('67b6b5723a5', 'RQ000048', 'PG000029', 'Proses Survei Selesai', '', '', '2025-02-20 04:54:10', ''),
('67b8716d27f', 'RQ000048', 'PG000015', 'Verifikasi Proses', '', '', '2025-02-21 12:28:29', ''),
('67b8886e2b9', 'RQ000048', 'PG000029', 'Dalam Proses Pasang', '', '', '2025-02-21 14:06:38', ''),
('67b8893fcc1', 'RQ000048', 'PG000029', 'Terpasang', '', '', '2025-02-21 14:10:07', ''),
('67bc5b53d81', 'RQ000049', 'PE000053', 'PENGAJUAN', '', '', '2025-02-24 11:43:15', ''),
('67bd7940539', 'RQ000048', 'PG000015', 'Berhasil Active', '', '', '2025-02-25 08:03:12', ''),
('67c578a327d', 'RQ000049', 'PG000006', 'Dalam Proses Survei', '', '', '2025-03-03 09:38:43', ''),
('67c592e78a9', 'RQ000049', 'PG000014', 'Proses Survei Selesai', '', '', '2025-03-03 11:30:47', ''),
('67c6991b515', 'RQ000049', 'PG000015', 'Verifikasi Proses', '', '', '2025-03-04 06:09:31', ''),
('67c6a064b57', 'RQ000049', 'PG000014', 'Dalam Proses Pasang', '', '', '2025-03-04 06:40:36', ''),
('67c6efba468', 'RQ000049', 'PG000014', 'Terpasang', '', '', '2025-03-04 12:19:06', ''),
('67c7a934c87', 'RQ000049', 'PG000015', 'Berhasil Active', '', '', '2025-03-05 01:30:28', ''),
('67dbc7252dd', 'RQ000050', 'PE000054', 'PENGAJUAN', '', '', '2025-03-20 07:43:33', ''),
('67dcca14769', 'RQ000050', 'PG000017', 'Dalam Proses Survei', '', '', '2025-03-21 02:08:20', ''),
('67de05492d1', 'RQ000050', 'PG000017', 'Proses Survei Selesai', '', '', '2025-03-22 00:33:13', ''),
('67de0d49b10', 'RQ000050', 'PG000015', 'Verifikasi Proses', '', '', '2025-03-22 01:07:21', ''),
('67e194333ea', 'RQ000050', 'PG000017', 'Dalam Proses Pasang', '', '', '2025-03-24 17:19:47', ''),
('67e195678a3', 'RQ000050', 'PG000017', 'Terpasang', '', '', '2025-03-24 17:24:55', ''),
('67e21a4052b', 'RQ000050', 'PG000015', 'Berhasil Active', '', '', '2025-03-25 02:51:44', ''),
('67e810ac062', 'RQ000012', 'PG000002', 'Putus Langganan', '', 'Testing', '2025-03-29 15:24:28', ''),
('67ecec46490', 'RQ000051', 'PE000055', 'PENGAJUAN', '', '', '2025-04-02 07:50:30', ''),
('67f2260819b', 'RQ000052', 'PE000056', 'PENGAJUAN', '', '', '2025-04-06 06:58:16', ''),
('67f4943384d', 'RQ000051', 'PG000017', 'Dalam Proses Survei', '', '', '2025-04-08 03:12:51', ''),
('67f494988ed', 'RQ000051', 'PG000017', 'Proses Survei Selesai', '', '', '2025-04-08 03:14:32', ''),
('67f4ca9a0bf', 'RQ000051', 'PG000015', 'Verifikasi Proses', '', '', '2025-04-08 07:04:58', ''),
('67f71aba4df', 'RQ000051', 'PG000017', 'Dalam Proses Pasang', '', '', '2025-04-10 01:11:22', ''),
('67f7259c6ba', 'RQ000051', 'PG000017', 'Terpasang', '', '', '2025-04-10 01:57:48', ''),
('67f9d31b916', 'RQ000051', 'PG000015', 'Berhasil Active', '', '', '2025-04-12 02:42:35', ''),
('67fdd5603bf', 'RQ000052', 'PG000029', 'Dalam Proses Survei', '', '', '2025-04-15 03:41:20', ''),
('67fdd5a0bf5', 'RQ000052', 'PG000029', 'Proses Survei Selesai', '', '', '2025-04-15 03:42:24', ''),
('67ff3990108', 'RQ000052', 'PG000032', 'Verifikasi Proses', '', '', '2025-04-16 05:01:04', ''),
('68077feeb32', 'RQ000052', 'PG000026', 'Dalam Proses Pasang', '', '', '2025-04-22 11:39:26', ''),
('680781b54b1', 'RQ000052', 'PG000026', 'Terpasang', '', '', '2025-04-22 11:47:01', ''),
('6808b08f6b0', 'RQ000052', 'PG000032', 'Berhasil Active', '', '', '2025-04-23 09:19:11', ''),
('6845518d242', 'RQ000053', 'PE000057', 'PENGAJUAN', '', '', '2025-06-08 09:02:05', ''),
('68468c9f9ef', 'RQ000053', 'PG000034', 'Dalam Proses Survei', '', '', '2025-06-09 07:26:23', ''),
('68468e53902', 'RQ000053', 'PG000034', 'Proses Survei Selesai', '', '', '2025-06-09 07:33:39', ''),
('68468edd868', 'RQ000053', 'PG000034', 'Verifikasi Proses', '', '', '2025-06-09 07:35:57', ''),
('68468fba87e', 'RQ000053', 'PG000034', 'Dalam Proses Pasang', '', '', '2025-06-09 07:39:38', ''),
('68469176bd2', 'RQ000053', 'PG000034', 'Terpasang', '', '', '2025-06-09 07:47:02', ''),
('6846b8144c3', 'RQ000053', 'PG000034', 'Berhasil Active', '', '', '2025-06-09 10:31:48', ''),
('6847f10e4da', 'RQ000054', 'PE000058', 'PENGAJUAN', '', '', '2025-06-10 08:47:10', ''),
('6847f1e7156', 'RQ000054', 'PG000034', 'Dalam Proses Survei', '', '', '2025-06-10 08:50:47', ''),
('6847f26f04a', 'RQ000054', 'PG000034', 'Proses Survei Selesai', '', '', '2025-06-10 08:53:03', ''),
('6847f2a5816', 'RQ000054', 'PG000034', 'Verifikasi Proses', '', '', '2025-06-10 08:53:57', ''),
('6847f31e947', 'RQ000054', 'PG000034', 'Dalam Proses Pasang', '', '', '2025-06-10 08:55:58', ''),
('6847f3a12d6', 'RQ000054', 'PG000034', 'Terpasang', '', '', '2025-06-10 08:58:09', ''),
('6847f4283af', 'RQ000054', 'PG000034', 'Berhasil Active', '', '', '2025-06-10 09:00:24', ''),
('6847f7d5618', 'RQ000055', 'PE000059', 'PENGAJUAN', '', '', '2025-06-10 09:16:05', ''),
('6847f85c312', 'RQ000055', 'PG000034', 'Dalam Proses Survei', '', '', '2025-06-10 09:18:20', ''),
('6847f88d2a2', 'RQ000055', 'PG000034', 'Proses Survei Selesai', '', '', '2025-06-10 09:19:09', ''),
('6847f8b8f35', 'RQ000055', 'PG000034', 'Verifikasi Proses', '', '', '2025-06-10 09:19:52', ''),
('6847f92949e', 'RQ000055', 'PG000034', 'Dalam Proses Pasang', '', '', '2025-06-10 09:21:45', ''),
('6847f9e0c7d', 'RQ000055', 'PG000034', 'Terpasang', '', '', '2025-06-10 09:24:48', ''),
('6847fa0dbde', 'RQ000055', 'PG000034', 'Berhasil Active', '', '', '2025-06-10 09:25:33', ''),
('6847fa0ec8d', '', 'PG000034', 'Berhasil Active', '', '', '2025-06-10 09:25:34', ''),
('684a52d039e', 'RQ000056', 'US000001', 'PENGAJUAN', '', '', '2025-06-12 04:08:48', ''),
('684a5349497', 'RQ000056', 'PG000034', 'Dalam Proses Survei', '', '', '2025-06-12 04:10:49', ''),
('684a537680b', 'RQ000057', 'PE000060', 'PENGAJUAN', '', '', '2025-06-12 04:11:34', ''),
('684ad4f616d', 'RQ000056', 'PG000034', 'Proses Survei Selesai', '', '', '2025-06-12 13:24:06', ''),
('684ad68ab65', 'RQ000056', 'PG000034', 'Verifikasi Proses', '', '', '2025-06-12 13:30:50', ''),
('684ad699b08', 'RQ000056', 'PG000034', 'Dalam Proses Pasang', '', '', '2025-06-12 13:31:05', ''),
('684ad6cd472', 'RQ000056', 'PG000034', 'Terpasang', '', '', '2025-06-12 13:31:57', ''),
('684ad6dea8f', 'RQ000056', 'PG000034', 'Berhasil Active', '', '', '2025-06-12 13:32:14', ''),
('684ad8a3be2', 'RQ000057', 'PG000034', 'Dalam Proses Survei', '', '', '2025-06-12 13:39:47', ''),
('684adb93e14', 'RQ000057', 'PG000034', 'Proses Survei Selesai', '', '', '2025-06-12 13:52:19', ''),
('684adba6315', 'RQ000057', 'PG000034', 'Verifikasi Proses', '', '', '2025-06-12 13:52:38', ''),
('684adbadcad', 'RQ000057', 'PG000034', 'Dalam Proses Pasang', '', '', '2025-06-12 13:52:45', ''),
('684ae0474a5', 'RQ000057', 'PG000034', 'Terpasang', '', '', '2025-06-12 14:12:23', ''),
('684afa27d46', 'RQ000057', 'PG000034', 'Berhasil Active', '', '', '2025-06-12 16:02:47', ''),
('684afa8b990', 'RQ000058', 'US000002', 'PENGAJUAN', '', '', '2025-06-12 16:04:27', ''),
('684afae813e', 'RQ000058', 'PG000034', 'Dalam Proses Survei', '', '', '2025-06-12 16:06:00', ''),
('684b14cf11d', 'RQ000058', 'PG000034', 'Proses Survei Selesai', '', '', '2025-06-12 17:56:31', ''),
('684b15ba770', 'RQ000058', 'PG000034', 'Verifikasi Proses', '', '', '2025-06-12 18:00:26', ''),
('684b16e6e16', 'RQ000059', 'PE000061', 'PENGAJUAN', '', '', '2025-06-12 18:05:26', ''),
('684b16ef8bb', 'RQ000059', 'PG000034', 'Dalam Proses Survei', '', '', '2025-06-12 18:05:35', ''),
('684b1703d04', 'RQ000059', 'PG000034', 'Proses Survei Selesai', '', '', '2025-06-12 18:05:55', ''),
('684b17f6022', 'RQ000058', 'PG000034', 'Dalam Proses Pasang', '', '', '2025-06-12 18:09:58', ''),
('684b18eb529', 'RQ000060', 'PE000062', 'PENGAJUAN', '', '', '2025-06-12 18:14:03', ''),
('684b190da5b', 'RQ000060', 'PG000034', 'Dalam Proses Survei', '', '', '2025-06-12 18:14:37', ''),
('684b19346fa', 'RQ000060', 'PG000034', 'Proses Survei Selesai', '', '', '2025-06-12 18:15:16', ''),
('684b19481e8', 'RQ000060', 'PG000034', 'Verifikasi Proses', '', '', '2025-06-12 18:15:36', ''),
('684b1954f04', 'RQ000060', 'PG000034', 'Dalam Proses Pasang', '', '', '2025-06-12 18:15:48', ''),
('684b1c8fb29', 'RQ000060', 'PG000034', 'Terpasang', '', '', '2025-06-12 18:29:35', ''),
('684b2470265', 'RQ000061', 'PE000063', 'PENGAJUAN', '', '', '2025-06-12 19:03:12', ''),
('684b248d706', 'RQ000061', 'PG000034', 'Dalam Proses Survei', '', '', '2025-06-12 19:03:41', ''),
('684b24ad182', 'RQ000061', 'PG000034', 'Proses Survei Selesai', '', '', '2025-06-12 19:04:13', ''),
('684b24c0189', 'RQ000061', 'PG000034', 'Verifikasi Proses', '', '', '2025-06-12 19:04:32', ''),
('684b24ceb51', 'RQ000061', 'PG000034', 'Dalam Proses Pasang', '', '', '2025-06-12 19:04:46', ''),
('684b2500e95', 'RQ000061', 'PG000034', 'Terpasang', '', '', '2025-06-12 19:05:36', ''),
('684b26e51b7', 'RQ000061', 'PG000034', 'Berhasil Active', '', '', '2025-06-12 19:13:41', ''),
('684b26facb8', 'RQ000060', 'PG000034', 'Berhasil Active', '', '', '2025-06-12 19:14:02', ''),
('684b2dce061', 'RQ000056', 'PG000034', 'Putus Langganan', '', 'tes aja', '2025-06-12 19:43:10', ''),
('684b2f31067', 'RQ000057', 'PG000034', 'Putus Langganan', '', '', '2025-06-12 19:49:05', 'Hak milik'),
('684ba30932c', 'RQ000059', 'PG000034', 'Verifikasi Proses', '', '', '2025-06-13 04:03:21', ''),
('684ba30f3f8', 'RQ000059', 'PG000034', 'Dalam Proses Pasang', '', '', '2025-06-13 04:03:27', ''),
('684bacde511', 'RQ000062', 'PE000064', 'PENGAJUAN', '', '', '2025-06-13 04:45:18', ''),
('684bace629e', 'RQ000062', 'PG000034', 'Dalam Proses Survei', '', '', '2025-06-13 04:45:26', ''),
('684bacfbac7', 'RQ000062', 'PG000034', 'Proses Survei Selesai', '', '', '2025-06-13 04:45:47', ''),
('684bad9540c', 'RQ000059', 'PG000034', 'Terpasang', '', '', '2025-06-13 04:48:21', ''),
('684baef1be4', 'RQ000063', 'US000003', 'PENGAJUAN', '', '', '2025-06-13 04:54:09', ''),
('684bd357275', 'RQ000056', 'PG000034', '', 'Kembali Proses', '', '2025-06-13 07:29:27', ''),
('684bd36ca09', 'RQ000012', 'PG000034', '', 'Kembali Proses', '', '2025-06-13 07:29:48', ''),
('684bd4c563a', 'RQ000060', 'PG000034', 'Putus Langganan', '', 'Nihil quibusdam sit ', '2025-06-13 07:35:33', 'Hak milik'),
('684bd4e0c7e', 'RQ000061', 'PG000034', 'Putus Langganan', '', 'Id est anim consect', '2025-06-13 07:36:00', 'Hak milik'),
('684ccdb52df', 'RQ000012', 'PG000032', 'Berhasil Active', '', '', '2025-06-14 01:17:41', ''),
('684ccdcfade', 'RQ000012', 'PG000032', 'Putus Langganan', '', 'Tes data', '2025-06-14 01:18:07', ''),
('684f8a1e483', 'RQ000062', 'PG000032', 'Gagal', '', 'Test data', '2025-06-16 03:06:06', 'Belum Terpasang'),
('684f8a2a03f', 'RQ000059', 'PG000032', 'Berhasil Active', '', '', '2025-06-16 03:06:18', ''),
('684f8a2b0e9', '', 'PG000032', 'Berhasil Active', '', '', '2025-06-16 03:06:19', ''),
('684f8a33db3', 'RQ000058', 'PG000032', 'Gagal', '', 'Test data', '2025-06-16 03:06:27', 'Belum Terpasang'),
('684f8a3eb9c', 'RQ000056', 'PG000032', 'Berhasil Active', '', '', '2025-06-16 03:06:38', ''),
('684f8a3f813', '', 'PG000032', 'Berhasil Active', '', '', '2025-06-16 03:06:39', ''),
('684ffc06aaa', 'RQ000063', 'PG000034', 'Dalam Proses Survei', '', '', '2025-06-16 11:12:06', ''),
('6852bb1cd90', 'RQ000064', 'US000004', 'PENGAJUAN', '', '', '2025-06-18 13:11:56', ''),
('68583ffac5a', 'RQ000065', 'US000005', 'PENGAJUAN', '', '', '2025-06-22 17:40:10', ''),
('685840275c3', 'RQ000063', 'PG000034', 'Proses Survei Selesai', '', '', '2025-06-22 17:40:55', ''),
('6858403cb2b', 'RQ000065', 'PG000034', 'Dalam Proses Survei', '', '', '2025-06-22 17:41:16', ''),
('6858404fdd9', 'RQ000065', 'PG000034', 'Proses Survei Selesai', '', '', '2025-06-22 17:41:35', ''),
('6858406a4b6', 'RQ000065', 'PG000034', 'Verifikasi Proses', '', '', '2025-06-22 17:42:02', ''),
('685840702c0', 'RQ000065', 'PG000034', 'Dalam Proses Pasang', '', '', '2025-06-22 17:42:08', ''),
('685840c4d7f', 'RQ000065', 'PG000034', 'Terpasang', '', '', '2025-06-22 17:43:32', ''),
('685840daec8', 'RQ000065', 'PG000034', 'Berhasil Active', '', '', '2025-06-22 17:43:54', ''),
('685bfd44878', 'RQ000066', 'US000006', 'PENGAJUAN', '', '', '2025-06-25 13:44:36', ''),
('685c12ab968', 'RQ000064', 'PG000034', 'Dalam Proses Survei', '', '', '2025-06-25 15:15:55', ''),
('685c12c4201', 'RQ000064', 'PG000034', 'Proses Survei Selesai', '', '', '2025-06-25 15:16:20', ''),
('685c12cc5a7', 'RQ000063', 'PG000034', 'Verifikasi Proses', '', '', '2025-06-25 15:16:28', ''),
('685c12d1312', 'RQ000063', 'PG000034', 'Dalam Proses Pasang', '', '', '2025-06-25 15:16:33', ''),
('685c17156e4', 'RQ000063', 'PG000034', 'Terpasang', '', '', '2025-06-25 15:34:45', ''),
('685c281c3cf', 'RQ000067', 'PE000065', 'PENGAJUAN', '', '', '2025-06-25 16:47:24', ''),
('685c2831708', 'RQ000067', 'PG000034', 'Dalam Proses Survei', '', '', '2025-06-25 16:47:45', ''),
('685c3f793b8', 'RQ000068', 'US000007', 'PENGAJUAN', '', '', '2025-06-25 18:27:05', ''),
('685c49bfaa6', 'RQ000069', 'PE000066', 'PENGAJUAN', '', '', '2025-06-25 19:10:55', ''),
('685c4d7fd3b', 'RQ000064', 'PG000034', 'Verifikasi Proses', '', '', '2025-06-25 19:26:55', ''),
('685c4da0d11', 'RQ000068', 'PG000034', 'Dalam Proses Survei', '', '', '2025-06-25 19:27:28', ''),
('685c4ed65aa', 'RQ000067', 'PG000034', 'Proses Survei Selesai', '', '', '2025-06-25 19:32:38', ''),
('685c4f24971', 'RQ000067', 'PG000034', 'Verifikasi Proses', '', '', '2025-06-25 19:33:56', ''),
('685c4f59094', 'RQ000067', 'PG000034', 'Dalam Proses Pasang', '', '', '2025-06-25 19:34:49', ''),
('685c4fafa19', 'RQ000067', 'PG000034', 'Terpasang', '', '', '2025-06-25 19:36:15', ''),
('685c4fde5cc', 'RQ000067', 'PG000034', 'Berhasil Active', '', '', '2025-06-25 19:37:02', ''),
('685e144fa97', 'RQ000070', 'PE000067', 'PENGAJUAN', '', '', '2025-06-27 03:47:27', ''),
('685f9cb5b63', 'RQ000071', 'PE000068', 'PENGAJUAN', '', '', '2025-06-28 07:41:41', ''),
('685f9cbde62', 'RQ000071', 'PG000034', 'Dalam Proses Survei', '', '', '2025-06-28 07:41:49', ''),
('685f9cd55f8', 'RQ000071', 'PG000034', 'Proses Survei Selesai', '', '', '2025-06-28 07:42:13', ''),
('685f9ce11f0', 'RQ000071', 'PG000034', 'Verifikasi Proses', '', '', '2025-06-28 07:42:25', ''),
('685f9ce6454', 'RQ000071', 'PG000034', 'Dalam Proses Pasang', '', '', '2025-06-28 07:42:30', ''),
('685f9db4a41', 'RQ000072', 'PE000069', 'PENGAJUAN', '', '', '2025-06-28 07:45:56', ''),
('685f9dc45b6', 'RQ000072', 'PG000034', 'Dalam Proses Survei', '', '', '2025-06-28 07:46:12', ''),
('685f9de522c', 'RQ000072', 'PG000034', 'Proses Survei Selesai', '', '', '2025-06-28 07:46:45', ''),
('685f9ded83f', 'RQ000072', 'PG000034', 'Verifikasi Proses', '', '', '2025-06-28 07:46:53', ''),
('685f9df330b', 'RQ000072', 'PG000034', 'Dalam Proses Pasang', '', '', '2025-06-28 07:46:59', ''),
('68611a8256c', '', 'PG000034', 'Alat telah kembali', '', '', '2025-06-29 10:50:42', 'Sudah diambil'),
('68615e3cd17', 'RQ000072', 'PG000034', 'Terpasang', '', '', '2025-06-29 15:39:40', ''),
('68615e46149', 'RQ000072', 'PG000034', 'Berhasil Active', '', '', '2025-06-29 15:39:50', ''),
('68615e787b2', 'RQ000072', 'PG000034', 'Putus Langganan', '', 'Maiores recusandae ', '2025-06-29 15:40:40', ''),
('68649d7ac51', 'RQ000070', 'PG000006', 'Dalam Proses Survei', '', '', '2025-07-02 02:46:18', ''),
('68649eb9e2f', 'RQ000070', 'PG000006', 'Proses Survei Selesai', '', '', '2025-07-02 02:51:37', ''),
('6864a13127d', 'RQ000070', 'PG000032', 'Verifikasi Proses', '', '', '2025-07-02 03:02:09', ''),
('6864a53c13c', 'RQ000070', 'PG000006', 'Dalam Proses Pasang', '', '', '2025-07-02 03:19:24', ''),
('6865ca423f3', 'RQ000070', 'PG000014', 'Terpasang', '', '', '2025-07-03 00:09:38', ''),
('68663276ca9', 'RQ000070', 'PG000032', 'Berhasil Active', '', '', '2025-07-03 07:34:14', ''),
('68663277abf', '', 'PG000032', 'Berhasil Active', '', '', '2025-07-03 07:34:15', ''),
('68677b560cf', 'RQ000073', 'PE000070', 'PENGAJUAN', '', '', '2025-07-04 06:57:26', ''),
('686b8ec5845', 'RQ000073', 'PG000006', 'Dalam Proses Survei', '', '', '2025-07-07 09:09:25', ''),
('686b8f5eef4', 'RQ000073', 'PG000006', 'Proses Survei Selesai', '', '', '2025-07-07 09:11:58', ''),
('686b918c26a', 'RQ000073', 'PG000032', 'Verifikasi Proses', '', '', '2025-07-07 09:21:16', ''),
('686b94d0898', 'RQ000073', 'PG000006', 'Dalam Proses Pasang', '', '', '2025-07-07 09:35:12', ''),
('686bb2ca485', 'RQ000073', 'PG000014', 'Terpasang', '', '', '2025-07-07 11:43:06', ''),
('686c6cfd044', 'RQ000063', 'PG000032', 'Berhasil Active', '', '', '2025-07-08 00:57:33', ''),
('686c6d0b8aa', 'RQ000071', 'PG000032', 'Gagal', '', 'tes data', '2025-07-08 00:57:47', 'Belum Terpasang'),
('686c6d1794f', 'RQ000064', 'PG000032', 'Gagal', '', 'tes data', '2025-07-08 00:57:59', 'Belum Terpasang'),
('686cc309c6b', 'RQ000073', 'PG000032', 'Berhasil Active', '', '', '2025-07-08 07:04:41', ''),
('686cc30a79c', '', 'PG000032', 'Berhasil Active', '', '', '2025-07-08 07:04:42', ''),
('6879bb3d3f1', 'RQ000074', 'PE000071', 'PENGAJUAN', '', '', '2025-07-18 03:10:53', ''),
('687f2a643a7', 'RQ000074', 'PG000004', 'Dalam Proses Survei', '', '', '2025-07-22 06:06:28', ''),
('68802b5413f', 'RQ000074', 'PG000004', 'Proses Survei Selesai', '', '', '2025-07-23 00:22:44', ''),
('688040b9119', 'RQ000074', 'PG000032', 'Verifikasi Proses', '', '', '2025-07-23 01:54:01', ''),
('6881dd749b8', 'RQ000075', 'PE000072', 'PENGAJUAN', '', '', '2025-07-24 07:15:00', ''),
('6884db719e5', 'RQ000075', 'PG000032', 'Gagal', '', 'pindah ke Whusnet', '2025-07-26 13:43:13', 'Belum Terpasang'),
('6886e075c1f', 'RQ000076', 'PE000073', 'PENGAJUAN', '', '', '2025-07-28 02:29:09', ''),
('6888983f2de', 'RQ000076', 'PG000006', 'Dalam Proses Survei', '', '', '2025-07-29 09:45:35', ''),
('68889920cd4', 'RQ000076', 'PG000006', 'Proses Survei Selesai', '', '', '2025-07-29 09:49:20', ''),
('6889761f902', 'RQ000076', 'PG000032', 'Verifikasi Proses', '', '', '2025-07-30 01:32:15', ''),
('688af6c6ee6', 'RQ000076', 'PG000006', 'Dalam Proses Pasang', '', '', '2025-07-31 04:53:26', ''),
('688b0931c1e', 'RQ000076', 'PG000006', 'Terpasang', '', '', '2025-07-31 06:12:01', ''),
('688b1cb70bb', 'RQ000076', 'PG000032', 'Berhasil Active', '', '', '2025-07-31 07:35:19', ''),
('688b1cb7cda', '', 'PG000032', 'Berhasil Active', '', '', '2025-07-31 07:35:19', ''),
('688c0a785c9', 'RQ000074', 'PG000004', 'Dalam Proses Pasang', '', '', '2025-08-01 00:29:44', ''),
('688f93890c4', 'RQ000066', 'PG000005', 'Gagal', '', 'tes', '2025-08-03 16:51:21', 'Belum Terpasang'),
('688f9395a9c', 'RQ000069', 'PG000005', 'Gagal', '', 'tes', '2025-08-03 16:51:33', 'Belum Terpasang'),
('688f93adb23', 'RQ000068', 'PG000005', 'Proses Survei Selesai', '', '', '2025-08-03 16:51:57', ''),
('688f93c3893', 'RQ000068', 'PG000005', 'Gagal', '', 'tes', '2025-08-03 16:52:19', 'Belum Terpasang'),
('68944bf81b2', 'RQ000077', 'PE000074', 'PENGAJUAN', '', '', '2025-08-07 06:47:20', ''),
('68993421013', 'RQ000074', 'PG000004', 'Terpasang', '', '', '2025-08-11 00:06:57', ''),
('6899a1035ae', 'RQ000074', 'PG000032', 'Berhasil Active', '', '', '2025-08-11 07:51:31', ''),
('6899a10457f', '', 'PG000032', 'Berhasil Active', '', '', '2025-08-11 07:51:32', ''),
('6899ca3016c', 'RQ000077', 'PG000004', 'Dalam Proses Survei', '', '', '2025-08-11 10:47:12', ''),
('6899ca62b98', 'RQ000077', 'PG000004', 'Proses Survei Selesai', '', '', '2025-08-11 10:48:02', ''),
('6899cab25f4', 'RQ000077', 'PG000005', 'Verifikasi Proses', '', '', '2025-08-11 10:49:22', ''),
('6899cc20c79', 'RQ000077', 'PG000004', 'Dalam Proses Pasang', '', '', '2025-08-11 10:55:28', ''),
('6899cda9063', 'RQ000077', 'PG000004', 'Terpasang', '', '', '2025-08-11 11:02:01', ''),
('689aa835a7b', 'RQ000077', 'PG000032', 'Berhasil Active', '', '', '2025-08-12 02:34:29', ''),
('68ccc198415', '', 'PG000024', 'OFF', '', '', '2025-09-19 02:36:08', ''),
('68ccc19dc9c', '', 'PG000024', 'OFF', '', '', '2025-09-19 02:36:13', ''),
('68e31904662', 'RQ000078', 'PE000075', 'PENGAJUAN', '', '', '2025-10-06 01:19:00', ''),
('68e47ac8179', 'RQ000079', 'PE000076', 'PENGAJUAN', '', '', '2025-10-07 02:28:24', ''),
('68e4d0f0799', 'RQ000080', 'PE000077', 'PENGAJUAN', '', '', '2025-10-07 08:36:00', ''),
('68e6065fb6b', 'RQ000079', 'PG000013', 'Dalam Proses Survei', '', '', '2025-10-08 06:36:15', ''),
('68e6068b747', 'RQ000079', 'PG000013', 'Proses Survei Selesai', '', '', '2025-10-08 06:36:59', ''),
('68e606be607', 'RQ000078', 'PG000013', 'Dalam Proses Survei', '', '', '2025-10-08 06:37:50', ''),
('68e606d83e4', 'RQ000078', 'PG000013', 'Proses Survei Selesai', '', '', '2025-10-08 06:38:16', ''),
('68e60776382', 'RQ000079', 'PG000032', 'Verifikasi Proses', '', '', '2025-10-08 06:40:54', ''),
('68e6078396e', 'RQ000078', 'PG000032', 'Verifikasi Proses', '', '', '2025-10-08 06:41:07', ''),
('68e71a44a10', 'RQ000079', 'PG000032', 'Gagal', '', 'Data di OLT Jetis', '2025-10-09 02:13:24', 'Belum Terpasang'),
('68e71d72d1a', 'RQ000078', 'PG000013', 'Dalam Proses Pasang', '', '', '2025-10-09 02:26:58', ''),
('68e71e152b1', 'RQ000078', 'PG000013', 'Terpasang', '', '', '2025-10-09 02:29:41', ''),
('68e76f5d6a1', 'RQ000078', 'PG000032', 'Berhasil Active', '', '', '2025-10-09 08:16:29', ''),
('68e76f5e41c', '', 'PG000032', 'Berhasil Active', '', '', '2025-10-09 08:16:30', ''),
('68f1935a7bb', 'RQ000080', 'PG000032', 'Gagal', '', 'HTB kabel jauh', '2025-10-17 00:52:42', 'Belum Terpasang'),
('68f5afc12ea', 'RQ000081', 'PE000078', 'PENGAJUAN', '', '', '2025-10-20 03:42:57', ''),
('68fc2af2018', 'RQ000081', 'PG000014', 'Dalam Proses Survei', '', '', '2025-10-25 01:42:10', ''),
('68fe1e439ba', 'RQ000081', 'PG000014', 'Proses Survei Selesai', '', '', '2025-10-26 13:12:35', ''),
('68fe4ede9a2', 'RQ000081', 'PG000005', 'Verifikasi Proses', '', '', '2025-10-26 16:39:58', ''),
('68febf46944', 'RQ000081', 'PG000014', 'Dalam Proses Pasang', '', '', '2025-10-27 00:39:34', ''),
('68ff401d71a', 'RQ000081', 'PG000014', 'Terpasang', '', '', '2025-10-27 09:49:17', ''),
('690066978de', 'RQ000081', 'PG000032', 'Berhasil Active', '', '', '2025-10-28 06:45:43', ''),
('690066987e0', '', 'PG000032', 'Berhasil Active', '', '', '2025-10-28 06:45:44', ''),
('690456a55e3', 'RQ000082', 'PE000079', 'PENGAJUAN', '', '', '2025-10-31 06:26:45', ''),
('6909720107f', 'RQ000083', 'PE000080', 'PENGAJUAN', '', '', '2025-11-04 03:24:49', ''),
('690c4caf483', 'RQ000082', 'PG000014', 'Dalam Proses Survei', '', '', '2025-11-06 07:22:23', ''),
('690c5dec5e1', 'RQ000082', 'PG000014', 'Proses Survei Selesai', '', '', '2025-11-06 08:35:56', ''),
('690d4062858', 'RQ000082', 'PG000032', 'Verifikasi Proses', '', '', '2025-11-07 00:42:10', ''),
('690eaa269c4', 'RQ000083', 'PG000032', 'Gagal', '', 'Belum ada jalur', '2025-11-08 02:25:42', 'Belum Terpasang'),
('690ef274263', 'RQ000082', 'PG000014', 'Dalam Proses Pasang', '', '', '2025-11-08 07:34:12', ''),
('690ef74d35b', 'RQ000082', 'PG000014', 'Terpasang', '', '', '2025-11-08 07:54:53', ''),
('6911b0fabbc', 'RQ000082', 'PG000032', 'Berhasil Active', '', '', '2025-11-10 09:31:38', ''),
('693d0c6ddbf', 'RQ000084', 'PE000081', 'PENGAJUAN', '', '', '2025-12-13 06:49:17', ''),
('6944ad5dc06', 'RQ000085', 'PE000082', 'PENGAJUAN', '', '', '2025-12-19 01:41:49', ''),
('6944c526a6c', 'RQ000084', 'PG000036', 'Gagal', '', 'Pindah ke Aplikasi Jetis', '2025-12-19 03:23:18', 'Belum Terpasang'),
('6944c57c44b', 'RQ000085', 'PG000036', 'Gagal', '', 'Pindah ke Aplikasi Jetis', '2025-12-19 03:24:44', 'Belum Terpasang'),
('6948f0d3c73', 'RQ000086', 'PE000083', 'PENGAJUAN', '', '', '2025-12-22 07:18:43', ''),
('694f42f87d1', 'RQ000087', 'PE000084', 'PENGAJUAN', '', '', '2025-12-27 02:22:48', ''),
('6951e144e9e', 'RQ000087', 'PG000005', 'Dalam Proses Survei', '', '', '2025-12-29 02:02:44', ''),
('6951e16aae1', 'RQ000087', 'PG000005', 'Proses Survei Selesai', '', '', '2025-12-29 02:03:22', ''),
('6951f89e7cc', 'RQ000087', 'PG000036', 'Verifikasi Proses', '', '', '2025-12-29 03:42:22', ''),
('6954d660305', 'RQ000086', 'PG000014', 'Dalam Proses Survei', '', '', '2025-12-31 07:53:04', ''),
('6954d733649', 'RQ000086', 'PG000014', 'Proses Survei Selesai', '', '', '2025-12-31 07:56:35', ''),
('6954e9406df', 'RQ000087', 'PG000010', 'Dalam Proses Pasang', '', '', '2025-12-31 09:13:36', ''),
('6954ea2876a', 'RQ000087', 'PG000010', 'Terpasang', '', '', '2025-12-31 09:17:28', ''),
('695769e6495', 'RQ000086', 'PG000036', 'Verifikasi Proses', '', '', '2026-01-02 06:47:02', ''),
('69576a9b832', 'RQ000087', 'PG000036', 'Berhasil Active', '', '', '2026-01-02 06:50:03', ''),
('69576a9c449', '', 'PG000036', 'Berhasil Active', '', '', '2026-01-02 06:50:04', ''),
('695d9e6f0a8', 'RQ000086', 'PG000036', 'Gagal', '', 'Pindah ke aplikasi Whusnet', '2026-01-06 23:44:47', 'Belum Terpasang'),
('6980291c488', 'RQ000088', 'PE000085', 'PENGAJUAN', '', '', '2026-02-02 04:33:32', ''),
('6985696d2a1', 'RQ000088', 'PG000014', 'Dalam Proses Survei', '', '', '2026-02-06 04:09:17', ''),
('698569d063e', 'RQ000088', 'PG000014', 'Proses Survei Selesai', '', '', '2026-02-06 04:10:56', ''),
('69858ee70ac', 'RQ000088', 'PG000036', 'Verifikasi Proses', '', '', '2026-02-06 06:49:11', ''),
('6985e69c77d', 'RQ000088', 'PG000014', 'Dalam Proses Pasang', '', '', '2026-02-06 13:03:24', ''),
('6985e784621', 'RQ000088', 'PG000014', 'Terpasang', '', '', '2026-02-06 13:07:16', ''),
('6986e9b0ed5', 'RQ000088', 'PG000036', 'Berhasil Active', '', '', '2026-02-07 07:28:48', ''),
('6986e9b1c5e', '', 'PG000036', 'Berhasil Active', '', '', '2026-02-07 07:28:49', ''),
('69aa0d9f739', 'RQ000035', 'PG000036', 'Putus Langganan', '', 'Pindah rumah', '2026-03-05 23:11:27', ''),
('69aa0db1629', '', 'PG000036', 'Alat telah kembali', '', '', '2026-03-05 23:11:45', 'Sudah diambil'),
('69cb0b9a2f4', 'RQ000089', 'PE000086', 'PENGAJUAN', '', '', '2026-03-30 23:47:38', ''),
('69cf3c3a455', 'RQ000089', 'PG000036', 'Gagal', '', 'Sudah pasang provider lain', '2026-04-03 04:04:10', 'Belum Terpasang'),
('69d346225c4', 'RQ000042', 'PG000036', 'Putus Langganan', '', 'Sudah tidak ada yang memakai karena ditunggal kerja di luar', '2026-04-06 05:35:30', ''),
('69d34632160', '', 'PG000036', 'Alat telah kembali', '', '', '2026-04-06 05:35:46', 'Sudah diambil'),
('69d741f88af', 'RQ000090', 'PE000087', 'PENGAJUAN', '', '', '2026-04-09 06:06:48', ''),
('69dc965961f', 'RQ000090', 'PG000036', 'Gagal', '', 'Pindah aplikasi Jetis', '2026-04-13 07:08:09', 'Belum Terpasang'),
('69e58913dec', 'RQ000091', 'PE000088', 'PENGAJUAN', '', '', '2026-04-20 02:01:55', ''),
('69e9944d2f0', 'RQ000091', 'PG000014', 'Dalam Proses Survei', '', '', '2026-04-23 03:38:53', ''),
('69e995d160c', 'RQ000091', 'PG000014', 'Proses Survei Selesai', '', '', '2026-04-23 03:45:21', ''),
('69e99b733ee', 'RQ000091', 'PG000036', 'Verifikasi Proses', '', '', '2026-04-23 04:09:23', ''),
('69e9b23d65d', 'RQ000091', 'PG000014', 'Dalam Proses Pasang', '', '', '2026-04-23 05:46:37', '');
INSERT INTO `riwayat_pelanggan` (`ID`, `IDPERMINTAAN`, `CREATEBY`, `STATUSTINDAKAN`, `STATUSLANGGANAN`, `ALASAN`, `TGLTINDAKAN`, `STATUSTINDAKANALAT`) VALUES
('69e9f001a60', 'RQ000091', 'PG000014', 'Terpasang', '', '', '2026-04-23 10:10:09', ''),
('69eae3fc86b', 'RQ000091', 'PG000036', 'Berhasil Active', '', '', '2026-04-24 03:31:08', ''),
('69f0324cdef', 'RQ000092', 'PE000089', 'PENGAJUAN', '', '', '2026-04-28 04:06:36', ''),
('69f051a590d', 'RQ000093', 'PE000090', 'PENGAJUAN', '', '', '2026-04-28 06:20:21', ''),
('69f19065b5b', 'RQ000092', 'PG000014', 'Dalam Proses Survei', '', '', '2026-04-29 05:00:21', ''),
('69f2bd44d23', 'RQ000092', 'PG000014', 'Proses Survei Selesai', '', '', '2026-04-30 02:24:04', ''),
('69f2c4df6fd', 'RQ000092', 'PG000036', 'Verifikasi Proses', '', '', '2026-04-30 02:56:31', ''),
('69f2c53bd5a', 'RQ000092', 'PG000014', 'Dalam Proses Pasang', '', '', '2026-04-30 02:58:03', ''),
('69f2ec36883', 'RQ000093', 'PG000014', 'Dalam Proses Survei', '', '', '2026-04-30 05:44:22', ''),
('69f2ec8d510', 'RQ000093', 'PG000014', 'Proses Survei Selesai', '', '', '2026-04-30 05:45:49', ''),
('69f2ed7a3bc', 'RQ000092', 'PG000014', 'Terpasang', '', '', '2026-04-30 05:49:46', ''),
('69f2fe02c59', 'RQ000092', 'PG000036', 'Berhasil Active', '', '', '2026-04-30 07:00:18', ''),
('69f57562dc6', 'RQ000094', 'PE000091', 'PENGAJUAN', '', '', '2026-05-02 03:54:10', ''),
('69f57fba8fc', 'RQ000093', 'PG000036', 'Gagal', '', 'Tidak tercover', '2026-05-02 04:38:18', 'Belum Terpasang'),
('69f85a08818', 'RQ000094', 'PG000013', 'Dalam Proses Survei', '', '', '2026-05-04 08:34:16', ''),
('69f85a35d1c', 'RQ000094', 'PG000013', 'Proses Survei Selesai', '', '', '2026-05-04 08:35:01', ''),
('69f939ed576', 'RQ000094', 'PG000036', 'Verifikasi Proses', '', '', '2026-05-05 00:29:33', ''),
('69f9746b7fa', 'RQ000095', 'PE000092', 'PENGAJUAN', '', '', '2026-05-05 04:39:07', ''),
('69fbf2d1df9', 'RQ000095', 'PG000014', 'Dalam Proses Survei', '', '', '2026-05-07 02:02:57', ''),
('69fc06ee465', 'RQ000096', 'PE000093', 'PENGAJUAN', '', '', '2026-05-07 03:28:46', ''),
('69fc07c203f', 'RQ000095', 'PG000014', 'Proses Survei Selesai', '', '', '2026-05-07 03:32:18', ''),
('69fc1324a9b', 'RQ000095', 'PG000036', 'Verifikasi Proses', '', '', '2026-05-07 04:20:52', ''),
('69fc1327c1a', 'RQ000095', 'PG000036', 'Verifikasi Proses', '', '', '2026-05-07 04:20:55', ''),
('69fc14d6282', 'RQ000094', 'PG000013', 'Dalam Proses Pasang', '', '', '2026-05-07 04:28:06', ''),
('69fc165dd50', 'RQ000094', 'PG000013', 'Terpasang', '', '', '2026-05-07 04:34:37', ''),
('69fc44ae0f0', 'RQ000095', 'PG000014', 'Dalam Proses Pasang', '', '', '2026-05-07 07:52:14', ''),
('69fc518a322', 'RQ000094', 'PG000036', 'Berhasil Active', '', '', '2026-05-07 08:47:06', ''),
('69fc5904655', 'RQ000095', 'PG000014', 'Terpasang', '', '', '2026-05-07 09:19:00', ''),
('69fc5ba16f2', 'RQ000096', 'PG000014', 'Dalam Proses Survei', '', '', '2026-05-07 09:30:09', ''),
('69fc5c3ca69', 'RQ000096', 'PG000014', 'Proses Survei Selesai', '', '', '2026-05-07 09:32:44', ''),
('69fd4b6e9f7', 'RQ000095', 'PG000036', 'Berhasil Active', '', '', '2026-05-08 02:33:18', ''),
('69fe7c1cc8f', 'RQ000096', 'PG000036', 'Verifikasi Proses', '', '', '2026-05-09 00:13:16', ''),
('69febe4a4c1', 'RQ000096', 'PG000014', 'Dalam Proses Pasang', '', '', '2026-05-09 04:55:38', ''),
('69fed1ec071', 'RQ000096', 'PG000014', 'Terpasang', '', '', '2026-05-09 06:19:24', ''),
('6a015a11a88', 'RQ000097', 'PE000094', 'PENGAJUAN', '', '', '2026-05-11 04:24:49', ''),
('6a02d5a9959', 'RQ000096', 'PG000036', 'Berhasil Active', '', '', '2026-05-12 07:24:25', ''),
('6a0517a0dce', 'RQ000097', 'PG000036', 'Gagal', '', 'Pemasangan terlalu lama, karena masih harus menunggu penarikan ODP', '2026-05-14 00:30:24', 'Belum Terpasang'),
('6a0581a0d85', 'RQ000097', 'PG000036', '', 'Kembali Pengajuan', '', '2026-05-14 08:02:40', ''),
('6a066cd820a', 'RQ000098', 'PE000095', 'PENGAJUAN', '', '', '2026-05-15 00:46:16', ''),
('6a06c393563', 'RQ000097', 'PG000013', 'Dalam Proses Survei', '', '', '2026-05-15 06:56:19', ''),
('6a06c3c1526', 'RQ000097', 'PG000013', 'Proses Survei Selesai', '', '', '2026-05-15 06:57:05', ''),
('6a06c3c7ba8', 'RQ000098', 'PG000013', 'Dalam Proses Survei', '', '', '2026-05-15 06:57:11', ''),
('6a06c3e98c2', 'RQ000098', 'PG000013', 'Proses Survei Selesai', '', '', '2026-05-15 06:57:45', ''),
('6a06e775313', 'RQ000097', 'PG000002', 'Verifikasi Proses', '', '', '2026-05-15 09:29:25', ''),
('6a06e77f027', 'RQ000098', 'PG000002', 'Verifikasi Proses', '', '', '2026-05-15 09:29:35', ''),
('6a0a702bf13', 'RQ000099', 'PE000096', 'PENGAJUAN', '', '', '2026-05-18 01:49:31', ''),
('6a0c0f8adb8', 'RQ000100', 'PE000097', 'PENGAJUAN', '', '', '2026-05-19 07:21:46', ''),
('6a0c367abcc', 'RQ000098', 'PG000005', 'Dalam Proses Pasang', '', '', '2026-05-19 10:07:54', ''),
('6a0c3754f15', 'RQ000098', 'PG000005', 'Terpasang', '', '', '2026-05-19 10:11:32', ''),
('6a0d6cb9e56', 'RQ000098', 'PG000036', 'Berhasil Active', '', '', '2026-05-20 08:11:37', ''),
('6a1013685f4', 'RQ000100', 'PG000036', 'Gagal', '', 'Pindah ke OLT Jetis', '2026-05-22 08:27:20', 'Belum Terpasang'),
('6a11674b144', 'RQ000100', 'PG000036', '', 'Kembali Pengajuan', '', '2026-05-23 08:37:31', ''),
('6a118e3d510', 'RQ000099', 'PG000005', 'Dalam Proses Survei', '', '', '2026-05-23 11:23:41', ''),
('6a118e5c4ae', 'RQ000099', 'PG000005', 'Proses Survei Selesai', '', '', '2026-05-23 11:24:12', ''),
('6a118ead247', 'RQ000100', 'PG000005', 'Dalam Proses Survei', '', '', '2026-05-23 11:25:33', ''),
('6a118ed51e0', 'RQ000100', 'PG000005', 'Proses Survei Selesai', '', '', '2026-05-23 11:26:13', ''),
('6a13a7ed810', 'RQ000099', 'PG000036', 'Verifikasi Proses', '', '', '2026-05-25 01:37:49', ''),
('6a13b05c821', 'RQ000100', 'PG000036', 'Verifikasi Proses', '', '', '2026-05-25 02:13:48', ''),
('6a13ba3ccf4', 'RQ000101', 'PE000098', 'PENGAJUAN', '', '', '2026-05-25 02:55:56', ''),
('6a17ebd11ae', 'RQ000097', 'PG000013', 'Dalam Proses Pasang', '', '', '2026-05-28 07:16:33', ''),
('6a1820024cd', 'RQ000099', 'PG000005', 'Dalam Proses Pasang', '', '', '2026-05-28 10:59:14', ''),
('6a1820cfbf8', 'RQ000099', 'PG000005', 'Terpasang', '', '', '2026-05-28 11:02:39', ''),
('6a193e874a4', 'RQ000099', 'PG000036', 'Berhasil Active', '', '', '2026-05-29 07:21:43', ''),
('6a1a189dbc0', 'RQ000097', 'PG000005', 'Terpasang', '', '', '2026-05-29 22:52:13', ''),
('6a1a8e5c0cf', 'RQ000097', 'PG000036', 'Berhasil Active', '', '', '2026-05-30 07:14:36', ''),
('6a1aa137c52', 'RQ000102', 'PE000099', 'PENGAJUAN', '', '', '2026-05-30 08:35:03', ''),
('6a1cd6f162a', 'RQ000101', 'PG000036', 'Gagal', '', 'Keberatan biaya over kabel', '2026-06-01 00:48:49', 'Belum Terpasang'),
('6a1cdb0c863', 'RQ000103', 'PE000100', 'PENGAJUAN', '', '', '2026-06-01 01:06:20', ''),
('6a1d3bb1b2d', 'RQ000103', 'PG000038', 'Dalam Proses Survei', '', '', '2026-06-01 07:58:41', ''),
('6a1d3c3d4b6', 'RQ000103', 'PG000038', 'Proses Survei Selesai', '', '', '2026-06-01 08:01:01', ''),
('6a1d556d0da', 'RQ000102', 'PG000006', 'Dalam Proses Survei', '', '', '2026-06-01 09:48:29', ''),
('6a1d559a554', 'RQ000102', 'PG000006', 'Proses Survei Selesai', '', '', '2026-06-01 09:49:14', ''),
('6a1e21de2c8', 'RQ000104', 'PE000101', 'PENGAJUAN', '', '', '2026-06-02 00:20:46', ''),
('6a1e25f1a88', 'RQ000102', 'PG000036', 'Verifikasi Proses', '', '', '2026-06-02 00:38:09', ''),
('6a1e297e7c0', 'RQ000103', 'PG000036', 'Verifikasi Proses', '', '', '2026-06-02 00:53:18', ''),
('6a1e834ca1c', 'RQ000105', 'PE000102', 'PENGAJUAN', '', '', '2026-06-02 07:16:28', ''),
('6a1e8e08497', 'RQ000105', 'PG000036', 'Gagal', '', 'Sudah pasang provider lain', '2026-06-02 08:02:16', 'Belum Terpasang'),
('6a1ff1deca8', 'RQ000103', 'PG000037', 'Dalam Proses Pasang', '', '', '2026-06-03 09:20:30', ''),
('6a1ff2513e7', 'RQ000103', 'PG000037', 'Terpasang', '', '', '2026-06-03 09:22:25', ''),
('6a1ff25b7eb', 'RQ000100', 'PG000037', 'Dalam Proses Pasang', '', '', '2026-06-03 09:22:35', ''),
('6a1ffe1f3ed', 'RQ000102', 'PG000006', 'Dalam Proses Pasang', '', '', '2026-06-03 10:12:47', ''),
('6a1ffec3699', 'RQ000102', 'PG000006', 'Terpasang', '', '', '2026-06-03 10:15:31', ''),
('6a1fffbae06', 'RQ000100', 'PG000037', 'Terpasang', '', '', '2026-06-03 10:19:38', ''),
('6a20e2fa240', 'RQ000106', 'PE000103', 'PENGAJUAN', '', '', '2026-06-04 02:29:14', ''),
('6a2173e5be2', 'RQ000104', 'PG000037', 'Dalam Proses Survei', '', '', '2026-06-04 12:47:33', ''),
('6a217405b6d', 'RQ000104', 'PG000037', 'Proses Survei Selesai', '', '', '2026-06-04 12:48:05', ''),
('6a223d29518', 'RQ000106', 'PG000038', 'Dalam Proses Survei', '', '', '2026-06-05 03:06:17', ''),
('6a223d8564b', 'RQ000106', 'PG000038', 'Proses Survei Selesai', '', '', '2026-06-05 03:07:49', ''),
('6a22499a80c', 'RQ000106', 'PG000036', 'Gagal', '', 'Tidak setuju dengan biaya tambahannya', '2026-06-05 03:59:22', 'Belum Terpasang'),
('6a22696eead', 'RQ000104', 'PG000036', 'Verifikasi Proses', '', '', '2026-06-05 06:15:10', ''),
('6a2284c28a1', 'RQ000103', 'PG000036', 'Berhasil Active', '', '', '2026-06-05 08:11:46', ''),
('6a228afdcf4', 'RQ000100', 'PG000036', 'Berhasil Active', '', '', '2026-06-05 08:38:21', ''),
('6a23d3b580d', 'RQ000102', 'PG000036', 'Berhasil Active', '', '', '2026-06-06 08:00:53', ''),
('6a2955f0d1b', 'RQ000104', 'PG000037', 'Dalam Proses Pasang', '', '', '2026-06-10 12:17:52', ''),
('6a295700617', 'RQ000104', 'PG000037', 'Terpasang', '', '', '2026-06-10 12:22:24', ''),
('6a2a66a3140', 'RQ000104', 'PG000036', 'Berhasil Active', '', '', '2026-06-11 07:41:23', ''),
('6a326233997', 'RQ000107', 'PE000104', 'PENGAJUAN', '', '', '2026-06-17 09:00:35', ''),
('6a360efc80f', 'RQ000107', 'PG000039', 'Dalam Proses Survei', '', '', '2026-06-20 03:54:36', ''),
('6a360ff0752', 'RQ000107', 'PG000039', 'Proses Survei Selesai', '', '', '2026-06-20 03:58:40', ''),
('6a36349cb4e', 'RQ000107', 'PG000036', 'Verifikasi Proses', '', '', '2026-06-20 06:35:08', ''),
('6a365b61bea', 'RQ000107', 'PG000039', 'Dalam Proses Pasang', '', '', '2026-06-20 09:20:33', ''),
('6a365bd49b4', 'RQ000107', 'PG000039', 'Terpasang', '', '', '2026-06-20 09:22:28', ''),
('6a39eeb71f5', 'RQ000108', 'PE000105', 'PENGAJUAN', '', '', '2026-06-23 02:25:59', ''),
('6a3a28b55d0', 'RQ000107', 'PG000036', 'Berhasil Active', '', '', '2026-06-23 06:33:25', ''),
('6a3ca11e377', 'RQ000109', 'PE000106', 'PENGAJUAN', '', '', '2026-06-25 03:31:42', ''),
('6a3df20b13b', 'RQ000109', 'PG000005', 'Dalam Proses Survei', '', '', '2026-06-26 03:29:15', ''),
('6a3df234e53', 'RQ000109', 'PG000005', 'Proses Survei Selesai', '', '', '2026-06-26 03:29:56', ''),
('6a3e1b7e62e', 'RQ000109', 'PG000036', 'Verifikasi Proses', '', '', '2026-06-26 06:26:06', ''),
('6a411e11d8c', 'RQ000109', 'PG000040', 'Dalam Proses Pasang', '', '', '2026-06-28 13:13:53', ''),
('6a411ee1bad', 'RQ000109', 'PG000040', 'Terpasang', '', '', '2026-06-28 13:17:21', ''),
('6a421013e99', 'RQ000109', 'PG000036', 'Berhasil Active', '', '', '2026-06-29 06:26:27', ''),
('6a43bd34400', 'RQ000108', 'PG000038', 'Dalam Proses Survei', '', '', '2026-06-30 12:57:24', ''),
('6a43bd5f182', 'RQ000108', 'PG000038', 'Proses Survei Selesai', '', '', '2026-06-30 12:58:07', ''),
('6a44b50d2f7', 'RQ000108', 'PG000036', 'Verifikasi Proses', '', '', '2026-07-01 06:34:53', ''),
('6a44d617862', 'RQ000108', 'PG000038', 'Dalam Proses Pasang', '', '', '2026-07-01 08:55:51', ''),
('6a44d6d3f40', 'RQ000108', 'PG000038', 'Terpasang', '', '', '2026-07-01 08:58:59', ''),
('6a45dcc618d', 'RQ000108', 'PG000036', 'Berhasil Active', '', '', '2026-07-02 03:36:38', ''),
('6a6c54d9af3', 'RQ000110', 'PE000107', 'PENGAJUAN', '', '', '2026-07-31 07:55:05', ''),
('6a6ea295145', 'RQ000110', 'PG000040', 'Dalam Proses Survei', '', '', '2026-08-02 01:51:17', ''),
('6a6ea2b437a', 'RQ000110', 'PG000040', 'Proses Survei Selesai', '', '', '2026-08-02 01:51:48', ''),
('6a7018fb280', 'RQ000110', 'PG000036', 'Verifikasi Proses', '', '', '2026-08-03 04:28:43', ''),
('6a71e9dd54e', 'RQ000110', 'PG000040', 'Dalam Proses Pasang', '', '', '2026-08-04 13:32:13', ''),
('6a71ea6dc09', 'RQ000110', 'PG000040', 'Terpasang', '', '', '2026-08-04 13:34:37', ''),
('6a72e9cc8e3', 'RQ000110', 'PG000036', 'Berhasil Active', '', '', '2026-08-05 07:44:12', ''),
('6a7ebdede64', 'RQ000111', 'PE000108', 'PENGAJUAN', '', '', '2026-08-14 07:04:13', ''),
('6a7ececb59a', 'RQ000111', 'PG000040', 'Dalam Proses Survei', '', '', '2026-08-14 08:16:11', ''),
('6a7eceeb767', 'RQ000111', 'PG000040', 'Proses Survei Selesai', '', '', '2026-08-14 08:16:43', ''),
('6a7ed042336', 'RQ000111', 'PG000036', 'Verifikasi Proses', '', '', '2026-08-14 08:22:26', ''),
('6a7edb7db31', 'RQ000111', 'PG000040', 'Dalam Proses Pasang', '', '', '2026-08-14 09:10:21', ''),
('6a7edbf8055', 'RQ000111', 'PG000040', 'Terpasang', '', '', '2026-08-14 09:12:24', ''),
('6a801cec2ba', 'RQ000111', 'PG000036', 'Berhasil Active', '', '', '2026-08-15 08:01:48', ''),
('6a9a6386841', 'RQ000112', 'PE000109', 'PENGAJUAN', '', '', '2026-09-04 06:21:58', ''),
('6a9e0edd21d', 'RQ000113', 'PE000110', 'PENGAJUAN', '', '', '2026-09-07 01:09:49', ''),
('6a9e1211947', 'RQ000112', 'PG000005', 'Gagal', '', 'Rumah akan segera dikosongkan (pindah rumah)', '2026-09-07 01:23:29', 'Belum Terpasang'),
('6aa23c70c86', 'RQ000113', 'PG000005', 'Dalam Proses Survei', '', '', '2026-09-10 05:13:20', ''),
('6aa23c96418', 'RQ000113', 'PG000005', 'Proses Survei Selesai', '', '', '2026-09-10 05:13:58', ''),
('6aa267bebe2', 'RQ000113', 'PG000036', 'Verifikasi Proses', '', '', '2026-09-10 08:18:06', ''),
('6aa424cd0d0', 'RQ000113', 'PG000005', 'Dalam Proses Pasang', '', '', '2026-09-11 15:57:01', ''),
('6aa42562a3e', 'RQ000113', 'PG000005', 'Terpasang', '', '', '2026-09-11 15:59:30', ''),
('6aa4ed963aa', 'RQ000113', 'PG000036', 'Berhasil Active', '', '', '2026-09-12 06:13:42', ''),
('6ae5962762a', 'RQ000114', 'PE000111', 'PENGAJUAN', '', '', '2026-10-31 07:17:59', '');

-- --------------------------------------------------------

--
-- Struktur dari tabel `router`
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
-- Struktur dari tabel `satuan`
--

CREATE TABLE `satuan` (
  `IDSATUAN` int(11) NOT NULL,
  `NAMASATUAN` varchar(30) DEFAULT NULL,
  `SEBUTAN` varchar(5) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `setting_billing`
--

CREATE TABLE `setting_billing` (
  `IDSETTING` int(5) NOT NULL,
  `JENIS_SETTING` varchar(200) NOT NULL,
  `VALUE_SETTING` varchar(200) NOT NULL,
  `SATUAN_SETTING` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `setting_billing`
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
-- Struktur dari tabel `set_app`
--

CREATE TABLE `set_app` (
  `ID` int(1) NOT NULL,
  `NAMA` varchar(50) NOT NULL,
  `ALAMAT` varchar(100) NOT NULL,
  `KONTAK` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `statusbarang`
--

CREATE TABLE `statusbarang` (
  `IDSTATUSBARANG` int(11) NOT NULL,
  `KODEBARANG` varchar(10) DEFAULT NULL,
  `IDMERK` varchar(11) NOT NULL,
  `TGLINPUT` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `TGLUPDATE` datetime NOT NULL,
  `TGLKEMBALI` date NOT NULL,
  `STATUSBARANG` varchar(70) DEFAULT NULL,
  `STATUSKEADAAN` varchar(70) DEFAULT NULL,
  `SUMBERDAYAID` varchar(11) DEFAULT NULL,
  `IDPERMINTAAN` varchar(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `statusbarang`
--

INSERT INTO `statusbarang` (`IDSTATUSBARANG`, `KODEBARANG`, `IDMERK`, `TGLINPUT`, `TGLUPDATE`, `TGLKEMBALI`, `STATUSBARANG`, `STATUSKEADAAN`, `SUMBERDAYAID`, `IDPERMINTAAN`) VALUES
(0, 'BR000001', 'MR000001', NULL, '0000-00-00 00:00:00', '0000-00-00', 'TERSEDIA', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `sumberdaya`
--

CREATE TABLE `sumberdaya` (
  `IDSD` int(11) NOT NULL,
  `NAMA` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `survey_pemasangan_wifi`
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
-- Dumping data untuk tabel `survey_pemasangan_wifi`
--

INSERT INTO `survey_pemasangan_wifi` (`IDSURVEY`, `IDPENGGUNA`, `IDPERMINTAAN`, `LAT`, `LONG`, `GEOGRAFIS`, `SINYAL`, `FOTORUMAH`, `ESTIMASIKEBUTUHAN`, `ALATPASIF`, `KATEGORITINGKAT`, `BIAYAPEMASANGAN`, `TGLSURVEY`) VALUES
('spw660a6c6a', 'PE000001', 'RQ000001', '-7.977149830195099', '111.48279594290237', NULL, NULL, '6d63682e0eaf8acc31c88e1a0fbbb4f8.jpeg', 'router', '', 'SEDANG', NULL, '2024-04-01 08:12:26'),
('spw660a6ca1', 'PE000002', 'RQ000002', '-7.986686129415344', '111.48964981349327', NULL, NULL, '1acbd48352ad8184f168c79f6aac7e29.jpeg', 'router', '', 'SEDANG', NULL, '2024-04-01 08:13:21'),
('spw660a6ccb', 'PE000003', 'RQ000003', '-7.988166721092596 ', '111.49535998020464', NULL, NULL, '42dba9f3f82e48eb45869abce4bda849.jpeg', 'ruouter', '', 'SEDANG', NULL, '2024-04-01 08:14:03'),
('spw660a6cfa', 'PE000004', 'RQ000004', '-7.978812492481689 ', '111.49340893787566', NULL, NULL, '92e77927e759ce29678e9a816dffbeb1.jpeg', 'router', '', 'MUDAH', NULL, '2024-04-01 08:14:50'),
('spw6634559b', 'PE000005', 'RQ000005', '-7.989789818607847', '111.48814189814755', NULL, NULL, '624a75fad36f7e3e604b7c9a94863376.jpeg', 'KABEL', '', 'SEDANG', NULL, '2025-11-15 04:04:55'),
('spw660a6d23', 'PE000006', 'RQ000006', '-7.962692171551547', '111.48915822449817', NULL, NULL, 'a67d4cf59e12662efc8ed99ebbee6776.jpeg', 'router', '', 'MUDAH', NULL, '2024-04-01 08:15:31'),
('spw660a6d7f', 'PE000007', 'RQ000007', '-7.988473198746671', '111.48258378205378', NULL, NULL, '91cb5be348d2ae739a59c752384d5c99.jpeg', 'router', '', 'SEDANG', NULL, '2024-04-01 08:17:03'),
('spw660a6e01', 'PE000008', 'RQ000008', '-7.988962897871736', '111.4967835532181', NULL, NULL, '8ea77b19944eca51034f32f8e90c7b42.jpeg', 'router', '', 'SULIT', NULL, '2024-04-01 08:19:13'),
('spw660a6e21', 'PE000009', 'RQ000009', '-7.976334091453576', '111.47246971088916', NULL, NULL, 'bb0ce9cbec061bf86110590d2a9a14f9.jpeg', 'router', '', 'SEDANG', NULL, '2024-04-01 08:19:45'),
('spw66261b67', 'PE000010', 'RQ000010', '-7.989534910514298', '111.49398783799032', NULL, NULL, '0065e092734e9ff5a06457455aae884a.jpg', 'Dropcore 100 Meter\r\nRouter XPON\r\nPatchcore 1 Pasang ', 'Fastcont 2', 'SEDANG', NULL, '2024-04-23 07:00:02'),
('spw660a25c7', 'PE000011', 'RQ000011', '-7.993685529720685', '111.48468542455974', NULL, NULL, 'aed9083cd58af9ae620275a98057bd3b.jpeg', 'router GPON\r\nfastcont 2\r\nDropcore 100 meter', 'fastcont 2', 'SEDANG', NULL, '2024-04-01 03:27:45'),
('spw660a29b6', 'PE000012', 'RQ000012', '-7.885988', '111.5050955', NULL, NULL, 'ac65020273a82c25fa5a4e8c21d8f05c.png', 'tes data', '', 'SEDANG', NULL, '2024-04-01 03:27:50'),
('spw66271b45', 'PE000013', 'RQ000013', '-7.98974159827631', '111.49446552438263', NULL, NULL, '324c1358f370a5f9ec27319d81896a2a.jpg', 'Dropcore 120 Meter\r\nRouter GPON \r\nFastcont 2', 'Fastcont 2', 'MUDAH', NULL, '2024-04-23 07:04:05'),
('spw663455e8', 'PE000014', 'RQ000014', '1', '1', NULL, NULL, NULL, 'KABEL', '', 'MUDAH', NULL, '2024-05-03 03:11:36'),
('spw66345615', 'PE000015', 'RQ000015', '1', '1', NULL, NULL, '63c49326ec6b1f402db8edf92b8394c5.jpeg', 'KABEL', '', 'SULIT', NULL, '2024-05-03 03:12:21'),
('spw663229e5', 'PE000016', 'RQ000016', '-7.979966693444841', '111.50545981083545', NULL, NULL, 'bef391c28601e6bdba5219adb95a5569.jpg', 'kabel drop.core 110 meter\r\nruter gphon.\r\npatcore sepasang', 'Ruter', 'MUDAH', NULL, '2024-05-03 11:24:41'),
('spw66359bcb', 'PE000017', 'RQ000017', '-7.990135', '111.497316', NULL, NULL, '1189de50a4d10170d03e31cd574e92b6.jpg', 'Dropcore ± 150 Meter\r\nRouter GPON \r\nFastcont 2 / Patchcore ', '1:2', 'SULIT', NULL, '2024-05-11 09:42:57'),
('spw66358eff', 'PE000018', 'RQ000018', '-7.986728030382605', '111.48925501064558', NULL, NULL, NULL, 'KABEL', '', 'SEDANG', NULL, '2024-05-04 01:27:27'),
('spw66359283', 'PE000019', 'RQ000019', '-7.963170', '111.489338', NULL, NULL, NULL, 'Tower Monopole 2 Pipa\r\nMikrotik LHG 5\r\nRouter Tp Link TL WR840N\r\nKabel 20 M', '', 'MUDAH', NULL, '2024-05-04 01:42:27'),
('spw6644644d', 'PE000020', 'RQ000020', '-7.980084938783042', '111.5054259554308', NULL, NULL, NULL, NULL, '', '', NULL, NULL),
('spw6674dabd', 'PE000021', 'RQ000021', '-79610110', '111.4913200', NULL, NULL, 'f038d6aba3203b39677aa3e947ac4f35.jpg', 'kabel', '', 'MUDAH', NULL, '2024-06-21 01:43:25'),
('spw66694670', 'PE000022', 'RQ000022', '-7.992811061816721', '111.48846895636377', NULL, NULL, NULL, NULL, '', '', NULL, NULL),
('6685a38d76f', 'PE000023', 'RQ000023', '-8.00051', '111.498333', NULL, NULL, NULL, '', '', 'MUDAH', NULL, NULL),
('6685a3f0142', 'PE000024', 'RQ000024', '-7.928029', '111.458116', NULL, NULL, NULL, '', '', 'MUDAH', NULL, NULL),
('spw66ced68d', 'PE000025', 'RQ000025', '-8.002724', '111.498859', NULL, NULL, 'ddb60b81c93bd431f2582af565ecd4a9.png', 'Kabel, GPON', '', 'SEDANG', NULL, '2024-08-28 07:49:33'),
('spw668e0003', 'PE000026', 'RQ000026', '-7.86971302841363', '111.4316980668257', NULL, NULL, NULL, NULL, '', '', NULL, NULL),
('spw668f30fb', 'PE000031', 'RQ000027', '-7.86971302841363', '111.4721366018057', NULL, NULL, NULL, NULL, '', '', NULL, NULL),
('spw669c88da', 'PE000032', 'RQ000028', '-8.002624', '111.499135', NULL, NULL, 'f1cc85f7c8aae0e724bccd1cd70867be.png', 'Kabel fo 200m, modem gpon, patcore 1 pcs', 'patcore 1 pcs', 'SEDANG', NULL, '2024-07-21 04:11:19'),
('spw66c964a6', 'PE000033', 'RQ000029', '-7.979182', '111.490103', NULL, NULL, '86a35c0ad05dab0e30d77d1752c8d564.jpeg', 'router Gpone\r\nkabel FO 150\r\nfastcon', 'Fastcon 2biji ', 'SULIT', NULL, '2024-08-28 02:41:36'),
('spw67047ebd', 'PE000034', 'RQ000030', '-7.993028', '111.475405', NULL, NULL, 'a20290f61c433866711416ef78ed618a.jpg', 'kabel dropcore ±300 m\r\nrouter gpon\r\npatch cord sepasang', 'kabel dropcore 300m\r\npatch cord', 'SEDANG', NULL, '2024-10-18 00:08:42'),
('spw66f78f85', 'PE000035', 'RQ000031', '-7.6218368', '112.1124352', NULL, NULL, NULL, NULL, '', '', NULL, NULL),
('spw67048001', 'PE000036', 'RQ000032', '-7.985838', '111.489618', NULL, NULL, 'b0ee73b4bbc41e894836bb13e82d1c27.jpg', 'kabel dropcore 150 m\r\nrouter gpon\r\npatch cord', 'kabel dropcore 150m\r\npatch cord sepasang', 'SEDANG', NULL, '2024-10-16 03:05:31'),
('spw671ef2d1', 'PE000037', 'RQ000033', '-7.986502', '111.488887', NULL, NULL, 'd777d8a18d6a5a9b38e27c184afdd179.jpg', 'Dropcore 170±\r\nRouter GPON \r\nPatchcore 1 Pasang', 'patch cord\r\nkabel dropcore 220m', 'SULIT', NULL, '2024-11-12 13:31:12'),
('spw671880a1', 'PE000038', 'RQ000034', '-7.905793', '111.9880199', NULL, NULL, '8dc371b6b6af2318b65afbf5951bb2ff.png', 'TEST', '', 'SEDANG', NULL, '2024-10-23 04:50:41'),
('spw6730c801', 'PE000039', 'RQ000035', '-7.960670', '111.490562', NULL, NULL, '6ee42f66141a7d5ee0ca550533006a55.jpg', 'kabel dropcore 250 m\r\nrouter gpon\r\npatch cord', 'kabel dropcore 200m\r\npatch cord', 'SULIT', NULL, '2024-11-12 13:41:53'),
('spw674f0bd9', 'PE000040', 'RQ000036', '-7.977435', '111.489120', NULL, NULL, '5a35efd7ae3bc68536f3f619110dd9b7.jpg', 'kabel drop core-+ 300 meter\r\njalur sulit tidak ada cagak \r\n\r\nnb: menunggu keputusan dari pihak pelanggan', '', 'SULIT', NULL, '2024-12-03 13:47:05'),
('spw6757c429', 'PE000041', 'RQ000037', '-7.989994', '111.487589', NULL, NULL, '964a318483218758dcd6cef70877f603.jpg', 'Kabel fo +-170m\r\nModem GPON \r\nPatchcord', 'Patchcord 2', 'SULIT', NULL, '2024-12-10 10:09:33'),
('spw676bb021', 'PE000042', 'RQ000038', '-7.982693', '111.491692', NULL, NULL, '979b5379c72794bc3169a1370d57c5c3.jpg', 'kabel drop core 150\r\nruter gphon\r\nfastcone 2', 'ruter', 'SULIT', NULL, '2024-12-25 11:49:40'),
('6774b557261', 'PE000043', 'RQ000039', '', '', NULL, NULL, NULL, '', '', 'MUDAH', NULL, NULL),
('677741d9600', 'PE000044', 'RQ000040', '-7.9880013', '111.4890784', NULL, NULL, 'b5b9d0d4da0f4c373d4723def4a98594.jpg', NULL, '', 'MUDAH', NULL, '2026-02-17 03:45:55'),
('677741d973d', 'PE000045', 'RQ000041', '-7.989790', '111.488142', NULL, NULL, NULL, '', '', 'MUDAH', NULL, NULL),
('677741d9862', 'PE000046', 'RQ000042', '-8.003069', '111.500087', NULL, NULL, NULL, '', '', 'MUDAH', NULL, NULL),
('677741d9985', 'PE000047', 'RQ000043', '-8.003047', '111.499781', NULL, NULL, NULL, '', '', 'MUDAH', NULL, NULL),
('677741d9a3b', 'PE000048', 'RQ000044', '-7.9767270', '111.4773723', NULL, NULL, NULL, '', '', 'MUDAH', NULL, NULL),
('677746da42a', 'PE000049', 'RQ000045', '-8.002114', '111.495832', NULL, NULL, NULL, '', '', 'MUDAH', NULL, NULL),
('spw678b32fb', 'PE000050', 'RQ000046', '-7.984041', '111.480823', NULL, NULL, '7b520e03ecca7e5a2ec3d25a7786512b.jpg', 'kabel drop core 350 meter\r\ncagak 5 biji\r\n\r\nNb;info dari pelanggan tidak jadi pasang\r\n      karena tidak ada cagK dan kabel over ', '', 'SULIT', NULL, '2025-01-18 04:50:03'),
('spw67935e73', 'PE000051', 'RQ000047', '-8.003023', '111.499516', NULL, NULL, '18903a5722a631d65b8f540fef73e84f.jpg', 'Kabel fo -+100m\r\nPatcore\r\nGpon', '', 'SEDANG', NULL, '2025-01-24 09:33:39'),
('spw67b6b571', 'PE000052', 'RQ000048', '-7.994099', '111.488423', NULL, NULL, '7e68ea776669e3eefb2d6e9c2cc8a067.jpg', 'Kabel fo 150m\r\nPatcord sepasang\r\nRouter gpon 1', 'Pathcord sepasang', 'SEDANG', NULL, '2025-02-21 14:10:08'),
('spw67c592e6', 'PE000053', 'RQ000049', '-7.968457', '111.492359', NULL, NULL, 'c047a6401cefd2ce5cac78604fb26e21.jpg', 'kabel drop core 200 meter over ,50\r\nruter gphon\r\npatcore\r\n\r\nnb.tinggal mengaktifkan jalur beckbone', 'Ruter', 'SEDANG', NULL, '2025-03-04 12:19:07'),
('spw67de0547', 'PE000054', 'RQ000050', '-7.982244', '111.510905', NULL, NULL, 'f24b4f02bcc33ee7ca026f3e41910153.jpeg', 'Dropcore 170 Meter\r\nPathcore 1 Pasang\r\nRouter GPON', '', 'SULIT', NULL, '2025-03-22 00:33:11'),
('spw67f49498', 'PE000055', 'RQ000051', '-8.002574', '111.497427', NULL, NULL, '5ec59594941efde57e5c68ea067599d0.jpeg', 'Dropcore 200 Meter\r\nPathcore 1 Pasang\r\nRouter GPON', '', 'SULIT', NULL, '2025-04-08 03:14:32'),
('spw67fdd5a0', 'PE000056', 'RQ000052', '-7.981598', '111.510838', NULL, NULL, '9038dbce772ffb36aa1e598f883f07fe.jpg', 'Kabel fo 130 m\r\nPathcord sepasang\r\nRouter gpon 1', 'Kabel dropcore, patcore', 'SEDANG', NULL, '2025-04-22 11:47:02'),
('spw68468e53', 'PE000057', 'RQ000053', '-7.461524', '112.680374', NULL, NULL, NULL, 'modem tplink\r\nkabel fiber 16 meter\r\nkabel jaringan 2 meter\r\n', '', 'SEDANG', NULL, '2025-06-09 07:33:39'),
('spw6847f26e', 'PE000058', 'RQ000054', '-7.43167', '112.595939', NULL, NULL, 'bc7bec21b618f2209b249f27c39df2da.png', 'kabel jaringan 10 meter\r\nmodem tplink 456\r\nkabel fiber 20 mter', 'fddfdsfdsfdf', 'SEDANG', NULL, '2025-06-10 08:58:09'),
('spw6847f88d', 'PE000059', 'RQ000055', '-7.431816', '112.596064', NULL, NULL, NULL, 'dsfdsfdsdsffds', 'dffdfdf', 'SEDANG', NULL, '2025-06-10 09:24:48'),
('spw684ad4f5', 'US000001', 'RQ000056', 'Quia iste accusamus ', 'Necessitatibus tempo', NULL, NULL, 'c8395cf03216dde7e86655a57909b69a.png', 'Assumenda voluptatem', 'Rerum temporibus nos', 'SULIT', NULL, '2025-06-12 13:31:57'),
('spw684adb93', 'PE000060', 'RQ000057', 'Omnis provident nat', 'Quo quidem reprehend', NULL, NULL, '1d9c904d6c3441513ee07b9dc40b3ac2.png', 'Velit ratione in non', 'Velit ad id odio cor', 'MUDAH', NULL, '2025-06-12 14:12:23'),
('spw684b14ce', 'US000002', 'RQ000058', '61', '12', NULL, NULL, 'b7a305c3f2534531417214a641a91323.jpg', 'Assumenda voluptatem', '', 'SEDANG', NULL, '2025-06-12 17:56:30'),
('spw684b1703', 'PE000061', 'RQ000059', 'Blanditiis minus exe', 'Soluta ea labore ex ', NULL, NULL, '892b2cd415beb7bf9cfa25f0071b143a.jpg', 'Ut atque et qui enim', 'Sed deleniti minus i', 'SEDANG', NULL, '2025-06-13 04:48:21'),
('spw684b1933', 'PE000062', 'RQ000060', 'Inventore et volupta', 'Beatae dignissimos q', NULL, NULL, '7c2832d36f2b81adb797c8509e14f3fa.jpg', 'Animi similique qua', 'Quisquam ea natus de', 'SULIT', NULL, '2025-06-12 18:29:35'),
('spw684b24ac', 'PE000063', 'RQ000061', 'Vitae qui unde quasi', 'Architecto itaque no', NULL, NULL, '197dd1d3c9a779fb817ad133bc12e7b1.jpg', 'Labore voluptatem q', 'Molestiae eius ad as', 'SULIT', NULL, '2025-06-12 19:05:36'),
('spw684bacfb', 'PE000064', 'RQ000062', 'Beatae corrupti eli', 'Quia sit ipsa sapie', NULL, NULL, '14a83e6bb6618ee57fd1c7edc1b552d0.jpg', 'Voluptatem reprehend', '', 'MUDAH', NULL, '2025-06-13 04:45:47'),
('spw68584025', 'US000003', 'RQ000063', 'Dolor facere aliquip', 'Sit reprehenderit q', NULL, NULL, '9bbccde97dd2a0ca75c7dcfdd586f691.jpg', 'Esse veniam quibus', 'Ullamco sit dolore p', 'MUDAH', NULL, '2025-06-25 15:34:45'),
('spw685c12c2', 'US000004', 'RQ000064', 'Ut ut similique eum ', 'Reprehenderit odio n', NULL, NULL, '80d0a4962476798759ec3f6f416a2b8e.jpg', 'Eaque iusto providen', '', 'SULIT', NULL, '2025-06-25 15:16:18'),
('spw6858404f', 'US000005', 'RQ000065', 'Enim illum architec', 'Quo est nulla tenet', NULL, NULL, NULL, 'Quidem reiciendis te', 'Inventore impedit i', 'SEDANG', NULL, '2025-06-22 17:43:32'),
('spw685bfd44', 'US000006', 'RQ000066', '223', '23354', NULL, NULL, NULL, NULL, '', '', NULL, NULL),
('spw685c4ed4', 'PE000065', 'RQ000067', 'Ad officiis consequa', 'Vel omnis adipisci q', NULL, NULL, '1f860d52f6fb3d751a76f13cae89c844.jpg', 'Nam in magnam et qui', 'Facere ad autem odit', 'SULIT', NULL, '2025-06-25 19:36:15'),
('spw688f93ad', 'US000007', 'RQ000068', '223', '23354', NULL, NULL, NULL, 'tes', '', 'SEDANG', NULL, '2025-08-03 16:51:57'),
('spw685c49bf', 'PE000066', 'RQ000069', '223', '23354', NULL, NULL, NULL, NULL, '', '', NULL, NULL),
('spw68649eb8', 'PE000067', 'RQ000070', '-7.986910', '111.488496', NULL, NULL, '44fc728f110aeb2656b2a51d72da11fa.jpg', 'Kabel FO 150 m\r\nPatcore \r\nGpon', 'Ruter', 'SEDANG', NULL, '2025-07-03 00:09:38'),
('spw685f9cd3', 'PE000068', 'RQ000071', 'Est neque irure sed', 'Et est odio occaeca', NULL, NULL, '58ead824a502f26020acca65abaaa583.jpg', 'Est sunt est mollit', '', 'SEDANG', NULL, '2025-06-28 07:42:11'),
('spw685f9de3', 'PE000069', 'RQ000072', 'Magnam voluptatem a', 'At minima similique ', NULL, NULL, 'a2016792f3efe05d4ddccb8a134e7185.jpg', 'Consequuntur autem e', 'Natus architecto dol', 'SEDANG', NULL, '2025-06-29 15:39:40'),
('spw686b8f5d', 'PE000070', 'RQ000073', '-7.986524', '111.489664', NULL, NULL, 'd1c621c266ea872c416f4407d5b598f3.jpg', 'Kabel FO 125m\r\nXpon\r\nPatcore ', 'ruter', 'SEDANG', NULL, '2025-07-07 11:43:06'),
('spw68802b52', 'PE000071', 'RQ000074', '-7.999403', '111.505925', NULL, NULL, '1cfd6406f65c00f6b434bafdd555246f.jpg', 'DC ±350m\r\ntp link\r\nHTB A/B\r\nlan pendek\r\nambil dari hub telaga', 'DC 350m\r\npatchcord', 'SEDANG', NULL, '2025-08-11 00:06:57'),
('spw6881dd74', 'PE000072', 'RQ000075', '-7.961735', '111.536522', NULL, NULL, NULL, NULL, '', '', NULL, NULL),
('spw6888991f', 'PE000073', 'RQ000076', '-7.977100', '111.472732', NULL, NULL, '59827c29f797a462dd51ccbf57a7c0b9.jpg', 'Dropcore 170m\r\nGpon \r\nPatcore \r\n1:4/1:8', '', 'SEDANG', NULL, '2025-07-29 09:49:19'),
('spw6899ca61', 'PE000074', 'RQ000077', '-7.979318', '111.490392', NULL, NULL, 'ac68b41b5f979808713d3928674d0d56.jpg', 'DC 160m\r\nModem Xpon\r\nPatchcord', 'DC 156m\r\nPatchcord', 'SEDANG', NULL, '2025-08-11 11:02:01'),
('spw68e606d8', 'PE000075', 'RQ000078', '-7.962648', '111.489176', NULL, NULL, 'd5bed14d16cf22d7ba966bd4707f0934.jpg', 'Kabel 160\r\nPatch core \r\nModem ', '', 'MUDAH', NULL, '2025-10-08 06:38:16'),
('spw68e6068a', 'PE000076', 'RQ000079', '-7.951688', '111.499786', NULL, NULL, 'c85c06e5ab6bea43ef27bfe49af9e787.jpg', 'Kabel ±100\r\nPatch core \r\nModem ', '', 'MUDAH', NULL, '2025-10-08 06:36:58'),
('spw68e4d0f0', 'PE000077', 'RQ000080', '-7.944300', '111.528355', NULL, NULL, NULL, NULL, '', '', NULL, NULL),
('spw68fe1e43', 'PE000078', 'RQ000081', '-7.977046', '111.474533', NULL, NULL, 'c26b98560c602b38c25c79c1f483c660.jpg', 'kabel dc -+150\r\nruter gpon\r\npatcore\r\nplc 1:4', 'ruter', 'SEDANG', NULL, '2025-10-27 09:49:17'),
('spw690c5deb', 'PE000079', 'RQ000082', '-7.977544', '111.473579', NULL, NULL, '005499a6fee9d599364a9c28942fa1cd.jpg', 'kabel dc 60 m\r\nruter gpon\r\npatcore ', 'ruter', 'SEDANG', NULL, '2025-11-08 07:54:53'),
('spw69097201', 'PE000080', 'RQ000083', '-7.956266', '111.514494', NULL, NULL, NULL, NULL, '', '', NULL, NULL),
('spw693d0c6d', 'PE000081', 'RQ000084', '-7.960787', '111.507036', NULL, NULL, NULL, NULL, '', '', NULL, NULL),
('spw6944ad5d', 'PE000082', 'RQ000085', '-7.959219', '111.507694', NULL, NULL, NULL, NULL, '', '', NULL, NULL),
('spw6954d732', 'PE000083', 'RQ000086', '-7.9635557', '111.5261037', NULL, NULL, 'f3be61585474f5c806d088e6841bc3df.jpg', 'dropcore\r\nruter gpon\r\nbeckbone / Jalur belum tersedia\r\n', '', 'SULIT', NULL, '2025-12-31 07:56:34'),
('spw6951e16a', 'PE000084', 'RQ000087', '-7.977400', '111.474941', NULL, NULL, 'd916fa2fbe177f94c47d34edee232d2e.jpg', 'dropcore 150+-\r\npetcore\r\ngpon', 'Fascone 2', 'SEDANG', NULL, '2025-12-31 09:17:28'),
('spw698569cf', 'PE000085', 'RQ000088', '-7.958187', '111.493698', NULL, NULL, 'fa44a54319aaaf977084905eea01f144.jpg', 'Dc -+100 m\r\nruter gpon\r\npatcore ', 'ruter gpon', 'SEDANG', NULL, '2026-02-06 13:07:16'),
('spw69cb0b9a', 'PE000086', 'RQ000089', '-8.001552', '111.491935', NULL, NULL, NULL, NULL, '', '', NULL, NULL),
('spw69d741f8', 'PE000087', 'RQ000090', '-7.951863', '111.516587', NULL, NULL, NULL, NULL, '', '', NULL, NULL),
('spw69e995d1', 'PE000088', 'RQ000091', '-7.9888440', '111.4936305', NULL, NULL, '14d20ee8c31ba8152639499c4fbb6847.jpg', 'DC 150 m\r\nruter gpon\r\npatcore\r\n\r\nnoted :ngalor e r enek cagak nunut cagak e uwong ', 'ruter', 'SULIT', NULL, '2026-04-23 10:10:09'),
('spw69f2bd44', 'PE000089', 'RQ000092', '-7.990004', '111.500080', NULL, NULL, 'd1f4241cf34383edb1915c221702c606.jpg', 'Dc 530 m\r\nruter gpon dualband\r\npatcore\r\n\r\nnoted:semntra ambil langsung dari odp wra 16 ( plening pertigaan kemingkinan di tambah odp )', 'modem dualband', 'SULIT', NULL, '2026-04-30 05:49:46'),
('spw69f2ec8d', 'PE000090', 'RQ000093', '-7.973006', '111.519967', NULL, NULL, '57325b47c525d7ecbdca6cf679bdecd7.jpg', 'JALUR BELUM TERSEDIA\r\n\r\nnoted: jika pasang minggu\"depan . punya rumah mau keluar kota', '', 'SULIT', NULL, '2026-04-30 05:45:49'),
('spw69f85a35', 'PE000091', 'RQ000094', '-7.976934', '111.481891', NULL, NULL, 'f2a87f07c49f5edbac924d8b47dc3801.jpg', 'kabel 200m\r\npatch core \r\nmodem ', '', 'SULIT', NULL, '2026-05-04 08:35:01'),
('spw69fc07c1', 'PE000092', 'RQ000095', '-7.991271', '111.499912', NULL, NULL, '38acaba514d9586a3d326a10f30e6ece.jpg', 'Dc 140 m\r\nrutet gpon\r\npatcore\r\n1:2\r\nnoted : odp pertigaan cakruk pas', 'spliter 1:2', 'SULIT', NULL, '2026-05-07 09:19:00'),
('spw69fc5c3c', 'PE000093', 'RQ000096', '-7.990910', '111.481586', NULL, NULL, 'cdefb4e3473d08333221111bdc9b40e4.jpg', 'Dc -+ 170 m\r\nruter gpon\r\npatcore\r\n\r\nnoted : odp kpk 20 belum di ketahui\r\n            - sebelum psb harap hubungi pelanggan ', '1:2', 'SULIT', NULL, '2026-05-09 06:19:24'),
('spw6a06c3c0', 'PE000094', 'RQ000097', '-7.9680350', '111.4928832', NULL, NULL, 'f5d1989791e81ff2537628d8fbfea936.jpg', 'kabel±100\r\npatch core \r\nmodem', '', 'MUDAH', NULL, '2026-06-27 01:57:40'),
('spw6a06c3e9', 'PE000095', 'RQ000098', '-7.987700', '111.492826', NULL, NULL, '0288a99c344a43adccdfd9e960da3b21.jpg', 'kabel ±150\r\npatch core \r\nmodem', '', 'SEDANG', NULL, '2026-05-15 06:57:45'),
('spw6a118e5b', 'PE000096', 'RQ000099', '-7.975335', '111.482642', NULL, NULL, '49d3c56ec299ad98884306ea3f90c9f5.jpg', 'drocore 150\r\npetcore\r\ngpon', '', 'SEDANG', NULL, '2026-05-23 11:24:11'),
('spw6a118ed5', 'PE000097', 'RQ000100', '-7.959852', '111.500380', NULL, NULL, '64a15e196d82be5bddc07b0b7eb33b1e.jpg', 'dropcore 150\r\npetcore\r\ngpon\r\nnb :blum ada backbone', '', 'SEDANG', NULL, '2026-05-23 11:26:13'),
('spw6a13ba3c', 'PE000098', 'RQ000101', '-7.983287', '111.491245', NULL, NULL, NULL, NULL, '', '', NULL, NULL),
('spw6a1d5599', 'PE000099', 'RQ000102', '-7.990182', '111.499614', NULL, NULL, '14ff3a2ef9c131143a875fda1a429ce6.jpeg', 'Dropcore 70 Meter\r\nRouter GPON\r\nPathcore 2', '', 'SULIT', NULL, '2026-06-01 09:49:13'),
('spw6a1d3c3d', 'PE000100', 'RQ000103', '-7.991589', '111.499307', NULL, NULL, '90b5c4b1611ccc0dd8ae77f6739e619e.jpg', 'Patchore 1pasang\r\nDc -+200m\r\nRuter', '', 'SULIT', NULL, '2026-06-01 08:01:01'),
('spw6a217405', 'PE000101', 'RQ000104', '-7.981700', '111.512400', NULL, NULL, '17eca6ab05d2e2ecd398638cc004f2f9.jpg', 'dropcore 180m\r\nmodem gpon\r\npatchcord ', '', 'SEDANG', NULL, '2026-06-04 12:48:05'),
('spw6a1e834c', 'PE000102', 'RQ000105', '-7.9594251', '111.4945190', NULL, NULL, NULL, NULL, '', '', NULL, NULL),
('spw6a223d85', 'PE000103', 'RQ000106', '-7.978305', '111.493051', NULL, NULL, '0f6a97e42ef21193bfd0327d5b60e967.jpg', 'Dc -+250m\r\nPatchore 1pasang\r\nRuter\r\nNb:membutuhkan tiang 2', '', 'SULIT', NULL, '2026-06-05 03:07:49'),
('spw6a360fef', 'PE000104', 'RQ000107', '-7.990637', '111.500398', NULL, NULL, 'a13c45877ab8072a54cfd1b70a935490.jpg', 'Sudah ready \r\nKabel dc 150m \r\nModem fiberhome \r\nPatchore 2 \r\nNgambil odp wra 34', '', 'SEDANG', NULL, '2026-06-20 03:58:39'),
('spw6a43bd5e', 'PE000105', 'RQ000108', '-7.990554', '111.500012', NULL, NULL, 'fe6823dceabeb9068f4f281cece326c9.jpg', 'Dc 70m\r\nPatchore \r\nRuter gpon', '', 'MUDAH', NULL, '2026-06-30 12:58:06'),
('spw6a3df234', 'PE000106', 'RQ000109', '-7.958043', '111.493036', NULL, NULL, '39668b2a245d12e1b193cca78d64cc55.jpg', 'dropcore 75m\r\npetcore\r\ngpon', '', 'SEDANG', NULL, '2026-06-26 03:29:56'),
('spw6a6ea2b3', 'PE000107', 'RQ000110', '-7.960065', '111.498827', NULL, NULL, '4893f00169dedfab69a6c8c0c00aa164.jpg', 'dropcore 150+-\r\npetcore\r\ndualben', '', 'SEDANG', NULL, '2026-08-02 01:51:47'),
('spw6a7eceea', 'PE000108', 'RQ000111', '-7.987583', '111.492347', NULL, NULL, '5c4fdf3687413789ae8d352f5bbb83ef.jpg', 'dropcore 170\r\npetcore\r\ngpon', '', 'SEDANG', NULL, '2026-08-14 08:16:42'),
('spw6a9a6386', 'PE000109', 'RQ000112', '-8.000193', '111.494816', NULL, NULL, NULL, NULL, '', '', NULL, NULL),
('spw6aa23c95', 'PE000110', 'RQ000113', '-7.985421', '111.492930', NULL, NULL, '29b06148598be947738ddffcef8aa4d9.jpeg', 'Router xpon. Dc 120m. Pathcord 2', 'Router xpon', 'SEDANG', NULL, '2026-09-11 15:59:30'),
('spw6ae59627', 'PE000111', 'RQ000114', '-7.962994', '111.521807', NULL, NULL, NULL, NULL, '', '', NULL, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `tampilan`
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
-- Dumping data untuk tabel `tampilan`
--

INSERT INTO `tampilan` (`IDTAMPILAN`, `NAMA`, `ALT`, `IMG`, `SETUP`, `TGL_UPDATE`) VALUES
(1, 'Logo Aplikasi', 'logo-aplikasi', 'Koala.jpg', '', '2021-07-02 01:44:51'),
(2, 'Faficon Aplikasi', 'faficon-aplikasi', 'Tulips.jpg', '', '2021-07-02 01:44:22'),
(3, 'Logo Invoice', 'logo-invoice', 'Chrysanthemum.jpg', '', '2021-07-02 01:42:15'),
(4, 'Logo Email', 'logo-email', '6f1eab21e2cd35941b4957b20186e918.jpeg', '', '2021-09-03 23:59:31'),
(5, 'Setting Wa - Template Pesan', 'wa_list', 'null', '', '2021-08-31 14:52:40'),
(6, 'Setting Url Review', 'url_review', 'null', 'http://review.whusnet.com', '2021-09-24 02:11:29'),
(7, 'Setting Notification ke Channel', 'setting_notification_ke_channel', 'null', '-', '2021-09-24 02:11:29');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tb_alamat`
--

CREATE TABLE `tb_alamat` (
  `IDWILAYAH` varchar(15) NOT NULL,
  `KOTA` varchar(50) NOT NULL,
  `KEC` varchar(50) NOT NULL,
  `DESA` varchar(50) NOT NULL,
  `CONCAT` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `tb_alamat`
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
-- Struktur dari tabel `tb_history_process_finish_installation`
--

CREATE TABLE `tb_history_process_finish_installation` (
  `IDUNIQ` varchar(12) NOT NULL,
  `IDREQUEST` varchar(11) NOT NULL,
  `NOTE` text NOT NULL,
  `INSERTED_AT` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `CODENOTIF` varchar(50) NOT NULL,
  `STATUSNOTIF` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tb_history_process_not_active`
--

CREATE TABLE `tb_history_process_not_active` (
  `IDUNIQ` varchar(12) NOT NULL,
  `IDREQUEST` varchar(11) NOT NULL,
  `NOTE` text NOT NULL,
  `INSERTED_AT` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `CODENOTIF` varchar(50) NOT NULL,
  `STATUSNOTIF` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tb_history_process_start_installation`
--

CREATE TABLE `tb_history_process_start_installation` (
  `IDUNIQ` varchar(12) NOT NULL,
  `IDREQUEST` varchar(11) NOT NULL,
  `NOTE` text NOT NULL,
  `INSERTED_AT` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `CODENOTIF` varchar(50) NOT NULL,
  `STATUSNOTIF` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data untuk tabel `tb_history_process_start_installation`
--

INSERT INTO `tb_history_process_start_installation` (`IDUNIQ`, `IDREQUEST`, `NOTE`, `INSERTED_AT`, `CODENOTIF`, `STATUSNOTIF`) VALUES
('his675812d2e', 'RQ000037', 'PROSES PEMASANGAN', '2024-12-10 12:05:01', '14', 1),
('his676bef82e', 'RQ000038', 'PROSES PEMASANGAN', '2024-12-25 13:05:02', '14', 1),
('his6796227c9', 'RQ000047', 'PROSES PEMASANGAN', '2025-01-26 13:05:02', '14', 1),
('his67b8886e2', 'RQ000048', 'PROSES PEMASANGAN', '2025-02-21 16:05:02', '14', 1),
('his67c6a064b', 'RQ000049', 'PROSES PEMASANGAN', '2025-03-04 08:05:02', '14', 1),
('his67e194333', 'RQ000050', 'PROSES PEMASANGAN', '2025-03-24 19:05:02', '14', 1),
('his67f71aba4', 'RQ000051', 'PROSES PEMASANGAN', '2025-04-10 03:05:02', '14', 1),
('his68077feeb', 'RQ000052', 'PROSES PEMASANGAN', '2025-04-22 13:05:02', '14', 1),
('his68468fba8', 'RQ000053', 'PROSES PEMASANGAN', '2025-06-09 09:05:05', '14', 1),
('his6847f31e9', 'RQ000054', 'PROSES PEMASANGAN', '2025-06-10 10:05:05', '14', 1),
('his6847f9294', 'RQ000055', 'PROSES PEMASANGAN', '2025-06-10 11:05:05', '14', 1),
('his684ad699b', 'RQ000056', 'PROSES PEMASANGAN', '2025-06-12 15:05:05', '14', 1),
('his684adbadc', 'RQ000057', 'PROSES PEMASANGAN', '2025-06-12 15:05:06', '14', 1),
('his684b17f60', 'RQ000058', 'PROSES PEMASANGAN', '2025-06-12 20:05:13', '14', 1),
('his684b1954f', 'RQ000060', 'PROSES PEMASANGAN', '2025-06-12 20:05:11', '14', 1),
('his684b24ceb', 'RQ000061', 'PROSES PEMASANGAN', '2025-06-12 20:05:12', '14', 1),
('his684ba30f3', 'RQ000059', 'PROSES PEMASANGAN', '2025-06-13 05:05:02', '14', 1),
('his685840702', 'RQ000065', 'PROSES PEMASANGAN', '2025-06-22 19:05:06', '14', 1),
('his685c12d13', 'RQ000063', 'PROSES PEMASANGAN', '2025-06-25 17:05:04', '14', 1),
('his685c4f590', 'RQ000067', 'PROSES PEMASANGAN', '2025-06-25 21:05:05', '14', 1),
('his685f9ce64', 'RQ000071', 'PROSES PEMASANGAN', '2025-06-28 09:05:08', '14', 1),
('his685f9df33', 'RQ000072', 'PROSES PEMASANGAN', '2025-06-28 09:05:09', '14', 1),
('his6864a53c1', 'RQ000070', 'PROSES PEMASANGAN', '2025-07-02 05:05:02', '14', 1),
('his686b94d08', 'RQ000073', 'PROSES PEMASANGAN', '2025-07-07 11:05:05', '14', 1),
('his688af6c6e', 'RQ000076', 'PROSES PEMASANGAN', '2025-07-31 06:05:01', '14', 1),
('his688c0a785', 'RQ000074', 'PROSES PEMASANGAN', '2025-08-01 02:05:02', '14', 1),
('his6899cc20c', 'RQ000077', 'PROSES PEMASANGAN', '2025-08-11 12:05:05', '14', 1),
('his68e71d72d', 'RQ000078', 'PROSES PEMASANGAN', '2025-10-09 04:05:02', '14', 1),
('his68febf469', 'RQ000081', 'PROSES PEMASANGAN', '2025-10-27 02:05:02', '14', 1),
('his690ef2742', 'RQ000082', 'PROSES PEMASANGAN', '2025-11-08 09:05:02', '14', 1),
('his6954e9406', 'RQ000087', 'PROSES PEMASANGAN', '2025-12-31 11:05:02', '14', 1),
('his6985e69c7', 'RQ000088', 'PROSES PEMASANGAN', '2026-02-06 14:05:02', '14', 1),
('his69e9b23d6', 'RQ000091', 'PROSES PEMASANGAN', '2026-04-23 07:05:01', '14', 1),
('his69f2c53bd', 'RQ000092', 'PROSES PEMASANGAN', '2026-04-30 04:05:03', '14', 1),
('his69fc14d62', 'RQ000094', 'PROSES PEMASANGAN', '2026-05-07 06:05:02', '14', 1),
('his69fc44ae0', 'RQ000095', 'PROSES PEMASANGAN', '2026-05-07 09:05:02', '14', 1),
('his69febe4a4', 'RQ000096', 'PROSES PEMASANGAN', '2026-05-09 06:05:01', '14', 1),
('his6a0c367ab', 'RQ000098', 'PROSES PEMASANGAN', '2026-05-19 12:05:01', '14', 1),
('his6a17ebd11', 'RQ000097', 'PROSES PEMASANGAN', '2026-05-28 09:05:02', '14', 1),
('his6a1820024', 'RQ000099', 'PROSES PEMASANGAN', '2026-05-28 12:05:02', '14', 1),
('his6a1ff1dec', 'RQ000103', 'PROSES PEMASANGAN', '2026-06-03 11:05:01', '14', 1),
('his6a1ff25b7', 'RQ000100', 'PROSES PEMASANGAN', '2026-06-03 11:05:02', '14', 1),
('his6a1ffe1f3', 'RQ000102', 'PROSES PEMASANGAN', '2026-06-03 12:05:01', '14', 1),
('his6a2955f0d', 'RQ000104', 'PROSES PEMASANGAN', '2026-06-10 14:05:01', '14', 1),
('his6a365b61b', 'RQ000107', 'PROSES PEMASANGAN', '2026-06-20 11:05:01', '14', 1),
('his6a411e11d', 'RQ000109', 'PROSES PEMASANGAN', '2026-06-28 15:05:01', '14', 1),
('his6a44d6178', 'RQ000108', 'PROSES PEMASANGAN', '2026-07-01 10:05:02', '14', 1),
('his6a71e9dd5', 'RQ000110', 'PROSES PEMASANGAN', '2026-08-04 15:05:01', '14', 1),
('his6a7edb7db', 'RQ000111', 'PROSES PEMASANGAN', '2026-08-14 11:05:02', '14', 1),
('his6aa424cd0', 'RQ000113', 'PROSES PEMASANGAN', '2026-09-11 17:05:02', '14', 1);

-- --------------------------------------------------------

--
-- Struktur dari tabel `tb_history_process_survey`
--

CREATE TABLE `tb_history_process_survey` (
  `IDUNIQ` varchar(12) NOT NULL,
  `IDREQUEST` varchar(11) NOT NULL,
  `NOTE` text NOT NULL,
  `INSERTED_AT` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `CODENOTIF` varchar(50) NOT NULL,
  `STATUSNOTIF` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data untuk tabel `tb_history_process_survey`
--

INSERT INTO `tb_history_process_survey` (`IDUNIQ`, `IDREQUEST`, `NOTE`, `INSERTED_AT`, `CODENOTIF`, `STATUSNOTIF`) VALUES
('his671880494', 'RQ000034', 'TEST - PROSES SURVEY', '2024-10-23 04:51:30', '14', 1),
('his671ef288b', 'RQ000033', 'PROSES SURVEY', '2024-10-28 03:00:02', '14', 1),
('his6730c4a49', 'RQ000035', 'PROSES SURVEY', '2024-11-10 15:00:02', '14', 1),
('his674f07118', 'RQ000036', 'PROSES SURVEY', '2024-12-03 14:00:02', '14', 1),
('his6757c3ee5', 'RQ000037', 'PROSES SURVEY', '2024-12-10 06:05:01', '14', 1),
('his676baa8dd', 'RQ000038', 'PROSES SURVEY', '2024-12-25 08:05:02', '14', 1),
('his677c89543', 'RQ000046', 'PROSES SURVEY', '2025-01-07 03:05:01', '14', 1),
('his67935e081', 'RQ000047', 'PROSES SURVEY', '2025-01-24 11:05:02', '14', 1),
('his67b6b3edd', 'RQ000048', 'PROSES SURVEY', '2025-02-20 06:05:02', '14', 1),
('his67c578a32', 'RQ000049', 'PROSES SURVEY', '2025-03-03 11:05:02', '14', 1),
('his67dcca147', 'RQ000050', 'PROSES SURVEY', '2025-03-21 04:05:01', '14', 1),
('his67f494338', 'RQ000051', 'PROSES SURVEY', '2025-04-08 05:05:02', '14', 1),
('his67fdd5603', 'RQ000052', 'PROSES SURVEY', '2025-04-15 05:05:01', '14', 1),
('his68468c9f9', 'RQ000053', 'PROSES SURVEY', '2025-06-09 09:05:02', '14', 1),
('his6847f1e71', 'RQ000054', 'PROSES SURVEY', '2025-06-10 10:05:03', '14', 1),
('his6847f85c3', 'RQ000055', 'PROSES SURVEY', '2025-06-10 11:05:03', '14', 1),
('his684a53494', 'RQ000056', 'PROSES SURVEY', '2025-06-12 06:05:04', '14', 1),
('his684ad8a3b', 'RQ000057', 'PROSES SURVEY', '2025-06-12 15:05:02', '14', 1),
('his684afae81', 'RQ000058', 'PROSES SURVEY', '2025-06-12 18:05:01', '14', 1),
('his684b16ef8', 'RQ000059', 'PROSES SURVEY', '2025-06-12 20:05:07', '14', 1),
('his684b190da', 'RQ000060', 'PROSES SURVEY', '2025-06-12 20:05:05', '14', 1),
('his684b248d7', 'RQ000061', 'PROSES SURVEY', '2025-06-12 20:05:06', '14', 1),
('his684bace62', 'RQ000062', 'PROSES SURVEY', '2025-06-13 06:05:04', '14', 1),
('his684ffc06a', 'RQ000063', 'PROSES SURVEY', '2025-06-16 13:05:02', '14', 1),
('his6858403cb', 'RQ000065', 'PROSES SURVEY', '2025-06-22 19:05:03', '14', 1),
('his685c12ab9', 'RQ000064', 'PROSES SURVEY', '2025-06-25 17:05:02', '14', 1),
('his685c28317', 'RQ000067', 'PROSES SURVEY', '2025-06-25 18:05:03', '14', 1),
('his685c4da0d', 'RQ000068', 'PROSES SURVEY', '2025-06-25 21:05:03', '14', 1),
('his685f9cbde', 'RQ000071', 'PROSES SURVEY', '2025-06-28 09:05:04', '14', 1),
('his685f9dc45', 'RQ000072', 'PROSES SURVEY', '2025-06-28 09:05:05', '14', 1),
('his68649d7ac', 'RQ000070', 'PROSES SURVEY', '2025-07-02 04:05:02', '14', 1),
('his686b8ec58', 'RQ000073', 'PROSES SURVEY', '2025-07-07 11:05:02', '14', 1),
('his687f2a643', 'RQ000074', 'PROSES SURVEY', '2025-07-22 08:05:02', '14', 1),
('his6888983f2', 'RQ000076', 'PROSES SURVEY', '2025-07-29 11:05:02', '14', 1),
('his6899ca301', 'RQ000077', 'PROSES SURVEY', '2025-08-11 12:05:02', '14', 1),
('his68e6065fb', 'RQ000079', 'PROSES SURVEY', '2025-10-08 08:05:03', '14', 1),
('his68e606be6', 'RQ000078', 'PROSES SURVEY', '2025-10-08 08:05:02', '14', 1),
('his68fc2af20', 'RQ000081', 'PROSES SURVEY', '2025-10-25 03:05:01', '14', 1),
('his690c4caf4', 'RQ000082', 'PROSES SURVEY', '2025-11-06 09:05:02', '14', 1),
('his6951e144e', 'RQ000087', 'PROSES SURVEY', '2025-12-29 03:05:02', '14', 1),
('his6954d6603', 'RQ000086', 'PROSES SURVEY', '2025-12-31 09:05:01', '14', 1),
('his6985696d2', 'RQ000088', 'PROSES SURVEY', '2026-02-06 06:05:01', '14', 1),
('his69e9944d2', 'RQ000091', 'PROSES SURVEY', '2026-04-23 05:05:01', '14', 1),
('his69f19065b', 'RQ000092', 'PROSES SURVEY', '2026-04-29 06:05:02', '14', 1),
('his69f2ec368', 'RQ000093', 'PROSES SURVEY', '2026-04-30 07:05:01', '14', 1),
('his69f85a088', 'RQ000094', 'PROSES SURVEY', '2026-05-04 10:05:01', '14', 1),
('his69fbf2d1d', 'RQ000095', 'PROSES SURVEY', '2026-05-07 03:05:01', '14', 1),
('his69fc5ba16', 'RQ000096', 'PROSES SURVEY', '2026-05-07 11:05:02', '14', 1),
('his6a06c3935', 'RQ000097', 'PROSES SURVEY', '2026-05-15 08:05:02', '14', 1),
('his6a06c3c7b', 'RQ000098', 'PROSES SURVEY', '2026-05-15 08:05:03', '14', 1),
('his6a118e3d5', 'RQ000099', 'PROSES SURVEY', '2026-05-23 13:05:01', '14', 1),
('his6a118ead2', 'RQ000100', 'PROSES SURVEY', '2026-05-23 13:05:04', '14', 1),
('his6a1d3bb1b', 'RQ000103', 'PROSES SURVEY', '2026-06-01 09:05:02', '14', 1),
('his6a1d556d0', 'RQ000102', 'PROSES SURVEY', '2026-06-01 11:05:02', '14', 1),
('his6a2173e5b', 'RQ000104', 'PROSES SURVEY', '2026-06-04 14:05:01', '14', 1),
('his6a223d295', 'RQ000106', 'PROSES SURVEY', '2026-06-05 05:05:01', '14', 1),
('his6a360efc8', 'RQ000107', 'PROSES SURVEY', '2026-06-20 05:05:01', '14', 1),
('his6a3df20b1', 'RQ000109', 'PROSES SURVEY', '2026-06-26 05:05:02', '14', 1),
('his6a43bd344', 'RQ000108', 'PROSES SURVEY', '2026-06-30 14:05:01', '14', 1),
('his6a6ea2951', 'RQ000110', 'PROSES SURVEY', '2026-08-02 03:05:01', '14', 1),
('his6a7ececb5', 'RQ000111', 'PROSES SURVEY', '2026-08-14 10:05:02', '14', 1),
('his6aa23c70c', 'RQ000113', 'PROSES SURVEY', '2026-09-10 07:05:02', '14', 1);

-- --------------------------------------------------------

--
-- Struktur dari tabel `tb_history_process_team_installation`
--

CREATE TABLE `tb_history_process_team_installation` (
  `IDUNIQ` varchar(12) NOT NULL,
  `IDREQUEST` varchar(11) NOT NULL,
  `NOTE` text NOT NULL,
  `INSERTED_AT` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `CODENOTIF` varchar(50) NOT NULL,
  `STATUSNOTIF` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tb_history_process_verification`
--

CREATE TABLE `tb_history_process_verification` (
  `IDUNIQ` varchar(12) NOT NULL,
  `IDREQUEST` varchar(11) NOT NULL,
  `NOTE` text NOT NULL,
  `INSERTED_AT` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `CODENOTIF` varchar(50) NOT NULL,
  `STATUSNOTIF` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tb_history_registrations`
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
-- Dumping data untuk tabel `tb_history_registrations`
--

INSERT INTO `tb_history_registrations` (`IDUNIQ`, `IDREQUEST`, `NOTE`, `INSERTED_AT`, `CODENOTIF`, `STATUSNOTIF`) VALUES
('his66f78f855', 'RQ000031', 'PENGAJUAN PELANGGAN', '2024-09-28 05:09:25', '12', 1),
('his66fe549a6', 'RQ000032', 'PENGAJUAN PELANGGAN', '2024-10-03 08:23:54', '12', 1),
('his670c730c8', 'RQ000033', 'PENGAJUAN PELANGGAN', '2024-10-14 01:25:32', '12', 1),
('his671880079', 'RQ000034', 'TEST - PENGAJUAN PELANGGAN', '2024-10-23 04:48:07', '13', 1),
('his6725852db', 'RQ000035', 'PENGAJUAN PELANGGAN', '2024-11-02 01:49:33', '13', 1),
('his674989186', 'RQ000036', 'PENGAJUAN PELANGGAN', '2024-11-29 09:27:52', '13', 1),
('his675000f08', 'RQ000037', 'PENGAJUAN PELANGGAN', '2024-12-04 07:12:48', '13', 1),
('his675413480', 'RQ000038', 'PENGAJUAN PELANGGAN', '2024-12-07 09:20:08', '13', 1),
('his6778fc1cd', 'RQ000046', 'PENGAJUAN PELANGGAN', '2025-01-04 09:15:08', '13', 1),
('his6791a241d', 'RQ000047', 'PENGAJUAN PELANGGAN', '2025-01-23 01:58:25', '13', 1),
('his67ad582bd', 'RQ000048', 'PENGAJUAN PELANGGAN', '2025-02-13 02:25:47', '13', 1),
('his67bc5b53d', 'RQ000049', 'PENGAJUAN PELANGGAN', '2025-02-24 11:43:15', '13', 1),
('his67dbc7252', 'RQ000050', 'PENGAJUAN PELANGGAN', '2025-03-20 07:43:33', '13', 1),
('his67ecec464', 'RQ000051', 'PENGAJUAN PELANGGAN', '2025-04-02 07:50:30', '13', 1),
('his67f226081', 'RQ000052', 'PENGAJUAN PELANGGAN', '2025-04-06 06:58:16', '13', 1),
('his6845518d2', 'RQ000053', 'PENGAJUAN PELANGGAN', '2025-06-08 09:02:05', '13', 1),
('his6847f10e4', 'RQ000054', 'PENGAJUAN PELANGGAN', '2025-06-10 08:47:10', '13', 1),
('his6847f7d56', 'RQ000055', 'PENGAJUAN PELANGGAN', '2025-06-10 09:16:05', '13', 1),
('his684a52d03', 'RQ000056', 'PENGAJUAN PELANGGAN', '2025-06-12 04:08:48', '13', 1),
('his684a53768', 'RQ000057', 'PENGAJUAN PELANGGAN', '2025-06-12 04:11:34', '13', 1),
('his684afa8b9', 'RQ000058', 'PENGAJUAN PELANGGAN', '2025-06-12 16:04:27', '13', 1),
('his684b16e6e', 'RQ000059', 'PENGAJUAN PELANGGAN', '2025-06-12 18:05:26', '13', 1),
('his684b18eb5', 'RQ000060', 'PENGAJUAN PELANGGAN', '2025-06-12 18:14:03', '13', 1),
('his684b24702', 'RQ000061', 'PENGAJUAN PELANGGAN', '2025-06-12 19:03:12', '13', 1),
('his684bacde5', 'RQ000062', 'PENGAJUAN PELANGGAN', '2025-06-13 04:45:18', '13', 1),
('his684baef1b', 'RQ000063', 'PENGAJUAN PELANGGAN', '2025-06-13 04:54:09', '13', 1),
('his6852bb1cd', 'RQ000064', 'PENGAJUAN PELANGGAN', '2025-06-18 13:11:56', '13', 1),
('his68583ffac', 'RQ000065', 'PENGAJUAN PELANGGAN', '2025-06-22 17:40:10', '13', 1),
('his685bfd448', 'RQ000066', 'PENGAJUAN PELANGGAN', '2025-06-25 13:44:36', '13', 1),
('his685c281c3', 'RQ000067', 'PENGAJUAN PELANGGAN', '2025-06-25 16:47:24', '13', 1),
('his685c3f793', 'RQ000068', 'PENGAJUAN PELANGGAN', '2025-06-25 18:27:05', '13', 1),
('his685c49bfa', 'RQ000069', 'PENGAJUAN PELANGGAN', '2025-06-25 19:10:55', '13', 1),
('his685e144fa', 'RQ000070', 'PENGAJUAN PELANGGAN', '2025-06-27 03:47:27', '13', 1),
('his685f9cb5b', 'RQ000071', 'PENGAJUAN PELANGGAN', '2025-06-28 07:41:41', '13', 1),
('his685f9db4a', 'RQ000072', 'PENGAJUAN PELANGGAN', '2025-06-28 07:45:56', '13', 1),
('his68677b560', 'RQ000073', 'PENGAJUAN PELANGGAN', '2025-07-04 06:57:26', '13', 1),
('his6879bb3d3', 'RQ000074', 'PENGAJUAN PELANGGAN', '2025-07-18 03:10:53', '13', 1),
('his6881dd749', 'RQ000075', 'PENGAJUAN PELANGGAN', '2025-07-24 07:15:00', '13', 1),
('his6886e075c', 'RQ000076', 'PENGAJUAN PELANGGAN', '2025-07-28 02:29:09', '13', 1),
('his68944bf81', 'RQ000077', 'PENGAJUAN PELANGGAN', '2025-08-07 06:47:20', '13', 1),
('his68e319046', 'RQ000078', 'PENGAJUAN PELANGGAN', '2025-10-06 01:19:00', '13', 1),
('his68e47ac81', 'RQ000079', 'PENGAJUAN PELANGGAN', '2025-10-07 02:28:24', '13', 1),
('his68e4d0f07', 'RQ000080', 'PENGAJUAN PELANGGAN', '2025-10-07 08:36:00', '13', 1),
('his68f5afc12', 'RQ000081', 'PENGAJUAN PELANGGAN', '2025-10-20 03:42:57', '13', 1),
('his690456a55', 'RQ000082', 'PENGAJUAN PELANGGAN', '2025-10-31 06:26:45', '13', 1),
('his690972010', 'RQ000083', 'PENGAJUAN PELANGGAN', '2025-11-04 03:24:49', '13', 1),
('his693d0c6dd', 'RQ000084', 'PENGAJUAN PELANGGAN', '2025-12-13 06:49:17', '13', 1),
('his6944ad5dc', 'RQ000085', 'PENGAJUAN PELANGGAN', '2025-12-19 01:41:49', '13', 1),
('his6948f0d3c', 'RQ000086', 'PENGAJUAN PELANGGAN', '2025-12-22 07:18:43', '13', 1),
('his694f42f87', 'RQ000087', 'PENGAJUAN PELANGGAN', '2025-12-27 02:22:48', '13', 1),
('his6980291c4', 'RQ000088', 'PENGAJUAN PELANGGAN', '2026-02-02 04:33:32', '13', 1),
('his69cb0b9a2', 'RQ000089', 'PENGAJUAN PELANGGAN', '2026-03-30 23:47:38', '13', 1),
('his69d741f88', 'RQ000090', 'PENGAJUAN PELANGGAN', '2026-04-09 06:06:48', '13', 1),
('his69e58913d', 'RQ000091', 'PENGAJUAN PELANGGAN', '2026-04-20 02:01:55', '13', 1),
('his69f0324cd', 'RQ000092', 'PENGAJUAN PELANGGAN', '2026-04-28 04:06:36', '13', 1),
('his69f051a59', 'RQ000093', 'PENGAJUAN PELANGGAN', '2026-04-28 06:20:21', '13', 1),
('his69f57562d', 'RQ000094', 'PENGAJUAN PELANGGAN', '2026-05-02 03:54:10', '13', 1),
('his69f9746b7', 'RQ000095', 'PENGAJUAN PELANGGAN', '2026-05-05 04:39:07', '13', 1),
('his69fc06ee4', 'RQ000096', 'PENGAJUAN PELANGGAN', '2026-05-07 03:28:46', '13', 1),
('his6a015a11a', 'RQ000097', 'PENGAJUAN PELANGGAN', '2026-05-11 04:24:49', '13', 1),
('his6a066cd82', 'RQ000098', 'PENGAJUAN PELANGGAN', '2026-05-15 00:46:16', '13', 1),
('his6a0a702bf', 'RQ000099', 'PENGAJUAN PELANGGAN', '2026-05-18 01:49:31', '13', 1),
('his6a0c0f8ad', 'RQ000100', 'PENGAJUAN PELANGGAN', '2026-05-19 07:21:46', '13', 1),
('his6a13ba3cc', 'RQ000101', 'PENGAJUAN PELANGGAN', '2026-05-25 02:55:56', '13', 1),
('his6a1aa137c', 'RQ000102', 'PENGAJUAN PELANGGAN', '2026-05-30 08:35:03', '13', 1),
('his6a1cdb0c8', 'RQ000103', 'PENGAJUAN PELANGGAN', '2026-06-01 01:06:20', '13', 1),
('his6a1e21de2', 'RQ000104', 'PENGAJUAN PELANGGAN', '2026-06-02 00:20:46', '13', 1),
('his6a1e834ca', 'RQ000105', 'PENGAJUAN PELANGGAN', '2026-06-02 07:16:28', '13', 1),
('his6a20e2fa2', 'RQ000106', 'PENGAJUAN PELANGGAN', '2026-06-04 02:29:14', '13', 1),
('his6a3262339', 'RQ000107', 'PENGAJUAN PELANGGAN', '2026-06-17 09:00:35', '13', 1),
('his6a39eeb71', 'RQ000108', 'PENGAJUAN PELANGGAN', '2026-06-23 02:25:59', '13', 1),
('his6a3ca11e3', 'RQ000109', 'PENGAJUAN PELANGGAN', '2026-06-25 03:31:42', '13', 1),
('his6a6c54d9a', 'RQ000110', 'PENGAJUAN PELANGGAN', '2026-07-31 07:55:05', '13', 1),
('his6a7ebdede', 'RQ000111', 'PENGAJUAN PELANGGAN', '2026-08-14 07:04:13', '13', 1),
('his6a9a63868', 'RQ000112', 'PENGAJUAN PELANGGAN', '2026-09-04 06:21:58', '13', 1),
('his6a9e0edd2', 'RQ000113', 'PENGAJUAN PELANGGAN', '2026-09-07 01:09:49', '13', 1),
('his6ae596276', 'RQ000114', 'PENGAJUAN PELANGGAN', '2026-10-31 07:17:59', '13', 1);

-- --------------------------------------------------------

--
-- Struktur dari tabel `tb_history_report_survey`
--

CREATE TABLE `tb_history_report_survey` (
  `IDUNIQ` varchar(12) NOT NULL,
  `IDREQUEST` varchar(11) NOT NULL,
  `NOTE` text NOT NULL,
  `INSERTED_AT` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `CODENOTIF` varchar(50) NOT NULL,
  `STATUSNOTIF` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data untuk tabel `tb_history_report_survey`
--

INSERT INTO `tb_history_report_survey` (`IDUNIQ`, `IDREQUEST`, `NOTE`, `INSERTED_AT`, `CODENOTIF`, `STATUSNOTIF`) VALUES
('his671880a1d', 'RQ000034', 'TEST - REPORT SURVEY', '2024-10-23 04:51:31', '15', 1),
('his671ef2d25', 'RQ000033', 'REPORT SURVEY', '2024-10-28 03:00:04', '15', 1),
('his6730c8019', 'RQ000035', 'REPORT SURVEY', '2024-11-10 15:00:03', '15', 1),
('his674f0bda0', 'RQ000036', 'REPORT SURVEY', '2024-12-03 14:00:03', '15', 1),
('his6757c42a6', 'RQ000037', 'REPORT SURVEY', '2024-12-10 06:05:02', '15', 1),
('his676bb0225', 'RQ000038', 'REPORT SURVEY', '2024-12-25 09:05:02', '15', 1),
('his678b32fc6', 'RQ000046', 'REPORT SURVEY', '2025-01-18 06:05:01', '15', 1),
('his67935e74c', 'RQ000047', 'REPORT SURVEY', '2025-01-24 11:05:04', '15', 1),
('his67b6b5723', 'RQ000048', 'REPORT SURVEY', '2025-02-20 06:05:03', '15', 1),
('his67c592e78', 'RQ000049', 'REPORT SURVEY', '2025-03-03 13:05:02', '15', 1),
('his67de05492', 'RQ000050', 'REPORT SURVEY', '2025-03-22 02:05:02', '15', 1),
('his67f494988', 'RQ000051', 'REPORT SURVEY', '2025-04-08 05:05:03', '15', 1),
('his67fdd5a0b', 'RQ000052', 'REPORT SURVEY', '2025-04-15 05:05:03', '15', 1),
('his68468e539', 'RQ000053', 'REPORT SURVEY', '2025-06-09 09:05:03', '15', 1),
('his6847f26f0', 'RQ000054', 'REPORT SURVEY', '2025-06-10 10:05:04', '15', 1),
('his6847f88d2', 'RQ000055', 'REPORT SURVEY', '2025-06-10 11:05:04', '15', 1),
('his684ad4f61', 'RQ000056', 'REPORT SURVEY', '2025-06-12 15:05:03', '15', 1),
('his684adb93e', 'RQ000057', 'REPORT SURVEY', '2025-06-12 15:05:04', '15', 1),
('his684b14cf1', 'RQ000058', 'REPORT SURVEY', '2025-06-12 19:05:02', '15', 1),
('his684b1703d', 'RQ000059', 'REPORT SURVEY', '2025-06-12 20:05:10', '15', 1),
('his684b19346', 'RQ000060', 'REPORT SURVEY', '2025-06-12 20:05:08', '15', 1),
('his684b24ad1', 'RQ000061', 'REPORT SURVEY', '2025-06-12 20:05:09', '15', 1),
('his684bacfba', 'RQ000062', 'REPORT SURVEY', '2025-06-13 06:05:05', '15', 1),
('his685840275', 'RQ000063', 'REPORT SURVEY', '2025-06-22 19:05:05', '15', 1),
('his6858404fd', 'RQ000065', 'REPORT SURVEY', '2025-06-22 19:05:04', '15', 1),
('his685c12c42', 'RQ000064', 'REPORT SURVEY', '2025-06-25 17:05:03', '15', 1),
('his685c4ed65', 'RQ000067', 'REPORT SURVEY', '2025-06-25 21:05:04', '15', 1),
('his685f9cd55', 'RQ000071', 'REPORT SURVEY', '2025-06-28 09:05:06', '15', 1),
('his685f9de52', 'RQ000072', 'REPORT SURVEY', '2025-06-28 09:05:07', '15', 1),
('his68649eb9e', 'RQ000070', 'REPORT SURVEY', '2025-07-02 04:05:03', '15', 1),
('his686b8f5ee', 'RQ000073', 'REPORT SURVEY', '2025-07-07 11:05:03', '15', 1),
('his68802b541', 'RQ000074', 'REPORT SURVEY', '2025-07-23 02:05:02', '15', 1),
('his68889920c', 'RQ000076', 'REPORT SURVEY', '2025-07-29 11:05:03', '15', 1),
('his688f93adb', 'RQ000068', 'REPORT SURVEY', '2025-08-03 19:05:02', '15', 1),
('his6899ca62b', 'RQ000077', 'REPORT SURVEY', '2025-08-11 12:05:03', '15', 1),
('his68e6068b7', 'RQ000079', 'REPORT SURVEY', '2025-10-08 08:05:06', '15', 1),
('his68e606d83', 'RQ000078', 'REPORT SURVEY', '2025-10-08 08:05:05', '15', 1),
('his68fe1e439', 'RQ000081', 'REPORT SURVEY', '2025-10-26 15:05:01', '15', 1),
('his690c5dec5', 'RQ000082', 'REPORT SURVEY', '2025-11-06 10:05:02', '15', 1),
('his6951e16aa', 'RQ000087', 'REPORT SURVEY', '2025-12-29 03:05:03', '15', 1),
('his6954d7336', 'RQ000086', 'REPORT SURVEY', '2025-12-31 09:05:02', '15', 1),
('his698569d06', 'RQ000088', 'REPORT SURVEY', '2026-02-06 06:05:03', '15', 1),
('his69e995d16', 'RQ000091', 'REPORT SURVEY', '2026-04-23 05:05:02', '15', 1),
('his69f2bd44d', 'RQ000092', 'REPORT SURVEY', '2026-04-30 04:05:02', '15', 1),
('his69f2ec8d5', 'RQ000093', 'REPORT SURVEY', '2026-04-30 07:05:02', '15', 1),
('his69f85a35d', 'RQ000094', 'REPORT SURVEY', '2026-05-04 10:05:02', '15', 1),
('his69fc07c20', 'RQ000095', 'REPORT SURVEY', '2026-05-07 05:05:02', '15', 1),
('his69fc5c3ca', 'RQ000096', 'REPORT SURVEY', '2026-05-07 11:05:03', '15', 1),
('his6a06c3c15', 'RQ000097', 'REPORT SURVEY', '2026-05-15 08:05:04', '15', 1),
('his6a06c3e98', 'RQ000098', 'REPORT SURVEY', '2026-05-15 08:05:06', '15', 1),
('his6a118e5c4', 'RQ000099', 'REPORT SURVEY', '2026-05-23 13:05:05', '15', 1),
('his6a118ed51', 'RQ000100', 'REPORT SURVEY', '2026-05-23 13:05:07', '15', 1),
('his6a1d3c3d4', 'RQ000103', 'REPORT SURVEY', '2026-06-01 09:05:04', '15', 1),
('his6a1d559a5', 'RQ000102', 'REPORT SURVEY', '2026-06-01 11:05:03', '15', 1),
('his6a217405b', 'RQ000104', 'REPORT SURVEY', '2026-06-04 14:05:02', '15', 1),
('his6a223d856', 'RQ000106', 'REPORT SURVEY', '2026-06-05 05:05:02', '15', 1),
('his6a360ff07', 'RQ000107', 'REPORT SURVEY', '2026-06-20 05:05:02', '15', 1),
('his6a3df234e', 'RQ000109', 'REPORT SURVEY', '2026-06-26 05:05:03', '15', 1),
('his6a43bd5f1', 'RQ000108', 'REPORT SURVEY', '2026-06-30 14:05:02', '15', 1),
('his6a6ea2b43', 'RQ000110', 'REPORT SURVEY', '2026-08-02 03:05:03', '15', 1),
('his6a7eceeb7', 'RQ000111', 'REPORT SURVEY', '2026-08-14 10:05:03', '15', 1),
('his6aa23c964', 'RQ000113', 'REPORT SURVEY', '2026-09-10 07:05:03', '15', 1);

-- --------------------------------------------------------

--
-- Struktur dari tabel `tb_jenismember`
--

CREATE TABLE `tb_jenismember` (
  `idmonth` int(2) NOT NULL,
  `name` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `tb_jenismember`
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
-- Struktur dari tabel `tb_log`
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
-- Struktur dari tabel `tb_menu`
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
-- Dumping data untuk tabel `tb_menu`
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
(36, 7, 0, 'Setting Umum', '', 'settings/Controll_SettingTampilan', '', '', 'data-setting-umum', 2),
(37, 0, 0, 'GANGGUAN', 'bx bxs-magic-wand', '#', '', '', '', 6),
(38, 37, 0, 'Tambah Tiket', '', 'gangguan/Controll_tambahtiket', '', '', 'Data-tambah-tiket', 1),
(39, 37, 0, 'Tiket Masuk', '', 'gangguan/Controll_tiketmasuk', '', '', 'data-tiket-masuk', 2),
(40, 37, 0, 'Tiket Proses', '', 'gangguan/Controll_tiketproses', '', '', 'data-tiket-proses', 3),
(41, 37, 0, 'Riwayat Selesai', '', 'gangguan/Controll_riwayat_tiketselesai', '', '', 'data-riwayat-tiket-selesai', 4),
(42, 37, 0, 'Riwayat Gagal', '', 'gangguan/Controll_riwayat_tiketgagal', '', '', 'data-riwayat-tiket-gagal', 5),
(43, 2, 0, 'Master Kota', '', 'master/Controll_wilayah', '', '', 'data-wilayah', 6);

-- --------------------------------------------------------

--
-- Struktur dari tabel `tb_menujabatan`
--

CREATE TABLE `tb_menujabatan` (
  `JABATANID` int(11) NOT NULL,
  `menuid` int(11) NOT NULL,
  `status` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `tb_menujabatan`
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
(1, 14, 'YES'),
(1, 13, 'YES'),
(1, 12, 'YES'),
(1, 11, 'YES'),
(1, 24, 'YES'),
(1, 22, 'YES'),
(1, 17, 'YES'),
(1, 19, 'NO'),
(1, 18, 'YES'),
(1, 23, 'YES'),
(1, 2, 'YES'),
(1, 16, 'YES'),
(1, 15, 'YES'),
(1, 35, 'YES'),
(1, 34, 'YES'),
(1, 31, 'YES'),
(1, 6, 'YES'),
(1, 5, 'YES'),
(1, 4, 'YES'),
(1, 43, 'YES'),
(1, 20, 'YES'),
(1, 25, 'YES'),
(1, 28, 'YES'),
(1, 27, 'YES'),
(1, 26, 'YES'),
(1, 37, 'YES'),
(1, 40, 'YES'),
(1, 39, 'YES'),
(1, 38, 'YES'),
(1, 42, 'YES'),
(1, 41, 'YES'),
(1, 7, 'YES'),
(1, 36, 'YES'),
(1, 29, 'YES'),
(1, 21, 'YES'),
(5, 10, 'YES'),
(5, 24, 'YES'),
(5, 22, 'YES'),
(5, 14, 'YES'),
(5, 13, 'YES'),
(5, 12, 'YES'),
(5, 11, 'YES'),
(5, 17, 'NO'),
(5, 23, 'NO'),
(5, 19, 'NO'),
(5, 18, 'NO'),
(5, 2, 'NO'),
(5, 4, 'YES'),
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
(5, 36, 'YES'),
(5, 29, 'YES');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tb_nolimitcron`
--

CREATE TABLE `tb_nolimitcron` (
  `ID` int(1) NOT NULL COMMENT '1=update-data-tagihan-bulanan, 2=notifWA_tagihan-bulanan,3=update-data-tagihan-bulananmember, 4=notifWA_tagihan-bulananmember',
  `START` int(2) NOT NULL,
  `LENGHT` int(2) NOT NULL,
  `BULAN` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tb_promo`
--

CREATE TABLE `tb_promo` (
  `IDPROMO` varchar(20) NOT NULL,
  `TITLE` varchar(100) NOT NULL,
  `IMG` varchar(200) NOT NULL,
  `DESKRIPSI` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tb_setup_notif`
--

CREATE TABLE `tb_setup_notif` (
  `ID` int(4) NOT NULL,
  `SUBAPP` varchar(30) NOT NULL,
  `APPNAME` varchar(50) NOT NULL COMMENT 'telegram/whatsapp',
  `CHANNEL` varchar(100) NOT NULL COMMENT 'nama channel'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `tb_setup_notif`
--

INSERT INTO `tb_setup_notif` (`ID`, `SUBAPP`, `APPNAME`, `CHANNEL`) VALUES
(1, 'APLIKASI', 'telegram', '@WhusnetNotifTier01'),
(2, 'APLIKASI', 'telegram', '@WhusnetNotifTier02');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tb_tiernotif`
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
-- Dumping data untuk tabel `tb_tiernotif`
--

INSERT INTO `tb_tiernotif` (`ID`, `GROUPACCOUNTS`, `TITLE`, `APPNOTIF`, `DUETIME`, `NOTE`) VALUES
(11, 1, 'Notification App Gangguan 01', 1, '24', 'notif untuk gangguan - tier 1'),
(12, 1, 'Notification App Gangguan 02', 2, '24', 'notif untuk gangguan - tier 2'),
(13, 2, 'Notif Registration Tele', 2, '0', 'Notif info Mulai Registration'),
(14, 3, 'Notification Mulai Proses 02', 2, '0', 'Notif info Mulai Proses'),
(15, 0, 'Notification Telat Proses 01', 1, '27', 'Notif info telat Proses');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tb_urut`
--

CREATE TABLE `tb_urut` (
  `idurut` int(11) NOT NULL,
  `namaForm` text NOT NULL,
  `noUrut` char(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `tb_urut`
--

INSERT INTO `tb_urut` (`idurut`, `namaForm`, `noUrut`) VALUES
(1, 'RQ', '114'),
(2, 'PE', '111'),
(3, 'US', '7'),
(4, 'PG', '40'),
(6, 'ME', '0'),
(7, 'MR', '11'),
(8, 'BR', '11'),
(9, 'IN', '106'),
(10, 'PL', '0'),
(11, 'PK', '27'),
(12, 'PP', '0'),
(13, 'PQ', '0'),
(14, 'TI', '37');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tglpenagihan`
--

CREATE TABLE `tglpenagihan` (
  `id` int(2) NOT NULL,
  `penagihan` varchar(5) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tiket_kategori`
--

CREATE TABLE `tiket_kategori` (
  `IDKATEGORITIKET` int(3) NOT NULL,
  `KATEGORI` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `tiket_kategori`
--

INSERT INTO `tiket_kategori` (`IDKATEGORITIKET`, `KATEGORI`) VALUES
(1, 'Gangguan'),
(2, 'Migrasi Paket'),
(3, 'Speed OnDeman'),
(4, 'Lainnya'),
(11, 'FO Cut');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tiket_masuk`
--

CREATE TABLE `tiket_masuk` (
  `NOTIKET` varchar(15) NOT NULL,
  `IDPELANGGAN` varchar(11) DEFAULT NULL,
  `IDKATEGORITIKET` int(3) DEFAULT NULL,
  `GANGGUANLAIN` text DEFAULT NULL,
  `KELUHAN` text DEFAULT NULL,
  `LAT` varchar(20) NOT NULL,
  `LONG` varchar(20) NOT NULL,
  `LAT_LOKASIGANGGUAN` varchar(50) NOT NULL DEFAULT '',
  `IMG_LOCATION` varchar(255) NOT NULL DEFAULT '',
  `LONG_LOKASIGANGGUAN` varchar(50) NOT NULL DEFAULT '',
  `INSERTEDBY` varchar(11) DEFAULT NULL,
  `INSERTEDAT` datetime NOT NULL,
  `PROCESSEDBY` varchar(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `tiket_masuk`
--

INSERT INTO `tiket_masuk` (`NOTIKET`, `IDPELANGGAN`, `IDKATEGORITIKET`, `GANGGUANLAIN`, `KELUHAN`, `LAT`, `LONG`, `LAT_LOKASIGANGGUAN`, `IMG_LOCATION`, `LONG_LOKASIGANGGUAN`, `INSERTEDBY`, `INSERTEDAT`, `PROCESSEDBY`) VALUES
('TI000001', 'PE000013', 1, '', 'sinyal wireless router melemah', '', '', '', '', '', 'PG000004', '2024-04-30 17:00:45', 'PG000014'),
('TI000002', 'PE000006', 1, '', 'modem los', '', '', '', '', '', 'PG000024', '2024-08-29 09:50:55', 'PG000014'),
('TI000003', 'PE000009', 1, '', 'modem nyala power', '', '', '', '', '', 'PG000024', '2024-08-29 15:04:43', 'PG000014'),
('TI000004', 'PE000032', 1, '', 'LOS', '', '', '', '', '', 'PG000004', '2024-09-20 17:03:54', 'PG000006'),
('TI000005', 'PE000012', 1, '', 'TES ON', '-8.0474747', '111.9224398', '', '', '', 'PG000001', '2024-09-25 11:23:49', 'PG000014'),
('TI000006', 'PE000017', 1, '', 'Modem LOS', '', '', '', '', '', 'PG000008', '2024-10-17 12:04:07', 'PG000025'),
('TI000007', 'PE000017', 1, '', 'ganti modem router', '', '', '', '', '', 'PG000020', '2024-10-23 21:25:09', 'PG000006'),
('TI000008', 'PE000025', 1, '', 'LOS', '', '', '', '', '', 'PG000020', '2024-10-26 19:57:22', 'PG000014'),
('TI000009', 'PE000034', 1, '', 'Cctv tidak terhubung ke wifinya', '', '', '', '', '', 'PG000024', '2024-11-18 10:31:17', 'PG000014'),
('TI000010', 'PE000019', 1, '', 'Modem LOS', '', '', '', '', '', 'PG000024', '2024-11-24 08:46:45', 'PG000025'),
('TI000011', 'PE000019', 1, '', 'LOS', '', '', '', '', '', 'PG000004', '2024-11-30 17:54:19', 'PG000014'),
('TI000012', 'PE000019', 1, '', 'LOS', '', '', '', '', '', 'PG000004', '2024-12-01 21:08:32', 'PG000025'),
('TI000013', 'PE000009', 1, '', 'LOS', '', '', '', '', '', 'PG000004', '2024-12-26 19:07:24', 'PG000029'),
('TI000014', 'PE000043', 1, '', 'Gangguan ODP LOS BDG 6 ODP 12, Pernah beberapa saat lalu putus di timur TPU NGIJO SAMBILAWANG', '', '', '', '', '', 'PG000024', '2025-01-01 10:28:42', 'PG000027'),
('TI000015', 'PE000039', 1, '', 'tidak terhubung ke server', '', '', '', '', '', 'PG000004', '2025-01-04 07:03:22', 'PG000024'),
('TI000016', 'PE000039', 1, '', 'PPPoE TIdak terhubung ke server , redaman aman', '', '', '', '', '', 'PG000004', '2025-01-04 07:04:06', 'PG000024'),
('TI000017', 'PE000001', 1, '', 'modem los', '', '', '', '', '', 'PG000020', '2025-01-16 20:19:50', 'PG000031'),
('TI000018', 'PE000001', 1, '', 'LOS', '', '', '', '', '', 'PG000004', '2025-01-20 16:36:22', 'PG000027'),
('TI000019', 'PE000001', 1, '', 'LOS', '', '', '', '', '', 'PG000004', '2025-01-20 16:46:51', 'PG000004'),
('TI000020', 'PE000009', 1, '', 'Modem LOS', '', '', '', '', '', 'PG000024', '2025-01-22 13:38:52', 'PG000029'),
('TI000021', 'PE000005', 1, '', 'Redaman tinggi modem sering terputus dari server', '', '', '', '', '', 'PG000024', '2025-01-26 20:47:37', 'PG000010'),
('TI000022', 'PE000049', 4, 'kabel jatuh di sekitaran lokasi pelangan', 'kabel jatuh di sekitaran lokasi pelangan , info dari pelanggan\r\n\"Barat rumah utara tikungan , soal e tadi ibuk di wa tetangga\"', '', '', '', '', '', 'PG000004', '2025-02-07 22:59:42', 'PG000006'),
('TI000023', 'PE000043', 1, '', 'Gangguan kenaikan redaman ftth port 2 dan 3 wringinanom', '', '', '-8.000479', '', '111.499078', 'PG000024', '2025-02-21 10:57:47', 'PG000014'),
('TI000024', 'PE000053', 1, '', 'LOS', '', '', '', '', '', 'PG000020', '2025-03-10 21:10:11', 'PG000014'),
('TI000025', 'PE000013', 1, '', 'modem los', '', '', '', '', '', 'PG000020', '2025-04-08 13:46:34', 'PG000010'),
('TI000026', 'PE000013', 1, '', 'LOS', '', '', '', '', '', 'PG000004', '2025-04-10 06:38:00', 'PG000005'),
('TI000027', 'PE000043', 1, '', 'BDG 7 ODP 4 Bancangan\r\nLOS', '', '', '-7.9625', '', '111.48953', 'PG000020', '2025-05-04 10:55:18', 'PG000005'),
('TI000028', 'PE000033', 1, '', 'tidak terhubung ke server', '', '', '', '', '', 'PG000020', '2025-05-17 16:31:59', 'PG000005'),
('TI000029', 'PE000011', 1, '', 'Modem LOS', '', '', '0', '', '0', 'PG000024', '2025-06-16 01:15:05', 'PG000034'),
('TI000031', 'PE000057', 1, '', 'gak konek', '', '', '0', '', '0', 'PG000034', '2025-06-20 14:35:18', 'PG000034'),
('TI000032', 'PE000057', 1, '', 'mati lagi pak', '', '', '0', '', '0', 'PG000034', '2025-06-20 14:44:54', 'PG000034'),
('TI000035', 'PE000013', 1, '', 'Tidak terhubung ke server/ LOS', '', '', '0', '', '0', 'PG000024', '2025-07-09 13:21:24', 'PG000014'),
('TI000036', 'PE000043', 1, '', 'Gangguan :\r\n2 port kabel arah pasar wringinanom saat ini terpantau sedang los, ada indikasi kabel putus\r\nhttps://maps.app.goo.gl/CDBSbUi7GRMV4hPv7', '', '', '0', '', '0', 'PG000024', '2025-07-09 13:33:00', 'PG000014'),
('TI000037', 'PE000006', 1, '', 'FO Cut', '', '', '0', '', '0', 'PG000005', '2025-08-17 07:03:46', 'PG000014');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tiket_proses`
--

CREATE TABLE `tiket_proses` (
  `NOTIKET` varchar(15) NOT NULL,
  `PROCESSEDBY` varchar(11) DEFAULT NULL,
  `PROCESSEDAT` timestamp NOT NULL DEFAULT current_timestamp(),
  `FLAG` tinyint(1) NOT NULL COMMENT '0="belum diproses";1="proses selesai/berhasil";2="proses gagal"',
  `FINISHEDAT` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `tiket_proses`
--

INSERT INTO `tiket_proses` (`NOTIKET`, `PROCESSEDBY`, `PROCESSEDAT`, `FLAG`, `FINISHEDAT`) VALUES
('TI000001', 'PG000014', '2024-05-01 09:56:03', 1, '0000-00-00 00:00:00'),
('TI000002', 'PG000014', '2024-08-29 08:11:03', 1, '0000-00-00 00:00:00'),
('TI000003', 'PG000014', '2024-08-29 08:53:22', 1, '0000-00-00 00:00:00'),
('TI000004', 'PG000006', '2024-09-21 05:25:39', 1, '0000-00-00 00:00:00'),
('TI000006', 'PG000025', '2024-10-17 08:49:36', 1, '0000-00-00 00:00:00'),
('TI000007', 'PG000006', '2024-10-24 05:17:29', 1, '0000-00-00 00:00:00'),
('TI000008', 'PG000014', '2024-10-28 03:50:00', 1, '0000-00-00 00:00:00'),
('TI000009', 'PG000014', '2024-11-19 03:07:02', 1, '0000-00-00 00:00:00'),
('TI000010', 'PG000025', '2024-11-28 00:59:36', 1, '0000-00-00 00:00:00'),
('TI000011', 'PG000014', '2024-12-01 14:00:23', 1, '0000-00-00 00:00:00'),
('TI000012', 'PG000025', '2024-12-02 07:57:19', 1, '0000-00-00 00:00:00'),
('TI000013', 'PG000029', '2024-12-29 00:18:46', 1, '0000-00-00 00:00:00'),
('TI000014', 'PG000027', '2025-01-01 07:13:51', 1, '0000-00-00 00:00:00'),
('TI000016', 'PG000024', '2025-01-04 04:59:54', 1, '0000-00-00 00:00:00'),
('TI000015', 'PG000024', '2025-01-04 05:00:03', 1, '0000-00-00 00:00:00'),
('TI000017', 'PG000031', '2025-01-17 04:45:01', 1, '0000-00-00 00:00:00'),
('TI000019', 'PG000004', '2025-01-20 09:47:46', 1, '0000-00-00 00:00:00'),
('TI000018', 'PG000027', '2025-01-21 04:17:49', 1, '0000-00-00 00:00:00'),
('TI000020', 'PG000029', '2025-01-22 08:45:06', 1, '0000-00-00 00:00:00'),
('TI000023', 'PG000014', '2025-02-21 14:00:43', 1, '0000-00-00 00:00:00'),
('TI000022', 'PG000006', '2025-03-03 03:32:59', 1, '0000-00-00 00:00:00'),
('TI000021', 'PG000010', '2025-03-08 08:05:15', 1, '0000-00-00 00:00:00'),
('TI000024', 'PG000014', '2025-03-11 05:15:00', 1, '0000-00-00 00:00:00'),
('TI000005', 'PG000014', '2025-03-11 05:15:15', 1, '0000-00-00 00:00:00'),
('TI000025', 'PG000010', '2025-04-09 07:38:18', 1, '0000-00-00 00:00:00'),
('TI000026', 'PG000005', '2025-04-10 02:04:31', 1, '0000-00-00 00:00:00'),
('TI000028', 'PG000005', '2025-05-23 08:24:00', 1, '0000-00-00 00:00:00'),
('TI000027', 'PG000005', '2025-05-23 08:24:09', 1, '0000-00-00 00:00:00'),
('TI000031', 'PG000034', '2025-06-20 07:36:06', 1, '0000-00-00 00:00:00'),
('TI000032', 'PG000034', '2025-06-20 07:45:05', 0, '0000-00-00 00:00:00'),
('TI000029', 'PG000034', '2025-06-25 21:47:09', 0, '0000-00-00 00:00:00'),
('TI000037', 'PG000014', '2025-10-06 13:33:52', 1, '0000-00-00 00:00:00'),
('TI000036', 'PG000014', '2025-10-06 13:33:55', 1, '0000-00-00 00:00:00'),
('TI000035', 'PG000014', '2025-10-06 13:33:59', 1, '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tiket_riwayatgagal`
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
-- Struktur dari tabel `tiket_riwayatselesai`
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

--
-- Dumping data untuk tabel `tiket_riwayatselesai`
--

INSERT INTO `tiket_riwayatselesai` (`NOTIKET`, `FOTOKARYAWAN`, `FOTOKONEKSI`, `RATTING`, `REVIEW`, `KONFIRMASI`, `CATATANTEKNISI`, `FINISHEDBY`, `FINISHEDAT`) VALUES
('TI000001', NULL, 'a09fe2544e0fc4ddc8653b55464a9f95.jpg', NULL, NULL, '', 'ganti ruter', 'PG000014', '2024-05-01 20:01:55'),
('TI000003', NULL, NULL, NULL, NULL, '', 'ganti modem dan splis ulang ', 'PG000014', '2024-08-29 16:46:09'),
('TI000002', NULL, NULL, NULL, NULL, '', 'splis ulang', 'PG000014', '2024-08-29 16:46:21'),
('TI000004', NULL, NULL, NULL, NULL, '', 'kabel putus ke gantol', 'PG000006', '2024-09-21 12:45:20'),
('TI000006', NULL, '1a4137c5bb23b1cee9ec74f766c0f8e2.jpg', NULL, NULL, '', 'perbaikan redaman BDG 4 ODP 16', 'PG000025', '2024-10-17 15:50:46'),
('TI000007', NULL, NULL, NULL, NULL, '', 'Ganti ZTE', 'PG000006', '2024-10-24 12:50:53'),
('TI000008', NULL, NULL, NULL, NULL, '', 'kabel putus di odp', 'PG000014', '2024-10-28 13:59:36'),
('TI000009', NULL, NULL, NULL, NULL, '', 'seting ulang', 'PG000014', '2024-11-20 10:44:22'),
('TI000010', NULL, NULL, NULL, NULL, '', '', 'PG000025', '2024-11-28 07:59:50'),
('TI000011', NULL, NULL, NULL, NULL, '', 'tiket kepencet keproses \r\nbelum ada pengecekan kelokasio', 'PG000014', '2024-12-02 07:54:32'),
('TI000012', NULL, NULL, NULL, NULL, '', 'splice di odp', 'PG000025', '2024-12-02 14:57:36'),
('TI000013', NULL, NULL, NULL, NULL, '', '', 'PG000029', '2024-12-29 07:19:00'),
('TI000014', NULL, NULL, NULL, NULL, '', 'Splice ulang', 'PG000027', '2025-01-01 14:14:10'),
('TI000016', NULL, NULL, NULL, NULL, '', 'Sudah selesai', 'PG000024', '2025-01-04 12:00:20'),
('TI000015', NULL, NULL, NULL, NULL, '', 'Sudah bisa', 'PG000024', '2025-01-04 12:00:29'),
('TI000017', NULL, NULL, NULL, NULL, '', 'Kabel putus', 'PG000031', '2025-01-17 11:45:26'),
('TI000019', NULL, NULL, NULL, NULL, '', 'salah input data', 'PG000004', '2025-01-20 16:48:10'),
('TI000018', NULL, NULL, NULL, NULL, '', 'kabel dilulun orang, penarikan kabel dan penyambungan ulang', 'PG000027', '2025-01-21 16:17:04'),
('TI000020', NULL, NULL, NULL, NULL, '', 'Splice ulang', 'PG000029', '2025-01-22 15:56:38'),
('TI000023', NULL, NULL, NULL, NULL, '', 'kabel putus terkena benda sajam', 'PG000014', '2025-02-21 21:00:43'),
('TI000022', NULL, NULL, NULL, NULL, '', 'Sudah dilakukan pengamanan & pergantian kabel fo ', 'PG000006', '2025-03-03 12:12:30'),
('TI000021', NULL, NULL, NULL, NULL, '', 'Tiket selesai', 'PG000010', '2025-03-08 15:05:15'),
('TI000024', NULL, NULL, NULL, NULL, '', 'splis ulang di rumah', 'PG000014', '2025-03-11 12:15:00'),
('TI000005', NULL, NULL, NULL, NULL, '', '', 'PG000014', '2025-03-11 12:15:15'),
('TI000025', NULL, NULL, NULL, NULL, '', 'Kabel putus dimakan tikus', 'PG000010', '2025-04-09 14:38:18'),
('TI000026', NULL, NULL, NULL, NULL, '', '', 'PG000005', '2025-04-10 09:04:31'),
('TI000027', NULL, NULL, NULL, NULL, '', '', 'PG000005', '2025-05-31 12:53:31'),
('TI000028', NULL, NULL, NULL, NULL, '', '', 'PG000005', '2025-05-31 12:53:39'),
('TI000031', NULL, NULL, NULL, NULL, '', 'kabelnya dimakan tikus', 'PG000034', '2025-06-20 14:38:00'),
('TI000035', NULL, NULL, NULL, NULL, '', '', 'PG000014', '2025-10-06 20:34:09'),
('TI000036', NULL, NULL, NULL, NULL, '', '', 'PG000014', '2025-10-06 20:34:32'),
('TI000037', NULL, NULL, NULL, NULL, '', '', 'PG000014', '2025-10-06 20:34:39');

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaksi`
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
-- Struktur dari tabel `users`
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
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`IDUSERS`, `IDPENGGUNA`, `USERNAME`, `PASSWORD`, `IDCABANG`, `JABATANID`, `AKSESANDROID`, `AKSESAPPPELANGGANID`, `ASADMIN_APPKEUANGAN`, `ENTRYBY`, `ENTRYDATE`, `LASTLOGIN`, `LASTIPADDR`, `BEARER`) VALUES
('6156ba0c2c', 'PG000001', 'faruqi', 'a4416712755cf7c534696241f3330be2c3fce216', 1, 1, 1, 1, '1', '1', '2021-10-01 14:35:35', '2021-10-01 00:00:00', '127.0.0.1', ''),
('6156ba9e6f', 'PG000002', 'taufik', 'd95f4131ee69b6383f80e52f9cd5128fcba780f0', 1, 1, 0, 1, '0', '0', '2021-10-01 14:44:35', '2021-10-01 00:00:00', '127.0.0.1', ''),
('6156bab76b', 'PG000003', 'etik', 'ca322994365d59e91fd9eb68bd61bf8aaf872c8b', 1, 1, 0, 1, '0', '0', '2021-10-01 14:44:08', '2021-10-01 00:00:00', '127.0.0.1', ''),
('6156bae559', 'PG000004', 'rohman', 'b8231d68ea09381c5d806a7c04a8185428a02c16', 1, 1, 0, 1, 'true', 'PG000004', '2024-03-16 13:45:44', '2024-03-16 00:00:00', '103.171.244.46', ''),
('6156bb1e75', 'PG000005', 'ausya', 'f65e44fadb585310cf729d4e81f3d0ec14362d87', 1, 1, 0, 1, '0', '0', '2021-10-01 14:42:56', '2021-10-01 00:00:00', '127.0.0.1', ''),
('6156bc8ce3', 'PG000006', 'arli', '0e3707782fb43ec7e12068a1a515cae1c5c96f9c', 1, 1, 0, 1, '', 'PG000008', '2024-06-08 09:28:24', '2024-06-08 00:00:00', '103.171.244.56', ''),
('6156bd54b1', 'PG000008', 'rifki', '7f21c292013257a6752eb6d574a11bb84b0100e6', 1, 1, 0, 1, '1', '1', '2021-10-01 14:54:30', '2021-10-01 00:00:00', '127.0.0.1', ''),
('6156bda369', 'PG000010', 'mawan', 'fb178ab67b4475746d33f5ba9a256f38e3e691da', 1, 1, 0, 1, '', 'PG000024', '2025-03-04 08:19:29', '2025-03-04 00:00:00', '103.171.244.101', ''),
('65f549687d', 'PG000014', 'imam', 'aa262e52bd86cd096c9a5dfd67ea6b46a1116418', 1, 5, 0, 1, '', 'PG000004', '2026-05-05 09:02:54', '2026-05-05 00:00:00', '165.99.202.251', ''),
('65f7a26219', 'PG000016', 'zulfan', 'f282f6cef5a5e22cfb6257673f51bcf88030d99a', 1, 5, 0, 1, '', 'PG000004', '2025-11-17 08:34:00', '2025-11-17 00:00:00', '165.99.202.248', ''),
('65f7a6cbbe', 'PG000018', NULL, NULL, 1, 1, 0, 1, '', 'PG000008', '2024-03-18 00:00:00', NULL, '103.171.244.64', ''),
('65fced199f', 'PE000001', 'tria-mustika-aji', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2024-03-22 00:00:00', NULL, '103.171.244.55', ''),
('65fcee7b24', 'PE000002', 'bayu-hiendhiarta', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2024-03-22 00:00:00', NULL, '103.171.244.55', ''),
('65fcef5088', 'PE000003', 'nurcholis sofyan tsawri', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2024-03-22 00:00:00', NULL, '103.171.244.55', ''),
('65fcefe47b', 'PE000004', 'very-angriawan', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2024-03-22 00:00:00', NULL, '103.171.244.55', ''),
('65fcf32c98', 'PE000005', 'nanang-dianarko', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2024-03-22 00:00:00', NULL, '103.171.244.55', ''),
('65fcf49a20', 'PE000006', 'adelia-dewi-maharani', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2024-03-22 00:00:00', NULL, '103.171.244.55', ''),
('66038471e9', 'PE000007', 'fitdyah-rusdiana', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2024-03-27 00:00:00', NULL, '103.171.244.55', ''),
('6603864d66', 'PE000008', 'mulyono', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2024-03-27 00:00:00', NULL, '103.171.244.55', ''),
('660387fe92', 'PE000009', 'widatul-khiyaroh', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2024-03-27 00:00:00', NULL, '103.171.244.55', ''),
('660a149d03', 'PE000010', 'muhamad-komarudin', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2024-04-01 00:00:00', NULL, '103.171.244.51', ''),
('660a23944c', 'PE000011', 'yogha-christian-gumilar', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2024-04-01 00:00:00', NULL, '103.171.244.51', ''),
('660a2982ba', 'PE000012', 'ad', '356a192b7913b04c54574d18c28d46e6395428ab', 1, 1, 0, 1, '', 'PG000001', '2024-12-05 20:18:47', '2024-12-05 20:18:47', '180.248.0.62', ''),
('661f31499b', 'PE000013', 'kristina-damayanti', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2024-04-17 00:00:00', NULL, '103.171.244.56', ''),
('6628ae1a00', 'PE000014', 'sogol', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2024-04-24 00:00:00', NULL, '103.171.244.56', ''),
('6628ae96e5', 'PE000015', 'edhi-kurniawan', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2024-04-24 00:00:00', NULL, '103.171.244.56', ''),
('6628b3cd45', 'PE000016', 'andi-maslikhul-qirom', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2024-04-24 00:00:00', NULL, '103.171.244.56', ''),
('6631fbcf03', 'PG000020', 'yusril', 'a86c28ed0de47bde9490b15b350e196d760f66a8', 1, 1, 0, 1, '', 'PG000004', '2024-05-01 15:23:28', '2024-05-01 00:00:00', '103.171.244.56', ''),
('6633993cb0', 'PE000017', 'HariyadiWringinanom', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000008', '2024-05-02 00:00:00', NULL, '103.171.244.56', ''),
('66358e1547', 'PE000018', 'wawan-yayuk', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2024-05-04 00:00:00', NULL, '103.171.244.56', ''),
('6635920630', 'PE000019', 'rini', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2024-05-04 00:00:00', NULL, '103.171.244.56', ''),
('663c9117e2', 'PG000022', 'enggar', 'e289a4b78891b7df73ea68965cfd4e6bc94efaaf', 1, 2, 0, 1, '', 'PG000008', '2024-05-09 16:30:21', '2024-05-09 00:00:00', '103.171.244.56', ''),
('663d769f8e', 'PG000023', 'andre', '39bd95ecb1419b9f497b5a64f2926102625bd8c1', 1, 5, 0, 1, '', 'PG000020', '2024-05-10 08:22:06', '2024-05-10 00:00:00', '103.171.244.56', ''),
('6644644df0', 'PE000020', 'samsul-huda', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2024-05-15 00:00:00', NULL, '103.171.244.56', ''),
('6654881e65', 'PE000021', 'Suwito', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000022', '2024-05-27 00:00:00', NULL, '103.171.244.197', ''),
('66694670f2', 'PE000022', 'anamul-mustaqim', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2024-06-12 00:00:00', NULL, '103.171.244.56', ''),
('6685a38d73', 'PE000023', '', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000008', '2024-07-04 00:00:00', NULL, NULL, ''),
('6685a3f00f', 'PE000024', '', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000008', '2024-07-04 00:00:00', NULL, NULL, ''),
('668dfda132', 'PE000025', 'rokayatin', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2024-07-10 00:00:00', NULL, '103.171.244.56', ''),
('668e00032a', 'PE000026', 'nyamin', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2024-07-10 00:00:00', NULL, '103.171.244.56', ''),
('668f30fbca', 'PE000031', 'mediana-hiska-wati', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2024-07-11 00:00:00', NULL, '103.171.244.56', ''),
('6695c20d8a', 'PG000024', 'arifqi', '9156e6f9e617cd4a8714af5f8f6b2a501fb6b11f', 1, 1, 0, 1, '', 'PG000008', '2024-07-16 07:43:14', '2024-07-16 00:00:00', '103.171.244.56', ''),
('669c87ddc8', 'PE000032', 'MeidianaRiskaWatiWringinanom', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000024', '2024-07-21 00:00:00', NULL, '103.171.244.41', ''),
('66a462db59', 'PE000033', 'nur-hayati', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2024-07-27 00:00:00', NULL, '103.171.244.41', ''),
('66f21484e6', 'PE000034', 'henny-sri-jaristy', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2024-09-24 00:00:00', NULL, '103.171.244.53', ''),
('66f78f855a', 'PE000035', 'test01', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000001', '2024-09-28 00:00:00', NULL, '125.166.8.138', ''),
('66fe549a6a', 'PE000036', 'wahid-asari', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2024-10-03 00:00:00', NULL, '103.171.244.62', ''),
('670c730c80', 'PE000037', 'ika-yohana-ratnasari', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2024-10-14 00:00:00', NULL, '103.171.244.62', ''),
('6718800797', 'PE000038', 'TESTs', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000001', '2024-10-23 00:00:00', NULL, '180.248.2.255', ''),
('6725852dbc', 'PE000039', 'lukman-asifuddin', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2024-11-02 00:00:00', NULL, '103.171.244.62', ''),
('6749891863', 'PE000040', 'sumarsih', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2024-11-29 00:00:00', NULL, '103.171.244.62', ''),
('675000f087', 'PE000041', 'fery-dwi-martdiawan', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2024-12-04 00:00:00', NULL, '103.171.244.62', ''),
('6754134803', 'PE000042', 'agus-wardana', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2024-12-07 00:00:00', NULL, '103.171.244.111', ''),
('6760e38bac', 'PG000028', 'pkl1', '40bd001563085fc35165329ea1ff5c5ecbdbbeef', 1, 1, 0, 1, '', 'PG000008', '2024-12-17 09:36:11', '2024-12-17 00:00:00', '103.171.244.65', ''),
('6774b55720', 'PE000043', '', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000024', '2025-01-01 00:00:00', NULL, NULL, ''),
('677741d95d', 'PE000044', 'veri-toko', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2025-01-03 00:00:00', NULL, NULL, ''),
('677741d96e', 'PE000045', 'nanang-dianarko-toko', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2025-01-03 00:00:00', NULL, NULL, ''),
('677741d983', 'PE000046', 'luki-novitasari', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2025-01-03 00:00:00', NULL, NULL, ''),
('677741d996', 'PE000047', 'sarno-mandor', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2025-01-03 00:00:00', NULL, NULL, ''),
('677741d9a1', 'PE000048', 'heri-kasiono', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2025-01-03 00:00:00', NULL, NULL, ''),
('677746da3f', 'PE000049', 'sulastri-anggres', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2025-01-03 00:00:00', NULL, NULL, ''),
('6778fc1cd9', 'PE000050', 'wateno', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2025-01-04 00:00:00', NULL, '103.171.244.110', ''),
('6791a241d4', 'PE000051', 'imam-choirul-anam', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2025-01-23 00:00:00', NULL, '103.171.244.111', ''),
('67ad582bd2', 'PE000052', 'bagas-mimbo-handoko', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2025-02-13 00:00:00', NULL, '103.171.244.108', ''),
('67bc5b53d5', 'PE000053', 'nur-ikhwan', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2025-02-24 00:00:00', NULL, '103.171.244.111', ''),
('67dbc7252a', 'PE000054', 'ansori', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2025-03-20 00:00:00', NULL, '103.171.244.108', ''),
('67ecec4645', 'PE000055', 'eko-setiawan', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2025-04-02 00:00:00', NULL, '103.171.244.108', ''),
('67f2260816', 'PE000056', 'yulius-subhil-mubarok', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000015', '2025-04-06 00:00:00', NULL, '103.171.244.111', ''),
('67fcb84bbf', 'PG000032', 'ridwan', '9b49cf0c5d300da5c499ec28c69ea2683a123515', 1, 1, 0, 1, '', 'PG000002', '2025-04-14 14:25:18', '2025-04-14 00:00:00', '103.171.244.110', ''),
('682f11928f', 'PG000034', 'webapptesting', 'bd8989e0afef634567fbc5230991220beaab8034', 1, 1, 0, 1, '', 'PG000004', '2025-05-22 18:59:45', '2025-05-22 00:00:00', '103.171.244.111', ''),
('6845518d1e', 'PE000057', 'wawan', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000034', '2025-06-08 00:00:00', NULL, '36.85.23.117', ''),
('6847f10e48', 'PE000058', 'somat', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000034', '2025-06-10 00:00:00', NULL, '36.82.90.74', ''),
('6847f7d559', 'PE000059', 'weeeee', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000034', '2025-06-10 00:00:00', NULL, '36.82.90.74', ''),
('684a52d036', 'US000001', 'pudezer', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000034', '2025-06-12 00:00:00', NULL, '36.71.81.167', ''),
('684a53767d', 'PE000060', 'cenefuj', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000034', '2025-06-12 00:00:00', NULL, '36.71.81.167', ''),
('684afa8b95', 'US000002', 'piqewed', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000034', '2025-06-12 00:00:00', NULL, '36.71.81.167', ''),
('684b16e6de', 'PE000061', 'tyhuc', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000034', '2025-06-13 00:00:00', NULL, '36.71.81.167', ''),
('684b18eb4f', 'PE000062', 'movedicuse', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000034', '2025-06-13 00:00:00', NULL, '36.71.81.167', ''),
('684b247022', 'PE000063', 'lezyjiduke', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000034', '2025-06-13 00:00:00', NULL, '36.71.81.167', ''),
('684bacde4d', 'PE000064', 'kidexohyfi', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000034', '2025-06-13 00:00:00', NULL, '36.71.81.167', ''),
('684baef1bb', 'US000003', 'xekigimoze', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000034', '2025-06-13 00:00:00', NULL, '36.71.81.167', ''),
('6852bb1cd6', 'US000004', 'sajugeqaro', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000034', '2025-06-18 00:00:00', NULL, '125.166.97.233', ''),
('68583ffac2', 'US000005', 'kelozyc', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000034', '2025-06-23 00:00:00', NULL, '125.166.97.233', ''),
('685bfd4484', 'US000006', 'midaqes', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000034', '2025-06-25 00:00:00', NULL, '125.166.96.252', ''),
('685c281c39', 'PE000065', 'qiqigyzavi', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000034', '2025-06-25 00:00:00', NULL, '125.166.96.252', ''),
('685c3f7938', 'US000007', 'kogyzefota', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000034', '2025-06-26 00:00:00', NULL, '125.166.96.252', ''),
('685c49bfa7', 'PE000066', 'xypokyva', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000034', '2025-06-26 00:00:00', NULL, '125.166.96.252', ''),
('685e144fa4', 'PE000067', 'ridwan-prasetyo', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000032', '2025-06-27 00:00:00', NULL, '103.171.244.110', ''),
('685f9cb5af', 'PE000068', 'zyxanudali', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000034', '2025-06-28 00:00:00', NULL, '125.166.96.129', ''),
('685f9db49d', 'PE000069', 'lenafy', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000034', '2025-06-28 00:00:00', NULL, '125.166.96.129', ''),
('68677b5609', 'PE000070', 'aris-subagyo', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000032', '2025-07-04 00:00:00', NULL, '103.186.9.69', ''),
('6879bb3d3c', 'PE000071', 'ida-erlina', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000032', '2025-07-18 00:00:00', NULL, '103.186.9.108', ''),
('6881dd7499', 'PE000072', 'nasrullah-hasri', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000032', '2025-07-24 00:00:00', NULL, '103.186.9.108', ''),
('6886e075bd', 'PE000073', 'koiriah', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000032', '2025-07-28 00:00:00', NULL, '103.186.9.108', ''),
('68944bf818', 'PE000074', 'sunarmi', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000032', '2025-08-07 00:00:00', NULL, '103.186.9.108', ''),
('68e3190463', 'PE000075', 'risky-multiyani', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000032', '2025-10-06 00:00:00', NULL, '103.186.9.200', ''),
('68e47ac814', 'PE000076', 'hengky-indra-kusuma', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000032', '2025-10-07 00:00:00', NULL, '103.186.9.200', ''),
('68e4d0f076', 'PE000077', 'danang-wahyu-rino-syahputra', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000032', '2025-10-07 00:00:00', NULL, '103.186.9.200', ''),
('68f5afc12c', 'PE000078', 'punjung-ali-setiawan', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000032', '2025-10-20 00:00:00', NULL, '103.186.9.200', ''),
('690456a55c', 'PE000079', 'rusmi', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000032', '2025-10-31 00:00:00', NULL, '165.99.202.248', ''),
('6909720106', 'PE000080', 'alfian-asfahani-putra', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000032', '2025-11-04 00:00:00', NULL, '165.99.202.248', ''),
('6917e10242', 'PG000035', 'riobagus', '95e3a4d944e33ca34888d0355821f3941ac15ded', 1, 5, 0, 1, '', 'PG000024', '2025-11-15 09:10:32', '2025-11-15 00:00:00', '165.99.202.248', ''),
('691d4a0bbc', 'PG000036', 'lisvi', '7c222fb2927d828af22f592134e8932480637c0d', 1, 1, 0, 1, '', 'PG000032', '2025-11-19 11:39:53', '2025-11-19 00:00:00', '165.99.202.250', ''),
('693d0c6dd9', 'PE000081', 'agung-cahyono', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000036', '2025-12-13 00:00:00', NULL, '165.99.202.251', ''),
('6944ad5dbb', 'PE000082', ' bayu-arga-adhi-sukma', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000036', '2025-12-19 00:00:00', NULL, '165.99.202.251', ''),
('6948f0d3c5', 'PE000083', 'baitul-mawardani', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000036', '2025-12-22 00:00:00', NULL, '165.99.202.251', ''),
('694f42f87b', 'PE000084', 'zainal-abidin', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000036', '2025-12-27 00:00:00', NULL, '165.99.202.251', ''),
('6975c3e6e9', 'PG000037', 'novarel', '73236f5157d448001370ea5950db6e047da0354f', 1, 5, 0, 1, '', 'PG000024', '2026-01-25 14:19:22', '2026-01-25 00:00:00', '165.99.202.248', ''),
('6980291c45', 'PE000085', 'khoirur-rosiqi', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000036', '2026-02-02 00:00:00', NULL, '165.99.202.251', ''),
('69cb0b9a2d', 'PE000086', 'andika-ahmad-weldan', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000036', '2026-03-31 00:00:00', NULL, '165.99.202.251', ''),
('69d741f887', 'PE000087', 'nanik-suyatini', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000036', '2026-04-09 00:00:00', NULL, '165.99.202.251', ''),
('69e58913dc', 'PE000088', 'muji-mulyani', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000036', '2026-04-20 00:00:00', NULL, '165.99.202.250', ''),
('69f0324cdd', 'PE000089', 'wahyu-imam-nurhanto', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000036', '2026-04-28 00:00:00', NULL, '165.99.202.250', ''),
('69f051a58e', 'PE000090', 'retno-tri-wuryani', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000036', '2026-04-28 00:00:00', NULL, '165.99.202.250', ''),
('69f57562da', 'PE000091', 'sutaji', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000036', '2026-05-02 00:00:00', NULL, '165.99.202.250', ''),
('69f9746b7d', 'PE000092', 'samud', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000036', '2026-05-05 00:00:00', NULL, '165.99.202.250', ''),
('69fc06ee44', 'PE000093', 'agus-suwarno', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000036', '2026-05-07 00:00:00', NULL, '165.99.202.250', ''),
('6a015a11a6', 'PE000094', 'nagrio-pislendra-lembong', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000036', '2026-05-11 00:00:00', NULL, '165.99.202.250', ''),
('6a066cd81e', 'PE000095', 'sri-ani', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000036', '2026-05-15 00:00:00', NULL, '165.99.202.250', ''),
('6a0a702bef', 'PE000096', 'devi-sartika', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000036', '2026-05-18 00:00:00', NULL, '165.99.202.250', ''),
('6a0c0f8ad9', 'PE000097', 'andika-irfan-ramadani', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000036', '2026-05-19 00:00:00', NULL, '165.99.202.250', ''),
('6a13ba3ccc', 'PE000098', 'hariyadi-rt01rw01', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000036', '2026-05-25 00:00:00', NULL, '165.99.202.250', ''),
('6a1aa137c3', 'PE000099', 'sofyan-nur-wahid', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000036', '2026-05-30 00:00:00', NULL, '165.99.203.250', ''),
('6a1cdb0c84', 'PE000100', 'latiful-khoiron', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000036', '2026-06-01 00:00:00', NULL, '165.99.202.66', ''),
('6a1cfb1a29', 'PG000038', 'arjuncicun', 'a017cad6c54f2e3cdd0642e86e9fa0050ddaaf2c', 1, 5, 0, 1, '', 'PG000002', '2026-06-01 10:23:31', '2026-06-01 00:00:00', '165.99.202.65', ''),
('6a1e21de2a', 'PE000101', 'muhammad-sjahson-mujtahiddin', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000036', '2026-06-02 00:00:00', NULL, '165.99.202.66', ''),
('6a1e834c9f', 'PE000102', 'dimas-ghio-ferry', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000036', '2026-06-02 00:00:00', NULL, '165.99.202.250', ''),
('6a20e2fa22', 'PE000103', 'arya-henindra-febriansyah', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000036', '2026-06-04 00:00:00', NULL, '165.99.202.250', ''),
('6a32623397', 'PE000104', 'soiman', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000036', '2026-06-17 00:00:00', NULL, '165.99.203.250', ''),
('6a39eeb71d', 'PE000105', 'soiman-masjid-nahdlatul-ummah', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000036', '2026-06-23 00:00:00', NULL, '165.99.203.248', ''),
('6a3ca11e35', 'PE000106', 'indra-nur-cahyono', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000036', '2026-06-25 00:00:00', NULL, '165.99.203.248', ''),
('6a3f2d2665', 'PG000040', 'dwisaputra', '03cb3d0958c37eff1579d63639b6870f186bdc23', 1, 5, 0, 1, '', 'PG000002', '2026-06-27 08:59:57', '2026-06-27 00:00:00', '165.99.202.249', ''),
('6a6c54d9ac', 'PE000107', 'muhamad-suhadak', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000036', '2026-07-31 00:00:00', NULL, '165.99.203.248', ''),
('6a7ebdede4', 'PE000108', 'endah-budi-utami', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000036', '2026-08-14 00:00:00', NULL, '165.99.203.248', ''),
('6a9a638681', 'PE000109', 'sutini', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000036', '2026-09-04 00:00:00', NULL, '165.99.202.101', ''),
('6a9e0edd1f', 'PE000110', 'sriani-2', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000005', '2026-09-07 00:00:00', NULL, '165.99.202.101', ''),
('6ae5962761', 'PE000111', 'ahmad-zaini', '20eabe5d64b0e216796e834f52d61fd0b70332fc', 1, 8, 0, 1, '', 'PG000036', '2026-10-31 00:00:00', NULL, '165.99.203.248', '');

-- --------------------------------------------------------

--
-- Struktur dari tabel `wa_template_pesan`
--

CREATE TABLE `wa_template_pesan` (
  `ID` int(6) NOT NULL,
  `TITLE` varchar(150) NOT NULL,
  `ALIAS` varchar(150) NOT NULL,
  `TEMPLATEMESSAGE` text NOT NULL,
  `CREATEDAT` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `wa_template_pesan`
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
-- Indeks untuk tabel `apiakses`
--
ALTER TABLE `apiakses`
  ADD PRIMARY KEY (`IDUSERS`);

--
-- Indeks untuk tabel `apikeuangan_buktitransaksilunas`
--
ALTER TABLE `apikeuangan_buktitransaksilunas`
  ADD PRIMARY KEY (`IDUNIQ`);

--
-- Indeks untuk tabel `apikeuangan_buktitransaksipemasangan`
--
ALTER TABLE `apikeuangan_buktitransaksipemasangan`
  ADD PRIMARY KEY (`IDPERMINTAAN`);

--
-- Indeks untuk tabel `apikeuangan_buktitransaksitagihan`
--
ALTER TABLE `apikeuangan_buktitransaksitagihan`
  ADD PRIMARY KEY (`IDUNIQ`);

--
-- Indeks untuk tabel `apikeuangan_buktitransaksiterkumpul`
--
ALTER TABLE `apikeuangan_buktitransaksiterkumpul`
  ADD PRIMARY KEY (`IDUNIQ`);

--
-- Indeks untuk tabel `apikeuangan_detailjurnalpemasukan`
--
ALTER TABLE `apikeuangan_detailjurnalpemasukan`
  ADD PRIMARY KEY (`IDJURNALPEMASUKAN`);

--
-- Indeks untuk tabel `apikeuangan_detailjurnalpengeluaran`
--
ALTER TABLE `apikeuangan_detailjurnalpengeluaran`
  ADD PRIMARY KEY (`IDJURNALPENGELUARAN`);

--
-- Indeks untuk tabel `apikeuangan_jurnaloperasioanl`
--
ALTER TABLE `apikeuangan_jurnaloperasioanl`
  ADD PRIMARY KEY (`IDOPERASIONAL`);

--
-- Indeks untuk tabel `apikeuangan_jurnalpemasukan`
--
ALTER TABLE `apikeuangan_jurnalpemasukan`
  ADD PRIMARY KEY (`IDJURNALPEMASUKAN`);

--
-- Indeks untuk tabel `apikeuangan_jurnalpengeluaran`
--
ALTER TABLE `apikeuangan_jurnalpengeluaran`
  ADD PRIMARY KEY (`IDJURNALPENGELUARAN`);

--
-- Indeks untuk tabel `apikeuangan_jurnaltranfer`
--
ALTER TABLE `apikeuangan_jurnaltranfer`
  ADD PRIMARY KEY (`IDJURNALTRANFER`);

--
-- Indeks untuk tabel `barang`
--
ALTER TABLE `barang`
  ADD PRIMARY KEY (`KODEBARANG`);

--
-- Indeks untuk tabel `beritainfo`
--
ALTER TABLE `beritainfo`
  ADD PRIMARY KEY (`IDCONTENT`);

--
-- Indeks untuk tabel `biaya_tagihan`
--
ALTER TABLE `biaya_tagihan`
  ADD PRIMARY KEY (`IDBIAYA`);

--
-- Indeks untuk tabel `cabang`
--
ALTER TABLE `cabang`
  ADD PRIMARY KEY (`ID`);

--
-- Indeks untuk tabel `cron`
--
ALTER TABLE `cron`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `detail_aktivasi_pppoe`
--
ALTER TABLE `detail_aktivasi_pppoe`
  ADD PRIMARY KEY (`IDDETAILAKTIVASIPPPOE`);

--
-- Indeks untuk tabel `detail_aktivasi_queue`
--
ALTER TABLE `detail_aktivasi_queue`
  ADD PRIMARY KEY (`IDDETAILAKTIVASIQUEUE`);

--
-- Indeks untuk tabel `detail_jurnal_harian`
--
ALTER TABLE `detail_jurnal_harian`
  ADD PRIMARY KEY (`IDDETAILJURNAL`);

--
-- Indeks untuk tabel `detail_komisi_mitra`
--
ALTER TABLE `detail_komisi_mitra`
  ADD PRIMARY KEY (`IDKOMISI`);

--
-- Indeks untuk tabel `ip_kosong`
--
ALTER TABLE `ip_kosong`
  ADD PRIMARY KEY (`IPADDRESS`);

--
-- Indeks untuk tabel `jabatan`
--
ALTER TABLE `jabatan`
  ADD PRIMARY KEY (`ID`);

--
-- Indeks untuk tabel `jenka_paket`
--
ALTER TABLE `jenka_paket`
  ADD PRIMARY KEY (`ID`);

--
-- Indeks untuk tabel `jurnalharian`
--
ALTER TABLE `jurnalharian`
  ADD PRIMARY KEY (`KODEJURNAL`);

--
-- Indeks untuk tabel `kategori_barang`
--
ALTER TABLE `kategori_barang`
  ADD PRIMARY KEY (`KODEKATEGORIBARANG`);

--
-- Indeks untuk tabel `kategori_perangkat_jaringan`
--
ALTER TABLE `kategori_perangkat_jaringan`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `kode_kontrol_distribusi`
--
ALTER TABLE `kode_kontrol_distribusi`
  ADD PRIMARY KEY (`kode`);

--
-- Indeks untuk tabel `komisi`
--
ALTER TABLE `komisi`
  ADD PRIMARY KEY (`ID`);

--
-- Indeks untuk tabel `komisi_mitra`
--
ALTER TABLE `komisi_mitra`
  ADD PRIMARY KEY (`IDKOMISI`);

--
-- Indeks untuk tabel `laporan_pemasangan_wifi`
--
ALTER TABLE `laporan_pemasangan_wifi`
  ADD PRIMARY KEY (`IDREPORT`),
  ADD KEY `idx_idpengguna` (`IDPENGGUNA`),
  ADD KEY `idx_ipaddr` (`IPADDR`);

--
-- Indeks untuk tabel `level`
--
ALTER TABLE `level`
  ADD PRIMARY KEY (`ID`);

--
-- Indeks untuk tabel `merk_barang`
--
ALTER TABLE `merk_barang`
  ADD PRIMARY KEY (`IDMERK`);

--
-- Indeks untuk tabel `noonu1024`
--
ALTER TABLE `noonu1024`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `odp_location`
--
ALTER TABLE `odp_location`
  ADD PRIMARY KEY (`IDLOCATION`);

--
-- Indeks untuk tabel `olt_aktifasi`
--
ALTER TABLE `olt_aktifasi`
  ADD PRIMARY KEY (`IDAKTIFASI`);

--
-- Indeks untuk tabel `olt_check_dbm`
--
ALTER TABLE `olt_check_dbm`
  ADD PRIMARY KEY (`ID`);

--
-- Indeks untuk tabel `olt_report_signal`
--
ALTER TABLE `olt_report_signal`
  ADD PRIMARY KEY (`ID`);

--
-- Indeks untuk tabel `olt_report_state`
--
ALTER TABLE `olt_report_state`
  ADD PRIMARY KEY (`ID`);

--
-- Indeks untuk tabel `olt_slot_register`
--
ALTER TABLE `olt_slot_register`
  ADD PRIMARY KEY (`IDMESIN`);

--
-- Indeks untuk tabel `olt_slot_unregister`
--
ALTER TABLE `olt_slot_unregister`
  ADD PRIMARY KEY (`IDMESIN`);

--
-- Indeks untuk tabel `paket`
--
ALTER TABLE `paket`
  ADD PRIMARY KEY (`KODEPAKET`),
  ADD KEY `idx_kodepaket` (`KODEPAKET`),
  ADD KEY `idx_namapaket` (`NAMA_PAKET`);

--
-- Indeks untuk tabel `penagihan`
--
ALTER TABLE `penagihan`
  ADD PRIMARY KEY (`IDTAGIHAN`);

--
-- Indeks untuk tabel `pengguna`
--
ALTER TABLE `pengguna`
  ADD PRIMARY KEY (`IDPENGGUNA`),
  ADD KEY `idx_idpengguna` (`IDPENGGUNA`),
  ADD KEY `idx_idwilayah` (`IDWILAYAH`),
  ADD KEY `idx_hp` (`HP`),
  ADD KEY `idx_namadepan` (`NAMADEPAN`);

--
-- Indeks untuk tabel `person`
--
ALTER TABLE `person`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `prosedure_permintaan_wifi`
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
-- Indeks untuk tabel `riwayatstatus_penggunabarang`
--
ALTER TABLE `riwayatstatus_penggunabarang`
  ADD PRIMARY KEY (`ID`);

--
-- Indeks untuk tabel `riwayat_pelanggan`
--
ALTER TABLE `riwayat_pelanggan`
  ADD PRIMARY KEY (`ID`);

--
-- Indeks untuk tabel `router`
--
ALTER TABLE `router`
  ADD PRIMARY KEY (`IDROUTER`);

--
-- Indeks untuk tabel `satuan`
--
ALTER TABLE `satuan`
  ADD PRIMARY KEY (`IDSATUAN`);

--
-- Indeks untuk tabel `setting_billing`
--
ALTER TABLE `setting_billing`
  ADD PRIMARY KEY (`IDSETTING`);

--
-- Indeks untuk tabel `set_app`
--
ALTER TABLE `set_app`
  ADD PRIMARY KEY (`ID`);

--
-- Indeks untuk tabel `statusbarang`
--
ALTER TABLE `statusbarang`
  ADD PRIMARY KEY (`IDSTATUSBARANG`);

--
-- Indeks untuk tabel `tampilan`
--
ALTER TABLE `tampilan`
  ADD PRIMARY KEY (`IDTAMPILAN`);

--
-- Indeks untuk tabel `tb_history_process_finish_installation`
--
ALTER TABLE `tb_history_process_finish_installation`
  ADD PRIMARY KEY (`IDUNIQ`);

--
-- Indeks untuk tabel `tb_history_process_not_active`
--
ALTER TABLE `tb_history_process_not_active`
  ADD PRIMARY KEY (`IDUNIQ`);

--
-- Indeks untuk tabel `tb_history_process_start_installation`
--
ALTER TABLE `tb_history_process_start_installation`
  ADD PRIMARY KEY (`IDUNIQ`);

--
-- Indeks untuk tabel `tb_history_process_survey`
--
ALTER TABLE `tb_history_process_survey`
  ADD PRIMARY KEY (`IDUNIQ`);

--
-- Indeks untuk tabel `tb_history_process_team_installation`
--
ALTER TABLE `tb_history_process_team_installation`
  ADD PRIMARY KEY (`IDUNIQ`);

--
-- Indeks untuk tabel `tb_history_process_verification`
--
ALTER TABLE `tb_history_process_verification`
  ADD PRIMARY KEY (`IDUNIQ`);

--
-- Indeks untuk tabel `tb_history_registrations`
--
ALTER TABLE `tb_history_registrations`
  ADD PRIMARY KEY (`IDUNIQ`);

--
-- Indeks untuk tabel `tb_history_report_survey`
--
ALTER TABLE `tb_history_report_survey`
  ADD PRIMARY KEY (`IDUNIQ`);

--
-- Indeks untuk tabel `tb_menu`
--
ALTER TABLE `tb_menu`
  ADD PRIMARY KEY (`menuid`);

--
-- Indeks untuk tabel `tb_setup_notif`
--
ALTER TABLE `tb_setup_notif`
  ADD PRIMARY KEY (`ID`);

--
-- Indeks untuk tabel `tb_tiernotif`
--
ALTER TABLE `tb_tiernotif`
  ADD PRIMARY KEY (`ID`);

--
-- Indeks untuk tabel `tiket_kategori`
--
ALTER TABLE `tiket_kategori`
  ADD PRIMARY KEY (`IDKATEGORITIKET`);

--
-- Indeks untuk tabel `tiket_masuk`
--
ALTER TABLE `tiket_masuk`
  ADD PRIMARY KEY (`NOTIKET`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`IDUSERS`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `tb_setup_notif`
--
ALTER TABLE `tb_setup_notif`
  MODIFY `ID` int(4) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `tb_tiernotif`
--
ALTER TABLE `tb_tiernotif`
  MODIFY `ID` int(3) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `prosedure_permintaan_wifi`
--
ALTER TABLE `prosedure_permintaan_wifi`
  ADD CONSTRAINT `prosedure_permintaan_wifi_ibfk_1` FOREIGN KEY (`kategori_perangkat_jaringan`) REFERENCES `kategori_perangkat_jaringan` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `prosedure_permintaan_wifi_ibfk_2` FOREIGN KEY (`kode_kontrol_distribusi`) REFERENCES `kode_kontrol_distribusi` (`kode`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
