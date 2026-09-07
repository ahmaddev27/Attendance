-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: employee_attendance
-- ------------------------------------------------------
-- Server version	8.4.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `employee_attendance`
--

/*!40000 DROP DATABASE IF EXISTS `employee_attendance`*/;

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `employee_attendance` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `employee_attendance`;

--
-- Table structure for table `attendances`
--

DROP TABLE IF EXISTS `attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `type` enum('check_in','check_out') COLLATE utf8mb4_unicode_ci NOT NULL,
  `scanned_at` timestamp NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `fraud_check_status` enum('passed','gps_failed','ip_failed','skipped') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'skipped',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `attendances_employee_id_scanned_at_index` (`employee_id`,`scanned_at`),
  CONSTRAINT `attendances_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendances`
--

LOCK TABLES `attendances` WRITE;
/*!40000 ALTER TABLE `attendances` DISABLE KEYS */;
INSERT INTO `attendances` VALUES (1,1,'check_in','2026-09-05 11:13:37','127.0.0.1',0.0000000,0.0000000,'skipped','2026-09-05 11:13:37'),(2,1,'check_out','2026-09-05 11:21:52','127.0.0.1',0.0000000,0.0000000,'skipped','2026-09-05 11:21:52'),(3,9,'check_in','2026-09-05 12:56:45',NULL,NULL,NULL,'skipped','2026-09-05 12:56:45'),(4,10,'check_in','2026-09-05 12:56:45',NULL,NULL,NULL,'skipped','2026-09-05 12:56:45'),(5,11,'check_in','2026-09-05 12:56:45',NULL,NULL,NULL,'skipped','2026-09-05 12:56:45'),(6,12,'check_in','2026-09-05 12:56:45',NULL,NULL,NULL,'skipped','2026-09-05 12:56:45'),(7,13,'check_in','2026-09-05 12:56:45',NULL,NULL,NULL,'skipped','2026-09-05 12:56:45');
/*!40000 ALTER TABLE `attendances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('5c785c036466adea360111aa28563bfd556b5fba','i:3;',1788609255),('5c785c036466adea360111aa28563bfd556b5fba:timer','i:1788609255;',1788609255),('da4b9237bacccdf19c0760cab7aec4a8359010b0','i:2;',1788610593),('da4b9237bacccdf19c0760cab7aec4a8359010b0:timer','i:1788610593;',1788610593),('settings.all','a:10:{s:11:\"gps_enabled\";a:6:{s:2:\"id\";i:1;s:3:\"key\";s:11:\"gps_enabled\";s:5:\"value\";s:1:\"0\";s:4:\"type\";s:7:\"boolean\";s:10:\"created_at\";s:27:\"2026-09-05T10:59:57.000000Z\";s:10:\"updated_at\";s:27:\"2026-09-05T10:59:57.000000Z\";}s:10:\"office_lat\";a:6:{s:2:\"id\";i:2;s:3:\"key\";s:10:\"office_lat\";s:5:\"value\";N;s:4:\"type\";s:6:\"number\";s:10:\"created_at\";s:27:\"2026-09-05T10:59:57.000000Z\";s:10:\"updated_at\";s:27:\"2026-09-05T10:59:57.000000Z\";}s:10:\"office_lng\";a:6:{s:2:\"id\";i:3;s:3:\"key\";s:10:\"office_lng\";s:5:\"value\";N;s:4:\"type\";s:6:\"number\";s:10:\"created_at\";s:27:\"2026-09-05T10:59:57.000000Z\";s:10:\"updated_at\";s:27:\"2026-09-05T10:59:57.000000Z\";}s:22:\"geofence_radius_meters\";a:6:{s:2:\"id\";i:4;s:3:\"key\";s:22:\"geofence_radius_meters\";s:5:\"value\";s:3:\"100\";s:4:\"type\";s:6:\"number\";s:10:\"created_at\";s:27:\"2026-09-05T10:59:57.000000Z\";s:10:\"updated_at\";s:27:\"2026-09-05T10:59:57.000000Z\";}s:10:\"ip_enabled\";a:6:{s:2:\"id\";i:5;s:3:\"key\";s:10:\"ip_enabled\";s:5:\"value\";s:1:\"0\";s:4:\"type\";s:7:\"boolean\";s:10:\"created_at\";s:27:\"2026-09-05T10:59:57.000000Z\";s:10:\"updated_at\";s:27:\"2026-09-05T10:59:57.000000Z\";}s:12:\"ip_whitelist\";a:6:{s:2:\"id\";i:6;s:3:\"key\";s:12:\"ip_whitelist\";s:5:\"value\";s:2:\"[]\";s:4:\"type\";s:4:\"json\";s:10:\"created_at\";s:27:\"2026-09-05T10:59:57.000000Z\";s:10:\"updated_at\";s:27:\"2026-09-05T10:59:57.000000Z\";}s:12:\"sms_username\";a:6:{s:2:\"id\";i:7;s:3:\"key\";s:12:\"sms_username\";s:5:\"value\";s:0:\"\";s:4:\"type\";s:6:\"string\";s:10:\"created_at\";s:27:\"2026-09-05T10:59:57.000000Z\";s:10:\"updated_at\";s:27:\"2026-09-05T10:59:57.000000Z\";}s:12:\"sms_password\";a:6:{s:2:\"id\";i:8;s:3:\"key\";s:12:\"sms_password\";s:5:\"value\";s:0:\"\";s:4:\"type\";s:6:\"string\";s:10:\"created_at\";s:27:\"2026-09-05T10:59:57.000000Z\";s:10:\"updated_at\";s:27:\"2026-09-05T10:59:57.000000Z\";}s:10:\"sms_sender\";a:6:{s:2:\"id\";i:9;s:3:\"key\";s:10:\"sms_sender\";s:5:\"value\";s:0:\"\";s:4:\"type\";s:6:\"string\";s:10:\"created_at\";s:27:\"2026-09-05T10:59:57.000000Z\";s:10:\"updated_at\";s:27:\"2026-09-05T10:59:57.000000Z\";}s:21:\"employee_number_start\";a:6:{s:2:\"id\";i:10;s:3:\"key\";s:21:\"employee_number_start\";s:5:\"value\";s:4:\"1001\";s:4:\"type\";s:6:\"number\";s:10:\"created_at\";s:27:\"2026-09-05T10:59:57.000000Z\";s:10:\"updated_at\";s:27:\"2026-09-05T10:59:57.000000Z\";}}',1788616225);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employees` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_number` int unsigned NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employees_employee_number_unique` (`employee_number`),
  UNIQUE KEY `employees_phone_unique` (`phone`),
  UNIQUE KEY `employees_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employees`
--

LOCK TABLES `employees` WRITE;
/*!40000 ALTER TABLE `employees` DISABLE KEYS */;
INSERT INTO `employees` VALUES (1,1001,'Test User','+962700000000','rosenbaum.lucie@example.net',1,'2026-09-05 11:00:04','2026-09-05 11:00:04'),(2,1002,'احمد جبر','059719812',NULL,1,'2026-09-05 11:56:36','2026-09-05 11:56:36'),(3,6336,'Miss Olga Anderson Jr.','+962707969563','ymarvin@example.com',1,'2026-09-05 12:56:45','2026-09-05 12:56:45'),(4,6279,'Grover Leuschke','+962725913052','gcummings@example.com',1,'2026-09-05 12:56:45','2026-09-05 12:56:45'),(5,7899,'Nels Johns','+962724303454','sylvia24@example.net',1,'2026-09-05 12:56:45','2026-09-05 12:56:45'),(6,3848,'Lafayette D\'Amore','+962717618396','waters.davin@example.net',1,'2026-09-05 12:56:45','2026-09-05 12:56:45'),(7,8350,'Litzy Hammes','+962725298063','tjacobi@example.net',1,'2026-09-05 12:56:45','2026-09-05 12:56:45'),(8,1312,'Karen Predovic','+962750740827','jerrell15@example.com',1,'2026-09-05 12:56:45','2026-09-05 12:56:45'),(9,6786,'Katlyn Dare','+962770814809','nathen.hills@example.com',1,'2026-09-05 12:56:45','2026-09-05 12:56:45'),(10,6094,'Sunny Hoppe','+962713461218','patsy.mitchell@example.net',1,'2026-09-05 12:56:45','2026-09-05 12:56:45'),(11,6224,'Kaelyn Wolf','+962750051412','bfritsch@example.org',1,'2026-09-05 12:56:45','2026-09-05 12:56:45'),(12,3925,'Oswaldo Volkman','+962702468461','smosciski@example.org',1,'2026-09-05 12:56:45','2026-09-05 12:56:45'),(13,4710,'Erick Bechtelar','+962753132992','thiel.enola@example.org',1,'2026-09-05 12:56:45','2026-09-05 12:56:45'),(14,1743,'Marcella Boehm','+962716217234','sstokes@example.com',1,'2026-09-05 12:56:45','2026-09-05 12:56:45'),(15,8993,'Esmeralda Kovacek','+962731113078','sabshire@example.com',1,'2026-09-05 12:56:45','2026-09-05 12:56:45'),(16,6392,'Miss Isabel Douglas IV','+962705782132','briana76@example.net',1,'2026-09-05 12:56:45','2026-09-05 12:56:45'),(17,5106,'Gus Spencer MD','+962736396179','bradford03@example.org',1,'2026-09-05 12:56:45','2026-09-05 12:56:45'),(18,7212,'Jaime Kassulke PhD','+962736921588','dion.glover@example.com',1,'2026-09-05 12:56:45','2026-09-05 12:56:45');
/*!40000 ALTER TABLE `employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
INSERT INTO `jobs` VALUES (1,'default','{\"uuid\":\"dde93d5d-ad5f-4f5c-a1a1-8c338599a241\",\"displayName\":\"App\\\\Jobs\\\\SendSmsJob\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":3,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"App\\\\Jobs\\\\SendSmsJob\",\"command\":\"O:19:\\\"App\\\\Jobs\\\\SendSmsJob\\\":2:{s:26:\\\"\\u0000App\\\\Jobs\\\\SendSmsJob\\u0000phone\\\";s:9:\\\"059719812\\\";s:28:\\\"\\u0000App\\\\Jobs\\\\SendSmsJob\\u0000message\\\";s:130:\\\"أهلاً بك في TAQAT. رقمك الوظيفي: 1002. استخدمه لتسجيل الحضور عبر QR عند المدخل.\\\";}\"}}',0,NULL,1788609396,1788609396),(2,'default','{\"uuid\":\"16d454ce-1115-4313-834e-00ec056598a9\",\"displayName\":\"App\\\\Jobs\\\\SendSmsJob\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":3,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"App\\\\Jobs\\\\SendSmsJob\",\"command\":\"O:19:\\\"App\\\\Jobs\\\\SendSmsJob\\\":2:{s:26:\\\"\\u0000App\\\\Jobs\\\\SendSmsJob\\u0000phone\\\";s:13:\\\"+962700000000\\\";s:28:\\\"\\u0000App\\\\Jobs\\\\SendSmsJob\\u0000message\\\";s:75:\\\"تمت الموافقة على طلب إجازتك بتاريخ 2026-09-07.\\\";}\"}}',0,NULL,1788609458,1788609458),(3,'default','{\"uuid\":\"36be340d-9390-472f-8b7d-211750bf12cf\",\"displayName\":\"App\\\\Jobs\\\\SendSmsJob\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":3,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"App\\\\Jobs\\\\SendSmsJob\",\"command\":\"O:19:\\\"App\\\\Jobs\\\\SendSmsJob\\\":2:{s:26:\\\"\\u0000App\\\\Jobs\\\\SendSmsJob\\u0000phone\\\";s:13:\\\"+962700000000\\\";s:28:\\\"\\u0000App\\\\Jobs\\\\SendSmsJob\\u0000message\\\";s:80:\\\"تم رفض طلب إجازتك بتاريخ 2026-09-10. السبب: شسبشس\\n\\\";}\"}}',0,NULL,1788609466,1788609466);
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leave_requests`
--

DROP TABLE IF EXISTS `leave_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `leave_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `leave_requests_employee_id_foreign` (`employee_id`),
  KEY `leave_requests_reviewed_by_foreign` (`reviewed_by`),
  KEY `leave_requests_status_start_date_index` (`status`,`start_date`),
  CONSTRAINT `leave_requests_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leave_requests_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leave_requests`
--

LOCK TABLES `leave_requests` WRITE;
/*!40000 ALTER TABLE `leave_requests` DISABLE KEYS */;
INSERT INTO `leave_requests` VALUES (1,1,'2026-09-10','2026-09-12','Test leave request','rejected',2,'2026-09-05 11:57:46','شسبشس\n','2026-09-05 11:19:26','2026-09-05 11:57:46'),(2,1,'2026-09-07','2026-10-02','aaaaaaaaaaaa','approved',2,'2026-09-05 11:57:38',NULL,'2026-09-05 11:53:31','2026-09-05 11:57:38'),(3,14,'2026-10-02','2026-10-02',NULL,'pending',NULL,NULL,NULL,'2026-09-05 12:56:45','2026-09-05 12:56:45'),(4,15,'2026-09-22','2026-09-22',NULL,'pending',NULL,NULL,NULL,'2026-09-05 12:56:45','2026-09-05 12:56:45'),(5,16,'2026-09-08','2026-09-08',NULL,'pending',NULL,NULL,NULL,'2026-09-05 12:56:45','2026-09-05 12:56:45'),(6,17,'2026-09-18','2026-09-18',NULL,'pending',NULL,NULL,NULL,'2026-09-05 12:56:45','2026-09-05 12:56:45'),(7,18,'2026-09-20','2026-09-20',NULL,'pending',NULL,NULL,NULL,'2026-09-05 12:56:45','2026-09-05 12:56:45');
/*!40000 ALTER TABLE `leave_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_09_05_115724_create_employees_table',1),(5,'2026_09_05_115725_create_attendances_table',1),(6,'2026_09_05_115726_create_leave_requests_table',1),(7,'2026_09_05_115728_create_settings_table',1),(8,'2026_09_05_115732_create_sms_logs_table',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('6FtQIwTkKyDwlexBv8ELtckPRj7xLm4Il3Fd2Lw4',2,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.44121.4 Chrome/148.0.7778.280 Safari/537.36 MSIX','YTo0OntzOjY6Il90b2tlbiI7czo0MDoiV1d4OXNKRWFPUFNacmZNVUo2QklvV05UcHBYdmFMaU9kcE5jcWxzRSI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6Mzc6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9hZG1pbi9lbXBsb3llZXMiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToyO30=',1788613121),('En4SKu4LNQPlYKEuEj5KHUNkcsWgHriLLSyYr25c',2,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.46388.4 Chrome/148.0.7778.280 Safari/537.36 MSIX','YTo0OntzOjY6Il90b2tlbiI7czo0MDoieHRURElRVmdkRWdVbkFSQmJMdnNPeGJOeU5ZTTFnOTJuUjN3Q1RsYSI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MzY6Imh0dHA6Ly9sb2NhbGhvc3Q6ODAwMC9hZG1pbi9zbXMtbG9ncyI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjI7fQ==',1788616008),('J0dUcD4Q2WvvmQKjQnLSyKNgB2yhJFh5F0Hd7lU3',2,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:155.0) Gecko/20100101 Firefox/155.0','YTo1OntzOjY6Il90b2tlbiI7czo0MDoiMjFlRjFxQk1vbUZzRExtb2xoVnZMRjVFSzNmb0hMQlVXdEl0ZmFaWiI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MzY6Imh0dHA6Ly9sb2NhbGhvc3Q6ODAwMC9hZG1pbi9zZXR0aW5ncyI7fXM6MzoidXJsIjthOjA6e31zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToyO30=',1788612626),('kfGVF0ZfyXHjVFgqlWUJlSw6iIisqfO5A7iwTcXf',2,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:155.0) Gecko/20100101 Firefox/155.0','YTo1OntzOjY6Il90b2tlbiI7czo0MDoiQ29GR09Rb1NIN0FpSXFITlFDM3JWRXFvT0hpa09nVXBwdUExWWVyNCI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6Mjc6Imh0dHA6Ly9sb2NhbGhvc3Q6ODAwMC9hZG1pbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fXM6MzoidXJsIjthOjA6e31zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToyO30=',1788614199),('lemvV2kmD1Dxha8tzSdb3bKehhH8IKYrR3ye2BGG',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:155.0) Gecko/20100101 Firefox/155.0','YTozOntzOjY6Il90b2tlbiI7czo0MDoieU1oV01uR1h6QTVQejVOOHBLS250d014Z0dtVDJLNE1iejI2R09xYiI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1788609125),('R5Hy4GRcN2z1LfDZjhTgmmKcJTT5Kuqu7ECZnUWR',NULL,'127.0.0.1','curl/8.7.1','YTozOntzOjY6Il90b2tlbiI7czo0MDoiT3VQNWpHU0dWWkVONzZtUkQ5NXBCWjg0UzVHN3ExZ01lNzNaZWQzWCI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6Mjc6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9sb2dpbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1788613015);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `type` enum('string','boolean','json','number') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'string',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_key_unique` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'gps_enabled','0','boolean','2026-09-05 10:59:57','2026-09-05 10:59:57'),(2,'office_lat',NULL,'number','2026-09-05 10:59:57','2026-09-05 10:59:57'),(3,'office_lng',NULL,'number','2026-09-05 10:59:57','2026-09-05 10:59:57'),(4,'geofence_radius_meters','100','number','2026-09-05 10:59:57','2026-09-05 10:59:57'),(5,'ip_enabled','0','boolean','2026-09-05 10:59:57','2026-09-05 10:59:57'),(6,'ip_whitelist','[]','json','2026-09-05 10:59:57','2026-09-05 10:59:57'),(7,'sms_username','','string','2026-09-05 10:59:57','2026-09-05 10:59:57'),(8,'sms_password','','string','2026-09-05 10:59:57','2026-09-05 10:59:57'),(9,'sms_sender','','string','2026-09-05 10:59:57','2026-09-05 10:59:57'),(10,'employee_number_start','1001','number','2026-09-05 10:59:57','2026-09-05 10:59:57');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sms_logs`
--

DROP TABLE IF EXISTS `sms_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sms_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('sent','failed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_response` text COLLATE utf8mb4_unicode_ci,
  `error_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sent_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sms_logs_sent_at_index` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sms_logs`
--

LOCK TABLES `sms_logs` WRITE;
/*!40000 ALTER TABLE `sms_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `sms_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Test User','test@example.com','2026-09-05 10:59:56','$2y$12$ILKEKlensXs7pLELAJiNku6fdYNCyg4PkqljIybZphAFPJ50xFK.W','eTtZc5dHdE','2026-09-05 10:59:57','2026-09-05 10:59:57'),(2,'Administrator','admin@example.com',NULL,'$2y$12$kKTPNcddVRr.Up5ZqqxYFOyDzpiowp4lrfWzxoBttlozwW2C0hWBm','5RlBGxS8I1ZEZdFgzyl7c4o23aWH7tHkpqt7uNzuaQmWP8c1Z8ljcNfRPUKo','2026-09-05 10:59:57','2026-09-05 10:59:57');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'employee_attendance'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-05 17:03:16
