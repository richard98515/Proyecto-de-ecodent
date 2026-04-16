-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: ecodent
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `alertas`
--

DROP TABLE IF EXISTS `alertas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `alertas` (
  `id_alerta` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) DEFAULT NULL,
  `id_regla` int(11) DEFAULT NULL,
  `titulo` varchar(200) NOT NULL,
  `mensaje` text NOT NULL,
  `tipo` enum('info','warning','danger','success') DEFAULT 'info',
  `leida` tinyint(1) DEFAULT 0,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_alerta`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_no_leidas` (`leida`),
  CONSTRAINT `alertas_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `alertas`
--

LOCK TABLES `alertas` WRITE;
/*!40000 ALTER TABLE `alertas` DISABLE KEYS */;
INSERT INTO `alertas` VALUES (1,8,1,'⚠️ Paciente con 3+ ausencias - edmundo','El paciente tiene 6 ausencias sin aviso. Revisar estado de cuenta.','danger',0,'2026-04-02 00:06:21'),(2,8,1,'⚠️ Paciente con 3+ ausencias - Juan Pérez','El paciente tiene 7 ausencias sin aviso. Revisar estado de cuenta.','danger',0,'2026-04-02 00:06:21'),(3,8,2,'⚠️ Paciente con llegadas tarde - sergio','El paciente ha llegado tarde 4 veces.','warning',0,'2026-04-02 00:06:21'),(4,8,7,'⚠️ Paciente con 3+ ausencias - edmundo','El paciente edmundo tiene 6 ausencias sin aviso. Revisar estado de cuenta.','danger',1,'2026-04-02 00:11:21'),(5,8,7,'⚠️ Paciente con 3+ ausencias - Juan Pérez','El paciente Juan Pérez tiene 7 ausencias sin aviso. Revisar estado de cuenta.','danger',1,'2026-04-02 00:11:21'),(6,8,8,'⚠️ Paciente con llegadas tarde - sergio','El paciente sergio ha llegado tarde 4 veces.','warning',1,'2026-04-02 00:11:21'),(7,8,9,'⚠️ Paciente bloqueado - Juan Pérez','El paciente Juan Pérez está BLOQUEADO. No puede agendar citas.','danger',1,'2026-04-02 00:11:21'),(8,8,9,'⚠️ Paciente bloqueado - edmundo','El paciente edmundo está BLOQUEADO. No puede agendar citas.','danger',1,'2026-04-02 00:11:21');
/*!40000 ALTER TABLE `alertas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `backups`
--

DROP TABLE IF EXISTS `backups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backups` (
  `id_backup` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_archivo` varchar(255) NOT NULL,
  `tipo` enum('diario','semanal','mensual') NOT NULL,
  `tamano` int(11) DEFAULT NULL,
  `ruta` varchar(500) NOT NULL,
  `estado` enum('exitoso','fallido','en_progreso') DEFAULT 'exitoso',
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_backup`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `backups`
--

LOCK TABLES `backups` WRITE;
/*!40000 ALTER TABLE `backups` DISABLE KEYS */;
INSERT INTO `backups` VALUES (1,'ecodent_backup_diario_2026-04-02_05-32-53.sql','diario',56862,'C:\\xampp\\htdocs\\ecodent\\cron/../backups/ecodent_backup_diario_2026-04-02_05-32-53.sql','exitoso','2026-04-01 23:32:53'),(2,'ecodent_backup_diario_2026-04-02_06-06-21.sql','diario',57250,'C:\\xampp\\htdocs\\ecodent\\cron/../backups/ecodent_backup_diario_2026-04-02_06-06-21.sql','exitoso','2026-04-02 00:06:21');
/*!40000 ALTER TABLE `backups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `citas`
--

DROP TABLE IF EXISTS `citas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `citas` (
  `id_cita` int(11) NOT NULL AUTO_INCREMENT,
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
  `fecha_actualizacion` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_cita`),
  KEY `idx_fecha_cita` (`fecha_cita`),
  KEY `idx_odontologo_fecha` (`id_odontologo`,`fecha_cita`),
  KEY `idx_paciente` (`id_paciente`),
  KEY `idx_estado` (`estado`),
  CONSTRAINT `citas_ibfk_1` FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id_paciente`) ON DELETE CASCADE,
  CONSTRAINT `citas_ibfk_2` FOREIGN KEY (`id_odontologo`) REFERENCES `odontologos` (`id_odontologo`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `citas`
--

LOCK TABLES `citas` WRITE;
/*!40000 ALTER TABLE `citas` DISABLE KEYS */;
INSERT INTO `citas` VALUES (1,1,1,'2026-03-15','09:00:00','09:40:00','Consulta general - Dolor de muela','completada',0,3,1,1,25,NULL,NULL,NULL,0,NULL,NULL,'2026-03-14 14:18:09','2026-03-17 09:20:21'),(2,1,2,'2026-03-17','10:30:00','11:10:00','Limpieza dental','cancelada_pac',0,3,1,0,0,3,NULL,'2026-03-17 04:02:13',0,NULL,NULL,'2026-03-14 14:18:09','2026-03-17 04:02:13'),(3,1,1,'2026-03-19','15:00:00','15:40:00','Revisión de ortodoncia','cancelada_doc',0,3,1,0,0,1,'capacitacion','2026-03-17 03:11:49',0,NULL,NULL,'2026-03-14 14:18:09','2026-03-17 03:11:49'),(4,3,1,'2026-03-16','16:40:00','17:20:00','dolor de muela','cancelada_pac',0,3,1,0,0,5,NULL,'2026-03-14 14:27:44',0,'2026-03-15 16:40:00','2026-03-16 15:40:00','2026-03-14 14:24:01','2026-03-14 15:21:26'),(5,3,1,'2026-03-16','08:00:00','08:40:00','dolor de muela','cancelada_pac',0,3,1,0,0,5,NULL,'2026-03-14 14:33:04',0,'2026-03-15 08:00:00','2026-03-16 07:00:00','2026-03-14 14:27:31','2026-03-14 15:21:26'),(6,3,1,'2026-03-16','08:40:00','09:20:00','dolor de muela','cancelada_pac',0,3,1,0,0,5,NULL,'2026-03-14 14:30:03',0,'2026-03-15 08:40:00','2026-03-16 07:40:00','2026-03-14 14:28:23','2026-03-14 15:21:26'),(7,3,1,'2026-03-16','08:00:00','08:40:00','dolor de muela','completada',0,3,1,0,0,NULL,NULL,NULL,0,'2026-03-15 08:00:00','2026-03-16 07:00:00','2026-03-14 14:33:34','2026-03-14 15:21:26'),(8,2,1,'2026-03-17','08:00:00','08:40:00','limpieza','cancelada_doc',0,3,1,0,0,1,'emergencia','2026-03-16 15:15:05',0,'2026-03-16 08:00:00','2026-03-17 07:00:00','2026-03-14 15:05:28','2026-03-16 15:15:05'),(9,2,1,'2026-03-18','08:00:00','08:40:00','cirujia molar','cancelada_pac',0,3,1,0,0,4,NULL,'2026-03-17 02:45:19',0,NULL,NULL,'2026-03-16 06:11:22','2026-03-17 02:45:19'),(10,2,1,'2026-03-18','08:40:00','09:20:00','cirujia molar','cancelada_pac',0,3,1,0,0,4,NULL,'2026-03-17 05:06:14',0,NULL,NULL,'2026-03-16 06:11:22','2026-03-17 05:06:14'),(11,2,1,'2026-03-18','09:20:00','10:00:00','cirujia molar','cancelada_pac',0,3,1,0,0,4,NULL,'2026-03-17 05:06:13',0,NULL,NULL,'2026-03-16 06:11:22','2026-03-17 05:06:13'),(12,3,1,'2026-03-18','10:00:00','13:20:00','implante','programada',0,3,1,0,0,NULL,NULL,NULL,0,NULL,NULL,'2026-03-16 06:26:06',NULL),(13,3,1,'2026-03-16','15:20:00','16:00:00','molar','completada',0,3,1,1,20,NULL,NULL,NULL,0,NULL,NULL,'2026-03-16 06:53:40','2026-03-16 14:56:51'),(14,2,1,'2026-03-16','08:40:00','09:20:00','ciruji','ausente',0,3,1,0,0,NULL,NULL,NULL,0,NULL,NULL,'2026-03-16 06:58:14','2026-03-16 14:47:06'),(15,1,1,'2026-03-31','08:00:00','09:20:00','cirugia','cancelada_pac',0,3,1,0,0,3,NULL,'2026-03-17 04:00:43',0,NULL,NULL,'2026-03-16 06:59:19','2026-03-17 04:00:43'),(16,2,1,'2026-03-18','08:00:00','08:40:00','muela','completada',0,3,1,0,0,NULL,NULL,NULL,0,'2026-03-17 08:00:00','2026-03-18 07:00:00','2026-03-17 02:45:33','2026-03-17 02:46:31'),(17,2,1,'2026-03-17','10:00:00','10:40:00','si','cancelada_doc',0,3,1,0,0,1,'emergencia','2026-03-17 03:25:38',0,'2026-03-16 10:00:00','2026-03-17 09:00:00','2026-03-17 02:47:04','2026-03-17 03:25:38'),(18,2,1,'2026-03-17','10:00:00',NULL,'limpieza','cancelada_doc',0,3,1,0,0,1,'emergencia','2026-03-17 04:52:14',0,NULL,NULL,'2026-03-17 02:47:21','2026-03-17 04:52:14'),(19,2,1,'2026-03-17','11:20:00',NULL,'limpieza','cancelada_pac',0,3,1,0,0,4,NULL,'2026-03-17 05:06:18',0,NULL,NULL,'2026-03-17 02:47:37','2026-03-17 05:06:18'),(20,1,1,'2026-03-24','08:00:00','09:20:00','implante','cancelada_pac',0,3,1,0,0,3,NULL,'2026-03-17 03:56:56',0,NULL,NULL,'2026-03-17 03:06:46','2026-03-17 03:56:56'),(21,1,1,'2026-03-20','16:00:00',NULL,'Revisión de ortodoncia','cancelada_pac',0,3,1,0,0,3,NULL,'2026-03-17 03:50:21',0,NULL,NULL,'2026-03-17 03:12:51','2026-03-17 03:50:21'),(22,1,1,'2026-03-20','17:20:00',NULL,'Revisión de ortodoncia','cancelada_pac',0,3,1,0,0,3,NULL,'2026-03-17 03:50:25',0,NULL,NULL,'2026-03-17 03:26:36','2026-03-17 03:50:25'),(23,2,1,'2026-03-23','08:00:00','08:40:00','limpieza','cancelada_pac',0,3,1,0,0,4,NULL,'2026-03-17 05:06:10',0,'2026-03-22 08:00:00','2026-03-23 07:00:00','2026-03-17 04:59:06','2026-03-17 05:06:10'),(24,2,1,'2026-03-23','08:40:00','09:20:00','limpieza','cancelada_pac',0,3,1,0,0,4,NULL,'2026-03-17 05:06:08',0,'2026-03-22 08:40:00','2026-03-23 07:40:00','2026-03-17 04:59:28','2026-03-17 05:06:08'),(25,2,1,'2026-03-18','08:00:00',NULL,'si','cancelada_pac',0,3,1,0,0,4,NULL,'2026-03-17 05:06:16',0,'2026-03-17 08:00:00','2026-03-18 07:00:00','2026-03-17 05:02:41','2026-03-17 05:06:16'),(26,2,1,'2026-03-18','10:40:00',NULL,'si','cancelada_pac',0,3,1,0,0,4,NULL,'2026-03-17 05:06:11',0,'2026-03-17 10:40:00','2026-03-18 09:40:00','2026-03-17 05:02:58','2026-03-17 05:06:11'),(27,2,1,'2026-03-23','08:00:00','08:40:00','limpieza dental','cancelada_doc',0,3,1,0,0,1,'emergencia personal','2026-03-17 05:08:10',0,'2026-03-22 08:00:00','2026-03-23 07:00:00','2026-03-17 05:06:50','2026-03-17 05:08:10'),(28,2,1,'2026-03-27','09:20:00','10:00:00','limpieza dental','cancelada_doc',0,3,1,0,0,1,'emergencia','2026-03-17 09:17:51',0,'2026-03-26 09:20:00','2026-03-27 08:20:00','2026-03-17 05:09:11','2026-03-17 09:17:51'),(29,2,1,'2026-03-25','08:00:00','08:40:00','limpieza dental','completada',0,3,1,0,0,NULL,NULL,NULL,0,'2026-03-24 08:00:00','2026-03-25 07:00:00','2026-03-17 09:18:52','2026-03-25 18:23:55'),(30,2,1,'2026-03-27','16:00:00','17:20:00','implante','completada',0,3,1,0,0,NULL,NULL,NULL,0,NULL,NULL,'2026-03-25 18:17:42','2026-03-25 19:17:23'),(31,2,1,'2026-03-31','08:00:00','09:20:00','Cirujia molar','programada',0,3,1,0,0,NULL,NULL,NULL,0,NULL,NULL,'2026-03-30 15:10:18',NULL),(32,2,1,'2026-03-31','17:20:00','18:00:00','muela','cancelada_doc',0,3,1,0,0,1,'capacitacion','2026-03-30 15:13:36',0,NULL,NULL,'2026-03-30 15:11:35','2026-03-30 15:13:36'),(33,2,1,'2026-03-31','09:20:00','10:00:00','implante','completada',0,3,1,0,0,NULL,NULL,NULL,0,NULL,NULL,'2026-03-30 15:15:01','2026-03-30 15:15:11'),(34,3,1,'2026-03-31','10:00:00','10:40:00','implante','completada',0,3,1,1,15,NULL,NULL,NULL,0,NULL,NULL,'2026-03-30 15:27:27','2026-03-30 15:27:35'),(35,1,1,'2026-03-31','10:40:00','11:20:00','mulas','ausente',0,3,1,0,0,NULL,NULL,NULL,0,NULL,NULL,'2026-03-30 15:28:10','2026-03-30 15:28:16'),(36,2,1,'2026-04-03','09:20:00','10:00:00','muela','cancelada_pac',3,3,0,0,0,4,NULL,'2026-03-31 00:34:47',0,NULL,NULL,'2026-03-30 15:54:55','2026-03-31 00:34:47'),(37,4,1,'2026-04-03','08:00:00','08:40:00','limpieza','cancelada_doc',0,3,1,0,0,1,'DERMATOLOGO','2026-04-01 02:05:47',0,NULL,NULL,'2026-03-30 19:16:29','2026-04-01 02:05:47'),(38,1,1,'2026-04-02','08:00:00','08:40:00','muela','ausente',0,3,1,0,0,NULL,NULL,NULL,0,'2026-04-01 08:00:00','2026-04-02 07:00:00','2026-03-30 20:09:20','2026-03-30 21:37:12'),(39,1,1,'2026-03-31','08:40:00','09:20:00','muela','ausente',0,3,1,0,0,NULL,NULL,NULL,0,'2026-03-30 08:40:00','2026-03-31 07:40:00','2026-03-30 21:17:15','2026-03-30 21:36:39'),(40,1,1,'2026-03-31','10:00:00','10:40:00','dolor','ausente',0,3,1,0,0,NULL,NULL,NULL,0,'2026-03-30 10:00:00','2026-03-31 09:00:00','2026-03-30 21:17:24','2026-03-30 21:36:51'),(41,2,1,'2026-04-03','08:40:00','09:20:00','muela','cancelada_doc',1,3,1,0,0,1,'EMERGENCIA','2026-04-01 01:51:35',0,'2026-03-30 15:20:00','2026-03-31 14:20:00','2026-03-31 00:13:22','2026-04-01 01:51:35'),(42,2,1,'2026-04-03','10:00:00','12:00:00','blanqueamiento','cancelada_pac',0,3,1,0,0,4,NULL,'2026-03-31 00:34:49',0,NULL,NULL,'2026-03-31 00:26:37','2026-03-31 00:34:49'),(43,2,1,'2026-04-06','08:00:00','10:40:00','protesis','cancelada_doc',0,3,1,0,0,1,'BBB','2026-04-01 02:03:40',0,NULL,NULL,'2026-03-31 00:33:30','2026-04-01 02:03:40'),(44,5,1,'2026-04-06','10:40:00','11:20:00','consulta','cancelada_doc',0,3,1,0,0,1,'EMERGENCIA','2026-04-01 02:00:39',0,'2026-04-05 10:40:00','2026-04-06 09:40:00','2026-03-31 01:24:49','2026-04-01 02:00:39'),(45,3,1,'2026-04-02','08:40:00','10:40:00','revision','cancelada_doc',0,3,1,0,0,1,'CAPACITACION','2026-04-01 01:50:23',0,'2026-04-01 01:23:59',NULL,'2026-03-31 01:37:44','2026-04-01 01:50:23'),(46,5,1,'2026-04-06','08:40:00','09:20:00','consulta','ausente',0,3,1,0,0,NULL,NULL,NULL,0,'2026-04-05 08:40:00','2026-04-06 07:40:00','2026-04-01 02:24:16','2026-04-01 02:25:30'),(47,5,1,'2026-04-13','08:00:00','08:40:00','dolor de muela','ausente',0,3,1,0,0,NULL,NULL,NULL,0,'2026-04-12 08:00:00','2026-04-13 07:00:00','2026-04-01 02:24:34','2026-04-01 02:25:43'),(48,5,1,'2026-04-20','08:00:00','08:40:00','dolor de muela','ausente',0,3,1,0,0,NULL,NULL,NULL,0,'2026-04-19 08:00:00','2026-04-20 07:00:00','2026-04-01 02:24:51','2026-04-01 02:25:49'),(49,5,1,'2026-04-06','09:20:00','10:00:00','consulta_general','completada',0,3,1,0,0,NULL,NULL,NULL,0,NULL,NULL,'2026-04-01 02:34:55','2026-04-01 02:35:29'),(50,5,1,'2026-04-06','10:00:00','10:40:00','extraccion','completada',0,3,1,0,0,NULL,NULL,NULL,0,NULL,NULL,'2026-04-01 02:35:08','2026-04-01 02:35:36'),(51,5,1,'2026-04-06','11:20:00','12:00:00','blanqueamiento','completada',0,3,1,0,0,NULL,NULL,NULL,0,NULL,NULL,'2026-04-01 02:35:22','2026-04-01 02:35:44'),(52,4,1,'2026-04-27','08:00:00','08:40:00','ortodoncia','programada',0,3,1,0,0,NULL,NULL,NULL,0,NULL,NULL,'2026-04-01 22:50:17',NULL),(53,5,2,'2026-04-08','09:00:00','09:40:00','blanqueamiento','completada',0,3,1,0,0,NULL,NULL,NULL,0,NULL,NULL,'2026-04-01 22:52:41','2026-04-02 00:30:45'),(54,5,1,'2026-04-01','16:00:00','16:40:00','protesis','completada',0,3,1,0,0,NULL,NULL,NULL,0,NULL,NULL,'2026-04-01 23:47:10','2026-04-01 23:47:23');
/*!40000 ALTER TABLE `citas` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER incrementar_cambios_cita 
BEFORE UPDATE ON citas 
FOR EACH ROW 
BEGIN
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
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER actualizar_paciente_after_cita 
AFTER UPDATE ON citas 
FOR EACH ROW 
BEGIN
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
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER actualizar_estado_after_cita
AFTER UPDATE ON citas
FOR EACH ROW
BEGIN
    -- Si la cita se marcó como ausente
    IF NEW.estado = 'ausente' AND OLD.estado != 'ausente' THEN
        CALL verificar_estado_cuenta(NEW.id_paciente);
    END IF;
    
    -- Si la cita se marcó como completada y llegó tarde
    IF NEW.estado = 'completada' AND OLD.estado != 'completada' AND NEW.llego_tarde = 1 THEN
        CALL verificar_estado_cuenta(NEW.id_paciente);
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER registrar_historial_citas
AFTER UPDATE ON citas
FOR EACH ROW
BEGIN
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
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `estadisticas_odontologos`
--

DROP TABLE IF EXISTS `estadisticas_odontologos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `estadisticas_odontologos` (
  `id_estadistica` int(11) NOT NULL AUTO_INCREMENT,
  `id_odontologo` int(11) NOT NULL,
  `mes` int(11) NOT NULL,
  `anio` int(11) NOT NULL,
  `total_citas` int(11) DEFAULT 0,
  `citas_completadas` int(11) DEFAULT 0,
  `ausencias` int(11) DEFAULT 0,
  `llegadas_tarde` int(11) DEFAULT 0,
  `ingresos_totales` decimal(10,2) DEFAULT 0.00,
  `fecha_calculo` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_estadistica`),
  UNIQUE KEY `unique_odontologo_mes` (`id_odontologo`,`mes`,`anio`),
  CONSTRAINT `estadisticas_odontologos_ibfk_1` FOREIGN KEY (`id_odontologo`) REFERENCES `odontologos` (`id_odontologo`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estadisticas_odontologos`
--

LOCK TABLES `estadisticas_odontologos` WRITE;
/*!40000 ALTER TABLE `estadisticas_odontologos` DISABLE KEYS */;
INSERT INTO `estadisticas_odontologos` VALUES (1,1,4,2026,16,4,4,0,40.00,'2026-04-02 11:52:38'),(2,2,4,2026,1,1,0,0,0.00,'2026-04-02 11:52:38');
/*!40000 ALTER TABLE `estadisticas_odontologos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `historial_modificaciones_citas`
--

DROP TABLE IF EXISTS `historial_modificaciones_citas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `historial_modificaciones_citas` (
  `id_historial` int(11) NOT NULL AUTO_INCREMENT,
  `id_cita` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_anterior` date DEFAULT NULL,
  `hora_anterior` time DEFAULT NULL,
  `fecha_nueva` date DEFAULT NULL,
  `hora_nueva` time DEFAULT NULL,
  `fecha_modificacion` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_historial`),
  KEY `id_cita` (`id_cita`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `historial_modificaciones_citas_ibfk_1` FOREIGN KEY (`id_cita`) REFERENCES `citas` (`id_cita`) ON DELETE CASCADE,
  CONSTRAINT `historial_modificaciones_citas_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `historial_modificaciones_citas`
--

LOCK TABLES `historial_modificaciones_citas` WRITE;
/*!40000 ALTER TABLE `historial_modificaciones_citas` DISABLE KEYS */;
INSERT INTO `historial_modificaciones_citas` VALUES (1,36,4,'2026-04-01','08:00:00','2026-04-10','10:00:00','2026-03-31 00:01:10'),(2,41,4,'2026-03-31','15:20:00','2026-04-03','08:40:00','2026-03-31 00:16:54'),(3,36,4,'2026-04-10','10:00:00','2026-03-31','08:40:00','2026-03-31 00:17:19'),(4,36,4,'2026-03-31','08:40:00','2026-04-03','09:20:00','2026-03-31 00:29:18');
/*!40000 ALTER TABLE `historial_modificaciones_citas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `horarios_odontologos`
--

DROP TABLE IF EXISTS `horarios_odontologos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `horarios_odontologos` (
  `id_horario` int(11) NOT NULL AUTO_INCREMENT,
  `id_odontologo` int(11) NOT NULL,
  `dia_semana` enum('lunes','martes','miercoles','jueves','viernes','sabado','domingo') NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id_horario`),
  KEY `idx_odontologo_dia` (`id_odontologo`,`dia_semana`),
  CONSTRAINT `horarios_odontologos_ibfk_1` FOREIGN KEY (`id_odontologo`) REFERENCES `odontologos` (`id_odontologo`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `horarios_odontologos`
--

LOCK TABLES `horarios_odontologos` WRITE;
/*!40000 ALTER TABLE `horarios_odontologos` DISABLE KEYS */;
INSERT INTO `horarios_odontologos` VALUES (1,1,'lunes','08:00:00','20:00:00',1),(2,1,'martes','08:00:00','18:00:00',1),(3,1,'miercoles','08:00:00','18:00:00',1),(4,1,'jueves','08:00:00','18:00:00',1),(5,1,'viernes','08:00:00','18:00:00',1),(6,2,'lunes','09:00:00','17:00:00',1),(7,2,'martes','09:00:00','17:00:00',1),(8,2,'miercoles','09:00:00','17:00:00',1),(9,2,'jueves','09:00:00','17:00:00',1),(10,2,'viernes','09:00:00','17:00:00',1);
/*!40000 ALTER TABLE `horarios_odontologos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mensajes_pendientes`
--

DROP TABLE IF EXISTS `mensajes_pendientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mensajes_pendientes` (
  `id_mensaje` int(11) NOT NULL AUTO_INCREMENT,
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
  `fecha_registro` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_mensaje`),
  KEY `idx_no_enviados` (`enviado`,`fecha_programado`),
  KEY `id_usuario` (`id_usuario`),
  KEY `id_cita` (`id_cita`),
  CONSTRAINT `mensajes_pendientes_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `mensajes_pendientes_ibfk_2` FOREIGN KEY (`id_cita`) REFERENCES `citas` (`id_cita`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mensajes_pendientes`
--

LOCK TABLES `mensajes_pendientes` WRITE;
/*!40000 ALTER TABLE `mensajes_pendientes` DISABLE KEYS */;
INSERT INTO `mensajes_pendientes` VALUES (1,5,5,'cancelacion','email','Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.',NULL,NULL,1,'2026-03-14 14:33:04','2026-04-01 01:23:46','2026-03-14 14:33:04'),(2,4,8,'','email','Tu cita ha sido cancelada por el odontólogo. Motivo: emergencia. \r\n                    Por favor, ingresa al sistema para elegir una nueva fecha entre las opciones disponibles.',NULL,NULL,1,'2026-03-16 15:15:05','2026-04-01 01:23:53','2026-03-16 15:15:05'),(3,4,9,'cancelacion','email','Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.',NULL,NULL,1,'2026-03-17 02:45:19','2026-04-01 01:24:00','2026-03-17 02:45:19'),(4,3,3,'','email','Tu cita ha sido cancelada por el odontólogo. Motivo: capacitacion. \r\n                    Por favor, ingresa al sistema para elegir una nueva fecha entre las opciones disponibles.',NULL,NULL,1,'2026-03-17 03:11:49','2026-04-01 01:24:07','2026-03-17 03:11:49'),(5,4,17,'','email','Tu cita ha sido cancelada por el odontólogo. Motivo: emergencia. \r\n                    Por favor, ingresa al sistema para elegir una nueva fecha entre las opciones disponibles.',NULL,NULL,1,'2026-03-17 03:25:39','2026-04-01 01:24:16','2026-03-17 03:25:39'),(6,3,21,'cancelacion','email','Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.',NULL,NULL,1,'2026-03-17 03:50:21','2026-04-01 01:24:24','2026-03-17 03:50:21'),(7,3,22,'cancelacion','email','Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.',NULL,NULL,1,'2026-03-17 03:50:25','2026-04-01 01:24:34','2026-03-17 03:50:25'),(8,3,20,'cancelacion','email','Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.',NULL,NULL,1,'2026-03-17 03:56:56','2026-04-01 01:24:42','2026-03-17 03:56:56'),(9,3,15,'cancelacion','email','Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.',NULL,NULL,1,'2026-03-17 04:00:43','2026-04-01 01:24:51','2026-03-17 04:00:43'),(10,3,2,'cancelacion','email','Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.',NULL,NULL,1,'2026-03-17 04:02:13','2026-04-01 01:24:58','2026-03-17 04:02:13'),(11,4,18,'','email','Tu cita ha sido cancelada por el odontólogo. Motivo: emergencia. \r\n                    Por favor, ingresa al sistema para elegir una nueva fecha entre las opciones disponibles.',NULL,NULL,1,'2026-03-17 04:52:14','2026-04-01 01:25:04','2026-03-17 04:52:14'),(12,4,24,'cancelacion','email','Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.',NULL,NULL,1,'2026-03-17 05:06:08','2026-04-01 01:25:10','2026-03-17 05:06:08'),(13,4,23,'cancelacion','email','Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.',NULL,NULL,1,'2026-03-17 05:06:10','2026-04-01 01:25:17','2026-03-17 05:06:10'),(14,4,26,'cancelacion','email','Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.',NULL,NULL,1,'2026-03-17 05:06:11','2026-04-01 01:25:26','2026-03-17 05:06:11'),(15,4,11,'cancelacion','email','Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.',NULL,NULL,1,'2026-03-17 05:06:13','2026-04-01 01:25:34','2026-03-17 05:06:13'),(16,4,10,'cancelacion','email','Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.',NULL,NULL,1,'2026-03-17 05:06:14','2026-04-01 01:43:58','2026-03-17 05:06:14'),(17,4,25,'cancelacion','email','Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.',NULL,NULL,1,'2026-03-17 05:06:16','2026-04-01 01:44:05','2026-03-17 05:06:16'),(18,4,19,'cancelacion','email','Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.',NULL,NULL,1,'2026-03-17 05:06:18','2026-04-01 01:44:11','2026-03-17 05:06:18'),(19,4,27,'','email','Tu cita ha sido cancelada por el odontólogo. Motivo: emergencia personal. \r\n                    Por favor, ingresa al sistema para elegir una nueva fecha entre las opciones disponibles.',NULL,NULL,1,'2026-03-17 05:08:10','2026-04-01 01:44:18','2026-03-17 05:08:10'),(20,4,28,'','email','Tu cita ha sido cancelada por el odontólogo. Motivo: emergencia. \r\n                    Por favor, ingresa al sistema para elegir una nueva fecha entre las opciones disponibles.',NULL,NULL,1,'2026-03-17 09:17:51','2026-04-01 01:44:25','2026-03-17 09:17:51'),(21,4,32,'','email','Tu cita ha sido cancelada por el odontólogo. Motivo: capacitacion. \r\n                    Por favor, ingresa al sistema para elegir una nueva fecha entre las opciones disponibles.',NULL,NULL,1,'2026-03-30 15:13:36','2026-04-01 01:44:31','2026-03-30 15:13:36'),(22,4,36,'cancelacion','email','Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.',NULL,NULL,1,'2026-03-31 00:34:47','2026-04-01 01:44:38','2026-03-31 00:34:47'),(23,4,42,'cancelacion','email','Tu cita ha sido cancelada exitosamente. Puedes agendar un nuevo horario cuando lo desees.',NULL,NULL,1,'2026-03-31 00:34:49','2026-04-01 01:44:44','2026-03-31 00:34:49'),(24,5,45,'','email','Tu cita del 02/04/2026 a las 08:40 ha sido cancelada por el odontólogo. \r\nMotivo: CAPACITACION. \r\nPor favor, ingresa al sistema para elegir una nueva fecha entre las opciones disponibles.','12347885','ser@gmail.com',1,'2026-04-01 01:50:23','2026-04-01 23:32:30','2026-04-01 01:50:23'),(25,5,45,'','whatsapp','🦷 *EcoDent - Cita Cancelada*\n\nHola sergio, lamentamos informarte que tu cita del *02/04/2026* a las *08:40* ha sido cancelada.\n\n📋 *Motivo:* CAPACITACION\n\n📅 *Opciones de reprogramación disponibles:*\n  1. 02/04/2026 a las 08:00\n  2. 02/04/2026 a las 10:40\n  3. 02/04/2026 a las 11:20\n\nPor favor ingresa al sistema EcoDent para elegir tu nueva fecha.\n📞 Tel: 77112233','12347885','ser@gmail.com',0,'2026-04-01 01:50:23',NULL,'2026-04-01 01:50:23'),(26,4,41,'','email','Tu cita del 03/04/2026 a las 08:40 ha sido cancelada por el odontólogo. \r\nMotivo: EMERGENCIA. \r\nPor favor, ingresa al sistema para elegir una nueva fecha entre las opciones disponibles.','73537562','richardocsachoqueherrera985@gmail.com',1,'2026-04-01 01:51:35','2026-04-01 23:32:35','2026-04-01 01:51:35'),(27,4,41,'','whatsapp','🦷 *EcoDent - Cita Cancelada*\n\nHola Richard Edmundo Herrera, lamentamos informarte que tu cita del *03/04/2026* a las *08:40* ha sido cancelada.\n\n📋 *Motivo:* EMERGENCIA\n\n📅 *Opciones de reprogramación disponibles:*\n  1. 02/04/2026 a las 09:20\n  2. 02/04/2026 a las 08:00\n\nPor favor ingresa al sistema EcoDent para elegir tu nueva fecha.\n📞 Tel: 77112233','73537562','richardocsachoqueherrera985@gmail.com',0,'2026-04-01 01:51:35',NULL,'2026-04-01 01:51:35'),(28,7,44,'cancelacion_doctor','email','Tu cita del 06/04/2026 a las 10:40 ha sido cancelada por el odontólogo. \r\nMotivo: EMERGENCIA. \r\nPor favor, ingresa al sistema para elegir una nueva fecha entre las opciones disponibles.','73537562','herreraocsachoquerichard985@gmail.com',1,'2026-04-01 02:00:39','2026-04-01 23:32:40','2026-04-01 02:00:39'),(29,7,44,'cancelacion_doctor','whatsapp','🦷 *EcoDent - Cita Cancelada*\n\nHola edmundo, lamentamos informarte que tu cita del *06/04/2026* a las *10:40* ha sido cancelada.\n\n📋 *Motivo:* EMERGENCIA\n\n📅 *Opciones de reprogramación disponibles:*\n  1. 17/04/2026 a las 08:00\n  2. 17/04/2026 a las 08:40\n  3. 17/04/2026 a las 09:20\n\nPor favor ingresa al sistema EcoDent para elegir tu nueva fecha.\n📞 Tel: 77112233','73537562','herreraocsachoquerichard985@gmail.com',0,'2026-04-01 02:00:39',NULL,'2026-04-01 02:00:39'),(30,4,43,'cancelacion_doctor','email','Tu cita del 06/04/2026 a las 08:00 ha sido cancelada por el odontólogo. \r\nMotivo: BBB. \r\nPor favor, ingresa al sistema para elegir una nueva fecha entre las opciones disponibles.','73537562','richardocsachoqueherrera985@gmail.com',1,'2026-04-01 02:03:40','2026-04-01 23:32:47','2026-04-01 02:03:40'),(31,4,43,'cancelacion_doctor','whatsapp','🦷 *EcoDent - Cita Cancelada*\n\nHola Richard Edmundo Herrera, lamentamos informarte que tu cita del *06/04/2026* a las *08:00* ha sido cancelada.\n\n📋 *Motivo:* BBB\n\n📅 *Opciones de reprogramación disponibles:*\n  1. 02/04/2026 a las 09:20\n  2. 02/04/2026 a las 10:00\n  3. 02/04/2026 a las 12:00\n\nPor favor ingresa al sistema EcoDent para elegir tu nueva fecha.\n📞 Tel: 77112233','73537562','richardocsachoqueherrera985@gmail.com',0,'2026-04-01 02:03:40',NULL,'2026-04-01 02:03:40'),(32,6,37,'cancelacion_doctor','email','Tu cita del 03/04/2026 a las 08:00 ha sido cancelada por el odontólogo. \r\nMotivo: DERMATOLOGO. \r\nPor favor, ingresa al sistema para elegir una nueva fecha entre las opciones disponibles.','73537562','jhamilth@gmail.com',1,'2026-04-01 02:05:47','2026-04-01 23:32:52','2026-04-01 02:05:47'),(33,6,37,'cancelacion_doctor','whatsapp','🦷 *EcoDent - Cita Cancelada*\n\nHola Jhamileth, lamentamos informarte que tu cita del *03/04/2026* a las *08:00* ha sido cancelada.\n\n📋 *Motivo:* DERMATOLOGO\n\n📅 *Opciones de reprogramación disponibles:*\n  1. 02/04/2026 a las 08:00\n  2. 02/04/2026 a las 09:20\n  3. 02/04/2026 a las 10:00\n\nPor favor ingresa al sistema EcoDent para elegir tu nueva fecha.\n📞 Tel: 77112233','73537562','jhamilth@gmail.com',0,'2026-04-01 02:05:47',NULL,'2026-04-01 02:05:47');
/*!40000 ALTER TABLE `mensajes_pendientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `odontologos`
--

DROP TABLE IF EXISTS `odontologos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `odontologos` (
  `id_odontologo` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `especialidad_principal` varchar(100) NOT NULL,
  `especialidades_adicionales` text DEFAULT NULL,
  `duracion_cita_min` int(11) DEFAULT 40,
  `max_citas_dia` int(11) DEFAULT 8,
  `color_calendario` varchar(7) DEFAULT '#2E75B6',
  `activo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id_odontologo`),
  UNIQUE KEY `id_usuario` (`id_usuario`),
  KEY `idx_activo` (`activo`),
  CONSTRAINT `odontologos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `odontologos`
--

LOCK TABLES `odontologos` WRITE;
/*!40000 ALTER TABLE `odontologos` DISABLE KEYS */;
INSERT INTO `odontologos` VALUES (1,1,'Ortodoncia',NULL,40,8,'#2E75B6',1),(2,2,'Endodoncia',NULL,40,8,'#C00000',1);
/*!40000 ALTER TABLE `odontologos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `opciones_reprogramacion_cita`
--

DROP TABLE IF EXISTS `opciones_reprogramacion_cita`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `opciones_reprogramacion_cita` (
  `id_opcion` int(11) NOT NULL AUTO_INCREMENT,
  `id_cita_original` int(11) NOT NULL,
  `fecha_propuesta` date NOT NULL,
  `hora_propuesta` time NOT NULL,
  `hora_propuesta_fin` time DEFAULT NULL,
  `id_odontologo` int(11) NOT NULL,
  `seleccionada` tinyint(1) DEFAULT 0,
  `fecha_registro` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_opcion`),
  KEY `idx_cita_original` (`id_cita_original`),
  KEY `id_odontologo` (`id_odontologo`),
  CONSTRAINT `opciones_reprogramacion_cita_ibfk_1` FOREIGN KEY (`id_cita_original`) REFERENCES `citas` (`id_cita`) ON DELETE CASCADE,
  CONSTRAINT `opciones_reprogramacion_cita_ibfk_2` FOREIGN KEY (`id_odontologo`) REFERENCES `odontologos` (`id_odontologo`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `opciones_reprogramacion_cita`
--

LOCK TABLES `opciones_reprogramacion_cita` WRITE;
/*!40000 ALTER TABLE `opciones_reprogramacion_cita` DISABLE KEYS */;
INSERT INTO `opciones_reprogramacion_cita` VALUES (1,8,'2026-03-17','10:00:00',NULL,1,1,'2026-03-16 15:15:05'),(2,8,'2026-03-17','11:20:00',NULL,1,1,'2026-03-16 15:15:05'),(3,3,'2026-03-20','16:00:00',NULL,1,1,'2026-03-17 03:11:49'),(4,3,'2026-03-20','17:20:00',NULL,1,1,'2026-03-17 03:11:49'),(5,17,'2026-03-18','08:00:00',NULL,1,1,'2026-03-17 03:25:39'),(6,17,'2026-03-18','10:40:00',NULL,1,1,'2026-03-17 03:25:39'),(7,17,'2026-03-18','11:20:00',NULL,1,0,'2026-03-17 03:25:39'),(8,18,'2026-03-23','08:00:00','08:40:00',1,1,'2026-03-17 04:52:14'),(9,18,'2026-03-23','08:40:00','09:20:00',1,1,'2026-03-17 04:52:14'),(10,18,'2026-03-23','09:20:00','10:00:00',1,0,'2026-03-17 04:52:14'),(11,27,'2026-03-27','08:40:00','09:20:00',1,0,'2026-03-17 05:08:10'),(12,27,'2026-03-27','09:20:00','10:00:00',1,1,'2026-03-17 05:08:10'),(13,27,'2026-03-27','08:00:00','08:40:00',1,0,'2026-03-17 05:08:10'),(14,28,'2026-03-24','08:00:00','08:40:00',1,0,'2026-03-17 09:17:51'),(15,28,'2026-03-24','09:20:00','10:00:00',1,0,'2026-03-17 09:17:51'),(16,28,'2026-03-25','08:00:00','08:40:00',1,1,'2026-03-17 09:17:51'),(17,32,'2026-03-31','16:40:00','17:20:00',1,0,'2026-03-30 15:13:36'),(18,32,'2026-03-31','14:40:00','15:20:00',1,0,'2026-03-30 15:13:36'),(19,32,'2026-03-31','15:20:00','16:00:00',1,1,'2026-03-30 15:13:36'),(20,32,'2026-03-31','16:00:00','16:40:00',1,0,'2026-03-30 15:13:36'),(21,45,'2026-04-02','08:00:00','08:40:00',1,0,'2026-04-01 01:50:23'),(22,45,'2026-04-02','10:40:00','11:20:00',1,0,'2026-04-01 01:50:23'),(23,45,'2026-04-02','11:20:00','12:00:00',1,0,'2026-04-01 01:50:23'),(24,41,'2026-04-02','09:20:00','10:00:00',1,0,'2026-04-01 01:51:35'),(25,41,'2026-04-02','08:00:00','08:40:00',1,0,'2026-04-01 01:51:35'),(26,44,'2026-04-17','08:00:00','08:40:00',1,0,'2026-04-01 02:00:39'),(27,44,'2026-04-17','08:40:00','09:20:00',1,0,'2026-04-01 02:00:39'),(28,44,'2026-04-17','09:20:00','10:00:00',1,0,'2026-04-01 02:00:39'),(29,43,'2026-04-02','09:20:00','10:00:00',1,0,'2026-04-01 02:03:40'),(30,43,'2026-04-02','10:00:00','10:40:00',1,0,'2026-04-01 02:03:40'),(31,43,'2026-04-02','12:00:00','12:40:00',1,0,'2026-04-01 02:03:40'),(32,37,'2026-04-02','08:00:00','08:40:00',1,0,'2026-04-01 02:05:47'),(33,37,'2026-04-02','09:20:00','10:00:00',1,0,'2026-04-01 02:05:47'),(34,37,'2026-04-02','10:00:00','10:40:00',1,0,'2026-04-01 02:05:47');
/*!40000 ALTER TABLE `opciones_reprogramacion_cita` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pacientes`
--

DROP TABLE IF EXISTS `pacientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pacientes` (
  `id_paciente` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `ausencias_sin_aviso` int(11) DEFAULT 0,
  `llegadas_tarde` int(11) DEFAULT 0,
  `fecha_ultima_ausencia` date DEFAULT NULL,
  `estado_cuenta` enum('normal','observacion','restringida','bloqueada') DEFAULT 'normal',
  `puede_agendar` tinyint(1) DEFAULT 1,
  `limite_citas_simultaneas` int(11) DEFAULT 3,
  `fecha_actualizacion_estado` datetime DEFAULT NULL,
  PRIMARY KEY (`id_paciente`),
  UNIQUE KEY `id_usuario` (`id_usuario`),
  KEY `idx_estado_cuenta` (`estado_cuenta`),
  KEY `idx_ausencias` (`ausencias_sin_aviso`),
  CONSTRAINT `pacientes_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pacientes`
--

LOCK TABLES `pacientes` WRITE;
/*!40000 ALTER TABLE `pacientes` DISABLE KEYS */;
INSERT INTO `pacientes` VALUES (1,3,'1985-05-15','Calle Potosí #123, La Paz',7,1,'2026-04-02','bloqueada',0,0,'2026-03-30 21:37:12'),(2,4,NULL,NULL,1,1,'2026-03-16','normal',1,3,NULL),(3,5,NULL,NULL,0,4,NULL,'observacion',1,2,'2026-04-01 02:20:23'),(4,6,'2021-11-22','z/villa Dolores C/sebarullo N/1814',0,0,NULL,'normal',1,3,'2026-04-01 02:20:23'),(5,7,NULL,NULL,6,0,'2026-04-20','bloqueada',0,0,'2026-04-01 02:25:49');
/*!40000 ALTER TABLE `pacientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pagos`
--

DROP TABLE IF EXISTS `pagos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pagos` (
  `id_pago` int(11) NOT NULL AUTO_INCREMENT,
  `id_paciente` int(11) NOT NULL,
  `id_tratamiento` int(11) DEFAULT NULL,
  `id_usuario_registro` int(11) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `concepto` varchar(255) NOT NULL,
  `fecha_pago` date NOT NULL,
  `foto_comprobante` varchar(255) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `metodo_pago` enum('efectivo','tarjeta','transferencia','otro') DEFAULT 'efectivo',
  `fecha_registro` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_pago`),
  KEY `idx_paciente` (`id_paciente`),
  KEY `idx_fecha` (`fecha_pago`),
  KEY `idx_tratamiento` (`id_tratamiento`),
  KEY `id_usuario_registro` (`id_usuario_registro`),
  CONSTRAINT `pagos_ibfk_1` FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id_paciente`) ON DELETE CASCADE,
  CONSTRAINT `pagos_ibfk_2` FOREIGN KEY (`id_tratamiento`) REFERENCES `tratamientos` (`id_tratamiento`) ON DELETE SET NULL,
  CONSTRAINT `pagos_ibfk_3` FOREIGN KEY (`id_usuario_registro`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pagos`
--

LOCK TABLES `pagos` WRITE;
/*!40000 ALTER TABLE `pagos` DISABLE KEYS */;
INSERT INTO `pagos` VALUES (1,1,1,1,150.00,'Pago completo consulta','2026-03-14',NULL,NULL,'efectivo','2026-03-14 14:18:09'),(2,4,3,1,50.00,'pago inicial','2026-03-31','/ecodent/uploads/comprobantes/pago_1774912949_3.png','','efectivo','2026-03-30 19:22:29'),(3,4,3,1,50.00,'SEGUNDO PAGO','2026-03-31','/ecodent/uploads/comprobantes/pago_1774915231_3.png','','efectivo','2026-03-30 20:00:31'),(4,5,4,1,450.00,'primera cuota','2026-03-31','/ecodent/uploads/comprobantes/pago_1774935890_4.png','cancelado','efectivo','2026-03-31 01:44:50'),(5,5,5,1,74000.00,'pirmera cuota','2026-03-31','/ecodent/uploads/comprobantes/pago_1774936033_5.png','niguna','transferencia','2026-03-31 01:47:13'),(6,5,5,1,1000.00,'segunda cuota','2026-03-31',NULL,'','efectivo','2026-03-31 01:52:34'),(7,5,4,8,40.00,'SEGUNDO PAGO','2026-04-02','/ecodent/uploads/comprobantes/pago_1775098927_4.png','PAGO CONFORME','transferencia','2026-04-01 23:02:07');
/*!40000 ALTER TABLE `pagos` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER actualizar_tratamiento_after_pago 
AFTER INSERT ON pagos 
FOR EACH ROW 
BEGIN
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
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `reglas_alertas`
--

DROP TABLE IF EXISTS `reglas_alertas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reglas_alertas` (
  `id_regla` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `condicion` text NOT NULL,
  `mensaje` text NOT NULL,
  `activa` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id_regla`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reglas_alertas`
--

LOCK TABLES `reglas_alertas` WRITE;
/*!40000 ALTER TABLE `reglas_alertas` DISABLE KEYS */;
INSERT INTO `reglas_alertas` VALUES (7,'Paciente con 3+ ausencias','ausencias_sin_aviso >= 3','El paciente {nombre_paciente} tiene {ausencias} ausencias sin aviso. Revisar estado de cuenta.',1),(8,'Paciente con llegadas tarde','llegadas_tarde >= 3','El paciente {nombre_paciente} ha llegado tarde {llegadas_tarde} veces.',1),(9,'Paciente bloqueado','estado_cuenta = \"bloqueada\"','El paciente {nombre_paciente} está BLOQUEADO. No puede agendar citas.',1);
/*!40000 ALTER TABLE `reglas_alertas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `slots_bloqueados`
--

DROP TABLE IF EXISTS `slots_bloqueados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `slots_bloqueados` (
  `id_bloqueo` int(11) NOT NULL AUTO_INCREMENT,
  `id_odontologo` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_bloqueo`),
  KEY `idx_odontologo_fecha` (`id_odontologo`,`fecha`),
  CONSTRAINT `fk_slots_odontologo` FOREIGN KEY (`id_odontologo`) REFERENCES `odontologos` (`id_odontologo`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `slots_bloqueados`
--

LOCK TABLES `slots_bloqueados` WRITE;
/*!40000 ALTER TABLE `slots_bloqueados` DISABLE KEYS */;
INSERT INTO `slots_bloqueados` VALUES (36,1,'2026-04-02','08:40:00','09:20:00','Cancelación por odontólogo: CAPACITACION','2026-04-01 01:50:23'),(37,1,'2026-04-03','08:40:00','09:20:00','Cancelación por odontólogo: EMERGENCIA','2026-04-01 01:51:35'),(38,1,'2026-04-06','10:40:00','11:20:00','Cancelación por odontólogo: EMERGENCIA','2026-04-01 02:00:39'),(39,1,'2026-04-06','08:00:00','08:40:00','Cancelación por odontólogo: BBB','2026-04-01 02:03:40'),(40,1,'2026-04-03','08:00:00','08:40:00','Cancelación por odontólogo: DERMATOLOGO','2026-04-01 02:05:47');
/*!40000 ALTER TABLE `slots_bloqueados` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tratamientos`
--

DROP TABLE IF EXISTS `tratamientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tratamientos` (
  `id_tratamiento` int(11) NOT NULL AUTO_INCREMENT,
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
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_tratamiento`),
  KEY `idx_paciente` (`id_paciente`),
  KEY `idx_odontologo` (`id_odontologo`),
  CONSTRAINT `tratamientos_ibfk_1` FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id_paciente`) ON DELETE CASCADE,
  CONSTRAINT `tratamientos_ibfk_2` FOREIGN KEY (`id_odontologo`) REFERENCES `odontologos` (`id_odontologo`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tratamientos`
--

LOCK TABLES `tratamientos` WRITE;
/*!40000 ALTER TABLE `tratamientos` DISABLE KEYS */;
INSERT INTO `tratamientos` VALUES (1,1,1,'Consulta inicial',NULL,150.00,0.00,150.00,'2026-03-14',NULL,'pendiente',NULL,'2026-03-14 14:18:09'),(2,1,1,'Limpieza dental',NULL,200.00,0.00,200.00,'2026-03-14',NULL,'pendiente',NULL,'2026-03-14 14:18:09'),(3,4,1,'Limpieza dental','diente molar izquierdo',100.00,100.00,0.00,'2026-03-31','2026-04-03','completado','','2026-03-30 19:21:23'),(4,5,1,'implante dental','retiral el molar superior',500.00,490.00,10.00,'2026-03-31','2026-04-07','completado','','2026-03-31 01:43:56'),(5,5,1,'ortodincoa','colocacion de brakets',75000.00,75000.00,0.00,'2026-03-31','2026-04-24','en_progreso','compra de brakets','2026-03-31 01:46:15');
/*!40000 ALTER TABLE `tratamientos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
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
  `ultimo_acceso` datetime DEFAULT NULL,
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_rol` (`rol`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'carlos.mamani@ecodent.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Dr. Carlos Mamani','77112233','odontologo',1,NULL,NULL,1,'2026-03-14 14:03:49','2026-04-01 23:46:05'),(2,'maria.quispe@ecodent.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Dra. María Quispe','77112234','odontologo',1,NULL,NULL,1,'2026-03-14 14:03:49',NULL),(3,'juan.perez@email.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Juan Pérez','71234567','paciente',1,NULL,NULL,1,'2026-03-14 14:03:49','2026-03-31 01:30:16'),(4,'richardocsachoqueherrera985@gmail.com','$2y$10$8dC6Qdmyt8R/dfInimLiO.sI8dGTNV0LK5Hiehtf0iTXXB3AHaBU6','Richard Edmundo Herrera','73537562','paciente',1,NULL,NULL,1,'2026-03-14 14:12:19','2026-04-02 01:20:25'),(5,'ser@gmail.com','$2y$10$JylLczXrWYIEqLFiLUhLSORT4ILIUJru0GEPsq.Cri50kaJ0A6t1K','sergio','12347885','paciente',1,NULL,NULL,1,'2026-03-14 14:19:24','2026-03-14 14:19:24'),(6,'jhamilth@gmail.com','$2y$10$o/DKzsv0cTi3f8Wby6hFve/yXebNW8SgRImWhs4HlGCFach15/MUa','Jhamileth','73537562','paciente',1,NULL,NULL,1,'2026-03-30 19:16:01',NULL),(7,'herreraocsachoquerichard985@gmail.com','$2y$10$yoAqXbgIK6NEKDFZ0.UrdOzSL29IE8bCMJA5vXa07MPDCAGU3HqOq','edmundo','73537562','paciente',1,NULL,NULL,1,'2026-03-31 01:05:24','2026-04-01 02:23:59'),(8,'admin@ecodent.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Administrador Sistema','77112233','admin',1,NULL,NULL,1,'2026-04-01 10:30:22','2026-04-02 00:16:12');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-02 11:52:39
