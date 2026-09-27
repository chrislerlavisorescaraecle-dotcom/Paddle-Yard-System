-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 22, 2026 at 03:34 PM
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
-- Database: `paddleyard`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(254) NOT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('customer','staff','admin') NOT NULL DEFAULT 'customer',
  `usertype_id` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `account_status` enum('active','suspended','disabled') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `banking_payment_qrs`
--

CREATE TABLE `banking_payment_qrs` (
  `qr_id` bigint(20) UNSIGNED NOT NULL,
  `qr_image_path` varchar(500) NOT NULL,
  `qr_account_name` varchar(150) NOT NULL,
  `qr_number` varchar(100) NOT NULL,
  `date_uploaded` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cancellations`
--

CREATE TABLE `cancellations` (
  `cancellation_id` bigint(20) UNSIGNED NOT NULL,
  `reservation_id` bigint(20) UNSIGNED NOT NULL,
  `reason` varchar(1000) NOT NULL,
  `request_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `cutoff_status` enum('eligible','late','not_eligible') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `courts`
--

CREATE TABLE `courts` (
  `court_id` bigint(20) UNSIGNED NOT NULL,
  `court_name` varchar(100) NOT NULL,
  `location` varchar(255) NOT NULL,
  `court_status` enum('available','maintenance','inactive') NOT NULL DEFAULT 'available',
  `hourly_rate` decimal(10,2) NOT NULL,
  `court_type` enum('indoor','outdoor') NOT NULL,
  `court_photo_path` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `paddles`
--

CREATE TABLE `paddles` (
  `paddle_id` bigint(20) UNSIGNED NOT NULL,
  `paddle_status` enum('available','rented','maintenance','retired') NOT NULL DEFAULT 'available',
  `rental_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `paddle_rentals`
--

CREATE TABLE `paddle_rentals` (
  `paddle_rental_id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `paddle_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `rental_status` enum('requested','approved','checked_out','returned','cancelled','overdue') NOT NULL DEFAULT 'requested',
  `rented_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `returned_at` timestamp NULL DEFAULT NULL
) ;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` bigint(20) UNSIGNED NOT NULL,
  `reservation_id` bigint(20) UNSIGNED NOT NULL,
  `qr_id` bigint(20) UNSIGNED DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','bank_qr','card','ewallet') NOT NULL,
  `payment_screenshot_path` varchar(500) DEFAULT NULL,
  `payment_status` enum('pending','submitted','verified','rejected','refunded') NOT NULL DEFAULT 'pending',
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `report_id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `report_type` varchar(50) NOT NULL,
  `date_generated` timestamp NOT NULL DEFAULT current_timestamp(),
  `range_start` date NOT NULL,
  `range_end` date NOT NULL
) ;

-- --------------------------------------------------------

--
-- Table structure for table `reschedules`
--

CREATE TABLE `reschedules` (
  `reschedule_id` bigint(20) UNSIGNED NOT NULL,
  `reservation_id` bigint(20) UNSIGNED NOT NULL,
  `new_date` date NOT NULL,
  `new_start_time` time NOT NULL,
  `new_end_time` time NOT NULL,
  `request_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `cutoff_status` enum('eligible','late','not_eligible') NOT NULL,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending'
) ;

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `reservation_id` bigint(20) UNSIGNED NOT NULL,
  `court_id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `staff_account_id` bigint(20) UNSIGNED DEFAULT NULL,
  `reservation_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `reservation_status` enum('pending','confirmed','checked_in','cancelled','completed','no_show') NOT NULL DEFAULT 'pending',
  `qr_code` char(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `setting_name` varchar(100) NOT NULL,
  `setting_value` varchar(1000) NOT NULL,
  `date_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_types`
--

CREATE TABLE `user_types` (
  `usertype_id` tinyint(3) UNSIGNED NOT NULL,
  `usertype_name` varchar(30) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_types`
--

INSERT INTO `user_types` (`usertype_id`, `usertype_name`, `created_at`) VALUES
(1, 'customer', '2026-09-22 12:58:30'),
(2, 'staff', '2026-09-22 12:58:30'),
(3, 'admin', '2026-09-22 12:58:30');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`account_id`),
  ADD UNIQUE KEY `uq_accounts_email` (`email`),
  ADD KEY `idx_accounts_usertype` (`usertype_id`),
  ADD KEY `idx_accounts_status` (`account_status`);

--
-- Indexes for table `banking_payment_qrs`
--
ALTER TABLE `banking_payment_qrs`
  ADD PRIMARY KEY (`qr_id`),
  ADD KEY `idx_payment_qrs_active` (`is_active`,`date_uploaded`);

--
-- Indexes for table `cancellations`
--
ALTER TABLE `cancellations`
  ADD PRIMARY KEY (`cancellation_id`),
  ADD UNIQUE KEY `uq_cancellations_reservation` (`reservation_id`);

--
-- Indexes for table `courts`
--
ALTER TABLE `courts`
  ADD PRIMARY KEY (`court_id`),
  ADD UNIQUE KEY `uq_courts_name_location` (`court_name`,`location`),
  ADD KEY `idx_courts_status` (`court_status`);

--
-- Indexes for table `paddles`
--
ALTER TABLE `paddles`
  ADD PRIMARY KEY (`paddle_id`),
  ADD KEY `idx_paddles_status` (`paddle_status`);

--
-- Indexes for table `paddle_rentals`
--
ALTER TABLE `paddle_rentals`
  ADD PRIMARY KEY (`paddle_rental_id`),
  ADD KEY `idx_paddle_rentals_account` (`account_id`,`rental_status`),
  ADD KEY `idx_paddle_rentals_paddle` (`paddle_id`,`rental_status`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD UNIQUE KEY `uq_payments_reservation` (`reservation_id`),
  ADD KEY `idx_payments_qr` (`qr_id`),
  ADD KEY `idx_payments_status` (`payment_status`,`payment_date`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `idx_reports_account_date` (`account_id`,`date_generated`);

--
-- Indexes for table `reschedules`
--
ALTER TABLE `reschedules`
  ADD PRIMARY KEY (`reschedule_id`),
  ADD KEY `idx_reschedules_reservation_status` (`reservation_id`,`status`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`reservation_id`),
  ADD UNIQUE KEY `uq_reservations_qr_code` (`qr_code`),
  ADD KEY `idx_reservations_court_schedule` (`court_id`,`reservation_date`,`start_time`,`end_time`),
  ADD KEY `idx_reservations_account_date` (`account_id`,`reservation_date`),
  ADD KEY `idx_reservations_staff` (`staff_account_id`),
  ADD KEY `idx_reservations_status` (`reservation_status`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `uq_system_settings_name` (`setting_name`),
  ADD KEY `idx_system_settings_account` (`account_id`);

--
-- Indexes for table `user_types`
--
ALTER TABLE `user_types`
  ADD PRIMARY KEY (`usertype_id`),
  ADD UNIQUE KEY `uq_user_types_name` (`usertype_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `account_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `banking_payment_qrs`
--
ALTER TABLE `banking_payment_qrs`
  MODIFY `qr_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cancellations`
--
ALTER TABLE `cancellations`
  MODIFY `cancellation_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `courts`
--
ALTER TABLE `courts`
  MODIFY `court_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `paddles`
--
ALTER TABLE `paddles`
  MODIFY `paddle_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `paddle_rentals`
--
ALTER TABLE `paddle_rentals`
  MODIFY `paddle_rental_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `report_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reschedules`
--
ALTER TABLE `reschedules`
  MODIFY `reschedule_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `reservation_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `setting_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_types`
--
ALTER TABLE `user_types`
  MODIFY `usertype_id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accounts`
--
ALTER TABLE `accounts`
  ADD CONSTRAINT `fk_accounts_usertype` FOREIGN KEY (`usertype_id`) REFERENCES `user_types` (`usertype_id`);

--
-- Constraints for table `cancellations`
--
ALTER TABLE `cancellations`
  ADD CONSTRAINT `fk_cancellations_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`);

--
-- Constraints for table `paddle_rentals`
--
ALTER TABLE `paddle_rentals`
  ADD CONSTRAINT `fk_paddle_rentals_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`account_id`),
  ADD CONSTRAINT `fk_paddle_rentals_paddle` FOREIGN KEY (`paddle_id`) REFERENCES `paddles` (`paddle_id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_qr` FOREIGN KEY (`qr_id`) REFERENCES `banking_payment_qrs` (`qr_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_payments_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`);

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `fk_reports_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`account_id`);

--
-- Constraints for table `reschedules`
--
ALTER TABLE `reschedules`
  ADD CONSTRAINT `fk_reschedules_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`);

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `fk_reservations_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`account_id`),
  ADD CONSTRAINT `fk_reservations_court` FOREIGN KEY (`court_id`) REFERENCES `courts` (`court_id`),
  ADD CONSTRAINT `fk_reservations_staff` FOREIGN KEY (`staff_account_id`) REFERENCES `accounts` (`account_id`) ON DELETE SET NULL;

--
-- Constraints for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD CONSTRAINT `fk_system_settings_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`account_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
