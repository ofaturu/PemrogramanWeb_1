-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 25, 2026 at 09:56 AM
-- Server version: 10.4.27-MariaDB
-- PHP Version: 8.1.12

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
  `gambar` longblob DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kendaraan`
--

INSERT INTO `kendaraan` (`kode_unik_kendaraan`, `id_merk`, `nama_kendaraan`, `jenis_kendaraan`, `harga_per_hari`, `gambar`) VALUES
(1, 14, 'Audi A8', 'roda 4', 500000, NULL),
(2, 3, 'Daihatsu Xenia', 'roda 4', 350000, NULL),
(3, 3, 'Daihatsu Xenia New 2025', 'roda 4', 400000, NULL),
(5, 4, 'Kia Kia', 'roda 4', 300000, NULL),
(6, 8, 'Mazda Mazda 3', 'roda 4', 700000, NULL),
(7, 6, 'Mitsubishi Galant', 'roda 4', 250000, NULL);

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
(20, 'Vespa');

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
  `status` enum('booking','sedang_disewa','selesai','dibatalkan') DEFAULT 'booking'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `penyewaan`
--

INSERT INTO `penyewaan` (`id_sewa`, `id_user`, `kode_unik_kendaraan`, `tanggal_sewa`, `tanggal_kembali`, `total_biaya`, `status`) VALUES
(13, 1, 6, '2026-06-25 09:05:00', '2026-06-26 09:05:00', 700000, 'sedang_disewa'),
(17, 2, 1, '2026-06-25 09:13:00', '2026-06-26 09:13:00', 500000, 'booking'),
(18, 2, 1, '2026-06-25 09:05:00', '2026-06-26 09:05:00', 500000, 'booking');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nama`, `email`, `password`, `created_at`) VALUES
(1, 'fatchur', 'a@gmail.com', '$2y$10$T2irbai2z4cqvHqXZwL5qumnXUoZ6nIVLaZyr18rxPtjpvgjPS.UW', '2026-05-08 02:37:05'),
(2, 'bagas', 'b@gmail.com', '$2y$10$NVpgcRk3ZdZX1Wbi4iHXR.Le.BW4F1GHr6Mhxzuf6Rxiyy/evGldC', '2026-05-13 06:33:31'),
(3, 'Test Operator Updated', 'testop@ftrans.com', '$2y$10$CksAaIy3ywj6K/jz.6D8pu0ZUk0L5oCKmAHDX.8o3ax7uwCRFAYnu', '2026-06-04 07:40:37'),
(4, 'Test User', 'test@example.com', '$2y$10$voyGYVCdrXnwncEfHK6v4uuW7EbgSgUhJzE18xenxkz/wsOdEAwXK', '2026-06-18 07:26:53'),
(5, 'Test User', 'test2@example.com', '$2y$10$2cMOaiGO3uw7WmgE3blA2..Xwt9t/1rAdldhguDIlLTLP.O1GmCTm', '2026-06-25 07:38:27');

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
-- AUTO_INCREMENT for table `merk_kendaraan`
--
ALTER TABLE `merk_kendaraan`
  MODIFY `id_merk` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `penyewaan`
--
ALTER TABLE `penyewaan`
  MODIFY `id_sewa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
