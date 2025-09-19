-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 08, 2025 at 05:00 AM
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
-- Database: `absence_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `absences`
--

CREATE TABLE `absences` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `shift` varchar(50) NOT NULL,
  `department` varchar(50) DEFAULT NULL,
  `employee_id` varchar(50) DEFAULT NULL,
  `totalEmployees` int(11) NOT NULL,
  `totalAbsent` int(11) NOT NULL,
  `matex` int(11) NOT NULL,
  `avance` int(11) NOT NULL,
  `hrpro` int(11) NOT NULL,
  `leaveAbsent` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `absences`
--

INSERT INTO `absences` (`id`, `date`, `shift`, `department`, `employee_id`, `totalEmployees`, `totalAbsent`, `matex`, `avance`, `hrpro`, `leaveAbsent`) VALUES
(1, '2025-08-19', 'day', NULL, NULL, 682, 10, 3, 2, 4, 1),
(2, '2025-08-20', 'day', NULL, NULL, 682, 13, 3, 4, 4, 2),
(3, '2025-08-19', 'night', NULL, NULL, 682, 14, 3, 6, 5, 0),
(4, '2025-08-20', 'night', NULL, NULL, 682, 7, 3, 2, 1, 1),
(5, '2025-08-21', 'day', NULL, NULL, 682, 11, 2, 3, 5, 1),
(6, '2025-08-21', 'night', NULL, NULL, 682, 10, 0, 4, 5, 1),
(10, '2025-08-22', 'day', NULL, NULL, 682, 11, 1, 5, 3, 2),
(11, '2025-08-22', 'night', NULL, NULL, 682, 7, 0, 3, 2, 2),
(12, '2024-08-18', 'day', NULL, NULL, 645, 11, 2, 5, 3, 1),
(13, '2024-08-18', 'night', NULL, NULL, 645, 18, 3, 6, 8, 1),
(17, '2025-08-23', 'day', NULL, NULL, 682, 7, 0, 3, 4, 0),
(18, '2025-08-23', 'night', NULL, NULL, 682, 8, 0, 5, 3, 0),
(19, '2025-08-24', 'day', NULL, NULL, 682, 5, 1, 3, 1, 0),
(20, '2025-08-24', 'night', NULL, NULL, 682, 1, 0, 0, 1, 0),
(21, '2025-08-26', 'night', NULL, NULL, 682, 8, 1, 4, 3, 0),
(23, '2025-08-26', 'day', NULL, NULL, 682, 4, 1, 2, 1, 0),
(24, '2025-08-27', 'day', NULL, NULL, 682, 10, 2, 4, 4, 0),
(25, '2025-08-27', 'night', NULL, NULL, 682, 3, 0, 3, 0, 0),
(26, '2025-08-28', 'day', NULL, NULL, 682, 7, 1, 4, 2, 0),
(27, '2025-08-28', 'night', NULL, NULL, 682, 9, 0, 5, 4, 0),
(28, '2023-08-17', 'day', NULL, NULL, 651, 12, 3, 5, 4, 0),
(29, '2025-08-29', 'day', NULL, NULL, 682, 8, 0, 4, 4, 0),
(30, '2025-08-29', 'night', NULL, NULL, 682, 5, 0, 3, 2, 0),
(34, '2025-08-30', 'day', NULL, NULL, 682, 9, 0, 4, 5, 0),
(35, '2025-08-30', 'night', NULL, NULL, 682, 5, 0, 3, 2, 0),
(43, '2025-09-01', 'day', NULL, NULL, 682, 9, 0, 5, 4, 0),
(44, '2025-09-01', 'night', NULL, NULL, 682, 7, 0, 3, 4, 0),
(45, '2025-09-02', 'day', NULL, NULL, 682, 5, 0, 3, 2, 0),
(46, '2025-09-02', 'night', NULL, NULL, 682, 4, 0, 2, 2, 0),
(53, '2025-09-03', 'day', NULL, NULL, 682, 14, 1, 3, 4, 6),
(54, '2025-09-03', 'night', NULL, NULL, 682, 9, 0, 2, 2, 5);

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `department` enum('Matex','Avance','HRPro') NOT NULL,
  `shift` enum('day','night') NOT NULL,
  `status` enum('present','absent','leave') NOT NULL,
  `date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `man_power`
--

CREATE TABLE `man_power` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `shift` enum('Day','Night') NOT NULL,
  `matexEmployees` int(11) NOT NULL,
  `matexAbsent` int(11) NOT NULL,
  `avanceEmployees` int(11) NOT NULL,
  `avanceAbsent` int(11) NOT NULL,
  `hrproEmployees` int(11) NOT NULL,
  `hrproAbsent` int(11) NOT NULL,
  `totalEmployees` int(11) NOT NULL,
  `totalAbsent` int(11) NOT NULL,
  `totalPresent` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `absences`
--
ALTER TABLE `absences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `date` (`date`,`shift`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `man_power`
--
ALTER TABLE `man_power`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_record` (`date`,`shift`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `absences`
--
ALTER TABLE `absences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `man_power`
--
ALTER TABLE `man_power`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
