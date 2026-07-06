-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 05, 2026 at 10:53 AM
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
(1, 14, 'Audi A8', 'roda 4', 1000000, 0x61382e6a7067, 'Matic', '5 Seater', 'Bensin', 'tersedia', 1, 'Hitam'),
(2, 12, 'BMW M4', 'roda 4', 750000, 0x6d342e77656270, 'Matic', '5 Seater', 'Bensin', 'tersedia', 1, 'Hitam'),
(3, 1, 'Honda NSX', 'roda 4', 2500000, 0x6e73782e6a7067, 'Matic', '5 Seater', 'Bensin', 'disewa', 1, 'Hitam'),
(4, 2, 'Toyota GR Supra', 'roda 4', 3000000, 0x67725f73757072612e6a7067, 'Matic', '5 Seater', 'Bensin', 'tersedia', 1, 'Hitam'),
(5, 8, 'Mazda RX 7', 'roda 4', 2000000, 0x72785f372e6a7067, 'Matic', '5 Seater', 'Bensin', 'tersedia', 1, 'Hitam'),
(7, 6, 'Mitsubishi Lancer Evo X', 'roda 4', 1000000, 0x6c616e6365725f65766f5f782e77656270, 'Matic', '5 Seater', 'Bensin', 'tersedia', 1, 'Hitam'),
(8, 7, 'Nissan GTR R35', 'roda 4', 3500000, 0x6774725f7233352e6a7067, 'Matic', '5 Seater', 'Bensin', 'disewa', 1, 'Hitam'),
(9, 18, 'Yamaha R1M', 'roda 2', 500000, 0x72316d2e6a7067, 'Matic', '5 Seater', 'Bensin', 'tersedia', 1, 'Hitam');

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
  `waktu_bayar` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `penyewaan`
--

INSERT INTO `penyewaan` (`id_sewa`, `id_user`, `kode_unik_kendaraan`, `tanggal_sewa`, `tanggal_kembali`, `total_biaya`, `status`, `bukti_pembayaran`, `waktu_bayar`) VALUES
(31, 1, 3, '2026-07-05 10:23:00', '2026-07-06 10:23:00', 2500000, 'sedang_disewa', NULL, NULL),
(32, 8, 1, '2026-07-05 10:24:00', '2026-07-06 10:24:00', 1000000, 'sedang_disewa', 'bukti pembayaran_ofaturu_32.png', '2026-07-05 15:24:47'),
(33, 8, 3, '2026-07-05 10:29:00', '2026-07-06 10:29:00', 2500000, 'sedang_disewa', 'bukti pembayaran_ofaturu_33.jpg', '2026-07-05 15:29:50'),
(35, 8, 8, '2026-07-05 10:44:00', '2026-07-06 10:44:00', 3500000, 'sedang_disewa', 'bukti pembayaran_ofaturu_35.jpg', '2026-07-05 15:44:30'),
(37, 8, 1, '2026-07-05 10:48:00', '2026-07-06 10:48:00', 1000000, 'selesai', 'bukti pembayaran_ofaturu_37.jpg', '2026-07-05 15:49:04');

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
  `role` varchar(20) DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nama`, `email`, `password`, `created_at`, `role`) VALUES
(1, 'fatchur rachman', 'fatchurrachman001@gmail.com', '$2y$10$c/Sau5RgcMCS3fWMP8Qz/OEpfoRayRnZ8WDzQx8bU0lhZwQ2EJ1fC', '2026-07-01 06:56:39', 'admin'),
(7, 'user1', 'user1@gmail.com', '$2y$10$LAb0osYqgunw558Nbf6CRO01j6erzyIxATOwt8oxgWl.ubFxiawBW', '2026-07-01 07:18:28', 'user'),
(8, 'ofaturu', 'ofaturu@gmail.com', '$2y$10$.fc4i0Ztseij9aJv3wPf0.NJ8ZnfTrak3zGK9v262TVa0RbceHp1S', '2026-07-01 07:35:14', 'user');

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
-- Indexes for table `merk_kendaraan`
--
ALTER TABLE `merk_kendaraan`
  ADD PRIMARY KEY (`id_merk`);

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
-- AUTO_INCREMENT for table `merk_kendaraan`
--
ALTER TABLE `merk_kendaraan`
  MODIFY `id_merk` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `penyewaan`
--
ALTER TABLE `penyewaan`
  MODIFY `id_sewa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

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
