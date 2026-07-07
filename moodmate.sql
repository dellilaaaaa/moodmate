-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 24, 2026 at 11:16 AM
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
-- Database: `moodmate`
--

-- --------------------------------------------------------

--
-- Table structure for table `hasil_tes`
--

CREATE TABLE `hasil_tes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `skor` int(11) NOT NULL,
  `tanggal_tes` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hasil_tes`
--

INSERT INTO `hasil_tes` (`id`, `user_id`, `skor`, `tanggal_tes`) VALUES
(2, 7, 58, '2026-06-24');

-- --------------------------------------------------------

--
-- Table structure for table `riwayat_mood`
--

CREATE TABLE `riwayat_mood` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 1,
  `mood` varchar(50) NOT NULL,
  `skor` int(11) NOT NULL,
  `waktu` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `riwayat_mood`
--

INSERT INTO `riwayat_mood` (`id`, `user_id`, `mood`, `skor`, `waktu`) VALUES
(1, 4, 'Biasa', 54, '2026-06-24 14:09:22'),
(2, 5, 'Lelah', 60, '2026-06-24 14:15:58'),
(3, 6, 'Sedih', 90, '2026-06-24 14:22:29'),
(4, 7, 'Biasa', 40, '2026-06-24 14:27:05');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_premium` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `is_premium`) VALUES
(1, 'buddy', 'buddy@moodmate.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
(2, 'yunda', 'yunda@gmail.com', '$2y$10$UEJCo3zUu55RVLhPMKAgtOCRJttVVJ2B8BloKmi69PA9J7vzndRxy', 1),
(3, 'nisa', 'nisa@gmail.com', '$2y$10$hwUVMHCc9AU/r2k07GXZae.uHeQvuZRRLJotXU0XcBNCnuVG4iLea', 1),
(4, 'kai', 'kai@gmail.com', '$2y$10$Umu2qzhH.CvfQkwdl0PgM.1GIwFsNeUKO/Eh0CTw2OrN6iVxGXikO', 1),
(5, 'dellila', 'dellila@gmail.com', '$2y$10$k0NpTJJEYu5DJfuE9x2xKexaF42x.YxnRAsxvEow7NjxtcxBWvKj.', 1),
(6, 'excel', 'excel@gmail.com', '$2y$10$KK//jSQdza3p4qMfCj5XduK7xVQRddCiuzwir2alkRSsJ9eUO3vN.', 1),
(7, 'skay', 'skay@gmail.com', '$2y$10$W2V.r4pFjvzuJPvvJEZwOe5CdB7NJYrEATB7h424547lhYOG7f4W2', 1),
(8, 'nana', 'nana@gmail.com', '$2y$10$dVoAqjM7R/bMEahOzU3i0.Ox3hr6VPuJn0bBXWJrsFK.t41xbBjVO', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `hasil_tes`
--
ALTER TABLE `hasil_tes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `riwayat_mood`
--
ALTER TABLE `riwayat_mood`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `hasil_tes`
--
ALTER TABLE `hasil_tes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `riwayat_mood`
--
ALTER TABLE `riwayat_mood`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `hasil_tes`
--
ALTER TABLE `hasil_tes`
  ADD CONSTRAINT `hasil_tes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
