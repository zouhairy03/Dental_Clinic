-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: Jun 05, 2025 at 06:59 PM
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
(1, 'zouhair', 'Zizou_2025@', 'zouhair youssef', '2025-05-26 16:47:20', '2025-06-04 22:58:09', '2025-06-05 19:54:24'),
(2, 'admin2', 'Zouhair_2003', 'Jane Smith', '2025-05-26 16:47:20', '2025-05-30 23:25:12', '2025-05-30 23:46:33'),
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
  `invoice_id` int DEFAULT NULL,
  `notes` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `patient_id`, `dentist_id`, `appointment_date`, `appointment_time`, `treatment_type`, `status`, `invoice_id`, `notes`) VALUES
(4, 4, 4, '2025-06-04', '14:00:00', 'Whitening', 'completed', NULL, 'Patient request.'),
(6, 6, 6, '2025-06-06', '08:15:00', 'Extraction', 'scheduled', NULL, 'Impacted wisdom tooth.'),
(7, 7, 7, '2025-06-07', '13:00:00', 'Braces Adjustment', 'scheduled', NULL, 'Monthly adjustment.'),
(8, 8, 8, '2025-06-08', '16:45:00', 'Filling', 'scheduled', NULL, 'Cavity reported.'),
(9, 9, 9, '2025-06-09', '10:30:00', 'Cleaning', 'scheduled', NULL, 'Tartar buildup.'),
(10, 10, 10, '2025-06-10', '12:00:00', 'Crown', 'scheduled', NULL, 'Broken molar.'),
(12, 7, NULL, '2025-09-27', '14:40:00', 'test', 'completed', NULL, 'test2\r\n'),
(14, 3, NULL, '2025-05-28', '11:40:00', 'test', 'completed', NULL, 'test'),
(15, 6, NULL, '2025-05-29', '13:40:00', 'good', 'completed', NULL, 'test'),
(16, 8, NULL, '2025-05-30', '23:50:00', 'test', 'completed', NULL, 'ggdgd'),
(17, 11, NULL, '2025-05-29', '03:50:00', 'goo', 'completed', NULL, 'hello world'),
(18, 14, NULL, '2025-05-29', '00:30:00', 'good', 'scheduled', NULL, 'GOOD'),
(19, 19, NULL, '2025-05-06', '12:40:00', 'hello', 'scheduled', NULL, 'world'),
(20, 5, NULL, '2025-06-05', '19:58:00', 'whitening teeth', 'scheduled', NULL, 'the patient had already started with another doctor');

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
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int NOT NULL,
  `patient_id` int NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `tax_amount` decimal(10,2) DEFAULT '0.00',
  `discount` decimal(10,2) DEFAULT '0.00',
  `status` enum('paid','pending','overdue','partial','cancelled') DEFAULT 'pending',
  `payment_terms` varchar(100) DEFAULT 'Due on receipt',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `patient_id`, `invoice_date`, `due_date`, `total_amount`, `tax_amount`, `discount`, `status`, `payment_terms`, `notes`, `created_at`, `updated_at`) VALUES
(2, 11, '2025-05-30', '2025-06-06', 0.00, 0.00, 0.00, 'pending', 'Due on receipt', '', '2025-05-30 01:23:35', '2025-05-30 01:23:35'),
(4, 11, '2025-06-01', '2025-06-08', 0.00, 0.00, 0.00, 'pending', 'Due on receipt', 'blanchiment', '2025-06-01 22:18:11', '2025-06-01 22:18:11');

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `id` int NOT NULL,
  `invoice_id` int NOT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` int DEFAULT '1',
  `unit_price` decimal(10,2) NOT NULL,
  `tax_rate` decimal(5,2) DEFAULT '0.00',
  `discount` decimal(5,2) DEFAULT '0.00',
  `line_total` decimal(10,2) GENERATED ALWAYS AS (((`quantity` * `unit_price`) * (1 - (`discount` / 100)))) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `cna` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `working_type` enum('student','employed','self-employed','unemployed') NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `age` int NOT NULL
) ;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `full_name`, `phone`, `cna`, `address`, `working_type`, `created_at`, `age`) VALUES
(3, 'Charlie Puth', '5551234567', 'EH9910', 'street nil ', 'unemployed', '2025-05-26 16:47:20', 35),
(4, 'Diana Ross', '7778889999', NULL, NULL, 'self-employed', '2025-05-26 16:47:20', 41),
(5, 'Ethan Hunt', '6665554444', NULL, NULL, 'employed', '2025-05-26 16:47:20', 37),
(6, 'Fiona Apple', '2223334444', NULL, NULL, 'student', '2025-05-26 16:47:20', 20),
(7, 'George Martin', '9990001111', NULL, NULL, 'employed', '2025-05-26 16:47:20', 48),
(8, 'Hannah Lee', '8887776666', NULL, NULL, 'unemployed', '2025-05-26 16:47:20', 26),
(9, 'Ian Fleming', '4445556666', NULL, NULL, 'self-employed', '2025-05-26 16:47:20', 55),
(10, 'Julia Roberts', '1112223333', NULL, NULL, 'employed', '2025-05-26 16:47:20', 31),
(11, 'zouhair youssef', '0688000980', NULL, NULL, 'student', '2025-05-26 17:27:05', 21),
(12, 'salma yi', '92328382', NULL, NULL, 'student', '2025-05-28 22:55:17', 82),
(14, 'salma yi', '000000', NULL, NULL, 'student', '2025-05-28 22:55:41', 82),
(15, 'test', '723727277', NULL, NULL, 'student', '2025-05-28 23:21:40', 90),
(16, 'soumia', '0691603104', NULL, NULL, 'employed', '2025-05-29 16:49:56', 40),
(17, 'soumia', '82882838', NULL, 'sidi Othman casa', 'employed', '2025-05-29 19:15:00', 40),
(18, 'sara', '282888', NULL, 'Oulfa', 'self-employed', '2025-05-29 23:51:47', 45),
(19, 'me', '2773772', 'Ba6262', 'alfa', 'student', '2025-05-30 00:02:52', 28),
(20, 'djsjfjs', '932939', 'Ba8191', 'suds', 'student', '2025-05-30 00:17:38', 99);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int NOT NULL,
  `patient_id` int NOT NULL,
  `appointment_id` int DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_time` time DEFAULT NULL,
  `payment_method` enum('cash','credit_card','debit_card','bank_transfer','check') NOT NULL,
  `status` enum('completed','pending','failed','refunded') DEFAULT 'completed',
  `transaction_id` varchar(100) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `patient_id`, `appointment_id`, `amount`, `payment_date`, `payment_time`, `payment_method`, `status`, `transaction_id`, `notes`, `created_at`, `updated_at`) VALUES
(16, 6, 6, 500.00, '2025-06-04', '23:00:47', 'cash', 'completed', 'XXXXXX', 'hello world', '2025-06-04 22:00:47', '2025-06-04 22:00:47');

-- --------------------------------------------------------

--
-- Table structure for table `payment_invoice`
--

CREATE TABLE `payment_invoice` (
  `payment_id` int NOT NULL,
  `invoice_id` int NOT NULL,
  `amount_applied` decimal(10,2) NOT NULL,
  `applied_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  ADD KEY `dentist_id` (`dentist_id`),
  ADD KEY `fk_appointment_invoice` (`invoice_id`);

--
-- Indexes for table `dentists`
--
ALTER TABLE `dentists`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone` (`phone`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `appointment_id` (`appointment_id`);

--
-- Indexes for table `payment_invoice`
--
ALTER TABLE `payment_invoice`
  ADD PRIMARY KEY (`payment_id`,`invoice_id`),
  ADD KEY `invoice_id` (`invoice_id`);

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `dentists`
--
ALTER TABLE `dentists`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`dentist_id`) REFERENCES `dentists` (`id`),
  ADD CONSTRAINT `fk_appointment_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payment_invoice`
--
ALTER TABLE `payment_invoice`
  ADD CONSTRAINT `payment_invoice_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payment_invoice_ibfk_2` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
