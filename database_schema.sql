-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Creato il: Ott 28, 2025 alle 15:56
-- Versione del server: 8.0.36
-- Versione PHP: 8.0.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `my_gefarmdb`
--

-- --------------------------------------------------------

--
-- Struttura della tabella `gefarm_devices`
--

DROP TABLE IF EXISTS `gefarm_devices`;
CREATE TABLE `gefarm_devices` (
  `id` int NOT NULL,
  `device_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_family` enum('uno','duo','caricar','emc') COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_type` enum('emcengine','emcinverter','emcbox') COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome_dispositivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'Dispositivo Gefarm',
  `ssid_ap` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ssid_password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_setup_completed` tinyint(1) DEFAULT '0',
  `chain2_active` tinyint(1) DEFAULT '0',
  `firmware_version` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_seen` timestamp NULL DEFAULT NULL,
  `du` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `k1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `k2` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fiv` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dataplate_synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `firmware_username` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `firmware_password_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `gefarm_device_meter_data`
--

DROP TABLE IF EXISTS `gefarm_device_meter_data`;
CREATE TABLE `gefarm_device_meter_data` (
  `id` int NOT NULL,
  `device_id` int NOT NULL,
  `inserted_by_user_id` int DEFAULT NULL,
  `cf_owner_encrypted` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Codice Fiscale CRIPTATO',
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cognome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `indirizzo` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `zip_code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `citta` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provincia` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pod` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `valid_from` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `valid_to` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `gefarm_password_reset_tokens`
--

DROP TABLE IF EXISTS `gefarm_password_reset_tokens`;
CREATE TABLE `gefarm_password_reset_tokens` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `type` enum('reset','verify') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'verify',
  `token` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `used` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `gefarm_thingsboard_configs`
--

DROP TABLE IF EXISTS `gefarm_thingsboard_configs`;
CREATE TABLE `gefarm_thingsboard_configs` (
  `id` int NOT NULL,
  `device_id` int NOT NULL,
  `user_id` int NOT NULL,
  `tb_username` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tb_access_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Token criptato',
  `tb_device_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `enabled` tinyint(1) DEFAULT '0',
  `provisioned_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `gefarm_users`
--

DROP TABLE IF EXISTS `gefarm_users`;
CREATE TABLE `gefarm_users` (
  `id` int NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cognome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `avatar_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar_color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#00853d',
  `email_verified` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `telefono` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Struttura della tabella `gefarm_user_devices`
--

DROP TABLE IF EXISTS `gefarm_user_devices`;
CREATE TABLE `gefarm_user_devices` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `device_id` int NOT NULL,
  `role` enum('owner','user','technician') COLLATE utf8mb4_unicode_ci DEFAULT 'user',
  `nickname` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_favorite` tinyint(1) DEFAULT '0',
  `is_meter_owner` tinyint(1) DEFAULT '0',
  `added_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `gefarm_user_sessions`
--

DROP TABLE IF EXISTS `gefarm_user_sessions`;
CREATE TABLE `gefarm_user_sessions` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `token_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_info` text COLLATE utf8mb4_unicode_ci,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indici per le tabelle scaricate
--

--
-- Indici per le tabelle `gefarm_devices`
--
ALTER TABLE `gefarm_devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `device_id` (`device_id`),
  ADD KEY `idx_device_id` (`device_id`);

--
-- Indici per le tabelle `gefarm_device_meter_data`
--
ALTER TABLE `gefarm_device_meter_data`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inserted_by_user_id` (`inserted_by_user_id`),
  ADD KEY `idx_device_active` (`device_id`,`is_active`),
  ADD KEY `idx_cf` (`cf_owner_encrypted`);

--
-- Indici per le tabelle `gefarm_password_reset_tokens`
--
ALTER TABLE `gefarm_password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_token` (`token`);

--
-- Indici per le tabelle `gefarm_thingsboard_configs`
--
ALTER TABLE `gefarm_thingsboard_configs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_device_tb` (`device_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indici per le tabelle `gefarm_users`
--
ALTER TABLE `gefarm_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`);

--
-- Indici per le tabelle `gefarm_user_devices`
--
ALTER TABLE `gefarm_user_devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_device` (`user_id`,`device_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_device_id` (`device_id`);

--
-- Indici per le tabelle `gefarm_user_sessions`
--
ALTER TABLE `gefarm_user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_token` (`token_hash`(100)),
  ADD KEY `idx_expiry` (`expires_at`);

--
-- AUTO_INCREMENT per le tabelle scaricate
--

--
-- AUTO_INCREMENT per la tabella `gefarm_devices`
--
ALTER TABLE `gefarm_devices`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `gefarm_device_meter_data`
--
ALTER TABLE `gefarm_device_meter_data`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `gefarm_password_reset_tokens`
--
ALTER TABLE `gefarm_password_reset_tokens`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `gefarm_thingsboard_configs`
--
ALTER TABLE `gefarm_thingsboard_configs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `gefarm_users`
--
ALTER TABLE `gefarm_users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT per la tabella `gefarm_user_devices`
--
ALTER TABLE `gefarm_user_devices`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `gefarm_user_sessions`
--
ALTER TABLE `gefarm_user_sessions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Limiti per le tabelle scaricate
--

--
-- Limiti per la tabella `gefarm_device_meter_data`
--
ALTER TABLE `gefarm_device_meter_data`
  ADD CONSTRAINT `gefarm_device_meter_data_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `gefarm_devices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `gefarm_device_meter_data_ibfk_2` FOREIGN KEY (`inserted_by_user_id`) REFERENCES `gefarm_users` (`id`) ON DELETE SET NULL;

--
-- Limiti per la tabella `gefarm_password_reset_tokens`
--
ALTER TABLE `gefarm_password_reset_tokens`
  ADD CONSTRAINT `gefarm_password_reset_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `gefarm_users` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `gefarm_thingsboard_configs`
--
ALTER TABLE `gefarm_thingsboard_configs`
  ADD CONSTRAINT `gefarm_thingsboard_configs_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `gefarm_devices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `gefarm_thingsboard_configs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `gefarm_users` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `gefarm_user_devices`
--
ALTER TABLE `gefarm_user_devices`
  ADD CONSTRAINT `gefarm_user_devices_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `gefarm_users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `gefarm_user_devices_ibfk_2` FOREIGN KEY (`device_id`) REFERENCES `gefarm_devices` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `gefarm_user_sessions`
--
ALTER TABLE `gefarm_user_sessions`
  ADD CONSTRAINT `gefarm_user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `gefarm_users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
