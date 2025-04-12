-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: localhost:3306
-- Thời gian đã tạo: Th3 26, 2025 lúc 08:16 PM
-- Phiên bản máy phục vụ: 8.0.36
-- Phiên bản PHP: 8.1.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `psolutio_order`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `fb_account_types`
--

CREATE TABLE `fb_account_types` (
  `id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `fb_account_types`
--

INSERT INTO `fb_account_types` (`id`, `name`, `created_at`) VALUES
(1, 'Tài Khoản +7', '2025-03-26 09:14:27'),
(2, 'TÀI KHOẢN +7 VNĐ', '2025-03-26 09:18:54'),
(3, 'TÀI KHOẢN -7 VNĐ', '2025-03-26 12:10:00'),
(4, 'TÀI KHOẢN -7 VNĐ', '2025-03-26 12:10:48'),
(5, '1', '2025-03-26 12:12:54'),
(6, 'TÀI KHOẢN +7 VNĐ', '2025-03-26 12:14:27');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `fb_configs`
--

CREATE TABLE `fb_configs` (
  `id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_type_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `fb_configs`
--

INSERT INTO `fb_configs` (`id`, `name`, `account_id`, `source`, `account_type_id`, `created_at`) VALUES
(1, 'Config for type 1', 'id tài khoản5', '2', 1, '2025-03-26 09:37:46'),
(2, 'Config for type 1 (Đơn #2)', 'id tài khoản5', '2', 1, '2025-03-26 10:06:48'),
(3, 'Config for type 1 (Đơn #3)', 'id tài khoản55', '2', 1, '2025-03-26 10:06:53'),
(4, 'Config for type 1 (Đơn #3) (Đơn #3)', 'id tài khoản55', '2', 1, '2025-03-26 10:06:59'),
(5, 'Config for type 1 (Đơn #3) (Đơn #3) (Đơn #3)', 'id tài khoản55', '2', 1, '2025-03-26 10:07:03'),
(6, 'Config for type 1 (Đơn #4)', '9978978', '2', 1, '2025-03-26 10:07:39'),
(7, 'Config for type 1 (Đơn #5)', '22331235', '2', 1, '2025-03-26 10:09:39'),
(8, 'TÀI KHOẢN +7 VNĐ - 26/03/2025 17:13', '', 'GIANG', 2, '2025-03-26 10:13:39'),
(9, 'Tài Khoản +7 - 26/03/2025 17:22', '', 'MINH', 1, '2025-03-26 10:22:32'),
(10, 'Tài Khoản +7 - 26/03/2025 17:22 (Đơn #8)', '11111111', 'MINH', 1, '2025-03-26 10:22:45'),
(11, 'Tài Khoản +7 - 26/03/2025 17:22 (Đơn #8) (Đơn #8)', '11111111', 'MINH', 1, '2025-03-26 10:22:45'),
(12, 'Tài Khoản +7 - 26/03/2025 18:35', '', 'GIANG', 1, '2025-03-26 11:35:11'),
(13, 'TÀI KHOẢN +7 VNĐ - 26/03/2025 18:35', '', 'GIANG', 2, '2025-03-26 11:35:24'),
(14, 'TÀI KHOẢN +7 VNĐ - 26/03/2025 18:35', '', 'GIANG', 2, '2025-03-26 11:35:30'),
(15, 'Tài Khoản +7 - 26/03/2025 18:35', '', 'Giang', 1, '2025-03-26 11:35:39'),
(16, 'Tài Khoản +7 - 26/03/2025 18:35 (Đơn #12)', 'auto-17429818666456546745747', 'Giang', 1, '2025-03-26 13:06:29');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `fb_orders`
--

CREATE TABLE `fb_orders` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `fb_config_id` int NOT NULL,
  `quantity` int NOT NULL,
  `source` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
  `notification` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `fb_orders`
--

INSERT INTO `fb_orders` (`id`, `user_id`, `fb_config_id`, `quantity`, `source`, `status`, `notification`, `created_at`) VALUES
(1, 1, 1, 1, '2', 'Processed', '1231231231231213', '2025-03-26 09:37:46'),
(2, 1, 2, 11, '1', 'Pending', '12223as', '2025-03-26 09:47:08'),
(3, 1, 5, 1, '1', 'Processed', 'nhiều tiền', '2025-03-26 09:56:01'),
(4, 1, 6, 2, '3', 'Pending', 'test', '2025-03-26 10:07:16'),
(5, 1, 7, 2, '1', 'Pending', '112', '2025-03-26 10:09:05'),
(6, 1, 1, 1, '1', 'Pending', '31231231', '2025-03-26 10:12:23'),
(7, 1, 8, 1, '3', 'Pending', 'qư', '2025-03-26 10:13:39'),
(8, 3, 11, 1, '1', 'Processed', '', '2025-03-26 10:22:32'),
(9, 1, 12, 1, '3', 'Pending', '', '2025-03-26 11:35:11'),
(10, 1, 13, 2, '3', 'Pending', '', '2025-03-26 11:35:24'),
(11, 1, 14, 2, '3', 'Pending', '', '2025-03-26 11:35:30'),
(12, 1, 16, 2, '2', 'Pending', '', '2025-03-26 11:35:39');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `fb_sources`
--

CREATE TABLE `fb_sources` (
  `id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `fb_sources`
--

INSERT INTO `fb_sources` (`id`, `name`, `created_at`, `status`) VALUES
(1, 'MINH', '2025-03-26 09:13:54', 'active'),
(2, 'Giang', '2025-03-26 09:13:58', 'inactive'),
(3, 'GIANG', '2025-03-26 09:19:02', 'active'),
(4, 'mai', '2025-03-26 12:11:04', 'active'),
(5, 'mai', '2025-03-26 12:11:08', 'active'),
(6, 'mai', '2025-03-26 12:11:11', 'active'),
(7, 'mai', '2025-03-26 12:11:26', 'active'),
(8, 'mai', '2025-03-26 12:11:58', 'active'),
(9, '1', '2025-03-26 12:12:56', 'active'),
(10, '1', '2025-03-26 12:12:59', 'active'),
(11, '2', '2025-03-26 12:13:03', 'active'),
(12, '3', '2025-03-26 12:13:06', 'active'),
(13, '3', '2025-03-26 12:14:20', 'active');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `logs`
--

CREATE TABLE `logs` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `action` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `details` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `logs`
--

INSERT INTO `logs` (`id`, `user_id`, `action`, `details`, `created_at`) VALUES
(1, 1, 'view_dashboard', '{\"time\":\"2025-03-26 16:08:18\"}', '2025-03-26 09:08:18'),
(2, 1, 'view_profile', '{\"time\":\"2025-03-26 16:08:25\",\"changes\":[]}', '2025-03-26 09:08:25'),
(3, 1, 'view_resource_types', '{\"time\":\"2025-03-26 16:08:27\"}', '2025-03-26 09:08:27'),
(4, 1, 'code_backup', '{\"time\":\"2025-03-26 16:28:01\",\"changes\":{\"filename\":\"code_backup_2025-03-26_16-27-54.zip\"}}', '2025-03-26 09:28:01'),
(5, 1, 'backup_download', '{\"time\":\"2025-03-26 16:28:05\",\"changes\":{\"filename\":\"code_backup_2025-03-26_16-27-54.zip\"}}', '2025-03-26 09:28:05'),
(6, 1, 'view_resource_types', '{\"time\":\"2025-03-26 16:31:30\"}', '2025-03-26 09:31:30'),
(7, 1, 'create_fb_order', '{\"time\":\"2025-03-26 16:37:46\",\"order_id\":\"1\",\"details\":{\"fb_config_id\":\"1\",\"quantity\":1,\"source_id\":2,\"notification\":\"\"}}', '2025-03-26 09:37:46'),
(8, 1, 'update_fb_order_status', '{\"time\":\"2025-03-26 16:38:04\",\"order_id\":1,\"details\":{\"old_status\":\"Pending\",\"new_status\":\"Pending\",\"notification\":\"\\u0110\\u01a1n h\\u00e0ng c\\u1ee7a b\\u1ea1n\"}}', '2025-03-26 09:38:04'),
(9, 1, 'update_fb_order_status', '{\"time\":\"2025-03-26 16:38:14\",\"order_id\":1,\"details\":{\"old_status\":\"Pending\",\"new_status\":\"Processed\",\"notification\":\"\\u0110\\u01a1n h\\u00e0ng c\\u1ee7a b\\u1ea1n\"}}', '2025-03-26 09:38:14'),
(10, 1, 'update_fb_order_status', '{\"time\":\"2025-03-26 16:46:17\",\"order_id\":1,\"details\":{\"old_status\":\"Processed\",\"new_status\":\"Processed\",\"notification\":\"1231231231231213\"}}', '2025-03-26 09:46:17'),
(11, 1, 'update_fb_order_status', '{\"time\":\"2025-03-26 16:46:56\",\"order_id\":1,\"details\":{\"old_status\":\"Processed\",\"new_status\":\"Processed\",\"notification\":\"1231231231231213\"}}', '2025-03-26 09:46:56'),
(12, 1, 'create_fb_order', '{\"time\":\"2025-03-26 16:47:08\",\"order_id\":\"2\",\"details\":{\"fb_config_id\":\"1\",\"quantity\":11,\"source_id\":1,\"notification\":\"12223as\"}}', '2025-03-26 09:47:08'),
(13, 1, 'create_fb_order', '{\"time\":\"2025-03-26 16:56:01\",\"order_id\":\"3\",\"details\":{\"fb_config_id\":\"1\",\"quantity\":1,\"source_id\":1,\"notification\":\"nhi\\u1ec1u ti\\u1ec1n\"}}', '2025-03-26 09:56:01'),
(14, 1, 'update_fb_order_status', '{\"time\":\"2025-03-26 17:00:26\",\"order_id\":3,\"details\":{\"old_status\":\"Pending\",\"new_status\":\"Pending\",\"notification\":\"nhi\\u1ec1u ti\\u1ec1n\"}}', '2025-03-26 10:00:26'),
(15, 1, 'update_fb_order_status', '{\"time\":\"2025-03-26 17:00:41\",\"order_id\":3,\"details\":{\"old_status\":\"Pending\",\"new_status\":\"Processed\",\"notification\":\"nhi\\u1ec1u ti\\u1ec1n\"}}', '2025-03-26 10:00:41'),
(16, 1, 'update_fb_order_status', '{\"time\":\"2025-03-26 17:01:10\",\"order_id\":3,\"details\":{\"old_status\":\"Processed\",\"new_status\":\"Processed\",\"notification\":\"nhi\\u1ec1u ti\\u1ec1n\"}}', '2025-03-26 10:01:10'),
(17, 1, 'update_fb_order_status', '{\"time\":\"2025-03-26 17:03:00\",\"order_id\":3,\"details\":{\"old_status\":\"Processed\",\"new_status\":\"Processed\",\"notification\":\"nhi\\u1ec1u ti\\u1ec1n\"}}', '2025-03-26 10:03:00'),
(18, 1, 'update_fb_order_status', '{\"time\":\"2025-03-26 17:03:05\",\"order_id\":3,\"details\":{\"old_status\":\"Processed\",\"new_status\":\"Processed\",\"notification\":\"nhi\\u1ec1u ti\\u1ec1n\"}}', '2025-03-26 10:03:05'),
(19, 1, 'update_fb_order_status', '{\"time\":\"2025-03-26 17:03:16\",\"order_id\":3,\"details\":{\"old_status\":\"Processed\",\"new_status\":\"Processed\",\"notification\":\"nhi\\u1ec1u ti\\u1ec1n\"}}', '2025-03-26 10:03:16'),
(20, 1, 'update_fb_order_status', '{\"time\":\"2025-03-26 17:04:55\",\"order_id\":3,\"details\":{\"old_status\":\"Processed\",\"new_status\":\"Processed\",\"notification\":\"nhi\\u1ec1u ti\\u1ec1n\"}}', '2025-03-26 10:04:55'),
(21, 1, 'update_fb_order_status', '{\"time\":\"2025-03-26 17:05:01\",\"order_id\":3,\"details\":{\"old_status\":\"Processed\",\"new_status\":\"Processed\",\"notification\":\"nhi\\u1ec1u ti\\u1ec1n\"}}', '2025-03-26 10:05:01'),
(22, 1, 'update_fb_order_status', '{\"time\":\"2025-03-26 17:05:09\",\"order_id\":2,\"details\":{\"old_status\":\"Pending\",\"new_status\":\"Pending\",\"notification\":\"12223as\"}}', '2025-03-26 10:05:09'),
(23, 1, 'update_fb_order_status', '{\"time\":\"2025-03-26 17:06:48\",\"order_id\":2,\"details\":{\"old_status\":\"Pending\",\"new_status\":\"Pending\",\"notification\":\"12223as\"}}', '2025-03-26 10:06:48'),
(24, 1, 'update_fb_order_status', '{\"time\":\"2025-03-26 17:06:53\",\"order_id\":3,\"details\":{\"old_status\":\"Processed\",\"new_status\":\"Processed\",\"notification\":\"nhi\\u1ec1u ti\\u1ec1n\"}}', '2025-03-26 10:06:53'),
(25, 1, 'update_fb_order_status', '{\"time\":\"2025-03-26 17:06:59\",\"order_id\":3,\"details\":{\"old_status\":\"Processed\",\"new_status\":\"Processed\",\"notification\":\"nhi\\u1ec1u ti\\u1ec1n\"}}', '2025-03-26 10:06:59'),
(26, 1, 'update_fb_order_status', '{\"time\":\"2025-03-26 17:07:03\",\"order_id\":3,\"details\":{\"old_status\":\"Processed\",\"new_status\":\"Processed\",\"notification\":\"nhi\\u1ec1u ti\\u1ec1n\"}}', '2025-03-26 10:07:03'),
(27, 1, 'create_fb_order', '{\"time\":\"2025-03-26 17:07:16\",\"order_id\":\"4\",\"details\":{\"fb_config_id\":\"1\",\"quantity\":2,\"source_id\":3,\"notification\":\"test\"}}', '2025-03-26 10:07:16'),
(28, 1, 'update_fb_order_status', '{\"time\":\"2025-03-26 17:07:39\",\"order_id\":4,\"details\":{\"old_status\":\"Pending\",\"new_status\":\"Pending\",\"notification\":\"test\"}}', '2025-03-26 10:07:39'),
(29, 1, 'create_fb_order', '{\"time\":\"2025-03-26 17:09:05\",\"order_id\":\"5\",\"details\":{\"fb_config_id\":\"1\",\"quantity\":2,\"source_id\":1,\"notification\":\"112\"}}', '2025-03-26 10:09:05'),
(30, 1, 'update_fb_order_status', '{\"time\":\"2025-03-26 17:09:39\",\"order_id\":5,\"details\":{\"old_status\":\"Pending\",\"new_status\":\"Pending\",\"notification\":\"112\"}}', '2025-03-26 10:09:39'),
(31, 1, 'create_fb_order', '{\"time\":\"2025-03-26 17:12:23\",\"order_id\":\"6\",\"details\":{\"fb_config_id\":\"1\",\"quantity\":1,\"source_id\":1,\"notification\":\"31231231\"}}', '2025-03-26 10:12:23'),
(32, 1, 'view_dashboard', '{\"time\":\"2025-03-26 17:13:14\"}', '2025-03-26 10:13:14'),
(33, 1, 'create_fb_order', '{\"time\":\"2025-03-26 17:13:39\",\"order_id\":\"7\",\"details\":{\"fb_config_id\":\"8\",\"quantity\":1,\"source_id\":3,\"notification\":\"q\\u01b0\"}}', '2025-03-26 10:13:39'),
(34, 1, 'view_resource_types', '{\"time\":\"2025-03-26 17:19:41\"}', '2025-03-26 10:19:41'),
(35, 1, 'view_resource_types', '{\"time\":\"2025-03-26 17:20:14\"}', '2025-03-26 10:20:14'),
(36, 1, 'create_user', '{\"time\":\"2025-03-26 17:21:41\",\"target_user_id\":\"2\",\"details\":{\"username\":\"hellogirl\",\"role\":\"HKD\"}}', '2025-03-26 10:21:41'),
(37, 1, 'create_user', '{\"time\":\"2025-03-26 17:21:51\",\"target_user_id\":\"3\",\"details\":{\"username\":\"hellogirl01\",\"role\":\"USER_HKD\"}}', '2025-03-26 10:21:51'),
(38, 3, 'login', '{\"username\":\"hellogirl01\",\"success\":true,\"ip\":\"104.28.158.51\",\"time\":\"2025-03-26 17:22:09\"}', '2025-03-26 10:22:09'),
(39, 3, 'view_dashboard', '{\"time\":\"2025-03-26 17:22:09\"}', '2025-03-26 10:22:09'),
(40, 3, 'create_fb_order', '{\"time\":\"2025-03-26 17:22:32\",\"order_id\":\"8\",\"details\":{\"fb_config_id\":\"9\",\"quantity\":1,\"source_id\":1,\"notification\":\"\"}}', '2025-03-26 10:22:32'),
(41, 1, 'view_return_orders', '{\"time\":\"2025-03-26 17:22:35\",\"filters\":{\"status\":\"\",\"search\":\"\"}}', '2025-03-26 10:22:35'),
(42, 1, 'update_fb_order_status', '{\"time\":\"2025-03-26 17:22:45\",\"order_id\":8,\"details\":{\"old_status\":\"Pending\",\"new_status\":\"Processed\",\"notification\":\"\"}}', '2025-03-26 10:22:45'),
(43, 1, 'update_fb_order_status', '{\"time\":\"2025-03-26 17:22:45\",\"order_id\":8,\"details\":{\"old_status\":\"Processed\",\"new_status\":\"Processed\",\"notification\":\"\"}}', '2025-03-26 10:22:45'),
(44, 1, 'view_logs', '{\"time\":\"2025-03-26 17:23:24\",\"filters\":{\"user_id\":0,\"action\":\"\",\"start_date\":\"\",\"end_date\":\"\"},\"page\":1}', '2025-03-26 10:23:24'),
(45, 1, 'view_logs', '{\"time\":\"2025-03-26 17:24:15\",\"filters\":{\"user_id\":0,\"action\":\"\",\"start_date\":\"\",\"end_date\":\"\"},\"page\":1}', '2025-03-26 10:24:15'),
(46, 1, 'login', '{\"username\":\"admin\",\"success\":true,\"ip\":\"104.28.158.51\",\"time\":\"2025-03-26 18:16:12\"}', '2025-03-26 11:16:12'),
(47, 1, 'view_dashboard', '{\"time\":\"2025-03-26 18:16:12\"}', '2025-03-26 11:16:12'),
(48, 1, 'view_profile', '{\"time\":\"2025-03-26 18:17:34\",\"changes\":[]}', '2025-03-26 11:17:34'),
(49, 1, 'view_profile', '{\"time\":\"2025-03-26 18:18:21\",\"changes\":[]}', '2025-03-26 11:18:21'),
(50, 1, 'view_profile', '{\"time\":\"2025-03-26 18:18:31\",\"changes\":[]}', '2025-03-26 11:18:31'),
(51, 1, 'view_profile', '{\"time\":\"2025-03-26 18:18:39\",\"changes\":[]}', '2025-03-26 11:18:39'),
(52, 1, 'view_profile', '{\"time\":\"2025-03-26 18:24:54\",\"changes\":[]}', '2025-03-26 11:24:54'),
(53, 1, 'view_profile', '{\"time\":\"2025-03-26 18:27:15\",\"changes\":[]}', '2025-03-26 11:27:15'),
(54, 1, 'view_profile', '{\"time\":\"2025-03-26 18:28:27\",\"changes\":[]}', '2025-03-26 11:28:27'),
(55, 1, 'view_profile', '{\"time\":\"2025-03-26 18:29:38\",\"changes\":[]}', '2025-03-26 11:29:38'),
(56, 1, 'view_profile', '{\"time\":\"2025-03-26 18:30:02\",\"changes\":[]}', '2025-03-26 11:30:02'),
(57, 1, 'view_resource_types', '{\"time\":\"2025-03-26 18:30:14\"}', '2025-03-26 11:30:14'),
(58, 1, 'create_fb_order', '{\"time\":\"2025-03-26 18:35:11\",\"order_id\":\"9\",\"details\":{\"fb_config_id\":\"12\",\"quantity\":1,\"source_id\":3,\"notification\":\"\"}}', '2025-03-26 11:35:11'),
(59, 1, 'create_fb_order', '{\"time\":\"2025-03-26 18:35:24\",\"order_id\":\"10\",\"details\":{\"fb_config_id\":\"13\",\"quantity\":2,\"source_id\":3,\"notification\":\"\"}}', '2025-03-26 11:35:24'),
(60, 1, 'create_fb_order', '{\"time\":\"2025-03-26 18:35:30\",\"order_id\":\"11\",\"details\":{\"fb_config_id\":\"14\",\"quantity\":2,\"source_id\":3,\"notification\":\"\"}}', '2025-03-26 11:35:30'),
(61, 1, 'create_fb_order', '{\"time\":\"2025-03-26 18:35:39\",\"order_id\":\"12\",\"details\":{\"fb_config_id\":\"15\",\"quantity\":2,\"source_id\":2,\"notification\":\"\"}}', '2025-03-26 11:35:39'),
(62, 1, 'view_return_orders', '{\"time\":\"2025-03-26 18:36:55\",\"filters\":{\"status\":\"\",\"search\":\"\"}}', '2025-03-26 11:36:55'),
(63, 1, 'view_profile', '{\"time\":\"2025-03-26 18:42:49\",\"changes\":[]}', '2025-03-26 11:42:49'),
(64, 1, 'view_dashboard', '{\"time\":\"2025-03-26 18:42:51\"}', '2025-03-26 11:42:51'),
(65, 1, 'view_profile', '{\"time\":\"2025-03-26 18:42:52\",\"changes\":[]}', '2025-03-26 11:42:52'),
(66, 1, 'view_return_orders', '{\"time\":\"2025-03-26 18:42:59\",\"filters\":{\"status\":\"\",\"search\":\"\"}}', '2025-03-26 11:42:59'),
(67, 1, 'view_resource_types', '{\"time\":\"2025-03-26 18:46:59\"}', '2025-03-26 11:46:59'),
(68, 1, 'view_resource_types', '{\"time\":\"2025-03-26 18:56:22\"}', '2025-03-26 11:56:22'),
(69, 1, 'view_resource_types', '{\"time\":\"2025-03-26 18:57:17\"}', '2025-03-26 11:57:17'),
(70, 1, 'view_logs', '{\"time\":\"2025-03-26 18:57:21\",\"filters\":{\"user_id\":0,\"action\":\"\",\"start_date\":\"\",\"end_date\":\"\"},\"page\":1}', '2025-03-26 11:57:21'),
(71, 1, 'view_logs', '{\"time\":\"2025-03-26 19:02:13\",\"filters\":{\"user_id\":0,\"action\":\"\",\"start_date\":\"\",\"end_date\":\"\"},\"page\":1}', '2025-03-26 12:02:13'),
(72, 1, 'view_logs', '{\"time\":\"2025-03-26 19:04:41\",\"filters\":{\"user_id\":0,\"action\":\"\",\"start_date\":\"\",\"end_date\":\"\"},\"page\":1}', '2025-03-26 12:04:41'),
(73, 1, 'view_logs', '{\"time\":\"2025-03-26 19:05:25\",\"filters\":{\"user_id\":0,\"action\":\"\",\"start_date\":\"\",\"end_date\":\"\"},\"page\":1}', '2025-03-26 12:05:25'),
(74, 1, 'view_resource_types', '{\"time\":\"2025-03-26 19:08:52\",\"page\":1}', '2025-03-26 12:08:52'),
(75, 1, 'view_resource_types', '{\"time\":\"2025-03-26 19:08:54\",\"page\":1}', '2025-03-26 12:08:54'),
(76, 1, 'add_resource_type', '{\"time\":\"2025-03-26 19:08:57\",\"resource_id\":\"1\",\"details\":{\"name\":\"1\"}}', '2025-03-26 12:08:57'),
(77, 1, 'view_resource_types', '{\"time\":\"2025-03-26 19:08:57\",\"page\":1}', '2025-03-26 12:08:57'),
(78, 1, 'add_resource_type', '{\"time\":\"2025-03-26 19:08:58\",\"resource_id\":\"2\",\"details\":{\"name\":\"2\"}}', '2025-03-26 12:08:58'),
(79, 1, 'view_resource_types', '{\"time\":\"2025-03-26 19:08:58\",\"page\":1}', '2025-03-26 12:08:58'),
(80, 1, 'add_resource_type', '{\"time\":\"2025-03-26 19:09:00\",\"resource_id\":\"3\",\"details\":{\"name\":\"3\"}}', '2025-03-26 12:09:00'),
(81, 1, 'view_resource_types', '{\"time\":\"2025-03-26 19:09:00\",\"page\":1}', '2025-03-26 12:09:00'),
(82, 1, 'add_resource_type', '{\"time\":\"2025-03-26 19:09:01\",\"resource_id\":\"4\",\"details\":{\"name\":\"4\"}}', '2025-03-26 12:09:01'),
(83, 1, 'view_resource_types', '{\"time\":\"2025-03-26 19:09:01\",\"page\":1}', '2025-03-26 12:09:01'),
(84, 1, 'add_resource_type', '{\"time\":\"2025-03-26 19:09:03\",\"resource_id\":\"5\",\"details\":{\"name\":\"5\"}}', '2025-03-26 12:09:03'),
(85, 1, 'view_resource_types', '{\"time\":\"2025-03-26 19:09:03\",\"page\":1}', '2025-03-26 12:09:03'),
(86, 1, 'add_resource_type', '{\"time\":\"2025-03-26 19:09:05\",\"resource_id\":\"6\",\"details\":{\"name\":\"6\"}}', '2025-03-26 12:09:05'),
(87, 1, 'view_resource_types', '{\"time\":\"2025-03-26 19:09:05\",\"page\":1}', '2025-03-26 12:09:05'),
(88, 1, 'view_resource_types', '{\"time\":\"2025-03-26 19:19:00\",\"page\":1}', '2025-03-26 12:19:00'),
(89, 1, 'view_resource_types', '{\"time\":\"2025-03-26 19:19:06\",\"page\":1}', '2025-03-26 12:19:06'),
(90, 1, 'view_return_orders', '{\"time\":\"2025-03-26 19:22:22\",\"filters\":{\"status\":\"\",\"search\":\"\"}}', '2025-03-26 12:22:22'),
(91, 1, 'view_resource_types', '{\"time\":\"2025-03-26 19:22:30\",\"page\":1}', '2025-03-26 12:22:30'),
(92, 1, 'view_return_orders', '{\"time\":\"2025-03-26 19:23:52\",\"filters\":{\"status\":\"\",\"search\":\"\"}}', '2025-03-26 12:23:52'),
(93, 1, 'view_profile', '{\"time\":\"2025-03-26 19:23:54\",\"changes\":[]}', '2025-03-26 12:23:54'),
(94, 1, 'view_return_orders', '{\"time\":\"2025-03-26 19:23:55\",\"filters\":{\"status\":\"\",\"search\":\"\"}}', '2025-03-26 12:23:55'),
(95, 1, 'view_return_orders', '{\"time\":\"2025-03-26 19:28:27\",\"filters\":{\"status\":\"\",\"search\":\"\"}}', '2025-03-26 12:28:27'),
(96, 2, 'login', '{\"username\":\"hellogirl\",\"success\":true,\"ip\":\"104.28.158.51\",\"time\":\"2025-03-26 19:28:44\"}', '2025-03-26 12:28:44'),
(97, 2, 'view_dashboard', '{\"time\":\"2025-03-26 19:28:44\"}', '2025-03-26 12:28:44'),
(98, 2, 'view_profile', '{\"time\":\"2025-03-26 19:28:48\",\"changes\":[]}', '2025-03-26 12:28:48'),
(99, 2, 'view_profile', '{\"time\":\"2025-03-26 19:29:12\",\"changes\":[]}', '2025-03-26 12:29:12'),
(100, 2, 'view_dashboard', '{\"time\":\"2025-03-26 19:29:13\"}', '2025-03-26 12:29:13'),
(101, 2, 'view_return_orders', '{\"time\":\"2025-03-26 19:29:29\",\"filters\":{\"status\":\"\",\"search\":\"\"}}', '2025-03-26 12:29:29'),
(102, 1, 'view_resource_types', '{\"time\":\"2025-03-26 19:31:05\",\"page\":1}', '2025-03-26 12:31:05'),
(103, 1, 'view_resource_types', '{\"time\":\"2025-03-26 19:31:08\",\"page\":1}', '2025-03-26 12:31:08'),
(104, 1, 'view_resource_types', '{\"time\":\"2025-03-26 19:31:32\",\"page\":1}', '2025-03-26 12:31:32'),
(105, 1, 'add_resource_type', '{\"time\":\"2025-03-26 19:31:37\",\"resource_id\":\"7\",\"details\":{\"name\":\"7\"}}', '2025-03-26 12:31:37'),
(106, 1, 'view_resource_types', '{\"time\":\"2025-03-26 19:31:37\",\"page\":1}', '2025-03-26 12:31:37'),
(107, 1, 'add_resource_type', '{\"time\":\"2025-03-26 19:31:40\",\"resource_id\":\"8\",\"details\":{\"name\":\"8\"}}', '2025-03-26 12:31:40'),
(108, 1, 'view_resource_types', '{\"time\":\"2025-03-26 19:31:40\",\"page\":1}', '2025-03-26 12:31:40'),
(109, 1, 'add_resource_type', '{\"time\":\"2025-03-26 19:31:42\",\"resource_id\":\"9\",\"details\":{\"name\":\"9\"}}', '2025-03-26 12:31:42'),
(110, 1, 'view_resource_types', '{\"time\":\"2025-03-26 19:31:42\",\"page\":1}', '2025-03-26 12:31:42'),
(111, 1, 'view_dashboard', '{\"time\":\"2025-03-26 19:31:51\"}', '2025-03-26 12:31:51'),
(112, 1, 'view_dashboard', '{\"time\":\"2025-03-26 19:38:22\"}', '2025-03-26 12:38:22'),
(113, 1, 'view_dashboard', '{\"time\":\"2025-03-26 19:38:39\"}', '2025-03-26 12:38:39'),
(114, 1, 'view_profile', '{\"time\":\"2025-03-26 20:05:43\",\"changes\":[]}', '2025-03-26 13:05:43'),
(115, 1, 'view_profile', '{\"time\":\"2025-03-26 20:05:48\",\"changes\":[]}', '2025-03-26 13:05:48'),
(116, 1, 'view_return_orders', '{\"time\":\"2025-03-26 20:06:12\",\"filters\":{\"status\":\"\",\"search\":\"\"}}', '2025-03-26 13:06:12'),
(117, 1, 'update_fb_order_status', '{\"time\":\"2025-03-26 20:06:29\",\"order_id\":12,\"details\":{\"old_status\":\"Pending\",\"new_status\":\"Pending\",\"notification\":\"\"}}', '2025-03-26 13:06:29'),
(118, 1, 'view_dashboard', '{\"time\":\"2025-03-26 20:06:46\"}', '2025-03-26 13:06:46'),
(119, 1, 'view_dashboard', '{\"time\":\"2025-03-26 20:06:46\"}', '2025-03-26 13:06:46'),
(120, 1, 'view_profile', '{\"time\":\"2025-03-26 20:06:51\",\"changes\":[]}', '2025-03-26 13:06:51'),
(121, 1, 'view_profile', '{\"time\":\"2025-03-26 20:07:02\",\"changes\":[]}', '2025-03-26 13:07:02'),
(122, 1, 'update_avatar', '{\"time\":\"2025-03-26 20:07:02\",\"changes\":{\"avatar\":\"changed\"}}', '2025-03-26 13:07:02'),
(123, 1, 'view_dashboard', '{\"time\":\"2025-03-26 20:07:03\"}', '2025-03-26 13:07:03'),
(124, 1, 'view_profile', '{\"time\":\"2025-03-26 20:07:05\",\"changes\":[]}', '2025-03-26 13:07:05'),
(125, 1, 'view_return_orders', '{\"time\":\"2025-03-26 20:07:08\",\"filters\":{\"status\":\"\",\"search\":\"\"}}', '2025-03-26 13:07:08'),
(126, 1, 'view_return_orders', '{\"time\":\"2025-03-26 20:07:11\",\"filters\":{\"status\":\"\",\"search\":\"\"}}', '2025-03-26 13:07:11'),
(127, 1, 'view_return_orders', '{\"time\":\"2025-03-26 20:07:17\",\"filters\":{\"status\":\"\",\"search\":\"\"}}', '2025-03-26 13:07:17'),
(128, 1, 'view_return_orders', '{\"time\":\"2025-03-26 20:07:26\",\"filters\":{\"status\":\"\",\"search\":\"\"}}', '2025-03-26 13:07:26');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `orders`
--

CREATE TABLE `orders` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `resource_type` int NOT NULL,
  `quantity` int NOT NULL,
  `source` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `notification` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `resource_types`
--

CREATE TABLE `resource_types` (
  `id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `resource_types`
--

INSERT INTO `resource_types` (`id`, `name`) VALUES
(1, '1'),
(2, '2'),
(3, '3'),
(4, '4'),
(5, '5'),
(6, '6'),
(7, '7'),
(8, '8'),
(9, '9');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `parent_id` int DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`, `created_at`, `status`, `parent_id`, `avatar`) VALUES
(1, 'admin', '$2y$10$YZeJ9b2ao7bk3cHU62O4d.O35JtUu3Fj6p0UqOGTBAcAc/3bJZSE6', 'admin@example.com', 'ADMIN', '2025-03-26 09:08:16', 1, NULL, 'https://cellphones.com.vn/sforum/wp-content/uploads/2024/02/anh-avatar-cute-95.jpg'),
(2, 'hellogirl', '$2y$10$mlIxsLnwzT/VviQXt6Cc9.NRHQjS08aG4QHacDqOro1d/ddSU08Ia', 'vipsphi@gmail.com', 'HKD', '2025-03-26 10:21:41', 1, NULL, NULL),
(3, 'hellogirl01', '$2y$10$E2NidHp5IramzFyt/gHV9eM/RzdCcQAxEgQ2VnAn6KGJXEJBUrpe.', 'vipsphi+55@gmail.com', 'USER_HKD', '2025-03-26 10:21:51', 1, 2, NULL);

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `fb_account_types`
--
ALTER TABLE `fb_account_types`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `fb_configs`
--
ALTER TABLE `fb_configs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_type_id` (`account_type_id`);

--
-- Chỉ mục cho bảng `fb_orders`
--
ALTER TABLE `fb_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fb_config_id` (`fb_config_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Chỉ mục cho bảng `fb_sources`
--
ALTER TABLE `fb_sources`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `resource_types`
--
ALTER TABLE `resource_types`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `fb_account_types`
--
ALTER TABLE `fb_account_types`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT cho bảng `fb_configs`
--
ALTER TABLE `fb_configs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT cho bảng `fb_orders`
--
ALTER TABLE `fb_orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT cho bảng `fb_sources`
--
ALTER TABLE `fb_sources`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT cho bảng `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=129;

--
-- AUTO_INCREMENT cho bảng `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `resource_types`
--
ALTER TABLE `resource_types`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `fb_configs`
--
ALTER TABLE `fb_configs`
  ADD CONSTRAINT `fb_configs_ibfk_1` FOREIGN KEY (`account_type_id`) REFERENCES `fb_account_types` (`id`);

--
-- Các ràng buộc cho bảng `fb_orders`
--
ALTER TABLE `fb_orders`
  ADD CONSTRAINT `fb_orders_ibfk_1` FOREIGN KEY (`fb_config_id`) REFERENCES `fb_configs` (`id`),
  ADD CONSTRAINT `fb_orders_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
