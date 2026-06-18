-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 18, 2026 at 09:05 AM
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
  `merk_kendaraan` varchar(50) NOT NULL,
  `nama_kendaraan` varchar(50) DEFAULT NULL,
  `jenis_kendaraan` enum('roda 2','roda 4') DEFAULT NULL,
  `harga_per_hari` int(10) DEFAULT NULL,
  `gambar` longblob DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kendaraan`
--

INSERT INTO `kendaraan` (`kode_unik_kendaraan`, `merk_kendaraan`, `nama_kendaraan`, `jenis_kendaraan`, `harga_per_hari`, `gambar`) VALUES
(1, 'Honda', 'Honda Vario 150', 'roda 2', 75000, NULL),
(2, 'Toyota', 'Toyota Avanza', 'roda 4', 300000, NULL),
(3, 'Yamaha', 'Yamaha NMAX', 'roda 2', 100000, NULL),
(4, 'Honda', 'Honda Brio', 'roda 4', 250000, NULL),
(5, 'Mitsubishi', 'Mitsubishi Xpander', 'roda 4', 350000, NULL),
(6, 'Suzuki', 'Suzuki Ertiga', 'roda 4', 280000, NULL),
(7, 'Honda', 'Honda Beat', 'roda 2', 60000, NULL),
(8, 'Daihatsu', 'Daihatsu Ayla', 'roda 4', 200000, NULL),
(9, 'Kawasaki', 'Kawasaki KLX 150', 'roda 2', 120000, NULL),
(10, 'Toyota', 'Toyota Innova Reborn', 'roda 4', 450000, NULL),
(11, 'Toyota', 'Toyota Fortuner', 'roda 4', 600000, NULL),
(12, 'Toyota', 'Toyota Yaris', 'roda 4', 280000, NULL),
(13, 'Toyota', 'Toyota Agya', 'roda 4', 200000, NULL),
(14, 'Toyota', 'Toyota Calya', 'roda 4', 250000, NULL),
(15, 'Toyota', 'Toyota Rush', 'roda 4', 300000, NULL),
(16, 'Toyota', 'Toyota Alphard', 'roda 4', 1500000, NULL),
(17, 'Toyota', 'Toyota Vios', 'roda 4', 350000, NULL),
(18, 'Toyota', 'Toyota Raize', 'roda 4', 320000, NULL),
(19, 'Toyota', 'Toyota Hilux', 'roda 4', 500000, NULL),
(20, 'Toyota', 'Toyota Camry', 'roda 4', 800000, NULL),
(21, 'Toyota', 'Toyota Supra MK 4', 'roda 4', 1000000, NULL),
(22, 'Yamaha', 'Yamaha Aerox 155', 'roda 2', 90000, NULL);

--
-- Triggers `kendaraan`
--
DELIMITER $$
CREATE TRIGGER `otomatis_isi_merk` BEFORE INSERT ON `kendaraan` FOR EACH ROW BEGIN
    -- Jika kolom merk_kendaraan dari website kosong, otomatis ambil dari kata pertama nama_kendaraan
    IF NEW.merk_kendaraan IS NULL OR NEW.merk_kendaraan = '' THEN
        SET NEW.merk_kendaraan = SUBSTRING_INDEX(NEW.nama_kendaraan, ' ', 1);
    END IF;
END
$$
DELIMITER ;

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
(8, 2, 20, '2026-06-18 08:52:00', '0000-00-00 00:00:00', 800000, 'booking');

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
(3, 'Test Operator Updated', 'testop@ftrans.com', '$2y$10$CksAaIy3ywj6K/jz.6D8pu0ZUk0L5oCKmAHDX.8o3ax7uwCRFAYnu', '2026-06-04 07:40:37');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `kendaraan`
--
ALTER TABLE `kendaraan`
  ADD PRIMARY KEY (`kode_unik_kendaraan`);

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
-- AUTO_INCREMENT for table `penyewaan`
--
ALTER TABLE `penyewaan`
  MODIFY `id_sewa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

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
