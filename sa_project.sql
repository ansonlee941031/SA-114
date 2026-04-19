-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- 主機： 127.0.0.1
-- 產生時間： 2026-04-19 07:19:57
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
-- 資料表結構 `cafe_hours`
--

CREATE TABLE `cafe_hours` (
  `hour_id` int(11) NOT NULL,
  `cafe_id` int(11) NOT NULL,
  `day_of_week` tinyint(4) DEFAULT NULL COMMENT '1=一, 7=日',
  `open_time` time DEFAULT NULL,
  `close_time` time DEFAULT NULL,
  `is_closed` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `cafe_hours`
--

INSERT INTO `cafe_hours` (`hour_id`, `cafe_id`, `day_of_week`, `open_time`, `close_time`, `is_closed`) VALUES
(1, 1, 1, '08:00:00', '14:00:00', 0),
(2, 1, 2, '08:00:00', '14:00:00', 0),
(3, 1, 3, '08:00:00', '14:00:00', 0),
(4, 1, 4, '08:00:00', '14:00:00', 0),
(5, 1, 5, NULL, NULL, 1),
(6, 1, 6, NULL, NULL, 1),
(7, 1, 7, NULL, NULL, 1);

-- --------------------------------------------------------

--
-- 資料表結構 `cafe_shop`
--

CREATE TABLE `cafe_shop` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL COMMENT '店名',
  `address` varchar(255) DEFAULT NULL COMMENT '地址',
  `phone` varchar(20) DEFAULT NULL COMMENT '電話',
  `opening_hours` varchar(255) DEFAULT NULL COMMENT '營業時間',
  `distance_meters` int(11) DEFAULT NULL COMMENT '距離(公尺)',
  `rating` decimal(2,1) DEFAULT NULL COMMENT '評價 (1.0-5.0)',
  `min_consumption` int(11) DEFAULT 0 COMMENT '最低消費金額'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `cafe_shop`
--

INSERT INTO `cafe_shop` (`id`, `name`, `address`, `phone`, `opening_hours`, `distance_meters`, `rating`, `min_consumption`) VALUES
(1, '輔大一粒麥-舒德門市', '242新北市新莊區三泰路88號', '02 2905 5239', '08:00–14:00\r\n', 179, 4.3, 0),
(2, '哈姆喫茶 hamu kissa', '242新北市新莊區中正路514巷25號', '0229042722', '14:00–20:00', 144, 4.9, 100),
(3, '工寓咖啡 café industry', '242新北市新莊區中正路593號2樓', '0229040024', '11:00–21:00', 133, 4.3, 110),
(4, '楓橋', '242新北市新莊區中正路496號', '0229062186', '10:30–22:30', 199, 4.6, 80),
(5, '彼得好咖啡 新莊輔大店', '242新北市新莊區福營路183號1樓', '0229080158', '07:30–17:30', 328, 4.6, 0),
(6, '輔大一粒麥-國璽門市', '242新北市新莊區三泰路88號', '0229055239', '08:00–14:00', 955, 3.8, 0),
(7, '每一杯咖啡', '242新北市新莊區中正路510號內聖言樓B1', '0229056533', '10:30–18:00', 386, 4.2, 0),
(8, 'STARBUCKS 星巴克 (新莊尚德門市)', '242新北市新莊區中正路518號', '0229019826', '07:30–20:30', 210, 4.1, 0),
(9, 'Louisa Coffee 路易莎咖啡(輔大烘豆概念門市)', '242新北市新莊區建國一路55號', '0229012570', '08:00–21:00', 260, 3.8, 0),
(10, '餐旅系生活午茶', '242新北市新莊區中正路510號', '0229053500', '12:00–17:30', 219, 4.6, 0);

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
  `戶外座位` tinyint(1) NOT NULL DEFAULT 0,
  `甜點` tinyint(1) NOT NULL DEFAULT 0,
  `廁所` tinyint(1) NOT NULL DEFAULT 0,
  `低消` int(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `label`
--

INSERT INTO `label` (`cafe_id`, `name`, `插座`, `不限時`, `停車位`, `wifi`, `戶外座位`, `甜點`, `廁所`, `低消`) VALUES
(1, '一粒麥', 0, 0, 0, 0, 0, 0, 0, 0),
(2, '哈姆喫茶 hamu kissa', 0, 0, 0, 0, 0, 0, 0, NULL),
(3, '工寓咖啡 café industry', 0, 0, 0, 0, 0, 0, 0, NULL),
(4, '楓橋', 0, 0, 0, 0, 0, 0, 0, NULL),
(5, '彼得好咖啡 新莊輔大店', 0, 0, 0, 0, 0, 0, 0, NULL),
(6, '輔大一粒麥-國璽門市', 0, 0, 0, 0, 0, 0, 0, NULL),
(7, '每一杯咖啡', 0, 0, 0, 0, 0, 0, 0, NULL),
(8, 'STARBUCKS 星巴克 (新莊尚德門市)', 0, 0, 0, 0, 0, 0, 0, NULL),
(9, 'Louisa Coffee 路易莎咖啡(輔大烘豆概念門市)', 0, 0, 0, 0, 0, 0, 0, NULL),
(10, '餐旅系生活午茶', 0, 0, 0, 0, 0, 0, 0, NULL);

--
-- 已傾印資料表的索引
--

--
-- 資料表索引 `cafe_hours`
--
ALTER TABLE `cafe_hours`
  ADD PRIMARY KEY (`hour_id`),
  ADD KEY `cafe_id` (`cafe_id`);

--
-- 資料表索引 `cafe_shop`
--
ALTER TABLE `cafe_shop`
  ADD PRIMARY KEY (`id`),
  ADD KEY `distance_meters` (`distance_meters`);

--
-- 資料表索引 `label`
--
ALTER TABLE `label`
  ADD PRIMARY KEY (`cafe_id`);

--
-- 在傾印的資料表使用自動遞增(AUTO_INCREMENT)
--

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `cafe_hours`
--
ALTER TABLE `cafe_hours`
  MODIFY `hour_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `cafe_shop`
--
ALTER TABLE `cafe_shop`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- 已傾印資料表的限制式
--

--
-- 資料表的限制式 `cafe_hours`
--
ALTER TABLE `cafe_hours`
  ADD CONSTRAINT `cafe_hours_ibfk_1` FOREIGN KEY (`cafe_id`) REFERENCES `cafe_shop` (`id`) ON DELETE CASCADE;

--
-- 資料表的限制式 `label`
--
ALTER TABLE `label`
  ADD CONSTRAINT `label_ibfk_1` FOREIGN KEY (`cafe_id`) REFERENCES `cafe_shop` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
