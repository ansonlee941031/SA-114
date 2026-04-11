-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- 主機： 127.0.0.1
-- 產生時間： 2026-04-11 10:42:22
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
  `opening_hours` varchar(255) DEFAULT NULL COMMENT '營業時間'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `cafe_shop`
--

INSERT INTO `cafe_shop` (`id`, `name`, `address`, `phone`, `opening_hours`) VALUES
(1, '一粒麥', '242新北市新莊區三泰路88號', '02 2905 5239', '08:00–14:00\r\n'),
(2, '夢咖啡 Cafe Moose', '新北市三重區捷運路77號', '02 2989 7709', '11:00–21:00');

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
(2, '夢咖啡 Cafe Moose', 0, 0, 0, 0, 0, 0, 0, 0);

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
  ADD PRIMARY KEY (`id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
