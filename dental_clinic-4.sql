-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: May 27, 2025 at 11:36 PM
-- Server version: 8.0.40
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dental_clinic`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_login` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password_hash`, `name`, `created_at`, `last_login`, `updated_at`) VALUES
(1, 'zouhair', 'zizou', 'zouhair youssef', '2025-05-26 16:47:20', '2025-05-28 00:32:07', '2025-05-26 23:03:42'),
(2, 'admin2', 'hash2', 'Jane Smith', '2025-05-26 16:47:20', NULL, NULL),
(3, 'admin3', 'hash3', 'Emily Davis', '2025-05-26 16:47:20', NULL, NULL),
(4, 'admin4', 'hash4', 'Michael Johnson', '2025-05-26 16:47:20', NULL, NULL),
(5, 'admin5', 'hash5', 'Linda Brown', '2025-05-26 16:47:20', NULL, NULL),
(6, 'admin6', 'hash6', 'Robert Miller', '2025-05-26 16:47:20', NULL, NULL),
(7, 'admin7', 'hash7', 'Patricia Wilson', '2025-05-26 16:47:20', NULL, NULL),
(8, 'admin8', 'hash8', 'James Taylor', '2025-05-26 16:47:20', NULL, NULL),
(9, 'admin9', 'hash9', 'Barbara Moore', '2025-05-26 16:47:20', NULL, NULL),
(10, 'admin10', 'hash10', 'David Anderson', '2025-05-26 16:47:20', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int NOT NULL,
  `patient_id` int DEFAULT NULL,
  `dentist_id` int DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `treatment_type` varchar(100) DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled') DEFAULT 'scheduled',
  `notes` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `patient_id`, `dentist_id`, `appointment_date`, `appointment_time`, `treatment_type`, `status`, `notes`) VALUES
(1, 1, 1, '2025-06-01', '10:00:00', 'Cleaning', 'cancelled', 'Regular cleaning.'),
(2, 2, 2, '2025-06-02', '11:30:00', 'Filling', 'scheduled', 'Minor cavity.'),
(3, 3, 3, '2025-06-03', '09:45:00', 'Root Canal', 'scheduled', 'Tooth sensitivity.'),
(4, 4, 4, '2025-06-04', '14:00:00', 'Whitening', 'scheduled', 'Patient request.'),
(5, 5, 5, '2025-06-05', '15:30:00', 'Checkup', 'scheduled', 'Routine check.'),
(6, 6, 6, '2025-06-06', '08:15:00', 'Extraction', 'scheduled', 'Impacted wisdom tooth.'),
(7, 7, 7, '2025-06-07', '13:00:00', 'Braces Adjustment', 'scheduled', 'Monthly adjustment.'),
(8, 8, 8, '2025-06-08', '16:45:00', 'Filling', 'scheduled', 'Cavity reported.'),
(9, 9, 9, '2025-06-09', '10:30:00', 'Cleaning', 'scheduled', 'Tartar buildup.'),
(10, 10, 10, '2025-06-10', '12:00:00', 'Crown', 'scheduled', 'Broken molar.'),
(11, 11, NULL, '2025-05-27', '12:40:00', 'remove cavity', 'scheduled', 'the patient already has cavity for second time'),
(12, 7, NULL, '2025-05-27', '10:40:00', 'test', 'scheduled', 'hshsf');

-- --------------------------------------------------------

--
-- Table structure for table `dentists`
--

CREATE TABLE `dentists` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `working_hours` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `dentists`
--

INSERT INTO `dentists` (`id`, `name`, `specialization`, `working_hours`) VALUES
(1, 'Dr. Alice Smith', 'Orthodontist', '09:00-17:00'),
(2, 'Dr. Brian Johnson', 'Endodontist', '10:00-18:00'),
(3, 'Dr. Cathy Brown', 'General Dentist', '08:00-16:00'),
(4, 'Dr. Daniel Lee', 'Periodontist', '11:00-19:00'),
(5, 'Dr. Ella White', 'Pediatric Dentist', '09:30-17:30'),
(6, 'Dr. Frank Black', 'Oral Surgeon', '07:00-15:00'),
(7, 'Dr. Grace Green', 'Prosthodontist', '08:30-16:30'),
(8, 'Dr. Henry Scott', 'Cosmetic Dentist', '10:30-18:30'),
(9, 'Dr. Irene King', 'General Dentist', '09:00-17:00'),
(10, 'Dr. Jack Hall', 'Orthodontist', '12:00-20:00');

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `working_type` enum('student','employed','self-employed','unemployed') NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `age` int NOT NULL
) ;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `full_name`, `phone`, `working_type`, `created_at`, `age`) VALUES
(1, 'Alice Cooper', '1234567890', 'employed', '2025-05-26 16:47:20', 29),
(2, 'Bob Marley', '0987654321', 'student', '2025-05-26 16:47:20', 22),
(3, 'Charlie Puth', '5551234567', 'unemployed', '2025-05-26 16:47:20', 35),
(4, 'Diana Ross', '7778889999', 'self-employed', '2025-05-26 16:47:20', 41),
(5, 'Ethan Hunt', '6665554444', 'employed', '2025-05-26 16:47:20', 37),
(6, 'Fiona Apple', '2223334444', 'student', '2025-05-26 16:47:20', 20),
(7, 'George Martin', '9990001111', 'employed', '2025-05-26 16:47:20', 48),
(8, 'Hannah Lee', '8887776666', 'unemployed', '2025-05-26 16:47:20', 26),
(9, 'Ian Fleming', '4445556666', 'self-employed', '2025-05-26 16:47:20', 55),
(10, 'Julia Roberts', '1112223333', 'employed', '2025-05-26 16:47:20', 31),
(11, 'zouhair youssef', '0688000980', 'student', '2025-05-26 17:27:05', 21);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `dentist_id` (`dentist_id`);

--
-- Indexes for table `dentists`
--
ALTER TABLE `dentists`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone` (`phone`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `dentists`
--
ALTER TABLE `dentists`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`dentist_id`) REFERENCES `dentists` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
