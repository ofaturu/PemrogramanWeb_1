-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 08, 2026 at 08:46 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ftrans`
--

-- --------------------------------------------------------

--
-- Table structure for table `kendaraan`
--

CREATE TABLE `kendaraan` (
  `kode_unik_kendaraan` int(10) NOT NULL,
  `id_merk` int(50) NOT NULL,
  `nama_kendaraan` varchar(50) DEFAULT NULL,
  `jenis_kendaraan` enum('roda 2','roda 4') DEFAULT NULL,
  `harga_per_hari` int(10) DEFAULT NULL,
  `gambar` longblob DEFAULT NULL,
  `transmisi` varchar(20) DEFAULT 'Matic',
  `tempat_duduk` varchar(20) DEFAULT '5 Seater',
  `bahan_bakar` varchar(30) DEFAULT 'Bensin',
  `status_kendaraan` enum('tersedia','disewa','perawatan') DEFAULT 'tersedia',
  `stok` int(11) DEFAULT 1,
  `warna` varchar(50) DEFAULT 'Hitam'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kendaraan`
--

INSERT INTO `kendaraan` (`kode_unik_kendaraan`, `id_merk`, `nama_kendaraan`, `jenis_kendaraan`, `harga_per_hari`, `gambar`, `transmisi`, `tempat_duduk`, `bahan_bakar`, `status_kendaraan`, `stok`, `warna`) VALUES
(1, 14, 'Audi A8', 'roda 4', 1000000, 0x61382e6a7067, 'Matic', '5 Seater', 'Bensin', 'disewa', 1, 'Hitam'),
(2, 12, 'BMW M4', 'roda 4', 750000, 0x6d342e77656270, 'Matic', '4 Seater', 'Bensin', 'disewa', 1, 'Biru'),
(3, 1, 'Honda NSX', 'roda 4', 2500000, 0x6e73782e6a7067, 'Matic', '2 Seater', 'Bensin', 'disewa', 1, 'Oranye'),
(4, 2, 'Toyota GR Supra', 'roda 4', 3000000, 0x67725f73757072612e6a7067, 'Matic', '2 Seater', 'Bensin', 'disewa', 1, 'Merah'),
(5, 8, 'Mazda RX 7', 'roda 4', 2000000, 0x72785f372e6a7067, 'Matic', '2 Seater', 'Bensin', 'tersedia', 1, 'Hitam'),
(6, 19, 'Kawasaki z1000', 'roda 2', 555555, 0x7a313030302e6a7067, 'Matic', '2 Seater', 'V Power', 'perawatan', 1, 'Hitam'),
(7, 6, 'Mitsubishi Lancer Evo X', 'roda 4', 1000000, 0x6c616e6365725f65766f5f782e77656270, 'Matic', '2 Seater', 'Bensin', 'tersedia', 1, 'Hitam'),
(8, 7, 'Nissan GTR R35', 'roda 4', 3500000, 0x6774725f7233352e6a7067, 'Matic', '2 Seater', 'Bensin', 'tersedia', 1, 'Putih'),
(9, 18, 'Yamaha R1M', 'roda 2', 500000, 0x72316d2e6a7067, 'Matic', '2 Seater', 'Bensin', 'disewa', 1, 'Hitam');

-- --------------------------------------------------------

--
-- Table structure for table `landing_settings`
--

CREATE TABLE `landing_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `landing_settings`
--

INSERT INTO `landing_settings` (`setting_key`, `setting_value`) VALUES
('hero_image', 'https://images.unsplash.com/photo-1614162692292-7ac56d7f7f1e?auto=format&fit=crop&w=1000&q=80'),
('hero_subtitle', 'Nikmati kenyamanan berkendara terbaik dengan armada mobil mewah dan pelayanan VIP yang dirancang khusus untuk memenuhi standar eksklusivitas Anda.'),
('hero_title', 'Eksplorasi Perjalanan Kelas Dunia Bersama Kami.');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance`
--

CREATE TABLE `maintenance` (
  `id` int(11) NOT NULL,
  `kode_unik_kendaraan` int(11) NOT NULL,
  `deskripsi` varchar(255) NOT NULL,
  `biaya` int(11) DEFAULT 0,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maintenance`
--

INSERT INTO `maintenance` (`id`, `kode_unik_kendaraan`, `deskripsi`, `biaya`, `tanggal_mulai`, `tanggal_selesai`) VALUES
(1, 1, 'ganti oli', 500000, '2026-07-06', '2026-07-06'),
(2, 2, 'pajak', 2500000, '2026-07-06', '2026-07-06'),
(3, 6, 'modif hedon', 0, '2026-07-06', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `merk_kendaraan`
--

CREATE TABLE `merk_kendaraan` (
  `id_merk` int(10) NOT NULL,
  `nama_merk` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `merk_kendaraan`
--

INSERT INTO `merk_kendaraan` (`id_merk`, `nama_merk`) VALUES
(1, 'honda'),
(2, 'toyota'),
(3, 'daihatsu'),
(4, 'kia'),
(5, 'Suzuki'),
(6, 'Mitsubishi'),
(7, 'Nissan'),
(8, 'Mazda'),
(9, 'Isuzu'),
(10, 'Wuling'),
(11, 'Hyundai'),
(12, 'BMW'),
(13, 'Mercedes-Benz'),
(14, 'Audi'),
(15, 'Volkswagen'),
(16, 'Ford'),
(17, 'Chevrolet'),
(18, 'Yamaha'),
(19, 'Kawasaki'),
(20, 'Vespa'),
(21, 'Porsche');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 8, 'Pemesanan Kendaraan Berhasil', 'Pemesanan kendaraan Audi A8 Anda berhasil dibuat. Silakan selesaikan pembayaran.', 1, '2026-07-06 12:31:29'),
(2, NULL, 'Pemesanan Baru Masuk', 'Penyewa ofaturu baru saja melakukan pemesanan kendaraan Audi A8.', 1, '2026-07-06 12:31:29'),
(3, 8, 'Pembayaran Xendit Sukses', 'Pembayaran Anda via VA_SIMULATOR sebesar Rp 1.000.000 untuk sewa Audi A8 berhasil diterima.', 1, '2026-07-06 12:31:42'),
(4, NULL, 'Pembayaran Masuk (Xendit)', 'Pembayaran penyewaan #INV-40 oleh ofaturu sebesar Rp 1.000.000 telah sukses dibayar via Xendit.', 1, '2026-07-06 12:31:42'),
(5, 8, 'Pemesanan Kendaraan Berhasil', 'Pemesanan kendaraan BMW M4 Anda berhasil dibuat. Silakan selesaikan pembayaran.', 1, '2026-07-06 12:46:44'),
(6, NULL, 'Pemesanan Baru Masuk', 'Penyewa ofaturu baru saja melakukan pemesanan kendaraan BMW M4.', 1, '2026-07-06 12:46:44'),
(7, 8, 'Pembayaran Xendit Sukses', 'Pembayaran Anda via VA_SIMULATOR sebesar Rp 12.750.000 untuk sewa BMW M4 berhasil diterima.', 1, '2026-07-06 12:46:53'),
(8, NULL, 'Pembayaran Masuk (Xendit)', 'Pembayaran penyewaan #INV-41 oleh ofaturu sebesar Rp 12.750.000 telah sukses dibayar via Xendit.', 1, '2026-07-06 12:46:53'),
(9, 8, 'Pemesanan Kendaraan Berhasil', 'Pemesanan kendaraan Honda NSX Anda berhasil dibuat. Silakan selesaikan pembayaran.', 1, '2026-07-06 12:47:06'),
(10, NULL, 'Pemesanan Baru Masuk', 'Penyewa ofaturu baru saja melakukan pemesanan kendaraan Honda NSX.', 1, '2026-07-06 12:47:06'),
(11, 8, 'Pembayaran Xendit Sukses', 'Pembayaran Anda via VA_SIMULATOR sebesar Rp 2.500.000 untuk sewa Honda NSX berhasil diterima.', 1, '2026-07-06 12:54:03'),
(12, NULL, 'Pembayaran Masuk (Xendit)', 'Pembayaran penyewaan #INV-42 oleh ofaturu sebesar Rp 2.500.000 telah sukses dibayar via Xendit.', 1, '2026-07-06 12:54:03'),
(13, 8, 'Pemesanan Kendaraan Berhasil', 'Pemesanan kendaraan Toyota GR Supra Anda berhasil dibuat. Silakan selesaikan pembayaran.', 1, '2026-07-06 13:14:30'),
(14, NULL, 'Pemesanan Baru Masuk', 'Penyewa ofaturu baru saja melakukan pemesanan kendaraan Toyota GR Supra.', 1, '2026-07-06 13:14:30'),
(15, 8, 'Pembayaran Xendit Sukses', 'Pembayaran Anda via VA_SIMULATOR sebesar Rp 3.000.000 untuk sewa Toyota GR Supra berhasil diterima.', 1, '2026-07-06 13:14:38'),
(16, NULL, 'Pembayaran Masuk (Xendit)', 'Pembayaran penyewaan #INV-43 oleh ofaturu sebesar Rp 3.000.000 telah sukses dibayar via Xendit.', 1, '2026-07-06 13:14:38');

-- --------------------------------------------------------

--
-- Table structure for table `penyewaan`
--

CREATE TABLE `penyewaan` (
  `id_sewa` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `kode_unik_kendaraan` int(10) NOT NULL,
  `tanggal_sewa` datetime NOT NULL,
  `tanggal_kembali` datetime NOT NULL,
  `total_biaya` int(11) DEFAULT NULL,
  `status` enum('booking','sedang_disewa','selesai','dibatalkan') DEFAULT 'booking',
  `bukti_pembayaran` varchar(255) DEFAULT NULL,
  `waktu_bayar` datetime DEFAULT NULL,
  `xendit_invoice_id` varchar(255) DEFAULT NULL,
  `xendit_invoice_url` varchar(500) DEFAULT NULL,
  `tanggal_kembali_aktual` datetime DEFAULT NULL,
  `denda` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `penyewaan`
--

INSERT INTO `penyewaan` (`id_sewa`, `id_user`, `kode_unik_kendaraan`, `tanggal_sewa`, `tanggal_kembali`, `total_biaya`, `status`, `bukti_pembayaran`, `waktu_bayar`, `xendit_invoice_id`, `xendit_invoice_url`) VALUES
(31, 1, 3, '2026-07-05 10:23:00', '2026-07-06 10:23:00', 2500000, 'selesai', NULL, NULL, NULL, NULL),
(32, 8, 1, '2026-07-05 10:24:00', '2026-07-06 10:24:00', 1000000, 'selesai', 'bukti pembayaran_ofaturu_32.png', '2026-07-05 15:24:47', NULL, NULL),
(33, 8, 3, '2026-07-05 10:29:00', '2026-07-06 10:29:00', 2500000, 'selesai', 'bukti pembayaran_ofaturu_33.jpg', '2026-07-05 15:29:50', NULL, NULL),
(35, 8, 8, '2026-07-05 10:44:00', '2026-07-06 10:44:00', 3500000, 'selesai', 'bukti pembayaran_ofaturu_35.jpg', '2026-07-05 15:44:30', NULL, NULL),
(37, 8, 1, '2026-07-05 10:48:00', '2026-07-06 10:48:00', 1000000, 'selesai', 'bukti pembayaran_ofaturu_37.jpg', '2026-07-05 15:49:04', NULL, NULL),
(39, 8, 9, '2026-07-06 12:53:00', '2026-07-07 12:53:00', 500000, 'sedang_disewa', NULL, NULL, NULL, NULL),
(40, 8, 1, '2026-07-06 14:31:00', '2026-07-07 14:31:00', 1000000, 'sedang_disewa', NULL, '2026-07-06 19:31:42', NULL, NULL),
(41, 8, 2, '2026-07-06 20:00:00', '2026-07-23 20:00:00', 12750000, 'sedang_disewa', NULL, '2026-07-06 19:46:53', NULL, NULL),
(42, 8, 3, '2026-07-06 20:00:00', '2026-07-07 20:00:00', 2500000, 'sedang_disewa', NULL, '2026-07-06 19:54:03', NULL, NULL),
(43, 8, 4, '2026-07-06 21:00:00', '2026-07-07 21:00:00', 3000000, 'sedang_disewa', NULL, '2026-07-06 20:14:38', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` varchar(20) DEFAULT 'user',
  `no_hp` varchar(20) DEFAULT NULL,
  `membership_tier` varchar(20) DEFAULT 'basic'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nama`, `email`, `password`, `created_at`, `role`, `no_hp`) VALUES
(1, 'fatchur rachman', 'fatchurrachman001@gmail.com', '$2y$10$FfKXrNXR33xGG4Hkt1ZiH.3XQWCzTFewNhuAAjztGGEvxaNLT3kpW', '2026-07-01 06:56:39', 'admin', '08123456789'),
(7, 'user1', 'user1@gmail.com', '$2y$10$LAb0osYqgunw558Nbf6CRO01j6erzyIxATOwt8oxgWl.ubFxiawBW', '2026-07-01 07:18:28', 'user', NULL),
(8, 'ofaturu', 'ofaturu@gmail.com', '$2y$10$M3wrlOyQr/QnizkLgXvzg.4pgD7v4WkykWZeMNjbsz1JEjxamqbiu', '2026-07-01 07:35:14', 'user', '08123456789'),
(9, 'Test Modal', 'testmodal@gmail.com', '$2y$10$MnfgkqOX3CIfjLkIfIsp/OxDbL4svmv11p9cl/esSRznhINv444wq', '2026-07-06 12:12:05', 'user', '081234567890');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `kendaraan`
--
ALTER TABLE `kendaraan`
  ADD PRIMARY KEY (`kode_unik_kendaraan`),
  ADD KEY `id_merk` (`id_merk`);

--
-- Indexes for table `landing_settings`
--
ALTER TABLE `landing_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `maintenance`
--
ALTER TABLE `maintenance`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `merk_kendaraan`
--
ALTER TABLE `merk_kendaraan`
  ADD PRIMARY KEY (`id_merk`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `penyewaan`
--
ALTER TABLE `penyewaan`
  ADD PRIMARY KEY (`id_sewa`),
  ADD KEY `id_user` (`id_user`),
  ADD KEY `kode_unik_kendaraan` (`kode_unik_kendaraan`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `kendaraan`
--
ALTER TABLE `kendaraan`
  MODIFY `kode_unik_kendaraan` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `maintenance`
--
ALTER TABLE `maintenance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `merk_kendaraan`
--
ALTER TABLE `merk_kendaraan`
  MODIFY `id_merk` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `penyewaan`
--
ALTER TABLE `penyewaan`
  MODIFY `id_sewa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `kendaraan`
--
ALTER TABLE `kendaraan`
  ADD CONSTRAINT `kendaraan_ibfk_1` FOREIGN KEY (`id_merk`) REFERENCES `merk_kendaraan` (`id_merk`);

--
-- Constraints for table `penyewaan`
--
ALTER TABLE `penyewaan`
  ADD CONSTRAINT `penyewaan_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `penyewaan_ibfk_2` FOREIGN KEY (`kode_unik_kendaraan`) REFERENCES `kendaraan` (`kode_unik_kendaraan`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;