-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 30-08-2025 a las 02:02:19
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `bormex_db`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) NOT NULL,
  `action` enum('INSERT','UPDATE','DELETE') NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `user_id`, `ip_address`, `user_agent`, `timestamp`) VALUES
(1, 'users', 1, '', NULL, '{\"login_time\":\"2025-08-26 12:42:57\"}', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-26 18:42:57'),
(2, 'users', 1, '', NULL, '{\"login_time\":\"2025-08-26 16:09:44\"}', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-26 22:09:44'),
(3, 'users', 1, '', NULL, '{\"login_time\":\"2025-08-27 10:43:05\"}', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-27 16:43:05'),
(4, 'users', 1, '', NULL, '{\"login_time\":\"2025-08-27 15:04:55\"}', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-27 21:04:55'),
(5, 'users', 1, '', NULL, '{\"login_time\":\"2025-08-27 15:50:31\"}', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-27 21:50:31'),
(6, 'users', 1, '', NULL, '{\"login_time\":\"2025-08-28 09:08:46\"}', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-28 15:08:46'),
(7, 'users', 1, '', NULL, '{\"login_time\":\"2025-08-28 09:14:02\"}', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-28 15:14:02'),
(8, 'users', 1, '', NULL, '{\"login_time\":\"2025-08-28 11:04:22\"}', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-28 17:04:22'),
(9, 'users', 1, '', NULL, '{\"login_time\":\"2025-08-28 15:05:08\"}', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-28 21:05:08'),
(10, 'users', 1, '', NULL, '{\"login_time\":\"2025-08-29 12:08:28\"}', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 18:08:28'),
(11, 'users', 1, '', NULL, '{\"login_time\":\"2025-08-29 15:20:46\"}', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 21:20:46'),
(12, 'users', 1, '', NULL, '{\"login_time\":\"2025-08-29 17:45:01\"}', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 23:45:01');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `company_settings`
--

CREATE TABLE `company_settings` (
  `id` int(11) NOT NULL,
  `company_name` varchar(200) NOT NULL DEFAULT 'BORMEX',
  `address` text NOT NULL DEFAULT 'San Miguel Sciosla, Puebla, Calle Benito Juárez, número 36',
  `phone` varchar(20) DEFAULT '2211-73-81-50',
  `email` varchar(100) DEFAULT NULL,
  `tax_id` varchar(50) DEFAULT NULL,
  `folio_prefix` varchar(10) DEFAULT 'BM',
  `current_folio_number` int(11) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `company_settings`
--

INSERT INTO `company_settings` (`id`, `company_name`, `address`, `phone`, `email`, `tax_id`, `folio_prefix`, `current_folio_number`, `updated_at`) VALUES
(1, 'BORMEX', 'San Miguel Sciosla, Puebla, Calle Benito Juárez, número 36', '2211-73-81-50', 'contacto@bormex.com', NULL, 'BM', 7, '2025-08-29 19:02:22');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `invoice_data`
--

CREATE TABLE `invoice_data` (
  `id` int(11) NOT NULL,
  `note_id` int(11) NOT NULL,
  `tax_id` varchar(50) NOT NULL,
  `business_name` varchar(200) NOT NULL,
  `address` text NOT NULL,
  `cfdi_use` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notes`
--

CREATE TABLE `notes` (
  `id` int(11) NOT NULL,
  `folio` varchar(20) NOT NULL,
  `client_name` varchar(200) NOT NULL,
  `client_phone` varchar(20) DEFAULT NULL,
  `client_email` varchar(100) DEFAULT NULL,
  `client_address` text DEFAULT NULL,
  `status` enum('con_anticipo_trabajandose','liquidada_pendiente_entrega','pagada_y_entregada') NOT NULL DEFAULT 'con_anticipo_trabajandose',
  `payment_method` enum('efectivo','transferencia','tarjeta') NOT NULL DEFAULT 'efectivo',
  `advance_payment` decimal(10,2) DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `requires_invoice` tinyint(1) DEFAULT 0,
  `observations` text DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `current_total` decimal(10,2) NOT NULL,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `notes`
--

INSERT INTO `notes` (`id`, `folio`, `client_name`, `client_phone`, `client_email`, `client_address`, `status`, `payment_method`, `advance_payment`, `discount`, `requires_invoice`, `observations`, `subtotal`, `tax_amount`, `total`, `current_total`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(3, 'BM003', 'Carlos Ramírez', '2215555555', 'carlos@email.com', NULL, 'pagada_y_entregada', 'tarjeta', 0.00, 0.00, 0, 'Playeras publicitarias', 800.00, 0.00, 800.00, 0.00, 1, NULL, '2025-08-26 17:22:09', '2025-08-26 17:22:09'),
(4, 'BM004', 'PEREZ GORGE ÑAÑES', '1234567890', 'correo@correo.com', 'Sur Ponente #233\r\nJsjsb./.#+#', 'con_anticipo_trabajandose', 'efectivo', 0.00, 0.00, 0, 'sdfsdf', 220.00, 0.00, 220.00, 220.00, 1, 1, '2025-08-28 21:18:03', '2025-08-28 21:18:15');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `note_items`
--

CREATE TABLE `note_items` (
  `id` int(11) NOT NULL,
  `note_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `note_items`
--

INSERT INTO `note_items` (`id`, `note_id`, `description`, `quantity`, `unit_price`, `subtotal`) VALUES
(3, 3, 'Playera publicitaria', 20, 40.00, 800.00),
(6, 4, 'sdfsdf', 1, 200.00, 200.00),
(7, 4, 'sdf', 1, 20.00, 20.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('super_admin','admin','encargado','empleado') NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role`, `first_name`, `last_name`, `active`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@bormex.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 'Super', 'Administrador', 1, '2025-08-25 23:13:12', '2025-08-25 23:13:12'),
(2, 'encargado', 'encargado@bormex.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'encargado', 'Juan', 'Pérez', 1, '2025-08-26 17:22:09', '2025-08-26 17:22:09'),
(3, 'empleado', 'empleado@bormex.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'empleado', 'María', 'González', 1, '2025-08-26 17:22:09', '2025-08-26 17:22:09');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_table_record` (`table_name`,`record_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_timestamp` (`timestamp`);

--
-- Indices de la tabla `company_settings`
--
ALTER TABLE `company_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `invoice_data`
--
ALTER TABLE `invoice_data`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `note_id` (`note_id`),
  ADD KEY `idx_note_id` (`note_id`);

--
-- Indices de la tabla `notes`
--
ALTER TABLE `notes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `folio` (`folio`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_folio` (`folio`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_client_name` (`client_name`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indices de la tabla `note_items`
--
ALTER TABLE `note_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_note_id` (`note_id`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `company_settings`
--
ALTER TABLE `company_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `invoice_data`
--
ALTER TABLE `invoice_data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `notes`
--
ALTER TABLE `notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `note_items`
--
ALTER TABLE `note_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Filtros para la tabla `invoice_data`
--
ALTER TABLE `invoice_data`
  ADD CONSTRAINT `invoice_data_ibfk_1` FOREIGN KEY (`note_id`) REFERENCES `notes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `notes`
--
ALTER TABLE `notes`
  ADD CONSTRAINT `notes_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `notes_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Filtros para la tabla `note_items`
--
ALTER TABLE `note_items`
  ADD CONSTRAINT `note_items_ibfk_1` FOREIGN KEY (`note_id`) REFERENCES `notes` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
