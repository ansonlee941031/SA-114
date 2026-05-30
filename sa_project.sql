-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- 主機： 127.0.0.1
-- 產生時間： 2026-05-30 16:07:37
-- 伺服器版本： 10.4.32-MariaDB
-- PHP 版本： 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 資料庫： `sa_project`
--

-- --------------------------------------------------------

--
-- 資料表結構 `label`
--

CREATE TABLE `label` (
  `cafe_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `插座` tinyint(1) NOT NULL DEFAULT 0,
  `不限時` tinyint(1) NOT NULL DEFAULT 0,
  `停車位` tinyint(1) NOT NULL DEFAULT 0,
  `wifi` tinyint(1) NOT NULL DEFAULT 0,
  `甜點` tinyint(1) NOT NULL DEFAULT 0,
  `廁所` tinyint(1) NOT NULL DEFAULT 0,
  `低消` int(10) DEFAULT NULL,
  `室內座位` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `label`
--

INSERT INTO `label` (`cafe_id`, `name`, `插座`, `不限時`, `停車位`, `wifi`, `甜點`, `廁所`, `低消`, `室內座位`) VALUES
(1, '輔大一粒麥-舒德門市', 0, 0, 1, 1, 1, 1, 0, 0),
(2, '哈姆喫茶 hamu kissa', 1, 1, 0, 1, 1, 1, NULL, 1),
(3, '工寓咖啡 café industry', 1, 1, 1, 1, 1, 1, NULL, 1),
(4, '楓橋', 0, 0, 0, 1, 1, 1, NULL, 1),
(5, '彼得好咖啡 新莊輔大店', 1, 1, 1, 1, 1, 1, NULL, 1),
(6, '輔大一粒麥-國璽門市', 0, 0, 1, 1, 1, 1, NULL, 0),
(7, '每一杯咖啡', 0, 0, 1, 1, 1, 1, NULL, 1),
(8, 'STARBUCKS 星巴克 (新莊尚德門市)', 1, 1, 1, 1, 1, 1, NULL, 1),
(9, 'Louisa Coffee 路易莎咖啡(輔大烘豆概念門市)', 1, 1, 1, 1, 1, 1, NULL, 1),
(10, '餐旅系生活午茶', 0, 0, 1, 1, 1, 1, NULL, 1),
(172, '丰錞咖啡所', 1, 1, 1, 1, 0, 0, 100, 1),
(173, '擼貓 (中途貓之家)', 0, 1, 0, 0, 0, 0, 150, 1),
(174, '客美多咖啡 新莊幸福店', 0, 0, 0, 1, 1, 0, 100, 1),
(175, 'May i COFFEE you? 美艾咖啡友', 0, 1, 1, 1, 0, 1, 120, 1),
(176, '目黑咖啡', 0, 0, 0, 0, 0, 0, 150, 0),
(177, 'Meteore Labo', 0, 1, 0, 1, 0, 0, 130, 1),
(178, '漂夢島甜點工作室', 1, 0, 0, 1, 1, 0, 200, 1),
(179, '續命咖啡', 0, 0, 0, 1, 1, 1, 80, 0),
(180, '何止', 0, 0, 1, 0, 0, 1, 160, 0),
(181, '林椐咖啡', 1, 0, 0, 1, 1, 1, 150, 1),
(182, '山林咖啡', 0, 1, 1, 0, 0, 0, 100, 0),
(183, '艸堂咖啡', 1, 1, 1, 1, 1, 0, 110, 0),
(184, '來福咖啡 Laifu coffee', 0, 0, 1, 0, 0, 1, 120, 1),
(185, '日豐企業行', 0, 1, 0, 1, 0, 1, 150, 0),
(186, 'D\'or caf\'e 兜咖啡', 1, 0, 1, 1, 1, 1, 180, 1),
(187, '工寓咖啡', 0, 0, 1, 0, 0, 0, 100, 0),
(188, 'M&Y CAFE', 1, 1, 0, 1, 1, 1, 150, 1),
(189, '反轉點咖啡', 0, 0, 1, 0, 0, 1, 130, 1),
(190, '山納斯咖啡', 0, 0, 0, 0, 0, 1, 120, 1),
(191, '倆口早午餐', 0, 0, 1, 0, 1, 1, 150, 1),
(192, '怪獸天團珈琲所', 1, 0, 1, 0, 0, 1, 140, 1),
(193, '星河咖啡', 0, 1, 0, 0, 1, 1, 100, 0),
(194, '禾豐珈琲館', 0, 1, 0, 0, 1, 0, 120, 1),
(195, 'Paws Cafe', 0, 0, 1, 0, 1, 0, 150, 0),
(196, '朝暮豆行', 1, 0, 1, 0, 0, 1, 100, 1),
(197, '布納咖啡館', 0, 0, 1, 0, 1, 0, 200, 1),
(198, '左轉靠右', 1, 0, 0, 1, 0, 0, 150, 1),
(199, '聊寓 La Vio', 1, 0, 0, 1, 1, 0, 160, 0),
(200, 'Queen SHOP Lifestore', 1, 0, 0, 1, 1, 1, 120, 1),
(201, '龍火 Panacea', 1, 1, 1, 0, 1, 1, 200, 0),
(202, '獨角獸 unicorn', 0, 1, 0, 0, 1, 0, 180, 0);

--
-- 已傾印資料表的索引
--

--
-- 資料表索引 `label`
--
ALTER TABLE `label`
  ADD PRIMARY KEY (`cafe_id`);

--
-- 已傾印資料表的限制式
--

--
-- 資料表的限制式 `label`
--
ALTER TABLE `label`
  ADD CONSTRAINT `label_ibfk_1` FOREIGN KEY (`cafe_id`) REFERENCES `cafe_shop` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
