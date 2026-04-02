-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 02, 2026 at 05:03 AM
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
-- Database: `ecodent`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `verificar_estado_cuenta` (IN `p_id_paciente` INT)   BEGIN
    DECLARE v_ausencias INT;
    DECLARE v_llegadas_tarde INT;

    -- Obtener contadores actuales del paciente
    SELECT ausencias_sin_aviso, llegadas_tarde 
    INTO v_ausencias, v_llegadas_tarde
    FROM pacientes 
    WHERE id_paciente = p_id_paciente;

    -- Aplicar reglas de negocio según el PDF
    IF v_ausencias >= 6 THEN
        -- BLOQUEADA: no puede agendar por el sistema
        UPDATE pacientes 
        SET estado_cuenta = 'bloqueada', 
            puede_agendar = 0, 
            limite_citas_simultaneas = 0,
            fecha_actualizacion_estado = NOW()
        WHERE id_paciente = p_id_paciente;

    ELSEIF v_ausencias >= 4 THEN
        -- RESTRINGIDA: solo 1 cita a la vez
        UPDATE pacientes 
        SET estado_cuenta = 'restringida', 
            puede_agendar = 1, 
            limite_citas_simultaneas = 1,
            fecha_actualizacion_estado = NOW()
        WHERE id_paciente = p_id_paciente;

    ELSEIF v_ausencias >= 3 OR v_llegadas_tarde >= 3 THEN
        -- OBSERVACION: máximo 2 citas simultáneas
        UPDATE pacientes 
        SET estado_cuenta = 'observacion', 
            puede_agendar = 1, 
            limite_citas_simultaneas = 2,
            fecha_actualizacion_estado = NOW()
        WHERE id_paciente = p_id_paciente;

    ELSE
        -- NORMAL: sin restricciones, máximo 3 citas
        UPDATE pacientes 
        SET estado_cuenta = 'normal', 
            puede_agendar = 1, 
            limite_citas_simultaneas = 3,
            fecha_actualizacion_estado = NOW()
        WHERE id_paciente = p_id_paciente;
    END IF;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `alertas`
--

CREATE TABLE `alertas` (
  `id_alerta` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `id_regla` int(11) DEFAULT NULL,
  `titulo` varchar(200) NOT NULL,
  `mensaje` text NOT NULL,
  `tipo` enum('info','warning','danger','success') DEFAULT 'info',
  `leida` tinyint(1) DEFAULT 0,
  `fecha_creacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `backups`
--

CREATE TABLE `backups` (
  `id_backup` int(11) NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `tipo` enum('diario','semanal','mensual') NOT NULL,
  `tamano` int(11) DEFAULT NULL,
  `ruta` varchar(500) NOT NULL,
  `estado` enum('exitoso','fallido','en_progreso') DEFAULT 'exitoso',
  `fecha_creacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `citas`
--

CREATE TABLE `citas` (
  `id_cita` int(11) NOT NULL,
  `id_paciente` int(11) NOT NULL,
  `id_odontologo` int(11) NOT NULL,
  `fecha_cita` date NOT NULL,
  `hora_cita` time NOT NULL,
  `hora_fin` time DEFAULT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `estado` enum('programada','confirmada','completada','cancelada_pac','cancelada_doc','ausente') DEFAULT 'programada',
  `cambios_realizados` int(11) DEFAULT 0,
  `limite_cambios` int(11) DEFAULT 3,
  `puede_modificar` tinyint(1) DEFAULT 1,
  `llego_tarde` tinyint(1) DEFAULT 0,
  `minutos_tarde` int(11) DEFAULT 0,
  `cancelado_por` int(11) DEFAULT NULL,
  `motivo_cancelacion` text DEFAULT NULL,
  `fecha_cancelacion` datetime DEFAULT NULL,
  `notificacion_enviada` tinyint(1) DEFAULT 0,
  `fecha_recordatorio_24h` datetime DEFAULT NULL,
  `fecha_recordatorio_1h` datetime DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `citas`
--

INSERT INTO `citas` (`id_cita`, `id_paciente`, `id_odontologo`, `fecha_cita`, `hora_cita`, `hora_fin`, `motivo`, `estado`, `cambios_realizados`, `limite_cambios`, `puede_modificar`, `llego_tarde`, `minutos_tarde`, `cancelado_por`, `motivo_cancelacion`, `fecha_cancelacion`, `notificacion_enviada`, `fecha_recordatorio_24h`, `fecha_recordatorio_1h`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 1, 1, '2026-03-15', '09:00:00', '09:40:00', 'Consulta general - Dolor de muela', 'completada', 0, 3, 1, 1, 25, NULL, NULL, NULL, 0, NULL, NULL, '2026-03-14 14:18:09', '2026-03-17 09:20:21'),
(2, 1, 2, '2026-03-17', '10:30:00', '11:10:00', 'Limpieza dental', 'cancelada_pac', 0, 3, 1, 0, 0, 3, NULL, '2026-03-17 04:02:13', 0, NULL, NULL, '2026-03-14 14:18:09', '2026-03-17 04:02:13'),
(3, 1, 1, '2026-03-19', '15:00:00', '15:40:00', 'Revisión de ortodoncia', 'cancelada_doc', 0, 3, 1, 0, 0, 1, 'capacitacion', '2026-03-17 03:11:49', 0, NULL, NULL, '2026-03-14 14:18:09', '2026-03-17 03:11:49'),
(4, 3, 1, '2026-03-16', '16:40:00', '17:20:00', 'dolor de muela', 'cancelada_pac', 0, 3, 1, 0, 0, 5, NULL, '2026-03-14 14:27:44', 0, '2026-03-15 16:40:00', '2026-03-16 15:40:00', '2026-03-14 14:24:01', '2026-03-14 15:21:26'),
(5, 3, 1, '2026-03-16', '08:00:00', '08:40:00', 'dolor de muela', 'cancelada_pac', 0, 3, 1, 0, 0, 5, NULL, '2026-03-14 14:33:04', 0, '2026-03-15 08:00:00', '2026-03-16 07:00:00', '2026-03-14 14:27:31', '2026-03-14 15:21:26'),
(6, 3, 1, '2026-03-16', '08:40:00', '09:20:00', 'dolor de muela', 'cancelada_pac', 0, 3, 1, 0, 0, 5, NULL, '2026-03-14 14:30:03', 0, '2026-03-15 08:40:00', '2026-03-16 07:40:00', '2026-03-14 14:28:23', '2026-03-14 15:21:26'),
(7, 3, 1, '2026-03-16', '08:00:00', '08:40:00', 'dolor de muela', 'completada', 0, 3, 1, 0, 0, NULL, NULL, NULL, 0, '2026-03-15 08:00:00', '2026-03-16 07:00:00', '2026-03-14 14:33:34', '2026-03-14 15:21:26'),
(8, 2, 1, '2026-03-17', '08:00:00', '08:40:00', 'limpieza', 'cancelada_doc', 0, 3, 1, 0, 0, 1, 'emergencia', '2026-03-16 15:15:05', 0, '2026-03-16 08:00:00', '2026-03-17 07:00:00', '2026-03-14 15:05:28', '2026-03-16 15:15:05'),
(9, 2, 1, '2026-03-18', '08:00:00', '08:40:00', 'cirujia molar', 'cancelada_pac', 0, 3, 1, 0, 0, 4, NULL, '2026-03-17 02:45:19', 0, NULL, NULL, '2026-03-16 06:11:22', '2026-03-17 02:45:19'),
(10, 2, 1, '2026-03-18', '08:40:00', '09:20:00', 'cirujia molar', 'cancelada_pac', 0, 3, 1, 0, 0, 4, NULL, '2026-03-17 05:06:14', 0, NULL, NULL, '2026-03-16 06:11:22', '2026-03-17 05:06:14'),
(11, 2, 1, '2026-03-18', '09:20:00', '10:00:00', 'cirujia molar', 'cancelada_pac', 0, 3, 1, 0, 0, 4, NULL, '2026-03-17 05:06:13', 0, NULL, NULL, '2026-03-16 06:11:22', '2026-03-17 05:06:13'),
(12, 3, 1, '2026-03-18', '10:00:00', '13:20:00', 'implante', 'programada', 0, 3, 1, 0, 0, NULL, NULL, NULL, 0, NULL, NULL, '2026-03-16 06:26:06', NULL),
(13, 3, 1, '2026-03-16', '15:20:00', '16:00:00', 'molar', 'completada', 0, 3, 1, 1, 20, NULL, NULL, NULL, 0, NULL, NULL, '2026-03-16 06:53:40', '2026-03-16 14:56:51'),
(14, 2, 1, '2026-03-16', '08:40:00', '09:20:00', 'ciruji', 'ausente', 0, 3, 1, 0, 0, NULL, NULL, NULL, 0, NULL, NULL, '2026-03-16 06:58:14', '2026-03-16 14:47:06'),
(15, 1, 1, '2026-03-31', '08:00:00', '09:20:00', 'cirugia', 'cancelada_pac', 0, 3, 1, 0, 0, 3, NULL, '2026-03-17 04:00:43', 0, NULL, NULL, '2026-03-16 06:59:19', '2026-03-17 04:00:43'),
(16, 2, 1, '2026-03-18', '08:00:00', '08:40:00', 'muela', 'completada', 0, 3, 1, 0, 0, NULL, NULL, NULL, 0, '2026-03-17 08:00:00', '2026-03-18 07:00:00', '2026-03-17 02:45:33', '2026-03-17 02:46:31'),
(17, 2, 1, '2026-03-17', '10:00:00', '10:40:00', 'si', 'cancelada_doc', 0, 3, 1, 0, 0, 1, 'emergencia', '2026-03-17 03:25:38', 0, '2026-03-16 10:00:00', '2026-03-17 09:00:00', '2026-03-17 02:47:04', '2026-03-17 03:25:38'),
(18, 2, 1, '2026-03-17', '10:00:00', NULL, 'limpieza', 'cancelada_doc', 0, 3, 1, 0, 0, 1, 'emergencia', '2026-03-17 04:52:14', 0, NULL, NULL, '2026-03-17 02:47:21', '2026-03-17 04:52:14'),
(19, 2, 1, '2026-03-17', '11:20:00', NULL, 'limpieza', 'cancelada_pac', 0, 3, 1, 0, 0, 4, NULL, '2026-03-17 05:06:18', 0, NULL, NULL, '2026-03-17 02:47:37', '2026-03-17 05:06:18'),
(20, 1, 1, '2026-03-24', '08:00:00', '09:20:00', 'implante', 'cancelada_pac', 0, 3, 1, 0, 0, 3, NULL, '2026-03-17 03:56:56', 0, NULL, NULL, '2026-03-17 03:06:46', '2026-03-17 03:56:56'),
(21, 1, 1, '2026-03-20', '16:00:00', NULL, 'Revisión de ortodoncia', 'cancelada_pac', 0, 3, 1, 0, 0, 3, NULL, '2026-03-17 03:50:21', 0, NULL, NULL, '2026-03-17 03:12:51', '2026-03-17 03:50:21'),
(22, 1, 1, '2026-03-20', '17:20:00', NULL, 'Revisión de ortodoncia', 'cancelada_pac', 0, 3, 1, 0, 0, 3, NULL, '2026-03-17 03:50:25', 0, NULL, NULL, '2026-03-17 03:26:36', '2026-03-17 03:50:25'),
(23, 2, 1, '2026-03-23', '08:00:00', '08:40:00', 'limpieza', 'cancelada_pac', 0, 3, 1, 0, 0, 4, NULL, '2026-03-17 05:06:10', 0, '2026-03-22 08:00:00', '2026-03-23 07:00:00', '2026-03-17 04:59:06', '2026-03-17 05:06:10'),
(24, 2, 1, '2026-03-23', '08:40:00', '09:20:00', 'limpieza', 'cancelada_pac', 0, 3, 1, 0, 0, 4, NULL, '2026-03-17 05:06:08', 0, '2026-03-22 08:40:00', '2026-03-23 07:40:00', '2026-03-17 04:59:28', '2026-03-17 05:06:08'),
(25, 2, 1, '2026-03-18', '08:00:00', NULL, 'si', 'cancelada_pac', 0, 3, 1, 0, 0, 4, NULL, '2026-03-17 05:06:16', 0, '2026-03-17 08:00:00', '2026-03-18 07:00:00', '2026-03-17 05:02:41', '2026-03-17 05:06:16'),
(26, 2, 1, '2026-03-18', '10:40:00', NULL, 'si', 'cancelada_pac', 0, 3, 1, 0, 0, 4, NULL, '2026-03-17 05:06:11', 0, '2026-03-17 10:40:00', '2026-03-18 09:40:00', '2026-03-17 05:02:58', '2026-03-17 05:06:11'),
(27, 2, 1, '2026-03-23', '08:00:00', '08:40:00', 'limpieza dental', 'cancelada_doc', 0, 3, 1, 0, 0, 1, 'emergencia personal', '2026-03-17 05:08:10', 0, '2026-03-22 08:00:00', '2026-03-23 07:00:00', '2026-03-17 05:06:50', '2026-03-17 05:08:10'),
(28, 2, 1, '2026-03-27', '09:20:00', '10:00:00', 'limpieza dental', 'cancelada_doc', 0, 3, 1, 0, 0, 1, 'emergencia', '2026-03-17 09:17:51', 0, '2026-03-26 09:20:00', '2026-03-27 08:20:00', '2026-03-17 05:09:11', '2026-03-17 09:17:51'),
(29, 2, 1, '2026-03-25', '08:00:00', '08:40:00', 'limpieza dental', 'completada', 0, 3, 1, 0, 0, NULL, NULL, NULL, 0, '2026-03-24 08:00:00', '2026-03-25 07:00:00', '2026-03-17 09:18:52', '2026-03-25 18:23:55'),
(30, 2, 1, '2026-03-27', '16:00:00', '17:20:00', 'implante', 'completada', 0, 3, 1, 0, 0, NULL, NULL, NULL, 0, NULL, NULL, '2026-03-25 18:17:42', '2026-03-25 19:17:23'),
(31, 2, 1, '2026-03-31', '08:00:00', '09:20:00', 'Cirujia molar', 'programada', 0, 3, 1, 0, 0, NULL, NULL, NULL, 0, NULL, NULL, '2026-03-30 15:10:18', NULL),
(32, 2, 1, '2026-03-31', '17:20:00', '18:00:00', 'muela', 'cancelada_doc', 0, 3, 1, 0, 0, 1, 'capacitacion', '2026-03-30 15:13:36', 0, NULL, NULL, '2026-03-30 15:11:35', '2026-03-30 15:13:36'),
(33, 2, 1, '2026-03-31', '09:20:00', '10:00:00', 'implante', 'completada', 0, 3, 1, 0, 0, NULL, NULL, NULL, 0, NULL, NULL, '2026-03-30 15:15:01', '2026-03-30 15:15:11'),
(34, 3, 1, '2026-03-31', '10:00:00', '10:40:00', 'implante', 'completada', 0, 3, 1, 1, 15, NULL, NULL, NULL, 0, NULL, NULL, '2026-03-30 15:27:27', '2026-03-30 15:27:35'),
(35, 1, 1, '2026-03-31', '10:40:00', '11:20:00', 'mulas', 'ausente', 0, 3, 1, 0, 0, NULL, NULL, NULL, 0, NULL, NULL, '2026-03-30 15:28:10', '2026-03-30 15:28:16'),
(36, 2, 1, '2026-04-03', '09:20:00', '10:00:00', 'muela', 'cancelada_pac', 3, 3, 0, 0, 0, 4, NULL, '2026-03-31 00:34:47', 0, NULL, NULL, '2026-03-30 15:54:55', '2026-03-31 00:34:47'),
(37, 4, 1, '2026-04-03', '08:00:00', '08:40:00', 'limpieza', 'cancelada_doc', 0, 3, 1, 0, 0, 1, 'DERMATOLOGO', '2026-04-01 02:05:47', 0, NULL, NULL, '2026-03-30 19:16:29', '2026-04-01 02:05:47'),
(38, 1, 1, '2026-04-02', '08:00:00', '08:40:00', 'muela', 'ausente', 0, 3, 1, 0, 0, NULL, NULL, NULL, 0, '2026-04-01 08:00:00', '2026-04-02 07:00:00', '2026-03-30 20:09:20', '2026-03-30 21:37:12'),
(39, 1, 1, '2026-03-31', '08:40:00', '09:20:00', 'muela', 'ausente', 0, 3, 1, 0, 0, NULL, NULL, NULL, 0, '2026-03-30 08:40:00', '2026-03-31 07:40:00', '2026-03-30 21:17:15', '2026-03-30 21:36:39'),
(40, 1, 1, '2026-03-31', '10:00:00', '10:40:00', 'dolor', 'ausente', 0, 3, 1, 0, 0, NULL, NULL, NULL, 0, '2026-03-30 10:00:00', '2026-03-31 09:00:00', '2026-03-30 21:17:24', '2026-03-30 21:36:51'),
(41, 2, 1, '2026-04-03', '08:40:00', '09:20:00', 'muela', 'cancelada_doc', 1, 3, 1, 0, 0, 1, 'EMERGENCIA', '2026-04-01 01:51:35', 0, '2026-03-30 15:20:00', '2026-03-31 14:20:00', '2026-03-31 00:13:22', '2026-04-01 01:51:35'),
(42, 2, 1, '2026-04-03', '10:00:00', '12:00:00', 'blanqueamiento', 'cancelada_pac', 0, 3, 1, 0, 0, 4, NULL, '2026-03-31 00:34:49', 0, NULL, NULL, '2026-03-31 00:26:37', '2026-03-31 00:34:49'),
(43, 2, 1, '2026-04-06', '08:00:00', '10:40:00', 'protesis', 'cancelada_doc', 0, 3, 1, 0, 0, 1, 'BBB', '2026-04-01 02:03:40', 0, NULL, NULL, '2026-03-31 00:33:30', '2026-04-01 02:03:40'),
(44, 5, 1, '2026-04-06', '10:40:00', '11:20:00', 'consulta', 'cancelada_doc', 0, 3, 1, 0, 0, 1, 'EMERGENCIA', '2026-04-01 02:00:39', 0, '2026-04-05 10:40:00', '2026-04-06 09:40:00', '2026-03-31 01:24:49', '2026-04-01 02:00:39'),
(45, 3, 1, '2026-04-02', '08:40:00', '10:40:00', 'revision', 'cancelada_doc', 0, 3, 1, 0, 0, 1, 'CAPACITACION', '2026-04-01 01:50:23', 0, '2026-04-01 01:23:59', NULL, '2026-03-31 01:37:44', '2026-04-01 01:50:23'),
(46, 5, 1, '2026-04-06', '08:40:00', '09:20:00', 'consulta', 'ausente', 0, 3, 1, 0, 0, NULL, NULL, NULL, 0, '2026-04-05 08:40:00', '2026-04-06 07:40:00', '2026-04-01 02:24:16', '2026-04-01 02:25:30'),
(47, 5, 1, '2026-04-13', '08:00:00', '08:40:00', 'dolor de muela', 'ausente', 0, 3, 1, 0, 0, NULL, NULL, NULL, 0, '2026-04-12 08:00:00', '2026-04-13 07:00:00', '2026-04-01 02:24:34', '2026-04-01 02:25:43'),
(48, 5, 1, '2026-04-20', '08:00:00', '08:40:00', 'dolor de muela', 'ausente', 0, 3, 1, 0, 0, NULL, NULL, NULL, 0, '2026-04-19 08:00:00', '2026-04-20 07:00:00', '2026-04-01 02:24:51', '2026-04-01 02:25:49'),
(49, 5, 1, '2026-04-06', '09:20:00', '10:00:00', 'consulta_general', 'completada', 0, 3, 1, 0, 0, NULL, NULL, NULL, 0, NULL, NULL, '2026-04-01 02:34:55', '2026-04-01 02:35:29'),
(50, 5, 1, '2026-04-06', '10:00:00', '10:40:00', 'extraccion', 'completada', 0, 3, 1, 0, 0, NULL, NULL, NULL, 0, NULL, NULL, '2026-04-01 02:35:08', '2026-04-01 02:35:36'),
(51, 5, 1, '2026-04-06', '11:20:00', '12:00:00', 'blanqueamiento', 'completada', 0, 3, 1, 0, 0, NULL, NULL, NULL, 0, NULL, NULL, '2026-04-01 02:35:22', '2026-04-01 02:35:44'),
(52, 4, 1, '2026-04-27', '08:00:00', '08:40:00', 'ortodoncia', 'programada', 0, 3, 1, 0, 0, NULL, NULL, NULL, 0, NULL, NULL, '2026-04-01 22:50:17', NULL),
(53, 5, 2, '2026-04-08', '09:00:00', '09:40:00', 'blanqueamiento', 'programada', 0, 3, 1, 0, 0, NULL, NULL, NULL, 0, NULL, NULL, '2026-04-01 22:52:41', NULL);

--
-- Triggers `citas`
--
DELIMITER $$
CREATE TRIGGER `actualizar_estado_after_cita` AFTER UPDATE ON `citas` FOR EACH ROW BEGIN
    -- Si la cita se marcó como ausente
    IF NEW.estado = 'ausente' AND OLD.estado != 'ausente' THEN
        CALL verificar_estado_cuenta(NEW.id_paciente);
    END IF;
    
    -- Si la cita se marcó como completada y llegó tarde
    IF NEW.estado = 'completada' AND OLD.estado != 'completada' AND NEW.llego_tarde = 1 THEN
        CALL verificar_estado_cuenta(NEW.id_paciente);
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `actualizar_paciente_after_cita` AFTER UPDATE ON `citas` FOR EACH ROW BEGIN
    -- Si la cita se marcó como completada y llegó tarde
    IF NEW.estado = 'completada' AND OLD.estado != 'completada' AND NEW.llego_tarde = 1 THEN
        UPDATE pacientes 
        SET llegadas_tarde = llegadas_tarde + 1
        WHERE id_paciente = NEW.id_paciente;
    END IF;
    
    -- Si la cita se marcó como ausente
    IF NEW.estado = 'ausente' AND OLD.estado != 'ausente' THEN
        UPDATE pacientes 
        SET ausencias_sin_aviso = ausencias_sin_aviso + 1,
            fecha_ultima_ausencia = NEW.fecha_cita
        WHERE id_paciente = NEW.id_paciente;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `incrementar_cambios_cita` BEFORE UPDATE ON `citas` FOR EACH ROW BEGIN
    -- Si se cambió fecha o hora, y la cita está programada/confirmada
    IF (NEW.fecha_cita != OLD.fecha_cita OR NEW.hora_cita != OLD.hora_cita) 
       AND OLD.estado IN ('programada', 'confirmada') THEN
        
        -- Incrementar contador de cambios
        SET NEW.cambios_realizados = OLD.cambios_realizados + 1;
        
        -- Si llegó al límite de 3 cambios, deshabilitar más modificaciones
        IF NEW.cambios_realizados >= NEW.limite_cambios THEN
            SET NEW.puede_modificar = 0;
        END IF;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `registrar_historial_citas` AFTER UPDATE ON `citas` FOR EACH ROW BEGIN
    DECLARE v_id_usuario INT;
    
    -- Obtener el ID del usuario que modificó
    IF NEW.cancelado_por IS NOT NULL AND NEW.cancelado_por > 0 THEN
        SET v_id_usuario = NEW.cancelado_por;
    ELSE
        -- Obtener el ID de usuario del paciente
        SELECT id_usuario INTO v_id_usuario 
        FROM pacientes 
        WHERE id_paciente = NEW.id_paciente;
    END IF;
    
    -- Solo registrar si cambió fecha u hora
    IF (NEW.fecha_cita != OLD.fecha_cita OR NEW.hora_cita != OLD.hora_cita) THEN
        
        INSERT INTO historial_modificaciones_citas (
            id_cita,
            id_usuario,
            fecha_anterior,
            hora_anterior,
            fecha_nueva,
            hora_nueva,
            fecha_modificacion
        ) VALUES (
            NEW.id_cita,
            v_id_usuario,
            OLD.fecha_cita,
            OLD.hora_cita,
            NEW.fecha_cita,
            NEW.hora_cita,
            NOW()
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `estadisticas_odontologos`
--

CREATE TABLE `estadisticas_odontologos` (
  `id_estadistica` int(11) NOT NULL,
  `id_odontologo` int(11) NOT NULL,
  `mes` int(11) NOT NULL,
  `anio` int(11) NOT NULL,
  `total_citas` int(11) DEFAULT 0,
  `citas_completadas` int(11) DEFAULT 0,
  `ausencias` int(11) DEFAULT 0,
  `llegadas_tarde` int(11) DEFAULT 0,
  `ingresos_totales` decimal(10,2) DEFAULT 0.00,
  `fecha_calculo` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `historial_modificaciones_citas`
--

CREATE TABLE `historial_modificaciones_citas` (
  `id_historial` int(11) NOT NULL,
  `id_cita` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_anterior` date DEFAULT NULL,
  `hora_anterior` time DEFAULT NULL,
  `fecha_nueva` date DEFAULT NULL,
  `hora_nueva` time DEFAULT NULL,
  `fecha_modificacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `historial_modificaciones_citas`
--

INSERT INTO `historial_modificaciones_citas` (`id_historial`, `id_cita`, `id_usuario`, `fecha_anterior`, `hora_anterior`, `fecha_nueva`, `hora_nueva`, `fecha_modificacion`) VALUES
(1, 36, 4, '2026-04-01', '08:00:00', '2026-04-10', '10:00:00', '2026-03-31 00:01:10'),
(2, 41, 4, '2026-03-31', '15:20:00', '2026-04-03', '08:40:00', '2026-03-31 00:16:54'),
(3, 36, 4, '2026-04-10', '10:00:00', '2026-03-31', '08:40:00', '2026-03-31 00:17:19'),
(4, 36, 4, '2026-03-31', '08:40:00', '2026-04-03', '09:20:00', '2026-03-31 00:29:18');

-- --------------------------------------------------------

--
-- Table structure for table `horarios_odontologos`
--

CREATE TABLE `horarios_odontologos` (
  `id_horario` int(11) NOT NULL,
  `id_odontologo` int(11) NOT NULL,
  `dia_semana` enum('lunes','martes','miercoles','jueves','viernes','sabado','domingo') NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `horarios_odontologos`
--

INSERT INTO `horarios_odontologos` (`id_horario`, `id_odontologo`, `dia_semana`, `hora_inicio`, `hora_fin`, `activo`) VALUES
(1, 1, 'lunes', '08:00:00', '20:00:00', 1),
(2, 1, 'martes', '08:00:00', '18:00:00', 1),
(3, 1, 'miercoles', '08:00:00', '18:00:00', 1),
(4, 1, 'jueves', '08:00:00', '18:00:00', 1),
(5, 1, 'viernes', '08:00:00', '18:00:00', 1),
(6, 2, 'lunes', '09:00:00', '17:00:00', 1),
(7, 2, 'martes', '09:00:00', '17:00:00', 1),
(8, 2, 'miercoles', '09:00:00', '17:00:00', 1),
(9, 2, 'jueves', '09:00:00', '17:00:00', 1),
(10, 2, 'viernes', '09:00:00', '17:00:00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `mensajes_pendientes`
--

CREATE TABLE `mensajes_pendientes` (
  `id_mensaje` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_cita` int(11) DEFAULT NULL,
  `tipo` enum('confirmacion','recordatorio_24h','recordatorio_1h','cancelacion','cancelacion_doctor','reprogramacion') DEFAULT NULL,
  `canal` enum('email','whatsapp') NOT NULL,
  `mensaje` text NOT NULL,
  `telefono_destino` varchar(20) DEFAULT NULL,
  `email_destino` varchar(100) DEFAULT NULL,
  `enviado` tinyint(1) DEFAULT 0,
  `fecha_programado` datetime DEFAULT NULL,
  `fecha_envio` datetime DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `mensajes_pendientes`
--

INSERT INTO `mensajes_pendientes` (`id_mensaje`, `id_usuario`, `id_cita`, `tipo`, `canal`, `mensaje`, `telefono_destino`, `email_destino`, `enviado`, `fecha_programado`, `fecha_envio`, `fecha_registro`) VALUES
(1, 5, 5, 'cancelacion', 'email', 'Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.', NULL, NULL, 1, '2026-03-14 14:33:04', '2026-04-01 01:23:46', '2026-03-14 14:33:04'),
(2, 4, 8, '', 'email', 'Tu cita ha sido cancelada por el odontólogo. Motivo: emergencia. \r\n                    Por favor, ingresa al sistema para elegir una nueva fecha entre las opciones disponibles.', NULL, NULL, 1, '2026-03-16 15:15:05', '2026-04-01 01:23:53', '2026-03-16 15:15:05'),
(3, 4, 9, 'cancelacion', 'email', 'Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.', NULL, NULL, 1, '2026-03-17 02:45:19', '2026-04-01 01:24:00', '2026-03-17 02:45:19'),
(4, 3, 3, '', 'email', 'Tu cita ha sido cancelada por el odontólogo. Motivo: capacitacion. \r\n                    Por favor, ingresa al sistema para elegir una nueva fecha entre las opciones disponibles.', NULL, NULL, 1, '2026-03-17 03:11:49', '2026-04-01 01:24:07', '2026-03-17 03:11:49'),
(5, 4, 17, '', 'email', 'Tu cita ha sido cancelada por el odontólogo. Motivo: emergencia. \r\n                    Por favor, ingresa al sistema para elegir una nueva fecha entre las opciones disponibles.', NULL, NULL, 1, '2026-03-17 03:25:39', '2026-04-01 01:24:16', '2026-03-17 03:25:39'),
(6, 3, 21, 'cancelacion', 'email', 'Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.', NULL, NULL, 1, '2026-03-17 03:50:21', '2026-04-01 01:24:24', '2026-03-17 03:50:21'),
(7, 3, 22, 'cancelacion', 'email', 'Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.', NULL, NULL, 1, '2026-03-17 03:50:25', '2026-04-01 01:24:34', '2026-03-17 03:50:25'),
(8, 3, 20, 'cancelacion', 'email', 'Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.', NULL, NULL, 1, '2026-03-17 03:56:56', '2026-04-01 01:24:42', '2026-03-17 03:56:56'),
(9, 3, 15, 'cancelacion', 'email', 'Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.', NULL, NULL, 1, '2026-03-17 04:00:43', '2026-04-01 01:24:51', '2026-03-17 04:00:43'),
(10, 3, 2, 'cancelacion', 'email', 'Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.', NULL, NULL, 1, '2026-03-17 04:02:13', '2026-04-01 01:24:58', '2026-03-17 04:02:13'),
(11, 4, 18, '', 'email', 'Tu cita ha sido cancelada por el odontólogo. Motivo: emergencia. \r\n                    Por favor, ingresa al sistema para elegir una nueva fecha entre las opciones disponibles.', NULL, NULL, 1, '2026-03-17 04:52:14', '2026-04-01 01:25:04', '2026-03-17 04:52:14'),
(12, 4, 24, 'cancelacion', 'email', 'Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.', NULL, NULL, 1, '2026-03-17 05:06:08', '2026-04-01 01:25:10', '2026-03-17 05:06:08'),
(13, 4, 23, 'cancelacion', 'email', 'Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.', NULL, NULL, 1, '2026-03-17 05:06:10', '2026-04-01 01:25:17', '2026-03-17 05:06:10'),
(14, 4, 26, 'cancelacion', 'email', 'Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.', NULL, NULL, 1, '2026-03-17 05:06:11', '2026-04-01 01:25:26', '2026-03-17 05:06:11'),
(15, 4, 11, 'cancelacion', 'email', 'Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.', NULL, NULL, 1, '2026-03-17 05:06:13', '2026-04-01 01:25:34', '2026-03-17 05:06:13'),
(16, 4, 10, 'cancelacion', 'email', 'Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.', NULL, NULL, 1, '2026-03-17 05:06:14', '2026-04-01 01:43:58', '2026-03-17 05:06:14'),
(17, 4, 25, 'cancelacion', 'email', 'Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.', NULL, NULL, 1, '2026-03-17 05:06:16', '2026-04-01 01:44:05', '2026-03-17 05:06:16'),
(18, 4, 19, 'cancelacion', 'email', 'Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.', NULL, NULL, 1, '2026-03-17 05:06:18', '2026-04-01 01:44:11', '2026-03-17 05:06:18'),
(19, 4, 27, '', 'email', 'Tu cita ha sido cancelada por el odontólogo. Motivo: emergencia personal. \r\n                    Por favor, ingresa al sistema para elegir una nueva fecha entre las opciones disponibles.', NULL, NULL, 1, '2026-03-17 05:08:10', '2026-04-01 01:44:18', '2026-03-17 05:08:10'),
(20, 4, 28, '', 'email', 'Tu cita ha sido cancelada por el odontólogo. Motivo: emergencia. \r\n                    Por favor, ingresa al sistema para elegir una nueva fecha entre las opciones disponibles.', NULL, NULL, 1, '2026-03-17 09:17:51', '2026-04-01 01:44:25', '2026-03-17 09:17:51'),
(21, 4, 32, '', 'email', 'Tu cita ha sido cancelada por el odontólogo. Motivo: capacitacion. \r\n                    Por favor, ingresa al sistema para elegir una nueva fecha entre las opciones disponibles.', NULL, NULL, 1, '2026-03-30 15:13:36', '2026-04-01 01:44:31', '2026-03-30 15:13:36'),
(22, 4, 36, 'cancelacion', 'email', 'Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.', NULL, NULL, 1, '2026-03-31 00:34:47', '2026-04-01 01:44:38', '2026-03-31 00:34:47'),
(23, 4, 42, 'cancelacion', 'email', 'Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.', NULL, NULL, 1, '2026-03-31 00:34:49', '2026-04-01 01:44:44', '2026-03-31 00:34:49'),
(24, 5, 45, '', 'email', 'Tu cita del 02/04/2026 a las 08:40 ha sido cancelada por el odontólogo. \r\nMotivo: CAPACITACION. \r\nPor favor, ingresa al sistema para elegir una nueva fecha entre las opciones disponibles.', '12347885', 'ser@gmail.com', 0, '2026-04-01 01:50:23', NULL, '2026-04-01 01:50:23'),
(25, 5, 45, '', 'whatsapp', '🦷 *EcoDent - Cita Cancelada*\n\nHola sergio, lamentamos informarte que tu cita del *02/04/2026* a las *08:40* ha sido cancelada.\n\n📋 *Motivo:* CAPACITACION\n\n📅 *Opciones de reprogramación disponibles:*\n  1. 02/04/2026 a las 08:00\n  2. 02/04/2026 a las 10:40\n  3. 02/04/2026 a las 11:20\n\nPor favor ingresa al sistema EcoDent para elegir tu nueva fecha.\n📞 Tel: 77112233', '12347885', 'ser@gmail.com', 0, '2026-04-01 01:50:23', NULL, '2026-04-01 01:50:23'),
(26, 4, 41, '', 'email', 'Tu cita del 03/04/2026 a las 08:40 ha sido cancelada por el odontólogo. \r\nMotivo: EMERGENCIA. \r\nPor favor, ingresa al sistema para elegir una nueva fecha entre las opciones disponibles.', '73537562', 'richardocsachoqueherrera985@gmail.com', 0, '2026-04-01 01:51:35', NULL, '2026-04-01 01:51:35'),
(27, 4, 41, '', 'whatsapp', '🦷 *EcoDent - Cita Cancelada*\n\nHola Richard Edmundo Herrera, lamentamos informarte que tu cita del *03/04/2026* a las *08:40* ha sido cancelada.\n\n📋 *Motivo:* EMERGENCIA\n\n📅 *Opciones de reprogramación disponibles:*\n  1. 02/04/2026 a las 09:20\n  2. 02/04/2026 a las 08:00\n\nPor favor ingresa al sistema EcoDent para elegir tu nueva fecha.\n📞 Tel: 77112233', '73537562', 'richardocsachoqueherrera985@gmail.com', 0, '2026-04-01 01:51:35', NULL, '2026-04-01 01:51:35'),
(28, 7, 44, 'cancelacion_doctor', 'email', 'Tu cita del 06/04/2026 a las 10:40 ha sido cancelada por el odontólogo. \r\nMotivo: EMERGENCIA. \r\nPor favor, ingresa al sistema para elegir una nueva fecha entre las opciones disponibles.', '73537562', 'herreraocsachoquerichard985@gmail.com', 0, '2026-04-01 02:00:39', NULL, '2026-04-01 02:00:39'),
(29, 7, 44, 'cancelacion_doctor', 'whatsapp', '🦷 *EcoDent - Cita Cancelada*\n\nHola edmundo, lamentamos informarte que tu cita del *06/04/2026* a las *10:40* ha sido cancelada.\n\n📋 *Motivo:* EMERGENCIA\n\n📅 *Opciones de reprogramación disponibles:*\n  1. 17/04/2026 a las 08:00\n  2. 17/04/2026 a las 08:40\n  3. 17/04/2026 a las 09:20\n\nPor favor ingresa al sistema EcoDent para elegir tu nueva fecha.\n📞 Tel: 77112233', '73537562', 'herreraocsachoquerichard985@gmail.com', 0, '2026-04-01 02:00:39', NULL, '2026-04-01 02:00:39'),
(30, 4, 43, 'cancelacion_doctor', 'email', 'Tu cita del 06/04/2026 a las 08:00 ha sido cancelada por el odontólogo. \r\nMotivo: BBB. \r\nPor favor, ingresa al sistema para elegir una nueva fecha entre las opciones disponibles.', '73537562', 'richardocsachoqueherrera985@gmail.com', 0, '2026-04-01 02:03:40', NULL, '2026-04-01 02:03:40'),
(31, 4, 43, 'cancelacion_doctor', 'whatsapp', '🦷 *EcoDent - Cita Cancelada*\n\nHola Richard Edmundo Herrera, lamentamos informarte que tu cita del *06/04/2026* a las *08:00* ha sido cancelada.\n\n📋 *Motivo:* BBB\n\n📅 *Opciones de reprogramación disponibles:*\n  1. 02/04/2026 a las 09:20\n  2. 02/04/2026 a las 10:00\n  3. 02/04/2026 a las 12:00\n\nPor favor ingresa al sistema EcoDent para elegir tu nueva fecha.\n📞 Tel: 77112233', '73537562', 'richardocsachoqueherrera985@gmail.com', 0, '2026-04-01 02:03:40', NULL, '2026-04-01 02:03:40'),
(32, 6, 37, 'cancelacion_doctor', 'email', 'Tu cita del 03/04/2026 a las 08:00 ha sido cancelada por el odontólogo. \r\nMotivo: DERMATOLOGO. \r\nPor favor, ingresa al sistema para elegir una nueva fecha entre las opciones disponibles.', '73537562', 'jhamilth@gmail.com', 0, '2026-04-01 02:05:47', NULL, '2026-04-01 02:05:47'),
(33, 6, 37, 'cancelacion_doctor', 'whatsapp', '🦷 *EcoDent - Cita Cancelada*\n\nHola Jhamileth, lamentamos informarte que tu cita del *03/04/2026* a las *08:00* ha sido cancelada.\n\n📋 *Motivo:* DERMATOLOGO\n\n📅 *Opciones de reprogramación disponibles:*\n  1. 02/04/2026 a las 08:00\n  2. 02/04/2026 a las 09:20\n  3. 02/04/2026 a las 10:00\n\nPor favor ingresa al sistema EcoDent para elegir tu nueva fecha.\n📞 Tel: 77112233', '73537562', 'jhamilth@gmail.com', 0, '2026-04-01 02:05:47', NULL, '2026-04-01 02:05:47');

-- --------------------------------------------------------

--
-- Table structure for table `odontologos`
--

CREATE TABLE `odontologos` (
  `id_odontologo` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `especialidad_principal` varchar(100) NOT NULL,
  `especialidades_adicionales` text DEFAULT NULL,
  `duracion_cita_min` int(11) DEFAULT 40,
  `max_citas_dia` int(11) DEFAULT 8,
  `color_calendario` varchar(7) DEFAULT '#2E75B6',
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `odontologos`
--

INSERT INTO `odontologos` (`id_odontologo`, `id_usuario`, `especialidad_principal`, `especialidades_adicionales`, `duracion_cita_min`, `max_citas_dia`, `color_calendario`, `activo`) VALUES
(1, 1, 'Ortodoncia', NULL, 40, 8, '#2E75B6', 1),
(2, 2, 'Endodoncia', NULL, 40, 8, '#C00000', 1);

-- --------------------------------------------------------

--
-- Table structure for table `opciones_reprogramacion_cita`
--

CREATE TABLE `opciones_reprogramacion_cita` (
  `id_opcion` int(11) NOT NULL,
  `id_cita_original` int(11) NOT NULL,
  `fecha_propuesta` date NOT NULL,
  `hora_propuesta` time NOT NULL,
  `hora_propuesta_fin` time DEFAULT NULL,
  `id_odontologo` int(11) NOT NULL,
  `seleccionada` tinyint(1) DEFAULT 0,
  `fecha_registro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `opciones_reprogramacion_cita`
--

INSERT INTO `opciones_reprogramacion_cita` (`id_opcion`, `id_cita_original`, `fecha_propuesta`, `hora_propuesta`, `hora_propuesta_fin`, `id_odontologo`, `seleccionada`, `fecha_registro`) VALUES
(1, 8, '2026-03-17', '10:00:00', NULL, 1, 1, '2026-03-16 15:15:05'),
(2, 8, '2026-03-17', '11:20:00', NULL, 1, 1, '2026-03-16 15:15:05'),
(3, 3, '2026-03-20', '16:00:00', NULL, 1, 1, '2026-03-17 03:11:49'),
(4, 3, '2026-03-20', '17:20:00', NULL, 1, 1, '2026-03-17 03:11:49'),
(5, 17, '2026-03-18', '08:00:00', NULL, 1, 1, '2026-03-17 03:25:39'),
(6, 17, '2026-03-18', '10:40:00', NULL, 1, 1, '2026-03-17 03:25:39'),
(7, 17, '2026-03-18', '11:20:00', NULL, 1, 0, '2026-03-17 03:25:39'),
(8, 18, '2026-03-23', '08:00:00', '08:40:00', 1, 1, '2026-03-17 04:52:14'),
(9, 18, '2026-03-23', '08:40:00', '09:20:00', 1, 1, '2026-03-17 04:52:14'),
(10, 18, '2026-03-23', '09:20:00', '10:00:00', 1, 0, '2026-03-17 04:52:14'),
(11, 27, '2026-03-27', '08:40:00', '09:20:00', 1, 0, '2026-03-17 05:08:10'),
(12, 27, '2026-03-27', '09:20:00', '10:00:00', 1, 1, '2026-03-17 05:08:10'),
(13, 27, '2026-03-27', '08:00:00', '08:40:00', 1, 0, '2026-03-17 05:08:10'),
(14, 28, '2026-03-24', '08:00:00', '08:40:00', 1, 0, '2026-03-17 09:17:51'),
(15, 28, '2026-03-24', '09:20:00', '10:00:00', 1, 0, '2026-03-17 09:17:51'),
(16, 28, '2026-03-25', '08:00:00', '08:40:00', 1, 1, '2026-03-17 09:17:51'),
(17, 32, '2026-03-31', '16:40:00', '17:20:00', 1, 0, '2026-03-30 15:13:36'),
(18, 32, '2026-03-31', '14:40:00', '15:20:00', 1, 0, '2026-03-30 15:13:36'),
(19, 32, '2026-03-31', '15:20:00', '16:00:00', 1, 1, '2026-03-30 15:13:36'),
(20, 32, '2026-03-31', '16:00:00', '16:40:00', 1, 0, '2026-03-30 15:13:36'),
(21, 45, '2026-04-02', '08:00:00', '08:40:00', 1, 0, '2026-04-01 01:50:23'),
(22, 45, '2026-04-02', '10:40:00', '11:20:00', 1, 0, '2026-04-01 01:50:23'),
(23, 45, '2026-04-02', '11:20:00', '12:00:00', 1, 0, '2026-04-01 01:50:23'),
(24, 41, '2026-04-02', '09:20:00', '10:00:00', 1, 0, '2026-04-01 01:51:35'),
(25, 41, '2026-04-02', '08:00:00', '08:40:00', 1, 0, '2026-04-01 01:51:35'),
(26, 44, '2026-04-17', '08:00:00', '08:40:00', 1, 0, '2026-04-01 02:00:39'),
(27, 44, '2026-04-17', '08:40:00', '09:20:00', 1, 0, '2026-04-01 02:00:39'),
(28, 44, '2026-04-17', '09:20:00', '10:00:00', 1, 0, '2026-04-01 02:00:39'),
(29, 43, '2026-04-02', '09:20:00', '10:00:00', 1, 0, '2026-04-01 02:03:40'),
(30, 43, '2026-04-02', '10:00:00', '10:40:00', 1, 0, '2026-04-01 02:03:40'),
(31, 43, '2026-04-02', '12:00:00', '12:40:00', 1, 0, '2026-04-01 02:03:40'),
(32, 37, '2026-04-02', '08:00:00', '08:40:00', 1, 0, '2026-04-01 02:05:47'),
(33, 37, '2026-04-02', '09:20:00', '10:00:00', 1, 0, '2026-04-01 02:05:47'),
(34, 37, '2026-04-02', '10:00:00', '10:40:00', 1, 0, '2026-04-01 02:05:47');

-- --------------------------------------------------------

--
-- Table structure for table `pacientes`
--

CREATE TABLE `pacientes` (
  `id_paciente` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `ausencias_sin_aviso` int(11) DEFAULT 0,
  `llegadas_tarde` int(11) DEFAULT 0,
  `fecha_ultima_ausencia` date DEFAULT NULL,
  `estado_cuenta` enum('normal','observacion','restringida','bloqueada') DEFAULT 'normal',
  `puede_agendar` tinyint(1) DEFAULT 1,
  `limite_citas_simultaneas` int(11) DEFAULT 3,
  `fecha_actualizacion_estado` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pacientes`
--

INSERT INTO `pacientes` (`id_paciente`, `id_usuario`, `fecha_nacimiento`, `direccion`, `ausencias_sin_aviso`, `llegadas_tarde`, `fecha_ultima_ausencia`, `estado_cuenta`, `puede_agendar`, `limite_citas_simultaneas`, `fecha_actualizacion_estado`) VALUES
(1, 3, '1985-05-15', 'Calle Potosí #123, La Paz', 7, 1, '2026-04-02', 'bloqueada', 0, 0, '2026-03-30 21:37:12'),
(2, 4, NULL, NULL, 1, 1, '2026-03-16', 'normal', 1, 3, NULL),
(3, 5, NULL, NULL, 0, 4, NULL, 'observacion', 1, 2, '2026-04-01 02:20:23'),
(4, 6, '2021-11-22', 'z/villa Dolores C/sebarullo N/1814', 0, 0, NULL, 'normal', 1, 3, '2026-04-01 02:20:23'),
(5, 7, NULL, NULL, 6, 0, '2026-04-20', 'bloqueada', 0, 0, '2026-04-01 02:25:49');

-- --------------------------------------------------------

--
-- Table structure for table `pagos`
--

CREATE TABLE `pagos` (
  `id_pago` int(11) NOT NULL,
  `id_paciente` int(11) NOT NULL,
  `id_tratamiento` int(11) DEFAULT NULL,
  `id_usuario_registro` int(11) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `concepto` varchar(255) NOT NULL,
  `fecha_pago` date NOT NULL,
  `foto_comprobante` varchar(255) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `metodo_pago` enum('efectivo','tarjeta','transferencia','otro') DEFAULT 'efectivo',
  `fecha_registro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pagos`
--

INSERT INTO `pagos` (`id_pago`, `id_paciente`, `id_tratamiento`, `id_usuario_registro`, `monto`, `concepto`, `fecha_pago`, `foto_comprobante`, `observaciones`, `metodo_pago`, `fecha_registro`) VALUES
(1, 1, 1, 1, 150.00, 'Pago completo consulta', '2026-03-14', NULL, NULL, 'efectivo', '2026-03-14 14:18:09'),
(2, 4, 3, 1, 50.00, 'pago inicial', '2026-03-31', '/ecodent/uploads/comprobantes/pago_1774912949_3.png', '', 'efectivo', '2026-03-30 19:22:29'),
(3, 4, 3, 1, 50.00, 'SEGUNDO PAGO', '2026-03-31', '/ecodent/uploads/comprobantes/pago_1774915231_3.png', '', 'efectivo', '2026-03-30 20:00:31'),
(4, 5, 4, 1, 450.00, 'primera cuota', '2026-03-31', '/ecodent/uploads/comprobantes/pago_1774935890_4.png', 'cancelado', 'efectivo', '2026-03-31 01:44:50'),
(5, 5, 5, 1, 74000.00, 'pirmera cuota', '2026-03-31', '/ecodent/uploads/comprobantes/pago_1774936033_5.png', 'niguna', 'transferencia', '2026-03-31 01:47:13'),
(6, 5, 5, 1, 1000.00, 'segunda cuota', '2026-03-31', NULL, '', 'efectivo', '2026-03-31 01:52:34'),
(7, 5, 4, 8, 40.00, 'SEGUNDO PAGO', '2026-04-02', '/ecodent/uploads/comprobantes/pago_1775098927_4.png', 'PAGO CONFORME', 'transferencia', '2026-04-01 23:02:07');

--
-- Triggers `pagos`
--
DELIMITER $$
CREATE TRIGGER `actualizar_tratamiento_after_pago` AFTER INSERT ON `pagos` FOR EACH ROW BEGIN
    -- Declarar la variable primero
    DECLARE nuevo_total DECIMAL(10,2);
    
    -- Calcular el total pagado sumando todos los pagos del tratamiento
    SELECT COALESCE(SUM(monto), 0) INTO nuevo_total
    FROM pagos 
    WHERE id_tratamiento = NEW.id_tratamiento;
    
    -- Actualizar el tratamiento
    UPDATE tratamientos 
    SET total_pagado = nuevo_total
    WHERE id_tratamiento = NEW.id_tratamiento;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `reglas_alertas`
--

CREATE TABLE `reglas_alertas` (
  `id_regla` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `condicion` text NOT NULL,
  `mensaje` text NOT NULL,
  `activa` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `slots_bloqueados`
--

CREATE TABLE `slots_bloqueados` (
  `id_bloqueo` int(11) NOT NULL,
  `id_odontologo` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `slots_bloqueados`
--

INSERT INTO `slots_bloqueados` (`id_bloqueo`, `id_odontologo`, `fecha`, `hora_inicio`, `hora_fin`, `motivo`, `fecha_registro`) VALUES
(2, 1, '2026-03-16', '08:00:00', '08:40:00', 'correo', '2026-03-14 15:03:03'),
(3, 1, '2026-03-17', '08:40:00', '09:20:00', 'correo', '2026-03-14 15:08:24'),
(4, 1, '2026-03-17', '09:20:00', '10:00:00', 'correo', '2026-03-14 15:08:24'),
(23, 1, '2026-03-17', '08:00:00', '08:40:00', 'Cancelación por odontólogo: emergencia', '2026-03-16 15:15:05'),
(24, 1, '2026-03-19', '15:00:00', '15:40:00', 'Cancelación por odontólogo: capacitacion', '2026-03-17 03:11:49'),
(25, 1, '2026-03-17', '10:00:00', '10:40:00', 'Cancelación por odontólogo: emergencia', '2026-03-17 03:25:39'),
(26, 1, '2026-03-17', '10:00:00', '10:40:00', 'Cancelación por odontólogo: emergencia', '2026-03-17 04:52:14'),
(27, 1, '2026-03-23', '08:00:00', '08:40:00', 'Cancelación por odontólogo: emergencia personal', '2026-03-17 05:08:10'),
(28, 1, '2026-03-27', '09:20:00', '10:00:00', 'Cancelación por odontólogo: emergencia', '2026-03-17 09:17:51'),
(29, 1, '2026-03-31', '17:20:00', '18:00:00', 'Cancelación por odontólogo: capacitacion', '2026-03-30 15:13:36'),
(36, 1, '2026-04-02', '08:40:00', '09:20:00', 'Cancelación por odontólogo: CAPACITACION', '2026-04-01 01:50:23'),
(37, 1, '2026-04-03', '08:40:00', '09:20:00', 'Cancelación por odontólogo: EMERGENCIA', '2026-04-01 01:51:35'),
(38, 1, '2026-04-06', '10:40:00', '11:20:00', 'Cancelación por odontólogo: EMERGENCIA', '2026-04-01 02:00:39'),
(39, 1, '2026-04-06', '08:00:00', '08:40:00', 'Cancelación por odontólogo: BBB', '2026-04-01 02:03:40'),
(40, 1, '2026-04-03', '08:00:00', '08:40:00', 'Cancelación por odontólogo: DERMATOLOGO', '2026-04-01 02:05:47');

-- --------------------------------------------------------

--
-- Table structure for table `tratamientos`
--

CREATE TABLE `tratamientos` (
  `id_tratamiento` int(11) NOT NULL,
  `id_paciente` int(11) NOT NULL,
  `id_odontologo` int(11) NOT NULL,
  `nombre_tratamiento` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `costo_total` decimal(10,2) NOT NULL,
  `total_pagado` decimal(10,2) DEFAULT 0.00,
  `saldo_pendiente` decimal(10,2) GENERATED ALWAYS AS (`costo_total` - `total_pagado`) STORED,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `estado` enum('pendiente','en_progreso','completado','cancelado') DEFAULT 'pendiente',
  `notas` text DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tratamientos`
--

INSERT INTO `tratamientos` (`id_tratamiento`, `id_paciente`, `id_odontologo`, `nombre_tratamiento`, `descripcion`, `costo_total`, `total_pagado`, `fecha_inicio`, `fecha_fin`, `estado`, `notas`, `fecha_creacion`) VALUES
(1, 1, 1, 'Consulta inicial', NULL, 150.00, 0.00, '2026-03-14', NULL, 'pendiente', NULL, '2026-03-14 14:18:09'),
(2, 1, 1, 'Limpieza dental', NULL, 200.00, 0.00, '2026-03-14', NULL, 'pendiente', NULL, '2026-03-14 14:18:09'),
(3, 4, 1, 'Limpieza dental', 'diente molar izquierdo', 100.00, 100.00, '2026-03-31', '2026-04-03', 'completado', '', '2026-03-30 19:21:23'),
(4, 5, 1, 'implante dental', 'retiral el molar superior', 500.00, 490.00, '2026-03-31', '2026-04-07', 'completado', '', '2026-03-31 01:43:56'),
(5, 5, 1, 'ortodincoa', 'colocacion de brakets', 75000.00, 75000.00, '2026-03-31', '2026-04-24', 'en_progreso', 'compra de brakets', '2026-03-31 01:46:15');

-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `contrasena_hash` varchar(255) NOT NULL,
  `nombre_completo` varchar(150) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `rol` enum('paciente','odontologo','admin') NOT NULL,
  `email_verificado` tinyint(1) DEFAULT 0,
  `codigo_verificacion` varchar(10) DEFAULT NULL,
  `codigo_expiracion` datetime DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_registro` datetime DEFAULT current_timestamp(),
  `ultimo_acceso` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `email`, `contrasena_hash`, `nombre_completo`, `telefono`, `rol`, `email_verificado`, `codigo_verificacion`, `codigo_expiracion`, `activo`, `fecha_registro`, `ultimo_acceso`) VALUES
(1, 'carlos.mamani@ecodent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Carlos Mamani', '77112233', 'odontologo', 1, NULL, NULL, 1, '2026-03-14 14:03:49', '2026-04-01 11:27:04'),
(2, 'maria.quispe@ecodent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dra. María Quispe', '77112234', 'odontologo', 1, NULL, NULL, 1, '2026-03-14 14:03:49', NULL),
(3, 'juan.perez@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Juan Pérez', '71234567', 'paciente', 1, NULL, NULL, 1, '2026-03-14 14:03:49', '2026-03-31 01:30:16'),
(4, 'richardocsachoqueherrera985@gmail.com', '$2y$10$8dC6Qdmyt8R/dfInimLiO.sI8dGTNV0LK5Hiehtf0iTXXB3AHaBU6', 'Richard Edmundo Herrera', '73537562', 'paciente', 1, NULL, NULL, 1, '2026-03-14 14:12:19', '2026-03-31 01:30:55'),
(5, 'ser@gmail.com', '$2y$10$JylLczXrWYIEqLFiLUhLSORT4ILIUJru0GEPsq.Cri50kaJ0A6t1K', 'sergio', '12347885', 'paciente', 1, NULL, NULL, 1, '2026-03-14 14:19:24', '2026-03-14 14:19:24'),
(6, 'jhamilth@gmail.com', '$2y$10$o/DKzsv0cTi3f8Wby6hFve/yXebNW8SgRImWhs4HlGCFach15/MUa', 'Jhamileth', '73537562', 'paciente', 1, NULL, NULL, 1, '2026-03-30 19:16:01', NULL),
(7, 'herreraocsachoquerichard985@gmail.com', '$2y$10$yoAqXbgIK6NEKDFZ0.UrdOzSL29IE8bCMJA5vXa07MPDCAGU3HqOq', 'edmundo', '73537562', 'paciente', 1, NULL, NULL, 1, '2026-03-31 01:05:24', '2026-04-01 02:23:59'),
(8, 'admin@ecodent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador Sistema', '77112233', 'admin', 1, NULL, NULL, 1, '2026-04-01 10:30:22', '2026-04-01 22:14:17');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `alertas`
--
ALTER TABLE `alertas`
  ADD PRIMARY KEY (`id_alerta`),
  ADD KEY `idx_usuario` (`id_usuario`),
  ADD KEY `idx_no_leidas` (`leida`);

--
-- Indexes for table `backups`
--
ALTER TABLE `backups`
  ADD PRIMARY KEY (`id_backup`);

--
-- Indexes for table `citas`
--
ALTER TABLE `citas`
  ADD PRIMARY KEY (`id_cita`),
  ADD KEY `idx_fecha_cita` (`fecha_cita`),
  ADD KEY `idx_odontologo_fecha` (`id_odontologo`,`fecha_cita`),
  ADD KEY `idx_paciente` (`id_paciente`),
  ADD KEY `idx_estado` (`estado`);

--
-- Indexes for table `estadisticas_odontologos`
--
ALTER TABLE `estadisticas_odontologos`
  ADD PRIMARY KEY (`id_estadistica`),
  ADD UNIQUE KEY `unique_odontologo_mes` (`id_odontologo`,`mes`,`anio`);

--
-- Indexes for table `historial_modificaciones_citas`
--
ALTER TABLE `historial_modificaciones_citas`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `id_cita` (`id_cita`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indexes for table `horarios_odontologos`
--
ALTER TABLE `horarios_odontologos`
  ADD PRIMARY KEY (`id_horario`),
  ADD KEY `idx_odontologo_dia` (`id_odontologo`,`dia_semana`);

--
-- Indexes for table `mensajes_pendientes`
--
ALTER TABLE `mensajes_pendientes`
  ADD PRIMARY KEY (`id_mensaje`),
  ADD KEY `idx_no_enviados` (`enviado`,`fecha_programado`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_cita` (`id_cita`);

--
-- Indexes for table `odontologos`
--
ALTER TABLE `odontologos`
  ADD PRIMARY KEY (`id_odontologo`),
  ADD UNIQUE KEY `id_usuario` (`id_usuario`),
  ADD KEY `idx_activo` (`activo`);

--
-- Indexes for table `opciones_reprogramacion_cita`
--
ALTER TABLE `opciones_reprogramacion_cita`
  ADD PRIMARY KEY (`id_opcion`),
  ADD KEY `idx_cita_original` (`id_cita_original`),
  ADD KEY `id_odontologo` (`id_odontologo`);

--
-- Indexes for table `pacientes`
--
ALTER TABLE `pacientes`
  ADD PRIMARY KEY (`id_paciente`),
  ADD UNIQUE KEY `id_usuario` (`id_usuario`),
  ADD KEY `idx_estado_cuenta` (`estado_cuenta`),
  ADD KEY `idx_ausencias` (`ausencias_sin_aviso`);

--
-- Indexes for table `pagos`
--
ALTER TABLE `pagos`
  ADD PRIMARY KEY (`id_pago`),
  ADD KEY `idx_paciente` (`id_paciente`),
  ADD KEY `idx_fecha` (`fecha_pago`),
  ADD KEY `idx_tratamiento` (`id_tratamiento`),
  ADD KEY `id_usuario_registro` (`id_usuario_registro`);

--
-- Indexes for table `reglas_alertas`
--
ALTER TABLE `reglas_alertas`
  ADD PRIMARY KEY (`id_regla`);

--
-- Indexes for table `slots_bloqueados`
--
ALTER TABLE `slots_bloqueados`
  ADD PRIMARY KEY (`id_bloqueo`),
  ADD KEY `idx_odontologo_fecha` (`id_odontologo`,`fecha`);

--
-- Indexes for table `tratamientos`
--
ALTER TABLE `tratamientos`
  ADD PRIMARY KEY (`id_tratamiento`),
  ADD KEY `idx_paciente` (`id_paciente`),
  ADD KEY `idx_odontologo` (`id_odontologo`);

--
-- Indexes for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_rol` (`rol`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `alertas`
--
ALTER TABLE `alertas`
  MODIFY `id_alerta` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `backups`
--
ALTER TABLE `backups`
  MODIFY `id_backup` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `citas`
--
ALTER TABLE `citas`
  MODIFY `id_cita` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `estadisticas_odontologos`
--
ALTER TABLE `estadisticas_odontologos`
  MODIFY `id_estadistica` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `historial_modificaciones_citas`
--
ALTER TABLE `historial_modificaciones_citas`
  MODIFY `id_historial` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `horarios_odontologos`
--
ALTER TABLE `horarios_odontologos`
  MODIFY `id_horario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `mensajes_pendientes`
--
ALTER TABLE `mensajes_pendientes`
  MODIFY `id_mensaje` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `odontologos`
--
ALTER TABLE `odontologos`
  MODIFY `id_odontologo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `opciones_reprogramacion_cita`
--
ALTER TABLE `opciones_reprogramacion_cita`
  MODIFY `id_opcion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `pacientes`
--
ALTER TABLE `pacientes`
  MODIFY `id_paciente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `pagos`
--
ALTER TABLE `pagos`
  MODIFY `id_pago` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `reglas_alertas`
--
ALTER TABLE `reglas_alertas`
  MODIFY `id_regla` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `slots_bloqueados`
--
ALTER TABLE `slots_bloqueados`
  MODIFY `id_bloqueo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `tratamientos`
--
ALTER TABLE `tratamientos`
  MODIFY `id_tratamiento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `alertas`
--
ALTER TABLE `alertas`
  ADD CONSTRAINT `alertas_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Constraints for table `citas`
--
ALTER TABLE `citas`
  ADD CONSTRAINT `citas_ibfk_1` FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id_paciente`) ON DELETE CASCADE,
  ADD CONSTRAINT `citas_ibfk_2` FOREIGN KEY (`id_odontologo`) REFERENCES `odontologos` (`id_odontologo`) ON DELETE CASCADE;

--
-- Constraints for table `estadisticas_odontologos`
--
ALTER TABLE `estadisticas_odontologos`
  ADD CONSTRAINT `estadisticas_odontologos_ibfk_1` FOREIGN KEY (`id_odontologo`) REFERENCES `odontologos` (`id_odontologo`) ON DELETE CASCADE;

--
-- Constraints for table `historial_modificaciones_citas`
--
ALTER TABLE `historial_modificaciones_citas`
  ADD CONSTRAINT `historial_modificaciones_citas_ibfk_1` FOREIGN KEY (`id_cita`) REFERENCES `citas` (`id_cita`) ON DELETE CASCADE,
  ADD CONSTRAINT `historial_modificaciones_citas_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Constraints for table `horarios_odontologos`
--
ALTER TABLE `horarios_odontologos`
  ADD CONSTRAINT `horarios_odontologos_ibfk_1` FOREIGN KEY (`id_odontologo`) REFERENCES `odontologos` (`id_odontologo`) ON DELETE CASCADE;

--
-- Constraints for table `mensajes_pendientes`
--
ALTER TABLE `mensajes_pendientes`
  ADD CONSTRAINT `mensajes_pendientes_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `mensajes_pendientes_ibfk_2` FOREIGN KEY (`id_cita`) REFERENCES `citas` (`id_cita`) ON DELETE SET NULL;

--
-- Constraints for table `odontologos`
--
ALTER TABLE `odontologos`
  ADD CONSTRAINT `odontologos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Constraints for table `opciones_reprogramacion_cita`
--
ALTER TABLE `opciones_reprogramacion_cita`
  ADD CONSTRAINT `opciones_reprogramacion_cita_ibfk_1` FOREIGN KEY (`id_cita_original`) REFERENCES `citas` (`id_cita`) ON DELETE CASCADE,
  ADD CONSTRAINT `opciones_reprogramacion_cita_ibfk_2` FOREIGN KEY (`id_odontologo`) REFERENCES `odontologos` (`id_odontologo`);

--
-- Constraints for table `pacientes`
--
ALTER TABLE `pacientes`
  ADD CONSTRAINT `pacientes_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Constraints for table `pagos`
--
ALTER TABLE `pagos`
  ADD CONSTRAINT `pagos_ibfk_1` FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id_paciente`) ON DELETE CASCADE,
  ADD CONSTRAINT `pagos_ibfk_2` FOREIGN KEY (`id_tratamiento`) REFERENCES `tratamientos` (`id_tratamiento`) ON DELETE SET NULL,
  ADD CONSTRAINT `pagos_ibfk_3` FOREIGN KEY (`id_usuario_registro`) REFERENCES `usuarios` (`id_usuario`);

--
-- Constraints for table `slots_bloqueados`
--
ALTER TABLE `slots_bloqueados`
  ADD CONSTRAINT `fk_slots_odontologo` FOREIGN KEY (`id_odontologo`) REFERENCES `odontologos` (`id_odontologo`) ON DELETE CASCADE;

--
-- Constraints for table `tratamientos`
--
ALTER TABLE `tratamientos`
  ADD CONSTRAINT `tratamientos_ibfk_1` FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id_paciente`) ON DELETE CASCADE,
  ADD CONSTRAINT `tratamientos_ibfk_2` FOREIGN KEY (`id_odontologo`) REFERENCES `odontologos` (`id_odontologo`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
