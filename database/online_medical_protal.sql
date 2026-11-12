-- Reconstructed from the PHP queries; this is not the original database dump.
-- Local development only. No existing tables or data are removed.
CREATE DATABASE IF NOT EXISTS `online_medical_protal`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `online_medical_protal`;

CREATE TABLE IF NOT EXISTS `users` (
  `p_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id` INT UNSIGNED GENERATED ALWAYS AS (`p_id`) VIRTUAL,
  `p_name` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) GENERATED ALWAYS AS (`p_name`) VIRTUAL,
  `username` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `phone_number` VARCHAR(30) NOT NULL DEFAULT '',
  `location` VARCHAR(100) NOT NULL DEFAULT '',
  `special_note` TEXT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` VARCHAR(20) NOT NULL DEFAULT 'user',
  PRIMARY KEY (`p_id`),
  UNIQUE KEY `users_username` (`username`),
  UNIQUE KEY `users_email` (`email`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `admin` (
  `a_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id` INT UNSIGNED GENERATED ALWAYS AS (`a_id`) VIRTUAL,
  `a_name` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) GENERATED ALWAYS AS (`a_name`) VIRTUAL,
  `username` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` VARCHAR(20) NOT NULL DEFAULT 'admin',
  PRIMARY KEY (`a_id`),
  UNIQUE KEY `admin_username` (`username`),
  UNIQUE KEY `admin_email` (`email`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `doctor` (
  `d_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id` INT UNSIGNED GENERATED ALWAYS AS (`d_id`) VIRTUAL,
  `d_name` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) GENERATED ALWAYS AS (`d_name`) VIRTUAL,
  `username` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `doctor_type` VARCHAR(100) NOT NULL DEFAULT '',
  `location` VARCHAR(100) NOT NULL DEFAULT '',
  `gender` VARCHAR(20) NOT NULL DEFAULT '',
  `price_per_appointment` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `description` TEXT NULL,
  `role` VARCHAR(20) NOT NULL DEFAULT 'doctor',
  PRIMARY KEY (`d_id`),
  UNIQUE KEY `doctor_username` (`username`),
  UNIQUE KEY `doctor_email` (`email`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `appointment` (
  `a_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `d_id` INT UNSIGNED NOT NULL,
  `p_id` INT UNSIGNED NOT NULL,
  `appointment_name` VARCHAR(255) NOT NULL,
  `phone_number` VARCHAR(30) NOT NULL DEFAULT '',
  `session` VARCHAR(50) NOT NULL,
  `date` DATE NOT NULL,
  PRIMARY KEY (`a_id`),
  KEY `appointment_doctor` (`d_id`),
  KEY `appointment_patient` (`p_id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `favourite` (
  `f_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `p_id` INT UNSIGNED NOT NULL,
  `d_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`f_id`),
  UNIQUE KEY `favourite_patient_doctor` (`p_id`, `d_id`),
  KEY `favourite_doctor` (`d_id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `card_details` (
  `payment_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `a_id` INT UNSIGNED NOT NULL,
  `p_id` INT UNSIGNED NOT NULL,
  `p_name` VARCHAR(255) NOT NULL,
  `number` VARCHAR(30) NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `ex_date` VARCHAR(20) NOT NULL,
  `cvv` VARCHAR(4) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  -- save.php writes d-m-y h:i:s, not a MySQL DATETIME value.
  `date` VARCHAR(30) NOT NULL,
  PRIMARY KEY (`payment_id`),
  KEY `payment_appointment` (`a_id`),
  KEY `payment_patient` (`p_id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `lab_upload` (
  `l_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `username` VARCHAR(100) NOT NULL,
  `phone_number` VARCHAR(30) NOT NULL,
  `L_location` VARCHAR(100) NOT NULL,
  `sp_note` TEXT NULL,
  `file` VARCHAR(1024) NOT NULL,
  PRIMARY KEY (`l_id`)
) ENGINE=InnoDB;
