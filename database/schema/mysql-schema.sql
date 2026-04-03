/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `admin_menu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_menu` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) NOT NULL DEFAULT '0',
  `order` int(11) NOT NULL DEFAULT '0',
  `title` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uri` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permission` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admin_operation_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_operation_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `method` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `input` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_operation_log_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admin_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `http_method` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `http_path` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_permissions_name_unique` (`name`),
  UNIQUE KEY `admin_permissions_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admin_role_menu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_role_menu` (
  `role_id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  KEY `admin_role_menu_role_id_menu_id_index` (`role_id`,`menu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admin_role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  KEY `admin_role_permissions_role_id_permission_id_index` (`role_id`,`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admin_role_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_role_users` (
  `role_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  KEY `admin_role_users_role_id_user_id_index` (`role_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admin_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_roles_name_unique` (`name`),
  UNIQUE KEY `admin_roles_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admin_user_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_user_permissions` (
  `user_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  KEY `admin_user_permissions_user_id_permission_id_index` (`user_id`,`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admin_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_busy_in_game` tinyint(1) NOT NULL DEFAULT '0',
  `busy_since` timestamp NULL DEFAULT NULL,
  `terms_of_service_accepted` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `privacy_policy_accepted` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `community_guidelines_accepted` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `marketing_emails_consent` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_processing_consent` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content_moderation_consent` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `terms_accepted_date` timestamp NULL DEFAULT NULL,
  `privacy_accepted_date` timestamp NULL DEFAULT NULL,
  `guidelines_accepted_date` timestamp NULL DEFAULT NULL,
  `notification_preferences` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `push_notifications` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_notifications` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile_visibility` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Public',
  `content_filtering` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'On',
  `safe_mode` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'On',
  `location_sharing` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `analytics_consent` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `crash_reporting` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  `first_name` text COLLATE utf8mb4_unicode_ci,
  `last_name` text COLLATE utf8mb4_unicode_ci,
  `phone_number` text COLLATE utf8mb4_unicode_ci,
  `phone_number_2` text COLLATE utf8mb4_unicode_ci,
  `address` text COLLATE utf8mb4_unicode_ci,
  `sex` text COLLATE utf8mb4_unicode_ci,
  `dob` date DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `google_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `secret_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile_photos` json DEFAULT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci,
  `tagline` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_country_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_country_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_country_international` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sexual_orientation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `height_cm` int(11) DEFAULT NULL,
  `body_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `last_online_at` datetime DEFAULT NULL,
  `online_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'Offline',
  `looking_for` text COLLATE utf8mb4_unicode_ci,
  `interested_in` text COLLATE utf8mb4_unicode_ci,
  `age_range_min` int(11) DEFAULT NULL,
  `age_range_max` int(11) DEFAULT NULL,
  `max_distance_km` int(11) DEFAULT NULL,
  `smoking_habit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `drinking_habit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pet_preference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `religion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `political_views` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `languages_spoken` text COLLATE utf8mb4_unicode_ci,
  `education_level` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `occupation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT NULL,
  `phone_verified` tinyint(1) DEFAULT NULL,
  `verification_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `failed_login_attempts` int(11) DEFAULT NULL,
  `last_password_change` datetime DEFAULT NULL,
  `subscription_tier` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subscription_expires` datetime DEFAULT NULL,
  `credits_balance` int(11) DEFAULT NULL,
  `profile_views` int(11) DEFAULT NULL,
  `likes_received` int(11) DEFAULT NULL,
  `matches_count` int(11) DEFAULT NULL,
  `completed_profile_pct` int(11) DEFAULT NULL,
  `is_guest` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `last_trending_notification_sent` timestamp NULL DEFAULT NULL COMMENT 'When was the last trending notification sent to this user',
  `last_trending_notification_period` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Last notification period: morning, afternoon, evening, night',
  `last_trending_notification_date` date DEFAULT NULL COMMENT 'Date of last trending notification',
  `trending_notifications_today` int(11) NOT NULL DEFAULT '0' COMMENT 'Count of trending notifications sent today',
  `max_trending_notifications_per_day` int(11) NOT NULL DEFAULT '4' COMMENT 'Maximum trending notifications per day (1 per period)',
  `app_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'ugflix',
  `platform` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'android',
  `is_imported` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'No',
  `import_source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `external_profile_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imported_at` timestamp NULL DEFAULT NULL,
  `game_coins_balance` int(10) unsigned NOT NULL DEFAULT '0',
  `total_games_played` int(10) unsigned NOT NULL DEFAULT '0',
  `total_games_won` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_users_username_unique` (`username`),
  UNIQUE KEY `admin_users_external_profile_url_unique` (`external_profile_url`),
  KEY `idx_notification_tracking` (`last_trending_notification_date`,`trending_notifications_today`),
  KEY `idx_au_email` (`email`),
  KEY `idx_au_status` (`status`),
  KEY `idx_au_app_type` (`app_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `africa_talking_responses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `africa_talking_responses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `sessionId` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phoneNumber` text COLLATE utf8mb4_unicode_ci,
  `errorMessage` text COLLATE utf8mb4_unicode_ci,
  `post` text COLLATE utf8mb4_unicode_ci,
  `get` text COLLATE utf8mb4_unicode_ci,
  `recording_url` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `blog_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blog_comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `blog_post_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `user_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `likes_count` int(10) unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `blog_comments_blog_post_id_index` (`blog_post_id`),
  KEY `blog_comments_user_id_index` (`user_id`),
  KEY `blog_comments_status_index` (`status`),
  CONSTRAINT `blog_comments_blog_post_id_foreign` FOREIGN KEY (`blog_post_id`) REFERENCES `blog_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `blog_likes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blog_likes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `likeable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `likeable_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `blog_likes_user_id_likeable_type_likeable_id_unique` (`user_id`,`likeable_type`,`likeable_id`),
  KEY `blog_likes_likeable_type_likeable_id_index` (`likeable_type`,`likeable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `blog_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blog_posts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `excerpt` text COLLATE utf8mb4_unicode_ci,
  `image_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'General',
  `author_id` bigint(20) unsigned DEFAULT NULL,
  `author_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Admin',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `views_count` int(10) unsigned NOT NULL DEFAULT '0',
  `likes_count` int(10) unsigned NOT NULL DEFAULT '0',
  `comments_count` int(10) unsigned NOT NULL DEFAULT '0',
  `is_pinned` tinyint(1) NOT NULL DEFAULT '0',
  `comments_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chat_heads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_heads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `product_name` text COLLATE utf8mb4_unicode_ci,
  `product_photo` text COLLATE utf8mb4_unicode_ci,
  `product_owner_id` int(11) DEFAULT NULL,
  `product_owner_name` text COLLATE utf8mb4_unicode_ci,
  `product_owner_photo` text COLLATE utf8mb4_unicode_ci,
  `product_owner_last_seen` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` text COLLATE utf8mb4_unicode_ci,
  `customer_photo` text COLLATE utf8mb4_unicode_ci,
  `customer_last_seen` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_message_body` text COLLATE utf8mb4_unicode_ci,
  `last_message_time` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_message_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'dating',
  `sender_unread_count` int(11) DEFAULT '0',
  `receiver_unread_count` int(11) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chat_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `chat_head_id` bigint(20) unsigned NOT NULL,
  `sender_id` bigint(20) unsigned NOT NULL,
  `receiver_id` bigint(20) unsigned NOT NULL,
  `sender_name` text COLLATE utf8mb4_unicode_ci,
  `sender_photo` text COLLATE utf8mb4_unicode_ci,
  `receiver_name` text COLLATE utf8mb4_unicode_ci,
  `receiver_photo` text COLLATE utf8mb4_unicode_ci,
  `body` text COLLATE utf8mb4_unicode_ci,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `audio_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `audio_duration` int(11) DEFAULT NULL COMMENT 'Duration in seconds',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `audio` text COLLATE utf8mb4_unicode_ci,
  `video` text COLLATE utf8mb4_unicode_ci,
  `document` text COLLATE utf8mb4_unicode_ci,
  `photo` text COLLATE utf8mb4_unicode_ci,
  `longitude` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cm_sender` (`sender_id`),
  KEY `idx_cm_receiver` (`receiver_id`),
  KEY `idx_cm_receiver_status` (`receiver_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `checkers_chat_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `checkers_chat_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `user_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `checkers_chat_messages_session_id_index` (`session_id`),
  CONSTRAINT `checkers_chat_messages_session_id_foreign` FOREIGN KEY (`session_id`) REFERENCES `checkers_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `checkers_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `checkers_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session_code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','active','completed','cancelled','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `player1_id` bigint(20) unsigned NOT NULL,
  `player1_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `player2_id` bigint(20) unsigned DEFAULT NULL,
  `player2_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `board_state` json DEFAULT NULL,
  `current_turn` enum('red','black') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'red',
  `current_turn_user_id` bigint(20) unsigned DEFAULT NULL,
  `last_move_from` int(11) DEFAULT NULL,
  `last_move_to` int(11) DEFAULT NULL,
  `last_captured` json DEFAULT NULL,
  `last_crowned` tinyint(1) NOT NULL DEFAULT '0',
  `winner_id` bigint(20) unsigned DEFAULT NULL,
  `winner_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `move_count` int(11) NOT NULL DEFAULT '0',
  `player1_last_poll` timestamp NULL DEFAULT NULL,
  `player2_last_poll` timestamp NULL DEFAULT NULL,
  `chat_head_id` bigint(20) unsigned DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `ended_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `checkers_sessions_session_code_unique` (`session_code`),
  KEY `checkers_sessions_status_index` (`status`),
  KEY `checkers_sessions_player1_id_index` (`player1_id`),
  KEY `checkers_sessions_player2_id_index` (`player2_id`),
  KEY `checkers_sessions_current_turn_user_id_index` (`current_turn_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `coin_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `coin_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `amount` int(11) NOT NULL,
  `balance_after` int(11) NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `game_session_id` bigint(20) unsigned DEFAULT NULL,
  `related_user_id` int(10) unsigned DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `coin_transactions_game_session_id_foreign` (`game_session_id`),
  KEY `coin_transactions_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `coin_transactions_type_index` (`type`),
  KEY `coin_transactions_related_user_id_foreign` (`related_user_id`),
  CONSTRAINT `coin_transactions_game_session_id_foreign` FOREIGN KEY (`game_session_id`) REFERENCES `game_sessions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `coin_transactions_related_user_id_foreign` FOREIGN KEY (`related_user_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `coin_transactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `companies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `owner_id` int(11) NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` text COLLATE utf8mb4_unicode_ci,
  `logo` text COLLATE utf8mb4_unicode_ci,
  `website` text COLLATE utf8mb4_unicode_ci,
  `about` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_expire` date DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `phone_number` text COLLATE utf8mb4_unicode_ci,
  `phone_number_2` text COLLATE utf8mb4_unicode_ci,
  `pobox` text COLLATE utf8mb4_unicode_ci,
  `color` text COLLATE utf8mb4_unicode_ci,
  `slogan` text COLLATE utf8mb4_unicode_ci,
  `facebook` text COLLATE utf8mb4_unicode_ci,
  `twitter` text COLLATE utf8mb4_unicode_ci,
  `currency` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'USD',
  `settings_worker_can_create_stock_item` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'Yes',
  `settings_worker_can_create_stock_record` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'Yes',
  `settings_worker_can_create_stock_category` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'Yes',
  `settings_worker_can_view_balance` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'Yes',
  `settings_worker_can_view_stats` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'Yes',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `content_moderation_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `content_moderation_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `content_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `moderator_id` int(10) unsigned DEFAULT NULL,
  `action_type` enum('content_filtered','content_approved','content_blocked','content_quarantined','user_warning','user_suspended','user_banned','content_reported','user_blocked','user_unblocked','legal_consent_updated') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `filter_result` json DEFAULT NULL,
  `automated` tinyint(1) NOT NULL DEFAULT '0',
  `severity_level` enum('low','medium','high','critical') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'low',
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `content_moderation_logs_content_type_content_id_index` (`content_type`,`content_id`),
  KEY `content_moderation_logs_user_id_index` (`user_id`),
  KEY `content_moderation_logs_moderator_id_index` (`moderator_id`),
  KEY `content_moderation_logs_action_type_index` (`action_type`),
  KEY `content_moderation_logs_automated_index` (`automated`),
  KEY `content_moderation_logs_severity_level_index` (`severity_level`),
  KEY `content_moderation_logs_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `content_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `content_reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reporter_id` int(10) unsigned NOT NULL,
  `reported_content_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reported_content_id` bigint(20) unsigned DEFAULT NULL,
  `reported_user_id` int(10) unsigned NOT NULL,
  `report_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `moderator_id` int(10) unsigned DEFAULT NULL,
  `moderation_action` enum('no_action','warning','content_removed','user_suspended','user_banned','escalated') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `moderation_notes` text COLLATE utf8mb4_unicode_ci,
  `priority` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'low',
  `resolved_at` timestamp NULL DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `content_reports_reporter_id_index` (`reporter_id`),
  KEY `content_reports_reported_user_id_index` (`reported_user_id`),
  KEY `content_reports_status_index` (`status`),
  KEY `content_reports_priority_index` (`priority`),
  KEY `content_reports_created_at_index` (`created_at`),
  KEY `content_reports_reported_content_type_reported_content_id_index` (`reported_content_type`,`reported_content_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
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
DROP TABLE IF EXISTS `financial_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `financial_periods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `company_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `description` text COLLATE utf8mb4_unicode_ci,
  `total_investment` bigint(20) NOT NULL DEFAULT '0',
  `total_sales` bigint(20) NOT NULL DEFAULT '0',
  `total_profit` bigint(20) NOT NULL DEFAULT '0',
  `total_expenses` bigint(20) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `game_invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `game_invitations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sender_id` bigint(20) unsigned NOT NULL,
  `receiver_id` bigint(20) unsigned NOT NULL,
  `game_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'matatu',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `game_session_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `game_invitations_sender_id_index` (`sender_id`),
  KEY `game_invitations_receiver_id_index` (`receiver_id`),
  KEY `game_invitations_status_index` (`status`),
  KEY `game_invitations_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `game_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `game_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `player1_id` bigint(20) unsigned NOT NULL,
  `player2_id` bigint(20) unsigned NOT NULL,
  `player1_hand` text COLLATE utf8mb4_unicode_ci,
  `player2_hand` text COLLATE utf8mb4_unicode_ci,
  `discard_pile` text COLLATE utf8mb4_unicode_ci,
  `draw_pile` text COLLATE utf8mb4_unicode_ci,
  `cut_card` text COLLATE utf8mb4_unicode_ci,
  `current_turn_user_id` bigint(20) unsigned DEFAULT NULL,
  `current_suit` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `draw_stack` int(11) NOT NULL DEFAULT '0',
  `player1_score` int(11) NOT NULL DEFAULT '0',
  `player2_score` int(11) NOT NULL DEFAULT '0',
  `player1_rounds_won` int(11) NOT NULL DEFAULT '0',
  `player2_rounds_won` int(11) NOT NULL DEFAULT '0',
  `player1_last_poll` timestamp NULL DEFAULT NULL,
  `player2_last_poll` timestamp NULL DEFAULT NULL,
  `current_round` int(11) NOT NULL DEFAULT '1',
  `target_score` int(11) NOT NULL DEFAULT '100',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'waiting',
  `winner_id` bigint(20) unsigned DEFAULT NULL,
  `forfeit_user_id` bigint(20) unsigned DEFAULT NULL,
  `chat_head_id` bigint(20) unsigned DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `ended_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `game_sessions_player1_id_index` (`player1_id`),
  KEY `game_sessions_player2_id_index` (`player2_id`),
  KEY `game_sessions_status_index` (`status`),
  KEY `game_sessions_current_turn_user_id_index` (`current_turn_user_id`),
  KEY `game_sessions_forfeit_user_id_foreign` (`forfeit_user_id`),
  CONSTRAINT `game_sessions_forfeit_user_id_foreign` FOREIGN KEY (`forfeit_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `game_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `game_stats` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `game_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `games_played` int(10) unsigned NOT NULL DEFAULT '0',
  `wins` int(10) unsigned NOT NULL DEFAULT '0',
  `losses` int(10) unsigned NOT NULL DEFAULT '0',
  `draws` int(10) unsigned NOT NULL DEFAULT '0',
  `high_score` int(10) unsigned NOT NULL DEFAULT '0',
  `total_play_seconds` int(10) unsigned NOT NULL DEFAULT '0',
  `last_played_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `game_stats_user_id_game_type_unique` (`user_id`,`game_type`),
  KEY `game_stats_user_id_index` (`user_id`),
  KEY `game_stats_game_type_index` (`game_type`),
  CONSTRAINT `game_stats_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `class_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `use_db_table` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `table_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fields` text COLLATE utf8mb4_unicode_ci,
  `file_id` text COLLATE utf8mb4_unicode_ci,
  `end_point` varchar(355) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `images` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `administrator_id` bigint(20) unsigned DEFAULT NULL,
  `src` text COLLATE utf8mb4_unicode_ci,
  `thumbnail` text COLLATE utf8mb4_unicode_ci,
  `parent_id` int(11) DEFAULT NULL,
  `size` int(11) DEFAULT NULL,
  `deleted_at` date DEFAULT NULL,
  `type` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` bigint(20) DEFAULT NULL,
  `parent_endpoint` text COLLATE utf8mb4_unicode_ci,
  `note` text COLLATE utf8mb4_unicode_ci,
  `is_processed` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `parent_local_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `learning_material_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `learning_material_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `name` text COLLATE utf8mb4_unicode_ci,
  `short_description` text COLLATE utf8mb4_unicode_ci,
  `description` text COLLATE utf8mb4_unicode_ci,
  `image` text COLLATE utf8mb4_unicode_ci,
  `color` text COLLATE utf8mb4_unicode_ci,
  `icon` text COLLATE utf8mb4_unicode_ci,
  `slug` text COLLATE utf8mb4_unicode_ci,
  `order` int(11) DEFAULT NULL,
  `status` int(11) DEFAULT NULL,
  `external_url` text COLLATE utf8mb4_unicode_ci,
  `external_id` text COLLATE utf8mb4_unicode_ci,
  `last_visit` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `learning_material_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `learning_material_posts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `learning_material_category_id` bigint(20) unsigned DEFAULT NULL,
  `title` text COLLATE utf8mb4_unicode_ci,
  `external_id` text COLLATE utf8mb4_unicode_ci,
  `short_description` text COLLATE utf8mb4_unicode_ci,
  `description` text COLLATE utf8mb4_unicode_ci,
  `image` text COLLATE utf8mb4_unicode_ci,
  `slug` text COLLATE utf8mb4_unicode_ci,
  `external_url` text COLLATE utf8mb4_unicode_ci,
  `external_download_url` text COLLATE utf8mb4_unicode_ci,
  `download_url` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `links` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `title` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_id` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `thumbnail` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `processed` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'No',
  `success` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'No',
  `error` text COLLATE utf8mb4_unicode_ci,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'Movie',
  `school_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'Primary',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ludo_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ludo_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','waiting','playing','completed','cancelled','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `game_type` enum('2_player','4_player') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '2_player',
  `player1_id` bigint(20) unsigned DEFAULT NULL,
  `player1_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `player1_avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `player1_pieces` json DEFAULT NULL,
  `player1_finished_count` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `player2_id` bigint(20) unsigned DEFAULT NULL,
  `player2_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `player2_avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `player2_pieces` json DEFAULT NULL,
  `player2_finished_count` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `player3_id` bigint(20) unsigned DEFAULT NULL,
  `player3_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `player3_avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `player3_pieces` json DEFAULT NULL,
  `player3_finished_count` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `player4_id` bigint(20) unsigned DEFAULT NULL,
  `player4_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `player4_avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `player4_pieces` json DEFAULT NULL,
  `player4_finished_count` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `current_turn_player` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `current_turn_user_id` bigint(20) unsigned DEFAULT NULL,
  `last_dice_roll` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `consecutive_sixes` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `can_roll_again` tinyint(1) NOT NULL DEFAULT '0',
  `must_move_piece` tinyint(1) NOT NULL DEFAULT '0',
  `last_action` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_action_player` tinyint(3) unsigned DEFAULT NULL,
  `last_captured_piece` json DEFAULT NULL,
  `winner_player` tinyint(3) unsigned DEFAULT NULL,
  `winner_user_id` bigint(20) unsigned DEFAULT NULL,
  `rankings` json DEFAULT NULL,
  `player1_last_poll` timestamp NULL DEFAULT NULL,
  `player2_last_poll` timestamp NULL DEFAULT NULL,
  `player3_last_poll` timestamp NULL DEFAULT NULL,
  `player4_last_poll` timestamp NULL DEFAULT NULL,
  `turn_started_at` timestamp NULL DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `ended_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ludo_sessions_session_code_unique` (`session_code`),
  KEY `ludo_sessions_status_index` (`status`),
  KEY `ludo_sessions_player1_id_index` (`player1_id`),
  KEY `ludo_sessions_player2_id_index` (`player2_id`),
  KEY `ludo_sessions_current_turn_user_id_index` (`current_turn_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `movie_crawler_pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `movie_crawler_pages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `movie_crawler_website_id` bigint(20) unsigned NOT NULL,
  `title` text COLLATE utf8mb4_unicode_ci,
  `slug` text COLLATE utf8mb4_unicode_ci,
  `url` text COLLATE utf8mb4_unicode_ci,
  `movie_id` text COLLATE utf8mb4_unicode_ci,
  `page_content` longtext COLLATE utf8mb4_unicode_ci,
  `error_message` longtext COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'Pending',
  `last_fetched_at` datetime DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'movie',
  `row_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `img_port_muno_file_name` text COLLATE utf8mb4_unicode_ci,
  `bunny_file_name` text COLLATE utf8mb4_unicode_ci,
  `tmdb_poster_path` text COLLATE utf8mb4_unicode_ci,
  `vj` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `series_id` bigint(20) DEFAULT NULL,
  `is_muno` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `muno_processed` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `munowatch_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `muno_success` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `muno_message` text COLLATE utf8mb4_unicode_ci,
  `muno_series_processed` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `muno_series_success` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `muno_series_group_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_generated` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'No',
  `is_episode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `episodes_data_fetched` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `episode_data` text COLLATE utf8mb4_unicode_ci,
  `series_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `movie_crawler_websites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `movie_crawler_websites` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `name` text COLLATE utf8mb4_unicode_ci,
  `url` text COLLATE utf8mb4_unicode_ci,
  `about` text COLLATE utf8mb4_unicode_ci,
  `priority` int(11) DEFAULT '1',
  `last_fetched_at` datetime DEFAULT NULL,
  `last_tested_at` timestamp NULL DEFAULT NULL,
  `page_number` int(11) DEFAULT NULL,
  `total_movies_found` int(11) DEFAULT NULL,
  `new_movies_found` int(11) DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `fetch_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `failed_message` text COLLATE utf8mb4_unicode_ci,
  `response_data` longtext COLLATE utf8mb4_unicode_ci,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_page_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `max_page` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `email` text COLLATE utf8mb4_unicode_ci,
  `password` text COLLATE utf8mb4_unicode_ci,
  `token` text COLLATE utf8mb4_unicode_ci,
  `token_expiry` text COLLATE utf8mb4_unicode_ci,
  `current_munowatch_category_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `movie_downloads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `movie_downloads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `local_id` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `movie_model_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'Pending',
  `download_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_app',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `local_video_link` text COLLATE utf8mb4_unicode_ci,
  `download_started_at` datetime DEFAULT NULL,
  `download_completed_at` datetime DEFAULT NULL,
  `download_duration` int(11) DEFAULT NULL,
  `file_size` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `download_progress` text COLLATE utf8mb4_unicode_ci,
  `watch_progress` text COLLATE utf8mb4_unicode_ci,
  `title` text COLLATE utf8mb4_unicode_ci,
  `url` text COLLATE utf8mb4_unicode_ci,
  `image_url` text COLLATE utf8mb4_unicode_ci,
  `local_image_url` text COLLATE utf8mb4_unicode_ci,
  `thumbnail_url` text COLLATE utf8mb4_unicode_ci,
  `description` text COLLATE utf8mb4_unicode_ci,
  `genre` text COLLATE utf8mb4_unicode_ci,
  `vj` text COLLATE utf8mb4_unicode_ci,
  `content_type` text COLLATE utf8mb4_unicode_ci,
  `content_is_video` text COLLATE utf8mb4_unicode_ci,
  `is_premium` text COLLATE utf8mb4_unicode_ci,
  `episode_number` text COLLATE utf8mb4_unicode_ci,
  `is_first_episode` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_md_user` (`user_id`),
  KEY `idx_md_movie` (`movie_model_id`),
  KEY `idx_md_created` (`created_at`),
  KEY `idx_md_type` (`download_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `movie_likes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `movie_likes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `movie_model_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `browser` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  PRIMARY KEY (`id`),
  KEY `movie_likes_user_id_index` (`user_id`),
  KEY `movie_likes_movie_model_id_index` (`movie_model_id`),
  KEY `movie_likes_status_index` (`status`),
  KEY `movie_likes_created_at_index` (`created_at`),
  KEY `idx_ml_user_movie` (`user_id`,`movie_model_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `movie_models`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `movie_models` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `title` text COLLATE utf8mb4_unicode_ci,
  `external_url` text COLLATE utf8mb4_unicode_ci,
  `url` text COLLATE utf8mb4_unicode_ci,
  `image_url` text COLLATE utf8mb4_unicode_ci,
  `thumbnail_url` text COLLATE utf8mb4_unicode_ci,
  `description` text COLLATE utf8mb4_unicode_ci,
  `year` text COLLATE utf8mb4_unicode_ci,
  `rating` text COLLATE utf8mb4_unicode_ci,
  `duration` text COLLATE utf8mb4_unicode_ci,
  `size` double(8,2) DEFAULT NULL,
  `genre` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `director` text COLLATE utf8mb4_unicode_ci,
  `stars` text COLLATE utf8mb4_unicode_ci,
  `country` text COLLATE utf8mb4_unicode_ci,
  `language` text COLLATE utf8mb4_unicode_ci,
  `imdb_url` text COLLATE utf8mb4_unicode_ci,
  `imdb_rating` double(8,2) DEFAULT NULL,
  `imdb_votes` double(8,2) DEFAULT NULL,
  `imdb_id` text COLLATE utf8mb4_unicode_ci,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'movie',
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fix_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'pending' COMMENT 'Fix status: pending, fixed, error',
  `fix_error_message` text COLLATE utf8mb4_unicode_ci COMMENT 'Error message when fix_status=error',
  `fix_date` timestamp NULL DEFAULT NULL COMMENT 'Date when the record was last fixed',
  `fix_counter` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of fix attempts',
  `error` text COLLATE utf8mb4_unicode_ci,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `downloads_count` text COLLATE utf8mb4_unicode_ci,
  `in_app_downloads_count` int(10) unsigned NOT NULL DEFAULT '0',
  `gallery_downloads_count` int(10) unsigned NOT NULL DEFAULT '0',
  `views_count` text COLLATE utf8mb4_unicode_ci,
  `likes_count` text COLLATE utf8mb4_unicode_ci,
  `dislikes_count` text COLLATE utf8mb4_unicode_ci,
  `comments_count` text COLLATE utf8mb4_unicode_ci,
  `comments` text COLLATE utf8mb4_unicode_ci,
  `video_is_downloaded_to_server` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'no',
  `video_downloaded_to_server_start_time` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_downloaded_to_server_end_time` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_downloaded_to_server_duration` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_is_downloaded_to_server_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_is_downloaded_to_server_error_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_processed` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `downloaded_from_google` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `uploaded_to_from_google` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `local_video_link` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plays_on_google` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `downloaded_to_new_server` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'No',
  `new_server_path` text COLLATE utf8mb4_unicode_ci,
  `server_fail_reason` text COLLATE utf8mb4_unicode_ci,
  `actor` text COLLATE utf8mb4_unicode_ci,
  `vj` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content_is_video` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `content_type_processed` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `content_type_processed_time` datetime DEFAULT NULL,
  `is_premium` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'No',
  `episode_number` int(11) DEFAULT NULL,
  `is_first_episode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `last_listing_date` datetime DEFAULT NULL,
  `views_time_count` int(11) DEFAULT '0',
  `is_trending` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `trending_time` datetime DEFAULT NULL,
  `trending_id` int(11) DEFAULT NULL,
  `platform_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'all',
  `temp_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'Inactive',
  `video_url_tested_by_curl` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `video_url_tested_by_curl_works` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `video_url_tested_by_human` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `video_url_tested_by_human_works` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `firebase_transfer_attempted` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `firebase_transfer_transfer_in_progress` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `firebase_transfer_successful` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `firebase_transfer_failure_reason` text COLLATE utf8mb4_unicode_ci,
  `firebase_transfer_path` text COLLATE utf8mb4_unicode_ci,
  `firebase_video_url` text COLLATE utf8mb4_unicode_ci,
  `firebase_video_url_expires_at` datetime DEFAULT NULL,
  `firebase_video_tested_by_curl` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `firebase_video_tested_by_curl_works` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `firebase_video_tested_by_human` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `firebase_video_tested_by_human_works` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `old_video_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `page_source_url` text COLLATE utf8mb4_unicode_ci,
  `poster_url` text COLLATE utf8mb4_unicode_ci,
  `external_id` text COLLATE utf8mb4_unicode_ci,
  `season_number` int(11) DEFAULT '1',
  `series_title` text COLLATE utf8mb4_unicode_ci,
  `episode_title` text COLLATE utf8mb4_unicode_ci,
  `is_muno` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `muno_processed` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `munowatch_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `muno_success` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `muno_message` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_mm_type` (`type`),
  KEY `idx_mm_status` (`status`),
  KEY `idx_mm_type_status` (`type`,`status`),
  KEY `idx_mm_category` (`category_id`),
  KEY `idx_mm_genre` (`genre`),
  KEY `idx_mm_vj` (`vj`),
  KEY `idx_mm_series_listing` (`type`,`is_first_episode`,`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `movie_pics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `movie_pics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `movie_id` text COLLATE utf8mb4_unicode_ci,
  `pic_url` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `movie_searches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `movie_searches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `search_term` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `search_term_normalized` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'web',
  `search_count` int(11) NOT NULL DEFAULT '1',
  `results_count` int(11) NOT NULL DEFAULT '0',
  `has_results` tinyint(1) NOT NULL DEFAULT '0',
  `found_movie_ids` text COLLATE utf8mb4_unicode_ci,
  `click_count` int(11) NOT NULL DEFAULT '0',
  `first_searched_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_searched_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `movie_searches_user_id_last_searched_at_index` (`user_id`,`last_searched_at`),
  KEY `movie_searches_search_term_normalized_last_searched_at_index` (`search_term_normalized`,`last_searched_at`),
  KEY `movie_searches_created_at_index` (`created_at`),
  KEY `movie_searches_search_term_normalized_index` (`search_term_normalized`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `movie_views`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `movie_views` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `movie_model_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `ip_address` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `browser` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `progress` int(11) NOT NULL DEFAULT '0',
  `max_progress` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `movie_views_movie_model_id_foreign` (`movie_model_id`),
  KEY `movie_views_user_id_foreign` (`user_id`),
  KEY `idx_mv_user_movie` (`user_id`,`movie_model_id`),
  KEY `idx_mv_user_updated` (`user_id`,`updated_at`),
  KEY `idx_mv_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `movie_wishlists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `movie_wishlists` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `movie_model_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `browser` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  PRIMARY KEY (`id`),
  KEY `movie_wishlists_user_id_index` (`user_id`),
  KEY `movie_wishlists_movie_model_id_index` (`movie_model_id`),
  KEY `movie_wishlists_status_index` (`status`),
  KEY `movie_wishlists_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `munowatch_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `munowatch_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `munowatch_id` int(11) NOT NULL COMMENT 'Category ID from munowatch API',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Category name (e.g., Movies, Series, Korean, Animation)',
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL-friendly version of category name',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT 'Category description from API',
  `api_endpoint` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Specific API endpoint for this category',
  `api_parameters` json DEFAULT NULL COMMENT 'Additional API parameters for this category',
  `status` enum('active','inactive','deprecated') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `is_featured` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether to prioritize this category',
  `sort_order` int(11) NOT NULL DEFAULT '0' COMMENT 'Display order for category rotation',
  `total_pages` int(11) NOT NULL DEFAULT '0' COMMENT 'Total pages available in this category',
  `current_page` int(11) NOT NULL DEFAULT '0' COMMENT 'Current page being crawled',
  `total_videos_found` int(11) NOT NULL DEFAULT '0' COMMENT 'Total videos discovered in this category',
  `new_videos_last_crawl` int(11) NOT NULL DEFAULT '0' COMMENT 'New videos found in last crawl',
  `crawl_status` enum('pending','in_progress','completed','failed','paused') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `error_message` text COLLATE utf8mb4_unicode_ci COMMENT 'Last error message if crawling failed',
  `last_crawled_at` timestamp NULL DEFAULT NULL COMMENT 'When this category was last crawled',
  `next_crawl_at` timestamp NULL DEFAULT NULL COMMENT 'When to crawl this category next',
  `crawl_frequency_hours` int(11) NOT NULL DEFAULT '24' COMMENT 'How often to crawl this category (hours)',
  `videos_per_page` int(11) NOT NULL DEFAULT '10' COMMENT 'Expected videos per page for this category',
  `success_rate` decimal(5,2) NOT NULL DEFAULT '100.00' COMMENT 'Crawling success rate percentage',
  `metadata` json DEFAULT NULL COMMENT 'Additional category metadata from API',
  `parent_category_id` int(11) DEFAULT NULL COMMENT 'For subcategories',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `munowatch_categories_munowatch_id_unique` (`munowatch_id`),
  UNIQUE KEY `munowatch_categories_slug_unique` (`slug`),
  KEY `munowatch_categories_status_is_featured_index` (`status`,`is_featured`),
  KEY `munowatch_categories_crawl_status_next_crawl_at_index` (`crawl_status`,`next_crawl_at`),
  KEY `munowatch_categories_sort_order_status_index` (`sort_order`,`status`),
  KEY `munowatch_categories_last_crawled_at_index` (`last_crawled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `munowatch_movie_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `munowatch_movie_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `munowatch_category_id` int(11) NOT NULL COMMENT 'Category ID from munowatch dashboard API',
  `category_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Category name from dashboard (e.g., "Latest on Munowatch", "Horror Movies")',
  `category_description` text COLLATE utf8mb4_unicode_ci COMMENT 'Category description if available',
  `api_endpoint_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'dashboard' COMMENT 'Type: dashboard, browse, shows, list',
  `api_parameters` json DEFAULT NULL COMMENT 'Additional API parameters for fetching this category',
  `status` enum('active','inactive','fetching','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `is_dynamic` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Whether category is fetched dynamically from dashboard',
  `sort_order` int(11) NOT NULL DEFAULT '0' COMMENT 'Display order for category',
  `total_movies_in_category` int(11) NOT NULL DEFAULT '0' COMMENT 'Total movies found in this category',
  `movies_fetched` int(11) NOT NULL DEFAULT '0' COMMENT 'Number of movies already fetched from this category',
  `last_fetched_from_dashboard_at` timestamp NULL DEFAULT NULL COMMENT 'When category was last seen in dashboard API',
  `last_movies_fetched_at` timestamp NULL DEFAULT NULL COMMENT 'When movies were last fetched for this category',
  `sample_movies` json DEFAULT NULL COMMENT 'Sample movies from dashboard to understand category content',
  `category_metadata` json DEFAULT NULL COMMENT 'Additional metadata from dashboard API',
  `has_pagination` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether this category supports pagination for more movies',
  `pagination_endpoint` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Specific endpoint for paginated content',
  `current_page` int(11) NOT NULL DEFAULT '1' COMMENT 'Current page for pagination',
  `max_pages` int(11) DEFAULT NULL COMMENT 'Maximum pages available for this category',
  `last_error_message` text COLLATE utf8mb4_unicode_ci COMMENT 'Last error when fetching this category',
  `next_fetch_at` timestamp NULL DEFAULT NULL COMMENT 'When to fetch this category next',
  `fetch_frequency_hours` int(11) NOT NULL DEFAULT '6' COMMENT 'How often to check dashboard for this category',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `munowatch_movie_categories_munowatch_category_id_unique` (`munowatch_category_id`),
  KEY `muno_status_dynamic_idx` (`status`,`is_dynamic`),
  KEY `muno_category_id_idx` (`munowatch_category_id`),
  KEY `muno_dashboard_status_idx` (`last_fetched_from_dashboard_at`,`status`),
  KEY `muno_next_fetch_idx` (`next_fetch_at`,`status`),
  KEY `muno_sort_order_idx` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `my_counters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `my_counters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `count_value` bigint(20) NOT NULL DEFAULT '0',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'SUCCESS',
  `status_message` text COLLATE utf8mb4_unicode_ci,
  `data` longtext COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `page_visits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `page_visits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `device_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `os` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `browser` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referrer_url` text COLLATE utf8mb4_unicode_ci,
  `utm_source` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `utm_medium` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `utm_campaign` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `page_url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `button_clicked` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `time_on_page_seconds` int(10) unsigned DEFAULT NULL,
  `landed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `left_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `page_visits_created_at_index` (`created_at`),
  KEY `page_visits_button_clicked_index` (`button_clicked`),
  KEY `page_visits_country_index` (`country`),
  KEY `page_visits_user_id_foreign` (`user_id`),
  KEY `page_visits_session_id_index` (`session_id`),
  KEY `page_visits_ip_address_index` (`ip_address`),
  KEY `page_visits_device_type_index` (`device_type`),
  CONSTRAINT `page_visits_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `title` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_visit` datetime DEFAULT NULL,
  `category_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` char(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'blank.png',
  `banner_image` mediumtext COLLATE utf8mb4_unicode_ci,
  `show_in_banner` varchar(35) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `show_in_categories` varchar(35) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attributes` longtext COLLATE utf8mb4_unicode_ci,
  `is_parent` varchar(14) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `parent_id` int(11) DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `photo` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product` int(11) NOT NULL,
  `user` int(11) NOT NULL,
  `date_created` date NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `feature_photo` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metric` smallint(6) DEFAULT NULL,
  `currency` smallint(6) DEFAULT NULL,
  `description` mediumtext COLLATE utf8mb4_unicode_ci,
  `summary` text COLLATE utf8mb4_unicode_ci,
  `price_1` decimal(10,2) DEFAULT NULL,
  `price_2` decimal(10,2) DEFAULT NULL,
  `feature_photo` varchar(130) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rates` int(11) DEFAULT NULL,
  `date_added` date DEFAULT NULL,
  `date_updated` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `user` int(11) DEFAULT NULL,
  `category` int(11) DEFAULT NULL,
  `sub_category` int(11) DEFAULT NULL,
  `supplier` int(11) DEFAULT NULL,
  `url` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) DEFAULT NULL,
  `in_stock` tinyint(1) DEFAULT NULL,
  `keywords` text COLLATE utf8mb4_unicode_ci,
  `p_type` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `local_id` text COLLATE utf8mb4_unicode_ci,
  `updated_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `stripe_id` varchar(550) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stripe_price` varchar(550) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_colors` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `colors` text COLLATE utf8mb4_unicode_ci,
  `has_sizes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sizes` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `url` (`url`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `safemode_views`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `safemode_views` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `external_video_id` bigint(20) unsigned NOT NULL COMMENT 'MunoWatch video id',
  `video_title` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `genre` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'view' COMMENT 'view|play|like|mylist',
  `progress_seconds` double NOT NULL DEFAULT '0',
  `duration_seconds` double NOT NULL DEFAULT '0',
  `max_progress_seconds` double NOT NULL DEFAULT '0',
  `percentage` double NOT NULL DEFAULT '0',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active' COMMENT 'Active|Completed',
  `device` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'safemode',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sm_user_video_action` (`user_id`,`external_video_id`,`action`),
  KEY `safemode_views_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `schools`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schools` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `name` text COLLATE utf8mb4_unicode_ci,
  `district` text COLLATE utf8mb4_unicode_ci,
  `county` text COLLATE utf8mb4_unicode_ci,
  `sub_county` text COLLATE utf8mb4_unicode_ci,
  `parish` text COLLATE utf8mb4_unicode_ci,
  `address` text COLLATE utf8mb4_unicode_ci,
  `p_o_box` text COLLATE utf8mb4_unicode_ci,
  `email` text COLLATE utf8mb4_unicode_ci,
  `website` text COLLATE utf8mb4_unicode_ci,
  `phone` text COLLATE utf8mb4_unicode_ci,
  `fax` text COLLATE utf8mb4_unicode_ci,
  `school_type` text COLLATE utf8mb4_unicode_ci,
  `service_code` text COLLATE utf8mb4_unicode_ci,
  `reg_no` text COLLATE utf8mb4_unicode_ci,
  `center_no` text COLLATE utf8mb4_unicode_ci,
  `operation_status` text COLLATE utf8mb4_unicode_ci,
  `founder` text COLLATE utf8mb4_unicode_ci,
  `funder` text COLLATE utf8mb4_unicode_ci,
  `boys_girls` text COLLATE utf8mb4_unicode_ci,
  `day_boarding` text COLLATE utf8mb4_unicode_ci,
  `registry_status` text COLLATE utf8mb4_unicode_ci,
  `nearest_school` text COLLATE utf8mb4_unicode_ci,
  `nearest_school_distance` text COLLATE utf8mb4_unicode_ci,
  `founding_year` int(11) DEFAULT NULL,
  `level` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` text COLLATE utf8mb4_unicode_ci,
  `longitude` text COLLATE utf8mb4_unicode_ci,
  `highest_class` text COLLATE utf8mb4_unicode_ci,
  `access` text COLLATE utf8mb4_unicode_ci,
  `details` text COLLATE utf8mb4_unicode_ci,
  `has_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_website` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contated` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `replied` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `success` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `reply_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` text COLLATE utf8mb4_unicode_ci,
  `photo` text COLLATE utf8mb4_unicode_ci,
  `photos` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `scraper_models`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scraper_models` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` text COLLATE utf8mb4_unicode_ci,
  `title` text COLLATE utf8mb4_unicode_ci,
  `datae` longtext COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `series_movies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `series_movies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `title` text COLLATE utf8mb4_unicode_ci,
  `Category` text COLLATE utf8mb4_unicode_ci,
  `description` text COLLATE utf8mb4_unicode_ci,
  `thumbnail` text COLLATE utf8mb4_unicode_ci,
  `total_seasons` int(11) DEFAULT NULL,
  `total_episodes` int(11) DEFAULT NULL,
  `total_views` int(11) DEFAULT NULL,
  `total_rating` int(11) DEFAULT NULL,
  `is_active` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'No',
  `fix_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'pending' COMMENT 'Fix status: pending, fixed, error',
  `fix_error_message` text COLLATE utf8mb4_unicode_ci COMMENT 'Error message when fix_status=error',
  `fix_date` timestamp NULL DEFAULT NULL COMMENT 'Date when the record was last fixed',
  `fix_counter` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Number of fix attempts',
  `external_url` text COLLATE utf8mb4_unicode_ci,
  `is_premium` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'No',
  `external_id` text COLLATE utf8mb4_unicode_ci,
  `vj` text COLLATE utf8mb4_unicode_ci,
  `genre` text COLLATE utf8mb4_unicode_ci,
  `language` text COLLATE utf8mb4_unicode_ci,
  `country` text COLLATE utf8mb4_unicode_ci,
  `year` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rating` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `poster_url` text COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `is_muno` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `muno_processed` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `munowatch_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `series_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `company_id` bigint(20) unsigned NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `image` text COLLATE utf8mb4_unicode_ci,
  `buying_price` bigint(20) DEFAULT '0',
  `selling_price` bigint(20) DEFAULT '0',
  `expected_profit` bigint(20) DEFAULT '0',
  `earned_profit` bigint(20) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `company_id` bigint(20) unsigned NOT NULL,
  `created_by_id` bigint(20) unsigned NOT NULL,
  `stock_category_id` bigint(20) unsigned NOT NULL,
  `stock_sub_category_id` bigint(20) unsigned NOT NULL,
  `financial_period_id` bigint(20) unsigned NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `image` text COLLATE utf8mb4_unicode_ci,
  `barcode` text COLLATE utf8mb4_unicode_ci,
  `sku` text COLLATE utf8mb4_unicode_ci,
  `generate_sku` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `update_sku` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gallery` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `buying_price` bigint(20) NOT NULL DEFAULT '0',
  `selling_price` bigint(20) NOT NULL DEFAULT '0',
  `original_quantity` bigint(20) NOT NULL DEFAULT '0',
  `current_quantity` bigint(20) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_records` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `company_id` bigint(20) unsigned NOT NULL,
  `stock_item_id` bigint(20) unsigned NOT NULL,
  `stock_category_id` bigint(20) unsigned NOT NULL,
  `stock_sub_category_id` bigint(20) unsigned NOT NULL,
  `created_by_id` bigint(20) unsigned NOT NULL,
  `sku` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `measurement_unit` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` double(8,2) NOT NULL,
  `selling_price` double(8,2) NOT NULL,
  `total_sales` double(8,2) NOT NULL,
  `profit` bigint(20) NOT NULL DEFAULT '0',
  `financial_period_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_sub_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_sub_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `company_id` bigint(20) unsigned NOT NULL,
  `stock_category_id` bigint(20) unsigned NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `image` text COLLATE utf8mb4_unicode_ci,
  `buying_price` bigint(20) DEFAULT '0',
  `selling_price` bigint(20) DEFAULT '0',
  `expected_profit` bigint(20) DEFAULT '0',
  `earned_profit` bigint(20) DEFAULT '0',
  `measurement_unit` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `current_quantity` bigint(20) DEFAULT '0',
  `reorder_level` bigint(20) DEFAULT '0',
  `in_stock` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'No',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `streaming_stations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `streaming_stations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'General',
  `frequency` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `logo_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Uganda',
  `language` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'English',
  `region` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT '0',
  `votes` int(10) unsigned NOT NULL DEFAULT '0',
  `listeners_count` int(10) unsigned NOT NULL DEFAULT '0',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `streaming_stations_slug_unique` (`slug`),
  KEY `streaming_stations_type_index` (`type`),
  KEY `streaming_stations_category_index` (`category`),
  KEY `streaming_stations_status_index` (`status`),
  KEY `streaming_stations_is_featured_index` (`is_featured`),
  KEY `streaming_stations_type_status_index` (`type`,`status`),
  KEY `streaming_stations_sort_order_index` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `streaming_urls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `streaming_urls` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `streaming_station_id` bigint(20) unsigned NOT NULL,
  `url` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `format` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quality` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bitrate` int(10) unsigned DEFAULT NULL,
  `cdn_provider` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referrer_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `needs_token_refresh` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `sort_order` int(10) unsigned NOT NULL DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `streaming_urls_streaming_station_id_index` (`streaming_station_id`),
  KEY `streaming_urls_status_index` (`status`),
  KEY `streaming_urls_is_default_index` (`is_default`),
  CONSTRAINT `streaming_urls_streaming_station_id_foreign` FOREIGN KEY (`streaming_station_id`) REFERENCES `streaming_stations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscription_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subscription_plans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Plan name in English',
  `name_luganda` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Plan name in Luganda',
  `name_swahili` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Plan name in Swahili',
  `slug` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL-friendly identifier',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT 'Plan description in English',
  `description_luganda` text COLLATE utf8mb4_unicode_ci COMMENT 'Plan description in Luganda',
  `description_swahili` text COLLATE utf8mb4_unicode_ci COMMENT 'Plan description in Swahili',
  `price` decimal(15,2) NOT NULL COMMENT 'Plan price',
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UGX' COMMENT 'Currency code (UGX, USD, etc)',
  `duration_days` int(11) NOT NULL COMMENT 'Subscription duration in days',
  `features` text COLLATE utf8mb4_unicode_ci COMMENT 'Plan features in HTML (English)',
  `features_luganda` text COLLATE utf8mb4_unicode_ci COMMENT 'Plan features in HTML (Luganda)',
  `features_swahili` text COLLATE utf8mb4_unicode_ci COMMENT 'Plan features in HTML (Swahili)',
  `status` enum('Active','Inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active' COMMENT 'Plan availability status',
  `is_featured` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Show as featured/recommended plan',
  `sort_order` int(11) NOT NULL DEFAULT '0' COMMENT 'Display order (ascending)',
  `discount_percentage` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'Discount percentage (0-100)',
  `is_trial` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Is this a trial plan?',
  `max_downloads` int(11) DEFAULT NULL COMMENT 'Maximum downloads allowed (NULL = unlimited)',
  `max_watchlist` int(11) DEFAULT NULL COMMENT 'Maximum watchlist items (NULL = unlimited)',
  `ad_free` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Is this plan ad-free?',
  `hd_streaming` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Allow HD streaming?',
  `created_by` bigint(20) unsigned DEFAULT NULL COMMENT 'Admin user who created this plan',
  `updated_by` bigint(20) unsigned DEFAULT NULL COMMENT 'Admin user who last updated this plan',
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscription_plans_slug_unique` (`slug`),
  KEY `idx_status` (`status`),
  KEY `idx_sort_order` (`sort_order`,`status`),
  KEY `idx_slug` (`slug`),
  KEY `idx_duration` (`duration_days`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscription_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subscription_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `subscription_id` bigint(20) unsigned NOT NULL COMMENT 'Related subscription',
  `user_id` bigint(20) unsigned NOT NULL COMMENT 'User who made the transaction',
  `transaction_type` enum('Initial','Renewal','Upgrade','Downgrade','Refund','Withdrawal') COLLATE utf8mb4_unicode_ci DEFAULT 'Initial',
  `platform` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL COMMENT 'Transaction amount',
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UGX' COMMENT 'Currency code',
  `status` enum('Pending','Processing','Completed','Failed','Refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending' COMMENT 'Transaction status',
  `pesapal_tracking_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Pesapal order_tracking_id',
  `merchant_reference` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Pesapal merchant reference',
  `payment_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Payment method used (Visa, MTN, etc)',
  `confirmation_code` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Pesapal confirmation code',
  `payment_account` text COLLATE utf8mb4_unicode_ci COMMENT 'Payment account details (masked)',
  `request_payload` json DEFAULT NULL COMMENT 'Original payment request data',
  `response_payload` json DEFAULT NULL COMMENT 'Payment gateway response',
  `error_message` text COLLATE utf8mb4_unicode_ci COMMENT 'Error message if transaction failed',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'User IP address',
  `user_agent` text COLLATE utf8mb4_unicode_ci COMMENT 'User browser/device info',
  `refunded_by` bigint(20) unsigned DEFAULT NULL COMMENT 'Admin who processed refund',
  `refunded_at` datetime DEFAULT NULL COMMENT 'When refund was processed',
  `refund_reason` text COLLATE utf8mb4_unicode_ci COMMENT 'Reason for refund',
  `number_of_times_checked` int(11) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_subscription` (`subscription_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_pesapal_tracking` (`pesapal_tracking_id`),
  KEY `idx_merchant_ref` (`merchant_reference`),
  KEY `idx_user_date` (`user_id`,`created_at`),
  KEY `idx_transaction_type` (`transaction_type`),
  KEY `idx_st_platform` (`platform`),
  KEY `idx_st_status_type` (`status`,`transaction_type`),
  KEY `idx_st_status_type_date` (`status`,`transaction_type`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subscriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL COMMENT 'User who owns this subscription',
  `plan_id` bigint(20) unsigned NOT NULL COMMENT 'Subscription plan',
  `days` int(11) NOT NULL COMMENT 'Number of days in this subscription',
  `start_date_time` datetime DEFAULT NULL COMMENT 'Subscription start date and time',
  `end_date_time` datetime DEFAULT NULL COMMENT 'Subscription end date and time',
  `grace_period_end` datetime DEFAULT NULL COMMENT 'Grace period end (3 days after end_date_time)',
  `status` enum('Pending','Active','Expired','Cancelled','Failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending' COMMENT 'Subscription status',
  `auto_renew` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Automatically renew subscription?',
  `payment_method` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pesapal' COMMENT 'Payment method used',
  `payment_status` enum('Pending','Processing','Completed','Failed','Refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending' COMMENT 'Payment status',
  `pesapal_transaction_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Internal transaction ID',
  `pesapal_tracking_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Pesapal order_tracking_id',
  `pesapal_merchant_reference` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Unique merchant reference',
  `pesapal_signature` text COLLATE utf8mb4_unicode_ci COMMENT 'Pesapal signature for verification',
  `pesapal_response` json DEFAULT NULL COMMENT 'Full Pesapal response JSON',
  `payment_url` text COLLATE utf8mb4_unicode_ci,
  `payment_confirmed_at` timestamp NULL DEFAULT NULL,
  `failed_at` timestamp NULL DEFAULT NULL,
  `amount_paid` decimal(15,2) NOT NULL COMMENT 'Amount paid for this subscription',
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UGX' COMMENT 'Currency code',
  `is_extension` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Is this an extension of existing subscription?',
  `extended_from_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Previous subscription ID if extension',
  `cancelled_at` datetime DEFAULT NULL COMMENT 'When subscription was cancelled',
  `cancelled_reason` text COLLATE utf8mb4_unicode_ci COMMENT 'Reason for cancellation',
  `payment_failure_reason` text COLLATE utf8mb4_unicode_ci,
  `cancelled_by` bigint(20) unsigned DEFAULT NULL COMMENT 'User/Admin who cancelled',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'User IP address during purchase',
  `user_agent` text COLLATE utf8mb4_unicode_ci COMMENT 'User browser/device info',
  `expiry_notification_sent` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Has expiry notification been sent?',
  `expiry_notification_at` datetime DEFAULT NULL COMMENT 'When expiry notification was sent',
  `app_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'ugflix',
  `platform` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'android',
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscriptions_pesapal_merchant_reference_unique` (`pesapal_merchant_reference`),
  KEY `idx_user_status` (`user_id`,`status`),
  KEY `idx_status_end_date` (`status`,`end_date_time`),
  KEY `idx_pesapal_tracking` (`pesapal_tracking_id`),
  KEY `idx_merchant_reference` (`pesapal_merchant_reference`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_expiry_check` (`status`,`end_date_time`,`expiry_notification_sent`),
  KEY `idx_plan` (`plan_id`),
  KEY `idx_extended_from` (`extended_from_id`),
  KEY `idx_sub_app_type` (`app_type`),
  KEY `idx_sub_app_type_payment_status` (`app_type`,`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `trending_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trending_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `movie_model_id` bigint(20) unsigned DEFAULT NULL,
  `title` text COLLATE utf8mb4_unicode_ci,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_url` text COLLATE utf8mb4_unicode_ci,
  `description` text COLLATE utf8mb4_unicode_ci,
  `views_count` int(11) DEFAULT NULL,
  `views_time` int(11) DEFAULT NULL,
  `url` text COLLATE utf8mb4_unicode_ci,
  `trending_time` datetime DEFAULT NULL,
  `day_time` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_sent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'No',
  `sent_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `trivia_meta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trivia_meta` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `trivia_meta_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `trivia_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trivia_questions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `difficulty` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `format` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'multiple_choice',
  `correct_answer` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `wrong_answers` json NOT NULL,
  `hint` text COLLATE utf8mb4_unicode_ci,
  `image_url` text COLLATE utf8mb4_unicode_ci,
  `points` int(11) NOT NULL DEFAULT '10',
  `timer_seconds` int(11) NOT NULL DEFAULT '15',
  `version` int(11) NOT NULL DEFAULT '1',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `trivia_questions_difficulty_index` (`difficulty`),
  KEY `trivia_questions_category_index` (`category`),
  KEY `trivia_questions_format_index` (`format`),
  KEY `trivia_questions_status_index` (`status`),
  KEY `trivia_questions_version_index` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_blocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_blocks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `blocker_id` int(10) unsigned NOT NULL,
  `blocked_user_id` int(10) unsigned NOT NULL,
  `reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `block_type` enum('user_initiated','moderator_initiated','automatic') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user_initiated',
  `status` enum('active','expired','removed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `expires_at` timestamp NULL DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_block` (`blocker_id`,`blocked_user_id`),
  KEY `user_blocks_blocker_id_index` (`blocker_id`),
  KEY `user_blocks_blocked_user_id_index` (`blocked_user_id`),
  KEY `user_blocks_status_index` (`status`),
  KEY `user_blocks_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `google_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `game_coins_balance` int(11) NOT NULL DEFAULT '0',
  `is_busy_in_game` tinyint(1) NOT NULL DEFAULT '0',
  `busy_since` timestamp NULL DEFAULT NULL,
  `total_games_played` int(11) NOT NULL DEFAULT '0',
  `total_games_won` int(11) NOT NULL DEFAULT '0',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `terms_of_service_accepted` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `privacy_policy_accepted` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `community_guidelines_accepted` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `marketing_emails_consent` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_processing_consent` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content_moderation_consent` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `terms_accepted_date` timestamp NULL DEFAULT NULL,
  `privacy_accepted_date` timestamp NULL DEFAULT NULL,
  `guidelines_accepted_date` timestamp NULL DEFAULT NULL,
  `notification_preferences` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `push_notifications` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_notifications` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile_visibility` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Public',
  `content_filtering` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'On',
  `safe_mode` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'On',
  `location_sharing` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `analytics_consent` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `crash_reporting` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_google_id_unique` (`google_id`),
  KEY `users_is_busy_in_game_index` (`is_busy_in_game`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `video_playback_failures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `video_playback_failures` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `user_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `movie_id` bigint(20) unsigned DEFAULT NULL,
  `movie_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_url` text COLLATE utf8mb4_unicode_ci,
  `transformed_url` text COLLATE utf8mb4_unicode_ci,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `error_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `retry_count` int(11) NOT NULL DEFAULT '0',
  `device_model` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_os` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_os_version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `app_version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `player_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `network_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_subscription` tinyint(1) NOT NULL DEFAULT '0',
  `subscription_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subscription_expires_at` timestamp NULL DEFAULT NULL,
  `screen_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `additional_data` json DEFAULT NULL,
  `status` enum('pending','investigating','resolved','ignored') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  `fix_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING' COMMENT 'PENDING, FIXED, FAILED',
  `fix_status_message` text COLLATE utf8mb4_unicode_ci COMMENT 'Explanation of what happened during fix attempt',
  `number_of_fix_attempts` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Incremented on every fix attempt',
  `last_fix_attempt_at` timestamp NULL DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `video_playback_failures_user_id_index` (`user_id`),
  KEY `video_playback_failures_movie_id_index` (`movie_id`),
  KEY `video_playback_failures_error_type_index` (`error_type`),
  KEY `video_playback_failures_status_index` (`status`),
  KEY `video_playback_failures_created_at_index` (`created_at`),
  KEY `video_playback_failures_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `video_playback_failures_movie_id_created_at_index` (`movie_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `video_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `video_transfers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `source_url` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Original video URL to transfer',
  `source_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Source type: url, file, etc',
  `source_size` bigint(20) DEFAULT NULL COMMENT 'Source video size in bytes',
  `drive_file_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Google Drive file ID',
  `drive_file_name` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'File name in Google Drive',
  `drive_public_url` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Public playable URL from Google Drive',
  `drive_download_url` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Direct download URL from Google Drive',
  `drive_folder_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Google Drive folder ID',
  `status` enum('pending','downloading','uploading','completed','failed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'Current transfer status',
  `progress` int(11) NOT NULL DEFAULT '0' COMMENT 'Progress percentage (0-100)',
  `bytes_transferred` bigint(20) NOT NULL DEFAULT '0' COMMENT 'Bytes transferred so far',
  `total_bytes` bigint(20) NOT NULL DEFAULT '0' COMMENT 'Total bytes to transfer',
  `started_at` timestamp NULL DEFAULT NULL COMMENT 'When transfer started',
  `completed_at` timestamp NULL DEFAULT NULL COMMENT 'When transfer completed',
  `duration_seconds` int(11) DEFAULT NULL COMMENT 'Total transfer duration in seconds',
  `error_message` text COLLATE utf8mb4_unicode_ci COMMENT 'Error message if failed',
  `error_details` text COLLATE utf8mb4_unicode_ci COMMENT 'Detailed error information',
  `retry_count` int(11) NOT NULL DEFAULT '0' COMMENT 'Number of retry attempts',
  `last_retry_at` timestamp NULL DEFAULT NULL COMMENT 'Last retry timestamp',
  `video_title` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Video title/name',
  `video_description` text COLLATE utf8mb4_unicode_ci COMMENT 'Video description',
  `video_duration` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Video duration (e.g., 02:15:30)',
  `video_quality` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Video quality (e.g., 1080p, 720p)',
  `video_format` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Video format (e.g., mp4, mkv)',
  `transfer_metadata` json DEFAULT NULL COMMENT 'Additional metadata as JSON',
  `transferred_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'User who initiated transfer',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT 'Admin notes',
  `average_speed_mbps` decimal(10,2) DEFAULT NULL COMMENT 'Average transfer speed in Mbps',
  `server_location` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Server location used for transfer',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `video_transfers_status_index` (`status`),
  KEY `video_transfers_drive_file_id_index` (`drive_file_id`),
  KEY `video_transfers_created_at_index` (`created_at`),
  KEY `video_transfers_status_created_at_index` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `watchlists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `watchlists` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `movie_model_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('active','removed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `added_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_wl_user_movie` (`user_id`,`movie_model_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'2014_10_12_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'2014_10_12_100000_create_password_reset_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'2019_08_19_000000_create_failed_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2019_12_14_000001_create_personal_access_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2016_01_04_173148_create_admin_tables',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2023_12_27_191449_create_companies_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2023_12_28_175439_add_more_data_to_users_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2023_12_28_184634_create_stock_categories_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2023_12_28_191608_create_stock_sub_categories_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2023_12_29_185415_create_financial_periods_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2023_12_29_193135_add_email_to_users_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2023_12_30_170905_create_stock_items_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2024_01_01_181454_add_in_stock_stock_sub_categories',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2024_01_01_182639_create_stock_records_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2024_01_03_174223_add_profit_col_stock_records',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2024_01_03_175748_add_financial_period_id_to',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2024_01_06_180349_add_currency_to_companies',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2024_01_30_162221_create_scraper_models_table',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2024_01_30_165212_create_movie_models_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2024_01_31_025031_create_africa_talking_responses_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2024_03_03_200757_create_pages_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2024_03_03_200905_create_links_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2024_03_03_203609_add_status_links',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2024_03_06_002911_add_type_links',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2024_03_06_025744_create_schools_table',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2024_03_06_033705_add_url_to_schools',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2024_03_07_182821_add_photo_to_schools',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2024_03_08_070221_add_last_visit_to_pages',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2024_03_08_072447_create_learning_material_categories_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2024_03_08_072941_add_external_id',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2024_03_08_081658_add_last_visit',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2024_03_08_083026_add_cat_to_pages',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2024_03_08_094204_create_learning_material_posts_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2024_03_08_095345_add_cols_to_learning_material_posts',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2024_03_12_135449_create_series_movies_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2024_03_21_235713_create_movie_views_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2024_03_22_002513_create_movie_likes_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2024_03_22_002943_add_progress_cols_to_movie_views',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2024_05_19_053222_add_progress_cols_to_series_movies',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2024_05_19_060517_add_progress_cols_s_to_series_movies',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2024_05_19_083910_add_secret_code_to_admin_users',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2024_05_26_045651_add_downloaded_from_google_movies',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2024_05_26_055851_add_local_viodeo_link_movies',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2024_05_26_074223_add_plays_on_google_movies',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2024_08_16_215714_add_new_server_things_movie_models',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2024_08_16_215923_add_new_server_fail_reason_movie_models',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2025_04_13_200648_create_my_counters_table',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2025_04_13_204335_add_status_to_my_counters',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2025_04_13_211507_add__actor',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2025_04_13_214135_add_vj',33);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2025_04_14_200342_add_content_type',34);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2025_04_14_204704_add_content_prem',35);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2025_04_15_114843_add_sourse_to__series_movie',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2025_04_16_233337_add_is_firs_movie_models',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2025_04_19_222917_add_last_listing_date_movie_models',38);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2025_04_27_110601_add_missing_params_to_admin_users',39);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2025_04_28_185934_create_movie_downloads_table',39);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2025_04_30_090619_add_views_count_to_movie_models',39);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2025_04_30_125008_add_trending_to_movie_models',40);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2025_04_30_125558_create_trending_notifications_table',40);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2025_04_30_130247_add_day_time_to_trending_notifications',40);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2025_04_30_131545_add_is_sent_trending_notifications',40);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2025_04_30_213607_add_is_sent_movie_downloads',41);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2025_05_13_005457_change_languages_spoken_to_text',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2025_05_17_093642_add_type_to_chat_heads',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2025_05_18_093856_change_online_status',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2025_07_03_151704_add_platform_type_to_movie_models',44);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2025_07_08_194350_create_content_reports_table',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2025_07_08_194601_create_user_blocks_table',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2025_07_08_194624_create_content_moderation_logs_table',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2025_07_08_194638_add_legal_consent_fields_to_users_table',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2025_07_08_201457_add_legal_consent_fields_to_admin_users_table',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2025_07_09_015718_make_some_fields_nullable_for_content_reports',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2025_07_09_042457_make_temp_status_to_movies_model_table',46);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2025_08_24_220312_make_is_guest_to_admin_users',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'2025_09_26_111919_make_is_firebase_things_to__movie_models',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2024_01_01_000000_create_watchlists_table',48);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2025_09_30_023308_create_movie_crawler_websites_table',48);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2025_09_30_024939_create_movie_crawler_pages_table',48);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (80,'2025_09_30_044305_add_page_source_url_to_movie_models',48);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2025_10_02_050000_recreate_movie_likes_table_without_constraints',49);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2025_10_02_051000_create_movie_wishlists_table',49);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (83,'2025_10_03_000001_create_subscription_plans_table',49);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (84,'2025_10_03_000002_create_subscriptions_table',49);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (85,'2025_10_03_000003_create_subscription_transactions_table',49);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (86,'2025_10_04_004005_add_payment_tracking_to_subscriptions_table',49);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (87,'2025_10_08_201346_create_munowatch_categories_table',50);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (88,'2025_10_08_202924_add_current_munowatch_category_id_to_movie_crawler_websites_table',51);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (89,'2025_10_08_202953_add_current_munowatch_category_id_to_movie_crawler_websites_table',51);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (90,'2025_10_08_204058_create_munowatch_movie_categories_table',52);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (91,'2025_10_08_224055_add_poster_url_to_movie_models',53);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (92,'2025_10_08_224256_add_external_id_to_movie_models',54);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (93,'2025_10_08_224446_change_duration_to_movie_models',55);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (94,'2025_10_08_231018_add_episode_season_fields_to_movie_models',56);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (95,'2025_10_08_231052_add_munowatch_fields_to_series_movies',56);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (96,'2025_10_08_232606_add_default_type_to_movie_crawler_pages_table',56);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (97,'2025_10_08_232955_add_notes_to_movie_crawler_pages_table',56);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (98,'2025_10_09_112930_add_is_muno_to_movie_models',57);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (99,'2025_10_09_121935_add_is_muno_success_to_movie_models',58);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (100,'2025_10_09_134531_add_is_muno_success_to_movie_crawler_pages',58);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (101,'2025_10_10_104135_add_google_id_to_users_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (102,'2025_10_10_135600_add_google_oauth_fields_to_admin_users_table',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (103,'2025_10_11_024213_add_notification_tracking_to_admin_users',60);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (104,'2025_10_15_130822_add_is_number_of_times_to_subscription_transactions',61);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (105,'2025_10_16_233749_add_serries_to_movie_crawler_pages',62);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (106,'2025_10_19_000001_create_video_transfers_table',63);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (107,'2025_10_20_191455_add_app_type_to_users',64);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (108,'2025_10_20_193238_add_app_type_subscriptions',64);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (109,'2025_10_20_193451_add_app_type_subscriptions',64);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (110,'2025_10_20_231634_create_movie_searches_table',65);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (111,'2025_10_21_084236_remove_foreign_key_from_movie_searches_table',66);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (112,'2025_10_22_002705_create_movie_pics_table',67);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (113,'2025_10_24_225320_add_app_page_id_crawler_pages',68);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (114,'2025_10_25_185554_add_episode_id_crawler_pages',69);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (115,'2025_10_25_210014_add_existing_serie_series_movies',69);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (116,'2024_12_25_000001_create_video_playback_failures_table',70);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (117,'2026_01_13_023328_change_response_data_to_longtext_in_movie_crawler_websites',71);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (118,'2026_01_13_091833_add_import_fields_to_admin_users_table',71);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (119,'2026_01_28_000001_create_game_sessions_table',71);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (120,'2026_01_28_000002_create_game_invitations_table',71);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (121,'2026_01_28_100001_add_cut_card_to_game_sessions',71);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (122,'2026_01_29_000001_create_ludo_sessions_table',71);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (123,'2026_01_29_001837_add_forfeit_user_id_to_game_sessions_table',71);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (124,'2026_01_29_002505_create_coin_transactions_table',71);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (125,'2026_01_29_002638_add_game_coins_balance_to_users_table',71);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (126,'2026_01_29_003000_add_game_coins_balance_to_admin_users_table',71);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (127,'2026_01_29_004000_fix_coin_transactions_foreign_keys',71);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (128,'2026_01_29_023356_add_last_poll_columns_to_game_sessions_table',71);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (129,'2026_01_30_000001_add_audio_fields_to_chat_messages_table',71);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (130,'2026_01_29_150000_add_busy_state_to_users_and_sessions',72);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (131,'2026_01_29_221527_add_busy_state_columns_to_admin_users_table',72);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (132,'2026_02_07_000001_add_fix_columns_to_video_playback_failures_table',73);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (133,'2026_02_10_000001_create_blog_posts_table',74);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (134,'2026_02_10_112740_create_safemode_views_table',74);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (135,'2026_02_10_200000_add_fix_columns_to_series_movies_and_movie_models',75);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (136,'2025_10_25_000001_add_payment_failure_reason_to_subscriptions',76);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (137,'2026_03_05_182843_make_subscription_dates_nullable',77);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (138,'2026_06_15_000001_create_streaming_stations_table',78);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (139,'2026_03_09_182824_add_withdrawal_to_subscription_transactions_type',79);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (140,'2025_07_22_000000_add_download_type_to_movie_downloads',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (141,'2026_03_13_000000_add_download_type_counts_to_movie_models',81);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (142,'2026_06_20_000001_create_page_visits_table',82);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (143,'2026_03_26_100000_create_game_stats_table',83);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (144,'2026_03_14_000001_add_performance_indexes',84);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (145,'2026_03_26_000001_create_checkers_tables',84);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (146,'2026_03_26_000001_create_trivia_questions_table',84);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (147,'2026_03_29_000001_add_platform_to_subscription_transactions',84);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (148,'2026_04_02_000001_create_cache_table',85);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (149,'2026_04_02_000002_create_sessions_table',85);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (150,'2026_04_02_000003_create_jobs_table',85);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (151,'2026_04_02_000004_add_optimization_indexes',86);
