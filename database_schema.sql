CREATE TABLE `gefarm_users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(100) UNIQUE NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `nome` VARCHAR(100) NOT NULL,
    `cognome` VARCHAR(100) NOT NULL,
    `avatar_path` VARCHAR(255) NULL,
    `avatar_color` VARCHAR(7) DEFAULT '#00853d',
    `email_verified` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `gefarm_devices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `device_id` VARCHAR(50) UNIQUE NOT NULL,
    `device_type` VARCHAR(50) NOT NULL,
    `nome_dispositivo` VARCHAR(255) NOT NULL,
    `ssid_ap` VARCHAR(100) NULL,
    `device_password_hash` VARCHAR(255) NULL,
    `first_setup_completed` BOOLEAN DEFAULT FALSE,
    `chain2_active` BOOLEAN DEFAULT FALSE,
    `firmware_version` VARCHAR(20) NULL,
    `last_seen` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_device_id` (`device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `gefarm_user_devices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `device_id` INT NOT NULL,
    `role` ENUM('owner', 'user', 'technician') DEFAULT 'user',
    `nickname` VARCHAR(100) NULL,
    `is_favorite` BOOLEAN DEFAULT FALSE,
    `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `gefarm_users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`device_id`) REFERENCES `gefarm_devices`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_user_device` (`user_id`, `device_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_device_id` (`device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `gefarm_device_meter_data` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `device_id` INT NOT NULL,
    `cf` VARCHAR(255) NOT NULL COMMENT 'Codice Fiscale (criptato)',
    `nome` VARCHAR(100) NOT NULL,
    `cognome` VARCHAR(100) NOT NULL,
    `indirizzo` TEXT NOT NULL,
    `zip_code` VARCHAR(10) NOT NULL,
    `citta` VARCHAR(100) NOT NULL,
    `provincia` VARCHAR(50) NOT NULL,
    `pod` VARCHAR(50) NULL COMMENT 'Point of Delivery',
    `email` VARCHAR(100) NOT NULL,
    `telefono` VARCHAR(20) NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    `valid_from` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `valid_to` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`device_id`) REFERENCES `gefarm_devices`(`id`) ON DELETE CASCADE,
    INDEX `idx_device_active` (`device_id`, `is_active`),
    INDEX `idx_cf` (`cf`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `gefarm_thingsboard_configs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `device_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `tb_username` VARCHAR(100) NULL,
    `tb_access_token` VARCHAR(255) NULL COMMENT 'Token criptato',
    `tb_device_id` VARCHAR(100) NULL,
    `enabled` BOOLEAN DEFAULT FALSE,
    `provisioned_at` TIMESTAMP NULL,
    FOREIGN KEY (`device_id`) REFERENCES `gefarm_devices`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `gefarm_users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_device_tb` (`device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `gefarm_user_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `token_hash` VARCHAR(255) NOT NULL,
    `device_info` TEXT NULL,
    `expires_at` TIMESTAMP NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `gefarm_users`(`id`) ON DELETE CASCADE,
    INDEX `idx_token` (`token_hash`(100)),
    INDEX `idx_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `gefarm_password_reset_tokens` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `token` VARCHAR(100) UNIQUE NOT NULL,
    `expires_at` TIMESTAMP NOT NULL,
    `used` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `gefarm_users`(`id`) ON DELETE CASCADE,
    INDEX `idx_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

